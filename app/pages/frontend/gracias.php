<?php
/**
 * Thank You Page - Order Confirmation
 * Unificada para manejar tanto órdenes pagadas como impagadas
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Get order info from URL
$order_id = $_GET['order'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($order_id) || empty($token)) {
    redirect(url('/'));
    exit;
}

// Get order by token
$order = get_order_by_token($token);

if (!$order || $order['id'] !== $order_id) {
    redirect(url('/'));
    exit;
}

// Determinar el estado del pago
$order_status = $order['status'] ?? 'impago';
$is_paid = in_array($order_status, ['pagado', 'cobrada', 'entregada', 'lista_retiro']);
$is_pending = in_array($order_status, ['impago', 'pendiente', 'pending']);

// Clear cart and coupon
if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
}
if (isset($_SESSION['coupon_code'])) {
    unset($_SESSION['coupon_code']);
}
if (isset($_SESSION['applied_coupon'])) {
    unset($_SESSION['applied_coupon']);
}

// Clear checkout timer and session data
if (isset($_SESSION['checkout_start_time'])) {
    unset($_SESSION['checkout_start_time']);
}
if (isset($_SESSION['checkout_id'])) {
    unset($_SESSION['checkout_id']);
}

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');
$payment_config = read_json(APP_PATH . '/config/payment.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Get MercadoPago public key for retry payment (solo si está pendiente)
$mp_public_key = '';
if ($is_pending) {
    $payment_credentials = get_payment_credentials();
    $mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
    $mp_public_key = $mp_mode === 'sandbox' ?
        ($payment_credentials['mercadopago']['public_key_sandbox'] ?? '') :
        ($payment_credentials['mercadopago']['public_key_prod'] ?? '');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_paid ? '¡Gracias por tu compra!' : 'Pedido Creado'; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <style nonce="<?= csp_nonce() ?>">
        .success-container {
            max-width: 600px;
            margin: 4rem auto;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-text, #333);
            margin-bottom: 1rem;
        }

        .subtitle {
            font-size: 1.1rem;
            color: var(--color-text-secondary, #666);
            margin-bottom: 2rem;
        }

        .order-info {
            background: var(--color-bg-light, #f8f9fa);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--color-border, #e9ecef);
        }

        .order-row:last-child {
            border-bottom: none;
        }

        .order-label {
            font-weight: 600;
            color: var(--color-text-secondary, #495057);
        }

        .order-value {
            color: var(--color-text, #212529);
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--color-primary, #667eea);
        }

        .items-list {
            margin: 2rem 0;
        }

        .items-list h3 {
            margin-bottom: 15px;
            color: var(--color-text, #2c3e50);
        }

        .item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--color-border, #e9ecef);
        }

        .item-name {
            color: var(--color-text, #333);
        }

        .item-price {
            font-weight: 600;
            color: var(--color-primary, #667eea);
        }

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

        .buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--color-success, #48bb78);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-success-dark, #38a169);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .btn-secondary {
            background: var(--color-bg-light, #f7fafc);
            color: var(--color-text-secondary, #4a5568);
            border: 2px solid var(--color-border, #e2e8f0);
        }

        .btn-secondary:hover {
            background: var(--color-bg-lighter, #edf2f7);
            border-color: var(--color-border-dark, #cbd5e0);
        }

        @media (max-width: 600px) {
            .success-container {
                margin: 2rem 1rem;
                padding: 2rem 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .success-icon {
                font-size: 3rem;
            }
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
        <?php if ($is_paid): ?>
            <!-- Orden pagada exitosamente -->
            <div class="success-icon">✅</div>
            <h1>¡Pedido Confirmado!</h1>
            <p class="subtitle">Gracias por tu compra, <?php echo htmlspecialchars($order['customer_name']); ?></p>
        <?php else: ?>
            <!-- Orden pendiente de pago -->
            <div class="success-icon">⏳</div>
            <h1>Pedido Creado</h1>
            <p class="subtitle">Tu pedido fue creado exitosamente, pero el pago aún no se ha completado.</p>
        <?php endif; ?>

        <div class="order-info">
            <div class="order-row">
                <span class="order-label">Número de pedido:</span>
                <span class="order-value order-number">#<?php echo htmlspecialchars($order['order_number']); ?></span>
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
                        'presencial' => '💵 Pago Presencial'
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
                        'impago' => '⏳ Pendiente de pago',
                        'pendiente' => '⏳ Pendiente de pago',
                        'pagado' => '✅ Pagado',
                        'cobrada' => '✅ Pagado',
                        'lista_retiro' => '📦 Listo para retiro',
                        'entregada' => '🏠 Entregado'
                    ];
                    echo $status_labels[$order_status] ?? $order_status;
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

        <?php if ($is_pending): ?>
        <!-- Orden pendiente - Mostrar advertencia y opciones -->
        <div class="info-box">
            <h3>⚠️ Importante: Stock no reservado</h3>
            <p>Tu pedido fue creado pero <strong>el pago no se completó</strong>. Los productos <strong>no quedan reservados</strong> hasta que se confirme el pago.</p>
            <ul style="margin-top: 10px;">
                <li>Los productos pueden agotarse antes de que pagues</li>
                <li>No garantizamos disponibilidad hasta recibir el pago</li>
                <li>Te contactaremos para coordinar el pago</li>
            </ul>
        </div>

        <div class="buttons">
            <?php if (!empty($mp_public_key)): ?>
            <button data-action="retryPayment" class="btn btn-primary">
                💳 Pagar con MercadoPago
            </button>
            <?php endif; ?>

            <a href="<?php echo url('/'); ?>" class="btn btn-secondary">
                🏠 Volver a la Tienda
            </a>
        </div>

        <?php else: ?>
        <!-- Orden pagada - Mostrar solo volver a la tienda -->
        <div class="info-box">
            <h3>ℹ️ Próximos pasos</h3>
            <p>
                <?php if (($order['delivery_method'] ?? 'pickup') === 'shipping'): ?>
                    Recibirás tu pedido en la dirección indicada. Te contactaremos pronto para coordinar la entrega.
                <?php else: ?>
                    Puedes retirar tu pedido en nuestro local. Te contactaremos pronto para coordinar el retiro.
                <?php endif; ?>
            </p>
            <p style="margin-top: 10px;">
                <strong>Hemos enviado un email de confirmación a:</strong><br>
                <?php echo htmlspecialchars($order['customer_email']); ?>
            </p>
        </div>

        <div class="buttons">
            <a href="<?php echo url('/'); ?>" class="btn btn-primary">
                🏠 Volver a la Tienda
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($is_pending && !empty($mp_public_key)): ?>
    <!-- MercadoPago Payment Modal -->
    <div id="mercadopago-modal" class="mp-modal mp-modal-scrollable">
        <div class="mp-modal-content mp-modal-content-small">
            <button data-action="closeMercadopagoModal" class="mp-close-btn">✕</button>
            <div class="mb-md">
                <h2 class="mp-title mp-title-small">💳 Pagar con Mercadopago</h2>
                <p class="mp-order-info mp-order-info-small">Pedido #<?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>
            <div class="mp-summary mp-summary-compact">
                <div class="mp-summary-total mp-summary-total-simple">
                    <span>Total a pagar</span>
                    <span><?php echo format_price($order['total'], $order['currency']); ?></span>
                </div>
            </div>
            <div id="mp-loading" class="mp-loading mp-loading-compact">
                <div class="mp-loading-flex mp-loading-flex-small">
                    <div class="spinner spinner-small"></div>
                    <p class="mp-loading-text mp-loading-text-small">Cargando pasarela de pago...</p>
                </div>
            </div>
            <div id="walletBrick_container"></div>
        </div>
    </div>

    <style nonce="<?= csp_nonce() ?>">
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- MercadoPago SDK -->
    <script nonce="<?= csp_nonce() ?>" src="https://sdk.mercadopago.com/js/v2"></script>

    <script nonce="<?= csp_nonce() ?>">
        const mp = new MercadoPago('<?php echo $mp_public_key; ?>', {
            locale: 'es-AR'
        });

        function retryPayment() {
            // Show modal
            document.getElementById('mercadopago-modal').classList.add('active');

            // Create new preference for this order
            fetch('<?php echo url('/api/?endpoint=crear-preferencia-mp'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: '<?php echo $order['id']; ?>',
                    tracking_token: '<?php echo $token; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.preference_id) {
                    // Initialize Wallet Brick
                    const bricksBuilder = mp.bricks();

                    const renderWalletBrick = async () => {
                        const settings = {
                            initialization: {
                                preferenceId: data.preference_id,
                            },
                            customization: {
                                texts: {
                                    valueProp: 'security_safety',
                                },
                            },
                            callbacks: {
                                onReady: () => {
                                    document.getElementById('mp-loading').classList.add('hidden');
                                },
                                onSubmit: () => {
                                    return new Promise((resolve, reject) => {
                                        resolve();
                                    });
                                },
                                onError: (error) => {
                                    console.error('Error en Wallet Brick:', error);
                                    document.getElementById('mp-loading').classList.add('hidden');
                                    alert('Error al cargar el pago. Por favor intenta nuevamente.');
                                }
                            }
                        };

                        await bricksBuilder.create('wallet', 'walletBrick_container', settings);
                    };

                    renderWalletBrick();
                } else {
                    alert('Error al crear la preferencia de pago. Por favor intenta nuevamente.');
                    closeMercadopagoModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar el pago. Por favor intenta nuevamente más tarde.');
                closeMercadopagoModal();
            });
        }

        function closeMercadopagoModal() {
            document.getElementById('mercadopago-modal').classList.remove('active');
            // Reload page to check payment status
            location.reload();
        }

        // Wrappers for event delegation
        // Save original functions before overwriting
        const _retryPayment = retryPayment;
        window.retryPayment = function(event, element, params) {
            return _retryPayment();
        };

        const _closeMercadopagoModal = closeMercadopagoModal;
        window.closeMercadopagoModal = function(event, element, params) {
            return _closeMercadopagoModal();
        };
    </script>

    <!-- Event Delegation System -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
    <?php endif; ?>

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
