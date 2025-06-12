<?php
    if (!defined('ABSPATH')) {
        exit;
    }

    /* // Incluir la integración si existe
    if (file_exists(VENTAS_PLUGIN_DIR . 'includes/class-ventas-almacenes-integration.php')) {
        require_once VENTAS_PLUGIN_DIR . 'includes/class-ventas-almacenes-integration.php';
    }*/

    // Registrar scripts y estilos
    function ventas_enqueue_scripts() {
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
        
        wp_localize_script('ventas-admin-scripts', 'cotizacionesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cotizaciones_nonce')
        ));
    }
    add_action('admin_enqueue_scripts', 'ventas_enqueue_scripts');

    // Validar disponibilidad de stock si la integración está activa
    if (class_exists('Ventas_Almacenes_Integration')) {
        $integration = Ventas_Almacenes_Integration::get_instance();
        
        // Solo validar si el plugin de almacenes está activo
        if ($integration->is_warehouse_plugin_active()) {
            $validacion = validar_cotizacion_antes_crear($productos);
            if ($validacion !== true) {
                $logger->error('Validación de stock falló', ['errores' => $validacion]);
                Ventas_Messages::get_instance()->add_message(
                    'Error en la validación de stock:<br>' . implode('<br>', $validacion),
                    'error'
                );
                return false;
            }
        }
    }

    function ventas_init_logging() {
        if (class_exists('Ventas_Logger')) {
            $logger = Ventas_Logger::get_instance();
            $logger->log('Iniciando procesamiento de formulario de cotización', 'debug');
            if (!empty($_POST)) {
                $logger->log('POST data recibida: ' . print_r($_POST, true), 'debug');
            }
        }
    }
    add_action('admin_init', 'ventas_init_logging');

    // Función para generar folio único
    function generar_folio_cotizacion() {
        global $wpdb;
        $prefix = 'COT-';
        $year = date('Y');
        $table = $wpdb->prefix . 'ventas_cotizaciones';
        
        $ultimo_folio = $wpdb->get_var("
            SELECT folio 
            FROM $table 
            WHERE folio LIKE '$prefix$year%' 
            ORDER BY id DESC 
            LIMIT 1
        ");

        if ($ultimo_folio) {
            $numero = intval(substr($ultimo_folio, -4)) + 1;
        } else {
            $numero = 1;
        }

        return $prefix . $year . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    // Mejorar la función de búsqueda de productos
    add_action('wp_ajax_buscar_productos', 'ventas_buscar_productos');
    function ventas_buscar_productos() {
        check_ajax_referer('ventas_nonce', 'nonce');

        $term = sanitize_text_field($_GET['q']);
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '!='
                ]
            ]
        ];

        // Buscar por SKU o nombre
        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key' => '_sku',
                'value' => $term,
                'compare' => 'LIKE'
            ],
            [
                'key' => '_sku',
                'value' => sanitize_title($term),
                'compare' => 'LIKE'
            ]
        ];

        // Si no hay resultados por SKU, buscar por nombre
        $products = get_posts($args);
        if (empty($products)) {
            $args['s'] = $term;
            unset($args['meta_query'][1]); // Remover búsqueda por SKU
            $products = get_posts($args);
        }

        $results = [];
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product && $wc_product->is_purchasable()) {
                $sku = $wc_product->get_sku();
                $name = $wc_product->get_name();
                $results[] = [
                    'id' => $product->ID,
                    'text' => $sku . ' - ' . $name, // Texto para mostrar en el dropdown
                    'sku' => $sku,                  // SKU para mostrar después de seleccionar
                    'name' => $name,
                    'price' => $wc_product->get_price(),
                    'stock' => $wc_product->get_stock_quantity()
                ];
            }
        }

        wp_send_json_success($results);
    }


    // Funciones existentes mejoradas
    function get_cotizacion($id) {
        global $wpdb;
        $cotizacion = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, 
                    u.display_name as cliente_nombre,
                    um.meta_value as cliente_rut
            FROM {$wpdb->prefix}ventas_cotizaciones c
            LEFT JOIN {$wpdb->users} u ON c.cliente_id = u.ID
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'billing_rut'
            WHERE c.id = %d",
            $id
        ), ARRAY_A);
        
        if (!$cotizacion) {
            return false;
        }
        
        // Obtener los items de la cotización
        $cotizacion['items'] = get_items_cotizacion($id);
        
        return $cotizacion;
    }

    function get_items_cotizacion($cotizacion_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, 
                    p.post_title as producto_nombre,
                    pm.meta_value as producto_sku
            FROM {$wpdb->prefix}ventas_cotizaciones_items i
            LEFT JOIN {$wpdb->posts} p ON i.producto_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE i.cotizacion_id = %d 
            ORDER BY i.id ASC",
            $cotizacion_id
        ), ARRAY_A);
    }

    /**
     * Validar cotización antes de crear
     */
    function validar_cotizacion_antes_crear($productos) {
        // Verificar si la integración está disponible
        if (!class_exists('Ventas_Almacenes_Integration')) {
            return true;
        }
        
        $integration = Ventas_Almacenes_Integration::get_instance();
        
        if (!$integration->is_warehouse_plugin_active()) {
            return true; // Si no hay plugin de almacenes, no validar
        }
        
        $validar_stock = Ventas_DB::get_instance()->get_config('validar_stock_cotizacion', '1');
        if ($validar_stock != '1') {
            return true; // Validación desactivada
        }
        
        $errores = array();
        
        foreach ($productos as $index => $producto) {
            if (empty($producto['almacen_id'])) {
                $product = wc_get_product($producto['producto_id']);
                if ($product) {
                    $errores[] = sprintf(
                        'Producto "%s": Debe seleccionar un almacén',
                        $product->get_name()
                    );
                }
                continue;
            }
            
            $stock_disponible = Ventas_DB::get_instance()->get_stock_disponible(
                $producto['producto_id'],
                $producto['almacen_id']
            );
            
            if ($stock_disponible < $producto['cantidad']) {
                $product = wc_get_product($producto['producto_id']);
                if ($product) {
                    $errores[] = sprintf(
                        'Producto "%s": Stock insuficiente (Disponible: %d, Solicitado: %d)',
                        $product->get_name(),
                        $stock_disponible,
                        $producto['cantidad']
                    );
                }
            }
        }
        
        return empty($errores) ? true : $errores;
    }