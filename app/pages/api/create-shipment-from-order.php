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

    // Obtener configuración de Zipnova para origin_id
    $shipping_config = read_json(APP_PATH . '/config/shipping.json');
    $znva_config = $shipping_config['carriers']['ZNVA'] ?? null;

    if (!$znva_config || empty($znva_config['origin']['origin_id'])) {
        error_log("API CreateShipment: Configuración de Zipnova no encontrada");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Configuración de envío no encontrada'
        ]);
        exit;
    }

    // Separar calle y número de calle
    $street_full = $shipping_address['street'] ?? $shipping_address['address'] ?? '';
    $street_parts = preg_match('/^(.+?)\s+(\d+.*)$/', $street_full, $matches);
    $street = $street_parts ? trim($matches[1]) : $street_full;
    $street_number = $street_parts ? trim($matches[2]) : '';

    // Construir items desde la orden (igual que en quote)
    $items = [];
    if (isset($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $product = get_product_by_slug($item['slug'] ?? '');
            $items[] = [
                'sku' => $item['slug'] ?? 'ITEM',
                'weight' => (int)($product['weight'] ?? 500), // gramos
                'height' => (int)($product['height'] ?? 10),  // cm
                'width' => (int)($product['width'] ?? 10),    // cm
                'length' => (int)($product['length'] ?? 10),  // cm
                'description' => $item['name'] ?? 'Producto',
                'classification_id' => 1
            ];
        }
    }

    // Si no hay items, usar defaults
    if (empty($items)) {
        $items[] = [
            'sku' => 'ORDER-ITEM',
            'weight' => 500,
            'height' => 10,
            'width' => 10,
            'length' => 10,
            'description' => 'Pedido ' . ($order['order_number'] ?? $order_id),
            'classification_id' => 1
        ];
    }

    // Calcular valor declarado (solo productos, SIN envío ni descuentos)
    $declared_value = 0;
    if (isset($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $item_price = (float)($item['price'] ?? 0);
            $item_quantity = (int)($item['quantity'] ?? 1);
            $declared_value += $item_price * $item_quantity;
        }
    }
    // Si no hay items, usar subtotal o total - shipping_cost
    if ($declared_value == 0) {
        $declared_value = (float)($order['subtotal'] ?? $order['total'] ?? 0);
        $shipping_cost = (float)($order['shipping_cost'] ?? 0);
        $declared_value = max(0, $declared_value - $shipping_cost);
    }

    // Preparar datos para crear envío en Zipnova (formato correcto según API)
    $shipment_data = [
        // IDs y referencias
        'rate_id' => $quote_data['rate_id'],
        'tariff_id' => $quote_data['tariff_id'] ?? null,
        'external_id' => $order['order_number'] ?? 'ORD-' . $order_id,
        'reference' => $order['order_number'] ?? $order_id,

        // Tipo de servicio (código, no ID)
        'service_type' => $quote_data['service_type_code'] ?? $quote_data['service_id'] ?? 'standard_delivery',

        // Valor declarado (solo mercadería, SIN envío)
        'declared_value' => (int)$declared_value,

        // Origen (solo el ID, no el objeto completo)
        'origin_id' => $znva_config['origin']['origin_id'],

        // Items del paquete
        'items' => $items,

        // Destino (con campos correctos: zipcode, state, street_number, document)
        'destination' => [
            'name' => $shipping_address['name'] ?? $order['customer_name'],
            'street' => $street,
            'street_number' => $street_number,
            'city' => $shipping_address['city'] ?? '',
            'state' => $shipping_address['province'] ?? $shipping_address['state'] ?? '',
            'zipcode' => $shipping_address['postal_code'] ?? '',
            'country' => $shipping_address['country'] ?? 'AR',
            'phone' => $shipping_address['phone'] ?? $order['customer_phone'] ?? '',
            'email' => $order['customer_email'] ?? '',
            'document' => $shipping_address['document'] ?? $order['customer_document'] ?? '' // DNI/CUIT (REQUERIDO)
        ]
    ];

    error_log("API CreateShipment: Creando envío para orden $order_id");

    // Crear envío en Zipnova (pasar order_number o order_id para logs)
    $result = zipnova_create_shipment($shipment_data, $order['order_number'] ?? $order_id);

    if ($result['success']) {
        // Actualizar la orden con el shipment_id (Zipnova lo devuelve como 'id')
        $shipment_id = $result['data']['id'] ?? null;

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
