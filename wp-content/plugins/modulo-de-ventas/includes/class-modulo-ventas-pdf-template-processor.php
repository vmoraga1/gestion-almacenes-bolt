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
        $tabla_detalles = $wpdb->prefix . 'mv_cotizaciones_items'; // ← NOMBRE CORRECTO
        $tabla_clientes = $wpdb->prefix . 'mv_clientes';
        
        // Cargar cotización principal con nombres de columnas REALES
        $cotizacion = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, 
                    cl.razon_social as cliente_nombre, 
                    cl.email as cliente_email, 
                    cl.telefono as cliente_telefono, 
                    cl.rut as cliente_rut,
                    cl.direccion_facturacion as cliente_direccion, 
                    cl.ciudad_facturacion as cliente_ciudad,
                    cl.region_facturacion as cliente_region,
                    cl.comuna_facturacion as cliente_comuna,
                    cl.giro_comercial as cliente_giro
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
        
        // Mapear nombres de campos de cotizaciones a nombres esperados
        $descuento = isset($cotizacion->descuento_monto) ? floatval($cotizacion->descuento_monto) : 0.0;
        $impuestos = isset($cotizacion->impuesto_monto) ? floatval($cotizacion->impuesto_monto) : 0.0;
        $subtotal = isset($cotizacion->subtotal) ? floatval($cotizacion->subtotal) : 0.0;
        $total = isset($cotizacion->total) ? floatval($cotizacion->total) : 0.0;
        
        // Validar campos de cliente
        $cliente_direccion = isset($cotizacion->cliente_direccion) ? $cotizacion->cliente_direccion : '';
        $cliente_ciudad = isset($cotizacion->cliente_ciudad) ? $cotizacion->cliente_ciudad : '';
        $cliente_region = isset($cotizacion->cliente_region) ? $cotizacion->cliente_region : '';
        $cliente_comuna = isset($cotizacion->cliente_comuna) ? $cotizacion->cliente_comuna : '';
        
        // Preparar datos estructurados
        $this->cotizacion_data = array(
            // Datos de la cotización
            'cotizacion' => array(
                'id' => $cotizacion->id,
                'numero' => isset($cotizacion->folio) ? $cotizacion->folio : $cotizacion->id,
                'fecha' => isset($cotizacion->fecha) ? $cotizacion->fecha : $cotizacion->fecha_creacion,
                'fecha_vencimiento' => isset($cotizacion->fecha_expiracion) ? $cotizacion->fecha_expiracion : null,
                'estado' => $cotizacion->estado,
                'observaciones' => isset($cotizacion->observaciones) ? $cotizacion->observaciones : '',
                'notas_internas' => isset($cotizacion->notas_internas) ? $cotizacion->notas_internas : '',
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestos,
                'total' => $total,
                'vendedor' => isset($cotizacion->vendedor_nombre) ? $cotizacion->vendedor_nombre : get_bloginfo('name'),
                'moneda' => isset($cotizacion->moneda) ? $cotizacion->moneda : 'CLP',
                'plazo_pago' => isset($cotizacion->plazo_pago) ? $cotizacion->plazo_pago : '',
                'terminos_condiciones' => isset($cotizacion->terminos_condiciones) ? $cotizacion->terminos_condiciones : ''
            ),
            
            // Datos del cliente
            'cliente' => array(
                'nombre' => $cotizacion->cliente_nombre ?: 'Cliente sin nombre',
                'razon_social' => $cotizacion->cliente_nombre ?: 'Cliente sin nombre',
                'email' => $cotizacion->cliente_email ?: '',
                'telefono' => $cotizacion->cliente_telefono ?: '',
                'rut' => $cotizacion->cliente_rut ?: '',
                'direccion' => $cliente_direccion,
                'ciudad' => $cliente_ciudad,
                'region' => $cliente_region,
                'comuna' => $cliente_comuna,
                'giro' => isset($cotizacion->cliente_giro) ? $cotizacion->cliente_giro : ''
            ),
            
            // Datos de la empresa
            'empresa' => $this->obtener_datos_empresa(),
            
            // Productos/servicios - usar $es_demo = false para procesar productos reales
            'productos' => $this->procesar_detalles_productos($detalles, false),
            
            // Fechas formateadas
            'fechas' => array(
                'hoy' => current_time('d/m/Y'),
                'fecha_cotizacion' => isset($cotizacion->fecha) ? date('d/m/Y', strtotime($cotizacion->fecha)) : current_time('d/m/Y'),
                'fecha_vencimiento_formateada' => isset($cotizacion->fecha_expiracion) && $cotizacion->fecha_expiracion ? date('d/m/Y', strtotime($cotizacion->fecha_expiracion)) : '',
                'mes_actual' => current_time('F'),
                'año_actual' => current_time('Y')
            ),
            
            // Totales formateados
            'totales' => array(
                'subtotal_formateado' => number_format($subtotal, 0, ',', '.'),
                'descuento_formateado' => number_format($descuento, 0, ',', '.'),
                'impuestos_formateado' => number_format($impuestos, 0, ',', '.'),
                'total_formateado' => number_format($total, 0, ',', '.'),
                'descuento_porcentaje' => $subtotal > 0 ? round(($descuento / $subtotal) * 100, 1) : 0
            )
        );
        
        return true;
    }

    /**
     * Obtener nombre del vendedor
     */
    private function obtener_nombre_vendedor($user_id) {
        if (!$user_id) {
            return get_bloginfo('name') ?: 'Sistema';
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return 'Vendedor';
        }
        
        $nombre = $user->display_name ?: $user->first_name . ' ' . $user->last_name;
        return trim($nombre) ?: $user->user_login;
    }

    /**
     * Formatear fecha
     */
    private function formatear_fecha($fecha, $formato = 'd/m/Y') {
        if (empty($fecha) || $fecha == '0000-00-00' || $fecha == '0000-00-00 00:00:00') {
            return '';
        }
        
        $timestamp = is_numeric($fecha) ? $fecha : strtotime($fecha);
        return $timestamp ? date($formato, $timestamp) : '';
    }
    
    /**
     * Generar datos de prueba para preview
     */
    private function generar_datos_prueba($tipo = 'cotizacion') {
        $productos_demo = array(
            array(
                'codigo' => 'PROD001',
                'sku' => 'PROD001',
                'nombre' => 'Laptop HP Pavilion 15',
                'descripcion' => 'Laptop HP Pavilion 15 con procesador Intel Core i5',
                'cantidad' => 2,
                'precio_unitario' => 650000,
                'descuento' => 0,
                'subtotal' => 1300000
            ),
            array(
                'codigo' => 'SERV001',
                'sku' => 'SERV001',
                'nombre' => 'Instalación y Configuración',
                'descripcion' => 'Servicio de instalación y configuración de software',
                'cantidad' => 1,
                'precio_unitario' => 75000,
                'descuento' => 5000,
                'subtotal' => 70000
            ),
            array(
                'codigo' => 'ACC001',
                'sku' => 'ACC001',
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
            // Datos de la cotización - MISMA ESTRUCTURA que cargar_datos_cotizacion()
            'cotizacion' => array(
                'id' => 1001,
                'numero' => 'COT-2025-001',
                'fecha' => current_time('Y-m-d H:i:s'),
                'fecha_vencimiento' => date('Y-m-d', strtotime('+30 days')),
                'estado' => 'pendiente',
                'observaciones' => 'Esta es una cotización de ejemplo generada automáticamente para mostrar el diseño de la plantilla PDF.',
                'notas_internas' => 'Notas internas solo para uso del equipo de ventas.',
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestos,
                'total' => $total,
                'vendedor' => 'Juan Pérez - Ejecutivo de Ventas',
                'moneda' => 'CLP',
                'plazo_pago' => '30_dias',
                'terminos_condiciones' => 'Términos y condiciones estándar de la empresa.'
            ),
            
            // Datos del cliente - MISMA ESTRUCTURA que cargar_datos_cotizacion()
            'cliente' => array(
                'nombre' => 'Tecnología y Servicios Empresariales Ltda.',
                'razon_social' => 'Tecnología y Servicios Empresariales Ltda.',
                'email' => 'contacto@techservicios.cl',
                'telefono' => '+56 2 2345 6789',
                'rut' => '76.543.210-K',
                'direccion' => 'Av. Providencia 1234, Oficina 567',
                'ciudad' => 'Santiago',
                'region' => 'Región Metropolitana',
                'comuna' => 'Providencia',
                'giro' => 'Servicios de tecnología y consultoría'
            ),
            
            // Datos de la empresa
            'empresa' => $this->obtener_datos_empresa(),
            
            // Productos - usar $es_demo = true para procesar productos demo
            'productos' => $this->procesar_detalles_productos($productos_demo, true),
            
            // Fechas formateadas
            'fechas' => array(
                'hoy' => current_time('d/m/Y'),
                'fecha_cotizacion' => current_time('d/m/Y'),
                'fecha_vencimiento_formateada' => date('d/m/Y', strtotime('+30 days')),
                'mes_actual' => date_i18n('F'),
                'año_actual' => current_time('Y')
            ),
            
            // Totales formateados
            'totales' => array(
                'subtotal_formateado' => number_format($subtotal, 0, ',', '.'),
                'descuento_formateado' => number_format($descuento, 0, ',', '.'),
                'impuestos_formateado' => number_format($impuestos, 0, ',', '.'),
                'total_formateado' => number_format($total, 0, ',', '.'),
                'descuento_porcentaje' => 10.0
            )
        );
        
        return true;
    }
    
    /**
     * Obtener datos de la empresa desde configuración de WordPress
     */
    private function obtener_datos_empresa() {
        return array(
            'nombre' => get_bloginfo('name'),
            'direccion' => get_option('mv_empresa_direccion', ''),
            'telefono' => get_option('mv_empresa_telefono', ''),
            'email' => get_option('mv_empresa_email', get_option('admin_email')),
            'sitio_web' => get_home_url(),
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
    
        if (empty($detalles)) {
            return $productos_procesados;
        }
    
        foreach ($detalles as $detalle) {
            if ($es_demo) {
                // Para datos demo, ya vienen como array
                $producto = $detalle;
            } else {
                // Para datos reales, adaptarse al esquema REAL de wp_mv_cotizaciones_items
                $cantidad = floatval(isset($detalle->cantidad) ? $detalle->cantidad : 0);
                $precio_unitario = floatval(isset($detalle->precio_unitario) ? $detalle->precio_unitario : 0);
                $descuento_monto = floatval(isset($detalle->descuento_monto) ? $detalle->descuento_monto : 0);
                $subtotal = floatval(isset($detalle->subtotal) ? $detalle->subtotal : 0);
                
                $producto = array(
                    'codigo' => isset($detalle->sku) ? $detalle->sku : '',
                    'sku' => isset($detalle->sku) ? $detalle->sku : '',
                    'nombre' => isset($detalle->nombre) ? $detalle->nombre : 'Producto sin nombre',
                    'descripcion' => isset($detalle->descripcion) ? $detalle->descripcion : '',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio_unitario,
                    'descuento' => $descuento_monto, // Usar descuento_monto de la tabla real
                    'subtotal' => $subtotal,
                    'notas' => isset($detalle->notas) ? $detalle->notas : '',
                    'producto_id' => isset($detalle->producto_id) ? $detalle->producto_id : 0,
                    'almacen_id' => isset($detalle->almacen_id) ? $detalle->almacen_id : 0
                );
            }
        
            // Agregar campos calculados y formateados
            $producto['precio_unitario_formateado'] = number_format($producto['precio_unitario'], 0, ',', '.');
            $producto['descuento_formateado'] = number_format($producto['descuento'], 0, ',', '.');
            $producto['subtotal_formateado'] = number_format($producto['subtotal'], 0, ',', '.');
            $producto['precio_con_descuento'] = $producto['precio_unitario'] - $producto['descuento'];
            $producto['precio_con_descuento_formateado'] = number_format($producto['precio_con_descuento'], 0, ',', '.');
        
            // Alias para compatibilidad
            $producto['precio'] = $producto['precio_unitario_formateado'];
            $producto['total'] = $producto['subtotal_formateado'];
        
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
        
        // 1. Procesar condicionales {{#if variable}}...{{/if}}
        $contenido = preg_replace_callback('/\{\{#if\s+([^}]+)\}\}(.*?)\{\{\/if\}\}/s', function($matches) {
            $variable = trim($matches[1]);
            $contenido_condicional = $matches[2];
            
            $valor = $this->obtener_valor_variable_handlebars($variable);
            
            // Mostrar contenido si la variable existe y no está vacía
            if (!empty($valor) && $valor !== '0' && $valor !== 0 && $valor !== 'false') {
                return $this->procesar_contenido($contenido_condicional);
            }
            
            return '';
        }, $contenido);
        
        // 2. Procesar bucles {{#each array}}...{{/each}}
        $contenido = preg_replace_callback('/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s', function($matches) {
            $variable = trim($matches[1]);
            $template_item = $matches[2];
            
            $array = $this->obtener_valor_variable_handlebars($variable);
            
            if (!is_array($array) || empty($array)) {
                return '';
            }
            
            $resultado = '';
            foreach ($array as $index => $item) {
                // Crear contexto temporal para el item
                $contexto_temporal = $this->cotizacion_data;
                
                // Agregar propiedades del item al contexto
                if (is_array($item)) {
                    foreach ($item as $key => $value) {
                        $contexto_temporal[$key] = $value;
                    }
                }
                
                // Agregar variables especiales del bucle
                $contexto_temporal['@index'] = $index;
                $contexto_temporal['@first'] = ($index === 0);
                $contexto_temporal['@last'] = ($index === (count($array) - 1));
                
                // Crear una nueva instancia temporal para procesar el item
                $procesador_temporal = clone $this;
                $procesador_temporal->cotizacion_data = $contexto_temporal;
                
                $resultado .= $procesador_temporal->procesar_contenido($template_item);
            }
            
            return $resultado;
        }, $contenido);
        
        // 3. Procesar variables simples {{variable}}
        $patron = '/\{\{([^}]+)\}\}/';
        
        return preg_replace_callback($patron, function($matches) {
            $variable = trim($matches[1]);
            return $this->obtener_valor_variable($variable);
        }, $contenido);
    }

    /**
     * Obtener valor de variable para Handlebars
     */
    private function obtener_valor_variable_handlebars($variable) {
        $variable = trim($variable);
        
        // Variables especiales
        if (in_array($variable, ['productos', 'items'])) {
            return isset($this->cotizacion_data['productos']) ? $this->cotizacion_data['productos'] : array();
        }
        
        // Usar el método existente
        return $this->obtener_valor_variable($variable);
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
            return '<p style="text-align: center; color: #666; font-style: italic; padding: 20px;">No hay productos en esta cotización.</p>';
        }
        
        $html = '<table class="productos-tabla" style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 11px; border: 1px solid #ddd;">
                    <thead>
                        <tr style="background-color: #2c5aa0;">
                            <th style="color: white; border: 1px solid #2c5aa0; padding: 10px 8px; text-align: left; font-weight: bold;">Código</th>
                            <th style="color: white; border: 1px solid #2c5aa0; padding: 10px 8px; text-align: left; font-weight: bold;">Producto/Servicio</th>
                            <th style="color: white; border: 1px solid #2c5aa0; padding: 10px 8px; text-align: center; font-weight: bold; width: 80px;">Cant.</th>
                            <th style="color: white; border: 1px solid #2c5aa0; padding: 10px 8px; text-align: right; font-weight: bold; width: 100px;">Precio Unit.</th>
                            <th style="color: white; border: 1px solid #2c5aa0; padding: 10px 8px; text-align: right; font-weight: bold; width: 80px;">Desc.</th>
                            <th style="color: white; border: 1px solid #2c5aa0; padding: 10px 8px; text-align: right; font-weight: bold; width: 100px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($this->cotizacion_data['productos'] as $index => $producto) {
            $background = ($index % 2 == 0) ? '#ffffff' : '#f9f9f9';
            
            $html .= '<tr style="background-color: ' . $background . ';">
                        <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top; font-family: monospace;">' . esc_html($producto['codigo']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; vertical-align: top;">
                            <strong>' . esc_html($producto['nombre']) . '</strong>';
            
            if (!empty($producto['descripcion']) && $producto['descripcion'] !== $producto['nombre']) {
                $html .= '<br><small style="color: #666; line-height: 1.2;">' . esc_html($producto['descripcion']) . '</small>';
            }
            
            $html .= '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: top; font-weight: bold;">' . esc_html($producto['cantidad']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top; font-family: monospace;">$' . esc_html($producto['precio_unitario_formateado']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top; font-family: monospace; color: #d63384;">$' . esc_html($producto['descuento_formateado']) . '</td>
                        <td style="border: 1px solid #ddd; padding: 8px; text-align: right; vertical-align: top; font-weight: bold; font-family: monospace;">$' . esc_html($producto['subtotal_formateado']) . '</td>
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

    /**
     * Método de preview mejorado
     */
    public function generar_preview_plantilla($html_content, $css_content, $cotizacion_id = 0) {
        $this->logger->log("TEMPLATE_PROCESSOR: Generando preview de plantilla");
        
        if ($cotizacion_id > 0) {
            // Usar datos reales si se proporciona una cotización
            $this->cargar_datos_cotizacion($cotizacion_id);
        } else {
            // Usar datos de prueba
            $this->generar_datos_prueba('cotizacion');
        }
        
        // Procesar contenido
        $html_procesado = $this->procesar_contenido($html_content, 'cotizacion');
        $css_procesado = $this->procesar_contenido($css_content, 'cotizacion');
        
        // Generar documento final
        $documento_final = $this->generar_documento_final($html_procesado, $css_procesado);
        
        // Guardar temporalmente para preview
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/modulo-ventas/temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $timestamp = current_time('Y-m-d_H-i-s');
        $filename = "preview_plantilla_{$timestamp}.html";
        $filepath = $temp_dir . '/' . $filename;
        
        file_put_contents($filepath, $documento_final);
        
        // Devolver URL del preview
        $preview_url = $upload_dir['baseurl'] . '/modulo-ventas/temp/' . $filename;
        
        return $preview_url;
    }
}