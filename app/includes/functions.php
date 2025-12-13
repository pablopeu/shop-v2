<?php
/**
 * Core Functions
 * Sistema de funciones principales para la aplicación
 *
 * IMPORTANTE: Este archivo es cargado por bootstrap.php
 * No requiere security-check ya que usa APP_ENTRY_POINT
 */

// Config ya se carga en bootstrap.php - no requiere include aquí

// Security headers - Ahora definidas en security.php (evitar duplicación)

// HTTPS Enforcement
function enforce_https() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

/**
 * Read JSON file with proper error handling
 * @param string $file Path to JSON file
 * @param bool $associative Return associative array
 * @return mixed Decoded JSON data or false on error
 */
function read_json($file, $associative = true) {
    if (!file_exists($file)) {
        // Create default structure for known files
        $default_data = get_default_json_structure($file);

        if ($default_data !== null) {
            // Create directory if needed
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Create the file with default structure
            if (write_json($file, $default_data)) {
                error_log("Created missing JSON file with default structure: $file");
            } else {
                error_log("JSON file not found and could not create: $file");
                return $associative ? [] : new stdClass();
            }
        } else {
            error_log("JSON file not found: $file");
            return $associative ? [] : new stdClass();
        }
    }

    $fp = fopen($file, 'r');
    if (!$fp) {
        error_log("Cannot open file for reading: $file");
        return $associative ? [] : new stdClass();
    }

    // Acquire shared lock for reading
    if (flock($fp, LOCK_SH)) {
        $content = fread($fp, filesize($file) ?: 1);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($content, $associative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in $file: " . json_last_error_msg());
            return $associative ? [] : new stdClass();
        }

        return $data;
    }

    fclose($fp);
    error_log("Cannot acquire lock for reading: $file");
    return $associative ? [] : new stdClass();
}

/**
 * Write JSON file with file locking
 * @param string $file Path to JSON file
 * @param mixed $data Data to write
 * @param bool $pretty Pretty print JSON
 * @return bool Success status
 */
function write_json($file, $data, $pretty = true) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty) {
        $flags |= JSON_PRETTY_PRINT;
    }

    $json = json_encode($data, $flags);
    if ($json === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        return false;
    }

    // Create directory if it doesn't exist
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'w');
    if (!$fp) {
        error_log("Cannot open file for writing: $file");
        return false;
    }

    // Acquire exclusive lock for writing
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    fclose($fp);
    error_log("Cannot acquire lock for writing: $file");
    return false;
}

/**
 * Get default JSON structure for known files
 * @param string $file Path to JSON file
 * @return mixed Default structure or null if unknown
 */
function get_default_json_structure($file) {
    $basename = basename($file);

    switch ($basename) {
        case 'reviews.json':
            return ['reviews' => []];

        case 'archived_orders.json':
            return ['orders' => []];

        case 'orders.json':
            return ['orders' => []];

        case 'products.json':
            return ['products' => []];

        case 'coupons.json':
            return ['coupons' => []];

        case 'promotions.json':
            return ['promotions' => []];

        case 'wishlists.json':
            return ['wishlists' => []];

        case 'newsletters.json':
            return ['subscribers' => []];

        case 'admin_logs.json':
            return ['logs' => []];

        case 'stock_logs.json':
            return ['logs' => []];

        case 'visits.json':
            return ['visits' => []];

        case 'webhook_log.json':
            return ['logs' => []];

        case 'webhook_rate_limit.json':
            return ['limits' => []];

        case 'mp_preference_log.json':
            return ['preferences' => []];

        case 'maintenance.json':
            return [
                'enabled' => false,
                'bypass_code' => '',
                'message' => 'Sitio en mantenimiento. Volveremos pronto.'
            ];

        case 'theme.json':
            return ['active_theme' => 'minimal'];

        case 'carousel.json':
            return ['slides' => []];

        default:
            return null;
    }
}

// sanitize_input() y validate_email() - Ahora definidas en security.php (evitar duplicación)

/**
 * Generate unique ID
 * @param string $prefix Prefix for ID
 * @return string Unique ID
 */
function generate_id($prefix = '') {
    return $prefix . uniqid() . '-' . bin2hex(random_bytes(4));
}

/**
 * Generate secure token
 * @param int $length Token length
 * @return string Secure token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// generate_csrf_token() y validate_csrf_token() - Ahora definidas en security.php (evitar duplicación)

/**
 * Get current timestamp in ISO 8601 format
 * @return string ISO 8601 timestamp
 */
function get_timestamp() {
    return date('c'); // ISO 8601 format
}

/**
 * Format price for display
 * @param float $price Price
 * @param string $currency Currency code
 * @return string Formatted price
 */
function format_price($price, $currency = 'ARS') {
    $symbols = [
        'ARS' => '$',
        'USD' => 'U$D'
    ];

    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format($price, 2, ',', '.');
}

/**
 * Format product price intelligently based on available prices
 * If only USD price exists, shows USD with ARS in parentheses
 * Otherwise follows the selected currency
 *
 * @param array $product Product with price_ars and price_usd
 * @param string $selected_currency User's selected currency (ARS or USD)
 * @return string Formatted price string
 */
function format_product_price($product, $selected_currency = 'ARS') {
    $price_ars = floatval($product['price_ars'] ?? 0);
    $price_usd = floatval($product['price_usd'] ?? 0);

    // If product only has USD price (no ARS price)
    if ($price_usd > 0 && $price_ars == 0) {
        // Calculate ARS equivalent
        $currency_config = read_json(__DIR__ . '/../config/currency.json');
        $exchange_rate = $currency_config['exchange_rate'] ?? 1500;
        $calculated_ars = $price_usd * $exchange_rate;

        return 'U$D ' . number_format($price_usd, 2, ',', '.') .
               ' <span class="price-conversion">(' .
               format_price($calculated_ars, 'ARS') . ')</span>';
    }

    // If product only has ARS price (no USD price)
    if ($price_ars > 0 && $price_usd == 0) {
        return format_price($price_ars, 'ARS');
    }

    // Product has both prices - use selected currency
    $price = $selected_currency === 'USD' ? $price_usd : $price_ars;
    return format_price($price, $selected_currency);
}

/**
 * Convert price between currencies
 * @param float $price Price to convert
 * @param string $from Source currency
 * @param string $to Target currency
 * @return float Converted price
 */
function convert_currency($price, $from, $to) {
    if ($from === $to) {
        return $price;
    }

    $currency_config = read_json(__DIR__ . '/../config/currency.json');
    $rate = $currency_config['exchange_rate'] ?? 1000;

    if ($from === 'ARS' && $to === 'USD') {
        return $price / $rate;
    } elseif ($from === 'USD' && $to === 'ARS') {
        return $price * $rate;
    }

    return $price;
}

/**
 * Get exchange rate from DolarAPI
 * @param string $type Type of dollar rate: 'blue', 'oficial', or 'bolsa'
 * @return array|null Returns array with rate data or null on failure
 */
function get_dolarapi_rate($type = 'blue') {
    // Validate type
    $valid_types = ['blue', 'oficial', 'bolsa'];
    if (!in_array($type, $valid_types)) {
        error_log("DolarAPI: Invalid type '$type', defaulting to 'blue'");
        $type = 'blue';
    }

    $api_url = "https://dolarapi.com/v1/dolares/{$type}";

    // Set timeout and user agent
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
            'ignore_errors' => true
        ]
    ]);

    try {
        $response = @file_get_contents($api_url, false, $context);

        if ($response === false) {
            error_log("DolarAPI: Failed to fetch data for type '{$type}'");
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['venta'])) {
            error_log("DolarAPI: Invalid response format for type '{$type}'");
            return null;
        }

        return [
            'compra' => $data['compra'] ?? 0,
            'venta' => $data['venta'] ?? 0,
            'casa' => $data['casa'] ?? '',
            'nombre' => $data['nombre'] ?? '',
            'moneda' => $data['moneda'] ?? 'USD',
            'fechaActualizacion' => $data['fechaActualizacion'] ?? date('Y-m-d\TH:i:s\Z'),
            'type' => $type
        ];

    } catch (Exception $e) {
        error_log("DolarAPI Exception for type '{$type}': " . $e->getMessage());
        return null;
    }
}

/**
 * Get exchange rate from Bluelytics API (deprecated - kept for backward compatibility)
 * @deprecated Use get_dolarapi_rate() instead
 * @return array|null Returns array with 'blue' rate or null on failure
 */
function get_bluelytics_rate() {
    $api_url = 'https://api.bluelytics.com.ar/v2/latest';

    // Set timeout and user agent
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
            'ignore_errors' => true
        ]
    ]);

    try {
        $response = @file_get_contents($api_url, false, $context);

        if ($response === false) {
            error_log('Bluelytics API: Failed to fetch data');
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['blue']['value_sell'])) {
            error_log('Bluelytics API: Invalid response format');
            return null;
        }

        return [
            'blue' => $data['blue']['value_sell'],
            'oficial' => $data['oficial']['value_sell'] ?? null,
            'timestamp' => $data['last_update'] ?? date('Y-m-d\TH:i:s\Z')
        ];

    } catch (Exception $e) {
        error_log('Bluelytics API Exception: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update exchange rate from API or manual override
 * @param bool $force_update Force update even if recently updated
 * @return bool Success
 */
function update_exchange_rate($force_update = false) {
    $config_file = __DIR__ . '/../config/currency.json';
    $config = read_json($config_file);

    // If API is not enabled, don't update
    if (!($config['api_enabled'] ?? false)) {
        return false;
    }

    // Check if we need to update (only if more than 30 minutes old)
    if (!$force_update && isset($config['last_update'])) {
        $last_update = strtotime($config['last_update']);
        $now = time();
        if ($now - $last_update < 1800) { // 30 minutes
            return false; // Too recent, skip update
        }
    }

    // Get the selected dollar type (default to 'blue')
    $dollar_type = $config['dollar_type'] ?? 'blue';

    // Get rate from DolarAPI
    $api_data = get_dolarapi_rate($dollar_type);

    if ($api_data === null) {
        return false; // API failed
    }

    // Update config only if no manual override is active
    if (!($config['manual_override'] ?? false)) {
        $config['exchange_rate'] = $api_data['venta'];
        $config['exchange_rate_source'] = 'api';
    }

    // Always update API values for display
    $config['api_compra'] = $api_data['compra'];
    $config['api_venta'] = $api_data['venta'];
    $config['api_casa'] = $api_data['casa'];
    $config['api_nombre'] = $api_data['nombre'];
    $config['last_update'] = $api_data['fechaActualizacion'];

    return write_json($config_file, $config);
}

/**
 * Get current exchange rate (updates from API if needed)
 * @return float Current exchange rate
 */
function get_current_exchange_rate() {
    // Try to update from API if enabled
    update_exchange_rate();

    $config = read_json(__DIR__ . '/../config/currency.json');
    return $config['exchange_rate'] ?? 1000;
}

/**
 * Generate slug from string
 * @param string $string Input string
 * @return string URL-friendly slug
 */
function generate_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[áàäâ]/', 'a', $string);
    $string = preg_replace('/[éèëê]/', 'e', $string);
    $string = preg_replace('/[íìïî]/', 'i', $string);
    $string = preg_replace('/[óòöô]/', 'o', $string);
    $string = preg_replace('/[úùüû]/', 'u', $string);
    $string = preg_replace('/[ñ]/', 'n', $string);
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

/**
 * Log admin action
 * @param string $action Action description
 * @param string $user User who performed action
 * @param array $details Additional details
 */
function log_admin_action($action, $user, $details = []) {
    $log_file = __DIR__ . '/../data/admin_logs.json';
    $logs = read_json($log_file);

    if (!isset($logs['logs'])) {
        $logs = ['logs' => []];
    }

    $logs['logs'][] = [
        'timestamp' => get_timestamp(),
        'action' => $action,
        'user' => $user,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    // Keep only last 1000 logs
    if (count($logs['logs']) > 1000) {
        $logs['logs'] = array_slice($logs['logs'], -1000);
    }

    write_json($log_file, $logs);
}

/**
 * Check if maintenance mode is enabled
 * @return bool Maintenance mode status
 */
function is_maintenance_mode() {
    $maintenance = read_json(__DIR__ . '/../config/maintenance.json');

    if (!isset($maintenance['enabled']) || !$maintenance['enabled']) {
        return false;
    }

    // Check bypass code
    if (isset($_GET['bypass']) && $_GET['bypass'] === $maintenance['bypass_code']) {
        return false;
    }

    // Check allowed IPs
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($client_ip, $maintenance['allowed_ips'] ?? [])) {
        return false;
    }

    return true;
}

/**
 * Check if current request is for admin area
 * @return bool True if admin area
 */
function is_admin_area() {
    return defined('ADMIN_AREA') && ADMIN_AREA === true;
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to (automatically applies BASE_PATH to internal paths)
 */
function redirect($url) {
    // If it's an internal path (starts with /), apply url()
    // External URLs (http://, https://) are left as-is
    if (!empty($url) && $url[0] === '/' && strpos($url, '//') !== 0) {
        $url = url($url);
    }
    header("Location: $url");
    exit;
}

/**
 * Get base URL
 * @return string Base URL
 */
function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

/**
 * Build URL with BASE_PATH
 * @param string $path Path relative to base (e.g., '/admin', '/producto/slug')
 * @return string Full URL with base path
 */
function url($path = '') {
    // Si la ruta ya es absoluta (http://, https://, //), devolverla sin modificar
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }

    // Si la ruta es solo un archivo (sin /), devolverla sin modificar
    if (!empty($path) && $path[0] !== '/') {
        return $path;
    }

    // Cargar configuración para obtener base_path
    static $base_path = null;
    if ($base_path === null) {
        $config_file = __DIR__ . '/../config/config.php';
        if (file_exists($config_file)) {
            $config = require $config_file;
            $base_path = $config['base_path'] ?? '';
        } else {
            $base_path = '';
        }
        // Asegurar que base_path no termine en /
        $base_path = rtrim($base_path, '/');
    }

    // Si el path ya empieza con base_path, no duplicar (idempotencia)
    if (!empty($base_path) && strpos($path, $base_path . '/') === 0) {
        return $path;
    }

    // Construir URL: base_path + path
    return $base_path . $path;
}

/**
 * Check if user is logged in
 * @return bool Login status
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Usar comparación flexible para logged_in (puede ser 1 o true)
    // No requerir user_id porque puede estar vacío en algunos casos
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true;
}

/**
 * Check if user is admin
 * @return bool Admin status
 */
function is_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin authentication
 */
function require_admin() {
    if (!is_admin()) {
        redirect(url('/admin/login.php'));
    }

    // Check session timeout (default: 2 hours)
    require_once __DIR__ . '/auth.php';
    if (!check_session_timeout(7200)) {
        redirect(url('/admin/login.php?timeout=1'));
    }
}

/**
 * Get logged user data
 * @return array|null User data
 */
function get_logged_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Validate if a theme exists and has required files
 * @param string $theme_slug Theme slug to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_theme($theme_slug) {
    // Sanitize theme slug
    $theme_slug = preg_replace('/[^a-z0-9_-]/i', '', $theme_slug);

    if (empty($theme_slug)) {
        return [
            'valid' => false,
            'message' => 'Theme slug is empty'
        ];
    }

    $theme_path = PUBLIC_PATH . '/assets/themes/' . $theme_slug;

    // Check if theme directory exists
    if (!is_dir($theme_path)) {
        return [
            'valid' => false,
            'message' => "Theme directory does not exist: {$theme_slug}"
        ];
    }

    // Check required files
    $required_files = [
        'variables.css',
        'theme.css',
        'theme.json'
    ];

    foreach ($required_files as $file) {
        if (!file_exists($theme_path . '/' . $file)) {
            return [
                'valid' => false,
                'message' => "Missing required file: {$file}"
            ];
        }
    }

    return [
        'valid' => true,
        'message' => 'Theme is valid'
    ];
}

/**
 * Render site logo or site name
 * Muestra el logo si está configurado, sino muestra el nombre del sitio
 * @param array $site_config Site configuration
 * @return void Echoes HTML
 */
function render_site_logo($site_config, $css_class = '') {
    if (!empty($site_config['logo']['enabled']) && !empty($site_config['logo']['path'])) {
        // Render logo image
        $logo_path = htmlspecialchars(url($site_config['logo']['path']));
        $logo_alt = htmlspecialchars($site_config['logo']['alt'] ?? $site_config['site_name']);
        $logo_width = (int)($site_config['logo']['width'] ?? 170);
        $logo_height = (int)($site_config['logo']['height'] ?? 85);
        $class_attr = $css_class ? ' class="' . htmlspecialchars($css_class) . '"' : '';

        $default_class = 'site-logo-img';
        $final_class = $css_class ? $css_class . ' ' . $default_class : $default_class;

        echo '<img src="' . $logo_path . '"
                   alt="' . $logo_alt . '"
                   width="' . $logo_width . '"
                   height="' . $logo_height . '"
                   class="' . htmlspecialchars($final_class) . '">';
    } else {
        // Render site name as text
        echo '<h1>' . htmlspecialchars($site_config['site_name']) . '</h1>';
    }
}

/**
 * Render meta tags for SEO and social media
 * Renders Open Graph and Twitter Card meta tags
 * @param array $site_config Site configuration
 * @return void Echoes HTML meta tags
 */
function render_meta_tags($site_config) {
    $meta_tags = $site_config['meta_tags'] ?? [];

    // Get base URL for absolute URLs (required for Open Graph)
    $base_url = get_base_url();
    $site_url = $base_url . url('/');

    // Get default values from site config if meta_tags not set
    $og_title = $meta_tags['og_title'] ?? $site_config['site_name'] ?? '';
    $og_type = $meta_tags['og_type'] ?? 'website';
    $og_url = $meta_tags['og_url'] ?? $site_url;
    $og_url_secure = $meta_tags['og_url_secure'] ?? $site_url;
    $og_image = $meta_tags['og_image'] ?? '';
    $og_site_name = $meta_tags['og_site_name'] ?? $site_config['site_name'] ?? '';
    $og_description = $meta_tags['og_description'] ?? $site_config['site_description'] ?? '';
    $content_type = $meta_tags['content_type'] ?? 'text/html; charset=utf-8';
    $og_image_width = $meta_tags['og_image_width'] ?? '1280';
    $og_image_height = $meta_tags['og_image_height'] ?? '960';
    $twitter_card = $meta_tags['twitter_card'] ?? 'summary_large_image';

    // Convert ALL URLs to absolute if they're relative (not just defaults)
    if ($og_url && !preg_match('#^https?://#', $og_url)) {
        $og_url = $base_url . $og_url;
    }
    if ($og_url_secure && !preg_match('#^https?://#', $og_url_secure)) {
        $og_url_secure = $base_url . $og_url_secure;
    }
    if ($og_image && !preg_match('#^https?://#', $og_image)) {
        $og_image = $base_url . $og_image;
    }

    // Render meta tags
    if ($og_title) {
        echo '<meta property="og:title" content="' . htmlspecialchars($og_title) . '">' . "\n    ";
    }
    if ($og_type) {
        echo '<meta property="og:type" content="' . htmlspecialchars($og_type) . '">' . "\n    ";
    }
    if ($og_url) {
        echo '<meta property="og:url" content="' . htmlspecialchars($og_url) . '">' . "\n    ";
    }
    if ($og_url_secure) {
        echo '<meta property="og:url:secure_url" content="' . htmlspecialchars($og_url_secure) . '">' . "\n    ";
    }
    if ($og_image) {
        echo '<meta property="og:image" content="' . htmlspecialchars($og_image) . '">' . "\n    ";
    }
    if ($og_site_name) {
        echo '<meta property="og:site_name" content="' . htmlspecialchars($og_site_name) . '">' . "\n    ";
    }
    if ($og_description) {
        echo '<meta property="og:description" content="' . htmlspecialchars($og_description) . '">' . "\n    ";
    }
    if ($content_type) {
        echo '<meta http-equiv="content-type" content="' . htmlspecialchars($content_type) . '">' . "\n    ";
    }
    if ($og_image && $og_image_width) {
        echo '<meta property="og:image:width" content="' . htmlspecialchars($og_image_width) . '">' . "\n    ";
    }
    if ($og_image && $og_image_height) {
        echo '<meta property="og:image:height" content="' . htmlspecialchars($og_image_height) . '">' . "\n    ";
    }
    if ($twitter_card) {
        echo '<meta name="twitter:card" content="' . htmlspecialchars($twitter_card) . '">' . "\n    ";
    }
}

/**
 * Render custom footer HTML
 * Muestra el footer personalizado si está configurado, sino muestra el footer por defecto
 * @param array $site_config Site configuration
 * @param array $footer_config Footer configuration
 * @return void Echoes HTML
 */
function render_footer($site_config, $footer_config) {
    // Check if advanced footer is configured
    if (!empty($footer_config['enabled']) && $footer_config['type'] === 'advanced') {
        // Obtener colores del theme activo
        $theme_config = read_json(APP_PATH . '/config/theme.json');
        $active_theme = $theme_config['active_theme'] ?? 'classic';
        $theme = get_theme_config($active_theme);

        // Usar colores del theme para el footer
        $bg_color = $theme['footer']['background_color'] ?? '#292c2f';
        $text_color = $theme['footer']['text_color'] ?? '#ffffff';

        $style = 'style="background-color: ' . htmlspecialchars($bg_color) . '; color: ' . htmlspecialchars($text_color) . ';"';

        // Render advanced 3-column footer
        echo '<div class="footer-distributed" ' . $style . '>';

        // Left Column
        echo '<div class="footer-left">';

        // Logo
        if (!empty($footer_config['left_column']['logo']['enabled']) && !empty($footer_config['left_column']['logo']['path'])) {
            $logo = $footer_config['left_column']['logo'];
            echo '<img src="' . htmlspecialchars(url($logo['path'])) . '" ';
            echo 'alt="' . htmlspecialchars($logo['alt'] ?? 'Logo') . '" ';
            echo 'height="' . (int)($logo['height'] ?? 83) . '" ';
            echo 'width="' . (int)($logo['width'] ?? 169) . '">';
        }

        // Links
        if (!empty($footer_config['left_column']['links'])) {
            echo '<p class="footer-links">';
            $first = true;
            foreach ($footer_config['left_column']['links'] as $link) {
                if (!$first) echo ' | ';
                echo '<a href="' . htmlspecialchars($link['url']) . '">' . htmlspecialchars($link['text']) . '</a>';
                $first = false;
            }
            echo '</p>';
        }

        // Email (sin icono, solo texto y link)
        if (!empty($footer_config['left_column']['email']['enabled']) && !empty($footer_config['left_column']['email']['address'])) {
            $email = $footer_config['left_column']['email'];
            $subject = urlencode($email['subject'] ?? 'Consulta desde el sitio web');
            $body = !empty($email['body']) ? '&Body=' . urlencode($email['body']) : '';
            echo '<div>';
            echo '<a href="mailto:' . htmlspecialchars($email['address']) . '?Subject=' . $subject . $body . '" class="email-link">';
            echo htmlspecialchars($email['address']);
            echo '</a>';
            echo '</div>';
        }

        // WhatsApp (sin icono, solo texto configurable y link)
        if (!empty($footer_config['left_column']['whatsapp']['enabled']) && !empty($site_config['whatsapp']['enabled'])) {
            $whatsapp = $site_config['whatsapp'];

            // Usar custom_link si está configurado, sino generar link con número
            if (!empty($whatsapp['custom_link'])) {
                $wa_link = $whatsapp['custom_link'];
            } else if (!empty($whatsapp['number'])) {
                $number = preg_replace('/[^0-9]/', '', $whatsapp['number']);
                $message = urlencode($whatsapp['message'] ?? 'Hola, consulta desde el sitio web');
                $wa_link = 'https://wa.me/' . $number . '?text=' . $message;
            } else {
                $wa_link = '#';
            }

            $display_text = $whatsapp['display_text'] ?? $whatsapp['number'] ?? 'WhatsApp';
            echo '<div>';
            echo '<a href="' . htmlspecialchars($wa_link) . '" target="_blank" class="whatsapp-link">';
            echo htmlspecialchars($display_text);
            echo '</a>';
            echo '</div>';
        }

        echo '</div>'; // End footer-left

        // Center Column
        echo '<div class="footer-center">';

        // Address
        if (!empty($footer_config['center_column']['address']['enabled'])) {
            $address = $footer_config['center_column']['address'];
            if (!empty($address['street']) || !empty($address['city'])) {
                echo '<div>';
                if (!empty($address['map_url'])) {
                    echo '<a href="' . htmlspecialchars($address['map_url']) . '" target="_blank"><i class="fas fa-map-marker-alt"></i></a>';
                } else {
                    echo '<i class="fas fa-map-marker-alt"></i>';
                }
                echo '<p>';
                if (!empty($address['street'])) {
                    echo '<span>' . htmlspecialchars($address['street']) . '</span>';
                }
                if (!empty($address['city']) || !empty($address['country'])) {
                    $location = trim(($address['city'] ?? '') . ', ' . ($address['country'] ?? ''), ', ');
                    if ($location) {
                        echo '<span>' . htmlspecialchars($location) . '</span>';
                    }
                }
                echo '</p>';
                echo '</div>';
            }
        }

        // Phones
        if (!empty($footer_config['center_column']['phones'])) {
            echo '<div>';
            echo '<i class="fas fa-phone"></i>';
            echo '<p>';
            foreach ($footer_config['center_column']['phones'] as $phone) {
                if (!empty($phone['number'])) {
                    echo '<span>' . htmlspecialchars($phone['number']);
                    if (!empty($phone['label'])) {
                        echo ' (' . htmlspecialchars($phone['label']) . ')';
                    }
                    echo '</span>';
                }
            }
            echo '</p>';
            echo '</div>';
        }

        // WhatsApp en sección central (con icono)
        if (!empty($footer_config['center_column']['whatsapp']['enabled']) && !empty($site_config['whatsapp']['enabled'])) {
            $whatsapp = $site_config['whatsapp'];

            // Usar custom_link si está configurado, sino generar link con número
            if (!empty($whatsapp['custom_link'])) {
                $wa_link = $whatsapp['custom_link'];
            } else if (!empty($whatsapp['number'])) {
                $number = preg_replace('/[^0-9]/', '', $whatsapp['number']);
                $message = urlencode($whatsapp['message'] ?? 'Hola, consulta desde el sitio web');
                $wa_link = 'https://wa.me/' . $number . '?text=' . $message;
            } else {
                $wa_link = '#';
            }

            $display_text = $whatsapp['display_text'] ?? $whatsapp['number'] ?? 'WhatsApp';
            echo '<div>';
            echo '<i class="fab fa-whatsapp"></i>';
            echo '<p>';
            echo '<span><a href="' . htmlspecialchars($wa_link) . '" target="_blank" class="whatsapp-link">';
            echo htmlspecialchars($display_text);
            echo '</a></span>';
            echo '</p>';
            echo '</div>';
        }

        // Schedule
        if (!empty($footer_config['center_column']['schedule']['enabled'])) {
            $schedule = $footer_config['center_column']['schedule'];
            if (!empty($schedule['days']) || !empty($schedule['hours'])) {
                echo '<div>';
                echo '<i class="far fa-clock"></i>';
                echo '<p>';
                if (!empty($schedule['days'])) {
                    echo '<span>' . htmlspecialchars($schedule['days']) . '</span>';
                }
                if (!empty($schedule['hours'])) {
                    echo '<span>' . htmlspecialchars($schedule['hours']) . '</span>';
                }
                echo '</p>';
                echo '</div>';
            }
        }

        echo '</div>'; // End footer-center

        // Right Column
        echo '<div class="footer-right">';

        // About
        if (!empty($footer_config['right_column']['about']['enabled'])) {
            $about = $footer_config['right_column']['about'];
            echo '<p class="footer-company-about">';
            if (!empty($about['title'])) {
                echo '<span>' . htmlspecialchars($about['title']) . '</span>';
            }
            if (!empty($about['text'])) {
                echo nl2br(htmlspecialchars($about['text']));
            }
            echo '</p>';
        }

        // Social Media - Orden: Facebook / X / Instagram / WhatsApp / Telegram
        if (!empty($footer_config['right_column']['social']['enabled'])) {
            $social = $footer_config['right_column']['social'];
            // Check if WhatsApp should be shown in social media
            $show_whatsapp = (!empty($social['whatsapp']['enabled']) || (!is_array($social['whatsapp'] ?? null) && !empty($social['whatsapp']))) && !empty($site_config['whatsapp']['enabled']);
            $has_social = !empty($social['facebook']) || !empty($social['twitter']) ||
                         !empty($social['instagram']) || $show_whatsapp || !empty($social['telegram']);

            if ($has_social) {
                echo '<div class="footer-icons">';

                // Facebook
                if (!empty($social['facebook'])) {
                    echo '<a href="' . htmlspecialchars($social['facebook']) . '" target="_blank" class="social-icon facebook"><i class="fab fa-facebook-f"></i></a>';
                }
                // X (Twitter)
                if (!empty($social['twitter'])) {
                    echo '<a href="' . htmlspecialchars($social['twitter']) . '" target="_blank" class="social-icon x-twitter"><i class="fab fa-x-twitter"></i></a>';
                }
                // Instagram
                if (!empty($social['instagram'])) {
                    echo '<a href="' . htmlspecialchars($social['instagram']) . '" target="_blank" class="social-icon instagram"><i class="fab fa-instagram"></i></a>';
                }
                // WhatsApp - read from central site config
                if ($show_whatsapp) {
                    $whatsapp = $site_config['whatsapp'];
                    // Usar custom_link si está configurado, sino generar link con número
                    if (!empty($whatsapp['custom_link'])) {
                        $wa_link = htmlspecialchars($whatsapp['custom_link']);
                    } else if (!empty($whatsapp['number'])) {
                        $wa_number = preg_replace('/[^0-9]/', '', $whatsapp['number']);
                        $wa_link = 'https://wa.me/' . $wa_number;
                    } else {
                        $wa_link = '#';
                    }
                    echo '<a href="' . $wa_link . '" target="_blank" class="social-icon whatsapp"><i class="fab fa-whatsapp"></i></a>';
                }
                // Telegram
                if (!empty($social['telegram'])) {
                    echo '<a href="' . htmlspecialchars($social['telegram']) . '" target="_blank" class="social-icon telegram"><i class="fab fa-telegram-plane"></i></a>';
                }

                echo '</div>';
            }
        }

        echo '</div>'; // End footer-right

        echo '</div>'; // End footer-distributed

    } else {
        // Default simple footer
        echo '<div class="container">';
        echo '    <div class="footer-content">';
        echo '        <div class="footer-section">';
        echo '            <h3>' . htmlspecialchars($site_config['site_name']) . '</h3>';
        echo '            <p>' . htmlspecialchars($site_config['site_description']) . '</p>';
        echo '        </div>';
        echo '        <div class="footer-section">';
        echo '            <h4>Contacto</h4>';
        if (!empty($site_config['contact_email'])) {
            echo '            <p>Email: ' . htmlspecialchars($site_config['contact_email']) . '</p>';
        }
        if (!empty($site_config['contact_phone'])) {
            echo '            <p>Teléfono: ' . htmlspecialchars($site_config['contact_phone']) . '</p>';
        }
        echo '        </div>';
        echo '        <div class="footer-section">';
        echo '            <h4>Enlaces</h4>';
        echo '            <a href="' . url('/') . '">Inicio</a><br>';
        echo '            <a href="' . url('/buscar') . '">Buscar</a><br>';
        echo '            <a href="' . url('/favoritos') . '">Favoritos</a>';
        echo '        </div>';
        echo '    </div>';
        echo '    <div class="footer-bottom">';
        echo '        <p>' . htmlspecialchars($site_config['footer_text'] ?? '© ' . date('Y') . ' ' . $site_config['site_name'] . '. Todos los derechos reservados.') . '</p>';
        echo '    </div>';
        echo '</div>';
    }
}

/**
 * Get user-friendly payment error message and details based on Mercadopago status_detail
 *
 * @param string $status Payment status (approved, rejected, pending, etc.)
 * @param string $status_detail Detailed status from Mercadopago
 * @return array ['title' => string, 'message' => string, 'icon' => string, 'suggestions' => array]
 */
function get_payment_message($status, $status_detail = '') {
    // Default messages
    $messages = [
        'title' => 'Error en el pago',
        'message' => 'No se pudo procesar el pago',
        'icon' => '❌',
        'suggestions' => [
            'Verifica los datos de tu tarjeta',
            'Intenta con otro medio de pago',
            'Contáctanos si el problema persiste'
        ]
    ];

    // Handle approved payments
    if ($status === 'approved') {
        return [
            'title' => '¡Pago aprobado!',
            'message' => 'Tu pago fue procesado exitosamente',
            'icon' => '✅',
            'suggestions' => []
        ];
    }

    // Handle authorized payments (pending capture)
    if ($status === 'authorized') {
        return [
            'title' => 'Pago autorizado',
            'message' => 'Tu pago ha sido autorizado y está pendiente de confirmación final.',
            'icon' => '🔐',
            'suggestions' => [
                'El pago será confirmado automáticamente',
                'Recibirás una notificación cuando se complete',
                'Este proceso es normal y seguro'
            ]
        ];
    }

    // Handle in_mediation payments (disputes)
    if ($status === 'in_mediation') {
        return [
            'title' => 'Pago en mediación',
            'message' => 'Tu pago está siendo revisado debido a una disputa.',
            'icon' => '⚖️',
            'suggestions' => [
                'El equipo de Mercadopago está revisando el caso',
                'Te contactaremos si necesitamos más información',
                'Recibirás una notificación con la resolución'
            ]
        ];
    }

    // Handle pending payments
    if ($status === 'pending' || $status === 'in_process') {
        $pending_messages = [
            'pending_contingency' => [
                'title' => 'Pago pendiente',
                'message' => 'Estamos procesando tu pago. Te avisaremos cuando esté aprobado.',
                'icon' => '⏳',
                'suggestions' => [
                    'El proceso puede tomar unos minutos',
                    'Recibirás una confirmación por email',
                    'Puedes verificar el estado desde "Seguir mi pedido"'
                ]
            ],
            'pending_review_manual' => [
                'title' => 'Pago en revisión',
                'message' => 'Tu pago está siendo revisado por el equipo de Mercadopago.',
                'icon' => '🔍',
                'suggestions' => [
                    'La revisión puede tomar hasta 48 horas',
                    'Te notificaremos por email cuando se apruebe',
                    'No es necesario que hagas nada más'
                ]
            ]
        ];

        if (isset($pending_messages[$status_detail])) {
            return $pending_messages[$status_detail];
        }

        return [
            'title' => 'Pago pendiente',
            'message' => 'Tu pago está siendo procesado',
            'icon' => '⏳',
            'suggestions' => [
                'Recibirás una confirmación cuando se apruebe',
                'Puedes hacer seguimiento desde tu email',
                'El proceso es automático'
            ]
        ];
    }

    // Handle rejected payments - specific error messages
    if ($status === 'rejected') {
        $rejection_messages = [
            'cc_rejected_bad_filled_card_number' => [
                'title' => 'Número de tarjeta incorrecto',
                'message' => 'El número de tarjeta que ingresaste no es válido',
                'icon' => '💳',
                'suggestions' => [
                    'Verifica que hayas ingresado los 16 dígitos correctamente',
                    'Asegúrate de no incluir espacios ni guiones',
                    'Revisa que la tarjeta no esté vencida'
                ]
            ],
            'cc_rejected_bad_filled_date' => [
                'title' => 'Fecha de vencimiento incorrecta',
                'message' => 'La fecha de vencimiento de la tarjeta no es válida',
                'icon' => '📅',
                'suggestions' => [
                    'Verifica el mes y año de vencimiento en tu tarjeta',
                    'Asegúrate de que la tarjeta no esté vencida',
                    'Intenta con otra tarjeta si esta ya expiró'
                ]
            ],
            'cc_rejected_bad_filled_security_code' => [
                'title' => 'Código de seguridad incorrecto',
                'message' => 'El código CVV/CVC que ingresaste no coincide',
                'icon' => '🔒',
                'suggestions' => [
                    'El CVV son los 3 dígitos al dorso de tu tarjeta',
                    'En tarjetas AMEX son 4 dígitos en el frente',
                    'Verifica que estés ingresando el código correcto'
                ]
            ],
            'cc_rejected_bad_filled_other' => [
                'title' => 'Datos incorrectos',
                'message' => 'Hay un error en los datos de la tarjeta',
                'icon' => '⚠️',
                'suggestions' => [
                    'Revisa todos los campos del formulario',
                    'Verifica nombre, número, fecha y CVV',
                    'Asegúrate de completar todos los campos requeridos'
                ]
            ],
            'cc_rejected_blacklist' => [
                'title' => 'Tarjeta no permitida',
                'message' => 'Esta tarjeta no puede ser utilizada para pagos en línea',
                'icon' => '🚫',
                'suggestions' => [
                    'Contacta a tu banco para más información',
                    'Intenta con otra tarjeta o medio de pago',
                    'Puedes elegir pago presencial en el checkout'
                ]
            ],
            'cc_rejected_call_for_authorize' => [
                'title' => 'Autorización requerida',
                'message' => 'Tu banco requiere que autorices esta compra',
                'icon' => '📞',
                'suggestions' => [
                    'Comunícate con tu banco para autorizar el pago',
                    'Es posible que necesites confirmar la operación',
                    'Intenta nuevamente después de hablar con tu banco'
                ]
            ],
            'cc_rejected_card_disabled' => [
                'title' => 'Tarjeta deshabilitada',
                'message' => 'Tu tarjeta está deshabilitada para compras en línea',
                'icon' => '⛔',
                'suggestions' => [
                    'Contacta a tu banco para habilitar compras online',
                    'Verifica que la tarjeta no esté bloqueada',
                    'Intenta con otra tarjeta mientras tanto'
                ]
            ],
            'cc_rejected_card_error' => [
                'title' => 'Error de tarjeta',
                'message' => 'Hubo un problema al procesar tu tarjeta',
                'icon' => '❌',
                'suggestions' => [
                    'Verifica que la tarjeta esté activa',
                    'Contacta a tu banco si el problema persiste',
                    'Intenta con otro medio de pago'
                ]
            ],
            'cc_rejected_duplicated_payment' => [
                'title' => 'Pago duplicado',
                'message' => 'Ya existe un pago reciente idéntico a este',
                'icon' => '🔄',
                'suggestions' => [
                    'Verifica si ya completaste esta compra anteriormente',
                    'Revisa tu email para confirmaciones previas',
                    'Si no realizaste el pago, espera unos minutos e intenta de nuevo'
                ]
            ],
            'cc_rejected_high_risk' => [
                'title' => 'Pago no autorizado',
                'message' => 'El sistema de seguridad detectó un riesgo en esta transacción',
                'icon' => '🛡️',
                'suggestions' => [
                    'Por tu seguridad, este pago fue bloqueado',
                    'Contacta a tu banco para más información',
                    'Intenta con otro medio de pago o pago presencial'
                ]
            ],
            'cc_rejected_insufficient_amount' => [
                'title' => 'Fondos insuficientes',
                'message' => 'Tu tarjeta no tiene saldo suficiente para esta compra',
                'icon' => '💰',
                'suggestions' => [
                    'Verifica el saldo disponible en tu tarjeta',
                    'Intenta con otra tarjeta o medio de pago',
                    'Puedes elegir pago presencial en el checkout'
                ]
            ],
            'cc_rejected_invalid_installments' => [
                'title' => 'Cuotas no disponibles',
                'message' => 'La cantidad de cuotas seleccionada no está permitida',
                'icon' => '📊',
                'suggestions' => [
                    'Intenta con menos cuotas',
                    'Consulta con tu banco las opciones de financiación',
                    'Puedes intentar pagar en una sola cuota'
                ]
            ],
            'cc_rejected_max_attempts' => [
                'title' => 'Máximo de intentos excedido',
                'message' => 'Superaste el límite de intentos de pago con esta tarjeta',
                'icon' => '🚨',
                'suggestions' => [
                    'Espera unas horas antes de intentar nuevamente',
                    'Intenta con otra tarjeta',
                    'Contacta a tu banco si crees que hay un error'
                ]
            ],
            'cc_rejected_other_reason' => [
                'title' => 'Pago rechazado',
                'message' => 'Tu banco rechazó la transacción',
                'icon' => '❌',
                'suggestions' => [
                    'Contacta a tu banco para conocer el motivo',
                    'Verifica que tu tarjeta tenga habilitadas las compras online',
                    'Intenta con otro medio de pago'
                ]
            ]
        ];

        if (isset($rejection_messages[$status_detail])) {
            return $rejection_messages[$status_detail];
        }
    }

    // Handle cancelled payments
    if ($status === 'cancelled') {
        return [
            'title' => 'Pago cancelado',
            'message' => 'El pago fue cancelado',
            'icon' => '⛔',
            'suggestions' => [
                'Puedes intentar realizar el pago nuevamente',
                'Elige otro medio de pago si lo prefieres',
                'Tu carrito sigue disponible'
            ]
        ];
    }

    // Handle refunded payments
    if ($status === 'refunded') {
        return [
            'title' => 'Pago reembolsado',
            'message' => 'Este pago fue reembolsado',
            'icon' => '↩️',
            'suggestions' => [
                'El dinero será devuelto a tu cuenta',
                'El proceso puede tomar algunos días hábiles',
                'Recibirás una confirmación de tu banco'
            ]
        ];
    }

    // Handle chargebacks
    if ($status === 'charged_back') {
        return [
            'title' => 'Contracargo',
            'message' => 'Se realizó un contracargo en este pago',
            'icon' => '⚠️',
            'suggestions' => [
                'Contacta a soporte para más información',
                'Revisa tu email para detalles del caso'
            ]
        ];
    }

    // Default error message for unknown status
    return $messages;
}

/**
 * Get payment credentials from external secure file
 * Returns credentials stored outside webroot for security
 *
 * @return array Payment credentials array
 */
function get_payment_credentials() {
    $credentials_path_file = PAYMENT_CREDENTIALS_PATH_FILE;

    // Get path to credentials file
    if (!file_exists($credentials_path_file)) {
        error_log("Payment credentials path file not found. Using default path.");

        // Default path based on environment
        if (file_exists('/home2/uv0023/shop-v2-app')) {
            // Production
            $credentials_path = '/home2/uv0023/payment_credentials.json';
        } else {
            // Development/Testing
            $credentials_path = APP_PATH . '/data/payment_credentials.json';
        }
    } else {
        $credentials_path = trim(file_get_contents($credentials_path_file));
    }

    // Read credentials file
    if (!file_exists($credentials_path)) {
        error_log("Payment credentials file not found at: $credentials_path");
        return [
            'mercadopago' => [
                'access_token_sandbox' => '',
                'access_token_prod' => '',
                'public_key_sandbox' => '',
                'public_key_prod' => '',
                'webhook_secret_sandbox' => '',
                'webhook_secret_prod' => ''
            ]
        ];
    }

    $credentials = @json_decode(file_get_contents($credentials_path), true);

    if (!$credentials || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON in payment credentials file: " . json_last_error_msg());
        return [
            'mercadopago' => [
                'access_token_sandbox' => '',
                'access_token_prod' => '',
                'public_key_sandbox' => '',
                'public_key_prod' => '',
                'webhook_secret_sandbox' => '',
                'webhook_secret_prod' => ''
            ]
        ];
    }

    return $credentials;
}
