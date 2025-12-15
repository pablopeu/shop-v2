<?php
/**
 * API Endpoint: Export Archived Orders
 * Exporta órdenes archivadas en formato CSV o JSON
 *
 * SEGURIDAD:
 * - Requiere autenticación de admin
 * - Rate limiting estricto (1 export cada 5 minutos)
 * - Logging obligatorio de todas las exportaciones
 * - Validación CSRF
 */

// Security check - REQUIRED
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Verificar autenticación de admin
if (!is_admin_logged_in()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Rate limiting MUY estricto para exportaciones (1 cada 5 minutos por admin)
$export_identifier = 'export_archived_' . ($_SESSION['user_id'] ?? 'unknown');
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!check_rate_limit($export_identifier, 1, 300)) { // 1 export cada 5 min
    http_response_code(429);
    error_log("SECURITY: Export archived rate limit excedido - User: {$_SESSION['username']}, IP: $client_ip");
    echo json_encode([
        'error' => 'Demasiadas exportaciones. Por favor espera 5 minutos antes de exportar nuevamente.'
    ]);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Validar CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    error_log("SECURITY: CSRF token inválido en export archived - User: {$_SESSION['username']}, IP: $client_ip");
    echo json_encode(['error' => 'Token de seguridad inválido']);
    exit;
}

// Validar que order_ids sea un array
if (!isset($_POST['order_ids']) || !is_array($_POST['order_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'IDs de órdenes requeridos']);
    exit;
}

// Validar que no se exporten más de 1000 órdenes a la vez
if (count($_POST['order_ids']) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Máximo 1000 órdenes por exportación']);
    exit;
}

$order_ids = $_POST['order_ids'];
$format = sanitize_input($_POST['format'] ?? 'json');

// Validar formato
if (!in_array($format, ['csv', 'json'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato inválido. Use: csv o json']);
    exit;
}

// Obtener todas las órdenes archivadas
$all_archived = get_archived_orders();

// Filtrar órdenes seleccionadas
$selected_orders = array_filter($all_archived, function($order) use ($order_ids) {
    return in_array($order['id'], $order_ids);
});

if (empty($selected_orders)) {
    http_response_code(404);
    echo json_encode(['error' => 'No se encontraron órdenes archivadas']);
    exit;
}

// LOG DE SEGURIDAD: Registrar la exportación
$export_log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'user_id' => $_SESSION['user_id'] ?? 'unknown',
    'username' => $_SESSION['username'] ?? 'unknown',
    'ip' => $client_ip,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'action' => 'export_archived_orders',
    'format' => $format,
    'order_count' => count($selected_orders),
    'order_ids' => array_slice($order_ids, 0, 10) // Solo primeros 10 IDs
];

error_log("AUDIT: Export archived realizado - " . json_encode($export_log));

// Guardar en archivo de auditoría
$audit_file = APP_PATH . '/data/logs/exports.log';
$audit_dir = dirname($audit_file);
if (!is_dir($audit_dir)) {
    mkdir($audit_dir, 0755, true);
}
file_put_contents($audit_file, json_encode($export_log) . "\n", FILE_APPEND | LOCK_EX);

// Exportar según formato
if ($format === 'csv') {
    // Headers para descarga CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="envios_archivados_' . date('Y-m-d_His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // BOM para soporte UTF-8 en Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers CSV
    fputcsv($output, [
        'Nº Orden',
        'Fecha Orden',
        'Fecha Archivo',
        'Cliente',
        'Email',
        'Teléfono',
        'Estado',
        'Método de Entrega',
        'Dirección',
        'Nº Seguimiento',
        'Subtotal',
        'Envío',
        'Descuento',
        'Total',
        'Moneda',
        'Productos',
        'Notas'
    ]);

    // Filas de datos
    foreach ($selected_orders as $order) {
        // Construir string de productos
        $products_str = '';
        foreach ($order['items'] as $item) {
            $products_str .= $item['name'] . ' (x' . $item['quantity'] . '), ';
        }
        $products_str = rtrim($products_str, ', ');

        fputcsv($output, [
            $order['order_number'],
            date('d/m/Y H:i', strtotime($order['date'])),
            isset($order['archived_date']) ? date('d/m/Y H:i', strtotime($order['archived_date'])) : 'N/A',
            $order['customer_name'] ?? 'N/A',
            $order['customer_email'] ?? 'N/A',
            $order['customer_phone'] ?? 'N/A',
            $order['status'],
            ($order['delivery_method'] ?? 'pickup') === 'pickup' ? 'Retiro' : 'Envío',
            $order['shipping_address'] ?? 'N/A',
            $order['tracking_number'] ?? 'N/A',
            number_format($order['subtotal'] ?? 0, 2, '.', ''),
            number_format($order['shipping_cost'] ?? 0, 2, '.', ''),
            number_format(($order['discount_promotion'] ?? 0) + ($order['discount_coupon'] ?? 0), 2, '.', ''),
            number_format($order['total'], 2, '.', ''),
            $order['currency'],
            $products_str,
            $order['notes'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} else {
    // Formato JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="envios_archivados_' . date('Y-m-d_His') . '.json"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Formatear órdenes para JSON
    $export_data = [];
    foreach ($selected_orders as $order) {
        $export_data[] = [
            'order_number' => $order['order_number'],
            'date' => $order['date'],
            'archived_date' => $order['archived_date'] ?? null,
            'customer' => [
                'name' => $order['customer_name'] ?? null,
                'email' => $order['customer_email'] ?? null,
                'phone' => $order['customer_phone'] ?? null
            ],
            'status' => $order['status'],
            'delivery_method' => $order['delivery_method'] ?? 'pickup',
            'shipping_address' => $order['shipping_address'] ?? null,
            'tracking_number' => $order['tracking_number'] ?? null,
            'tracking_url' => $order['tracking_url'] ?? null,
            'items' => $order['items'],
            'subtotal' => round($order['subtotal'] ?? 0, 2),
            'shipping_cost' => round($order['shipping_cost'] ?? 0, 2),
            'discount_promotion' => round($order['discount_promotion'] ?? 0, 2),
            'discount_coupon' => round($order['discount_coupon'] ?? 0, 2),
            'coupon_code' => $order['coupon_code'] ?? null,
            'total' => round($order['total'], 2),
            'currency' => $order['currency'],
            'notes' => $order['notes'] ?? ''
        ];
    }

    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
