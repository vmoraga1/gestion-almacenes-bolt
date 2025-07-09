<?php
/**
 * Clase para manejar las descargas y visualización de PDFs
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hooks para manejar requests de PDF
        add_action('init', array($this, 'manejar_requests_pdf'));
        //add_action('wp_ajax_mv_generar_pdf_cotizacion', array($this, 'ajax_generar_pdf'));
        //add_action('wp_ajax_mv_descargar_pdf_cotizacion', array($this, 'ajax_descargar_pdf'));
        
        // Endpoint personalizado para PDFs
        add_action('init', array($this, 'agregar_endpoint_pdf'));
        add_action('template_redirect', array($this, 'manejar_endpoint_pdf'));
    }
    
    /**
     * Agregar endpoint personalizado para PDFs
     */
    public function agregar_endpoint_pdf() {
        add_rewrite_rule(
            '^mv-pdf/([^/]+)/([0-9]+)/?$',
            'index.php?mv_pdf_action=$matches[1]&mv_pdf_id=$matches[2]',
            'top'
        );
        
        add_rewrite_tag('%mv_pdf_action%', '([^&]+)');
        add_rewrite_tag('%mv_pdf_id%', '([0-9]+)');
    }
    
    /**
     * Manejar endpoint de PDF
     */
    public function manejar_endpoint_pdf() {
        $action = get_query_var('mv_pdf_action');
        $id = get_query_var('mv_pdf_id');
        
        if ($action && $id) {
            $this->procesar_request_pdf($action, $id);
        }
    }
    
    /**
     * Manejar requests de PDF via query params
     */
    public function manejar_requests_pdf() {
        if (!isset($_GET['mv_pdf'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['mv_pdf']);
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
        
        // Verificar nonce
        if (!wp_verify_nonce($nonce, 'mv_pdf_' . $action . '_' . $id)) {
            wp_die(__('Enlace de seguridad inválido', 'modulo-ventas'));
        }
        
        $this->procesar_request_pdf($action, $id);
    }
    
    /**
     * Procesar request de PDF
     */
    private function procesar_request_pdf($action, $id) {
        // Verificar permisos
        if (!current_user_can('view_cotizaciones')) {
            wp_die(__('No tiene permisos para ver este documento', 'modulo-ventas'));
        }
        
        switch ($action) {
            case 'cotizacion':
                $this->servir_pdf_cotizacion($id, 'inline');
                break;
                
            case 'cotizacion-download':
                $this->servir_pdf_cotizacion($id, 'attachment');
                break;
                
            case 'preview':
                $this->preview_pdf_cotizacion($id);
                break;
                
            default:
                wp_die(__('Acción no válida', 'modulo-ventas'));
        }
    }
    
    /**
     * Servir PDF de cotización
     */
    private function servir_pdf_cotizacion($cotizacion_id, $disposition = 'inline') {
        // PASO 1: Limpiar cualquier salida previa ANTES de generar el PDF
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        try {
            // PASO 2: Generar el PDF
            $pdf_generator = new Modulo_Ventas_PDF();
            $pdf_path = $pdf_generator->generar_pdf_cotizacion($cotizacion_id);
            
            // PASO 3: Verificar errores en la generación
            if (is_wp_error($pdf_path)) {
                error_log('MODULO_VENTAS_PDF_HANDLER: Error en generación: ' . $pdf_path->get_error_message());
                wp_die($pdf_path->get_error_message(), 'Error PDF', array('response' => 500));
            }
            
            // PASO 4: Verificar que el archivo existe
            if (!file_exists($pdf_path)) {
                error_log('MODULO_VENTAS_PDF_HANDLER: Archivo no encontrado: ' . $pdf_path);
                wp_die(__('Archivo PDF no encontrado', 'modulo-ventas'), 'Error PDF', array('response' => 404));
            }
            
            // PASO 5: Verificar que se puede leer
            if (!is_readable($pdf_path)) {
                error_log('MODULO_VENTAS_PDF_HANDLER: Archivo no legible: ' . $pdf_path);
                wp_die(__('No se puede leer el archivo PDF', 'modulo-ventas'), 'Error PDF', array('response' => 403));
            }
            
            // PASO 6: Obtener información del archivo
            $filesize = filesize($pdf_path);
            if ($filesize === false || $filesize <= 0) {
                error_log('MODULO_VENTAS_PDF_HANDLER: Archivo vacío: ' . $pdf_path);
                wp_die(__('Archivo PDF vacío o corrupto', 'modulo-ventas'), 'Error PDF', array('response' => 500));
            }
            
            // PASO 7: Obtener información de la cotización para el nombre del archivo
            $db = new Modulo_Ventas_DB();
            $cotizacion = $db->obtener_cotizacion($cotizacion_id);
            
            $filename = 'cotizacion_' . ($cotizacion ? $cotizacion->folio : $cotizacion_id) . '.pdf';
            $filename = sanitize_file_name($filename);
            
            // PASO 8: Iniciar nuevo buffer de salida limpio
            ob_start();
            
            // PASO 9: Configurar headers sin que generen salida
            $content_disposition = ($disposition === 'attachment') ? 'attachment' : 'inline';
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . $content_disposition . '; filename="' . $filename . '"');
            header('Content-Length: ' . $filesize);
            header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('X-Robots-Tag: noindex, nofollow');
            header('X-Content-Type-Options: nosniff');
            
            // PASO 10: Limpiar buffer antes de enviar archivo
            ob_end_clean();
            
            // PASO 11: Enviar archivo directamente
            readfile($pdf_path);
            
            // PASO 12: Log de éxito
            error_log('MODULO_VENTAS_PDF_HANDLER: PDF servido exitosamente - ' . $filename . ' (' . $filesize . ' bytes)');
            
        } catch (Exception $e) {
            // Limpiar buffer en caso de error
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            error_log('MODULO_VENTAS_PDF_HANDLER: Exception servir PDF: ' . $e->getMessage());
            error_log('MODULO_VENTAS_PDF_HANDLER: Exception trace: ' . $e->getTraceAsString());
            wp_die('Error al servir PDF: ' . $e->getMessage(), 'Error PDF', array('response' => 500));
        }
        
        // PASO 13: Terminar ejecución
        exit;
    }
    
    /**
     * Preview de PDF en navegador
     */
    private function preview_pdf_cotizacion($cotizacion_id) {
        $this->servir_pdf_cotizacion($cotizacion_id, 'inline');
    }
    
    /**
     * AJAX: Generar PDF
     */
    public function ajax_generar_pdf() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('view_cotizaciones')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $cotizacion_id = isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : 0;
        
        if (!$cotizacion_id) {
            wp_send_json_error(array('message' => __('ID de cotización inválido', 'modulo-ventas')));
        }
        
        $pdf_generator = new Modulo_Ventas_PDF();
        $pdf_path = $pdf_generator->generar_pdf_cotizacion($cotizacion_id);
        
        if (is_wp_error($pdf_path)) {
            wp_send_json_error(array('message' => $pdf_path->get_error_message()));
        }
        
        // Generar URLs
        $preview_url = $this->generar_url_pdf($cotizacion_id, 'preview');
        $download_url = $this->generar_url_pdf($cotizacion_id, 'cotizacion-download');
        
        wp_send_json_success(array(
            'message' => __('PDF generado exitosamente', 'modulo-ventas'),
            'preview_url' => $preview_url,
            'download_url' => $download_url,
            'pdf_path' => $pdf_path
        ));
    }
    
    /**
     * AJAX: Descargar PDF
     */
    public function ajax_descargar_pdf() {
        $this->ajax_generar_pdf();
    }
    
    /**
     * Generar URL para PDF
     */
    public function generar_url_pdf($cotizacion_id, $action = 'cotizacion') {
        $nonce = wp_create_nonce('mv_pdf_' . $action . '_' . $cotizacion_id);
        
        return add_query_arg(array(
            'mv_pdf' => $action,
            'id' => $cotizacion_id,
            'nonce' => $nonce
        ), home_url());
    }
    
    /**
     * Generar URL de preview
     */
    public function generar_url_preview($cotizacion_id) {
        return $this->generar_url_pdf($cotizacion_id, 'preview');
    }
    
    /**
     * Generar URL de descarga
     */
    public function generar_url_descarga($cotizacion_id) {
        return $this->generar_url_pdf($cotizacion_id, 'cotizacion-download');
    }
    
    /**
     * Enviar headers para PDF
     */
    private function enviar_headers_pdf($filename, $disposition = 'inline') {
        // Limpiar output buffer
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        // Headers de seguridad
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
        
        // Headers del PDF
        header('Content-Type: application/pdf', true);
        header('Cache-Control: private, max-age=0, must-revalidate', true);
        header('Pragma: public', true);
        
        // Disposition (inline para ver en navegador, attachment para descargar)
        if ($disposition === 'attachment') {
            header('Content-Disposition: attachment; filename="' . $filename . '"', true);
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"', true);
        }
    }
    
    /**
     * Limpiar archivos temporales antiguos
     */
    public function limpiar_archivos_temporales() {
        $pdf_generator = new Modulo_Ventas_PDF();
        return $pdf_generator->limpiar_pdfs_antiguos();
    }
    
    /**
     * Verificar si un PDF existe
     */
    public function pdf_existe($cotizacion_id) {
        $pdf_generator = new Modulo_Ventas_PDF();
        
        // Generar nombre de archivo esperado
        $db = new Modulo_Ventas_DB();
        $cotizacion = $db->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            return false;
        }
        
        $fecha = date('Y-m-d');
        $folio = preg_replace('/[^a-zA-Z0-9]/', '', $cotizacion->folio);
        $filename = "cotizacion_{$folio}_{$fecha}.pdf";
        
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/modulo-ventas/pdfs/' . $filename;
        
        return file_exists($pdf_path);
    }
    
    /**
     * Obtener información de un PDF
     */
    public function obtener_info_pdf($cotizacion_id) {
        if (!$this->pdf_existe($cotizacion_id)) {
            return false;
        }
        
        $db = new Modulo_Ventas_DB();
        $cotizacion = $db->obtener_cotizacion($cotizacion_id);
        
        $fecha = date('Y-m-d');
        $folio = preg_replace('/[^a-zA-Z0-9]/', '', $cotizacion->folio);
        $filename = "cotizacion_{$folio}_{$fecha}.pdf";
        
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/modulo-ventas/pdfs/' . $filename;
        
        return array(
            'filename' => $filename,
            'path' => $pdf_path,
            'size' => filesize($pdf_path),
            'created' => filemtime($pdf_path),
            'preview_url' => $this->generar_url_preview($cotizacion_id),
            'download_url' => $this->generar_url_descarga($cotizacion_id)
        );
    }
}