<?php
/**
 * Vista del formulario de edición de cotización
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// TEST URGENTE - Agregar JUSTO después de if (!defined('ABSPATH'))
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Crear un archivo de log temporal para verificar
    $log_file = WP_CONTENT_DIR . '/editar-cotizacion-post.txt';
    $log_data = date('Y-m-d H:i:s') . " - POST RECIBIDO\n";
    $log_data .= "Nonce: " . ($_POST['mv_cotizacion_nonce'] ?? 'NO EXISTE') . "\n";
    $log_data .= "Cliente: " . ($_POST['cliente_id'] ?? 'NO EXISTE') . "\n";
    $log_data .= "Items: " . (isset($_POST['items']) ? count($_POST['items']) : 0) . "\n";
    $log_data .= "---\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    
    // También intentar un die() para ver si llega
    // die('POST RECIBIDO - PRUEBA'); // Descomentar para prueba extrema
}

// ============ DEBUG TEMPORAL - ELIMINAR DESPUÉS ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('=== EDITAR-COTIZACION.PHP - POST RECIBIDO ===');
    error_log('Nonce: ' . ($_POST['mv_cotizacion_nonce'] ?? 'NO EXISTE'));
    error_log('Action: ' . ($_POST['action'] ?? 'NO EXISTE'));
    error_log('Cliente ID: ' . ($_POST['cliente_id'] ?? 'NO EXISTE'));
    error_log('Cotización ID: ' . ($_POST['cotizacion_id'] ?? 'NO EXISTE'));
    error_log('Items: ' . (isset($_POST['items']) ? count($_POST['items']) . ' productos' : 'NO EXISTEN'));
    
    // Verificar si el procesamiento se está ejecutando
    if (isset($_POST['mv_cotizacion_nonce'])) {
        error_log('Verificando nonce...');
        $nonce_valido = wp_verify_nonce($_POST['mv_cotizacion_nonce'], 'mv_editar_cotizacion');
        error_log('Nonce válido: ' . ($nonce_valido ? 'SÍ' : 'NO'));
    }
}
// ============ FIN DEBUG ============

// Las variables ya vienen del controlador:
// $cotizacion - objeto con los datos de la cotización
// $items - array con los items de la cotización
// $lista_clientes - array con los clientes disponibles
// $almacenes - array con los almacenes disponibles
// $config - array con la configuración del módulo
// $cotizacion_id - ID de la cotización actual

// Asegurar que tenemos el ID de la cotización
$cotizacion_id = $cotizacion->id ?? 0;

?>

<div class="wrap mv-editar-cotizacion">
    <h1>
        <span class="dashicons dashicons-edit"></span>
        <?php echo sprintf(__('Editar Cotización %s', 'modulo-ventas'), esc_html($cotizacion->folio)); ?>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id); ?>" class="page-title-action">
            <?php _e('Ver Cotización', 'modulo-ventas'); ?>
        </a>
    </h1>
    
    <form method="post" 
        action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" 
        id="mv-form-cotizacion" 
        class="mv-form-cotizacion mv-cotizacion-form" 
        data-validate-decimales="true">
        <?php wp_nonce_field('mv_editar_cotizacion', 'mv_cotizacion_nonce'); ?>
        <input type="hidden" name="cotizacion_id" value="<?php echo esc_attr($cotizacion_id); ?>">
        
        <div class="mv-form-container">
            <!-- Columna principal -->
            <div class="mv-form-main">
                
                <!-- Información general -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Información General', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <div class="mv-form-grid">
                            <!-- Cliente -->
                            <div class="mv-form-group mv-form-group-full">
                                <label for="cliente_id" class="required">
                                    <?php _e('Cliente', 'modulo-ventas'); ?>
                                </label>
                                <div class="mv-input-group">
                                    <select name="cliente_id" id="cliente_id" class="mv-select2-cliente" required>
                                        <option value=""><?php _e('Seleccione un cliente', 'modulo-ventas'); ?></option>
                                        <?php foreach ($lista_clientes as $cliente) : ?>
                                            <option value="<?php echo esc_attr($cliente->id); ?>"
                                                    <?php selected($cotizacion->cliente_id, $cliente->id); ?>
                                                    data-rut="<?php echo esc_attr(mv_formatear_rut($cliente->rut)); ?>"
                                                    data-email="<?php echo esc_attr($cliente->email); ?>"
                                                    data-telefono="<?php echo esc_attr($cliente->telefono); ?>"
                                                    data-direccion="<?php echo esc_attr($cliente->direccion_facturacion); ?>"
                                                    data-giro="<?php echo esc_attr($cliente->giro_comercial); ?>">
                                                <?php echo esc_html($cliente->razon_social . ' - ' . mv_formatear_rut($cliente->rut)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button mv-btn-nuevo-cliente" title="<?php esc_attr_e('Agregar nuevo cliente', 'modulo-ventas'); ?>">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                    </button>
                                </div>
                                <div id="mv-cliente-info" class="mv-cliente-info" <?php echo $cotizacion->cliente_id ? '' : 'style="display: none;"'; ?>>
                                    <?php
                                    // Obtener datos del cliente actual
                                    $cliente_actual = null;
                                    foreach ($lista_clientes as $cliente) {
                                        if ($cliente->id == $cotizacion->cliente_id) {
                                            $cliente_actual = $cliente;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="mv-info-row">
                                        <span class="mv-info-label"><?php _e('RUT:', 'modulo-ventas'); ?></span>
                                        <span class="mv-info-value" data-field="rut"><?php echo $cliente_actual ? esc_html(mv_formatear_rut($cliente_actual->rut)) : '-'; ?></span>
                                    </div>
                                    <div class="mv-info-row">
                                        <span class="mv-info-label"><?php _e('Email:', 'modulo-ventas'); ?></span>
                                        <span class="mv-info-value" data-field="email"><?php echo $cliente_actual ? esc_html($cliente_actual->email) : '-'; ?></span>
                                    </div>
                                    <div class="mv-info-row">
                                        <span class="mv-info-label"><?php _e('Teléfono:', 'modulo-ventas'); ?></span>
                                        <span class="mv-info-value" data-field="telefono"><?php echo $cliente_actual ? esc_html($cliente_actual->telefono) : '-'; ?></span>
                                    </div>
                                    <div class="mv-info-row">
                                        <span class="mv-info-label"><?php _e('Dirección:', 'modulo-ventas'); ?></span>
                                        <span class="mv-info-value" data-field="direccion"><?php echo $cliente_actual ? esc_html($cliente_actual->direccion_facturacion) : '-'; ?></span>
                                    </div>
                                    <div class="mv-info-row">
                                        <span class="mv-info-label"><?php _e('Giro:', 'modulo-ventas'); ?></span>
                                        <span class="mv-info-value" data-field="giro"><?php echo $cliente_actual ? esc_html($cliente_actual->giro_comercial) : '-'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fecha -->
                            <div class="mv-form-group">
                                <label for="fecha">
                                    <?php _e('Fecha', 'modulo-ventas'); ?>
                                </label>
                                <input type="date" 
                                    name="fecha" 
                                    id="fecha" 
                                    value="<?php echo esc_attr(date('Y-m-d', strtotime($cotizacion->fecha))); ?>" 
                                    class="regular-text">
                            </div>
                            
                            <!-- Fecha de expiración -->
                            <div class="mv-form-group">
                                <label for="fecha_expiracion">
                                    <?php _e('Válida hasta', 'modulo-ventas'); ?>
                                </label>
                                <input type="date" 
                                    name="fecha_expiracion" 
                                    id="fecha_expiracion" 
                                    value="<?php echo esc_attr($cotizacion->fecha_expiracion); ?>"
                                    min="<?php echo esc_attr(date('Y-m-d')); ?>"
                                    class="regular-text">
                            </div>
                            
                            <!-- Plazo de pago -->
                            <div class="mv-form-group">
                                <label for="plazo_pago">
                                    <?php _e('Plazo de pago', 'modulo-ventas'); ?>
                                </label>
                                <select name="plazo_pago" id="plazo_pago" class="regular-text">
                                    <?php foreach (mv_get_plazos_pago() as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($cotizacion->plazo_pago, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Vendedor -->
                            <div class="mv-form-group">
                                <label for="vendedor_id">
                                    <?php _e('Vendedor', 'modulo-ventas'); ?>
                                </label>
                                <select name="vendedor_id" id="vendedor_id" class="regular-text">
                                    <?php foreach (mv_get_vendedores() as $vendedor) : ?>
                                        <option value="<?php echo esc_attr($vendedor->ID); ?>" 
                                                <?php selected($cotizacion->vendedor_id ?? $cotizacion->vendedor, $vendedor->ID); ?>>
                                            <?php echo esc_html($vendedor->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (function_exists('mv_almacenes_activo') && mv_almacenes_activo() && !empty($almacenes)) : ?>
                            <!-- Almacén predeterminado -->
                            <div class="mv-form-group">
                                <label for="almacen_id">
                                    <?php _e('Almacén predeterminado', 'modulo-ventas'); ?>
                                </label>
                                <select name="almacen_id" id="almacen_id" class="regular-text">
                                    <option value=""><?php _e('Sin almacén específico', 'modulo-ventas'); ?></option>
                                    <?php foreach ($almacenes as $almacen) : ?>
                                        <option value="<?php echo esc_attr($almacen->id); ?>" 
                                                <?php selected($cotizacion->almacen_id ?? '', $almacen->id); ?>>
                                            <?php echo esc_html($almacen->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <!-- IVA -->
                            <div class="mv-form-group">
                                <label>
                                    <input type="checkbox" 
                                        name="incluye_iva" 
                                        id="incluye_iva" 
                                        value="1" 
                                        <?php checked($cotizacion->incluye_iva, 1); ?>>
                                    <?php _e('Incluir IVA (19%)', 'modulo-ventas'); ?>
                                </label>
                            </div>
                            
                            <!-- Estado -->
                            <div class="mv-form-group">
                                <label for="estado">
                                    <?php _e('Estado', 'modulo-ventas'); ?>
                                </label>
                                <select name="estado" id="estado" class="regular-text">
                                    <?php 
                                    // Obtener estados disponibles
                                    $estados_disponibles = mv_get_estados_cotizacion();
                                    
                                    // Si la función devuelve un array con estructura compleja
                                    if (is_array($estados_disponibles)) {
                                        foreach ($estados_disponibles as $key => $estado_info) {
                                            // Verificar si es un array con 'label' o es un string directo
                                            $label = is_array($estado_info) ? $estado_info['label'] : $estado_info;
                                            
                                            // No permitir cambiar a estados finales
                                            if (!in_array($key, ['convertida', 'cancelada'])) : 
                                            ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected($cotizacion->estado, $key); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php 
                                            endif;
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Productos -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Productos / Servicios', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <!-- Buscador de productos -->
                        <div class="mv-product-search">
                            <label for="buscar_producto" class="screen-reader-text">
                                <?php _e('Buscar producto', 'modulo-ventas'); ?>
                            </label>
                            <select id="buscar_producto" class="mv-select2-productos" style="width: 100%;">
                                <option value=""><?php _e('Buscar productos por nombre, SKU o categoría...', 'modulo-ventas'); ?></option>
                            </select>
                        </div>
                        
                        <!-- Tabla de productos -->
                        <div class="mv-productos-tabla-wrapper">
                            <table class="wp-list-table widefat fixed striped" id="mv-tabla-productos">
                                <thead>
                                    <tr>
                                        <th class="column-producto"><?php _e('Producto/Servicio', 'modulo-ventas'); ?></th>
                                        <?php if (function_exists('mv_almacenes_activo') && mv_almacenes_activo()) : ?>
                                        <th class="column-almacen"><?php _e('Almacén', 'modulo-ventas'); ?></th>
                                        <?php endif; ?>
                                        <th class="column-cantidad"><?php _e('Cantidad', 'modulo-ventas'); ?></th>
                                        <th class="column-precio"><?php _e('Precio Unit.', 'modulo-ventas'); ?></th>
                                        <th class="column-descuento"><?php _e('Descuento', 'modulo-ventas'); ?></th>
                                        <th class="column-subtotal"><?php _e('Subtotal', 'modulo-ventas'); ?></th>
                                        <th class="column-acciones"></th>
                                    </tr>
                                </thead>
                                <tbody id="mv-productos-lista">
                                    <?php if ($items && count($items) > 0): ?>
                                        <?php foreach ($items as $index => $item): 
                                            // Obtener información del producto de WooCommerce
                                            $producto_info = null;
                                            $producto_sku = $item->sku ?? '';
                                            $producto_nombre = $item->nombre; // Usar el nombre guardado en la cotización
                                            
                                            if ($item->producto_id > 0) {
                                                // Si tiene producto_id, intentar obtener de WooCommerce
                                                $wc_product = wc_get_product($item->producto_id);
                                                if ($wc_product) {
                                                    $producto_sku = $producto_sku ?: $wc_product->get_sku();
                                                    // Si no hay nombre guardado, usar el de WooCommerce
                                                    if (empty($producto_nombre)) {
                                                        $producto_nombre = $wc_product->get_name();
                                                    }
                                                }
                                            }
                                            
                                            // Determinar el tipo de descuento y valor
                                            $tipo_descuento = $item->tipo_descuento ?? 'monto';
                                            $valor_descuento = 0;
                                            if ($tipo_descuento === 'porcentaje' && isset($item->descuento_porcentaje)) {
                                                $valor_descuento = $item->descuento_porcentaje;
                                            } elseif (isset($item->descuento_monto)) {
                                                $valor_descuento = $item->descuento_monto;
                                            }
                                        ?>
                                            <tr class="mv-producto-row" data-index="<?php echo $index; ?>">
                                                <td class="column-producto">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo esc_attr($item->id); ?>">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][producto_id]" value="<?php echo esc_attr($item->producto_id); ?>">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][variacion_id]" value="<?php echo esc_attr($item->variacion_id ?? 0); ?>">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][sku]" value="<?php echo esc_attr($producto_sku); ?>">
                                                    
                                                    <div class="mv-producto-info">
                                                        <strong class="mv-producto-nombre"><?php echo esc_html($producto_nombre); ?></strong>
                                                        <input type="text" 
                                                            name="items[<?php echo $index; ?>][nombre]" 
                                                            value="<?php echo esc_attr($producto_nombre); ?>"
                                                            class="mv-producto-nombre-input regular-text" 
                                                            style="display:none;">
                                                        <?php if ($producto_sku): ?>
                                                            <small class="mv-producto-sku">SKU: <?php echo esc_html($producto_sku); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <?php if (function_exists('mv_almacenes_activo') && mv_almacenes_activo()): ?>
                                                <td class="column-almacen">
                                                    <select name="items[<?php echo $index; ?>][almacen_id]" class="mv-select-almacen">
                                                        <option value="0" <?php selected(($item->almacen_id ?? 0), 0); ?>><?php _e('General', 'modulo-ventas'); ?></option>
                                                        <?php foreach ($almacenes as $almacen): ?>
                                                            <option value="<?php echo esc_attr($almacen->id); ?>" 
                                                                    <?php selected($item->almacen_id ?? 0, $almacen->id); ?>>
                                                                <?php echo esc_html($almacen->name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="mv-stock-info">
                                                        Stock: <span class="mv-stock-cantidad">-</span>
                                                    </small>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <td class="column-cantidad">
                                                    <input type="number" 
                                                        name="items[<?php echo $index; ?>][cantidad]" 
                                                        value="<?php echo esc_attr($item->cantidad); ?>"
                                                        min="0.01" 
                                                        step="0.01" 
                                                        class="mv-input-cantidad small-text" 
                                                        required>
                                                </td>
                                                
                                                <td class="column-precio">
                                                    <input type="number" 
                                                        name="items[<?php echo $index; ?>][precio_unitario]" 
                                                        value="<?php echo esc_attr($item->precio_unitario); ?>"
                                                        min="0" 
                                                        step="0.01" 
                                                        class="mv-input-precio small-text" 
                                                        required>
                                                </td>
                                                
                                                <td class="column-descuento">
                                                    <div class="mv-descuento-group">
                                                        <input type="number" 
                                                            name="items[<?php echo $index; ?>][descuento]" 
                                                            value="<?php echo esc_attr($valor_descuento); ?>"
                                                            min="0" 
                                                            step="0.01" 
                                                            class="mv-input-descuento small-text">
                                                        <select name="items[<?php echo $index; ?>][tipo_descuento]" class="mv-tipo-descuento">
                                                            <option value="monto" <?php selected($tipo_descuento, 'monto'); ?>>$</option>
                                                            <option value="porcentaje" <?php selected($tipo_descuento, 'porcentaje'); ?>>%</option>
                                                        </select>
                                                    </div>
                                                </td>
                                                
                                                <td class="column-subtotal">
                                                    <span class="mv-subtotal-item">
                                                        <?php echo wc_price($item->total ?? 0); ?>
                                                    </span>
                                                    <input type="hidden" 
                                                        name="items[<?php echo $index; ?>][subtotal]" 
                                                        value="<?php echo esc_attr($item->subtotal ?? 0); ?>"
                                                        class="mv-input-subtotal">
                                                    <input type="hidden" 
                                                        name="items[<?php echo $index; ?>][total]" 
                                                        value="<?php echo esc_attr($item->total ?? 0); ?>"
                                                        class="mv-input-total">
                                                </td>
                                                
                                                <td class="column-acciones">
                                                    <button type="button" class="button button-small mv-btn-eliminar-producto" title="<?php esc_attr_e('Eliminar', 'modulo-ventas'); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr class="mv-no-productos">
                                            <td colspan="<?php echo function_exists('mv_almacenes_activo') && mv_almacenes_activo() ? '7' : '6'; ?>" class="text-center">
                                                <?php _e('No hay productos agregados. Use el buscador para agregar productos.', 'modulo-ventas'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="<?php echo function_exists('mv_almacenes_activo') && mv_almacenes_activo() ? '7' : '6'; ?>">
                                            <button type="button" class="button mv-btn-agregar-linea">
                                                <span class="dashicons dashicons-plus-alt"></span>
                                                <?php _e('Agregar línea personalizada', 'modulo-ventas'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Observaciones -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Información Adicional', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <div class="mv-form-group">
                            <label for="observaciones">
                                <?php _e('Observaciones', 'modulo-ventas'); ?>
                                <span class="description"><?php _e('(Visible para el cliente)', 'modulo-ventas'); ?></span>
                            </label>
                            <textarea name="observaciones" 
                                    id="observaciones" 
                                    rows="3" 
                                    class="large-text"
                                    placeholder="<?php esc_attr_e('Observaciones que aparecerán en la cotización...', 'modulo-ventas'); ?>"><?php echo esc_textarea($cotizacion->observaciones ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mv-form-group">
                            <label for="notas_internas">
                                <?php _e('Notas internas', 'modulo-ventas'); ?>
                                <span class="description"><?php _e('(Solo uso interno)', 'modulo-ventas'); ?></span>
                            </label>
                            <textarea name="notas_internas" 
                                    id="notas_internas" 
                                    rows="3" 
                                    class="large-text"
                                    placeholder="<?php esc_attr_e('Notas privadas sobre esta cotización...', 'modulo-ventas'); ?>"><?php echo esc_textarea($cotizacion->notas_internas ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mv-form-group">
                            <label for="terminos_condiciones">
                                <?php _e('Términos y condiciones', 'modulo-ventas'); ?>
                            </label>
                            <textarea name="terminos_condiciones" 
                                    id="terminos_condiciones" 
                                    rows="4" 
                                    class="large-text"><?php echo esc_textarea($cotizacion->terminos_condiciones ?? $config['terminos_condiciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Columna lateral -->
            <div class="mv-form-sidebar">
                
                <!-- Acciones -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Acciones', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <div class="mv-actions">
                            <button type="submit" name="action" value="update" class="button button-primary button-large" id="mv-btn-guardar">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Actualizar Cotización', 'modulo-ventas'); ?>
                            </button>
                            
                            <button type="submit" name="action" value="update_and_send" class="button button-large">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php _e('Actualizar y Enviar', 'modulo-ventas'); ?>
                            </button>
                            
                            <button type="button" class="button button-large mv-btn-preview" data-cotizacion-id="<?php echo esc_attr($cotizacion_id); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Vista Previa', 'modulo-ventas'); ?>
                            </button>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion_id)); ?>" class="button button-link">
                                <?php _e('Cancelar', 'modulo-ventas'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen de totales -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Resumen', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <table class="mv-totales-tabla">
                            <tbody>
                                <!-- Subtotal -->
                                <tr class="subtotal">
                                    <th><?php _e('Subtotal', 'modulo-ventas'); ?></th>
                                    <td><span id="mv-subtotal"><?php echo wc_price($cotizacion->subtotal ?? 0); ?></span></td>
                                </tr>
                                
                                <!-- Descuento -->
                                <tr class="descuento">
                                    <th>
                                        <?php _e('Descuento', 'modulo-ventas'); ?>
                                        <div class="mv-descuento-controls">
                                            <select name="tipo_descuento_global" id="tipo_descuento_global" class="small-text">
                                                <option value="monto" <?php selected(($cotizacion->tipo_descuento_global ?? 'monto'), 'monto'); ?>>$</option>
                                                <option value="porcentaje" <?php selected(($cotizacion->tipo_descuento_global ?? 'porcentaje'), 'porcentaje'); ?>>%</option>
                                            </select>
                                            <input type="number" 
                                                name="descuento_global" 
                                                id="descuento_global" 
                                                value="<?php echo esc_attr($cotizacion->descuento_global ?? 0); ?>" 
                                                min="0" 
                                                step="0.01"
                                                class="small-text mv-precio-field">
                                        </div>
                                    </th>
                                    <td><span id="mv-descuento-total">
                                        <?php 
                                        $descuento_monto = 0;
                                        if (isset($cotizacion->descuento_global) && $cotizacion->descuento_global > 0) {
                                            if ($cotizacion->tipo_descuento_global === 'porcentaje') {
                                                $descuento_monto = ($cotizacion->subtotal ?? 0) * ($cotizacion->descuento_global / 100);
                                            } else {
                                                $descuento_monto = $cotizacion->descuento_global;
                                            }
                                        }
                                        echo wc_price($descuento_monto);
                                        ?>
                                    </span></td>
                                </tr>
                                
                                <!-- Subtotal con descuento -->
                                <tr class="subtotal-descuento">
                                    <th><?php _e('Subtotal c/desc', 'modulo-ventas'); ?></th>
                                    <td><span id="mv-subtotal-descuento">
                                        <?php 
                                        $subtotal_con_descuento = ($cotizacion->subtotal ?? 0) - $descuento_monto;
                                        echo wc_price($subtotal_con_descuento);
                                        ?>
                                    </span></td>
                                </tr>
                                
                                <!-- Envío -->
                                <tr class="envio">
                                    <th>
                                        <?php _e('Envío', 'modulo-ventas'); ?>
                                        <input type="number" 
                                            name="costo_envio" 
                                            id="costo_envio" 
                                            value="<?php echo esc_attr($cotizacion->costo_envio ?? $cotizacion->envio_monto ?? $cotizacion->envio ?? 0); ?>" 
                                            min="0" 
                                            step="1"
                                            class="small-text mv-precio-field">
                                    </th>
                                    <td><span id="mv-envio"><?php echo wc_price($cotizacion->costo_envio ?? $cotizacion->envio_monto ?? $cotizacion->envio ?? 0); ?></span></td>
                                </tr>
                                
                                <!-- IVA -->
                                <tr class="iva" id="mv-row-iva" <?php echo $cotizacion->incluye_iva ? '' : 'style="display:none;"'; ?>>
                                    <th><?php _e('IVA (19%)', 'modulo-ventas'); ?></th>
                                    <td><span id="mv-iva">
                                        <?php 
                                        if ($cotizacion->incluye_iva) {
                                            $base_iva = $subtotal_con_descuento + ($cotizacion->envio_monto ?? 0);
                                            echo wc_price($base_iva * 0.19);
                                        } else {
                                            echo wc_price(0);
                                        }
                                        ?>
                                    </span></td>
                                </tr>
                                
                                <!-- Total -->
                                <tr class="total">
                                    <th><?php _e('TOTAL', 'modulo-ventas'); ?></th>
                                    <td><strong id="mv-total"><?php echo wc_price($cotizacion->total ?? 0); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <input type="hidden" name="subtotal" id="input-subtotal" value="<?php echo esc_attr($cotizacion->subtotal ?? 0); ?>">
                        <input type="hidden" name="total" id="input-total" value="<?php echo esc_attr($cotizacion->total ?? 0); ?>">
                    </div>
                </div>
                
                <!-- Información de la cotización -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Información', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <div class="mv-info-list">
                            <div class="mv-info-row">
                                <label><?php _e('Folio:', 'modulo-ventas'); ?></label>
                                <span><strong><?php echo esc_html($cotizacion->folio); ?></strong></span>
                            </div>
                            <div class="mv-info-row">
                                <label><?php _e('Creada:', 'modulo-ventas'); ?></label>
                                <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cotizacion->fecha)); ?></span>
                            </div>
                            <?php if (!empty($cotizacion->updated_at) && $cotizacion->updated_at !== $cotizacion->fecha): ?>
                            <div class="mv-info-row">
                                <label><?php _e('Modificada:', 'modulo-ventas'); ?></label>
                                <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cotizacion->updated_at)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Herramientas -->
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Herramientas', 'modulo-ventas'); ?></span>
                    </h2>
                    <div class="inside">
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=modulo-ventas-cotizaciones&action=duplicate&id=' . $cotizacion_id), 'duplicate_cotizacion_' . $cotizacion_id); ?>" 
                            class="button">
                                <?php _e('Duplicar esta cotización', 'modulo-ventas'); ?>
                            </a>
                        </p>
                        <?php if ($cotizacion->estado === 'aprobada' || $cotizacion->estado === 'aceptada'): ?>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=modulo-ventas-cotizaciones&action=convert&id=' . $cotizacion_id), 'convert_cotizacion_' . $cotizacion_id); ?>" 
                            class="button button-primary">
                                <?php _e('Convertir a Venta', 'modulo-ventas'); ?>
                            </a>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </form>
</div>

<!-- Modal para nuevo cliente -->
<div id="mv-modal-nuevo-cliente" class="mv-modal" style="display: none;">
    <div class="mv-modal-content">
        <div class="mv-modal-header">
            <h2><?php _e('Nuevo Cliente', 'modulo-ventas'); ?></h2>
            <span class="mv-modal-close">&times;</span>
        </div>
        <div class="mv-modal-body">
            <form id="mv-form-nuevo-cliente">
                <?php wp_nonce_field('mv_crear_cliente_rapido', 'cliente_nonce'); ?>
                <div class="mv-form-grid">
                    <div class="mv-form-group">
                        <label for="nuevo_cliente_razon_social" class="required">
                            <?php _e('Razón Social', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            id="nuevo_cliente_razon_social" 
                            name="cliente[razon_social]" 
                            required
                            class="regular-text">
                    </div>
                    
                    <div class="mv-form-group">
                        <label for="nuevo_cliente_rut" class="required">
                            <?php _e('RUT', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            id="nuevo_cliente_rut" 
                            name="cliente[rut]" 
                            required
                            placeholder="12.345.678-9"
                            class="regular-text">
                    </div>
                    
                    <div class="mv-form-group">
                        <label for="nuevo_cliente_giro">
                            <?php _e('Giro Comercial', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            id="nuevo_cliente_giro" 
                            name="cliente[giro_comercial]"
                            class="regular-text">
                    </div>
                    
                    <div class="mv-form-group">
                        <label for="nuevo_cliente_telefono">
                            <?php _e('Teléfono', 'modulo-ventas'); ?>
                        </label>
                        <input type="tel" 
                            id="nuevo_cliente_telefono" 
                            name="cliente[telefono]"
                            class="regular-text">
                    </div>
                    
                    <div class="mv-form-group">
                        <label for="nuevo_cliente_email">
                            <?php _e('Email', 'modulo-ventas'); ?>
                        </label>
                        <input type="email" 
                            id="nuevo_cliente_email" 
                            name="cliente[email]"
                            class="regular-text">
                    </div>
                    
                    <div class="mv-form-group">
                        <label for="nuevo_cliente_direccion">
                            <?php _e('Dirección', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            id="nuevo_cliente_direccion" 
                            name="cliente[direccion_facturacion]"
                            class="regular-text">
                    </div>
                </div>
                
                <div class="mv-modal-footer">
                    <button type="submit" class="button button-primary">
                        <?php _e('Crear Cliente', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button mv-modal-cancel">
                        <?php _e('Cancelar', 'modulo-ventas'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template para nuevos productos (JavaScript) -->
<script type="text/template" id="mv-template-producto">
    <tr class="mv-producto-row" data-index="{{index}}">
        <td class="column-producto">
            <input type="hidden" name="items[{{index}}][producto_id]" value="{{producto_id}}">
            <input type="hidden" name="items[{{index}}][variacion_id]" value="{{variacion_id}}">
            <input type="hidden" name="items[{{index}}][sku]" value="{{sku}}">
            
            <div class="mv-producto-info">
                <strong class="mv-producto-nombre">{{nombre}}</strong>
                <input type="text" 
                    name="items[{{index}}][nombre]" 
                    value="{{nombre}}"
                    class="mv-producto-nombre-input regular-text" 
                    style="display:none;">
                {{#if sku}}
                <small class="mv-producto-sku">SKU: {{sku}}</small>
                {{/if}}
            </div>
        </td>
        
        <?php if (function_exists('mv_almacenes_activo') && mv_almacenes_activo()): ?>
        <td class="column-almacen">
            <select name="items[{{index}}][almacen_id]" class="mv-select-almacen">
                <option value=""><?php _e('Sin almacén', 'modulo-ventas'); ?></option>
                {{opciones_almacen}}
            </select>
            <small class="mv-stock-info">
                Stock: <span class="mv-stock-cantidad">-</span>
            </small>
        </td>
        <?php endif; ?>
        
        <td class="column-cantidad">
            <input type="number" 
                name="items[{{index}}][cantidad]" 
                value="1"
                min="0.01" 
                step="0.01" 
                class="mv-input-cantidad small-text" 
                required>
        </td>
        
        <td class="column-precio">
            <input type="number" 
                name="items[{{index}}][precio_unitario]" 
                value="{{precio}}"
                min="0" 
                step="0.01" 
                class="mv-input-precio small-text" 
                required>
        </td>
        
        <td class="column-descuento">
            <div class="mv-descuento-group">
                <input type="number" 
                    name="items[{{index}}][descuento]" 
                    value="0"
                    min="0" 
                    step="0.01" 
                    class="mv-input-descuento small-text">
                <select name="items[{{index}}][tipo_descuento]" class="mv-tipo-descuento">
                    <option value="monto">$</option>
                    <option value="porcentaje">%</option>
                </select>
            </div>
        </td>
        
        <td class="column-subtotal">
            <span class="mv-subtotal-item">{{precio_formateado}}</span>
            <input type="hidden" name="items[{{index}}][subtotal]" value="{{precio}}" class="mv-input-subtotal">
            <input type="hidden" name="items[{{index}}][total]" value="{{precio}}" class="mv-input-total">
        </td>
        
        <td class="column-acciones">
            <button type="button" class="button button-small mv-btn-eliminar-producto" title="<?php esc_attr_e('Eliminar', 'modulo-ventas'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </td>
    </tr>
</script>

<!-- Estilos específicos para editar cotización -->
<style type="text/css">
/* Layout principal */
.mv-form-container {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    margin-top: 20px;
}

.mv-form-main .postbox {
    margin-bottom: 20px;
}

/* Formulario */
.mv-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.mv-form-group {
    margin-bottom: 0;
}

.mv-form-group-full {
    grid-column: 1 / -1;
}

.mv-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.mv-form-group label.required:after {
    content: ' *';
    color: #d63638;
}

.mv-input-group {
    display: flex;
    gap: 10px;
}

.mv-input-group select {
    flex: 1;
}

/* Info del cliente */
.mv-cliente-info {
    margin-top: 10px;
    padding: 10px;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 4px;
}

.mv-info-row {
    display: flex;
    margin-bottom: 5px;
}

.mv-info-label {
    font-weight: 600;
    margin-right: 10px;
    min-width: 80px;
}

/* Tabla de productos */
.mv-productos-tabla-wrapper {
    margin-top: 20px;
    overflow-x: auto;
}

#mv-tabla-productos {
    min-width: 700px;
}

.column-producto { width: 35%; }
.column-almacen { width: 15%; }
.column-cantidad { width: 10%; }
.column-precio { width: 15%; }
.column-descuento { width: 12%; }
.column-subtotal { width: 12%; text-align: right; }
.column-acciones { width: 50px; text-align: center; }

.mv-producto-info {
    margin-bottom: 5px;
}

.mv-producto-nombre {
    display: block;
}

.mv-producto-sku {
    color: #646970;
}

.mv-descuento-group {
    display: flex;
    gap: 5px;
}

.mv-stock-info {
    display: block;
    color: #646970;
    margin-top: 5px;
}

/* Sidebar */
.mv-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mv-actions .button {
    width: 100%;
    justify-content: center;
}

/* Totales */
.mv-totales-tabla {
    width: 100%;
    border-collapse: collapse;
}

.mv-totales-tabla th,
.mv-totales-tabla td {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.mv-totales-tabla th {
    text-align: left;
    font-weight: normal;
    color: #646970;
}

.mv-totales-tabla td {
    text-align: right;
    font-weight: 600;
}

.mv-totales-tabla tr.total th,
.mv-totales-tabla tr.total td {
    border-top: 2px solid #dcdcde;
    border-bottom: none;
    padding-top: 12px;
    font-size: 16px;
}

.mv-descuento-controls {
    display: inline-flex;
    gap: 5px;
    margin-left: 10px;
}

.mv-descuento-controls input,
.mv-descuento-controls select {
    width: 60px;
}

/* Modal */
.mv-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.mv-modal-content {
    background-color: #fff;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.mv-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #dcdcde;
}

.mv-modal-header h2 {
    margin: 0;
}

.mv-modal-close {
    font-size: 28px;
    cursor: pointer;
    color: #666;
}

.mv-modal-close:hover {
    color: #000;
}

.mv-modal-body {
    padding: 20px;
}

.mv-modal-footer {
    padding: 20px;
    border-top: 1px solid #dcdcde;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Select2 */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--single {
    height: 32px;
    line-height: 30px;
    border: 1px solid #8c8f94;
}

/* Responsive */
@media screen and (max-width: 1200px) {
    .mv-form-container {
        grid-template-columns: 1fr;
    }
    
    .mv-form-sidebar {
        order: -1;
    }
}

@media screen and (max-width: 782px) {
    .mv-form-grid {
        grid-template-columns: 1fr;
    }
    
    .mv-descuento-controls {
        display: block;
        margin-left: 0;
        margin-top: 5px;
    }
}

.text-center {
    text-align: center;
}

/* Loading spinner */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.dashicons-update.spin {
    animation: spin 1s linear infinite;
}
</style>

<!-- JavaScript para editar cotización -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    'use strict';
    
    // Variables globales
    var productoIndex = <?php echo count($items); ?>;
    var almacenesDisponibles = <?php echo json_encode(function_exists('mv_almacenes_activo') && mv_almacenes_activo() ? $almacenes : array()); ?>;
    
    // Definir ajaxurl si no existe
    if (typeof ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }
    
    // Definir moduloVentasAjax si no existe
    if (typeof moduloVentasAjax === 'undefined') {
        window.moduloVentasAjax = {
            ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>',
            currency_symbol: '<?php echo get_woocommerce_currency_symbol(); ?>'
        };
    }
    
    // Inicializar Select2 para clientes
    if ($.fn.select2) {
        $('#cliente_id').select2({
            placeholder: '<?php _e('Seleccione un cliente', 'modulo-ventas'); ?>',
            allowClear: false,
            width: '100%'
        });
        
        // Inicializar Select2 para búsqueda de productos
        $('#buscar_producto').select2({
            placeholder: '<?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?>',
            minimumInputLength: 2,
            ajax: {
                url: window.ajaxurl,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'mv_buscar_productos',
                        busqueda: params.term,
                        almacen_id: $('#almacen_id').val() || 0,
                        nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
                    };
                },
                processResults: function(data) {
                    console.log('Respuesta del servidor:', data); // Debug
                    
                    if (data.success) {
                        if (!data.data.productos || data.data.productos.length === 0) {
                            return {
                                results: [{
                                    id: 0,
                                    text: data.data.mensaje || '<?php _e('No se encontraron productos', 'modulo-ventas'); ?>',
                                    disabled: true
                                }]
                            };
                        }
                        
                        return {
                            results: data.data.productos
                        };
                    }
                    
                    return { 
                        results: [{
                            id: 0,
                            text: '<?php _e('Error al buscar productos', 'modulo-ventas'); ?>',
                            disabled: true
                        }]
                    };
                },
                cache: false
            },
            templateResult: formatProducto,
            templateSelection: function(producto) {
                if (producto.id && producto.id !== '0' && producto.id !== 0) {
                    // Agregar producto si no es el placeholder
                    if (producto.precio !== undefined) {
                        agregarProducto(producto);
                    }
                }
                // Siempre devolver el placeholder
                return '<?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?>';
            },
            language: {
                searching: function() {
                    return '<?php _e('Buscando...', 'modulo-ventas'); ?>';
                },
                noResults: function() {
                    return '<?php _e('No se encontraron resultados', 'modulo-ventas'); ?>';
                },
                errorLoading: function() {
                    return '<?php _e('Error al cargar los resultados', 'modulo-ventas'); ?>';
                },
                inputTooShort: function(args) {
                    var remainingChars = args.minimum - args.input.length;
                    return '<?php _e('Por favor ingrese', 'modulo-ventas'); ?> ' + remainingChars + ' <?php _e('o más caracteres', 'modulo-ventas'); ?>';
                }
            }
        });
    }
    
    // Formato para mostrar productos en Select2
    function formatProducto(producto) {
        if (!producto.id) return producto.text;
        
        // Si es un mensaje (no un producto real)
        if (producto.id === 0 || producto.disabled) {
            return $('<div class="select2-no-results">' + producto.text + '</div>');
        }
        
        var stockClass = producto.en_stock ? 'in-stock' : 'out-of-stock';
        var stockText = '';
        
        if (producto.gestion_stock) {
            stockText = producto.stock > 0 ? 
                '<?php _e('Stock:', 'modulo-ventas'); ?> ' + producto.stock : 
                '<?php _e('Agotado', 'modulo-ventas'); ?>';
        } else {
            stockText = producto.en_stock ? 
                '<?php _e('Disponible', 'modulo-ventas'); ?>' : 
                '<?php _e('Agotado', 'modulo-ventas'); ?>';
        }
        
        var $producto = $(
            '<div class="mv-producto-result ' + (producto.en_stock ? '' : 'producto-agotado') + '">' +
                '<div class="mv-producto-nombre">' + producto.nombre + '</div>' +
                '<div class="mv-producto-meta">' +
                    'SKU: ' + (producto.sku || 'N/A') + ' | ' +
                    '<span class="stock-status ' + stockClass + '">' + stockText + '</span> | ' +
                    '$' + Number(producto.precio || 0).toLocaleString('es-CL') +
                '</div>' +
            '</div>'
        );
        
        return $producto;
    }
    
    // Función para agregar producto a la tabla
    function agregarProducto(producto) {
        console.log('Agregando producto:', producto); // Debug
        
        // Eliminar mensaje de "no hay productos"
        $('.mv-no-productos').remove();
        
        // Preparar opciones de almacén - INCLUIR OPCIÓN GENERAL
        var opcionesAlmacen = '<option value="0"><?php _e('General', 'modulo-ventas'); ?></option>';
        if (almacenesDisponibles && almacenesDisponibles.length > 0) {
            almacenesDisponibles.forEach(function(almacen) {
                opcionesAlmacen += '<option value="' + almacen.id + '">' + almacen.name + '</option>';
            });
        }
        
        // Crear nueva fila
        var nuevaFila = '<tr class="mv-producto-row" data-index="' + productoIndex + '">';
        
        // Columna producto
        nuevaFila += '<td class="column-producto">';
        nuevaFila += '<input type="hidden" name="items[' + productoIndex + '][producto_id]" value="' + (producto.id || 0) + '">';
        nuevaFila += '<input type="hidden" name="items[' + productoIndex + '][variacion_id]" value="' + (producto.variacion_id || 0) + '">';
        nuevaFila += '<input type="hidden" name="items[' + productoIndex + '][sku]" value="' + (producto.sku || '') + '">';
        nuevaFila += '<div class="mv-producto-info">';
        nuevaFila += '<strong class="mv-producto-nombre">' + (producto.nombre || '') + '</strong>';
        nuevaFila += '<input type="text" name="items[' + productoIndex + '][nombre]" value="' + (producto.nombre || '') + '" class="mv-producto-nombre-input regular-text" style="display:none;">';
        if (producto.sku) {
            nuevaFila += '<small class="mv-producto-sku">SKU: ' + producto.sku + '</small>';
        }
        nuevaFila += '</div>';
        nuevaFila += '</td>';
        
        // Columna almacén (si está activo)
        if (almacenesDisponibles && almacenesDisponibles.length > 0) {
            nuevaFila += '<td class="column-almacen">';
            nuevaFila += '<select name="items[' + productoIndex + '][almacen_id]" class="mv-select-almacen">';
            nuevaFila += opcionesAlmacen;
            nuevaFila += '</select>';
            nuevaFila += '<small class="mv-stock-info">Stock: <span class="mv-stock-cantidad">' + (producto.stock || '-') + '</span></small>';
            nuevaFila += '</td>';
        }
        
        // Columna cantidad
        nuevaFila += '<td class="column-cantidad">';
        nuevaFila += '<input type="number" name="items[' + productoIndex + '][cantidad]" value="1" min="0.01" step="0.01" class="mv-input-cantidad small-text" required>';
        nuevaFila += '</td>';
        
        // Columna precio
        nuevaFila += '<td class="column-precio">';
        nuevaFila += '<input type="number" name="items[' + productoIndex + '][precio_unitario]" value="' + (producto.precio || 0) + '" min="0" step="0.01" class="mv-input-precio small-text" required>';
        nuevaFila += '</td>';
        
        // Columna descuento
        nuevaFila += '<td class="column-descuento">';
        nuevaFila += '<div class="mv-descuento-group">';
        nuevaFila += '<input type="number" name="items[' + productoIndex + '][descuento]" value="0" min="0" step="0.01" class="mv-input-descuento small-text">';
        nuevaFila += '<select name="items[' + productoIndex + '][tipo_descuento]" class="mv-tipo-descuento">';
        nuevaFila += '<option value="monto">$</option>';
        nuevaFila += '<option value="porcentaje">%</option>';
        nuevaFila += '</select>';
        nuevaFila += '</div>';
        nuevaFila += '</td>';
        
        // Columna subtotal
        nuevaFila += '<td class="column-subtotal">';
        nuevaFila += '<span class="mv-subtotal-item">' + formatearPrecio(producto.precio || 0) + '</span>';
        nuevaFila += '<input type="hidden" name="items[' + productoIndex + '][subtotal]" value="' + (producto.precio || 0) + '" class="mv-input-subtotal">';
        nuevaFila += '<input type="hidden" name="items[' + productoIndex + '][total]" value="' + (producto.precio || 0) + '" class="mv-input-total">';
        nuevaFila += '</td>';
        
        // Columna acciones
        nuevaFila += '<td class="column-acciones">';
        nuevaFila += '<button type="button" class="button button-small mv-btn-eliminar-producto" title="<?php esc_attr_e('Eliminar', 'modulo-ventas'); ?>">';
        nuevaFila += '<span class="dashicons dashicons-trash"></span>';
        nuevaFila += '</button>';
        nuevaFila += '</td>';
        
        nuevaFila += '</tr>';
        
        // Agregar a la tabla
        $('#mv-productos-lista').append(nuevaFila);
        
        // Incrementar índice
        productoIndex++;
        
        // Calcular totales
        calcularTotales();
        
        // Limpiar select de productos
        $('#buscar_producto').val(null).trigger('change');
    }
    
    // Agregar línea personalizada
    $('.mv-btn-agregar-linea').on('click', function() {
        var producto = {
            id: 0,
            variacion_id: 0,
            nombre: '',
            sku: 'CUSTOM',
            precio: 0
        };
        
        agregarProducto(producto);
        
        // Hacer editable el nombre
        var $row = $('#mv-productos-lista tr:last');
        $row.find('.mv-producto-nombre').hide();
        $row.find('.mv-producto-nombre-input').show().focus();
    });
    
    // Eliminar producto
    $(document).on('click', '.mv-btn-eliminar-producto', function() {
        if (confirm('<?php _e('¿Está seguro de eliminar este producto?', 'modulo-ventas'); ?>')) {
            $(this).closest('tr').remove();
            
            // Si no quedan productos, mostrar mensaje
            if ($('#mv-productos-lista tr').length === 0) {
                var colspan = almacenesDisponibles && almacenesDisponibles.length > 0 ? 7 : 6;
                $('#mv-productos-lista').html(
                    '<tr class="mv-no-productos">' +
                        '<td colspan="' + colspan + '" class="text-center">' +
                            '<?php _e('No hay productos agregados. Use el buscador para agregar productos.', 'modulo-ventas'); ?>' +
                        '</td>' +
                    '</tr>'
                );
            }
            
            // Reindexar productos
            reindexarProductos();
            calcularTotales();
        }
    });
    
    // Función para reindexar productos después de eliminar
    function reindexarProductos() {
        $('#mv-productos-lista .mv-producto-row').each(function(index) {
            $(this).attr('data-index', index);
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/items\[\d+\]/, 'items[' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
        productoIndex = $('#mv-productos-lista .mv-producto-row').length;
    }
    
    // Mostrar información del cliente seleccionado
    $('#cliente_id').on('change', function() {
        var $selected = $(this).find(':selected');
        
        if ($selected.val()) {
            $('#mv-cliente-info').show();
            $('#mv-cliente-info [data-field="rut"]').text($selected.data('rut') || '-');
            $('#mv-cliente-info [data-field="email"]').text($selected.data('email') || '-');
            $('#mv-cliente-info [data-field="telefono"]').text($selected.data('telefono') || '-');
            $('#mv-cliente-info [data-field="direccion"]').text($selected.data('direccion') || '-');
            $('#mv-cliente-info [data-field="giro"]').text($selected.data('giro') || '-');
        } else {
            $('#mv-cliente-info').hide();
        }
    });
    
    // Modal nuevo cliente
    $('.mv-btn-nuevo-cliente').on('click', function() {
        $('#mv-modal-nuevo-cliente').fadeIn();
    });
    
    $('.mv-modal-close, .mv-modal-cancel').on('click', function() {
        $('#mv-modal-nuevo-cliente').fadeOut();
    });
    
    // Formulario nuevo cliente
    $('#mv-form-nuevo-cliente').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        
        $submit.prop('disabled', true).text('<?php _e('Creando...', 'modulo-ventas'); ?>');
        
        // Recopilar datos
        var datosCliente = {};
        $form.find('input').each(function() {
            var name = $(this).attr('name');
            if (name && name.includes('cliente[')) {
                var key = name.match(/cliente\[(.+)\]/)[1];
                datosCliente[key] = $(this).val();
            }
        });
        
        // Limpiar RUT antes de enviar
        if (datosCliente.rut) {
            datosCliente.rut = datosCliente.rut.replace(/\./g, '').replace(/-/g, '');
        }
        
        $.ajax({
            url: window.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mv_crear_cliente_rapido',
                cliente: datosCliente,
                nonce: $form.find('[name="cliente_nonce"]').val() || moduloVentasAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var nuevoCliente = response.data.cliente;
                    
                    // Agregar al select
                    var option = new Option(
                        nuevoCliente.razon_social + ' - ' + nuevoCliente.rut,
                        nuevoCliente.id,
                        true,
                        true
                    );
                    
                    $(option).attr({
                        'data-rut': nuevoCliente.rut,
                        'data-email': nuevoCliente.email,
                        'data-telefono': nuevoCliente.telefono,
                        'data-direccion': nuevoCliente.direccion_facturacion,
                        'data-giro': nuevoCliente.giro_comercial
                    });
                    
                    $('#cliente_id').append(option).trigger('change');
                    
                    // Cerrar modal y limpiar formulario
                    $('#mv-modal-nuevo-cliente').fadeOut();
                    $form[0].reset();
                    
                    // Mostrar mensaje de éxito
                    mostrarNotificacion(response.data.message || '<?php _e('Cliente creado exitosamente', 'modulo-ventas'); ?>', 'success');
                } else {
                    alert(response.data.message || '<?php _e('Error al crear el cliente', 'modulo-ventas'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                alert('<?php _e('Error de conexión. Por favor, intente nuevamente.', 'modulo-ventas'); ?>');
            },
            complete: function() {
                $submit.prop('disabled', false).text('<?php _e('Crear Cliente', 'modulo-ventas'); ?>');
            }
        });
    });
    
    // Calcular totales cuando cambian valores
    $(document).on('change keyup', '.mv-input-cantidad, .mv-input-precio, .mv-input-descuento, #descuento_global, #costo_envio', function() {
        calcularTotales();
    });
    
    $(document).on('change', '.mv-tipo-descuento, #tipo_descuento_global', function() {
        calcularTotales();
    });
    
    // Checkbox IVA
    $('#incluye_iva').on('change', function() {
        if ($(this).is(':checked')) {
            $('#mv-row-iva').show();
        } else {
            $('#mv-row-iva').hide();
        }
        calcularTotales();
    });
    
    // Función para calcular totales
    function calcularTotales() {
        var subtotal = 0;
        var incluyeIva = $('#incluye_iva').is(':checked');
        
        // Calcular subtotal de productos
        $('#mv-productos-lista .mv-producto-row').each(function() {
            var $row = $(this);
            var cantidad = parseFloat($row.find('.mv-input-cantidad').val()) || 0;
            var precio = parseFloat($row.find('.mv-input-precio').val()) || 0;
            var descuento = parseFloat($row.find('.mv-input-descuento').val()) || 0;
            var tipoDescuento = $row.find('.mv-tipo-descuento').val();
            
            var subtotalItem = cantidad * precio;
            
            // Aplicar descuento
            if (tipoDescuento === 'porcentaje') {
                subtotalItem = subtotalItem - (subtotalItem * (descuento / 100));
            } else {
                subtotalItem = subtotalItem - descuento;
            }
            
            subtotalItem = Math.max(0, subtotalItem);
            
            // Actualizar subtotal del item
            $row.find('.mv-subtotal-item').text(formatearPrecio(subtotalItem));
            $row.find('.mv-input-subtotal').val(cantidad * precio);
            $row.find('.mv-input-total').val(subtotalItem.toFixed(2));
            
            subtotal += subtotalItem;
        });
        
        // Descuento global
        var descuentoGlobal = parseFloat($('#descuento_global').val()) || 0;
        var tipoDescuentoGlobal = $('#tipo_descuento_global').val();
        var descuentoGlobalMonto = 0;
        
        if (tipoDescuentoGlobal === 'porcentaje') {
            descuentoGlobalMonto = subtotal * (descuentoGlobal / 100);
        } else {
            descuentoGlobalMonto = descuentoGlobal;
        }
        
        var subtotalConDescuento = subtotal - descuentoGlobalMonto;
        subtotalConDescuento = Math.max(0, subtotalConDescuento);
        
        // Envío
        var envio = parseFloat($('#costo_envio').val()) || 0;
        
        // Base para IVA (subtotal con descuento + envío)
        var baseIva = subtotalConDescuento + envio;
        
        // IVA
        var iva = incluyeIva ? baseIva * 0.19 : 0;
        
        // Total
        var total = baseIva + iva;
        
        // Actualizar UI
        $('#mv-subtotal').text(formatearPrecio(subtotal));
        $('#mv-descuento-total').text(formatearPrecio(descuentoGlobalMonto));
        $('#mv-subtotal-descuento').text(formatearPrecio(subtotalConDescuento));
        $('#mv-envio').text(formatearPrecio(envio));
        $('#mv-iva').text(formatearPrecio(iva));
        $('#mv-total').text(formatearPrecio(total));
        
        // Actualizar inputs hidden
        $('#input-subtotal').val(subtotal.toFixed(2));
        $('#input-total').val(total.toFixed(2));
    }
    
    // Formatear precio
    function formatearPrecio(valor) {
        var symbol = moduloVentasAjax.currency_symbol || '$';
        return symbol + ' ' + new Intl.NumberFormat('es-CL', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(Math.round(valor));
    }
    
    // Cambio de almacén - actualizar stock
    $(document).on('change', '.mv-select-almacen', function() {
        var $row = $(this).closest('tr');
        var productoId = $row.find('input[name*="[producto_id]"]').val();
        var variacionId = $row.find('input[name*="[variacion_id]"]').val();
        var almacenId = $(this).val();
        var $stockInfo = $row.find('.mv-stock-cantidad');
        
        if (productoId && productoId > 0) {
            $stockInfo.text('...');
            
            $.post(window.ajaxurl, {
                action: 'mv_obtener_stock_producto',
                producto_id: productoId,
                variacion_id: variacionId,
                almacen_id: almacenId,
                nonce: moduloVentasAjax.nonce
            }, function(response) {
                if (response.success) {
                    var stock = 0;
                    
                    if (almacenId && response.data.stock_por_almacen) {
                        var almacenData = response.data.stock_por_almacen.find(function(a) {
                            return a.almacen_id == almacenId;
                        });
                        stock = almacenData ? almacenData.stock : 0;
                    } else {
                        stock = response.data.stock_general || 0;
                    }
                    
                    $stockInfo.text(stock);
                } else {
                    $stockInfo.text('-');
                }
            }).fail(function() {
                $stockInfo.text('-');
            });
        }
    });
    
    // Vista previa
    $('.mv-btn-preview').on('click', function() {
        var cotizacionId = $(this).data('cotizacion-id');
        if (cotizacionId) {
            window.open('<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id='); ?>' + cotizacionId, '_blank');
        } else {
            alert('<?php _e('Debe guardar la cotización primero', 'modulo-ventas'); ?>');
        }
    });
    
    // Validación del formulario
    $('#mv-form-cotizacion').on('submit', function(e) {
        // Validar cliente
        if (!$('#cliente_id').val()) {
            alert('<?php _e('Debe seleccionar un cliente', 'modulo-ventas'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Validar productos
        if ($('#mv-productos-lista .mv-producto-row').length === 0) {
            alert('<?php _e('Debe agregar al menos un producto', 'modulo-ventas'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Recalcular totales antes de enviar
        calcularTotales();
        
        // Deshabilitar botón de envío para evitar doble envío
        $('#mv-btn-guardar').prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> <?php _e('Actualizando...', 'modulo-ventas'); ?>'
        );
        
        // Debug
        console.log('Enviando formulario de edición...');
        console.log('Cliente ID:', $('#cliente_id').val());
        console.log('Número de productos:', $('#mv-productos-lista .mv-producto-row').length);
        
        return true;
    });
    
    // Función para mostrar notificaciones
    function mostrarNotificacion(mensaje, tipo) {
        tipo = tipo || 'info';
        var claseIcono = tipo === 'success' ? 'dashicons-yes' : 'dashicons-info';
        
        var $notificacion = $('<div class="notice notice-' + tipo + ' is-dismissible" style="margin-top: 20px;">' +
                            '<p><span class="dashicons ' + claseIcono + '"></span> ' + mensaje + '</p>' +
                            '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Descartar</span></button>' +
                            '</div>');
        
        $('.wrap > h1').after($notificacion);
        
        // Auto-ocultar después de 5 segundos
        setTimeout(function() {
            $notificacion.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Botón de cerrar
        $notificacion.find('.notice-dismiss').on('click', function() {
            $notificacion.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    // Hacer campos editables al hacer doble clic
    $(document).on('dblclick', '.mv-producto-nombre', function() {
        var $this = $(this);
        var $input = $this.siblings('.mv-producto-nombre-input');
        $this.hide();
        $input.show().focus();
    });
    
    $(document).on('blur', '.mv-producto-nombre-input', function() {
        var $this = $(this);
        var $nombre = $this.siblings('.mv-producto-nombre');
        var valor = $this.val();
        
        if (valor.trim() !== '') {
            $nombre.text(valor).show();
            $this.hide();
        }
    });
    
    // Tecla Enter en input de nombre
    $(document).on('keypress', '.mv-producto-nombre-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $(this).blur();
        }
    });
    
    // Inicializar al cargar
    // Calcular totales iniciales
    calcularTotales();
    
    // Si hay stock por almacén, actualizarlo
    $('.mv-select-almacen').each(function() {
        if ($(this).val()) {
            $(this).trigger('change');
        }
    });
    
    // Verificar si hay mensaje de éxito en la URL
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('updated') === '1') {
        mostrarNotificacion('<?php _e('Cotización actualizada exitosamente', 'modulo-ventas'); ?>', 'success');
    }
    
    // Prevenir envío accidental con Enter
    $(document).on('keypress', 'input[type="text"], input[type="number"]', function(e) {
        if (e.which === 13 && !$(this).hasClass('mv-producto-nombre-input')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Usar title attributes nativos en lugar de tooltip jQuery UI
    $('.mv-btn-eliminar-producto').attr('title', '<?php _e('Eliminar este producto', 'modulo-ventas'); ?>');
    $('.mv-btn-agregar-linea').attr('title', '<?php _e('Agregar un producto o servicio personalizado', 'modulo-ventas'); ?>');
    
    // Debug: Log para verificar que el script se carga
    console.log('Script de editar cotización cargado correctamente');
    console.log('Productos existentes:', productoIndex);
    console.log('Almacenes disponibles:', almacenesDisponibles ? almacenesDisponibles.length : 0);

    // CORRECCIÓN: Asegurar que el formulario se envíe correctamente
    // Eliminar cualquier handler que pueda estar bloqueando
    $('#mv-form-cotizacion').off('submit.gab'); // Remover handlers del plugin de almacenes
    
    // Re-agregar nuestro handler con mayor prioridad
    $('#mv-form-cotizacion').on('submit.mv', function(e) {
        console.log('=== SUBMIT HANDLER MV ===');
        
        // Validar cliente
        if (!$('#cliente_id').val()) {
            alert('<?php _e('Debe seleccionar un cliente', 'modulo-ventas'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Validar productos
        if ($('#mv-productos-lista .mv-producto-row').length === 0) {
            alert('<?php _e('Debe agregar al menos un producto', 'modulo-ventas'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Recalcular totales
        calcularTotales();
        
        // Verificar que el action está correcto
        var actionUrl = $(this).attr('action');
        console.log('Action URL:', actionUrl);
        
        // Si no hay action o es incorrecto, establecerlo
        if (!actionUrl || actionUrl === '#') {
            $(this).attr('action', window.location.href);
            console.log('Action corregido a:', window.location.href);
        }
        
        // Deshabilitar botón temporalmente
        $('#mv-btn-guardar').prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> <?php _e('Actualizando...', 'modulo-ventas'); ?>'
        );
        
        console.log('Formulario listo para enviar');
        
        // IMPORTANTE: No prevenir el envío
        return true;
    });
    
    // Alternativa: Forzar envío con el botón si el submit no funciona
    $('#mv-btn-guardar').off('click').on('click', function(e) {
        console.log('Botón guardar - forzando submit');
        e.preventDefault(); // Prevenir comportamiento por defecto del botón
        
        // Validaciones
        if (!$('#cliente_id').val()) {
            alert('<?php _e('Debe seleccionar un cliente', 'modulo-ventas'); ?>');
            return false;
        }
        
        if ($('#mv-productos-lista .mv-producto-row').length === 0) {
            alert('<?php _e('Debe agregar al menos un producto', 'modulo-ventas'); ?>');
            return false;
        }
        
        // Forzar el submit del formulario
        console.log('Forzando submit del formulario...');
        $('#mv-form-cotizacion')[0].submit(); // Usar submit nativo, no jQuery
    });
    
    // Debug final
    console.log('Handlers de submit actuales:', $._data($('#mv-form-cotizacion')[0], 'events'));
});
</script>