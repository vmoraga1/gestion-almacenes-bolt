/**
 * JAVASCRIPT PARA EDITOR DE PLANTILLAS PDF
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('PDF Templates JS: Iniciando...');
    
    // Variables globales
    var htmlEditor, cssEditor;
    var plantillaId = $('#plantilla-id').val();
    var tipoActual = $('#plantilla-tipo').val();
    var variableSeleccionada = '';
    var cambiosSinGuardar = false;
    
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
        console.log('PDF Templates: Inicializando editor...');
        
        // Esperar a que CodeMirror esté disponible
        setTimeout(function() {
            initCodeEditors();
            cargarVariablesDisponibles();
            setupEventHandlers();
        }, 100);
    }
    
    /**
     * Inicializar funcionalidades de la lista
     */
    function inicializarLista() {
        console.log('PDF Templates: Inicializando lista...');
        
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
            previewPlantilla(plantillaId);
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
                    'Ctrl-S': function(cm) {
                        guardarPlantilla();
                        return false;
                    },
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
                theme: 'default',
                extraKeys: {
                    'Ctrl-S': function(cm) {
                        guardarPlantilla();
                        return false;
                    }
                }
            });
            
            cssEditor = wp.codeEditor.initialize($('#css-editor'), cssSettings);
            
            // Detectar cambios
            if (htmlEditor && htmlEditor.codemirror) {
                htmlEditor.codemirror.on('change', function() {
                    cambiosSinGuardar = true;
                    ocultarStatusGuardado();
                });
            }
            
            if (cssEditor && cssEditor.codemirror) {
                cssEditor.codemirror.on('change', function() {
                    cambiosSinGuardar = true;
                    ocultarStatusGuardado();
                });
            }
            
            console.log('PDF Templates: Editores CodeMirror inicializados');
            
        } catch (error) {
            console.error('Error inicializando CodeMirror:', error);
        }
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
        $('#btn-guardar-plantilla').on('click', function() {
            guardarPlantilla();
        });
        
        // Vista previa
        $('#btn-preview-plantilla, #btn-actualizar-preview').on('click', function() {
            generarPreview();
        });
        
        // Cambio de tipo de documento
        $('#plantilla-tipo').on('change', function() {
            tipoActual = $(this).val();
            cargarVariablesDisponibles();
        });
        
        // Detectar cambios en campos del formulario
        $('#plantilla-nombre, #plantilla-descripcion, #plantilla-activa').on('change', function() {
            cambiosSinGuardar = true;
            ocultarStatusGuardado();
        });
        
        // Modal de variables
        $('#btn-insertar-variable').on('click', abrirModalVariables);
        $('.mv-modal-close').on('click', cerrarModalVariables);
        $('#btn-insertar-variable-modal').on('click', insertarVariableSeleccionada);
        
        // Cerrar modal con Escape
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // Escape
                cerrarModalVariables();
            }
        });
        
        // Clic en variables del sidebar
        $(document).on('click', '.mv-variable-item', function() {
            var variable = $(this).data('variable');
            insertarVariable(variable);
        });
        
        // Formatear código
        $('#btn-formato-html').on('click', function() {
            formatearHtml();
        });
        
        $('#btn-formato-css').on('click', function() {
            formatearCss();
        });
        
        // CSS base
        $('#btn-css-plantilla').on('click', function() {
            insertarCssBase();
        });
        
        // Advertencia al salir con cambios sin guardar
        $(window).on('beforeunload', function() {
            if (cambiosSinGuardar) {
                return 'Hay cambios sin guardar. ¿Estás seguro de que quieres salir?';
            }
        });
        
        // Auto-guardado cada 2 minutos
        setInterval(function() {
            if (cambiosSinGuardar && $('#plantilla-nombre').val().trim()) {
                guardarPlantilla(true); // Guardado silencioso
            }
        }, 120000);
        
        console.log('PDF Templates: Event handlers configurados');
    }
    
    /**
     * Cambiar tab activo
     */
    function cambiarTab(tab) {
        $('.mv-tab-btn').removeClass('active');
        $('.mv-tab-btn[data-tab="' + tab + '"]').addClass('active');
        
        $('.mv-tab-content').hide();
        $('#tab-' + tab).show();
        
        // Refresh de editores al cambiar tab
        setTimeout(function() {
            if (tab === 'html' && htmlEditor) {
                htmlEditor.codemirror.refresh();
            } else if (tab === 'css' && cssEditor) {
                cssEditor.codemirror.refresh();
            }
        }, 100);
    }
    
    /**
     * Cargar variables disponibles
     */
    function cargarVariablesDisponibles() {
        $('#variables-disponibles').html('<div class="mv-loading-variables"><span class="spinner is-active"></span> ' + mvPdfTemplates.i18n.cargando + '</div>');
        
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
                html += '<div class="mv-variable-item" data-variable="' + variable.variable + '" title="' + variable.descripcion + '">';
                html += '<code>{{' + variable.variable + '}}</code>';
                html += '<small>' + variable.descripcion + '</small>';
                html += '</div>';
            });
        });
        
        if (html === '') {
            html = '<p>No hay variables disponibles para este tipo de documento.</p>';
        }
        
        $('#variables-disponibles').html(html);
    }
    
    /**
     * Guardar plantilla
     */
    function guardarPlantilla(silencioso) {
        silencioso = silencioso || false;
        
        if (!silencioso) {
            mostrarStatusGuardando();
        }
        
        var datos = {
            action: 'mv_guardar_plantilla',
            nonce: mvPdfTemplates.nonce,
            id: plantillaId,
            nombre: $('#plantilla-nombre').val(),
            tipo: $('#plantilla-tipo').val(),
            descripcion: $('#plantilla-descripcion').val(),
            html_content: htmlEditor ? htmlEditor.codemirror.getValue() : $('#html-editor').val(),
            css_content: cssEditor ? cssEditor.codemirror.getValue() : $('#css-editor').val(),
            activa: $('#plantilla-activa').is(':checked') ? 1 : 0
        };
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: datos,
            success: function(response) {
                if (response.success) {
                    if (!silencioso) {
                        mostrarStatusGuardado();
                    }
                    cambiosSinGuardar = false;
                    
                    // Si es plantilla nueva, actualizar ID
                    if (!plantillaId && response.data.plantilla_id) {
                        plantillaId = response.data.plantilla_id;
                        $('#plantilla-id').val(plantillaId);
                        
                        // Actualizar URL sin recargar página
                        if (history.pushState) {
                            var newUrl = window.location.href.replace('action=new', 'action=edit&plantilla_id=' + plantillaId);
                            history.pushState({path: newUrl}, '', newUrl);
                        }
                    }
                    
                    if (!silencioso) {
                        mostrarNotificacion(response.data.message, 'success');
                    }
                } else {
                    if (!silencioso) {
                        ocultarStatusGuardando();
                        mostrarNotificacion(response.data.message || mvPdfTemplates.i18n.error_general, 'error');
                    }
                }
            },
            error: function() {
                if (!silencioso) {
                    ocultarStatusGuardando();
                    mostrarNotificacion(mvPdfTemplates.i18n.error_general, 'error');
                }
            }
        });
    }
    
    /**
     * Generar vista previa
     */
    function generarPreview() {
        var cotizacionId = $('#cotizacion-preview').val();
        
        $('#preview-container').html('<div class="mv-preview-loading"><span class="spinner is-active"></span> Generando vista previa...</div>');
        
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_preview_plantilla',
                nonce: mvPdfTemplates.nonce,
                html_content: htmlEditor ? htmlEditor.codemirror.getValue() : $('#html-editor').val(),
                css_content: cssEditor ? cssEditor.codemirror.getValue() : $('#css-editor').val(),
                cotizacion_id: cotizacionId
            },
            success: function(response) {
                if (response.success) {
                    // Abrir preview en nueva ventana
                    window.open(response.data.preview_url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
                    $('#preview-container').html('<div class="mv-preview-placeholder"><p>Vista previa abierta en nueva ventana</p></div>');
                } else {
                    $('#preview-container').html('<div class="mv-preview-error"><p>Error: ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#preview-container').html('<div class="mv-preview-error"><p>Error de conexión</p></div>');
            }
        });
    }
    
    /**
     * Insertar variable en el editor
     */
    function insertarVariable(variable) {
        var texto = '{{' + variable + '}}';
        
        if (htmlEditor && $('.mv-tab-btn[data-tab="html"]').hasClass('active')) {
            var cursor = htmlEditor.codemirror.getCursor();
            htmlEditor.codemirror.replaceRange(texto, cursor);
            htmlEditor.codemirror.focus();
        } else if (cssEditor && $('.mv-tab-btn[data-tab="css"]').hasClass('active')) {
            var cursor = cssEditor.codemirror.getCursor();
            cssEditor.codemirror.replaceRange(texto, cursor);
            cssEditor.codemirror.focus();
        }
        
        cambiosSinGuardar = true;
        ocultarStatusGuardado();
    }
    
    /**
     * Funciones de status
     */
    function mostrarStatusGuardando() {
        $('#status-guardado').addClass('hidden');
        $('#status-guardando').removeClass('hidden');
    }
    
    function mostrarStatusGuardado() {
        $('#status-guardando').addClass('hidden');
        $('#status-guardado').removeClass('hidden');
        
        setTimeout(function() {
            $('#status-guardado').addClass('hidden');
        }, 3000);
    }
    
    function ocultarStatusGuardando() {
        $('#status-guardando').addClass('hidden');
    }
    
    function ocultarStatusGuardado() {
        $('#status-guardado').addClass('hidden');
    }
    
    /**
     * Modal de variables
     */
    function abrirModalVariables() {
        $('#modal-variables').show();
        cargarVariablesModal();
    }
    
    function cerrarModalVariables() {
        $('#modal-variables').hide();
        variableSeleccionada = '';
    }
    
    function cargarVariablesModal() {
        $('#lista-variables-modal').html('<div class="spinner is-active"></div>');
        
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
                    mostrarVariablesModal(response.data);
                }
            }
        });
    }
    
    function mostrarVariablesModal(variables) {
        var html = '';
        
        $.each(variables, function(categoria, vars) {
            html += '<h4>' + categoria + '</h4>';
            
            $.each(vars, function(i, variable) {
                html += '<label style="display: block; margin-bottom: 10px;">';
                html += '<input type="radio" name="variable_modal" value="' + variable.variable + '" style="margin-right: 10px;">';
                html += '<code>{{' + variable.variable + '}}</code> - ' + variable.descripcion;
                html += '</label>';
            });
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
        if (htmlEditor && typeof html_beautify === 'function') {
            var formatted = html_beautify(htmlEditor.codemirror.getValue(), {
                indent_size: 2,
                wrap_line_length: 80
            });
            htmlEditor.codemirror.setValue(formatted);
        }
    }
    
    function formatearCss() {
        if (cssEditor && typeof css_beautify === 'function') {
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
        
        if (cssEditor) {
            cssEditor.codemirror.setValue(cssBase);
            cambiarTab('css');
        }
    }
    
    /**
     * Funciones para la lista de plantillas
     */
    function cambiarEstadoPlantilla(plantillaId, activa) {
        $.ajax({
            url: mvPdfTemplates.ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_cambiar_estado_plantilla',
                nonce: mvPdfTemplates.nonce,
                plantilla_id: plantillaId,
                activa: activa
            },
            success: function(response) {
                if (response.success) {
                    mostrarNotificacion(response.data.message, 'success');
                    
                    // Actualizar texto de estado
                    var $row = $('tr[data-plantilla-id="' + plantillaId + '"]');
                    $row.find('.mv-estado-text').text(activa ? 'Activa' : 'Inactiva');
                    $row.attr('data-estado', activa ? 'activa' : 'inactiva');
                } else {
                    mostrarNotificacion(response.data.message, 'error');
                    // Revertir toggle
                    $('input[data-plantilla-id="' + plantillaId + '"]').prop('checked', !activa);
                }
            },
            error: function() {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general, 'error');
                // Revertir toggle
                $('input[data-plantilla-id="' + plantillaId + '"]').prop('checked', !activa);
            }
        });
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
                    mostrarNotificacion(response.data.message, 'success');
                    
                    // Recargar página después de 2 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    mostrarNotificacion(response.data.message, 'error');
                }
            },
            error: function() {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general, 'error');
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
                    mostrarNotificacion(response.data.message, 'success');
                    
                    // Remover fila de la tabla
                    $('tr[data-plantilla-id="' + plantillaId + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    mostrarNotificacion(response.data.message, 'error');
                }
            },
            error: function() {
                mostrarNotificacion(mvPdfTemplates.i18n.error_general, 'error');
            }
        });
    }
    
    function previewPlantilla(plantillaId) {
        // TODO: Implementar preview de plantilla existente
        alert('Preview de plantilla - En desarrollo');
    }
    
    /**
     * Mostrar notificaciones
     */
    function mostrarNotificacion(mensaje, tipo) {
        tipo = tipo || 'info';
        
        var $notice = $('<div class="notice notice-' + tipo + ' is-dismissible"><p>' + mensaje + '</p></div>');
        
        if ($('.wp-header-end').length) {
            $notice.insertAfter('.wp-header-end');
        } else if ($('.wrap h1').length) {
            $notice.insertAfter('.wrap h1');
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
    
    console.log('PDF Templates JS: Inicialización completa');
});