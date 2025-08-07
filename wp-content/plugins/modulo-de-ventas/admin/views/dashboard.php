<?php
/**
 * Vista del Dashboard principal del Módulo de Ventas
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Variables disponibles:
// $stats - Estadísticas del dashboard
?>

<div class="wrap mv-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-dashboard"></span>
        <?php _e('Dashboard - Módulo de Ventas', 'modulo-ventas'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Mensaje de bienvenida -->
    <div class="mv-welcome-panel">
        <div class="mv-welcome-panel-content">
            <h2><?php _e('¡Bienvenido al Módulo de Ventas!', 'modulo-ventas'); ?></h2>
            <p class="about-description">
                <?php _e('Aquí puedes ver un resumen de tu actividad de ventas y acceder rápidamente a las funciones principales.', 'modulo-ventas'); ?>
            </p>
            
            <div class="mv-welcome-panel-column-container">
                <div class="mv-welcome-panel-column">
                    <h3><?php _e('Comenzar', 'modulo-ventas'); ?></h3>
                    <a class="button button-primary button-hero" href="<?php echo esc_url(mv_admin_url('nueva-cotizacion')); ?>">
                        <?php _e('Nueva Cotización', 'modulo-ventas'); ?>
                    </a>
                    <p><?php _e('o', 'modulo-ventas'); ?> <a href="<?php echo esc_url(mv_admin_url('cotizaciones')); ?>"><?php _e('ver todas las cotizaciones', 'modulo-ventas'); ?></a></p>
                </div>
                
                <div class="mv-welcome-panel-column">
                    <h3><?php _e('Accesos Rápidos', 'modulo-ventas'); ?></h3>
                    <ul>
                        <li>
                            <a href="<?php echo esc_url(mv_admin_url('nuevo-cliente')); ?>" class="welcome-icon welcome-add-page">
                                <?php _e('Agregar nuevo cliente', 'modulo-ventas'); ?>
                            </a>
                        </li>
                        <!--<li>
                            <a href="<?php echo esc_url(mv_admin_url('reportes')); ?>" class="welcome-icon welcome-view-site">
                                <?php _e('Ver reportes', 'modulo-ventas'); ?>
                            </a>
                        </li>-->
                        <li>
                            <a href="<?php echo esc_url(mv_admin_url('configuracion')); ?>" class="welcome-icon welcome-widgets-menus">
                                <?php _e('Configuración', 'modulo-ventas'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="mv-welcome-panel-column mv-welcome-panel-last">
                    <h3><?php _e('Recursos', 'modulo-ventas'); ?></h3>
                    <ul>
                        <li>
                            <div class="welcome-icon welcome-learn-more">
                                <?php 
                                printf(
                                    __('Versión %s', 'modulo-ventas'),
                                    MODULO_VENTAS_VERSION
                                );
                                ?>
                            </div>
                        </li>
                        <li>
                            <a href="#" class="welcome-icon welcome-learn-more mv-open-help">
                                <?php _e('Ayuda y documentación', 'modulo-ventas'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas generales -->
    <div class="mv-stats-boxes">
        <div class="mv-stat-box">
            <div class="mv-stat-number"><?php echo number_format($stats['cotizaciones_mes'], 0, ',', '.'); ?></div>
            <div class="mv-stat-label"><?php _e('Cotizaciones este mes', 'modulo-ventas'); ?></div>
            <?php if (isset($stats['sin_datos_mes']) && $stats['sin_datos_mes']) : ?>
                <div class="mv-stat-sublabel" style="color: #666; font-size: 11px; margin-top: 5px;">
                    <?php printf(__('Total histórico: %d', 'modulo-ventas'), $stats['total_historico']); ?>
                </div>
            <?php endif; ?>
            <div class="mv-stat-change">
                <?php if ($stats['cotizaciones_mes_anterior'] > 0 || $stats['cotizaciones_mes'] > 0) : ?>
                    <?php 
                    if ($stats['cotizaciones_mes_anterior'] > 0) {
                        $cambio = (($stats['cotizaciones_mes'] - $stats['cotizaciones_mes_anterior']) / $stats['cotizaciones_mes_anterior']) * 100;
                    } else {
                        $cambio = 100; // Si no había cotizaciones el mes anterior
                    }
                    ?>
                    <span class="<?php echo $cambio >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $cambio >= 0 ? '+' : ''; ?><?php echo number_format($cambio, 1, ',', '.'); ?>%
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mv-stat-box">
            <div class="mv-stat-number"><?php echo mv_formato_precio($stats['valor_total_mes']); ?></div>
            <div class="mv-stat-label"><?php _e('Valor total cotizado', 'modulo-ventas'); ?></div>
            <div class="mv-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
        </div>
        
        <div class="mv-stat-box">
            <div class="mv-stat-number"><?php echo number_format($stats['tasa_conversion'], 1, ',', '.'); ?>%</div>
            <div class="mv-stat-label"><?php _e('Tasa de conversión', 'modulo-ventas'); ?></div>
            <div class="mv-stat-sublabel">
                <?php 
                printf(
                    __('%d de %d convertidas', 'modulo-ventas'),
                    $stats['por_estado']['convertida'],
                    array_sum($stats['por_estado'])
                );
                ?>
            </div>
        </div>
        
        <div class="mv-stat-box">
            <div class="mv-stat-number"><?php echo intval($stats['proximas_expirar']); ?></div>
            <div class="mv-stat-label"><?php _e('Próximas a expirar (7 días)', 'modulo-ventas'); ?></div>
            <div class="mv-stat-action">
                <a href="<?php echo esc_url(mv_admin_url('cotizaciones', array('estado' => 'pendiente'))); ?>">
                    <?php _e('Ver todas', 'modulo-ventas'); ?> →
                </a>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal en dos columnas -->
    <div class="mv-dashboard-columns">
        <div class="mv-dashboard-main">
            
            <!-- Gráfico de evolución -->
            <div class="mv-dashboard-widget">
                <h2><?php _e('Evolución de Cotizaciones', 'modulo-ventas'); ?></h2>
                <div class="mv-chart-container">
                    <canvas id="mv-chart-evolucion" height="300"></canvas>
                </div>
                <div class="mv-chart-legend" id="mv-legend-evolucion"></div>
            </div>
            
            <!-- Tabla de cotizaciones recientes -->
            <div class="mv-dashboard-widget">
                <h2>
                    <?php _e('Cotizaciones Recientes', 'modulo-ventas'); ?>
                    <a href="<?php echo esc_url(mv_admin_url('cotizaciones')); ?>" class="page-title-action">
                        <?php _e('Ver todas', 'modulo-ventas'); ?>
                    </a>
                </h2>
                
                <?php if (!empty($stats['cotizaciones_recientes'])) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Folio', 'modulo-ventas'); ?></th>
                                <th><?php _e('Cliente', 'modulo-ventas'); ?></th>
                                <th><?php _e('Fecha', 'modulo-ventas'); ?></th>
                                <th><?php _e('Total', 'modulo-ventas'); ?></th>
                                <th><?php _e('Estado', 'modulo-ventas'); ?></th>
                                <th><?php _e('Acciones', 'modulo-ventas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['cotizaciones_recientes'] as $cotizacion) : ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(mv_admin_url('ver-cotizacion', array('id' => $cotizacion->id))); ?>">
                                                <?php echo esc_html($cotizacion->folio); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($cotizacion->razon_social); ?></td>
                                    <td><?php echo mv_fecha_relativa($cotizacion->fecha); ?></td>
                                    <td><?php echo mv_formato_precio($cotizacion->total); ?></td>
                                    <td><?php echo mv_get_estado_badge($cotizacion->estado); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(mv_admin_url('ver-cotizacion', array('id' => $cotizacion->id))); ?>" 
                                            class="button button-small">
                                            <?php _e('Ver', 'modulo-ventas'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="mv-no-items">
                        <?php _e('No hay cotizaciones recientes.', 'modulo-ventas'); ?>
                        <a href="<?php echo esc_url(mv_admin_url('nueva-cotizacion')); ?>">
                            <?php _e('Crear primera cotización', 'modulo-ventas'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            
        </div>
        
        <div class="mv-dashboard-sidebar">
            
            <!-- Estado de cotizaciones -->
            <div class="mv-dashboard-widget">
                <h3><?php _e('Estado de Cotizaciones', 'modulo-ventas'); ?></h3>
                <div class="mv-status-list">
                    <?php 
                    $estados = mv_get_estados_cotizacion();
                    foreach ($estados as $key => $config) :
                        $cantidad = isset($stats['por_estado'][$key]) ? $stats['por_estado'][$key] : 0;
                        $total = array_sum($stats['por_estado']);
                        $porcentaje = $total > 0 ? ($cantidad / $total) * 100 : 0;
                    ?>
                        <div class="mv-status-item">
                            <div class="mv-status-header">
                                <span class="dashicons <?php echo esc_attr($config['icon']); ?>" 
                                    style="color: <?php echo esc_attr($config['color']); ?>"></span>
                                <span class="mv-status-label"><?php echo esc_html($config['label']); ?></span>
                                <span class="mv-status-count"><?php echo intval($cantidad); ?></span>
                            </div>
                            <div class="mv-status-bar">
                                <div class="mv-status-bar-fill" 
                                    style="width: <?php echo esc_attr($porcentaje); ?>%; 
                                            background-color: <?php echo esc_attr($config['color']); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Top clientes -->
            <div class="mv-dashboard-widget">
                <h3><?php _e('Top Clientes', 'modulo-ventas'); ?></h3>
                <?php if (!empty($stats['top_clientes'])) : ?>
                    <ul class="mv-top-list">
                        <?php foreach ($stats['top_clientes'] as $index => $cliente) : ?>
                            <li>
                                <span class="mv-top-position"><?php echo $index + 1; ?></span>
                                <div class="mv-top-info">
                                    <strong><?php echo esc_html($cliente->razon_social); ?></strong>
                                    <span class="mv-top-meta">
                                        <?php
                                        printf(
                                            __('%d cotizaciones - %s', 'modulo-ventas'),
                                            $cliente->total_cotizaciones,
                                            mv_formato_precio($cliente->valor_total)
                                        );
                                        ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="mv-no-items"><?php _e('No hay datos disponibles.', 'modulo-ventas'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Productos más cotizados -->
            <div class="mv-dashboard-widget">
                <h3><?php _e('Productos Más Cotizados', 'modulo-ventas'); ?></h3>
                <?php if (!empty($stats['top_productos'])) : ?>
                    <ul class="mv-product-list">
                        <?php foreach ($stats['top_productos'] as $producto) : ?>
                            <li>
                                <div class="mv-product-name"><?php echo esc_html($producto->nombre); ?></div>
                                <div class="mv-product-stats">
                                    <?php 
                                    printf(
                                        __('%d veces - %s unidades', 'modulo-ventas'),
                                        $producto->veces_cotizado,
                                        number_format($producto->cantidad_total, 0, ',', '.')
                                    );
                                    ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="mv-no-items"><?php _e('No hay datos disponibles.', 'modulo-ventas'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Acciones rápidas -->
            <div class="mv-dashboard-widget">
                <h3><?php _e('Acciones Rápidas', 'modulo-ventas'); ?></h3>
                <div class="mv-quick-actions">
                    <a href="<?php echo esc_url(mv_admin_url('nueva-cotizacion')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Nueva Cotización', 'modulo-ventas'); ?>
                    </a>
                    <a href="<?php echo esc_url(mv_admin_url('nuevo-cliente')); ?>" class="button">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php _e('Nuevo Cliente', 'modulo-ventas'); ?>
                    </a>
                    <a href="<?php echo esc_url(mv_admin_url('reportes')); ?>" class="button">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('Ver Reportes', 'modulo-ventas'); ?>
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style type="text/css">
/* Estilos específicos del dashboard */
.mv-dashboard {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

.wrap h1.wp-heading-inline {
    display: flex;
    align-items: center;
    gap: 5px;
}

.mv-welcome-panel {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    margin: 16px 0;
    padding: 23px 10px 0;
    position: relative;
}

.mv-welcome-panel-content {
    margin-left: 13px;
    max-width: 1500px;
}

.mv-welcome-panel-column-container {
    clear: both;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 0 0 20px;
}

.mv-stats-boxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.mv-stat-box {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    padding: 20px;
    position: relative;
    text-align: center;
    transition: all 0.3s ease;
}

.mv-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.mv-stat-number {
    font-size: 32px;
    font-weight: 600;
    line-height: 1.2;
    color: #1d2327;
    margin-bottom: 5px;
    transition: transform 0.2s ease;
}

.mv-stat-label {
    color: #646970;
    font-size: 14px;
}

.mv-stat-sublabel {
    color: #8c8f94;
    font-size: 12px;
    margin-top: 5px;
}

.mv-stat-action {
    margin-top: 10px;
}

.mv-stat-action a {
    font-size: 13px;
    text-decoration: none;
}

.mv-stat-change {
    margin-top: 10px;
    font-size: 12px;
}

.mv-stat-change .positive {
    color: #00a32a;
}

.mv-stat-change .negative {
    color: #d63638;
}

.mv-dashboard-columns {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    margin-top: 20px;
}

.mv-dashboard-main {
    min-width: 0; /* Importante para prevenir overflow */
}

.mv-dashboard-widget {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    margin-bottom: 20px;
    padding: 20px;
    overflow: hidden; /* Prevenir overflow horizontal */
}

.mv-dashboard-widget h2,
.mv-dashboard-widget h3 {
    margin: 0 0 15px;
    padding: 0;
    font-size: 14px;
    font-weight: 600;
}

/* Contenedor del gráfico - CRÍTICO para responsive */
.mv-chart-container {
    position: relative;
    height: 300px;
    width: 100%;
    max-width: 100%;
    overflow: hidden;
}

.mv-chart-container canvas {
    max-width: 100% !important;
    height: auto !important;
}

/* Status bars */
.mv-status-item {
    margin-bottom: 15px;
}

.mv-status-header {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}

.mv-status-label {
    flex: 1;
    margin-left: 8px;
}

.mv-status-count {
    font-weight: 600;
}

.mv-status-bar {
    background: #f0f0f1;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.mv-status-bar-fill {
    height: 100%;
    transition: width 0.3s ease;
}

/* Listas */
.mv-top-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.mv-top-list li {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
    border-radius: 8px;
}

.mv-top-list li:last-child {
    border-bottom: none;
}

.mv-top-position {
    width: 30px;
    height: 30px;
    background: #f0f0f1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 10px;
}

.mv-top-info {
    flex: 1;
    min-width: 0; /* Prevenir overflow de texto */
}

.mv-top-info strong {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.mv-top-meta {
    display: block;
    color: #646970;
    font-size: 12px;
}

.mv-product-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.mv-product-list li {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
    border-radius: 8px;
}

.mv-product-list li:last-child {
    border-bottom: none;
}

.mv-product-stats {
    color: #646970;
    font-size: 12px;
    margin-top: 3px;
}

/* Acciones rápidas */
.mv-quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.mv-quick-actions .button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.mv-quick-actions .button .dashicons {
    margin-right: 5px;
}

.mv-no-items {
    color: #646970;
    text-align: center;
    padding: 20px 0;
}

table.wp-list-table.widefat.fixed.striped {
    border-radius: 8px;
}

/* Responsive */
@media screen and (max-width: 1200px) {
    .mv-dashboard-columns {
        grid-template-columns: 1fr;
    }
    
    .mv-dashboard-sidebar {
        max-width: 100%;
    }
}

@media screen and (max-width: 782px) {
    .mv-welcome-panel-column-container {
        grid-template-columns: 1fr;
    }
    
    .mv-stats-boxes {
        grid-template-columns: 1fr;
    }
    
    .mv-dashboard {
        margin-right: 10px;
    }
}

/* Prevenir scroll horizontal */
.wrap {
    max-width: 100%;
    overflow-x: hidden;
}

/* Tabla responsive */
@media screen and (max-width: 782px) {
    .wp-list-table {
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Variable para almacenar la instancia del gráfico
    var chartEvolucion = null;
    
    // Función para crear/actualizar el gráfico
    function crearGrafico() {
        if ($('#mv-chart-evolucion').length && typeof Chart !== 'undefined') {
            <?php if (!empty($stats['cotizaciones_por_mes'])): ?>
            
            var canvas = document.getElementById('mv-chart-evolucion');
            var ctx = canvas.getContext('2d');
            
            // Si ya existe el gráfico, destruirlo
            if (chartEvolucion) {
                chartEvolucion.destroy();
            }
            
            // Preparar datos
            var labels = [];
            var dataCantidades = [];
            var dataMontos = [];
            
            <?php foreach ($stats['cotizaciones_por_mes'] as $mes): ?>
            labels.push('<?php echo esc_js($mes['mes_corto']); ?>');
            dataCantidades.push(<?php echo intval($mes['cantidad']); ?>);
            dataMontos.push(<?php echo floatval($mes['monto']); ?>);
            <?php endforeach; ?>
            
            // Configuración global de Chart.js
            Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            
            // Crear gráfico
            chartEvolucion = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cotizaciones',
                        data: dataCantidades,
                        backgroundColor: 'rgba(34, 113, 177, 0.8)',
                        borderColor: '#2271b1',
                        borderWidth: 1,
                        yAxisID: 'y-cantidad',
                        order: 2
                    }, {
                        label: 'Monto Total',
                        data: dataMontos,
                        type: 'line',
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: '#00a32a',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y-monto',
                        order: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        title: {
                            display: false
                        },
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    
                                    if (context.datasetIndex === 1) {
                                        // Formato para montos
                                        var valor = context.parsed.y;
                                        label += '$' + new Intl.NumberFormat('es-CL', {
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0
                                        }).format(valor);
                                    } else {
                                        // Formato para cantidades
                                        label += context.parsed.y + ' cotizaciones';
                                    }
                                    return label;
                                },
                                afterBody: function(tooltipItems) {
                                    if (tooltipItems.length === 2) {
                                        var cantidad = tooltipItems[0].parsed.y;
                                        var monto = tooltipItems[1].parsed.y;
                                        var promedio = cantidad > 0 ? monto / cantidad : 0;
                                        
                                        return '\nPromedio: $' + 
                                            new Intl.NumberFormat('es-CL', {
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0
                                            }).format(promedio);
                                    }
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: true
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        'y-cantidad': {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Cantidad de Cotizaciones',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 11
                                },
                                color: '#666'
                            },
                            grid: {
                                borderDash: [2, 2]
                            }
                        },
                        'y-monto': {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Monto Total (CLP)',
                                font: {
                                    size: 12,
                                    weight: 'normal'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                color: '#666',
                                callback: function(value, index, ticks) {
                                    if (value === 0) return '$0';
                                    
                                    // Formatear según el rango
                                    if (value >= 1000000) {
                                        return '$' + (value / 1000000).toFixed(1).replace('.', ',') + 'M';
                                    } else if (value >= 1000) {
                                        return '$' + Math.round(value / 1000) + 'k';
                                    } else {
                                        return '$' + Math.round(value);
                                    }
                                }
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    onResize: function(chart, size) {
                        // Forzar redimensionamiento
                        chart.resize();
                    }
                }
            });
            
            <?php else: ?>
            // No hay datos, mostrar mensaje
            $('#mv-chart-evolucion').parent().html(
                '<div style="text-align: center; padding: 80px 20px; color: #666;">' +
                '<span class="dashicons dashicons-chart-line" style="font-size: 48px; opacity: 0.3;"></span>' +
                '<p style="margin-top: 10px;">No hay datos suficientes para mostrar el gráfico.</p>' +
                '<p style="font-size: 13px;">Las estadísticas aparecerán cuando haya cotizaciones registradas.</p>' +
                '</div>'
            );
            <?php endif; ?>
        }
    }
    
    // Crear el gráfico inicialmente
    crearGrafico();
    
    // Manejar cambios de tamaño con debounce
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (chartEvolucion) {
                chartEvolucion.resize();
            }
        }, 250);
    });
    
    // Detectar cambios de zoom
    var lastWidth = $(window).width();
    $(window).on('resize', function() {
        if ($(window).width() !== lastWidth) {
            lastWidth = $(window).width();
            if (chartEvolucion) {
                setTimeout(function() {
                    chartEvolucion.resize();
                }, 100);
            }
        }
    });
    
    // Cerrar panel de bienvenida
    $('.mv-welcome-panel .notice-dismiss').on('click', function() {
        $('.mv-welcome-panel').slideUp();
        $.post(ajaxurl, {
            action: 'mv_dismiss_welcome',
            nonce: '<?php echo wp_create_nonce('mv_dismiss_welcome'); ?>'
        });
    });
    
    // Animación en hover de estadísticas (sin duplicar)
    $('.mv-stat-box').on('mouseenter', function() {
        $(this).find('.mv-stat-number').css('transform', 'scale(1.05)');
    }).on('mouseleave', function() {
        $(this).find('.mv-stat-number').css('transform', 'scale(1)');
    });
});
</script>