<?php
/**
 * Clase para generar PDFs del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF {
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Configuración del PDF
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
        $this->db = new Modulo_Ventas_DB();
        
        // Obtener configuración
        $this->config = $this->obtener_configuracion();
        
        // Forzar carga de TCPDF al instanciar
        $this->forzar_carga_tcpdf();
    }
    
    /**
     * Verificar si TCPDF está disponible
     */
    private function verificar_tcpdf() {
        // Si ya está cargado, no hacer nada más
        if (class_exists('TCPDF')) {
            return true;
        }
        
        // Intentar cargar con autoload
        $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
            
            if (class_exists('TCPDF')) {
                return true;
            }
        }
        
        // Cargar manualmente como fallback
        $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
            
            if (class_exists('TCPDF')) {
                return true;
            }
        }
        
        // Log del error
        if ($this->logger) {
            $this->logger->log('TCPDF no se pudo cargar. Archivos presentes pero clase no disponible.', 'error');
        }
        
        return false;
    }
    
    /**
     * Incluir TCPDF
     */
    private function incluir_tcpdf() {
        // Este método ahora se ejecuta después de verificar_tcpdf()
        // por lo que TCPDF ya debería estar disponible
        
        if (!class_exists('TCPDF')) {
            // Último intento directo
            $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once $tcpdf_path;
            }
        }
        
        // Log para debug
        if ($this->logger) {
            if (class_exists('TCPDF')) {
                $this->logger->log('TCPDF cargado exitosamente', 'info');
            } else {
                $this->logger->log('Error: TCPDF no se pudo cargar después de incluir archivos', 'error');
            }
        }
    }

    /**
     * Forzar carga de TCPDF - NUEVO MÉTODO
     */
    private function forzar_carga_tcpdf() {
        if (class_exists('TCPDF')) {
            return; // Ya está cargado
        }
        
        // Múltiples intentos de carga
        $rutas_tcpdf = array(
            MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php',
            MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php'
        );
        
        foreach ($rutas_tcpdf as $ruta) {
            if (file_exists($ruta) && !class_exists('TCPDF')) {
                require_once $ruta;
                
                if (class_exists('TCPDF')) {
                    if ($this->logger) {
                        $this->logger->log("TCPDF cargado desde: {$ruta}", 'info');
                    }
                    break;
                }
            }
        }
        
        // Test final
        if (!class_exists('TCPDF') && $this->logger) {
            $this->logger->log('ADVERTENCIA: TCPDF no está disponible después de intentar cargarlo', 'warning');
        }
    }
    
    /**
     * Obtener configuración del PDF
     */
    private function obtener_configuracion() {
        // Usar configuración con valores por defecto seguros
        $defaults = array(
            'empresa_nombre' => get_bloginfo('name'),
            'empresa_direccion' => get_option('woocommerce_store_address', ''),
            'empresa_ciudad' => get_option('woocommerce_store_city', ''),
            'empresa_telefono' => '',
            'empresa_email' => get_option('admin_email'),
            'empresa_web' => get_option('siteurl'),
            'empresa_rut' => '',
            'empresa_logo' => '',
            'papel_tamano' => 'A4',
            'papel_orientacion' => 'P',
            'fuente_principal' => 'helvetica',
            'fuente_tamano' => 10,
            'color_primario' => '#2271b1',
            'margen_izquierdo' => 15,
            'margen_derecho' => 15,
            'margen_superior' => 27,
            'margen_inferior' => 25,
            'mostrar_footer' => true,
            'footer_texto' => __('Documento generado por', 'modulo-ventas') . ' ' . get_bloginfo('name'),
            'terminos_default' => __("• Esta cotización tiene una validez de 30 días desde la fecha de emisión.\n• Los precios incluyen IVA y están sujetos a cambios sin previo aviso.\n• Para confirmar el pedido, se requiere una señal del 50% del valor total.\n• Los tiempos de entrega se confirmarán al momento de la orden de compra.", 'modulo-ventas'),
            'nota_validez' => __('30 días', 'modulo-ventas'),
            'incluir_logo' => false, // Deshabilitado por defecto hasta que esté configurado
            'logo_posicion' => 'izquierda',
            'mostrar_qr' => false,
            'marca_agua' => false,
            'marca_agua_texto' => __('COTIZACIÓN', 'modulo-ventas')
        );
        
        // Intentar obtener configuración guardada
        $configuracion_guardada = get_option('mv_pdf_config', array());
        
        return wp_parse_args($configuracion_guardada, $defaults);
    }

    /**
     * Generar PDF de cotización usando plantillas personalizables
     */
    public function generar_pdf_cotizacion($cotizacion_id) {
        error_log("MODULO_VENTAS_PDF: Iniciando generación con plantillas para cotización {$cotizacion_id}");
        
        try {
            // PASO 1: Verificar TCPDF
            if (!$this->verificar_tcpdf()) {
                error_log('MODULO_VENTAS_PDF: TCPDF no verificado');
                return new WP_Error('tcpdf_missing', __('TCPDF no está instalado. Ejecute: composer install en el directorio del plugin', 'modulo-ventas'));
            }
            
            // PASO 2: Obtener plantilla activa para cotizaciones
            if (!class_exists('Modulo_Ventas_PDF_Templates')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates.php';
            }
            
            $templates_manager = Modulo_Ventas_PDF_Templates::get_instance();
            $plantilla = $templates_manager->obtener_plantilla_activa('cotizacion');
            
            if (!$plantilla) {
                error_log('MODULO_VENTAS_PDF: No hay plantilla activa para cotizaciones, usando método tradicional');
                return $this->generar_pdf_cotizacion_tradicional($cotizacion_id);
            }
            
            error_log("MODULO_VENTAS_PDF: Usando plantilla: {$plantilla->nombre} (ID: {$plantilla->id})");
            
            // PASO 3: Cargar procesador de plantillas
            if (!class_exists('Modulo_Ventas_PDF_Template_Processor')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            }
            
            $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
            
            // PASO 4: Procesar plantilla con datos reales
            $html_documento = $processor->procesar_plantilla($plantilla, $cotizacion_id, 'cotizacion');
            
            if (is_wp_error($html_documento)) {
                error_log('MODULO_VENTAS_PDF: Error procesando plantilla: ' . $html_documento->get_error_message());
                return $html_documento;
            }
            
            error_log('MODULO_VENTAS_PDF: Plantilla procesada exitosamente, generando PDF...');
            
            // PASO 5: Convertir HTML a PDF usando TCPDF
            $pdf_path = $this->convertir_html_a_pdf($html_documento, $cotizacion_id, $plantilla);
            
            if (is_wp_error($pdf_path)) {
                error_log('MODULO_VENTAS_PDF: Error convirtiendo a PDF: ' . $pdf_path->get_error_message());
                return $pdf_path;
            }
            
            error_log("MODULO_VENTAS_PDF: PDF generado exitosamente: {$pdf_path}");
            return $pdf_path;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS_PDF: Exception en generación: ' . $e->getMessage());
            error_log('MODULO_VENTAS_PDF: Exception trace: ' . $e->getTraceAsString());
            return new WP_Error('pdf_generation_error', $e->getMessage());
        }
    }
    
    /*
     * Convertir HTML procesado a PDF (Reemplazado al final)
     *
    private function convertir_html_a_pdf($html_content, $cotizacion_id, $plantilla = null) {
        try {
            // Cargar TCPDF
            if (!class_exists('TCPDF')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            }
            
            // Obtener configuración de la plantilla
            $config_plantilla = array();
            if ($plantilla && !empty($plantilla->configuracion)) {
                $config_plantilla = json_decode($plantilla->configuracion, true) ?: array();
            }
            
            // Configuración del PDF
            $orientacion = isset($config_plantilla['papel_orientacion']) ? $config_plantilla['papel_orientacion'] : 'P';
            $tamano = isset($config_plantilla['papel_tamano']) ? $config_plantilla['papel_tamano'] : 'A4';
            $margenes = isset($config_plantilla['margenes']) ? $config_plantilla['margenes'] : array(
                'superior' => 15,
                'inferior' => 15,
                'izquierdo' => 15,
                'derecho' => 15
            );
            
            // Crear instancia TCPDF
            $pdf = new TCPDF($orientacion, PDF_UNIT, $tamano, true, 'UTF-8', false);
            
            // Configurar metadatos
            $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
            $titulo = 'Cotización ' . ($cotizacion ? $cotizacion->folio : $cotizacion_id);
            
            $pdf->SetCreator('Módulo de Ventas');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle($titulo);
            $pdf->SetSubject('Cotización');
            
            // Configurar márgenes
            $pdf->SetMargins(
                $margenes['izquierdo'], 
                $margenes['superior'], 
                $margenes['derecho']
            );
            $pdf->SetAutoPageBreak(TRUE, $margenes['inferior']);
            
            // Desactivar header y footer predeterminados
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Agregar página
            $pdf->AddPage();
            
            // Escribir contenido HTML
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            // Generar ruta de archivo
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';
            
            // Crear directorio si no existe
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
                
                // Crear .htaccess para proteger archivos
                $htaccess_content = "Order deny,allow\nDeny from all\n";
                file_put_contents($pdf_dir . '/.htaccess', $htaccess_content);
            }
            
            // Generar nombre único
            $timestamp = current_time('Y-m-d_H-i-s');
            $filename = "cotizacion-{$cotizacion_id}_{$timestamp}.pdf";
            $pdf_path = $pdf_dir . '/' . $filename;
            
            // Guardar PDF
            $pdf->Output($pdf_path, 'F');
            
            // Verificar que el archivo se creó
            if (!file_exists($pdf_path) || filesize($pdf_path) == 0) {
                return new WP_Error('pdf_save_error', __('Error al guardar el archivo PDF', 'modulo-ventas'));
            }
            
            error_log("MODULO_VENTAS_PDF: PDF guardado en: {$pdf_path}");
            return $pdf_path;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS_PDF: Error en convertir_html_a_pdf: ' . $e->getMessage());
            return new WP_Error('pdf_conversion_error', $e->getMessage());
        }
    }*/

    /**
     * Método de fallback: Generar PDF con método tradicional si no hay plantillas
     */
    private function generar_pdf_cotizacion_tradicional($cotizacion_id) {
        error_log("MODULO_VENTAS_PDF: Usando método tradicional para cotización {$cotizacion_id}");
        
        // Aquí mantener el código original de generación que ya funciona
        // Este será el fallback si las plantillas fallan
        
        try {
            if (!class_exists('TCPDF')) {
                require_once MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            }
            
            // Obtener datos
            $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
            if (!$cotizacion) {
                return new WP_Error('invalid_quote', __('Cotización no encontrada', 'modulo-ventas'));
            }
            
            $cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
            if (!$cliente) {
                return new WP_Error('invalid_client', __('Cliente no encontrado', 'modulo-ventas'));
            }
            
            $items = $this->db->obtener_items_cotizacion($cotizacion_id);
            
            // Generar PDF con diseño básico
            $pdf = new TCPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false);
            
            $pdf->SetCreator('Módulo de Ventas');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle('Cotización ' . $cotizacion->folio);
            
            $pdf->SetMargins(15, 27, 15);
            $pdf->SetAutoPageBreak(TRUE, 25);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            $pdf->AddPage();
            
            // Contenido básico
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->Cell(0, 15, 'COTIZACIÓN', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 8, 'N° ' . $cotizacion->folio, 0, 1, 'C');
            $pdf->Ln(10);
            
            // Info cliente
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Cliente: ' . $cliente->razon_social, 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, 'RUT: ' . $cliente->rut, 0, 1, 'L');
            $pdf->Ln(5);
            
            // Tabla básica de productos
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(80, 8, 'Descripción', 1, 0, 'L');
            $pdf->Cell(20, 8, 'Cant.', 1, 0, 'C');
            $pdf->Cell(30, 8, 'Precio', 1, 0, 'R');
            $pdf->Cell(30, 8, 'Total', 1, 1, 'R');
            
            $pdf->SetFont('helvetica', '', 9);
            foreach ($items as $item) {
                $pdf->Cell(80, 6, $item->descripcion, 1, 0, 'L');
                $pdf->Cell(20, 6, $item->cantidad, 1, 0, 'C');
                $pdf->Cell(30, 6, '$' . number_format($item->precio_unitario, 0, ',', '.'), 1, 0, 'R');
                $pdf->Cell(30, 6, '$' . number_format($item->subtotal, 0, ',', '.'), 1, 1, 'R');
            }
            
            // Total
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(130, 8, 'TOTAL:', 0, 0, 'R');
            $pdf->Cell(30, 8, '$' . number_format($cotizacion->total, 0, ',', '.'), 1, 1, 'R');
            
            // Guardar
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';
            
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            
            $timestamp = current_time('Y-m-d_H-i-s');
            $filename = "cotizacion-{$cotizacion_id}_{$timestamp}.pdf";
            $pdf_path = $pdf_dir . '/' . $filename;
            
            $pdf->Output($pdf_path, 'F');
            
            if (!file_exists($pdf_path)) {
                return new WP_Error('pdf_save_error', __('Error al guardar PDF', 'modulo-ventas'));
            }
            
            return $pdf_path;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS_PDF: Error en método tradicional: ' . $e->getMessage());
            return new WP_Error('pdf_traditional_error', $e->getMessage());
        }
    }
    
    /**
     * Crear instancia de TCPDF configurada
     */
    private function crear_instancia_pdf() {
        $pdf = new TCPDF(
            $this->config['papel_orientacion'],
            PDF_UNIT,
            $this->config['papel_tamano'],
            true,
            'UTF-8',
            false
        );
        
        // Configuración básica
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(
            $this->config['margen_izquierdo'],
            $this->config['margen_superior'],
            $this->config['margen_derecho']
        );
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, $this->config['margen_inferior']);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Fuente por defecto
        $pdf->SetFont($this->config['fuente_principal'], '', $this->config['fuente_tamano']);
        
        // Desactivar header y footer por defecto
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter($this->config['mostrar_footer']);
        
        if ($this->config['mostrar_footer']) {
            $pdf->setFooterData(array(0,64,0), array(0,64,128));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        }
        
        return $pdf;
    }
    
    /**
     * Generar header del documento
     */
    private function generar_header($pdf, $cotizacion) {
        $y_inicial = $pdf->GetY();
        
        // Si hay logo, agregarlo
        if ($this->config['incluir_logo'] && !empty($this->config['empresa_logo'])) {
            $logo_path = mv_get_logo_path();
            
            if ($logo_path && file_exists($logo_path)) {
                // Obtener dimensiones de la imagen
                $image_info = getimagesize($logo_path);
                $image_width = $image_info[0];
                $image_height = $image_info[1];
                
                // Calcular tamaño proporcional (máximo 40mm de alto)
                $max_height = 40;
                $scale = $max_height / ($image_height * 0.264583); // Convertir px a mm
                $logo_width = ($image_width * 0.264583) * $scale;
                $logo_height = $max_height;
                
                // Posición del logo
                if ($this->config['logo_posicion'] === 'derecha') {
                    $logo_x = 195 - $logo_width;
                } else {
                    $logo_x = 15;
                }
                
                $pdf->Image($logo_path, $logo_x, $y_inicial, $logo_width, $logo_height);
                
                // Ajustar posición del título según posición del logo
                if ($this->config['logo_posicion'] === 'derecha') {
                    $titulo_x = 15;
                    $titulo_w = 195 - $logo_width - 20;
                } else {
                    $titulo_x = 15 + $logo_width + 10;
                    $titulo_w = 195 - $logo_width - 25;
                }
                
                $pdf->SetXY($titulo_x, $y_inicial + 5);
            } else {
                $titulo_x = 15;
                $titulo_w = 180;
                $pdf->SetXY($titulo_x, $y_inicial);
            }
        } else {
            $titulo_x = 15;
            $titulo_w = 180;
        }
        
        // Convertir color primario de hex a RGB
        $color_rgb = $this->hex_to_rgb($this->config['color_primario']);
        $pdf->SetTextColor($color_rgb[0], $color_rgb[1], $color_rgb[2]);
        
        $pdf->SetFont($this->config['fuente_principal'], 'B', 20);
        $pdf->Cell($titulo_w, 15, __('COTIZACIÓN', 'modulo-ventas'), 0, 1, 'C');
        
        $pdf->SetFont($this->config['fuente_principal'], '', 14);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetX($titulo_x);
        $pdf->Cell($titulo_w, 8, 'N° ' . $cotizacion->folio, 0, 1, 'C');
        
        $pdf->Ln(15);
    }

    /**
     * Convertir color hexadecimal a RGB
     */
    private function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * Generar información de la empresa
     */
    private function generar_info_empresa($pdf) {
        $pdf->SetFont($this->config['fuente_principal'], 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        
        // Nombre de la empresa
        $pdf->Cell(0, 8, $this->config['empresa_nombre'], 0, 1, 'L');
        
        // RUT si está configurado
        if (!empty($this->config['empresa_rut'])) {
            $pdf->SetFont($this->config['fuente_principal'], '', 10);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell(0, 6, __('RUT:', 'modulo-ventas') . ' ' . $this->config['empresa_rut'], 0, 1, 'L');
        }
        
        $pdf->SetFont($this->config['fuente_principal'], '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        // Dirección
        if (!empty($this->config['empresa_direccion'])) {
            $direccion = $this->config['empresa_direccion'];
            if (!empty($this->config['empresa_ciudad'])) {
                $direccion .= ', ' . $this->config['empresa_ciudad'];
            }
            $pdf->Cell(0, 6, $direccion, 0, 1, 'L');
        }
        
        // Contacto
        $contacto = array();
        if (!empty($this->config['empresa_telefono'])) {
            $contacto[] = __('Tel:', 'modulo-ventas') . ' ' . $this->config['empresa_telefono'];
        }
        if (!empty($this->config['empresa_email'])) {
            $contacto[] = __('Email:', 'modulo-ventas') . ' ' . $this->config['empresa_email'];
        }
        if (!empty($this->config['empresa_web'])) {
            $contacto[] = __('Web:', 'modulo-ventas') . ' ' . str_replace(array('http://', 'https://'), '', $this->config['empresa_web']);
        }
        
        if (!empty($contacto)) {
            $pdf->Cell(0, 6, implode(' | ', $contacto), 0, 1, 'L');
        }
        
        $pdf->Ln(8);
    }
    
    /**
     * Generar información de cotización y cliente
     */
    private function generar_info_cotizacion($pdf, $cotizacion, $cliente, $vendedor) {
        // Línea separadora
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(8);
        
        // Información en dos columnas
        $pdf->SetFont($this->config['fuente_principal'], 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        
        // Columna izquierda - Cliente
        $pdf->Cell(95, 6, __('CLIENTE', 'modulo-ventas'), 0, 0, 'L');
        
        // Columna derecha - Cotización
        $pdf->Cell(95, 6, __('INFORMACIÓN DE COTIZACIÓN', 'modulo-ventas'), 0, 1, 'L');
        
        $pdf->SetFont($this->config['fuente_principal'], '', 10);
        $pdf->SetTextColor(60, 60, 60);
        
        // Datos del cliente
        $y_inicial = $pdf->GetY();
        $pdf->Cell(95, 5, $cliente->razon_social, 0, 1, 'L');
        
        if (!empty($cliente->rut)) {
            $pdf->Cell(95, 5, __('RUT:', 'modulo-ventas') . ' ' . $cliente->rut, 0, 1, 'L');
        }
        
        if (!empty($cliente->direccion_facturacion)) {
            $pdf->Cell(95, 5, $cliente->direccion_facturacion, 0, 1, 'L');
        }
        
        if (!empty($cliente->telefono)) {
            $pdf->Cell(95, 5, __('Tel:', 'modulo-ventas') . ' ' . $cliente->telefono, 0, 1, 'L');
        }
        
        if (!empty($cliente->email)) {
            $pdf->Cell(95, 5, $cliente->email, 0, 1, 'L');
        }
        
        // Datos de la cotización (columna derecha)
        $pdf->SetXY(110, $y_inicial);
        
        $pdf->Cell(95, 5, __('Fecha:', 'modulo-ventas') . ' ' . date_i18n(get_option('date_format'), strtotime($cotizacion->fecha)), 0, 1, 'L');
        
        if (!empty($cotizacion->fecha_expiracion)) {
            $pdf->SetX(110);
            $pdf->Cell(95, 5, __('Válida hasta:', 'modulo-ventas') . ' ' . date_i18n(get_option('date_format'), strtotime($cotizacion->fecha_expiracion)), 0, 1, 'L');
        }
        
        if ($vendedor) {
            $pdf->SetX(110);
            $pdf->Cell(95, 5, __('Vendedor:', 'modulo-ventas') . ' ' . $vendedor->display_name, 0, 1, 'L');
        }
        
        if (!empty($cotizacion->condiciones_pago)) {
            $pdf->SetX(110);
            $pdf->Cell(95, 5, __('Condiciones:', 'modulo-ventas') . ' ' . $cotizacion->condiciones_pago, 0, 1, 'L');
        }
        
        $pdf->Ln(10);
    }
    
    /**
     * Generar tabla de productos
     */
    private function generar_tabla_productos($pdf, $items, $cotizacion) {
        // Headers de la tabla
        $pdf->SetFont($this->config['fuente_principal'], 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(200, 200, 200);
        
        // Anchos de columnas
        $w = array(80, 20, 25, 25, 30); // Descripción, Cant, Precio, Desc, Subtotal
        
        $pdf->Cell($w[0], 8, __('Descripción', 'modulo-ventas'), 1, 0, 'L', 1);
        $pdf->Cell($w[1], 8, __('Cant.', 'modulo-ventas'), 1, 0, 'C', 1);
        $pdf->Cell($w[2], 8, __('Precio', 'modulo-ventas'), 1, 0, 'R', 1);
        $pdf->Cell($w[3], 8, __('Desc.', 'modulo-ventas'), 1, 0, 'R', 1);
        $pdf->Cell($w[4], 8, __('Subtotal', 'modulo-ventas'), 1, 1, 'R', 1);
        
        // Contenido de la tabla
        $pdf->SetFont($this->config['fuente_principal'], '', 9);
        $pdf->SetFillColor(255, 255, 255);
        
        $fill = false;
        foreach ($items as $item) {
            $nombre = $item->nombre;
            if (!empty($item->sku)) {
                $nombre = '[' . $item->sku . '] ' . $nombre;
            }
            
            // Calcular altura necesaria para el texto
            $height = $pdf->getStringHeight($w[0], $nombre);
            $cell_height = max(6, $height);
            
            $pdf->Cell($w[0], $cell_height, $nombre, 1, 0, 'L', $fill);
            $pdf->Cell($w[1], $cell_height, number_format($item->cantidad, 0), 1, 0, 'C', $fill);
            
            // CORRECCIÓN: Verificar que precio_unitario existe - LÍNEA PROBLEMÁTICA 605
            $precio = isset($item->precio_unitario) ? $item->precio_unitario : 
                    (isset($item->precio) ? $item->precio : 0);
            
            $pdf->Cell($w[2], $cell_height, '$' . number_format($precio, 0), 1, 0, 'R', $fill);
            
            // CORRECCIÓN: Verificar propiedades de descuento - LÍNEA PROBLEMÁTICA 609
            $descuento_texto = '';
            $descuento_monto = isset($item->descuento_monto) ? $item->descuento_monto : 0;
            $descuento_porcentaje = isset($item->descuento_porcentaje) ? $item->descuento_porcentaje : 0;
            $tipo_descuento = isset($item->tipo_descuento) ? $item->tipo_descuento : 'monto';
            
            // Priorizar descuento porcentaje si existe
            if ($descuento_porcentaje > 0) {
                $descuento_texto = $descuento_porcentaje . '%';
            } elseif ($descuento_monto > 0) {
                $descuento_texto = '$' . number_format($descuento_monto, 0);
            } else {
                $descuento_texto = '-';
            }
            
            $pdf->Cell($w[3], $cell_height, $descuento_texto, 1, 0, 'R', $fill);
            
            // CORRECCIÓN: Verificar subtotal
            $subtotal = isset($item->subtotal) ? $item->subtotal : 
                    ($precio * $item->cantidad);
            
            $pdf->Cell($w[4], $cell_height, '$' . number_format($subtotal, 0), 1, 1, 'R', $fill);
            
            $fill = !$fill;
        }
        
        $pdf->Ln(5);
    }
    
    /**
     * Generar totales
     */
    private function generar_totales($pdf, $cotizacion) {
        // Posición para los totales (lado derecho)
        $x_totales = 130;
        $w_label = 35;
        $w_value = 35;
        
        $pdf->SetFont($this->config['fuente_principal'], '', 10);
        $pdf->SetTextColor(0, 0, 0);
        
        // Subtotal
        $pdf->SetXY($x_totales, $pdf->GetY());
        $pdf->Cell($w_label, 6, __('Subtotal:', 'modulo-ventas'), 0, 0, 'L');
        $pdf->Cell($w_value, 6, '$' . number_format($cotizacion->subtotal, 0), 0, 1, 'R');
        
        // CORRECCIÓN: Verificar descuento global - LÍNEA PROBLEMÁTICA 646
        $descuento_global = isset($cotizacion->descuento_global) ? $cotizacion->descuento_global : 0;
        $descuento_global_tipo = isset($cotizacion->descuento_global_tipo) ? $cotizacion->descuento_global_tipo : 'monto';
        
        if ($descuento_global > 0) {
            $pdf->SetX($x_totales);
            $descuento_texto = __('Descuento:', 'modulo-ventas');
            if ($descuento_global_tipo === 'porcentaje') {
                $descuento_texto .= ' (' . $descuento_global . '%)';
            }
            $pdf->Cell($w_label, 6, $descuento_texto, 0, 0, 'L');
            
            // Calcular monto del descuento
            $monto_descuento = 0;
            if ($descuento_global_tipo === 'porcentaje') {
                $monto_descuento = $cotizacion->subtotal * ($descuento_global / 100);
            } else {
                $monto_descuento = $descuento_global;
            }
            
            $pdf->Cell($w_value, 6, '-$' . number_format($monto_descuento, 0), 0, 1, 'R');
        }
        
        // Envío si existe
        $costo_envio = isset($cotizacion->costo_envio) ? $cotizacion->costo_envio : 0;
        if ($costo_envio > 0) {
            $pdf->SetX($x_totales);
            $pdf->Cell($w_label, 6, __('Envío:', 'modulo-ventas'), 0, 0, 'L');
            $pdf->Cell($w_value, 6, '$' . number_format($costo_envio, 0), 0, 1, 'R');
        }
        
        // CORRECCIÓN: Verificar IVA - LÍNEA PROBLEMÁTICA 673
        $incluir_iva = isset($cotizacion->incluir_iva) ? $cotizacion->incluir_iva : false;
        if ($incluir_iva) {
            $pdf->SetX($x_totales);
            $pdf->Cell($w_label, 6, __('IVA (19%):', 'modulo-ventas'), 0, 0, 'L');
            
            // Calcular IVA
            $base_iva = $cotizacion->total / 1.19;
            $iva = $cotizacion->total - $base_iva;
            
            $pdf->Cell($w_value, 6, '$' . number_format($iva, 0), 0, 1, 'R');
        }
        
        // Línea separadora
        $pdf->SetX($x_totales);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line($x_totales, $pdf->GetY(), $x_totales + $w_label + $w_value, $pdf->GetY());
        $pdf->Ln(2);
        
        // Total
        $pdf->SetX($x_totales);
        $pdf->SetFont($this->config['fuente_principal'], 'B', 12);
        $pdf->Cell($w_label, 8, __('TOTAL:', 'modulo-ventas'), 0, 0, 'L');
        $pdf->Cell($w_value, 8, '$' . number_format($cotizacion->total, 0), 0, 1, 'R');
        
        $pdf->Ln(10);
    }
    
    /**
     * Generar términos y condiciones
     */
    private function generar_terminos($pdf, $cotizacion) {
        $terminos_a_mostrar = '';
        
        // Usar términos específicos de la cotización si existen
        if (!empty($cotizacion->observaciones)) {
            $terminos_a_mostrar = $cotizacion->observaciones;
        } elseif (!empty($cotizacion->terminos_condiciones)) {
            $terminos_a_mostrar = $cotizacion->terminos_condiciones;
        } else {
            // Usar términos por defecto de la configuración
            $terminos_a_mostrar = $this->config['terminos_default'];
        }
        
        if (!empty($terminos_a_mostrar)) {
            $pdf->SetFont($this->config['fuente_principal'], 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 6, __('TÉRMINOS Y CONDICIONES', 'modulo-ventas'), 0, 1, 'L');
            
            $pdf->SetFont($this->config['fuente_principal'], '', 9);
            $pdf->SetTextColor(60, 60, 60);
            
            // Reemplazar la validez por defecto si está configurada
            $terminos_procesados = str_replace(
                '30 días',
                $this->config['nota_validez'],
                $terminos_a_mostrar
            );
            
            $pdf->MultiCell(0, 5, $terminos_procesados, 0, 'L');
        }
        
        // Agregar marca de agua si está habilitada
        if ($this->config['marca_agua']) {
            $this->agregar_marca_agua($pdf);
        }
    }

    /**
     * Agregar marca de agua al documento
     */
    private function agregar_marca_agua($pdf) {
        $pdf->StartTransform();
        $pdf->Rotate(45, 105, 148); // Rotar 45 grados en el centro de A4
        
        $pdf->SetFont($this->config['fuente_principal'], 'B', 50);
        $pdf->SetTextColor(240, 240, 240); // Gris muy claro
        $pdf->Text(50, 150, $this->config['marca_agua_texto']);
        
        $pdf->StopTransform();
    }
    
    /**
     * Generar nombre del archivo
     */
    private function generar_nombre_archivo($cotizacion) {
        $fecha = date('Y-m-d');
        $folio = preg_replace('/[^a-zA-Z0-9]/', '', $cotizacion->folio);
        return "cotizacion_{$folio}_{$fecha}.pdf";
    }
    
    /**
     * Obtener ruta completa del archivo
     */
    private function obtener_ruta_archivo($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/modulo-ventas/pdfs/' . $filename;
    }
    
    /**
     * Crear directorio para PDFs
     */
    private function crear_directorio_pdf() {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
            
            // Crear archivo .htaccess para proteger el directorio
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($pdf_dir . '/.htaccess', $htaccess_content);
        }
        
        return $pdf_dir;
    }
    
    /**
     * Obtener URL del PDF generado
     */
    public function obtener_url_pdf($filename) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/modulo-ventas/pdfs/' . $filename;
    }
    
    /**
     * Limpiar PDFs antiguos
     */
    public function limpiar_pdfs_antiguos($dias = 30) {
        $pdf_dir = $this->crear_directorio_pdf();
        $archivos = glob($pdf_dir . '/*.pdf');
        $fecha_limite = strtotime("-{$dias} days");
        $eliminados = 0;
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $fecha_limite) {
                if (unlink($archivo)) {
                    $eliminados++;
                }
            }
        }
        
        if ($eliminados > 0) {
            $this->logger->log("PDFs limpiados: {$eliminados} archivos eliminados", 'info');
        }
        
        return $eliminados;
    }
    
    /**
     * Generar PDF de reporte
     */
    public function generar_pdf_reporte($tipo, $parametros = array()) {
        if (!$this->verificar_tcpdf()) {
            return new WP_Error('tcpdf_missing', __('TCPDF no está instalado', 'modulo-ventas'));
        }
        
        // Por implementar en futura versión
        return new WP_Error('in_development', __('Generación de reportes en desarrollo', 'modulo-ventas'));
    }

    /**
     * Generar PDF usando sistema de plantillas HTML (NUEVO MÉTODO)
     */
    public function generar_pdf_desde_plantilla($cotizacion_id) {
        error_log("MODULO_VENTAS_PDF: Generando PDF desde plantilla para cotización {$cotizacion_id}");
        
        try {
            // 1. Verificar TCPDF
            if (!$this->verificar_tcpdf()) {
                return new WP_Error('tcpdf_missing', __('TCPDF no está instalado', 'modulo-ventas'));
            }
            
            // 2. Obtener HTML procesado del sistema de plantillas
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-templates.php';
            require_once MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-template-processor.php';
            
            $pdf_templates = Modulo_Ventas_PDF_Templates::get_instance();
            $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
            
            // Obtener plantilla activa
            $plantilla = $pdf_templates->obtener_plantilla_activa('cotizacion');
            if (!$plantilla) {
                return new WP_Error('no_template', __('No hay plantilla activa para cotizaciones', 'modulo-ventas'));
            }
            
            error_log("MODULO_VENTAS_PDF: Plantilla obtenida: {$plantilla->nombre}");
            
            // Procesar plantilla para obtener HTML completo
            $documento_html = $processor->procesar_plantilla($plantilla, $cotizacion_id, 'cotizacion');
            
            error_log("MODULO_VENTAS_PDF: HTML procesado (" . strlen($documento_html) . " caracteres)");
            
            // 3. Convertir HTML a PDF usando TCPDF
            $pdf_path = $this->convertir_html_a_pdf($documento_html, $cotizacion_id);
            
            if (is_wp_error($pdf_path)) {
                return $pdf_path;
            }
            
            error_log("MODULO_VENTAS_PDF: PDF generado exitosamente: {$pdf_path}");
            return $pdf_path;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS_PDF: Error en generar_pdf_desde_plantilla: ' . $e->getMessage());
            return new WP_Error('pdf_generation_error', 'Error generando PDF: ' . $e->getMessage());
        }
    }

    /**
     * Convertir HTML a PDF usando TCPDF (NUEVO MÉTODO)
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
            
            // Configurar TCPDF optimizado para HTML
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Configuración del documento
            $pdf->SetCreator('Módulo de Ventas');
            $pdf->SetAuthor(get_bloginfo('name'));
            $pdf->SetTitle('Cotización ' . ($cotizacion ? $cotizacion->folio : $cotizacion_id));
            $pdf->SetSubject('Cotización generada desde plantilla');
            
            // Configurar página para mejor rendering de HTML
            $pdf->SetMargins(10, 10, 10);  // Márgenes más pequeños
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            $pdf->SetAutoPageBreak(TRUE, 15);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Desactivar header y footer por defecto
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Configuraciones especiales para mejor rendering de CSS
            $pdf->setFontSubsetting(true);
            $pdf->SetFont('helvetica', '', 11);
            
            // Agregar página
            $pdf->AddPage();
            
            // Procesar HTML para compatibilidad con TCPDF
            $html_optimizado = $this->optimizar_html_para_tcpdf($html_content);
            
            error_log("MODULO_VENTAS_PDF: HTML optimizado para TCPDF");
            
            // Convertir HTML a PDF
            $pdf->writeHTML($html_optimizado, true, false, true, false, '');
            
            // Crear directorio si no existe
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';
            
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            
            // Generar nombre único para el archivo
            $timestamp = current_time('Y-m-d_H-i-s');
            $filename = 'cotizacion-' . $cotizacion_id . '_' . $timestamp . '.pdf';
            $filepath = $pdf_dir . '/' . $filename;
            
            // Guardar PDF
            $pdf->Output($filepath, 'F');
            
            // Verificar que se creó el archivo
            if (!file_exists($filepath)) {
                return new WP_Error('save_error', __('Error al guardar archivo PDF', 'modulo-ventas'));
            }
            
            // Devolver URL del archivo
            $file_url = $upload_dir['baseurl'] . '/modulo-ventas/pdfs/' . $filename;
            
            error_log("MODULO_VENTAS_PDF: PDF guardado en: {$filepath}");
            
            return $file_url;
            
        } catch (Exception $e) {
            error_log('MODULO_VENTAS_PDF: Error en convertir_html_a_pdf: ' . $e->getMessage());
            return new WP_Error('conversion_error', 'Error convirtiendo HTML a PDF: ' . $e->getMessage());
        }
    }

    /**
     * Optimizar HTML para compatibilidad con TCPDF (NUEVO MÉTODO)
     */
    private function optimizar_html_para_tcpdf($html_content) {
        error_log("MODULO_VENTAS_PDF: Iniciando optimización HTML para TCPDF");
        
        // Extraer contenido del body y CSS
        preg_match('/<body[^>]*>(.*?)<\/body>/is', $html_content, $matches);
        $body_content = isset($matches[1]) ? $matches[1] : $html_content;
        
        preg_match('/<style[^>]*>(.*?)<\/style>/is', $html_content, $css_matches);
        $css_content = isset($css_matches[1]) ? $css_matches[1] : '';
        
        // LIMPIEZA AGRESIVA
        error_log("MODULO_VENTAS_PDF: Limpiando CSS problemático");
        
        // 1. Limpiar CSS problemático
        $css_limpio = $this->limpiar_css_tcpdf($css_content);
        
        // 2. Convertir flexbox a estructura de tabla
        error_log("MODULO_VENTAS_PDF: Convirtiendo flexbox a tablas");
        $body_content = $this->convertir_flexbox_simple($body_content);
        
        // 3. Limpiar HTML problemático
        $body_content = $this->limpiar_html_tcpdf($body_content);
        
        // Reconstruir HTML limpio
        $html_final = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>' . $css_limpio . '</style>
    </head>
    <body>' . $body_content . '</body>
    </html>';
        
        error_log("MODULO_VENTAS_PDF: HTML optimizado completado");
        return $html_final;
    }

    /**
     * AGREGAR este método para limpiar CSS
     */
    private function limpiar_css_tcpdf($css_content) {
        // Eliminar propiedades problemáticas específicas
        $problematicas = array(
            '/display\s*:\s*flex[^;]*;/i' => '',
            '/display\s*:\s*grid[^;]*;/i' => '',
            '/justify-content\s*:[^;]*;/i' => '',
            '/align-items\s*:[^;]*;/i' => '',
            '/flex\s*:[^;]*;/i' => '',
            '/transform\s*:[^;]*;/i' => '',
            '/border-radius\s*:[^;]*;/i' => '',
            '/box-shadow\s*:[^;]*;/i' => '',
            '/text-shadow\s*:[^;]*;/i' => '',
            '/transition\s*:[^;]*;/i' => '',
            '/animation\s*:[^;]*;/i' => '',
            '/@media[^{]*\{[^{}]*(\{[^{}]*\}[^{}]*)*\}/i' => ''
        );
        
        foreach ($problematicas as $patron => $reemplazo) {
            $css_content = preg_replace($patron, $reemplazo, $css_content);
        }
        
        // CSS básico y seguro
        $css_base = '
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 15px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #333; padding: 6px; font-size: 10px; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .header-simple { width: 100%; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            .empresa-simple { width: 60%; display: inline-block; vertical-align: top; }
            .cotizacion-simple { width: 35%; display: inline-block; vertical-align: top; text-align: right; }
            .cliente-simple { background-color: #f8f8f8; padding: 10px; margin-bottom: 15px; }
            .totales-simple { text-align: right; margin-top: 15px; }
            h1 { font-size: 18px; margin: 0 0 10px 0; }
            h2 { font-size: 16px; margin: 0 0 10px 0; }
            h3 { font-size: 14px; margin: 0 0 10px 0; }
            p { margin: 0 0 5px 0; }
        ';
        
        return $css_base . ' ' . $css_content;
    }

    /**
     * AGREGAR este método para convertir flexbox
     */
    private function convertir_flexbox_simple($html_content) {
        // Convertir header con flexbox a estructura simple
        $html_content = preg_replace(
            '/<div class="header"[^>]*>/i',
            '<div class="header-simple">',
            $html_content
        );
        
        // Convertir elementos dentro del header
        $html_content = preg_replace(
            '/<div class="empresa-info"[^>]*>/i',
            '<div class="empresa-simple">',
            $html_content
        );
        
        $html_content = preg_replace(
            '/<div class="cotizacion-info"[^>]*>/i',
            '<div class="cotizacion-simple">',
            $html_content
        );
        
        // Convertir sección cliente
        $html_content = preg_replace(
            '/<div class="cliente-seccion"[^>]*>/i',
            '<div class="cliente-simple">',
            $html_content
        );
        
        $html_content = preg_replace(
            '/<div class="cliente-datos"[^>]*>/i',
            '<div>',
            $html_content
        );
        
        // Simplificar totales
        $html_content = preg_replace(
            '/<div class="totales-seccion"[^>]*>/i',
            '<div class="totales-simple">',
            $html_content
        );
        
        return $html_content;
    }

    /**
     * AGREGAR este método para limpiar HTML
     */
    private function limpiar_html_tcpdf($html_content) {
        // Remover scripts y links
        $html_content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html_content);
        $html_content = preg_replace('/<link[^>]*>/i', '', $html_content);
        
        // Simplificar divs complejos
        $html_content = preg_replace('/<div class="dato-linea"[^>]*>/i', '<p>', $html_content);
        $html_content = str_replace('</div>', '</p>', $html_content);
        
        // Limpiar clases CSS complejas que no necesitamos
        $html_content = preg_replace('/class="[^"]*cotizacion-header[^"]*"/i', 'style="background: #f0f0f0; padding: 10px; text-align: center;"', $html_content);
        $html_content = preg_replace('/class="[^"]*total-fila[^"]*"/i', 'style="padding: 5px 0; border-bottom: 1px solid #ccc;"', $html_content);
        
        return $html_content;
    }
}