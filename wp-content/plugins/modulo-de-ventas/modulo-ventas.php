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

// Hook para detectar cuando se desactiva el plugin
add_action('deactivate_plugin', function($plugin, $network_deactivating) {
    if ($plugin === 'modulo-de-ventas/modulo-ventas.php') {
        $log_file = WP_CONTENT_DIR . '/modulo-ventas-deactivation.log';
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        $log_data = date('Y-m-d H:i:s') . " - Plugin siendo desactivado\n";
        $log_data .= "Network deactivating: " . ($network_deactivating ? 'YES' : 'NO') . "\n";
        $log_data .= "Backtrace:\n";
        
        foreach ($backtrace as $i => $call) {
            $file = isset($call['file']) ? str_replace(ABSPATH, '', $call['file']) : 'unknown';
            $line = isset($call['line']) ? $call['line'] : 0;
            $function = isset($call['function']) ? $call['function'] : 'unknown';
            $log_data .= "  #{$i} {$file}:{$line} - {$function}()\n";
        }
        
        $log_data .= "\n" . str_repeat('-', 80) . "\n\n";
        
        file_put_contents($log_file, $log_data, FILE_APPEND);
    }
}, 10, 2);

// Shutdown handler para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log_file = WP_CONTENT_DIR . '/modulo-ventas-fatal-error.log';
        $log_data = date('Y-m-d H:i:s') . " - Error fatal detectado\n";
        $log_data .= "Type: " . $error['type'] . "\n";
        $log_data .= "Message: " . $error['message'] . "\n";
        $log_data .= "File: " . str_replace(ABSPATH, '', $error['file']) . "\n";
        $log_data .= "Line: " . $error['line'] . "\n";
        $log_data .= "\n" . str_repeat('-', 80) . "\n\n";
        
        file_put_contents($log_file, $log_data, FILE_APPEND);
    }
});

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
        add_action('plugins_loaded', array($this, 'cargar_plugin'), 10);
        add_action('admin_init', array($this, 'verificar_dependencias_admin'), 10);
        
        // Cargar traducciones en el momento correcto
        add_action('init', array($this, 'cargar_textdomain'), 0);

        // Hook muy temprano para AJAX
        add_action('init', array($this, 'init_ajax_temprano'), 5);
        
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

        // Sistema de plantillas PDF personalizables (NUEVO)
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates-db.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates-ajax.php';
        
        // PDF Handler - VERIFICAR ARCHIVO EXISTE
        $pdf_handler_path = MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-handler.php';
        if (file_exists($pdf_handler_path)) {
            require_once $pdf_handler_path;
        } else {
            error_log('MODULO_VENTAS: PDF Handler no encontrado en: ' . $pdf_handler_path);
        }
        
        // AJAX siempre se carga
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-ajax.php';
        
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-admin.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-cotizaciones-list-table.php';
        
    }
    
    // Inicializar clases
    private function inicializar_clases() {
        // Instanciar clases principales
        $this->logger = Modulo_Ventas_Logger::get_instance();
        $this->db = new Modulo_Ventas_DB();
        $this->clientes = new Modulo_Ventas_Clientes();
        $this->cotizaciones = new Modulo_Ventas_Cotizaciones();
        $this->integration = new Modulo_Ventas_Integration();

        // PDF Handler solo si existe la clase
        if (class_exists('Modulo_Ventas_PDF_Handler')) {
            $this->pdf_handler = new Modulo_Ventas_PDF_Handler();
        } else {
            error_log('MODULO_VENTAS: Clase Modulo_Ventas_PDF_Handler no encontrada');
        }

        // Sistema de plantillas PDF (NUEVO) - también para frontend si es necesario
        if (!isset($this->pdf_templates)) {
            $this->pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
            
            // AJAX handler para plantillas PDF
            new Modulo_Ventas_PDF_Templates_Ajax();
            
            error_log('MODULO_VENTAS: Sistema de plantillas PDF inicializado globalmente');
        }
        
        // Ajax SIEMPRE se necesita (tanto en admin como en peticiones AJAX)
        $this->ajax = new Modulo_Ventas_Ajax();
        
        // Admin solo si estamos en el área administrativa
        if (is_admin()) {
            $this->admin = new Modulo_Ventas_Admin();
            //error_log('Clase Admin instanciada: ' . get_class($this->admin));
            $this->pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
            error_log('MODULO_VENTAS: Sistema de plantillas PDF inicializado');
        }
        
        // Log para debug
        if (defined('DOING_AJAX') && DOING_AJAX) {
            //error_log('Petición AJAX detectada');
        }
    }

    // NUEVO MÉTODO: Inicialización temprana para AJAX
    public function init_ajax_temprano() {
        // Solo para peticiones AJAX o si estamos en admin
        if (wp_doing_ajax() || is_admin()) {
            // Cargar TCPDF lo más temprano posible
            $this->cargar_tcpdf_global();
            
            // Log para debug
            if (wp_doing_ajax()) {
                error_log('MODULO_VENTAS: Inicialización temprana AJAX - TCPDF disponible: ' . (class_exists('TCPDF') ? 'SI' : 'NO'));
            }
        }
    }

    // Cargar TCPDF globalmente
    private function cargar_tcpdf_global() {
        // Si ya está cargado, no hacer nada
        if (class_exists('TCPDF')) {
            return;
        }
        
        // Intentar autoload primero
        $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            
            if (class_exists('TCPDF')) {
                error_log('MODULO_VENTAS: TCPDF cargado via autoload');
                return;
            }
        }
        
        // Cargar manualmente como fallback
        $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
            
            if (class_exists('TCPDF')) {
                error_log('MODULO_VENTAS: TCPDF cargado manualmente');
                return;
            }
        }
        
        // Log si falla
        error_log('MODULO_VENTAS: ERROR - No se pudo cargar TCPDF');
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

    // Y crea estos nuevos métodos:
    public function cargar_plugin() {
        $this->cargar_archivos();
        $this->inicializar_clases();
    }

    public function verificar_dependencias_admin() {
        // Solo verificar en el admin, no en AJAX
        if (wp_doing_ajax()) {
            return; // No hacer nada durante AJAX
        }
        
        $dependencias_cumplidas = true;
        $mensajes_error = array();
        
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            $dependencias_cumplidas = false;
            $mensajes_error[] = 'Módulo de Ventas requiere WooCommerce para funcionar.';
        }
        
        // Verificar Gestión de Almacenes
        if (!defined('GESTION_ALMACENES_VERSION')) {
            $dependencias_cumplidas = false;
            $mensajes_error[] = 'Módulo de Ventas requiere el plugin Gestión de Almacenes para funcionar.';
        }
        
        // Si faltan dependencias, mostrar avisos
        if (!$dependencias_cumplidas) {
            add_action('admin_notices', function() use ($mensajes_error) {
                foreach ($mensajes_error as $mensaje) {
                    echo '<div class="error"><p>' . esc_html($mensaje) . '</p></div>';
                }
            });
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

        // Crear tablas de plantillas PDF
        $pdf_db = new Modulo_Ventas_PDF_Templates_DB();
        $pdf_db->crear_tablas();
        
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
            $upload_dir['basedir'] . '/modulo-ventas/pdfs',
            $upload_dir['basedir'] . '/modulo-ventas/previews',
        );
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Configuración específica por directorio
                $this->crear_htaccess_directorio($dir);
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

    /**
     * Manejar requests de PDF vía URL
     */
    public function manejar_requests_pdf() {
        // Verificar si es un request para PDF
        if (!isset($_GET['mv_pdf']) || !isset($_GET['cotizacion_id'])) {
            return;
        }
        
        $cotizacion_id = intval($_GET['cotizacion_id']);
        $accion = sanitize_text_field($_GET['mv_pdf']);
        
        // Verificar permisos básicos
        if (!current_user_can('read') && !$this->verificar_acceso_publico($cotizacion_id)) {
            wp_die(__('No tienes permisos para acceder a este PDF', 'modulo-ventas'));
        }
        
        try {
            switch ($accion) {
                case 'generar':
                case 'download':
                    $this->generar_pdf_cotizacion($cotizacion_id, $accion === 'download');
                    break;
                    
                case 'preview':
                    $this->generar_pdf_cotizacion($cotizacion_id, false, true);
                    break;
                    
                default:
                    wp_die(__('Acción no válida', 'modulo-ventas'));
            }
            
        } catch (Exception $e) {
            if (isset($this->logger)) {
                $this->logger->log('MODULO_VENTAS: Error generando PDF: ' . $e->getMessage(), 'error');
            }
            wp_die(__('Error generando PDF: ', 'modulo-ventas') . $e->getMessage());
        }
    }

    /**
     * Verificar acceso público a cotización
     */
    private function verificar_acceso_publico($cotizacion_id) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'mv_cotizaciones';
        
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        if (empty($token)) {
            return false;
        }
        
        $cotizacion = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $tabla WHERE id = %d AND token_publico = %s",
            $cotizacion_id,
            $token
        ));
        
        return !empty($cotizacion);
    }

    /**
     * Generar PDF de cotización usando plantillas
     */
    private function generar_pdf_cotizacion($cotizacion_id, $descargar = false, $preview = false) {
        if (isset($this->logger)) {
            $this->logger->log("MODULO_VENTAS: Generando PDF para cotización {$cotizacion_id}");
        }
        
        // Verificar que el sistema de plantillas esté disponible
        if (!isset($this->pdf_templates)) {
            throw new Exception(__('Sistema de plantillas no disponible', 'modulo-ventas'));
        }
        
        // Obtener plantilla activa para cotizaciones
        $plantilla = $this->pdf_templates->obtener_plantilla_activa('cotizacion');
        
        if (!$plantilla) {
            throw new Exception(__('No hay plantilla activa para cotizaciones', 'modulo-ventas'));
        }
        
        // Procesar plantilla con datos reales
        $documento_html = $this->pdf_templates->procesar_plantilla_para_pdf($plantilla->id, $cotizacion_id);
        
        // Verificar si tenemos PDF Handler disponible
        if (!class_exists('Modulo_Ventas_PDF_Handler') || !isset($this->pdf_handler)) {
            // Fallback: mostrar HTML directamente para preview
            if ($preview) {
                header('Content-Type: text/html; charset=utf-8');
                echo $documento_html;
                exit;
            } else {
                throw new Exception(__('Sistema PDF no disponible', 'modulo-ventas'));
            }
        }
        
        // Generar PDF usando TCPDF
        $this->generar_pdf_con_tcpdf($documento_html, $cotizacion_id, $descargar, $preview);
    }

    /**
     * Generar PDF usando TCPDF con el HTML procesado
     */
    private function generar_pdf_con_tcpdf($html_content, $cotizacion_id, $descargar = false, $preview = false) {
        // Cargar TCPDF si no está disponible
        if (!class_exists('TCPDF')) {
            $this->cargar_tcpdf_global();
        }
        
        if (!class_exists('TCPDF')) {
            throw new Exception(__('TCPDF no está disponible', 'modulo-ventas'));
        }
        
        try {
            // Obtener datos de la cotización para el nombre del archivo
            global $wpdb;
            $tabla = $wpdb->prefix . 'mv_cotizaciones';
            $cotizacion = $wpdb->get_row($wpdb->prepare(
                "SELECT numero, fecha_creacion FROM $tabla WHERE id = %d",
                $cotizacion_id
            ));
            
            $nombre_archivo = $cotizacion ? 
                sanitize_file_name('cotizacion_' . $cotizacion->numero . '.pdf') : 
                'cotizacion_' . $cotizacion_id . '.pdf';
            
            // Configurar TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configuración del documento
            $pdf->SetCreator('Módulo de Ventas');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle('Cotización ' . ($cotizacion ? $cotizacion->numero : $cotizacion_id));
            $pdf->SetSubject('Cotización generada automáticamente');
            
            // Configurar página
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            $pdf->SetAutoPageBreak(TRUE, 25);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Desactivar header y footer por defecto
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Agregar página
            $pdf->AddPage();
            
            // Convertir HTML a PDF
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            // Definir modo de salida
            if ($preview) {
                $output_mode = 'I'; // Inline (mostrar en navegador)
            } else if ($descargar) {
                $output_mode = 'D'; // Download (forzar descarga)
            } else {
                $output_mode = 'I'; // Por defecto inline
            }
            
            // Limpiar buffer de salida antes de enviar PDF
            if (ob_get_length()) {
                ob_end_clean();
            }
            
            // Enviar PDF
            $pdf->Output($nombre_archivo, $output_mode);
            
            // Log de éxito
            if (isset($this->logger)) {
                $this->logger->log("MODULO_VENTAS: PDF generado exitosamente para cotización {$cotizacion_id}");
            }
            
            // Terminar ejecución para evitar output adicional
            exit;
            
        } catch (Exception $e) {
            if (isset($this->logger)) {
                $this->logger->log('MODULO_VENTAS: Error en TCPDF: ' . $e->getMessage(), 'error');
            }
            throw new Exception(__('Error generando PDF: ', 'modulo-ventas') . $e->getMessage());
        }
    }

    /**
     * Generar URL para PDF de cotización
     */
    public function generar_url_pdf($cotizacion_id, $accion = 'generar', $incluir_token = false) {
        $args = array(
            'mv_pdf' => $accion,
            'cotizacion_id' => $cotizacion_id
        );
        
        // Incluir token público si se solicita
        if ($incluir_token) {
            $token = $this->generar_token_publico($cotizacion_id);
            if ($token) {
                $args['token'] = $token;
            }
        }
        
        return add_query_arg($args, home_url());
    }

    /**
     * Generar token público para cotización
     */
    private function generar_token_publico($cotizacion_id) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'mv_cotizaciones';
        
        // Verificar si ya tiene token
        $token_existente = $wpdb->get_var($wpdb->prepare(
            "SELECT token_publico FROM $tabla WHERE id = %d",
            $cotizacion_id
        ));
        
        if ($token_existente) {
            return $token_existente;
        }
        
        // Generar nuevo token
        $token = wp_generate_password(32, false);
        
        $resultado = $wpdb->update(
            $tabla,
            array('token_publico' => $token),
            array('id' => $cotizacion_id),
            array('%s'),
            array('%d')
        );
        
        return $resultado !== false ? $token : null;
    }

    /**
     * Obtener instancia del sistema de plantillas
     */
    public function get_pdf_templates() {
        return isset($this->pdf_templates) ? $this->pdf_templates : null;
    }

    /**
     * Modificar htaccess para preview de PDF
     */
    private function crear_htaccess_directorio($directorio) {
        $htaccess_path = $directorio . '/.htaccess';
        
        // Obtener el nombre del directorio
        $dir_name = basename($directorio);
        
        switch ($dir_name) {
            case 'previews':
                // Permitir acceso a archivos HTML de preview
                $contenido = "# Módulo de Ventas - Previews\n";
                $contenido .= "Options -Indexes\n";
                $contenido .= "<Files \"*.html\">\n";
                $contenido .= "    Order allow,deny\n";
                $contenido .= "    Allow from all\n";
                $contenido .= "    Require all granted\n";
                $contenido .= "</Files>\n";
                $contenido .= "<Files \"*.htm\">\n";
                $contenido .= "    Order allow,deny\n";
                $contenido .= "    Allow from all\n";
                $contenido .= "    Require all granted\n";
                $contenido .= "</Files>\n";
                $contenido .= "# Bloquear otros tipos de archivo\n";
                $contenido .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)\$\">\n";
                $contenido .= "    Order deny,allow\n";
                $contenido .= "    Deny from all\n";
                $contenido .= "</FilesMatch>\n";
                break;
                
            case 'pdfs':
                // Permitir acceso a archivos PDF
                $contenido = "# Módulo de Ventas - PDFs\n";
                $contenido .= "Options -Indexes\n";
                $contenido .= "<Files \"*.pdf\">\n";
                $contenido .= "    Order allow,deny\n";
                $contenido .= "    Allow from all\n";
                $contenido .= "    Require all granted\n";
                $contenido .= "</Files>\n";
                $contenido .= "# Bloquear archivos ejecutables\n";
                $contenido .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)\$\">\n";
                $contenido .= "    Order deny,allow\n";
                $contenido .= "    Deny from all\n";
                $contenido .= "</FilesMatch>\n";
                break;
                
            default:
                // Para otros directorios, bloquear todo excepto index.php
                $contenido = "# Módulo de Ventas - Protegido\n";
                $contenido .= "Options -Indexes\n";
                $contenido .= "Order deny,allow\n";
                $contenido .= "Deny from all\n";
                $contenido .= "<Files \"index.php\">\n";
                $contenido .= "    Allow from all\n";
                $contenido .= "</Files>\n";
                break;
        }
        
        // Escribir archivo .htaccess
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, $contenido);
            error_log("MODULO_VENTAS: .htaccess creado para directorio: $dir_name");
        }
    }
}

//Función principal para obtener la instancia del plugin
function modulo_ventas() {
    return Modulo_Ventas::get_instance();
}

// Inicializar el plugin
modulo_ventas();

// Asegurar que los handlers AJAX se carguen correctamente
add_action('init', function() {
    // Inicialización temprana para AJAX
    $plugin_instance = modulo_ventas();
    $plugin_instance->init_ajax_temprano();

    // TAMBIÉN AGREGAR UN HOOK MÁS TEMPRANO PARA TCPDF:
    add_action('wp_loaded', function() {
        // Cargar TCPDF lo más temprano posible para AJAX
        if (wp_doing_ajax() || is_admin()) {
            $tcpdf_loaded = false;
            
            // Método 1: Autoload
            $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload_path) && !$tcpdf_loaded) {
                require_once $autoload_path;
                if (class_exists('TCPDF')) {
                    $tcpdf_loaded = true;
                    error_log('MODULO_VENTAS: TCPDF cargado via autoload');
                }
            }
            
            // Método 2: Carga directa como fallback
            if (!$tcpdf_loaded) {
                $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
                if (file_exists($tcpdf_path)) {
                    require_once $tcpdf_path;
                    if (class_exists('TCPDF')) {
                        $tcpdf_loaded = true;
                        error_log('MODULO_VENTAS: TCPDF cargado directamente');
                    }
                }
            }
            
            if (!$tcpdf_loaded) {
                error_log('MODULO_VENTAS: ERROR - No se pudo cargar TCPDF');
            }
        }
    }, 5); // Prioridad alta

    // Solo cargar AJAX si estamos en una petición AJAX
    if (wp_doing_ajax()) {
        // Verificar que las clases necesarias estén disponibles
        if (!class_exists('Modulo_Ventas_Ajax')) {
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-db.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-logger.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-clientes.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-cotizaciones.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-ajax.php';
            
            // Crear instancia
            new Modulo_Ventas_Ajax();
        }
    }
}, 1); // Prioridad 1 para que se ejecute temprano

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

// Forzar carga de Ajax para peticiones AJAX
if (wp_doing_ajax()) {
    $plugin = Modulo_Ventas::get_instance();
    // Cargar archivos sin verificar dependencias
    if (method_exists($plugin, 'cargar_plugin')) {
        $plugin->cargar_plugin();
    }
}

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

// Función para ejecutar la migración completa
function mv_ejecutar_migracion_completa() {
    global $wpdb;
    
    $tabla_clientes = $wpdb->prefix . 'mv_clientes';
    $errores = array();
    $exitos = array();
    
    // 1. Verificar que la tabla existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$tabla_clientes'") != $tabla_clientes) {
        $errores[] = "La tabla $tabla_clientes no existe";
        return array('errores' => $errores, 'exitos' => $exitos);
    }
    
    // 2. Obtener columnas actuales
    $columnas_actuales = $wpdb->get_col("SHOW COLUMNS FROM $tabla_clientes");
    
    // 3. Agregar columna credito_disponible si NO existe
    if (!in_array('credito_disponible', $columnas_actuales)) {
        $result = $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN credito_disponible DECIMAL(10,2) DEFAULT 0.00 AFTER credito_autorizado");
        if ($result !== false) {
            $exitos[] = "Columna credito_disponible agregada";
        } else {
            $errores[] = "Error al agregar credito_disponible: " . $wpdb->last_error;
        }
    } else {
        $exitos[] = "Columna credito_disponible ya existe";
    }
    
    // 4. Migrar datos de columnas sin sufijo a columnas con _facturacion
    $migraciones = array(
        'ciudad' => 'ciudad_facturacion',
        'region' => 'region_facturacion',
        'codigo_postal' => 'codigo_postal_facturacion'
    );
    
    foreach ($migraciones as $origen => $destino) {
        if (in_array($origen, $columnas_actuales) && in_array($destino, $columnas_actuales)) {
            // Copiar datos
            $result = $wpdb->query("UPDATE $tabla_clientes 
                SET $destino = $origen 
                WHERE $destino IS NULL AND $origen IS NOT NULL");
            
            if ($result !== false) {
                $exitos[] = "Datos copiados de $origen a $destino ($result registros)";
                
                // Eliminar columna origen
                $result2 = $wpdb->query("ALTER TABLE $tabla_clientes DROP COLUMN $origen");
                if ($result2 !== false) {
                    $exitos[] = "Columna $origen eliminada";
                } else {
                    $errores[] = "Error al eliminar $origen: " . $wpdb->last_error;
                }
            }
        }
    }
    
    // 5. Agregar columnas faltantes
    $columnas_necesarias = array(
        'sitio_web' => "ALTER TABLE $tabla_clientes ADD COLUMN sitio_web VARCHAR(255) DEFAULT NULL AFTER email",
        'codigo_postal_facturacion' => "ALTER TABLE $tabla_clientes ADD COLUMN codigo_postal_facturacion VARCHAR(20) DEFAULT NULL AFTER region_facturacion",
        'codigo_postal_envio' => "ALTER TABLE $tabla_clientes ADD COLUMN codigo_postal_envio VARCHAR(20) DEFAULT NULL AFTER region_envio",
        'fecha_actualizacion' => "ALTER TABLE $tabla_clientes ADD COLUMN fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        'modificado_por' => "ALTER TABLE $tabla_clientes ADD COLUMN modificado_por BIGINT(20) UNSIGNED DEFAULT NULL"
    );
    
    // Actualizar lista de columnas
    $columnas_actuales = $wpdb->get_col("SHOW COLUMNS FROM $tabla_clientes");
    
    foreach ($columnas_necesarias as $columna => $sql) {
        if (!in_array($columna, $columnas_actuales)) {
            $result = $wpdb->query($sql);
            if ($result !== false) {
                $exitos[] = "Columna $columna agregada";
            } else {
                $errores[] = "Error al agregar $columna: " . $wpdb->last_error;
            }
        }
    }
    
    // 6. Actualizar versión de base de datos
    update_option('modulo_ventas_db_version', '2.1.0');
    
    return array(
        'errores' => $errores,
        'exitos' => $exitos
    );
}

// Ejecutar migración si se solicita
add_action('admin_init', function() {
    if (isset($_GET['mv_migrate_db']) && $_GET['mv_migrate_db'] === '1' && current_user_can('manage_options')) {
        $resultado = mv_ejecutar_migracion_completa();
        
        echo '<div style="margin: 20px; padding: 20px; background: white; border: 1px solid #ccc;">';
        echo '<h2>Resultado de la migración</h2>';
        
        if (!empty($resultado['exitos'])) {
            echo '<h3 style="color: green;">✓ Cambios exitosos:</h3>';
            echo '<ul>';
            foreach ($resultado['exitos'] as $exito) {
                echo '<li>' . esc_html($exito) . '</li>';
            }
            echo '</ul>';
        }
        
        if (!empty($resultado['errores'])) {
            echo '<h3 style="color: red;">✗ Errores:</h3>';
            echo '<ul>';
            foreach ($resultado['errores'] as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<p><a href="' . admin_url() . '">Volver al admin</a></p>';
        echo '</div>';
        
        wp_die();
    }
});

// Agregar aviso en el admin si la estructura necesita actualización
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $tabla_clientes = $wpdb->prefix . 'mv_clientes';
    $columnas = $wpdb->get_col("SHOW COLUMNS FROM $tabla_clientes");
    
    // Verificar si existe alguna columna problemática (pero NO credito_disponible)
    if (in_array('ciudad', $columnas) || in_array('region', $columnas) || in_array('codigo_postal', $columnas)) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Módulo de Ventas:</strong> La estructura de la base de datos necesita actualización.
                <a href="<?php echo admin_url('?mv_migrate_db=1'); ?>" class="button button-primary">
                    Ejecutar migración
                </a>
            </p>
        </div>
        <?php
    }
});

// Hook para procesar requests de PDF
add_action('template_redirect', function() {
    $plugin = Modulo_Ventas::get_instance();
    if (method_exists($plugin, 'manejar_requests_pdf')) {
        $plugin->manejar_requests_pdf();
    }
}, 5);

// Hook para limpiar previews de plantillas periódicamente
add_action('wp_loaded', function() {
    if (!wp_next_scheduled('mv_limpiar_previews_plantillas')) {
        wp_schedule_event(time(), 'hourly', 'mv_limpiar_previews_plantillas');
    }
});

// Hook para ejecutar la limpieza
add_action('mv_limpiar_previews_plantillas', function() {
    try {
        $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
        if ($pdf_templates && method_exists($pdf_templates, 'limpiar_previews_antiguos')) {
            $eliminados = $pdf_templates->limpiar_previews_antiguos();
            error_log("MODULO_VENTAS: Limpieza automática de previews - {$eliminados} archivos eliminados");
        }
    } catch (Exception $e) {
        error_log("MODULO_VENTAS: Error en limpieza de previews: " . $e->getMessage());
    }
});

// Agregar endpoints personalizados para PDFs
add_action('init', function() {
    add_rewrite_rule(
        '^pdf/cotizacion/([0-9]+)/?$',
        'index.php?mv_pdf=generar&cotizacion_id=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^pdf/cotizacion/([0-9]+)/download/?$',
        'index.php?mv_pdf=download&cotizacion_id=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^pdf/cotizacion/([0-9]+)/preview/?$',
        'index.php?mv_pdf=preview&cotizacion_id=$matches[1]',
        'top'
    );
});

// Registrar query vars personalizadas
add_filter('query_vars', function($vars) {
    $vars[] = 'mv_pdf';
    $vars[] = 'cotizacion_id';
    $vars[] = 'token';
    return $vars;
});

// Hook para flush rewrite rules en activación (agregar al final del hook de activación existente)
register_activation_hook(MODULO_VENTAS_PLUGIN_FILE, function() {
    flush_rewrite_rules();
});

register_deactivation_hook(MODULO_VENTAS_PLUGIN_FILE, function() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook('mv_limpiar_previews_plantillas');
});

/**
 * Hook de activación mínimo para plantillas PDF
 */
register_activation_hook(__FILE__, 'modulo_ventas_activar_pdf_templates');

function modulo_ventas_activar_pdf_templates() {
    // Cargar mini-instalador
    require_once plugin_dir_path(__FILE__) . 'includes/class-modulo-ventas-pdf-installer.php';
    
    // Verificar e instalar si es necesario
    Modulo_Ventas_PDF_Installer::verificar_e_instalar();
    
    error_log('MODULO_VENTAS: Sistema de plantillas PDF verificado/instalado');
}

/**
 * Verificar instalación en admin_init
 */
add_action('admin_init', 'modulo_ventas_verificar_pdf_templates');

function modulo_ventas_verificar_pdf_templates() {
    // Solo ejecutar en admin
    if (!is_admin()) {
        return;
    }
    
    // Solo ejecutar una vez por sesión
    if (get_transient('mv_pdf_check_done')) {
        return;
    }
    
    // Cargar mini-instalador
    require_once plugin_dir_path(__FILE__) . 'includes/class-modulo-ventas-pdf-installer.php';
    
    // Verificar si las tablas existen
    if (!Modulo_Ventas_PDF_Installer::tablas_existen()) {
        Modulo_Ventas_PDF_Installer::verificar_e_instalar();
        
        // Mostrar mensaje de éxito
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Módulo de Ventas:</strong> Sistema de plantillas PDF instalado exitosamente.</p>';
            echo '</div>';
        });
    }
    
    // Marcar como verificado por 1 hora
    set_transient('mv_pdf_check_done', true, HOUR_IN_SECONDS);
}

/**
 * Hook para limpiar previews temporales
 */
add_action('mv_limpiar_preview_temporal', function($filepath) {
    if (file_exists($filepath)) {
        unlink($filepath);
        error_log('MODULO_VENTAS: Preview temporal eliminado: ' . basename($filepath));
    }
});

// ===================================================================
// PÁGINA DE DEBUG TEMPORAL
// ===================================================================

// Agregar al final de modulo-ventas.php para debug:
add_action('wp_ajax_mv_test_template_processing', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h2>Test de Procesamiento de Plantillas</h2>';
    
    try {
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
        
        // Test simple
        $html_test = '<h1>Empresa: {{empresa.nombre}}</h1>
<p>Cliente: {{cliente.nombre}}</p>
<p>RUT Cliente: {{cliente.rut}}</p>
<p>Cotización: {{cotizacion.numero}}</p>
<p>Total: ${{totales.total_formateado}}</p>
<p>Fecha: {{fechas.fecha_cotizacion}}</p>';
        
        echo '<h3>HTML Original:</h3>';
        echo '<pre>' . esc_html($html_test) . '</pre>';
        
        $html_procesado = $processor->procesar_plantilla_preview($html_test, '');
        
        echo '<h3>HTML Procesado:</h3>';
        echo '<pre>' . esc_html($html_procesado) . '</pre>';
        
        echo '<h3>Vista Renderizada:</h3>';
        echo '<div style="border: 1px solid #ccc; padding: 20px; background: white;">';
        echo $html_procesado;
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<p style="color: red;">Error: ' . esc_html($e->getMessage()) . '</p>';
    }
    
    wp_die();
});

// Debug para preview de plantillas
add_action('wp_ajax_mv_debug_preview_plantilla', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos para debug');
    }
    
    echo '<h2>Debug Preview Plantilla Predeterminada</h2>';
    
    try {
        // Verificar que las clases existen
        if (!class_exists('Modulo_Ventas_PDF_Templates')) {
            echo '<p style="color: red;">❌ Clase Modulo_Ventas_PDF_Templates no encontrada</p>';
            wp_die();
        }
        
        echo '<p style="color: green;">✅ Clase Modulo_Ventas_PDF_Templates encontrada</p>';
        
        // Obtener instancia
        $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
        
        if (!$pdf_templates) {
            echo '<p style="color: red;">❌ No se pudo crear instancia de PDF Templates</p>';
            wp_die();
        }
        
        echo '<p style="color: green;">✅ Instancia de PDF Templates creada</p>';
        
        // Verificar processor
        $reflection = new ReflectionClass($pdf_templates);
        $processor_property = $reflection->getProperty('processor');
        $processor_property->setAccessible(true);
        $processor = $processor_property->getValue($pdf_templates);
        
        if (!$processor) {
            echo '<p style="color: orange;">⚠️ Processor no inicializado, intentando crear...</p>';
            
            // Intentar cargar processor manualmente
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
            $processor_property->setValue($pdf_templates, $processor);
            
            echo '<p style="color: green;">✅ Processor creado manualmente</p>';
        } else {
            echo '<p style="color: green;">✅ Processor ya disponible</p>';
        }
        
        // Buscar plantilla predeterminada
        $plantilla = $pdf_templates->obtener_plantilla_activa('cotizacion');
        
        if (!$plantilla) {
            echo '<p style="color: orange;">⚠️ No hay plantilla activa, creando plantilla predeterminada...</p>';
            
            $pdf_templates->crear_plantilla_predeterminada('cotizacion');
            $plantilla = $pdf_templates->obtener_plantilla_activa('cotizacion');
            
            if (!$plantilla) {
                echo '<p style="color: red;">❌ No se pudo crear plantilla predeterminada</p>';
                wp_die();
            }
            
            echo '<p style="color: green;">✅ Plantilla predeterminada creada</p>';
        } else {
            echo '<p style="color: green;">✅ Plantilla activa encontrada</p>';
        }
        
        echo '<h3>Información de la plantilla:</h3>';
        echo '<p><strong>ID:</strong> ' . $plantilla->id . '</p>';
        echo '<p><strong>Nombre:</strong> ' . esc_html($plantilla->nombre) . '</p>';
        echo '<p><strong>Tipo:</strong> ' . esc_html($plantilla->tipo) . '</p>';
        
        // Mostrar fragmento del HTML
        $html_fragment = substr($plantilla->html_content, 0, 300);
        echo '<h3>HTML (primeros 300 caracteres):</h3>';
        echo '<pre>' . esc_html($html_fragment) . '...</pre>';
        
        // Test de procesamiento básico
        echo '<h3>Test de procesamiento de variables:</h3>';
        
        $test_html = '<h1>Empresa: {{empresa.nombre}}</h1><p>Cliente: {{cliente.nombre}}</p><p>Total: ${{totales.total_formateado}}</p>';
        
        echo '<h4>HTML de test:</h4>';
        echo '<pre>' . esc_html($test_html) . '</pre>';
        
        try {
            $html_procesado = $processor->procesar_plantilla_preview($test_html, '');
            
            echo '<h4>HTML procesado:</h4>';
            echo '<pre>' . esc_html($html_procesado) . '</pre>';
            
            echo '<h4>Vista renderizada:</h4>';
            echo '<div style="border: 1px solid #ccc; padding: 15px; background: white; margin: 10px 0;">';
            echo $html_procesado;
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Error en procesamiento: ' . esc_html($e->getMessage()) . '</p>';
        }
        
        // Test del preview completo
        echo '<h3>Test de preview completo:</h3>';
        
        try {
            $preview_url = $pdf_templates->generar_preview_plantilla(
                $plantilla->html_content,
                $plantilla->css_content,
                0
            );
            
            echo '<p style="color: green;">✅ Preview generado exitosamente</p>';
            echo '<p><strong>URL:</strong> <a href="' . esc_url($preview_url) . '" target="_blank">' . esc_html($preview_url) . '</a></p>';
            
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ Error generando preview: ' . esc_html($e->getMessage()) . '</p>';
            echo '<p><strong>Trace:</strong></p>';
            echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Error general: ' . esc_html($e->getMessage()) . '</p>';
        echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
    }
    
    wp_die();
});

// Debug para preview desde editor - SIN NONCE
add_action('wp_ajax_mv_test_editor_preview', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h2>Test Preview - Debug Nonce</h2>';
    
    // Verificar nonce
    $nonce_correcto = wp_create_nonce('mv_pdf_templates');
    echo '<p>Nonce generado: ' . $nonce_correcto . '</p>';
    
    $html_test = '<h1>{{empresa.nombre}}</h1><p>{{cliente.nombre}}</p>';
    
    try {
        // Test directo (sin AJAX)
        $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
        $preview_url = $pdf_templates->generar_preview_plantilla($html_test, '', 0);
        
        echo '<h3>✅ Test directo funciona:</h3>';
        echo '<p><a href="' . esc_url($preview_url) . '" target="_blank">Ver preview con variables</a></p>';
        
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Error: ' . esc_html($e->getMessage()) . '</p>';
    }
    
    // JavaScript test
    echo '<script>
        console.log("=== DEBUG ===");
        if (typeof mvPdfTemplates !== "undefined") {
            console.log("mvPdfTemplates disponible:", mvPdfTemplates);
            
            jQuery.ajax({
                url: mvPdfTemplates.ajaxurl,
                type: "POST", 
                data: {
                    action: "mv_preview_plantilla",
                    nonce: mvPdfTemplates.nonce,
                    html_content: "' . esc_js($html_test) . '",
                    css_content: "",
                    cotizacion_id: 0
                },
                success: function(response) {
                    console.log("✅ AJAX OK:", response);
                },
                error: function(xhr, status, error) {
                    console.log("❌ AJAX Error:", xhr.responseText);
                }
            });
        } else {
            console.log("mvPdfTemplates NO disponible");
        }
    </script>';
    
    wp_die();
});

// Debug para verificar enqueue de scripts
add_action('wp_ajax_mv_debug_enqueue', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Sin permisos');
    }
    
    echo '<h2>Debug Enqueue de Scripts</h2>';
    
    // Verificar hook actual
    global $hook_suffix;
    echo '<p><strong>Hook actual:</strong> ' . ($hook_suffix ?: 'No definido') . '</p>';
    
    // Verificar que la clase de templates existe
    if (class_exists('Modulo_Ventas_PDF_Templates')) {
        echo '<p style="color: green;">✅ Clase PDF Templates existe</p>';
        
        $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
        echo '<p style="color: green;">✅ Instancia creada</p>';
        
        // Verificar que el método enqueue existe
        if (method_exists($pdf_templates, 'enqueue_admin_scripts')) {
            echo '<p style="color: green;">✅ Método enqueue_admin_scripts existe</p>';
        } else {
            echo '<p style="color: red;">❌ Método enqueue_admin_scripts NO existe</p>';
        }
        
    } else {
        echo '<p style="color: red;">❌ Clase PDF Templates NO existe</p>';
    }
    
    // Verificar scripts enqueued
    global $wp_scripts;
    echo '<h3>Scripts Enqueued:</h3>';
    
    if (isset($wp_scripts->registered['mv-pdf-templates'])) {
        echo '<p style="color: green;">✅ Script mv-pdf-templates registrado</p>';
        $script = $wp_scripts->registered['mv-pdf-templates'];
        echo '<p>Archivo: ' . $script->src . '</p>';
        echo '<p>Dependencias: ' . implode(', ', $script->deps) . '</p>';
    } else {
        echo '<p style="color: red;">❌ Script mv-pdf-templates NO registrado</p>';
    }
    
    if (in_array('mv-pdf-templates', $wp_scripts->queue)) {
        echo '<p style="color: green;">✅ Script mv-pdf-templates en cola</p>';
    } else {
        echo '<p style="color: red;">❌ Script mv-pdf-templates NO en cola</p>';
    }
    
    // Verificar archivo JavaScript
    $js_path = MODULO_VENTAS_PLUGIN_URL . 'assets/js/pdf-templates.js';
    $js_file = MODULO_VENTAS_PLUGIN_DIR . 'assets/js/pdf-templates.js';
    
    echo '<h3>Archivo JavaScript:</h3>';
    echo '<p>URL: ' . $js_path . '</p>';
    echo '<p>Ruta: ' . $js_file . '</p>';
    echo '<p>Existe: ' . (file_exists($js_file) ? '✅ SÍ' : '❌ NO') . '</p>';
    
    if (file_exists($js_file)) {
        echo '<p>Tamaño: ' . number_format(filesize($js_file)) . ' bytes</p>';
        echo '<p>Modificado: ' . date('Y-m-d H:i:s', filemtime($js_file)) . '</p>';
    }
    
    // Test de forzar enqueue
    echo '<h3>Test de Forzar Enqueue:</h3>';
    
    // Simular hook de admin
    $_GET['page'] = 'mv-pdf-templates';
    $test_hook = 'modulo-ventas_page_mv-pdf-templates';
    
    if ($pdf_templates && method_exists($pdf_templates, 'enqueue_admin_scripts')) {
        echo '<p>Ejecutando enqueue_admin_scripts con hook: ' . $test_hook . '</p>';
        
        ob_start();
        $pdf_templates->enqueue_admin_scripts($test_hook);
        $output = ob_get_clean();
        
        if ($output) {
            echo '<p>Output del enqueue:</p>';
            echo '<pre>' . esc_html($output) . '</pre>';
        } else {
            echo '<p>Sin output del enqueue</p>';
        }
        
        // Verificar después del enqueue forzado
        if (wp_script_is('mv-pdf-templates', 'registered')) {
            echo '<p style="color: green;">✅ Script registrado después del enqueue forzado</p>';
        }
        
        if (wp_script_is('mv-pdf-templates', 'enqueued')) {
            echo '<p style="color: green;">✅ Script enqueued después del forzado</p>';
        }
    }
    
    // Verificar localización
    echo '<h3>Verificar Localización:</h3>';
    
    if (isset($wp_scripts->registered['mv-pdf-templates'])) {
        $script = $wp_scripts->registered['mv-pdf-templates'];
        if (isset($script->extra['data'])) {
            echo '<p style="color: green;">✅ Datos de localización encontrados</p>';
            echo '<pre>' . esc_html($script->extra['data']) . '</pre>';
        } else {
            echo '<p style="color: red;">❌ Sin datos de localización</p>';
        }
    }
    
    wp_die();
});