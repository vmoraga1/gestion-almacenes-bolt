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
        add_action('plugins_loaded', array($this, 'verificar_dependencias'), 1);
        
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
            error_log('Clase Ajax instanciada: ' . get_class($this->ajax));
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

// Hook temprano para inicializar AJAX - AGREGAR ANTES del hook de actualizaciones
/*add_action('plugins_loaded', function() {
    if (is_admin()) {
        // Cargar archivos necesarios
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-db.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-logger.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-clientes.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-cotizaciones.php';
        require_once MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-ajax.php';
        
        // Instanciar la clase Ajax
        new Modulo_Ventas_Ajax();
    }
}, 5); DESCOMENTAR SI CREAR_CLIENTE_RAPIDO NO FUNCIONA */

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