<?php
/**
 * Admin Logout
 */

define('APP_ENTRY_POINT', true);

// Cargar configuración de ruta al bootstrap (generada por instalador)
$bootstrap_config = __DIR__ . '/bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        die('Bootstrap file not found. Please check your installation.');
    }
} else {
    // Fallback para desarrollo (estructura relativa)
    require_once __DIR__ . '/../../app/bootstrap.php';
}

// Define security constant to prevent direct file access


// Destroy session
destroy_admin_session();

// Redirect to login
redirect(url('/admin/login.php?logged_out=1'));
