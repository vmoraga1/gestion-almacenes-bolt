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
        add_action('wp_ajax_gab_create_transfer', array($this, 'ajax_create_transfer'));
        add_action('wp_ajax_gab_get_transfer_list', array($this, 'ajax_get_transfer_list'));
        add_action('wp_ajax_gab_search_products_transfer', array($this, 'ajax_search_products'));
        add_action('wp_ajax_gab_get_warehouse_stock_transfer', array($this, 'ajax_get_warehouse_stock'));
        add_action('wp_ajax_gab_update_transfer', array($this, 'ajax_update_transfer'));
        add_action('wp_ajax_gab_delete_transfer', array($this, 'ajax_delete_transfer'));
        add_action('wp_ajax_gab_complete_transfer', [$this, 'ajax_complete_transfer']);

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
        
        $stock = $this->db->get_product_stock_in_warehouse($product_id, $warehouse_id);
        
        wp_send_json_success(array('stock' => $stock));
    }

    
    //AJAX: Actualizar transferencia
    public function ajax_update_transfer() {
        check_ajax_referer('gab_transfer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos suficientes.']);
        }

        // Debugging: Ver qué datos están llegando
        error_log('[DEBUG] POST data: ' . print_r($_POST, true));

        $transfer_id = intval($_POST['transfer_id']);
        $source_warehouse_id = intval($_POST['source_warehouse_id']);
        $target_warehouse_id = intval($_POST['target_warehouse_id']);
        $notes = sanitize_text_field($_POST['notes']);

        $items = [];

        // Debugging: Ver específicamente el campo items
        error_log('[DEBUG] Items field: ' . print_r($_POST['items'], true));

        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $items[] = [
                    'product_id' => intval($item['product_id']),
                    'quantity' => intval($item['quantity']),
                ];
            }
        }

        // Debugging: Ver el array procesado
        error_log('[DEBUG] Processed items: ' . print_r($items, true));

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

    ?>
    <div class="wrap">
        <h1>
            <?php esc_html_e('Detalles de Transferencia', 'gestion-almacenes'); ?>
            <a href="<?php echo admin_url('admin.php?page=gab-stock-transfers'); ?>" class="page-title-action">
                <?php esc_html_e('Volver al listado', 'gestion-almacenes'); ?>
            </a>
        </h1>

        <div class="gab-transfer-view">
            <!-- Información general de la transferencia -->
            <div class="gab-card">
                <h2><?php esc_html_e('Información General', 'gestion-almacenes'); ?></h2>
                <table class="gab-info-table">
                    <tr>
                        <th><?php esc_html_e('ID de Transferencia:', 'gestion-almacenes'); ?></th>
                        <td>#<?php echo esc_html($transfer->id); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Estado:', 'gestion-almacenes'); ?></th>
                        <td>
                            <span class="gab-status gab-status-<?php echo esc_attr($transfer->status); ?>">
                                <?php echo esc_html($this->get_status_label($transfer->status)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Almacén de Origen:', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php 
                            $source = $warehouses_by_id[$transfer->source_warehouse_id] ?? null;
                            echo $source ? esc_html($source->name) : __('(Almacén eliminado)', 'gestion-almacenes');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Almacén de Destino:', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php 
                            $target = $warehouses_by_id[$transfer->target_warehouse_id] ?? null;
                            echo $target ? esc_html($target->name) : __('(Almacén eliminado)', 'gestion-almacenes');
                            ?>
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
                    <tr>
                        <th><?php esc_html_e('Fecha de Creación:', 'gestion-almacenes'); ?></th>
                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transfer->created_at))); ?></td>
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
                    <tr>
                        <th><?php esc_html_e('Fecha de Completado:', 'gestion-almacenes'); ?></th>
                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transfer->completed_at))); ?></td>
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
                                $product = $products_info[$item->product_id] ?? null;
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
                            <td><?php echo esc_html($item->transferred_qty ?? $item->requested_qty); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php 
                                if ($product && $source) {
                                    $source_stock = $this->db->get_warehouse_stock($transfer->source_warehouse_id, $item->product_id);
                                    echo esc_html($source_stock ?? 0);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($product && $target) {
                                    $target_stock = $this->db->get_warehouse_stock($transfer->target_warehouse_id, $item->product_id);
                                    echo esc_html($target_stock ?? 0);
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
            <div class="gab-card">
                <h2><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></h2>
                <div class="gab-actions">
                    <?php if (in_array(strtolower($transfer->status), ['pending', 'draft'])): ?>
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
                        <p class="description">
                            <?php esc_html_e('Esta transferencia ya ha sido completada y no puede ser modificada.', 'gestion-almacenes'); ?>
                        </p>
                    <?php else: ?>
                        <p class="description">
                            <?php 
                            printf(
                                esc_html__('Estado actual: %s', 'gestion-almacenes'), 
                                esc_html($this->get_status_label($transfer->status))
                            ); 
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Estilos CSS -->
        <style>
        .gab-transfer-view {
            max-width: 1200px;
            margin-top: 20px;
        }
        
        .gab-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .gab-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .gab-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .gab-info-table th {
            text-align: left;
            padding: 10px;
            width: 200px;
            font-weight: 600;
            vertical-align: top;
        }
        
        .gab-info-table td {
            padding: 10px;
        }
        
        .gab-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .gab-status-draft {
            background: #6c757d;
            color: #fff;
        }

        .gab-status-pending {
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
        }
        
        .gab-actions .button-link-delete {
            color: #d63638;
        }
        
        .gab-actions .button-link-delete:hover {
            color: #b32d2e;
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
                            location.reload();
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
                            window.location.href = '<?php echo admin_url('admin.php?page=gab-stock-transfers'); ?>';
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
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Actualizar el stock para cada producto
            foreach ($transfer->items as $item) {
                // Reducir stock del almacén de origen
                $this->db->update_warehouse_stock(
                    $transfer->source_warehouse_id,
                    $item->product_id,
                    -$item->requested_qty
                );
                
                // Aumentar stock del almacén de destino
                $this->db->update_warehouse_stock(
                    $transfer->target_warehouse_id,
                    $item->product_id,
                    $item->requested_qty
                );
                
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
            
            wp_send_json_success(['message' => __('Transferencia completada exitosamente.', 'gestion-almacenes')]);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[GESTION ALMACENES] Error al completar transferencia: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error al procesar la transferencia. Por favor, intenta nuevamente.', 'gestion-almacenes')]);
        }
    }

}