jQuery(document).ready(function($) {
    'use strict';
    
    // Manejar clics en botones PDF de la tabla
    $('.mv-btn-pdf-preview, .mv-btn-pdf-download').on('click', function(e) {
        var $btn = $(this);
        var isPreview = $btn.hasClass('mv-btn-pdf-preview');
        
        // Agregar indicador de carga
        $btn.addClass('mv-pdf-loading');
        $btn.prop('disabled', true);
        
        // Para preview, no necesitamos hacer nada especial ya que abre en nueva ventana
        // Para download, tampoco necesitamos AJAX ya que es descarga directa
        
        // Restaurar botón después de un tiempo
        setTimeout(function() {
            $btn.removeClass('mv-pdf-loading');
            $btn.prop('disabled', false);
        }, isPreview ? 2000 : 3000);
    });
    
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

/**
 * TAMBIÉN AGREGAR ESTAS FUNCIONES GLOBALES PARA USO EXTERNO
 */

// Función global para generar PDF de cotización
window.mvGenerarPDF = function(cotizacionId, modo) {
    modo = modo || 'preview';
    
    if (!cotizacionId) {
        console.error('ID de cotización requerido');
        return;
    }
    
    // Crear URL
    var url = ajaxurl + '?action=mv_generar_pdf_cotizacion&cotizacion_id=' + cotizacionId + '&modo=' + modo;
    
    // Agregar nonce si está disponible
    if (typeof moduloVentasAjax !== 'undefined' && moduloVentasAjax.nonce) {
        url += '&_wpnonce=' + moduloVentasAjax.nonce;
    }
    
    if (modo === 'preview') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
};

// Función global para enviar PDF por email
window.mvEnviarPDFEmail = function(cotizacionId, email) {
    if (!cotizacionId || !email) {
        console.error('ID de cotización y email requeridos');
        return;
    }
    
    // TODO: Implementar envío real por AJAX
    console.log('Enviando PDF de cotización', cotizacionId, 'a', email);
    alert('Función de envío por email en desarrollo');
};