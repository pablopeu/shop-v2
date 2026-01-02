<?php
/**
 * API Endpoint: Crear Preferencia de MercadoPago
 * Crea una preferencia de pago en MercadoPago para una orden específica
 *
 * Este archivo NO es un entry point, debe ser accedido vía /api/?endpoint=crear-preferencia-mp
 */

// Security check - REQUIRED
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Rate limiting específico para creación de preferencias (más restrictivo)
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$mp_identifier = 'mp_create_' . $client_ip;
if (!check_rate_limit($mp_identifier, 20, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Demasiados intentos de crear preferencias. Espera un momento.'
    ]);
    exit;
}

// Obtener datos del request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

// Validar parámetros requeridos
$order_id = $data['order_id'] ?? null;
$tracking_token = $data['tracking_token'] ?? null;

if (!$order_id || !$tracking_token) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'order_id y tracking_token son requeridos'
    ]);
    exit;
}

// Validar formato de order_id (debe ser alfanumérico)
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $order_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Formato de order_id inválido'
    ]);
    exit;
}

// Cargar configuración
$payment_config = read_json(APP_PATH . '/config/payment.json');
$payment_credentials = get_payment_credentials();
$site_config = read_json(APP_PATH . '/config/site.json');

$mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
$sandbox_mode = ($mp_mode === 'sandbox');

$access_token = $sandbox_mode ?
    ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
    ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

if (empty($access_token)) {
    error_log("API MP: Access token vacío (modo: $mp_mode)");
    echo json_encode([
        'success' => false,
        'error' => 'Configuración de MercadoPago incompleta'
    ]);
    exit;
}

// Obtener la orden
$order = get_order_by_id($order_id);

if (!$order) {
    error_log("API MP: Orden no encontrada: $order_id");
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
    exit;
}

// Validar tracking token (CRÍTICO para seguridad)
if ($order['tracking_token'] !== $tracking_token) {
    error_log("API MP: Token inválido para orden: $order_id desde IP: " . $client_ip);
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

// Verificar que la orden aún no esté pagada
if ($order['status'] === 'cobrada' || $order['status'] === 'entregada') {
    error_log("API MP: Intento de pagar orden ya cobrada: $order_id");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Esta orden ya fue pagada']);
    exit;
}

// Verificar que la orden tenga items
if (empty($order['items'])) {
    error_log("API MP: Orden sin items: $order_id");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La orden no tiene productos']);
    exit;
}

try {
    // Crear instancia de MercadoPago
    $mp = new MercadoPago($access_token, $sandbox_mode);

    // Preparar items para la preferencia
    $items = [];
    foreach ($order['items'] as $item) {
        // Validar que cada item tenga los datos necesarios
        if (!isset($item['name']) || !isset($item['quantity'])) {
            error_log("API MP: Item inválido en orden $order_id: " . json_encode($item));
            continue;
        }

        // Calcular precio unitario (final_price es el total, dividimos por quantity)
        $unit_price = (float) ($item['final_price'] ?? $item['price']);
        if ($item['quantity'] > 1) {
            $unit_price = $unit_price / $item['quantity'];
        }

        $items[] = [
            'title' => $item['name'],
            'quantity' => (int) $item['quantity'],
            'unit_price' => $unit_price,
            'currency_id' => $order['currency'] === 'USD' ? 'USD' : 'ARS'
        ];
    }

    // Agregar costo de envío como un item separado si existe
    $shipping_cost = (float)($order['shipping_cost'] ?? 0);
    if ($shipping_cost > 0) {
        $shipping_service_name = $order['shipping']['service_name'] ?? 'Envío';
        $items[] = [
            'title' => '🚚 ' . $shipping_service_name,
            'quantity' => 1,
            'unit_price' => $shipping_cost,
            'currency_id' => $order['currency'] === 'USD' ? 'USD' : 'ARS'
        ];
    }

    if (empty($items)) {
        error_log("API MP: No se pudieron procesar items de orden $order_id");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Error al procesar los productos']);
        exit;
    }

    // URLs de retorno - usar URL absoluta con dominio
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $protocol . '://' . $host . url('');
    $return_url = $base_url . '/gracias?order=' . $order_id . '&token=' . $tracking_token;

    // Datos de la preferencia
    $preference_data = [
        'items' => $items,
        'back_urls' => [
            'success' => $return_url . '&status=approved',
            'failure' => $return_url . '&status=rejected',
            'pending' => $return_url . '&status=pending'
        ],
        'auto_return' => 'approved',
        'external_reference' => $order_id,
        'notification_url' => $base_url . '/webhook.php',
        'statement_descriptor' => $site_config['site_name'] ?? 'Shop',
        'payer' => [
            'name' => $order['customer_name'] ?? '',
            'email' => $order['customer_email'] ?? ''
        ]
    ];

    error_log("API MP: Creando preferencia para orden $order_id (items: " . count($items) . ")");

    // Crear preferencia
    $preference = $mp->createPreference($preference_data);

    if (isset($preference['id'])) {
        error_log("API MP: Preferencia creada exitosamente: " . $preference['id'] . " para orden $order_id");

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'preference_id' => $preference['id'],
            'init_point' => $mp->getInitPoint($preference)
        ]);
    } else {
        error_log("API MP: Respuesta sin ID de preferencia: " . json_encode($preference));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al crear preferencia de pago'
        ]);
    }

} catch (Exception $e) {
    error_log("API MP: Exception al crear preferencia para orden $order_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar el pago. Por favor, intenta nuevamente.'
    ]);
}
