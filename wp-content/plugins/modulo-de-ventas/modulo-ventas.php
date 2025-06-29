<?php
/**
 * Plugin Name: Módulo de Ventas
 * Plugin URI: https://hostpanish.com/personalizaciones/
 * Description: Sistema de cotizaciones y ventas integrado con WooCommerce y Gestión de Almacenes
 * Version: 2.0.0
 * Author: Victor Moraga
 * Author URI: https://hostpanish.com/
 * License: GPL2
 * Text Domain: modulo-ventas
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('MODULO_VENTAS_VERSION', '2.0.0');
define('MODULO_VENTAS_DB_VERSION', '2.0.0');
define('MODULO_VENTAS_PLUGIN_FILE', __FILE__);
define('MODULO_VENTAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MODULO_VENTAS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MODULO_VENTAS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Clase principal del plugin
class Modulo_Ventas {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    // Instancias de las clases principales
    private $db;
    private $admin;
    private $ajax;
    private $cotizaciones;
    private $clientes;
    private $integration;
    private $logger;
    
    // Obtener instancia única
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Constructor
    private function __construct() {
        // Cargar archivos básicos que no usan traducciones inmediatamente
        //$this->cargar_archivos_basicos();
        
        // Verificar dependencias
        add_action('plugins_loaded', array($this, 'verificar_dependencias'), 5);
        
        // Cargar traducciones en el momento correcto
        add_action('init', array($this, 'cargar_textdomain'), 0);
        
        // Hooks de activación/desactivación
        register_activation_hook(MODULO_VENTAS_PLUGIN_FILE, array($this, 'activar'));
        register_deactivation_hook(MODULO_VENTAS_PLUGIN_FILE, array($this, 'desactivar'));
        
        // Declarar compatibilidad con HPOS
        add_action('before_woocommerce_init', function() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        
        // Inicializar el plugin
        add_action('init', array($this, 'init'), 10);
    }
    
    // Cargar archivos básicos que no dependen de traducciones
    /*private function cargar_archivos_basicos() {
        // Solo cargar helpers.php si estamos en el admin y es necesario
        if (is_admin()) {
            // Cargar helpers pero sin ejecutar código que use traducciones
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/helpers-basic.php';
        }
    }*/
    
    // Verificar dependencias del plugin
    public function verificar_dependencias() {
        $dependencias_cumplidas = true;
        $mensajes_error = array();
        
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            $dependencias_cumplidas = false;
            // NO usar traducciones aquí, usar texto plano
            $mensajes_error[] = 'Módulo de Ventas requiere WooCommerce para funcionar.';
        }
        
        // Verificar Gestión de Almacenes
        if (!defined('GESTION_ALMACENES_VERSION')) {
            $dependencias_cumplidas = false;
            // NO usar traducciones aquí, usar texto plano
            $mensajes_error[] = 'Módulo de Ventas requiere el plugin Gestión de Almacenes para funcionar.';
        }
        
        // Si faltan dependencias, mostrar avisos y desactivar
        if (!$dependencias_cumplidas) {
            add_action('admin_notices', function() use ($mensajes_error) {
                foreach ($mensajes_error as $mensaje) {
                    echo '<div class="error"><p>' . esc_html($mensaje) . '</p></div>';
                }
            });
            
            // Desactivar el plugin
            if (function_exists('deactivate_plugins')) {
                deactivate_plugins(MODULO_VENTAS_PLUGIN_BASENAME);
            }
            return false;
        }
        
        // Si todo está bien, cargar archivos necesarios
        $this->cargar_archivos();
        $this->inicializar_clases();
        
        return true;
    }
    
    // Cargar archivos del plugin
    private function cargar_archivos() {
        // Clases base
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/helpers.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-db.php';
        //require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-updater.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-logger.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-messages.php';
        
        // Clases de funcionalidad
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-clientes.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-cotizaciones.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-integration.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf.php';
        
        // Admin
        if (is_admin()) {
            require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-admin.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-ajax.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-cotizaciones-list-table.php';
        }
    }
    
    // Inicializar clases
    private function inicializar_clases() {
        // Instanciar clases principales
        $this->logger = Modulo_Ventas_Logger::get_instance();
        $this->db = new Modulo_Ventas_DB();
        $this->clientes = new Modulo_Ventas_Clientes();
        $this->cotizaciones = new Modulo_Ventas_Cotizaciones();
        $this->integration = new Modulo_Ventas_Integration();
        
        // Admin solo si estamos en el área administrativa
        if (is_admin()) {
            $this->admin = new Modulo_Ventas_Admin();
            $this->ajax = new Modulo_Ventas_Ajax();
        }
    }
    
    // Inicialización del plugin
    public function init() {
        // Iniciar sesión si es necesario
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        // Cargar estilos y scripts
        add_action('admin_enqueue_scripts', array($this, 'cargar_assets_admin'));
        add_action('wp_enqueue_scripts', array($this, 'cargar_assets_frontend'));
        
        // Hook para mostrar mensajes en admin
        add_action('admin_notices', array($this, 'mostrar_mensajes_admin'));
        
        // Hooks personalizados para otros plugins
        do_action('modulo_ventas_init');
    }
    
    // Cargar archivos de traducción
    public function cargar_textdomain() {
        load_plugin_textdomain(
            'modulo-ventas',
            false,
            dirname(MODULO_VENTAS_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    // Cargar assets para el admin
    public function cargar_assets_admin($hook) {
        $screen = get_current_screen();
        
        // Solo cargar en páginas del plugin
        if ($screen && strpos($screen->id, 'modulo-ventas') !== false) {
            // jQuery UI
            wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('jquery-ui-datepicker');
            
            // Select2
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));
            
            // Estilos y scripts del plugin
            wp_enqueue_style(
                'modulo-ventas-admin',
                MODULO_VENTAS_PLUGIN_URL . 'assets/css/admin-styles.css',
                array(),
                MODULO_VENTAS_VERSION
            );
            
            wp_enqueue_script(
                'modulo-ventas-admin',
                MODULO_VENTAS_PLUGIN_URL . 'assets/js/admin-scripts.js',
                array('jquery', 'jquery-ui-dialog', 'jquery-ui-datepicker', 'select2'),
                MODULO_VENTAS_VERSION,
                true
            );
            
            // Localización para JavaScript
            wp_localize_script('modulo-ventas-admin', 'moduloVentasAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('modulo_ventas_nonce'),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_position' => get_option('woocommerce_currency_pos'),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
                'i18n' => array(
                    'confirm_delete' => __('¿Está seguro de eliminar este elemento?', 'modulo-ventas'),
                    'loading' => __('Cargando...', 'modulo-ventas'),
                    'error' => __('Ha ocurrido un error', 'modulo-ventas'),
                    'select_client' => __('Seleccione un cliente', 'modulo-ventas'),
                    'select_product' => __('Seleccione un producto', 'modulo-ventas'),
                    'select_warehouse' => __('Seleccione un almacén', 'modulo-ventas'),
                )
            ));
        }
    }
    
    /**
     * Cargar assets para el frontend
     */
    public function cargar_assets_frontend() {
        // Por ahora no necesitamos assets en el frontend
    }
    
    // Mostrar mensajes en el admin
    public function mostrar_mensajes_admin() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'modulo-ventas') !== false) {
            Modulo_Ventas_Messages::get_instance()->display_messages();
        }
    }
    
    /**
     * Activación del plugin
     */
    public function activar() {
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(MODULO_VENTAS_PLUGIN_BASENAME);
            wp_die(__('Este plugin requiere PHP 7.4 o superior.', 'modulo-ventas'));
        }
        
        $this->cargar_archivos();
    
        // Crear instancia temporal de DB para la activación
        $db = new Modulo_Ventas_DB();
        $db->crear_tablas();
        
        // Crear directorios necesarios
        $this->crear_directorios();
        
        // Agregar capacidades
        $this->agregar_capacidades();
        
        // Programar tareas cron
        $this->programar_cron();
        
        // Guardar versión
        update_option('modulo_ventas_version', MODULO_VENTAS_VERSION);
        update_option('modulo_ventas_db_version', MODULO_VENTAS_DB_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log de activación
        if (class_exists('Modulo_Ventas_Logger')) {
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log('Plugin activado correctamente', 'info');
        }
    }
    
    /**
     * Desactivación del plugin
     */
    public function desactivar() {
        // Desprogramar tareas cron
        $this->desprogramar_cron();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log de desactivación
        if (class_exists('Modulo_Ventas_Logger')) {
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log('Plugin desactivado', 'info');
        }
    }
    
    // Crear directorios necesarios
    private function crear_directorios() {
        $upload_dir = wp_upload_dir();
        $dirs = array(
            $upload_dir['basedir'] . '/modulo-ventas',
            $upload_dir['basedir'] . '/modulo-ventas/cotizaciones',
            $upload_dir['basedir'] . '/modulo-ventas/logs',
            $upload_dir['basedir'] . '/modulo-ventas/temp',
        );
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Crear archivo .htaccess para proteger los directorios
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, 'deny from all');
                }
            }
        }
    }
    
    // Agregar capacidades a roles
    private function agregar_capacidades() {
        $roles = array('administrator', 'shop_manager');
        $capacidades = array(
            'manage_modulo_ventas',
            'view_cotizaciones',
            'create_cotizaciones',
            'edit_cotizaciones',
            'delete_cotizaciones',
            'manage_clientes_ventas',
            'view_reportes_ventas'
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capacidades as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    // Programar tareas cron
    private function programar_cron() {
        // Verificar cotizaciones expiradas diariamente
        if (!wp_next_scheduled('modulo_ventas_check_expired_quotes')) {
            wp_schedule_event(time(), 'daily', 'modulo_ventas_check_expired_quotes');
        }
        
        // Limpiar logs antiguos semanalmente
        if (!wp_next_scheduled('modulo_ventas_clean_old_logs')) {
            wp_schedule_event(time(), 'weekly', 'modulo_ventas_clean_old_logs');
        }
    }
    
    // Desprogramar tareas cron
    private function desprogramar_cron() {
        wp_clear_scheduled_hook('modulo_ventas_check_expired_quotes');
        wp_clear_scheduled_hook('modulo_ventas_clean_old_logs');
    }
    
    
    // Getters para acceder a las instancias
    public function get_db() {
        return $this->db;
    }
    
    public function get_clientes() {
        return $this->clientes;
    }
    
    public function get_cotizaciones() {
        return $this->cotizaciones;
    }
    
    public function get_integration() {
        return $this->integration;
    }
    
    public function get_logger() {
        return $this->logger;
    }
}

//Función principal para obtener la instancia del plugin
function modulo_ventas() {
    return Modulo_Ventas::get_instance();
}

// Inicializar el plugin
modulo_ventas();

//Hook para verificar actualizaciones del plugin
add_action('plugins_loaded', function() {
    $version_actual = get_option('modulo_ventas_version', '0');
    
    if (version_compare($version_actual, MODULO_VENTAS_VERSION, '<')) {
        // Ejecutar actualizaciones necesarias
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-updater.php';
        $updater = new Modulo_Ventas_Updater($version_actual, MODULO_VENTAS_VERSION);
        $updater->run();
    }
}, 20);

/**
 * Verificar tablas en cada carga del admin
 */
add_action('admin_init', 'modulo_ventas_verificar_tablas');

function modulo_ventas_verificar_tablas() {
    // Solo verificar una vez por sesión
    if (get_transient('mv_tables_checked')) {
        return;
    }
    
    $db = new Modulo_Ventas_DB();
    $tablas_faltantes = $db->verificar_tablas();
    
    if (!empty($tablas_faltantes)) {
        // Si faltan tablas, crearlas
        $db->crear_tablas();
        
        // Agregar mensaje de administrador
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>' . __('Las tablas del Módulo de Ventas han sido creadas/actualizadas.', 'modulo-ventas') . '</p>';
            echo '</div>';
        });
    }
    
    // Marcar como verificado por 12 horas
    set_transient('mv_tables_checked', true, 12 * HOUR_IN_SECONDS);
}

/**
 * Crear roles y capacidades
 */
function modulo_ventas_crear_roles() {
    // Obtener el rol de administrador
    $admin = get_role('administrator');
    
    if ($admin) {
        // Capacidades de cotizaciones
        $admin->add_cap('view_cotizaciones');
        $admin->add_cap('create_cotizaciones');
        $admin->add_cap('edit_cotizaciones');
        $admin->add_cap('delete_cotizaciones');
        $admin->add_cap('convert_cotizaciones');
        
        // Capacidades de clientes
        $admin->add_cap('manage_clientes_ventas');
        
        // Capacidades de reportes
        $admin->add_cap('view_reportes_ventas');
        
        // Capacidades de configuración
        $admin->add_cap('manage_modulo_ventas');
    }
    
    // Crear rol de vendedor si no existe
    if (!get_role('vendedor')) {
        add_role('vendedor', __('Vendedor', 'modulo-ventas'), array(
            'read' => true,
            'view_cotizaciones' => true,
            'create_cotizaciones' => true,
            'edit_cotizaciones' => true,
            'manage_clientes_ventas' => true
        ));
    }
}

// Asegurar que los handlers AJAX se registren temprano
add_action('plugins_loaded', function() {
    // Solo procesar si es una petición AJAX
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        return;
    }
    
    // Solo para acciones del módulo de ventas
    if (!isset($_REQUEST['action']) || strpos($_REQUEST['action'], 'mv_') !== 0) {
        return;
    }
    
    // Log para debug
    error_log('=== CARGANDO AJAX TEMPRANO PARA: ' . $_REQUEST['action'] . ' ===');
    
    // Cargar dependencias mínimas necesarias
    if (!class_exists('Modulo_Ventas_DB')) {
        $files_to_load = array(
            'includes/helpers.php',
            'includes/class-modulo-ventas-db.php',
            'includes/class-modulo-ventas-logger.php',
            'includes/class-modulo-ventas-messages.php',
            'includes/class-modulo-ventas-clientes.php',
            'includes/class-modulo-ventas-cotizaciones.php',
            'admin/class-modulo-ventas-ajax.php'
        );
        
        foreach ($files_to_load as $file) {
            $file_path = MODULO_VENTAS_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log('ERROR: No se encontró el archivo: ' . $file_path);
            }
        }
    }
    
    // Instanciar la clase AJAX si no existe
    if (class_exists('Modulo_Ventas_Ajax')) {
        error_log('Instanciando Modulo_Ventas_Ajax temprano');
        new Modulo_Ventas_Ajax();
        
        // Verificar que se registró
        if (has_action('wp_ajax_' . $_REQUEST['action'])) {
            error_log('✓ Handler registrado correctamente para: ' . $_REQUEST['action']);
        } else {
            error_log('✗ ERROR: Handler NO registrado para: ' . $_REQUEST['action']);
        }
    } else {
        error_log('ERROR: Clase Modulo_Ventas_Ajax no disponible');
    }
}, 5); // Prioridad 5 - muy temprano

/**
 * ALTERNATIVA MÁS SIMPLE
 * Si lo anterior no funciona, agregar handler directo
 */
add_action('wp_ajax_mv_crear_cliente_rapido', function() {
    // Solo ejecutar si no hay otro handler registrado
    if (did_action('wp_ajax_mv_crear_cliente_rapido') > 1) {
        return;
    }
    
    error_log('=== HANDLER DIRECTO DE RESPALDO EJECUTADO ===');
    
    // Cargar lo necesario si no está cargado
    if (!function_exists('mv_validar_rut')) {
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/helpers.php';
    }
    
    if (!class_exists('Modulo_Ventas_DB')) {
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-db.php';
    }
    
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'modulo_ventas_nonce')) {
        wp_send_json_error(array('message' => 'Error de seguridad'));
        return;
    }
    
    // Validar datos
    if (!isset($_POST['cliente'])) {
        wp_send_json_error(array('message' => 'Datos del cliente no proporcionados'));
        return;
    }
    
    $datos = $_POST['cliente'];
    
    if (empty($datos['razon_social']) || empty($datos['rut'])) {
        wp_send_json_error(array('message' => 'Razón social y RUT son obligatorios'));
        return;
    }
    
    if (!mv_validar_rut($datos['rut'])) {
        wp_send_json_error(array('message' => 'RUT inválido'));
        return;
    }
    
    // Preparar datos
    $datos_cliente = array(
        'razon_social' => sanitize_text_field($datos['razon_social']),
        'rut' => mv_limpiar_rut($datos['rut']),
        'giro_comercial' => isset($datos['giro_comercial']) ? sanitize_text_field($datos['giro_comercial']) : '',
        'telefono' => isset($datos['telefono']) ? sanitize_text_field($datos['telefono']) : '',
        'email' => isset($datos['email']) ? sanitize_email($datos['email']) : '',
        'direccion_facturacion' => isset($datos['direccion_facturacion']) ? sanitize_textarea_field($datos['direccion_facturacion']) : '',
        'comuna_facturacion' => isset($datos['comuna_facturacion']) ? sanitize_text_field($datos['comuna_facturacion']) : '',
        'ciudad_facturacion' => isset($datos['ciudad_facturacion']) ? sanitize_text_field($datos['ciudad_facturacion']) : '',
        'region_facturacion' => isset($datos['region_facturacion']) ? sanitize_text_field($datos['region_facturacion']) : ''
    );
    
    // Crear cliente
    $db = new Modulo_Ventas_DB();
    $cliente_id = $db->crear_cliente($datos_cliente);
    
    if (is_wp_error($cliente_id)) {
        wp_send_json_error(array('message' => $cliente_id->get_error_message()));
        return;
    }
    
    // Obtener cliente creado
    $cliente = $db->obtener_cliente($cliente_id);
    
    // Respuesta exitosa
    wp_send_json_success(array(
        'cliente' => array(
            'id' => $cliente->id,
            'razon_social' => $cliente->razon_social,
            'rut' => function_exists('mv_formatear_rut') ? mv_formatear_rut($cliente->rut) : $cliente->rut,
            'email' => $cliente->email,
            'telefono' => $cliente->telefono,
            'direccion' => $cliente->direccion_facturacion,
            'giro' => $cliente->giro_comercial
        ),
        'message' => 'Cliente creado exitosamente'
    ));
}, 9999); // Prioridad muy baja para ser el respaldo