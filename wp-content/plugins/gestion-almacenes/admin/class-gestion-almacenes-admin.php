<?php

if (!defined('ABSPATH')) {
    exit;
}

class Gestion_Almacenes_Admin {

    private $transfer_controller;

    public function __construct() {
        add_action('admin_menu', array($this, 'registrar_menu_almacenes'));
        add_action('admin_post_gab_add_warehouse', array($this, 'procesar_agregar_almacen'));
        add_action('wp_ajax_get_warehouse_stock', array($this, 'ajax_get_warehouse_stock'));
        // Hook para agregar estilos CSS en admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        // Inicializar controlador de transferencias
        $this->transfer_controller = new Gestion_Almacenes_Transfer_Controller();
    }

    public function registrar_menu_almacenes() {
        // Menú Principal de Almacenes
        add_menu_page(
            __('Warehouses', 'gestion-almacenes'),
            __('Warehouses', 'gestion-almacenes'),
            'manage_options',
            'gab-warehouse-management',
            array($this, 'contenido_pagina_almacenes'),
            'dashicons-store',
            25
        );

        // Submenú para Gestión de Almacenes
        add_submenu_page(
            'gab-warehouse-management',
            __('Gestión de Almacenes', 'gestion-almacenes'),
            __('Gestión de Almacenes', 'gestion-almacenes'),
            'manage_options',
            'gab-warehouse-management',
            array($this, 'contenido_pagina_almacenes')
        );

        // Submenú para Agregar Nuevo Almacén
        add_submenu_page(
            'gab-warehouse-management',
            __('Agregar Nuevo Almacén', 'gestion-almacenes'),
            __('Agregar Nuevo', 'gestion-almacenes'),
            'manage_options',
            'gab-add-new-warehouse',
            'gab_mostrar_formulario_nuevo_almacen'
        );

        // NUEVOS SUBMENÚS PARA TRANSFERENCIAS
        
        // Separador visual (submenú deshabilitado)
        add_submenu_page(
            'gab-warehouse-management',
            '',
            '— ' . __('Transferencias', 'gestion-almacenes') . ' —',
            'manage_options',
            '#',
            ''
        );

        // Nueva Transferencia
        add_submenu_page(
            'gab-warehouse-management',
            __('Nueva Transferencia', 'gestion-almacenes'),
            __('Nueva Transferencia', 'gestion-almacenes'),
            'manage_options',
            'gab-new-transfer',
            array($this, 'mostrar_nueva_transferencia')
        );

        // Lista de Transferencias
        add_submenu_page(
            'gab-warehouse-management',
            __('Lista de Transferencias', 'gestion-almacenes'),
            __('Lista de Transferencias', 'gestion-almacenes'),
            'manage_options',
            'gab-transfer-list',
            array($this, 'mostrar_lista_transferencias')
        );

        // Gestión de Discrepancias
        add_submenu_page(
            'gab-warehouse-management',
            __('Gestión de Discrepancias', 'gestion-almacenes'),
            __('Discrepancias', 'gestion-almacenes'),
            'manage_options',
            'gab-discrepancies',
            array($this, 'mostrar_discrepancias')
        );

        // Almacén de Mermas
        add_submenu_page(
            'gab-warehouse-management',
            __('Almacén de Mermas', 'gestion-almacenes'),
            __('Mermas', 'gestion-almacenes'),
            'manage_options',
            'gab-waste-store',
            array($this, 'mostrar_almacen_mermas')
        );

        // Separador visual
        add_submenu_page(
            'gab-warehouse-management',
            '',
            '— ' . __('Reportes', 'gestion-almacenes') . ' —',
            'manage_options',
            '#',
            ''
        );
        
        // Submenú para Reporte de Stock
        add_submenu_page(
            'gab-warehouse-management', 
            __('Reporte de Stock', 'gestion-almacenes'),
            __('Reporte de Stock', 'gestion-almacenes'),
            'manage_options',
            'gab-stock-report', 
            array($this, 'mostrar_reporte_stock')
        );
        
        // Submenú para Configuración
        add_submenu_page(
            'gab-warehouse-management', 
            __('Configuración', 'gestion-almacenes'),
            __('Configuración', 'gestion-almacenes'),
            'manage_options',
            'gab-warehouse-settings', 
            array($this, 'mostrar_configuracion')
        );
    }

    public function enqueue_admin_scripts($hook) {
        // Solo en páginas relevantes del plugin
        if (strpos($hook, 'gab-stock-report') === false && 
            strpos($hook, 'gab-transfer-stock') === false && 
            strpos($hook, 'gab-warehouse-management') === false &&
            strpos($hook, 'gab-add-new-warehouse') === false &&
            strpos($hook, 'gab-warehouse-settings') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'gestion-almacenes-admin-css',
            GESTION_ALMACENES_PLUGIN_URL . 'admin/assets/css/gestion-almacenes-admin.css',
            array(),
            '1.0.0'
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'gestion-almacenes-admin-js',
            GESTION_ALMACENES_PLUGIN_URL . 'admin/assets/js/gestion-almacenes-admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localizar script con datos AJAX
        wp_localize_script('gestion-almacenes-admin-js', 'gestionAlmacenesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('get_warehouse_stock_nonce'),
            'messages' => array(
                'confirm_delete' => __('¿Está seguro de que desea eliminar este elemento?', 'gestion-almacenes'),
                'transfer_confirm' => __('¿Confirma la transferencia?', 'gestion-almacenes'),
                'error_connection' => __('Error de conexión', 'gestion-almacenes'),
                'stock_updated' => __('Stock actualizado correctamente', 'gestion-almacenes')
            )
        ));
    }


    public function contenido_pagina_almacenes() {
        // Cargar la vista desde el archivo separado
        require_once GESTION_ALMACENES_PLUGIN_DIR . 'admin/views/page-almacenes-list.php';
        
        // Llamar a la función de la vista
        gab_mostrar_listado_almacenes();
    }

    public function procesar_agregar_almacen() {
        if (!isset($_POST['gab_warehouse_nonce']) || !wp_verify_nonce($_POST['gab_warehouse_nonce'], 'gab_add_warehouse_nonce')) {
            wp_die(__('No tienes permiso para realizar esta acción.', 'gestion-almacenes'));
        }

        $data = [
            'name'     => sanitize_text_field($_POST['warehouse_name']),
            'address'  => sanitize_textarea_field($_POST['warehouse_address']),
            'comuna'   => sanitize_text_field($_POST['warehouse_comuna']),
            'ciudad'   => sanitize_text_field($_POST['warehouse_ciudad']),
            'region'   => sanitize_text_field($_POST['warehouse_region']),
            'pais'     => sanitize_text_field($_POST['warehouse_pais']),
            'email'    => sanitize_email($_POST['warehouse_email']),
            'telefono' => sanitize_text_field($_POST['warehouse_telefono']),
            'slug'     => sanitize_title($_POST['warehouse_name']),
        ];

        if (empty($data['name'])) {
            wp_redirect(admin_url('admin.php?page=gab-add-new-warehouse&status=error&message=nombre_vacio'));
            exit;
        }

        global $gestion_almacenes_db;
        $insert_result = $gestion_almacenes_db->insertar_almacen($data);

        if ($insert_result) {
            wp_redirect(admin_url('admin.php?page=gab-warehouse-management&status=success'));
        } else {
            wp_redirect(admin_url('admin.php?page=gab-warehouse-management&status=error&message=error_db'));
        }

        exit;
    }

    

    public function mostrar_reporte_stock() {
        global $gestion_almacenes_db;
        
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Reporte de Stock por Almacén', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Visualiza el estado del inventario en todos los almacenes.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        // Obtener datos
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        $productos_stock = $gestion_almacenes_db->get_all_products_warehouse_stock();
        $threshold = get_option('gab_low_stock_threshold', 5);
        
        // Obtener filtros
        $warehouse_filters = isset($_GET['warehouse_filter']) ? (array) $_GET['warehouse_filter'] : array();
        $status_filters = isset($_GET['status_filter']) ? (array) $_GET['status_filter'] : array();
        
        // Limpiar filtros
        $warehouse_filters = array_map('intval', $warehouse_filters);
        $status_filters = array_map('sanitize_text_field', $status_filters);

        // Determinar qué almacenes mostrar en las columnas
        $almacenes_a_mostrar = array();
        if (!empty($warehouse_filters)) {
            // Si hay filtros seleccionados, solo mostrar esos almacenes
            foreach ($almacenes as $almacen) {
                if (in_array($almacen->id, $warehouse_filters)) {
                    $almacenes_a_mostrar[] = $almacen;
                }
            }
        } else {
            // Si no hay filtros, mostrar todos los almacenes
            $almacenes_a_mostrar = $almacenes;
        }

        // Procesar datos por producto
        $productos_agrupados = $this->agrupar_productos_por_almacen($productos_stock, $almacenes);
        
        // Aplicar filtros
        $productos_filtrados = $this->aplicar_filtros_reportes($productos_agrupados, $warehouse_filters, $status_filters, $threshold);

        // Estadísticas generales (usando almacenes filtrados)
        $this->mostrar_estadisticas_stock_agrupadas($productos_filtrados, $almacenes_a_mostrar, $threshold);

        // Formulario de filtros con checkboxes
        echo '<div class="gab-form-section">';
        echo '<form method="get" action="" id="gab-report-filters">';
        echo '<input type="hidden" name="page" value="gab-stock-report">';
        
        echo '<div class="gab-form-row">';
        
        // Filtros por almacén (checkboxes)
        echo '<div class="gab-form-group">';
        echo '<label><strong>' . esc_html__('Filtrar por Almacenes', 'gestion-almacenes') . '</strong></label>';
        echo '<div class="filter-checkbox-container">';
        
        if ($almacenes) {
            foreach ($almacenes as $almacen) {
                $checked = in_array($almacen->id, $warehouse_filters) ? 'checked' : '';
                echo '<label>';
                echo '<input type="checkbox" name="warehouse_filter[]" value="' . esc_attr($almacen->id) . '" ' . $checked . '>';
                echo esc_html($almacen->name) . ' (' . esc_html($almacen->ciudad) . ')';
                echo '</label>';
            }
        }
        
        echo '</div>';
        echo '<small class="description">' . esc_html__('Selecciona almacenes específicos para mostrar solo esas columnas.', 'gestion-almacenes') . '</small>';
        echo '</div>';

        // Filtros por estado (checkboxes)
        echo '<div class="gab-form-group">';
        echo '<label><strong>' . esc_html__('Filtrar por Estado de Stock', 'gestion-almacenes') . '</strong></label>';
        echo '<div class="filter-checkbox-container">';
        
        $estados = array(
            'high' => __('Stock alto', 'gestion-almacenes'),
            'medium' => __('Stock medio', 'gestion-almacenes'),
            'low' => __('Stock bajo', 'gestion-almacenes'),
            'out' => __('Sin stock', 'gestion-almacenes')
        );
        
        foreach ($estados as $key => $label) {
            $checked = in_array($key, $status_filters) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="status_filter[]" value="' . esc_attr($key) . '" ' . $checked . '>';
            echo '<span class="gab-badge stock-' . esc_attr($key) . '" style="margin-right: 5px;">' . esc_html($label) . '</span>';
            echo '</label>';
        }
        
        echo '</div>';
        echo '<small class="description">' . esc_html__('Filtra productos por su estado de stock.', 'gestion-almacenes') . '</small>';
        echo '</div>';

        echo '</div>'; // fin gab-form-row

        // Botones de acción
        echo '<div class="gab-form-row">';
        echo '<div class="gab-form-group">';
        echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Aplicar Filtros', 'gestion-almacenes') . '">';
        echo ' <a href="' . esc_url(admin_url('admin.php?page=gab-stock-report')) . '" class="button button-secondary">' . esc_html__('Limpiar Filtros', 'gestion-almacenes') . '</a>';
        echo ' <button type="button" id="export_report" class="button button-secondary">' . esc_html__('Exportar CSV', 'gestion-almacenes') . '</button>';
        echo '</div>';
        echo '</div>';

        echo '</form>';
        echo '</div>'; // fin gab-form-section

        // Información sobre filtros activos
        if (!empty($warehouse_filters) || !empty($status_filters)) {
            echo '<div class="gab-message info" style="margin-bottom: 20px;">';
            echo '<p><strong>' . esc_html__('Filtros activos:', 'gestion-almacenes') . '</strong> ';
            
            $filtros_activos = array();
            
            if (!empty($warehouse_filters)) {
                $nombres_almacenes = array();
                foreach ($almacenes_a_mostrar as $almacen) {
                    $nombres_almacenes[] = $almacen->name;
                }
                $filtros_activos[] = sprintf(
                    esc_html__('Almacenes: %s', 'gestion-almacenes'),
                    implode(', ', $nombres_almacenes)
                );
            }
            
            if (!empty($status_filters)) {
                $nombres_estados = array();
                foreach ($status_filters as $status) {
                    if (isset($estados[$status])) {
                        $nombres_estados[] = $estados[$status];
                    }
                }
                $filtros_activos[] = sprintf(
                    esc_html__('Estados: %s', 'gestion-almacenes'),
                    implode(', ', $nombres_estados)
                );
            }
            
            echo implode(' | ', $filtros_activos);
            echo '</p>';
            echo '</div>';
        }

        // Tabla de reporte con columnas filtradas por almacén
        if (!empty($productos_filtrados)) {
            echo '<div class="gab-table-container">';
            echo '<table class="gab-table wp-list-table widefat fixed striped" id="stock-report-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="min-width: 200px;">' . esc_html__('Producto', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 80px;">' . esc_html__('SKU', 'gestion-almacenes') . '</th>';
            
            // Columnas dinámicas SOLO por almacenes seleccionados
            if ($almacenes_a_mostrar) {
                foreach ($almacenes_a_mostrar as $almacen) {
                    echo '<th style="width: 100px; text-align: center;">';
                    echo esc_html($almacen->name);
                    echo '<br><small style="font-weight: normal;">' . esc_html($almacen->ciudad) . '</small>';
                    echo '</th>';
                }
            }
            
            echo '<th style="width: 80px;">' . esc_html__('Total', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 100px;">' . esc_html__('Estado', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 120px;">' . esc_html__('Acciones', 'gestion-almacenes') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($productos_filtrados as $product_id => $producto_data) {
                $product = wc_get_product($product_id);
                if (!$product) continue;

                echo '<tr>';
                
                // Producto
                echo '<td>';
                echo '<strong>' . esc_html($product->get_name()) . '</strong>';
                if ($product->get_type() === 'variation') {
                    echo '<br><small>' . esc_html($product->get_formatted_variation_attributes(true)) . '</small>';
                }
                echo '</td>';

                // SKU
                echo '<td>' . esc_html($product->get_sku() ?: '-') . '</td>';

                // Columnas de stock SOLO por almacenes seleccionados
                $total_stock_filtrado = 0;
                if ($almacenes_a_mostrar) {
                    foreach ($almacenes_a_mostrar as $almacen) {
                        $stock = isset($producto_data['almacenes'][$almacen->id]) ? $producto_data['almacenes'][$almacen->id] : 0;
                        $total_stock_filtrado += $stock;
                        
                        echo '<td style="text-align: center;">';
                        if ($stock > 0) {
                            $status_class = $this->obtener_clase_estado_stock($stock, $threshold);
                            echo '<span class="' . esc_attr($status_class) . '">' . esc_html($stock) . '</span>';
                        } else {
                            echo '<span style="color: #999;">0</span>';
                        }
                        echo '</td>';
                    }
                }

                // Total (solo de almacenes mostrados)
                echo '<td style="text-align: center;">';
                echo '<strong>' . esc_html($total_stock_filtrado) . '</strong>';
                echo '</td>';

                // Estado general (basado en almacenes mostrados)
                echo '<td style="text-align: center;">';
                $producto_data_filtrado = array(
                    'almacenes' => array(),
                    'total_stock' => $total_stock_filtrado
                );
                
                // Solo incluir almacenes mostrados para el cálculo del estado
                foreach ($almacenes_a_mostrar as $almacen) {
                    if (isset($producto_data['almacenes'][$almacen->id])) {
                        $producto_data_filtrado['almacenes'][$almacen->id] = $producto_data['almacenes'][$almacen->id];
                    }
                }
                
                echo $this->obtener_badge_estado_general($producto_data_filtrado, $threshold);
                echo '</td>';

                // Acciones
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('post.php?post=' . $product_id . '&action=edit')) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Editar producto', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-edit"></span>';
                echo '</a> ';
                
                echo '<a href="' . esc_url(add_query_arg(array(
                    'page' => 'gab-transfer-stock',
                    'product_id' => $product_id
                ), admin_url('admin.php'))) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Transferir stock', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-randomize"></span>';
                echo '</a>';
                echo '</td>';

                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';

            // Resumen de la tabla
            $total_items = count($productos_filtrados);
            $total_almacenes_mostrados = count($almacenes_a_mostrar);
            
            echo '<p class="description">';
            echo sprintf(
                esc_html__('Mostrando %d producto(s) en %d almacén(es).', 'gestion-almacenes'), 
                $total_items, 
                $total_almacenes_mostrados
            );
            echo '</p>';

        } else {
            echo '<div class="gab-message info">';
            echo '<h3>' . esc_html__('No se encontraron productos', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('No hay productos que coincidan con los filtros seleccionados.', 'gestion-almacenes') . '</p>';
            echo '</div>';
        }

        echo '</div>'; // fin wrap
    }

    /**
     * Agrupar productos por almacén
     */
    private function agrupar_productos_por_almacen($productos_stock, $almacenes) {
        $productos_agrupados = array();
        
        if ($productos_stock) {
            foreach ($productos_stock as $item) {
                $product_id = $item->product_id;
                
                if (!isset($productos_agrupados[$product_id])) {
                    $productos_agrupados[$product_id] = array(
                        'almacenes' => array(),
                        'total_stock' => 0
                    );
                }
                
                $productos_agrupados[$product_id]['almacenes'][$item->warehouse_id] = intval($item->stock);
                $productos_agrupados[$product_id]['total_stock'] += intval($item->stock);
            }
        }
        
        return $productos_agrupados;
    }

    /**
     * Aplicar filtros a los productos agrupados
     */
    private function aplicar_filtros_reportes($productos_agrupados, $warehouse_filters, $status_filters, $threshold) {
        $productos_filtrados = array();
        
        foreach ($productos_agrupados as $product_id => $producto_data) {
            $incluir_producto = true;
            
            // Filtro por almacenes
            if (!empty($warehouse_filters)) {
                $tiene_stock_en_almacenes_filtrados = false;
                foreach ($warehouse_filters as $warehouse_id) {
                    if (isset($producto_data['almacenes'][$warehouse_id]) && $producto_data['almacenes'][$warehouse_id] > 0) {
                        $tiene_stock_en_almacenes_filtrados = true;
                        break;
                    }
                }
                if (!$tiene_stock_en_almacenes_filtrados) {
                    $incluir_producto = false;
                }
            }
            
            // Filtro por estado
            if (!empty($status_filters) && $incluir_producto) {
                $cumple_estado = false;
                
                foreach ($status_filters as $status) {
                    $estado_producto = $this->determinar_estado_producto($producto_data, $threshold);
                    if (in_array($status, $estado_producto)) {
                        $cumple_estado = true;
                        break;
                    }
                }
                
                if (!$cumple_estado) {
                    $incluir_producto = false;
                }
            }
            
            if ($incluir_producto) {
                $productos_filtrados[$product_id] = $producto_data;
            }
        }
        
        return $productos_filtrados;
    }

    /**
     * Determinar estados de un producto
     */
    private function determinar_estado_producto($producto_data, $threshold) {
        $estados = array();
        
        foreach ($producto_data['almacenes'] as $warehouse_id => $stock) {
            if ($stock == 0) {
                $estados[] = 'out';
            } elseif ($stock <= $threshold) {
                $estados[] = 'low';
            } elseif ($stock <= ($threshold * 2)) {
                $estados[] = 'medium';
            } else {
                $estados[] = 'high';
            }
        }
        
        return array_unique($estados);
    }

    /**
     * Obtener clase CSS para estado de stock
     */
    private function obtener_clase_estado_stock($stock, $threshold) {
        if ($stock == 0) {
            return 'gab-badge stock-out';
        } elseif ($stock <= $threshold) {
            return 'gab-badge stock-low';
        } elseif ($stock <= ($threshold * 2)) {
            return 'gab-badge stock-medium';
        } else {
            return 'gab-badge stock-high';
        }
    }

    /**
     * Obtener badge de estado general
     */
    private function obtener_badge_estado_general($producto_data, $threshold) {
        $total_stock = $producto_data['total_stock'];
        
        if ($total_stock == 0) {
            return '<span class="gab-badge stock-out">' . esc_html__('Sin stock', 'gestion-almacenes') . '</span>';
        }
        
        $tiene_stock_bajo = false;
        $tiene_stock_alto = false;
        
        foreach ($producto_data['almacenes'] as $stock) {
            if ($stock > 0 && $stock <= $threshold) {
                $tiene_stock_bajo = true;
            } elseif ($stock > ($threshold * 2)) {
                $tiene_stock_alto = true;
            }
        }
        
        if ($tiene_stock_bajo) {
            return '<span class="gab-badge stock-low">' . esc_html__('Stock bajo', 'gestion-almacenes') . '</span>';
        } elseif ($tiene_stock_alto) {
            return '<span class="gab-badge stock-high">' . esc_html__('Stock alto', 'gestion-almacenes') . '</span>';
        } else {
            return '<span class="gab-badge stock-medium">' . esc_html__('Stock medio', 'gestion-almacenes') . '</span>';
        }
    }

    /**
     * Mostrar estadísticas de stock agrupadas
     */
    private function mostrar_estadisticas_stock_agrupadas($productos_filtrados, $almacenes_mostrados, $threshold) {
        $total_productos = count($productos_filtrados);
        $total_stock = 0;
        $productos_sin_stock = 0;
        $productos_stock_bajo = 0;
        $total_almacenes_mostrados = count($almacenes_mostrados);

        foreach ($productos_filtrados as $producto_data) {
            // Calcular solo el stock de los almacenes que se están mostrando
            $stock_producto_filtrado = 0;
            $tiene_stock_bajo_en_mostrados = false;
            
            foreach ($almacenes_mostrados as $almacen) {
                if (isset($producto_data['almacenes'][$almacen->id])) {
                    $stock_almacen = $producto_data['almacenes'][$almacen->id];
                    $stock_producto_filtrado += $stock_almacen;
                    
                    if ($stock_almacen > 0 && $stock_almacen <= $threshold) {
                        $tiene_stock_bajo_en_mostrados = true;
                    }
                }
            }
            
            $total_stock += $stock_producto_filtrado;
            
            if ($stock_producto_filtrado == 0) {
                $productos_sin_stock++;
            } elseif ($tiene_stock_bajo_en_mostrados) {
                $productos_stock_bajo++;
            }
        }

        echo '<div class="gab-stats-grid">';
        
        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_almacenes_mostrados . '</span>';
        echo '<span class="stat-label">' . esc_html__('Almacenes Mostrados', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_productos . '</span>';
        echo '<span class="stat-label">' . esc_html__('Productos', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number">' . $total_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Stock Total', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #d63638;">' . $productos_stock_bajo . '</span>';
        echo '<span class="stat-label">' . esc_html__('Stock Bajo', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '<div class="gab-stat-card">';
        echo '<span class="stat-number" style="color: #999;">' . $productos_sin_stock . '</span>';
        echo '<span class="stat-label">' . esc_html__('Sin Stock', 'gestion-almacenes') . '</span>';
        echo '</div>';

        echo '</div>';
    }

    public function mostrar_transferir_stock() {
        global $gestion_almacenes_db;

        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Transferir Stock entre Almacenes', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Transfiere productos de un almacén a otro de forma segura.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        // Procesar transferencia si se envió el formulario
        if (isset($_POST['transfer_stock']) && wp_verify_nonce($_POST['transfer_nonce'], 'transfer_stock_nonce')) {
            $product_id = intval($_POST['product_id']);
            $from_warehouse = intval($_POST['from_warehouse']);
            $to_warehouse = intval($_POST['to_warehouse']);
            $quantity = intval($_POST['quantity']);

            if ($product_id && $from_warehouse && $to_warehouse && $quantity > 0 && $from_warehouse !== $to_warehouse) {
                $result = $gestion_almacenes_db->transfer_stock_between_warehouses($product_id, $from_warehouse, $to_warehouse, $quantity);

                if ($result) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p>' . esc_html__('Transferencia realizada con éxito.', 'gestion-almacenes') . '</p>';
                    echo '</div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p>' . esc_html__('Error al realizar la transferencia. Verifica que haya suficiente stock.', 'gestion-almacenes') . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>' . esc_html__('Datos inválidos. Verifica todos los campos.', 'gestion-almacenes') . '</p>';
                echo '</div>';
            }
        }

        // Obtener datos necesarios
        $almacenes = $gestion_almacenes_db->obtener_almacenes();
        $productos_wc = $this->obtener_productos_woocommerce();

        if (!$almacenes || count($almacenes) < 2) {
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('Insuficientes almacenes', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('Necesitas al menos 2 almacenes para realizar transferencias.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-add-new-warehouse')) . '" class="button button-primary">';
            echo esc_html__('Agregar Almacén', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }

        if (!$productos_wc || count($productos_wc) === 0) {
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('No hay productos', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('No se encontraron productos de WooCommerce con stock en almacenes.', 'gestion-almacenes') . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Formulario de transferencia
        echo '<div class="gab-form-section">';
        echo '<form method="post" action="" id="gab-transfer-form">';
        wp_nonce_field('transfer_stock_nonce', 'transfer_nonce');

        echo '<div class="gab-form-row">';
        
        // Selector de producto
        echo '<div class="gab-form-group">';
        echo '<label for="product_id">' . esc_html__('Seleccionar Producto', 'gestion-almacenes') . '</label>';
        echo '<select name="product_id" id="product_id" required>';
        echo '<option value="">' . esc_html__('Selecciona un producto...', 'gestion-almacenes') . '</option>';
        
        foreach ($productos_wc as $producto) {
            echo '<option value="' . esc_attr($producto->ID) . '">';
            echo esc_html($producto->post_title) . ' (ID: ' . esc_html($producto->ID) . ')';
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        // Almacén origen
        echo '<div class="gab-form-group">';
        echo '<label for="from_warehouse">' . esc_html__('Almacén Origen', 'gestion-almacenes') . '</label>';
        echo '<select name="from_warehouse" id="from_warehouse" required>';
        echo '<option value="">' . esc_html__('Selecciona almacén origen...', 'gestion-almacenes') . '</option>';
        
        foreach ($almacenes as $almacen) {
            echo '<option value="' . esc_attr($almacen->id) . '">';
            echo esc_html($almacen->name) . ' - ' . esc_html($almacen->ciudad);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        echo '</div>'; // fin gab-form-row

        echo '<div class="gab-form-row">';

        // Almacén destino
        echo '<div class="gab-form-group">';
        echo '<label for="to_warehouse">' . esc_html__('Almacén Destino', 'gestion-almacenes') . '</label>';
        echo '<select name="to_warehouse" id="to_warehouse" required>';
        echo '<option value="">' . esc_html__('Selecciona almacén destino...', 'gestion-almacenes') . '</option>';
        
        foreach ($almacenes as $almacen) {
            echo '<option value="' . esc_attr($almacen->id) . '">';
            echo esc_html($almacen->name) . ' - ' . esc_html($almacen->ciudad);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        // Cantidad
        echo '<div class="gab-form-group">';
        echo '<label for="quantity">' . esc_html__('Cantidad a Transferir', 'gestion-almacenes') . '</label>';
        echo '<input type="number" name="quantity" id="quantity" min="1" required>';
        echo '</div>';

        echo '</div>'; // fin gab-form-row

        // Información de stock
        echo '<div id="stock_info" class="gab-stock-info">';
        echo esc_html__('Selecciona un producto y almacén origen para ver el stock disponible.', 'gestion-almacenes');
        echo '</div>';

        // Botón de transferencia
        echo '<div class="gab-form-row">';
        echo '<div class="gab-form-group">';
        echo '<input type="submit" name="transfer_stock" class="button button-primary gab-button-primary" ';
        echo 'value="' . esc_attr__('Realizar Transferencia', 'gestion-almacenes') . '">';
        echo '</div>';
        echo '</div>';

        echo '</form>';
        echo '</div>'; // fin gab-form-section

        // Historial reciente de transferencias (opcional)
        echo '<div class="gab-section-header" style="margin-top: 40px;">';
        echo '<h2>' . esc_html__('Transferencias Recientes', 'gestion-almacenes') . '</h2>';
        echo '</div>';

        $this->mostrar_transferencias_recientes();

        echo '</div>'; // fin wrap
    }

    /**
     * Función auxiliar para obtener productos de WooCommerce
     */
    private function obtener_productos_woocommerce() {
        if (!class_exists('WooCommerce')) {
            return array();
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_manage_stock',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Mostrar historial de transferencias recientes
     */
    private function mostrar_transferencias_recientes() {
        echo '<div class="gab-message info">';
        echo '<p>' . esc_html__('El historial de transferencias esta en desarrollo aún', 'gestion-almacenes') . '</p>';
        echo '</div>';
    }

    /**
    * Handler AJAX para obtener stock de almacén
    */
    public function ajax_get_warehouse_stock() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'get_warehouse_stock_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $warehouse_id = intval($_POST['warehouse_id']);
        
        if (!$product_id || !$warehouse_id) {
            wp_send_json_error('Invalid parameters');
            return;
        }
        
        global $gestion_almacenes_db;
        $stock = $gestion_almacenes_db->get_product_stock_in_warehouse($product_id, $warehouse_id);
        
        wp_send_json_success(array('stock' => $stock));
    }


    /**
     * Mostrar página de nueva transferencia
     */
    public function mostrar_nueva_transferencia() {
        global $gestion_almacenes_db;
        
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Nueva Transferencia de Stock', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Crear una nueva transferencia entre almacenes.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        $almacenes = $gestion_almacenes_db->obtener_almacenes();

        if (!$almacenes || count($almacenes) < 2) {
            echo '<div class="gab-message warning">';
            echo '<h3>' . esc_html__('Almacenes insuficientes', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('Necesitas al menos 2 almacenes para crear transferencias.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-add-new-warehouse')) . '" class="button button-primary">';
            echo esc_html__('Agregar Almacén', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
            echo '</div>';
            return;
        }

        ?>
        <form id="gab-transfer-form">
            <?php wp_nonce_field('gab_transfer_nonce', 'nonce'); ?>
            
            <div class="gab-form-section">
                <h3><?php esc_html_e('Información de la Transferencia', 'gestion-almacenes'); ?></h3>
                
                <div class="gab-form-row">
                    <div class="gab-form-group">
                        <label for="source_warehouse"><?php esc_html_e('Almacén Origen', 'gestion-almacenes'); ?></label>
                        <select name="source_warehouse" id="source_warehouse" required>
                            <option value=""><?php esc_html_e('Seleccionar almacén origen...', 'gestion-almacenes'); ?></option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo esc_attr($almacen->id); ?>">
                                    <?php echo esc_html($almacen->name . ' - ' . $almacen->ciudad); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="gab-form-group">
                        <label for="target_warehouse"><?php esc_html_e('Almacén Destino', 'gestion-almacenes'); ?></label>
                        <select name="target_warehouse" id="target_warehouse" required>
                            <option value=""><?php esc_html_e('Seleccionar almacén destino...', 'gestion-almacenes'); ?></option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo esc_attr($almacen->id); ?>">
                                    <?php echo esc_html($almacen->name . ' - ' . $almacen->ciudad); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="gab-form-section">
                <h3><?php esc_html_e('Productos a Transferir', 'gestion-almacenes'); ?></h3>
                
                <table class="wp-list-table widefat fixed striped" id="products-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;"><?php esc_html_e('SKU', 'gestion-almacenes'); ?></th>
                            <th><?php esc_html_e('Producto', 'gestion-almacenes'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Stock Disponible', 'gestion-almacenes'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Cantidad', 'gestion-almacenes'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Acciones', 'gestion-almacenes'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <tr id="no-products-row">
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <div style="color: #666; font-style: italic; margin-bottom: 15px;">
                                    <?php esc_html_e('No hay productos agregados', 'gestion-almacenes'); ?>
                                </div>
                                <button type="button" id="add-product-btn" class="button button-primary">
                                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php esc_html_e('Añadir producto', 'gestion-almacenes'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr id="add-product-row" style="display: none;">
                            <td colspan="5" style="text-align: center; padding: 15px; background-color: #f0f6fc;">
                                <button type="button" id="add-product-btn-table" class="button button-secondary">
                                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php esc_html_e('Añadir producto', 'gestion-almacenes'); ?>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="gab-form-section">
                <h3><?php esc_html_e('Notas Adicionales', 'gestion-almacenes'); ?></h3>
                <textarea name="notes" id="notes" rows="4" style="width: 100%;" 
                        placeholder="<?php esc_attr_e('Agregar notas o comentarios sobre esta transferencia...', 'gestion-almacenes'); ?>"></textarea>
            </div>

            <div class="gab-form-row">
                <div class="gab-form-group">
                    <input type="submit" class="button button-primary gab-button-primary" 
                        value="<?php esc_attr_e('Crear Transferencia', 'gestion-almacenes'); ?>" id="submit-transfer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gab-transfer-list')); ?>" 
                    class="button button-secondary"><?php esc_html_e('Cancelar', 'gestion-almacenes'); ?></a>
                </div>
            </div>
        </form>

        <!-- Modal para búsqueda de productos -->
        <div id="product-search-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php esc_html_e('Buscar Productos', 'gestion-almacenes'); ?></h2>
                <input type="text" id="product-search" placeholder="<?php esc_attr_e('Escriba para buscar productos...', 'gestion-almacenes'); ?>">
                <div id="search-results"></div>
            </div>
        </div>
        
        <style>
        /* Modal exacto del plugin de referencia */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            position: relative;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 10px;
        }

        .close:hover {
            color: black;
        }

        .modal-content h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
            padding-right: 40px;
        }

        #product-search {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        #product-search:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
        }

        #search-results {
            max-height: calc(80vh - 150px);
            overflow-y: auto;
            margin-top: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background: white;
        }

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
            font-size: 14px;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }

        .searching, .no-results {
            padding: 10px;
            color: #666;
            font-style: italic;
            text-align: center;
        }

        .error {
            color: #dc3545;
            padding: 10px;
            text-align: center;
        }

        #search-results::-webkit-scrollbar {
            width: 8px;
        }

        #search-results::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #search-results::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        #search-results::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 2% auto;
                padding: 15px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Script cargado'); // Debug
            
            let productCount = 0;
            const MAX_PRODUCTS = 60;
            
            // Debug - verificar elementos
            console.log('Modal element:', $('#product-search-modal').length);
            console.log('Add product buttons:', $('#add-product-btn, #add-product-btn-table').length);
            
            // Función para abrir el modal
            function openModal() {
                console.log('Abriendo modal'); // Debug
                $('#product-search-modal').show();
                $('#product-search').val('').focus();
                $('#search-results').empty();
            }
            
            // Función para cerrar el modal
            function closeModal() {
                console.log('Cerrando modal'); // Debug
                $('#product-search-modal').hide();
                $('#product-search').val('');
                $('#search-results').empty();
            }
            
            // Event handlers para abrir modal
            $(document).on('click', '#add-product-btn, #add-product-btn-table', function(e) {
                e.preventDefault();
                console.log('Click en agregar producto'); // Debug
                
                const sourceWarehouse = $('#source_warehouse').val();
                if (!sourceWarehouse) {
                    alert('<?php esc_html_e('Primero selecciona el almacén origen', 'gestion-almacenes'); ?>');
                    $('#source_warehouse').focus();
                    return;
                }
                openModal();
            });

            // Cerrar modal con X
            $(document).on('click', '.close', function() {
                closeModal();
            });
            
            // Cerrar modal al hacer clic fuera
            $(document).on('click', '#product-search-modal', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Prevenir que el modal se cierre al hacer clic dentro del contenido
            $(document).on('click', '.modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Búsqueda de productos
            let searchTimeout;
            $(document).on('input', '#product-search', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                if (query.length < 2) {
                    $('#search-results').empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    $('#search-results').html('<div class="searching"><?php esc_html_e('Buscando...', 'gestion-almacenes'); ?></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gab_search_products_transfer',
                            term: query,
                            nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('Respuesta búsqueda:', response); // Debug
                            $('#search-results').empty();
                            
                            if (response.success && response.data && response.data.products) {
                                if (response.data.products.length > 0) {
                                    response.data.products.forEach(function(product) {
                                        // Verificar si ya está agregado
                                        const isAdded = $(`tr[data-product-id="${product.id}"]`).length > 0;
                                        if (!isAdded) {
                                            $('#search-results').append(`
                                                <div class="search-result-item" data-product-id="${product.id}" data-product-name="${product.name}" data-product-sku="${product.sku || ''}">
                                                    ${product.sku ? `[${product.sku}] ` : ''}${product.name}
                                                </div>
                                            `);
                                        }
                                    });
                                    
                                    if ($('#search-results').children().length === 0) {
                                        $('#search-results').append('<div class="no-results"><?php esc_html_e('Todos los productos coincidentes ya están en la lista.', 'gestion-almacenes'); ?></div>');
                                    }
                                } else {
                                    $('#search-results').append('<div class="no-results"><?php esc_html_e('No se encontraron productos', 'gestion-almacenes'); ?></div>');
                                }
                            } else {
                                $('#search-results').append('<div class="error">' + (response.data ? response.data.message : '<?php esc_html_e('Error al buscar productos', 'gestion-almacenes'); ?>') + '</div>');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.error('Error en búsqueda:', textStatus, errorThrown);
                            $('#search-results').html('<div class="error"><?php esc_html_e('Error al buscar productos', 'gestion-almacenes'); ?></div>');
                        }
                    });
                }, 500);
            });

            // Seleccionar producto del modal
            $(document).on('click', '.search-result-item', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).data('product-name');
                const productSku = $(this).data('product-sku');
                const sourceWarehouse = $('#source_warehouse').val();
                
                addProductToTransfer(productId, productName, productSku, sourceWarehouse);
                closeModal();
            });
            
            // Función para agregar producto a la tabla
            function addProductToTransfer(productId, productName, productSku, sourceWarehouse) {
                if (productCount >= MAX_PRODUCTS) {
                    alert('<?php esc_html_e('Número máximo de productos alcanzado', 'gestion-almacenes'); ?>');
                    return;
                }

                // Verificar si el producto ya está en la lista
                if ($(`tr[data-product-id="${productId}"]`).length > 0) {
                    alert('<?php esc_html_e('Este producto ya está en la lista.', 'gestion-almacenes'); ?>');
                    return;
                }

                // Ocultar mensaje de "no hay productos"
                $('#no-products-row').hide();
                $('#add-product-row').show();

                // Agregar fila con indicador de carga
                const row = `
                    <tr data-product-id="${productId}">
                        <td>${productSku || ''}</td>
                        <td>${productName}</td>
                        <td class="available-stock"><span class="loading"><?php esc_html_e('Cargando...', 'gestion-almacenes'); ?></span></td>
                        <td>
                            <input type="number" name="products[${productCount}][quantity]" min="1" required 
                                class="demanded-stock-input" value="1" disabled style="width: 80px; text-align: center;">
                            <input type="hidden" name="products[${productCount}][product_id]" value="${productId}">
                        </td>
                        <td>
                            <button type="button" class="button remove-product">
                                <?php esc_html_e('Eliminar', 'gestion-almacenes'); ?>
                            </button>
                        </td>
                    </tr>
                `;
                
                $('#add-product-row').before(row);
                
                // Obtener stock disponible
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_get_warehouse_stock_transfer',
                        product_id: productId,
                        warehouse_id: sourceWarehouse,
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        const row = $(`tr[data-product-id="${productId}"]`);
                        if (response.success) {
                            const stock = parseInt(response.data.stock);
                            if (stock <= 0) {
                                row.remove();
                                alert('<?php esc_html_e('No hay stock disponible para este producto', 'gestion-almacenes'); ?>');
                                
                                // Verificar si no hay productos
                                if ($('#products-tbody tr[data-product-id]').length === 0) {
                                    $('#no-products-row').show();
                                    $('#add-product-row').hide();
                                }
                            } else {
                                row.find('.available-stock').html(`<strong style="color: #0073aa;">${stock}</strong>`);
                                const input = row.find('.demanded-stock-input');
                                input.prop('disabled', false).attr('max', stock);
                            }
                        } else {
                            row.find('.available-stock').html('<span class="error">Error</span>');
                        }
                        updateSubmitButton();
                    },
                    error: function() {
                        const row = $(`tr[data-product-id="${productId}"]`);
                        row.find('.available-stock').html('<span class="error">Error</span>');
                        updateSubmitButton();
                    }
                });

                productCount++;
            }

            // Eliminar producto
            $(document).on('click', '.remove-product', function() {
                $(this).closest('tr').remove();
                
                if ($('#products-tbody tr[data-product-id]').length === 0) {
                    $('#no-products-row').show();
                    $('#add-product-row').hide();
                }
                updateSubmitButton();
            });

            // Actualizar estado del botón submit
            function updateSubmitButton() {
                const $submitBtn = $('#submit-transfer');
                const hasProducts = $('#products-tbody tr[data-product-id]').length > 0;
                
                if (hasProducts) {
                    $submitBtn.prop('disabled', false).removeClass('button-disabled');
                } else {
                    $submitBtn.prop('disabled', true).addClass('button-disabled');
                }
            }

            // Validaciones y envío de formulario
            $('#target_warehouse').on('change', function() {
                const sourceWarehouse = $('#source_warehouse').val();
                const targetWarehouse = $(this).val();
                
                if (sourceWarehouse && targetWarehouse && sourceWarehouse === targetWarehouse) {
                    alert('<?php esc_html_e('Los almacenes origen y destino deben ser diferentes', 'gestion-almacenes'); ?>');
                    $(this).val('');
                }
            });

            $(document).on('change', '.demanded-stock-input', function() {
                const input = $(this);
                const max = parseInt(input.attr('max'), 10);
                const value = parseInt(input.val(), 10);
                
                if (value > max) {
                    alert('<?php esc_html_e('La cantidad no puede exceder el stock disponible', 'gestion-almacenes'); ?>');
                    input.val(max);
                } else if (value < 1) {
                    alert('<?php esc_html_e('La cantidad debe ser al menos 1', 'gestion-almacenes'); ?>');
                    input.val(1);
                }
            });

            $('#gab-transfer-form').on('submit', function(e) {
                e.preventDefault();
                
                const sourceWarehouse = $('#source_warehouse').val();
                const targetWarehouse = $('#target_warehouse').val();
                
                if (!sourceWarehouse || !targetWarehouse) {
                    alert('<?php esc_html_e('Debes seleccionar ambos almacenes', 'gestion-almacenes'); ?>');
                    return;
                }
                
                if ($('#products-tbody tr[data-product-id]').length === 0) {
                    alert('<?php esc_html_e('Debes agregar al menos un producto', 'gestion-almacenes'); ?>');
                    return;
                }

                const products = [];
                $('.demanded-stock-input').each(function() {
                    const row = $(this).closest('tr');
                    products.push({
                        product_id: row.data('product-id'),
                        quantity: parseInt($(this).val(), 10)
                    });
                });

                const $submitBtn = $('#submit-transfer');
                const originalText = $submitBtn.val();
                $submitBtn.prop('disabled', true).val('<?php esc_attr_e('Creando...', 'gestion-almacenes'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gab_create_transfer',
                        source_warehouse: sourceWarehouse,
                        target_warehouse: targetWarehouse,
                        products: products,
                        notes: $('#notes').val(),
                        nonce: '<?php echo wp_create_nonce('gab_transfer_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.href = '<?php echo admin_url('admin.php?page=gab-transfer-list'); ?>';
                        } else {
                            alert(response.data.message || '<?php esc_html_e('Error al crear la transferencia', 'gestion-almacenes'); ?>');
                            $submitBtn.prop('disabled', false).val(originalText);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e('Error de conexión', 'gestion-almacenes'); ?>');
                        $submitBtn.prop('disabled', false).val(originalText);
                    }
                });
            });
            
            // Inicializar estado del botón
            updateSubmitButton();
        });
        </script>
        
        <?php
        echo '</div>';
    }

    /**
     * Mostrar lista de transferencias
     */
    public function mostrar_lista_transferencias() {
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Lista de Transferencias', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Gestiona todas las transferencias de stock entre almacenes.', 'gestion-almacenes') . '</p>';
        echo '</div>';

        $transfers = $this->transfer_controller->get_transfers();

        echo '<div class="gab-form-section">';
        echo '<div class="gab-form-row">';
        echo '<div class="gab-form-group">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=gab-new-transfer')) . '" class="button button-primary">';
        echo '<span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>';
        echo esc_html__('Nueva Transferencia', 'gestion-almacenes');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        if ($transfers && count($transfers) > 0) {
            echo '<table class="gab-table wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="width: 80px;">' . esc_html__('ID', 'gestion-almacenes') . '</th>';
            echo '<th>' . esc_html__('Almacén Origen', 'gestion-almacenes') . '</th>';
            echo '<th>' . esc_html__('Almacén Destino', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 100px;">' . esc_html__('Estado', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Creado por', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Fecha', 'gestion-almacenes') . '</th>';
            echo '<th style="width: 150px;">' . esc_html__('Acciones', 'gestion-almacenes') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($transfers as $transfer) {
                echo '<tr>';
                echo '<td><strong>#' . str_pad($transfer->id, 4, '0', STR_PAD_LEFT) . '</strong></td>';
                echo '<td>' . esc_html($transfer->source_warehouse_name ?: 'Almacén eliminado') . '</td>';
                echo '<td>' . esc_html($transfer->target_warehouse_name ?: 'Almacén eliminado') . '</td>';
                echo '<td>';
                $status_class = 'gab-badge ';
                switch ($transfer->status) {
                    case 'draft':
                        $status_class .= 'stock-medium';
                        $status_text = __('Borrador', 'gestion-almacenes');
                        break;
                    case 'completed':
                        $status_class .= 'stock-high';
                        $status_text = __('Completada', 'gestion-almacenes');
                        break;
                    case 'cancelled':
                        $status_class .= 'stock-out';
                        $status_text = __('Cancelada', 'gestion-almacenes');
                        break;
                    default:
                        $status_class .= 'stock-low';
                        $status_text = esc_html($transfer->status);
                }
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span>';
                echo '</td>';
                echo '<td>' . esc_html($transfer->created_by_name ?: 'Usuario eliminado') . '</td>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($transfer->created_at))) . '</td>';
                echo '<td>';
                
                if ($transfer->status === 'draft') {
                    echo '<a href="' . esc_url(admin_url('admin.php?page=gab-edit-transfer&transfer_id=' . $transfer->id)) . '" ';
                    echo 'class="button button-small" title="' . esc_attr__('Editar transferencia', 'gestion-almacenes') . '">';
                    echo '<span class="dashicons dashicons-edit"></span>';
                    echo '</a> ';
                }
                
                echo '<a href="' . esc_url(admin_url('admin.php?page=gab-view-transfer&transfer_id=' . $transfer->id)) . '" ';
                echo 'class="button button-small" title="' . esc_attr__('Ver transferencia', 'gestion-almacenes') . '">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '</a>';
                
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<div class="gab-message info">';
            echo '<h3>' . esc_html__('No hay transferencias', 'gestion-almacenes') . '</h3>';
            echo '<p>' . esc_html__('No se han creado transferencias aún. Crea tu primera transferencia para comenzar.', 'gestion-almacenes') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=gab-new-transfer')) . '" class="button button-primary">';
            echo esc_html__('Crear Primera Transferencia', 'gestion-almacenes');
            echo '</a>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Páginas placeholder para las funcionalidades restantes
     */
    public function mostrar_discrepancias() {
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Gestión de Discrepancias', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Próximamente: Gestión de discrepancias de stock.', 'gestion-almacenes') . '</p>';
        echo '</div>';
        echo '</div>';
    }

    public function mostrar_almacen_mermas() {
        echo '<div class="wrap gab-admin-page">';
        echo '<div class="gab-section-header">';
        echo '<h1>' . esc_html__('Almacén de Mermas', 'gestion-almacenes') . '</h1>';
        echo '<p>' . esc_html__('Próximamente: Gestión de productos perdidos y dañados.', 'gestion-almacenes') . '</p>';
        echo '</div>';
        echo '</div>';
    }



/**
 * Página de configuración del plugin
*/
public function mostrar_configuracion() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html(__('Configuración de Gestión de Almacenes', 'gestion-almacenes')) . '</h1>';
    
    // Procesar configuración si se envió el formulario
    if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['settings_nonce'], 'save_settings_nonce')) {
        $default_warehouse = intval($_POST['default_warehouse']);
        $low_stock_threshold = intval($_POST['low_stock_threshold']);
        $auto_select_warehouse = isset($_POST['auto_select_warehouse']) ? 1 : 0;
        
        update_option('gab_default_warehouse', $default_warehouse);
        update_option('gab_low_stock_threshold', $low_stock_threshold);
        update_option('gab_auto_select_warehouse', $auto_select_warehouse);
        
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('Configuración guardada correctamente.', 'gestion-almacenes')) . '</p></div>';
    }
    
    global $gestion_almacenes_db;
    $almacenes = $gestion_almacenes_db->obtener_almacenes();
    
    $default_warehouse = get_option('gab_default_warehouse', '');
    $low_stock_threshold = get_option('gab_low_stock_threshold', 5);
    $auto_select_warehouse = get_option('gab_auto_select_warehouse', 0);
    
    echo '<form method="post" action="">';
    wp_nonce_field('save_settings_nonce', 'settings_nonce');
    
    echo '<table class="form-table">';
    
    // Almacén por defecto
    echo '<tr>';
    echo '<th scope="row"><label for="default_warehouse">' . esc_html(__('Almacén por Defecto', 'gestion-almacenes')) . '</label></th>';
    echo '<td>';
    echo '<select name="default_warehouse" id="default_warehouse">';
    echo '<option value="">' . esc_html(__('Ninguno', 'gestion-almacenes')) . '</option>';
    if ($almacenes) {
        foreach ($almacenes as $almacen) {
            $selected = ($default_warehouse == $almacen->id) ? 'selected' : '';
            echo '<option value="' . esc_attr($almacen->id) . '" ' . $selected . '>' . esc_html($almacen->name) . '</option>';
        }
    }
    echo '</select>';
    echo '<p class="description">' . esc_html(__('Almacén que se seleccionará automáticamente en los productos.', 'gestion-almacenes')) . '</p>';
    echo '</td>';
    echo '</tr>';
    
    // Umbral de stock bajo
    echo '<tr>';
    echo '<th scope="row"><label for="low_stock_threshold">' . esc_html(__('Umbral de Stock Bajo', 'gestion-almacenes')) . '</label></th>';
    echo '<td>';
    echo '<input type="number" name="low_stock_threshold" id="low_stock_threshold" value="' . esc_attr($low_stock_threshold) . '" min="1" class="small-text">';
    echo '<p class="description">' . esc_html(__('Cantidad por debajo de la cual se considera stock bajo.', 'gestion-almacenes')) . '</p>';
    echo '</td>';
    echo '</tr>';
    
    // Selección automática de almacén
    echo '<tr>';
    echo '<th scope="row">' . esc_html(__('Selección Automática', 'gestion-almacenes')) . '</th>';
    echo '<td>';
    echo '<fieldset>';
    echo '<label>';
    echo '<input type="checkbox" name="auto_select_warehouse" value="1" ' . checked($auto_select_warehouse, 1, false) . '>';
    echo esc_html(__('Seleccionar automáticamente el almacén con más stock disponible', 'gestion-almacenes'));
    echo '</label>';
    echo '<p class="description">' . esc_html(__('Si está activado, se seleccionará automáticamente el almacén con mayor stock disponible para el producto.', 'gestion-almacenes')) . '</p>';
    echo '</fieldset>';
    echo '</td>';
    echo '</tr>';
    
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" name="save_settings" class="button-primary" value="' . esc_attr(__('Guardar Configuración', 'gestion-almacenes')) . '">';
    echo '</p>';
    echo '</form>';
    
    // Información del sistema
    echo '<h2>' . esc_html(__('Información del Sistema', 'gestion-almacenes')) . '</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>' . esc_html(__('Configuración', 'gestion-almacenes')) . '</th><th>' . esc_html(__('Valor', 'gestion-almacenes')) . '</th></tr></thead>';
    echo '<tbody>';
    
    echo '<tr><td>' . esc_html(__('Total de Almacenes', 'gestion-almacenes')) . '</td><td>' . (is_array($almacenes) ? count($almacenes) : 0) . '</td></tr>';
    
    $productos_con_stock = $gestion_almacenes_db->get_all_products_warehouse_stock();
    $productos_unicos = array();
    if ($productos_con_stock) {
        foreach ($productos_con_stock as $item) {
            if (!in_array($item->product_id, $productos_unicos)) {
                $productos_unicos[] = $item->product_id;
            }
        }
    }
    echo '<tr><td>' . esc_html(__('Productos con Stock Asignado', 'gestion-almacenes')) . '</td><td>' . count($productos_unicos) . '</td></tr>';
    
    $productos_stock_bajo = $gestion_almacenes_db->get_low_stock_products($low_stock_threshold);
    echo '<tr><td>' . esc_html(__('Productos con Stock Bajo', 'gestion-almacenes')) . '</td><td>' . (is_array($productos_stock_bajo) ? count($productos_stock_bajo) : 0) . '</td></tr>';
    
    echo '<tr><td>' . esc_html(__('Versión del Plugin', 'gestion-almacenes')) . '</td><td>1.0.0</td></tr>';
    //echo '<tr><td>' . esc_html(__('Versión de WordPress', 'gestion-almacenes')) . '</td><td>' . get_bloginfo('version') . '</td></tr>';
    //echo '<tr><td>' . esc_html(__('Versión de WooCommerce', 'gestion-almacenes')) . '</td><td>' . (defined('WC_VERSION') ? WC_VERSION : 'No instalado') . '</td></tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>';
}


}
