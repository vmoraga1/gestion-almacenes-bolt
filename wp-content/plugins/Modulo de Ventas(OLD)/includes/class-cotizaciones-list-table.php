<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Cotizaciones_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'cotizacion',
            'plural'   => 'cotizaciones',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'        => '<input type="checkbox" />',
            'folio'     => 'Folio',
            'cliente'   => 'Cliente',
            'fecha'     => 'Fecha',
            'total'     => 'Total',
            'estado'    => 'Estado',
            'acciones'  => 'Acciones'
        ];
    }

    public function prepare_items() {
        global $wpdb;
        
        // Limpiar caché antes de preparar los items
        wp_cache_delete($wpdb->prefix . 'ventas_cotizaciones', 'ventas');
        $wpdb->flush();
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $this->get_total_items();
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    
        $this->items = $this->get_items($per_page, $current_page);
    }

    private function get_total_items() {
        global $wpdb;
        $table = $wpdb->prefix.'ventas_cotizaciones';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    private function get_items($per_page, $page_number) {
        global $wpdb;
        $logger = Ventas_Logger::get_instance();
        
        $table = $wpdb->prefix . 'ventas_cotizaciones';
        
        // Log para debug
        $logger->log('Consultando cotizaciones...', 'debug');
        
        $sql = $wpdb->prepare("
            SELECT SQL_NO_CACHE 
                c.*, 
                u.display_name as cliente_nombre 
            FROM {$wpdb->prefix}ventas_cotizaciones c 
            LEFT JOIN {$wpdb->users} u ON c.cliente_id = u.ID
            ORDER BY c.id DESC
            LIMIT %d OFFSET %d
        ", $per_page, ($page_number - 1) * $per_page);
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Log resultados
        $logger->log('Resultados encontrados: ' . count($results), 'debug');
        $logger->log('SQL ejecutado: ' . $sql, 'debug');
        
        return $results;
    }

    public function display_rows() {
        $logger = Ventas_Logger::get_instance();
        $records = $this->items;
        $estados = ventas_get_estados_cotizacion();
    
        $logger->log('Registros a mostrar: ' . print_r($records, true), 'debug');
        
        if (empty($records)) {
            $logger->log('No hay registros para mostrar', 'warning');
            return;
        }
    
        foreach ($records as $record) {
            $logger->log('Procesando registro: ' . print_r($record, true), 'debug');
            
            // Verificar que tenemos todos los campos necesarios
            if (!isset($record['id']) || !isset($record['folio'])) {
                $logger->log('Registro inválido - faltan campos requeridos', 'error');
                continue;
            }
    
            echo '<tr>';
            // Checkbox column
            echo '<td><input type="checkbox" name="cotizacion[]" value="' . esc_attr($record['id']) . '" /></td>';
            // Folio column
            echo '<td>' . esc_html($record['folio']) . '</td>';
            // Cliente column
            echo '<td>' . esc_html(isset($record['cliente_nombre']) ? $record['cliente_nombre'] : 'Cliente no encontrado') . '</td>';
            // Fecha column
            echo '<td>' . date_i18n(get_option('date_format'), strtotime($record['fecha'])) . '</td>';
            // Total column
            echo '<td>' . ventas_format_price($record['total']) . '</td>';
            // Estado column
            if (isset($record['estado']) && isset($estados[$record['estado']])) {
                echo '<td><span class="estado-badge estado-' . esc_attr($record['estado']) . '">' . 
                     esc_html($estados[$record['estado']]) . '</span></td>';
            } else {
                echo '<td><span class="estado-badge estado-desconocido">Estado desconocido</span></td>';
            }
            // Acciones column
            echo '<td class="actions">';
            echo '<a href="' . admin_url('admin.php?page=ventas-cotizaciones&action=ver&id=' . $record['id']) . 
                 '" class="button button-small">Ver</a> ';
            echo '<a href="#" class="button button-small eliminar-cotizacion" data-id="' . 
                 esc_attr($record['id']) . '">Eliminar</a>';
            echo '</td>';
            echo '</tr>';
        }
    }

    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="cotizacion[]" value="%s" />',
            $item->id
        );
    }

    protected function column_folio($item) {
        $actions = [
            'view'   => sprintf(
                '<a href="%s">Ver</a>',
                admin_url('admin.php?page=ventas-ver-cotizacion&id=' . $item->id)
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'¿Estás seguro?\')">Eliminar</a>',
                wp_nonce_url(admin_url('admin.php?page=ventas-cotizaciones&action=delete&id=' . $item->id), 'delete_cotizacion_' . $item->id)
            )
        ];

        return sprintf(
            '%1$s %2$s',
            $item->folio,
            $this->row_actions($actions)
        );
    }

    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'cliente':
                return esc_html($item->cliente_nombre);
            case 'fecha':
                return date_i18n(get_option('date_format'), strtotime($item->fecha));
            case 'total':
                return wc_price($item->total);
            case 'estado':
                $estados = ventas_get_estados_cotizacion();
                return '<span class="estado-' . esc_attr($item->estado) . '">' . 
                    esc_html($estados[$item->estado]) . '</span>';
            case 'acciones':
                $acciones = '<a href="' . admin_url('admin.php?page=ventas-cotizaciones&action=pdf&id=' . $item->id) . 
                        '" class="button" target="_blank">PDF</a>';
                if ($item->estado === 'pendiente') {
                    $acciones .= ' <button type="button" class="button button-primary convertir-venta" ' .
                            'data-cotizacion-id="' . esc_attr($item->id) . '">Convertir a Venta</button>';
                }
                return $acciones;
            default:
                return print_r($item, true);
        }
    }
}