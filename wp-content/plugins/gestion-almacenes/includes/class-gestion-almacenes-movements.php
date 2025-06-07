<?php
/**
 * Gestor de movimientos de stock
 */
class Gestion_Almacenes_Movements {
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Registrar un movimiento de stock
     */
    public function log_movement($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_stock_movements';
        
        // Obtener el balance actual después del movimiento
        $balance_after = $this->db->get_warehouse_stock($data['warehouse_id'], $data['product_id']);
        
        $movement_data = array(
            'product_id' => $data['product_id'],
            'warehouse_id' => $data['warehouse_id'],
            'movement_type' => $data['type'],
            'quantity' => $data['quantity'],
            'balance_after' => $balance_after,
            'reference_type' => isset($data['reference_type']) ? $data['reference_type'] : null,
            'reference_id' => isset($data['reference_id']) ? $data['reference_id'] : null,
            'notes' => isset($data['notes']) ? $data['notes'] : null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $table,
            $movement_data,
            array('%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            error_log('[GAB Movements] Error al registrar movimiento: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Obtener historial de movimientos de un producto
     */
    public function get_product_movements($product_id, $warehouse_id = null, $limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_stock_movements';
        $warehouses_table = $wpdb->prefix . 'gab_warehouses';
        
        $query = "SELECT 
                    m.*,
                    w.name as warehouse_name,
                    u.display_name as user_name
                FROM $table m
                LEFT JOIN $warehouses_table w ON m.warehouse_id = w.id
                LEFT JOIN {$wpdb->users} u ON m.created_by = u.ID
                WHERE m.product_id = %d";
        
        $params = array($product_id);
        
        if ($warehouse_id) {
            $query .= " AND m.warehouse_id = %d";
            $params[] = $warehouse_id;
        }
        
        $query .= " ORDER BY m.created_at DESC, m.id DESC LIMIT %d";
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Obtener estadísticas de movimientos
     */
    public function get_movement_stats($product_id = null, $warehouse_id = null, $date_from = null, $date_to = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_stock_movements';
        
        $where = array('1=1');
        $params = array();
        
        if ($product_id) {
            $where[] = "product_id = %d";
            $params[] = $product_id;
        }
        
        if ($warehouse_id) {
            $where[] = "warehouse_id = %d";
            $params[] = $warehouse_id;
        }
        
        if ($date_from) {
            $where[] = "created_at >= %s";
            $params[] = $date_from . ' 00:00:00';
        }
        
        if ($date_to) {
            $where[] = "created_at <= %s";
            $params[] = $date_to . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT 
                    movement_type,
                    COUNT(*) as total_movements,
                    SUM(ABS(quantity)) as total_quantity
                FROM $table
                WHERE $where_clause
                GROUP BY movement_type";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query, OBJECT_K);
    }
}