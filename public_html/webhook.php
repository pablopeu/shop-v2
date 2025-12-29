<?php
/**
 * Mercadopago Webhook
 * Receives payment notifications from Mercadopago
 */

define('APP_ENTRY_POINT', true);

// Cargar configuración de ruta al bootstrap (generada por instalador)
$bootstrap_config = __DIR__ . '/bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        http_response_code(500);
        die('Error: bootstrap.php not found');
    }
} else {
    // Fallback para desarrollo (estructura relativa)
    $bootstrap_path = __DIR__ . '/../app/bootstrap.php';
    if (!file_exists($bootstrap_path)) {
        http_response_code(500);
        die('Error: bootstrap.php not found');
    }
    require_once $bootstrap_path;
}

// Load MP logger
require_once APP_PATH . '/includes/mp-logger.php';

// Define security constant to prevent direct file access


// Log webhook for debugging
function log_webhook($message, $data = []) {
    $log_file = APP_PATH . '/data/webhook_log.json';

    // Ensure data directory exists
    $data_dir = APP_PATH . '/data';
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
    }

    $logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];

    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data
    ];

    // Keep only last 100 logs
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100);
    }

    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
}

/**
 * Validate X-Signature header from Mercadopago
 * Protects against fraudulent notifications
 */
function validate_mercadopago_signature($request_data, $headers, $secret_key) {
    // Extract x-signature header
    $signature_header = $headers['x-signature'] ?? $headers['X-Signature'] ?? '';
    $request_id = $headers['x-request-id'] ?? $headers['X-Request-Id'] ?? '';

    if (empty($signature_header) || empty($request_id)) {
        log_webhook('Missing required headers for signature validation', [
            'has_signature' => !empty($signature_header),
            'has_request_id' => !empty($request_id)
        ]);
        return false;
    }

    // Parse signature header: "ts=1234567890,v1=abc123..."
    $signature_parts = [];
    foreach (explode(',', $signature_header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $signature_parts[trim($kv[0])] = trim($kv[1]);
        }
    }

    $ts = $signature_parts['ts'] ?? '';
    $received_hash = $signature_parts['v1'] ?? '';

    if (empty($ts) || empty($received_hash)) {
        log_webhook('Invalid signature header format', ['signature_header' => $signature_header]);
        return false;
    }

    // Build the manifest for validation
    // Format: id:{data_id};request-id:{request_id};ts:{timestamp}
    // MercadoPago sends different formats:
    // - New format: {"data": {"id": "123"}, "id": "456", ...}
    // - Old format: {"resource": "123", "topic": "payment"}
    // - Merchant order: {"id": "123", "data": {...}}

    // Try multiple locations for the data_id
    $data_id = $request_data['data']['id'] ?? // New webhook format
               $request_data['id'] ?? // Merchant order format
               $request_data['resource'] ?? // Old IPN format
               '';

    // DEBUGGING: Log which ID we're using
    error_log("WEBHOOK DEBUG - Signature validation - data_id: $data_id");
    error_log("WEBHOOK DEBUG - Full request_data: " . json_encode($request_data));

    $manifest = "id:{$data_id};request-id:{$request_id};ts:{$ts}";

    // Calculate expected signature
    $expected_hash = hash_hmac('sha256', $manifest, $secret_key);

    // Compare signatures (constant-time comparison to prevent timing attacks)
    $is_valid = hash_equals($expected_hash, $received_hash);

    if (!$is_valid) {
        log_webhook('Signature validation failed', [
            'manifest' => $manifest,
            'expected' => $expected_hash,
            'received' => $received_hash,
            'secret_key_length' => strlen($secret_key)
        ]);
    }

    return $is_valid;
}

/**
 * Validate timestamp to prevent replay attacks
 */
function validate_timestamp($signature_header, $max_age_minutes = 5) {
    // Parse signature header to get timestamp
    $signature_parts = [];
    foreach (explode(',', $signature_header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $signature_parts[trim($kv[0])] = trim($kv[1]);
        }
    }

    $ts = $signature_parts['ts'] ?? '';

    if (empty($ts) || !is_numeric($ts)) {
        log_webhook('Invalid or missing timestamp in signature', ['ts' => $ts]);
        return false;
    }

    // Convert to milliseconds
    $current_ts = time() * 1000;
    $max_age_ms = $max_age_minutes * 60 * 1000;

    $age = abs($current_ts - (int)$ts);

    if ($age > $max_age_ms) {
        log_webhook('Timestamp too old or in future', [
            'timestamp' => $ts,
            'current' => $current_ts,
            'age_seconds' => $age / 1000,
            'max_age_minutes' => $max_age_minutes
        ]);
        return false;
    }

    return true;
}

/**
 * Validate IP address is from Mercadopago
 *
 * NOTE: Mercadopago recommends using signature validation instead of IP whitelisting
 * because their IPs are dynamic and change frequently. This validation is optional.
 *
 * @param string $ip Client IP address
 * @param string $mode 'sandbox' or 'production'
 * @return bool True if IP is valid
 */
function validate_mercadopago_ip($ip, $mode = 'production') {
    // Mercadopago legacy IP ranges (documented in 2021, may be outdated)
    $legacy_ranges = [
        '209.225.49.0/24',
        '216.33.197.0/24',
        '216.33.196.0/24',
        '63.128.82.0/24',
        '63.128.83.0/24',
        '63.128.94.0/24',
    ];

    // AWS South America (São Paulo) - Used for sandbox and some production
    $aws_sa_ranges = [
        '52.67.0.0/16',
        '54.94.0.0/16',
        '54.232.0.0/16',
    ];

    // AWS US East (N. Virginia & Ohio) - Used primarily for production
    // Based on observed IPs: 54.88.x.x, 18.206.x.x, 18.215.x.x
    $aws_us_ranges = [
        '54.88.0.0/16',      // AWS US East
        '18.206.0.0/16',     // AWS US East
        '18.215.0.0/16',     // AWS US East
        '52.0.0.0/12',       // AWS US East (broader range)
        '18.208.0.0/13',     // AWS US East (broader range)
    ];

    // Google Cloud Platform ranges - Sometimes used by Mercadopago
    $gcp_ranges = [
        '35.245.0.0/16',     // GCP (observed: 35.245.91.34)
    ];

    // Combine ranges based on mode
    $allowed_ranges = array_merge($legacy_ranges, $aws_sa_ranges);

    // Production mode includes additional AWS US ranges
    if ($mode === 'production') {
        $allowed_ranges = array_merge($allowed_ranges, $aws_us_ranges, $gcp_ranges);
    }

    foreach ($allowed_ranges as $range) {
        if (ip_in_range($ip, $range)) {
            return true;
        }
    }

    log_webhook('IP not in Mercadopago whitelist', [
        'ip' => $ip,
        'mode' => $mode,
        'note' => 'Consider disabling IP validation and relying on signature validation instead'
    ]);
    return false;
}

/**
 * Check if IP is in CIDR range
 */
function ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) {
        $range .= '/32';
    }

    list($subnet, $bits) = explode('/', $range);

    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = -1 << (32 - (int)$bits);
    $subnet_long &= $mask;

    return ($ip_long & $mask) == $subnet_long;
}

/**
 * Rate limiting to prevent DoS attacks on webhook
 */
function check_webhook_rate_limit($max_requests = 100, $window_seconds = 60) {
    $rate_limit_file = APP_PATH . '/data/webhook_rate_limit.json';
    $current_time = time();

    // Load rate limit data
    $rate_data = file_exists($rate_limit_file) ?
        json_decode(file_get_contents($rate_limit_file), true) : [];

    // Clean old entries
    $rate_data = array_filter($rate_data, function($timestamp) use ($current_time, $window_seconds) {
        return $timestamp > ($current_time - $window_seconds);
    });

    // Check if limit exceeded
    if (count($rate_data) >= $max_requests) {
        log_webhook('Rate limit exceeded', [
            'requests_in_window' => count($rate_data),
            'max_requests' => $max_requests,
            'window_seconds' => $window_seconds
        ]);
        return false;
    }

    // Add current request
    $rate_data[] = $current_time;

    // Save rate limit data
    file_put_contents($rate_limit_file, json_encode(array_values($rate_data)));

    return true;
}

/**
 * Get all headers (case-insensitive)
 */
function get_request_headers() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$header_name] = $value;
            // Also store lowercase version for case-insensitive access
            $headers[strtolower($header_name)] = $value;
        }
    }
    return $headers;
}

// Handle GET requests (Mercadopago validation)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    log_webhook('GET request received - Mercadopago validation', ['query' => $_GET]);
    http_response_code(200);
    exit('OK');
}

// Get webhook data from POST
// Try multiple methods to read POST data (some servers/configs may not support php://input)
$input = file_get_contents('php://input');

// DEBUGGING: Log raw input immediately
error_log("WEBHOOK DEBUG - Raw php://input length: " . strlen($input));
error_log("WEBHOOK DEBUG - Raw php://input: " . $input);

// If php://input is empty, try $_POST as fallback
if (empty($input) && !empty($_POST)) {
    error_log("WEBHOOK DEBUG - php://input was empty, using $_POST instead");
    $input = json_encode($_POST);
}

// If still empty, try STDIN
if (empty($input)) {
    error_log("WEBHOOK DEBUG - Both php://input and $_POST empty, trying STDIN");
    $stdin_input = @file_get_contents('php://stdin');
    if (!empty($stdin_input)) {
        $input = $stdin_input;
        error_log("WEBHOOK DEBUG - Read from STDIN: " . $stdin_input);
    }
}

$data = json_decode($input, true);

// DEBUGGING: Log JSON decode result
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("WEBHOOK DEBUG - JSON decode error: " . json_last_error_msg());
    error_log("WEBHOOK DEBUG - Failed input was: " . $input);
}

// Get all request headers
$headers = get_request_headers();

// Get client IP
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($client_ip, ',') !== false) {
    $client_ip = trim(explode(',', $client_ip)[0]);
}

log_webhook('Webhook received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'input' => $input,
    'input_length' => strlen($input),
    'parsed_data' => $data,
    'query' => $_GET,
    'post' => $_POST,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
    'client_ip' => $client_ip,
    'has_signature' => !empty($headers['x-signature'] ?? ''),
    'json_decode_error' => json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null
]);

// Also log with detailed MP logger
if ($data !== null) {
    log_webhook_received($data, $headers, $client_ip);
} else {
    log_mp_error('WEBHOOK', 'Failed to parse webhook data', [
        'input' => $input,
        'input_length' => strlen($input),
        'json_error' => json_last_error_msg(),
        'post' => $_POST,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ]);
}

// Validate webhook - accept either 'type' or 'topic' field (MercadoPago uses both)
if (!$data || (!isset($data['type']) && !isset($data['topic']))) {
    log_webhook('Invalid webhook data - missing type/topic field', [
        'data' => $data,
        'raw_input' => $input,
        'has_type' => isset($data['type']),
        'has_topic' => isset($data['topic'])
    ]);
    http_response_code(400);
    exit('Invalid data');
}

// Get payment config and credentials
$payment_config = read_json(APP_PATH . '/config/payment.json');
$payment_credentials = get_payment_credentials();

$mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
$sandbox_mode = ($mp_mode === 'sandbox');

$access_token = $sandbox_mode ?
    ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
    ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

$webhook_secret = $sandbox_mode ?
    ($payment_credentials['mercadopago']['webhook_secret_sandbox'] ?? '') :
    ($payment_credentials['mercadopago']['webhook_secret_prod'] ?? '');

$security_config = $payment_config['mercadopago']['webhook_security'] ?? [
    'validate_signature' => true,
    'validate_timestamp' => true,
    'validate_ip' => true,
    'max_timestamp_age_minutes' => 5
];

if (empty($access_token)) {
    log_webhook('No access token configured');
    http_response_code(500);
    exit('Not configured');
}

// ========== SECURITY VALIDATIONS ==========

// 1. Rate Limiting (always check to prevent DoS)
if (!check_webhook_rate_limit(100, 60)) {
    http_response_code(429);
    exit('Too many requests');
}

// 2. IP Validation (if enabled)
if ($security_config['validate_ip'] ?? true) {
    $ip_valid = validate_mercadopago_ip($client_ip, $mp_mode);
    log_webhook_validation('IP_VALIDATION', $ip_valid, [
        'client_ip' => $client_ip,
        'mode' => $mp_mode,
        'validation_enabled' => true
    ]);

    if (!$ip_valid) {
        log_webhook('IP validation failed - rejecting webhook', [
            'ip' => $client_ip,
            'mode' => $mp_mode
        ]);
        log_mp_error('WEBHOOK_VALIDATION', 'Validación de IP falló', [
            'client_ip' => $client_ip,
            'mode' => $mp_mode
        ]);
        http_response_code(403);
        exit('Forbidden');
    }
} else {
    log_webhook_validation('IP_VALIDATION', true, [
        'validation_enabled' => false,
        'reason' => 'IP validation disabled in config'
    ]);
}

log_webhook('[DEBUG A] After IP validation block');
log_mp_debug('DEBUG_A', 'Después del bloque de validación de IP', [
    'validate_signature_config' => $security_config['validate_signature'] ?? 'not_set',
    'validate_timestamp_config' => $security_config['validate_timestamp'] ?? 'not_set',
    'has_webhook_secret' => !empty($webhook_secret)
]);

// 3. X-Signature Validation (if enabled and secret is configured)
$should_validate_signature = ($security_config['validate_signature'] ?? false);
$has_webhook_secret = !empty($webhook_secret);
$has_signature_header = !empty($headers['x-signature'] ?? '');
$has_request_id_header = !empty($headers['x-request-id'] ?? '');

log_webhook('[DEBUG SIGNATURE] Signature validation config', [
    'should_validate' => $should_validate_signature,
    'has_secret' => $has_webhook_secret,
    'secret_length' => $has_webhook_secret ? strlen($webhook_secret) : 0,
    'has_signature_header' => $has_signature_header,
    'has_request_id_header' => $has_request_id_header
]);
log_mp_debug('SIGNATURE_CONFIG', 'Configuración de validación de signature', [
    'should_validate' => $should_validate_signature,
    'has_secret' => $has_webhook_secret,
    'has_signature_header' => $has_signature_header,
    'has_request_id_header' => $has_request_id_header
]);

if ($should_validate_signature && $has_webhook_secret) {
    // Check if signature headers are present
    if (!$has_signature_header || !$has_request_id_header) {
        // Signature validation is enabled but headers are missing
        // This is common with old IPN webhooks or certain notification types
        log_webhook('WARNING: Signature validation enabled but headers missing - ALLOWING webhook for backward compatibility', [
            'has_signature_header' => $has_signature_header,
            'has_request_id_header' => $has_request_id_header,
            'webhook_type' => $data['type'] ?? $data['topic'] ?? 'unknown'
        ]);
        log_mp_error('WEBHOOK_SIGNATURE', 'Headers de signature faltantes pero permitiendo webhook por compatibilidad', [
            'has_signature_header' => $has_signature_header,
            'has_request_id_header' => $has_request_id_header
        ]);
    } else {
        // Headers are present, validate signature
        $signature_valid = validate_mercadopago_signature($data, $headers, $webhook_secret);
        log_webhook('[DEBUG SIGNATURE] Validation result', ['valid' => $signature_valid]);
        log_mp_debug('SIGNATURE_VALIDATION', 'Resultado de validación de signature', ['valid' => $signature_valid]);

        if (!$signature_valid) {
            log_webhook('Signature validation failed - rejecting webhook');
            log_mp_error('WEBHOOK_SIGNATURE', 'Validación de signature falló', [
                'signature_header' => $headers['x-signature'] ?? 'missing',
                'request_id_header' => $headers['x-request-id'] ?? 'missing',
                'data_keys' => array_keys($data)
            ]);
            http_response_code(401);
            exit('Unauthorized');
        }
        log_webhook('Signature validation passed');
        log_mp_debug('SIGNATURE_PASSED', 'Signature validation passed');
    }
} elseif ($should_validate_signature && !$has_webhook_secret) {
    log_webhook('WARNING: Signature validation enabled but webhook secret NOT configured', [
        'sandbox_mode' => $sandbox_mode
    ]);
    log_mp_error('WEBHOOK_CONFIG', 'Validación de signature habilitada pero secret no configurado', [
        'sandbox_mode' => $sandbox_mode
    ]);
} else {
    log_webhook('Signature validation disabled - skipping');
    log_mp_debug('SIGNATURE_DISABLED', 'Validación de signature deshabilitada');
}

log_webhook('[DEBUG B] After signature validation block');
log_mp_debug('DEBUG_B', 'Después del bloque de validación de signature', [
    'signature_validated' => 'skipped or passed'
]);

// 4. Timestamp Validation (if enabled)
if ($security_config['validate_timestamp'] ?? true) {
    $signature_header = $headers['x-signature'] ?? '';
    if (!empty($signature_header)) {
        $max_age = $security_config['max_timestamp_age_minutes'] ?? 5;
        if (!validate_timestamp($signature_header, $max_age)) {
            log_webhook('Timestamp validation failed - possible replay attack');
            http_response_code(401);
            exit('Unauthorized');
        }
        log_webhook('Timestamp validation passed');
    }
}

log_webhook('[DEBUG C] After timestamp validation block');
log_mp_debug('DEBUG_C', 'Después del bloque de validación de timestamp', [
    'timestamp_validated' => 'skipped or passed'
]);

log_webhook('[CHECKPOINT 1] After security validations');
log_mp_debug('CHECKPOINT_1', 'Después de validaciones de seguridad', ['client_ip' => $client_ip]);

// Get notification type - MercadoPago uses 'type' (newer) or 'topic' (legacy)
log_webhook('[CHECKPOINT 2] About to extract notification type', [
    'has_type' => isset($data['type']),
    'has_topic' => isset($data['topic']),
    'data_keys' => array_keys($data)
]);

$notification_type = $data['type'] ?? $data['topic'] ?? 'unknown';
$notification_action = $data['action'] ?? 'unknown';

log_webhook('[CHECKPOINT 3] Notification type extracted', [
    'notification_type' => $notification_type,
    'notification_action' => $notification_action
]);

log_webhook('Determining notification type', [
    'type_field' => $data['type'] ?? null,
    'topic_field' => $data['topic'] ?? null,
    'action_field' => $notification_action,
    'final_type' => $notification_type,
    'full_data_keys' => array_keys($data)
]);

log_mp_debug('WEBHOOK_TYPE_DETECTION', 'Detectando tipo de notificación', [
    'type' => $data['type'] ?? null,
    'topic' => $data['topic'] ?? null,
    'action' => $notification_action,
    'resolved_type' => $notification_type
]);

// Handle payment notification (check both 'payment' and 'payments' for compatibility)
if ($notification_type === 'payment' || $notification_type === 'payments') {
    try {
        // Extract payment ID - support multiple formats:
        // - Nested: data.id (Webhook v1 format)
        // - Flat: id (some integrations)
        // - Resource: resource (IPN/topic format)
        $payment_id = $data['data']['id'] ?? $data['id'] ?? $data['resource'] ?? null;

        // If resource is a URL, extract the ID from it
        if ($payment_id && strpos($payment_id, 'http') === 0) {
            // Extract ID from URL like "https://api.mercadopago.com/v1/payments/123456"
            $payment_id = basename(parse_url($payment_id, PHP_URL_PATH));
        }

        if (!$payment_id || !is_numeric($payment_id)) {
            log_webhook('No payment ID in webhook', [
                'has_data' => isset($data['data']),
                'has_data_id' => isset($data['data']['id']),
                'has_id' => isset($data['id']),
                'has_resource' => isset($data['resource']),
                'resource_value' => $data['resource'] ?? null,
                'data_keys' => array_keys($data),
                'full_data' => $data
            ]);
            log_mp_debug('WEBHOOK_ERROR', 'Webhook de pago sin payment_id válido', $data);
            http_response_code(400);
            exit('No payment ID');
        }

        log_mp_debug('PAYMENT_WEBHOOK', "Procesando webhook de pago - Payment ID: $payment_id", [
            'payment_id' => $payment_id,
            'notification_type' => $notification_type,
            'action' => $notification_action
        ]);

        // Get payment details from Mercadopago
        $mp = new MercadoPago($access_token, $sandbox_mode);

        try {
            $payment = $mp->getPayment($payment_id);
        } catch (Exception $e) {
            log_webhook('Error getting payment from MP API', [
                'payment_id' => $payment_id,
                'error' => $e->getMessage()
            ]);
            log_mp_error('WEBHOOK', 'Error al obtener pago de MP API', [
                'payment_id' => $payment_id,
                'error' => $e->getMessage()
            ]);
            // If it's a test payment that doesn't exist, return 200 to avoid retries
            http_response_code(200);
            exit('Payment not found in MP');
        }

        log_webhook('Payment details retrieved', ['payment' => $payment]);

        // Log complete payment details with our detailed logger
        log_payment_details($payment_id, $payment);

        // Find order by external_reference (order ID)
        $order_id = $payment['external_reference'] ?? null;

        if (!$order_id) {
            log_webhook('No external reference in payment');
            http_response_code(200); // Acknowledge but don't process
            exit('OK');
        }

        // Load orders
        $orders_file = APP_PATH . '/data/orders.json';

        if (!file_exists($orders_file)) {
            log_webhook('Orders file does not exist');
            http_response_code(200);
            exit('Orders file not found');
        }

        $orders_data = read_json($orders_file);

        if (!isset($orders_data['orders']) || !is_array($orders_data['orders'])) {
            log_webhook('Invalid orders data structure');
            http_response_code(200);
            exit('Invalid orders data');
        }

        $order_index = null;
        foreach ($orders_data['orders'] as $index => $order) {
            if ($order['id'] === $order_id) {
                $order_index = $index;
                break;
            }
        }

        if ($order_index === null) {
            log_webhook('Order not found', ['order_id' => $order_id]);
            http_response_code(200);
            exit('Order not found');
        }

        $order = $orders_data['orders'][$order_index];

        // Update order based on payment status
        $payment_status = $payment['status'];
        $status_detail = $payment['status_detail'];

        log_webhook('Processing payment status', [
            'order_id' => $order_id,
            'payment_status' => $payment_status,
            'status_detail' => $status_detail
        ]);

        // Map Mercadopago status to order status
        $new_order_status = null;
        $restore_stock = false;

        switch ($payment_status) {
            case 'approved':
                // Payment approved - order is now paid (estado: pagado)
                $new_order_status = 'pagado';
                break;

            case 'authorized':
                // Payment authorized but not yet captured - still unpaid
                $new_order_status = 'impago';
                log_webhook('Payment authorized - pending capture', ['order_id' => $order_id]);
                break;

            case 'pending':
            case 'in_process':
                $new_order_status = 'impago';
                break;

            case 'in_mediation':
                // Payment is being disputed - keep as unpaid
                $new_order_status = 'impago';
                log_webhook('Payment in mediation (dispute)', [
                    'order_id' => $order_id,
                    'status_detail' => $status_detail
                ]);
                break;

            case 'rejected':
            case 'cancelled':
                $new_order_status = 'rechazada';
                $restore_stock = true; // Restore stock if payment was rejected
                break;

            case 'refunded':
            case 'charged_back':
                $new_order_status = 'cancelada';
                $restore_stock = true;
                break;

            default:
                // Unknown status - log it and don't change order status
                log_webhook('Unknown payment status received', [
                    'order_id' => $order_id,
                    'payment_status' => $payment_status,
                    'status_detail' => $status_detail
                ]);
                http_response_code(200);
                exit('OK - Unknown status');
        }

        if ($new_order_status && $order['status'] !== $new_order_status) {
            // CRITICAL SECTION: Acquire exclusive lock to prevent race conditions
            // Previene que múltiples webhooks simultáneos procesen la misma orden
            $lock_result = with_lock('webhook', $order_id, function() use (
                $orders_file, $order_id, $order, $new_order_status, $payment_status,
                $status_detail, $payment_id, $payment, &$orders_data, &$order_index
            ) {
                // Re-read orders data INSIDE lock to get fresh state
                $orders_data = read_json($orders_file);

                // Re-find order index (could have changed)
                $order_index = null;
                foreach ($orders_data['orders'] as $idx => $o) {
                    if ($o['id'] === $order_id) {
                        $order_index = $idx;
                        $order = $o; // Update order reference
                        break;
                    }
                }

                if ($order_index === null) {
                    log_webhook('Order not found after acquiring lock', ['order_id' => $order_id]);
                    return false;
                }

                // Double-check stock_reduced flag AFTER acquiring lock
                if ($payment_status === 'approved' && ($order['stock_reduced'] ?? false)) {
                    log_webhook('Stock already reduced (detected in lock)', ['order_id' => $order_id]);
                    return 'already_processed';
                }

                // Update order status
                $old_status = $order['status'];
                $orders_data['orders'][$order_index]['status'] = $new_order_status;
            $orders_data['orders'][$order_index]['payment_status'] = $payment_status;
            $orders_data['orders'][$order_index]['payment_status_detail'] = $status_detail;
            $orders_data['orders'][$order_index]['payment_id'] = $payment_id;

            // Extract fee details and net amount
            $fee_details = $payment['fee_details'] ?? [];
            $transaction_details = $payment['transaction_details'] ?? [];

            // Calculate total fees from fee_details
            $total_fees = 0;
            $fee_breakdown = [];
            foreach ($fee_details as $fee) {
                $fee_amount = floatval($fee['amount'] ?? 0);
                $total_fees += $fee_amount;
                $fee_breakdown[] = [
                    'type' => $fee['type'] ?? 'unknown',
                    'amount' => $fee_amount,
                    'fee_payer' => $fee['fee_payer'] ?? 'collector'
                ];
            }

            // Get net amount (what merchant receives after fees)
            $net_received_amount = floatval($transaction_details['net_received_amount'] ?? 0);

            // If net_received_amount is not provided, calculate it
            if ($net_received_amount == 0 && isset($payment['transaction_amount'])) {
                $net_received_amount = floatval($payment['transaction_amount']) - $total_fees;
            }

            // Update/create complete Mercadopago data
            $orders_data['orders'][$order_index]['mercadopago_data'] = [
                'payment_id' => $payment['id'],
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'],
                'transaction_amount' => $payment['transaction_amount'] ?? null,
                'currency_id' => $payment['currency_id'] ?? null,
                'date_created' => $payment['date_created'] ?? null,
                'date_approved' => $payment['date_approved'] ?? null,
                'date_last_updated' => $payment['date_last_updated'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'payment_type_id' => $payment['payment_type_id'] ?? null,
                'installments' => $payment['installments'] ?? 1,
                'description' => $payment['description'] ?? null,
                'capture' => $payment['capture'] ?? null,
                'external_reference' => $payment['external_reference'] ?? null,
                'payer_email' => $payment['payer']['email'] ?? null,
                'payer_identification' => $payment['payer']['identification']['number'] ?? null,
                'card_last_four_digits' => $payment['card']['last_four_digits'] ?? null,
                'card_first_six_digits' => $payment['card']['first_six_digits'] ?? null,

                // NUEVO: Fees and net amount
                'total_paid_amount' => floatval($payment['transaction_amount'] ?? 0),
                'fee_details' => $fee_breakdown,
                'total_fees' => $total_fees,
                'net_received_amount' => $net_received_amount,

                // Transaction details
                'operation_number' => $transaction_details['external_resource_url'] ?? null,
                'payment_method_reference_id' => $transaction_details['payment_method_reference_id'] ?? null,
                'acquirer_reference' => $transaction_details['acquirer_reference'] ?? null,

                'webhook_received_at' => date('Y-m-d H:i:s'),
            ];

            // Add to status history
            if (!isset($orders_data['orders'][$order_index]['status_history'])) {
                $orders_data['orders'][$order_index]['status_history'] = [];
            }

            $orders_data['orders'][$order_index]['status_history'][] = [
                'status' => $new_order_status,
                'date' => date('Y-m-d H:i:s'),
                'user' => 'mercadopago_webhook',
                'payment_status' => $payment_status
            ];

            // Handle stock
            if ($payment_status === 'approved' && !($order['stock_reduced'] ?? false)) {
                // Reduce stock on approved payment
                foreach ($order['items'] as $item) {
                    reduce_product_stock($item['product_id'], $item['quantity']);
                }
                $orders_data['orders'][$order_index]['stock_reduced'] = true;
                log_webhook('Stock reduced', ['order_id' => $order_id]);
            } elseif ($restore_stock && ($order['stock_reduced'] ?? false)) {
                // Restore stock on rejected/cancelled payment
                foreach ($order['items'] as $item) {
                    restore_product_stock($item['product_id'], $item['quantity']);
                }
                $orders_data['orders'][$order_index]['stock_reduced'] = false;
                log_webhook('Stock restored', ['order_id' => $order_id]);
            }

                // Save orders
                write_json($orders_file, $orders_data);

                log_webhook('Order updated successfully', [
                    'order_id' => $order_id,
                    'new_status' => $new_order_status,
                    'payment_status' => $payment_status
                ]);

                // Log order update with detailed logger
                log_order_update($order_id, $old_status, $new_order_status, $payment_status);

                // Return success with updated order data
                return [
                    'success' => true,
                    'old_status' => $old_status,
                    'new_status' => $new_order_status,
                    'updated_order' => $orders_data['orders'][$order_index]
                ];
            }, 10); // 10 second timeout for lock acquisition

            // Check lock result
            if ($lock_result === false) {
                log_webhook('Failed to acquire lock for order processing', ['order_id' => $order_id]);
                http_response_code(409); // Conflict
                exit('Order locked - concurrent processing');
            }

            if ($lock_result === 'already_processed') {
                log_webhook('Webhook already processed (idempotent)', ['order_id' => $order_id]);
                http_response_code(200);
                exit('OK - Already processed');
            }

            // Extract data from lock result
            $old_status = $lock_result['old_status'];
            $updated_order = $lock_result['updated_order'];

            // Send notifications based on new status (outside lock)
            // Las notificaciones se pueden enviar fuera del lock crítico

            if ($new_order_status === 'pagado' && $payment_status === 'approved') {
                // Payment approved - send to customer based on preference
                $customer_notif_sent = false;
                if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                    $customer_notif_sent = send_telegram_payment_approved_to_customer($updated_order);
                    log_notification_sent('TELEGRAM_PAYMENT_APPROVED_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
                } else {
                    $customer_notif_sent = send_payment_approved_email($updated_order);
                    log_notification_sent('EMAIL_PAYMENT_APPROVED', $updated_order['customer_email'], $customer_notif_sent, $order_id);
                }

                // Always send to admin via Telegram AND Email
                $telegram_sent = send_telegram_payment_approved($updated_order);
                log_notification_sent('TELEGRAM_PAYMENT_APPROVED', 'admin', $telegram_sent, $order_id);

                $admin_email_sent = send_admin_new_order_email($updated_order);
                log_notification_sent('EMAIL_ADMIN_NEW_ORDER', 'admin', $admin_email_sent, $order_id);

                log_mp_debug('PAYMENT_APPROVED', "Pago aprobado - Orden: $order_id", [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'amount' => $payment['transaction_amount'] ?? 0,
                    'fees' => $total_fees,
                    'net_amount' => $net_received_amount,
                    'payment_method' => $payment['payment_method_id'] ?? 'unknown',
                    'customer_notif_sent' => $customer_notif_sent,
                    'telegram_sent' => $telegram_sent,
                    'admin_email_sent' => $admin_email_sent
                ]);
            } elseif ($new_order_status === 'impago' && in_array($payment_status, ['pending', 'in_process', 'authorized', 'in_mediation'])) {
                // Payment pending - send to customer based on preference
                $customer_notif_sent = false;
                if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                    $customer_notif_sent = send_telegram_payment_pending_to_customer($updated_order);
                    log_notification_sent('TELEGRAM_PAYMENT_PENDING_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
                } else {
                    $customer_notif_sent = send_payment_pending_email($updated_order);
                    log_notification_sent('EMAIL_PAYMENT_PENDING', $updated_order['customer_email'], $customer_notif_sent, $order_id);
                }

                log_mp_debug('PAYMENT_PENDING', "Pago pendiente - Orden: $order_id", [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'payment_status' => $payment_status,
                    'status_detail' => $status_detail,
                    'customer_notif_sent' => $customer_notif_sent
                ]);
            } elseif ($new_order_status === 'rechazada') {
                // Payment rejected - send to customer based on preference
                $customer_notif_sent = false;
                if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                    $customer_notif_sent = send_telegram_payment_rejected_to_customer($updated_order, $status_detail);
                    log_notification_sent('TELEGRAM_PAYMENT_REJECTED_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
                } else {
                    $customer_notif_sent = send_payment_rejected_email($updated_order, $status_detail);
                    log_notification_sent('EMAIL_PAYMENT_REJECTED', $updated_order['customer_email'], $customer_notif_sent, $order_id);
                }

                // Always send to admin via Telegram
                $telegram_sent = send_telegram_payment_rejected($updated_order);
                log_notification_sent('TELEGRAM_PAYMENT_REJECTED', 'admin', $telegram_sent, $order_id);

                log_mp_debug('PAYMENT_REJECTED', "Pago rechazado - Orden: $order_id", [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'status_detail' => $status_detail,
                    'customer_notif_sent' => $customer_notif_sent,
                    'telegram_sent' => $telegram_sent
                ]);
            } elseif ($new_order_status === 'cancelada' && in_array($payment_status, ['refunded', 'charged_back'])) {
                // Refunded or charged back - don't send regular rejection email
                // Chargeback notification will be sent separately
                log_webhook('Order cancelled due to refund/chargeback - no customer notification sent');
                log_mp_debug('PAYMENT_REFUNDED_OR_CHARGEBACK', "Pago reembolsado o contracargo - Orden: $order_id", [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'payment_status' => $payment_status
                ]);
            }

            http_response_code(200);
            exit('OK');
        }

        http_response_code(200);
        exit('OK - No changes');

    } catch (Exception $e) {
        log_webhook('Error processing payment webhook', ['error' => $e->getMessage()]);
        http_response_code(500);
        exit('Error: ' . $e->getMessage());
    }
}

// Handle chargeback notification
if ($notification_type === 'chargebacks' || $notification_type === 'chargeback') {
    try {
        $chargeback_id = $data['data']['id'] ?? null;

        if (!$chargeback_id) {
            log_webhook('No chargeback ID in webhook');
            http_response_code(400);
            exit('No chargeback ID');
        }

        log_webhook('Chargeback notification received', [
            'chargeback_id' => $chargeback_id,
            'action' => $data['action'] ?? 'unknown'
        ]);

        // Get payment ID from chargeback data if available
        $payment_id = $data['data']['payment_id'] ?? null;

        if ($payment_id) {
            // Get payment details to find the order
            $mp = new MercadoPago($access_token, $sandbox_mode);

            try {
                $payment = $mp->getPayment($payment_id);
                $order_id = $payment['external_reference'] ?? null;

                if ($order_id) {
                    // Load orders
                    $orders_file = APP_PATH . '/data/orders.json';
                    $orders_data = read_json($orders_file);

                    // Find order
                    $order_index = null;
                    foreach ($orders_data['orders'] as $index => $order) {
                        if ($order['id'] === $order_id) {
                            $order_index = $index;
                            break;
                        }
                    }

                    if ($order_index !== null) {
                        $order = $orders_data['orders'][$order_index];

                        // Update order with chargeback info
                        if (!isset($orders_data['orders'][$order_index]['chargebacks'])) {
                            $orders_data['orders'][$order_index]['chargebacks'] = [];
                        }

                        $orders_data['orders'][$order_index]['chargebacks'][] = [
                            'chargeback_id' => $chargeback_id,
                            'payment_id' => $payment_id,
                            'action' => $data['action'] ?? 'unknown',
                            'date' => date('Y-m-d H:i:s'),
                            'data' => $data
                        ];

                        // If chargeback is created/lost, mark order as charged_back and restore stock
                        if (in_array($data['action'] ?? '', ['created', 'lost'])) {
                            $old_status = $order['status'];
                            $orders_data['orders'][$order_index]['status'] = 'cancelada';

                            // Restore stock if it was reduced
                            if ($order['stock_reduced'] ?? false) {
                                foreach ($order['items'] as $item) {
                                    restore_product_stock($item['product_id'], $item['quantity']);
                                }
                                $orders_data['orders'][$order_index]['stock_reduced'] = false;
                                log_webhook('Stock restored due to chargeback', ['order_id' => $order_id]);
                            }

                            // Add to status history
                            if (!isset($orders_data['orders'][$order_index]['status_history'])) {
                                $orders_data['orders'][$order_index]['status_history'] = [];
                            }

                            $orders_data['orders'][$order_index]['status_history'][] = [
                                'status' => 'cancelada',
                                'date' => date('Y-m-d H:i:s'),
                                'user' => 'mercadopago_webhook',
                                'reason' => 'chargeback_' . ($data['action'] ?? 'unknown'),
                                'previous_status' => $old_status
                            ];

                            log_webhook('Order marked as charged_back', [
                                'order_id' => $order_id,
                                'chargeback_id' => $chargeback_id,
                                'action' => $data['action']
                            ]);
                        }

                        // Save orders
                        write_json($orders_file, $orders_data);

                        // Send chargeback alert notifications
                        $updated_order = $orders_data['orders'][$order_index];
                        $chargeback_data = [
                            'chargeback_id' => $chargeback_id,
                            'payment_id' => $payment_id,
                            'action' => $data['action'] ?? 'unknown',
                            'date' => date('Y-m-d H:i:s')
                        ];

                        send_admin_chargeback_alert($updated_order, $chargeback_data);
                        send_telegram_chargeback_alert($updated_order, $chargeback_data);

                        log_webhook('Chargeback alerts sent', [
                            'order_id' => $order_id,
                            'chargeback_id' => $chargeback_id
                        ]);
                    }
                }
            } catch (Exception $e) {
                log_webhook('Error processing chargeback - payment not found', [
                    'payment_id' => $payment_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        http_response_code(200);
        exit('OK - Chargeback processed');

    } catch (Exception $e) {
        log_webhook('Error processing chargeback webhook', ['error' => $e->getMessage()]);
        http_response_code(500);
        exit('Error: ' . $e->getMessage());
    }
}

// Handle merchant_order notification
if ($notification_type === 'merchant_order' || $notification_type === 'merchant_orders') {
    try {
        $merchant_order_id = $data['data']['id'] ?? null;

        if (!$merchant_order_id) {
            log_webhook('No merchant_order ID in webhook');
            http_response_code(400);
            exit('No merchant_order ID');
        }

        log_webhook('Merchant order notification received', [
            'merchant_order_id' => $merchant_order_id,
            'action' => $data['action'] ?? 'unknown'
        ]);

        // For Checkout Bricks, we primarily rely on payment webhooks
        // Merchant orders are more relevant for Checkout Pro
        // We'll log it but not take specific action unless needed

        http_response_code(200);
        exit('OK - Merchant order logged');

    } catch (Exception $e) {
        log_webhook('Error processing merchant_order webhook', ['error' => $e->getMessage()]);
        http_response_code(500);
        exit('Error: ' . $e->getMessage());
    }
}

// Acknowledge other notification types
log_webhook('Unrecognized notification type - webhook ignored', [
    'notification_type' => $notification_type,
    'type_field' => $data['type'] ?? null,
    'topic_field' => $data['topic'] ?? null,
    'action' => $notification_action,
    'full_data' => $data
]);

log_mp_debug('WEBHOOK_IGNORED', "Tipo de notificación no reconocido: $notification_type", [
    'notification_type' => $notification_type,
    'action' => $notification_action,
    'data' => $data
]);

http_response_code(200);
exit('OK');

/**
 * Reduce product stock
 */
function reduce_product_stock($product_id, $quantity) {
    update_stock($product_id, -$quantity, 'Pago aprobado via webhook');
}

/**
 * Restore product stock
 */
function restore_product_stock($product_id, $quantity) {
    update_stock($product_id, $quantity, 'Pago cancelado/rechazado via webhook');
}
