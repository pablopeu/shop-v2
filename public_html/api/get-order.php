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
 * API - Get Order Details
 * Returns order details by ID
 */

// Apply rate limiting: 10 requests per minute per IP
api_rate_limit(10, 60);

// Set JSON header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!is_logged_in() || !is_admin()) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Order ID is required'
    ]);
    exit;
}

$order_id = $_GET['id'];
$order = get_order_by_id($order_id);

if (!$order) {
    echo json_encode([
        'success' => false,
        'error' => 'Order not found'
    ]);
    exit;
}

// Format order data for display
$order['total_formatted'] = format_price($order['total']);

// Format item prices
foreach ($order['items'] as &$item) {
    $item['price_formatted'] = format_price($item['price']);
}

// Return order data
echo json_encode([
    'success' => true,
    'order' => $order
]);
