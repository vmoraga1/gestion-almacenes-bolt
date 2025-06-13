<?php
/**
 * Clase para generar PDFs del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_PDF {
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Instancia de la base de datos
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = Modulo_Ventas_Logger::get_instance();
        $this->db = new Modulo_Ventas_DB();
        
        // Incluir TCPDF si existe
        if ($this->verificar_tcpdf()) {
            $this->incluir_tcpdf();
        }
    }
    
    /**
     * Verificar si TCPDF está disponible
     */
    private function verificar_tcpdf() {
        $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        
        if (!file_exists($tcpdf_path)) {
            $this->logger->log('TCPDF no encontrado. Instale con: composer require tecnickcom/tcpdf', 'warning');
            return false;
        }
        
        return true;
    }
    
    /**
     * Incluir TCPDF
     */
    private function incluir_tcpdf() {
        require_once MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
    }
    
    /**
     * Generar PDF de cotización
     */
    public function generar_pdf_cotizacion($cotizacion_id) {
        if (!$this->verificar_tcpdf()) {
            return new WP_Error('tcpdf_missing', __('TCPDF no está instalado', 'modulo-ventas'));
        }
        
        // Obtener datos
        $cotizacion = $this->db->obtener_cotizacion($cotizacion_id);
        if (!$cotizacion) {
            return new WP_Error('invalid_quote', __('Cotización no encontrada', 'modulo-ventas'));
        }
        
        $cliente = $this->db->obtener_cliente($cotizacion->cliente_id);
        $items = $this->db->obtener_items_cotizacion($cotizacion_id);
        
        // Por ahora, retornar mensaje de desarrollo
        return new WP_Error('in_development', __('Generación de PDF en desarrollo', 'modulo-ventas'));
    }
    
    /**
     * Generar PDF de reporte
     */
    public function generar_pdf_reporte($tipo, $parametros = array()) {
        if (!$this->verificar_tcpdf()) {
            return new WP_Error('tcpdf_missing', __('TCPDF no está instalado', 'modulo-ventas'));
        }
        
        // Por implementar
        return new WP_Error('in_development', __('Generación de reportes en desarrollo', 'modulo-ventas'));
    }
}