<?php
/**
 * Vista del formulario de nueva cotización
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// SOLUCIÓN TEMPORAL: Incluir helpers manualmente si no están cargados
if (!function_exists('mv_calcular_fecha_expiracion')) {
    $helpers_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/helpers.php';
    if (file_exists($helpers_path)) {
        require_once $helpers_path;
        error_log('[MODULO_VENTAS] Helpers cargado manualmente desde nueva-cotizacion.php');
    } else {
        error_log('[MODULO_VENTAS] No se pudo encontrar helpers.php en: ' . $helpers_path);
    }
}

// Variables disponibles:
// $lista_clientes - Array de clientes
// $almacenes - Array de almacenes disponibles
// $config - Configuración del plugin

// Función temporal para tooltips
if (!function_exists('mv_tooltip')) {
    function mv_tooltip($content, $text) {
        return $content; // Por ahora, solo retornar el contenido sin tooltip
    }
}

?>

<div class="wrap mv-nueva-cotizacion">
    <h1>
        <span class="dashicons dashicons-plus-alt"></span>
        <?php _e('Nueva Cotización', 'modulo-ventas'); ?>
    </h1>
    
    <form method="post" id="mv-form-cotizacion" class="mv-form-cotizacion">
        <?php wp_nonce_field('modulo_ventas_nonce', 'mv_cotizacion_nonce'); ?>
        
        <!-- Columna principal -->
        <div class="mv-form-main">
            
            <!-- Información general -->
            <div class="postbox">
                <h2 class="hndle">
                    <span style="margin: 1em;"><?php _e('Información General', 'modulo-ventas'); ?></span>
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
                            <div id="mv-cliente-info" class="mv-cliente-info" style="display: none;">
                                <div class="mv-info-row">
                                    <span class="mv-info-label"><?php _e('RUT:', 'modulo-ventas'); ?></span>
                                    <span class="mv-info-value" data-field="rut">-</span>
                                </div>
                                <div class="mv-info-row">
                                    <span class="mv-info-label"><?php _e('Email:', 'modulo-ventas'); ?></span>
                                    <span class="mv-info-value" data-field="email">-</span>
                                </div>
                                <div class="mv-info-row">
                                    <span class="mv-info-label"><?php _e('Teléfono:', 'modulo-ventas'); ?></span>
                                    <span class="mv-info-value" data-field="telefono">-</span>
                                </div>
                                <div class="mv-info-row">
                                    <span class="mv-info-label"><?php _e('Dirección:', 'modulo-ventas'); ?></span>
                                    <span class="mv-info-value" data-field="direccion">-</span>
                                </div>
                                <div class="mv-info-row">
                                    <span class="mv-info-label"><?php _e('Giro:', 'modulo-ventas'); ?></span>
                                    <span class="mv-info-value" data-field="giro">-</span>
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
                                    value="<?php echo esc_attr(current_time('Y-m-d')); ?>" 
                                    class="regular-text">
                        </div>
                        
                        <!-- Fecha de expiración -->
                        <div class="mv-form-group">
                            <label for="fecha_expiracion">
                                <?php _e('Válida hasta', 'modulo-ventas'); ?>
                                <?php echo mv_tooltip(
                                    '<span class="dashicons dashicons-editor-help"></span>',
                                    __('Fecha de expiración', 'modulo-ventas')
                                ); ?>
                            </label>
                            <input type="date" 
                                    name="fecha_expiracion" 
                                    id="fecha_expiracion" 
                                    value="<?php echo esc_attr(mv_calcular_fecha_expiracion()); ?>"
                                    min="<?php echo esc_attr(current_time('Y-m-d')); ?>"
                                    class="regular-text">
                        </div>
                        
                        <!-- Plazo de pago -->
                        <div class="mv-form-group">
                            <label for="plazo_pago">
                                <?php _e('Plazo de pago', 'modulo-ventas'); ?>
                            </label>
                            <select name="plazo_pago" id="plazo_pago" class="regular-text">
                                <?php foreach (mv_get_plazos_pago() as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>">
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
                                            <?php selected(get_current_user_id(), $vendedor->ID); ?>>
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
                                <?php echo mv_tooltip(
                                    '<span class="dashicons dashicons-editor-help"></span>',
                                    __('Almacén por defecto para los productos. Puede cambiar el almacén por cada producto.', 'modulo-ventas')
                                ); ?>
                            </label>
                            <select name="almacen_id" id="almacen_id" class="regular-text">
                                <option value=""><?php _e('Sin almacén específico', 'modulo-ventas'); ?></option>
                                <?php foreach ($almacenes as $almacen) : ?>
                                    <option value="<?php echo esc_attr($almacen->id); ?>"
                                            <?php selected($config['almacen_predeterminado'], $almacen->id); ?>>
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
                                    checked>
                                <?php _e('Incluir IVA (19%)', 'modulo-ventas'); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Productos -->
            <div class="postbox">
                <h2 class="hndle">
                    <span style="margin: 1em;"><?php _e('Productos / Servicios', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    
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
                                    <th class="column-acciones"><?php _e('Accion', 'modulo-ventas'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="mv-productos-lista">
                                <tr class="mv-no-productos">
                                    <td colspan="<?php echo mv_almacenes_activo() ? '7' : '6'; ?>" class="text-center">
                                        <?php _e('No hay productos agregados. Use el buscador para agregar productos.', 'modulo-ventas'); ?>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <!-- Buscador en tfoot -->
                                <tr>
                                    <td colspan="<?php echo mv_almacenes_activo() ? '7' : '6'; ?>">
                                        <label for="buscar_producto" class="screen-reader-text">
                                            <?php _e('Buscar producto', 'modulo-ventas'); ?>
                                        </label>
                                        <select id="buscar_producto" class="mv-select2-productos" style="width: 100%;">
                                            <option value=""><?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <!-- Linea personalizada --
                                <tr>
                                    <td colspan="<?php echo mv_almacenes_activo() ? '7' : '6'; ?>">
                                        <button type="button" class="button mv-btn-agregar-linea">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                            <?php _e('Agregar línea personalizada', 'modulo-ventas'); ?>
                                        </button>
                                    </td>
                                </tr>-->
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>            
        </div>

        <div class="mv-form-container">    

            <!-- Observaciones -->
            <div class="postbox" id="box-observaciones">
                <h2 class="hndle">
                    <span style="margin: 1em;"><?php _e('Información Adicional', 'modulo-ventas'); ?></span>
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
                                    placeholder="<?php esc_attr_e('Observaciones que aparecerán en la cotización...', 'modulo-ventas'); ?>"></textarea>
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
                                    placeholder="<?php esc_attr_e('Notas privadas sobre esta cotización...', 'modulo-ventas'); ?>"></textarea>
                    </div>
                    
                    <div class="mv-form-group">
                        <label for="terminos_condiciones">
                            <?php _e('Términos y condiciones', 'modulo-ventas'); ?>
                        </label>
                        <textarea name="terminos_condiciones" 
                                    id="terminos_condiciones" 
                                    rows="4" 
                                    class="large-text"><?php echo esc_textarea($config['terminos_condiciones'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Resumen de totales -->
            <div class="postbox" id="box-totales">
                <h2 class="hndle">
                    <span style="margin: 1em;"><?php _e('Resumen', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <table class="mv-totales-tabla">
                        <tbody>
                            <tr class="subtotal">
                                <th><?php _e('Subtotal', 'modulo-ventas'); ?></th>
                                <td><span id="mv-subtotal">0</span></td>
                            </tr>
                            
                            <tr class="descuento">
                                <th>
                                    <?php _e('Descuento', 'modulo-ventas'); ?>
                                    <div class="mv-descuento-controls">
                                        <select name="tipo_descuento_global" id="tipo_descuento_global" class="small-text">
                                            <option value="monto">$</option>
                                            <option value="porcentaje">%</option>
                                        </select>
                                        <input type="number" 
                                            name="descuento_global" 
                                            id="descuento_global" 
                                            value="0" 
                                            min="0" 
                                            step="0.01"
                                            class="small-text">
                                    </div>
                                </th>
                                <td><span id="mv-descuento-total">0</span></td>
                            </tr>
                            
                            <tr class="subtotal-descuento">
                                <th><?php _e('Subtotal c/desc', 'modulo-ventas'); ?></th>
                                <td><span id="mv-subtotal-descuento">0</span></td>
                            </tr>
                            
                            <tr class="envio">
                                <th>
                                    <?php _e('Envío', 'modulo-ventas'); ?>
                                    <input type="number" 
                                        name="costo_envio" 
                                        id="costo_envio" 
                                        value="0" 
                                        min="0" 
                                        step="1"
                                        class="small-text">
                                </th>
                                <td><span id="mv-envio">0</span></td>
                            </tr>
                            
                            <tr class="subtotal-con-envio">
                                <th><?php _e('Subtotal + Envío', 'modulo-ventas'); ?></th>
                                <td><span id="mv-subtotal-con-envio">0</span></td>
                            </tr>
                            
                            <tr class="iva" id="mv-row-iva">
                                <th><?php _e('IVA (19%)', 'modulo-ventas'); ?></th>
                                <td><span id="mv-iva">0</span></td>
                            </tr>
                            
                            <tr class="total">
                                <th><?php _e('TOTAL', 'modulo-ventas'); ?></th>
                                <td><strong id="mv-total">0</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <input type="hidden" name="subtotal" id="input-subtotal" value="0">
                    <input type="hidden" name="total" id="input-total" value="0">
                    <input type="hidden" name="descuento_monto" id="descuento_monto" value="0">
                    <input type="hidden" name="descuento_porcentaje" id="descuento_porcentaje" value="0">
                    <input type="hidden" name="condiciones_pago" id="condiciones_pago" value="">
                </div>
            </div>
        </div>

        <div class="mv-form-container">

            <!-- Acciones -->
            <div class="postbox" id="box-acciones">
                <h2 class="hndle">
                    <span style="margin: 1em;"><?php _e('Acciones', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <div class="mv-actions">
                        <button type="submit" name="action" value="save" class="button button-primary button-large" id="mv-btn-guardar">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Crear Cotización', 'modulo-ventas'); ?>
                        </button>
                        
                        <button type="submit" name="action" value="save_and_new" class="button button-large">
                            <?php _e('Crear y Nueva', 'modulo-ventas'); ?>
                        </button>
                        
                        <button type="button" class="button button-large mv-btn-preview">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Vista Previa', 'modulo-ventas'); ?>
                        </button>
                        
                        <a href="<?php echo esc_url(mv_admin_url('cotizaciones')); ?>" class="button button-link">
                            <?php _e('Cancelar', 'modulo-ventas'); ?>
                        </a>
                    </div>
                </div>
            </div>
                
            <!-- Plantillas rápidas -->
            <?php if (apply_filters('mv_mostrar_plantillas', true)) : ?>
            <div class="postbox" id="box-plantillas">
                <h2 class="hndle">
                    <span style="margin: 1em;"><?php _e('Plantillas Rápidas', 'modulo-ventas'); ?></span>
                </h2>
                <div class="inside">
                    <select id="mv-plantillas" class="widefat">
                        <option value=""><?php _e('Seleccionar plantilla...', 'modulo-ventas'); ?></option>
                        <?php
                        $plantillas = apply_filters('mv_plantillas_cotizacion', array());
                        foreach ($plantillas as $key => $plantilla) : ?>
                            <option value="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($plantilla['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Cargue una plantilla predefinida con productos y configuración.', 'modulo-ventas'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                    class="mv-input-descuento small-text-dcto">
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

<style type="text/css">
/* Layout principal */
.mv-form-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
    grid-template-areas: "box-observaciones box-totales" ;
}

#box-observaciones {
    grid-area: box-observaciones;
}

#box-totales {
    grid-area: box-totales;
}

/* Responsive */
@media screen and (max-width: 800px) {
    .mv-form-container {
        grid-template-columns: 1fr;
        grid-template-areas:
            "box-totales"
            "box-observaciones"
    }
    
    .mv-form-sidebar {
        order: -1;
    }
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
.mv-cliente-result {
    padding: 2px 0;
}

.mv-cliente-option {
    padding: 2px 0;
}

.mv-cliente-nombre {
    font-weight: 600;
    line-height: 1.3;
    color: #23282d;
}

.mv-cliente-meta {
    font-size: 12px;
    color: #666;
    line-height: 1.3;
}

.mv-cliente-email {
    font-size: 12px;
    color: #666;
    line-height: 1.3;
}

.select2-results__option--highlighted .mv-cliente-nombre {
    color: #fff;
}

.select2-results__option--highlighted .mv-cliente-meta {
    color: #f0f0f0;
}

/* Fix para el ancho del dropdown */
.select2-container--default .select2-dropdown {
    min-width: 350px;
}

/* Loading spinner */
.select2-container--default .select2-results__option.loading-results:before {
    content: '<?php _e('Buscando...', 'modulo-ventas'); ?>';
}

/*.select2-results__option--highlighted .mv-cliente-email {
    color: #fff;
    opacity: 0.9;
}*/

.mv-cliente-info {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.mv-info-row {
    display: flex;
    margin-bottom: 8px;
    font-size: 14px;
}

.mv-info-row:last-child {
    margin-bottom: 0;
}

.mv-info-label {
    font-weight: 600;
    width: 100px;
    color: #555;
}

.mv-info-value {
    flex: 1;
    color: #333;
}
/*.mv-cliente-info {
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
}*/

/* Tabla de productos */
.mv-productos-tabla-wrapper {
    margin-top: 20px;
    overflow-x: auto;
}

#mv-tabla-productos {
    min-width: 700px;
}

.column-producto {
    width: 30%;
}

.column-almacen {
    width: 12%;
}

.column-cantidad {
    width: 7%;
}

.column-precio {
    width: 14%;
}

.column-descuento {
    width: 14%;
}

.column-subtotal {
    width: 8%;
    text-align: left;
}

.column-acciones {
    width: 50px;
    text-align: center;
}

select.mv-select-almacen, input.mv-input-cantidad.small-text, input.mv-input-precio.regular-text {
    width: -webkit-fill-available;
}

.mv-descuento-item {
    display: flex
;
}

input.mv-input-descuento.small-text-dcto {
    width: 100%;
}

input#costo_envio, input#descuento_global {
    width: 80px;
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
    max-width: 800px;
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
    display: flex;
    justify-content: flex-end;
    gap: 10px;
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
</style>

<script type="text/javascript">
// Esperar a que jQuery esté listo
jQuery(document).ready(function($) {
    // Variables globales
    var productoIndex = 0;
    var almacenesDisponibles = <?php echo json_encode(mv_almacenes_activo() ? $almacenes : array()); ?>;
    
    console.log('=== OVERRIDE SELECT2 ===');
    
    // Paso 1: Destruir CUALQUIER inicialización previa
    function destruirSelect2Cliente() {
        if ($('#cliente_id').data('select2')) {
            console.log('Destruyendo Select2 previo...');
            $('#cliente_id').select2('destroy');
        }
    }
    
    // Paso 2: Nuestra inicialización
    function inicializarSelect2Cliente() {
        console.log('Inicializando Select2 con AJAX (override)...');
        
        // Limpiar opciones excepto placeholder
        $('#cliente_id').find('option:not(:first)').remove();
        
        $('#cliente_id').select2({
            placeholder: 'Digite para buscar cliente...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'mv_buscar_cliente',
                        termino: params.term || '',
                        nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
                    };
                },
                processResults: function(response) {
                    console.log('Respuesta AJAX recibida');
                    if (response.success && response.data && response.data.results) {
                        return {
                            results: response.data.results.map(function(cliente) {
                                return {
                                    id: cliente.id,
                                    text: cliente.text || (cliente.razon_social + ' - ' + cliente.rut),
                                    rut: cliente.rut,
                                    email: cliente.email,
                                    telefono: cliente.telefono,
                                    direccion: cliente.direccion,
                                    giro: cliente.giro_comercial || cliente.giro
                                };
                            })
                        };
                    }
                    return { results: [] };
                }
            }
        });
        
        // Manejar selección
        $('#cliente_id').on('select2:select', function(e) {
            var data = e.params.data;
            var $option = $(this).find('option[value="' + data.id + '"]');
            if ($option.length === 0) {
                $option = $('<option></option>').attr('value', data.id).text(data.text);
                $(this).append($option);
            }
            $option.attr({
                'selected': true,
                'data-rut': data.rut || '',
                'data-email': data.email || '',
                'data-telefono': data.telefono || '',
                'data-direccion': data.direccion || '',
                'data-giro': data.giro || ''
            });
            $(this).val(data.id).trigger('change');
        });
        
        console.log('Select2 AJAX configurado');
    }
    
    // Paso 3: Ejecutar inmediatamente
    destruirSelect2Cliente();
    inicializarSelect2Cliente();
    
    // Paso 4: Ejecutar de nuevo después de un delay para sobrescribir cualquier inicialización tardía
    setTimeout(function() {
        console.log('Verificando si Select2 fue sobrescrito...');
        var instance = $('#cliente_id').data('select2');
        if (instance && instance.options && instance.options.options) {
            if (!instance.options.options.ajax) {
                console.log('¡Select2 fue sobrescrito! Re-inicializando...');
                destruirSelect2Cliente();
                inicializarSelect2Cliente();
            } else {
                console.log('✅ Select2 mantiene configuración AJAX');
            }
        }
    }, 500);
    
    // Paso 5: Otro check más tarde
    setTimeout(function() {
        var instance = $('#cliente_id').data('select2');
        if (instance && instance.options && instance.options.options && instance.options.options.ajax) {
            console.log('✅ FINAL: Select2 con AJAX funcionando');
        } else {
            console.log('❌ FINAL: Select2 sin AJAX - ejecute manualmente: inicializarSelect2ClienteAjax()');
        }
    }, 1000);
    
    // Exponer función global para reinicializar manualmente
    window.inicializarSelect2ClienteAjax = inicializarSelect2Cliente;

    function formatClienteResult(cliente) {
        if (!cliente.id || cliente.disabled) {
            return $('<span>' + cliente.text + '</span>');
        }
        
        var $container = $(
            '<div class="mv-cliente-result">' +
                '<div class="mv-cliente-nombre">' + (cliente.razon_social || cliente.text) + '</div>' +
                '<div class="mv-cliente-meta">' +
                    (cliente.rut ? 'RUT: ' + cliente.rut : '') +
                    (cliente.email ? ' | Email: ' + cliente.email : '') +
                '</div>' +
            '</div>'
        );
        
        return $container;
    }

    // Función para formatear la selección
    function formatClienteSelection(cliente) {
        return cliente.text || (cliente.razon_social + ' - ' + cliente.rut) || cliente.id;
    }

    // Función para formatear la visualización de clientes
    function formatCliente(cliente) {
        if (!cliente.id) {
            return cliente.text;
        }
        
        var $option = $(cliente.element);
        var rut = $option.attr('data-rut') || '';
        var email = $option.attr('data-email') || '';
        
        var $container = $(
            '<div class="mv-cliente-option">' +
                '<div class="mv-cliente-nombre">' + cliente.text + '</div>' +
                (email ? '<div class="mv-cliente-email">' + email + '</div>' : '') +
            '</div>'
        );
        
        return $container;
    }

    // Función de emergencia para resetear todo
    window.resetearSelectCliente = function() {
        var $select = $('#cliente_id');
        
        // Destruir Select2
        if ($select.data('select2')) {
            $select.select2('destroy');
        }
        
        // Limpiar y agregar solo placeholder
        $select.empty().append('<option value="">Seleccione un cliente</option>');
        
        // Convertir en input normal temporalmente para debug
        $select.show();
        
        console.log('Select reseteado. Ahora es un select normal.');
    };

    // Test manual de búsqueda de clientes
    window.testBusquedaClientes = function(termino) {
        console.log('=== TEST BÚSQUEDA DE CLIENTES ===');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_buscar_cliente',
                termino: termino || 'test',
                nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
            },
            success: function(response) {
                console.log('Respuesta:', response);
                if (response.success && response.data) {
                    console.log('Clientes encontrados:', response.data.results || response.data.clientes);
                } else {
                    console.error('No se encontraron clientes o error en respuesta');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                console.error('Respuesta:', xhr.responseText);
            }
        });
    };
    
    console.log('Para probar búsqueda de clientes, ejecuta: testBusquedaClientes("término")');

    // CSS adicional para mejorar la visualización
    if (!$('#mv-cliente-search-styles').length) {
        $('head').append(`
            <style id="mv-cliente-search-styles">
            .mv-cliente-result {
                padding: 2px 0;
            }
            
            .mv-cliente-nombre {
                font-weight: 600;
                line-height: 1.3;
                color: #23282d;
            }
            
            .mv-cliente-meta {
                font-size: 12px;
                color: #666;
                line-height: 1.3;
            }
            
            .select2-results__option--highlighted .mv-cliente-nombre {
                color: #fff;
            }
            
            .select2-results__option--highlighted .mv-cliente-meta {
                color: #f0f0f0;
            }
            
            /* Fix para el ancho del dropdown */
            .select2-container--default .select2-dropdown {
                min-width: 350px;
            }
            
            /* Estilos para cuando no hay resultados */
            .select2-results__option--disabled {
                color: #999;
                font-style: italic;
            }
            </style>
        `);
    }

    // Reconfigurar Select2 para productos
    if ($.fn.select2) {
        $('.mv-select2-productos').select2({
            placeholder: '<?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?>',
            minimumInputLength: 2,
            ajax: {
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    console.log('Enviando búsqueda:', params.term);
                    return {
                        action: 'mv_buscar_productos',
                        busqueda: params.term,
                        almacen_id: $('#almacen_id').val() || 0,
                        nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
                    };
                },
                processResults: function(response) {
                    console.log('Respuesta completa:', response);
                    
                    if (response.success) {
                        if (response.data && response.data.productos) {
                            console.log('Productos encontrados:', response.data.productos.length);
                            console.log('Primer producto:', response.data.productos[0]);
                            
                            // Asegurar que cada producto tenga el formato correcto
                            var productos = response.data.productos.map(function(producto) {
                                return {
                                    id: producto.id || producto.producto_id,
                                    text: producto.text || producto.nombre || 'Producto sin nombre',
                                    nombre: producto.nombre,
                                    sku: producto.sku,
                                    precio: producto.precio,
                                    stock: producto.stock,
                                    en_stock: producto.en_stock !== false,
                                    gestion_stock: producto.gestion_stock,
                                    variacion_id: producto.variacion_id || 0
                                };
                            });
                            
                            return { results: productos };
                        } else {
                            console.log('No hay productos en la respuesta');
                            return {
                                results: [{
                                    id: 0,
                                    text: response.data.mensaje || '<?php _e('No se encontraron productos', 'modulo-ventas'); ?>',
                                    disabled: true
                                }]
                            };
                        }
                    } else {
                        console.error('Error en la respuesta:', response.data);
                        return {
                            results: [{
                                id: 0,
                                text: response.data ? response.data.message : '<?php _e('Error al buscar productos', 'modulo-ventas'); ?>',
                                disabled: true
                            }]
                        };
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('Error AJAX:', {
                        status: textStatus,
                        error: errorThrown,
                        response: xhr.responseText
                    });
                    
                    return {
                        results: [{
                            id: 0,
                            text: '<?php _e('Error de conexión', 'modulo-ventas'); ?>',
                            disabled: true
                        }]
                    };
                }
            },
            templateResult: formatProducto,
            templateSelection: function(producto) {
                if (producto.id && producto.id !== '0' && producto.id !== 0) {
                    console.log('Producto seleccionado:', producto);
                    
                    // Asegurar que el producto tenga todos los datos necesarios
                    var productoCompleto = {
                        id: producto.id,
                        producto_id: producto.id,
                        variacion_id: producto.variacion_id || 0,
                        nombre: producto.nombre || producto.text,
                        sku: producto.sku || '',
                        precio: producto.precio || 0,
                        stock: producto.stock || 0,
                        en_stock: producto.en_stock !== false
                    };
                    
                    agregarProducto(productoCompleto);
                }
                // Siempre retornar el placeholder
                return '<?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?>';
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
                    'SKU: ' + producto.sku + ' | ' +
                    '<span class="stock-status ' + stockClass + '">' + stockText + '</span> | ' +
                    '$' + Number(producto.precio).toLocaleString('es-CL') +
                '</div>' +
            '</div>'
        );
        
        return $producto;
    }

    // Agregar estos estilos CSS para mejorar la visualización
    var styles = `
    <style>
    .select2-results__option {
        padding: 8px 12px !important;
    }

    .mv-producto-result {
        line-height: 1.4;
    }

    .mv-producto-nombre {
        font-weight: 600;
        margin-bottom: 3px;
    }

    .mv-producto-meta {
        font-size: 12px;
        color: #666;
    }

    .mv-producto-meta .sku {
        color: #2271b1;
    }

    .mv-producto-meta .stock.in-stock {
        color: #46b450;
    }

    .mv-producto-meta .stock.out-of-stock {
        color: #dc3232;
    }

    .mv-producto-meta .precio {
        font-weight: 600;
        color: #23282d;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] .mv-producto-meta {
        color: #fff;
        opacity: 0.9;
    }

    .mv-producto-result.producto-agotado {
        opacity: 0.7;
        background-color: #f8f8f8;
    }

    .mv-producto-result.producto-agotado .mv-producto-nombre {
        color: #666;
    }

    .stock-status.out-of-stock {
        color: #e74c3c;
        font-weight: bold;
    }

    .stock-status.in-stock {
        color: #27ae60;
    }

    </style>
    `;

    // Agregar los estilos al head
    $('head').append(styles);
    
    // Mostrar información del cliente seleccionado
    $('#cliente_id').off('change').on('change', function() {
        var $selected = $(this).find('option:selected');
        
        console.log('Evento change disparado');
        console.log('Cliente seleccionado:', $selected.val());
        console.log('Datos disponibles:', {
            rut: $selected.attr('data-rut'),
            email: $selected.attr('data-email'),
            telefono: $selected.attr('data-telefono'),
            direccion: $selected.attr('data-direccion'),
            giro: $selected.attr('data-giro')
        });
        
        if ($selected.val()) {
            // Mostrar el contenedor
            $('#mv-cliente-info').show();
            
            // Actualizar cada campo
            $('#mv-cliente-info [data-field="rut"]').text($selected.attr('data-rut') || '-');
            $('#mv-cliente-info [data-field="email"]').text($selected.attr('data-email') || '-');
            $('#mv-cliente-info [data-field="telefono"]').text($selected.attr('data-telefono') || '-');
            $('#mv-cliente-info [data-field="direccion"]').text($selected.attr('data-direccion') || '-');
            $('#mv-cliente-info [data-field="giro"]').text($selected.attr('data-giro') || '-');
        } else {
            $('#mv-cliente-info').hide();
        }
    });

    // Si se está usando Select2, asegurarse de que preserve los atributos data
    if ($.fn.select2 && $('#cliente_id').hasClass('mv-select2-cliente')) {
        $('#cliente_id').select2({
            placeholder: '<?php _e('Seleccione un cliente', 'modulo-ventas'); ?>',
            allowClear: true,
            width: '100%',
            templateResult: formatCliente,
            templateSelection: formatCliente
        });
    }
    
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
        
        // Recopilar datos del formulario
        var datosCliente = {};
        $form.find('input').each(function() {
            var match = $(this).attr('name').match(/cliente\[(.+)\]/);
            if (match) {
                datosCliente[match[1]] = $(this).val();
            }
        });
        
        console.log('Datos a enviar:', datosCliente);
        
        $.post(ajaxurl, {
            action: 'mv_crear_cliente_rapido',
            cliente: datosCliente,
            nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
        }, function(response) {
            console.log('Respuesta del servidor:', response);
            
            if (response.success && response.data && response.data.cliente) {
                // Obtener el cliente de la respuesta
                var nuevoCliente = response.data.cliente;
                
                // Crear la nueva opción con jQuery directamente
                var $newOption = $('<option></option>')
                    .attr('value', nuevoCliente.id)
                    .text(nuevoCliente.razon_social + ' - ' + nuevoCliente.rut)
                    .attr('data-rut', nuevoCliente.rut || '')
                    .attr('data-email', nuevoCliente.email || '')
                    .attr('data-telefono', nuevoCliente.telefono || '')
                    .attr('data-direccion', nuevoCliente.direccion_facturacion || '')
                    .attr('data-giro', nuevoCliente.giro_comercial || '');
                
                // Agregar la opción al select
                $('#cliente_id').append($newOption);
                
                // Si el select usa Select2, actualizar
                if ($.fn.select2 && $('#cliente_id').hasClass('mv-select2-cliente')) {
                    // Destruir Select2 temporalmente
                    $('#cliente_id').select2('destroy');
                    
                    // Seleccionar la nueva opción
                    $('#cliente_id').val(nuevoCliente.id);
                    
                    // Re-inicializar Select2
                    $('#cliente_id').select2({
                        placeholder: '<?php _e('Seleccione un cliente', 'modulo-ventas'); ?>',
                        allowClear: true,
                        width: '100%'
                    });
                } else {
                    // Sin Select2, solo seleccionar
                    $('#cliente_id').val(nuevoCliente.id);
                }
                
                // Disparar el evento change
                $('#cliente_id').trigger('change');
                
                // Cerrar modal y limpiar formulario
                $('#mv-modal-nuevo-cliente').fadeOut();
                $form[0].reset();
                
                // Mostrar mensaje
                mvShowToast(response.data.message || '<?php _e('Cliente creado exitosamente', 'modulo-ventas'); ?>', 'success');
                
                // Log para depuración
                console.log('Cliente agregado al select:', {
                    id: nuevoCliente.id,
                    texto: nuevoCliente.razon_social + ' - ' + nuevoCliente.rut,
                    datos: {
                        rut: nuevoCliente.rut,
                        email: nuevoCliente.email,
                        telefono: nuevoCliente.telefono,
                        direccion: nuevoCliente.direccion_facturacion,
                        giro: nuevoCliente.giro_comercial
                    }
                });
            } else {
                var errorMsg = response.data && response.data.message ? response.data.message : '<?php _e('Error al crear el cliente', 'modulo-ventas'); ?>';
                alert(errorMsg);
            }
        }).fail(function(xhr, status, error) {
            console.error('Error AJAX:', error);
            alert('<?php _e('Error de conexión', 'modulo-ventas'); ?>');
        }).always(function() {
            $submit.prop('disabled', false).text('<?php _e('Crear Cliente', 'modulo-ventas'); ?>');
        });
    });
    
    // Agregar producto a la tabla
    function agregarProducto(producto) {
        // Verificar que el producto tenga los datos necesarios
        if (!producto || (!producto.id && producto.id !== 0)) {
            console.error('Producto inválido:', producto);
            return;
        }
        
        console.log('Agregando producto:', producto); // Debug
        
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
        
        // Asegurarse de que todos los campos tengan valores
        var datos = {
            index: productoIndex,
            producto_id: producto.id || 0,
            variacion_id: producto.variacion_id || 0,
            nombre: producto.nombre || producto.text || producto.name || '',
            sku: producto.sku || '',
            precio: producto.precio || 0,
            precio_original: producto.precio_regular || producto.precio || 0,
            opciones_almacen: opcionesAlmacen
        };
        
        console.log('Datos para el template:', datos); // Debug
        
        // Reemplazar variables en el template
        var html = template;
        for (var key in datos) {
            var regex = new RegExp('{{' + key + '}}', 'g');
            html = html.replace(regex, datos[key]);
        }
        
        // Agregar a la tabla
        $('#mv-productos-lista').append(html);
        
        // Si hay stock disponible, mostrarlo
        if (producto.stock !== undefined) {
            var $row = $('#mv-productos-lista tr:last');
            $row.find('.mv-stock-cantidad').text(producto.stock);
        }
        
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
        $(this).closest('tr').remove();
        
        // Si no quedan productos, mostrar mensaje
        if ($('#mv-productos-lista tr').length === 0) {
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
        
        // Envío
        var envio = parseFloat($('#costo_envio').val()) || 0;
        
        // Subtotal con envío
        var subtotalConEnvio = subtotalConDescuento + envio;
        
        // IVA (se calcula sobre el total incluyendo envío)
        var iva = incluyeIva ? subtotalConEnvio * 0.19 : 0;
        
        // Total
        var total = subtotalConEnvio + iva;
        
        // Actualizar UI
        $('#mv-subtotal').text(formatearPrecio(subtotal));
        $('#mv-descuento-total').text(formatearPrecio(descuentoGlobalMonto));
        $('#mv-subtotal-descuento').text(formatearPrecio(subtotalConDescuento));
        $('#mv-envio').text(formatearPrecio(envio));
        $('#mv-subtotal-con-envio').text(formatearPrecio(subtotalConEnvio));
        $('#mv-iva').text(formatearPrecio(iva));
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

    // Antes del submit, agregar campos hidden para cada producto
    $('#mv-form-cotizacion').on('submit', function(e) {
        // Primero, limpiar productos anteriores
        $(this).find('input[name^="producto_id_"]').remove();
        
        // Agregar campos hidden para cada producto en la tabla
        var index = 0;
        $('#mv-productos-lista .mv-producto-row').each(function() {
            var $row = $(this);
            var $form = $('#mv-form-cotizacion');
            
            // Producto ID
            $form.append('<input type="hidden" name="producto_id_' + index + '" value="' + $row.data('producto-id') + '">');
            
            // Variación ID
            $form.append('<input type="hidden" name="variacion_id_' + index + '" value="' + ($row.data('variacion-id') || 0) + '">');
            
            // SKU
            $form.append('<input type="hidden" name="sku_' + index + '" value="' + $row.find('.producto-sku').text() + '">');
            
            // Nombre
            $form.append('<input type="hidden" name="nombre_' + index + '" value="' + $row.find('.producto-nombre').text() + '">');
            
            // Cantidad
            $form.append('<input type="hidden" name="cantidad_' + index + '" value="' + $row.find('.producto-cantidad').val() + '">');
            
            // Precio
            $form.append('<input type="hidden" name="precio_' + index + '" value="' + $row.find('.producto-precio').val() + '">');
            
            // Almacén
            var almacenId = $row.find('.mv-select-almacen').val() || $('#almacen_id').val() || 0;
            $form.append('<input type="hidden" name="almacen_id_' + index + '" value="' + almacenId + '">');
            
            // Descuento
            var descuento = $row.find('.producto-descuento').val() || 0;
            $form.append('<input type="hidden" name="descuento_' + index + '" value="' + descuento + '">');
            
            // Subtotal
            var subtotal = parseFloat($row.find('.producto-subtotal').text().replace(/[^0-9.-]+/g, '')) || 0;
            $form.append('<input type="hidden" name="subtotal_' + index + '" value="' + subtotal + '">');
            
            index++;
        });
    
    });
    
    // Vista previa
    $('.mv-btn-preview').on('click', function() {
        alert('<?php _e('La vista previa estará disponible próximamente', 'modulo-ventas'); ?>');
    });
    
    // Validación del formulario
    $('#mv-form-cotizacion').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        console.log('=== ENVÍO DE COTIZACIÓN CON NONCE CORRECTO ===');
        
        var $form = $(this);
        var valido = true;
        var errores = [];
        
        // Validaciones
        if (!$('#cliente_id').val()) {
            valido = false;
            errores.push('Debe seleccionar un cliente');
            $('#cliente_id').addClass('mv-input-error');
        }
        
        if ($('#mv-productos-lista .mv-producto-row').length === 0) {
            valido = false;
            errores.push('Debe agregar al menos un producto');
        }
        
        if (!valido) {
            alert(errores.join('\n'));
            return false;
        }
        
        // Mostrar loading
        var $btnGuardar = $('#mv-btn-guardar');
        var textoOriginal = $btnGuardar.html();
        $btnGuardar.prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> Guardando...'
        );
        
        // Obtener el nonce del formulario
        var nonce = $('#mv_cotizacion_nonce').val();
        console.log('Nonce encontrado:', nonce);
        
        // Organizar datos en el formato esperado
        var datos_generales = {
            cliente_id: $('#cliente_id').val(),
            fecha: $('#fecha').val() || new Date().toISOString().split('T')[0],
            fecha_expiracion: $('#fecha_expiracion').val() || '',
            vendedor_id: $('#vendedor_id').val() || '',
            almacen_id: $('#almacen_id').val() || '',
            incluye_iva: $('#incluye_iva').is(':checked') ? 1 : 0,
            descuento_monto: $('#descuento_global').val() || 0,
            descuento_porcentaje: 0,
            tipo_descuento: 'monto',
            costo_envio: $('#costo_envio').val() || 0,
            observaciones: $('#observaciones').val() || '',
            notas_internas: $('#notas_internas').val() || '',
            terminos_condiciones: $('#terminos_condiciones').val() || '',
            plazo_pago: $('#plazo_pago').val() || '',
            condiciones_pago: $('#condiciones_pago').val() || ''
        };
        
        // Recopilar items
        var items = [];
        $('#mv-productos-lista .mv-producto-row').each(function(index) {
            var $row = $(this);
            items.push({
                producto_id: $row.find('input[name*="[producto_id]"]').val(),
                variacion_id: $row.find('input[name*="[variacion_id]"]').val() || 0,
                sku: $row.find('input[name*="[sku]"]').val(),
                nombre: $row.find('input[name*="[nombre]"]').val(),
                descripcion: $row.find('textarea[name*="[descripcion]"]').val() || '',
                cantidad: $row.find('input[name*="[cantidad]"]').val() || 1,
                precio_unitario: $row.find('input[name*="[precio_unitario]"]').val() || 0,
                descuento_tipo: $row.find('select[name*="[descuento_tipo]"]').val() || 'monto',
                descuento_valor: $row.find('input[name*="[descuento_valor]"]').val() || 0,
                subtotal: $row.find('input[name*="[subtotal]"]').val() || 0,
                almacen_id: $row.find('select[name*="[almacen_id]"]').val() || datos_generales.almacen_id
            });
        });
        
        console.log('Datos generales:', datos_generales);
        console.log('Items:', items);
        
        // IMPORTANTE: Usar el nombre correcto del nonce
        // El formulario tiene wp_nonce_field('mv_crear_cotizacion', 'mv_cotizacion_nonce')
        // Pero necesitamos enviarlo con el nombre que espera el handler
        var datosEnvio = {
            action: 'mv_guardar_cotizacion',
            nonce: nonce,  // El handler busca $_POST['nonce']
            _wpnonce: nonce,  // Por si acaso busca este nombre
            mv_cotizacion_nonce: nonce,  // El nombre del campo
            datos_generales: datos_generales,
            items: items
        };
        
        // Enviar por AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: datosEnvio,
            dataType: 'json',
            success: function(response) {
                console.log('Respuesta:', response);
                
                if (response.success) {
                    mvShowToast(response.data.message || 'Cotización creada exitosamente', 'success');
                    
                    setTimeout(function() {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else if (response.data.cotizacion_id) {
                            window.location.href = '<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id='); ?>' + response.data.cotizacion_id;
                        } else {
                            window.location.href = '<?php echo admin_url('admin.php?page=modulo-ventas-cotizaciones'); ?>';
                        }
                    }, 1500);
                } else {
                    console.error('Error:', response);
                    var mensaje = response.data && response.data.message ? response.data.message : 'Error al crear la cotización';
                    if (response.data && response.data.errors) {
                        mensaje += '\n\n' + response.data.errors.join('\n');
                    }
                    alert(mensaje);
                    mvShowToast(mensaje, 'error');
                    $btnGuardar.prop('disabled', false).html(textoOriginal);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                var errorMessage = 'Error de conexión';
                if (xhr.status === 403 || xhr.responseText === '-1' || xhr.responseText === '0') {
                    errorMessage = 'Error de seguridad. El nonce no coincide. Por favor, recarga la página.';
                }
                
                alert(errorMessage);
                mvShowToast(errorMessage, 'error');
                $btnGuardar.prop('disabled', false).html(textoOriginal);
            }
        });
        
        return false;
    });

    // Asegurar que el botón de guardar no tenga comportamiento por defecto
    $('#mv-btn-guardar').on('click', function(e) {
        if ($(this).attr('type') !== 'submit') {
            e.preventDefault();
            $('#mv-form-cotizacion').submit();
        }
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

    window.verificarNonces = function() {
        console.log('=== VERIFICACIÓN DE NONCES ===');
        
        var nonce = $('#mv_cotizacion_nonce').val();
        console.log('Nonce en el formulario:', nonce);
        
        // Probar con diferentes acciones de nonce
        var acciones = ['mv_crear_cotizacion', 'modulo_ventas_nonce', 'mv_guardar_cotizacion'];
        
        acciones.forEach(function(accion) {
            $.post(ajaxurl, {
                action: 'wp_ajax_nopriv_test',
                test_nonce: nonce,
                test_action: accion
            }, function(response) {
                console.log('Test nonce con acción "' + accion + '":', response);
            });
        });
    };

    
});
</script>