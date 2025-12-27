<?php
/**
 * Thank You Page - Order Confirmation
 */

// Define security constant to prevent direct file access


// Set security headers

// Start session

// Get order info from URL
$order_id = $_GET['order'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($order_id) || empty($token)) {
    header('Location: ' . url('/'));
    exit;
}

// Get order by token
$order = get_order_by_token($token);

if (!$order || $order['id'] !== $order_id) {
    header('Location: ' . url('/'));
    exit;
}

// Clear cart and coupon for successful orders
// This handles Mercadopago payments where cart wasn't cleared at checkout
if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}
if (isset($_SESSION['coupon_code'])) {
    unset($_SESSION['coupon_code']);
}
if (isset($_SESSION['applied_coupon'])) {
    unset($_SESSION['applied_coupon']);
}

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Get payment status from URL if provided
$payment_status = $_GET['payment_status'] ?? null;
$payment_status_detail = $_GET['payment_status_detail'] ?? '';
$payment_message = null;

// Get specific message for non-approved payments
if ($payment_status && $payment_status !== 'approved') {
    $payment_message = get_payment_message($payment_status, $payment_status_detail);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Gracias por tu compra! - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <style nonce="<?= csp_nonce() ?>">
        /* Info Box - Payment suggestions */
        .info-box {
            background: var(--color-warning-bg, #fff3cd);
            border-left: 4px solid var(--color-warning, #ffc107);
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            border-radius: 8px;
        }

        .info-box h3 {
            color: var(--color-warning-text, #856404);
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
            color: var(--color-text-secondary, #555);
        }

        .info-box li {
            margin: 5px 0;
        }

        /* Items list heading */
        .items-list h3 {
            margin-bottom: 15px;
            color: var(--color-text, #2c3e50);
        }

        /* Shipping info */
        .shipping-info {
            margin-top: 10px;
        }

        /* Tracking info */
        .tracking-info {
            margin-top: 30px;
            color: var(--color-text-lighter, #999);
            font-size: 14px;
        }

        .tracking-link {
            color: var(--color-primary, #667eea);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="<?php echo url('/'); ?>" class="logo"><?php render_site_logo($site_config); ?></a>
            <nav class="nav">
                <a href="<?php echo url('/'); ?>">🏠 Volver al inicio</a>
            </nav>
        </div>
    </header>

    <div class="success-container">
        <?php if ($payment_message): ?>
        <div class="success-icon"><?php echo $payment_message['icon']; ?></div>

        <h1><?php echo htmlspecialchars($payment_message['title']); ?></h1>
        <p class="subtitle"><?php echo htmlspecialchars($payment_message['message']); ?></p>

        <?php if (!empty($payment_message['suggestions'])): ?>
        <div class="info-box">
            <h3>ℹ️ Información importante:</h3>
            <ul>
                <?php foreach ($payment_message['suggestions'] as $suggestion): ?>
                <li><?php echo htmlspecialchars($suggestion); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <p class="subtitle">Detalles de tu pedido, <?php echo htmlspecialchars($order['customer_name']); ?>:</p>
        <?php else: ?>
        <div class="success-icon">✅</div>

        <h1>¡Pedido Confirmado!</h1>
        <p class="subtitle">Gracias por tu compra, <?php echo htmlspecialchars($order['customer_name']); ?></p>
        <?php endif; ?>

        <div class="order-info">
            <div class="order-row">
                <span class="order-label">Número de pedido:</span>
                <span class="order-value order-number"><?php echo htmlspecialchars($order['order_number']); ?></span>
            </div>

            <div class="order-row">
                <span class="order-label">Fecha:</span>
                <span class="order-value"><?php echo date('d/m/Y H:i', strtotime($order['date'])); ?></span>
            </div>

            <div class="order-row">
                <span class="order-label">Total:</span>
                <span class="order-value"><?php echo format_price($order['total'], $order['currency']); ?></span>
            </div>

            <div class="order-row">
                <span class="order-label">Método de pago:</span>
                <span class="order-value">
                    <?php
                    $payment_labels = [
                        'mercadopago' => '💳 Mercadopago',
                        'arrangement' => '🤝 Arreglo con el vendedor',
                        'pickup_payment' => '💵 Pago al retirar',
                        'presencial' => '💵 Pago Presencial' // Legacy
                    ];
                    echo $payment_labels[$order['payment_method']] ?? '💳 ' . htmlspecialchars($order['payment_method']);
                    ?>
                </span>
            </div>

            <div class="order-row">
                <span class="order-label">Estado:</span>
                <span class="order-value">
                    <?php
                    $status_labels = [
                        'pending' => '📦 Pendiente',
                        'confirmed' => '✅ Confirmado',
                        'shipped' => '🚚 Enviado',
                        'delivered' => '🏠 Entregado'
                    ];
                    echo $status_labels[$order['status']] ?? '📦 Pendiente';
                    ?>
                </span>
            </div>
        </div>

        <?php if (!empty($order['items'])): ?>
        <div class="items-list">
            <h3>Productos:</h3>
            <?php foreach ($order['items'] as $item): ?>
            <div class="item">
                <span class="item-name">
                    <?php echo htmlspecialchars($item['name']); ?>
                    (x<?php echo $item['quantity']; ?>)
                </span>
                <span class="item-price">
                    <?php echo format_price($item['final_price'], $order['currency']); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array($order['payment_method'], ['arrangement', 'pickup_payment', 'presencial'])): ?>
        <div class="info-box">
            <h3>ℹ️ Instrucciones para tu pedido</h3>
            <p>
                <strong>Tu pedido está confirmado.</strong><br>
                <?php if (($order['delivery_method'] ?? 'pickup') === 'shipping'): ?>
                    Recibirás tu pedido en la dirección indicada. Te contactaremos pronto para coordinar la entrega y el pago.
                <?php else: ?>
                    Puedes retirar tu pedido en nuestro local. Te contactaremos pronto para coordinar el retiro y el pago.
                <?php endif; ?>
            </p>
            <p class="shipping-info">
                <strong>Hemos enviado un email de confirmación a:</strong><br>
                <?php echo htmlspecialchars($order['customer_email']); ?>
            </p>
        </div>
        <?php else: ?>
        <div class="info-box">
            <h3>ℹ️ Próximos pasos</h3>
            <p>
                Una vez que se confirme el pago, comenzaremos a procesar tu pedido.
                Te mantendremos informado por email sobre el estado de tu compra.
            </p>
        </div>
        <?php endif; ?>

        <div class="buttons">
            <a href="<?php echo url('/pedido?order=' . urlencode($order['id']) . '&token=' . urlencode($order['tracking_token'])); ?>"
               class="btn btn-primary">
                📦 Seguir mi pedido
            </a>
            <a href="<?php echo url('/'); ?>" class="btn btn-secondary">
                🏠 Volver al inicio
            </a>
        </div>

        <p class="tracking-info">
            Guarda este enlace para hacer seguimiento de tu pedido.<br>
            También puedes rastrearlo en cualquier momento desde <a href="<?php echo url('/track'); ?>" class="tracking-link">aquí</a> usando tu email y número de pedido.
        </p>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        // Clear cart and customer data after successful purchase
        localStorage.removeItem('cart');
        localStorage.removeItem('cart_timestamp');
        localStorage.removeItem('applied_coupon');

        // Clear customer contact info and validation data
        sessionStorage.removeItem('checkout_contact_info');
        sessionStorage.removeItem('telegramValidated');
        sessionStorage.removeItem('emailValidated');

        console.log('Cart and customer data cleared');
    </script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
