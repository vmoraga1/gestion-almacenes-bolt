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
}