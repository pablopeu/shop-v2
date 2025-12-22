<?php
/**
 * API: Shipping Endpoints (Zipnova Integration)
 * Handles quote requests, shipment creation, tracking, and webhooks
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

require_once APP_PATH . '/includes/zipnova.php';

// Helper to send JSON response
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Determine action based on query parameter
$action = $_GET['action'] ?? '';

/**
 * GET /api/shipping?action=quotes
 * Get shipping quotes
 */
if ($action === 'quotes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Apply rate limiting
    api_rate_limit(20, 60);

    // Get parameters
    $postal_code = sanitize_input($_GET['postal_code'] ?? '');
    $city = sanitize_input($_GET['city'] ?? '');
    $province = sanitize_input($_GET['province'] ?? '');
    $country = sanitize_input($_GET['country'] ?? 'AR');
    $weight = (float)($_GET['weight'] ?? 0);
    $declared_value = (float)($_GET['declared_value'] ?? 0);

    // Validation
    if (empty($postal_code)) {
        send_json_response(['success' => false, 'error' => 'Código postal requerido'], 400);
    }

    $destination = [
        'postal_code' => $postal_code,
        'city' => $city,
        'province' => $province,
        'country' => $country
    ];

    $packages = [[
        'weight' => $weight > 0 ? $weight : 1,
        'declared_value' => $declared_value
    ]];

    // Check cache
    $cache_key = 'quote_' . md5(json_encode($destination) . json_encode($packages));
    $cache_file = BASE_PATH . '/data/cache/' . $cache_key . '.json';
    $config = zipnova_get_config();
    $cache_minutes = $config['options']['cache_quotes_minutes'] ?? 5;

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < ($cache_minutes * 60)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        send_json_response($cached_data);
    }

    // Get quotes from Zipnova
    $result = zipnova_get_quotes(null, $destination, $packages);

    if ($result['success']) {
        // Save to cache
        $cache_dir = BASE_PATH . '/data/cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        file_put_contents($cache_file, json_encode($result));
    }

    send_json_response($result);
}

/**
 * POST /api/shipping?action=quotes
 * Get shipping quotes (POST version with full data)
 */
if ($action === 'quotes' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Apply rate limiting
    api_rate_limit(20, 60);

    // Require JSON Content-Type
    require_json_content_type();

    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    $validation = validate_api_input($data, [
        'destination' => [
            'required' => true,
            'type' => 'object'
        ]
    ]);

    if (!$validation['valid']) {
        send_json_response(['success' => false, 'error' => $validation['error']], 400);
    }

    $destination = $data['destination'];
    $packages = $data['packages'] ?? [];

    // If no packages provided, use defaults
    if (empty($packages)) {
        $weight = (float)($data['weight'] ?? 0);
        $declared_value = (float)($data['declared_value'] ?? 0);
        $packages = [[
            'weight' => $weight > 0 ? $weight : 1,
            'declared_value' => $declared_value
        ]];
    }

    // Check cache
    $cache_key = 'quote_' . md5(json_encode($destination) . json_encode($packages));
    $cache_file = BASE_PATH . '/data/cache/' . $cache_key . '.json';
    $config = zipnova_get_config();
    $cache_minutes = $config['options']['cache_quotes_minutes'] ?? 5;

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < ($cache_minutes * 60)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        send_json_response($cached_data);
    }

    // Get quotes from Zipnova
    $result = zipnova_get_quotes(null, $destination, $packages);

    if ($result['success']) {
        // Save to cache
        $cache_dir = BASE_PATH . '/data/cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        file_put_contents($cache_file, json_encode($result));
    }

    send_json_response($result);
}

/**
 * POST /api/shipping?action=create
 * Create a new shipment
 */
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require authentication for creating shipments
    require_admin();

    // Apply rate limiting
    api_rate_limit(10, 60);

    // Require JSON Content-Type
    require_json_content_type();

    // Validate CSRF token
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!validate_csrf_token($data['csrf_token'] ?? '')) {
        send_json_response(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
    }

    // Validate required fields
    $validation = validate_api_input($data, [
        'service_id' => [
            'required' => true,
            'type' => 'string'
        ],
        'destination' => [
            'required' => true,
            'type' => 'object'
        ],
        'customer' => [
            'required' => true,
            'type' => 'object'
        ],
        'reference' => [
            'required' => true,
            'type' => 'string'
        ]
    ]);

    if (!$validation['valid']) {
        send_json_response(['success' => false, 'error' => $validation['error']], 400);
    }

    // Create shipment
    $shipment_data = [
        'service_id' => $data['service_id'],
        'destination' => $data['destination'],
        'customer' => $data['customer'],
        'reference' => $data['reference'],
        'packages' => $data['packages'] ?? []
    ];

    $result = zipnova_create_shipment($shipment_data);

    send_json_response($result, $result['success'] ? 200 : 500);
}

/**
 * GET /api/shipping?action=track&id={shipment_id}
 * Get tracking information for a shipment
 */
if ($action === 'track' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Apply rate limiting
    api_rate_limit(30, 60);

    $shipment_id = sanitize_input($_GET['id'] ?? '');

    if (empty($shipment_id)) {
        send_json_response(['success' => false, 'error' => 'ID de envío requerido'], 400);
    }

    // Try to get from local storage first
    $local_shipment = zipnova_load_shipment($shipment_id);

    if ($local_shipment) {
        // Return local data
        send_json_response(['success' => true, 'data' => $local_shipment]);
    } else {
        // Query Zipnova API
        $result = zipnova_get_shipment($shipment_id);
        send_json_response($result, $result['success'] ? 200 : 404);
    }
}

/**
 * POST /api/shipping?action=webhook
 * Webhook endpoint to receive updates from Zipnova
 */
if ($action === 'webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_ZIPNOVA_SIGNATURE'] ?? '';

    // Verify webhook signature
    if (!zipnova_webhook_verify($payload, $signature)) {
        zipnova_log('Webhook Rejected', ['reason' => 'Invalid signature']);
        send_json_response(['success' => false, 'error' => 'Invalid signature'], 403);
    }

    // Parse webhook data
    $data = json_decode($payload, true);

    if (!$data) {
        zipnova_log('Webhook Rejected', ['reason' => 'Invalid JSON']);
        send_json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
    }

    // Process webhook
    $event_type = $data['event'] ?? '';
    $shipment_id = $data['shipment_id'] ?? '';
    $new_status = $data['status'] ?? '';

    zipnova_log('Webhook Received', [
        'event' => $event_type,
        'shipment_id' => $shipment_id,
        'status' => $new_status
    ]);

    // Update local shipment status
    if ($shipment_id && $new_status) {
        zipnova_update_shipment_status($shipment_id, [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
            'webhook_data' => $data
        ]);

        // TODO: Enviar notificación al cliente si el estado cambió
        // TODO: Actualizar la orden relacionada
    }

    send_json_response(['success' => true, 'message' => 'Webhook processed']);
}

/**
 * GET /api/shipping?action=test-connection
 * Test connection to Zipnova API
 */
if ($action === 'test-connection' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Require authentication
    require_admin();

    // Apply rate limiting
    api_rate_limit(5, 60);

    $result = zipnova_test_connection();

    send_json_response($result, $result['success'] ? 200 : 500);
}

/**
 * GET /api/shipping?action=list
 * List all shipments (admin only)
 */
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Require authentication
    require_admin();

    // Apply rate limiting
    api_rate_limit(30, 60);

    $status_filter = sanitize_input($_GET['status'] ?? '');
    $reference_filter = sanitize_input($_GET['reference'] ?? '');

    $filters = [];
    if ($status_filter) {
        $filters['status'] = $status_filter;
    }
    if ($reference_filter) {
        $filters['reference'] = $reference_filter;
    }

    $shipments = zipnova_get_all_shipments($filters);

    send_json_response(['success' => true, 'data' => $shipments]);
}

/**
 * POST /api/shipping?action=cancel&id={shipment_id}
 * Cancel a shipment (admin only)
 */
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require authentication
    require_admin();

    // Apply rate limiting
    api_rate_limit(10, 60);

    // Require JSON Content-Type
    require_json_content_type();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate CSRF token
    if (!validate_csrf_token($data['csrf_token'] ?? '')) {
        send_json_response(['success' => false, 'error' => 'Token de seguridad inválido'], 403);
    }

    $shipment_id = sanitize_input($_GET['id'] ?? $data['shipment_id'] ?? '');

    if (empty($shipment_id)) {
        send_json_response(['success' => false, 'error' => 'ID de envío requerido'], 400);
    }

    $result = zipnova_cancel_shipment($shipment_id);

    send_json_response($result, $result['success'] ? 200 : 500);
}

// No action matched - return error
send_json_response([
    'success' => false,
    'error' => 'Invalid action or method'
], 400);
