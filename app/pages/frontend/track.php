<?php
/**
 * Track Order - Search Page
 * Permite buscar pedidos por email y número de orden
 */

// Set security headers

// Start session

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Get all products for cart panel
$all_products = get_all_products(true); // Only active products

$error = null;
$success = null;
$found_order = null;
$show_link = false;

// Check if token is provided in URL
$token_param = $_GET['token'] ?? '';
if (!empty($token_param)) {
    // Get order by token
    $order = get_order_by_token($token_param);

    if ($order) {
        // Redirect to proper pedido page with order ID and token
        redirect(url("/pedido?order={$order['id']}&token={$token_param}"));
        exit;
    } else {
        $error = 'Token de seguimiento inválido';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $order_number = trim($_POST['order_number'] ?? '');

    if (empty($email) || empty($order_number)) {
        $error = 'Por favor completa todos los campos';
    } else {
        // Search for order
        $orders = get_all_orders();

        foreach ($orders as $order) {
            if (strtolower($order['customer_email']) === strtolower($email) &&
                $order['order_number'] === $order_number) {
                $found_order = $order;
                $show_link = true;
                break;
            }
        }

        if (!$found_order) {
            $error = 'No se encontró ningún pedido con esos datos';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastrear Pedido - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <?php if ($show_link && $found_order): ?>
        <!-- Card de link permanente -->
        <div class="tracking-link-card">
            <h2>✅ Pedido Encontrado</h2>
            <p>Guarda este link para consultar tu pedido en cualquier momento sin necesidad de volver a ingresar tus datos:</p>
            <div class="link-display">
                <input type="text" value="<?= url("/pedido?order={$found_order['id']}&token={$found_order['tracking_token']}") ?>" readonly id="tracking-link-input">
                <button data-action="copyLink">Copiar</button>
            </div>
            <p class="redirect-message">Redirigiendo a tu pedido en <span id="countdown">3</span> segundos...</p>
        </div>

        <script nonce="<?= csp_nonce() ?>">
            // Countdown y redirección
            let seconds = 3;
            const countdownEl = document.getElementById('countdown');
            const trackingUrl = <?= json_encode(url("/pedido?order={$found_order['id']}&token={$found_order['tracking_token']}")) ?>;

            const interval = setInterval(() => {
                seconds--;
                countdownEl.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(interval);
                    window.location.href = trackingUrl;
                }
            }, 1000);
        </script>
    <?php else: ?>
        <!-- Formulario de búsqueda -->
        <div class="track-form-container">
            <h1>📦 Rastrear mi Pedido</h1>
            <p>Ingresa tus datos para ver el estado de tu compra</p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-top: var(--spacing-lg, 28px); padding: var(--spacing-md, 20px); background: var(--color-error-bg, #fee); border: 1px solid var(--color-error, #ef4444); border-radius: var(--border-radius-md, 6px); color: var(--color-error-dark, #dc2626);">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" style="margin-top: var(--spacing-lg, 28px);">
                <div class="track-form-group">
                    <label for="email">📧 Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="tu@email.com"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                    <div style="margin-top: var(--spacing-xs, 6px); color: var(--color-text-light, #666); font-size: var(--font-size-sm, 14px);">El email que usaste al realizar la compra</div>
                </div>

                <div class="track-form-group">
                    <label for="order_number">🔢 Número de Pedido</label>
                    <input
                        type="text"
                        id="order_number"
                        name="order_number"
                        placeholder="ORD-2025-00001"
                        required
                        value="<?php echo htmlspecialchars($_POST['order_number'] ?? ''); ?>"
                    >
                    <div style="margin-top: var(--spacing-xs, 6px); color: var(--color-text-light, #666); font-size: var(--font-size-sm, 14px);">Lo encontrarás en el email de confirmación</div>
                </div>

                <button type="submit" class="track-form-submit">
                    🔍 Buscar Pedido
                </button>
            </form>

            <div style="margin: var(--spacing-xl, 36px) 0; text-align: center; color: var(--color-text-light, #666); position: relative;">
                <span style="background: var(--color-bg, white); padding: 0 var(--spacing-sm, 12px); position: relative; z-index: 1;">o</span>
                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: var(--color-border, #e0e0e0); z-index: 0;"></div>
            </div>

            <div style="text-align: center;">
                <a href="<?php echo url('/'); ?>" style="display: inline-block; padding: var(--spacing-sm, 12px) var(--spacing-lg, 28px); color: var(--color-primary, #667eea); text-decoration: none; border: 1px solid var(--color-border, #e0e0e0); border-radius: var(--border-radius-md, 6px); transition: var(--transition-base, 0.3s ease);">
                    🏠 Volver al inicio
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cart Panel Component -->
    <?php
    require_once APP_PATH . '/includes/frontend/cart-panel.php';
    render_cart_panel();
    ?>

    <!-- Favorites Panel Component -->
    <?php
    require_once APP_PATH . '/includes/frontend/favorites-panel.php';
    render_favorites_panel();
    ?>

    <!-- Shared JavaScript Modules -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>

    <script nonce="<?= csp_nonce() ?>">
        // Products data for cart panel (global for shared modules)
        window.productUrlBase = '<?php echo url('/producto/'); ?>';
        window.products = <?php
            // Deep clone products to avoid reference issues
            $products_for_js = json_decode(json_encode($all_products), true);

            // Apply url() to cloned products for JavaScript usage
            foreach ($products_for_js as &$p) {
                if (isset($p['thumbnail'])) {
                    $p['thumbnail'] = url($p['thumbnail']);
                }
                if (isset($p['images']) && is_array($p['images'])) {
                    foreach ($p['images'] as &$img) {
                        if (is_array($img) && isset($img['url'])) {
                            $img['url'] = url($img['url']);
                        } elseif (is_string($img)) {
                            $img = url($img);
                        }
                    }
                    unset($img); // Break reference
                }
            }
            unset($p); // Break reference
            echo json_encode($products_for_js);
        ?>;

        // Update cart count from localStorage
        ShopCart.updateCartBadge();
        ShopFavorites.updateFavoritesCount();

        // Navigation functions
        function goToCheckout() {
            window.location.href = '<?php echo url('/carrito'); ?>';
        }

        function goToFavoritesPage() {
            window.location.href = '<?php echo url('/favoritos'); ?>';
        }

        // Export for event delegation compatibility
        window.goToCheckout = function(event, element, params) {
            return goToCheckout();
        };

        window.goToFavoritesPage = function(event, element, params) {
            return goToFavoritesPage();
        };

        // Función para copiar link
        function copyLink(event, element) {
            const input = document.getElementById('tracking-link-input');
            if (input) {
                input.select();
                input.setSelectionRange(0, 99999); // Para móviles

                // Método moderno
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(() => {
                        showCopyFeedback(element);
                    }).catch(() => {
                        // Fallback
                        document.execCommand('copy');
                        showCopyFeedback(element);
                    });
                } else {
                    // Fallback para navegadores antiguos
                    document.execCommand('copy');
                    showCopyFeedback(element);
                }
            }
        }

        function showCopyFeedback(button) {
            const originalText = button.textContent;
            const originalBg = button.style.background;

            button.textContent = '✅ Copiado!';
            button.style.background = 'var(--color-success, #22c55e)';

            setTimeout(() => {
                button.textContent = originalText;
                button.style.background = originalBg;
            }, 2000);
        }

        window.copyLink = copyLink;
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>

    <!-- Mobile Menu -->
    <?php include APP_PATH . '/includes/mobile-menu.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
