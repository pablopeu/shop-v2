<?php
/**
 * Admin Logout
 */

define('APP_ENTRY_POINT', true);
// Detectar entorno y cargar bootstrap apropiadamente
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    // Producción
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    // Testing local
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    // Desarrollo
    require_once __DIR__ . '/../app/bootstrap.php';
}

// Define security constant to prevent direct file access


// Destroy session
destroy_admin_session();

// Redirect to login
redirect(url('/admin/login.php?logged_out=1'));
