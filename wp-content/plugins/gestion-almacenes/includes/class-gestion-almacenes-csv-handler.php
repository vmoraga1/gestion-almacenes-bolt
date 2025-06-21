<?php
/**
 * Manejador de exportación/importación CSV para stock de almacenes
 */
class Gestion_Almacenes_CSV_Handler {
    
    private $db;
    private $almacenes = array();
    
    public function __construct($db) {
        $this->db = $db;
        $this->init_hooks();
        $this->load_warehouses();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hooks para exportación
        add_filter('woocommerce_product_export_column_names', array($this, 'add_export_columns'), 10, 1);
        add_filter('woocommerce_product_export_product_default_columns', array($this, 'add_export_columns'), 10, 1);
        
        // Hooks para importación
        add_filter('woocommerce_csv_product_import_mapping_options', array($this, 'add_import_columns'), 10, 1);
        add_filter('woocommerce_csv_product_import_mapping_default_columns', array($this, 'add_import_column_mapping'), 10, 1);
        
        // Hook para procesar después de importar
        add_action('woocommerce_product_import_inserted_product_object', array($this, 'save_imported_warehouse_stock'), 10, 2);
        
        // Hook para debug
        add_action('woocommerce_product_import_before_process_item', array($this, 'debug_import_data'), 10, 1);
    }
    
    /**
     * Cargar lista de almacenes
     */
    private function load_warehouses() {
        $this->almacenes = $this->db->get_warehouses();
        //error_log('GAB CSV: Almacenes cargados: ' . count($this->almacenes));
    }
    
    /**
     * Agregar columnas de almacenes a la exportación
     */
    public function add_export_columns($columns) {
        // Agregar columna de stock total en almacenes
        $columns['stock_total_almacenes'] = __('Stock Total en Almacenes', 'gestion-almacenes');
        
        // Registrar filtro para stock total
        add_filter('woocommerce_product_export_product_column_stock_total_almacenes', array($this, 'export_total_warehouse_stock'), 10, 3);
        
        // Agregar una columna por cada almacén
        foreach ($this->almacenes as $almacen) {
            $column_key = 'stock_almacen_' . $almacen->id;
            $column_name = sprintf(__('Stock - %s', 'gestion-almacenes'), $almacen->name);
            $columns[$column_key] = $column_name;
            
            // Registrar el filtro para esta columna específica
            add_filter('woocommerce_product_export_product_column_' . $column_key, array($this, 'export_warehouse_stock'), 10, 3);
        }
        
        return $columns;
    }
    
    /**
     * Exportar stock de un almacén específico
     */
    public function export_warehouse_stock($value, $product, $column_key) {
        // Extraer ID del almacén del nombre de la columna
        if (preg_match('/stock_almacen_(\d+)/', $column_key, $matches)) {
            $warehouse_id = intval($matches[1]);
            $stock = $this->db->get_warehouse_stock($warehouse_id, $product->get_id());
            return $stock > 0 ? $stock : '0';
        }
        
        return '0';
    }
    
    /**
     * Exportar stock total en almacenes
     */
    public function export_total_warehouse_stock($value, $product, $column_key) {
        $total = $this->db->get_total_stock_all_warehouses($product->get_id());
        return $total > 0 ? $total : '0';
    }
    
    /**
     * Agregar columnas para importación
     */
    public function add_import_columns($columns) {
        // Columna de stock total
        $columns['stock_total_almacenes'] = __('Stock Total en Almacenes', 'gestion-almacenes');
        
        // Una columna por cada almacén
        foreach ($this->almacenes as $almacen) {
            $column_key = 'stock_almacen_' . $almacen->id;
            $column_name = sprintf(__('Stock - %s', 'gestion-almacenes'), $almacen->name);
            $columns[$column_key] = $column_name;
        }
        
        return $columns;
    }
    
    /**
     * Mapeo de columnas para importación
     */
    public function add_import_column_mapping($columns) {
        // Agregar mapeo automático si los nombres coinciden
        $columns[__('Stock Total en Almacenes', 'gestion-almacenes')] = 'stock_total_almacenes';
        $columns['Stock Total en Almacenes'] = 'stock_total_almacenes';
        
        foreach ($this->almacenes as $almacen) {
            $column_name = sprintf(__('Stock - %s', 'gestion-almacenes'), $almacen->name);
            $column_key = 'stock_almacen_' . $almacen->id;
            
            // Mapear con y sin traducción
            $columns[$column_name] = $column_key;
            $columns['Stock - ' . $almacen->name] = $column_key;
        }
        
        return $columns;
    }
    
    /**
     * Debug de datos de importación
     */
    public function debug_import_data($data) {
        error_log('GAB CSV Import - Datos recibidos: ' . print_r($data, true));
        
        // Buscar columnas de almacén
        foreach ($data as $key => $value) {
            if (strpos($key, 'stock_almacen_') === 0) {
                error_log("GAB CSV Import - Encontrada columna de almacén: $key = $value");
            }
        }
    }
    
    /**
     * Guardar stock de almacenes después de importar producto
     */
    public function save_imported_warehouse_stock($product, $data) {
        global $gestion_almacenes_movements;
        
        $product_id = $product->get_id();
        error_log('GAB CSV Import - Procesando producto ID: ' . $product_id);
        
        // Variable para verificar si se actualizó algo
        $stock_updated = false;
        $total_stock = 0;
        
        // Procesar columnas individuales de almacén
        foreach ($this->almacenes as $almacen) {
            $column_key = 'stock_almacen_' . $almacen->id;
            
            // Verificar si existe la columna en los datos
            if (isset($data[$column_key]) && $data[$column_key] !== '') {
                $new_stock = intval($data[$column_key]);
                
                // Solo procesar si el stock es válido (0 o más)
                if ($new_stock >= 0) {
                    error_log("GAB CSV Import - Actualizando stock: Producto $product_id, Almacén {$almacen->id}, Stock: $new_stock");
                    
                    // Obtener stock actual
                    $current_stock = $this->db->get_warehouse_stock($almacen->id, $product_id);
                    
                    // Guardar el nuevo stock
                    $result = $this->db->save_product_warehouse_stock($product_id, $almacen->id, $new_stock);
                    
                    // Considerar exitoso si devuelve true o si no hay error
                    if ($result !== false) {
                        $stock_updated = true;
                        $total_stock += $new_stock;
                        
                        error_log("GAB CSV Import - Stock guardado exitosamente");
                        
                        // Registrar movimiento solo si cambió
                        if ($current_stock != $new_stock && isset($gestion_almacenes_movements)) {
                            $quantity_change = $new_stock - $current_stock;
                            $gestion_almacenes_movements->log_movement(array(
                                'product_id' => $product_id,
                                'warehouse_id' => $almacen->id,
                                'type' => 'adjustment',
                                'quantity' => $quantity_change,
                                'reference_type' => 'csv_import',
                                'reference_id' => 0,
                                'notes' => sprintf(
                                    __('Importación CSV: %s. Stock %d → %d', 'gestion-almacenes'),
                                    $product->get_name(),
                                    $current_stock,
                                    $new_stock
                                )
                            ));
                        }
                    } else {
                        error_log("GAB CSV Import - ERROR real al guardar stock");
                    }
                }
            }
        }
        
        // Actualizar el stock total de WooCommerce
        if ($stock_updated && get_option('gab_manage_wc_stock', 'yes') === 'yes') {
            // Si no se especificó stock en la columna "Inventario", usar el total de almacenes
            if (!isset($data['stock_quantity']) || $data['stock_quantity'] === '') {
                $product->set_stock_quantity($total_stock);
                $product->set_manage_stock(true);
                $product->save();
                
                error_log('GAB CSV Import - Stock WooCommerce actualizado a: ' . $total_stock);
            }
        }
    }
}