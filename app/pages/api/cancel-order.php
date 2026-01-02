<?php
/**
 * Cancel Order API
 * Permite cancelar pedidos en estado pending
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Apply rate limiting: 10 requests per minute per IP
api_rate_limit(10, 60);

// Set security headers
set_security_headers();

// Start session
session_start();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validar Origin header para prevenir CSRF
$config = require APP_PATH . '/config/config.php';
$app_url = $config['app_url'] ?? '';
$allowed_origins = [
    'http://localhost:8000',
    'http://127.0.0.1:8000'
];
// Add production URL if configured
if (!empty($app_url)) {
    $allowed_origins[] = rtrim($app_url, '/');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

// Extraer dominio del referer si no hay origin
if (empty($origin) && !empty($_SERVER['HTTP_REFERER'])) {
    $parsed = parse_url($_SERVER['HTTP_REFERER']);
    $origin = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
}

$origin_valid = false;
foreach ($allowed_origins as $allowed) {
    if (strpos($origin, $allowed) === 0) {
        $origin_valid = true;
        break;
    }
}

if (!$origin_valid && !empty($origin)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid origin']);
    error_log("Cancel Order: Origen no permitido: $origin desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['order_id']) || !isset($data['token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id or token']);
    exit;
}

// Validar CSRF token si está presente (opcional pero recomendado)
if (isset($data['csrf_token'])) {
    if (!validate_csrf_token($data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        error_log("Cancel Order: CSRF token inválido para orden: {$data['order_id']}");
        exit;
    }
}

$order_id = sanitize_input($data['order_id']);
$token = sanitize_input($data['token']);

// Verify order exists and token matches
$order = get_order_by_token($token);

if (!$order || $order['id'] !== $order_id) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

// Check if order can be cancelled
if ($order['status'] !== 'pending') {
    http_response_code(400);
    echo json_encode(['error' => 'Only pending orders can be cancelled']);
    exit;
}

// Update order status
$result = update_order_status($order_id, 'cancelled', 'Cancelado por el cliente');

if ($result['success']) {
    // Restore stock for cancelled items
    foreach ($order['items'] as $item) {
        $product = get_product_by_id($item['product_id']);
        if ($product) {
            update_product_stock($item['product_id'], $product['stock'] + $item['quantity']);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to cancel order']);
}
