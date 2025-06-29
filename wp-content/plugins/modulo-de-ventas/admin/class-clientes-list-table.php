<?php
/**
 * Clase para mostrar lista de clientes
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Incluir clase padre si no está cargada
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Clientes_List_Table extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => __('Cliente', 'modulo-ventas'),
            'plural'   => __('Clientes', 'modulo-ventas'),
            'ajax'     => false
        ));
        
        $this->db = new Modulo_Ventas_DB();
    }
    
    /**
     * Columnas de la tabla
     */
    public function get_columns() {
        return array(
            'cb'            => '<input type="checkbox" />',
            'razon_social'  => __('Razón Social', 'modulo-ventas'),
            'rut'           => __('RUT', 'modulo-ventas'),
            'email'         => __('Email', 'modulo-ventas'),
            'telefono'      => __('Teléfono', 'modulo-ventas'),
            'ciudad'        => __('Ciudad', 'modulo-ventas'),
            'cotizaciones'  => __('Cotizaciones', 'modulo-ventas'),
            'ultima_actividad' => __('Última Actividad', 'modulo-ventas'),
            'estado'        => __('Estado', 'modulo-ventas')
        );
    }
    
    /**
     * Columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'razon_social' => array('razon_social', false),
            'rut'          => array('rut', false),
            'email'        => array('email', false),
            'estado'       => array('estado', false),
            'ultima_actividad' => array('fecha_modificacion', true)
        );
    }
    
    /**
     * Columna por defecto
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'razon_social':
            case 'rut':
            case 'email':
            case 'telefono':
            case 'ciudad':
                return esc_html($item[$column_name]);
            default:
                return isset($item[$column_name]) ? $item[$column_name] : '-';
        }
    }
    
    /**
     * Columna checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="cliente[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Columna razón social con acciones
     */
    public function column_razon_social($item) {
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=modulo-ventas-editar-cliente&id=' . $item['id']),
                __('Editar', 'modulo-ventas')
            ),
            'view' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=modulo-ventas-ver-cliente&id=' . $item['id']),
                __('Ver', 'modulo-ventas')
            )
        );
        
        // Agregar acción de eliminar solo si no tiene cotizaciones
        // Agregar temporalmente antes de generar el enlace:
        error_log('=== GENERANDO ENLACE ELIMINAR ===');
        error_log('ID cliente: ' . $item['id']);
        error_log('Acción nonce: eliminar_cliente_' . $item['id']);
        $url_eliminar = wp_nonce_url(
            admin_url('admin.php?page=modulo-ventas-clientes&action=delete&id=' . $item['id']),
            'delete_cliente_' . $item['id']
        );
        error_log('URL generada: ' . $url_eliminar);

        $actions['delete'] = sprintf(
            '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
            $url_eliminar,
            __('¿Está seguro de eliminar este cliente?', 'modulo-ventas'),
            __('Eliminar', 'modulo-ventas')
        );

        if ($item['cotizaciones'] == 0) {
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=modulo-ventas-clientes&action=delete&id=' . $item['id']),
                    'delete_cliente_' . $item['id']
                ),
                __('¿Está seguro de eliminar este cliente?', 'modulo-ventas'),
                __('Eliminar', 'modulo-ventas')
            );
        }
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            admin_url('admin.php?page=modulo-ventas-editar-cliente&id=' . $item['id']),
            esc_html($item['razon_social']),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Columna RUT
     */
    public function column_rut($item) {
        return mv_formatear_rut($item['rut']);
    }
    
    /**
     * Columna email
     */
    public function column_email($item) {
        if ($item['email']) {
            return sprintf(
                '<a href="mailto:%s">%s</a>',
                esc_attr($item['email']),
                esc_html($item['email'])
            );
        }
        return '-';
    }
    
    /**
     * Columna ciudad
     */
    public function column_ciudad($item) {
        $ciudad_parts = array();
        
        if (!empty($item['ciudad_facturacion'])) {
            $ciudad_parts[] = $item['ciudad_facturacion'];
        }
        if (!empty($item['region_facturacion'])) {
            $ciudad_parts[] = $item['region_facturacion'];
        }
        
        return !empty($ciudad_parts) ? implode(', ', $ciudad_parts) : '-';
    }
    
    /**
     * Columna cotizaciones
     */
    public function column_cotizaciones($item) {
        if ($item['cotizaciones'] > 0) {
            return sprintf(
                '<a href="%s"><strong>%d</strong></a>',
                admin_url('admin.php?page=modulo-ventas-cotizaciones&cliente_id=' . $item['id']),
                $item['cotizaciones']
            );
        }
        return '<span style="color: #999;">0</span>';
    }
    
    /**
     * Columna última actividad
     */
    public function column_ultima_actividad($item) {
        if ($item['fecha_modificacion'] && $item['fecha_modificacion'] != '0000-00-00 00:00:00') {
            return human_time_diff(strtotime($item['fecha_modificacion']), current_time('timestamp')) . ' ' . __('atrás', 'modulo-ventas');
        }
        return '-';
    }
    
    /**
     * Columna estado
     */
    public function column_estado($item) {
        $estado_text = $item['estado'] == 'activo' ? __('Activo', 'modulo-ventas') : __('Inactivo', 'modulo-ventas');
        $estado_class = $item['estado'] == 'activo' ? 'success' : 'warning';
        
        return sprintf(
            '<span class="mv-badge mv-badge-%s">%s</span>',
            $estado_class,
            $estado_text
        );
    }
    
    /**
     * Acciones masivas
     */
    public function get_bulk_actions() {
        return array(
            'activate' => __('Activar', 'modulo-ventas'),
            'deactivate' => __('Desactivar', 'modulo-ventas'),
            'export' => __('Exportar', 'modulo-ventas'),
            'delete' => __('Eliminar', 'modulo-ventas')
        );
    }
    
    /**
     * Preparar items
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        // Procesar acciones masivas
        $this->process_bulk_action();
        
        // Obtener datos
        $per_page = $this->get_items_per_page('clientes_per_page', 20);
        $current_page = $this->get_pagenum();
        
        // Parámetros de búsqueda
        $args = array(
            'limite' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orden' => isset($_GET['orderby']) ? $_GET['orderby'] : 'razon_social',
            'orden_dir' => isset($_GET['order']) ? $_GET['order'] : 'ASC'
        );
        
        // Búsqueda
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $args['buscar'] = sanitize_text_field($_REQUEST['s']);
        }
        
        // Filtro por estado
        if (isset($_REQUEST['estado']) && !empty($_REQUEST['estado'])) {
            $args['estado'] = sanitize_text_field($_REQUEST['estado']);
        }
        
        // Obtener clientes con conteo de cotizaciones
        $resultado = $this->db->obtener_clientes_con_estadisticas($args);
        
        $this->items = $resultado['items'];
        
        $this->set_pagination_args(array(
            'total_items' => $resultado['total'],
            'per_page'    => $per_page,
            'total_pages' => ceil($resultado['total'] / $per_page)
        ));
    }
    
    /**
     * Procesar acciones masivas
     */
    public function process_bulk_action() {
        // Eliminar múltiples
        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['cliente']) ? $_REQUEST['cliente'] : array();
            
            if (!empty($ids)) {
                $eliminados = 0;
                $errores = 0;
                
                foreach ($ids as $id) {
                    // Verificar que no tenga cotizaciones
                    $cotizaciones = $this->db->contar_cotizaciones_cliente($id);
                    if ($cotizaciones == 0) {
                        $resultado = $this->db->eliminar_cliente(intval($id));
                        if ($resultado) {
                            $eliminados++;
                        } else {
                            $errores++;
                        }
                    } else {
                        $errores++;
                    }
                }
                
                if ($eliminados > 0) {
                    add_action('admin_notices', function() use ($eliminados) {
                        echo '<div class="notice notice-success is-dismissible"><p>';
                        printf(_n('%d cliente eliminado.', '%d clientes eliminados.', $eliminados, 'modulo-ventas'), $eliminados);
                        echo '</p></div>';
                    });
                }
                
                if ($errores > 0) {
                    add_action('admin_notices', function() use ($errores) {
                        echo '<div class="notice notice-error is-dismissible"><p>';
                        printf(_n('%d cliente no pudo ser eliminado.', '%d clientes no pudieron ser eliminados.', $errores, 'modulo-ventas'), $errores);
                        echo '</p></div>';
                    });
                }
            }
        }
        
        // Activar/Desactivar múltiples
        if ('activate' === $this->current_action() || 'deactivate' === $this->current_action()) {
            $ids = isset($_REQUEST['cliente']) ? $_REQUEST['cliente'] : array();
            $nuevo_estado = ('activate' === $this->current_action()) ? 'activo' : 'inactivo';
            
            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $this->db->actualizar_cliente($id, array('estado' => $nuevo_estado));
                }
                
                add_action('admin_notices', function() use ($ids, $nuevo_estado) {
                    echo '<div class="notice notice-success is-dismissible"><p>';
                    printf(
                        _n('%d cliente %s.', '%d clientes %s.', count($ids), 'modulo-ventas'),
                        count($ids),
                        $nuevo_estado == 'activo' ? __('activado', 'modulo-ventas') : __('desactivado', 'modulo-ventas')
                    );
                    echo '</p></div>';
                });
            }
        }
    }
    
    /**
     * Mensaje cuando no hay items
     */
    public function no_items() {
        _e('No se encontraron clientes.', 'modulo-ventas');
    }
    
    /**
     * Vista adicional para filtros
     */
    public function extra_tablenav($which) {
        if ($which == 'top') {
            ?>
            <div class="alignleft actions">
                <select name="estado" id="filter-by-estado">
                    <option value=""><?php _e('Todos los estados', 'modulo-ventas'); ?></option>
                    <option value="activo" <?php selected(isset($_REQUEST['estado']) && $_REQUEST['estado'] == 'activo'); ?>>
                        <?php _e('Activo', 'modulo-ventas'); ?>
                    </option>
                    <option value="inactivo" <?php selected(isset($_REQUEST['estado']) && $_REQUEST['estado'] == 'inactivo'); ?>>
                        <?php _e('Inactivo', 'modulo-ventas'); ?>
                    </option>
                </select>
                <?php submit_button(__('Filtrar', 'modulo-ventas'), 'button', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
}