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
     * Datos de la cotización/documento actual
     */
    private $cotizacion_data;

    /**
     * Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cotizacion_data = null;
        
        // Cargar el logger si no está disponible
        if (!class_exists('Modulo_Ventas_Logger')) {
            $logger_file = MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-logger.php';
            if (file_exists($logger_file)) {
                require_once $logger_file;
            }
        }
        
        // Inicializar logger si existe
        if (class_exists('Modulo_Ventas_Logger')) {
            $this->logger = Modulo_Ventas_Logger::get_instance();
        }
    }

    /**
     * Cargar datos en el procesador - VERSIÓN CORREGIDA
     */
    public function cargar_datos($datos) {
        $this->logger->log("PROCESSOR: Iniciando carga de datos");
        
        // Verificar que $datos no esté vacío
        if (empty($datos)) {
            $this->logger->log("PROCESSOR: ERROR - Datos vacíos recibidos", 'error');
            return false;
        }
        
        // Verificar si es array u objeto
        if (is_array($datos)) {
            $this->datos = $datos;
            $this->logger->log("PROCESSOR: Datos cargados desde array");
        } elseif (is_object($datos)) {
            $this->datos = (array) $datos;
            $this->logger->log("PROCESSOR: Datos cargados desde objeto convertido a array");
        } else {
            $this->logger->log("PROCESSOR: ERROR - Tipo de datos no válido: " . gettype($datos), 'error');
            return false;
        }
        
        // AGREGAR: Verificar estructura de datos
        $claves_principales = array('cotizacion', 'cliente', 'empresa', 'productos', 'totales', 'fechas');
        $claves_encontradas = array_intersect($claves_principales, array_keys($this->datos));
        
        $this->logger->log("PROCESSOR: Datos cargados exitosamente. Claves principales: " . implode(', ', $claves_encontradas));
        
        // AGREGAR: Debug de contenido de empresa
        if (isset($this->datos['empresa'])) {
            $this->logger->log("PROCESSOR: Datos de empresa disponibles - nombre: " . (isset($this->datos['empresa']['nombre']) ? $this->datos['empresa']['nombre'] : 'NO_DISPONIBLE'));
        } else {
            $this->logger->log("PROCESSOR: ERROR - No hay datos de empresa", 'error');
        }
        
        return true;
    }

    /**
     * Procesar template HTML con los datos cargados
     */
    public function procesar_template($html_content) {
        // CORREGIR: Verificar que hay datos cargados
        if (empty($this->datos) && !empty($this->cotizacion_data)) {
            $this->datos = $this->cotizacion_data;
            $this->logger->log("PROCESSOR: Sincronizando datos desde cotizacion_data");
        }

        // AGREGAR: Verificar que hay datos cargados
        if (empty($this->datos)) {
            error_log("PROCESSOR: ERROR - No hay datos cargados para procesar template");
            return $html_content; // Devolver contenido sin procesar
        }
        
        error_log("PROCESSOR: Procesando template con datos disponibles");

        $this->logger->log("PROCESSOR: Procesando template con datos disponibles");
        $this->logger->log("PROCESSOR: Claves principales: " . implode(', ', array_keys($this->datos)));
        
        // Continuar con el procesamiento normal...
        return $this->procesar_contenido($html_content);
    }

    /**
     * Procesar CSS con los datos cargados
     */
    public function procesar_css($css_content) {
        if (empty($css_content)) {
            error_log("PROCESSOR: CSS vacío recibido");
            return '';
        }
        
        error_log("PROCESSOR: Procesando CSS de " . strlen($css_content) . " caracteres");
        
        // Remover tags <style> si están presentes (evitar anidamiento)
        $css_limpio = $css_content;
        if (strpos($css_limpio, '<style>') !== false) {
            $css_limpio = preg_replace('/<style[^>]*>/', '', $css_limpio);
            $css_limpio = str_replace('</style>', '', $css_limpio);
            error_log("PROCESSOR: Removidos tags <style> anidados");
        }
        
        // Procesar variables si las hay
        $css_procesado = $this->procesar_contenido($css_limpio);
        
        $resultado = trim($css_procesado);
        error_log("PROCESSOR: CSS procesado - " . strlen($resultado) . " caracteres finales");
        
        return $resultado;
    }

    /**
     * Limpia CSS
     */
    private function limpiar_css_para_pdf($css) {
        // Remover @import y @media queries problemáticas
        $css = preg_replace('/@import[^;]+;/', '', $css);
        $css = preg_replace('/@media[^{]+\{[^{}]*\{[^{}]*\}[^{}]*\}/', '', $css);
        
        // Convertir flexbox problemático a display: block
        $css = str_replace('display: flex', 'display: block', $css);
        $css = str_replace('display:flex', 'display:block', $css);
        
        // Remover propiedades CSS problemáticas para PDF
        $propiedades_problematicas = array(
            'transform:',
            'transition:',
            'animation:',
            'box-shadow:',
            'filter:',
            'backdrop-filter:'
        );
        
        foreach ($propiedades_problematicas as $prop) {
            $css = preg_replace('/' . preg_quote($prop) . '[^;]+;/', '', $css);
        }
        
        return $css;
    }

    /**
     * Procesar variables simples como {{variable}}
     */
    private function procesar_variables_simples($html, $datos) {
        // Buscar todas las variables con el patrón {{variable}}
        preg_match_all('/\{\{([^}]+)\}\}/', $html, $matches);
        
        foreach ($matches[0] as $i => $variable_completa) {
            $variable = trim($matches[1][$i]);
            $valor = $this->obtener_valor_variable($variable, $datos);
            $html = str_replace($variable_completa, $valor, $html);
        }
        
        return $html;
    }

    /**
     * Procesar variables complejas como tablas
     */
    private function procesar_variables_complejas($html, $datos) {
        // Procesar tabla de productos si existe
        if (strpos($html, '{{tabla_productos}}') !== false) {
            $tabla_html = $this->generar_tabla_productos($datos);
            $html = str_replace('{{tabla_productos}}', $tabla_html, $html);
        }
        
        // Procesar logo de empresa si existe
        if (strpos($html, '{{logo_empresa}}') !== false) {
            $logo_html = $this->procesar_logo_empresa($datos);
            $html = str_replace('{{logo_empresa}}', $logo_html, $html);
        }
        
        return $html;
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
     * Verificar si el procesador tiene datos cargados
     */
    public function tiene_datos() {
        return !empty($this->datos);
    }
    
    /**
     * Procesar plantilla completa para visualización web
     */
    public function procesar_plantilla($html_content, $datos, $tipo = 'cotizacion') {
        $this->logger->log("PROCESSOR: Procesando plantilla para visualización web - tipo: {$tipo}");
        
        // Cargar datos
        if (is_numeric($datos)) {
            // Si es un ID, cargar datos de la base de datos
            $this->cargar_datos_documento($tipo, $datos);
        } elseif (is_array($datos) || is_object($datos)) {
            // Si son datos directos, cargarlos
            $this->cargar_datos($datos);
        } else {
            throw new Exception('Datos inválidos para procesar plantilla');
        }
        
        if (!$this->tiene_datos()) {
            throw new Exception('No se pudieron cargar los datos para la plantilla');
        }
        
        // Procesar contenido HTML
        $html_procesado = $this->procesar_contenido($html_content);
        
        // Optimizar para web
        $html_optimizado = $this->optimizar_html_para_web($html_procesado);
        
        $this->logger->log("PROCESSOR: Plantilla procesada exitosamente para web");
        
        return $html_optimizado;
    }

    /*
     * Procesar plantilla completa con datos de cotización
     *
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
    }*/
    
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
    public function cargar_datos_cotizacion($cotizacion_id) {
        global $wpdb;
        
        $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        $tabla_detalles = $wpdb->prefix . 'mv_cotizaciones_items'; // ← NOMBRE CORRECTO
        $tabla_clientes = $wpdb->prefix . 'mv_clientes';

        $empresa_data = array(
            'nombre' => get_option('blogname', 'Mi Empresa'),
            'logo' => get_option('modulo_ventas_logo_empresa', '')
        );
        
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
                'folio' => isset($cotizacion->folio) ? $cotizacion->folio : 'COT-' . str_pad($cotizacion->id, 6, '0', STR_PAD_LEFT),
                'numero' => isset($cotizacion->folio) ? $cotizacion->folio : $cotizacion->id,
                'fecha' => isset($cotizacion->fecha) ? $cotizacion->fecha : $cotizacion->fecha_creacion,
                'fecha_expiracion' => isset($cotizacion->fecha_expiracion) && $cotizacion->fecha_expiracion ? 
                                    date('d/m/Y', strtotime($cotizacion->fecha_expiracion)) : 
                                    date('d/m/Y', strtotime('+30 days')),
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
                'rut' => !empty($cotizacion->cliente_rut) ? $this->formatear_rut($cotizacion->cliente_rut) : '',
                'rut_sin_formato' => $cotizacion->cliente_rut ?? '',
                'direccion' => $cliente_direccion,
                'ciudad' => $cliente_ciudad,
                'region' => $cliente_region,
                'comuna' => $cliente_comuna,
                'giro' => isset($cotizacion->cliente_giro) ? $cotizacion->cliente_giro : ''
            ),
            
            // Datos de la empresa
            'empresa' => array(
                'nombre' => get_option('blogname', 'Mi Empresa'),
                'rut' => get_option('modulo_ventas_rut_empresa', '12.345.678-9'),
                'rut_formateado' => $this->formatear_rut($empresa->rut ?? ''),
                'direccion' => get_option('modulo_ventas_direccion_empresa', 'Dirección de la empresa'),
                'ciudad' => get_option('modulo_ventas_ciudad_empresa', 'Santiago'),
                'region' => get_option('modulo_ventas_region_empresa', 'Región Metropolitana'),
                'telefono' => get_option('modulo_ventas_telefono_empresa', '+56 2 1234 5678'),
                'email' => get_option('modulo_ventas_email_empresa', 'contacto@empresa.cl'),
                'sitio_web' => get_option('home', home_url()),
                
                // USAR función existente:
                'logo_empresa' => '',
                'info' => get_option('modulo_ventas_info_empresa', 'Información adicional de la empresa')
            ),
            
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

            'sistema' => array(
                'version' => '1.0',
                'usuario' => wp_get_current_user()->display_name ?: 'Sistema',
                'fecha_generacion' => current_time('d/m/Y H:i'),
                'host' => $_SERVER['HTTP_HOST'] ?? 'localhost'
            ),
            
            // Totales formateados
            'totales' => array(
                'subtotal' => $subtotal,
                'subtotal_formateado' => number_format($subtotal, 0, ',', '.'),
                'descuento' => $descuento,
                'descuento_formateado' => number_format($descuento, 0, ',', '.'),
                'descuento_porcentaje' => $cotizacion->tipo_descuento === 'porcentaje' 
                    ? (isset($cotizacion->descuento_porcentaje) ? $cotizacion->descuento_porcentaje : 0)
                    : ($subtotal > 0 ? round(($descuento / $subtotal) * 100, 1) : 0),

                'costo_envio' => isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0,
                'costo_envio_formateado' => '' . number_format(isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0, 0, ',', '.'),
                'subtotal_con_descuento' => $subtotal - $descuento,
                'subtotal_con_descuento_formateado' => '' . number_format($subtotal - $descuento, 0, ',', '.'),
                'subtotal_con_envio' => ($subtotal - $descuento) + (isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0),
                'subtotal_con_envio_formateado' => '' . number_format(($subtotal - $descuento) + (isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0), 0, ',', '.'),
                
                'impuestos' => $impuestos,
                'impuestos_formateado' => number_format($impuestos, 0, ',', '.'),
                'total' => $total,
                'total_formateado' => number_format($total, 0, ',', '.'),
                'moneda' => $cotizacion->moneda ?? 'CLP'
            )
        );
        // CORREGIR: Sincronizar las dos propiedades
        $this->datos = $this->cotizacion_data;
        
        $this->logger->log("TEMPLATE_PROCESSOR: Datos sincronizados entre cotizacion_data y datos");
        $this->logger->log("TEMPLATE_PROCESSOR: tiene_datos() ahora devuelve: " . ($this->tiene_datos() ? 'TRUE' : 'FALSE'));
                
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
        $this->logger->log("TEMPLATE_PROCESSOR: Generando datos de prueba para: {$tipo}");
        
        $productos_demo = array(
            array(
                'codigo' => 'PROD001',
                'sku' => 'PROD001',
                'nombre' => 'Laptop HP Pavilion 15',
                'descripcion' => 'Laptop HP Pavilion 15 con procesador Intel Core i5',
                'cantidad' => 2,
                'precio_unitario' => 650000,
                'precio_unitario_formateado' => '$650.000',
                'descuento' => 0,
                'descuento_formateado' => '$0',
                'subtotal' => 1300000,
                'subtotal_formateado' => '$1.300.000'
            ),
            array(
                'codigo' => 'SERV001',
                'sku' => 'SERV001',
                'nombre' => 'Instalación y Configuración',
                'descripcion' => 'Servicio de instalación y configuración de software',
                'cantidad' => 1,
                'precio_unitario' => 75000,
                'precio_unitario_formateado' => '$75.000',
                'descuento' => 5000,
                'descuento_formateado' => '$5.000',
                'subtotal' => 70000,
                'subtotal_formateado' => '$70.000'
            ),
            array(
                'codigo' => 'ACC001',
                'sku' => 'ACC001',
                'nombre' => 'Mouse Inalámbrico',
                'descripcion' => 'Mouse inalámbrico ergonómico',
                'cantidad' => 2,
                'precio_unitario' => 25000,
                'precio_unitario_formateado' => '$25.000',
                'descuento' => 0,
                'descuento_formateado' => '$0',
                'subtotal' => 50000,
                'subtotal_formateado' => '$50.000'
            )
        );
        
        $subtotal = 1420000;
        $descuento = 142000; // 10%
        $impuestos = 242820; // 19% sobre (subtotal - descuento)
        $total = 1520820;
        
        // CRÍTICO: ASIGNAR INMEDIATAMENTE sin llamar a métodos que puedan causar bucles
        $this->cotizacion_data = array(
            // Datos de la cotización - MISMA ESTRUCTURA que cargar_datos_cotizacion()
            'cotizacion' => array(
                'id' => $cotizacion->id,
                'numero' => isset($cotizacion->folio) ? $cotizacion->folio : $cotizacion->id,
                'folio' => isset($cotizacion->folio) ? $cotizacion->folio : 'COT-' . str_pad($cotizacion->id, 6, '0', STR_PAD_LEFT),
                'fecha' => current_time('Y-m-d H:i:s'),
                'fecha_expiracion' => isset($cotizacion->fecha_vencimiento) && $cotizacion->fecha_vencimiento ? 
                        date('d/m/Y', strtotime($cotizacion->fecha_vencimiento)) : 
                        (isset($cotizacion->fecha_expiracion) && $cotizacion->fecha_expiracion ? 
                        date('d/m/Y', strtotime($cotizacion->fecha_expiracion)) : 
                        date('d/m/Y', strtotime('+30 days'))), // Fallback: 30 días desde hoy
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
                'terminos_condiciones' => 'Términos y condiciones estándar de la empresa.',
                'costo_envio' => isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0,
                'costo_envio_formateado' => '$' . number_format(isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0, 0, ',', '.'),
                'condiciones_pago' => isset($cotizacion->condiciones_pago) ? $cotizacion->condiciones_pago : '',
                'plazo_pago' => isset($cotizacion->plazo_pago) ? $cotizacion->plazo_pago : '',
                'subtotal_con_envio' => ($subtotal - $descuento) + (isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0),
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
            
            // Datos de la empresa - DIRECTO sin llamar métodos que puedan fallar
            'empresa' => array(
                'nombre' => get_option('blogname', 'Mi Empresa'),
                'rut' => get_option('mv_empresa_rut', '12.345.678-9'),
                'direccion' => get_option('mv_empresa_direccion', 'Dirección de la empresa'),
                'ciudad' => get_option('mv_empresa_ciudad', 'Santiago'),
                'region' => get_option('mv_empresa_region', 'Región Metropolitana'),
                'telefono' => get_option('mv_empresa_telefono', '+56 2 1234 5678'),
                'email' => get_option('admin_email', 'contacto@empresa.cl'),
                'sitio_web' => get_option('home', home_url()),
                'logo' => $this->procesar_logo_empresa(),
                'info' => get_option('modulo_ventas_info_empresa')
            ),
            
            // Productos - DIRECTAMENTE procesados, sin llamar a métodos externos
            'productos' => $productos_demo,
            
            // Fechas formateadas
            'fechas' => array(
                'hoy' => current_time('d/m/Y'),
                'fecha_cotizacion' => current_time('d/m/Y'),
                'fecha_vencimiento_formateada' => date('d/m/Y', strtotime('+30 days')),
                'mes_actual' => date_i18n('F'),
                'año_actual' => current_time('Y')
            ),

            'sistema' => array(
                'version' => '1.0',
                'usuario' => wp_get_current_user()->display_name ? wp_get_current_user()->display_name : 'Sistema',
                'fecha_generacion' => current_time('d/m/Y H:i'),
                'host' => $_SERVER['HTTP_HOST'] ?? 'localhost'
            ),
            
            // Totales formateados
            'totales' => array(
                'subtotal' => $subtotal,
                'subtotal_formateado' => number_format($subtotal, 0, ',', '.'),
                'descuento' => $descuento,
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
        
        if (!$this->cotizacion_data) {
            error_log('PROCESSOR: ERROR - procesar_contenido llamado sin datos');
            return $contenido;
        }
        
        // Log inicial
        $variables_iniciales = preg_match_all('/\{\{[^}]+\}\}/', $contenido, $matches_iniciales);
        error_log('PROCESSOR: Variables encontradas inicialmente: ' . $variables_iniciales);
        if ($variables_iniciales > 0) {
            error_log('PROCESSOR: Variables a procesar: ' . implode(', ', array_slice($matches_iniciales[0], 0, 5)));
        }
        
        // 1. Procesar condicionales {{#if variable}}...{{/if}}
        $contenido = preg_replace_callback('/\{\{#if\s+([^}]+)\}\}(.*?)\{\{\/if\}\}/s', function($matches) {
            $variable = trim($matches[1]);
            $contenido_condicional = $matches[2];
            
            $valor = $this->obtener_valor_variable($variable);
            
            // Considerar como "true" si no está vacío
            return !empty($valor) ? $contenido_condicional : '';
        }, $contenido);
        
        // 2. Procesar bucles {{#each array}}...{{/each}}
        $contenido = preg_replace_callback('/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s', function($matches) {
            $variable = trim($matches[1]);
            $template_item = $matches[2];
            
            $array = $this->obtener_valor_variable($variable);
            
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
        
        $contenido_procesado = preg_replace_callback($patron, function($matches) {
            $variable = trim($matches[1]);
            $valor = $this->obtener_valor_variable($variable);
            
            // Debug de cada variable procesada
            error_log('PROCESSOR: Procesando variable: ' . $variable . ' = ' . 
                    (is_string($valor) ? substr($valor, 0, 50) : gettype($valor)));
            
            return $valor;
        }, $contenido);
        
        // Log final
        $variables_finales = preg_match_all('/\{\{[^}]+\}\}/', $contenido_procesado, $matches_finales);
        error_log('PROCESSOR: Variables restantes después del procesamiento: ' . $variables_finales);
        
        return $contenido_procesado;
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
            error_log('PROCESSOR: obtener_valor_variable - No hay datos cargados');
            return '{{' . $variable . '}}'; // Devolver sin procesar
        }
        
        // Variables especiales que necesitan procesamiento inmediato
        $variables_especiales = array(
            'tabla_productos',
            'logo_empresa', 
            'fecha_actual',
            'hora_actual'
        );
        
        if (in_array($variable, $variables_especiales)) {
            return $this->procesar_variable_especial($variable, null);
        }
        
        // Manejar variables con puntos (ej: empresa.nombre)
        $partes = explode('.', $variable);
        $valor = $this->cotizacion_data;
        
        foreach ($partes as $parte) {
            if (is_array($valor) && isset($valor[$parte])) {
                $valor = $valor[$parte];
            } elseif (is_object($valor) && isset($valor->$parte)) {
                $valor = $valor->$parte;
            } else {
                // Variable no encontrada
                error_log('PROCESSOR: Variable no encontrada: ' . $variable);
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
                $datos_empresa = (object)array('nombre' => get_option('blogname', 'Mi Empresa'));
                return $this->generar_logo_empresa($datos_empresa);
                
            case 'fecha_actual':
                return current_time('d/m/Y');
                
            case 'hora_actual':
                return current_time('H:i');
                
            default:
                // Para valores normales, asegurar que sean strings
                if (is_array($valor) || is_object($valor)) {
                    return json_encode($valor);
                }
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
    private function procesar_logo_empresa($datos) {
        $logo_url = '';
        
        if (isset($datos['logo_empresa']) && $datos['logo_empresa']) {
            $logo_url = $datos['logo_empresa'];
        } elseif (isset($datos['empresa']['logo']) && $datos['empresa']['logo']) {
            $logo_url = $datos['empresa']['logo'];
        }
        
        if ($logo_url) {
            return '<img src="' . esc_url($logo_url) . '" alt="Logo" class="logo-empresa" style="max-height: 80px; max-width: 200px;">';
        }
        
        return '';
    }

    /**
     * NUEVO: Generar HTML del logo de la empresa
     */
    private function generar_logo_empresa($datos_empresa) {
        $logo_url = '';
        
        // Buscar logo en el sistema nuevo primero
        $config = get_option('mv_pdf_config', array());
        if (isset($config['empresa_logo']) && !empty($config['empresa_logo'])) {
            $logo_url = $config['empresa_logo'];
        } else {
            // Fallback al sistema antiguo
            $logo_id = get_option('modulo_ventas_logo_empresa', '');
            if ($logo_id) {
                // Si es un ID de attachment
                if (is_numeric($logo_id)) {
                    $logo_url = wp_get_attachment_url($logo_id);
                } else {
                    // Si es una URL directa
                    $logo_url = $logo_id;
                }
            }
        }
        
        if ($logo_url) {
            $nombre_empresa = isset($datos_empresa->nombre) ? $datos_empresa->nombre : 'Empresa';
            return '<img src="' . esc_url($logo_url) . '" alt="Logo ' . esc_attr($nombre_empresa) . '" style="max-height: 80px; max-width: 200px; width: auto; height: auto;" />';
        }
        
        // Fallback: placeholder del logo
        $nombre_empresa = isset($datos_empresa->nombre) ? $datos_empresa->nombre : 'Empresa';
        return '<div style="width: 150px; height: 80px; background: #f5f5f5; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666; text-align: center;">
            <div>
                <div style="font-weight: bold;">' . esc_html($nombre_empresa) . '</div>
                <div style="font-size: 10px; margin-top: 5px;">Logo</div>
            </div>
        </div>';
    }
    
    /**
     * Generar documento HTML final
     */
    public function generar_documento_final($html_procesado, $css_procesado) {
        error_log("PREVIEW: === INICIO GENERAR_DOCUMENTO_FINAL ===");
        error_log("PREVIEW: HTML recibido: " . strlen($html_procesado) . " caracteres");
        error_log("PREVIEW: CSS recibido: " . strlen($css_procesado) . " caracteres");
        
        // Verificar contenido recibido
        $html_valido = !empty($html_procesado) && strlen(trim($html_procesado)) > 10;
        $css_valido = !empty($css_procesado) && strlen(trim($css_procesado)) > 10;
        
        error_log("PREVIEW: HTML válido: " . ($html_valido ? 'SI' : 'NO'));
        error_log("PREVIEW: CSS válido: " . ($css_valido ? 'SI' : 'NO'));
        
        // Limpiar CSS de posibles tags anidados
        $css_limpio = '';
        if ($css_valido) {
            $css_limpio = $css_procesado;
            if (strpos($css_limpio, '<style>') !== false) {
                $css_limpio = preg_replace('/<style[^>]*>/', '', $css_limpio);
                $css_limpio = str_replace('</style>', '', $css_limpio);
                error_log("PREVIEW: CSS contenía tags <style> anidados - removidos");
            }
            $css_limpio = trim($css_limpio);
        }
        
        // CONSTRUIR DOCUMENTO PASO A PASO
        $partes_documento = array();
        
        // 1. DOCTYPE y apertura HTML
        $partes_documento[] = '<!DOCTYPE html>';
        $partes_documento[] = '<html lang="es">';
        $partes_documento[] = '<head>';
        $partes_documento[] = '    <meta charset="UTF-8">';
        $partes_documento[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $partes_documento[] = '    <title>Vista Previa - Plantilla PDF</title>';
        
        // 2. Agregar CSS
        if ($css_valido && !empty($css_limpio)) {
            $partes_documento[] = '    <style type="text/css">';
            $partes_documento[] = '/* CSS de la plantilla */';
            $partes_documento[] = $css_limpio;
            $partes_documento[] = '    </style>';
            error_log("PREVIEW: CSS agregado como partes separadas (" . strlen($css_limpio) . " caracteres)");
        } else {
            // CSS básico de fallback
            $partes_documento[] = '    <style type="text/css">';
            $partes_documento[] = '/* CSS de fallback */';
            $partes_documento[] = 'body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; color: #333; background: #f9f9f9; }';
            $partes_documento[] = '.documento { max-width: 800px; margin: 0 auto; padding: 20px; background: white; border: 1px solid #ddd; border-radius: 5px; }';
            $partes_documento[] = 'table { border-collapse: collapse; width: 100%; margin: 10px 0; }';
            $partes_documento[] = 'td, th { padding: 8px; border: 1px solid #ddd; text-align: left; }';
            $partes_documento[] = '.header { background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-bottom: 2px solid #ccc; }';
            $partes_documento[] = '.productos-tabla { width: 100%; border-collapse: collapse; }';
            $partes_documento[] = '.productos-tabla th { background: #f0f0f0; font-weight: bold; }';
            $partes_documento[] = '    </style>';
            error_log("PREVIEW: CSS de fallback agregado como partes separadas");
        }
        
        // 3. Cerrar head y abrir body
        $partes_documento[] = '</head>';
        $partes_documento[] = '<body>';
        
        // 4. Agregar contenido HTML
        if ($html_valido) {
            $partes_documento[] = $html_procesado;
        } else {
            $partes_documento[] = '<div class="documento">';
            $partes_documento[] = '    <h1>Error: No hay contenido HTML para mostrar</h1>';
            $partes_documento[] = '    <p>HTML recibido: ' . strlen($html_procesado) . ' caracteres</p>';
            $partes_documento[] = '</div>';
            error_log("PREVIEW: USANDO HTML DE FALLBACK");
        }
        
        // 5. Cerrar body y html
        $partes_documento[] = '</body>';
        $partes_documento[] = '</html>';
        
        // 6. ENSAMBLAR DOCUMENTO FINAL
        $documento = implode("\n", $partes_documento);
        
        // Verificaciones finales CORREGIDAS
        $longitud_total = strlen($documento);
        $contiene_style = strpos($documento, '<style') !== false;  // CORREGIDO: sin >
        $contiene_style_close = strpos($documento, '</style>') !== false;
        $contiene_body = strpos($documento, '<body>') !== false && strpos($documento, '</body>') !== false;

        error_log("PREVIEW: === RESULTADO FINAL ===");
        error_log("PREVIEW: Longitud total: {$longitud_total} caracteres");
        error_log("PREVIEW: Contiene <style: " . ($contiene_style ? 'SI' : 'NO'));
        error_log("PREVIEW: Contiene </style>: " . ($contiene_style_close ? 'SI' : 'NO'));
        error_log("PREVIEW: Estructura body válida: " . ($contiene_body ? 'SI' : 'NO'));

        // DEBUG ADICIONAL: Mostrar primeros 500 caracteres del documento
        error_log("PREVIEW: Primeros 500 caracteres del documento:");
        error_log(substr($documento, 0, 500));

        // Log final para el debug principal - SOLO UNA VEZ
        $css_final = $contiene_style && $contiene_style_close ? 'SI' : 'NO';
        $contenido_final = $html_valido ? 'SI' : 'NO';
        error_log("PREVIEW: DOCUMENTO FINAL DEFINITIVO - Longitud: {$longitud_total}, CSS: {$css_final}, Contenido: {$contenido_final}");

        return $documento;

        // TEST TEMPORAL: Guardar archivo para inspección directa
        $upload_dir = wp_upload_dir();
        $test_path = $upload_dir['basedir'] . '/debug-documento.html';
        file_put_contents($test_path, $documento);
        $test_url = $upload_dir['baseurl'] . '/debug-documento.html';
        error_log("PREVIEW: Documento guardado para test en: {$test_url}");
    }

    /**
     * Formatear RUT chileno
     */
    private function formatear_rut($rut) {
        if (empty($rut)) {
            return '';
        }
        
        // Limpiar el RUT (solo números y K/k)
        $rut_limpio = preg_replace('/[^0-9Kk]/', '', $rut);
        
        if (strlen($rut_limpio) < 2) {
            return $rut; // Devolver original si es muy corto
        }
        
        // Separar dígito verificador
        $dv = substr($rut_limpio, -1);
        $numero = substr($rut_limpio, 0, -1);
        
        // Formatear con puntos
        $numero_formateado = number_format($numero, 0, '', '.');
        
        return $numero_formateado . '-' . strtoupper($dv);
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
     * MÉTODO DE VERIFICACIÓN: debug_datos_cargados
     */
    public function debug_datos_cargados() {
        if (empty($this->cotizacion_data)) {
            return 'No hay datos cargados';
        }
        
        $info = array();
        $info[] = 'Claves principales: ' . implode(', ', array_keys($this->cotizacion_data));
        
        if (isset($this->cotizacion_data['empresa'])) {
            $info[] = 'Empresa: ' . (isset($this->cotizacion_data['empresa']['nombre']) ? 
                                    $this->cotizacion_data['empresa']['nombre'] : 'Sin nombre');
        }
        
        if (isset($this->cotizacion_data['cliente'])) {
            $info[] = 'Cliente: ' . (isset($this->cotizacion_data['cliente']['nombre']) ? 
                                    $this->cotizacion_data['cliente']['nombre'] : 'Sin nombre');
        }
        
        if (isset($this->cotizacion_data['productos'])) {
            $info[] = 'Productos: ' . count($this->cotizacion_data['productos']) . ' items';
        }
        
        return implode(' | ', $info);
    }

    /**
     * Formatear productos desde items de cotización - MÉTODO REQUERIDO
     */
    public function formatear_productos_desde_items($items) {
        $productos = array();
        
        if (!is_array($items) && !is_object($items)) {
            return $productos;
        }
        
        if (is_object($items)) {
            $items = (array) $items;
        }
        
        foreach ($items as $item) {
            if (is_array($item)) {
                $item = (object) $item;
            }
            
            $cantidad = isset($item->cantidad) ? intval($item->cantidad) : 0;
            $precio_unitario = isset($item->precio_unitario) ? floatval($item->precio_unitario) : 0;
            $subtotal = $cantidad * $precio_unitario;
            
            $productos[] = array(
                'codigo' => isset($item->codigo) ? $item->codigo : 'N/A', // CORREGIR: campo faltante
                'nombre' => isset($item->nombre_producto) ? $item->nombre_producto : (isset($item->nombre) ? $item->nombre : 'Producto sin nombre'),
                'descripcion' => isset($item->descripcion) ? $item->descripcion : '',
                'cantidad' => $cantidad,
                'unidad' => isset($item->unidad) ? $item->unidad : 'uds',
                'precio_unitario' => $precio_unitario,
                'precio_unitario_formateado' => '$' . number_format($precio_unitario, 0, ',', '.'),
                'subtotal' => $subtotal,
                'subtotal_formateado' => '$' . number_format($subtotal, 0, ',', '.'),
                'descuento' => isset($item->descuento) ? floatval($item->descuento) : 0,
                'descuento_formateado' => '$' . number_format(isset($item->descuento) ? $item->descuento : 0, 0, ',', '.'),
                'total' => $subtotal - (isset($item->descuento) ? $item->descuento : 0),
                'total_formateado' => '$' . number_format($subtotal - (isset($item->descuento) ? $item->descuento : 0), 0, ',', '.')
            );
        }
        
        return $productos;
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

    /**
     * Obtener datos reales para preview según el tipo de documento
     */
    public function obtener_datos_preview($tipo_documento = 'cotizacion') {
        global $wpdb;
        
        switch ($tipo_documento) {
            case 'cotizacion':
                return $this->obtener_ultima_cotizacion_para_preview();
                
            case 'venta':
                return $this->obtener_ultima_venta_para_preview();
                
            case 'pedido':
                return $this->obtener_ultimo_pedido_para_preview();
                
            default:
                return $this->obtener_datos_prueba($tipo_documento);
        }
    }

    /**
     * Obtener datos de la última cotización real
     */
    private function obtener_ultima_cotizacion_para_preview() {
        global $wpdb;
        
        $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        $tabla_items = $wpdb->prefix . 'mv_cotizaciones_items';
        $tabla_clientes = $wpdb->prefix . 'mv_clientes';
        
        // CORREGIDO: Usar get_row() sin prepare() cuando no hay parámetros dinámicos
        $cotizacion = $wpdb->get_row(
            "SELECT c.*, cl.razon_social, cl.rut, cl.email, cl.telefono,
                    cl.direccion_facturacion, cl.ciudad_facturacion, cl.region_facturacion,
                    cl.giro_comercial
            FROM {$tabla_cotizaciones} c
            LEFT JOIN {$tabla_clientes} cl ON c.cliente_id = cl.id
            WHERE c.estado != 'eliminada'
            ORDER BY c.fecha_creacion DESC
            LIMIT 1"
        );
        
        if (!$cotizacion) {
            // CORREGIDO: GENERAR datos de prueba sin recursión
            $this->logger->log("TEMPLATE_PROCESSOR: No hay cotizaciones reales, usando datos de prueba");
            
            // DIRECTAMENTE generar y asignar datos de prueba
            $datos_empresa = $this->obtener_datos_empresa_fallback();
            $datos_prueba = $this->obtener_datos_prueba_cotizacion($datos_empresa);
            
            // ASIGNAR directamente sin llamar a métodos que pueden causar bucles
            $this->cotizacion_data = $datos_prueba;
            
            return $datos_prueba;
        }
        
        // Verificar que la cotización tenga ID
        if (!isset($cotizacion->id) || empty($cotizacion->id)) {
            error_log('MODULO_VENTAS: Cotización sin ID encontrada');
            
            // CORREGIDO: Generar datos de prueba directamente
            $datos_empresa = $this->obtener_datos_empresa_fallback();
            $datos_prueba = $this->obtener_datos_prueba_cotizacion($datos_empresa);
            $this->cotizacion_data = $datos_prueba;
            
            return $datos_prueba;
        }
        
        // Obtener items de la cotización - USAR prepare() porque hay parámetro dinámico
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tabla_items} WHERE cotizacion_id = %d ORDER BY orden ASC",
            $cotizacion->id
        ));
        
        // Obtener datos de empresa
        if (function_exists('obtener_datos_empresa')) {
            $datos_empresa = obtener_datos_empresa();
        } else {
            $datos_empresa = $this->obtener_datos_empresa_fallback();
        }

        // AGREGAR: Obtener y procesar productos ANTES de crear $datos_formatted
        $items = $this->obtener_items_cotizacion($cotizacion->id);
        $productos = $this->formatear_productos_desde_items($items);
        
        // AGREGAR: Calcular totales ANTES de crear $datos_formatted
        $subtotal = isset($cotizacion->subtotal) ? floatval($cotizacion->subtotal) : 0;
        $descuento = isset($cotizacion->descuento_monto) ? floatval($cotizacion->descuento_monto) : 0;
        $impuestos = isset($cotizacion->impuesto_monto) ? floatval($cotizacion->impuesto_monto) : 0;
        $total = isset($cotizacion->total) ? floatval($cotizacion->total) : 0;
        
        // AGREGAR: Obtener datos de empresa ANTES de crear $datos_formatted
        $datos_empresa = $this->obtener_datos_empresa_fallback();
        
        // Formatear datos reales para el template
        $datos_formatted = array(
            'cotizacion' => array(
                'id' => $cotizacion->id,
                'folio' => $cotizacion->folio,
                'fecha' => date('d/m/Y', strtotime($cotizacion->fecha)),
                'fecha_expiracion' => isset($cotizacion->fecha_expiracion) ? date('d/m/Y', strtotime($cotizacion->fecha_expiracion)) : date('d/m/Y', strtotime('+30 days')), // AGREGAR: variable faltante
                'plazo_pago' => $cotizacion->plazo_pago,
                'moneda' => $cotizacion->moneda,
                'observaciones' => !empty($cotizacion->observaciones) ? $cotizacion->observaciones : 'Sin observaciones',
                'estado' => $cotizacion->estado
            ),
            
            'cliente' => array(
                'nombre' => $cotizacion->razon_social ?? 'Cliente sin nombre',
                'rut' => $this->formatear_rut($cotizacion->rut ?? ''),
                'email' => $cotizacion->email ?? '',
                'telefono' => $cotizacion->telefono ?? '',
                'direccion' => $cotizacion->direccion_facturacion ?? '',
                'ciudad' => $cotizacion->ciudad_facturacion ?? '',
                'region' => $cotizacion->region_facturacion ?? '',
                'giro' => $cotizacion->giro_comercial ?? ''
            ),

            'empresa' => array(
                'nombre' => $datos_empresa->nombre ?? 'Empresa',
                'rut' => $datos_empresa->rut ?? '',
                'direccion' => $datos_empresa->direccion ?? '',
                'ciudad' => $datos_empresa->ciudad ?? '',
                'region' => $datos_empresa->region ?? '',
                'telefono' => $datos_empresa->telefono ?? '',
                'email' => $datos_empresa->email ?? '',
                'sitio_web' => $datos_empresa->sitio_web ?? home_url(),
                'logo' => $this->generar_logo_empresa($datos_empresa), 
                'info' => get_option('modulo_ventas_info_empresa')
            ),
            
            'productos' => $productos, 
        
            'totales' => array(
                'subtotal' => $subtotal, 
                'subtotal_formateado' => '$' . number_format($subtotal, 0, ',', '.'),
                'descuento' => $descuento, 
                'descuento_formateado' => '$' . number_format($descuento, 0, ',', '.'),
                'descuento_porcentaje' => isset($cotizacion->descuento_porcentaje) ? $cotizacion->descuento_porcentaje : 0,
                'impuestos' => $impuestos, 
                'impuestos_formateado' => '$' . number_format($impuestos, 0, ',', '.'),
                'total' => $total,
                'total_formateado' => '$' . number_format($total, 0, ',', '.'),
                'moneda' => $cotizacion->moneda ?? 'CLP'
            ),
            
            'fechas' => array(
                'hoy' => date('d/m/Y'),
                'fecha_cotizacion' => date('d/m/Y', strtotime($cotizacion->fecha)),
                'fecha_vencimiento' => isset($cotizacion->fecha_expiracion) ? 
                    date('d/m/Y', strtotime($cotizacion->fecha_expiracion)) : 
                    date('d/m/Y', strtotime('+30 days')),
                'mes_actual' => date('F'),
                'año_actual' => date('Y')
            ),
            
            'sistema' => array(
                'usuario' => wp_get_current_user()->display_name,
                'fecha_generacion' => date('d/m/Y H:i:s'),
                'version' => '1.0'
            )
        );
        
        // ASIGNAR los datos formateados
        $this->cotizacion_data = $datos_formatted;
        
        $this->logger->log("TEMPLATE_PROCESSOR: Datos reales de cotización cargados (ID: {$cotizacion->id})");
        
        return $datos_formatted;
    }

    /**
     * Calcular totales desde cotización real
     */
    private function calcular_totales_desde_cotizacion($cotizacion) {
        // CORREGIR: Inicializar todas las variables necesarias
        $subtotal = isset($cotizacion->subtotal) ? floatval($cotizacion->subtotal) : 0;
        $descuento = isset($cotizacion->descuento_monto) ? floatval($cotizacion->descuento_monto) : 0;
        $impuestos = isset($cotizacion->impuesto_monto) ? floatval($cotizacion->impuesto_monto) : 0; // AGREGAR ESTA LÍNEA
        $costo_envio = isset($cotizacion->costo_envio) ? floatval($cotizacion->costo_envio) : 0;
        $total = isset($cotizacion->total) ? floatval($cotizacion->total) : 0;
        
        return array(
            'subtotal' => $subtotal,
            'subtotal_formateado' => '$' . number_format($subtotal, 0, ',', '.'),
            'descuento' => $descuento,
            'descuento_formateado' => '$' . number_format($descuento, 0, ',', '.'),
            'impuestos' => $impuestos,
            'impuestos_formateado' => '$' . number_format($impuestos, 0, ',', '.'),
            'costo_envio' => $costo_envio,
            'costo_envio_formateado' => '$' . number_format($costo_envio, 0, ',', '.'),
            'total' => $total,
            'total_formateado' => '$' . number_format($total, 0, ',', '.'),
            'porcentaje_impuesto' => isset($cotizacion->impuesto_porcentaje) ? floatval($cotizacion->impuesto_porcentaje) : 19,
            'incluye_iva' => isset($cotizacion->incluye_iva) ? (bool)$cotizacion->incluye_iva : true
        );
    }

    /**
     * AGREGAR: Método fallback para obtener datos de empresa
     */
    private function obtener_datos_empresa_fallback() {
        $datos_empresa = new stdClass();
        
        $datos_empresa->nombre = get_option('modulo_ventas_nombre_empresa', get_bloginfo('name'));
        $datos_empresa->direccion = get_option('modulo_ventas_direccion_empresa', '');
        $datos_empresa->ciudad = get_option('modulo_ventas_ciudad_empresa', '');
        $datos_empresa->region = get_option('modulo_ventas_region_empresa', '');
        $datos_empresa->telefono = get_option('modulo_ventas_telefono_empresa', '');
        $datos_empresa->email = get_option('modulo_ventas_email_empresa', get_bloginfo('admin_email'));
        $datos_empresa->rut = get_option('modulo_ventas_rut_empresa', '');
        $datos_empresa->sitio_web = home_url();
        
        // CORREGIR: Logo con HTML img tag
        $logo_id = get_option('modulo_ventas_logo_empresa', '');
        if ($logo_id) {
            $logo_url = wp_get_attachment_url($logo_id);
            if ($logo_url) {
                $datos_empresa->logo = '<img src="' . esc_url($logo_url) . '" alt="Logo empresa" style="max-height: 80px; width: auto;" />';
            } else {
                $datos_empresa->logo = '<div style="width: 100px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666;">Sin logo</div>';
            }
        } else {
            $datos_empresa->logo = '<div style="width: 100px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666;">Sin logo</div>';
        }
        
        // AGREGAR: Info adicional
        $datos_empresa->info = get_option('modulo_ventas_info_empresa', 'Información adicional de la empresa');
        
        return $datos_empresa;
    }

    /**
     * Formatear productos de cotización para template
     */
    private function formatear_productos_cotizacion($items) {
        $productos = array();
        
        if (!is_array($items)) {
            return $productos;
        }
        
        foreach ($items as $item) {
            $productos[] = array(
                'nombre' => isset($item->nombre_producto) ? $item->nombre_producto : 'Producto sin nombre',
                'descripcion' => isset($item->descripcion) ? $item->descripcion : '',
                'cantidad' => isset($item->cantidad) ? intval($item->cantidad) : 0,
                'precio_unitario' => isset($item->precio_unitario) ? floatval($item->precio_unitario) : 0,
                'precio_unitario_formateado' => '$' . number_format(isset($item->precio_unitario) ? $item->precio_unitario : 0, 0, ',', '.'),
                'subtotal' => isset($item->subtotal) ? floatval($item->subtotal) : 0,
                'subtotal_formateado' => '$' . number_format(isset($item->subtotal) ? $item->subtotal : 0, 0, ',', '.'),
                'descuento' => isset($item->descuento) ? floatval($item->descuento) : 0,
                'descuento_formateado' => '$' . number_format(isset($item->descuento) ? $item->descuento : 0, 0, ',', '.')
            );
        }
        
        return $productos;
    }

    /**
     * Obtener datos de la última venta (placeholder)
     */
    private function obtener_ultima_venta_para_preview() {
        // Por ahora usar datos de prueba, implementar cuando esté el módulo de ventas
        return $this->obtener_datos_prueba('venta');
    }

    /**
     * Obtener datos del último pedido (placeholder)
     */
    private function obtener_ultimo_pedido_para_preview() {
        // Por ahora usar datos de prueba, implementar cuando esté el módulo de pedidos
        return $this->obtener_datos_prueba('pedido');
    }

    /**
     * Obtener datos de prueba para preview
     */
    public function obtener_datos_prueba($tipo_documento = 'cotizacion') {
        // CORREGIDO: Obtener datos de empresa con fallback
        if (function_exists('obtener_datos_empresa')) {
            $datos_empresa = obtener_datos_empresa();
        } else {
            $datos_empresa = $this->obtener_datos_empresa_fallback();
        }
        
        switch ($tipo_documento) {
            case 'cotizacion':
                return $this->obtener_datos_prueba_cotizacion($datos_empresa);
            
            case 'venta':
                return $this->obtener_datos_prueba_venta($datos_empresa);
                
            case 'pedido':
                return $this->obtener_datos_prueba_pedido($datos_empresa);
                
            default:
                return $this->obtener_datos_prueba_cotizacion($datos_empresa);
        }
    }

    /**
     * Datos de prueba específicos para cotización
     */
    private function obtener_datos_prueba_cotizacion($datos_empresa) {
        return array(
            'cotizacion' => array(
                'numero' => 'COT-001',
                'fecha' => date('d/m/Y'),
                'fecha_vencimiento' => date('d/m/Y', strtotime('+30 days')),
                'estado' => 'Borrador',
                'observaciones' => 'Esta es una cotización de prueba para visualizar la plantilla',
                'vendedor' => 'Juan Pérez',
                'validez' => '30 días',
                'condiciones_pago' => 'Contado contra entrega'
            ),
            
            'cliente' => array(
                'nombre' => 'Empresa Cliente de Prueba S.A.',
                'rut' => '12.345.678-9',
                'email' => 'contacto@clienteprueba.cl',
                'telefono' => '+56 9 8765 4321',
                'direccion' => 'Av. Providencia 1234, Oficina 567',
                'ciudad' => 'Santiago',
                'region' => 'Metropolitana',
                'giro' => 'Servicios de Consultoría'
            ),
            
            'empresa' => array(
                'nombre' => isset($datos_empresa->nombre) ? $datos_empresa->nombre : 'Empresa de Prueba',
                'direccion' => isset($datos_empresa->direccion) ? $datos_empresa->direccion : 'Dirección de Prueba',
                'ciudad' => isset($datos_empresa->ciudad) ? $datos_empresa->ciudad : 'Ciudad',
                'region' => isset($datos_empresa->region) ? $datos_empresa->region : 'Región',
                'telefono' => isset($datos_empresa->telefono) ? $datos_empresa->telefono : '+56 2 1234 5678',
                'email' => isset($datos_empresa->email) ? $datos_empresa->email : 'contacto@empresa.cl',
                'rut' => isset($datos_empresa->rut) ? $datos_empresa->rut : '98.765.432-1',
                'sitio_web' => isset($datos_empresa->sitio_web) ? $datos_empresa->sitio_web : home_url()
            ),
            
            'productos' => array(
                array(
                    'nombre' => 'Consultoría en Gestión Empresarial',
                    'descripcion' => 'Análisis y optimización de procesos empresariales',
                    'cantidad' => 20,
                    'precio_unitario' => 50000,
                    'precio_unitario_formateado' => '$50.000',
                    'subtotal' => 1000000,
                    'subtotal_formateado' => '$1.000.000',
                    'descuento' => 0,
                    'descuento_formateado' => '$0'
                ),
                array(
                    'nombre' => 'Capacitación de Personal',
                    'descripcion' => 'Programa de capacitación para equipos de trabajo',
                    'cantidad' => 15,
                    'precio_unitario' => 35000,
                    'precio_unitario_formateado' => '$35.000',
                    'subtotal' => 525000,
                    'subtotal_formateado' => '$525.000',
                    'descuento' => 25000,
                    'descuento_formateado' => '$25.000'
                )
            ),
            
            'totales' => array(
                'subtotal' => 1525000,
                'subtotal_formateado' => '1.525.000',
                'descuento' => 25000,
                'descuento_formateado' => '25.000',
                'impuestos' => 285000,
                'impuestos_formateado' => '285.000',
                'total' => 1785000,
                'total_formateado' => '1.785.000',
                'descuento_porcentaje' => 1.6
            ),
            
            'fechas' => array(
                'hoy' => date('d/m/Y'),
                'fecha_cotizacion' => date('d/m/Y'),
                'fecha_vencimiento_formateada' => date('d/m/Y', strtotime('+30 days'))
            ),
            
            'logo_empresa' => isset($datos_empresa->logo) ? $datos_empresa->logo : '',
            'info_empresa' => isset($datos_empresa->info_adicional) ? $datos_empresa->info_adicional : 'Información adicional de la empresa',
            'terminos_condiciones' => get_option('modulo_ventas_terminos_condiciones', 'Términos y condiciones: Los precios son válidos por 30 días.')
        );
    }

    /**
     * Datos de prueba para venta (placeholder)
     */
    private function obtener_datos_prueba_venta($datos_empresa) {
        $datos = $this->obtener_datos_prueba_cotizacion($datos_empresa);
        
        // Modificar algunos campos específicos para venta
        $datos['venta'] = $datos['cotizacion'];
        $datos['venta']['numero'] = 'VEN-001';
        $datos['venta']['estado'] = 'Completada';
        unset($datos['cotizacion']);
        
        return $datos;
    }

    /**
     * Datos de prueba para pedido (placeholder)
     */
    private function obtener_datos_prueba_pedido($datos_empresa) {
        $datos = $this->obtener_datos_prueba_cotizacion($datos_empresa);
        
        // Modificar algunos campos específicos para pedido
        $datos['pedido'] = $datos['cotizacion'];
        $datos['pedido']['numero'] = 'PED-001';
        $datos['pedido']['estado'] = 'En Proceso';
        unset($datos['cotizacion']);
        
        return $datos;
    }

    /**
     * Generar HTML optimizado para mPDF (si no existe)
     */
    public function generar_html_para_mpdf($html_procesado, $css_procesado) {
        return $this->generar_documento_final($html_procesado, $css_procesado);
    }

    private function formatear_rut_fallback($rut) {
        if (function_exists('mv_formatear_rut')) {
            return mv_formatear_rut($rut);
        }
        
        // Fallback: formateo básico
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) {
            return $rut;
        }
        
        $dv = substr($rut, -1);
        $numero = substr($rut, 0, -1);
        
        // Formatear con puntos
        $numero = strrev($numero);
        $numero = chunk_split($numero, 3, '.');
        $numero = strrev($numero);
        $numero = ltrim($numero, '.');
        
        return $numero . '-' . $dv;
    }

    /**
     * NUEVO: Obtener items/productos de una cotización
     */
    private function obtener_items_cotizacion($cotizacion_id) {
        global $wpdb;
        
        $tabla_items = $wpdb->prefix . 'mv_cotizaciones_items';
        
        // CORREGIR: Obtener items con JOIN a productos para obtener el código
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ci.*,
                p.post_title as nombre_producto,
                pm1.meta_value as codigo_producto,
                pm2.meta_value as descripcion_producto
            FROM $tabla_items ci
            LEFT JOIN {$wpdb->posts} p ON ci.producto_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_product_short_description'
            WHERE ci.cotizacion_id = %d 
            ORDER BY ci.orden ASC",
            $cotizacion_id
        ));
        
        if (!$items) {
            $this->logger->log("PROCESSOR: No se encontraron items para cotización {$cotizacion_id}");
            return array();
        }
        
        // Agregar campos faltantes a cada item
        foreach ($items as $item) {
            // Agregar código del producto
            if (empty($item->codigo_producto)) {
                $item->codigo = 'PROD-' . str_pad($item->producto_id, 3, '0', STR_PAD_LEFT);
            } else {
                $item->codigo = $item->codigo_producto;
            }
            
            // Asegurar que tenga nombre
            if (empty($item->nombre_producto)) {
                $item->nombre_producto = 'Producto ID ' . $item->producto_id;
            }
            
            // Agregar descripción si existe
            if (!empty($item->descripcion_producto)) {
                $item->descripcion = $item->descripcion_producto;
            } elseif (empty($item->descripcion)) {
                $item->descripcion = '';
            }
        }
        
        $this->logger->log("PROCESSOR: Encontrados " . count($items) . " items para cotización {$cotizacion_id}");
        return $items;
    }

    /**
     * Procesar CSS específicamente para visualización web
     */
    public function procesar_css_para_web($css_content) {
        $this->logger->log("PROCESSOR: Procesando CSS para visualización web");
        
        // Limpiar CSS básico
        $css_limpio = $this->limpiar_css_base($css_content);
        
        // Aplicar optimizaciones web
        $css_web = $this->aplicar_optimizaciones_web($css_limpio);
        
        // Agregar estilos responsivos
        $css_responsivo = $this->agregar_estilos_responsivos($css_web);
        
        return $css_responsivo;
    }

    /**
     * Procesar CSS para impresión desde navegador
     */
    public function procesar_css_para_impresion($css_content) {
        $this->logger->log("PROCESSOR: Procesando CSS para impresión");
        
        // Procesar primero para web
        $css_web = $this->procesar_css_para_web($css_content);
        
        // Agregar optimizaciones de impresión
        $css_impresion = $this->agregar_estilos_impresion($css_web);
        
        return $css_impresion;
    }

    /**
     * Limpiar CSS básico (versión mejorada)
     */
    private function limpiar_css_base($css) {
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
    private function aplicar_optimizaciones_web($css) {
        $optimizaciones = array(
            // Mejorar renderizado de fuentes
            'body' => 'font-display: swap; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;',
            
            // Optimizar imágenes
            'img' => 'max-width: 100%; height: auto; display: block;',
            
            // Mejorar tablas
            'table' => 'border-collapse: collapse; width: 100%;',
            '.productos-tabla' => 'border-collapse: collapse; width: 100%;',
            
            // Flexbox con prefijos para compatibilidad
            '.d-flex, .flex' => 'display: -webkit-box; display: -ms-flexbox; display: flex;',
            '.flex-column' => '-webkit-box-orient: vertical; -webkit-box-direction: normal; -ms-flex-direction: column; flex-direction: column;',
            '.justify-content-between' => '-webkit-box-pack: justify; -ms-flex-pack: justify; justify-content: space-between;',
            '.align-items-center' => '-webkit-box-align: center; -ms-flex-align: center; align-items: center;'
        );
        
        // Aplicar optimizaciones
        foreach ($optimizaciones as $selector => $propiedades) {
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
    private function agregar_estilos_responsivos($css) {
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
        }";
        
        return $css . $responsive_css;
    }

    /**
     * Agregar estilos específicos para impresión
     */
    private function agregar_estilos_impresion($css) {
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
                padding: 0 !important;
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
        }";
        
        return $css . $print_css;
    }

    /**
     * Optimizar HTML para visualización web
     */
    public function optimizar_html_para_web($html) {
        // Agregar clases responsivas
        $html = $this->agregar_clases_responsivas($html);
        
        // Optimizar imágenes
        $html = $this->optimizar_imagenes_html($html);
        
        // Agregar landmarks ARIA para accesibilidad
        $html = $this->agregar_landmarks_aria($html);
        
        return $html;
    }

    /**
     * Agregar clases responsivas al HTML
     */
    private function agregar_clases_responsivas($html) {
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
    private function optimizar_imagenes_html($html) {
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
    private function agregar_landmarks_aria($html) {
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
     * Cargar datos de documento según tipo
     */
    private function cargar_datos_documento($tipo, $id) {
        switch ($tipo) {
            case 'cotizacion':
                $this->cargar_datos_cotizacion($id);
                break;
            case 'factura':
                $this->cargar_datos_factura($id);
                break;
            case 'orden_compra':
                $this->cargar_datos_orden_compra($id);
                break;
            default:
                throw new Exception("Tipo de documento no soportado: {$tipo}");
        }
    }

    /**
     * Cargar datos de factura (placeholder para futuro)
     */
    private function cargar_datos_factura($factura_id) {
        // TODO: Implementar cuando tengamos sistema de facturas
        throw new Exception('Sistema de facturas no implementado aún');
    }

    /**
     * Cargar datos de orden de compra (placeholder para futuro)
     */
    private function cargar_datos_orden_compra($orden_id) {
        // TODO: Implementar cuando tengamos sistema de órdenes
        throw new Exception('Sistema de órdenes de compra no implementado aún');
    }

    /**
     * Generar URL para visualización web del documento
     */
    public function generar_url_documento($tipo, $id, $action = 'view') {
        $base_url = home_url("documentos/{$tipo}/{$id}");
        
        if ($action !== 'view') {
            $base_url .= "/{$action}";
        }
        
        return $base_url;
    }

    /**
     * Generar token de acceso para documento
     */
    public function generar_token_acceso($tipo, $id) {
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt';
        return hash('sha256', $tipo . $id . $salt . date('Y-m-d'));
    }

    /**
     * Generar URL pública con token de acceso
     */
    public function generar_url_publica($tipo, $id, $con_token = true) {
        $url = $this->generar_url_documento($tipo, $id);
        
        if ($con_token) {
            $token = $this->generar_token_acceso($tipo, $id);
            $url .= "?token={$token}";
        }
        
        return $url;
    }

    /**
     * Verificar token de acceso público
     */
    public function verificar_token($tipo, $id, $token) {
        $expected_token = $this->generar_token_acceso($tipo, $id);
        return hash_equals($expected_token, $token);
    }

    /**
     * Generar documento HTML completo para web
     */
    public function generar_documento_web($html_procesado, $css_procesado, $titulo = '', $modo = 'view') {
        $documento = '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . esc_html($titulo ?: 'Documento') . '</title>
        
        <!-- CSS base del sistema -->
        <style type="text/css">
            /* Reset y estilos base */
            * { box-sizing: border-box; }
            
            body {
                margin: 0;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f5f5f5;
                color: #333;
            }
            
            .mv-document-container {
                max-width: 210mm;
                margin: 20px auto;
                background: white;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                border-radius: 8px;
                overflow: hidden;
                min-height: 297mm;
            }
            
            .mv-document-content {
                padding: 20px;
            }
            
            @media print {
                body { background: white; }
                .mv-document-container {
                    margin: 0;
                    box-shadow: none;
                    border-radius: 0;
                    max-width: none;
                    width: 100%;
                }
                .mv-document-content { padding: 0; }
            }
        </style>
        
        <!-- CSS específico de la plantilla -->
        <style type="text/css">' . $css_procesado . '</style>
    </head>
    <body class="mv-mode-' . esc_attr($modo) . '">
        <div class="mv-document-container">
            <div class="mv-document-content">' . 
                $html_procesado . 
            '</div>
        </div>
    </body>
    </html>';
        
        return $documento;
    }
}