<?php
/**
 * Debug para encontrar el error que desactiva el plugin
 * Coloca este archivo en la raíz de WordPress
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Sin permisos');
}

// Activar modo debug completo
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug - Error en Cotizaciones</h1>";
echo "<pre>";

// 1. Verificar estado del plugin
$active_plugins = get_option('active_plugins');
$plugin_file = 'modulo-de-ventas/modulo-ventas.php';

if (in_array($plugin_file, $active_plugins)) {
    echo "✅ Plugin activo\n";
} else {
    echo "❌ Plugin NO activo\n";
}

// 2. Buscar errores recientes en el log
echo "\n=== ÚLTIMOS ERRORES FATALES ===\n";

$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    $log_content = file_get_contents($debug_log);
    $lines = explode("\n", $log_content);
    
    // Buscar errores fatales recientes
    $errores_fatales = [];
    foreach ($lines as $line) {
        if (strpos($line, 'Fatal error') !== false || 
            strpos($line, 'Parse error') !== false ||
            strpos($line, 'modulo-de-ventas') !== false && strpos($line, 'error') !== false) {
            $errores_fatales[] = $line;
        }
    }
    
    // Mostrar últimos 10 errores fatales
    $ultimos_errores = array_slice($errores_fatales, -10);
    foreach ($ultimos_errores as $error) {
        echo $error . "\n";
    }
} else {
    echo "No se encontró debug.log\n";
}

// 3. Verificar archivos problemáticos específicos
echo "\n=== VERIFICANDO ARCHIVOS AJAX ===\n";

$plugin_path = WP_PLUGIN_DIR . '/modulo-de-ventas/';
$archivo_ajax = $plugin_path . 'admin/class-modulo-ventas-ajax.php';

if (file_exists($archivo_ajax)) {
    // Verificar sintaxis
    $output = shell_exec("php -l \"$archivo_ajax\" 2>&1");
    echo "Sintaxis de class-modulo-ventas-ajax.php: ";
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ OK\n";
    } else {
        echo "❌ ERROR\n$output\n";
    }
    
    // Buscar operadores ?? restantes
    $contenido = file_get_contents($archivo_ajax);
    if (strpos($contenido, '??') !== false) {
        echo "⚠️ Aún contiene operadores ??\n";
        
        // Mostrar líneas con ??
        $lineas = explode("\n", $contenido);
        foreach ($lineas as $num => $linea) {
            if (strpos($linea, '??') !== false) {
                echo "  Línea " . ($num + 1) . ": " . trim($linea) . "\n";
            }
        }
    }
}

// 4. Simular creación de cotización para capturar error
echo "\n=== SIMULANDO PROCESO DE COTIZACIÓN ===\n";

try {
    // Verificar si la clase existe
    if (class_exists('Modulo_Ventas_Ajax')) {
        echo "✅ Clase Modulo_Ventas_Ajax existe\n";
        
        // Verificar métodos
        $metodos = get_class_methods('Modulo_Ventas_Ajax');
        if (in_array('guardar_cotizacion', $metodos)) {
            echo "✅ Método guardar_cotizacion existe\n";
        } else {
            echo "❌ Método guardar_cotizacion NO existe\n";
        }
    } else {
        echo "❌ Clase Modulo_Ventas_Ajax NO existe\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

// 5. Verificar helpers.php
echo "\n=== VERIFICANDO HELPERS.PHP ===\n";
$helpers_file = $plugin_path . 'includes/helpers.php';

if (file_exists($helpers_file)) {
    $output = shell_exec("php -l \"$helpers_file\" 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ Sintaxis correcta\n";
    } else {
        echo "❌ Error de sintaxis:\n$output\n";
    }
}

echo "</pre>";

// Agregar botón para limpiar debug.log
?>
<form method="post" style="margin-top: 20px;">
    <input type="hidden" name="clear_debug_log" value="1">
    <button type="submit" class="button">Limpiar debug.log</button>
</form>

<?php
if (isset($_POST['clear_debug_log'])) {
    file_put_contents($debug_log, '');
    echo "<p>✅ debug.log limpiado</p>";
}
?>

<p><a href="<?php echo admin_url('plugins.php'); ?>">Volver a Plugins</a></p>