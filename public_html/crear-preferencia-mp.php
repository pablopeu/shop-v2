<?php
/**
 * Crear Preferencia de MercadoPago
 * API endpoint para crear preferencias de pago
 */

define('APP_ENTRY_POINT', true);

// Detectar entorno y cargar bootstrap
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    // Producción
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} else {
    // Desarrollo
    $bootstrap_path = __DIR__ . '/../app/bootstrap.php';
    if (!file_exists($bootstrap_path)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Bootstrap not found']);
        exit;
    }
    require_once $bootstrap_path;
}

// Establecer header JSON
header('Content-Type: application/json');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
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

$order_id = $data['order_id'] ?? null;
$tracking_token = $data['tracking_token'] ?? null;

if (!$order_id || !$tracking_token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id y tracking_token son requeridos']);
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
    error_log("crear-preferencia-mp: Access token vacío");
    echo json_encode(['success' => false, 'error' => 'Configuración de MercadoPago incompleta']);
    exit;
}

// Obtener la orden
$order = get_order_by_id($order_id);

if (!$order) {
    error_log("crear-preferencia-mp: Orden no encontrada: $order_id");
    echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
    exit;
}

// Validar tracking token
if ($order['tracking_token'] !== $tracking_token) {
    error_log("crear-preferencia-mp: Token inválido para orden: $order_id");
    echo json_encode(['success' => false, 'error' => 'Token inválido']);
    exit;
}

// Verificar que la orden aún no esté pagada
if ($order['status'] === 'cobrada' || $order['status'] === 'entregada') {
    error_log("crear-preferencia-mp: Orden ya pagada: $order_id");
    echo json_encode(['success' => false, 'error' => 'Esta orden ya fue pagada']);
    exit;
}

try {
    // Crear instancia de MercadoPago
    $mp = new MercadoPago($access_token, $sandbox_mode);

    // Preparar items para la preferencia
    $items = [];
    foreach ($order['items'] as $item) {
        $items[] = [
            'title' => $item['name'],
            'quantity' => (int) $item['quantity'],
            'unit_price' => (float) ($item['final_price'] ?? $item['price']),
            'currency_id' => $order['currency'] === 'USD' ? 'USD' : 'ARS'
        ];
    }

    // URLs de retorno - usar URL absoluta con dominio
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'peu.net';
    $base_url = $protocol . '://' . $host . url('');
    $return_url = $base_url . '/checkout-return?order=' . $order_id . '&token=' . $tracking_token;

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

    error_log("crear-preferencia-mp: Creando preferencia para orden $order_id");

    // Crear preferencia
    $preference = $mp->createPreference($preference_data);

    if (isset($preference['id'])) {
        error_log("crear-preferencia-mp: Preferencia creada: " . $preference['id']);
        echo json_encode([
            'success' => true,
            'preference_id' => $preference['id'],
            'init_point' => $mp->getInitPoint($preference)
        ]);
    } else {
        error_log("crear-preferencia-mp: Error - respuesta sin ID: " . json_encode($preference));
        echo json_encode(['success' => false, 'error' => 'Error al crear preferencia']);
    }

} catch (Exception $e) {
    error_log("crear-preferencia-mp: Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
