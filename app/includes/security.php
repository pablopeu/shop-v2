<?php
/**
 * Security Functions
 * Funciones de seguridad del sistema
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Generate CSP nonce for inline scripts
 * @return string Base64 encoded nonce
 */
function generate_csp_nonce() {
    if (!isset($_SESSION['csp_nonce'])) {
        $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
    }
    return $_SESSION['csp_nonce'];
}

/**
 * Get CSP nonce (helper for templates)
 * @return string Current CSP nonce
 */
function csp_nonce() {
    return $_SESSION['csp_nonce'] ?? '';
}

/**
 * Set security headers
 */
function set_security_headers() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Generate nonce for this request
    $nonce = generate_csp_nonce();

    // CSP (Content Security Policy)
    // Strict CSP with nonces - All inline event handlers converted to event delegation
    // 'unsafe-eval' required for MercadoPago SDK
    // Multiple 'sha256-...' hashes allow MercadoPago SDK inline scripts (different scripts in different contexts)
    // 'unsafe-inline' in style-src permitido (conversion de estilos inline pendiente)
    if (!isset($_SESSION['debug_mode']) || !$_SESSION['debug_mode']) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'sha256-RpuWbcoKHtKl7uWamhq6Qvgzi2L1h6KiTLYhudr/mRk=' 'sha256-nc7dWKPKMf6dz8/Sq4nwFHmhcw9wPW/8UFRGat11sR8=' 'unsafe-eval' https://cdnjs.cloudflare.com https://sdk.mercadopago.com https://*.mercadopago.com https://http2.mlstatic.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://*.mercadopago.com; font-src 'self' https://cdnjs.cloudflare.com https://http2.mlstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.mercadopago.com https://*.mercadopago.com https://*.mercadolibre.com https://http2.mlstatic.com; frame-src https://*.mercadopago.com;");
    }
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    // Check expiry (1 hour)
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random string
 */
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash password securely
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}
