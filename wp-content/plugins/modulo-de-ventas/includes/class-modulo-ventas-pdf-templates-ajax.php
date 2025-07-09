<?php
/**
 * HANDLER AJAX PARA PLANTILLAS PDF
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-pdf-templates-ajax.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF_Templates_Ajax {
    
    /**
     * Instancia del gestor de plantillas
     */
    private $templates_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->templates_manager = Modulo_Ventas_PDF_Templates::get_instance();
        
        // Registrar handlers AJAX
        $this->registrar_ajax_handlers();
    }
    
    /**
     * Registrar todos los handlers AJAX de plantillas
     */
    private function registrar_ajax_handlers() {
        $ajax_actions = array(
            'mv_guardar_plantilla' => 'guardar_plantilla',
            'mv_obtener_plantilla' => 'obtener_plantilla',
            'mv_eliminar_plantilla' => 'eliminar_plantilla',
            'mv_duplicar_plantilla' => 'duplicar_plantilla',
            'mv_preview_plantilla' => 'preview_plantilla',
            'mv_asignar_plantilla' => 'asignar_plantilla',
            'mv_obtener_variables' => 'obtener_variables',
            'mv_obtener_plantillas_tipo' => 'obtener_plantillas_tipo',
            'mv_cambiar_estado_plantilla' => 'cambiar_estado_plantilla'
        );
        
        foreach ($ajax_actions as $action => $method) {
            add_action('wp_ajax_' . $action, array($this, $method));
        }
    }
    
    /**
     * AJAX: Guardar plantilla
     */
    public function guardar_plantilla() {
        error_log('TEMPLATES_AJAX: Guardando plantilla...');
        
        // Verificación de seguridad
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        // Preparar datos
        $datos = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'nombre' => sanitize_text_field($_POST['nombre'] ?: ''),
            'tipo' => sanitize_text_field($_POST['tipo'] ?: 'cotizacion'),
            'descripcion' => sanitize_textarea_field($_POST['descripcion'] ?: ''),
            'html_content' => wp_unslash($_POST['html_content'] ?: ''),
            'css_content' => wp_unslash($_POST['css_content'] ?: ''),
            'configuracion' => isset($_POST['configuracion']) ? $_POST['configuracion'] : array(),
            'activa' => isset($_POST['activa']) ? 1 : 0
        );
        
        error_log('TEMPLATES_AJAX: Datos recibidos - Nombre: ' . $datos['nombre'] . ', Tipo: ' . $datos['tipo']);
        
        // Delegar a la clase de lógica de negocio
        $resultado = $this->templates_manager->guardar_plantilla($datos);
        
        if (is_wp_error($resultado)) {
            error_log('TEMPLATES_AJAX: Error guardando: ' . $resultado->get_error_message());
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        error_log('TEMPLATES_AJAX: Plantilla guardada con ID: ' . $resultado);
        
        wp_send_json_success(array(
            'message' => __('Plantilla guardada exitosamente', 'modulo-ventas'),
            'plantilla_id' => $resultado
        ));
    }
    
    /**
     * AJAX: Obtener plantilla
     */
    public function obtener_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantilla_id = intval($_POST['plantilla_id']);
        
        error_log('TEMPLATES_AJAX: Obteniendo plantilla ID: ' . $plantilla_id);
        
        // Delegar a la clase de lógica de negocio
        $plantilla = $this->templates_manager->obtener_plantilla($plantilla_id);
        
        if (!$plantilla) {
            wp_send_json_error(array('message' => __('Plantilla no encontrada', 'modulo-ventas')));
        }
        
        wp_send_json_success($plantilla);
    }
    
    /**
     * AJAX: Eliminar plantilla
     */
    public function eliminar_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantilla_id = intval($_POST['plantilla_id']);
        
        if (!$plantilla_id) {
            wp_send_json_error(array('message' => __('ID de plantilla inválido', 'modulo-ventas')));
        }
        
        // Verificar que no sea una plantilla predeterminada
        $plantilla = $this->templates_manager->obtener_plantilla($plantilla_id);
        if ($plantilla && $plantilla->es_predeterminada) {
            wp_send_json_error(array('message' => __('No se puede eliminar una plantilla predeterminada', 'modulo-ventas')));
        }
        
        // Eliminar plantilla
        global $wpdb;
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        $resultado = $wpdb->delete($tabla, array('id' => $plantilla_id), array('%d'));
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => __('Error al eliminar la plantilla', 'modulo-ventas')));
        }
        
        wp_send_json_success(array('message' => __('Plantilla eliminada exitosamente', 'modulo-ventas')));
    }
    
    /**
     * AJAX: Duplicar plantilla
     */
    public function duplicar_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantilla_id = intval($_POST['plantilla_id']);
        $plantilla_original = $this->templates_manager->obtener_plantilla($plantilla_id);
        
        if (!$plantilla_original) {
            wp_send_json_error(array('message' => __('Plantilla original no encontrada', 'modulo-ventas')));
        }
        
        // Crear datos para la plantilla duplicada
        $datos_duplicada = array(
            'nombre' => $plantilla_original->nombre . ' (Copia)',
            'tipo' => $plantilla_original->tipo,
            'descripcion' => $plantilla_original->descripcion,
            'html_content' => $plantilla_original->html_content,
            'css_content' => $plantilla_original->css_content,
            'configuracion' => json_decode($plantilla_original->configuracion, true) ?: array(),
            'activa' => 0 // La copia inicia inactiva
        );
        
        $resultado = $this->templates_manager->guardar_plantilla($datos_duplicada);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Plantilla duplicada exitosamente', 'modulo-ventas'),
            'plantilla_id' => $resultado
        ));
    }
    
    /**
     * AJAX: Preview de plantilla
     */
    public function preview_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $html_content = wp_unslash($_POST['html_content'] ?: '');
        $css_content = wp_unslash($_POST['css_content'] ?: '');
        $cotizacion_id = intval($_POST['cotizacion_id'] ?: 0);
        
        error_log('TEMPLATES_AJAX: Generando preview para cotización: ' . $cotizacion_id);
        
        try {
            // Delegar al procesador de plantillas (será implementado en el siguiente paso)
            $preview_url = $this->templates_manager->generar_preview_plantilla(
                $html_content, 
                $css_content, 
                $cotizacion_id
            );
            
            wp_send_json_success(array('preview_url' => $preview_url));
            
        } catch (Exception $e) {
            error_log('TEMPLATES_AJAX: Error en preview: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Asignar plantilla activa a un tipo de documento
     */
    public function asignar_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $tipo_documento = sanitize_text_field($_POST['tipo_documento']);
        $plantilla_id = intval($_POST['plantilla_id']);
        
        if (!$tipo_documento || !$plantilla_id) {
            wp_send_json_error(array('message' => __('Parámetros inválidos', 'modulo-ventas')));
        }
        
        global $wpdb;
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        
        // Desactivar configuración anterior
        $wpdb->update(
            $tabla_config,
            array('activa' => 0),
            array('tipo_documento' => $tipo_documento),
            array('%d'),
            array('%s')
        );
        
        // Insertar o activar nueva configuración
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tabla_config WHERE tipo_documento = %s AND plantilla_id = %d",
            $tipo_documento,
            $plantilla_id
        ));
        
        if ($existe) {
            // Activar existente
            $resultado = $wpdb->update(
                $tabla_config,
                array('activa' => 1),
                array('id' => $existe),
                array('%d'),
                array('%d')
            );
        } else {
            // Crear nueva configuración
            $resultado = $wpdb->insert(
                $tabla_config,
                array(
                    'tipo_documento' => $tipo_documento,
                    'plantilla_id' => $plantilla_id,
                    'activa' => 1,
                    'asignado_por' => get_current_user_id()
                ),
                array('%s', '%d', '%d', '%d')
            );
        }
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => __('Error al asignar plantilla', 'modulo-ventas')));
        }
        
        wp_send_json_success(array('message' => __('Plantilla asignada exitosamente', 'modulo-ventas')));
    }
    
    /**
     * AJAX: Obtener variables disponibles
     */
    public function obtener_variables() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        $tipo_documento = sanitize_text_field($_POST['tipo_documento'] ?: '');
        
        // Delegar a la clase de lógica de negocio
        $variables = $this->templates_manager->obtener_variables_disponibles($tipo_documento);
        
        wp_send_json_success($variables);
    }
    
    /**
     * AJAX: Obtener plantillas por tipo
     */
    public function obtener_plantillas_tipo() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        $tipo = sanitize_text_field($_POST['tipo'] ?: '');
        $activas_solo = isset($_POST['activas_solo']) ? (bool)$_POST['activas_solo'] : false;
        
        $plantillas = $this->templates_manager->obtener_plantillas($tipo, $activas_solo);
        
        wp_send_json_success($plantillas);
    }
    
    /**
     * AJAX: Cambiar estado activo/inactivo de plantilla
     */
    public function cambiar_estado_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantilla_id = intval($_POST['plantilla_id']);
        $activa = isset($_POST['activa']) ? (bool)$_POST['activa'] : false;
        
        if (!$plantilla_id) {
            wp_send_json_error(array('message' => __('ID de plantilla inválido', 'modulo-ventas')));
        }
        
        global $wpdb;
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        $resultado = $wpdb->update(
            $tabla,
            array('activa' => $activa ? 1 : 0),
            array('id' => $plantilla_id),
            array('%d'),
            array('%d')
        );
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => __('Error al cambiar estado de plantilla', 'modulo-ventas')));
        }
        
        $mensaje = $activa ? 
            __('Plantilla activada exitosamente', 'modulo-ventas') : 
            __('Plantilla desactivada exitosamente', 'modulo-ventas');
        
        wp_send_json_success(array('message' => $mensaje));
    }
}

// Inicializar la clase AJAX
new Modulo_Ventas_PDF_Templates_Ajax();