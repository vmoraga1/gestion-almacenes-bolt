<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'registrar_menu_almacenes'));
        add_action('admin_post_gab_add_warehouse', array($this, 'procesar_agregar_almacen'));
        // Hooks para reportes y transferencias
        add_action('admin_menu', array($this, 'registrar_menu_stock_report'));
        add_action('wp_ajax_get_warehouse_stock', array($this, 'ajax_get_warehouse_stock'));
        // Hook para agregar estilos CSS en admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
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

        // Submenú para Agregar Nuevo Almacén
        add_submenu_page(
            'gab-warehouse-management',
            __('Agregar Nuevo Almacén', 'gestion-almacenes'),
            __('Agregar Nuevo', 'gestion-almacenes'),
            'manage_options',
            'gab-add-new-warehouse',
            'gab_mostrar_formulario_nuevo_almacen'
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

        // Submenú para Transferencias
        add_submenu_page(
            'gab-warehouse-management', 
            __('Transferir Stock', 'gestion-almacenes'),
            __('Transferir Stock', 'gestion-almacenes'),
            'manage_options',
            'gab-transfer-stock', 
            array($this, 'mostrar_transferir_stock')
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
            strpos($hook, 'gab-add-new-warehouse') === false) {
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


    public function contenido_pagina_almacenes() {
        echo '<div class="wrap"><h1>' . esc_html(__('Manage Warehouses', 'gestion-almacenes')) . '</h1>';

        if (isset($_GET['status'])) {
            if ($_GET['status'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('Almacén guardado correctamente.', 'gestion-almacenes')) . '</p></div>';
            } elseif ($_GET['status'] === 'error') {
                $message = __('Ocurrió un error al guardar el almacén.', 'gestion-almacenes');
                if (!empty($_GET['message'])) {
                    $error_code = sanitize_key($_GET['message']);
                    if ($error_code === 'nombre_vacio') {
                        $message = __('Error: El nombre del almacén no puede estar vacío.', 'gestion-almacenes');
                    } elseif ($error_code === 'error_db') {
                        $message = __('Error de base de datos al guardar el almacén.', 'gestion-almacenes');
                    }
                }
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }

        $add_new_url = admin_url('admin.php?page=gab-add-new-warehouse');
        echo '<p><a href="' . esc_url($add_new_url) . '" class="button button-primary">' . esc_html(__('Agregar Nuevo Almacén', 'gestion-almacenes')) . '</a></p>';

        global $gestion_almacenes_db;
        $almacenes = $gestion_almacenes_db->obtener_almacenes();

        if ($almacenes) {
            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            $columns = ['ID', 'Nombre', 'Dirección', 'Comuna', 'Ciudad', 'Región', 'País', 'Email', 'Teléfono', 'Acciones'];
            foreach ($columns as $col) {
                echo '<th>' . esc_html__($col, 'gestion-almacenes') . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($almacenes as $almacen) {
                echo '<tr>';
                echo '<td>' . esc_html($almacen->id) . '</td>';
                echo '<td>' . esc_html($almacen->name) . '</td>';
                echo '<td>' . esc_html($almacen->address) . '</td>';
                echo '<td>' . esc_html($almacen->comuna) . '</td>';
                echo '<td>' . esc_html($almacen->ciudad) . '</td>';
                echo '<td>' . esc_html($almacen->region) . '</td>';
                echo '<td>' . esc_html($almacen->pais) . '</td>';
                echo '<td>' . esc_html($almacen->email) . '</td>';
                echo '<td>' . esc_html($almacen->telefono) . '</td>';
                echo '<td>' . esc_html__('Editar | Eliminar', 'gestion-almacenes') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No hay almacenes registrados aún.', 'gestion-almacenes') . '</p>';
        }

        echo '</div>';
    }

    public function procesar_agregar_almacen() {
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
    }

    public function registrar_menu_stock_report() {
        add_submenu_page(
            'gab-warehouse-management',
            __('Reporte de Stock', 'gestion-almacenes'),
            __('Reporte de Stock', 'gestion-almacenes'),
            'manage_options',
            'gab-stock-report',
            array($this, 'mostrar_reporte_stock')
        );

        add_submenu_page(
            'gab-warehouse-management',
            __('Transferir Stock', 'gestion-almacenes'),
            __('Transferir Stock', 'gestion-almacenes'),
            'manage_options',
            'gab-transfer-stock',
            array($this, 'mostrar_transferir_stock')
        );
    }

    public function mostrar_reporte_stock() {
        global $gestion_almacenes_db;
        echo '<div class="wrap"><h1>' . esc_html__('Reporte de Stock por Almacén', 'gestion-almacenes') . '</h1>';

        // Aquí puedes insertar la lógica del reporte de stock general si lo deseas.

        echo '</div>';
    }

    public function mostrar_transferir_stock() {
        global $gestion_almacenes_db;

        echo '<div class="wrap"><h1>' . esc_html__('Transferir Stock entre Almacenes', 'gestion-almacenes') . '</h1>';

        if (isset($_POST['transfer_stock']) && wp_verify_nonce($_POST['transfer_nonce'], 'transfer_stock_nonce')) {
            $product_id = intval($_POST['product_id']);
            $from_warehouse = intval($_POST['from_warehouse']);
            $to_warehouse = intval($_POST['to_warehouse']);
            $quantity = intval($_POST['quantity']);

            if ($product_id && $from_warehouse && $to_warehouse && $quantity > 0) {
                $result = $gestion_almacenes_db->transfer_stock_between_warehouses($product_id, $from_warehouse, $to_warehouse, $quantity);

                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Transferencia realizada con éxito.', 'gestion-almacenes') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error al realizar la transferencia. Verifica que haya suficiente stock.', 'gestion-almacenes') . '</p></div>';
                }
            }
        }

        // Aquí podrías agregar el formulario si aún no lo tienes como vista separada.
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
 * Página de configuración del plugin
*/
public function mostrar_configuracion() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html(__('Configuración de Gestión de Almacenes', 'gestion-almacenes')) . '</h1>';
    
    // Procesar configuración si se envió el formulario
    if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['settings_nonce'], 'save_settings_nonce')) {
        $default_warehouse = intval($_POST['default_warehouse']);
        $low_stock_threshold = intval($_POST['low_stock_threshold']);
        $auto_select_warehouse = isset($_POST['auto_select_warehouse']) ? 1 : 0;
        
        update_option('gab_default_warehouse', $default_warehouse);
        update_option('gab_low_stock_threshold', $low_stock_threshold);
        update_option('gab_auto_select_warehouse', $auto_select_warehouse);
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('Configuración guardada correctamente.', 'gestion-almacenes')) . '</p></div>';
    }
    
    global $gestion_almacenes_db;
    $almacenes = $gestion_almacenes_db->obtener_almacenes();
    
    $default_warehouse = get_option('gab_default_warehouse', '');
    $low_stock_threshold = get_option('gab_low_stock_threshold', 5);
    $auto_select_warehouse = get_option('gab_auto_select_warehouse', 0);
    
    echo '<form method="post" action="">';
    wp_nonce_field('save_settings_nonce', 'settings_nonce');
    
    echo '<table class="form-table">';
    
    // Almacén por defecto
    echo '<tr>';
    echo '<th scope="row"><label for="default_warehouse">' . esc_html(__('Almacén por Defecto', 'gestion-almacenes')) . '</label></th>';
    echo '<td>';
    echo '<select name="default_warehouse" id="default_warehouse">';
    echo '<option value="">' . esc_html(__('Ninguno', 'gestion-almacenes')) . '</option>';
    if ($almacenes) {
        foreach ($almacenes as $almacen) {
            $selected = ($default_warehouse == $almacen->id) ? 'selected' : '';
            echo '<option value="' . esc_attr($almacen->id) . '" ' . $selected . '>' . esc_html($almacen->name) . '</option>';
        }
    }
    echo '</select>';
    echo '<p class="description">' . esc_html(__('Almacén que se seleccionará automáticamente en los productos.', 'gestion-almacenes')) . '</p>';
    echo '</td>';
    echo '</tr>';
    
    // Umbral de stock bajo
    echo '<tr>';
    echo '<th scope="row"><label for="low_stock_threshold">' . esc_html(__('Umbral de Stock Bajo', 'gestion-almacenes')) . '</label></th>';
    echo '<td>';
    echo '<input type="number" name="low_stock_threshold" id="low_stock_threshold" value="' . esc_attr($low_stock_threshold) . '" min="1" class="small-text">';
    echo '<p class="description">' . esc_html(__('Cantidad por debajo de la cual se considera stock bajo.', 'gestion-almacenes')) . '</p>';
    echo '</td>';
    echo '</tr>';
    
    // Selección automática de almacén
    echo '<tr>';
    echo '<th scope="row">' . esc_html(__('Selección Automática', 'gestion-almacenes')) . '</th>';
    echo '<td>';
    echo '<fieldset>';
    echo '<label>';
    echo '<input type="checkbox" name="auto_select_warehouse" value="1" ' . checked($auto_select_warehouse, 1, false) . '>';
    echo esc_html(__('Seleccionar automáticamente el almacén con más stock disponible', 'gestion-almacenes'));
    echo '</label>';
    echo '<p class="description">' . esc_html(__('Si está activado, se seleccionará automáticamente el almacén con mayor stock disponible para el producto.', 'gestion-almacenes')) . '</p>';
    echo '</fieldset>';
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" name="save_settings" class="button-primary" value="' . esc_attr(__('Guardar Configuración', 'gestion-almacenes')) . '">';
    echo '</p>';
    echo '</form>';
    
    // Información del sistema
    echo '<h2>' . esc_html(__('Información del Sistema', 'gestion-almacenes')) . '</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>' . esc_html(__('Configuración', 'gestion-almacenes')) . '</th><th>' . esc_html(__('Valor', 'gestion-almacenes')) . '</th></tr></thead>';
    echo '<tbody>';
    
    echo '<tr><td>' . esc_html(__('Total de Almacenes', 'gestion-almacenes')) . '</td><td>' . (is_array($almacenes) ? count($almacenes) : 0) . '</td></tr>';
    
    $productos_con_stock = $gestion_almacenes_db->get_all_products_warehouse_stock();
    $productos_unicos = array();
    if ($productos_con_stock) {
        foreach ($productos_con_stock as $item) {
            if (!in_array($item->product_id, $productos_unicos)) {
                $productos_unicos[] = $item->product_id;
            }
        }
    }
    echo '<tr><td>' . esc_html(__('Productos con Stock Asignado', 'gestion-almacenes')) . '</td><td>' . count($productos_unicos) . '</td></tr>';
    
    $productos_stock_bajo = $gestion_almacenes_db->get_low_stock_products($low_stock_threshold);
    echo '<tr><td>' . esc_html(__('Productos con Stock Bajo', 'gestion-almacenes')) . '</td><td>' . (is_array($productos_stock_bajo) ? count($productos_stock_bajo) : 0) . '</td></tr>';
    
    echo '<tr><td>' . esc_html(__('Versión del Plugin', 'gestion-almacenes')) . '</td><td>1.0.0</td></tr>';
    echo '<tr><td>' . esc_html(__('Versión de WordPress', 'gestion-almacenes')) . '</td><td>' . get_bloginfo('version') . '</td></tr>';
    echo '<tr><td>' . esc_html(__('Versión de WooCommerce', 'gestion-almacenes')) . '</td><td>' . (defined('WC_VERSION') ? WC_VERSION : 'No instalado') . '</td></tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>';
}


}
