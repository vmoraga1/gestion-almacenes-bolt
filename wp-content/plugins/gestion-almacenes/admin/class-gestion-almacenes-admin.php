<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_Admin {

    private $transfer_controller;

    public function __construct() {
        add_action('admin_menu', array($this, 'registrar_menu_almacenes'));
        //add_action('admin_post_gab_add_warehouse', array($this, 'procesar_agregar_almacen'));
        add_action('wp_ajax_get_warehouse_stock', array($this, 'ajax_get_warehouse_stock'));
        // Hook para agregar estilos CSS en admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        // Inicializar controlador de transferencias
        $this->transfer_controller = new Gestion_Almacenes_Transfer_Controller();
        // Agregar hook para AJAX de actualización de stock
        add_action('wp_ajax_gab_update_warehouse_stock', [$this, 'ajax_update_warehouse_stock']);
        add_action('wp_ajax_gab_create_warehouse', array($this, 'ajax_create_warehouse'));
        // Modal Agregar Stock en página de Reporte de Stock por Almacén
        add_action('wp_ajax_gab_adjust_stock', array($this, 'ajax_adjust_stock'));
        
        // Hook para verificar el stock del almacén antes de agregar al carrito
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validar_stock_almacen'), 10, 5);
        add_filter('woocommerce_update_cart_validation', array($this, 'validar_stock_almacen_actualizar'), 10, 4);

        // Hook para verificar stock durante el checkout
        add_action('woocommerce_check_cart_items', array($this, 'verificar_stock_checkout'));

        // Hook para reducir el stock del almacén después de completar el pedido
        add_action('woocommerce_reduce_order_stock', array($this, 'reducir_stock_almacen'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'reducir_stock_almacen_pago_completo'), 10, 1);
    }

    public function registrar_menu_almacenes() {
        // Menú Principal de Almacenes
        add_menu_page(
            __('Warehouses', 'gestion-almacenes'),
            __('Warehouses', 'gestion-almacenes'),
            'manage_options',
            'gab-warehouse-management',
            array($this, 'contenido_pagina_almacenes'),
            'dashicons-store',
            25
        );

        // Submenú para Gestión de Almacenes
        add_submenu_page(
            'gab-warehouse-management',
            __('Gestión de Almacenes', 'gestion-almacenes'),
            __('Gestión de Almacenes', 'gestion-almacenes'),
            'manage_options',
            'gab-warehouse-management',
            array($this, 'contenido_pagina_almacenes')
        );

        // Submenú para Agregar Nuevo Almacén
        /*add_submenu_page(
            'gab-warehouse-management',
            __('Agregar Nuevo Almacén', 'gestion-almacenes'),
            __('Agregar Nuevo', 'gestion-almacenes'),
            'manage_options',
            'gab-add-new-warehouse',
            'gab_mostrar_formulario_nuevo_almacen'
        );*/

        // NUEVOS SUBMENÚS PARA TRANSFERENCIAS
        // Separador visual (submenú deshabilitado)
        add_submenu_page(
            'gab-warehouse-management',
            '',
            '— ' . __('Transferencias', 'gestion-almacenes') . ' —',
            'manage_options',
            '#',
            ''
        );

        // Nueva Transferencia
        add_submenu_page(
            'gab-warehouse-management',
            __('Nueva Transferencia', 'gestion-almacenes'),
            __('Nueva Transferencia', 'gestion-almacenes'),
            'manage_options',
            'gab-new-transfer',
            array($this, 'mostrar_nueva_transferencia')
        );

        // Lista de Transferencias
        add_submenu_page(
            'gab-warehouse-management',
            __('Lista de Transferencias', 'gestion-almacenes'),
            __('Lista de Transferencias', 'gestion-almacenes'),
            'manage_options',
            'gab-transfer-list',
            array($this, 'mostrar_lista_transferencias')
        );

        // Editar Transferencia (página oculta, solo accesible por URL)
        add_submenu_page(
            null,
            __('Editar Transferencia', 'gestion-almacenes'),
            __('Editar Transferencia', 'gestion-almacenes'),
            'manage_options',
            'gab-edit-transfer',
            array($this, 'mostrar_editar_transferencia')
        );
        
        // Página para ver transferencias
        add_submenu_page(
            null,
            __('Ver Transferencia', 'gestion-almacenes'),
            __('Ver Transferencia', 'gestion-almacenes'),
            'manage_options',
            'gab-view-transfer',
            [$this->transfer_controller, 'render_view_transfer_page']
        );

        add_submenu_page(
            null,
            __('Imprimir Transferencia', 'gestion-almacenes'),
            __('Imprimir Transferencia', 'gestion-almacenes'),
            'manage_options',
            'gab-print-transfer',
            [$this->transfer_controller, 'render_print_transfer_page']
        );

        // Gestión de Discrepancias
        add_submenu_page(
            'gab-warehouse-management',
            __('Gestión de Discrepancias', 'gestion-almacenes'),
            __('Discrepancias', 'gestion-almacenes'),
            'manage_options',
            'gab-discrepancies',
            array($this, 'mostrar_discrepancias')
        );

        // Almacén de Mermas
        add_submenu_page(
            'gab-warehouse-management',
            __('Almacén de Mermas', 'gestion-almacenes'),
            __('Mermas', 'gestion-almacenes'),
            'manage_options',
            'gab-waste-store',
            array($this, 'mostrar_almacen_mermas')
        );

        // Separador visual
        add_submenu_page(
            'gab-warehouse-management',
            '',
            '— ' . __('Reportes', 'gestion-almacenes') . ' —',
            'manage_options',
            '#',
            ''
        );
        
        // Submenú para Reporte de Stock
        add_submenu_page(
            'gab-warehouse-management', 
            __('Reporte de Stock', 'gestion-almacenes'),
            __('Reporte de Stock', 'gestion-almacenes'),
            'manage_options',
            'gab-stock-report', 
            array($this, 'mostrar_reporte_stock')
        );
        
        // Submenú para Configuración
        add_submenu_page(
            'gab-warehouse-management', 
            __('Configuración', 'gestion-almacenes'),
            __('Configuración', 'gestion-almacenes'),
            'manage_options',
            'gab-warehouse-settings', 
            array($this, 'mostrar_configuracion')
        );

    }

    public function enqueue_admin_scripts($hook) {
        // Solo en páginas relevantes del plugin
        if (strpos($hook, 'gab-stock-report') === false && 
            strpos($hook, 'gab-transfer-stock') === false && 
            strpos($hook, 'gab-warehouse-management') === false &&
            strpos($hook, 'gab-add-new-warehouse') === false &&
            strpos($hook, 'gab-warehouse-settings') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'gestion-almacenes-admin-css',
            GESTION_ALMACENES_PLUGIN_URL . 'admin/assets/css/gestion-almacenes-admin.css',
            array(),
            '1.0.0'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'gestion-almacenes-admin-js',
            GESTION_ALMACENES_PLUGIN_URL . 'admin/assets/js/gestion-almacenes-admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localizar script con datos AJAX
        wp_localize_script('gestion-almacenes-admin-js', 'gestionAlmacenesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('get_warehouse_stock_nonce'),
            'messages' => array(
                'confirm_delete' => __('¿Está seguro de que desea eliminar este elemento?', 'gestion-almacenes'),
                'transfer_confirm' => __('¿Confirma la transferencia?', 'gestion-almacenes'),
                'error_connection' => __('Error de conexión', 'gestion-almacenes'),
                'stock_updated' => __('Stock actualizado correctamente', 'gestion-almacenes')
            )
        ));
    }

    /**
     * Crear nuevo almacén vía AJAX
     */
    public function ajax_create_warehouse() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_warehouse_stock_nonce')) {
            wp_send_json_error('Nonce inválido');
            wp_die();
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            wp_die();
        }
        
        // Validar y sanitizar datos
        $name = isset($_POST['warehouse_name']) ? sanitize_text_field($_POST['warehouse_name']) : '';
        $address = isset($_POST['warehouse_address']) ? sanitize_text_field($_POST['warehouse_address']) : '';
        $comuna = isset($_POST['warehouse_comuna']) ? sanitize_text_field($_POST['warehouse_comuna']) : '';
        $ciudad = isset($_POST['warehouse_ciudad']) ? sanitize_text_field($_POST['warehouse_ciudad']) : '';
        $region = isset($_POST['warehouse_region']) ? sanitize_text_field($_POST['warehouse_region']) : '';
        $pais = isset($_POST['warehouse_pais']) ? sanitize_text_field($_POST['warehouse_pais']) : '';
        $email = isset($_POST['warehouse_email']) ? sanitize_email($_POST['warehouse_email']) : '';
        $telefono = isset($_POST['warehouse_phone']) ? sanitize_text_field($_POST['warehouse_phone']) : '';
        
        // Validaciones
        if (empty($name)) {
            wp_send_json_error('El nombre del almacén es obligatorio');
            wp_die();
        }
        
        if (empty($address)) {
            wp_send_json_error('La dirección del almacén es obligatoria');
            wp_die();
        }
        
        if (empty($comuna) || empty($ciudad) || empty($region) || empty($pais)) {
            wp_send_json_error('Todos los campos de ubicación son obligatorios');
            wp_die();
        }
        
        if (empty($email)) {
            wp_send_json_error('El email es obligatorio');
            wp_die();
        }
        
        // Validar email
        if (!is_email($email)) {
            wp_send_json_error('Email inválido');
            wp_die();
        }
        
        global $wpdb;
        $tabla_almacenes = $wpdb->prefix . 'gab_warehouses';
        
        // Verificar si ya existe un almacén con el mismo nombre
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla_almacenes WHERE name = %s",
            $name
        ));
        
        if ($exists > 0) {
            wp_send_json_error('Ya existe un almacén con ese nombre');
            wp_die();
        }
        
        // Generar slug
        $slug = sanitize_title($name);
        
        // Asegurar que el slug sea único
        $slug_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla_almacenes WHERE slug = %s",
            $slug
        ));
        
        if ($slug_exists > 0) {
            $slug = $slug . '-' . time();
        }
        
        // Insertar nuevo almacén
        $result = $wpdb->insert(
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
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Error al crear el almacén: ' . $wpdb->last_error);
            wp_die();
        }
        
        $new_warehouse_id = $wpdb->insert_id;
        
        // Obtener el almacén recién creado
        $new_warehouse = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla_almacenes WHERE id = %d",
            $new_warehouse_id
        ), ARRAY_A);
        
        wp_send_json_success(array(
            'message' => 'Almacén creado correctamente',
            'warehouse' => $new_warehouse,
            'warehouse_id' => $new_warehouse_id
        ));
    }

    public function ajax_update_warehouse_stock() {
        // Verificar nonce
        if (!check_ajax_referer('gab_update_stock', 'nonce', false)) {
            wp_send_json_error(['message' => __('Error de seguridad', 'gestion-almacenes')]);
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Sin permisos suficientes', 'gestion-almacenes')]);
            return;
        }
        
        $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $new_stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
        
        if (!$warehouse_id || !$product_id || $new_stock < 0) {
            wp_send_json_error(['message' => __('Datos inválidos', 'gestion-almacenes')]);
            return;
        }
        
        try {
            global $gestion_almacenes_db;
            
            // Actualizar stock del almacén
            $result = $gestion_almacenes_db->set_warehouse_stock($warehouse_id, $product_id, $new_stock);
            
            if (!$result) {
                throw new Exception(__('No se pudo actualizar el stock en la base de datos', 'gestion-almacenes'));
            }
            
            // Obtener stock total de todos los almacenes
            $total_stock = 0;
            $warehouses = $gestion_almacenes_db->get_warehouses();
            $warehouse_stocks = [];
            
            foreach ($warehouses as $warehouse) {
                $stock = $gestion_almacenes_db->get_warehouse_stock($warehouse->id, $product_id);
                $total_stock += $stock;
                $warehouse_stocks[$warehouse->id] = [
                    'name' => $warehouse->name,
                    'stock' => $stock
                ];
            }
            
            wp_send_json_success([
                'message' => __('Stock actualizado correctamente', 'gestion-almacenes'),
                'new_total' => $total_stock,
                'warehouse_stocks' => $warehouse_stocks
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }


    public function contenido_pagina_almacenes() {
        // Cargar la vista desde el archivo separado
        require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/views/page-almacenes-list.php';
        
        // Llamar a la función de la vista
        gab_mostrar_listado_almacenes();
    }

    /*public function procesar_agregar_almacen() {
        if (!isset($_POST['gab_warehouse_nonce']) || !wp_verify_nonce($_POST['gab_warehouse_nonce'], 'gab_add_warehouse_nonce')) {
            wp_die(__('No tienes permiso para realizar esta acción.', 'gestion-almacenes'));
        }

        $data = [
            'name'     => sanitize_text_field($_POST['warehouse_name']),
            'address'  => sanitize_textarea_field($_POST['warehouse_address']),
            'comuna'   => sanitize_text_field($_POST['warehouse_comuna']),
            'ciudad'   => sanitize_text_field($_POST['warehouse_ciudad']),
            'region'   => sanitize_text_field($_POST['warehouse_region']),
            'pais'     => sanitize_text_field($_POST['warehouse_pais']),
            'email'    => sanitize_email($_POST['warehouse_email']),
            'telefono' => sanitize_text_field($_POST['warehouse_telefono']),
            'slug'     => sanitize_title($_POST['warehouse_name']),
        ];

        if (empty($data['name'])) {
            wp_redirect(admin_url('admin.php?page=gab-add-new-warehouse&status=error&message=nombre_vacio'));
            exit;
        }

        global $gestion_almacenes_db;
        $insert_result = $gestion_almacenes_db->insertar_almacen($data);

        if ($insert_result) {
            wp_redirect(admin_url('admin.php?page=gab-warehouse-management&status=success'));
        } else {
            wp_redirect(admin_url('admin.php?page=gab-warehouse-management&status=error&message=error_db'));
        }

        exit;
    }*/

    
    // Página Reporte de Stock por Almacén
    public function mostrar_reporte_stock() {
        global $gestion_almacenes_db;
        
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Reporte de Stock por Almacén', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Visualiza el estado del inventario en todos los almacenes, incluyendo productos sin stock asignado.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        // Obtener datos
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        
        // Usar la nueva función que obtiene TODOS los productos
        $todos_productos = $gestion_almacenes_db->get_all_wc_products_with_warehouse_stock();
        
        $threshold = get_option('gab_low_stock_threshold', 5);
        
        // Obtener filtros
        $warehouse_filters = isset($_GET['warehouse_filter']) ? (array) $_GET['warehouse_filter'] : array();
        $status_filters = isset($_GET['status_filter']) ? (array) $_GET['status_filter'] : array();
        $show_all_products = isset($_GET['show_all']) ? sanitize_text_field($_GET['show_all']) : 'no';
        
        // Limpiar filtros
        $warehouse_filters = array_map('intval', $warehouse_filters);
        $status_filters = array_map('sanitize_text_field', $status_filters);

        // Determinar qué almacenes mostrar en las columnas
        $almacenes_a_mostrar = array();
        if (!empty($warehouse_filters)) {
            foreach ($almacenes as $almacen) {
                if (in_array($almacen->id, $warehouse_filters)) {
                    $almacenes_a_mostrar[] = $almacen;
                }
            }
        } else {
            $almacenes_a_mostrar = $almacenes;
        }

        // Procesar y agrupar todos los productos
        $productos_agrupados = $this->agrupar_todos_productos($todos_productos, $almacenes);
        
        // Aplicar filtros con nueva opción de mostrar todos
        $productos_filtrados = $this->aplicar_filtros_reportes_extendido($productos_agrupados, $warehouse_filters, $status_filters, $threshold, $show_all_products);

        // Estadísticas generales (usando almacenes filtrados)
        $this->mostrar_estadisticas_stock_agrupadas_extendido($productos_filtrados, $almacenes_a_mostrar, $threshold, $todos_productos);

        // Formulario de filtros con checkboxes
        echo '<div class="gab-form-section">';
        echo '<form method="get" action="" id="gab-report-filters">';
        echo '<input type="hidden" name="page" value="gab-stock-report">';
        
        echo '<div class="gab-form-row">';
        
        // Filtros por almacén (checkboxes)
        echo '<div class="gab-form-group">';
        echo '<label><strong>' . esc_html__('Filtrar por Almacenes', 'gestion-almacenes') . '</strong></label>';
        echo '<div class="filter-checkbox-container">';
        
        if ($almacenes) {
            foreach ($almacenes as $almacen) {
                $checked = in_array($almacen->id, $warehouse_filters) ? 'checked' : '';
                echo '<label>';
                echo '<input type="checkbox" name="warehouse_filter[]" value="' . esc_attr($almacen->id) . '" ' . $checked . '>';
                echo esc_html($almacen->name) . ' (' . esc_html($almacen->ciudad) . ')';
                echo '</label>';
            }
        }
        
        echo '</div>';
        echo '<small class="description">' . esc_html__('Selecciona almacenes específicos para mostrar solo esas columnas.', 'gestion-almacenes') . '</small>';
        echo '</div>';

        // Filtros por estado (checkboxes)
        echo '<div class="gab-form-group">';
        echo '<label><strong>' . esc_html__('Filtrar por Estado de Stock', 'gestion-almacenes') . '</strong></label>';
        echo '<div class="filter-checkbox-container">';
        
        $estados = array(
            'high' => __('Stock alto', 'gestion-almacenes'),
            'medium' => __('Stock medio', 'gestion-almacenes'),
            'low' => __('Stock bajo', 'gestion-almacenes'),
            'out' => __('Sin stock', 'gestion-almacenes'),
            'unassigned' => __('Sin asignar', 'gestion-almacenes') // NUEVO ESTADO
        );
        
        foreach ($estados as $key => $label) {
            $checked = in_array($key, $status_filters) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="status_filter[]" value="' . esc_attr($key) . '" ' . $checked . '>';
            echo '<span class="gab-badge stock-' . esc_attr($key) . '" style="margin-right: 5px;">' . esc_html($label) . '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '<small class="description">' . esc_html__('Filtra productos por su estado de stock.', 'gestion-almacenes') . '</small>';
        echo '</div>';

        echo '</div>'; // fin gab-form-row

        // NUEVO: Opción para mostrar todos los productos
        echo '<div class="gab-form-row">';
        echo '<div class="gab-form-group">';
        echo '<label>';
        $checked_all = $show_all_products === 'yes' ? 'checked' : '';
        echo '<input type="checkbox" name="show_all" value="yes" ' . $checked_all . '>';
        echo '<strong>' . esc_html__('Mostrar productos sin stock en ningún almacén', 'gestion-almacenes') . '</strong>';
        echo '</label>';
        echo '<small class="description">' . esc_html__('Incluye productos de WooCommerce que no tienen stock asignado en ningún almacén.', 'gestion-almacenes') . '</small>';
        echo '</div>';
        echo '</div>';

        // Botones de acción
        echo '<div class="gab-form-row">';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Aplicar Filtros', 'gestion-almacenes') . '">';
        echo ' <a href="' . esc_url(admin_url('admin.php?page=gab-stock-report')) . '" class="button button-secondary">' . esc_html__('Limpiar Filtros', 'gestion-almacenes') . '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

        // Información sobre filtros activos
        if (!empty($warehouse_filters) || !empty($status_filters) || $show_all_products === 'yes') {
            echo '<div class="gab-message info" style="margin-bottom: 20px;">';
            echo '<p><strong>' . esc_html__('Filtros activos:', 'gestion-almacenes') . '</strong> ';
            
            $filtros_activos = array();
            
            if (!empty($warehouse_filters)) {
                $nombres_almacenes = array();
                foreach ($almacenes_a_mostrar as $almacen) {
                    $nombres_almacenes[] = $almacen->name;
                }
                $filtros_activos[] = sprintf(
                    esc_html__('Almacenes: %s', 'gestion-almacenes'),
                    implode(', ', $nombres_almacenes)
                );
            }
            
            if (!empty($status_filters)) {
                $nombres_estados = array();
                foreach ($status_filters as $status) {
                    if (isset($estados[$status])) {
                        $nombres_estados[] = $estados[$status];
                    }
                }
                $filtros_activos[] = sprintf(
                    esc_html__('Estados: %s', 'gestion-almacenes'),
                    implode(', ', $nombres_estados)
                );
            }
            
            if ($show_all_products === 'yes') {
                $filtros_activos[] = esc_html__('Mostrando todos los productos', 'gestion-almacenes');
            }
            
            echo implode(' | ', $filtros_activos);
            echo '</p>';
            echo '</div>';
        }

        // Tabla de reporte con columnas filtradas por almacén
        if (!empty($productos_filtrados)) {
            echo '<div class="gab-table-container">';
            echo '<table class="gab-table wp-list-table widefat fixed striped" id="stock-report-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="min-width: 200px;">' . esc_html__('Producto', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 80px;">' . esc_html__('SKU', 'gestion-almacenes') . '</th>';
            
            // Columnas dinámicas SOLO por almacenes seleccionados
            if ($almacenes_a_mostrar) {
                foreach ($almacenes_a_mostrar as $almacen) {
                    echo '<th style="width: 100px; text-align: center;">';
                    echo esc_html($almacen->name);
                    echo '<br><small style="font-weight: normal;">' . esc_html($almacen->ciudad) . '</small>';
                    echo '</th>';
                }
            }
            
            echo '<th style="width: 80px;">' . esc_html__('Total', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 100px;">' . esc_html__('Estado', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Acciones', 'gestion-almacenes') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($productos_filtrados as $product_id => $producto_data) {
                $product = wc_get_product($product_id);
                if (!$product) continue;

                echo '<tr>';
                
                // Producto
                echo '<td>';
                echo '<strong>' . esc_html($product->get_name()) . '</strong>';
                if ($product->get_type() === 'variation') {
                    echo '<br><small>' . esc_html($product->get_formatted_variation_attributes(true)) . '</small>';
                }
                echo '</td>';

                // SKU
                echo '<td>' . esc_html($product->get_sku() ?: '-') . '</td>';

                // Columnas de stock SOLO por almacenes seleccionados
                $total_stock_filtrado = 0;
                if ($almacenes_a_mostrar) {
                    foreach ($almacenes_a_mostrar as $almacen) {
                        $stock = isset($producto_data['almacenes'][$almacen->id]) ? $producto_data['almacenes'][$almacen->id] : 0;
                        $total_stock_filtrado += $stock;
                        
                        echo '<td style="text-align: center;">';
                        if ($stock > 0) {
                            $status_class = $this->obtener_clase_estado_stock($stock, $threshold);
                            echo '<span class="' . esc_attr($status_class) . '">' . esc_html($stock) . '</span>';
                        } else {
                            echo '<span style="color: #999;">0</span>';
                        }
                        echo '</td>';
                    }
                }

                // Total (solo de almacenes mostrados)
                echo '<td style="text-align: center;">';
                echo '<strong>' . esc_html($total_stock_filtrado) . '</strong>';
                echo '</td>';

                // Estado general
                echo '<td style="text-align: center;">';
                if ($producto_data['sin_asignar']) {
                    echo '<span class="gab-badge stock-unassigned">' . esc_html__('Sin asignar', 'gestion-almacenes') . '</span>';
                } else {
                    $producto_data_filtrado = array(
                        'almacenes' => array(),
                        'total_stock' => $total_stock_filtrado
                    );
                    
                    foreach ($almacenes_a_mostrar as $almacen) {
                        if (isset($producto_data['almacenes'][$almacen->id])) {
                            $producto_data_filtrado['almacenes'][$almacen->id] = $producto_data['almacenes'][$almacen->id];
                        }
                    }
                    
                    echo $this->obtener_badge_estado_general($producto_data_filtrado, $threshold);
                }
                echo '</td>';

                // Acciones
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('post.php?post=' . $product_id . '&action=edit')) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Editar producto', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-edit"></span>';
                echo '</a> ';
                
                echo '<a href="' . esc_url(add_query_arg(array(
                    'page' => 'gab-transfer-stock',
                    'product_id' => $product_id
                ), admin_url('admin.php'))) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Transferir stock', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-randomize"></span>';
                echo '</a> ';
                
                // NUEVO: Botón para ajustar stock inicial
                echo '<button class="button button-small adjust-stock" ';
                echo 'data-product-id="' . esc_attr($product_id) . '" ';
                echo 'data-product-name="' . esc_attr($product->get_name()) . '" ';
                echo 'title="' . esc_attr__('Ajustar stock', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-update"></span>';
                echo '</button>';
                
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            // Resumen de la tabla
            $total_items = count($productos_filtrados);
            $total_almacenes_mostrados = count($almacenes_a_mostrar);
            
            echo '<p class="description">';
            echo sprintf(
                esc_html__('Mostrando %d producto(s) en %d almacén(es).', 'gestion-almacenes'), 
                $total_items, 
                $total_almacenes_mostrados
            );
            echo '</p>';

        } else {
            echo '<div class="gab-message info">';
            echo '<h3>' . esc_html__('No se encontraron productos', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('No hay productos que coincidan con los filtros seleccionados.', 'gestion-almacenes') . '</p>';
            echo '</div>';
        }

        // Incluir el modal de ajuste de stock aquí
        $this->render_adjust_stock_modal($almacenes);

        echo '</div>'; // fin wrap
    }

    /**
     * Agrupar TODOS los productos (incluyendo los sin stock asignado)
     */
    private function agrupar_todos_productos($todos_productos, $almacenes) {
        $productos_agrupados = array();
        
        foreach ($todos_productos as $producto) {
            $product_id = $producto->product_id;
            
            $productos_agrupados[$product_id] = array(
                'almacenes' => array(),
                'total_stock' => 0,
                'sin_asignar' => empty($producto->warehouse_stock)
            );
            
            // Si tiene stock en almacenes
            if (!empty($producto->warehouse_stock)) {
                foreach ($producto->warehouse_stock as $warehouse_id => $stock) {
                    $productos_agrupados[$product_id]['almacenes'][$warehouse_id] = intval($stock);
                    $productos_agrupados[$product_id]['total_stock'] += intval($stock);
                }
            }
            
            // Agregar información adicional del producto
            $productos_agrupados[$product_id]['wc_stock'] = $producto->wc_stock;
            $productos_agrupados[$product_id]['manage_stock'] = $producto->manage_stock;
            $productos_agrupados[$product_id]['sku'] = $producto->sku;
        }
        
        return $productos_agrupados;
    }

    /**
     * Aplicar filtros extendido (incluye opción de mostrar todos)
     */
    private function aplicar_filtros_reportes_extendido($productos_agrupados, $warehouse_filters, $status_filters, $threshold, $show_all) {
        $productos_filtrados = array();
        
        foreach ($productos_agrupados as $product_id => $producto_data) {
            $incluir_producto = false;
            
            // Si está marcado mostrar todos y el producto no tiene stock asignado
            if ($show_all === 'yes' && $producto_data['sin_asignar']) {
                $incluir_producto = true;
            }
            
            // Si tiene stock en algún almacén
            if ($producto_data['total_stock'] > 0) {
                $incluir_producto = true;
                
                // Aplicar filtro por almacenes si existe
                if (!empty($warehouse_filters)) {
                    $tiene_stock_en_almacenes_filtrados = false;
                    foreach ($warehouse_filters as $warehouse_id) {
                        if (isset($producto_data['almacenes'][$warehouse_id]) && $producto_data['almacenes'][$warehouse_id] > 0) {
                            $tiene_stock_en_almacenes_filtrados = true;
                            break;
                        }
                    }
                    if (!$tiene_stock_en_almacenes_filtrados) {
                        $incluir_producto = false;
                    }
                }
            }
            
            // Filtro por estado
            if (!empty($status_filters) && $incluir_producto) {
                $cumple_estado = false;
                
                // Si el producto está sin asignar
                if ($producto_data['sin_asignar'] && in_array('unassigned', $status_filters)) {
                    $cumple_estado = true;
                } else {
                    // Usar la lógica existente para productos con stock
                    $estado_producto = $this->determinar_estado_producto($producto_data, $threshold);
                    foreach ($status_filters as $status) {
                        if (in_array($status, $estado_producto)) {
                            $cumple_estado = true;
                            break;
                        }
                    }
                }
                
                if (!$cumple_estado) {
                    $incluir_producto = false;
                }
            }
            
            if ($incluir_producto) {
                $productos_filtrados[$product_id] = $producto_data;
            }
        }
        
        return $productos_filtrados;
    }

    /**
     * Mostrar estadísticas extendidas
     */
    private function mostrar_estadisticas_stock_agrupadas_extendido($productos_filtrados, $almacenes_mostrados, $threshold, $todos_productos) {
        $total_productos = count($productos_filtrados);
        $total_productos_wc = count($todos_productos);
        $total_stock = 0;
        $productos_sin_stock = 0;
        $productos_stock_bajo = 0;
        $productos_sin_asignar = 0;
        $total_almacenes_mostrados = count($almacenes_mostrados);

        foreach ($productos_filtrados as $producto_data) {
            if ($producto_data['sin_asignar']) {
                $productos_sin_asignar++;
                continue;
            }
            
            // Calcular solo el stock de los almacenes que se están mostrando
            $stock_producto_filtrado = 0;
            $tiene_stock_bajo_en_mostrados = false;
            
            foreach ($almacenes_mostrados as $almacen) {
                if (isset($producto_data['almacenes'][$almacen->id])) {
                    $stock_almacen = $producto_data['almacenes'][$almacen->id];
                    $stock_producto_filtrado += $stock_almacen;
                    
                    if ($stock_almacen > 0 && $stock_almacen <= $threshold) {
                        $tiene_stock_bajo_en_mostrados = true;
                    }
                }
            }
            
            $total_stock += $stock_producto_filtrado;
            
            if ($stock_producto_filtrado == 0) {
                $productos_sin_stock++;
            } elseif ($tiene_stock_bajo_en_mostrados) {
                $productos_stock_bajo++;
            }
        }

        echo '<div class="gab-stats-grid">';
        
        // Nueva estadística: Total productos en WooCommerce
        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_productos_wc . '</span>';
        echo '<span class="stat-label">' . esc_html__('Total Productos WC', 'gestion-almacenes') . '</span>';
        echo '</div>';
        
        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_almacenes_mostrados . '</span>';
        echo '<span class="stat-label">' . esc_html__('Almacenes Mostrados', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_productos . '</span>';
        echo '<span class="stat-label">' . esc_html__('Productos Filtrados', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Stock Total', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #d63638;">' . $productos_stock_bajo . '</span>';
        echo '<span class="stat-label">' . esc_html__('Stock Bajo', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #999;">' . $productos_sin_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Sin Stock', 'gestion-almacenes') . '</span>';
        echo '</div>';
        
        // Nueva estadística: Productos sin asignar
        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #2271b1;">' . $productos_sin_asignar . '</span>';
        echo '<span class="stat-label">' . esc_html__('Sin Asignar', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Renderizar modal de ajuste de stock
     */
    private function render_adjust_stock_modal($almacenes) {
        ?>
        <!-- Modal de Ajuste de Stock -->
        <div id="adjust-stock-modal" class="gab-modal" style="display: none;">
            <div class="gab-modal-content" style="max-width: 500px;">
                <div class="gab-modal-header">
                    <h2>Ajustar Stock</h2>
                    <span class="gab-modal-close">&times;</span>
                </div>
                
                <form id="adjust-stock-form" method="post">
                    <input type="hidden" id="adjust_product_id" name="product_id" value="">
                    
                    <div class="gab-form-group">
                        <label>Producto:</label>
                        <p id="adjust_product_name" style="font-weight: bold; margin: 5px 0;"></p>
                    </div>
                    
                    <div class="gab-form-group">
                        <label for="adjust_warehouse_id">Almacén *</label>
                        <select id="adjust_warehouse_id" name="warehouse_id" required>
                            <option value="">Seleccionar almacén...</option>
                            <?php foreach ($almacenes as $almacen) : ?>
                                <option value="<?php echo esc_attr($almacen->id); ?>">
                                    <?php echo esc_html($almacen->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="gab-form-group">
                        <label for="adjust_stock">Cantidad de Stock *</label>
                        <input type="number" id="adjust_stock" name="stock" min="0" required>
                        <p class="description">Ingrese la cantidad total de stock para este producto en el almacén seleccionado.</p>
                    </div>
                    
                    <div class="gab-form-group">
                        <label for="adjust_operation">Tipo de Operación *</label>
                        <select id="adjust_operation" name="operation" required>
                            <option value="set">Establecer stock (reemplazar cantidad actual)</option>
                            <option value="add">Agregar al stock existente</option>
                            <option value="subtract">Restar del stock existente</option>
                        </select>
                    </div>
                    
                    <div class="gab-form-actions">
                        <button type="button" class="button gab-cancel-btn">Cancelar</button>
                        <button type="submit" class="button button-primary">Ajustar Stock</button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Manejar modal de ajuste de stock
            $('.adjust-stock').on('click', function() {
                var productId = $(this).data('product-id');
                var productName = $(this).data('product-name');
                
                $('#adjust_product_id').val(productId);
                $('#adjust_product_name').text(productName);
                $('#adjust-stock-form')[0].reset();
                $('#adjust_product_id').val(productId);
                
                $('#adjust-stock-modal').fadeIn(300);
            });
            
            $('#adjust-stock-modal .gab-modal-close, #adjust-stock-modal .gab-cancel-btn').on('click', function() {
                $('#adjust-stock-modal').fadeOut(300);
            });
            
            $('#adjust-stock-modal').on('click', function(e) {
                if ($(e.target).is('#adjust-stock-modal')) {
                    $(this).fadeOut(300);
                }
            });
            
            $('#adjust-stock-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'gab_adjust_stock',
                    product_id: $('#adjust_product_id').val(),
                    warehouse_id: $('#adjust_warehouse_id').val(),
                    stock: $('#adjust_stock').val(),
                    operation: $('#adjust_operation').val(),
                    nonce: '<?php echo wp_create_nonce('gab_adjust_stock'); ?>'
                };
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>', // Usar admin_url en lugar de ajaxurl
                    type: 'POST',
                    data: formData,
                    beforeSend: function() {
                        $('#adjust-stock-form button[type="submit"]').prop('disabled', true).text('Procesando...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || 'Stock ajustado correctamente');
                            location.reload();
                        } else {
                            alert(response.data || 'Error al ajustar el stock');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', status, error);
                        console.error('Response:', xhr.responseText);
                        alert('Error de conexión al servidor');
                    },
                    complete: function() {
                        $('#adjust-stock-form button[type="submit"]').prop('disabled', false).text('Ajustar Stock');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Ajustar stock de un producto en un almacén
     */
    public function ajax_adjust_stock() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gab_adjust_stock')) {
            wp_send_json_error('Nonce inválido');
            wp_die();
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos suficientes');
            wp_die();
        }
        
        // Obtener y validar datos
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $warehouse_id = isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0;
        $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
        $operation = isset($_POST['operation']) ? sanitize_text_field($_POST['operation']) : 'set';
        
        if (!$product_id || !$warehouse_id) {
            wp_send_json_error('Datos incompletos');
            wp_die();
        }
        
        if ($stock < 0) {
            wp_send_json_error('La cantidad no puede ser negativa');
            wp_die();
        }
        
        // Verificar que el producto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Producto no encontrado');
            wp_die();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Obtener stock actual
        $current_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT stock FROM $table WHERE product_id = %d AND warehouse_id = %d",
            $product_id,
            $warehouse_id
        ));
        
        $new_stock = 0;
        
        // Calcular nuevo stock según la operación
        switch ($operation) {
            case 'add':
                $new_stock = ($current_stock !== null ? intval($current_stock) : 0) + $stock;
                break;
                
            case 'subtract':
                $current = $current_stock !== null ? intval($current_stock) : 0;
                $new_stock = max(0, $current - $stock);
                break;
                
            case 'set':
            default:
                $new_stock = $stock;
                break;
        }
        
        // Actualizar o insertar stock
        if ($current_stock !== null) {
            // Actualizar registro existente
            $result = $wpdb->update(
                $table,
                array('stock' => $new_stock),
                array(
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id
                ),
                array('%d'),
                array('%d', '%d')
            );
        } else {
            // Crear nuevo registro
            $result = $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'stock' => $new_stock
                ),
                array('%d', '%d', '%d')
            );
        }
        
        if ($result === false) {
            wp_send_json_error('Error al actualizar el stock: ' . $wpdb->last_error);
            wp_die();
        }
        
        // Obtener información del almacén
        $warehouse = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}gab_warehouses WHERE id = %d",
            $warehouse_id
        ));
        
        // Log de la operación (opcional)
        $log_message = sprintf(
            'Stock ajustado: %s (ID: %d) - Almacén: %s - Operación: %s - Cantidad: %d - Nuevo stock: %d',
            $product->get_name(),
            $product_id,
            $warehouse ? $warehouse->name : 'ID: ' . $warehouse_id,
            $operation,
            $stock,
            $new_stock
        );
        
        // Aquí podrías agregar el registro en una tabla de logs si la tienes
        error_log('GAB Stock: ' . $log_message);
        
        wp_send_json_success(array(
            'message' => 'Stock ajustado correctamente',
            'new_stock' => $new_stock,
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id
        ));
    }

    /**
     * Determinar estados de un producto
     */
    private function determinar_estado_producto($producto_data, $threshold) {
        $estados = array();
        
        foreach ($producto_data['almacenes'] as $warehouse_id => $stock) {
            if ($stock == 0) {
                $estados[] = 'out';
            } elseif ($stock <= $threshold) {
                $estados[] = 'low';
            } elseif ($stock <= ($threshold * 2)) {
                $estados[] = 'medium';
            } else {
                $estados[] = 'high';
            }
        }
        
        return array_unique($estados);
    }

    /**
     * Obtener clase CSS para estado de stock
     */
    private function obtener_clase_estado_stock($stock, $threshold) {
        if ($stock == 0) {
            return 'gab-badge stock-out';
        } elseif ($stock <= $threshold) {
            return 'gab-badge stock-low';
        } elseif ($stock <= ($threshold * 2)) {
            return 'gab-badge stock-medium';
        } else {
            return 'gab-badge stock-high';
        }
    }

    /**
     * Obtener badge de estado general
     */
    private function obtener_badge_estado_general($producto_data, $threshold) {
        $total_stock = $producto_data['total_stock'];
        
        if ($total_stock == 0) {
            return '<span class="gab-badge stock-out">' . esc_html__('Sin stock', 'gestion-almacenes') . '</span>';
        }
        
        $tiene_stock_bajo = false;
        $tiene_stock_alto = false;
        
        foreach ($producto_data['almacenes'] as $stock) {
            if ($stock > 0 && $stock <= $threshold) {
                $tiene_stock_bajo = true;
            } elseif ($stock > ($threshold * 2)) {
                $tiene_stock_alto = true;
            }
        }
        
        if ($tiene_stock_bajo) {
            return '<span class="gab-badge stock-low">' . esc_html__('Stock bajo', 'gestion-almacenes') . '</span>';
        } elseif ($tiene_stock_alto) {
            return '<span class="gab-badge stock-high">' . esc_html__('Stock alto', 'gestion-almacenes') . '</span>';
        } else {
            return '<span class="gab-badge stock-medium">' . esc_html__('Stock medio', 'gestion-almacenes') . '</span>';
        }
    }

    /**
     * Mostrar estadísticas de stock agrupadas
     */
    private function mostrar_estadisticas_stock_agrupadas($productos_filtrados, $almacenes_mostrados, $threshold) {
        $total_productos = count($productos_filtrados);
        $total_stock = 0;
        $productos_sin_stock = 0;
        $productos_stock_bajo = 0;
        $total_almacenes_mostrados = count($almacenes_mostrados);

        foreach ($productos_filtrados as $producto_data) {
            // Calcular solo el stock de los almacenes que se están mostrando
            $stock_producto_filtrado = 0;
            $tiene_stock_bajo_en_mostrados = false;
            
            foreach ($almacenes_mostrados as $almacen) {
                if (isset($producto_data['almacenes'][$almacen->id])) {
                    $stock_almacen = $producto_data['almacenes'][$almacen->id];
                    $stock_producto_filtrado += $stock_almacen;
                    
                    if ($stock_almacen > 0 && $stock_almacen <= $threshold) {
                        $tiene_stock_bajo_en_mostrados = true;
                    }
                }
            }
            
            $total_stock += $stock_producto_filtrado;
            
            if ($stock_producto_filtrado == 0) {
                $productos_sin_stock++;
            } elseif ($tiene_stock_bajo_en_mostrados) {
                $productos_stock_bajo++;
            }
        }

        echo '<div class="gab-stats-grid">';
        
        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_almacenes_mostrados . '</span>';
        echo '<span class="stat-label">' . esc_html__('Almacenes Mostrados', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_productos . '</span>';
        echo '<span class="stat-label">' . esc_html__('Productos', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Stock Total', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #d63638;">' . $productos_stock_bajo . '</span>';
        echo '<span class="stat-label">' . esc_html__('Stock Bajo', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #999;">' . $productos_sin_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Sin Stock', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '</div>';
    }

    // Página Transferencias
    public function mostrar_transferir_stock() {
        global $gestion_almacenes_db;

        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Transferir Stock entre Almacenes', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Transfiere productos de un almacén a otro de forma segura.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        // Procesar transferencia si se envió el formulario
        if (isset($_POST['transfer_stock']) && wp_verify_nonce($_POST['transfer_nonce'], 'transfer_stock_nonce')) {
            $product_id = intval($_POST['product_id']);
            $from_warehouse = intval($_POST['from_warehouse']);
            $to_warehouse = intval($_POST['to_warehouse']);
            $quantity = intval($_POST['quantity']);

            if ($product_id && $from_warehouse && $to_warehouse && $quantity > 0 && $from_warehouse !== $to_warehouse) {
                $result = $gestion_almacenes_db->transfer_stock_between_warehouses($product_id, $from_warehouse, $to_warehouse, $quantity);

                if ($result) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p>' . esc_html__('Transferencia realizada con éxito.', 'gestion-almacenes') . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p>' . esc_html__('Error al realizar la transferencia. Verifica que haya suficiente stock.', 'gestion-almacenes') . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html__('Datos inválidos. Verifica todos los campos.', 'gestion-almacenes') . '</p>';
                echo '</div>';
            }
        }

        // Obtener datos necesarios
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        $productos_wc = $this->obtener_productos_woocommerce();

        if (!$almacenes || count($almacenes) < 2) {
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('Insuficientes almacenes', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('Necesitas al menos 2 almacenes para realizar transferencias.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-add-new-warehouse')) . '" class="button button-primary">';
            echo esc_html__('Agregar Almacén', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }

        if (!$productos_wc || count($productos_wc) === 0) {
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('No hay productos', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('No se encontraron productos de WooCommerce con stock en almacenes.', 'gestion-almacenes') . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Formulario de transferencia
        echo '<div class="gab-form-section">';
        echo '<form method="post" action="" id="gab-transfer-form">';
        wp_nonce_field('transfer_stock_nonce', 'transfer_nonce');

        echo '<div class="gab-form-row">';
        
        // Selector de producto
        echo '<div class="gab-form-group">';
        echo '<label for="product_id">' . esc_html__('Seleccionar Producto', 'gestion-almacenes') . '</label>';
        echo '<select name="product_id" id="product_id" required>';
        echo '<option value="">' . esc_html__('Selecciona un producto...', 'gestion-almacenes') . '</option>';
        
        foreach ($productos_wc as $producto) {
            echo '<option value="' . esc_attr($producto->ID) . '">';
            echo esc_html($producto->post_title) . ' (ID: ' . esc_html($producto->ID) . ')';
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        // Almacén origen
        echo '<div class="gab-form-group">';
        echo '<label for="from_warehouse">' . esc_html__('Almacén Origen', 'gestion-almacenes') . '</label>';
        echo '<select name="from_warehouse" id="from_warehouse" required>';
        echo '<option value="">' . esc_html__('Selecciona almacén origen...', 'gestion-almacenes') . '</option>';
        
        foreach ($almacenes as $almacen) {
            echo '<option value="' . esc_attr($almacen->id) . '">';
            echo esc_html($almacen->name) . ' - ' . esc_html($almacen->ciudad);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        echo '</div>'; // fin gab-form-row

        echo '<div class="gab-form-row">';

        // Almacén destino
        echo '<div class="gab-form-group">';
        echo '<label for="to_warehouse">' . esc_html__('Almacén Destino', 'gestion-almacenes') . '</label>';
        echo '<select name="to_warehouse" id="to_warehouse" required>';
        echo '<option value="">' . esc_html__('Selecciona almacén destino...', 'gestion-almacenes') . '</option>';
        
        foreach ($almacenes as $almacen) {
            echo '<option value="' . esc_attr($almacen->id) . '">';
            echo esc_html($almacen->name) . ' - ' . esc_html($almacen->ciudad);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        // Cantidad
        echo '<div class="gab-form-group">';
        echo '<label for="quantity">' . esc_html__('Cantidad a Transferir', 'gestion-almacenes') . '</label>';
        echo '<input type="number" name="quantity" id="quantity" min="1" required>';
        echo '</div>';

        echo '</div>'; // fin gab-form-row

        // Información de stock
        echo '<div id="stock_info" class="gab-stock-info">';
        echo esc_html__('Selecciona un producto y almacén origen para ver el stock disponible.', 'gestion-almacenes');
        echo '</div>';

        // Botón de transferencia
        echo '<div class="gab-form-row">';
        echo '<div class="gab-form-group">';
        echo '<input type="submit" name="transfer_stock" class="button button-primary gab-button-primary" ';
        echo 'value="' . esc_attr__('Realizar Transferencia', 'gestion-almacenes') . '">';
        echo '</div>';
        echo '</div>';

        echo '</form>';
        echo '</div>'; // fin gab-form-section

        // Historial reciente de transferencias (opcional)
        echo '<div class="gab-section-header" style="margin-top: 40px;">';
        echo '<h2>' . esc_html__('Transferencias Recientes', 'gestion-almacenes') . '</h2>';
        echo '</div>';

        $this->mostrar_transferencias_recientes();

        echo '</div>'; // fin wrap
    }

    /**
     * Función auxiliar para obtener productos de WooCommerce
     */
    private function obtener_productos_woocommerce() {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_manage_stock',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Mostrar historial de transferencias recientes
     */
    private function mostrar_transferencias_recientes() {
        echo '<div class="gab-message info">';
        echo '<p>' . esc_html__('El historial de transferencias esta en desarrollo aún', 'gestion-almacenes') . '</p>';
        echo '</div>';
    }

    /**
    * Handler AJAX para obtener stock de almacén
    */
    public function ajax_get_warehouse_stock() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'get_warehouse_stock_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $warehouse_id = intval($_POST['warehouse_id']);
        
        if (!$product_id || !$warehouse_id) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        global $gestion_almacenes_db;
        $stock = $gestion_almacenes_db->get_product_stock_in_warehouse($product_id, $warehouse_id);
        
        wp_send_json_success(array('stock' => $stock));
    }


    /**
     * Mostrar página de nueva transferencia
     */
    public function mostrar_nueva_transferencia() {
        global $gestion_almacenes_db;
        
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Nueva Transferencia de Stock', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Crear una nueva transferencia entre almacenes.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        $almacenes = $gestion_almacenes_db->obtener_almacenes();

        if (!$almacenes || count($almacenes) < 2) {
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('Almacenes insuficientes', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('Necesitas al menos 2 almacenes para crear transferencias.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-add-new-warehouse')) . '" class="button button-primary">';
            echo esc_html__('Agregar Almacén', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }

        ?>
        <form id="gab-transfer-form">
            <?php wp_nonce_field('gab_transfer_nonce', 'nonce'); ?>
            
            <div class="gab-form-section">
                <h3><?php esc_html_e('Información de la Transferencia', 'gestion-almacenes'); ?></h3>
                
                <div class="gab-form-row">
                    <div class="gab-form-group">
                        <label for="source_warehouse"><?php esc_html_e('Almacén Origen', 'gestion-almacenes'); ?></label>
                        <select name="source_warehouse" id="source_warehouse" required>
                            <option value=""><?php esc_html_e('Seleccionar almacén origen...', 'gestion-almacenes'); ?></option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo esc_attr($almacen->id); ?>">
                                    <?php echo esc_html($almacen->name . ' - ' . $almacen->ciudad); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="gab-form-group">
                        <label for="target_warehouse"><?php esc_html_e('Almacén Destino', 'gestion-almacenes'); ?></label>
                        <select name="target_warehouse" id="target_warehouse" required>
                            <option value=""><?php esc_html_e('Seleccionar almacén destino...', 'gestion-almacenes'); ?></option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo esc_attr($almacen->id); ?>">
                                    <?php echo esc_html($almacen->name . ' - ' . $almacen->ciudad); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="gab-form-section">
                <h3><?php esc_html_e('Productos a Transferir', 'gestion-almacenes'); ?></h3>
                
                <table class="wp-list-table widefat fixed striped" id="products-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;"><?php esc_html_e('SKU', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Producto', 'gestion-almacenes'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Stock Disponible', 'gestion-almacenes'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Cantidad', 'gestion-almacenes'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <tr id="no-products-row">
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <div style="color: #666; font-style: italic; margin-bottom: 15px;">
                                    <?php esc_html_e('No hay productos agregados', 'gestion-almacenes'); ?>
                                </div>
                                <button type="button" id="add-product-btn" class="button button-primary">
                                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php esc_html_e('Añadir producto', 'gestion-almacenes'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr id="add-product-row" style="display: none;">
                            <td colspan="5" style="text-align: center; padding: 15px; background-color: #f0f6fc;">
                                <button type="button" id="add-product-btn-table" class="button button-secondary">
                                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php esc_html_e('Añadir producto', 'gestion-almacenes'); ?>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="gab-form-section">
                <h3><?php esc_html_e('Notas Adicionales', 'gestion-almacenes'); ?></h3>
                <textarea name="notes" id="notes" rows="4" style="width: 100%;" 
                        placeholder="<?php esc_attr_e('Agregar notas o comentarios sobre esta transferencia...', 'gestion-almacenes'); ?>"></textarea>
            </div>

            <div class="gab-form-row">
                <div class="gab-form-group">
                    <input type="submit" class="button button-primary gab-button-primary" 
                        value="<?php esc_attr_e('Crear Transferencia', 'gestion-almacenes'); ?>" id="submit-transfer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gab-transfer-list')); ?>" 
                    class="button button-secondary"><?php esc_html_e('Cancelar', 'gestion-almacenes'); ?></a>
                </div>
            </div>
        </form>

        <!-- Modal para búsqueda de productos -->
        <div id="product-search-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php esc_html_e('Buscar Productos', 'gestion-almacenes'); ?></h2>
                <input type="text" id="product-search" placeholder="<?php esc_attr_e('Escriba para buscar productos...', 'gestion-almacenes'); ?>">
                <div id="search-results"></div>
            </div>
        </div>
        
        <style>
        /* Modal exacto del plugin de referencia */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            position: relative;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 10px;
        }

        .close:hover {
            color: black;
        }

        .modal-content h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
            padding-right: 40px;
        }

        #product-search {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        #product-search:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
        }

        #search-results {
            max-height: calc(80vh - 150px);
            overflow-y: auto;
            margin-top: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background: white;
        }

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
            font-size: 14px;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }

        .searching, .no-results {
            padding: 10px;
            color: #666;
            font-style: italic;
            text-align: center;
        }

        .error {
            color: #dc3545;
            padding: 10px;
            text-align: center;
        }

        #search-results::-webkit-scrollbar {
            width: 8px;
        }

        #search-results::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #search-results::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        #search-results::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 2% auto;
                padding: 15px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Script cargado'); // Debug
            
            let productCount = 0;
            const MAX_PRODUCTS = 60;
            
            // Debug - verificar elementos
            console.log('Modal element:', $('#product-search-modal').length);
            console.log('Add product buttons:', $('#add-product-btn, #add-product-btn-table').length);
            
            // Función para abrir el modal
            function openModal() {
                console.log('Abriendo modal'); // Debug
                $('#product-search-modal').show();
                $('#product-search').val('').focus();
                $('#search-results').empty();
            }
            
            // Función para cerrar el modal
            function closeModal() {
                console.log('Cerrando modal'); // Debug
                $('#product-search-modal').hide();
                $('#product-search').val('');
                $('#search-results').empty();
            }
            
            // Event handlers para abrir modal
            $(document).on('click', '#add-product-btn, #add-product-btn-table', function(e) {
                e.preventDefault();
                console.log('Click en agregar producto'); // Debug
                
                const sourceWarehouse = $('#source_warehouse').val();
                if (!sourceWarehouse) {
                    alert('<?php esc_html_e('Primero selecciona el almacén origen', 'gestion-almacenes'); ?>');
                    $('#source_warehouse').focus();
                    return;
                }
                openModal();
            });

            // Cerrar modal con X
            $(document).on('click', '.close', function() {
                closeModal();
            });
            
            // Cerrar modal al hacer clic fuera
            $(document).on('click', '#product-search-modal', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Prevenir que el modal se cierre al hacer clic dentro del contenido
            $(document).on('click', '.modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Búsqueda de productos
            let searchTimeout;
            $(document).on('input', '#product-search', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                if (query.length < 2) {
                    $('#search-results').empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    $('#search-results').html('<div class="searching"><?php esc_html_e('Buscando...', 'gestion-almacenes'); ?></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gab_search_products_transfer',
                            term: query,
                            nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('Respuesta búsqueda:', response); // Debug
                            $('#search-results').empty();
                            
                            if (response.success && response.data && response.data.products) {
                                if (response.data.products.length > 0) {
                                    response.data.products.forEach(function(product) {
                                        // Verificar si ya está agregado
                                        const isAdded = $(`tr[data-product-id="${product.id}"]`).length > 0;
                                        if (!isAdded) {
                                            $('#search-results').append(`
                                                <div class="search-result-item" data-product-id="${product.id}" data-product-name="${product.name}" data-product-sku="${product.sku || ''}">
                                                    ${product.sku ? `[${product.sku}] ` : ''}${product.name}
                                                </div>
                                            `);
                                        }
                                    });
                                    
                                    if ($('#search-results').children().length === 0) {
                                        $('#search-results').append('<div class="no-results"><?php esc_html_e('Todos los productos coincidentes ya están en la lista.', 'gestion-almacenes'); ?></div>');
                                    }
                                } else {
                                    $('#search-results').append('<div class="no-results"><?php esc_html_e('No se encontraron productos', 'gestion-almacenes'); ?></div>');
                                }
                            } else {
                                $('#search-results').append('<div class="error">' + (response.data ? response.data.message : '<?php esc_html_e('Error al buscar productos', 'gestion-almacenes'); ?>') + '</div>');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('Error en búsqueda:', textStatus, errorThrown);
                            $('#search-results').html('<div class="error"><?php esc_html_e('Error al buscar productos', 'gestion-almacenes'); ?></div>');
                        }
                    });
                }, 500);
            });

            // Seleccionar producto del modal
            $(document).on('click', '.search-result-item', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productSku = $(this).data('product-sku');
                const sourceWarehouse = $('#source_warehouse').val();
                
                addProductToTransfer(productId, productName, productSku, sourceWarehouse);
                closeModal();
            });
            
            // Función para agregar producto a la tabla
            function addProductToTransfer(productId, productName, productSku, sourceWarehouse) {
                if (productCount >= MAX_PRODUCTS) {
                    alert('<?php esc_html_e('Número máximo de productos alcanzado', 'gestion-almacenes'); ?>');
                    return;
                }

                // Verificar si el producto ya está en la lista
                if ($(`tr[data-product-id="${productId}"]`).length > 0) {
                    alert('<?php esc_html_e('Este producto ya está en la lista.', 'gestion-almacenes'); ?>');
                    return;
                }

                // Ocultar mensaje de "no hay productos"
                $('#no-products-row').hide();
                $('#add-product-row').show();

                // Agregar fila con indicador de carga
                const row = `
                    <tr data-product-id="${productId}">
                        <td>${productSku || ''}</td>
                        <td>${productName}</td>
                        <td class="available-stock"><span class="loading"><?php esc_html_e('Cargando...', 'gestion-almacenes'); ?></span></td>
                        <td>
                            <input type="number" name="products[${productCount}][quantity]" min="1" required 
                                class="demanded-stock-input" value="1" disabled style="width: 80px; text-align: center;">
                            <input type="hidden" name="products[${productCount}][product_id]" value="${productId}">
                        </td>
                        <td>
                            <button type="button" class="button remove-product">
                                <?php esc_html_e('Eliminar', 'gestion-almacenes'); ?>
                            </button>
                        </td>
                    </tr>
                `;
                
                $('#add-product-row').before(row);
                
                // Obtener stock disponible
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_get_warehouse_stock_transfer',
                        product_id: productId,
                        warehouse_id: sourceWarehouse,
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        const row = $(`tr[data-product-id="${productId}"]`);
                        if (response.success) {
                            const stock = parseInt(response.data.stock);
                            if (stock <= 0) {
                                row.remove();
                                alert('<?php esc_html_e('No hay stock disponible para este producto', 'gestion-almacenes'); ?>');
                                
                                // Verificar si no hay productos
                                if ($('#products-tbody tr[data-product-id]').length === 0) {
                                    $('#no-products-row').show();
                                    $('#add-product-row').hide();
                                }
                            } else {
                                row.find('.available-stock').html(`<strong style="color: #0073aa;">${stock}</strong>`);
                                const input = row.find('.demanded-stock-input');
                                input.prop('disabled', false).attr('max', stock);
                            }
                        } else {
                            row.find('.available-stock').html('<span class="error">Error</span>');
                        }
                        updateSubmitButton();
                    },
                    error: function() {
                        const row = $(`tr[data-product-id="${productId}"]`);
                        row.find('.available-stock').html('<span class="error">Error</span>');
                        updateSubmitButton();
                    }
                });

                productCount++;
            }

            // Eliminar producto
            $(document).on('click', '.remove-product', function() {
                $(this).closest('tr').remove();
                
                if ($('#products-tbody tr[data-product-id]').length === 0) {
                    $('#no-products-row').show();
                    $('#add-product-row').hide();
                }
                updateSubmitButton();
            });

            // Actualizar estado del botón submit
            function updateSubmitButton() {
                const $submitBtn = $('#submit-transfer');
                const hasProducts = $('#products-tbody tr[data-product-id]').length > 0;
                
                if (hasProducts) {
                    $submitBtn.prop('disabled', false).removeClass('button-disabled');
                } else {
                    $submitBtn.prop('disabled', true).addClass('button-disabled');
                }
            }

            // Validaciones y envío de formulario
            $('#target_warehouse').on('change', function() {
                const sourceWarehouse = $('#source_warehouse').val();
                const targetWarehouse = $(this).val();
                
                if (sourceWarehouse && targetWarehouse && sourceWarehouse === targetWarehouse) {
                    alert('<?php esc_html_e('Los almacenes origen y destino deben ser diferentes', 'gestion-almacenes'); ?>');
                    $(this).val('');
                }
            });

            $(document).on('change', '.demanded-stock-input', function() {
                const input = $(this);
                const max = parseInt(input.attr('max'), 10);
                const value = parseInt(input.val(), 10);
                
                if (value > max) {
                    alert('<?php esc_html_e('La cantidad no puede exceder el stock disponible', 'gestion-almacenes'); ?>');
                    input.val(max);
                } else if (value < 1) {
                    alert('<?php esc_html_e('La cantidad debe ser al menos 1', 'gestion-almacenes'); ?>');
                    input.val(1);
                }
            });

            $('#gab-transfer-form').on('submit', function(e) {
                e.preventDefault();
                
                const sourceWarehouse = $('#source_warehouse').val();
                const targetWarehouse = $('#target_warehouse').val();
                
                if (!sourceWarehouse || !targetWarehouse) {
                    alert('<?php esc_html_e('Debes seleccionar ambos almacenes', 'gestion-almacenes'); ?>');
                    return;
                }
                
                if ($('#products-tbody tr[data-product-id]').length === 0) {
                    alert('<?php esc_html_e('Debes agregar al menos un producto', 'gestion-almacenes'); ?>');
                    return;
                }

                const products = [];
                $('.demanded-stock-input').each(function() {
                    const row = $(this).closest('tr');
                    products.push({
                        product_id: row.data('product-id'),
                        quantity: parseInt($(this).val(), 10)
                    });
                });

                const $submitBtn = $('#submit-transfer');
                const originalText = $submitBtn.val();
                $submitBtn.prop('disabled', true).val('<?php esc_attr_e('Creando...', 'gestion-almacenes'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_create_transfer',
                        source_warehouse: sourceWarehouse,
                        target_warehouse: targetWarehouse,
                        products: products,
                        notes: $('#notes').val(),
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.href = '<?php echo admin_url('admin.php?page=gab-transfer-list'); ?>';
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Error al crear la transferencia', 'gestion-almacenes'); ?>');
                            $submitBtn.prop('disabled', false).val(originalText);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Error de conexión', 'gestion-almacenes'); ?>');
                        $submitBtn.prop('disabled', false).val(originalText);
                    }
                });
            });
            
            // Inicializar estado del botón
            updateSubmitButton();
        });
        </script>
        
        <?php
        echo '</div>';
    }

    /**
     * Mostrar lista de transferencias
     */
    public function mostrar_lista_transferencias() {
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Lista de Transferencias', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Gestiona todas las transferencias de stock entre almacenes.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        $transfers = $this->transfer_controller->get_transfers();

        echo '<div class="gab-form-section">';
        echo '<div class="gab-form-row">';
        echo '<div class="gab-form-group">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=gab-new-transfer')) . '" class="button button-primary">';
        echo '<span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>';
        echo esc_html__('Nueva Transferencia', 'gestion-almacenes');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        if ($transfers && count($transfers) > 0) {
            echo '<table class="gab-table wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="width: 80px;">' . esc_html__('ID', 'gestion-almacenes') . '</th>';
            echo '<th>' . esc_html__('Almacén Origen', 'gestion-almacenes') . '</th>';
            echo '<th>' . esc_html__('Almacén Destino', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 100px;">' . esc_html__('Estado', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Creado por', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Fecha', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Acciones', 'gestion-almacenes') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($transfers as $transfer) {
                echo '<tr>';
                echo '<td><strong>#' . str_pad($transfer->id, 4, '0', STR_PAD_LEFT) . '</strong></td>';
                echo '<td>' . esc_html($transfer->source_warehouse_name ?: 'Almacén eliminado') . '</td>';
                echo '<td>' . esc_html($transfer->target_warehouse_name ?: 'Almacén eliminado') . '</td>';
                echo '<td>';
                $status_class = 'gab-badge ';
                switch ($transfer->status) {
                    case 'draft':
                        $status_class .= 'stock-medium';
                        $status_text = __('Borrador', 'gestion-almacenes');
                        break;
                    case 'completed':
                        $status_class .= 'stock-high';
                        $status_text = __('Completada', 'gestion-almacenes');
                        break;
                    case 'cancelled':
                        $status_class .= 'stock-out';
                        $status_text = __('Cancelada', 'gestion-almacenes');
                        break;
                    default:
                        $status_class .= 'stock-low';
                        $status_text = esc_html($transfer->status);
                }
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
                echo '</td>';
                echo '<td>' . esc_html($transfer->created_by_name ?: 'Usuario eliminado') . '</td>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($transfer->created_at))) . '</td>';
                echo '<td>';
                
                if ($transfer->status === 'draft') {
                    echo '<a href="' . esc_url(admin_url('admin.php?page=gab-edit-transfer&transfer_id=' . $transfer->id)) . '" ';
                    echo 'class="button button-small" title="' . esc_attr__('Editar transferencia', 'gestion-almacenes') . '">';
                    echo '<span class="dashicons dashicons-edit"></span>';
                    echo '</a> ';
                }
                
                echo '<a href="' . esc_url(admin_url('admin.php?page=gab-view-transfer&transfer_id=' . $transfer->id)) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Ver transferencia', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '</a>';
                
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<div class="gab-message info">';
            echo '<h3>' . esc_html__('No hay transferencias', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('No se han creado transferencias aún. Crea tu primera transferencia para comenzar.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-new-transfer')) . '" class="button button-primary">';
            echo esc_html__('Crear Primera Transferencia', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Páginas placeholder para las funcionalidades restantes
     */
    public function mostrar_discrepancias() {
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Gestión de Discrepancias', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Próximamente: Gestión de discrepancias de stock.', 'gestion-almacenes') . '</p>';
        echo '</div>';
        echo '</div>';
    }

    public function mostrar_almacen_mermas() {
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Almacén de Mermas', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Próximamente: Gestión de productos perdidos y dañados.', 'gestion-almacenes') . '</p>';
        echo '</div>';
        echo '</div>';
    }


    // Página para editar transferencias
    public function mostrar_editar_transferencia() {
        global $gestion_almacenes_db;
        
        // Obtener ID de transferencia
        $transfer_id = isset($_GET['transfer_id']) ? intval($_GET['transfer_id']) : 0;
        
        if (!$transfer_id) {
            echo '<div class="wrap gab-admin-page">';
            echo '<div class="gab-message error">';
            echo '<h3>' . esc_html__('Error', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('ID de transferencia no válido.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-transfer-list')) . '" class="button button-primary">';
            echo esc_html__('Volver a Lista de Transferencias', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }
        
        // Obtener transferencia
        $transfer = $this->transfer_controller->get_transfer($transfer_id);
        
        if (!$transfer) {
            echo '<div class="wrap gab-admin-page">';
            echo '<div class="gab-message error">';
            echo '<h3>' . esc_html__('Transferencia no encontrada', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('La transferencia solicitada no existe o ha sido eliminada.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-transfer-list')) . '" class="button button-primary">';
            echo esc_html__('Volver a Lista de Transferencias', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }
        
        // Solo permitir editar transferencias en estado 'draft'
        if ($transfer->status !== 'draft') {
            echo '<div class="wrap gab-admin-page">';
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('No se puede editar', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('Solo se pueden editar transferencias en estado borrador.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-view-transfer&transfer_id=' . $transfer_id)) . '" class="button button-primary">';
            echo esc_html__('Ver Transferencia', 'gestion-almacenes');
            echo '</a>';
            echo ' <a href="' . esc_url(admin_url('admin.php?page=gab-transfer-list')) . '" class="button button-secondary">';
            echo esc_html__('Volver a Lista', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }

        $almacenes = $gestion_almacenes_db->obtener_almacenes();

        ?>
        <div class="wrap gab-admin-page">
            <div class="gab-section-header">
                <h1><?php echo esc_html__('Editar Transferencia', 'gestion-almacenes') . ' #' . str_pad($transfer->id, 4, '0', STR_PAD_LEFT); ?></h1>
                <p><?php esc_html_e('Modifica los datos de la transferencia en estado borrador.', 'gestion-almacenes'); ?></p>
            </div>

            <!-- Información de la transferencia -->
            <div class="gab-form-section" style="background: #f0f6fc; border-left: 4px solid #0073aa;">
                <h3><?php esc_html_e('Información de la Transferencia', 'gestion-almacenes'); ?></h3>
                <div class="gab-form-row">
                    <div class="gab-form-group">
                        <strong><?php esc_html_e('ID:', 'gestion-almacenes'); ?></strong> 
                        #<?php echo str_pad($transfer->id, 4, '0', STR_PAD_LEFT); ?>
                    </div>
                    <div class="gab-form-group">
                        <strong><?php esc_html_e('Estado:', 'gestion-almacenes'); ?></strong> 
                        <span class="gab-badge stock-medium"><?php esc_html_e('Borrador', 'gestion-almacenes'); ?></span>
                    </div>
                    <div class="gab-form-group">
                        <strong><?php esc_html_e('Creado:', 'gestion-almacenes'); ?></strong> 
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transfer->created_at))); ?>
                    </div>
                </div>
            </div>

            <form id="gab-edit-transfer-form" data-transfer-id="<?php echo esc_attr($transfer->id); ?>">
                <?php wp_nonce_field('gab_transfer_nonce', 'nonce'); ?>
                
                <div class="gab-form-section">
                    <h3><?php esc_html_e('Almacenes', 'gestion-almacenes'); ?></h3>
                    
                    <div class="gab-form-row">
                        <div class="gab-form-group">
                            <label for="source_warehouse"><?php esc_html_e('Almacén Origen', 'gestion-almacenes'); ?></label>
                            <select name="source_warehouse" id="source_warehouse" required>
                                <option value=""><?php esc_html_e('Seleccionar almacén origen...', 'gestion-almacenes'); ?></option>
                                <?php foreach ($almacenes as $almacen): ?>
                                    <option value="<?php echo esc_attr($almacen->id); ?>" <?php selected($almacen->id, $transfer->source_warehouse_id); ?>>
                                        <?php echo esc_html($almacen->name . ' - ' . $almacen->ciudad); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="gab-form-group">
                            <label for="target_warehouse"><?php esc_html_e('Almacén Destino', 'gestion-almacenes'); ?></label>
                            <select name="target_warehouse" id="target_warehouse" required>
                                <option value=""><?php esc_html_e('Seleccionar almacén destino...', 'gestion-almacenes'); ?></option>
                                <?php foreach ($almacenes as $almacen): ?>
                                    <option value="<?php echo esc_attr($almacen->id); ?>" <?php selected($almacen->id, $transfer->target_warehouse_id); ?>>
                                        <?php echo esc_html($almacen->name . ' - ' . $almacen->ciudad); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="gab-form-section">
                    <h3><?php esc_html_e('Productos a Transferir', 'gestion-almacenes'); ?></h3>
                    
                    <table class="wp-list-table widefat fixed striped" id="products-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;"><?php esc_html_e('SKU', 'gestion-almacenes'); ?></th>
                                <th><?php esc_html_e('Producto', 'gestion-almacenes'); ?></th>
                                <th style="width: 120px;"><?php esc_html_e('Stock Disponible', 'gestion-almacenes'); ?></th>
                                <th style="width: 120px;"><?php esc_html_e('Cantidad', 'gestion-almacenes'); ?></th>
                                <th style="width: 80px;"><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="products-tbody">
                            <?php if ($transfer->items && count($transfer->items) > 0): ?>
                                <?php foreach ($transfer->items as $index => $item): 
                                    $product = wc_get_product($item->product_id);
                                    if ($product):
                                        $current_stock = $gestion_almacenes_db->get_product_stock_in_warehouse($item->product_id, $transfer->source_warehouse_id);
                                ?>
                                    <tr data-product-id="<?php echo esc_attr($item->product_id); ?>" data-item-id="<?php echo esc_attr($item->id); ?>">
                                        <td><?php echo esc_html($product->get_sku() ?: ''); ?></td>
                                        <td><strong><?php echo esc_html($product->get_name()); ?></strong></td>
                                        <td class="available-stock" style="text-align: center;">
                                            <strong style="color: #0073aa;"><?php echo esc_html($current_stock); ?></strong>
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="number" name="products[<?php echo esc_attr($item->id); ?>][quantity]" 
                                                min="1" max="<?php echo esc_attr($current_stock); ?>" 
                                                value="<?php echo esc_attr($item->requested_qty); ?>" required
                                                class="demanded-stock-input" style="width: 80px; text-align: center;">
                                            <input type="hidden" name="products[<?php echo esc_attr($item->id); ?>][product_id]" 
                                                value="<?php echo esc_attr($item->product_id); ?>">
                                        </td>
                                        <td style="text-align: center;">
                                            <button type="button" class="button button-small remove-product" 
                                                    title="<?php esc_attr_e('Eliminar producto', 'gestion-almacenes'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; ?>
                            <?php endif; ?>
                            
                            <tr id="add-product-row">
                                <td colspan="5" style="text-align: center; padding: 15px; background-color: #f0f6fc;">
                                    <button type="button" id="add-product-btn" class="button button-secondary">
                                        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                                        <?php esc_html_e('Agregar Producto', 'gestion-almacenes'); ?>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="gab-form-section">
                    <h3><?php esc_html_e('Notas', 'gestion-almacenes'); ?></h3>
                    <textarea name="notes" id="notes" rows="4" style="width: 100%;" 
                            placeholder="<?php esc_attr_e('Agregar notas o comentarios sobre esta transferencia...', 'gestion-almacenes'); ?>"><?php echo esc_textarea($transfer->notes); ?></textarea>
                </div>

                <div class="gab-form-row">
                    <div class="gab-form-group">
                        <input type="submit" class="button button-primary gab-button-primary" 
                            value="<?php esc_attr_e('Actualizar Transferencia', 'gestion-almacenes'); ?>" id="submit-transfer">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gab-transfer-list')); ?>" 
                        class="button button-secondary"><?php esc_html_e('Cancelar', 'gestion-almacenes'); ?></a>
                        <button type="button" id="delete-transfer" class="button button-danger" style="margin-left: 20px;">
                            <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Eliminar Transferencia', 'gestion-almacenes'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Modal para búsqueda de productos (igual que en nueva transferencia) -->
            <div id="product-search-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2><?php esc_html_e('Buscar Productos', 'gestion-almacenes'); ?></h2>
                    <input type="text" id="product-search" placeholder="<?php esc_attr_e('Escriba para buscar productos...', 'gestion-almacenes'); ?>">
                    <div id="search-results"></div>
                </div>
            </div>
            
            <!-- Modal de confirmación para eliminar -->
            <div id="delete-confirm-modal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close">&times;</span>
                    <h2 style="color: #d63638;"><?php esc_html_e('Confirmar Eliminación', 'gestion-almacenes'); ?></h2>
                    <p><?php esc_html_e('¿Estás seguro de que deseas eliminar esta transferencia?', 'gestion-almacenes'); ?></p>
                    <p><strong><?php esc_html_e('Esta acción no se puede deshacer.', 'gestion-almacenes'); ?></strong></p>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" id="confirm-delete" class="button button-danger">
                            <?php esc_html_e('Sí, Eliminar', 'gestion-almacenes'); ?>
                        </button>
                        <button type="button" class="button button-secondary close" style="margin-left: 10px;">
                            <?php esc_html_e('Cancelar', 'gestion-almacenes'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* Estilos para editar transferencia */
        .button-danger {
            background: #d63638 !important;
            border-color: #d63638 !important;
            color: white !important;
        }
        
        .button-danger:hover {
            background: #b32d2e !important;
            border-color: #b32d2e !important;
        }
        
        /* Modal styles (reutilizar los mismos de nueva transferencia) */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            position: relative;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 10px;
        }

        .close:hover {
            color: black;
        }

        .modal-content h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
            padding-right: 40px;
        }

        #product-search {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        #search-results {
            max-height: calc(80vh - 150px);
            overflow-y: auto;
            margin-top: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background: white;
        }

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
            font-size: 14px;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }

        .searching, .no-results {
            padding: 10px;
            color: #666;
            font-style: italic;
            text-align: center;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            let productCount = <?php echo count($transfer->items); ?>;
            const MAX_PRODUCTS = 60;
            const transferId = <?php echo $transfer->id; ?>;
            
            // Función para abrir el modal de productos
            function openProductModal() {
                $('#product-search-modal').show();
                $('#product-search').val('').focus();
                $('#search-results').empty();
            }
            
            // Función para cerrar modales
            function closeModal() {
                $('.modal').hide();
                $('#product-search').val('');
                $('#search-results').empty();
            }
            
            // Event handlers para abrir modal de productos
            $(document).on('click', '#add-product-btn', function(e) {
                e.preventDefault();
                const sourceWarehouse = $('#source_warehouse').val();
                if (!sourceWarehouse) {
                    alert('<?php esc_html_e('Primero selecciona el almacén origen', 'gestion-almacenes'); ?>');
                    $('#source_warehouse').focus();
                    return;
                }
                openProductModal();
            });

            // Cerrar modales
            $(document).on('click', '.close', function() {
                closeModal();
            });
            
            $(document).on('click', '.modal', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            $(document).on('click', '.modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Búsqueda de productos (igual que en nueva transferencia)
            let searchTimeout;
            $(document).on('input', '#product-search', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                if (query.length < 2) {
                    $('#search-results').empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    $('#search-results').html('<div class="searching"><?php esc_html_e('Buscando...', 'gestion-almacenes'); ?></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gab_search_products_transfer',
                            term: query,
                            nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#search-results').empty();
                            
                            if (response.success && response.data && response.data.products) {
                                if (response.data.products.length > 0) {
                                    response.data.products.forEach(function(product) {
                                        const isAdded = $(`tr[data-product-id="${product.id}"]`).length > 0;
                                        if (!isAdded) {
                                            $('#search-results').append(`
                                                <div class="search-result-item" data-product-id="${product.id}" data-product-name="${product.name}" data-product-sku="${product.sku || ''}">
                                                    ${product.sku ? `[${product.sku}] ` : ''}${product.name}
                                                </div>
                                            `);
                                        }
                                    });
                                    
                                    if ($('#search-results').children().length === 0) {
                                        $('#search-results').append('<div class="no-results"><?php esc_html_e('Todos los productos coincidentes ya están en la lista.', 'gestion-almacenes'); ?></div>');
                                    }
                                } else {
                                    $('#search-results').append('<div class="no-results"><?php esc_html_e('No se encontraron productos', 'gestion-almacenes'); ?></div>');
                                }
                            }
                        }
                    });
                }, 500);
            });

            // Seleccionar producto del modal
            $(document).on('click', '.search-result-item', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productSku = $(this).data('product-sku');
                const sourceWarehouse = $('#source_warehouse').val();
                
                addProductToTransfer(productId, productName, productSku, sourceWarehouse);
                closeModal();
            });
            
            // Función para agregar producto a la tabla
            function addProductToTransfer(productId, productName, productSku, sourceWarehouse) {
                if (productCount >= MAX_PRODUCTS) {
                    alert('<?php esc_html_e('Número máximo de productos alcanzado', 'gestion-almacenes'); ?>');
                    return;
                }

                if ($(`tr[data-product-id="${productId}"]`).length > 0) {
                    alert('<?php esc_html_e('Este producto ya está en la lista.', 'gestion-almacenes'); ?>');
                    return;
                }

                const row = `
                    <tr data-product-id="${productId}">
                        <td>${productSku || ''}</td>
                        <td><strong>${productName}</strong></td>
                        <td class="available-stock" style="text-align: center;">
                            <span class="loading"><?php esc_html_e('Cargando...', 'gestion-almacenes'); ?></span>
                        </td>
                        <td style="text-align: center;">
                            <input type="number" name="products[new_${productCount}][quantity]" 
                                min="1" value="1" required class="demanded-stock-input" 
                                style="width: 80px; text-align: center;" disabled>
                            <input type="hidden" name="products[new_${productCount}][product_id]" value="${productId}">
                        </td>
                        <td style="text-align: center;">
                            <button type="button" class="button button-small remove-product">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                `;
                
                $('#add-product-row').before(row);
                
                // Obtener stock disponible
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_get_warehouse_stock_transfer',
                        product_id: productId,
                        warehouse_id: sourceWarehouse,
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        const row = $(`tr[data-product-id="${productId}"]`);
                        if (response.success) {
                            const stock = parseInt(response.data.stock);
                            if (stock <= 0) {
                                row.remove();
                                alert('<?php esc_html_e('No hay stock disponible para este producto', 'gestion-almacenes'); ?>');
                            } else {
                                row.find('.available-stock').html(`<strong style="color: #0073aa;">${stock}</strong>`);
                                const input = row.find('.demanded-stock-input');
                                input.prop('disabled', false).attr('max', stock);
                            }
                        }
                    }
                });

                productCount++;
            }

            // Eliminar producto
            $(document).on('click', '.remove-product', function() {
                $(this).closest('tr').remove();
            });

            // Validar almacenes diferentes
            $('#target_warehouse').on('change', function() {
                const sourceWarehouse = $('#source_warehouse').val();
                const targetWarehouse = $(this).val();
                
                if (sourceWarehouse && targetWarehouse && sourceWarehouse === targetWarehouse) {
                    alert('<?php esc_html_e('Los almacenes origen y destino deben ser diferentes', 'gestion-almacenes'); ?>');
                    $(this).val('');
                }
            });

            // Validar cantidades
            $(document).on('change', '.demanded-stock-input', function() {
                const input = $(this);
                const max = parseInt(input.attr('max'), 10);
                const value = parseInt(input.val(), 10);
                
                if (value > max) {
                    alert('<?php esc_html_e('La cantidad no puede exceder el stock disponible', 'gestion-almacenes'); ?>');
                    input.val(max);
                } else if (value < 1) {
                    alert('<?php esc_html_e('La cantidad debe ser al menos 1', 'gestion-almacenes'); ?>');
                    input.val(1);
                }
            });

            // Eliminar transferencia
            $('#delete-transfer').on('click', function() {
                $('#delete-confirm-modal').show();
            });

            $('#confirm-delete').on('click', function() {
                if (confirm('<?php esc_html_e('¿Estás completamente seguro? Esta acción eliminará permanentemente la transferencia.', 'gestion-almacenes'); ?>')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gab_delete_transfer',
                            transfer_id: transferId,
                            nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                window.location.href = '<?php echo admin_url('admin.php?page=gab-transfer-list'); ?>';
                            } else {
                                alert(response.data.message || '<?php esc_html_e('Error al eliminar la transferencia', 'gestion-almacenes'); ?>');
                            }
                        }
                    });
                }
            });

            // Enviar formulario de actualización
            $('#gab-edit-transfer-form').on('submit', function(e) {
                e.preventDefault();
                
                const sourceWarehouse = $('#source_warehouse').val();
                const targetWarehouse = $('#target_warehouse').val();
                
                if (!sourceWarehouse || !targetWarehouse) {
                    alert('<?php esc_html_e('Debes seleccionar ambos almacenes', 'gestion-almacenes'); ?>');
                    return;
                }
                
                if ($('#products-tbody tr[data-product-id]').length === 0) {
                    alert('<?php esc_html_e('Debes tener al menos un producto', 'gestion-almacenes'); ?>');
                    return;
                }

                const $submitBtn = $('#submit-transfer');
                const originalText = $submitBtn.val();
                $submitBtn.prop('disabled', true).val('<?php esc_attr_e('Actualizando...', 'gestion-almacenes'); ?>');

                // Recopilar datos de productos
                const products = {};
                $('.demanded-stock-input').each(function() {
                    const name = $(this).attr('name');
                    const match = name.match(/products\[([^\]]+)\]\[quantity\]/);
                    if (match) {
                        const itemKey = match[1];
                        const row = $(this).closest('tr');
                        products[itemKey] = {
                            product_id: row.data('product-id'),
                            quantity: parseInt($(this).val(), 10)
                        };
                    }
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_update_transfer',
                        transfer_id: transferId,
                        source_warehouse_id: sourceWarehouse,
                        target_warehouse_id: targetWarehouse,
                        notes: $('#notes').val(),
                        items: products,
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        $submitBtn.prop('disabled', false).val(originalText);
                        if (response.success) {
                            alert('<?php esc_html_e('Transferencia actualizada correctamente.', 'gestion-almacenes'); ?>');
                            window.location.href = '<?php echo admin_url('admin.php?page=gab-view-transfer&transfer_id=' . $transfer->id); ?>';
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Hubo un problema al actualizar la transferencia.', 'gestion-almacenes'); ?>');
                        }
                    },
                    error: function() {
                        $submitBtn.prop('disabled', false).val(originalText);
                        alert('<?php esc_html_e('Error inesperado en la solicitud.', 'gestion-almacenes'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }


/**
 * Página de configuración del plugin
*/
    public function mostrar_configuracion() {
        // Variable para mostrar mensaje de éxito
        $saved = false;
        $error_message = '';
        
        // Procesar configuración si se envió el formulario
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_submitted'])) {
            // Verificar nonce
            if (!isset($_POST['settings_nonce']) || !wp_verify_nonce($_POST['settings_nonce'], 'save_settings_nonce')) {
                $error_message = 'Error de seguridad. Por favor, intenta de nuevo.';
            } else {
                // Guardar configuración del sistema
                $default_warehouse = isset($_POST['default_warehouse']) ? intval($_POST['default_warehouse']) : '';
                $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 5;
                $auto_select_warehouse = isset($_POST['auto_select_warehouse']) ? 1 : 0;
                
                update_option('gab_default_warehouse', $default_warehouse);
                update_option('gab_low_stock_threshold', $low_stock_threshold);
                update_option('gab_auto_select_warehouse', $auto_select_warehouse);
                
                // Guardar datos de la empresa
                update_option('gab_company_name', sanitize_text_field($_POST['company_name'] ?? ''));
                update_option('gab_company_rut', sanitize_text_field($_POST['company_rut'] ?? ''));
                update_option('gab_company_address', sanitize_textarea_field($_POST['company_address'] ?? ''));
                update_option('gab_company_phone', sanitize_text_field($_POST['company_phone'] ?? ''));
                update_option('gab_company_email', sanitize_email($_POST['company_email'] ?? ''));
                update_option('gab_manage_wc_stock', isset($_POST['manage_wc_stock']) ? 'yes' : 'no');
                update_option('gab_auto_sync_stock', isset($_POST['auto_sync_stock']) ? 'yes' : 'no');
                update_option('gab_default_sales_warehouse', sanitize_text_field($_POST['default_sales_warehouse'] ?? ''));

                update_option('gab_stock_allocation_method', sanitize_text_field($_POST['stock_allocation_method'] ?? 'priority'));
                update_option('gab_allow_partial_fulfillment', isset($_POST['allow_partial_fulfillment']) ? 'yes' : 'no');
                update_option('gab_notify_low_stock_on_sale', isset($_POST['notify_low_stock_on_sale']) ? 'yes' : 'no');
                update_option('gab_low_stock_email', sanitize_email($_POST['low_stock_email'] ?? ''));
                
                $saved = true;
            }
        }

        // Obtener valores actuales
        $company_name = get_option('gab_company_name', get_bloginfo('name'));
        $company_address = get_option('gab_company_address', '');
        $company_phone = get_option('gab_company_phone', '');
        $company_email = get_option('gab_company_email', get_bloginfo('admin_email'));
        $company_rut = get_option('gab_company_rut', '');
        
        global $gestion_almacenes_db;
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        
        $default_warehouse = get_option('gab_default_warehouse', '');
        $low_stock_threshold = get_option('gab_low_stock_threshold', 5);
        $auto_select_warehouse = get_option('gab_auto_select_warehouse', 0);
        
        // Estadísticas del sistema
        $productos_con_stock = $gestion_almacenes_db->get_all_products_warehouse_stock();
        $productos_unicos = array();
        if ($productos_con_stock) {
            foreach ($productos_con_stock as $item) {
                if (!in_array($item->product_id, $productos_unicos)) {
                    $productos_unicos[] = $item->product_id;
                }
            }
        }
        $productos_stock_bajo = $gestion_almacenes_db->get_low_stock_products($low_stock_threshold);
        ?>
        
        <div class="wrap">
            <h1><?php esc_html_e('Configuración de Gestión de Almacenes', 'gestion-almacenes'); ?></h1>
            
            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Configuración guardada correctamente.', 'gestion-almacenes'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_settings_nonce', 'settings_nonce'); ?>
                <input type="hidden" name="form_submitted" value="1" />
                
                <!-- Información de la Empresa -->
                <h2><?php esc_html_e('Información de la Empresa', 'gestion-almacenes'); ?></h2>
                <p><?php esc_html_e('Esta información aparecerá en los documentos impresos de transferencias.', 'gestion-almacenes'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="company_name"><?php esc_html_e('Nombre de la Empresa', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="company_name" id="company_name" 
                                value="<?php echo esc_attr($company_name); ?>" class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Nombre que aparecerá en los documentos.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="company_rut"><?php esc_html_e('RUT/ID Fiscal', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="company_rut" id="company_rut" 
                                value="<?php echo esc_attr($company_rut); ?>" class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Identificación fiscal de la empresa.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="company_address"><?php esc_html_e('Dirección', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <textarea name="company_address" id="company_address" rows="3" cols="50" 
                                    class="large-text"><?php echo esc_textarea($company_address); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Dirección completa de la empresa.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="company_phone"><?php esc_html_e('Teléfono', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="company_phone" id="company_phone" 
                                value="<?php echo esc_attr($company_phone); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="company_email"><?php esc_html_e('Email', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="company_email" id="company_email" 
                                value="<?php echo esc_attr($company_email); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>

                <!-- Control de Stock de WooCommerce -->
                <h2><?php esc_html_e('Control de Stock', 'gestion-almacenes'); ?></h2>
                <p><?php esc_html_e('Configure cómo el plugin gestiona el stock de WooCommerce.', 'gestion-almacenes'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Gestionar Stock de WooCommerce', 'gestion-almacenes'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="manage_wc_stock" value="1" 
                                        <?php checked(get_option('gab_manage_wc_stock', 'yes'), 'yes'); ?>>
                                    <?php esc_html_e('Permitir que el plugin controle el stock de WooCommerce', 'gestion-almacenes'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Cuando está activado, el stock de WooCommerce será la suma de todos los almacenes y no se podrá editar directamente.', 'gestion-almacenes'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Sincronización Automática', 'gestion-almacenes'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="auto_sync_stock" value="1" 
                                        <?php checked(get_option('gab_auto_sync_stock', 'yes'), 'yes'); ?>>
                                    <?php esc_html_e('Sincronizar automáticamente el stock cuando se realicen cambios', 'gestion-almacenes'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Actualiza el stock de WooCommerce inmediatamente cuando se modifique el stock en cualquier almacén.', 'gestion-almacenes'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Almacén Predeterminado para Ventas', 'gestion-almacenes'); ?></th>
                        <td>
                            <select name="default_sales_warehouse" id="default_sales_warehouse">
                                <option value=""><?php esc_html_e('Automático (menor stock primero)', 'gestion-almacenes'); ?></option>
                                <?php 
                                $default_sales_warehouse = get_option('gab_default_sales_warehouse', '');
                                if ($almacenes): 
                                    foreach ($almacenes as $almacen): ?>
                                        <option value="<?php echo esc_attr($almacen->id); ?>" 
                                                <?php selected($default_sales_warehouse, $almacen->id); ?>>
                                            <?php echo esc_html($almacen->name); ?>
                                        </option>
                                    <?php endforeach;
                                endif; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('De qué almacén se descontará el stock cuando se realice una venta.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Gestión de Ventas -->
                <h2><?php esc_html_e('Gestión de Ventas', 'gestion-almacenes'); ?></h2>
                <p><?php esc_html_e('Configure cómo se maneja el stock cuando se realizan ventas.', 'gestion-almacenes'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Método de Asignación de Stock', 'gestion-almacenes'); ?></th>
                        <td>
                            <?php $allocation_method = get_option('gab_stock_allocation_method', 'priority'); ?>
                            <select name="stock_allocation_method" id="stock_allocation_method">
                                <option value="priority" <?php selected($allocation_method, 'priority'); ?>>
                                    <?php esc_html_e('Por prioridad (almacén predeterminado primero)', 'gestion-almacenes'); ?>
                                </option>
                                <option value="balanced" <?php selected($allocation_method, 'balanced'); ?>>
                                    <?php esc_html_e('Balanceado (distribuir entre almacenes)', 'gestion-almacenes'); ?>
                                </option>
                                <option value="nearest" <?php selected($allocation_method, 'nearest'); ?>>
                                    <?php esc_html_e('Más cercano (requiere configuración de zonas)', 'gestion-almacenes'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Cómo se selecciona de qué almacén tomar el stock cuando se realiza una venta.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Permitir Ventas Parciales', 'gestion-almacenes'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="allow_partial_fulfillment" value="1" 
                                        <?php checked(get_option('gab_allow_partial_fulfillment', 'no'), 'yes'); ?>>
                                    <?php esc_html_e('Permitir completar pedidos tomando stock de múltiples almacenes', 'gestion-almacenes'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Si está activado, se puede tomar stock de varios almacenes para completar un pedido.', 'gestion-almacenes'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Notificar Stock Bajo', 'gestion-almacenes'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="notify_low_stock_on_sale" value="1" 
                                        <?php checked(get_option('gab_notify_low_stock_on_sale', 'yes'), 'yes'); ?>>
                                    <?php esc_html_e('Enviar notificación cuando el stock de un almacén quede bajo después de una venta', 'gestion-almacenes'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Email para Notificaciones', 'gestion-almacenes'); ?></th>
                        <td>
                            <input type="email" name="low_stock_email" id="low_stock_email" 
                                value="<?php echo esc_attr(get_option('gab_low_stock_email', get_option('admin_email'))); ?>" 
                                class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Email donde se enviarán las notificaciones de stock bajo.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Configuración del Sistema -->
                <h2><?php esc_html_e('Configuración del Sistema', 'gestion-almacenes'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_warehouse"><?php esc_html_e('Almacén por Defecto', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <select name="default_warehouse" id="default_warehouse">
                                <option value=""><?php esc_html_e('Ninguno', 'gestion-almacenes'); ?></option>
                                <?php if ($almacenes): ?>
                                    <?php foreach ($almacenes as $almacen): ?>
                                        <option value="<?php echo esc_attr($almacen->id); ?>" 
                                                <?php selected($default_warehouse, $almacen->id); ?>>
                                            <?php echo esc_html($almacen->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Almacén que se seleccionará automáticamente en los productos.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="low_stock_threshold"><?php esc_html_e('Umbral de Stock Bajo', 'gestion-almacenes'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="low_stock_threshold" id="low_stock_threshold" 
                                value="<?php echo esc_attr($low_stock_threshold); ?>" min="1" class="small-text">
                            <p class="description">
                                <?php esc_html_e('Cantidad por debajo de la cual se considera stock bajo.', 'gestion-almacenes'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Selección Automática', 'gestion-almacenes'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="auto_select_warehouse" value="1" 
                                        <?php checked($auto_select_warehouse, 1); ?>>
                                    <?php esc_html_e('Seleccionar automáticamente el almacén con más stock disponible', 'gestion-almacenes'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Si está activado, se seleccionará automáticamente el almacén con mayor stock disponible para el producto.', 'gestion-almacenes'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_settings" class="button-primary" 
                        value="<?php esc_attr_e('Guardar Configuración', 'gestion-almacenes'); ?>">
                </p>
            </form>
            
            <!-- Información del Sistema -->
            <h2><?php esc_html_e('Información del Sistema', 'gestion-almacenes'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Configuración', 'gestion-almacenes'); ?></th>
                        <th><?php esc_html_e('Valor', 'gestion-almacenes'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php esc_html_e('Total de Almacenes', 'gestion-almacenes'); ?></td>
                        <td><?php echo is_array($almacenes) ? count($almacenes) : 0; ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Productos con Stock Asignado', 'gestion-almacenes'); ?></td>
                        <td><?php echo count($productos_unicos); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Productos con Stock Bajo', 'gestion-almacenes'); ?></td>
                        <td><?php echo is_array($productos_stock_bajo) ? count($productos_stock_bajo) : 0; ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Versión del Plugin', 'gestion-almacenes'); ?></td>
                        <td>1.0.0</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Validar stock del almacén al agregar al carrito
     */
    public function validar_stock_almacen($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        if (!$passed) {
            return $passed;
        }
        
        // Usar variation_id si existe, sino usar product_id
        $actual_product_id = $variation_id ? $variation_id : $product_id;
        
        // Obtener el almacén predeterminado o el seleccionado
        $warehouse_id = $this->get_selected_warehouse(); // Implementar esta función según tu lógica
        
        if (!$warehouse_id) {
            // Si no hay almacén seleccionado, usar el primero disponible
            $warehouse_id = $this->get_default_warehouse_id();
        }
        
        // Verificar stock en el almacén
        $stock_disponible = $this->get_warehouse_stock($actual_product_id, $warehouse_id);
        
        // Verificar cantidad en el carrito actual
        $cart_qty = $this->get_product_quantity_in_cart($actual_product_id);
        $total_qty = $cart_qty + $quantity;
        
        if ($stock_disponible < $total_qty) {
            $product = wc_get_product($actual_product_id);
            wc_add_notice(sprintf(
                __('No hay suficientes unidades disponibles de "%s". Stock disponible: %d unidades.', 'gestion-almacenes'),
                $product->get_name(),
                $stock_disponible
            ), 'error');
            return false;
        }
        
        return $passed;
    }

    /**
     * Validar stock al actualizar carrito
     */
    public function validar_stock_almacen_actualizar($passed, $cart_item_key, $values, $quantity) {
        if (!$passed) {
            return $passed;
        }
        
        $product_id = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];
        $warehouse_id = $this->get_selected_warehouse();
        
        if (!$warehouse_id) {
            $warehouse_id = $this->get_default_warehouse_id();
        }
        
        $stock_disponible = $this->get_warehouse_stock($product_id, $warehouse_id);
        
        if ($stock_disponible < $quantity) {
            $product = wc_get_product($product_id);
            wc_add_notice(sprintf(
                __('No hay suficientes unidades disponibles de "%s". Stock disponible: %d unidades.', 'gestion-almacenes'),
                $product->get_name(),
                $stock_disponible
            ), 'error');
            return false;
        }
        
        return $passed;
    }

    /**
     * Verificar stock durante el checkout
     */
    public function verificar_stock_checkout() {
        $warehouse_id = $this->get_selected_warehouse();
        
        if (!$warehouse_id) {
            $warehouse_id = $this->get_default_warehouse_id();
        }
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            $stock_disponible = $this->get_warehouse_stock($product_id, $warehouse_id);
            
            if ($stock_disponible < $quantity) {
                $product = $cart_item['data'];
                wc_add_notice(sprintf(
                    __('No hay suficientes unidades disponibles de "%s" en el almacén seleccionado. Stock disponible: %d unidades.', 'gestion-almacenes'),
                    $product->get_name(),
                    $stock_disponible
                ), 'error');
            }
        }
    }

    /**
     * Reducir stock del almacén cuando se completa el pedido
     */
    public function reducir_stock_almacen($order) {
        // Verificar si ya se procesó para evitar duplicados
        $already_reduced = $order->get_meta('_gab_stock_reduced');
        if ($already_reduced) {
            return;
        }
        
        $warehouse_id = $this->get_selected_warehouse();
        if (!$warehouse_id) {
            $warehouse_id = $this->get_default_warehouse_id();
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
            $quantity = $item->get_quantity();
            
            // Reducir stock del almacén
            $wpdb->query($wpdb->prepare(
                "UPDATE $table 
                SET stock = stock - %d 
                WHERE product_id = %d 
                AND warehouse_id = %d 
                AND stock >= %d",
                $quantity,
                $product_id,
                $warehouse_id,
                $quantity
            ));
            
            // Registrar en el log o historial
            $this->log_stock_movement($product_id, $warehouse_id, -$quantity, 'order', $order->get_id());
        }
        
        // Marcar como procesado
        $order->update_meta_data('_gab_stock_reduced', 'yes');
        $order->update_meta_data('_gab_warehouse_id', $warehouse_id);
        $order->save();
    }

    /**
     * Funciones auxiliares
     */

    /**
     * Obtener stock de un producto en un almacén
     */
    private function get_warehouse_stock($product_id, $warehouse_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        $stock = $wpdb->get_var($wpdb->prepare(
            "SELECT stock FROM $table WHERE product_id = %d AND warehouse_id = %d",
            $product_id,
            $warehouse_id
        ));
        
        return $stock !== null ? intval($stock) : 0;
    }

    /**
     * Obtener cantidad de producto en el carrito
     */
    private function get_product_quantity_in_cart($product_id) {
        $quantity = 0;
        
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $item_product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
                if ($item_product_id == $product_id) {
                    $quantity += $cart_item['quantity'];
                }
            }
        }
        
        return $quantity;
    }

    /**
     * Obtener almacén seleccionado (implementar según tu lógica)
     */
    private function get_selected_warehouse() {
        // Opción 1: Desde la sesión
        if (WC()->session) {
            $warehouse_id = WC()->session->get('selected_warehouse');
            if ($warehouse_id) {
                return intval($warehouse_id);
            }
        }
        
        // Opción 2: Desde una cookie
        if (isset($_COOKIE['gab_selected_warehouse'])) {
            return intval($_COOKIE['gab_selected_warehouse']);
        }
        
        // Opción 3: Basado en la ubicación del cliente
        // Implementar lógica según zona de envío
        
        return null;
    }

    /**
     * Obtener ID del almacén predeterminado
     */
    private function get_default_warehouse_id() {
        // Opción 1: Desde configuración
        $default_id = get_option('gab_default_warehouse_id');
        if ($default_id) {
            return intval($default_id);
        }
        
        // Opción 2: El primer almacén activo
        global $wpdb;
        $warehouse_id = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}gab_warehouses 
            WHERE status = 'active' 
            ORDER BY id ASC 
            LIMIT 1"
        );
        
        return $warehouse_id ? intval($warehouse_id) : null;
    }

    /**
     * Registrar movimiento de stock
     */
    private function log_stock_movement($product_id, $warehouse_id, $quantity, $type, $reference_id = null) {
        // Implementar registro en tabla de logs si existe
        do_action('gab_stock_movement', array(
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'quantity' => $quantity,
            'type' => $type,
            'reference_id' => $reference_id,
            'date' => current_time('mysql')
        ));
    }


}
