<?php
/**
 * Clase para manejar los clientes del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Clientes {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Modulo_Ventas_DB();
        
        // Hooks para integración con usuarios de WordPress
        add_action('show_user_profile', array($this, 'mostrar_campos_cliente_perfil'));
        add_action('edit_user_profile', array($this, 'mostrar_campos_cliente_perfil'));
        add_action('personal_options_update', array($this, 'guardar_campos_cliente_perfil'));
        add_action('edit_user_profile_update', array($this, 'guardar_campos_cliente_perfil'));
        
        // Hooks para mostrar campos en el listado de usuarios
        add_filter('manage_users_columns', array($this, 'agregar_columnas_usuarios'));
        add_filter('manage_users_custom_column', array($this, 'mostrar_columnas_usuarios'), 10, 3);
        add_filter('manage_users_sortable_columns', array($this, 'columnas_ordenables_usuarios'));
        
        // Hook para cuando se crea un usuario
        add_action('user_register', array($this, 'crear_cliente_desde_usuario'), 10, 1);
        
        // Hook para cuando se elimina un usuario
        add_action('delete_user', array($this, 'desvincular_cliente_de_usuario'), 10, 1);
        
        // Agregar capacidad de búsqueda por RUT
        add_action('pre_user_query', array($this, 'buscar_usuarios_por_rut'));
        
        // Ajax handlers
        //add_action('wp_ajax_mv_buscar_cliente', array($this, 'ajax_buscar_cliente'));
        //add_action('wp_ajax_mv_crear_cliente_rapido', array($this, 'ajax_crear_cliente_rapido'));
        //add_action('wp_ajax_mv_obtener_cliente', array($this, 'ajax_obtener_cliente'));
    }
    
    /**
     * Mostrar campos de cliente en el perfil de usuario
     */
    public function mostrar_campos_cliente_perfil($user) {
        // Verificar permisos
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        // Obtener datos del cliente si existe
        $cliente = $this->obtener_cliente_por_usuario($user->ID);
        
        // Obtener regiones de Chile
        $regiones = $this->obtener_regiones_chile();
        ?>
        
        <h2><?php _e('Información de Cliente', 'modulo-ventas'); ?></h2>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="mv_cliente_razon_social"><?php _e('Razón Social', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="text" 
                        name="mv_cliente[razon_social]" 
                        id="mv_cliente_razon_social" 
                        value="<?php echo esc_attr($cliente ? $cliente->razon_social : $user->display_name); ?>" 
                        class="regular-text" />
                    <p class="description"><?php _e('Nombre del cliente o empresa', 'modulo-ventas'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_rut"><?php _e('RUT', 'modulo-ventas'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" 
                        name="mv_cliente[rut]" 
                        id="mv_cliente_rut" 
                        value="<?php echo esc_attr($cliente ? $cliente->rut : ''); ?>" 
                        class="regular-text" 
                        placeholder="12.345.678-9" />
                    <span id="mv_rut_validacion" class="description"></span>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_giro"><?php _e('Giro Comercial', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="text" 
                        name="mv_cliente[giro_comercial]" 
                        id="mv_cliente_giro" 
                        value="<?php echo esc_attr($cliente ? $cliente->giro_comercial : ''); ?>" 
                        class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_telefono"><?php _e('Teléfono', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="tel" 
                        name="mv_cliente[telefono]" 
                        id="mv_cliente_telefono" 
                        value="<?php echo esc_attr($cliente ? $cliente->telefono : ''); ?>" 
                        class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_email"><?php _e('Email', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="email" 
                        name="mv_cliente[email]" 
                        id="mv_cliente_email" 
                        value="<?php echo esc_attr($cliente ? $cliente->email : $user->user_email); ?>" 
                        class="regular-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_email_dte"><?php _e('Email DTE', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="email" 
                        name="mv_cliente[email_dte]" 
                        id="mv_cliente_email_dte" 
                        value="<?php echo esc_attr($cliente ? $cliente->email_dte : ''); ?>" 
                        class="regular-text" />
                    <p class="description"><?php _e('Email para recepción de documentos tributarios electrónicos', 'modulo-ventas'); ?></p>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Dirección de Facturación', 'modulo-ventas'); ?></h3>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="mv_cliente_direccion_fact"><?php _e('Dirección', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="text" 
                        name="mv_cliente[direccion_facturacion]" 
                        id="mv_cliente_direccion_fact" 
                        value="<?php echo esc_attr($cliente ? $cliente->direccion_facturacion : ''); ?>" 
                        class="large-text" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_region_fact"><?php _e('Región', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <select name="mv_cliente[region_facturacion]" 
                            id="mv_cliente_region_fact" 
                            class="mv-select-region" 
                            data-target="comuna_facturacion">
                        <option value=""><?php _e('Seleccione una región', 'modulo-ventas'); ?></option>
                        <?php foreach ($regiones as $region) : ?>
                            <option value="<?php echo esc_attr($region); ?>" 
                                    <?php selected($cliente ? $cliente->region_facturacion : '', $region); ?>>
                                <?php echo esc_html($region); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_comuna_fact"><?php _e('Comuna', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <select name="mv_cliente[comuna_facturacion]" 
                            id="mv_cliente_comuna_fact" 
                            class="mv-select-comuna">
                        <option value=""><?php _e('Seleccione primero una región', 'modulo-ventas'); ?></option>
                        <?php if ($cliente && $cliente->region_facturacion) : ?>
                            <?php $comunas = $this->obtener_comunas_por_region($cliente->region_facturacion); ?>
                            <?php foreach ($comunas as $comuna) : ?>
                                <option value="<?php echo esc_attr($comuna); ?>" 
                                        <?php selected($cliente->comuna_facturacion, $comuna); ?>>
                                    <?php echo esc_html($comuna); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="mv_cliente_ciudad_fact"><?php _e('Ciudad', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <input type="text" 
                        name="mv_cliente[ciudad_facturacion]" 
                        id="mv_cliente_ciudad_fact" 
                        value="<?php echo esc_attr($cliente ? $cliente->ciudad_facturacion : ''); ?>" 
                        class="regular-text" />
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Dirección de Envío', 'modulo-ventas'); ?></h3>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="mv_cliente_mismo_envio"><?php _e('Usar dirección de facturación', 'modulo-ventas'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                            name="mv_cliente[usar_direccion_facturacion_para_envio]" 
                            id="mv_cliente_mismo_envio" 
                            value="1" 
                            <?php checked($cliente ? $cliente->usar_direccion_facturacion_para_envio : 1, 1); ?> />
                        <?php _e('La dirección de envío es la misma que la de facturación', 'modulo-ventas'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <div id="mv_direccion_envio_diferente" style="<?php echo ($cliente && !$cliente->usar_direccion_facturacion_para_envio) ? '' : 'display:none;'; ?>">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="mv_cliente_direccion_envio"><?php _e('Dirección', 'modulo-ventas'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                            name="mv_cliente[direccion_envio]" 
                            id="mv_cliente_direccion_envio" 
                            value="<?php echo esc_attr($cliente ? $cliente->direccion_envio : ''); ?>" 
                            class="large-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="mv_cliente_region_envio"><?php _e('Región', 'modulo-ventas'); ?></label>
                    </th>
                    <td>
                        <select name="mv_cliente[region_envio]" 
                                id="mv_cliente_region_envio" 
                                class="mv-select-region" 
                                data-target="comuna_envio">
                            <option value=""><?php _e('Seleccione una región', 'modulo-ventas'); ?></option>
                            <?php foreach ($regiones as $region) : ?>
                                <option value="<?php echo esc_attr($region); ?>" 
                                        <?php selected($cliente ? $cliente->region_envio : '', $region); ?>>
                                    <?php echo esc_html($region); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="mv_cliente_comuna_envio"><?php _e('Comuna', 'modulo-ventas'); ?></label>
                    </th>
                    <td>
                        <select name="mv_cliente[comuna_envio]" 
                                id="mv_cliente_comuna_envio" 
                                class="mv-select-comuna">
                            <option value=""><?php _e('Seleccione primero una región', 'modulo-ventas'); ?></option>
                            <?php if ($cliente && $cliente->region_envio) : ?>
                                <?php $comunas = $this->obtener_comunas_por_region($cliente->region_envio); ?>
                                <?php foreach ($comunas as $comuna) : ?>
                                    <option value="<?php echo esc_attr($comuna); ?>" 
                                            <?php selected($cliente->comuna_envio, $comuna); ?>>
                                        <?php echo esc_html($comuna); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="mv_cliente_ciudad_envio"><?php _e('Ciudad', 'modulo-ventas'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                            name="mv_cliente[ciudad_envio]" 
                            id="mv_cliente_ciudad_envio" 
                            value="<?php echo esc_attr($cliente ? $cliente->ciudad_envio : ''); ?>" 
                            class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle dirección de envío
            $('#mv_cliente_mismo_envio').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#mv_direccion_envio_diferente').hide();
                } else {
                    $('#mv_direccion_envio_diferente').show();
                }
            });
            
            // Validar RUT en tiempo real
            $('#mv_cliente_rut').on('blur', function() {
                var rut = $(this).val();
                if (rut) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mv_validar_rut',
                            rut: rut,
                            nonce: '<?php echo wp_create_nonce('mv_validar_rut'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#mv_rut_validacion').html('<span style="color:green;">' + response.data.message + '</span>');
                            } else {
                                $('#mv_rut_validacion').html('<span style="color:red;">' + response.data.message + '</span>');
                            }
                        }
                    });
                }
            });
            
            // Cargar comunas según región
            $('.mv-select-region').on('change', function() {
                var region = $(this).val();
                var target = $(this).data('target');
                var $comunaSelect = $('select[name="mv_cliente[' + target + ']"]');
                
                if (region) {
                    $comunaSelect.html('<option value="">Cargando...</option>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mv_obtener_comunas',
                            region: region,
                            nonce: '<?php echo wp_create_nonce('mv_obtener_comunas'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var options = '<option value="">Seleccione una comuna</option>';
                                $.each(response.data.comunas, function(i, comuna) {
                                    options += '<option value="' + comuna + '">' + comuna + '</option>';
                                });
                                $comunaSelect.html(options);
                            }
                        }
                    });
                } else {
                    $comunaSelect.html('<option value="">Seleccione primero una región</option>');
                }
            });
        });
        </script>
        
        <?php
    }
    
    /**
     * Guardar campos de cliente desde el perfil de usuario
     */
    public function guardar_campos_cliente_perfil($user_id) {
        // Verificar permisos
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Verificar nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id)) {
            return;
        }
        
        if (!isset($_POST['mv_cliente'])) {
            return;
        }
        
        $datos_cliente = $_POST['mv_cliente'];
        
        // Validar RUT
        if (empty($datos_cliente['rut'])) {
            add_action('user_profile_update_errors', function($errors) {
                $errors->add('mv_rut_required', __('El RUT es obligatorio para clientes.', 'modulo-ventas'));
            });
            return;
        }
        
        // Buscar si el usuario ya tiene un cliente asociado
        $cliente_existente = $this->obtener_cliente_por_usuario($user_id);
        
        if ($cliente_existente) {
            // Actualizar cliente existente
            $datos_cliente['user_id'] = $user_id;
            $resultado = $this->db->actualizar_cliente($cliente_existente->id, $datos_cliente);
            
            if (is_wp_error($resultado)) {
                add_action('user_profile_update_errors', function($errors) use ($resultado) {
                    $errors->add('mv_cliente_error', $resultado->get_error_message());
                });
            }
        } else {
            // Crear nuevo cliente
            $datos_cliente['user_id'] = $user_id;
            $resultado = $this->db->crear_cliente($datos_cliente);
            
            if (is_wp_error($resultado)) {
                add_action('user_profile_update_errors', function($errors) use ($resultado) {
                    $errors->add('mv_cliente_error', $resultado->get_error_message());
                });
            }
        }
    }
    
    /**
     * Agregar columnas al listado de usuarios
     */
    public function agregar_columnas_usuarios($columns) {
        $columns['mv_rut'] = __('RUT', 'modulo-ventas');
        $columns['mv_razon_social'] = __('Razón Social', 'modulo-ventas');
        $columns['mv_telefono'] = __('Teléfono', 'modulo-ventas');
        return $columns;
    }
    
    /**
     * Mostrar contenido de columnas personalizadas
     */
    public function mostrar_columnas_usuarios($value, $column_name, $user_id) {
        $cliente = $this->obtener_cliente_por_usuario($user_id);
        
        if (!$cliente) {
            return '—';
        }
        
        switch ($column_name) {
            case 'mv_rut':
                return esc_html($cliente->rut);
                
            case 'mv_razon_social':
                return esc_html($cliente->razon_social);
                
            case 'mv_telefono':
                return esc_html($cliente->telefono ?: '—');
                
            default:
                return $value;
        }
    }
    
    /**
     * Hacer columnas ordenables
     */
    public function columnas_ordenables_usuarios($columns) {
        $columns['mv_rut'] = 'mv_rut';
        $columns['mv_razon_social'] = 'mv_razon_social';
        return $columns;
    }
    
    /**
     * Modificar query para buscar por RUT
     */
    public function buscar_usuarios_por_rut($query) {
        global $wpdb;
        
        if (!is_admin() || !$query->is_search()) {
            return;
        }
        
        $search = trim($query->get('search'));
        if (!$search) {
            return;
        }
        
        // Buscar en la tabla de clientes
        $tabla_clientes = $this->db->get_tabla_clientes();
        $cliente_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$tabla_clientes} 
             WHERE rut LIKE %s AND user_id IS NOT NULL",
            '%' . $wpdb->esc_like($search) . '%'
        ));
        
        if (!empty($cliente_ids)) {
            $query->set('include', array_merge($query->get('include', array()), $cliente_ids));
        }
    }
    
    /**
     * Crear cliente cuando se registra un usuario
     */
    public function crear_cliente_desde_usuario($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return;
        }
        
        // Solo crear cliente si tiene el rol adecuado
        if (!in_array('customer', $user->roles) && !in_array('subscriber', $user->roles)) {
            return;
        }
        
        // Preparar datos básicos
        $datos_cliente = array(
            'user_id' => $user_id,
            'razon_social' => $user->display_name ?: $user->user_login,
            'email' => $user->user_email,
            'rut' => '', // Deberá completarse después
        );
        
        // Intentar obtener datos de WooCommerce si existen
        if (function_exists('WC')) {
            $customer = new WC_Customer($user_id);
            
            if ($customer) {
                $datos_cliente['razon_social'] = $customer->get_billing_company() ?: $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name();
                $datos_cliente['telefono'] = $customer->get_billing_phone();
                $datos_cliente['direccion_facturacion'] = $customer->get_billing_address_1();
                $datos_cliente['ciudad_facturacion'] = $customer->get_billing_city();
                $datos_cliente['region_facturacion'] = $customer->get_billing_state();
                $datos_cliente['pais_facturacion'] = $customer->get_billing_country() ?: 'CL';
                
                // Dirección de envío
                if ($customer->get_shipping_address_1()) {
                    $datos_cliente['usar_direccion_facturacion_para_envio'] = 0;
                    $datos_cliente['direccion_envio'] = $customer->get_shipping_address_1();
                    $datos_cliente['ciudad_envio'] = $customer->get_shipping_city();
                    $datos_cliente['region_envio'] = $customer->get_shipping_state();
                    $datos_cliente['pais_envio'] = $customer->get_shipping_country() ?: 'CL';
                }
            }
        }
        
        // No crear si no hay RUT (se creará cuando se complete el perfil)
        if (empty($datos_cliente['rut'])) {
            return;
        }
        
        $this->db->crear_cliente($datos_cliente);
    }
    
    /**
     * Desvincular cliente cuando se elimina un usuario
     */
    public function desvincular_cliente_de_usuario($user_id) {
        $cliente = $this->obtener_cliente_por_usuario($user_id);
        
        if ($cliente) {
            // No eliminar el cliente, solo desvincular
            $this->db->actualizar_cliente($cliente->id, array('user_id' => null));
        }
    }
    
    /**
     * Obtener cliente por ID de usuario
     */
    public function obtener_cliente_por_usuario($user_id) {
        global $wpdb;
        $tabla_clientes = $this->db->get_tabla_clientes();
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$tabla_clientes} WHERE user_id = %d",
            $user_id
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * AJAX: Buscar cliente
     */
    public function ajax_buscar_cliente() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $termino = isset($_POST['termino']) ? sanitize_text_field($_POST['termino']) : '';
        
        $clientes = $this->db->buscar_clientes($termino, array('limit' => 10));
        
        $resultados = array();
        foreach ($clientes as $cliente) {
            $resultados[] = array(
                'id' => $cliente->id,
                'text' => sprintf('%s - %s', $cliente->razon_social, $cliente->rut),
                'razon_social' => $cliente->razon_social,
                'rut' => $cliente->rut,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion_facturacion,
                'comuna' => $cliente->comuna_facturacion,
                'ciudad' => $cliente->ciudad_facturacion,
                'region' => $cliente->region_facturacion
            );
        }
        
        wp_send_json_success(array('results' => $resultados));
    }
    
    /**
     * AJAX: Crear cliente rápido
     */
    public function ajax_crear_cliente_rapido() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $datos = $_POST['cliente'];
        
        // Validaciones básicas
        if (empty($datos['razon_social']) || empty($datos['rut'])) {
            wp_send_json_error(array('message' => __('Razón social y RUT son obligatorios', 'modulo-ventas')));
        }
        
        // Validar formato RUT
        if (!$this->validar_formato_rut($datos['rut'])) {
            wp_send_json_error(array('message' => __('Formato de RUT inválido', 'modulo-ventas')));
        }
        
        $cliente_id = $this->db->crear_cliente($datos);
        
        if (is_wp_error($cliente_id)) {
            wp_send_json_error(array('message' => $cliente_id->get_error_message()));
        }
        
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        wp_send_json_success(array(
            'cliente' => $cliente,
            'message' => __('Cliente creado exitosamente', 'modulo-ventas')
        ));
    }
    
    /**
     * AJAX: Obtener datos de cliente
     */
    public function ajax_obtener_cliente() {
        check_ajax_referer('modulo_ventas_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Sin permisos', 'modulo-ventas')));
        }
        
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        if (!$cliente_id) {
            wp_send_json_error(array('message' => __('ID de cliente inválido', 'modulo-ventas')));
        }
        
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        if (!$cliente) {
            wp_send_json_error(array('message' => __('Cliente no encontrado', 'modulo-ventas')));
        }
        
        wp_send_json_success(array('cliente' => $cliente));
    }
    
    /**
     * Obtener regiones de Chile
     */
    public function obtener_regiones_chile() {
        return array(
            'Arica y Parinacota',
            'Tarapacá',
            'Antofagasta',
            'Atacama',
            'Coquimbo',
            'Valparaíso',
            'Metropolitana de Santiago',
            'Libertador General Bernardo O\'Higgins',
            'Maule',
            'Ñuble',
            'Biobío',
            'La Araucanía',
            'Los Ríos',
            'Los Lagos',
            'Aysén del General Carlos Ibáñez del Campo',
            'Magallanes y de la Antártica Chilena'
        );
    }
    
    /**
     * Obtener comunas por región
     */
    public function obtener_comunas_por_region($region) {
        // Array simplificado de comunas por región
        // En producción, esto debería venir de una base de datos o API
        $comunas_por_region = array(
            'Arica y Parinacota' => array('Arica', 'Camarones', 'Putre', 'General Lagos'),
            'Tarapacá' => array('Iquique', 'Alto Hospicio', 'Pozo Almonte', 'Camiña', 'Colchane', 'Huara', 'Pica'),
            'Antofagasta' => array('Antofagasta', 'Mejillones', 'Sierra Gorda', 'Taltal', 'Calama', 'Ollagüe', 'San Pedro de Atacama', 'Tocopilla', 'María Elena'),
            'Atacama' => array('Copiapó', 'Caldera', 'Tierra Amarilla', 'Chañaral', 'Diego de Almagro', 'Vallenar', 'Alto del Carmen', 'Freirina', 'Huasco'),
            'Coquimbo' => array('La Serena', 'Coquimbo', 'Andacollo', 'La Higuera', 'Paihuano', 'Vicuña', 'Illapel', 'Canela', 'Los Vilos', 'Salamanca', 'Ovalle', 'Combarbalá', 'Monte Patria', 'Punitaqui', 'Río Hurtado'),
            'Valparaíso' => array('Valparaíso', 'Casablanca', 'Concón', 'Juan Fernández', 'Puchuncaví', 'Quintero', 'Viña del Mar', 'Isla de Pascua', 'Los Andes', 'Calle Larga', 'Rinconada', 'San Esteban', 'La Ligua', 'Cabildo', 'Papudo', 'Petorca', 'Zapallar', 'Quillota', 'La Calera', 'Hijuelas', 'La Cruz', 'Nogales', 'San Antonio', 'Algarrobo', 'Cartagena', 'El Quisco', 'El Tabo', 'Santo Domingo', 'San Felipe', 'Catemu', 'Llay Llay', 'Panquehue', 'Putaendo', 'Santa María', 'Quilpué', 'Limache', 'Olmué', 'Villa Alemana'),
            'Metropolitana de Santiago' => array('Santiago', 'Cerrillos', 'Cerro Navia', 'Conchalí', 'El Bosque', 'Estación Central', 'Huechuraba', 'Independencia', 'La Cisterna', 'La Florida', 'La Granja', 'La Pintana', 'La Reina', 'Las Condes', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado', 'Macul', 'Maipú', 'Ñuñoa', 'Pedro Aguirre Cerda', 'Peñalolén', 'Providencia', 'Pudahuel', 'Quilicura', 'Quinta Normal', 'Recoleta', 'Renca', 'San Joaquín', 'San Miguel', 'San Ramón', 'Vitacura', 'Puente Alto', 'Pirque', 'San José de Maipo', 'Colina', 'Lampa', 'Tiltil', 'San Bernardo', 'Buin', 'Calera de Tango', 'Paine', 'Melipilla', 'Alhué', 'Curacaví', 'María Pinto', 'San Pedro', 'Talagante', 'El Monte', 'Isla de Maipo', 'Padre Hurtado', 'Peñaflor'),
            // ... Agregar más regiones según necesidad
        );
        
        return isset($comunas_por_region[$region]) ? $comunas_por_region[$region] : array();
    }
    
    /**
     * Obtener lista de clientes para mostrar en select
     */
    public function obtener_clientes_para_select($args = array()) {
        $defaults = array(
            'orderby' => 'razon_social',
            'order' => 'ASC',
            'limit' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return $this->db->obtener_clientes_para_select();
    }

    /**
     * Obtener nombre legible del estado de cotización
     *
     * @param string $estado Estado interno
     * @return string Nombre legible
     */
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

    /**
     * Obtener icono para tipo de actividad
     *
     * @param string $tipo Tipo de actividad
     * @return string Clase del icono dashicon
     */
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

    /**
     * Verificar permisos AJAX y nonce
     *
     * @param string $capability Capacidad requerida
     * @param string $nonce_action Acción del nonce
     * @param string $nonce_field Campo del nonce (por defecto 'nonce')
     */
    function mv_ajax_check_permissions($capability, $nonce_action, $nonce_field = 'nonce') {
        // Verificar nonce
        if (!check_ajax_referer($nonce_action, $nonce_field, false)) {
            wp_send_json_error(array('message' => __('Error de seguridad', 'modulo-ventas')));
        }
        
        // Verificar permisos
        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => __('Sin permisos suficientes', 'modulo-ventas')));
        }
    }
    
    /**
     * Sincronizar cliente con usuario de WooCommerce
     */
    public function sincronizar_con_woocommerce($cliente_id, $user_id = null) {
        $cliente = $this->db->obtener_cliente($cliente_id);
        
        if (!$cliente) {
            return false;
        }
        
        // Si no se especifica user_id, usar el del cliente
        if (!$user_id && $cliente->user_id) {
            $user_id = $cliente->user_id;
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Actualizar datos de WooCommerce
        update_user_meta($user_id, 'billing_first_name', $cliente->razon_social);
        update_user_meta($user_id, 'billing_company', $cliente->razon_social);
        update_user_meta($user_id, 'billing_email', $cliente->email);
        update_user_meta($user_id, 'billing_phone', $cliente->telefono);
        update_user_meta($user_id, 'billing_address_1', $cliente->direccion_facturacion);
        update_user_meta($user_id, 'billing_city', $cliente->ciudad_facturacion);
        update_user_meta($user_id, 'billing_state', $cliente->region_facturacion);
        update_user_meta($user_id, 'billing_country', $cliente->pais_facturacion ?: 'CL');
        
        // Dirección de envío
        if (!$cliente->usar_direccion_facturacion_para_envio) {
            update_user_meta($user_id, 'shipping_address_1', $cliente->direccion_envio);
            update_user_meta($user_id, 'shipping_city', $cliente->ciudad_envio);
            update_user_meta($user_id, 'shipping_state', $cliente->region_envio);
            update_user_meta($user_id, 'shipping_country', $cliente->pais_envio ?: 'CL');
        }
        
        return true;
    }
}