<?php
/**
 * API: Shipping Endpoints (Zipnova Integration)
 * Handles quote requests, shipment creation, tracking, and webhooks
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// LOG: Archivo shipping.php está siendo ejecutado
error_log('=== SHIPPING.PHP CALLED ===');
error_log('REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log('QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'NONE'));
error_log('GET params: ' . json_encode($_GET));

require_once APP_PATH . '/includes/carriers.php';

// Helper to send JSON response
function send_json_response($data, $status_code = 200) {
    error_log('send_json_response called with status: ' . $status_code);
    error_log('Response data: ' . json_encode($data));
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Determine action based on query parameter
$action = $_GET['action'] ?? '';
error_log('Action determined: ' . ($action ?: 'EMPTY'));

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

    // Formatear destino según API v2 de Zipnova
    $destination = [
        'city' => $city ?: 'Ciudad',
        'state' => $province ?: 'Provincia',
        'zipcode' => $postal_code
    ];

    // Formatear items según API v2 de Zipnova
    $items = [[
        'sku' => 'CART-ITEM',
        'weight' => (int)($weight > 0 ? ($weight * 1000) : 100), // convertir kg a gramos
        'height' => 10,
        'width' => 10,
        'length' => 10,
        'description' => 'Producto del carrito',
        'classification_id' => 1
    ]];

    // Check cache
    $cache_key = 'quote_' . md5(json_encode($destination) . json_encode($items) . $declared_value);
    $cache_file = __DIR__ . '/../../data/cache/' . $cache_key . '.json';
    $config = zipnova_get_config();
    $cache_minutes = $config['options']['cache_quotes_minutes'] ?? 5;

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < ($cache_minutes * 60)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        send_json_response($cached_data);
    }

    // Get quotes from Zipnova con nueva firma
    $result = zipnova_get_quotes($destination, $items, (float)$declared_value);

    if ($result['success']) {
        // Save to cache
        $cache_dir = __DIR__ . '/../../data/cache';
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
    error_log('=== POST QUOTES ENDPOINT MATCHED ===');

    // Apply rate limiting
    api_rate_limit(20, 60);
    error_log('Rate limit passed');

    // Require JSON Content-Type
    require_json_content_type();
    error_log('JSON content type validated');

    // Get POST data
    $input = file_get_contents('php://input');
    error_log('Raw POST input: ' . $input);
    $data = json_decode($input, true);
    error_log('Decoded data: ' . json_encode($data));

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

    $destination_raw = $data['destination'];
    $items_raw = $data['items'] ?? [];

    // Formatear destino según API v2
    $destination = [
        'city' => $destination_raw['city'] ?? 'Ciudad',
        'state' => $destination_raw['state'] ?? $destination_raw['province'] ?? 'Provincia',
        'zipcode' => $destination_raw['zipcode'] ?? $destination_raw['postal_code'] ?? ''
    ];

    // Formatear items
    $items = [];
    $total_declared_value = 0;

    if (empty($items_raw)) {
        // Si no hay items, usar valores por defecto
        $weight = (float)($data['weight'] ?? 0);
        $declared_value = (float)($data['declared_value'] ?? 0);
        $total_declared_value = $declared_value;

        $items = [[
            'sku' => 'CART-ITEM',
            'weight' => (int)($weight > 0 ? ($weight * 1000) : 100), // convertir kg a gramos
            'quantity' => 1,
            'description' => 'Producto del carrito',
            'classification_id' => 1
        ]];
    } else {
        // Formatear cada item
        foreach ($items_raw as $item) {
            $items[] = [
                'sku' => $item['sku'] ?? 'ITEM-' . uniqid(),
                'weight' => (int)($item['weight'] ?? 100),
                'quantity' => (int)($item['quantity'] ?? 1),
                'description' => $item['description'] ?? 'Producto',
                'classification_id' => (int)($item['classification_id'] ?? 1)
            ];
            $total_declared_value += (float)($item['declared_value'] ?? 0);
        }
    }

    // Check cache
    $cache_key = 'quote_' . md5(json_encode($destination) . json_encode($items) . $total_declared_value);
    $cache_file = __DIR__ . '/../../data/cache/' . $cache_key . '.json';
    $config = zipnova_get_config();
    $cache_minutes = $config['options']['cache_quotes_minutes'] ?? 5;

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < ($cache_minutes * 60)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        send_json_response($cached_data);
    }

    // Get quotes from Zipnova con nueva firma
    $result = zipnova_get_quotes($destination, $items, $total_declared_value);

    if ($result['success']) {
        // Save to cache
        $cache_dir = __DIR__ . '/../../data/cache';
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
    require_once APP_PATH . '/includes/email.php';
    require_once APP_PATH . '/includes/telegram.php';

    // Get raw POST data
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_ZIPNOVA_SIGNATURE'] ?? '';

    // Verify webhook signature if secret is configured
    $config = zipnova_get_config();
    $webhook_secret = $config['options']['webhook_secret'] ?? '';

    if (!empty($webhook_secret)) {
        if (!zipnova_webhook_verify($payload, $signature)) {
            zipnova_log('Webhook Rejected', ['reason' => 'Invalid signature']);
            send_json_response(['success' => false, 'error' => 'Invalid signature'], 403);
        }
    }

    // Parse webhook data
    $data = json_decode($payload, true);

    if (!$data) {
        zipnova_log('Webhook Rejected', ['reason' => 'Invalid JSON']);
        send_json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
    }

    // Process webhook
    $event_type = $data['event'] ?? 'status_update';
    $shipment_id = $data['shipment_id'] ?? '';
    $tracking_id = $data['tracking_id'] ?? $shipment_id;
    $new_status = $data['status'] ?? '';

    zipnova_log('Webhook Received', [
        'event' => $event_type,
        'shipment_id' => $shipment_id,
        'tracking_id' => $tracking_id,
        'status' => $new_status
    ]);

    // Update local shipment status
    if ($shipment_id && $new_status) {
        zipnova_update_shipment_status($shipment_id, [
            'status' => $new_status,
            'updated_at' => date('Y-m-d H:i:s'),
            'webhook_data' => $data
        ]);
    }

    // Update order shipping status
    if ($tracking_id && $new_status) {
        $updated = update_shipping_status_by_tracking($tracking_id, $new_status, $data);

        if ($updated) {
            // Find the order to send notification
            $orders = get_all_orders();
            $order = null;

            foreach ($orders as $o) {
                if (isset($o['shipping']['tracking_id']) && $o['shipping']['tracking_id'] === $tracking_id) {
                    $order = $o;
                    break;
                }
            }

            if ($order) {
                // Send email notification to customer
                send_shipping_status_notification($order, $new_status);

                zipnova_log('Notification Sent', [
                    'order_number' => $order['order_number'],
                    'customer_email' => $order['customer_email'],
                    'new_status' => $new_status
                ]);
            }
        }
    }

    send_json_response(['success' => true, 'message' => 'Webhook processed']);
}

/**
 * Send shipping status notification to customer
 */
function send_shipping_status_notification($order, $new_status) {
    require_once APP_PATH . '/includes/carriers.php';

    // Mapear estado de carrier a estado base del sistema
    $carrier_type = 'zipnova'; // Default
    $carrier = $order['shipping']['carrier'] ?? null;
    if ($carrier) {
        $carrier_config = get_carrier_config($carrier);
        $carrier_type = $carrier_config['type'] ?? 'zipnova';
    }

    // Convertir status de carrier a base status
    $base_status = map_carrier_status_to_base($carrier_type, $new_status);

    // Mensajes según estado base del sistema
    $status_messages = [
        'pendiente' => 'Tu envío está siendo procesado',
        'en_preparacion' => 'Tu pedido está en preparación',
        'en_transito' => 'Tu pedido está en camino',
        'en_reparto' => 'Tu pedido está en reparto',
        'entregada' => 'Tu pedido ha sido entregado',
        'fallida' => 'Hubo un problema con tu envío',
        'devuelta' => 'Tu envío está siendo devuelto',
        'cancelada' => 'Tu envío ha sido cancelado'
    ];

    $subject = $status_messages[$base_status] ?? 'Actualización de tu envío';
    $tracking_id = $order['shipping']['tracking_id'] ?? '';
    $order_number = $order['order_number'];

    $body = "
    <h2>{$subject}</h2>
    <p>Hola {$order['customer_name']},</p>
    <p>Te informamos que el estado de tu envío ha cambiado.</p>
    <p><strong>Orden:</strong> {$order_number}</p>
    <p><strong>Estado:</strong> {$subject}</p>
    ";

    if (!empty($tracking_id)) {
        $tracking_url = url('/tracking?id=' . urlencode($tracking_id));
        $body .= "<p><strong>Tracking ID:</strong> {$tracking_id}</p>";
        $body .= "<p><a href='{$tracking_url}'>Ver seguimiento completo</a></p>";
    }

    $body .= "<p>Gracias por tu compra.</p>";

    // Send email
    send_email(
        $order['customer_email'],
        $subject . ' - Orden ' . $order_number,
        $body
    );

    // Send Telegram notification if configured
    if (!empty($order['telegram_chat_id'])) {
        $telegram_message = "📦 *Actualización de envío*\n\n";
        $telegram_message .= "Orden: {$order_number}\n";
        $telegram_message .= "Estado: {$subject}\n";
        if (!empty($tracking_id)) {
            $telegram_message .= "Tracking: {$tracking_id}\n";
        }

        send_telegram_notification($order['telegram_chat_id'], $telegram_message);
    }
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
