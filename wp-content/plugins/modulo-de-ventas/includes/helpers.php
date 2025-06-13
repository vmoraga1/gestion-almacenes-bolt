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
 * Obtener instancia del plugin
 */
if (!function_exists('mv_get_instance')) {
    static $instance = null;
    
    if (null === $instance) {
        $instance = new stdClass();
        $instance->messages = Modulo_Ventas_Messages::get_instance();
    }
    
    return $instance;
}

/**
 * Acceso directo a la clase de mensajes
 */
function mv_messages() {
    return Modulo_Ventas_Messages::get_instance();
}

/**
 * Acceso directo a la base de datos
 */
function mv_db() {
    return new Modulo_Ventas_DB();
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
 * Formatear RUT chileno
 */
if (!function_exists('mv_formatear_rut')) {
    function mv_formatear_rut($rut) {
        // Limpiar RUT
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 2) {
            return $rut;
        }
        
        // Separar número y dígito verificador
        $numero = substr($rut, 0, -1);
        $dv = substr($rut, -1);
        
        // Formatear con puntos
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
        
        return $numero_formateado . '-' . strtoupper($dv);
    }
}

/**
 * Limpiar RUT (solo números y K)
 */
if (!function_exists('mv_limpiar_rut')) {
    function mv_limpiar_rut($rut) {
        return preg_replace('/[^0-9kK]/', '', strtoupper($rut));
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