<?php
/**
 * HANDLER AJAX PARA PLANTILLAS PDF - ACTUALIZADO
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
            'mv_cambiar_estado_plantilla' => 'cambiar_estado_plantilla',
            'mv_cargar_plantilla_predeterminada' => 'cargar_plantilla_predeterminada',
            // Nuevos
            'mv_guardar_config_plantillas' => 'guardar_config_plantillas',
            'mv_restablecer_config_plantillas' => 'restablecer_config_plantillas',
            'mv_obtener_cotizaciones_preview' => 'obtener_cotizaciones_preview',
            'mv_validar_plantilla' => 'validar_plantilla',
            'mv_exportar_plantilla' => 'exportar_plantilla',
            'mv_importar_plantilla' => 'importar_plantilla',
            'mv_obtener_estadisticas_plantillas' => 'obtener_estadisticas_plantillas',
            'mv_servir_preview' => 'servir_preview',

            'mv_debug_variables_plantilla' => 'debug_variables_plantilla'
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
            // Generar preview usando el sistema de plantillas
            $preview_url = $this->templates_manager->generar_preview_plantilla(
                $html_content, 
                $css_content, 
                $cotizacion_id
            );
            
            wp_send_json_success(array(
                'preview_url' => $preview_url,
                'message' => __('Preview generado exitosamente', 'modulo-ventas')
            ));
            
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
        
        // Desactivar plantilla actual
        $wpdb->update(
            $tabla_config,
            array('activa' => 0),
            array('tipo_documento' => $tipo_documento),
            array('%d'),
            array('%s')
        );
        
        // Activar nueva plantilla
        $resultado = $wpdb->replace(
            $tabla_config,
            array(
                'tipo_documento' => $tipo_documento,
                'plantilla_id' => $plantilla_id,
                'activa' => 1,
                'fecha_asignacion' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%s')
        );
        
        if ($resultado === false) {
            wp_send_json_error(array('message' => __('Error al asignar plantilla', 'modulo-ventas')));
        }
        
        wp_send_json_success(array(
            'message' => __('Plantilla asignada exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * AJAX: Obtener variables disponibles por tipo de documento
     */
    public function obtener_variables() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $tipo_documento = sanitize_text_field($_POST['tipo_documento'] ?: 'cotizacion');
        
        error_log('TEMPLATES_AJAX: Obteniendo variables para tipo: ' . $tipo_documento);
        
        try {
            // Cargar el procesador de plantillas
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
            
            // Obtener variables del procesador
            $variables = $processor->obtener_variables_disponibles($tipo_documento);
            
            wp_send_json_success($variables);
            
        } catch (Exception $e) {
            error_log('TEMPLATES_AJAX: Error obteniendo variables: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Error al cargar variables', 'modulo-ventas')));
        }
    }
    
    /**
     * AJAX: Obtener plantillas por tipo
     */
    public function obtener_plantillas_tipo() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $tipo = sanitize_text_field($_POST['tipo'] ?: '');
        $activas_solo = isset($_POST['activas_solo']) && $_POST['activas_solo'];
        
        $plantillas = $this->templates_manager->obtener_plantillas($tipo, $activas_solo);
        
        wp_send_json_success(array('plantillas' => $plantillas));
    }
    
    /**
     * AJAX: Cambiar estado de plantilla (activa/inactiva)
     */
    public function cambiar_estado_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantilla_id = intval($_POST['plantilla_id']);
        $activa = isset($_POST['activa']) && $_POST['activa'];
        
        if (!$plantilla_id) {
            wp_send_json_error(array('message' => __('ID de plantilla inválido', 'modulo-ventas')));
        }
        
        // Verificar que la plantilla existe
        $plantilla = $this->templates_manager->obtener_plantilla($plantilla_id);
        if (!$plantilla) {
            wp_send_json_error(array('message' => __('Plantilla no encontrada', 'modulo-ventas')));
        }
        
        // Actualizar estado
        global $wpdb;
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        $resultado = $wpdb->update(
            $tabla,
            array(
                'activa' => $activa ? 1 : 0,
                'fecha_actualizacion' => current_time('mysql')
            ),
            array('id' => $plantilla_id),
            array('%d', '%s'),
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
    
    /**
     * AJAX: Cargar plantilla predeterminada para un tipo
     */
    public function cargar_plantilla_predeterminada() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $tipo = sanitize_text_field($_POST['tipo'] ?: 'cotizacion');
        
        try {
            // Crear plantilla predeterminada si no existe
            $resultado = $this->templates_manager->crear_plantilla_predeterminada($tipo);
            
            if (is_wp_error($resultado)) {
                wp_send_json_error(array('message' => $resultado->get_error_message()));
            }
            
            // Obtener la plantilla recién creada
            $plantilla = $this->templates_manager->obtener_plantilla($resultado);
            
            wp_send_json_success(array(
                'message' => __('Plantilla predeterminada cargada', 'modulo-ventas'),
                'plantilla' => $plantilla
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Obtener lista de cotizaciones para preview
     */
    public function obtener_cotizaciones_preview() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        global $wpdb;
        $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        $tabla_clientes = $wpdb->prefix . 'mv_clientes';
        
        // Obtener últimas 10 cotizaciones para preview
        $cotizaciones = $wpdb->get_results(
            "SELECT c.id, c.numero, c.fecha_creacion, c.total, cl.nombre as cliente_nombre
            FROM $tabla_cotizaciones c
            LEFT JOIN $tabla_clientes cl ON c.cliente_id = cl.id
            ORDER BY c.fecha_creacion DESC
            LIMIT 10"
        );
        
        wp_send_json_success(array('cotizaciones' => $cotizaciones));
    }

    /**
     * AJAX: Servir archivo de preview de forma segura
     */
    public function servir_preview() {
        // No necesita check_ajax_referer aquí porque usamos nonce personalizado
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Sin permisos para acceder al preview', 'modulo-ventas'), 403);
        }
        
        $filename = sanitize_file_name($_GET['file'] ?? '');
        $nonce = sanitize_text_field($_GET['nonce'] ?? '');
        
        if (empty($filename) || empty($nonce)) {
            wp_die(__('Parámetros inválidos', 'modulo-ventas'), 400);
        }
        
        // Verificar nonce específico del archivo
        if (!wp_verify_nonce($nonce, 'mv_preview_access_' . $filename)) {
            error_log('TEMPLATES_AJAX: Nonce inválido para preview: ' . $filename);
            wp_die(__('Acceso no autorizado al preview', 'modulo-ventas'), 403);
        }
        
        // Verificar que el archivo existe y es seguro
        $upload_dir = wp_upload_dir();
        $preview_path = $upload_dir['basedir'] . '/modulo-ventas/previews/' . $filename;
        
        if (!file_exists($preview_path)) {
            error_log('TEMPLATES_AJAX: Archivo de preview no encontrado: ' . $preview_path);
            wp_die(__('Preview no encontrado', 'modulo-ventas'), 404);
        }
        
        // Verificar que es un archivo HTML válido
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'html') {
            error_log('TEMPLATES_AJAX: Intento de acceso a archivo no HTML: ' . $filename);
            wp_die(__('Tipo de archivo no permitido', 'modulo-ventas'), 400);
        }
        
        // Verificar que el archivo no es muy antiguo (más de 4 horas)
        $file_time = filemtime($preview_path);
        if ($file_time < (time() - 14400)) { // 4 horas = 14400 segundos
            error_log('TEMPLATES_AJAX: Preview expirado, eliminando: ' . $filename);
            unlink($preview_path);
            wp_die(__('El preview ha expirado', 'modulo-ventas'), 410);
        }
        
        error_log('TEMPLATES_AJAX: Sirviendo preview: ' . $filename);
        
        // Servir el archivo con headers apropiados
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow');
        
        // Leer y mostrar el contenido
        readfile($preview_path);
        
        // Programar eliminación del archivo después de servirlo
        wp_schedule_single_event(time() + 3600, 'mv_limpiar_preview_temporal', array($preview_path));
        
        exit;
    }
    
    /**
     * AJAX: Validar plantilla (verificar sintaxis y variables)
     */
    public function validar_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $html_content = wp_unslash($_POST['html_content'] ?: '');
        $css_content = wp_unslash($_POST['css_content'] ?: '');
        
        $errores = array();
        $advertencias = array();
        
        // Validar HTML básico
        if (empty($html_content)) {
            $errores[] = __('El contenido HTML no puede estar vacío', 'modulo-ventas');
        }
        
        // Buscar variables no reconocidas
        $variables_conocidas = $this->obtener_lista_variables_conocidas();
        preg_match_all('/\{\{([^}]+)\}\}/', $html_content . ' ' . $css_content, $matches);
        
        foreach ($matches[1] as $variable) {
            $variable = trim($variable);
            if (!in_array($variable, $variables_conocidas)) {
                $advertencias[] = sprintf(__('Variable no reconocida: %s', 'modulo-ventas'), $variable);
            }
        }
        
        // Verificar CSS básico
        if (!empty($css_content)) {
            $css_lines = explode("\n", $css_content);
            $open_braces = 0;
            
            foreach ($css_lines as $line_num => $line) {
                $open_braces += substr_count($line, '{');
                $open_braces -= substr_count($line, '}');
                
                if ($open_braces < 0) {
                    $errores[] = sprintf(__('Error de sintaxis CSS en línea %d', 'modulo-ventas'), $line_num + 1);
                    break;
                }
            }
            
            if ($open_braces > 0) {
                $errores[] = __('CSS incompleto: llaves sin cerrar', 'modulo-ventas');
            }
        }
        
        // Verificar estructura HTML básica
        if (!empty($html_content)) {
            // Verificar que no hay etiquetas malformadas
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            
            if (!$dom->loadHTML('<!DOCTYPE html><html><body>' . $html_content . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                $errors = libxml_get_errors();
                foreach ($errors as $error) {
                    if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                        $errores[] = sprintf(__('Error HTML: %s', 'modulo-ventas'), trim($error->message));
                    }
                }
            }
            
            libxml_clear_errors();
            libxml_use_internal_errors(false);
        }
        
        wp_send_json_success(array(
            'valida' => empty($errores),
            'errores' => $errores,
            'advertencias' => $advertencias
        ));
    }
    
    /**
     * Obtener lista de variables conocidas para validación
     */
    private function obtener_lista_variables_conocidas() {
        $variables_grupos = $this->templates_manager->obtener_variables_disponibles();
        $variables_planas = array();
        
        foreach ($variables_grupos as $grupo => $variables) {
            foreach ($variables as $var) {
                $variables_planas[] = $var['variable'];
            }
        }
        
        // Agregar variables especiales
        $variables_especiales = array(
            'tabla_productos',
            'logo_empresa',
            'fecha_actual',
            'hora_actual'
        );
        
        return array_merge($variables_planas, $variables_especiales);
    }

    /**
     * AJAX: Guardar configuración de plantillas
     */
    public function guardar_config_plantillas() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantillas = isset($_POST['plantillas']) ? $_POST['plantillas'] : array();
        
        global $wpdb;
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        
        $exitos = 0;
        $errores = array();
        
        foreach ($plantillas as $tipo => $plantilla_id) {
            $tipo = sanitize_text_field($tipo);
            $plantilla_id = intval($plantilla_id);
            
            if (empty($plantilla_id)) {
                // Desactivar configuración existente
                $wpdb->update(
                    $tabla_config,
                    array('activa' => 0),
                    array('tipo_documento' => $tipo),
                    array('%d'),
                    array('%s')
                );
                continue;
            }
            
            // Desactivar configuración anterior
            $wpdb->update(
                $tabla_config,
                array('activa' => 0),
                array('tipo_documento' => $tipo),
                array('%d'),
                array('%s')
            );
            
            // Insertar nueva configuración
            $resultado = $wpdb->replace(
                $tabla_config,
                array(
                    'tipo_documento' => $tipo,
                    'plantilla_id' => $plantilla_id,
                    'activa' => 1,
                    'fecha_asignacion' => current_time('mysql')
                ),
                array('%s', '%d', '%d', '%s')
            );
            
            if ($resultado !== false) {
                $exitos++;
            } else {
                $errores[] = sprintf(__('Error configurando %s', 'modulo-ventas'), $tipo);
            }
        }
        
        if ($exitos > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('Configuración guardada exitosamente. %d tipos configurados.', 'modulo-ventas'), $exitos)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No se pudo guardar la configuración', 'modulo-ventas'),
                'errores' => $errores
            ));
        }
    }
    
    /**
     * AJAX: Restablecer configuración a plantillas predeterminadas
     */
    public function restablecer_config_plantillas() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        global $wpdb;
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        
        try {
            // Desactivar toda la configuración actual
            $wpdb->update(
                $tabla_config,
                array('activa' => 0),
                array('activa' => 1),
                array('%d'),
                array('%d')
            );
            
            // Obtener plantillas predeterminadas
            $plantillas_predeterminadas = $wpdb->get_results(
                "SELECT id, tipo FROM $tabla_plantillas WHERE es_predeterminada = 1 AND activa = 1"
            );
            
            $configuradas = 0;
            
            foreach ($plantillas_predeterminadas as $plantilla) {
                $resultado = $wpdb->replace(
                    $tabla_config,
                    array(
                        'tipo_documento' => $plantilla->tipo,
                        'plantilla_id' => $plantilla->id,
                        'activa' => 1,
                        'fecha_asignacion' => current_time('mysql')
                    ),
                    array('%s', '%d', '%d', '%s')
                );
                
                if ($resultado !== false) {
                    $configuradas++;
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('Configuración restablecida. %d plantillas predeterminadas configuradas.', 'modulo-ventas'), $configuradas)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error restableciendo configuración: ', 'modulo-ventas') . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Exportar plantilla
     */
    public function exportar_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $plantilla_id = intval($_POST['plantilla_id']);
        $plantilla = $this->templates_manager->obtener_plantilla($plantilla_id);
        
        if (!$plantilla) {
            wp_send_json_error(array('message' => __('Plantilla no encontrada', 'modulo-ventas')));
        }
        
        // Preparar datos para exportar
        $export_data = array(
            'version' => '1.0',
            'fecha_exportacion' => current_time('mysql'),
            'plantilla' => array(
                'nombre' => $plantilla->nombre,
                'tipo' => $plantilla->tipo,
                'descripcion' => $plantilla->descripcion,
                'html_content' => $plantilla->html_content,
                'css_content' => $plantilla->css_content,
                'configuracion' => json_decode($plantilla->configuracion, true)
            )
        );
        
        // Generar nombre de archivo
        $nombre_archivo = sanitize_file_name('plantilla_' . $plantilla->nombre . '_' . date('Y-m-d') . '.json');
        
        // Configurar headers para descarga
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        header('Content-Length: ' . strlen(json_encode($export_data, JSON_PRETTY_PRINT)));
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * AJAX: Importar plantilla
     */
    public function importar_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        if (!isset($_FILES['archivo_plantilla']) || $_FILES['archivo_plantilla']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Error subiendo archivo', 'modulo-ventas')));
        }
        
        $archivo = $_FILES['archivo_plantilla'];
        
        // Verificar tipo de archivo
        if ($archivo['type'] !== 'application/json' && !str_ends_with($archivo['name'], '.json')) {
            wp_send_json_error(array('message' => __('Solo se permiten archivos JSON', 'modulo-ventas')));
        }
        
        // Leer contenido del archivo
        $contenido = file_get_contents($archivo['tmp_name']);
        $datos = json_decode($contenido, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Archivo JSON inválido', 'modulo-ventas')));
        }
        
        // Validar estructura del archivo
        if (!isset($datos['plantilla']) || !isset($datos['plantilla']['nombre'])) {
            wp_send_json_error(array('message' => __('Estructura de archivo inválida', 'modulo-ventas')));
        }
        
        $plantilla_data = $datos['plantilla'];
        
        // Preparar datos para importar
        $datos_importacion = array(
            'nombre' => sanitize_text_field($plantilla_data['nombre']) . ' (Importada)',
            'tipo' => sanitize_text_field($plantilla_data['tipo'] ?: 'cotizacion'),
            'descripcion' => sanitize_textarea_field($plantilla_data['descripcion'] ?: ''),
            'html_content' => wp_unslash($plantilla_data['html_content'] ?: ''),
            'css_content' => wp_unslash($plantilla_data['css_content'] ?: ''),
            'configuracion' => $plantilla_data['configuracion'] ?: array(),
            'activa' => 0 // Importar como inactiva por seguridad
        );
        
        // Guardar plantilla importada
        $resultado = $this->templates_manager->guardar_plantilla($datos_importacion);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Plantilla importada exitosamente', 'modulo-ventas'),
            'plantilla_id' => $resultado
        ));
    }
    
    /**
     * AJAX: Obtener estadísticas de uso de plantillas
     */
    public function obtener_estadisticas_plantillas() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        global $wpdb;
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        
        // Estadísticas básicas
        $total_plantillas = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_plantillas");
        $plantillas_activas = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_plantillas WHERE activa = 1");
        $tipos_configurados = $wpdb->get_var("SELECT COUNT(DISTINCT tipo_documento) FROM $tabla_config WHERE activa = 1");
        
        // Plantillas por tipo
        $por_tipo = $wpdb->get_results(
            "SELECT tipo, COUNT(*) as cantidad 
            FROM $tabla_plantillas 
            GROUP BY tipo 
            ORDER BY cantidad DESC"
        );
        
        // Plantillas más recientes
        $recientes = $wpdb->get_results(
            "SELECT nombre, fecha_actualizacion, fecha_creacion 
            FROM $tabla_plantillas 
            ORDER BY COALESCE(fecha_actualizacion, fecha_creacion) DESC 
            LIMIT 5"
        );
        
        wp_send_json_success(array(
            'total_plantillas' => $total_plantillas,
            'plantillas_activas' => $plantillas_activas,
            'tipos_configurados' => $tipos_configurados,
            'por_tipo' => $por_tipo,
            'recientes' => $recientes
        ));
    }

    /**
     * AJAX: Debug de variables de plantilla
     */
    public function debug_variables_plantilla() {
        check_ajax_referer('mv_pdf_templates', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        try {
            // Crear instancia del procesador
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
            
            // Obtener datos de prueba
            $datos_prueba = $processor->debug_datos_prueba();
            
            // Ejemplo de procesamiento
            $html_test = '<h1>{{empresa.nombre}}</h1><p>Cliente: {{cliente.nombre}}</p><p>Total: ${{totales.total_formateado}}</p>';
            $html_procesado = $processor->procesar_plantilla_preview($html_test, '');
            
            wp_send_json_success(array(
                'datos_prueba' => $datos_prueba,
                'html_original' => $html_test,
                'html_procesado' => $html_procesado,
                'variables_disponibles' => $processor->obtener_variables_disponibles()
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}