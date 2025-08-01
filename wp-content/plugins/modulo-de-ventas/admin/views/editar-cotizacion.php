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

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permisos para editar esta página.', 'modulo-ventas'));
}

// Obtener ID de la cotización
$cotizacion_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$cotizacion_id) {
    wp_die(__('ID de cotización no válido.', 'modulo-ventas'));
}

// Obtener instancia de la base de datos
$db = Modulo_Ventas_DB::get_instance();
$cotizacion = $db->get_cotizacion($cotizacion_id);

if (!$cotizacion) {
    wp_die(__('Cotización no encontrada.', 'modulo-ventas'));
}

// Verificar si la cotización puede ser editada
if (in_array($cotizacion->estado, ['convertida', 'cancelada'])) {
    wp_die(__('Esta cotización no puede ser editada.', 'modulo-ventas'));
}

// Obtener items de la cotización
$items = $db->get_items_cotizacion($cotizacion_id);

// Obtener lista de clientes
global $wpdb;
$tabla_clientes = $wpdb->prefix . 'mv_clientes';
$lista_clientes = $wpdb->get_results("SELECT * FROM $tabla_clientes ORDER BY razon_social ASC");

// Obtener almacenes si el plugin está activo
$almacenes = array();
if (class_exists('Gestion_Almacenes_DB')) {
    global $gestion_almacenes_db;
    $almacenes = $gestion_almacenes_db->obtener_almacenes();
}

// Obtener configuración
$config = get_option('mv_configuracion', array());

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mv_cotizacion_nonce'])) {
    if (!wp_verify_nonce($_POST['mv_cotizacion_nonce'], 'mv_editar_cotizacion')) {
        wp_die(__('Error de seguridad. Por favor, intente nuevamente.', 'modulo-ventas'));
    }
    
    // Procesar actualización aquí...
    // (La lógica de actualización se maneja en el controlador principal)
}
?>

<div class="wrap mv-editar-cotizacion">
    <h1>
        <span class="dashicons dashicons-edit"></span>
        <?php echo sprintf(__('Editar Cotización %s', 'modulo-ventas'), esc_html($cotizacion->folio)); ?>
        <a href="<?php echo admin_url('admin.php?page=ventas-ver-cotizacion&id=' . $cotizacion_id); ?>" class="page-title-action">
            <?php _e('Ver Cotización', 'modulo-ventas'); ?>
        </a>
    </h1>
    
    <form method="post" id="mv-form-cotizacion" class="mv-form-cotizacion mv-cotizacion-form" data-validate-decimales="true">
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
                                    <?php echo mv_get_instance()->get_messages()->tooltip(
                                        '<span class="dashicons dashicons-editor-help"></span>',
                                        __('Fecha hasta la cual la cotización es válida', 'modulo-ventas')
                                    ); ?>
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
                            
                            <?php if (mv_almacenes_activo() && !empty($almacenes)) : ?>
                            <!-- Almacén predeterminado -->
                            <div class="mv-form-group">
                                <label for="almacen_id">
                                    <?php _e('Almacén predeterminado', 'modulo-ventas'); ?>
                                    <?php echo mv_get_instance()->get_messages()->tooltip(
                                        '<span class="dashicons dashicons-editor-help"></span>',
                                        __('Almacén por defecto para los productos. Puede cambiar el almacén por cada producto.', 'modulo-ventas')
                                    ); ?>
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
                                    <?php foreach (ventas_get_estados_cotizacion() as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($cotizacion->estado, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
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
                                        <?php if (mv_almacenes_activo()) : ?>
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
                                            // Obtener información del producto
                                            $tabla_productos = $wpdb->prefix . 'mv_productos';
                                            $producto = $wpdb->get_row($wpdb->prepare("
                                                SELECT * FROM $tabla_productos WHERE id = %d
                                            ", $item->producto_id));
                                        ?>
                                            <tr class="mv-producto-row" data-index="<?php echo $index; ?>">
                                                <td class="column-producto">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo esc_attr($item->id); ?>">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][producto_id]" value="<?php echo esc_attr($item->producto_id); ?>">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][variacion_id]" value="<?php echo esc_attr($item->variacion_id ?? 0); ?>">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][sku]" value="<?php echo esc_attr($producto ? $producto->codigo : ''); ?>">
                                                    <div class="mv-producto-info">
                                                        <strong class="mv-producto-nombre"><?php echo esc_html($item->nombre ?? ($producto ? $producto->nombre : 'Producto')); ?></strong>
                                                        <input type="text" 
                                                               name="items[<?php echo $index; ?>][nombre]" 
                                                               value="<?php echo esc_attr($item->nombre ?? ($producto ? $producto->nombre : '')); ?>" 
                                                               class="mv-producto-nombre-input" 
                                                               style="display:none;">
                                                        <?php if ($producto && $producto->codigo): ?>
                                                            <small class="mv-producto-sku">SKU: <?php echo esc_html($producto->codigo); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mv-producto-descripcion">
                                                        <textarea name="items[<?php echo $index; ?>][descripcion]" 
                                                                  placeholder="<?php esc_attr_e('Descripción adicional (opcional)', 'modulo-ventas'); ?>" 
                                                                  rows="2"><?php echo esc_textarea($item->descripcion ?? ''); ?></textarea>
                                                    </div>
                                                </td>
                                                
                                                <?php if (mv_almacenes_activo()) : ?>
                                                <td class="column-almacen">
                                                    <select name="items[<?php echo $index; ?>][almacen_id]" class="mv-select-almacen">
                                                        <option value=""><?php _e('General', 'modulo-ventas'); ?></option>
                                                        <?php foreach ($almacenes as $almacen) : ?>
                                                            <option value="<?php echo esc_attr($almacen->id); ?>" 
                                                                    <?php selected($item->almacen_id ?? '', $almacen->id); ?>>
                                                                <?php echo esc_html($almacen->name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="mv-stock-info">Stock: <span class="mv-stock-cantidad">-</span></small>
                                                </td>
                                                <?php endif; ?>
                                                
                                                <td class="column-cantidad">
                                                    <input type="number" 
                                                        name="items[<?php echo $index; ?>][cantidad]" 
                                                        value="<?php echo esc_attr($item->cantidad); ?>" 
                                                        min="0.01" 
                                                        step="0.01" 
                                                        class="mv-input-cantidad mv-cantidad-field small-text">
                                                </td>
                                                
                                                <td class="column-precio">
                                                    <input type="number" 
                                                        name="items[<?php echo $index; ?>][precio_unitario]" 
                                                        value="<?php echo esc_attr($item->precio_unitario); ?>" 
                                                        min="0" 
                                                        step="1" 
                                                        class="mv-input-precio mv-precio-field regular-text">
                                                    <input type="hidden" name="items[<?php echo $index; ?>][precio_original]" value="<?php echo esc_attr($item->precio_original ?? $item->precio_unitario); ?>">
                                                </td>
                                                
                                                <td class="column-descuento">
                                                    <div class="mv-descuento-item">
                                                        <select name="items[<?php echo $index; ?>][tipo_descuento]" class="mv-tipo-descuento small-text">
                                                            <option value="monto" <?php selected($item->tipo_descuento ?? 'monto', 'monto'); ?>>$</option>
                                                            <option value="porcentaje" <?php selected($item->tipo_descuento ?? '', 'porcentaje'); ?>>%</option>
                                                        </select>
                                                        <input type="number" 
                                                            name="items[<?php echo $index; ?>][descuento_monto]" 
                                                            value="<?php echo esc_attr($item->descuento ?? 0); ?>" 
                                                            min="0" 
                                                            step="0.01" 
                                                            class="mv-input-descuento small-text">
                                                    </div>
                                                </td>
                                                
                                                <td class="column-subtotal">
                                                    <span class="mv-subtotal-item"><?php echo wc_price($item->total); ?></span>
                                                    <input type="hidden" name="items[<?php echo $index; ?>][subtotal]" value="<?php echo esc_attr($item->total); ?>" class="mv-input-subtotal">
                                                </td>
                                                
                                                <td class="column-acciones">
                                                    <button type="button" class="button-link mv-btn-eliminar-producto" title="<?php esc_attr_e('Eliminar', 'modulo-ventas'); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr class="mv-no-productos">
                                            <td colspan="<?php echo mv_almacenes_activo() ? '7' : '6'; ?>" class="text-center">
                                                <?php _e('No hay productos agregados. Use el buscador para agregar productos.', 'modulo-ventas'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="<?php echo mv_almacenes_activo() ? '7' : '6'; ?>">
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
                                <?php _e('Actualizar y Enviar', 'modulo-ventas'); ?>
                            </button>
                            
                            <button type="button" class="button button-large mv-btn-preview">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Vista Previa', 'modulo-ventas'); ?>
                            </button>
                            
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ventas-ver-cotizacion&id=' . $cotizacion_id)); ?>" class="button button-link">
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
                                <tr class="subtotal">
                                    <th><?php _e('Subtotal', 'modulo-ventas'); ?></th>
                                    <td><span id="mv-subtotal"><?php echo wc_price($cotizacion->subtotal); ?></span></td>
                                </tr>
                                
                                <tr class="descuento">
                                    <th>
                                        <?php _e('Descuento', 'modulo-ventas'); ?>
                                        <div class="mv-descuento-controls">
                                            <select name="tipo_descuento_global" id="tipo_descuento_global" class="small-text">
                                                <option value="monto" <?php selected($cotizacion->tipo_descuento_global, 'monto'); ?>>$</option>
                                                <option value="porcentaje" <?php selected($cotizacion->tipo_descuento_global, 'porcentaje'); ?>>%</option>
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
                                        if ($cotizacion->descuento_global > 0) {
                                            if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                                echo wc_price($cotizacion->subtotal * ($cotizacion->descuento_global / 100));
                                            } else {
                                                echo wc_price($cotizacion->descuento_global);
                                            }
                                        } else {
                                            echo wc_price(0);
                                        }
                                        ?>
                                    </span></td>
                                </tr>
                                
                                <tr class="subtotal-descuento">
                                    <th><?php _e('Subtotal c/desc', 'modulo-ventas'); ?></th>
                                    <td><span id="mv-subtotal-descuento">
                                        <?php 
                                        $subtotal_con_descuento = $cotizacion->subtotal;
                                        if ($cotizacion->descuento_global > 0) {
                                            if ($cotizacion->tipo_descuento_global == 'porcentaje') {
                                                $subtotal_con_descuento -= ($cotizacion->subtotal * ($cotizacion->descuento_global / 100));
                                            } else {
                                                $subtotal_con_descuento -= $cotizacion->descuento_global;
                                            }
                                        }
                                        echo wc_price($subtotal_con_descuento);
                                        ?>
                                    </span></td>
                                </tr>
                                
                                <tr class="iva" id="mv-row-iva" <?php echo $cotizacion->incluye_iva ? '' : 'style="display:none;"'; ?>>
                                    <th><?php _e('IVA (19%)', 'modulo-ventas'); ?></th>
                                    <td><span id="mv-iva">
                                        <?php 
                                        if ($cotizacion->incluye_iva) {
                                            echo wc_price($subtotal_con_descuento * 0.19);
                                        } else {
                                            echo wc_price(0);
                                        }
                                        ?>
                                    </span></td>
                                </tr>
                                
                                <tr class="envio">
                                    <th>
                                        <?php _e('Envío', 'modulo-ventas'); ?>
                                        <input type="number" 
                                            name="costo_envio" 
                                            id="costo_envio" 
                                            value="<?php echo esc_attr($cotizacion->envio ?? 0); ?>" 
                                            min="0" 
                                            step="1"
                                            class="small-text mv-precio-field">
                                    </th>
                                    <td><span id="mv-envio"><?php echo wc_price($cotizacion->envio ?? 0); ?></span></td>
                                </tr>
                                
                                <tr class="total">
                                    <th><?php _e('TOTAL', 'modulo-ventas'); ?></th>
                                    <td><strong id="mv-total"><?php echo wc_price($cotizacion->total); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <input type="hidden" name="subtotal" id="input-subtotal" value="<?php echo esc_attr($cotizacion->subtotal); ?>">
                        <input type="hidden" name="total" id="input-total" value="<?php echo esc_attr($cotizacion->total); ?>">
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
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=duplicate&id=' . $cotizacion_id), 'duplicate_cotizacion_' . $cotizacion_id); ?>" 
                               class="button">
                                <?php _e('Duplicar esta cotización', 'modulo-ventas'); ?>
                            </a>
                        </p>
                        <?php if ($cotizacion->estado === 'aprobada' || $cotizacion->estado === 'aceptada'): ?>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=convert&id=' . $cotizacion_id), 'convert_cotizacion_' . $cotizacion_id); ?>" 
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

<!-- Template para línea de producto -->
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
                       class="mv-producto-nombre-input" 
                       style="display:none;">
                <small class="mv-producto-sku">SKU: {{sku}}</small>
            </div>
            <div class="mv-producto-descripcion">
                <textarea name="items[{{index}}][descripcion]" 
                          placeholder="<?php esc_attr_e('Descripción adicional (opcional)', 'modulo-ventas'); ?>" 
                          rows="2"></textarea>
            </div>
        </td>
        
        <?php if (mv_almacenes_activo()) : ?>
        <td class="column-almacen">
            <select name="items[{{index}}][almacen_id]" class="mv-select-almacen">
                <option value=""><?php _e('General', 'modulo-ventas'); ?></option>
                {{opciones_almacen}}
            </select>
            <small class="mv-stock-info">Stock: <span class="mv-stock-cantidad">-</span></small>
        </td>
        <?php endif; ?>
        
        <td class="column-cantidad">
            <input type="number" 
                   name="items[{{index}}][cantidad]" 
                   value="1" 
                   min="0.01" 
                   step="0.01" 
                   class="mv-input-cantidad small-text">
        </td>
        
        <td class="column-precio">
            <input type="number" 
                   name="items[{{index}}][precio_unitario]" 
                   value="{{precio}}" 
                   min="0" 
                   step="1" 
                   class="mv-input-precio regular-text">
            <input type="hidden" name="items[{{index}}][precio_original]" value="{{precio_original}}">
        </td>
        
        <td class="column-descuento">
            <div class="mv-descuento-item">
                <select name="items[{{index}}][tipo_descuento]" class="mv-tipo-descuento small-text">
                    <option value="monto">$</option>
                    <option value="porcentaje">%</option>
                </select>
                <input type="number" 
                    name="items[{{index}}][descuento_monto]" 
                    value="0" 
                    min="0" 
                    step="0.01" 
                    class="mv-input-descuento mv-precio-field small-text ">
            </div>
        </td>
        
        <td class="column-subtotal">
            <span class="mv-subtotal-item">0</span>
            <input type="hidden" name="items[{{index}}][subtotal]" value="0" class="mv-input-subtotal">
        </td>
        
        <td class="column-acciones">
            <button type="button" class="button-link mv-btn-eliminar-producto" title="<?php esc_attr_e('Eliminar', 'modulo-ventas'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </td>
    </tr>
</script>

<!-- Estilos (reutilizados de nueva-cotizacion.php) -->
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

.column-producto {
    width: 35%;
}

.column-almacen {
    width: 15%;
}

.column-cantidad {
    width: 10%;
}

.column-precio {
    width: 15%;
}

.column-descuento {
    width: 12%;
}

.column-subtotal {
    width: 12%;
    text-align: right;
}

.column-acciones {
    width: 50px;
    text-align: center;
}

.mv-producto-info {
    margin-bottom: 5px;
}

.mv-producto-nombre {
    display: block;
}

.mv-producto-sku {
    color: #646970;
}

.mv-producto-descripcion textarea {
    width: 100%;
    font-size: 12px;
}

.mv-descuento-item {
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

/* Info list */
.mv-info-list .mv-info-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f1;
}

.mv-info-list .mv-info-row:last-child {
    border-bottom: none;
}

.mv-info-list label {
    color: #646970;
    font-weight: normal;
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

/* Estados de validación */
.mv-input-error {
    border-color: #d63638 !important;
}

.mv-error-message {
    color: #d63638;
    font-size: 13px;
    margin-top: 5px;
}

/* Loading */
.mv-loading {
    opacity: 0.6;
    pointer-events: none;
}

.mv-loading:after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.text-center {
    text-align: center;
}
</style>

<script type="text/javascript">
// Esperar a que jQuery esté listo
jQuery(document).ready(function($) {
    // Variables globales
    var productoIndex = <?php echo count($items); ?>;
    var almacenesDisponibles = <?php echo json_encode(mv_almacenes_activo() ? $almacenes : array()); ?>;
    
    // Inicializar Select2
    if ($.fn.select2) {
        // Cliente
        $('.mv-select2-cliente').select2({
            placeholder: '<?php _e('Seleccione un cliente', 'modulo-ventas'); ?>',
            allowClear: true,
            width: '100%'
        });
        
        // Productos
        $('.mv-select2-productos').select2({
            placeholder: '<?php _e('Buscar productos...', 'modulo-ventas'); ?>',
            minimumInputLength: 2,
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'mv_buscar_productos',
                        busqueda: params.term,
                        almacen_id: $('#almacen_id').val(),
                        nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
                    };
                },
                processResults: function(data) {
                    if (data.success) {
                        return {
                            results: data.data.productos
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            templateResult: formatProducto,
            templateSelection: function(producto) {
                if (producto.id) {
                    agregarProducto(producto);
                }
                return null;
            }
        });
    }
    
    // Formato para mostrar productos en Select2
    function formatProducto(producto) {
        if (!producto.id) return producto.text;
        
        var $producto = $(
            '<div class="mv-producto-result">' +
                '<div class="mv-producto-nombre">' + producto.nombre + '</div>' +
                '<div class="mv-producto-meta">' +
                    'SKU: ' + (producto.sku || 'N/A') + ' | ' +
                    'Stock: ' + (producto.stock || 0) + ' | ' +
                    'Precio: ' + moduloVentasAjax.currency_symbol + producto.precio +
                '</div>' +
            '</div>'
        );
        
        return $producto;
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
        
        $.post(ajaxurl, {
            action: 'mv_crear_cliente_rapido',
            cliente: $form.serializeArray().reduce(function(obj, item) {
                var keys = item.name.match(/\[([^\]]+)\]/g);
                if (keys) {
                    var key = keys[0].replace(/[\[\]]/g, '');
                    obj[key] = item.value;
                }
                return obj;
            }, {}),
            nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                // Agregar cliente al select
                var nuevoCliente = response.data.cliente;
                var option = new Option(
                    nuevoCliente.razon_social + ' - ' + nuevoCliente.rut,
                    nuevoCliente.id,
                    true,
                    true
                );
                
                $('#cliente_id').append(option).trigger('change');
                
                // Cerrar modal y limpiar formulario
                $('#mv-modal-nuevo-cliente').fadeOut();
                $form[0].reset();
                
                // Mostrar mensaje
                mvShowToast('<?php _e('Cliente creado exitosamente', 'modulo-ventas'); ?>', 'success');
            } else {
                alert(response.data.message);
            }
        }).always(function() {
            $submit.prop('disabled', false).text('<?php _e('Crear Cliente', 'modulo-ventas'); ?>');
        });
    });
    
    // Agregar producto a la tabla
    function agregarProducto(producto) {
        // Eliminar mensaje de "no hay productos"
        $('.mv-no-productos').remove();
        
        // Preparar opciones de almacén
        var opcionesAlmacen = '';
        if (almacenesDisponibles.length > 0) {
            almacenesDisponibles.forEach(function(almacen) {
                opcionesAlmacen += '<option value="' + almacen.id + '">' + almacen.name + '</option>';
            });
        }
        
        // Obtener template
        var template = $('#mv-template-producto').html();
        
        // Reemplazar variables
        var html = template
            .replace(/{{index}}/g, productoIndex)
            .replace(/{{producto_id}}/g, producto.id)
            .replace(/{{variacion_id}}/g, producto.variacion_id || 0)
            .replace(/{{nombre}}/g, producto.nombre)
            .replace(/{{sku}}/g, producto.sku || '')
            .replace(/{{precio}}/g, producto.precio)
            .replace(/{{precio_original}}/g, producto.precio_regular || producto.precio)
            .replace(/{{opciones_almacen}}/g, opcionesAlmacen);
        
        // Agregar a la tabla
        $('#mv-productos-lista').append(html);
        
        // Incrementar índice
        productoIndex++;
        
        // Calcular totales
        calcularTotales();
        
        // Limpiar select de productos
        $('.mv-select2-productos').val(null).trigger('change');
    }
    
    // Agregar línea personalizada
    $('.mv-btn-agregar-linea').on('click', function() {
        var producto = {
            id: 0,
            variacion_id: 0,
            nombre: '',
            sku: 'CUSTOM',
            precio: 0,
            precio_regular: 0
        };
        
        agregarProducto(producto);
        
        // Hacer editable el nombre
        var $row = $('#mv-productos-lista tr:last');
        $row.find('.mv-producto-nombre').hide();
        $row.find('.mv-producto-nombre-input').show().focus();
    });
    
    // Eliminar producto
    $(document).on('click', '.mv-btn-eliminar-producto', function() {
        var $row = $(this).closest('tr');
        var itemId = $row.find('input[name*="[id]"]').val();
        
        // Si el item tiene ID, marcarlo para eliminar
        if (itemId) {
            $row.hide();
            $row.append('<input type="hidden" name="items_eliminar[]" value="' + itemId + '">');
        } else {
            // Si es nuevo, simplemente eliminar
            $row.remove();
        }
        
        // Si no quedan productos visibles, mostrar mensaje
        if ($('#mv-productos-lista tr:visible').length === 0) {
            $('#mv-productos-lista').html(
                '<tr class="mv-no-productos">' +
                    '<td colspan="' + (almacenesDisponibles.length > 0 ? '7' : '6') + '" class="text-center">' +
                        '<?php _e('No hay productos agregados. Use el buscador para agregar productos.', 'modulo-ventas'); ?>' +
                    '</td>' +
                '</tr>'
            );
        }
        
        calcularTotales();
    });
    
    // Cambio de almacén - actualizar stock
    $(document).on('change', '.mv-select-almacen', function() {
        var $row = $(this).closest('tr');
        var productoId = $row.find('input[name*="[producto_id]"]').val();
        var variacionId = $row.find('input[name*="[variacion_id]"]').val();
        var almacenId = $(this).val();
        var $stockInfo = $row.find('.mv-stock-cantidad');
        
        if (productoId) {
            $stockInfo.text('...');
            
            $.post(ajaxurl, {
                action: 'mv_obtener_stock_producto',
                producto_id: productoId,
                variacion_id: variacionId,
                almacen_id: almacenId,
                nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
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
                    
                    // Marcar en rojo si no hay suficiente stock
                    var cantidad = parseFloat($row.find('.mv-input-cantidad').val()) || 0;
                    if (stock < cantidad) {
                        $stockInfo.css('color', '#d63638');
                    } else {
                        $stockInfo.css('color', '');
                    }
                }
            });
        }
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
        $('#mv-productos-lista .mv-producto-row:visible').each(function() {
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
            $row.find('.mv-input-subtotal').val(subtotalItem.toFixed(2));
            
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
        
        // IVA
        var iva = incluyeIva ? subtotalConDescuento * 0.19 : 0;
        
        // Envío
        var envio = parseFloat($('#costo_envio').val()) || 0;
        
        // Total
        var total = subtotalConDescuento + iva + envio;
        
        // Actualizar UI
        $('#mv-subtotal').text(formatearPrecio(subtotal));
        $('#mv-descuento-total').text(formatearPrecio(descuentoGlobalMonto));
        $('#mv-subtotal-descuento').text(formatearPrecio(subtotalConDescuento));
        $('#mv-iva').text(formatearPrecio(iva));
        $('#mv-envio').text(formatearPrecio(envio));
        $('#mv-total').text(formatearPrecio(total));
        
        // Actualizar inputs hidden
        $('#input-subtotal').val(subtotal.toFixed(2));
        $('#input-total').val(total.toFixed(2));
    }
    
    // Formatear precio
    function formatearPrecio(valor) {
        return moduloVentasAjax.currency_symbol + ' ' + 
            new Intl.NumberFormat('es-CL', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(valor);
    }
    
    // Vista previa
    $('.mv-btn-preview').on('click', function() {
        // Guardar temporalmente y abrir en nueva ventana
        var formData = $('#mv-form-cotizacion').serialize();
        formData += '&action=preview';
        
        // Crear formulario temporal para POST en nueva ventana
        var $form = $('<form>', {
            action: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            target: '_blank'
        });
        
        $form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'mv_preview_cotizacion'
        }));
        
        $form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
        }));
        
        $form.append($('<input>', {
            type: 'hidden',
            name: 'data',
            value: JSON.stringify($('#mv-form-cotizacion').serializeArray())
        }));
        
        $form.appendTo('body').submit().remove();
    });
    
    // Validación del formulario
    $('#mv-form-cotizacion').on('submit', function(e) {
        var valido = true;
        var errores = [];
        
        // Limpiar errores previos
        $('.mv-input-error').removeClass('mv-input-error');
        
        // Validar cliente
        if (!$('#cliente_id').val()) {
            valido = false;
            errores.push('<?php _e('Debe seleccionar un cliente', 'modulo-ventas'); ?>');
            $('#cliente_id').addClass('mv-input-error');
        }
        
        // Validar productos
        if ($('#mv-productos-lista .mv-producto-row:visible').length === 0) {
            valido = false;
            errores.push('<?php _e('Debe agregar al menos un producto', 'modulo-ventas'); ?>');
        }
        
        // Validar stock si está activo el plugin de almacenes
        if (almacenesDisponibles.length > 0) {
            var stockInsuficiente = false;
            $('#mv-productos-lista .mv-producto-row:visible').each(function() {
                var $row = $(this);
                var cantidad = parseFloat($row.find('.mv-input-cantidad').val()) || 0;
                var stock = parseFloat($row.find('.mv-stock-cantidad').text()) || 0;
                var almacenId = $row.find('.mv-select-almacen').val();
                
                if (almacenId && stock < cantidad) {
                    stockInsuficiente = true;
                    $row.find('.mv-input-cantidad').addClass('mv-input-error');
                }
            });
            
            if (stockInsuficiente) {
                valido = false;
                errores.push('<?php _e('Algunos productos no tienen suficiente stock en el almacén seleccionado', 'modulo-ventas'); ?>');
            }
        }
        
        // Validar fechas
        var fecha = $('#fecha').val();
        var fechaExpiracion = $('#fecha_expiracion').val();
        if (fecha && fechaExpiracion && new Date(fechaExpiracion) < new Date(fecha)) {
            valido = false;
            errores.push('<?php _e('La fecha de expiración no puede ser anterior a la fecha de la cotización', 'modulo-ventas'); ?>');
            $('#fecha_expiracion').addClass('mv-input-error');
        }
        
        if (!valido) {
            e.preventDefault();
            alert(errores.join('\n'));
            return false;
        }
        
        // Deshabilitar botón de envío
        $('#mv-btn-guardar').prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> <?php _e('Actualizando...', 'modulo-ventas'); ?>'
        );
    });
    
    // Función toast (simple implementación)
    function mvShowToast(message, type) {
        var $toast = $('<div class="notice notice-' + type + ' is-dismissible mv-toast">' +
                      '<p>' + message + '</p>' +
                      '</div>');
        
        $('h1').after($toast);
        
        setTimeout(function() {
            $toast.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Actualizar stock al cargar para productos existentes
    $('.mv-select-almacen').each(function() {
        $(this).trigger('change');
    });
    
    // Calcular totales iniciales
    calcularTotales();
    
    // Advertencia al salir sin guardar
    var cambiosSinGuardar = false;
    
    $('#mv-form-cotizacion').on('change', 'input, select, textarea', function() {
        cambiosSinGuardar = true;
    });
    
    $('#mv-form-cotizacion').on('submit', function() {
        cambiosSinGuardar = false;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (cambiosSinGuardar) {
            e.preventDefault();
            e.returnValue = '<?php _e('Hay cambios sin guardar. ¿Está seguro de que desea salir?', 'modulo-ventas'); ?>';
        }
    });

    // Configurar validación de decimales cuando se agreguen productos dinámicamente
    $(document).on('click', '.mv-btn-agregar-producto', function() {
        // Después de agregar el producto, reconfigurar validación
        setTimeout(function() {
            if (typeof window.mvActualizarConfigDecimales === 'function') {
                window.mvActualizarConfigDecimales({});
            }
        }, 100);
    });

    // También configurar para productos ya existentes al cargar la página
    $(document).ready(function() {
        // Esperar a que se cargue el script de decimales
        setTimeout(function() {
            if (typeof window.mvActualizarConfigDecimales === 'function') {
                window.mvActualizarConfigDecimales({});
            }
        }, 500);
    });
});
</script>