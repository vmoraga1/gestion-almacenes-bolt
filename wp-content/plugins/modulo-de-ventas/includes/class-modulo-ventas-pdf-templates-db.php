<?php
/**
 * ESTRUCTURA DE BASE DE DATOS PARA PLANTILLAS PDF PERSONALIZABLES
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF_Templates_DB {
    
    /**
     * Versión de la base de datos de plantillas
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'verificar_tablas'), 11);
    }
    
    /**
     * Verificar y crear tablas si es necesario
     */
    public function verificar_tablas() {
        $version_actual = get_option('mv_pdf_templates_db_version', '0.0.0');
        
        if (version_compare($version_actual, self::DB_VERSION, '<')) {
            $this->crear_tablas();
            update_option('mv_pdf_templates_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Crear todas las tablas necesarias
     */
    public function crear_tablas() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla principal de plantillas
        $this->crear_tabla_plantillas($wpdb, $charset_collate);
        
        // Tabla de configuración de plantillas activas
        $this->crear_tabla_configuracion($wpdb, $charset_collate);
        
        // Tabla de variables disponibles
        $this->crear_tabla_variables($wpdb, $charset_collate);
        
        // Insertar datos por defecto
        $this->insertar_datos_por_defecto();
        
        error_log('MODULO_VENTAS_TEMPLATES: Tablas de plantillas PDF creadas exitosamente');
    }
    
    /**
     * Crear tabla de plantillas PDF
     */
    private function crear_tabla_plantillas($wpdb, $charset_collate) {
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        
        $sql = "CREATE TABLE $tabla_plantillas (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nombre varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            tipo enum('cotizacion','venta','pedido','factura','general') NOT NULL DEFAULT 'cotizacion',
            descripcion text,
            html_content longtext NOT NULL,
            css_content longtext,
            configuracion longtext,
            variables_usadas text,
            es_predeterminada tinyint(1) NOT NULL DEFAULT 0,
            activa tinyint(1) NOT NULL DEFAULT 1,
            version varchar(10) NOT NULL DEFAULT '1.0',
            creado_por bigint(20) UNSIGNED NOT NULL,
            fecha_creacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY tipo (tipo),
            KEY activa (activa),
            KEY es_predeterminada (es_predeterminada),
            KEY creado_por (creado_por)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log('Error creando tabla de plantillas PDF: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Crear tabla de configuración de plantillas activas
     */
    private function crear_tabla_configuracion($wpdb, $charset_collate) {
        $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
        
        $sql = "CREATE TABLE $tabla_config (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tipo_documento enum('cotizacion','venta','pedido','factura','general') NOT NULL,
            plantilla_id mediumint(9) NOT NULL,
            configuracion_adicional longtext,
            activa tinyint(1) NOT NULL DEFAULT 1,
            fecha_asignacion datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            asignado_por bigint(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tipo_documento (tipo_documento),
            KEY plantilla_id (plantilla_id),
            KEY activa (activa)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log('Error creando tabla de configuración de plantillas: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Crear tabla de variables disponibles
     */
    private function crear_tabla_variables($wpdb, $charset_collate) {
        $tabla_variables = $wpdb->prefix . 'mv_pdf_template_variables';
        
        $sql = "CREATE TABLE $tabla_variables (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            categoria varchar(50) NOT NULL,
            variable varchar(100) NOT NULL,
            descripcion text NOT NULL,
            tipo_dato enum('string','number','date','boolean','object','array') NOT NULL DEFAULT 'string',
            ejemplo varchar(255),
            disponible_en text,
            obligatoria tinyint(1) NOT NULL DEFAULT 0,
            activa tinyint(1) NOT NULL DEFAULT 1,
            orden int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY variable (variable),
            KEY categoria (categoria),
            KEY activa (activa),
            KEY orden (orden)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log('Error creando tabla de variables de plantillas: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Insertar datos por defecto
     */
    private function insertar_datos_por_defecto() {
        global $wpdb;
        
        // Verificar si ya existen datos
        $tabla_variables = $wpdb->prefix . 'mv_pdf_template_variables';
        $existe_datos = $wpdb->get_var("SELECT COUNT(*) FROM $tabla_variables");
        
        if ($existe_datos > 0) {
            return; // Ya existen datos
        }
        
        // Variables por defecto
        $variables_por_defecto = $this->obtener_variables_por_defecto();
        
        foreach ($variables_por_defecto as $variable) {
            $wpdb->insert($tabla_variables, $variable);
        }
        
        // Plantilla por defecto para cotizaciones
        $this->crear_plantilla_por_defecto_cotizacion();
        
        error_log('MODULO_VENTAS_TEMPLATES: Datos por defecto insertados');
    }
    
    /**
     * Obtener variables por defecto del sistema
     */
    private function obtener_variables_por_defecto() {
        return array(
            // Variables de empresa
            array(
                'categoria' => 'empresa',
                'variable' => 'empresa.nombre',
                'descripcion' => 'Nombre de la empresa',
                'tipo_dato' => 'string',
                'ejemplo' => 'Mi Empresa S.A.',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'obligatoria' => 1,
                'orden' => 1
            ),
            array(
                'categoria' => 'empresa',
                'variable' => 'empresa.direccion',
                'descripcion' => 'Dirección de la empresa',
                'tipo_dato' => 'string',
                'ejemplo' => 'Av. Principal 123, Santiago',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 2
            ),
            array(
                'categoria' => 'empresa',
                'variable' => 'empresa.telefono',
                'descripcion' => 'Teléfono de la empresa',
                'tipo_dato' => 'string',
                'ejemplo' => '+56 2 1234 5678',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 3
            ),
            array(
                'categoria' => 'empresa',
                'variable' => 'empresa.email',
                'descripcion' => 'Email de la empresa',
                'tipo_dato' => 'string',
                'ejemplo' => 'contacto@miempresa.cl',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 4
            ),
            array(
                'categoria' => 'empresa',
                'variable' => 'empresa.rut',
                'descripcion' => 'RUT de la empresa',
                'tipo_dato' => 'string',
                'ejemplo' => '12.345.678-9',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 5
            ),
            
            // Variables de cotización
            array(
                'categoria' => 'cotizacion',
                'variable' => 'cotizacion.folio',
                'descripcion' => 'Número de folio de la cotización',
                'tipo_dato' => 'string',
                'ejemplo' => 'COT-2025-000024',
                'disponible_en' => 'cotizacion',
                'obligatoria' => 1,
                'orden' => 10
            ),
            array(
                'categoria' => 'cotizacion',
                'variable' => 'cotizacion.fecha',
                'descripcion' => 'Fecha de emisión de la cotización',
                'tipo_dato' => 'date',
                'ejemplo' => '09/07/2025',
                'disponible_en' => 'cotizacion',
                'obligatoria' => 1,
                'orden' => 11
            ),
            array(
                'categoria' => 'cotizacion',
                'variable' => 'cotizacion.fecha_expiracion',
                'descripcion' => 'Fecha de expiración de la cotización',
                'tipo_dato' => 'date',
                'ejemplo' => '08/08/2025',
                'disponible_en' => 'cotizacion',
                'orden' => 12
            ),
            array(
                'categoria' => 'cotizacion',
                'variable' => 'cotizacion.subtotal',
                'descripcion' => 'Subtotal de la cotización',
                'tipo_dato' => 'number',
                'ejemplo' => '$190,400',
                'disponible_en' => 'cotizacion',
                'obligatoria' => 1,
                'orden' => 13
            ),
            array(
                'categoria' => 'cotizacion',
                'variable' => 'cotizacion.total',
                'descripcion' => 'Total de la cotización',
                'tipo_dato' => 'number',
                'ejemplo' => '$226,576',
                'disponible_en' => 'cotizacion',
                'obligatoria' => 1,
                'orden' => 14
            ),
            
            // Variables de cliente
            array(
                'categoria' => 'cliente',
                'variable' => 'cliente.nombre',
                'descripcion' => 'Nombre o razón social del cliente',
                'tipo_dato' => 'string',
                'ejemplo' => 'Alexis Sanchez',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'obligatoria' => 1,
                'orden' => 20
            ),
            array(
                'categoria' => 'cliente',
                'variable' => 'cliente.rut',
                'descripcion' => 'RUT del cliente',
                'tipo_dato' => 'string',
                'ejemplo' => '12.345.678-9',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 21
            ),
            array(
                'categoria' => 'cliente',
                'variable' => 'cliente.direccion',
                'descripcion' => 'Dirección del cliente',
                'tipo_dato' => 'string',
                'ejemplo' => 'Av. Cliente 456, Valparaíso',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 22
            ),
            array(
                'categoria' => 'cliente',
                'variable' => 'cliente.telefono',
                'descripcion' => 'Teléfono del cliente',
                'tipo_dato' => 'string',
                'ejemplo' => '+56 9 8765 4321',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 23
            ),
            array(
                'categoria' => 'cliente',
                'variable' => 'cliente.email',
                'descripcion' => 'Email del cliente',
                'tipo_dato' => 'string',
                'ejemplo' => 'cliente@email.com',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'orden' => 24
            ),
            
            // Variables de productos
            array(
                'categoria' => 'productos',
                'variable' => 'productos',
                'descripcion' => 'Lista de productos/servicios',
                'tipo_dato' => 'array',
                'ejemplo' => '[{nombre: "Producto 1", cantidad: 2, precio: 1000}]',
                'disponible_en' => 'cotizacion,venta,pedido,factura',
                'obligatoria' => 1,
                'orden' => 30
            ),
            
            // Variables de vendedor
            array(
                'categoria' => 'vendedor',
                'variable' => 'vendedor.nombre',
                'descripcion' => 'Nombre del vendedor asignado',
                'tipo_dato' => 'string',
                'ejemplo' => 'Juan Pérez',
                'disponible_en' => 'cotizacion,venta,pedido',
                'orden' => 40
            )
        );
    }
    
    /**
     * Crear plantilla por defecto para cotizaciones
     */
    private function crear_plantilla_por_defecto_cotizacion() {
        global $wpdb;
        
        $tabla_plantillas = $wpdb->prefix . 'mv_pdf_templates';
        
        // HTML de la plantilla por defecto
        $html_content = $this->obtener_html_plantilla_por_defecto();
        $css_content = $this->obtener_css_plantilla_por_defecto();
        
        $plantilla_datos = array(
            'nombre' => 'Plantilla Estándar - Cotización',
            'slug' => 'cotizacion-estandar',
            'tipo' => 'cotizacion',
            'descripcion' => 'Plantilla estándar para cotizaciones con diseño profesional',
            'html_content' => $html_content,
            'css_content' => $css_content,
            'configuracion' => json_encode(array(
                'papel_tamano' => 'A4',
                'papel_orientacion' => 'P',
                'margenes' => array(
                    'superior' => 15,
                    'inferior' => 15,
                    'izquierdo' => 15,
                    'derecho' => 15
                )
            )),
            'variables_usadas' => 'empresa.nombre,empresa.direccion,empresa.telefono,empresa.email,cotizacion.folio,cotizacion.fecha,cotizacion.fecha_expiracion,cliente.nombre,cliente.rut,productos,cotizacion.subtotal,cotizacion.total',
            'es_predeterminada' => 1,
            'activa' => 1,
            'creado_por' => get_current_user_id() ?: 1,
            'fecha_creacion' => current_time('mysql')
        );
        
        $result = $wpdb->insert($tabla_plantillas, $plantilla_datos);
        
        if ($result) {
            $plantilla_id = $wpdb->insert_id;
            
            // Asignar como plantilla activa para cotizaciones
            $tabla_config = $wpdb->prefix . 'mv_pdf_templates_config';
            $wpdb->insert($tabla_config, array(
                'tipo_documento' => 'cotizacion',
                'plantilla_id' => $plantilla_id,
                'activa' => 1,
                'asignado_por' => get_current_user_id() ?: 1
            ));
            
            error_log('MODULO_VENTAS_TEMPLATES: Plantilla por defecto creada con ID: ' . $plantilla_id);
        } else {
            error_log('MODULO_VENTAS_TEMPLATES: Error creando plantilla por defecto: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Obtener HTML de plantilla por defecto
     */
    private function obtener_html_plantilla_por_defecto() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cotización {{cotizacion.folio}}</title>
</head>
<body>
    <div class="documento">
        <!-- Header -->
        <div class="header">
            <div class="empresa-info">
                <h1>{{empresa.nombre}}</h1>
                <div class="empresa-datos">
                    <p>{{empresa.direccion}}</p>
                    <p>Tel: {{empresa.telefono}} | Email: {{empresa.email}}</p>
                    {{#if empresa.rut}}<p>RUT: {{empresa.rut}}</p>{{/if}}
                </div>
            </div>
            <div class="documento-info">
                <h2>COTIZACIÓN</h2>
                <div class="documento-datos">
                    <p><strong>N°:</strong> {{cotizacion.folio}}</p>
                    <p><strong>Fecha:</strong> {{cotizacion.fecha}}</p>
                    {{#if cotizacion.fecha_expiracion}}
                    <p><strong>Válida hasta:</strong> {{cotizacion.fecha_expiracion}}</p>
                    {{/if}}
                </div>
            </div>
        </div>
        
        <!-- Información del Cliente -->
        <div class="cliente-seccion">
            <h3>INFORMACIÓN DEL CLIENTE</h3>
            <div class="cliente-datos">
                <p><strong>Cliente:</strong> {{cliente.nombre}}</p>
                {{#if cliente.rut}}<p><strong>RUT:</strong> {{cliente.rut}}</p>{{/if}}
                {{#if cliente.direccion}}<p><strong>Dirección:</strong> {{cliente.direccion}}</p>{{/if}}
                {{#if cliente.telefono}}<p><strong>Teléfono:</strong> {{cliente.telefono}}</p>{{/if}}
                {{#if cliente.email}}<p><strong>Email:</strong> {{cliente.email}}</p>{{/if}}
            </div>
        </div>
        
        <!-- Tabla de Productos -->
        <div class="productos-seccion">
            <table class="productos-tabla">
                <thead>
                    <tr>
                        <th class="producto-desc">Descripción</th>
                        <th class="producto-cant">Cant.</th>
                        <th class="producto-precio">Precio Unit.</th>
                        <th class="producto-subtotal">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {{#each productos}}
                    <tr>
                        <td class="producto-desc">
                            {{#if this.sku}}<strong>[{{this.sku}}]</strong> {{/if}}
                            {{this.nombre}}
                            {{#if this.descripcion}}<br><small>{{this.descripcion}}</small>{{/if}}
                        </td>
                        <td class="producto-cant">{{this.cantidad}}</td>
                        <td class="producto-precio">${{this.precio_unitario}}</td>
                        <td class="producto-subtotal">${{this.subtotal}}</td>
                    </tr>
                    {{/each}}
                </tbody>
            </table>
        </div>
        
        <!-- Totales -->
        <div class="totales-seccion">
            <div class="totales-tabla">
                {{#if cotizacion.subtotal}}
                <div class="total-fila">
                    <span class="total-label">Subtotal:</span>
                    <span class="total-valor">${{cotizacion.subtotal}}</span>
                </div>
                {{/if}}
                {{#if cotizacion.descuento_global}}
                <div class="total-fila">
                    <span class="total-label">Descuento:</span>
                    <span class="total-valor">-${{cotizacion.descuento_global}}</span>
                </div>
                {{/if}}
                {{#if cotizacion.iva}}
                <div class="total-fila">
                    <span class="total-label">IVA (19%):</span>
                    <span class="total-valor">${{cotizacion.iva}}</span>
                </div>
                {{/if}}
                <div class="total-fila total-final">
                    <span class="total-label"><strong>TOTAL:</strong></span>
                    <span class="total-valor"><strong>${{cotizacion.total}}</strong></span>
                </div>
            </div>
        </div>
        
        <!-- Términos y Condiciones -->
        <div class="terminos-seccion">
            <h4>Términos y Condiciones</h4>
            <ul>
                <li>Esta cotización tiene una validez de 30 días desde la fecha de emisión.</li>
                <li>Los precios incluyen IVA y están sujetos a cambios sin previo aviso.</li>
                <li>Para confirmar el pedido, se requiere una señal del 50% del valor total.</li>
                <li>Los tiempos de entrega se confirmarán al momento de la orden de compra.</li>
            </ul>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Gracias por confiar en {{empresa.nombre}}</p>
            {{#if vendedor.nombre}}<p><em>Atendido por: {{vendedor.nombre}}</em></p>{{/if}}
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Obtener CSS de plantilla por defecto
     */
    private function obtener_css_plantilla_por_defecto() {
        return 'body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    color: #333;
    line-height: 1.4;
}

.documento {
    max-width: 100%;
    margin: 0 auto;
    padding: 20px;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    border-bottom: 2px solid #2c5aa0;
    padding-bottom: 20px;
}

.empresa-info h1 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 24px;
}

.empresa-datos p {
    margin: 2px 0;
    font-size: 12px;
}

.documento-info {
    text-align: right;
}

.documento-info h2 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 20px;
}

.documento-datos p {
    margin: 2px 0;
    font-size: 12px;
}

/* Cliente */
.cliente-seccion {
    margin-bottom: 25px;
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.cliente-seccion h3 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 14px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
}

.cliente-datos p {
    margin: 3px 0;
    font-size: 12px;
}

/* Tabla de Productos */
.productos-seccion {
    margin-bottom: 25px;
}

.productos-tabla {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.productos-tabla th {
    background-color: #2c5aa0;
    color: white;
    padding: 10px 8px;
    text-align: left;
    font-size: 12px;
    font-weight: bold;
}

.productos-tabla td {
    padding: 8px;
    border-bottom: 1px solid #dee2e6;
    font-size: 11px;
}

.producto-desc {
    width: 50%;
}

.producto-cant {
    width: 15%;
    text-align: center;
}

.producto-precio {
    width: 20%;
    text-align: right;
}

.producto-subtotal {
    width: 15%;
    text-align: right;
    font-weight: bold;
}

.productos-tabla tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}

/* Totales */
.totales-seccion {
    margin-bottom: 25px;
    display: flex;
    justify-content: flex-end;
}

.totales-tabla {
    min-width: 250px;
}

.total-fila {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid #dee2e6;
}

.total-final {
    border-top: 2px solid #2c5aa0;
    border-bottom: 2px solid #2c5aa0;
    margin-top: 10px;
    padding-top: 10px;
    font-size: 14px;
}

.total-label {
    font-size: 12px;
}

.total-valor {
    font-size: 12px;
    text-align: right;
}

/* Términos */
.terminos-seccion {
    margin-bottom: 25px;
    border-top: 1px solid #dee2e6;
    padding-top: 20px;
}

.terminos-seccion h4 {
    color: #2c5aa0;
    margin: 0 0 10px 0;
    font-size: 13px;
}

.terminos-seccion ul {
    margin: 0;
    padding-left: 20px;
}

.terminos-seccion li {
    margin-bottom: 5px;
    font-size: 10px;
    line-height: 1.3;
}

/* Footer */
.footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    font-size: 11px;
    color: #666;
}

.footer p {
    margin: 5px 0;
}

/* Utilidades */
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: bold; }
.font-italic { font-style: italic; }

/* Responsive adjustments for PDF */
@media print {
    .documento {
        padding: 10px;
    }
    
    .header {
        page-break-inside: avoid;
    }
    
    .productos-tabla {
        page-break-inside: auto;
    }
    
    .productos-tabla tr {
        page-break-inside: avoid;
    }
}';
    }
}

// Inicializar la clase
new Modulo_Ventas_PDF_Templates_DB();