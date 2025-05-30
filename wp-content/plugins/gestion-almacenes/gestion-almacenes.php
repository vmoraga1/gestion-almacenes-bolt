<?php
/*
Plugin Name: Gestión de Almacenes / Bodegas / Tiendas
Plugin URI: https://hostpanish.com
Description: Gestión de Stock para multiples "Almacenes"
Version: 1.0.0
Author: Víctor Moraga
Author URI: https://hostpanish.com
*/

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
if ( ! defined( 'GESTION_ALMACENES_PLUGIN_DIR' ) ) {
    define( 'GESTION_ALMACENES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'GESTION_ALMACENES_PLUGIN_URL' ) ) {
    define( 'GESTION_ALMACENES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Cargar archivos principales
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-db.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/class-gestion-almacenes-admin.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'woocommerce/class-gestion-almacenes-woocommerce.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/views/page-nuevo-almacen.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/views/page-almacenes-list.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-transfer-controller.php';

// Instanciar clases principales (esto las "activa" y engancha sus hooks)
$gestion_almacenes_db = new Gestion_Almacenes_DB();
$gestion_almacenes_admin = new Gestion_Almacenes_Admin();
$gestion_almacenes_woocommerce = new Gestion_Almacenes_WooCommerce();

// Registrar hook de activación - ahora llama a un método de la clase DB
register_activation_hook(__FILE__, array($gestion_almacenes_db, 'activar_plugin'));

// Registrar hook de desactivación (si planeas tener uno para limpiar al desactivar)
// register_deactivation_hook(__FILE__, array($gestion_almacenes_db, 'desactivar_plugin'));

// Hook para inicializar las clases y sus hooks - opcional, pero buena práctica
add_action('plugins_loaded', 'gestion_almacenes_init');
function gestion_almacenes_init() {
    global $gestion_almacenes_admin, $gestion_almacenes_woocommerce;

    // Asegúrate de que las clases se instancian solo una vez si usas este hook
    // o puedes instanciarlas directamente como arriba y asegurarte de que sus constructores solo añaden hooks una vez.
    // Para este ejemplo simple, las instancias directas al principio son suficientes.
    global $gestion_almacenes_db;
    $gestion_almacenes_db->crear_tablas_transferencias();

    // Aquí podrías cargar archivos de idioma si los tuvieras
    load_plugin_textdomain( 'gestion-almacenes', false, GESTION_ALMACENES_PLUGIN_DIR . '/languages/' );
}

// Configurar opciones por defecto en la activación
register_activation_hook(__FILE__, 'gab_set_default_options');
function gab_set_default_options() {
    add_option('gab_low_stock_threshold', 5);
    add_option('gab_auto_select_warehouse', 0);
    add_option('gab_default_warehouse', '');
}