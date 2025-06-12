<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="convertir-cotizacion-modal" style="display: none;">
    <h2>Convertir Cotización a Venta</h2>
    <form id="convertir-cotizacion-form">
        <input type="hidden" name="cotizacion_id" id="cotizacion_id" value="">
        <div class="form-field">
            <label for="estado_pedido">Estado del Pedido:</label>
            <select name="estado_pedido" id="estado_pedido" required>
                <?php
                $estados = wc_get_order_statuses();
                foreach ($estados as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="submit-buttons">
            <button type="submit" class="button button-primary">Convertir a Venta</button>
            <button type="button" class="button cancel-modal">Cancelar</button>
        </div>
    </form>
</div>