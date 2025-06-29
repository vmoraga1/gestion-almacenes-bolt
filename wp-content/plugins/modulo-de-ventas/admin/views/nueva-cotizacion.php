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
        <?php wp_nonce_field('mv_crear_cotizacion', 'mv_cotizacion_nonce'); ?>
        
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

<!-- Modal Nuevo Cliente con Validación de RUT -->
<div id="mv-modal-nuevo-cliente" class="mv-modal" style="display: none;">
    <div class="mv-modal-content">
        <div class="mv-modal-header">
            <h3><?php _e('Nuevo Cliente Rápido', 'modulo-ventas'); ?></h3>
            <span class="mv-modal-close">&times;</span>
        </div>
        
        <form id="mv-form-nuevo-cliente" class="mv-form">
            <div class="mv-modal-body">
                <div class="mv-form-row">
                    <div class="mv-form-col">
                        <label for="cliente_razon_social">
                            <?php _e('Razón Social', 'modulo-ventas'); ?> 
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                            name="cliente[razon_social]" 
                            id="cliente_razon_social" 
                            class="mv-input" 
                            required>
                    </div>
                    
                    <div class="mv-form-col">
                        <label for="cliente_rut">
                            <?php _e('RUT', 'modulo-ventas'); ?> 
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                            name="cliente[rut]" 
                            id="cliente_rut" 
                            class="mv-input" 
                            placeholder="12.345.678-9"
                            required>
                        <span class="mv-field-description">
                            <?php _e('Se validará automáticamente', 'modulo-ventas'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="mv-form-row">
                    <div class="mv-form-col">
                        <label for="cliente_email">
                            <?php _e('Email', 'modulo-ventas'); ?>
                        </label>
                        <input type="email" 
                            name="cliente[email]" 
                            id="cliente_email" 
                            class="mv-input">
                    </div>
                    
                    <div class="mv-form-col">
                        <label for="cliente_telefono">
                            <?php _e('Teléfono', 'modulo-ventas'); ?>
                        </label>
                        <input type="tel" 
                            name="cliente[telefono]" 
                            id="cliente_telefono" 
                            class="mv-input">
                    </div>
                </div>
                
                <div class="mv-form-row">
                    <div class="mv-form-col-full">
                        <label for="cliente_giro">
                            <?php _e('Giro Comercial', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            name="cliente[giro_comercial]" 
                            id="cliente_giro" 
                            class="mv-input">
                    </div>
                </div>
                
                <div class="mv-form-row">
                    <div class="mv-form-col-full">
                        <label for="cliente_direccion">
                            <?php _e('Dirección', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            name="cliente[direccion_facturacion]" 
                            id="cliente_direccion" 
                            class="mv-input">
                    </div>
                </div>
                
                <div class="mv-form-row">
                    <div class="mv-form-col">
                        <label for="cliente_comuna">
                            <?php _e('Comuna', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            name="cliente[comuna_facturacion]" 
                            id="cliente_comuna" 
                            class="mv-input">
                    </div>
                    
                    <div class="mv-form-col">
                        <label for="cliente_ciudad">
                            <?php _e('Ciudad', 'modulo-ventas'); ?>
                        </label>
                        <input type="text" 
                            name="cliente[ciudad_facturacion]" 
                            id="cliente_ciudad" 
                            class="mv-input">
                    </div>
                    
                    <div class="mv-form-col">
                        <label for="cliente_region">
                            <?php _e('Región', 'modulo-ventas'); ?>
                        </label>
                        <select name="cliente[region_facturacion]" 
                                id="cliente_region" 
                                class="mv-input">
                            <option value=""><?php _e('Seleccione...', 'modulo-ventas'); ?></option>
                            <?php 
                            $regiones = mv_get_regiones_chile();
                            foreach ($regiones as $codigo => $nombre) : ?>
                                <option value="<?php echo esc_attr($codigo); ?>">
                                    <?php echo esc_html($nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mv-modal-footer">
                <button type="button" class="button mv-modal-cancel">
                    <?php _e('Cancelar', 'modulo-ventas'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Crear Cliente', 'modulo-ventas'); ?>
                </button>
            </div>
        </form>
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

/* Estilos del modal */
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
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.mv-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mv-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.mv-modal-close {
    font-size: 28px;
    font-weight: bold;
    line-height: 20px;
    cursor: pointer;
    color: #999;
    transition: color 0.2s;
}

.mv-modal-close:hover {
    color: #333;
}

.mv-modal-body {
    padding: 20px;
}

.mv-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.mv-modal-footer button {
    margin-left: 10px;
}

.mv-form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.mv-form-col {
    flex: 1;
}

.mv-form-col-full {
    width: 100%;
}

.mv-form label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
}

.mv-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.mv-input:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.mv-input.error {
    border-color: #d63638;
}

.mv-input.error:focus {
    border-color: #d63638;
    box-shadow: 0 0 0 1px #d63638;
}

.required {
    color: #d63638;
}

.mv-field-description {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #666;
    font-style: italic;
}

.mv-error-message {
    display: block;
    margin-top: 5px;
    color: #d63638;
    font-size: 12px;
}

.mv-success-message {
    display: block;
    margin-top: 5px;
    color: #00a32a;
    font-size: 12px;
}

/* Animación de carga */
.mv-loading {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #2271b1;
    border-radius: 50%;
    animation: mv-spin 1s linear infinite;
    margin-left: 5px;
}

@keyframes mv-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 600px) {
    .mv-modal-content {
        width: 95%;
        margin: 10px;
    }
    
    .mv-form-row {
        flex-direction: column;
    }
    
    .mv-form-col {
        width: 100%;
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
</style>

<script type="text/javascript">
// Esperar a que jQuery esté listo
jQuery(document).ready(function($) {
    // Variables globales
    var productoIndex = 0;
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
            placeholder: '<?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?>',
            minimumInputLength: 2,
            ajax: {
                url: ajaxurl,
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
                    if (data.data) {
                    }
                    
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
                error: function(xhr, textStatus, errorThrown) {
                },
                cache: false
            },
            templateResult: formatProducto,
            templateSelection: function(producto) {
                if (producto.id && producto.id !== '0') {
                    agregarProducto(producto);
                    return '<?php _e('Buscar productos por Nombre o SKU', 'modulo-ventas'); ?>';
                }

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
        
        console.log('Enviando formulario de nuevo cliente...');
        
        $submit.prop('disabled', true).text('<?php _e('Creando...', 'modulo-ventas'); ?>');
        
        // Recopilar datos del formulario
        var datosCliente = {};
        $form.find('input, select').each(function() {
            var name = $(this).attr('name');
            if (name) {
                var match = name.match(/cliente\[(.+)\]/);
                if (match) {
                    datosCliente[match[1]] = $(this).val();
                }
            }
        });
        
        // Agregar el nonce si no está en los datos
        var nonce = $form.find('input[name="nonce"]').val() || moduloVentasAjax.nonce;
        
        console.log('Datos a enviar:', {
            action: 'mv_crear_cliente_rapido',
            cliente: datosCliente,
            nonce: nonce
        });
        
        $.ajax({
            url: ajaxurl || moduloVentasAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_crear_cliente_rapido',
                cliente: datosCliente,
                nonce: nonce
            },
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                
                if (response.success) {
                    // La respuesta exitosa tiene la estructura: response.data.cliente y response.data.message
                    var nuevoCliente = response.data.cliente;
                    
                    // Crear nueva opción para el select
                    var optionText = nuevoCliente.razon_social + ' - ' + nuevoCliente.rut;
                    var option = new Option(optionText, nuevoCliente.id, true, true);
                    
                    // Agregar atributos de datos
                    $(option).attr({
                        'data-rut': nuevoCliente.rut || '',
                        'data-email': nuevoCliente.email || '',
                        'data-telefono': nuevoCliente.telefono || '',
                        'data-direccion': nuevoCliente.direccion || '',
                        'data-giro': nuevoCliente.giro || ''
                    });
                    
                    // Agregar al select y seleccionar
                    $('#cliente_id').append(option).trigger('change');
                    
                    // Cerrar modal
                    $('#mv-modal-nuevo-cliente').fadeOut();
                    
                    // Limpiar formulario
                    $form[0].reset();
                    
                    // Mostrar mensaje de éxito
                    var mensaje = response.data.message || '<?php _e('Cliente creado exitosamente', 'modulo-ventas'); ?>';
                    
                    // Si existe la función mvShowToast, usarla
                    if (typeof mvShowToast === 'function') {
                        mvShowToast(mensaje, 'success');
                    } else {
                        // Alternativa: mostrar alerta o crear notificación simple
                        var $notice = $('<div class="notice notice-success is-dismissible"><p>' + mensaje + '</p></div>');
                        $('.wrap > h1').after($notice);
                        setTimeout(function() {
                            $notice.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    }
                    
                    console.log('Cliente creado exitosamente:', nuevoCliente);
                    
                } else {
                    // Error
                    alert(response.data.message || '<?php _e('Error al crear el cliente', 'modulo-ventas'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    responseJSON: xhr.responseJSON
                });
                
                var errorMsg = '<?php _e('Error de conexión', 'modulo-ventas'); ?>';
                
                // Intentar obtener mensaje de error más específico
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                } else if (xhr.responseText) {
                    console.log('Respuesta del servidor:', xhr.responseText);
                    errorMsg += '. Revise la consola para más detalles.';
                }
                
                alert(errorMsg);
            },
            complete: function() {
                $submit.prop('disabled', false).text('<?php _e('Crear Cliente', 'modulo-ventas'); ?>');
            }
        });
    });

    // Función para mostrar toast si no existe
    if (typeof mvShowToast === 'undefined') {
        window.mvShowToast = function(message, type) {
            type = type || 'info';
            var $toast = $('<div class="notice notice-' + type + ' is-dismissible mv-toast" style="position: fixed; top: 50px; right: 20px; z-index: 9999; min-width: 300px;">' +
                        '<p>' + message + '</p>' +
                        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Descartar</span></button>' +
                        '</div>');
            
            $('body').append($toast);
            
            // Auto-ocultar después de 3 segundos
            setTimeout(function() {
                $toast.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
            
            // Botón de cerrar
            $toast.find('.notice-dismiss').on('click', function() {
                $toast.fadeOut(function() {
                    $(this).remove();
                });
            });
        };
    }
    
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
    $('#mv-form-cotizacion').on('submit', function(e) {
        var valido = true;
        var errores = [];
        
        // Validar cliente
        if (!$('#cliente_id').val()) {
            valido = false;
            errores.push('<?php _e('Debe seleccionar un cliente', 'modulo-ventas'); ?>');
            $('#cliente_id').addClass('mv-input-error');
        }
        
        // Validar productos
        if ($('#mv-productos-lista .mv-producto-row').length === 0) {
            valido = false;
            errores.push('<?php _e('Debe agregar al menos un producto', 'modulo-ventas'); ?>');
        }
        
        if (!valido) {
            e.preventDefault();
            alert(errores.join('\n'));
            return false;
        }
        
        // Deshabilitar botón de envío
        $('#mv-btn-guardar').prop('disabled', true).html(
            '<span class="dashicons dashicons-update spin"></span> <?php _e('Guardando...', 'modulo-ventas'); ?>'
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

    // CÓDIGO DE DEPURACIÓN - ELIMINAR DESPUÉS DE RESOLVER EL PROBLEMA
    // Interceptar el envío del formulario para depuración
    $('#mv-form-cotizacion').on('submit', function(e) {
        console.log('=== DEPURACIÓN DE ENVÍO DE FORMULARIO ===');
        
        // Obtener todos los campos del formulario
        var formData = $(this).serializeArray();
        console.log('Datos del formulario:', formData);
        
        // Mostrar específicamente los items
        var items = {};
        formData.forEach(function(field) {
            if (field.name.startsWith('items[')) {
                if (!items[field.name]) {
                    items[field.name] = field.value;
                }
            }
        });
        console.log('Items encontrados:', items);
        
        // Verificar campos específicos
        console.log('=== Verificación de campos de productos ===');
        $('#mv-productos-lista .mv-producto-row').each(function(index) {
            var $row = $(this);
            console.log('Producto ' + index + ':');
            console.log('  - producto_id:', $row.find('input[name*="[producto_id]"]').val());
            console.log('  - nombre (hidden):', $row.find('input[name*="[nombre]"]').val());
            console.log('  - nombre (visible):', $row.find('.mv-producto-nombre').text());
            console.log('  - sku:', $row.find('input[name*="[sku]"]').val());
            console.log('  - precio:', $row.find('input[name*="[precio_unitario]"]').val());
        });
        
        // No cancelar el envío, solo depurar
        // e.preventDefault(); // Descomentar para evitar el envío y revisar la consola
    });
});
</script>