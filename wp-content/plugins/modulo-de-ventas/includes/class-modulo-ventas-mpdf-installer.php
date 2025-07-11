<?php
/**
 * Instalador automático de mPDF
 */

if (!defined('ABSPATH')) {
    exit;
}

class Modulo_Ventas_mPDF_Installer {
    
    /**
     * Instalar mPDF automáticamente
     */
    public static function instalar() {
        $resultado = array(
            'success' => false,
            'message' => '',
            'method' => ''
        );
        
        try {
            // Método 1: Verificar si ya está instalado
            if (self::verificar_mpdf_existente()) {
                $resultado['success'] = true;
                $resultado['message'] = 'mPDF ya está instalado';
                $resultado['method'] = 'existing';
                return $resultado;
            }
            
            // Método 2: Intentar Composer
            if (self::instalar_via_composer()) {
                $resultado['success'] = true;
                $resultado['message'] = 'mPDF instalado via Composer';
                $resultado['method'] = 'composer';
                return $resultado;
            }
            
            // Método 3: Descarga manual
            if (self::instalar_via_descarga()) {
                $resultado['success'] = true;
                $resultado['message'] = 'mPDF instalado via descarga manual';
                $resultado['method'] = 'download';
                return $resultado;
            }
            
            $resultado['message'] = 'No se pudo instalar mPDF automáticamente';
            
        } catch (Exception $e) {
            $resultado['message'] = 'Error durante instalación: ' . $e->getMessage();
        }
        
        return $resultado;
    }
    
    /**
     * Verificar si mPDF ya existe
     */
    private static function verificar_mpdf_existente() {
        $paths = array(
            MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php',
            MODULO_VENTAS_PLUGIN_DIR . 'vendor/mpdf/mpdf/src/Mpdf.php'
        );
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Instalar via Composer
     */
    private static function instalar_via_composer() {
        $plugin_dir = MODULO_VENTAS_PLUGIN_DIR;
        
        // Verificar si composer está disponible
        $composer_path = self::encontrar_composer();
        if (!$composer_path) {
            return false;
        }
        
        // Crear composer.json si no existe
        $composer_json = $plugin_dir . 'composer.json';
        if (!file_exists($composer_json)) {
            $composer_config = array(
                "require" => array(
                    "mpdf/mpdf" => "^8.0"
                ),
                "config" => array(
                    "optimize-autoloader" => true
                )
            );
            
            file_put_contents($composer_json, json_encode($composer_config, JSON_PRETTY_PRINT));
        }
        
        // Ejecutar composer install
        $old_dir = getcwd();
        chdir($plugin_dir);
        
        $command = $composer_path . ' install --no-dev --optimize-autoloader 2>&1';
        $output = shell_exec($command);
        
        chdir($old_dir);
        
        // Verificar instalación
        return file_exists($plugin_dir . 'vendor/mpdf/mpdf/src/Mpdf.php');
    }
    
    /**
     * Encontrar ruta de Composer
     */
    private static function encontrar_composer() {
        $possible_paths = array(
            'composer',
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            'composer.phar'
        );
        
        foreach ($possible_paths as $path) {
            $test = shell_exec("which $path 2>/dev/null");
            if (!empty($test)) {
                return trim($path);
            }
        }
        
        return false;
    }
    
    /**
     * Instalar via descarga manual
     */
    private static function instalar_via_descarga() {
        $vendor_dir = MODULO_VENTAS_PLUGIN_DIR . 'vendor/';
        $mpdf_dir = $vendor_dir . 'mpdf/mpdf/';
        
        // Crear directorios
        if (!file_exists($vendor_dir)) {
            wp_mkdir_p($vendor_dir);
        }
        
        // URL de descarga de mPDF
        $download_url = 'https://github.com/mpdf/mpdf/archive/refs/tags/v8.2.4.zip';
        $zip_file = $vendor_dir . 'mpdf.zip';
        
        // Descargar archivo
        $response = wp_remote_get($download_url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $zip_file
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        // Extraer ZIP
        $unzip_result = unzip_file($zip_file, $vendor_dir);
        if (is_wp_error($unzip_result)) {
            return false;
        }
        
        // Mover archivos a la ubicación correcta
        $extracted_dir = $vendor_dir . 'mpdf-8.2.4/';
        if (file_exists($extracted_dir)) {
            if (!file_exists($mpdf_dir)) {
                wp_mkdir_p(dirname($mpdf_dir));
            }
            self::copiar_directorio($extracted_dir, $mpdf_dir);
            self::eliminar_directorio($extracted_dir);
        }
        
        // Limpiar archivo ZIP
        if (file_exists($zip_file)) {
            unlink($zip_file);
        }
        
        // Crear autoloader básico
        self::crear_autoloader_basico();
        
        return file_exists($mpdf_dir . 'src/Mpdf.php');
    }
    
    /**
     * Copiar directorio recursivamente
     */
    private static function copiar_directorio($src, $dst) {
        $dir = opendir($src);
        wp_mkdir_p($dst);
        
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                if (is_dir($src . '/' . $file)) {
                    self::copiar_directorio($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        
        closedir($dir);
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private static function eliminar_directorio($dir) {
        if (!file_exists($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::eliminar_directorio($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Crear autoloader básico para mPDF
     */
    private static function crear_autoloader_basico() {
        $autoloader_content = '<?php
// Autoloader básico para mPDF
spl_autoload_register(function ($class) {
    if (strpos($class, "Mpdf\\\\") === 0) {
        $file = __DIR__ . "/mpdf/mpdf/src/" . str_replace("\\\\", "/", substr($class, 5)) . ".php";
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
';
        
        $autoloader_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
        file_put_contents($autoloader_path, $autoloader_content);
    }
    
    /**
     * Página de diagnóstico mPDF
     */
    public static function pagina_diagnostico() {
        ?>
        <div class="wrap">
            <h1>Diagnóstico mPDF</h1>
            
            <?php
            $info = self::obtener_info_diagnostico();
            
            echo '<div class="notice ' . ($info['mpdf_disponible'] ? 'notice-success' : 'notice-error') . '">';
            echo '<p><strong>Estado mPDF:</strong> ' . ($info['mpdf_disponible'] ? '✅ Disponible' : '❌ No disponible') . '</p>';
            echo '</div>';
            
            if (!$info['mpdf_disponible']) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>🔧 Instalación Automática</strong></p>';
                echo '<p><button class="button button-primary" onclick="instalarMPDF()">Instalar mPDF Automáticamente</button></p>';
                echo '</div>';
            }
            ?>
            
            <h2>Información del Sistema</h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <?php foreach ($info as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></strong></td>
                        <td><?php echo esc_html(is_bool($value) ? ($value ? 'Sí' : 'No') : $value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <script>
            function instalarMPDF() {
                if (confirm('¿Instalar mPDF automáticamente?')) {
                    window.location.href = '<?php echo admin_url("admin.php?page=mv-mpdf-install"); ?>';
                }
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * Obtener información de diagnóstico
     */
    public static function obtener_info_diagnostico() {
        return array(
            'mpdf_disponible' => self::verificar_mpdf_existente(),
            'composer_disponible' => self::encontrar_composer() !== false,
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Sí' : 'No',
            'curl_available' => function_exists('curl_init') ? 'Sí' : 'No',
            'zip_available' => class_exists('ZipArchive') ? 'Sí' : 'No'
        );
    }
}