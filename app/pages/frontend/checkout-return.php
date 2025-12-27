<?php
/**
 * Checkout Return Handler
 * Maneja el retorno desde MercadoPago y muestra UI apropiada según el estado del pago
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Obtener el token de tracking
$tracking_token = sanitize_input($_GET['token'] ?? '');
$status = sanitize_input($_GET['status'] ?? ''); // approved, pending, failure

if (empty($tracking_token)) {
    redirect(url('/'));
    exit;
}

// Buscar la orden
$order = get_order_by_token($tracking_token);

if (!$order) {
    redirect(url('/'));
    exit;
}

// Determinar el estado del pago (usar el status de la orden)
$payment_status = $order['status'] ?? 'pendiente';

// Si el pago ya fue aprobado, redirigir a la página de tracking normal
if ($payment_status === 'cobrada' || $payment_status === 'approved' || $payment_status === 'entregada') {
    redirect(url('/track?token=' . $tracking_token));
    exit;
}

// Si llegamos aquí, el pago no está completado
// Mostrar página con modal informativo

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$payment_config = read_json(APP_PATH . '/config/payment.json');
$active_theme = $theme_config['active_theme'] ?? 'classic';

// Get MercadoPago public key for retry payment
$payment_credentials = get_payment_credentials();
$mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
$mp_public_key = $mp_mode === 'sandbox' ?
    ($payment_credentials['mercadopago']['public_key_sandbox'] ?? '') :
    ($payment_credentials['mercadopago']['public_key_prod'] ?? '');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del Pedido - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <style nonce="<?= csp_nonce() ?>">
        .return-container {
            max-width: 600px;
            margin: 4rem auto;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
        }

        .return-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .return-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-text, #333);
            margin-bottom: 1rem;
        }

        .return-message {
            font-size: 1.1rem;
            color: var(--color-text-secondary, #666);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .return-order-info {
            background: var(--color-bg-light, #f8f9fa);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .return-order-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--color-border, #e9ecef);
        }

        .return-order-row:last-child {
            border-bottom: none;
        }

        .return-order-label {
            font-weight: 600;
            color: var(--color-text-secondary, #495057);
        }

        .return-order-value {
            color: var(--color-text, #212529);
        }

        .return-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .return-btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .return-btn-primary {
            background: var(--color-primary, #667eea);
            color: white;
        }

        .return-btn-primary:hover {
            background: var(--color-primary-dark, #5568d3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .return-btn-secondary {
            background: var(--color-success, #48bb78);
            color: white;
        }

        .return-btn-secondary:hover {
            background: var(--color-success-dark, #38a169);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .return-btn-tertiary {
            background: var(--color-bg-light, #f7fafc);
            color: var(--color-text-secondary, #4a5568);
            border: 2px solid var(--color-border, #e2e8f0);
        }

        .return-btn-tertiary:hover {
            background: var(--color-bg-lighter, #edf2f7);
            border-color: var(--color-border-dark, #cbd5e0);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .return-container {
            animation: fadeIn 0.5s ease;
        }

        @media (max-width: 600px) {
            .return-container {
                margin: 2rem 1rem;
                padding: 2rem 1.5rem;
            }

            .return-title {
                font-size: 1.5rem;
            }

            .return-icon {
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

    <div class="return-container">
        <?php if ($status === 'failure'): ?>
            <!-- Payment Failed -->
            <div class="return-icon">❌</div>
            <h1 class="return-title">Pago No Procesado</h1>
            <p class="return-message">
                El pago no pudo ser procesado. Tu pedido fue creado pero está pendiente de pago.
            </p>
        <?php else: ?>
            <!-- Payment Pending/Cancelled by user -->
            <div class="return-icon">⏳</div>
            <h1 class="return-title">Pago Pendiente</h1>
            <p class="return-message">
                Tu pedido fue creado exitosamente pero el pago aún no se ha completado.
            </p>
        <?php endif; ?>

        <div class="return-order-info">
            <div class="return-order-row">
                <span class="return-order-label">Número de pedido:</span>
                <span class="return-order-value">#<?php echo htmlspecialchars($order['order_number']); ?></span>
            </div>
            <div class="return-order-row">
                <span class="return-order-label">Total:</span>
                <span class="return-order-value"><?php echo format_price($order['total'], $order['currency']); ?></span>
            </div>
            <div class="return-order-row">
                <span class="return-order-label">Estado:</span>
                <span class="return-order-value">
                    <?php
                    $status_labels = [
                        'pendiente' => '⏳ Pendiente de pago',
                        'cobrada' => '✅ Pagado',
                        'rechazada' => '❌ Rechazado'
                    ];
                    echo $status_labels[$payment_status] ?? $payment_status;
                    ?>
                </span>
            </div>
        </div>

        <div class="return-buttons">
            <a href="<?php echo url('/track?token=' . $tracking_token); ?>" class="return-btn return-btn-primary">
                📋 Ver Estado del Pedido
            </a>

            <?php if ($payment_status === 'pendiente' || $payment_status === 'pending' || $payment_status === 'rechazada' || $payment_status === 'cancelada'): ?>
            <button data-action="retryPayment" class="return-btn return-btn-secondary">
                💳 Intentar Pagar Nuevamente
            </button>
            <?php endif; ?>

            <a href="<?php echo url('/'); ?>" class="return-btn return-btn-tertiary">
                🏠 Volver a la Tienda
            </a>
        </div>
    </div>

    <?php if (($payment_status === 'pendiente' || $payment_status === 'pending' || $payment_status === 'rechazada' || $payment_status === 'cancelada') && !empty($mp_public_key)): ?>
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
                    tracking_token: '<?php echo $tracking_token; ?>'
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
                                    // Payment initiated
                                    return new Promise((resolve, reject) => {
                                        resolve();
                                    });
                                },
                                onError: (error) => {
                                    console.error('Error en Wallet Brick:', error);
                                    document.getElementById('mp-loading').classList.add('hidden');
                                    showModal({
                                        title: 'Error de Pago',
                                        message: 'Error al cargar el pago. Por favor intenta nuevamente.',
                                        icon: '❌',
                                        iconClass: 'danger',
                                        confirmText: 'Entendido',
                                        showCancel: false,
                                        confirmType: 'danger',
                                        onConfirm: function() {}
                                    });
                                }
                            }
                        };

                        await bricksBuilder.create('wallet', 'walletBrick_container', settings);
                    };

                    renderWalletBrick();
                } else {
                    showModal({
                        title: 'Error de Pago',
                        message: 'Error al crear la preferencia de pago. Por favor intenta nuevamente.',
                        icon: '❌',
                        iconClass: 'danger',
                        confirmText: 'Entendido',
                        showCancel: false,
                        confirmType: 'danger',
                        onConfirm: function() {
                            closeMercadopagoModal();
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal({
                    title: 'Error de Pago',
                    message: 'Error al procesar el pago. Por favor intenta nuevamente más tarde.',
                    icon: '❌',
                    iconClass: 'danger',
                    confirmText: 'Entendido',
                    showCancel: false,
                    confirmType: 'danger',
                    onConfirm: function() {
                        closeMercadopagoModal();
                    }
                });
            });
        }

        function closeMercadopagoModal() {
            document.getElementById('mercadopago-modal').classList.remove('active');
            // Reload page to check payment status
            location.reload();
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: retryPayment
         */
        const _retryPayment = retryPayment;
        window.retryPayment = function(event, element, params) {
            return _retryPayment();
        };

        /**
         * Wrapper: closeMercadopagoModal
         */
        const _closeMercadopagoModal = closeMercadopagoModal;
        window.closeMercadopagoModal = function(event, element, params) {
            return _closeMercadopagoModal();
        };
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>

    <!-- Auto-show stock warning modal on page load -->
    <script nonce="<?= csp_nonce() ?>">
        // Show stock warning modal automatically
        document.addEventListener('DOMContentLoaded', function() {
            showModal({
                title: '⚠️ Importante: Stock no reservado',
                message: 'Tu pedido fue creado pero <strong>el pago no se completó</strong>. Los productos <strong>no quedan reservados</strong> hasta que se confirme el pago.',
                details: `
                    <div style="text-align: left; margin-top: 15px;">
                        <p style="margin-bottom: 10px;"><strong>Esto significa que:</strong></p>
                        <ul style="margin-left: 20px; margin-bottom: 15px;">
                            <li>Los productos pueden agotarse antes de que pagues</li>
                            <li>No garantizamos disponibilidad hasta recibir el pago</li>
                            <li>Te contactaremos para coordinar el pago</li>
                        </ul>
                        <p style="margin-bottom: 10px;"><strong>¿Deseas pagar ahora?</strong></p>
                        <p style="color: #666; font-size: 14px;">Puedes intentar pagar nuevamente con MercadoPago o coordinar el pago con nosotros.</p>
                    </div>
                `,
                icon: '⚠️',
                iconClass: 'warning',
                confirmText: '💳 Pagar con MercadoPago',
                confirmType: 'primary',
                cancelText: '📋 Ver Estado del Pedido',
                onConfirm: function() {
                    // Trigger retry payment
                    const retryBtn = document.querySelector('[data-action="retryPayment"]');
                    if (retryBtn) {
                        retryBtn.click();
                    }
                },
                onCancel: function() {
                    // Do nothing - modal closes, user can see the page
                }
            });
        });
    </script>
    <?php endif; ?>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/frontend/modal.php'; ?>

    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
