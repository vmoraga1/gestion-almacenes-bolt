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
$db = new Modulo_Ventas_DB();
$cotizacion = $db->obtener_cotizacion($cotizacion_id);

if (!$cotizacion) {
    wp_die(__('Cotización no encontrada.', 'modulo-ventas'));
}

// Obtener items de la cotización
$items = $db->obtener_items_cotizacion($cotizacion_id);

// Obtener datos del cliente
$cliente = $db->obtener_cliente($cotizacion->cliente_id);

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
                                        // Obtener producto de WooCommerce
                                        $producto = null;
                                        $nombre_producto = '';
                                        $sku_producto = '';
                                        
                                        if ($item->producto_id) {
                                            $producto = wc_get_product($item->producto_id);
                                            
                                            if ($producto) {
                                                $nombre_producto = $producto->get_name();
                                                $sku_producto = $producto->get_sku();
                                            } else {
                                                // Si no se encuentra en WooCommerce, usar los datos guardados
                                                $nombre_producto = $item->nombre ?: 'Producto #' . $item->producto_id;
                                                $sku_producto = $item->sku ?: 'N/A';
                                            }
                                        } else {
                                            // Producto personalizado (sin ID de WooCommerce)
                                            $nombre_producto = $item->nombre ?: 'Producto personalizado';
                                            $sku_producto = $item->sku ?: 'N/A';
                                        }
                                    ?>
                                        <tr>
                                            <td class="column-item">
                                                <?php echo $index + 1; ?>
                                            </td>
                                            <td class="column-descripcion">
                                                <strong><?php echo esc_html($nombre_producto); ?></strong>
                                                <?php if ($sku_producto && $sku_producto !== 'N/A'): ?>
                                                    <br><small>SKU: <?php echo esc_html($sku_producto); ?></small>
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
                                                    $stock_disponible = 0;
                                                    
                                                    if ($producto && $producto->managing_stock()) {
                                                        if (!empty($item->almacen_id) && function_exists('mv_get_stock_almacen')) {
                                                            $stock_disponible = mv_get_stock_almacen($item->producto_id, $item->almacen_id);
                                                        } else {
                                                            $stock_disponible = $producto->get_stock_quantity();
                                                        }
                                                    }
                                                    
                                                    echo '<strong>' . number_format($stock_disponible, 2) . '</strong>';
                                                    ?>
                                                </td>
                                            <?php endif; ?>
                                            
                                            <td class="column-cantidad"><?php echo esc_html($item->cantidad); ?></td>
                                            <td class="column-precio"><?php echo wc_price($item->precio_unitario); ?></td>
                                            <td class="column-descuento">
                                                <?php 
                                                $descuento_item = 0;
                                                if (isset($item->descuento_monto) && $item->descuento_monto > 0) {
                                                    $descuento_item = $item->descuento_monto;
                                                    echo wc_price($descuento_item);
                                                    if (isset($item->tipo_descuento) && $item->tipo_descuento == 'monto') {
                                                        echo ' <small>($)</small>';
                                                    }
                                                } elseif (isset($item->descuento_porcentaje) && $item->descuento_porcentaje > 0) {
                                                    echo esc_html($item->descuento_porcentaje) . '%';
                                                    $descuento_item = ($item->precio_unitario * $item->cantidad * $item->descuento_porcentaje) / 100;
                                                    echo '<br><small>(' . wc_price($descuento_item) . ')</small>';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td class="column-subtotal">
                                                <strong><?php echo wc_price($item->total); ?></strong>
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
                                        <?php echo wc_price($cotizacion->subtotal ?? 0); ?>
                                    </td>
                                </tr>
                                
                                <?php 
                                // Calcular descuento global
                                $descuento_global_monto = 0;
                                $tiene_descuento = false;
                                
                                if (isset($cotizacion->descuento_monto) && $cotizacion->descuento_monto > 0) {
                                    $descuento_global_monto = $cotizacion->descuento_monto;
                                    $tiene_descuento = true;
                                } elseif (isset($cotizacion->descuento_porcentaje) && $cotizacion->descuento_porcentaje > 0) {
                                    $descuento_global_monto = ($cotizacion->subtotal * $cotizacion->descuento_porcentaje) / 100;
                                    $tiene_descuento = true;
                                }
                                ?>
                                
                                <?php if ($tiene_descuento): ?>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php 
                                        if (isset($cotizacion->descuento_porcentaje) && $cotizacion->descuento_porcentaje > 0) {
                                            echo sprintf(__('Descuento (%s%%):', 'modulo-ventas'), number_format($cotizacion->descuento_porcentaje, 0));
                                        } else {
                                            _e('Descuento:', 'modulo-ventas');
                                        }
                                        ?>
                                    </td>
                                    <td class="column-total">
                                        -<?php echo wc_price($descuento_global_monto); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if (isset($cotizacion->costo_envio) && $cotizacion->costo_envio > 0): ?>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php _e('Envío:', 'modulo-ventas'); ?>
                                    </td>
                                    <td class="column-total">
                                        <?php echo wc_price($cotizacion->costo_envio); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php if (isset($cotizacion->incluye_iva) && $cotizacion->incluye_iva): ?>
                                <tr class="mv-totales-row">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <?php _e('IVA (19%):', 'modulo-ventas'); ?>
                                    </td>
                                    <td class="column-total">
                                        <?php 
                                        $base_imponible = $cotizacion->subtotal ?? 0;
                                        if ($tiene_descuento) {
                                            $base_imponible -= $descuento_global_monto;
                                        }
                                        if (isset($cotizacion->costo_envio) && $cotizacion->costo_envio > 0) {
                                            $base_imponible += $cotizacion->costo_envio;
                                        }
                                        $iva = $cotizacion->impuesto_monto ?? ($base_imponible * 0.19);
                                        echo wc_price($iva);
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <tr class="mv-totales-row mv-total-final">
                                    <td colspan="<?php echo $gestion_almacenes_activo ? '7' : '5'; ?>" class="text-right">
                                        <strong><?php _e('TOTAL:', 'modulo-ventas'); ?></strong>
                                    </td>
                                    <td class="column-total">
                                        <strong class="mv-total-amount"><?php echo wc_price($cotizacion->total ?? 0); ?></strong>
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
                    <span><?php _e('Resumen', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <table class="mv-totales-table">
                        <tbody>
                            <?php 
                            // Calcular subtotal
                            $subtotal = $cotizacion->subtotal ?? 0;
                            
                            // Calcular descuento global
                            $descuento_global = 0;
                            if (isset($cotizacion->descuento_monto) && $cotizacion->descuento_monto > 0) {
                                $descuento_global = $cotizacion->descuento_monto;
                            } elseif (isset($cotizacion->descuento_porcentaje) && $cotizacion->descuento_porcentaje > 0) {
                                $descuento_global = ($subtotal * $cotizacion->descuento_porcentaje) / 100;
                            }
                            
                            // Subtotal con descuento
                            $subtotal_con_descuento = $subtotal - $descuento_global;
                            
                            // Costo de envío
                            $costo_envio = $cotizacion->costo_envio ?? 0;
                            
                            // Subtotal con envío
                            $subtotal_con_envio = $subtotal_con_descuento + $costo_envio;
                            
                            // IVA
                            $iva = 0;
                            if (isset($cotizacion->incluye_iva) && $cotizacion->incluye_iva) {
                                $iva = $cotizacion->impuesto_monto ?? ($subtotal_con_envio * 0.19);
                            }
                            
                            // Total
                            $total = $cotizacion->total ?? ($subtotal_con_envio + $iva);
                            ?>
                            
                            <tr>
                                <th><?php _e('Subtotal:', 'modulo-ventas'); ?></th>
                                <td><?php echo wc_price($subtotal); ?></td>
                            </tr>
                            
                            <?php if ($descuento_global > 0): ?>
                            <tr>
                                <th><?php _e('Descuento:', 'modulo-ventas'); ?></th>
                                <td>-<?php echo wc_price($descuento_global); ?></td>
                            </tr>
                            <tr>
                                <th><?php _e('Subtotal con descuento:', 'modulo-ventas'); ?></th>
                                <td><?php echo wc_price($subtotal_con_descuento); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($costo_envio > 0): ?>
                            <tr>
                                <th><?php _e('Envío:', 'modulo-ventas'); ?></th>
                                <td><?php echo wc_price($costo_envio); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if ($iva > 0): ?>
                            <tr>
                                <th><?php _e('IVA (19%):', 'modulo-ventas'); ?></th>
                                <td><?php echo wc_price($iva); ?></td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr class="total">
                                <th><?php _e('Total:', 'modulo-ventas'); ?></th>
                                <td><strong><?php echo wc_price($total); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
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
                    <?php 
                    // Generar URLs para PDF
                    $pdf_handler = new Modulo_Ventas_PDF_Handler();
                    $preview_url = $pdf_handler->generar_url_preview($cotizacion_id);
                    $download_url = $pdf_handler->generar_url_descarga($cotizacion_id);
                    ?>

                    <div class="mv-pdf-actions">
                        <button type="button" class="button button-secondary mv-btn-pdf-preview" 
                                data-cotizacion-id="<?php echo $cotizacion_id; ?>"
                                data-preview-url="<?php echo esc_url($preview_url); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Ver PDF', 'modulo-ventas'); ?>
                        </button>
                        
                        <button type="button" class="button button-secondary mv-btn-pdf-download" 
                                data-cotizacion-id="<?php echo $cotizacion_id; ?>"
                                data-download-url="<?php echo esc_url($download_url); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Descargar PDF', 'modulo-ventas'); ?>
                        </button>
                        
                        <button type="button" class="button button-secondary mv-btn-email-pdf" 
                                data-cotizacion-id="<?php echo $cotizacion_id; ?>">
                            <span class="dashicons dashicons-email"></span>
                            <?php _e('Enviar por Email', 'modulo-ventas'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <?php
            /*// ===== DEBUG TEMPORAL - REMOVER DESPUÉS =====
            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
            echo '<h3>🐛 DEBUG INFORMACIÓN</h3>';

            // Mostrar información de la cotización
            echo '<p><strong>ID Cotización:</strong> ' . $cotizacion->id . '</p>';
            echo '<p><strong>Estado:</strong> ' . $cotizacion->estado . '</p>';
            echo '<p><strong>Cliente ID:</strong> ' . $cotizacion->cliente_id . '</p>';
            echo '<p><strong>Total:</strong> $' . number_format($cotizacion->total, 2) . '</p>';

            // Contar items
            $items_count = $db->contar_items_cotizacion($cotizacion->id);
            echo '<p><strong>Cantidad de Items:</strong> ' . $items_count . '</p>';

            // Debug completo
            $debug_info = $db->debug_cotizacion($cotizacion->id);
            echo '<p><strong>Debug completo:</strong></p>';
            echo '<pre style="background: white; padding: 10px; font-size: 12px;">';
            print_r($debug_info);
            echo '</pre>';

            echo '</div>';
            // ===== FIN DEBUG TEMPORAL =====*/

            // Verificar si la cotización puede generar PDF
            $puede_generar_pdf = $db->cotizacion_puede_generar_pdf($cotizacion->id);

            ?>

            <!-- Sección PDF -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">
                        <span class="dashicons dashicons-media-document" style="margin-right: 5px;"></span>
                        <?php _e('Documento PDF', 'modulo-ventas'); ?>
                    </h2>
                </div>
                <div class="inside">
                    <?php if ($puede_generar_pdf): ?>
                        <div class="mv-cotizacion-pdf-actions">
                            <p class="description" style="margin-bottom: 15px;">
                                <?php _e('Genere el PDF de esta cotización para enviar al cliente o para sus registros.', 'modulo-ventas'); ?>
                            </p>
                            
                            <div class="mv-pdf-buttons" style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px;">
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin-ajax.php?action=mv_generar_pdf_cotizacion&cotizacion_id=' . $cotizacion->id . '&modo=preview'),
                                    'mv_pdf_cotizacion_' . $cotizacion->id
                                ); ?>" 
                                class="button button-primary mv-pdf-preview-button" 
                                target="_blank"
                                data-cotizacion-id="<?php echo $cotizacion->id; ?>">
                                    <span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span>
                                    <?php _e('Ver PDF', 'modulo-ventas'); ?>
                                </a>
                                
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin-ajax.php?action=mv_generar_pdf_cotizacion&cotizacion_id=' . $cotizacion->id . '&modo=download'),
                                    'mv_pdf_cotizacion_' . $cotizacion->id
                                ); ?>" 
                                class="button mv-pdf-download-button"
                                data-cotizacion-id="<?php echo $cotizacion->id; ?>">
                                    <span class="dashicons dashicons-download" style="margin-right: 5px;"></span>
                                    <?php _e('Descargar PDF', 'modulo-ventas'); ?>
                                </a>
                                
                                <button type="button" 
                                        class="button mv-pdf-email-button" 
                                        data-cotizacion-id="<?php echo $cotizacion->id; ?>"
                                        onclick="mv_modal_enviar_email(<?php echo $cotizacion->id; ?>)">
                                    <span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span>
                                    <?php _e('Enviar por Email', 'modulo-ventas'); ?>
                                </button>
                            </div>
                            
                            <div class="mv-pdf-info" style="background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #0073aa;">
                                <h4 style="margin: 0 0 10px 0; color: #23282d;">
                                    <span class="dashicons dashicons-info" style="margin-right: 5px;"></span>
                                    <?php _e('Información del PDF', 'modulo-ventas'); ?>
                                </h4>
                                <ul style="margin: 0; padding-left: 20px; color: #646970;">
                                    <li><?php _e('Incluye información completa de la cotización y cliente', 'modulo-ventas'); ?></li>
                                    <li><?php _e('Formato profesional listo para imprimir o enviar', 'modulo-ventas'); ?></li>
                                    <li><?php _e('Se genera en tiempo real con los datos actuales', 'modulo-ventas'); ?></li>
                                    <li><?php printf(__('Archivo: cotizacion_%s.pdf', 'modulo-ventas'), $cotizacion->folio); ?></li>
                                </ul>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Mensaje cuando no se puede generar PDF -->
                        <div class="notice notice-warning inline" style="margin: 0;">
                            <p>
                                <span class="dashicons dashicons-warning" style="margin-right: 5px;"></span>
                                <strong><?php _e('PDF no disponible', 'modulo-ventas'); ?></strong>
                            </p>
                            <p style="margin: 10px 0 0 0;">
                                <?php _e('Para generar el PDF, la cotización debe cumplir con los siguientes requisitos:', 'modulo-ventas'); ?>
                            </p>
                            <ul style="margin: 10px 0 0 20px;">
                                <li><?php _e('Tener al menos un producto agregado', 'modulo-ventas'); ?></li>
                                <li><?php _e('Tener un cliente asignado', 'modulo-ventas'); ?></li>
                                <li><?php _e('Tener un total mayor a cero', 'modulo-ventas'); ?></li>
                            </ul>
                            
                            <?php if ($cotizacion->estado === 'borrador'): ?>
                            <p style="margin: 15px 0 0 0;">
                                <a href="<?php echo admin_url('admin.php?page=modulo-ventas-editar-cotizacion&id=' . $cotizacion->id); ?>" 
                                class="button button-secondary">
                                    <span class="dashicons dashicons-edit" style="margin-right: 5px;"></span>
                                    <?php _e('Completar Cotización', 'modulo-ventas'); ?>
                                </a>
                            </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial -->
            <div class="postbox">
                <h2 class="hndle">
                    <span><?php _e('Historial', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-historial">
                        <!-- Creación -->
                        <div class="mv-historial-item">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <div class="mv-historial-content">
                                <strong><?php _e('Cotización creada', 'modulo-ventas'); ?></strong>
                                <p>
                                    <?php 
                                    if (isset($cotizacion->creado_por) && $cotizacion->creado_por) {
                                        $creador = get_user_by('id', $cotizacion->creado_por);
                                        if ($creador) {
                                            echo sprintf(__('Por %s', 'modulo-ventas'), $creador->display_name);
                                        }
                                    }
                                    ?>
                                </p>
                                <small><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cotizacion->fecha_creacion ?? $cotizacion->fecha)); ?></small>
                            </div>
                        </div>
                        
                        <!-- Estado actual -->
                        <?php if ($cotizacion->estado !== 'borrador'): ?>
                        <div class="mv-historial-item">
                            <span class="dashicons dashicons-flag"></span>
                            <div class="mv-historial-content">
                                <strong><?php _e('Estado actual', 'modulo-ventas'); ?></strong>
                                <p><?php echo esc_html($estados[$cotizacion->estado] ?? ucfirst($cotizacion->estado)); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Conversión a pedido -->
                        <?php if (isset($cotizacion->venta_id) && $cotizacion->venta_id): ?>
                        <div class="mv-historial-item">
                            <span class="dashicons dashicons-cart"></span>
                            <div class="mv-historial-content">
                                <strong><?php _e('Convertida a pedido', 'modulo-ventas'); ?></strong>
                                <p>
                                    <?php echo sprintf(
                                        __('Pedido #%s creado', 'modulo-ventas'), 
                                        '<a href="' . admin_url('post.php?post=' . $cotizacion->venta_id . '&action=edit') . '" target="_blank">' . 
                                        $cotizacion->venta_id . 
                                        '</a>'
                                    ); ?>
                                </p>
                                <?php if (isset($cotizacion->fecha_conversion) && $cotizacion->fecha_conversion): ?>
                                    <small><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cotizacion->fecha_conversion)); ?></small>
                                <?php endif; ?>
                            </div>
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

/* Estilo para Botones PDF */
.mv-pdf-actions {
    display: inline-block;
    margin-left: 10px;
}

.mv-pdf-actions .button {
    margin-right: 5px;
}

.mv-pdf-actions .dashicons {
    margin-right: 3px;
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

    // Ver PDF en nueva ventana
    $('.mv-btn-pdf-preview').on('click', function() {
        var previewUrl = $(this).data('preview-url');
        window.open(previewUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    });
    
    // Descargar PDF
    $('.mv-btn-pdf-download').on('click', function() {
        var downloadUrl = $(this).data('download-url');
        window.location.href = downloadUrl;
    });
    
    // Enviar por email (por implementar)
    $('.mv-btn-email-pdf').on('click', function() {
        var cotizacionId = $(this).data('cotizacion-id');
        alert('Función de envío por email en desarrollo');
        // TODO: Implementar modal para envío por email
    });

    // Mejorar botones PDF con indicadores de carga
    $('.mv-pdf-preview-button, .mv-pdf-download-button').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        var isPreview = $btn.hasClass('mv-pdf-preview-button');
        
        // Cambiar texto y deshabilitar
        $btn.prop('disabled', true);
        $btn.find('.dashicons').removeClass('dashicons-visibility dashicons-download').addClass('dashicons-update');
        
        if (isPreview) {
            $btn.append(' <?php _e('Generando...', 'modulo-ventas'); ?>');
        } else {
            $btn.append(' <?php _e('Preparando descarga...', 'modulo-ventas'); ?>');
        }
        
        // Restaurar después de un tiempo
        setTimeout(function() {
            $btn.prop('disabled', false);
            $btn.text(originalText);
            
            if (isPreview) {
                $btn.prepend('<span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span>');
            } else {
                $btn.prepend('<span class="dashicons dashicons-download" style="margin-right: 5px;"></span>');
            }
        }, isPreview ? 2000 : 3000);
    });
    // Fin PDF

    // Función para mostrar modal de envío por email
    function mv_modal_enviar_email(cotizacionId) {
        var email = prompt('<?php _e('Ingrese el email del destinatario:', 'modulo-ventas'); ?>');
        if (email && email.trim()) {
            if (isValidEmail(email)) {
                // TODO: Implementar envío real por AJAX
                alert('<?php _e('Función de envío por email en desarrollo', 'modulo-ventas'); ?>\n\nCotización: ' + cotizacionId + '\nEmail: ' + email);
            } else {
                alert('<?php _e('Por favor ingrese un email válido', 'modulo-ventas'); ?>');
            }
        }
    }

    // Validador de email
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Mejorar botón de email
    $('.mv-pdf-email-button').on('click', function() {
        var $btn = $(this);
        $btn.find('.dashicons').removeClass('dashicons-email-alt').addClass('dashicons-update');
        
        // Restaurar después de la acción
        setTimeout(function() {
            $btn.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-email-alt');
        }, 1000);
    });
});
</script>