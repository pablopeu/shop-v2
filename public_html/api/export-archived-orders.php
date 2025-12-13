<?php
define('APP_ENTRY_POINT', true);
// Bootstrap de la aplicación - detectar entorno
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    require_once __DIR__ . '/../../app/bootstrap.php';
}

/**
 * API - Export Archived Orders
 * Exports archived orders in CSV or JSON format
 */


// Start session
session_start();

// Check admin authentication
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Check if order IDs are provided
if (!isset($_POST['order_ids']) || !is_array($_POST['order_ids'])) {
    http_response_code(400);
    echo 'Order IDs are required';
    exit;
}

$order_ids = $_POST['order_ids'];
$format = $_POST['format'] ?? 'json';

// Get all archived orders
$all_archived = get_archived_orders();

// Filter selected orders
$selected_orders = array_filter($all_archived, function($order) use ($order_ids) {
    return in_array($order['id'], $order_ids);
});

if (empty($selected_orders)) {
    http_response_code(404);
    echo 'No orders found';
    exit;
}

// Export based on format
if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="envios_archivados_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Headers
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

    // Add data rows
    foreach ($selected_orders as $order) {
        // Build products string
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
            $order['subtotal'] ?? 0,
            $order['shipping_cost'] ?? 0,
            ($order['discount_promotion'] ?? 0) + ($order['discount_coupon'] ?? 0),
            $order['total'],
            $order['currency'],
            $products_str,
            $order['notes'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} else {
    // JSON format
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="envios_archivados_' . date('Y-m-d_His') . '.json"');

    // Format orders for JSON
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
            'subtotal' => $order['subtotal'] ?? 0,
            'shipping_cost' => $order['shipping_cost'] ?? 0,
            'discount_promotion' => $order['discount_promotion'] ?? 0,
            'discount_coupon' => $order['discount_coupon'] ?? 0,
            'coupon_code' => $order['coupon_code'] ?? null,
            'total' => $order['total'],
            'currency' => $order['currency'],
            'notes' => $order['notes'] ?? ''
        ];
    }

    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
