<?php
/**
 * Gestor de versiones para el plugin
 */

class Gestion_Almacenes_Version_Manager {
    
    private $plugin_file;
    private $current_version;
    private $changelog_file;
    
    public function __construct() {
        $this->plugin_file = GESTION_ALMACENES_PLUGIN_DIR . 'gestion-almacenes.php';
        $this->changelog_file = GESTION_ALMACENES_PLUGIN_DIR . 'CHANGELOG.md';
        $this->current_version = GESTION_ALMACENES_VERSION;
    }
    
    /**
     * Incrementar versión según el tipo de cambio
     * @param string $type 'patch', 'minor', 'major'
     * @param string $description Descripción del cambio
     */
    public function incrementar_version($type = 'patch', $description = '') {
        $version_parts = explode('.', $this->current_version);
        
        switch ($type) {
            case 'major':
                // 1.0.0 -> 2.0.0
                $version_parts[0]++;
                $version_parts[1] = 0;
                $version_parts[2] = 0;
                break;
                
            case 'minor':
                // 1.0.0 -> 1.1.0
                $version_parts[1]++;
                $version_parts[2] = 0;
                break;
                
            case 'patch':
            default:
                // 1.0.0 -> 1.0.1
                $version_parts[2]++;
                break;
        }
        
        $new_version = implode('.', $version_parts);
        
        // Actualizar archivos
        $this->actualizar_version_en_archivos($new_version);
        
        // Registrar en changelog
        $this->agregar_entrada_changelog($new_version, $type, $description);
        
        // Registrar en base de datos
        $this->registrar_version_en_db($new_version, $type, $description);
        
        return $new_version;
    }
    
    /**
     * Actualizar versión en todos los archivos necesarios
     */
    private function actualizar_version_en_archivos($new_version) {
        // 1. Actualizar en el archivo principal del plugin
        $plugin_content = file_get_contents($this->plugin_file);
        
        // Actualizar en el header
        $plugin_content = preg_replace(
            '/\* Version:\s*[\d.]+/',
            '* Version: ' . $new_version,
            $plugin_content
        );
        
        // Actualizar la constante
        $plugin_content = preg_replace(
            "/define\('GESTION_ALMACENES_VERSION',\s*'[\d.]+'\)/",
            "define('GESTION_ALMACENES_VERSION', '$new_version')",
            $plugin_content
        );
        
        // Actualizar la constante de versión de BD si es necesario
        if ($this->requiere_actualizacion_db($new_version)) {
            $plugin_content = preg_replace(
                "/define\('GESTION_ALMACENES_DB_VERSION',\s*'[\d.]+'\)/",
                "define('GESTION_ALMACENES_DB_VERSION', '$new_version')",
                $plugin_content
            );
        }
        
        file_put_contents($this->plugin_file, $plugin_content);
        
        // 2. Actualizar README.txt si existe
        $readme_file = GESTION_ALMACENES_PLUGIN_DIR . 'readme.txt';
        if (file_exists($readme_file)) {
            $readme_content = file_get_contents($readme_file);
            $readme_content = preg_replace(
                '/Stable tag:\s*[\d.]+/',
                'Stable tag: ' . $new_version,
                $readme_content
            );
            file_put_contents($readme_file, $readme_content);
        }
    }
    
    /**
     * Agregar entrada al changelog
     */
    private function agregar_entrada_changelog($version, $type, $description) {
        $date = date('Y-m-d');
        $type_label = ucfirst($type);
        
        // Crear changelog si no existe
        if (!file_exists($this->changelog_file)) {
            $changelog_content = "# Changelog - Gestión de Almacenes\n\n";
            $changelog_content .= "Todos los cambios notables en este proyecto serán documentados en este archivo.\n\n";
        } else {
            $changelog_content = file_get_contents($this->changelog_file);
        }
        
        // Preparar nueva entrada
        $new_entry = "\n## [$version] - $date\n\n";
        $new_entry .= "### $type_label Update\n";
        
        if (!empty($description)) {
            $new_entry .= "- $description\n";
        }
        
        // Agregar cambios recientes si existen
        $recent_changes = get_option('gab_pending_changes', array());
        if (!empty($recent_changes)) {
            $new_entry .= "\n### Cambios incluidos:\n";
            foreach ($recent_changes as $change) {
                $new_entry .= "- {$change['type']}: {$change['description']}\n";
            }
            // Limpiar cambios pendientes
            delete_option('gab_pending_changes');
        }
        
        // Insertar después del título
        $changelog_parts = explode("\n## ", $changelog_content, 2);
        if (count($changelog_parts) > 1) {
            $changelog_content = $changelog_parts[0] . $new_entry . "\n## " . $changelog_parts[1];
        } else {
            $changelog_content .= $new_entry;
        }
        
        file_put_contents($this->changelog_file, $changelog_content);
    }
    
    /**
     * Registrar versión en base de datos
     */
    private function registrar_version_en_db($version, $type, $description) {
        $version_history = get_option('gab_version_history', array());
        
        $version_history[] = array(
            'version' => $version,
            'type' => $type,
            'description' => $description,
            'date' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login
        );
        
        update_option('gab_version_history', $version_history);
        update_option('gab_current_version', $version);
    }
    
    /**
     * Registrar un cambio pendiente para el próximo release
     */
    public function registrar_cambio_pendiente($type, $description) {
        $pending_changes = get_option('gab_pending_changes', array());
        
        $pending_changes[] = array(
            'type' => $type, // 'Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security'
            'description' => $description,
            'date' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login
        );
        
        update_option('gab_pending_changes', $pending_changes);
    }
    
    /**
     * Obtener cambios pendientes
     */
    public function obtener_cambios_pendientes() {
        return get_option('gab_pending_changes', array());
    }
    
    /**
     * Obtener historial de versiones
     */
    public function obtener_historial_versiones() {
        return get_option('gab_version_history', array());
    }
    
    /**
     * Verificar si una versión requiere actualización de BD
     */
    private function requiere_actualizacion_db($version) {
        // Define aquí las versiones que requieren cambios en la BD
        $versiones_con_cambios_db = array('1.1.0', '2.0.0');
        return in_array($version, $versiones_con_cambios_db);
    }
    
    /**
     * Crear archivo ZIP para distribución
     */
    public function crear_release($version = null) {
        if (!$version) {
            $version = $this->current_version;
        }
        
        $plugin_slug = 'gestion-almacenes';
        $zip_filename = $plugin_slug . '-' . $version . '.zip';
        $zip_path = WP_CONTENT_DIR . '/uploads/' . $zip_filename;
        
        // Crear ZIP
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            // Agregar archivos del plugin
            $this->agregar_directorio_a_zip($zip, GESTION_ALMACENES_PLUGIN_DIR, $plugin_slug);
            $zip->close();
            
            return array(
                'success' => true,
                'file' => $zip_path,
                'url' => content_url('uploads/' . $zip_filename)
            );
        }
        
        return array('success' => false, 'error' => 'No se pudo crear el archivo ZIP');
    }
    
    /**
     * Agregar directorio recursivamente al ZIP
     */
    private function agregar_directorio_a_zip($zip, $dir, $base) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $base . '/' . substr($filePath, strlen($dir) + 1);
                
                // Excluir archivos no necesarios
                if (!$this->debe_excluir_archivo($relativePath)) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }
    
    /**
     * Determinar si un archivo debe ser excluido del release
     */
    private function debe_excluir_archivo($path) {
        $excludes = array(
            '.git',
            '.gitignore',
            '.DS_Store',
            'node_modules',
            'tests',
            '.phpunit',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json'
        );
        
        foreach ($excludes as $exclude) {
            if (strpos($path, $exclude) !== false) {
                return true;
            }
        }
        
        return false;
    }
}