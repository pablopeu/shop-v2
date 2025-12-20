<?php
define('APP_ENTRY_POINT', true);

// Cargar configuración de ruta al bootstrap (generada por instalador)
$bootstrap_config = __DIR__ . '/../bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Bootstrap not found']);
        exit;
    }
} else {
    // Fallback para desarrollo (estructura relativa)
    require_once __DIR__ . '/../../../app/bootstrap.php';
}

/**
 * API: Check Session Status
 * Verifies if admin session is still active
 */


header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!is_admin()) {
    http_response_code(401);
    echo json_encode([
        'valid' => false,
        'reason' => 'not_authenticated'
    ]);
    exit;
}

// Check session timeout (2 hours)
if (!check_session_timeout(7200)) {
    http_response_code(401);
    echo json_encode([
        'valid' => false,
        'reason' => 'session_expired'
    ]);
    exit;
}

// Session is valid
echo json_encode([
    'valid' => true,
    'user' => [
        'username' => $_SESSION['username'] ?? 'Unknown',
        'role' => $_SESSION['role'] ?? 'unknown'
    ]
]);
