<?php
if (!defined('ABSPATH')) {
    exit;
}

// Agregar menú al panel de administración
function ventas_admin_menu() {
    add_menu_page(
        'Sistema de Ventas',
        'Ventas',
        'manage_woocommerce',
        'ventas-cotizaciones',
        'ventas_cotizaciones_page',
        'dashicons-cart',
        56
    );

    add_submenu_page(
        'ventas-cotizaciones',
        'Cotizaciones',
        'Cotizaciones',
        'manage_woocommerce',
        'ventas-cotizaciones',
        'ventas_cotizaciones_page'
    );

    add_submenu_page(
        'ventas-cotizaciones',
        'Nueva Cotización',
        'Nueva Cotización',
        'manage_woocommerce',
        'ventas-nueva-cotizacion',
        'ventas_nueva_cotizacion_page'
    );

    add_submenu_page(
        'ventas-cotizaciones',
        'Registros del Sistema',
        'Registros',
        'manage_woocommerce',
        'ventas-logs',
        'ventas_logs_page'
    );

    function ventas_logs_page() {
        require_once VENTAS_PLUGIN_DIR . 'views/logs-viewer.php';
    }
}
add_action('admin_menu', 'ventas_admin_menu');

// Páginas del plugin
function ventas_cotizaciones_page() {
    if (isset($_GET['action']) && $_GET['action'] === 'ver' && isset($_GET['id'])) {
        require_once VENTAS_PLUGIN_DIR . 'views/ver-cotizacion.php';
    } else {
        require_once VENTAS_PLUGIN_DIR . 'views/lista-cotizaciones.php';
    }
}

function ventas_nueva_cotizacion_page() {
    require_once VENTAS_PLUGIN_DIR . 'views/nueva-cotizacion.php';
}