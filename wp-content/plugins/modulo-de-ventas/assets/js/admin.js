/**
 * JavaScript del panel de administración
 * Módulo de Ventas
 */

(function($) {
    'use strict';

    // Esperar a que el DOM esté listo
    $(document).ready(function() {
        
        // Inicializar componentes
        initSelect2();
        initTooltips();
        initDatepickers();
        initAjaxForms();
        initConfirmDialogs();
        
        // Log de confirmación
        console.log('Módulo de Ventas: JavaScript cargado correctamente');
    });
    
    /**
     * Inicializar Select2
     */
    function initSelect2() {
        if ($.fn.select2) {
            $('.mv-select2').select2({
                width: '100%',
                placeholder: 'Seleccione una opción',
                allowClear: true
            });
        }
    }
    
    /**
     * Inicializar tooltips
     */
    function initTooltips() {
        $('.mv-tooltip').on('mouseenter', function() {
            $(this).find('.mv-tooltiptext').fadeIn(200);
        }).on('mouseleave', function() {
            $(this).find('.mv-tooltiptext').fadeOut(200);
        });
    }
    
    /**
     * Inicializar datepickers
     */
    function initDatepickers() {
        if ($.fn.datepicker) {
            $('.mv-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        }
    }
    
    /**
     * Inicializar formularios AJAX
     */
    function initAjaxForms() {
        $('.mv-ajax-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submit = $form.find('[type="submit"]');
            var originalText = $submit.text();
            
            // Mostrar loading
            $form.addClass('mv-loading');
            $submit.prop('disabled', true).text('Procesando...');
            
            // Enviar petición
            $.ajax({
                url: $form.attr('action') || moduloVentasAjax.ajax_url,
                type: $form.attr('method') || 'POST',
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message || 'Operación exitosa', 'success');
                        
                        // Recargar si es necesario
                        if (response.data.reload) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        showMessage(response.data.message || 'Error al procesar', 'error');
                    }
                },
                error: function() {
                    showMessage('Error de conexión', 'error');
                },
                complete: function() {
                    $form.removeClass('mv-loading');
                    $submit.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    /**
     * Inicializar diálogos de confirmación
     */
    function initConfirmDialogs() {
        $(document).on('click', '.mv-confirm', function(e) {
            var message = $(this).data('confirm') || '¿Está seguro de realizar esta acción?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    /**
     * Mostrar mensaje
     */
    function showMessage(message, type) {
        type = type || 'info';
        
        var $message = $('<div class="notice notice-' + type + ' is-dismissible mv-notice">' +
                        '<p>' + message + '</p>' +
                        '<button type="button" class="notice-dismiss">' +
                        '<span class="screen-reader-text">Descartar este aviso.</span>' +
                        '</button></div>');
        
        $('.wp-header-end').after($message);
        
        // Auto ocultar después de 5 segundos
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Botón de cerrar
        $message.find('.notice-dismiss').on('click', function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Funciones públicas
     */
    window.ModuloVentas = {
        showMessage: showMessage,
        showLoading: function(element) {
            $(element).addClass('mv-loading');
        },
        hideLoading: function(element) {
            $(element).removeClass('mv-loading');
        },
        formatMoney: function(amount) {
            return moduloVentasAjax.currency_symbol + ' ' + 
                   new Intl.NumberFormat('es-CL').format(amount);
        }
    };

})(jQuery);