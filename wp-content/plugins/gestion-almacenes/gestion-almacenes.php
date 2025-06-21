<?php
/**
 * Plugin Name: Gestión de Almacenes / Bodegas / Tiendas
 * Plugin URI: https://hostpanish.com
 * Description: Gestión de Stock para multiples "Almacenes"
 * Version: 1.4.2
 * Author: Víctor Moraga
 * Author URI: https://hostpanish.com
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GESTION_ALMACENES_VERSION', '1.4.2');
define('GESTION_ALMACENES_DB_VERSION', '1.1.0');
define('GESTION_ALMACENES_PLUGIN_BASENAME', plugin_basename(__FILE__));
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
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-movements.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-csv-handler.php';
require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-product-columns.php';

// Variables globales para las instancias
global $gestion_almacenes_db, $gestion_almacenes_admin, $gestion_almacenes_woocommerce, $gestion_almacenes_stock_sync, $gestion_almacenes_sales_manager, $gestion_almacenes_order_display, $gestion_almacenes_movements, $gestion_almacenes_csv_handler, $gestion_almacenes_product_columns;

// Instanciar clases principales
$gestion_almacenes_db = new Gestion_Almacenes_DB();
$gestion_almacenes_admin = new Gestion_Almacenes_Admin();
$gestion_almacenes_woocommerce = new Gestion_Almacenes_WooCommerce();
$gestion_almacenes_stock_sync = new Gestion_Almacenes_Stock_Sync_Manager($gestion_almacenes_db);
$gestion_almacenes_sales_manager = new Gestion_Almacenes_Sales_Stock_Manager($gestion_almacenes_db);
$gestion_almacenes_order_display = new Gestion_Almacenes_Order_Display($gestion_almacenes_db);
$gestion_almacenes_movements = new Gestion_Almacenes_Movements($gestion_almacenes_db);
$gestion_almacenes_csv_handler = new Gestion_Almacenes_CSV_Handler($gestion_almacenes_db);
$gestion_almacenes_product_columns = new Gestion_Almacenes_Product_Columns($gestion_almacenes_db);


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

// Hook de activación mejorado
register_activation_hook(__FILE__, 'gab_plugin_activation');
function gab_plugin_activation() {
    global $gestion_almacenes_db;
    
    // Asegurarse de que la clase esté cargada
    if (!class_exists('Gestion_Almacenes_DB')) {
        require_once GESTION_ALMACENES_PLUGIN_DIR . 'includes/class-gestion-almacenes-db.php';
        $gestion_almacenes_db = new Gestion_Almacenes_DB();
    }
    
    // Ejecutar la creación de tablas
    $result = $gestion_almacenes_db->activar_plugin();
    
    // Verificar versión de la base de datos
    $gestion_almacenes_db->verificar_version_db();
    
    // Configurar opciones por defecto
    gab_set_default_options();
    
    // Registrar la activación
    add_option('gab_activation_date', current_time('mysql'));
    
    // Forzar verificación de tablas en la próxima carga
    set_transient('gab_check_tables', true, 60);
}

add_action('plugins_loaded', 'gab_check_db_version');
function gab_check_db_version() {
    global $gestion_almacenes_db;
    
    // Verificar si necesita actualización
    $version_actual = get_option('gab_db_version', '0');
    
    if (version_compare($version_actual, GESTION_ALMACENES_DB_VERSION, '<')) {
        if ($gestion_almacenes_db) {
            $gestion_almacenes_db->verificar_version_db();
        }
    }
}

// Verificación automática de tablas al cargar el admin
add_action('admin_init', 'gab_check_tables_integrity');
function gab_check_tables_integrity() {
    // Solo verificar si el transient está activo o cada 24 horas
    if (!get_transient('gab_tables_checked') || get_transient('gab_check_tables')) {
        global $gestion_almacenes_db;
        
        if ($gestion_almacenes_db) {
            $tablas_faltantes = $gestion_almacenes_db->verificar_tablas();
            
            if (count($tablas_faltantes) > 0) {
                // Intentar reparar automáticamente
                $repair_result = $gestion_almacenes_db->reparar_tablas_faltantes();
                
                if (!$repair_result['success']) {
                    // Mostrar aviso al admin
                    add_action('admin_notices', function() use ($tablas_faltantes) {
                        echo '<div class="notice notice-error is-dismissible">';
                        echo '<p><strong>Gestión de Almacenes:</strong> ';
                        echo 'Faltan las siguientes tablas: ' . implode(', ', $tablas_faltantes) . '. ';
                        echo 'Por favor, desactiva y vuelve a activar el plugin.';
                        echo '</p></div>';
                    });
                }
            }
            
            // Marcar como verificado por 24 horas
            set_transient('gab_tables_checked', true, DAY_IN_SECONDS);
            delete_transient('gab_check_tables');
        }
    }
}

// Agregar enlace de "Reparar tablas" en la página de plugins
add_filter('plugin_action_links_' . GESTION_ALMACENES_PLUGIN_BASENAME, 'gab_add_repair_link');
function gab_add_repair_link($links) {
    $repair_link = '<a href="' . wp_nonce_url(admin_url('admin.php?page=gab-warehouse-management&gab_repair_tables=1'), 'gab_repair_tables') . '">' . __('Reparar tablas', 'gestion-almacenes') . '</a>';
    array_unshift($links, $repair_link);
    return $links;
}

// Manejar la reparación manual
add_action('admin_init', 'gab_handle_repair_tables');
function gab_handle_repair_tables() {
    if (isset($_GET['gab_repair_tables']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'gab_repair_tables') && current_user_can('manage_options')) {
            global $gestion_almacenes_db;
            
            $result = $gestion_almacenes_db->crear_tablas_plugin();
            
            if ($result['success']) {
                wp_redirect(admin_url('admin.php?page=gab-warehouse-management&repair=success'));
            } else {
                wp_redirect(admin_url('admin.php?page=gab-warehouse-management&repair=error'));
            }
            exit;
        }
    }
    
    // Mostrar mensaje después de reparación
    if (isset($_GET['repair'])) {
        add_action('admin_notices', function() {
            $type = $_GET['repair'] === 'success' ? 'success' : 'error';
            $message = $_GET['repair'] === 'success' 
                ? 'Las tablas se han reparado correctamente.' 
                : 'Hubo un error al reparar las tablas. Revisa los logs.';
            
            echo '<div class="notice notice-' . $type . ' is-dismissible">';
            echo '<p><strong>Gestión de Almacenes:</strong> ' . $message . '</p>';
            echo '</div>';
        });
    }
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

// Debug temporal para verificar que el CSV handler se está cargando
add_action('init', function() {
    if (is_admin() && isset($_GET['debug_gab_csv'])) {
        global $gestion_almacenes_csv_handler, $gestion_almacenes_db;
        
        echo '<pre>';
        echo 'CSV Handler cargado: ' . (isset($gestion_almacenes_csv_handler) ? 'SÍ' : 'NO') . "\n";
        echo 'DB cargada: ' . (isset($gestion_almacenes_db) ? 'SÍ' : 'NO') . "\n";
        
        if (isset($gestion_almacenes_db)) {
            $almacenes = $gestion_almacenes_db->get_warehouses();
            echo 'Almacenes encontrados: ' . count($almacenes) . "\n";
            foreach ($almacenes as $almacen) {
                echo " - ID: {$almacen->id}, Nombre: {$almacen->name}\n";
            }
        }
        echo '</pre>';
        die();
    }
});