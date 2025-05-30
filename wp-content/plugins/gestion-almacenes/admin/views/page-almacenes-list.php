<?php
/**
 * Vista mejorada para listado de almacenes
 * wp-content/plugins/gestion-almacenes/admin/views/page-almacenes-list.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función para mostrar el listado de almacenes con acciones CRUD
 */
function gab_mostrar_listado_almacenes() {
    global $gestion_almacenes_db;
    
    // Procesar acciones de eliminación
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['warehouse_id'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_warehouse_' . $_GET['warehouse_id'])) {
            $warehouse_id = intval($_GET['warehouse_id']);
            $result = gab_eliminar_almacen($warehouse_id);
            
            if ($result['success']) {
                $redirect_url = add_query_arg(array(
                    'page' => 'gab-warehouse-management',
                    'status' => 'deleted'
                ), admin_url('admin.php'));
            } else {
                $redirect_url = add_query_arg(array(
                    'page' => 'gab-warehouse-management',
                    'status' => 'error',
                    'message' => $result['message']
                ), admin_url('admin.php'));
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    $almacenes = $gestion_almacenes_db->obtener_almacenes();
    ?>
    
    <div class="wrap gab-admin-page">
        <div class="gab-section-header">
            <h1><?php echo esc_html(__('Gestión de Almacenes', 'gestion-almacenes')); ?></h1>
            <p><?php echo esc_html(__('Administra todos los almacenes registrados en el sistema.', 'gestion-almacenes')); ?></p>
        </div>

        <?php gab_mostrar_mensajes_estado(); ?>

        <!-- Estadísticas Rápidas -->
        <div class="gab-stats-grid">
            <div class="gab-stat-card">
                <span class="stat-number"><?php echo count($almacenes ?: array()); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Almacenes', 'gestion-almacenes'); ?></span>
            </div>
            <div class="gab-stat-card">
                <span class="stat-number"><?php echo gab_contar_productos_con_stock(); ?></span>
                <span class="stat-label"><?php esc_html_e('Productos con Stock', 'gestion-almacenes'); ?></span>
            </div>
            <div class="gab-stat-card">
                <span class="stat-number"><?php echo gab_contar_productos_stock_bajo(); ?></span>
                <span class="stat-label"><?php esc_html_e('Stock Bajo', 'gestion-almacenes'); ?></span>
            </div>
        </div>

        <!-- Acciones Principales -->
        <div class="gab-form-section">
            <div class="gab-form-row">
                <div class="gab-form-group">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gab-add-new-warehouse')); ?>" 
                        class="button button-primary">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php esc_html_e('Agregar Nuevo Almacén', 'gestion-almacenes'); ?>
                    </a>
                </div>
                <div class="gab-form-group">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gab-stock-report')); ?>" 
                        class="button button-secondary">
                        <span class="dashicons dashicons-chart-bar" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php esc_html_e('Ver Reporte de Stock', 'gestion-almacenes'); ?>
                    </a>
                </div>
                <div class="gab-form-group">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gab-transfer-stock')); ?>" 
                        class="button button-secondary">
                        <span class="dashicons dashicons-randomize" style="vertical-align: middle; margin-right: 5px;"></span>
                        <?php esc_html_e('Transferir Stock', 'gestion-almacenes'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tabla de Almacenes -->
        <?php if ($almacenes && count($almacenes) > 0): ?>
            <table class="gab-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php esc_html_e('ID', 'gestion-almacenes'); ?></th>
                        <th><?php esc_html_e('Nombre', 'gestion-almacenes'); ?></th>
                        <th><?php esc_html_e('Ubicación', 'gestion-almacenes'); ?></th>
                        <th><?php esc_html_e('Contacto', 'gestion-almacenes'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Productos', 'gestion-almacenes'); ?></th>
                        <th style="width: 100px;"><?php esc_html_e('Stock Total', 'gestion-almacenes'); ?></th>
                        <th style="width: 150px;"><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($almacenes as $almacen): ?>
                        <?php
                        $stats = gab_obtener_estadisticas_almacen($almacen->id);
                        $ubicacion = implode(', ', array_filter([
                            $almacen->comuna,
                            $almacen->ciudad,
                            $almacen->region
                        ]));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($almacen->id); ?></strong></td>
                            <td>
                                <strong><?php echo esc_html($almacen->name); ?></strong>
                                <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                    <?php echo esc_html($almacen->address); ?>
                                </div>
                            </td>
                            <td>
                                <div><?php echo esc_html($ubicacion); ?></div>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo esc_html($almacen->pais); ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="dashicons dashicons-email" style="font-size: 14px; vertical-align: middle;"></span>
                                    <a href="mailto:<?php echo esc_attr($almacen->email); ?>" style="text-decoration: none;">
                                        <?php echo esc_html($almacen->email); ?>
                                    </a>
                                </div>
                                <?php if (!empty($almacen->telefono)): ?>
                                <div style="margin-top: 2px;">
                                    <span class="dashicons dashicons-phone" style="font-size: 14px; vertical-align: middle;"></span>
                                    <?php echo esc_html($almacen->telefono); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <strong><?php echo esc_html($stats['productos']); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <strong><?php echo esc_html($stats['stock_total']); ?></strong>
                                <?php if ($stats['stock_bajo'] > 0): ?>
                                    <div>
                                        <span class="gab-badge stock-low">
                                            <?php echo sprintf(esc_html__('%d bajo', 'gestion-almacenes'), $stats['stock_bajo']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="<?php echo esc_url(gab_url_editar_almacen($almacen->id)); ?>" 
                                    class="button button-small" 
                                    title="<?php esc_attr_e('Editar almacén', 'gestion-almacenes'); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                
                                <a href="<?php echo esc_url(gab_url_ver_stock_almacen($almacen->id)); ?>" 
                                    class="button button-small" 
                                    title="<?php esc_attr_e('Ver stock', 'gestion-almacenes'); ?>">
                                    <span class="dashicons dashicons-list-view"></span>
                                </a>
                                
                                <?php if (gab_puede_eliminar_almacen($almacen->id)): ?>
                                <a href="<?php echo esc_url(gab_url_eliminar_almacen($almacen->id)); ?>" 
                                    class="button button-small gab-delete-confirm" 
                                    data-confirm-message="<?php echo esc_attr(sprintf(
                                        __('¿Está seguro de eliminar el almacén "%s"? Esta acción no se puede deshacer.', 'gestion-almacenes'),
                                        $almacen->name
                                    )); ?>"
                                    title="<?php esc_attr_e('Eliminar almacén', 'gestion-almacenes'); ?>"
                                    style="color: #d63638;">
                                    <span class="dashicons dashicons-trash"></span>
                                </a>
                                <?php else: ?>
                                <span class="button button-small" 
                                        style="cursor: not-allowed; opacity: 0.5;" 
                                        title="<?php esc_attr_e('No se puede eliminar: tiene productos con stock', 'gestion-almacenes'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="gab-message info">
                <h3><?php esc_html_e('No hay almacenes registrados', 'gestion-almacenes'); ?></h3>
                <p><?php esc_html_e('Comienza agregando tu primer almacén para gestionar el stock de tus productos.', 'gestion-almacenes'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=gab-add-new-warehouse')); ?>" 
                    class="button button-primary">
                    <?php esc_html_e('Agregar Primer Almacén', 'gestion-almacenes'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php
}

/**
 * Funciones auxiliares para el listado
 */

function gab_mostrar_mensajes_estado() {
    if (isset($_GET['status'])) {
        $status = sanitize_key($_GET['status']);
        $message = '';
        $type = 'success';

        switch ($status) {
            case 'success':
                $message = __('Almacén guardado correctamente.', 'gestion-almacenes');
                break;
            case 'updated':
                $message = __('Almacén actualizado correctamente.', 'gestion-almacenes');
                break;
            case 'deleted':
                $message = __('Almacén eliminado correctamente.', 'gestion-almacenes');
                break;
            case 'error':
                $type = 'error';
                $error_code = isset($_GET['message']) ? sanitize_key($_GET['message']) : '';
                
                switch ($error_code) {
                    case 'nombre_vacio':
                        $message = __('Error: El nombre del almacén no puede estar vacío.', 'gestion-almacenes');
                        break;
                    case 'error_db':
                        $message = __('Error de base de datos al procesar la solicitud.', 'gestion-almacenes');
                        break;
                    case 'cannot_delete':
                        $message = __('No se puede eliminar el almacén porque tiene productos con stock.', 'gestion-almacenes');
                        break;
                    default:
                        $message = __('Ocurrió un error al procesar la solicitud.', 'gestion-almacenes');
                }
                break;
        }

        if ($message) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
            echo '<p>' . esc_html($message) . '</p>';
            echo '</div>';
        }
    }
}

function gab_contar_productos_con_stock() {
    global $gestion_almacenes_db;
    $productos_con_stock = $gestion_almacenes_db->get_all_products_warehouse_stock();
    $productos_unicos = array();
    
    if ($productos_con_stock) {
        foreach ($productos_con_stock as $item) {
            if (!in_array($item->product_id, $productos_unicos)) {
                $productos_unicos[] = $item->product_id;
            }
        }
    }
    
    return count($productos_unicos);
}

function gab_contar_productos_stock_bajo() {
    global $gestion_almacenes_db;
    $threshold = get_option('gab_low_stock_threshold', 5);
    $productos_stock_bajo = $gestion_almacenes_db->get_low_stock_products($threshold);
    return is_array($productos_stock_bajo) ? count($productos_stock_bajo) : 0;
}

function gab_obtener_estadisticas_almacen($warehouse_id) {
    global $gestion_almacenes_db;
    
    $productos_almacen = $gestion_almacenes_db->get_all_products_warehouse_stock();
    $productos = 0;
    $stock_total = 0;
    $stock_bajo = 0;
    $threshold = get_option('gab_low_stock_threshold', 5);
    
    if ($productos_almacen) {
        foreach ($productos_almacen as $item) {
            if ($item->warehouse_id == $warehouse_id) {
                $productos++;
                $stock_total += intval($item->stock);
                
                if (intval($item->stock) <= $threshold && intval($item->stock) > 0) {
                    $stock_bajo++;
                }
            }
        }
    }
    
    return array(
        'productos' => $productos,
        'stock_total' => $stock_total,
        'stock_bajo' => $stock_bajo
    );
}

function gab_url_editar_almacen($warehouse_id) {
    return add_query_arg(array(
        'page' => 'gab-edit-warehouse',
        'warehouse_id' => $warehouse_id
    ), admin_url('admin.php'));
}

function gab_url_ver_stock_almacen($warehouse_id) {
    return add_query_arg(array(
        'page' => 'gab-stock-report',
        'warehouse_filter' => $warehouse_id
    ), admin_url('admin.php'));
}

function gab_url_eliminar_almacen($warehouse_id) {
    return wp_nonce_url(
        add_query_arg(array(
            'page' => 'gab-warehouse-management',
            'action' => 'delete',
            'warehouse_id' => $warehouse_id
        ), admin_url('admin.php')),
        'delete_warehouse_' . $warehouse_id
    );
}

function gab_puede_eliminar_almacen($warehouse_id) {
    global $gestion_almacenes_db;
    
    // Verificar si el almacén tiene productos con stock
    $productos_con_stock = $gestion_almacenes_db->get_all_products_warehouse_stock();
    
    if ($productos_con_stock) {
        foreach ($productos_con_stock as $item) {
            if ($item->warehouse_id == $warehouse_id && intval($item->stock) > 0) {
                return false; // No se puede eliminar si tiene stock
            }
        }
    }
    
    return true;
}

function gab_eliminar_almacen($warehouse_id) {
    global $wpdb, $gestion_almacenes_db;
    
    // Verificar si se puede eliminar
    if (!gab_puede_eliminar_almacen($warehouse_id)) {
        return array(
            'success' => false,
            'message' => 'cannot_delete'
        );
    }
    
    // Eliminar registros de stock (aunque estén en 0)
    $table_stock = $wpdb->prefix . 'gab_warehouse_product_stock';
    $wpdb->delete($table_stock, array('warehouse_id' => $warehouse_id), array('%d'));
    
    // Eliminar almacén
    $table_warehouses = $wpdb->prefix . 'gab_warehouses';
    $result = $wpdb->delete($table_warehouses, array('id' => $warehouse_id), array('%d'));
    
    if ($result === false) {
        return array(
            'success' => false,
            'message' => 'error_db'
        );
    }
    
    return array('success' => true);
}