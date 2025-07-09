<?php
/**
 * CLASE PRINCIPAL PARA GESTIÓN DE PLANTILLAS PDF
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-pdf-templates.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF_Templates {
    
    /**
     * Instancia singleton
     */
    private static $instance = null;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
        
        // Hooks para admin
        add_action('admin_menu', array($this, 'agregar_menu_admin'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers se manejan en clase separada: Modulo_Ventas_PDF_Templates_Ajax
    }
    
    /**
     * Obtener instancia singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Agregar menú en admin
     */
    public function agregar_menu_admin() {
        // Verificar que el menú padre existe
        $parent_slug = 'modulo-ventas';
        
        // Verificar si el menú padre existe
        global $admin_page_hooks;
        if (!isset($admin_page_hooks[$parent_slug])) {
            // Si no existe, crear como menú principal temporal
            add_menu_page(
                __('Plantillas PDF', 'modulo-ventas'),
                __('Plantillas PDF', 'modulo-ventas'),
                'manage_options',
                'mv-pdf-templates',
                array($this, 'pagina_admin_plantillas'),
                'dashicons-media-document',
                30
            );
        } else {
            // Agregar como submenú
            add_submenu_page(
                $parent_slug,
                __('Plantillas PDF', 'modulo-ventas'),
                __('Plantillas PDF', 'modulo-ventas'),
                'manage_options',
                'mv-pdf-templates',
                array($this, 'pagina_admin_plantillas')
            );
        }
    }
    
    /**
     * Enqueue scripts y estilos para admin
     */
    public function enqueue_admin_scripts($hook) {
        // CORRECCIÓN: Verificar el hook correcto
        if (strpos($hook, 'mv-pdf-templates') === false && 
            strpos($hook, 'plantillas-pdf') === false) {
            return;
        }
        
        // CodeMirror para editor HTML/CSS
        wp_enqueue_code_editor(array('type' => 'text/html'));
        wp_enqueue_code_editor(array('type' => 'text/css'));
        
        // Scripts personalizados
        wp_enqueue_script(
            'mv-pdf-templates',
            MODULO_VENTAS_PLUGIN_URL . 'assets/js/pdf-templates.js',
            array('jquery', 'code-editor'),
            MODULO_VENTAS_VERSION,
            true
        );
        
        // Estilos
        wp_enqueue_style(
            'mv-pdf-templates',
            MODULO_VENTAS_PLUGIN_URL . 'assets/css/pdf-templates.css',
            array(),
            MODULO_VENTAS_VERSION
        );
        
        // Localizar script
        wp_localize_script('mv-pdf-templates', 'mvPdfTemplates', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mv_pdf_templates'),
            'i18n' => array(
                'confirmar_eliminar' => __('¿Está seguro de eliminar esta plantilla?', 'modulo-ventas'),
                'error_general' => __('Ha ocurrido un error. Por favor intente nuevamente.', 'modulo-ventas'),
                'guardando' => __('Guardando...', 'modulo-ventas'),
                'cargando' => __('Cargando...', 'modulo-ventas'),
                'preview' => __('Vista Previa', 'modulo-ventas'),
                'guardar' => __('Guardar', 'modulo-ventas')
            )
        ));
    }
    
    /**
     * Página principal de administración de plantillas
     */
    public function pagina_admin_plantillas() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $plantilla_id = isset($_GET['plantilla_id']) ? intval($_GET['plantilla_id']) : 0;
        
        switch ($action) {
            case 'edit':
            case 'new':
                $this->mostrar_editor_plantilla($plantilla_id);
                break;
            case 'config':
                $this->mostrar_configuracion_plantillas();
                break;
            default:
                $this->mostrar_lista_plantillas();
                break;
        }
    }
    
    /**
     * Mostrar lista de plantillas
     */
    private function mostrar_lista_plantillas() {
        $plantillas = $this->obtener_plantillas();
        
        include MODULO_VENTAS_PLUGIN_DIR . 'admin/views/pdf-templates/lista-plantillas.php';
    }
    
    /**
     * Mostrar editor de plantilla
     */
    private function mostrar_editor_plantilla($plantilla_id = 0) {
        $plantilla = null;
        $variables = $this->obtener_variables_disponibles();
        
        if ($plantilla_id > 0) {
            $plantilla = $this->obtener_plantilla($plantilla_id);
            if (!$plantilla) {
                wp_die(__('Plantilla no encontrada', 'modulo-ventas'));
            }
        }
        
        include MODULO_VENTAS_PLUGIN_DIR . 'admin/views/pdf-templates/editor-plantilla.php';
    }
    
    /**
     * Mostrar configuración de plantillas
     */
    private function mostrar_configuracion_plantillas() {
        $configuracion_actual = $this->obtener_configuracion_plantillas();
        $plantillas_por_tipo = $this->obtener_plantillas_por_tipo();
        
        include MODULO_VENTAS_PLUGIN_DIR . 'admin/views/pdf-templates/configuracion-plantillas.php';
    }
    
    /**
     * Obtener todas las plantillas
     */
    public function obtener_plantillas($tipo = null, $activas_solo = false) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        $where = array('1=1');
        $params = array();
        
        if ($tipo) {
            $where[] = 'tipo = %s';
            $params[] = $tipo;
        }
        
        if ($activas_solo) {
            $where[] = 'activa = 1';
        }
        
        $sql = "SELECT * FROM $tabla WHERE " . implode(' AND ', $where) . " ORDER BY es_predeterminada DESC, nombre ASC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Obtener una plantilla específica
     */
    public function obtener_plantilla($id) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Obtener plantilla activa para un tipo de documento
     */
    public function obtener_plantilla_activa($tipo_documento) {
        global $wpdb;
        
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT p.* FROM $tabla_plantillas p 
             INNER JOIN $tabla_config c ON p.id = c.plantilla_id 
             WHERE c.tipo_documento = %s AND c.activa = 1 AND p.activa = 1",
            $tipo_documento
        ));
        
        // Si no hay plantilla asignada, buscar la predeterminada del tipo
        if (!$result) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tabla_plantillas 
                 WHERE tipo = %s AND es_predeterminada = 1 AND activa = 1 
                 ORDER BY id ASC LIMIT 1",
                $tipo_documento
            ));
        }
        
        return $result;
    }
    
    /**
     * Guardar plantilla
     */
    public function guardar_plantilla($datos) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        // Validar datos requeridos
        $errores = array();
        
        if (empty($datos['nombre'])) {
            $errores[] = __('El nombre de la plantilla es requerido', 'modulo-ventas');
        }
        
        if (empty($datos['html_content'])) {
            $errores[] = __('El contenido HTML es requerido', 'modulo-ventas');
        }
        
        if (!empty($errores)) {
            return new WP_Error('datos_invalidos', implode(', ', $errores));
        }
        
        // Generar slug único
        $slug = $this->generar_slug_unico($datos['nombre'], isset($datos['id']) ? $datos['id'] : 0);
        
        // Preparar datos
        $plantilla_datos = array(
            'nombre' => sanitize_text_field($datos['nombre']),
            'slug' => $slug,
            'tipo' => sanitize_text_field($datos['tipo'] ?: 'cotizacion'),
            'descripcion' => sanitize_textarea_field($datos['descripcion'] ?: ''),
            'html_content' => $datos['html_content'], // No sanitizar HTML del editor
            'css_content' => $datos['css_content'] ?: '',
            'configuracion' => json_encode($datos['configuracion'] ?: array()),
            'variables_usadas' => $this->extraer_variables_usadas($datos['html_content']),
            'activa' => isset($datos['activa']) ? intval($datos['activa']) : 1
        );
        
        if (isset($datos['id']) && $datos['id'] > 0) {
            // Actualizar plantilla existente
            $plantilla_datos['fecha_modificacion'] = current_time('mysql');
            
            $result = $wpdb->update(
                $tabla,
                $plantilla_datos,
                array('id' => intval($datos['id'])),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            
            if ($result === false) {
                return new WP_Error('error_actualizacion', $wpdb->last_error);
            }
            
            return intval($datos['id']);
            
        } else {
            // Crear nueva plantilla
            $plantilla_datos['creado_por'] = get_current_user_id();
            $plantilla_datos['fecha_creacion'] = current_time('mysql');
            
            $result = $wpdb->insert($tabla, $plantilla_datos);
            
            if ($result === false) {
                return new WP_Error('error_insercion', $wpdb->last_error);
            }
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Generar slug único
     */
    private function generar_slug_unico($nombre, $excluir_id = 0) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        $slug_base = sanitize_title($nombre);
        $slug = $slug_base;
        $contador = 1;
        
        while (true) {
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $tabla WHERE slug = %s AND id != %d",
                $slug,
                $excluir_id
            ));
            
            if ($existe == 0) {
                break;
            }
            
            $slug = $slug_base . '-' . $contador;
            $contador++;
        }
        
        return $slug;
    }
    
    /**
     * Extraer variables usadas en el HTML
     */
    private function extraer_variables_usadas($html_content) {
        $variables = array();
        
        // Buscar variables con sintaxis {{variable}}
        preg_match_all('/\{\{([^}]+)\}\}/', $html_content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $variable) {
                // Limpiar espacios y caracteres especiales
                $variable = trim($variable);
                
                // Remover helpers de Handlebars (como #if, #each)
                if (!preg_match('/^(#|\/|else|\^)/', $variable)) {
                    $variables[] = $variable;
                }
            }
        }
        
        return implode(',', array_unique($variables));
    }
    
    /**
     * Obtener variables disponibles
     */
    public function obtener_variables_disponibles($tipo_documento = null) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_template_variables';
        $where = 'activa = 1';
        $params = array();
        
        if ($tipo_documento) {
            $where .= ' AND (disponible_en LIKE %s OR disponible_en = "")';
            $params[] = '%' . $tipo_documento . '%';
        }
        
        $sql = "SELECT * FROM $tabla WHERE $where ORDER BY categoria ASC, orden ASC, variable ASC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $variables = $wpdb->get_results($sql);
        
        // Agrupar por categoría
        $variables_agrupadas = array();
        foreach ($variables as $variable) {
            $variables_agrupadas[$variable->categoria][] = $variable;
        }
        
        return $variables_agrupadas;
    }
    
    /**
     * Obtener configuración actual de plantillas
     */
    public function obtener_configuracion_plantillas() {
        global $wpdb;
        
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        
        return $wpdb->get_results(
            "SELECT c.*, p.nombre as plantilla_nombre 
            FROM $tabla_config c 
            LEFT JOIN $tabla_plantillas p ON c.plantilla_id = p.id 
            WHERE c.activa = 1 
            ORDER BY c.tipo_documento"
        );
    }
    
    /**
     * Obtener plantillas agrupadas por tipo
     */
    public function obtener_plantillas_por_tipo() {
        $plantillas = $this->obtener_plantillas(null, true);
        
        $por_tipo = array();
        foreach ($plantillas as $plantilla) {
            $por_tipo[$plantilla->tipo][] = $plantilla;
        }
        
        return $por_tipo;
    }
    
    // ======================================
    // MÉTODOS PARA PROCESAMIENTO DE PLANTILLAS
    // ======================================
    
    /**
     * Generar preview de plantilla con datos de prueba
     */
    public function generar_preview_plantilla($html_content, $css_content, $cotizacion_id = 0) {
        // TODO: Implementar en el siguiente paso (motor de templates)
        throw new Exception(__('Preview de plantillas será implementado en el siguiente paso', 'modulo-ventas'));
    }

    /**
     * Método auxiliar para mostrar tabla de plantillas
     */
    private function mostrar_tabla_plantillas($plantillas, $tipo) {
        // El código de la función ya está incluido en la vista
    }
}

// Inicializar la clase
Modulo_Ventas_PDF_Templates::get_instance();