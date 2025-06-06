<?php
/**
 * Gestor de stock para ventas - VERSIÓN CORREGIDA
 */
class Gestion_Almacenes_Sales_Stock_Manager {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        
        // Solo activar si el control de stock está habilitado
        if (get_option('gab_manage_wc_stock', 'yes') === 'yes') {
            $this->init_hooks();
        }
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // PRIORIDAD ALTA para sobrescribir la validación de WooCommerce
        add_filter('woocommerce_product_get_stock_quantity', [$this, 'override_stock_for_validation'], 1, 2);
        add_filter('woocommerce_product_variation_get_stock_quantity', [$this, 'override_stock_for_validation'], 1, 2);
        
        // Hook para validación personalizada de stock
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_warehouse_stock'], 20, 5);
        add_filter('woocommerce_update_cart_validation', [$this, 'validate_warehouse_stock_on_update'], 20, 4);
        
        // Hook cuando se reduce el stock de un pedido
        add_action('woocommerce_reduce_order_stock', [$this, 'handle_order_stock_reduction'], 10, 1);
        
        // Prevenir que WooCommerce reduzca el stock directamente
        add_filter('woocommerce_can_reduce_order_stock', [$this, 'prevent_default_stock_reduction'], 10, 2);
        
        // Mostrar información del almacén en el pedido
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'show_warehouse_info_in_order']);
        
        // Agregar meta box en la página del pedido
        add_action('add_meta_boxes', [$this, 'add_warehouse_meta_box']);
        
        // Hook cuando se cancela o reembolsa un pedido
        add_action('woocommerce_order_status_cancelled', [$this, 'restore_stock_on_cancel']);
        add_action('woocommerce_order_status_refunded', [$this, 'restore_stock_on_cancel']);
        
        // Hook para manejar el stock en el checkout
        add_action('woocommerce_check_cart_items', [$this, 'check_cart_items_stock']);
    }
    
    /**
     * Sobrescribir el stock para la validación de WooCommerce
     */
    public function override_stock_for_validation($stock, $product) {
        // Si el producto no maneja stock, devolver el valor original
        if (!$product || !$product->get_manage_stock()) {
            return $stock;
        }
        
        // Obtener el stock total de todos los almacenes
        $total_stock = 0;
        $warehouses = $this->db->get_warehouses();
        
        foreach ($warehouses as $warehouse) {
            $warehouse_stock = $this->db->get_warehouse_stock($warehouse->id, $product->get_id());
            $total_stock += $warehouse_stock;
        }
        
        return $total_stock;
    }
    
    /**
     * Validar stock del almacén al agregar al carrito
     */
    public function validate_warehouse_stock($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        // Si ya falló otra validación, no continuar
        if (!$passed) {
            return $passed;
        }
        
        $actual_product_id = $variation_id ? $variation_id : $product_id;
        $product = wc_get_product($actual_product_id);
        
        if (!$product || !$product->get_manage_stock()) {
            return $passed;
        }
        
        // Calcular stock total disponible en todos los almacenes
        $total_available = 0;
        $warehouses = $this->db->get_warehouses();
        
        foreach ($warehouses as $warehouse) {
            $stock = $this->db->get_warehouse_stock($warehouse->id, $actual_product_id);
            $total_available += $stock;
        }
        
        // Verificar cantidad ya en el carrito
        $cart_qty = $this->get_product_quantity_in_cart($actual_product_id);
        $total_requested = $cart_qty + $quantity;
        
        if ($total_requested > $total_available) {
            wc_add_notice(
                sprintf(
                    __('Solo hay %d unidades disponibles de "%s" en todos los almacenes.', 'gestion-almacenes'),
                    $total_available,
                    $product->get_name()
                ),
                'error'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar stock al actualizar el carrito
     */
    public function validate_warehouse_stock_on_update($passed, $cart_item_key, $values, $quantity) {
        if (!$passed) {
            return $passed;
        }
        
        $product_id = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->get_manage_stock()) {
            return $passed;
        }
        
        // Calcular stock total disponible
        $total_available = 0;
        $warehouses = $this->db->get_warehouses();
        
        foreach ($warehouses as $warehouse) {
            $stock = $this->db->get_warehouse_stock($warehouse->id, $product_id);
            $total_available += $stock;
        }
        
        if ($quantity > $total_available) {
            wc_add_notice(
                sprintf(
                    __('Solo hay %d unidades disponibles de "%s".', 'gestion-almacenes'),
                    $total_available,
                    $product->get_name()
                ),
                'error'
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar stock de todos los items del carrito
     */
    public function check_cart_items_stock() {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $stock_issues = array();
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
            $product = $cart_item['data'];
            
            if (!$product || !$product->get_manage_stock()) {
                continue;
            }
            
            $quantity_in_cart = $cart_item['quantity'];
            
            // Calcular stock total disponible
            $total_available = 0;
            $warehouses = $this->db->get_warehouses();
            
            foreach ($warehouses as $warehouse) {
                $stock = $this->db->get_warehouse_stock($warehouse->id, $product_id);
                $total_available += $stock;
            }
            
            if ($quantity_in_cart > $total_available) {
                $stock_issues[] = sprintf(
                    __('%s solo tiene %d unidades disponibles.', 'gestion-almacenes'),
                    $product->get_name(),
                    $total_available
                );
                
                // Ajustar cantidad en el carrito al máximo disponible
                $cart->set_quantity($cart_item_key, $total_available);
            }
        }
        
        // Mostrar errores si hay problemas de stock
        if (!empty($stock_issues)) {
            wc_add_notice(
                __('Se han ajustado las cantidades de algunos productos debido a la disponibilidad:', 'gestion-almacenes') . 
                '<br>' . implode('<br>', $stock_issues),
                'notice'
            );
        }
    }
    
    /**
     * Obtener cantidad de un producto en el carrito
     */
    private function get_product_quantity_in_cart($product_id) {
        $quantity = 0;
        
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $item_product_id = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
                if ($item_product_id == $product_id) {
                    $quantity += $cart_item['quantity'];
                }
            }
        }
        
        return $quantity;
    }
    
    /**
     * Manejar la reducción de stock cuando se crea un pedido
     */
    public function handle_order_stock_reduction($order) {
        // Verificar si ya se procesó este pedido
        if (get_post_meta($order->get_id(), '_gab_stock_reduced', true) === 'yes') {
            return;
        }
        
        $warehouse_allocations = array();
        $errors = array();
        
        // Procesar cada item del pedido
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product || !$product->get_manage_stock()) {
                continue;
            }
            
            $product_id = $product->get_id();
            $quantity_needed = $item->get_quantity();
            
            // Determinar de qué almacén(es) tomar el stock
            $allocation = $this->allocate_stock_from_warehouses($product_id, $quantity_needed);
            
            if ($allocation['success']) {
                $warehouse_allocations[$item->get_id()] = $allocation['allocations'];
                
                // Reducir stock de cada almacén
                foreach ($allocation['allocations'] as $warehouse_id => $qty) {
                    $this->reduce_warehouse_stock($warehouse_id, $product_id, $qty);
                }
            } else {
                $errors[] = sprintf(
                    __('No hay suficiente stock para %s. Necesario: %d, Disponible: %d', 'gestion-almacenes'),
                    $product->get_name(),
                    $quantity_needed,
                    $allocation['available']
                );
            }
        }
        
        // Si hay errores, revertir cambios
        if (!empty($errors)) {
            // Restaurar stock
            foreach ($warehouse_allocations as $item_id => $allocations) {
                foreach ($allocations as $warehouse_id => $qty) {
                    $item = $order->get_item($item_id);
                    $product_id = $item->get_product()->get_id();
                    $this->restore_warehouse_stock($warehouse_id, $product_id, $qty);
                }
            }
            
            // Agregar nota al pedido
            $order->add_order_note(
                __('Error al reducir stock:', 'gestion-almacenes') . "\n" . 
                implode("\n", $errors)
            );
            
            throw new Exception(implode("\n", $errors));
        }
        
        // Guardar las asignaciones en el pedido
        update_post_meta($order->get_id(), '_gab_warehouse_allocations', $warehouse_allocations);
        update_post_meta($order->get_id(), '_gab_stock_reduced', 'yes');
        
        // Agregar nota al pedido
        $this->add_stock_reduction_note($order, $warehouse_allocations);
    }
    
    /**
     * Asignar stock desde los almacenes disponibles
     */
    private function allocate_stock_from_warehouses($product_id, $quantity_needed) {
        $warehouses = $this->db->get_warehouses();
        $allocations = array();
        $total_available = 0;
        $remaining_qty = $quantity_needed;
        
        // Obtener configuración de prioridad
        $allocation_method = get_option('gab_stock_allocation_method', 'priority');
        $default_warehouse = get_option('gab_default_sales_warehouse', '');
        
        // Si hay un almacén predeterminado, intentar primero desde ahí
        if ($default_warehouse && $remaining_qty > 0) {
            $stock = $this->db->get_warehouse_stock($default_warehouse, $product_id);
            $total_available += $stock;
            
            if ($stock > 0) {
                $to_allocate = min($stock, $remaining_qty);
                $allocations[$default_warehouse] = $to_allocate;
                $remaining_qty -= $to_allocate;
            }
        }
        
        // Si todavía necesitamos más stock, buscar en otros almacenes
        if ($remaining_qty > 0) {
            // Ordenar almacenes según el método de asignación
            if ($allocation_method === 'balanced') {
                // Distribuir equitativamente
                usort($warehouses, function($a, $b) use ($product_id) {
                    $stock_a = $this->db->get_warehouse_stock($a->id, $product_id);
                    $stock_b = $this->db->get_warehouse_stock($b->id, $product_id);
                    return $stock_b - $stock_a; // Mayor stock primero
                });
            } else {
                // Por prioridad (orden de ID o configuración específica)
                // Aquí podrías implementar un campo de prioridad en los almacenes
            }
            
            foreach ($warehouses as $warehouse) {
                if ($warehouse->id == $default_warehouse) {
                    continue; // Ya lo procesamos
                }
                
                $stock = $this->db->get_warehouse_stock($warehouse->id, $product_id);
                $total_available += $stock;
                
                if ($stock > 0 && $remaining_qty > 0) {
                    $to_allocate = min($stock, $remaining_qty);
                    $allocations[$warehouse->id] = $to_allocate;
                    $remaining_qty -= $to_allocate;
                }
                
                if ($remaining_qty == 0) {
                    break;
                }
            }
        } else {
            // Calcular stock total disponible para el mensaje de error
            foreach ($warehouses as $warehouse) {
                if ($warehouse->id != $default_warehouse) {
                    $total_available += $this->db->get_warehouse_stock($warehouse->id, $product_id);
                }
            }
        }
        
        return array(
            'success' => $remaining_qty == 0,
            'allocations' => $allocations,
            'available' => $total_available
        );
    }
    
    /**
     * Reducir stock de un almacén
     */
    private function reduce_warehouse_stock($warehouse_id, $product_id, $quantity) {
        try {
            $this->db->update_warehouse_stock($warehouse_id, $product_id, -$quantity);
            
            // Registrar el movimiento
            $this->log_stock_movement($warehouse_id, $product_id, -$quantity, 'sale');
            
        } catch (Exception $e) {
            error_log('[GAB Sales] Error al reducir stock: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Restaurar stock de un almacén
     */
    private function restore_warehouse_stock($warehouse_id, $product_id, $quantity) {
        try {
            $this->db->update_warehouse_stock($warehouse_id, $product_id, $quantity);
            
            // Registrar el movimiento
            $this->log_stock_movement($warehouse_id, $product_id, $quantity, 'restore');
            
        } catch (Exception $e) {
            error_log('[GAB Sales] Error al restaurar stock: ' . $e->getMessage());
        }
    }
    
    /**
     * Registrar movimiento de stock
     */
    private function log_stock_movement($warehouse_id, $product_id, $quantity, $type) {
        // Si existe la tabla de movimientos, registrar
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_stock_movements';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wpdb->insert(
                $table_name,
                array(
                    'warehouse_id' => $warehouse_id,
                    'product_id' => $product_id,
                    'movement_type' => $type,
                    'quantity' => $quantity,
                    'reference_type' => 'order',
                    'reference_id' => 0, // Se actualizará con el ID del pedido
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s')
            );
        }
    }
    
    /**
     * Prevenir que WooCommerce reduzca el stock por defecto
     */
    public function prevent_default_stock_reduction($can_reduce, $order) {
        // Dejar que nuestro sistema maneje la reducción
        return false;
    }
    
    /**
     * Agregar nota al pedido con información de reducción de stock
     */
    private function add_stock_reduction_note($order, $allocations) {
        $note = __('Stock reducido de los siguientes almacenes:', 'gestion-almacenes') . "\n\n";
        
        $warehouses = $this->db->get_warehouses();
        $warehouse_names = array();
        foreach ($warehouses as $warehouse) {
            $warehouse_names[$warehouse->id] = $warehouse->name;
        }
        
        foreach ($allocations as $item_id => $warehouse_allocations) {
            $item = $order->get_item($item_id);
            $note .= "• " . $item->get_name() . ":\n";
            
            foreach ($warehouse_allocations as $warehouse_id => $qty) {
                $warehouse_name = $warehouse_names[$warehouse_id] ?? 'ID: ' . $warehouse_id;
                $note .= "  - " . $warehouse_name . ": " . $qty . " unidades\n";
            }
        }
        
        $order->add_order_note($note);
    }
    
    /**
     * Mostrar información del almacén en el pedido
     */
    public function show_warehouse_info_in_order($order) {
        $allocations = get_post_meta($order->get_id(), '_gab_warehouse_allocations', true);
        
        if (empty($allocations)) {
            return;
        }
        
        $warehouses = $this->db->get_warehouses();
        $warehouse_names = array();
        foreach ($warehouses as $warehouse) {
            $warehouse_names[$warehouse->id] = $warehouse->name;
        }
        
        ?>
        <div class="gab-order-warehouse-info" style="margin-top: 20px;">
            <h4><?php esc_html_e('Asignación de Stock por Almacén', 'gestion-almacenes'); ?></h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="text-align: left; padding: 8px; border: 1px solid #dee2e6;">
                            <?php esc_html_e('Producto', 'gestion-almacenes'); ?>
                        </th>
                        <th style="text-align: left; padding: 8px; border: 1px solid #dee2e6;">
                            <?php esc_html_e('Almacén', 'gestion-almacenes'); ?>
                        </th>
                        <th style="text-align: center; padding: 8px; border: 1px solid #dee2e6;">
                            <?php esc_html_e('Cantidad', 'gestion-almacenes'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocations as $item_id => $warehouse_allocations): 
                        $item = $order->get_item($item_id);
                        if (!$item) continue;
                        
                        $first = true;
                        foreach ($warehouse_allocations as $warehouse_id => $qty):
                            $warehouse_name = $warehouse_names[$warehouse_id] ?? 'ID: ' . $warehouse_id;
                    ?>
                        <tr>
                            <?php if ($first): ?>
                                <td style="padding: 8px; border: 1px solid #dee2e6;" 
                                    rowspan="<?php echo count($warehouse_allocations); ?>">
                                    <?php echo esc_html($item->get_name()); ?>
                                </td>
                            <?php $first = false; endif; ?>
                            <td style="padding: 8px; border: 1px solid #dee2e6;">
                                <?php echo esc_html($warehouse_name); ?>
                            </td>
                            <td style="text-align: center; padding: 8px; border: 1px solid #dee2e6;">
                                <?php echo esc_html($qty); ?>
                            </td>
                        </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Agregar meta box en la página del pedido
     */
    public function add_warehouse_meta_box() {
        add_meta_box(
            'gab_warehouse_allocation',
            __('Asignación de Stock por Almacén', 'gestion-almacenes'),
            [$this, 'render_warehouse_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Renderizar meta box
     */
    public function render_warehouse_meta_box($post) {
        $order = wc_get_order($post->ID);
        $allocations = get_post_meta($order->get_id(), '_gab_warehouse_allocations', true);
        
        if (empty($allocations)) {
            echo '<p>' . esc_html__('No hay información de asignación de stock.', 'gestion-almacenes') . '</p>';
            return;
        }
        
        $warehouses = $this->db->get_warehouses();
        $warehouse_names = array();
        foreach ($warehouses as $warehouse) {
            $warehouse_names[$warehouse->id] = $warehouse->name;
        }
        
        echo '<div style="max-height: 200px; overflow-y: auto;">';
        foreach ($allocations as $item_id => $warehouse_allocations) {
            $item = $order->get_item($item_id);
            if (!$item) continue;
            
            echo '<strong>' . esc_html($item->get_name()) . '</strong>';
            echo '<ul style="margin: 5px 0 15px 20px;">';
            
            foreach ($warehouse_allocations as $warehouse_id => $qty) {
                $warehouse_name = $warehouse_names[$warehouse_id] ?? 'ID: ' . $warehouse_id;
                echo '<li>' . esc_html($warehouse_name) . ': ' . esc_html($qty) . ' ' . esc_html__('unidades', 'gestion-almacenes') . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    
    /**
     * Restaurar stock cuando se cancela un pedido
     */
    public function restore_stock_on_cancel($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Verificar si se redujo el stock
        if (get_post_meta($order_id, '_gab_stock_reduced', true) !== 'yes') {
            return;
        }
        
        $allocations = get_post_meta($order_id, '_gab_warehouse_allocations', true);
        
        if (empty($allocations)) {
            return;
        }
        
        // Restaurar stock a cada almacén
        foreach ($allocations as $item_id => $warehouse_allocations) {
            $item = $order->get_item($item_id);
            if (!$item) continue;
            
            $product = $item->get_product();
            if (!$product) continue;
            
            $product_id = $product->get_id();
            
            foreach ($warehouse_allocations as $warehouse_id => $qty) {
                $this->restore_warehouse_stock($warehouse_id, $product_id, $qty);
            }
        }
        
        // Marcar como restaurado
        update_post_meta($order_id, '_gab_stock_restored', 'yes');
        delete_post_meta($order_id, '_gab_stock_reduced');
        
        // Agregar nota al pedido
        $order->add_order_note(__('Stock restaurado a los almacenes.', 'gestion-almacenes'));
    }
}