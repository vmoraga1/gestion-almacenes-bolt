<?php

if (!defined('ABSPATH')) {
    exit;
}

// Función para mostrar el formulario de agregar nuevo almacén
// Esta función se llama directamente desde la clase Gestion_Almacenes_Admin
function gab_mostrar_formulario_nuevo_almacen() {
    ?>
    <div class="wrap">;
        <h1><?php echo esc_html(__('Agregar Nuevo Almacén', 'gestion-almacenes')); ?></h1>

        <?php
        // Manejar mensajes de error de validación si redirigimos de vuelta a esta página
        if (isset($_GET['status']) && $_GET['status'] === 'error' && isset($_GET['message']) && sanitize_key($_GET['message']) === 'nombre_vacio') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(__('Error: El nombre del almacén no puede estar vacío.', 'gestion-almacenes')) . '</p></div>';
        }
        ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('gab_add_warehouse_nonce', 'gab_warehouse_nonce'); ?>
            <input type="hidden" name="action" value="gab_add_warehouse">

            <table class="form-table">
                <tr>
                    <th><label for="warehouse_name"><?php echo esc_html(__('Nombre del Almacén', 'gestion-almacenes')); ?></label></th>
                    <td><input type="text" name="warehouse_name" id="warehouse_name" class="regular-text" required value="<?php echo isset($_POST['warehouse_name']) ? esc_attr(sanitize_text_field($_POST['warehouse_name'])) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="warehouse_address"><?php echo esc_html(__('Dirección', 'gestion-almacenes')); ?></label></th>
                    <td><textarea name="warehouse_address" id="warehouse_address" class="large-text" required><?php echo isset($_POST['warehouse_address']) ? esc_textarea(sanitize_textarea_field($_POST['warehouse_address'])) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="warehouse_comuna"><?php echo esc_html(__('Comuna', 'gestion-almacenes')); ?></label></th>
                    <td><input type="text" name="warehouse_comuna" id="warehouse_comuna" class="regular-text" required value="<?php echo isset($_POST['warehouse_comuna']) ? esc_attr(sanitize_text_field($_POST['warehouse_comuna'])) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="warehouse_ciudad"><?php echo esc_html(__('Ciudad', 'gestion-almacenes')); ?></label></th>
                    <td><input type="text" name="warehouse_ciudad" id="warehouse_ciudad" class="regular-text" required value="<?php echo isset($_POST['warehouse_ciudad']) ? esc_attr(sanitize_text_field($_POST['warehouse_ciudad'])) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="warehouse_region"><?php echo esc_html(__('Región', 'gestion-almacenes')); ?></label></th>
                    <td><input type="text" name="warehouse_region" id="warehouse_region" class="regular-text" required value="<?php echo isset($_POST['warehouse_region']) ? esc_attr(sanitize_text_field($_POST['warehouse_region'])) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="warehouse_pais"><?php echo esc_html(__('País', 'gestion-almacenes')); ?></label></th>
                    <td><input type="text" name="warehouse_pais" id="warehouse_pais" class="regular-text" required value="<?php echo isset($_POST['warehouse_pais']) ? esc_attr(sanitize_text_field($_POST['warehouse_pais'])) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="warehouse_email"><?php echo esc_html(__('Email', 'gestion-almacenes')); ?></label></th>
                    <td><input type="email" name="warehouse_email" id="warehouse_email" class="regular-text" required value="<?php echo isset($_POST['warehouse_email']) ? esc_attr(sanitize_email($_POST['warehouse_email'])) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="warehouse_telefono"><?php echo esc_html(__('Teléfono', 'gestion-almacenes')); ?></label></th>
                    <td><input type="text" name="warehouse_telefono" id="warehouse_telefono" class="regular-text" required value="<?php echo isset($_POST['warehouse_telefono']) ? esc_attr(sanitize_text_field($_POST['warehouse_telefono'])) : ''; ?>" /></td>
                </tr>
            </table>

            <input type="submit" name="submit_warehouse" class="button-primary" value="<?php echo esc_attr(__('Guardar Almacén', 'gestion-almacenes')); ?>" />
        </form>
    </div>
    <?php
}
