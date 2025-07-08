<?php
/**
 * Página de diagnóstico para PDF
 * Ubicación: wp-content/plugins/modulo-de-ventas/admin/views/diagnostico-pdf.php
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permisos para acceder a esta página.', 'modulo-ventas'));
}

echo '<div class="wrap">';
echo '<h1>Diagnóstico del Sistema PDF</h1>';

// Test 1: Verificar archivos
echo '<h2>1. Verificación de Archivos</h2>';
echo '<table class="widefat">';
echo '<thead><tr><th>Archivo</th><th>Estado</th><th>Ruta</th></tr></thead>';
echo '<tbody>';

$archivos_check = array(
    'composer.json' => MODULO_VENTAS_PLUGIN_DIR . 'composer.json',
    'vendor/autoload.php' => MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php',
    'vendor/tecnickcom/tcpdf/tcpdf.php' => MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php',
    'class-modulo-ventas-pdf.php' => MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf.php',
    'class-modulo-ventas-pdf-handler.php' => MODULO_VENTAS_PLUGIN_DIR . 'includes/class-modulo-ventas-pdf-handler.php'
);

foreach ($archivos_check as $nombre => $ruta) {
    $existe = file_exists($ruta);
    echo '<tr>';
    echo '<td>' . esc_html($nombre) . '</td>';
    echo '<td>' . ($existe ? '<span style="color:green;">✓ Existe</span>' : '<span style="color:red;">✗ No existe</span>') . '</td>';
    echo '<td><code>' . esc_html($ruta) . '</code></td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Test 2: Verificar clases
echo '<h2>2. Verificación de Clases</h2>';
echo '<table class="widefat">';
echo '<thead><tr><th>Clase</th><th>Estado</th><th>Información</th></tr></thead>';
echo '<tbody>';

$clases_check = array(
    'TCPDF' => 'Biblioteca para generar PDFs',
    'Modulo_Ventas_PDF' => 'Clase principal del generador de PDF',
    'Modulo_Ventas_PDF_Handler' => 'Manejador de descargas y visualización'
);

foreach ($clases_check as $clase => $descripcion) {
    $existe = class_exists($clase);
    echo '<tr>';
    echo '<td><code>' . esc_html($clase) . '</code></td>';
    echo '<td>' . ($existe ? '<span style="color:green;">✓ Disponible</span>' : '<span style="color:red;">✗ No disponible</span>') . '</td>';
    echo '<td>' . esc_html($descripcion) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Test 3: Verificar funciones
echo '<h2>3. Verificación de Funciones</h2>';
echo '<table class="widefat">';
echo '<thead><tr><th>Función</th><th>Estado</th><th>Descripción</th></tr></thead>';
echo '<tbody>';

$funciones_check = array(
    'mv_get_pdf_config' => 'Obtener configuración de PDF',
    'mv_upload_logo' => 'Subir logo de empresa',
    'mv_get_logo_path' => 'Obtener ruta del logo'
);

foreach ($funciones_check as $funcion => $descripcion) {
    $existe = function_exists($funcion);
    echo '<tr>';
    echo '<td><code>' . esc_html($funcion) . '</code></td>';
    echo '<td>' . ($existe ? '<span style="color:green;">✓ Disponible</span>' : '<span style="color:red;">✗ No disponible</span>') . '</td>';
    echo '<td>' . esc_html($descripcion) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Test 4: Permisos de directorio
echo '<h2>4. Verificación de Permisos</h2>';
$upload_dir = wp_upload_dir();
$pdf_dir = $upload_dir['basedir'] . '/modulo-ventas/pdfs';

echo '<table class="widefat">';
echo '<thead><tr><th>Directorio</th><th>Existe</th><th>Escribible</th><th>Ruta</th></tr></thead>';
echo '<tbody>';

$directorios_check = array(
    'Upload base' => $upload_dir['basedir'],
    'Modulo Ventas' => $upload_dir['basedir'] . '/modulo-ventas',
    'PDFs' => $pdf_dir
);

foreach ($directorios_check as $nombre => $ruta) {
    $existe = file_exists($ruta);
    $escribible = is_writable($ruta);
    
    echo '<tr>';
    echo '<td>' . esc_html($nombre) . '</td>';
    echo '<td>' . ($existe ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗</span>') . '</td>';
    echo '<td>' . ($escribible ? '<span style="color:green;">✓</span>' : '<span style="color:red;">✗</span>') . '</td>';
    echo '<td><code>' . esc_html($ruta) . '</code></td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Test 5: Test avanzado de TCPDF
echo '<h2>5. Test Avanzado de TCPDF</h2>';

// Intentar múltiples formas de cargar TCPDF
$tcpdf_cargado = false;
$metodo_carga = '';

// Método 1: Autoload
if (!class_exists('TCPDF')) {
    $autoload_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php';
    if (file_exists($autoload_path)) {
        require_once $autoload_path;
        if (class_exists('TCPDF')) {
            $tcpdf_cargado = true;
            $metodo_carga = 'Autoload Composer';
        }
    }
}

// Método 2: Carga directa
if (!$tcpdf_cargado) {
    $tcpdf_path = MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
        if (class_exists('TCPDF')) {
            $tcpdf_cargado = true;
            $metodo_carga = 'Carga directa';
        }
    }
}

if ($tcpdf_cargado) {
    echo '<p style="color:green;">✓ TCPDF cargado exitosamente (' . esc_html($metodo_carga) . ')</p>';
    
    try {
        // Test de instanciación
        $pdf = new TCPDF();
        echo '<p style="color:green;">✓ TCPDF se puede instanciar correctamente</p>';
        
        // Test básico de contenido
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Test PDF - ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        
        echo '<p style="color:green;">✓ Se puede crear una página y agregar contenido</p>';
        
        // Test de salida
        $test_content = $pdf->Output('', 'S');
        if (strlen($test_content) > 1000) {
            echo '<p style="color:green;">✓ PDF se genera correctamente (' . number_format(strlen($test_content)) . ' bytes)</p>';
        } else {
            echo '<p style="color:orange;">⚠ PDF generado pero parece incompleto</p>';
        }
        
        // Mostrar información de TCPDF
        echo '<div style="background:#f0f8ff;padding:10px;border:1px solid #ccc;margin:10px 0;">';
        echo '<strong>Información de TCPDF:</strong><br>';
        echo '• Versión: ' . (defined('TCPDF_VERSION') ? TCPDF_VERSION : 'No disponible') . '<br>';
        echo '• Ruta cargada: ' . (class_exists('TCPDF') ? 'Disponible' : 'No disponible') . '<br>';
        echo '• PDF Core: ' . (defined('PDF_PRODUCER') ? PDF_PRODUCER : 'TCPDF') . '<br>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<p style="color:red;">✗ Error al probar TCPDF: ' . esc_html($e->getMessage()) . '</p>';
        echo '<details><summary>Detalles del error</summary><pre>' . esc_html($e->getTraceAsString()) . '</pre></details>';
    }
    
} else {
    echo '<p style="color:red;">✗ TCPDF no se pudo cargar</p>';
    echo '<div style="background:#ffe6e6;padding:10px;border:1px solid #ff9999;margin:10px 0;">';
    echo '<strong>Rutas verificadas:</strong><br>';
    echo '• Autoload: ' . MODULO_VENTAS_PLUGIN_DIR . 'vendor/autoload.php<br>';
    echo '• TCPDF directo: ' . MODULO_VENTAS_PLUGIN_DIR . 'vendor/tecnickcom/tcpdf/tcpdf.php<br>';
    echo '<br><strong>Soluciones:</strong><br>';
    echo '1. Ejecutar: <code>cd ' . MODULO_VENTAS_PLUGIN_DIR . ' && composer install</code><br>';
    echo '2. Verificar permisos de archivos<br>';
    echo '3. Verificar que PHP puede incluir archivos de vendor/';
    echo '</div>';
}

// Test 5.5: Test con la clase del plugin
echo '<h3>5.1 Test con Modulo_Ventas_PDF</h3>';
try {
    $pdf_generator = new Modulo_Ventas_PDF();
    echo '<p style="color:green;">✓ Modulo_Ventas_PDF se instancia correctamente</p>';
    
    // Test básico (sin cotización real)
    if (method_exists($pdf_generator, 'verificar_tcpdf')) {
        $reflection = new ReflectionClass($pdf_generator);
        $method = $reflection->getMethod('verificar_tcpdf');
        $method->setAccessible(true);
        $tcpdf_ok = $method->invoke($pdf_generator);
        
        if ($tcpdf_ok) {
            echo '<p style="color:green;">✓ Verificación interna de TCPDF exitosa</p>';
        } else {
            echo '<p style="color:red;">✗ Verificación interna de TCPDF falló</p>';
        }
    }
    
} catch (Exception $e) {
    echo '<p style="color:red;">✗ Error al instanciar Modulo_Ventas_PDF: ' . esc_html($e->getMessage()) . '</p>';
}

// Test 6: Configuración actual
echo '<h2>6. Configuración Actual</h2>';
$config = get_option('mv_pdf_config', array());

if (empty($config)) {
    echo '<p style="color:orange;">⚠ No hay configuración guardada aún</p>';
} else {
    echo '<pre style="background:#f5f5f5;padding:10px;overflow:auto;">';
    print_r($config);
    echo '</pre>';
}

// Botones de acción - VERSIÓN ACTUALIZADA
echo '<h2>7. Acciones de Reparación</h2>';
echo '<p>';
echo '<a href="' . admin_url('admin.php?page=modulo-ventas-config-pdf') . '" class="button button-primary">Ir a Configuración PDF</a> ';
echo '<button type="button" id="crear-directorios" class="button">Crear Directorios</button> ';
echo '<button type="button" id="test-pdf-simple" class="button button-secondary">Test PDF Simple</button> ';
echo '<button type="button" id="recargar-tcpdf" class="button">Recargar TCPDF</button> ';
echo '<button type="button" id="test-cotizacion-demo" class="button">Test Cotización Demo</button>';
echo '</p>';

echo '</div>';

// JavaScript para acciones
?>
<script>
jQuery(document).ready(function($) {
    // Debug: verificar que ajaxurl existe
    console.log('AJAX URL:', ajaxurl);
    console.log('Nonce disponible:', '<?php echo wp_create_nonce('mv_diagnostico'); ?>');
    
    $('#test-pdf-simple').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Probando...');
        
        console.log('Iniciando test PDF simple...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mv_test_pdf_simple',
                nonce: '<?php echo wp_create_nonce('mv_diagnostico'); ?>'
            },
            success: function(response) {
                console.log('Respuesta AJAX exitosa:', response);
                $btn.prop('disabled', false).text('Test PDF Simple');
                
                if (response.success) {
                    alert('Test PDF exitoso!\n\n' + response.data.message + '\n\nTamaño: ' + response.data.file_size);
                    
                    // Abrir PDF si está disponible
                    if (response.data.file_url) {
                        console.log('Abriendo PDF:', response.data.file_url);
                        window.open(response.data.file_url, '_blank');
                    }
                } else {
                    console.error('Error en respuesta:', response.data);
                    var errorMsg = 'Error en test PDF:\n\n';
                    if (response.data && response.data.message) {
                        errorMsg += response.data.message;
                        if (response.data.file && response.data.line) {
                            errorMsg += '\n\nArchivo: ' + response.data.file + '\nLínea: ' + response.data.line;
                        }
                    } else {
                        errorMsg += 'Error desconocido';
                    }
                    alert(errorMsg);
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                console.error('Error AJAX:', {
                    xhr: xhr,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    responseText: xhr.responseText
                });
                
                $btn.prop('disabled', false).text('Test PDF Simple');
                
                var errorMsg = 'Error de conexión AJAX:\n';
                errorMsg += 'Estado: ' + textStatus + '\n';
                errorMsg += 'Error: ' + errorThrown + '\n';
                
                if (xhr.responseText) {
                    errorMsg += '\nRespuesta del servidor:\n' + xhr.responseText.substring(0, 200);
                }
                
                alert(errorMsg);
            },
            timeout: 30000 // 30 segundos de timeout
        });
    });
    
    $('#crear-directorios').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Creando...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mv_crear_directorios_pdf',
                nonce: '<?php echo wp_create_nonce('mv_diagnostico'); ?>'
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Crear Directorios');
                console.log('Crear directorios response:', response);
                
                if (response.success) {
                    alert('Directorios procesados:\n\n' + response.data.detalles.join('\n'));
                    location.reload();
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Error desconocido'));
                }
            },
            error: function(xhr, textStatus, errorThrown) {
                console.error('Error crear directorios:', xhr.responseText);
                $btn.prop('disabled', false).text('Crear Directorios');
                alert('Error de conexión: ' + textStatus);
            }
        });
    });
    
    $('#test-cotizacion-demo').on('click', function() {
        alert('Test de cotización demo temporalmente deshabilitado para debug');
    });
    
    $('#recargar-tcpdf').on('click', function() {
        location.reload();
    });
});
</script>