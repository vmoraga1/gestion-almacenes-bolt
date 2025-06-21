<?php
/**
 * Clase para mostrar lista de cotizaciones
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

class Cotizaciones_List_Table extends WP_List_Table {
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => __('Cotización', 'modulo-ventas'),
            'plural'   => __('Cotizaciones', 'modulo-ventas'),
            'ajax'     => false
        ));
        
        $this->db = new Modulo_Ventas_DB();
    }
    
    /**
     * Columnas de la tabla
     */
    public function get_columns() {
        return array(
            'cb'               => '<input type="checkbox" />',
            'numero'           => __('Número', 'modulo-ventas'),
            'fecha'            => __('Fecha', 'modulo-ventas'),
            'cliente'          => __('Cliente', 'modulo-ventas'),
            'total'            => __('Total', 'modulo-ventas'),
            'estado'           => __('Estado', 'modulo-ventas'),
            'fecha_vencimiento' => __('Vencimiento', 'modulo-ventas'),
            'acciones'         => __('Acciones', 'modulo-ventas')
        );
    }
    
    /**
     * Columnas ordenables
     */
    public function get_sortable_columns() {
        return array(
            'numero' => array('numero', false),
            'fecha'  => array('fecha', true),
            'total'  => array('total', false),
            'estado' => array('estado', false)
        );
    }
    
    /**
     * Columna por defecto
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'numero':
            case 'fecha':
            case 'total':
            case 'estado':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }
    
    /**
     * Columna checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="cotizacion[]" value="%s" />',
            $item['id']
        );
    }
    
    /**
     * Columna número
     */
    public function column_numero($item) {
        $actions = array(
            'view' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $item['id']),
                __('Ver', 'modulo-ventas')
            ),
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=modulo-ventas-editar-cotizacion&id=' . $item['id']),
                __('Editar', 'modulo-ventas')
            ),
            'duplicate' => sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=modulo-ventas-cotizaciones&action=duplicate&id=' . $item['id']),
                    'duplicate_cotizacion_' . $item['id']
                ),
                __('Duplicar', 'modulo-ventas')
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=modulo-ventas-cotizaciones&action=delete&id=' . $item['id']),
                    'delete_cotizacion_' . $item['id']
                ),
                __('¿Está seguro de eliminar esta cotización?', 'modulo-ventas'),
                __('Eliminar', 'modulo-ventas')
            )
        );
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            admin_url('admin.php?page=modulo-ventas-ver-cotizacion&id=' . $item['id']),
            esc_html($item['numero']),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Columna fecha
     */
    public function column_fecha($item) {
        return date_i18n(get_option('date_format'), strtotime($item['fecha']));
    }
    
    /**
     * Columna cliente
     */
    public function column_cliente($item) {
        $cliente = $this->db->obtener_cliente($item['cliente_id']);
        if ($cliente) {
            return sprintf(
                '<a href="%s">%s</a><br><small>%s</small>',
                admin_url('admin.php?page=modulo-ventas-ver-cliente&id=' . $cliente->id),
                esc_html($cliente->razon_social),
                esc_html($cliente->rut)
            );
        }
        return '-';
    }
    
    /**
     * Columna total
     */
    public function column_total($item) {
        return wc_price($item['total']);
    }
    
    /**
     * Columna estado
     */
    public function column_estado($item) {
        $estados = array(
            'borrador' => array('label' => __('Borrador', 'modulo-ventas'), 'color' => '#6c757d'),
            'enviada' => array('label' => __('Enviada', 'modulo-ventas'), 'color' => '#007cba'),
            'aceptada' => array('label' => __('Aceptada', 'modulo-ventas'), 'color' => '#00a32a'),
            'rechazada' => array('label' => __('Rechazada', 'modulo-ventas'), 'color' => '#d63638'),
            'vencida' => array('label' => __('Vencida', 'modulo-ventas'), 'color' => '#996800'),
            'convertida' => array('label' => __('Convertida', 'modulo-ventas'), 'color' => '#3858e9')
        );
        
        $estado_info = isset($estados[$item['estado']]) ? $estados[$item['estado']] : array('label' => $item['estado'], 'color' => '#666');
        
        return sprintf(
            '<span style="background-color: %s; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">%s</span>',
            esc_attr($estado_info['color']),
            esc_html($estado_info['label'])
        );
    }
    
    /**
     * Columna fecha vencimiento
     */
    public function column_fecha_vencimiento($item) {
        if (!empty($item['fecha_vencimiento'])) {
            $fecha = strtotime($item['fecha_vencimiento']);
            $hoy = strtotime(date('Y-m-d'));
            
            // Calcular días restantes
            $dias_restantes = floor(($fecha - $hoy) / (60 * 60 * 24));
            
            $clase = '';
            if ($dias_restantes < 0) {
                $clase = 'vencida';
                $texto = __('Vencida', 'modulo-ventas');
            } elseif ($dias_restantes <= 3) {
                $clase = 'por-vencer';
                $texto = sprintf(_n('%d día', '%d días', $dias_restantes, 'modulo-ventas'), $dias_restantes);
            } else {
                $texto = date_i18n(get_option('date_format'), $fecha);
            }
            
            return sprintf(
                '<span class="fecha-vencimiento %s">%s</span>',
                esc_attr($clase),
                esc_html($texto)
            );
        }
        
        return '-';
    }
    
    /**
     * Columna acciones
     */
    public function column_acciones($item) {
        $acciones = array();
        
        // PDF
        $acciones[] = sprintf(
            '<a href="%s" class="button button-small" target="_blank" title="%s"><span class="dashicons dashicons-pdf"></span></a>',
            wp_nonce_url(
                admin_url('admin.php?page=modulo-ventas-cotizaciones&action=pdf&id=' . $item['id']),
                'pdf_cotizacion_' . $item['id']
            ),
            __('Descargar PDF', 'modulo-ventas')
        );
        
        // Email
        $acciones[] = sprintf(
            '<a href="%s" class="button button-small" title="%s"><span class="dashicons dashicons-email"></span></a>',
            wp_nonce_url(
                admin_url('admin.php?page=modulo-ventas-cotizaciones&action=email&id=' . $item['id']),
                'email_cotizacion_' . $item['id']
            ),
            __('Enviar por Email', 'modulo-ventas')
        );
        
        // Convertir a venta
        if (in_array($item['estado'], array('aceptada', 'enviada'))) {
            $acciones[] = sprintf(
                '<a href="%s" class="button button-small button-primary" title="%s"><span class="dashicons dashicons-cart"></span></a>',
                wp_nonce_url(
                    admin_url('admin.php?page=modulo-ventas-cotizaciones&action=convert&id=' . $item['id']),
                    'convert_cotizacion_' . $item['id']
                ),
                __('Convertir a Venta', 'modulo-ventas')
            );
        }
        
        return implode(' ', $acciones);
    }
    
    /**
     * Acciones masivas
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Eliminar', 'modulo-ventas'),
            'export' => __('Exportar', 'modulo-ventas')
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
        $per_page = $this->get_items_per_page('cotizaciones_per_page', 20);
        $current_page = $this->get_pagenum();
        
        // Filtros
        $args = array(
            'limite' => $per_page,
            'offset' => ($current_page - 1) * $per_page,
            'orden' => isset($_GET['orderby']) ? $_GET['orderby'] : 'fecha',
            'orden_dir' => isset($_GET['order']) ? $_GET['order'] : 'DESC'
        );
        
        // Búsqueda
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $args['buscar'] = sanitize_text_field($_REQUEST['s']);
        }
        
        // Filtro por estado
        if (isset($_REQUEST['estado']) && !empty($_REQUEST['estado'])) {
            $args['estado'] = sanitize_text_field($_REQUEST['estado']);
        }

        // Filtro por fechas
        if (isset($_REQUEST['fecha_desde']) && !empty($_REQUEST['fecha_desde'])) {
            $args['fecha_desde'] = sanitize_text_field($_REQUEST['fecha_desde']);
        }

        if (isset($_REQUEST['fecha_hasta']) && !empty($_REQUEST['fecha_hasta'])) {
            $args['fecha_hasta'] = sanitize_text_field($_REQUEST['fecha_hasta']);
        }
        
        // Obtener cotizaciones
        $resultado = $this->db->obtener_cotizaciones($args);
        
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
            $ids = isset($_REQUEST['cotizacion']) ? $_REQUEST['cotizacion'] : array();
            
            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $this->db->eliminar_cotizacion(intval($id));
                }
                
                Modulo_Ventas_Messages::get_instance()->add_message(
                    sprintf(__('%d cotizaciones eliminadas.', 'modulo-ventas'), count($ids)),
                    'success'
                );
            }
        }
    }
    
    /**
     * Mensaje cuando no hay items
     */
    public function no_items() {
        _e('No se encontraron cotizaciones.', 'modulo-ventas');
    }
}