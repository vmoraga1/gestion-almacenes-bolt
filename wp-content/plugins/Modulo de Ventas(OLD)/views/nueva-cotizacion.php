<?php
if (!defined('ABSPATH')) {
    exit;
}

ventas_check_permissions();
?>

<style>
    .cliente-selector-container {
        margin-bottom: 20px;
        padding: 15px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .cliente-izquierda,
    .cliente-derecha {
        flex: 1 1 48%;
        min-width: 300px;
    }
    
    .cliente-controls {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .cliente-datos-container {
        margin-top: 15px;
        display: none;
    }
    
    .cliente-datos-container table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .cliente-datos-container th {
        text-align: left;
        width: 30%;
        padding: 8px;
        background: #f8f8f8;
    }
    
    .cliente-datos-container td {
        padding: 8px;
    }
    
    .productos-container {
        margin-top: 20px;
    }
    
    .totales-container {
        margin-top: 20px;
        max-width: 450px;
        margin-left: auto;
    }
    
    .submit-container {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    /* Estilos para la integración con almacenes */
    .selector-almacen {
        width: 100%;
    }

    .mensaje-stock {
        font-size: 11px;
        margin-top: 3px;
        padding: 3px 5px;
        border-radius: 3px;
    }

    .mensaje-stock.error {
        color: #dc3232;
        background-color: #fbeaea;
        border: 1px solid #dc3232;
    }

    .mensaje-stock.success {
        color: #46b450;
        background-color: #ecf7ed;
        border: 1px solid #46b450;
    }

    .no-almacenes {
        color: #999;
        font-style: italic;
        font-size: 12px;
        text-align: center;
        display: block;
        padding: 5px;
    }

    /* Alineación de columnas */
    #productos-list th,
    #productos-list td {
        vertical-align: middle;
        padding: 8px;
    }

    #productos-list .eliminar-producto {
        padding: 2px 8px;
        line-height: 1;
    }

</style>

<div class="wrap">
    <h1>Nueva Cotizaci&oacute;n</h1>

    <form id="nueva-cotizacion-form" method="post">
        <!-- Contenedor principal dividido -->
        <div class="form-field cliente-selector-container">
            <!-- Izquierda - Datos del cliente -->
            <div class="cliente-izquierda">
                <label for="cliente">Cliente:</label>
                <div class="cliente-controls">
                    <select name="cliente" id="cliente" class="regular-text" required></select>
                    <button type="button" class="button button-secondary" id="nuevo-cliente-btn">
                        <span class="dashicons dashicons-plus-alt"></span> Nuevo Cliente
                    </button>
                    <button type="button" class="button button-secondary" id="editar-cliente-btn" style="display:none;">
                        <span class="dashicons dashicons-edit"></span> Editar Cliente
                    </button>
                </div>

                <div id="cliente-datos" class="cliente-datos-container">
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <th>Nombre del cliente</th>
                                <td class="cliente-nombre">-</td>
                            </tr>
                            <tr>
                                <th>Direcci&oacute;n</th>
                                <td class="cliente-direccion">-</td>
                            </tr>
                            <tr>
                                <th>Tel&eacute;fono</th>
                                <td class="cliente-telefono">-</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td class="cliente-email">-</td>
                            </tr>
                            <tr>
                                <th>RUT</th>
                                <td class="cliente-rut">-</td>
                            </tr>
                            <tr>
                                <th>Giro Comercial</th>
                                <td class="cliente-giro">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Derecha - Espacio para nuevos campos -->
            <div class="cliente-derecha">
                <h3>Otra Información</h3>
                <!-- Campos de fecha y plazos -->
                <p>
                    <label for="fecha-cotizacion">Fecha:</label>
                    <input type="date" id="fecha-cotizacion" name="fecha_cotizacion" class="widefat" value="<?php echo date('Y-m-d'); ?>">
                </p>
                <p>
                    <label for="fecha-expiracion">Expiración:</label>
                    <input type="date" id="fecha-expiracion" name="fecha_expiracion" class="widefat">
                </p>
                <p>
                    <label for="plazo-pago">Plazo de Pago:</label>
                    <select id="plazo-pago" name="plazo_pago" class="widefat">
                        <option value="inmediato">Pago Inmediato</option>
                        <option value="credito">Crédito</option>
                    </select>
                </p>
                <p class="campo-credito" style="display: none;">
                    <label for="plazo-credito">Plazo de Crédito:</label>
                    <select id="plazo-credito" name="plazo_credito" class="widefat">
                        <option value="15">15 días</option>
                        <option value="30">30 días</option>
                        <option value="60">60 días</option>
                        <option value="90">90 días</option>
                    </select>
                </p>
                <p>
                    <label for="vendedor">Vendedor:</label>
                    <input type="text" id="vendedor" name="vendedor" class="widefat">
                </p>
                <p>
                    <label for="condiciones_pago">Condiciones de Pago:</label>
                    <input type="text" id="condiciones-pago" name="condiciones_pago" class="widefat">
                </p>
                <p>
                    <div class="iva-option" style="margin: 20px 0;">
                        <label style="font-weight: bold;">
                            <input type="checkbox" id="agregar-iva" name="agregar_iva" value="1" />
                            Agregar IVA (19%)
                        </label>
                    </div>
                    <input type="hidden" name="iva" id="input-iva" value="0" />
                </p>
            </div>
        </div>

        <div class="productos-container">
            <h2>Productos</h2>
                <table class="wp-list-table widefat" id="productos-list">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="30%">Producto</th>
                            <th width="20%">Almacén</th>
                            <th width="10%">Cantidad</th>
                            <th width="12%">Precio Unit.</th>
                            <th width="10%">Descuento %</th>
                            <th width="10%">Total</th>
                            <th width="3%">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Los productos se agregarán dinámicamente aquí -->
                    </tbody>
                </table>

                <button type="button" class="button button-primary" onclick="agregar_producto()">Agregar Producto</button>

        </div>

        <div class="totales-container">
            <table class="wp-list-table widefat">
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td id="subtotal" class="amount">$0.00</td>
                </tr>
                <tr>
                    <td><strong>Descuento Global:</strong></td>
                    <td style="display: flex; align-items: center; gap: 10px;">
                        <input type="number" id="descuento-global" min="0" step="0.01" value="0" style="width: 120px;">
                        <select id="tipo-descuento-global" style="width: 100px;">
                            <option value="porcentaje">Porcentaje</option>
                            <option value="monto">Monto</option>
                        </select>
                        <span id="simbolo-descuento">%</span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Envío:</strong></td>
                    <td>
                        <input type="number" id="envio" min="0" step="0.01" value="0">
                    </td>
                </tr>
                <!-- NUEVO: Total Neto - visible solo cuando hay IVA -->
                <tr id="total-neto-container" style="display: none;">
                    <td><strong>Total Neto:</strong></td>
                    <td id="total-neto" class="amount">$0.00</td>
                </tr>
                <tr id="iva-container" style="display: none;">
                    <td><strong>IVA 19%:</strong></td>
                    <td id="iva-monto" class="amount">$0.00</td>
                </tr>
                <tr class="total-row">
                    <td><strong>Total Final:</strong></td>
                    <td id="total-final" class="amount">$0.00</td>
                </tr>
            </table>

            <!-- Campos ocultos para enviar con el formulario -->
            <input type="hidden" name="subtotal" id="input-subtotal" value="0" />
            <input type="hidden" name="descuento" id="input-descuento" value="0" />
            <input type="hidden" name="envio" id="input-envio" value="0" />
            <input type="hidden" name="total" id="input-total" value="0" />
            <input type="hidden" name="total_neto" id="input-total-neto" value="0" />

        </div>

        <div class="submit-container">
            <button type="button" id="guardar-cotizacion" class="button button-primary">Guardar Cotizaci&oacute;n</button>
            <a href="<?php echo admin_url('admin.php?page=ventas-cotizaciones'); ?>" class="button">Cancelar</a>
        </div>
    </form>
    
    <div id="editar-cliente-modal" title="Editar datos del cliente" style="display:none;">
        <form id="editar-cliente-form" method="post">
            <input type="hidden" name="cliente_id" value="">
            <p>
                <label>Nombre:</label>
                <input type="text" name="nombre" class="widefat">
            </p>
            <p>
                <label>Direcci&oacute;n:</label>
                <input type="text" name="direccion" class="widefat">
            </p>
            <p>
                <label>Tel&eacute;fono:</label>
                <input type="text" name="telefono" class="widefat">
            </p>
            <p>
                <label>Email:</label>
                <input type="email" name="email" class="widefat">
            </p>
            <p>
                <label>RUT:</label>
                <input type="text" name="rut" class="widefat">
            </p>
            <p>
                <label>Giro Comercial:</label>
                <input type="text" name="giro" class="widefat">
            </p>
        </form>
    </div>
    
    <div id="nuevo-cliente-modal" title="Crear nuevo cliente" style="display:none;">
        <form id="nuevo-cliente-form">
            <p>
                <label>Nombre:</label>
                <input type="text" name="nombre" class="widefat" required>
            </p>
            <p>
                <label>Direcci&oacute;n:</label>
                <input type="text" name="direccion" class="widefat">
            </p>
            <p>
                <label>Tel&eacute;fono:</label>
                <input type="text" name="telefono" class="widefat">
            </p>
            <p>
                <label>Email:</label>
                <input type="email" name="email" class="widefat" required>
            </p>
            <p>
                <label>RUT:</label>
                <input type="text" name="rut" class="widefat">
            </p>
            <p>
                <label>Giro Comercial:</label>
                <input type="text" name="giro" class="widefat">
            </p>
        </form>
    </div>
</div>

<script>
    var tienePluginAlmacenes = <?php echo json_encode(class_exists('Gestion_Almacenes_DB')); ?>;

    jQuery(document).ready(function($) {
        console.log('=== SOLUCIÓN DEFINITIVA PARA calcularTotales ===');
        
        // 1. Primero, remover TODOS los event listeners que llaman a calcularTotales
        $(document).off('change', '.cantidad, .precio, .descuento, #descuento-global, #envio, #tipo-descuento-global, #agregar-iva');
        $(document).off('click', '.eliminar-producto');
        
        // 2. Sobrescribir calcularTotales para que use NUESTRA lógica
        window.calcularTotales = function() {
            console.log('calcularTotales interceptada - redirigiendo a actualizar_totales');
            
            // Llamar a nuestra función
            if (typeof actualizar_totales === 'function') {
                actualizar_totales();
            } else {
                console.error('actualizar_totales no está definida aún');
            }
        };
        
        // 3. Esperar un poco y volver a sobrescribir (por si se carga después)
        setTimeout(function() {
            window.calcularTotales = function() {
                if (typeof actualizar_totales === 'function') {
                    actualizar_totales();
                }
            };
            console.log('calcularTotales sobrescrita nuevamente');
        }, 100);
    });

    // TU FUNCIÓN actualizar_totales COMPLETA
    function actualizar_totales() {
        console.log('=== Ejecutando actualizar_totales ===');
        var subtotal = 0;
        
        // IMPORTANTE: La función original busca '.total' como texto, no como input
        // Necesitamos calcular diferente
        jQuery('.producto-row, #productos-lista tr:visible').each(function() {
            var $row = jQuery(this);
            var cantidad = parseFloat($row.find('.cantidad').val()) || 0;
            
            // Buscar precio con las clases correctas
            var precio = 0;
            if ($row.find('.precio_unitario').length) {
                precio = parseFloat($row.find('.precio_unitario').val()) || 0;
            } else if ($row.find('.precio').length) {
                // El código original usa .precio y formatea diferente
                var precioStr = $row.find('.precio').val();
                if (precioStr) {
                    // Remover formato chileno (puntos de miles y coma decimal)
                    precioStr = precioStr.replace(/\./g, '').replace(',', '.');
                    precio = parseFloat(precioStr) || 0;
                }
            }
            
            var descuento = parseFloat($row.find('.descuento').val()) || 0;
            
            var subtotalLinea = cantidad * precio;
            var montoDescuento = (subtotalLinea * descuento) / 100;
            var totalLinea = subtotalLinea - montoDescuento;
            
            // Actualizar el total de la línea
            if ($row.find('.total').is('input')) {
                $row.find('.total').val(totalLinea.toFixed(2));
            } else {
                // Si es un TD (como espera el código original)
                $row.find('.total').text(formatMoney(totalLinea)).data('valor-real', totalLinea);
            }
            
            subtotal += totalLinea;
        });
        
        // Obtener valores de descuento y envío
        var descuento_input = jQuery('#descuento-global').val();
        if (descuento_input) {
            // Remover formato si existe
            descuento_input = descuento_input.toString().replace(/\./g, '').replace(',', '.');
        }
        var descuento_valor = parseFloat(descuento_input) || 0;
        
        var tipo_descuento = jQuery('#tipo-descuento-global').val();
        
        var envio_input = jQuery('#envio').val();
        if (envio_input) {
            // Remover formato si existe
            envio_input = envio_input.toString().replace(/\./g, '').replace(',', '.');
        }
        var envio = parseFloat(envio_input) || 0;
        
        // Calcular descuento
        var descuento_monto = 0;
        if (tipo_descuento === 'porcentaje') {
            descuento_monto = subtotal * (descuento_valor / 100);
        } else {
            descuento_monto = descuento_valor;
        }
        
        // Calcular TOTAL NETO
        var total_neto = subtotal - descuento_monto + envio;
        
        // Calcular IVA
        var iva = 0;
        if (jQuery('#agregar-iva').is(':checked')) {
            iva = total_neto * 0.19;
            jQuery('#iva-container').show();
            jQuery('#total-neto-container').show();
            jQuery('#iva-monto').text(formatMoney(iva));
            jQuery('#total-neto').text(formatMoney(total_neto));
        } else {
            jQuery('#iva-container').hide();
            jQuery('#total-neto-container').hide();
        }
        
        // Total final
        var total_final = total_neto + iva;
        
        // ACTUALIZAR VISUALIZACIÓN - FORZAR
        var subtotalFormateado = formatMoney(subtotal);
        var totalFormateado = formatMoney(total_final);
        
        // Actualizar con múltiples métodos
        jQuery('#subtotal').text(subtotalFormateado);
        jQuery('#subtotal').html(subtotalFormateado);
        jQuery('#subtotal').data('valor-real', subtotal);
        
        jQuery('#total-final').text(totalFormateado);
        jQuery('#total-final').html(totalFormateado);
        jQuery('#total-final').data('valor-real', total_final);
        
        // Si el código original espera el formato con data
        if (jQuery('#subtotal').data('valor-real') !== undefined) {
            jQuery('#subtotal').data('valor-real', subtotal);
        }
        if (jQuery('#total-final').data('valor-real') !== undefined) {
            jQuery('#total-final').data('valor-real', total_final);
        }
        
        // Actualizar campos ocultos
        jQuery('input[name="subtotal"]').val(subtotal.toFixed(2));
        jQuery('input[name="descuento"]').val(descuento_monto.toFixed(2));
        jQuery('input[name="envio"]').val(envio.toFixed(2));
        jQuery('input[name="total_neto"]').val(total_neto.toFixed(2));
        jQuery('input[name="iva"]').val(iva.toFixed(2));
        jQuery('input[name="total"]').val(total_final.toFixed(2));
        
        console.log('Totales calculados:', {
            subtotal: subtotal,
            descuento: descuento_monto,
            envio: envio,
            total_neto: total_neto,
            iva: iva,
            total_final: total_final
        });
    }

    setInterval(function() {
        if (jQuery('#subtotal').text() === '$0,00' && jQuery('.producto-row').length > 0) {
            actualizar_totales();
        }
    }, 200);

    // ASEGURAR que formatMoney existe
    if (typeof formatMoney !== 'function') {
        window.formatMoney = function(amount) {
            if (isNaN(amount) || amount === null) {
                amount = 0;
            }
            
            // Formato chileno: punto para miles, coma para decimales
            const formattedNumber = parseFloat(amount).toFixed(2)
                .replace('.', ',')
                .replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            
            return '$' + formattedNumber;
        };
    }

    // Función auxiliar para forzar actualización
    function forzarActualizacionSubtotal() {
        // Obtener el valor real del subtotal
        var subtotal = 0;
        jQuery('.producto-row').each(function() {
            var total = parseFloat(jQuery(this).find('.total').val()) || 0;
            subtotal += total;
        });
        
        if (subtotal > 0) {
            var subtotalFormateado = formatMoney(subtotal);
            
            // Método 1: Actualizar directamente
            jQuery('#subtotal').text(subtotalFormateado);
            
            // Método 2: Usar innerHTML nativo
            var elemento = document.getElementById('subtotal');
            if (elemento) {
                elemento.innerHTML = subtotalFormateado;
                elemento.textContent = subtotalFormateado;
            }
            
            // Método 3: Reemplazar el elemento completo
            if (jQuery('#subtotal').text() === '$0.00' || jQuery('#subtotal').text() === '$0,00') {
                jQuery('#subtotal').replaceWith('<td id="subtotal" class="amount">' + subtotalFormateado + '</td>');
            }
            
            console.log('Forzando actualización de subtotal:', subtotalFormateado);
        }
    }

    // Sobrescribir completamente la función actualizar_totales
    window.actualizar_totales = function() {
        console.log('=== Ejecutando actualizar_totales (nueva) ===');
        var subtotal = 0;
        
        // Calcular subtotal sumando todos los totales de productos
        jQuery('.producto-row').each(function() {
            var total = parseFloat(jQuery(this).find('.total').val()) || 0;
            subtotal += total;
        });
        
        // Obtener valores de descuento y envío
        var descuento_input = jQuery('#descuento-global').val();
        var descuento_valor = 0;
        
        if (descuento_input !== '' && descuento_input !== '.' && descuento_input !== '-.') {
            descuento_valor = parseFloat(descuento_input) || 0;
        }
        
        var tipo_descuento = jQuery('#tipo-descuento-global').val();
        
        var envio_input = jQuery('#envio').val();
        var envio = 0;
        
        if (envio_input !== '' && envio_input !== '.' && envio_input !== '-.') {
            envio = parseFloat(envio_input) || 0;
        }
        
        // Calcular descuento
        var descuento_monto = 0;
        if (tipo_descuento === 'porcentaje') {
            descuento_monto = subtotal * (descuento_valor / 100);
        } else {
            descuento_monto = descuento_valor;
        }
        
        // Calcular TOTAL NETO
        var total_neto = subtotal - descuento_monto + envio;
        
        // Calcular IVA
        var iva = 0;
        if (jQuery('#agregar-iva').is(':checked')) {
            iva = total_neto * 0.19;
            jQuery('#iva-container').show();
            jQuery('#total-neto-container').show();
            jQuery('#iva-monto').text(formatMoney(iva));
            jQuery('#total-neto').text(formatMoney(total_neto));
        } else {
            jQuery('#iva-container').hide();
            jQuery('#total-neto-container').hide();
        }
        
        // Total final
        var total_final = total_neto + iva;
        
        // FORZAR ACTUALIZACIÓN MÚLTIPLES VECES
        var subtotalFormateado = formatMoney(subtotal);
        var totalFormateado = formatMoney(total_final);
        
        // Actualizar inmediatamente
        jQuery('#subtotal').text(subtotalFormateado);
        jQuery('#total-final').text(totalFormateado);
        
        // Actualizar con un pequeño delay
        setTimeout(function() {
            jQuery('#subtotal').text(subtotalFormateado);
            jQuery('#total-final').text(totalFormateado);
            forzarActualizacionSubtotal();
        }, 10);
        
        // Actualizar nuevamente después de 100ms
        setTimeout(function() {
            if (jQuery('#subtotal').text() === '$0.00' || jQuery('#subtotal').text() === '$0,00') {
                forzarActualizacionSubtotal();
            }
        }, 100);
        
        // También guardar en data para compatibilidad
        jQuery('#subtotal').data('valor-real', subtotal);
        jQuery('#total-final').data('valor-real', total_final);
        
        // Actualizar campos ocultos
        jQuery('input[name="subtotal"]').val(subtotal.toFixed(2));
        jQuery('input[name="descuento"]').val(descuento_monto.toFixed(2));
        jQuery('input[name="envio"]').val(envio.toFixed(2));
        jQuery('input[name="total_neto"]').val(total_neto.toFixed(2));
        jQuery('input[name="iva"]').val(iva.toFixed(2));
        jQuery('input[name="total"]').val(total_final.toFixed(2));
        
        console.log('Totales actualizados:', {
            subtotal: subtotal,
            subtotal_formateado: subtotalFormateado,
            elemento_subtotal: jQuery('#subtotal').text()
        });
    };

    // INTERCEPTAR Y BLOQUEAR cualquier intento de poner el subtotal en 0
    jQuery(document).ready(function($) {
        // Crear un MutationObserver para detectar cambios
        var targetNode = document.getElementById('subtotal');
        if (targetNode) {
            var config = { childList: true, characterData: true, subtree: true };
            
            var callback = function(mutationsList, observer) {
                for(var mutation of mutationsList) {
                    var currentText = $('#subtotal').text();
                    if (currentText === '$0.00' || currentText === '$0,00') {
                        console.log('Detectado intento de poner subtotal en 0, corrigiendo...');
                        observer.disconnect(); // Desconectar temporalmente para evitar loop
                        forzarActualizacionSubtotal();
                        setTimeout(function() {
                            observer.observe(targetNode, config); // Reconectar
                        }, 100);
                    }
                }
            };
            
            var observer = new MutationObserver(callback);
            observer.observe(targetNode, config);
        }
    });

    /**
     * Función para calcular el total de una línea
     */
    function calcular_total(index) {
        console.log('calcular_total llamada para fila:', index);
        
        var $fila = jQuery('#producto_row_' + index);
        var cantidad = parseFloat($fila.find('.cantidad').val()) || 0;
        var precio = parseFloat($fila.find('.precio_unitario').val()) || 0;
        var descuento = parseFloat($fila.find('.descuento').val()) || 0;
        
        var subtotal = cantidad * precio;
        var descuento_monto = subtotal * (descuento / 100);
        var total = subtotal - descuento_monto;
        
        // IMPORTANTE: Asegurarse de que el valor se establece correctamente
        $fila.find('.total').val(total.toFixed(2));
        
        console.log('Total calculado para fila ' + index + ':', {
            cantidad: cantidad,
            precio: precio,
            descuento: descuento,
            total: total,
            elemento_total: $fila.find('.total').length,
            valor_establecido: $fila.find('.total').val()
        });
        
        // Llamar a actualizar_totales después de establecer el valor
        actualizar_totales();
    }

    /**
     * Cargar stock de almacenes para un producto
     */
    function cargarStockAlmacenes(productoId, filaIndex) {
        console.log('cargarStockAlmacenes llamada - Producto:', productoId, 'Fila:', filaIndex);
        
        // Verificar que el selector existe
        var $selector = jQuery('#almacen_' + filaIndex);
        console.log('Selector encontrado:', $selector.length > 0);
        
        if ($selector.length === 0) {
            console.error('No se encontró el selector de almacén para la fila ' + filaIndex);
            return;
        }
        
        // Mostrar mensaje de carga
        $selector.html('<option value="">Cargando almacenes...</option>');
        
        jQuery.ajax({
            url: ventasAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'ventas_get_stock_almacenes',
                nonce: ventasAjax.nonce,
                producto_id: productoId
            },
            success: function(response) {
                console.log('Respuesta recibida:', response);
                if (response.success && response.data.almacenes) {
                    console.log('Actualizando selector con', response.data.almacenes.length, 'almacenes');
                    actualizarSelectorAlmacen(filaIndex, response.data.almacenes);
                } else {
                    console.error('Respuesta sin almacenes');
                    $selector.html('<option value="">Error al cargar almacenes</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                $selector.html('<option value="">Error al cargar almacenes</option>');
            }
        });
    }

    /**
     * Actualizar el selector de almacén con los datos de stock
     */
    function actualizarSelectorAlmacen(filaIndex, almacenes) {
        console.log('actualizarSelectorAlmacen llamada - Fila:', filaIndex, 'Almacenes:', almacenes);
        
        var $selector = jQuery('#almacen_' + filaIndex);
        
        if ($selector.length === 0) {
            console.error('Selector no encontrado para fila ' + filaIndex);
            return;
        }
        
        // Limpiar el selector
        $selector.empty();
        
        // Opción por defecto
        $selector.append('<option value="">Seleccionar almacén...</option>');
        
        // Verificar si hay almacenes
        if (!almacenes || almacenes.length === 0) {
            console.warn('No hay almacenes disponibles');
            $selector.append('<option value="" disabled>No hay almacenes disponibles</option>');
            return;
        }
        
        console.log('Agregando ' + almacenes.length + ' almacenes al selector');
        
        // Agregar opciones de almacenes con stock
        jQuery.each(almacenes, function(i, almacen) {
            console.log('Procesando almacén:', almacen);
            
            var textoOpcion = almacen.almacen_nombre + ' (Stock: ' + almacen.stock_disponible + ')';
            var $option = jQuery('<option></option>')
                .attr('value', almacen.almacen_id)
                .text(textoOpcion)
                .data('stock', almacen.stock_disponible)
                .data('nombre', almacen.almacen_nombre);
                
            if (parseInt(almacen.stock_disponible) <= 0) {
                $option.prop('disabled', true);
                $option.text(textoOpcion + ' - Sin stock');
            }
            
            $selector.append($option);
        });
        
        console.log('Opciones agregadas. Total de opciones:', $selector.find('option').length);
        
        // Si el selector tiene Select2, actualizarlo
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
        
        if (!$selectorAlmacen.val()) {
            $mensajeStock.text('').removeClass('error success');
            return true;
        }
        
        if (cantidad > stockDisponible) {
            $mensajeStock
                .addClass('error')
                .removeClass('success')
                .text('⚠️ Stock insuficiente. Disponible: ' + stockDisponible);
            return false;
        } else {
            $mensajeStock
                .addClass('success')
                .removeClass('error')
                .text('✓ Stock disponible');
            return true;
        }
    }

    function agregar_producto() {
        var index = jQuery('.producto-row').length;
        var html = '<tr id="producto_row_' + index + '" class="producto-row">';
        
        // Columna 1: Número
        html += '<td>' + (index + 1) + '</td>';
        
        // Columna 2: Producto
        html += '<td>';
        html += '<select name="productos[' + index + '][producto_id]" class="buscar-producto" style="width: 100%;" required>';
        html += '<option value="">Buscar producto...</option>';
        html += '</select>';
        html += '</td>';
        
        // Columna 3: Almacén
        html += '<td>';
        if (tienePluginAlmacenes) {
            html += '<select name="productos[' + index + '][almacen_id]" ';
            html += 'id="almacen_' + index + '" ';
            html += 'class="selector-almacen" ';
            html += 'style="width: 100%;">';
            html += '<option value="">Seleccionar almacén...</option>';
            html += '</select>';
            html += '<input type="hidden" name="productos[' + index + '][almacen_nombre]" ';
            html += 'id="almacen_nombre_' + index + '" />';
            html += '<div class="mensaje-stock"></div>';
        } else {
            html += '<span class="no-almacenes">Plugin de almacenes no activo</span>';
        }
        html += '</td>';
        
        // Columna 4: Cantidad
        html += '<td><input type="number" name="productos[' + index + '][cantidad]" class="cantidad" min="1" value="1" style="width: 100%;" required /></td>';
        
        // Columna 5: Precio unitario
        html += '<td><input type="number" name="productos[' + index + '][precio_unitario]" class="precio_unitario" step="0.01" min="0" style="width: 100%;" required /></td>';
        
        // Columna 6: Descuento
        html += '<td><input type="number" name="productos[' + index + '][descuento]" class="descuento" min="0" max="100" value="0" style="width: 100%;" /></td>';
        
        // Columna 7: Total
        html += '<td><input type="text" name="productos[' + index + '][total]" class="total" readonly style="width: 100%;" /></td>';
        
        // Columna 8: Acción
        html += '<td><button type="button" class="button eliminar-producto" onclick="eliminar_producto(' + index + ')">×</button></td>';
        
        html += '</tr>';
        
        jQuery('#productos-list tbody').append(html);
        
        // Inicializar Select2 para el buscador de productos
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
            minimumInputLength: 2,
            placeholder: 'Buscar producto...'
        });
        
        // Eventos
        var $fila = jQuery('#producto_row_' + index);
        
        // Al seleccionar producto
        $fila.find('.buscar-producto').on('select2:select', function(e) {
            var data = e.params.data;
            $fila.find('.precio_unitario').val(data.price);
            calcular_total(index);
            
            // Cargar almacenes si el plugin está activo
            if (tienePluginAlmacenes && typeof cargarStockAlmacenes === 'function') {
                cargarStockAlmacenes(data.id, index);
            }
        });

        // ADICIONALMENTE, agregar un trigger para cuando el precio se establece inicialmente:
        jQuery(document).ready(function($) {
            // Después de que se carga un producto y se establece su precio
            $(document).on('precio_establecido', function() {
                actualizar_totales();
            });
            
            // También actualizar totales cuando se elimine una fila
            $(document).on('click', '.eliminar-producto', function() {
                setTimeout(function() {
                    actualizar_totales();
                }, 100);
            });
        });

        // OPCIONAL: Si quieres que se actualice también cuando cambien manualmente los precios
        jQuery(document).on('change keyup', '.precio_unitario, .cantidad, .descuento', function() {
            var $row = jQuery(this).closest('.producto-row');
            var index = $row.attr('id').replace('producto_row_', '');
            calcular_total(index);
        });
        
        // Al cambiar cantidad
        $fila.find('.cantidad').on('change keyup', function() {
            calcular_total(index);
            if (typeof validarStockAlmacen === 'function') {
                validarStockAlmacen(index);
            }
        });
        
        // Al cambiar almacén
        $fila.find('.selector-almacen').on('change', function() {
            var nombreAlmacen = jQuery(this).find('option:selected').data('nombre') || '';
            jQuery('#almacen_nombre_' + index).val(nombreAlmacen);
            if (typeof validarStockAlmacen === 'function') {
                validarStockAlmacen(index);
            }
        });
        
        // Otros eventos
        $fila.find('.precio_unitario, .descuento').on('change keyup', function() {
            calcular_total(index);
        });
    }

    

    // TAMBIÉN, agregar un test para verificar que los elementos existen:
    function verificar_elementos_totales() {
        console.log('=== Verificación de elementos ===');
        console.log('Filas de productos:', jQuery('.producto-row').length);
        console.log('Inputs .total:', jQuery('.producto-row .total').length);
        console.log('Valores de totales:');
        jQuery('.producto-row').each(function(index) {
            var $fila = jQuery(this);
            console.log('Fila ' + index + ':', {
                total_input: $fila.find('.total').length,
                valor: $fila.find('.total').val()
            });
        });
    }

    // Ejecutar verificación cuando se carga la página
    jQuery(document).ready(function($) {
        // Verificar después de un pequeño retraso
        setTimeout(function() {
            verificar_elementos_totales();
        }, 1000);
    });

    /**
     * Función para eliminar un producto
     */
    function eliminar_producto(index) {
        jQuery('#producto_row_' + index).remove();
        actualizar_totales();
        
        // Renumerar las filas restantes
        jQuery('.producto-row').each(function(i) {
            jQuery(this).find('td:first').text(i + 1);
        });
    }

    

    // Función auxiliar para formatear números con separadores de miles
    function formatearNumero(numero) {
        return numero.toLocaleString('es-CL', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Alternativa si toLocaleString no funciona bien:
    function formatearNumeroAlternativo(numero) {
        var partes = numero.toFixed(2).split('.');
        partes[0] = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return partes.join('.');
    }

    // Agregar eventos para actualizar totales
    jQuery(document).ready(function($) {
        // Eventos para actualización en tiempo real
        $('#descuento-global, #envio').on('input', function() {
            // 'input' se dispara con cada cambio en el valor
            actualizar_totales();
        });
        
        // Evento para tipo de descuento
        $('#tipo-descuento-global').on('change', function() {
            // Cambiar el símbolo según el tipo
            if ($(this).val() === 'porcentaje') {
                $('#simbolo-descuento').text('%');
            } else {
                $('#simbolo-descuento').text('$');
            }
            actualizar_totales();
        });
        
        // Formatear al salir del campo (mantener para limpieza)
        $('#descuento-global, #envio').on('blur', function() {
            var valor = $(this).val();
            
            // Si está vacío, no hacer nada (dejar vacío)
            if (valor === '') {
                return;
            }
            
            // Si solo tiene punto decimal, convertir a 0
            if (valor === '.' || valor === '-.') {
                $(this).val('0');
                actualizar_totales();
                return;
            }
            
            // Convertir a número para limpiar formato
            var numero = parseFloat(valor);
            if (!isNaN(numero)) {
                // Opcional: limitar decimales a 2
                $(this).val(numero.toFixed(2));
            }
        });
        
        // Actualizar cuando cambie el checkbox de IVA
        $('#agregar-iva').on('change', function() {
            actualizar_totales();
        });
        
        // Inicializar totales
        actualizar_totales();
    });

    jQuery(document).ready(function($) {
        // Validación para campos numéricos
        $('#descuento-global, #envio').on('input', function(e) {
            var valor = $(this).val();
            var valorAnterior = $(this).data('valor-anterior') || '';
            
            // Permitir vacío
            if (valor === '') {
                $(this).data('valor-anterior', valor);
                return;
            }
            
            // Permitir un solo punto decimal al inicio o después de números
            if (valor === '.') {
                $(this).data('valor-anterior', valor);
                return;
            }
            
            // Validar formato numérico
            var regex = /^\d*\.?\d*$/;
            
            if (regex.test(valor)) {
                // Valor válido, guardar para referencia
                $(this).data('valor-anterior', valor);
            } else {
                // Valor inválido, restaurar el anterior
                $(this).val(valorAnterior);
                e.preventDefault();
            }
        });
        
        // Prevenir pegado de texto no numérico
        $('#descuento-global, #envio').on('paste', function(e) {
            e.preventDefault();
            var pastedData = (e.originalEvent || e).clipboardData.getData('text');
            
            // Limpiar el texto pegado
            var cleanedData = pastedData.replace(/[^\d.]/g, '');
            
            // Asegurar solo un punto decimal
            var parts = cleanedData.split('.');
            if (parts.length > 2) {
                cleanedData = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Insertar el texto limpio
            this.value = cleanedData;
            $(this).trigger('input');
        });
    });

    

    // Eventos adicionales para los totales
    jQuery(document).ready(function($) {
        // Actualizar totales cuando cambien descuento global o envío
        $('#descuento_global, #costo_envio').on('change keyup', function() {
            actualizar_totales();
        });
        
        // Inicializar totales
        actualizar_totales();
    });

    

    

    // Debug: Verificar la integración con almacenes
    jQuery(document).ready(function($) {
        console.log('=== DEBUG INTEGRACIÓN ALMACENES ===');
        console.log('Plugin de almacenes activo:', tienePluginAlmacenes);
        console.log('URL AJAX:', ventasAjax.ajax_url);
        console.log('Nonce:', ventasAjax.nonce);
        
        // Probar manualmente la carga de almacenes
        window.testCargarAlmacenes = function(productoId) {
            console.log('Probando carga de almacenes para producto:', productoId);
            
            $.ajax({
                url: ventasAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ventas_get_stock_almacenes',
                    nonce: ventasAjax.nonce,
                    producto_id: productoId
                },
                success: function(response) {
                    console.log('Respuesta exitosa:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error en la petición:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        };
        
        console.log('Para probar, ejecuta en consola: testCargarAlmacenes(ID_PRODUCTO)');
    });

    function verificarElementosVisuales() {
        console.log('=== Verificación de elementos visuales ===');
        
        // Verificar elemento subtotal
        var $subtotal = jQuery('#subtotal');
        console.log('Elemento #subtotal:', {
            existe: $subtotal.length > 0,
            tipo: $subtotal.prop('tagName'),
            clase: $subtotal.attr('class'),
            contenido_actual: $subtotal.text() || $subtotal.val()
        });
        
        // Verificar elemento total-final
        var $totalFinal = jQuery('#total-final');
        console.log('Elemento #total-final:', {
            existe: $totalFinal.length > 0,
            tipo: $totalFinal.prop('tagName'),
            clase: $totalFinal.attr('class'),
            contenido_actual: $totalFinal.text() || $totalFinal.val()
        });
        
        // Intentar actualizar directamente
        console.log('Intentando actualizar directamente...');
        $subtotal.text('$TEST');
        $totalFinal.text('$TEST');
        
        setTimeout(function() {
            console.log('Valores después de actualización directa:');
            console.log('Subtotal:', $subtotal.text() || $subtotal.val());
            console.log('Total Final:', $totalFinal.text() || $totalFinal.val());
        }, 100);
    }

    // Ejecutar verificación
    jQuery(document).ready(function($) {
        setTimeout(verificarElementosVisuales, 2000);
    });

    // DESPUÉS de definir todas tus funciones, agregar los event listeners
    jQuery(document).ready(function($) {
        // Esperar un momento para asegurar que todo esté cargado
        setTimeout(function() {
            console.log('Agregando nuevos event listeners');
            
            // Eventos para actualización en tiempo real
            $('#descuento-global, #envio').on('input', function() {
                actualizar_totales();
            });
            
            // Evento para tipo de descuento
            $('#tipo-descuento-global').on('change', function() {
                if ($(this).val() === 'porcentaje') {
                    $('#simbolo-descuento').text('%');
                } else {
                    $('#simbolo-descuento').text('$');
                }
                actualizar_totales();
            });
            
            // Evento para checkbox de IVA
            $('#agregar-iva').on('change', function() {
                actualizar_totales();
            });
            
            // Eventos para los productos
            $(document).on('change keyup', '.cantidad, .precio_unitario, .descuento', function() {
                var $row = $(this).closest('.producto-row');
                var index = $row.attr('id').replace('producto_row_', '');
                calcular_total(index);
            });
            
            // Inicializar totales
            actualizar_totales();
            
        }, 500); // Esperar 500ms para asegurar que todo esté cargado
    });

    jQuery(document).ready(function($) {
        // Interceptar TODAS las funciones que podrían estar cambiando el subtotal
        
        // 1. Guardar referencia al método text original
        var originalText = $.fn.text;
        
        // 2. Sobrescribir temporalmente el método text
        $.fn.text = function() {
            // Si están intentando cambiar el subtotal a $0.00
            if (this.attr('id') === 'subtotal' && arguments.length > 0) {
                var nuevoValor = arguments[0];
                console.warn('INTENTO DE CAMBIAR SUBTOTAL:', {
                    elemento: this[0],
                    valor_actual: this.text(),
                    nuevo_valor: nuevoValor,
                    stack: new Error().stack
                });
                
                // Si intentan poner $0.00, ignorarlo
                if (nuevoValor === '$0.00' || nuevoValor === '$0,00') {
                    console.error('BLOQUEADO: Intento de poner subtotal en 0');
                    return this; // No hacer nada
                }
            }
            
            // Llamar al método original
            return originalText.apply(this, arguments);
        };
        
        // 3. Monitorear cambios en el DOM
        console.log('Iniciando monitoreo del subtotal...');
        
        // 4. Verificar cada 500ms si el subtotal fue cambiado
        setInterval(function() {
            var valorActual = $('#subtotal').text();
            var valorReal = $('#subtotal').data('valor-real') || 0;
            
            if ((valorActual === '$0.00' || valorActual === '$0,00') && valorReal > 0) {
                console.error('Subtotal fue cambiado a 0! Valor real:', valorReal);
                $('#subtotal').text(formatMoney(valorReal));
            }
        }, 500);
    });


</script>