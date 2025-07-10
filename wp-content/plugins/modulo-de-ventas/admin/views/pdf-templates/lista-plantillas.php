<?php
/**
 * VISTA ADMIN - LISTA DE PLANTILLAS PDF
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/admin/views/pdf-templates/lista-plantillas.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener estadísticas
$total_plantillas = count($plantillas);
$plantillas_activas = array_filter($plantillas, function($p) { return $p->activa; });
$total_activas = count($plantillas_activas);

// Agrupar por tipo
$plantillas_por_tipo = array();
foreach ($plantillas as $plantilla) {
    $plantillas_por_tipo[$plantilla->tipo][] = $plantilla;
}

$tipos_disponibles = array(
    'cotizacion' => __('Cotización', 'modulo-ventas'),
    'factura' => __('Factura', 'modulo-ventas'),
    'boleta' => __('Boleta', 'modulo-ventas'),
    'orden_compra' => __('Orden de Compra', 'modulo-ventas'),
    'guia_despacho' => __('Guía de Despacho', 'modulo-ventas')
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Plantillas PDF', 'modulo-ventas'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new'); ?>" class="page-title-action">
        <?php _e('Agregar Nueva', 'modulo-ventas'); ?>
    </a>
    
    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=config'); ?>" class="page-title-action">
        <?php _e('Configuración', 'modulo-ventas'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Panel de estadísticas -->
    <div class="mv-stats-panel" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0; display: flex; gap: 30px;">
        <div class="stat-item">
            <div style="font-size: 24px; font-weight: bold; color: #2c5aa0;"><?php echo $total_plantillas; ?></div>
            <div style="color: #666; font-size: 14px;"><?php _e('Total Plantillas', 'modulo-ventas'); ?></div>
        </div>
        <div class="stat-item">
            <div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo $total_activas; ?></div>
            <div style="color: #666; font-size: 14px;"><?php _e('Activas', 'modulo-ventas'); ?></div>
        </div>
        <div class="stat-item">
            <div style="font-size: 24px; font-weight: bold; color: #00a0d2;"><?php echo count($plantillas_por_tipo); ?></div>
            <div style="color: #666; font-size: 14px;"><?php _e('Tipos de Documento', 'modulo-ventas'); ?></div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="mv-filters" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
        <form method="get" style="display: flex; gap: 15px; align-items: center;">
            <input type="hidden" name="page" value="mv-pdf-templates">
            
            <label for="filter-tipo"><?php _e('Tipo:', 'modulo-ventas'); ?></label>
            <select name="tipo" id="filter-tipo">
                <option value=""><?php _e('Todos los tipos', 'modulo-ventas'); ?></option>
                <?php foreach ($tipos_disponibles as $valor => $etiqueta): ?>
                    <option value="<?php echo esc_attr($valor); ?>" <?php selected(isset($_GET['tipo']) ? $_GET['tipo'] : '', $valor); ?>>
                        <?php echo esc_html($etiqueta); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="filter-estado"><?php _e('Estado:', 'modulo-ventas'); ?></label>
            <select name="estado" id="filter-estado">
                <option value=""><?php _e('Todos los estados', 'modulo-ventas'); ?></option>
                <option value="activa" <?php selected(isset($_GET['estado']) ? $_GET['estado'] : '', 'activa'); ?>><?php _e('Activas', 'modulo-ventas'); ?></option>
                <option value="inactiva" <?php selected(isset($_GET['estado']) ? $_GET['estado'] : '', 'inactiva'); ?>><?php _e('Inactivas', 'modulo-ventas'); ?></option>
            </select>
            
            <input type="submit" class="button" value="<?php _e('Filtrar', 'modulo-ventas'); ?>">
            
            <?php if (isset($_GET['tipo']) || isset($_GET['estado'])): ?>
                <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates'); ?>" class="button">
                    <?php _e('Limpiar Filtros', 'modulo-ventas'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if (empty($plantillas)): ?>
        
        <!-- Estado vacío -->
        <div class="mv-empty-state" style="text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <div style="font-size: 48px; color: #ddd; margin-bottom: 20px;">📄</div>
            <h2><?php _e('No hay plantillas PDF', 'modulo-ventas'); ?></h2>
            <p style="color: #666; margin-bottom: 30px;">
                <?php _e('Crea tu primera plantilla PDF para personalizar la apariencia de tus documentos.', 'modulo-ventas'); ?>
            </p>
            <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new'); ?>" class="button button-primary button-large">
                <?php _e('Crear Primera Plantilla', 'modulo-ventas'); ?>
            </a>
        </div>
        
    <?php else: ?>
        
        <!-- Tabla de plantillas -->
        <div class="mv-plantillas-container">
            <table class="wp-list-table widefat fixed striped mv-plantillas-table">
                <thead>
                    <tr>
                        <th scope="col" style="width: 50px;"><?php _e('Estado', 'modulo-ventas'); ?></th>
                        <th scope="col"><?php _e('Nombre', 'modulo-ventas'); ?></th>
                        <th scope="col" style="width: 120px;"><?php _e('Tipo', 'modulo-ventas'); ?></th>
                        <th scope="col" style="width: 100px;"><?php _e('Predeterminada', 'modulo-ventas'); ?></th>
                        <th scope="col" style="width: 150px;"><?php _e('Última Modificación', 'modulo-ventas'); ?></th>
                        <th scope="col" style="width: 200px;"><?php _e('Acciones', 'modulo-ventas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plantillas as $plantilla): ?>
                        <tr data-plantilla-id="<?php echo $plantilla->id; ?>" data-estado="<?php echo $plantilla->activa ? 'activa' : 'inactiva'; ?>">
                            
                            <!-- Estado -->
                            <td>
                                <label class="mv-toggle-switch" style="position: relative; display: inline-block; width: 40px; height: 20px;">
                                    <input type="checkbox" 
                                           class="plantilla-toggle-estado" 
                                           data-plantilla-id="<?php echo $plantilla->id; ?>"
                                           <?php checked($plantilla->activa); ?>
                                           style="opacity: 0; width: 0; height: 0;">
                                    <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px;"></span>
                                    <span style="position: absolute; content: ''; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                                </label>
                                <div class="mv-estado-text" style="font-size: 11px; color: #666; margin-top: 2px;">
                                    <?php echo $plantilla->activa ? __('Activa', 'modulo-ventas') : __('Inactiva', 'modulo-ventas'); ?>
                                </div>
                            </td>
                            
                            <!-- Nombre y descripción -->
                            <td>
                                <strong>
                                    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $plantilla->id); ?>">
                                        <?php echo esc_html($plantilla->nombre); ?>
                                    </a>
                                </strong>
                                
                                <?php if (!empty($plantilla->descripcion)): ?>
                                    <div style="color: #666; font-size: 13px; margin-top: 3px;">
                                        <?php echo esc_html($plantilla->descripcion); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Row actions -->
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $plantilla->id); ?>">
                                            <?php _e('Editar', 'modulo-ventas'); ?>
                                        </a>
                                    </span>
                                    
                                    <span class="view"> | 
                                        <a href="#" class="mv-preview-plantilla" data-plantilla-id="<?php echo $plantilla->id; ?>">
                                            <?php _e('Vista Previa', 'modulo-ventas'); ?>
                                        </a>
                                    </span>
                                    
                                    <?php if (!$plantilla->es_predeterminada): ?>
                                        <span class="duplicate"> | 
                                            <a href="#" class="mv-duplicar-plantilla" data-plantilla-id="<?php echo $plantilla->id; ?>">
                                                <?php _e('Duplicar', 'modulo-ventas'); ?>
                                            </a>
                                        </span>
                                        
                                        <span class="trash"> | 
                                            <a href="#" class="mv-eliminar-plantilla" data-plantilla-id="<?php echo $plantilla->id; ?>" style="color: #a00;">
                                                <?php _e('Eliminar', 'modulo-ventas'); ?>
                                            </a>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- Tipo -->
                            <td>
                                <span class="mv-tipo-badge" style="background: #f0f0f1; border-radius: 3px; padding: 3px 8px; font-size: 11px; font-weight: 500;">
                                    <?php echo esc_html($tipos_disponibles[$plantilla->tipo] ?? ucfirst($plantilla->tipo)); ?>
                                </span>
                            </td>
                            
                            <!-- Predeterminada -->
                            <td style="text-align: center;">
                                <?php if ($plantilla->es_predeterminada): ?>
                                    <span style="color: #46b450; font-weight: bold;">✓</span>
                                    <div style="font-size: 11px; color: #666;"><?php _e('Por defecto', 'modulo-ventas'); ?></div>
                                <?php else: ?>
                                    <span style="color: #ddd;">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Fecha de modificación -->
                            <td>
                                <?php 
                                $fecha = $plantilla->fecha_actualizacion ?: $plantilla->fecha_creacion;
                                echo date_i18n('d/m/Y H:i', strtotime($fecha));
                                ?>
                                <div style="font-size: 11px; color: #666;">
                                    <?php echo human_time_diff(strtotime($fecha), current_time('timestamp')); ?> <?php _e('atrás', 'modulo-ventas'); ?>
                                </div>
                            </td>
                            
                            <!-- Acciones -->
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $plantilla->id); ?>" 
                                       class="button button-small">
                                        <?php _e('Editar', 'modulo-ventas'); ?>
                                    </a>
                                    
                                    <button type="button" 
                                            class="button button-small mv-preview-plantilla" 
                                            data-plantilla-id="<?php echo $plantilla->id; ?>">
                                        <?php _e('Preview', 'modulo-ventas'); ?>
                                    </button>
                                    
                                    <?php if (!$plantilla->es_predeterminada): ?>
                                        <button type="button" 
                                                class="button button-small mv-duplicar-plantilla" 
                                                data-plantilla-id="<?php echo $plantilla->id; ?>">
                                            <?php _e('Duplicar', 'modulo-ventas'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Acciones en lote -->
        <div class="mv-bulk-actions" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
            <h3><?php _e('Acciones Rápidas', 'modulo-ventas'); ?></h3>
            <div style="display: flex; gap: 15px; align-items: center; margin-top: 10px;">
                <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new&tipo=cotizacion'); ?>" class="button">
                    <?php _e('Nueva Plantilla de Cotización', 'modulo-ventas'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=new&tipo=factura'); ?>" class="button">
                    <?php _e('Nueva Plantilla de Factura', 'modulo-ventas'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=config'); ?>" class="button button-secondary">
                    <?php _e('Configurar Asignaciones', 'modulo-ventas'); ?>
                </a>
            </div>
        </div>
        
    <?php endif; ?>
    
    <!-- Información de ayuda -->
    <div class="mv-help-box" style="margin-top: 30px; padding: 20px; background: #fff; border-left: 4px solid #2c5aa0; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3><?php _e('Información sobre Plantillas PDF', 'modulo-ventas'); ?></h3>
        <ul style="margin-left: 20px;">
            <li><?php _e('Las plantillas activas se utilizan automáticamente para generar PDFs', 'modulo-ventas'); ?></li>
            <li><?php _e('Puedes tener múltiples plantillas por tipo de documento', 'modulo-ventas'); ?></li>
            <li><?php _e('Las plantillas predeterminadas no se pueden eliminar', 'modulo-ventas'); ?></li>
            <li><?php _e('Usa el editor visual para personalizar el diseño y contenido', 'modulo-ventas'); ?></li>
            <li><?php _e('Las variables dinámicas se reemplazan automáticamente con datos reales', 'modulo-ventas'); ?></li>
        </ul>
        
        <p style="margin-top: 15px;">
            <strong><?php _e('¿Necesitas ayuda?', 'modulo-ventas'); ?></strong>
            <a href="#" style="margin-left: 10px;"><?php _e('Ver Documentación', 'modulo-ventas'); ?></a> |
            <a href="#" style="margin-left: 5px;"><?php _e('Soporte Técnico', 'modulo-ventas'); ?></a>
        </p>
    </div>
</div>

<style>
/* Estilos específicos para la lista de plantillas */
.mv-toggle-switch input:checked + span {
    background-color: #2c5aa0 !important;
}

.mv-toggle-switch input:checked + span + span {
    transform: translateX(20px) !important;
}

.mv-plantillas-table tr[data-estado="inactiva"] {
    opacity: 0.6;
}

.mv-tipo-badge {
    text-transform: capitalize;
}

.mv-plantillas-table .row-actions {
    margin-top: 5px;
}

.mv-stats-panel .stat-item {
    text-align: center;
}

@media (max-width: 782px) {
    .mv-stats-panel {
        flex-direction: column;
        gap: 15px !important;
    }
    
    .mv-filters form {
        flex-direction: column;
        align-items: stretch !important;
        gap: 10px !important;
    }
    
    .mv-bulk-actions div {
        flex-direction: column;
        align-items: stretch !important;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Confirmación para eliminación
    $('.mv-eliminar-plantilla').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php echo esc_js(__('¿Estás seguro de eliminar esta plantilla? Esta acción no se puede deshacer.', 'modulo-ventas')); ?>')) {
            return;
        }
        
        // Aquí se ejecutaría la eliminación via AJAX
        // Ya está implementado en el JavaScript principal
    });
    
    // Tooltips para estados
    $('.mv-toggle-switch').attr('title', '<?php echo esc_js(__('Activar/Desactivar plantilla', 'modulo-ventas')); ?>');
    
    // Destacar fila al hacer hover
    $('.mv-plantillas-table tbody tr').hover(
        function() {
            $(this).css('background-color', '#f8f9fa');
        },
        function() {
            $(this).css('background-color', '');
        }
    );
});
</script>