<?php
/**
 * MINI-INSTALADOR PARA PLANTILLAS PDF
 * 
 * Archivo: wp-content/plugins/modulo-de-ventas/includes/class-modulo-ventas-pdf-installer.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF_Installer {
    
    /**
     * Verificar e instalar sistema de plantillas PDF
     */
    public static function verificar_e_instalar() {
        $instalado = get_option('mv_pdf_templates_installed', false);
        
        if (!$instalado) {
            self::instalar_sistema_pdf();
            update_option('mv_pdf_templates_installed', true);
            update_option('mv_pdf_templates_version', '1.0.0');
        }
    }
    
    /**
     * Instalar sistema de plantillas PDF
     */
    private static function instalar_sistema_pdf() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        // 1. Crear tabla de plantillas PDF
        self::crear_tabla_plantillas($wpdb, $charset_collate);
        
        // 2. Crear tabla de configuración
        self::crear_tabla_configuracion($wpdb, $charset_collate);
        
        // 3. Insertar plantilla predeterminada
        self::insertar_plantilla_predeterminada($wpdb);
        
        // 4. Configurar plantilla como activa
        self::configurar_plantilla_activa($wpdb);
        
        // 5. Crear directorios necesarios
        self::crear_directorios();
        
        // 6. Configurar opciones básicas
        self::configurar_opciones_basicas();
        
        error_log('MODULO_VENTAS: Sistema de plantillas PDF instalado exitosamente');
    }
    
    /**
     * Crear tabla de plantillas PDF
     */
    private static function crear_tabla_plantillas($wpdb, $charset_collate) {
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        $sql = "CREATE TABLE $tabla (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nombre varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            tipo enum('cotizacion','venta','pedido','factura','general') NOT NULL DEFAULT 'cotizacion',
            descripcion text,
            html_content longtext NOT NULL,
            css_content longtext,
            configuracion longtext,
            es_predeterminada tinyint(1) NOT NULL DEFAULT 0,
            activa tinyint(1) NOT NULL DEFAULT 1,
            creado_por bigint(20) UNSIGNED NOT NULL DEFAULT 1,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY tipo (tipo),
            KEY activa (activa),
            KEY es_predeterminada (es_predeterminada)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Crear tabla de configuración
     */
    private static function crear_tabla_configuracion($wpdb, $charset_collate) {
        $tabla = $wpdb->prefix . 'mv_pdf_templates_config';
        
        $sql = "CREATE TABLE $tabla (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tipo_documento enum('cotizacion','venta','pedido','factura','general') NOT NULL,
            plantilla_id mediumint(9) NOT NULL,
            configuracion_adicional longtext,
            activa tinyint(1) NOT NULL DEFAULT 1,
            fecha_asignacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            asignado_por bigint(20) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY tipo_documento (tipo_documento),
            KEY plantilla_id (plantilla_id),
            KEY activa (activa)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Insertar plantilla predeterminada
     */
    private static function insertar_plantilla_predeterminada($wpdb) {
        $tabla = $wpdb->prefix . 'mv_pdf_templates';
        
        // Verificar si ya existe
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla WHERE slug = %s",
            'plantilla-predeterminada-cotizacion'
        ));
        
        if ($existe > 0) {
            return;
        }
        
        $html_content = '<div class="documento-cotizacion">
    <header class="header-empresa">
        <div class="logo-container">
            {{logo_empresa}}
        </div>
        <div class="datos-empresa">
            <h1>{{empresa.nombre}}</h1>
            <p>{{empresa.direccion}}</p>
            <p>Teléfono: {{empresa.telefono}} | Email: {{empresa.email}}</p>
            <p>RUT: {{empresa.rut}}</p>
        </div>
    </header>

    <div class="titulo-documento">
        <h1>COTIZACIÓN {{cotizacion.numero}}</h1>
        <p>Fecha: {{fechas.fecha_cotizacion}}</p>
        <p>Válida hasta: {{fechas.fecha_vencimiento_formateada}}</p>
    </div>

    <div class="seccion-cliente">
        <h2>DATOS DEL CLIENTE</h2>
        <div class="cliente-info">
            <p><strong>Razón Social:</strong> {{cliente.nombre}}</p>
            <p><strong>RUT:</strong> {{cliente.rut}}</p>
            <p><strong>Dirección:</strong> {{cliente.direccion}}</p>
            <p><strong>Ciudad:</strong> {{cliente.ciudad}}, {{cliente.region}}</p>
            <p><strong>Teléfono:</strong> {{cliente.telefono}}</p>
            <p><strong>Email:</strong> {{cliente.email}}</p>
        </div>
    </div>

    <div class="seccion-productos">
        <h2>DETALLE DE PRODUCTOS/SERVICIOS</h2>
        {{tabla_productos}}
    </div>

    <div class="seccion-totales">
        <div class="tabla-totales">
            <div class="fila-total">
                <span class="label">Subtotal:</span>
                <span class="valor">${{totales.subtotal_formateado}}</span>
            </div>
            <div class="fila-total">
                <span class="label">Descuento ({{totales.descuento_porcentaje}}%):</span>
                <span class="valor">-${{totales.descuento_formateado}}</span>
            </div>
            <div class="fila-total">
                <span class="label">IVA (19%):</span>
                <span class="valor">${{totales.impuestos_formateado}}</span>
            </div>
            <div class="fila-total total-final">
                <span class="label"><strong>TOTAL:</strong></span>
                <span class="valor"><strong>${{totales.total_formateado}}</strong></span>
            </div>
        </div>
    </div>

    <div class="seccion-observaciones">
        <h2>OBSERVACIONES</h2>
        <p>{{cotizacion.observaciones}}</p>
        
        <div class="condiciones">
            <h3>Condiciones Generales:</h3>
            <ul>
                <li>Validez de la oferta: 30 días desde la fecha de emisión</li>
                <li>Forma de pago: 50% al confirmar pedido, 50% contra entrega</li>
                <li>Tiempo de entrega: 15 días hábiles</li>
                <li>Garantía: 12 meses por defectos de fabricación</li>
            </ul>
        </div>
    </div>

    <footer class="footer-documento">
        <p>Atentamente,</p>
        <p><strong>{{cotizacion.vendedor}}</strong></p>
        <p>{{empresa.nombre}}</p>
        <p>Documento generado el {{fechas.hoy}} a las {{sistema.hora_actual}}</p>
    </footer>
</div>';

        $css_content = '/* Estilos base para documento PDF */
body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #333;
    margin: 0;
    padding: 0;
}

.documento-cotizacion {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.header-empresa {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #0073aa;
}

.logo-container {
    flex: 0 0 150px;
}

.logo-empresa {
    max-width: 140px;
    max-height: 80px;
    height: auto;
}

.datos-empresa {
    flex: 1;
    text-align: right;
    margin-left: 20px;
}

.datos-empresa h1 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 18px;
}

.datos-empresa p {
    margin: 3px 0;
    font-size: 11px;
}

.titulo-documento {
    text-align: center;
    margin: 30px 0;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.titulo-documento h1 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 24px;
    font-weight: bold;
}

.titulo-documento p {
    margin: 5px 0;
    font-size: 12px;
    color: #666;
}

.seccion-cliente {
    margin: 25px 0;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.seccion-cliente h2 {
    margin: 0 0 15px 0;
    color: #0073aa;
    font-size: 14px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.cliente-info p {
    margin: 5px 0;
    font-size: 11px;
}

.seccion-productos {
    margin: 25px 0;
}

.seccion-productos h2 {
    margin: 0 0 15px 0;
    color: #0073aa;
    font-size: 14px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.productos-tabla {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-size: 10px;
}

.productos-tabla th {
    background-color: #0073aa;
    color: white;
    border: 1px solid #0073aa;
    padding: 8px;
    text-align: left;
    font-weight: bold;
    font-size: 10px;
}

.productos-tabla td {
    border: 1px solid #ddd;
    padding: 8px;
    vertical-align: top;
}

.productos-tabla tr:nth-child(even) {
    background-color: #f9f9f9;
}

.productos-tabla td:nth-child(3),
.productos-tabla td:nth-child(4),
.productos-tabla td:nth-child(5),
.productos-tabla td:nth-child(6) {
    text-align: right;
}

.seccion-totales {
    margin: 25px 0;
}

.tabla-totales {
    width: 300px;
    margin-left: auto;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.fila-total {
    display: flex;
    justify-content: space-between;
    padding: 8px 15px;
    border-bottom: 1px solid #eee;
    font-size: 11px;
}

.fila-total:last-child {
    border-bottom: none;
}

.total-final {
    background-color: #0073aa;
    color: white;
    font-size: 12px;
}

.fila-total .label {
    flex: 1;
}

.fila-total .valor {
    flex: 0 0 auto;
    min-width: 80px;
    text-align: right;
}

.seccion-observaciones {
    margin: 25px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.seccion-observaciones h2 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 14px;
}

.seccion-observaciones p {
    margin: 5px 0;
    font-size: 11px;
}

.condiciones {
    margin-top: 15px;
}

.condiciones h3 {
    margin: 0 0 8px 0;
    color: #333;
    font-size: 12px;
}

.condiciones ul {
    margin: 0;
    padding-left: 20px;
}

.condiciones li {
    margin: 3px 0;
    font-size: 10px;
}

.footer-documento {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    text-align: center;
    font-size: 10px;
    color: #666;
}

.footer-documento p {
    margin: 3px 0;
}

@media (max-width: 600px) {
    .header-empresa {
        flex-direction: column;
        text-align: center;
    }
    
    .datos-empresa {
        text-align: center;
        margin-left: 0;
        margin-top: 15px;
    }
    
    .productos-tabla {
        font-size: 9px;
    }
    
    .productos-tabla th,
    .productos-tabla td {
        padding: 4px;
    }
}';

        $wpdb->insert($tabla, array(
            'nombre' => 'Plantilla Predeterminada Cotización',
            'slug' => 'plantilla-predeterminada-cotizacion',
            'tipo' => 'cotizacion',
            'descripcion' => 'Plantilla predeterminada del sistema para cotizaciones.',
            'html_content' => $html_content,
            'css_content' => $css_content,
            'configuracion' => json_encode(array('formato_papel' => 'A4', 'orientacion' => 'vertical')),
            'es_predeterminada' => 1,
            'activa' => 1,
            'creado_por' => 1,
            'fecha_creacion' => current_time('mysql'),
            'fecha_actualizacion' => current_time('mysql')
        ));
    }
    
    /**
     * Configurar plantilla como activa
     */
    private static function configurar_plantilla_activa($wpdb) {
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        
        // Obtener ID de la plantilla predeterminada
        $plantilla_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tabla_plantillas WHERE slug = %s",
            'plantilla-predeterminada-cotizacion'
        ));
        
        if ($plantilla_id) {
            // Verificar si ya existe configuración
            $existe_config = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $tabla_config WHERE tipo_documento = %s",
                'cotizacion'
            ));
            
            if ($existe_config == 0) {
                $wpdb->insert($tabla_config, array(
                    'tipo_documento' => 'cotizacion',
                    'plantilla_id' => $plantilla_id,
                    'configuracion_adicional' => '{}',
                    'activa' => 1,
                    'fecha_asignacion' => current_time('mysql'),
                    'asignado_por' => 1
                ));
            }
        }
    }
    
    /**
     * Crear directorios necesarios
     */
    private static function crear_directorios() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/modulo-ventas';
        
        $directorios = array(
            $base_dir . '/previews',
            $base_dir . '/pdfs'
        );
        
        foreach ($directorios as $directorio) {
            if (!file_exists($directorio)) {
                wp_mkdir_p($directorio);
                
                // Crear .htaccess básico
                $htaccess_content = "# Modulo de Ventas\n";
                $htaccess_content .= "Options -Indexes\n";
                
                file_put_contents($directorio . '/.htaccess', $htaccess_content);
                file_put_contents($directorio . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Configurar opciones básicas
     */
    private static function configurar_opciones_basicas() {
        // Solo configurar si no existen
        $opciones = array(
            'mv_empresa_nombre' => get_bloginfo('name'),
            'mv_empresa_direccion' => 'Dirección no configurada',
            'mv_empresa_telefono' => '',
            'mv_empresa_email' => get_option('admin_email'),
            'mv_empresa_rut' => '77.777.777-7'
        );
        
        foreach ($opciones as $opcion => $valor) {
            if (!get_option($opcion)) {
                update_option($opcion, $valor);
            }
        }
    }
    
    /**
     * Verificar si las tablas existen
     */
    public static function tablas_existen() {
        global $wpdb;
        
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        
        $plantillas_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_plantillas'") == $tabla_plantillas;
        $config_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_config'") == $tabla_config;
        
        return $plantillas_existe && $config_existe;
    }
}