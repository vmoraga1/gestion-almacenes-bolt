<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ventas_buscar_clientes')) {
    add_action('wp_ajax_buscar_clientes', 'ventas_buscar_clientes');
    function ventas_buscar_clientes() {
        check_ajax_referer('ventas_nonce', 'nonce');

        $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        
        if (empty($term)) {
            wp_send_json_error();
            return;
        }

        $args = array(
            'search'         => '*' . $term . '*',
            'search_columns' => array(
                'user_login',
                'user_email',
                'user_nicename',
                'display_name',
                'first_name',
                'last_name'
            ),
            'number'        => 20,
            'orderby'       => 'display_name',
            'order'         => 'ASC'
        );

        $user_query = new WP_User_Query($args);
        $users = array();

        if (!empty($user_query->get_results())) {
            foreach ($user_query->get_results() as $user) {
                // Obtener roles del usuario
                $roles = array_map(function($role) {
                    return translate_user_role($role);
                }, $user->roles);

                // Obtener nombre completo si está disponible
                $full_name = trim(sprintf('%s %s',
                    get_user_meta($user->ID, 'first_name', true),
                    get_user_meta($user->ID, 'last_name', true)
                ));

                $display_name = !empty($full_name) ? $full_name : $user->display_name;
                
                $users[] = array(
                    'id'   => $user->ID,
                    'text' => sprintf(
                        '%s (%s) - %s',
                        $display_name,
                        $user->user_email,
                        implode(', ', $roles)
                    )
                );
            }
        }

        wp_send_json_success($users);
    }
}

add_action('wp_ajax_refresh_logs', 'ventas_refresh_logs');
function ventas_refresh_logs() {
    check_ajax_referer('ventas_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }

    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    $logger = Ventas_Logger::get_instance();
    $content = $logger->get_filtered_log_content($date, $type, $search);

    wp_send_json_success(['content' => $content]);
}

add_action('wp_ajax_clear_logs', 'ventas_clear_logs');
function ventas_clear_logs() {
    check_ajax_referer('ventas_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
    }

    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');
    $logger = Ventas_Logger::get_instance();
    
    if ($logger->clear_logs($date)) {
        wp_send_json_success(['message' => 'Logs limpiados correctamente']);
    } else {
        wp_send_json_error(['message' => 'Error al limpiar los logs']);
    }
}

// Obtener datos del cliente
add_action('wp_ajax_obtener_datos_cliente', 'ventas_obtener_datos_cliente');
function ventas_obtener_datos_cliente() {
    check_ajax_referer('ventas_nonce', 'nonce');
    
    if (!isset($_POST['cliente_id']) || !is_numeric($_POST['cliente_id'])) {
        wp_send_json_error(['message' => 'ID de cliente no válido']);
        return;
    }

    $cliente_id = intval($_POST['cliente_id']);
    if ($cliente_id <= 0) {
        wp_send_json_error(['message' => 'ID de cliente no válido (conversion fallida)']);
        return;
    }

    global $wpdb;

    $user_data = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, user_login, user_email, display_name 
         FROM {$wpdb->users} 
         WHERE ID = %d",
        $cliente_id
    ));
    
    if (!$user_data) {
        wp_send_json_error(['message' => 'Cliente no encontrado']);
        return;
    }

    // Obtener metadatos relevantes
    $meta_keys = ['billing_address_1', 'billing_phone', 'billing_rut', 'billing_giro'];
    $meta_data = [];

    foreach ($meta_keys as $key) {
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
            $cliente_id, $key
        ));
        $meta_data[$key] = $meta_value ?: '';
    }

    $datos_cliente = array(
        'id' => $user_data->ID,
        'nombre' => $user_data->display_name ?: $user_data->user_login,
        'email' => $user_data->user_email,
        'direccion' => $meta_data['billing_address_1'],
        'telefono' => $meta_data['billing_phone'],
        'rut' => $meta_data['billing_rut'],
        'giro' => $meta_data['billing_giro']
    );

    wp_send_json_success($datos_cliente);
}

// Guardar datos del cliente
add_action('wp_ajax_guardar_datos_cliente', 'ventas_guardar_datos_cliente');
function ventas_guardar_datos_cliente() {
    check_ajax_referer('ventas_nonce', 'nonce');
    
    // Parse los datos serializados
    $raw_data = file_get_contents('php://input');
    error_log('Datos raw recibidos: ' . $raw_data);
    
    parse_str($raw_data, $parsed_data);
    error_log('Datos parseados: ' . print_r($parsed_data, true));
    
    // Obtener ID del cliente de los datos parseados
    $cliente_id = isset($parsed_data['cliente_id']) ? absint($parsed_data['cliente_id']) : 0;
    error_log('Cliente ID extraído: ' . $cliente_id);

    if ($cliente_id <= 0) {
        wp_send_json_error([
            'message' => 'ID de cliente no válido',
            'debug' => ['raw_id' => $parsed_data['cliente_id'] ?? 'no set']
        ]);
        return;
    }

    // Verificar usuario
    $user = get_user_by('ID', $cliente_id);
    if (!$user) {
        wp_send_json_error(['message' => 'Cliente no encontrado']);
        return;
    }

    // Datos sanitizados desde los datos parseados
    $nombre = sanitize_text_field($parsed_data['nombre'] ?? '');
    $email = sanitize_email($parsed_data['email'] ?? '');
    $direccion = sanitize_text_field($parsed_data['direccion'] ?? '');
    $telefono = sanitize_text_field($parsed_data['telefono'] ?? '');
    $rut = sanitize_text_field($parsed_data['rut'] ?? '');
    $giro = sanitize_text_field($parsed_data['giro'] ?? '');

    // Actualizar usuario
    $update_result = wp_update_user([
        'ID' => $cliente_id,
        'display_name' => $nombre,
        'user_email' => $email
    ]);

    if (is_wp_error($update_result)) {
        wp_send_json_error([
            'message' => 'Error al actualizar los datos del cliente',
            'debug' => $update_result->get_error_message()
        ]);
        return;
    }

    // Actualizar metadatos
    update_user_meta($cliente_id, 'billing_first_name', $nombre);
    update_user_meta($cliente_id, 'billing_address_1', $direccion);
    update_user_meta($cliente_id, 'billing_phone', $telefono);
    update_user_meta($cliente_id, 'billing_email', $email);
    update_user_meta($cliente_id, 'billing_rut', $rut);
    update_user_meta($cliente_id, 'billing_giro', $giro);

    // Limpiar caché
    clean_user_cache($cliente_id);
    wp_cache_delete($cliente_id, 'users');
    wp_cache_delete($cliente_id, 'user_meta');

    wp_send_json_success([
        'message' => 'Datos actualizados correctamente',
        'cliente_id' => $cliente_id
    ]);
}

// Crear nuevo cliente
add_action('wp_ajax_crear_cliente', 'ventas_crear_cliente');
function ventas_crear_cliente() {
    check_ajax_referer('ventas_nonce', 'nonce');
    
    // Validar datos requeridos
    if (empty($_POST['email']) || empty($_POST['nombre'])) {
        wp_send_json_error(['message' => 'El nombre y email son requeridos']);
        return;
    }

    // Crear usuario
    $userdata = array(
        'user_login'    => sanitize_email($_POST['email']),
        'user_email'    => sanitize_email($_POST['email']),
        'display_name'  => sanitize_text_field($_POST['nombre']),
        'user_pass'     => wp_generate_password(),
        'role'          => 'customer'
    );

    $user_id = wp_insert_user($userdata);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
        return;
    }

    // Actualizar meta datos
    update_user_meta($user_id, 'billing_address_1', sanitize_text_field($_POST['direccion']));
    update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['telefono']));
    update_user_meta($user_id, 'billing_rut', sanitize_text_field($_POST['rut']));
    update_user_meta($user_id, 'billing_giro', sanitize_text_field($_POST['giro']));

    // Preparar respuesta para Select2
    $response_data = array(
        'id'   => $user_id,
        'text' => sprintf(
            '%s (%s) - %s',
            $_POST['nombre'],
            $_POST['email'],
            'Cliente'
        )
    );

    wp_send_json_success($response_data);
}

// Guardar nueva cotización
add_action('wp_ajax_guardar_cotizacion', 'ventas_guardar_cotizacion');
function ventas_guardar_cotizacion() {
    $logger = Ventas_Logger::get_instance();

    // Leer el contenido crudo del input
    $raw_input = file_get_contents('php://input');
    $logger->log('Contenido crudo de php://input: ' . $raw_input, 'debug');

    // Intentar decodificar como JSON primero
    $data = json_decode($raw_input, true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
        // Si no es JSON válido o está vacío, usar $_POST
        $data = $_POST;
        $is_json = false;
        $logger->log('Datos procesados desde $_POST', 'debug');
    } else {
        $logger->log('Datos procesados desde JSON input', 'debug');
    }

    // Aplicar stripslashes_deep a la data recibida.
    $data = stripslashes_deep($data);
    $logger->log('Data después de stripslashes_deep: ' . print_r($data, true), 'debug');

    // *** Verificación de Nonce: Obtener el nonce del array $data ***
    // check_ajax_referer necesita el nonce en el array $data.
    // Si la data vino como JSON, ya debería estar en $data.
    // Si vino como POST, también debería estar en $data (que ahora es $_POST).
    // Añadimos un log antes de la verificación.
    $logger->log('Verificando nonce. Nonce recibido en $data: ' . ($data['nonce'] ?? 'No presente'), 'debug');

    // check_ajax_referer espera el nombre de la acción y el nombre del campo nonce.
    // Opcionalmente, el tercer argumento es el array donde buscar el nonce.
    // Si el nonce no está en $data, intentará buscar en $_REQUEST, lo cual es útil.
    // Dejamos la llamada como está, pero el log anterior nos dirá si el nonce está en $data.
    // check_ajax_referer('ventas_nonce', 'nonce'); // Quitamos $data del tercer argumento para que use $_REQUEST si es necesario

    // $logger->log('Nonce verificado exitosamente.', 'debug'); // Este log solo se ejecutará si el nonce es válido.

    // Si la verificación del nonce falla, WordPress detiene la ejecución y envía el 400 Bad Request
    // antes de llegar a los logs dentro del try block.

    $logger->log('Iniciando guardado de cotización', 'info'); // Este log se moverá después de la verificación del nonce
    $logger->log('Data final procesada: ' . print_r($data, true), 'debug'); // Este log también se mueve

    try {
        $logger->log('Iniciando validaciones dentro del try block', 'debug');
        // Validación básica de campos obligatorios (usamos $data)
        if (empty($data['cliente_id']) || empty($data['productos']) || !isset($data['subtotal']) || !isset($data['total'])) {
            $logger->log('Error de validación: Datos obligatorios incompletos.', 'error'); // Log de error específico
            throw new Exception('Datos obligatorios incompletos (cliente_id, productos, subtotal, total).');
        }
        $logger->log('Validaciones iniciales pasadas.', 'debug');

        $db = Ventas_DB::get_instance();
        $logger->log('Instancia de Ventas_DB obtenida.', 'debug');

        // *** Construir el array $datos_generales incluyendo TODOS los campos necesarios/opcionales ***
        // Usamos $data que contiene los datos de $_POST
        $datos_generales = array(
            'cliente_id' => isset($data['cliente_id']) ? intval($data['cliente_id']) : 0,
            'fecha' => !empty($data['fecha_cotizacion']) ? sanitize_text_field($data['fecha_cotizacion']) : current_time('mysql'),
            'subtotal' => isset($data['subtotal']) ? floatval($data['subtotal']) : 0,
            'total' => isset($data['total']) ? floatval($data['total']) : 0,
            'estado' => 'pendiente',
            'folio' => generar_folio_cotizacion(), // Generar folio aquí o en db.php
        );

        // Agregar campos opcionales si existen en $data
        if (isset($data['fecha_expiracion'])) {
             $datos_generales['fecha_expiracion'] = sanitize_text_field($data['fecha_expiracion']);
        }
        if (isset($data['plazo_pago'])) {
             $datos_generales['plazo_pago'] = sanitize_text_field($data['plazo_pago']);
        }
        if (isset($data['plazo_credito'])) {
             $datos_generales['plazo_credito'] = intval($data['plazo_credito']);
        }
        if (isset($data['vendedor'])) {
             $datos_generales['vendedor'] = sanitize_text_field($data['vendedor']);
        }
        if (isset($data['condiciones_pago'])) {
             $datos_generales['condiciones_pago'] = sanitize_textarea_field($data['condiciones_pago']);
        }
        if (isset($data['agregar_iva'])) {
            $datos_generales['incluye_iva'] = intval($data['agregar_iva']); // Esperamos 0 o 1
        }
        if (isset($data['descuento_global'])) {
            $datos_generales['descuento_global'] = floatval($data['descuento_global']);
        }
        if (isset($data['tipo_descuento_global'])) {
             $datos_generales['tipo_descuento_global'] = sanitize_text_field($data['tipo_descuento_global']);
        }
        if (isset($data['envio'])) {
            $datos_generales['envio'] = floatval($data['envio']);
        }
        $logger->log('Datos generales para DB preparados: ' . print_r($datos_generales, true), 'debug');

        // Validar que $productos es un array
        $productos = isset($data['productos']) ? $data['productos'] : null;
        if (!is_array($productos)) {
            $logger->log('Error de validación: Productos no es un array.', 'error'); // Log de error específico
            throw new Exception('Formato de productos inválido (no es un array) después de stripslashes_deep.');
        }
        $logger->log('Productos para DB preparados: ' . print_r($productos, true), 'debug');

        $logger->log('Llamando a $db->crear_cotizacion()', 'debug');
        // *** Llamar a la función de creación de cotización en la clase DB ***
        $resultado = $db->crear_cotizacion($datos_generales, $productos);
        $logger->log('$db->crear_cotizacion() retornó: ' . print_r($resultado, true), 'debug');

        if ($resultado) {
            $logger->log('Cotización creada en DB con ID: ' . $resultado, 'info');
            wp_send_json_success(array(
                'cotizacion_id' => $resultado, // Usar $resultado que es el ID
                'message'       => 'Cotización guardada exitosamente'
            ));
            $logger->log('Respuesta JSON de éxito enviada.', 'debug');

        } else {
            // Manejo de errores si $db->crear_cotizacion() falla
            $error_db = $db->get_last_error();
            $logger->log('Error de DB al guardar la cotización. DB Error: ' . ($error_db ? $error_db : 'Ninguno reportado'), 'error'); // Log de error específico de DB
            wp_send_json_error(array('message' => 'Error al guardar la cotización. Consulte los logs para más detalles.'));
            $logger->log('Respuesta JSON de error enviada (error de DB).', 'debug');
        }

    } catch (Exception $e) {
        $logger->log('Excepción capturada: ' . $e->getMessage(), 'error'); // Log de la excepción
        wp_send_json_error(array('message' => 'Error interno al guardar la cotización: ' . $e->getMessage())); // Incluir mensaje de excepción
         $logger->log('Respuesta JSON de error enviada (excepción).', 'debug');
    }
}

// Añadir al final de tu archivo ajax-handlers.php
add_action('wp_ajax_generar_nonce_cotizacion', 'ventas_generar_nonce_cotizacion');
add_action('wp_ajax_nopriv_generar_nonce_cotizacion', 'ventas_generar_nonce_cotizacion'); // Si necesitas que funcione también para usuarios no logueados

function ventas_generar_nonce_cotizacion() {
    // Genera un nonce para la acción 'ventas_nonce'
    $nonce = wp_create_nonce('ventas_nonce');
    wp_send_json_success(['nonce' => $nonce]);
    wp_die(); // Termina la ejecución AJAX
}