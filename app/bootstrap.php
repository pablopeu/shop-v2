<?php
/**
 * Bootstrap File
 * Inicializa la aplicación y carga configuración
 *
 * Este archivo es el corazón del sistema. Solo debe ser incluido
 * desde los puntos de entrada autorizados.
 */

// Prevenir acceso directo
if (!defined('APP_ENTRY_POINT')) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Define application paths - Load from config.php first
// El bootstrap está en /app/, entonces __DIR__ es la ruta de app
$bootstrap_app_path = __DIR__;
$config_file = $bootstrap_app_path . '/config/config.php';

// Cargar configuración para obtener paths dinámicos
if (!file_exists($config_file)) {
    die('Configuration file not found. Please run the installer.');
}

$config = require_once $config_file;

// Definir paths desde configuración (instalador los configura dinámicamente)
define('APP_PATH', $config['app_path']);
define('PUBLIC_PATH', $config['public_path']);
define('APP_ROOT', dirname(APP_PATH));

// Load additional path configurations
require_once APP_PATH . '/config/paths.php';

// Load core functions
require_once APP_PATH . '/includes/functions.php';
require_once APP_PATH . '/includes/security.php';
require_once APP_PATH . '/includes/router.php';

// Load business logic includes
require_once APP_PATH . '/includes/products.php';
require_once APP_PATH . '/includes/orders.php';
require_once APP_PATH . '/includes/coupons.php';
require_once APP_PATH . '/includes/promotions.php';
require_once APP_PATH . '/includes/email.php';
require_once APP_PATH . '/includes/pseudo-cron.php';
require_once APP_PATH . '/includes/telegram.php';
require_once APP_PATH . '/includes/mercadopago.php';
require_once APP_PATH . '/includes/mp-logger.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/theme-loader.php';
require_once APP_PATH . '/includes/strings.php';
require_once APP_PATH . '/includes/rate_limit.php';
require_once APP_PATH . '/includes/locks.php';
require_once APP_PATH . '/includes/api_helpers.php';

// Load frontend component functions (render_* helpers)
require_once APP_PATH . '/includes/frontend/product-card.php';
require_once APP_PATH . '/includes/frontend/cart-panel.php';
require_once APP_PATH . '/includes/frontend/favorites-panel.php';
require_once APP_PATH . '/includes/frontend/quantity-selector.php';
require_once APP_PATH . '/includes/frontend/review-card.php';
require_once APP_PATH . '/includes/frontend/currency-toggle.php';
require_once APP_PATH . '/includes/frontend/coupon-form.php';
require_once APP_PATH . '/includes/frontend/breadcrumb.php';
require_once APP_PATH . '/includes/frontend/share-buttons.php';

// NOTE: carousel.php, mobile-menu.php, tracking-events.php, tracking-scripts.php
// are HTML components, NOT function libraries. They should be included in pages, not here.

// Start session securely
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

// Set security headers
set_security_headers();

// Check maintenance mode
if (is_maintenance_mode() && !is_admin_area()) {
    require_once APP_PATH . '/pages/maintenance.php';
    exit;
}

// Run pseudo-cron for email queue processing
// Executes automatically every 60 seconds with minimal overhead
run_pseudo_cron(60);
