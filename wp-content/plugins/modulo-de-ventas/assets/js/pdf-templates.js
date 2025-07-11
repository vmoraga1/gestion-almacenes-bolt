/**
 * JAVASCRIPT PARA EDITOR DE PLANTILLAS PDF - ACTUALIZADO
 */

jQuery(document).ready(function($) {
    'use strict';
        
    // Variables globales - CORREGIDAS para ser accesibles
    var htmlEditor, cssEditor;
    var plantillaId = $('#plantilla-id').val();
    var tipoActual = $('#plantilla-tipo').val();
    var variableSeleccionada = '';
    var cambiosSinGuardar = false;
    var previewWindow = null;
    var validacionTimeout = null;
    
    // Hacer variables y funciones globalmente accesibles
    window.mvPdfEditor = {
        htmlEditor: null,
        cssEditor: null,
        plantillaId: plantillaId,
        tipoActual: tipoActual,
        cambiosSinGuardar: false
    };
    
    // Funciones globales para debugging
    window.guardarPlantilla = guardarPlantilla;
    window.generarPreview = generarPreview;
    window.mvDebugEditor = function() {
        console.log('Estado del editor:', {
            htmlEditor: htmlEditor,
            cssEditor: cssEditor,
            plantillaId: plantillaId,
            tipoActual: tipoActual,
            cambiosSinGuardar: cambiosSinGuardar
        });
    };
    
    // Verificar que mvPdfTemplates esté disponible
    if (typeof mvPdfTemplates === 'undefined') {
        console.error('mvPdfTemplates no está definido');
        return;
    }
    
    // Inicializar solo si estamos en la página del editor
    if ($('.mv-editor-plantillas').length > 0) {
        inicializarEditor();
    }
    
    // Inicializar funcionalidades de la lista
    if ($('.mv-plantillas-table').length > 0) {
        inicializarLista();
    }
    
    /**
     * Inicializar editor de plantillas
     */
    function inicializarEditor() {        
        // Esperar a que CodeMirror esté disponible
        setTimeout(function() {
            initCodeEditors();
            cargarVariablesDisponibles();
            setupEventHandlers();
            cargarCotizacionesParaPreview();
        }, 100);
    }
    
    /**
     * Inicializar funcionalidades de la lista
     */
    function inicializarLista() {
        // Toggle de estado de plantillas
        $('.plantilla-toggle-estado').on('change', function() {
            var plantillaId = $(this).data('plantilla-id');
            var activa = $(this).is(':checked');
            
            cambiarEstadoPlantilla(plantillaId, activa);
        });
        
        // Duplicar plantilla
        $('.mv-duplicar-plantilla').on('click', function() {
            var plantillaId = $(this).data('plantilla-id');
            duplicarPlantilla(plantillaId);
        });
        
        // Eliminar plantilla
        $('.mv-eliminar-plantilla').on('click', function() {
            var plantillaId = $(this).data('plantilla-id');
            
            if (confirm(mvPdfTemplates.i18n.confirmar_eliminar)) {
                eliminarPlantilla(plantillaId);
            }
        });
        
        // Preview de plantilla
        $('.mv-preview-plantilla').on('click', function() {
            var plantillaId = $(this).data('plantilla-id');
            previewPlantillaExistente(plantillaId);
        });
    }
    
    /**
     * Inicializar editores CodeMirror
     */
    function initCodeEditors() {
        if (typeof wp === 'undefined' || !wp.codeEditor) {
            console.error('CodeMirror no disponible');
            return;
        }
        
        try {
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
            window.mvPdfEditor.htmlEditor = htmlEditor;
            
            // Editor CSS
            var cssSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
            cssSettings.codemirror = _.extend({}, cssSettings.codemirror, {
                mode: 'css',
                lineNumbers: true,
                lineWrapping: true,
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
            
            cssEditor = wp.codeEditor.initialize($('#css-editor'), cssSettings);
            window.mvPdfEditor.cssEditor = cssEditor;
            
            // Event listeners para detectar cambios
            if (htmlEditor && htmlEditor.codemirror) {
                htmlEditor.codemirror.on('change', function() {
                    marcarCambiosSinGuardar();
                    validarPlantillaConDelay();
                });
            }
            
            if (cssEditor && cssEditor.codemirror) {
                cssEditor.codemirror.on('change', function() {
                    marcarCambiosSinGuardar();
                    validarPlantillaConDelay();
                });
            }
                        
        } catch (error) {
            console.error('Error inicializando editores:', error);
        }
    }
    
    /**
     * Configurar event handlers - CORREGIDOS
     */
    function setupEventHandlers() {
        // Tabs del editor
        $('.mv-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            cambiarTab(tab);
        });
        
        // Botón guardar - CORREGIDO selector
        $('#btn-guardar-plantilla').on('click', function(e) {
            e.preventDefault();
            guardarPlantilla();
        });
        
        // Botón preview - CORREGIDO selector
        $('#btn-preview-plantilla').on('click', function(e) {
            e.preventDefault();
            generarPreview();
        });
        
        // Botón actualizar preview
        $('#btn-actualizar-preview').on('click', function(e) {
            e.preventDefault();
            generarPreview();
        });
        
        // Botón cargar plantilla predeterminada
        $('#cargar-plantilla-base').on('click', function(e) {
            e.preventDefault();
            cargarPlantillaPredeterminada();
        });
        
        // Formatear código - CORREGIDOS selectores
        $('#btn-formato-html').on('click', function(e) {
            e.preventDefault();
            formatearHtml();
        });
        
        $('#btn-formato-css').on('click', function(e) {
            e.preventDefault();
            formatearCss();
        });
        
        // Insertar CSS base
        $('#btn-css-plantilla').on('click', function(e) {
            e.preventDefault();
            insertarCssBase();
        });
        
        // Variables disponibles
        $(document).on('click', '.mv-variable-item', function() {
            var variable = $(this).data('variable');
            insertarVariable(variable);
        });
        
        // Modal de variables - CORREGIDOS selectores
        $('#btn-insertar-variable').on('click', function(e) {
            e.preventDefault();
            abrirModalVariables();
        });
        
        $('.mv-modal-close').on('click', function() {
            cerrarModalVariables();
        });
        
        $('#btn-insertar-variable-modal').on('click', function() {
            insertarVariableSeleccionada();
        });
        
        // Validar al cargar
        setTimeout(function() {
            if (htmlEditor || cssEditor) {
                validarPlantilla();
            }
        }, 1000);
        
        // Detectar cambios en campos del formulario
        $('#plantilla-nombre, #plantilla-descripcion, #plantilla-tipo').on('change input', function() {
            marcarCambiosSinGuardar();
        });
        
        // Preview con cotización específica
        $('#cotizacion-preview').on('change', function() {
            if ($(this).val()) {
                generarPreview($(this).val());
            }
        });
        
        // Advertir antes de salir con cambios sin guardar
        $(window).on('beforeunload', function() {
            if (cambiosSinGuardar) {
                return 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
            }
        });
    }
    
    /**
     * Cargar variables disponibles - CORREGIDO
     */
    function cargarVariablesDisponibles() {
        $('#variables-disponibles').html('<div class="mv-loading-variables"><span class="spinner is-active"></span> ' + (mvPdfTemplates.i18n.cargando || 'Cargando...') + '</div>');
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_variables',
                nonce: mvPdfTemplates.nonce,
                tipo_documento: tipoActual // CORREGIDO nombre del parámetro
            },
            success: function(response) {
                if (response.success) {
                    mostrarVariablesDisponibles(response.data);
                } else {
                    $('#variables-disponibles').html('<p>Error: ' + (response.data.message || 'No se pudieron cargar las variables') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('PDF Templates: Error cargando variables:', error);
                $('#variables-disponibles').html('<p>Error de conexión</p>');
            }
        });
    }
    
    /**
     * Cargar cotizaciones para preview
     */
    function cargarCotizacionesParaPreview() {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_cotizaciones_preview',
                nonce: mvPdfTemplates.nonce
            },
            success: function(response) {
                if (response.success && response.data.cotizaciones) {
                    var select = $('#cotizacion-preview');
                    select.empty().append('<option value="">Datos de prueba</option>');
                    
                    $.each(response.data.cotizaciones, function(i, cot) {
                        var texto = cot.folio + ' - ' + (cot.cliente_nombre || 'Sin cliente') + ' ($' + Number(cot.total).toLocaleString() + ')';                                   new Intl.NumberFormat('es-CL').format(cot.total) + ')';
                        select.append('<option value="' + cot.id + '">' + texto + '</option>');
                    });
                }
            }
        });
    }
    
    /**
     * Mostrar variables disponibles en el sidebar - CORREGIDO
     */
    function mostrarVariablesDisponibles(variables) {
        var $container = $('#variables-disponibles');
        $container.empty();
        
        if (!variables || Object.keys(variables).length === 0) {
            $container.html('<p>No hay variables disponibles</p>');
            return;
        }
        
        $.each(variables, function(grupo, vars) {
            $container.append('<h4 style="margin: 15px 0 8px 0; color: #2271b1; font-size: 12px; text-transform: uppercase;">' + grupo + '</h4>');
            
            if (Array.isArray(vars)) {
                $.each(vars, function(i, variable) {
                    var $item = $('<div class="mv-variable-item" data-variable="' + variable.variable + '" title="' + variable.descripcion + '">' +
                                 '<code>{{' + variable.variable + '}}</code>' +
                                 '<small>' + variable.descripcion + '</small>' +
                                 '</div>');
                    $container.append($item);
                });
            }
        });
    }
    
    /**
     * Cambiar pestaña del editor
     */
    function cambiarTab(tab) {
        $('.mv-tab-btn').removeClass('active');
        $('.mv-tab-content').hide();
        
        $('[data-tab="' + tab + '"]').addClass('active');
        $('#tab-' + tab).show();
        
        // Refrescar editor al cambiar tab
        setTimeout(function() {
            if (tab === 'html' && htmlEditor && htmlEditor.codemirror) {
                htmlEditor.codemirror.refresh();
            } else if (tab === 'css' && cssEditor && cssEditor.codemirror) {
                cssEditor.codemirror.refresh();
            }
        }, 100);
    }
    
    /**
     * Marcar cambios sin guardar
     */
    function marcarCambiosSinGuardar() {
        cambiosSinGuardar = true;
        window.mvPdfEditor.cambiosSinGuardar = true;
        
        // Actualizar indicador visual si existe
        if ($('#estado-cambios').length) {
            $('#estado-cambios').text('Cambios sin guardar').removeClass('guardado').addClass('sin-guardar');
        }
        
        // Habilitar botón guardar si existe
        $('#btn-guardar-plantilla').prop('disabled', false);
    }
    
    /**
     * Marcar como guardado
     */
    function marcarComoGuardado() {
        cambiosSinGuardar = false;
        window.mvPdfEditor.cambiosSinGuardar = false;
        
        if ($('#estado-cambios').length) {
            $('#estado-cambios').text('Guardado').removeClass('sin-guardar').addClass('guardado');
        }
    }
    
    /**
     * Guardar plantilla - FUNCIÓN PRINCIPAL CORREGIDA
     */
    function guardarPlantilla() {        
        var $boton = $('#btn-guardar-plantilla');
        var textoOriginal = $boton.text();
        
        // Validar campos requeridos
        var nombre = $('#plantilla-nombre').val().trim();
        if (!nombre) {
            mostrarNotificacion('El nombre de la plantilla es requerido', 'error');
            $('#plantilla-nombre').focus();
            return;
        }
        
        $boton.prop('disabled', true).text(mvPdfTemplates.i18n.guardando || 'Guardando...');
        
        var datos = {
            action: 'mv_guardar_plantilla',
            nonce: mvPdfTemplates.nonce,
            id: plantillaId,
            nombre: nombre,
            tipo: $('#plantilla-tipo').val(),
            descripcion: $('#plantilla-descripcion').val(),
            html_content: htmlEditor && htmlEditor.codemirror ? htmlEditor.codemirror.getValue() : '',
            css_content: cssEditor && cssEditor.codemirror ? cssEditor.codemirror.getValue() : '',
            activa: $('#plantilla-activa').is(':checked') ? 1 : 0
        };
                
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: datos,
            success: function(response) {
                
                if (response.success) {
                    mostrarNotificacion(response.data.message || 'Plantilla guardada exitosamente', 'success');
                    marcarComoGuardado();
                    
                    // Actualizar ID si es nueva plantilla
                    if (!plantillaId && response.data.plantilla_id) {
                        plantillaId = response.data.plantilla_id;
                        window.mvPdfEditor.plantillaId = plantillaId;
                        $('#plantilla-id').val(plantillaId);
                        
                        // Actualizar URL sin recargar página
                        if (history.pushState) {
                            var newUrl = window.location.href.replace('action=new', 'action=edit&plantilla_id=' + plantillaId);
                            history.pushState(null, null, newUrl);
                        }
                    }
                } else {
                    mostrarNotificacion(response.data.message || 'Error al guardar la plantilla', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('PDF Templates: Error AJAX guardando:', error);
                mostrarNotificacion(mvPdfTemplates.i18n.error_general || 'Error de conexión', 'error');
            },
            complete: function() {
                $boton.prop('disabled', false).text(textoOriginal);
            }
        });
    }
    
    /**
     * Generar preview de plantilla - FUNCIÓN PRINCIPAL CORREGIDA
     */
    function generarPreview(cotizacionId) {        
        var $boton = $('#btn-preview-plantilla');
        var textoOriginal = $boton.text();
        
        $boton.prop('disabled', true).text(mvPdfTemplates.i18n.cargando || 'Generando...');
        
        var datos = {
            action: 'mv_preview_plantilla',
            nonce: mvPdfTemplates.nonce,
            html_content: htmlEditor && htmlEditor.codemirror ? htmlEditor.codemirror.getValue() : '',
            css_content: cssEditor && cssEditor.codemirror ? cssEditor.codemirror.getValue() : '',
            cotizacion_id: cotizacionId || 0
        };
                
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: datos,
            success: function(response) {                
                if (response.success) {
                    abrirPreview(response.data.preview_url);
                } else {
                    mostrarNotificacion(response.data.message || 'Error al generar preview', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('PDF Templates: Error AJAX preview:', error);
                mostrarNotificacion(mvPdfTemplates.i18n.error_general || 'Error de conexión', 'error');
            },
            complete: function() {
                $boton.prop('disabled', false).text(textoOriginal);
            }
        });
    }

    /**
     * Abrir preview en nueva ventana
     */
    function abrirPreview(url) {        
        // Cerrar preview anterior si existe
        if (previewWindow && !previewWindow.closed) {
            previewWindow.close();
        }
        
        // Para URLs de AJAX, abrir en nueva pestaña/ventana
        if (url.indexOf('admin-ajax.php') !== -1) {
            previewWindow = window.open(
                url, 
                'preview-plantilla',
                'width=1000,height=700,scrollbars=yes,resizable=yes,status=no,toolbar=no,menubar=no'
            );
            
            if (previewWindow) {
                previewWindow.focus();
            } else {
                // Fallback: mostrar en la misma ventana
                window.open(url, '_blank');
            }
        } else {
            // Para URLs directas
            previewWindow = window.open(url, '_blank');
        }
    }
    
    /**
     * Cargar plantilla predeterminada
     */
    function cargarPlantillaPredeterminada() {
        if (!confirm('¿Estás seguro? Esto sobrescribirá el contenido actual.')) {
            return;
        }
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_cargar_plantilla_predeterminada',
                nonce: mvPdfTemplates.nonce,
                tipo: tipoActual
            },
            success: function(response) {
                if (response.success && response.data.plantilla) {
                    var plantilla = response.data.plantilla;
                    
                    if (htmlEditor && htmlEditor.codemirror) {
                        htmlEditor.codemirror.setValue(plantilla.html_content || '');
                    }
                    if (cssEditor && cssEditor.codemirror) {
                        cssEditor.codemirror.setValue(plantilla.css_content || '');
                    }
                    
                    marcarCambiosSinGuardar();
                    mostrarNotificacion(response.data.message || 'Plantilla cargada', 'success');
                    
                    // Cambiar a pestaña HTML
                    cambiarTab('html');
                } else {
                    mostrarNotificacion(response.data.message || 'Error cargando plantilla', 'error');
                }
            },
            error: function() {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general || 'Error de conexión', 'error');
            }
        });
    }
    
    /**
     * Validar plantilla con delay
     */
    function validarPlantillaConDelay() {
        clearTimeout(validacionTimeout);
        validacionTimeout = setTimeout(validarPlantilla, 1000);
    }
    
    /**
     * Validar plantilla
     */
    function validarPlantilla() {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_validar_plantilla',
                nonce: mvPdfTemplates.nonce,
                html_content: htmlEditor && htmlEditor.codemirror ? htmlEditor.codemirror.getValue() : '',
                css_content: cssEditor && cssEditor.codemirror ? cssEditor.codemirror.getValue() : ''
            },
            success: function(response) {
                if (response.success) {
                    mostrarResultadosValidacion(response.data);
                }
            }
        });
    }
    
    /**
     * Mostrar resultados de validación
     */
    function mostrarResultadosValidacion(datos) {
        var $validacion = $('#validacion-resultados');
        if (!$validacion.length) return;
        
        $validacion.empty();
        
        if (datos.valida) {
            $validacion.append('<div class="notice notice-success inline"><p>✓ Plantilla válida</p></div>');
        } else {
            if (datos.errores && datos.errores.length > 0) {
                var html = '<div class="notice notice-error inline"><p><strong>Errores:</strong></p><ul>';
                $.each(datos.errores, function(i, error) {
                    html += '<li>' + error + '</li>';
                });
                html += '</ul></div>';
                $validacion.append(html);
            }
        }
        
        if (datos.advertencias && datos.advertencias.length > 0) {
            var html = '<div class="notice notice-warning inline"><p><strong>Advertencias:</strong></p><ul>';
            $.each(datos.advertencias, function(i, advertencia) {
                html += '<li>' + advertencia + '</li>';
            });
            html += '</ul></div>';
            $validacion.append(html);
        }
    }
    
    /**
     * Insertar variable en el editor activo
     */
    function insertarVariable(variable) {
        var editor = $('.mv-tab-btn.active').data('tab') === 'html' ? htmlEditor : cssEditor;
        
        if (editor && editor.codemirror) {
            var cursor = editor.codemirror.getCursor();
            editor.codemirror.replaceRange('{{' + variable + '}}', cursor);
            editor.codemirror.focus();
        }
    }
    
    /**
     * Modal de variables
     */
    function abrirModalVariables() {
        $('#modal-variables').show();
        cargarVariablesEnModal();
    }
    
    function cerrarModalVariables() {
        $('#modal-variables').hide();
    }
    
    function cargarVariablesEnModal() {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_variables',
                nonce: mvPdfTemplates.nonce,
                tipo_documento: tipoActual
            },
            success: function(response) {
                if (response.success) {
                    mostrarVariablesEnModal(response.data);
                }
            }
        });
    }
    
    function mostrarVariablesEnModal(variables) {
        var html = '';
        
        $.each(variables, function(grupo, vars) {
            html += '<h4>' + grupo + '</h4>';
            
            if (Array.isArray(vars)) {
                $.each(vars, function(i, variable) {
                    html += '<label style="display: block; margin-bottom: 10px;">';
                    html += '<input type="radio" name="variable_modal" value="' + variable.variable + '" style="margin-right: 10px;">';
                    html += '<code>{{' + variable.variable + '}}</code> - ' + variable.descripcion;
                    html += '</label>';
                });
            }
        });
        
        $('#lista-variables-modal').html(html);
    }
    
    function insertarVariableSeleccionada() {
        var variable = $('input[name="variable_modal"]:checked').val();
        if (variable) {
            insertarVariable(variable);
            cerrarModalVariables();
        }
    }
    
    /**
     * Formatear código
     */
    function formatearHtml() {
        if (htmlEditor && htmlEditor.codemirror && typeof html_beautify === 'function') {
            var formatted = html_beautify(htmlEditor.codemirror.getValue(), {
                indent_size: 2,
                wrap_line_length: 80
            });
            htmlEditor.codemirror.setValue(formatted);
        }
    }
    
    function formatearCss() {
        if (cssEditor && cssEditor.codemirror && typeof css_beautify === 'function') {
            var formatted = css_beautify(cssEditor.codemirror.getValue(), {
                indent_size: 2
            });
            cssEditor.codemirror.setValue(formatted);
        }
    }
    
    /**
     * Insertar CSS base
     */
    function insertarCssBase() {
        var cssBase = `/* Estilos base para PDF */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
    color: #333;
    line-height: 1.4;
    font-size: 12px;
}

.documento {
    max-width: 100%;
    margin: 0 auto;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    border-bottom: 2px solid #2c5aa0;
    padding-bottom: 20px;
}

.empresa-info h1 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 24px;
}

/* Tabla de productos */
.productos-tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.productos-tabla th {
    background-color: #2c5aa0;
    color: white;
    padding: 10px 8px;
    text-align: left;
}

.productos-tabla td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}

/* Totales */
.totales-seccion {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 25px;
}

.total-fila {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #ddd;
}

.total-final {
    border-top: 2px solid #2c5aa0;
    font-weight: bold;
    font-size: 14px;
}`;
        
        if (cssEditor && cssEditor.codemirror) {
            cssEditor.codemirror.setValue(cssBase);
            cambiarTab('css');
        }
    }
    
    /**
     * Funciones para la lista de plantillas
     */
    function cambiarEstadoPlantilla(plantillaId, activa) {        
        var $checkbox = $('input[data-plantilla-id="' + plantillaId + '"]');
        var $row = $('tr[data-plantilla-id="' + plantillaId + '"]');
        
        // Obtener tipo de plantilla
        var tipoPlantilla = $row.find('.mv-tipo-badge').text().trim();
                
        // Si se está activando, hacer petición previa para obtener nombres
        if (activa) {            
            // Hacer una petición AJAX previa para obtener información de las plantillas
            $.ajax({
                url: mvPdfTemplates.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mv_obtener_plantillas_tipo',
                    nonce: mvPdfTemplates.nonce,
                    tipo: tipoPlantilla.toLowerCase(),
                    activas_solo: true
                },
                success: function(response) {                    
                    if (response.success && response.data && response.data.length > 0) {
                        // Hay plantillas activas del mismo tipo
                        var plantillasActivas = response.data.filter(function(p) {
                            return parseInt(p.id) !== parseInt(plantillaId);
                        });
                        
                        if (plantillasActivas.length > 0) {
                            var nombreOtra = plantillasActivas[0].nombre || 'Otra plantilla';
                            
                            // Obtener nombre de la plantilla actual haciendo otra petición
                            $.ajax({
                                url: mvPdfTemplates.ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mv_obtener_plantilla',
                                    nonce: mvPdfTemplates.nonce,
                                    plantilla_id: plantillaId
                                },
                                success: function(response2) {
                                    var nombreActual = 'Plantilla ID ' + plantillaId;
                                    if (response2.success && response2.data && response2.data.nombre) {
                                        nombreActual = response2.data.nombre;
                                    }
                                    
                                    var mensaje = 'Al activar "' + nombreActual + '" (tipo: ' + tipoPlantilla + '):\n\n' +
                                                '• Se desactivará automáticamente: "' + nombreOtra + '"\n' +
                                                '• La página se recargará para mostrar los cambios\n\n' +
                                                '¿Desea continuar?';
                                                                        
                                    if (confirm(mensaje)) {
                                        ejecutarCambioEstado(plantillaId, activa, $checkbox, $row);
                                    } else {
                                        $checkbox.prop('checked', false);
                                    }
                                },
                                error: function() {
                                    // Si falla obtener el nombre, usar genérico
                                    var mensaje = 'Al activar esta plantilla (tipo: ' + tipoPlantilla + '):\n\n' +
                                                '• Se desactivará automáticamente: "' + nombreOtra + '"\n' +
                                                '• La página se recargará para mostrar los cambios\n\n' +
                                                '¿Desea continuar?';
                                    
                                    if (confirm(mensaje)) {
                                        ejecutarCambioEstado(plantillaId, activa, $checkbox, $row);
                                    } else {
                                        $checkbox.prop('checked', false);
                                    }
                                }
                            });
                        } else {
                            // No hay conflictos, proceder directamente
                            ejecutarCambioEstado(plantillaId, activa, $checkbox, $row);
                        }
                    } else {
                        // No hay plantillas activas del mismo tipo, proceder directamente
                        ejecutarCambioEstado(plantillaId, activa, $checkbox, $row);
                    }
                },
                error: function() {
                    ejecutarCambioEstado(plantillaId, activa, $checkbox, $row);
                }
            });
        } else {
            // Es desactivación, proceder directamente
            ejecutarCambioEstado(plantillaId, activa, $checkbox, $row);
        }
    }

    /**
     * Función auxiliar para ejecutar el cambio de estado
     */
    function ejecutarCambioEstado(plantillaId, activa, $checkbox, $row) {        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_cambiar_estado_plantilla',
                nonce: mvPdfTemplates.nonce,
                plantilla_id: plantillaId,
                activa: activa ? 1 : 0
            },
            beforeSend: function() {
                $checkbox.prop('disabled', true);
            },
            success: function(response) {                
                if (response.success) {
                    mostrarNotificacion(response.data.message, 'success');
                    
                    if (response.data.requiere_recarga) {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $row.find('.mv-estado-text').text('Inactiva');
                        $row.attr('data-estado', 'inactiva');
                        $checkbox.prop('disabled', false);
                    }
                } else {
                    var tipoNotificacion = response.data.codigo === 'REQUIERE_REEMPLAZO' ? 'warning' : 'error';
                    mostrarNotificacion(response.data.message, tipoNotificacion);
                    $checkbox.prop('checked', !activa);
                    $checkbox.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general, 'error');
                $checkbox.prop('checked', !activa);
                $checkbox.prop('disabled', false);
            }
        });
    }

    /**
     * Función auxiliar para destacar plantillas del mismo tipo
     */
    function destacarPlantillasDelMismoTipo(tipo, plantillaActualId) {
        // Remover destacados previos
        $('.mv-plantilla-destacada').removeClass('mv-plantilla-destacada');
        
        // Destacar plantillas del mismo tipo (excepto la actual)
        $('.mv-plantillas-table tbody tr').each(function() {
            var $row = $(this);
            var otroId = $row.data('plantilla-id');
            var otroTipo = $row.find('.mv-tipo-badge').text().toLowerCase().trim();
            
            if (otroId != plantillaActualId && otroTipo === tipo) {
                $row.addClass('mv-plantilla-destacada');
            }
        });
        
        // Remover destacado después de 5 segundos
        setTimeout(function() {
            $('.mv-plantilla-destacada').removeClass('mv-plantilla-destacada');
        }, 5000);
    }

    /**
     * Función mejorada para mostrar notificaciones con diferentes tipos
     */
    function mostrarNotificacion(mensaje, tipo) {
        tipo = tipo || 'info';
        
        // Mapear tipos a clases de WordPress
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
            default:
                claseWordPress = 'notice-info';
        }
        
        var $notice = $('<div class="notice ' + claseWordPress + ' is-dismissible"><p>' + mensaje + '</p></div>');
        
        if ($('.wp-header-end').length) {
            $notice.insertAfter('.wp-header-end');
        } else if ($('.wrap h1').length) {
            $notice.insertAfter('.wrap h1');
        } else {
            $notice.prependTo('.wrap');
        }
        
        // Auto-remover después de 6 segundos (más tiempo para mensajes importantes)
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 6000);
        
        // Scroll hacia arriba para mostrar notificación
        $('html, body').animate({ scrollTop: 0 }, 500);
    }
    
    function duplicarPlantilla(plantillaId) {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_duplicar_plantilla',
                nonce: mvPdfTemplates.nonce,
                plantilla_id: plantillaId
            },
            success: function(response) {
                if (response.success) {
                    mostrarNotificacion(response.data.message || 'Plantilla duplicada', 'success');
                    
                    // Recargar página después de 2 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarNotificacion(response.data.message || 'Error al duplicar', 'error');
                }
            },
            error: function() {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general || 'Error de conexión', 'error');
            }
        });
    }
    
    function eliminarPlantilla(plantillaId) {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_eliminar_plantilla',
                nonce: mvPdfTemplates.nonce,
                plantilla_id: plantillaId
            },
            success: function(response) {
                if (response.success) {
                    mostrarNotificacion(response.data.message || 'Plantilla eliminada', 'success');
                    
                    // Remover fila de la tabla
                    $('tr[data-plantilla-id="' + plantillaId + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    mostrarNotificacion(response.data.message || 'Error al eliminar', 'error');
                }
            },
            error: function() {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general || 'Error de conexión', 'error');
            }
        });
    }
    
    function previewPlantillaExistente(plantillaId) {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_obtener_plantilla',
                nonce: mvPdfTemplates.nonce,
                plantilla_id: plantillaId
            },
            success: function(response) {
                if (response.success) {
                    var plantilla = response.data;
                    
                    // Generar preview con el contenido de la plantilla
                    $.ajax({
                        url: mvPdfTemplates.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mv_preview_plantilla',
                            nonce: mvPdfTemplates.nonce,
                            html_content: plantilla.html_content,
                            css_content: plantilla.css_content,
                            cotizacion_id: 0
                        },
                        success: function(previewResponse) {
                            if (previewResponse.success) {
                                abrirPreview(previewResponse.data.preview_url);
                            } else {
                                mostrarNotificacion(previewResponse.data.message || 'Error en preview', 'error');
                            }
                        }
                    });
                } else {
                    mostrarNotificacion(response.data.message || 'Error obteniendo plantilla', 'error');
                }
            }
        });
    }
    
    /**
     * Mostrar notificaciones - MEJORADO
     */
    function mostrarNotificacion(mensaje, tipo) {
        tipo = tipo || 'info';
        
        var $notice = $('<div class="notice notice-' + tipo + ' is-dismissible"><p>' + mensaje + '</p></div>');
        
        // Buscar ubicación para la notificación
        if ($('.wp-header-end').length) {
            $notice.insertAfter('.wp-header-end');
        } else if ($('.wrap h1').length) {
            $notice.insertAfter('.wrap h1');
        } else if ($('.mv-editor-plantillas h1').length) {
            $notice.insertAfter('.mv-editor-plantillas h1');
        } else {
            $notice.prependTo('.wrap');
        }
        
        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll hacia arriba para mostrar notificación
        $('html, body').animate({ scrollTop: 0 }, 500);
    }
});