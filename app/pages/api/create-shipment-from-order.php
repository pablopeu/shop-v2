<?php
/**
 * API Endpoint: Crear Envío desde Orden
 * Crea un envío en Zipnova usando los datos almacenados en una orden
 *
 * Este archivo NO es un entry point, debe ser accedido vía /api/?endpoint=create-shipment-from-order
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

// Require admin authentication
require_admin();

// Include carriers functions for zipnova_create_shipment()
require_once APP_PATH . '/includes/carriers.php';

// Rate limiting
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$identifier = 'create_shipment_' . $client_ip;
if (!check_rate_limit($identifier, 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Demasiados intentos. Espera un momento.'
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

// Validar CSRF token
if (!validate_csrf_token($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

// Validar parámetro requerido
$order_id = $data['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'order_id es requerido'
    ]);
    exit;
}

try {
    // Obtener la orden
    $order = get_order_by_id($order_id);

    if (!$order) {
        error_log("API CreateShipment: Orden no encontrada: $order_id");
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
        exit;
    }

    // Verificar que la orden NO tenga ya un envío creado
    if (!empty($order['shipping']['carrier_shipment_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Esta orden ya tiene un envío creado',
            'shipment_id' => $order['shipping']['carrier_shipment_id']
        ]);
        exit;
    }

    // Verificar que la orden tenga datos de cotización
    $quote_data = $order['shipping_quote_data'] ?? [];
    if (empty($quote_data) || empty($quote_data['rate_id'])) {
        error_log("API CreateShipment: Orden sin datos de cotización: $order_id");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'La orden no tiene datos de cotización de envío'
        ]);
        exit;
    }

    // Verificar que tenga dirección de envío
    $shipping_address = $order['shipping_address'] ?? [];
    if (empty($shipping_address)) {
        error_log("API CreateShipment: Orden sin dirección de envío: $order_id");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'La orden no tiene dirección de envío'
        ]);
        exit;
    }

    // Preparar datos para crear envío en Zipnova
    $shipment_data = [
        'rate_id' => $quote_data['rate_id'],
        'tariff_id' => $quote_data['tariff_id'] ?? null,
        'reference' => $order['order_number'] ?? $order_id,
        'destination' => [
            'name' => $shipping_address['name'] ?? $order['customer_name'],
            'street' => $shipping_address['street'] ?? $shipping_address['address'] ?? '',
            'city' => $shipping_address['city'] ?? '',
            'province' => $shipping_address['province'] ?? $shipping_address['state'] ?? '',
            'postal_code' => $shipping_address['postal_code'] ?? '',
            'country' => $shipping_address['country'] ?? 'AR',
            'phone' => $shipping_address['phone'] ?? $order['customer_phone'] ?? '',
            'email' => $order['customer_email'] ?? ''
        ],
        'customer' => [
            'name' => $order['customer_name'] ?? '',
            'email' => $order['customer_email'] ?? '',
            'phone' => $order['customer_phone'] ?? ''
        ],
        // Datos opcionales de la cotización
        'service_id' => $quote_data['service_id'] ?? null,
        'carrier_id' => $quote_data['carrier_id'] ?? null,
        'logistic_type' => $quote_data['logistic_type'] ?? null
    ];

    error_log("API CreateShipment: Creando envío para orden $order_id");

    // Crear envío en Zipnova
    $result = zipnova_create_shipment($shipment_data);

    if ($result['success']) {
        // Actualizar la orden con el shipment_id
        $shipment_id = $result['data']['shipment_id'] ?? null;

        if ($shipment_id) {
            // Obtener la orden actualizada
            $order = get_order_by_id($order_id);

            if (!isset($order['shipping'])) {
                $order['shipping'] = [];
            }

            $order['shipping']['carrier_shipment_id'] = $shipment_id;
            $order['shipping']['carrier'] = $quote_data['carrier_name'] ?? 'ZNVA';
            $order['shipping']['status'] = 'pendiente';
            $order['shipping']['created_at'] = date('Y-m-d H:i:s');

            // Guardar orden actualizada
            if (update_order_data($order_id, $order)) {
                error_log("API CreateShipment: Envío creado exitosamente: $shipment_id para orden $order_id");

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'shipment_id' => $shipment_id,
                        'order_id' => $order_id,
                        'carrier' => $order['shipping']['carrier']
                    ],
                    'message' => 'Envío creado exitosamente'
                ]);
            } else {
                error_log("API CreateShipment: Error al actualizar orden con shipment_id: $order_id");
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Envío creado pero error al actualizar orden'
                ]);
            }
        } else {
            error_log("API CreateShipment: Respuesta de Zipnova sin shipment_id");
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener ID del envío creado'
            ]);
        }
    } else {
        error_log("API CreateShipment: Error al crear envío: " . ($result['error'] ?? 'Unknown'));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Error al crear envío en el carrier'
        ]);
    }

} catch (Exception $e) {
    error_log("API CreateShipment: Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la solicitud'
    ]);
}
