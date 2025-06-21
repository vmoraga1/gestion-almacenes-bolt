<?php
/**
 * Clase para manejar la base de datos del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_DB {
    
    /**
     * Versión de la base de datos
     */
    private $db_version = '2.0.0';
    
    /**
     * Instancia de wpdb
     */
    private $wpdb;
    
    /**
     * Nombres de las tablas
     */
    private $tabla_cotizaciones;
    private $tabla_cotizaciones_items;
    private $tabla_clientes;
    private $tabla_clientes_meta;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Definir nombres de tablas
        $this->tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
        $this->tabla_cotizaciones_items = $wpdb->prefix . 'mv_cotizaciones_items';
        $this->tabla_clientes = $wpdb->prefix . 'mv_clientes';
        $this->tabla_clientes_meta = $wpdb->prefix . 'mv_clientes_meta';
    }
    
    /**
     * Crear todas las tablas necesarias
     */
    public function crear_tablas() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $this->crear_tabla_clientes();
        $this->crear_tabla_clientes_meta();
        $this->crear_tabla_cotizaciones();
        $this->crear_tabla_cotizaciones_items();
        
        // Actualizar versión de BD
        update_option('modulo_ventas_db_version', $this->db_version);
        
        // Log
        $logger = Modulo_Ventas_Logger::get_instance();
        $logger->log('Tablas de base de datos creadas/actualizadas', 'info');
    }
    
    /**
     * Crear tabla de clientes
     */
    private function crear_tabla_clientes() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->tabla_clientes} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID del usuario de WordPress si existe',
            razon_social varchar(255) NOT NULL COMMENT 'Nombre del cliente o empresa',
            rut varchar(20) NOT NULL COMMENT 'RUT del cliente',
            giro_comercial varchar(255) DEFAULT NULL,
            telefono varchar(50) DEFAULT NULL,
            email varchar(100) DEFAULT NULL,
            email_dte varchar(100) DEFAULT NULL COMMENT 'Email para documentos tributarios',
            direccion_facturacion text DEFAULT NULL,
            comuna_facturacion varchar(100) DEFAULT NULL,
            ciudad_facturacion varchar(100) DEFAULT NULL,
            region_facturacion varchar(100) DEFAULT NULL,
            pais_facturacion varchar(100) DEFAULT 'Chile',
            direccion_envio text DEFAULT NULL,
            comuna_envio varchar(100) DEFAULT NULL,
            ciudad_envio varchar(100) DEFAULT NULL,
            region_envio varchar(100) DEFAULT NULL,
            pais_envio varchar(100) DEFAULT 'Chile',
            usar_direccion_facturacion_para_envio tinyint(1) DEFAULT 1,
            notas text DEFAULT NULL,
            estado varchar(20) DEFAULT 'activo',
            fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            creado_por bigint(20) UNSIGNED DEFAULT NULL,
            modificado_por bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY rut_unique (rut),
            KEY user_id (user_id),
            KEY razon_social (razon_social),
            KEY estado (estado),
            KEY fecha_creacion (fecha_creacion)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de meta datos de clientes
     */
    private function crear_tabla_clientes_meta() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->tabla_clientes_meta} (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cliente_id bigint(20) UNSIGNED NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext DEFAULT NULL,
            PRIMARY KEY (meta_id),
            KEY cliente_id (cliente_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de cotizaciones
     */
    private function crear_tabla_cotizaciones() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->tabla_cotizaciones} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            folio varchar(50) NOT NULL COMMENT 'Número único de cotización',
            cliente_id bigint(20) UNSIGNED NOT NULL,
            fecha datetime DEFAULT CURRENT_TIMESTAMP,
            fecha_expiracion date DEFAULT NULL,
            plazo_pago varchar(50) DEFAULT NULL,
            condiciones_pago text DEFAULT NULL,
            vendedor_id bigint(20) UNSIGNED DEFAULT NULL,
            vendedor_nombre varchar(100) DEFAULT NULL,
            almacen_id int(11) DEFAULT NULL COMMENT 'Almacén principal de la cotización',
            moneda varchar(10) DEFAULT 'CLP',
            tipo_cambio decimal(10,4) DEFAULT 1.0000,
            subtotal decimal(20,2) NOT NULL DEFAULT 0.00,
            descuento_monto decimal(20,2) DEFAULT 0.00,
            descuento_porcentaje decimal(5,2) DEFAULT 0.00,
            tipo_descuento enum('monto','porcentaje') DEFAULT 'monto',
            costo_envio decimal(20,2) DEFAULT 0.00,
            impuesto_monto decimal(20,2) DEFAULT 0.00,
            impuesto_porcentaje decimal(5,2) DEFAULT 19.00 COMMENT 'IVA Chile',
            incluye_iva tinyint(1) DEFAULT 1,
            total decimal(20,2) NOT NULL DEFAULT 0.00,
            observaciones text DEFAULT NULL,
            notas_internas text DEFAULT NULL,
            terminos_condiciones text DEFAULT NULL,
            estado enum('borrador','pendiente','aprobada','rechazada','expirada','convertida') DEFAULT 'pendiente',
            fecha_conversion datetime DEFAULT NULL COMMENT 'Fecha cuando se convirtió a venta',
            venta_id bigint(20) DEFAULT NULL COMMENT 'ID del pedido de WooCommerce si se convirtió',
            fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            creado_por bigint(20) UNSIGNED DEFAULT NULL,
            modificado_por bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY folio_unique (folio),
            KEY cliente_id (cliente_id),
            KEY vendedor_id (vendedor_id),
            KEY almacen_id (almacen_id),
            KEY estado (estado),
            KEY fecha (fecha),
            KEY fecha_expiracion (fecha_expiracion),
            KEY venta_id (venta_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de items de cotización
     */
    private function crear_tabla_cotizaciones_items() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->tabla_cotizaciones_items} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cotizacion_id bigint(20) UNSIGNED NOT NULL,
            producto_id bigint(20) UNSIGNED NOT NULL,
            variacion_id bigint(20) UNSIGNED DEFAULT 0,
            almacen_id int(11) DEFAULT NULL COMMENT 'Almacén del cual se tomará el stock',
            sku varchar(100) DEFAULT NULL,
            nombre varchar(255) NOT NULL,
            descripcion text DEFAULT NULL,
            cantidad decimal(10,2) NOT NULL DEFAULT 1.00,
            precio_unitario decimal(20,2) NOT NULL DEFAULT 0.00,
            precio_original decimal(20,2) DEFAULT NULL COMMENT 'Precio antes de descuentos',
            descuento_monto decimal(20,2) DEFAULT 0.00,
            descuento_porcentaje decimal(5,2) DEFAULT 0.00,
            tipo_descuento enum('monto','porcentaje') DEFAULT 'monto',
            subtotal decimal(20,2) NOT NULL DEFAULT 0.00,
            impuesto_monto decimal(20,2) DEFAULT 0.00,
            impuesto_porcentaje decimal(5,2) DEFAULT 19.00,
            total decimal(20,2) NOT NULL DEFAULT 0.00,
            notas text DEFAULT NULL,
            orden int(11) DEFAULT 0,
            stock_disponible int(11) DEFAULT NULL COMMENT 'Stock al momento de crear la cotización',
            PRIMARY KEY (id),
            KEY cotizacion_id (cotizacion_id),
            KEY producto_id (producto_id),
            KEY variacion_id (variacion_id),
            KEY almacen_id (almacen_id),
            KEY orden (orden)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Verificar si las tablas existen
     */
    public function verificar_tablas() {
        $tablas = array(
            $this->tabla_clientes,
            $this->tabla_clientes_meta,
            $this->tabla_cotizaciones,
            $this->tabla_cotizaciones_items
        );
        
        $tablas_faltantes = array();
        
        foreach ($tablas as $tabla) {
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$tabla'") != $tabla) {
                $tablas_faltantes[] = $tabla;
            }
        }
        
        return $tablas_faltantes;
    }
    
    /**
     * Eliminar todas las tablas
     */
    public function eliminar_tablas() {
        $tablas = array(
            $this->tabla_cotizaciones_items,
            $this->tabla_cotizaciones,
            $this->tabla_clientes_meta,
            $this->tabla_clientes
        );
        
        foreach ($tablas as $tabla) {
            $this->wpdb->query("DROP TABLE IF EXISTS $tabla");
        }
        
        delete_option('modulo_ventas_db_version');
    }
    
    /**
     * Obtener nombres de tablas
     */
    public function get_tabla_clientes() {
        return $this->tabla_clientes;
    }
    
    public function get_tabla_clientes_meta() {
        return $this->tabla_clientes_meta;
    }
    
    public function get_tabla_cotizaciones() {
        return $this->tabla_cotizaciones;
    }
    
    public function get_tabla_cotizaciones_items() {
        return $this->tabla_cotizaciones_items;
    }
    
    /**
     * FUNCIONES CRUD PARA CLIENTES
     */
    
    /**
     * Crear cliente
     */
    public function crear_cliente($datos) {
        // Validar RUT único
        if ($this->cliente_existe_por_rut($datos['rut'])) {
            return new WP_Error('rut_duplicado', __('Ya existe un cliente con este RUT', 'modulo-ventas'));
        }
        
        // Preparar datos
        $datos_insertar = array(
            'razon_social' => sanitize_text_field($datos['razon_social']),
            'rut' => sanitize_text_field($datos['rut']),
            'giro_comercial' => isset($datos['giro_comercial']) ? sanitize_text_field($datos['giro_comercial']) : null,
            'telefono' => isset($datos['telefono']) ? sanitize_text_field($datos['telefono']) : null,
            'email' => isset($datos['email']) ? sanitize_email($datos['email']) : null,
            'email_dte' => isset($datos['email_dte']) ? sanitize_email($datos['email_dte']) : null,
            'direccion_facturacion' => isset($datos['direccion_facturacion']) ? sanitize_textarea_field($datos['direccion_facturacion']) : null,
            'comuna_facturacion' => isset($datos['comuna_facturacion']) ? sanitize_text_field($datos['comuna_facturacion']) : null,
            'ciudad_facturacion' => isset($datos['ciudad_facturacion']) ? sanitize_text_field($datos['ciudad_facturacion']) : null,
            'region_facturacion' => isset($datos['region_facturacion']) ? sanitize_text_field($datos['region_facturacion']) : null,
            'pais_facturacion' => isset($datos['pais_facturacion']) ? sanitize_text_field($datos['pais_facturacion']) : 'Chile',
            'creado_por' => get_current_user_id()
        );
        
        // Si tiene dirección de envío diferente
        if (isset($datos['usar_direccion_facturacion_para_envio']) && !$datos['usar_direccion_facturacion_para_envio']) {
            $datos_insertar['usar_direccion_facturacion_para_envio'] = 0;
            $datos_insertar['direccion_envio'] = sanitize_textarea_field($datos['direccion_envio']);
            $datos_insertar['comuna_envio'] = sanitize_text_field($datos['comuna_envio']);
            $datos_insertar['ciudad_envio'] = sanitize_text_field($datos['ciudad_envio']);
            $datos_insertar['region_envio'] = sanitize_text_field($datos['region_envio']);
            $datos_insertar['pais_envio'] = isset($datos['pais_envio']) ? sanitize_text_field($datos['pais_envio']) : 'Chile';
        }
        
        // Si está vinculado a un usuario de WordPress
        if (isset($datos['user_id']) && $datos['user_id']) {
            $datos_insertar['user_id'] = intval($datos['user_id']);
        }
        
        // Insertar
        $resultado = $this->wpdb->insert($this->tabla_clientes, $datos_insertar);
        
        if ($resultado === false) {
            return new WP_Error('error_db', __('Error al crear el cliente', 'modulo-ventas'));
        }
        
        $cliente_id = $this->wpdb->insert_id;
        
        // Guardar metadatos adicionales si existen
        if (isset($datos['meta']) && is_array($datos['meta'])) {
            foreach ($datos['meta'] as $key => $value) {
                $this->actualizar_cliente_meta($cliente_id, $key, $value);
            }
        }
        
        // Log
        $logger = Modulo_Ventas_Logger::get_instance();
        $logger->log("Cliente creado: ID {$cliente_id}, RUT {$datos['rut']}", 'info');
        
        return $cliente_id;
    }
    
    /**
     * Obtener cliente por ID
     */
    public function obtener_cliente($cliente_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tabla_clientes} WHERE id = %d",
            $cliente_id
        );
        
        $cliente = $this->wpdb->get_row($sql);
        
        if ($cliente) {
            // Obtener metadatos
            $cliente->meta = $this->obtener_cliente_meta($cliente_id);
        }
        
        return $cliente;
    }
    
    /**
     * Obtener cliente por RUT
     */
    public function obtener_cliente_por_rut($rut) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tabla_clientes} WHERE rut = %s",
            $rut
        );
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Verificar si existe cliente por RUT
     */
    public function cliente_existe_por_rut($rut) {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabla_clientes} WHERE rut = %s",
            $rut
        );
        
        return $this->wpdb->get_var($sql) > 0;
    }
    
    /**
     * Actualizar cliente
     */
    public function actualizar_cliente($cliente_id, $datos) {
        $datos['modificado_por'] = get_current_user_id();
        
        // Si se está actualizando el RUT, verificar que no exista
        if (isset($datos['rut'])) {
            $cliente_actual = $this->obtener_cliente($cliente_id);
            if ($cliente_actual && $cliente_actual->rut !== $datos['rut']) {
                if ($this->cliente_existe_por_rut($datos['rut'])) {
                    return new WP_Error('rut_duplicado', __('Ya existe otro cliente con este RUT', 'modulo-ventas'));
                }
            }
        }
        
        $resultado = $this->wpdb->update(
            $this->tabla_clientes,
            $datos,
            array('id' => $cliente_id)
        );
        
        if ($resultado === false) {
            return new WP_Error('error_db', __('Error al actualizar el cliente', 'modulo-ventas'));
        }
        
        return true;
    }
    
    /**
     * Buscar clientes
     */
    public function buscar_clientes($termino = '', $args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'razon_social',
            'order' => 'ASC',
            'estado' => 'activo'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->tabla_clientes} WHERE 1=1";
        $params = array();
        
        // Búsqueda por término
        if (!empty($termino)) {
            $sql .= " AND (razon_social LIKE %s OR rut LIKE %s OR email LIKE %s)";
            $like_term = '%' . $this->wpdb->esc_like($termino) . '%';
            $params[] = $like_term;
            $params[] = $like_term;
            $params[] = $like_term;
        }
        
        // Filtro por estado
        if (!empty($args['estado'])) {
            $sql .= " AND estado = %s";
            $params[] = $args['estado'];
        }
        
        // Ordenamiento
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Límite
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Obtener todos los clientes para select
     */
    public function obtener_clientes_para_select() {
        $sql = "SELECT id, razon_social, rut, email, telefono, direccion_facturacion, giro_comercial 
                FROM {$this->tabla_clientes} 
                WHERE estado = 'activo' 
                ORDER BY razon_social ASC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * FUNCIONES PARA METADATOS DE CLIENTES
     */
    
    /**
     * Actualizar meta de cliente
     */
    public function actualizar_cliente_meta($cliente_id, $meta_key, $meta_value) {
        // Verificar si existe
        $meta_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT meta_id FROM {$this->tabla_clientes_meta} 
             WHERE cliente_id = %d AND meta_key = %s",
            $cliente_id,
            $meta_key
        ));
        
        if ($meta_id) {
            // Actualizar
            return $this->wpdb->update(
                $this->tabla_clientes_meta,
                array('meta_value' => maybe_serialize($meta_value)),
                array('meta_id' => $meta_id)
            );
        } else {
            // Insertar
            return $this->wpdb->insert(
                $this->tabla_clientes_meta,
                array(
                    'cliente_id' => $cliente_id,
                    'meta_key' => $meta_key,
                    'meta_value' => maybe_serialize($meta_value)
                )
            );
        }
    }
    
    /**
     * Obtener meta de cliente
     */
    public function obtener_cliente_meta($cliente_id, $meta_key = '') {
        if (empty($meta_key)) {
            // Obtener todos los meta
            $sql = $this->wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$this->tabla_clientes_meta} 
                 WHERE cliente_id = %d",
                $cliente_id
            );
            
            $results = $this->wpdb->get_results($sql);
            $meta = array();
            
            foreach ($results as $row) {
                $meta[$row->meta_key] = maybe_unserialize($row->meta_value);
            }
            
            return $meta;
        } else {
            // Obtener meta específico
            $sql = $this->wpdb->prepare(
                "SELECT meta_value FROM {$this->tabla_clientes_meta} 
                 WHERE cliente_id = %d AND meta_key = %s",
                $cliente_id,
                $meta_key
            );
            
            $value = $this->wpdb->get_var($sql);
            return $value ? maybe_unserialize($value) : null;
        }
    }
    
    /**
     * Eliminar meta de cliente
     */
    public function eliminar_cliente_meta($cliente_id, $meta_key) {
        return $this->wpdb->delete(
            $this->tabla_clientes_meta,
            array(
                'cliente_id' => $cliente_id,
                'meta_key' => $meta_key
            )
        );
    }
    
    /**
     * FUNCIONES CRUD PARA COTIZACIONES
     */
    
    /**
     * Generar folio único para cotización
     */
    public function generar_folio_cotizacion() {
        $prefijo = get_option('modulo_ventas_prefijo_cotizacion', 'COT');
        $year = date('Y');
        
        // Obtener el último número usado este año
        $sql = $this->wpdb->prepare(
            "SELECT folio FROM {$this->tabla_cotizaciones} 
             WHERE folio LIKE %s 
             ORDER BY id DESC LIMIT 1",
            $prefijo . '-' . $year . '-%'
        );
        
        $ultimo_folio = $this->wpdb->get_var($sql);
        
        if ($ultimo_folio) {
            // Extraer el número
            $partes = explode('-', $ultimo_folio);
            $numero = intval(end($partes)) + 1;
        } else {
            $numero = 1;
        }
        
        return sprintf('%s-%s-%06d', $prefijo, $year, $numero);
    }
    
    /**
     * Crear cotización
     */
    public function crear_cotizacion($datos_generales, $items) {
        // Iniciar transacción
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Validar datos requeridos
            if (empty($datos_generales['cliente_id'])) {
                throw new Exception(__('Cliente es requerido', 'modulo-ventas'));
            }
            
            if (empty($items)) {
                throw new Exception(__('Debe agregar al menos un producto', 'modulo-ventas'));
            }
            
            // Generar folio
            $folio = $this->generar_folio_cotizacion();
            
            // Preparar datos de cotización
            $datos_cotizacion = array(
                'folio' => $folio,
                'cliente_id' => intval($datos_generales['cliente_id']),
                'fecha' => current_time('mysql'),
                'fecha_expiracion' => !empty($datos_generales['fecha_expiracion']) ? $datos_generales['fecha_expiracion'] : null,
                'plazo_pago' => !empty($datos_generales['plazo_pago']) ? sanitize_text_field($datos_generales['plazo_pago']) : null,
                'condiciones_pago' => !empty($datos_generales['condiciones_pago']) ? sanitize_textarea_field($datos_generales['condiciones_pago']) : null,
                'vendedor_id' => !empty($datos_generales['vendedor_id']) ? intval($datos_generales['vendedor_id']) : get_current_user_id(),
                'vendedor_nombre' => !empty($datos_generales['vendedor_nombre']) ? sanitize_text_field($datos_generales['vendedor_nombre']) : wp_get_current_user()->display_name,
                'almacen_id' => !empty($datos_generales['almacen_id']) ? intval($datos_generales['almacen_id']) : null,
                'incluye_iva' => isset($datos_generales['incluye_iva']) ? intval($datos_generales['incluye_iva']) : 1,
                'descuento_monto' => !empty($datos_generales['descuento_monto']) ? floatval($datos_generales['descuento_monto']) : 0,
                'descuento_porcentaje' => !empty($datos_generales['descuento_porcentaje']) ? floatval($datos_generales['descuento_porcentaje']) : 0,
                'tipo_descuento' => !empty($datos_generales['tipo_descuento']) ? $datos_generales['tipo_descuento'] : 'monto',
                'costo_envio' => !empty($datos_generales['costo_envio']) ? floatval($datos_generales['costo_envio']) : 0,
                'observaciones' => !empty($datos_generales['observaciones']) ? sanitize_textarea_field($datos_generales['observaciones']) : null,
                'notas_internas' => !empty($datos_generales['notas_internas']) ? sanitize_textarea_field($datos_generales['notas_internas']) : null,
                'terminos_condiciones' => !empty($datos_generales['terminos_condiciones']) ? sanitize_textarea_field($datos_generales['terminos_condiciones']) : null,
                'estado' => 'pendiente',
                'creado_por' => get_current_user_id()
            );
            
            // Calcular totales
            $subtotal = 0;
            $impuesto_total = 0;
            
            foreach ($items as $item) {
                $subtotal += floatval($item['subtotal']);
                if ($datos_cotizacion['incluye_iva']) {
                    $impuesto_total += floatval($item['subtotal']) * 0.19; // IVA Chile
                }
            }
            
            // Aplicar descuento global
            if ($datos_cotizacion['tipo_descuento'] === 'porcentaje') {
                $descuento = $subtotal * ($datos_cotizacion['descuento_porcentaje'] / 100);
            } else {
                $descuento = $datos_cotizacion['descuento_monto'];
            }
            
            $subtotal_con_descuento = $subtotal - $descuento;
            
            // Calcular total
            $total = $subtotal_con_descuento + $impuesto_total + $datos_cotizacion['costo_envio'];
            
            $datos_cotizacion['subtotal'] = $subtotal;
            $datos_cotizacion['impuesto_monto'] = $impuesto_total;
            $datos_cotizacion['total'] = $total;
            
            // Insertar cotización
            $resultado = $this->wpdb->insert($this->tabla_cotizaciones, $datos_cotizacion);
            
            if ($resultado === false) {
                throw new Exception(__('Error al crear la cotización', 'modulo-ventas'));
            }
            
            $cotizacion_id = $this->wpdb->insert_id;
            
            // Insertar items
            $orden = 0;
            foreach ($items as $item) {
                $datos_item = array(
                    'cotizacion_id' => $cotizacion_id,
                    'producto_id' => intval($item['producto_id']),
                    'variacion_id' => !empty($item['variacion_id']) ? intval($item['variacion_id']) : 0,
                    'almacen_id' => !empty($item['almacen_id']) ? intval($item['almacen_id']) : $datos_cotizacion['almacen_id'],
                    'sku' => !empty($item['sku']) ? sanitize_text_field($item['sku']) : null,
                    'nombre' => sanitize_text_field($item['nombre']),
                    'descripcion' => !empty($item['descripcion']) ? sanitize_textarea_field($item['descripcion']) : null,
                    'cantidad' => floatval($item['cantidad']),
                    'precio_unitario' => floatval($item['precio_unitario']),
                    'precio_original' => !empty($item['precio_original']) ? floatval($item['precio_original']) : floatval($item['precio_unitario']),
                    'descuento_monto' => !empty($item['descuento_monto']) ? floatval($item['descuento_monto']) : 0,
                    'descuento_porcentaje' => !empty($item['descuento_porcentaje']) ? floatval($item['descuento_porcentaje']) : 0,
                    'tipo_descuento' => !empty($item['tipo_descuento']) ? $item['tipo_descuento'] : 'monto',
                    'subtotal' => floatval($item['subtotal']),
                    'impuesto_monto' => $datos_cotizacion['incluye_iva'] ? floatval($item['subtotal']) * 0.19 : 0,
                    'impuesto_porcentaje' => $datos_cotizacion['incluye_iva'] ? 19 : 0,
                    'total' => floatval($item['subtotal']) + ($datos_cotizacion['incluye_iva'] ? floatval($item['subtotal']) * 0.19 : 0),
                    'notas' => !empty($item['notas']) ? sanitize_textarea_field($item['notas']) : null,
                    'orden' => $orden++,
                    'stock_disponible' => !empty($item['stock_disponible']) ? intval($item['stock_disponible']) : null
                );
                
                $resultado_item = $this->wpdb->insert($this->tabla_cotizaciones_items, $datos_item);
                
                if ($resultado_item === false) {
                    throw new Exception(__('Error al guardar los productos', 'modulo-ventas'));
                }
            }
            
            // Confirmar transacción
            $this->wpdb->query('COMMIT');
            
            // Log
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log("Cotización creada: {$folio} (ID: {$cotizacion_id})", 'info');
            
            // Hook para que otros plugins puedan actuar
            do_action('modulo_ventas_cotizacion_creada', $cotizacion_id, $datos_cotizacion, $items);
            
            return $cotizacion_id;
            
        } catch (Exception $e) {
            // Revertir transacción
            $this->wpdb->query('ROLLBACK');
            
            // Log error
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log("Error al crear cotización: " . $e->getMessage(), 'error');
            
            return new WP_Error('crear_cotizacion_error', $e->getMessage());
        }
    }
    
    /**
     * Obtener cotización
     */
    public function obtener_cotizacion($cotizacion_id) {
        $sql = $this->wpdb->prepare(
            "SELECT c.*, cl.razon_social, cl.rut, cl.email 
             FROM {$this->tabla_cotizaciones} c
             LEFT JOIN {$this->tabla_clientes} cl ON c.cliente_id = cl.id
             WHERE c.id = %d",
            $cotizacion_id
        );
        
        $cotizacion = $this->wpdb->get_row($sql);
        
        if ($cotizacion) {
            // Obtener items
            $cotizacion->items = $this->obtener_items_cotizacion($cotizacion_id);
        }
        
        return $cotizacion;
    }
    
    /**
     * Obtener items de cotización
     */
    public function obtener_items_cotizacion($cotizacion_id) {
        $sql = $this->wpdb->prepare(
            "SELECT ci.*, p.post_title as producto_nombre
             FROM {$this->tabla_cotizaciones_items} ci
             LEFT JOIN {$this->wpdb->posts} p ON ci.producto_id = p.ID
             WHERE ci.cotizacion_id = %d
             ORDER BY ci.orden ASC",
            $cotizacion_id
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Actualizar estado de cotización
     */
    public function actualizar_estado_cotizacion($cotizacion_id, $nuevo_estado) {
        $estados_validos = array('borrador', 'pendiente', 'aprobada', 'rechazada', 'expirada', 'convertida');
        
        if (!in_array($nuevo_estado, $estados_validos)) {
            return new WP_Error('estado_invalido', __('Estado no válido', 'modulo-ventas'));
        }
        
        $resultado = $this->wpdb->update(
            $this->tabla_cotizaciones,
            array(
                'estado' => $nuevo_estado,
                'modificado_por' => get_current_user_id()
            ),
            array('id' => $cotizacion_id)
        );
        
        if ($resultado === false) {
            return new WP_Error('error_db', __('Error al actualizar el estado', 'modulo-ventas'));
        }
        
        // Log
        $logger = Modulo_Ventas_Logger::get_instance();
        $logger->log("Estado de cotización {$cotizacion_id} actualizado a: {$nuevo_estado}", 'info');
        
        // Hook
        do_action('modulo_ventas_cotizacion_estado_actualizado', $cotizacion_id, $nuevo_estado);
        
        return true;
    }
    
    /**
     * Verificar y actualizar cotizaciones expiradas
     */
    public function verificar_cotizaciones_expiradas() {
        $sql = "UPDATE {$this->tabla_cotizaciones} 
                SET estado = 'expirada' 
                WHERE estado = 'pendiente' 
                AND fecha_expiracion IS NOT NULL 
                AND fecha_expiracion < CURDATE()";
        
        $actualizadas = $this->wpdb->query($sql);
        
        if ($actualizadas > 0) {
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log("{$actualizadas} cotizaciones marcadas como expiradas", 'info');
        }
        
        return $actualizadas;
    }
    
    /**
     * Obtener cotizaciones con filtros
     */
    public function obtener_cotizaciones($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'fecha',
            'order' => 'DESC',
            'estado' => '',
            'cliente_id' => 0,
            'vendedor_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'buscar' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT c.*, cl.razon_social, cl.rut 
                FROM {$this->tabla_cotizaciones} c
                LEFT JOIN {$this->tabla_clientes} cl ON c.cliente_id = cl.id
                WHERE 1=1";
        
        $params = array();
        
        // Filtros
        if (!empty($args['estado'])) {
            $sql .= " AND c.estado = %s";
            $params[] = $args['estado'];
        }
        
        if ($args['cliente_id'] > 0) {
            $sql .= " AND c.cliente_id = %d";
            $params[] = $args['cliente_id'];
        }
        
        if ($args['vendedor_id'] > 0) {
            $sql .= " AND c.vendedor_id = %d";
            $params[] = $args['vendedor_id'];
        }
        
        if (!empty($args['fecha_desde'])) {
            $sql .= " AND DATE(c.fecha) >= %s";
            $params[] = $args['fecha_desde'];
        }
        
        if (!empty($args['fecha_hasta'])) {
            $sql .= " AND DATE(c.fecha) <= %s";
            $params[] = $args['fecha_hasta'];
        }
        
        if (!empty($args['buscar'])) {
            $sql .= " AND (c.folio LIKE %s OR cl.razon_social LIKE %s OR cl.rut LIKE %s)";
            $like_term = '%' . $this->wpdb->esc_like($args['buscar']) . '%';
            $params[] = $like_term;
            $params[] = $like_term;
            $params[] = $like_term;
        }
        
        // Ordenamiento
        $sql .= " ORDER BY c.{$args['orderby']} {$args['order']}";
        
        // Límite
        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }
        
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Contar cotizaciones
     */
    public function contar_cotizaciones($args = array()) {
        $defaults = array(
            'estado' => '',
            'cliente_id' => 0,
            'vendedor_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'buscar' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT COUNT(*) 
                FROM {$this->tabla_cotizaciones} c
                LEFT JOIN {$this->tabla_clientes} cl ON c.cliente_id = cl.id
                WHERE 1=1";
        
        $params = array();
        
        // Aplicar los mismos filtros que en obtener_cotizaciones
        if (!empty($args['estado'])) {
            $sql .= " AND c.estado = %s";
            $params[] = $args['estado'];
        }
        
        if ($args['cliente_id'] > 0) {
            $sql .= " AND c.cliente_id = %d";
            $params[] = $args['cliente_id'];
        }
        
        if ($args['vendedor_id'] > 0) {
            $sql .= " AND c.vendedor_id = %d";
            $params[] = $args['vendedor_id'];
        }
        
        if (!empty($args['fecha_desde'])) {
            $sql .= " AND DATE(c.fecha) >= %s";
            $params[] = $args['fecha_desde'];
        }
        
        if (!empty($args['fecha_hasta'])) {
            $sql .= " AND DATE(c.fecha) <= %s";
            $params[] = $args['fecha_hasta'];
        }
        
        if (!empty($args['buscar'])) {
            $sql .= " AND (c.folio LIKE %s OR cl.razon_social LIKE %s OR cl.rut LIKE %s)";
            $like_term = '%' . $this->wpdb->esc_like($args['buscar']) . '%';
            $params[] = $like_term;
            $params[] = $like_term;
            $params[] = $like_term;
        }
        
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }
        
        return $this->wpdb->get_var($sql);
    }

    public function obtener_estadisticas_dashboard() {
        return array(
            'cotizaciones_mes' => 0,
            'monto_mes' => 0,
            'conversion_rate' => 0
        );
    }
    
    /**
     * Eliminar cotización
     */
    public function eliminar_cotizacion($cotizacion_id) {
        // Verificar si se puede eliminar
        $cotizacion = $this->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            return new WP_Error('cotizacion_no_existe', __('La cotización no existe', 'modulo-ventas'));
        }
        
        if ($cotizacion->estado === 'convertida') {
            return new WP_Error('cotizacion_convertida', __('No se puede eliminar una cotización convertida en venta', 'modulo-ventas'));
        }
        
        // Iniciar transacción
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Eliminar items
            $this->wpdb->delete(
                $this->tabla_cotizaciones_items,
                array('cotizacion_id' => $cotizacion_id)
            );
            
            // Eliminar cotización
            $resultado = $this->wpdb->delete(
                $this->tabla_cotizaciones,
                array('id' => $cotizacion_id)
            );
            
            if ($resultado === false) {
                throw new Exception(__('Error al eliminar la cotización', 'modulo-ventas'));
            }
            
            // Confirmar transacción
            $this->wpdb->query('COMMIT');
            
            // Log
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log("Cotización eliminada: {$cotizacion->folio} (ID: {$cotizacion_id})", 'info');
            
            // Hook
            do_action('modulo_ventas_cotizacion_eliminada', $cotizacion_id, $cotizacion);
            
            return true;
            
        } catch (Exception $e) {
            // Revertir transacción
            $this->wpdb->query('ROLLBACK');
            
            return new WP_Error('error_eliminar', $e->getMessage());
        }
    }
}