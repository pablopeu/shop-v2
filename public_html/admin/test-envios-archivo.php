<?php
/**
 * Script de diagnóstico para envios-archivo.php
 */

// Define como punto de entrada autorizado
define('APP_ENTRY_POINT', true);
define('ADMIN_AREA', true);

// Bootstrap
$bootstrap_config = __DIR__ . '/bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        die('Bootstrap file not found.');
    }
} else {
    require_once __DIR__ . '/../../app/bootstrap.php';
}

echo "<h1>Diagnóstico de envios-archivo.php</h1>";

// Verificar que el archivo existe
$archivo_path = APP_PATH . '/pages/admin/envios-archivo.php';
echo "<p><strong>Ruta del archivo:</strong> " . htmlspecialchars($archivo_path) . "</p>";
echo "<p><strong>¿Existe?:</strong> " . (file_exists($archivo_path) ? '✅ SÍ' : '❌ NO') . "</p>";

if (file_exists($archivo_path)) {
    echo "<p><strong>Permisos:</strong> " . substr(sprintf('%o', fileperms($archivo_path)), -4) . "</p>";
    echo "<p><strong>Tamaño:</strong> " . filesize($archivo_path) . " bytes</p>";
    echo "<p><strong>Última modificación:</strong> " . date('Y-m-d H:i:s', filemtime($archivo_path)) . "</p>";

    // Intentar verificar sintaxis PHP
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($archivo_path) . " 2>&1", $output, $return_var);
    echo "<p><strong>Sintaxis PHP:</strong><br>" . nl2br(htmlspecialchars(implode("\n", $output))) . "</p>";

    // Verificar funciones utilizadas
    $funciones_requeridas = [
        'require_admin',
        'read_json',
        'generate_csrf_token',
        'restore_archived_order',
        'delete_archived_order',
        'get_archived_orders',
        'get_logged_user',
        'log_admin_action',
        'csp_nonce',
        'url',
        'htmlspecialchars'
    ];

    echo "<h2>Funciones Requeridas:</h2><ul>";
    foreach ($funciones_requeridas as $func) {
        $exists = function_exists($func);
        echo "<li>" . htmlspecialchars($func) . ": " . ($exists ? '✅' : '❌') . "</li>";
    }
    echo "</ul>";

    // Verificar que APP_PATH está definido
    echo "<h2>Constantes:</h2><ul>";
    echo "<li>APP_PATH: " . (defined('APP_PATH') ? '✅ ' . htmlspecialchars(APP_PATH) : '❌') . "</li>";
    echo "<li>PUBLIC_PATH: " . (defined('PUBLIC_PATH') ? '✅ ' . htmlspecialchars(PUBLIC_PATH) : '❌') . "</li>";
    echo "<li>APP_ENTRY_POINT: " . (defined('APP_ENTRY_POINT') ? '✅' : '❌') . "</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='" . url('/admin/?page=envios-archivo') . "'>Intentar cargar envios-archivo.php</a></p>";
