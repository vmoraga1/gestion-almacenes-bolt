<?php
/**
 * Clase para manejar todas las peticiones AJAX del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Ajax {
    
    /**
     * Instancias necesarias
     */
    private $db;
    private $cotizaciones;
    private $clientes;
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Modulo_Ventas_DB();
        $this->cotizaciones = new Modulo_Ventas_Cotizaciones();
        $this->clientes = new Modulo_Ventas_Clientes();
        $this->logger = Modulo_Ventas_Logger::get_instance();
        
        // Registrar todos los handlers AJAX
        $this->registrar_ajax_handlers();
    }
    
    /**
     * Registrar todos los handlers AJAX
     */
    private function registrar_ajax_handlers() {
        // Handlers públicos (no requieren login)
        $public_actions = array(
            'mv_validar_rut_publico' => 'validar_rut_publico',
        );
        
        foreach ($public_actions as $action => $method) {
            add_action('wp_ajax_nopriv_' . $action, array($this, $method));
            add_action('wp_ajax_' . $action, array($this, $method));
        }
        
        // Handlers privados (requieren login)
        $private_actions = array(
            // Cotizaciones
            'mv_buscar_productos' => 'buscar_productos',
            'mv_obtener_stock_producto' => 'obtener_stock_producto',
            'mv_guardar_cotizacion' => 'guardar_cotizacion',
            'mv_actualizar_cotizacion' => 'actualizar_cotizacion',
            'mv_actualizar_estado_cotizacion' => 'actualizar_estado_cotizacion',
            'mv_duplicar_cotizacion' => 'duplicar_cotizacion',
            'mv_convertir_cotizacion' => 'convertir_cotizacion',
            'mv_eliminar_cotizacion' => 'eliminar_cotizacion',
            'mv_enviar_cotizacion_email' => 'enviar_cotizacion_email',
            'mv_generar_pdf_cotizacion' => 'generar_pdf_cotizacion',
            'mv_obtener_almacenes' => 'obtener_almacenes',
            'mv_calcular_totales' => 'calcular_totales',
            'mv_buscar_cotizaciones' => 'buscar_cotizaciones',
            
            // Clientes
            'mv_buscar_cliente' => 'buscar_cliente',
            'mv_crear_cliente_rapido' => 'crear_cliente_rapido',
            'mv_obtener_cliente' => 'obtener_cliente',
            'mv_actualizar_cliente' => 'actualizar_cliente',
            'mv_eliminar_cliente' => 'eliminar_cliente',
            'mv_validar_rut' => 'validar_rut',
            'mv_obtener_comunas' => 'obtener_comunas',
            'mv_sincronizar_cliente_wc' => 'sincronizar_cliente_woocommerce',
            
            // Reportes y estadísticas
            'mv_obtener_estadisticas' => 'obtener_estadisticas',
            'mv_obtener_reporte' => 'obtener_reporte',
            'mv_exportar_cotizaciones' => 'exportar_cotizaciones',
            'mv_exportar_clientes' => 'exportar_clientes',
            'mv_generar_grafico' => 'generar_datos_grafico',
            
            // Utilidades
            'mv_actualizar_almacen_pedido' => 'actualizar_almacen_pedido',
            'mv_buscar_usuario' => 'buscar_usuario',
            'mv_test_email' => 'test_email',
            'mv_limpiar_logs' => 'limpiar_logs',
            'mv_backup_datos' => 'backup_datos',
        );
        
        foreach ($private_actions as $action => $method) {
            add_action('wp_ajax_' . $action, array($this, $method));
        }
    }
    
    /**
     * COTIZACIONES
     */
    
    /**
     * Buscar productos
     */
    public function buscar_productos() {
        // Verificar nonce
        if (!check_ajax_referer('modulo_ventas_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Error de seguridad', 'modulo-ventas')));
        }
        
        // Verificar permisos
        if (!current_user_can('create_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $busqueda = isset($_POST['busqueda']) ? sanitize_text_field($_POST['busqueda']) : '';
        $almacen_id = isset($_POST['almacen_id']) ? intval($_POST['almacen_id']) : 0;
        $limite = isset($_POST['limite']) ? intval($_POST['limite']) : 20;
        
        // Si no hay búsqueda o es muy corta, no devolver resultados
        if (empty($busqueda) || strlen($busqueda) < 2) {
            wp_send_json_success(array(
                'productos' => array(),
                'total' => 0,
                'busqueda' => $busqueda,
                'mensaje' => __('Ingrese al menos 2 caracteres para buscar', 'modulo-ventas')
            ));
            return;
        }
        
        // Arrays para almacenar resultados
        $productos_encontrados = array();
        $ids_procesados = array();
        
        // 1. Buscar por SKU exacto (case insensitive)
        global $wpdb;
        
        // Primero intentar búsqueda exacta de SKU
        $sku_exacto = $wpdb->get_var($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' 
            AND LOWER(meta_value) = LOWER(%s)
            LIMIT 1
        ", $busqueda));
        
        if ($sku_exacto) {
            $product = wc_get_product($sku_exacto);
            if ($product) {
                $productos_encontrados[] = $product;
                $ids_procesados[] = $product->get_id();
            }
        }
        
        // 2. Buscar por SKU parcial (LIKE)
        if (count($productos_encontrados) < $limite) {
            $sku_search = '%' . $wpdb->esc_like(strtolower($busqueda)) . '%';
            
            // Buscar sin filtro de stock
            $productos_sku = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type IN ('product', 'product_variation')
                AND p.post_status = 'publish'
                AND pm.meta_key = '_sku' 
                AND LOWER(pm.meta_value) LIKE %s
                AND p.ID NOT IN (" . implode(',', array_map('intval', $ids_procesados ?: [0])) . ")
                ORDER BY p.post_title
                LIMIT %d
            ", $sku_search, $limite - count($productos_encontrados)));
            
            foreach ($productos_sku as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    // Para variaciones, obtener el producto padre
                    if ($product->get_type() === 'variation') {
                        $parent_id = $product->get_parent_id();
                        if (!in_array($parent_id, $ids_procesados)) {
                            $parent_product = wc_get_product($parent_id);
                            if ($parent_product) {
                                $productos_encontrados[] = $parent_product;
                                $ids_procesados[] = $parent_id;
                            }
                        }
                    } else if (!in_array($product_id, $ids_procesados)) {
                        $productos_encontrados[] = $product;
                        $ids_procesados[] = $product_id;
                    }
                }
            }
        }
        
        // 3. Buscar por nombre (sin filtro de stock)
        if (count($productos_encontrados) < $limite) {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => $limite - count($productos_encontrados),
                'post_status' => 'publish',
                's' => $busqueda,
                'post__not_in' => $ids_procesados ?: [0]
                // REMOVIDO el meta_query de stock_status
            );
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product) {
                        $productos_encontrados[] = $product;
                        $ids_procesados[] = $product->get_id();
                    }
                }
                wp_reset_postdata();
            }
        }
        
        // Si no hay resultados, retornar mensaje
        if (empty($productos_encontrados)) {
            wp_send_json_success(array(
                'productos' => array(),
                'total' => 0,
                'busqueda' => $busqueda,
                'mensaje' => sprintf(__('No se encontraron productos para "%s"', 'modulo-ventas'), $busqueda)
            ));
            return;
        }
        
        // Separar productos con stock y sin stock
        $productos_con_stock = array();
        $productos_sin_stock = array();
        
        foreach ($productos_encontrados as $product) {
            // Obtener stock
            $stock_disponible = $product->get_stock_quantity();
            
            // Si hay gestión de almacenes
            if ($almacen_id && function_exists('mv_get_stock_almacen')) {
                $stock_disponible = mv_get_stock_almacen($product->get_id(), $almacen_id);
            }
            
            // Determinar si tiene stock
            $tiene_stock = ($product->is_in_stock() && $stock_disponible > 0) || 
                        (!$product->managing_stock() && $product->is_in_stock());
            
            $precio = $product->get_price();
            $precio_formateado = $precio ? number_format($precio, 0, ',', '.') : '0';
            
            // Agregar indicador de estado de stock al nombre
            $nombre_display = $product->get_name();
            if (!$tiene_stock) {
                $nombre_display .= ' (Agotado)';
            }
            
            $producto_data = array(
                'id' => $product->get_id(),
                'text' => $nombre_display . ' - $' . $precio_formateado,
                'nombre' => $product->get_name(),
                'sku' => $product->get_sku() ?: 'N/A',
                'precio' => $precio ?: 0,
                'precio_regular' => $product->get_regular_price() ?: $precio,
                'precio_oferta' => $product->get_sale_price() ?: '',
                'stock' => $stock_disponible ?: 0,
                'variacion_id' => 0,
                'es_variable' => $product->is_type('variable'),
                'en_stock' => $tiene_stock,
                'gestion_stock' => $product->managing_stock()
            );
            
            // Si es un producto variable, obtener la primera variación disponible
            if ($product->is_type('variable')) {
                $variaciones = $product->get_available_variations();
                if (!empty($variaciones)) {
                    // Buscar primera variación con stock
                    $variacion_seleccionada = null;
                    foreach ($variaciones as $variacion) {
                        $var_obj = wc_get_product($variacion['variation_id']);
                        if ($var_obj && $var_obj->is_in_stock()) {
                            $variacion_seleccionada = $variacion;
                            break;
                        }
                    }
                    
                    // Si no hay ninguna con stock, tomar la primera
                    if (!$variacion_seleccionada && !empty($variaciones)) {
                        $variacion_seleccionada = reset($variaciones);
                    }
                    
                    if ($variacion_seleccionada) {
                        $var_obj = wc_get_product($variacion_seleccionada['variation_id']);
                        if ($var_obj) {
                            $producto_data['variacion_id'] = $variacion_seleccionada['variation_id'];
                            $producto_data['precio'] = $var_obj->get_price() ?: $precio;
                            $producto_data['precio_regular'] = $var_obj->get_regular_price() ?: $producto_data['precio_regular'];
                            $producto_data['precio_oferta'] = $var_obj->get_sale_price() ?: '';
                            
                            if ($almacen_id && function_exists('mv_get_stock_almacen')) {
                                $producto_data['stock'] = mv_get_stock_almacen($variacion_seleccionada['variation_id'], $almacen_id);
                            } else {
                                $producto_data['stock'] = $var_obj->get_stock_quantity() ?: 0;
                            }
                        }
                    }
                }
            }
            
            // Separar por disponibilidad de stock
            if ($tiene_stock) {
                $productos_con_stock[] = $producto_data;
            } else {
                $productos_sin_stock[] = $producto_data;
            }
        }
        
        // Combinar arrays: primero con stock, luego sin stock
        $productos = array_merge($productos_con_stock, $productos_sin_stock);
        
        // Respuesta con todos los productos
        wp_send_json_success(array(
            'productos' => $productos,
            'total' => count($productos),
            'busqueda' => $busqueda,
            'con_stock' => count($productos_con_stock),
            'sin_stock' => count($productos_sin_stock)
        ));
    }
    
    /**
     * Obtener stock de producto
     */
    public function obtener_stock_producto() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
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
            'gestion_stock' => $producto->managing_stock(),
            'en_stock' => $producto->is_in_stock(),
            'stock_status' => $producto->get_stock_status(),
            'backorders' => $producto->get_backorders(),
            'stock_por_almacen' => array()
        );
        
        // Stock por almacén si está disponible
        if (mv_almacenes_activo()) {
            $almacenes = mv_get_almacenes();
            foreach ($almacenes as $almacen) {
                $stock = mv_get_stock_almacen($id_real, $almacen->id);
                $response['stock_por_almacen'][] = array(
                    'almacen_id' => $almacen->id,
                    'almacen_nombre' => $almacen->name,
                    'stock' => $stock,
                    'direccion' => $almacen->address,
                    'activo' => $almacen->is_active
                );
            }
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Guardar cotización
     */
    public function guardar_cotizacion() {
        mv_ajax_check_permissions('create_cotizaciones', 'modulo_ventas_nonce');
        
        // Validar datos
        if (!isset($_POST['datos_generales']) || !isset($_POST['items'])) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        // Sanitizar datos generales
        $datos_generales = array();
        foreach ($_POST['datos_generales'] as $key => $value) {
            switch ($key) {
                case 'cliente_id':
                case 'vendedor_id':
                case 'almacen_id':
                    $datos_generales[$key] = intval($value);
                    break;
                    
                case 'subtotal':
                case 'total':
                case 'descuento_monto':
                case 'descuento_porcentaje':
                case 'costo_envio':
                    $datos_generales[$key] = floatval($value);
                    break;
                    
                case 'incluye_iva':
                    $datos_generales[$key] = $value ? 1 : 0;
                    break;
                    
                case 'fecha_expiracion':
                    $datos_generales[$key] = sanitize_text_field($value);
                    if ($datos_generales[$key] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos_generales[$key])) {
                        wp_send_json_error(array('message' => __('Formato de fecha inválido', 'modulo-ventas')));
                    }
                    break;
                    
                case 'observaciones':
                case 'notas_internas':
                case 'terminos_condiciones':
                case 'condiciones_pago':
                    $datos_generales[$key] = sanitize_textarea_field($value);
                    break;
                    
                default:
                    $datos_generales[$key] = sanitize_text_field($value);
            }
        }
        
        // Sanitizar items
        $items = array();
        foreach ($_POST['items'] as $item) {
            $item_sanitizado = array(
                'producto_id' => intval($item['producto_id']),
                'variacion_id' => isset($item['variacion_id']) ? intval($item['variacion_id']) : 0,
                'almacen_id' => isset($item['almacen_id']) ? intval($item['almacen_id']) : null,
                'sku' => sanitize_text_field($item['sku'] ?? ''),
                'nombre' => sanitize_text_field($item['nombre']),
                'descripcion' => sanitize_textarea_field($item['descripcion'] ?? ''),
                'cantidad' => floatval($item['cantidad']),
                'precio_unitario' => floatval($item['precio_unitario']),
                'precio_original' => floatval($item['precio_original'] ?? $item['precio_unitario']),
                'descuento_monto' => floatval($item['descuento_monto'] ?? 0),
                'descuento_porcentaje' => floatval($item['descuento_porcentaje'] ?? 0),
                'tipo_descuento' => sanitize_text_field($item['tipo_descuento'] ?? 'monto'),
                'subtotal' => floatval($item['subtotal']),
                'notas' => sanitize_textarea_field($item['notas'] ?? ''),
                'stock_disponible' => isset($item['stock_disponible']) ? intval($item['stock_disponible']) : null
            );
            
            // Validaciones
            if ($item_sanitizado['cantidad'] <= 0) {
                wp_send_json_error(array('message' => __('La cantidad debe ser mayor a 0', 'modulo-ventas')));
            }
            
            if ($item_sanitizado['precio_unitario'] < 0) {
                wp_send_json_error(array('message' => __('El precio no puede ser negativo', 'modulo-ventas')));
            }
            
            $items[] = $item_sanitizado;
        }
        
        // Crear cotización
        $cotizacion_id = $this->cotizaciones->crear_cotizacion($datos_generales, $items);
        
        if (is_wp_error($cotizacion_id)) {
            $this->logger->log('Error al crear cotización: ' . $cotizacion_id->get_error_message(), 'error');
            wp_send_json_error(array(
                'message' => $cotizacion_id->get_error_message(),
                'errors' => $cotizacion_id->get_error_messages()
            ));
        }
        
        // Obtener cotización creada
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        
        $this->logger->log("Cotización creada exitosamente: {$cotizacion->folio}", 'info');
        
        wp_send_json_success(array(
            'cotizacion_id' => $cotizacion_id,
            'folio' => $cotizacion->folio,
            'redirect_url' => mv_admin_url('ver-cotizacion', array('id' => $cotizacion_id)),
            'message' => sprintf(__('Cotización %s creada exitosamente', 'modulo-ventas'), $cotizacion->folio)
        ));
    }
    
    /**
     * Actualizar cotización
     */
    public function actualizar_cotizacion() {
        mv_ajax_check_permissions('edit_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        // Verificar que existe
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            wp_send_json_error(array('message' => __('Cotización no encontrada', 'modulo-ventas')));
        }
        
        // Por implementar: actualización completa
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }
    
    /**
     * Actualizar estado de cotización
     */
    public function actualizar_estado_cotizacion() {
        mv_ajax_check_permissions('edit_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        $nuevo_estado = isset($_POST['estado']) ? sanitize_text_field($_POST['estado']) : '';
        
        if (!$cotizacion_id || !$nuevo_estado) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        $resultado = $this->db->actualizar_estado_cotizacion($cotizacion_id, $nuevo_estado);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        $this->logger->log("Estado de cotización {$cotizacion_id} actualizado a: {$nuevo_estado}", 'info');
        
        wp_send_json_success(array(
            'message' => __('Estado actualizado exitosamente', 'modulo-ventas'),
            'nuevo_estado' => $nuevo_estado,
            'badge_html' => mv_get_estado_badge($nuevo_estado)
        ));
    }
    
    /**
     * Duplicar cotización
     */
    public function duplicar_cotizacion() {
        mv_ajax_check_permissions('create_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $nueva_cotizacion_id = $this->cotizaciones->duplicar_cotizacion($cotizacion_id);
        
        if (is_wp_error($nueva_cotizacion_id)) {
            wp_send_json_error(array('message' => $nueva_cotizacion_id->get_error_message()));
        }
        
        $nueva_cotizacion = $this->db->obtener_cotizacion($nueva_cotizacion_id);
        
        wp_send_json_success(array(
            'cotizacion_id' => $nueva_cotizacion_id,
            'folio' => $nueva_cotizacion->folio,
            'redirect_url' => mv_admin_url('editar-cotizacion', array('id' => $nueva_cotizacion_id)),
            'message' => sprintf(__('Cotización duplicada exitosamente. Nueva cotización: %s', 'modulo-ventas'), $nueva_cotizacion->folio)
        ));
    }
    
    /**
     * Convertir cotización a pedido
     */
    public function convertir_cotizacion() {
        mv_ajax_check_permissions('edit_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        $crear_cuenta = isset($_POST['crear_cuenta']) ? (bool)$_POST['crear_cuenta'] : false;
        $enviar_email = isset($_POST['enviar_email']) ? (bool)$_POST['enviar_email'] : true;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $pedido_id = $this->cotizaciones->convertir_a_pedido($cotizacion_id);
        
        if (is_wp_error($pedido_id)) {
            $this->logger->log('Error al convertir cotización: ' . $pedido_id->get_error_message(), 'error');
            wp_send_json_error(array('message' => $pedido_id->get_error_message()));
        }
        
        // Si se debe crear cuenta para el cliente
        if ($crear_cuenta) {
            $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
            $cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
            
            if ($cliente && !$cliente->user_id && $cliente->email) {
                $user_id = wp_create_user(
                    $cliente->email,
                    wp_generate_password(),
                    $cliente->email
                );
                
                if (!is_wp_error($user_id)) {
                    // Actualizar cliente con user_id
                    $this->db->actualizar_cliente($cliente->id, array('user_id' => $user_id));
                    
                    // Asignar rol de cliente
                    $user = new WP_User($user_id);
                    $user->set_role('customer');
                    
                    // Actualizar pedido con el cliente
                    $order = wc_get_order($pedido_id);
                    $order->set_customer_id($user_id);
                    $order->save();
                    
                    // Enviar email de nueva cuenta
                    if ($enviar_email) {
                        wp_new_user_notification($user_id, null, 'both');
                    }
                }
            }
        }
        
        $this->logger->log("Cotización {$cotizacion_id} convertida a pedido #{$pedido_id}", 'info');
        
        wp_send_json_success(array(
            'pedido_id' => $pedido_id,
            'pedido_url' => admin_url('post.php?post=' . $pedido_id . '&action=edit'),
            'message' => sprintf(__('Cotización convertida exitosamente. Pedido #%d creado', 'modulo-ventas'), $pedido_id)
        ));
    }
    
    /**
     * Eliminar cotización
     */
    public function eliminar_cotizacion() {
        mv_ajax_check_permissions('delete_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $resultado = $this->db->eliminar_cotizacion($cotizacion_id);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Cotización eliminada exitosamente', 'modulo-ventas'),
            'redirect_url' => mv_admin_url('cotizaciones')
        ));
    }
    
    /**
     * Enviar cotización por email
     */
    public function enviar_cotizacion_email() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        $email_destino = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $email_copia = isset($_POST['email_cc']) ? sanitize_email($_POST['email_cc']) : '';
        $mensaje_adicional = isset($_POST['mensaje']) ? sanitize_textarea_field($_POST['mensaje']) : '';
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $resultado = $this->cotizaciones->enviar_por_email($cotizacion_id, $email_destino);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cotización enviada exitosamente a %s', 'modulo-ventas'), $email_destino)
        ));
    }
    
    /**
     * Generar PDF de cotización
     */
    public function generar_pdf_cotizacion() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        $accion = isset($_POST['accion']) ? sanitize_text_field($_POST['accion']) : 'descargar';
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $pdf_path = $this->cotizaciones->generar_pdf($cotizacion_id);
        
        if (is_wp_error($pdf_path)) {
            wp_send_json_error(array('message' => $pdf_path->get_error_message()));
        }
        
        // Convertir a URL
        $upload_dir = wp_upload_dir();
        $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $pdf_path);
        
        wp_send_json_success(array(
            'pdf_url' => $pdf_url,
            'pdf_path' => $pdf_path,
            'message' => __('PDF generado exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * Obtener almacenes
     */
    public function obtener_almacenes() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $incluir_inactivos = isset($_POST['incluir_inactivos']) ? (bool)$_POST['incluir_inactivos'] : false;
        
        $almacenes = mv_get_almacenes(!$incluir_inactivos);
        
        $almacenes_array = array();
        foreach ($almacenes as $almacen) {
            $almacenes_array[] = array(
                'id' => $almacen->id,
                'nombre' => $almacen->name,
                'direccion' => $almacen->address,
                'activo' => $almacen->is_active,
                'es_principal' => $almacen->is_main
            );
        }
        
        wp_send_json_success(array(
            'almacenes' => $almacenes_array,
            'total' => count($almacenes_array)
        ));
    }
    
    /**
     * Calcular totales
     */
    public function calcular_totales() {
        mv_ajax_check_permissions('view_cotizaciones');
        
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        $incluye_iva = isset($_POST['incluye_iva']) ? (bool)$_POST['incluye_iva'] : true;
        $descuento_global = isset($_POST['descuento_global']) ? floatval($_POST['descuento_global']) : 0;
        $tipo_descuento = isset($_POST['tipo_descuento']) ? sanitize_text_field($_POST['tipo_descuento']) : 'monto';
        $costo_envio = isset($_POST['costo_envio']) ? floatval($_POST['costo_envio']) : 0;
        $tasa_iva = isset($_POST['tasa_iva']) ? floatval($_POST['tasa_iva']) : 19;
        
        $subtotal = 0;
        $items_calculados = array();
        
        // Calcular subtotal y totales por item
        foreach ($items as $index => $item) {
            $cantidad = floatval($item['cantidad'] ?? 0);
            $precio_unitario = floatval($item['precio_unitario'] ?? 0);
            $descuento_item = floatval($item['descuento'] ?? 0);
            $tipo_descuento_item = $item['tipo_descuento'] ?? 'monto';
            
            // Calcular subtotal del item
            $subtotal_item = $cantidad * $precio_unitario;
            
            // Aplicar descuento del item
            $descuento_item_monto = $tipo_descuento_item === 'porcentaje' 
                ? $subtotal_item * ($descuento_item / 100)
                : $descuento_item;
            
            $subtotal_item_con_descuento = $subtotal_item - $descuento_item_monto;
            
            // Calcular IVA del item si aplica
            $iva_item = $incluye_iva ? mv_calcular_iva($subtotal_item_con_descuento, $tasa_iva) : 0;
            $total_item = $subtotal_item_con_descuento + $iva_item;
            
            $items_calculados[$index] = array(
                'subtotal' => round($subtotal_item, 2),
                'descuento' => round($descuento_item_monto, 2),
                'subtotal_con_descuento' => round($subtotal_item_con_descuento, 2),
                'iva' => round($iva_item, 2),
                'total' => round($total_item, 2)
            );
            
            $subtotal += $subtotal_item_con_descuento;
        }
        
        // Aplicar descuento global
        $descuento_global_monto = $tipo_descuento === 'porcentaje'
            ? $subtotal * ($descuento_global / 100)
            : $descuento_global;
        
        $subtotal_con_descuento = $subtotal - $descuento_global_monto;
        
        // Calcular IVA total
        $iva_total = $incluye_iva ? mv_calcular_iva($subtotal_con_descuento, $tasa_iva) : 0;
        
        // Total final
        $total = $subtotal_con_descuento + $iva_total + $costo_envio;
        
        wp_send_json_success(array(
            'items' => $items_calculados,
            'subtotal' => round($subtotal, 2),
            'descuento_global' => round($descuento_global_monto, 2),
            'subtotal_con_descuento' => round($subtotal_con_descuento, 2),
            'iva' => round($iva_total, 2),
            'envio' => round($costo_envio, 2),
            'total' => round($total, 2),
            'resumen' => array(
                'subtotal_formateado' => mv_formato_precio($subtotal),
                'descuento_formateado' => mv_formato_precio($descuento_global_monto),
                'iva_formateado' => mv_formato_precio($iva_total),
                'envio_formateado' => mv_formato_precio($costo_envio),
                'total_formateado' => mv_formato_precio($total)
            )
        ));
    }
    
    /**
     * Buscar cotizaciones
     */
    public function buscar_cotizaciones() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $busqueda = isset($_POST['busqueda']) ? sanitize_text_field($_POST['busqueda']) : '';
        $estado = isset($_POST['estado']) ? sanitize_text_field($_POST['estado']) : '';
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $fecha_desde = isset($_POST['fecha_desde']) ? sanitize_text_field($_POST['fecha_desde']) : '';
        $fecha_hasta = isset($_POST['fecha_hasta']) ? sanitize_text_field($_POST['fecha_hasta']) : '';
        $limite = isset($_POST['limite']) ? intval($_POST['limite']) : 20;
        
        $args = array(
            'buscar' => $busqueda,
            'estado' => $estado,
            'cliente_id' => $cliente_id,
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta,
            'limit' => $limite
        );
        
        $cotizaciones = $this->db->obtener_cotizaciones($args);
        $total = $this->db->contar_cotizaciones($args);
        
        $cotizaciones_array = array();
        foreach ($cotizaciones as $cotizacion) {
            $cotizaciones_array[] = array(
                'id' => $cotizacion->id,
                'folio' => $cotizacion->folio,
                'fecha' => mv_formato_fecha($cotizacion->fecha),
                'cliente' => $cotizacion->razon_social,
                'total' => mv_formato_precio($cotizacion->total),
                'estado' => $cotizacion->estado,
                'estado_badge' => mv_get_estado_badge($cotizacion->estado),
                'url_ver' => mv_admin_url('ver-cotizacion', array('id' => $cotizacion->id)),
                'url_editar' => mv_admin_url('editar-cotizacion', array('id' => $cotizacion->id))
            );
        }
        
        wp_send_json_success(array(
            'cotizaciones' => $cotizaciones_array,
            'total' => $total
        ));
    }
    
    /**
     * CLIENTES
     */
    
    /**
     * Buscar cliente
     */
    public function buscar_cliente() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $termino = isset($_POST['termino']) ? sanitize_text_field($_POST['termino']) : '';
        $limite = isset($_POST['limite']) ? intval($_POST['limite']) : 10;
        
        $clientes = $this->db->buscar_clientes($termino, array('limit' => $limite));
        
        $resultados = array();
        foreach ($clientes as $cliente) {
            $resultados[] = array(
                'id' => $cliente->id,
                'value' => $cliente->id,
                'label' => sprintf('%s - %s', $cliente->razon_social, mv_formatear_rut($cliente->rut)),
                'text' => sprintf('%s - %s', $cliente->razon_social, mv_formatear_rut($cliente->rut)),
                'razon_social' => $cliente->razon_social,
                'rut' => mv_formatear_rut($cliente->rut),
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion_facturacion,
                'comuna' => $cliente->comuna_facturacion,
                'ciudad' => $cliente->ciudad_facturacion,
                'region' => $cliente->region_facturacion,
                'giro' => $cliente->giro_comercial
            );
        }
        
        wp_send_json_success(array(
            'results' => $resultados,
            'clientes' => $resultados // Para compatibilidad
        ));
    }
    
    /**
     * Crear cliente rápido
     */
    public function crear_cliente_rapido() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        if (!isset($_POST['cliente'])) {
            wp_send_json_error(array('message' => __('Datos del cliente no proporcionados', 'modulo-ventas')));
        }
        
        $datos = $_POST['cliente'];
        
        // Validaciones básicas
        if (empty($datos['razon_social']) || empty($datos['rut'])) {
            wp_send_json_error(array('message' => __('Razón social y RUT son obligatorios', 'modulo-ventas')));
        }
        
        // Validar formato RUT
        if (!mv_validar_rut($datos['rut'])) {
            wp_send_json_error(array('message' => __('RUT inválido', 'modulo-ventas')));
        }
        
        // Sanitizar datos
        $datos_cliente = array(
            'razon_social' => sanitize_text_field($datos['razon_social']),
            'rut' => mv_limpiar_rut($datos['rut']),
            'giro_comercial' => sanitize_text_field($datos['giro_comercial'] ?? ''),
            'telefono' => sanitize_text_field($datos['telefono'] ?? ''),
            'email' => sanitize_email($datos['email'] ?? ''),
            'email_dte' => sanitize_email($datos['email_dte'] ?? ''),
            'direccion_facturacion' => sanitize_textarea_field($datos['direccion_facturacion'] ?? ''),
            'comuna_facturacion' => sanitize_text_field($datos['comuna_facturacion'] ?? ''),
            'ciudad_facturacion' => sanitize_text_field($datos['ciudad_facturacion'] ?? ''),
            'region_facturacion' => sanitize_text_field($datos['region_facturacion'] ?? ''),
            'pais_facturacion' => sanitize_text_field($datos['pais_facturacion'] ?? 'Chile')
        );
        
        $cliente_id = $this->db->crear_cliente($datos_cliente);
        
        if (is_wp_error($cliente_id)) {
            wp_send_json_error(array('message' => $cliente_id->get_error_message()));
        }
        
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        $this->logger->log("Cliente creado rápidamente: {$cliente->razon_social} (ID: {$cliente_id})", 'info');
        
        wp_send_json_success(array(
            'cliente' => array(
                'id' => $cliente->id,
                'razon_social' => $cliente->razon_social,
                'rut' => mv_formatear_rut($cliente->rut),
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion_facturacion,
                'giro' => $cliente->giro_comercial
            ),
            'message' => __('Cliente creado exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * Obtener datos de cliente
     */
    public function obtener_cliente() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        if (!$cliente_id) {
            wp_send_json_error(array('message' => __('ID de cliente inválido', 'modulo-ventas')));
        }
        
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        if (!$cliente) {
            wp_send_json_error(array('message' => __('Cliente no encontrado', 'modulo-ventas')));
        }
        
        // Formatear datos para respuesta
        $cliente_data = array(
            'id' => $cliente->id,
            'razon_social' => $cliente->razon_social,
            'rut' => mv_formatear_rut($cliente->rut),
            'giro_comercial' => $cliente->giro_comercial,
            'telefono' => $cliente->telefono,
            'email' => $cliente->email,
            'email_dte' => $cliente->email_dte,
            'direccion_facturacion' => $cliente->direccion_facturacion,
            'comuna_facturacion' => $cliente->comuna_facturacion,
            'ciudad_facturacion' => $cliente->ciudad_facturacion,
            'region_facturacion' => $cliente->region_facturacion,
            'pais_facturacion' => $cliente->pais_facturacion,
            'usar_misma_direccion' => $cliente->usar_direccion_facturacion_para_envio,
            'direccion_envio' => $cliente->direccion_envio,
            'comuna_envio' => $cliente->comuna_envio,
            'ciudad_envio' => $cliente->ciudad_envio,
            'region_envio' => $cliente->region_envio,
            'pais_envio' => $cliente->pais_envio,
            'user_id' => $cliente->user_id,
            'fecha_creacion' => mv_formato_fecha($cliente->fecha_creacion, true),
            'meta' => $cliente->meta ?? array()
        );
        
        wp_send_json_success(array('cliente' => $cliente_data));
    }
    
    /**
     * Actualizar cliente
     */
    public function actualizar_cliente() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        if (!$cliente_id || !isset($_POST['cliente'])) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        $datos = $_POST['cliente'];
        
        // Preparar datos para actualización
        $datos_actualizacion = array();
        
        // Campos permitidos para actualización
        $campos_permitidos = array(
            'razon_social', 'giro_comercial', 'telefono', 'email', 'email_dte',
            'direccion_facturacion', 'comuna_facturacion', 'ciudad_facturacion', 
            'region_facturacion', 'pais_facturacion', 'usar_direccion_facturacion_para_envio',
            'direccion_envio', 'comuna_envio', 'ciudad_envio', 'region_envio', 'pais_envio'
        );
        
        foreach ($campos_permitidos as $campo) {
            if (isset($datos[$campo])) {
                $datos_actualizacion[$campo] = sanitize_text_field($datos[$campo]);
            }
        }
        
        // RUT requiere validación especial
        if (isset($datos['rut'])) {
            $rut_limpio = mv_limpiar_rut($datos['rut']);
            if (!mv_validar_rut($rut_limpio)) {
                wp_send_json_error(array('message' => __('RUT inválido', 'modulo-ventas')));
            }
            $datos_actualizacion['rut'] = $rut_limpio;
        }
        
        $resultado = $this->db->actualizar_cliente($cliente_id, $datos_actualizacion);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        $this->logger->log("Cliente actualizado: ID {$cliente_id}", 'info');
        
        wp_send_json_success(array('message' => __('Cliente actualizado exitosamente', 'modulo-ventas')));
    }
    
    /**
     * Eliminar cliente
     */
    public function eliminar_cliente() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        if (!$cliente_id) {
            wp_send_json_error(array('message' => __('ID de cliente inválido', 'modulo-ventas')));
        }
        
        // Verificar si tiene cotizaciones asociadas
        $cotizaciones = $this->db->obtener_cotizaciones(array(
            'cliente_id' => $cliente_id,
            'limit' => 1
        ));
        
        if (!empty($cotizaciones)) {
            wp_send_json_error(array('message' => __('No se puede eliminar un cliente con cotizaciones asociadas', 'modulo-ventas')));
        }
        
        global $wpdb;
        $resultado = $wpdb->delete(
            $this->db->get_tabla_clientes(),
            array('id' => $cliente_id),
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => __('Error al eliminar el cliente', 'modulo-ventas')));
        }
        
        $this->logger->log("Cliente eliminado: ID {$cliente_id}", 'info');
        
        wp_send_json_success(array('message' => __('Cliente eliminado exitosamente', 'modulo-ventas')));
    }
    
    /**
     * Validar RUT vía AJAX
     */
    public function validar_rut() {
        // Log para debug
        error_log('Método validar_rut() ejecutado');
        
        // Verificar nonce de forma más flexible
        $nonce_valid = false;
        
        // Primero intentar con el nonce general
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'modulo_ventas_nonce')) {
            $nonce_valid = true;
            error_log('Nonce válido: modulo_ventas_nonce');
        }
        // Si no, intentar con el nonce específico
        else if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mv_validar_rut')) {
            $nonce_valid = true;
            error_log('Nonce válido: mv_validar_rut');
        }
        // Intentar otros nombres de campo de nonce
        else {
            $nonce_fields = array('_ajax_nonce', 'security', '_wpnonce');
            foreach ($nonce_fields as $field) {
                if (isset($_POST[$field])) {
                    if (wp_verify_nonce($_POST[$field], 'modulo_ventas_nonce') || 
                        wp_verify_nonce($_POST[$field], 'mv_validar_rut')) {
                        $nonce_valid = true;
                        error_log("Nonce válido en campo: $field");
                        break;
                    }
                }
            }
        }
        
        if (!$nonce_valid) {
            error_log('Error: Nonce inválido');
            error_log('Nonces recibidos: ' . print_r(array_filter($_POST, function($key) {
                return strpos($key, 'nonce') !== false || $key === 'security' || $key === '_wpnonce';
            }, ARRAY_FILTER_USE_KEY), true));
            
            wp_send_json_error(array(
                'message' => __('Error de seguridad', 'modulo-ventas'),
                'debug' => 'Nonce inválido o faltante'
            ));
            return;
        }
        
        $rut = isset($_POST['rut']) ? sanitize_text_field($_POST['rut']) : '';
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        error_log("Validando RUT: $rut");
        
        if (empty($rut)) {
            wp_send_json_error(array('message' => __('RUT vacío', 'modulo-ventas')));
            return;
        }
        
        // Limpiar RUT
        $rut_limpio = mv_limpiar_rut($rut);
        error_log("RUT limpio: $rut_limpio");
        
        // Validar formato y dígito verificador
        if (!mv_validar_rut($rut)) {
            error_log("RUT inválido: $rut");
            wp_send_json_error(array(
                'message' => __('RUT inválido. Verifique el dígito verificador.', 'modulo-ventas'),
                'rut_ingresado' => $rut,
                'rut_limpio' => $rut_limpio
            ));
            return;
        }
        
        // Verificar si ya existe
        $cliente_existente = $this->db->obtener_cliente_por_rut($rut_limpio);
        
        if ($cliente_existente && $cliente_existente->id != $cliente_id) {
            error_log("RUT ya existe para cliente ID: " . $cliente_existente->id);
            wp_send_json_error(array(
                'message' => __('Este RUT ya está registrado', 'modulo-ventas'),
                'cliente_existente' => array(
                    'id' => $cliente_existente->id,
                    'razon_social' => $cliente_existente->razon_social
                )
            ));
            return;
        }
        
        // RUT válido y disponible
        wp_send_json_success(array(
            'message' => __('RUT válido', 'modulo-ventas'),
            'rut_formateado' => mv_formatear_rut($rut_limpio),
            'rut_limpio' => $rut_limpio
        ));
    }
    
    /**
     * Validar RUT público (sin autenticación)
     */
    public function validar_rut_publico() {
        // Este método puede ser usado en formularios públicos
        $rut = isset($_POST['rut']) ? sanitize_text_field($_POST['rut']) : '';
        
        if (empty($rut)) {
            wp_send_json_error(array('valid' => false));
        }
        
        $rut_limpio = mv_limpiar_rut($rut);
        $es_valido = mv_validar_rut($rut_limpio);
        
        wp_send_json_success(array(
            'valid' => $es_valido,
            'formatted' => $es_valido ? mv_formatear_rut($rut_limpio) : ''
        ));
    }
    
    /**
     * Obtener comunas por región
     */
    public function obtener_comunas() {
        check_ajax_referer('mv_obtener_comunas', 'nonce');
        
        $region = isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '';
        
        if (empty($region)) {
            wp_send_json_error(array('message' => __('Región no especificada', 'modulo-ventas')));
        }
        
        $comunas = $this->clientes->obtener_comunas_por_region($region);
        
        wp_send_json_success(array('comunas' => $comunas));
    }
    
    /**
     * Sincronizar cliente con WooCommerce
     */
    public function sincronizar_cliente_woocommerce() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$cliente_id) {
            wp_send_json_error(array('message' => __('ID de cliente inválido', 'modulo-ventas')));
        }
        
        $resultado = $this->clientes->sincronizar_con_woocommerce($cliente_id, $user_id);
        
        if (!$resultado) {
            wp_send_json_error(array('message' => __('Error al sincronizar', 'modulo-ventas')));
        }
        
        wp_send_json_success(array('message' => __('Cliente sincronizado con WooCommerce', 'modulo-ventas')));
    }
    
    /**
     * REPORTES Y ESTADÍSTICAS
     */
    
    /**
     * Obtener estadísticas
     */
    public function obtener_estadisticas() {
        mv_ajax_check_permissions('view_reportes_ventas', 'modulo_ventas_nonce');
        
        $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'general';
        $periodo = isset($_POST['periodo']) ? sanitize_text_field($_POST['periodo']) : 'mes_actual';
        $fecha_desde = isset($_POST['fecha_desde']) ? sanitize_text_field($_POST['fecha_desde']) : '';
        $fecha_hasta = isset($_POST['fecha_hasta']) ? sanitize_text_field($_POST['fecha_hasta']) : '';
        
        // Si se especifican fechas personalizadas
        if ($periodo === 'personalizado' && $fecha_desde && $fecha_hasta) {
            // Usar fechas personalizadas
        } else {
            // Calcular fechas según período
            switch ($periodo) {
                case 'hoy':
                    $fecha_desde = $fecha_hasta = current_time('Y-m-d');
                    break;
                    
                case 'ayer':
                    $fecha_desde = $fecha_hasta = date('Y-m-d', strtotime('-1 day'));
                    break;
                    
                case 'semana':
                    $fecha_desde = date('Y-m-d', strtotime('monday this week'));
                    $fecha_hasta = current_time('Y-m-d');
                    break;
                    
                case 'mes_actual':
                    $fecha_desde = date('Y-m-01');
                    $fecha_hasta = current_time('Y-m-d');
                    break;
                    
                case 'mes_anterior':
                    $fecha_desde = date('Y-m-01', strtotime('first day of last month'));
                    $fecha_hasta = date('Y-m-t', strtotime('last day of last month'));
                    break;
                    
                case 'trimestre':
                    $trimestre_actual = ceil(date('n') / 3);
                    $fecha_desde = date('Y-m-d', mktime(0, 0, 0, ($trimestre_actual - 1) * 3 + 1, 1, date('Y')));
                    $fecha_hasta = current_time('Y-m-d');
                    break;
                    
                case 'año':
                    $fecha_desde = date('Y-01-01');
                    $fecha_hasta = current_time('Y-m-d');
                    break;
            }
        }
        
        global $wpdb;
        $tabla = $this->db->get_tabla_cotizaciones();
        $tabla_items = $this->db->get_tabla_cotizaciones_items();
        
        $estadisticas = array();
        
        switch ($tipo) {
            case 'general':
                // Estadísticas generales
                $estadisticas = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        COUNT(*) as total_cotizaciones,
                        SUM(total) as valor_total,
                        AVG(total) as ticket_promedio,
                        SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as cotizaciones_convertidas,
                        SUM(CASE WHEN estado = 'convertida' THEN total ELSE 0 END) as valor_convertido,
                        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as cotizaciones_pendientes,
                        SUM(CASE WHEN estado = 'aprobada' THEN 1 ELSE 0 END) as cotizaciones_aprobadas,
                        SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END) as cotizaciones_rechazadas,
                        SUM(CASE WHEN estado = 'expirada' THEN 1 ELSE 0 END) as cotizaciones_expiradas
                     FROM {$tabla}
                     WHERE DATE(fecha) BETWEEN %s AND %s",
                    $fecha_desde,
                    $fecha_hasta
                ), ARRAY_A);
                
                // Calcular tasa de conversión
                $estadisticas['tasa_conversion'] = $estadisticas['total_cotizaciones'] > 0
                    ? round(($estadisticas['cotizaciones_convertidas'] / $estadisticas['total_cotizaciones']) * 100, 2)
                    : 0;
                break;
                
            case 'vendedores':
                // Estadísticas por vendedor
                $estadisticas = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        vendedor_id,
                        vendedor_nombre,
                        COUNT(*) as cotizaciones,
                        SUM(total) as valor_total,
                        AVG(total) as ticket_promedio,
                        SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as convertidas,
                        SUM(CASE WHEN estado = 'convertida' THEN total ELSE 0 END) as valor_convertido
                     FROM {$tabla}
                     WHERE DATE(fecha) BETWEEN %s AND %s
                     GROUP BY vendedor_id
                     ORDER BY valor_total DESC",
                    $fecha_desde,
                    $fecha_hasta
                ), ARRAY_A);
                break;
                
            case 'productos':
                // Productos más cotizados
                $estadisticas = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        ci.producto_id,
                        ci.nombre,
                        COUNT(DISTINCT ci.cotizacion_id) as veces_cotizado,
                        SUM(ci.cantidad) as cantidad_total,
                        SUM(ci.total) as valor_total,
                        AVG(ci.precio_unitario) as precio_promedio
                     FROM {$tabla_items} ci
                     INNER JOIN {$tabla} c ON ci.cotizacion_id = c.id
                     WHERE DATE(c.fecha) BETWEEN %s AND %s
                     GROUP BY ci.producto_id
                     ORDER BY veces_cotizado DESC
                     LIMIT 20",
                    $fecha_desde,
                    $fecha_hasta
                ), ARRAY_A);
                break;
                
            case 'clientes':
                // Top clientes
                $estadisticas = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        c.cliente_id,
                        cl.razon_social,
                        cl.rut,
                        COUNT(c.id) as cotizaciones,
                        SUM(c.total) as valor_total,
                        AVG(c.total) as ticket_promedio,
                        SUM(CASE WHEN c.estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
                     FROM {$tabla} c
                     INNER JOIN {$this->db->get_tabla_clientes()} cl ON c.cliente_id = cl.id
                     WHERE DATE(c.fecha) BETWEEN %s AND %s
                     GROUP BY c.cliente_id
                     ORDER BY valor_total DESC
                     LIMIT 20",
                    $fecha_desde,
                    $fecha_hasta
                ), ARRAY_A);
                break;
                
            case 'evolucion':
                // Evolución temporal
                $estadisticas = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        DATE(fecha) as dia,
                        COUNT(*) as cotizaciones,
                        SUM(total) as valor_total,
                        SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
                     FROM {$tabla}
                     WHERE DATE(fecha) BETWEEN %s AND %s
                     GROUP BY DATE(fecha)
                     ORDER BY dia ASC",
                    $fecha_desde,
                    $fecha_hasta
                ), ARRAY_A);
                break;
        }
        
        wp_send_json_success(array(
            'estadisticas' => $estadisticas,
            'periodo' => array(
                'desde' => $fecha_desde,
                'hasta' => $fecha_hasta,
                'dias' => (strtotime($fecha_hasta) - strtotime($fecha_desde)) / 86400 + 1
            )
        ));
    }
    
    /**
     * Obtener reporte
     */
    public function obtener_reporte() {
        mv_ajax_check_permissions('view_reportes_ventas', 'modulo_ventas_nonce');
        
        $tipo_reporte = isset($_POST['tipo_reporte']) ? sanitize_text_field($_POST['tipo_reporte']) : '';
        $formato = isset($_POST['formato']) ? sanitize_text_field($_POST['formato']) : 'json';
        
        // Por implementar según necesidades específicas
        wp_send_json_error(array('message' => __('Tipo de reporte no implementado', 'modulo-ventas')));
    }
    
    /**
     * Exportar cotizaciones
     */
    public function exportar_cotizaciones() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        $formato = isset($_POST['formato']) ? sanitize_text_field($_POST['formato']) : 'csv';
        $todos = isset($_POST['todos']) ? (bool)$_POST['todos'] : false;
        
        if ($todos) {
            // Obtener todas las cotizaciones con filtros actuales
            $filtros = array(
                'estado' => isset($_POST['filtro_estado']) ? sanitize_text_field($_POST['filtro_estado']) : '',
                'fecha_desde' => isset($_POST['filtro_fecha_desde']) ? sanitize_text_field($_POST['filtro_fecha_desde']) : '',
                'fecha_hasta' => isset($_POST['filtro_fecha_hasta']) ? sanitize_text_field($_POST['filtro_fecha_hasta']) : '',
                'limit' => -1
            );
            
            $cotizaciones = $this->db->obtener_cotizaciones($filtros);
        } else {
            // Obtener cotizaciones específicas
            $cotizaciones = array();
            foreach ($ids as $id) {
                $cotizacion = $this->db->obtener_cotizacion($id);
                if ($cotizacion) {
                    $cotizaciones[] = $cotizacion;
                }
            }
        }
        
        if (empty($cotizaciones)) {
            wp_send_json_error(array('message' => __('No hay cotizaciones para exportar', 'modulo-ventas')));
        }
        
        // Preparar datos para exportación
        $headers = array(
            __('Folio', 'modulo-ventas'),
            __('Fecha', 'modulo-ventas'),
            __('Cliente', 'modulo-ventas'),
            __('RUT', 'modulo-ventas'),
            __('Subtotal', 'modulo-ventas'),
            __('Descuento', 'modulo-ventas'),
            __('IVA', 'modulo-ventas'),
            __('Total', 'modulo-ventas'),
            __('Estado', 'modulo-ventas'),
            __('Vendedor', 'modulo-ventas'),
            __('Fecha Expiración', 'modulo-ventas'),
            __('Observaciones', 'modulo-ventas')
        );
        
        $data = array();
        foreach ($cotizaciones as $cotizacion) {
            $estados = mv_get_estados_cotizacion();
            $data[] = array(
                $cotizacion->folio,
                mv_formato_fecha($cotizacion->fecha),
                $cotizacion->razon_social,
                mv_formatear_rut($cotizacion->rut),
                number_format($cotizacion->subtotal, 0, ',', '.'),
                number_format($cotizacion->descuento_monto, 0, ',', '.'),
                number_format($cotizacion->impuesto_monto, 0, ',', '.'),
                number_format($cotizacion->total, 0, ',', '.'),
                $estados[$cotizacion->estado]['label'],
                $cotizacion->vendedor_nombre,
                $cotizacion->fecha_expiracion ? mv_formato_fecha($cotizacion->fecha_expiracion) : '',
                $cotizacion->observaciones
            );
        }
        
        // Generar archivo temporal
        $upload_dir = wp_upload_dir();
        $filename = 'cotizaciones_' . date('YmdHis') . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // Crear CSV
        $file = fopen($filepath, 'w');
        
        // BOM para UTF-8
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($file, $headers, ';');
        
        // Data
        foreach ($data as $row) {
            fputcsv($file, $row, ';');
        }
        
        fclose($file);
        
        // Retornar URL del archivo
        $file_url = $upload_dir['url'] . '/' . $filename;
        
        wp_send_json_success(array(
            'file_url' => $file_url,
            'filename' => $filename,
            'message' => sprintf(__('%d cotizaciones exportadas', 'modulo-ventas'), count($cotizaciones))
        ));
    }
    
    /**
     * Exportar clientes
     */
    public function exportar_clientes() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
        $todos = isset($_POST['todos']) ? (bool)$_POST['todos'] : false;
        
        if ($todos) {
            $clientes = $this->db->buscar_clientes('', array('limit' => -1));
        } else {
            $clientes = array();
            foreach ($ids as $id) {
                $cliente = $this->db->obtener_cliente($id);
                if ($cliente) {
                    $clientes[] = $cliente;
                }
            }
        }
        
        if (empty($clientes)) {
            wp_send_json_error(array('message' => __('No hay clientes para exportar', 'modulo-ventas')));
        }
        
        // Preparar datos
        $headers = array(
            __('ID', 'modulo-ventas'),
            __('Razón Social', 'modulo-ventas'),
            __('RUT', 'modulo-ventas'),
            __('Giro Comercial', 'modulo-ventas'),
            __('Teléfono', 'modulo-ventas'),
            __('Email', 'modulo-ventas'),
            __('Email DTE', 'modulo-ventas'),
            __('Dirección', 'modulo-ventas'),
            __('Comuna', 'modulo-ventas'),
            __('Ciudad', 'modulo-ventas'),
            __('Región', 'modulo-ventas'),
            __('Fecha Registro', 'modulo-ventas')
        );
        
        $data = array();
        foreach ($clientes as $cliente) {
            $data[] = array(
                $cliente->id,
                $cliente->razon_social,
                mv_formatear_rut($cliente->rut),
                $cliente->giro_comercial,
                $cliente->telefono,
                $cliente->email,
                $cliente->email_dte,
                $cliente->direccion_facturacion,
                $cliente->comuna_facturacion,
                $cliente->ciudad_facturacion,
                $cliente->region_facturacion,
                mv_formato_fecha($cliente->fecha_creacion)
            );
        }
        
        // Generar archivo
        $upload_dir = wp_upload_dir();
        $filename = 'clientes_' . date('YmdHis') . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        mv_export_csv($filename, $headers, $data);
    }
    
    /**
     * Generar datos para gráfico
     */
    public function generar_datos_grafico() {
        mv_ajax_check_permissions('view_reportes_ventas', 'modulo_ventas_nonce');
        
        $tipo = isset($_POST['tipo_grafico']) ? sanitize_text_field($_POST['tipo_grafico']) : 'linea';
        $datos = isset($_POST['datos']) ? sanitize_text_field($_POST['datos']) : 'cotizaciones';
        $periodo = isset($_POST['periodo']) ? sanitize_text_field($_POST['periodo']) : 'mes';
        
        // Obtener datos según el tipo solicitado
        global $wpdb;
        $tabla = $this->db->get_tabla_cotizaciones();
        
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = current_time('Y-m-d');
        
        if ($periodo === 'año') {
            $fecha_inicio = date('Y-01-01');
        } elseif ($periodo === 'semana') {
            $fecha_inicio = date('Y-m-d', strtotime('-7 days'));
        }
        
        $labels = array();
        $datasets = array();
        
        switch ($datos) {
            case 'cotizaciones':
                $resultados = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(fecha) as dia, COUNT(*) as total
                     FROM {$tabla}
                     WHERE DATE(fecha) BETWEEN %s AND %s
                     GROUP BY DATE(fecha)
                     ORDER BY dia ASC",
                    $fecha_inicio,
                    $fecha_fin
                ));
                
                foreach ($resultados as $row) {
                    $labels[] = date_i18n('j M', strtotime($row->dia));
                    $valores[] = intval($row->total);
                }
                
                $datasets[] = array(
                    'label' => __('Cotizaciones', 'modulo-ventas'),
                    'data' => $valores,
                    'borderColor' => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)',
                    'tension' => 0.4
                );
                break;
                
            case 'ventas':
                $resultados_cotizaciones = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(fecha) as dia, 
                            SUM(total) as total_cotizado,
                            SUM(CASE WHEN estado = 'convertida' THEN total ELSE 0 END) as total_convertido
                     FROM {$tabla}
                     WHERE DATE(fecha) BETWEEN %s AND %s
                     GROUP BY DATE(fecha)
                     ORDER BY dia ASC",
                    $fecha_inicio,
                    $fecha_fin
                ));
                
                $valores_cotizados = array();
                $valores_convertidos = array();
                
                foreach ($resultados_cotizaciones as $row) {
                    $labels[] = date_i18n('j M', strtotime($row->dia));
                    $valores_cotizados[] = floatval($row->total_cotizado);
                    $valores_convertidos[] = floatval($row->total_convertido);
                }
                
                $datasets[] = array(
                    'label' => __('Total Cotizado', 'modulo-ventas'),
                    'data' => $valores_cotizados,
                    'borderColor' => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)'
                );
                
                $datasets[] = array(
                    'label' => __('Total Convertido', 'modulo-ventas'),
                    'data' => $valores_convertidos,
                    'borderColor' => '#27ae60',
                    'backgroundColor' => 'rgba(39, 174, 96, 0.1)'
                );
                break;
        }
        
        wp_send_json_success(array(
            'labels' => $labels,
            'datasets' => $datasets
        ));
    }
    
    /**
     * UTILIDADES
     */
    
    /**
     * Actualizar almacén de pedido
     */
    public function actualizar_almacen_pedido() {
        check_ajax_referer('mv_actualizar_almacen_pedido', 'nonce');
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $almacen_id = isset($_POST['almacen_id']) ? intval($_POST['almacen_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('ID de pedido inválido', 'modulo-ventas')));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Pedido no encontrado', 'modulo-ventas')));
        }
        
        if ($almacen_id) {
            $order->update_meta_data('_almacen_id', $almacen_id);
            
            // Obtener nombre del almacén
            if (mv_almacenes_activo()) {
                global $gestion_almacenes_db;
                $almacen = $gestion_almacenes_db->get_warehouse($almacen_id);
                if ($almacen) {
                    $order->update_meta_data('_almacen_nombre', $almacen->name);
                }
            }
        } else {
            $order->delete_meta_data('_almacen_id');
            $order->delete_meta_data('_almacen_nombre');
        }
        
        $order->save();
        
        $this->logger->log("Almacén actualizado para pedido #{$order_id}: Almacén ID {$almacen_id}", 'info');
        
        wp_send_json_success(array('message' => __('Almacén actualizado', 'modulo-ventas')));
    }
    
    /**
     * Buscar usuario
     */
    public function buscar_usuario() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        $busqueda = isset($_POST['busqueda']) ? sanitize_text_field($_POST['busqueda']) : '';
        
        $args = array(
            'search' => '*' . $busqueda . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 20
        );
        
        $user_query = new WP_User_Query($args);
        $usuarios = array();
        
        if (!empty($user_query->get_results())) {
            foreach ($user_query->get_results() as $user) {
                $usuarios[] = array(
                    'id' => $user->ID,
                    'text' => sprintf('%s (%s)', $user->display_name, $user->user_email),
                    'email' => $user->user_email,
                    'nombre' => $user->display_name
                );
            }
        }
        
        wp_send_json_success(array('usuarios' => $usuarios));
    }
    
    /**
     * Test de email
     */
    public function test_email() {
        mv_ajax_check_permissions('manage_modulo_ventas', 'modulo_ventas_nonce');
        
        $email_destino = isset($_POST['email']) ? sanitize_email($_POST['email']) : get_option('admin_email');
        
        $asunto = sprintf(__('Prueba de Email - %s', 'modulo-ventas'), get_bloginfo('name'));
        $mensaje = sprintf(
            __("Este es un email de prueba desde el Módulo de Ventas.\n\nFecha: %s\nSitio: %s\nURL: %s", 'modulo-ventas'),
            current_time('mysql'),
            get_bloginfo('name'),
            home_url()
        );
        
        $enviado = wp_mail($email_destino, $asunto, $mensaje);
        
        if ($enviado) {
            wp_send_json_success(array('message' => sprintf(__('Email de prueba enviado a %s', 'modulo-ventas'), $email_destino)));
        } else {
            wp_send_json_error(array('message' => __('Error al enviar el email de prueba', 'modulo-ventas')));
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function limpiar_logs() {
        mv_ajax_check_permissions('manage_modulo_ventas', 'modulo_ventas_nonce');
        
        $dias = isset($_POST['dias']) ? intval($_POST['dias']) : 30;
        
        $eliminados = $this->logger->limpiar_logs_antiguos($dias);
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d registros de log eliminados', 'modulo-ventas'), $eliminados),
            'eliminados' => $eliminados
        ));
    }
    
    /**
     * Backup de datos
     */
    public function backup_datos() {
        mv_ajax_check_permissions('manage_modulo_ventas', 'modulo_ventas_nonce');
        
        // Por implementar: sistema de backup
        wp_send_json_error(array('message' => __('Función de backup en desarrollo', 'modulo-ventas')));
    }
}