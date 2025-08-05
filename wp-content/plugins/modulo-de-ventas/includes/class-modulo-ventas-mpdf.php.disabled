<?php
/**
 * Wrapper para mPDF - Compatible con sistema actual
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_mPDF {
    
    private $mpdf;
    private $logger;
    
    public function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
        $this->verificar_mpdf();
    }
    
    /**
     * Verificar que mPDF está disponible
     */
    private function verificar_mpdf() {
        // Cargar mPDF
        if (!class_exists('Mpdf\Mpdf')) {
            // Intentar cargar via autoload
            $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
            } else {
                // Cargar directamente
                $mpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/mpdf/mpdf/src/Mpdf.php';
                if (file_exists($mpdf_path)) {
                    require_once $mpdf_path;
                } else {
                    throw new Exception('mPDF no está instalado');
                }
            }
        }
        
        $this->logger->log("MPDF: Librería cargada exitosamente");
    }
    
    /**
     * Generar PDF desde plantilla (MÉTODO PRINCIPAL)
     */
    public function generar_pdf_desde_plantilla($cotizacion_id) {
        try {
            $this->logger->log("MPDF: Generando PDF sincronizado para cotización {$cotizacion_id}");
            
            // Usar el nuevo sistema de sincronización visual
            $sync_system = Modulo_Ventas_mPDF_Visual_Sync::get_instance();
            
            // Obtener plantilla activa
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates.php';
            $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
            $plantilla = $pdf_templates->obtener_plantilla_activa('cotizacion');
            
            if (!$plantilla) {
                return new WP_Error('no_template', 'No hay plantilla activa para cotizaciones');
            }
            
            // Generar HTML sincronizado para PDF
            $documento_html = $sync_system->sincronizar_plantilla_para_mpdf($plantilla, $cotizacion_id, false);
            
            $this->logger->log("MPDF: HTML sincronizado generado (" . strlen($documento_html) . " caracteres)");
            
            // Convertir a PDF usando mPDF
            return $this->convertir_html_a_pdf($documento_html, $cotizacion_id);
            
        } catch (Exception $e) {
            $this->logger->log('MPDF: Error en PDF sincronizado: ' . $e->getMessage(), 'error');
            return new WP_Error('pdf_generation_error', 'Error generando PDF: ' . $e->getMessage());
        }
    }

    
    /**
     * Optimizar CSS para mPDF (reemplazar Flexbox con alternativas)
     */
    private function optimizar_css_para_mpdf($html_content) {
        // Reemplazos CSS para mPDF
        $css_replacements = array(
            // Flexbox a Display Table
            'display: flex' => 'display: table',
            'display:flex' => 'display:table',
            'flex-direction: row' => 'table-layout: auto',
            'flex-direction: column' => 'display: table-cell; vertical-align: top',
            'justify-content: space-between' => 'width: 100%',
            'align-items: center' => 'vertical-align: middle',
            'flex: 1' => 'width: 100%',
            
            // CSS Grid a Table
            'display: grid' => 'display: table',
            'grid-template-columns' => 'width',
            
            // Position Fixed (no soportado)
            'position: fixed' => 'position: absolute',
            
            // Transform (limitado)
            'transform:' => '/* transform no soportado */',
            
            // Box Shadow simplificado
            'box-shadow: ' => 'border: 1px solid #ddd; /* ',
            
            // Viewport units
            'vw' => '%',
            'vh' => '%',
        );
        
        foreach ($css_replacements as $search => $replace) {
            $html_content = str_ireplace($search, $replace, $html_content);
        }
        
        // Agregar CSS específico para mPDF
        $mpdf_css = '
        <style>
        /* CSS optimizado para mPDF */
        .header-empresa {
            width: 100%;
            border-collapse: collapse;
        }
        .header-empresa td {
            vertical-align: top;
            padding: 10px;
        }
        .empresa-info {
            width: 60%;
        }
        .cotizacion-info {
            width: 40%;
            text-align: right;
        }
        .productos-tabla {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .productos-tabla th,
        .productos-tabla td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .productos-tabla th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .totales-seccion {
            width: 100%;
            text-align: right;
            margin-top: 20px;
        }
        .totales {
            display: inline-block;
            min-width: 300px;
            border: 1px solid #ddd;
            padding: 15px;
        }
        .total-fila {
            margin: 5px 0;
            padding: 3px 0;
        }
        .total-final {
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
            font-weight: bold;
        }
        </style>';
        
        // Insertar CSS optimizado antes del </head>
        $html_content = str_replace('</head>', $mpdf_css . '</head>', $html_content);
        
        $this->logger->log("MPDF: CSS optimizado para mPDF");
        return $html_content;
    }
    
    /**
     * Convertir HTML a PDF usando mPDF
     */
    private function convertir_html_a_pdf($html_content, $cotizacion_id) {
        try {
            // Obtener datos para nombre del archivo
            global $wpdb;
            $tabla = $wpdb->prefix . 'mv_cotizaciones';
            $cotizacion = $wpdb->get_row($wpdb->prepare(
                "SELECT folio, fecha FROM $tabla WHERE id = %d",
                $cotizacion_id
            ));
            
            $nombre_archivo = $cotizacion ? 
                sanitize_file_name('cotizacion_' . $cotizacion->folio . '.pdf') : 
                'cotizacion_' . $cotizacion_id . '.pdf';
            
            // Configurar mPDF
            $config = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_header' => 10,
                'margin_footer' => 10,
                'default_font_size' => 10,
                'default_font' => 'Arial',
                'tempDir' => sys_get_temp_dir(),
            );
            
            // Crear instancia mPDF
            $this->mpdf = new \Mpdf\Mpdf($config);
            
            // Configurar metadatos
            $this->mpdf->SetTitle('Cotización ' . $cotizacion_folio);
            $this->mpdf->SetAuthor(get_bloginfo('name'));
            $this->mpdf->SetCreator('Módulo de Ventas - mPDF');
            
            $this->mpdf->SetDisplayMode('fullpage');
            $this->mpdf->showWatermarkText = false;
            $this->mpdf->showWatermarkImage = false;

            // Escribir HTML
            $this->mpdf->WriteHTML($html_content);
            
            // Crear directorio de destino
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/modulo-ventas-pdf/';
            
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            
            // Ruta completa del archivo
            $pdf_path = $pdf_dir . $nombre_archivo;
            
            // Guardar PDF
            $this->mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
            
            // Verificar que se creó correctamente
            if (!file_exists($pdf_path) || filesize($pdf_path) == 0) {
                return new WP_Error('pdf_save_error', 'Error al guardar el archivo PDF');
            }
            
            $this->logger->log("MPDF: PDF guardado exitosamente en: {$pdf_path}");
            return $pdf_path;
            
        } catch (\Mpdf\MpdfException $e) {
            $this->logger->log('MPDF: Error mPDF: ' . $e->getMessage());
            return new WP_Error('mpdf_error', 'Error de mPDF: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->log('MPDF: Error general: ' . $e->getMessage());
            return new WP_Error('pdf_conversion_error', 'Error convirtiendo a PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Método de fallback para generar PDF simple sin plantillas
     */
    public function generar_pdf_cotizacion_simple($cotizacion_id) {
        try {
            // Obtener datos de la cotización
            global $wpdb;
            $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
            $tabla_clientes = $wpdb->prefix . 'mv_clientes';
            $tabla_items = $wpdb->prefix . 'mv_cotizacion_items';
            
            $cotizacion = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tabla_cotizaciones WHERE id = %d",
                $cotizacion_id
            ));
            
            if (!$cotizacion) {
                return new WP_Error('invalid_quote', 'Cotización no encontrada');
            }
            
            $cliente = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tabla_clientes WHERE id = %d",
                $cotizacion->cliente_id
            ));
            
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $tabla_items WHERE cotizacion_id = %d",
                $cotizacion_id
            ));
            
            // Generar HTML simple
            $html = $this->generar_html_simple($cotizacion, $cliente, $items);
            
            // Convertir a PDF
            return $this->convertir_html_a_pdf($html, $cotizacion_id);
            
        } catch (Exception $e) {
            $this->logger->log('MPDF: Error en PDF simple: ' . $e->getMessage());
            return new WP_Error('pdf_generation_error', 'Error generando PDF simple: ' . $e->getMessage());
        }
    }
    
    /**
     * Generar HTML simple para PDF
     */
    private function generar_html_simple($cotizacion, $cliente, $items) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Cotización <?php echo esc_html($cotizacion->folio); ?></title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .empresa-info h1 { color: #2c5aa0; margin: 0; }
                .cliente-info { background-color: #f9f9f9; padding: 15px; margin: 20px 0; }
                .productos-tabla { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .productos-tabla th, .productos-tabla td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .productos-tabla th { background-color: #f5f5f5; }
                .totales { text-align: right; margin-top: 20px; }
                .total-final { font-weight: bold; font-size: 14px; border-top: 2px solid #333; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="empresa-info">
                    <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                    <p>Cotización N° <?php echo esc_html($cotizacion->folio); ?></p>
                    <p>Fecha: <?php echo esc_html(date('d/m/Y', strtotime($cotizacion->fecha))); ?></p>
                </div>
            </div>
            
            <?php if ($cliente): ?>
            <div class="cliente-info">
                <h3>Cliente</h3>
                <p><strong><?php echo esc_html($cliente->razon_social); ?></strong></p>
                <p>RUT: <?php echo esc_html($cliente->rut); ?></p>
                <p>Email: <?php echo esc_html($cliente->email); ?></p>
                <p>Teléfono: <?php echo esc_html($cliente->telefono); ?></p>
            </div>
            <?php endif; ?>
            
            <table class="productos-tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->nombre_producto); ?></td>
                        <td><?php echo esc_html($item->cantidad); ?></td>
                        <td>$<?php echo number_format($item->precio_unitario, 0, ',', '.'); ?></td>
                        <td>$<?php echo number_format($item->total, 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="totales">
                <p>Subtotal: $<?php echo number_format($cotizacion->subtotal, 0, ',', '.'); ?></p>
                <p>IVA: $<?php echo number_format($cotizacion->impuestos, 0, ',', '.'); ?></p>
                <p class="total-final">TOTAL: $<?php echo number_format($cotizacion->total, 0, ',', '.'); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtener información del motor PDF
     */
    public function obtener_info_motor() {
        $info = array(
            'motor_actual' => 'mPDF',
            'mpdf_disponible' => class_exists('Mpdf\Mpdf'),
            'version_mpdf' => defined('Mpdf\VERSION') ? \Mpdf\VERSION : 'Desconocida',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'temp_dir' => sys_get_temp_dir(),
            'upload_dir_writable' => is_writable(wp_upload_dir()['basedir'])
        );
        
        return $info;
    }
    
    /**
     * Verificar disponibilidad de mPDF
     */
    public function mpdf_disponible() {
        try {
            return class_exists('Mpdf\Mpdf');
        } catch (Exception $e) {
            $this->logger->log('mPDF: Error verificando disponibilidad: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Método de prueba simple para test
     */
    public function test_generacion_simple() {
        try {
            // HTML de prueba muy básico
            $html_test = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Test mPDF</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .header { color: #2c5aa0; text-align: center; }
                    .content { margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Test de mPDF</h1>
                    <p>Generado el ' . date('d/m/Y H:i:s') . '</p>
                </div>
                <div class="content">
                    <p>Este es un PDF de prueba generado con mPDF.</p>
                    <p>Si puedes ver este contenido, <strong>mPDF está funcionando correctamente</strong>.</p>
                </div>
            </body>
            </html>';
            
            // Configurar mPDF para test
            $config = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'default_font_size' => 12,
                'default_font' => 'Arial',
                'tempDir' => sys_get_temp_dir(),
            );
            
            // Crear instancia mPDF
            $mpdf = new \Mpdf\Mpdf($config);
            
            // Configurar metadatos
            $mpdf->SetTitle('Test mPDF');
            $mpdf->SetAuthor('Módulo de Ventas');
            $mpdf->SetCreator('Test mPDF');
            
            // Escribir HTML
            $mpdf->WriteHTML($html_test);
            
            // Crear directorio de destino
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/modulo-ventas-pdf/';
            
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            
            // Nombre del archivo de test
            $pdf_path = $pdf_dir . 'test_mpdf_' . date('Y-m-d_H-i-s') . '.pdf';
            
            // Guardar PDF
            $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);
            
            // Verificar que se creó correctamente
            if (!file_exists($pdf_path) || filesize($pdf_path) == 0) {
                return new WP_Error('pdf_save_error', 'Error al guardar el archivo PDF de test');
            }
            
            $this->logger->log("mPDF: PDF de test generado exitosamente: {$pdf_path}");
            
            return array(
                'success' => true,
                'file_path' => $pdf_path,
                'file_size' => filesize($pdf_path),
                'file_url' => str_replace(ABSPATH, home_url('/'), $pdf_path)
            );
            
        } catch (\Mpdf\MpdfException $e) {
            $this->logger->log('mPDF: Error en test: ' . $e->getMessage());
            return new WP_Error('mpdf_test_error', 'Error de mPDF en test: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->logger->log('mPDF: Error general en test: ' . $e->getMessage());
            return new WP_Error('test_error', 'Error en test: ' . $e->getMessage());
        }
    }
}