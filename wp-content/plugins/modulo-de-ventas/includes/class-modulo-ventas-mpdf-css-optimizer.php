<?php
/**
 * CORRECCIÓN URGENTE: Optimizador CSS para mPDF
 * REEMPLAZAR el archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-mpdf-css-optimizer.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_mPDF_CSS_Optimizer {
    
    /**
     * Optimizar CSS para mPDF (VERSIÓN CORREGIDA)
     */
    public static function optimizar_css($css_content) {
        // 1. Reemplazos básicos MÁS CONSERVADORES
        $css_replacements = array(
            // ELIMINAR flexbox completamente en lugar de convertir
            'display: flex;' => 'display: block;',
            'display:flex;' => 'display:block;',
            'display: flex' => 'display: block',
            
            // ELIMINAR propiedades flexbox
            'flex-direction: row;' => '',
            'flex-direction: column;' => '',
            'justify-content: space-between;' => '',
            'justify-content: center;' => 'text-align: center;',
            'align-items: center;' => 'vertical-align: middle;',
            'flex: 1;' => 'width: 100%;',
            'flex-wrap: wrap;' => '',
            
            // CSS Grid ELIMINADO
            'display: grid;' => 'display: block;',
            'grid-template-columns:' => '/* grid no soportado */',
            'grid-gap:' => '/* grid-gap no soportado */',
            
            // Position problemático
            'position: fixed;' => 'position: relative;',
            'position: sticky;' => 'position: relative;',
            
            // Transform ELIMINADO completamente
            'transform:' => '/* transform:',
            'transition:' => '/* transition:',
            'animation:' => '/* animation:',
            
            // Box shadow SIMPLIFICADO
            'box-shadow:' => '/* box-shadow:',
            
            // Viewport units
            'vw' => '%',
            'vh' => '%',
            'vmin' => '%',
            'vmax' => '%',
            
            // Calc() función
            'calc(' => '/* calc(',
            
            // Pseudo elementos
            '::before' => '/* ::before',
            '::after' => '/* ::after',
        );
        
        // Aplicar reemplazos
        foreach ($css_replacements as $search => $replace) {
            $css_content = str_ireplace($search, $replace, $css_content);
        }
        
        // 2. CSS específico SEGURO para mPDF
        $mpdf_safe_css = self::generar_css_seguro_mpdf();
        
        return $css_content . "\n\n" . $mpdf_safe_css;
    }
    
    /**
     * CSS ultra-seguro para mPDF (sin tablas complejas)
     */
    private static function generar_css_seguro_mpdf() {
        return '
/* ========================================
   CSS ULTRA-SEGURO PARA mPDF
   ======================================== */

/* Reset muy básico */
* {
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #333;
}

/* Tipografía segura */
h1, h2, h3, h4, h5, h6 {
    font-family: Arial, sans-serif;
    margin-bottom: 10px;
    font-weight: bold;
}

h1 { font-size: 18px; color: #2c5aa0; }
h2 { font-size: 16px; color: #2c5aa0; }
h3 { font-size: 14px; color: #333; }

/* Contenedores simples SIN FLEXBOX */
.header-empresa {
    width: 100%;
    margin-bottom: 20px;
    border-bottom: 2px solid #2c5aa0;
    padding-bottom: 15px;
}

.empresa-info {
    width: 100%;
    float: left;
    margin-bottom: 15px;
}

.cotizacion-info {
    width: 100%;
    background-color: #f8f9fa;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
}

/* Información del cliente SIMPLE */
.cliente-info {
    background-color: #f8f9fa;
    padding: 15px;
    margin: 20px 0;
    border: 1px solid #dee2e6;
}

.cliente-info h3 {
    margin-bottom: 10px;
    color: #2c5aa0;
    border-bottom: 1px solid #2c5aa0;
    padding-bottom: 5px;
}

/* Tablas MUY BÁSICAS para productos */
.productos-tabla {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    font-size: 11px;
}

.productos-tabla th {
    background-color: #2c5aa0;
    color: white;
    padding: 8px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #2c5aa0;
}

.productos-tabla td {
    padding: 6px;
    border: 1px solid #ddd;
    vertical-align: top;
}

/* Alternancia de filas SIMPLE */
.productos-tabla tr:nth-child(even) {
    background-color: #f9f9f9;
}

/* Totales SIN FLEXBOX */
.totales-seccion {
    width: 100%;
    margin-top: 20px;
    text-align: right;
}

.totales {
    width: 300px;
    margin-left: auto;
    border: 1px solid #ddd;
    padding: 15px;
    background-color: #f8f9fa;
}

.total-fila {
    margin: 5px 0;
    padding: 3px 0;
    border-bottom: 1px dotted #ccc;
}

.total-final {
    border-top: 2px solid #2c5aa0;
    margin-top: 10px;
    padding-top: 10px;
    font-weight: bold;
    font-size: 13px;
}

/* Observaciones */
.observaciones {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 15px;
    margin: 20px 0;
}

.observaciones h4 {
    margin-bottom: 10px;
    color: #856404;
}

/* Footer */
.footer-documento {
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    text-align: center;
    font-size: 10px;
    color: #666;
}

/* Utilidades básicas */
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }

.font-bold { font-weight: bold; }

/* Limpiar floats */
.clearfix:after {
    content: "";
    display: table;
    clear: both;
}

/* Sin elementos problemáticos */
.no-mpdf {
    display: none;
}';
    }
    
    /**
     * Optimizar HTML para mPDF (VERSIÓN MUY CONSERVADORA)
     */
    public static function optimizar_html($html_content) {
        // 1. NO convertir a tables - usar divs simples
        $html_content = self::simplificar_estructura_html($html_content);
        
        // 2. Limpiar elementos problemáticos
        $html_content = self::limpiar_elementos_problemáticos($html_content);
        
        // 3. Asegurar estructura válida
        $html_content = self::validar_estructura_html($html_content);
        
        return $html_content;
    }
    
    /**
     * Simplificar estructura HTML (SIN CONVERTIR A TABLES)
     */
    private static function simplificar_estructura_html($html_content) {
        // NO hacer conversiones complejas - solo limpiar
        
        // Cambiar divs problemáticos a divs simples
        $patterns = array(
            // Remover clases flexbox
            '/class="([^"]*)\s*header-empresa([^"]*)"/' => 'class="header-empresa clearfix"',
            '/class="([^"]*)\s*empresa-info([^"]*)"/' => 'class="empresa-info"',
            '/class="([^"]*)\s*cotizacion-info([^"]*)"/' => 'class="cotizacion-info"',
        );
        
        foreach ($patterns as $pattern => $replacement) {
            $html_content = preg_replace($pattern, $replacement, $html_content);
        }
        
        return $html_content;
    }
    
    /**
     * Limpiar elementos problemáticos para mPDF
     */
    private static function limpiar_elementos_problemáticos($html_content) {
        // Remover scripts
        $html_content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html_content);
        
        // Remover comentarios HTML
        $html_content = preg_replace('/<!--(.|\s)*?-->/', '', $html_content);
        
        // Remover atributos data-
        $html_content = preg_replace('/\s+data-[^=]*="[^"]*"/i', '', $html_content);
        
        // Remover estilos inline problemáticos
        $html_content = preg_replace('/style="[^"]*display:\s*flex[^"]*"/i', '', $html_content);
        $html_content = preg_replace('/style="[^"]*position:\s*fixed[^"]*"/i', '', $html_content);
        
        return $html_content;
    }
    
    /**
     * Validar estructura HTML para mPDF
     */
    private static function validar_estructura_html($html_content) {
        // Asegurar que hay DOCTYPE
        if (strpos($html_content, '<!DOCTYPE') === false) {
            $html_content = '<!DOCTYPE html>' . "\n" . $html_content;
        }
        
        // Asegurar charset UTF-8
        if (strpos($html_content, 'charset') === false) {
            $html_content = str_replace('<head>', '<head><meta charset="UTF-8">', $html_content);
        }
        
        return $html_content;
    }
    
    /**
     * Crear plantilla ultra-simple para mPDF
     */
    public static function crear_plantilla_ultra_simple() {
        $html_simple = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{cotizacion.folio}} - {{empresa.nombre}}</title>
</head>
<body>
    <div class="documento-cotizacion">
        
        <!-- Header simple -->
        <div class="header-empresa clearfix">
            <div class="empresa-info">
                <h1>{{empresa.nombre}}</h1>
                <p>{{empresa.direccion}}</p>
                <p>{{empresa.ciudad}}, {{empresa.region}}</p>
                <p>Tel: {{empresa.telefono}} | Email: {{empresa.email}}</p>
                <p>RUT: {{empresa.rut}}</p>
            </div>
            <div class="cotizacion-info">
                <h2>COTIZACIÓN</h2>
                <p><strong>N°:</strong> {{cotizacion.folio}}</p>
                <p><strong>Fecha:</strong> {{cotizacion.fecha}}</p>
                <p><strong>Válida hasta:</strong> {{cotizacion.fecha_expiracion}}</p>
                <p><strong>Vendedor:</strong> {{cotizacion.vendedor}}</p>
            </div>
        </div>
        
        <!-- Cliente -->
        <div class="cliente-info">
            <h3>DATOS DEL CLIENTE</h3>
            <p><strong>{{cliente.nombre}}</strong></p>
            <p>RUT: {{cliente.rut}}</p>
            <p>{{cliente.direccion}}</p>
            <p>{{cliente.ciudad}}, {{cliente.region}}</p>
            <p>Tel: {{cliente.telefono}} | Email: {{cliente.email}}</p>
        </div>
        
        <!-- Productos - tabla simple -->
        <table class="productos-tabla">
            <thead>
                <tr>
                    <th style="width: 50%;">Descripción</th>
                    <th style="width: 15%;">Cant.</th>
                    <th style="width: 15%;">Precio</th>
                    <th style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                {{tabla_productos}}
            </tbody>
        </table>
        
        <!-- Totales -->
        <div class="totales-seccion">
            <div class="totales">
                <div class="total-fila">
                    <span>Subtotal: ${{totales.subtotal_formateado}}</span>
                </div>
                <div class="total-fila">
                    <span>Descuento: -${{totales.descuento_formateado}}</span>
                </div>
                <div class="total-fila">
                    <span>IVA: ${{totales.impuestos_formateado}}</span>
                </div>
                <div class="total-fila total-final">
                    <span><strong>TOTAL: ${{totales.total_formateado}}</strong></span>
                </div>
            </div>
        </div>
        
        <!-- Observaciones -->
        <div class="observaciones">
            <h4>OBSERVACIONES</h4>
            <p>{{cotizacion.observaciones}}</p>
        </div>
        
        <!-- Footer -->
        <div class="footer-documento">
            <p>{{empresa.nombre}} - {{fechas.hoy}}</p>
        </div>
        
    </div>
</body>
</html>';

        return $html_simple;
    }
}