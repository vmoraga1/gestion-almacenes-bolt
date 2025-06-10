<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_Admin {

    private $transfer_controller;

    public function __construct() {
        add_action('admin_menu', array($this, 'registrar_menu_almacenes'));
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
        add_action('wp_ajax_gab_search_products_for_select', array($this, 'ajax_search_products_for_select'));
        add_action('wp_ajax_gab_export_movements', array($this, 'ajax_export_movements'));
        add_action('wp_ajax_gab_test_ajax', array($this, 'test_ajax'));
        
    }

    public function registrar_menu_almacenes() {
        // Menú Principal de Almacenes
        add_menu_page(
            __('Almacenes', 'gestion-almacenes'),
            __('Almacenes', 'gestion-almacenes'),
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

        /*// Gestión de Discrepancias
        add_submenu_page(
            'gab-warehouse-management',
            __('Gestión de Discrepancias', 'gestion-almacenes'),
            __('Discrepancias', 'gestion-almacenes'),
            'manage_options',
            'gab-discrepancies',
            array($this, 'mostrar_discrepancias')
        );*/

        /*// Almacén de Mermas
        add_submenu_page(
            'gab-warehouse-management',
            __('Almacén de Mermas', 'gestion-almacenes'),
            __('Mermas', 'gestion-almacenes'),
            'manage_options',
            'gab-waste-store',
            array($this, 'mostrar_almacen_mermas')
        );*/

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

        // Historial de Movimientos
        add_submenu_page(
            'gab-warehouse-management',
            __('Historial de Movimientos', 'gestion-almacenes'),
            __('Historial de Movimientos', 'gestion-almacenes'),
            'manage_options',
            'gab-movements-history',
            array($this, 'mostrar_historial_movimientos')
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

        // Submenú Herramientas
        add_submenu_page(
            'gab-warehouse-management',
            __('Herramientas', 'gestion-almacenes'),
            __('Herramientas', 'gestion-almacenes'),
            'manage_options',
            'gab-tools',
            array($this, 'mostrar_herramientas')
        );

        // Submenú Gestor de Versiones:
        add_submenu_page(
            'gab-warehouse-management',
            __('Gestor de Versiones', 'gestion-almacenes'),
            __('Versiones', 'gestion-almacenes'),
            'manage_options',
            'gab-version-manager',
            array($this, 'mostrar_gestor_versiones')
        );

    }

    public function enqueue_admin_scripts($hook) {
        
        // Páginas donde cargar los scripts del plugin
        $plugin_pages = array(
            'toplevel_page_gab-warehouse-management',
            'almacenes_page_gab-settings',
            'almacenes_page_gab-stock-report',
            'almacenes_page_gab-mermas',
            'almacenes_page_gab-transfers',
            'almacenes_page_gab-create-transfer',
            'almacenes_page_gab-view-transfer',
            'almacenes_page_gab-movements-history',
            // Agregar versiones en inglés
            'warehouses_page_gab-settings',
            'warehouses_page_gab-stock-report',
            'warehouses_page_gab-mermas',
            'warehouses_page_gab-transfers',
            'warehouses_page_gab-create-transfer',
            'warehouses_page_gab-view-transfer',
            'warehouses_page_gab-movements-history'
        );
        
        if (!in_array($hook, $plugin_pages)) {
            return;
        }
        
        /* // Verificar si el archivo CSS existe
        $css_path = GESTION_ALMACENES_PLUGIN_DIR . 'admin/assets/css/gestion-almacenes-admin.css';
        if (!file_exists($css_path)) {
            error_log('ADVERTENCIA: El archivo CSS no existe en: ' . $css_path);
        } else {
            error_log('Archivo CSS encontrado en: ' . $css_path);
        }*/
        
        // Estilos generales del plugin
        wp_enqueue_style(
            'gab-admin-style',
            GESTION_ALMACENES_PLUGIN_URL . 'admin/assets/css/gestion-almacenes-admin.css',
            array(),
            GESTION_ALMACENES_VERSION
        );
        
        /* // Verificar si se encoló correctamente
        if (wp_style_is('gab-admin-style', 'enqueued')) {
            error_log('CSS encolado correctamente');
        } else {
            error_log('ERROR: CSS no se encoló');
        } */
        
        // Scripts generales
        wp_enqueue_script(
            'gab-admin-script',
            GESTION_ALMACENES_PLUGIN_URL . 'admin/assets/js/gestion-almacenes-admin.js',
            array('jquery'),
            GESTION_ALMACENES_VERSION,
            true
        );
        
        // Localización
        wp_localize_script('gab-admin-script', 'gestionAlmacenesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('get_warehouse_stock_nonce')
        ));
        
        // Para páginas que necesitan Select2
        if (in_array($hook, ['almacenes_page_gab-movements-history', 'almacenes_page_gab-create-transfer'])) {
            // Cargar Select2 directamente desde CDN si WooCommerce no está disponible
            if (!wp_script_is('select2', 'registered')) {
                wp_enqueue_script(
                    'select2',
                    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                    array('jquery'),
                    '4.1.0',
                    true
                );
                
                wp_enqueue_style(
                    'select2',
                    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                    array(),
                    '4.1.0'
                );
            } else {
                // Si está registrado, usar el de WooCommerce
                wp_enqueue_script('select2');
                wp_enqueue_style('select2');
            }
            
            // También cargar los enhanced selects de WooCommerce si están disponibles
            if (function_exists('WC')) {
                wp_enqueue_script('wc-enhanced-select');
                wp_enqueue_style('woocommerce_admin_styles');
            }
        }
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
            global $gestion_almacenes_db, $gestion_almacenes_movements;

            // Obtener stock anterior
            $old_stock = $gestion_almacenes_db->get_warehouse_stock($warehouse_id, $product_id);
            
            // Actualizar stock del almacén
            $result = $gestion_almacenes_db->set_warehouse_stock($warehouse_id, $product_id, $new_stock);
            
            if (!$result) {
                throw new Exception(__('No se pudo actualizar el stock en la base de datos', 'gestion-almacenes'));
            }

            // Registrar movimiento
            $quantity_change = $new_stock - $old_stock;
            if ($quantity_change != 0) {
                $gestion_almacenes_movements->log_movement(array(
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'type' => 'adjustment',
                    'quantity' => $quantity_change,
                    'notes' => sprintf(
                        __('Ajuste manual de stock: %d → %d', 'gestion-almacenes'),
                        $old_stock,
                        $new_stock
                    )
                ));
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
            'high' => array(
                'label' => __('Stock alto', 'gestion-almacenes'),
                'color' => '#8fd694'
            ),
            'medium' => array(
                'label' => __('Stock medio', 'gestion-almacenes'),
                'color' => '#ffe082'
            ),
            'low' => array(
                'label' => __('Stock bajo', 'gestion-almacenes'),
                'color' => '#ff9e6b'
            ),
            'out' => array(
                'label' => __('Sin stock / Agotados', 'gestion-almacenes'),
                'color' => '#f28b82'
            ),
            'unassigned' => array(
                'label' => __('Sin asignar', 'gestion-almacenes'),
                'color' => '#90b7f3'
            )
        );

        foreach ($estados as $key => $estado_info) {
            $checked = in_array($key, $status_filters) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 8px; cursor: pointer;">';
            echo '<input type="checkbox" name="status_filter[]" value="' . esc_attr($key) . '" ' . $checked . ' style="margin-right: 8px;">';
            echo '<span style="display: inline-block; padding: 3px 8px; background-color: ' . esc_attr($estado_info['color']) . '; ';
            echo 'color: white; border-radius: 3px; font-size: 12px; font-weight: 600;">';
            echo esc_html($estado_info['label']);
            echo '</span>';
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
                // Definir los labels de estados aquí también
                $estados_labels = array(
                    'high' => __('Stock alto', 'gestion-almacenes'),
                    'medium' => __('Stock medio', 'gestion-almacenes'),
                    'low' => __('Stock bajo', 'gestion-almacenes'),
                    'out' => __('Sin stock', 'gestion-almacenes'),
                    'unassigned' => __('Sin asignar', 'gestion-almacenes')
                );
                
                $nombres_estados = array();
                foreach ($status_filters as $status) {
                    if (isset($estados_labels[$status])) {
                        $nombres_estados[] = $estados_labels[$status];
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
                        $stock = isset($producto_data['almacenes'][$almacen->id]) ? $producto_data['almacenes'][$almacen->id] : null;
                        $total_stock_filtrado += ($stock !== null ? $stock : 0);
                        
                        echo '<td style="text-align: center;" class="stock-cell" data-warehouse-id="' . esc_attr($almacen->id) . '">';
                        
                        if ($stock === null) {
                            // No tiene registro en este almacén
                            echo '<span style="color: #999; font-style: italic;">-</span>';
                        } else if ($stock == 0) {
                            // AGOTADO - mostrar claramente
                            echo '<span class="gab-badge stock-out" style="font-size: 11px;">';
                            echo '<span class="dashicons dashicons-warning" style="font-size: 12px; width: 12px; height: 12px; vertical-align: middle;"></span> ';
                            echo esc_html__('Agotado', 'gestion-almacenes');
                            echo '</span>';
                        } else {
                            // Tiene stock - mostrar con color según cantidad
                            $status_class = $this->obtener_clase_estado_stock($stock, $threshold);
                            echo '<span class="' . esc_attr($status_class) . '">' . esc_html($stock) . '</span>';
                        }
                        echo '</td>';
                    }
                }

                // Total (solo de almacenes mostrados)
                echo '<td style="text-align: center;">';
                echo '<strong>' . esc_html($total_stock_filtrado) . '</strong>';
                echo '</td>';

                // Estado general
                echo '<td style="text-align: center;" class="status-cell">';
                if ($producto_data['sin_asignar']) {
                    echo '<span class="gab-badge stock-unassigned">' . esc_html__('Sin asignar', 'gestion-almacenes') . '</span>';
                } else if ($producto_data['es_agotado_total']) {
                    // Completamente agotado
                    echo '<span class="gab-badge stock-out">';
                    echo '<span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span> ';
                    echo esc_html__('Agotado Total', 'gestion-almacenes');
                    echo '</span>';
                } else if ($producto_data['tiene_algun_agotamiento']) {
                    // Parcialmente agotado
                    echo '<span class="gab-badge stock-low" style="white-space: normal; display: inline-block;">';
                    echo '<span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: middle;" title="Agotado"></span> ';
                    echo sprintf(
                        esc_html__('En %d', 'gestion-almacenes'),
                        $producto_data['almacenes_agotados']
                    );
                    echo '</span>';
                } else {
                    // Con stock - usar lógica existente
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

                /* // Botón para Ver Historial de Movimientos del producto
                echo '<a href="' . esc_url(add_query_arg(array(
                    'page' => 'gab-movements-history',
                    'product_id' => $product_id
                ), admin_url('admin.php'))) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Ver historial', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-backup"></span>';
                echo '</a> ';*/
                
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
     * Mostrar página de historial de movimientos
     */
    public function mostrar_historial_movimientos() {
        global $gestion_almacenes_db, $gestion_almacenes_movements;
        
        // Obtener filtros
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
        $movement_type = isset($_GET['movement_type']) ? sanitize_text_field($_GET['movement_type']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        
        // Obtener almacenes para el filtro
        $almacenes = $gestion_almacenes_db->get_warehouses();
        
        // Obtener movimientos
        $movements = $this->get_filtered_movements($product_id, $warehouse_id, $movement_type, $date_from, $date_to, $page_num, $per_page);
        $total_movements = $this->count_filtered_movements($product_id, $warehouse_id, $movement_type, $date_from, $date_to);
        
        // Obtener estadísticas
        $stats = $gestion_almacenes_movements->get_movement_stats($product_id, $warehouse_id, $date_from, $date_to);
        
        ?>
        <div class="wrap gab-admin-page">
            <div class="gab-section-header">
                <h1><?php esc_html_e('Historial de Movimientos de Stock', 'gestion-almacenes'); ?></h1>
                <p><?php esc_html_e('Visualiza todos los movimientos de entrada y salida de stock en los almacenes.', 'gestion-almacenes'); ?></p>
            </div>
            
            <!-- Estadísticas Rápidas -->
            <div class="gab-form-section" style="margin-bottom: 20px;">
                <h3><?php esc_html_e('Resumen General', 'gestion-almacenes'); ?></h3>
                <div class="gab-movement-types-grid">
                    <?php
                    // Calcular totales de entradas y salidas
                    $total_entradas = 0;
                    $total_salidas = 0;
                    $movimientos_totales = 0;
                    
                    // Para manejar correctamente los ajustes, necesitamos una consulta adicional
                    global $wpdb;
                    $table = $wpdb->prefix . 'gab_stock_movements';
                    
                    // Construir WHERE clause para los filtros actuales
                    $where_conditions = ["1=1"];
                    $where_params = [];
                    
                    if ($product_id) {
                        $where_conditions[] = "product_id = %d";
                        $where_params[] = $product_id;
                    }
                    if ($warehouse_id) {
                        $where_conditions[] = "warehouse_id = %d";
                        $where_params[] = $warehouse_id;
                    }
                    if ($date_from) {
                        $where_conditions[] = "created_at >= %s";
                        $where_params[] = $date_from . ' 00:00:00';
                    }
                    if ($date_to) {
                        $where_conditions[] = "created_at <= %s";
                        $where_params[] = $date_to . ' 23:59:59';
                    }
                    
                    $where_clause = implode(' AND ', $where_conditions);
                    
                    // Consulta para obtener ajustes positivos y negativos por separado
                    $adjustment_query = "SELECT 
                        SUM(CASE WHEN movement_type = 'adjustment' AND quantity > 0 THEN quantity ELSE 0 END) as adjustment_positive,
                        SUM(CASE WHEN movement_type = 'adjustment' AND quantity < 0 THEN ABS(quantity) ELSE 0 END) as adjustment_negative
                        FROM $table 
                        WHERE $where_clause";
                    
                    if (!empty($where_params)) {
                        $adjustment_query = $wpdb->prepare($adjustment_query, $where_params);
                    }
                    
                    $adjustment_data = $wpdb->get_row($adjustment_query);
                    
                    // Procesar las estadísticas normales
                    foreach ($stats as $tipo => $data) {
                        $movimientos_totales += $data->total_movements;
                        
                        // Para ajustes, usar los datos separados
                        if ($tipo === 'adjustment') {
                            $total_entradas += intval($adjustment_data->adjustment_positive);
                            $total_salidas += intval($adjustment_data->adjustment_negative);
                        } 
                        // Tipos que son siempre entradas
                        elseif (in_array($tipo, ['initial', 'transfer_in', 'return'])) {
                            $total_entradas += abs($data->total_quantity);
                        } 
                        // Tipos que son siempre salidas
                        else {
                            $total_salidas += abs($data->total_quantity);
                        }
                    }
                    
                    // Ajustes manuales pueden ser tanto entradas como salidas
                    if (isset($stats['adjustment'])) {
                        // Recalcular basándose en los movimientos reales
                        // Esto requeriría análisis más detallado de los datos
                    }
                    ?>
                    
                    <!-- Total Movimientos -->
                    <div class="movement-type-card">
                        <div class="movement-type-icon" style="background-color: #0073aa;">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </div>
                        <div class="movement-type-info">
                            <h4><?php esc_html_e('Total Movimientos', 'gestion-almacenes'); ?></h4>
                            <p>
                                <strong style="font-size: 24px;"><?php echo esc_html($movimientos_totales); ?></strong>
                            </p>
                            <p style="color: #666;">
                                <?php esc_html_e('registros totales', 'gestion-almacenes'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Total Entradas -->
                    <div class="movement-type-card">
                        <div class="movement-type-icon" style="background-color: #00a32a;">
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                        </div>
                        <div class="movement-type-info">
                            <h4><?php esc_html_e('Total Entradas', 'gestion-almacenes'); ?></h4>
                            <p>
                                <strong style="font-size: 24px; color: #00a32a;">+<?php echo esc_html($total_entradas); ?></strong>
                            </p>
                            <p style="color: #666;">
                                <?php esc_html_e('unidades ingresadas', 'gestion-almacenes'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Total Salidas -->
                    <div class="movement-type-card">
                        <div class="movement-type-icon" style="background-color: #d63638;">
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                        </div>
                        <div class="movement-type-info">
                            <h4><?php esc_html_e('Total Salidas', 'gestion-almacenes'); ?></h4>
                            <p>
                                <strong style="font-size: 24px; color: #d63638;">-<?php echo esc_html($total_salidas); ?></strong>
                            </p>
                            <p style="color: #666;">
                                <?php esc_html_e('unidades retiradas', 'gestion-almacenes'); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Balance Neto -->
                    <div class="movement-type-card">
                        <div class="movement-type-icon" style="background-color: <?php echo ($total_entradas - $total_salidas) >= 0 ? '#00a32a' : '#d63638'; ?>;">
                            <span class="dashicons dashicons-<?php echo ($total_entradas - $total_salidas) >= 0 ? 'plus' : 'minus'; ?>"></span>
                        </div>
                        <div class="movement-type-info">
                            <h4><?php esc_html_e('Balance Neto', 'gestion-almacenes'); ?></h4>
                            <p>
                                <strong style="font-size: 24px; color: <?php echo ($total_entradas - $total_salidas) >= 0 ? '#00a32a' : '#d63638'; ?>;">
                                    <?php 
                                    $balance = $total_entradas - $total_salidas;
                                    echo ($balance >= 0 ? '+' : '') . esc_html($balance); 
                                    ?>
                                </strong>
                            </p>
                            <p style="color: #666;">
                                <?php esc_html_e('diferencia neta', 'gestion-almacenes'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desglose por tipo de movimiento -->
            <div class="gab-form-section" style="margin-bottom: 20px;">
                <h3><?php esc_html_e('Desglose por Tipo de Movimiento', 'gestion-almacenes'); ?></h3>
                <div class="gab-movement-types-grid">
                    <?php
                    $tipos_info = array(
                        //'initial' => array('label' => __('Stock Inicial', 'gestion-almacenes'), 'icon' => 'plus-alt', 'color' => '#2271b1'),
                        'sale' => array('label' => __('Ventas', 'gestion-almacenes'), 'icon' => 'cart', 'color' => '#dc3545'),
                        'adjustment' => array('label' => __('Ajustes', 'gestion-almacenes'), 'icon' => 'update', 'color' => '#f0ad4e'),
                        'transfer_in' => array('label' => __('Entrada Transfer.', 'gestion-almacenes'), 'icon' => 'download', 'color' => '#00a32a'),
                        'transfer_out' => array('label' => __('Salida Transfer.', 'gestion-almacenes'), 'icon' => 'upload', 'color' => '#d63638'),
                        'return' => array('label' => __('Devoluciones', 'gestion-almacenes'), 'icon' => 'undo', 'color' => '#00a32a')
                    );
                    
                    // Mostrar todos los tipos, incluso con valor 0
                    foreach ($tipos_info as $tipo => $info):
                        $movements_count = isset($stats[$tipo]) ? $stats[$tipo]->total_movements : 0;
                        $units_count = isset($stats[$tipo]) ? abs($stats[$tipo]->total_quantity) : 0;
                    ?>
                        <div class="movement-type-card<?php echo $movements_count == 0 ? ' movement-type-empty' : ''; ?>">
                            <div class="movement-type-icon" style="background-color: <?php echo esc_attr($info['color']); ?>;">
                                <span class="dashicons dashicons-<?php echo esc_attr($info['icon']); ?>"></span>
                            </div>
                            <div class="movement-type-info">
                                <h4><?php echo esc_html($info['label']); ?></h4>
                                <p>
                                    <strong><?php echo esc_html($movements_count); ?></strong> 
                                    <?php esc_html_e('movimientos', 'gestion-almacenes'); ?>
                                </p>
                                <p>
                                    <strong><?php echo esc_html($units_count); ?></strong> 
                                    <?php esc_html_e('unidades', 'gestion-almacenes'); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="gab-form-section">
                <h3><?php esc_html_e('Filtros', 'gestion-almacenes'); ?></h3>
                <form method="get" action="" id="gab-movements-filter">
                    <input type="hidden" name="page" value="gab-movements-history">
                    
                    <div class="gab-form-row">
                        <!-- Filtro por producto -->
                        <div class="gab-form-group">
                            <label for="product_id"><?php esc_html_e('Producto', 'gestion-almacenes'); ?></label>
                            <select name="product_id" id="product_id" class="gab-select2" style="width: 100%;">
                                <!-- Select2 manejará las opciones dinámicamente -->
                            </select>
                        </div>
                        
                        <!-- Filtro por almacén -->
                        <div class="gab-form-group">
                            <label for="warehouse_id"><?php esc_html_e('Almacén', 'gestion-almacenes'); ?></label>
                            <select name="warehouse_id" id="warehouse_id">
                                <option value=""><?php esc_html_e('Todos los almacenes', 'gestion-almacenes'); ?></option>
                                <?php foreach ($almacenes as $almacen): ?>
                                    <option value="<?php echo esc_attr($almacen->id); ?>" 
                                        <?php selected($warehouse_id, $almacen->id); ?>>
                                        <?php echo esc_html($almacen->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Filtro por tipo de movimiento -->
                        <div class="gab-form-group">
                            <label for="movement_type"><?php esc_html_e('Tipo de Movimiento', 'gestion-almacenes'); ?></label>
                            <select name="movement_type" id="movement_type">
                                <option value=""><?php esc_html_e('Todos los tipos', 'gestion-almacenes'); ?></option>
                                <option value="initial" <?php selected($movement_type, 'initial'); ?>>
                                    <?php esc_html_e('Stock Inicial', 'gestion-almacenes'); ?>
                                </option>
                                <option value="adjustment" <?php selected($movement_type, 'adjustment'); ?>>
                                    <?php esc_html_e('Ajuste Manual', 'gestion-almacenes'); ?>
                                </option>
                                <option value="transfer_in" <?php selected($movement_type, 'transfer_in'); ?>>
                                    <?php esc_html_e('Entrada por Transferencia', 'gestion-almacenes'); ?>
                                </option>
                                <option value="transfer_out" <?php selected($movement_type, 'transfer_out'); ?>>
                                    <?php esc_html_e('Salida por Transferencia', 'gestion-almacenes'); ?>
                                </option>
                                <option value="sale" <?php selected($movement_type, 'sale'); ?>>
                                    <?php esc_html_e('Venta', 'gestion-almacenes'); ?>
                                </option>
                                <option value="return" <?php selected($movement_type, 'return'); ?>>
                                    <?php esc_html_e('Devolución', 'gestion-almacenes'); ?>
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="gab-form-date">
                        <!-- Filtro por fecha -->
                        <div class="gab-form-group">
                            <label for="date_from"><?php esc_html_e('Desde', 'gestion-almacenes'); ?></label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                        </div>
                        
                        <div class="gab-form-group">
                            <label for="date_to"><?php esc_html_e('Hasta', 'gestion-almacenes'); ?></label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                        </div>
                        
                        
                    </div>
                    <div class="gab-form-group-btn" style="align-self: flex-end;">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-filter" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Aplicar Filtros', 'gestion-almacenes'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gab-movements-history')); ?>" 
                        class="button button-secondary">
                            <?php esc_html_e('Limpiar', 'gestion-almacenes'); ?>
                        </a>
                        <button type="button" class="button" onclick="exportMovements()">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Exportar CSV', 'gestion-almacenes'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Información de filtros activos -->
            <?php if ($product_id || $warehouse_id || $movement_type || $date_from || $date_to): ?>
            <div class="gab-active-filters-section">
                <div class="gab-active-filters-header">
                    <span class="dashicons dashicons-filter"></span>
                    <strong><?php esc_html_e('Filtros activos', 'gestion-almacenes'); ?></strong>
                </div>
                <div class="gab-active-filters-tags">
                    <?php
                    // Producto
                    if ($product_id) {
                        $product = wc_get_product($product_id);
                        if ($product) {
                            ?>
                            <div class="gab-filter-tag">
                                <span class="filter-tag-label"><?php esc_html_e('Producto', 'gestion-almacenes'); ?></span>
                                <span class="filter-tag-value"><?php echo esc_html($product->get_name()); ?></span>
                                <a href="<?php echo esc_url(remove_query_arg('product_id')); ?>" class="filter-tag-remove" title="<?php esc_attr_e('Quitar filtro', 'gestion-almacenes'); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </a>
                            </div>
                            <?php
                        }
                    }
                    
                    // Almacén
                    if ($warehouse_id) {
                        foreach ($almacenes as $almacen) {
                            if ($almacen->id == $warehouse_id) {
                                ?>
                                <div class="gab-filter-tag">
                                    <span class="filter-tag-label"><?php esc_html_e('Almacén', 'gestion-almacenes'); ?></span>
                                    <span class="filter-tag-value"><?php echo esc_html($almacen->name); ?></span>
                                    <a href="<?php echo esc_url(remove_query_arg('warehouse_id')); ?>" class="filter-tag-remove" title="<?php esc_attr_e('Quitar filtro', 'gestion-almacenes'); ?>">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </a>
                                </div>
                                <?php
                                break;
                            }
                        }
                    }
                    
                    // Tipo de movimiento
                    if ($movement_type) {
                        $type_info = $this->get_movement_type_info($movement_type);
                        ?>
                        <div class="gab-filter-tag" style="border-left-color: <?php echo esc_attr($type_info['color']); ?>;">
                            <span class="filter-tag-label"><?php esc_html_e('Tipo', 'gestion-almacenes'); ?></span>
                            <span class="filter-tag-value"><?php echo esc_html($type_info['label']); ?></span>
                            <a href="<?php echo esc_url(remove_query_arg('movement_type')); ?>" class="filter-tag-remove" title="<?php esc_attr_e('Quitar filtro', 'gestion-almacenes'); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                        </div>
                        <?php
                    }
                    
                    // Período
                    if ($date_from || $date_to) {
                        $periodo = '';
                        if ($date_from && $date_to) {
                            $periodo = date_i18n('j M Y', strtotime($date_from)) . ' - ' . date_i18n('j M Y', strtotime($date_to));
                        } elseif ($date_from) {
                            $periodo = sprintf(esc_html__('Desde %s', 'gestion-almacenes'), date_i18n('j M Y', strtotime($date_from)));
                        } else {
                            $periodo = sprintf(esc_html__('Hasta %s', 'gestion-almacenes'), date_i18n('j M Y', strtotime($date_to)));
                        }
                        ?>
                        <div class="gab-filter-tag">
                            <span class="filter-tag-label"><?php esc_html_e('Período', 'gestion-almacenes'); ?></span>
                            <span class="filter-tag-value"><?php echo esc_html($periodo); ?></span>
                            <a href="<?php echo esc_url(remove_query_arg(['date_from', 'date_to'])); ?>" class="filter-tag-remove" title="<?php esc_attr_e('Quitar filtro', 'gestion-almacenes'); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </a>
                        </div>
                        <?php
                    }
                    ?>
                    
                    <!-- Botón limpiar todos -->
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gab-movements-history')); ?>" 
                    class="gab-clear-all-filters">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php esc_html_e('Limpiar todos', 'gestion-almacenes'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tabla de movimientos -->
            <?php if (!empty($movements)): ?>
                <div class="gab-table-container">
                    <table class="gab-table wp-list-table widefat fixed striped" id="movements-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;"><?php esc_html_e('Fecha/Hora', 'gestion-almacenes'); ?></th>
                                <th style="width: 20%;"><?php esc_html_e('Producto', 'gestion-almacenes'); ?></th>
                                <th style="width: 15%;"><?php esc_html_e('Almacén', 'gestion-almacenes'); ?></th>
                                <th style="width: 15%;"><?php esc_html_e('Tipo', 'gestion-almacenes'); ?></th>
                                <th style="width: 5%; text-align: center;"><?php esc_html_e('Cantidad', 'gestion-almacenes'); ?></th>
                                <th style="width: 5%; text-align: center;"><?php esc_html_e('Saldo', 'gestion-almacenes'); ?></th>
                                <th style="width: 20%;"><?php esc_html_e('Detalles', 'gestion-almacenes'); ?></th>
                                <th style="width: 10%;"><?php esc_html_e('Usuario', 'gestion-almacenes'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement): ?>
                                <?php
                                $product = wc_get_product($movement->product_id);
                                $type_info = $this->get_movement_type_info($movement->movement_type);
                                ?>
                                <tr>
                                    <td>
                                        <span style="font-size: 12px;">
                                            <?php echo esc_html(date_i18n(
                                                get_option('date_format') . ' ' . get_option('time_format'), 
                                                strtotime($movement->created_at)
                                            )); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product): ?>
                                            <strong><?php echo esc_html($product->get_name()); ?></strong>
                                            <?php if ($product->get_sku()): ?>
                                                <br><small style="color: #666;">SKU: <?php echo esc_html($product->get_sku()); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em style="color: #999;"><?php esc_html_e('Producto eliminado', 'gestion-almacenes'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($movement->warehouse_name); ?></td>
                                    <td>
                                        <span class="gab-badge" style="background: <?php echo esc_attr($type_info['color']); ?>; color: white;">
                                            <?php echo esc_html($type_info['label']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php
                                        $qty_class = $movement->quantity > 0 ? 'stock-in' : 'stock-out';
                                        $qty_symbol = $movement->quantity > 0 ? '+' : '';
                                        ?>
                                        <strong class="<?php echo esc_attr($qty_class); ?>" style="font-size: 14px;">
                                            <?php echo esc_html($qty_symbol . $movement->quantity); ?>
                                        </strong>
                                    </td>
                                    <td style="text-align: center;">
                                        <strong style="font-size: 14px; color: #0073aa;">
                                            <?php echo esc_html($movement->balance_after); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;">
                                            <?php echo esc_html($movement->notes ?: '-'); ?>
                                            <?php if ($movement->reference_type && $movement->reference_id): ?>
                                                <br>
                                                <small style="color: #0073aa;">
                                                    <?php echo $this->get_reference_link($movement->reference_type, $movement->reference_id); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo esc_html($movement->user_name ?: __('Sistema', 'gestion-almacenes')); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php
                $total_pages = ceil($total_movements / $per_page);
                if ($total_pages > 1):
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $page_num,
                        'total' => $total_pages,
                        'prev_text' => '&laquo; ' . __('Anterior', 'gestion-almacenes'),
                        'next_text' => __('Siguiente', 'gestion-almacenes') . ' &raquo;',
                        'type' => 'plain',
                        'end_size' => 2,
                        'mid_size' => 2
                    );
                    ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(
                                    esc_html__('%d movimientos', 'gestion-almacenes'),
                                    $total_movements
                                ); ?>
                            </span>
                            <?php echo paginate_links($pagination_args); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Resumen -->
                <p class="description" style="margin-top: 10px;">
                    <?php printf(
                        esc_html__('Mostrando %d de %d movimientos totales.', 'gestion-almacenes'), 
                        count($movements), 
                        $total_movements
                    ); ?>
                </p>
                
            <?php else: ?>
                <div class="gab-message info">
                    <h3><?php esc_html_e('No se encontraron movimientos', 'gestion-almacenes'); ?></h3>
                    <p><?php esc_html_e('No hay movimientos que coincidan con los filtros seleccionados.', 'gestion-almacenes'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cargar Select2 directamente -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        
        <!-- Estilos adicionales -->
        <style>

        .wrap.gab-admin-page {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .gab-section-header {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        /* Estilos para movimientos */
        .stock-in { 
            color: #00a32a; 
            font-weight: bold;
        }
        .stock-out { 
            color: #d63638; 
            font-weight: bold;
        }
        
        /* Grid de tipos de movimiento */
        .gab-movement-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .movement-type-card {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 5px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .movement-type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: #0073aa;
        }
        
        /* Cards vacías con estilo más sutil */
        .movement-type-card.movement-type-empty {
            opacity: 0.6;
            background: #f9f9f9;
        }

        .movement-type-card.movement-type-empty:hover {
            transform: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-color: #e0e0e0;
        }

        .movement-type-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .movement-type-icon .dashicons {
            font-size: 24px;
            width: 24px;
            height: 24px;
            color: white;
        }
        
        .movement-type-info h4 {
            margin: 0 0 2px 0;
            font-size: 14px;
            color: #23282d;
            font-weight: 600;
        }

        .movement-type-info p {
            margin: 0 0 4px 0;
            font-size: 11px;
            color: #666;
            line-height: 1.4;
        }

        .movement-type-info p strong {
            color: #23282d;
            font-size: 16px;
        }
        
        /* Select2 personalizado */
        .gab-select2 { 
            min-width: 300px; 
        }

        /* Mejorar paginación */
        .tablenav-pages {
            margin: 20px 0;
            text-align: center;
        }
        
        .tablenav-pages .page-numbers {
            padding: 5px 10px;
            margin: 0 2px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            text-decoration: none;
            border-radius: 3px;
        }
        
        .tablenav-pages .page-numbers:hover {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
        }
        
        .tablenav-pages .page-numbers.current {
            background: #0073aa;
            color: white;
            border-color: #0073aa;
            font-weight: bold;
        }

        /* Estilos para filtros activos */
        .gab-active-filters-section {
            background: #f0f6fc;
            border: 1px solid #c3dcf3;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .gab-active-filters-header {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #0073aa;
            font-size: 14px;
        }

        .gab-active-filters-header .dashicons {
            margin-right: 6px;
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .gab-active-filters-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        /* Tags de filtros */
        .gab-filter-tag {
            display: inline-flex;
            align-items: center;
            background: #fff;
            border: 1px solid #ddd;
            border-left: 3px solid #0073aa;
            border-radius: 4px;
            padding: 0;
            font-size: 13px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            overflow: hidden;
        }

        .gab-filter-tag:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .filter-tag-label {
            background: #f5f5f5;
            padding: 6px 10px;
            color: #666;
            font-weight: 600;
            border-right: 1px solid #ddd;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-tag-value {
            padding: 6px 12px;
            color: #23282d;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .filter-tag-remove {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            color: #666;
            text-decoration: none;
            border-left: 1px solid #eee;
            transition: all 0.2s ease;
        }

        .filter-tag-remove:hover {
            background: #fee;
            color: #d63638;
        }

        .filter-tag-remove .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }

        /* Botón limpiar todos */
        .gab-clear-all-filters {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            background: #dc3545;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .gab-clear-all-filters:hover {
            background: #b32d2e;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220,53,69,0.3);
        }

        .gab-clear-all-filters .dashicons {
            margin-right: 4px;
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        /* Alineación vertical de etiquetas y campos */
        .gab-form-group {
            display: flex;
            flex-direction: column;
            margin-right: 20px; /* Espacio entre columnas si hay varias por fila */
            flex: 1; /* Hace que los filtros se repartan equitativamente */
            min-width: 200px; /* Evita que se encojan demasiado */
        }

        /* Opcional: estilo consistente para labels */
        .gab-form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .gab-form-row {
            display: ruby;
            flex-wrap: wrap;
            gap: 15px; /* Espacio entre grupos de filtros */
            margin-bottom: 15px;
        }

        .gab-form-date {
            display: ruby;
            flex-wrap: wrap;
            gap: 15px; /* Espacio entre grupos de filtros */
            margin-bottom: 15px;
        }

        .gab-form-group-btn {
            display: block;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        /* Animación de entrada */
        @keyframes filterTagSlideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .gab-filter-tag {
            animation: filterTagSlideIn 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .gab-active-filters-section {
                padding: 12px 15px;
            }
            
            .gab-active-filters-tags {
                gap: 8px;
            }
            
            .gab-filter-tag {
                font-size: 12px;
            }
            
            .filter-tag-label {
                padding: 5px 8px;
                font-size: 10px;
            }
            
            .filter-tag-value {
                padding: 5px 10px;
                max-width: 150px;
            }
            
            .filter-tag-remove {
                padding: 5px 6px;
            }
            
            .gab-clear-all-filters {
                padding: 5px 10px;
                font-size: 11px;
            }
        }

        /* Estilos personalizados para Select2 */
        .gab-select2 {
            min-width: 300px;
        }

        .select2-container--default .select2-selection--single {
            height: 36px;
            line-height: 34px;
            border-color: #7e8993;
            border-radius: 4px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 10px;
            padding-right: 30px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 34px;
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #72777c;
        }

        /* Dropdown personalizado */
        .select2-container--default .select2-results__option {
            padding: 8px 12px;
        }

        .gab-select2-dropdown .select2-results__option--highlighted[aria-selected] {
            background-color: #0073aa;
        }

        /* Mensaje de carga */
        .select2-container--default .select2-results__option--loading {
            padding: 10px;
            text-align: center;
        }

        /* Clear button */
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 5px;
            font-size: 16px;
            color: #666;
        }

        .select2-container--default .select2-selection--single .select2-selection__clear:hover {
            color: #d63638;
        }

        /* Focus state */
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }

        /* Personalización adicional de Select2 */
        

        .select2-container--default .select2-results__option strong {
            color: #23282d;
        }

        .select2-container--default .select2-results__option small {
            display: inline-block;
            margin-left: 5px;
        }

        .select2-results__option--highlighted strong {
            color: #fff;
        }        

        /* Spinner de carga personalizado */
        .select2-container--default .select2-results__option.loading-results {
            padding: 10px;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .gab-select2 {
                min-width: 100%;
                width: 100% !important;
            }
        }

        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Verificar si Select2 está disponible después de cargarlo
            if (typeof $.fn.select2 === 'function') {
                initializeProductSelect();
            } else {
                console.error('Select2 no está disponible incluso después de cargarlo');
            }
            
            function initializeProductSelect() {
                $('#product_id').select2({
                    ajax: {
                        url: ajaxurl,
                        type: 'GET',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                action: 'gab_search_products_for_select',
                                term: params.term || '',
                                nonce: '<?php echo wp_create_nonce('gab_search_products'); ?>' // Nonce correcto
                            };
                        },
                        processResults: function (data) {
                            
                            if (data.success && data.data) {
                                // Agregar opción "Todos los productos"
                                var results = [{
                                    id: '',
                                    text: '<?php esc_html_e('Todos los productos', 'gestion-almacenes'); ?>'
                                }];
                                
                                // Agregar productos encontrados
                                $.each(data.data, function(index, item) {
                                    // Decodificar HTML entities
                                    var decodedText = $('<div/>').html(item.text).text();
                                    
                                    results.push({
                                        id: item.id,
                                        text: decodedText,
                                        sku: item.sku,
                                        name: item.name
                                    });
                                });
                                
                                return {
                                    results: results
                                };
                            } else {
                                return {
                                    results: [{
                                        id: '',
                                        text: '<?php esc_html_e('Todos los productos', 'gestion-almacenes'); ?>'
                                    }]
                                };
                            }
                        },
                        cache: true,
                        error: function(xhr, textStatus, errorThrown) {
                            console.error('Error AJAX:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                error: errorThrown
                            });
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: '<?php esc_html_e('Buscar producto por nombre o SKU...', 'gestion-almacenes'); ?>',
                    allowClear: true,
                    width: '100%',
                    // Template para mostrar resultados en el dropdown
                    templateResult: function(data) {
                        if (!data.id) {
                            return data.text;
                        }
                        
                        var $result = $('<span>');
                        
                        // Nombre del producto
                        $result.append($('<strong>').text(data.name || data.text));
                        
                        // SKU si existe
                        if (data.sku) {
                            $result.append(' <small style="color: #666;">(' + data.sku + ')</small>');
                        }
                        
                        return $result;
                    },
                    // Template para mostrar la selección
                    templateSelection: function(data) {
                        if (!data.id) {
                            return data.text;
                        }
                        
                        var text = data.name || data.text;
                        if (data.sku) {
                            text += ' (SKU: ' + data.sku + ')';
                        }
                        
                        return text;
                    },
                    language: {
                        inputTooShort: function(args) {
                            var remainingChars = args.minimum - args.input.length;
                            return 'Por favor ingresa ' + remainingChars + ' o más caracteres';
                        },
                        noResults: function() {
                            return '<?php esc_html_e('No se encontraron productos', 'gestion-almacenes'); ?>';
                        },
                        searching: function() {
                            return '<?php esc_html_e('Buscando...', 'gestion-almacenes'); ?>';
                        },
                        errorLoading: function() {
                            return '<?php esc_html_e('Error al cargar los resultados', 'gestion-almacenes'); ?>';
                        }
                    }
                });
                
                // Si hay un producto preseleccionado
                <?php if ($product_id && $product = wc_get_product($product_id)): ?>
                var preselectedOption = new Option(
                    '<?php echo esc_js($product->get_name() . ($product->get_sku() ? ' (SKU: ' . $product->get_sku() . ')' : '')); ?>',
                    '<?php echo esc_js($product_id); ?>',
                    true,
                    true
                );
                $('#product_id').append(preselectedOption).trigger('change');
                <?php endif; ?>
                
            }
            
            // Validación de fechas
            $('#date_from, #date_to').on('change', function() {
                var from = $('#date_from').val();
                var to = $('#date_to').val();
                
                if (from && to && from > to) {
                    alert('<?php esc_html_e('La fecha "Desde" no puede ser mayor que la fecha "Hasta"', 'gestion-almacenes'); ?>');
                    $(this).val('');
                }
            });
            
            // Función de prueba para verificar AJAX
            window.testAjax = function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'GET',
                    data: {
                        action: 'gab_search_products_for_select',
                        term: 'test',
                        nonce: '<?php echo wp_create_nonce('gab_search_products'); ?>'
                    },
                    success: function(response) {
                        console.log('Prueba AJAX exitosa:', response);
                    },
                    error: function(xhr) {
                        console.error('Error en prueba AJAX:', xhr.responseText);
                    }
                });
            };
        });

        function exportMovements() {
            var params = new URLSearchParams(window.location.search);
            params.set('action', 'gab_export_movements');
            params.set('nonce', '<?php echo wp_create_nonce('gab_export_movements'); ?>');
            
            window.location.href = ajaxurl + '?' + params.toString();
        }
        </script>
        <?php
    }

    /**
     * Método de prueba AJAX
     */
    public function test_ajax() {
        wp_send_json_success(array('message' => 'AJAX funciona correctamente'));
    }

    /**
     * Obtener movimientos filtrados
     */
    private function get_filtered_movements($product_id, $warehouse_id, $movement_type, $date_from, $date_to, $page, $per_page) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_stock_movements';
        $warehouses_table = $wpdb->prefix . 'gab_warehouses';
        
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT 
                    m.*,
                    w.name as warehouse_name,
                    u.display_name as user_name
                FROM $table m
                LEFT JOIN $warehouses_table w ON m.warehouse_id = w.id
                LEFT JOIN {$wpdb->users} u ON m.created_by = u.ID
                WHERE 1=1";
        
        $params = array();
        
        if ($product_id) {
            $query .= " AND m.product_id = %d";
            $params[] = $product_id;
        }
        
        if ($warehouse_id) {
            $query .= " AND m.warehouse_id = %d";
            $params[] = $warehouse_id;
        }
        
        if ($movement_type) {
            $query .= " AND m.movement_type = %s";
            $params[] = $movement_type;
        }
        
        if ($date_from) {
            $query .= " AND m.created_at >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to) {
            $query .= " AND m.created_at <= %s";
            $params[] = $date_to . ' 23:59:59';
        }
        
        $query .= " ORDER BY m.created_at DESC, m.id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * Contar movimientos filtrados
     */
    private function count_filtered_movements($product_id, $warehouse_id, $movement_type, $date_from, $date_to) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_stock_movements';
        
        $query = "SELECT COUNT(*) FROM $table WHERE 1=1";
        $params = array();
        
        if ($product_id) {
            $query .= " AND product_id = %d";
            $params[] = $product_id;
        }
        
        if ($warehouse_id) {
            $query .= " AND warehouse_id = %d";
            $params[] = $warehouse_id;
        }
        
        if ($movement_type) {
            $query .= " AND movement_type = %s";
            $params[] = $movement_type;
        }
        
        if ($date_from) {
            $query .= " AND created_at >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to) {
            $query .= " AND created_at <= %s";
            $params[] = $date_to . ' 23:59:59';
        }
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return intval($wpdb->get_var($query));
    }

    /**
     * Mostrar estadísticas de movimientos
     */
    private function mostrar_estadisticas_movimientos($stats) {
        $tipos = array(
            'initial' => array('label' => __('Stock Inicial', 'gestion-almacenes'), 'icon' => 'plus-alt'),
            'adjustment' => array('label' => __('Ajustes', 'gestion-almacenes'), 'icon' => 'update'),
            'transfer_in' => array('label' => __('Entradas por Transferencia', 'gestion-almacenes'), 'icon' => 'download'),
            'transfer_out' => array('label' => __('Salidas por Transferencia', 'gestion-almacenes'), 'icon' => 'upload'),
            'sale' => array('label' => __('Ventas', 'gestion-almacenes'), 'icon' => 'cart'),
            'return' => array('label' => __('Devoluciones', 'gestion-almacenes'), 'icon' => 'undo')
        );
        
        foreach ($tipos as $tipo => $info) {
            $count = isset($stats[$tipo]) ? $stats[$tipo]->total_movements : 0;
            $quantity = isset($stats[$tipo]) ? abs($stats[$tipo]->total_quantity) : 0;
            ?>
            <div class="gab-stat-card">
                <span class="dashicons dashicons-<?php echo esc_attr($info['icon']); ?>" 
                    style="font-size: 24px; color: #0073aa; margin-bottom: 5px;"></span>
                <span class="stat-number"><?php echo esc_html($count); ?></span>
                <span class="stat-label"><?php echo esc_html($info['label']); ?></span>
                <small style="color: #666;"><?php echo sprintf(__('%d unidades', 'gestion-almacenes'), $quantity); ?></small>
            </div>
            <?php
        }
    }

    /**
     * Obtener información del tipo de movimiento
     */
    private function get_movement_type_info($type) {
        $types = array(
            'initial' => array('label' => __('Stock Inicial', 'gestion-almacenes'), 'color' => '#2271b1'),
            'adjustment' => array('label' => __('Ajuste Manual', 'gestion-almacenes'), 'color' => '#f0ad4e'),
            'transfer_in' => array('label' => __('Entrada Transferencia', 'gestion-almacenes'), 'color' => '#5cb85c'),
            'transfer_out' => array('label' => __('Salida Transferencia', 'gestion-almacenes'), 'color' => '#d9534f'),
            'sale' => array('label' => __('Venta', 'gestion-almacenes'), 'color' => '#dc3545'),
            'return' => array('label' => __('Devolución', 'gestion-almacenes'), 'color' => '#00a32a'),
            'in' => array('label' => __('Entrada', 'gestion-almacenes'), 'color' => '#5cb85c'),
            'out' => array('label' => __('Salida', 'gestion-almacenes'), 'color' => '#d9534f')
        );
        
        return isset($types[$type]) ? $types[$type] : array('label' => $type, 'color' => '#666');
    }

    /**
     * Obtener enlace de referencia
     */
    private function get_reference_link($type, $id) {
        switch ($type) {
            case 'order':
                return sprintf(
                    '<a href="%s">%s #%d</a>',
                    esc_url(admin_url('post.php?post=' . $id . '&action=edit')),
                    __('Pedido', 'gestion-almacenes'),
                    $id
                );
                
            case 'transfer':
                return sprintf(
                    '<a href="%s">%s #%d</a>',
                    esc_url(admin_url('admin.php?page=gab-view-transfer&transfer_id=' . $id)),
                    __('Transferencia', 'gestion-almacenes'),
                    $id
                );
                
            default:
                return $type . ' #' . $id;
        }
    }

    /**
     * AJAX: Buscar productos para select2
     */
    public function ajax_search_products_for_select() {
        // Verificar nonce - intentar diferentes nombres por compatibilidad
        $nonce_valid = false;
        
        if (isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'gab_search_products')) {
            $nonce_valid = true;
        } elseif (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'gab_search_products')) {
            $nonce_valid = true;
        } elseif (isset($_GET['_ajax_nonce']) && wp_verify_nonce($_GET['_ajax_nonce'], 'gab_search_products')) {
            $nonce_valid = true;
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        if (strlen($term) < 2) {
            wp_send_json_success(array());
            return;
        }
        
        // Primero buscar por SKU exacto
        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'meta_query' => array(
                array(
                    'key' => '_sku',
                    'value' => $term,
                    'compare' => 'LIKE'
                )
            )
        );
        
        $products_by_sku = get_posts($args);
        
        // Luego buscar por nombre
        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $term,
            'orderby' => 'relevance'
        );
        
        $products_by_name = get_posts($args);
        
        // Combinar resultados y eliminar duplicados
        $all_products = array_merge($products_by_sku, $products_by_name);
        $seen = array();
        $results = array();
        
        foreach ($all_products as $product_post) {
            if (in_array($product_post->ID, $seen)) {
                continue;
            }
            
            $seen[] = $product_post->ID;
            $product = wc_get_product($product_post->ID);
            
            if (!$product) {
                continue;
            }
            
            // Construir el texto de visualización
            $text = $product->get_name();
            
            // Agregar SKU si existe
            if ($product->get_sku()) {
                $text .= ' (SKU: ' . $product->get_sku() . ')';
            }
            
            // Para variaciones, mostrar atributos
            if ($product->is_type('variation')) {
                $attributes = $product->get_variation_attributes();
                $attr_text = array();
                foreach ($attributes as $attr_name => $attr_value) {
                    if ($attr_value) {
                        $attr_text[] = $attr_value;
                    }
                }
                if (!empty($attr_text)) {
                    $text .= ' - ' . implode(', ', $attr_text);
                }
            }
            
            // Agregar precio para referencia
            $price = $product->get_price();
            if ($price) {
                $text .= ' - ' . get_woocommerce_currency_symbol() . number_format($price, 0, ',', '.');
            }

            $results[] = array(
                'id' => $product->get_id(),
                'text' => $text, // Ya no necesita wp_strip_all_tags
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'price' => $price
            );
            
            // Limitar a 20 resultados
            if (count($results) >= 20) {
                break;
            }
        }
        
        wp_send_json_success($results);
    }

    /**
     * Exportar movimientos a CSV
     */
    public function ajax_export_movements() {
        check_ajax_referer('gab_export_movements', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos');
        }
        
        // Obtener filtros
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
        $movement_type = isset($_GET['movement_type']) ? sanitize_text_field($_GET['movement_type']) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        
        // Obtener todos los movimientos (sin límite)
        $movements = $this->get_filtered_movements($product_id, $warehouse_id, $movement_type, $date_from, $date_to, 1, 999999);
        
        // Configurar headers para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="movimientos-stock-' . date('Y-m-d') . '.csv"');
        
        // Abrir output
        $output = fopen('php://output', 'w');
        
        // BOM para Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, array(
            'Fecha',
            'Producto',
            'SKU',
            'Almacén',
            'Tipo',
            'Cantidad',
            'Saldo',
            'Notas',
            'Usuario'
        ));
        
        // Datos
        foreach ($movements as $movement) {
            $product = wc_get_product($movement->product_id);
            $type_info = $this->get_movement_type_info($movement->movement_type);
            
            fputcsv($output, array(
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($movement->created_at)),
                $product ? $product->get_name() : 'Producto eliminado',
                $product ? $product->get_sku() : '',
                $movement->warehouse_name,
                $type_info['label'],
                $movement->quantity,
                $movement->balance_after,
                $movement->notes,
                $movement->user_name
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * Agrupar TODOS los productos (incluyendo los sin stock asignado)
     */
    private function agrupar_todos_productos($todos_productos, $almacenes) {
        $productos_agrupados = array();
        
        foreach ($todos_productos as $producto) {
            $product_id = $producto->product_id;
            
            // Inicializar estructura
            $productos_agrupados[$product_id] = array(
                'almacenes' => array(),
                'total_stock' => 0,
                'sin_asignar' => true,
                'tiene_registros' => false,
                'almacenes_agotados' => 0, // Nuevo: contar almacenes agotados
                'es_agotado_total' => false, // Nuevo: si está agotado en TODOS los almacenes
                'tiene_algun_agotamiento' => false, // Nuevo: si tiene al menos un almacén agotado
                'wc_stock' => $producto->wc_stock,
                'manage_stock' => $producto->manage_stock,
                'sku' => $producto->sku
            );
            
            // Si tiene registros de stock
            if (isset($producto->warehouse_stock) && !empty($producto->warehouse_stock)) {
                $productos_agrupados[$product_id]['tiene_registros'] = true;
                $productos_agrupados[$product_id]['sin_asignar'] = false;
                
                $almacenes_con_registro = 0;
                $almacenes_en_cero = 0;
                
                foreach ($producto->warehouse_stock as $warehouse_id => $stock) {
                    $stock_value = intval($stock);
                    $productos_agrupados[$product_id]['almacenes'][$warehouse_id] = $stock_value;
                    $productos_agrupados[$product_id]['total_stock'] += $stock_value;
                    
                    $almacenes_con_registro++;
                    
                    if ($stock_value == 0) {
                        $almacenes_en_cero++;
                        $productos_agrupados[$product_id]['tiene_algun_agotamiento'] = true;
                    }
                }
                
                $productos_agrupados[$product_id]['almacenes_agotados'] = $almacenes_en_cero;
                
                // Está completamente agotado si todos los almacenes con registro están en 0
                if ($almacenes_con_registro > 0 && $almacenes_con_registro == $almacenes_en_cero) {
                    $productos_agrupados[$product_id]['es_agotado_total'] = true;
                }
            }
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
            
            // Lógica base de inclusión
            if ($producto_data['sin_asignar']) {
                // Producto sin asignar
                if ($show_all === 'yes' || empty($status_filters)) {
                    $incluir_producto = true;
                }
            } else if ($producto_data['total_stock'] > 0 || $producto_data['tiene_algun_agotamiento']) {
                // Producto con stock o con agotamientos
                $incluir_producto = true;
            }
            
            // Aplicar filtro por almacenes
            if (!empty($warehouse_filters) && !$producto_data['sin_asignar']) {
                $tiene_relacion_con_almacenes = false;
                
                foreach ($warehouse_filters as $warehouse_id) {
                    // Incluir si tiene stock o registro (aunque sea 0) en el almacén filtrado
                    if (isset($producto_data['almacenes'][$warehouse_id])) {
                        $tiene_relacion_con_almacenes = true;
                        break;
                    }
                }
                
                if (!$tiene_relacion_con_almacenes) {
                    $incluir_producto = false;
                }
            }
            
            // Aplicar filtros de estado
            if (!empty($status_filters)) {
                $cumple_estado = false;
                
                // Sin asignar
                if ($producto_data['sin_asignar'] && in_array('unassigned', $status_filters)) {
                    $cumple_estado = true;
                }
                // Sin stock - incluir productos totalmente agotados O con algún agotamiento
                else if (in_array('out', $status_filters) && 
                        ($producto_data['es_agotado_total'] || $producto_data['tiene_algun_agotamiento'])) {
                    $cumple_estado = true;
                }
                // Con stock - evaluar niveles
                else if ($producto_data['total_stock'] > 0) {
                    $tiene_stock_bajo = false;
                    $tiene_stock_medio = false;
                    $tiene_stock_alto = false;
                    
                    foreach ($producto_data['almacenes'] as $stock) {
                        if ($stock > 0) {
                            if ($stock <= $threshold) {
                                $tiene_stock_bajo = true;
                            } else if ($stock <= ($threshold * 2)) {
                                $tiene_stock_medio = true;
                            } else {
                                $tiene_stock_alto = true;
                            }
                        }
                    }
                    
                    if ($tiene_stock_bajo && in_array('low', $status_filters)) {
                        $cumple_estado = true;
                    }
                    if ($tiene_stock_medio && in_array('medium', $status_filters)) {
                        $cumple_estado = true;
                    }
                    if ($tiene_stock_alto && in_array('high', $status_filters)) {
                        $cumple_estado = true;
                    }
                }
                
                $incluir_producto = $cumple_estado;
            }
            
            if ($incluir_producto) {
                $productos_filtrados[$product_id] = $producto_data;
            }
        }
        
        return $productos_filtrados;
    }

    /**
     * Mostrar estadísticas extendidas (CORREGIDO)
     */
    private function mostrar_estadisticas_stock_agrupadas_extendido($productos_filtrados, $almacenes_mostrados, $threshold, $todos_productos) {
        $total_productos = count($productos_filtrados);
        $total_productos_wc = count($todos_productos);
        $total_stock = 0;
        $productos_sin_stock = 0; // Incluirá productos con agotamientos
        $productos_stock_bajo = 0;
        $productos_sin_asignar = 0;
        $total_almacenes_mostrados = count($almacenes_mostrados);

        foreach ($productos_filtrados as $producto_data) {
            // Producto sin asignar
            if ($producto_data['sin_asignar']) {
                $productos_sin_asignar++;
                continue;
            }
            
            // Producto con algún agotamiento o totalmente agotado
            if ($producto_data['es_agotado_total'] || $producto_data['tiene_algun_agotamiento']) {
                $productos_sin_stock++;
                // No hacer continue si tiene stock parcial, para contarlo también en el total
            }
            
            // Contar stock total
            $stock_producto_total = 0;
            $tiene_stock_bajo = false;
            
            if (!empty($almacenes_mostrados)) {
                foreach ($almacenes_mostrados as $almacen) {
                    if (isset($producto_data['almacenes'][$almacen->id])) {
                        $stock_almacen = $producto_data['almacenes'][$almacen->id];
                        $stock_producto_total += $stock_almacen;
                        
                        if ($stock_almacen > 0 && $stock_almacen <= $threshold) {
                            $tiene_stock_bajo = true;
                        }
                    }
                }
            } else {
                $stock_producto_total = $producto_data['total_stock'];
                
                foreach ($producto_data['almacenes'] as $stock) {
                    if ($stock > 0 && $stock <= $threshold) {
                        $tiene_stock_bajo = true;
                    }
                }
            }
            
            $total_stock += $stock_producto_total;
            
            // Solo contar como stock bajo si NO está en la categoría de agotados
            if ($tiene_stock_bajo && !$producto_data['es_agotado_total']) {
                $productos_stock_bajo++;
            }
        }

        // Mostrar estadísticas
        echo '<div class="gab-stats-grid">';
        
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
        echo '<span class="stat-number" style="color: #dc3545; font-weight: bold;">' . $productos_sin_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Sin Stock / Agotados', 'gestion-almacenes') . '</span>';
        echo '</div>';
        
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
        
        global $wpdb, $gestion_almacenes_movements;
        $table = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Obtener stock actual
        $current_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT stock FROM $table WHERE product_id = %d AND warehouse_id = %d",
            $product_id,
            $warehouse_id
        ));
        
        $old_stock = $current_stock !== null ? intval($current_stock) : 0;
        $new_stock = 0;
        $movement_qty = 0;
        $movement_notes = '';
        
        // Calcular nuevo stock según la operación
        switch ($operation) {
            case 'add':
                $new_stock = $old_stock + $stock;
                $movement_qty = $stock;
                $movement_notes = sprintf(
                    __('Ajuste: Agregar %d unidades. Stock: %d → %d', 'gestion-almacenes'),
                    $stock, $old_stock, $new_stock
                );
                break;
                
            case 'subtract':
                $new_stock = max(0, $old_stock - $stock);
                $movement_qty = -min($stock, $old_stock);
                $movement_notes = sprintf(
                    __('Ajuste: Restar %d unidades. Stock: %d → %d', 'gestion-almacenes'),
                    $stock, $old_stock, $new_stock
                );
                break;
                
            case 'set':
            default:
                $new_stock = $stock;
                $movement_qty = $new_stock - $old_stock;
                $movement_notes = sprintf(
                    __('Ajuste: Establecer stock en %d. Stock anterior: %d', 'gestion-almacenes'),
                    $new_stock, $old_stock
                );
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
        
        // Registrar el movimiento solo si hubo cambio y el gestor de movimientos existe
        if ($movement_qty !== 0 && isset($gestion_almacenes_movements)) {
            $gestion_almacenes_movements->log_movement(array(
                'product_id' => $product_id,
                'warehouse_id' => $warehouse_id,
                'type' => 'adjustment',
                'quantity' => $movement_qty,
                'notes' => $movement_notes
            ));
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
    private function determinar_estado_detallado_producto($producto_data, $threshold) {
        // Si nunca ha tenido registros
        if (!isset($producto_data['tiene_registros']) || !$producto_data['tiene_registros']) {
            return array(
                'estado' => 'unassigned',
                'label' => __('Sin asignar', 'gestion-almacenes'),
                'class' => 'stock-unassigned',
                'icon' => 'dashicons-minus'
            );
        }
        
        // Si tiene registros pero stock total es 0
        if ($producto_data['total_stock'] == 0) {
            return array(
                'estado' => 'depleted',
                'label' => __('Agotado', 'gestion-almacenes'),
                'class' => 'stock-out',
                'icon' => 'dashicons-warning'
            );
        }
        
        // Analizar niveles de stock
        $almacenes_con_stock = 0;
        $almacenes_stock_bajo = 0;
        
        foreach ($producto_data['almacenes'] as $warehouse_id => $stock) {
            if ($stock > 0) {
                $almacenes_con_stock++;
                if ($stock <= $threshold) {
                    $almacenes_stock_bajo++;
                }
            }
        }
        
        // Si todos los almacenes con stock están bajos
        if ($almacenes_con_stock > 0 && $almacenes_con_stock == $almacenes_stock_bajo) {
            return array(
                'estado' => 'low',
                'label' => __('Stock bajo', 'gestion-almacenes'),
                'class' => 'stock-low',
                'icon' => 'dashicons-arrow-down'
            );
        }
        
        // Si hay mezcla de stocks
        if ($almacenes_stock_bajo > 0) {
            return array(
                'estado' => 'mixed',
                'label' => __('Stock mixto', 'gestion-almacenes'),
                'class' => 'stock-medium',
                'icon' => 'dashicons-leftright'
            );
        }
        
        // Stock normal/alto
        return array(
            'estado' => 'normal',
            'label' => __('Stock disponible', 'gestion-almacenes'),
            'class' => 'stock-high',
            'icon' => 'dashicons-yes'
        );
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

        .wrap.gab-admin-page {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .gab-section-header {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .gab-form-row {
            display: block;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .gab-form-group {
            display: flex;
            flex-direction: row;
            align-items: center;
            width: 100%; /* o 50% si deseas dos columnas en desktop */
            max-width: 600px;
            margin-bottom: 1rem;
        }

        .gab-form-group label {
            width: 150px; /* ajusta según lo largo de los textos */
            margin-right: 1rem;
            font-weight: bold;
        }

        .gab-form-group select {
            flex: 1;
            padding: 0.5rem;
            max-width: 100%;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 2% auto;
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

        ?>
        <style>
        .wrap.gab-admin-page {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .gab-section-header {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .gab-form-row {
            margin-bottom: 20px;
        }
        </style>
        <?php
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

        .wrap.gab-admin-page {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .gab-section-header {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .gab-form-row {
            display: block;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .gab-form-group {
            display: flex;
            flex-direction: row;
            align-items: center;
            width: 100%; /* o 50% si deseas dos columnas en desktop */
            max-width: 600px;
            margin-bottom: 1rem;
        }

        .gab-form-group label {
            width: 150px; /* ajusta según lo largo de los textos */
            margin-right: 1rem;
            font-weight: bold;
        }

        .gab-form-group select {
            flex: 1;
            padding: 0.5rem;
            max-width: 100%;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
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
        
        <div class="wrap gab-admin-page">
            <div class="gab-section-header">
                <h1><?php esc_html_e('Configuración de Gestión de Almacenes', 'gestion-almacenes'); ?></h1>
            </div>
            
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

        <style>
        .wrap.gab-admin-page {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .gab-section-header {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        </style>

        <?php
    }

    // Página de herramienas
    public function mostrar_herramientas() {
        global $gestion_almacenes_db, $wpdb;
        
        // Manejar acciones
        if (isset($_POST['gab_action']) && wp_verify_nonce($_POST['gab_tools_nonce'], 'gab_tools_actions')) {
            switch ($_POST['gab_action']) {
                case 'verify_tables':
                    $missing = $gestion_almacenes_db->verificar_tablas();
                    if (empty($missing)) {
                        echo '<div class="notice notice-success"><p>✓ Todas las tablas están presentes</p></div>';
                    } else {
                        echo '<div class="notice notice-warning"><p>Tablas faltantes: ' . implode(', ', $missing) . '</p></div>';
                    }
                    break;
                    
                case 'repair_tables':
                    $result = $gestion_almacenes_db->crear_tablas_plugin();
                    if ($result['success']) {
                        echo '<div class="notice notice-success"><p>✓ Tablas reparadas correctamente</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Errores: ' . implode('<br>', $result['errors']) . '</p></div>';
                    }
                    break;
                    
                case 'clear_logs':
                    // Limpiar logs antiguos si implementas un sistema de logs
                    echo '<div class="notice notice-success"><p>✓ Logs limpiados</p></div>';
                    break;
            }
        }
        ?>
        <div class="wrap gab-admin-page">
            <h1><?php esc_html_e('Herramientas de Gestión de Almacenes', 'gestion-almacenes'); ?></h1>
            
            <div class="gab-tools-section">
                <h2><?php esc_html_e('Estado de la Base de Datos', 'gestion-almacenes'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tabla', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Estado', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Registros', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Tamaño', 'gestion-almacenes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $tables = [
                            'gab_warehouses' => __('Almacenes', 'gestion-almacenes'),
                            'gab_warehouse_product_stock' => __('Stock por Almacén', 'gestion-almacenes'),
                            'gab_stock_movements' => __('Movimientos de Stock', 'gestion-almacenes'),
                            'gab_stock_transfers' => __('Transferencias', 'gestion-almacenes'),
                            'gab_stock_transfer_items' => __('Items de Transferencias', 'gestion-almacenes')
                        ];
                        
                        foreach ($tables as $table => $name) {
                            $full_table = $wpdb->prefix . $table;
                            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
                            
                            echo '<tr>';
                            echo '<td><strong>' . esc_html($name) . '</strong><br><code>' . esc_html($full_table) . '</code></td>';
                            
                            if ($exists) {
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                                $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '$full_table'");
                                
                                echo '<td><span style="color: green;">✓ ' . __('Existe', 'gestion-almacenes') . '</span></td>';
                                echo '<td>' . number_format($count) . '</td>';
                                echo '<td>' . ($size ? $size . ' MB' : '-') . '</td>';
                            } else {
                                echo '<td><span style="color: red;">✗ ' . __('No existe', 'gestion-almacenes') . '</span></td>';
                                echo '<td>-</td>';
                                echo '<td>-</td>';
                            }
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
                
                <h2 style="margin-top: 30px;"><?php esc_html_e('Acciones de Mantenimiento', 'gestion-almacenes'); ?></h2>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('gab_tools_actions', 'gab_tools_nonce'); ?>
                    
                    <p>
                        <button type="submit" name="gab_action" value="verify_tables" class="button">
                            <?php esc_html_e('Verificar Tablas', 'gestion-almacenes'); ?>
                        </button>
                        <span class="description"><?php esc_html_e('Verifica que todas las tablas necesarias existan', 'gestion-almacenes'); ?></span>
                    </p>
                    
                    <p>
                        <button type="submit" name="gab_action" value="repair_tables" class="button button-primary" 
                                onclick="return confirm('<?php esc_attr_e('¿Estás seguro? Esto intentará crear todas las tablas faltantes.', 'gestion-almacenes'); ?>')">
                            <?php esc_html_e('Reparar Tablas', 'gestion-almacenes'); ?>
                        </button>
                        <span class="description"><?php esc_html_e('Crea las tablas faltantes', 'gestion-almacenes'); ?></span>
                    </p>
                </form>
                
                <h2 style="margin-top: 30px;"><?php esc_html_e('Información del Sistema', 'gestion-almacenes'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Versión del Plugin', 'gestion-almacenes'); ?></th>
                        <td><?php echo GESTION_ALMACENES_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Versión de la BD', 'gestion-almacenes'); ?></th>
                        <td><?php echo get_option('gab_db_version', 'version'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Prefijo de Tablas', 'gestion-almacenes'); ?></th>
                        <td><code><?php echo $wpdb->prefix; ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Versión de PHP', 'gestion-almacenes'); ?></th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Versión de MySQL', 'gestion-almacenes'); ?></th>
                        <td><?php echo $wpdb->db_version(); ?></td>
                    </tr>
                </table>

                <h2 style="margin-top: 30px;"><?php esc_html_e('Historial de Migraciones', 'gestion-almacenes'); ?></h2>

                <?php
                $migraciones = $gestion_almacenes_db->obtener_historial_migraciones();
                if (!empty($migraciones)) {
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Versión', 'gestion-almacenes'); ?></th>
                                <th><?php esc_html_e('Descripción', 'gestion-almacenes'); ?></th>
                                <th><?php esc_html_e('Fecha', 'gestion-almacenes'); ?></th>
                                <th><?php esc_html_e('Resultado', 'gestion-almacenes'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($migraciones) as $migracion) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($migracion['version']); ?></strong></td>
                                <td><?php echo esc_html($migracion['descripcion']); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($migracion['fecha']))); ?></td>
                                <td>
                                    <?php if ($migracion['resultado'] === 'success') : ?>
                                        <span style="color: green;">✓ <?php esc_html_e('Exitoso', 'gestion-almacenes'); ?></span>
                                    <?php else : ?>
                                        <span style="color: red;">✗ <?php esc_html_e('Error', 'gestion-almacenes'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                } else {
                    echo '<p>' . esc_html__('No hay migraciones registradas.', 'gestion-almacenes') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    // Agregar este método en la misma clase:
    public function mostrar_gestor_versiones() {
        // Cargar el gestor de versiones
        require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-version-manager.php';
        $version_manager = new Gestion_Almacenes_Version_Manager();
        
        // Procesar acciones
        if (isset($_POST['action']) && wp_verify_nonce($_POST['version_nonce'], 'gab_version_action')) {
            switch ($_POST['action']) {
                case 'increment_version':
                    $type = sanitize_text_field($_POST['version_type']);
                    $description = sanitize_textarea_field($_POST['version_description']);
                    $new_version = $version_manager->incrementar_version($type, $description);
                    echo '<div class="notice notice-success"><p>Versión actualizada a: ' . $new_version . '</p></div>';
                    break;
                    
                case 'add_change':
                    $change_type = sanitize_text_field($_POST['change_type']);
                    $change_desc = sanitize_textarea_field($_POST['change_description']);
                    $version_manager->registrar_cambio_pendiente($change_type, $change_desc);
                    echo '<div class="notice notice-success"><p>Cambio registrado para el próximo release</p></div>';
                    break;
                    
                case 'create_release':
                    $result = $version_manager->crear_release();
                    if ($result['success']) {
                        echo '<div class="notice notice-success"><p>Release creado: <a href="' . $result['url'] . '">Descargar</a></p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Error: ' . $result['error'] . '</p></div>';
                    }
                    break;
            }
        }
        
        $pending_changes = $version_manager->obtener_cambios_pendientes();
        $version_history = $version_manager->obtener_historial_versiones();
        ?>
        <div class="wrap gab-admin-page">
            <h1><?php esc_html_e('Gestor de Versiones', 'gestion-almacenes'); ?></h1>
            
            <div class="gab-version-info" style="background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h2 style="margin-top: 0;">Información Actual</h2>
                <p><strong>Versión del Plugin:</strong> <?php echo GESTION_ALMACENES_VERSION; ?></p>
                <p><strong>Versión de la Base de Datos:</strong> <?php echo defined('GESTION_ALMACENES_DB_VERSION') ? GESTION_ALMACENES_DB_VERSION : 'No definida'; ?></p>
                <p><strong>Fecha de Instalación:</strong> <?php echo get_option('gab_db_install_date', 'No registrada'); ?></p>
            </div>
            
            <!-- Incrementar Versión -->
            <div class="gab-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc;">
                <h2><?php esc_html_e('Crear Nueva Versión', 'gestion-almacenes'); ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('gab_version_action', 'version_nonce'); ?>
                    <input type="hidden" name="action" value="increment_version">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="version_type"><?php esc_html_e('Tipo de Versión', 'gestion-almacenes'); ?></label></th>
                            <td>
                                <select name="version_type" id="version_type">
                                    <option value="patch"><?php esc_html_e('Patch (x.x.X) - Correcciones menores', 'gestion-almacenes'); ?></option>
                                    <option value="minor"><?php esc_html_e('Minor (x.X.0) - Nuevas funcionalidades', 'gestion-almacenes'); ?></option>
                                    <option value="major"><?php esc_html_e('Major (X.0.0) - Cambios importantes', 'gestion-almacenes'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Patch: correcciones de bugs. Minor: nuevas características. Major: cambios que rompen compatibilidad.', 'gestion-almacenes'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="version_description"><?php esc_html_e('Descripción', 'gestion-almacenes'); ?></label></th>
                            <td>
                                <textarea name="version_description" id="version_description" rows="3" cols="50"></textarea>
                                <p class="description"><?php esc_html_e('Resumen de los cambios principales en esta versión', 'gestion-almacenes'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Crear Nueva Versión', 'gestion-almacenes'); ?>">
                        <a href="<?php echo admin_url('admin.php?page=gab-version-manager&action=create_release'); ?>" 
                        class="button button-secondary"
                        onclick="return confirm('¿Crear un archivo ZIP de la versión actual?');">
                            <?php esc_html_e('Crear Release ZIP', 'gestion-almacenes'); ?>
                        </a>
                    </p>
                </form>
            </div>
            
            <!-- Registrar Cambios Pendientes -->
            <div class="gab-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc;">
                <h2><?php esc_html_e('Registrar Cambio Pendiente', 'gestion-almacenes'); ?></h2>
                
                <form method="post">
                    <?php wp_nonce_field('gab_version_action', 'version_nonce'); ?>
                    <input type="hidden" name="action" value="add_change">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="change_type"><?php esc_html_e('Tipo de Cambio', 'gestion-almacenes'); ?></label></th>
                            <td>
                                <select name="change_type" id="change_type">
                                    <option value="Added"><?php esc_html_e('Added - Nueva funcionalidad', 'gestion-almacenes'); ?></option>
                                    <option value="Changed"><?php esc_html_e('Changed - Cambio en funcionalidad existente', 'gestion-almacenes'); ?></option>
                                    <option value="Fixed"><?php esc_html_e('Fixed - Corrección de bug', 'gestion-almacenes'); ?></option>
                                    <option value="Removed"><?php esc_html_e('Removed - Funcionalidad eliminada', 'gestion-almacenes'); ?></option>
                                    <option value="Security"><?php esc_html_e('Security - Mejora de seguridad', 'gestion-almacenes'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="change_description"><?php esc_html_e('Descripción', 'gestion-almacenes'); ?></label></th>
                            <td>
                                <input type="text" name="change_description" id="change_description" class="regular-text" required>
                                <p class="description"><?php esc_html_e('Describe brevemente el cambio realizado', 'gestion-almacenes'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button" value="<?php esc_attr_e('Registrar Cambio', 'gestion-almacenes'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- Cambios Pendientes -->
            <?php if (!empty($pending_changes)) : ?>
            <div class="gab-card" style="background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc;">
                <h2><?php esc_html_e('Cambios Pendientes para el Próximo Release', 'gestion-almacenes'); ?></h2>
                
                <ul>
                    <?php foreach ($pending_changes as $change) : ?>
                    <li>
                        <strong><?php echo esc_html($change['type']); ?>:</strong> 
                        <?php echo esc_html($change['description']); ?>
                        <em>(<?php echo esc_html($change['date']); ?> por <?php echo esc_html($change['user']); ?>)</em>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Historial de Versiones -->
            <div class="gab-card" style="background: white; padding: 20px; border: 1px solid #ccc;">
                <h2><?php esc_html_e('Historial de Versiones', 'gestion-almacenes'); ?></h2>
                
                <?php if (!empty($version_history)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Versión', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Tipo', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Descripción', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Fecha', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Usuario', 'gestion-almacenes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($version_history) as $version) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($version['version']); ?></strong></td>
                            <td><?php echo esc_html(ucfirst($version['type'])); ?></td>
                            <td><?php echo esc_html($version['description']); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($version['date']))); ?></td>
                            <td><?php echo esc_html($version['user']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p><?php esc_html_e('No hay historial de versiones registrado aún.', 'gestion-almacenes'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }


}
