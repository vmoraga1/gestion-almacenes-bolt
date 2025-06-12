<?php
if (!defined('ABSPATH')) {
    exit;
}

ventas_check_permissions();

$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$logger = Ventas_Logger::get_instance(); // Obtener la instancia del logger

$cotizacion = get_cotizacion($cotizacion_id);

$logger->log("ver-cotizacion.php - *** get_cotizacion() devolvió: " . print_r($cotizacion, true), 'debug'); // <-- Agrega este log

// Validación mejorada
if (empty($cotizacion) || !is_array($cotizacion)) {
    $logger->log("Cotización no encontrada: ID {$cotizacion_id}", 'error');
    wp_die('Cotización no encontrada o inválida');
}

// Validar que el cliente existe
$cliente = get_user_by('id', $cotizacion['cliente_id']);
if (!$cliente) {
    wp_die('Cliente no encontrado');
}

//$items = get_items_cotizacion($cotizacion_id);
$estados = ventas_get_estados_cotizacion();

$logger = Ventas_Logger::get_instance();
//if (!$cotizacion || !is_object($cotizacion)) {
//    $logger->log("Cotización no encontrada: ID {$cotizacion_id}", 'error');
//    wp_die('Cotización no encontrada o inválida');
//}
?>

<div class="wrap cotizacion-detalle">
    <h1>
        Cotización #<?php echo esc_html($cotizacion['folio']); ?>
        <?php if (isset($cotizacion['estado']) && isset($estados[$cotizacion['estado']])): ?>
        <span class="estado-badge estado-<?php echo esc_attr($cotizacion['estado']); ?>">
            <?php echo esc_html($estados[$cotizacion['estado']]); ?>
        </span>
        <?php endif; ?>
    </h1>

    <div class="cotizacion-header">
        <div class="info-empresa">
            <h3>Datos de la Empresa</h3>
            <p><?php echo esc_html(get_bloginfo('name')); ?></p>
            <p><?php echo esc_html(get_option('woocommerce_store_address')); ?></p>
            <p>Teléfono: <?php echo esc_html(get_option('woocommerce_store_phone')); ?></p>
        </div>

        <div class="info-cotizacion">
            <h3>Información de Cotización</h3>
            <?php if (isset($cotizacion['fecha'])): ?>
            <p><strong>Fecha:</strong> <?php echo date_i18n(get_option('date_format'), strtotime($cotizacion['fecha'])); ?>
            <?php endif; ?>
        </div>

        <div class="info-cliente">
            <h3>Datos del Cliente</h3>
            <?php if ($cliente): ?>
                <p><strong>Nombre:</strong> <?php echo esc_html($cliente->display_name); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($cliente->user_email); ?></p>
                <?php 
                $telefono = get_user_meta($cliente->ID, 'billing_phone', true);
                if ($telefono): ?>
                    <p><strong>Teléfono:</strong> <?php echo esc_html($telefono); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="cotizacion-items">
        <h3>Productos</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Descuento</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cotizacion['items'] as $item): 
                    $producto = wc_get_product($item['producto_id']);
                    if (!$producto) continue;
                ?>
                    <tr>
                        <td><?php echo esc_html($producto->get_sku()); ?></td>
                        <td><?php echo esc_html($producto->get_name()); ?></td>
                        <td><?php echo esc_html($item['cantidad']); ?></td>
                        <td><?php echo ventas_format_price($item['precio_unitario']); ?></td>
                        <td><?php echo ventas_format_price($item['descuento']); ?></td>
                        <td><?php echo ventas_format_price($item['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cotizacion-totales">
        <table class="wp-list-table widefat">
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td><?php echo ventas_format_price($cotizacion['subtotal']); ?></td>
            </tr>
            <?php if (isset($cotizacion['descuento']) && $cotizacion['descuento'] > 0): ?>
            <tr>
                <td><strong>Descuento Global:</strong></td>
                <td><?php echo ventas_format_price($cotizacion['descuento']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (isset($cotizacion['envio']) && $cotizacion['envio'] > 0): ?>
            <tr>
                <td><strong>Envío:</strong></td>
                <td><?php echo ventas_format_price($cotizacion['envio']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td><strong>Total Final:</strong></td>
                <td><?php echo ventas_format_price($cotizacion['total']); ?></td>
            </tr>
        </table>
    </div>

    <div class="cotizacion-acciones">
        <a href="<?php echo admin_url('admin.php?page=ventas-cotizaciones&action=pdf&id=' . $cotizacion_id); ?>" 
            class="button" target="_blank">
            Generar PDF
        </a>
        
        <?php if (isset($cotizacion['estado']) && $cotizacion['estado'] === 'pendiente'): ?>
            <button type="button" class="button button-primary convertir-venta" 
                    data-cotizacion-id="<?php echo esc_attr($cotizacion_id); ?>">
                Convertir a Venta
            </button>
        <?php endif; ?>
        
        <a href="<?php echo admin_url('admin.php?page=ventas-cotizaciones'); ?>" class="button">
            Volver al Listado
        </a>
    </div>
</div>