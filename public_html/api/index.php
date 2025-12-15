<?php
/**
 * API Entry Point
 * Punto de entrada oficial para endpoints de API
 *
 * Este es el 5to entry point oficial del proyecto (junto con index.php, admin/index.php, admin/login.php, webhook.php)
 */

define('APP_ENTRY_POINT', true);

// Detectar entorno y cargar bootstrap
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    // Producción
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    // Testing
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    // Desarrollo
    $bootstrap_path = __DIR__ . '/../../app/bootstrap.php';
    if (!file_exists($bootstrap_path)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Bootstrap not found']);
        exit;
    }
    require_once $bootstrap_path;
}

// Establecer header JSON para todas las respuestas
header('Content-Type: application/json');

// Rate limiting global para API
$api_identifier = 'api_' . get_client_ip();
if (!check_rate_limit($api_identifier, 100, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Demasiadas solicitudes. Por favor, intenta más tarde.'
    ]);
    exit;
}

// Obtener el endpoint solicitado
$endpoint = $_GET['endpoint'] ?? '';

// Validar que el endpoint existe
if (empty($endpoint)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint no especificado'
    ]);
    exit;
}

// Mapa de endpoints disponibles
$endpoints_map = [
    'crear-preferencia-mp' => APP_PATH . '/pages/api/crear-preferencia-mp.php',
];

// Verificar que el endpoint existe
if (!isset($endpoints_map[$endpoint])) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint no encontrado'
    ]);
    exit;
}

// Verificar que el archivo del endpoint existe
$endpoint_file = $endpoints_map[$endpoint];
if (!file_exists($endpoint_file)) {
    http_response_code(500);
    error_log("API: Archivo de endpoint no encontrado: $endpoint_file");
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
    exit;
}

// Cargar y ejecutar el endpoint
require_once $endpoint_file;
