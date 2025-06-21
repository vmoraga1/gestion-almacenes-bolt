<?php
/**
 ********** IMPORTANTE: Muestra una nueva columna con el stock por almacén, ESTA OCULTA CON CSS ***********
 */
class Gestion_Almacenes_Product_Columns {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Agregar columna
        add_filter('manage_edit-product_columns', array($this, 'add_warehouse_stock_column'), 20);
        
        // Mostrar contenido de la columna
        add_action('manage_product_posts_custom_column', array($this, 'display_warehouse_stock_column'), 10, 2);
        
        // Hacer la columna ordenable (opcional)
        add_filter('manage_edit-product_sortable_columns', array($this, 'make_column_sortable'));
        
        // CSS para la columna
        add_action('admin_head', array($this, 'column_styles'));
    }
    
    /**
     * Agregar columna de stock por almacén
     */
    public function add_warehouse_stock_column($columns) {
        // Insertar después de la columna de stock
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Insertar después de stock_status o is_in_stock
            if ($key == 'is_in_stock') {
                $new_columns['warehouse_stock'] = __('Stock por Almacén', 'gestion-almacenes');
            }
        }
        
        // Si no se encontró la columna de stock, agregar al final
        if (!isset($new_columns['warehouse_stock'])) {
            $new_columns['warehouse_stock'] = __('Stock por Almacén', 'gestion-almacenes');
        }
        
        return $new_columns;
    }
    
    /**
     * Mostrar contenido de la columna
     */
    public function display_warehouse_stock_column($column, $post_id) {
        if ($column !== 'warehouse_stock') {
            return;
        }
        
        // Obtener almacenes
        $warehouses = $this->db->get_warehouses();
        
        if (empty($warehouses)) {
            echo '<span style="color: #999;">—</span>';
            return;
        }
        
        // Obtener stock por almacén
        $has_stock = false;
        $output = '<div class="gab-warehouse-stock-list">';
        
        foreach ($warehouses as $warehouse) {
            $stock = $this->db->get_warehouse_stock($warehouse->id, $post_id);
            
            if ($stock > 0) {
                $has_stock = true;
                $stock_class = $stock <= get_option('gab_low_stock_threshold', 5) ? 'low-stock' : 'in-stock';
                
                $output .= sprintf(
                    '<div class="warehouse-stock-item %s" title="%s">
                        <span class="warehouse-name">%s:</span>
                        <span class="stock-qty">%d</span>
                    </div>',
                    esc_attr($stock_class),
                    esc_attr($warehouse->name),
                    esc_html($this->abbreviate_name($warehouse->name)),
                    $stock
                );
            }
        }
        
        $output .= '</div>';
        
        if (!$has_stock) {
            echo '<span style="color: #999;">—</span>';
        } else {
            echo $output;
        }
    }
    
    /**
     * Abreviar nombre del almacén para la columna
     */
    private function abbreviate_name($name) {
        // Si el nombre es corto, devolverlo completo
        if (strlen($name) <= 15) {
            return $name;
        }
        
        // Intentar usar las primeras palabras
        $words = explode(' ', $name);
        if (count($words) > 1) {
            $abbr = '';
            foreach ($words as $word) {
                $abbr .= mb_substr($word, 0, 1);
            }
            return strtoupper($abbr);
        }
        
        // Si es una sola palabra larga, truncar
        return mb_substr($name, 0, 12) . '...';
    }
    
    /**
     * Hacer la columna ordenable (opcional)
     */
    public function make_column_sortable($columns) {
        $columns['warehouse_stock'] = 'warehouse_stock';
        return $columns;
    }
    
    /**
     * Estilos CSS para la columna
     */
    public function column_styles() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'edit-product') {
            ?>
            <style>
                .column-warehouse_stock {
                    display: none;
                }
                
                .gab-warehouse-stock-list {
                    font-size: 12px;
                }
                
                .warehouse-stock-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 2px 0;
                    border-bottom: 1px dotted #ddd;
                }
                
                .warehouse-stock-item:last-child {
                    border-bottom: none;
                }
                
                .warehouse-stock-item.in-stock .stock-qty {
                    color: #00a32a;
                    font-weight: 600;
                }
                
                .warehouse-stock-item.low-stock .stock-qty {
                    color: #dba617;
                    font-weight: 600;
                }
                
                .warehouse-name {
                    color: #666;
                    margin-right: 5px;
                }
                
                @media screen and (max-width: 1400px) {
                    .column-warehouse_stock {
                        width: 120px;
                    }
                    
                    .gab-warehouse-stock-list {
                        font-size: 11px;
                    }
                }
            </style>
            <?php
        }
    }
}