<?php
/**
 * Clase para manejar las integraciones del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Integration {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Modulo_Ventas_DB();
        $this->logger = Modulo_Ventas_Logger::get_instance();
        
        // Hooks de WooCommerce
        $this->init_woocommerce_hooks();
        
        // Hooks de Gestión de Almacenes
        $this->init_almacenes_hooks();
        
        // Hooks de WordPress
        $this->init_wordpress_hooks();
        
        // Hooks de API REST
        $this->init_rest_api_hooks();
    }
    
    /**
     * Inicializar hooks de WooCommerce
     */
    private function init_woocommerce_hooks() {
        // Agregar campos a los pedidos
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'mostrar_datos_cotizacion_en_pedido'));
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'mostrar_selector_almacen_pedido'));
        
        // Agregar columna en listado de pedidos
        add_filter('manage_edit-shop_order_columns', array($this, 'agregar_columna_cotizacion'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'mostrar_columna_cotizacion'), 10, 2);
        
        // Metabox en productos para stock por almacén
        add_action('add_meta_boxes', array($this, 'agregar_metabox_stock_almacenes'));
        
        // Guardar datos adicionales en el pedido
        add_action('woocommerce_checkout_create_order', array($this, 'guardar_datos_cliente_pedido'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'guardar_almacen_item_pedido'), 10, 4);
        
        // Modificar stock según almacén
        add_filter('woocommerce_order_item_quantity', array($this, 'ajustar_cantidad_segun_almacen'), 10, 3);
        add_action('woocommerce_reduce_order_stock', array($this, 'reducir_stock_por_almacen'), 10, 1);
        add_action('woocommerce_restore_order_stock', array($this, 'restaurar_stock_por_almacen'), 10, 1);
        
        // Validaciones en checkout
        add_action('woocommerce_after_checkout_validation', array($this, 'validar_stock_almacen_checkout'), 10, 2);
        
        // Agregar datos de cliente a la orden
        add_filter('woocommerce_order_formatted_billing_address', array($this, 'agregar_rut_direccion_facturacion'), 10, 2);
        
        // Emails
        add_action('woocommerce_email_after_order_table', array($this, 'agregar_info_cotizacion_email'), 10, 4);
        
        // Reportes
        add_filter('woocommerce_admin_reports', array($this, 'agregar_reportes_personalizados'));
    }
    
    /**
     * Inicializar hooks de Gestión de Almacenes
     */
    private function init_almacenes_hooks() {
        // Hook cuando se actualiza stock en almacén
        add_action('gestion_almacenes_stock_actualizado', array($this, 'sincronizar_stock_con_woocommerce'), 10, 3);
        
        // Hook cuando se crea una transferencia
        add_action('gestion_almacenes_transferencia_completada', array($this, 'actualizar_cotizaciones_afectadas'), 10, 2);
        
        // Filtro para mostrar información en el plugin de almacenes
        add_filter('gestion_almacenes_info_producto', array($this, 'agregar_info_cotizaciones_producto'), 10, 2);
        
        // Agregar opción de almacén preferido en configuración
        add_action('gestion_almacenes_configuracion_adicional', array($this, 'agregar_configuracion_ventas'));
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_wordpress_hooks() {
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'agregar_widgets_dashboard'));
        
        // Barra de administración
        add_action('admin_bar_menu', array($this, 'agregar_menu_barra_admin'), 100);
        
        // Capacidades de usuario
        add_filter('user_has_cap', array($this, 'filtrar_capacidades_usuario'), 10, 3);
        
        // Scripts y estilos para integraciones
        add_action('admin_enqueue_scripts', array($this, 'cargar_assets_integracion'));
    }
    
    /**
     * Inicializar hooks de API REST
     */
    private function init_rest_api_hooks() {
        add_action('rest_api_init', array($this, 'registrar_rutas_api'));
    }
    
    /**
     * Mostrar datos de cotización en el pedido
     */
    public function mostrar_datos_cotizacion_en_pedido($order) {
        $cotizacion_id = $order->get_meta('_cotizacion_id');
        $cotizacion_folio = $order->get_meta('_cotizacion_folio');
        
        if ($cotizacion_id || $cotizacion_folio) {
            ?>
            <div class="cotizacion-info">
                <h3><?php _e('Información de Cotización', 'modulo-ventas'); ?></h3>
                <?php if ($cotizacion_folio) : ?>
                    <p>
                        <strong><?php _e('Folio:', 'modulo-ventas'); ?></strong> 
                        <?php echo esc_html($cotizacion_folio); ?>
                        <?php if ($cotizacion_id) : ?>
                            <a href="<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id); ?>" 
                               class="button button-small"
                               target="_blank">
                                <?php _e('Ver Cotización', 'modulo-ventas'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }
        
        // Mostrar RUT del cliente si existe
        $cliente_rut = $order->get_meta('_cliente_rut');
        if ($cliente_rut) {
            ?>
            <p>
                <strong><?php _e('RUT:', 'modulo-ventas'); ?></strong> 
                <?php echo esc_html($cliente_rut); ?>
            </p>
            <?php
        }
    }
    
    /**
     * Mostrar selector de almacén en pedido
     */
    public function mostrar_selector_almacen_pedido($order) {
        // Solo si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_DB')) {
            return;
        }
        
        global $gestion_almacenes_db;
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        
        if (empty($almacenes)) {
            return;
        }
        
        $almacen_pedido = $order->get_meta('_almacen_id');
        ?>
        <div class="almacen-pedido">
            <h4><?php _e('Almacén del Pedido', 'modulo-ventas'); ?></h4>
            <p class="form-field form-field-wide">
                <label for="almacen_pedido"><?php _e('Almacén:', 'modulo-ventas'); ?></label>
                <select id="almacen_pedido" name="almacen_pedido" class="wc-enhanced-select">
                    <option value=""><?php _e('— Seleccionar almacén —', 'modulo-ventas'); ?></option>
                    <?php foreach ($almacenes as $almacen) : ?>
                        <option value="<?php echo esc_attr($almacen->id); ?>" 
                                <?php selected($almacen_pedido, $almacen->id); ?>>
                            <?php echo esc_html($almacen->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <h4><?php _e('Stock por Almacén de los Productos', 'modulo-ventas'); ?></h4>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Producto', 'modulo-ventas'); ?></th>
                        <th><?php _e('Almacén Asignado', 'modulo-ventas'); ?></th>
                        <th><?php _e('Stock Disponible', 'modulo-ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item_id => $item) : ?>
                        <?php 
                        $product = $item->get_product();
                        $almacen_item = wc_get_order_item_meta($item_id, '_almacen_id', true);
                        $almacen_nombre = wc_get_order_item_meta($item_id, '_almacen_nombre', true);
                        ?>
                        <tr>
                            <td><?php echo esc_html($product->get_name()); ?></td>
                            <td>
                                <?php 
                                if ($almacen_nombre) {
                                    echo esc_html($almacen_nombre);
                                } elseif ($almacen_item) {
                                    $almacen_obj = $gestion_almacenes_db->get_warehouse($almacen_item);
                                    echo $almacen_obj ? esc_html($almacen_obj->name) : 'ID: ' . $almacen_item;
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($almacen_item) {
                                    $stock = $gestion_almacenes_db->get_product_warehouse_stock($product->get_id());
                                    echo isset($stock[$almacen_item]) ? $stock[$almacen_item] : 0;
                                } else {
                                    echo $product->get_stock_quantity();
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#almacen_pedido').on('change', function() {
                var almacen_id = $(this).val();
                var order_id = <?php echo $order->get_id(); ?>;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mv_actualizar_almacen_pedido',
                        order_id: order_id,
                        almacen_id: almacen_id,
                        nonce: '<?php echo wp_create_nonce('mv_actualizar_almacen_pedido'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Almacén actualizado', 'modulo-ventas'); ?>');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Agregar columna de cotización en listado de pedidos
     */
    public function agregar_columna_cotizacion($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            
            // Agregar después de la columna de estado
            if ($key === 'order_status') {
                $new_columns['cotizacion'] = __('Cotización', 'modulo-ventas');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Mostrar contenido de columna cotización
     */
    public function mostrar_columna_cotizacion($column, $post_id) {
        if ($column === 'cotizacion') {
            $order = wc_get_order($post_id);
            $cotizacion_folio = $order->get_meta('_cotizacion_folio');
            $cotizacion_id = $order->get_meta('_cotizacion_id');
            
            if ($cotizacion_folio) {
                if ($cotizacion_id) {
                    printf(
                        '<a href="%s" target="_blank">%s</a>',
                        admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id),
                        esc_html($cotizacion_folio)
                    );
                } else {
                    echo esc_html($cotizacion_folio);
                }
            } else {
                echo '—';
            }
        }
    }
    
    /**
     * Agregar metabox de stock por almacén en productos
     */
    public function agregar_metabox_stock_almacenes() {
        // Solo si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_DB')) {
            return;
        }
        
        add_meta_box(
            'mv_stock_almacenes_info',
            __('Stock por Almacén', 'modulo-ventas'),
            array($this, 'mostrar_metabox_stock_almacenes'),
            'product',
            'side',
            'default'
        );
    }
    
    /**
     * Mostrar contenido del metabox de stock
     */
    public function mostrar_metabox_stock_almacenes($post) {
        global $gestion_almacenes_db;
        
        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }
        
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        $stock_por_almacen = $gestion_almacenes_db->get_product_warehouse_stock($post->ID);
        
        ?>
        <div class="stock-almacenes-info">
            <?php if (!empty($almacenes)) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Almacén', 'modulo-ventas'); ?></th>
                            <th><?php _e('Stock', 'modulo-ventas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_stock = 0;
                        foreach ($almacenes as $almacen) : 
                            $stock = isset($stock_por_almacen[$almacen->id]) ? $stock_por_almacen[$almacen->id] : 0;
                            $total_stock += $stock;
                        ?>
                            <tr>
                                <td><?php echo esc_html($almacen->name); ?></td>
                                <td>
                                    <strong><?php echo $stock; ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th><?php _e('Total', 'modulo-ventas'); ?></th>
                            <th><strong><?php echo $total_stock; ?></strong></th>
                        </tr>
                    </tfoot>
                </table>
                
                <p class="description">
                    <?php _e('Stock gestionado por el plugin Gestión de Almacenes', 'modulo-ventas'); ?>
                </p>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=gab-stock-report&product_id=' . $post->ID); ?>" 
                       class="button button-small">
                        <?php _e('Ver movimientos', 'modulo-ventas'); ?>
                    </a>
                </p>
            <?php else : ?>
                <p><?php _e('No hay almacenes configurados.', 'modulo-ventas'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Guardar datos del cliente en el pedido
     */
    public function guardar_datos_cliente_pedido($order, $data) {
        // Si el usuario está logueado, buscar sus datos de cliente
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $clientes = new Modulo_Ventas_Clientes();
            $cliente = $clientes->obtener_cliente_por_usuario($user_id);
            
            if ($cliente) {
                $order->update_meta_data('_cliente_id', $cliente->id);
                $order->update_meta_data('_cliente_rut', $cliente->rut);
                $order->update_meta_data('_cliente_giro', $cliente->giro_comercial);
            }
        }
        
        // Si hay datos de RUT en el checkout (campo personalizado)
        if (isset($_POST['billing_rut'])) {
            $order->update_meta_data('_cliente_rut', sanitize_text_field($_POST['billing_rut']));
        }
        
        // Si hay almacén seleccionado en el checkout
        if (isset($_POST['almacen_preferido'])) {
            $order->update_meta_data('_almacen_id', intval($_POST['almacen_preferido']));
        }
    }
    
    /**
     * Guardar almacén en items del pedido
     */
    public function guardar_almacen_item_pedido($item, $cart_item_key, $values, $order) {
        // Si el item tiene almacén asignado desde el carrito
        if (isset($values['almacen_id'])) {
            $item->add_meta_data('_almacen_id', $values['almacen_id']);
            
            // Guardar también el nombre del almacén para referencia
            if (class_exists('Gestion_Almacenes_DB')) {
                global $gestion_almacenes_db;
                $almacen = $gestion_almacenes_db->get_warehouse($values['almacen_id']);
                if ($almacen) {
                    $item->add_meta_data('_almacen_nombre', $almacen->name);
                }
            }
        }
    }
    
    /**
     * Reducir stock por almacén cuando se procesa un pedido
     */
    public function reducir_stock_por_almacen($order) {
        // Solo si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_Sales_Stock_Manager')) {
            return;
        }
        
        global $gestion_almacenes_sales_manager;
        
        foreach ($order->get_items() as $item_id => $item) {
            $almacen_id = $item->get_meta('_almacen_id');
            
            if ($almacen_id) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                
                // Usar el ID correcto (variación si existe)
                $stock_id = $variation_id ?: $product_id;
                
                // Reducir stock en el almacén específico
                $gestion_almacenes_sales_manager->reducir_stock_almacen(
                    $stock_id,
                    $almacen_id,
                    $quantity,
                    $order->get_id()
                );
                
                $this->logger->log(
                    sprintf(
                        'Stock reducido: Producto %d, Almacén %d, Cantidad %d, Pedido #%d',
                        $stock_id,
                        $almacen_id,
                        $quantity,
                        $order->get_id()
                    ),
                    'info'
                );
            }
        }
    }
    
    /**
     * Restaurar stock por almacén cuando se cancela un pedido
     */
    public function restaurar_stock_por_almacen($order) {
        // Solo si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_Sales_Stock_Manager')) {
            return;
        }
        
        global $gestion_almacenes_sales_manager;
        
        foreach ($order->get_items() as $item_id => $item) {
            $almacen_id = $item->get_meta('_almacen_id');
            
            if ($almacen_id) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                
                // Usar el ID correcto
                $stock_id = $variation_id ?: $product_id;
                
                // Restaurar stock en el almacén específico
                $gestion_almacenes_sales_manager->restaurar_stock_almacen(
                    $stock_id,
                    $almacen_id,
                    $quantity,
                    $order->get_id()
                );
                
                $this->logger->log(
                    sprintf(
                        'Stock restaurado: Producto %d, Almacén %d, Cantidad %d, Pedido #%d',
                        $stock_id,
                        $almacen_id,
                        $quantity,
                        $order->get_id()
                    ),
                    'info'
                );
            }
        }
    }
    
    /**
     * Validar stock de almacén en checkout
     */
    public function validar_stock_almacen_checkout($data, $errors) {
        // Solo si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_DB')) {
            return;
        }
        
        global $gestion_almacenes_db;
        
        // Verificar cada item del carrito
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['almacen_id'])) {
                $product = $cart_item['data'];
                $almacen_id = $cart_item['almacen_id'];
                $quantity = $cart_item['quantity'];
                
                // Obtener stock del almacén
                $stock_almacen = $gestion_almacenes_db->get_product_warehouse_stock($product->get_id());
                $stock_disponible = isset($stock_almacen[$almacen_id]) ? $stock_almacen[$almacen_id] : 0;
                
                if ($stock_disponible < $quantity) {
                    $almacen = $gestion_almacenes_db->get_warehouse($almacen_id);
                    $almacen_nombre = $almacen ? $almacen->name : 'ID ' . $almacen_id;
                    
                    $errors->add(
                        'stock_insuficiente',
                        sprintf(
                            __('Stock insuficiente para %s en almacén %s. Disponible: %d', 'modulo-ventas'),
                            $product->get_name(),
                            $almacen_nombre,
                            $stock_disponible
                        )
                    );
                }
            }
        }
    }
    
    /**
     * Agregar RUT a la dirección de facturación
     */
    public function agregar_rut_direccion_facturacion($address, $order) {
        $rut = $order->get_meta('_cliente_rut');
        
        if ($rut) {
            $address['rut'] = $rut;
        }
        
        return $address;
    }
    
    /**
     * Agregar información de cotización en emails
     */
    public function agregar_info_cotizacion_email($order, $sent_to_admin, $plain_text, $email) {
        $cotizacion_folio = $order->get_meta('_cotizacion_folio');
        
        if ($cotizacion_folio) {
            if ($plain_text) {
                echo "\n" . __('Referencia de Cotización:', 'modulo-ventas') . ' ' . $cotizacion_folio . "\n";
            } else {
                echo '<p><strong>' . __('Referencia de Cotización:', 'modulo-ventas') . '</strong> ' . esc_html($cotizacion_folio) . '</p>';
            }
        }
    }
    
    /**
     * Agregar reportes personalizados a WooCommerce
     */
    public function agregar_reportes_personalizados($reports) {
        $reports['cotizaciones'] = array(
            'title' => __('Cotizaciones', 'modulo-ventas'),
            'reports' => array(
                'cotizaciones_por_estado' => array(
                    'title' => __('Cotizaciones por Estado', 'modulo-ventas'),
                    'description' => __('Análisis de cotizaciones agrupadas por estado', 'modulo-ventas'),
                    'hide_title' => false,
                    'callback' => array($this, 'reporte_cotizaciones_por_estado')
                ),
                'conversion_cotizaciones' => array(
                    'title' => __('Conversión de Cotizaciones', 'modulo-ventas'),
                    'description' => __('Tasa de conversión de cotizaciones a ventas', 'modulo-ventas'),
                    'hide_title' => false,
                    'callback' => array($this, 'reporte_conversion_cotizaciones')
                ),
                'cotizaciones_por_vendedor' => array(
                    'title' => __('Cotizaciones por Vendedor', 'modulo-ventas'),
                    'description' => __('Rendimiento de vendedores en cotizaciones', 'modulo-ventas'),
                    'hide_title' => false,
                    'callback' => array($this, 'reporte_cotizaciones_por_vendedor')
                )
            )
        );
        
        return $reports;
    }
    
    /**
     * Sincronizar stock con WooCommerce cuando se actualiza en almacenes
     */
    public function sincronizar_stock_con_woocommerce($product_id, $warehouse_id, $new_stock) {
        // Obtener producto
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        // Si está configurado para sincronizar
        if (get_option('modulo_ventas_sincronizar_stock', 'yes') === 'yes') {
            global $gestion_almacenes_db;
            
            // Obtener stock total de todos los almacenes
            $stock_por_almacen = $gestion_almacenes_db->get_product_warehouse_stock($product_id);
            $stock_total = array_sum($stock_por_almacen);
            
            // Actualizar stock en WooCommerce
            $product->set_stock_quantity($stock_total);
            $product->save();
            
            $this->logger->log(
                sprintf(
                    'Stock sincronizado con WooCommerce: Producto %d, Stock total: %d',
                    $product_id,
                    $stock_total
                ),
                'info'
            );
        }
    }
    
    /**
     * Actualizar cotizaciones cuando se completa una transferencia
     */
    public function actualizar_cotizaciones_afectadas($transfer_id, $transfer_data) {
        // Buscar cotizaciones pendientes que puedan verse afectadas
        $cotizaciones = $this->db->obtener_cotizaciones(array(
            'estado' => 'pendiente',
            'limit' => -1
        ));
        
        foreach ($cotizaciones as $cotizacion) {
            // Verificar si algún producto de la cotización está en la transferencia
            $items = $this->db->obtener_items_cotizacion($cotizacion->id);
            
            foreach ($items as $item) {
                // Si el producto está en la transferencia y el almacén destino coincide
                if ($this->producto_en_transferencia($item->producto_id, $transfer_data)) {
                    // Enviar notificación al vendedor
                    $this->notificar_stock_disponible($cotizacion, $item);
                }
            }
        }
    }
    
    /**
     * Agregar información de cotizaciones en vista de producto de almacenes
     */
    public function agregar_info_cotizaciones_producto($info, $product_id) {
        // Contar cotizaciones pendientes con este producto
        global $wpdb;
        $tabla_items = $this->db->get_tabla_cotizaciones_items();
        $tabla_cotizaciones = $this->db->get_tabla_cotizaciones();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT c.id) 
             FROM {$tabla_cotizaciones} c
             INNER JOIN {$tabla_items} ci ON c.id = ci.cotizacion_id
             WHERE ci.producto_id = %d AND c.estado = 'pendiente'",
            $product_id
        ));
        
        if ($count > 0) {
            $info['cotizaciones_pendientes'] = sprintf(
                __('%d cotizaciones pendientes incluyen este producto', 'modulo-ventas'),
                $count
            );
        }
        
        return $info;
    }
    
    /**
     * Agregar widgets al dashboard
     */
    public function agregar_widgets_dashboard() {
        wp_add_dashboard_widget(
            'mv_cotizaciones_resumen',
            __('Resumen de Cotizaciones', 'modulo-ventas'),
            array($this, 'widget_resumen_cotizaciones')
        );
        
        wp_add_dashboard_widget(
            'mv_cotizaciones_recientes',
            __('Cotizaciones Recientes', 'modulo-ventas'),
            array($this, 'widget_cotizaciones_recientes')
        );
    }
    
    /**
     * Widget de resumen de cotizaciones
     */
    public function widget_resumen_cotizaciones() {
        $estados = array('pendiente', 'aprobada', 'expirada', 'convertida');
        $resumen = array();
        
        foreach ($estados as $estado) {
            $resumen[$estado] = $this->db->contar_cotizaciones(array('estado' => $estado));
        }
        
        ?>
        <div class="mv-dashboard-widget">
            <ul>
                <li>
                    <span class="dashicons dashicons-clock"></span>
                    <?php printf(__('Pendientes: %d', 'modulo-ventas'), $resumen['pendiente']); ?>
                </li>
                <li>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php printf(__('Aprobadas: %d', 'modulo-ventas'), $resumen['aprobada']); ?>
                </li>
                <li>
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php printf(__('Expiradas: %d', 'modulo-ventas'), $resumen['expirada']); ?>
                </li>
                <li>
                    <span class="dashicons dashicons-cart"></span>
                    <?php printf(__('Convertidas: %d', 'modulo-ventas'), $resumen['convertida']); ?>
                </li>
            </ul>
            
            <p class="sub">
                <a href="<?php echo admin_url('admin.php?page=modulo-ventas-cotizaciones'); ?>">
                    <?php _e('Ver todas las cotizaciones', 'modulo-ventas'); ?> →
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Widget de cotizaciones recientes
     */
    public function widget_cotizaciones_recientes() {
        $cotizaciones = $this->db->obtener_cotizaciones(array(
            'limit' => 5,
            'orderby' => 'fecha',
            'order' => 'DESC'
        ));
        
        if (empty($cotizaciones)) {
            echo '<p>' . __('No hay cotizaciones recientes.', 'modulo-ventas') . '</p>';
            return;
        }
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Folio', 'modulo-ventas'); ?></th>
                    <th><?php _e('Cliente', 'modulo-ventas'); ?></th>
                    <th><?php _e('Total', 'modulo-ventas'); ?></th>
                    <th><?php _e('Estado', 'modulo-ventas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cotizaciones as $cotizacion) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion->id); ?>">
                                <?php echo esc_html($cotizacion->folio); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($cotizacion->razon_social); ?></td>
                        <td><?php echo wc_price($cotizacion->total); ?></td>
                        <td>
                            <?php 
                            $cotizaciones_obj = new Modulo_Ventas_Cotizaciones();
                            $estados = $cotizaciones_obj->obtener_estados();
                            echo esc_html($estados[$cotizacion->estado]);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p class="sub">
            <a href="<?php echo admin_url('admin.php?page=modulo-ventas-nueva-cotizacion'); ?>" class="button button-primary">
                <?php _e('Nueva Cotización', 'modulo-ventas'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Agregar menú en la barra de administración
     */
    public function agregar_menu_barra_admin($wp_admin_bar) {
        if (!current_user_can('view_cotizaciones')) {
            return;
        }
        
        // Menú principal
        $wp_admin_bar->add_node(array(
            'id' => 'modulo-ventas',
            'title' => '<span class="ab-icon dashicons dashicons-cart"></span>' . __('Ventas', 'modulo-ventas'),
            'href' => admin_url('admin.php?page=modulo-ventas-cotizaciones'),
            'meta' => array(
                'title' => __('Módulo de Ventas', 'modulo-ventas')
            )
        ));
        
        // Submenús
        $wp_admin_bar->add_node(array(
            'id' => 'mv-nueva-cotizacion',
            'parent' => 'modulo-ventas',
            'title' => __('Nueva Cotización', 'modulo-ventas'),
            'href' => admin_url('admin.php?page=modulo-ventas-nueva-cotizacion')
        ));
        
        $wp_admin_bar->add_node(array(
            'id' => 'mv-cotizaciones',
            'parent' => 'modulo-ventas',
            'title' => __('Ver Cotizaciones', 'modulo-ventas'),
            'href' => admin_url('admin.php?page=modulo-ventas-cotizaciones')
        ));
        
        $wp_admin_bar->add_node(array(
            'id' => 'mv-clientes',
            'parent' => 'modulo-ventas',
            'title' => __('Clientes', 'modulo-ventas'),
            'href' => admin_url('users.php')
        ));
    }
    
    /**
     * Cargar assets para integraciones
     */
    public function cargar_assets_integracion($hook) {
        // En páginas de WooCommerce
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'shop_order') {
                wp_enqueue_style(
                    'modulo-ventas-integration',
                    MODULO_VENTAS_PLUGIN_URL . 'assets/css/integration.css',
                    array(),
                    MODULO_VENTAS_VERSION
                );
                
                wp_enqueue_script(
                    'modulo-ventas-integration',
                    MODULO_VENTAS_PLUGIN_URL . 'assets/js/integration.js',
                    array('jquery'),
                    MODULO_VENTAS_VERSION,
                    true
                );
            }
        }
    }
    
    /**
     * Registrar rutas de API REST
     */
    public function registrar_rutas_api() {
        register_rest_route('modulo-ventas/v1', '/cotizaciones', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_obtener_cotizaciones'),
            'permission_callback' => array($this, 'permisos_api_lectura')
        ));
        
        register_rest_route('modulo-ventas/v1', '/cotizaciones/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_obtener_cotizacion'),
            'permission_callback' => array($this, 'permisos_api_lectura')
        ));
        
        register_rest_route('modulo-ventas/v1', '/cotizaciones', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_crear_cotizacion'),
            'permission_callback' => array($this, 'permisos_api_escritura')
        ));
        
        register_rest_route('modulo-ventas/v1', '/clientes', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_obtener_clientes'),
            'permission_callback' => array($this, 'permisos_api_lectura')
        ));
        
        register_rest_route('modulo-ventas/v1', '/stock/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_obtener_stock_producto'),
            'permission_callback' => array($this, 'permisos_api_lectura')
        ));
    }
    
    /**
     * API: Obtener cotizaciones
     */
    public function api_obtener_cotizaciones($request) {
        $params = $request->get_params();
        
        $args = array(
            'limit' => isset($params['per_page']) ? intval($params['per_page']) : 20,
            'offset' => isset($params['page']) ? (intval($params['page']) - 1) * intval($params['per_page']) : 0,
            'estado' => isset($params['estado']) ? sanitize_text_field($params['estado']) : '',
            'cliente_id' => isset($params['cliente_id']) ? intval($params['cliente_id']) : 0,
            'fecha_desde' => isset($params['fecha_desde']) ? sanitize_text_field($params['fecha_desde']) : '',
            'fecha_hasta' => isset($params['fecha_hasta']) ? sanitize_text_field($params['fecha_hasta']) : ''
        );
        
        $cotizaciones = $this->db->obtener_cotizaciones($args);
        $total = $this->db->contar_cotizaciones($args);
        
        return new WP_REST_Response(array(
            'cotizaciones' => $cotizaciones,
            'total' => $total,
            'pages' => ceil($total / $args['limit'])
        ), 200);
    }
    
    /**
     * API: Obtener una cotización
     */
    public function api_obtener_cotizacion($request) {
        $cotizacion_id = $request['id'];
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            return new WP_Error('cotizacion_no_encontrada', __('Cotización no encontrada', 'modulo-ventas'), array('status' => 404));
        }
        
        return new WP_REST_Response($cotizacion, 200);
    }
    
    /**
     * API: Crear cotización
     */
    public function api_crear_cotizacion($request) {
        $datos = $request->get_json_params();
        
        if (!isset($datos['datos_generales']) || !isset($datos['items'])) {
            return new WP_Error('datos_incompletos', __('Datos incompletos', 'modulo-ventas'), array('status' => 400));
        }
        
        $cotizaciones = new Modulo_Ventas_Cotizaciones();
        $cotizacion_id = $cotizaciones->crear_cotizacion($datos['datos_generales'], $datos['items']);
        
        if (is_wp_error($cotizacion_id)) {
            return $cotizacion_id;
        }
        
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        
        return new WP_REST_Response($cotizacion, 201);
    }
    
    /**
     * API: Obtener clientes
     */
    public function api_obtener_clientes($request) {
        $params = $request->get_params();
        
        $args = array(
            'limit' => isset($params['per_page']) ? intval($params['per_page']) : 20,
            'offset' => isset($params['page']) ? (intval($params['page']) - 1) * intval($params['per_page']) : 0
        );
        
        $termino = isset($params['search']) ? sanitize_text_field($params['search']) : '';
        $clientes = $this->db->buscar_clientes($termino, $args);
        
        return new WP_REST_Response($clientes, 200);
    }
    
    /**
     * API: Obtener stock de producto
     */
    public function api_obtener_stock_producto($request) {
        $product_id = $request['id'];
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('producto_no_encontrado', __('Producto no encontrado', 'modulo-ventas'), array('status' => 404));
        }
        
        $response = array(
            'producto_id' => $product_id,
            'nombre' => $product->get_name(),
            'sku' => $product->get_sku(),
            'stock_general' => $product->get_stock_quantity(),
            'stock_por_almacen' => array()
        );
        
        // Stock por almacén si está disponible
        if (class_exists('Gestion_Almacenes_DB')) {
            global $gestion_almacenes_db;
            $stock_almacenes = $gestion_almacenes_db->get_product_warehouse_stock($product_id);
            
            foreach ($stock_almacenes as $almacen_id => $stock) {
                $almacen = $gestion_almacenes_db->get_warehouse($almacen_id);
                $response['stock_por_almacen'][] = array(
                    'almacen_id' => $almacen_id,
                    'almacen_nombre' => $almacen ? $almacen->name : 'ID ' . $almacen_id,
                    'stock' => $stock
                );
            }
        }
        
        return new WP_REST_Response($response, 200);
    }
    
    /**
     * Permisos API lectura
     */
    public function permisos_api_lectura() {
        return current_user_can('view_cotizaciones') || current_user_can('manage_woocommerce');
    }
    
    /**
     * Permisos API escritura
     */
    public function permisos_api_escritura() {
        return current_user_can('create_cotizaciones') || current_user_can('manage_woocommerce');
    }
    
    /**
     * Verificar si un producto está en una transferencia
     */
    private function producto_en_transferencia($product_id, $transfer_data) {
        // Implementar lógica según estructura de transferencias
        return false;
    }
    
    /**
     * Notificar disponibilidad de stock
     */
    private function notificar_stock_disponible($cotizacion, $item) {
        // Implementar notificación por email o sistema interno
        $this->logger->log(
            sprintf(
                'Stock disponible para producto %d en cotización %s',
                $item->producto_id,
                $cotizacion->folio
            ),
            'info'
        );
    }
}