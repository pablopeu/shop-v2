<?php
/**
 * API: Print Shipping Label
 *
 * Obtiene la etiqueta de envío desde Zipnova y la retorna para impresión/descarga
 *
 * Métodos permitidos: GET, POST
 * Autenticación: Requiere sesión de admin
 *
 * Parámetros:
 * - order_id: ID de la orden (opcional si se proporciona shipment_id)
 * - shipment_id: ID del envío en Zipnova (opcional si se proporciona order_id)
 * - format: Formato deseado (pdf, png, zpl) - default: pdf
 * - action: 'preview' | 'download' - default: download
 *
 * Respuesta:
 * - success: true/false
 * - data: {label_url, format, cached}
 * - error: mensaje de error
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require admin authentication
require_admin();

// Set JSON response header
header('Content-Type: application/json');

// Get request parameters
$order_id = sanitize_input($_REQUEST['order_id'] ?? '');
$shipment_id = sanitize_input($_REQUEST['shipment_id'] ?? '');
$format = sanitize_input($_REQUEST['format'] ?? 'pdf');
$action = sanitize_input($_REQUEST['action'] ?? 'download');

// Validate format
$valid_formats = ['pdf', 'png', 'zpl'];
if (!in_array($format, $valid_formats)) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato inválido. Formatos permitidos: ' . implode(', ', $valid_formats)
    ]);
    exit;
}

// Determine shipment_id from order_id if not provided
if (empty($shipment_id) && !empty($order_id)) {
    $order = get_order_by_id($order_id);

    if (!$order) {
        echo json_encode([
            'success' => false,
            'error' => 'Orden no encontrada'
        ]);
        exit;
    }

    // Check if order has shipping data
    if (empty($order['shipping']['carrier_shipment_id'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Esta orden no tiene un envío asociado'
        ]);
        exit;
    }

    $shipment_id = $order['shipping']['carrier_shipment_id'];
}

// Validate shipment_id
if (empty($shipment_id)) {
    echo json_encode([
        'success' => false,
        'error' => 'Se requiere order_id o shipment_id'
    ]);
    exit;
}

// Get label from Zipnova
$result = zipnova_get_label($shipment_id, $format);

if (!$result['success']) {
    // Log admin action
    log_admin_action('print_label_failed', $_SESSION['username'], [
        'shipment_id' => $shipment_id,
        'order_id' => $order_id,
        'format' => $format,
        'error' => $result['error'] ?? 'Unknown error'
    ]);

    echo json_encode($result);
    exit;
}

// Log successful label request
log_admin_action('print_label', $_SESSION['username'], [
    'shipment_id' => $shipment_id,
    'order_id' => $order_id,
    'format' => $format,
    'action' => $action,
    'cached' => $result['data']['cached'] ?? false
]);

// Return label data
echo json_encode([
    'success' => true,
    'data' => [
        'label_url' => $result['data']['label_url'],
        'format' => $result['data']['format'],
        'cached' => $result['data']['cached'] ?? false,
        'shipment_id' => $shipment_id,
        'action' => $action
    ],
    'message' => 'Etiqueta obtenida exitosamente'
]);
