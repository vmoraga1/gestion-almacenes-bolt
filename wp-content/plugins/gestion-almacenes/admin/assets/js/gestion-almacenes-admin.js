jQuery(document).ready(function ($) {
    'use strict';

    /**
     * Funciones para la página de Transferencias de Stock
     */
    if ($('#gab-transfer-form').length) {
        initTransferFunctions();
    }

    /**
     * Funciones para la página de Reportes de Stock
     */
    if ($('#gab-stock-report').length) {
        initReportFunctions();
    }

    /**
     * Inicializar funciones de transferencia
     */
    function initTransferFunctions() {
        // Actualizar información de stock cuando cambian producto o almacén
        $('#product_id, #from_warehouse').on('change', updateStockInfo);
        
        // Validar que almacenes origen y destino sean diferentes
        $('#to_warehouse').on('change', validateWarehouseSelection);
        
        // Validar formulario antes de enviar
        $('#gab-transfer-form').on('submit', validateTransferForm);
        
        // Inicializar select2 si está disponible
        if ($.fn.select2) {
            $('#product_id, #from_warehouse, #to_warehouse').select2({
                width: '100%',
                placeholder: 'Seleccionar...'
            });
        }
    }

    /**
     * Actualizar información de stock disponible
     */
    function updateStockInfo() {
        var productId = $('#product_id').val();
        var warehouseId = $('#from_warehouse').val();

        // Limpiar información anterior
        $('#stock_info').text('Selecciona un producto y almacén origen para ver el stock disponible.');
        $('#quantity').removeAttr('max').val('');

        if (productId && warehouseId) {
            // Mostrar indicador de carga
            $('#stock_info').html('<span class="spinner is-active" style="float: none;"></span> Consultando stock...');
            
            $.ajax({
                url: gestionAlmacenesAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_warehouse_stock',
                    product_id: productId,
                    warehouse_id: warehouseId,
                    nonce: gestionAlmacenesAjax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var stock = parseInt(response.data.stock) || 0;
                        if (stock > 0) {
                            $('#stock_info').html('<strong style="color: #00a32a;">Stock disponible: ' + stock + ' unidades</strong>');
                            $('#quantity').attr('max', stock).attr('min', 1);
                        } else {
                            $('#stock_info').html('<strong style="color: #d63638;">No hay stock disponible en este almacén</strong>');
                            $('#quantity').attr('max', 0);
                        }
                    } else {
                        $('#stock_info').html('<strong style="color: #d63638;">Error al consultar stock</strong>');
                    }
                },
                error: function () {
                    $('#stock_info').html('<strong style="color: #d63638;">Error de conexión</strong>');
                }
            });
        }
    }

    /**
     * Validar selección de almacenes
     */
    function validateWarehouseSelection() {
        var fromWarehouse = $('#from_warehouse').val();
        var toWarehouse = $('#to_warehouse').val();

        if (fromWarehouse && toWarehouse && fromWarehouse === toWarehouse) {
            alert('Los almacenes origen y destino deben ser diferentes.');
            $('#to_warehouse').val('').trigger('change');
        }
    }

    /**
     * Validar formulario de transferencia
     */
    function validateTransferForm(e) {
        var productId = $('#product_id').val();
        var fromWarehouse = $('#from_warehouse').val();
        var toWarehouse = $('#to_warehouse').val();
        var quantity = parseInt($('#quantity').val()) || 0;
        var maxStock = parseInt($('#quantity').attr('max')) || 0;

        var errors = [];

        if (!productId) {
            errors.push('Debe seleccionar un producto.');
        }

        if (!fromWarehouse) {
            errors.push('Debe seleccionar un almacén origen.');
        }

        if (!toWarehouse) {
            errors.push('Debe seleccionar un almacén destino.');
        }

        if (quantity <= 0) {
            errors.push('La cantidad debe ser mayor a 0.');
        }

        if (maxStock > 0 && quantity > maxStock) {
            errors.push('La cantidad no puede ser mayor al stock disponible (' + maxStock + ').');
        }

        if (fromWarehouse && toWarehouse && fromWarehouse === toWarehouse) {
            errors.push('Los almacenes origen y destino deben ser diferentes.');
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert('Errores encontrados:\n\n• ' + errors.join('\n• '));
            return false;
        }

        // Confirmación antes de transferir
        var confirmMessage = '¿Confirma la transferencia de ' + quantity + ' unidades?';
        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }

        return true;
    }

    /**
     * Inicializar funciones de reportes
     */
    function initReportFunctions() {
        // Manejar filtros de reporte
        $('#warehouse_filter, #product_filter').on('change', function() {
            filterStockReport();
        });

        // Funcionalidad de exportar (si se implementa)
        $('#export_report').on('click', function(e) {
            e.preventDefault();
            exportStockReport();
        });
    }

    /**
     * Filtrar reporte de stock
     */
    function filterStockReport() {
        // Esta función se implementará cuando se complete la página de reportes
        console.log('Filtros aplicados');
    }

    /**
     * Exportar reporte de stock
     */
    function exportStockReport() {
        // Esta función se implementará cuando se complete la funcionalidad de exportación
        alert('Funcionalidad de exportación en desarrollo');
    }

    /**
     * Funciones generales de utilidad
     */

    // Confirmar eliminaciones
    $('.gab-delete-confirm').on('click', function(e) {
        var message = $(this).data('confirm-message') || '¿Está seguro de que desea eliminar este elemento?';
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });

    // Mostrar/ocultar spinners en formularios
    $('form').on('submit', function() {
        var $submitButton = $(this).find('input[type="submit"], button[type="submit"]');
        $submitButton.prop('disabled', true);
        
        // Restaurar botón después de 5 segundos como medida de seguridad
        setTimeout(function() {
            $submitButton.prop('disabled', false);
        }, 5000);
    });

    // Validación en tiempo real para campos numéricos
    $('input[type="number"]').on('input', function() {
        var min = parseInt($(this).attr('min'));
        var max = parseInt($(this).attr('max'));
        var value = parseInt($(this).val());

        if (!isNaN(min) && value < min) {
            $(this).val(min);
        }

        if (!isNaN(max) && value > max) {
            $(this).val(max);
        }
    });

    // Auto-hide notices después de 5 segundos
    setTimeout(function() {
        $('.notice.is-dismissible').fadeOut();
    }, 5000);

    // Cuando construyas el array products, asegúrate de que tenga esta estructura:
var products = [];



});