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

// Set JSON header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("GET-ORDER API: Session ID: " . session_id());
error_log("GET-ORDER API: Session data: " . print_r($_SESSION, true));
error_log("GET-ORDER API: is_logged_in(): " . (is_logged_in() ? 'true' : 'false'));
error_log("GET-ORDER API: is_admin(): " . (is_admin() ? 'true' : 'false'));

// Check admin authentication
if (!is_logged_in() || !is_admin()) {
    error_log("GET-ORDER API: UNAUTHORIZED - is_logged_in=" . (is_logged_in() ? 'true' : 'false') . ", is_admin=" . (is_admin() ? 'true' : 'false'));
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
error_log("GET-ORDER API: Buscando orden con ID: " . $order_id);

$order = get_order_by_id($order_id);

if (!$order) {
    error_log("GET-ORDER API: Orden no encontrada. ID: " . $order_id);
    echo json_encode([
        'success' => false,
        'error' => 'Order not found'
    ]);
    exit;
}

error_log("GET-ORDER API: Orden encontrada: " . json_encode($order));

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
