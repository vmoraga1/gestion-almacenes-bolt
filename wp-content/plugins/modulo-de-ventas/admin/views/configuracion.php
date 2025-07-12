<?php
/**
 * Vista de configuración del Módulo de Ventas
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir tab activa
$tab_activa = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Obtener configuración actual
$config = mv_get_configuracion_completa();

// Inicializar variables de mensajes
$mensaje_guardado = '';
$error_message = '';

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mv_config_nonce'])) {
    if (!wp_verify_nonce($_POST['mv_config_nonce'], 'mv_guardar_configuracion')) {
        $error_message = __('Error de seguridad. Por favor, intenta de nuevo.', 'modulo-ventas');
    } else {
        if (function_exists('mv_procesar_configuracion')) {
            $resultado = mv_procesar_configuracion($_POST);
            if (is_array($resultado) && isset($resultado['success']) && $resultado['success']) {
                $mensaje_guardado = __('Configuración guardada exitosamente.', 'modulo-ventas');
                // Recargar configuración
                $config = mv_get_configuracion_completa();
            } else {
                $error_message = isset($resultado['message']) ? $resultado['message'] : 'Error al guardar la configuración.';
            }
        } else {
            $error_message = 'Función mv_procesar_configuracion no disponible.';
        }
    }
}

// Obtener almacenes si están disponibles
$almacenes = array();
if (function_exists('mv_get_almacenes')) {
    try {
        $almacenes = mv_get_almacenes(false);
    } catch (Exception $e) {
        // Ignorar errores de almacenes
    }
}

?>

<div class="wrap mv-configuracion">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Configuración del Módulo de Ventas', 'modulo-ventas'); ?>
    </h1>

    <?php if ($mensaje_guardado) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($mensaje_guardado); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_message) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Navegación por tabs -->
    <nav class="nav-tab-wrapper">
        <a href="?page=modulo-ventas-configuracion&tab=general" 
        class="nav-tab <?php echo $tab_activa === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php _e('General', 'modulo-ventas'); ?>
        </a>
        <a href="?page=modulo-ventas-configuracion&tab=stock" 
        class="nav-tab <?php echo $tab_activa === 'stock' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-products"></span>
            <?php _e('Stock y Almacenes', 'modulo-ventas'); ?>
        </a>
        <a href="?page=modulo-ventas-configuracion&tab=emails" 
        class="nav-tab <?php echo $tab_activa === 'emails' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-email"></span>
            <?php _e('Emails y Notificaciones', 'modulo-ventas'); ?>
        </a>
        <a href="?page=modulo-ventas-configuracion&tab=integracion" 
        class="nav-tab <?php echo $tab_activa === 'integracion' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('Integración', 'modulo-ventas'); ?>
        </a>
        <a href="?page=modulo-ventas-configuracion&tab=pdf" 
        class="nav-tab <?php echo $tab_activa === 'pdf' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-media-document"></span>
            <?php _e('PDF y Documentos', 'modulo-ventas'); ?>
        </a>
        <a href="?page=modulo-ventas-configuracion&tab=avanzado" 
        class="nav-tab <?php echo $tab_activa === 'avanzado' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php _e('Avanzado', 'modulo-ventas'); ?>
        </a>
    </nav>

    <form method="post" action="" enctype="multipart/form-data" class="mv-config-form">
        <?php wp_nonce_field('mv_guardar_configuracion', 'mv_config_nonce'); ?>
        
        <div class="mv-configuracion mv-config-tab-content">
        <?php if ($tab_activa === 'general') : ?>
            <!-- TAB GENERAL -->
            <div class="mv-tab-panel">
                <h2><?php _e('Configuración General', 'modulo-ventas'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="prefijo_cotizacion"><?php _e('Prefijo de Cotizaciones', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                name="modulo_ventas_prefijo_cotizacion" 
                                id="prefijo_cotizacion" 
                                value="<?php echo esc_attr($config['prefijo_cotizacion']); ?>" 
                                class="regular-text" 
                                maxlength="10" />
                            <p class="description">
                                <?php _e('Prefijo que se usará para numerar las cotizaciones. Ej: COT, QUOTE, etc.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="dias_expiracion"><?php _e('Días de Expiración por Defecto', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                name="modulo_ventas_dias_expiracion" 
                                id="dias_expiracion" 
                                value="<?php echo esc_attr($config['dias_expiracion']); ?>" 
                                class="small-text" 
                                min="1" 
                                max="365" />
                            <span><?php _e('días', 'modulo-ventas'); ?></span>
                            <p class="description">
                                <?php _e('Número de días por defecto para la expiración de nuevas cotizaciones.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="numeracion_tipo"><?php _e('Tipo de Numeración', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" 
                                        name="modulo_ventas_numeracion_tipo" 
                                        value="consecutiva" 
                                        <?php checked($config['numeracion_tipo'], 'consecutiva'); ?> />
                                    <?php _e('Consecutiva (COT-001, COT-002, etc.)', 'modulo-ventas'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" 
                                        name="modulo_ventas_numeracion_tipo" 
                                        value="fecha" 
                                        <?php checked($config['numeracion_tipo'], 'fecha'); ?> />
                                    <?php _e('Por fecha (COT-20250105-001)', 'modulo-ventas'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" 
                                        name="modulo_ventas_numeracion_tipo" 
                                        value="anual" 
                                        <?php checked($config['numeracion_tipo'], 'anual'); ?> />
                                    <?php _e('Anual (COT-2025-001, reinicia cada año)', 'modulo-ventas'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="moneda_predeterminada"><?php _e('Moneda Predeterminada', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <select name="modulo_ventas_moneda_predeterminada" id="moneda_predeterminada" class="regular-text">
                                <option value="CLP" <?php selected($config['moneda_predeterminada'], 'CLP'); ?>>CLP - Peso Chileno</option>
                                <option value="USD" <?php selected($config['moneda_predeterminada'], 'USD'); ?>>USD - Dólar Americano</option>
                                <option value="EUR" <?php selected($config['moneda_predeterminada'], 'EUR'); ?>>EUR - Euro</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="decimales_precio"><?php _e('Decimales en Precios', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <select name="modulo_ventas_decimales_precio" id="decimales_precio" class="small-text">
                                <option value="0" <?php selected($config['decimales_precio'], '0'); ?>>0 (sin decimales)</option>
                                <option value="1" <?php selected($config['decimales_precio'], '1'); ?>>1 decimal</option>
                                <option value="2" <?php selected($config['decimales_precio'], '2'); ?>>2 decimales</option>
                                <option value="3" <?php selected($config['decimales_precio'], '3'); ?>>3 decimales</option>
                                <option value="4" <?php selected($config['decimales_precio'], '4'); ?>>4 decimales</option>
                            </select>
                            <p class="description">
                                <?php _e('Número de decimales a mostrar en los precios de las cotizaciones. Ejemplo: 2 decimales = $1,234.56', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="decimales_cantidad"><?php _e('Decimales en Cantidades', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <select name="modulo_ventas_decimales_cantidad" id="decimales_cantidad" class="small-text">
                                <option value="0" <?php selected($config['decimales_cantidad'], '0'); ?>>0 (enteros solamente)</option>
                                <option value="1" <?php selected($config['decimales_cantidad'], '1'); ?>>1 decimal</option>
                                <option value="2" <?php selected($config['decimales_cantidad'], '2'); ?>>2 decimales</option>
                                <option value="3" <?php selected($config['decimales_cantidad'], '3'); ?>>3 decimales</option>
                            </select>
                            <p class="description">
                                <?php _e('Número de decimales permitidos en las cantidades. Ejemplo: 2 decimales = 1.25 unidades', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($tab_activa === 'stock') : ?>
            <!-- TAB STOCK Y ALMACENES -->
            <div class="mv-tab-panel">
                <h2><?php _e('Configuración de Stock y Almacenes', 'modulo-ventas'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr class="mv-highlight-row">
                        <th scope="row">
                            <label for="permitir_cotizar_sin_stock"><?php _e('Cotizaciones sin Stock', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_permitir_cotizar_sin_stock" 
                                        id="permitir_cotizar_sin_stock" 
                                        value="yes" 
                                        <?php checked($config['permitir_cotizar_sin_stock'], 'yes'); ?> />
                                    <strong><?php _e('Permitir crear cotizaciones aunque no haya stock disponible', 'modulo-ventas'); ?></strong>
                                </label>
                                <p class="description">
                                    <?php _e('Si está desactivado, no se podrán agregar productos sin stock a las cotizaciones.', 'modulo-ventas'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reservar_stock"><?php _e('Reservar Stock', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_reservar_stock" 
                                        id="reservar_stock" 
                                        value="yes" 
                                        <?php checked($config['reservar_stock'], 'yes'); ?> />
                                    <?php _e('Reservar automáticamente el stock al crear cotizaciones', 'modulo-ventas'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('El stock se reservará hasta que la cotización expire o se convierta en pedido.', 'modulo-ventas'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr class="mv-reserva-tiempo">
                        <th scope="row">
                            <label for="tiempo_reserva_stock"><?php _e('Tiempo de Reserva', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                name="modulo_ventas_tiempo_reserva_stock" 
                                id="tiempo_reserva_stock" 
                                value="<?php echo esc_attr($config['tiempo_reserva_stock']); ?>" 
                                class="small-text" 
                                min="1" 
                                max="72" />
                            <span><?php _e('horas', 'modulo-ventas'); ?></span>
                            <p class="description">
                                <?php _e('Tiempo máximo que se reservará el stock para una cotización pendiente.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <?php if (!empty($almacenes)) : ?>
                    <tr>
                        <th scope="row">
                            <label for="almacen_predeterminado"><?php _e('Almacén Predeterminado', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <select name="modulo_ventas_almacen_predeterminado" id="almacen_predeterminado" class="regular-text">
                                <option value=""><?php _e('-- Seleccionar almacén --', 'modulo-ventas'); ?></option>
                                <?php foreach ($almacenes as $almacen) : ?>
                                    <option value="<?php echo esc_attr($almacen->id); ?>" 
                                            <?php selected($config['almacen_predeterminado'], $almacen->id); ?>>
                                        <?php echo esc_html($almacen->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Almacén que se seleccionará por defecto al crear nuevas cotizaciones.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sincronizar_stock"><?php _e('Sincronización de Stock', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_sincronizar_stock" 
                                        id="sincronizar_stock" 
                                        value="yes" 
                                        <?php checked($config['sincronizar_stock'], 'yes'); ?> />
                                    <?php _e('Sincronizar automáticamente con el sistema de almacenes', 'modulo-ventas'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Mantiene actualizado el stock entre ambos sistemas en tiempo real.', 'modulo-ventas'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <?php else : ?>
                    <tr>
                        <td colspan="2">
                            <div class="notice notice-info inline">
                                <p><?php _e('El plugin "Gestión de Almacenes" no está disponible o no tiene almacenes configurados.', 'modulo-ventas'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th scope="row">
                            <label for="mostrar_stock_cotizacion"><?php _e('Mostrar Stock en Cotizaciones', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_mostrar_stock_cotizacion" 
                                        id="mostrar_stock_cotizacion" 
                                        value="yes" 
                                        <?php checked($config['mostrar_stock_cotizacion'], 'yes'); ?> />
                                    <?php _e('Mostrar cantidad disponible al seleccionar productos', 'modulo-ventas'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($tab_activa === 'emails') : ?>
            <!-- TAB EMAILS Y NOTIFICACIONES -->
            <div class="mv-tab-panel">
                <h2><?php _e('Configuración de Emails y Notificaciones', 'modulo-ventas'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="notificar_nueva_cotizacion"><?php _e('Notificar Nueva Cotización', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_notificar_nueva_cotizacion" 
                                        id="notificar_nueva_cotizacion" 
                                        value="yes" 
                                        <?php checked($config['notificar_nueva_cotizacion'], 'yes'); ?> />
                                    <?php _e('Enviar email cuando se crea una nueva cotización', 'modulo-ventas'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="emails_notificacion"><?php _e('Emails de Notificación', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <textarea name="modulo_ventas_emails_notificacion" 
                                    id="emails_notificacion" 
                                    rows="3" 
                                    cols="50" 
                                    class="regular-text"><?php echo esc_textarea($config['emails_notificacion']); ?></textarea>
                            <p class="description">
                                <?php _e('Emails separados por comas que recibirán notificaciones de nuevas cotizaciones.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="plantilla_email_cotizacion"><?php _e('Plantilla de Email', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <textarea name="modulo_ventas_plantilla_email_cotizacion" 
                                    id="plantilla_email_cotizacion" 
                                    rows="10" 
                                    cols="70" 
                                    class="large-text"><?php echo esc_textarea($config['plantilla_email_cotizacion']); ?></textarea>
                            <p class="description">
                                <?php _e('Plantilla para emails de cotización. Variables disponibles: {cliente_nombre}, {folio}, {fecha}, {total}, {enlace_cotizacion}', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($tab_activa === 'integracion') : ?>
            <!-- TAB INTEGRACIÓN -->
            <div class="mv-tab-panel">
                <h2><?php _e('Configuración de Integración', 'modulo-ventas'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="crear_cliente_automatico"><?php _e('Crear Cliente Automático', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_crear_cliente_automatico" 
                                        id="crear_cliente_automatico" 
                                        value="yes" 
                                        <?php checked($config['crear_cliente_automatico'], 'yes'); ?> />
                                    <?php _e('Crear automáticamente cliente si no existe al crear cotización', 'modulo-ventas'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="conversion_automatica"><?php _e('Conversión Automática', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_conversion_automatica" 
                                        id="conversion_automatica" 
                                        value="yes" 
                                        <?php checked($config['conversion_automatica'], 'yes'); ?> />
                                    <?php _e('Convertir automáticamente cotizaciones a pedidos de WooCommerce', 'modulo-ventas'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Al cambiar el estado a "Aprobada", se creará automáticamente un pedido en WooCommerce.', 'modulo-ventas'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php elseif ($tab_activa === 'pdf') : ?>
            <!-- TAB PDF Y DOCUMENTOS - VERSIÓN CONSOLIDADA -->
            <div class="mv-tab-panel">
                <h2><?php _e('Configuración de PDF y Documentos', 'modulo-ventas'); ?></h2>
                
                <!-- SECCIÓN 1: INFORMACIÓN DE LA EMPRESA -->
                <div class="mv-pdf-section" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="margin-top: 0; color: #2c5aa0; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px;">
                        📄 <?php _e('Información de la Empresa', 'modulo-ventas'); ?>
                    </h3>
                    <p class="description" style="margin-bottom: 20px;">
                        <?php _e('Esta información aparecerá en todos los documentos PDF generados por el sistema.', 'modulo-ventas'); ?>
                    </p>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="logo_empresa"><?php _e('Logo de la Empresa', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <input type="file" 
                                    name="modulo_ventas_logo_empresa" 
                                    id="logo_empresa" 
                                    accept="image/*" />
                                <?php if ($config['logo_empresa']) : ?>
                                    <br><br>
                                    <img src="<?php echo esc_url($config['logo_empresa']); ?>" 
                                        alt="Logo actual" 
                                        style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px;" />
                                    <br><br>
                                    <label>
                                        <input type="checkbox" name="modulo_ventas_eliminar_logo" value="yes" />
                                        <?php _e('Eliminar logo actual', 'modulo-ventas'); ?>
                                    </label>
                                <?php endif; ?>
                                <p class="description">
                                    <?php _e('Logo que aparecerá en los PDFs. Tamaño recomendado: 300x150px. Formatos: JPG, PNG, SVG', 'modulo-ventas'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="info_empresa"><?php _e('Nombre de la Empresa', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                    name="modulo_ventas_nombre_empresa" 
                                    id="nombre_empresa" 
                                    value="<?php echo esc_attr(get_option('modulo_ventas_nombre_empresa', get_option('blogname'))); ?>" 
                                    class="regular-text" />
                                <p class="description">
                                    <?php _e('Nombre legal o comercial de la empresa', 'modulo-ventas'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="rut_empresa"><?php _e('RUT de la Empresa', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                    name="modulo_ventas_rut_empresa" 
                                    id="rut_empresa" 
                                    value="<?php echo esc_attr(get_option('modulo_ventas_rut_empresa', '')); ?>" 
                                    class="regular-text" 
                                    placeholder="12.345.678-9" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="info_empresa"><?php _e('Información Adicional de la Empresa', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <textarea name="modulo_ventas_info_empresa" 
                                        id="info_empresa" 
                                        rows="4" 
                                        cols="70" 
                                        class="large-text"><?php echo esc_textarea($config['info_empresa']); ?></textarea>
                                <p class="description">
                                    <?php _e('Información adicional que aparecerá en el header de los PDFs (giro comercial, descripción, etc.). Disponible como {{info_empresa}}', 'modulo-ventas'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="direccion_empresa"><?php _e('Dirección', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                    name="modulo_ventas_direccion_empresa" 
                                    id="direccion_empresa" 
                                    value="<?php echo esc_attr(get_option('modulo_ventas_direccion_empresa', '')); ?>" 
                                    class="regular-text" 
                                    placeholder="Av. Principal 123" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ciudad_empresa"><?php _e('Ciudad y Región', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" 
                                        name="modulo_ventas_ciudad_empresa" 
                                        id="ciudad_empresa" 
                                        value="<?php echo esc_attr(get_option('modulo_ventas_ciudad_empresa', '')); ?>" 
                                        class="regular-text" 
                                        placeholder="Santiago" 
                                        style="flex: 1;" />
                                    <input type="text" 
                                        name="modulo_ventas_region_empresa" 
                                        id="region_empresa" 
                                        value="<?php echo esc_attr(get_option('modulo_ventas_region_empresa', '')); ?>" 
                                        class="regular-text" 
                                        placeholder="Región Metropolitana" 
                                        style="flex: 2;" />
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="telefono_empresa"><?php _e('Contacto', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <input type="text" 
                                        name="modulo_ventas_telefono_empresa" 
                                        id="telefono_empresa" 
                                        value="<?php echo esc_attr(get_option('modulo_ventas_telefono_empresa', '')); ?>" 
                                        class="regular-text" 
                                        placeholder="+56 2 2345 6789" 
                                        style="flex: 1; min-width: 200px;" />
                                    <input type="email" 
                                        name="modulo_ventas_email_empresa" 
                                        id="email_empresa" 
                                        value="<?php echo esc_attr(get_option('modulo_ventas_email_empresa', get_option('admin_email'))); ?>" 
                                        class="regular-text" 
                                        placeholder="contacto@empresa.com" 
                                        style="flex: 1; min-width: 200px;" />
                                </div>
                                <p class="description">
                                    <?php _e('Teléfono y email de contacto que aparecerán en los PDFs', 'modulo-ventas'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="terminos_condiciones"><?php _e('Términos y Condiciones', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <textarea name="modulo_ventas_terminos_condiciones" 
                                        id="terminos_condiciones" 
                                        rows="6" 
                                        cols="70" 
                                        class="large-text"><?php echo esc_textarea($config['terminos_condiciones']); ?></textarea>
                                <p class="description">
                                    <?php _e('Términos y condiciones que aparecerán al final de los documentos PDF.', 'modulo-ventas'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- SECCIÓN 2: ASIGNACIÓN DE PLANTILLAS -->
                <div class="mv-pdf-section" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="margin-top: 0; color: #2c5aa0; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px;">
                        🎨 <?php _e('Asignación de Plantillas por Tipo de Documento', 'modulo-ventas'); ?>
                    </h3>
                    <p class="description" style="margin-bottom: 20px;">
                        <?php _e('Configura qué plantilla se utilizará para cada tipo de documento. Solo aparecen plantillas activas.', 'modulo-ventas'); ?>
                    </p>
                    
                    <?php
                    // Obtener plantillas disponibles
                    global $wpdb;
                    $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
                    $plantillas_disponibles = $wpdb->get_results("
                        SELECT id, nombre, tipo, descripcion 
                        FROM $tabla_plantillas 
                        WHERE activa = 1 
                        ORDER BY tipo, nombre
                    ");
                    
                    // Agrupar plantillas por tipo
                    $plantillas_por_tipo = array();
                    foreach ($plantillas_disponibles as $plantilla) {
                        $plantillas_por_tipo[$plantilla->tipo][] = $plantilla;
                    }
                    
                    // Obtener configuración actual de plantillas
                    $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
                    $config_plantillas = $wpdb->get_results("
                        SELECT tipo_documento, plantilla_id 
                        FROM $tabla_config
                    ");
                    $config_actual = array();
                    foreach ($config_plantillas as $config_item) {
                        $config_actual[$config_item->tipo_documento] = $config_item->plantilla_id;
                    }
                    
                    // Tipos de documentos
                    $tipos_documentos = array(
                        'cotizacion' => __('Cotización', 'modulo-ventas'),
                        'factura' => __('Factura', 'modulo-ventas'),
                        'boleta' => __('Boleta', 'modulo-ventas'),
                        'orden_compra' => __('Orden de Compra', 'modulo-ventas'),
                        'guia_despacho' => __('Guía de Despacho', 'modulo-ventas'),
                    );
                    ?>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <?php foreach ($tipos_documentos as $tipo => $nombre_tipo) : ?>
                            <div class="plantilla-config-item" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
                                <h4 style="margin-top: 0; color: #2c5aa0; display: flex; align-items: center; gap: 10px;">
                                    <?php 
                                    $iconos = array(
                                        'cotizacion' => '💰',
                                        'factura' => '🧾', 
                                        'boleta' => '🧾',
                                        'orden_compra' => '📋',
                                        'guia_despacho' => '📦'
                                    );
                                    echo $iconos[$tipo] ?? '📄';
                                    ?>
                                    <?php echo esc_html($nombre_tipo); ?>
                                    
                                    <!-- Estado de configuración -->
                                    <span class="config-status" style="margin-left: auto; font-size: 12px;">
                                        <?php if (isset($config_actual[$tipo])) : ?>
                                            <span style="color: #46b450;">● Configurado</span>
                                        <?php else : ?>
                                            <span style="color: #dc3232;">● Sin configurar</span>
                                        <?php endif; ?>
                                    </span>
                                </h4>
                                
                                <select name="plantilla_<?php echo $tipo; ?>" 
                                        class="regular-text plantilla-selector" 
                                        data-tipo="<?php echo $tipo; ?>">
                                    <option value=""><?php _e('-- Seleccionar plantilla --', 'modulo-ventas'); ?></option>
                                    <?php if (isset($plantillas_por_tipo[$tipo])) : ?>
                                        <?php foreach ($plantillas_por_tipo[$tipo] as $plantilla) : ?>
                                            <option value="<?php echo $plantilla->id; ?>" 
                                                    <?php selected($config_actual[$tipo] ?? '', $plantilla->id); ?>>
                                                <?php echo esc_html($plantilla->nombre); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                
                                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button type="button" 
                                            class="button button-small preview-plantilla" 
                                            data-tipo="<?php echo $tipo; ?>"
                                            <?php echo !isset($config_actual[$tipo]) ? 'disabled' : ''; ?>>
                                        👁️ <?php _e('Preview', 'modulo-ventas'); ?>
                                    </button>
                                    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new&tipo=' . $tipo); ?>" 
                                    class="button button-small">
                                        ➕ <?php _e('Nueva', 'modulo-ventas'); ?>
                                    </a>
                                    <?php if (isset($config_actual[$tipo])) : ?>
                                        <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $config_actual[$tipo]); ?>" 
                                        class="button button-small">
                                            ✏️ <?php _e('Editar', 'modulo-ventas'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- SECCIÓN 3: GESTIÓN DE PLANTILLAS -->
                <div class="mv-pdf-section" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px;">
                    <h3 style="margin-top: 0; color: #2c5aa0; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px;">
                        🛠️ <?php _e('Gestión de Plantillas', 'modulo-ventas'); ?>
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div style="text-align: center; padding: 20px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                            <div style="font-size: 32px; color: #2c5aa0; margin-bottom: 10px;">
                                <?php echo count($plantillas_disponibles); ?>
                            </div>
                            <div style="font-weight: bold; margin-bottom: 5px;"><?php _e('Plantillas Activas', 'modulo-ventas'); ?></div>
                            <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates'); ?>" class="button button-small">
                                <?php _e('Ver Todas', 'modulo-ventas'); ?>
                            </a>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                            <div style="font-size: 32px; color: #46b450; margin-bottom: 10px;">
                                <?php echo count(array_filter($config_actual)); ?>
                            </div>
                            <div style="font-weight: bold; margin-bottom: 5px;"><?php _e('Tipos Configurados', 'modulo-ventas'); ?></div>
                            <span style="font-size: 12px; color: #666;">
                                de <?php echo count($tipos_documentos); ?> disponibles
                            </span>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: white; border-radius: 4px; border: 1px solid #ddd;">
                            <div style="font-size: 24px; color: #2c5aa0; margin-bottom: 10px;">📄</div>
                            <div style="font-weight: bold; margin-bottom: 10px;"><?php _e('Acciones Rápidas', 'modulo-ventas'); ?></div>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new'); ?>" class="button button-small">
                                    <?php _e('Nueva Plantilla', 'modulo-ventas'); ?>
                                </a>
                                <button type="button" class="button button-small test-pdf-generation">
                                    <?php _e('Test Sistema PDF', 'modulo-ventas'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN 4: CONFIGURACIÓN AVANZADA -->
                <div class="mv-pdf-section" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
                    <h3 style="margin-top: 0; color: #2c5aa0; border-bottom: 2px solid #2c5aa0; padding-bottom: 10px;">
                        ⚙️ <?php _e('Configuración Avanzada de PDF', 'modulo-ventas'); ?>
                    </h3>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="pdf_engine"><?php _e('Motor de PDF', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <select name="modulo_ventas_pdf_engine" id="pdf_engine" class="regular-text">
                                    <option value="mpdf" <?php selected(get_option('modulo_ventas_pdf_engine', 'mpdf'), 'mpdf'); ?>>
                                        mPDF (Recomendado)
                                    </option>
                                    <option value="tcpdf" <?php selected(get_option('modulo_ventas_pdf_engine', 'mpdf'), 'tcpdf'); ?>>
                                        TCPDF (Fallback)
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Motor utilizado para generar los PDFs. mPDF ofrece mejor compatibilidad con CSS.', 'modulo-ventas'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pdf_formato"><?php _e('Formato de Página', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <select name="modulo_ventas_pdf_formato" id="pdf_formato" class="regular-text">
                                    <option value="A4" <?php selected(get_option('modulo_ventas_pdf_formato', 'A4'), 'A4'); ?>>A4</option>
                                    <option value="Letter" <?php selected(get_option('modulo_ventas_pdf_formato', 'A4'), 'Letter'); ?>>Letter</option>
                                    <option value="Legal" <?php selected(get_option('modulo_ventas_pdf_formato', 'A4'), 'Legal'); ?>>Legal</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pdf_orientacion"><?php _e('Orientación', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <select name="modulo_ventas_pdf_orientacion" id="pdf_orientacion" class="regular-text">
                                    <option value="portrait" <?php selected(get_option('modulo_ventas_pdf_orientacion', 'portrait'), 'portrait'); ?>>
                                        <?php _e('Retrato (Vertical)', 'modulo-ventas'); ?>
                                    </option>
                                    <option value="landscape" <?php selected(get_option('modulo_ventas_pdf_orientacion', 'portrait'), 'landscape'); ?>>
                                        <?php _e('Paisaje (Horizontal)', 'modulo-ventas'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="pdf_compresion"><?php _e('Compresión de PDF', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" 
                                            name="modulo_ventas_pdf_compresion" 
                                            id="pdf_compresion" 
                                            value="yes" 
                                            <?php checked(get_option('modulo_ventas_pdf_compresion', 'yes'), 'yes'); ?> />
                                        <?php _e('Activar compresión para archivos más pequeños', 'modulo-ventas'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Reduce el tamaño de los archivos PDF generados.', 'modulo-ventas'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <?php elseif ($tab_activa === 'avanzado') : ?>
            <!-- TAB AVANZADO -->
            <div class="mv-tab-panel">
                <h2><?php _e('Configuración Avanzada', 'modulo-ventas'); ?></h2>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="log_level"><?php _e('Nivel de Logging', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <select name="modulo_ventas_log_level" id="log_level" class="regular-text">
                                <option value="emergency" <?php selected($config['log_level'], 'emergency'); ?>>Emergency</option>
                                <option value="alert" <?php selected($config['log_level'], 'alert'); ?>>Alert</option>
                                <option value="critical" <?php selected($config['log_level'], 'critical'); ?>>Critical</option>
                                <option value="error" <?php selected($config['log_level'], 'error'); ?>>Error</option>
                                <option value="warning" <?php selected($config['log_level'], 'warning'); ?>>Warning</option>
                                <option value="notice" <?php selected($config['log_level'], 'notice'); ?>>Notice</option>
                                <option value="info" <?php selected($config['log_level'], 'info'); ?>>Info</option>
                                <option value="debug" <?php selected($config['log_level'], 'debug'); ?>>Debug</option>
                            </select>
                            <p class="description">
                                <?php _e('Nivel mínimo de eventos que se registrarán en los logs.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="log_retention_days"><?php _e('Retención de Logs', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                name="modulo_ventas_log_retention_days" 
                                id="log_retention_days" 
                                value="<?php echo esc_attr($config['log_retention_days']); ?>" 
                                class="small-text" 
                                min="1" 
                                max="365" />
                            <span><?php _e('días', 'modulo-ventas'); ?></span>
                            <p class="description">
                                <?php _e('Número de días que se mantendrán los archivos de log antes de eliminarlos automáticamente.', 'modulo-ventas'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="debug_mode"><?php _e('Modo Debug', 'modulo-ventas'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" 
                                        name="modulo_ventas_debug_mode" 
                                        id="debug_mode" 
                                        value="yes" 
                                        <?php checked($config['debug_mode'], 'yes'); ?> />
                                    <?php _e('Activar modo debug (solo para desarrollo)', 'modulo-ventas'); ?>
                                </label>
                                <p class="description">
                                    <span style="color: #d63384;">⚠️</span> <?php _e('Solo activar para depuración. Puede afectar el rendimiento.', 'modulo-ventas'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <p class="submit">
            <input type="submit" 
                name="submit" 
                id="submit" 
                class="button-primary" 
                value="<?php esc_attr_e('Guardar Configuración', 'modulo-ventas'); ?>" />
            <a href="?page=modulo-ventas-configuracion&reset=1&tab=<?php echo esc_attr($tab_activa); ?>" 
            class="button button-secondary" 
            onclick="return confirm('<?php esc_attr_e('¿Está seguro de restaurar la configuración por defecto?', 'modulo-ventas'); ?>')">
                <?php _e('Restaurar Valores por Defecto', 'modulo-ventas'); ?>
            </a>
        </p>
    </form>
</div>

<style>
.mv-configuracion .nav-tab-wrapper {
    margin-bottom: 0;
}

.mv-configuracion .nav-tab .dashicons {
    margin-right: 5px;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* CSS MÁS ESPECÍFICO para evitar conflictos */
.mv-configuracion .mv-config-tab-content {
    background: white !important;
    padding: 20px !important;
    border-top: none !important;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04) !important;
    display: block !important;
    height: auto !important;
    position: static !important;
    visibility: visible !important;
}

/* Asegurar que el panel de tab también sea visible */
.mv-configuracion .mv-tab-panel {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.mv-configuracion .form-table {
    display: table !important;
    width: 100% !important;
}

.mv-configuracion .form-table th {
    width: 220px;
    padding-left: 10px;
    vertical-align: top;
    display: table-cell !important;
}

.mv-configuracion .form-table td {
    padding-top: 15px;
    padding-bottom: 15px;
    display: table-cell !important;
}

.mv-configuracion .form-table tr {
    display: table-row !important;
}

.mv-configuracion .description {
    font-style: italic;
    color: #666;
    margin-top: 5px;
}

.mv-configuracion fieldset label {
    margin-bottom: 8px;
    display: block;
    font-weight: normal;
}

.mv-configuracion .button-secondary {
    margin-left: 10px;
}

.mv-highlight-row {
    background-color: #f9f9f9;
}

.mv-highlight-row th,
.mv-highlight-row td {
    border-left: 4px solid #0073aa;
    padding-left: 15px;
}

.mv-config-form .notice.inline {
    margin: 5px 0 15px 0;
    padding: 5px 12px;
}

.mv-configuracion .mv-tab-panel h2 {
    margin-top: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e5e5;
    display: block !important;
}

.mv-reserva-tiempo {
    display: none;
}

/* Responsivo */
@media (max-width: 782px) {
    .mv-configuracion .nav-tab {
        font-size: 12px;
        padding: 8px 10px;
    }
    
    .mv-configuracion .nav-tab .dashicons {
        display: none;
    }
    
    .mv-configuracion .form-table th {
        width: auto;
        padding-left: 0;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Mostrar/ocultar opciones dependientes
    function toggleDependentOptions() {
        // Tiempo de reserva
        if ($('#reservar_stock').is(':checked')) {
            $('.mv-reserva-tiempo').show();
        } else {
            $('.mv-reserva-tiempo').hide();
        }
    }
    
    $('#reservar_stock').change(toggleDependentOptions);
    toggleDependentOptions();

    // Cambio en selector de plantilla
    $('.plantilla-selector').on('change', function() {
        var $this = $(this);
        var $container = $this.closest('.plantilla-config-item');
        var $status = $container.find('.config-status span');
        var $preview = $container.find('.preview-plantilla');
        
        if ($this.val()) {
            $status.removeClass().addClass('').css('color', '#46b450').html('● Configurado');
            $preview.prop('disabled', false);
        } else {
            $status.removeClass().addClass('').css('color', '#dc3232').html('● Sin configurar');
            $preview.prop('disabled', true);
        }
    });
    
    // Preview de plantilla
    $('.preview-plantilla').on('click', function() {
        var tipo = $(this).data('tipo');
        var plantillaId = $('select[name="plantilla_' + tipo + '"]').val();
        
        if (!plantillaId) {
            alert('Selecciona una plantilla primero');
            return;
        }
        
        // Abrir preview en nueva ventana
        var url = ajaxurl + '?action=mv_preview_plantilla&plantilla_id=' + plantillaId + '&tipo=' + tipo;
        window.open(url, 'preview-plantilla', 'width=800,height=600,scrollbars=yes');
    });
    
    // Test del sistema PDF
    $('.test-pdf-generation').on('click', function() {
        window.open('/wp-admin/admin-ajax.php?action=mv_test_mpdf_visual_comparison', 'test-pdf', 'width=1000,height=800,scrollbars=yes');
    });
    
    // Validación del formulario
    $('.mv-config-form').on('submit', function(e) {
        var prefijo = $('#prefijo_cotizacion').val().trim();
        var dias = parseInt($('#dias_expiracion').val());
        
        if (prefijo === '') {
            alert('<?php echo esc_js(__('El prefijo de cotizaciones no puede estar vacío.', 'modulo-ventas')); ?>');
            $('#prefijo_cotizacion').focus();
            e.preventDefault();
            return false;
        }
        
        if (isNaN(dias) || dias < 1 || dias > 365) {
            alert('<?php echo esc_js(__('Los días de expiración deben estar entre 1 y 365.', 'modulo-ventas')); ?>');
            $('#dias_expiracion').focus();
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>