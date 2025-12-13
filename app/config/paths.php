<?php
/**
 * Path Configuration
 * Define todas las rutas del sistema
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Paths principales
define('CONFIG_PATH', APP_PATH . '/config');
define('INCLUDES_PATH', APP_PATH . '/includes');
define('PAGES_PATH', APP_PATH . '/pages');
define('DATA_PATH', APP_PATH . '/data');
define('VENDOR_PATH', APP_PATH . '/vendor');

// Public paths
define('ASSETS_PATH', PUBLIC_PATH . '/assets');
define('IMAGES_PATH', PUBLIC_PATH . '/assets/images');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

// Data paths (JSON storage)
define('PRODUCTS_DATA', DATA_PATH . '/products.json');
define('ORDERS_DATA', DATA_PATH . '/orders.json');
define('USERS_DATA', DATA_PATH . '/users.json');
define('CONFIG_DATA', DATA_PATH . '/config');

// Payment credentials path file (stores the location of payment_credentials.json)
define('PAYMENT_CREDENTIALS_PATH_FILE', APP_PATH . '/.payment_credentials_path');
