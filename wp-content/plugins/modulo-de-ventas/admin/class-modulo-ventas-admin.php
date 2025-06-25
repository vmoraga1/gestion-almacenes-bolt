<?php
/**
 * Clase para manejar la administración del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Admin {
    
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
        
        // Hooks del menú
        add_action('admin_menu', array($this, 'registrar_menu'));
        
        // Hooks de acciones en lote
        add_filter('bulk_actions-edit-shop_order', array($this, 'agregar_acciones_lote_pedidos'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'manejar_acciones_lote_pedidos'), 10, 3);
        
        // Notices administrativos
        add_action('admin_notices', array($this, 'mostrar_avisos_admin'));
        
        // Scripts y estilos específicos del admin
        add_action('admin_enqueue_scripts', array($this, 'cargar_assets_admin'));
        
        // Personalización de la página de usuarios
        add_action('restrict_manage_users', array($this, 'filtro_usuarios_con_cliente'));
        add_filter('pre_get_users', array($this, 'filtrar_usuarios_query'));
        
        // AJAX handlers adicionales
        add_action('wp_ajax_mv_actualizar_almacen_pedido', array($this, 'ajax_actualizar_almacen_pedido'));
        add_action('wp_ajax_mv_obtener_comunas', array($this, 'ajax_obtener_comunas'));
        add_action('wp_ajax_mv_exportar_cotizaciones', array($this, 'ajax_exportar_cotizaciones'));
        add_action('wp_ajax_mv_obtener_estadisticas', array($this, 'ajax_obtener_estadisticas'));
        
        // Configuración del plugin
        add_action('admin_init', array($this, 'registrar_configuracion'));
    }
    
    /**
     * Registrar menú del plugin
     */
    public function registrar_menu() {
        // Menú principal
        add_menu_page(
            __('Módulo de Ventas', 'modulo-ventas'),
            __('Ventas', 'modulo-ventas'),
            'view_cotizaciones',
            'modulo-ventas',
            array($this, 'pagina_dashboard'),
            'dashicons-cart',
            55 // Posición después de WooCommerce
        );
        
        // Dashboard (reemplaza la página principal)
        add_submenu_page(
            'modulo-ventas',
            __('Dashboard', 'modulo-ventas'),
            __('Dashboard', 'modulo-ventas'),
            'view_cotizaciones',
            'modulo-ventas',
            array($this, 'pagina_dashboard')
        );
        
        // Cotizaciones
        add_submenu_page(
            'modulo-ventas',
            __('Cotizaciones', 'modulo-ventas'),
            __('Cotizaciones', 'modulo-ventas'),
            'view_cotizaciones',
            'modulo-ventas-cotizaciones',
            array($this, 'pagina_cotizaciones')
        );
        
        // Nueva Cotización
        add_submenu_page(
            'modulo-ventas',
            __('Nueva Cotización', 'modulo-ventas'),
            __('Nueva Cotización', 'modulo-ventas'),
            'create_cotizaciones',
            'modulo-ventas-nueva-cotizacion',
            array($this, 'pagina_nueva_cotizacion')
        );
        
        // Ver/Editar Cotización (oculto del menú)
        add_submenu_page(
            null,
            __('Ver Cotización', 'modulo-ventas'),
            __('Ver Cotización', 'modulo-ventas'),
            'view_cotizaciones',
            'modulo-ventas-ver-cotizacion',
            array($this, 'pagina_ver_cotizacion')
        );
        
        // Editar Cotización (oculto del menú)
        add_submenu_page(
            null,
            __('Editar Cotización', 'modulo-ventas'),
            __('Editar Cotización', 'modulo-ventas'),
            'edit_cotizaciones',
            'modulo-ventas-editar-cotizacion',
            array($this, 'pagina_editar_cotizacion')
        );
        
        // Separador
        add_submenu_page(
            'modulo-ventas',
            '',
            '<span class="mv-menu-separator">' . __('Gestión', 'modulo-ventas') . '</span>',
            'view_cotizaciones',
            '#',
            ''
        );
        
        // Clientes
        add_submenu_page(
            'modulo-ventas',
            __('Clientes', 'modulo-ventas'),
            __('Clientes', 'modulo-ventas'),
            'manage_clientes_ventas',
            'modulo-ventas-clientes',
            array($this, 'pagina_clientes')
        );
        
        // Nuevo Cliente
        add_submenu_page(
            'modulo-ventas',
            __('Nuevo Cliente', 'modulo-ventas'),
            __('Nuevo Cliente', 'modulo-ventas'),
            'manage_clientes_ventas',
            'modulo-ventas-nuevo-cliente',
            array($this, 'pagina_nuevo_cliente')
        );
        
        // Ver/Editar Cliente (oculto)
        add_submenu_page(
            null,
            __('Editar Cliente', 'modulo-ventas'),
            __('Editar Cliente', 'modulo-ventas'),
            'manage_clientes_ventas',
            'modulo-ventas-editar-cliente',
            array($this, 'pagina_editar_cliente')
        );
        
        // Separador
        add_submenu_page(
            'modulo-ventas',
            '',
            '<span class="mv-menu-separator">' . __('Reportes', 'modulo-ventas') . '</span>',
            'view_reportes_ventas',
            '#',
            ''
        );
        
        // Reportes
        add_submenu_page(
            'modulo-ventas',
            __('Reportes', 'modulo-ventas'),
            __('Reportes', 'modulo-ventas'),
            'view_reportes_ventas',
            'modulo-ventas-reportes',
            array($this, 'pagina_reportes')
        );
        
        // Estadísticas
        add_submenu_page(
            'modulo-ventas',
            __('Estadísticas', 'modulo-ventas'),
            __('Estadísticas', 'modulo-ventas'),
            'view_reportes_ventas',
            'modulo-ventas-estadisticas',
            array($this, 'pagina_estadisticas')
        );
        
        // Separador
        add_submenu_page(
            'modulo-ventas',
            '',
            '<span class="mv-menu-separator">' . __('Configuración', 'modulo-ventas') . '</span>',
            'manage_modulo_ventas',
            '#',
            ''
        );
        
        // Configuración
        add_submenu_page(
            'modulo-ventas',
            __('Configuración', 'modulo-ventas'),
            __('Configuración', 'modulo-ventas'),
            'manage_modulo_ventas',
            'modulo-ventas-configuracion',
            array($this, 'pagina_configuracion')
        );
        
        // Herramientas
        add_submenu_page(
            'modulo-ventas',
            __('Herramientas', 'modulo-ventas'),
            __('Herramientas', 'modulo-ventas'),
            'manage_modulo_ventas',
            'modulo-ventas-herramientas',
            array($this, 'pagina_herramientas')
        );
        
        // Logs
        add_submenu_page(
            'modulo-ventas',
            __('Logs del Sistema', 'modulo-ventas'),
            __('Logs', 'modulo-ventas'),
            'manage_modulo_ventas',
            'modulo-ventas-logs',
            array($this, 'pagina_logs')
        );
    }
    
    /**
     * Página del Dashboard
     */
    public function pagina_dashboard() {
        // Verificar permisos
        if (!current_user_can('view_cotizaciones')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Obtener estadísticas
        $stats = $this->obtener_estadisticas_dashboard();
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Página de listado de cotizaciones
     */
    public function pagina_cotizaciones() {
        // Verificar permisos
        if (!current_user_can('view_cotizaciones')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Procesar acciones
        $this->procesar_acciones_cotizaciones();
        
        // Preparar tabla de listado
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-cotizaciones-list-table.php';
        $cotizaciones_table = new Cotizaciones_List_Table();
        $cotizaciones_table->prepare_items();
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/cotizaciones-list.php';
    }
    
    /**
     * Página de nueva cotización
     */
    public function pagina_nueva_cotizacion() {
        // Verificar permisos
        if (!current_user_can('create_cotizaciones')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // AGREGAR: Procesar el formulario si se envió
        if (isset($_POST['mv_cotizacion_nonce']) && wp_verify_nonce($_POST['mv_cotizacion_nonce'], 'mv_crear_cotizacion')) {
            $this->procesar_nueva_cotizacion();
        }

        // Obtener datos necesarios
        $clientes = new Modulo_Ventas_Clientes();
        $lista_clientes = $clientes->obtener_clientes_para_select();
        
        // Almacenes si está disponible
        $almacenes = array();
        if (class_exists('Gestion_Almacenes_DB')) {
            global $gestion_almacenes_db;
            $almacenes = $gestion_almacenes_db->obtener_almacenes();
        }
        
        // Configuración
        $config = $this->obtener_configuracion();
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/nueva-cotizacion.php';
    }

    /**
     * Procesar el formulario de nueva cotización
     */
    private function procesar_nueva_cotizacion() {
        // Recopilar datos generales con valores por defecto para campos opcionales
        $datos_generales = array(
            'cliente_id' => isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0,
            'vendedor_id' => get_current_user_id(),
            'almacen_id' => isset($_POST['almacen_id']) ? intval($_POST['almacen_id']) : 0,
            'fecha' => isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : date('Y-m-d'),
            'fecha_expiracion' => isset($_POST['fecha_expiracion']) ? sanitize_text_field($_POST['fecha_expiracion']) : '',
            'plazo_pago' => isset($_POST['plazo_pago']) ? sanitize_text_field($_POST['plazo_pago']) : '',
            'condiciones_pago' => isset($_POST['condiciones_pago']) ? sanitize_textarea_field($_POST['condiciones_pago']) : '',
            'observaciones' => isset($_POST['observaciones']) ? sanitize_textarea_field($_POST['observaciones']) : '',
            'notas_internas' => isset($_POST['notas_internas']) ? sanitize_textarea_field($_POST['notas_internas']) : '',
            'terminos_condiciones' => isset($_POST['terminos_condiciones']) ? sanitize_textarea_field($_POST['terminos_condiciones']) : '',
            'incluye_iva' => isset($_POST['incluye_iva']) ? 1 : 0,
            'descuento_monto' => isset($_POST['descuento_monto']) ? floatval($_POST['descuento_monto']) : 0,
            'descuento_porcentaje' => isset($_POST['descuento_porcentaje']) ? floatval($_POST['descuento_porcentaje']) : 0,
            'tipo_descuento' => isset($_POST['tipo_descuento_global']) ? sanitize_text_field($_POST['tipo_descuento_global']) : 'monto',
            'costo_envio' => isset($_POST['costo_envio']) ? floatval($_POST['costo_envio']) : 0,
            'subtotal' => isset($_POST['subtotal']) ? floatval($_POST['subtotal']) : 0,
            'total' => isset($_POST['total']) ? floatval($_POST['total']) : 0
        );
        
        // Aplicar descuento global según tipo
        if ($datos_generales['tipo_descuento'] === 'porcentaje') {
            $datos_generales['descuento_porcentaje'] = isset($_POST['descuento_global']) ? floatval($_POST['descuento_global']) : 0;
            $datos_generales['descuento_monto'] = 0;
        } else {
            $datos_generales['descuento_monto'] = isset($_POST['descuento_global']) ? floatval($_POST['descuento_global']) : 0;
            $datos_generales['descuento_porcentaje'] = 0;
        }
        
        // Debug para ver qué campos están llegando
        error_log('Datos POST recibidos: ' . print_r($_POST, true));
        
        // Validar datos requeridos
        if (!$datos_generales['cliente_id']) {
            $this->agregar_mensaje_admin('error', __('Debe seleccionar un cliente', 'modulo-ventas'));
            return;
        }
        
        // Recopilar items - NUEVA estructura correcta
        $items = array();
        
        // Los productos vienen en el array $_POST['items']
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                // Solo procesar si tiene producto_id válido
                if (isset($item['producto_id']) && !empty($item['producto_id'])) {
                    // Obtener datos del producto de WooCommerce si es necesario
                    $producto_id = intval($item['producto_id']);
                    $variacion_id = isset($item['variacion_id']) ? intval($item['variacion_id']) : 0;
                    
                    // Si el nombre está vacío y es un producto de WooCommerce, obtenerlo
                    $nombre = isset($item['nombre']) ? sanitize_text_field($item['nombre']) : '';
                    $sku = isset($item['sku']) ? sanitize_text_field($item['sku']) : '';
                    
                    if (empty($nombre) && $producto_id > 0) {
                        // Es un producto de WooCommerce, obtener sus datos
                        $product = wc_get_product($variacion_id ? $variacion_id : $producto_id);
                        if ($product) {
                            $nombre = $product->get_name();
                            if (empty($sku)) {
                                $sku = $product->get_sku();
                            }
                        }
                    }
                    
                    // Si aún no tiene nombre, usar un valor por defecto
                    if (empty($nombre)) {
                        $nombre = 'Producto personalizado';
                    }
                    
                    // Calcular descuento
                    $precio_unitario = isset($item['precio_unitario']) ? floatval($item['precio_unitario']) : 0;
                    $cantidad = isset($item['cantidad']) ? floatval($item['cantidad']) : 1;
                    $tipo_descuento = isset($item['tipo_descuento']) ? $item['tipo_descuento'] : 'monto';
                    $descuento_valor = isset($item['descuento_monto']) ? floatval($item['descuento_monto']) : 0;
                    
                    // Calcular montos de descuento
                    if ($tipo_descuento === 'porcentaje') {
                        $descuento_porcentaje = $descuento_valor;
                        $descuento_monto = ($precio_unitario * $cantidad) * ($descuento_porcentaje / 100);
                    } else {
                        $descuento_monto = $descuento_valor;
                        $descuento_porcentaje = ($precio_unitario > 0) ? ($descuento_monto / ($precio_unitario * $cantidad)) * 100 : 0;
                    }
                    
                    // Calcular subtotal del item
                    $subtotal_item = ($precio_unitario * $cantidad) - $descuento_monto;
                    
                    $items[] = array(
                        'producto_id' => $producto_id,
                        'variacion_id' => $variacion_id,
                        'almacen_id' => isset($item['almacen_id']) ? intval($item['almacen_id']) : $datos_generales['almacen_id'],
                        'sku' => $sku,
                        'nombre' => $nombre,
                        'descripcion' => isset($item['descripcion']) ? sanitize_textarea_field($item['descripcion']) : '',
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'precio_original' => isset($item['precio_original']) ? floatval($item['precio_original']) : $precio_unitario,
                        'descuento_monto' => $descuento_monto,
                        'descuento_porcentaje' => $descuento_porcentaje,
                        'tipo_descuento' => $tipo_descuento,
                        'subtotal' => $subtotal_item,
                        'stock_disponible' => isset($item['stock_disponible']) ? intval($item['stock_disponible']) : null
                    );
                }
            }
        }
        
        // Validar que hay productos
        if (empty($items)) {
            $this->agregar_mensaje_admin('error', __('Debe agregar al menos un producto', 'modulo-ventas'));
            return;
        }
        
        // Debug items
        error_log('Items a guardar: ' . print_r($items, true));
        
        // Crear cotización usando la clase de cotizaciones
        $cotizaciones = new Modulo_Ventas_Cotizaciones();
        $cotizacion_id = $cotizaciones->crear_cotizacion($datos_generales, $items);
        
        if (is_wp_error($cotizacion_id)) {
            // Agregar mensaje de error
            $this->agregar_mensaje_admin('error', $cotizacion_id->get_error_message());
            error_log('Error al crear cotización: ' . $cotizacion_id->get_error_message());
        } else {
            // Éxito - log
            error_log('Cotización creada exitosamente con ID: ' . $cotizacion_id);
            
            // Redirigir según la acción
            if (isset($_POST['action']) && $_POST['action'] == 'save_and_new') {
                wp_redirect(admin_url('admin.php?page=modulo-ventas-nueva-cotizacion&message=created'));
            } else {
                wp_redirect(admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id));
            }
            exit;
        }
    }

    /**
     * Agregar mensaje administrativo
     */
    private function agregar_mensaje_admin($tipo, $mensaje) {
        add_action('admin_notices', function() use ($tipo, $mensaje) {
            echo '<div class="notice notice-' . esc_attr($tipo) . ' is-dismissible">';
            echo '<p>' . esc_html($mensaje) . '</p>';
            echo '</div>';
        });
    }
    
    /**
     * Página de ver cotización
     */
    public function pagina_ver_cotizacion() {
        // Verificar permisos
        if (!current_user_can('view_cotizaciones')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Obtener ID
        $cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$cotizacion_id) {
            wp_die(__('ID de cotización inválido.', 'modulo-ventas'));
        }
        
        // Obtener cotización
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            wp_die(__('Cotización no encontrada.', 'modulo-ventas'));
        }
        
        // Obtener cliente
        $cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
        
        // Verificar si puede editar
        $puede_editar = current_user_can('edit_cotizaciones') && 
                    !in_array($cotizacion->estado, array('convertida', 'expirada'));
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/ver-cotizacion.php';
    }
    
    /**
     * Página de editar cotización
     */
    public function pagina_editar_cotizacion() {
        // Verificar permisos
        if (!current_user_can('edit_cotizaciones')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Obtener ID
        $cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$cotizacion_id) {
            wp_die(__('ID de cotización inválido.', 'modulo-ventas'));
        }
        
        // Obtener cotización
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            wp_die(__('Cotización no encontrada.', 'modulo-ventas'));
        }
        
        // Verificar que se puede editar
        if (in_array($cotizacion->estado, array('convertida', 'expirada'))) {
            wp_die(__('Esta cotización no se puede editar.', 'modulo-ventas'));
        }
        
        // Obtener datos necesarios
        $clientes = new Modulo_Ventas_Clientes();
        $lista_clientes = $clientes->obtener_clientes_para_select();
        
        // Almacenes
        $almacenes = array();
        if (class_exists('Gestion_Almacenes_DB')) {
            global $gestion_almacenes_db;
            $almacenes = $gestion_almacenes_db->obtener_almacenes();
        }
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/editar-cotizacion.php';
    }
    
    /**
     * Página de listado de clientes
     */
    public function pagina_clientes() {
        // Verificar permisos
        if (!current_user_can('manage_clientes_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Procesar acciones
        $this->procesar_acciones_clientes();
        
        // Preparar tabla
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-clientes-list-table.php';
        $clientes_table = new Clientes_List_Table();
        $clientes_table->prepare_items();
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/clientes-list.php';
    }
    
    /**
     * Página de nuevo cliente
     */
    public function pagina_nuevo_cliente() {
        // Verificar permisos
        if (!current_user_can('manage_clientes_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Procesar formulario si fue enviado
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mv_cliente_nonce'])) {
            $this->procesar_formulario_cliente();
        }
        
        // Obtener datos necesarios
        $clientes_obj = new Modulo_Ventas_Clientes();
        $regiones = $clientes_obj->obtener_regiones_chile();
        
        // Usuarios sin cliente asociado
        $usuarios_disponibles = $this->obtener_usuarios_sin_cliente();
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/nuevo-cliente.php';
    }
    
    /**
     * Página de editar cliente
     */
    public function pagina_editar_cliente() {
        // Verificar permisos
        if (!current_user_can('manage_clientes_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Obtener ID
        $cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$cliente_id) {
            wp_die(__('ID de cliente inválido.', 'modulo-ventas'));
        }
        
        // Obtener cliente
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        if (!$cliente) {
            wp_die(__('Cliente no encontrado.', 'modulo-ventas'));
        }
        
        // Procesar formulario si fue enviado
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mv_cliente_nonce'])) {
            $this->procesar_formulario_cliente($cliente_id);
        }
        
        // Obtener datos necesarios
        $clientes_obj = new Modulo_Ventas_Clientes();
        $regiones = $clientes_obj->obtener_regiones_chile();
        
        // Usuarios sin cliente o el usuario actual
        $usuarios_disponibles = $this->obtener_usuarios_sin_cliente($cliente->user_id);
        
        // Estadísticas del cliente
        $estadisticas_cliente = $this->obtener_estadisticas_cliente($cliente_id);
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/editar-cliente.php';
    }
    
    /**
     * Página de reportes
     */
    public function pagina_reportes() {
        // Verificar permisos
        if (!current_user_can('view_reportes_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Obtener filtros
        $fecha_desde = isset($_GET['fecha_desde']) ? sanitize_text_field($_GET['fecha_desde']) : date('Y-m-01');
        $fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize_text_field($_GET['fecha_hasta']) : date('Y-m-d');
        $vendedor_id = isset($_GET['vendedor_id']) ? intval($_GET['vendedor_id']) : 0;
        $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
        
        // Obtener datos de reportes
        $reportes = $this->generar_reportes($fecha_desde, $fecha_hasta, $vendedor_id, $estado);
        
        // Lista de vendedores
        $vendedores = $this->obtener_vendedores();
        
        // Estados disponibles
        $cotizaciones_obj = new Modulo_Ventas_Cotizaciones();
        $estados = $cotizaciones_obj->obtener_estados();
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/reportes.php';
    }
    
    /**
     * Página de estadísticas
     */
    public function pagina_estadisticas() {
        // Verificar permisos
        if (!current_user_can('view_reportes_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Período seleccionado
        $periodo = isset($_GET['periodo']) ? sanitize_text_field($_GET['periodo']) : 'mes_actual';
        
        // Obtener estadísticas
        $estadisticas = $this->obtener_estadisticas_completas($periodo);
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/estadisticas.php';
    }
    
    /**
     * Página de configuración
     */
    public function pagina_configuracion() {
        // Verificar permisos
        if (!current_user_can('manage_modulo_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Tabs de configuración
        $tab_activa = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/configuracion.php';
    }
    
    /**
     * Página de herramientas
     */
    public function pagina_herramientas() {
        // Verificar permisos
        if (!current_user_can('manage_modulo_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Procesar acciones de herramientas
        $mensaje = '';
        if (isset($_POST['action']) && isset($_POST['mv_herramientas_nonce'])) {
            if (wp_verify_nonce($_POST['mv_herramientas_nonce'], 'mv_herramientas')) {
                $mensaje = $this->procesar_herramienta($_POST['action']);
            }
        }
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/herramientas.php';
    }
    
    /**
     * Página de logs
     */
    public function pagina_logs() {
        // Verificar permisos
        if (!current_user_can('manage_modulo_ventas')) {
            wp_die(__('No tiene permisos suficientes para acceder a esta página.', 'modulo-ventas'));
        }
        
        // Filtros
        $nivel = isset($_GET['nivel']) ? sanitize_text_field($_GET['nivel']) : '';
        $buscar = isset($_GET['buscar']) ? sanitize_text_field($_GET['buscar']) : '';
        
        // Obtener logs
        $logs = $this->logger->obtener_logs(array(
            'nivel' => $nivel,
            'buscar' => $buscar,
            'limite' => 100
        ));
        
        // Cargar vista
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    /**
     * Cargar assets específicos del admin
     */
    public function cargar_assets_admin($hook) {
        // Solo cargar en páginas del plugin
        if (strpos($hook, 'modulo-ventas') === false) {
            return;
        }
        
        // CSS adicional para el admin
        wp_enqueue_style(
            'modulo-ventas-admin-extra',
            MODULO_VENTAS_PLUGIN_URL . 'assets/css/admin-extra.css',
            array('modulo-ventas-admin'),
            MODULO_VENTAS_VERSION
        );
        
        // JavaScript adicional según la página
        if (strpos($hook, 'estadisticas') !== false) {
            // Chart.js para gráficos
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                array(),
                '3.9.1'
            );
            
            wp_enqueue_script(
                'modulo-ventas-estadisticas',
                MODULO_VENTAS_PLUGIN_URL . 'assets/js/estadisticas.js',
                array('jquery', 'chartjs'),
                MODULO_VENTAS_VERSION,
                true
            );
        }
        
        if (strpos($hook, 'reportes') !== false) {
            // DataTables para reportes
            wp_enqueue_style(
                'datatables',
                'https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css',
                array(),
                '1.13.1'
            );
            
            wp_enqueue_script(
                'datatables',
                'https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js',
                array('jquery'),
                '1.13.1'
            );
            
            wp_enqueue_script(
                'modulo-ventas-reportes',
                MODULO_VENTAS_PLUGIN_URL . 'assets/js/reportes.js',
                array('jquery', 'datatables'),
                MODULO_VENTAS_VERSION,
                true
            );
        }
    }
    
    /**
     * Procesar acciones de cotizaciones
     */
    private function procesar_acciones_cotizaciones() {
        if (!isset($_REQUEST['action']) || !isset($_REQUEST['_wpnonce'])) {
            return;
        }
        
        $action = $_REQUEST['action'];
        $nonce = $_REQUEST['_wpnonce'];
        
        // Acciones individuales
        if (isset($_REQUEST['cotizacion_id'])) {
            $cotizacion_id = intval($_REQUEST['cotizacion_id']);
            
            switch ($action) {
                case 'delete':
                    if (wp_verify_nonce($nonce, 'delete_cotizacion_' . $cotizacion_id)) {
                        if (current_user_can('delete_cotizaciones')) {
                            $resultado = $this->db->eliminar_cotizacion($cotizacion_id);
                            if (!is_wp_error($resultado)) {
                                $this->agregar_mensaje_admin('success', __('Cotización eliminada correctamente.', 'modulo-ventas'));
                            } else {
                                $this->agregar_mensaje_admin('error', $resultado->get_error_message());
                            }
                        }
                    }
                    break;
                    
                case 'duplicate':
                    if (wp_verify_nonce($nonce, 'duplicate_cotizacion_' . $cotizacion_id)) {
                        if (current_user_can('create_cotizaciones')) {
                            $cotizaciones = new Modulo_Ventas_Cotizaciones();
                            $nueva_id = $cotizaciones->duplicar_cotizacion($cotizacion_id);
                            if (!is_wp_error($nueva_id)) {
                                wp_redirect(admin_url('admin.php?page=modulo-ventas-editar-cotizacion&id=' . $nueva_id));
                                exit;
                            } else {
                                $this->agregar_mensaje_admin('error', $nueva_id->get_error_message());
                            }
                        }
                    }
                    break;
            }
        }
        
        // Acciones en lote
        if (isset($_REQUEST['cotizaciones']) && is_array($_REQUEST['cotizaciones'])) {
            $cotizaciones_ids = array_map('intval', $_REQUEST['cotizaciones']);
            
            switch ($action) {
                case 'bulk-delete':
                    if (wp_verify_nonce($nonce, 'bulk-cotizaciones')) {
                        if (current_user_can('delete_cotizaciones')) {
                            $eliminadas = 0;
                            foreach ($cotizaciones_ids as $id) {
                                $resultado = $this->db->eliminar_cotizacion($id);
                                if (!is_wp_error($resultado)) {
                                    $eliminadas++;
                                }
                            }
                            $this->agregar_mensaje_admin('success', sprintf(__('%d cotizaciones eliminadas.', 'modulo-ventas'), $eliminadas));
                        }
                    }
                    break;
                    
                case 'bulk-export':
                    if (wp_verify_nonce($nonce, 'bulk-cotizaciones')) {
                        if (current_user_can('view_cotizaciones')) {
                            $this->exportar_cotizaciones($cotizaciones_ids);
                        }
                    }
                    break;
            }
        }
    }
    
    /**
     * Procesar acciones de clientes
     */
    private function procesar_acciones_clientes() {
        if (!isset($_REQUEST['action']) || !isset($_REQUEST['_wpnonce'])) {
            return;
        }
        
        $action = $_REQUEST['action'];
        $nonce = $_REQUEST['_wpnonce'];
        
        // Acciones individuales
        if (isset($_REQUEST['id'])) {  // Cambiar de 'cliente_id' a 'id' para consistencia con la tabla
            $cliente_id = intval($_REQUEST['id']);
            
            switch ($action) {
                case 'delete':
                    if (wp_verify_nonce($nonce, 'delete_cliente_' . $cliente_id)) {
                        if (current_user_can('manage_clientes_ventas')) {
                            // Usar el nuevo método eliminar_cliente que ya incluye la verificación
                            $resultado = $this->db->eliminar_cliente($cliente_id);
                            
                            if (is_wp_error($resultado)) {
                                $this->agregar_mensaje_admin('error', $resultado->get_error_message());
                            } elseif ($resultado) {
                                $this->agregar_mensaje_admin('success', __('Cliente eliminado correctamente.', 'modulo-ventas'));
                            } else {
                                $this->agregar_mensaje_admin('error', __('Error al eliminar el cliente.', 'modulo-ventas'));
                            }
                        }
                    }
                    break;
            }
        }
    }
    
    /**
     * Procesar formulario de cliente
     */
    private function procesar_formulario_cliente($cliente_id = null) {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['mv_cliente_nonce'], 'mv_guardar_cliente')) {
            $this->agregar_mensaje_admin('error', __('Error de seguridad. Intente nuevamente.', 'modulo-ventas'));
            return;
        }
        
        // Preparar datos
        $datos_cliente = array(
            'razon_social' => sanitize_text_field($_POST['razon_social']),
            'rut' => sanitize_text_field($_POST['rut']),
            'giro_comercial' => sanitize_text_field($_POST['giro_comercial']),
            'telefono' => sanitize_text_field($_POST['telefono']),
            'email' => sanitize_email($_POST['email']),
            'email_dte' => sanitize_email($_POST['email_dte']),
            'direccion_facturacion' => sanitize_textarea_field($_POST['direccion_facturacion']),
            'comuna_facturacion' => sanitize_text_field($_POST['comuna_facturacion']),
            'ciudad_facturacion' => sanitize_text_field($_POST['ciudad_facturacion']),
            'region_facturacion' => sanitize_text_field($_POST['region_facturacion']),
            'pais_facturacion' => sanitize_text_field($_POST['pais_facturacion'] ?: 'Chile'),
            'usar_direccion_facturacion_para_envio' => isset($_POST['usar_direccion_facturacion_para_envio']) ? 1 : 0,
            'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : null
        );
        
        // Dirección de envío si es diferente
        if (!$datos_cliente['usar_direccion_facturacion_para_envio']) {
            $datos_cliente['direccion_envio'] = sanitize_textarea_field($_POST['direccion_envio']);
            $datos_cliente['comuna_envio'] = sanitize_text_field($_POST['comuna_envio']);
            $datos_cliente['ciudad_envio'] = sanitize_text_field($_POST['ciudad_envio']);
            $datos_cliente['region_envio'] = sanitize_text_field($_POST['region_envio']);
            $datos_cliente['pais_envio'] = sanitize_text_field($_POST['pais_envio'] ?: 'Chile');
        }
        
        // Guardar o actualizar
        if ($cliente_id) {
            // Actualizar
            $resultado = $this->db->actualizar_cliente($cliente_id, $datos_cliente);
            
            if (!is_wp_error($resultado)) {
                $this->agregar_mensaje_admin('success', __('Cliente actualizado correctamente.', 'modulo-ventas'));
                
                // Sincronizar con WooCommerce si tiene usuario
                if ($datos_cliente['user_id']) {
                    $clientes = new Modulo_Ventas_Clientes();
                    $clientes->sincronizar_con_woocommerce($cliente_id, $datos_cliente['user_id']);
                }
            } else {
                $this->agregar_mensaje_admin('error', $resultado->get_error_message());
            }
        } else {
            // Crear nuevo
            $nuevo_id = $this->db->crear_cliente($datos_cliente);
            
            if (!is_wp_error($nuevo_id)) {
                wp_redirect(admin_url('admin.php?page=modulo-ventas-editar-cliente&id=' . $nuevo_id . '&message=created'));
                exit;
            } else {
                $this->agregar_mensaje_admin('error', $nuevo_id->get_error_message());
            }
        }
    }
    
    /**
     * Registrar configuración
     */
    public function registrar_configuracion() {
        // Configuración General
        register_setting('modulo_ventas_general', 'modulo_ventas_prefijo_cotizacion');
        register_setting('modulo_ventas_general', 'modulo_ventas_dias_expiracion');
        register_setting('modulo_ventas_general', 'modulo_ventas_reservar_stock');
        register_setting('modulo_ventas_general', 'modulo_ventas_almacen_predeterminado');
        
        // Configuración de Emails
        register_setting('modulo_ventas_emails', 'modulo_ventas_notificar_nueva_cotizacion');
        register_setting('modulo_ventas_emails', 'modulo_ventas_emails_notificacion');
        register_setting('modulo_ventas_emails', 'modulo_ventas_plantilla_email_cotizacion');
        
        // Configuración de Integración
        register_setting('modulo_ventas_integracion', 'modulo_ventas_sincronizar_stock');
        register_setting('modulo_ventas_integracion', 'modulo_ventas_crear_cliente_automatico');
        register_setting('modulo_ventas_integracion', 'modulo_ventas_campos_checkout');
        
        // Configuración de PDF
        register_setting('modulo_ventas_pdf', 'modulo_ventas_logo_empresa');
        register_setting('modulo_ventas_pdf', 'modulo_ventas_info_empresa');
        register_setting('modulo_ventas_pdf', 'modulo_ventas_footer_pdf');
        register_setting('modulo_ventas_pdf', 'modulo_ventas_terminos_condiciones');
    }
    
    /**
     * Obtener estadísticas del dashboard
     */
    private function obtener_estadisticas_dashboard() {
        $stats = array();
        
        // Cotizaciones del mes actual
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-d');
        
        $stats['cotizaciones_mes'] = $this->db->contar_cotizaciones(array(
            'fecha_desde' => $fecha_inicio,
            'fecha_hasta' => $fecha_fin
        ));
        
        // Por estado
        $estados = array('pendiente', 'aprobada', 'rechazada', 'expirada', 'convertida');
        foreach ($estados as $estado) {
            $stats['por_estado'][$estado] = $this->db->contar_cotizaciones(array('estado' => $estado));
        }
        
        // Tasa de conversión
        $total_cotizaciones = array_sum($stats['por_estado']);
        $stats['tasa_conversion'] = $total_cotizaciones > 0 
            ? round(($stats['por_estado']['convertida'] / $total_cotizaciones) * 100, 1) 
            : 0;
        
        // Valor total cotizado este mes
        global $wpdb;
        $tabla = $this->db->get_tabla_cotizaciones();
        $stats['valor_total_mes'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total) FROM {$tabla} WHERE DATE(fecha) BETWEEN %s AND %s",
            $fecha_inicio,
            $fecha_fin
        )) ?: 0;
        
        // Cotizaciones próximas a expirar (próximos 7 días)
        $fecha_limite = date('Y-m-d', strtotime('+7 days'));
        $stats['proximas_expirar'] = $this->db->contar_cotizaciones(array(
            'estado' => 'pendiente',
            'fecha_hasta' => $fecha_limite
        ));
        
        // Top clientes del mes
        $stats['top_clientes'] = $wpdb->get_results($wpdb->prepare(
            "SELECT c.razon_social, COUNT(cot.id) as num_cotizaciones, SUM(cot.total) as total_cotizado
             FROM {$tabla} cot
             INNER JOIN {$this->db->get_tabla_clientes()} c ON cot.cliente_id = c.id
             WHERE DATE(cot.fecha) BETWEEN %s AND %s
             GROUP BY cot.cliente_id
             ORDER BY total_cotizado DESC
             LIMIT 5",
            $fecha_inicio,
            $fecha_fin
        ));
        
        // Productos más cotizados
        $tabla_items = $this->db->get_tabla_cotizaciones_items();
        $stats['top_productos'] = $wpdb->get_results($wpdb->prepare(
            "SELECT ci.nombre, COUNT(DISTINCT ci.cotizacion_id) as veces_cotizado, 
                    SUM(ci.cantidad) as cantidad_total
             FROM {$tabla_items} ci
             INNER JOIN {$tabla} cot ON ci.cotizacion_id = cot.id
             WHERE DATE(cot.fecha) BETWEEN %s AND %s
             GROUP BY ci.producto_id
             ORDER BY veces_cotizado DESC
             LIMIT 5",
            $fecha_inicio,
            $fecha_fin
        ));
        
        return $stats;
    }
    
    /**
     * Generar reportes
     */
    private function generar_reportes($fecha_desde, $fecha_hasta, $vendedor_id = 0, $estado = '') {
        global $wpdb;
        $tabla = $this->db->get_tabla_cotizaciones();
        $tabla_items = $this->db->get_tabla_cotizaciones_items();
        
        $reportes = array();
        
        // Condiciones base
        $where = "DATE(fecha) BETWEEN %s AND %s";
        $params = array($fecha_desde, $fecha_hasta);
        
        if ($vendedor_id) {
            $where .= " AND vendedor_id = %d";
            $params[] = $vendedor_id;
        }
        
        if ($estado) {
            $where .= " AND estado = %s";
            $params[] = $estado;
        }
        
        // Reporte general
        $sql = "SELECT COUNT(*) as total_cotizaciones, 
                       SUM(total) as valor_total,
                       AVG(total) as ticket_promedio,
                       SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as convertidas,
                       SUM(CASE WHEN estado = 'convertida' THEN total ELSE 0 END) as valor_convertido
                FROM {$tabla}
                WHERE {$where}";
        
        $reportes['general'] = $wpdb->get_row($wpdb->prepare($sql, $params));
        
        // Por vendedor
        $sql = "SELECT vendedor_nombre, COUNT(*) as cotizaciones, 
                       SUM(total) as valor_total,
                       SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
                FROM {$tabla}
                WHERE {$where}
                GROUP BY vendedor_id
                ORDER BY valor_total DESC";
        
        $reportes['por_vendedor'] = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Por cliente
        $sql = "SELECT c.razon_social, COUNT(cot.id) as cotizaciones,
                       SUM(cot.total) as valor_total,
                       SUM(CASE WHEN cot.estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
                FROM {$tabla} cot
                INNER JOIN {$this->db->get_tabla_clientes()} c ON cot.cliente_id = c.id
                WHERE {$where}
                GROUP BY cot.cliente_id
                ORDER BY valor_total DESC
                LIMIT 20";
        
        $reportes['por_cliente'] = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Por producto
        $sql = "SELECT ci.nombre, COUNT(DISTINCT ci.cotizacion_id) as veces_cotizado,
                       SUM(ci.cantidad) as cantidad_total,
                       SUM(ci.total) as valor_total
                FROM {$tabla_items} ci
                INNER JOIN {$tabla} cot ON ci.cotizacion_id = cot.id
                WHERE {$where}
                GROUP BY ci.producto_id
                ORDER BY valor_total DESC
                LIMIT 20";
        
        $reportes['por_producto'] = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Evolución diaria
        $sql = "SELECT DATE(fecha) as dia, COUNT(*) as cotizaciones,
                       SUM(total) as valor_total
                FROM {$tabla}
                WHERE {$where}
                GROUP BY DATE(fecha)
                ORDER BY dia ASC";
        
        $reportes['evolucion_diaria'] = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        return $reportes;
    }
    
    /**
     * Obtener vendedores
     */
    private function obtener_vendedores() {
        global $wpdb;
        $tabla = $this->db->get_tabla_cotizaciones();
        
        return $wpdb->get_results(
            "SELECT DISTINCT vendedor_id, vendedor_nombre 
             FROM {$tabla} 
             WHERE vendedor_id IS NOT NULL 
             ORDER BY vendedor_nombre"
        );
    }
    
    /**
     * Obtener estadísticas completas
     */
    private function obtener_estadisticas_completas($periodo) {
        // Definir fechas según período
        switch ($periodo) {
            case 'hoy':
                $fecha_desde = date('Y-m-d');
                $fecha_hasta = date('Y-m-d');
                break;
                
            case 'semana':
                $fecha_desde = date('Y-m-d', strtotime('monday this week'));
                $fecha_hasta = date('Y-m-d');
                break;
                
            case 'mes_actual':
                $fecha_desde = date('Y-m-01');
                $fecha_hasta = date('Y-m-d');
                break;
                
            case 'mes_anterior':
                $fecha_desde = date('Y-m-01', strtotime('first day of last month'));
                $fecha_hasta = date('Y-m-t', strtotime('last day of last month'));
                break;
                
            case 'año':
                $fecha_desde = date('Y-01-01');
                $fecha_hasta = date('Y-m-d');
                break;
                
            default:
                $fecha_desde = date('Y-m-01');
                $fecha_hasta = date('Y-m-d');
        }
        
        return $this->generar_reportes($fecha_desde, $fecha_hasta);
    }
    
    /**
     * Obtener usuarios sin cliente asociado
     */
    private function obtener_usuarios_sin_cliente($incluir_usuario_id = null) {
        global $wpdb;
        
        $sql = "SELECT u.ID, u.display_name, u.user_email
                FROM {$wpdb->users} u
                WHERE u.ID NOT IN (
                    SELECT user_id FROM {$this->db->get_tabla_clientes()} 
                    WHERE user_id IS NOT NULL
                )";
        
        if ($incluir_usuario_id) {
            $sql .= " OR u.ID = " . intval($incluir_usuario_id);
        }
        
        $sql .= " ORDER BY u.display_name";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Obtener estadísticas de un cliente
     */
    private function obtener_estadisticas_cliente($cliente_id) {
        global $wpdb;
        $tabla = $this->db->get_tabla_cotizaciones();
        
        $stats = array();
        
        // Total de cotizaciones
        $stats['total_cotizaciones'] = $this->db->contar_cotizaciones(array(
            'cliente_id' => $cliente_id
        ));
        
        // Cotizaciones por estado
        $stats['por_estado'] = $wpdb->get_results($wpdb->prepare(
            "SELECT estado, COUNT(*) as cantidad
            FROM {$tabla}
            WHERE cliente_id = %d
            GROUP BY estado",
            $cliente_id
        ));
        
        // Valor total cotizado
        $stats['valor_total'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total) FROM {$tabla} WHERE cliente_id = %d",
            $cliente_id
        )) ?: 0;
        
        // Valor convertido en ventas
        $stats['valor_convertido'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total) FROM {$tabla} 
            WHERE cliente_id = %d AND estado = 'convertida'",
            $cliente_id
        )) ?: 0;
        
        // Última cotización
        $stats['ultima_cotizacion'] = $wpdb->get_row($wpdb->prepare(
            "SELECT fecha, folio, total, estado
            FROM {$tabla}
            WHERE cliente_id = %d
            ORDER BY fecha DESC
            LIMIT 1",
            $cliente_id
        ));
        
        return $stats;
    }
    
    /**
     * Procesar herramienta
     */
    private function procesar_herramienta($herramienta) {
        switch ($herramienta) {
            case 'verificar_expiracion':
                $cotizaciones = new Modulo_Ventas_Cotizaciones();
                $actualizadas = $cotizaciones->verificar_cotizaciones_expiradas();
                return sprintf(__('%d cotizaciones actualizadas a expiradas.', 'modulo-ventas'), $actualizadas);
                
            case 'limpiar_logs':
                $eliminados = $this->logger->limpiar_logs_antiguos(30);
                return sprintf(__('%d registros de log eliminados.', 'modulo-ventas'), $eliminados);
                
            case 'sincronizar_stock':
                if (class_exists('Gestion_Almacenes_DB')) {
                    // Implementar sincronización
                    return __('Sincronización de stock completada.', 'modulo-ventas');
                } else {
                    return __('El plugin de Gestión de Almacenes no está activo.', 'modulo-ventas');
                }
                
            case 'reindexar_busquedas':
                // Implementar reindexación si es necesario
                return __('Índices de búsqueda actualizados.', 'modulo-ventas');
                
            default:
                return __('Herramienta no reconocida.', 'modulo-ventas');
        }
    }
    
    /**
     * Exportar cotizaciones
     */
    private function exportar_cotizaciones($ids = array()) {
        // Implementar exportación a CSV/Excel
        // Por ahora, redirigir con mensaje
        $this->agregar_mensaje_admin('info', __('La función de exportación está en desarrollo.', 'modulo-ventas'));
    }
    
    /**
     * Obtener configuración
     */
    private function obtener_configuracion() {
        return array(
            'prefijo_cotizacion' => get_option('modulo_ventas_prefijo_cotizacion', 'COT'),
            'dias_expiracion' => get_option('modulo_ventas_dias_expiracion', 30),
            'reservar_stock' => get_option('modulo_ventas_reservar_stock', 'no'),
            'almacen_predeterminado' => get_option('modulo_ventas_almacen_predeterminado', 0),
            'notificar_nueva_cotizacion' => get_option('modulo_ventas_notificar_nueva_cotizacion', 'yes'),
            'emails_notificacion' => get_option('modulo_ventas_emails_notificacion', get_option('admin_email')),
            'sincronizar_stock' => get_option('modulo_ventas_sincronizar_stock', 'yes'),
            'crear_cliente_automatico' => get_option('modulo_ventas_crear_cliente_automatico', 'no')
        );
    }
    
    /*
     * Agregar mensaje administrativo
     
    private function agregar_mensaje_admin($tipo, $mensaje) {
        add_settings_error(
            'modulo_ventas_messages',
            'modulo_ventas_message',
            $mensaje,
            $tipo
        );
    }*/
    
    /**
     * Mostrar avisos administrativos
     */
    public function mostrar_avisos_admin() {
        settings_errors('modulo_ventas_messages');
        
        // Verificar si hay tablas faltantes
        $tablas_faltantes = $this->db->verificar_tablas();
        if (!empty($tablas_faltantes)) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Módulo de Ventas:', 'modulo-ventas'); ?></strong>
                    <?php _e('Faltan tablas en la base de datos. Por favor, desactive y vuelva a activar el plugin.', 'modulo-ventas'); ?>
                </p>
            </div>
            <?php
        }
        
        // Mensaje de bienvenida después de crear cliente
        if (isset($_GET['page']) && $_GET['page'] === 'modulo-ventas-editar-cliente' && isset($_GET['message']) && $_GET['message'] === 'created') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Cliente creado exitosamente.', 'modulo-ventas'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Agregar acciones en lote para pedidos
     */
    public function agregar_acciones_lote_pedidos($actions) {
        $actions['crear_cotizacion'] = __('Crear cotización', 'modulo-ventas');
        return $actions;
    }
    
    /**
     * Manejar acciones en lote de pedidos
     */
    public function manejar_acciones_lote_pedidos($redirect_to, $action, $post_ids) {
        if ($action !== 'crear_cotizacion') {
            return $redirect_to;
        }
        
        // Por implementar: crear cotizaciones desde pedidos seleccionados
        
        return $redirect_to;
    }
    
    /**
     * Filtro para usuarios con cliente
     */
    public function filtro_usuarios_con_cliente() {
        $filtro = isset($_GET['cliente_status']) ? $_GET['cliente_status'] : '';
        ?>
        <select name="cliente_status" style="float:none;margin-left:10px;">
            <option value=""><?php _e('Todos los usuarios', 'modulo-ventas'); ?></option>
            <option value="con_cliente" <?php selected($filtro, 'con_cliente'); ?>>
                <?php _e('Con cliente asociado', 'modulo-ventas'); ?>
            </option>
            <option value="sin_cliente" <?php selected($filtro, 'sin_cliente'); ?>>
                <?php _e('Sin cliente asociado', 'modulo-ventas'); ?>
            </option>
        </select>
        <?php
    }
    
    /**
     * Filtrar query de usuarios
     */
    public function filtrar_usuarios_query($query) {
        global $pagenow;
        
        if ($pagenow !== 'users.php' || !isset($_GET['cliente_status'])) {
            return;
        }
        
        global $wpdb;
        $tabla_clientes = $this->db->get_tabla_clientes();
        
        if ($_GET['cliente_status'] === 'con_cliente') {
            $query->query_from .= " INNER JOIN {$tabla_clientes} mc ON {$wpdb->users}.ID = mc.user_id";
        } elseif ($_GET['cliente_status'] === 'sin_cliente') {
            $query->query_from .= " LEFT JOIN {$tabla_clientes} mc ON {$wpdb->users}.ID = mc.user_id";
            $query->query_where .= " AND mc.user_id IS NULL";
        }
    }
    
    /**
     * AJAX: Actualizar almacén de pedido
     */
    public function ajax_actualizar_almacen_pedido() {
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
        } else {
            $order->delete_meta_data('_almacen_id');
        }
        
        $order->save();
        
        wp_send_json_success(array('message' => __('Almacén actualizado', 'modulo-ventas')));
    }
    
    /**
     * AJAX: Obtener comunas
     */
    public function ajax_obtener_comunas() {
        check_ajax_referer('mv_obtener_comunas', 'nonce');
        
        $region = isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '';
        
        if (empty($region)) {
            wp_send_json_error(array('message' => __('Región no especificada', 'modulo-ventas')));
        }
        
        $clientes = new Modulo_Ventas_Clientes();
        $comunas = $clientes->obtener_comunas_por_region($region);
        
        wp_send_json_success(array('comunas' => $comunas));
    }
    
    /**
     * AJAX: Exportar cotizaciones
     */
    public function ajax_exportar_cotizaciones() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('view_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        // Por implementar
        wp_send_json_error(array('message' => __('Función en desarrollo', 'modulo-ventas')));
    }
    
    /**
     * AJAX: Obtener estadísticas
     */
    public function ajax_obtener_estadisticas() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('view_reportes_ventas')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'general';
        $periodo = isset($_POST['periodo']) ? sanitize_text_field($_POST['periodo']) : 'mes_actual';
        
        $estadisticas = $this->obtener_estadisticas_completas($periodo);
        
        wp_send_json_success(array('estadisticas' => $estadisticas));
    }
}