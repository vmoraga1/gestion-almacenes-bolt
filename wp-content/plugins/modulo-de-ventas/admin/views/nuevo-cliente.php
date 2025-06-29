<?php
/**
 * Vista para crear nuevo cliente
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
if (!current_user_can('manage_clientes_ventas')) {
    wp_die(__('No tienes permisos para ver esta página.', 'modulo-ventas'));
}
?>

<div class="wrap mv-nuevo-cliente">
    <h1>
        <?php _e('Nuevo Cliente', 'modulo-ventas'); ?>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-clientes'); ?>" class="page-title-action">
            <?php _e('Volver al listado', 'modulo-ventas'); ?>
        </a>
    </h1>
    
    <form method="post" id="mv-form-cliente" class="mv-form">
        <?php wp_nonce_field('mv_crear_cliente', 'mv_cliente_nonce'); ?>
        
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
                                    <label for="email">
                                        <?php _e('Email', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="email" name="email" id="email" 
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="telefono">
                                        <?php _e('Teléfono', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="tel" name="telefono" id="telefono" 
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="email_dte">
                                        <?php _e('Email DTE', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="email" name="email_dte" id="email_dte" 
                                        class="regular-text">
                                    <span class="description"><?php _e('Email para recepción de documentos tributarios', 'modulo-ventas'); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Dirección de facturación -->
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
                                    <input type="text" name="direccion_facturacion" id="direccion_facturacion" 
                                        class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="comuna_facturacion">
                                        <?php _e('Comuna', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="comuna_facturacion" id="comuna_facturacion" 
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ciudad_facturacion">
                                        <?php _e('Ciudad', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="ciudad_facturacion" id="ciudad_facturacion" 
                                        class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="region_facturacion">
                                        <?php _e('Región', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select name="region_facturacion" id="region_facturacion" class="regular-text">
                                        <option value=""><?php _e('Seleccione una región', 'modulo-ventas'); ?></option>
                                        <?php if (isset($regiones)) : ?>
                                            <?php foreach ($regiones as $region) : ?>
                                                <option value="<?php echo esc_attr($region); ?>">
                                                    <?php echo esc_html($region); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="pais_facturacion">
                                        <?php _e('País', 'modulo-ventas'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="pais_facturacion" id="pais_facturacion" 
                                        class="regular-text" value="Chile">
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
                            <div id="minor-publishing-actions">
                                <div class="misc-pub-section">
                                    <label>
                                        <input type="checkbox" name="estado" value="activo" checked>
                                        <?php _e('Cliente activo', 'modulo-ventas'); ?>
                                    </label>
                                </div>
                            </div>
                            <div id="major-publishing-actions">
                                <div id="publishing-action">
                                    <span class="spinner"></span>
                                    <input type="submit" name="submit" id="submit" 
                                        class="button button-primary button-large" 
                                        value="<?php _e('Crear Cliente', 'modulo-ventas'); ?>">
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Usuario de WordPress -->
                <?php if (isset($usuarios_disponibles) && !empty($usuarios_disponibles)) : ?>
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Vincular con Usuario', 'modulo-ventas'); ?></h2>
                    <div class="inside">
                        <p class="description">
                            <?php _e('Opcionalmente, puedes vincular este cliente con un usuario de WordPress existente.', 'modulo-ventas'); ?>
                        </p>
                        <select name="user_id" id="user_id" class="widefat">
                            <option value=""><?php _e('Sin vincular', 'modulo-ventas'); ?></option>
                            <?php foreach ($usuarios_disponibles as $user) : ?>
                                <option value="<?php echo $user->ID; ?>">
                                    <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </form>
</div>

<style>
/* Estilos del formulario */
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
}

.required:after {
    content: " *";
    color: #d63638;
}

@media screen and (max-width: 1200px) {
    .mv-form-container {
        flex-direction: column;
    }
    
    .mv-form-sidebar {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Asegurar que el formulario tenga el nonce
    $('#mv-form-nuevo-cliente').each(function() {
        if (!$(this).find('input[name="nonce"]').length) {
            $(this).append('<input type="hidden" name="nonce" value="' + moduloVentasAjax.nonce + '" />');
        }
    });
    
    // Debug: verificar datos del formulario antes de enviar
    $(document).on('submit', '#mv-form-nuevo-cliente', function(e) {
        console.log('Datos del formulario:', $(this).serializeArray());
    });

    // Formatear RUT mientras se escribe
    $('#rut').on('blur', function() {
        var rut = $(this).val();
        // Aquí podrías agregar la lógica para formatear el RUT
    });
    
    // Validación del formulario
    $('#mv-form-cliente').on('submit', function(e) {
        var razonSocial = $('#razon_social').val();
        var rut = $('#rut').val();
        
        if (!razonSocial || !rut) {
            e.preventDefault();
            alert('<?php _e('Por favor complete los campos obligatorios', 'modulo-ventas'); ?>');
            return false;
        }
    });
});
</script>