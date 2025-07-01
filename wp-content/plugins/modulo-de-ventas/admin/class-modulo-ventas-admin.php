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
            
            // Notas de clientes
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
        );
        
        foreach ($private_actions as $action => $method) {
            add_action('wp_ajax_' . $action, array($this, $method));
        }
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
     * Buscar productos
     */
    public function buscar_productos() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $busqueda = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'all';
        
        if (strlen($busqueda) < 2) {
            wp_send_json_error(array('message' => __('Ingrese al menos 2 caracteres', 'modulo-ventas')));
        }
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 20,
            's' => $busqueda,
            'post_status' => 'publish'
        );
        
        if ($tipo !== 'all') {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => $tipo
                )
            );
        }
        
        $productos = get_posts($args);
        $resultados = array();
        
        foreach ($productos as $producto) {
            $wc_product = wc_get_product($producto->ID);
            
            $resultados[] = array(
                'id' => $producto->ID,
                'text' => $producto->post_title,
                'sku' => $wc_product->get_sku(),
                'price' => $wc_product->get_price(),
                'stock' => $wc_product->get_stock_quantity(),
                'type' => $wc_product->get_type()
            );
        }
        
        wp_send_json_success(array('results' => $resultados));
    }

    /**
     * Obtener stock de producto
     */
    public function obtener_stock_producto() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $producto_id = isset($_POST['producto_id']) ? intval($_POST['producto_id']) : 0;
        $almacen_id = isset($_POST['almacen_id']) ? intval($_POST['almacen_id']) : 0;
        
        if (!$producto_id) {
            wp_send_json_error(array('message' => __('Producto no especificado', 'modulo-ventas')));
        }
        
        // Implementar lógica de stock por almacén si es necesario
        $wc_product = wc_get_product($producto_id);
        
        if (!$wc_product) {
            wp_send_json_error(array('message' => __('Producto no encontrado', 'modulo-ventas')));
        }
        
        $stock_data = array(
            'stock_quantity' => $wc_product->get_stock_quantity(),
            'stock_status' => $wc_product->get_stock_status(),
            'managing_stock' => $wc_product->managing_stock()
        );
        
        wp_send_json_success($stock_data);
    }

    /**
     * Obtener datos de cliente
     */
    public function obtener_cliente() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        if (!$cliente_id) {
            wp_send_json_error(array('message' => __('Cliente no especificado', 'modulo-ventas')));
        }
        
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        if (!$cliente) {
            wp_send_json_error(array('message' => __('Cliente no encontrado', 'modulo-ventas')));
        }
        
        $cliente_data = array(
            'id' => $cliente->id,
            'razon_social' => $cliente->razon_social,
            'rut' => $cliente->rut,
            'email' => $cliente->email,
            'telefono' => $cliente->telefono,
            'direccion' => $cliente->direccion_facturacion,
            'comuna' => $cliente->comuna_facturacion,
            'ciudad' => $cliente->ciudad_facturacion,
            'region' => $cliente->region_facturacion,
            'descuento' => $cliente->descuento_predeterminado
        );
        
        wp_send_json_success(array('cliente' => $cliente_data));
    }

    /**
     * Buscar cliente
     */
    public function buscar_cliente() {
        mv_ajax_check_permissions('view_cotizaciones', 'modulo_ventas_nonce');
        
        $busqueda = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (strlen($busqueda) < 2) {
            wp_send_json_success(array('results' => array()));
        }
        
        $clientes = $this->db->buscar_clientes($busqueda);
        $resultados = array();
        
        foreach ($clientes as $cliente) {
            $resultados[] = array(
                'id' => $cliente->id,
                'text' => $cliente->razon_social . ' - ' . mv_formatear_rut($cliente->rut),
                'rut' => mv_formatear_rut($cliente->rut),
                'email' => $cliente->email
            );
        }
        
        wp_send_json_success(array('results' => $resultados));
    }

    /**
     * Crear cliente rápido
     */
    public function crear_cliente_rapido() {
        mv_ajax_check_permissions('manage_clientes_ventas', 'modulo_ventas_nonce');
        
        if (!isset($_POST['cliente']) || !is_array($_POST['cliente'])) {
            wp_send_json_error(array('message' => __('Datos de cliente no válidos', 'modulo-ventas')));
        }
        
        $datos = $_POST['cliente'];
        
        // Validar RUT
        if (!isset($datos['rut']) || empty($datos['rut'])) {
            wp_send_json_error(array('message' => __('El RUT es obligatorio', 'modulo-ventas')));
        }
        
        $rut_limpio = mv_limpiar_rut($datos['rut']);
        if (!mv_validar_rut($rut_limpio)) {
            wp_send_json_error(array('message' => __('RUT inválido', 'modulo-ventas')));
        }
        
        // Verificar si el RUT ya existe
        $existe = $this->db->obtener_cliente_por_rut($rut_limpio);
        if ($existe) {
            wp_send_json_error(array('message' => __('Ya existe un cliente con este RUT', 'modulo-ventas')));
        }
        
        // Preparar datos
        $datos_cliente = array(
            'rut' => $rut_limpio,
            'razon_social' => sanitize_text_field($datos['razon_social'] ?? ''),
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
     * Validar RUT
     */
    public function validar_rut() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
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

    // Aquí irían el resto de métodos...
    // Por ahora, crearemos métodos vacíos para evitar errores

    public function guardar_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function actualizar_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function actualizar_estado_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function duplicar_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function convertir_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function eliminar_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function enviar_cotizacion_email() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function generar_pdf_cotizacion() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function obtener_almacenes() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function calcular_totales() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function buscar_cotizaciones() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function actualizar_cliente() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function eliminar_cliente() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function sincronizar_cliente_woocommerce() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function obtener_estadisticas() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function obtener_reporte() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function exportar_cotizaciones() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function exportar_clientes() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function generar_datos_grafico() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function actualizar_almacen_pedido() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function buscar_usuario() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function test_email() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function limpiar_logs() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function backup_datos() {
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }

    public function validar_rut_publico() {
        // Mismo código que validar_rut pero sin verificar login
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
}