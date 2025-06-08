<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_Transfer_Controller {
    
    private $db;
    
    public function __construct() {
        global $gestion_almacenes_db;
        $this->db = $gestion_almacenes_db;
        
        // Registrar hooks AJAX
        add_action('wp_ajax_gab_search_products_transfer', array($this, 'ajax_search_products'));
        add_action('wp_ajax_gab_get_warehouse_stock_transfer', array($this, 'ajax_get_warehouse_stock'));
        add_action('wp_ajax_gab_create_transfer', array($this, 'ajax_create_transfer'));
        add_action('wp_ajax_gab_get_transfer_list', array($this, 'ajax_get_transfer_list'));
        add_action('wp_ajax_gab_update_transfer', array($this, 'ajax_update_transfer'));
        add_action('wp_ajax_gab_delete_transfer', array($this, 'ajax_delete_transfer'));
        add_action('wp_ajax_gab_complete_transfer', [$this, 'ajax_complete_transfer']);
        add_action('wp_ajax_gab_get_warehouse', array($this, 'ajax_get_warehouse'));
        add_action('wp_ajax_gab_update_warehouse', array($this, 'ajax_update_warehouse'));

    }

    /**
     * Crear nueva transferencia
     */
    public function create_transfer($data) {
        global $wpdb;
        
        try {
            // Validar datos
            if (empty($data['source_warehouse']) || empty($data['target_warehouse']) || empty($data['products'])) {
                throw new Exception(__('Datos incompletos para crear la transferencia', 'gestion-almacenes'));
            }
            
            if ($data['source_warehouse'] == $data['target_warehouse']) {
                throw new Exception(__('Los almacenes origen y destino deben ser diferentes', 'gestion-almacenes'));
            }
            
            $wpdb->query('START TRANSACTION');
            
            // Crear transferencia principal
            $transfer_data = array(
                'source_warehouse_id' => intval($data['source_warehouse']),
                'target_warehouse_id' => intval($data['target_warehouse']),
                'status' => 'draft',
                'notes' => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            );
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'gab_stock_transfers',
                $transfer_data,
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            $transfer_id = $wpdb->insert_id;
            
            // Agregar productos
            foreach ($data['products'] as $product) {
                $product_data = array(
                    'transfer_id' => $transfer_id,
                    'product_id' => intval($product['product_id']),
                    'requested_qty' => intval($product['quantity'])
                );
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'gab_stock_transfer_items',
                    $product_data,
                    array('%d', '%d', '%d')
                );
                
                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
            }
            
            $wpdb->query('COMMIT');
            
            return array(
                'success' => true,
                'transfer_id' => $transfer_id,
                'message' => __('Transferencia creada exitosamente', 'gestion-almacenes')
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Obtener lista de transferencias
     */
    public function get_transfers() {
        global $wpdb;
        
        $query = "
            SELECT t.*, 
                u.display_name as created_by_name,
                wa_source.name as source_warehouse_name,
                wa_target.name as target_warehouse_name
            FROM {$wpdb->prefix}gab_stock_transfers t
            LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
            LEFT JOIN {$wpdb->prefix}gab_warehouses wa_source ON t.source_warehouse_id = wa_source.id
            LEFT JOIN {$wpdb->prefix}gab_warehouses wa_target ON t.target_warehouse_id = wa_target.id
            ORDER BY t.created_at DESC
        ";
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Obtener transferencia específica con sus items
     */
    public function get_transfer($transfer_id) {
        global $wpdb;
        
        // Obtener transferencia principal
        $transfer = $wpdb->get_row($wpdb->prepare("
            SELECT t.*, 
                u.display_name as created_by_name,
                wa_source.name as source_warehouse_name,
                wa_target.name as target_warehouse_name
            FROM {$wpdb->prefix}gab_stock_transfers t
            LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
            LEFT JOIN {$wpdb->prefix}gab_warehouses wa_source ON t.source_warehouse_id = wa_source.id
            LEFT JOIN {$wpdb->prefix}gab_warehouses wa_target ON t.target_warehouse_id = wa_target.id
            WHERE t.id = %d
        ", $transfer_id));
        
        if (!$transfer) {
            return null;
        }
        
        // Obtener items de la transferencia
        $transfer->items = $wpdb->get_results($wpdb->prepare("
            SELECT ti.*, p.post_title as product_name
            FROM {$wpdb->prefix}gab_stock_transfer_items ti
            LEFT JOIN {$wpdb->posts} p ON ti.product_id = p.ID
            WHERE ti.transfer_id = %d
            ORDER BY ti.id
        ", $transfer_id));
        
        return $transfer;
    }
    
    /**
     * Buscar productos para transferencia
     */
    public function search_products($search_term) {
        if (strlen($search_term) < 2) {
            return array();
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $search_term,
            'meta_query' => array(
                array(
                    'key' => '_manage_stock',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );
        
        $products = get_posts($args);
        $results = array();
        
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if ($product && $product->managing_stock()) {
                $results[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku()
                );
            }
        }
        
        return $results;
    }
    
    // Métodos AJAX
    
    public function ajax_create_transfer() {
        check_ajax_referer('gab_transfer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos suficientes', 'gestion-almacenes')));
            return;
        }
        
        $data = array(
            'source_warehouse' => isset($_POST['source_warehouse']) ? intval($_POST['source_warehouse']) : 0,
            'target_warehouse' => isset($_POST['target_warehouse']) ? intval($_POST['target_warehouse']) : 0,
            'products' => isset($_POST['products']) ? $_POST['products'] : array(),
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : ''
        );
        
        $result = $this->create_transfer($data);
        
        if ($result['success']) {
            // Agregar las URLs al resultado existente
            $result['redirect_url'] = admin_url('admin.php?page=gab-stock-transfers');
            $result['print_url'] = admin_url('admin.php?page=gab-print-transfer&transfer_id=' . $result['transfer_id']);
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    public function ajax_search_products() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gab_transfer_nonce')) {
            wp_send_json_error(array('message' => __('Token de seguridad inválido', 'gestion-almacenes')));
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos suficientes', 'gestion-almacenes')));
            return;
        }
        
        $search_term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        
        if (strlen($search_term) < 2) {
            wp_send_json_success(array('products' => array()));
            return;
        }
        
        try {
            // Buscar en productos simples y variables
            $args = array(
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'publish',
                'posts_per_page' => 30,
                's' => $search_term,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_manage_stock',
                        'value' => 'yes',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_stock_status',
                        'value' => 'instock',
                        'compare' => '='
                    )
                )
            );
            
            $products_query = new WP_Query($args);
            $results = array();
            
            if ($products_query->have_posts()) {
                while ($products_query->have_posts()) {
                    $products_query->the_post();
                    global $post;
                    
                    $product = wc_get_product($post->ID);
                    if (!$product) continue;
                    
                    // Solo incluir productos que gestionen stock o tengan stock
                    if ($product->managing_stock() || $product->get_stock_status() === 'instock') {
                        $product_name = $product->get_name();
                        
                        // Para variaciones, incluir atributos
                        if ($product->is_type('variation')) {
                            $parent = wc_get_product($product->get_parent_id());
                            if ($parent) {
                                $variation_attributes = $product->get_variation_attributes();
                                $formatted_attributes = array();
                                
                                foreach ($variation_attributes as $attribute_name => $attribute_value) {
                                    $taxonomy = str_replace('attribute_', '', $attribute_name);
                                    
                                    if (taxonomy_exists($taxonomy)) {
                                        $term = get_term_by('slug', $attribute_value, $taxonomy);
                                        if ($term) {
                                            $attribute_value = $term->name;
                                        }
                                    }
                                    
                                    $attribute_label = wc_attribute_label($taxonomy);
                                    $formatted_attributes[] = $attribute_label . ': ' . $attribute_value;
                                }
                                
                                if (!empty($formatted_attributes)) {
                                    $product_name = $parent->get_name() . ' (' . implode(', ', $formatted_attributes) . ')';
                                }
                            }
                        }
                        
                        $results[] = array(
                            'id' => $product->get_id(),
                            'name' => $product_name,
                            'sku' => $product->get_sku() ?: ''
                        );
                    }
                }
                wp_reset_postdata();
            }
            
            // También buscar por SKU específicamente
            if (!empty($search_term)) {
                $sku_args = array(
                    'post_type' => array('product', 'product_variation'),
                    'post_status' => 'publish',
                    'posts_per_page' => 20,
                    'meta_query' => array(
                        'relation' => 'AND',
                        array(
                            'key' => '_sku',
                            'value' => $search_term,
                            'compare' => 'LIKE'
                        ),
                        array(
                            'relation' => 'OR',
                            array(
                                'key' => '_manage_stock',
                                'value' => 'yes',
                                'compare' => '='
                            ),
                            array(
                                'key' => '_stock_status',
                                'value' => 'instock',
                                'compare' => '='
                            )
                        )
                    )
                );
                
                $sku_query = new WP_Query($sku_args);
                
                if ($sku_query->have_posts()) {
                    while ($sku_query->have_posts()) {
                        $sku_query->the_post();
                        global $post;
                        
                        $product = wc_get_product($post->ID);
                        if (!$product) continue;
                        
                        // Verificar si ya está en los resultados
                        $already_exists = false;
                        foreach ($results as $existing) {
                            if ($existing['id'] == $product->get_id()) {
                                $already_exists = true;
                                break;
                            }
                        }
                        
                        if (!$already_exists && ($product->managing_stock() || $product->get_stock_status() === 'instock')) {
                            $product_name = $product->get_name();
                            
                            if ($product->is_type('variation')) {
                                $parent = wc_get_product($product->get_parent_id());
                                if ($parent) {
                                    $variation_attributes = $product->get_variation_attributes();
                                    $formatted_attributes = array();
                                    
                                    foreach ($variation_attributes as $attribute_name => $attribute_value) {
                                        $taxonomy = str_replace('attribute_', '', $attribute_name);
                                        
                                        if (taxonomy_exists($taxonomy)) {
                                            $term = get_term_by('slug', $attribute_value, $taxonomy);
                                            if ($term) {
                                                $attribute_value = $term->name;
                                            }
                                        }
                                        
                                        $attribute_label = wc_attribute_label($taxonomy);
                                        $formatted_attributes[] = $attribute_label . ': ' . $attribute_value;
                                    }
                                    
                                    if (!empty($formatted_attributes)) {
                                        $product_name = $parent->get_name() . ' (' . implode(', ', $formatted_attributes) . ')';
                                    }
                                }
                            }
                            
                            $results[] = array(
                                'id' => $product->get_id(),
                                'name' => $product_name,
                                'sku' => $product->get_sku() ?: ''
                            );
                        }
                    }
                    wp_reset_postdata();
                }
            }
            
            // Ordenar resultados por relevancia (primero los que coinciden exactamente)
            usort($results, function($a, $b) use ($search_term) {
                $search_lower = strtolower($search_term);
                $a_name_lower = strtolower($a['name']);
                $b_name_lower = strtolower($b['name']);
                $a_sku_lower = strtolower($a['sku']);
                $b_sku_lower = strtolower($b['sku']);
                
                // Coincidencia exacta de SKU tiene prioridad máxima
                if ($a_sku_lower === $search_lower) return -1;
                if ($b_sku_lower === $search_lower) return 1;
                
                // Luego los que empiezan con el término de búsqueda
                $a_starts = strpos($a_name_lower, $search_lower) === 0;
                $b_starts = strpos($b_name_lower, $search_lower) === 0;
                
                if ($a_starts && !$b_starts) return -1;
                if ($b_starts && !$a_starts) return 1;
                
                // Finalmente orden alfabético
                return strcmp($a_name_lower, $b_name_lower);
            });
            
            // Limitar resultados para evitar sobrecarga
            $results = array_slice($results, 0, 25);
            
            wp_send_json_success(array('products' => $results));
            
        } catch (Exception $e) {
            error_log('Error en búsqueda de productos para transferencias: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Error al buscar productos', 'gestion-almacenes')));
        }
    }
    
    public function ajax_get_warehouse_stock() {
        check_ajax_referer('gab_transfer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos suficientes', 'gestion-almacenes')));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        
        if (!$product_id || !$warehouse_id) {
            wp_send_json_error(array('message' => __('Parámetros inválidos', 'gestion-almacenes')));
            return;
        }
        
        // Asegurar que existe el registro antes de consultar
        $this->db->ensure_warehouse_stock_exists($warehouse_id, $product_id);
        
        // Ahora obtener el stock (será 0 si es un registro nuevo)
        $stock = $this->db->get_product_stock_in_warehouse($product_id, $warehouse_id);
        
        // Agregar información adicional para debug
        $response_data = array(
            'stock' => $stock,
            'is_new' => ($stock === 0), // Indicar si es un registro nuevo
            'warehouse_id' => $warehouse_id,
            'product_id' => $product_id
        );
        
        wp_send_json_success($response_data);
    }

    
    //AJAX: Actualizar transferencia
    public function ajax_update_transfer() {
        check_ajax_referer('gab_transfer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos suficientes.']);
        }

        $transfer_id = intval($_POST['transfer_id']);
        $source_warehouse_id = intval($_POST['source_warehouse_id']);
        $target_warehouse_id = intval($_POST['target_warehouse_id']);
        $notes = sanitize_text_field($_POST['notes']);

        $items = [];

        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $items[] = [
                    'product_id' => intval($item['product_id']),
                    'quantity' => intval($item['quantity']),
                ];
            }
        }

        if (empty($items)) {
            wp_send_json_error(['message' => 'Debes incluir al menos un producto.']);
        }

        $result = $this->db->update_transfer($transfer_id, [
            'source_warehouse_id' => $source_warehouse_id,
            'target_warehouse_id' => $target_warehouse_id,
            'notes' => $notes,
            'items' => $items,
        ]);

        if ($result) {
            wp_send_json_success(['message' => 'Transferencia actualizada con éxito.']);
        } else {
            wp_send_json_error(['message' => 'No se pudo actualizar la transferencia.']);
        }
    }

    //AJAX: Eliminar transferencia
    public function ajax_delete_transfer() {
        check_ajax_referer('gab_transfer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos suficientes.']);
        }

        $transfer_id = intval($_POST['transfer_id']);

        $result = $this->db->delete_transfer($transfer_id);

        if ($result) {
            wp_send_json_success(['message' => 'Transferencia eliminada con éxito.']);
        } else {
            wp_send_json_error(['message' => 'No se pudo eliminar la transferencia.']);
        }
    }

    // Página ver transferencias
    public function render_view_transfer_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'gestion-almacenes'));
        }

        // Obtener ID de la transferencia
        $transfer_id = isset($_GET['transfer_id']) ? intval($_GET['transfer_id']) : 0;
        
        if (!$transfer_id) {
            wp_die(__('ID de transferencia inválido.', 'gestion-almacenes'));
        }

        // Obtener datos de la transferencia
        $transfer = $this->db->get_transfer($transfer_id);
        
        if (!$transfer) {
            wp_die(__('Transferencia no encontrada.', 'gestion-almacenes'));
        }

        // Obtener almacenes
        $warehouses = $this->db->get_warehouses();
        $warehouses_by_id = [];
        foreach ($warehouses as $warehouse) {
            $warehouses_by_id[$warehouse->id] = $warehouse;
        }

        // Obtener información de productos
        $products_info = [];
        if (!empty($transfer->items)) {
            foreach ($transfer->items as $item) {
                $product = wc_get_product($item->product_id);
                if ($product) {
                    $products_info[$item->product_id] = $product;
                }
            }
        }

        // ✅ Definir $source y $target UNA VEZ para usar en toda la página
        $source = isset($warehouses_by_id[$transfer->source_warehouse_id]) ? $warehouses_by_id[$transfer->source_warehouse_id] : null;
        $target = isset($warehouses_by_id[$transfer->target_warehouse_id]) ? $warehouses_by_id[$transfer->target_warehouse_id] : null;

    ?>

        <?php
        // Obtener información de la empresa
        $company_name = get_option('gab_company_name', get_bloginfo('name'));
        $company_rut = get_option('gab_company_rut', '');
        $company_address = get_option('gab_company_address', '');
        $company_phone = get_option('gab_company_phone', '');
        $company_email = get_option('gab_company_email', '');
        ?>

    <!-- Página Detalles de transferencia -->
    <div class="wrap gab-admin-page">
        <div class="container-header">
            <div class="gab-company-header">
                <h1><?php echo esc_html($company_name); ?></h1>
                <?php if ($company_rut): ?>
                    <p><strong><?php esc_html_e('RUT:', 'gestion-almacenes'); ?></strong> <?php echo esc_html($company_rut); ?></p>
                <?php endif; ?>
                <?php if ($company_address): ?>
                    <p><?php echo nl2br(esc_html($company_address)); ?></p>
                <?php endif; ?>
                <?php if ($company_phone): ?>
                    <p><strong><?php esc_html_e('Teléfono:', 'gestion-almacenes'); ?></strong> <?php echo esc_html($company_phone); ?></p>
                <?php endif; ?>
                <?php if ($company_email): ?>
                    <p><strong><?php esc_html_e('Email:', 'gestion-almacenes'); ?></strong> <?php echo esc_html($company_email); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="titulo-det-transfer">
                <h2>
                    <?php esc_html_e('Detalles de Transferencia', 'gestion-almacenes'); ?>
                </h2>
                <h4><?php esc_html_e('ID de Transferencia:', 'gestion-almacenes'); ?></h4>
                <div class="id-transfer"><h3>N° <?php echo esc_html($transfer->id); ?></h3></div>
                <div class="fecha-creacion">
                    <p><?php esc_html_e('Fecha Creación:', 'gestion-almacenes'); ?></p>
                    <p><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transfer->created_at))); ?></p>
                </div>
                <div class="fecha-confirmacion">
                    <p><?php esc_html_e('Fecha Completado: ', 'gestion-almacenes'); ?></p>
                    <p><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transfer->completed_at))); ?></p>
                </div>
            </div>
        </div>
    
        <div class="gab-transfer-view">
            <!-- Información general de la transferencia -->
            <div class="gab-card">
                <h2><?php esc_html_e('Información General', 'gestion-almacenes'); ?></h2>
                <div class="container-info">
                <table class="gab-info-table">
                    
                    <tr>
                        <th><?php esc_html_e('Estado:', 'gestion-almacenes'); ?></th>
                        <td>
                            <span class="gab-status gab-status-<?php echo esc_attr($transfer->status); ?>">
                                <?php echo esc_html($this->get_status_label($transfer->status)); ?>
                            </span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php esc_html_e('Creado por:', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php 
                            $user = get_user_by('id', $transfer->created_by);
                            echo $user ? esc_html($user->display_name) : __('Usuario desconocido', 'gestion-almacenes');
                            ?>
                        </td>
                    </tr>
                    
                    <?php if ($transfer->status === 'completed' && $transfer->completed_by): ?>
                    <tr>
                        <th><?php esc_html_e('Completado por:', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php 
                            $completed_user = get_user_by('id', $transfer->completed_by);
                            echo $completed_user ? esc_html($completed_user->display_name) : __('Usuario desconocido', 'gestion-almacenes');
                            ?>
                        </td>
                    </tr>
                </table>
                <table class="gab-info-table">
                    <tr>
                        <th><?php esc_html_e('Almacén de Origen:', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php 
                            // Versión compatible con PHP 5.6+
                            $source = isset($warehouses_by_id[$transfer->source_warehouse_id]) ? $warehouses_by_id[$transfer->source_warehouse_id] : null;
                            echo $source ? esc_html($source->name) : __('(Almacén eliminado)', 'gestion-almacenes');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Almacén de Destino:', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php 
                            // Versión compatible con PHP 5.6+
                            $target = isset($warehouses_by_id[$transfer->target_warehouse_id]) ? $warehouses_by_id[$transfer->target_warehouse_id] : null;
                            echo $target ? esc_html($target->name) : __('(Almacén eliminado)', 'gestion-almacenes');
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($transfer->notes)): ?>
                    <tr>
                        <th><?php esc_html_e('Notas:', 'gestion-almacenes'); ?></th>
                        <td><?php echo esc_html($transfer->notes); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                </div>
            </div>

            <!-- Productos de la transferencia -->
            <div class="gab-card">
                <h2><?php esc_html_e('Productos', 'gestion-almacenes'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Producto', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('SKU', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Cantidad Solicitada', 'gestion-almacenes'); ?></th>
                            <?php if ($transfer->status === 'completed'): ?>
                            <th><?php esc_html_e('Cantidad Transferida', 'gestion-almacenes'); ?></th>
                            <?php endif; ?>
                            <th><?php esc_html_e('Stock Actual (Origen)', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Stock Actual (Destino)', 'gestion-almacenes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfer->items as $item): ?>
                        <tr>
                            <td>
                                <?php 
                                // Versión compatible con PHP 5.6+
                                $product = isset($products_info[$item->product_id]) ? $products_info[$item->product_id] : null;
                                if ($product) {
                                    echo esc_html($product->get_name());
                                } else {
                                    echo __('(Producto eliminado)', 'gestion-almacenes');
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                echo $product ? esc_html($product->get_sku()) : '-';
                                ?>
                            </td>
                            <td><?php echo esc_html($item->requested_qty); ?></td>
                            <?php if ($transfer->status === 'completed'): ?>
                            <td><?php echo esc_html(isset($item->transferred_qty) ? $item->transferred_qty : $item->requested_qty); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php 
                                if ($product && $source) {
                                    $source_stock = $this->db->get_warehouse_stock($transfer->source_warehouse_id, $item->product_id);
                                    echo esc_html($source_stock !== null ? $source_stock : 0);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($product && $target) {
                                    $target_stock = $this->db->get_warehouse_stock($transfer->target_warehouse_id, $item->product_id);
                                    echo esc_html($target_stock !== null ? $target_stock : 0);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Acciones -->
            <div class="gab-card-acciones">
                <h2><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></h2>
                <div class="gab-actions">
                    <!-- Botón de imprimir siempre visible -->
                    <button class="button" onclick="window.print()">
                        <span class="dashicons dashicons-printer" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Imprimir', 'gestion-almacenes'); ?>
                    </button>
                    
                    <?php if (in_array(strtolower($transfer->status), ['pending', 'draft'])): ?>
                        <!-- Botones solo para transferencias pendientes -->
                        <a href="<?php echo admin_url('admin.php?page=gab-edit-transfer&transfer_id=' . $transfer->id); ?>" 
                        class="button button-primary">
                            <?php esc_html_e('Editar Transferencia', 'gestion-almacenes'); ?>
                        </a>
                        <button class="button button-secondary" id="complete-transfer">
                            <?php esc_html_e('Completar Transferencia', 'gestion-almacenes'); ?>
                        </button>
                        <button class="button button-link-delete" id="delete-transfer">
                            <?php esc_html_e('Eliminar Transferencia', 'gestion-almacenes'); ?>
                        </button>
                    <?php elseif ($transfer->status === 'completed'): ?>
                        <span class="description" style="margin-left: 10px;">
                            <?php esc_html_e('Esta transferencia ya ha sido completada y no puede ser modificada.', 'gestion-almacenes'); ?>
                        </span>
                    <?php else: ?>
                        <span class="description" style="margin-left: 10px;">
                            <?php 
                            printf(
                                esc_html__('Estado actual: %s', 'gestion-almacenes'), 
                                esc_html($this->get_status_label($transfer->status))
                            ); 
                            ?>
                        </span>
                    <?php endif; ?>

                    <button 
                        class="button button-secondary" 
                        onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=gab-transfer-list')); ?>'">
                        <?php esc_html_e('Volver a Lista de Transferencias', 'gestion-almacenes'); ?>
                    </button>
                </div>
            </div>

            <!-- Área de firmas (solo visible en impresión) -->
            <div class="footer-impresion">
            <div class="gab-signatures">
                <div class="gab-signature-box">
                    <div class="gab-signature-line"></div>
                    <div class="gab-signature-label">
                        <?php esc_html_e('Entregado por ', 'gestion-almacenes'); ?>
                        <?php esc_html_e('(Nombre y Firma)', 'gestion-almacenes'); ?>
                    </div>
                </div>
                <div class="gab-signature-box">
                    <div class="gab-signature-line"></div>
                    <div class="gab-signature-label">
                        <?php esc_html_e('Recibido por ', 'gestion-almacenes'); ?>
                        <?php esc_html_e('(Nombre y Firma)', 'gestion-almacenes'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Pie de página para impresión -->
            <div class="gab-print-footer">
                <p><?php printf(
                    __('Documento generado el %s a las %s', 'gestion-almacenes'),
                    wp_date(get_option('date_format')),
                    wp_date(get_option('time_format'))
                ); ?></p>
            </div>
            </div>
        </div>

        <style>
        /* Estilos generales */
        .wrap.gab-admin-page {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .container-header {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr; /* Una columna para el texto y otra para la tabla */
            gap: 15px; /* Espacio entre el cuadro de texto y la tabla */
            align-items: stretch; /* Estira los elementos para que tengan la misma altura */
        }

        .gab-company-header {

        }
        .gab-company-header h4 {
            margin: 0;
        }

        .titulo-det-transfer {
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #ccc;      /* borde gris claro */
            padding: 5px;               /* espacio interior */
            border-radius: 6px;          /* bordes redondeados opcional */
            background-color: #f9f9f9;   /* fondo suave opcional */
            margin-bottom: 0;         /* separación hacia abajo */

        }
        .titulo-det-transfer h1,h2,h3,h4,p {
            margin: 0;
        }
        .id-transfer h3 {
            color: red;
            padding: 5px;
        }

        .fecha-creacion {
            display: flex;
            gap: 6px;
            margin: 0;
        }
        .fecha-confirmacion {
            display: flex;
            gap: 6px;
            margin: 0;
        }
        .fecha-creacion p {
            margin: 0;
        }
        .fecha-confirmacion p {
            margin: 0;
        }

        .gab-transfer-view {
            max-width: 100%;
            margin-top: 10px;
            
        }

        .container-info {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr; /* Una columna para el texto y otra para la tabla */
            gap: 10px; /* Espacio entre el cuadro de texto y la tabla */
            align-items: stretch; /* Estira los elementos para que tengan la misma altura */
        }

        .gab-info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .gab-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 10px;
            padding: 20px;
        }

        .gab-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .gab-card-acciones {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 20px;
            padding: 20px;
        }

        .gab-info-table th {
            text-align: left;
            padding: 5px;
            width: 150px;
            font-weight: 600;
            vertical-align: top;
        }

        .gab-info-table td {
            padding: 5px;
        }

        .gab-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .gab-status-pending,
        .gab-status-draft {
            background: #f0ad4e;
            color: #fff;
        }

        .gab-status-completed {
            background: #5cb85c;
            color: #fff;
        }

        .gab-status-cancelled {
            background: #d9534f;
            color: #fff;
        }

        .gab-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px 0;
        }

        .gab-actions .button-link-delete {
            color: #d63638;
        }

        .gab-actions .button-link-delete:hover {
            color: #b32d2e;
        }

        /* Información de empresa para impresión */
        .gab-company-header {
            display: block;
        }

        /* Fuera del @media print, para mostrar solo en impresión */
        .gab-signatures,
        .gab-print-footer {
            display: none;
        }

        /* Estilos para impresión */
        @media print {
            @page {
                size: letter;
                margin-top: 0 !important;
            }
            
            body {
                background: #fff;
                font-size: 11pt;
                margin: 0;
                padding: 0;
            }
            
            /* Ocultar elementos de WordPress */
            #adminmenumain,
            #adminmenuback,
            #adminmenuwrap,
            #wpadminbar,
            #screen-meta,
            #screen-meta-links,
            #wpfooter,
            .notice,
            .no-print,
            .gab-actions button,
            .gab-actions a {
                display: none !important;
            }
            
            #wpcontent {
                margin-left: 0 !important;
                padding-left: 0 !important;
            }

            .wrap {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            
            
            .page-title-action {
                display: none !important;
            }
            
            /* Mostrar información de empresa */
            .gab-company-header {
                display: block;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .gab-company-header h2 {
                font-size: 18pt;
                margin: 0 0 10px 0;
            }
            
            .gab-company-header p {
                margin: 3px 0;
                font-size: 10pt;
            }
            .id-transfer h3 {
                color: red !important;
                padding: 5px;
            }
            
            /* Ajustar diseño de tarjetas */
            .gab-card {
                border: none;
                box-shadow: none;
                padding: 5px 0;
                margin-bottom: 0;
                page-break-inside: avoid;
            }
            
            .gab-card h2 {
                font-size: 14pt;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #000;
            }

            .gab-card-acciones {
                display: none;
            }
            
            /* Tabla de información */
            .gab-info-table th {
                width: 150px;
                padding: 5px;
                font-size: 10pt;
            }
            
            .gab-info-table td {
                padding: 5px;
                font-size: 10pt;
            }
            
            /* Estados */
            .gab-status {
                background: none !important;
                color: #000 !important;
                border: 1px solid #000;
                padding: 2px 5px !important;
            }
            
            /* Tabla de productos */
            .wp-list-table {
                border-collapse: collapse;
                width: 100%;
                font-size: 10pt;
            }
            
            .wp-list-table th,
            .wp-list-table td {
                border: 1px solid #000 !important;
                padding: 5px !important;
            }
            
            .wp-list-table th {
                background-color: #f0f0f0 !important;
                font-weight: bold;
            }
            
            .wp-list-table tr:nth-child(even) {
                background-color: transparent !important;
            }
            
            /* Área de firmas */
            .footer-impresion {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                padding: 10px 0;
                width: 100%;
                font-size: 10px;
            }
            
            .gab-signature-box {
                width: 45%;
                text-align: center;
            }
            
            .gab-signature-line {
                border-bottom: 1px solid #000;
                height: 40px;
                margin-bottom: 5px;
            }
            
            .gab-signature-label {
                font-size: 10pt;
            }

            /* Mostrar firmas y pie de página en impresión */
            .gab-signatures,
            .gab-print-footer {
                display: block !important;
            }
            
            .gab-signatures {
                margin-top: 50px;
                margin-bottom: 0;
                display: flex !important;
                justify-content: space-between;
            }
            
            /* Pie de página */
            .gab-print-footer {
                margin-top: 5px;
                margin-bottom: 0;
                text-align: center;
                font-size: 9pt;
                color: #666;
            }
        }
        </style>

        <!-- JavaScript para las acciones -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Completar transferencia
            $('#complete-transfer').on('click', function() {
                if (!confirm('<?php esc_html_e('¿Estás seguro de que deseas completar esta transferencia? Esta acción actualizará los inventarios y no se puede deshacer.', 'gestion-almacenes'); ?>')) {
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('<?php esc_html_e('Procesando...', 'gestion-almacenes'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_complete_transfer',
                        transfer_id: <?php echo $transfer->id; ?>,
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            
                            // Preguntar si desea imprimir el comprobante (Desactivado, porque no muestra el stock transferido a ubicación de destino)
                            /*if (confirm('<?php esc_html_e("¿Desea imprimir el comprobante de transferencia completada?", "gestion-almacenes"); ?>')) {
                                window.print();
                            }*/
                            
                            // Recargar la página después de un momento
                            setTimeout(function() {
                                location.reload();
                            },);
                        } else {
                            alert(response.data.message);
                            $button.prop('disabled', false).text('<?php esc_html_e('Completar Transferencia', 'gestion-almacenes'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Error al procesar la solicitud.', 'gestion-almacenes'); ?>');
                        $button.prop('disabled', false).text('<?php esc_html_e('Completar Transferencia', 'gestion-almacenes'); ?>');
                    }
                });
            });
            
            // Eliminar transferencia
            $('#delete-transfer').on('click', function() {
                if (!confirm('<?php esc_html_e('¿Estás seguro de que deseas eliminar esta transferencia? Esta acción no se puede deshacer.', 'gestion-almacenes'); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_delete_transfer',
                        transfer_id: <?php echo $transfer->id; ?>,
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.href = '<?php echo admin_url('admin.php?page=gab-transfer-list'); ?>';
                        } else {
                            alert(response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Error al procesar la solicitud.', 'gestion-almacenes'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // Método auxiliar para obtener las etiquetas de estado
    private function get_status_label($status) {
        $labels = [
            'draft' => __('Borrador', 'gestion-almacenes'),
            'pending' => __('Pendiente', 'gestion-almacenes'),
            'completed' => __('Completada', 'gestion-almacenes'),
            'cancelled' => __('Cancelada', 'gestion-almacenes'),
        ];
        
        return $labels[strtolower($status)] ?? $status;
    }

    // Ajax para completar la transferencia
    public function ajax_complete_transfer() {
        check_ajax_referer('gab_transfer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos suficientes.']);
        }
        
        $transfer_id = intval($_POST['transfer_id']);
        
        // Obtener la transferencia
        $transfer = $this->db->get_transfer($transfer_id);
        
        if (!$transfer) {
            wp_send_json_error(['message' => 'Transferencia no encontrada.']);
        }
        
        if (!in_array(strtolower($transfer->status), ['pending', 'draft'])) {
            wp_send_json_error(['message' => 'Esta transferencia ya no está pendiente.']);
        }

        // Asegurar que existen los registros de stock para todos los productos
        foreach ($transfer->items as $item) {
            // Asegurar registro en almacén origen
            $this->db->ensure_warehouse_stock_exists($transfer->source_warehouse_id, $item->product_id);
            
            // Asegurar registro en almacén destino
            $this->db->ensure_warehouse_stock_exists($transfer->target_warehouse_id, $item->product_id);
        }
        
        // Verificar que hay suficiente stock en el almacén de origen
        $stock_errors = [];
        foreach ($transfer->items as $item) {
            $current_stock = $this->db->get_warehouse_stock($transfer->source_warehouse_id, $item->product_id);
            if ($current_stock < $item->requested_qty) {
                $product = wc_get_product($item->product_id);
                $product_name = $product ? $product->get_name() : 'ID: ' . $item->product_id;
                $stock_errors[] = sprintf(
                    __('Stock insuficiente para %s. Disponible: %d, Solicitado: %d', 'gestion-almacenes'),
                    $product_name,
                    $current_stock,
                    $item->requested_qty
                );
            }
        }
        
        if (!empty($stock_errors)) {
            wp_send_json_error([
                'message' => __('No se puede completar la transferencia:', 'gestion-almacenes') . "\n" . implode("\n", $stock_errors)
            ]);
        }
        
        // Procesar la transferencia
        global $wpdb, $gestion_almacenes_movements;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Actualizar el stock para cada producto
            foreach ($transfer->items as $item) {
                // Obtener stock antes del movimiento
                $source_stock_before = $this->db->get_warehouse_stock($transfer->source_warehouse_id, $item->product_id);
                $target_stock_before = $this->db->get_warehouse_stock($transfer->target_warehouse_id, $item->product_id);

                // Reducir stock del almacén de origen
                $this->db->update_warehouse_stock(
                    $transfer->source_warehouse_id,
                    $item->product_id,
                    -$item->requested_qty
                );

                // Registrar movimiento de salida
                $gestion_almacenes_movements->log_movement(array(
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'type' => 'transfer_out',
                    'quantity' => -$item->requested_qty,
                    'reference_type' => 'transfer',
                    'reference_id' => $transfer_id,
                    'notes' => sprintf(
                        __('Transferencia #%d hacia %s', 'gestion-almacenes'),
                        $transfer_id,
                        $warehouses_by_id[$transfer->target_warehouse_id]->name ?? 'ID: ' . $transfer->target_warehouse_id
                    )
                ));
                
                // Aumentar stock del almacén de destino
                $this->db->update_warehouse_stock(
                    $transfer->target_warehouse_id,
                    $item->product_id,
                    $item->requested_qty
                );
                
                // Registrar movimiento de entrada
                $gestion_almacenes_movements->log_movement(array(
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->target_warehouse_id,
                    'type' => 'transfer_in',
                    'quantity' => $item->requested_qty,
                    'reference_type' => 'transfer',
                    'reference_id' => $transfer_id,
                    'notes' => sprintf(
                        __('Transferencia #%d desde %s', 'gestion-almacenes'),
                        $transfer_id,
                        $warehouses_by_id[$transfer->source_warehouse_id]->name ?? 'ID: ' . $transfer->source_warehouse_id
                    )
                ));

                // Actualizar la cantidad transferida en el item
                $wpdb->update(
                    $wpdb->prefix . 'gab_stock_transfer_items',
                    ['transferred_qty' => $item->requested_qty],
                    [
                        'transfer_id' => $transfer_id,
                        'product_id' => $item->product_id
                    ],
                    ['%d'],
                    ['%d', '%d']
                );
            }
            
            // Actualizar el estado de la transferencia
            $wpdb->update(
                $wpdb->prefix . 'gab_stock_transfers',
                [
                    'status' => 'completed',
                    'completed_by' => get_current_user_id(),
                    'completed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $transfer_id],
                ['%s', '%d', '%s', '%s'],
                ['%d']
            );
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success([
                'message' => __('Transferencia completada exitosamente.', 'gestion-almacenes'),
                'print_url' => admin_url('admin.php?page=gab-print-transfer&transfer_id=' . $transfer_id)
            ]);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[GESTION ALMACENES] Error al completar transferencia: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error al procesar la transferencia. Por favor, intenta nuevamente.', 'gestion-almacenes')]);
        }
    }

    // Configuración de impresión para transferencias
    public function render_print_transfer_page() {
        // Salir inmediatamente del contexto de WordPress admin
        ob_start();
        
        // Verificar permisos básicos
        if (!current_user_can('manage_options')) {
            die('Sin permisos');
        }

        // Obtener ID de la transferencia
        $transfer_id = isset($_GET['transfer_id']) ? intval($_GET['transfer_id']) : 0;
        
        if (!$transfer_id) {
            die('ID inválido');
        }

        // Limpiar cualquier output anterior
        ob_end_clean();
        
        // Comenzar output limpio
        header('Content-Type: text/html; charset=utf-8');

        // Obtener datos de la transferencia
        $transfer = $this->db->get_transfer($transfer_id);
        
        if (!$transfer) {
            wp_die(__('Transferencia no encontrada.', 'gestion-almacenes'));
        }

        // Obtener almacenes
        $warehouses = $this->db->get_warehouses();
        $warehouses_by_id = [];
        foreach ($warehouses as $warehouse) {
            $warehouses_by_id[$warehouse->id] = $warehouse;
        }

        // Obtener información de productos
        $products_info = [];
        if (!empty($transfer->items)) {
            foreach ($transfer->items as $item) {
                $product = wc_get_product($item->product_id);
                if ($product) {
                    $products_info[$item->product_id] = $product;
                }
            }
        }

        // Información de la empresa (obtener de las opciones guardadas)
        $company_info = [
            'name' => get_option('gab_company_name', get_bloginfo('name')),
            'rut' => get_option('gab_company_rut', ''),
            'address' => get_option('gab_company_address', ''),
            'phone' => get_option('gab_company_phone', ''),
            'email' => get_option('gab_company_email', get_bloginfo('admin_email'))
        ];

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php printf(__('Transferencia #%d', 'gestion-almacenes'), $transfer->id); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                    line-height: 1.5;
                    color: #333;
                    background: #fff;
                }
                
                .print-container {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                
                .header {
                    border-bottom: 2px solid #333;
                    padding-bottom: 20px;
                    margin-bottom: 20px;
                }
                
                .company-info {
                    margin-bottom: 20px;
                }
                
                .company-name {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                
                .transfer-title {
                    font-size: 20px;
                    font-weight: bold;
                    text-align: center;
                    margin: 20px 0;
                    text-transform: uppercase;
                }
                
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .info-section {
                    border: 1px solid #ddd;
                    padding: 15px;
                    border-radius: 5px;
                }
                
                .info-section h3 {
                    font-size: 16px;
                    margin-bottom: 10px;
                    color: #555;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 5px;
                }
                
                .info-row {
                    margin-bottom: 5px;
                }
                
                .info-label {
                    font-weight: bold;
                    display: inline-block;
                    min-width: 120px;
                }
                
                .products-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                
                .products-table th,
                .products-table td {
                    border: 1px solid #ddd;
                    padding: 10px;
                    text-align: left;
                }
                
                .products-table th {
                    background-color: #f5f5f5;
                    font-weight: bold;
                }
                
                .products-table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                
                .text-center {
                    text-align: center;
                }
                
                .text-right {
                    text-align: right;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 5px 10px;
                    border-radius: 3px;
                    font-weight: bold;
                    text-transform: uppercase;
                    font-size: 12px;
                }
                
                .status-draft {
                    background: #6c757d;
                    color: #fff;
                }
                
                .status-pending {
                    background: #f0ad4e;
                    color: #fff;
                }
                
                .status-completed {
                    background: #5cb85c;
                    color: #fff;
                }
                
                .notes-section {
                    margin-top: 30px;
                    padding: 15px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    background-color: #f9f9f9;
                }
                
                .signatures {
                    margin-top: 50px;
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 50px;
                }
                
                .signature-box {
                    text-align: center;
                }
                
                .signature-line {
                    border-bottom: 1px solid #333;
                    margin-bottom: 5px;
                    height: 40px;
                }
                
                .signature-label {
                    font-size: 12px;
                    color: #666;
                }
                
                .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                
                @media print {
                    body {
                        margin: 0;
                    }
                    
                    .print-container {
                        max-width: 100%;
                        padding: 0;
                    }
                    
                    .no-print {
                        display: none !important;
                    }
                    
                    .signatures {
                        page-break-inside: avoid;
                    }
                }
                
                .print-actions {
                    margin-bottom: 20px;
                    text-align: center;
                }
                
                .print-actions button {
                    padding: 10px 20px;
                    font-size: 16px;
                    cursor: pointer;
                    margin: 0 5px;
                }
            </style>
        </head>
        <body>
            <div class="print-actions no-print">
                <button onclick="window.print()" class="button button-primary">
                    <?php esc_html_e('Imprimir', 'gestion-almacenes'); ?>
                </button>
                <button onclick="window.close()" class="button">
                    <?php esc_html_e('Cerrar', 'gestion-almacenes'); ?>
                </button>
            </div>
            
            <div class="print-container">
                <!-- Encabezado -->
                <div class="header">
                    <div class="company-info">
                        <div class="company-name"><?php echo esc_html($company_info['name']); ?></div>
                        <?php if ($company_info['rut']): ?>
                            <div><strong><?php esc_html_e('RUT:', 'gestion-almacenes'); ?></strong> <?php echo esc_html($company_info['rut']); ?></div>
                        <?php endif; ?>
                        <?php if ($company_info['address']): ?>
                            <div><?php echo nl2br(esc_html($company_info['address'])); ?></div>
                        <?php endif; ?>
                        <?php if ($company_info['phone']): ?>
                            <div><strong><?php esc_html_e('Teléfono:', 'gestion-almacenes'); ?></strong> <?php echo esc_html($company_info['phone']); ?></div>
                        <?php endif; ?>
                        <?php if ($company_info['email']): ?>
                            <div><strong><?php esc_html_e('Email:', 'gestion-almacenes'); ?></strong> <?php echo esc_html($company_info['email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Título -->
                <h1 class="transfer-title">
                    <?php 
                    if (strtolower($transfer->status) === 'completed') {
                        esc_html_e('Comprobante de Transferencia de Stock', 'gestion-almacenes');
                    } else {
                        esc_html_e('Orden de Transferencia de Stock', 'gestion-almacenes');
                    }
                    ?>
                </h1>
                
                <!-- Información de la transferencia -->
                <div class="info-grid">
                    <div class="info-section">
                        <h3><?php esc_html_e('Información de la Transferencia', 'gestion-almacenes'); ?></h3>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Número:', 'gestion-almacenes'); ?></span>
                            #<?php echo esc_html(str_pad($transfer->id, 5, '0', STR_PAD_LEFT)); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Fecha:', 'gestion-almacenes'); ?></span>
                            <?php echo esc_html(wp_date(get_option('date_format'), strtotime($transfer->created_at))); ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Estado:', 'gestion-almacenes'); ?></span>
                            <span class="status-badge status-<?php echo esc_attr(strtolower($transfer->status)); ?>">
                                <?php echo esc_html($this->get_status_label($transfer->status)); ?>
                            </span>
                        </div>
                        <?php if ($transfer->status === 'completed' && $transfer->completed_at): ?>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Completado:', 'gestion-almacenes'); ?></span>
                            <?php echo esc_html(wp_date(get_option('date_format'), strtotime($transfer->completed_at))); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-section">
                        <h3><?php esc_html_e('Almacenes', 'gestion-almacenes'); ?></h3>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Origen:', 'gestion-almacenes'); ?></span>
                            <?php 
                            $source = $warehouses_by_id[$transfer->source_warehouse_id] ?? null;
                            echo $source ? esc_html($source->name) : __('(Almacén eliminado)', 'gestion-almacenes');
                            ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Destino:', 'gestion-almacenes'); ?></span>
                            <?php 
                            $target = $warehouses_by_id[$transfer->target_warehouse_id] ?? null;
                            echo $target ? esc_html($target->name) : __('(Almacén eliminado)', 'gestion-almacenes');
                            ?>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Creado por:', 'gestion-almacenes'); ?></span>
                            <?php 
                            $user = get_user_by('id', $transfer->created_by);
                            echo $user ? esc_html($user->display_name) : __('Usuario desconocido', 'gestion-almacenes');
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de productos -->
                <h3><?php esc_html_e('Detalle de Productos', 'gestion-almacenes'); ?></h3>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Código', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Producto', 'gestion-almacenes'); ?></th>
                            <th class="text-center"><?php esc_html_e('Cantidad Solicitada', 'gestion-almacenes'); ?></th>
                            <?php if ($transfer->status === 'completed'): ?>
                            <th class="text-center"><?php esc_html_e('Cantidad Transferida', 'gestion-almacenes'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_items = 0;
                        $total_transferred = 0;
                        foreach ($transfer->items as $item): 
                            $product = $products_info[$item->product_id] ?? null;
                            $total_items += $item->requested_qty;
                            if ($transfer->status === 'completed') {
                                $total_transferred += ($item->transferred_qty ?? $item->requested_qty);
                            }
                        ?>
                        <tr>
                            <td><?php echo $product ? esc_html($product->get_sku()) : '-'; ?></td>
                            <td>
                                <?php 
                                if ($product) {
                                    echo esc_html($product->get_name());
                                } else {
                                    echo __('(Producto eliminado)', 'gestion-almacenes');
                                }
                                ?>
                            </td>
                            <td class="text-center"><?php echo esc_html($item->requested_qty); ?></td>
                            <?php if ($transfer->status === 'completed'): ?>
                            <td class="text-center"><?php echo esc_html($item->transferred_qty ?? $item->requested_qty); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-right"><?php esc_html_e('Total:', 'gestion-almacenes'); ?></th>
                            <th class="text-center"><?php echo esc_html($total_items); ?></th>
                            <?php if ($transfer->status === 'completed'): ?>
                            <th class="text-center"><?php echo esc_html($total_transferred); ?></th>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- Notas -->
                <?php if (!empty($transfer->notes)): ?>
                <div class="notes-section">
                    <h3><?php esc_html_e('Notas:', 'gestion-almacenes'); ?></h3>
                    <p><?php echo esc_html($transfer->notes); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Firmas -->
                <div class="signatures">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">
                            <?php esc_html_e('Entregado por', 'gestion-almacenes'); ?><br>
                            <?php esc_html_e('(Nombre y Firma)', 'gestion-almacenes'); ?>
                        </div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">
                            <?php esc_html_e('Recibido por', 'gestion-almacenes'); ?><br>
                            <?php esc_html_e('(Nombre y Firma)', 'gestion-almacenes'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Pie de página -->
                <div class="footer">
                    <p><?php printf(__('Documento generado el %s a las %s', 'gestion-almacenes'), 
                        wp_date(get_option('date_format')), 
                        wp_date(get_option('time_format'))
                    ); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        die(); // Usar die() en lugar de exit para mayor compatibilidad
    }

    /**
     * Obtener datos de un almacén vía AJAX (para editar Almacén)
     */
    public function ajax_get_warehouse() {
        // Verificar nonce - usar el mismo nonce que ya tienes configurado
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_warehouse_stock_nonce')) {
            wp_send_json_error('Nonce inválido');
            wp_die();
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            wp_die();
        }
        
        // Obtener ID del almacén
        $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        
        if (!$warehouse_id) {
            wp_send_json_error('ID de almacén inválido');
            wp_die();
        }
        
        global $wpdb;
        $tabla_almacenes = $wpdb->prefix . 'gab_warehouses';
        
        // Obtener datos del almacén
        $almacen = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla_almacenes WHERE id = %d",
            $warehouse_id
        ), ARRAY_A);
        
        if (!$almacen) {
            wp_send_json_error('Almacén no encontrado');
            wp_die();
        }
        
        // Enviar respuesta exitosa
        wp_send_json_success($almacen);
    }

    /**
     * Actualizar datos de un almacén vía AJAX
     */
    public function ajax_update_warehouse() {        
        // Intentar verificar con ambos nonces para debug
        $nonce_stock = isset($_POST['nonce']) ? wp_verify_nonce($_POST['nonce'], 'get_warehouse_stock_nonce') : false;
        $nonce_edit = isset($_POST['nonce']) ? wp_verify_nonce($_POST['nonce'], 'gab_edit_warehouse_nonce') : false;
        
        // Verificar nonce - usar el mismo nonce que ajax_get_warehouse
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_warehouse_stock_nonce')) {
            wp_send_json_error('Nonce inválido para actualización');
            wp_die();
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            wp_die();
        }
        
        // Validar y sanitizar datos
        $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $name = isset($_POST['warehouse_name']) ? sanitize_text_field($_POST['warehouse_name']) : '';
        $address = isset($_POST['warehouse_address']) ? sanitize_text_field($_POST['warehouse_address']) : '';
        $comuna = isset($_POST['warehouse_comuna']) ? sanitize_text_field($_POST['warehouse_comuna']) : '';
        $ciudad = isset($_POST['warehouse_ciudad']) ? sanitize_text_field($_POST['warehouse_ciudad']) : '';
        $region = isset($_POST['warehouse_region']) ? sanitize_text_field($_POST['warehouse_region']) : '';
        $pais = isset($_POST['warehouse_pais']) ? sanitize_text_field($_POST['warehouse_pais']) : '';
        $email = isset($_POST['warehouse_email']) ? sanitize_email($_POST['warehouse_email']) : '';
        $telefono = isset($_POST['warehouse_phone']) ? sanitize_text_field($_POST['warehouse_phone']) : '';
        
        // Validaciones
        if (!$warehouse_id) {
            wp_send_json_error('ID de almacén inválido');
            wp_die();
        }
        
        if (empty($name)) {
            wp_send_json_error('El nombre del almacén es obligatorio');
            wp_die();
        }
        
        if (empty($address)) {
            wp_send_json_error('La dirección del almacén es obligatoria');
            wp_die();
        }
        
        // Validar email si se proporciona
        if (!empty($email) && !is_email($email)) {
            wp_send_json_error('Email inválido');
            wp_die();
        }
        
        global $wpdb;
        $tabla_almacenes = $wpdb->prefix . 'gab_warehouses';
        
        // Verificar que el almacén existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla_almacenes WHERE id = %d",
            $warehouse_id
        ));
        
        if (!$exists) {
            wp_send_json_error('El almacén no existe');
            wp_die();
        }
        
        // Generar slug si es necesario
        $slug = sanitize_title($name);
        
        // Actualizar datos
        $result = $wpdb->update(
            $tabla_almacenes,
            array(
                'name' => $name,
                'slug' => $slug,
                'address' => $address,
                'comuna' => $comuna,
                'ciudad' => $ciudad,
                'region' => $region,
                'pais' => $pais,
                'email' => $email,
                'telefono' => $telefono
            ),
            array('id' => $warehouse_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Error al actualizar el almacén: ' . $wpdb->last_error);
            wp_die();
        }
        
        // Obtener datos actualizados
        $almacen_actualizado = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla_almacenes WHERE id = %d",
            $warehouse_id
        ), ARRAY_A);
        
        wp_send_json_success(array(
            'message' => 'Almacén actualizado correctamente',
            'warehouse' => $almacen_actualizado
        ));
    }

}