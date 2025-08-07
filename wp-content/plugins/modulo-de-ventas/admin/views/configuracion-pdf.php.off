<?php
/**
 * Vista de configuración para PDFs
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
    wp_die(__('No tienes permisos para acceder a esta página.', 'modulo-ventas'));
}

// Procesar formulario si se envió
if (isset($_POST['mv_pdf_config_nonce']) && wp_verify_nonce($_POST['mv_pdf_config_nonce'], 'mv_guardar_config_pdf')) {
    $config = array(
        // Información de la empresa
        'empresa_nombre' => sanitize_text_field($_POST['empresa_nombre']),
        'empresa_direccion' => sanitize_text_field($_POST['empresa_direccion']),
        'empresa_ciudad' => sanitize_text_field($_POST['empresa_ciudad']),
        'empresa_telefono' => sanitize_text_field($_POST['empresa_telefono']),
        'empresa_email' => sanitize_email($_POST['empresa_email']),
        'empresa_web' => esc_url_raw($_POST['empresa_web']),
        'empresa_rut' => sanitize_text_field($_POST['empresa_rut']),
        
        // Configuración del PDF
        'papel_tamano' => sanitize_text_field($_POST['papel_tamano']),
        'papel_orientacion' => sanitize_text_field($_POST['papel_orientacion']),
        'fuente_principal' => sanitize_text_field($_POST['fuente_principal']),
        'fuente_tamano' => intval($_POST['fuente_tamano']),
        'color_primario' => sanitize_hex_color($_POST['color_primario']),
        
        // Márgenes
        'margen_izquierdo' => intval($_POST['margen_izquierdo']),
        'margen_derecho' => intval($_POST['margen_derecho']),
        'margen_superior' => intval($_POST['margen_superior']),
        'margen_inferior' => intval($_POST['margen_inferior']),
        
        // Footer
        'mostrar_footer' => isset($_POST['mostrar_footer']),
        'footer_texto' => sanitize_textarea_field($_POST['footer_texto']),
        
        // Términos por defecto
        'terminos_default' => sanitize_textarea_field($_POST['terminos_default']),
        'nota_validez' => sanitize_text_field($_POST['nota_validez']),
        
        // Opciones avanzadas
        'incluir_logo' => isset($_POST['incluir_logo']),
        'logo_posicion' => sanitize_text_field($_POST['logo_posicion']),
        'mostrar_qr' => isset($_POST['mostrar_qr']),
        'marca_agua' => isset($_POST['marca_agua']),
        'marca_agua_texto' => sanitize_text_field($_POST['marca_agua_texto'])
    );
    
    // Manejar upload de logo
    if (!empty($_FILES['empresa_logo']['name'])) {
        $upload_result = mv_upload_logo($_FILES['empresa_logo']);
        if (!is_wp_error($upload_result)) {
            $config['empresa_logo'] = $upload_result['url'];
            $config['empresa_logo_path'] = $upload_result['file'];
        }
    }
    
    update_option('mv_pdf_config', $config);
    
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>' . __('Configuración guardada exitosamente.', 'modulo-ventas') . '</p>';
    echo '</div>';
}

// Obtener configuración actual
$config = get_option('mv_pdf_config', array());
$defaults = array(
    'empresa_nombre' => get_bloginfo('name'),
    'empresa_direccion' => get_option('woocommerce_store_address', ''),
    'empresa_ciudad' => get_option('woocommerce_store_city', ''),
    'empresa_telefono' => '',
    'empresa_email' => get_option('admin_email'),
    'empresa_web' => get_option('siteurl'),
    'empresa_rut' => '',
    'empresa_logo' => '',
    'papel_tamano' => 'A4',
    'papel_orientacion' => 'P',
    'fuente_principal' => 'helvetica',
    'fuente_tamano' => 10,
    'color_primario' => '#2271b1',
    'margen_izquierdo' => 15,
    'margen_derecho' => 15,
    'margen_superior' => 27,
    'margen_inferior' => 25,
    'mostrar_footer' => true,
    'footer_texto' => __('Documento generado por', 'modulo-ventas') . ' ' . get_bloginfo('name'),
    'terminos_default' => __("• Esta cotización tiene una validez de 30 días desde la fecha de emisión.\n• Los precios incluyen IVA y están sujetos a cambios sin previo aviso.\n• Para confirmar el pedido, se requiere una señal del 50% del valor total.\n• Los tiempos de entrega se confirmarán al momento de la orden de compra.", 'modulo-ventas'),
    'nota_validez' => __('30 días', 'modulo-ventas'),
    'incluir_logo' => true,
    'logo_posicion' => 'izquierda',
    'mostrar_qr' => false,
    'marca_agua' => false,
    'marca_agua_texto' => __('COTIZACIÓN', 'modulo-ventas')
);

$config = wp_parse_args($config, $defaults);
?>

<div class="wrap mv-configuracion-pdf">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Configuración de PDFs', 'modulo-ventas'); ?>
    </h1>
    
    <p class="description">
        <?php _e('Configure la apariencia y contenido de los PDFs generados para cotizaciones.', 'modulo-ventas'); ?>
    </p>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('mv_guardar_config_pdf', 'mv_pdf_config_nonce'); ?>
        
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <div id="post-body-content">
                    
                    <!-- Información de la Empresa -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Información de la Empresa', 'modulo-ventas'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_nombre"><?php _e('Nombre de la Empresa', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="empresa_nombre" id="empresa_nombre" 
                                               value="<?php echo esc_attr($config['empresa_nombre']); ?>" 
                                               class="regular-text" required>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_rut"><?php _e('RUT de la Empresa', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="empresa_rut" id="empresa_rut" 
                                               value="<?php echo esc_attr($config['empresa_rut']); ?>" 
                                               class="regular-text" placeholder="12.345.678-9">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_direccion"><?php _e('Dirección', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="empresa_direccion" id="empresa_direccion" 
                                               value="<?php echo esc_attr($config['empresa_direccion']); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_ciudad"><?php _e('Ciudad', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="empresa_ciudad" id="empresa_ciudad" 
                                               value="<?php echo esc_attr($config['empresa_ciudad']); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_telefono"><?php _e('Teléfono', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="empresa_telefono" id="empresa_telefono" 
                                               value="<?php echo esc_attr($config['empresa_telefono']); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_email"><?php _e('Email', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="email" name="empresa_email" id="empresa_email" 
                                               value="<?php echo esc_attr($config['empresa_email']); ?>" 
                                               class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_web"><?php _e('Sitio Web', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="url" name="empresa_web" id="empresa_web" 
                                               value="<?php echo esc_attr($config['empresa_web']); ?>" 
                                               class="regular-text" placeholder="https://">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="empresa_logo"><?php _e('Logo de la Empresa', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="file" name="empresa_logo" id="empresa_logo" 
                                               accept="image/*">
                                        <p class="description">
                                            <?php _e('Formatos soportados: JPG, PNG, GIF. Tamaño recomendado: 200x80px', 'modulo-ventas'); ?>
                                        </p>
                                        
                                        <?php if (!empty($config['empresa_logo'])): ?>
                                            <div class="mv-logo-preview">
                                                <img src="<?php echo esc_url($config['empresa_logo']); ?>" 
                                                     alt="Logo actual" style="max-width: 200px; max-height: 80px;">
                                                <p><small><?php _e('Logo actual', 'modulo-ventas'); ?></small></p>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Configuración del Documento -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Configuración del Documento', 'modulo-ventas'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="papel_tamano"><?php _e('Tamaño del Papel', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <select name="papel_tamano" id="papel_tamano">
                                            <option value="A4" <?php selected($config['papel_tamano'], 'A4'); ?>>A4</option>
                                            <option value="LETTER" <?php selected($config['papel_tamano'], 'LETTER'); ?>>Carta</option>
                                            <option value="LEGAL" <?php selected($config['papel_tamano'], 'LEGAL'); ?>>Legal</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="papel_orientacion"><?php _e('Orientación', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <select name="papel_orientacion" id="papel_orientacion">
                                            <option value="P" <?php selected($config['papel_orientacion'], 'P'); ?>><?php _e('Vertical', 'modulo-ventas'); ?></option>
                                            <option value="L" <?php selected($config['papel_orientacion'], 'L'); ?>><?php _e('Horizontal', 'modulo-ventas'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="fuente_principal"><?php _e('Fuente Principal', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <select name="fuente_principal" id="fuente_principal">
                                            <option value="helvetica" <?php selected($config['fuente_principal'], 'helvetica'); ?>>Helvetica</option>
                                            <option value="times" <?php selected($config['fuente_principal'], 'times'); ?>>Times</option>
                                            <option value="courier" <?php selected($config['fuente_principal'], 'courier'); ?>>Courier</option>
                                            <option value="dejavusans" <?php selected($config['fuente_principal'], 'dejavusans'); ?>>DejaVu Sans</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="fuente_tamano"><?php _e('Tamaño de Fuente', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="fuente_tamano" id="fuente_tamano" 
                                               value="<?php echo esc_attr($config['fuente_tamano']); ?>" 
                                               min="8" max="16" class="small-text"> px
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="color_primario"><?php _e('Color Primario', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="color" name="color_primario" id="color_primario" 
                                               value="<?php echo esc_attr($config['color_primario']); ?>">
                                        <p class="description"><?php _e('Color para títulos y elementos destacados', 'modulo-ventas'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Términos y Condiciones -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Términos y Condiciones por Defecto', 'modulo-ventas'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="nota_validez"><?php _e('Validez de Cotización', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="nota_validez" id="nota_validez" 
                                               value="<?php echo esc_attr($config['nota_validez']); ?>" 
                                               class="regular-text" placeholder="30 días">
                                        <p class="description"><?php _e('Tiempo de validez predeterminado para las cotizaciones', 'modulo-ventas'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="terminos_default"><?php _e('Términos por Defecto', 'modulo-ventas'); ?></label>
                                    </th>
                                    <td>
                                        <textarea name="terminos_default" id="terminos_default" 
                                                  rows="8" cols="50" class="large-text"><?php echo esc_textarea($config['terminos_default']); ?></textarea>
                                        <p class="description"><?php _e('Estos términos aparecerán cuando una cotización no tenga términos específicos', 'modulo-ventas'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- Sidebar -->
                <div id="postbox-container-1" class="postbox-container">
                    
                    <!-- Márgenes -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Márgenes (mm)', 'modulo-ventas'); ?></h2>
                        </div>
                        <div class="inside">
                            <p>
                                <label for="margen_superior"><?php _e('Superior:', 'modulo-ventas'); ?></label>
                                <input type="number" name="margen_superior" id="margen_superior" 
                                       value="<?php echo esc_attr($config['margen_superior']); ?>" 
                                       min="5" max="50" class="small-text">
                            </p>
                            
                            <p>
                                <label for="margen_inferior"><?php _e('Inferior:', 'modulo-ventas'); ?></label>
                                <input type="number" name="margen_inferior" id="margen_inferior" 
                                       value="<?php echo esc_attr($config['margen_inferior']); ?>" 
                                       min="5" max="50" class="small-text">
                            </p>
                            
                            <p>
                                <label for="margen_izquierdo"><?php _e('Izquierdo:', 'modulo-ventas'); ?></label>
                                <input type="number" name="margen_izquierdo" id="margen_izquierdo" 
                                       value="<?php echo esc_attr($config['margen_izquierdo']); ?>" 
                                       min="5" max="50" class="small-text">
                            </p>
                            
                            <p>
                                <label for="margen_derecho"><?php _e('Derecho:', 'modulo-ventas'); ?></label>
                                <input type="number" name="margen_derecho" id="margen_derecho" 
                                       value="<?php echo esc_attr($config['margen_derecho']); ?>" 
                                       min="5" max="50" class="small-text">
                            </p>
                        </div>
                    </div>

                    <!-- Opciones Avanzadas -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Opciones Avanzadas', 'modulo-ventas'); ?></h2>
                        </div>
                        <div class="inside">
                            <p>
                                <label>
                                    <input type="checkbox" name="incluir_logo" value="1" 
                                           <?php checked($config['incluir_logo']); ?>>
                                    <?php _e('Incluir logo en el PDF', 'modulo-ventas'); ?>
                                </label>
                            </p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="mostrar_footer" value="1" 
                                           <?php checked($config['mostrar_footer']); ?>>
                                    <?php _e('Mostrar pie de página', 'modulo-ventas'); ?>
                                </label>
                            </p>
                            
                            <p>
                                <label for="footer_texto"><?php _e('Texto del pie:', 'modulo-ventas'); ?></label>
                                <textarea name="footer_texto" id="footer_texto" 
                                          rows="2" class="widefat"><?php echo esc_textarea($config['footer_texto']); ?></textarea>
                            </p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="mostrar_qr" value="1" 
                                           <?php checked($config['mostrar_qr']); ?>>
                                    <?php _e('Incluir código QR', 'modulo-ventas'); ?>
                                </label>
                            </p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" name="marca_agua" value="1" 
                                           <?php checked($config['marca_agua']); ?>>
                                    <?php _e('Marca de agua', 'modulo-ventas'); ?>
                                </label>
                            </p>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php _e('Acciones', 'modulo-ventas'); ?></h2>
                        </div>
                        <div class="inside">
                            <p>
                                <button type="submit" class="button button-primary button-large">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php _e('Guardar Configuración', 'modulo-ventas'); ?>
                                </button>
                            </p>
                            
                            <p>
                                <button type="button" class="button button-secondary" id="mv-test-pdf">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Vista Previa de Prueba', 'modulo-ventas'); ?>
                                </button>
                            </p>
                            
                            <p>
                                <button type="button" class="button button-secondary" id="mv-restore-defaults">
                                    <span class="dashicons dashicons-undo"></span>
                                    <?php _e('Restaurar Valores por Defecto', 'modulo-ventas'); ?>
                                </button>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test PDF
    $('#mv-test-pdf').on('click', function() {
        alert('<?php _e('Función de vista previa en desarrollo', 'modulo-ventas'); ?>');
        // TODO: Implementar vista previa con datos de ejemplo
    });
    
    // Restaurar defaults
    $('#mv-restore-defaults').on('click', function() {
        if (confirm('<?php _e('¿Está seguro de que desea restaurar los valores por defecto?', 'modulo-ventas'); ?>')) {
            // TODO: Implementar restauración de defaults
            location.reload();
        }
    });
});
</script>

<style>
.mv-configuracion-pdf .form-table th {
    width: 200px;
}

.mv-logo-preview {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
    display: inline-block;
}

.mv-configuracion-pdf .postbox .inside {
    margin: 0;
}

.mv-configuracion-pdf .button-large {
    width: 100%;
    text-align: center;
}

#postbox-container-1 .postbox {
    margin-bottom: 20px;
}
</style>