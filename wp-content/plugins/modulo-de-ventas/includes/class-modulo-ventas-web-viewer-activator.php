<?php
/**
 * ACTIVADOR CORREGIDO DEL SISTEMA DE VISUALIZACIÓN WEB
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-web-viewer-activator.php
 * 
 * REEMPLAZA EL ARCHIVO EXISTENTE CON ESTE CONTENIDO CORREGIDO
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Web_Viewer_Activator {
    
    /**
     * Activar el sistema de visualización web
     */
    public static function activar() {
        $activator = new self();
        
        echo '<div class="wrap">';
        echo '<h1>🚀 Activando Sistema de Visualización Web</h1>';
        echo '<div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">';
        
        try {
            $activator->ejecutar_activacion();
            echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; color: #155724; margin-top: 20px;">';
            echo '<h3>✅ ¡Sistema Activado Exitosamente!</h3>';
            echo '<p>El sistema de visualización web está ahora activo. Los documentos se mostrarán directamente en el navegador.</p>';
            echo '<p><strong>Próximos pasos:</strong></p>';
            echo '<ul>';
            echo '<li>Ve a la lista de cotizaciones para ver los nuevos botones "Ver Web"</li>';
            echo '<li>Prueba acceder a una URL como: <code>' . home_url('/documentos/cotizacion/1') . '</code></li>';
            echo '<li>Las plantillas ahora se muestran con CSS completo en el navegador</li>';
            echo '</ul>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; color: #721c24;">';
            echo '<h3>❌ Error en la Activación</h3>';
            echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
            echo '<p><strong>Posibles soluciones:</strong></p>';
            echo '<ul>';
            echo '<li>Verifica que todos los archivos del plugin estén presentes</li>';
            echo '<li>Asegúrate de tener permisos de escritura en el directorio del plugin</li>';
            echo '<li>Contacta al desarrollador si el problema persiste</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Ejecutar proceso de activación
     */
    private function ejecutar_activacion() {
        echo '<h3>📋 Proceso de Activación</h3>';
        echo '<ol>';
        
        // Paso 1: Verificar archivos necesarios
        echo '<li><strong>Verificando archivos del sistema...</strong><br>';
        $this->verificar_archivos();
        echo '<span style="color: green;">✅ Archivos verificados</span></li>';
        
        // Paso 2: Crear controlador si no existe
        echo '<li><strong>Verificando controlador de documentos...</strong><br>';
        $this->verificar_controlador();
        echo '<span style="color: green;">✅ Controlador verificado</span></li>';
        
        // Paso 3: Agregar métodos al procesador existente
        echo '<li><strong>Extendiendo procesador de plantillas...</strong><br>';
        $this->extender_procesador();
        echo '<span style="color: green;">✅ Procesador extendido</span></li>';
        
        // Paso 4: Configurar sistema de rutas
        echo '<li><strong>Configurando sistema de rutas...</strong><br>';
        $this->configurar_rutas();
        echo '<span style="color: green;">✅ Rutas configuradas</span></li>';
        
        // Paso 5: Actualizar configuración
        echo '<li><strong>Actualizando configuración...</strong><br>';
        $this->actualizar_configuracion();
        echo '<span style="color: green;">✅ Configuración actualizada</span></li>';
        
        // Paso 6: Flush rewrite rules
        echo '<li><strong>Actualizando reglas de URL...</strong><br>';
        flush_rewrite_rules();
        echo '<span style="color: green;">✅ URLs actualizadas</span></li>';
        
        echo '</ol>';
        
        // Mostrar URLs de ejemplo
        $this->mostrar_urls_ejemplo();
    }
    
    /**
     * Verificar archivos necesarios
     */
    private function verificar_archivos() {
        $archivos_necesarios = array(
            'includes/class-modulo-ventas-pdf-template-processor.php' => 'Procesador de plantillas',
            'admin/class-modulo-ventas-admin.php' => 'Administrador'
        );
        
        $faltantes = array();
        
        foreach ($archivos_necesarios as $archivo => $descripcion) {
            $ruta = MODULO_VENTAS_PLUGIN_DIR . $archivo;
            if (!file_exists($ruta)) {
                $faltantes[] = "{$descripcion} ({$archivo})";
            } else {
                echo '<small style="color: green;">✓ ' . $descripcion . '</small><br>';
            }
        }
        
        if (!empty($faltantes)) {
            throw new Exception('Archivos faltantes: ' . implode(', ', $faltantes));
        }
    }
    
    /**
     * Verificar y crear controlador si es necesario
     */
    private function verificar_controlador() {
        $controller_file = MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-document-viewer.php';
        
        if (file_exists($controller_file)) {
            echo '<small style="color: orange;">⚠️ El controlador ya existe</small><br>';
            return;
        }
        
        // Crear controlador básico
        $controller_code = $this->obtener_codigo_controlador_basico();
        
        if (!file_put_contents($controller_file, $controller_code)) {
            throw new Exception('No se pudo crear el archivo del controlador');
        }
        
        echo '<small style="color: green;">📄 Controlador creado exitosamente</small><br>';
    }
    
    /**
     * Extender procesador existente
     */
    private function extender_procesador() {
        $processor_file = MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        
        if (!file_exists($processor_file)) {
            throw new Exception('Archivo del procesador no encontrado');
        }
        
        $contenido = file_get_contents($processor_file);
        
        // Verificar si ya tiene los métodos web
        if (strpos($contenido, 'procesar_css_para_web') !== false) {
            echo '<small style="color: orange;">⚠️ Los métodos web ya están presentes</small><br>';
            return;
        }
        
        // Agregar métodos básicos antes del último cierre de clase
        $metodos_web = $this->obtener_metodos_web_basicos();
        
        $posicion_cierre = strrpos($contenido, '}');
        if ($posicion_cierre === false) {
            throw new Exception('No se pudo encontrar el cierre de la clase en el procesador');
        }
        
        $contenido_nuevo = substr($contenido, 0, $posicion_cierre) . 
                          "\n" . $metodos_web . "\n" . 
                          substr($contenido, $posicion_cierre);
        
        // Crear backup
        $backup_file = $processor_file . '.backup.' . date('Y-m-d-H-i-s');
        copy($processor_file, $backup_file);
        
        if (!file_put_contents($processor_file, $contenido_nuevo)) {
            throw new Exception('No se pudo actualizar el procesador');
        }
        
        echo '<small style="color: blue;">📝 Backup creado: ' . basename($backup_file) . '</small><br>';
    }
    
    /**
     * Configurar rutas en archivo principal
     */
    private function configurar_rutas() {
        $main_file = MODULO_VENTAS_PLUGIN_DIR . 'modulo-ventas.php';
        $contenido = file_get_contents($main_file);
        
        if (strpos($contenido, 'class-modulo-ventas-document-viewer.php') !== false) {
            echo '<small style="color: orange;">⚠️ El include ya está presente</small><br>';
            return;
        }
        
        // Agregar include al final del archivo
        $include_code = "\n// Sistema de visualización web de documentos\nrequire_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-document-viewer.php';\n";
        
        // Crear backup
        $backup_main = $main_file . '.backup.' . date('Y-m-d-H-i-s');
        copy($main_file, $backup_main);
        
        // Agregar al final
        $contenido_nuevo = rtrim($contenido, "?>\n\r") . $include_code;
        file_put_contents($main_file, $contenido_nuevo);
        
        echo '<small style="color: green;">📝 Include agregado al archivo principal</small><br>';
    }
    
    /**
     * Actualizar configuración
     */
    private function actualizar_configuracion() {
        $opciones = array(
            'mv_web_viewer_enabled' => '1',
            'mv_web_viewer_version' => '1.0.0',
            'mv_web_viewer_activated' => current_time('mysql')
        );
        
        foreach ($opciones as $key => $value) {
            update_option($key, $value);
        }
        
        echo '<small style="color: green;">⚙️ Opciones guardadas</small><br>';
    }
    
    /**
     * Mostrar URLs de ejemplo
     */
    private function mostrar_urls_ejemplo() {
        echo '<h3>🔗 URLs de Ejemplo</h3>';
        echo '<div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;">';
        
        global $wpdb;
        $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        $cotizacion_ejemplo = $wpdb->get_row("SELECT id FROM {$tabla_cotizaciones} ORDER BY id DESC LIMIT 1");
        
        if ($cotizacion_ejemplo) {
            $ejemplo_id = $cotizacion_ejemplo->id;
            $base_url = home_url();
            
            echo '<h4>📋 Cotización #' . $ejemplo_id . '</h4>';
            echo '<ul>';
            echo '<li><strong>Vista normal:</strong><br><code>' . $base_url . '/documentos/cotizacion/' . $ejemplo_id . '</code></li>';
            echo '<li><strong>Vista de impresión:</strong><br><code>' . $base_url . '/documentos/cotizacion/' . $ejemplo_id . '/imprimir</code></li>';
            echo '</ul>';
            
            echo '<p><a href="' . admin_url('admin.php?page=modulo-ventas-cotizaciones') . '" class="button button-primary">📋 Ver Lista de Cotizaciones</a></p>';
        } else {
            echo '<p style="color: orange;">⚠️ No hay cotizaciones disponibles. Crea una cotización para probar el sistema.</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Obtener código básico del controlador
     */
    private function obtener_codigo_controlador_basico() {
        return '<?php
/**
 * CONTROLADOR BÁSICO DE VISUALIZACIÓN WEB
 * Creado automáticamente por el activador
 */

if (!defined("ABSPATH")) {
    exit;
}

class Modulo_Ventas_Document_Viewer {
    
    public function __construct() {
        add_filter("query_vars", array($this, "add_query_vars"));
        add_action("template_redirect", array($this, "handle_document_view"));
        add_action("init", array($this, "add_rewrite_rules"));
        add_action("admin_footer", array($this, "add_view_buttons"));
    }
    
    public function add_query_vars($vars) {
        $vars[] = "mv_doc_type";
        $vars[] = "mv_doc_id";
        $vars[] = "mv_doc_action";
        return $vars;
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule(
            "^documentos/([^/]+)/([0-9]+)/?$",
            "index.php?mv_doc_type=$matches[1]&mv_doc_id=$matches[2]&mv_doc_action=view",
            "top"
        );
        
        add_rewrite_rule(
            "^documentos/([^/]+)/([0-9]+)/imprimir/?$",
            "index.php?mv_doc_type=$matches[1]&mv_doc_id=$matches[2]&mv_doc_action=print",
            "top"
        );
    }
    
    public function handle_document_view() {
        $doc_type = get_query_var("mv_doc_type");
        $doc_id = get_query_var("mv_doc_id");
        $action = get_query_var("mv_doc_action");
        
        if (!$doc_type || !$doc_id || !$action) {
            return;
        }
        
        if ($doc_type === "cotizacion") {
            $this->mostrar_cotizacion($doc_id, $action);
        }
        
        exit;
    }
    
    private function mostrar_cotizacion($id, $action) {
        // Obtener datos de la cotización
        global $modulo_ventas_db;
        $cotizacion = $modulo_ventas_db->obtener_cotizacion($id);
        
        if (!$cotizacion) {
            wp_die("Cotización no encontrada", 404);
        }
        
        // Obtener plantilla
        require_once MODULO_VENTAS_PLUGIN_DIR . "includes/class-modulo-ventas-pdf-template-processor.php";
        $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
        
        // Cargar datos
        $processor->cargar_datos_cotizacion($id);
        
        // Obtener plantilla activa
        global $wpdb;
        $tabla_plantillas = $wpdb->prefix . "mv_pdf_templates";
        $plantilla = $wpdb->get_row("SELECT * FROM {$tabla_plantillas} WHERE tipo_documento = \"cotizacion\" AND activa = 1 LIMIT 1");
        
        if (!$plantilla) {
            wp_die("No hay plantilla configurada para cotizaciones");
        }
        
        // Procesar plantilla
        $html_procesado = $processor->procesar_template($plantilla->html_content);
        $css_procesado = $processor->procesar_css_para_web($plantilla->css_content);
        
        // Renderizar documento
        $this->render_document($html_procesado, $css_procesado, "Cotización " . $cotizacion->folio, $action);
    }
    
    private function render_document($html, $css, $titulo, $modo) {
        header("Content-Type: text/html; charset=utf-8");
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($titulo); ?></title>
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f5f5f5; }
        .document-container { max-width: 210mm; margin: 0 auto; background: white; padding: 20px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .action-bar { background: #fff; padding: 15px; border-bottom: 1px solid #ddd; text-align: center; margin: -20px -20px 20px -20px; }
        .btn { padding: 10px 20px; margin: 0 5px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px; }
        @media print { .action-bar { display: none; } body { background: white; } .document-container { box-shadow: none; margin: 0; } }
        <?php echo $css; ?>
    </style>
</head>
<body>
    <?php if ($modo !== "print"): ?>
    <div class="action-bar">
        <a href="javascript:window.print()" class="btn">🖨️ Imprimir</a>
        <a href="javascript:history.back()" class="btn">← Volver</a>
    </div>
    <?php endif; ?>
    
    <div class="document-container">
        <?php echo $html; ?>
    </div>
    
    <?php if ($modo === "print"): ?>
    <script>window.onload = function() { setTimeout(function() { window.print(); }, 500); }</script>
    <?php endif; ?>
</body>
</html>
        <?php
    }
    
    public function add_view_buttons() {
        $screen = get_current_screen();
        if ($screen && $screen->id === "modulo-ventas_page_modulo-ventas-cotizaciones") {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $(".wp-list-table tbody tr").each(function() {
                    const cotizacionId = $(this).find("input[type=checkbox]").val();
                    if (cotizacionId) {
                        const viewUrl = "<?php echo home_url(); ?>/documentos/cotizacion/" + cotizacionId;
                        const printUrl = viewUrl + "/imprimir";
                        $(this).find(".row-actions").append(
                            " | <a href=\"" + viewUrl + "\" target=\"_blank\" title=\"Ver en navegador\">👁️ Ver Web</a>" +
                            " | <a href=\"" + printUrl + "\" target=\"_blank\" title=\"Vista de impresión\">🖨️ Imprimir</a>"
                        );
                    }
                });
            });
            </script>
            <?php
        }
    }
}

// Inicializar
add_action("init", function() {
    new Modulo_Ventas_Document_Viewer();
}, 15);';
    }
    
    /**
     * Obtener métodos web básicos para el procesador
     */
    private function obtener_metodos_web_basicos() {
        return '
    /**
     * MÉTODOS PARA VISUALIZACIÓN WEB
     * Agregados automáticamente por el activador
     */
    
    public function procesar_css_para_web($css_content) {
        // Limpiar CSS
        $css = preg_replace("/\/\*.*?\*\//s", "", $css_content);
        $css = preg_replace("/\s+/", " ", $css);
        
        // Agregar estilos responsivos
        $css .= "
        @media screen and (max-width: 768px) {
            .document-container { margin: 10px !important; padding: 15px !important; }
            table { font-size: 11px !important; }
            h1 { font-size: 18px !important; }
            h2 { font-size: 16px !important; }
        }
        @media print {
            body { background: white !important; }
            .action-bar { display: none !important; }
            .document-container { margin: 0 !important; box-shadow: none !important; }
        }";
        
        return trim($css);
    }
    
    public function procesar_css_para_impresion($css_content) {
        $css = $this->procesar_css_para_web($css_content);
        
        // Agregar estilos específicos de impresión
        $css .= "
        @media print {
            * { -webkit-print-color-adjust: exact !important; }
            table { border-collapse: collapse !important; }
            th, td { border: 1px solid #333 !important; padding: 4pt !important; }
        }";
        
        return $css;
    }';
    }
}

// Hook para AJAX
add_action("wp_ajax_mv_activate_web_viewer", array("Modulo_Ventas_Web_Viewer_Activator", "activar"));

// Hook para el menú de admin - CORREGIDO
add_action("admin_menu", function() {
    add_submenu_page(
        "modulo-ventas",                     // Padre correcto
        "Activar Vista Web",                 // Título de página
        "🚀 Activar Vista Web",             // Título de menú
        "manage_options",                    // Capacidad
        "mv-activate-web-viewer",            // Slug
        function() {                         // Callback
            if (isset($_GET["activate"]) && $_GET["activate"] === "1") {
                Modulo_Ventas_Web_Viewer_Activator::activar();
            } else {
                echo '<div class="wrap">';
                echo '<h1>🚀 Activar Sistema de Visualización Web</h1>';
                echo '<div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0;">';
                
                echo '<div style="background: #e7f3ff; border: 1px solid #bee5eb; border-radius: 4px; padding: 20px; margin-bottom: 25px;">';
                echo '<h3 style="color: #0c5460; margin-top: 0;">🎯 Sistema de Visualización Web</h3>';
                echo '<p style="color: #0c5460;">Transformar sistema de PDFs limitados a visualización web completa con CSS avanzado, responsive design y impresión optimizada.</p>';
                echo '</div>';
                
                echo '<div style="display: flex; gap: 30px; margin: 25px 0;">';
                
                echo '<div style="flex: 1;">';
                echo '<h4>✅ Activando:</h4>';
                echo '<ul style="line-height: 1.8;">';
                echo '<li>CSS completo sin limitaciones</li>';
                echo '<li>Diseño responsive automático</li>';
                echo '<li>Impresión desde navegador</li>';
                echo '<li>Carga instantánea</li>';
                echo '<li>URLs para compartir</li>';
                echo '</ul>';
                echo '</div>';
                
                echo '<div style="flex: 1;">';
                echo '<h4>🔧 Características:</h4>';
                echo '<ul style="line-height: 1.8;">';
                echo '<li>URLs: <code>/documentos/cotizacion/123</code></li>';
                echo '<li>Tokens de seguridad</li>';
                echo '<li>Vistas especializadas</li>';
                echo '<li>Botones en admin</li>';
                echo '<li>Compatible con plantillas</li>';
                echo '</ul>';
                echo '</div>';
                
                echo '</div>';
                
                // Estado del sistema
                $activated = get_option("mv_web_viewer_enabled", "0");
                
                if ($activated === "1") {
                    echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; padding: 15px;">';
                    echo '<h4 style="color: #0c5460; margin-top: 0;">Estado: ✅ Sistema Ya Activado</h4>';
                    echo '<p style="color: #0c5460; margin-bottom: 10px;">El sistema está funcionando. Ve a la lista de cotizaciones para usar los botones "Ver Web".</p>';
                    echo '<a href="' . admin_url("admin.php?page=modulo-ventas-cotizaciones") . '" class="button button-primary">📋 Ir a Cotizaciones</a>';
                    echo '</div>';
                } else {
                    echo '<div style="text-align: center; margin: 30px 0;">';
                    echo '<a href="?page=mv-activate-web-viewer&activate=1" class="button button-primary button-hero" style="padding: 15px 30px; font-size: 16px;">🚀 Activar Sistema Web</a>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
            }
        }
    );
}, 30); // Prioridad alta para asegurar que se registra después del menú principal