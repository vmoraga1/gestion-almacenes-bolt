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
        
        <button type="button" id="btn-preview-plantilla" class="button">
            <span class="dashicons dashicons-visibility"></span>
            <?php _e('Vista Previa', 'modulo-ventas'); ?>
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
</style>

<script>
/*jQuery(document).ready(function($) {
    console.log('Editor de plantillas PDF cargado');
    
    // Variables globales
    var htmlEditor, cssEditor;
    var plantillaId = $('#plantilla-id').val();
    var tipoActual = $('#plantilla-tipo').val();
    
    // Inicializar editores de código
    initCodeEditors();
    
    // Cargar variables disponibles
    cargarVariablesDisponibles();
    
    // Event handlers
    setupEventHandlers();
    
    /**
     * Inicializar editores CodeMirror
     *
    function initCodeEditors() {
        // Editor HTML
        var htmlSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
        htmlSettings.codemirror = _.extend({}, htmlSettings.codemirror, {
            mode: 'htmlmixed',
            lineNumbers: true,
            lineWrapping: true,
            autoCloseTags: true,
            autoCloseBrackets: true,
            matchBrackets: true,
            theme: 'default',
            extraKeys: {
                'Ctrl-Space': 'autocomplete',
                'F11': function(cm) {
                    cm.setOption('fullScreen', !cm.getOption('fullScreen'));
                },
                'Esc': function(cm) {
                    if (cm.getOption('fullScreen')) cm.setOption('fullScreen', false);
                }
            }
        });
        
        htmlEditor = wp.codeEditor.initialize($('#html-editor'), htmlSettings);
        
        // Editor CSS
        var cssSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
        cssSettings.codemirror = _.extend({}, cssSettings.codemirror, {
            mode: 'css',
            lineNumbers: true,
            lineWrapping: true,
            autoCloseBrackets: true,
            matchBrackets: true,
            theme: 'default'
        });
        
        cssEditor = wp.codeEditor.initialize($('#css-editor'), cssSettings);
        
        console.log('Editores CodeMirror inicializados');
    }
    
    /**
     * Configurar event handlers
     *
    function setupEventHandlers() {
        // Navegación entre pestañas
        $('.mv-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            cambiarTab(tab);
        });
        
        // Guardar plantilla
        $('#btn-guardar-plantilla').on('click', guardarPlantilla);
        
        // Vista previa
        $('#btn-preview-plantilla, #btn-actualizar-preview').on('click', generarPreview);
        
        // Cambio de tipo de documento
        $('#plantilla-tipo').on('change', function() {
            tipoActual = $(this).val();
            cargarVariablesDisponibles();
        });
        
        // Modal de variables
        $('#btn-insertar-variable').on('click', abrirModalVariables);
        $('.mv-modal-close').on('click', cerrarModalVariables);
        $('#btn-insertar-variable-modal').on('click', insertarVariableSeleccionada);
        
        // Clic en variables del sidebar
        $(document).on('click', '.mv-variable-item', function() {
            var variable = $(this).data('variable');
            insertarVariable(variable);
        });
        
        // Formatear código
        $('#btn-formato-html').on('click', function() {
            if (htmlEditor) {
                var formatted = html_beautify(htmlEditor.codemirror.getValue());
                htmlEditor.codemirror.setValue(formatted);
            }
        });
        
        $('#btn-formato-css').on('click', function() {
            if (cssEditor) {
                var formatted = css_beautify(cssEditor.codemirror.getValue());
                cssEditor.codemirror.setValue(formatted);
            }
        });
        
        // Auto-guardado cada 30 segundos (opcional)
        setInterval(function() {
            if ($('#plantilla-nombre').val().trim()) {
                guardarPlantilla(true); // Guardado silencioso
            }
        }, 30000);
        
        console.log('Event handlers configurados');
    }
    
    /**
     * Cargar variables disponibles
     *
    function cargarVariablesDisponibles() {
        $('#variables-disponibles').html('<div class="mv-loading-variables"><span class="spinner is-active"></span> Cargando variables...</div>');
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_variables',
                tipo_documento: tipoActual,
                nonce: mvPdfTemplates.nonce
            },
            success: function(response) {
                if (response.success) {
                    mostrarVariablesDisponibles(response.data);
                } else {
                    $('#variables-disponibles').html('<p>Error cargando variables</p>');
                }
            },
            error: function() {
                $('#variables-disponibles').html('<p>Error de conexión</p>');
            }
        });
    }
    
    /**
     * Mostrar variables en el sidebar
     *
    function mostrarVariablesDisponibles(variables) {
        var html = '';
        
        $.each(variables, function(categoria, vars) {
            html += '<h4 style="margin: 15px 0 8px 0; color: #2271b1; font-size: 12px; text-transform: uppercase;">' + categoria + '</h4>';
            
            $.each(vars, function(i, variable) {
                html += '<div class="mv-variable-item" data-variable="' + variable.variable + '">';
                html += '<code>{{' + variable.variable + '}}</code>';
                html += '<small>' + variable.descripcion + '</small>';
                html += '</div>';
            });
        });
        
        $('#variables-disponibles').html(html);
    }
    
    console.log('Sistema de plantillas PDF - Editor inicializado');
});*/
</script>