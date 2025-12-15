<?php
/**
 * Sync Cart API - Sincroniza carrito de localStorage a sesión PHP
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Apply rate limiting: 30 requests per minute per IP
api_rate_limit(30, 60);

// Require JSON Content-Type
require_json_content_type();

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

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['cart']) || !is_array($data['cart'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid cart data']);
    exit;
}

// Validate cart items (keep array format for checkout)
$cart = [];
foreach ($data['cart'] as $item) {
    if (!isset($item['product_id']) || !isset($item['quantity'])) {
        continue;
    }

    $quantity = intval($item['quantity']);

    // Only add items with quantity > 0
    if ($quantity > 0) {
        $cart[] = [
            'product_id' => sanitize_input($item['product_id']),
            'quantity' => $quantity
        ];
    }
}

// Save to session - if empty, clear the cart
if (empty($cart)) {
    unset($_SESSION['cart']);
} else {
    $_SESSION['cart'] = $cart;
}

// Also save coupon if present (for checkout to use)
if (isset($data['coupon_code']) && !empty($data['coupon_code'])) {
    $coupon_code = sanitize_input($data['coupon_code']);

    // Validate coupon before saving to session
    $coupon_validation = validate_coupon($coupon_code, $cart);

    if ($coupon_validation['valid']) {
        $_SESSION['applied_coupon'] = $coupon_code;
    } else {
        // Coupon is expired or invalid, clear it and return error
        unset($_SESSION['applied_coupon']);
        echo json_encode([
            'success' => false,
            'error' => 'coupon_expired',
            'message' => 'El cupón "' . $coupon_code . '" ha expirado o ya no es válido: ' . $coupon_validation['message']
        ]);
        exit;
    }
} else {
    // Clear coupon if not present
    unset($_SESSION['applied_coupon']);
}

// Force session write and close to ensure data is saved
session_write_close();

echo json_encode([
    'success' => true,
    'items' => count($cart),
    'message' => 'Cart synchronized successfully'
]);
