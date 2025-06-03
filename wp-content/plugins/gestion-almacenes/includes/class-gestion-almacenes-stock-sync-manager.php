<?php
/**
 * Gestor de sincronización de stock entre almacenes y WooCommerce
 */
class Gestion_Almacenes_Stock_Sync_Manager {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        
        // Solo activar si la opción está habilitada
        if (get_option('gab_manage_wc_stock', 'yes') === 'yes') {
            $this->init_hooks();
        }
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Modificar la interfaz de producto
        add_action('woocommerce_product_options_stock_status', [$this, 'add_warehouse_stock_info']);
        add_filter('woocommerce_product_get_stock_quantity', [$this, 'override_stock_quantity'], 10, 2);
        add_filter('woocommerce_product_variation_get_stock_quantity', [$this, 'override_stock_quantity'], 10, 2);
        
        // Hacer el campo de stock de solo lectura
        add_action('admin_footer', [$this, 'make_stock_field_readonly']);
        
        // Deshabilitar la edición directa del stock
        add_filter('woocommerce_product_stock_quantity', [$this, 'prevent_direct_stock_update'], 10, 2);
        
        // Sincronizar cuando se actualice el stock del almacén
        add_action('gab_warehouse_stock_updated', [$this, 'sync_woocommerce_stock'], 10, 2);
        
        // Agregar columna de stock total en la lista de productos
        add_filter('manage_edit-product_columns', [$this, 'add_total_stock_column']);
        add_action('manage_product_posts_custom_column', [$this, 'display_total_stock_column'], 10, 2);
        
        // Validar antes de realizar pedidos
        add_filter('woocommerce_product_is_in_stock', [$this, 'check_warehouse_stock'], 10, 2);

        // Hook para sincronizar al cargar el producto
        add_action('woocommerce_product_object_updated_props', [$this, 'sync_on_product_load'], 10, 2);
        add_action('woocommerce_admin_process_product_object', [$this, 'force_sync_stock']);
    }
    
    /**
     * Agregar información de stock por almacén en la página del producto
    */
    public function add_warehouse_stock_info() {
        global $post;
        
        if (!$post) return;
        
        $product_id = $post->ID;
        $warehouses = $this->db->get_warehouses();
        $total_stock = 0;
        
        ?>
        <div class="options_group gab_warehouse_stock_info">
            <h4 style="padding-left: 12px;"><?php esc_html_e('Stock por Almacén', 'gestion-almacenes'); ?></h4>
            
            <style>
                .gab-stock-table {
                    margin: 10px 12px;
                    width: calc(100% - 24px);
                    border-collapse: collapse;
                }
                .gab-stock-table th,
                .gab-stock-table td {
                    padding: 8px;
                    border: 1px solid #ddd;
                    text-align: left;
                }
                .gab-stock-table th {
                    background-color: #f5f5f5;
                    font-weight: bold;
                }
                .gab-stock-table .total-row {
                    background-color: #f0f0f0;
                    font-weight: bold;
                }
                .gab-stock-editable {
                    width: 80px;
                    text-align: center;
                }
                .gab-stock-notice {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    padding: 10px;
                    margin: 10px 12px;
                    border-radius: 3px;
                }
            </style>

            // Condicional ocultar tabla primaria
            <?php if (get_option('gab_manage_wc_stock', 'yes') === 'yes'): ?>
            <style>
                /* Ocultar la tabla antigua de stock por almacén */
                #warehouse_stock_fields,
                .options_group.warehouse_stock_fields {
                    display: none !important;
                }
            </style>
            <?php endif; ?>
            
            <div class="gab-stock-notice">
                <p><?php esc_html_e('El stock total es gestionado automáticamente por el plugin de almacenes.', 'gestion-almacenes'); ?></p>
            </div>
            
            <table class="gab-stock-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Almacén', 'gestion-almacenes'); ?></th>
                        <th><?php esc_html_e('Stock Actual', 'gestion-almacenes'); ?></th>
                        <th><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warehouses as $warehouse): 
                        $stock = $this->db->get_warehouse_stock($warehouse->id, $product_id);
                        $total_stock += $stock;
                    ?>
                        <tr>
                            <td><?php echo esc_html($warehouse->name); ?></td>
                            <td>
                                <span class="gab-stock-display" data-warehouse="<?php echo esc_attr($warehouse->id); ?>">
                                    <?php echo esc_html($stock); ?>
                                </span>
                                <input type="number" 
                                    class="gab-stock-editable" 
                                    data-warehouse="<?php echo esc_attr($warehouse->id); ?>"
                                    data-product="<?php echo esc_attr($product_id); ?>"
                                    value="<?php echo esc_attr($stock); ?>"
                                    min="0"
                                    style="display:none;">
                            </td>
                            <td>
                                <button type="button" class="button gab-edit-stock" data-warehouse="<?php echo esc_attr($warehouse->id); ?>">
                                    <?php esc_html_e('Editar', 'gestion-almacenes'); ?>
                                </button>
                                <button type="button" class="button gab-save-stock" data-warehouse="<?php echo esc_attr($warehouse->id); ?>" style="display:none;">
                                    <?php esc_html_e('Guardar', 'gestion-almacenes'); ?>
                                </button>
                                <button type="button" class="button gab-cancel-stock" data-warehouse="<?php echo esc_attr($warehouse->id); ?>" style="display:none;">
                                    <?php esc_html_e('Cancelar', 'gestion-almacenes'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><?php esc_html_e('Total', 'gestion-almacenes'); ?></td>
                        <td colspan="2"><?php echo esc_html($total_stock); ?></td>
                    </tr>
                </tfoot>
            </table>
            
            <script>
            jQuery(document).ready(function($) {
                // Editar stock
                $('.gab-edit-stock').on('click', function() {
                    var warehouseId = $(this).data('warehouse');
                    $(this).hide();
                    $('.gab-save-stock[data-warehouse="' + warehouseId + '"]').show();
                    $('.gab-cancel-stock[data-warehouse="' + warehouseId + '"]').show();
                    $('.gab-stock-display[data-warehouse="' + warehouseId + '"]').hide();
                    $('.gab-stock-editable[data-warehouse="' + warehouseId + '"]').show().focus();
                });
                
                // Cancelar edición
                $('.gab-cancel-stock').on('click', function() {
                    var warehouseId = $(this).data('warehouse');
                    var originalValue = $('.gab-stock-display[data-warehouse="' + warehouseId + '"]').text().trim();
                    
                    $(this).hide();
                    $('.gab-save-stock[data-warehouse="' + warehouseId + '"]').hide();
                    $('.gab-edit-stock[data-warehouse="' + warehouseId + '"]').show();
                    $('.gab-stock-editable[data-warehouse="' + warehouseId + '"]').hide().val(originalValue);
                    $('.gab-stock-display[data-warehouse="' + warehouseId + '"]').show();
                });
                
                // Guardar stock
                $('.gab-save-stock').on('click', function() {
                    var warehouseId = $(this).data('warehouse');
                    var productId = $('.gab-stock-editable[data-warehouse="' + warehouseId + '"]').data('product');
                    var newStock = $('.gab-stock-editable[data-warehouse="' + warehouseId + '"]').val();
                    var $button = $(this);
                    var $row = $button.closest('tr');
                    
                    // Validar entrada
                    if (newStock === '' || newStock < 0) {
                        alert('<?php esc_html_e('Por favor ingrese un valor válido', 'gestion-almacenes'); ?>');
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php esc_html_e('Guardando...', 'gestion-almacenes'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'gab_update_warehouse_stock',
                            warehouse_id: warehouseId,
                            product_id: productId,
                            stock: newStock,
                            nonce: '<?php echo wp_create_nonce('gab_update_stock'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Actualizar la visualización del stock del almacén
                                $('.gab-stock-display[data-warehouse="' + warehouseId + '"]').text(newStock);
                                
                                // Ocultar botones de edición
                                $button.hide();
                                $('.gab-cancel-stock[data-warehouse="' + warehouseId + '"]').hide();
                                $('.gab-edit-stock[data-warehouse="' + warehouseId + '"]').show();
                                $('.gab-stock-editable[data-warehouse="' + warehouseId + '"]').hide();
                                $('.gab-stock-display[data-warehouse="' + warehouseId + '"]').show();
                                
                                // Mostrar efecto visual de éxito
                                $row.css('background-color', '#d4edda');
                                setTimeout(function() {
                                    $row.css('background-color', '');
                                }, 2000);
                                
                                // Actualizar el total sin recargar
                                if (response.data && response.data.new_total !== undefined) {
                                    // Actualizar el total en la tabla
                                    $('.total-row td:last').html('<strong>' + response.data.new_total + '</strong>');
                                    
                                    // Actualizar el campo de stock de WooCommerce
                                    $('#_stock').val(response.data.new_total);
                                    
                                    // Si el stock es de solo lectura, actualizar también el texto
                                    if ($('#_stock').prop('readonly')) {
                                        $('#_stock').css('background-color', '#d4edda');
                                        setTimeout(function() {
                                            $('#_stock').css('background-color', '#f0f0f0');
                                        }, 2000);
                                    }
                                    
                                    // Actualizar el desglose si existe
                                    if (response.data.warehouse_stocks) {
                                        var breakdown = [];
                                        $.each(response.data.warehouse_stocks, function(id, data) {
                                            if (data.stock > 0) {
                                                breakdown.push(data.name + ': ' + data.stock);
                                            }
                                        });
                                        
                                        // Buscar si hay una columna de desglose en la lista de productos
                                        var $productRow = $('tr[data-product-id="' + productId + '"]');
                                        if ($productRow.length) {
                                            var $stockCell = $productRow.find('.gab_total_stock');
                                            if ($stockCell.length) {
                                                var html = '<span style="font-weight: bold;">' + response.data.new_total + '</span>';
                                                if (breakdown.length > 0) {
                                                    html += '<br><small style="color: #666;">' + breakdown.join('<br>') + '</small>';
                                                }
                                                $stockCell.html(html);
                                            }
                                        }
                                    }
                                }
                                
                                // Mostrar notificación temporal
                                if (!$('.gab-stock-update-notice').length) {
                                    $('<div class="gab-stock-update-notice notice notice-success is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999;"><p>' + response.data.message + '</p></div>')
                                        .appendTo('body')
                                        .delay(3000)
                                        .fadeOut(function() {
                                            $(this).remove();
                                        });
                                }
                                
                            } else {
                                var errorMsg = response.data && response.data.message ? response.data.message : 'Error desconocido';
                                alert('Error: ' + errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error AJAX:', status, error);
                            console.error('Respuesta:', xhr.responseText);
                            alert('<?php esc_html_e('Error al procesar la solicitud. Por favor revise la consola.', 'gestion-almacenes'); ?>');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('<?php esc_html_e('Guardar', 'gestion-almacenes'); ?>');
                        }
                    });
                });
                // Calcular y mostrar el stock total
                var totalStock = 0;
                $('.gab-stock-display').each(function() {
                    var stock = parseInt($(this).text()) || 0;
                    totalStock += stock;
                });
                
                // Actualizar el campo de stock de WooCommerce
                if ($('#_stock').length) {
                    var currentStock = parseInt($('#_stock').val()) || 0;
                    
                    if (currentStock !== totalStock) {
                        $('#_stock').val(totalStock);
                        
                        // Mostrar un indicador visual de que se actualizó
                        $('#_stock').css('background-color', '#fff3cd');
                        setTimeout(function() {
                            $('#_stock').css('background-color', '#f0f0f0');
                        }, 2000);
                        
                        // Actualizar también el total en la tabla
                        $('.total-row td:last').html('<strong>' + totalStock + '</strong>');
                        
                        // Si el stock cambió a 0, actualizar el estado del stock
                        if (totalStock === 0) {
                            $('#_stock_status').val('outofstock');
                        } else if (totalStock > 0 && $('#_stock_status').val() === 'outofstock') {
                            $('#_stock_status').val('instock');
                        }
                    }
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Sobrescribir la cantidad de stock de WooCommerce
     */
    public function override_stock_quantity($stock, $product) {
        // Si el control de stock no está activado, devolver el stock original
        if (get_option('gab_manage_wc_stock', 'yes') !== 'yes') {
            return $stock;
        }
        
        // Solo para productos que manejan stock
        if (!$product || !$product->get_manage_stock()) {
            return $stock;
        }
        
        $product_id = $product->get_id();
        
        // SIEMPRE devolver el stock total de los almacenes, incluso si es 0
        $total_stock = $this->get_total_warehouse_stock($product_id);
        
        // Si el stock de WooCommerce es diferente al total de almacenes, actualizarlo
        if ($stock != $total_stock) {
            // Actualizar el stock en la base de datos de WooCommerce
            update_post_meta($product_id, '_stock', $total_stock);
            
            // También actualizar el estado del stock
            update_post_meta($product_id, '_stock_status', $total_stock > 0 ? 'instock' : 'outofstock');
        }
        
        return $total_stock;
    }

    /**
     * Obtener stock total de todos los almacenes para un producto
     * @param int $product_id ID del producto
     * @return int Stock total
     */
    public function get_total_warehouse_stock($product_id) {
        global $gestion_almacenes_db;
        
        $warehouses = $gestion_almacenes_db->get_warehouses();
        $total = 0;
        
        foreach ($warehouses as $warehouse) {
            $stock = $gestion_almacenes_db->get_warehouse_stock($warehouse->id, $product_id);
            $total += intval($stock);
        }
        
        return $total;
    }
    
    /**
     * Hacer el campo de stock de solo lectura
     */
    public function make_stock_field_readonly() {
        if (get_current_screen()->id === 'product'): ?>
            <script>
            jQuery(document).ready(function($) {
                // Hacer el campo de stock de solo lectura
                $('#_stock').prop('readonly', true).css({
                    'background-color': '#f0f0f0',
                    'cursor': 'not-allowed'
                });
                
                // Agregar nota
                if ($('#_stock').length && !$('.gab-stock-readonly-notice').length) {
                    $('#_stock').after('<p class="gab-stock-readonly-notice" style="color: #666; font-style: italic; margin-top: 5px;"><?php esc_html_e('Este campo es gestionado automáticamente por el plugin de almacenes.', 'gestion-almacenes'); ?></p>');
                }
                
                // Deshabilitar también para variaciones
                $(document).on('woocommerce_variations_loaded', function() {
                    $('.variable_stock').prop('readonly', true).css({
                        'background-color': '#f0f0f0',
                        'cursor': 'not-allowed'
                    });
                });
            });
            </script>
        <?php endif;
    }
    
    /**
     * Prevenir actualización directa del stock
     */
    public function prevent_direct_stock_update($stock, $product) {
        // Si se está intentando actualizar desde el admin de WooCommerce
        if (is_admin() && !defined('DOING_AJAX')) {
            return $this->get_total_warehouse_stock($product->get_id());
        }
        return $stock;
    }
    
    /**
     * Sincronizar stock con WooCommerce cuando se actualiza el stock del almacén
     */
    public function sync_woocommerce_stock($product_id, $warehouse_id) {
        $product = wc_get_product($product_id);
        if (!$product) return;
        
        $total_stock = $this->get_total_warehouse_stock($product_id);
        
        // Actualizar sin disparar hooks infinitos
        remove_filter('woocommerce_product_stock_quantity', [$this, 'prevent_direct_stock_update'], 10);
        
        $product->set_stock_quantity($total_stock);
        $product->set_manage_stock(true);
        $product->save();
        
        add_filter('woocommerce_product_stock_quantity', [$this, 'prevent_direct_stock_update'], 10, 2);
    }

    /**
     * Sincronizar stock cuando se carga un producto
     */
    public function sync_on_product_load($product, $updated_props) {
        // Solo si estamos en el admin y el control está activado
        if (!is_admin() || get_option('gab_manage_wc_stock', 'yes') !== 'yes') {
            return;
        }
        
        // Si el producto maneja stock
        if ($product->get_manage_stock()) {
            $product_id = $product->get_id();
            $total_stock = $this->get_total_warehouse_stock($product_id);
            
            // Forzar la actualización del stock
            $product->set_stock_quantity($total_stock);
        }
    }

    /**
     * Forzar sincronización del stock antes de guardar
     */
    public function force_sync_stock($product) {
        if (get_option('gab_manage_wc_stock', 'yes') !== 'yes') {
            return;
        }
        
        if ($product->get_manage_stock()) {
            $product_id = $product->get_id();
            $total_stock = $this->get_total_warehouse_stock($product_id);
            
            // Forzar el stock correcto
            $product->set_stock_quantity($total_stock);
            
            // Asegurarse de que se guarde
            remove_filter('woocommerce_product_stock_quantity', [$this, 'prevent_direct_stock_update'], 10);
            update_post_meta($product_id, '_stock', $total_stock);
            update_post_meta($product_id, '_stock_status', $total_stock > 0 ? 'instock' : 'outofstock');
            add_filter('woocommerce_product_stock_quantity', [$this, 'prevent_direct_stock_update'], 10, 2);
        }
    }
    
    /**
     * Agregar columna de stock total en la lista de productos
     */
    public function add_total_stock_column($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Agregar después de la columna de stock
            if ($key === 'is_in_stock') {
                $new_columns['gab_total_stock'] = __('Stock Total<br>(Almacenes)', 'gestion-almacenes');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Mostrar el stock total en la columna
     */
    public function display_total_stock_column($column, $post_id) {
        if ($column === 'gab_total_stock') {
            $product = wc_get_product($post_id);
            
            if ($product && $product->get_manage_stock()) {
                $total_stock = $this->get_total_warehouse_stock($post_id);
                
                echo '<span style="font-weight: bold;">' . esc_html($total_stock) . '</span>';
                
                // Mostrar desglose
                $warehouses = $this->db->get_warehouses();
                $breakdown = [];
                
                foreach ($warehouses as $warehouse) {
                    $stock = $this->db->get_warehouse_stock($warehouse->id, $post_id);
                    if ($stock > 0) {
                        $breakdown[] = esc_html($warehouse->name) . ': ' . $stock;
                    }
                }
                
                if (!empty($breakdown)) {
                    echo '<br><small style="color: #666;">' . implode('<br>', $breakdown) . '</small>';
                }
            } else {
                echo '—';
            }
        }
    }
    
    /**
     * Verificar stock del almacén antes de permitir la compra
     */
    public function check_warehouse_stock($is_in_stock, $product) {
        if (!$product->get_manage_stock()) {
            return $is_in_stock;
        }
        
        $total_stock = $this->get_total_warehouse_stock($product->get_id());
        
        return $total_stock > 0;
    }
}