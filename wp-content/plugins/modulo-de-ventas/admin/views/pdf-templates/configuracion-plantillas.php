<?php
/**
 * VISTA ADMIN - CONFIGURACIÓN DE PLANTILLAS PDF
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/admin/views/pdf-templates/configuracion-plantillas.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Tipos de documentos disponibles
$tipos_documentos = array(
    'cotizacion' => __('Cotización', 'modulo-ventas'),
    'factura' => __('Factura', 'modulo-ventas'),
    'boleta' => __('Boleta', 'modulo-ventas'),
    'orden_compra' => __('Orden de Compra', 'modulo-ventas'),
    'guia_despacho' => __('Guía de Despacho', 'modulo-ventas'),
    'nota_credito' => __('Nota de Crédito', 'modulo-ventas'),
    'nota_debito' => __('Nota de Débito', 'modulo-ventas')
);

// Configuración actual indexada por tipo
$config_actual = array();
foreach ($configuracion_actual as $config) {
    $config_actual[$config->tipo_documento] = $config;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Configuración de Plantillas PDF', 'modulo-ventas'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates'); ?>" class="page-title-action">
        <?php _e('← Volver a Plantillas', 'modulo-ventas'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div class="mv-config-container" style="display: flex; gap: 30px; margin-top: 20px;">
        
        <!-- Panel principal de configuración -->
        <div class="mv-config-main" style="flex: 2;">
            
            <!-- Introducción -->
            <div class="mv-intro-panel" style="background: #e7f3ff; border: 1px solid #bee5eb; border-radius: 4px; padding: 20px; margin-bottom: 25px;">
                <h2 style="margin-top: 0; color: #0c5460;">
                    <?php _e('Asignación de Plantillas por Tipo de Documento', 'modulo-ventas'); ?>
                </h2>
                <p style="margin-bottom: 0; color: #0c5460;">
                    <?php _e('Configura qué plantilla se utilizará para cada tipo de documento. Solo las plantillas activas aparecen en las listas.', 'modulo-ventas'); ?>
                </p>
            </div>
            
            <!-- Formulario de configuración -->
            <form method="post" id="form-config-plantillas">
                <?php wp_nonce_field('mv_config_plantillas', 'mv_config_nonce'); ?>
                
                <div class="mv-config-grid">
                    <?php foreach ($tipos_documentos as $tipo => $nombre): ?>
                        
                        <div class="mv-config-item" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                            
                            <div class="mv-config-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                                <h3 style="margin: 0; color: #2c5aa0;">
                                    <?php echo esc_html($nombre); ?>
                                </h3>
                                
                                <div class="mv-config-status">
                                    <?php if (isset($config_actual[$tipo])): ?>
                                        <span style="color: #46b450; font-weight: bold;">● <?php _e('Configurado', 'modulo-ventas'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #dc3232; font-weight: bold;">● <?php _e('Sin configurar', 'modulo-ventas'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mv-config-content" style="display: flex; gap: 20px; align-items: center;">
                                
                                <!-- Selector de plantilla -->
                                <div style="flex: 1;">
                                    <label for="plantilla_<?php echo $tipo; ?>" style="display: block; font-weight: 600; margin-bottom: 5px;">
                                        <?php _e('Plantilla a utilizar:', 'modulo-ventas'); ?>
                                    </label>
                                    
                                    <select name="plantillas[<?php echo $tipo; ?>]" 
                                            id="plantilla_<?php echo $tipo; ?>" 
                                            class="mv-select-plantilla"
                                            style="width: 100%; padding: 8px;">
                                        
                                        <option value=""><?php _e('Sin asignar', 'modulo-ventas'); ?></option>
                                        
                                        <?php if (isset($plantillas_por_tipo[$tipo])): ?>
                                            <optgroup label="<?php echo esc_attr(sprintf(__('Plantillas de %s', 'modulo-ventas'), $nombre)); ?>">
                                                <?php foreach ($plantillas_por_tipo[$tipo] as $plantilla): ?>
                                                    <option value="<?php echo $plantilla->id; ?>" 
                                                            <?php selected(isset($config_actual[$tipo]) ? $config_actual[$tipo]->plantilla_id : '', $plantilla->id); ?>>
                                                        <?php echo esc_html($plantilla->nombre); ?>
                                                        <?php if ($plantilla->es_predeterminada): ?>
                                                            <?php _e('(Predeterminada)', 'modulo-ventas'); ?>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        
                                        <!-- Plantillas de otros tipos (en caso de que se quiera usar) -->
                                        <?php foreach ($plantillas_por_tipo as $tipo_plantilla => $plantillas_tipo): ?>
                                            <?php if ($tipo_plantilla !== $tipo): ?>
                                                <optgroup label="<?php echo esc_attr(sprintf(__('Plantillas de %s', 'modulo-ventas'), $tipos_documentos[$tipo_plantilla] ?? ucfirst($tipo_plantilla))); ?>">
                                                    <?php foreach ($plantillas_tipo as $plantilla): ?>
                                                        <option value="<?php echo $plantilla->id; ?>" 
                                                                <?php selected(isset($config_actual[$tipo]) ? $config_actual[$tipo]->plantilla_id : '', $plantilla->id); ?>>
                                                            <?php echo esc_html($plantilla->nombre); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <?php if (isset($config_actual[$tipo])): ?>
                                        <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                            <?php 
                                            echo sprintf(
                                                __('Configurado el %s', 'modulo-ventas'), 
                                                date_i18n('d/m/Y H:i', strtotime($config_actual[$tipo]->fecha_asignacion))
                                            ); 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Acciones -->
                                <div style="flex: 0 0 200px;">
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        
                                        <!-- Preview -->
                                        <button type="button" 
                                                class="button button-small mv-preview-tipo" 
                                                data-tipo="<?php echo $tipo; ?>"
                                                <?php if (!isset($config_actual[$tipo])): ?>disabled<?php endif; ?>>
                                            <?php _e('Vista Previa', 'modulo-ventas'); ?>
                                        </button>
                                        
                                        <!-- Crear nueva plantilla para este tipo -->
                                        <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new&tipo=' . $tipo); ?>" 
                                           class="button button-small">
                                            <?php _e('Nueva Plantilla', 'modulo-ventas'); ?>
                                        </a>
                                        
                                        <!-- Editar plantilla actual -->
                                        <?php if (isset($config_actual[$tipo])): ?>
                                            <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $config_actual[$tipo]->plantilla_id); ?>" 
                                               class="button button-small">
                                                <?php _e('Editar Plantilla', 'modulo-ventas'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Información adicional -->
                            <?php if (isset($config_actual[$tipo])): ?>
                                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 3px; font-size: 13px;">
                                    <strong><?php _e('Plantilla actual:', 'modulo-ventas'); ?></strong> 
                                    <?php echo esc_html($config_actual[$tipo]->plantilla_nombre); ?>
                                    
                                    <?php if (!empty($config_actual[$tipo]->descripcion)): ?>
                                        <br><span style="color: #666;"><?php echo esc_html($config_actual[$tipo]->descripcion); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 3px; font-size: 13px;">
                                    <strong><?php _e('Sin configurar:', 'modulo-ventas'); ?></strong> 
                                    <?php _e('Se utilizará la plantilla predeterminada si existe.', 'modulo-ventas'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php endforeach; ?>
                </div>
                
                <!-- Botones de acción -->
                <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <input type="submit" 
                                   name="guardar_configuracion" 
                                   class="button button-primary button-large" 
                                   value="<?php _e('Guardar Configuración', 'modulo-ventas'); ?>">
                            
                            <button type="button" 
                                    class="button button-large" 
                                    id="restablecer-configuracion">
                                <?php _e('Restablecer a Predeterminadas', 'modulo-ventas'); ?>
                            </button>
                        </div>
                        
                        <div style="color: #666; font-size: 13px;">
                            <?php _e('Los cambios se aplicarán inmediatamente', 'modulo-ventas'); ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Panel lateral de información -->
        <div class="mv-config-sidebar" style="flex: 1;">
            
            <!-- Resumen de configuración -->
            <div class="mv-sidebar-panel" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                <h3><?php _e('Resumen de Configuración', 'modulo-ventas'); ?></h3>
                
                <div class="mv-config-summary">
                    <?php 
                    $configurados = count($configuracion_actual);
                    $total_tipos = count($tipos_documentos);
                    ?>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span><?php _e('Tipos configurados:', 'modulo-ventas'); ?></span>
                        <strong><?php echo $configurados; ?>/<?php echo $total_tipos; ?></strong>
                    </div>
                    
                    <div style="width: 100%; background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 15px;">
                        <div style="width: <?php echo ($configurados / $total_tipos) * 100; ?>%; background: #2c5aa0; height: 8px;"></div>
                    </div>
                    
                    <div style="font-size: 12px; color: #666;">
                        <?php if ($configurados === $total_tipos): ?>
                            <?php _e('¡Configuración completa!', 'modulo-ventas'); ?>
                        <?php else: ?>
                            <?php echo sprintf(__('Faltan %d tipos por configurar', 'modulo-ventas'), $total_tipos - $configurados); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Plantillas disponibles -->
            <div class="mv-sidebar-panel" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                <h3><?php _e('Plantillas Disponibles', 'modulo-ventas'); ?></h3>
                
                <?php if (empty($plantillas_por_tipo)): ?>
                    <p style="color: #666; font-style: italic;">
                        <?php _e('No hay plantillas disponibles.', 'modulo-ventas'); ?>
                    </p>
                    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new'); ?>" class="button button-small">
                        <?php _e('Crear Primera Plantilla', 'modulo-ventas'); ?>
                    </a>
                <?php else: ?>
                    <?php foreach ($plantillas_por_tipo as $tipo => $plantillas): ?>
                        <div style="margin-bottom: 15px;">
                            <strong style="color: #2c5aa0;"><?php echo esc_html($tipos_documentos[$tipo] ?? ucfirst($tipo)); ?></strong>
                            <div style="margin-left: 10px; font-size: 13px;">
                                <?php foreach ($plantillas as $plantilla): ?>
                                    <div style="margin: 2px 0; color: #666;">
                                        • <?php echo esc_html($plantilla->nombre); ?>
                                        <?php if ($plantilla->es_predeterminada): ?>
                                            <span style="color: #46b450;">(<?php _e('Predeterminada', 'modulo-ventas'); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Ayuda -->
            <div class="mv-sidebar-panel" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px;">
                <h3><?php _e('Ayuda', 'modulo-ventas'); ?></h3>
                
                <div style="font-size: 13px; line-height: 1.5;">
                    <p><strong><?php _e('¿Cómo funciona?', 'modulo-ventas'); ?></strong></p>
                    <ul style="margin-left: 15px;">
                        <li><?php _e('Asigna una plantilla a cada tipo de documento', 'modulo-ventas'); ?></li>
                        <li><?php _e('Solo aparecen plantillas activas', 'modulo-ventas'); ?></li>
                        <li><?php _e('Si no hay asignación, se usa la predeterminada', 'modulo-ventas'); ?></li>
                    </ul>
                    
                    <p><strong><?php _e('Consejos:', 'modulo-ventas'); ?></strong></p>
                    <ul style="margin-left: 15px;">
                        <li><?php _e('Prueba el preview antes de guardar', 'modulo-ventas'); ?></li>
                        <li><?php _e('Crea plantillas específicas para cada tipo', 'modulo-ventas'); ?></li>
                        <li><?php _e('Duplica plantillas para ahorrar tiempo', 'modulo-ventas'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.mv-select-plantilla {
    max-width: 100%;
}

.mv-config-item {
    transition: box-shadow 0.2s;
}

.mv-config-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.mv-preview-tipo:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 1200px) {
    .mv-config-container {
        flex-direction: column;
    }
    
    .mv-config-content {
        flex-direction: column !important;
        align-items: stretch !important;
    }
}

@media (max-width: 782px) {
    .mv-config-content > div:last-child {
        flex: none !important;
    }
    
    .mv-config-content > div:last-child > div {
        flex-direction: row !important;
        flex-wrap: wrap;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Manejar envío del formulario
    $('#form-config-plantillas').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('input[type="submit"]');
        var textoOriginal = $submit.val();
        
        $submit.prop('disabled', true).val('<?php echo esc_js(__('Guardando...', 'modulo-ventas')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=mv_guardar_config_plantillas',
            success: function(response) {
                if (response.success) {
                    mostrarNotificacion(response.data.message, 'success');
                    // Actualizar estados visuales
                    actualizarEstadosConfiguracion();
                } else {
                    mostrarNotificacion(response.data.message, 'error');
                }
            },
            error: function() {
                mostrarNotificacion('<?php echo esc_js(__('Error al guardar configuración', 'modulo-ventas')); ?>', 'error');
            },
            complete: function() {
                $submit.prop('disabled', false).val(textoOriginal);
            }
        });
    });
    
    // Preview por tipo
    $('.mv-preview-tipo').on('click', function() {
        var tipo = $(this).data('tipo');
        var plantillaId = $('#plantilla_' + tipo).val();
        
        if (!plantillaId) {
            alert('<?php echo esc_js(__('Selecciona una plantilla primero', 'modulo-ventas')); ?>');
            return;
        }
        
        // Generar preview usando AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_plantilla',
                nonce: '<?php echo wp_create_nonce('mv_pdf_templates'); ?>',
                plantilla_id: plantillaId
            },
            success: function(response) {
                if (response.success) {
                    var plantilla = response.data;
                    
                    // Generar preview
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mv_preview_plantilla',
                            nonce: '<?php echo wp_create_nonce('mv_pdf_templates'); ?>',
                            html_content: plantilla.html_content,
                            css_content: plantilla.css_content,
                            cotizacion_id: 0
                        },
                        success: function(previewResponse) {
                            if (previewResponse.success) {
                                abrirPreview(previewResponse.data.preview_url);
                            } else {
                                mostrarNotificacion(previewResponse.data.message, 'error');
                            }
                        }
                    });
                }
            }
        });
    });
    
    // Restablecer configuración
    $('#restablecer-configuracion').on('click', function() {
        if (!confirm('<?php echo esc_js(__('¿Estás seguro de restablecer toda la configuración a las plantillas predeterminadas?', 'modulo-ventas')); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_restablecer_config_plantillas',
                nonce: '<?php echo wp_create_nonce('mv_pdf_templates'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    mostrarNotificacion(response.data.message, 'success');
                    // Recargar página después de 2 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarNotificacion(response.data.message, 'error');
                }
            }
        });
    });
    
    // Actualizar estados cuando cambia la selección
    $('.mv-select-plantilla').on('change', function() {
        var $container = $(this).closest('.mv-config-item');
        var $status = $container.find('.mv-config-status span');
        var $preview = $container.find('.mv-preview-tipo');
        
        if ($(this).val()) {
            $status.removeClass().addClass('').css('color', '#46b450').html('● <?php echo esc_js(__('Configurado', 'modulo-ventas')); ?>');
            $preview.prop('disabled', false);
        } else {
            $status.removeClass().addClass('').css('color', '#dc3232').html('● <?php echo esc_js(__('Sin configurar', 'modulo-ventas')); ?>');
            $preview.prop('disabled', true);
        }
    });
    
    function mostrarNotificacion(mensaje, tipo) {
        var $notice = $('<div class="notice notice-' + tipo + ' is-dismissible"><p>' + mensaje + '</p></div>');
        
        if ($('.wp-header-end').length) {
            $notice.insertAfter('.wp-header-end');
        } else {
            $notice.prependTo('.wrap');
        }
        
        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        $('html, body').animate({ scrollTop: 0 }, 500);
    }
    
    function abrirPreview(url) {
        window.open(url, 'preview-plantilla', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }
    
    function actualizarEstadosConfiguracion() {
        // Actualizar contadores y estados visuales
        var configurados = $('.mv-select-plantilla').filter(function() {
            return $(this).val() !== '';
        }).length;
        
        var total = $('.mv-select-plantilla').length;
        var porcentaje = (configurados / total) * 100;
        
        // Actualizar barra de progreso si existe
        $('.mv-config-summary .progress-bar').css('width', porcentaje + '%');
    }
});
</script>

<?php
// Manejar envío del formulario
if (isset($_POST['guardar_configuracion']) && wp_verify_nonce($_POST['mv_config_nonce'], 'mv_config_plantillas')) {
    
    global $wpdb;
    $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
    
    $plantillas = isset($_POST['plantillas']) ? $_POST['plantillas'] : array();
    $errores = array();
    $exitos = 0;
    
    foreach ($plantillas as $tipo => $plantilla_id) {
        if (empty($plantilla_id)) {
            // Desactivar configuración existente
            $wpdb->update(
                $tabla_config,
                array('activa' => 0),
                array('tipo_documento' => $tipo),
                array('%d'),
                array('%s')
            );
            continue;
        }
        
        // Desactivar configuración anterior
        $wpdb->update(
            $tabla_config,
            array('activa' => 0),
            array('tipo_documento' => $tipo),
            array('%d'),
            array('%s')
        );
        
        // Insertar nueva configuración
        $resultado = $wpdb->replace(
            $tabla_config,
            array(
                'tipo_documento' => $tipo,
                'plantilla_id' => intval($plantilla_id),
                'activa' => 1,
                'fecha_asignacion' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%s')
        );
        
        if ($resultado !== false) {
            $exitos++;
        } else {
            $errores[] = sprintf(__('Error configurando %s', 'modulo-ventas'), $tipos_documentos[$tipo]);
        }
    }
}