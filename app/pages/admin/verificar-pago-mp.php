<?php
/**
 * Verificar Pago de Mercadopago
 * Herramienta para consultar un pago en Mercadopago y ver todos sus detalles
 */


require_once APP_PATH . '/includes/mercadopago.php';


// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$payment_config = read_json(APP_PATH . '/config/payment.json');
$payment_credentials = get_payment_credentials();

$payment_details = null;
$error = '';
$order_info = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_payment'])) {
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
                if (isset($payment_details['external_reference'])) {
                    $order_info = get_order_by_id($payment_details['external_reference']);
                }
            }
        } catch (Exception $e) {
            $error = 'Error al consultar el pago: ' . $e->getMessage();
        }
    }
}

// Get recent orders with MP payments
$all_orders = get_all_orders();
$mp_orders = array_filter($all_orders, function($order) {
    return $order['payment_method'] === 'mercadopago' && isset($order['payment_id']);
});
usort($mp_orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$mp_orders = array_slice($mp_orders, 0, 10); // Last 10

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Pago Mercadopago - Admin</title>

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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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

        .json-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
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
            <h1>🔍 Verificar Pago de Mercadopago</h1>
            <a href="<?php echo url('/admin/?page=ventas'); ?>" class="btn btn-secondary">← Volver a Ventas</a>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="info-box">
            💡 <strong>Tip:</strong> Ingresa el Payment ID de Mercadopago para ver todos los detalles del pago.
            El Payment ID aparece en tus órdenes o en las notificaciones del webhook.
        </div>

        <!-- Search Form -->
        <div class="card">
            <h2>Consultar Pago</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="payment_id">Payment ID de Mercadopago:</label>
                    <input type="text" id="payment_id" name="payment_id"
                           placeholder="Ej: 1234567890"
                           value="<?php echo htmlspecialchars($_POST['payment_id'] ?? ''); ?>">
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        El Payment ID es un número único que identifica el pago en Mercadopago
                    </small>
                </div>
                <button type="submit" name="check_payment" class="btn btn-primary">
                    🔍 Buscar Pago
                </button>
            </form>
        </div>

        <!-- Recent MP Orders -->
        <?php if (!empty($mp_orders)): ?>
        <div class="card">
            <h2>Últimas Órdenes con Mercadopago</h2>
            <ul class="recent-orders">
                <?php foreach ($mp_orders as $order): ?>
                <li>
                    <span>
                        <strong>Orden #<?php echo htmlspecialchars($order['order_number']); ?></strong> -
                        <?php echo htmlspecialchars($order['customer_name']); ?>
                        <br>
                        <small style="color: #666;">
                            Payment ID: <?php echo htmlspecialchars($order['payment_id'] ?? 'N/A'); ?>
                        </small>
                    </span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($order['payment_id'] ?? ''); ?>">
                        <button type="submit" name="check_payment" class="btn btn-primary">Ver Detalles</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Payment Details -->
        <?php if ($payment_details): ?>
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
                <div class="payment-detail-label">Fecha de Aprobación:</div>
                <div class="payment-detail-value">
                    <?php
                    echo isset($payment_details['date_approved']) ?
                        date('d/m/Y H:i:s', strtotime($payment_details['date_approved'])) :
                        'N/A';
                    ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Email del Pagador:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['payer']['email'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Método de Pago:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['payment_type_id'] ?? 'N/A'); ?> -
                    <?php echo htmlspecialchars($payment_details['payment_method_id'] ?? 'N/A'); ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Installments:</div>
                <div class="payment-detail-value">
                    <?php echo htmlspecialchars($payment_details['installments'] ?? '1'); ?> cuota(s)
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
                    <?php echo htmlspecialchars($order_info['status']); ?>
                </div>
            </div>

            <div class="payment-detail">
                <div class="payment-detail-label">Stock Reducido:</div>
                <div class="payment-detail-value">
                    <?php echo ($order_info['stock_reduced'] ?? false) ? '✅ Sí' : '❌ No'; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Raw JSON -->
        <div class="card">
            <h2>📄 JSON Completo (Raw)</h2>
            <div class="json-viewer">
                <pre><?php echo json_encode($payment_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
