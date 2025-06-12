<?php
if (!defined('ABSPATH')) {
    exit;
}

$logger = Ventas_Logger::get_instance();
$logger->log('Archivo db.php cargado.', 'debug');

class Ventas_DB {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Función para crear las tablas de la base de datos
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de cotizaciones
        $table_cotizaciones = $wpdb->prefix . 'ventas_cotizaciones';
        $sql_cotizaciones = "CREATE TABLE IF NOT EXISTS $table_cotizaciones (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            folio varchar(50) NOT NULL UNIQUE,
            cliente_id bigint(20) NOT NULL,
            fecha datetime DEFAULT CURRENT_TIMESTAMP,
            fecha_expiracion date DEFAULT NULL,
            plazo_pago varchar(50) DEFAULT NULL,
            plazo_credito int DEFAULT NULL,
            vendedor varchar(100) DEFAULT NULL,
            condiciones_pago text DEFAULT NULL,
            subtotal decimal(10,2) NOT NULL,
            descuento_global decimal(10,2) DEFAULT 0,
            tipo_descuento_global varchar(20) DEFAULT NULL,
            envio decimal(10,2) DEFAULT 0,
            incluye_iva tinyint(1) DEFAULT 0,
            total decimal(10,2) NOT NULL,
            estado varchar(50) DEFAULT 'pendiente',
            orden_id bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY cliente_id (cliente_id),
            KEY estado (estado)
        ) $charset_collate;";

        // Tabla de items de cotización
        $table_items = $wpdb->prefix . 'ventas_cotizaciones_items';
        $sql_items = "CREATE TABLE IF NOT EXISTS $table_items (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cotizacion_id bigint(20) NOT NULL,
            producto_id bigint(20) NOT NULL,
            cantidad int NOT NULL,
            precio_unitario decimal(10,2) NOT NULL,
            descuento decimal(10,2) DEFAULT 0,
            total decimal(10,2) NOT NULL,
            PRIMARY KEY  (id),
            KEY cotizacion_id (cotizacion_id),
            KEY producto_id (producto_id),
            FOREIGN KEY (cotizacion_id) REFERENCES $table_cotizaciones(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_cotizaciones);
        dbDelta($sql_items);
    }

    // Funciones CRUD para cotizaciones
    public function get_cotizacion($id) {
        global $wpdb;
        $logger = Ventas_Logger::get_instance(); // Asegúrate de tener una instancia del logger
        $table = $wpdb->prefix . 'ventas_cotizaciones';
    
        // Convertir el ID a entero explícitamente (aunque ya se hace en ver-cotizacion.php, es buena práctica aquí también)
        $cotizacion_id = intval($id);
    
        $logger->log("get_cotizacion() - Intentando obtener cotización con ID: {$cotizacion_id}", 'debug');
    
        if (!$cotizacion_id) {
             $logger->log("get_cotizacion() - ID de cotización inválido o 0: {$id}", 'error');
             return false; // Retornar false si el ID es inválido
        }
    
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $cotizacion_id);
    
        $logger->log("get_cotizacion() - Consulta SQL: {$sql}", 'debug');
    
        $cotizacion = $wpdb->get_row($sql);
    
        $logger->log("get_cotizacion() - Resultado de get_row: " . print_r($cotizacion, true), 'debug');
        if ($wpdb->last_error) {
             $logger->log("get_cotizacion() - Error SQL: " . $wpdb->last_error, 'error');
             // Opcionalmente, podrías lanzar una excepción aquí si quieres que el error sea más visible
        }
    
        return $cotizacion; // Esto será false si no se encontró nada
    }

    public function get_items_cotizacion($cotizacion_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_cotizaciones_items';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE cotizacion_id = %d", $cotizacion_id));
    }

    public function crear_cotizacion($datos_generales, $productos) {
        global $wpdb;
        $wpdb->show_errors();
        
        // Logging detallado
        $logger = Ventas_Logger::get_instance();
        $logger->log('Iniciando creación de cotización desde el plugin', 'info');
        $logger->log('Datos generales recibidos: ' . print_r($datos_generales, true), 'debug');
        $logger->log('Productos recibidos: ' . print_r($productos, true), 'debug');
    
        // Verificar que los datos requeridos estén presentes
        $campos_requeridos = ['cliente_id', 'subtotal', 'total'];
        foreach ($campos_requeridos as $campo) {
            if (!isset($datos_generales[$campo])) {
                $logger->log("Campo requerido faltante: {$campo}", 'error');
                throw new Exception("Campo requerido faltante: {$campo}");
            }
        }
    
        $wpdb->query('START TRANSACTION');

        try {
            // Verificar datos requeridos
            if (empty($datos_generales['cliente_id'])) {
                throw new Exception('ID de cliente no proporcionado');
            }
    
            // Generar folio
            $folio = $this->generar_folio();
            $logger->log('Folio generado: ' . $folio, 'debug');
    
            // Preparar datos para inserción
            $datos_insercion = array(
                'folio' => $folio,
                'cliente_id' => $datos_generales['cliente_id'],
                'fecha' => current_time('mysql'),
                'subtotal' => $datos_generales['subtotal'],
                'total' => $datos_generales['total'],
                'estado' => 'pendiente'
            );
    
            // Campos opcionales
            $campos_opcionales = ['fecha_expiracion', 'plazo_pago', 'plazo_credito', 'vendedor', 
                                'condiciones_pago', 'descuento_global', 'tipo_descuento_global', 
                                'envio', 'incluye_iva'];
            
            foreach ($campos_opcionales as $campo) {
                if (isset($datos_generales[$campo])) {
                    $datos_insercion[$campo] = $datos_generales[$campo];
                }
            }
    
            $logger->log('Datos finales preparados para inserción en BD: ' . print_r($datos_insercion, true), 'debug'); 
    
            // Insertar cotización
            $resultado = $wpdb->insert(
                $wpdb->prefix . 'ventas_cotizaciones',
                $datos_insercion,
                array_merge(
                    array('%s', '%d', '%s', '%f', '%f', '%s'),
                    array_fill(0, count($campos_opcionales), '%s')
                )
            );

            if ($resultado === false) {
                $logger->log('Error en inserción: ' . $wpdb->last_error, 'error');
                throw new Exception('Error al crear la cotización: ' . $wpdb->last_error);
            }
    
            $cotizacion_id = $wpdb->insert_id;
            $logger->log('Cotización creada con ID: ' . $cotizacion_id, 'success');

            // Insertar productos
            foreach ($productos as $producto) {
                // Preparar datos del item
                $item_data = array(
                    'cotizacion_id' => $cotizacion_id,
                    'producto_id' => $producto['producto_id'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio_unitario'],
                    'descuento' => $producto['descuento'],
                    'total' => $producto['total']
                );
                
                // Agregar campos de almacén si están presentes
                if (isset($producto['almacen_id']) && !empty($producto['almacen_id'])) {
                    $item_data['almacen_id'] = $producto['almacen_id'];
                    $item_data['almacen_nombre'] = isset($producto['almacen_nombre']) ? $producto['almacen_nombre'] : '';
                }
                
                // Agregar stock disponible si está presente
                if (isset($producto['stock_disponible'])) {
                    $item_data['stock_disponible'] = $producto['stock_disponible'];
                }
                
                // Insertar el item
                $wpdb->insert(
                    $table_items,
                    $item_data,
                    array('%d', '%d', '%d', '%f', '%f', '%f', '%d', '%s', '%d')
                );
                
                // Si se configuró para reservar stock
                $reservar_stock = $this->get_config('reservar_stock_cotizacion', '0');
                if ($reservar_stock == '1' && !empty($producto['almacen_id'])) {
                    $this->crear_reserva_stock(
                        $cotizacion_id,
                        $producto['producto_id'],
                        $producto['almacen_id'],
                        $producto['cantidad']
                    );
                }
            }

            $wpdb->query('COMMIT');
            $logger->log('Transacción completada exitosamente', 'success');
            return $cotizacion_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $logger->log('Error en crear_cotizacion: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    private function generar_folio() {
        global $wpdb;
        $prefix = 'COT-';
        $year = date('Y');
        $table = $wpdb->prefix . 'ventas_cotizaciones';
        
        $ultimo_folio = $wpdb->get_var($wpdb->prepare(
            "SELECT folio FROM $table WHERE folio LIKE %s ORDER BY id DESC LIMIT 1",
            $prefix . $year . '%'
        ));

        if ($ultimo_folio) {
            $numero = intval(substr($ultimo_folio, -4)) + 1;
        } else {
            $numero = 1;
        }

        return $prefix . $year . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    public function actualizar_cotizacion($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_cotizaciones';
        return $wpdb->update($table, $data, array('id' => $id));
    }

    public function eliminar_cotizacion($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_cotizaciones';
        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    /**
     * Actualizar tablas para integración con almacenes
     */
    public function update_tables_for_warehouses() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar si las columnas ya existen
        $table_items = $wpdb->prefix . 'ventas_cotizaciones_items';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_items");
        $existing_columns = array_map(function($col) { return $col->Field; }, $columns);
        
        // Agregar columnas de almacén si no existen
        if (!in_array('almacen_id', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_items 
                ADD COLUMN almacen_id INT(11) DEFAULT NULL AFTER producto_id,
                ADD COLUMN almacen_nombre VARCHAR(100) DEFAULT NULL AFTER almacen_id,
                ADD INDEX idx_almacen_id (almacen_id)");
        }
        
        if (!in_array('stock_disponible', $existing_columns)) {
            $wpdb->query("ALTER TABLE $table_items 
                ADD COLUMN stock_disponible INT(11) DEFAULT 0 AFTER total,
                ADD COLUMN stock_reservado TINYINT(1) DEFAULT 0 AFTER stock_disponible");
        }
        
        // Crear tabla de reservas
        $sql_reservas = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ventas_reservas_stock (
            id INT(11) NOT NULL AUTO_INCREMENT,
            cotizacion_id INT(11) NOT NULL,
            producto_id INT(11) NOT NULL,
            almacen_id INT(11) NOT NULL,
            cantidad_reservada INT(11) NOT NULL DEFAULT 0,
            fecha_reserva DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_expiracion DATETIME DEFAULT NULL,
            estado VARCHAR(20) DEFAULT 'activa',
            PRIMARY KEY (id),
            INDEX idx_cotizacion (cotizacion_id),
            INDEX idx_producto_almacen (producto_id, almacen_id),
            INDEX idx_estado_fecha (estado, fecha_expiracion)
        ) $charset_collate;";
        
        // Crear tabla de configuración
        $sql_config = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ventas_configuracion (
            id INT(11) NOT NULL AUTO_INCREMENT,
            clave VARCHAR(100) NOT NULL,
            valor TEXT,
            PRIMARY KEY (id),
            UNIQUE KEY idx_clave (clave)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_reservas);
        dbDelta($sql_config);
        
        // Insertar configuraciones por defecto
        $this->set_default_config();
    }

    /**
     * Establecer configuración por defecto
     */
    private function set_default_config() {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_configuracion';
        
        $defaults = array(
            'almacen_predeterminado' => null,
            'validar_stock_cotizacion' => '1',
            'reservar_stock_cotizacion' => '0',
            'tiempo_reserva_horas' => '24',
            'permitir_sobreventa' => '0'
        );
        
        foreach ($defaults as $clave => $valor) {
            $wpdb->insert(
                $table,
                array('clave' => $clave, 'valor' => $valor),
                array('%s', '%s')
            );
        }
    }

    /**
     * Obtener configuración
     */
    public function get_config($key, $default = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_configuracion';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT valor FROM $table WHERE clave = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }

    /**
     * Actualizar configuración
     */
    public function update_config($key, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_configuracion';
        
        return $wpdb->update(
            $table,
            array('valor' => $value),
            array('clave' => $key),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Funciones para manejo de reservas de stock
     */
    public function crear_reserva_stock($cotizacion_id, $producto_id, $almacen_id, $cantidad) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_reservas_stock';
        
        // Calcular fecha de expiración
        $horas_reserva = $this->get_config('tiempo_reserva_horas', 24);
        $fecha_expiracion = date('Y-m-d H:i:s', strtotime("+{$horas_reserva} hours"));
        
        return $wpdb->insert(
            $table,
            array(
                'cotizacion_id' => $cotizacion_id,
                'producto_id' => $producto_id,
                'almacen_id' => $almacen_id,
                'cantidad_reservada' => $cantidad,
                'fecha_expiracion' => $fecha_expiracion,
                'estado' => 'activa'
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s')
        );
    }

    /**
     * Liberar reservas de una cotización
     */
    public function liberar_reservas_cotizacion($cotizacion_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_reservas_stock';
        
        return $wpdb->update(
            $table,
            array('estado' => 'liberada'),
            array('cotizacion_id' => $cotizacion_id, 'estado' => 'activa'),
            array('%s'),
            array('%d', '%s')
        );
    }

    /**
     * Obtener stock disponible considerando reservas
     */
    public function get_stock_disponible($producto_id, $almacen_id) {
        global $wpdb, $gestion_almacenes_db;
        
        // Verificar si el plugin de almacenes está activo
        if (!class_exists('Gestion_Almacenes_DB')) {
            return 0;
        }
        
        // Obtener stock actual del almacén
        $stock_actual = $gestion_almacenes_db->get_warehouse_stock($almacen_id, $producto_id);
        
        // Obtener reservas activas
        $reservas = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(cantidad_reservada) 
            FROM {$wpdb->prefix}ventas_reservas_stock 
            WHERE producto_id = %d 
            AND almacen_id = %d 
            AND estado = 'activa'
            AND fecha_expiracion > NOW()",
            $producto_id,
            $almacen_id
        ));
        
        $reservas = $reservas ? intval($reservas) : 0;
        
        return max(0, $stock_actual - $reservas);
    }

    /**
     * Limpiar reservas expiradas
     */
    public function limpiar_reservas_expiradas() {
        global $wpdb;
        $table = $wpdb->prefix . 'ventas_reservas_stock';
        
        return $wpdb->query(
            "UPDATE $table 
            SET estado = 'expirada' 
            WHERE estado = 'activa' 
            AND fecha_expiracion < NOW()"
        );
    }
}