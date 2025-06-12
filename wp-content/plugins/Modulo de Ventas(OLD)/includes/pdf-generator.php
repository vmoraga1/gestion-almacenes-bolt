<?php
if (!defined('ABSPATH')) {
    exit;
}

$tcpdf_path = VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdf_path)) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Error: TCPDF no está instalado correctamente. Por favor, instale la librería TCPDF en el directorio vendor/tecnickcom/tcpdf.</p></div>';
    });
    return;
}

require_once($tcpdf_path);
if (!class_exists('TCPDF')) {
    return;
}

require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php');

class Ventas_PDF_Generator extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 15);
        $this->Cell(0, 10, get_bloginfo('name'), 0, 1, 'C');
        
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, get_option('woocommerce_store_address'), 0, 1, 'C');
        $this->Cell(0, 5, 'Tel: ' . get_option('woocommerce_store_phone'), 0, 1, 'C');
        
        $this->Ln(10);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

function generar_pdf_cotizacion($cotizacion_id) {
    $cotizacion = get_cotizacion($cotizacion_id);
    if (!$cotizacion) {
        wp_die('Cotización no encontrada');
    }

    $cliente = get_user_by('id', $cotizacion->cliente_id);
    $items = get_items_cotizacion($cotizacion_id);

    // Crear nuevo documento PDF
    $pdf = new Ventas_PDF_Generator(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Configurar documento
    $pdf->SetCreator(get_bloginfo('name'));
    $pdf->SetAuthor(wp_get_current_user()->display_name);
    $pdf->SetTitle('Cotización ' . $cotizacion->folio);

    // Configurar márgenes
    $pdf->SetMargins(15, 50, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);

    // Agregar página
    $pdf->AddPage();

    // Información de la cotización
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'COTIZACIÓN: ' . $cotizacion->folio, 0, 1, 'R');
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y', strtotime($cotizacion->fecha)), 0, 1, 'R');

    // Información del cliente
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'CLIENTE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Nombre: ' . $cliente->display_name, 0, 1, 'L');
    $pdf->Cell(0, 5, 'Email: ' . $cliente->user_email, 0, 1, 'L');
    if ($telefono = get_user_meta($cliente->ID, 'billing_phone', true)) {
        $pdf->Cell(0, 5, 'Teléfono: ' . $telefono, 0, 1, 'L');
    }

    $pdf->Ln(10);

    // Tabla de productos
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    
    // Cabecera de la tabla
    $pdf->Cell(30, 7, 'SKU', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Producto', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Cant.', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Precio', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Desc.', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Total', 1, 1, 'C', true);

    // Contenido de la tabla
    $pdf->SetFont('helvetica', '', 9);
    foreach ($items as $item) {
        $producto = wc_get_product($item->producto_id);
        if (!$producto) continue;

        $pdf->Cell(30, 6, $producto->get_sku(), 1, 0, 'L');
        $pdf->Cell(60, 6, $producto->get_name(), 1, 0, 'L');
        $pdf->Cell(20, 6, $item->cantidad, 1, 0, 'C');
        $pdf->Cell(30, 6, wc_price($item->precio_unitario, ['decimals' => 0]), 1, 0, 'R');
        $pdf->Cell(20, 6, wc_price($item->descuento, ['decimals' => 0]), 1, 0, 'R');
        $pdf->Cell(30, 6, wc_price($item->total, ['decimals' => 0]), 1, 1, 'R');
    }

    // Totales
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(140, 6, '', 0, 0);
    $pdf->Cell(20, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(30, 6, wc_price($cotizacion->subtotal, ['decimals' => 0]), 0, 1, 'R');

    if ($cotizacion->descuento > 0) {
        $pdf->Cell(140, 6, '', 0, 0);
        $pdf->Cell(20, 6, 'Descuento:', 0, 0, 'R');
        $pdf->Cell(30, 6, wc_price($cotizacion->descuento, ['decimals' => 0]), 0, 1, 'R');
    }

    if ($cotizacion->envio > 0) {
        $pdf->Cell(140, 6, '', 0, 0);
        $pdf->Cell(20, 6, 'Envío:', 0, 0, 'R');
        $pdf->Cell(30, 6, wc_price($cotizacion->envio, ['decimals' => 0]), 0, 1, 'R');
    }

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(140, 8, '', 0, 0);
    $pdf->Cell(20, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(30, 8, wc_price($cotizacion->total, ['decimals' => 0]), 0, 1, 'R');

    // Condiciones
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, 'Esta cotización tiene una validez de 15 días a partir de su emisión.', 0, 'L');

    return $pdf;
}

// Manejar la acción de generar PDF
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'ventas-cotizaciones' && 
        isset($_GET['action']) && $_GET['action'] === 'pdf' && 
        isset($_GET['id'])) {
        
        $cotizacion_id = intval($_GET['id']);
        $pdf = generar_pdf_cotizacion($cotizacion_id);
        $pdf->Output('Cotizacion-' . get_cotizacion($cotizacion_id)->folio . '.pdf', 'D');
        exit;
    }
});