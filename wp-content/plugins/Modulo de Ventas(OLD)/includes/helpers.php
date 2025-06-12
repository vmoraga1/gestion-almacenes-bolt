<?php
if (!defined('ABSPATH')) {
    exit;
}

function ventas_get_estados_cotizacion() {
    return array(
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'vencida' => 'Vencida',
        'convertida' => 'Convertida a Venta'
    );
}

function ventas_format_price($price) {
    return wc_price($price, [
        'decimals' => wc_get_price_decimals(),
        'price_format' => get_woocommerce_price_format()
    ]);
}

function ventas_check_permissions() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'ventas'));
    }
}