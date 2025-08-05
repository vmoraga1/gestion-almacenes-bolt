<?php
/**
 * EXTENSIÓN DEL PROCESADOR DE PLANTILLAS PARA WEB
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-template-processor-web.php
 * 
 * Métodos adicionales para procesar CSS y HTML optimizados para visualización web
 * en lugar de conversión a PDF.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Template_Processor_Web {
    
    /**
     * Procesar CSS específicamente para visualización web
     */
    public static function procesar_css_para_web($css_content) {
        // Limpiar CSS básico
        $css_limpio = self::limpiar_css_base($css_content);
        
        // Optimizaciones específicas para web
        $css_web = self::aplicar_optimizaciones_web($css_limpio);
        
        // Agregar estilos responsivos
        $css_responsivo = self::agregar_estilos_responsivos($css_web);
        
        return $css_responsivo;
    }
    
    /**
     * Procesar CSS específicamente para impresión desde web
     */
    public static function procesar_css_para_impresion($css_content) {
        // Procesar primero para web
        $css_web = self::procesar_css_para_web($css_content);
        
        // Agregar optimizaciones específicas para impresión
        $css_impresion = self::agregar_estilos_impresion($css_web);
        
        return $css_impresion;
    }
    
    /**
     * Limpiar CSS básico
     */
    private static function limpiar_css_base($css) {
        // Remover comentarios
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Normalizar espacios
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remover espacios alrededor de llaves y dos puntos
        $css = str_replace(array(' {', '{ ', '} ', ' }', ' :', ': ', ' ;', '; '), 
                          array('{', '{', '}', '}', ':', ':', ';', ';'), $css);
        
        return trim($css);
    }
    
    /**
     * Aplicar optimizaciones específicas para web
     */
    private static function aplicar_optimizaciones_web($css) {
        $optimizaciones = array(
            // Mejorar renderizado de fuentes
            'body' => 'font-display: swap; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;',
            
            // Optimizar imágenes
            'img' => 'max-width: 100%; height: auto; display: block;',
            
            // Mejorar tablas
            'table' => 'border-collapse: collapse; width: 100%; table-layout: auto;',
            
            // Optimizar flexbox para mejor compatibilidad
            '.flex, .d-flex' => 'display: -webkit-box; display: -ms-flexbox; display: flex;',
            '.flex-column' => '-webkit-box-orient: vertical; -webkit-box-direction: normal; -ms-flex-direction: column; flex-direction: column;',
            '.justify-content-between' => '-webkit-box-pack: justify; -ms-flex-pack: justify; justify-content: space-between;',
            '.align-items-center' => '-webkit-box-align: center; -ms-flex-align: center; align-items: center;'
        );
        
        // Aplicar optimizaciones
        foreach ($optimizaciones as $selector => $propiedades) {
            // Buscar si el selector ya existe
            if (strpos($css, $selector) !== false) {
                // Si existe, agregar propiedades
                $css = preg_replace(
                    '/(' . preg_quote($selector, '/') . '\s*\{[^}]*)/i',
                    '$1 ' . $propiedades,
                    $css
                );
            } else {
                // Si no existe, agregar selector completo
                $css .= " {$selector} { {$propiedades} }";
            }
        }
        
        return $css;
    }
    
    /**
     * Agregar estilos responsivos
     */
    private static function agregar_estilos_responsivos($css) {
        $responsive_css = "
        
        /* Estilos responsivos */
        @media screen and (max-width: 768px) {
            .mv-document-container {
                margin: 10px !important;
                border-radius: 4px !important;
            }
            
            .mv-document-content {
                padding: 15px !important;
            }
            
            .mv-action-bar {
                padding: 10px 15px !important;
                flex-direction: column !important;
                gap: 10px !important;
            }
            
            .mv-action-buttons {
                width: 100% !important;
                justify-content: center !important;
            }
            
            .mv-btn {
                padding: 10px 12px !important;
                font-size: 13px !important;
            }
            
            /* Hacer tablas responsivas */
            .productos-tabla,
            table {
                font-size: 11px !important;
            }
            
            .productos-tabla th,
            .productos-tabla td {
                padding: 6px 4px !important;
            }
            
            /* Ajustar encabezados */
            h1 { font-size: 18px !important; }
            h2 { font-size: 16px !important; }
            h3 { font-size: 14px !important; }
            
            /* Ocultar elementos no esenciales en móvil */
            .hide-mobile {
                display: none !important;
            }
        }
        
        @media screen and (max-width: 480px) {
            .mv-document-container {
                margin: 5px !important;
                border-radius: 0 !important;
            }
            
            .mv-document-content {
                padding: 10px !important;
            }
            
            /* Hacer las tablas aún más compactas */
            .productos-tabla,
            table {
                font-size: 10px !important;
            }
            
            /* Stack elementos en columna */
            .header {
                flex-direction: column !important;
                text-align: center !important;
            }
            
            .totales-seccion {
                margin-top: 20px !important;
            }
        }
        
        /* Mejoras de accesibilidad */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        @media (prefers-contrast: high) {
            body {
                background: white !important;
                color: black !important;
            }
            
            .mv-document-container {
                border: 2px solid black !important;
            }
        }";
        
        return $css . $responsive_css;
    }
    
    /**
     * Agregar estilos específicos para impresión
     */
    private static function agregar_estilos_impresion($css) {
        $print_css = "
        
        /* Estilos específicos para impresión */
        @media print {
            /* Reset básico para impresión */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                background: white !important;
                color: black !important;
                font-size: 11pt !important;
                line-height: 1.3 !important;
            }
            
            /* Ocultar elementos no imprimibles */
            .mv-action-bar,
            .mv-preview-badge,
            .no-print,
            button {
                display: none !important;
            }
            
            /* Optimizar contenedor para impresión */
            .mv-document-container {
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                max-width: none !important;
                width: 100% !important;
            }
            
            .mv-document-content {
                padding: 15mm !important;
            }
            
            /* Configuración de página */
            @page {
                size: A4;
                margin: 15mm;
            }
            
            /* Evitar saltos de página problemáticos */
            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid !important;
                page-break-inside: avoid !important;
            }
            
            table {
                page-break-inside: avoid !important;
            }
            
            tr {
                page-break-inside: avoid !important;
            }
            
            .page-break-before {
                page-break-before: always !important;
            }
            
            .page-break-after {
                page-break-after: always !important;
            }
            
            .no-page-break {
                page-break-inside: avoid !important;
            }
            
            /* Optimizar tablas para impresión */
            .productos-tabla,
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9pt !important;
            }
            
            .productos-tabla th,
            .productos-tabla td,
            table th,
            table td {
                border: 1px solid #333 !important;
                padding: 4pt !important;
                text-align: left !important;
            }
            
            .productos-tabla th {
                background: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            /* Asegurar que los colores se impriman */
            .header {
                border-bottom: 2pt solid #333 !important;
            }
            
            /* Optimizar tipografía para impresión */
            h1 { font-size: 16pt !important; }
            h2 { font-size: 14pt !important; }
            h3 { font-size: 12pt !important; }
            h4 { font-size: 11pt !important; }
            
            /* Mejorar contraste para impresión */
            .cliente-info,
            .totales-seccion,
            .observaciones {
                border: 1pt solid #666 !important;
                padding: 8pt !important;
                margin: 8pt 0 !important;
            }
            
            /* Asegurar que las imágenes se impriman bien */
            img {
                max-width: 100% !important;
                height: auto !important;
                page-break-inside: avoid !important;
            }
            
            /* Footer para todas las páginas */
            .document-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 20mm;
                border-top: 1pt solid #333;
                padding: 5mm;
                font-size: 8pt;
                color: #666;
            }
        }
        
        /* Estilos adicionales para vista de impresión en pantalla */
        .mv-mode-print {
            background: white !important;
        }
        
        .mv-mode-print .mv-document-container {
            box-shadow: 0 0 10px rgba(0,0,0,0.1) !important;
            border: 1px solid #ddd !important;
        }
        
        .mv-mode-print .mv-document-content {
            min-height: 277mm !important; /* A4 height minus margins */
        }";
        
        return $css . $print_css;
    }
    
    /**
     * Optimizar HTML para visualización web
     */
    public static function optimizar_html_para_web($html) {
        // Agregar clases adicionales para responsive
        $html = self::agregar_clases_responsivas($html);
        
        // Optimizar imágenes
        $html = self::optimizar_imagenes_html($html);
        
        // Agregar landmarks ARIA para accesibilidad
        $html = self::agregar_landmarks_aria($html);
        
        return $html;
    }
    
    /**
     * Agregar clases responsivas al HTML
     */
    private static function agregar_clases_responsivas($html) {
        $reemplazos = array(
            // Agregar clases responsivas a tablas
            '<table' => '<table class="table-responsive"',
            
            // Agregar clases a elementos de totales
            'class="totales-seccion"' => 'class="totales-seccion no-page-break"',
            
            // Agregar clases a observaciones
            'class="observaciones"' => 'class="observaciones no-page-break"',
            
            // Mejorar header
            'class="header"' => 'class="header no-page-break"'
        );
        
        foreach ($reemplazos as $buscar => $reemplazar) {
            $html = str_replace($buscar, $reemplazar, $html);
        }
        
        return $html;
    }
    
    /**
     * Optimizar imágenes en HTML
     */
    private static function optimizar_imagenes_html($html) {
        // Agregar loading="lazy" a imágenes
        $html = preg_replace(
            '/<img(?![^>]*loading=)([^>]*)/i',
            '<img loading="lazy"$1',
            $html
        );
        
        // Asegurar alt text en imágenes
        $html = preg_replace(
            '/<img(?![^>]*alt=)([^>]*)/i',
            '<img alt=""$1',
            $html
        );
        
        return $html;
    }
    
    /**
     * Agregar landmarks ARIA para accesibilidad
     */
    private static function agregar_landmarks_aria($html) {
        $reemplazos = array(
            // Header principal
            '<div class="header"' => '<header class="header" role="banner"',
            
            // Información del cliente
            '<div class="cliente-info"' => '<section class="cliente-info" aria-labelledby="cliente-heading"',
            
            // Tabla de productos
            '<div class="productos"' => '<main class="productos" role="main" aria-labelledby="productos-heading"',
            
            // Totales
            '<div class="totales-seccion"' => '<section class="totales-seccion" aria-labelledby="totales-heading"',
            
            // Observaciones
            '<div class="observaciones"' => '<section class="observaciones" aria-labelledby="observaciones-heading"',
            
            // Footer
            '<div class="footer"' => '<footer class="footer" role="contentinfo"'
        );
        
        foreach ($reemplazos as $buscar => $reemplazar) {
            $html = str_replace($buscar, $reemplazar, $html);
        }
        
        // Agregar IDs a headings si no los tienen
        $html = preg_replace(
            '/<h([123])([^>]*?)>([^<]*cliente[^<]*)</i',
            '<h$1$2 id="cliente-heading">$3</h$1>',
            $html
        );
        
        $html = preg_replace(
            '/<h([123])([^>]*?)>([^<]*producto[^<]*)</i',
            '<h$1$2 id="productos-heading">$3</h$1>',
            $html
        );
        
        return $html;
    }
    
    /**
     * Generar CSS crítico para carga rápida
     */
    public static function extraer_css_critico($css) {
        // Selectores críticos que deben cargarse primero
        $selectores_criticos = array(
            'body', 'html', '.mv-document-container', '.mv-document-content',
            'h1', 'h2', 'h3', '.header', '.cliente-info', 'table', '.productos-tabla'
        );
        
        $css_critico = '';
        
        foreach ($selectores_criticos as $selector) {
            // Extraer reglas CSS para este selector
            $patron = '/' . preg_quote($selector, '/') . '\s*\{[^}]*\}/i';
            if (preg_match($patron, $css, $matches)) {
                $css_critico .= $matches[0] . "\n";
            }
        }
        
        return $css_critico;
    }
    
    /**
     * Minificar CSS para mejor rendimiento
     */
    public static function minificar_css($css) {
        // Remover comentarios
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Remover espacios innecesarios
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remover espacios alrededor de caracteres especiales
        $css = str_replace(array(' {', '{ ', '} ', ' }', ' :', ': ', ' ;', '; ', ' ,', ', '),
                          array('{', '{', '}', '}', ':', ':', ';', ';', ',', ','), $css);
        
        // Remover punto y coma final innecesario
        $css = preg_replace('/;}/', '}', $css);
        
        return trim($css);
    }
}