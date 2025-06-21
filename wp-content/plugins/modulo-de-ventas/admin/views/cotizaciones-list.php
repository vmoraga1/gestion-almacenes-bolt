<?php
/**
 * Vista del listado de cotizaciones
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('view_cotizaciones')) {
    wp_die(__('No tienes permisos para ver esta página.', 'modulo-ventas'));
}

// Crear instancia de la tabla
$cotizaciones_table = new Cotizaciones_List_Table();
$cotizaciones_table->prepare_items();

// Obtener estadísticas
$db = new Modulo_Ventas_DB();
$estadisticas = $db->obtener_estadisticas_cotizaciones();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Cotizaciones', 'modulo-ventas'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=modulo-ventas-nueva-cotizacion'); ?>" class="page-title-action">
        <?php _e('Añadir nueva', 'modulo-ventas'); ?>
    </a>
    
    <?php if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])): ?>
        <span class="subtitle">
            <?php printf(__('Resultados de búsqueda para: %s', 'modulo-ventas'), '<strong>' . esc_html($_REQUEST['s']) . '</strong>'); ?>
        </span>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php
    // Mostrar mensajes
    Modulo_Ventas_Messages::get_instance()->display_messages();
    ?>
    
    <!-- Estadísticas rápidas -->
    <div class="mv-stats-container">
        <div class="mv-stat-box">
            <span class="mv-stat-number"><?php echo esc_html($estadisticas['total']); ?></span>
            <span class="mv-stat-label"><?php _e('Total Cotizaciones', 'modulo-ventas'); ?></span>
        </div>
        <div class="mv-stat-box">
            <span class="mv-stat-number"><?php echo esc_html($estadisticas['pendientes']); ?></span>
            <span class="mv-stat-label"><?php _e('Pendientes', 'modulo-ventas'); ?></span>
        </div>
        <div class="mv-stat-box">
            <span class="mv-stat-number"><?php echo esc_html($estadisticas['aceptadas']); ?></span>
            <span class="mv-stat-label"><?php _e('Aceptadas', 'modulo-ventas'); ?></span>
        </div>
        <div class="mv-stat-box">
            <span class="mv-stat-number"><?php echo wc_price($estadisticas['monto_total']); ?></span>
            <span class="mv-stat-label"><?php _e('Monto Total', 'modulo-ventas'); ?></span>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get">
        <input type="hidden" name="page" value="modulo-ventas-cotizaciones">
        
        <!-- Búsqueda -->
        <?php $cotizaciones_table->search_box(__('Buscar cotizaciones', 'modulo-ventas'), 'buscar'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="estado" id="filter-by-estado">
                    <option value=""><?php _e('Todos los estados', 'modulo-ventas'); ?></option>
                    <?php
                    $estados = ventas_get_estados_cotizacion();
                    $estado_actual = isset($_REQUEST['estado']) ? $_REQUEST['estado'] : '';
                    foreach ($estados as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($estado_actual, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <!-- Filtro por fecha -->
                <label for="fecha_desde" class="screen-reader-text"><?php _e('Fecha desde', 'modulo-ventas'); ?></label>
                <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo esc_attr(isset($_REQUEST['fecha_desde']) ? $_REQUEST['fecha_desde'] : ''); ?>" placeholder="<?php esc_attr_e('Fecha desde', 'modulo-ventas'); ?>">
                
                <label for="fecha_hasta" class="screen-reader-text"><?php _e('Fecha hasta', 'modulo-ventas'); ?></label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo esc_attr(isset($_REQUEST['fecha_hasta']) ? $_REQUEST['fecha_hasta'] : ''); ?>" placeholder="<?php esc_attr_e('Fecha hasta', 'modulo-ventas'); ?>">
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filtrar', 'modulo-ventas'); ?>">
                
                <?php if (isset($_REQUEST['estado']) || isset($_REQUEST['fecha_desde']) || isset($_REQUEST['fecha_hasta']) || isset($_REQUEST['s'])) : ?>
                    <a href="<?php echo admin_url('admin.php?page=modulo-ventas-cotizaciones'); ?>" class="button">
                        <?php _e('Limpiar filtros', 'modulo-ventas'); ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Acciones adicionales -->
            <div class="alignright">
                <a href="#" class="button" id="exportar-cotizaciones">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Exportar', 'modulo-ventas'); ?>
                </a>
            </div>
        </div>
    </form>

    <!-- Tabla de cotizaciones -->
    <form id="cotizaciones-form" method="post">
        <?php 
        $cotizaciones_table->views();
        $cotizaciones_table->display(); 
        ?>
    </form>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Exportar cotizaciones
        $('#exportar-cotizaciones').on('click', function(e) {
            e.preventDefault();
            
            var filtros = {
                estado: $('#filter-by-estado').val(),
                fecha_desde: $('#fecha_desde').val(),
                fecha_hasta: $('#fecha_hasta').val(),
                busqueda: $('input[name="s"]').val()
            };
            
            // Agregar parámetros a la URL
            var url = ajaxurl + '?action=mv_exportar_cotizaciones&nonce=' + '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>';
            
            $.each(filtros, function(key, value) {
                if (value) {
                    url += '&' + key + '=' + encodeURIComponent(value);
                }
            });
            
            window.location.href = url;
        });
    });
    </script>

</div>

<style>
/* Estadísticas */
.mv-stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.mv-stat-box {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.mv-stat-number {
    display: block;
    font-size: 32px;
    font-weight: 600;
    color: #2271b1;
    margin-bottom: 5px;
}

.mv-stat-label {
    display: block;
    color: #646970;
    font-size: 14px;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .mv-stats-container {
        grid-template-columns: 1fr 1fr;
    }
    
    .mv-stat-box {
        padding: 15px;
    }
    
    .mv-stat-number {
        font-size: 24px;
    }
}
</style>