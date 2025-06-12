<?php
if (!defined('ABSPATH')) {
    exit;
}

function convertir_cotizacion_a_venta($cotizacion_id, $estado_pedido = 'wc-pending') {
    global $wpdb;
    $logger = Ventas_Logger::get_instance();
    
    $logger->info('Iniciando conversión de cotización', [
        'cotizacion_id' => $cotizacion_id,
        'estado_pedido' => $estado_pedido
    ]);
    
    // Obtener datos de la cotización
    $cotizacion = get_cotizacion($cotizacion_id);
    if (!$cotizacion || $cotizacion['estado'] !== 'pendiente') {
        $logger->error('Cotización inválida', [
            'cotizacion_id' => $cotizacion_id,
            'estado' => $cotizacion ? $cotizacion['estado'] : 'no existe'
        ]);
        return new WP_Error('cotizacion_invalida', 'La cotización no existe o ya ha sido procesada');
    }
    
    // Obtener items
    $items = isset($cotizacion['items']) ? $cotizacion['items'] : array();
    
    // Validar disponibilidad antes de crear el pedido
    if (class_exists('Ventas_Almacenes_Integration')) {
        $integration = Ventas_Almacenes_Integration::get_instance();
        if ($integration->is_warehouse_plugin_active()) {
            $validacion = $integration->validar_disponibilidad_cotizacion($cotizacion_id);
            if ($validacion !== true) {
                $logger->error('Stock insuficiente para convertir', [
                    'cotizacion_id' => $cotizacion_id,
                    'errores' => $validacion
                ]);
                return new WP_Error('stock_insuficiente', implode('<br>', $validacion));
            }
        }
    }

    try {
        // Crear el pedido
        $order = wc_create_order([
            'customer_id' => $cotizacion['cliente_id'],
            'status' => $estado_pedido
        ]);

        // Agregar productos al pedido
        foreach ($items as $item) {
            $producto = wc_get_product($item['producto_id']);
            if (!$producto) continue;

            // Agregar producto al pedido
            $order_item_id = $order->add_product($producto, $item['cantidad'], [
                'subtotal' => $item['precio_unitario'] * $item['cantidad'],
                'total' => $item['total'],
            ]);
            
            // Guardar información del almacén en los metadatos del item
            if (!empty($item['almacen_id']) && $order_item_id) {
                wc_add_order_item_meta($order_item_id, '_almacen_id', $item['almacen_id']);
                wc_add_order_item_meta($order_item_id, '_almacen_nombre', $item['almacen_nombre']);
            }
        }

        // Aplicar descuentos y envío
        if ($cotizacion['descuento'] > 0) {
            $order->add_coupon('DESCUENTO-' . $cotizacion['folio'], $cotizacion['descuento']);
        }
        
        if ($cotizacion['envio'] > 0) {
            $order->set_shipping_total($cotizacion['envio']);
        }

        // Actualizar totales
        $order->calculate_totals();

        // Agregar nota con referencia a la cotización
        $order->add_order_note(
            sprintf('Pedido creado desde la cotización %s', $cotizacion['folio'])
        );

        // Si el plugin de almacenes está activo, manejar el stock
        if (class_exists('Ventas_Almacenes_Integration')) {
            $integration = Ventas_Almacenes_Integration::get_instance();
            if ($integration->is_warehouse_plugin_active()) {
                // Reducir stock de los almacenes específicos
                $integration->reducir_stock_almacen($cotizacion_id, $order->get_id());
                
                // Agregar nota sobre los almacenes utilizados
                $nota_almacenes = "Stock reducido de los siguientes almacenes:\n";
                foreach ($items as $item) {
                    if (!empty($item['almacen_nombre'])) {
                        $nota_almacenes .= sprintf(
                            "- %s: %d unidades de %s\n",
                            $item['almacen_nombre'],
                            $item['cantidad'],
                            get_the_title($item['producto_id'])
                        );
                    }
                }
                $order->add_order_note($nota_almacenes);
            }
        }

        // Actualizar estado de la cotización
        $wpdb->update(
            $wpdb->prefix . 'ventas_cotizaciones',
            ['estado' => 'convertida'],
            ['id' => $cotizacion_id],
            ['%s'],
            ['%d']
        );

        $logger->info('Cotización convertida exitosamente', [
            'cotizacion_id' => $cotizacion_id,
            'order_id' => $order->get_id()
        ]);

        return $order->get_id();

    } catch (Exception $e) {
        $logger->error('Error al convertir cotización', [
            'cotizacion_id' => $cotizacion_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return new WP_Error('error_conversion', $e->getMessage());
    }
}

// Manejar la acción de conversión vía AJAX
add_action('wp_ajax_convertir_cotizacion', 'ajax_convertir_cotizacion');
function ajax_convertir_cotizacion() {
    check_ajax_referer('ventas_nonce', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        Ventas_Messages::get_instance()->add_message('No tienes permisos suficientes para realizar esta acción.', 'error');
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }

    $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
    $estado_pedido = isset($_POST['estado_pedido']) ? sanitize_text_field($_POST['estado_pedido']) : 'wc-pending';

    $resultado = convertir_cotizacion_a_venta($cotizacion_id, $estado_pedido);

    if (is_wp_error($resultado)) {
        Ventas_Messages::get_instance()->add_message($resultado->get_error_message(), 'error');
        wp_send_json_error(['message' => $resultado->get_error_message()]);
    } else {
        Ventas_Messages::get_instance()->add_message(
            sprintf('Cotización convertida exitosamente. Orden #%d creada.', $resultado),
            'success'
        );
        wp_send_json_success([
            'message' => 'Cotización convertida exitosamente',
            'order_id' => $resultado
        ]);
    }
}

