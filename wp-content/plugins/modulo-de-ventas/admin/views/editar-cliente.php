<?php
/**
 * Vista para editar cliente
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Las variables $cliente, $regiones, $usuarios_disponibles y $estadisticas_cliente 
// vienen del controlador
?>

<div class="wrap mv-editar-cliente">
    <h1>
        <?php _e('Editar Cliente', 'modulo-ventas'); ?>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-clientes'); ?>" class="page-title-action">
            <?php _e('Volver al listado', 'modulo-ventas'); ?>
        </a>
        <?php if ($estadisticas_cliente['total_cotizaciones'] > 0): ?>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-ver-cliente&id=' . $cliente->id); ?>" class="page-title-action">
            <?php _e('Ver Detalle', 'modulo-ventas'); ?>
        </a>
        <?php endif; ?>
    </h1>
    
    <form method="post" id="mv-form-cliente" class="mv-form">
        <?php wp_nonce_field('mv_editar_cliente', 'mv_cliente_nonce'); ?>
        
        <div class="mv-form-container">
            <!-- Columna principal -->
            <div class="mv-form-main">
                
                <!-- Información básica -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Información Básica', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="razon_social" class="required">
                                        <?php _e('Razón Social', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="razon_social" id="razon_social" 
                                        value="<?php echo esc_attr($cliente->razon_social); ?>"
                                        class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="rut" class="required">
                                        <?php _e('RUT', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="rut" id="rut" 
                                        value="<?php echo esc_attr($cliente->rut); ?>"
                                        class="regular-text" required
                                        placeholder="12.345.678-9">
                                    <span class="description"><?php _e('Formato: 12.345.678-9', 'modulo-ventas'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="giro_comercial">
                                        <?php _e('Giro Comercial', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="giro_comercial" id="giro_comercial" 
                                        value="<?php echo esc_attr($cliente->giro_comercial); ?>"
                                        class="regular-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Información de contacto -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Información de Contacto', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="telefono">
                                        <?php _e('Teléfono', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="tel" name="telefono" id="telefono" 
                                        value="<?php echo esc_attr($cliente->telefono); ?>"
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="email">
                                        <?php _e('Email', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="email" name="email" id="email" 
                                        value="<?php echo esc_attr($cliente->email); ?>"
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sitio_web">
                                        <?php _e('Sitio Web', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="url" name="sitio_web" id="sitio_web" 
                                        value="<?php echo isset($cliente->sitio_web) ? esc_url($cliente->sitio_web) : ''; ?>"
                                        class="regular-text"
                                        placeholder="https://">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Dirección -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Dirección de Facturación', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="direccion_facturacion">
                                        <?php _e('Dirección', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea name="direccion_facturacion" id="direccion_facturacion" 
                                        rows="3" class="large-text"><?php echo esc_textarea($cliente->direccion_facturacion); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ciudad">
                                        <?php _e('Ciudad', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="ciudad" id="ciudad" 
                                        value="<?php echo isset($cliente->ciudad) ? esc_attr($cliente->ciudad) : ''; ?>"
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="region">
                                        <?php _e('Región', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select name="region" id="region" class="regular-text">
                                        <option value=""><?php _e('Seleccionar región', 'modulo-ventas'); ?></option>
                                        <?php foreach ($regiones as $codigo => $nombre): ?>
                                        <option value="<?php echo esc_attr($codigo); ?>" 
                                            <?php selected($cliente->region, $codigo); ?>>
                                            <?php echo esc_html($nombre); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="codigo_postal">
                                        <?php _e('Código Postal', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="codigo_postal" id="codigo_postal" 
                                        value="<?php echo isset($cliente->codigo_postal) ? esc_attr($cliente->codigo_postal) : ''; ?>"
                                        class="small-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Configuración -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Configuración', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="user_id">
                                        <?php _e('Usuario WordPress', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select name="user_id" id="user_id" class="regular-text">
                                        <option value=""><?php _e('Sin usuario asociado', 'modulo-ventas'); ?></option>
                                        <?php foreach ($usuarios_disponibles as $usuario): ?>
                                        <option value="<?php echo $usuario->ID; ?>" 
                                            <?php selected($cliente->user_id, $usuario->ID); ?>>
                                            <?php echo esc_html($usuario->display_name); ?> 
                                            (<?php echo esc_html($usuario->user_email); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php _e('Asociar con un usuario permite al cliente acceder a sus cotizaciones.', 'modulo-ventas'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="credito_autorizado">
                                        <?php _e('Crédito Autorizado', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" name="credito_autorizado" id="credito_autorizado" 
                                        value="<?php echo isset($cliente->credito_autorizado) ? esc_attr($cliente->credito_autorizado) : '0'; ?>"
                                        class="regular-text" min="0" step="0.01">
                                    <p class="description">
                                        <?php _e('Monto máximo de crédito autorizado para este cliente.', 'modulo-ventas'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="estado">
                                        <?php _e('Estado', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select name="estado" id="estado" class="regular-text">
                                        <option value="activo" <?php selected($cliente->estado, 'activo'); ?>>
                                            <?php _e('Activo', 'modulo-ventas'); ?>
                                        </option>
                                        <option value="inactivo" <?php selected($cliente->estado, 'inactivo'); ?>>
                                            <?php _e('Inactivo', 'modulo-ventas'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
            </div>
            
            <!-- Columna lateral -->
            <div class="mv-form-sidebar">
                
                <!-- Acciones -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Acciones', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <div class="submitbox">
                            <div id="major-publishing-actions">
                                <div id="delete-action">
                                    <?php if ($estadisticas_cliente['total_cotizaciones'] == 0): ?>
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=modulo-ventas-clientes&accion=eliminar&id=' . $cliente->id),
                                        'delete_cliente_' . $cliente->id
                                    ); ?>" class="submitdelete deletion" 
                                        onclick="return confirm('<?php esc_attr_e('¿Está seguro de eliminar este cliente?', 'modulo-ventas'); ?>')">
                                        <?php _e('Eliminar Cliente', 'modulo-ventas'); ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="mv-no-delete">
                                        <?php _e('No se puede eliminar (tiene cotizaciones)', 'modulo-ventas'); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div id="publishing-action">
                                    <input type="submit" name="submit" id="submit" 
                                        class="button button-primary button-large" 
                                        value="<?php esc_attr_e('Actualizar Cliente', 'modulo-ventas'); ?>">
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Información -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Información', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <p>
                            <strong><?php _e('Creado:', 'modulo-ventas'); ?></strong><br>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                                strtotime($cliente->fecha_creacion)); ?>
                        </p>
                        <?php if (isset($cliente->fecha_actualizacion) && $cliente->fecha_actualizacion): ?>
                        <p>
                            <strong><?php _e('Última actualización:', 'modulo-ventas'); ?></strong><br>
                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                                strtotime($cliente->fecha_actualizacion)); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Estadísticas', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <ul class="mv-stats-list">
                            <li>
                                <span class="dashicons dashicons-media-document"></span>
                                <strong><?php echo number_format($estadisticas_cliente['total_cotizaciones'], 0, ',', '.'); ?></strong>
                                <?php _e('Cotizaciones totales', 'modulo-ventas'); ?>
                            </li>
                            <li>
                                <span class="dashicons dashicons-clock"></span>
                                <strong><?php echo number_format($estadisticas_cliente['cotizaciones_pendientes'], 0, ',', '.'); ?></strong>
                                <?php _e('Pendientes', 'modulo-ventas'); ?>
                            </li>
                            <li>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <strong><?php echo number_format($estadisticas_cliente['cotizaciones_aprobadas'], 0, ',', '.'); ?></strong>
                                <?php _e('Aprobadas', 'modulo-ventas'); ?>
                            </li>
                            <li>
                                <span class="dashicons dashicons-cart"></span>
                                <strong><?php echo number_format($estadisticas_cliente['cotizaciones_convertidas'], 0, ',', '.'); ?></strong>
                                <?php _e('Convertidas', 'modulo-ventas'); ?>
                            </li>
                        </ul>
                        
                        <hr>
                        
                        <p>
                            <strong><?php _e('Total cotizado:', 'modulo-ventas'); ?></strong><br>
                            <?php echo wc_price($estadisticas_cliente['monto_total_cotizado']); ?>
                        </p>
                        
                        <p>
                            <strong><?php _e('Total aprobado:', 'modulo-ventas'); ?></strong><br>
                            <?php echo wc_price($estadisticas_cliente['monto_total_aprobado']); ?>
                        </p>
                        
                        <?php if ($estadisticas_cliente['ultima_cotizacion']): ?>
                        <hr>
                        <p>
                            <strong><?php _e('Última cotización:', 'modulo-ventas'); ?></strong><br>
                            <?php echo esc_html($estadisticas_cliente['ultima_cotizacion']->folio); ?><br>
                            <small><?php echo date_i18n(get_option('date_format'), 
                                strtotime($estadisticas_cliente['ultima_cotizacion']->fecha)); ?></small>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Acciones rápidas -->
                <?php if ($estadisticas_cliente['total_cotizaciones'] > 0): ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Acciones Rápidas', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=modulo-ventas-nueva-cotizacion&cliente_id=' . $cliente->id); ?>" 
                               class="button button-secondary">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php _e('Nueva Cotización', 'modulo-ventas'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=modulo-ventas-cotizaciones&cliente_id=' . $cliente->id); ?>" 
                               class="button button-secondary">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php _e('Ver Cotizaciones', 'modulo-ventas'); ?>
                            </a>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </form>
</div>

<style>
/* Layout del formulario */
.mv-form-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.mv-form-main {
    flex: 1;
    min-width: 0;
}

.mv-form-sidebar {
    width: 300px;
    flex-shrink: 0;
}

/* Campos requeridos */
.form-table label.required::after {
    content: ' *';
    color: #dc3232;
}

/* Estadísticas */
.mv-stats-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.mv-stats-list li {
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mv-stats-list .dashicons {
    color: #666;
}

/* No eliminar */
.mv-no-delete {
    color: #666;
    font-style: italic;
    font-size: 13px;
}

/* Responsive */
@media screen and (max-width: 1200px) {
    .mv-form-container {
        flex-direction: column;
    }
    
    .mv-form-sidebar {
        width: 100%;
    }
}

/* Estilo de submitbox similar a WordPress */
.submitbox {
    padding: 10px;
    clear: both;
    border-top: 1px solid #ddd;
    background: #fcfcfc;
}

.submitbox #major-publishing-actions {
    padding: 10px 0;
    clear: both;
}

.submitbox .deletion {
    color: #a00;
    text-decoration: none;
}

.submitbox .deletion:hover {
    color: #dc3232;
    border: none;
}

#delete-action {
    float: left;
    line-height: 2.30769231;
}

#publishing-action {
    float: right;
    line-height: 1.9;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Formatear RUT mientras se escribe
    $('#rut').on('input', function() {
        var rut = $(this).val().replace(/[^0-9kK]/g, '');
        var formatted = '';
        
        if (rut.length > 1) {
            // Agregar puntos cada 3 dígitos desde el final
            var digits = rut.slice(0, -1);
            var dv = rut.slice(-1);
            
            // Formatear los dígitos
            var groups = [];
            while (digits.length > 3) {
                groups.unshift(digits.slice(-3));
                digits = digits.slice(0, -3);
            }
            if (digits.length > 0) {
                groups.unshift(digits);
            }
            
            formatted = groups.join('.') + '-' + dv.toUpperCase();
        } else {
            formatted = rut;
        }
        
        $(this).val(formatted);
    });
    
    // Validación del formulario
    $('#mv-form-cliente').on('submit', function(e) {
        var valido = true;
        var errores = [];
        
        // Validar campos requeridos
        $(this).find('[required]').each(function() {
            if (!$(this).val().trim()) {
                valido = false;
                $(this).addClass('error');
                var label = $(this).closest('tr').find('label').text().replace(' *', '');
                errores.push(label + ' es obligatorio');
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Validar email si se proporciona
        var email = $('#email').val();
        if (email && !isValidEmail(email)) {
            valido = false;
            $('#email').addClass('error');
            errores.push('El formato del email no es válido');
        }
        
        // Validar URL si se proporciona
        var url = $('#sitio_web').val();
        if (url && !isValidUrl(url)) {
            valido = false;
            $('#sitio_web').addClass('error');
            errores.push('El formato de la URL no es válido');
        }
        
        if (!valido) {
            e.preventDefault();
            alert('Por favor, corrija los siguientes errores:\n\n' + errores.join('\n'));
            return false;
        }
    });
    
    // Funciones de validación
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch (_) {
            return false;
        }
    }
});
</script>