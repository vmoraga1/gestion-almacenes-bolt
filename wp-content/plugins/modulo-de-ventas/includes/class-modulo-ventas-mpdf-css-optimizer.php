<?php
/**
 * Optimizador CSS específico para mPDF
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-mpdf-css-optimizer.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_mPDF_CSS_Optimizer {
    
    /**
     * Optimizar CSS para mPDF
     */
    public static function optimizar_css($css_content) {
        // 1. Reemplazos básicos de CSS no soportado
        $css_replacements = array(
            // Flexbox a alternativas
            'display: flex;' => 'display: table; width: 100%;',
            'display:flex;' => 'display:table; width:100%;',
            'display: flex' => 'display: table; width: 100%',
            'flex-direction: row;' => 'table-layout: auto;',
            'flex-direction: column;' => 'display: table-cell; vertical-align: top;',
            'justify-content: space-between;' => 'width: 100%;',
            'justify-content: center;' => 'text-align: center;',
            'align-items: center;' => 'vertical-align: middle;',
            'flex: 1;' => 'width: 100%;',
            'flex-wrap: wrap;' => '',
            
            // CSS Grid a alternativas
            'display: grid;' => 'display: table;',
            'grid-template-columns:' => '/* grid no soportado */',
            'grid-gap:' => 'border-spacing:',
            
            // Position fixed/sticky (no soportado)
            'position: fixed;' => 'position: absolute;',
            'position: sticky;' => 'position: relative;',
            
            // Transform (limitado en mPDF)
            'transform: ' => '/* transform: ',
            'transition: ' => '/* transition: ',
            'animation: ' => '/* animation: ',
            
            // Box shadow simplificado
            'box-shadow:' => 'border: 1px solid #ddd; /*',
            
            // Viewport units a porcentajes
            'vw' => '%',
            'vh' => '%',
            'vmin' => '%',
            'vmax' => '%',
            
            // Calc() función (limitada)
            'calc(' => '/* calc(',
            
            // Pseudo elementos (limitados)
            '::before' => '/* ::before',
            '::after' => '/* ::after',
        );
        
        // Aplicar reemplazos
        foreach ($css_replacements as $search => $replace) {
            $css_content = str_ireplace($search, $replace, $css_content);
        }
        
        // 2. Agregar CSS específico para mPDF
        $mpdf_specific_css = self::generar_css_especifico_mpdf();
        
        return $css_content . "\n\n" . $mpdf_specific_css;
    }
    
    /**
     * Generar CSS específico optimizado para mPDF
     */
    private static function generar_css_especifico_mpdf() {
        return '
/* ========================================
   CSS OPTIMIZADO ESPECÍFICAMENTE PARA mPDF
   ======================================== */

/* Reset básico para mPDF */
* {
    box-sizing: border-box;
}

body {
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 10pt;
    line-height: 1.4;
    color: #333;
    margin: 0;
    padding: 0;
}

/* Tipografía mejorada */
h1, h2, h3, h4, h5, h6 {
    font-family: "DejaVu Sans", Arial, sans-serif;
    margin-top: 0;
    margin-bottom: 10pt;
    font-weight: bold;
}

h1 { font-size: 18pt; color: #2c5aa0; }
h2 { font-size: 16pt; color: #2c5aa0; }
h3 { font-size: 14pt; color: #333; }
h4 { font-size: 12pt; color: #333; }

/* Layout mejorado con tables */
.header-empresa {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20pt;
    border-bottom: 2pt solid #2c5aa0;
    padding-bottom: 15pt;
}

.header-empresa td {
    vertical-align: top;
    padding: 10pt;
}

.empresa-info {
    width: 65%;
    text-align: left;
}

.cotizacion-info {
    width: 35%;
    text-align: right;
    background-color: #f8f9fa;
    padding: 15pt;
    border: 1pt solid #dee2e6;
}

/* Información del cliente */
.cliente-info {
    background-color: #f8f9fa;
    padding: 15pt;
    margin: 20pt 0;
    border: 1pt solid #dee2e6;
    border-radius: 3pt;
}

.cliente-info h3 {
    margin-top: 0;
    margin-bottom: 10pt;
    color: #2c5aa0;
    border-bottom: 1pt solid #2c5aa0;
    padding-bottom: 5pt;
}

/* Tablas optimizadas para mPDF */
.productos-tabla {
    width: 100%;
    border-collapse: collapse;
    margin: 20pt 0;
    font-size: 9pt;
}

.productos-tabla th {
    background-color: #2c5aa0;
    color: white;
    padding: 8pt 6pt;
    text-align: left;
    font-weight: bold;
    border: 1pt solid #2c5aa0;
}

.productos-tabla td {
    padding: 6pt;
    border: 1pt solid #dee2e6;
    vertical-align: top;
}

.productos-tabla tr:nth-child(even) {
    background-color: #f8f9fa;
}

.productos-tabla tr:nth-child(odd) {
    background-color: white;
}

/* Columnas específicas de la tabla */
.productos-tabla .col-descripcion {
    width: 40%;
}

.productos-tabla .col-cantidad {
    width: 15%;
    text-align: center;
}

.productos-tabla .col-precio {
    width: 20%;
    text-align: right;
}

.productos-tabla .col-total {
    width: 25%;
    text-align: right;
    font-weight: bold;
}

/* Sección de totales */
.totales-seccion {
    width: 100%;
    margin-top: 20pt;
    text-align: right;
}

.totales {
    display: inline-block;
    min-width: 250pt;
    border: 1pt solid #dee2e6;
    padding: 15pt;
    background-color: #f8f9fa;
}

.total-fila {
    margin: 5pt 0;
    padding: 3pt 0;
    border-bottom: 1pt dotted #ccc;
}

.total-fila:last-child {
    border-bottom: none;
}

.total-final {
    border-top: 2pt solid #2c5aa0;
    margin-top: 10pt;
    padding-top: 10pt;
    font-weight: bold;
    font-size: 11pt;
    background-color: #e3f2fd;
}

/* Observaciones */
.observaciones {
    background-color: #fff3cd;
    border: 1pt solid #ffeaa7;
    padding: 15pt;
    margin: 20pt 0;
    border-radius: 3pt;
}

.observaciones h4 {
    margin-top: 0;
    color: #856404;
}

/* Footer del documento */
.footer-documento {
    margin-top: 30pt;
    padding-top: 15pt;
    border-top: 1pt solid #dee2e6;
    text-align: center;
    font-size: 9pt;
    color: #666;
}

/* Datos de la empresa */
.empresa-datos {
    font-size: 9pt;
    color: #666;
    line-height: 1.3;
}

/* Logo (si existe) */
.logo-empresa {
    max-height: 60pt;
    max-width: 150pt;
    margin-bottom: 10pt;
}

/* Códigos y números destacados */
.codigo, .numero {
    font-family: "DejaVu Sans Mono", "Courier New", monospace;
    background-color: #f8f9fa;
    padding: 2pt 4pt;
    border: 1pt solid #dee2e6;
    font-size: 9pt;
}

/* Estados y badges */
.estado-badge {
    padding: 3pt 6pt;
    border-radius: 2pt;
    font-size: 8pt;
    font-weight: bold;
    text-transform: uppercase;
}

.estado-borrador { background-color: #f8f9fa; color: #6c757d; }
.estado-enviada { background-color: #cce5ff; color: #004085; }
.estado-aceptada { background-color: #d4edda; color: #155724; }
.estado-rechazada { background-color: #f8d7da; color: #721c24; }

/* Utilidades de texto */
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }

.font-bold { font-weight: bold; }
.font-italic { font-style: italic; }

/* Márgenes y padding */
.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: 5pt; }
.mb-2 { margin-bottom: 10pt; }
.mb-3 { margin-bottom: 15pt; }

.mt-0 { margin-top: 0; }
.mt-1 { margin-top: 5pt; }
.mt-2 { margin-top: 10pt; }
.mt-3 { margin-top: 15pt; }

/* Colores específicos */
.text-primary { color: #2c5aa0; }
.text-secondary { color: #6c757d; }
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-warning { color: #ffc107; }

.bg-primary { background-color: #2c5aa0; color: white; }
.bg-secondary { background-color: #6c757d; color: white; }
.bg-light { background-color: #f8f9fa; }

/* Headers y footers para páginas múltiples */
@page {
    margin: 2cm 1.5cm;
    
    @top-center {
        content: "Cotización - " attr(data-folio);
        font-size: 8pt;
        color: #666;
    }
    
    @bottom-center {
        content: "Página " counter(page) " de " counter(pages);
        font-size: 8pt;
        color: #666;
    }
}

/* Saltos de página */
.page-break-before {
    page-break-before: always;
}

.page-break-after {
    page-break-after: always;
}

.page-break-inside-avoid {
    page-break-inside: avoid;
}

/* Print-specific */
.no-print {
    display: none;
}

/* Mejoras específicas para cotizaciones */
.seccion-principal {
    margin-bottom: 25pt;
}

.datos-contacto {
    font-size: 9pt;
    line-height: 1.3;
}

.terminos-condiciones {
    font-size: 8pt;
    color: #666;
    margin-top: 20pt;
    padding-top: 10pt;
    border-top: 1pt dotted #ccc;
}

/* Responsive para diferentes tamaños de contenido */
.contenido-ajustable {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

/* ======================================== */';
    }
    
    /**
     * Optimizar HTML para mPDF
     */
    public static function optimizar_html($html_content) {
        // Convertir div flexbox a tables
        $html_content = self::convertir_flexbox_a_table($html_content);
        
        // Optimizar imágenes
        $html_content = self::optimizar_imagenes($html_content);
        
        // Limpiar scripts y elementos no soportados
        $html_content = self::limpiar_elementos_no_soportados($html_content);
        
        return $html_content;
    }
    
    /**
     * Convertir estructuras flexbox a tables
     */
    private static function convertir_flexbox_a_table($html_content) {
        // Patrón para detectar containers flex
        $patterns = array(
            // Convertir div.header-empresa con flex a table
            '/<div([^>]*class="[^"]*header-empresa[^"]*"[^>]*)>/i' => '<table$1><tr>',
            '/<\/div>(\s*<!--\s*\/header-empresa\s*-->)/' => '</tr></table>$1',
            
            // Convertir hijos de header-empresa a table cells
            '/<div([^>]*class="[^"]*empresa-info[^"]*"[^>]*)>/i' => '<td$1>',
            '/<div([^>]*class="[^"]*cotizacion-info[^"]*"[^>]*)>/i' => '<td$1>',
        );
        
        foreach ($patterns as $pattern => $replacement) {
            $html_content = preg_replace($pattern, $replacement, $html_content);
        }
        
        return $html_content;
    }
    
    /**
     * Optimizar imágenes para mPDF
     */
    private static function optimizar_imagenes($html_content) {
        // Agregar atributos max-width a imágenes
        $html_content = preg_replace(
            '/<img([^>]*?)>/i',
            '<img$1 style="max-width: 100%; height: auto;">',
            $html_content
        );
        
        return $html_content;
    }
    
    /**
     * Limpiar elementos no soportados por mPDF
     */
    private static function limpiar_elementos_no_soportados($html_content) {
        // Remover scripts
        $html_content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html_content);
        
        // Remover comentarios HTML (opcional)
        $html_content = preg_replace('/<!--(.|\s)*?-->/', '', $html_content);
        
        // Remover atributos no soportados
        $html_content = preg_replace('/\s+(data-[^=]*="[^"]*")/i', '', $html_content);
        
        return $html_content;
    }
    
    /**
     * Crear plantilla optimizada específicamente para mPDF
     */
    public static function crear_plantilla_optimizada() {
        $html_optimizado = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{cotizacion.folio}} - {{empresa.nombre}}</title>
</head>
<body>
    <div class="documento-cotizacion">
        
        <!-- Header optimizado con table -->
        <table class="header-empresa">
            <tr>
                <td class="empresa-info">
                    <h1>{{empresa.nombre}}</h1>
                    <div class="empresa-datos">
                        <p>{{empresa.direccion}}</p>
                        <p>{{empresa.ciudad}}, {{empresa.region}}</p>
                        <p>Tel: {{empresa.telefono}} | Email: {{empresa.email}}</p>
                        <p>RUT: {{empresa.rut}}</p>
                    </div>
                </td>
                <td class="cotizacion-info">
                    <h2>COTIZACIÓN</h2>
                    <p class="codigo"><strong>N°:</strong> {{cotizacion.folio}}</p>
                    <p><strong>Fecha:</strong> {{cotizacion.fecha}}</p>
                    <p><strong>Válida hasta:</strong> {{cotizacion.fecha_expiracion}}</p>
                    <p><strong>Vendedor:</strong> {{cotizacion.vendedor}}</p>
                </td>
            </tr>
        </table>
        
        <!-- Información del cliente -->
        <div class="cliente-info">
            <h3>DATOS DEL CLIENTE</h3>
            <p><strong>{{cliente.nombre}}</strong></p>
            <p>RUT: {{cliente.rut}}</p>
            <p>{{cliente.direccion}}</p>
            <p>{{cliente.ciudad}}, {{cliente.region}}</p>
            <p>Tel: {{cliente.telefono}} | Email: {{cliente.email}}</p>
        </div>
        
        <!-- Tabla de productos optimizada -->
        <table class="productos-tabla">
            <thead>
                <tr>
                    <th class="col-descripcion">Descripción</th>
                    <th class="col-cantidad">Cant.</th>
                    <th class="col-precio">Precio Unit.</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                {{tabla_productos}}
            </tbody>
        </table>
        
        <!-- Totales optimizados -->
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
        
        <!-- Observaciones -->
        <div class="observaciones">
            <h4>OBSERVACIONES</h4>
            <p>{{cotizacion.observaciones}}</p>
        </div>
        
        <!-- Términos y condiciones -->
        <div class="terminos-condiciones">
            <h4>TÉRMINOS Y CONDICIONES</h4>
            <ul>
                <li>Validez de la oferta: 30 días desde la fecha de emisión</li>
                <li>Forma de pago: Según acuerdo comercial</li>
                <li>Tiempo de entrega: Según especificaciones del producto</li>
                <li>Garantía: Según condiciones del fabricante</li>
            </ul>
        </div>
        
        <!-- Footer -->
        <div class="footer-documento">
            <p>Documento generado el {{fechas.hoy}} por {{sistema.usuario}}</p>
            <p>{{empresa.sitio_web}}</p>
        </div>
        
    </div>
</body>
</html>';

        return $html_optimizado;
    }
}