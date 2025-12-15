<?php
/**
 * Debug API - Diagnóstico temporal
 */

define('APP_ENTRY_POINT', true);

// Detectar entorno y cargar bootstrap
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    $app_path = '/home2/uv0023/shop-v2-app';
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    $app_path = '/home/pablo/shop-v2-local-test/shop-v2-app';
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    $app_path = __DIR__ . '/../../app';
    require_once __DIR__ . '/../../app/bootstrap.php';
}

header('Content-Type: application/json');

$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'app_path' => $app_path,
    'app_path_exists' => file_exists($app_path),
    'api_dir_exists' => file_exists($app_path . '/pages/api'),
    'endpoint_file_exists' => file_exists($app_path . '/pages/api/crear-preferencia-mp.php'),
    'APP_PATH_constant' => defined('APP_PATH') ? APP_PATH : 'NO DEFINIDO',
    'files_in_app_pages' => file_exists($app_path . '/pages') ? scandir($app_path . '/pages') : [],
];

// Si existe el directorio api, listar archivos
if (file_exists($app_path . '/pages/api')) {
    $debug_info['files_in_api'] = scandir($app_path . '/pages/api');
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
