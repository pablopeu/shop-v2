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
    // Google Maps API domains added for address validation
    if (!isset($_SESSION['debug_mode']) || !$_SESSION['debug_mode']) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'sha256-RpuWbcoKHtKl7uWamhq6Qvgzi2L1h6KiTLYhudr/mRk=' 'sha256-nc7dWKPKMf6dz8/Sq4nwFHmhcw9wPW/8UFRGat11sR8=' 'unsafe-eval' https://cdnjs.cloudflare.com https://sdk.mercadopago.com https://*.mercadopago.com https://http2.mlstatic.com https://maps.googleapis.com https://*.googleapis.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://*.mercadopago.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://http2.mlstatic.com https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.mercadopago.com https://*.mercadopago.com https://*.mercadolibre.com https://http2.mlstatic.com https://maps.googleapis.com https://*.googleapis.com; frame-src https://*.mercadopago.com;");
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

/**
 * Validar fortaleza de contraseña
 * Implementa política de contraseñas segura según mejores prácticas
 *
 * Requisitos:
 * - Mínimo 12 caracteres
 * - Al menos una mayúscula
 * - Al menos una minúscula
 * - Al menos un número
 * - Al menos un símbolo especial
 *
 * @param string $password Contraseña a validar
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password_strength($password) {
    $errors = [];

    // Longitud mínima
    if (strlen($password) < 12) {
        $errors[] = 'La contraseña debe tener al menos 12 caracteres';
    }

    // Longitud máxima (prevenir DoS)
    if (strlen($password) > 128) {
        $errors[] = 'La contraseña no puede exceder 128 caracteres';
    }

    // Al menos una mayúscula
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Debe contener al menos una letra mayúscula';
    }

    // Al menos una minúscula
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Debe contener al menos una letra minúscula';
    }

    // Al menos un número
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Debe contener al menos un número';
    }

    // Al menos un símbolo especial
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Debe contener al menos un símbolo especial (!@#$%^&*()_+-=[]{}|;:,.<>?)';
    }

    // Verificar que no sea una contraseña común
    $common_passwords = [
        'password123', 'admin123456', 'qwerty123456', '123456789012',
        'password1234', 'Administrator1', 'Welcome@123', 'Password@123'
    ];

    if (in_array(strtolower($password), array_map('strtolower', $common_passwords))) {
        $errors[] = 'Esta contraseña es demasiado común. Elige una contraseña más segura';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'strength_score' => calculate_password_strength_score($password)
    ];
}

/**
 * Calcular score de fortaleza de contraseña (0-100)
 *
 * @param string $password
 * @return int Score de 0 a 100
 */
function calculate_password_strength_score($password) {
    $score = 0;
    $length = strlen($password);

    // Puntos por longitud (máximo 40 puntos)
    $score += min(40, $length * 2);

    // Puntos por variedad de caracteres (15 puntos cada uno, máximo 60)
    if (preg_match('/[a-z]/', $password)) $score += 15;
    if (preg_match('/[A-Z]/', $password)) $score += 15;
    if (preg_match('/[0-9]/', $password)) $score += 15;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 15;

    // Bonus por complejidad (variedad de símbolos)
    $unique_chars = count(array_unique(str_split($password)));
    if ($unique_chars > 10) $score += 10;

    return min(100, $score);
}

/**
 * Obtener mensaje descriptivo del nivel de fortaleza
 *
 * @param int $score Score de 0 a 100
 * @return array ['level' => string, 'message' => string, 'color' => string]
 */
function get_password_strength_level($score) {
    if ($score < 30) {
        return [
            'level' => 'Muy débil',
            'message' => 'Esta contraseña es muy fácil de adivinar',
            'color' => '#dc3545' // rojo
        ];
    } elseif ($score < 50) {
        return [
            'level' => 'Débil',
            'message' => 'Esta contraseña podría ser más segura',
            'color' => '#fd7e14' // naranja
        ];
    } elseif ($score < 70) {
        return [
            'level' => 'Aceptable',
            'message' => 'Contraseña de seguridad media',
            'color' => '#ffc107' // amarillo
        ];
    } elseif ($score < 85) {
        return [
            'level' => 'Fuerte',
            'message' => 'Buena contraseña',
            'color' => '#28a745' // verde
        ];
    } else {
        return [
            'level' => 'Muy fuerte',
            'message' => 'Excelente contraseña',
            'color' => '#20c997' // verde brillante
        ];
    }
}
