<?php
/**
 * Vista de detalle de cliente
 *
 * @package ModuloVentas
 * @subpackage Views
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Variables disponibles: $cliente, $estadisticas, $cotizaciones_recientes, $pedidos, $actividad
?>

<div class="wrap mv-cliente-detalle">
    <h1>
        <?php echo esc_html($cliente->razon_social); ?>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-editar-cliente&id=' . $cliente->id); ?>" 
        class="page-title-action">
            <?php _e('Editar', 'modulo-ventas'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-nueva-cotizacion&cliente_id=' . $cliente->id); ?>" 
        class="page-title-action">
            <?php _e('Nueva Cotización', 'modulo-ventas'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=modulo-ventas-clientes'); ?>" 
        class="page-title-action">
            <?php _e('Volver al listado', 'modulo-ventas'); ?>
        </a>
    </h1>
    
    <!-- Información básica del cliente -->
    <div class="mv-cliente-header">
        <div class="mv-cliente-info-box">
            <div class="mv-info-row">
                <strong><?php _e('RUT:', 'modulo-ventas'); ?></strong>
                <?php echo esc_html($cliente->rut); ?>
            </div>
            <?php if ($cliente->giro_comercial): ?>
            <div class="mv-info-row">
                <strong><?php _e('Giro:', 'modulo-ventas'); ?></strong>
                <?php echo esc_html($cliente->giro_comercial); ?>
            </div>
            <?php endif; ?>
            <?php if ($cliente->email): ?>
            <div class="mv-info-row">
                <strong><?php _e('Email:', 'modulo-ventas'); ?></strong>
                <a href="mailto:<?php echo esc_attr($cliente->email); ?>">
                    <?php echo esc_html($cliente->email); ?>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($cliente->telefono): ?>
            <div class="mv-info-row">
                <strong><?php _e('Teléfono:', 'modulo-ventas'); ?></strong>
                <a href="tel:<?php echo esc_attr($cliente->telefono); ?>">
                    <?php echo esc_html($cliente->telefono); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="mv-cliente-status">
            <span class="mv-badge mv-badge-<?php echo $cliente->estado === 'activo' ? 'success' : 'warning'; ?>">
                <?php echo $cliente->estado === 'activo' ? __('Activo', 'modulo-ventas') : __('Inactivo', 'modulo-ventas'); ?>
            </span>
            <?php if ($cliente->credito_autorizado > 0): ?>
            <div class="mv-credito-info">
                <strong><?php _e('Crédito:', 'modulo-ventas'); ?></strong>
                <?php echo wc_price($cliente->credito_autorizado); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="mv-stats-grid">
        <div class="mv-stat-card">
            <h3><?php echo number_format($estadisticas['total_cotizaciones'], 0, ',', '.'); ?></h3>
            <p><?php _e('Cotizaciones Totales', 'modulo-ventas'); ?></p>
            <div class="mv-stat-details">
                <span class="pendientes"><?php echo $estadisticas['cotizaciones_pendientes']; ?> pendientes</span>
                <span class="aprobadas"><?php echo $estadisticas['cotizaciones_aprobadas']; ?> aprobadas</span>
            </div>
        </div>
        
        <div class="mv-stat-card">
            <h3><?php echo wc_price($estadisticas['monto_total_cotizado']); ?></h3>
            <p><?php _e('Monto Total Cotizado', 'modulo-ventas'); ?></p>
            <div class="mv-stat-details">
                <span><?php _e('Promedio:', 'modulo-ventas'); ?> <?php echo wc_price($estadisticas['monto_promedio_cotizacion']); ?></span>
            </div>
        </div>
        
        <div class="mv-stat-card">
            <h3><?php echo number_format($estadisticas['tasa_conversion'], 1, ',', '.'); ?>%</h3>
            <p><?php _e('Tasa de Conversión', 'modulo-ventas'); ?></p>
            <div class="mv-stat-details">
                <span><?php echo $estadisticas['cotizaciones_convertidas']; ?> convertidas</span>
            </div>
        </div>
        
        <div class="mv-stat-card">
            <h3><?php echo $estadisticas['tiempo_promedio_decision']; ?> <?php _e('días', 'modulo-ventas'); ?></h3>
            <p><?php _e('Tiempo de Decisión', 'modulo-ventas'); ?></p>
            <div class="mv-stat-details">
                <span><?php _e('Promedio', 'modulo-ventas'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Contenido en tabs -->
    <div class="mv-tabs-container">
        <h2 class="nav-tab-wrapper">
            <a href="#cotizaciones" class="nav-tab nav-tab-active" data-tab="cotizaciones">
                <?php _e('Cotizaciones Recientes', 'modulo-ventas'); ?>
            </a>
            <?php if (!empty($pedidos)): ?>
            <a href="#pedidos" class="nav-tab" data-tab="pedidos">
                <?php _e('Pedidos', 'modulo-ventas'); ?>
            </a>
            <?php endif; ?>
            <a href="#productos" class="nav-tab" data-tab="productos">
                <?php _e('Productos Más Cotizados', 'modulo-ventas'); ?>
            </a>
            <a href="#estadisticas" class="nav-tab" data-tab="estadisticas">
                <?php _e('Estadísticas', 'modulo-ventas'); ?>
            </a>
            <a href="#actividad" class="nav-tab" data-tab="actividad">
                <?php _e('Actividad', 'modulo-ventas'); ?>
            </a>
        </h2>
        
        <!-- Tab: Cotizaciones Recientes -->
        <div id="tab-cotizaciones" class="tab-content active">
            <?php if (!empty($cotizaciones_recientes)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Número', 'modulo-ventas'); ?></th>
                        <th><?php _e('Fecha', 'modulo-ventas'); ?></th>
                        <th><?php _e('Total', 'modulo-ventas'); ?></th>
                        <th><?php _e('Estado', 'modulo-ventas'); ?></th>
                        <th><?php _e('Vencimiento', 'modulo-ventas'); ?></th>
                        <th><?php _e('Acciones', 'modulo-ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($cotizaciones_recientes)): ?>
                        <?php foreach ($cotizaciones_recientes as $cotizacion): ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion['id']); ?>">
                                    <strong><?php echo esc_html($cotizacion['numero']); ?></strong>
                                </a>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($cotizacion['fecha'])); ?></td>
                            <td><?php echo wc_price($cotizacion['total']); ?></td>
                            <td>
                                <?php
                                $estado_class = '';
                                switch($cotizacion['estado']) {
                                    case 'aprobada': $estado_class = 'success'; break;
                                    case 'rechazada': $estado_class = 'error'; break;
                                    case 'pendiente': $estado_class = 'warning'; break;
                                    case 'convertida': $estado_class = 'info'; break;
                                    default: $estado_class = 'default';
                                }
                                ?>
                                <span class="mv-badge mv-badge-<?php echo esc_attr($estado_class); ?>">
                                    <?php echo esc_html(mv_obtener_nombre_estado($cotizacion['estado'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if (!empty($cotizacion['fecha_vencimiento']) && $cotizacion['fecha_vencimiento'] != '0000-00-00') {
                                    echo date_i18n(get_option('date_format'), strtotime($cotizacion['fecha_vencimiento']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $cotizacion['id']); ?>"
                                    class="button button-small">
                                    <?php _e('Ver', 'modulo-ventas'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">
                                <?php _e('No hay cotizaciones registradas', 'modulo-ventas'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <p class="mv-ver-todas">
                <a href="<?php echo admin_url('admin.php?page=modulo-ventas-cotizaciones&cliente_id=' . $cliente->id); ?>" 
                    class="button">
                    <?php _e('Ver todas las cotizaciones', 'modulo-ventas'); ?>
                </a>
            </p>
            <?php else: ?>
            <p class="mv-no-items"><?php _e('No hay cotizaciones registradas para este cliente.', 'modulo-ventas'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Tab: Pedidos (si aplica) -->
        <?php if (!empty($pedidos)): ?>
        <div id="tab-pedidos" class="tab-content">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Pedido', 'modulo-ventas'); ?></th>
                        <th><?php _e('Fecha', 'modulo-ventas'); ?></th>
                        <th><?php _e('Total', 'modulo-ventas'); ?></th>
                        <th><?php _e('Estado', 'modulo-ventas'); ?></th>
                        <th><?php _e('Acciones', 'modulo-ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td>
                            <a href="<?php echo $pedido->get_edit_order_url(); ?>">
                                <strong>#<?php echo $pedido->get_order_number(); ?></strong>
                            </a>
                        </td>
                        <td><?php echo $pedido->get_date_created()->date_i18n(get_option('date_format')); ?></td>
                        <td><?php echo $pedido->get_formatted_order_total(); ?></td>
                        <td><?php echo wc_get_order_status_name($pedido->get_status()); ?></td>
                        <td>
                            <a href="<?php echo $pedido->get_edit_order_url(); ?>" class="button button-small">
                                <?php _e('Ver', 'modulo-ventas'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Tab: Productos Más Cotizados -->
        <div id="tab-productos" class="tab-content">
            <?php if (!empty($estadisticas['productos_mas_cotizados'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Producto', 'modulo-ventas'); ?></th>
                        <th><?php _e('Veces Cotizado', 'modulo-ventas'); ?></th>
                        <th><?php _e('Cantidad Total', 'modulo-ventas'); ?></th>
                        <th><?php _e('Monto Total', 'modulo-ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estadisticas['productos_mas_cotizados'] as $producto): ?>
                    <tr>
                        <td>
                            <?php if ($producto['producto_id']): ?>
                            <a href="<?php echo get_edit_post_link($producto['producto_id']); ?>">
                                <?php echo esc_html($producto['producto_nombre']); ?>
                            </a>
                            <?php else: ?>
                            <?php echo esc_html($producto['producto_nombre']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($producto['veces_cotizado'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($producto['cantidad_total'], 0, ',', '.'); ?></td>
                        <td><?php echo wc_price($producto['monto_total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="mv-no-items"><?php _e('No hay información de productos cotizados.', 'modulo-ventas'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Tab: Estadísticas -->
        <div id="tab-estadisticas" class="tab-content">
            <div class="mv-charts-container">
                <?php if (!empty($estadisticas['cotizaciones_por_mes'])): ?>
                <div class="mv-chart-box">
                    <h3><?php _e('Cotizaciones por Mes', 'modulo-ventas'); ?></h3>
                    <canvas id="chart-cotizaciones-mes" width="400" height="200"></canvas>
                </div>
                <?php endif; ?>
                
                <div class="mv-stats-summary">
                    <h3><?php _e('Resumen de Estadísticas', 'modulo-ventas'); ?></h3>
                    <ul>
                        <li>
                            <strong><?php _e('Total cotizaciones:', 'modulo-ventas'); ?></strong>
                            <?php echo number_format($estadisticas['total_cotizaciones'], 0, ',', '.'); ?>
                        </li>
                        <li>
                            <strong><?php _e('Monto promedio:', 'modulo-ventas'); ?></strong>
                            <?php echo wc_price($estadisticas['monto_promedio_cotizacion']); ?>
                        </li>
                        <li>
                            <strong><?php _e('Tasa de conversión:', 'modulo-ventas'); ?></strong>
                            <?php echo number_format($estadisticas['tasa_conversion'], 1, ',', '.'); ?>%
                        </li>
                        <li>
                            <strong><?php _e('Tiempo promedio de decisión:', 'modulo-ventas'); ?></strong>
                            <?php echo $estadisticas['tiempo_promedio_decision']; ?> <?php _e('días', 'modulo-ventas'); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Tab: Actividad -->
        <div id="tab-actividad" class="tab-content">
            <?php if (!empty($actividad)): ?>
                <?php 
                // Agrupar actividades por cotización
                $actividades_agrupadas = array();
                foreach ($actividad as $evento) {
                    if (isset($evento['referencia_id'])) {
                        $actividades_agrupadas[$evento['referencia_id']][] = $evento;
                    }
                }
                ?>
                
                <div class="mv-actividad-agrupada">
                    <?php foreach ($actividades_agrupadas as $cotizacion_id => $eventos): ?>
                        <div class="mv-grupo-actividad">
                            <h4 class="mv-grupo-titulo">
                                <?php 
                                // Obtener el número de cotización del primer evento
                                $numero_cotizacion = '';
                                if (preg_match('/COT-\d{4}-\d{6}/', $eventos[0]['descripcion'], $matches)) {
                                    $numero_cotizacion = $matches[0];
                                }
                                ?>
                                <span class="dashicons dashicons-media-document"></span>
                                <?php echo esc_html($numero_cotizacion); ?>
                                <span class="mv-evento-count">(<?php echo count($eventos); ?> eventos)</span>
                            </h4>
                            
                            <div class="mv-timeline-grupo">
                                <?php foreach ($eventos as $evento): ?>
                                    <div class="mv-timeline-item">
                                        <div class="mv-timeline-date">
                                            <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                                                strtotime($evento['fecha_actividad'])); ?>
                                        </div>
                                        <div class="mv-timeline-content">
                                            <?php echo wp_kses_post($evento['descripcion']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <p class="mv-no-items"><?php _e('No hay actividad registrada.', 'modulo-ventas'); ?></p>
            <?php endif; ?>
        </div>

        <!-- Notas del Cliente -->
        <div class="postbox">
            <h2 class="hndle">
                <span><?php _e('Notas y Comentarios', 'modulo-ventas'); ?></span>
                <span class="mv-count">(<?php echo $db->contar_notas_cliente($cliente->id); ?>)</span>
            </h2>
            <div class="inside">
                <!-- Formulario para agregar nota -->
                <div class="mv-nueva-nota-form">
                    <form id="mv-form-nueva-nota" method="post">
                        <?php wp_nonce_field('mv_agregar_nota_cliente', 'mv_nota_nonce'); ?>
                        <!-- <input type="hidden" name="action" value="agregar_nota"> comentado porque no funciona el guardado de notas-->
                        <input type="hidden" name="cliente_id" value="<?php echo esc_attr($cliente->id); ?>">
                        
                        <div class="mv-form-group">
                            <textarea name="nota" id="nueva_nota" rows="3" class="widefat" 
                                    placeholder="<?php esc_attr_e('Escriba una nota o comentario...', 'modulo-ventas'); ?>" 
                                    required></textarea>
                        </div>
                        
                        <div class="mv-nota-opciones">
                            <select name="tipo" id="tipo_nota" class="mv-select-tipo">
                                <?php foreach ($db->obtener_tipos_notas() as $valor => $etiqueta): ?>
                                    <option value="<?php echo esc_attr($valor); ?>">
                                        <?php echo esc_html($etiqueta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label class="mv-checkbox-privada">
                                <input type="checkbox" name="es_privada" value="1">
                                <?php _e('Nota privada', 'modulo-ventas'); ?>
                                <span class="description"><?php _e('(solo visible para ti)', 'modulo-ventas'); ?></span>
                            </label>
                            
                            <button type="submit" class="button button-primary">
                                <?php _e('Agregar Nota', 'modulo-ventas'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de notas existentes -->
                <div class="mv-notas-lista">
                    <?php
                    $notas = $db->obtener_notas_cliente($cliente->id);
                    if (empty($notas)): ?>
                        <p class="mv-no-notas"><?php _e('No hay notas para este cliente.', 'modulo-ventas'); ?></p>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): ?>
                            <div class="mv-nota-item <?php echo $nota->es_privada ? 'mv-nota-privada' : ''; ?>">
                                <div class="mv-nota-header">
                                    <span class="mv-nota-tipo mv-tipo-<?php echo esc_attr($nota->tipo); ?>">
                                        <?php 
                                        $tipos = $db->obtener_tipos_notas();
                                        echo esc_html($tipos[$nota->tipo] ?? $nota->tipo); 
                                        ?>
                                    </span>
                                    <span class="mv-nota-autor">
                                        <?php echo esc_html($nota->autor_nombre); ?>
                                    </span>
                                    <span class="mv-nota-fecha">
                                        <?php echo human_time_diff(strtotime($nota->fecha_creacion), current_time('timestamp')) . ' ' . __('atrás', 'modulo-ventas'); ?>
                                    </span>
                                    <?php if ($nota->es_privada): ?>
                                        <span class="mv-nota-privada-badge" title="<?php esc_attr_e('Nota privada', 'modulo-ventas'); ?>">
                                            <span class="dashicons dashicons-lock"></span>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($nota->creado_por == get_current_user_id()): ?>
                                        <div class="mv-nota-acciones">
                                            <a href="#" class="mv-editar-nota" data-nota-id="<?php echo esc_attr($nota->id); ?>">
                                                <?php _e('Editar', 'modulo-ventas'); ?>
                                            </a>
                                            <a href="#" class="mv-eliminar-nota" data-nota-id="<?php echo esc_attr($nota->id); ?>">
                                                <?php _e('Eliminar', 'modulo-ventas'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mv-nota-contenido">
                                    <?php echo nl2br(esc_html($nota->nota)); ?>
                                </div>
                                <?php if ($nota->fecha_actualizacion != $nota->fecha_creacion): ?>
                                    <div class="mv-nota-editado">
                                        <em><?php _e('Editado', 'modulo-ventas'); ?></em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Header del cliente */
.mv-cliente-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
}

.mv-cliente-info-box {
    flex: 1;
}

.mv-info-row {
    margin-bottom: 8px;
}

.mv-info-row strong {
    display: inline-block;
    min-width: 100px;
    color: #666;
}

.mv-cliente-status {
    text-align: right;
}

.mv-credito-info {
    margin-top: 10px;
    font-size: 14px;
}

/* Grid de estadísticas */
.mv-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.mv-stat-card {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    text-align: center;
}

.mv-stat-card h3 {
    margin: 0 0 5px 0;
    font-size: 32px;
    color: #23282d;
    font-weight: 400;
}

.mv-stat-card p {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
}

.mv-stat-details {
    font-size: 12px;
    color: #999;
}

.mv-stat-details span {
    margin: 0 5px;
}

.mv-stat-details .pendientes {
    color: #996800;
}

.mv-stat-details .aprobadas {
    color: #00a32a;
}

/* Tabs */
.mv-tabs-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
}

.nav-tab-wrapper {
    margin: 0;
    padding-left: 10px;
    border-bottom: 1px solid #ccd0d4;
}

.tab-content {
    display: none;
    padding: 20px;
}

.tab-content.active {
    display: block;
}

/* Tab Actividad */
.mv-actividad-agrupada {
    margin-top: 20px;
}

.mv-grupo-actividad {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    margin-bottom: 20px;
    overflow: hidden;
}

.mv-grupo-titulo {
    background: #fff;
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mv-evento-count {
    font-size: 14px;
    color: #666;
    font-weight: normal;
}

.mv-timeline-grupo {
    padding: 20px;
}

.mv-timeline-grupo .mv-timeline-item {
    margin-bottom: 15px;
    padding-left: 40px;
    position: relative;
}

.mv-timeline-grupo .mv-timeline-item::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 8px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #007cba;
}

.mv-timeline-grupo .mv-timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 18px;
    top: 16px;
    bottom: -15px;
    width: 2px;
    background: #e0e0e0;
}

/* Timeline anterior *
.mv-timeline {
    position: relative;
    padding-left: 30px;
}

.mv-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.mv-timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.mv-timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #007cba;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #e0e0e0;
}

.mv-timeline-date {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.mv-timeline-content {
    display: flex;
    align-items: center;
    gap: 10px;
}*/

/* Badges */
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

.mv-badge-error {
    background-color: #f8d7da;
    color: #721c24;
}

.mv-badge-info {
    background-color: #d1ecf1;
    color: #0c5460;
}

/* Otros */
.mv-ver-todas {
    margin-top: 20px;
    text-align: center;
}

.mv-no-items {
    text-align: center;
    color: #666;
    padding: 40px 0;
}

.mv-charts-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.mv-chart-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
}

.mv-stats-summary ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.mv-stats-summary li {
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.mv-stats-summary li:last-child {
    border-bottom: none;
}

/* Estilos para la sección de notas */
.mv-nueva-nota-form {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    margin-bottom: 20px;
}

.mv-nota-opciones {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 10px;
}

.mv-select-tipo {
    min-width: 150px;
}

.mv-checkbox-privada {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-right: auto;
}

.mv-checkbox-privada .description {
    color: #666;
    font-size: 12px;
}

.mv-notas-lista {
    margin-top: 20px;
}

.mv-nota-item {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
    position: relative;
}

.mv-nota-privada {
    background: #fffbf0;
    border-color: #f0c36d;
}

.mv-nota-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 12px;
    color: #666;
}

.mv-nota-tipo {
    background: #2271b1;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
}

.mv-tipo-llamada { background: #00a32a; }
.mv-tipo-reunion { background: #8c5cff; }
.mv-tipo-email { background: #0073aa; }
.mv-tipo-seguimiento { background: #f0b849; color: #000; }
.mv-tipo-cotizacion { background: #00a0d2; }
.mv-tipo-reclamo { background: #d63638; }
.mv-tipo-observacion { background: #666; }

.mv-nota-fecha {
    margin-left: auto;
}

.mv-nota-privada-badge {
    color: #f0c36d;
}

.mv-nota-acciones {
    position: relative;
}

.mv-nota-acciones a {
    margin-left: 10px;
    text-decoration: none;
    font-size: 12px;
}

.mv-nota-acciones a:hover {
    text-decoration: underline;
}

.mv-nota-contenido {
    line-height: 1.6;
    color: #333;
}

.mv-nota-editado {
    margin-top: 5px;
    font-size: 11px;
    color: #999;
}

.mv-no-notas {
    text-align: center;
    padding: 20px;
    color: #666;
}

.mv-count {
    font-weight: normal;
    color: #666;
}
/* Fin sección notas clientes */

/* Responsive */
@media screen and (max-width: 1200px) {
    .mv-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .mv-charts-container {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 782px) {
    .mv-cliente-header {
        flex-direction: column;
    }
    
    .mv-cliente-status {
        text-align: left;
        margin-top: 20px;
    }
    
    .mv-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Asegurar que ajaxurl esté definido
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    // Manejo de tabs
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var tab = $(this).data('tab');
        
        // Actualizar tabs activos
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Mostrar contenido
        $('.tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });
    
    // Gráfico de cotizaciones por mes (si hay datos)
    <?php if (!empty($estadisticas['cotizaciones_por_mes'])): ?>
    var ctx = document.getElementById('chart-cotizaciones-mes');
    if (ctx) {
        ctx = ctx.getContext('2d');
        
        var labels = [];
        var cantidades = [];
        var montos = [];
        
        <?php foreach (array_reverse($estadisticas['cotizaciones_por_mes']) as $mes): ?>
        labels.push('<?php echo $mes['mes']; ?>');
        cantidades.push(<?php echo $mes['cantidad']; ?>);
        montos.push(<?php echo $mes['monto']; ?>);
        <?php endforeach; ?>
        
        // Aquí iría la configuración de Chart.js
        // Por ahora es un placeholder
        console.log('Datos para gráfico:', {labels, cantidades, montos});
    }
    <?php endif; ?>

    // Manejar envío del formulario de nueva nota
    $('#mv-form-nueva-nota').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');

        // Construir datos manualmente sin campos extra
        var datos = {
            action: 'mv_agregar_nota_cliente',
            nonce: $form.find('input[name="mv_nota_nonce"]').val(),
            cliente_id: $form.find('input[name="cliente_id"]').val(),
            nota: $form.find('textarea[name="nota"]').val(),
            tipo: $form.find('select[name="tipo"]').val(),
            es_privada: $form.find('input[name="es_privada"]').is(':checked') ? 1 : 0
        };
        
        $submit.prop('disabled', true).text('<?php _e('Agregando...', 'modulo-ventas'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mv_agregar_nota_cliente',
                nonce: $form.find('input[name="mv_nota_nonce"]').val(),
                cliente_id: $form.find('input[name="cliente_id"]').val(),
                nota: $form.find('textarea[name="nota"]').val(),
                tipo: $form.find('select[name="tipo"]').val(),
                es_privada: $form.find('input[name="es_privada"]').is(':checked') ? 1 : 0
            },
            success: function(response) {                
                if (response && response.success) {
                    // Recargar la página para mostrar la nueva nota
                    location.reload();
                } else {
                    // Manejar error
                    var mensaje = 'Error al agregar la nota';
                    if (response && response.data && response.data.message) {
                        mensaje = response.data.message;
                    }
                    alert(mensaje);
                    $submit.prop('disabled', false).text('<?php _e('Agregar Nota', 'modulo-ventas'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                console.error('Respuesta:', xhr.responseText);
                alert('<?php _e('Error de conexión. Por favor, intente nuevamente.', 'modulo-ventas'); ?>');
                $submit.prop('disabled', false).text('<?php _e('Agregar Nota', 'modulo-ventas'); ?>');
            }
        });
    });
    
    // Manejar eliminación de notas
    $('.mv-eliminar-nota').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('¿Está seguro de eliminar esta nota?', 'modulo-ventas'); ?>')) {
            return;
        }
        
        var $link = $(this);
        var notaId = $link.data('nota-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mv_eliminar_nota_cliente',
                nonce: '<?php echo wp_create_nonce('mv_eliminar_nota'); ?>',
                nota_id: notaId
            },
            success: function(response) {
                if (response && response.success) {
                    $link.closest('.mv-nota-item').fadeOut(function() {
                        $(this).remove();
                        // Si no quedan notas, mostrar mensaje
                        if ($('.mv-nota-item').length === 0) {
                            $('.mv-notas-lista').html('<p class="mv-no-notas"><?php _e('No hay notas para este cliente.', 'modulo-ventas'); ?></p>');
                        }
                    });
                } else {
                    var mensaje = 'Error al eliminar la nota';
                    if (response && response.data && response.data.message) {
                        mensaje = response.data.message;
                    }
                    alert(mensaje);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                alert('<?php _e('Error al eliminar la nota', 'modulo-ventas'); ?>');
            }
        });
    });

    // Manejar edición de notas
    $(document).on('click', '.mv-editar-nota', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var notaId = $link.data('nota-id');
        var $notaItem = $link.closest('.mv-nota-item');
        var $notaContenido = $notaItem.find('.mv-nota-contenido');
        
        // Obtener el texto sin los <br>
        var textoActual = $notaContenido.html().replace(/<br\s*\/?>/gi, '\n').trim();
        // Decodificar entidades HTML
        var textArea = document.createElement('textarea');
        textArea.innerHTML = textoActual;
        textoActual = textArea.value;
        
        // Crear formulario de edición
        var formHtml = `
            <form class="mv-form-editar-nota" data-nota-id="${notaId}">
                <textarea class="widefat" rows="3">${textoActual}</textarea>
                <div class="mv-nota-editar-acciones" style="margin-top: 10px;">
                    <button type="submit" class="button button-primary button-small"><?php _e('Guardar', 'modulo-ventas'); ?></button>
                    <button type="button" class="button button-small mv-cancelar-edicion"><?php _e('Cancelar', 'modulo-ventas'); ?></button>
                </div>
            </form>
        `;
        
        // Ocultar contenido y mostrar formulario
        $notaContenido.hide();
        $notaContenido.after(formHtml);
        $link.hide();
        
        // Enfocar el textarea
        $notaItem.find('textarea').focus();
    });

    // Manejar cancelación de edición
    $(document).on('click', '.mv-cancelar-edicion', function() {
        var $form = $(this).closest('.mv-form-editar-nota');
        var $notaItem = $form.closest('.mv-nota-item');
        
        $form.remove();
        $notaItem.find('.mv-nota-contenido').show();
        $notaItem.find('.mv-editar-nota').show();
    });

    // Manejar envío del formulario de edición
    $(document).on('submit', '.mv-form-editar-nota', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('button[type="submit"]');
        var notaId = $form.data('nota-id');
        var nuevoTexto = $form.find('textarea').val();
        var $notaItem = $form.closest('.mv-nota-item');
        
        $submit.prop('disabled', true).text('<?php _e('Guardando...', 'modulo-ventas'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mv_actualizar_nota_cliente',
                nonce: '<?php echo wp_create_nonce('mv_actualizar_nota'); ?>',
                nota_id: notaId,
                nota: nuevoTexto
            },
            success: function(response) {
                if (response && response.success) {
                    // Actualizar el contenido con nl2br
                    var textoFormateado = nuevoTexto.replace(/\n/g, '<br>');
                    $notaItem.find('.mv-nota-contenido').html(textoFormateado).show();
                    $form.remove();
                    $notaItem.find('.mv-editar-nota').show();
                    
                    // Agregar o actualizar el indicador de editado
                    if ($notaItem.find('.mv-nota-editado').length === 0) {
                        $notaItem.append('<div class="mv-nota-editado"><em><?php _e('Editado', 'modulo-ventas'); ?></em></div>');
                    }
                } else {
                    var mensaje = response?.data?.message || 'Error al actualizar la nota';
                    alert(mensaje);
                    $submit.prop('disabled', false).text('<?php _e('Guardar', 'modulo-ventas'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error AJAX:', error);
                alert('<?php _e('Error al actualizar la nota', 'modulo-ventas'); ?>');
                $submit.prop('disabled', false).text('<?php _e('Guardar', 'modulo-ventas'); ?>');
            }
        });
    });
});
</script>