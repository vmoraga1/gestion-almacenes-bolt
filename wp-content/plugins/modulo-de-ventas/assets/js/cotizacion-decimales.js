/**
 * JavaScript para validación de decimales en formularios de cotización
 * Módulo de Ventas - WordPress Plugin
 * 
 * @package ModuloVentas
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Configuración de decimales (será pasada desde PHP)
    const mvDecimalesConfig = window.mvConfig || {
        decimales_precio: 2,
        decimales_cantidad: 0,
        moneda: 'CLP'
    };

    /**
     * Inicializar validación de decimales
     */
    function inicializarValidacionDecimales() {
        // Agregar estilos CSS
        agregarEstilosValidacion();
        
        // Configurar campos existentes
        configurarCamposPrecios();
        
        // Event listeners para formularios
        configurarEventListeners();
        
        // Configurar dinámicamente cuando se cambien los tipos de descuento
        configurarCambioTipoDescuento();
        
    }

    /**
     * Configurar cambios en tipo de descuento
     */
    function configurarCambioTipoDescuento() {
        $(document).on('change', '.mv-tipo-descuento', function() {
            const $select = $(this);
            const $input = $select.closest('td').find('.mv-input-descuento');
            const tipo = $select.val();
            
            if (tipo === 'monto') {
                // Configurar como campo de precio
                const decimales = parseInt(mvDecimalesConfig.decimales_precio);
                const step = decimales > 0 ? '0.' + '0'.repeat(decimales - 1) + '1' : '1';
                
                $input.attr({
                    'step': step,
                    'min': '0',
                    'max': '', // Sin límite máximo
                    'placeholder': decimales > 0 ? '0.' + '0'.repeat(decimales) : '0'
                }).removeClass('mv-cantidad-field').addClass('mv-precio-field');
                
            } else { // porcentaje
                $input.attr({
                    'step': '0.01',
                    'min': '0',
                    'max': '100',
                    'placeholder': '0.00'
                }).removeClass('mv-precio-field').addClass('mv-porcentaje-field');
            }
        });
    }

    /**
     * Configurar campos de precio y cantidad con validación
     */
    function configurarCamposPrecios() {
        // Configurar campos de precio - SOLO los campos específicos
        $('.mv-input-precio[type="number"], input[name*="precio_unitario"]:not([type="hidden"])').not('.mv-decimales-configured').each(function() {
            const $campo = $(this);
            const decimales = parseInt(mvDecimalesConfig.decimales_precio);
            const step = decimales > 0 ? '0.' + '0'.repeat(decimales - 1) + '1' : '1';
            
            $campo.attr({
                'type': 'number',
                'step': step,
                'min': '0',
                'placeholder': decimales > 0 ? '0.' + '0'.repeat(decimales) : '0'
            }).addClass('mv-precio-field mv-decimales-configured');
            
            // Validación en tiempo real
            $campo.off('input.decimales blur.decimales').on('input.decimales blur.decimales', function() {
                validarDecimalesPrecio(this);
            });
        });
        
        // Configurar campos de cantidad - SOLO los campos específicos
        $('.mv-input-cantidad[type="number"], input[name*="cantidad"]:not([type="hidden"])').not('.mv-decimales-configured').each(function() {
            const $campo = $(this);
            const decimales = parseInt(mvDecimalesConfig.decimales_cantidad);
            
            // Configurar step según decimales permitidos
            let step;
            if (decimales === 0) {
                step = '1'; // Solo enteros
            } else {
                step = '0.' + '0'.repeat(decimales - 1) + '1'; // Ej: 0.01, 0.001
            }
            
            $campo.attr({
                'type': 'number',
                'step': step,
                'min': decimales === 0 ? '1' : '0.' + '0'.repeat(decimales - 1) + '1',
                'placeholder': decimales > 0 ? '1.' + '0'.repeat(decimales) : '1'
            }).addClass('mv-cantidad-field mv-decimales-configured');
            
            // Validación en tiempo real
            $campo.off('input.decimales blur.decimales').on('input.decimales blur.decimales', function() {
                validarDecimalesCantidad(this);
            });
        });
        
        // También configurar campos de descuento (cuando son en monto)
        $('.mv-input-descuento[type="number"]').not('.mv-decimales-configured').each(function() {
            const $campo = $(this);
            const $tipoDescuento = $campo.closest('td').find('.mv-tipo-descuento');
            
            if ($tipoDescuento.val() === 'monto') {
                const decimales = parseInt(mvDecimalesConfig.decimales_precio);
                const step = decimales > 0 ? '0.' + '0'.repeat(decimales - 1) + '1' : '1';
                
                $campo.attr({
                    'step': step,
                    'min': '0'
                }).addClass('mv-precio-field mv-decimales-configured');
                
                $campo.off('input.decimales blur.decimales').on('input.decimales blur.decimales', function() {
                    if ($tipoDescuento.val() === 'monto') {
                        validarDecimalesPrecio(this);
                    }
                });
            }
        });
    }

    /**
     * Configurar event listeners para formularios
     */
    function configurarEventListeners() {
        // Validar formulario antes de enviar
        $('form.mv-cotizacion-form, form[data-validate-decimales="true"]').off('submit.decimales').on('submit.decimales', function(e) {
            if (!validarFormularioCompleto()) {
                e.preventDefault();
                
                // Scroll al primer campo con error
                const $primerError = $('.mv-field-error').first();
                if ($primerError.length) {
                    $('html, body').animate({
                        scrollTop: $primerError.offset().top - 100
                    }, 500);
                    $primerError.focus();
                }
                
                return false;
            }
        });
        
        // Recalcular totales cuando cambien precios o cantidades
        $(document).off('input.decimales blur.decimales', '.mv-precio-field, .mv-cantidad-field')
                  .on('input.decimales blur.decimales', '.mv-precio-field, .mv-cantidad-field', function() {
            // Llamar función de recálculo si existe
            if (typeof window.recalcularTotales === 'function') {
                window.recalcularTotales();
            } else if (typeof recalcularTotales === 'function') {
                recalcularTotales();
            }
        });
        
        // Configurar nuevos campos dinámicamente agregados usando MutationObserver (método moderno)
        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                let shouldReconfigure = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                const $node = $(node);
                                // Solo reconfigurar si hay nuevos campos sin configurar
                                if ($node.find('input[type="number"]:not(.mv-decimales-configured)').length > 0 || 
                                    ($node.is('input[type="number"]') && !$node.hasClass('mv-decimales-configured'))) {
                                    shouldReconfigure = true;
                                }
                            }
                        });
                    }
                });
                
                if (shouldReconfigure) {
                    setTimeout(configurarCamposPrecios, 100);
                }
            });
            
            // Observar cambios en el documento
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        } else {
            // Fallback para navegadores muy antiguos (no recomendado)
            console.warn('MutationObserver no soportado, funcionalidad dinámica limitada');
        }
    }

    /**
     * Validar decimales en campo de precio
     */
    function validarDecimalesPrecio(campo) {
        const $campo = $(campo);
        const valor = parseFloat($campo.val());
        const decimalesPermitidos = parseInt(mvDecimalesConfig.decimales_precio);
        
        if (isNaN(valor) || valor < 0) {
            mostrarErrorCampo($campo, mvConfig.textos?.error_precio_negativo || 'El precio debe ser un número positivo');
            return false;
        }
        
        // Verificar decimales
        const valorStr = $campo.val();
        const partes = valorStr.split('.');
        
        if (partes.length > 1 && partes[1].length > decimalesPermitidos) {
            const valorCorregido = valor.toFixed(decimalesPermitidos);
            $campo.val(valorCorregido);
            mostrarAdvertenciaCampo($campo, `Máximo ${decimalesPermitidos} decimales permitidos`);
            return false;
        }
        
        limpiarErrorCampo($campo);
        return true;
    }

    /**
     * Validar decimales en campo de cantidad
     */
    function validarDecimalesCantidad(campo) {
        const $campo = $(campo);
        const valor = parseFloat($campo.val());
        const decimalesPermitidos = parseInt(mvDecimalesConfig.decimales_cantidad);
        
        if (isNaN(valor) || valor <= 0) {
            mostrarErrorCampo($campo, mvConfig.textos?.error_cantidad_cero || 'La cantidad debe ser mayor a cero');
            return false;
        }
        
        // Verificar decimales solo si hay restricción
        if (decimalesPermitidos >= 0) {
            const valorStr = $campo.val();
            const partes = valorStr.split('.');
            
            if (partes.length > 1) {
                const decimalesActuales = partes[1].length;
                
                if (decimalesActuales > decimalesPermitidos) {
                    // Corregir automáticamente redondeando
                    const valorCorregido = valor.toFixed(decimalesPermitidos);
                    $campo.val(valorCorregido);
                    
                    if (decimalesPermitidos === 0) {
                        mostrarAdvertenciaCampo($campo, 'Solo se permiten cantidades enteras');
                    } else {
                        mostrarAdvertenciaCampo($campo, `Máximo ${decimalesPermitidos} decimales permitidos`);
                    }
                    return false;
                }
            }
        }
        
        limpiarErrorCampo($campo);
        return true;
    }

    /**
     * Formatear precio según configuración
     */
    function formatearPrecio(precio, incluirSimbolo = true) {
        const decimales = parseInt(mvDecimalesConfig.decimales_precio);
        const moneda = mvDecimalesConfig.moneda;
        
        const precioFormateado = new Intl.NumberFormat('es-CL', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }).format(precio);
        
        if (incluirSimbolo) {
            const simbolos = {
                'CLP': '$',
                'USD': 'US$',
                'EUR': '€'
            };
            
            const simbolo = simbolos[moneda] || '$';
            return `${simbolo} ${precioFormateado}`;
        }
        
        return precioFormateado;
    }

    /**
     * Formatear cantidad según configuración
     */
    function formatearCantidad(cantidad) {
        const decimales = parseInt(mvDecimalesConfig.decimales_cantidad);
        
        return new Intl.NumberFormat('es-CL', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }).format(cantidad);
    }

    /**
     * Mostrar error en campo
     */
    function mostrarErrorCampo($campo, mensaje) {
        limpiarErrorCampo($campo);
        
        $campo.addClass('mv-field-error');
        
        const $error = $('<div class="mv-field-error-message">')
            .text(mensaje);
        
        $campo.after($error);
    }

    /**
     * Mostrar advertencia en campo
     */
    function mostrarAdvertenciaCampo($campo, mensaje) {
        limpiarErrorCampo($campo);
        
        $campo.addClass('mv-field-warning');
        
        const $warning = $('<div class="mv-field-warning-message">')
            .text(mensaje);
        
        $campo.after($warning);
        
        // Limpiar advertencia después de 3 segundos
        setTimeout(() => {
            $warning.fadeOut(() => {
                $warning.remove();
                $campo.removeClass('mv-field-warning');
            });
        }, 3000);
    }

    /**
     * Limpiar errores de campo
     */
    function limpiarErrorCampo($campo) {
        $campo.removeClass('mv-field-error mv-field-warning');
        $campo.siblings('.mv-field-error-message, .mv-field-warning-message').remove();
    }

    /**
     * Validar todos los campos del formulario antes de enviar
     */
    function validarFormularioCompleto() {
        let esValido = true;
        
        // Validar precios
        $('.mv-precio-field').each(function() {
            if (!validarDecimalesPrecio(this)) {
                esValido = false;
            }
        });
        
        // Validar cantidades
        $('.mv-cantidad-field').each(function() {
            if (!validarDecimalesCantidad(this)) {
                esValido = false;
            }
        });
        
        return esValido;
    }

    /**
     * Agregar estilos CSS para campos con error
     */
    function agregarEstilosValidacion() {
        if ($('#mv-decimales-styles').length > 0) {
            return; // Ya están agregados
        }
        
        const estilos = `
            <style id="mv-decimales-styles">
            .mv-field-error {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
            }
            
            .mv-field-warning {
                border-color: #ffc107 !important;
                box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
            }
            
            .mv-field-error-message {
                display: block;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.875em;
                color: #dc3545;
            }
            
            .mv-field-warning-message {
                display: block;
                width: 100%;
                margin-top: 0.25rem;
                font-size: 0.875em;
                color: #856404;
                background-color: #fff3cd;
                padding: 0.25rem 0.5rem;
                border-radius: 0.25rem;
                border: 1px solid #ffeaa7;
            }
            
            .mv-precio-field:focus,
            .mv-cantidad-field:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 0.2rem rgba(0, 115, 170, 0.25);
            }
            </style>
        `;
        
        $('head').append(estilos);
    }

    /**
     * Función pública para actualizar configuración
     */
    window.mvActualizarConfigDecimales = function(nuevaConfig) {
        if (nuevaConfig.decimales_precio !== undefined) {
            mvDecimalesConfig.decimales_precio = parseInt(nuevaConfig.decimales_precio);
        }
        if (nuevaConfig.decimales_cantidad !== undefined) {
            mvDecimalesConfig.decimales_cantidad = parseInt(nuevaConfig.decimales_cantidad);
        }
        if (nuevaConfig.moneda !== undefined) {
            mvDecimalesConfig.moneda = nuevaConfig.moneda;
        }
        
        // Limpiar configuración anterior
        $('.mv-decimales-configured').removeClass('mv-decimales-configured');
        
        // Reconfigurar campos
        configurarCamposPrecios();
    };

    // Funciones públicas
    window.mvFormatearPrecio = formatearPrecio;
    window.mvFormatearCantidad = formatearCantidad;
    window.mvValidarFormularioCompleto = validarFormularioCompleto;

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        inicializarValidacionDecimales();
    });

})(jQuery);