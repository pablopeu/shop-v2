<?php
define('APP_ENTRY_POINT', true);
// Bootstrap de la aplicación - detectar entorno
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
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
