<?php
/**
 * Script para forzar la visualización de errores
 * Guárdalo como test-plugin-error.php en la raíz de WordPress
 */

// Configurar reporte de errores completo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Definir constantes de WordPress necesarias
define('WP_USE_THEMES', false);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);

// Cargar WordPress
require_once('wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Sin permisos');
}

echo "<h1>Test de Carga del Plugin</h1>";
echo "<pre>";

// Información básica
echo "PHP Version: " . phpversion() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

// Definir constantes del plugin si no existen
if (!defined('MODULO_VENTAS_PLUGIN_FILE')) {
    define('MODULO_VENTAS_PLUGIN_FILE', WP_PLUGIN_DIR . '/modulo-de-ventas/modulo-ventas.php');
    define('MODULO_VENTAS_PLUGIN_DIR', WP_PLUGIN_DIR . '/modulo-de-ventas/');
    define('MODULO_VENTAS_PLUGIN_URL', plugins_url('modulo-de-ventas/'));
}

echo "=== INTENTANDO CARGAR EL PLUGIN MANUALMENTE ===\n\n";

// Registrar un error handler personalizado
$errores_capturados = array();
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errores_capturados) {
    $errores_capturados[] = array(
        'tipo' => $errno,
        'mensaje' => $errstr,
        'archivo' => str_replace(ABSPATH, '', $errfile),
        'linea' => $errline
    );
    return true; // Prevenir el handler por defecto
});

// Registrar shutdown function para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n=== ERROR FATAL CAPTURADO ===\n";
        echo "Tipo: " . $error['type'] . "\n";
        echo "Mensaje: " . $error['message'] . "\n";
        echo "Archivo: " . str_replace(ABSPATH, '', $error['file']) . "\n";
        echo "Línea: " . $error['line'] . "\n";
    }
});

// Intentar cargar el plugin
try {
    echo "Cargando archivo principal del plugin...\n";

    // Verificar específicamente el archivo admin
    echo "\n=== VERIFICANDO ARCHIVO ADMIN ===\n";
    $admin_file = MODULO_VENTAS_PLUGIN_DIR . 'admin/class-modulo-ventas-admin.php';

    if (file_exists($admin_file)) {
        echo "Archivo existe: SÍ\n";
        
        // Verificar sintaxis con PHP
        $output = shell_exec("php -l \"$admin_file\" 2>&1");
        echo "Verificación de sintaxis:\n$output\n";
        
        // Intentar incluir el archivo
        echo "Intentando incluir el archivo...\n";
        
        ob_start();
        $error = null;
        try {
            require_once $admin_file;
            echo "✅ Archivo incluido sin errores\n";
        } catch (ParseError $e) {
            $error = "Parse Error: " . $e->getMessage() . " en línea " . $e->getLine();
        } catch (Error $e) {
            $error = "Error: " . $e->getMessage() . " en línea " . $e->getLine();
        } catch (Exception $e) {
            $error = "Exception: " . $e->getMessage() . " en línea " . $e->getLine();
        }
        $output = ob_get_clean();
        
        if ($error) {
            echo "❌ ERROR: $error\n";
        }
        if ($output) {
            echo "Output: $output\n";
        }
    } else {
        echo "❌ El archivo NO existe\n";
    }
    
    if (!file_exists(MODULO_VENTAS_PLUGIN_FILE)) {
        echo "ERROR: No se encuentra el archivo del plugin\n";
    } else {
        // Incluir el archivo
        ob_start();
        $resultado = include_once(MODULO_VENTAS_PLUGIN_FILE);
        $output = ob_get_clean();
        
        if ($output) {
            echo "Output capturado:\n$output\n";
        }
        
        echo "Archivo cargado: " . ($resultado ? "SÍ" : "NO") . "\n\n";
        
        // Verificar si la clase principal existe
        if (class_exists('Modulo_Ventas')) {
            echo "✅ Clase Modulo_Ventas existe\n";
            
            // Intentar obtener instancia
            echo "\nIntentando obtener instancia...\n";
            $instancia = Modulo_Ventas::get_instance();
            
            if ($instancia) {
                echo "✅ Instancia obtenida correctamente\n";
            } else {
                echo "❌ No se pudo obtener instancia\n";
            }
        } else {
            echo "❌ Clase Modulo_Ventas NO existe\n";
        }
        
        // Verificar otras clases
        echo "\n=== VERIFICANDO CLASES ===\n";
        $clases = [
            'Modulo_Ventas_DB',
            'Modulo_Ventas_Admin', 
            'Modulo_Ventas_Ajax',
            'Modulo_Ventas_Cotizaciones',
            'Modulo_Ventas_Clientes',
            'Modulo_Ventas_Messages'
        ];
        
        foreach ($clases as $clase) {
            echo "$clase: " . (class_exists($clase) ? "✅ Existe" : "❌ NO existe") . "\n";
        }
    }
    
} catch (ParseError $e) {
    echo "ERROR DE SINTAXIS:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . str_replace(ABSPATH, '', $e->getFile()) . "\n";
    echo "Línea: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "ERROR FATAL:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . str_replace(ABSPATH, '', $e->getFile()) . "\n";
    echo "Línea: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "EXCEPCIÓN:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . str_replace(ABSPATH, '', $e->getFile()) . "\n";
    echo "Línea: " . $e->getLine() . "\n";
}

// Mostrar errores capturados
if (!empty($errores_capturados)) {
    echo "\n=== ERRORES CAPTURADOS ===\n";
    foreach ($errores_capturados as $error) {
        echo sprintf(
            "[%s] %s en %s:%d\n",
            $error['tipo'],
            $error['mensaje'],
            $error['archivo'],
            $error['linea']
        );
    }
}

// Verificar si el plugin está en la lista de activos
echo "\n=== ESTADO DEL PLUGIN ===\n";
$active_plugins = get_option('active_plugins');
$plugin_file = 'modulo-de-ventas/modulo-ventas.php';

if (in_array($plugin_file, $active_plugins)) {
    echo "El plugin está en la lista de activos\n";
    
    // Verificar si WordPress lo desactivó por error
    $recently_activated = get_option('recently_activated', array());
    if (isset($recently_activated[$plugin_file])) {
        echo "El plugin fue desactivado recientemente\n";
        echo "Tiempo de desactivación: " . date('Y-m-d H:i:s', $recently_activated[$plugin_file]) . "\n";
    }
} else {
    echo "El plugin NO está activo\n";
}

echo "</pre>";

// Intentar activar el plugin si no está activo
if (!in_array($plugin_file, $active_plugins)) {
    echo '<form method="post" style="margin-top: 20px;">';
    echo '<input type="hidden" name="activate_plugin" value="1">';
    echo '<button type="submit" class="button button-primary">Intentar Activar Plugin</button>';
    echo '</form>';
    
    if (isset($_POST['activate_plugin'])) {
        echo "<h3>Intentando activar el plugin...</h3><pre>";
        
        // Capturar cualquier output
        ob_start();
        $resultado = activate_plugin($plugin_file);
        $output = ob_get_clean();
        
        if (is_wp_error($resultado)) {
            echo "ERROR al activar: " . $resultado->get_error_message() . "\n";
        } else {
            echo "Plugin activado correctamente\n";
        }
        
        if ($output) {
            echo "Output: $output\n";
        }
        
        echo "</pre>";
    }
}
?>

<p><a href="<?php echo admin_url('plugins.php'); ?>">Volver a Plugins</a></p>