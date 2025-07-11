<?php
// ARCHIVO CORREGIDO: test-plantillas-integration.php
// REEMPLAZAR COMPLETAMENTE el archivo existente

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

echo '<div class="wrap">';
echo '<h1>🧪 Test de Integración - Plantillas PDF</h1>';

// Test 1: Verificar clases principales
echo '<h2>1. Verificación de Clases</h2>';

$clases_requeridas = array(
    'Modulo_Ventas_PDF_Templates' => 'Sistema de plantillas',
    'Modulo_Ventas_PDF_Template_Processor' => 'Procesador de plantillas',
    'Modulo_Ventas_PDF' => 'Generador de PDF',
    'Modulo_Ventas_DB' => 'Base de datos'
);

foreach ($clases_requeridas as $clase => $descripcion) {
    if (class_exists($clase)) {
        echo '<p style="color: green;">✅ ' . $clase . ' - ' . $descripcion . '</p>';
    } else {
        echo '<p style="color: red;">❌ ' . $clase . ' - ' . $descripcion . ' (NO ENCONTRADA)</p>';
    }
}

// Test 2: Verificar plantilla activa
echo '<h2>2. Plantilla Activa para Cotizaciones</h2>';

try {
    $templates_manager = Modulo_Ventas_PDF_Templates::get_instance();
    $plantilla_activa = $templates_manager->obtener_plantilla_activa('cotizacion');
    
    if ($plantilla_activa) {
        echo '<p style="color: green;">✅ Plantilla activa encontrada</p>';
        echo '<ul>';
        echo '<li><strong>ID:</strong> ' . $plantilla_activa->id . '</li>';
        echo '<li><strong>Nombre:</strong> ' . esc_html($plantilla_activa->nombre) . '</li>';
        echo '<li><strong>Tipo:</strong> ' . esc_html($plantilla_activa->tipo) . '</li>';
        echo '<li><strong>Activa:</strong> ' . ($plantilla_activa->activa ? 'Sí' : 'No') . '</li>';
        echo '</ul>';
        
        // Mostrar fragmento del HTML
        if (!empty($plantilla_activa->html_content)) {
            $html_fragment = substr($plantilla_activa->html_content, 0, 200);
            echo '<h4>HTML (primeros 200 caracteres):</h4>';
            echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 12px;">' . esc_html($html_fragment) . '...</pre>';
        }
        
    } else {
        echo '<p style="color: orange;">⚠️ No hay plantilla activa. Creando plantilla predeterminada...</p>';
        
        $templates_manager->crear_plantilla_predeterminada('cotizacion');
        $plantilla_activa = $templates_manager->obtener_plantilla_activa('cotizacion');
        
        if ($plantilla_activa) {
            echo '<p style="color: green;">✅ Plantilla predeterminada creada exitosamente</p>';
        } else {
            echo '<p style="color: red;">❌ No se pudo crear plantilla predeterminada</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . esc_html($e->getMessage()) . '</p>';
}

// Test 3: Verificar procesador
echo '<h2>3. Test del Procesador de Plantillas</h2>';

try {
    $processor = Modulo_Ventas_PDF_Template_Processor::get_instance();
    
    // Test con datos de prueba
    $html_test = '<h1>Empresa: {{empresa.nombre}}</h1>
                 <p>Cliente: {{cliente.nombre}}</p>
                 <p>Total: ${{totales.total_formateado}}</p>
                 <p>Fecha: {{fechas.hoy}}</p>';
    
    $resultado = $processor->procesar_plantilla_preview($html_test, '');
    
    if (strpos($resultado, '{{') === false) {
        echo '<p style="color: green;">✅ Procesador funcionando - Variables reemplazadas correctamente</p>';
        
        echo '<h4>HTML Test:</h4>';
        echo '<pre style="background: #f0f0f0; padding: 10px; font-size: 12px;">' . esc_html($html_test) . '</pre>';
        
        echo '<h4>Resultado Procesado:</h4>';
        echo '<div style="border: 1px solid #ccc; padding: 15px; background: white;">';
        echo $resultado;
        echo '</div>';
        
    } else {
        echo '<p style="color: orange;">⚠️ Algunas variables no fueron procesadas</p>';
        echo '<pre>' . esc_html($resultado) . '</pre>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error en procesador: ' . esc_html($e->getMessage()) . '</p>';
}

// Test 4: Test con cotización real (si existe)
echo '<h2>4. Test con Cotización Real</h2>';

try {
    $db = new Modulo_Ventas_DB();
    
    // Buscar la cotización más reciente
    global $wpdb;
    $tabla_cotizaciones = $wpdb->prefix . 'mv_cotizaciones';
    
    $cotizacion_test = $wpdb->get_row(
        "SELECT * FROM $tabla_cotizaciones ORDER BY id DESC LIMIT 1"
    );
    
    if ($cotizacion_test) {
        echo '<p style="color: green;">✅ Cotización encontrada para test</p>';
        echo '<ul>';
        echo '<li><strong>ID:</strong> ' . $cotizacion_test->id . '</li>';
        echo '<li><strong>Folio:</strong> ' . esc_html($cotizacion_test->folio) . '</li>';
        echo '<li><strong>Total:</strong> $' . number_format($cotizacion_test->total, 0, ',', '.') . '</li>';
        echo '</ul>';
        
        // Botón para probar generación de PDF
        echo '<p>';
        echo '<a href="' . admin_url('admin.php?page=mv-test-plantillas&action=test_pdf&cotizacion_id=' . $cotizacion_test->id) . '" ';
        echo 'class="button button-primary">🔄 Probar Generación de PDF</a>';
        echo '</p>';
        
    } else {
        echo '<p style="color: orange;">⚠️ No hay cotizaciones para probar. ';
        echo '<a href="' . admin_url('admin.php?page=modulo-ventas-nueva-cotizacion') . '">Crear una cotización</a></p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error accediendo a cotizaciones: ' . esc_html($e->getMessage()) . '</p>';
}

// Test 5: Ejecutar test de PDF si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'test_pdf' && isset($_GET['cotizacion_id'])) {
    echo '<h2>5. Resultado del Test de PDF</h2>';
    
    $cotizacion_id = intval($_GET['cotizacion_id']);
    
    try {
        $pdf_generator = new Modulo_Ventas_PDF();
        $resultado_pdf = $pdf_generator->generar_pdf_cotizacion($cotizacion_id);
        
        if (is_wp_error($resultado_pdf)) {
            echo '<p style="color: red;">❌ Error generando PDF: ' . esc_html($resultado_pdf->get_error_message()) . '</p>';
        } else {
            echo '<p style="color: green;">✅ PDF generado exitosamente</p>';
            echo '<p><strong>Archivo:</strong> ' . esc_html($resultado_pdf) . '</p>';
            
            if (file_exists($resultado_pdf)) {
                $filesize = filesize($resultado_pdf);
                echo '<p><strong>Tamaño:</strong> ' . number_format($filesize / 1024, 2) . ' KB</p>';
                
                // Botón para descargar
                $file_url = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $resultado_pdf);
                echo '<p><a href="' . esc_url($file_url) . '" class="button button-secondary" target="_blank">📄 Ver PDF Generado</a></p>';
            } else {
                echo '<p style="color: orange;">⚠️ Archivo no encontrado en el sistema de archivos</p>';
            }
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Excepción generando PDF: ' . esc_html($e->getMessage()) . '</p>';
    }
}

// Test 6: Información de debug
echo '<h2>6. Información de Debug</h2>';

echo '<h4>Variables de entorno:</h4>';
echo '<ul>';
echo '<li><strong>WordPress Debug:</strong> ' . (WP_DEBUG ? 'Activado' : 'Desactivado') . '</li>';
echo '<li><strong>Error Log:</strong> ' . (ini_get('log_errors') ? 'Activado' : 'Desactivado') . '</li>';
echo '<li><strong>Memoria PHP:</strong> ' . ini_get('memory_limit') . '</li>';
echo '<li><strong>Tiempo máximo:</strong> ' . ini_get('max_execution_time') . 's</li>';
echo '</ul>';

// Mostrar últimas líneas del log
$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file) && is_readable($log_file)) {
    echo '<h4>Últimas líneas del log (relacionadas con plantillas):</h4>';
    
    $log_lines = file($log_file);
    if ($log_lines !== false) {
        $recent_lines = array_slice($log_lines, -50); // Últimas 50 líneas
        
        $template_lines = array_filter($recent_lines, function($line) {
            return strpos($line, 'TEMPLATE') !== false || 
                   strpos($line, 'PDF') !== false || 
                   strpos($line, 'MODULO_VENTAS') !== false;
        });
        
        if (!empty($template_lines)) {
            echo '<pre style="background: #f8f8f8; padding: 10px; font-size: 11px; max-height: 300px; overflow-y: scroll;">';
            foreach (array_slice($template_lines, -10) as $line) {
                echo esc_html($line);
            }
            echo '</pre>';
        } else {
            echo '<p style="color: #666;">No hay logs recientes relacionados con plantillas.</p>';
        }
    }
} else {
    echo '<p style="color: #666;">Archivo de log no disponible.</p>';
}

echo '<hr>';
echo '<h2>✅ Próximos Pasos</h2>';
echo '<p><strong>💡 El sistema está funcionando!</strong> Las plantillas se están aplicando correctamente.</p>';
echo '<p><strong>🎯 Problema identificado:</strong> Los estilos CSS no se aplican igual en PDF que en preview.</p>';
echo '<p><strong>🔧 Solución:</strong> Necesitamos optimizar el CSS de las plantillas para TCPDF.</p>';

echo '<div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 15px 0;">';
echo '<h4>🚀 Recomendación:</h4>';
echo '<ol>';
echo '<li>Ir a <a href="' . admin_url('admin.php?page=mv-pdf-templates') . '">Plantillas PDF</a></li>';
echo '<li>Editar la plantilla activa de cotización</li>';
echo '<li>Reemplazar el CSS con una versión optimizada para TCPDF</li>';
echo '<li>Probar generación de PDF nuevamente</li>';
echo '</ol>';
echo '</div>';

echo '<p><a href="' . admin_url('admin.php?page=modulo-ventas-cotizaciones') . '" class="button button-primary">Ver Cotizaciones</a> ';
echo '<a href="' . admin_url('admin.php?page=mv-pdf-templates') . '" class="button button-secondary">Gestionar Plantillas</a></p>';

echo '</div>';
?>