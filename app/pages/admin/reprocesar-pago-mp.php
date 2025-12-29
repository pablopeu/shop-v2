<?php
/**
 * Reprocesar Pago de Mercadopago
 * Herramienta administrativa para reprocesar pagos que fallaron en el webhook
 */


require_once APP_PATH . '/includes/mercadopago.php';
require_once APP_PATH . '/includes/mp-logger.php';


// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$payment_config = read_json(APP_PATH . '/config/payment.json');
$payment_credentials = get_payment_credentials();

$payment_details = null;
$order_info = null;
$error = '';
$message = '';
$reprocess_result = null;

// Handle payment search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_payment'])) {
    $payment_id = sanitize_input($_POST['payment_id'] ?? '');

    if (empty($payment_id)) {
        $error = 'Por favor ingresa un Payment ID';
    } else {
        try {
            $mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
            $sandbox_mode = ($mode === 'sandbox');
            $access_token = $sandbox_mode ?
                ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
                ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

            if (empty($access_token)) {
                $error = 'Mercadopago no configurado. Verifica tus credenciales.';
            } else {
                $mp = new MercadoPago($access_token, $sandbox_mode);
                $payment_details = $mp->getPayment($payment_id);

                // Try to find related order
                // external_reference contains order_number (ORD-YYYY-XXXXX), not order ID
                if (isset($payment_details['external_reference'])) {
                    $all_orders = get_all_orders();
                    foreach ($all_orders as $order) {
                        if ($order['order_number'] === $payment_details['external_reference']) {
                            $order_info = $order;
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error al consultar el pago: ' . $e->getMessage();
        }
    }
}

// Handle payment reprocessing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reprocess_payment'])) {
    $payment_id = sanitize_input($_POST['payment_id'] ?? '');

    try {
        // Log inicio del reprocesamiento
        log_mp_debug('MANUAL_REPROCESS', "Iniciando reprocesamiento manual del pago desde admin", [
            'payment_id' => $payment_id,
            'admin_user' => $_SESSION['username'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        // Get payment config and credentials
        $mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
        $sandbox_mode = ($mp_mode === 'sandbox');

        $access_token = $sandbox_mode ?
            ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
            ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

        if (empty($access_token)) {
            throw new Exception('MercadoPago no está configurado correctamente');
        }

        // Get payment details from Mercadopago
        $mp = new MercadoPago($access_token, $sandbox_mode);
        $payment = $mp->getPayment($payment_id);

        // Log payment details
        log_payment_details($payment_id, $payment);

        // Find order by external_reference (order_number, NOT order id)
        $order_number = $payment['external_reference'] ?? null;

        if (!$order_number) {
            throw new Exception('El pago no tiene external_reference (order_number)');
        }

        // Load orders
        $orders_file = APP_PATH . '/data/orders.json';

        if (!file_exists($orders_file)) {
            throw new Exception('Archivo de órdenes no encontrado');
        }

        $orders_data = read_json($orders_file);

        if (!isset($orders_data['orders']) || !is_array($orders_data['orders'])) {
            throw new Exception('Estructura de órdenes inválida');
        }

        // Search by order_number (external_reference), NOT by id
        $order_index = null;
        $order_id = null;
        foreach ($orders_data['orders'] as $index => $order) {
            if ($order['order_number'] === $order_number) {
                $order_index = $index;
                $order_id = $order['id'];
                break;
            }
        }

        if ($order_index === null) {
            throw new Exception("Orden no encontrada con número: $order_number");
        }

        $order = $orders_data['orders'][$order_index];
        $old_status = $order['status'];

        // Process payment status
        $payment_status = $payment['status'];
        $status_detail = $payment['status_detail'];

        // Map Mercadopago status to order status
        $new_order_status = null;
        $restore_stock = false;

        switch ($payment_status) {
            case 'approved':
                $new_order_status = 'cobrada';
                break;
            case 'authorized':
                $new_order_status = 'pendiente';
                break;
            case 'pending':
            case 'in_process':
                $new_order_status = 'pendiente';
                break;
            case 'in_mediation':
                $new_order_status = 'pendiente';
                break;
            case 'rejected':
            case 'cancelled':
                $new_order_status = 'rechazada';
                $restore_stock = true;
                break;
            case 'refunded':
            case 'charged_back':
                $new_order_status = 'cancelada';
                $restore_stock = true;
                break;
            default:
                throw new Exception("Estado de pago desconocido: $payment_status");
        }

        $reprocess_result = [
            'payment_id' => $payment_id,
            'order_id' => $order_id,
            'order_number' => $order['order_number'] ?? 'N/A',
            'old_status' => $old_status,
            'new_status' => $new_order_status,
            'payment_status' => $payment_status,
            'status_detail' => $status_detail,
            'amount' => $payment['transaction_amount'] ?? 0,
            'currency' => $payment['currency_id'] ?? 'ARS',
            'actions_taken' => [],
            'notifications_sent' => []
        ];

        if ($new_order_status && $order['status'] !== $new_order_status) {
            // Update order status
            $orders_data['orders'][$order_index]['status'] = $new_order_status;
            $orders_data['orders'][$order_index]['payment_status'] = $payment_status;
            $orders_data['orders'][$order_index]['payment_status_detail'] = $status_detail;
            $orders_data['orders'][$order_index]['payment_id'] = $payment_id;

            // Extract fee details and net amount
            $fee_details = $payment['fee_details'] ?? [];
            $transaction_details = $payment['transaction_details'] ?? [];

            // Calculate total fees from fee_details
            $total_fees = 0;
            $fee_breakdown = [];
            foreach ($fee_details as $fee) {
                $fee_amount = floatval($fee['amount'] ?? 0);
                $total_fees += $fee_amount;
                $fee_breakdown[] = [
                    'type' => $fee['type'] ?? 'unknown',
                    'amount' => $fee_amount,
                    'fee_payer' => $fee['fee_payer'] ?? 'collector'
                ];
            }

            // Get net amount
            $net_received_amount = floatval($transaction_details['net_received_amount'] ?? 0);
            if ($net_received_amount == 0 && isset($payment['transaction_amount'])) {
                $net_received_amount = floatval($payment['transaction_amount']) - $total_fees;
            }

            $reprocess_result['total_fees'] = $total_fees;
            $reprocess_result['net_amount'] = $net_received_amount;

            // Update mercadopago_data
            $orders_data['orders'][$order_index]['mercadopago_data'] = [
                'payment_id' => $payment['id'],
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'],
                'transaction_amount' => $payment['transaction_amount'] ?? null,
                'currency_id' => $payment['currency_id'] ?? null,
                'date_created' => $payment['date_created'] ?? null,
                'date_approved' => $payment['date_approved'] ?? null,
                'date_last_updated' => $payment['date_last_updated'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'payment_type_id' => $payment['payment_type_id'] ?? null,
                'installments' => $payment['installments'] ?? 1,
                'description' => $payment['description'] ?? null,
                'capture' => $payment['capture'] ?? null,
                'external_reference' => $payment['external_reference'] ?? null,
                'payer_email' => $payment['payer']['email'] ?? null,
                'payer_identification' => $payment['payer']['identification']['number'] ?? null,
                'card_last_four_digits' => $payment['card']['last_four_digits'] ?? null,
                'card_first_six_digits' => $payment['card']['first_six_digits'] ?? null,
                'total_paid_amount' => floatval($payment['transaction_amount'] ?? 0),
                'fee_details' => $fee_breakdown,
                'total_fees' => $total_fees,
                'net_received_amount' => $net_received_amount,
                'operation_number' => $transaction_details['external_resource_url'] ?? null,
                'payment_method_reference_id' => $transaction_details['payment_method_reference_id'] ?? null,
                'acquirer_reference' => $transaction_details['acquirer_reference'] ?? null,
                'manual_reprocess_at' => date('Y-m-d H:i:s'),
                'manual_reprocess_by' => $_SESSION['username'] ?? 'admin',
            ];

            // Add to status history
            if (!isset($orders_data['orders'][$order_index]['status_history'])) {
                $orders_data['orders'][$order_index]['status_history'] = [];
            }

            $orders_data['orders'][$order_index]['status_history'][] = [
                'status' => $new_order_status,
                'date' => date('Y-m-d H:i:s'),
                'user' => $_SESSION['username'] ?? 'admin',
                'payment_status' => $payment_status,
                'note' => 'Reprocesado manualmente desde admin debido a fallo en webhook'
            ];

            // Handle stock
            if ($payment_status === 'approved' && !($order['stock_reduced'] ?? false)) {
                foreach ($order['items'] as $item) {
                    reduce_product_stock($item['product_id'], $item['quantity']);
                }
                $orders_data['orders'][$order_index]['stock_reduced'] = true;
                $reprocess_result['actions_taken'][] = 'Stock reducido';
            } elseif ($restore_stock && ($order['stock_reduced'] ?? false)) {
                foreach ($order['items'] as $item) {
                    restore_product_stock($item['product_id'], $item['quantity']);
                }
                $orders_data['orders'][$order_index]['stock_reduced'] = false;
                $reprocess_result['actions_taken'][] = 'Stock restaurado';
            }

            // Save orders
            $save_success = write_json($orders_file, $orders_data);

            if (!$save_success) {
                throw new Exception('Error al guardar la orden en la base de datos');
            }

            $reprocess_result['actions_taken'][] = 'Orden actualizada en base de datos';

            log_order_update($order_id, $old_status, $new_order_status, $payment_status);

            // Verify the order was saved correctly
            $verify_orders = read_json($orders_file);
            $verify_found = false;
            foreach ($verify_orders['orders'] as $vo) {
                if ($vo['id'] === $order_id && $vo['status'] === $new_order_status) {
                    $verify_found = true;
                    break;
                }
            }

            if (!$verify_found) {
                throw new Exception('La orden no se guardó correctamente - verificación falló');
            }

            $reprocess_result['actions_taken'][] = 'Verificación de guardado: OK';

            // Send notifications
            $updated_order = $orders_data['orders'][$order_index];

            if ($new_order_status === 'cobrada') {
                // Send to customer based on preference
                $customer_notif_sent = false;
                try {
                    if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                        $customer_notif_sent = send_telegram_payment_approved_to_customer($updated_order);
                        log_notification_sent('TELEGRAM_PAYMENT_APPROVED_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
                        $reprocess_result['notifications_sent'][] = 'Telegram al cliente: ' . ($customer_notif_sent ? '✅' : '❌');
                    } else {
                        $customer_notif_sent = queue_email('payment_approved', ['order' => $updated_order], 'high');
                        log_notification_sent('EMAIL_PAYMENT_APPROVED', $updated_order['customer_email'], $customer_notif_sent, $order_id);
                        $reprocess_result['notifications_sent'][] = 'Email al cliente (' . $updated_order['customer_email'] . '): ' . ($customer_notif_sent ? '✅ (en cola)' : '❌');
                    }
                } catch (Exception $e) {
                    $reprocess_result['notifications_sent'][] = 'Email al cliente: ❌ Error: ' . $e->getMessage();
                    log_mp_error('REPROCESS_NOTIFICATION', 'Error enviando email al cliente', [
                        'error' => $e->getMessage(),
                        'order_id' => $order_id
                    ]);
                }

                // Always send to admin via Telegram
                try {
                    $telegram_sent = send_telegram_payment_approved($updated_order);
                    log_notification_sent('TELEGRAM_PAYMENT_APPROVED', 'admin', $telegram_sent, $order_id);
                    $reprocess_result['notifications_sent'][] = 'Telegram al admin: ' . ($telegram_sent ? '✅' : '❌');
                } catch (Exception $e) {
                    $reprocess_result['notifications_sent'][] = 'Telegram al admin: ❌ Error: ' . $e->getMessage();
                    log_mp_error('REPROCESS_NOTIFICATION', 'Error enviando Telegram al admin', [
                        'error' => $e->getMessage(),
                        'order_id' => $order_id
                    ]);
                }

                // Queue admin email
                try {
                    $admin_email_sent = queue_email('admin_new_order', ['order' => $updated_order], 'high');
                    log_notification_sent('EMAIL_ADMIN_NEW_ORDER', 'admin', $admin_email_sent, $order_id);
                    $reprocess_result['notifications_sent'][] = 'Email al admin: ' . ($admin_email_sent ? '✅ (en cola)' : '❌');
                } catch (Exception $e) {
                    $reprocess_result['notifications_sent'][] = 'Email al admin: ❌ Error: ' . $e->getMessage();
                    log_mp_error('REPROCESS_NOTIFICATION', 'Error enviando email al admin', [
                        'error' => $e->getMessage(),
                        'order_id' => $order_id
                    ]);
                }

                log_mp_debug('PAYMENT_APPROVED', "Pago aprobado (reproceso manual desde admin) - Orden: $order_id", [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'amount' => $payment['transaction_amount'] ?? 0,
                    'fees' => $total_fees,
                    'net_amount' => $net_received_amount,
                    'customer_notif_sent' => $customer_notif_sent,
                    'telegram_sent' => $telegram_sent,
                    'admin_user' => $_SESSION['username'] ?? 'unknown'
                ]);
            } elseif ($new_order_status === 'pendiente') {
                // Send to customer based on preference
                $customer_notif_sent = false;
                if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                    $customer_notif_sent = send_telegram_payment_pending_to_customer($updated_order);
                    log_notification_sent('TELEGRAM_PAYMENT_PENDING_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
                    $reprocess_result['notifications_sent'][] = 'Telegram al cliente: ' . ($customer_notif_sent ? '✅' : '❌');
                } else {
                    $customer_notif_sent = queue_email('payment_pending', ['order' => $updated_order], 'normal');
                    log_notification_sent('EMAIL_PAYMENT_PENDING', $updated_order['customer_email'], $customer_notif_sent, $order_id);
                    $reprocess_result['notifications_sent'][] = 'Email al cliente: ' . ($customer_notif_sent ? '✅ (en cola)' : '❌');
                }
            } elseif ($new_order_status === 'rechazada') {
                // Send to customer based on preference
                $customer_notif_sent = false;
                if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                    $customer_notif_sent = send_telegram_payment_rejected_to_customer($updated_order, $status_detail);
                    log_notification_sent('TELEGRAM_PAYMENT_REJECTED_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
                    $reprocess_result['notifications_sent'][] = 'Telegram al cliente: ' . ($customer_notif_sent ? '✅' : '❌');
                } else {
                    $customer_notif_sent = queue_email('payment_rejected', ['order' => $updated_order, 'status_detail' => $status_detail], 'normal');
                    log_notification_sent('EMAIL_PAYMENT_REJECTED', $updated_order['customer_email'], $customer_notif_sent, $order_id);
                    $reprocess_result['notifications_sent'][] = 'Email al cliente: ' . ($customer_notif_sent ? '✅ (en cola)' : '❌');
                }

                // Always send to admin via Telegram
                $telegram_sent = send_telegram_payment_rejected($updated_order);
                log_notification_sent('TELEGRAM_PAYMENT_REJECTED', 'admin', $telegram_sent, $order_id);
                $reprocess_result['notifications_sent'][] = 'Telegram al admin: ' . ($telegram_sent ? '✅' : '❌');
            }

            $message = '✅ Pago reprocesado exitosamente';
            log_admin_action('payment_reprocessed', $_SESSION['username'], [
                'payment_id' => $payment_id,
                'order_id' => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_order_status
            ]);
        } else {
            $reprocess_result['actions_taken'][] = 'Ninguna (el pago ya estaba en el estado correcto)';
            $message = 'ℹ️ El pago ya está en el estado correcto: ' . $old_status;

            log_mp_debug('MANUAL_REPROCESS', 'Pago ya procesado, no se requiere acción', [
                'payment_id' => $payment_id,
                'order_id' => $order_id,
                'current_status' => $old_status,
                'admin_user' => $_SESSION['username'] ?? 'unknown'
            ]);
        }

        // Refresh payment details
        $payment_details = $payment;
        $order_info = $orders_data['orders'][$order_index];

    } catch (Exception $e) {
        $error = 'Error al reprocesar el pago: ' . $e->getMessage();

        log_mp_error('MANUAL_REPROCESS', 'Error al reprocesar pago desde admin', [
            'payment_id' => $payment_id ?? 'unknown',
            'error' => $e->getMessage(),
            'admin_user' => $_SESSION['username'] ?? 'unknown'
        ]);
    }
}

// Get recent orders with pending MP payments
$all_orders = get_all_orders();
$pending_mp_orders = array_filter($all_orders, function($order) {
    return $order['payment_method'] === 'mercadopago' &&
           in_array($order['status'], ['pendiente', 'rechazada']) &&
           isset($order['payment_id']);
});
usort($pending_mp_orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$pending_mp_orders = array_slice($pending_mp_orders, 0, 10); // Last 10

// Get logged user
$user = get_logged_user();

/**
 * Reduce product stock
 */
function reduce_product_stock($product_id, $quantity) {
    update_stock($product_id, -$quantity, 'Pago aprobado via reprocesamiento manual');
}

/**
 * Restore product stock
 */
function restore_product_stock($product_id, $quantity) {
    update_stock($product_id, $quantity, 'Pago cancelado/rechazado via reprocesamiento manual');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reprocesar Pago Mercadopago - Admin</title>

    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
            max-width: 1200px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .content-header h1 {
            font-size: 24px;
            color: #2c3e50;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #FF9800;
            color: white;
        }

        .btn-warning:hover {
            background: #F57C00;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .card h2 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2196F3;
            color: #0d47a1;
        }

        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #FF9800;
            color: #856404;
        }

        .payment-detail {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .payment-detail:last-child {
            border-bottom: none;
        }

        .payment-detail-label {
            font-weight: 600;
            color: #555;
        }

        .payment-detail-value {
            color: #333;
            word-break: break-word;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }

        .status-approved {
            background: #4CAF50;
        }

        .status-pending {
            background: #FFA726;
        }

        .status-rejected {
            background: #f44336;
        }

        .recent-orders {
            list-style: none;
        }

        .recent-orders li {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .recent-orders li:hover {
            background: #f8f9fa;
        }

        .recent-orders button {
            padding: 5px 10px;
            font-size: 12px;
        }

        .result-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #4CAF50;
        }

        .result-box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .result-box ul {
            list-style: none;
            padding-left: 0;
        }

        .result-box ul li {
            padding: 5px 0;
            color: #555;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>🔄 Reprocesar Pago de Mercadopago</h1>
            <a href="<?php echo url('/admin/?page=ventas'); ?>" class="btn btn-secondary">← Volver a Ventas</a>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="info-box">
            💡 <strong>¿Qué hace esta herramienta?</strong><br>
            Reprocesa pagos de Mercadopago que no fueron procesados correctamente debido a un fallo en el webhook.
            Actualiza el estado de la orden, maneja el stock y envía las notificaciones correspondientes.
        </div>

        <!-- Search Form -->
        <div class="card">
            <h2>🔍 Buscar Pago</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="payment_id">Payment ID de Mercadopago:</label>
                    <input type="text" id="payment_id" name="payment_id"
                           placeholder="Ej: 1234567890"
                           value="<?php echo htmlspecialchars($_POST['payment_id'] ?? ''); ?>"
                           required>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        El Payment ID es un número único que identifica el pago en Mercadopago
                    </small>
                </div>
                <button type="submit" name="search_payment" class="btn btn-primary">
                    🔍 Buscar Pago
                </button>
            </form>
        </div>

        <!-- Recent Pending Orders -->
        <?php if (!empty($pending_mp_orders)): ?>
        <div class="card">
            <h2>⏳ Órdenes Pendientes/Rechazadas con Mercadopago</h2>
            <ul class="recent-orders">
                <?php foreach ($pending_mp_orders as $order): ?>
                <li>
                    <span>
                        <strong>Orden #<?php echo htmlspecialchars($order['order_number']); ?></strong> -
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                        <br>
                        <small style="color: #666;">
                            Payment ID: <?php echo htmlspecialchars($order['payment_id'] ?? 'N/A'); ?> |
                            Estado: <span style="color: <?php echo $order['status'] === 'pendiente' ? '#FFA726' : '#f44336'; ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </small>
                    </span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($order['payment_id'] ?? ''); ?>">
                        <button type="submit" name="search_payment" class="btn btn-warning">Ver Detalles</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Payment Details -->
        <?php if ($payment_details && !$reprocess_result): ?>
        <div class="card">
            <h2>📊 Detalles del Pago</h2>

            <div class="payment-detail">
                <div class="payment-detail-label">Payment ID:</div>
                <div class="payment-detail-value">
                    <strong><?php echo htmlspecialchars($payment_details['id']); ?></strong>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Estado:</div>
                <div class="payment-detail-value">
                    <?php
                    $status = $payment_details['status'];
                    $status_class = $status === 'approved' ? 'status-approved' :
                                  ($status === 'pending' || $status === 'in_process' ? 'status-pending' : 'status-rejected');
                    ?>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo strtoupper($status); ?>
                    </span>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Monto:</div>
                <div class="payment-detail-value">
                    <strong><?php echo $payment_details['currency_id']; ?> $<?php echo number_format($payment_details['transaction_amount'], 2); ?></strong>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Descripción:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['description'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Referencia Externa:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['external_reference'] ?? 'N/A'); ?>
                    <?php if ($order_info): ?>
                        <br><small style="color: #4CAF50;">✓ Orden encontrada en el sistema</small>
                    <?php else: ?>
                        <br><small style="color: #f44336; font-weight: 600;">⚠️ ADVERTENCIA: No se encontró una orden asociada a este pago</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Fecha de Creación:</div>
                <div class="payment-detail-value">
                    <?php echo date('d/m/Y H:i:s', strtotime($payment_details['date_created'])); ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Método de Pago:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['payment_type_id'] ?? 'N/A'); ?> -
                    <?php echo htmlspecialchars($payment_details['payment_method_id'] ?? 'N/A'); ?>
                </div>
            </div>

            <?php if (isset($payment_details['status_detail'])): ?>
            <div class="payment-detail">
                <div class="payment-detail-label">Detalle del Estado:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['status_detail']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Order Info if found -->
        <?php if ($order_info): ?>
        <div class="card">
            <h2>🛒 Información de la Orden</h2>

            <div class="payment-detail">
                <div class="payment-detail-label">Orden #:</div>
                <div class="payment-detail-value">
                    <strong><?php echo htmlspecialchars($order_info['order_number']); ?></strong>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Cliente:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($order_info['customer_name']); ?><br>
                    <small><?php echo htmlspecialchars($order_info['customer_email']); ?></small>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Total:</div>
                <div class="payment-detail-value">
                    <?php echo $order_info['currency']; ?> $<?php echo number_format($order_info['total'], 2); ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Estado de la Orden:</div>
                <div class="payment-detail-value">
                    <strong><?php echo htmlspecialchars($order_info['status']); ?></strong>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Stock Reducido:</div>
                <div class="payment-detail-value">
                    <?php echo ($order_info['stock_reduced'] ?? false) ? '✅ Sí' : '❌ No'; ?>
                </div>
            </div>
        </div>

        <div class="warning-box">
            ⚠️ <strong>Importante:</strong> Al reprocesar este pago se actualizará el estado de la orden según el estado actual del pago en Mercadopago,
            se manejará el stock automáticamente y se enviarán las notificaciones correspondientes al cliente y al administrador.
        </div>

        <form method="POST" action="" id="reprocessForm">
            <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($payment_details['id']); ?>">
            <input type="hidden" name="reprocess_payment" value="1">
            <button type="button" class="btn btn-danger" data-action="confirmReprocess">
                🔄 Reprocesar Pago
            </button>
        </form>

        <script nonce="<?= csp_nonce() ?>">
        function confirmReprocess() {
            showModal({
                title: 'Confirmar Reprocesamiento',
                message: '¿Está seguro que desea reprocesar este pago?<br><br>Esto actualizará el estado de la orden, manejará el stock y enviará las notificaciones correspondientes.',
                icon: '⚠️',
                confirmText: 'Sí, Reprocesar',
                cancelText: 'Cancelar',
                confirmType: 'danger',
                onConfirm: function() {
                    document.getElementById('reprocessForm').submit();
                }
            });
        }

        // Wrapper for event delegation
        window.confirmReprocess = confirmReprocess;
        </script>

        <!-- Event Delegation System for CSP -->
        <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Reprocess Result -->
        <?php if ($reprocess_result): ?>
        <div class="card">
            <h2>✅ Resultado del Reprocesamiento</h2>

            <div class="result-box">
                <h3>Información General</h3>
                <ul>
                    <li><strong>Payment ID:</strong> <?php echo htmlspecialchars($reprocess_result['payment_id']); ?></li>
                    <li><strong>Order ID:</strong> <?php echo htmlspecialchars($reprocess_result['order_id']); ?></li>
                    <li><strong>Orden #:</strong> <?php echo htmlspecialchars($reprocess_result['order_number']); ?></li>
                    <li><strong>Estado anterior:</strong> <?php echo htmlspecialchars($reprocess_result['old_status']); ?></li>
                    <li><strong>Estado nuevo:</strong> <strong style="color: #4CAF50;"><?php echo htmlspecialchars($reprocess_result['new_status']); ?></strong></li>
                    <li><strong>Estado de pago MP:</strong> <?php echo htmlspecialchars($reprocess_result['payment_status']); ?></li>
                    <li><strong>Monto:</strong> <?php echo $reprocess_result['currency']; ?> $<?php echo number_format($reprocess_result['amount'], 2); ?></li>
                    <?php if (isset($reprocess_result['total_fees'])): ?>
                    <li><strong>Comisiones:</strong> $<?php echo number_format($reprocess_result['total_fees'], 2); ?></li>
                    <li><strong>Neto acreditado:</strong> $<?php echo number_format($reprocess_result['net_amount'], 2); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if (!empty($reprocess_result['actions_taken'])): ?>
            <div class="result-box">
                <h3>Acciones Tomadas</h3>
                <ul>
                    <?php foreach ($reprocess_result['actions_taken'] as $action): ?>
                    <li>✓ <?php echo htmlspecialchars($action); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($reprocess_result['notifications_sent'])): ?>
            <div class="result-box">
                <h3>Notificaciones Enviadas</h3>
                <ul>
                    <?php foreach ($reprocess_result['notifications_sent'] as $notification): ?>
                    <li><?php echo $notification; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div style="margin-top: 20px;">
                <a href="<?php echo url('/admin/?page=reprocesar-pago-mp'); ?>" class="btn btn-primary">
                    ← Reprocesar Otro Pago
                </a>
                <a href="<?php echo url('/admin/?page=ventas'); ?>" class="btn btn-secondary">
                    Ir a Ventas
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
