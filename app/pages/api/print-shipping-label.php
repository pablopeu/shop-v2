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

// Include carriers functions for zipnova_get_label()
require_once APP_PATH . '/includes/carriers.php';

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

// Get order_number for logging if we have order
$order_number = null;
if (!empty($order_id)) {
    $order = $order ?? get_order_by_id($order_id);
    $order_number = $order['order_number'] ?? $order_id;
}

// Get label from Zipnova (pasar order_number para logs)
$result = zipnova_get_label($shipment_id, $format, $order_number);

if (!$result['success']) {
    // Log admin action
    log_admin_action('print_label_failed', $_SESSION['username'], [
        'shipment_id' => $shipment_id,
        'order_id' => $order_id,
        'format' => $format,
        'error' => $result['error'] ?? 'Unknown error',
        'http_code' => $result['http_code'] ?? null
    ]);

    // Propagar el código HTTP de Zipnova si existe
    if (isset($result['http_code'])) {
        http_response_code($result['http_code']);
    } else {
        http_response_code(500);
    }

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

// Si action=download, servir el PDF directamente
if ($action === 'download') {
    $label_url = $result['data']['label_url'];
    $filepath = APP_PATH . $label_url;

    if (file_exists($filepath)) {
        // Headers para PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="etiqueta_' . $shipment_id . '.pdf"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Archivo de etiqueta no encontrado'
        ]);
        exit;
    }
}

// Si action=view, devolver JSON
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
