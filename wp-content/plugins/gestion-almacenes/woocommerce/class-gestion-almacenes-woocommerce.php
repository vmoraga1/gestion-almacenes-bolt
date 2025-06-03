<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_WooCommerce {

    public function __construct() {
        // Hooks existentes
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'agregar_campos_almacen_producto'));
        add_action('woocommerce_process_product_meta', array($this, 'guardar_campos_almacen_producto'));
        
        // Nuevos hooks para integración completa
        add_filter('woocommerce_product_get_stock_quantity', array($this, 'calcular_stock_total'), 10, 2);
        add_filter('woocommerce_product_variation_get_stock_quantity', array($this, 'calcular_stock_total'), 10, 2);
        add_action('woocommerce_thankyou', array($this, 'reducir_stock_almacen_tras_compra'));
        add_action('woocommerce_order_status_cancelled', array($this, 'restaurar_stock_almacen'));
        add_action('woocommerce_order_status_refunded', array($this, 'restaurar_stock_almacen'));
        
        // Hook para agregar selector de almacén en el checkout
        add_action('woocommerce_cart_item_name', array($this, 'mostrar_almacen_en_carrito'), 10, 3);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'agregar_selector_almacen_producto'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'agregar_almacen_datos_carrito'), 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'guardar_almacen_en_pedido'), 10, 4);
        
        // Hook para validar stock disponible antes de agregar al carrito
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validar_stock_almacen'), 10, 5);
    }

    // Función existente - mantener
    public function agregar_campos_almacen_producto() {
        // Si el control de stock está activado, no mostrar esta tabla
        if (get_option('gab_manage_wc_stock', 'yes') === 'yes') {
            return; // No mostrar
        }

        global $post;
        global $gestion_almacenes_db;

        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        $current_stock_data = $gestion_almacenes_db->get_product_warehouse_stock($post->ID);

        echo '<div class="options_group show_if_simple">';
        echo '<h4>' . esc_html(__('Stock por Almacén', 'gestion-almacenes')) . '</h4>';
        echo '<p class="form-field">'. esc_html(__('Ingresa la cantidad de stock para cada almacén.', 'gestion-almacenes')) . '</p>';

        if ($almacenes) {
            foreach ($almacenes as $almacen) {
                $warehouse_id = $almacen->id;
                $warehouse_name = esc_html($almacen->name);
                $current_stock = isset($current_stock_data[$warehouse_id]) ? $current_stock_data[$warehouse_id] : 0;

                woocommerce_wp_text_input([
                    'id' => '_warehouse_stock_' . $warehouse_id,
                    'label' => sprintf(esc_html__('Stock en %s', 'gestion-almacenes'), $warehouse_name),
                    'placeholder' => esc_html__('Cantidad de stock', 'gestion-almacenes'),
                    'description' => sprintf(esc_html__('Stock disponible en el almacén: %s.', 'gestion-almacenes'), $warehouse_name),
                    'desc_tip' => true,
                    'type' => 'number',
                    'custom_attributes' => [
                        'step' => '1',
                        'min' => '0',
                    ],
                    'value' => $current_stock,
                ]);
            }
        } else {
            echo '<p>' . esc_html(__('No hay almacenes registrados. Por favor, agrega almacenes primero.', 'gestion-almacenes')) . '</p>';
        }

        echo '</div>';
    }

    // Guardar campos almacén
    public function guardar_campos_almacen_producto($post_id) {
        global $gestion_almacenes_db;

        delete_post_meta($post_id, '_warehouse_stock');

        foreach ($_POST as $key => $value) {
            if (strpos($key, '_warehouse_stock_') === 0) {
                $warehouse_id = str_replace('_warehouse_stock_', '', $key);

                if (is_numeric($warehouse_id)) {
                    $warehouse_id = intval($warehouse_id);
                    $stock = sanitize_text_field($value);
                    $gestion_almacenes_db->save_product_warehouse_stock($post_id, $warehouse_id, $stock);
                }
            }
        }
    }

    /**
     * Calcular el stock total sumando el stock de todos los almacenes
     */
    public function calcular_stock_total($stock_quantity, $product) {
        global $gestion_almacenes_db;
        
        $product_id = $product->get_id();
        $warehouse_stock = $gestion_almacenes_db->get_product_warehouse_stock($product_id);
        
        if (!empty($warehouse_stock)) {
            $total_stock = array_sum($warehouse_stock);
            return $total_stock;
        }
        
        return $stock_quantity; // Retorna el stock original si no hay datos de almacén
    }

    /**
     * Agregar selector de almacén en la página del producto
     */
    public function agregar_selector_almacen_producto() {
        global $product;
        global $gestion_almacenes_db;

        if (!$product->is_type('simple')) {
            return; // Solo para productos simples por now
        }

        $product_id = $product->get_id();
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        $warehouse_stock = $gestion_almacenes_db->get_product_warehouse_stock($product_id);

        if (!$almacenes) {
            return;
        }

        // Configuración
        $default_warehouse = get_option('gab_default_warehouse', '');
        $auto_select_warehouse = get_option('gab_auto_select_warehouse', 0);
        
        // Si está activada la selección automática, encontrar el almacén con más stock
        $selected_warehouse = '';
        if ($auto_select_warehouse && $warehouse_stock) {
            $max_stock = 0;
            foreach ($warehouse_stock as $wh_id => $stock) {
                if ($stock > $max_stock) {
                    $max_stock = $stock;
                    $selected_warehouse = $wh_id;
                }
            }
        } elseif ($default_warehouse) {
            $selected_warehouse = $default_warehouse;
        }

        echo '<div class="gab-warehouse-selector">';
        echo '<label for="selected_warehouse">' . esc_html__('Seleccionar Almacén:', 'gestion-almacenes') . '</label>';
        echo '<select name="selected_warehouse" id="selected_warehouse" required>';
        echo '<option value="">' . esc_html__('Selecciona un almacén', 'gestion-almacenes') . '</option>';

        foreach ($almacenes as $almacen) {
            $warehouse_id = $almacen->id;
            $warehouse_name = esc_html($almacen->name);
            $available_stock = isset($warehouse_stock[$warehouse_id]) ? $warehouse_stock[$warehouse_id] : 0;
            
            if ($available_stock > 0) {
                $is_selected = ($selected_warehouse == $warehouse_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($warehouse_id) . '" ' . $is_selected . '>';
                echo $warehouse_name . ' (' . sprintf(esc_html__('%d disponibles', 'gestion-almacenes'), $available_stock) . ')';
                echo '</option>';
            } else {
                // Mostrar almacenes sin stock pero deshabilitados
                echo '<option value="' . esc_attr($warehouse_id) . '" disabled>';
                echo $warehouse_name . ' (' . esc_html__('Sin stock', 'gestion-almacenes') . ')';
                echo '</option>';
            }
        }

        echo '</select>';
        
        // Mostrar información adicional del almacén seleccionado
        echo '<div id="warehouse-info" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; display: none;">';
        echo '<div id="warehouse-details"></div>';
        echo '</div>';
        
        echo '</div>';

        // JavaScript mejorado para validación en tiempo real
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var warehouseStock = <?php echo json_encode($warehouse_stock); ?>;
            var warehouseDetails = <?php echo json_encode($almacenes); ?>;
            
            function updateWarehouseInfo() {
                var warehouseId = $('#selected_warehouse').val();
                
                if (warehouseId && warehouseStock[warehouseId]) {
                    var maxStock = warehouseStock[warehouseId];
                    var warehouse = warehouseDetails.find(w => w.id == warehouseId);
                    
                    // Actualizar límite de cantidad
                    $('input[name="quantity"]').attr('max', maxStock);
                    
                    // Mostrar información del almacén
                    if (warehouse) {
                        var info = '<strong>' + warehouse.name + '</strong><br>';
                        info += '<?php esc_html_e('Stock disponible:', 'gestion-almacenes'); ?> <span style="color: #00a32a; font-weight: bold;">' + maxStock + '</span><br>';
                        info += '<?php esc_html_e('Ubicación:', 'gestion-almacenes'); ?> ' + warehouse.ciudad + ', ' + warehouse.region;
                        
                        $('#warehouse-details').html(info);
                        $('#warehouse-info').show();
                    }
                    
                    // Actualizar mensaje de stock en WooCommerce
                    $('.stock').html('<?php esc_html_e('Stock disponible en este almacén:', 'gestion-almacenes'); ?> <span style="color: #00a32a;">' + maxStock + '</span>');
                } else {
                    $('#warehouse-info').hide();
                    $('input[name="quantity"]').removeAttr('max');
                    $('.stock').text('<?php esc_html_e('Selecciona un almacén para ver el stock disponible', 'gestion-almacenes'); ?>');
                }
            }
            
            $('#selected_warehouse').on('change', updateWarehouseInfo);
            
            // Ejecutar al cargar si hay un almacén preseleccionado
            if ($('#selected_warehouse').val()) {
                updateWarehouseInfo();
            }
            
            // Validar antes de agregar al carrito
            $('form.cart').on('submit', function(e) {
                if (!$('#selected_warehouse').val()) {
                    e.preventDefault();
                    alert('<?php esc_html_e('Por favor selecciona un almacén antes de agregar al carrito.', 'gestion-almacenes'); ?>');
                    $('#selected_warehouse').focus();
                    return false;
                }
                
                var quantity = parseInt($('input[name="quantity"]').val());
                var maxStock = parseInt($('input[name="quantity"]').attr('max'));
                
                if (quantity > maxStock) {
                    e.preventDefault();
                    alert('<?php esc_html_e('La cantidad seleccionada excede el stock disponible.', 'gestion-almacenes'); ?>');
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Agregar datos del almacén seleccionado al carrito
     */
    public function agregar_almacen_datos_carrito($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['selected_warehouse']) && !empty($_POST['selected_warehouse'])) {
            $cart_item_data['selected_warehouse'] = sanitize_text_field($_POST['selected_warehouse']);
            
            // Hacer único el item del carrito para diferentes almacenes
            $cart_item_data['unique_key'] = md5(microtime().rand());
        }
        
        return $cart_item_data;
    }

    /**
     * Mostrar el almacén seleccionado en el carrito
     */
    public function mostrar_almacen_en_carrito($name, $cart_item, $cart_item_key) {
        if (isset($cart_item['selected_warehouse'])) {
            global $gestion_almacenes_db;
            $almacenes = $gestion_almacenes_db->obtener_almacenes();
            
            foreach ($almacenes as $almacen) {
                if ($almacen->id == $cart_item['selected_warehouse']) {
                    $name .= '<br><small>' . sprintf(esc_html__('Almacén: %s', 'gestion-almacenes'), esc_html($almacen->name)) . '</small>';
                    break;
                }
            }
        }
        
        return $name;
    }

    /**
     * Validar stock disponible en el almacén antes de agregar al carrito
     */
    public function validar_stock_almacen($passed, $product_id, $quantity, $variation_id = '', $variations = array()) {
        if (!isset($_POST['selected_warehouse']) || empty($_POST['selected_warehouse'])) {
            wc_add_notice(esc_html__('Por favor selecciona un almacén.', 'gestion-almacenes'), 'error');
            return false;
        }

        global $gestion_almacenes_db;
        $warehouse_id = intval($_POST['selected_warehouse']);
        $warehouse_stock = $gestion_almacenes_db->get_product_warehouse_stock($product_id);
        
        $available_stock = isset($warehouse_stock[$warehouse_id]) ? $warehouse_stock[$warehouse_id] : 0;
        
        if ($quantity > $available_stock) {
            wc_add_notice(sprintf(
                esc_html__('Solo hay %d unidades disponibles en el almacén seleccionado.', 'gestion-almacenes'), 
                $available_stock
            ), 'error');
            return false;
        }
        
        return $passed;
    }

    /**
     * Guardar información del almacén en el pedido
     */
    public function guardar_almacen_en_pedido($item, $cart_item_key, $values, $order) {
        if (isset($values['selected_warehouse'])) {
            $item->add_meta_data('_selected_warehouse', $values['selected_warehouse'], true);
        }
    }

    /**
     * Reducir stock del almacén específico tras una compra exitosa
     */
    public function reducir_stock_almacen_tras_compra($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        global $gestion_almacenes_db;

        foreach ($order->get_items() as $item_id => $item) {
            $warehouse_id = $item->get_meta('_selected_warehouse');
            
            if ($warehouse_id) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                
                // Obtener stock actual
                $current_stock_data = $gestion_almacenes_db->get_product_warehouse_stock($product_id);
                $current_stock = isset($current_stock_data[$warehouse_id]) ? $current_stock_data[$warehouse_id] : 0;
                
                // Reducir stock
                $new_stock = max(0, $current_stock - $quantity);
                $gestion_almacenes_db->save_product_warehouse_stock($product_id, $warehouse_id, $new_stock);
                
                error_log('[DEBUG GESTION ALMACENES] Stock reducido - Producto: ' . $product_id . ', Almacén: ' . $warehouse_id . ', Cantidad: ' . $quantity . ', Stock restante: ' . $new_stock);
            }
        }
    }

    /**
     * Restaurar stock del almacén cuando se cancela o reembolsa un pedido
     */
    public function restaurar_stock_almacen($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        global $gestion_almacenes_db;

        foreach ($order->get_items() as $item_id => $item) {
            $warehouse_id = $item->get_meta('_selected_warehouse');
            
            if ($warehouse_id) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                
                // Obtener stock actual
                $current_stock_data = $gestion_almacenes_db->get_product_warehouse_stock($product_id);
                $current_stock = isset($current_stock_data[$warehouse_id]) ? $current_stock_data[$warehouse_id] : 0;
                
                // Restaurar stock
                $new_stock = $current_stock + $quantity;
                $gestion_almacenes_db->save_product_warehouse_stock($product_id, $warehouse_id, $new_stock);
                
                error_log('[DEBUG GESTION ALMACENES] Stock restaurado - Producto: ' . $product_id . ', Almacén: ' . $warehouse_id . ', Cantidad: ' . $quantity . ', Stock actual: ' . $new_stock);
            }
        }
    }
}