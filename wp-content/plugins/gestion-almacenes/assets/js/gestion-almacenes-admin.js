jQuery(document).ready(function ($) {
    function updateStockInfo() {
        var productId = $('#product_id').val();
        var warehouseId = $('#from_warehouse').val();

        if (productId && warehouseId) {
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
                        $('#stock_info').text('Stock disponible: ' + response.data.stock + ' unidades');
                        $('#quantity').attr('max', response.data.stock);
                    } else {
                        $('#stock_info').text('No hay stock disponible en este almacén');
                        $('#quantity').attr('max', 0);
                    }
                }
            });
        } else {
            $('#stock_info').text('Selecciona un producto y almacén origen para ver el stock disponible.');
            $('#quantity').removeAttr('max');
        }
    }

    $('#product_id, #from_warehouse').on('change', updateStockInfo);

    $('#to_warehouse').on('change', function () {
        var fromWarehouse = $('#from_warehouse').val();
        var toWarehouse = $(this).val();

        if (fromWarehouse && toWarehouse && fromWarehouse === toWarehouse) {
        alert('Los almacenes origen y destino deben ser diferentes.');
        $(this).val('');
        }
    });
});
