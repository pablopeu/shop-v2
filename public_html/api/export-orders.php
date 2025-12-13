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
 * API - Export Orders
 * Exports orders in CSV or JSON format
 */


// Check admin authentication
if (!is_admin_logged_in()) {
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
$prefix = $_POST['prefix'] ?? 'ordenes'; // Default prefix if not specified

// Get all orders
$all_orders = get_all_orders();

// Filter selected orders
$selected_orders = array_filter($all_orders, function($order) use ($order_ids) {
    return in_array($order['id'], $order_ids);
});

if (empty($selected_orders)) {
    http_response_code(404);
    echo 'No orders found';
    exit;
}

// Sort orders by order number (ascending)
usort($selected_orders, function($a, $b) {
    return strcmp($a['order_number'], $b['order_number']);
});

// Export based on format
if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $prefix . '_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Headers
    fputcsv($output, [
        'Nº Orden',
        'Fecha',
        'Cliente',
        'Email',
        'Teléfono',
        'Estado',
        'Método de Entrega',
        'Dirección de Envío',
        'Nº Seguimiento',
        'URL Seguimiento',
        'Subtotal',
        'Costo de Envío',
        'Descuento',
        'Total ARS',
        'Total USD',
        'Cotización Dólar',
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

        // Calculate amounts in ARS and USD
        $exchange_rate = $order['exchange_rate'] ?? 1000;
        $total = $order['total'];
        $currency = $order['currency'];

        if ($currency === 'USD') {
            $total_usd = $total;
            $total_ars = $total * $exchange_rate;
        } else {
            // ARS
            $total_ars = $total;
            $total_usd = $total / $exchange_rate;
        }

        fputcsv($output, [
            $order['order_number'],
            date('d/m/Y H:i', strtotime($order['date'])),
            $order['customer_name'] ?? 'N/A',
            $order['customer_email'] ?? 'N/A',
            $order['customer_phone'] ?? 'N/A',
            $order['status'],
            ($order['delivery_method'] ?? 'pickup') === 'pickup' ? 'Retiro' : 'Envío',
            $order['shipping_address'] ?? 'N/A',
            $order['tracking_number'] ?? 'N/A',
            $order['tracking_url'] ?? 'N/A',
            number_format($order['subtotal'] ?? 0, 2, '.', ''),
            number_format($order['shipping_cost'] ?? 0, 2, '.', ''),
            number_format(($order['discount_promotion'] ?? 0) + ($order['discount_coupon'] ?? 0), 2, '.', ''),
            number_format($total_ars, 2, '.', ''),
            number_format($total_usd, 2, '.', ''),
            number_format($exchange_rate, 2, '.', ''),
            $products_str,
            $order['notes'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} else {
    // JSON format
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $prefix . '_' . date('Y-m-d_His') . '.json"');

    // Format orders for JSON
    $export_data = [];
    foreach ($selected_orders as $order) {
        // Calculate amounts in ARS and USD
        $exchange_rate = $order['exchange_rate'] ?? 1000;
        $total = $order['total'];
        $currency = $order['currency'];

        if ($currency === 'USD') {
            $total_usd = $total;
            $total_ars = $total * $exchange_rate;
        } else {
            $total_ars = $total;
            $total_usd = $total / $exchange_rate;
        }

        $export_data[] = [
            'order_number' => $order['order_number'],
            'date' => $order['date'],
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
            'total_ars' => round($total_ars, 2),
            'total_usd' => round($total_usd, 2),
            'exchange_rate' => round($exchange_rate, 2),
            'currency' => $order['currency'],
            'notes' => $order['notes'] ?? ''
        ];
    }

    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
