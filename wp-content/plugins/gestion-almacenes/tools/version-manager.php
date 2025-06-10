#!/usr/bin/env php
<?php
/**
 * CLI para gestión de versiones
 * Archivo: tools/version-manager.php
 * 
 * Uso: php version-manager.php [comando] [argumentos]
 * Comandos:
 *   bump patch|minor|major "descripción"
 *   changelog
 *   release
 */

// Cargar WordPress
$wp_load_paths = array(
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../wp-load.php',
);

foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Verificar que WordPress se cargó
if (!defined('ABSPATH')) {
    die("Error: No se pudo cargar WordPress\n");
}

// Cargar el gestor de versiones
require_once __DIR__ . '/../includes/class-gestion-almacenes-version-manager.php';

class GAB_Version_CLI {
    
    private $version_manager;
    
    public function __construct() {
        $this->version_manager = new Gestion_Almacenes_Version_Manager();
    }
    
    public function run($args) {
        if (count($args) < 2) {
            $this->show_help();
            return;
        }
        
        $command = $args[1];
        
        switch ($command) {
            case 'bump':
                $this->bump_version($args);
                break;
                
            case 'changelog':
                $this->show_changelog();
                break;
                
            case 'release':
                $this->create_release();
                break;
                
            case 'add-change':
                $this->add_change($args);
                break;
                
            default:
                $this->show_help();
        }
    }
    
    private function bump_version($args) {
        if (count($args) < 3) {
            echo "Uso: php version-manager.php bump [patch|minor|major] [descripción]\n";
            return;
        }
        
        $type = $args[2];
        $description = isset($args[3]) ? $args[3] : '';
        
        if (!in_array($type, ['patch', 'minor', 'major'])) {
            echo "Error: Tipo debe ser patch, minor o major\n";
            return;
        }
        
        $new_version = $this->version_manager->incrementar_version($type, $description);
        
        echo "✅ Versión actualizada a: $new_version\n";
        echo "📝 Changelog actualizado\n";
        
        // Mostrar cambios pendientes incluidos
        $changes = get_option('gab_pending_changes', array());
        if (!empty($changes)) {
            echo "\nCambios incluidos en esta versión:\n";
            foreach ($changes as $change) {
                echo "  - {$change['type']}: {$change['description']}\n";
            }
        }
    }
    
    private function add_change($args) {
        if (count($args) < 4) {
            echo "Uso: php version-manager.php add-change [tipo] [descripción]\n";
            echo "Tipos: Added, Changed, Fixed, Removed, Security\n";
            return;
        }
        
        $type = $args[2];
        $description = $args[3];
        
        $this->version_manager->registrar_cambio_pendiente($type, $description);
        
        echo "✅ Cambio registrado para el próximo release\n";
    }
    
    private function show_changelog() {
        $changelog_file = GESTION_ALMACENES_PLUGIN_DIR . 'CHANGELOG.md';
        
        if (file_exists($changelog_file)) {
            echo file_get_contents($changelog_file);
        } else {
            echo "No se encontró el archivo CHANGELOG.md\n";
        }
    }
    
    private function create_release() {
        echo "Creando release...\n";
        
        $result = $this->version_manager->crear_release();
        
        if ($result['success']) {
            echo "✅ Release creado exitosamente\n";
            echo "📦 Archivo: {$result['file']}\n";
            echo "🔗 URL: {$result['url']}\n";
        } else {
            echo "❌ Error: {$result['error']}\n";
        }
    }
    
    private function show_help() {
        echo "Gestor de Versiones - Gestión de Almacenes\n";
        echo "==========================================\n\n";
        echo "Uso: php version-manager.php [comando] [argumentos]\n\n";
        echo "Comandos disponibles:\n";
        echo "  bump [patch|minor|major] \"descripción\"  - Incrementa la versión\n";
        echo "  add-change [tipo] \"descripción\"         - Registra un cambio pendiente\n";
        echo "  changelog                               - Muestra el changelog\n";
        echo "  release                                 - Crea un archivo ZIP del release\n\n";
        echo "Ejemplos:\n";
        echo "  php version-manager.php bump patch \"Corrección de error en almacenes\"\n";
        echo "  php version-manager.php add-change Fixed \"Corregido error al editar almacén\"\n";
        echo "  php version-manager.php release\n";
    }
}

// Ejecutar CLI
$cli = new GAB_Version_CLI();
$cli->run($argv);