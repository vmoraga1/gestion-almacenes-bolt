<?php
/**
 * Clase para manejar las cotizaciones del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Cotizaciones {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Modulo_Ventas_DB();
        $this->logger = Modulo_Ventas_Logger::get_instance();
        
        // Hooks
        add_action('init', array($this, 'programar_verificacion_expiracion'));
        add_action('modulo_ventas_verificar_cotizaciones_expiradas', array($this, 'verificar_cotizaciones_expiradas'));
        
        // Ajax handlers
        /*add_action('wp_ajax_mv_obtener_stock_producto', array($this, 'ajax_obtener_stock_producto'));
        add_action('wp_ajax_mv_guardar_cotizacion', array($this, 'ajax_guardar_cotizacion'));
        add_action('wp_ajax_mv_actualizar_estado_cotizacion', array($this, 'ajax_actualizar_estado_cotizacion'));
        add_action('wp_ajax_mv_duplicar_cotizacion', array($this, 'ajax_duplicar_cotizacion'));
        add_action('wp_ajax_mv_convertir_cotizacion', array($this, 'ajax_convertir_cotizacion'));
        add_action('wp_ajax_mv_enviar_cotizacion_email', array($this, 'ajax_enviar_cotizacion_email'));
        add_action('wp_ajax_mv_obtener_almacenes', array($this, 'ajax_obtener_almacenes'));
        add_action('wp_ajax_mv_calcular_totales', array($this, 'ajax_calcular_totales'));*/
    }
    
    /**
     * Programar verificación de cotizaciones expiradas
     */
    public function programar_verificacion_expiracion() {
        if (!wp_next_scheduled('modulo_ventas_verificar_cotizaciones_expiradas')) {
            wp_schedule_event(time(), 'daily', 'modulo_ventas_verificar_cotizaciones_expiradas');
        }
    }
    
    /**
     * Verificar y actualizar cotizaciones expiradas
     */
    public function verificar_cotizaciones_expiradas() {
        $actualizadas = $this->db->verificar_cotizaciones_expiradas();
        
        if ($actualizadas > 0) {
            $this->logger->log("Verificación automática: {$actualizadas} cotizaciones marcadas como expiradas", 'info');
        }
    }
    
    /**
     * Crear nueva cotización
     */
    public function crear_cotizacion($datos_generales, $items) {
        // Validar permisos
        if (!current_user_can('create_cotizaciones')) {
            return new WP_Error('sin_permisos', __('No tiene permisos para crear cotizaciones', 'modulo-ventas'));
        }
        
        // Validar stock disponible antes de crear
        $validacion_stock = $this->validar_stock_items($items);
        if (is_wp_error($validacion_stock)) {
            return $validacion_stock;
        }
        
        // Crear cotización en la base de datos
        $cotizacion_id = $this->db->crear_cotizacion($datos_generales, $items);
        
        if (is_wp_error($cotizacion_id)) {
            return $cotizacion_id;
        }
        
        // Reservar stock si está configurado
        if (get_option('modulo_ventas_reservar_stock', 'no') === 'yes') {
            $this->reservar_stock_cotizacion($cotizacion_id);
        }
        
        // Enviar notificaciones si está configurado
        if (get_option('modulo_ventas_notificar_nueva_cotizacion', 'yes') === 'yes') {
            $this->enviar_notificacion_nueva_cotizacion($cotizacion_id);
        }
        
        // Hook para extensiones
        do_action('modulo_ventas_despues_crear_cotizacion', $cotizacion_id, $datos_generales, $items);
        
        return $cotizacion_id;
    }
    
    /**
     * Actualizar cotización existente
     */
    public function actualizar_cotizacion($cotizacion_id, $datos_generales, $items) {
        // Validar permisos
        if (!current_user_can('edit_cotizaciones')) {
            return new WP_Error('sin_permisos', __('No tiene permisos para editar cotizaciones', 'modulo-ventas'));
        }
        
        // Obtener cotización actual
        $cotizacion_actual = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion_actual) {
            return new WP_Error('cotizacion_no_existe', __('La cotización no existe', 'modulo-ventas'));
        }
        
        // Verificar que no esté convertida
        if ($cotizacion_actual->estado === 'convertida') {
            return new WP_Error('cotizacion_convertida', __('No se puede editar una cotización convertida en venta', 'modulo-ventas'));
        }
        
        // Validar stock
        $validacion_stock = $this->validar_stock_items($items);
        if (is_wp_error($validacion_stock)) {
            return $validacion_stock;
        }
        
        // TODO: Implementar actualización en la BD
        // Por ahora, retornamos un mensaje de no implementado
        return new WP_Error('no_implementado', __('La actualización de cotizaciones está en desarrollo', 'modulo-ventas'));
    }
    
    /**
     * Validar stock disponible para los items
     */
    private function validar_stock_items($items) {
        // Verificar si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_DB')) {
            return true; // Si no está activo, usar stock de WooCommerce
        }
        
        global $gestion_almacenes_db;
        $errores = array();
        
        foreach ($items as $item) {
            $producto_id = $item['producto_id'];
            $variacion_id = isset($item['variacion_id']) ? $item['variacion_id'] : 0;
            $almacen_id = isset($item['almacen_id']) ? $item['almacen_id'] : null;
            $cantidad = $item['cantidad'];
            
            // Obtener producto
            $producto = wc_get_product($variacion_id ?: $producto_id);
            if (!$producto) {
                $errores[] = sprintf(__('Producto ID %d no encontrado', 'modulo-ventas'), $producto_id);
                continue;
            }
            
            // Si hay almacén específico, verificar stock del almacén
            if ($almacen_id && $gestion_almacenes_db) {
                $stock_almacen = $gestion_almacenes_db->get_product_warehouse_stock($variacion_id ?: $producto_id);
                $stock_disponible = isset($stock_almacen[$almacen_id]) ? $stock_almacen[$almacen_id] : 0;
                
                if ($stock_disponible < $cantidad) {
                    $almacen = $gestion_almacenes_db->get_warehouse($almacen_id);
                    $errores[] = sprintf(
                        __('Stock insuficiente para %s en almacén %s. Disponible: %d, Solicitado: %d', 'modulo-ventas'),
                        $producto->get_name(),
                        $almacen ? $almacen->name : 'ID ' . $almacen_id,
                        $stock_disponible,
                        $cantidad
                    );
                }
            } else {
                // Usar stock general de WooCommerce
                if ($producto->managing_stock() && $producto->get_stock_quantity() < $cantidad) {
                    $errores[] = sprintf(
                        __('Stock insuficiente para %s. Disponible: %d, Solicitado: %d', 'modulo-ventas'),
                        $producto->get_name(),
                        $producto->get_stock_quantity(),
                        $cantidad
                    );
                }
            }
        }
        
        if (!empty($errores)) {
            return new WP_Error('stock_insuficiente', implode('<br>', $errores));
        }
        
        return true;
    }
    
    /**
     * Reservar stock de una cotización
     */
    private function reservar_stock_cotizacion($cotizacion_id) {
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return false;
        }
        
        // TODO: Implementar sistema de reservas
        // Esto requeriría una tabla adicional para tracking de reservas
        
        $this->logger->log("Reserva de stock para cotización {$cotizacion->folio} pendiente de implementación", 'debug');
        
        return true;
    }
    
    /**
     * Liberar stock reservado
     */
    public function liberar_stock_cotizacion($cotizacion_id) {
        // TODO: Implementar cuando se tenga el sistema de reservas
        return true;
    }
    
    /**
     * Convertir cotización a pedido de WooCommerce
     */
    public function convertir_a_pedido($cotizacion_id) {
        // Validar permisos
        if (!current_user_can('edit_cotizaciones')) {
            return new WP_Error('sin_permisos', __('No tiene permisos para convertir cotizaciones', 'modulo-ventas'));
        }
        
        // Obtener cotización
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return new WP_Error('cotizacion_no_existe', __('La cotización no existe', 'modulo-ventas'));
        }
        
        // Verificar estado
        if ($cotizacion->estado === 'convertida') {
            return new WP_Error('ya_convertida', __('Esta cotización ya fue convertida', 'modulo-ventas'));
        }
        
        if ($cotizacion->estado === 'expirada') {
            return new WP_Error('cotizacion_expirada', __('No se puede convertir una cotización expirada', 'modulo-ventas'));
        }
        
        // Validar stock nuevamente
        $validacion_stock = $this->validar_stock_items($cotizacion->items);
        if (is_wp_error($validacion_stock)) {
            return $validacion_stock;
        }
        
        try {
            // Crear pedido de WooCommerce
            $order = wc_create_order();
            
            // Asignar cliente
            $cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
            if ($cliente && $cliente->user_id) {
                $order->set_customer_id($cliente->user_id);
            }
            
            // Agregar datos del cliente
            if ($cliente) {
                $order->set_billing_first_name($cliente->razon_social);
                $order->set_billing_company($cliente->razon_social);
                $order->set_billing_email($cliente->email);
                $order->set_billing_phone($cliente->telefono);
                $order->set_billing_address_1($cliente->direccion_facturacion);
                $order->set_billing_city($cliente->ciudad_facturacion);
                $order->set_billing_state($cliente->region_facturacion);
                $order->set_billing_country($cliente->pais_facturacion ?: 'CL');
                
                // Dirección de envío
                if (!$cliente->usar_direccion_facturacion_para_envio) {
                    $order->set_shipping_address_1($cliente->direccion_envio);
                    $order->set_shipping_city($cliente->ciudad_envio);
                    $order->set_shipping_state($cliente->region_envio);
                    $order->set_shipping_country($cliente->pais_envio ?: 'CL');
                } else {
                    $order->set_shipping_address_1($cliente->direccion_facturacion);
                    $order->set_shipping_city($cliente->ciudad_facturacion);
                    $order->set_shipping_state($cliente->region_facturacion);
                    $order->set_shipping_country($cliente->pais_facturacion ?: 'CL');
                }
            }
            
            // Agregar productos
            foreach ($cotizacion->items as $item) {
                $producto = wc_get_product($item->variacion_id ?: $item->producto_id);
                if (!$producto) {
                    throw new Exception(sprintf(__('Producto ID %d no encontrado', 'modulo-ventas'), $item->producto_id));
                }
                
                $order_item_id = $order->add_product($producto, $item->cantidad, array(
                    'subtotal' => $item->subtotal,
                    'total' => $item->total,
                ));
                
                // Agregar meta del almacén si existe
                if ($item->almacen_id && $order_item_id) {
                    wc_add_order_item_meta($order_item_id, '_almacen_id', $item->almacen_id);
                    
                    // Obtener nombre del almacén
                    if (class_exists('Gestion_Almacenes_DB')) {
                        global $gestion_almacenes_db;
                        $almacen = $gestion_almacenes_db->get_warehouse($item->almacen_id);
                        if ($almacen) {
                            wc_add_order_item_meta($order_item_id, '_almacen_nombre', $almacen->name);
                        }
                    }
                }
            }
            
            // Aplicar descuento global si existe
            if ($cotizacion->descuento_monto > 0) {
                $descuento = new WC_Order_Item_Fee();
                $descuento->set_name(__('Descuento', 'modulo-ventas'));
                $descuento->set_amount(-$cotizacion->descuento_monto);
                $descuento->set_total(-$cotizacion->descuento_monto);
                $order->add_item($descuento);
            }
            
            // Agregar costo de envío si existe
            if ($cotizacion->costo_envio > 0) {
                $shipping = new WC_Order_Item_Shipping();
                $shipping->set_method_title(__('Envío', 'modulo-ventas'));
                $shipping->set_total($cotizacion->costo_envio);
                $order->add_item($shipping);
            }
            
            // Agregar nota con referencia a la cotización
            $order->add_order_note(sprintf(
                __('Pedido creado desde cotización %s', 'modulo-ventas'),
                $cotizacion->folio
            ));
            
            // Agregar meta con referencia a la cotización
            $order->update_meta_data('_cotizacion_id', $cotizacion_id);
            $order->update_meta_data('_cotizacion_folio', $cotizacion->folio);
            
            // Calcular totales
            $order->calculate_totals();
            
            // Guardar pedido
            $order->save();
            
            // Actualizar estado de la cotización
            $this->db->actualizar_estado_cotizacion($cotizacion_id, 'convertida');
            
            // Actualizar referencia al pedido en la cotización
            global $wpdb;
            $wpdb->update(
                $this->db->get_tabla_cotizaciones(),
                array(
                    'venta_id' => $order->get_id(),
                    'fecha_conversion' => current_time('mysql')
                ),
                array('id' => $cotizacion_id)
            );
            
            // Liberar stock reservado si existe
            $this->liberar_stock_cotizacion($cotizacion_id);
            
            // Log
            $this->logger->log(
                sprintf('Cotización %s convertida a pedido #%d', $cotizacion->folio, $order->get_id()),
                'info'
            );
            
            // Hook para extensiones
            do_action('modulo_ventas_cotizacion_convertida', $cotizacion_id, $order->get_id());
            
            return $order->get_id();
            
        } catch (Exception $e) {
            $this->logger->log('Error al convertir cotización: ' . $e->getMessage(), 'error');
            return new WP_Error('error_conversion', $e->getMessage());
        }
    }
    
    /**
     * Duplicar cotización
     */
    public function duplicar_cotizacion($cotizacion_id) {
        // Validar permisos
        if (!current_user_can('create_cotizaciones')) {
            return new WP_Error('sin_permisos', __('No tiene permisos para duplicar cotizaciones', 'modulo-ventas'));
        }
        
        // Obtener cotización original
        $cotizacion_original = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion_original) {
            return new WP_Error('cotizacion_no_existe', __('La cotización no existe', 'modulo-ventas'));
        }
        
        // Preparar datos para la nueva cotización
        $datos_generales = array(
            'cliente_id' => $cotizacion_original->cliente_id,
            'fecha_expiracion' => null, // Nueva fecha
            'plazo_pago' => $cotizacion_original->plazo_pago,
            'condiciones_pago' => $cotizacion_original->condiciones_pago,
            'vendedor_id' => get_current_user_id(),
            'vendedor_nombre' => wp_get_current_user()->display_name,
            'almacen_id' => $cotizacion_original->almacen_id,
            'incluye_iva' => $cotizacion_original->incluye_iva,
            'descuento_monto' => $cotizacion_original->descuento_monto,
            'descuento_porcentaje' => $cotizacion_original->descuento_porcentaje,
            'tipo_descuento' => $cotizacion_original->tipo_descuento,
            'costo_envio' => $cotizacion_original->costo_envio,
            'observaciones' => $cotizacion_original->observaciones,
            'terminos_condiciones' => $cotizacion_original->terminos_condiciones,
        );
        
        // Preparar items
        $items = array();
        foreach ($cotizacion_original->items as $item) {
            $items[] = array(
                'producto_id' => $item->producto_id,
                'variacion_id' => $item->variacion_id,
                'almacen_id' => $item->almacen_id,
                'sku' => $item->sku,
                'nombre' => $item->nombre,
                'descripcion' => $item->descripcion,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario,
                'precio_original' => $item->precio_original,
                'descuento_monto' => $item->descuento_monto,
                'descuento_porcentaje' => $item->descuento_porcentaje,
                'tipo_descuento' => $item->tipo_descuento,
                'subtotal' => $item->subtotal,
                'notas' => $item->notas,
            );
        }
        
        // Crear nueva cotización
        $nueva_cotizacion_id = $this->crear_cotizacion($datos_generales, $items);
        
        if (is_wp_error($nueva_cotizacion_id)) {
            return $nueva_cotizacion_id;
        }
        
        // Agregar nota indicando que es duplicada
        global $wpdb;
        $wpdb->update(
            $this->db->get_tabla_cotizaciones(),
            array('notas_internas' => sprintf(__('Duplicada de cotización %s', 'modulo-ventas'), $cotizacion_original->folio)),
            array('id' => $nueva_cotizacion_id)
        );
        
        return $nueva_cotizacion_id;
    }
    
    /**
     * Generar PDF de cotización
     */
    public function generar_pdf($cotizacion_id) {
        // Obtener cotización
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return new WP_Error('cotizacion_no_existe', __('La cotización no existe', 'modulo-ventas'));
        }
        
        // Verificar si existe la clase PDF
        if (!class_exists('Modulo_Ventas_PDF')) {
            return new WP_Error('pdf_no_disponible', __('La generación de PDF no está disponible', 'modulo-ventas'));
        }
        
        $pdf_generator = new Modulo_Ventas_PDF();
        return $pdf_generator->generar_cotizacion($cotizacion);
    }
    
    /**
     * Enviar cotización por email
     */
    public function enviar_por_email($cotizacion_id, $email_destino = null) {
        // Obtener cotización
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return new WP_Error('cotizacion_no_existe', __('La cotización no existe', 'modulo-ventas'));
        }
        
        // Obtener cliente
        $cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
        if (!$cliente) {
            return new WP_Error('cliente_no_existe', __('El cliente no existe', 'modulo-ventas'));
        }
        
        // Usar email del cliente si no se especifica otro
        if (!$email_destino) {
            $email_destino = $cliente->email;
        }
        
        if (!is_email($email_destino)) {
            return new WP_Error('email_invalido', __('Email de destino inválido', 'modulo-ventas'));
        }
        
        // Generar PDF
        $pdf_path = $this->generar_pdf($cotizacion_id);
        if (is_wp_error($pdf_path)) {
            return $pdf_path;
        }
        
        // Preparar email
        $asunto = sprintf(
            __('Cotización %s - %s', 'modulo-ventas'),
            $cotizacion->folio,
            get_bloginfo('name')
        );
        
        $mensaje = $this->obtener_plantilla_email($cotizacion, $cliente);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        $attachments = array($pdf_path);
        
        // Enviar email
        $enviado = wp_mail($email_destino, $asunto, $mensaje, $headers, $attachments);
        
        // Eliminar PDF temporal
        if (file_exists($pdf_path)) {
            unlink($pdf_path);
        }
        
        if ($enviado) {
            // Log
            $this->logger->log(
                sprintf('Cotización %s enviada por email a %s', $cotizacion->folio, $email_destino),
                'info'
            );
            
            // Hook
            do_action('modulo_ventas_cotizacion_enviada', $cotizacion_id, $email_destino);
            
            return true;
        } else {
            return new WP_Error('error_envio', __('Error al enviar el email', 'modulo-ventas'));
        }
    }
    
    /**
     * Obtener plantilla de email
     */
    private function obtener_plantilla_email($cotizacion, $cliente) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f4f4f4; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f4f4f4; padding: 10px; text-align: center; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f4f4f4; }
                .total { font-size: 18px; font-weight: bold; color: #333; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo get_bloginfo('name'); ?></h1>
                    <h2><?php _e('Cotización', 'modulo-ventas'); ?> <?php echo esc_html($cotizacion->folio); ?></h2>
                </div>
                
                <div class="content">
                    <p><?php _e('Estimado/a', 'modulo-ventas'); ?> <strong><?php echo esc_html($cliente->razon_social); ?></strong>,</p>
                    
                    <p><?php _e('Adjunto encontrará nuestra cotización con el siguiente detalle:', 'modulo-ventas'); ?></p>
                    
                    <table>
                        <tr>
                            <th><?php _e('Fecha', 'modulo-ventas'); ?></th>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($cotizacion->fecha)); ?></td>
                        </tr>
                        <?php if ($cotizacion->fecha_expiracion) : ?>
                        <tr>
                            <th><?php _e('Válida hasta', 'modulo-ventas'); ?></th>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($cotizacion->fecha_expiracion)); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php _e('Total', 'modulo-ventas'); ?></th>
                            <td class="total"><?php echo wc_price($cotizacion->total); ?></td>
                        </tr>
                    </table>
                    
                    <?php if ($cotizacion->observaciones) : ?>
                    <h3><?php _e('Observaciones', 'modulo-ventas'); ?></h3>
                    <p><?php echo nl2br(esc_html($cotizacion->observaciones)); ?></p>
                    <?php endif; ?>
                    
                    <p><?php _e('Para cualquier consulta, no dude en contactarnos.', 'modulo-ventas'); ?></p>
                    
                    <p><?php _e('Saludos cordiales,', 'modulo-ventas'); ?><br>
                    <?php echo esc_html($cotizacion->vendedor_nombre); ?><br>
                    <?php echo get_bloginfo('name'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php echo get_bloginfo('name'); ?> | <?php echo get_option('admin_email'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enviar notificación de nueva cotización
     */
    private function enviar_notificacion_nueva_cotizacion($cotizacion_id) {
        $emails_notificacion = get_option('modulo_ventas_emails_notificacion', get_option('admin_email'));
        
        if (empty($emails_notificacion)) {
            return;
        }
        
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return;
        }
        
        $asunto = sprintf(
            __('Nueva cotización %s creada', 'modulo-ventas'),
            $cotizacion->folio
        );
        
        $mensaje = sprintf(
            __('Se ha creado una nueva cotización:\n\nFolio: %s\nCliente: %s\nTotal: %s\nVendedor: %s\n\nPuede verla en: %s', 'modulo-ventas'),
            $cotizacion->folio,
            $cotizacion->razon_social,
            wc_price($cotizacion->total),
            $cotizacion->vendedor_nombre,
            admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id)
        );
        
        wp_mail($emails_notificacion, $asunto, $mensaje);
    }
    
    /**
     * AJAX: Obtener stock de producto
     */
    public function ajax_obtener_stock_producto() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        $producto_id = isset($_POST['producto_id']) ? intval($_POST['producto_id']) : 0;
        $variacion_id = isset($_POST['variacion_id']) ? intval($_POST['variacion_id']) : 0;
        
        if (!$producto_id) {
            wp_send_json_error(array('message' => __('ID de producto inválido', 'modulo-ventas')));
        }
        
        $id_real = $variacion_id ?: $producto_id;
        $producto = wc_get_product($id_real);
        
        if (!$producto) {
            wp_send_json_error(array('message' => __('Producto no encontrado', 'modulo-ventas')));
        }
        
        $response = array(
            'stock_general' => $producto->get_stock_quantity(),
            'stock_por_almacen' => array()
        );
        
        // Obtener stock por almacén si está disponible
        if (class_exists('Gestion_Almacenes_DB')) {
            global $gestion_almacenes_db;
            
            $almacenes = $gestion_almacenes_db->obtener_almacenes();
            $stock_almacenes = $gestion_almacenes_db->get_product_warehouse_stock($id_real);
            
            foreach ($almacenes as $almacen) {
                $response['stock_por_almacen'][] = array(
                    'almacen_id' => $almacen->id,
                    'almacen_nombre' => $almacen->name,
                    'stock' => isset($stock_almacenes[$almacen->id]) ? $stock_almacenes[$almacen->id] : 0
                );
            }
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: Guardar cotización
     */
    public function ajax_guardar_cotizacion() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('create_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        // Validar datos
        if (!isset($_POST['datos_generales']) || !isset($_POST['items'])) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        $datos_generales = $_POST['datos_generales'];
        $items = $_POST['items'];
        
        // Crear cotización
        $cotizacion_id = $this->crear_cotizacion($datos_generales, $items);
        
        if (is_wp_error($cotizacion_id)) {
            wp_send_json_error(array(
                'message' => $cotizacion_id->get_error_message(),
                'errors' => $cotizacion_id->get_error_messages()
            ));
        }
        
        wp_send_json_success(array(
            'cotizacion_id' => $cotizacion_id,
            'redirect_url' => admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id),
            'message' => __('Cotización creada exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * AJAX: Actualizar estado de cotización
     */
    public function ajax_actualizar_estado_cotizacion() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('edit_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        $nuevo_estado = isset($_POST['estado']) ? sanitize_text_field($_POST['estado']) : '';
        
        if (!$cotizacion_id || !$nuevo_estado) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        $resultado = $this->db->actualizar_estado_cotizacion($cotizacion_id, $nuevo_estado);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Estado actualizado exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * AJAX: Duplicar cotización
     */
    public function ajax_duplicar_cotizacion() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('create_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $nueva_cotizacion_id = $this->duplicar_cotizacion($cotizacion_id);
        
        if (is_wp_error($nueva_cotizacion_id)) {
            wp_send_json_error(array('message' => $nueva_cotizacion_id->get_error_message()));
        }
        
        wp_send_json_success(array(
            'cotizacion_id' => $nueva_cotizacion_id,
            'redirect_url' => admin_url('admin.php?page=modulo-ventas-editar-cotizacion&id=' . $nueva_cotizacion_id),
            'message' => __('Cotización duplicada exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * AJAX: Convertir cotización a pedido
     */
    public function ajax_convertir_cotizacion() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('edit_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $pedido_id = $this->convertir_a_pedido($cotizacion_id);
        
        if (is_wp_error($pedido_id)) {
            wp_send_json_error(array('message' => $pedido_id->get_error_message()));
        }
        
        wp_send_json_success(array(
            'pedido_id' => $pedido_id,
            'pedido_url' => admin_url('post.php?post=' . $pedido_id . '&action=edit'),
            'message' => sprintf(__('Cotización convertida exitosamente. Pedido #%d creado', 'modulo-ventas'), $pedido_id)
        ));
    }
    
    /**
     * AJAX: Enviar cotización por email
     */
    public function ajax_enviar_cotizacion_email() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('view_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        $email_destino = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $resultado = $this->enviar_por_email($cotizacion_id, $email_destino);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Cotización enviada exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * AJAX: Obtener almacenes disponibles
     */
    public function ajax_obtener_almacenes() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('view_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $almacenes = array();
        
        // Verificar si el plugin de almacenes está activo
        if (class_exists('Gestion_Almacenes_DB')) {
            global $gestion_almacenes_db;
            $almacenes_db = $gestion_almacenes_db->obtener_almacenes();
            
            foreach ($almacenes_db as $almacen) {
                $almacenes[] = array(
                    'id' => $almacen->id,
                    'nombre' => $almacen->name,
                    'direccion' => $almacen->address,
                    'activo' => $almacen->is_active
                );
            }
        }
        
        wp_send_json_success(array('almacenes' => $almacenes));
    }
    
    /**
     * AJAX: Calcular totales
     */
    public function ajax_calcular_totales() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        $incluye_iva = isset($_POST['incluye_iva']) ? (bool)$_POST['incluye_iva'] : true;
        $descuento_global = isset($_POST['descuento_global']) ? floatval($_POST['descuento_global']) : 0;
        $tipo_descuento = isset($_POST['tipo_descuento']) ? $_POST['tipo_descuento'] : 'monto';
        $costo_envio = isset($_POST['costo_envio']) ? floatval($_POST['costo_envio']) : 0;
        
        $subtotal = 0;
        $items_calculados = array();
        
        // Calcular subtotal y totales por item
        foreach ($items as $item) {
            $cantidad = floatval($item['cantidad']);
            $precio_unitario = floatval($item['precio_unitario']);
            $descuento_item = isset($item['descuento']) ? floatval($item['descuento']) : 0;
            $tipo_descuento_item = isset($item['tipo_descuento']) ? $item['tipo_descuento'] : 'monto';
            
            // Calcular subtotal del item
            $subtotal_item = $cantidad * $precio_unitario;
            
            // Aplicar descuento del item
            if ($tipo_descuento_item === 'porcentaje') {
                $descuento_item_monto = $subtotal_item * ($descuento_item / 100);
            } else {
                $descuento_item_monto = $descuento_item;
            }
            
            $subtotal_item_con_descuento = $subtotal_item - $descuento_item_monto;
            
            // Calcular IVA del item si aplica
            $iva_item = $incluye_iva ? $subtotal_item_con_descuento * 0.19 : 0;
            $total_item = $subtotal_item_con_descuento + $iva_item;
            
            $items_calculados[] = array(
                'subtotal' => $subtotal_item,
                'descuento' => $descuento_item_monto,
                'subtotal_con_descuento' => $subtotal_item_con_descuento,
                'iva' => $iva_item,
                'total' => $total_item
            );
            
            $subtotal += $subtotal_item_con_descuento;
        }
        
        // Aplicar descuento global
        if ($tipo_descuento === 'porcentaje') {
            $descuento_global_monto = $subtotal * ($descuento_global / 100);
        } else {
            $descuento_global_monto = $descuento_global;
        }
        
        $subtotal_con_descuento = $subtotal - $descuento_global_monto;
        
        // Calcular IVA total
        $iva_total = $incluye_iva ? $subtotal_con_descuento * 0.19 : 0;
        
        // Total final
        $total = $subtotal_con_descuento + $iva_total + $costo_envio;
        
        wp_send_json_success(array(
            'items' => $items_calculados,
            'subtotal' => $subtotal,
            'descuento_global' => $descuento_global_monto,
            'subtotal_con_descuento' => $subtotal_con_descuento,
            'iva' => $iva_total,
            'envio' => $costo_envio,
            'total' => $total
        ));
    }
    
    /**
     * Obtener estados disponibles para cotizaciones
     */
    public function obtener_estados() {
        return array(
            'borrador' => __('Borrador', 'modulo-ventas'),
            'pendiente' => __('Pendiente', 'modulo-ventas'),
            'aprobada' => __('Aprobada', 'modulo-ventas'),
            'rechazada' => __('Rechazada', 'modulo-ventas'),
            'expirada' => __('Expirada', 'modulo-ventas'),
            'convertida' => __('Convertida', 'modulo-ventas')
        );
    }
    
    /**
     * Obtener color del estado
     */
    public function obtener_color_estado($estado) {
        $colores = array(
            'borrador' => '#999999',
            'pendiente' => '#f39c12',
            'aprobada' => '#27ae60',
            'rechazada' => '#e74c3c',
            'expirada' => '#95a5a6',
            'convertida' => '#3498db'
        );
        
        return isset($colores[$estado]) ? $colores[$estado] : '#666666';
    }
}