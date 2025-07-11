<?php
/**
 * CLASE PRINCIPAL PARA GESTIÓN DE PLANTILLAS PDF - ACTUALIZADA
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
     * Procesador de plantillas
     */
    private $processor;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
        
        // Cargar el procesador de plantillas
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        $this->processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
        
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
        error_log("PDF Templates - Hook no coincide, retornando");
        return;
    }
    
    // CodeMirror para editor HTML/CSS
    wp_enqueue_code_editor(array('type' => 'text/html'));
    wp_enqueue_code_editor(array('type' => 'text/css'));
    
    // Scripts personalizados - CORRECCIÓN DE LA URL
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
        if (empty($datos['nombre'])) {
            return new WP_Error('nombre_requerido', __('El nombre de la plantilla es requerido', 'modulo-ventas'));
        }
        
        // Generar slug único
        $slug_base = sanitize_title($datos['nombre']);
        $slug = $slug_base;
        $counter = 1;
        
        // Verificar si el slug ya existe (excluyendo la plantilla actual si estamos editando)
        $plantilla_id = isset($datos['id']) ? intval($datos['id']) : 0;
        
        while (true) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $tabla WHERE slug = %s" . ($plantilla_id ? " AND id != %d" : ""),
                $plantilla_id ? array($slug, $plantilla_id) : array($slug)
            ));
            
            if (!$existing) {
                break; // Slug disponible
            }
            
            $slug = $slug_base . '-' . $counter;
            $counter++;
        }
        
        // Preparar datos para insertar/actualizar
        $datos_db = array(
            'nombre' => sanitize_text_field($datos['nombre']),
            'slug' => $slug,
            'tipo' => sanitize_text_field($datos['tipo']),
            'descripcion' => sanitize_textarea_field($datos['descripcion']),
            'html_content' => $datos['html_content'], // Ya viene sanitizado desde wp_unslash
            'css_content' => $datos['css_content'], // Ya viene sanitizado desde wp_unslash
            'configuracion' => is_array($datos['configuracion']) ? json_encode($datos['configuracion']) : '{}',
            'activa' => intval($datos['activa']),
            'fecha_actualizacion' => current_time('mysql')
        );
        
        $datos_format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s');
        
        if ($plantilla_id > 0) {
            // Actualizar plantilla existente
            $resultado = $wpdb->update(
                $tabla,
                $datos_db,
                array('id' => $plantilla_id),
                $datos_format,
                array('%d')
            );
            
            if ($resultado === false) {
                $this->logger->log('Error actualizando plantilla ID ' . $plantilla_id . ': ' . $wpdb->last_error, 'error');
                return new WP_Error('error_actualizacion', __('Error al actualizar la plantilla', 'modulo-ventas') . ': ' . $wpdb->last_error);
            }
            
            $this->logger->log('Plantilla ID ' . $plantilla_id . ' actualizada exitosamente');
            return $plantilla_id;
            
        } else {
            // Crear nueva plantilla
            $datos_db['fecha_creacion'] = current_time('mysql');
            $datos_db['es_predeterminada'] = 0;
            
            $datos_format[] = '%s'; // Para fecha_creacion
            $datos_format[] = '%d'; // Para es_predeterminada
            
            $resultado = $wpdb->insert(
                $tabla,
                $datos_db,
                $datos_format
            );
            
            if ($resultado === false) {
                $this->logger->log('Error creando nueva plantilla: ' . $wpdb->last_error, 'error');
                return new WP_Error('error_creacion', __('Error al crear la plantilla', 'modulo-ventas') . ': ' . $wpdb->last_error);
            }
            
            $nuevo_id = $wpdb->insert_id;
            $this->logger->log('Nueva plantilla creada con ID: ' . $nuevo_id . ' y slug: ' . $slug);
            return $nuevo_id;
        }
    }
    
    /*
     * Obtener variables disponibles para el editor
     *
    public function obtener_variables_disponibles($tipo = 'cotizacion') {
        return $this->processor->obtener_variables_disponibles($tipo);
    }*/
    
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
        $this->logger->log("PDF_TEMPLATES: Generando preview de plantilla");
        
        try {
            // Verificar que el procesador esté disponible
            if (!isset($this->processor) || !$this->processor) {
                $this->logger->log("PDF_TEMPLATES: Processor no disponible, creando...");
                require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
                $this->processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
            }
            
            $this->logger->log("PDF_TEMPLATES: Processor disponible, procesando contenido...");
            
            // Procesar plantilla con datos
            if ($cotizacion_id > 0) {
                $this->logger->log("PDF_TEMPLATES: Usando datos de cotización real: " . $cotizacion_id);
                // Usar datos de cotización real
                $plantilla_temp = (object) array(
                    'html_content' => $html_content,
                    'css_content' => $css_content
                );
                $documento_html = $this->processor->procesar_plantilla($plantilla_temp, $cotizacion_id);
            } else {
                $this->logger->log("PDF_TEMPLATES: Usando datos de prueba");
                // Usar datos de prueba
                $documento_html = $this->processor->procesar_plantilla_preview($html_content, $css_content);
            }
            
            $this->logger->log("PDF_TEMPLATES: Contenido procesado, preparando directorio...");
            
            // Preparar directorio de previews
            $upload_dir = wp_upload_dir();
            $preview_dir = $upload_dir['basedir'] . '/modulo-ventas/previews/';
            
            // Crear directorio si no existe
            if (!file_exists($preview_dir)) {
                wp_mkdir_p($preview_dir);
                
                // Crear .htaccess que BLOQUEA acceso directo
                $htaccess_content = "# Módulo de Ventas - Previews (Protegido)\n";
                $htaccess_content .= "Order deny,allow\n";
                $htaccess_content .= "Deny from all\n";
                $htaccess_content .= "# Solo acceso via AJAX\n";
                
                file_put_contents($preview_dir . '.htaccess', $htaccess_content);
                file_put_contents($preview_dir . 'index.php', '<?php // Silence is golden');
            }
            
            // Generar nombre único para el archivo
            $preview_filename = 'preview_' . md5(uniqid() . time()) . '.html';
            $preview_path = $preview_dir . $preview_filename;
            
            $this->logger->log("PDF_TEMPLATES: Guardando archivo: " . $preview_path);
            
            // Guardar archivo HTML procesado
            if (file_put_contents($preview_path, $documento_html) === false) {
                throw new Exception(__('No se pudo guardar el archivo de preview', 'modulo-ventas'));
            }
            
            // Devolver URL de AJAX
            $preview_nonce = wp_create_nonce('mv_preview_access_' . $preview_filename);
            $preview_url = admin_url('admin-ajax.php') . '?' . http_build_query(array(
                'action' => 'mv_servir_preview',
                'file' => $preview_filename,
                'nonce' => $preview_nonce
            ));
            
            $this->logger->log("PDF_TEMPLATES: Preview generado exitosamente: " . $preview_url);
            
            return $preview_url;
            
        } catch (Exception $e) {
            $this->logger->log("PDF_TEMPLATES: Error generando preview: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Procesar plantilla con datos reales para generar PDF
     */
    public function procesar_plantilla_para_pdf($plantilla_id, $cotizacion_id) {
        $this->logger->log("PDF_TEMPLATES: Procesando plantilla {$plantilla_id} para cotización {$cotizacion_id}");
        
        // Obtener plantilla
        $plantilla = $this->obtener_plantilla($plantilla_id);
        if (!$plantilla) {
            throw new Exception(__('Plantilla no encontrada', 'modulo-ventas'));
        }
        
        // Procesar con datos reales
        return $this->processor->procesar_plantilla($plantilla, $cotizacion_id);
    }
    
    /**
     * Limpiar archivos de preview antiguos (ejecutar periódicamente)
     */
    public function limpiar_previews_antiguos() {
        $upload_dir = wp_upload_dir();
        $preview_dir = $upload_dir['basedir'] . '/modulo-ventas/previews/';
        
        if (!is_dir($preview_dir)) {
            return 0;
        }
        
        $archivos = glob($preview_dir . 'preview_*.html');
        $tiempo_limite = time() - (2 * HOUR_IN_SECONDS); // 2 horas
        $eliminados = 0;
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $tiempo_limite) {
                if (unlink($archivo)) {
                    $eliminados++;
                }
            }
        }
        
        if ($eliminados > 0) {
            $this->logger->log("PDF_TEMPLATES: Limpieza de previews completada - {$eliminados} archivos eliminados");
        }
        
        return $eliminados;
    }
    
    /**
     * Crear plantilla predeterminada si no existe
     */
    public function crear_plantilla_predeterminada($tipo = 'cotizacion') {
        // Verificar si ya existe una plantilla predeterminada
        $existe = $this->obtener_plantilla_activa($tipo);
        if ($existe) {
            return;
        }
        
        $this->logger->log("PDF_TEMPLATES: Creando plantilla predeterminada para tipo: {$tipo}");
        
        // HTML base para cotización
        $html_base = '<!DOCTYPE html>
<div class="documento">
    <div class="header">
        <div class="empresa-info">
            {{logo_empresa}}
            <h1>{{empresa.nombre}}</h1>
            <p>{{empresa.direccion}}</p>
            <p>{{empresa.telefono}} | {{empresa.email}}</p>
            <p>RUT: {{empresa.rut}}</p>
        </div>
        
        <div class="cotizacion-info">
            <h2>COTIZACIÓN</h2>
            <p><strong>N°:</strong> {{cotizacion.numero}}</p>
            <p><strong>Fecha:</strong> {{fechas.fecha_cotizacion}}</p>
            <p><strong>Vencimiento:</strong> {{fechas.fecha_vencimiento_formateada}}</p>
            <p><strong>Estado:</strong> {{cotizacion.estado}}</p>
        </div>
    </div>
    
    <div class="cliente-info">
        <h3>Cliente</h3>
        <p><strong>{{cliente.nombre}}</strong></p>
        <p>RUT: {{cliente.rut}}</p>
        <p>{{cliente.direccion}}</p>
        <p>{{cliente.ciudad}}, {{cliente.region}}</p>
        <p>{{cliente.telefono}} | {{cliente.email}}</p>
    </div>
    
    <div class="productos">
        <h3>Productos y Servicios</h3>
        {{tabla_productos}}
    </div>
    
    <div class="totales-seccion">
        <div class="totales">
            <div class="total-fila">
                <span>Subtotal:</span>
                <span>${{totales.subtotal_formateado}}</span>
            </div>
            <div class="total-fila">
                <span>Descuento ({{totales.descuento_porcentaje}}%):</span>
                <span>-${{totales.descuento_formateado}}</span>
            </div>
            <div class="total-fila">
                <span>IVA (19%):</span>
                <span>${{totales.impuestos_formateado}}</span>
            </div>
            <div class="total-fila total-final">
                <span><strong>TOTAL:</strong></span>
                <span><strong>${{totales.total_formateado}}</strong></span>
            </div>
        </div>
    </div>
    
    <div class="observaciones">
        <h4>Observaciones</h4>
        <p>{{cotizacion.observaciones}}</p>
    </div>
    
    <div class="footer">
        <p>Cotización generada el {{fechas.hoy}} por {{cotizacion.vendedor}}</p>
        <p>{{empresa.sitio_web}}</p>
    </div>
</div>';
        
        // CSS base para cotización
        $css_base = 'body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
    color: #333;
    line-height: 1.4;
    font-size: 12px;
}

.documento {
    max-width: 100%;
    margin: 0 auto;
}

.header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    border-bottom: 2px solid #2c5aa0;
    padding-bottom: 20px;
}

.empresa-info h1 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 24px;
}

.cotizacion-info h2 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 20px;
}

.cliente-info {
    background-color: #f9f9f9;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}

.productos-tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.productos-tabla th {
    background-color: #2c5aa0;
    color: white;
    padding: 10px 8px;
    text-align: left;
    font-size: 11px;
}

.productos-tabla td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
    font-size: 11px;
}

.productos-tabla tr:nth-child(even) {
    background-color: #f9f9f9;
}

.totales-seccion {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 25px;
}

.totales {
    width: 300px;
}

.total-fila {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #ddd;
}

.total-final {
    border-top: 2px solid #2c5aa0;
    font-weight: bold;
    font-size: 14px;
    margin-top: 10px;
    padding-top: 10px;
}

.observaciones {
    background-color: #f9f9f9;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
}

.footer {
    text-align: center;
    color: #666;
    border-top: 1px solid #ddd;
    padding-top: 15px;
    font-size: 10px;
}

.logo-empresa {
    max-height: 60px;
    max-width: 180px;
    margin-bottom: 10px;
}';
        
        // Datos de la plantilla predeterminada
        $datos_plantilla = array(
            'nombre' => __('Plantilla Predeterminada - Cotización', 'modulo-ventas'),
            'tipo' => $tipo,
            'descripcion' => __('Plantilla básica predeterminada para cotizaciones', 'modulo-ventas'),
            'html_content' => $html_base,
            'css_content' => $css_base,
            'configuracion' => array(),
            'activa' => 1
        );
        
        // Guardar plantilla
        $resultado = $this->guardar_plantilla($datos_plantilla);
        
        if (!is_wp_error($resultado)) {
            // Marcar como predeterminada
            global $wpdb;
            $tabla = $wpdb->prefix . 'mv_pdf_templates';
            $wpdb->update(
                $tabla,
                array('es_predeterminada' => 1),
                array('id' => $resultado),
                array('%d'),
                array('%d')
            );
            
            $this->logger->log("PDF_TEMPLATES: Plantilla predeterminada creada con ID: {$resultado}");
        }
        
        return $resultado;
    }

    /**
     * Generar PDF de cotización real
     */
    public function generar_pdf_cotizacion($cotizacion_id) {
        $this->logger->log("PDF_TEMPLATES: Iniciando generación con TCPDF para cotización {$cotizacion_id}");
        
        try {
            // Verificar si tenemos la clase PDF disponible
            if (!class_exists('Modulo_Ventas_PDF')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf.php';
            }
            
            if (!class_exists('Modulo_Ventas_PDF')) {
                $this->logger->log("PDF_TEMPLATES: Clase Modulo_Ventas_PDF no disponible, usando método HTML", 'warning');
                return $this->generar_html_temporal($cotizacion_id);
            }
            
            // Usar el nuevo método que convierte HTML a PDF
            $pdf_generator = new Modulo_Ventas_PDF();
            
            // Verificar si el nuevo método existe
            if (method_exists($pdf_generator, 'generar_pdf_desde_plantilla')) {
                $this->logger->log("PDF_TEMPLATES: Usando método generar_pdf_desde_plantilla");
                $resultado = $pdf_generator->generar_pdf_desde_plantilla($cotizacion_id);
            } else {
                $this->logger->log("PDF_TEMPLATES: Método generar_pdf_desde_plantilla no existe, usando método tradicional");
                $resultado = $pdf_generator->generar_pdf_cotizacion($cotizacion_id);
            }
            
            if (is_wp_error($resultado)) {
                $this->logger->log("PDF_TEMPLATES: Error generando PDF: " . $resultado->get_error_message(), 'error');
                // Fallback a HTML si falla PDF
                return $this->generar_html_temporal($cotizacion_id);
            }
            
            $this->logger->log("PDF_TEMPLATES: PDF generado exitosamente: {$resultado}");
            return $resultado;
            
        } catch (Exception $e) {
            $this->logger->log("PDF_TEMPLATES: Excepción generando PDF: " . $e->getMessage(), 'error');
            // Fallback a HTML si hay excepción
            return $this->generar_html_temporal($cotizacion_id);
        }
    }

    /**
     * Generar HTML temporal (fallback) - NUEVO MÉTODO
     */
    private function generar_html_temporal($cotizacion_id) {
        $this->logger->log("PDF_TEMPLATES: Generando HTML temporal para cotización {$cotizacion_id}");
        
        // Cargar procesador
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
        
        // Obtener plantilla activa para cotizaciones
        $plantilla = $this->obtener_plantilla_activa('cotizacion');
        
        if (!$plantilla) {
            return new WP_Error('no_template', __('No hay plantilla activa para cotizaciones', 'modulo-ventas'));
        }
        
        // Procesar plantilla con datos reales
        $documento_html = $processor->procesar_plantilla($plantilla, $cotizacion_id, 'cotizacion');
        
        // Guardar como HTML temporal
        $html_path = $this->guardar_html_temporal($documento_html, 'cotizacion-' . $cotizacion_id);
        
        if (is_wp_error($html_path)) {
            return $html_path;
        }
        
        return $html_path;
    }

    /**
     * Guardar HTML temporal - NUEVO MÉTODO
     */
    private function guardar_html_temporal($html_content, $filename_base) {
        // Crear directorio temporal si no existe
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Generar nombre único para el archivo
        $timestamp = current_time('Y-m-d_H-i-s');
        $filename = $filename_base . '_' . $timestamp . '.html';
        $filepath = $temp_dir . '/' . $filename;
        
        // Guardar archivo HTML
        $resultado = file_put_contents($filepath, $html_content);
        
        if ($resultado === false) {
            return new WP_Error('save_error', __('Error al guardar archivo HTML', 'modulo-ventas'));
        }
        
        // Devolver URL del archivo
        $file_url = $upload_dir['baseurl'] . '/modulo-ventas/pdfs/' . $filename;
        
        return $file_url;
    }
    
    /**
     * Guardar PDF temporal
     */
    private function guardar_pdf_temporal($html_content, $filename_base) {
        // Crear directorio temporal si no existe
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Generar nombre único para el archivo
        $timestamp = current_time('Y-m-d_H-i-s');
        $filename = $filename_base . '_' . $timestamp . '.html';
        $filepath = $temp_dir . '/' . $filename;
        
        // Guardar archivo HTML (para luego convertir a PDF)
        $resultado = file_put_contents($filepath, $html_content);
        
        if ($resultado === false) {
            return new WP_Error('save_error', __('Error al guardar archivo PDF', 'modulo-ventas'));
        }
        
        // Devolver URL del archivo
        $file_url = $upload_dir['baseurl'] . '/modulo-ventas/pdfs/' . $filename;
        
        return $file_url;
    }
    
    /**
     * Obtener variables disponibles para el procesador
     */
    public function obtener_variables_disponibles($tipo_documento = 'cotizacion') {
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
        
        return $processor->obtener_variables_disponibles($tipo_documento);
    }
}

// Agregar hook para limpiar previews periódicamente
if (!wp_next_scheduled('mv_limpiar_previews_plantillas')) {
    wp_schedule_event(time(), 'hourly', 'mv_limpiar_previews_plantillas');
}

add_action('mv_limpiar_previews_plantillas', function() {
    $templates = Modulo_Ventas_PDF_Templates::get_instance();
    $templates->limpiar_previews_antiguos();
});

// Hook para limpiar archivos temporales de preview
add_action('mv_limpiar_preview_temporal', function($filepath) {
    if (file_exists($filepath)) {
        unlink($filepath);
        error_log('PDF_TEMPLATES: Preview temporal eliminado: ' . $filepath);
    }
});

// Inicializar la clase
Modulo_Ventas_PDF_Templates::get_instance();