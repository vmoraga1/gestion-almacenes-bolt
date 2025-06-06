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

    // Función para verificar si un producto se agotó
    function checkStockDepletion(productId, oldStock, newStock) {
        if (oldStock > 0 && newStock === 0) {
            // El producto se acaba de agotar
            showDepletionNotification(productId);
            
            // Marcar la fila visualmente
            var $row = $('tr[data-product-id="' + productId + '"]');
            $row.addClass('just-depleted');
            
            // Animación de alerta
            $row.css('background-color', '#ffcccc');
            setTimeout(function() {
                $row.animate({
                    backgroundColor: '#fff5f5'
                }, 1000, function() {
                    $row.addClass('all-stock-depleted');
                });
            }, 100);
        }
    }

    // Función para mostrar notificación de agotamiento
    function showDepletionNotification(productId) {
        var $row = $('tr[data-product-id="' + productId + '"]');
        var productName = $row.find('td:first strong').text();
        
        // Crear notificación
        var notification = $('<div class="notice notice-warning is-dismissible gab-depletion-notice">' +
            '<p><strong>¡Stock Agotado!</strong> El producto "' + productName + '" se ha agotado en todos los almacenes.</p>' +
            '<p><a href="#" class="button button-small replenish-stock" data-product-id="' + productId + '">' +
            'Reponer Stock</a></p>' +
            '</div>');
        
        // Insertar después del encabezado
        notification.insertAfter('.gab-section-header');
        
        // Hacer que el botón de reponer abra el modal de ajuste
        notification.find('.replenish-stock').on('click', function(e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            var $row = $('tr[data-product-id="' + productId + '"]');
            
            // Simular click en el botón de ajustar stock
            $row.find('.adjust-stock').click();
            
            // Cerrar la notificación
            notification.fadeOut();
        });
        
        // Auto-cerrar después de 10 segundos
        setTimeout(function() {
            notification.fadeOut();
        }, 10000);
    }

    // Modificar el handler de ajuste de stock para detectar agotamiento
    var originalAjaxSuccess = $.ajax.prototype.success;
    $(document).on('ajaxSuccess', function(event, xhr, settings) {
        if (settings.data && settings.data.includes('action=gab_adjust_stock')) {
            // Revisar si algún producto se agotó
            checkAllStocksForDepletion();
        }
    });

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

    // al construir el array products, asegurarse de que tenga esta estructura:
    //var products = [];

    /**
     * Funciones para el Modal de Crear/Editar Almacén
     */
    console.log('Inicializando modal de almacén');

    // Variables del modal
    var modal = $('#warehouse-modal');
    var closeBtn = $('.gab-modal-close');
    var cancelBtn = $('.gab-cancel-btn');
    var warehouseForm = $('#warehouse-form');
    var modalTitle = $('#modal-title');
    var submitBtn = $('#submit-btn');
    var formAction = $('#form_action');

    // Verificar que el modal existe
    if (modal.length > 0) {
        console.log('Modal encontrado en el DOM');
        
        // Función para abrir el modal en modo crear
        function openCreateModal() {
            console.log('Abriendo modal en modo crear');
            
            // Configurar el modal para crear
            modalTitle.text('Agregar Nuevo Almacén');
            submitBtn.text('Crear Almacén');
            formAction.val('create');
            
            // Limpiar el formulario
            warehouseForm[0].reset();
            $('#warehouse_id').val('');
            
            // Establecer valores por defecto
            $('#warehouse_pais').val('Chile');
            
            // Mostrar el modal
            modal.fadeIn(300);
        }
        
        // Función para abrir el modal en modo editar
        function openEditModal(warehouseId, rowData) {
            console.log('Abriendo modal en modo editar para ID:', warehouseId);
            
            // Configurar el modal para editar
            modalTitle.text('Editar Almacén');
            submitBtn.text('Guardar Cambios');
            formAction.val('edit');
            
            // Establecer el ID
            $('#warehouse_id').val(warehouseId);
            
            // Llenar con datos básicos de la tabla si están disponibles
            if (rowData) {
                $('#warehouse_name').val(rowData.name || '');
                $('#warehouse_email').val(rowData.email || '');
            }
            
            // Mostrar el modal
            modal.fadeIn(300);
            
            // Cargar datos completos vía AJAX
            if (typeof gestionAlmacenesAjax !== 'undefined') {
                $.ajax({
                    url: gestionAlmacenesAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gab_get_warehouse',
                        warehouse_id: warehouseId,
                        nonce: gestionAlmacenesAjax.nonce
                    },
                    success: function(response) {
                        console.log('Datos del almacén recibidos:', response);
                        
                        if (response.success && response.data) {
                            $('#warehouse_name').val(response.data.name || '');
                            $('#warehouse_address').val(response.data.address || '');
                            $('#warehouse_comuna').val(response.data.comuna || '');
                            $('#warehouse_ciudad').val(response.data.ciudad || '');
                            $('#warehouse_region').val(response.data.region || '');
                            $('#warehouse_pais').val(response.data.pais || 'Chile');
                            $('#warehouse_phone').val(response.data.telefono || '');
                            $('#warehouse_email').val(response.data.email || '');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Error al obtener datos del almacén:', error);
                    }
                });
            }
        }
        
        // Manejar click en botón agregar nuevo
        $('#add-warehouse-btn').on('click', function(e) {
            e.preventDefault();
            openCreateModal();
        });
        
        // Manejar click en botón editar
        $(document).on('click', '.edit-warehouse', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            var warehouseId = $button.data('id');
            
            if (!warehouseId) {
                console.error('No se encontró el ID del almacén');
                return false;
            }
            
            // Obtener datos básicos de la fila
            var $row = $button.closest('tr');
            var rowData = {
                name: $row.find('td:eq(1) strong').first().text().trim(),
                email: $row.find('a[href^="mailto:"]').text().trim()
            };
            
            openEditModal(warehouseId, rowData);
            return false;
        });
        
        // Cerrar modal con X
        closeBtn.on('click', function() {
            console.log('Cerrando modal');
            modal.fadeOut(300, function() {
                warehouseForm[0].reset();
            });
        });
        
        // Cerrar modal con botón Cancelar
        cancelBtn.on('click', function() {
            console.log('Cancelando operación');
            modal.fadeOut(300, function() {
                warehouseForm[0].reset();
            });
        });
        
        // Cerrar modal al hacer click fuera
        modal.on('click', function(e) {
            if ($(e.target).is(modal)) {
                modal.fadeOut(300, function() {
                    warehouseForm[0].reset();
                });
            }
        });
        
        // Prevenir cierre al hacer click dentro del modal
        $('.gab-modal-content').on('click', function(e) {
            e.stopPropagation();
        });
        
        // Manejar envío del formulario
        warehouseForm.on('submit', function(e) {
            e.preventDefault();
            
            var isCreating = formAction.val() === 'create';
            console.log(isCreating ? 'Creando nuevo almacén' : 'Actualizando almacén');
            
            // Recopilar datos del formulario
            var formData = {
                action: isCreating ? 'gab_create_warehouse' : 'gab_update_warehouse',
                warehouse_id: $('#warehouse_id').val(),
                warehouse_name: $('#warehouse_name').val(),
                warehouse_address: $('#warehouse_address').val(),
                warehouse_comuna: $('#warehouse_comuna').val(),
                warehouse_ciudad: $('#warehouse_ciudad').val(),
                warehouse_region: $('#warehouse_region').val(),
                warehouse_pais: $('#warehouse_pais').val(),
                warehouse_phone: $('#warehouse_phone').val(),
                warehouse_email: $('#warehouse_email').val(),
                nonce: gestionAlmacenesAjax.nonce
            };
            
            // Validaciones
            if (!formData.warehouse_name) {
                alert('Por favor ingrese el nombre del almacén');
                $('#warehouse_name').focus();
                return false;
            }
            
            if (!formData.warehouse_address) {
                alert('Por favor ingrese la dirección del almacén');
                $('#warehouse_address').focus();
                return false;
            }
            
            if (!formData.warehouse_email) {
                alert('Por favor ingrese el email del almacén');
                $('#warehouse_email').focus();
                return false;
            }
            
            // Validar email
            if (!isValidEmail(formData.warehouse_email)) {
                alert('Por favor ingrese un email válido');
                $('#warehouse_email').focus();
                return false;
            }
            
            // Validar campos de ubicación
            if (!formData.warehouse_comuna || !formData.warehouse_ciudad || !formData.warehouse_region || !formData.warehouse_pais) {
                alert('Por favor complete todos los campos de ubicación');
                return false;
            }
            
            // Enviar datos vía AJAX
            $.ajax({
                url: gestionAlmacenesAjax.ajax_url,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    submitBtn.prop('disabled', true).text(isCreating ? 'Creando...' : 'Guardando...');
                },
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message || (isCreating ? 'Almacén creado correctamente' : 'Almacén actualizado correctamente');
                        alert(message);
                        modal.fadeOut(300, function() {
                            location.reload();
                        });
                    } else {
                        alert(response.data || 'Error al procesar la solicitud');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', status, error);
                    alert('Error de conexión al servidor');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).text(isCreating ? 'Crear Almacén' : 'Guardar Cambios');
                }
            });
            
            return false;
        });
        
        // Función auxiliar para validar email
        function isValidEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    }

    // Función global de prueba
    window.testModal = function(mode) {
        if (modal.length > 0) {
            if (mode === 'create') {
                openCreateModal();
            } else {
                openEditModal(1, {name: 'Almacén de Prueba', email: 'test@example.com'});
            }
        } else {
            console.log('Modal no encontrado');
        }
    };

    console.log('Para probar el modal: testModal("create") o testModal("edit")');

});