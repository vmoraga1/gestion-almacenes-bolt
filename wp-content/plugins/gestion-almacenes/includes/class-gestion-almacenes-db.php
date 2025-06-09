<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_DB {

    public function __construct() {
        // Aquí podrías añadir hooks si esta clase necesitara interactuar con WordPress directamente en el constructor.
        // Para la creación de tabla, se llama directamente en el hook de activación.
    }

    // Función a ejecutar en la activación del plugin
    public function activar_plugin() {
        $this->crear_tablas_plugin();
        // Aquí podrías añadir otras tareas de activación si las tuvieras.
    }

    // Función para crear las tablas del plugin
    public function crear_tablas_plugin() { // Renombramos esta función
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); // Incluimos dbDelta aquí

        // Tabla de Almacenes
        $table_name_warehouses = $wpdb->prefix . 'gab_warehouses';

        // Asegúrate de que la tabla no exista antes de intentar crearla
        // *** CORRECCIÓN AQUÍ: Usar $table_name_warehouses en lugar de $table_name ***
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_warehouses'") != $table_name_warehouses) {
            error_log('[DEBUG GESTION ALMACENES DB] Intentando crear tabla de almacenes...');

            $sql_warehouses = "CREATE TABLE $table_name_warehouses (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name tinytext NOT NULL,
                slug tinytext NOT NULL,
                address text NOT NULL,
                comuna tinytext NOT NULL,
                ciudad tinytext NOT NULL,
                region tinytext NOT NULL,
                pais tinytext NOT NULL,
                email tinytext NOT NULL,
                telefono tinytext NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            dbDelta($sql_warehouses);

            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear la tabla de almacenes: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de almacenes creada o actualizada correctamente.');
            }

        } else {
            error_log('[DEBUG GESTION ALMACENES DB] La tabla de almacenes ya existe. No se intentó crear.');
        }

        // Tabla de movimientos de stock
        $table_movements = $wpdb->prefix . 'gab_stock_movements';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_movements'") != $table_movements) {
            error_log('[DEBUG GESTION ALMACENES DB] Creando tabla de movimientos...');
            
            $sql_movements = "CREATE TABLE $table_movements (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) UNSIGNED NOT NULL,
                warehouse_id mediumint(9) NOT NULL,
                movement_type enum('in','out','adjustment','transfer_in','transfer_out','sale','return','initial') NOT NULL,
                quantity int(11) NOT NULL,
                balance_after int(11) NOT NULL,
                reference_type varchar(50),
                reference_id bigint(20),
                notes text,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product (product_id),
                KEY idx_warehouse (warehouse_id),
                KEY idx_date (created_at),
                KEY idx_type (movement_type),
                KEY idx_reference (reference_type, reference_id)
            ) $charset_collate;";
            
            dbDelta($sql_movements);
            
            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear tabla de movimientos: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de movimientos creada correctamente.');
            }
        }

        // *** Nueva Tabla de Stock por Almacén ***
        $table_name_stock = $wpdb->prefix . 'gab_warehouse_product_stock';

         // Asegúrate de que la tabla no exista antes de intentar crearla
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_stock'") != $table_name_stock) {
            error_log('[DEBUG GESTION ALMACENES DB] Intentando crear tabla de stock por almacén...');
            $sql_stock = "CREATE TABLE $table_name_stock (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                warehouse_id mediumint(9) NOT NULL,
                product_id bigint(20) UNSIGNED NOT NULL,
                stock int(11) NOT NULL DEFAULT 0,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY product_warehouse (product_id, warehouse_id), -- Asegura que un producto solo tenga un registro por almacén
                KEY idx_warehouse (warehouse_id),
                KEY idx_product (product_id)
            ) $charset_collate;";

            dbDelta($sql_stock);

            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear la tabla de stock: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de stock creada o actualizada correctamente.');
            }

        } else {
            error_log('[DEBUG GESTION ALMACENES DB] La tabla de stock ya existe. No se intentó crear.');
        }

        $table_name_stock = $wpdb->prefix . 'gab_warehouse_stock';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_stock'") != $table_name_stock) {
                error_log('[DEBUG GESTION ALMACENES DB] Intentando crear tabla de stock...');
                
                $sql_stock = "CREATE TABLE $table_name_stock (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    warehouse_id mediumint(9) NOT NULL,
                    product_id mediumint(9) NOT NULL,
                    quantity int NOT NULL DEFAULT 0,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY warehouse_product (warehouse_id, product_id),
                    KEY idx_warehouse (warehouse_id),
                    KEY idx_product (product_id)
                ) $charset_collate;";
                
                dbDelta($sql_stock);
                
                if ($wpdb->last_error) {
                    error_log('[DEBUG GESTION ALMACENES DB] Error al crear la tabla de stock: ' . $wpdb->last_error);
                } else {
                    error_log('[DEBUG GESTION ALMACENES DB] Tabla de stock creada correctamente.');
                }
            }

            // Tabla de transferencias de stock
            $table_name_transfers = $wpdb->prefix . 'gab_stock_transfers';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_transfers'") != $table_name_transfers) {
                error_log('[DEBUG GESTION ALMACENES DB] Intentando crear tabla de transferencias...');
                
                $sql_transfers = "CREATE TABLE $table_name_transfers (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    source_warehouse_id mediumint(9) NOT NULL,
                    target_warehouse_id mediumint(9) NOT NULL,
                    status varchar(20) DEFAULT 'pending',
                    notes text,
                    created_by mediumint(9) NOT NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    completed_by mediumint(9),
                    completed_at datetime,
                    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_source (source_warehouse_id),
                    KEY idx_target (target_warehouse_id),
                    KEY idx_status (status),
                    KEY idx_created (created_at)
                ) $charset_collate;";
                
                dbDelta($sql_transfers);
                
                if ($wpdb->last_error) {
                    error_log('[DEBUG GESTION ALMACENES DB] Error al crear la tabla de transferencias: ' . $wpdb->last_error);
                } else {
                    error_log('[DEBUG GESTION ALMACENES DB] Tabla de transferencias creada correctamente.');
                }
            }

            // Tabla de items de transferencia
            $table_name_transfer_items = $wpdb->prefix . 'gab_stock_transfer_items';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_transfer_items'") != $table_name_transfer_items) {
                error_log('[DEBUG GESTION ALMACENES DB] Intentando crear tabla de items de transferencia...');
                
                $sql_transfer_items = "CREATE TABLE $table_name_transfer_items (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    transfer_id mediumint(9) NOT NULL,
                    product_id mediumint(9) NOT NULL,
                    requested_qty int NOT NULL,
                    transferred_qty int,
                    PRIMARY KEY (id),
                    KEY idx_transfer (transfer_id),
                    KEY idx_product (product_id)
                ) $charset_collate;";
                
                dbDelta($sql_transfer_items);
                
                if ($wpdb->last_error) {
                    error_log('[DEBUG GESTION ALMACENES DB] Error al crear la tabla de items: ' . $wpdb->last_error);
                } else {
                    error_log('[DEBUG GESTION ALMACENES DB] Tabla de items creada correctamente.');
                }
            }

        $this->crear_tablas_transferencias();
    }

    public function crear_tablas_transferencias() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tabla principal de transferencias
        $table_transfers = $wpdb->prefix . 'gab_stock_transfers';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_transfers'") != $table_transfers) {
            error_log('[DEBUG GESTION ALMACENES DB] Creando tabla de transferencias...');
            
            $sql_transfers = "CREATE TABLE $table_transfers (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                source_warehouse_id mediumint(9) NOT NULL,
                target_warehouse_id mediumint(9) NOT NULL,
                status enum('draft','completed','cancelled') NOT NULL DEFAULT 'draft',
                notes text,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime DEFAULT NULL,
                validated_by bigint(20) UNSIGNED DEFAULT NULL,
                validated_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY source_warehouse_id (source_warehouse_id),
                KEY target_warehouse_id (target_warehouse_id),
                KEY status (status),
                KEY created_by (created_by)
            ) $charset_collate;";

            dbDelta($sql_transfers);
            
            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear tabla de transferencias: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de transferencias creada correctamente.');
            }
        }

        // Tabla de items de transferencias
        $table_transfer_items = $wpdb->prefix . 'gab_stock_transfer_items';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_transfer_items'") != $table_transfer_items) {
            error_log('[DEBUG GESTION ALMACENES DB] Creando tabla de items de transferencias...');
            
            $sql_transfer_items = "CREATE TABLE $table_transfer_items (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                transfer_id mediumint(9) NOT NULL,
                product_id bigint(20) UNSIGNED NOT NULL,
                requested_qty int(11) NOT NULL,
                received_qty int(11) DEFAULT NULL,
                discrepancy_qty int(11) DEFAULT NULL,
                discrepancy_type enum('shortage','excess') DEFAULT NULL,
                notes text,
                PRIMARY KEY (id),
                KEY transfer_id (transfer_id),
                KEY product_id (product_id)
            ) $charset_collate;";

            dbDelta($sql_transfer_items);
            
            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear tabla de items: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de items de transferencias creada correctamente.');
            }
        }

        // Tabla de discrepancias
        $table_discrepancies = $wpdb->prefix . 'gab_stock_discrepancies';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_discrepancies'") != $table_discrepancies) {
            error_log('[DEBUG GESTION ALMACENES DB] Creando tabla de discrepancias...');
            
            $sql_discrepancies = "CREATE TABLE $table_discrepancies (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                transfer_id mediumint(9) NOT NULL,
                product_id bigint(20) UNSIGNED NOT NULL,
                warehouse_id mediumint(9) NOT NULL,
                quantity int(11) NOT NULL,
                discrepancy_type enum('shortage','excess') NOT NULL,
                reason text,
                status enum('pending','resolved') NOT NULL DEFAULT 'pending',
                resolution_type enum('adjust_source','adjust_target','loss','damage') DEFAULT NULL,
                resolution_notes text,
                resolved_by bigint(20) UNSIGNED DEFAULT NULL,
                resolved_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY transfer_id (transfer_id),
                KEY product_id (product_id),
                KEY warehouse_id (warehouse_id),
                KEY status (status)
            ) $charset_collate;";

            dbDelta($sql_discrepancies);
            
            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear tabla de discrepancias: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de discrepancias creada correctamente.');
            }
        }

        // Tabla de almacén de mermas
        $table_waste_store = $wpdb->prefix . 'gab_waste_store';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_waste_store'") != $table_waste_store) {
            error_log('[DEBUG GESTION ALMACENES DB] Creando tabla de almacén de mermas...');
            
            $sql_waste_store = "CREATE TABLE $table_waste_store (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) UNSIGNED NOT NULL,
                quantity int(11) NOT NULL,
                type enum('loss','damage') NOT NULL,
                notes text,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY product_id (product_id),
                KEY type (type),
                KEY created_by (created_by)
            ) $charset_collate;";

            dbDelta($sql_waste_store);
            
            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear tabla de mermas: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de almacén de mermas creada correctamente.');
            }
        }

        // Tabla de historial de mermas
        $table_waste_history = $wpdb->prefix . 'gab_waste_store_history';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_waste_history'") != $table_waste_history) {
            error_log('[DEBUG GESTION ALMACENES DB] Creando tabla de historial de mermas...');
            
            $sql_waste_history = "CREATE TABLE $table_waste_history (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) UNSIGNED NOT NULL,
                quantity int(11) NOT NULL,
                type enum('loss','damage') NOT NULL,
                notes text,
                discrepancy_id mediumint(9) DEFAULT NULL,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY product_id (product_id),
                KEY type (type),
                KEY discrepancy_id (discrepancy_id),
                KEY created_by (created_by)
            ) $charset_collate;";

            dbDelta($sql_waste_history);
            
            if ($wpdb->last_error) {
                error_log('[DEBUG GESTION ALMACENES DB] Error al crear tabla de historial de mermas: ' . $wpdb->last_error);
            } else {
                error_log('[DEBUG GESTION ALMACENES DB] Tabla de historial de mermas creada correctamente.');
            }
        }
    }

    // Función para insertar un nuevo almacén (trasladada de gab_procesar_agregar_almacen)
    public function insertar_almacen($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouses';

        $insert_result = $wpdb->insert($table_name, $data);

        if ($insert_result === false) {
            error_log('[DEBUG GESTION ALMACENES DB] Error en $wpdb->insert: ' . $wpdb->last_error);
        } else {
            error_log('[DEBUG GESTION ALMACENES DB] Inserción exitosa. Filas afectadas: ' . $insert_result);
        }

        return $insert_result; // Devuelve el resultado de la inserción
    }

    // Función para obtener todos los almacenes de la base de datos
    public function obtener_almacenes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouses';

        // Obtener todos los registros de la tabla de almacenes
        // $wpdb->get_results devuelve un array de objetos o null si no hay resultados
        $almacenes = $wpdb->get_results("SELECT * FROM $table_name");

        if ($almacenes === null) {
            error_log('[DEBUG GESTION ALMACENES DB] No se encontraron almacenes en la base de datos.');
        } /*else {
            error_log('[DEBUG GESTION ALMACENES DB] Se encontraron ' . count($almacenes) . ' almacenes.');
        }*/


        return $almacenes;
    }

    public function save_product_warehouse_stock($product_id, $warehouse_id, $stock) {
        global $wpdb, $gestion_almacenes_movements;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';

        $existing_stock = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, stock FROM $table_name WHERE product_id = %d AND warehouse_id = %d",
                $product_id,
                $warehouse_id
            )
        );

        $data = array(
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'stock' => max(0, intval($stock)),
        );

        $format = array('%d', '%d', '%d');

        if ($existing_stock) {
            // Actualizar registro existente
            $old_stock = intval($existing_stock->stock);
            $where = array('id' => $existing_stock->id);
            $where_format = array('%d');
            $result = $wpdb->update($table_name, $data, $where, $format, $where_format);

        } else {
            // Insertar nuevo registro - STOCK INICIAL
            $result = $wpdb->insert($table_name, $data, $format);
            
            if ($result !== false && intval($stock) > 0 && isset($gestion_almacenes_movements)) {
                // Registrar movimiento inicial
                $gestion_almacenes_movements->log_movement(array(
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'type' => 'initial',
                    'quantity' => intval($stock),
                    'notes' => __('Stock inicial', 'gestion-almacenes')
                ));
            }
        }
    }

    /**
     * Obtiene el stock de un producto para todos los almacenes.
     * Retorna un array asociativo [warehouse_id => stock].
     */
    public function get_product_warehouse_stock($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';

        $stock_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT warehouse_id, stock FROM $table_name WHERE product_id = %d",
                $product_id
            ),
            OBJECT_K // Devuelve un array de objetos, con warehouse_id como clave
        );

        $formatted_stock = array();
        if ($stock_data) {
            foreach ($stock_data as $warehouse_id => $item) {
                $formatted_stock[$warehouse_id] = $item->stock;
            }
        }
        error_log('[DEBUG GESTION ALMACENES DB] Stock obtenido para Producto ID ' . $product_id . ': ' . print_r($formatted_stock, true));

        return $formatted_stock;
    }

    /**
     * Elimina el stock de un producto para un almacén específico.
     */
    public function delete_product_warehouse_stock($product_id, $warehouse_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';

        $result = $wpdb->delete(
            $table_name,
            array(
                'product_id' => $product_id,
                'warehouse_id' => $warehouse_id
            ),
            array('%d', '%d')
        );

        if ($result === false) {
            error_log('[DEBUG GESTION ALMACENES DB] Error al eliminar stock: ' . $wpdb->last_error);
        } else {
            error_log('[DEBUG GESTION ALMACENES DB] Stock eliminado para Producto ID ' . $product_id . ' en Almacén ID ' . $warehouse_id . '. Filas afectadas: ' . $result);
        }

        return $result !== false;
    }

    /**
     * Obtiene información de un almacén específico por ID
     */
    public function obtener_almacen_por_id($warehouse_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouses';

        $almacen = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $warehouse_id
            )
        );

        return $almacen;
    }

    /**
     * Obtiene el stock disponible de un producto en un almacén específico
     */
    public function get_product_stock_in_warehouse($product_id, $warehouse_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';

        $stock = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT stock FROM $table_name WHERE product_id = %d AND warehouse_id = %d",
                $product_id,
                $warehouse_id
            )
        );

        return $stock !== null ? intval($stock) : 0;
    }

    /**
     * Obtener todos los productos de WooCommerce con su información de stock en almacenes
     * Incluye productos sin stock asignado
     */
    public function get_all_wc_products_with_warehouse_stock($warehouse_id = null) {
        global $wpdb;
        
        // Obtener todos los productos de WooCommerce (simples y variables)
        $products_query = "
            SELECT DISTINCT 
                p.ID as product_id,
                p.post_title as product_name,
                pm_sku.meta_value as sku,
                pm_stock.meta_value as wc_stock,
                pm_manage.meta_value as manage_stock,
                p.post_status,
                p.post_type
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->postmeta} pm_manage ON p.ID = pm_manage.post_id AND pm_manage.meta_key = '_manage_stock'
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
            ORDER BY p.post_title ASC
        ";
        
        $all_products = $wpdb->get_results($products_query);
        
        // Tabla de stock de almacenes
        $stock_table = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Para cada producto, obtener su stock en almacenes
        foreach ($all_products as &$product) {
            // Inicializar array de stock por almacén
            $product->warehouse_stock = array();
            $product->total_warehouse_stock = 0;
            
            // Consultar stock en almacenes
            $where_clause = "product_id = %d";
            $params = array($product->product_id);
            
            if ($warehouse_id) {
                $where_clause .= " AND warehouse_id = %d";
                $params[] = $warehouse_id;
            }
            
            $stock_query = $wpdb->prepare(
                "SELECT warehouse_id, stock 
                FROM $stock_table 
                WHERE $where_clause",
                $params
            );
            
            $warehouse_stocks = $wpdb->get_results($stock_query);
            
            // Llenar información de stock
            foreach ($warehouse_stocks as $ws) {
                $product->warehouse_stock[$ws->warehouse_id] = intval($ws->stock);
                $product->total_warehouse_stock += intval($ws->stock);
            }
            
            // Manejar valores nulos
            $product->wc_stock = $product->wc_stock !== null ? intval($product->wc_stock) : 0;
            $product->manage_stock = $product->manage_stock === 'yes';
            
            // Para variaciones, obtener el nombre del producto padre
            if ($product->post_type === 'product_variation') {
                $parent_id = wp_get_post_parent_id($product->product_id);
                if ($parent_id) {
                    $parent_title = get_the_title($parent_id);
                    
                    // Obtener atributos de la variación
                    $variation = wc_get_product($product->product_id);
                    if ($variation) {
                        $attributes = $variation->get_variation_attributes();
                        $attr_string = implode(', ', $attributes);
                        $product->product_name = $parent_title . ' - ' . $attr_string;
                    }
                }
            }
        }
        
        return $all_products;
    }

    /**
     * Obtener productos para select/dropdown incluyendo los que no tienen stock
     */
    public function get_products_for_dropdown() {
        global $wpdb;
        
        $query = "
            SELECT DISTINCT
                p.ID as id,
                p.post_title as name,
                pm_sku.meta_value as sku,
                p.post_type
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
            ORDER BY p.post_title ASC
        ";
        
        $products = $wpdb->get_results($query);
        
        // Mejorar nombres de variaciones
        foreach ($products as &$product) {
            if ($product->post_type === 'product_variation') {
                $parent_id = wp_get_post_parent_id($product->id);
                if ($parent_id) {
                    $parent_title = get_the_title($parent_id);
                    $variation = wc_get_product($product->id);
                    if ($variation) {
                        $attributes = $variation->get_variation_attributes();
                        $attr_string = implode(', ', $attributes);
                        $product->name = $parent_title . ' - ' . $attr_string;
                    }
                }
            }
            
            // Agregar SKU al nombre si existe
            if (!empty($product->sku)) {
                $product->display_name = $product->name . ' (SKU: ' . $product->sku . ')';
            } else {
                $product->display_name = $product->name;
            }
        }
        
        return $products;
    }

    /**
     * Inicializar stock de almacén para un producto
     * Crea registro con stock 0 si no existe
     */
    public function initialize_product_warehouse_stock($product_id, $warehouse_id, $stock = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Verificar si ya existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE product_id = %d AND warehouse_id = %d",
            $product_id,
            $warehouse_id
        ));
        
        if (!$exists) {
            // Crear registro con stock inicial
            $result = $wpdb->insert(
                $table,
                array(
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'stock' => $stock
                ),
                array('%d', '%d', '%d')
            );
            
            return $result !== false;
        }
        
        return true;
    }

    /**
     * Inicializar registro de stock si no existe
     * @param int $warehouse_id ID del almacén
     * @param int $product_id ID del producto
     * @return bool
     */
    public function ensure_warehouse_stock_exists($warehouse_id, $product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Verificar si ya existe el registro
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE warehouse_id = %d AND product_id = %d",
            $warehouse_id,
            $product_id
        ));
        
        if (!$exists) {
            // Crear registro con stock 0
            $result = $wpdb->insert(
                $table_name,
                array(
                    'warehouse_id' => $warehouse_id,
                    'product_id' => $product_id,
                    'stock' => 0,
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s')
            );
            
            if ($result === false) {
                error_log('[GAB] Error al crear registro de stock: ' . $wpdb->last_error);
                return false;
            }
            
            error_log('[GAB] Registro de stock creado para producto ' . $product_id . ' en almacén ' . $warehouse_id);
        }
        
        return true;
    }

    /**
     * Obtiene todos los productos con su stock por almacén
     */
    public function get_all_products_warehouse_stock() {
        global $wpdb;
        $table_name_stock = $wpdb->prefix . 'gab_warehouse_product_stock';
        $table_name_warehouses = $wpdb->prefix . 'gab_warehouses';

        $results = $wpdb->get_results("
            SELECT s.product_id, s.warehouse_id, s.stock, w.name as warehouse_name
            FROM $table_name_stock s
            LEFT JOIN $table_name_warehouses w ON s.warehouse_id = w.id
            ORDER BY s.product_id, s.warehouse_id
        ");

        return $results;
    }

    /**
     * Verifica si hay suficiente stock en un almacén específico
     */
    public function check_warehouse_stock_availability($product_id, $warehouse_id, $required_quantity) {
        $current_stock = $this->get_product_stock_in_warehouse($product_id, $warehouse_id);
        return $current_stock >= $required_quantity;
    }

    /**
     * Obtiene productos con bajo stock en todos los almacenes
     */
    public function get_low_stock_products($threshold = 5) {
        global $wpdb;
        $table_name_stock = $wpdb->prefix . 'gab_warehouse_product_stock';
        $table_name_warehouses = $wpdb->prefix . 'gab_warehouses';

        $results = $wpdb->get_results(
            $wpdb->prepare("
                SELECT s.product_id, s.warehouse_id, s.stock, w.name as warehouse_name
                FROM $table_name_stock s
                LEFT JOIN $table_name_warehouses w ON s.warehouse_id = w.id
                WHERE s.stock <= %d AND s.stock > 0
                ORDER BY s.stock ASC, s.product_id
            ", $threshold)
        );

        return $results;
    }

    /**
     * Transferir stock entre almacenes
     */
    public function transfer_stock_between_warehouses($product_id, $from_warehouse_id, $to_warehouse_id, $quantity) {
        global $wpdb;

        // Verificar stock disponible en almacén origen
        $from_stock = $this->get_product_stock_in_warehouse($product_id, $from_warehouse_id);
        
        if ($from_stock < $quantity) {
            return false; // No hay suficiente stock para transferir
        }

        // Iniciar transacción
        $wpdb->query('START TRANSACTION');

        try {
            // Reducir stock del almacén origen
            $new_from_stock = $from_stock - $quantity;
            $result1 = $this->save_product_warehouse_stock($product_id, $from_warehouse_id, $new_from_stock);

            // Aumentar stock del almacén destino
            $to_stock = $this->get_product_stock_in_warehouse($product_id, $to_warehouse_id);
            $new_to_stock = $to_stock + $quantity;
            $result2 = $this->save_product_warehouse_stock($product_id, $to_warehouse_id, $new_to_stock);

            if ($result1 && $result2) {
                $wpdb->query('COMMIT');
                error_log('[DEBUG GESTION ALMACENES DB] Transferencia exitosa - Producto: ' . $product_id . ', De: ' . $from_warehouse_id . ', A: ' . $to_warehouse_id . ', Cantidad: ' . $quantity);
                return true;
            } else {
                $wpdb->query('ROLLBACK');
                return false;
            }

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[DEBUG GESTION ALMACENES DB] Error en transferencia: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener almacenes con stock disponible para un producto
     */
    public function get_warehouses_with_stock($product_id, $min_stock = 1) {
        global $wpdb;
        $table_name_stock = $wpdb->prefix . 'gab_warehouse_product_stock';
        $table_name_warehouses = $wpdb->prefix . 'gab_warehouses';

        $results = $wpdb->get_results(
            $wpdb->prepare("
                SELECT w.*, s.stock
                FROM $table_name_warehouses w
                INNER JOIN $table_name_stock s ON w.id = s.warehouse_id
                WHERE s.product_id = %d AND s.stock >= %d
                ORDER BY s.stock DESC
            ", $product_id, $min_stock)
        );

        return $results;
    }

    public function get_transfer($transfer_id) {
        global $wpdb;
        $transfer_table = $wpdb->prefix . 'gab_stock_transfers';
        $items_table = $wpdb->prefix . 'gab_stock_transfer_items';

        // Obtener la cabecera
        $transfer = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $transfer_table WHERE id = %d", $transfer_id)
        );

        if (!$transfer) return null;

        // Obtener los items asociados
        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $items_table WHERE transfer_id = %d", $transfer_id)
        );

        $transfer->items = $items;

        return $transfer;
    }

    public function update_transfer($transfer_id, $data) {
        global $wpdb;
        $transfers_table = $wpdb->prefix . 'gab_stock_transfers';
        $items_table = $wpdb->prefix . 'gab_stock_transfer_items';

        // Actualizar cabecera
        $updated = $wpdb->update(
            $transfers_table,
            [
                'source_warehouse_id' => $data['source_warehouse_id'],
                'target_warehouse_id' => $data['target_warehouse_id'],
                'notes'               => $data['notes'],
                'updated_at'          => current_time('mysql')
            ],
            ['id' => $transfer_id],
            ['%d', '%d', '%s', '%s'],
            ['%d']
        );

        // Si hubo error, retorna false
        if ($updated === false) {
            error_log('[GESTION ALMACENES DB] Error al actualizar transferencia ID ' . $transfer_id . ': ' . $wpdb->last_error);
            return false;
        }

        // Eliminar los items anteriores
        $wpdb->delete($items_table, ['transfer_id' => $transfer_id]);

        // Insertar los nuevos items
        foreach ($data['items'] as $item) {
            $wpdb->insert($items_table, [
                'transfer_id'   => $transfer_id,
                'product_id'    => $item['product_id'],
                'requested_qty' => $item['quantity'],
            ]);
        }

        return true;
    }

    public function delete_transfer($transfer_id) {
        global $wpdb;
        $transfers_table = $wpdb->prefix . 'gab_stock_transfers';
        $items_table = $wpdb->prefix . 'gab_stock_transfer_items';

        // Eliminar primero los items
        $wpdb->delete($items_table, ['transfer_id' => $transfer_id]);

        // Luego la cabecera
        $deleted = $wpdb->delete($transfers_table, ['id' => $transfer_id]);

        return $deleted !== false;
    }

    // Actualizar stock
    public function update_warehouse_stock($warehouse_id, $product_id, $quantity_change) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Asegurar que existe el registro
        $this->ensure_warehouse_stock_exists($warehouse_id, $product_id);
        
        // Verificar el stock actual
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE warehouse_id = %d AND product_id = %d",
            $warehouse_id,
            $product_id
        ));
        
        if ($existing) {
            // Actualizar el stock existente
            $new_stock = max(0, $existing->stock + $quantity_change);
            
            $result = $wpdb->update(
                $table_name,
                [
                    'stock' => $new_stock,
                    'updated_at' => current_time('mysql')
                ],
                [
                    'warehouse_id' => $warehouse_id,
                    'product_id' => $product_id
                ],
                ['%d', '%s'],
                ['%d', '%d']
            );
            
            if ($result === false) {
                throw new Exception('Error al actualizar el stock: ' . $wpdb->last_error);
            }
            
            error_log('[GAB] Stock actualizado: Almacén ' . $warehouse_id . ', Producto ' . $product_id . 
                    ', Cambio: ' . $quantity_change . ', Nuevo stock: ' . $new_stock);
        } else {
            // Esto no debería pasar porque ensure_warehouse_stock_exists() ya creó el registro
            throw new Exception('No se pudo encontrar el registro de stock después de inicialización');
        }
        
        do_action('gab_warehouse_stock_updated', $product_id, $warehouse_id);
        
        return true;
    }

    /**
     * Establecer el stock de un producto en un almacén (valor absoluto)
     */
    public function set_warehouse_stock($warehouse_id, $product_id, $stock_value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Asegurar que el valor es positivo
        $stock_value = max(0, intval($stock_value));
        
        // Verificar si ya existe un registro
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE warehouse_id = %d AND product_id = %d",
            $warehouse_id,
            $product_id
        ));
        
        if ($existing) {
            // Actualizar el stock existente
            $data = ['stock' => $stock_value];
            $format = ['%d'];
            
            // Solo agregar updated_at si la columna existe
            if (property_exists($existing, 'updated_at')) {
                $data['updated_at'] = current_time('mysql');
                $format[] = '%s';
            }
            
            $result = $wpdb->update(
                $table_name,
                $data,
                [
                    'warehouse_id' => $warehouse_id,
                    'product_id' => $product_id
                ],
                $format,
                ['%d', '%d']
            );
            
            if ($result === false) {
                error_log('[GAB] Error al actualizar stock: ' . $wpdb->last_error);
                return false;
            }
        } else {
            // Crear nuevo registro - solo incluir columnas que existen
            $data = [
                'warehouse_id' => $warehouse_id,
                'product_id' => $product_id,
                'stock' => $stock_value
            ];
            $format = ['%d', '%d', '%d'];
            
            // Verificar qué columnas existen en la tabla
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
            
            if (in_array('updated_at', $columns)) {
                $data['updated_at'] = current_time('mysql');
                $format[] = '%s';
            }
            
            $result = $wpdb->insert($table_name, $data, $format);
            
            if ($result === false) {
                error_log('[GAB] Error al insertar stock: ' . $wpdb->last_error);
                return false;
            }
        }
        
        // Disparar acción para sincronizar
        do_action('gab_warehouse_stock_updated', $product_id, $warehouse_id);
        
        return true;
    }

    /**
    * Obtener todos los almacenes
    */
    public function get_warehouses() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouses';
        
        $warehouses = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY name ASC"
        );
        
        return $warehouses;
    }

    /**
     * Obtener un almacén por ID
     */
    public function get_warehouse($warehouse_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouses';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $warehouse_id
        ));
    }

    /**
    * Obtener el stock de un producto en un almacén específico
    */
    public function get_warehouse_stock($warehouse_id, $product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        $stock = $wpdb->get_var($wpdb->prepare(
            "SELECT stock FROM $table_name WHERE warehouse_id = %d AND product_id = %d",
            $warehouse_id,
            $product_id
        ));
        
        return $stock !== null ? intval($stock) : 0;
    }

    /**
     * Obtener todas las transferencias con información adicional
     */
    public function get_transfers($args = []) {
        global $wpdb;
        $transfers_table = $wpdb->prefix . 'gab_stock_transfers';
        $warehouses_table = $wpdb->prefix . 'gab_warehouses';
        
        // Construir la consulta base
        $query = "SELECT 
                    t.*,
                    sw.name as source_warehouse_name,
                    tw.name as target_warehouse_name,
                    u.display_name as created_by_name
                FROM $transfers_table t
                LEFT JOIN $warehouses_table sw ON t.source_warehouse_id = sw.id
                LEFT JOIN $warehouses_table tw ON t.target_warehouse_id = tw.id
                LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID";
        
        // Agregar filtros si se proporcionan
        $where = [];
        $values = [];
        
        if (!empty($args['status'])) {
            $where[] = "t.status = %s";
            $values[] = $args['status'];
        }
        
        if (!empty($args['warehouse_id'])) {
            $where[] = "(t.source_warehouse_id = %d OR t.target_warehouse_id = %d)";
            $values[] = $args['warehouse_id'];
            $values[] = $args['warehouse_id'];
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        // Ordenar por fecha de creación descendente
        $query .= " ORDER BY t.created_at DESC";
        
        // Aplicar límite si se proporciona
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
        }
        
        // Ejecutar la consulta
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * Contar transferencias según criterios
     */
    public function count_transfers($args = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_stock_transfers';
        
        $query = "SELECT COUNT(*) FROM $table_name WHERE 1=1";
        $values = [];
        
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $values[] = $args['status'];
        }
        
        if (!empty($args['warehouse_id'])) {
            $query .= " AND (source_warehouse_id = %d OR target_warehouse_id = %d)";
            $values[] = $args['warehouse_id'];
            $values[] = $args['warehouse_id'];
        }
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return intval($wpdb->get_var($query));
    }

    /**
     * Actualizar stock sin registrar movimiento (para uso interno)
     */
    public function update_warehouse_stock_silent($warehouse_id, $product_id, $quantity_change) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gab_warehouse_product_stock';
        
        // Asegurar que existe el registro
        $this->ensure_warehouse_stock_exists($warehouse_id, $product_id);
        
        // Actualizar directamente
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET stock = stock + %d, 
                updated_at = %s 
            WHERE warehouse_id = %d AND product_id = %d",
            $quantity_change,
            current_time('mysql'),
            $warehouse_id,
            $product_id
        ));
        
        if ($result === false) {
            throw new Exception('Error al actualizar el stock: ' . $wpdb->last_error);
        }
        
        // Asegurar que el stock no sea negativo
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
            SET stock = 0 
            WHERE warehouse_id = %d AND product_id = %d AND stock < 0",
            $warehouse_id,
            $product_id
        ));
        
        do_action('gab_warehouse_stock_updated', $product_id, $warehouse_id);
        
        return true;
    }
        
}