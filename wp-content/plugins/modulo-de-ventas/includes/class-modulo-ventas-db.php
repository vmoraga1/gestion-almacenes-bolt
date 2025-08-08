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
    private $db_version = '2.1.0';
    
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
    private $tabla_clientes_notas;
    
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
        $this->tabla_clientes_notas = $wpdb->prefix . 'mv_clientes_notas';

    }
    
    /**
     * Verificar y actualizar estructura de base de datos
     */
    public function verificar_y_actualizar_db() {
        $version_actual = get_option('modulo_ventas_db_version', '0');
        
        if (version_compare($version_actual, $this->db_version, '<')) {
            $this->ejecutar_migraciones($version_actual);
            update_option('modulo_ventas_db_version', $this->db_version);
        }
    }
    
    /**
     * Ejecutar migraciones necesarias
     */
    private function ejecutar_migraciones($desde_version) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Migración inicial - Crear todas las tablas
        if (version_compare($desde_version, '2.0.0', '<')) {
            $this->crear_tablas();
        }
        
        // Migración 2.1.0 - Agregar campos faltantes
        if (version_compare($desde_version, '2.1.0', '<')) {
            $this->migrar_a_2_1_0();
        }
        
        // Log de migración completada
        $logger = Modulo_Ventas_Logger::get_instance();
        $logger->log("Base de datos actualizada a versión {$this->db_version}", 'info');
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
        $this->crear_tabla_clientes_notas();
        
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
            sitio_web varchar(255) DEFAULT NULL,
            email_dte varchar(100) DEFAULT NULL COMMENT 'Email para documentos tributarios',
            direccion_facturacion text DEFAULT NULL,
            comuna_facturacion varchar(100) DEFAULT NULL,
            ciudad_facturacion varchar(100) DEFAULT NULL,
            region_facturacion varchar(100) DEFAULT NULL,
            codigo_postal_facturacion varchar(20) DEFAULT NULL,
            pais_facturacion varchar(100) DEFAULT 'Chile',
            direccion_envio text DEFAULT NULL,
            comuna_envio varchar(100) DEFAULT NULL,
            ciudad_envio varchar(100) DEFAULT NULL,
            region_envio varchar(100) DEFAULT NULL,
            codigo_postal_envio varchar(20) DEFAULT NULL,
            pais_envio varchar(100) DEFAULT 'Chile',
            usar_direccion_facturacion_para_envio tinyint(1) DEFAULT 1,
            credito_autorizado decimal(10,2) DEFAULT 0.00,
            credito_disponible decimal(10,2) DEFAULT 0.00,
            notas text DEFAULT NULL,
            estado varchar(20) DEFAULT 'activo',
            fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
     * Crear tabla de notas de clientes
     */
    private function crear_tabla_clientes_notas() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->tabla_clientes_notas} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            cliente_id bigint(20) UNSIGNED NOT NULL,
            nota text NOT NULL,
            tipo varchar(50) DEFAULT 'general' COMMENT 'general, llamada, reunion, seguimiento, etc',
            es_privada tinyint(1) DEFAULT 0 COMMENT 'Si es privada, solo la ve quien la creó',
            creado_por bigint(20) UNSIGNED NOT NULL COMMENT 'ID del usuario que creó la nota',
            fecha_creacion datetime DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cliente_id (cliente_id),
            KEY creado_por (creado_por),
            KEY tipo (tipo),
            KEY fecha_creacion (fecha_creacion)
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
     * Migración a versión 2.1.0 - AJUSTADA para tu estructura
     */
    private function migrar_a_2_1_0() {
        // Log inicio de migración
        error_log('Módulo Ventas: Iniciando migración a 2.1.0');
        
        // ========================================
        // ACTUALIZAR TABLA DE CLIENTES
        // ========================================
        
        // Los campos que necesitamos agregar a clientes (si no existen)
        $this->agregar_columna_si_no_existe(
            $this->tabla_clientes,
            'sitio_web',
            'VARCHAR(255) DEFAULT NULL AFTER email'
        );
        
        $this->agregar_columna_si_no_existe(
            $this->tabla_clientes,
            'ciudad',
            'VARCHAR(100) DEFAULT NULL AFTER direccion_facturacion'
        );
        
        $this->agregar_columna_si_no_existe(
            $this->tabla_clientes,
            'region',
            'VARCHAR(100) DEFAULT NULL AFTER ciudad'
        );
        
        $this->agregar_columna_si_no_existe(
            $this->tabla_clientes,
            'codigo_postal',
            'VARCHAR(20) DEFAULT NULL AFTER region'
        );
        
        $this->agregar_columna_si_no_existe(
            $this->tabla_clientes,
            'credito_autorizado',
            'DECIMAL(10,2) DEFAULT 0.00 AFTER usar_direccion_facturacion_para_envio'
        );
        
        // Manejar fecha_actualizacion/fecha_modificacion en clientes
        $columnas_clientes = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->tabla_clientes}");
        if (!in_array('fecha_actualizacion', $columnas_clientes) && !in_array('fecha_modificacion', $columnas_clientes)) {
            $this->agregar_columna_si_no_existe(
                $this->tabla_clientes,
                'fecha_actualizacion',
                'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            );
        }
        
        // ========================================
        // ACTUALIZAR TABLA DE COTIZACIONES
        // ========================================
        
        // La estructura ya tiene fecha_modificacion, necesitamos crear un alias fecha_actualizacion
        // para compatibilidad con el código que busca fecha_actualizacion
        $columnas_cotizaciones = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->tabla_cotizaciones}");
        
        if (!in_array('fecha_actualizacion', $columnas_cotizaciones) && in_array('fecha_modificacion', $columnas_cotizaciones)) {
            // Crear una vista o columna virtual fecha_actualizacion que apunte a fecha_modificacion
            // O mejor aún, actualizar el código para usar fecha_modificacion
            // Por ahora, agreguemos un alias
            $this->wpdb->query("ALTER TABLE {$this->tabla_cotizaciones} ADD COLUMN fecha_actualizacion DATETIME GENERATED ALWAYS AS (fecha_modificacion) VIRTUAL");
            
            // Si la BD no soporta columnas virtuales, entonces copiar el valor
            if ($this->wpdb->last_error) {
                $this->wpdb->query("ALTER TABLE {$this->tabla_cotizaciones} ADD COLUMN fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                $this->wpdb->query("UPDATE {$this->tabla_cotizaciones} SET fecha_actualizacion = fecha_modificacion");
            }
        }
        
        // ========================================
        // ACTUALIZAR TABLA DE ITEMS
        // ========================================
        
        // Estructura usa 'nombre' en lugar de 'producto_nombre'
        // Necesitamos crear un alias o actualizar las consultas
        $columnas_items = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->tabla_cotizaciones_items}");
        
        if (!in_array('producto_nombre', $columnas_items) && in_array('nombre', $columnas_items)) {
            // El campo existe como 'nombre', no necesitamos agregarlo
            // Pero debemos actualizar las consultas para usar 'nombre' en lugar de 'producto_nombre'
            error_log('Nota: La tabla usa "nombre" en lugar de "producto_nombre"');
        }
        
        // Verificar que los campos de totales existan (tu estructura ya los tiene)
        $campos_requeridos = array(
            'cantidad' => true,
            'precio_unitario' => true,
            'subtotal' => true,
            'total' => true
        );
        
        foreach ($campos_requeridos as $campo => $requerido) {
            if (!in_array($campo, $columnas_items)) {
                error_log("ADVERTENCIA: Campo $campo no encontrado en items de cotización");
            }
        }
        
        // Copiar datos de columnas _facturacion a columnas simples si es necesario
        $this->copiar_datos_facturacion_si_necesario();
        
        // Llenar nombres de productos si están vacíos
        $this->actualizar_nombres_productos_con_campo_correcto();
        
        error_log('Módulo Ventas: Migración a 2.1.0 completada');
    }

    /**
     * Actualizar nombres de productos usando el campo correcto
     */
    private function actualizar_nombres_productos_con_campo_correcto() {
        // Verificar qué campo existe
        $columnas = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->tabla_cotizaciones_items}");
        
        $campo_nombre = in_array('nombre', $columnas) ? 'nombre' : 'producto_nombre';
        
        $items_sin_nombre = $this->wpdb->get_results(
            "SELECT DISTINCT producto_id 
            FROM {$this->tabla_cotizaciones_items} 
            WHERE $campo_nombre IS NULL OR $campo_nombre = ''"
        );
        
        foreach ($items_sin_nombre as $item) {
            if ($item->producto_id > 0) {
                $producto = wc_get_product($item->producto_id);
                if ($producto) {
                    $this->wpdb->update(
                        $this->tabla_cotizaciones_items,
                        array($campo_nombre => $producto->get_name()),
                        array('producto_id' => $item->producto_id),
                        array('%s'),
                        array('%d')
                    );
                }
            }
        }
    }
    
    /**
     * Helper para agregar columna si no existe
     */
    private function agregar_columna_si_no_existe($tabla, $columna, $definicion) {
        $columnas = $this->wpdb->get_col("SHOW COLUMNS FROM $tabla");
        
        if (!in_array($columna, $columnas)) {
            $sql = "ALTER TABLE $tabla ADD COLUMN $columna $definicion";
            $resultado = $this->wpdb->query($sql);
            
            if ($this->wpdb->last_error) {
                error_log("Error agregando columna $columna a $tabla: " . $this->wpdb->last_error);
            } else {
                error_log("Columna $columna agregada exitosamente a $tabla");
            }
        }
    }
    
    /**
     * Copiar datos de columnas _facturacion a columnas simples
     */
    private function copiar_datos_facturacion_si_necesario() {
        // Copiar ciudad_facturacion a ciudad si está vacía
        $this->wpdb->query(
            "UPDATE {$this->tabla_clientes} 
            SET ciudad = ciudad_facturacion 
            WHERE (ciudad IS NULL OR ciudad = '') 
            AND ciudad_facturacion IS NOT NULL"
        );
        
        // Copiar region_facturacion a region si está vacía
        $this->wpdb->query(
            "UPDATE {$this->tabla_clientes} 
            SET region = region_facturacion 
            WHERE (region IS NULL OR region = '') 
            AND region_facturacion IS NOT NULL"
        );
    }
    
    /**
     * Actualizar nombres de productos vacíos
     */
    private function actualizar_nombres_productos() {
        $items_sin_nombre = $this->wpdb->get_results(
            "SELECT DISTINCT producto_id 
            FROM {$this->tabla_cotizaciones_items} 
            WHERE producto_nombre IS NULL OR producto_nombre = ''"
        );
        
        foreach ($items_sin_nombre as $item) {
            if ($item->producto_id > 0) {
                $producto = wc_get_product($item->producto_id);
                if ($producto) {
                    $this->wpdb->update(
                        $this->tabla_cotizaciones_items,
                        array('producto_nombre' => $producto->get_name()),
                        array('producto_id' => $item->producto_id),
                        array('%s'),
                        array('%d')
                    );
                }
            }
        }
    }
    
    /**
     * Verificar si las tablas existen
     */
    public function verificar_tablas() {
        $tablas = array(
            $this->tabla_clientes,
            $this->tabla_clientes_meta,
            $this->tabla_cotizaciones,
            $this->tabla_cotizaciones_items,
            $this->tabla_clientes_notas
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

    public function get_tabla_clientes_notas() {
        return $this->tabla_clientes_notas;
    }
    
    /**
     * FUNCIONES CRUD PARA CLIENTES
     */
    
    /**
     * Crear cliente
     */
    public function crear_cliente($datos) {
        // Validar datos obligatorios
        if (empty($datos['razon_social']) || empty($datos['rut'])) {
            return new WP_Error('datos_faltantes', __('Razón social y RUT son obligatorios', 'modulo-ventas'));
        }
        
        // IMPORTANTE: Limpiar y validar RUT antes de guardar
        $rut_limpio = mv_limpiar_rut($datos['rut']);
        
        // Validar RUT
        if (!mv_validar_rut($rut_limpio)) {
            return new WP_Error('rut_invalido', __('El RUT ingresado no es válido', 'modulo-ventas'));
        }
        
        // Verificar si ya existe
        if ($this->cliente_existe_por_rut($rut_limpio)) {
            return new WP_Error('rut_duplicado', __('Ya existe un cliente con este RUT', 'modulo-ventas'));
        }
        
        // Preparar datos para inserción - ESTRUCTURA SIMPLIFICADA
        $datos_insercion = array(
            'razon_social' => sanitize_text_field($datos['razon_social']),
            'rut' => $rut_limpio,
            'giro_comercial' => isset($datos['giro_comercial']) ? sanitize_text_field($datos['giro_comercial']) : '',
            'telefono' => isset($datos['telefono']) ? sanitize_text_field($datos['telefono']) : '',
            'email' => isset($datos['email']) ? sanitize_email($datos['email']) : '',
            'sitio_web' => isset($datos['sitio_web']) ? esc_url_raw($datos['sitio_web']) : '',
            'email_dte' => isset($datos['email_dte']) ? sanitize_email($datos['email_dte']) : '',
            'direccion_facturacion' => isset($datos['direccion_facturacion']) ? sanitize_textarea_field($datos['direccion_facturacion']) : '',
            'comuna_facturacion' => isset($datos['comuna_facturacion']) ? sanitize_text_field($datos['comuna_facturacion']) : '',
            'ciudad_facturacion' => isset($datos['ciudad_facturacion']) ? sanitize_text_field($datos['ciudad_facturacion']) : '',
            'region_facturacion' => isset($datos['region_facturacion']) ? sanitize_text_field($datos['region_facturacion']) : '',
            'codigo_postal_facturacion' => isset($datos['codigo_postal_facturacion']) ? sanitize_text_field($datos['codigo_postal_facturacion']) : '',
            'pais_facturacion' => isset($datos['pais_facturacion']) ? sanitize_text_field($datos['pais_facturacion']) : 'Chile',
            'credito_autorizado' => isset($datos['credito_autorizado']) ? floatval($datos['credito_autorizado']) : 0,
            'credito_disponible' => isset($datos['credito_disponible']) ? floatval($datos['credito_disponible']) : 0,
            'estado' => isset($datos['estado']) ? sanitize_text_field($datos['estado']) : 'activo',
            'creado_por' => get_current_user_id(),
            'fecha_creacion' => current_time('mysql')
        );
        
        // Manejar los campos de compatibilidad (ciudad, region, codigo_postal sin _facturacion)
        // Si vienen estos campos, mapearlos a los campos _facturacion
        if (isset($datos['ciudad']) && !isset($datos['ciudad_facturacion'])) {
            $datos_insercion['ciudad_facturacion'] = sanitize_text_field($datos['ciudad']);
        }
        if (isset($datos['region']) && !isset($datos['region_facturacion'])) {
            $datos_insercion['region_facturacion'] = sanitize_text_field($datos['region']);
        }
        if (isset($datos['codigo_postal']) && !isset($datos['codigo_postal_facturacion'])) {
            $datos_insercion['codigo_postal_facturacion'] = sanitize_text_field($datos['codigo_postal']);
        }
        
        // Si se proporciona usar_direccion_facturacion_para_envio
        if (isset($datos['usar_direccion_facturacion_para_envio'])) {
            $datos_insercion['usar_direccion_facturacion_para_envio'] = (int) $datos['usar_direccion_facturacion_para_envio'];
        }
        
        // Si no usa la misma dirección, agregar campos de envío
        if (isset($datos['usar_direccion_facturacion_para_envio']) && !$datos['usar_direccion_facturacion_para_envio']) {
            $campos_envio = array('direccion_envio', 'comuna_envio', 'ciudad_envio', 'region_envio', 'codigo_postal_envio', 'pais_envio');
            foreach ($campos_envio as $campo) {
                if (isset($datos[$campo])) {
                    $datos_insercion[$campo] = sanitize_text_field($datos[$campo]);
                }
            }
        }
        
        // Si se proporciona user_id
        if (!empty($datos['user_id'])) {
            $datos_insercion['user_id'] = intval($datos['user_id']);
        }
        
        // Insertar en base de datos
        $resultado = $this->wpdb->insert(
            $this->tabla_clientes,
            $datos_insercion
        );
        
        if ($resultado === false) {
            error_log('Error SQL: ' . $this->wpdb->last_error);
            return new WP_Error('error_db', __('Error al crear el cliente en la base de datos: ', 'modulo-ventas') . $this->wpdb->last_error);
        }
        
        $cliente_id = $this->wpdb->insert_id;
        
        // Log de actividad
        if (class_exists('Modulo_Ventas_Logger')) {
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log("Cliente creado: {$datos_insercion['razon_social']} (ID: {$cliente_id})", 'info');
        }
        
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
            // Asegurar que todos los campos esperados existan
            $campos_predeterminados = array(
                'sitio_web' => '',
                'ciudad' => '',
                'codigo_postal' => '',
                'credito_autorizado' => 0,
                'fecha_actualizacion' => null
            );
            
            foreach ($campos_predeterminados as $campo => $valor_predeterminado) {
                if (!property_exists($cliente, $campo)) {
                    $cliente->$campo = $valor_predeterminado;
                }
            }
            
            // Si existe ciudad_facturacion pero no ciudad, usar ciudad_facturacion
            if (empty($cliente->ciudad) && !empty($cliente->ciudad_facturacion)) {
                $cliente->ciudad = $cliente->ciudad_facturacion;
            }
            
            // Si existe fecha_modificacion pero no fecha_actualizacion, usar fecha_modificacion
            if (empty($cliente->fecha_actualizacion) && !empty($cliente->fecha_modificacion)) {
                $cliente->fecha_actualizacion = $cliente->fecha_modificacion;
            }
            
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
     * Crear nota de cliente
     */
    public function crear_nota_cliente($cliente_id, $nota, $tipo = 'general', $es_privada = false) {
        // Validar datos
        if (empty($cliente_id) || empty($nota)) {
            return new WP_Error('datos_faltantes', __('Cliente y nota son obligatorios', 'modulo-ventas'));
        }
        
        // Verificar que el cliente existe
        if (!$this->obtener_cliente($cliente_id)) {
            return new WP_Error('cliente_invalido', __('El cliente especificado no existe', 'modulo-ventas'));
        }
        
        $datos = array(
            'cliente_id' => intval($cliente_id),
            'nota' => sanitize_textarea_field($nota),
            'tipo' => sanitize_text_field($tipo),
            'es_privada' => intval($es_privada),
            'creado_por' => get_current_user_id()
        );
        
        $formatos = array('%d', '%s', '%s', '%d', '%d');
        
        $resultado = $this->wpdb->insert($this->tabla_clientes_notas, $datos, $formatos);
        
        if ($resultado === false) {
            return new WP_Error('error_db', __('Error al crear la nota', 'modulo-ventas'));
        }
        
        return $this->wpdb->insert_id;
    }

    /**
     * Obtener notas de un cliente
     */
    public function obtener_notas_cliente($cliente_id, $incluir_privadas = true) {
        $usuario_actual = get_current_user_id();
        
        $sql = "SELECT n.*, u.display_name as autor_nombre 
                FROM {$this->tabla_clientes_notas} n
                LEFT JOIN {$this->wpdb->users} u ON n.creado_por = u.ID
                WHERE n.cliente_id = %d";
        
        // Si no incluir privadas, solo mostrar las públicas o las propias
        if (!$incluir_privadas) {
            $sql .= " AND (n.es_privada = 0 OR n.creado_por = %d)";
            $sql = $this->wpdb->prepare($sql, $cliente_id, $usuario_actual);
        } else {
            $sql = $this->wpdb->prepare($sql, $cliente_id);
        }
        
        $sql .= " ORDER BY n.fecha_creacion DESC";
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Actualizar nota
     */
    public function actualizar_nota_cliente($nota_id, $texto_nota, $tipo = null, $es_privada = null) {
        $datos = array(
            'nota' => sanitize_textarea_field($texto_nota),
            'fecha_actualizacion' => current_time('mysql')
        );
        $formatos = array('%s', '%s');
        
        if ($tipo !== null) {
            $datos['tipo'] = sanitize_text_field($tipo);
            $formatos[] = '%s';
        }
        
        if ($es_privada !== null) {
            $datos['es_privada'] = intval($es_privada);
            $formatos[] = '%d';
        }
        
        $resultado = $this->wpdb->update(
            $this->tabla_clientes_notas,
            $datos,
            array('id' => intval($nota_id)),
            $formatos,
            array('%d')
        );
        
        return $resultado !== false;
    }

    /**
     * Eliminar nota
     */
    public function eliminar_nota_cliente($nota_id, $verificar_autor = true) {
        if ($verificar_autor) {
            // Verificar que el usuario actual es el autor
            $nota = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT creado_por FROM {$this->tabla_clientes_notas} WHERE id = %d",
                $nota_id
            ));
            
            if (!$nota || $nota->creado_por != get_current_user_id()) {
                return new WP_Error('sin_permisos', __('No tiene permisos para eliminar esta nota', 'modulo-ventas'));
            }
        }
        
        $resultado = $this->wpdb->delete(
            $this->tabla_clientes_notas,
            array('id' => intval($nota_id)),
            array('%d')
        );
        
        return $resultado !== false;
    }

    /**
     * Contar notas por cliente
     */
    public function contar_notas_cliente($cliente_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabla_clientes_notas} WHERE cliente_id = %d",
            $cliente_id
        ));
    }

    /**
     * Obtener tipos de notas disponibles
     */
    public function obtener_tipos_notas() {
        return array(
            'general' => __('General', 'modulo-ventas'),
            'llamada' => __('Llamada telefónica', 'modulo-ventas'),
            'reunion' => __('Reunión', 'modulo-ventas'),
            'email' => __('Correo electrónico', 'modulo-ventas'),
            'seguimiento' => __('Seguimiento', 'modulo-ventas'),
            'cotizacion' => __('Sobre cotización', 'modulo-ventas'),
            'reclamo' => __('Reclamo', 'modulo-ventas'),
            'observacion' => __('Observación', 'modulo-ventas')
        );
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
     * Eliminar cliente
     */
    public function eliminar_cliente($cliente_id) {
        // Verificar que no tenga cotizaciones
        $cotizaciones = $this->contar_cotizaciones_cliente($cliente_id);
        if ($cotizaciones > 0) {
            return new WP_Error('tiene_cotizaciones', __('No se puede eliminar un cliente con cotizaciones asociadas', 'modulo-ventas'));
        }
        
        // Eliminar metadatos
        $this->wpdb->delete(
            $this->tabla_clientes_meta,
            array('cliente_id' => $cliente_id)
        );
        
        // Eliminar cliente
        $resultado = $this->wpdb->delete(
            $this->tabla_clientes,
            array('id' => $cliente_id)
        );
        
        return $resultado !== false;
    }

    /**
     * Obtener clientes con estadísticas
     */
    public function obtener_clientes_con_estadisticas($args = array()) {
        // Argumentos por defecto
        $defaults = array(
            'limite' => 20,
            'offset' => 0,
            'orden' => 'razon_social',
            'orden_dir' => 'ASC',
            'buscar' => '',
            'estado' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Query principal con JOIN para cotizaciones Y notas
        $sql = "SELECT 
            c.*,
            COUNT(DISTINCT cot.id) as cotizaciones,
            COUNT(DISTINCT n.id) as total_notas,
            c.fecha_actualizacion as fecha_modificacion
        FROM {$this->tabla_clientes} c
        LEFT JOIN {$this->tabla_cotizaciones} cot ON c.id = cot.cliente_id
        LEFT JOIN {$this->tabla_clientes_notas} n ON c.id = n.cliente_id
        WHERE 1=1";
        
        // Query para contar total
        $count_sql = "SELECT COUNT(DISTINCT c.id) 
                    FROM {$this->tabla_clientes} c 
                    WHERE 1=1";
        
        // Condiciones WHERE
        $where_conditions = array();
        
        // Búsqueda
        if (!empty($args['buscar'])) {
            $buscar = '%' . $this->wpdb->esc_like($args['buscar']) . '%';
            $where_conditions[] = $this->wpdb->prepare(
                "(c.razon_social LIKE %s OR c.rut LIKE %s OR c.email LIKE %s)",
                $buscar, $buscar, $buscar
            );
        }
        
        // Estado
        if (!empty($args['estado'])) {
            $where_conditions[] = $this->wpdb->prepare("c.estado = %s", $args['estado']);
        }
        
        // Aplicar condiciones WHERE
        if (!empty($where_conditions)) {
            $where = ' AND ' . implode(' AND ', $where_conditions);
            $sql .= $where;
            $count_sql .= $where;
        }
        
        // GROUP BY
        $sql .= " GROUP BY c.id";
        
        // ORDER BY
        $orden_columnas_validas = array('razon_social', 'rut', 'email', 'estado', 'fecha_modificacion');
        $orden = in_array($args['orden'], $orden_columnas_validas) ? $args['orden'] : 'razon_social';
        $orden_dir = in_array(strtoupper($args['orden_dir']), array('ASC', 'DESC')) ? $args['orden_dir'] : 'ASC';
        
        $sql .= " ORDER BY c.{$orden} {$orden_dir}";
        
        // LIMIT y OFFSET
        if ($args['limite'] > 0) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limite'], $args['offset']);
        }
        
        // Ejecutar queries
        $items = $this->wpdb->get_results($sql, ARRAY_A);
        $total = $this->wpdb->get_var($count_sql);
        
        // Agregar el conteo de notas a cada item si no se obtuvo en la consulta principal
        // (por si hay algún problema con el GROUP BY)
        foreach ($items as &$item) {
            if (!isset($item['total_notas']) || $item['total_notas'] === null) {
                $item['total_notas'] = $this->contar_notas_cliente($item['id']);
            }
        }
        
        return array(
            'items' => $items,
            'total' => $total
        );
        
        /*$defaults = array(
            'limite' => 20,
            'offset' => 0,
            'orden' => 'razon_social',
            'orden_dir' => 'ASC',
            'buscar' => '',
            'estado' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Query base
        $sql = "SELECT c.*, 
                COUNT(DISTINCT cot.id) as cotizaciones
                FROM {$this->tabla_clientes} c
                LEFT JOIN {$this->tabla_cotizaciones} cot ON c.id = cot.cliente_id
                WHERE 1=1";
        
        $count_sql = "SELECT COUNT(DISTINCT c.id) 
                    FROM {$this->tabla_clientes} c
                    WHERE 1=1";
        
        $where_conditions = array();
        
        // Búsqueda
        if (!empty($args['buscar'])) {
            $buscar = '%' . $this->wpdb->esc_like($args['buscar']) . '%';
            $where_conditions[] = $this->wpdb->prepare(
                "(c.razon_social LIKE %s OR c.rut LIKE %s OR c.email LIKE %s OR c.telefono LIKE %s)",
                $buscar, $buscar, $buscar, $buscar
            );
        }
        
        // Filtro por estado
        if (!empty($args['estado'])) {
            $where_conditions[] = $this->wpdb->prepare("c.estado = %s", $args['estado']);
        }
        
        // Aplicar condiciones WHERE
        if (!empty($where_conditions)) {
            $where = ' AND ' . implode(' AND ', $where_conditions);
            $sql .= $where;
            $count_sql .= $where;
        }
        
        // GROUP BY
        $sql .= " GROUP BY c.id";
        
        // ORDER BY
        $orden_columnas_validas = array('razon_social', 'rut', 'email', 'estado', 'fecha_modificacion');
        $orden = in_array($args['orden'], $orden_columnas_validas) ? $args['orden'] : 'razon_social';
        $orden_dir = in_array(strtoupper($args['orden_dir']), array('ASC', 'DESC')) ? $args['orden_dir'] : 'ASC';
        
        $sql .= " ORDER BY c.{$orden} {$orden_dir}";
        
        // LIMIT y OFFSET
        if ($args['limite'] > 0) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limite'], $args['offset']);
        }
        
        // Ejecutar queries
        $items = $this->wpdb->get_results($sql, ARRAY_A);
        $total = $this->wpdb->get_var($count_sql);
        
        return array(
            'items' => $items,
            'total' => $total
        );*/
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function obtener_estadisticas_clientes() {
        $stats = array();
        
        // Total de clientes
        $stats['total_clientes'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tabla_clientes}"
        );
        
        // Clientes activos
        $stats['clientes_activos'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tabla_clientes} WHERE estado = 'activo'"
        );
        
        // Nuevos este mes
        $primer_dia_mes = date('Y-m-01');
        $stats['nuevos_mes'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabla_clientes} 
            WHERE fecha_creacion >= %s",
            $primer_dia_mes
        ));
        
        // Con cotizaciones
        $stats['con_cotizaciones'] = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT cliente_id) FROM {$this->tabla_cotizaciones}"
        );
        
        return $stats;
    }

    /**
     * Script de migración para actualizar tablas existentes
     */
    public function migrar_estructura_clientes() {
        // Obtener estructura actual
        $columnas_actuales = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->tabla_clientes}");
        
        // 1. Eliminar columnas problemáticas si existen
        if (in_array('credito_disponible', $columnas_actuales)) {
            $this->wpdb->query("ALTER TABLE {$this->tabla_clientes} DROP COLUMN credito_disponible");
        }
        
        // 2. Eliminar columnas redundantes (ciudad, region, codigo_postal sin _facturacion)
        $columnas_a_eliminar = array('ciudad', 'region', 'codigo_postal');
        foreach ($columnas_a_eliminar as $columna) {
            if (in_array($columna, $columnas_actuales)) {
                // Primero copiar datos a las columnas _facturacion si no están vacías
                $columna_destino = $columna . '_facturacion';
                if (in_array($columna_destino, $columnas_actuales)) {
                    $this->wpdb->query("UPDATE {$this->tabla_clientes} 
                        SET {$columna_destino} = {$columna} 
                        WHERE {$columna_destino} IS NULL AND {$columna} IS NOT NULL");
                }
                // Luego eliminar la columna
                $this->wpdb->query("ALTER TABLE {$this->tabla_clientes} DROP COLUMN {$columna}");
            }
        }
        
        // 3. Renombrar codigo_postal a codigo_postal_facturacion si es necesario
        if (!in_array('codigo_postal_facturacion', $columnas_actuales) && in_array('codigo_postal', $columnas_actuales)) {
            $this->wpdb->query("ALTER TABLE {$this->tabla_clientes} 
                CHANGE COLUMN codigo_postal codigo_postal_facturacion VARCHAR(20) DEFAULT NULL");
        }
        
        // 4. Agregar codigo_postal_envio si no existe
        if (!in_array('codigo_postal_envio', $columnas_actuales)) {
            $this->wpdb->query("ALTER TABLE {$this->tabla_clientes} 
                ADD COLUMN codigo_postal_envio VARCHAR(20) DEFAULT NULL AFTER region_envio");
        }
        
        // 5. Asegurar que todas las columnas necesarias existan
        $this->verificar_y_actualizar_db();
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

            // Primero calcular el subtotal de todos los items
            foreach ($items as $item) {
                $subtotal += floatval($item['subtotal']);
            }

            // Aplicar descuento global
            if ($datos_cotizacion['tipo_descuento'] === 'porcentaje') {
                $descuento = $subtotal * ($datos_cotizacion['descuento_porcentaje'] / 100);
                $datos_cotizacion['descuento_monto'] = $descuento;
            } else {
                $descuento = $datos_cotizacion['descuento_monto'];
            }

            $subtotal_con_descuento = $subtotal - $descuento;

            // Agregar costo de envío
            $subtotal_con_envio = $subtotal_con_descuento + $datos_cotizacion['costo_envio'];

            // Calcular IVA sobre el monto final (subtotal con descuento + envío)
            $impuesto_total = 0;
            if ($datos_cotizacion['incluye_iva']) {
                // IVA se aplica sobre el total incluyendo envío
                $impuesto_total = $subtotal_con_envio * 0.19;
            }

            // Calcular total final
            $total = $subtotal_con_envio + $impuesto_total;

            // Actualizar los valores en el array de datos
            $datos_cotizacion['subtotal'] = $subtotal;
            $datos_cotizacion['impuesto_monto'] = $impuesto_total;
            $datos_cotizacion['impuesto_porcentaje'] = $datos_cotizacion['incluye_iva'] ? 19 : 0;
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
        // Valores por defecto
        $defaults = array(
            'limite' => 20,
            'offset' => 0,
            'orden' => 'fecha',
            'orden_dir' => 'DESC',
            'buscar' => '',
            'estado' => '',
            'cliente_id' => 0,
            'vendedor_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Usar SELECT * para evitar listar todas las columnas
        $sql = "SELECT c.*, 
                cl.razon_social, cl.rut, cl.email, cl.telefono,
                u.display_name as usuario_vendedor
                FROM {$this->tabla_cotizaciones} c
                LEFT JOIN {$this->tabla_clientes} cl ON c.cliente_id = cl.id
                LEFT JOIN {$this->wpdb->users} u ON c.vendedor_id = u.ID
                WHERE 1=1";
        
        $params = array();
        
        // Filtro por búsqueda
        if (!empty($args['buscar'])) {
            $sql .= " AND (c.folio LIKE %s OR cl.razon_social LIKE %s OR cl.rut LIKE %s)";
            $like = '%' . $this->wpdb->esc_like($args['buscar']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        // Filtro por estado
        if (!empty($args['estado'])) {
            $sql .= " AND c.estado = %s";
            $params[] = $args['estado'];
        }
        
        // Filtro por cliente
        if (!empty($args['cliente_id'])) {
            $sql .= " AND c.cliente_id = %d";
            $params[] = $args['cliente_id'];
        }
        
        // Filtro por vendedor
        if (!empty($args['vendedor_id'])) {
            $sql .= " AND c.vendedor_id = %d";
            $params[] = $args['vendedor_id'];
        }
        
        // Filtro por fechas
        if (!empty($args['fecha_desde'])) {
            $sql .= " AND DATE(c.fecha) >= %s";
            $params[] = $args['fecha_desde'];
        }
        
        if (!empty($args['fecha_hasta'])) {
            $sql .= " AND DATE(c.fecha) <= %s";
            $params[] = $args['fecha_hasta'];
        }
        
        // Preparar consulta si hay parámetros
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }
        
        // Obtener total antes de aplicar límites
        $total_sql = "SELECT COUNT(*) FROM ({$sql}) as temp";
        $total = $this->wpdb->get_var($total_sql);
        
        // Ordenamiento - validar que la columna existe
        $columnas_validas = array('fecha', 'folio', 'total', 'estado', 'fecha_expiracion');
        if (!in_array($args['orden'], $columnas_validas)) {
            $args['orden'] = 'fecha';
        }
        
        $sql .= " ORDER BY c.{$args['orden']} {$args['orden_dir']}";
        
        // Límites
        $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['limite'], $args['offset']);
        
        // Obtener resultados
        $cotizaciones = $this->wpdb->get_results($sql);
        
        // Formatear resultados para la tabla
        $items = array();
        foreach ($cotizaciones as $cotizacion) {
            $items[] = array(
                'id' => $cotizacion->id,
                'numero' => $cotizacion->folio,
                'fecha' => $cotizacion->fecha,
                'cliente' => $cotizacion->razon_social ?: 'Sin cliente',
                'cliente_id' => $cotizacion->cliente_id,
                'total' => $cotizacion->total,
                'estado' => $cotizacion->estado,
                'fecha_vencimiento' => $cotizacion->fecha_expiracion, // Mapear el nombre correcto
                'vendedor' => $cotizacion->usuario_vendedor ?: $cotizacion->vendedor_nombre ?: 'Sin vendedor'
            );
        }
        
        // IMPORTANTE: Devolver array con la estructura correcta
        return array(
            'items' => $items,
            'total' => intval($total)
        );
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
            'fecha_expiracion_hasta' => '', // Nuevo parámetro para próximas a expirar
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
            // Si el estado es un array (para múltiples estados)
            if (is_array($args['estado'])) {
                $placeholders = array_fill(0, count($args['estado']), '%s');
                $sql .= " AND c.estado IN (" . implode(',', $placeholders) . ")";
                $params = array_merge($params, $args['estado']);
            } else {
                $sql .= " AND c.estado = %s";
                $params[] = $args['estado'];
            }
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
        
        // Nuevo filtro para fecha de expiración (para "próximas a expirar")
        if (!empty($args['fecha_expiracion_hasta'])) {
            $sql .= " AND c.fecha_expiracion <= %s AND c.fecha_expiracion >= %s";
            $params[] = $args['fecha_expiracion_hasta'];
            $params[] = date('Y-m-d'); // Desde hoy
        }
        
        if (!empty($args['buscar'])) {
            $sql .= " AND (c.folio LIKE %s OR cl.razon_social LIKE %s OR cl.rut LIKE %s)";
            $like_term = '%' . $this->wpdb->esc_like($args['buscar']) . '%';
            $params[] = $like_term;
            $params[] = $like_term;
            $params[] = $like_term;
        }
        
        // Preparar y ejecutar consulta
        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }
        
        return intval($this->wpdb->get_var($sql));
    }

    /**
     * Contar cotizaciones de un cliente
     */
    public function contar_cotizaciones_cliente($cliente_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabla_cotizaciones} WHERE cliente_id = %d",
            $cliente_id
        ));
    }

    /**
     * Obtener estadísticas de cotizaciones
     */
    public function obtener_estadisticas_cotizaciones() {
        $estadisticas = array(
            'total' => 0,
            'pendientes' => 0,
            'aceptadas' => 0,
            'rechazadas' => 0,
            'vencidas' => 0,
            'convertidas' => 0,
            'monto_total' => 0
        );
        
        // Total de cotizaciones
        $estadisticas['total'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tabla_cotizaciones}"
        );
        
        // Por estado
        $estados = $this->wpdb->get_results(
            "SELECT estado, COUNT(*) as cantidad, SUM(total) as monto 
            FROM {$this->tabla_cotizaciones} 
            GROUP BY estado"
        );
        
        foreach ($estados as $estado) {
            switch ($estado->estado) {
                case 'pendiente':
                case 'enviada':
                    $estadisticas['pendientes'] += $estado->cantidad;
                    break;
                case 'aceptada':
                    $estadisticas['aceptadas'] = $estado->cantidad;
                    break;
                case 'rechazada':
                    $estadisticas['rechazadas'] = $estado->cantidad;
                    break;
                case 'vencida':
                case 'expirada':
                    $estadisticas['vencidas'] += $estado->cantidad;
                    break;
                case 'convertida':
                    $estadisticas['convertidas'] = $estado->cantidad;
                    break;
            }
            $estadisticas['monto_total'] += floatval($estado->monto);
        }
        
        return $estadisticas;
    }

    /**
     * Obtener estadísticas del dashboard
     */
    public function obtener_estadisticas_dashboard() {
        $stats = array();
        
        // Cotizaciones del mes actual
        $fecha_inicio = date('Y-m-01');
        $fecha_fin = date('Y-m-d');
        
        $stats['cotizaciones_mes'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabla_cotizaciones} 
            WHERE DATE(fecha) >= %s AND DATE(fecha) <= %s",
            $fecha_inicio,
            $fecha_fin
        ));
        
        // Monto del mes
        $stats['monto_mes'] = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(total) FROM {$this->tabla_cotizaciones} 
            WHERE DATE(fecha) >= %s AND DATE(fecha) <= %s",
            $fecha_inicio,
            $fecha_fin
        )) ?: 0;
        
        // Tasa de conversión
        $total_cotizaciones = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tabla_cotizaciones}"
        );
        
        $cotizaciones_convertidas = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tabla_cotizaciones} WHERE estado = 'convertida'"
        );
        
        $stats['conversion_rate'] = $total_cotizaciones > 0 
            ? round(($cotizaciones_convertidas / $total_cotizaciones) * 100, 2)
            : 0;
        
        return $stats;
    }

    /**
     * Obtener valor total cotizado por cliente
     */
    public function obtener_valor_total_cotizado_cliente($cliente_id) {
        $sql = $this->wpdb->prepare(
            "SELECT SUM(total) FROM {$this->tabla_cotizaciones} 
            WHERE cliente_id = %d AND estado NOT IN ('cancelada', 'expirada')",
            $cliente_id
        );
        
        return floatval($this->wpdb->get_var($sql)) ?: 0;
    }

    /**
     * Obtener última cotización de un cliente
     */
    public function obtener_ultima_cotizacion_cliente($cliente_id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->tabla_cotizaciones} 
            WHERE cliente_id = %d 
            ORDER BY fecha DESC 
            LIMIT 1",
            $cliente_id
        );
        
        return $this->wpdb->get_row($sql);
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

    /**
     * Obtener cotizaciones agrupadas por mes
     * 
     * @param int $meses Número de meses hacia atrás
     * @return array
     */
    public function obtener_cotizaciones_por_mes($meses = 6) {
        global $wpdb;
        
        $resultados = array();
        
        // Generar datos para los últimos X meses
        for ($i = $meses - 1; $i >= 0; $i--) {
            $fecha_inicio = date('Y-m-01', strtotime("-$i months"));
            $fecha_fin = date('Y-m-t', strtotime("-$i months"));
            
            // Contar cotizaciones del mes
            $cantidad = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                FROM {$this->tabla_cotizaciones} 
                WHERE DATE(fecha) >= %s 
                AND DATE(fecha) <= %s",
                $fecha_inicio,
                $fecha_fin
            ));
            
            // Sumar montos del mes
            $monto = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(total), 0) 
                FROM {$this->tabla_cotizaciones} 
                WHERE DATE(fecha) >= %s 
                AND DATE(fecha) <= %s",
                $fecha_inicio,
                $fecha_fin
            ));
            
            $resultados[] = array(
                'mes' => date_i18n('M Y', strtotime($fecha_inicio)),
                'mes_corto' => date_i18n('M', strtotime($fecha_inicio)),
                'cantidad' => intval($cantidad),
                'monto' => floatval($monto),
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin
            );
        }
        
        return $resultados;
    }
    
    /**
     * Contar items de una cotización
     */
    public function contar_items_cotizacion($cotizacion_id) {
        $cotizacion_id = intval($cotizacion_id);
        
        if (!$cotizacion_id) {
            return 0;
        }
        
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tabla_cotizaciones_items} WHERE cotizacion_id = %d",
            $cotizacion_id
        );
        
        return intval($this->wpdb->get_var($sql));
    }

    /**
     * Verificar si una cotización existe y tiene el estado correcto para generar PDF
     */
    public function cotizacion_puede_generar_pdf($cotizacion_id) {
        $cotizacion = $this->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            error_log("MODULO_VENTAS: Cotización {$cotizacion_id} no encontrada");
            return false;
        }
                
        // Verificar que tenga items
        $tiene_items = $this->contar_items_cotizacion($cotizacion_id) > 0;
        
        if (!$tiene_items) {
            error_log("MODULO_VENTAS: Cotización {$cotizacion_id} no tiene items");
            return false;
        }
                
        // TODOS los estados válidos para generar PDF (incluyendo variaciones de nombres)
        $estados_validos = array(
            'borrador',
            'pendiente',     // ⭐ AGREGADO
            'enviada', 
            'aceptada', 
            'aprobada',      // ⭐ AGREGADO (variación de aceptada)
            'rechazada', 
            'vencida', 
            'expirada',      // ⭐ AGREGADO (variación de vencida)
            'convertida',
            'cancelada'      // ⭐ AGREGADO por si acaso
        );
        
        // Verificar estado
        $estado_valido = in_array(strtolower($cotizacion->estado), $estados_validos);
        
        if (!$estado_valido) {
            error_log("MODULO_VENTAS: Estado '{$cotizacion->estado}' no es válido para PDF");
            return false;
        }
        
        // Para borradores y pendientes, verificar datos adicionales
        if (in_array(strtolower($cotizacion->estado), array('borrador', 'pendiente'))) {
            // Verificar que tenga cliente
            if (!$cotizacion->cliente_id || $cotizacion->cliente_id <= 0) {
                error_log("MODULO_VENTAS: Cotización {$cotizacion_id} no tiene cliente asignado");
                return false;
            }
            
            // Verificar que tenga total mayor a 0
            if (!$cotizacion->total || $cotizacion->total <= 0) {
                error_log("MODULO_VENTAS: Cotización {$cotizacion_id} tiene total igual a 0");
                return false;
            }
        }
        
        error_log("MODULO_VENTAS: Cotización {$cotizacion_id} puede generar PDF");
        return true;
    }

    /**
     * Obtener datos completos de cotización para PDF
     */
    public function obtener_cotizacion_para_pdf($cotizacion_id) {
        $cotizacion = $this->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            return false;
        }
        
        // Obtener cliente
        $cliente = $this->obtener_cliente($cotizacion->cliente_id);
        
        // Obtener items
        $items = $this->obtener_items_cotizacion($cotizacion_id);
        
        // Obtener vendedor
        $vendedor = null;
        if ($cotizacion->usuario_id) {
            $vendedor = get_userdata($cotizacion->usuario_id);
        }
        
        return array(
            'cotizacion' => $cotizacion,
            'cliente' => $cliente,
            'items' => $items,
            'vendedor' => $vendedor
        );
    }

    /**
     * TAMBIÉN AGREGAR ESTE MÉTODO DE DEBUG PARA VER LA COTIZACIÓN ACTUAL
     */
    public function debug_cotizacion($cotizacion_id) {
        $cotizacion = $this->obtener_cotizacion($cotizacion_id);
        
        if (!$cotizacion) {
            return array('error' => 'Cotización no encontrada');
        }
        
        $items_count = $this->contar_items_cotizacion($cotizacion_id);
        
        return array(
            'cotizacion_id' => $cotizacion_id,
            'estado' => $cotizacion->estado,
            'cliente_id' => $cotizacion->cliente_id,
            'total' => $cotizacion->total,
            'items_count' => $items_count,
            'puede_pdf' => $this->cotizacion_puede_generar_pdf($cotizacion_id)
        );
    }

    /**
     * Actualizar cotización existente
     */
    public function actualizar_cotizacion($cotizacion_id, $datos_generales, $items) {
        try {
            // Iniciar transacción
            $this->wpdb->query('START TRANSACTION');
            
            // Preparar datos para actualización
            $datos_actualizacion = array(
                'cliente_id' => $datos_generales['cliente_id'],
                'almacen_id' => $datos_generales['almacen_id'],
                'fecha' => $datos_generales['fecha'],
                'fecha_expiracion' => $datos_generales['fecha_expiracion'],
                'plazo_pago' => $datos_generales['plazo_pago'],
                'condiciones_pago' => $datos_generales['condiciones_pago'],
                'observaciones' => $datos_generales['observaciones'],
                'notas_internas' => $datos_generales['notas_internas'],
                'terminos_condiciones' => $datos_generales['terminos_condiciones'],
                'incluye_iva' => $datos_generales['incluye_iva'],
                'descuento_monto' => $datos_generales['descuento_monto'],
                'descuento_porcentaje' => $datos_generales['descuento_porcentaje'],
                'tipo_descuento' => $datos_generales['tipo_descuento'],
                'envio' => $datos_generales['costo_envio'],
                'subtotal' => $datos_generales['subtotal'],
                'total' => $datos_generales['total'],
                'modificado_por' => get_current_user_id()
            );
            
            // Actualizar cotización principal
            $resultado = $this->wpdb->update(
                $this->tabla_cotizaciones,
                $datos_actualizacion,
                array('id' => $cotizacion_id),
                array(
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                    '%d', '%f', '%f', '%s', '%f', '%f', '%f', '%d'
                ),
                array('%d')
            );
            
            if ($resultado === false) {
                throw new Exception($this->wpdb->last_error ?: 'Error al actualizar la cotización');
            }
            
            // Eliminar items anteriores
            $this->wpdb->delete(
                $this->tabla_cotizaciones_items,
                array('cotizacion_id' => $cotizacion_id),
                array('%d')
            );
            
            // Insertar nuevos items
            if (!empty($items) && is_array($items)) {
                foreach ($items as $index => $item) {
                    // Validar item
                    if (empty($item['cantidad']) || $item['cantidad'] <= 0) {
                        continue;
                    }
                    
                    // Preparar datos del item
                    $datos_item = array(
                        'cotizacion_id' => $cotizacion_id,
                        'producto_id' => isset($item['producto_id']) ? intval($item['producto_id']) : 0,
                        'nombre' => isset($item['nombre']) ? sanitize_text_field($item['nombre']) : '',
                        'descripcion' => isset($item['descripcion']) ? sanitize_textarea_field($item['descripcion']) : '',
                        'sku' => isset($item['sku']) ? sanitize_text_field($item['sku']) : '',
                        'cantidad' => floatval($item['cantidad']),
                        'precio_unitario' => floatval($item['precio_unitario']),
                        'descuento_monto' => isset($item['descuento_monto']) ? floatval($item['descuento_monto']) : 0,
                        'descuento_porcentaje' => isset($item['descuento_porcentaje']) ? floatval($item['descuento_porcentaje']) : 0,
                        'tipo_descuento' => isset($item['tipo_descuento']) ? sanitize_text_field($item['tipo_descuento']) : 'monto',
                        'subtotal' => floatval($item['subtotal']),
                        'total' => floatval($item['total']),
                        'almacen_id' => isset($item['almacen_id']) ? intval($item['almacen_id']) : $datos_generales['almacen_id'],
                        'orden' => $index
                    );
                    
                    // Si no hay producto_id pero hay nombre, es un item personalizado
                    if (empty($datos_item['producto_id']) && !empty($datos_item['nombre'])) {
                        $datos_item['es_personalizado'] = 1;
                    }
                    
                    // Insertar item
                    $resultado_item = $this->wpdb->insert(
                        $this->tabla_cotizaciones_items,
                        $datos_item,
                        array('%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%f', '%f', '%d', '%d')
                    );
                    
                    if ($resultado_item === false) {
                        throw new Exception('Error al insertar item: ' . $this->wpdb->last_error);
                    }
                }
            }
            
            // Confirmar transacción
            $this->wpdb->query('COMMIT');
            
            // Log
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log("Cotización {$cotizacion_id} actualizada exitosamente", 'info');
            
            // Hook
            do_action('modulo_ventas_cotizacion_actualizada', $cotizacion_id, $datos_generales, $items);
            
            return $cotizacion_id;
            
        } catch (Exception $e) {
            // Revertir transacción
            $this->wpdb->query('ROLLBACK');
            
            $logger = Modulo_Ventas_Logger::get_instance();
            $logger->log('Error al actualizar cotización: ' . $e->getMessage(), 'error');
            
            return new WP_Error('error_actualizar', $e->getMessage());
        }
    }
}