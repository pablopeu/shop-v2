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
 * API: Validate Coupon Code
 * Checks if a coupon code is valid and returns coupon details
 */

// Apply rate limiting: 20 requests per minute per IP
api_rate_limit(20, 60);

// Require JSON Content-Type
require_json_content_type();

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['code']) || empty($data['code'])) {
    echo json_encode([
        'valid' => false,
        'message' => 'Código de cupón requerido.'
    ]);
    exit;
}

$code = strtoupper(trim($data['code']));
$subtotal = $data['subtotal'] ?? 0; // Subtotal del carrito para validar mínimo de compra

// Load coupons
$coupons_data = read_json(APP_PATH . '/data/coupons.json');
$coupons = $coupons_data['coupons'] ?? [];

// Find coupon by code
$coupon = null;
foreach ($coupons as $c) {
    if (strtoupper($c['code']) === $code) {
        $coupon = $c;
        break;
    }
}

if (!$coupon) {
    echo json_encode([
        'valid' => false,
        'message' => 'Cupón no encontrado.'
    ]);
    exit;
}

// Check if active
if (!$coupon['active']) {
    echo json_encode([
        'valid' => false,
        'message' => 'Este cupón no está activo.'
    ]);
    exit;
}

// Check dates
$now = time();

if (!empty($coupon['start_date'])) {
    $start = strtotime($coupon['start_date']);
    if ($now < $start) {
        echo json_encode([
            'valid' => false,
            'message' => 'Este cupón aún no está disponible.'
        ]);
        exit;
    }
}

if (!empty($coupon['end_date'])) {
    $end = strtotime($coupon['end_date']);
    if ($now > $end) {
        echo json_encode([
            'valid' => false,
            'message' => 'Este cupón ha expirado.'
        ]);
        exit;
    }
}

// Check max uses
if (isset($coupon['max_uses']) && $coupon['max_uses'] > 0) {
    $uses_count = $coupon['uses_count'] ?? 0;
    if ($uses_count >= $coupon['max_uses']) {
        echo json_encode([
            'valid' => false,
            'message' => 'Este cupón ha alcanzado su límite de usos.'
        ]);
        exit;
    }
}

// Check minimum purchase
$min_purchase = $coupon['min_purchase'] ?? 0;
if ($min_purchase > 0 && $subtotal > 0 && $subtotal < $min_purchase) {
    $faltante = $min_purchase - $subtotal;
    echo json_encode([
        'valid' => false,
        'message' => 'Este cupón requiere una compra mínima de $' . number_format($min_purchase, 2, ',', '.') . '. Te faltan $' . number_format($faltante, 2, ',', '.') . ' para aplicarlo.',
        'min_purchase' => $min_purchase,
        'current_subtotal' => $subtotal,
        'missing' => $faltante
    ]);
    exit;
}

// Coupon is valid
echo json_encode([
    'valid' => true,
    'message' => 'Cupón válido.',
    'coupon' => [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'type' => $coupon['type'],
        'value' => $coupon['value'],
        'min_purchase' => $coupon['min_purchase'] ?? 0
    ]
]);
