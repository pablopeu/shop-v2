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
 * API: Obtener Wishlist Compartida
 * Obtiene los IDs de productos de una wishlist compartida por su código
 */

// Apply rate limiting: 60 requests per minute per IP
api_rate_limit(60, 60);

header('Content-Type: application/json');

// Get code from GET parameter
$code = $_GET['code'] ?? '';

// Validar código
if (empty($code) || !preg_match('/^[a-zA-Z0-9]{6}$/', $code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Código inválido'
    ]);
    exit;
}

// Cargar wishlists compartidas
$wishlists_file = APP_PATH . '/data/shared_wishlists.json';
$wishlists = read_json($wishlists_file);

// Verificar si existe el código
if (!isset($wishlists[$code])) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Lista no encontrada'
    ]);
    exit;
}

$wishlist = $wishlists[$code];

// Verificar si expiró
if (strtotime($wishlist['expires_at']) < time()) {
    http_response_code(410);
    echo json_encode([
        'success' => false,
        'error' => 'Esta lista ha expirado'
    ]);
    exit;
}

// Devolver IDs de productos
echo json_encode([
    'success' => true,
    'product_ids' => $wishlist['product_ids'],
    'created_at' => $wishlist['created_at'],
    'expires_at' => $wishlist['expires_at']
]);
