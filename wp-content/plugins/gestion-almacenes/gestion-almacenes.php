<?php
/**
 * Plugin Name: Gestión de Almacenes / Bodegas / Tiendas
 * Plugin URI: https://hostpanish.com
 * Description: Gestión de Stock para multiples "Almacenes"
 * Version: 1.0.0
 * Author: Víctor Moraga
 * Author URI: https://hostpanish.com
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
if (!defined('GESTION_ALMACENES_PLUGIN_DIR')) {
    define('GESTION_ALMACENES_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('GESTION_ALMACENES_PLUGIN_URL')) {
    define('GESTION_ALMACENES_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Cargar archivos principales
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-db.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/class-gestion-almacenes-admin.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'woocommerce/class-gestion-almacenes-woocommerce.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/views/page-nuevo-almacen.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/views/page-almacenes-list.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-transfer-controller.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-stock-sync-manager.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-sales-stock-manager.php';

// Variables globales para las instancias
global $gestion_almacenes_db, $gestion_almacenes_admin, $gestion_almacenes_woocommerce, $gestion_almacenes_stock_sync, $gestion_almacenes_sales_manager, $gestion_almacenes_order_display;

// Instanciar clases principales
$gestion_almacenes_db = new Gestion_Almacenes_DB();
$gestion_almacenes_admin = new Gestion_Almacenes_Admin();
$gestion_almacenes_woocommerce = new Gestion_Almacenes_WooCommerce();
$gestion_almacenes_stock_sync = new Gestion_Almacenes_Stock_Sync_Manager($gestion_almacenes_db);
$gestion_almacenes_sales_manager = new Gestion_Almacenes_Sales_Stock_Manager($gestion_almacenes_db);
$gestion_almacenes_order_display = new Gestion_Almacenes_Order_Display($gestion_almacenes_db);

// Registrar hook de activación
register_activation_hook(__FILE__, array($gestion_almacenes_db, 'activar_plugin'));

// Hook para inicializar las clases y sus hooks
add_action('plugins_loaded', 'gestion_almacenes_init');
function gestion_almacenes_init() {
    global $gestion_almacenes_db;
    
    // Crear tablas de transferencias
    $gestion_almacenes_db->crear_tablas_transferencias();
    
    // Cargar archivos de idioma
    load_plugin_textdomain('gestion-almacenes', false, GESTION_ALMACENES_PLUGIN_DIR . '/languages/');
}

// Configurar opciones por defecto en la activación
register_activation_hook(__FILE__, 'gab_set_default_options');
function gab_set_default_options() {
    add_option('gab_low_stock_threshold', 5);
    add_option('gab_auto_select_warehouse', 0);
    add_option('gab_default_warehouse', '');
    add_option('gab_manage_wc_stock', 'yes');
    add_option('gab_auto_sync_stock', 'yes');
    add_option('gab_default_sales_warehouse', '');
    add_option('gab_stock_allocation_method', 'priority');
    add_option('gab_allow_partial_fulfillment', 'yes');
    add_option('gab_notify_low_stock_on_sale', 'yes');
    add_option('gab_low_stock_email', get_option('admin_email'));
}

// Función auxiliar para obtener la instancia del stock sync manager
function gab_get_stock_sync_manager() {
    global $gestion_almacenes_stock_sync;
    return $gestion_almacenes_stock_sync;
}