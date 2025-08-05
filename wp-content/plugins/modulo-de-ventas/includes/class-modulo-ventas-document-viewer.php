<?php
/**
 * CONTROLADOR DE VISUALIZACIÓN WEB DE DOCUMENTOS
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-document-viewer.php
 * 
 * Sistema para mostrar documentos (cotizaciones, facturas, etc.) directamente en el navegador
 * usando plantillas HTML con CSS completo, sin conversión a PDF.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Document_Viewer {
    
    private $db;
    private $template_processor;
    
    public function __construct() {
        // Inicializar la base de datos
        global $modulo_ventas_db;
        
        if (!$modulo_ventas_db || !is_object($modulo_ventas_db)) {
            if (!class_exists('Modulo_Ventas_DB')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-db.php';
            }
            $modulo_ventas_db = new Modulo_Ventas_DB();
        }
        
        $this->db = $modulo_ventas_db;
        
        // Cargar procesador de plantillas
        require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
        $this->template_processor = new Modulo_Ventas_PDF_Template_Processor();
        
        $this->init_hooks();
        
        //error_log("DOCUMENT_VIEWER: Inicializado correctamente - DB: " . (is_object($this->db) ? 'OK' : 'ERROR'));
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hook para query_vars personalizadas
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Hook para template_redirect
        add_action('template_redirect', array($this, 'handle_document_view'));
        
        // Hook para rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // AJAX para vista previa desde admin
        add_action('wp_ajax_mv_preview_document', array($this, 'ajax_preview_document'));
        add_action('wp_ajax_nopriv_mv_view_document', array($this, 'ajax_public_view_document'));
        
        // Agregar botones en admin
        add_action('admin_init', array($this, 'add_admin_view_buttons'));
    }
    
    /**
     * Agregar variables de consulta personalizadas
     */
    public function add_query_vars($vars) {
        $vars[] = 'mv_doc_type';
        $vars[] = 'mv_doc_id';
        $vars[] = 'mv_doc_action';
        $vars[] = 'mv_doc_template';
        return $vars;
    }
    
    /**
     * Agregar reglas de reescritura
     */
    public function add_rewrite_rules() {
        // Rutas principales para visualización
        add_rewrite_rule(
            '^documentos/([^/]+)/([0-9]+)/?$',
            'index.php?mv_doc_type=$matches[1]&mv_doc_id=$matches[2]&mv_doc_action=view',
            'top'
        );
        
        // Ruta para vista previa con plantilla específica
        add_rewrite_rule(
            '^documentos/([^/]+)/([0-9]+)/preview/([0-9]+)/?$',
            'index.php?mv_doc_type=$matches[1]&mv_doc_id=$matches[2]&mv_doc_action=preview&mv_doc_template=$matches[3]',
            'top'
        );
        
        // Ruta para impresión optimizada
        add_rewrite_rule(
            '^documentos/([^/]+)/([0-9]+)/imprimir/?$',
            'index.php?mv_doc_type=$matches[1]&mv_doc_id=$matches[2]&mv_doc_action=print',
            'top'
        );
    }
    
    /**
     * Manejar visualización de documentos
     */
    public function handle_document_view() {
        $doc_type = get_query_var('mv_doc_type');
        $doc_id = get_query_var('mv_doc_id');
        $action = get_query_var('mv_doc_action');
        
        if (!$doc_type || !$doc_id || !$action) {
            return;
        }
        
        // Validar tipo de documento
        $tipos_validos = array('cotizacion', 'factura', 'boleta', 'orden_compra', 'guia_despacho');
        if (!in_array($doc_type, $tipos_validos)) {
            wp_die(__('Tipo de documento no válido', 'modulo-ventas'), 400);
        }
        
        // Verificar permisos
        if (!$this->verificar_permisos_documento($doc_type, $doc_id, $action)) {
            wp_die(__('No tiene permisos para ver este documento', 'modulo-ventas'), 403);
        }
        
        // Procesar según la acción
        switch ($action) {
            case 'view':
                $this->render_document_view($doc_type, $doc_id);
                break;
            case 'preview':
                $template_id = get_query_var('mv_doc_template');
                $this->render_document_preview($doc_type, $doc_id, $template_id);
                break;
            case 'print':
                $this->render_document_print($doc_type, $doc_id);
                break;
            default:
                wp_die(__('Acción no válida', 'modulo-ventas'), 400);
        }
        
        exit;
    }
    
    /**
     * Verificar permisos para ver documento
     */
    private function verificar_permisos_documento($tipo, $id, $action) {
        // Para preview desde admin - requiere login
        if ($action === 'preview') {
            return current_user_can('edit_cotizaciones');
        }
        
        // Para view público - verificar token o sesión
        if ($action === 'view') {
            // Verificar si el usuario tiene permisos o si existe token público válido
            $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
            
            if (current_user_can('view_cotizaciones')) {
                return true;
            }
            
            if ($token) {
                return $this->verificar_token_publico($tipo, $id, $token);
            }
            
            // Para desarrollo, permitir acceso temporal
            if (WP_DEBUG) {
                return true;
            }
            
            return false;
        }
        
        // Para print - mismo que view
        return $this->verificar_permisos_documento($tipo, $id, 'view');
    }
    
    /**
     * Verificar token público para acceso sin login
     */
    private function verificar_token_publico($tipo, $id, $token) {
        $expected_token = $this->generar_token_documento($tipo, $id);
        return hash_equals($expected_token, $token);
    }
    
    /**
     * Generar token público para documento
     */
    public function generar_token_documento($tipo, $id) {
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt';
        return hash('sha256', $tipo . $id . $salt . date('Y-m-d'));
    }
    
    /**
     * Renderizar vista principal del documento
     */
    private function render_document_view($tipo, $id) {
        //error_log("=== DOCUMENT_VIEWER DEBUG ===");
        //error_log("STEP 1: render_document_view - Tipo: $tipo, ID: $id");
        
        // Obtener datos del documento
        $documento = $this->obtener_datos_documento($tipo, $id);
        if (!$documento) {
            wp_die(__('Documento no encontrado', 'modulo-ventas'), 404);
        }
        
        //error_log("STEP 2: Documento obtenido: " . (isset($documento->folio) ? $documento->folio : $documento->id));
        
        // Obtener plantilla activa para este tipo
        $plantilla = $this->obtener_plantilla_activa($tipo);
        if (!$plantilla) {
            wp_die(__('No hay plantilla configurada para este tipo de documento', 'modulo-ventas'), 500);
        }
        
        //error_log("STEP 3: Plantilla obtenida: " . $plantilla->nombre . " (ID: " . $plantilla->id . ")");
        //error_log("STEP 3a: HTML length: " . strlen($plantilla->html_content));
        //error_log("STEP 3b: CSS length: " . strlen($plantilla->css_content));
        
        // Verificar procesador
        if (!$this->template_processor || !is_object($this->template_processor)) {
            //error_log("STEP 4: Creando procesador...");
            if (!class_exists('Modulo_Ventas_PDF_Template_Processor')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            }
            $this->template_processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
        } else {
            //error_log("STEP 4: Procesador ya existe");
        }
        
        // CRÍTICO: Cargar datos de la cotización en el procesador
        //error_log("STEP 5: Cargando datos en procesador...");
        $datos_cargados = $this->template_processor->cargar_datos_cotizacion($id);
        //error_log("STEP 5a: Datos cargados resultado: " . ($datos_cargados ? 'SUCCESS' : 'FAILED'));
        
        // Verificar que los datos se cargaron
        if (method_exists($this->template_processor, 'tiene_datos')) {
            $tiene_datos = $this->template_processor->tiene_datos();
            //error_log("STEP 5b: tiene_datos() = " . ($tiene_datos ? 'TRUE' : 'FALSE'));
        } else {
            //error_log("STEP 5b: método tiene_datos() NO EXISTE");
            // Verificar directamente si hay datos
            if (property_exists($this->template_processor, 'cotizacion_data')) {
                $tiene_datos = !empty($this->template_processor->cotizacion_data);
                //error_log("STEP 5c: cotizacion_data existe y está " . ($tiene_datos ? 'LLENO' : 'VACÍO'));
            } else {
                //error_log("STEP 5c: propiedad cotizacion_data NO EXISTE");
            }
        }
        
        // Procesar plantilla
        //error_log("STEP 6: Procesando HTML...");
        $html_antes = substr($plantilla->html_content, 0, 200) . "...";
        //error_log("STEP 6a: HTML original (primeros 200 chars): " . $html_antes);
        
        $html_procesado = $this->template_processor->procesar_template($plantilla->html_content);
        
        $html_despues = substr($html_procesado, 0, 200) . "...";
        //error_log("STEP 6b: HTML procesado (primeros 200 chars): " . $html_despues);
        
        // Verificar si las variables se procesaron
        $variables_antes = substr_count($plantilla->html_content, '{{');
        $variables_despues = substr_count($html_procesado, '{{');
        //error_log("STEP 6c: Variables antes: $variables_antes, después: $variables_despues");
        
        if ($variables_antes > 0 && $variables_despues >= $variables_antes) {
            //error_log("STEP 6d: ❌ LAS VARIABLES NO SE PROCESARON!");
            // Mostrar las primeras variables sin procesar
            preg_match_all('/\{\{([^}]+)\}\}/', $html_procesado, $matches);
            if (!empty($matches[1])) {
                //error_log("STEP 6e: Variables sin procesar: " . implode(', ', array_slice($matches[1], 0, 10)));
            }
        } else {
            //error_log("STEP 6d: ✅ Variables se procesaron correctamente");
        }
        
        //error_log("STEP 7: Procesando CSS...");
        $css_procesado = $this->template_processor->procesar_css_para_web($plantilla->css_content);
        //error_log("STEP 7a: CSS procesado length: " . strlen($css_procesado));
        
        // Debug final antes de renderizar
        //error_log("STEP 8: Preparando renderizado final...");
        //error_log("STEP 8a: HTML final length: " . strlen($html_procesado));
        //error_log("STEP 8b: CSS final length: " . strlen($css_procesado));
        
        // Renderizar documento completo
        $this->render_complete_document($html_procesado, $css_procesado, $documento, 'view');
        
        //error_log("STEP 9: ✅ Renderizado completado");
    }
    
    /**
     * Renderizar vista previa con plantilla específica
     */
    private function render_document_preview($tipo, $id, $template_id) {
        // Obtener datos del documento
        $documento = $this->obtener_datos_documento($tipo, $id);
        if (!$documento) {
            wp_die(__('Documento no encontrado', 'modulo-ventas'), 404);
        }
        
        // Obtener plantilla específica
        $plantilla = $this->obtener_plantilla_por_id($template_id);
        if (!$plantilla) {
            wp_die(__('Plantilla no encontrada', 'modulo-ventas'), 404);
        }
        
        // Procesar plantilla
        $html_procesado = $this->template_processor->procesar_plantilla(
            $plantilla->html_content,
            $documento,
            $tipo
        );
        
        $css_procesado = $this->template_processor->procesar_css_para_web(
            $plantilla->css_content
        );
        
        // Renderizar con controles de preview
        $this->render_complete_document($html_procesado, $css_procesado, $documento, 'preview');
    }
    
    /**
     * Renderizar vista optimizada para impresión
     */
    private function render_document_print($tipo, $id) {
        // Obtener datos del documento
        $documento = $this->obtener_datos_documento($tipo, $id);
        if (!$documento) {
            wp_die(__('Documento no encontrado', 'modulo-ventas'), 404);
        }
        
        // Obtener plantilla activa
        $plantilla = $this->obtener_plantilla_activa($tipo);
        if (!$plantilla) {
            wp_die(__('No hay plantilla configurada', 'modulo-ventas'), 500);
        }
        
        // Procesar plantilla con optimizaciones para impresión
        $html_procesado = $this->template_processor->procesar_plantilla(
            $plantilla->html_content,
            $documento,
            $tipo
        );
        
        $css_procesado = $this->template_processor->procesar_css_para_impresion(
            $plantilla->css_content
        );
        
        // Renderizar optimizado para impresión
        $this->render_complete_document($html_procesado, $css_procesado, $documento, 'print');
    }
    
    /**
 * Renderizar documento HTML completo
 */
private function render_complete_document($html, $css, $documento, $modo = 'view') {
    // Configurar headers
    header('Content-Type: text/html; charset=utf-8');
    
    // Título dinámico según tipo de documento
    $titulo = $this->generar_titulo_documento($documento, $modo);
    
    // NUEVO: Detectar si es solicitud de impresión con Paged.js
    $is_paged_print = isset($_GET['paged_print']) && $_GET['paged_print'] == '1';
    
    if ($is_paged_print) {
        $this->render_paged_print_document($html, $css, $documento, $titulo);
        return;
    }
    
    // CORREGIR: Limpiar CSS de posibles etiquetas anidadas
    $css_limpio = $css;
    
    // Remover etiquetas <style> si están presentes en el CSS
    if (strpos($css_limpio, '<style>') !== false) {
        $css_limpio = preg_replace('/<style[^>]*>/', '', $css_limpio);
        $css_limpio = str_replace('</style>', '', $css_limpio);
        //error_log("DOCUMENT_VIEWER: CSS contenía etiquetas <style> anidadas - removidas");
    }
    
    // Escapar el CSS para evitar problemas
    $css_limpio = trim($css_limpio);
    
    //error_log("DOCUMENT_VIEWER: Renderizando documento - Modo: $modo, CSS: " . strlen($css_limpio) . " chars");
    
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($titulo); ?></title>
    
    <!-- CSS base del sistema -->
    <style type="text/css">
        /* Reset y estilos base */
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        /* Contenedor principal */
        .mv-document-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        /* Modo vista normal */
        .mv-mode-view .mv-document-container {
            margin: 20px auto;
            border-radius: 8px;
            overflow: hidden;
            min-height: 297mm;
        }
        
        /* Modo impresión */
        .mv-mode-print .mv-document-container {
            margin: 0;
            box-shadow: none;
            border-radius: 0;
            min-height: auto;
        }
        
        /* Barra de acciones */
        .mv-action-bar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .mv-mode-print .mv-action-bar {
            display: none;
        }
        
        .mv-action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .mv-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .mv-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .mv-btn-primary {
            background: #007cba;
            color: white;
            border-color: #007cba;
        }
        
        .mv-btn-primary:hover {
            background: #005a87;
            border-color: #005a87;
        }
        
        /* Contenido del documento */
        .mv-document-content {
            padding: 20px;
        }
        
        .mv-mode-print .mv-document-content {
            padding: 0;
        }
        
        /* Estilos de impresión */
        @media print {
            body {
                background: white;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .mv-action-bar {
                display: none !important;
            }
            
            .mv-document-container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                max-width: none;
                width: 100%;
                min-height: auto !important;
            }
            
            .mv-document-content {
                padding: 0;
            }
            
            /* Mejorar control de saltos de página */
            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid;
            }
            
            table {
                page-break-inside: auto;
            }
            
            /* Evitar huérfanos y viudas */
            p {
                orphans: 2;
                widows: 2;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
    </style>
    
    <?php if (!empty($css_limpio)): ?>
    <!-- CSS específico de la plantilla -->
    <style type="text/css">
        /* CSS de la plantilla */
        <?php echo $css_limpio; ?>
    </style>
    <?php endif; ?>
    
    <?php if ($modo === 'preview'): ?>
    <!-- Estilos adicionales para preview -->
    <style type="text/css">
        .mv-preview-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ff6b35;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 200;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        @media print {
            .mv-preview-badge {
                display: none;
            }
        }
    </style>
    <?php endif; ?>

</head>
<body class="mv-mode-<?php echo esc_attr($modo); ?>">
    
    <?php if ($modo === 'preview'): ?>
    <div class="mv-preview-badge">
        📋 VISTA PREVIA
    </div>
    <?php endif; ?>
    
    <?php if ($modo !== 'print'): ?>
    <!-- Barra de acciones -->
    <div class="mv-action-bar">
        <div class="mv-document-info">
            <strong><?php echo esc_html($titulo); ?></strong>
            <span style="color: #666; margin-left: 10px;">
                Generado el <?php echo date('d/m/Y H:i'); ?>
            </span>
        </div>
        
        <div class="mv-action-buttons">
            <button onclick="printWithPaging()" class="mv-btn mv-btn-primary">
                🖨️ Imprimir
            </button>
            
            <button onclick="printWithPaging()" class="mv-btn">
                💾 Guardar PDF
            </button>
            
            <?php if ($modo === 'preview'): ?>
            <a href="javascript:history.back()" class="mv-btn">
                ← Volver
            </a>
            <?php endif; ?>
            
            <button onclick="copyLink()" class="mv-btn" id="copy-link-btn">
                🔗 Copiar enlace
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Contenedor del documento -->
    <div class="mv-document-container">
        <div class="mv-document-content">
            <?php 
            // CORREGIR: Asegurar que el HTML no contenga CSS visible
            $html_limpio = $html;
            
            // Si el HTML contiene CSS visible (texto plano), removerlo
            if (strpos($html_limpio, 'img { max-width: 100%') !== false || 
                strpos($html_limpio, '@media screen') !== false) {
                
                // Buscar donde termina el CSS y empieza el HTML real
                $posicion_css_end = strrpos($html_limpio, '}');
                if ($posicion_css_end !== false) {
                    // Buscar la primera etiqueta HTML después del CSS
                    $buscar_desde = $posicion_css_end;
                    $primer_tag = strpos($html_limpio, '<', $buscar_desde);
                    if ($primer_tag !== false) {
                        $html_limpio = substr($html_limpio, $primer_tag);
                        //error_log("DOCUMENT_VIEWER: CSS removido del HTML visible");
                    }
                }
            }
            
            echo $html_limpio; 
            ?>
        </div>
    </div>
    
    <!-- JavaScript para funcionalidades -->
    <script>
        // Función de impresión con Paged.js completamente aislada
        function printWithPaging() {
            console.log('Abriendo ventana de impresión con Paged.js...');
            
            // Crear URL para versión de impresión con Paged.js
            const currentUrl = window.location.href;
            const printUrl = currentUrl + (currentUrl.includes('?') ? '&' : '?') + 'paged_print=1';
            
            // Abrir en nueva ventana
            const printWindow = window.open(printUrl, '_blank', 'width=210mm,height=297mm,scrollbars=yes');
            
            if (!printWindow) {
                alert('Por favor, permite las ventanas emergentes para la función de impresión.');
                return;
            }
            
            // Dar foco a la nueva ventana
            printWindow.focus();
        }
        
        // Copiar enlace al portapapeles
        function copyLink() {
            const url = window.location.href;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    showCopySuccess();
                });
            } else {
                // Fallback para navegadores sin soporte
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCopySuccess();
            }
        }
        
        function showCopySuccess() {
            const btn = document.getElementById('copy-link-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '✅ ¡Copiado!';
            btn.style.background = '#28a745';
            btn.style.color = 'white';
            
            setTimeout(function() {
                btn.innerHTML = originalText;
                btn.style.background = '';
                btn.style.color = '';
            }, 2000);
        }
        
        // Auto-focus para impresión si viene de enlace directo a print
        <?php if ($modo === 'print'): ?>
        window.addEventListener('load', function() {
            // Pequeño delay para asegurar que todo esté cargado
            setTimeout(function() {
                window.print();
            }, 500);
        });
        <?php endif; ?>
        
        // Prevenir zoom accidental en vista previa
        <?php if ($modo === 'preview'): ?>
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === '+' || e.key === '-' || e.key === '0')) {
                e.preventDefault();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html><?php
}

/**
 * Renderizar documento con Paged.js - MÉTODO COMPLETAMENTE AISLADO V2
 */
private function render_paged_print_document($html, $css, $documento, $titulo) {
    // Limpiar HTML completamente - SIN PROCESAR CSS
    $html_limpio = $html;
    if (strpos($html_limpio, 'img { max-width: 100%') !== false || 
        strpos($html_limpio, '@media screen') !== false) {
        $posicion_css_end = strrpos($html_limpio, '}');
        if ($posicion_css_end !== false) {
            $buscar_desde = $posicion_css_end;
            $primer_tag = strpos($html_limpio, '<', $buscar_desde);
            if ($primer_tag !== false) {
                $html_limpio = substr($html_limpio, $primer_tag);
            }
        }
    }
    
    // Limpiar CSS - SOLO remover etiquetas <style> anidadas
    $css_limpio = $css;
    if (strpos($css_limpio, '<style>') !== false) {
        $css_limpio = preg_replace('/<style[^>]*>/', '', $css_limpio);
        $css_limpio = str_replace('</style>', '', $css_limpio);
    }
    $css_limpio = trim($css_limpio);
    
    // Obtener nombre de empresa para footer
    $empresa_nombre = 'Documento';
    if (isset($documento->empresa_nombre)) {
        $empresa_nombre = $documento->empresa_nombre;
    } elseif (isset($documento->empresa) && isset($documento->empresa->nombre)) {
        $empresa_nombre = $documento->empresa->nombre;
    }
    
    header('Content-Type: text/html; charset=utf-8');
    
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Impresión - <?php echo esc_html($titulo); ?></title>
    
    <!-- Paged.js ANTES de los estilos -->
    <script>
        window.PagedConfig = { 
            auto: false,
            before: async () => {
                console.log('Paged.js: Iniciando procesamiento...');
            },
            after: () => {
                console.log('Paged.js: Procesamiento completado');
            }
        };
    </script>
    <script src="https://unpkg.com/pagedjs/dist/paged.polyfill.js"></script>
    
    <style>
    /* CSS de la plantilla EXACTO - sin tocar nada */
    <?php echo $css_limpio; ?>
    
    /* PAGED.JS - REGLAS CON MÁXIMA PRIORIDAD */
    @page {
        size: Letter !important;
        margin: 15mm 8mm 15mm 8mm !important;
    }
    
    /* (estilos del footer paginador) Crear footer manual ya que @page podría estar siendo interferido */
    .pagedjs_pages .pagedjs_page .pagedjs_margin-bottom {
        position: absolute !important;
        bottom: 2mm !important;
        left: 0 !important;
        right: 0 !important;
        height: 12mm !important;
        background: white !important;
        border-top: 1px solid #333 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-family: Arial, sans-serif !important;
        font-size: 9px !important;
        color: #333 !important;
        z-index: 10000 !important;
        padding: 2mm 0 !important;
    }
    
    .pagedjs_pages .pagedjs_page .pagedjs_margin-bottom::before {
        content: "Página " counter(page) " de " counter(pages) !important;
        display: block !important;
        text-align: center !important;
        width: 100% !important;
    }
    
    /* Resto de controles igual... */
    .paged-print-ui-controls {
        position: fixed !important;
        top: 10px !important;
        right: 10px !important;
        z-index: 9999 !important;
        background: rgba(0,0,0,0.9) !important;
        padding: 12px !important;
        border-radius: 6px !important;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
    }
        
        .paged-print-ui-controls button {
            margin: 0 4px !important;
            padding: 8px 16px !important;
            border: none !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
        }
        
        .paged-print-ui-btn-print {
            background: #0066cc !important;
            color: white !important;
        }
        
        .paged-print-ui-btn-print:hover {
            background: #0052a3 !important;
            transform: translateY(-1px) !important;
        }
        
        .paged-print-ui-btn-close {
            background: #dc3545 !important;
            color: white !important;
        }
        
        .paged-print-ui-btn-close:hover {
            background: #c82333 !important;
            transform: translateY(-1px) !important;
        }
        
        /* Ocultar controles SOLO en impresión */
        @media print {
            .paged-print-ui-controls {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Controles con namespace específico -->
    <div class="paged-print-ui-controls">
        <button class="paged-print-ui-btn-print" onclick="window.print()">🖨️ Imprimir</button>
        <button class="paged-print-ui-btn-close" onclick="window.close()">✕ Cerrar</button>
    </div>
    
    <!-- Contenido HTML EXACTO sin modificar -->
    <?php echo $html_limpio; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM cargado, esperando Paged.js...');
            
            // Verificar si Paged.js está disponible
            function initPagedJS() {
                if (typeof window.PagedPolyfill !== 'undefined') {
                    console.log('Paged.js encontrado, iniciando...');
                    
                    window.PagedPolyfill.preview().then(() => {
                        console.log('✅ Paged.js completado - Numeración activa');
                        
                        // Verificar si se crearon las páginas
                        setTimeout(() => {
                            const pages = document.querySelectorAll('.pagedjs_page');
                            console.log(`📄 Páginas creadas: ${pages.length}`);
                            
                            // Auto-imprimir después de procesar
                            setTimeout(() => {
                                console.log('🖨️ Iniciando impresión automática...');
                                window.print();
                            }, 1500);
                        }, 500);
                        
                    }).catch(error => {
                        console.error('❌ Error en Paged.js:', error);
                        console.log('📄 Fallback: Impresión sin numeración');
                        setTimeout(() => window.print(), 1000);
                    });
                } else {
                    console.error('❌ Paged.js no encontrado');
                    setTimeout(() => window.print(), 1000);
                }
            }
            
            // Dar tiempo para que Paged.js se cargue
            setTimeout(initPagedJS, 500);
        });
        
        // Evento después de imprimir
        window.addEventListener('afterprint', function() {
            console.log('📄 Impresión completada');
            setTimeout(() => {
                if (confirm('¿Cerrar esta ventana?')) {
                    window.close();
                }
            }, 1000);
        });
    </script>
</body>
</html><?php
    exit;
}
    
    /**
     * Obtener datos del documento según tipo
     */
    private function obtener_datos_documento($tipo, $id) {
        switch ($tipo) {
            case 'cotizacion':
                return $this->obtener_datos_cotizacion($id);
            case 'factura':
                return $this->obtener_datos_factura($id);
            // Agregar más tipos según necesidad
            default:
                //error_log("DOCUMENT_VIEWER: Tipo de documento no soportado: $tipo");
                return null;
        }
    }
    
    /**
     * Obtener datos completos de cotización
     */
    private function obtener_datos_cotizacion($id) {
        //error_log("DOCUMENT_VIEWER: obtener_datos_cotizacion - ID: $id");
        
        // Verificar e inicializar $this->db si es null
        if (!$this->db || !is_object($this->db)) {
            //error_log("DOCUMENT_VIEWER: Inicializando base de datos en obtener_datos_cotizacion...");
            
            global $modulo_ventas_db;
            
            if (!$modulo_ventas_db || !is_object($modulo_ventas_db)) {
                if (!class_exists('Modulo_Ventas_DB')) {
                    require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-db.php';
                }
                $modulo_ventas_db = new Modulo_Ventas_DB();
            }
            
            $this->db = $modulo_ventas_db;
        }
        
        if (!$this->db || !is_object($this->db)) {
            //error_log("DOCUMENT_VIEWER: ERROR - No se pudo inicializar la base de datos");
            return null;
        }
        
        // Obtener cotización
        $cotizacion = $this->db->obtener_cotizacion($id);
        if (!$cotizacion) {
            //error_log("DOCUMENT_VIEWER: Cotización no encontrada: $id");
            return null;
        }
        
        //error_log("DOCUMENT_VIEWER: Cotización encontrada: " . (isset($cotizacion->folio) ? $cotizacion->folio : $cotizacion->id));
        
        // Agregar items
        $cotizacion->items = $this->db->obtener_items_cotizacion($id);
        //error_log("DOCUMENT_VIEWER: Items cargados: " . count($cotizacion->items));
        
        // Agregar datos del cliente - CORREGIR WARNING
        if ($cotizacion->cliente_id) {
            $cotizacion->cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
            if ($cotizacion->cliente) {
                // CORREGIR: Verificar qué propiedad existe realmente
                $nombre_cliente = '';
                if (isset($cotizacion->cliente->nombre)) {
                    $nombre_cliente = $cotizacion->cliente->nombre;
                } elseif (isset($cotizacion->cliente->razon_social)) {
                    $nombre_cliente = $cotizacion->cliente->razon_social;
                } elseif (isset($cotizacion->cliente->empresa)) {
                    $nombre_cliente = $cotizacion->cliente->empresa;
                } else {
                    // Debug: ver qué propiedades tiene el cliente
                    //error_log("DOCUMENT_VIEWER: Propiedades del cliente: " . implode(', ', array_keys((array)$cotizacion->cliente)));
                    $nombre_cliente = 'Cliente ID: ' . $cotizacion->cliente_id;
                }
                //error_log("DOCUMENT_VIEWER: Cliente cargado: " . $nombre_cliente);
            }
        }
        
        // Calcular totales si no existen
        if (!isset($cotizacion->subtotal)) {
            $this->calcular_totales_cotizacion($cotizacion);
            //error_log("DOCUMENT_VIEWER: Totales calculados");
        }
        
        return $cotizacion;
    }
    
    /**
     * Obtener plantilla activa para tipo de documento
     */
    private function obtener_plantilla_activa($tipo) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        // CORREGIR: Usar columna 'tipo' en lugar de 'tipo_documento'
        $plantilla = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabla} 
            WHERE tipo = %s AND activa = 1 
            ORDER BY es_predeterminada DESC, fecha_creacion DESC LIMIT 1",
            $tipo
        ));
        
        if (!$plantilla) {
            //error_log("DOCUMENT_VIEWER: No se encontró plantilla activa para tipo: $tipo");
            
            // Fallback: buscar cualquier plantilla activa
            $plantilla = $wpdb->get_row("SELECT * FROM {$tabla} WHERE activa = 1 ORDER BY es_predeterminada DESC, id ASC LIMIT 1");
            
            if ($plantilla) {
                //error_log("DOCUMENT_VIEWER: Usando plantilla fallback: " . $plantilla->nombre);
            } else {
                //error_log("DOCUMENT_VIEWER: No hay plantillas activas disponibles");
            }
        }
        
        return $plantilla;
    }
    
    /**
     * Obtener plantilla por ID específico
     */
    private function obtener_plantilla_por_id($template_id) {
        global $wpdb;
        
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        $plantilla = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabla} WHERE id = %d",
            $template_id
        ));
        
        return $plantilla;
    }
    
    /**
     * Generar título dinámico para documento
     */
    private function generar_titulo_documento($documento, $modo) {
        $tipo_nombres = array(
            'cotizacion' => 'Cotización',
            'factura' => 'Factura',
            'boleta' => 'Boleta',
            'orden_compra' => 'Orden de Compra',
            'guia_despacho' => 'Guía de Despacho'
        );
        
        // CORREGIR: Determinar tipo del documento de forma segura
        $tipo_documento = 'cotizacion'; // Valor por defecto
        
        // Si el objeto tiene propiedad 'tipo'
        if (isset($documento->tipo)) {
            $tipo_documento = $documento->tipo;
        }
        // Si no, inferir del contexto (ya que sabemos que viene de cotizaciones)
        elseif (isset($documento->folio) && strpos($documento->folio, 'COT-') === 0) {
            $tipo_documento = 'cotizacion';
        }
        // Si tiene ID de cotización
        elseif (isset($documento->id) && is_numeric($documento->id)) {
            $tipo_documento = 'cotizacion';
        }
        
        $tipo_nombre = isset($tipo_nombres[$tipo_documento]) ? 
                    $tipo_nombres[$tipo_documento] : 
                    'Documento';
        
        $folio = isset($documento->folio) ? $documento->folio : 
                (isset($documento->numero) ? $documento->numero : 
                (isset($documento->id) ? $documento->id : 'S/N'));
        
        $titulo = $tipo_nombre . ' ' . $folio;
        
        if ($modo === 'preview') {
            $titulo .= ' - Vista Previa';
        } elseif ($modo === 'print') {
            $titulo .= ' - Impresión';
        }
        
        return $titulo;
    }
    
    /**
     * Agregar botones de visualización en admin
     */
    public function add_admin_view_buttons() {
        // Agregar JavaScript para botones en listas de cotizaciones
        add_action('admin_footer', array($this, 'admin_view_buttons_script'));
    }
    
    /**
     * Script para botones de visualización en admin
     */
    public function admin_view_buttons_script() {
        $screen = get_current_screen();
        
        // Detectar si estamos en la página de cotizaciones de múltiples formas
        $is_cotizaciones_page = false;
        
        if ($screen) {
            // Debug info
            //error_log("DOCUMENT_VIEWER DEBUG: Screen ID = " . $screen->id);
            //error_log("DOCUMENT_VIEWER DEBUG: Screen base = " . $screen->base);
            //error_log("DOCUMENT_VIEWER DEBUG: GET page = " . (isset($_GET['page']) ? $_GET['page'] : 'no-page'));
            
            // Método 1: Por ID de screen que contenga 'cotizacion'
            if (strpos($screen->id, 'cotizacion') !== false) {
                $is_cotizaciones_page = true;
                //error_log("DOCUMENT_VIEWER: Detectado por screen ID");
            }
            
            // Método 2: Por página GET
            if (isset($_GET['page']) && $_GET['page'] === 'modulo-ventas-cotizaciones') {
                $is_cotizaciones_page = true;
                //error_log("DOCUMENT_VIEWER: Detectado por GET page");
            }
            
            // Método 3: Por base del screen
            if (strpos($screen->base, 'cotizacion') !== false) {
                $is_cotizaciones_page = true;
                //error_log("DOCUMENT_VIEWER: Detectado por screen base");
            }
            
            // Método 4: Fuerza detección si estamos en el admin del plugin
            if (strpos($screen->id, 'modulo-ventas') !== false && strpos($screen->id, 'cotizacion') !== false) {
                $is_cotizaciones_page = true;
                //error_log("DOCUMENT_VIEWER: Detectado por plugin admin");
            }
        }
        
        // Debug adicional
        //error_log("DOCUMENT_VIEWER: ¿Es página de cotizaciones? " . ($is_cotizaciones_page ? 'SÍ' : 'NO'));
        
        if (!$is_cotizaciones_page) {
            // Mostrar debug en la consola para páginas incorrectas
            ?>
            <script>
            console.log("DOCUMENT_VIEWER DEBUG: NO es página de cotizaciones");
            console.log("Screen ID:", "<?php echo $screen ? $screen->id : 'undefined'; ?>");
            console.log("Screen base:", "<?php echo $screen ? $screen->base : 'undefined'; ?>");
            console.log("GET page:", "<?php echo isset($_GET['page']) ? $_GET['page'] : 'undefined'; ?>");
            console.log("URL actual:", window.location.href);
            </script>
            <?php
            return;
        }
        
        ?>
        <script>
        console.log("DOCUMENT_VIEWER: ✅ Página de cotizaciones detectada correctamente");
        console.log("Screen ID:", "<?php echo $screen ? $screen->id : 'undefined'; ?>");
        
        jQuery(document).ready(function($) {
            console.log("DOCUMENT_VIEWER: Iniciando búsqueda de filas de cotizaciones...");
            
            // Esperar a que la tabla se cargue
            setTimeout(function() {
                let filasEncontradas = 0;
                let botonesAgregados = 0;
                
                // Buscar filas de la tabla de cotizaciones
                $('.wp-list-table tbody tr').each(function() {
                    const $row = $(this);
                    filasEncontradas++;
                    
                    // Buscar ID de cotización
                    let cotizacionId = null;
                    
                    // Método 1: Checkbox
                    const $checkbox = $row.find('input[type="checkbox"]');
                    if ($checkbox.length && $checkbox.val() && $checkbox.val() !== '0') {
                        cotizacionId = $checkbox.val();
                        console.log("DOCUMENT_VIEWER: ID encontrado por checkbox:", cotizacionId);
                    }
                    
                    // Método 2: Enlaces existentes
                    if (!cotizacionId) {
                        const $links = $row.find('a[href*="id="]');
                        $links.each(function() {
                            const href = $(this).attr('href');
                            const match = href.match(/[?&]id=(\d+)/);
                            if (match) {
                                cotizacionId = match[1];
                                console.log("DOCUMENT_VIEWER: ID encontrado por enlace:", cotizacionId);
                                return false; // break
                            }
                        });
                    }
                    
                    // Método 3: Atributos data
                    if (!cotizacionId) {
                        cotizacionId = $row.attr('data-id') || $row.data('id');
                        if (cotizacionId) {
                            console.log("DOCUMENT_VIEWER: ID encontrado por data attribute:", cotizacionId);
                        }
                    }
                    
                    if (cotizacionId && cotizacionId !== '0') {
                        console.log("DOCUMENT_VIEWER: Procesando cotización ID:", cotizacionId);
                        
                        // URLs para los botones
                        const viewUrl = "<?php echo home_url(); ?>/documentos/cotizacion/" + cotizacionId;
                        const printUrl = viewUrl + "/imprimir";
                        
                        // Buscar o crear contenedor de acciones
                        let $actions = $row.find('.row-actions');
                        if ($actions.length === 0) {
                            // Crear en la primera celda con contenido
                            const $firstCell = $row.find('td').first();
                            if ($firstCell.length) {
                                $firstCell.append('<div class="row-actions"></div>');
                                $actions = $firstCell.find('.row-actions');
                                console.log("DOCUMENT_VIEWER: Creado contenedor de acciones");
                            }
                        }
                        
                        if ($actions.length > 0) {
                            // Verificar si ya existen los botones
                            if ($actions.find('a[href*="/documentos/cotizacion/"]').length === 0) {
                                // Agregar los botones
                                $actions.append(
                                    ' | <a href="' + viewUrl + '" target="_blank" title="Ver documento en navegador" style="color: #0073aa; text-decoration: none; font-weight: bold;">👁️ Ver Web</a>' +
                                    ' | <a href="' + printUrl + '" target="_blank" title="Abrir vista de impresión" style="color: #135e96; text-decoration: none; font-weight: bold;">🖨️ Imprimir</a>'
                                );
                                botonesAgregados++;
                                console.log("DOCUMENT_VIEWER: Botones agregados para cotización", cotizacionId);
                            } else {
                                console.log("DOCUMENT_VIEWER: Botones ya existen para cotización", cotizacionId);
                            }
                        } else {
                            console.log("DOCUMENT_VIEWER: No se pudo encontrar/crear contenedor de acciones");
                        }
                    } else {
                        console.log("DOCUMENT_VIEWER: No se pudo encontrar ID para esta fila");
                    }
                });
                
                console.log("DOCUMENT_VIEWER: RESUMEN:");
                console.log("- Filas encontradas:", filasEncontradas);
                console.log("- Botones agregados:", botonesAgregados);
                
                if (filasEncontradas === 0) {
                    console.log("DOCUMENT_VIEWER: ⚠️ No se encontraron filas. Verificando estructura HTML...");
                    console.log("- Tablas encontradas:", $('table').length);
                    console.log("- Tablas WP:", $('.wp-list-table').length);
                    console.log("- Tbody encontrados:", $('tbody').length);
                    
                    if ($('.wp-list-table').length > 0) {
                        console.log("HTML de la primera tabla:", $('.wp-list-table').first().html().substring(0, 300) + "...");
                    }
                } else if (botonesAgregados === 0) {
                    console.log("DOCUMENT_VIEWER: ⚠️ Se encontraron filas pero no se agregaron botones");
                    console.log("Revisar estructura de las filas o selectors CSS");
                } else {
                    console.log("DOCUMENT_VIEWER: ✅ Botones agregados exitosamente");
                }
                
            }, 1500); // Esperar 1.5 segundos para asegurar que todo esté cargado
        });
        </script>
        
        <style>
        /* Estilos para los botones web */
        .row-actions a[href*="/documentos/cotizacion/"] {
            font-weight: bold !important;
            text-decoration: none !important;
        }
        
        .row-actions a[href*="/documentos/cotizacion/"]:hover {
            text-decoration: underline !important;
        }
        
        /* Asegurar que los row-actions sean visibles */
        .wp-list-table .row-actions {
            position: static !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Vista previa desde admin
     */
    public function ajax_preview_document() {
        check_ajax_referer('mv_preview_document', 'nonce');
        
        if (!current_user_can('edit_cotizaciones')) {
            wp_die(__('Sin permisos', 'modulo-ventas'));
        }
        
        $tipo = sanitize_text_field($_POST['tipo']);
        $doc_id = intval($_POST['doc_id']);
        $template_id = intval($_POST['template_id']);
        
        // Redirigir a URL de preview
        $url = home_url("/documentos/{$tipo}/{$doc_id}/preview/{$template_id}");
        wp_send_json_success(array('url' => $url));
    }
    
    /**
     * Generar URL pública para documento
     */
    public function generar_url_publica($tipo, $id, $con_token = true) {
        $url = home_url("/documentos/{$tipo}/{$id}");
        
        if ($con_token) {
            $token = $this->generar_token_documento($tipo, $id);
            $url .= "?token={$token}";
        }
        
        return $url;
    }
    
    /**
     * Calcular totales para cotización
     */
    private function calcular_totales_cotizacion($cotizacion) {
        $subtotal = 0;
        
        foreach ($cotizacion->items as $item) {
            $subtotal += $item->cantidad * $item->precio_unitario;
        }
        
        $descuento = isset($cotizacion->descuento_porcentaje) ? 
                    ($subtotal * $cotizacion->descuento_porcentaje / 100) : 0;
        
        $subtotal_con_descuento = $subtotal - $descuento;
        $impuestos = $subtotal_con_descuento * 0.19; // IVA 19%
        $total = $subtotal_con_descuento + $impuestos;
        
        // Agregar propiedades calculadas
        $cotizacion->subtotal = $subtotal;
        $cotizacion->descuento_monto = $descuento;
        $cotizacion->impuestos = $impuestos;
        $cotizacion->total = $total;
        
        // Versiones formateadas
        $cotizacion->subtotal_formateado = number_format($subtotal, 0, ',', '.');
        $cotizacion->descuento_formateado = number_format($descuento, 0, ',', '.');
        $cotizacion->impuestos_formateado = number_format($impuestos, 0, ',', '.');
        $cotizacion->total_formateado = number_format($total, 0, ',', '.');
    }
}

// Inicializar el sistema
function init_modulo_ventas_document_viewer() {
    global $modulo_ventas_document_viewer;
    $modulo_ventas_document_viewer = new Modulo_Ventas_Document_Viewer();
}

add_action('init', 'init_modulo_ventas_document_viewer', 15);