<?php
/*
Plugin Name: Sistema de Ventas
Plugin URI: https://hostpanish.com/personalizaciones/
Description: Sistema de cotizaciones y ventas integrado con WooCommerce
Version: 1.0
Author: Victor Moraga
Author URI: https://hostpanish.com/
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

set_time_limit(120);

// Definir constantes del plugin
define('VENTAS_PLUGIN_DIR', dirname(__FILE__) . '/');
define('VENTAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VENTAS_VERSION', '1.0.0');

// Verificar que WooCommerce esté instalado y activo
function ventas_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>El plugin Ventas requiere WooCommerce para funcionar.</p></div>';
        });
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins(plugin_basename(__FILE__));
        }
        return;
    }
}
add_action('admin_init', 'ventas_check_woocommerce');

// Cargar estilos y scripts
function ventas_enqueue_admin_assets() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'ventas') !== false) {
        // jQuery UI
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_script('jquery-ui-dialog');
        
        // Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
        
        // Plugin assets
        wp_enqueue_style('ventas-admin-styles', VENTAS_PLUGIN_URL . 'assets/css/admin-styles.css', array(), VENTAS_VERSION);
        wp_enqueue_script('ventas-admin-scripts', VENTAS_PLUGIN_URL . 'assets/js/cotizaciones.js', array('jquery', 'jquery-ui-dialog', 'select2'), VENTAS_VERSION, true);
        
        wp_localize_script('ventas-admin-scripts', 'ventasAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ventas_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol()
        ));
    }
}
add_action('admin_enqueue_scripts', 'ventas_enqueue_admin_assets');

// Crear tablas personalizadas al activar el plugin
// Verificar dependencias al activar
function ventas_activate() {
    // Verificar TCPDF
    $tcpdf_path = VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Este plugin requiere la librería TCPDF. Por favor, ejecute "composer install" en el directorio del plugin.');
    }
    
    // Inicializar la base de datos
    $db = Ventas_DB::get_instance();
    $db->create_tables();
    $db->update_tables_for_warehouses();

    // Crear directorio de caché para TCPDF
    $cache_dir = WP_CONTENT_DIR . '/uploads/tcpdf-cache/';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
}
register_activation_hook(__FILE__, 'ventas_activate');

// Iniciar sesión si no está iniciada
function ventas_init_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}
add_action('init', 'ventas_init_session');

// Incluir archivos necesarios
require_once VENTAS_PLUGIN_DIR . 'includes/class-ventas-logger.php';
require_once VENTAS_PLUGIN_DIR . 'includes/class-ventas-messages.php';
require_once VENTAS_PLUGIN_DIR . 'includes/db.php';
require_once VENTAS_PLUGIN_DIR . 'includes/class-cotizaciones-list-table.php';
require_once VENTAS_PLUGIN_DIR . 'includes/helpers.php';
require_once VENTAS_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once VENTAS_PLUGIN_DIR . 'includes/admin-menu.php';
require_once VENTAS_PLUGIN_DIR . 'includes/cotizaciones.php';
require_once VENTAS_PLUGIN_DIR . 'includes/ventas.php';
require_once VENTAS_PLUGIN_DIR . 'includes/class-ventas-messages.php';

// DEBUG: Verificar clases cargadas
error_log('Ventas Plugin - Clases disponibles:');
error_log('Ventas_DB: ' . (class_exists('Ventas_DB') ? 'SI' : 'NO'));
error_log('Ventas_Logger: ' . (class_exists('Ventas_Logger') ? 'SI' : 'NO'));
error_log('Gestion_Almacenes_DB: ' . (class_exists('Gestion_Almacenes_DB') ? 'SI' : 'NO'));

// Cargar TCPDF solo si existe
if (file_exists(VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php')) {
    require_once VENTAS_PLUGIN_DIR . 'includes/tcpdf-config.php';
    require_once VENTAS_PLUGIN_DIR . 'includes/pdf-generator.php';
}

// Mostrar mensajes en el admin
function ventas_admin_messages() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'ventas') !== false) {
        Ventas_Messages::get_instance()->display_messages();
    }
}
add_action('admin_notices', 'ventas_admin_messages');

// Cargar traducciones
function ventas_load_textdomain() {
    load_plugin_textdomain('ventas', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'ventas_load_textdomain');

/**
 * Inicializar integración con almacenes
 */
/**
 * Inicializar integración con almacenes
 */
function ventas_init_warehouse_integration() {
    error_log('=== INIT WAREHOUSE INTEGRATION ===');
    error_log('Gestion_Almacenes_DB exists: ' . (class_exists('Gestion_Almacenes_DB') ? 'YES' : 'NO'));
    error_log('Ventas_DB exists: ' . (class_exists('Ventas_DB') ? 'YES' : 'NO'));
    
    // Solo cargar si el plugin de almacenes está activo Y la clase DB está disponible
    if (class_exists('Gestion_Almacenes_DB') && class_exists('Ventas_DB')) {
        error_log('Clases necesarias disponibles, cargando integración...');
        
        // Cargar la clase de integración solo cuando sea necesario
        $integration_file = VENTAS_PLUGIN_DIR . 'includes/class-ventas-almacenes-integration.php';
        error_log('Buscando archivo: ' . $integration_file);
        error_log('Archivo existe: ' . (file_exists($integration_file) ? 'YES' : 'NO'));
        
        if (file_exists($integration_file)) {
            require_once $integration_file;
            error_log('Archivo incluido');
            
            if (class_exists('Ventas_Almacenes_Integration')) {
                error_log('Clase Ventas_Almacenes_Integration disponible');
                $instance = Ventas_Almacenes_Integration::get_instance();
                error_log('Instancia creada: ' . (is_object($instance) ? 'YES' : 'NO'));
            } else {
                error_log('ERROR: Clase Ventas_Almacenes_Integration NO disponible después de incluir archivo');
            }
        }
    } else {
        error_log('Clases necesarias NO disponibles');
    }
}
// Usar prioridad más alta para asegurar que todo esté cargado
add_action('plugins_loaded', 'ventas_init_warehouse_integration', 50);

// Mover la verificación de WooCommerce al hook plugins_loaded
remove_action('admin_init', 'ventas_check_woocommerce');
add_action('plugins_loaded', 'ventas_check_woocommerce');

// AGREGAR ESTE CÓDIGO TEMPORALMENTE en ventas.php AL FINAL, ANTES del último
// Solo para depuración

// Debug: Verificar que los handlers AJAX estén registrados
add_action('wp_ajax_ventas_get_stock_almacenes', function() {
    error_log('AJAX Handler ventas_get_stock_almacenes llamado');
    
    // Verificar si la clase de integración existe
    if (!class_exists('Ventas_Almacenes_Integration')) {
        error_log('ERROR: Clase Ventas_Almacenes_Integration no encontrada');
        wp_send_json_error('Clase de integración no encontrada');
        return;
    }
    
    // Verificar si el método existe
    $integration = Ventas_Almacenes_Integration::get_instance();
    if (!method_exists($integration, 'ajax_get_stock_almacenes')) {
        error_log('ERROR: Método ajax_get_stock_almacenes no encontrado');
        wp_send_json_error('Método no encontrado');
        return;
    }
    
    // Si todo está bien, llamar al método
    $integration->ajax_get_stock_almacenes();
}, 5); // Prioridad alta para que se ejecute antes

// También agregar un test simple
add_action('wp_ajax_test_almacenes', function() {
    error_log('Test almacenes llamado');
    wp_send_json_success([
        'mensaje' => 'Handler funcionando',
        'clase_integration' => class_exists('Ventas_Almacenes_Integration'),
        'clase_gestion' => class_exists('Gestion_Almacenes_DB')
    ]);
});