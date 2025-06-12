<?php
/**
 * Clase para manejar el sistema de logs del Módulo de Ventas
 *
 * @package ModuloVentas
 * @since 2.0.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_Logger {
    
    /**
     * Instancia única de la clase
     */
    private static $instance = null;
    
    /**
     * Directorio de logs
     */
    private $log_dir;
    
    /**
     * Archivo de log actual
     */
    private $log_file;
    
    /**
     * Niveles de log permitidos
     */
    private $log_levels = array(
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7
    );
    
    /**
     * Nivel mínimo de log
     */
    private $min_level;
    
    /**
     * Formato de fecha para logs
     */
    private $date_format = 'Y-m-d H:i:s';
    
    /**
     * Tamaño máximo del archivo de log (5MB por defecto)
     */
    private $max_file_size = 5242880; // 5MB
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Configurar directorio de logs
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/modulo-ventas/logs/';
        
        // Crear directorio si no existe
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Crear .htaccess para proteger logs
            $htaccess = $this->log_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'deny from all');
            }
            
            // Crear index.php vacío
            $index = $this->log_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, '<?php // Silence is golden');
            }
        }
        
        // Configurar archivo de log
        $this->log_file = $this->log_dir . 'modulo-ventas-' . date('Y-m-d') . '.log';
        
        // Configurar nivel mínimo de log
        $this->min_level = get_option('modulo_ventas_log_level', 'info');
        
        // Hooks
        add_action('modulo_ventas_cron_daily', array($this, 'rotar_logs'));
        add_action('shutdown', array($this, 'log_fatal_errors'));
    }
    
    /**
     * Registrar mensaje en log
     */
    public function log($message, $level = 'info', $context = array()) {
        // Verificar si el nivel debe ser registrado
        if (!$this->should_log($level)) {
            return false;
        }
        
        // Preparar entrada de log
        $entry = $this->format_log_entry($message, $level, $context);
        
        // Escribir en archivo
        $written = $this->write_to_file($entry);
        
        // Si está en modo debug, también escribir en error_log
        if (WP_DEBUG && $level === 'error') {
            error_log('Modulo Ventas: ' . $message);
        }
        
        // Hook para permitir logging adicional
        do_action('modulo_ventas_log_written', $message, $level, $context);
        
        return $written;
    }
    
    /**
     * Métodos de conveniencia para diferentes niveles
     */
    public function emergency($message, $context = array()) {
        return $this->log($message, 'emergency', $context);
    }
    
    public function alert($message, $context = array()) {
        return $this->log($message, 'alert', $context);
    }
    
    public function critical($message, $context = array()) {
        return $this->log($message, 'critical', $context);
    }
    
    public function error($message, $context = array()) {
        return $this->log($message, 'error', $context);
    }
    
    public function warning($message, $context = array()) {
        return $this->log($message, 'warning', $context);
    }
    
    public function notice($message, $context = array()) {
        return $this->log($message, 'notice', $context);
    }
    
    public function info($message, $context = array()) {
        return $this->log($message, 'info', $context);
    }
    
    public function debug($message, $context = array()) {
        return $this->log($message, 'debug', $context);
    }
    
    /**
     * Verificar si se debe registrar según el nivel
     */
    private function should_log($level) {
        $current_level = isset($this->log_levels[$level]) ? $this->log_levels[$level] : 6;
        $min_level = isset($this->log_levels[$this->min_level]) ? $this->log_levels[$this->min_level] : 6;
        
        return $current_level <= $min_level;
    }
    
    /**
     * Formatear entrada de log
     */
    private function format_log_entry($message, $level, $context = array()) {
        $timestamp = date($this->date_format);
        $level_upper = strtoupper($level);
        
        // Información básica
        $entry = "[{$timestamp}] {$level_upper}: {$message}";
        
        // Agregar usuario si está logueado
        $user_id = get_current_user_id();
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            $entry .= " | Usuario: {$user->user_login} (ID: {$user_id})";
        }
        
        // Agregar IP
        $ip = $this->get_client_ip();
        if ($ip) {
            $entry .= " | IP: {$ip}";
        }
        
        // Agregar contexto si existe
        if (!empty($context)) {
            $entry .= " | Contexto: " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Agregar información de depuración en modo debug
        if (WP_DEBUG && $level === 'error') {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $trace_info = array();
            
            foreach ($backtrace as $i => $trace) {
                if ($i === 0) continue; // Saltar este método
                
                $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
                $line = isset($trace['line']) ? $trace['line'] : 0;
                $function = isset($trace['function']) ? $trace['function'] : 'unknown';
                
                $trace_info[] = "{$file}:{$line} {$function}()";
            }
            
            if (!empty($trace_info)) {
                $entry .= " | Trace: " . implode(' <- ', $trace_info);
            }
        }
        
        return $entry . PHP_EOL;
    }
    
    /**
     * Escribir en archivo
     */
    private function write_to_file($entry) {
        // Verificar rotación si es necesario
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_file_size) {
            $this->rotar_archivo_actual();
        }
        
        // Intentar escribir
        $result = @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
        
        // Si falla, intentar crear el directorio nuevamente
        if ($result === false) {
            wp_mkdir_p($this->log_dir);
            $result = @file_put_contents($this->log_file, $entry, FILE_APPEND | LOCK_EX);
        }
        
        return $result !== false;
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Rotar archivo actual
     */
    private function rotar_archivo_actual() {
        $new_name = $this->log_dir . 'modulo-ventas-' . date('Y-m-d-His') . '.log';
        @rename($this->log_file, $new_name);
        
        // Comprimir si está disponible
        if (function_exists('gzopen')) {
            $this->comprimir_log($new_name);
        }
    }
    
    /**
     * Comprimir archivo de log
     */
    private function comprimir_log($file) {
        $gz_file = $file . '.gz';
        $fp = fopen($file, 'rb');
        $gz = gzopen($gz_file, 'wb9');
        
        if ($fp && $gz) {
            while (!feof($fp)) {
                gzwrite($gz, fread($fp, 1024 * 512));
            }
            
            fclose($fp);
            gzclose($gz);
            
            // Eliminar archivo original
            @unlink($file);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Rotar logs antiguos
     */
    public function rotar_logs() {
        $dias_mantener = get_option('modulo_ventas_log_retention_days', 30);
        $fecha_limite = strtotime("-{$dias_mantener} days");
        
        $archivos = glob($this->log_dir . '*.log*');
        $eliminados = 0;
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $fecha_limite) {
                if (@unlink($archivo)) {
                    $eliminados++;
                }
            }
        }
        
        if ($eliminados > 0) {
            $this->info("Rotación de logs: {$eliminados} archivos eliminados");
        }
        
        return $eliminados;
    }
    
    /**
     * Limpiar logs antiguos manualmente
     */
    public function limpiar_logs_antiguos($dias = 30) {
        $fecha_limite = strtotime("-{$dias} days");
        $archivos = glob($this->log_dir . '*.log*');
        $eliminados = 0;
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $fecha_limite) {
                if (@unlink($archivo)) {
                    $eliminados++;
                }
            }
        }
        
        return $eliminados;
    }
    
    /**
     * Obtener logs para visualización
     */
    public function obtener_logs($args = array()) {
        $defaults = array(
            'fecha' => date('Y-m-d'),
            'nivel' => '',
            'buscar' => '',
            'limite' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Determinar archivo a leer
        $log_file = $this->log_dir . 'modulo-ventas-' . $args['fecha'] . '.log';
        
        if (!file_exists($log_file)) {
            return array();
        }
        
        // Leer archivo
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!$lines) {
            return array();
        }
        
        // Invertir para mostrar más recientes primero
        $lines = array_reverse($lines);
        
        // Filtrar y parsear
        $logs = array();
        foreach ($lines as $line) {
            $log_entry = $this->parse_log_line($line);
            
            if (!$log_entry) {
                continue;
            }
            
            // Filtrar por nivel
            if (!empty($args['nivel']) && $log_entry['level'] !== strtoupper($args['nivel'])) {
                continue;
            }
            
            // Filtrar por búsqueda
            if (!empty($args['buscar']) && stripos($line, $args['buscar']) === false) {
                continue;
            }
            
            $logs[] = $log_entry;
            
            // Limitar resultados
            if (count($logs) >= $args['limite']) {
                break;
            }
        }
        
        return $logs;
    }
    
    /**
     * Parsear línea de log
     */
    private function parse_log_line($line) {
        // Formato: [YYYY-MM-DD HH:MM:SS] LEVEL: Mensaje | Campo: Valor
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+): (.+)/';
        
        if (!preg_match($pattern, $line, $matches)) {
            return false;
        }
        
        $entry = array(
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'message' => $matches[3],
            'raw' => $line
        );
        
        // Parsear campos adicionales
        $parts = explode(' | ', $matches[3]);
        $entry['message'] = array_shift($parts);
        
        foreach ($parts as $part) {
            if (strpos($part, ': ') !== false) {
                list($key, $value) = explode(': ', $part, 2);
                $entry[strtolower($key)] = $value;
            }
        }
        
        return $entry;
    }
    
    /**
     * Obtener archivos de log disponibles
     */
    public function obtener_archivos_log() {
        $archivos = glob($this->log_dir . '*.log*');
        $lista = array();
        
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $fecha_match = array();
            
            if (preg_match('/modulo-ventas-(\d{4}-\d{2}-\d{2})/', $nombre, $fecha_match)) {
                $lista[] = array(
                    'archivo' => $nombre,
                    'fecha' => $fecha_match[1],
                    'tamaño' => filesize($archivo),
                    'tamaño_formateado' => size_format(filesize($archivo)),
                    'comprimido' => strpos($nombre, '.gz') !== false
                );
            }
        }
        
        // Ordenar por fecha descendente
        usort($lista, function($a, $b) {
            return strcmp($b['fecha'], $a['fecha']);
        });
        
        return $lista;
    }
    
    /**
     * Exportar logs
     */
    public function exportar_logs($fecha, $formato = 'txt') {
        $logs = $this->obtener_logs(array(
            'fecha' => $fecha,
            'limite' => -1
        ));
        
        if (empty($logs)) {
            return false;
        }
        
        $filename = 'modulo-ventas-logs-' . $fecha . '.' . $formato;
        
        switch ($formato) {
            case 'csv':
                $this->exportar_logs_csv($logs, $filename);
                break;
                
            case 'json':
                $this->exportar_logs_json($logs, $filename);
                break;
                
            default:
                $this->exportar_logs_txt($logs, $filename);
        }
        
        exit;
    }
    
    /**
     * Exportar logs como TXT
     */
    private function exportar_logs_txt($logs, $filename) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        foreach ($logs as $log) {
            echo $log['raw'] . PHP_EOL;
        }
    }
    
    /**
     * Exportar logs como CSV
     */
    private function exportar_logs_csv($logs, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // BOM para UTF-8
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array('Fecha/Hora', 'Nivel', 'Mensaje', 'Usuario', 'IP'), ';');
        
        // Data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['timestamp'],
                $log['level'],
                $log['message'],
                isset($log['usuario']) ? $log['usuario'] : '',
                isset($log['ip']) ? $log['ip'] : ''
            ), ';');
        }
        
        fclose($output);
    }
    
    /**
     * Exportar logs como JSON
     */
    private function exportar_logs_json($logs, $filename) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Log de errores fatales
     */
    public function log_fatal_errors() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $this->emergency(
                'Error Fatal: ' . $error['message'],
                array(
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'type' => $error['type']
                )
            );
        }
    }
    
    /**
     * Obtener estadísticas de logs
     */
    public function obtener_estadisticas() {
        $stats = array(
            'archivos_total' => 0,
            'tamaño_total' => 0,
            'logs_por_nivel' => array(),
            'logs_por_dia' => array()
        );
        
        $archivos = $this->obtener_archivos_log();
        $stats['archivos_total'] = count($archivos);
        
        foreach ($archivos as $archivo) {
            $stats['tamaño_total'] += $archivo['tamaño'];
        }
        
        // Analizar logs del día actual
        $logs_hoy = $this->obtener_logs(array('limite' => -1));
        
        foreach ($logs_hoy as $log) {
            $nivel = strtolower($log['level']);
            if (!isset($stats['logs_por_nivel'][$nivel])) {
                $stats['logs_por_nivel'][$nivel] = 0;
            }
            $stats['logs_por_nivel'][$nivel]++;
        }
        
        return $stats;
    }
    
    /**
     * Configurar nivel de log
     */
    public function set_log_level($level) {
        if (isset($this->log_levels[$level])) {
            $this->min_level = $level;
            update_option('modulo_ventas_log_level', $level);
            return true;
        }
        return false;
    }
    
    /**
     * Obtener nivel de log actual
     */
    public function get_log_level() {
        return $this->min_level;
    }
}