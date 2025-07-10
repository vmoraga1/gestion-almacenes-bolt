<?php
/**
 * MOTOR DE PROCESAMIENTO DE PLANTILLAS PDF
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF_Template_Processor {
    
    /**
     * Instancia singleton
     */
    private static $instance = null;
    
    /**
     * Logger
     */
    private $logger;
    
    /**
     * Datos de la cotización actual
     */
    private $cotizacion_data = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
    }
    
    /**
     * Obtener instancia singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Procesar plantilla completa con datos de cotización
     */
    public function procesar_plantilla($plantilla, $cotizacion_id, $tipo = 'cotizacion') {
        $this->logger->log("TEMPLATE_PROCESSOR: Procesando plantilla ID {$plantilla->id} para cotización {$cotizacion_id}");
        
        // Cargar datos de la cotización
        $this->cargar_datos_cotizacion($cotizacion_id);
        
        if (!$this->cotizacion_data) {
            throw new Exception(__('No se pudieron cargar los datos de la cotización', 'modulo-ventas'));
        }
        
        // Procesar HTML
        $html_procesado = $this->procesar_contenido($plantilla->html_content, $tipo);
        
        // Procesar CSS
        $css_procesado = $this->procesar_contenido($plantilla->css_content, $tipo);
        
        // Combinar en documento final
        $documento_final = $this->generar_documento_final($html_procesado, $css_procesado);
        
        $this->logger->log("TEMPLATE_PROCESSOR: Plantilla procesada exitosamente");
        
        return $documento_final;
    }
    
    /**
     * Procesar plantilla con datos de prueba para preview
     */
    public function procesar_plantilla_preview($html_content, $css_content, $tipo = 'cotizacion') {
        $this->logger->log("TEMPLATE_PROCESSOR: Procesando preview de plantilla");
        
        // Generar datos de prueba
        $this->generar_datos_prueba($tipo);
        
        // Procesar contenido
        $html_procesado = $this->procesar_contenido($html_content, $tipo);
        $css_procesado = $this->procesar_contenido($css_content, $tipo);
        
        // Generar documento final
        return $this->generar_documento_final($html_procesado, $css_procesado);
    }
    
    /**
     * Cargar datos reales de cotización
     */
    private function cargar_datos_cotizacion($cotizacion_id) {
        global $wpdb;
        
        $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        $tabla_detalles = $wpdb->prefix . 'mv_cotizacion_detalles';
        $tabla_clientes = $wpdb->prefix . 'mv_clientes';
        
        // Cargar cotización principal
        $cotizacion = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, 
                    cl.telefono as cliente_telefono, cl.rut as cliente_rut,
                    cl.direccion as cliente_direccion, cl.ciudad as cliente_ciudad,
                    cl.region as cliente_region
            FROM $tabla_cotizaciones c
            LEFT JOIN $tabla_clientes cl ON c.cliente_id = cl.id
            WHERE c.id = %d",
            $cotizacion_id
        ));
        
        if (!$cotizacion) {
            $this->logger->log("TEMPLATE_PROCESSOR: Cotización {$cotizacion_id} no encontrada", 'error');
            return false;
        }
        
        // Cargar detalles de productos
        $detalles = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tabla_detalles WHERE cotizacion_id = %d ORDER BY orden ASC",
            $cotizacion_id
        ));
        
        // Preparar datos estructurados
        $this->cotizacion_data = array(
            // Datos de la cotización
            'cotizacion' => array(
                'id' => $cotizacion->id,
                'numero' => $cotizacion->numero,
                'fecha' => $cotizacion->fecha_creacion,
                'fecha_vencimiento' => $cotizacion->fecha_vencimiento,
                'estado' => $cotizacion->estado,
                'observaciones' => $cotizacion->observaciones,
                'subtotal' => floatval($cotizacion->subtotal),
                'descuento' => floatval($cotizacion->descuento),
                'impuestos' => floatval($cotizacion->impuestos),
                'total' => floatval($cotizacion->total),
                'vendedor' => $cotizacion->vendedor ?: get_bloginfo('name')
            ),
            
            // Datos del cliente
            'cliente' => array(
                'nombre' => $cotizacion->cliente_nombre ?: 'Cliente sin nombre',
                'email' => $cotizacion->cliente_email ?: '',
                'telefono' => $cotizacion->cliente_telefono ?: '',
                'rut' => $cotizacion->cliente_rut ?: '',
                'direccion' => $cotizacion->cliente_direccion ?: '',
                'ciudad' => $cotizacion->cliente_ciudad ?: '',
                'region' => $cotizacion->cliente_region ?: ''
            ),
            
            // Datos de la empresa
            'empresa' => $this->obtener_datos_empresa(),
            
            // Productos/servicios
            'productos' => $this->procesar_detalles_productos($detalles),
            
            // Fechas formateadas
            'fechas' => array(
                'hoy' => current_time('d/m/Y'),
                'fecha_cotizacion' => date('d/m/Y', strtotime($cotizacion->fecha_creacion)),
                'fecha_vencimiento_formateada' => $cotizacion->fecha_vencimiento ? date('d/m/Y', strtotime($cotizacion->fecha_vencimiento)) : '',
                'mes_actual' => current_time('F'),
                'año_actual' => current_time('Y')
            ),
            
            // Totales formateados
            'totales' => array(
                'subtotal_formateado' => number_format($cotizacion->subtotal, 0, ',', '.'),
                'descuento_formateado' => number_format($cotizacion->descuento, 0, ',', '.'),
                'impuestos_formateado' => number_format($cotizacion->impuestos, 0, ',', '.'),
                'total_formateado' => number_format($cotizacion->total, 0, ',', '.'),
                'descuento_porcentaje' => $cotizacion->subtotal > 0 ? round(($cotizacion->descuento / $cotizacion->subtotal) * 100, 1) : 0
            )
        );
        
        return true;
    }
    
    /**
     * Generar datos de prueba para preview
     */
    private function generar_datos_prueba($tipo = 'cotizacion') {
        $productos_demo = array(
            array(
                'codigo' => 'PROD001',
                'nombre' => 'Laptop HP Pavilion 15',
                'descripcion' => 'Laptop HP Pavilion 15 con procesador Intel Core i5',
                'cantidad' => 2,
                'precio_unitario' => 650000,
                'descuento' => 0,
                'subtotal' => 1300000
            ),
            array(
                'codigo' => 'SERV001',
                'nombre' => 'Instalación y Configuración',
                'descripcion' => 'Servicio de instalación y configuración de software',
                'cantidad' => 1,
                'precio_unitario' => 75000,
                'descuento' => 5000,
                'subtotal' => 70000
            ),
            array(
                'codigo' => 'ACC001',
                'nombre' => 'Mouse Inalámbrico',
                'descripcion' => 'Mouse inalámbrico ergonómico',
                'cantidad' => 2,
                'precio_unitario' => 25000,
                'descuento' => 0,
                'subtotal' => 50000
            )
        );
        
        $subtotal = 1420000;
        $descuento = 142000; // 10%
        $impuestos = 242820; // 19% sobre (subtotal - descuento)
        $total = 1520820;
        
        $this->cotizacion_data = array(
            'cotizacion' => array(
                'id' => 1001,
                'numero' => 'COT-2025-001',
                'fecha' => current_time('Y-m-d H:i:s'),
                'fecha_vencimiento' => date('Y-m-d', strtotime('+30 days')),
                'estado' => 'enviada',
                'observaciones' => 'Esta es una cotización de ejemplo generada automáticamente para mostrar el diseño de la plantilla PDF.',
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestos,
                'total' => $total,
                'vendedor' => 'Juan Pérez - Ejecutivo de Ventas'
            ),
            
            'cliente' => array(
                'nombre' => 'Tecnología y Servicios Empresariales Ltda.',
                'email' => 'contacto@techservicios.cl',
                'telefono' => '+56 2 2345 6789',
                'rut' => '76.543.210-K',
                'direccion' => 'Av. Providencia 1234, Oficina 567',
                'ciudad' => 'Santiago',
                'region' => 'Región Metropolitana'
            ),
            
            'empresa' => $this->obtener_datos_empresa(),
            
            'productos' => $this->procesar_detalles_productos($productos_demo, true),
            
            'fechas' => array(
                'hoy' => current_time('d/m/Y'),
                'fecha_cotizacion' => current_time('d/m/Y'),
                'fecha_vencimiento_formateada' => date('d/m/Y', strtotime('+30 days')),
                'mes_actual' => date_i18n('F'),
                'año_actual' => current_time('Y')
            ),
            
            'totales' => array(
                'subtotal_formateado' => number_format($subtotal, 0, ',', '.'),
                'descuento_formateado' => number_format($descuento, 0, ',', '.'),
                'impuestos_formateado' => number_format($impuestos, 0, ',', '.'),
                'total_formateado' => number_format($total, 0, ',', '.'),
                'descuento_porcentaje' => 10.0
            )
        );
    }
    
    /**
     * Obtener datos de la empresa desde configuración de WordPress
     */
    private function obtener_datos_empresa() {
        return array(
            'nombre' => get_bloginfo('name') ?: 'Mi Empresa',
            'descripcion' => get_bloginfo('description') ?: 'Descripción de la empresa',
            'direccion' => get_option('mv_empresa_direccion', 'Dirección no configurada'),
            'telefono' => get_option('mv_empresa_telefono', ''),
            'email' => get_option('admin_email'),
            'sitio_web' => get_bloginfo('url'),
            'rut' => get_option('mv_empresa_rut', ''),
            'logo_url' => get_option('mv_empresa_logo', ''),
            'ciudad' => get_option('mv_empresa_ciudad', ''),
            'region' => get_option('mv_empresa_region', '')
        );
    }
    
    /**
     * Procesar detalles de productos
     */
    private function procesar_detalles_productos($detalles, $es_demo = false) {
        $productos_procesados = array();
        
        foreach ($detalles as $detalle) {
            if ($es_demo) {
                // Para datos demo, ya vienen como array
                $producto = $detalle;
            } else {
                // Para datos reales, convertir objeto a array
                $producto = array(
                    'codigo' => $detalle->codigo_producto ?: '',
                    'nombre' => $detalle->nombre_producto,
                    'descripcion' => $detalle->descripcion ?: '',
                    'cantidad' => intval($detalle->cantidad),
                    'precio_unitario' => floatval($detalle->precio_unitario),
                    'descuento' => floatval($detalle->descuento),
                    'subtotal' => floatval($detalle->subtotal)
                );
            }
            
            // Agregar campos calculados
            $producto['precio_unitario_formateado'] = number_format($producto['precio_unitario'], 0, ',', '.');
            $producto['descuento_formateado'] = number_format($producto['descuento'], 0, ',', '.');
            $producto['subtotal_formateado'] = number_format($producto['subtotal'], 0, ',', '.');
            $producto['precio_con_descuento'] = $producto['precio_unitario'] - $producto['descuento'];
            $producto['precio_con_descuento_formateado'] = number_format($producto['precio_con_descuento'], 0, ',', '.');
            
            $productos_procesados[] = $producto;
        }
        
        return $productos_procesados;
    }
    
    /**
     * Procesar contenido reemplazando variables
     */
    private function procesar_contenido($contenido, $tipo = 'cotizacion') {
        if (empty($contenido)) {
            return '';
        }
        
        // Buscar todas las variables en el formato {{variable}}
        $patron = '/\{\{([^}]+)\}\}/';
        
        return preg_replace_callback($patron, function($matches) use ($tipo) {
            $variable = trim($matches[1]);
            return $this->obtener_valor_variable($variable, $tipo);
        }, $contenido);
    }
    
    /**
     * Obtener valor de una variable específica
     */
    private function obtener_valor_variable($variable, $tipo = 'cotizacion') {
        if (!$this->cotizacion_data) {
            return '{{' . $variable . '}}'; // Devolver la variable sin procesar si no hay datos
        }
        
        // Variables especiales que necesitan procesamiento inmediato
        $variables_especiales = array(
            'tabla_productos',
            'logo_empresa', 
            'fecha_actual',
            'hora_actual',
            'sistema.fecha_actual',
            'sistema.hora_actual'
        );
        
        if (in_array($variable, $variables_especiales)) {
            return $this->procesar_variable_especial($variable, null);
        }
        
        // Manejar variables con puntos (ej: cotizacion.numero)
        $partes = explode('.', $variable);
        $valor = $this->cotizacion_data;
        
        foreach ($partes as $parte) {
            if (is_array($valor) && isset($valor[$parte])) {
                $valor = $valor[$parte];
            } elseif (is_object($valor) && isset($valor->$parte)) {
                $valor = $valor->$parte;
            } else {
                // Variable no encontrada
                $this->logger->log("TEMPLATE_PROCESSOR: Variable no encontrada: {$variable}", 'warning');
                
                // Intentar como variable especial antes de devolver sin procesar
                if (strpos($variable, '.') !== false) {
                    return $this->procesar_variable_especial($variable, null);
                }
                
                return '{{' . $variable . '}}';
            }
        }
        
        // Procesar variables especiales
        return $this->procesar_variable_especial($variable, $valor);
    }
    
    /**
     * Procesar variables especiales que requieren lógica adicional
     */
    private function procesar_variable_especial($variable, $valor) {
        switch ($variable) {
            case 'tabla_productos':
                return $this->generar_tabla_productos();
                
            case 'logo_empresa':
                return $this->procesar_logo_empresa($valor);
                
            case 'fecha_actual':
            case 'sistema.fecha_actual':
                return current_time('d/m/Y');
                
            case 'hora_actual':
            case 'sistema.hora_actual':
                return current_time('H:i');
                
            default:
                return is_string($valor) || is_numeric($valor) ? $valor : '';
        }
    }
    
    /**
     * Generar tabla HTML de productos
     */
    private function generar_tabla_productos() {
        if (!isset($this->cotizacion_data['productos']) || empty($this->cotizacion_data['productos'])) {
            return '<p style="text-align: center; color: #666; font-style: italic;">No hay productos en esta cotización.</p>';
        }
        
        $html = '<table class="productos-tabla" style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10px;">
                    <thead>
                        <tr>
                            <th style="background-color: #0073aa; color: white; border: 1px solid #0073aa; padding: 8px; text-align: left; font-weight: bold;">Código</th>
                            <th style="background-color: #0073aa; color: white; border: 1px solid #0073aa; padding: 8px; text-align: left; font-weight: bold;">Producto/Servicio</th>
                            <th style="background-color: #0073aa; color: white; border: 1px solid #0073aa; padding: 8px; text-align: center; font-weight: bold;">Cantidad</th>
                            <th style="background-color: #0073aa; color: white; border: 1px solid #0073aa; padding: 8px; text-align: right; font-weight: bold;">Precio Unit.</th>
                            <th style="background-color: #0073aa; color: white; border: 1px solid #0073aa; padding: 8px; text-align: right; font-weight: bold;">Descuento</th>
                            <th style="background-color: #0073aa; color: white; border: 1px solid #0073aa; padding: 8px; text-align: right; font-weight: bold;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($this->cotizacion_data['productos'] as $index => $producto) {
            $background = ($index % 2 == 0) ? '#ffffff' : '#f9f9f9';
            
            $html .= '<tr style="background-color: ' . $background . ';">
                        <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top;">' . esc_html($producto['codigo']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top;">
                            <strong>' . esc_html($producto['nombre']) . '</strong>';
            
            if (!empty($producto['descripcion'])) {
                $html .= '<br><small style="color: #666;">' . esc_html($producto['descripcion']) . '</small>';
            }
            
            $html .= '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: top;">' . esc_html($producto['cantidad']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top;">$' . esc_html($producto['precio_unitario_formateado']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top;">$' . esc_html($producto['descuento_formateado']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top;">$' . esc_html($producto['subtotal_formateado']) . '</td>
                    </tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    /**
     * Procesar logo de empresa
     */
    private function procesar_logo_empresa($logo_url) {
        if (empty($logo_url)) {
            return '';
        }
        
        // Asegurar que sea una URL completa
        if (!filter_var($logo_url, FILTER_VALIDATE_URL)) {
            $logo_url = home_url($logo_url);
        }
        
        return '<img src="' . esc_url($logo_url) . '" alt="Logo" class="logo-empresa" style="max-height: 80px; max-width: 200px;">';
    }
    
    /**
     * Generar documento HTML final
     */
    private function generar_documento_final($html_procesado, $css_procesado) {
        $documento = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento PDF</title>
    <style>
        ' . $css_procesado . '
    </style>
</head>
<body>
    <div class="documento">
        ' . $html_procesado . '
    </div>
</body>
</html>';
        
        return $documento;
    }
    
    /**
     * Obtener lista de variables disponibles para el editor
     */
    public function obtener_variables_disponibles($tipo = 'cotizacion') {
        $variables = array(
            'Datos de la Cotización' => array(
                array('variable' => 'cotizacion.numero', 'descripcion' => 'Número de cotización'),
                array('variable' => 'cotizacion.fecha', 'descripcion' => 'Fecha de creación'),
                array('variable' => 'cotizacion.fecha_vencimiento', 'descripcion' => 'Fecha de vencimiento'),
                array('variable' => 'cotizacion.estado', 'descripcion' => 'Estado actual'),
                array('variable' => 'cotizacion.observaciones', 'descripcion' => 'Observaciones'),
                array('variable' => 'cotizacion.vendedor', 'descripcion' => 'Nombre del vendedor'),
            ),
            
            'Datos del Cliente' => array(
                array('variable' => 'cliente.nombre', 'descripcion' => 'Nombre del cliente'),
                array('variable' => 'cliente.email', 'descripcion' => 'Email del cliente'),
                array('variable' => 'cliente.telefono', 'descripcion' => 'Teléfono del cliente'),
                array('variable' => 'cliente.rut', 'descripcion' => 'RUT del cliente'),
                array('variable' => 'cliente.direccion', 'descripcion' => 'Dirección del cliente'),
                array('variable' => 'cliente.ciudad', 'descripcion' => 'Ciudad del cliente'),
                array('variable' => 'cliente.region', 'descripcion' => 'Región del cliente'),
            ),
            
            'Datos de la Empresa' => array(
                array('variable' => 'empresa.nombre', 'descripcion' => 'Nombre de la empresa'),
                array('variable' => 'empresa.direccion', 'descripcion' => 'Dirección de la empresa'),
                array('variable' => 'empresa.telefono', 'descripcion' => 'Teléfono de la empresa'),
                array('variable' => 'empresa.email', 'descripcion' => 'Email de la empresa'),
                array('variable' => 'empresa.sitio_web', 'descripcion' => 'Sitio web de la empresa'),
                array('variable' => 'empresa.rut', 'descripcion' => 'RUT de la empresa'),
                array('variable' => 'logo_empresa', 'descripcion' => 'Logo de la empresa (imagen)'),
            ),
            
            'Productos y Servicios' => array(
                array('variable' => 'tabla_productos', 'descripcion' => 'Tabla completa de productos/servicios'),
            ),
            
            'Totales' => array(
                array('variable' => 'totales.subtotal_formateado', 'descripcion' => 'Subtotal con formato'),
                array('variable' => 'totales.descuento_formateado', 'descripcion' => 'Descuento con formato'),
                array('variable' => 'totales.impuestos_formateado', 'descripcion' => 'Impuestos con formato'),
                array('variable' => 'totales.total_formateado', 'descripcion' => 'Total con formato'),
                array('variable' => 'totales.descuento_porcentaje', 'descripcion' => 'Porcentaje de descuento'),
            ),
            
            'Fechas' => array(
                array('variable' => 'fechas.hoy', 'descripcion' => 'Fecha actual'),
                array('variable' => 'fechas.fecha_cotizacion', 'descripcion' => 'Fecha de cotización formateada'),
                array('variable' => 'fechas.fecha_vencimiento_formateada', 'descripcion' => 'Fecha de vencimiento formateada'),
                array('variable' => 'fechas.mes_actual', 'descripcion' => 'Mes actual'),
                array('variable' => 'fechas.año_actual', 'descripcion' => 'Año actual'),
            )
        );
        
        return $variables;
    }

    /**
     * Método de debug para verificar datos de prueba
     */
    public function debug_datos_prueba() {
        $this->generar_datos_prueba('cotizacion');
        return $this->cotizacion_data;
    }
}