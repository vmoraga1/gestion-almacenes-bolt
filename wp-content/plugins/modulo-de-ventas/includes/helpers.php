<?php
/**
 * Funciones auxiliares del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtener instancia del plugin con manejo seguro
 */
if (!function_exists('mv_get_instance')) {
    function mv_get_instance() {
        static $instance = null;
        
        if (null === $instance) {
            $instance = new stdClass();
            
            // Crear un objeto wrapper para mensajes
            $instance->messages = new Modulo_Ventas_Messages_Wrapper();
        }
        
        return $instance;
    }
}

/**
 * Clase wrapper para mensajes
 */
if (!class_exists('Modulo_Ventas_Messages_Wrapper')) {
    class Modulo_Ventas_Messages_Wrapper {
        private $real_instance = null;
        
        public function get_messages($context = null) {
            if ($this->real_instance === null && class_exists('Modulo_Ventas_Messages')) {
                $this->real_instance = Modulo_Ventas_Messages::get_instance();
            }
            
            if ($this->real_instance !== null) {
                return $this->real_instance->get_messages($context);
            }
            
            // Si no hay instancia, retornar array vacío
            return array();
        }
        
        public function __call($method, $args) {
            if ($this->real_instance === null && class_exists('Modulo_Ventas_Messages')) {
                $this->real_instance = Modulo_Ventas_Messages::get_instance();
            }
            
            if ($this->real_instance !== null && method_exists($this->real_instance, $method)) {
                return call_user_func_array(array($this->real_instance, $method), $args);
            }
            
            return null;
        }
    }
}
/*
 * Obtener instancia del plugin con manejo seguro
 *
if (!function_exists('mv_get_instance')) {
    function mv_get_instance() {
        static $instance = null;
        
        if (null === $instance) {
            $instance = new stdClass();
            
            // Crear un objeto wrapper para mensajes que siempre tenga el método get_messages
            $instance->messages = new class {
                private $real_instance = null;
                
                public function get_messages($context = null) {
                    if ($this->real_instance === null && class_exists('Modulo_Ventas_Messages')) {
                        $this->real_instance = Modulo_Ventas_Messages::get_instance();
                    }
                    
                    if ($this->real_instance !== null) {
                        return $this->real_instance->get_messages($context);
                    }
                    
                    // Si no hay instancia, retornar array vacío
                    return array();
                }
                
                public function __call($method, $args) {
                    if ($this->real_instance === null && class_exists('Modulo_Ventas_Messages')) {
                        $this->real_instance = Modulo_Ventas_Messages::get_instance();
                    }
                    
                    if ($this->real_instance !== null && method_exists($this->real_instance, $method)) {
                        return call_user_func_array(array($this->real_instance, $method), $args);
                    }
                    
                    return null;
                }
            };
        }
        
        return $instance;
    }
}*/

/* //Helper para obtener instancia de mensajes

if (!function_exists('mv_messages')) {
    function mv_messages() {
        if (class_exists('Modulo_Ventas_Messages')) {
            return Modulo_Ventas_Messages::get_instance();
        }
        return null;
    }
}*/

/**
 * Acceso directo a la clase de mensajes
 */
if (!function_exists('mv_messages')) {
    function mv_messages() {
        if (class_exists('Modulo_Ventas_Messages')) {
            return Modulo_Ventas_Messages::get_instance();
        }
        // Retornar un objeto mock si la clase no existe aún
        return new Modulo_Ventas_Mock_Messages();
    }
}

/**
 * Clase mock para mensajes cuando la clase real no está disponible
 */
if (!class_exists('Modulo_Ventas_Mock_Messages')) {
    class Modulo_Ventas_Mock_Messages {
        public function get_messages($context = null) { 
            return array(); 
        }
        
        public function add_message($message, $type = 'info', $context = 'general') { 
            return null; 
        }
        
        public function display_messages($context = null, $echo = true) { 
            return ''; 
        }
        
        public function __call($method, $args) { 
            return null; 
        }
    }
}

/**
 * Acceso directo a la base de datos
 */
if (!function_exists('mv_db')) {
    function mv_db() {
        if (class_exists('Modulo_Ventas_DB')) {
            return new Modulo_Ventas_DB();
        }
        return null;
    }
}

/**
 * Formatear precio según configuración de WooCommerce
 */
if (!function_exists('mv_formato_precio')) {
    function mv_formato_precio($precio, $incluir_simbolo = true) {
        if ($incluir_simbolo) {
            return wc_price($precio);
        }
        
        return number_format(
            $precio,
            wc_get_price_decimals(),
            wc_get_price_decimal_separator(),
            wc_get_price_thousand_separator()
        );
    }
}

/**
 * Validar RUT chileno
 */
if (!function_exists('mv_validar_rut')) {
    function mv_validar_rut($rut) {
        $rut = mv_limpiar_rut($rut);
        
        if (strlen($rut) < 2) {
            return false;
        }
        
        // Separar número y dígito verificador
        $numero = substr($rut, 0, -1);
        $dv = substr($rut, -1);
        
        // Validar que el número sea numérico
        if (!is_numeric($numero)) {
            return false;
        }
        
        // Calcular dígito verificador
        $suma = 0;
        $factor = 2;
        
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += $factor * $numero[$i];
            $factor = $factor == 7 ? 2 : $factor + 1;
        }
        
        $resto = $suma % 11;
        $dv_calculado = 11 - $resto;
        
        if ($dv_calculado == 11) {
            $dv_calculado = '0';
        } elseif ($dv_calculado == 10) {
            $dv_calculado = 'K';
        } else {
            $dv_calculado = (string)$dv_calculado;
        }
        
        return strtoupper($dv) === $dv_calculado;
    }
}

/**
 * Calcular dígito verificador de RUT
 * 
 * @param string $numero Número del RUT sin DV
 * @return string Dígito verificador calculado
 */
function mv_calcular_dv_rut($numero) {
    $suma = 0;
    $multiplicador = 2;
    
    // Recorrer número de derecha a izquierda
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += intval($numero[$i]) * $multiplicador;
        $multiplicador++;
        if ($multiplicador > 7) {
            $multiplicador = 2;
        }
    }
    
    $resto = $suma % 11;
    $dv = 11 - $resto;
    
    if ($dv == 11) {
        return '0';
    } elseif ($dv == 10) {
        return 'K';
    } else {
        return strval($dv);
    }
}

/**
 * Limpiar RUT (solo números y K)
 */
if (!function_exists('mv_limpiar_rut')) {
    function mv_limpiar_rut($rut) {
        // Convertir a mayúsculas y remover caracteres no deseados
        $rut = strtoupper($rut);
        $rut = str_replace(array('.', '-', ' '), '', $rut);
        return trim($rut);
    }
}

/**
 * Formatear RUT chileno
 */
if (!function_exists('mv_formatear_rut')) {
    function mv_formatear_rut($rut) {
        $rut_limpio = mv_limpiar_rut($rut);
        
        if (empty($rut_limpio)) {
            return '';
        }
        
        // Separar número y DV
        $numero = substr($rut_limpio, 0, -1);
        $dv = substr($rut_limpio, -1);
        
        // Formatear número con puntos
        $numero_formateado = '';
        $contador = 0;
        
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            if ($contador == 3) {
                $numero_formateado = '.' . $numero_formateado;
                $contador = 0;
            }
            $numero_formateado = $numero[$i] . $numero_formateado;
            $contador++;
        }
        
        return $numero_formateado . '-' . $dv;
    }
}

/**
 * Validar formato de email
 * 
 * @param string $email Email a validar
 * @return bool True si es válido
 */
function mv_validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Formatear número de teléfono chileno
 * 
 * @param string $telefono Teléfono sin formato
 * @return string Teléfono formateado
 */
function mv_formatear_telefono($telefono) {
    // Remover caracteres no numéricos
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    
    // Si es celular (9 dígitos comenzando con 9)
    if (strlen($telefono) == 9 && substr($telefono, 0, 1) == '9') {
        return '+56 9 ' . substr($telefono, 1, 4) . ' ' . substr($telefono, 5, 4);
    }
    
    // Si es teléfono fijo (9 dígitos con código de área)
    if (strlen($telefono) == 9) {
        return '+56 ' . substr($telefono, 0, 2) . ' ' . substr($telefono, 2, 4) . ' ' . substr($telefono, 6, 3);
    }
    
    // Si ya incluye código de país
    if (strlen($telefono) == 11 && substr($telefono, 0, 2) == '56') {
        $telefono = substr($telefono, 2);
        return mv_formatear_telefono($telefono);
    }
    
    return $telefono;
}

/**
 * Formatear precio en pesos chilenos
 * 
 * @param float $precio Precio a formatear
 * @param bool $incluir_simbolo Incluir símbolo de peso
 * @return string Precio formateado
 */
function mv_formato_precio($precio, $incluir_simbolo = true) {
    $formato = number_format($precio, 0, ',', '.');
    return $incluir_simbolo ? '$' . $formato : $formato;
}

/**
 * Obtener estados de cotización con colores
 */
if (!function_exists('mv_get_estados_cotizacion')) {
    function mv_get_estados_cotizacion() {
        return array(
            'borrador' => array(
                'label' => __('Borrador', 'modulo-ventas'),
                'color' => '#999999',
                'icon' => 'dashicons-edit'
            ),
            'pendiente' => array(
                'label' => __('Pendiente', 'modulo-ventas'),
                'color' => '#f39c12',
                'icon' => 'dashicons-clock'
            ),
            'aprobada' => array(
                'label' => __('Aprobada', 'modulo-ventas'),
                'color' => '#27ae60',
                'icon' => 'dashicons-yes-alt'
            ),
            'rechazada' => array(
                'label' => __('Rechazada', 'modulo-ventas'),
                'color' => '#e74c3c',
                'icon' => 'dashicons-dismiss'
            ),
            'expirada' => array(
                'label' => __('Expirada', 'modulo-ventas'),
                'color' => '#95a5a6',
                'icon' => 'dashicons-calendar-alt'
            ),
            'convertida' => array(
                'label' => __('Convertida', 'modulo-ventas'),
                'color' => '#3498db',
                'icon' => 'dashicons-cart'
            )
        );
    }
}

/**
 * Obtener estados de cotización
 */
function ventas_get_estados_cotizacion() {
    return array(
        'borrador' => __('Borrador', 'modulo-ventas'),
        'pendiente' => __('Pendiente', 'modulo-ventas'),
        'enviada' => __('Enviada', 'modulo-ventas'),
        'aceptada' => __('Aceptada', 'modulo-ventas'),
        'rechazada' => __('Rechazada', 'modulo-ventas'),
        'expirada' => __('Expirada', 'modulo-ventas'),
        'convertida' => __('Convertida', 'modulo-ventas')
    );
}

/**
 * Obtener badge HTML para estado
 */
if (!function_exists('mv_get_estado_badge')) {
    function mv_get_estado_badge($estado) {
        $estados = mv_get_estados_cotizacion();
        
        if (!isset($estados[$estado])) {
            return '<span class="mv-badge">' . esc_html($estado) . '</span>';
        }
        
        $config = $estados[$estado];
        
        return sprintf(
            '<span class="mv-badge mv-badge-%s" style="background-color: %s;"><span class="dashicons %s"></span> %s</span>',
            esc_attr($estado),
            esc_attr($config['color']),
            esc_attr($config['icon']),
            esc_html($config['label'])
        );
    }
}

/**
 * Calcular fecha de expiración
 */
if (!function_exists('mv_calcular_fecha_expiracion')) {
    function mv_calcular_fecha_expiracion($fecha_base = null, $dias = null) {
        if (!$fecha_base) {
            $fecha_base = current_time('Y-m-d');
        }
        
        if (!$dias) {
            $dias = get_option('modulo_ventas_dias_expiracion', 30);
        }
        
        return date('Y-m-d', strtotime($fecha_base . ' + ' . $dias . ' days'));
    }
}

/**
 * Verificar si una cotización está expirada
 */
if (!function_exists('mv_cotizacion_expirada')) {
    function mv_cotizacion_expirada($fecha_expiracion) {
        if (!$fecha_expiracion) {
            return false;
        }
        
        return strtotime($fecha_expiracion) < strtotime(current_time('Y-m-d'));
    }
}

/**
 * Obtener lista de regiones de Chile
 */
if (!function_exists('mv_get_regiones_chile')) {
    function mv_get_regiones_chile() {
        return array(
            'I' => 'Región de Tarapacá',
            'II' => 'Región de Antofagasta', 
            'III' => 'Región de Atacama',
            'IV' => 'Región de Coquimbo',
            'V' => 'Región de Valparaíso',
            'VI' => 'Región del Libertador General Bernardo O\'Higgins',
            'VII' => 'Región del Maule',
            'VIII' => 'Región del Biobío',
            'IX' => 'Región de La Araucanía',
            'X' => 'Región de Los Lagos',
            'XI' => 'Región de Aysén del General Carlos Ibáñez del Campo',
            'XII' => 'Región de Magallanes y de la Antártica Chilena',
            'RM' => 'Región Metropolitana de Santiago',
            'XIV' => 'Región de Los Ríos',
            'XV' => 'Región de Arica y Parinacota',
            'XVI' => 'Región de Ñuble'
        );
    }
}

/**
 * Obtener plazos de pago predefinidos
 */
if (!function_exists('mv_get_plazos_pago')) {
    function mv_get_plazos_pago() {
        return array(
            'contado' => __('Contado', 'modulo-ventas'),
            '15_dias' => __('15 días', 'modulo-ventas'),
            '30_dias' => __('30 días', 'modulo-ventas'),
            '45_dias' => __('45 días', 'modulo-ventas'),
            '60_dias' => __('60 días', 'modulo-ventas'),
            '90_dias' => __('90 días', 'modulo-ventas'),
            'personalizado' => __('Personalizado', 'modulo-ventas')
        );
    }
}

/**
 * Obtener label de plazo de pago
 */
function mv_get_plazo_pago_label($key) {
    $plazos = mv_get_plazos_pago();
    return isset($plazos[$key]) ? $plazos[$key] : $key;
}

/**
 * Formatear fecha según configuración de WordPress
 */
if (!function_exists('mv_formato_fecha')) {
    function mv_formato_fecha($fecha, $incluir_hora = false) {
        if (!$fecha || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
            return '—';
        }
        
        $timestamp = strtotime($fecha);
        
        if ($incluir_hora) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        }
        
        return date_i18n(get_option('date_format'), $timestamp);
    }
}

/**
 * Formatear fecha relativa (hace X tiempo)
 */
if (!function_exists('mv_fecha_relativa')) {
    function mv_fecha_relativa($fecha) {
        $timestamp = strtotime($fecha);
        $diferencia = current_time('timestamp') - $timestamp;
        
        if ($diferencia < 60) {
            return __('Hace un momento', 'modulo-ventas');
        } elseif ($diferencia < 3600) {
            $minutos = round($diferencia / 60);
            return sprintf(_n('Hace %d minuto', 'Hace %d minutos', $minutos, 'modulo-ventas'), $minutos);
        } elseif ($diferencia < 86400) {
            $horas = round($diferencia / 3600);
            return sprintf(_n('Hace %d hora', 'Hace %d horas', $horas, 'modulo-ventas'), $horas);
        } elseif ($diferencia < 604800) {
            $dias = round($diferencia / 86400);
            return sprintf(_n('Hace %d día', 'Hace %d días', $dias, 'modulo-ventas'), $dias);
        } else {
            return mv_formato_fecha($fecha);
        }
    }
}

/**
 * Calcular subtotal con descuento
 */
if (!function_exists('mv_calcular_subtotal_con_descuento')) {
    function mv_calcular_subtotal_con_descuento($subtotal, $descuento, $tipo_descuento = 'monto') {
        if ($tipo_descuento === 'porcentaje') {
            return $subtotal - ($subtotal * ($descuento / 100));
        }
        
        return $subtotal - $descuento;
    }
}

/**
 * Calcular IVA
 */
if (!function_exists('mv_calcular_iva')) {
    function mv_calcular_iva($monto, $tasa = 19) {
        return $monto * ($tasa / 100);
    }
}

/**
 * Calcular total con IVA
 */
if (!function_exists('mv_calcular_total_con_iva')) {
    function mv_calcular_total_con_iva($subtotal, $incluye_iva = true, $tasa_iva = 19) {
        if (!$incluye_iva) {
            return $subtotal;
        }
        
        return $subtotal + mv_calcular_iva($subtotal, $tasa_iva);
    }
}

/**
 * Obtener porcentaje
 */
if (!function_exists('mv_calcular_porcentaje')) {
    function mv_calcular_porcentaje($valor, $total, $decimales = 1) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($valor / $total) * 100, $decimales);
    }
}

/**
 * Sanitizar campos de formulario
 */
if (!function_exists('mv_sanitizar_campo')) {
    function mv_sanitizar_campo($valor, $tipo = 'text') {
        switch ($tipo) {
            case 'email':
                return sanitize_email($valor);
                
            case 'url':
                return esc_url_raw($valor);
                
            case 'textarea':
                return sanitize_textarea_field($valor);
                
            case 'int':
                return intval($valor);
                
            case 'float':
                return floatval($valor);
                
            case 'rut':
                return mv_limpiar_rut($valor);
                
            case 'key':
                return sanitize_key($valor);
                
            case 'title':
                return sanitize_title($valor);
                
            default:
                return sanitize_text_field($valor);
        }
    }
}

/**
 * Sanitizar array recursivamente
 */
function mv_sanitize_array($array) {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = mv_sanitize_array($value);
        } else {
            $array[$key] = sanitize_text_field($value);
        }
    }
    return $array;
}

/**
 * Verificar capacidad del usuario
 */
if (!function_exists('mv_current_user_can')) {
    function mv_current_user_can($capability, $object_id = null) {
        if ($object_id) {
            return current_user_can($capability, $object_id);
        }
        
        return current_user_can($capability);
    }
}

/**
 * Verificar si el usuario actual puede ver una cotización
 */
function mv_current_user_can_view_quote($cotizacion_id) {
    // Administradores siempre pueden ver
    if (current_user_can('manage_options')) {
        return true;
    }
    
    // Usuarios con permiso específico
    if (current_user_can('view_cotizaciones')) {
        return true;
    }
    
    // Verificar si es el vendedor asignado
    $cotizacion = mv_db()->obtener_cotizacion($cotizacion_id);
    if ($cotizacion && $cotizacion->vendedor_id == get_current_user_id()) {
        return true;
    }
    
    // Verificar si es el cliente
    if (is_user_logged_in()) {
        $clientes = new Modulo_Ventas_Clientes();
        $cliente = $clientes->obtener_cliente_por_usuario(get_current_user_id());
        
        if ($cliente && $cotizacion && $cotizacion->cliente_id == $cliente->id) {
            return true;
        }
    }
    
    return false;
}

/**
 * Obtener URL de administración del plugin
 */
if (!function_exists('mv_admin_url')) {
    function mv_admin_url($page, $args = array()) {
        $pages_map = array(
            'cotizaciones' => 'modulo-ventas-cotizaciones',
            'nueva-cotizacion' => 'modulo-ventas-nueva-cotizacion',
            'ver-cotizacion' => 'modulo-ventas-ver-cotizacion',
            'editar-cotizacion' => 'modulo-ventas-editar-cotizacion',
            'clientes' => 'modulo-ventas-clientes',
            'nuevo-cliente' => 'modulo-ventas-nuevo-cliente',
            'configuracion' => 'modulo-ventas-configuracion',
            'reportes' => 'modulo-ventas-reportes'
        );
        
        $page_slug = isset($pages_map[$page]) ? $pages_map[$page] : $page;
        
        $url = admin_url('admin.php?page=' . $page_slug);
        
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        return $url;
    }
}

/**
 * Generar nonce para acciones
 */
if (!function_exists('mv_create_nonce')) {
    function mv_create_nonce($action) {
        return wp_create_nonce('mv_' . $action);
    }
}

/**
 * Verificar nonce
 */
if (!function_exists('mv_verify_nonce')) {
    function mv_verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, 'mv_' . $action);
    }
}

/**
 * Obtener plantilla
 */
if (!function_exists('mv_get_template')) {
    function mv_get_template($template_name, $args = array()) {
        $template_path = MODULO_VENTAS_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return false;
        }
        
        if (!empty($args)) {
            extract($args);
        }
        
        include $template_path;
    }
}

/**
 * Obtener almacenes disponibles
 */
if (!function_exists('mv_get_almacenes')) {
    function mv_get_almacenes($solo_activos = true) {
        if (!class_exists('Gestion_Almacenes_DB')) {
            return array();
        }
        
        global $gestion_almacenes_db;
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        
        if ($solo_activos) {
            $almacenes = array_filter($almacenes, function($almacen) {
                return $almacen->is_active == 1;
            });
        }
        
        return $almacenes;
    }
}

/**
 * Obtener stock de producto en almacén
 */
if (!function_exists('mv_get_stock_almacen')) {
    function mv_get_stock_almacen($producto_id, $almacen_id = null) {
        if (!class_exists('Gestion_Almacenes_DB')) {
            // Si no está el plugin de almacenes, usar stock de WooCommerce
            $producto = wc_get_product($producto_id);
            return $producto ? $producto->get_stock_quantity() : 0;
        }
        
        global $gestion_almacenes_db;
        $stock_por_almacen = $gestion_almacenes_db->get_product_warehouse_stock($producto_id);
        
        if ($almacen_id) {
            return isset($stock_por_almacen[$almacen_id]) ? $stock_por_almacen[$almacen_id] : 0;
        }
        
        // Retornar stock total
        return array_sum($stock_por_almacen);
    }
}

/**
 * Verificar si el plugin de almacenes está activo
 */
if (!function_exists('mv_almacenes_activo')) {
    function mv_almacenes_activo() {
        return class_exists('Gestion_Almacenes_DB');
    }
}

/**
 * Obtener vendedores del sistema
 */
if (!function_exists('mv_get_vendedores')) {
    function mv_get_vendedores() {
        $args = array(
            'role__in' => array('administrator', 'shop_manager', 'vendedor'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        // Filtro para permitir modificar los roles
        $args = apply_filters('mv_vendedores_roles', $args);
        
        return get_users($args);
    }
}

/**
 * Obtener configuración del plugin
 */
function mv_get_config($key = null, $default = null) {
    $config = get_option('modulo_ventas_config', array());
    
    if (null === $key) {
        return $config;
    }
    
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Guardar configuración del plugin
 */
function mv_set_config($key, $value) {
    $config = get_option('modulo_ventas_config', array());
    $config[$key] = $value;
    update_option('modulo_ventas_config', $config);
}


/**
 * Log de actividad
 */
if (!function_exists('mv_log')) {
    function mv_log($mensaje, $nivel = 'info', $contexto = array()) {
        $logger = Modulo_Ventas_Logger::get_instance();
        $logger->log($mensaje, $nivel, $contexto);
    }
}

/**
 * Crear select HTML
 */
if (!function_exists('mv_html_select')) {
    function mv_html_select($args) {
        $defaults = array(
            'name' => '',
            'id' => '',
            'class' => '',
            'options' => array(),
            'selected' => '',
            'placeholder' => '',
            'required' => false,
            'disabled' => false,
            'multiple' => false,
            'data' => array()
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $id = $args['id'] ?: $args['name'];
        $class = 'mv-select ' . $args['class'];
        
        $attributes = array(
            'name="' . esc_attr($args['name']) . ($args['multiple'] ? '[]' : '') . '"',
            'id="' . esc_attr($id) . '"',
            'class="' . esc_attr($class) . '"'
        );
        
        if ($args['required']) {
            $attributes[] = 'required';
        }
        
        if ($args['disabled']) {
            $attributes[] = 'disabled';
        }
        
        if ($args['multiple']) {
            $attributes[] = 'multiple';
        }
        
        // Data attributes
        foreach ($args['data'] as $key => $value) {
            $attributes[] = 'data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        $html = '<select ' . implode(' ', $attributes) . '>';
        
        if ($args['placeholder'] && !$args['multiple']) {
            $html .= '<option value="">' . esc_html($args['placeholder']) . '</option>';
        }
        
        foreach ($args['options'] as $value => $label) {
            $selected_attr = '';
            
            if ($args['multiple'] && is_array($args['selected'])) {
                $selected_attr = in_array($value, $args['selected']) ? ' selected' : '';
            } else {
                $selected_attr = selected($args['selected'], $value, false);
            }
            
            $html .= '<option value="' . esc_attr($value) . '"' . $selected_attr . '>';
            $html .= esc_html($label);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }
}

/**
 * Crear input HTML
 */
if (!function_exists('mv_html_input')) {
    function mv_html_input($args) {
        $defaults = array(
            'type' => 'text',
            'name' => '',
            'id' => '',
            'class' => '',
            'value' => '',
            'placeholder' => '',
            'required' => false,
            'readonly' => false,
            'disabled' => false,
            'min' => '',
            'max' => '',
            'step' => '',
            'pattern' => '',
            'data' => array()
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $id = $args['id'] ?: $args['name'];
        $class = 'mv-input ' . $args['class'];
        
        $attributes = array(
            'type="' . esc_attr($args['type']) . '"',
            'name="' . esc_attr($args['name']) . '"',
            'id="' . esc_attr($id) . '"',
            'class="' . esc_attr($class) . '"',
            'value="' . esc_attr($args['value']) . '"'
        );
        
        if ($args['placeholder']) {
            $attributes[] = 'placeholder="' . esc_attr($args['placeholder']) . '"';
        }
        
        if ($args['required']) {
            $attributes[] = 'required';
        }
        
        if ($args['readonly']) {
            $attributes[] = 'readonly';
        }
        
        if ($args['disabled']) {
            $attributes[] = 'disabled';
        }
        
        // Atributos numéricos
        if (in_array($args['type'], array('number', 'range'))) {
            if ($args['min'] !== '') {
                $attributes[] = 'min="' . esc_attr($args['min']) . '"';
            }
            if ($args['max'] !== '') {
                $attributes[] = 'max="' . esc_attr($args['max']) . '"';
            }
            if ($args['step'] !== '') {
                $attributes[] = 'step="' . esc_attr($args['step']) . '"';
            }
        }
        
        if ($args['pattern']) {
            $attributes[] = 'pattern="' . esc_attr($args['pattern']) . '"';
        }
        
        // Data attributes
        foreach ($args['data'] as $key => $value) {
            $attributes[] = 'data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        return '<input ' . implode(' ', $attributes) . ' />';
    }
}

/**
 * Exportar a CSV
 */
if (!function_exists('mv_export_csv')) {
    function mv_export_csv($filename, $headers, $data) {
        // Headers HTTP
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // BOM para UTF-8
        echo "\xEF\xBB\xBF";
        
        // Abrir output
        $output = fopen('php://output', 'w');
        
        // Escribir headers
        fputcsv($output, $headers, ';');
        
        // Escribir datos
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
}

/**
 * Generar PDF (requiere clase PDF)
 */
if (!function_exists('mv_generate_pdf')) {
    function mv_generate_pdf($type, $id) {
        if (!class_exists('Modulo_Ventas_PDF')) {
            return new WP_Error('pdf_not_available', __('Generación de PDF no disponible', 'modulo-ventas'));
        }
        
        $pdf = new Modulo_Ventas_PDF();
        
        switch ($type) {
            case 'cotizacion':
                return $pdf->generar_cotizacion($id);
                
            case 'reporte':
                return $pdf->generar_reporte($id);
                
            default:
                return new WP_Error('invalid_type', __('Tipo de PDF inválido', 'modulo-ventas'));
        }
    }
}

/**
 * Función de debug (solo en modo desarrollo)
 */
if (!function_exists('mv_debug')) {
    function mv_debug($data, $label = '') {
        if (!WP_DEBUG) {
            return;
        }
        
        echo '<div style="background:#f5f5f5;border:1px solid #ccc;padding:10px;margin:10px 0;">';
        if ($label) {
            echo '<strong>' . esc_html($label) . ':</strong><br>';
        }
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        echo '</div>';
    }
}

/**
 * Validar permisos AJAX
 */
if (!function_exists('mv_ajax_check_permissions')) {
    function mv_ajax_check_permissions($capability = 'manage_woocommerce', $nonce_action = null) {
        // Verificar nonce si se proporciona
        if ($nonce_action) {
            check_ajax_referer($nonce_action, 'nonce');
        }
        
        // Verificar capacidad
        if (!current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('No tiene permisos suficientes para realizar esta acción.', 'modulo-ventas')
            ));
        }
        
        return true;
    }
}

/**
 * Helper para mostrar tooltips
 */
if (!function_exists('mv_tooltip')) {
    function mv_tooltip($content, $text, $position = 'top') {
        return Modulo_Ventas_Messages::get_instance()->tooltip($content, $text, $position);
    }
}

/**
 * Calcular fecha de expiración
 */
if (!function_exists('mv_calcular_fecha_expiracion')) {
    function mv_calcular_fecha_expiracion($fecha_base = null, $dias = null) {
        if (!$fecha_base) {
            $fecha_base = current_time('Y-m-d');
        }
        
        if (!$dias) {
            $dias = get_option('modulo_ventas_dias_expiracion', 30);
        }
        
        return date('Y-m-d', strtotime($fecha_base . ' + ' . $dias . ' days'));
    }
}

/**
 * Obtener nombre legible del estado de cotización
 * (Esta función parece no estar en helpers.php)
 */
if (!function_exists('mv_obtener_nombre_estado')) {
    function mv_obtener_nombre_estado($estado) {
        $estados = array(
            'borrador' => __('Borrador', 'modulo-ventas'),
            'pendiente' => __('Pendiente', 'modulo-ventas'),
            'enviada' => __('Enviada', 'modulo-ventas'),
            'aprobada' => __('Aprobada', 'modulo-ventas'),
            'rechazada' => __('Rechazada', 'modulo-ventas'),
            'expirada' => __('Expirada', 'modulo-ventas'),
            'convertida' => __('Convertida en Venta', 'modulo-ventas'),
            'cancelada' => __('Cancelada', 'modulo-ventas')
        );
        
        return isset($estados[$estado]) ? $estados[$estado] : $estado;
    }
}

/**
 * Obtener icono para tipo de actividad
 * (Esta función parece no estar en helpers.php)
 */
if (!function_exists('mv_obtener_icono_actividad')) {
    function mv_obtener_icono_actividad($tipo) {
        $iconos = array(
            'cotizacion' => 'dashicons-media-document',
            'pedido' => 'dashicons-cart',
            'nota' => 'dashicons-edit',
            'email' => 'dashicons-email',
            'llamada' => 'dashicons-phone',
            'reunion' => 'dashicons-groups',
            'tarea' => 'dashicons-clipboard',
            'sistema' => 'dashicons-info'
        );
        
        return isset($iconos[$tipo]) ? $iconos[$tipo] : 'dashicons-marker';
    }
}

/**
 * Obtener estadísticas del dashboard de clientes
 * (Esta función parece no estar en helpers.php)
 */
if (!function_exists('mv_obtener_estadisticas_clientes')) {
    function mv_obtener_estadisticas_clientes() {
        global $wpdb;
        $tabla_clientes = $wpdb->prefix . 'mv_clientes';
        $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        
        $stats = array(
            'total' => 0,
            'activos' => 0,
            'nuevos_mes' => 0,
            'con_cotizaciones' => 0
        );
        
        // Total de clientes
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_clientes");
        
        // Clientes activos
        $stats['activos'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $tabla_clientes WHERE estado = 'activo'"
        );
        
        // Nuevos este mes
        $primer_dia_mes = date('Y-m-01');
        $stats['nuevos_mes'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla_clientes WHERE fecha_creacion >= %s",
            $primer_dia_mes
        ));
        
        // Con cotizaciones
        $stats['con_cotizaciones'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT c.id) 
            FROM $tabla_clientes c 
            INNER JOIN $tabla_cotizaciones cot ON c.id = cot.cliente_id"
        );
        
        return $stats;
    }
}

/**
 * Obtener regiones de Chile (versión corregida)
 * Nota: Ya existe mv_get_regiones_chile() pero necesitamos mv_obtener_regiones_chile()
 * para mantener consistencia con el código existente
 */
if (!function_exists('mv_obtener_nombre_region')) {
    function mv_obtener_nombre_region($codigo) {
        $regiones = array(
            'I' => 'Tarapacá',
            'II' => 'Antofagasta',
            'III' => 'Atacama',
            'IV' => 'Coquimbo',
            'V' => 'Valparaíso',
            'VI' => 'O\'Higgins',
            'VII' => 'Maule',
            'VIII' => 'Biobío',
            'IX' => 'La Araucanía',
            'X' => 'Los Lagos',
            'XI' => 'Aysén',
            'XII' => 'Magallanes',
            'RM' => 'Metropolitana',
            'XIV' => 'Los Ríos',
            'XV' => 'Arica y Parinacota',
            'XVI' => 'Ñuble'
        );
        
        return isset($regiones[$codigo]) ? $regiones[$codigo] : $codigo;
    }
}

/**
 * Funciones auxiliares para configuración del Módulo de Ventas
 * Estas funciones deben agregarse al archivo includes/helpers.php
 */

/**
 * Obtener configuración completa del plugin
 */
if (!function_exists('mv_get_configuracion_completa')) {
    function mv_get_configuracion_completa() {
        return array(
            // General
            'prefijo_cotizacion' => get_option('modulo_ventas_prefijo_cotizacion', 'COT'),
            'dias_expiracion' => get_option('modulo_ventas_dias_expiracion', 30),
            'numeracion_tipo' => get_option('modulo_ventas_numeracion_tipo', 'consecutiva'),
            'moneda_predeterminada' => get_option('modulo_ventas_moneda_predeterminada', 'CLP'),
            'decimales_precio' => get_option('modulo_ventas_decimales_precio', '2'),
            'decimales_cantidad' => get_option('modulo_ventas_decimales_cantidad', '0'),
            
            // Stock y Almacenes
            'permitir_cotizar_sin_stock' => get_option('modulo_ventas_permitir_cotizar_sin_stock', 'no'),
            'reservar_stock' => get_option('modulo_ventas_reservar_stock', 'no'),
            'tiempo_reserva_stock' => get_option('modulo_ventas_tiempo_reserva_stock', 24),
            'almacen_predeterminado' => get_option('modulo_ventas_almacen_predeterminado', 0),
            'sincronizar_stock' => get_option('modulo_ventas_sincronizar_stock', 'yes'),
            'mostrar_stock_cotizacion' => get_option('modulo_ventas_mostrar_stock_cotizacion', 'yes'),
            
            // Emails y Notificaciones
            'notificar_nueva_cotizacion' => get_option('modulo_ventas_notificar_nueva_cotizacion', 'yes'),
            'emails_notificacion' => get_option('modulo_ventas_emails_notificacion', get_option('admin_email')),
            'plantilla_email_cotizacion' => get_option('modulo_ventas_plantilla_email_cotizacion', mv_get_plantilla_email_default()),
            
            // Integración
            'crear_cliente_automatico' => get_option('modulo_ventas_crear_cliente_automatico', 'no'),
            'conversion_automatica' => get_option('modulo_ventas_conversion_automatica', 'no'),
            
            // PDF y Documentos
            'logo_empresa' => get_option('modulo_ventas_logo_empresa', ''),
            'info_empresa' => get_option('modulo_ventas_info_empresa', ''),
            'terminos_condiciones' => get_option('modulo_ventas_terminos_condiciones', ''),
            
            // Avanzado
            'log_level' => get_option('modulo_ventas_log_level', 'info'),
            'log_retention_days' => get_option('modulo_ventas_log_retention_days', 30),
            'debug_mode' => get_option('modulo_ventas_debug_mode', 'no'),
        );
    }
}

/**
 * Procesar formulario de configuración
 */
if (!function_exists('mv_procesar_configuracion')) {
    function mv_procesar_configuracion($post_data) {
        try {
            // Manejar reset de configuración
            if (isset($_GET['reset']) && $_GET['reset'] == '1') {
                return mv_reset_configuracion();
            }
            
            // Lista de opciones a procesar
            $opciones_texto = array(
                'modulo_ventas_prefijo_cotizacion',
                'modulo_ventas_numeracion_tipo',
                'modulo_ventas_moneda_predeterminada',
                'modulo_ventas_decimales_precio',
                'modulo_ventas_decimales_cantidad',
                'modulo_ventas_log_level'
            );
            
            $opciones_numero = array(
                'modulo_ventas_dias_expiracion',
                'modulo_ventas_tiempo_reserva_stock',
                'modulo_ventas_almacen_predeterminado',
                'modulo_ventas_log_retention_days'
            );
            
            $opciones_textarea = array(
                'modulo_ventas_emails_notificacion',
                'modulo_ventas_plantilla_email_cotizacion',
                'modulo_ventas_info_empresa',
                'modulo_ventas_terminos_condiciones'
            );
            
            $opciones_checkbox = array(
                'modulo_ventas_permitir_cotizar_sin_stock',
                'modulo_ventas_reservar_stock',
                'modulo_ventas_sincronizar_stock',
                'modulo_ventas_mostrar_stock_cotizacion',
                'modulo_ventas_notificar_nueva_cotizacion',
                'modulo_ventas_crear_cliente_automatico',
                'modulo_ventas_conversion_automatica',
                'modulo_ventas_debug_mode'
            );
            
            // Procesar opciones de texto
            foreach ($opciones_texto as $opcion) {
                if (isset($post_data[$opcion])) {
                    $valor = sanitize_text_field($post_data[$opcion]);
                    
                    // Validaciones específicas
                    if ($opcion === 'modulo_ventas_prefijo_cotizacion') {
                        if (empty($valor) || strlen($valor) > 10) {
                            return array('success' => false, 'message' => __('El prefijo debe tener entre 1 y 10 caracteres.', 'modulo-ventas'));
                        }
                        $valor = strtoupper($valor);
                    }
                    
                    if ($opcion === 'modulo_ventas_decimales_precio') {
                        $valor = intval($valor);
                        if ($valor < 0 || $valor > 4) {
                            return array('success' => false, 'message' => __('Los decimales del precio deben estar entre 0 y 4.', 'modulo-ventas'));
                        }
                    }
                    
                    if ($opcion === 'modulo_ventas_decimales_cantidad') {
                        $valor = intval($valor);
                        if ($valor < 0 || $valor > 3) {
                            return array('success' => false, 'message' => __('Los decimales de cantidad deben estar entre 0 y 3.', 'modulo-ventas'));
                        }
                    }
                    
                    update_option($opcion, $valor);
                }
            }
            
            // Procesar opciones numéricas
            foreach ($opciones_numero as $opcion) {
                if (isset($post_data[$opcion])) {
                    $valor = intval($post_data[$opcion]);
                    
                    // Validaciones específicas
                    if ($opcion === 'modulo_ventas_dias_expiracion') {
                        if ($valor < 1 || $valor > 365) {
                            return array('success' => false, 'message' => __('Los días de expiración deben estar entre 1 y 365.', 'modulo-ventas'));
                        }
                    }
                    
                    if ($opcion === 'modulo_ventas_tiempo_reserva_stock') {
                        if ($valor < 1 || $valor > 72) {
                            return array('success' => false, 'message' => __('El tiempo de reserva debe estar entre 1 y 72 horas.', 'modulo-ventas'));
                        }
                    }
                    
                    update_option($opcion, $valor);
                }
            }
            
            // Procesar opciones textarea
            foreach ($opciones_textarea as $opcion) {
                if (isset($post_data[$opcion])) {
                    $valor = sanitize_textarea_field($post_data[$opcion]);
                    
                    // Validación de emails
                    if ($opcion === 'modulo_ventas_emails_notificacion') {
                        $emails = array_map('trim', explode(',', $valor));
                        $emails_validos = array();
                        
                        foreach ($emails as $email) {
                            if (!empty($email) && is_email($email)) {
                                $emails_validos[] = $email;
                            } elseif (!empty($email)) {
                                return array('success' => false, 'message' => sprintf(__('El email "%s" no es válido.', 'modulo-ventas'), $email));
                            }
                        }
                        
                        $valor = implode(', ', $emails_validos);
                    }
                    
                    update_option($opcion, $valor);
                }
            }
            
            // Procesar checkboxes
            foreach ($opciones_checkbox as $opcion) {
                $valor = isset($post_data[$opcion]) && $post_data[$opcion] === 'yes' ? 'yes' : 'no';
                update_option($opcion, $valor);
            }
            
            // Manejar upload de logo
            if (isset($_FILES['modulo_ventas_logo_empresa']) && $_FILES['modulo_ventas_logo_empresa']['error'] === UPLOAD_ERR_OK) {
                $resultado_logo = mv_manejar_upload_logo($_FILES['modulo_ventas_logo_empresa']);
                if ($resultado_logo['success']) {
                    update_option('modulo_ventas_logo_empresa', $resultado_logo['url']);
                } else {
                    return array('success' => false, 'message' => $resultado_logo['message']);
                }
            }
            
            // Eliminar logo si se solicita
            if (isset($post_data['modulo_ventas_eliminar_logo']) && $post_data['modulo_ventas_eliminar_logo'] === 'yes') {
                $logo_actual = get_option('modulo_ventas_logo_empresa');
                if ($logo_actual) {
                    mv_eliminar_logo($logo_actual);
                    delete_option('modulo_ventas_logo_empresa');
                }
            }
            
            // Actualizar configuración del logger si cambió el nivel
            if (isset($post_data['modulo_ventas_log_level'])) {
                $logger = Modulo_Ventas_Logger::get_instance();
                $logger->set_log_level($post_data['modulo_ventas_log_level']);
            }
            
            // Log de configuración actualizada
            mv_log('Configuración del plugin actualizada', 'info', array(
                'usuario' => get_current_user_id(),
                'tab' => isset($_GET['tab']) ? $_GET['tab'] : 'general'
            ));
            
            return array('success' => true);
            
        } catch (Exception $e) {
            mv_log('Error al guardar configuración: ' . $e->getMessage(), 'error');
            return array('success' => false, 'message' => __('Error interno al guardar la configuración.', 'modulo-ventas'));
        }
    }
}

/**
 * Resetear configuración a valores por defecto
 */
if (!function_exists('mv_reset_configuracion')) {
    function mv_reset_configuracion() {
        $opciones_default = array(
            'modulo_ventas_prefijo_cotizacion' => 'COT',
            'modulo_ventas_dias_expiracion' => 30,
            'modulo_ventas_numeracion_tipo' => 'consecutiva',
            'modulo_ventas_moneda_predeterminada' => 'CLP',
            'modulo_ventas_decimales_precio' => '2',
            'modulo_ventas_decimales_cantidad' => '0',
            'modulo_ventas_permitir_cotizar_sin_stock' => 'no',
            'modulo_ventas_reservar_stock' => 'no',
            'modulo_ventas_tiempo_reserva_stock' => 24,
            'modulo_ventas_almacen_predeterminado' => 0,
            'modulo_ventas_sincronizar_stock' => 'yes',
            'modulo_ventas_mostrar_stock_cotizacion' => 'yes',
            'modulo_ventas_notificar_nueva_cotizacion' => 'yes',
            'modulo_ventas_emails_notificacion' => get_option('admin_email'),
            'modulo_ventas_plantilla_email_cotizacion' => mv_get_plantilla_email_default(),
            'modulo_ventas_crear_cliente_automatico' => 'no',
            'modulo_ventas_conversion_automatica' => 'no',
            'modulo_ventas_info_empresa' => '',
            'modulo_ventas_terminos_condiciones' => '',
            'modulo_ventas_log_level' => 'info',
            'modulo_ventas_log_retention_days' => 30,
            'modulo_ventas_debug_mode' => 'no'
        );
        
        foreach ($opciones_default as $opcion => $valor) {
            update_option($opcion, $valor);
        }
        
        // Eliminar logo si existe
        $logo_actual = get_option('modulo_ventas_logo_empresa');
        if ($logo_actual) {
            mv_eliminar_logo($logo_actual);
            delete_option('modulo_ventas_logo_empresa');
        }
        
        if (function_exists('mv_log')) {
            mv_log('Configuración restablecida a valores por defecto', 'info');
        }
        
        return array('success' => true);
    }
}

/**
 * Obtener plantilla de email por defecto
 */
if (!function_exists('mv_get_plantilla_email_default')) {
    function mv_get_plantilla_email_default() {
        return "Estimado/a {cliente_nombre},

Le enviamos la cotización {folio} solicitada con fecha {fecha}.

Total: {total}

Puede revisar los detalles completos en el siguiente enlace:
{enlace_cotizacion}

Saludos cordiales,
Equipo de Ventas";
    }
}

/**
 * Manejar upload de logo
 */
if (!function_exists('mv_manejar_upload_logo')) {
    function mv_manejar_upload_logo($file) {
        // Verificar que sea una imagen
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            return array('success' => false, 'message' => __('Solo se permiten archivos de imagen (JPG, PNG, GIF, WebP).', 'modulo-ventas'));
        }
        
        // Verificar tamaño (máximo 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return array('success' => false, 'message' => __('El archivo es demasiado grande. Máximo permitido: 2MB.', 'modulo-ventas'));
        }
        
        // Directorio de uploads
        $upload_dir = wp_upload_dir();
        $mv_upload_dir = $upload_dir['basedir'] . '/modulo-ventas/';
        $mv_upload_url = $upload_dir['baseurl'] . '/modulo-ventas/';
        
        // Crear directorio si no existe
        if (!file_exists($mv_upload_dir)) {
            if (!wp_mkdir_p($mv_upload_dir)) {
                return array('success' => false, 'message' => __('No se pudo crear el directorio de uploads.', 'modulo-ventas'));
            }
        }
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo-' . time() . '.' . sanitize_file_name($extension);
        $file_path = $mv_upload_dir . $filename;
        
        // Eliminar logo anterior si existe
        $logo_actual = get_option('modulo_ventas_logo_empresa');
        if ($logo_actual) {
            mv_eliminar_logo($logo_actual);
        }
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return array(
                'success' => true,
                'url' => $mv_upload_url . $filename,
                'path' => $file_path
            );
        }
        
        return array('success' => false, 'message' => __('Error al subir el archivo.', 'modulo-ventas'));
    }
}

/**
 * Eliminar archivo de logo
 */
if (!function_exists('mv_eliminar_logo')) {
    function mv_eliminar_logo($logo_url) {
        if (empty($logo_url)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo_url);
        
        if (file_exists($logo_path)) {
            return unlink($logo_path);
        }
        
        return false;
    }
}

/**
 * Verificar si se puede cotizar sin stock según configuración
 */
if (!function_exists('mv_puede_cotizar_sin_stock')) {
    function mv_puede_cotizar_sin_stock() {
        return get_option('modulo_ventas_permitir_cotizar_sin_stock', 'no') === 'yes';
    }
}

/**
 * Verificar si se debe reservar stock automáticamente
 */
if (!function_exists('mv_debe_reservar_stock')) {
    function mv_debe_reservar_stock() {
        return get_option('modulo_ventas_reservar_stock', 'no') === 'yes';
    }
}

/**
 * Obtener tiempo de reserva de stock en horas
 */
if (!function_exists('mv_get_tiempo_reserva_stock')) {
    function mv_get_tiempo_reserva_stock() {
        return intval(get_option('modulo_ventas_tiempo_reserva_stock', 24));
    }
}

/**
 * Obtener almacén predeterminado
 */
if (!function_exists('mv_get_almacen_predeterminado')) {
    function mv_get_almacen_predeterminado() {
        return intval(get_option('modulo_ventas_almacen_predeterminado', 0));
    }
}

/**
 * Verificar si está activado el modo debug
 */
if (!function_exists('mv_is_debug_mode')) {
    function mv_is_debug_mode() {
        return get_option('modulo_ventas_debug_mode', 'no') === 'yes';
    }
}

/**
 * Obtener número de decimales para precios
 */
if (!function_exists('mv_get_decimales_precio')) {
    function mv_get_decimales_precio() {
        return intval(get_option('modulo_ventas_decimales_precio', '2'));
    }
}

/**
 * Obtener número de decimales para cantidades
 */
if (!function_exists('mv_get_decimales_cantidad')) {
    function mv_get_decimales_cantidad() {
        return intval(get_option('modulo_ventas_decimales_cantidad', '0'));
    }
}

/**
 * Formatear precio según configuración
 */
if (!function_exists('mv_format_precio')) {
    function mv_format_precio($precio, $incluir_simbolo = true) {
        $decimales = mv_get_decimales_precio();
        $moneda = get_option('modulo_ventas_moneda_predeterminada', 'CLP');
        
        $precio_formateado = number_format(floatval($precio), $decimales, ',', '.');
        
        if ($incluir_simbolo) {
            $simbolos = array(
                'CLP' => '$',        // Peso chileno
                'USD' => 'US$',      // Dólar americano  
                'EUR' => '€'         // Euro
            );
            
            $simbolo = isset($simbolos[$moneda]) ? $simbolos[$moneda] : '$';
            return $simbolo . ' ' . $precio_formateado;
        }
        
        return $precio_formateado;
    }
}

/**
 * Formatear cantidad según configuración
 */
if (!function_exists('mv_format_cantidad')) {
    function mv_format_cantidad($cantidad) {
        $decimales = mv_get_decimales_cantidad();
        return number_format($cantidad, $decimales, ',', '.');
    }
}

/**
 * Validar precio según configuración de decimales
 */
if (!function_exists('mv_validar_precio')) {
    function mv_validar_precio($precio) {
        $decimales_permitidos = mv_get_decimales_precio();
        
        // Convertir a float
        $precio = floatval($precio);
        
        if ($precio < 0) {
            return array('valid' => false, 'message' => __('El precio no puede ser negativo.', 'modulo-ventas'));
        }
        
        // Verificar decimales
        $precio_str = number_format($precio, 10, '.', '');
        $partes = explode('.', $precio_str);
        
        if (isset($partes[1])) {
            $decimales_actuales = strlen(rtrim($partes[1], '0'));
            if ($decimales_actuales > $decimales_permitidos) {
                return array(
                    'valid' => false, 
                    'message' => sprintf(__('El precio no puede tener más de %d decimales.', 'modulo-ventas'), $decimales_permitidos)
                );
            }
        }
        
        return array('valid' => true, 'precio' => $precio);
    }
}

/**
 * Validar cantidad según configuración de decimales
 */
if (!function_exists('mv_validar_cantidad')) {
    function mv_validar_cantidad($cantidad) {
        $decimales_permitidos = mv_get_decimales_cantidad();
        
        // Convertir a float
        $cantidad = floatval($cantidad);
        
        if ($cantidad <= 0) {
            return array('valid' => false, 'message' => __('La cantidad debe ser mayor a cero.', 'modulo-ventas'));
        }
        
        // Verificar decimales
        $cantidad_str = number_format($cantidad, 10, '.', '');
        $partes = explode('.', $cantidad_str);
        
        if (isset($partes[1])) {
            $decimales_actuales = strlen(rtrim($partes[1], '0'));
            if ($decimales_actuales > $decimales_permitidos) {
                return array(
                    'valid' => false, 
                    'message' => sprintf(__('La cantidad no puede tener más de %d decimales.', 'modulo-ventas'), $decimales_permitidos)
                );
            }
        }
        
        return array('valid' => true, 'cantidad' => $cantidad);
    }
}

/**
 * Obtener atributos HTML para campos de precio
 */
if (!function_exists('mv_get_precio_field_attributes')) {
    function mv_get_precio_field_attributes() {
        $decimales = mv_get_decimales_precio();
        
        if ($decimales > 0) {
            $step = '0.' . str_repeat('0', $decimales - 1) . '1';
        } else {
            $step = '1';
        }
        
        return array(
            'type' => 'number',
            'step' => $step,
            'min' => '0',
            'class' => 'mv-precio-field'
        );
    }
}

/**
 * Obtener atributos HTML para campos de cantidad
 */
if (!function_exists('mv_get_cantidad_field_attributes')) {
    function mv_get_cantidad_field_attributes() {
        $decimales = mv_get_decimales_cantidad();
        
        if ($decimales > 0) {
            $step = '0.' . str_repeat('0', $decimales - 1) . '1';
        } else {
            $step = '1';
        }
        
        return array(
            'type' => 'number',
            'step' => $step,
            'min' => '0.01',
            'class' => 'mv-cantidad-field'
        );
    }
}

/* Fin funciones auxiliares de página Configuración */



// Funciones auxiliares para PDF (inicio)
/**
 * Subir logo de empresa
 */
if (!function_exists('mv_upload_logo')) {
    function mv_upload_logo($file) {
        // Verificar que se subió un archivo
        if (empty($file['name'])) {
            return new WP_Error('no_file', __('No se seleccionó ningún archivo', 'modulo-ventas'));
        }
        
        // Verificar tipo de archivo
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_type', __('Tipo de archivo no permitido. Use JPG, PNG o GIF.', 'modulo-ventas'));
        }
        
        // Verificar tamaño (max 2MB)
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', __('El archivo es demasiado grande. Máximo 2MB.', 'modulo-ventas'));
        }
        
        // Configurar upload
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => function($dir, $name, $ext) {
                return 'mv-logo-' . time() . $ext;
            }
        );
        
        // Crear directorio si no existe
        $upload_dir = wp_upload_dir();
        $mv_dir = $upload_dir['basedir'] . '/modulo-ventas/logos';
        
        if (!file_exists($mv_dir)) {
            wp_mkdir_p($mv_dir);
        }
        
        // Cambiar directorio temporal para el upload
        add_filter('upload_dir', function($dirs) use ($upload_dir) {
            $dirs['path'] = $upload_dir['basedir'] . '/modulo-ventas/logos';
            $dirs['url'] = $upload_dir['baseurl'] . '/modulo-ventas/logos';
            $dirs['subdir'] = '';
            return $dirs;
        });
        
        // Incluir funciones de WordPress para upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Realizar upload
        $upload_result = wp_handle_upload($file, $upload_overrides);
        
        // Remover filtro
        remove_all_filters('upload_dir');
        
        if (isset($upload_result['error'])) {
            return new WP_Error('upload_error', $upload_result['error']);
        }
        
        return $upload_result;
    }
}

/**
 * Obtener URL del logo actual
 */
if (!function_exists('mv_get_logo_url')) {
    function mv_get_logo_url() {
        $config = get_option('mv_pdf_config', array());
        return isset($config['empresa_logo']) ? $config['empresa_logo'] : '';
    }
}

/**
 * Obtener path del logo actual
 */
if (!function_exists('mv_get_logo_path')) {
    function mv_get_logo_path() {
        $config = get_option('mv_pdf_config', array());
        
        if (isset($config['empresa_logo_path']) && file_exists($config['empresa_logo_path'])) {
            return $config['empresa_logo_path'];
        }
        
        // Fallback: convertir URL a path
        if (isset($config['empresa_logo'])) {
            $upload_dir = wp_upload_dir();
            $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $config['empresa_logo']);
            
            if (file_exists($logo_path)) {
                return $logo_path;
            }
        }
        
        return '';
    }
}

/**
 * Validar configuración de PDF
 */
if (!function_exists('mv_validate_pdf_config')) {
    function mv_validate_pdf_config($config) {
        $errors = array();
        
        // Validar campos requeridos
        if (empty($config['empresa_nombre'])) {
            $errors[] = __('El nombre de la empresa es requerido', 'modulo-ventas');
        }
        
        // Validar email
        if (!empty($config['empresa_email']) && !is_email($config['empresa_email'])) {
            $errors[] = __('El email de la empresa no es válido', 'modulo-ventas');
        }
        
        // Validar URL
        if (!empty($config['empresa_web']) && !filter_var($config['empresa_web'], FILTER_VALIDATE_URL)) {
            $errors[] = __('La URL del sitio web no es válida', 'modulo-ventas');
        }
        
        // Validar márgenes
        $margenes = array('margen_izquierdo', 'margen_derecho', 'margen_superior', 'margen_inferior');
        foreach ($margenes as $margen) {
            if (isset($config[$margen])) {
                $valor = intval($config[$margen]);
                if ($valor < 5 || $valor > 50) {
                    $errors[] = sprintf(__('El margen %s debe estar entre 5 y 50 mm', 'modulo-ventas'), str_replace('margen_', '', $margen));
                }
            }
        }
        
        // Validar tamaño de fuente
        if (isset($config['fuente_tamano'])) {
            $tamano = intval($config['fuente_tamano']);
            if ($tamano < 8 || $tamano > 16) {
                $errors[] = __('El tamaño de fuente debe estar entre 8 y 16 puntos', 'modulo-ventas');
            }
        }
        
        return $errors;
    }
}

/**
 * Obtener configuración de PDF con valores por defecto
 */
if (!function_exists('mv_get_pdf_config')) {
    function mv_get_pdf_config() {
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
            'incluir_logo' => true,
            'logo_posicion' => 'izquierda',
            'mostrar_qr' => false,
            'marca_agua' => false,
            'marca_agua_texto' => __('COTIZACIÓN', 'modulo-ventas')
        );
        
        $config = get_option('mv_pdf_config', array());
        return wp_parse_args($config, $defaults);
    }
}
// Fin funciones auxiliares para PDF