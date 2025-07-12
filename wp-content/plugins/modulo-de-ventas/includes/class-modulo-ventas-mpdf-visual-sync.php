<?php
/**
 * Sistema de Sincronización Visual mPDF
 * Archivo: includes/class-modulo-ventas-mpdf-visual-sync.php
 * 
 * Asegura que el preview y el PDF tengan exactamente la misma apariencia
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_mPDF_Visual_Sync {
    
    private static $instance = null;
    private $logger;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
    }
    
    /**
     * Procesar plantilla para que preview y PDF sean idénticos
     */
    public function sincronizar_plantilla_para_mpdf($plantilla, $cotizacion_id = null, $es_preview = false) {
        try {
            $this->logger->log("MPDF_SYNC: Sincronizando plantilla para " . ($es_preview ? 'preview' : 'PDF'));
            
            // 1. Obtener datos
            $datos = $this->obtener_datos_cotizacion($cotizacion_id);
            
            // 2. Procesar HTML con variables
            $html_procesado = $this->reemplazar_variables($plantilla->html_content, $datos);
            
            // 3. Aplicar CSS sincronizado (MISMO para preview y PDF)
            $css_sincronizado = $this->generar_css_sincronizado($plantilla->css_content, $es_preview);
            
            // 4. Generar documento HTML completo
            $documento_completo = $this->ensamblar_documento_html($html_procesado, $css_sincronizado, $es_preview);
            
            $this->logger->log("MPDF_SYNC: Documento sincronizado generado (" . strlen($documento_completo) . " caracteres)");
            
            return $documento_completo;
            
        } catch (Exception $e) {
            $this->logger->log("MPDF_SYNC: Error sincronizando: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Generar CSS que funcione IGUAL en navegador y mPDF
     */
    private function generar_css_sincronizado($css_original, $es_preview = false) {
        
        // CSS base compatible con mPDF
        $css_base = '
        /* Reset específico para mPDF */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333333;
            background: white;
        }
        
        /* Layout principal */
        .documento {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header - Table Layout para máxima compatibilidad */
        .header-empresa {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 10px;
        }
        
        .header-empresa td {
            vertical-align: top;
            padding: 5px;
        }
        
        .empresa-info {
            width: 65%;
        }
        
        .cotizacion-info {
            width: 35%;
            text-align: right;
        }
        
        .empresa-nombre {
            font-size: 18px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 5px;
        }
        
        .logo-empresa {
            max-height: 60px;
            max-width: 200px;
            display: block;
            margin-bottom: 10px;
        }
        
        /* Información de cotización */
        .folio-destacado {
            font-size: 20px;
            font-weight: bold;
            color: #2c5aa0;
            text-align: center;
            padding: 12px;
            border: 2px solid #2c5aa0;
            margin-bottom: 20px;
            background: #f8f9fa;
        }
        
        /* Cliente */
        .cliente-seccion {
            width: 100%;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .seccion-titulo {
            font-size: 14px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        
        .info-tabla {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-tabla td {
            padding: 3px 8px;
            vertical-align: top;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
            width: 30%;
        }
        
        .info-valor {
            color: #212529;
        }
        
        /* Tabla de productos */
        .productos-tabla {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #dee2e6;
        }
        
        .productos-tabla th {
            background-color: #2c5aa0;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
        }
        
        .productos-tabla td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 11px;
            vertical-align: top;
        }
        
        .productos-tabla tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .producto-nombre {
            font-weight: bold;
            color: #212529;
        }
        
        .producto-descripcion {
            color: #6c757d;
            font-style: italic;
            font-size: 10px;
            margin-top: 2px;
        }
        
        .cantidad-col {
            text-align: center;
            width: 10%;
        }
        
        .precio-col {
            text-align: right;
            width: 15%;
        }
        
        /* Totales */
        .totales-seccion {
            width: 100%;
            text-align: right;
            margin: 20px 0;
        }
        
        .totales-container {
            display: inline-block;
            width: 300px;
            border: 1px solid #dee2e6;
            background: white;
        }
        
        .total-tabla {
            width: 100%;
            border-collapse: collapse;
        }
        
        .total-tabla td {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .total-label {
            font-weight: bold;
            color: #495057;
            text-align: left;
        }
        
        .total-valor {
            text-align: right;
            color: #212529;
            width: 40%;
        }
        
        .total-final {
            background-color: #2c5aa0;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }
        
        .total-final .total-label,
        .total-final .total-valor {
            color: white;
        }
        
        /* Observaciones */
        .observaciones-seccion {
            width: 100%;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 20px 0;
        }
        
        .observaciones-texto {
            font-size: 11px;
            line-height: 1.5;
            color: #495057;
        }
        
        /* Términos */
        .terminos-seccion {
            width: 100%;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 20px 0;
        }
        
        .terminos-texto {
            font-size: 10px;
            line-height: 1.4;
            color: #6c757d;
        }
        
        /* Footer */
        .footer {
            width: 100%;
            text-align: center;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
            margin-top: 30px;
            font-size: 10px;
            color: #6c757d;
        }
        ';
        
        // CSS adicional para preview (simulando mPDF)
        if ($es_preview) {
            $css_base .= '
            /* Estilos específicos para preview que simulan mPDF */
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            
            .documento {
                background: white;
                border: 1px solid #ddd;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                min-height: 800px;
            }
            
            .preview-header {
                background: #e3f2fd;
                color: #1976d2;
                padding: 10px;
                text-align: center;
                font-size: 14px;
                font-weight: bold;
                margin: -20px -20px 20px -20px;
                border-bottom: 2px solid #1976d2;
            }
            ';
        }
        
        // Procesar CSS original del usuario (solo lo compatible con mPDF)
        $css_usuario = $this->filtrar_css_compatible_mpdf($css_original);
        
        return $css_base . "\n\n/* CSS de la plantilla (compatible mPDF) */\n" . $css_usuario;
    }
    
    /**
     * Filtrar CSS para que sea compatible con mPDF
     */
    private function filtrar_css_compatible_mpdf($css) {
        // Remover propiedades no compatibles con mPDF
        $propiedades_no_compatibles = array(
            '/display\s*:\s*flex[^;]*;/' => 'display: table;',
            '/display\s*:\s*grid[^;]*;/' => 'display: table;',
            '/position\s*:\s*fixed[^;]*;/' => 'position: absolute;',
            '/position\s*:\s*sticky[^;]*;/' => 'position: relative;',
            '/transform\s*:[^;]*;/' => '/* transform no soportado en mPDF */',
            '/transition\s*:[^;]*;/' => '/* transition no soportado en mPDF */',
            '/animation\s*:[^;]*;/' => '/* animation no soportado en mPDF */',
            '/box-shadow\s*:[^;]*;/' => 'border: 1px solid #ddd; /* box-shadow simulado */',
            '/text-shadow\s*:[^;]*;/' => '/* text-shadow no soportado en mPDF */',
            '/border-radius\s*:[^;]*;/' => '/* border-radius limitado en mPDF */',
        );
        
        foreach ($propiedades_no_compatibles as $patron => $reemplazo) {
            $css = preg_replace($patron, $reemplazo, $css);
        }
        
        // Convertir unidades relativas a absolutas
        $css = preg_replace('/(\d+(?:\.\d+)?)rem/', '${1}6px', $css); // 1rem = 16px
        $css = preg_replace('/(\d+(?:\.\d+)?)em/', '${1}6px', $css);   // 1em = 16px
        
        return $css;
    }
    
    /**
     * Ensamblar documento HTML completo
     */
    private function ensamblar_documento_html($html_content, $css_content, $es_preview = false) {
        $documento = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización PDF</title>
    <style>
' . $css_content . '
    </style>
</head>
<body>';

        if ($es_preview) {
            $documento .= '<div class="preview-header">📄 Vista Previa - Simulando mPDF</div>';
        }
        
        $documento .= $html_content;
        $documento .= '
</body>
</html>';
        
        return $documento;
    }
    
    /**
     * Obtener datos de cotización
     */
    private function obtener_datos_cotizacion($cotizacion_id = null) {
        if ($cotizacion_id) {
            // Datos reales con nombres de campos correctos
            global $wpdb;
            $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
            $tabla_items = $wpdb->prefix . 'mv_cotizaciones_items'; // Nombre correcto
            $tabla_clientes = $wpdb->prefix . 'mv_clientes';
            
            // Obtener cotización con datos del cliente
            $cotizacion = $wpdb->get_row($wpdb->prepare(
                "SELECT c.*, 
                        cl.razon_social, 
                        cl.email as cliente_email, 
                        cl.telefono as cliente_telefono, 
                        cl.rut as cliente_rut,
                        cl.direccion_facturacion, 
                        cl.ciudad_facturacion,
                        cl.region_facturacion,
                        cl.comuna_facturacion,
                        cl.giro_comercial
                FROM $tabla_cotizaciones c
                LEFT JOIN $tabla_clientes cl ON c.cliente_id = cl.id
                WHERE c.id = %d",
                $cotizacion_id
            ));
            
            if (!$cotizacion) {
                return $this->obtener_datos_prueba();
            }
            
            // Obtener items de la cotización
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $tabla_items WHERE cotizacion_id = %d ORDER BY orden ASC",
                $cotizacion_id
            ));
            
            // Calcular totales desde los items si no están en la cotización
            $totales_calculados = $this->calcular_totales_desde_items($items, $cotizacion);
            
            return array(
                'cotizacion' => $cotizacion,
                'items' => $items,
                'cliente' => (object) array(
                    'razon_social' => $cotizacion->razon_social ?? '',
                    'nombre' => $cotizacion->razon_social ?? '',
                    'email' => $cotizacion->cliente_email ?? '',
                    'telefono' => $cotizacion->cliente_telefono ?? '',
                    'rut' => $cotizacion->cliente_rut ?? '',
                    'direccion' => $cotizacion->direccion_facturacion ?? '',
                    'direccion_facturacion' => $cotizacion->direccion_facturacion ?? '',
                    'ciudad' => $cotizacion->ciudad_facturacion ?? '',
                    'ciudad_facturacion' => $cotizacion->ciudad_facturacion ?? '',
                    'region' => $cotizacion->region_facturacion ?? '',
                    'region_facturacion' => $cotizacion->region_facturacion ?? '',
                    'comuna' => $cotizacion->comuna_facturacion ?? '',
                    'comuna_facturacion' => $cotizacion->comuna_facturacion ?? '',
                    'giro_comercial' => $cotizacion->giro_comercial ?? ''
                ),
                'empresa' => $this->obtener_datos_empresa(),
                'totales' => $totales_calculados
            );
        }
        
        return $this->obtener_datos_prueba();
    }
    
    /**
     * Datos de prueba
     */
    private function obtener_datos_prueba() {
        return array(
            'cotizacion' => (object) array(
                'folio' => 'COT-2025-001',
                'fecha_creacion' => date('Y-m-d'),
                'fecha_expiracion' => date('Y-m-d', strtotime('+30 days')),
                'estado' => 'pendiente',
                'observaciones' => 'Esta es una cotización de prueba para verificar la sincronización visual entre preview y PDF.',
                'subtotal' => 850000,
                'iva' => 161500,
                'total' => 1011500
            ),
            'cliente' => (object) array(
                'nombre' => 'Empresa Cliente de Prueba S.A.',
                'email' => 'cliente@ejemplo.com',
                'telefono' => '+56 9 1234 5678',
                'direccion' => 'Av. Principal 123, Santiago, Chile',
                'rut' => '12.345.678-9'
            ),
            'empresa' => (object) array(
                'nombre' => get_option('blogname', 'Mi Empresa'),
                'direccion' => 'Av. Empresarial 456, Santiago, Chile',
                'telefono' => '+56 2 2345 6789',
                'email' => get_option('admin_email'),
                'rut' => '87.654.321-0',
                'logo' => get_option('modulo_ventas_logo_empresa', '')
            ),
            'items' => array(
                (object) array(
                    'producto_nombre' => 'Producto de Ejemplo 1',
                    'descripcion' => 'Descripción detallada del producto 1',
                    'cantidad' => 2,
                    'precio_unitario' => 150000,
                    'subtotal' => 300000
                ),
                (object) array(
                    'producto_nombre' => 'Producto de Ejemplo 2',
                    'descripcion' => 'Descripción detallada del producto 2',
                    'cantidad' => 1,
                    'precio_unitario' => 550000,
                    'subtotal' => 550000
                )
            ),
            'totales' => (object) array(
                'subtotal' => 850000,
                'iva' => 161500,
                'total' => 1011500
            )
        );
    }
    
    /**
     * Reemplazar variables en HTML
     */
    private function reemplazar_variables($html, $datos) {
        // === VARIABLES BÁSICAS DE COTIZACIÓN ===
        $variables = array(
            // Cotización
            '{{cotizacion.folio}}' => $datos['cotizacion']->folio ?? ($datos['cotizacion']->numero ?? 'COT-001'),
            '{{cotizacion.numero}}' => $datos['cotizacion']->folio ?? ($datos['cotizacion']->numero ?? 'COT-001'),
            '{{cotizacion.fecha}}' => date('d/m/Y', strtotime($datos['cotizacion']->fecha_creacion ?? 'now')),
            '{{cotizacion.fecha_expiracion}}' => date('d/m/Y', strtotime($datos['cotizacion']->fecha_expiracion ?? '+30 days')),
            '{{cotizacion.observaciones}}' => $datos['cotizacion']->observaciones ?? '',
            '{{cotizacion.estado}}' => $datos['cotizacion']->estado ?? 'pendiente',
            
            // === CLIENTE (usando razon_social y campos reales) ===
            '{{cliente.nombre}}' => $datos['cliente']->razon_social ?? ($datos['cliente']->nombre ?? ''),
            '{{cliente.razon_social}}' => $datos['cliente']->razon_social ?? '',
            '{{cliente.email}}' => $datos['cliente']->email ?? '',
            '{{cliente.telefono}}' => $datos['cliente']->telefono ?? '',
            '{{cliente.rut}}' => $datos['cliente']->rut ?? '',
            '{{cliente.direccion}}' => $datos['cliente']->direccion_facturacion ?? ($datos['cliente']->direccion ?? ''),
            '{{cliente.ciudad}}' => $datos['cliente']->ciudad_facturacion ?? ($datos['cliente']->ciudad ?? ''),
            '{{cliente.region}}' => $datos['cliente']->region_facturacion ?? ($datos['cliente']->region ?? ''),
            '{{cliente.comuna}}' => $datos['cliente']->comuna_facturacion ?? ($datos['cliente']->comuna ?? ''),
            '{{cliente.giro}}' => $datos['cliente']->giro_comercial ?? '',
            
            // === EMPRESA ===
            '{{empresa.nombre}}' => $datos['empresa']->nombre ?? get_option('blogname'),
            '{{empresa.direccion}}' => $datos['empresa']->direccion ?? '',
            '{{empresa.telefono}}' => $datos['empresa']->telefono ?? '',
            '{{empresa.email}}' => $datos['empresa']->email ?? get_option('admin_email'),
            '{{empresa.rut}}' => $datos['empresa']->rut ?? '',
            '{{empresa.ciudad}}' => $datos['empresa']->ciudad ?? '',
            '{{empresa.region}}' => $datos['empresa']->region ?? '',
            '{{empresa.sitio_web}}' => $datos['empresa']->sitio_web ?? get_option('siteurl'),
            '{{info_empresa}}' => $datos['empresa']->info_adicional ?? '',
            '{{empresa.info}}' => $datos['empresa']->info_adicional ?? '',
            '{{empresa.info_adicional}}' => $datos['empresa']->info_adicional ?? '',
            
            // === TOTALES (con formateo) ===
            '{{totales.subtotal}}' => '$' . number_format($datos['totales']->subtotal ?? 0, 0, ',', '.'),
            '{{totales.subtotal_formateado}}' => '$' . number_format($datos['totales']->subtotal ?? 0, 0, ',', '.'),
            '{{totales.iva}}' => '$' . number_format($datos['totales']->iva ?? 0, 0, ',', '.'),
            '{{totales.impuestos_formateado}}' => '$' . number_format($datos['totales']->iva ?? 0, 0, ',', '.'),
            '{{totales.total}}' => '$' . number_format($datos['totales']->total ?? 0, 0, ',', '.'),
            '{{totales.total_formateado}}' => '$' . number_format($datos['totales']->total ?? 0, 0, ',', '.'),
            
            // === DESCUENTOS ===
            '{{totales.descuento}}' => '$' . number_format($datos['totales']->descuento ?? 0, 0, ',', '.'),
            '{{totales.descuento_formateado}}' => '$' . number_format($datos['totales']->descuento ?? 0, 0, ',', '.'),
            '{{totales.descuento_porcentaje}}' => $datos['totales']->descuento_porcentaje ?? '0',
            
            // === FECHAS ===
            '{{fecha_actual}}' => date('d/m/Y'),
            '{{fechas.hoy}}' => date('d/m/Y'),
            '{{fechas.fecha_actual}}' => date('d/m/Y'),
            
            // === SISTEMA ===
            '{{sistema.usuario}}' => wp_get_current_user()->display_name ?? 'Sistema',
            '{{sistema.sitio}}' => get_option('blogname'),
            '{{sistema.version}}' => 'v2.0',

            // === TÉRMINOS Y CONDICIONES ===
            '{{terminos_condiciones}}' => get_option('modulo_ventas_terminos_condiciones', ''),
            '{{empresa.terminos}}' => get_option('modulo_ventas_terminos_condiciones', ''),
            '{{terminos}}' => get_option('modulo_ventas_terminos_condiciones', ''),
        );
        
        // Reemplazar variables simples
        $html_procesado = str_replace(array_keys($variables), array_values($variables), $html);
        
        // === PROCESAR TABLA DE PRODUCTOS ===
        $html_procesado = $this->procesar_tabla_productos($html_procesado, $datos['items'] ?? array());
        
        // === PROCESAR LOGO ===
        if (!empty($datos['empresa']->logo)) {
            $html_procesado = str_replace(
                '{{empresa.logo}}',
                '<img src="' . esc_url($datos['empresa']->logo) . '" alt="Logo" class="logo-empresa">',
                $html_procesado
            );
        } else {
            $html_procesado = str_replace('{{empresa.logo}}', '', $html_procesado);
        }
        
        return $html_procesado;
    }
    
    /**
     * Procesar tabla de productos
     */
    private function procesar_tabla_productos($html, $items) {
        // Buscar tanto {{tabla_productos}} como {{#productos}}
        
        // 1. Procesar {{tabla_productos}} (formato simple)
        if (strpos($html, '{{tabla_productos}}') !== false) {
            $tabla_html = $this->generar_tabla_productos_html($items);
            $html = str_replace('{{tabla_productos}}', $tabla_html, $html);
        }
        
        // 2. Procesar {{#productos}}...{{/productos}} (formato con plantilla)
        $patron = '/{{#productos}}(.*?){{\/productos}}/s';
        
        if (preg_match($patron, $html, $matches)) {
            $plantilla_fila = $matches[1];
            $filas_html = '';
            
            foreach ($items as $item) {
                $fila = str_replace(
                    array(
                        '{{producto.nombre}}',
                        '{{producto.descripcion}}',
                        '{{producto.codigo}}',
                        '{{producto.sku}}',
                        '{{cantidad}}',
                        '{{precio_unitario}}',
                        '{{precio_unitario_formateado}}',
                        '{{subtotal}}',
                        '{{subtotal_formateado}}'
                    ),
                    array(
                        $item->producto_nombre ?? ($item->nombre ?? ''),
                        $item->descripcion ?? '',
                        $item->codigo ?? '',
                        $item->sku ?? '',
                        $item->cantidad ?? 0,
                        '$' . number_format($item->precio_unitario ?? 0, 0, ',', '.'),
                        '$' . number_format($item->precio_unitario ?? 0, 0, ',', '.'),
                        '$' . number_format($item->subtotal ?? 0, 0, ',', '.'),
                        '$' . number_format($item->subtotal ?? 0, 0, ',', '.')
                    ),
                    $plantilla_fila
                );
                $filas_html .= $fila;
            }
            
            $html = str_replace($matches[0], $filas_html, $html);
        }
        
        return $html;
    }

    /**
     * Nuevo método para generar tabla HTML completa:
     */
    private function generar_tabla_productos_html($items) {
        if (empty($items)) {
            return '<p style="text-align: center; color: #666;">No hay productos en esta cotización</p>';
        }
        
        $tabla = '<table class="productos-tabla">
            <thead>
                <tr>
                    <th style="width: 50%;">Producto / Servicio</th>
                    <th style="width: 15%; text-align: center;">Cantidad</th>
                    <th style="width: 15%; text-align: right;">Precio Unit.</th>
                    <th style="width: 20%; text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($items as $item) {
            $nombre = $item->producto_nombre ?? ($item->nombre ?? 'Producto');
            $descripcion = $item->descripcion ?? '';
            $cantidad = $item->cantidad ?? 0;
            $precio_unitario = floatval($item->precio_unitario ?? 0);
            $subtotal = floatval($item->subtotal ?? 0);
            
            $tabla .= '<tr>';
            $tabla .= '<td>';
            $tabla .= '<div class="producto-nombre">' . esc_html($nombre) . '</div>';
            if ($descripcion) {
                $tabla .= '<div class="producto-descripcion">' . esc_html($descripcion) . '</div>';
            }
            $tabla .= '</td>';
            $tabla .= '<td class="cantidad-col">' . $cantidad . '</td>';
            $tabla .= '<td class="precio-col">$' . number_format($precio_unitario, 0, ',', '.') . '</td>';
            $tabla .= '<td class="precio-col">$' . number_format($subtotal, 0, ',', '.') . '</td>';
            $tabla .= '</tr>';
        }
        
        $tabla .= '</tbody></table>';
        
        return $tabla;
    }
    
    private function obtener_datos_cliente($cliente_id) {
        if (!$cliente_id) {
            return (object) array(
                'nombre' => 'Cliente de Prueba',
                'email' => 'cliente@ejemplo.com',
                'telefono' => '+56 9 1234 5678',
                'direccion' => 'Dirección de ejemplo',
                'rut' => '12.345.678-9'
            );
        }
        
        global $wpdb;
        $tabla = $wpdb->prefix . 'mv_clientes';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tabla WHERE id = %d",
            $cliente_id
        ));
    }
    
    private function obtener_datos_empresa() {
        return (object) array(
            'nombre' => get_option('modulo_ventas_nombre_empresa', get_option('blogname')),
            'direccion' => get_option('modulo_ventas_direccion_empresa', ''),
            'ciudad' => get_option('modulo_ventas_ciudad_empresa', ''),
            'region' => get_option('modulo_ventas_region_empresa', ''),
            'telefono' => get_option('modulo_ventas_telefono_empresa', ''),
            'email' => get_option('admin_email'),
            'rut' => get_option('modulo_ventas_rut_empresa', ''),
            'sitio_web' => get_option('siteurl'),
            'logo' => get_option('modulo_ventas_logo_empresa', ''),
            'info_adicional' => get_option('modulo_ventas_info_empresa', ''),
        );
    }
    
    private function calcular_totales($items) {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->subtotal ?? 0;
        }
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        return (object) array(
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total
        );
    }

    /**
     * Nuevo método para calcular totales desde items:
     */
    private function calcular_totales_desde_items($items, $cotizacion) {
        $subtotal = 0;
        
        foreach ($items as $item) {
            $subtotal += floatval($item->subtotal ?? 0);
        }
        
        // Usar totales de la cotización si están disponibles, sino calcular
        $descuento = floatval($cotizacion->descuento_monto ?? 0);
        $descuento_porcentaje = floatval($cotizacion->descuento_porcentaje ?? 0);
        $iva = floatval($cotizacion->impuesto_monto ?? ($subtotal * 0.19));
        $total = floatval($cotizacion->total ?? ($subtotal - $descuento + $iva));
        
        return (object) array(
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'descuento_porcentaje' => $descuento_porcentaje,
            'iva' => $iva,
            'total' => $total
        );
    }
}