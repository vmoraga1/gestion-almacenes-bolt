<?php
/**
 * Vista del listado de clientes
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
if (!current_user_can('manage_clientes_ventas')) {
    wp_die(__('No tienes permisos para ver esta página.', 'modulo-ventas'));
}

// Obtener estadísticas
$db = new Modulo_Ventas_DB();
$estadisticas = $db->obtener_estadisticas_clientes();
?>

<div class="wrap mv-clientes-list">
    <h1 class="wp-heading-inline">
        <?php _e('Clientes', 'modulo-ventas'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=modulo-ventas-nuevo-cliente'); ?>" class="page-title-action">
        <?php _e('Añadir nuevo', 'modulo-ventas'); ?>
    </a>
    
    <?php if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])): ?>
        <span class="subtitle">
            <?php printf(__('Resultados de búsqueda para: %s', 'modulo-ventas'), '<strong>' . esc_html($_REQUEST['s']) . '</strong>'); ?>
        </span>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php
    // Mostrar mensajes
    if (isset($_GET['mensaje'])) {
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'success';
        $mensaje = '';
        
        switch ($_GET['mensaje']) {
            case 'cliente_creado':
                $mensaje = __('Cliente creado exitosamente.', 'modulo-ventas');
                break;
            case 'cliente_actualizado':
                $mensaje = __('Cliente actualizado exitosamente.', 'modulo-ventas');
                break;
            case 'cliente_eliminado':
                $mensaje = __('Cliente eliminado exitosamente.', 'modulo-ventas');
                break;
            case 'error_eliminar':
                $mensaje = __('Error al eliminar el cliente. Puede tener cotizaciones asociadas.', 'modulo-ventas');
                $tipo = 'error';
                break;
        }
        
        if ($mensaje) {
            echo '<div class="notice notice-' . $tipo . ' is-dismissible"><p>' . $mensaje . '</p></div>';
        }
    }
    ?>
    
    <!-- Estadísticas rápidas -->
    <div class="mv-stats-container">
        <div class="mv-stat-box">
            <h3><?php echo number_format($estadisticas['total_clientes'], 0, ',', '.'); ?></h3>
            <p><?php _e('Clientes Totales', 'modulo-ventas'); ?></p>
        </div>
        <div class="mv-stat-box">
            <h3><?php echo number_format($estadisticas['clientes_activos'], 0, ',', '.'); ?></h3>
            <p><?php _e('Clientes Activos', 'modulo-ventas'); ?></p>
        </div>
        <div class="mv-stat-box">
            <h3><?php echo number_format($estadisticas['nuevos_mes'], 0, ',', '.'); ?></h3>
            <p><?php _e('Nuevos este Mes', 'modulo-ventas'); ?></p>
        </div>
        <div class="mv-stat-box">
            <h3><?php echo number_format($estadisticas['con_cotizaciones'], 0, ',', '.'); ?></h3>
            <p><?php _e('Con Cotizaciones', 'modulo-ventas'); ?></p>
        </div>
    </div>
    
    <!-- Formulario de búsqueda y tabla -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        
        <?php
        // Mostrar formulario de búsqueda
        $clientes_table->search_box(__('Buscar clientes', 'modulo-ventas'), 'buscar_clientes');
        
        // Mostrar tabla
        $clientes_table->display();
        ?>
    </form>
    
    <!-- Acciones adicionales -->
    <div class="mv-bulk-actions-info">
        <p class="description">
            <?php _e('Nota: Los clientes con cotizaciones asociadas no pueden ser eliminados.', 'modulo-ventas'); ?>
        </p>
    </div>
</div>

<style>
/* Estilos para las estadísticas */
.mv-stats-container {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.mv-stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    flex: 1;
    min-width: 200px;
    text-align: center;
}

.mv-stat-box h3 {
    margin: 0 0 5px 0;
    font-size: 32px;
    color: #23282d;
    font-weight: 400;
}

.mv-stat-box p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

/* Badges de estado */
.mv-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.mv-badge-success {
    background-color: #d4edda;
    color: #155724;
}

.mv-badge-warning {
    background-color: #fff3cd;
    color: #856404;
}

/* Ajustes para la tabla */
.wp-list-table .column-razon_social {
    width: 25%;
}

.wp-list-table .column-email {
    width: 20%;
}

.wp-list-table .column-cotizaciones,
.wp-list-table .column-estado {
    width: 10%;
    text-align: center;
}

.wp-list-table .column-ultima_actividad {
    width: 15%;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .mv-stats-container {
        flex-direction: column;
    }
    
    .mv-stat-box {
        margin-bottom: 10px;
    }
}
</style>