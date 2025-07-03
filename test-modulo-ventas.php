<?php
// test-modulo-ventas.php
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Sin permisos');
}

// Verificar si el plugin está activo
$active = is_plugin_active('modulo-de-ventas/modulo-ventas.php');
echo "Plugin activo: " . ($active ? 'SÍ' : 'NO') . "<br>";

// Si está activo, verificar las páginas del menú
global $submenu;
if (isset($submenu['modulo-ventas'])) {
    echo "<h3>Páginas del menú:</h3>";
    echo "<pre>";
    print_r($submenu['modulo-ventas']);
    echo "</pre>";
}

// Verificar si la clase Admin existe
echo "<h3>Clases:</h3>";
echo "Modulo_Ventas_Admin: " . (class_exists('Modulo_Ventas_Admin') ? 'Existe' : 'NO existe') . "<br>";

// Intentar acceder directamente a la página
if (class_exists('Modulo_Ventas_Admin')) {
    $admin = new Modulo_Ventas_Admin();
    
    echo "<h3>Intentando cargar página nueva cotización:</h3>";
    ob_start();
    try {
        $admin->pagina_nueva_cotizacion();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    } catch (Error $e) {
        echo "Error fatal: " . $e->getMessage();
    }
    $output = ob_get_clean();
    
    if (strlen($output) > 100) {
        echo "Página cargada (primeros 100 caracteres): " . substr($output, 0, 100) . "...";
    } else {
        echo "Output: " . $output;
    }
}
?>