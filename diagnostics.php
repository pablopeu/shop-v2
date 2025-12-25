<?php
/**
 * Script de diagnóstico para instalaciones del sistema
 * Coloca este archivo en la raíz donde instalaste el sistema
 * Accede vía: http://tu-dominio.com/ruta/diagnostics.php
 */

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'><title>Diagnóstico del Sistema</title>\n";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo "h2{color:#4ec9b0;}pre{background:#2d2d2d;padding:10px;border-left:3px solid #007acc;overflow-x:auto;}";
echo ".ok{color:#4ec9b0;}.error{color:#f48771;}.warning{color:#dcdcaa;}</style></head><body>\n";

echo "<h1>🔍 Diagnóstico del Sistema Shop v2</h1>\n";

// 1. Rutas del servidor
echo "<h2>📁 Información de Rutas</h2>\n";
echo "<pre>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Current Dir: " . __DIR__ . "\n";
echo "</pre>\n";

// 2. Detectar base_path automáticamente
echo "<h2>🔗 Detección de Base Path</h2>\n";
echo "<pre>";
$document_root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$script_dir = rtrim(__DIR__, '/');

if ($script_dir === $document_root) {
    $detected_base_path = '/';
    echo "<span class='ok'>✓ Instalado en raíz del dominio</span>\n";
} else {
    $detected_base_path = str_replace($document_root, '', $script_dir);
    if (substr($detected_base_path, -1) !== '/') {
        $detected_base_path .= '/';
    }
    echo "<span class='ok'>✓ Instalado en subdirectorio</span>\n";
}

echo "Base Path Detectado: <strong>" . htmlspecialchars($detected_base_path) . "</strong>\n";
echo "</pre>\n";

// 3. Verificar archivos .htaccess
echo "<h2>📄 Archivos .htaccess</h2>\n";

// .htaccess en el directorio actual
$htaccess_current = __DIR__ . '/.htaccess';
echo "<h3>.htaccess en directorio actual:</h3>\n";
if (file_exists($htaccess_current)) {
    echo "<pre class='ok'>✓ Existe: $htaccess_current\n\n";
    echo htmlspecialchars(file_get_contents($htaccess_current));
    echo "</pre>\n";
} else {
    echo "<pre class='error'>✗ No existe: $htaccess_current</pre>\n";
}

// .htaccess en DOCUMENT_ROOT (raíz)
if (__DIR__ !== $_SERVER['DOCUMENT_ROOT']) {
    $htaccess_root = $_SERVER['DOCUMENT_ROOT'] . '/.htaccess';
    echo "<h3>.htaccess en raíz (DOCUMENT_ROOT):</h3>\n";
    if (file_exists($htaccess_root)) {
        echo "<pre class='warning'>⚠ Existe: $htaccess_root\n\n";
        echo htmlspecialchars(file_get_contents($htaccess_root));
        echo "</pre>\n";
    } else {
        echo "<pre>No existe .htaccess en raíz</pre>\n";
    }
}

// 4. Verificar carpetas del sistema
echo "<h2>📦 Estructura de Carpetas</h2>\n";
echo "<pre>";

$folders_to_check = [
    'app' => 'Código privado (DEBE estar fuera de public_html)',
    'admin' => 'Panel de administración',
    'api' => 'API endpoints',
    'assets' => 'Recursos estáticos',
    'data' => 'Datos públicos',
];

foreach ($folders_to_check as $folder => $desc) {
    $path = __DIR__ . '/' . $folder;
    if (is_dir($path)) {
        echo "<span class='ok'>✓</span> $folder/ - $desc\n";
    } else {
        echo "<span class='error'>✗</span> $folder/ - NO ENCONTRADA - $desc\n";
    }
}
echo "</pre>\n";

// 5. Verificar archivo de configuración
echo "<h2>⚙️ Configuración del Sistema</h2>\n";
echo "<pre>";

// Buscar config.php en varios lugares posibles
$config_locations = [
    __DIR__ . '/app/config/config.php',
    __DIR__ . '/../app/config/config.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/app/config/config.php',
];

$config_found = false;
foreach ($config_locations as $config_path) {
    if (file_exists($config_path)) {
        echo "<span class='ok'>✓ Config encontrado:</span> $config_path\n\n";

        // Leer base_path del config
        $config_content = file_get_contents($config_path);
        if (preg_match("/define\s*\(\s*['\"]BASE_PATH['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $config_content, $matches)) {
            $config_base_path = $matches[1];
            echo "BASE_PATH en config.php: <strong>" . htmlspecialchars($config_base_path) . "</strong>\n";

            if ($config_base_path === $detected_base_path) {
                echo "<span class='ok'>✓ Coincide con el base_path detectado</span>\n";
            } else {
                echo "<span class='error'>✗ NO coincide con el base_path detectado!</span>\n";
                echo "  Detectado: " . htmlspecialchars($detected_base_path) . "\n";
                echo "  En config: " . htmlspecialchars($config_base_path) . "\n";
            }
        }

        $config_found = true;
        break;
    }
}

if (!$config_found) {
    echo "<span class='error'>✗ No se encontró config.php en ninguna ubicación esperada</span>\n";
    echo "\nBuscado en:\n";
    foreach ($config_locations as $loc) {
        echo "  - $loc\n";
    }
}

echo "</pre>\n";

// 6. Variables de servidor relevantes
echo "<h2>🌐 Variables de Servidor</h2>\n";
echo "<pre>";
$server_vars = ['HTTP_HOST', 'SERVER_NAME', 'REQUEST_SCHEME', 'REDIRECT_URL', 'REDIRECT_STATUS'];
foreach ($server_vars as $var) {
    if (isset($_SERVER[$var])) {
        echo "$var: " . htmlspecialchars($_SERVER[$var]) . "\n";
    }
}
echo "</pre>\n";

// 7. Test de URLs
echo "<h2>🔗 URLs de Prueba</h2>\n";
echo "<pre>";
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$current_url = $protocol . '://' . $host . $detected_base_path;

echo "URL Base del Sistema: <strong>" . htmlspecialchars($current_url) . "</strong>\n";
echo "\nPrueba estas URLs:\n";
echo "  - Frontend: " . htmlspecialchars($current_url) . "index.php\n";
echo "  - Admin: " . htmlspecialchars($current_url) . "admin/\n";
echo "  - API: " . htmlspecialchars($current_url) . "api/\n";
echo "</pre>\n";

// 8. Módulos Apache
echo "<h2>🔧 Configuración PHP/Apache</h2>\n";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $required = ['mod_rewrite', 'mod_headers'];
    foreach ($required as $mod) {
        if (in_array($mod, $modules)) {
            echo "<span class='ok'>✓</span> $mod habilitado\n";
        } else {
            echo "<span class='error'>✗</span> $mod NO encontrado\n";
        }
    }
} else {
    echo "<span class='warning'>⚠</span> No se puede verificar módulos Apache (apache_get_modules no disponible)\n";
}
echo "</pre>\n";

echo "</body></html>";
