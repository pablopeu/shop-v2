<?php
/**
 * API: Get Products by IDs
 * Returns product details for given product IDs
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Apply rate limiting: 30 requests per minute per IP
api_rate_limit(30, 60);

// Require JSON Content-Type
require_json_content_type();

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input schema
$validation = validate_api_input($data, [
    'product_ids' => [
        'required' => true,
        'type' => 'array',
        'min_items' => 1,
        'max_items' => 50  // Limitar cantidad para prevenir DoS
    ]
]);

if (!$validation['valid']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $validation['error']
    ]);
    exit;
}

$product_ids = $data['product_ids'];

// Validar que cada ID sea string y tenga formato válido
$product_ids = array_filter($product_ids, function($id) {
    return is_string($id) && preg_match('/^[a-zA-Z0-9\-_]+$/', $id) && strlen($id) <= 100;
});

// Obtener productos
$products = [];
foreach ($product_ids as $product_id) {
    $product = get_product_by_id($product_id);
    if ($product) {
        $products[] = $product;
    }
}

echo json_encode($products);
