<?php
/**
 * Script de Reparación de .htaccess
 * Ejecutar UNA VEZ para regenerar .htaccess con RewriteBase correcto
 *
 * USO: https://peu.net/shopv2/fix-htaccess.php
 *
 * IMPORTANTE: Este script se auto-elimina después de ejecutarse
 */

// Security: Solo permitir ejecución desde navegador
if (php_sapi_name() === 'cli') {
    die("Este script debe ejecutarse desde el navegador\n");
}

// Detectar base path desde la URL actual
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = dirname($script_name);
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}

// Normalizar para RewriteBase (debe terminar en /)
$rewrite_base = $base_path;
if ($rewrite_base === '') {
    $rewrite_base = '/';
} else {
    if (substr($rewrite_base, 0, 1) !== '/') {
        $rewrite_base = '/' . $rewrite_base;
    }
    if (substr($rewrite_base, -1) !== '/') {
        $rewrite_base .= '/';
    }
}

echo "<!DOCTYPE html>\n";
echo "<html>\n<head>\n<title>Reparación de .htaccess</title>\n";
echo "<style>body{font-family:monospace;max-width:800px;margin:50px auto;padding:20px;}";
echo "pre{background:#f5f5f5;padding:15px;border-left:4px solid #4CAF50;}";
echo ".success{color:#4CAF50;font-weight:bold;}";
echo ".error{color:#dc3545;font-weight:bold;}";
echo "</style>\n</head>\n<body>\n";

echo "<h1>🔧 Reparación de .htaccess</h1>\n";
echo "<p><strong>Base Path detectado:</strong> <code>" . htmlspecialchars($base_path) . "</code></p>\n";
echo "<p><strong>RewriteBase:</strong> <code>" . htmlspecialchars($rewrite_base) . "</code></p>\n";
echo "<hr>\n";

$errors = [];
$success = [];

// ==================== GENERAR .htaccess PRINCIPAL ====================
$public_htaccess_path = __DIR__ . '/.htaccess';

$public_htaccess = "# Public .htaccess\n";
$public_htaccess .= "# Routing rules for frontend and admin\n\n";
$public_htaccess .= "# Protect sensitive files\n";
$public_htaccess .= "<FilesMatch \"\\.(json|md|log)$\">\n";
$public_htaccess .= "    Order deny,allow\n";
$public_htaccess .= "    Deny from all\n";
$public_htaccess .= "</FilesMatch>\n\n";
$public_htaccess .= "# Enable rewrite engine\n";
$public_htaccess .= "RewriteEngine On\n";
$public_htaccess .= "RewriteBase " . $rewrite_base . "\n\n";
$public_htaccess .= "# Don't redirect existing files (CSS, JS, images, etc.)\n";
$public_htaccess .= "RewriteCond %{REQUEST_FILENAME} -f\n";
$public_htaccess .= "RewriteRule ^ - [L]\n\n";
$public_htaccess .= "# Don't redirect existing directories\n";
$public_htaccess .= "RewriteCond %{REQUEST_FILENAME} -d\n";
$public_htaccess .= "RewriteRule ^ - [L]\n\n";
$public_htaccess .= "# Don't redirect API endpoints\n";
$public_htaccess .= "RewriteCond %{REQUEST_URI} /api/ [OR]\n";
$public_htaccess .= "RewriteCond %{REQUEST_URI} /admin/api/\n";
$public_htaccess .= "RewriteRule ^ - [L]\n\n";
$public_htaccess .= "# Don't redirect webhook\n";
$public_htaccess .= "RewriteCond %{REQUEST_URI} /webhook\\.php$\n";
$public_htaccess .= "RewriteRule ^ - [L]\n\n";
$public_htaccess .= "# Don't redirect admin PHP files\n";
$public_htaccess .= "RewriteCond %{REQUEST_URI} /admin/.*\\.php$\n";
$public_htaccess .= "RewriteRule ^ - [L]\n\n";
$public_htaccess .= "# Redirect all other requests to index.php\n";
$public_htaccess .= "RewriteRule ^ index.php [L]\n";

if (file_put_contents($public_htaccess_path, $public_htaccess)) {
    $success[] = "✅ .htaccess principal generado: " . $public_htaccess_path;
} else {
    $errors[] = "❌ Error al generar .htaccess principal";
}

// ==================== GENERAR .htaccess DE API ====================
$api_htaccess_path = __DIR__ . '/api/.htaccess';
$api_rewrite_base = $rewrite_base . 'api/';

$api_htaccess = "# API Directory .htaccess\n";
$api_htaccess .= "# Redirect all requests to index.php (API router)\n\n";
$api_htaccess .= "# Enable rewrite engine\n";
$api_htaccess .= "RewriteEngine On\n";
$api_htaccess .= "RewriteBase " . $api_rewrite_base . "\n\n";
$api_htaccess .= "# Don't redirect if it's index.php itself\n";
$api_htaccess .= "RewriteCond %{REQUEST_URI} index\\.php$\n";
$api_htaccess .= "RewriteRule ^ - [L]\n\n";
$api_htaccess .= "# Don't redirect existing physical files (for legacy endpoints)\n";
$api_htaccess .= "RewriteCond %{REQUEST_FILENAME} -f\n";
$api_htaccess .= "RewriteRule ^ - [L]\n\n";
$api_htaccess .= "# Redirect everything else to index.php\n";
$api_htaccess .= "RewriteRule ^ index.php [L]\n";

if (file_put_contents($api_htaccess_path, $api_htaccess)) {
    $success[] = "✅ .htaccess de API generado: " . $api_htaccess_path;
} else {
    $errors[] = "❌ Error al generar .htaccess de API";
}

// ==================== MOSTRAR RESULTADOS ====================
echo "<h2>Resultados:</h2>\n";

if (!empty($success)) {
    echo "<div class='success'>\n";
    foreach ($success as $msg) {
        echo "<p>" . htmlspecialchars($msg) . "</p>\n";
    }
    echo "</div>\n";
}

if (!empty($errors)) {
    echo "<div class='error'>\n";
    foreach ($errors as $msg) {
        echo "<p>" . htmlspecialchars($msg) . "</p>\n";
    }
    echo "</div>\n";
}

// Mostrar contenido generado
echo "<h3>.htaccess principal:</h3>\n";
echo "<pre>" . htmlspecialchars($public_htaccess) . "</pre>\n";

echo "<h3>.htaccess de API:</h3>\n";
echo "<pre>" . htmlspecialchars($api_htaccess) . "</pre>\n";

// Auto-eliminar este script
echo "<hr>\n";
echo "<h2>🗑️ Auto-eliminación del script</h2>\n";
if (unlink(__FILE__)) {
    echo "<p class='success'>✅ Script eliminado exitosamente</p>\n";
    echo "<p><strong>Este script ya no existe.</strong> Los .htaccess han sido generados correctamente.</p>\n";
} else {
    echo "<p class='error'>❌ No se pudo eliminar el script automáticamente</p>\n";
    echo "<p>Por favor, elimina manualmente el archivo: <code>fix-htaccess.php</code></p>\n";
}

echo "<hr>\n";
echo "<p><strong>Siguiente paso:</strong> Recarga tu sitio y verifica que las imágenes se carguen correctamente.</p>\n";
echo "<p><a href='" . htmlspecialchars($base_path . '/') . "'>← Volver al inicio</a></p>\n";

echo "</body>\n</html>";
