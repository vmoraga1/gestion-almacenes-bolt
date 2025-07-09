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
        
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-pdf-templates'); ?>" class="button">
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
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="plantilla-nombre"><?php _e('Nombre', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="plantilla-nombre" 
                                       name="nombre" 
                                       value="<?php echo esc_attr($datos['nombre']); ?>" 
                                       class="regular-text"
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="plantilla-tipo"><?php _e('Tipo de Documento', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <select id="plantilla-tipo" name="tipo" required>
                                    <option value="cotizacion" <?php selected($datos['tipo'], 'cotizacion'); ?>><?php _e('Cotización', 'modulo-ventas'); ?></option>
                                    <option value="venta" <?php selected($datos['tipo'], 'venta'); ?>><?php _e('Venta', 'modulo-ventas'); ?></option>
                                    <option value="pedido" <?php selected($datos['tipo'], 'pedido'); ?>><?php _e('Pedido', 'modulo-ventas'); ?></option>
                                    <option value="factura" <?php selected($datos['tipo'], 'factura'); ?>><?php _e('Factura', 'modulo-ventas'); ?></option>
                                    <option value="general" <?php selected($datos['tipo'], 'general'); ?>><?php _e('General', 'modulo-ventas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="plantilla-descripcion"><?php _e('Descripción', 'modulo-ventas'); ?></label>
                            </th>
                            <td>
                                <textarea id="plantilla-descripcion" 
                                          name="descripcion" 
                                          class="large-text" 
                                          rows="3"><?php echo esc_textarea($datos['descripcion']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Estado', 'modulo-ventas'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           id="plantilla-activa" 
                                           name="activa" 
                                           value="1" 
                                           <?php checked($datos['activa'], 1); ?>>
                                    <?php _e('Plantilla activa', 'modulo-ventas'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Variables disponibles -->
                <div class="mv-panel">
                    <h3><?php _e('Variables Disponibles', 'modulo-ventas'); ?></h3>
                    <div id="variables-disponibles">
                        <div class="mv-loading-variables">
                            <span class="spinner is-active"></span>
                            <?php _e('Cargando variables...', 'modulo-ventas'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Ayuda rápida -->
                <div class="mv-panel">
                    <h3><?php _e('Ayuda Rápida', 'modulo-ventas'); ?></h3>
                    <div class="mv-help-content">
                        <h4><?php _e('Sintaxis de Variables', 'modulo-ventas'); ?></h4>
                        <code>{{variable}}</code>
                        
                        <h4><?php _e('Condicionales', 'modulo-ventas'); ?></h4>
                        <code>{{#if variable}}...{{/if}}</code>
                        
                        <h4><?php _e('Bucles', 'modulo-ventas'); ?></h4>
                        <code>{{#each productos}}...{{/each}}</code>
                        
                        <h4><?php _e('Formato de Fechas', 'modulo-ventas'); ?></h4>
                        <code>{{fecha_formato 'DD/MM/YYYY'}}</code>
                        
                        <h4><?php _e('Formato de Números', 'modulo-ventas'); ?></h4>
                        <code>{{numero_formato 2}}</code>
                    </div>
                </div>
            </div>
            
            <!-- Panel principal - Editores -->
            <div class="mv-editor-main">
                
                <!-- Pestañas del editor -->
                <div class="mv-editor-tabs">
                    <button type="button" class="mv-tab-btn active" data-tab="html">
                        <span class="dashicons dashicons-media-code"></span>
                        <?php _e('HTML', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="mv-tab-btn" data-tab="css">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <?php _e('CSS', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="mv-tab-btn" data-tab="preview">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Vista Previa', 'modulo-ventas'); ?>
                    </button>
                </div>
                
                <!-- Contenido de las pestañas -->
                <div class="mv-editor-content">
                    
                    <!-- Editor HTML -->
                    <div id="tab-html" class="mv-tab-content active">
                        <div class="mv-editor-header">
                            <h4><?php _e('Contenido HTML', 'modulo-ventas'); ?></h4>
                            <div class="mv-editor-controls">
                                <button type="button" class="button button-small" id="btn-formato-html">
                                    <?php _e('Formatear Código', 'modulo-ventas'); ?>
                                </button>
                                <button type="button" class="button button-small" id="btn-insertar-variable">
                                    <?php _e('Insertar Variable', 'modulo-ventas'); ?>
                                </button>
                            </div>
                        </div>
                        <textarea id="html-editor" 
                                  name="html_content" 
                                  class="mv-code-editor"><?php echo esc_textarea($datos['html_content']); ?></textarea>
                    </div>
                    
                    <!-- Editor CSS -->
                    <div id="tab-css" class="mv-tab-content" style="display: none;">
                        <div class="mv-editor-header">
                            <h4><?php _e('Estilos CSS', 'modulo-ventas'); ?></h4>
                            <div class="mv-editor-controls">
                                <button type="button" class="button button-small" id="btn-formato-css">
                                    <?php _e('Formatear Código', 'modulo-ventas'); ?>
                                </button>
                                <button type="button" class="button button-small" id="btn-css-plantilla">
                                    <?php _e('CSS Base', 'modulo-ventas'); ?>
                                </button>
                            </div>
                        </div>
                        <textarea id="css-editor" 
                                  name="css_content" 
                                  class="mv-code-editor"><?php echo esc_textarea($datos['css_content']); ?></textarea>
                    </div>
                    
                    <!-- Vista Previa -->
                    <div id="tab-preview" class="mv-tab-content" style="display: none;">
                        <div class="mv-editor-header">
                            <h4><?php _e('Vista Previa del PDF', 'modulo-ventas'); ?></h4>
                            <div class="mv-editor-controls">
                                <select id="cotizacion-preview">
                                    <option value="0"><?php _e('Usar datos de ejemplo', 'modulo-ventas'); ?></option>
                                    <?php
                                    // Obtener algunas cotizaciones para preview
                                    global $wpdb;
                                    $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
                                    $cotizaciones = $wpdb->get_results(
                                        "SELECT id, folio FROM $tabla_cotizaciones 
                                         WHERE estado != 'eliminada' 
                                         ORDER BY fecha DESC 
                                         LIMIT 10"
                                    );
                                    foreach ($cotizaciones as $cot) {
                                        echo '<option value="' . $cot->id . '">' . esc_html($cot->folio) . '</option>';
                                    }
                                    ?>
                                </select>
                                <button type="button" class="button" id="btn-actualizar-preview">
                                    <?php _e('Actualizar Preview', 'modulo-ventas'); ?>
                                </button>
                            </div>
                        </div>
                        <div id="preview-container">
                            <div class="mv-preview-placeholder">
                                <p><?php _e('Haz clic en "Actualizar Preview" para ver la vista previa', 'modulo-ventas'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal para seleccionar variables -->
<div id="modal-variables" class="mv-modal" style="display: none;">
    <div class="mv-modal-content">
        <div class="mv-modal-header">
            <h3><?php _e('Insertar Variable', 'modulo-ventas'); ?></h3>
            <button type="button" class="mv-modal-close">&times;</button>
        </div>
        <div class="mv-modal-body">
            <div id="lista-variables-modal">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
        <div class="mv-modal-footer">
            <button type="button" class="button button-primary" id="btn-insertar-variable-modal">
                <?php _e('Insertar', 'modulo-ventas'); ?>
            </button>
            <button type="button" class="button mv-modal-close">
                <?php _e('Cancelar', 'modulo-ventas'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Estilos para el editor de plantillas */
.mv-editor-plantillas {
    max-width: none;
}

.mv-editor-toolbar {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.mv-editor-status {
    margin-left: auto;
}

.mv-editor-layout {
    display: flex;
    gap: 20px;
    min-height: 600px;
}

.mv-editor-sidebar {
    flex: 0 0 300px;
    background: #fff;
}

.mv-editor-main {
    flex: 1;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.mv-panel {
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
}

.mv-panel h3 {
    background: #f9f9f9;
    border-bottom: 1px solid #ddd;
    margin: 0;
    padding: 12px 15px;
    font-size: 14px;
}

.mv-panel .form-table {
    margin: 0;
    padding: 15px;
}

.mv-panel .form-table th {
    width: 80px;
    padding: 8px 0;
}

.mv-panel .form-table td {
    padding: 8px 0;
}

#variables-disponibles {
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.mv-variable-item {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
    margin-bottom: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.mv-variable-item:hover {
    background-color: #f0f8ff;
}

.mv-variable-item code {
    display: block;
    font-weight: bold;
    color: #0073aa;
}

.mv-variable-item small {
    color: #666;
    font-size: 11px;
}

.mv-help-content {
    padding: 15px;
    font-size: 12px;
}

.mv-help-content h4 {
    margin: 15px 0 5px 0;
    font-size: 12px;
}

.mv-help-content code {
    display: block;
    background: #f0f0f0;
    padding: 3px 6px;
    border-radius: 3px;
    margin-bottom: 10px;
}

.mv-editor-tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
}

.mv-tab-btn {
    border: none;
    background: none;
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.mv-tab-btn:hover {
    background: #f0f0f0;
}

.mv-tab-btn.active {
    background: #fff;
    border-bottom-color: #2271b1;
    color: #2271b1;
}

.mv-editor-content {
    position: relative;
    height: calc(100vh - 200px);
    min-height: 500px;
}

.mv-tab-content {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.mv-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
}

.mv-editor-header h4 {
    margin: 0;
    font-size: 14px;
}

.mv-editor-controls {
    display: flex;
    gap: 5px;
}

.mv-code-editor {
    flex: 1;
    border: none;
    resize: none;
    outline: none;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.5;
}

#preview-container {
    flex: 1;
    padding: 20px;
    overflow: auto;
}

.mv-preview-placeholder {
    text-align: center;
    padding: 100px 20px;
    color: #666;
}

.mv-loading-variables {
    text-align: center;
    padding: 20px;
    color: #666;
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
}

.mv-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 80%;
    max-width: 600px;
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
jQuery(document).ready(function($) {
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
     */
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
     */
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
     */
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
     */
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
});
</script>