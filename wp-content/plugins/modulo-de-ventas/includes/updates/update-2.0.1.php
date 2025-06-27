<?php
/**
 * Actualización de base de datos - Versión 2.0.1
 * Agrega campos faltantes a la tabla de clientes
 */

function mv_update_2_0_1_add_missing_fields() {
    global $wpdb;
    
    $tabla_clientes = $wpdb->prefix . 'mv_clientes';
    
    // Verificar y agregar columnas faltantes
    $columnas_existentes = $wpdb->get_col("SHOW COLUMNS FROM $tabla_clientes");
    
    // Agregar sitio_web si no existe
    if (!in_array('sitio_web', $columnas_existentes)) {
        $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN sitio_web VARCHAR(255) DEFAULT NULL AFTER email");
    }
    
    // Agregar ciudad si no existe (o renombrar ciudad_facturacion)
    if (!in_array('ciudad', $columnas_existentes)) {
        if (in_array('ciudad_facturacion', $columnas_existentes)) {
            // Si existe ciudad_facturacion, crear ciudad como alias
            $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN ciudad VARCHAR(100) DEFAULT NULL AFTER direccion_facturacion");
            // Copiar datos existentes
            $wpdb->query("UPDATE $tabla_clientes SET ciudad = ciudad_facturacion WHERE ciudad_facturacion IS NOT NULL");
        } else {
            $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN ciudad VARCHAR(100) DEFAULT NULL AFTER direccion_facturacion");
        }
    }
    
    // Agregar codigo_postal si no existe
    if (!in_array('codigo_postal', $columnas_existentes)) {
        $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN codigo_postal VARCHAR(20) DEFAULT NULL AFTER region_facturacion");
    }
    
    // Agregar credito_autorizado si no existe
    if (!in_array('credito_autorizado', $columnas_existentes)) {
        $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN credito_autorizado DECIMAL(10,2) DEFAULT 0.00 AFTER estado");
    }
    
    // Agregar fecha_actualizacion si no existe (aunque fecha_modificacion puede existir)
    if (!in_array('fecha_actualizacion', $columnas_existentes) && !in_array('fecha_modificacion', $columnas_existentes)) {
        $wpdb->query("ALTER TABLE $tabla_clientes ADD COLUMN fecha_actualizacion DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
    }
}

// Ejecutar la actualización
mv_update_2_0_1_add_missing_fields();