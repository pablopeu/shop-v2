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
 * API: Crear Link Corto para Wishlist
 * Genera un código corto único para compartir listas de favoritos
 */

// Apply rate limiting: 20 requests per minute per IP
api_rate_limit(20, 60);

// Require JSON Content-Type
require_json_content_type();

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input schema
$validation = validate_api_input($data, [
    'product_ids' => [
        'required' => true,
        'type' => 'array',
        'min_items' => 1,
        'max_items' => 100  // Límite razonable para favoritos
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

if (empty($product_ids)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No hay IDs de productos válidos'
    ]);
    exit;
}

// Generar código corto único
function generate_short_code($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

// Cargar wishlists compartidas existentes
$wishlists_file = APP_PATH . '/data/shared_wishlists.json';
$wishlists = read_json($wishlists_file);

// Generar código único (intentar hasta 10 veces)
$code = null;
for ($i = 0; $i < 10; $i++) {
    $temp_code = generate_short_code(6);
    if (!isset($wishlists[$temp_code])) {
        $code = $temp_code;
        break;
    }
}

if (!$code) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo generar un código único'
    ]);
    exit;
}

// Obtener configuración de días de vencimiento
$site_config = read_json(APP_PATH . '/config/site.json');
$expiry_days = intval($site_config['shared_wishlist_expiry_days'] ?? 30);
if ($expiry_days < 1 || $expiry_days > 365) {
    $expiry_days = 30; // Fallback a 30 días si el valor es inválido
}

// Guardar wishlist
$wishlists[$code] = [
    'product_ids' => array_values($product_ids),
    'created_at' => date('Y-m-d H:i:s'),
    'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"))
];

if (!write_json($wishlists_file, $wishlists)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar el código'
    ]);
    exit;
}

// Devolver código generado
echo json_encode([
    'success' => true,
    'code' => $code,
    'expires_at' => $wishlists[$code]['expires_at']
]);
