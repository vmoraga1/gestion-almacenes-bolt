jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Módulo de Ventas: Iniciando scripts PDF...');
    
    // Verificar que las variables necesarias estén disponibles
    if (typeof ajaxurl === 'undefined') {
        console.error('ajaxurl no está definido');
        return;
    }
    
    if (typeof moduloVentasAjax === 'undefined') {
        console.error('moduloVentasAjax no está definido - verificar wp_localize_script');
        // Fallback básico
        window.moduloVentasAjax = {
            nonce: $('#modulo_ventas_nonce').val() || 'fallback_nonce',
            ajaxurl: ajaxurl
        };
    }
    
    console.log('Variables disponibles:', {
        ajaxurl: ajaxurl,
        nonce: moduloVentasAjax.nonce
    });
    
    // ===========================================
    // NUEVO CÓDIGO PARA MANEJAR PDFs VÍA AJAX
    // ===========================================
    
    /**
     * Función principal para generar PDFs
     */
    function generarPDFCotizacion(cotizacionId, accion) {
        console.log('Generando PDF - ID:', cotizacionId, 'Acción:', accion);
        
        if (!cotizacionId) {
            alert('Error: ID de cotización no válido');
            return;
        }
        
        // Mostrar indicador de carga
        var $btn = $('.mv-btn-pdf-' + (accion === 'download' ? 'download' : 'preview'));
        var originalText = $btn.text();
        
        $btn.prop('disabled', true)
            .addClass('mv-pdf-loading')
            .text(accion === 'download' ? 'Preparando descarga...' : 'Generando vista previa...');
        
        // Petición AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mv_generar_pdf_cotizacion',
                cotizacion_id: cotizacionId,
                accion: accion,
                nonce: moduloVentasAjax.nonce
            },
            timeout: 30000, // 30 segundos
            success: function(response) {
                console.log('Respuesta AJAX exitosa:', response);
                
                if (response.success) {
                    var data = response.data;
                    
                    if (accion === 'preview' && data.preview_url) {
                        // Abrir PDF en nueva ventana
                        window.open(data.preview_url, '_blank', 'width=900,height=700,scrollbars=yes,resizable=yes');
                    } else if (accion === 'download' && data.download_url) {
                        // Iniciar descarga
                        window.location.href = data.download_url;
                    } else {
                        alert('PDF generado exitosamente, pero URL no disponible');
                    }
                    
                    // Mostrar mensaje de éxito opcional
                    if (data.message) {
                        mostrarNotificacion(data.message, 'success');
                    }
                    
                } else {
                    console.error('Error en respuesta:', response);
                    alert('Error: ' + (response.data.message || 'Error desconocido'));
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                console.error('Error AJAX completo:', {
                    xhr: xhr,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    responseText: xhr.responseText
                });
                
                var errorMsg = 'Error de conexión al generar PDF.\n\n';
                errorMsg += 'Estado: ' + textStatus + '\n';
                errorMsg += 'Error: ' + errorThrown + '\n';
                errorMsg += 'Código HTTP: ' + xhr.status;
                
                if (textStatus === 'parsererror') {
                    errorMsg += '\n\n⚠ Error de parsing - El servidor devolvió contenido no válido';
                } else if (xhr.status === 0) {
                    errorMsg += '\n\n⚠ Error de red - Verificar conexión';
                } else if (xhr.status === 403) {
                    errorMsg += '\n\n⚠ Error de permisos';
                } else if (xhr.status === 404) {
                    errorMsg += '\n\n⚠ Handler AJAX no encontrado';
                }
                
                alert(errorMsg);
            },
            complete: function() {
                // Restaurar botón siempre
                $btn.prop('disabled', false)
                    .removeClass('mv-pdf-loading')
                    .text(originalText);
            }
        });
    }
    
    // ===========================================
    // EVENT HANDLERS PARA BOTONES PDF
    // ===========================================
    
    // Botón Ver PDF (Preview)
    $(document).on('click', '.mv-btn-pdf-preview, .mv-pdf-preview-button', function(e) {
        e.preventDefault();
        console.log('Click en Ver PDF');
        
        var cotizacionId = $(this).data('cotizacion-id') || $(this).closest('tr').data('cotizacion-id');
        
        if (!cotizacionId) {
            // Intentar obtener de la URL si estamos en página de detalle
            var urlParams = new URLSearchParams(window.location.search);
            cotizacionId = urlParams.get('cotizacion_id');
        }
        
        if (!cotizacionId) {
            alert('Error: No se pudo determinar el ID de la cotización');
            return;
        }
        
        generarPDFCotizacion(cotizacionId, 'preview');
    });
    
    // Botón Descargar PDF
    $(document).on('click', '.mv-btn-pdf-download, .mv-pdf-download-button', function(e) {
        e.preventDefault();
        console.log('Click en Descargar PDF');
        
        var cotizacionId = $(this).data('cotizacion-id') || $(this).closest('tr').data('cotizacion-id');
        
        if (!cotizacionId) {
            // Intentar obtener de la URL si estamos en página de detalle
            var urlParams = new URLSearchParams(window.location.search);
            cotizacionId = urlParams.get('cotizacion_id');
        }
        
        if (!cotizacionId) {
            alert('Error: No se pudo determinar el ID de la cotización');
            return;
        }
        
        generarPDFCotizacion(cotizacionId, 'download');
    });
    
    // ===========================================
    // RESTO DEL CÓDIGO EXISTENTE (mantener)
    // ===========================================
    
    // Manejar envío por email (placeholder)
    $(document).on('click', '.mv-btn-email', function(e) {
        e.preventDefault();
        var cotizacionId = $(this).data('cotizacion-id');
        
        // Placeholder - implementar modal de email después
        var email = prompt('Ingrese el email del destinatario:');
        if (email && email.trim()) {
            // TODO: Implementar envío por email real
            alert('Función de envío por email en desarrollo.\nCotización: ' + cotizacionId + '\nEmail: ' + email);
        }
    });
    
    // Manejar conversión a venta con confirmación
    $(document).on('click', '.mv-btn-convert', function(e) {
        var confirmed = confirm('¿Está seguro de convertir esta cotización a venta?\n\nEsta acción creará un pedido en WooCommerce y no se puede deshacer.');
        if (!confirmed) {
            e.preventDefault();
            return false;
        }
        
        // Agregar indicador de carga
        var $btn = $(this);
        $btn.addClass('mv-pdf-loading');
        $btn.prop('disabled', true);
        $btn.find('.dashicons').removeClass('dashicons-cart').addClass('dashicons-update');
    });
    
    // Función auxiliar para mostrar notificaciones
    function mostrarNotificacion(mensaje, tipo) {
        tipo = tipo || 'success';
        
        var $notice = $('<div class="notice notice-' + tipo + ' is-dismissible"><p>' + mensaje + '</p></div>');
        
        if ($('.wrap > h1').length) {
            $notice.insertAfter('.wrap > h1');
        } else {
            $notice.prependTo('.wrap');
        }
        
        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

        
    // Mejorar tooltips en dispositivos móviles
    if (window.innerWidth <= 782) {
        $('.mv-actions-buttons .button[title]').each(function() {
            var $btn = $(this);
            var title = $btn.attr('title');
            
            // Remover title para evitar tooltip nativo en móvil
            $btn.removeAttr('title');
            
            // Agregar data-title para referencia
            $btn.attr('data-title', title);
        });
    }

    
    // Debug: Confirmar carga del script
    console.log('Módulo de Ventas: Scripts PDF cargados correctamente');
});

// ===========================================
// FUNCIONES GLOBALES (mantener para compatibilidad)
// ===========================================

// Función global para generar PDF de cotización
window.mvGenerarPDF = function(cotizacionId, modo) {
    console.log('mvGenerarPDF llamada:', cotizacionId, modo);
    
    if (!cotizacionId) {
        console.error('ID de cotización requerido');
        return;
    }
    
    // Usar la nueva función AJAX
    jQuery(document).ready(function($) {
        generarPDFCotizacion(cotizacionId, modo || 'preview');
    });
};