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

// Variables disponibles:
// $cotizaciones_table - Instancia de WP_List_Table
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-media-text"></span>
        <?php _e('Cotizaciones', 'modulo-ventas'); ?>
    </h1>
    
    <a href="<?php echo esc_url(mv_admin_url('nueva-cotizacion')); ?>" class="page-title-action">
        <?php _e('Añadir nueva', 'modulo-ventas'); ?>
    </a>
    
    <?php if (isset($_REQUEST['s']) && $_REQUEST['s']) : ?>
        <span class="subtitle">
            <?php printf(__('Resultados de búsqueda para: %s', 'modulo-ventas'), '<strong>' . esc_html($_REQUEST['s']) . '</strong>'); ?>
        </span>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php 
    // Mostrar mensajes
    if (isset($_GET['message'])) {
        $message = '';
        $type = 'success';
        
        switch ($_GET['message']) {
            case 'created':
                $message = __('Cotización creada exitosamente.', 'modulo-ventas');
                break;
            case 'updated':
                $message = __('Cotización actualizada exitosamente.', 'modulo-ventas');
                break;
            case 'deleted':
                $message = __('Cotización eliminada exitosamente.', 'modulo-ventas');
                break;
            case 'bulk-deleted':
                $count = isset($_GET['count']) ? intval($_GET['count']) : 1;
                $message = sprintf(_n('%d cotización eliminada.', '%d cotizaciones eliminadas.', $count, 'modulo-ventas'), $count);
                break;
            case 'converted':
                $message = __('Cotización convertida en pedido exitosamente.', 'modulo-ventas');
                break;
            case 'duplicated':
                $message = __('Cotización duplicada exitosamente.', 'modulo-ventas');
                break;
            case 'error':
                $message = isset($_GET['error_message']) ? esc_html($_GET['error_message']) : __('Ha ocurrido un error.', 'modulo-ventas');
                $type = 'error';
                break;
        }
        
        if ($message) {
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $message . '</p></div>';
        }
    }
    ?>
    
    <!-- Filtros avanzados -->
    <div class="mv-filters-wrapper">
        <div class="mv-filters-toggle">
            <button type="button" class="button mv-toggle-filters">
                <span class="dashicons dashicons-filter"></span>
                <?php _e('Filtros avanzados', 'modulo-ventas'); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            
            <?php
            // Mostrar filtros activos
            $active_filters = array();
            if (!empty($_GET['estado'])) {
                $estados = mv_get_estados_cotizacion();
                $active_filters[] = sprintf(__('Estado: %s', 'modulo-ventas'), $estados[$_GET['estado']]['label']);
            }
            if (!empty($_GET['cliente_id'])) {
                $cliente = mv_db()->obtener_cliente(intval($_GET['cliente_id']));
                if ($cliente) {
                    $active_filters[] = sprintf(__('Cliente: %s', 'modulo-ventas'), $cliente->razon_social);
                }
            }
            if (!empty($_GET['fecha_desde']) || !empty($_GET['fecha_hasta'])) {
                $active_filters[] = __('Rango de fechas aplicado', 'modulo-ventas');
            }
            
            if (!empty($active_filters)) : ?>
                <div class="mv-active-filters">
                    <span><?php _e('Filtros activos:', 'modulo-ventas'); ?></span>
                    <?php foreach ($active_filters as $filter) : ?>
                        <span class="mv-filter-tag"><?php echo esc_html($filter); ?></span>
                    <?php endforeach; ?>
                    <a href="<?php echo esc_url(mv_admin_url('cotizaciones')); ?>" class="mv-clear-filters">
                        <?php _e('Limpiar filtros', 'modulo-ventas'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mv-filters-content" style="display: none;">
            <form method="get" action="">
                <input type="hidden" name="page" value="modulo-ventas-cotizaciones">
                
                <div class="mv-filter-row">
                    <div class="mv-filter-group">
                        <label for="filter-estado"><?php _e('Estado', 'modulo-ventas'); ?></label>
                        <select name="estado" id="filter-estado" class="mv-select2">
                            <option value=""><?php _e('Todos los estados', 'modulo-ventas'); ?></option>
                            <?php foreach (mv_get_estados_cotizacion() as $key => $estado) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($_GET['estado']) ? $_GET['estado'] : '', $key); ?>>
                                    <?php echo esc_html($estado['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mv-filter-group">
                        <label for="filter-cliente"><?php _e('Cliente', 'modulo-ventas'); ?></label>
                        <select name="cliente_id" id="filter-cliente" class="mv-select2-ajax" data-action="mv_buscar_cliente">
                            <?php if (!empty($_GET['cliente_id'])) : 
                                $cliente = mv_db()->obtener_cliente(intval($_GET['cliente_id']));
                                if ($cliente) : ?>
                                    <option value="<?php echo esc_attr($cliente->id); ?>" selected>
                                        <?php echo esc_html($cliente->razon_social . ' - ' . mv_formatear_rut($cliente->rut)); ?>
                                    </option>
                                <?php endif;
                            endif; ?>
                        </select>
                    </div>
                    
                    <div class="mv-filter-group">
                        <label for="filter-vendedor"><?php _e('Vendedor', 'modulo-ventas'); ?></label>
                        <select name="vendedor_id" id="filter-vendedor" class="mv-select2">
                            <option value=""><?php _e('Todos los vendedores', 'modulo-ventas'); ?></option>
                            <?php foreach (mv_get_vendedores() as $vendedor) : ?>
                                <option value="<?php echo esc_attr($vendedor->ID); ?>" 
                                        <?php selected(isset($_GET['vendedor_id']) ? $_GET['vendedor_id'] : '', $vendedor->ID); ?>>
                                    <?php echo esc_html($vendedor->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mv-filter-row">
                    <div class="mv-filter-group">
                        <label for="filter-fecha-desde"><?php _e('Fecha desde', 'modulo-ventas'); ?></label>
                        <input type="date" 
                               name="fecha_desde" 
                               id="filter-fecha-desde" 
                               value="<?php echo esc_attr(isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ''); ?>"
                               class="mv-datepicker">
                    </div>
                    
                    <div class="mv-filter-group">
                        <label for="filter-fecha-hasta"><?php _e('Fecha hasta', 'modulo-ventas'); ?></label>
                        <input type="date" 
                               name="fecha_hasta" 
                               id="filter-fecha-hasta" 
                               value="<?php echo esc_attr(isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ''); ?>"
                               class="mv-datepicker">
                    </div>
                    
                    <div class="mv-filter-group">
                        <label for="filter-monto-min"><?php _e('Monto mínimo', 'modulo-ventas'); ?></label>
                        <input type="number" 
                               name="monto_min" 
                               id="filter-monto-min" 
                               value="<?php echo esc_attr(isset($_GET['monto_min']) ? $_GET['monto_min'] : ''); ?>"
                               min="0"
                               step="1000"
                               placeholder="0">
                    </div>
                    
                    <div class="mv-filter-group">
                        <label for="filter-monto-max"><?php _e('Monto máximo', 'modulo-ventas'); ?></label>
                        <input type="number" 
                               name="monto_max" 
                               id="filter-monto-max" 
                               value="<?php echo esc_attr(isset($_GET['monto_max']) ? $_GET['monto_max'] : ''); ?>"
                               min="0"
                               step="1000"
                               placeholder="999999999">
                    </div>
                </div>
                
                <div class="mv-filter-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Aplicar filtros', 'modulo-ventas'); ?>
                    </button>
                    <a href="<?php echo esc_url(mv_admin_url('cotizaciones')); ?>" class="button">
                        <?php _e('Limpiar', 'modulo-ventas'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Estadísticas rápidas -->
    <div class="mv-list-stats">
        <?php
        // Obtener estadísticas con los filtros actuales
        $args_stats = array();
        if (!empty($_GET['estado'])) $args_stats['estado'] = sanitize_text_field($_GET['estado']);
        if (!empty($_GET['cliente_id'])) $args_stats['cliente_id'] = intval($_GET['cliente_id']);
        if (!empty($_GET['vendedor_id'])) $args_stats['vendedor_id'] = intval($_GET['vendedor_id']);
        if (!empty($_GET['fecha_desde'])) $args_stats['fecha_desde'] = sanitize_text_field($_GET['fecha_desde']);
        if (!empty($_GET['fecha_hasta'])) $args_stats['fecha_hasta'] = sanitize_text_field($_GET['fecha_hasta']);
        
        $total_cotizaciones = mv_db()->contar_cotizaciones($args_stats);
        
        // Valor total
        global $wpdb;
        $tabla = mv_db()->get_tabla_cotizaciones();
        $where = "1=1";
        $params = array();
        
        if (!empty($args_stats['estado'])) {
            $where .= " AND estado = %s";
            $params[] = $args_stats['estado'];
        }
        if (!empty($args_stats['cliente_id'])) {
            $where .= " AND cliente_id = %d";
            $params[] = $args_stats['cliente_id'];
        }
        if (!empty($args_stats['fecha_desde'])) {
            $where .= " AND DATE(fecha) >= %s";
            $params[] = $args_stats['fecha_desde'];
        }
        if (!empty($args_stats['fecha_hasta'])) {
            $where .= " AND DATE(fecha) <= %s";
            $params[] = $args_stats['fecha_hasta'];
        }
        
        $sql = "SELECT SUM(total) FROM {$tabla} WHERE {$where}";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        $valor_total = $wpdb->get_var($sql) ?: 0;
        ?>
        
        <div class="mv-stat-inline">
            <span class="mv-stat-label"><?php _e('Total cotizaciones:', 'modulo-ventas'); ?></span>
            <span class="mv-stat-value"><?php echo number_format($total_cotizaciones, 0, ',', '.'); ?></span>
        </div>
        
        <div class="mv-stat-inline">
            <span class="mv-stat-label"><?php _e('Valor total:', 'modulo-ventas'); ?></span>
            <span class="mv-stat-value"><?php echo mv_formato_precio($valor_total); ?></span>
        </div>
        
        <?php if ($total_cotizaciones > 0) : ?>
            <div class="mv-stat-inline">
                <span class="mv-stat-label"><?php _e('Promedio:', 'modulo-ventas'); ?></span>
                <span class="mv-stat-value"><?php echo mv_formato_precio($valor_total / $total_cotizaciones); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Formulario principal y tabla -->
    <form method="post" id="mv-cotizaciones-form">
        <?php
        // Búsqueda
        $cotizaciones_table->search_box(__('Buscar cotizaciones', 'modulo-ventas'), 'cotizacion');
        
        // Mostrar tabla
        $cotizaciones_table->display();
        ?>
    </form>
    
    <!-- Modal de acciones rápidas -->
    <div id="mv-quick-actions-modal" class="mv-modal" style="display: none;">
        <div class="mv-modal-content">
            <span class="mv-modal-close">&times;</span>
            <h2><?php _e('Acciones rápidas', 'modulo-ventas'); ?></h2>
            <div class="mv-modal-body">
                <p><?php _e('Seleccione una acción para la cotización:', 'modulo-ventas'); ?></p>
                <div class="mv-quick-actions-list">
                    <button type="button" class="button button-primary mv-action-view">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Ver detalles', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button mv-action-edit">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e('Editar', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button mv-action-duplicate">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php _e('Duplicar', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button mv-action-pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php _e('Descargar PDF', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button mv-action-email">
                        <span class="dashicons dashicons-email"></span>
                        <?php _e('Enviar por email', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button mv-action-convert">
                        <span class="dashicons dashicons-cart"></span>
                        <?php _e('Convertir a pedido', 'modulo-ventas'); ?>
                    </button>
                    <button type="button" class="button button-link-delete mv-action-delete">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Eliminar', 'modulo-ventas'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
/* Filtros */
.mv-filters-wrapper {
    background: #fff;
    border: 1px solid #c3c4c7;
    margin: 20px 0;
    padding: 15px;
}

.mv-filters-toggle {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mv-toggle-filters {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.mv-active-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}

.mv-filter-tag {
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 3px;
}

.mv-clear-filters {
    color: #b32d2e;
    text-decoration: none;
}

.mv-clear-filters:hover {
    color: #a02622;
}

.mv-filters-content {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #dcdcde;
}

.mv-filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.mv-filter-group {
    display: flex;
    flex-direction: column;
}

.mv-filter-group label {
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
}

.mv-filter-group input,
.mv-filter-group select {
    width: 100%;
}

.mv-filter-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

/* Estadísticas */
.mv-list-stats {
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    padding: 10px 15px;
    margin: 0 0 20px;
    display: flex;
    gap: 30px;
    align-items: center;
}

.mv-stat-inline {
    display: flex;
    align-items: center;
    gap: 8px;
}

.mv-stat-label {
    color: #646970;
    font-size: 13px;
}

.mv-stat-value {
    font-weight: 600;
    font-size: 14px;
}

/* Tabla personalizada */
.column-folio {
    width: 120px;
}

.column-cliente {
    width: 25%;
}

.column-fecha {
    width: 100px;
}

.column-total {
    width: 120px;
    text-align: right;
}

.column-estado {
    width: 120px;
}

.column-acciones {
    width: 100px;
    text-align: center;
}

/* Estados */
.mv-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
    color: #fff;
}

.mv-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    line-height: 14px;
}

/* Modal */
.mv-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.mv-modal-content {
    background-color: #fff;
    padding: 30px;
    border-radius: 5px;
    width: 90%;
    max-width: 500px;
    position: relative;
}

.mv-modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 28px;
    cursor: pointer;
    color: #666;
}

.mv-modal-close:hover {
    color: #000;
}

.mv-quick-actions-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 20px;
}

.mv-quick-actions-list .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 12px;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .mv-filter-row {
        grid-template-columns: 1fr;
    }
    
    .mv-list-stats {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .mv-quick-actions-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle filtros
    $('.mv-toggle-filters').on('click', function() {
        $('.mv-filters-content').slideToggle();
        $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2')
            .toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });
    
    // Inicializar Select2
    if ($.fn.select2) {
        $('.mv-select2').select2({
            width: '100%'
        });
        
        // Select2 con AJAX para clientes
        $('.mv-select2-ajax').select2({
            width: '100%',
            ajax: {
                url: ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: $(this).data('action'),
                        termino: params.term,
                        nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
                    };
                },
                processResults: function(data) {
                    if (data.success) {
                        return {
                            results: data.data.results
                        };
                    }
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: '<?php _e('Buscar cliente...', 'modulo-ventas'); ?>',
            allowClear: true
        });
    }
    
    // Modal de acciones rápidas
    var currentCotizacionId = null;
    
    $('.mv-quick-action-trigger').on('click', function(e) {
        e.preventDefault();
        currentCotizacionId = $(this).data('id');
        $('#mv-quick-actions-modal').fadeIn();
    });
    
    $('.mv-modal-close').on('click', function() {
        $('#mv-quick-actions-modal').fadeOut();
    });
    
    // Acciones del modal
    $('.mv-action-view').on('click', function() {
        if (currentCotizacionId) {
            window.location.href = '<?php echo admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id='); ?>' + currentCotizacionId;
        }
    });
    
    $('.mv-action-edit').on('click', function() {
        if (currentCotizacionId) {
            window.location.href = '<?php echo admin_url('admin.php?page=modulo-ventas-editar-cotizacion&id='); ?>' + currentCotizacionId;
        }
    });
    
    $('.mv-action-duplicate').on('click', function() {
        if (currentCotizacionId && confirm('<?php _e('¿Desea duplicar esta cotización?', 'modulo-ventas'); ?>')) {
            $.post(ajaxurl, {
                action: 'mv_duplicar_cotizacion',
                cotizacion_id: currentCotizacionId,
                nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect_url;
                } else {
                    alert(response.data.message);
                }
            });
        }
    });
    
    $('.mv-action-pdf').on('click', function() {
        if (currentCotizacionId) {
            window.open('<?php echo admin_url('admin-ajax.php?action=mv_generar_pdf_cotizacion&cotizacion_id='); ?>' + currentCotizacionId + '&nonce=<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>', '_blank');
        }
    });
    
    $('.mv-action-delete').on('click', function() {
        if (currentCotizacionId && confirm('<?php _e('¿Está seguro de eliminar esta cotización? Esta acción no se puede deshacer.', 'modulo-ventas'); ?>')) {
            $.post(ajaxurl, {
                action: 'mv_eliminar_cotizacion',
                cotizacion_id: currentCotizacionId,
                nonce: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message);
                }
            });
        }
    });
    
    // Cerrar modal al hacer clic fuera
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('mv-modal')) {
            $('.mv-modal').fadeOut();
        }
    });
    
    // Acciones en lote
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).prev('select').val();
        
        if (action === 'export') {
            e.preventDefault();
            var ids = [];
            $('input[name="cotizaciones[]"]:checked').each(function() {
                ids.push($(this).val());
            });
            
            if (ids.length === 0) {
                alert('<?php _e('Por favor seleccione al menos una cotización para exportar.', 'modulo-ventas'); ?>');
                return;
            }
            
            // Crear formulario temporal para descargar
            var form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'mv_exportar_cotizaciones'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'ids',
                value: ids.join(',')
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: '<?php echo wp_create_nonce('modulo_ventas_nonce'); ?>'
            }));
            
            form.appendTo('body').submit().remove();
        }
    });
});
</script>