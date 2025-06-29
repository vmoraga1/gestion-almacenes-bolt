/**
 * Script mejorado para validación de RUT con debug
 * Archivo: admin/js/validacion-rut.js
 */

(function($) {
    'use strict';
    
    console.log('Script de validación de RUT cargado');
    
    // Funciones de utilidad para RUT
    window.mvRutUtils = {
        // Limpiar RUT
        limpiar: function(rut) {
            return rut.replace(/[^0-9kK]/g, '').toUpperCase();
        },
        
        // Formatear RUT
        formatear: function(rut) {
            var rutLimpio = this.limpiar(rut);
            
            if (rutLimpio.length < 2) {
                return rutLimpio;
            }
            
            // Separar número y DV
            var numero = rutLimpio.slice(0, -1);
            var dv = rutLimpio.slice(-1);
            
            // Formatear número con puntos
            var numeroFormateado = '';
            var contador = 0;
            
            for (var i = numero.length - 1; i >= 0; i--) {
                if (contador === 3) {
                    numeroFormateado = '.' + numeroFormateado;
                    contador = 0;
                }
                numeroFormateado = numero[i] + numeroFormateado;
                contador++;
            }
            
            return numeroFormateado + '-' + dv;
        },
        
        // Validar RUT
        validar: function(rut) {
            var rutLimpio = this.limpiar(rut);
            
            console.log('Validando RUT:', rutLimpio);
            
            if (rutLimpio.length < 2) {
                console.log('RUT muy corto');
                return false;
            }
            
            // Separar número y dígito verificador
            var numero = rutLimpio.slice(0, -1);
            var dv = rutLimpio.slice(-1);
            
            // Verificar que el número sea válido
            if (!/^\d+$/.test(numero)) {
                console.log('Número contiene caracteres no válidos');
                return false;
            }
            
            // Calcular dígito verificador
            var suma = 0;
            var multiplicador = 2;
            
            for (var i = numero.length - 1; i >= 0; i--) {
                suma += parseInt(numero[i]) * multiplicador;
                multiplicador++;
                if (multiplicador > 7) {
                    multiplicador = 2;
                }
            }
            
            var resto = suma % 11;
            var dvCalculado = 11 - resto;
            
            if (dvCalculado === 11) {
                dvCalculado = '0';
            } else if (dvCalculado === 10) {
                dvCalculado = 'K';
            } else {
                dvCalculado = dvCalculado.toString();
            }
            
            console.log('DV calculado:', dvCalculado, 'DV ingresado:', dv);
            
            return dv === dvCalculado;
        }
    };
    
    // Cuando el documento esté listo
    $(document).ready(function() {
        console.log('Documento listo, configurando validación de RUT');
        
        // Función para configurar validación en un input
        function configurarValidacionRUT($input) {
            if (!$input.length) {
                console.log('Input de RUT no encontrado');
                return;
            }
            
            console.log('Configurando validación en input:', $input);
            
            var $errorMsg = $('<span class="mv-rut-error" style="color:#d63638;font-size:12px;display:none;margin-top:5px;"></span>');
            $input.after($errorMsg);
            
            // Formatear mientras escribe
            $input.on('input', function() {
                var valor = $(this).val();
                var rutFormateado = mvRutUtils.formatear(valor);
                
                if (valor !== rutFormateado) {
                    $(this).val(rutFormateado);
                }
            });
            
            // Validar al salir del campo
            $input.on('blur', function() {
                var rut = $(this).val();
                
                if (!rut) {
                    $(this).removeClass('error');
                    $errorMsg.hide();
                    return;
                }
                
                if (!mvRutUtils.validar(rut)) {
                    $(this).addClass('error');
                    $errorMsg.text('RUT inválido. Verifique el dígito verificador.').show();
                } else {
                    $(this).removeClass('error');
                    $errorMsg.hide();
                    
                    // Validar en servidor si no existe
                    $.post(ajaxurl, {
                        action: 'mv_validar_rut',
                        rut: rut,
                        nonce: moduloVentasAjax.nonce || $('input[name="mv_nonce"]').val()
                    }).done(function(response) {
                        console.log('Respuesta validación servidor:', response);
                        if (!response.success && response.data && response.data.message.includes('registrado')) {
                            $input.addClass('error');
                            $errorMsg.text(response.data.message).show();
                        }
                    }).fail(function(xhr) {
                        console.error('Error en validación AJAX:', xhr);
                    });
                }
            });
        }
        
        // Configurar en todos los inputs de RUT
        configurarValidacionRUT($('input[name="rut"]'));
        configurarValidacionRUT($('input[name="cliente[rut]"]'));
        configurarValidacionRUT($('#cliente_rut'));
        configurarValidacionRUT($('#rut'));
        
        // Para el modal de nuevo cliente
        $(document).on('shown.bs.modal', '#mv-modal-nuevo-cliente', function() {
            console.log('Modal abierto, configurando validación');
            configurarValidacionRUT($(this).find('input[name="cliente[rut]"]'));
        });
        
        // Interceptar envío de formulario de nuevo cliente
        $(document).on('submit', '#mv-form-nuevo-cliente', function(e) {
            console.log('Formulario de nuevo cliente enviado');
            
            var $form = $(this);
            var $rutInput = $form.find('input[name="cliente[rut]"]');
            var rut = $rutInput.val();
            
            // Validar RUT antes de enviar
            if (!rut) {
                e.preventDefault();
                alert('El RUT es obligatorio');
                return false;
            }
            
            if (!mvRutUtils.validar(rut)) {
                e.preventDefault();
                alert('El RUT ingresado no es válido. Por favor verifique el dígito verificador.');
                $rutInput.focus();
                return false;
            }
            
            // Limpiar y formatear RUT antes de enviar
            var rutLimpio = mvRutUtils.limpiar(rut);
            console.log('RUT limpio a enviar:', rutLimpio);
            
            // Actualizar el valor con el RUT limpio
            $rutInput.val(rutLimpio);
            
            // Log de datos a enviar
            var datosForm = $form.serializeArray();
            console.log('Datos del formulario:', datosForm);
        });
        
        // Para debug: interceptar respuestas AJAX
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url === ajaxurl && settings.data && settings.data.includes('mv_crear_cliente_rapido')) {
                console.log('Respuesta de crear cliente:', xhr.responseJSON);
            }
        });
    });
    
})(jQuery);