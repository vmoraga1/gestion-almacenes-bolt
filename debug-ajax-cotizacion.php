<?php
/**
 * Debug AJAX para encontrar el error al crear cotizaciones
 * Guarda este archivo como debug-ajax-cotizacion.php en la raíz de WordPress
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('Sin permisos');
}

// Configurar reporte de errores completo
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug AJAX Cotización</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Debug AJAX - Crear Cotización</h1>
    
    <div id="resultado"></div>
    
    <h2>Formulario de Prueba</h2>
    <form id="test-cotizacion">
        <p>
            <label>Cliente ID:</label>
            <input type="number" name="cliente_id" value="1" required>
        </p>
        <p>
            <label>Vendedor ID:</label>
            <input type="number" name="vendedor_id" value="<?php echo get_current_user_id(); ?>" required>
        </p>
        <p>
            <button type="submit">Probar Guardar Cotización</button>
        </p>
    </form>
    
    <h2>Log de Proceso</h2>
    <pre id="log" style="background: #f0f0f0; padding: 10px; min-height: 200px;"></pre>
    
    <script>
    jQuery(document).ready(function($) {
        function log(mensaje) {
            $('#log').append(new Date().toTimeString().split(' ')[0] + ' - ' + mensaje + '\n');
        }
        
        $('#test-cotizacion').on('submit', function(e) {
            e.preventDefault();
            
            log('Iniciando prueba de guardado...');
            
            // Datos mínimos para crear una cotización
            var datos = {
                action: 'mv_guardar_cotizacion',
                nonce: '<?php echo wp_create_nonce('mv_guardar_cotizacion'); ?>',
                cliente_id: $('input[name="cliente_id"]').val(),
                vendedor_id: $('input[name="vendedor_id"]').val(),
                items: JSON.stringify([{
                    producto_id: 1,
                    cantidad: 1,
                    precio_unitario: 1000,
                    subtotal: 1000
                }]),
                descuento_global: 0,
                descuento_tipo: 'monto',
                observaciones: 'Prueba de debug',
                notas_internas: '',
                terminos_condiciones: ''
            };
            
            log('Datos a enviar: ' + JSON.stringify(datos, null, 2));
            
            // Hacer la petición AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: datos,
                dataType: 'json',
                success: function(response) {
                    log('Respuesta exitosa:');
                    log(JSON.stringify(response, null, 2));
                    
                    if (response.success) {
                        $('#resultado').html('<p style="color: green;">✅ Cotización creada con ID: ' + response.data.cotizacion_id + '</p>');
                    } else {
                        $('#resultado').html('<p style="color: red;">❌ Error: ' + response.data.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    log('ERROR en la petición:');
                    log('Status: ' + status);
                    log('Error: ' + error);
                    log('Response Text: ' + xhr.responseText);
                    
                    $('#resultado').html('<p style="color: red;">❌ Error AJAX: ' + error + '</p>');
                    
                    // Intentar parsear el error
                    if (xhr.responseText) {
                        $('#resultado').append('<div style="background: #ffeeee; padding: 10px; margin-top: 10px;"><strong>Respuesta del servidor:</strong><br>' + xhr.responseText + '</div>');
                    }
                }
            });
        });
    });
    </script>
    
    <hr>
    
    <h2>Verificación Manual del Handler AJAX</h2>
    <?php
    // Verificar si el handler AJAX está registrado
    global $wp_filter;
    
    $ajax_actions = ['wp_ajax_mv_guardar_cotizacion', 'wp_ajax_nopriv_mv_guardar_cotizacion'];
    
    foreach ($ajax_actions as $action) {
        echo "<h3>$action:</h3>";
        if (isset($wp_filter[$action])) {
            echo "<pre>";
            foreach ($wp_filter[$action]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    echo "Prioridad $priority: ";
                    if (is_array($callback['function'])) {
                        echo get_class($callback['function'][0]) . '::' . $callback['function'][1];
                    } else {
                        echo $callback['function'];
                    }
                    echo "\n";
                }
            }
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>❌ No registrado</p>";
        }
    }
    ?>
    
    <hr>
    
    <h2>Estado del Plugin</h2>
    <?php
    $active = is_plugin_active('modulo-de-ventas/modulo-ventas.php');
    echo $active ? '<p style="color: green;">✅ Plugin activo</p>' : '<p style="color: red;">❌ Plugin inactivo</p>';
    
    // Verificar clases
    echo '<h3>Clases disponibles:</h3>';
    $clases = ['Modulo_Ventas', 'Modulo_Ventas_Ajax', 'Modulo_Ventas_Admin'];
    foreach ($clases as $clase) {
        echo $clase . ': ' . (class_exists($clase) ? '✅' : '❌') . '<br>';
    }
    ?>
    
    <p><a href="<?php echo admin_url('plugins.php'); ?>">Volver a Plugins</a></p>
</body>
</html>