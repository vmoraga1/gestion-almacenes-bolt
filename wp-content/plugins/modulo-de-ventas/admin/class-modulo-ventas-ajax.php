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

        // AGREGAR HOOKS DE DIAGNÓSTICO DIRECTAMENTE AQUÍ
        add_action('wp_ajax_mv_test_pdf_simple', array($this, 'test_pdf_simple'));
        add_action('wp_ajax_mv_crear_directorios_pdf', array($this, 'crear_directorios_pdf'));
        add_action('wp_ajax_mv_test_cotizacion_demo', array($this, 'test_cotizacion_demo'));
        add_action('wp_ajax_mv_descargar_pdf_test', array($this, 'descargar_pdf_test'));
        add_action('wp_ajax_nopriv_mv_descargar_pdf_test', array($this, 'descargar_pdf_test'));

        // Hooks AJAX para usuarios logueados
        add_action('wp_ajax_mv_generar_pdf_cotizacion', array($this, 'generar_pdf_cotizacion'));
        add_action('wp_ajax_mv_descargar_pdf_cotizacion', array($this, 'generar_pdf_cotizacion'));
        add_action('wp_ajax_mv_ver_pdf_cotizacion', array($this, 'generar_pdf_cotizacion'));
        add_action('wp_ajax_mv_test_pdf_simple', array($this, 'test_pdf_simple'));
        add_action('wp_ajax_mv_crear_directorios_pdf', array($this, 'crear_directorios_pdf'));
        add_action('wp_ajax_mv_servir_pdf', array($this, 'servir_pdf'));
        add_action('wp_ajax_mv_obtener_preview_real', array($this, 'ajax_obtener_preview_real'));
        add_action('wp_ajax_mv_vista_previa_datos_reales', array($this, 'ajax_vista_previa_datos_reales'));
        add_action('wp_ajax_mv_preview_mpdf_sincronizado_datos_reales', array($this, 'ajax_preview_mpdf_sincronizado_datos_reales'));
        add_action('wp_ajax_mv_generar_pdf_plantilla', array($this, 'ajax_generar_pdf_plantilla'));

        // Si necesitas soporte para usuarios no logueados (no recomendado para PDFs)
        // add_action('wp_ajax_nopriv_mv_generar_pdf_cotizacion', array($this, 'generar_pdf_cotizacion'));
        
        // Hook para manejar PDFs vía GET
        add_action('init', array($this, 'manejar_pdf_requests'));
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
            'mv_agregar_nota_cliente' => 'agregar_nota_cliente',
            'mv_eliminar_nota_cliente' => 'eliminar_nota_cliente',
            'mv_actualizar_nota_cliente' => 'actualizar_nota_cliente',
            
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

            // Diagnóstico y testing
            'mv_test_pdf_simple' => 'test_pdf_simple',
            'mv_crear_directorios_pdf' => 'crear_directorios_pdf', 
            'mv_test_cotizacion_demo' => 'test_cotizacion_demo',
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
        // Log directo al archivo
        $log_file = MODULO_VENTAS_PLUGIN_DIR . 'debug-cotizacion.log';
        file_put_contents($log_file, "\n=== " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
        file_put_contents($log_file, "INICIO guardar_cotizacion\n", FILE_APPEND);
        
        try {
            file_put_contents($log_file, "Verificando permisos...\n", FILE_APPEND);
            mv_ajax_check_permissions('create_cotizaciones', 'modulo_ventas_nonce');
            file_put_contents($log_file, "Permisos OK\n", FILE_APPEND);
            
            // Validar datos
            if (!isset($_POST['datos_generales']) || !isset($_POST['items'])) {
                file_put_contents($log_file, "ERROR: Datos incompletos\n", FILE_APPEND);
                wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
            }
            
            file_put_contents($log_file, "Datos recibidos OK\n", FILE_APPEND);
            
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
                    'producto_id'         => isset($item['producto_id']) ? intval($item['producto_id']) : 0,
                    'variacion_id'        => isset($item['variacion_id']) ? intval($item['variacion_id']) : 0,
                    'almacen_id'          => isset($item['almacen_id']) ? intval($item['almacen_id']) : null,
                    'sku'                 => isset($item['sku']) ? sanitize_text_field($item['sku']) : '',
                    'nombre'              => isset($item['nombre']) ? sanitize_text_field($item['nombre']) : '',
                    'descripcion'         => isset($item['descripcion']) ? sanitize_textarea_field($item['descripcion']) : '',
                    'cantidad'            => isset($item['cantidad']) ? floatval($item['cantidad']) : 0,
                    'precio_unitario'     => isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : 0,
                    'precio_original'     => isset($item['precio_original']) ? floatval($item['precio_original']) : (isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : 0),
                    'descuento_monto'     => isset($item['descuento_monto']) ? floatval($item['descuento_monto']) : 0,
                    'descuento_porcentaje'=> isset($item['descuento_porcentaje']) ? floatval($item['descuento_porcentaje']) : 0,
                    'tipo_descuento'      => isset($item['tipo_descuento']) ? sanitize_text_field($item['tipo_descuento']) : 'monto',
                    'subtotal'            => isset($item['subtotal']) ? floatval($item['subtotal']) : 0,
                    'notas'               => isset($item['notas']) ? sanitize_textarea_field($item['notas']) : '',
                    'stock_disponible'    => isset($item['stock_disponible']) ? intval($item['stock_disponible']) : null
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

            // Antes de crear la cotización
            file_put_contents($log_file, "Llamando a crear_cotizacion...\n", FILE_APPEND);
            $cotizacion_id = $this->cotizaciones->crear_cotizacion($datos_generales, $items);
            file_put_contents($log_file, "Resultado crear_cotizacion: " . print_r($cotizacion_id, true) . "\n", FILE_APPEND);
            
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
        } catch (Exception $e) {
            file_put_contents($log_file, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($log_file, "TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            file_put_contents($log_file, "ERROR FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($log_file, "TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            wp_send_json_error(array('message' => 'Error fatal: ' . $e->getMessage()));
        }
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

    /*
     * Generar PDF de cotización
     */
    public function generar_pdf_cotizacion() {
        // Debug inicial
        error_log('=== MODULO_VENTAS_AJAX: generar_pdf_cotizacion() INICIADO ===');
        error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
        error_log('POST data: ' . print_r($_POST, true));
        error_log('GET data: ' . print_r($_GET, true));
        
        // === DETERMINAR MÉTODO Y PARÁMETROS ===
        $is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
        $is_get = $_SERVER['REQUEST_METHOD'] === 'GET';
        
        // Obtener parámetros según el método
        if ($is_post) {
            $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
            $accion = isset($_POST['accion']) ? sanitize_text_field($_POST['accion']) : 'preview';
            $nonce_value = isset($_POST['nonce']) ? $_POST['nonce'] : '';
            $nonce_action = 'modulo_ventas_nonce';
        } else {
            // Petición GET
            $cotizacion_id = isset($_GET['cotizacion_id']) ? intval($_GET['cotizacion_id']) : 0;
            $accion = isset($_GET['modo']) ? sanitize_text_field($_GET['modo']) : 'preview';
            $nonce_value = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
            $nonce_action = 'mv_pdf_cotizacion_' . $cotizacion_id;
        }
        
        error_log("MODULO_VENTAS_AJAX: Parámetros detectados - ID: {$cotizacion_id}, Acción: {$accion}, Método: " . $_SERVER['REQUEST_METHOD']);
        
        // === VERIFICACIÓN DE NONCE ===
        $nonce_valid = false;
        if ($is_post) {
            $nonce_valid = wp_verify_nonce($nonce_value, $nonce_action);
            if (!$nonce_valid) {
                error_log('MODULO_VENTAS_AJAX: Nonce POST inválido. Esperado: ' . $nonce_action . ', Recibido: ' . $nonce_value);
            }
        } else {
            $nonce_valid = wp_verify_nonce($nonce_value, $nonce_action);
            if (!$nonce_valid) {
                error_log('MODULO_VENTAS_AJAX: Nonce GET inválido. Esperado: ' . $nonce_action . ', Recibido: ' . $nonce_value);
            }
        }
        
        if (!$nonce_valid) {
            if ($is_post) {
                wp_send_json_error(array('message' => 'Nonce inválido'));
                return;
            } else {
                wp_die('Enlace de seguridad inválido', 'Error', array('response' => 403));
                return;
            }
        }
        
        // === VERIFICACIÓN DE PERMISOS ===
        if (!current_user_can('view_cotizaciones')) {
            error_log('MODULO_VENTAS_AJAX: Usuario sin permisos');
            if ($is_post) {
                wp_send_json_error(array('message' => 'Sin permisos para generar PDFs'));
                return;
            } else {
                wp_die('Sin permisos para ver PDFs', 'Error', array('response' => 403));
                return;
            }
        }
        
        // === VALIDACIÓN DE PARÁMETROS ===
        if (!$cotizacion_id) {
            error_log('MODULO_VENTAS_AJAX: ID de cotización inválido: ' . $cotizacion_id);
            if ($is_post) {
                wp_send_json_error(array('message' => 'ID de cotización inválido'));
                return;
            } else {
                wp_die('ID de cotización inválido', 'Error', array('response' => 400));
                return;
            }
        }
        
        try {
            // === VERIFICAR COTIZACIÓN ===
            if (!$this->db->cotizacion_puede_generar_pdf($cotizacion_id)) {
                $mensaje = 'Esta cotización no puede generar PDF. Debe tener productos y datos completos.';
                if ($is_post) {
                    wp_send_json_error(array('message' => $mensaje));
                    return;
                } else {
                    wp_die($mensaje, 'Error', array('response' => 400));
                    return;
                }
            }
            
            // === CARGAR TCPDF ===
            if (!class_exists('TCPDF')) {
                $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
                if (file_exists($autoload_path)) {
                    require_once $autoload_path;
                }
            }
            
            if (!class_exists('TCPDF')) {
                $mensaje = 'TCPDF no está disponible. Ejecute composer install.';
                if ($is_post) {
                    wp_send_json_error(array('message' => $mensaje));
                    return;
                } else {
                    wp_die($mensaje, 'Error', array('response' => 500));
                    return;
                }
            }
            
            // === GENERAR PDF ===
            error_log("MODULO_VENTAS_AJAX: Generando PDF para cotización {$cotizacion_id}...");

            // USAR EL NUEVO SISTEMA DE PLANTILLAS que genera archivos locales
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates.php';
            $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();

            $pdf_result = $pdf_templates->generar_pdf_cotizacion($cotizacion_id);

            if (is_wp_error($pdf_result)) {
                error_log('MODULO_VENTAS_AJAX: Error generando PDF: ' . $pdf_result->get_error_message());
                $mensaje = 'Error generando PDF: ' . $pdf_result->get_error_message();
                if ($is_post) {
                    wp_send_json_error(array('message' => $mensaje));
                    return;
                } else {
                    wp_die($mensaje, 'Error PDF', array('response' => 500));
                    return;
                }
            }

            // $pdf_result puede ser una URL, necesitamos convertirla a path local
            if (filter_var($pdf_result, FILTER_VALIDATE_URL)) {
                // Es una URL, convertir a path local
                $upload_dir = wp_upload_dir();
                $upload_url = $upload_dir['baseurl'] . '/modulo-ventas/pdfs/';
                $upload_path = $upload_dir['basedir'] . '/modulo-ventas/pdfs/';
                
                if (strpos($pdf_result, $upload_url) === 0) {
                    $pdf_path = str_replace($upload_url, $upload_path, $pdf_result);
                    $pdf_url = $pdf_result; // Guardamos la URL también
                } else {
                    // URL externa o inesperada
                    error_log('MODULO_VENTAS_AJAX: URL inesperada: ' . $pdf_result);
                    $mensaje = 'URL de PDF inesperada';
                    if ($is_post) {
                        wp_send_json_error(array('message' => $mensaje));
                        return;
                    } else {
                        wp_die($mensaje, 'Error PDF', array('response' => 500));
                        return;
                    }
                }
            } else {
                // Es un path local
                $pdf_path = $pdf_result;
                
                // Convertir path a URL
                $upload_dir = wp_upload_dir();
                $upload_path = $upload_dir['basedir'] . '/modulo-ventas/pdfs/';
                $upload_url = $upload_dir['baseurl'] . '/modulo-ventas/pdfs/';
                
                if (strpos($pdf_path, $upload_path) === 0) {
                    $pdf_url = str_replace($upload_path, $upload_url, $pdf_path);
                } else {
                    // Path fuera del directorio esperado
                    $filename = basename($pdf_path);
                    $pdf_url = $upload_url . $filename;
                }
            }
            
            // === VERIFICAR ARCHIVO ===
            if (!file_exists($pdf_path)) {
                error_log('MODULO_VENTAS_AJAX: Archivo PDF no existe en path: ' . $pdf_path);
                error_log('MODULO_VENTAS_AJAX: PDF result original: ' . $pdf_result);
                
                $mensaje = 'El archivo PDF no se pudo crear en la ubicación esperada';
                if ($is_post) {
                    wp_send_json_error(array(
                        'message' => $mensaje,
                        'debug' => array(
                            'pdf_result' => $pdf_result,
                            'pdf_path' => $pdf_path,
                            'file_exists' => false,
                            'is_url' => filter_var($pdf_result, FILTER_VALIDATE_URL)
                        )
                    ));
                    return;
                } else {
                    wp_die($mensaje, 'Error PDF', array('response' => 500));
                    return;
                }
            }

            error_log('MODULO_VENTAS_AJAX: PDF generado exitosamente en: ' . $pdf_path);
            error_log('MODULO_VENTAS_AJAX: PDF URL: ' . $pdf_url);
            
            // === RESPUESTA SEGÚN MÉTODO ===
            if ($is_post) {
                // AJAX POST - Retornar JSON con URLs DIRECTAS
                $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
                $filename = basename($pdf_path);
                $file_size = filesize($pdf_path);
                
                wp_send_json_success(array(
                    'message' => 'PDF generado exitosamente',
                    'filename' => $filename,
                    'preview_url' => $pdf_url,  // URL directa para preview
                    'download_url' => $pdf_url, // URL directa para descarga
                    'direct_url' => $pdf_url,   // URL directa al archivo
                    'folio' => $cotizacion ? $cotizacion->folio : "COT-{$cotizacion_id}",
                    'file_size' => number_format($file_size) . ' bytes',
                    'debug' => array(
                        'pdf_result_original' => $pdf_result,
                        'pdf_path' => $pdf_path,
                        'pdf_url' => $pdf_url,
                        'file_exists' => file_exists($pdf_path),
                        'file_size' => $file_size,
                        'method_used' => 'PDF_Templates'
                    )
                ));
                
            } else {
                // GET - Servir archivo directamente
                $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
                $filename = 'cotizacion_' . ($cotizacion ? $cotizacion->folio : $cotizacion_id) . '.pdf';
                $filename = sanitize_file_name($filename);
                
                // Limpiar cualquier salida previa
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Configurar headers según la acción
                if ($accion === 'download') {
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                } else {
                    header('Content-Disposition: inline; filename="' . $filename . '"');
                }
                
                header('Content-Type: application/pdf');
                header('Content-Length: ' . filesize($pdf_path));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                error_log('MODULO_VENTAS_AJAX: Enviando archivo vía GET: ' . $filename);
                
                // Enviar archivo
                readfile($pdf_path);
                exit;
            }
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS_AJAX: Exception: ' . $e->getMessage());
            error_log('MODULO_VENTAS_AJAX: Exception trace: ' . $e->getTraceAsString());
            
            $mensaje = 'Error interno: ' . $e->getMessage();
            if ($is_post) {
                wp_send_json_error(array(
                    'message' => $mensaje,
                    'debug' => array(
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine(),
                        'cotizacion_id' => $cotizacion_id
                    )
                ));
            } else {
                wp_die($mensaje, 'Error PDF', array('response' => 500));
            }
        }
    }

    /**
     * Generar Plantilla PDF
     */
    public function ajax_generar_pdf_plantilla() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos', 'Error', array('response' => 403));
        }
        
        // Verificar nonce de manera flexible
        $nonce_valido = false;
        if (isset($_GET['nonce'])) {
            $nonces_posibles = array('mv_pdf_templates_nonce', 'mv_pdf_templates', 'mv_nonce');
            foreach ($nonces_posibles as $nonce_name) {
                if (wp_verify_nonce($_GET['nonce'], $nonce_name)) {
                    $nonce_valido = true;
                    break;
                }
            }
        }
        
        if (!$nonce_valido) {
            wp_die('Nonce inválido', 'Error de Seguridad', array('response' => 403));
        }
        
        $plantilla_id = isset($_GET['plantilla_id']) ? intval($_GET['plantilla_id']) : 0;
        $usar_datos_reales = isset($_GET['usar_datos_reales']) ? (bool)$_GET['usar_datos_reales'] : true;
        
        if (!$plantilla_id) {
            wp_die('ID de plantilla inválido', 'Error', array('response' => 400));
        }
        
        try {
            // Obtener plantilla
            global $wpdb;
            $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
            
            $plantilla = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tabla_plantillas WHERE id = %d",
                $plantilla_id
            ));
            
            if (!$plantilla) {
                wp_die('Plantilla no encontrada', 'Error', array('response' => 404));
            }
            
            // Crear procesador
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            $processor = new Modulo_Ventas_PDF_Template_Processor();
            
            // Obtener datos
            if ($usar_datos_reales) {
                try {
                    $tipo_documento = isset($plantilla->tipo_documento) ? $plantilla->tipo_documento : 'cotizacion';
                    $datos = $processor->obtener_datos_preview($tipo_documento);
                } catch (Exception $e) {
                    $processor->generar_datos_prueba($plantilla->tipo_documento);
                    $datos = $processor->cotizacion_data;
                }
            } else {
                $processor->generar_datos_prueba($plantilla->tipo_documento);
                $datos = $processor->cotizacion_data;
            }
            
            // Procesar plantilla
            $html_procesado = $processor->procesar_template($plantilla->contenido_html);
            $css_procesado = $processor->procesar_css($plantilla->contenido_css);
            $documento_final = $processor->generar_documento_final($html_procesado, $css_procesado);
            
            // Generar PDF usando TCPDF
            if (!class_exists('TCPDF')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'vendor/tcpdf/tcpdf.php';
            }
            
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configuración del PDF
            $pdf->SetCreator('Módulo de Ventas');
            $pdf->SetAuthor('Sistema de Gestión');
            $pdf->SetTitle('Preview de Plantilla - ' . $plantilla->nombre);
            
            // Configuración de márgenes
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);
            
            // Agregar página
            $pdf->AddPage();
            
            // Escribir HTML
            $pdf->writeHTML($documento_final, true, false, true, false, '');
            
            // Enviar PDF al navegador
            $filename = 'preview_plantilla_' . $plantilla_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
            
            $pdf->Output($filename, 'I'); // 'I' = inline en navegador
            exit;
            
        } catch (Exception $e) {
            error_log('Error generando PDF: ' . $e->getMessage());
            wp_die('Error al generar PDF: ' . $e->getMessage(), 'Error PDF', array('response' => 500));
        }
    }

    /**
     * Manejar peticiones GET para PDF (preview/download directo)
     */
    private function manejar_pdf_via_get() {
        // Activar output de errores para debug
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        
        // Log inicio del proceso
        error_log('MODULO_VENTAS: Iniciando manejar_pdf_via_get()');
        
        // Verificar parámetros
        $cotizacion_id = isset($_GET['cotizacion_id']) ? intval($_GET['cotizacion_id']) : 0;
        $modo = isset($_GET['modo']) ? sanitize_text_field($_GET['modo']) : 'preview';
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
        
        error_log("MODULO_VENTAS: Parámetros - ID: {$cotizacion_id}, Modo: {$modo}");
        
        // Verificar nonce
        if (!wp_verify_nonce($nonce, 'mv_pdf_cotizacion_' . $cotizacion_id)) {
            error_log('MODULO_VENTAS: Error - Nonce inválido');
            wp_die('Acceso no autorizado', 'Error', array('response' => 403));
        }
        
        error_log('MODULO_VENTAS: Nonce válido');
        
        // Verificar permisos
        if (!current_user_can('view_cotizaciones')) {
            error_log('MODULO_VENTAS: Error - Sin permisos');
            wp_die('Sin permisos', 'Error', array('response' => 403));
        }
        
        error_log('MODULO_VENTAS: Permisos OK');
        
        // Verificar cotización
        if (!$cotizacion_id || !$this->db->cotizacion_puede_generar_pdf($cotizacion_id)) {
            error_log('MODULO_VENTAS: Error - Cotización no válida para PDF');
            wp_die('Cotización no válida para PDF', 'Error', array('response' => 400));
        }
        
        error_log('MODULO_VENTAS: Cotización válida para PDF');
        
        try {
            // Asegurar que TCPDF esté disponible
            if (!class_exists('TCPDF')) {
                error_log('MODULO_VENTAS: TCPDF no está cargado, intentando cargar...');
                
                // Intentar autoload
                $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
                if (file_exists($autoload_path)) {
                    require_once $autoload_path;
                    error_log('MODULO_VENTAS: Autoload cargado');
                }
                
                if (!class_exists('TCPDF')) {
                    $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                    if (file_exists($tcpdf_path)) {
                        require_once $tcpdf_path;
                        error_log('MODULO_VENTAS: TCPDF cargado directamente');
                    }
                }
            }
            
            if (!class_exists('TCPDF')) {
                error_log('MODULO_VENTAS: ERROR CRÍTICO - TCPDF no disponible');
                wp_die('TCPDF no disponible', 'Error', array('response' => 500));
            }
            
            error_log('MODULO_VENTAS: TCPDF disponible, iniciando generación');
            
            // Intentar generar PDF
            $pdf_generator = new Modulo_Ventas_PDF();
            error_log('MODULO_VENTAS: Instancia PDF creada');
            
            $pdf_path = $pdf_generator->generar_pdf_cotizacion($cotizacion_id);
            error_log('MODULO_VENTAS: PDF generado, resultado: ' . (is_wp_error($pdf_path) ? 'ERROR' : 'OK'));
            
            if (is_wp_error($pdf_path)) {
                error_log('MODULO_VENTAS: Error en generación: ' . $pdf_path->get_error_message());
                wp_die('Error al generar PDF: ' . $pdf_path->get_error_message(), 'Error', array('response' => 500));
            }
            
            // Verificar que el archivo existe
            if (!file_exists($pdf_path)) {
                error_log('MODULO_VENTAS: ERROR - Archivo PDF no existe: ' . $pdf_path);
                wp_die('PDF no encontrado en: ' . $pdf_path, 'Error', array('response' => 404));
            }
            
            error_log('MODULO_VENTAS: Archivo PDF existe: ' . $pdf_path . ' (' . filesize($pdf_path) . ' bytes)');
            
            // Obtener nombre de archivo para download
            $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
            $filename = 'cotizacion_' . $cotizacion->folio . '.pdf';
            
            // Configurar headers según el modo
            if ($modo === 'download') {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $filename . '"');
            }
            
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($pdf_path));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            error_log('MODULO_VENTAS: Headers configurados, enviando archivo');
            
            // Limpiar output y enviar archivo
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            readfile($pdf_path);
            error_log('MODULO_VENTAS: Archivo enviado exitosamente');
            exit;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS: Exception en PDF GET: ' . $e->getMessage());
            error_log('MODULO_VENTAS: Exception trace: ' . $e->getTraceAsString());
            wp_die('Error interno: ' . $e->getMessage(), 'Error', array('response' => 500));
        } catch (Error $e) {
            error_log('MODULO_VENTAS: Error fatal en PDF GET: ' . $e->getMessage());
            error_log('MODULO_VENTAS: Error trace: ' . $e->getTraceAsString());
            wp_die('Error fatal: ' . $e->getMessage(), 'Error', array('response' => 500));
        }
    }

    /**
     * ACTUALIZAR EL MÉTODO servir_pdf() para soporte de diferentes tipos
     */
    public function servir_pdf() {
        // Verificar parámetros
        $filename = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'test';
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        
        if (empty($filename)) {
            wp_die('Archivo no especificado', 'Error', array('response' => 400));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($nonce, 'mv_pdf_access_' . $filename)) {
            wp_die('Acceso no autorizado', 'Error', array('response' => 403));
        }
        
        // Verificar permisos según tipo
        if ($type === 'cotizacion' && !current_user_can('view_cotizaciones')) {
            wp_die('Sin permisos', 'Error', array('response' => 403));
        }
        
        // Determinar directorio según tipo
        $upload_dir = wp_upload_dir();
        if ($type === 'cotizacion') {
            $pdf_path = $upload_dir['basedir'] . '/modulo-ventas/pdfs/' . $filename;
            $allowed_dir = realpath($upload_dir['basedir'] . '/modulo-ventas/pdfs/');
        } else {
            $pdf_path = $upload_dir['basedir'] . '/test-pdfs/' . $filename;
            $allowed_dir = realpath($upload_dir['basedir'] . '/test-pdfs/');
        }
        
        // Verificar que el archivo existe y es un PDF
        if (!file_exists($pdf_path) || pathinfo($pdf_path, PATHINFO_EXTENSION) !== 'pdf') {
            wp_die('Archivo no encontrado', 'Error', array('response' => 404));
        }
        
        // Verificar que es un archivo dentro del directorio permitido
        $real_path = realpath($pdf_path);
        if (!$allowed_dir || strpos($real_path, $allowed_dir) !== 0) {
            wp_die('Acceso denegado', 'Error', array('response' => 403));
        }
        
        // Servir el archivo
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($pdf_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Leer y enviar el archivo
        readfile($pdf_path);
        exit;
    }

    /**
     * AJAX: Test PDF simple
     */
    public function test_pdf_simple() {
        // Debug inicial
        //error_log('MODULO_VENTAS: test_pdf_simple() INICIADO');
        //error_log('MODULO_VENTAS: doing_ajax=' . (wp_doing_ajax() ? 'SI' : 'NO'));
        //error_log('MODULO_VENTAS: TCPDF existe=' . (class_exists('TCPDF') ? 'SI' : 'NO'));
        
        // Verificar nonce con más flexibilidad
        $nonce_valido = false;
        
        if (isset($_POST['nonce'])) {
            $nonce_valido = wp_verify_nonce($_POST['nonce'], 'mv_diagnostico');
            //error_log('MODULO_VENTAS: Nonce verification result: ' . ($nonce_valido ? 'VALIDO' : 'INVALIDO'));
        } else {
            //error_log('MODULO_VENTAS: No se encontró nonce en $_POST');
        }
        
        if (!$nonce_valido) {
            wp_send_json_error(array(
                'message' => 'Error de seguridad: nonce inválido',
                'debug' => array(
                    'nonce_sent' => isset($_POST['nonce']) ? $_POST['nonce'] : 'No enviado',
                    'expected_action' => 'mv_diagnostico'
                )
            ));
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos de administrador'));
            return;
        }
        
        // Intentar cargar TCPDF si no está disponible
        if (!class_exists('TCPDF')) {
            //error_log('MODULO_VENTAS: TCPDF no disponible, intentando cargar...');
            
            // Intentar autoload
            $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
                //error_log('MODULO_VENTAS: Autoload cargado');
            }
            
            // Intentar carga directa
            if (!class_exists('TCPDF')) {
                $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                if (file_exists($tcpdf_path)) {
                    require_once $tcpdf_path;
                    //error_log('MODULO_VENTAS: TCPDF cargado directamente');
                }
            }
            
            // Verificar si ahora está disponible
            if (!class_exists('TCPDF')) {
                //error_log('MODULO_VENTAS: ERROR - No se pudo cargar TCPDF');
                wp_send_json_error(array(
                    'message' => 'TCPDF no está disponible',
                    'debug' => array(
                        'autoload_exists' => file_exists($autoload_path),
                        'tcpdf_direct_exists' => file_exists($tcpdf_path),
                        'plugin_dir' => MODULO_VENTAS_PLUGIN_DIR
                    )
                ));
                return;
            }
        }
        
        //error_log('MODULO_VENTAS: TCPDF confirmado disponible, iniciando generación');
        
        try {
            // Test básico de creación de PDF
            $pdf = new TCPDF();
            //error_log('MODULO_VENTAS: TCPDF instanciado exitosamente');
            
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 15, 'TEST AJAX EXITOSO - ' . date('Y-m-d H:i:s'), 0, 1, 'C');
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, 'Este PDF fue generado via AJAX', 0, 1, 'C');
            $pdf->Cell(0, 10, 'Módulo de Ventas v' . MODULO_VENTAS_VERSION, 0, 1, 'C');
            
            //error_log('MODULO_VENTAS: Contenido PDF agregado');
            
            // Preparar ruta - USAR DIRECTORIO PÚBLICO
            $upload_dir = wp_upload_dir();
            
            // Probar primero en uploads raíz (más accesible)
            $pdf_dir = $upload_dir['basedir'] . '/test-pdfs';
            $filename = 'test_ajax_' . date('Ymd_His') . '.pdf';
            $filepath = $pdf_dir . '/' . $filename;
            
            // Crear directorio si no existe
            if (!file_exists($pdf_dir)) {
                $created = wp_mkdir_p($pdf_dir);
                //error_log('MODULO_VENTAS: Directorio test-pdfs ' . ($created ? 'creado' : 'no se pudo crear'));
                
                if (!$created) {
                    wp_send_json_error(array(
                        'message' => 'No se pudo crear directorio test-pdfs',
                        'debug' => array(
                            'pdf_dir' => $pdf_dir,
                            'upload_dir' => $upload_dir['basedir'],
                            'upload_writable' => is_writable($upload_dir['basedir'])
                        )
                    ));
                    return;
                }
                
                // Crear .htaccess permisivo en el nuevo directorio
                $htaccess_content = "# Permitir acceso a PDFs de test\n";
                $htaccess_content .= "Options +Indexes\n";
                $htaccess_content .= "<Files \"*.pdf\">\n";
                $htaccess_content .= "    Order allow,deny\n";
                $htaccess_content .= "    Allow from all\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($pdf_dir . '/.htaccess', $htaccess_content);
                //error_log('MODULO_VENTAS: .htaccess creado en test-pdfs');
            }
            
            // Guardar PDF
            $output_result = $pdf->Output($filepath, 'F');
            //error_log('MODULO_VENTAS: PDF Output resultado: ' . ($output_result ? 'exitoso' : 'falló'));
            
            // Verificar que se guardó
            if (file_exists($filepath)) {
                // URL directa al nuevo directorio
                $file_url = $upload_dir['baseurl'] . '/test-pdfs/' . $filename;
                
                // TAMBIÉN crear URL de descarga segura
                $download_url = admin_url('admin-ajax.php') . '?' . http_build_query(array(
                    'action' => 'mv_descargar_pdf_test',
                    'file' => $filename,
                    'nonce' => wp_create_nonce('mv_pdf_download_' . $filename)
                ));
                
                $filesize = filesize($filepath);
                
                //error_log('MODULO_VENTAS: PDF guardado exitosamente: ' . $filepath . ' (' . $filesize . ' bytes)');
                
                wp_send_json_success(array(
                    'message' => '¡Test PDF AJAX exitoso!',
                    'file_path' => $filepath,
                    'file_url' => $file_url,
                    'download_url' => $download_url,
                    'file_size' => number_format($filesize) . ' bytes',
                    'filename' => $filename,
                    'debug_info' => array(
                        'tcpdf_version' => defined('TCPDF_VERSION') ? TCPDF_VERSION : 'Desconocida',
                        'upload_dir' => $upload_dir['basedir'],
                        'pdf_dir' => $pdf_dir,
                        'file_exists' => true,
                        'file_readable' => is_readable($filepath),
                        'directory_public' => true
                    )
                ));
                
            } else {
                //error_log('MODULO_VENTAS: ERROR - archivo no se guardó: ' . $filepath);
                wp_send_json_error(array(
                    'message' => 'PDF se generó pero no se guardó en disco',
                    'debug' => array(
                        'filepath' => $filepath,
                        'directory_exists' => file_exists($pdf_dir),
                        'directory_writable' => is_writable($pdf_dir),
                        'output_result' => $output_result
                    )
                ));
            }
            
        } catch (Exception $e) {
            //error_log('MODULO_VENTAS: Exception en test_pdf_simple: ' . $e->getMessage());
            //error_log('MODULO_VENTAS: Exception trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => 'Exception durante generación: ' . $e->getMessage(),
                'debug' => array(
                    'exception_type' => get_class($e),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                    'tcpdf_available' => class_exists('TCPDF')
                )
            ));
            
        } catch (Error $e) {
            //error_log('MODULO_VENTAS: Error fatal en test_pdf_simple: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => 'Error fatal: ' . $e->getMessage(),
                'debug' => array(
                    'error_type' => get_class($e),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                )
            ));
        }
    }
    
    /**
     * AJAX: Crear directorios PDF
     */
    public function crear_directorios_pdf() {
        if (!check_ajax_referer('mv_diagnostico', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos'));
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/modulo-ventas';
        
        $directorios = array(
            $base_dir,
            $base_dir . '/pdfs',
            $base_dir . '/logos'
        );
        
        $resultado = array();
        
        foreach ($directorios as $dir) {
            if (!file_exists($dir)) {
                if (wp_mkdir_p($dir)) {
                    $resultado[] = 'Creado: ' . basename($dir);
                } else {
                    $resultado[] = 'Error creando: ' . basename($dir);
                }
            } else {
                $resultado[] = 'Ya existe: ' . basename($dir);
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Directorios procesados',
            'detalles' => $resultado
        ));
    }

    /**
     * Descargar PDF de test de forma segura
     */
    public function descargar_pdf_test() {
        // Verificar parámetros
        $filename = isset($_GET['file']) ? sanitize_file_name($_GET['file']) : '';
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        
        if (empty($filename)) {
            wp_die('Archivo no especificado', 'Error', array('response' => 400));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($nonce, 'mv_pdf_download_' . $filename)) {
            wp_die('Acceso no autorizado', 'Error', array('response' => 403));
        }
        
        // Construir ruta del archivo
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/test-pdfs/' . $filename;
        
        // Verificar que el archivo existe y es un PDF
        if (!file_exists($pdf_path) || pathinfo($pdf_path, PATHINFO_EXTENSION) !== 'pdf') {
            wp_die('Archivo no encontrado', 'Error', array('response' => 404));
        }
        
        // Verificar que es un archivo dentro del directorio permitido
        $real_path = realpath($pdf_path);
        $allowed_dir = realpath($upload_dir['basedir'] . '/test-pdfs/');
        
        if (strpos($real_path, $allowed_dir) !== 0) {
            wp_die('Acceso denegado', 'Error', array('response' => 403));
        }
        
        // Servir el archivo
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($pdf_path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Leer y enviar el archivo
        readfile($pdf_path);
        exit;
    }
    
    /**
     * AJAX: Test con cotización demo
     */
    public function test_cotizacion_demo() {
        check_ajax_referer('mv_diagnostico', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        try {
            $pdf_generator = new Modulo_Ventas_PDF();
            
            // Crear datos demo
            $cotizacion_demo = (object) array(
                'id' => 999,
                'folio' => 'DEMO-001',
                'fecha' => date('Y-m-d'),
                'fecha_expiracion' => date('Y-m-d', strtotime('+30 days')),
                'subtotal' => 50000,
                'total' => 59500,
                'estado' => 'pendiente',
                'observaciones' => 'Esta es una cotización de demostración generada para probar el sistema PDF.',
                'incluir_iva' => true,
                'costo_envio' => 0,
                'descuento_global' => 0
            );
            
            $cliente_demo = (object) array(
                'razon_social' => 'Cliente de Demostración S.A.',
                'rut' => '12.345.678-9',
                'email' => 'demo@ejemplo.com',
                'telefono' => '+56 9 1234 5678',
                'direccion_facturacion' => 'Av. Demo 123, Santiago'
            );
            
            $items_demo = array(
                (object) array(
                    'nombre' => 'Producto Demo 1',
                    'sku' => 'DEMO-001',
                    'cantidad' => 2,
                    'precio' => 15000,
                    'descuento' => 0,
                    'descuento_tipo' => 'porcentaje',
                    'subtotal' => 30000
                ),
                (object) array(
                    'nombre' => 'Producto Demo 2', 
                    'sku' => 'DEMO-002',
                    'cantidad' => 1,
                    'precio' => 20000,
                    'descuento' => 0,
                    'descuento_tipo' => 'porcentaje',
                    'subtotal' => 20000
                )
            );
            
            // Usar el método interno para generar PDF demo
            $resultado = $this->generar_pdf_demo($cotizacion_demo, $cliente_demo, $items_demo);
            
            if (is_wp_error($resultado)) {
                wp_send_json_error(array('message' => $resultado->get_error_message()));
            } else {
                $upload_dir = wp_upload_dir();
                $pdf_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $resultado);
                
                wp_send_json_success(array(
                    'message' => 'PDF de cotización demo generado exitosamente',
                    'pdf_path' => $resultado,
                    'pdf_url' => $pdf_url
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Generar PDF demo (método interno)
     */
    private function generar_pdf_demo($cotizacion, $cliente, $items) {
        if (!class_exists('TCPDF')) {
            return new WP_Error('tcpdf_missing', 'TCPDF no disponible');
        }
        
        try {
            $pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
            
            $pdf->SetCreator('Módulo de Ventas - Demo');
            $pdf->SetAuthor('Sistema Demo');
            $pdf->SetTitle('Cotización Demo');
            
            $pdf->SetMargins(15, 27, 15);
            $pdf->SetAutoPageBreak(TRUE, 25);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            $pdf->AddPage();
            
            // Header
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->SetTextColor(34, 113, 177);
            $pdf->Cell(0, 15, 'COTIZACIÓN DEMO', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 14);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 8, 'N° ' . $cotizacion->folio, 0, 1, 'C');
            $pdf->Ln(10);
            
            // Info empresa
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 8, get_bloginfo('name'), 0, 1, 'L');
            
            // Info cotización y cliente
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(95, 6, 'CLIENTE', 0, 0, 'L');
            $pdf->Cell(95, 6, 'INFORMACIÓN DE COTIZACIÓN', 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 10);
            $y_inicial = $pdf->GetY();
            
            // Cliente
            $pdf->Cell(95, 5, $cliente->razon_social, 0, 1, 'L');
            $pdf->Cell(95, 5, 'RUT: ' . $cliente->rut, 0, 1, 'L');
            
            // Cotización
            $pdf->SetXY(110, $y_inicial);
            $pdf->Cell(95, 5, 'Fecha: ' . date('d/m/Y', strtotime($cotizacion->fecha)), 0, 1, 'L');
            $pdf->SetX(110);
            $pdf->Cell(95, 5, 'Válida hasta: ' . date('d/m/Y', strtotime($cotizacion->fecha_expiracion)), 0, 1, 'L');
            
            $pdf->Ln(10);
            
            // Tabla productos
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(240, 240, 240);
            
            $w = array(80, 20, 25, 25, 30);
            $pdf->Cell($w[0], 8, 'Descripción', 1, 0, 'L', 1);
            $pdf->Cell($w[1], 8, 'Cant.', 1, 0, 'C', 1);
            $pdf->Cell($w[2], 8, 'Precio', 1, 0, 'R', 1);
            $pdf->Cell($w[3], 8, 'Desc.', 1, 0, 'R', 1);
            $pdf->Cell($w[4], 8, 'Subtotal', 1, 1, 'R', 1);
            
            $pdf->SetFont('helvetica', '', 9);
            foreach ($items as $item) {
                $pdf->Cell($w[0], 6, '[' . $item->sku . '] ' . $item->nombre, 1, 0, 'L');
                $pdf->Cell($w[1], 6, $item->cantidad, 1, 0, 'C');
                $pdf->Cell($w[2], 6, '$' . number_format($item->precio, 0), 1, 0, 'R');
                $pdf->Cell($w[3], 6, '-', 1, 0, 'R');
                $pdf->Cell($w[4], 6, '$' . number_format($item->subtotal, 0), 1, 1, 'R');
            }
            
            // Totales
            $pdf->Ln(5);
            $x_totales = 130;
            $pdf->SetFont('helvetica', '', 10);
            
            $pdf->SetXY($x_totales, $pdf->GetY());
            $pdf->Cell(35, 6, 'Subtotal:', 0, 0, 'L');
            $pdf->Cell(35, 6, '$' . number_format($cotizacion->subtotal, 0), 0, 1, 'R');
            
            $pdf->SetX($x_totales);
            $pdf->Cell(35, 6, 'IVA (19%):', 0, 0, 'L');
            $iva = $cotizacion->total - $cotizacion->subtotal;
            $pdf->Cell(35, 6, '$' . number_format($iva, 0), 0, 1, 'R');
            
            $pdf->SetX($x_totales);
            $pdf->Line($x_totales, $pdf->GetY(), $x_totales + 70, $pdf->GetY());
            $pdf->Ln(3);
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX($x_totales);
            $pdf->Cell(35, 8, 'TOTAL:', 0, 0, 'L');
            $pdf->Cell(35, 8, '$' . number_format($cotizacion->total, 0), 0, 1, 'R');
            
            // Observaciones
            if (!empty($cotizacion->observaciones)) {
                $pdf->Ln(10);
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, 'OBSERVACIONES', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 5, $cotizacion->observaciones, 0, 'L');
            }
            
            // Guardar
            $upload_dir = wp_upload_dir();
            $filename = 'cotizacion_demo_' . date('Y-m-d_H-i-s') . '.pdf';
            $filepath = $upload_dir['basedir'] . '/modulo-ventas/pdfs/' . $filename;
            
            wp_mkdir_p(dirname($filepath));
            $pdf->Output($filepath, 'F');
            
            return $filepath;
            
        } catch (Exception $e) {
            return new WP_Error('pdf_error', $e->getMessage());
        }
    }

    /**
     * Manejar requests de PDF vía GET (para preview/download)
     */
    public function manejar_pdf_requests() {
        // Solo procesar si es una petición de PDF
        if (!isset($_GET['mv_pdf_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['mv_pdf_action']);
        $cotizacion_id = isset($_GET['cotizacion_id']) ? intval($_GET['cotizacion_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
        
        // Verificar nonce
        if (!wp_verify_nonce($nonce, 'mv_pdf_cotizacion_' . $cotizacion_id)) {
            wp_die('Enlace de seguridad inválido', 'Error', array('response' => 403));
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('view_cotizaciones')) {
            wp_die('Sin permisos para ver PDFs', 'Error', array('response' => 403));
            return;
        }
        
        try {
            // Generar o obtener PDF
            $pdf_generator = new Modulo_Ventas_PDF();
            $pdf_path = $pdf_generator->generar_pdf_cotizacion($cotizacion_id);
            
            if (is_wp_error($pdf_path)) {
                wp_die('Error generando PDF: ' . $pdf_path->get_error_message(), 'Error PDF');
                return;
            }
            
            if (!file_exists($pdf_path)) {
                wp_die('Archivo PDF no encontrado', 'Error PDF');
                return;
            }
            
            // Obtener info de cotización para nombre de archivo
            $db = new Modulo_Ventas_DB();
            $cotizacion = $db->obtener_cotizacion($cotizacion_id);
            $filename = 'cotizacion_' . ($cotizacion ? $cotizacion->folio : $cotizacion_id) . '.pdf';
            $filename = sanitize_file_name($filename);
            
            // Limpiar cualquier salida previa
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Configurar headers según la acción
            if ($action === 'download') {
                header('Content-Disposition: attachment; filename="' . $filename . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $filename . '"');
            }
            
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($pdf_path));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            // Enviar archivo
            readfile($pdf_path);
            exit;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS: Error en manejar_pdf_requests: ' . $e->getMessage());
            wp_die('Error interno: ' . $e->getMessage(), 'Error PDF');
        }
    }

    /**
     * AJAX: Obtener preview con datos reales
     */
    public function ajax_obtener_preview_real() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos'));
        }
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mv_pdf_templates_nonce')) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }
        
        $tipo_documento = isset($_POST['tipo_documento']) ? sanitize_text_field($_POST['tipo_documento']) : 'cotizacion';
        $html_template = isset($_POST['html']) ? $_POST['html'] : '';
        $css_template = isset($_POST['css']) ? $_POST['css'] : '';
        
        try {
            // Crear instancia del procesador
            $processor = new Modulo_Ventas_PDF_Template_Processor();
            
            // Obtener datos reales
            $datos_reales = $processor->obtener_datos_preview($tipo_documento);
            
            // Verificar si son datos reales o de prueba
            $es_datos_reales = !empty($datos_reales['cotizacion']['numero']) && $datos_reales['cotizacion']['numero'] !== 'COT-001';
            
            // Procesar template con datos reales
            $processor->cargar_datos($datos_reales);
            $html_procesado = $processor->procesar_template($html_template);
            $css_procesado = $processor->procesar_css($css_template);
            
            // Generar documento final
            $documento_final = $processor->generar_documento_final($html_procesado, $css_procesado);
            
            wp_send_json_success(array(
                'html' => $documento_final,
                'datos_usados' => array(
                    'tipo' => $es_datos_reales ? 'real' : 'prueba',
                    'documento' => $tipo_documento,
                    'mensaje' => $es_datos_reales 
                        ? 'Preview generado con datos de la última ' . $tipo_documento . ' real (' . $datos_reales['cotizacion']['numero'] . ')'
                        : 'Preview generado con datos de prueba (no hay ' . $tipo_documento . 's reales)'
                )
            ));
            
        } catch (Exception $e) {
            error_log('Error en preview real: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error al generar preview: ' . $e->getMessage()));
        }
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
            $cantidad = isset($item['cantidad']) ? floatval($item['cantidad']) : 0;
            $precio_unitario = isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : 0;
            $descuento_item = isset($item['descuento']) ? floatval($item['descuento']) : 0;
            $tipo_descuento_item = isset($item['tipo_descuento']) ? $item['tipo_descuento'] : 'monto';
            
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
            'razon_social'          => isset($datos['razon_social']) ? sanitize_text_field($datos['razon_social']) : '',
            'rut'                   => isset($datos['rut']) ? mv_limpiar_rut($datos['rut']) : '',
            'giro_comercial'        => isset($datos['giro_comercial']) ? sanitize_text_field($datos['giro_comercial']) : '',
            'telefono'              => isset($datos['telefono']) ? sanitize_text_field($datos['telefono']) : '',
            'email'                 => isset($datos['email']) ? sanitize_email($datos['email']) : '',
            'email_dte'             => isset($datos['email_dte']) ? sanitize_email($datos['email_dte']) : '',
            'direccion_facturacion' => isset($datos['direccion_facturacion']) ? sanitize_textarea_field($datos['direccion_facturacion']) : '',
            'comuna_facturacion'    => isset($datos['comuna_facturacion']) ? sanitize_text_field($datos['comuna_facturacion']) : '',
            'ciudad_facturacion'    => isset($datos['ciudad_facturacion']) ? sanitize_text_field($datos['ciudad_facturacion']) : '',
            'region_facturacion'    => isset($datos['region_facturacion']) ? sanitize_text_field($datos['region_facturacion']) : '',
            'pais_facturacion'      => isset($datos['pais_facturacion']) ? sanitize_text_field($datos['pais_facturacion']) : 'Chile'
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
            'id'                    => isset($cliente->id) ? $cliente->id : null,
            'razon_social'          => isset($cliente->razon_social) ? $cliente->razon_social : '',
            'rut'                   => isset($cliente->rut) ? mv_formatear_rut($cliente->rut) : '',
            'giro_comercial'        => isset($cliente->giro_comercial) ? $cliente->giro_comercial : '',
            'telefono'              => isset($cliente->telefono) ? $cliente->telefono : '',
            'email'                 => isset($cliente->email) ? $cliente->email : '',
            'email_dte'             => isset($cliente->email_dte) ? $cliente->email_dte : '',
            'direccion_facturacion' => isset($cliente->direccion_facturacion) ? $cliente->direccion_facturacion : '',
            'comuna_facturacion'    => isset($cliente->comuna_facturacion) ? $cliente->comuna_facturacion : '',
            'ciudad_facturacion'    => isset($cliente->ciudad_facturacion) ? $cliente->ciudad_facturacion : '',
            'region_facturacion'    => isset($cliente->region_facturacion) ? $cliente->region_facturacion : '',
            'pais_facturacion'      => isset($cliente->pais_facturacion) ? $cliente->pais_facturacion : '',
            'usar_misma_direccion'  => isset($cliente->usar_direccion_facturacion_para_envio) ? $cliente->usar_direccion_facturacion_para_envio : false,
            'direccion_envio'       => isset($cliente->direccion_envio) ? $cliente->direccion_envio : '',
            'comuna_envio'          => isset($cliente->comuna_envio) ? $cliente->comuna_envio : '',
            'ciudad_envio'          => isset($cliente->ciudad_envio) ? $cliente->ciudad_envio : '',
            'region_envio'          => isset($cliente->region_envio) ? $cliente->region_envio : '',
            'pais_envio'            => isset($cliente->pais_envio) ? $cliente->pais_envio : '',
            'user_id'               => isset($cliente->user_id) ? $cliente->user_id : null,
            'fecha_creacion'        => isset($cliente->fecha_creacion) ? mv_formato_fecha($cliente->fecha_creacion, true) : '',
            'meta'                  => isset($cliente->meta) ? $cliente->meta : array()
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
        // Verificar nonce de forma más flexible
        $nonce_valid = false;
        
        // Primero intentar con el nonce general
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'modulo_ventas_nonce')) {
            $nonce_valid = true;
        }
        // Si no, intentar con el nonce específico
        else if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mv_validar_rut')) {
            $nonce_valid = true;
        }
        // Intentar otros nombres de campo de nonce
        else {
            $nonce_fields = array('_ajax_nonce', 'security', '_wpnonce');
            foreach ($nonce_fields as $field) {
                if (isset($_POST[$field])) {
                    if (wp_verify_nonce($_POST[$field], 'modulo_ventas_nonce') || 
                        wp_verify_nonce($_POST[$field], 'mv_validar_rut')) {
                        $nonce_valid = true;
                        break;
                    }
                }
            }
        }
        
        if (!$nonce_valid) {            
            wp_send_json_error(array(
                'message' => __('Error de seguridad', 'modulo-ventas'),
                'debug' => 'Nonce inválido o faltante'
            ));
            return;
        }
        
        $rut = isset($_POST['rut']) ? sanitize_text_field($_POST['rut']) : '';
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        if (empty($rut)) {
            wp_send_json_error(array('message' => __('RUT vacío', 'modulo-ventas')));
            return;
        }
        
        // Limpiar RUT
        $rut_limpio = mv_limpiar_rut($rut);
        
        // Validar formato y dígito verificador
        if (!mv_validar_rut($rut)) {
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
     * AJAX: Agregar nota a cliente
     */
    public function agregar_nota_cliente() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mv_agregar_nota_cliente')) {
            wp_send_json_error(array('message' => __('Error de seguridad', 'modulo-ventas')));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_clientes_ventas')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'modulo-ventas')));
        }
        
        // Validar datos
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $nota = isset($_POST['nota']) ? sanitize_textarea_field($_POST['nota']) : '';
        $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'general';
        $es_privada = isset($_POST['es_privada']) ? intval($_POST['es_privada']) : 0;
        
        if (!$cliente_id || empty($nota)) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        // Crear nota
        $resultado = $this->db->crear_nota_cliente($cliente_id, $nota, $tipo, $es_privada);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        // Log de actividad
        $this->logger->log("Nota agregada al cliente ID: {$cliente_id}", 'info');
        
        wp_send_json_success(array(
            'message' => __('Nota agregada correctamente', 'modulo-ventas'),
            'nota_id' => $resultado
        ));
    }

    /**
     * AJAX: Eliminar nota de cliente
     */
    public function eliminar_nota_cliente() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mv_eliminar_nota')) {
            wp_send_json_error(array('message' => __('Error de seguridad', 'modulo-ventas')));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_clientes_ventas')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'modulo-ventas')));
        }
        
        // Validar datos
        $nota_id = isset($_POST['nota_id']) ? intval($_POST['nota_id']) : 0;
        
        if (!$nota_id) {
            wp_send_json_error(array('message' => __('ID de nota inválido', 'modulo-ventas')));
        }
        
        // Eliminar nota
        $resultado = $this->db->eliminar_nota_cliente($nota_id);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        if (!$resultado) {
            wp_send_json_error(array('message' => __('Error al eliminar la nota', 'modulo-ventas')));
        }
        
        wp_send_json_success(array(
            'message' => __('Nota eliminada correctamente', 'modulo-ventas')
        ));
    }

    /**
     * AJAX: Actualizar nota de cliente
     */
    public function actualizar_nota_cliente() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mv_actualizar_nota')) {
            wp_send_json_error(array('message' => __('Error de seguridad', 'modulo-ventas')));
        }
        
        // Verificar permisos
        if (!current_user_can('manage_clientes_ventas')) {
            wp_send_json_error(array('message' => __('No tiene permisos suficientes', 'modulo-ventas')));
        }
        
        // Validar datos
        $nota_id = isset($_POST['nota_id']) ? intval($_POST['nota_id']) : 0;
        $texto_nota = isset($_POST['nota']) ? sanitize_textarea_field($_POST['nota']) : '';
        $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : null;
        $es_privada = isset($_POST['es_privada']) ? intval($_POST['es_privada']) : null;
        
        if (!$nota_id || empty($texto_nota)) {
            wp_send_json_error(array('message' => __('Datos incompletos', 'modulo-ventas')));
        }
        
        // Actualizar nota
        $resultado = $this->db->actualizar_nota_cliente($nota_id, $texto_nota, $tipo, $es_privada);
        
        if (!$resultado) {
            wp_send_json_error(array('message' => __('Error al actualizar la nota', 'modulo-ventas')));
        }
        
        wp_send_json_success(array(
            'message' => __('Nota actualizada correctamente', 'modulo-ventas')
        ));
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

    /**
     * AJAX: Vista previa con datos reales en nueva ventana
     */
    public function ajax_vista_previa_datos_reales() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos'));
        }
        
        // Verificar nonce
        $nonce_valido = false;
        if (isset($_POST['nonce'])) {
            $nonces_posibles = array('mv_pdf_templates_nonce', 'mv_pdf_templates', 'mv_nonce');
            foreach ($nonces_posibles as $nonce_name) {
                if (wp_verify_nonce($_POST['nonce'], $nonce_name)) {
                    $nonce_valido = true;
                    break;
                }
            }
        }
        
        if (!$nonce_valido) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }
        
        $plantilla_id = isset($_POST['plantilla_id']) ? intval($_POST['plantilla_id']) : 0;
        $usar_datos_reales = isset($_POST['usar_datos_reales']) ? (bool)$_POST['usar_datos_reales'] : true;
        
        // Contenido personalizado del editor
        $html_content = isset($_POST['html_content']) ? $_POST['html_content'] : '';
        $css_content = isset($_POST['css_content']) ? $_POST['css_content'] : '';
        
        if (!$plantilla_id) {
            wp_send_json_error(array('message' => 'ID de plantilla inválido'));
        }
        
        try {
            // Obtener plantilla de la base de datos
            global $wpdb;
            $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
            
            $plantilla = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tabla_plantillas WHERE id = %d",
                $plantilla_id
            ));
            
            if (!$plantilla) {
                wp_send_json_error(array('message' => 'Plantilla no encontrada'));
            }
            
            // CORREGIDO: Usar el campo correcto
            $tipo_documento = isset($plantilla->tipo) ? $plantilla->tipo : 'cotizacion';
            error_log('PREVIEW: Tipo de documento detectado: ' . $tipo_documento);
            
            // Decidir qué contenido usar
            $html_final = !empty($html_content) ? $html_content : $plantilla->contenido_html;
            $css_final = !empty($css_content) ? $css_content : $plantilla->contenido_css;
            
            if (empty($html_final)) {
                wp_send_json_error(array('message' => 'No hay contenido HTML para procesar'));
            }
            
            // Crear procesador
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            $processor = new Modulo_Ventas_PDF_Template_Processor();
            
            // MÉTODO MEJORADO: Cargar datos con fallback robusto
            $datos = null;
            $tipo_datos = 'prueba';
            $mensaje = '';
            
            if ($usar_datos_reales) {
                $tipo_documento = isset($plantilla->tipo_documento) ? $plantilla->tipo_documento : 'cotizacion';
                $datos = $processor->obtener_datos_preview($tipo_documento);
            } else {
                $datos = $this->obtener_datos_prueba_fallback($plantilla->tipo_documento);
            }
            
            // Verificar que tenemos datos válidos
            if (empty($datos)) {
                wp_send_json_error(array('message' => 'No se pudieron obtener datos para el preview'));
            }

            // VERIFICAR: Cargar datos en el procesador ANTES de procesar
            $this->logger->log("PREVIEW: Datos cargados en processor correctamente");
            $carga_exitosa = $processor->cargar_datos($datos);
            
            if (!$carga_exitosa) {
                $this->logger->log("PREVIEW: ERROR - No se pudieron cargar datos en el processor", 'error');
                wp_send_json_error(array('message' => 'Error cargando datos en el procesador'));
            }

            // AGREGAR: Verificar que los datos están disponibles en el processor
            if (!$processor->tiene_datos()) {
                $this->logger->log("PREVIEW: ERROR - Processor no tiene datos después de cargar", 'error');
                wp_send_json_error(array('message' => 'Los datos no se cargaron correctamente en el procesador'));
            }
            
            error_log('PREVIEW: Datos cargados en processor correctamente');
            error_log('PREVIEW: Empresa en datos: ' . 
                    (isset($datos['empresa']['nombre']) ? $datos['empresa']['nombre'] : 'NO_DEFINIDA'));
            
            // Procesar plantilla
            error_log('PREVIEW: Procesando HTML...');
            $html_procesado = $processor->procesar_template($html_final);
            
            error_log('PREVIEW: Procesando CSS...');
            $css_procesado = $processor->procesar_css($css_final);
            
            error_log('PREVIEW: Generando documento final...');
            $documento_final = $processor->generar_documento_final($html_procesado, $css_procesado);
            
            // Verificación final
            $variables_sin_procesar = preg_match_all('/\{\{[^}]+\}\}/', $documento_final, $matches);
            if ($variables_sin_procesar > 0) {
                error_log('PREVIEW: ADVERTENCIA - Variables sin procesar: ' . implode(', ', array_slice($matches[0], 0, 5)));
            }

            // CORREGIR: Buscar <style (con o sin atributos)
            $tiene_css = strpos($documento_final, '<style') !== false;
            $tiene_contenido = strpos($documento_final, 'class="documento"') !== false || strlen($documento_final) > 5000;

            error_log('PREVIEW: VERIFICACIÓN AJAX FINAL - Longitud: ' . strlen($documento_final) . 
                    ', CSS detectado: ' . ($tiene_css ? 'SI' : 'NO') . 
                    ', Contenido válido: ' . ($tiene_contenido ? 'SI' : 'NO'));

            // DEBUG TEMPORAL: Guardar archivo para inspección
            $upload_dir = wp_upload_dir();
            if (!file_exists($upload_dir['basedir'])) {
                wp_mkdir_p($upload_dir['basedir']);
            }

            // GENERAR nombre único para evitar cache
            $timestamp = date('H-i-s-') . wp_rand(100, 999);
            $test_path = $upload_dir['basedir'] . '/preview-plantilla-' . $timestamp . '.html';
            file_put_contents($test_path, $documento_final);
            $test_url = $upload_dir['baseurl'] . '/preview-plantilla-' . $timestamp . '.html';
            error_log('PREVIEW: Documento guardado para test en: ' . $test_url);

            // Respuesta exitosa (MODIFICAR)
            wp_send_json_success(array(
                'html' => $documento_final,
                'preview_url' => $test_url,  // AGREGAR ESTA LÍNEA
                'tipo_datos' => $tipo_datos,
                'mensaje' => $mensaje,
                'test_url' => $test_url,  
                'debug' => array(
                    'tiene_css_corregido' => $tiene_css,
                    'archivo_generado' => $test_path
                )
            ));
            
            // Respuesta exitosa
            wp_send_json_success(array(
                'html' => $documento_final,
                'tipo_datos' => $tipo_datos,
                'mensaje' => $mensaje,
                'debug' => array(
                    'plantilla_id' => $plantilla_id,
                    'tipo_documento' => $tipo_documento,
                    'usar_datos_reales' => $usar_datos_reales,
                    'datos_cargados' => !empty($datos),
                    'variables_sin_procesar' => $variables_sin_procesar,
                    'tiene_css' => $tiene_css,
                    'longitud_html' => strlen($documento_final),
                    'empresa_nombre' => isset($datos['empresa']['nombre']) ? $datos['empresa']['nombre'] : 'NO_DEFINIDA'
                )
            ));
            
        } catch (Exception $e) {
            error_log('PREVIEW: Error general: ' . $e->getMessage());
            error_log('PREVIEW: Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => 'Error al generar vista previa: ' . $e->getMessage(),
                'debug' => array(
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'error_completo' => $e->getMessage()
                )
            ));
        }
    }

    /**
     * AJAX: Preview mPDF sincronizado con datos reales
     */
    public function ajax_preview_mpdf_sincronizado_datos_reales() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Sin permisos'));
        }
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mv_pdf_templates_nonce')) {
            wp_send_json_error(array('message' => 'Nonce inválido'));
        }
        
        $plantilla_id = isset($_POST['plantilla_id']) ? intval($_POST['plantilla_id']) : 0;
        $usar_datos_reales = isset($_POST['usar_datos_reales']) ? (bool)$_POST['usar_datos_reales'] : true;
        
        if (!$plantilla_id) {
            wp_send_json_error(array('message' => 'ID de plantilla inválido'));
        }
        
        try {
            // Obtener plantilla
            global $wpdb;
            $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
            
            $plantilla = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tabla_plantillas WHERE id = %d",
                $plantilla_id
            ));
            
            if (!$plantilla) {
                wp_send_json_error(array('message' => 'Plantilla no encontrada'));
            }
            
            // Crear procesador
            $processor = new Modulo_Ventas_PDF_Template_Processor();
            
            // CORREGIDO: Obtener datos según el tipo SIN BUCLES
            if ($usar_datos_reales) {
                // Intentar obtener datos reales
                $datos = $processor->obtener_datos_preview($plantilla->tipo_documento);
                
                // Verificar si son realmente datos reales
                $es_datos_reales = false;
                if (isset($datos['cotizacion']['numero']) && $datos['cotizacion']['numero'] !== 'COT-001') {
                    $es_datos_reales = true;
                }
                
                $tipo_datos_usado = $es_datos_reales ? 'real' : 'prueba';
                $mensaje_datos = $es_datos_reales 
                    ? 'Preview generado con datos de cotización real (' . $datos['cotizacion']['numero'] . ')'
                    : 'Preview generado con datos de prueba (no hay cotizaciones reales)';
                    
            } else {
                // Usar datos de prueba directamente
                $processor->generar_datos_prueba($plantilla->tipo_documento);
                $datos = $processor->cotizacion_data;
                $tipo_datos_usado = 'prueba';
                $mensaje_datos = 'Preview generado con datos de prueba';
            }
            
            // Procesar plantilla
            $html_procesado = $processor->procesar_template($plantilla->contenido_html);
            $css_procesado = $processor->procesar_css($plantilla->contenido_css);
            
            // Generar documento final
            $documento_final = $processor->generar_documento_final($html_procesado, $css_procesado);
            
            // Agregar marca de agua informativa
            $documento_con_info = str_replace(
                '</body>',
                '<div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; z-index: 9999;">
                    ' . $mensaje_datos . '
                </div></body>',
                $documento_final
            );
            
            wp_send_json_success(array(
                'html' => $documento_con_info,
                'tipo_datos' => $tipo_datos_usado,
                'mensaje' => $mensaje_datos
            ));
            
        } catch (Exception $e) {
            error_log('Error en vista previa: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error al generar vista previa: ' . $e->getMessage()));
        }
    }

    /**
     * Datos de prueba independientes del processor
     */
    private function generar_datos_prueba_fallback($tipo_documento = 'cotizacion') {
        error_log('AJAX: Generando datos de prueba fallback para: ' . $tipo_documento);
        
        // Datos de empresa básicos
        $empresa = array(
            'nombre' => get_option('blogname', 'Mi Empresa S.A.'),
            'direccion' => 'Av. Principal 123, Santiago',
            'ciudad' => 'Santiago',
            'region' => 'Región Metropolitana',
            'telefono' => '+56 2 2345 6789',
            'email' => get_option('admin_email', 'empresa@ejemplo.com'),
            'rut' => '98.765.432-1',
            'sitio_web' => get_option('siteurl')
        );
        
        // Datos de cliente
        $cliente = array(
            'nombre' => 'Empresa Cliente de Prueba S.A.',
            'rut' => '12.345.678-9',
            'email' => 'cliente@ejemplo.com',
            'telefono' => '+56 9 1234 5678',
            'direccion' => 'Av. Cliente 456, Santiago',
            'ciudad' => 'Santiago',
            'region' => 'Región Metropolitana',
            'giro' => 'Servicios Comerciales'
        );
        
        // Productos de prueba
        $productos = array(
            array(
                'codigo' => 'PROD-001',
                'nombre' => 'Producto de Prueba 1',
                'descripcion' => 'Descripción del producto de prueba',
                'cantidad' => 2,
                'precio_unitario' => 50000,
                'precio_unitario_formateado' => '50.000',
                'subtotal' => 100000,
                'subtotal_formateado' => '100.000',
                'descuento' => 0,
                'descuento_formateado' => '0'
            ),
            array(
                'codigo' => 'SERV-001',
                'nombre' => 'Servicio de Prueba',
                'descripcion' => 'Descripción del servicio de prueba',
                'cantidad' => 1,
                'precio_unitario' => 150000,
                'precio_unitario_formateado' => '150.000',
                'subtotal' => 150000,
                'subtotal_formateado' => '150.000',
                'descuento' => 0,
                'descuento_formateado' => '0'
            )
        );
        
        // Totales
        $subtotal = 250000;
        $descuento = 0;
        $iva = 47500;
        $total = 297500;
        
        $totales = array(
            'subtotal' => $subtotal,
            'subtotal_formateado' => '$' . number_format($subtotal, 0, ',', '.'),
            'descuento' => $descuento,
            'descuento_formateado' => '$' . number_format($descuento, 0, ',', '.'),
            'iva' => $iva,
            'iva_formateado' => '$' . number_format($iva, 0, ',', '.'),
            'total' => $total,
            'total_formateado' => '$' . number_format($total, 0, ',', '.')
        );
        
        // Datos específicos según tipo de documento
        $documento = array(
            'numero' => 'COT-001',
            'fecha' => date('d/m/Y'),
            'fecha_vencimiento' => date('d/m/Y', strtotime('+30 days')),
            'estado' => 'Borrador',
            'observaciones' => 'Esta es una cotización de prueba para visualizar la plantilla',
            'vendedor' => 'Juan Pérez',
            'validez' => '30 días'
        );
        
        // Fechas
        $fechas = array(
            'hoy' => date('d/m/Y'),
            'fecha_cotizacion' => date('d/m/Y'),
            'fecha_vencimiento_formateada' => date('d/m/Y', strtotime('+30 days')),
            'mes_actual' => date('m'),
            'año_actual' => date('Y')
        );
        
        // Estructura final
        $datos = array(
            'cotizacion' => $documento,
            'cliente' => $cliente,
            'empresa' => $empresa,
            'productos' => $productos,
            'totales' => $totales,
            'fechas' => $fechas
        );
        
        error_log('AJAX: Datos de prueba fallback generados exitosamente');
        
        return $datos;
    }

    /**
     * AGREGAR: Método fallback para datos de prueba
     */
    private function obtener_datos_prueba_fallback($tipo_documento) {
        return array(
            'cotizacion' => array(
                'numero' => 'COT-001',
                'fecha' => date('d/m/Y'),
                'fecha_vencimiento' => date('d/m/Y', strtotime('+30 days')),
                'estado' => 'Borrador',
                'observaciones' => 'Esta es una cotización de prueba para visualizar la plantilla',
                'vendedor' => 'Juan Pérez',
                'validez' => '30 días',
                'condiciones_pago' => 'Contado contra entrega'
            ),
            
            'cliente' => array(
                'nombre' => 'Empresa Cliente de Prueba S.A.',
                'rut' => '12.345.678-9',
                'email' => 'cliente@ejemplo.com',
                'telefono' => '+56 9 1234 5678',
                'direccion' => 'Av. Principal 123',
                'ciudad' => 'Santiago',
                'region' => 'Región Metropolitana',
                'giro' => 'Servicios Generales'
            ),
            
            'empresa' => array(
                'nombre' => get_option('blogname', 'Mi Empresa'),
                'direccion' => 'Av. Empresarial 456',
                'ciudad' => 'Santiago',
                'region' => 'Región Metropolitana',
                'telefono' => '+56 2 2345 6789',
                'email' => get_option('admin_email', 'empresa@ejemplo.com'),
                'rut' => '98.765.432-1',
                'sitio_web' => get_option('siteurl')
            ),
            
            'productos' => array(
                array(
                    'codigo' => 'PROD-001',
                    'nombre' => 'Producto de Prueba 1',
                    'descripcion' => 'Descripción del producto de prueba',
                    'cantidad' => 2,
                    'precio_unitario' => 50000,
                    'precio_unitario_formateado' => '50.000',
                    'subtotal' => 100000,
                    'subtotal_formateado' => '100.000',
                    'descuento' => 0,
                    'descuento_formateado' => '0'
                ),
                array(
                    'codigo' => 'SERV-001',
                    'nombre' => 'Servicio de Prueba',
                    'descripcion' => 'Descripción del servicio de prueba',
                    'cantidad' => 1,
                    'precio_unitario' => 150000,
                    'precio_unitario_formateado' => '150.000',
                    'subtotal' => 150000,
                    'subtotal_formateado' => '150.000',
                    'descuento' => 0,
                    'descuento_formateado' => '0'
                )
            ),
            
            'totales' => array(
                'subtotal' => 250000,
                'subtotal_formateado' => '250.000',
                'descuento' => 0,
                'descuento_formateado' => '0',
                'descuento_porcentaje' => 0,
                'iva' => 47500,
                'iva_formateado' => '47.500',
                'iva_porcentaje' => 19,
                'total' => 297500,
                'total_formateado' => '297.500'
            ),
            
            'fechas' => array(
                'hoy' => date('d/m/Y'),
                'fecha_cotizacion' => date('d/m/Y'),
                'fecha_vencimiento_formateada' => date('d/m/Y', strtotime('+30 days')),
                'mes_actual' => date('m'),
                'año_actual' => date('Y')
            )
        );
    }
}