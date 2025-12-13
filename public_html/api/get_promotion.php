<?php
define('APP_ENTRY_POINT', true);
// Bootstrap de la aplicación - detectar entorno
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    require_once __DIR__ . '/../../app/bootstrap.php';
}

/**
 * Get Promotion API - Returns active promotion for a product
 */

// Apply rate limiting: 30 requests per minute per IP
api_rate_limit(30, 60);

header('Content-Type: application/json');

// Set security headers
set_security_headers();

// Include promotions system
require_once APP_PATH . '/includes/promotions.php';

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get product ID from query parameter
$product_id = $_GET['product_id'] ?? null;

if (empty($product_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID required']);
    exit;
}

// Get active promotion for product
$promotion = get_active_promotion_for_product(sanitize_input($product_id));

echo json_encode([
    'success' => true,
    'promotion' => $promotion
]);
