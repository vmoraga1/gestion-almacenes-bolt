<?php
/**
 * VISTA ADMIN - LISTA DE PLANTILLAS
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/admin/views/pdf-templates/lista-plantillas.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener plantillas agrupadas por tipo
$plantillas_por_tipo = array();
foreach ($plantillas as $plantilla) {
    $plantillas_por_tipo[$plantilla->tipo][] = $plantilla;
}

$tipos_documentos = array(
    'cotizacion' => __('Cotizaciones', 'modulo-ventas'),
    'venta' => __('Ventas', 'modulo-ventas'),
    'pedido' => __('Pedidos', 'modulo-ventas'),
    'factura' => __('Facturas', 'modulo-ventas'),
    'general' => __('General', 'modulo-ventas')
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

    <!-- Navegación por pestañas -->
    <nav class="nav-tab-wrapper">
        <a href="#all" class="nav-tab nav-tab-active" data-tab="all">
            <?php _e('Todas', 'modulo-ventas'); ?>
            <span class="count">(<?php echo count($plantillas); ?>)</span>
        </a>
        <?php foreach ($tipos_documentos as $tipo => $label): ?>
            <?php $count = isset($plantillas_por_tipo[$tipo]) ? count($plantillas_por_tipo[$tipo]) : 0; ?>
            <a href="#<?php echo $tipo; ?>" class="nav-tab" data-tab="<?php echo $tipo; ?>">
                <?php echo $label; ?>
                <span class="count">(<?php echo $count; ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Filtros -->
    <div class="mv-templates-filters" style="margin: 20px 0;">
        <select id="filter-estado" style="margin-right: 10px;">
            <option value=""><?php _e('Todos los estados', 'modulo-ventas'); ?></option>
            <option value="activa"><?php _e('Activas', 'modulo-ventas'); ?></option>
            <option value="inactiva"><?php _e('Inactivas', 'modulo-ventas'); ?></option>
            <option value="predeterminada"><?php _e('Predeterminadas', 'modulo-ventas'); ?></option>
        </select>
        
        <input type="search" id="search-plantillas" placeholder="<?php _e('Buscar plantillas...', 'modulo-ventas'); ?>" style="margin-right: 10px;">
        
        <button type="button" class="button" id="filter-reset">
            <?php _e('Limpiar Filtros', 'modulo-ventas'); ?>
        </button>
    </div>

    <!-- Contenido por pestañas -->
    <div id="tab-content-all" class="tab-content active">
        <?php $this->mostrar_tabla_plantillas($plantillas, 'all'); ?>
    </div>
    
    <?php foreach ($tipos_documentos as $tipo => $label): ?>
        <div id="tab-content-<?php echo $tipo; ?>" class="tab-content" style="display: none;">
            <?php 
            $plantillas_tipo = isset($plantillas_por_tipo[$tipo]) ? $plantillas_por_tipo[$tipo] : array();
            $this->mostrar_tabla_plantillas($plantillas_tipo, $tipo);
            ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
/**
 * Método auxiliar para mostrar tabla de plantillas
 */
function mostrar_tabla_plantillas($plantillas, $tipo) {
    if (empty($plantillas)) {
        echo '<div class="mv-no-plantillas">';
        echo '<p>' . __('No hay plantillas disponibles.', 'modulo-ventas') . '</p>';
        if ($tipo !== 'all') {
            echo '<a href="' . admin_url('admin.php?page=mv-pdf-templates&action=new&tipo=' . $tipo) . '" class="button button-primary">';
            echo __('Crear Primera Plantilla', 'modulo-ventas');
            echo '</a>';
        }
        echo '</div>';
        return;
    }
    ?>
    
    <table class="wp-list-table widefat fixed striped mv-plantillas-table">
        <thead>
            <tr>
                <th scope="col" class="column-nombre column-primary">
                    <?php _e('Nombre', 'modulo-ventas'); ?>
                </th>
                <th scope="col" class="column-tipo">
                    <?php _e('Tipo', 'modulo-ventas'); ?>
                </th>
                <th scope="col" class="column-estado">
                    <?php _e('Estado', 'modulo-ventas'); ?>
                </th>
                <th scope="col" class="column-uso">
                    <?php _e('En Uso', 'modulo-ventas'); ?>
                </th>
                <th scope="col" class="column-modificacion">
                    <?php _e('Última Modificación', 'modulo-ventas'); ?>
                </th>
                <th scope="col" class="column-acciones">
                    <?php _e('Acciones', 'modulo-ventas'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($plantillas as $plantilla): ?>
                <tr data-plantilla-id="<?php echo $plantilla->id; ?>" 
                    data-tipo="<?php echo $plantilla->tipo; ?>"
                    data-estado="<?php echo $plantilla->activa ? 'activa' : 'inactiva'; ?>"
                    data-predeterminada="<?php echo $plantilla->es_predeterminada ? '1' : '0'; ?>">
                    
                    <td class="column-nombre column-primary">
                        <strong>
                            <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $plantilla->id); ?>">
                                <?php echo esc_html($plantilla->nombre); ?>
                            </a>
                        </strong>
                        
                        <?php if ($plantilla->es_predeterminada): ?>
                            <span class="dashicons dashicons-star-filled" style="color: #f39c12;" title="<?php _e('Plantilla predeterminada', 'modulo-ventas'); ?>"></span>
                        <?php endif; ?>
                        
                        <?php if (!empty($plantilla->descripcion)): ?>
                            <br><em style="color: #666;"><?php echo esc_html($plantilla->descripcion); ?></em>
                        <?php endif; ?>
                        
                        <button type="button" class="toggle-row">
                            <span class="screen-reader-text"><?php _e('Mostrar más detalles', 'modulo-ventas'); ?></span>
                        </button>
                    </td>
                    
                    <td class="column-tipo" data-colname="<?php _e('Tipo', 'modulo-ventas'); ?>">
                        <span class="mv-tipo-badge mv-tipo-<?php echo $plantilla->tipo; ?>">
                            <?php echo ucfirst($plantilla->tipo); ?>
                        </span>
                    </td>
                    
                    <td class="column-estado" data-colname="<?php _e('Estado', 'modulo-ventas'); ?>">
                        <label class="mv-toggle-switch">
                            <input type="checkbox" 
                                   class="plantilla-toggle-estado" 
                                   data-plantilla-id="<?php echo $plantilla->id; ?>"
                                   <?php checked($plantilla->activa, 1); ?>
                                   <?php echo $plantilla->es_predeterminada ? 'disabled' : ''; ?>>
                            <span class="mv-toggle-slider"></span>
                        </label>
                        <span class="mv-estado-text">
                            <?php echo $plantilla->activa ? __('Activa', 'modulo-ventas') : __('Inactiva', 'modulo-ventas'); ?>
                        </span>
                    </td>
                    
                    <td class="column-uso" data-colname="<?php _e('En Uso', 'modulo-ventas'); ?>">
                        <?php
                        // Verificar si esta plantilla está asignada como activa
                        global $wpdb;
                        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
                        $en_uso = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $tabla_config WHERE plantilla_id = %d AND activa = 1",
                            $plantilla->id
                        ));
                        
                        if ($en_uso > 0) {
                            echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . __('Plantilla en uso', 'modulo-ventas') . '"></span>';
                            echo ' <span style="color: #46b450;">' . __('Sí', 'modulo-ventas') . '</span>';
                        } else {
                            echo '<span style="color: #666;">' . __('No', 'modulo-ventas') . '</span>';
                        }
                        ?>
                    </td>
                    
                    <td class="column-modificacion" data-colname="<?php _e('Última Modificación', 'modulo-ventas'); ?>">
                        <?php
                        $fecha = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $plantilla->fecha_modificacion);
                        echo esc_html($fecha);
                        ?>
                        <br><em style="color: #666; font-size: 12px;">
                            <?php echo sprintf(__('Versión %s', 'modulo-ventas'), $plantilla->version); ?>
                        </em>
                    </td>
                    
                    <td class="column-acciones" data-colname="<?php _e('Acciones', 'modulo-ventas'); ?>">
                        <div class="mv-acciones-plantilla">
                            <a href="<?php echo admin_url('admin.php?page=mv-pdf-templates&action=edit&plantilla_id=' . $plantilla->id); ?>" 
                                class="button button-small" 
                                title="<?php _e('Editar plantilla', 'modulo-ventas'); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </a>
                            
                            <button type="button" 
                                    class="button button-small mv-duplicar-plantilla" 
                                    data-plantilla-id="<?php echo $plantilla->id; ?>"
                                    title="<?php _e('Duplicar plantilla', 'modulo-ventas'); ?>">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            
                            <button type="button" 
                                    class="button button-small mv-preview-plantilla" 
                                    data-plantilla-id="<?php echo $plantilla->id; ?>"
                                    title="<?php _e('Vista previa', 'modulo-ventas'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            
                            <?php if (!$plantilla->es_predeterminada): ?>
                                <button type="button" 
                                        class="button button-small button-link-delete mv-eliminar-plantilla" 
                                        data-plantilla-id="<?php echo $plantilla->id; ?>"
                                        title="<?php _e('Eliminar plantilla', 'modulo-ventas'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php
}
?>

<style>
/* Estilos específicos para la lista de plantillas */
.mv-plantillas-table .column-nombre { width: 25%; }
.mv-plantillas-table .column-tipo { width: 12%; }
.mv-plantillas-table .column-estado { width: 15%; }
.mv-plantillas-table .column-uso { width: 10%; }
.mv-plantillas-table .column-modificacion { width: 18%; }
.mv-plantillas-table .column-acciones { width: 20%; }

.mv-tipo-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: white;
}

.mv-tipo-cotizacion { background-color: #2271b1; }
.mv-tipo-venta { background-color: #00a32a; }
.mv-tipo-pedido { background-color: #dba617; }
.mv-tipo-factura { background-color: #d63384; }
.mv-tipo-general { background-color: #6c757d; }

.mv-toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    margin-right: 8px;
}

.mv-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.mv-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.mv-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .mv-toggle-slider {
    background-color: #2271b1;
}

input:checked + .mv-toggle-slider:before {
    transform: translateX(20px);
}

input:disabled + .mv-toggle-slider {
    background-color: #f0f0f1;
    cursor: not-allowed;
}

.mv-acciones-plantilla {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.mv-acciones-plantilla .button {
    min-width: auto;
    padding: 6px 8px;
}

.mv-no-plantillas {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border: 1px dashed #ccd0d4;
    border-radius: 4px;
}

.tab-content {
    margin-top: 20px;
}

.nav-tab .count {
    color: #646970;
    font-weight: normal;
}

.mv-templates-filters {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

@media (max-width: 782px) {
    .mv-acciones-plantilla {
        justify-content: center;
    }
    
    .mv-toggle-switch {
        width: 36px;
        height: 20px;
    }
    
    .mv-toggle-slider:before {
        height: 14px;
        width: 14px;
    }
    
    input:checked + .mv-toggle-slider:before {
        transform: translateX(16px);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Navegación por pestañas
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var tab = $(this).data('tab');
        
        // Actualizar pestañas activas
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Mostrar contenido correspondiente
        $('.tab-content').hide();
        $('#tab-content-' + tab).show();
    });
    
    // Filtros y búsqueda
    $('#filter-estado, #search-plantillas').on('change keyup', function() {
        filtrarPlantillas();
    });
    
    $('#filter-reset').on('click', function() {
        $('#filter-estado').val('');
        $('#search-plantillas').val('');
        filtrarPlantillas();
    });
    
    function filtrarPlantillas() {
        var estadoFiltro = $('#filter-estado').val();
        var busqueda = $('#search-plantillas').val().toLowerCase();
        
        $('.mv-plantillas-table tbody tr').each(function() {
            var $row = $(this);
            var mostrar = true;
            
            // Filtro por estado
            if (estadoFiltro) {
                var estado = $row.data('estado');
                var esPredeterminada = $row.data('predeterminada') == '1';
                
                if (estadoFiltro === 'activa' && estado !== 'activa') {
                    mostrar = false;
                } else if (estadoFiltro === 'inactiva' && estado !== 'inactiva') {
                    mostrar = false;
                } else if (estadoFiltro === 'predeterminada' && !esPredeterminada) {
                    mostrar = false;
                }
            }
            
            // Filtro por búsqueda
            if (busqueda && mostrar) {
                var nombre = $row.find('.column-nombre a').text().toLowerCase();
                var descripcion = $row.find('.column-nombre em').text().toLowerCase();
                
                if (nombre.indexOf(busqueda) === -1 && descripcion.indexOf(busqueda) === -1) {
                    mostrar = false;
                }
            }
            
            $row.toggle(mostrar);
        });
    }
    
    console.log('Lista de plantillas PDF cargada');
});
</script>