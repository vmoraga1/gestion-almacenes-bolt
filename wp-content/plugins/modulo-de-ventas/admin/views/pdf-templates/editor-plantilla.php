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

    <!-- Vista Previa Mejorada - CON TOGGLE -->
    <div class="mv-panel">
        <h3 style="display: flex; align-items: center; gap: 8px;">
            <span class="dashicons dashicons-visibility" style="color: #0073aa;"></span>
            <?php _e('Vista Previa de Plantilla', 'modulo-ventas'); ?>
        </h3>
        
        <!-- Controles Unificados CON TOGGLE -->
        <div class="mv-preview-controls" style="margin-bottom: 15px; padding: 15px; background: #fff; border-radius: 4px; border-left: 4px solid #0073aa;">
            
            <!-- TOGGLE PARA TIPO DE DATOS - AGREGADO -->
            <div style="margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; margin-bottom: 8px;">
                    <input type="checkbox" id="usar-datos-reales" checked> 
                    <span class="dashicons dashicons-database" style="color: #0073aa;"></span>
                    <span>Usar datos de cotizaciones reales</span>
                    <small style="color: #666; margin-left: 10px;">(última cotización creada)</small>
                </label>
                <div id="info-tipo-datos" style="margin-top: 8px; font-size: 12px; padding: 8px; border-radius: 3px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                    <span id="mensaje-tipo-datos">✓ Mostrando datos de la última cotización real</span>
                </div>
            </div>
            
            <!-- Botones de Acción -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                
                <!-- Actualizar Preview -->
                <button type="button" 
                        id="actualizar-preview" 
                        class="button button-primary"
                        style="display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-update"></span>
                    Actualizar Preview
                </button>
                
                <!-- Abrir en Nueva Ventana -->
                <button type="button" 
                        id="vista-previa-nueva-ventana" 
                        class="button button-secondary"
                        style="display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-external"></span>
                    Abrir en Nueva Ventana
                </button>
                
                <!-- Generar PDF -->
                <button type="button" 
                        id="generar-pdf-preview" 
                        class="button button-secondary"
                        style="display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-pdf"></span>
                    Generar PDF
                </button>
                
                <!-- Botón de Debug CSS (temporal) -->
                <button type="button" 
                        id="debug-css-preview" 
                        class="button button-link"
                        style="display: flex; align-items: center; gap: 8px; color: #666;">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Debug CSS
                </button>
            </div>
        </div>
        
        <!-- Área de Preview -->
        <div id="preview-container" style="border: 1px solid #ccc; border-radius: 4px; min-height: 400px; background: #fff; position: relative;">
            
            <!-- Estado de Carga -->
            <div id="preview-loading" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 10;">
                <span class="dashicons dashicons-update-alt spin" style="font-size: 24px; color: #0073aa;"></span>
                <p style="margin-top: 10px; color: #666;">Generando vista previa...</p>
            </div>
            
            <!-- Contenido del Preview -->
            <iframe id="preview-iframe" 
                    style="width: 100%; height: 600px; border: none; border-radius: 4px;"
                    srcdoc="<div style='padding: 40px; text-align: center; color: #666; font-family: Arial, sans-serif;'>
                        <span class='dashicons dashicons-visibility' style='font-size: 48px; color: #ddd; display: block; margin-bottom: 20px;'></span>
                        <h3>Vista Previa de Plantilla</h3>
                        <p>Haz clic en 'Actualizar Preview' para ver la plantilla renderizada</p>
                        <p><small>El CSS se aplicará correctamente en esta vista</small></p>
                    </div>">
            </iframe>
            
            <!-- Indicador de Tipo de Datos -->
            <div id="preview-data-badge" 
                style="position: absolute; top: 10px; right: 10px; padding: 5px 10px; background: rgba(0,123,0,0.8); color: white; border-radius: 3px; font-size: 12px; z-index: 20;">
                <span id="badge-text">Datos reales</span>
            </div>
            
            <!-- Indicador de CSS -->
            <div id="css-status-badge" 
                style="position: absolute; top: 10px; left: 10px; padding: 5px 10px; background: rgba(0,0,255,0.8); color: white; border-radius: 3px; font-size: 12px; z-index: 20;">
                <span id="css-status-text">CSS: ✓</span>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div id="preview-info" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px; display: none;">
            <div id="preview-info-content"></div>
        </div>
    </div>

    <!-- Sección de botones de preview actualizada --
    <div class="mv-botones-preview" style="margin: 20px 0; padding: 15px; background: #f1f1f1; border-radius: 8px;">
        <h3 style="margin-bottom: 15px;">
            <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
            Opciones de Preview
        </h3>-->
        
        <!-- Toggle global para todos los previews --
        <div class="mv-preview-mode-control" style="margin-bottom: 15px; padding: 10px; background: #fff; border-radius: 4px; border-left: 4px solid #0073aa;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
                <input type="checkbox" id="usar-datos-reales-global" checked> 
                <span class="dashicons dashicons-database" style="color: #0073aa;"></span>
                <span>Usar datos reales en todos los previews</span>
                <small style="color: #666; margin-left: 10px;">(última cotización creada)</small>
            </label>
            <div id="info-datos-preview" style="margin-top: 8px; font-size: 12px; color: #666;"></div>
        </div>-->

        <!-- Botones de preview --
        <div class="mv-botones-container" style="display: flex; gap: 10px; flex-wrap: wrap;">
            
            <!- Vista Previa en Nueva Ventana --
            <button type="button" 
                    id="vista-previa-ventana" 
                    class="button button-secondary"
                    data-usar-reales="true"
                    style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-external"></span>
                Vista Previa
                <small class="preview-data-indicator">(datos reales)</small>
            </button>

            <!- Preview Sincronizado con mPDF --
            <button type="button" 
                    id="preview-sincronizado-mpdf" 
                    class="button button-secondary"
                    data-usar-reales="true"
                    style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-pdf"></span>
                Preview Sincronizado mPDF
                <small class="preview-data-indicator">(datos reales)</small>
            </button>

            <!- Test de Comparación Visual --
            <button type="button" 
                    id="test-comparison" 
                    class="button button-secondary"
                    style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-analytics"></span>
                Test Comparación Visual
            </button>
        </div>

        <!- Información sobre el estado actual --
        <div class="mv-preview-info" style="margin-top: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px; font-size: 13px;">
            <span class="dashicons dashicons-info"></span>
            <strong>Estado actual:</strong> 
            <span id="estado-datos-preview">Todos los previews usarán datos de la última cotización real</span>
        </div>
    </div>-->

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
    height: auto;
}

.mv-editor-sidebar {
    flex: initial;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow-y: visible;
}

.mv-editor-content {
    flex: 1;
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    height: 1280px !important;
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
    overflow: clip;
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
    
    // Variables globales
    var usarDatosReales = true;
    var previewActualizandose = false;
    var cambioTimeout;
    
    /**
     * Función mejorada para escribir HTML en iframe
     */
    function escribirHtmlEnIframe(htmlContent) {
        var iframe = document.getElementById('preview-iframe');
        
        if (!iframe) {
            console.error('❌ Iframe no encontrado');
            return false;
        }
        
        // Método 1: Usar srcdoc (preferido)
        try {
            iframe.srcdoc = htmlContent;
            console.log('✅ HTML cargado usando srcdoc');
            return true;
        } catch (e) {
            console.log('⚠️ srcdoc falló, intentando método alternativo');
        }
        
        // Método 2: Escribir directamente en el documento del iframe
        try {
            var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(htmlContent);
            iframeDoc.close();
            console.log('✅ HTML cargado usando write()');
            return true;
        } catch (e) {
            console.error('❌ Error escribiendo en iframe:', e);
            return false;
        }
    }
    
    /**
     * Validar y limpiar HTML antes de cargar en iframe
     */
    function validarHtmlParaIframe(htmlResponse) {
        console.log('🔍 Validando HTML recibido...');
        
        // Verificar que es HTML válido
        if (!htmlResponse || typeof htmlResponse !== 'string') {
            console.error('❌ Respuesta no es una cadena válida');
            return null;
        }
        
        // Verificar estructura básica
        var tieneDoctype = htmlResponse.toLowerCase().indexOf('<!doctype') !== -1;
        var tieneHtml = htmlResponse.toLowerCase().indexOf('<html') !== -1;
        var tieneHead = htmlResponse.toLowerCase().indexOf('<head>') !== -1;
        var tieneBody = htmlResponse.toLowerCase().indexOf('<body>') !== -1;
        var tieneStyle = htmlResponse.toLowerCase().indexOf('<style>') !== -1;
        
        console.log('📋 Estructura del HTML:', {
            doctype: tieneDoctype,
            html: tieneHtml,
            head: tieneHead,
            body: tieneBody,
            style: tieneStyle,
            longitud: htmlResponse.length
        });
        
        // Si no tiene estructura básica, envolver
        if (!tieneHtml || !tieneHead || !tieneBody) {
            console.log('🔧 HTML incompleto, envolviendo...');
            
            var htmlLimpio = htmlResponse;
            
            // Si es solo contenido del body, envolver correctamente
            if (!tieneHtml) {
                htmlLimpio = `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista Previa</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 8px; border: 1px solid #ddd; }
        .documento { max-width: 800px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="documento">
        ${htmlResponse}
    </div>
</body>
</html>`;
            }
            
            return htmlLimpio;
        }
        
        return htmlResponse;
    }
    
    /**
     * NUEVA: Combinar HTML y CSS para asegurar que los estilos se apliquen
     */
    function combinarHtmlYCss(htmlContent, cssContent) {
        console.log('🎨 Combinando HTML y CSS...');
        
        // Si no hay CSS, devolver HTML tal como está
        if (!cssContent || cssContent.trim() === '') {
            console.log('⚠️ No hay CSS para combinar');
            return htmlContent;
        }
        
        // Si el HTML ya tiene estilos, no duplicar
        if (htmlContent.indexOf('<style>') !== -1) {
            console.log('ℹ️ HTML ya contiene estilos, no agregando CSS adicional');
            return htmlContent;
        }
        
        var htmlFinal = htmlContent;
        
        // Buscar donde insertar el CSS
        var headEnd = htmlFinal.indexOf('</head>');
        if (headEnd !== -1) {
            // Insertar antes del cierre de head
            var cssTag = '\n<style type="text/css">\n' + cssContent + '\n</style>\n';
            htmlFinal = htmlFinal.substring(0, headEnd) + cssTag + htmlFinal.substring(headEnd);
            console.log('✅ CSS insertado en <head>');
        } else {
            // Si no hay head, buscar body y agregar al inicio
            var bodyStart = htmlFinal.indexOf('<body>');
            if (bodyStart !== -1) {
                var cssTag = '\n<style type="text/css">\n' + cssContent + '\n</style>\n';
                htmlFinal = htmlFinal.substring(0, bodyStart + 6) + cssTag + htmlFinal.substring(bodyStart + 6);
                console.log('✅ CSS insertado al inicio de <body>');
            } else {
                // Como último recurso, agregar al inicio del documento
                var cssTag = '<style type="text/css">\n' + cssContent + '\n</style>\n';
                htmlFinal = cssTag + htmlFinal;
                console.log('✅ CSS insertado al inicio del documento');
            }
        }
        
        return htmlFinal;
    }
    
    /**
     * Actualizar vista previa - VERSIÓN CORREGIDA CON CSS
     */
    function actualizarPreview() {
        if (previewActualizandose) {
            console.log('⏳ Preview ya actualizándose, ignorando solicitud');
            return;
        }
        
        var plantillaId = obtenerPlantillaId();
        
        if (!plantillaId) {
            mostrarError('No se pudo obtener el ID de la plantilla');
            return;
        }
        
        previewActualizandose = true;
        mostrarCargando(true);
        
        console.log('🔄 Iniciando actualización de preview:', {
            plantillaId: plantillaId,
            usarDatosReales: usarDatosReales
        });
        
        // Obtener contenido actual de los editores
        var htmlContent = '';
        var cssContent = '';
        
        // Intentar múltiples métodos para obtener el contenido HTML
        if (typeof window.codemirrorHTML !== 'undefined' && window.codemirrorHTML) {
            htmlContent = window.codemirrorHTML.getValue();
        } else if (typeof editorHtml !== 'undefined' && editorHtml && typeof editorHtml.getValue === 'function') {
            htmlContent = editorHtml.getValue();
        } else if ($('#html-editor').length > 0) {
            htmlContent = $('#html-editor').val();
        } else if ($('#contenido_html').length > 0) {
            htmlContent = $('#contenido_html').val();
        } else if ($('textarea[name="contenido_html"]').length > 0) {
            htmlContent = $('textarea[name="contenido_html"]').val();
        }
        
        // Intentar múltiples métodos para obtener el contenido CSS
        if (typeof window.codemirrorCSS !== 'undefined' && window.codemirrorCSS) {
            cssContent = window.codemirrorCSS.getValue();
        } else if (typeof editorCSS !== 'undefined' && editorCSS && typeof editorCSS.getValue === 'function') {
            cssContent = editorCSS.getValue();
        } else if ($('#css-editor').length > 0) {
            cssContent = $('#css-editor').val();
        } else if ($('#contenido_css').length > 0) {
            cssContent = $('#contenido_css').val();
        } else if ($('textarea[name="contenido_css"]').length > 0) {
            cssContent = $('textarea[name="contenido_css"]').val();
        }
        
        console.log('📝 Contenido capturado:', {
            htmlLength: htmlContent.length,
            cssLength: cssContent.length,
            htmlPreview: htmlContent.substring(0, 100) + '...',
            cssPreview: cssContent.substring(0, 100) + '...'
        });
        
        var datosAjax = {
            action: 'mv_vista_previa_datos_reales',
            plantilla_id: plantillaId,
            usar_datos_reales: usarDatosReales,
            nonce: obtenerNonce()
        };
        
        // Agregar contenido del editor
        if (htmlContent.trim()) {
            datosAjax.html_content = htmlContent;
        }
        if (cssContent.trim()) {
            datosAjax.css_content = cssContent;
        }
        
        console.log('📤 Enviando petición AJAX...');
        
        $.ajax({
            url: obtenerAjaxUrl(),
            method: 'POST',
            data: datosAjax,
            timeout: 30000,
            success: function(response) {
                console.log('📥 Respuesta AJAX recibida:', {
                    success: response.success,
                    hasData: !!response.data,
                    hasHtml: !!(response.data && response.data.html),
                    htmlLength: response.data && response.data.html ? response.data.html.length : 0
                });
                
                if (response.success && response.data && response.data.html) {
                    var htmlRecibido = response.data.html;
                    
                    // NUEVO: Combinar con CSS si es necesario
                    var htmlConCss = combinarHtmlYCss(htmlRecibido, cssContent);
                    
                    // Validar HTML final
                    var htmlValidado = validarHtmlParaIframe(htmlConCss);
                    
                    if (htmlValidado) {
                        // Verificar procesamiento de variables
                        var tieneVariablesSinProcesar = htmlValidado.indexOf('{{') !== -1;
                        if (tieneVariablesSinProcesar) {
                            console.warn('⚠️ Se detectaron variables sin procesar en el HTML');
                            var variablesEncontradas = htmlValidado.match(/\{\{[^}]+\}\}/g);
                            console.log('Variables sin procesar:', variablesEncontradas);
                        }
                        
                        // Cargar en iframe
                        var cargaExitosa = escribirHtmlEnIframe(htmlValidado);
                        
                        if (cargaExitosa) {
                            setTimeout(function() {
                                var iframe = document.getElementById('preview-iframe');
                                if (iframe && iframe.contentWindow) {
                                    try {
                                        // Forzar re-renderizado
                                        iframe.contentWindow.document.body.style.display = 'none';
                                        iframe.contentWindow.document.body.offsetHeight; // trigger reflow
                                        iframe.contentWindow.document.body.style.display = '';
                                        
                                        console.log('🔄 Forzado re-renderizado del iframe');
                                    } catch (e) {
                                        console.log('⚠️ No se pudo forzar re-renderizado:', e);
                                    }
                                }
                            }, 100);
                            
                            // Verificar si los estilos se aplicaron
                            var tieneCss = htmlValidado.indexOf('<style>') !== -1;
                            console.log('🎨 CSS detectado en HTML final:', tieneCss);
                            
                            // Actualizar indicadores visuales si existen
                            if ($('#css-status-text').length > 0) {
                                $('#css-status-text').text('CSS: ' + (tieneCss ? '✓' : '❌'));
                            }
                            if ($('#css-status-badge').length > 0) {
                                $('#css-status-badge').css('background', tieneCss ? 'rgba(0,128,0,0.8)' : 'rgba(255,0,0,0.8)');
                            }
                            
                            // Mostrar información de éxito
                            var mensaje = response.data.mensaje || 'Preview actualizado correctamente';
                            if (tieneVariablesSinProcesar) {
                                mensaje += ' (⚠ Variables sin procesar detectadas)';
                            }
                            if (!tieneCss) {
                                mensaje += ' (⚠ CSS no detectado en HTML final)';
                            } else {
                                mensaje += ' (✓ CSS aplicado)';
                            }
                            
                            mostrarInfoPreview(mensaje, 'success');
                            console.log('✅ Preview actualizado exitosamente');
                            
                        } else {
                            mostrarError('Error cargando HTML en iframe');
                        }
                    } else {
                        mostrarError('HTML recibido no es válido');
                    }
                } else {
                    var errorMsg = 'Error en la respuesta del servidor';
                    if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                    
                    console.error('❌ Error en respuesta:', response);
                    mostrarError(errorMsg);
                    
                    // Mostrar debug si está disponible
                    if (response.data && response.data.debug) {
                        console.log('🐛 Debug info:', response.data.debug);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ Error AJAX completo:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'Sin respuesta',
                    statusCode: xhr.status
                });
                
                var errorMsg = 'Error de conexión: ';
                if (xhr.status === 0) {
                    errorMsg += 'Sin conexión al servidor';
                } else if (xhr.status === 403) {
                    errorMsg += 'Sin permisos (403)';
                } else if (xhr.status === 404) {
                    errorMsg += 'Acción no encontrada (404)';
                } else if (xhr.status === 500) {
                    errorMsg += 'Error interno del servidor (500)';
                    // Mostrar parte de la respuesta para debug
                    if (xhr.responseText) {
                        console.log('📄 Respuesta del servidor (500):', xhr.responseText.substring(0, 1000));
                    }
                } else {
                    errorMsg += error + ' (' + xhr.status + ')';
                }
                
                mostrarError(errorMsg);
                
                // Mostrar mensaje específico en el iframe
                var errorHtml = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 0; padding: 40px; background: #f8f9fa; }
                            .error-container { 
                                text-align: center; 
                                color: #721c24; 
                                background: #f8d7da; 
                                border: 1px solid #f5c6cb; 
                                border-radius: 5px; 
                                padding: 40px; 
                                max-width: 500px; 
                                margin: 0 auto; 
                            }
                            h3 { margin-top: 0; color: #721c24; }
                            p { margin: 10px 0; }
                            small { color: #6c757d; }
                        </style>
                    </head>
                    <body>
                        <div class="error-container">
                            <h3>❌ Error cargando vista previa</h3>
                            <p><strong>Error:</strong> ${errorMsg}</p>
                            <p><small>Revisa la consola del navegador (F12) para más detalles</small></p>
                        </div>
                    </body>
                    </html>
                `;
                escribirHtmlEnIframe(errorHtml);
            },
            complete: function() {
                previewActualizandose = false;
                mostrarCargando(false);
                console.log('🏁 Petición AJAX completada');
            }
        });
    }
    
    /**
     * Función de test para verificar iframe
     */
    function testIframe() {
        var htmlTest = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Test del iframe</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        margin: 20px; 
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        min-height: 400px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .test { 
                        background: rgba(255,255,255,0.1);
                        border: 2px solid rgba(255,255,255,0.3); 
                        padding: 30px; 
                        border-radius: 15px; 
                        text-align: center;
                        backdrop-filter: blur(10px);
                        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
                    }
                    h1 { 
                        color: #fff; 
                        margin-top: 0;
                        font-size: 2em;
                        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                    }
                    p { 
                        font-size: 1.1em; 
                        line-height: 1.6;
                        margin: 15px 0;
                    }
                    .success { 
                        color: #90EE90; 
                        font-weight: bold; 
                    }
                    .time {
                        background: rgba(255,255,255,0.2);
                        padding: 10px;
                        border-radius: 8px;
                        margin-top: 20px;
                        font-family: monospace;
                    }
                </style>
            </head>
            <body>
                <div class="test">
                    <h1>🧪 Test del iframe</h1>
                    <p class="success">✅ Si puedes ver este mensaje con estilos, el iframe funciona correctamente.</p>
                    <p>🎨 Los estilos CSS se están aplicando apropiadamente.</p>
                    <p>📱 El iframe puede cargar HTML completo con CSS embebido.</p>
                    <div class="time">
                        <strong>⏰ Hora de test:</strong> ${new Date().toLocaleString()}
                    </div>
                </div>
            </body>
            </html>
        `;
        
        var cargaExitosa = escribirHtmlEnIframe(htmlTest);
        if (cargaExitosa) {
            mostrarInfoPreview('🧪 Test del iframe ejecutado exitosamente', 'success');
        } else {
            mostrarError('❌ Error ejecutando test del iframe');
        }
        console.log('🧪 Test HTML cargado en iframe');
    }
    
    /**
     * NUEVA: Función para actualizar preview con debounce en cambios del editor
     */
    function programarActualizacionPreview() {
        if (cambioTimeout) {
            clearTimeout(cambioTimeout);
        }
        
        cambioTimeout = setTimeout(function() {
            console.log('⏰ Auto-actualizando preview por cambio en editor...');
            actualizarPreview();
        }, 2000); // Esperar 2 segundos después del último cambio
    }
    
    // Funciones auxiliares (mantenidas del código original)
    function obtenerNonce() {
        var nonce = '';
        if ($('input[name="nonce"]').length > 0) {
            nonce = $('input[name="nonce"]').val();
        } else if (typeof mvPdfTemplates !== 'undefined' && mvPdfTemplates.nonce) {
            nonce = mvPdfTemplates.nonce;
        } else {
            nonce = '<?php echo wp_create_nonce("mv_pdf_templates_nonce"); ?>';
        }
        return nonce;
    }
    
    function obtenerAjaxUrl() {
        if (typeof mvPdfTemplates !== 'undefined' && mvPdfTemplates.ajaxurl) {
            return mvPdfTemplates.ajaxurl;
        } else if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        } else {
            return '<?php echo admin_url("admin-ajax.php"); ?>';
        }
    }
    
    function obtenerPlantillaId() {
        return $('#plantilla-id').val() || 
               $('input[name="id"]').val() || 
               $('#plantilla_id').val() ||
               $('input[name="plantilla_id"]').val() ||
               new URLSearchParams(window.location.search).get('plantilla_id') ||
               (typeof plantillaId !== 'undefined' ? plantillaId : null);
    }
    
    function mostrarCargando(mostrar) {
        if (mostrar) {
            $('#preview-loading').show();
            if ($('#actualizar-preview').length > 0) {
                $('#actualizar-preview').prop('disabled', true)
                    .html('<span class="dashicons dashicons-update-alt spin"></span> Actualizando...');
            }
        } else {
            $('#preview-loading').hide();
            if ($('#actualizar-preview').length > 0) {
                $('#actualizar-preview').prop('disabled', false)
                    .html('<span class="dashicons dashicons-update"></span> Actualizar Preview');
            }
        }
    }
    
    function mostrarInfoPreview(mensaje, tipo) {
        tipo = tipo || 'info';
        
        var $info = $('#preview-info');
        var $content = $('#preview-info-content');
        
        // Si no existe el contenedor, crearlo
        if ($info.length === 0) {
            $info = $('<div id="preview-info" style="display: none;"><div id="preview-info-content"></div></div>');
            if ($('#preview-container').length > 0) {
                $('#preview-container').prepend($info);
            } else {
                $('.wrap').append($info);
            }
            $content = $('#preview-info-content');
        }
        
        var clases = {
            'success': 'background: #d4edda; color: #155724; border-left: 4px solid #28a745;',
            'error': 'background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545;',
            'warning': 'background: #fff3cd; color: #856404; border-left: 4px solid #ffc107;',
            'info': 'background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8;'
        };
        
        var iconos = {
            'success': 'yes',
            'error': 'no',
            'warning': 'warning',
            'info': 'info'
        };
        
        $content.html('<span class="dashicons dashicons-' + iconos[tipo] + '"></span> ' + mensaje);
        
        $info.attr('style', 'margin: 10px 0; padding: 12px; border-radius: 4px; font-size: 14px; ' + 
                           (clases[tipo] || clases['info']) + ' display: block;');
        
        $info.show();
        
        // Auto-ocultar mensajes de éxito después de 8 segundos
        if (tipo === 'success') {
            setTimeout(function() {
                $info.fadeOut();
            }, 8000);
        }
    }
    
    function mostrarError(mensaje) {
        mostrarInfoPreview(mensaje, 'error');
    }
    
    function inicializarPreview() {
        // Manejar cambio en el toggle de datos reales/prueba
        $(document).on('change', '#usar-datos-reales', function() {
            usarDatosReales = $(this).is(':checked');
            actualizarIndicadores();
            
            console.log('🔄 Tipo de datos cambiado a:', usarDatosReales ? 'reales' : 'prueba');
            
            // Auto-actualizar preview si hay contenido cargado
            var iframe = document.getElementById('preview-iframe');
            if (iframe && (iframe.srcdoc || iframe.src)) {
                setTimeout(actualizarPreview, 500);
            }
        });
        
        // Manejar botones
        $(document).on('click', '#actualizar-preview', function(e) {
            e.preventDefault();
            actualizarPreview();
        });
        
        $(document).on('click', '#test-iframe', function(e) {
            e.preventDefault();
            testIframe();
        });
        
        // NUEVO: Detectar cambios en editores CodeMirror para auto-actualizar
        setTimeout(function() {
            // Para HTML
            if (typeof window.codemirrorHTML !== 'undefined' && window.codemirrorHTML) {
                window.codemirrorHTML.on('change', programarActualizacionPreview);
                console.log('📝 Auto-actualización conectada al editor HTML');
            } else if (typeof editorHtml !== 'undefined' && editorHtml && editorHtml.on) {
                editorHtml.on('change', programarActualizacionPreview);
                console.log('📝 Auto-actualización conectada al editor HTML (alternativo)');
            }
            
            // Para CSS
            if (typeof window.codemirrorCSS !== 'undefined' && window.codemirrorCSS) {
                window.codemirrorCSS.on('change', programarActualizacionPreview);
                console.log('🎨 Auto-actualización conectada al editor CSS');
            } else if (typeof editorCSS !== 'undefined' && editorCSS && editorCSS.on) {
                editorCSS.on('change', programarActualizacionPreview);
                console.log('🎨 Auto-actualización conectada al editor CSS (alternativo)');
            }
        }, 1000);
        
        // Actualizar indicadores iniciales
        actualizarIndicadores();
        
        console.log('✅ Sistema de preview con CSS mejorado inicializado');
    }
    
    function actualizarIndicadores() {
        var $infoContainer = $('#info-tipo-datos');
        var $badge = $('#badge-text');
        var $mensaje = $('#mensaje-tipo-datos');
        
        if ($infoContainer.length > 0) {
            if (usarDatosReales) {
                $infoContainer.css({
                    'background': '#d4edda',
                    'color': '#155724',
                    'border': '1px solid #c3e6cb'
                });
                if ($mensaje.length > 0) {
                    $mensaje.text('✓ Mostrando datos de la última cotización real');
                }
                if ($badge.length > 0) {
                    $badge.text('Datos reales').parent().css('background', 'rgba(0,123,0,0.8)');
                }
            } else {
                $infoContainer.css({
                    'background': '#d1ecf1', 
                    'color': '#0c5460',
                    'border': '1px solid #bee5eb'
                });
                if ($mensaje.length > 0) {
                    $mensaje.text('ℹ Mostrando datos de prueba predefinidos');
                }
                if ($badge.length > 0) {
                    $badge.text('Datos de prueba').parent().css('background', 'rgba(255,193,7,0.8)');
                }
            }
        }
    }
    
    // Inicializar el sistema
    inicializarPreview();
    
    // Agregar botón de test si no existe
    setTimeout(function() {
        if ($('#test-iframe').length === 0 && $('#actualizar-preview').length > 0) {
            $('#actualizar-preview').after(
                '<button type="button" id="test-iframe" class="button button-secondary" style="margin-left: 10px;" title="Probar funcionamiento del iframe">' +
                '<span class="dashicons dashicons-admin-tools"></span> Test iframe' +
                '</button>'
            );
        }
    }, 500);
    
    // Exponer funciones para debugging
    window.mvPreviewDebug = {
        actualizarPreview: actualizarPreview,
        testIframe: testIframe,
        escribirHtmlEnIframe: escribirHtmlEnIframe,
        validarHtmlParaIframe: validarHtmlParaIframe,
        combinarHtmlYCss: combinarHtmlYCss,
        programarActualizacionPreview: programarActualizacionPreview,
        usarDatosReales: function() { return usarDatosReales; },
        estado: function() {
            return {
                usarDatosReales: usarDatosReales,
                previewActualizandose: previewActualizandose,
                plantillaId: obtenerPlantillaId()
            };
        }
    };
    
    console.log('🔧 Debug del preview disponible en: window.mvPreviewDebug');
    console.log('ℹ️ Usa window.mvPreviewDebug.estado() para ver el estado actual');
});
</script>

<?php