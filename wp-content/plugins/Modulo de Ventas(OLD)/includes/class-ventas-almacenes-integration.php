<?php
/**
 * Clase para integrar el sistema de ventas con gestión de almacenes
 * 
 * @package Sistema_Ventas
 * @subpackage Integracion_Almacenes
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ventas_Almacenes_Integration {
    
    private static $instance = null;
    private $db;
    private $logger;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = Ventas_DB::get_instance();
        $this->logger = Ventas_Logger::get_instance();
        
        // Hooks para integración
        add_action('init', array($this, 'init_integration'));
        add_action('wp_ajax_ventas_get_stock_almacenes', array($this, 'ajax_get_stock_almacenes'));
        add_action('wp_ajax_ventas_validar_stock_almacen', array($this, 'ajax_validar_stock_almacen'));
        
        // Hook para limpiar reservas expiradas
        add_action('ventas_limpiar_reservas', array($this, 'limpiar_reservas_expiradas'));
        
        // Programar limpieza de reservas cada hora
        if (!wp_next_scheduled('ventas_limpiar_reservas')) {
            wp_schedule_event(time(), 'hourly', 'ventas_limpiar_reservas');
        }
    }
    
    /**
     * Inicializar la integración
     */
    public function init_integration() {
        // Verificar si el plugin de almacenes está activo
        if (!$this->is_warehouse_plugin_active()) {
            return;
        }
        
        // Actualizar tablas si es necesario
        $this->check_and_update_tables();
    }
    
    /**
     * Verificar si el plugin de almacenes está activo
     */
    public function is_warehouse_plugin_active() {
        return class_exists('Gestion_Almacenes_DB');
    }
    
    /**
     * Verificar y actualizar tablas
     */
    private function check_and_update_tables() {
        $version_actual = get_option('ventas_almacenes_db_version', '0');
        $version_nueva = '1.1.0';
        
        if (version_compare($version_actual, $version_nueva, '<')) {
            $this->db->update_tables_for_warehouses();
            update_option('ventas_almacenes_db_version', $version_nueva);
        }
    }
    
    /**
     * Obtener almacenes disponibles
     */
    public function get_almacenes() {
        if (!$this->is_warehouse_plugin_active()) {
            return array();
        }
        
        global $gestion_almacenes_db;
        return $gestion_almacenes_db->get_warehouses();
    }
    
    /**
     * Obtener stock de un producto en todos los almacenes
     */
    public function get_stock_por_almacenes($producto_id) {
        if (!$this->is_warehouse_plugin_active()) {
            return array();
        }
        
        global $gestion_almacenes_db;
        $almacenes = $this->get_almacenes();
        $stock_data = array();
        
        foreach ($almacenes as $almacen) {
            $stock_actual = $gestion_almacenes_db->get_warehouse_stock($almacen->id, $producto_id);
            $stock_disponible = $this->db->get_stock_disponible($producto_id, $almacen->id);
            
            $stock_data[] = array(
                'almacen_id' => $almacen->id,
                'almacen_nombre' => $almacen->name,
                'stock_actual' => $stock_actual,
                'stock_disponible' => $stock_disponible,
                'stock_reservado' => $stock_actual - $stock_disponible
            );
        }
        
        return $stock_data;
    }
    
    /**
     * AJAX: Obtener stock de almacenes para un producto
     */
    public function ajax_get_stock_almacenes() {
        check_ajax_referer('ventas_nonce', 'nonce');
        
        $producto_id = isset($_POST['producto_id']) ? intval($_POST['producto_id']) : 0;
        
        if (!$producto_id) {
            wp_send_json_error('ID de producto inválido');
        }
        
        $stock_data = $this->get_stock_por_almacenes($producto_id);
        
        wp_send_json_success(array(
            'almacenes' => $stock_data,
            'tiene_plugin_almacenes' => $this->is_warehouse_plugin_active()
        ));
    }
    
    /**
     * AJAX: Validar stock disponible en almacén
     */
    public function ajax_validar_stock_almacen() {
        check_ajax_referer('ventas_nonce', 'nonce');
        
        $producto_id = isset($_POST['producto_id']) ? intval($_POST['producto_id']) : 0;
        $almacen_id = isset($_POST['almacen_id']) ? intval($_POST['almacen_id']) : 0;
        $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 0;
        
        if (!$producto_id || !$cantidad) {
            wp_send_json_error('Datos inválidos');
        }
        
        // Si no se especifica almacén, usar el predeterminado
        if (!$almacen_id) {
            $almacen_id = $this->db->get_config('almacen_predeterminado');
        }
        
        $stock_disponible = $this->db->get_stock_disponible($producto_id, $almacen_id);
        $es_valido = $stock_disponible >= $cantidad;
        
        wp_send_json_success(array(
            'es_valido' => $es_valido,
            'stock_disponible' => $stock_disponible,
            'cantidad_solicitada' => $cantidad
        ));
    }
    
    /**
     * Reducir stock del almacén al convertir cotización
     */
    public function reducir_stock_almacen($cotizacion_id, $order_id) {
        if (!$this->is_warehouse_plugin_active()) {
            return;
        }
        
        global $wpdb, $gestion_almacenes_db;
        
        // Obtener items de la cotización
        $items = $this->db->get_items_cotizacion($cotizacion_id);
        
        foreach ($items as $item) {
            if (empty($item->almacen_id)) {
                continue;
            }
            
            // Reducir stock del almacén
            $stock_actual = $gestion_almacenes_db->get_warehouse_stock($item->almacen_id, $item->producto_id);
            $nuevo_stock = max(0, $stock_actual - $item->cantidad);
            
            $gestion_almacenes_db->set_warehouse_stock($item->almacen_id, $item->producto_id, $nuevo_stock);
            
            $this->logger->info('Stock reducido por conversión de cotización', array(
                'cotizacion_id' => $cotizacion_id,
                'order_id' => $order_id,
                'producto_id' => $item->producto_id,
                'almacen_id' => $item->almacen_id,
                'cantidad' => $item->cantidad,
                'stock_anterior' => $stock_actual,
                'stock_nuevo' => $nuevo_stock
            ));
        }
        
        // Liberar reservas si existen
        $this->db->liberar_reservas_cotizacion($cotizacion_id);
    }
    
    /**
     * Validar disponibilidad de stock para todos los items de una cotización
     */
    public function validar_disponibilidad_cotizacion($cotizacion_id) {
        $items = $this->db->get_items_cotizacion($cotizacion_id);
        $errores = array();
        
        foreach ($items as $item) {
            if (empty($item->almacen_id)) {
                $errores[] = sprintf(
                    'El producto "%s" no tiene almacén asignado',
                    get_the_title($item->producto_id)
                );
                continue;
            }
            
            $stock_disponible = $this->db->get_stock_disponible($item->producto_id, $item->almacen_id);
            
            if ($stock_disponible < $item->cantidad) {
                $errores[] = sprintf(
                    'Stock insuficiente para "%s" en almacén "%s". Disponible: %d, Solicitado: %d',
                    get_the_title($item->producto_id),
                    $item->almacen_nombre,
                    $stock_disponible,
                    $item->cantidad
                );
            }
        }
        
        return empty($errores) ? true : $errores;
    }
    
    /**
     * Limpiar reservas expiradas
     */
    public function limpiar_reservas_expiradas() {
        $this->db->limpiar_reservas_expiradas();
        $this->logger->info('Limpieza de reservas expiradas ejecutada');
    }
    
    /**
     * Obtener resumen de stock para mostrar en cotización
     */
    public function get_resumen_stock_cotizacion($cotizacion_id) {
        if (!$this->is_warehouse_plugin_active()) {
            return array();
        }
        
        $items = $this->db->get_items_cotizacion($cotizacion_id);
        $resumen = array();
        
        foreach ($items as $item) {
            $stock_data = $this->get_stock_por_almacenes($item->producto_id);
            $resumen[$item->producto_id] = array(
                'nombre' => get_the_title($item->producto_id),
                'almacenes' => $stock_data,
                'almacen_seleccionado' => $item->almacen_id,
                'cantidad_cotizada' => $item->cantidad
            );
        }
        
        return $resumen;
    }
}