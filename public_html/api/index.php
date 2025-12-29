<?php
/**
 * API Entry Point
 * Punto de entrada oficial para endpoints de API
 *
 * Este es el 5to entry point oficial del proyecto (junto con index.php, admin/index.php, admin/login.php, webhook.php)
 */

define('APP_ENTRY_POINT', true);

// Cargar configuración de ruta al bootstrap (generada por instalador)
$bootstrap_config = __DIR__ . '/bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Bootstrap file not found']);
        exit;
    }
} else {
    // Fallback para desarrollo (estructura relativa)
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
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$api_identifier = 'api_' . $client_ip;
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
    // Pagos y Preferencias
    'crear-preferencia-mp' => APP_PATH . '/pages/api/crear-preferencia-mp.php',

    // Exportación (Admin only)
    'export-orders' => APP_PATH . '/pages/api/export-orders.php',
    'export-archived-orders' => APP_PATH . '/pages/api/export-archived-orders.php',

    // Validación y Carrito
    'validate-coupon' => APP_PATH . '/pages/api/validate-coupon.php',
    'sync-cart' => APP_PATH . '/pages/api/sync-cart.php',

    // Órdenes
    'get-order' => APP_PATH . '/pages/api/get-order.php',
    'get-archived-order' => APP_PATH . '/pages/api/get-archived-order.php',
    'cancel-order' => APP_PATH . '/pages/api/cancel-order.php',

    // Productos y Promociones
    'get-products' => APP_PATH . '/pages/api/get-products.php',
    'get-promotion' => APP_PATH . '/pages/api/get-promotion.php',

    // Wishlist
    'create-short-link' => APP_PATH . '/pages/api/create-short-link.php',
    'get-shared-wishlist' => APP_PATH . '/pages/api/get-shared-wishlist.php',

    // Testing (Checkout)
    'send-test-email' => APP_PATH . '/pages/api/send-test-email.php',
    'send-telegram-test' => APP_PATH . '/pages/api/send-telegram-test.php',

    // Admin Operations
    'update-exchange-rate' => APP_PATH . '/pages/api/update-exchange-rate.php',
    'update-products-order' => APP_PATH . '/pages/api/update-products-order.php',

    // Shipping (Zipnova Integration)
    'shipping' => APP_PATH . '/pages/api/shipping.php',
    'create-shipment-from-order' => APP_PATH . '/pages/api/create-shipment-from-order.php',
    'print-shipping-label' => APP_PATH . '/pages/api/print-shipping-label.php',

    // Email Templates (Admin only)
    'preview-email-template' => APP_PATH . '/pages/api/preview-email-template.php',
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
