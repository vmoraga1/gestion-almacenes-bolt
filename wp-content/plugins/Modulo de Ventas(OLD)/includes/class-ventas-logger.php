<?php
if (!defined('ABSPATH')) {
    exit;
}

class Ventas_Logger {
    private static $instance = null;
    private $log_dir;
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/ventas-logs/';
        
        // Crear directorio de logs si no existe
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log($message, $type = 'info') {
        $date = current_time('Y-m-d');
        $time = current_time('H:i:s');
        $log_file = $this->log_dir . $date . '.log';
        
        // Formatear mensaje
        $log_message = sprintf(
            "[%s] [%s] %s\n",
            $time,
            strtoupper($type),
            is_array($message) || is_object($message) ? print_r($message, true) : $message
        );
        
        // Escribir en archivo
        error_log($log_message, 3, $log_file);
    }
    
    public function get_log_content($date) {
        $log_file = $this->log_dir . $date . '.log';
        if (file_exists($log_file)) {
            return file_get_contents($log_file);
        }
        return '';
    }
    
    public function get_available_dates() {
        $dates = array();
        if (is_dir($this->log_dir)) {
            foreach (scandir($this->log_dir) as $file) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $file)) {
                    $dates[] = substr($file, 0, 10);
                }
            }
        }
        return array_reverse($dates);
    }
    
    public function get_log_types() {
        return array(
            'info' => 'Información',
            'error' => 'Error',
            'warning' => 'Advertencia',
            'debug' => 'Debug'
        );
    }
}