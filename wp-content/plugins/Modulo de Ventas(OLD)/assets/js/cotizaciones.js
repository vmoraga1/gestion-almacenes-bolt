jQuery(document).ready(function($) {
    console.log("Archivo cotizaciones.js cargado");

    function agregar_producto() {
        var index = jQuery('.producto-row').length;
        var html = '<tr id="producto_row_' + index + '" class="producto-row">';
        html += '<td>' + (index + 1) + '</td>';
        html += '<td>';
        html += '<select name="productos[' + index + '][producto_id]" class="buscar-producto" style="width: 100%;" required>';
        html += '<option value="">Buscar producto...</option>';
        html += '</select>';
        html += '</td>';
        
        // Nueva columna de almacén
        html += '<td>';
        html += '<select name="productos[' + index + '][almacen_id]" ';
        html += 'id="almacen_' + index + '" ';
        html += 'class="selector-almacen" ';
        html += 'style="width: 100%;">';
        html += '<option value="">Seleccionar almacén...</option>';
        html += '</select>';
        html += '<input type="hidden" name="productos[' + index + '][almacen_nombre]" ';
        html += 'id="almacen_nombre_' + index + '" />';
        html += '</td>';
        
        html += '<td><input type="number" name="productos[' + index + '][cantidad]" class="cantidad" min="1" value="1" required /></td>';
        html += '<td><input type="number" name="productos[' + index + '][precio_unitario]" class="precio_unitario" step="0.01" min="0" required /></td>';
        html += '<td><input type="number" name="productos[' + index + '][descuento]" class="descuento" min="0" max="100" value="0" /></td>';
        html += '<td><input type="text" name="productos[' + index + '][total]" class="total" readonly /></td>';
        html += '<td><button type="button" class="button eliminar-producto" onclick="eliminar_producto(' + index + ')">×</button></td>';
        html += '</tr>';
        
        jQuery('#productos-list tbody').append(html);
        
        // Inicializar Select2
        jQuery('#producto_row_' + index + ' .buscar-producto').select2({
            ajax: {
                url: ventasAjax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'buscar_productos',
                        q: params.term,
                        nonce: ventasAjax.nonce
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.data
                    };
                },
                cache: true
            },
            minimumInputLength: 2
        });
        
        // Eventos
        var $fila = jQuery('#producto_row_' + index);
        
        // Al seleccionar producto
        $fila.find('.buscar-producto').on('select2:select', function(e) {
            var data = e.params.data;
            $fila.find('.precio_unitario').val(data.price);
            calcular_total(index);
            cargarStockAlmacenes(data.id, index);
        });
        
        // Al cambiar cantidad
        $fila.find('.cantidad').on('change keyup', function() {
            calcular_total(index);
            validarStockAlmacen(index);
        });
        
        // Al cambiar almacén
        $fila.find('.selector-almacen').on('change', function() {
            var nombreAlmacen = jQuery(this).find('option:selected').data('nombre') || '';
            jQuery('#almacen_nombre_' + index).val(nombreAlmacen);
            validarStockAlmacen(index);
        });
        
        // Otros eventos existentes
        $fila.find('.precio_unitario, .descuento').on('change keyup', function() {
            calcular_total(index);
        });
    }

    // AGREGAR VALIDACIÓN AL FORMULARIO
    jQuery(document).ready(function($) {
        // Validar antes de guardar
        $('#form-nueva-cotizacion').on('submit', function(e) {
            var hayErrores = false;
            var mensajesError = [];
            
            $('.producto-row').each(function(index) {
                var $fila = $(this);
                var productoId = $fila.find('.buscar-producto').val();
                var almacenId = $fila.find('.selector-almacen').val();
                var cantidad = parseInt($fila.find('.cantidad').val()) || 0;
                
                if (productoId && !almacenId) {
                    hayErrores = true;
                    mensajesError.push('Fila ' + (index + 1) + ': Debe seleccionar un almacén');
                }
                
                if (productoId && almacenId && !validarStockAlmacen(index)) {
                    hayErrores = true;
                    mensajesError.push('Fila ' + (index + 1) + ': Stock insuficiente en el almacén seleccionado');
                }
            });
            
            if (hayErrores) {
                e.preventDefault();
                alert('Por favor corrija los siguientes errores:\n\n' + mensajesError.join('\n'));
                return false;
            }
        });
    });

    // Función para agregar producto
    $('#agregar-producto').on('click', function() {
        console.log("Agregar producto clickeado")
        const $template = $('.producto-template').clone();
        $template.removeClass('producto-template').show();
        
        // Remover cualquier instancia previa de Select2 antes de clonar
        const $select = $template.find('.producto-select');
        if ($select.data('select2')) {
            $select.select2('destroy');
        }
        
        $('#productos-lista').append($template);
        
        // Inicializar Select2 solo para el nuevo select
        const $newSelect = $template.find('.producto-select');
        $newSelect.select2({
            ajax: {
                url: ventasAjax.ajax_url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'buscar_productos',
                        nonce: ventasAjax.nonce,
                        q: params.term
                    };
                },
                processResults: function(data) {
                    if (data.success === false) {
                        console.error('Error:', data.data.message);
                        return { results: [] };
                    }
                    return { results: data.data };
                },
                cache: true
            },
            minimumInputLength: 2,
            language: {
                errorLoading: function() {
                    return 'No se pudieron cargar los resultados...';
                },
                inputTooShort: function() {
                    return 'Por favor ingrese 2 o más caracteres';
                },
                noResults: function() {
                    return 'No se encontraron resultados';
                },
                searching: function() {
                    return 'Buscando...';
                }
            }
        }).on('select2:select', function(e) {
            const data = e.params.data;
            const $row = $(this).closest('tr');
            
            // Actualizar el texto mostrado en el select2 para mostrar solo el SKU
            const $select = $(this);
            
            // Modificar el texto mostrado en el select2 después de la selección
            setTimeout(function() {
                $select.next('.select2-container').find('.select2-selection__rendered').text(data.sku);
            }, 0);
            
            $row.find('.producto-nombre').val(data.name);
            // Formatear el precio antes de mostrarlo con punto como separador de miles
            const precioFormateado = Math.round(data.price).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            $row.find('.precio').val(precioFormateado).attr('title', precioFormateado);
            calcularTotales();
        });
    });

    // Corregir el formato de moneda en los cálculos
    function formatMoney(amount) {
        if (isNaN(amount) || amount === null) {
            amount = 0;
        }
        
        // No redondear el monto, mantener decimales
        const formattedNumber = parseFloat(amount).toFixed(2)
            .replace('.', ',')  // Cambiar punto decimal por coma
            .replace(/\B(?=(\d{3})+(?!\d))/g, ".");  // Agregar separadores de miles
        
        return ventasAjax.currency_symbol + formattedNumber;
    }

    function calcularTotales() {
        let subtotal = 0;
        $('#productos-lista tr:visible').each(function() {
            const cantidad = parseFloat($(this).find('.cantidad').val()) || 0;
            const precioStr = $(this).find('.precio').val().replace(/\./g, '').replace(',', '.');
            const precio = parseFloat(precioStr) || 0;
            const descuentoPorcentaje = parseFloat($(this).find('.descuento').val()) || 0;
    
            const subtotalLinea = cantidad * precio;
            const montoDescuento = (subtotalLinea * descuentoPorcentaje) / 100;
            const total = subtotalLinea - montoDescuento; // Este es el valor numérico real
    
            $(this).find('.total')
                .text(formatMoney(total)) // Usar el valor formateado para mostrar
                .data('valor-real', total); // Usar el valor numérico real para data
    
            subtotal += total;
        });
    
        const descuentoGlobalStr = $('#descuento-global').val().replace(/\./g, '').replace(',', '.');
        const descuentoGlobalValor = parseFloat(descuentoGlobalStr) || 0;
        const tipoDescuento = $('#tipo-descuento-global').val();
        let montoDescuentoGlobal = 0;
    
        if (tipoDescuento === 'porcentaje') {
            montoDescuentoGlobal = (subtotal * descuentoGlobalValor) / 100;
        } else {
            montoDescuentoGlobal = descuentoGlobalValor;
        }
    
        const envioStr = $('#envio').val().replace(/\./g, '').replace(',', '.');
        const envio = parseFloat(envioStr) || 0;
        let totalSinIVA = subtotal - montoDescuentoGlobal + envio;
    
        let montoIVA = 0;
        if ($('#agregar-iva').is(':checked')) {
            montoIVA = totalSinIVA * 0.19;
            $('#iva-monto').text(formatMoney(montoIVA));
            $('#iva-container').show();
        } else {
            $('#iva-container').hide();
        }
    
        const totalFinal = totalSinIVA + montoIVA; // Este es el valor numérico real
    
        $('#subtotal')
            .text(formatMoney(subtotal)) // Usar el valor formateado para mostrar
            .data('valor-real', subtotal); // Usar el valor numérico real para data
    
        $('#total-final')
            .text(formatMoney(totalFinal)) // Usar el valor formateado para mostrar
            .data('valor-real', totalFinal); // Usar el valor numérico real para data
    }

    // Agregar evento para cambio de tipo de descuento
    $(document).on('change', '#tipo-descuento-global', function() {
        const tipo = $(this).val();
        const $simbolo = $('#simbolo-descuento');
        const $input = $('#descuento-global');
        
        if (tipo === 'porcentaje') {
            $simbolo.text('%');
            $input.attr({
                'max': '100',
                'step': '1'
            }).val('0');
        } else {
            $simbolo.text(ventasAjax.currency_symbol);
            $input.attr({
                'max': '',
                'step': '1'
            }).val('0');
        }
        
        calcularTotales();
    });

    // Actualizar los eventos para recalcular
    $(document).on('change', '.cantidad, .precio, .descuento, #descuento-global, #envio, #tipo-descuento-global', calcularTotales);
    
    // Eliminar producto
    $(document).on('click', '.eliminar-producto', function() {
        $(this).closest('tr').remove();
        calcularTotales();
    });

    // Agregar el evento para el checkbox de IVA
    $(document).on('change', '#agregar-iva', calcularTotales);

    // Actualizar los eventos para recalcular (agregar el nuevo evento)
    $(document).on('change', '.cantidad, .precio, .descuento, #descuento-global, #envio, #tipo-descuento-global, #agregar-iva', calcularTotales);


    // CLIENTES //
    // Inicializar Select2 para búsqueda de clientes
    $('#cliente').select2({
        ajax: {
            url: ventasAjax.ajax_url,
            dataType: 'json',
            delay: 100,
            data: function(params) {
                return {
                    q: params.term,
                    action: 'buscar_clientes',
                    nonce: ventasAjax.nonce
                };
            },
            processResults: function(data) {
                return {
                    results: data.data
                };
            }
        },
        language: {
            noResults: function() {
                return "No se encontraron resultados";
            },
            searching: function() {
                return "Buscando...";
            }
        },
        escapeMarkup: function(markup) {
            return markup;
        },
        templateResult: function(data) {
            if (data.loading) {
                return data.text;
            }
            return $('<div>').text(data.text).html();
        },
        minimumInputLength: 2,
        placeholder: 'Buscar cliente...',
        width: '100%'
    }).on('select2:select', function(e) {
        const clienteId = e.params.data.id;
        if (clienteId) {
            $('#editar-cliente-btn').show();
            $('#nuevo-cliente-btn').hide();
            cargarDatosCliente(clienteId);
        }
    }).on('select2:unselect', function() {
        $('#editar-cliente-btn').hide();
        $('#nuevo-cliente-btn').show();
    }).on('select2:open', function() {
        // Agregar botón al pie del dropdown
        setTimeout(() => {
            const $dropdown = $('.select2-container--open');
            const $results = $dropdown.find('.select2-results');
            
            // Verificar si ya existe el botón
            if (!$dropdown.find('.nuevo-cliente-footer').length) {
                const $footer = $('<div class="nuevo-cliente-footer" style="padding: 10px; text-align: center; border-top: 1px solid #ddd; background: #f8f9fa;">' +
                    '<button type="button" class="button button-primary nuevo-cliente-modal-btn" style="width: 100%;">+ Nuevo Cliente</button>' +
                    '</div>');
                
                $results.append($footer);
                
                // Agregar el evento click al botón
                $footer.find('.nuevo-cliente-modal-btn').on('click', function() {
                    const $modal = $('#nuevo-cliente-modal');
                    $modal.dialog({
                        modal: true,
                        width: 500,
                        buttons: {
                            "Crear": function() {
                                crearNuevoCliente($(this));
                            },
                            "Cancelar": function() {
                                $(this).dialog('close');
                            }
                        }
                    });
                    $('#nuevo-cliente-form')[0].reset();
                    $('#cliente').select2('close');
                });
            }
        }, 100);
    }).on('select2:close', function() {
        // Limpiar el footer cuando se cierra el dropdown
        $('.nuevo-cliente-footer').remove();
    });
    

    // Escuchar el evento personalizado
    $(document).on('clienteSeleccionado', function(event, clienteId, clienteData) {
        cargarDatosCliente(clienteId);
    });
    
    $('#customer').select2({
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    action: 'wqs_search_customers',
                    nonce: '<?php echo wp_create_nonce("wqs_nonce"); ?>'
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            }
        },
        minimumInputLength: 2,
        placeholder: 'Buscar cliente...',
        width: '300px'
    });

    // Insertar botón "Nuevo Cliente" al pie del dropdown de Select2
    let botonInsertado = false;

    $('#customer').on('select2:open', function () {
        if (!botonInsertado) {
            setTimeout(() => {
                const $dropdown = $('.select2-container--open');
                const $results = $dropdown.find('.select2-results__options');

                // Crear el botón clonado
                const $btnNuevoCliente = $(
                    `<div class="select2-new-customer-button" style="
                        padding: 10px;
                        text-align: center;
                        font-weight: bold;
                        border-top: 1px solid #ddd;
                        cursor: pointer;
                        background: #f7f7f7;
                        color: #0073aa;
                    ">+ Nuevo Cliente</div>`
                );

                // Insertar el botón al final del contenedor de resultados
                $results.parent().append($btnNuevoCliente);
                botonInsertado = true;

                // Acción al hacer clic en el botón
                $btnNuevoCliente.on('click', function () {
                    window.location.href = '?page=wqs-quotations&action=new&customer_id=0';
                });
            }, 100); // Pequeño retraso para asegurar renderizado
        }
    });

    // Reiniciar estado cuando se cierra el dropdown
    $('#customer').on('select2:close', function () {
        botonInsertado = false;
    });
    
    //Manejador para el botón "Crear Cliente"
    $('#nuevo-cliente-btn').on('click', function() {
        $('#nuevo-cliente-modal').dialog({
            modal: true,
            width: 500,
            buttons: {
                "Crear": function() {
                    crearNuevoCliente($(this));
                },
                "Cancelar": function() {
                    $(this).dialog('close');
                }
            }
        });
    });
    
    // Función para crear nuevo cliente
    function crearNuevoCliente($modal) {
        const $form = $('#nuevo-cliente-form');
        const formData = new FormData($form[0]);
        
        $.ajax({
            url: ventasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'crear_cliente',
                nonce: ventasAjax.nonce,
                nombre: formData.get('nombre'),
                direccion: formData.get('direccion'),
                telefono: formData.get('telefono'),
                email: formData.get('email'),
                rut: formData.get('rut'),
                giro: formData.get('giro')
            },
            success: function(response) {
                if (response.success) {
                    const newOption = new Option(response.data.text, response.data.id, true, true);
                    $('#cliente').append(newOption).trigger('change');
                    cargarDatosCliente(response.data.id);
                    $('#nuevo-cliente-btn').hide();
                    $('#editar-cliente-btn').show();
                    $modal.dialog('close');
                    mostrarMensaje('Cliente creado correctamente', 'success');
                    $form[0].reset();
                } else {
                    mostrarMensaje(response.data.message || 'Error al crear el cliente', 'error');
                }
            },
            error: function() {
                mostrarMensaje('Error al crear el cliente', 'error');
            }
        });
    }
    
    function cargarDatosCliente(clienteId) {
        $.ajax({
            url: ventasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'obtener_datos_cliente',
                nonce: ventasAjax.nonce,
                cliente_id: clienteId
            },
            success: function(response) {
                if (response.success) {
                    mostrarDatosCliente(response.data);
                }
            }
        });
    }

    function mostrarDatosCliente(datos) {
        const $tabla = $('#cliente-datos');
        
        $tabla.find('.cliente-nombre').text(datos.nombre || '-');
        $tabla.find('.cliente-direccion').text(datos.direccion || '-');
        $tabla.find('.cliente-telefono').text(datos.telefono || '-');
        $tabla.find('.cliente-email').text(datos.email || '-');
        $tabla.find('.cliente-rut').text(datos.rut || '-');
        $tabla.find('.cliente-giro').text(datos.giro || '-');

        // Evento para mostrar/ocultar el campo de crédito
        $('#plazo-pago').on('change', function() {
            const $campoPlazoCredito = $('.campo-credito');
            if ($(this).val() === 'credito') {
                $campoPlazoCredito.show();
            } else {
                $campoPlazoCredito.hide();
            }
        });

        $tabla.show();
    }

    // Manejador para el botón de editar cliente
    $('#editar-cliente-btn').on('click', function() {
        const clienteId = $('#cliente').val();
        if (clienteId) {
            abrirModalEditarCliente(clienteId);
        }
    });

    function abrirModalEditarCliente(clienteId) {
        $.ajax({
            url: ventasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'obtener_datos_cliente',
                nonce: ventasAjax.nonce,
                cliente_id: clienteId
            },
            success: function(response) {
                if (response.success) {
                    mostrarModalEditarCliente(response.data);
                }
            }
        });
    }
    
    // Agregar botón "Nuevo Cliente" al pie del modal de Select2
    $('#customer').on('select2:open', function(e) {
        // Crear el botón si no existe ya
        if (!$('.custom-select2-footer').length) {
            const newOption = $(
                '<span class="custom-select2-footer" style="display: block; padding: 10px; text-align: center; border-top: 1px solid #ddd;">' +
                    '<button type="button" class="button button-primary" style="width: 100%; font-weight: bold;">+ Nuevo Cliente</button>' +
                '</span>'
            );
    
            $('.select2-container--open .select2-results__options').last().after(newOption);
        }
    });

// Acción del botón "Nuevo Cliente"
$(document).on('click', '.custom-select2-footer button', function() {
    window.location.href = '?page=wqs-quotations&action=new&customer_id=0';
});

    function mostrarModalEditarCliente(datos) {
        const $form = $('#editar-cliente-form');
        
        // Llenar el formulario con los datos
        $form.find('input[name="cliente_id"]').val(datos.id);
        console.log('ID del cliente cargado:', datos.id);
        
        $form.find('input[name="cliente_id"]').val(datos.id);
        $form.find('input[name="nombre"]').val(datos.nombre);
        $form.find('input[name="direccion"]').val(datos.direccion);
        $form.find('input[name="telefono"]').val(datos.telefono);
        $form.find('input[name="email"]').val(datos.email);
        $form.find('input[name="rut"]').val(datos.rut);
        $form.find('input[name="giro"]').val(datos.giro);

        // Abrir el modal
        $('#editar-cliente-modal').dialog({
            modal: true,
            width: 500,
            buttons: {
                "Guardar": function() {
                    guardarDatosCliente($(this));
                },
                "Cancelar": function() {
                    $(this).dialog('close');
                }
            }
        });
    }

    // Manejador para el botón de editar cliente
    //$(document).on('click', '.editar-cliente', function() {
    //    const clienteId = $(this).data('cliente-id');
    //    abrirModalEditarCliente(clienteId);
    //});

    // Función para guardar datos del cliente
    function guardarDatosCliente($modal) {
        const $form = $('#editar-cliente-form');
        
        // Construir objeto de datos directamente
        const formData = {
            action: 'guardar_datos_cliente',
            nonce: ventasAjax.nonce,
            cliente_id: $form.find('input[name="cliente_id"]').val(),
            nombre: $form.find('input[name="nombre"]').val(),
            direccion: $form.find('input[name="direccion"]').val(),
            telefono: $form.find('input[name="telefono"]').val(),
            email: $form.find('input[name="email"]').val(),
            rut: $form.find('input[name="rut"]').val(),
            giro: $form.find('input[name="giro"]').val()
        };
    
    	// Log para depuración
        console.log('Datos a enviar:', formData);
    
        // Enviar datos como objeto
        $.ajax({
            url: ventasAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    cargarDatosCliente(formData.cliente_id);
                    $modal.dialog('close');
                    mostrarMensaje('Datos actualizados correctamente', 'success');
                } else {
                    mostrarMensaje(response.data.message || 'Error al guardar los datos', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error Ajax:', {xhr, status, error});
                mostrarMensaje('Error al guardar los datos', 'error');
            }
        });
    }
    
    // Nueva función auxiliar
    function actualizarSelect2Cliente(id, nombre, email) {
        const $select = $('#cliente');
        const nuevoTexto = `${nombre} (${email}) - Cliente`;
        const $option = $select.find(`option[value="${id}"]`);
        
        if ($option.length) {
            $option.text(nuevoTexto);
            $select.trigger('change');
        }
    }
    
    function mostrarMensaje(mensaje, tipo = 'success') {
        const $mensaje = $('<div>')
            .addClass('notice notice-' + tipo + ' is-dismissible')
            .append($('<p>').text(mensaje))
            .append('<button type="button" class="notice-dismiss"></button>');

        $('.wrap h1').after($mensaje);

        // Hacer el mensaje descartable
        $mensaje.find('.notice-dismiss').on('click', function() {
            $(this).parent().fadeOut(300, function() { $(this).remove(); });
        });

        // Auto-ocultar despu¨¦s de 5 segundos
        setTimeout(function() {
            $mensaje.fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }
    
    $(document).ready(function() {
        //console.log('Eventos en el formulario:', $._data($('#editar-cliente-form')[0], 'events'));
        //console.log('Handlers de submit:', $._data($('#editar-cliente-form')[0], 'submit'));
    });
    
    // FIN Clientes //


    // Función para guardar la cotización
    $('#guardar-cotizacion').on('click', function() {
        const $guardarBtn = $(this); // Cachear el botón
        $guardarBtn.prop('disabled', true).text('Obteniendo Nonce...'); // Deshabilitar y cambiar texto temporalmente
    
        // 1. Obtener un nonce fresco del servidor
        $.ajax({
            url: ventasAjax.ajax_url, // O directamente ajaxurl si está disponible globalmente
            type: 'POST',
            data: {
                action: 'generar_nonce_cotizacion' // La nueva acción AJAX para generar nonce
            },
            dataType: 'json',
            success: function(nonce_response) {
                console.log('Respuesta de la solicitud de Nonce:', nonce_response); // <-- Añadido: Log de la respuesta del nonce
                
                if (nonce_response.success && nonce_response.data.nonce) {
                    const nonce_fresco = nonce_response.data.nonce;
                    console.log('Nonce fresco obtenido:', nonce_fresco);
    
                    // 2. Proceder a guardar la cotización con el nonce fresco
                    // Recolectar productos
                    let productos = [];
                    $('#productos-lista tr:visible').each(function() {
                        const $row = $(this);
                        const precio = parseFloat($row.find('.precio').val().replace(/\./g, '')) || 0;
                        const cantidad = parseFloat($row.find('.cantidad').val()) || 0;
                        const descuento = parseFloat($row.find('.descuento').val()) || 0;
                        const total = $row.find('.total').data('valor-real');
    
                        productos.push({
                            id: $row.find('.producto-select').val(),
                            cantidad: cantidad,
                            precio: precio,
                            descuento: descuento,
                            total: total
                        });
                    });
    
                    // Preparar datos para enviar (incluir el nonce fresco)
                    const formData = {
                        action: 'guardar_cotizacion',
                        nonce: nonce_fresco, // Usar el nonce recién obtenido
                        cliente_id: $('#cliente').val(),
                        fecha_cotizacion: $('#fecha-cotizacion').val(),
                        fecha_expiracion: $('#fecha-expiracion').val(),
                        plazo_pago: $('#plazo-pago').val(),
                        plazo_credito: $('#plazo-credito').val(),
                        vendedor: $('#vendedor').val(),
                        condiciones_pago: $('#condiciones-pago').val(),
                        agregar_iva: $('#agregar-iva').is(':checked') ? 1 : 0,
                        productos: productos,
                        subtotal: $('#subtotal').data('valor-real'),
                        descuento_global: parseFloat($('#descuento-global').val()) || 0,
                        tipo_descuento_global: $('#tipo-descuento-global').val(),
                        envio: parseFloat($('#envio').val()) || 0,
                        total: $('#total-final').data('valor-real')
                    };
    
                    const jsonData = JSON.stringify(formData);
    
                    // Enviar datos de la cotización
                    $.ajax({
                        url: ventasAjax.ajax_url,
                        type: 'POST',
                        data: jsonData,
                        contentType: 'application/json',
                        dataType: 'json',
                        beforeSend: function() {
                            $guardarBtn.text('Guardando...'); // Cambiar texto del botón
                        },
                        success: function(response) {
                            if (response.success) {
                                mostrarMensaje('Cotización guardada exitosamente', 'success');
                                console.log('Cotización guardada con ID:', response.data.cotizacion_id);
                                habilitarEdicion(response.data.cotizacion_id);
                            } else {
                                mostrarMensaje('Error al guardar cotización: ' + (response.data.message || 'Error desconocido'), 'error');
                                 $guardarBtn.prop('disabled', false).text('Guardar Cotización'); // Re-habilitar botón en caso de error
                            }
                        },
                        complete: function() {
                            // El botón se habilitará o reemplazará en habilitarEdicion() o en caso de error manejado arriba.
                        },
                        error: function(xhr, status, error) {
                             console.error("Error en la solicitud AJAX de guardado:", status, error, xhr);
                             console.log("Respuesta cruda del servidor:", xhr.responseText);
                             mostrarMensaje('Error al guardar la cotización. Consulta la consola para más detalles.', 'error');
                             $guardarBtn.prop('disabled', false).text('Guardar Cotización'); // Re-habilitar botón
                        }
                    });
    
                } else {
                    mostrarMensaje('Error al obtener el nonce de seguridad. Intenta recargar la página.', 'error');
                     $guardarBtn.prop('disabled', false).text('Guardar Cotización'); // Re-habilitar botón
                }
            },
            error: function(xhr, status, error) {
                 console.error("Error en la solicitud AJAX para obtener nonce:", status, error, xhr);
                 console.log("Respuesta cruda del servidor (nonce):", xhr.responseText);
                 mostrarMensaje('Error al obtener el nonce de seguridad. Consulta la consola para más detalles.', 'error');
                 $guardarBtn.prop('disabled', false).text('Guardar Cotización'); // Re-habilitar botón
            }
        });
    });


    // Nueva función para manejar la interfaz después de guardar
    function habilitarEdicion(cotizacionId) {
        // 1. Deshabilitar campos del formulario de entrada
        $('#formulario-cotizacion').find('input, select, textarea').prop('disabled', true);

        // 2. Actualizar el botón "Guardar"
        const guardarBtn = $('#guardar-cotizacion');
        guardarBtn.hide(); // O guardarBtn.remove();

        // 3. Mostrar un botón "Editar" (crear si no existe)
        let editarBtn = $('#editar-cotizacion');
        if (editarBtn.length === 0) {
            editarBtn = $('<button type="button" id="editar-cotizacion" class="button button-primary">Editar Cotización</button>');
            guardarBtn.after(editarBtn); // Añadir el botón después del botón guardar

            // 4. Añadir evento click al botón "Editar"
            editarBtn.on('click', function() {
                // TODO: Lógica para cargar datos de la cotización y habilitar campos
                cargarCotizacionParaEdicion(cotizacionId); // Llama a una función para cargar datos
            });
        } else {
            editarBtn.show(); // Si ya existe, solo mostrarlo
        }

        // Opcional: Almacenar el ID de la cotización en un campo oculto o data attribute
        $('#cotizacion-id').val(cotizacionId); // Asumiendo que tienes un input hidden con id="cotizacion-id"
    }

    // TODO: Implementar la función cargarCotizacionParaEdicion(cotizacionId)
    // Esta función hará un AJAX call al backend para obtener los datos de la cotización
    function cargarCotizacionParaEdicion(cotizacionId) {
        console.log('Cargando datos para editar cotización con ID:', cotizacionId);
        // Aquí iría la llamada AJAX para obtener los datos de la cotización
        // $.ajax({...});
        // Una vez que se reciben los datos, llenar el formulario y habilitar campos.
    }

    /**
     * Cargar stock de almacenes para un producto
     */
    function cargarStockAlmacenes(productoId, filaIndex) {
        jQuery.ajax({
            url: ventasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'ventas_get_stock_almacenes',
                nonce: ventasAjax.nonce,
                producto_id: productoId
            },
            success: function(response) {
                if (response.success) {
                    actualizarSelectorAlmacen(filaIndex, response.data.almacenes);
                }
            }
        });
    }

    /**
     * Actualizar el selector de almacén con los datos de stock
     */
    function actualizarSelectorAlmacen(filaIndex, almacenes) {
        var $selector = jQuery('#almacen_' + filaIndex);
        $selector.empty();
        
        // Opción por defecto
        $selector.append('<option value="">Seleccionar almacén...</option>');
        
        // Agregar opciones de almacenes con stock
        jQuery.each(almacenes, function(i, almacen) {
            var textoOpcion = almacen.almacen_nombre + ' (Stock: ' + almacen.stock_disponible + ')';
            var $option = jQuery('<option>')
                .val(almacen.almacen_id)
                .text(textoOpcion)
                .data('stock', almacen.stock_disponible)
                .data('nombre', almacen.almacen_nombre);
                
            if (almacen.stock_disponible <= 0) {
                $option.prop('disabled', true);
                $option.text(textoOpcion + ' - Sin stock');
            }
            
            $selector.append($option);
        });
        
        // Refrescar Select2 si está activo
        if ($selector.hasClass('select2-hidden-accessible')) {
            $selector.trigger('change');
        }
    }

    /**
     * Validar stock al cambiar cantidad o almacén
     */
    function validarStockAlmacen(filaIndex) {
        var $fila = jQuery('#producto_row_' + filaIndex);
        var cantidad = parseInt($fila.find('.cantidad').val()) || 0;
        var $selectorAlmacen = $fila.find('.selector-almacen');
        var stockDisponible = parseInt($selectorAlmacen.find('option:selected').data('stock')) || 0;
        
        var $mensajeStock = $fila.find('.mensaje-stock');
        if (!$mensajeStock.length) {
            $mensajeStock = jQuery('<div class="mensaje-stock"></div>');
            $selectorAlmacen.after($mensajeStock);
        }
        
        if (cantidad > stockDisponible && $selectorAlmacen.val()) {
            $mensajeStock
                .addClass('error')
                .removeClass('success')
                .text('⚠️ Stock insuficiente. Disponible: ' + stockDisponible);
            return false;
        } else if ($selectorAlmacen.val()) {
            $mensajeStock
                .addClass('success')
                .removeClass('error')
                .text('✓ Stock disponible');
            return true;
        } else {
            $mensajeStock.text('').removeClass('error success');
            return true;
        }
    }

});