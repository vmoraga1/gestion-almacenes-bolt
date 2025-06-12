<?php
if (!defined('ABSPATH')) {
    exit;
}

ventas_check_permissions();
// Forzar actualización del cache de la tabla
wp_cache_delete('cotizaciones_count', 'ventas');

$cotizaciones_table = new Cotizaciones_List_Table();
$cotizaciones_table->prepare_items();

// Mensajes de estado
if (isset($_GET['message'])) {
    $message = sanitize_text_field($_GET['message']);
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'success';
    $messages = [
        'created' => 'Cotización creada exitosamente.',
        'converted' => 'Cotización convertida a venta exitosamente.',
        'deleted' => 'Cotización eliminada exitosamente.'
    ];
    if (isset($messages[$message])) {
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
        echo '<p>' . esc_html($messages[$message]) . '</p>';
        echo '</div>';
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Cotizaciones</h1>
    <a href="<?php echo admin_url('admin.php?page=ventas-nueva-cotizacion'); ?>" class="page-title-action">Añadir Nueva</a>
    <a href="<?php echo add_query_arg('refresh', '1'); ?>" class="page-title-action">Actualizar Lista</a>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <?php
        // Mostrar la caja de búsqueda
        $cotizaciones_table->search_box('Buscar', 'search_id');
        ?>
        
        <div class="tablenav top">
            <!-- Aquí puedes agregar filtros adicionales si lo deseas -->
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-check"></th>
                    <th scope="col" class="manage-column column-folio">Folio</th>
                    <th scope="col" class="manage-column column-fecha">Fecha</th>
                    <th scope="col" class="manage-column column-cliente">Cliente</th>
                    <th scope="col" class="manage-column column-total">Total</th>
                    <th scope="col" class="manage-column column-estado">Estado</th>
                    <th scope="col" class="manage-column column-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (empty($cotizaciones_table->items)) : ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="6">
                            <div class="notice notice-info inline">
                                <p>No hay cotizaciones disponibles. ¿Deseas <a href="<?php echo admin_url('admin.php?page=ventas-nueva-cotizacion'); ?>">crear una nueva</a>?</p>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php $cotizaciones_table->display_rows(); ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                
            </tfoot>
        </table>
        
        <?php $cotizaciones_table->display_tablenav('bottom'); ?>
    </form>
</div>

<?php require_once VENTAS_PLUGIN_DIR . 'views/convertir-cotizacion.php'; ?>