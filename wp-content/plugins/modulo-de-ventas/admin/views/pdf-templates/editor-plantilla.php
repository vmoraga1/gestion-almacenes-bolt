<?php
/**
 * VISTA ADMIN - EDITOR DE PLANTILLAS
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

$es_nueva = !$plantilla;
$titulo = $es_nueva ? __('Nueva Plantilla PDF', 'modulo-ventas') : sprintf(__('Editar Plantilla: %s', 'modulo-ventas'), $plantilla->nombre);

// Datos por defecto para nueva plantilla
$datos = array(
    'id' => $plantilla ? $plantilla->id : 0,
    'nombre' => $plantilla ? $plantilla->nombre : '',
    'tipo' => $plantilla ? $plantilla->tipo : (isset($_GET['tipo']) ? sanitize_text_field($_GET['tipo']) : 'cotizacion'),
    'descripcion' => $plantilla ? $plantilla->descripcion : '',
    'html_content' => $plantilla ? $plantilla->html_content : '',
    'css_content' => $plantilla ? $plantilla->css_content : '',
    'activa' => $plantilla ? $plantilla->activa : 1
);
?>

<div class="wrap mv-editor-plantillas">
    <h1><?php echo esc_html($titulo); ?></h1>
    
    <div class="mv-editor-toolbar">
        <button type="button" id="btn-guardar-plantilla" class="button button-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php _e('Guardar Plantilla', 'modulo-ventas'); ?>
        </button>
        
        <?php if (!$es_nueva): ?>
            <button type="button" id="btn-duplicar-plantilla" class="button" data-plantilla-id="<?php echo $datos['id']; ?>">
                <span class="dashicons dashicons-admin-page"></span>
                <?php _e('Duplicar', 'modulo-ventas'); ?>
            </button>
        <?php endif; ?>
        
        <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates'); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php _e('Volver a la Lista', 'modulo-ventas'); ?>
        </a>
        
        <div class="mv-editor-status">
            <span id="status-guardado" class="hidden">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php _e('Guardado', 'modulo-ventas'); ?>
            </span>
            <span id="status-guardando" class="hidden">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <?php _e('Guardando...', 'modulo-ventas'); ?>
            </span>
        </div>
    </div>

    <!-- Sección de botones de preview actualizada -->
    <div class="mv-botones-preview" style="margin: 20px 0; padding: 15px; background: #f1f1f1; border-radius: 8px;">
        <h3 style="margin-bottom: 15px;">
            <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
            Opciones de Preview
        </h3>
        
        <!-- Toggle global para todos los previews -->
        <div class="mv-preview-mode-control" style="margin-bottom: 15px; padding: 10px; background: #fff; border-radius: 4px; border-left: 4px solid #0073aa;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                <input type="checkbox" id="usar-datos-reales-global" checked> 
                <span class="dashicons dashicons-database" style="color: #0073aa;"></span>
                <span>Usar datos reales en todos los previews</span>
                <small style="color: #666; margin-left: 10px;">(última cotización creada)</small>
            </label>
            <div id="info-datos-preview" style="margin-top: 8px; font-size: 12px; color: #666;"></div>
        </div>

        <!-- Botones de preview -->
        <div class="mv-botones-container" style="display: flex; gap: 10px; flex-wrap: wrap;">
            
            <!-- Vista Previa en Nueva Ventana -->
            <button type="button" 
                    id="vista-previa-ventana" 
                    class="button button-secondary"
                    data-usar-reales="true"
                    style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-external"></span>
                Vista Previa
                <small class="preview-data-indicator">(datos reales)</small>
            </button>

            <!-- Preview Sincronizado con mPDF -->
            <button type="button" 
                    id="preview-sincronizado-mpdf" 
                    class="button button-secondary"
                    data-usar-reales="true"
                    style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-pdf"></span>
                Preview Sincronizado mPDF
                <small class="preview-data-indicator">(datos reales)</small>
            </button>

            <!-- Test de Comparación Visual -->
            <button type="button" 
                    id="test-comparison" 
                    class="button button-secondary"
                    style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-analytics"></span>
                Test Comparación Visual
            </button>
        </div>

        <!-- Información sobre el estado actual -->
        <div class="mv-preview-info" style="margin-top: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px; font-size: 13px;">
            <span class="dashicons dashicons-info"></span>
            <strong>Estado actual:</strong> 
            <span id="estado-datos-preview">Todos los previews usarán datos de la última cotización real</span>
        </div>
    </div>

    <form id="form-plantilla" method="post">
        <?php wp_nonce_field('mv_pdf_templates', 'nonce'); ?>
        <input type="hidden" id="plantilla-id" name="id" value="<?php echo $datos['id']; ?>">
        
        <div class="mv-editor-layout">
            <!-- Panel izquierdo - Configuración -->
            <div class="mv-editor-sidebar">
                
                <!-- Información básica -->
                <div class="mv-panel">
                    <h3><?php _e('Información Básica', 'modulo-ventas'); ?></h3>
                    
                    <div class="mv-field">
                        <label for="plantilla-nombre"><?php _e('Nombre de la Plantilla', 'modulo-ventas'); ?></label>
                        <input type="text" id="plantilla-nombre" name="nombre" value="<?php echo esc_attr($datos['nombre']); ?>" class="regular-text" required>
                    </div>
                    
                    <div class="mv-field">
                        <label for="plantilla-tipo"><?php _e('Tipo de Documento', 'modulo-ventas'); ?></label>
                        <select id="plantilla-tipo" name="tipo">
                            <option value="cotizacion" <?php selected($datos['tipo'], 'cotizacion'); ?>><?php _e('Cotización', 'modulo-ventas'); ?></option>
                            <option value="factura" <?php selected($datos['tipo'], 'factura'); ?>><?php _e('Factura', 'modulo-ventas'); ?></option>
                            <option value="orden_compra" <?php selected($datos['tipo'], 'orden_compra'); ?>><?php _e('Orden de Compra', 'modulo-ventas'); ?></option>
                            <option value="guia_despacho" <?php selected($datos['tipo'], 'guia_despacho'); ?>><?php _e('Guía de Despacho', 'modulo-ventas'); ?></option>
                        </select>
                    </div>
                    
                    <div class="mv-field">
                        <label for="plantilla-descripcion"><?php _e('Descripción', 'modulo-ventas'); ?></label>
                        <textarea id="plantilla-descripcion" name="descripcion" rows="3" class="large-text"><?php echo esc_textarea($datos['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="mv-field">
                        <label>
                            <input type="checkbox" id="plantilla-activa" name="activa" value="1" <?php checked($datos['activa'], 1); ?>>
                            <?php _e('Plantilla Activa', 'modulo-ventas'); ?>
                        </label>
                    </div>
                </div>
                
                <!-- Variables disponibles -->
                <div class="mv-panel">
                    <h3><?php _e('Variables Disponibles', 'modulo-ventas'); ?></h3>
                    <p class="description"><?php _e('Haz clic en una variable para insertarla en el editor.', 'modulo-ventas'); ?></p>
                    
                    <div id="variables-disponibles">
                        <div class="mv-loading-variables">
                            <span class="spinner is-active"></span>
                            <?php _e('Cargando variables...', 'modulo-ventas'); ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" id="btn-insertar-variable" class="button button-small">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Ver Todas las Variables', 'modulo-ventas'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Acciones rápidas -->
                <div class="mv-panel">
                    <h3><?php _e('Acciones Rápidas', 'modulo-ventas'); ?></h3>
                    
                    <div class="mv-quick-actions">
                        <button type="button" id="btn-formato-html" class="button button-small">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php _e('Formatear HTML', 'modulo-ventas'); ?>
                        </button>
                        
                        <button type="button" id="btn-formato-css" class="button button-small">
                            <span class="dashicons dashicons-art"></span>
                            <?php _e('Formatear CSS', 'modulo-ventas'); ?>
                        </button>
                        
                        <button type="button" id="btn-css-plantilla" class="button button-small">
                            <span class="dashicons dashicons-admin-appearance"></span>
                            <?php _e('CSS Base', 'modulo-ventas'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Preview de cotización -->
                <div class="mv-panel">
                    <h3><?php _e('Vista Previa', 'modulo-ventas'); ?></h3>
                    
                    <div class="mv-field">
                        <label for="cotizacion-preview"><?php _e('Cotización (opcional)', 'modulo-ventas'); ?></label>
                        <select id="cotizacion-preview">
                            <option value="0"><?php _e('Usar datos de ejemplo', 'modulo-ventas'); ?></option>
                            <?php
                            // TODO: Cargar cotizaciones disponibles
                            ?>
                        </select>
                    </div>
                    
                    <button type="button" id="btn-actualizar-preview" class="button button-small" style="width: 100%;">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Actualizar Preview', 'modulo-ventas'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Panel principal - Editor -->
            <div class="mv-editor-content">
                <!-- Pestañas -->
                <div class="mv-tabs">
                    <button type="button" class="mv-tab-btn active" data-tab="html">
                        <span class="dashicons dashicons-editor-code"></span>
                        HTML
                    </button>
                    <button type="button" class="mv-tab-btn" data-tab="css">
                        <span class="dashicons dashicons-art"></span>
                        CSS
                    </button>
                    <button type="button" class="mv-tab-btn" data-tab="preview">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Vista Previa', 'modulo-ventas'); ?>
                    </button>
                </div>
                
                <!-- Contenido de pestañas -->
                <div class="mv-tab-content" id="tab-html">
                    <div class="mv-editor-header">
                        <h3><?php _e('Editor HTML', 'modulo-ventas'); ?></h3>
                        <p class="description">
                            <?php _e('Utiliza las variables de la barra lateral para personalizar tu plantilla. Ejemplo: {{empresa.nombre}}, {{cliente.nombre}}, etc.', 'modulo-ventas'); ?>
                        </p>
                    </div>
                    
                    <textarea id="html-editor" name="html_content" rows="20" class="large-text code"><?php echo esc_textarea($datos['html_content']); ?></textarea>
                </div>
                
                <div class="mv-tab-content" id="tab-css" style="display: none;">
                    <div class="mv-editor-header">
                        <h3><?php _e('Editor CSS', 'modulo-ventas'); ?></h3>
                        <p class="description">
                            <?php _e('Define los estilos para tu plantilla PDF. Los estilos se aplicarán al generar el documento.', 'modulo-ventas'); ?>
                        </p>
                    </div>
                    
                    <textarea id="css-editor" name="css_content" rows="20" class="large-text code"><?php echo esc_textarea($datos['css_content']); ?></textarea>
                </div>
                
                <div class="mv-tab-content" id="tab-preview" style="display: none;">
                    <div class="mv-editor-header">
                        <h3><?php _e('Vista Previa del Documento', 'modulo-ventas'); ?></h3>
                        <p class="description">
                            <?php _e('Previsualización de cómo se verá el documento PDF generado.', 'modulo-ventas'); ?>
                        </p>
                    </div>
                    
                    <div id="preview-container">
                        <div class="mv-preview-placeholder">
                            <p><?php _e('Haz clic en "Vista Previa" para ver cómo se verá tu plantilla.', 'modulo-ventas'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal de variables -->
<div id="modal-variables" class="mv-modal" style="display: none;">
    <div class="mv-modal-content">
        <div class="mv-modal-header">
            <h3><?php _e('Insertar Variable', 'modulo-ventas'); ?></h3>
            <button type="button" class="mv-modal-close">&times;</button>
        </div>
        
        <div class="mv-modal-body">
            <p><?php _e('Selecciona una variable para insertar en el editor:', 'modulo-ventas'); ?></p>
            <div id="lista-variables-modal">
                <!-- Las variables se cargarán aquí via AJAX -->
            </div>
        </div>
        
        <div class="mv-modal-footer">
            <button type="button" class="button mv-modal-close"><?php _e('Cancelar', 'modulo-ventas'); ?></button>
            <button type="button" id="btn-insertar-variable-modal" class="button button-primary"><?php _e('Insertar', 'modulo-ventas'); ?></button>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para el editor de plantillas */
.mv-editor-plantillas {
    margin-right: 20px;
}

.mv-editor-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.mv-editor-status {
    margin-left: auto;
}

.mv-editor-layout {
    display: flex;
    gap: 20px;
    height: 600px;
}

.mv-editor-sidebar {
    flex: 0 0 300px;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow-y: auto;
}

.mv-editor-content {
    flex: 1;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
}

.mv-panel {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.mv-panel:last-child {
    border-bottom: none;
}

.mv-panel h3 {
    margin: 0 0 15px 0;
    color: #1e1e1e;
    font-size: 14px;
}

.mv-field {
    margin-bottom: 15px;
}

.mv-field:last-child {
    margin-bottom: 0;
}

.mv-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
}

.mv-field input,
.mv-field select,
.mv-field textarea {
    width: 100%;
}

.mv-quick-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.mv-quick-actions .button {
    justify-content: flex-start;
    text-align: left;
}

.mv-tabs {
    display: flex;
    border-bottom: 1px solid #ccd0d4;
}

.mv-tab-btn {
    padding: 12px 20px;
    background: #f6f7f7;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.mv-tab-btn:hover {
    background: #e9ecef;
}

.mv-tab-btn.active {
    background: white;
    border-bottom-color: #0073aa;
    color: #0073aa;
}

.mv-tab-content {
    flex: 1;
    padding: 20px;
    overflow: auto;
}

.mv-editor-header {
    margin-bottom: 15px;
}

.mv-editor-header h3 {
    margin: 0 0 5px 0;
    color: #1e1e1e;
}

.mv-editor-header .description {
    margin: 0;
    color: #646970;
    font-size: 13px;
}

#html-editor,
#css-editor {
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
}

#variables-disponibles {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 10px;
    background: #fafafa;
}

.mv-variable-item {
    padding: 8px;
    margin-bottom: 5px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
}

.mv-variable-item:hover {
    background: #f0f8ff;
    border-color: #0073aa;
}

.mv-variable-item code {
    display: block;
    color: #0073aa;
    font-weight: 600;
    margin-bottom: 3px;
}

.mv-variable-item small {
    color: #666;
    font-size: 11px;
    line-height: 1.3;
}

.mv-loading-variables {
    text-align: center;
    padding: 20px;
    color: #666;
}

.mv-preview-placeholder {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    border: 2px dashed #ddd;
    border-radius: 4px;
}

/* Modal */
.mv-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mv-modal-content {
    background: white;
    border-radius: 4px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    width: 600px;
    max-width: 90vw;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.mv-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mv-modal-header h3 {
    margin: 0;
}

.mv-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.mv-modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.mv-modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Responsive */
@media (max-width: 1200px) {
    .mv-editor-layout {
        flex-direction: column;
    }
    
    .mv-editor-sidebar {
        flex: none;
    }
    
    .mv-editor-content {
        height: 500px;
    }
}

@media (max-width: 782px) {
    .mv-editor-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mv-editor-toolbar .button {
        margin-bottom: 5px;
    }
    
    .mv-tab-btn {
        padding: 10px 15px;
    }
    
    .mv-modal-content {
        width: 95%;
        margin: 2% auto;
    }
}

.preview-data-indicator {
    background: #d4edda;
    color: #155724;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.preview-data-indicator.prueba {
    background: #d1ecf1;
    color: #0c5460;
}

.mv-botones-container button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('Editor plantilla: JavaScript cargado');
    
    // Variables globales
    var usarDatosReales = true;
    var previewTimeout;
    var hasUnsavedChanges = false;
    
    // Definir función mostrarNotificacion si no existe
    if (typeof window.mostrarNotificacion === 'undefined') {
        window.mostrarNotificacion = function(mensaje, tipo) {
            tipo = tipo || 'info';
            
            var claseWordPress = 'notice-info';
            switch(tipo) {
                case 'success':
                    claseWordPress = 'notice-success';
                    break;
                case 'error':
                    claseWordPress = 'notice-error';
                    break;
                case 'warning':
                    claseWordPress = 'notice-warning';
                    break;
            }
            
            var $notice = $('<div class="notice ' + claseWordPress + ' is-dismissible"><p>' + mensaje + '</p></div>');
            
            if ($('.wp-header-end').length) {
                $notice.insertAfter('.wp-header-end');
            } else if ($('.wrap h1').length) {
                $notice.insertAfter('.wrap h1');
            } else {
                $notice.prependTo('.wrap');
            }
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            $('html, body').animate({ scrollTop: 0 }, 500);
        };
    }

    /**
     * Función principal de preview - determina qué tipo usar
     */
    function actualizarPreview() {
        if (previewTimeout) {
            clearTimeout(previewTimeout);
        }
        
        previewTimeout = setTimeout(function() {
            if (usarDatosReales) {
                actualizarPreviewConDatosReales();
            } else {
                actualizarPreviewConDatosPrueba();
            }
        }, 1000);
    }

    /**
     * Preview con datos reales (función actual modificada)
     */
    function actualizarPreviewConDatosReales() {
        var $iframe = $('#preview-iframe');
        var $loading = $('#preview-loading');
        
        // Mostrar loading
        $loading.show();
        $iframe.hide();
        
        // CORREGIDO: Obtener contenido con verificaciones
        var htmlContent = '';
        var cssContent = '';
        
        // Verificar si los editores están disponibles
        if (typeof editorHtml !== 'undefined' && editorHtml && typeof editorHtml.getValue === 'function') {
            htmlContent = editorHtml.getValue();
        } else {
            // Fallback: obtener desde textarea o input
            htmlContent = $('#contenido_html').val() || $('textarea[name="contenido_html"]').val() || '';
        }
        
        if (typeof editorCSS !== 'undefined' && editorCSS && typeof editorCSS.getValue === 'function') {
            cssContent = editorCSS.getValue();
        } else {
            // Fallback: obtener desde textarea o input
            cssContent = $('#contenido_css').val() || $('textarea[name="contenido_css"]').val() || '';
        }
        
        var tipoDocumento = $('#tipo-documento').val() || $('#tipo_documento').val() || 'cotizacion';
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_preview_real',
                html: htmlContent,
                css: cssContent,
                tipo_documento: tipoDocumento,
                nonce: mvPdfTemplates.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Escribir contenido en iframe
                    var iframe = $iframe[0];
                    iframe.contentWindow.document.open();
                    iframe.contentWindow.document.write(response.data.html);
                    iframe.contentWindow.document.close();
                    
                    // Mostrar mensaje de tipo de datos
                    if (response.data.datos_usados) {
                        mostrarInfoPreview(response.data.datos_usados);
                    }
                } else {
                    console.error('Error en preview real:', response.data.message);
                    mostrarErrorPreview('Error al cargar datos reales: ' + response.data.message);
                    // Fallback automático a datos de prueba
                    setTimeout(function() {
                        usarDatosReales = false;
                        $('#usar-datos-reales').prop('checked', false);
                        actualizarPreviewConDatosPrueba();
                    }, 1000);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX en preview real:', error);
                mostrarErrorPreview('Error de conexión al obtener datos reales');
                // Fallback automático a datos de prueba
                setTimeout(function() {
                    usarDatosReales = false;
                    $('#usar-datos-reales').prop('checked', false);
                    actualizarPreviewConDatosPrueba();
                }, 1000);
            },
            complete: function() {
                $loading.hide();
                $iframe.show();
                
                // Marcar como guardado si hay cambios
                if (typeof hasUnsavedChanges !== 'undefined' && hasUnsavedChanges) {
                    marcarComoGuardado();
                }
            }
        });
    }

    /**
     * NUEVA FUNCIÓN: Preview con datos de prueba (funcionalidad original)
     */
    function actualizarPreviewConDatosPrueba() {
        var $iframe = $('#preview-iframe');
        var $loading = $('#preview-loading');
        
        // Mostrar loading
        $loading.show();
        $iframe.hide();
        
        // CORREGIDO: Obtener contenido con verificaciones
        var htmlContent = '';
        var cssContent = '';
        
        // Verificar si los editores están disponibles
        if (typeof editorHtml !== 'undefined' && editorHtml && typeof editorHtml.getValue === 'function') {
            htmlContent = editorHtml.getValue();
        } else {
            // Fallback: obtener desde textarea o input
            htmlContent = $('#contenido_html').val() || $('textarea[name="contenido_html"]').val() || '';
        }
        
        if (typeof editorCSS !== 'undefined' && editorCSS && typeof editorCSS.getValue === 'function') {
            cssContent = editorCSS.getValue();
        } else {
            // Fallback: obtener desde textarea o input
            cssContent = $('#contenido_css').val() || $('textarea[name="contenido_css"]').val() || '';
        }
        
        var tipoDocumento = $('#tipo-documento').val() || $('#tipo_documento').val() || 'cotizacion';
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_preview',  // Acción ORIGINAL para datos de prueba
                html: htmlContent,
                css: cssContent,
                tipo_documento: tipoDocumento,
                nonce: mvPdfTemplates.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Escribir contenido en iframe
                    var iframe = $iframe[0];
                    iframe.contentWindow.document.open();
                    iframe.contentWindow.document.write(response.data.html);
                    iframe.contentWindow.document.close();
                    
                    // Mostrar mensaje indicando datos de prueba
                    mostrarInfoPreview({
                        tipo: 'prueba',
                        mensaje: 'Preview generado con datos de prueba'
                    });
                } else {
                    console.error('Error en preview de prueba:', response.data.message);
                    mostrarErrorPreview('Error al generar preview: ' + (response.data.message || 'Error desconocido'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX en preview de prueba:', error);
                mostrarErrorPreview('Error de conexión al generar preview');
            },
            complete: function() {
                $loading.hide();
                $iframe.show();
                
                // Marcar como guardado si hay cambios
                if (typeof hasUnsavedChanges !== 'undefined' && hasUnsavedChanges) {
                    marcarComoGuardado();
                }
            }
        });
    }

    /**
     * Mostrar información del preview
     */
    function mostrarInfoPreview(datosUsados) {
        var $info = $('#preview-info');
        if ($info.length === 0) {
            $info = $('<div id="preview-info" class="mv-preview-info"></div>');
            $('#preview-container').prepend($info);
        }
        
        var iconClass = datosUsados.tipo === 'real' ? 'dashicons-yes-alt' : 'dashicons-info';
        var colorClass = datosUsados.tipo === 'real' ? 'success' : 'info';
        
        $info.html(
            '<span class="dashicons ' + iconClass + '"></span> ' +
            '<strong>Preview:</strong> ' + datosUsados.mensaje
        ).attr('class', 'mv-preview-info ' + colorClass).show();
    }

    /**
     * Mostrar error en el preview
     */
    function mostrarErrorPreview(mensaje) {
        var $info = $('#preview-info');
        if ($info.length === 0) {
            $info = $('<div id="preview-info" class="mv-preview-info"></div>');
            $('#preview-container').prepend($info);
        }
        
        $info.html(
            '<span class="dashicons dashicons-warning"></span> ' +
            '<strong>Error:</strong> ' + mensaje
        ).attr('class', 'mv-preview-info error').show();
    }

    var mvPdfTemplates = {
        ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('mv_pdf_templates_nonce'); ?>',
        i18n: {
            guardando: '<?php _e('Guardando...', 'modulo-ventas'); ?>',
            guardado: '<?php _e('Guardado', 'modulo-ventas'); ?>',
            error: '<?php _e('Error', 'modulo-ventas'); ?>',
            cargando: '<?php _e('Cargando...', 'modulo-ventas'); ?>'
        }
    };

    // Variables para prevenir múltiples actualizaciones
    var previewTimeout;
    var hasUnsavedChanges = false;

    // Debug: Verificar nonce
    console.log('mvPdfTemplates nonce:', mvPdfTemplates.nonce);
    console.log('mvPdfTemplates ajaxurl:', mvPdfTemplates.ajaxurl);
        
    // Preview sincronizado con mPDF - CORREGIDO
    $(document).on('click', '#preview-sincronizado-mpdf', function(e) {
        e.preventDefault();
        
        // Obtener ID de plantilla de diferentes formas posibles
        var plantillaId = $('#plantilla_id').val() || 
                        $('#plantilla-id').val() || 
                        $('input[name="plantilla_id"]').val() ||
                        $('input[name="id"]').val() ||
                        getUrlParameter('plantilla_id');
        
        console.log('Plantilla ID obtenido:', plantillaId);
        
        if (!plantillaId) {
            mostrarNotificacion('Error: No se pudo obtener el ID de la plantilla', 'error');
            console.error('Campos de ID encontrados:', {
                'plantilla_id': $('#plantilla_id').val(),
                'plantilla-id': $('#plantilla-id').val(),
                'input_plantilla_id': $('input[name="plantilla_id"]').val(),
                'input_id': $('input[name="id"]').val(),
                'url_param': getUrlParameter('plantilla_id')
            });
            return;
        }
        
        var $btn = $(this);
        var textoOriginal = $btn.text();
        
        $btn.text('🔄 Generando preview sincronizado...').prop('disabled', true);
        
        // Obtener nonce
        var nonce = '<?php echo wp_create_nonce("mv_nonce"); ?>';
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'mv_preview_mpdf_sincronizado',
                plantilla_id: plantillaId,
                nonce: nonce
            },
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                
                if (response.success) {
                    // Abrir preview en nueva ventana
                    var ventana = window.open('', 'preview-mpdf-sync', 'width=900,height=700,scrollbars=yes,resizable=yes');
                    if (ventana) {
                        ventana.document.write(response.data.html);
                        ventana.document.close();
                        mostrarNotificacion('Preview sincronizado con mPDF generado exitosamente', 'success');
                    } else {
                        mostrarNotificacion('Error: No se pudo abrir la ventana de preview. Verifica que los popups estén habilitados.', 'warning');
                    }
                } else {
                    mostrarNotificacion('Error: ' + (response.data.message || 'Error desconocido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', {xhr: xhr, status: status, error: error});
                mostrarNotificacion('Error de conexión: ' + error, 'error');
            },
            complete: function() {
                $btn.text(textoOriginal).prop('disabled', false);
            }
        });
    });

    /**
     * Agregar toggle para alternar entre datos reales y de prueba
     */
    function agregarToggleTipoDatos() {
        // Verificar si ya existe el toggle
        if ($('#usar-datos-reales').length > 0) {
            return;
        }
        
        var toggleHtml = 
            '<div class="mv-preview-controls" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">' +
                '<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">' +
                    '<input type="checkbox" id="usar-datos-reales" ' + (usarDatosReales ? 'checked' : '') + '> ' +
                    '<span class="dashicons dashicons-database" style="margin-top: 3px;"></span>' +
                    '<strong>Usar datos reales</strong> ' +
                    '<small style="color: #666;">(última cotización creada)</small>' +
                '</label>' +
            '</div>';
        
        $('#preview-container').prepend(toggleHtml);
        
        // Manejar cambio en el toggle
        $(document).on('change', '#usar-datos-reales', function() {
            usarDatosReales = $(this).is(':checked');
            
            // Actualizar preview inmediatamente
            actualizarPreview();
            
            // Mostrar feedback visual
            if (usarDatosReales) {
                $(this).closest('label').addClass('active');
            } else {
                $(this).closest('label').removeClass('active');
            }
        });
    }

    /**
     * Agregar estilos CSS para los controles
     */
    function agregarEstilosPreview() {
        if ($('#mv-preview-styles').length > 0) {
            return; // Ya se agregaron los estilos
        }
        
        var estilos = 
            '<style id="mv-preview-styles">' +
            '.mv-preview-info { ' +
                'padding: 8px 12px; ' +
                'margin-bottom: 10px; ' +
                'border-radius: 4px; ' +
                'font-size: 13px; ' +
                'display: none; ' +
            '} ' +
            '.mv-preview-info.success { ' +
                'background: #d4edda; ' +
                'color: #155724; ' +
                'border: 1px solid #c3e6cb; ' +
            '} ' +
            '.mv-preview-info.info { ' +
                'background: #d1ecf1; ' +
                'color: #0c5460; ' +
                'border: 1px solid #bee5eb; ' +
            '} ' +
            '.mv-preview-info.error { ' +
                'background: #f8d7da; ' +
                'color: #721c24; ' +
                'border: 1px solid #f5c6cb; ' +
            '} ' +
            '.mv-preview-controls label.active { ' +
                'color: #0073aa; ' +
                'font-weight: 600; ' +
            '} ' +
            '</style>';
        
        $('head').append(estilos);
    }
    
    // Test de comparación visual
    $(document).on('click', '#test-comparison', function(e) {
        e.preventDefault();
        var ventana = window.open('/wp-admin/admin-ajax.php?action=mv_test_mpdf_visual_comparison', 'test-comparison', 'width=1000,height=800,scrollbars=yes,resizable=yes');
        if (!ventana) {
            mostrarNotificacion('Error: No se pudo abrir la ventana de test. Verifica que los popups estén habilitados.', 'warning');
        }
    });
    
    // Función auxiliar para obtener parámetros de URL
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    // Función auxiliar para marcar como guardado (si existe)
    function marcarComoGuardado() {
        if (typeof window.marcarComoGuardado === 'function') {
            window.marcarComoGuardado();
        }
        hasUnsavedChanges = false;
    }
    
    // Inicialización
    function inicializarEditor() {
        // Agregar estilos y controles existentes
        agregarEstilosPreview();
        agregarToggleTipoDatos();
        
        // Nuevas funcionalidades
        agregarEstilosAnimacion();
        inicializarControlesPreview();
        
        // CORREGIDO: Inicializar con verificaciones
        setTimeout(function() {
            // Verificar que los elementos necesarios existan antes de actualizar
            if ($('#preview-iframe').length > 0) {
                actualizarPreview();
            }
        }, 500);
    }
    
    // Inicializar cuando el documento esté listo
    inicializarEditor();
    
    // Debug: Mostrar información del editor
    console.log('Información del editor:', {
        'plantilla_id disponible': $('#plantilla_id').length > 0,
        'plantilla-id disponible': $('#plantilla-id').length > 0,
        'input plantilla_id': $('input[name="plantilla_id"]').length > 0,
        'ajaxurl': typeof ajaxurl !== 'undefined' ? ajaxurl : 'NO DEFINIDO',
        'URL actual': window.location.href,
        'usarDatosReales': usarDatosReales
    });

    // Variable global para el estado de datos reales en todos los previews
    var usarDatosRealesGlobal = true;

    /**
     * Inicializar controles de preview con datos reales
     */
    function inicializarControlesPreview() {
        // Manejar toggle global
        $(document).on('change', '#usar-datos-reales-global', function() {
            usarDatosRealesGlobal = $(this).is(':checked');
            
            // Actualizar indicadores en botones
            actualizarIndicadoresBotones();
            
            // Actualizar estado del toggle del preview en tiempo real
            $('#usar-datos-reales').prop('checked', usarDatosRealesGlobal);
            usarDatosReales = usarDatosRealesGlobal;
            
            // Actualizar preview inmediatamente
            actualizarPreview();
            
            // Mostrar información del estado
            actualizarInfoEstadoDatos();
        });
        
        // Sincronizar con el toggle del preview en tiempo real
        $(document).on('change', '#usar-datos-reales', function() {
            var nuevoEstado = $(this).is(':checked');
            $('#usar-datos-reales-global').prop('checked', nuevoEstado);
            usarDatosRealesGlobal = nuevoEstado;
            actualizarIndicadoresBotones();
            actualizarInfoEstadoDatos();
        });
        
        // Inicializar indicadores
        actualizarIndicadoresBotones();
        actualizarInfoEstadoDatos();
    }

    /**
     * Actualizar indicadores visuales en los botones
     */
    function actualizarIndicadoresBotones() {
        $('.preview-data-indicator').each(function() {
            var $indicator = $(this);
            if (usarDatosRealesGlobal) {
                $indicator.text('(datos reales)')
                        .removeClass('prueba')
                        .css({
                            'background': '#d4edda',
                            'color': '#155724'
                        });
            } else {
                $indicator.text('(datos de prueba)')
                        .addClass('prueba')
                        .css({
                            'background': '#d1ecf1',
                            'color': '#0c5460'
                        });
            }
        });
        
        // Actualizar atributo data en botones
        $('button[data-usar-reales]').attr('data-usar-reales', usarDatosRealesGlobal);
    }

    /**
     * Actualizar información del estado de datos
     */
    function actualizarInfoEstadoDatos() {
        var mensaje = usarDatosRealesGlobal 
            ? 'Todos los previews usarán datos de la última cotización real'
            : 'Todos los previews usarán datos de prueba generados automáticamente';
            
        $('#estado-datos-preview').text(mensaje);
        
        var $infoContainer = $('#info-datos-preview');
        if (usarDatosRealesGlobal) {
            $infoContainer.html('<span style="color: #155724;">✓ Usando datos reales de la última cotización</span>');
        } else {
            $infoContainer.html('<span style="color: #0c5460;">ℹ Usando datos de prueba predefinidos</span>');
        }
    }

    /**
     * Botón Vista Previa en Nueva Ventana - ACTUALIZADO
     */
    $(document).on('click', '#vista-previa-ventana', function(e) {
        e.preventDefault();
        
        // Obtener ID de plantilla
        var plantillaId = obtenerPlantillaId();
        
        if (!plantillaId) {
            mostrarNotificacion('Error: No se pudo obtener el ID de la plantilla', 'error');
            return;
        }
        
        var $btn = $(this);
        var textoOriginal = $btn.html();
        
        $btn.html('<span class="dashicons dashicons-update-alt spin"></span> Generando vista previa...').prop('disabled', true);
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            method: 'POST',
            data: {
                action: 'mv_vista_previa_datos_reales',
                plantilla_id: plantillaId,
                usar_datos_reales: usarDatosRealesGlobal,
                nonce: mvPdfTemplates.nonce  // CORREGIDO: usar el mismo nonce
            },
            success: function(response) {
                console.log('Respuesta vista previa:', response);
                
                if (response.success) {
                    // Abrir preview en nueva ventana
                    var ventana = window.open('', 'vista-previa-plantilla', 'width=900,height=700,scrollbars=yes,resizable=yes');
                    if (ventana) {
                        ventana.document.write(response.data.html);
                        ventana.document.close();
                        
                        var tipoMsg = response.data.tipo_datos === 'real' ? 'datos reales' : 'datos de prueba';
                        mostrarNotificacion('Vista previa generada exitosamente con ' + tipoMsg, 'success');
                    } else {
                        mostrarNotificacion('Error: No se pudo abrir la ventana de preview. Verifica que los popups estén habilitados.', 'warning');
                    }
                } else {
                    mostrarNotificacion('Error: ' + (response.data.message || 'Error desconocido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX vista previa:', {xhr: xhr, status: status, error: error});
                mostrarNotificacion('Error de conexión: ' + error, 'error');
            },
            complete: function() {
                $btn.html(textoOriginal).prop('disabled', false);
            }
        });
    });

    /**
     * Preview Sincronizado con mPDF - ACTUALIZADO
     */
    $(document).on('click', '#preview-sincronizado-mpdf', function(e) {
        e.preventDefault();
        
        var plantillaId = obtenerPlantillaId();
        
        if (!plantillaId) {
            mostrarNotificacion('Error: No se pudo obtener el ID de la plantilla', 'error');
            return;
        }
        
        var $btn = $(this);
        var textoOriginal = $btn.html();
        
        $btn.html('<span class="dashicons dashicons-update-alt spin"></span> Generando preview mPDF...').prop('disabled', true);
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,  // CORREGIDO: usar la misma URL
            method: 'POST',
            data: {
                action: 'mv_preview_mpdf_sincronizado_datos_reales',
                plantilla_id: plantillaId,
                usar_datos_reales: usarDatosRealesGlobal,
                nonce: mvPdfTemplates.nonce  // CORREGIDO: usar el mismo nonce
            },
            success: function(response) {
                console.log('Respuesta preview mPDF:', response);
                
                if (response.success) {
                    // Abrir preview en nueva ventana
                    var ventana = window.open('', 'preview-mpdf-sync', 'width=900,height=700,scrollbars=yes,resizable=yes');
                    if (ventana) {
                        ventana.document.write(response.data.html);
                        ventana.document.close();
                        
                        var tipoMsg = response.data.tipo_datos === 'real' ? 'datos reales' : 'datos de prueba';
                        mostrarNotificacion('Preview mPDF generado exitosamente con ' + tipoMsg, 'success');
                    } else {
                        mostrarNotificacion('Error: No se pudo abrir la ventana de preview. Verifica que los popups estén habilitados.', 'warning');
                    }
                } else {
                    mostrarNotificacion('Error: ' + (response.data.message || 'Error desconocido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX preview mPDF:', {xhr: xhr, status: status, error: error});
                mostrarNotificacion('Error de conexión: ' + error, 'error');
            },
            complete: function() {
                $btn.html(textoOriginal).prop('disabled', false);
            }
        });
    });

    /**
     * Función auxiliar para obtener ID de plantilla
     */
    function obtenerPlantillaId() {
        return $('#plantilla_id').val() || 
            $('#plantilla-id').val() || 
            $('input[name="plantilla_id"]').val() ||
            $('input[name="id"]').val() ||
            getUrlParameter('plantilla_id');
    }

    /**
     * Agregar CSS para animación de carga
     */
    function agregarEstilosAnimacion() {
        if ($('#mv-animation-styles').length > 0) {
            return;
        }
        
        var estilos = 
            '<style id="mv-animation-styles">' +
            '.spin { animation: spin 1s linear infinite; } ' +
            '@keyframes spin { ' +
                '0% { transform: rotate(0deg); } ' +
                '100% { transform: rotate(360deg); } ' +
            '} ' +
            '.mv-botones-preview button { ' +
                'transition: all 0.3s ease; ' +
            '} ' +
            '.mv-botones-preview button:hover:not(:disabled) { ' +
                'transform: translateY(-1px); ' +
                'box-shadow: 0 2px 8px rgba(0,0,0,0.15); ' +
            '} ' +
            '</style>';
        
        $('head').append(estilos);
    }
});
</script>

<?php