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
     * Generar PDF de cotización
     */
    public function generar_pdf_cotizacion($cotizacion_id) {
        try {
            if (!$this->verificar_tcpdf()) {
                return new WP_Error('tcpdf_missing', __('TCPDF no está instalado. Ejecute: composer install en el directorio del plugin', 'modulo-ventas'));
            }
            
            if (!class_exists('TCPDF')) {
                return new WP_Error('tcpdf_not_loaded', __('No se pudo cargar la clase TCPDF', 'modulo-ventas'));
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
            $vendedor = get_userdata($cotizacion->vendedor_id);
            
            // Crear PDF
            $pdf = $this->crear_instancia_pdf();
            
            // Configurar documento
            $pdf->SetCreator('Módulo de Ventas');
            $pdf->SetAuthor($this->config['empresa_nombre']);
            $pdf->SetTitle('Cotización ' . $cotizacion->folio);
            $pdf->SetSubject('Cotización de venta');
            $pdf->SetKeywords('cotización, venta, presupuesto');
            
            // Agregar página
            $pdf->AddPage();
            
            // Generar contenido
            $this->generar_header($pdf, $cotizacion);
            $this->generar_info_empresa($pdf);
            $this->generar_info_cotizacion($pdf, $cotizacion, $cliente, $vendedor);
            $this->generar_tabla_productos($pdf, $items, $cotizacion);
            $this->generar_totales($pdf, $cotizacion);
            $this->generar_terminos($pdf, $cotizacion);
            
            // Generar archivo
            $filename = $this->generar_nombre_archivo($cotizacion);
            $filepath = $this->obtener_ruta_archivo($filename);
            
            // Asegurar que el directorio existe
            $this->crear_directorio_pdf();
            
            // Guardar PDF
            $pdf->Output($filepath, 'F');
            
            // Log de éxito
            if ($this->logger) {
                $this->logger->log("PDF generado para cotización {$cotizacion->folio}: {$filename}", 'info');
            }
            
            return $filepath;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('Error generando PDF: ' . $e->getMessage(), 'error');
            }
            return new WP_Error('pdf_generation_error', 'Error al generar PDF: ' . $e->getMessage());
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
            $pdf->Cell($w[2], $cell_height, '$' . number_format($item->precio, 0), 1, 0, 'R', $fill);
            
            // Descuento
            $descuento_texto = '';
            if ($item->descuento > 0) {
                if ($item->descuento_tipo === 'porcentaje') {
                    $descuento_texto = $item->descuento . '%';
                } else {
                    $descuento_texto = '$' . number_format($item->descuento, 0);
                }
            } else {
                $descuento_texto = '-';
            }
            
            $pdf->Cell($w[3], $cell_height, $descuento_texto, 1, 0, 'R', $fill);
            $pdf->Cell($w[4], $cell_height, '$' . number_format($item->subtotal, 0), 1, 1, 'R', $fill);
            
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
        
        // Descuento global si existe
        if ($cotizacion->descuento_global > 0) {
            $pdf->SetX($x_totales);
            $descuento_texto = __('Descuento:', 'modulo-ventas');
            if ($cotizacion->descuento_global_tipo === 'porcentaje') {
                $descuento_texto .= ' (' . $cotizacion->descuento_global . '%)';
            }
            $pdf->Cell($w_label, 6, $descuento_texto, 0, 0, 'L');
            
            // Calcular monto del descuento
            $monto_descuento = 0;
            if ($cotizacion->descuento_global_tipo === 'porcentaje') {
                $monto_descuento = $cotizacion->subtotal * ($cotizacion->descuento_global / 100);
            } else {
                $monto_descuento = $cotizacion->descuento_global;
            }
            
            $pdf->Cell($w_value, 6, '-$' . number_format($monto_descuento, 0), 0, 1, 'R');
        }
        
        // Envío si existe
        if ($cotizacion->costo_envio > 0) {
            $pdf->SetX($x_totales);
            $pdf->Cell($w_label, 6, __('Envío:', 'modulo-ventas'), 0, 0, 'L');
            $pdf->Cell($w_value, 6, '$' . number_format($cotizacion->costo_envio, 0), 0, 1, 'R');
        }
        
        // IVA si está incluido
        if ($cotizacion->incluir_iva) {
            $pdf->SetX($x_totales);
            $pdf->Cell($w_label, 6, __('IVA (19%):', 'modulo-ventas'), 0, 0, 'L');
            
            // Calcular IVA
            $base_iva = $cotizacion->total / 1.19;
            $iva = $cotizacion->total - $base_iva;
            
            $pdf->Cell($w_value, 6, '$' . number_format($iva, 0), 0, 1, 'R');
        }
        
        // Línea separadora
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetX($x_totales);
        $pdf->Line($x_totales, $pdf->GetY(), $x_totales + $w_label + $w_value, $pdf->GetY());
        $pdf->Ln(3);
        
        // Total
        $pdf->SetFont($this->config['fuente_principal'], 'B', 12);
        $pdf->SetX($x_totales);
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
}