<?php
/**
 * Vista de detalle de cotización
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permisos para ver esta página.', 'modulo-ventas'));
}

// Obtener ID de la cotización
$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$cotizacion_id) {
    wp_die(__('ID de cotización no válido.', 'modulo-ventas'));
}

// Obtener instancia de la base de datos
$db = Ventas_DB::get_instance();
$cotizacion = $db->get_cotizacion($cotizacion_id);

if (!$cotizacion) {
    wp_die(__('Cotización no encontrada.', 'modulo-ventas'));
}

// Obtener items de la cotización
$items = $db->get_items_cotizacion($cotizacion_id);

// Obtener datos del cliente
global $wpdb;
$tabla_clientes = $wpdb->prefix . 'sm_clientes';
$cliente = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM $tabla_clientes WHERE id = %d
", $cotizacion->cliente_id));

// Verificar si el plugin de gestión de almacenes está activo
$gestion_almacenes_activo = class_exists('Gestion_Almacenes_DB');
$almacenes = array();

if ($gestion_almacenes_activo) {
    global $gestion_almacenes_db;
    $almacenes = $gestion_almacenes_db->obtener_almacenes();
}

// Obtener estados disponibles
$estados = ventas_get_estados_cotizacion();

// Obtener vendedor
$vendedor = null;
if (!empty($cotizacion->vendedor_id)) {
    $vendedor = get_user_by('id', $cotizacion->vendedor_id);
}
?>

<div class="wrap mv-ver-cotizacion">
    <h1>
        <span class="dashicons dashicons-visibility"></span>
        <?php echo sprintf(__('Cotización %s', 'modulo-ventas'), esc_html($cotizacion->folio)); ?>
        <span class="estado-badge estado-<?php echo esc_attr($cotizacion->estado); ?>">
            <?php echo esc_html($estados[$cotizacion->estado] ?? ucfirst($cotizacion->estado)); ?>
        </span>
    </h1>
    
    <?php
    // Mostrar mensajes de estado
    if (isset($_GET['message'])) {
        $message = '';
        $type = 'success';
        
        switch ($_GET['message']) {
            case 'updated':
                $message = __('Cotización actualizada exitosamente.', 'modulo-ventas');
                break;
            case 'sent':
                $message = __('Cotización enviada por email exitosamente.', 'modulo-ventas');
                break;
            case 'duplicated':
                $message = __('Cotización duplicada exitosamente.', 'modulo-ventas');
                break;
        }
        
        if ($message) {
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    ?>
    
    <div class="mv-cotizacion-container">
        <!-- Columna principal -->
        <div class="mv-cotizacion-main">
            
            <!-- Información del Cliente -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Información del Cliente', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <?php if ($cliente): ?>
                        <div class="mv-cliente-detalle">
                            <div class="mv-info-grid">
                                <div class="mv-info-item">
                                    <label><?php _e('Razón Social:', 'modulo-ventas'); ?></label>
                                    <span><?php echo esc_html($cliente->razon_social); ?></span>
                                </div>
                                <div class="mv-info-item">
                                    <label><?php _e('RUT:', 'modulo-ventas'); ?></label>
                                    <span><?php echo esc_html(mv_formatear_rut($cliente->rut)); ?></span>
                                </div>
                                <div class="mv-info-item">
                                    <label><?php _e('Email:', 'modulo-ventas'); ?></label>
                                    <span><?php echo esc_html($cliente->email ?: '-'); ?></span>
                                </div>
                                <div class="mv-info-item">
                                    <label><?php _e('Teléfono:', 'modulo-ventas'); ?></label>
                                    <span><?php echo esc_html($cliente->telefono ?: '-'); ?></span>
                                </div>
                                <div class="mv-info-item">
                                    <label><?php _e('Dirección:', 'modulo-ventas'); ?></label>
                                    <span><?php echo nl2br(esc_html($cliente->direccion_facturacion ?: '-')); ?></span>
                                </div>
                                <div class="mv-info-item">
                                    <label><?php _e('Giro:', 'modulo-ventas'); ?></label>
                                    <span><?php echo esc_html($cliente->giro_comercial ?: '-'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="mv-no-data"><?php _e('Información del cliente no disponible.', 'modulo-ventas'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items de la Cotización -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Productos / Servicios', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-tabla-wrapper">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="column-item"><?php _e('Item', 'modulo-ventas'); ?></th>
                                    <th class="column-descripcion"><?php _e('Descripción', 'modulo-ventas'); ?></th>
                                    <?php if ($gestion_almacenes_activo): ?>
                                        <th class="column-almacen"><?php _e('Almacén', 'modulo-ventas'); ?></th>
                                        <th class="column-stock"><?php _e('Stock', 'modulo-ventas'); ?></th>
                                    <?php endif; ?>
                                    <th class="column-cantidad"><?php _e('Cant.', 'modulo-ventas'); ?></th>
                                    <th class="column-precio"><?php _e('Precio Unit.', 'modulo-ventas'); ?></th>
                                    <th class="column-descuento"><?php _e('Desc.', 'modulo-ventas'); ?></th>
                                    <th class="column-subtotal"><?php _e('Subtotal', 'modulo-ventas'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($items): ?>
                                    <?php foreach ($items as $index => $item): 
                                        // Obtener producto de la tabla de productos
                                        $tabla_productos = $wpdb->prefix . 'sm_productos';
                                        $producto = $wpdb->get_row($wpdb->prepare("
                                            SELECT * FROM $tabla_productos WHERE id = %d
                                        ", $item->producto_id));
                                    ?>
                                        <tr>
                                            <td class="column-item">
                                                <?php echo $index + 1; ?>
                                            </td>
                                            <td class="column-descripcion">
                                                <strong><?php echo esc_html($producto ? $producto->nombre : $item->nombre ?? 'Producto'); ?></strong>
                                                <?php if ($producto && $producto->codigo): ?>
                                                    <br><small>SKU: <?php echo esc_html($producto->codigo); ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($item->descripcion)): ?>
                                                    <br><small><?php echo esc_html($item->descripcion); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <?php if ($gestion_almacenes_activo): ?>
                                                <td class="column-almacen">
                                                    <?php
                                                    if (!empty($item->almacen_id) && !empty($almacenes)) {
                                                        foreach ($almacenes as $almacen) {
                                                            if ($almacen->id == $item->almacen_id) {
                                                                echo esc_html($almacen->name);
                                                                break;
                                                            }
                                                        }
                                                    } else {
                                                        echo '<span class="mv-text-muted">General</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="column-stock">
                                                    <?php
                                                    if ($producto && $gestion_almacenes_activo) {
                                                        $stock_info = array();
                                                        $stock_total = 0;
                                                        
                                                        // Si hay un almacén específico seleccionado
                                                        if (!empty($item->almacen_id)) {
                                                            $stock_almacen = $gestion_almacenes_db->get_warehouse_stock($item->almacen_id, $item->producto_id);
                                                            echo '<strong>' . intval($stock_almacen) . '</strong>';
                                                        } else {
                                                            // Mostrar stock total de todos los almacenes
                                                            $stock_por_almacen = $gestion_almacenes_db->get_product_warehouse_stock($item->producto_id);
                                                            
                                                            foreach ($almacenes as $almacen) {
                                                                $stock_cantidad = isset($stock_por_almacen[$almacen->id]) ? $stock_por_almacen[$almacen->id] : 0;
                                                                if ($stock_cantidad > 0) {
                                                                    $stock_info[] = $almacen->name . ': ' . $stock_cantidad;
                                                                    $stock_total += $stock_cantidad;
                                                                }
                                                            }
                                                            
                                                            if (!empty($stock_info)) {
                                                                echo '<span class="mv-stock-info" title="' . esc_attr(implode("\n", $stock_info)) . '">';
                                                                echo '<strong>' . $stock_total . '</strong>';
                                                                echo ' <span class="dashicons dashicons-info"></span>';
                                                                echo '</span>';
                                                            } else {
                                                                echo '<span class="mv-text-danger">0</span>';
                                                            }
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                            <?php endif; ?>
                                            
                                            <td class="column-cantidad"><?php echo esc_html($item->cantidad); ?></td>
                                            <td class="column-precio"><?php echo ventas_format_price($item->precio_unitario); ?></td>
                                            <td class="column-descuento">
                                                <?php 
                                                if ($item->descuento > 0) {
                                                    echo ventas_format_price($item->descuento);
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td class="column-subtotal">
                                                <strong><?php echo ventas_format_price($item->total); ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $gestion_almacenes_activo ? '8' : '6'; ?>" class="text-center">
                                            <?php _e('No hay productos en esta cotización.', 'modulo-ventas'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php _e('Subtotal:', 'modulo-ventas'); ?>
                                    </td>
                                    <td class="column-total">
                                        <?php echo ventas_format_price($cotizacion->subtotal); ?>
                                    </td>
                                </tr>
                                
                                <?php if ($cotizacion->descuento_global > 0): ?>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php 
                                        if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                            echo sprintf(__('Descuento (%s%%):', 'modulo-ventas'), number_format($cotizacion->descuento_global, 0));
                                        } else {
                                            _e('Descuento:', 'modulo-ventas');
                                        }
                                        ?>
                                    </td>
                                    <td class="column-total">
                                        -<?php 
                                        if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                            echo ventas_format_price($cotizacion->subtotal * ($cotizacion->descuento_global / 100));
                                        } else {
                                            echo ventas_format_price($cotizacion->descuento_global);
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($cotizacion->incluye_iva): ?>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php _e('IVA (19%):', 'modulo-ventas'); ?>
                                    </td>
                                    <td class="column-total">
                                        <?php 
                                        $base_imponible = $cotizacion->subtotal;
                                        if ($cotizacion->descuento_global > 0) {
                                            if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                                $base_imponible -= ($cotizacion->subtotal * ($cotizacion->descuento_global / 100));
                                            } else {
                                                $base_imponible -= $cotizacion->descuento_global;
                                            }
                                        }
                                        echo ventas_format_price($base_imponible * 0.19);
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($cotizacion->envio > 0): ?>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php _e('Envío:', 'modulo-ventas'); ?>
                                    </td>
                                    <td class="column-total">
                                        <?php echo ventas_format_price($cotizacion->envio); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr class="mv-totales-row mv-total-final">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <strong><?php _e('TOTAL:', 'modulo-ventas'); ?></strong>
                                    </td>
                                    <td class="column-total">
                                        <strong class="mv-total-amount"><?php echo ventas_format_price($cotizacion->total); ?></strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Información Adicional -->
            <?php if (!empty($cotizacion->observaciones) || !empty($cotizacion->notas_internas) || !empty($cotizacion->terminos_condiciones)): ?>
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Información Adicional', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <?php if (!empty($cotizacion->observaciones)): ?>
                        <div class="mv-info-section">
                            <h4><?php _e('Observaciones', 'modulo-ventas'); ?></h4>
                            <div class="mv-info-content">
                                <?php echo nl2br(esc_html($cotizacion->observaciones)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cotizacion->notas_internas)): ?>
                        <div class="mv-info-section">
                            <h4><?php _e('Notas Internas', 'modulo-ventas'); ?> 
                                <span class="mv-badge mv-badge-warning"><?php _e('Solo uso interno', 'modulo-ventas'); ?></span>
                            </h4>
                            <div class="mv-info-content mv-notas-internas">
                                <?php echo nl2br(esc_html($cotizacion->notas_internas)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($cotizacion->terminos_condiciones)): ?>
                        <div class="mv-info-section">
                            <h4><?php _e('Términos y Condiciones', 'modulo-ventas'); ?></h4>
                            <div class="mv-info-content">
                                <?php echo nl2br(esc_html($cotizacion->terminos_condiciones)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /mv-cotizacion-main -->

        <!-- Sidebar -->
        <div class="mv-cotizacion-sidebar">
            
            <!-- Información General -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Información General', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-info-list">
                        <div class="mv-info-row">
                            <label><?php _e('Folio:', 'modulo-ventas'); ?></label>
                            <span class="mv-folio"><?php echo esc_html($cotizacion->folio); ?></span>
                        </div>
                        <div class="mv-info-row">
                            <label><?php _e('Fecha:', 'modulo-ventas'); ?></label>
                            <span><?php echo date_i18n(get_option('date_format'), strtotime($cotizacion->fecha)); ?></span>
                        </div>
                        <?php if (!empty($cotizacion->fecha_expiracion)): ?>
                        <div class="mv-info-row">
                            <label><?php _e('Válida hasta:', 'modulo-ventas'); ?></label>
                            <span><?php echo date_i18n(get_option('date_format'), strtotime($cotizacion->fecha_expiracion)); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($cotizacion->plazo_pago)): ?>
                        <div class="mv-info-row">
                            <label><?php _e('Plazo de pago:', 'modulo-ventas'); ?></label>
                            <span><?php echo esc_html(mv_get_plazo_pago_label($cotizacion->plazo_pago)); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($vendedor): ?>
                        <div class="mv-info-row">
                            <label><?php _e('Vendedor:', 'modulo-ventas'); ?></label>
                            <span><?php echo esc_html($vendedor->display_name); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Resumen de Totales -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Resumen de Totales', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-resumen-totales">
                        <div class="mv-total-row">
                            <span><?php _e('Subtotal:', 'modulo-ventas'); ?></span>
                            <span><?php echo ventas_format_price($cotizacion->subtotal); ?></span>
                        </div>
                        <?php if ($cotizacion->descuento_global > 0): ?>
                        <div class="mv-total-row">
                            <span><?php _e('Descuento:', 'modulo-ventas'); ?></span>
                            <span>-<?php 
                                if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                    echo ventas_format_price($cotizacion->subtotal * ($cotizacion->descuento_global / 100));
                                } else {
                                    echo ventas_format_price($cotizacion->descuento_global);
                                }
                            ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($cotizacion->incluye_iva): ?>
                        <div class="mv-total-row">
                            <span><?php _e('IVA:', 'modulo-ventas'); ?></span>
                            <span><?php 
                                $base_imponible = $cotizacion->subtotal;
                                if ($cotizacion->descuento_global > 0) {
                                    if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                        $base_imponible -= ($cotizacion->subtotal * ($cotizacion->descuento_global / 100));
                                    } else {
                                        $base_imponible -= $cotizacion->descuento_global;
                                    }
                                }
                                echo ventas_format_price($base_imponible * 0.19);
                            ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($cotizacion->envio > 0): ?>
                        <div class="mv-total-row">
                            <span><?php _e('Envío:', 'modulo-ventas'); ?></span>
                            <span><?php echo ventas_format_price($cotizacion->envio); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="mv-total-row mv-total-final">
                            <span><?php _e('TOTAL:', 'modulo-ventas'); ?></span>
                            <span><?php echo ventas_format_price($cotizacion->total); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Acciones', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-actions">
                        <a href="<?php echo admin_url('admin.php?page=ventas-editar-cotizacion&id=' . $cotizacion_id); ?>" 
                           class="button button-primary button-large mv-btn-full">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Editar Cotización', 'modulo-ventas'); ?>
                        </a>
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=duplicate&id=' . $cotizacion_id), 'duplicate_cotizacion_' . $cotizacion_id); ?>" 
                           class="button button-large mv-btn-full">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php _e('Duplicar', 'modulo-ventas'); ?>
                        </a>
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=send&id=' . $cotizacion_id), 'send_cotizacion_' . $cotizacion_id); ?>" 
                           class="button button-large mv-btn-full">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Enviar por Email', 'modulo-ventas'); ?>
                        </a>
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=pdf&id=' . $cotizacion_id), 'pdf_cotizacion_' . $cotizacion_id); ?>" 
                           class="button button-large mv-btn-full" target="_blank">
                            <span class="dashicons dashicons-pdf"></span>
                            <?php _e('Descargar PDF', 'modulo-ventas'); ?>
                        </a>
                        
                        <?php if ($cotizacion->estado === 'pendiente' || $cotizacion->estado === 'enviada'): ?>
                        <hr class="mv-separator">
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=accept&id=' . $cotizacion_id), 'accept_cotizacion_' . $cotizacion_id); ?>" 
                           class="button button-large mv-btn-full mv-btn-success">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Marcar como Aceptada', 'modulo-ventas'); ?>
                        </a>
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=reject&id=' . $cotizacion_id), 'reject_cotizacion_' . $cotizacion_id); ?>" 
                           class="button button-large mv-btn-full mv-btn-danger">
                            <span class="dashicons dashicons-no"></span>
                            <?php _e('Marcar como Rechazada', 'modulo-ventas'); ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($cotizacion->estado === 'aprobada' || $cotizacion->estado === 'aceptada'): ?>
                        <hr class="mv-separator">
                        
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=convert&id=' . $cotizacion_id), 'convert_cotizacion_' . $cotizacion_id); ?>" 
                           class="button button-large button-primary mv-btn-full">
                            <span class="dashicons dashicons-cart"></span>
                            <?php _e('Convertir a Venta', 'modulo-ventas'); ?>
                        </a>
                        <?php endif; ?>
                        
                        <hr class="mv-separator">
                        
                        <a href="#" onclick="window.print(); return false;" 
                           class="button button-large mv-btn-full">
                            <span class="dashicons dashicons-printer"></span>
                            <?php _e('Imprimir', 'modulo-ventas'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Historial -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Historial', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-historial">
                        <div class="mv-historial-item">
                            <strong><?php _e('Creada:', 'modulo-ventas'); ?></strong><br>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cotizacion->fecha)); ?>
                        </div>
                        
                        <?php if (!empty($cotizacion->updated_at) && $cotizacion->updated_at !== $cotizacion->fecha): ?>
                        <div class="mv-historial-item">
                            <strong><?php _e('Última modificación:', 'modulo-ventas'); ?></strong><br>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cotizacion->updated_at)); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($cotizacion->orden_id): ?>
                        <div class="mv-historial-item">
                            <strong><?php _e('Convertida a venta:', 'modulo-ventas'); ?></strong><br>
                            <a href="<?php echo admin_url('admin.php?page=ventas-ver-venta&id=' . $cotizacion->orden_id); ?>">
                                <?php echo sprintf(__('Orden #%d', 'modulo-ventas'), $cotizacion->orden_id); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /mv-cotizacion-sidebar -->

    </div><!-- /mv-cotizacion-container -->
</div><!-- /wrap -->

<style>
/* Layout principal */
.mv-cotizacion-container {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    margin-top: 20px;
}

.mv-cotizacion-main .postbox,
.mv-cotizacion-sidebar .postbox {
    margin-bottom: 20px;
}

/* Estados */
.estado-badge {
    display: inline-block;
    padding: 4px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 3px;
    margin-left: 10px;
}

.estado-pendiente {
    background-color: #f0ad4e;
    color: #fff;
}

.estado-enviada {
    background-color: #5bc0de;
    color: #fff;
}

.estado-aceptada,
.estado-aprobada {
    background-color: #5cb85c;
    color: #fff;
}

.estado-rechazada {
    background-color: #d9534f;
    color: #fff;
}

.estado-vencida {
    background-color: #868686;
    color: #fff;
}

/* Info del cliente */
.mv-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.mv-info-item {
    display: flex;
    flex-direction: column;
}

.mv-info-item label {
    font-weight: 600;
    color: #646970;
    margin-bottom: 3px;
}

.mv-info-item span {
    color: #2c3338;
}

/* Tabla de productos */
.mv-tabla-wrapper {
    overflow-x: auto;
}

.column-item {
    width: 50px;
    text-align: center;
}

.column-descripcion {
    width: 35%;
}

.column-almacen {
    width: 120px;
}

.column-stock {
    width: 80px;
    text-align: center;
}

.column-cantidad {
    width: 60px;
    text-align: center;
}

.column-precio {
    width: 100px;
    text-align: right;
}

.column-descuento {
    width: 80px;
    text-align: right;
}

.column-subtotal {
    width: 100px;
    text-align: right;
}

.column-total {
    text-align: right;
    font-weight: 600;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

/* Stock info */
.mv-stock-info {
    cursor: help;
    position: relative;
}

.mv-stock-info .dashicons {
    font-size: 14px;
    vertical-align: middle;
    color: #2271b1;
}

.mv-stock-info[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background-color: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    white-space: pre-line;
    font-size: 12px;
    z-index: 10000;
    min-width: 150px;
    text-align: left;
    margin-bottom: 5px;
}

/* Totales */
.mv-totales-row td {
    padding: 8px 10px;
    border-top: 1px solid #f0f0f1;
}

.mv-total-final td {
    padding: 12px 10px;
    border-top: 2px solid #dcdcde;
    font-size: 16px;
}

.mv-total-amount {
    color: #2271b1;
    font-size: 18px;
}

/* Información adicional */
.mv-info-section {
    margin-bottom: 20px;
}

.mv-info-section:last-child {
    margin-bottom: 0;
}

.mv-info-section h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
}

.mv-info-content {
    padding: 10px;
    background-color: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 4px;
}

.mv-notas-internas {
    background-color: #fff9e6;
    border-color: #f0ad4e;
}

/* Sidebar info */
.mv-info-list .mv-info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.mv-info-list .mv-info-row:last-child {
    border-bottom: none;
}

.mv-folio {
    font-weight: 600;
    color: #2271b1;
}

/* Resumen totales */
.mv-resumen-totales .mv-total-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f1;
}

.mv-resumen-totales .mv-total-final {
    border-bottom: none;
    border-top: 2px solid #dcdcde;
    padding-top: 10px;
    margin-top: 8px;
    font-size: 16px;
    font-weight: 600;
}

/* Acciones */
.mv-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mv-btn-full {
    width: 100%;
    justify-content: center;
}

.mv-btn-success {
    background-color: #5cb85c;
    border-color: #4cae4c;
    color: white;
}

.mv-btn-success:hover {
    background-color: #449d44;
    border-color: #398439;
    color: white;
}

.mv-btn-danger {
    background-color: #d9534f;
    border-color: #d43f3a;
    color: white;
}

.mv-btn-danger:hover {
    background-color: #c9302c;
    border-color: #ac2925;
    color: white;
}

.mv-separator {
    margin: 15px 0;
    border: 0;
    border-top: 1px solid #dcdcde;
}

/* Historial */
.mv-historial-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
    font-size: 13px;
}

.mv-historial-item:last-child {
    border-bottom: none;
}

/* Badges */
.mv-badge {
    display: inline-block;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 3px;
}

.mv-badge-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

/* Utilidades */
.mv-text-muted {
    color: #646970;
}

.mv-text-danger {
    color: #d63638;
}

.mv-no-data {
    color: #646970;
    font-style: italic;
    padding: 20px;
    text-align: center;
}

/* Responsive */
@media screen and (max-width: 1200px) {
    .mv-cotizacion-container {
        grid-template-columns: 1fr;
    }
    
    .mv-cotizacion-sidebar {
        order: -1;
    }
}

@media screen and (max-width: 782px) {
    .mv-info-grid {
        grid-template-columns: 1fr;
    }
    
    .mv-tabla-wrapper {
        margin: 0 -12px;
    }
    
    .mv-tabla-wrapper table {
        font-size: 12px;
    }
}

/* Estilos para impresión */
@media print {
    .wrap h1 a,
    #adminmenumain,
    #wpadminbar,
    #wpfooter,
    .postbox .hndle,
    .mv-cotizacion-sidebar,
    .mv-btn-full,
    .mv-actions,
    .mv-notas-internas {
        display: none !important;
    }
    
    .wrap {
        margin: 0;
    }
    
    .mv-cotizacion-container {
        display: block;
    }
    
    .postbox {
        border: none;
        box-shadow: none;
        margin-bottom: 20px;
    }
    
    .postbox .inside {
        padding: 0;
    }
    
    .estado-badge {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Confirmar acciones críticas
    $('a[href*="action=reject"], a[href*="action=delete"]').on('click', function(e) {
        if (!confirm('<?php _e('¿Está seguro de realizar esta acción?', 'modulo-ventas'); ?>')) {
            e.preventDefault();
        }
    });
    
    // Mostrar/ocultar tooltips
    $('.mv-stock-info').on('mouseenter', function() {
        $(this).addClass('show-tooltip');
    }).on('mouseleave', function() {
        $(this).removeClass('show-tooltip');
    });
});
</script>