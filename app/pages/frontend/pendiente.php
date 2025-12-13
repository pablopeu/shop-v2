<?php
/**
 * Payment Pending Page
 */

// Define security constant to prevent direct file access


// Set security headers

// Start session

// Get order info from URL
$order_id = $_GET['order'] ?? '';
$token = $_GET['token'] ?? '';

$order = null;
if (!empty($order_id) && !empty($token)) {
    $order = get_order_by_token($token);
    if ($order && $order['id'] !== $order_id) {
        $order = null;
    }
}

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Pendiente - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>
</head>
<body>
    <div class="pending-container">
        <div class="pending-icon">⏳</div>

        <h1>Pago Pendiente</h1>
        <p class="subtitle">
            Tu pedido ha sido registrado y está esperando la confirmación del pago.
        </p>

        <?php if ($order): ?>
        <div class="order-box">
            <h3>📋 Información del Pedido</h3>

            <div class="order-row">
                <span class="order-label">Número de pedido:</span>
                <span class="order-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
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
                <span class="order-label">Email:</span>
                <span class="order-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>ℹ️ ¿Qué significa esto?</h3>
            <p>
                Tu pago está siendo procesado por Mercadopago. Esto puede deberse a:
            </p>
            <ul>
                <li>El pago está siendo verificado por tu banco</li>
                <li>Se requiere autorización adicional</li>
                <li>El procesamiento puede tomar unos minutos</li>
            </ul>
            <p class="info-emphasis">
                <strong>Te notificaremos por email cuando se confirme el pago.</strong>
            </p>
        </div>

        <div class="info-box info-box-warning">
            <h3 class="warning-title">💳 Mientras tanto...</h3>
            <p class="warning-text">
                - No realices un nuevo pago por el mismo pedido<br>
                - Revisa tu email para actualizaciones<br>
                - Si tienes dudas, contáctanos con tu número de pedido
            </p>
        </div>

        <div class="buttons">
            <?php if ($order): ?>
            <a href="<?php echo url('/pedido?order=' . urlencode($order['id']) . '&token=' . urlencode($order['tracking_token'])); ?>"
               class="btn btn-primary">
                📦 Ver estado del pedido
            </a>
            <?php endif; ?>
            <a href="<?php echo url('/'); ?>" class="btn btn-secondary">
                🏠 Volver al inicio
            </a>
        </div>

        <p class="contact-info">
            Contacto: <?php echo htmlspecialchars($site_config['contact_email'] ?? 'contacto@tienda.com'); ?>
        </p>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        // Clear cart and customer data after order is created
        localStorage.removeItem('cart');
        localStorage.removeItem('cart_timestamp');
        localStorage.removeItem('applied_coupon');

        // Clear customer contact info and validation data
        sessionStorage.removeItem('checkout_contact_info');
        sessionStorage.removeItem('telegramValidated');
        sessionStorage.removeItem('emailValidated');

        console.log('Cart and customer data cleared');
    </script>
</body>
</html>
