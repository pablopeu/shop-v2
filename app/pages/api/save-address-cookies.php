<?php
/**
 * Save Address Cookies API - Guarda datos de dirección validada en cookies
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

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// Validar que tenga al menos dirección y código postal
if (empty($data['address']) || empty($data['postal_code'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Address and postal code are required']);
    exit;
}

// Sanitizar datos
$address = sanitize_input($data['address']);
$postal_code = sanitize_input($data['postal_code']);
$city = sanitize_input($data['city'] ?? '');
$province = sanitize_input($data['province'] ?? '');
$country = sanitize_input($data['country'] ?? 'AR');

// Configuración de cookies (1 año)
$cookie_expiry = time() + (365 * 24 * 60 * 60);
$cookie_path = '/';

// Guardar cookies de dirección
setcookie('checkout_address', $address, $cookie_expiry, $cookie_path);
setcookie('checkout_postal_code', $postal_code, $cookie_expiry, $cookie_path);
setcookie('checkout_city', $city, $cookie_expiry, $cookie_path);
setcookie('checkout_state', $province, $cookie_expiry, $cookie_path);
setcookie('checkout_country', $country, $cookie_expiry, $cookie_path);

echo json_encode([
    'success' => true,
    'message' => 'Address saved to cookies successfully'
]);
