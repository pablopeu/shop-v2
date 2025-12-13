<?php
/**
 * Track Order - Search Page
 * Permite buscar pedidos por email y número de orden
 */

// Define security constant to prevent direct file access


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
        $found_order = null;

        foreach ($orders as $order) {
            if (strtolower($order['customer_email']) === strtolower($email) &&
                $order['order_number'] === $order_number) {
                $found_order = $order;
                break;
            }
        }

        if ($found_order) {
            // Redirect to tracking page with token
            header("Location: " . url("/pedido?order={$found_order['id']}&token={$found_order['tracking_token']}"));
            exit;
        } else {
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

    <div class="container">
        <div class="card">
            <div class="header">
                <div class="icon">📦</div>
                <h1>Rastrear mi Pedido</h1>
                <p>Ingresa tus datos para ver el estado de tu compra</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="tu@email.com"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                    <div class="help-text">El email que usaste al realizar la compra</div>
                </div>

                <div class="form-group">
                    <label for="order_number">Número de Pedido</label>
                    <input
                        type="text"
                        id="order_number"
                        name="order_number"
                        placeholder="ORD-2025-00001"
                        required
                        value="<?php echo htmlspecialchars($_POST['order_number'] ?? ''); ?>"
                    >
                    <div class="help-text">Lo encontrarás en el email de confirmación</div>
                </div>

                <button type="submit" class="btn">
                    🔍 Buscar Pedido
                </button>
            </form>

            <div class="divider">o</div>

            <div class="link-section">
                <a href="<?php echo url('/'); ?>" class="link-btn">
                    🏠 Volver al inicio
                </a>
            </div>
        </div>
    </div>

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

        // Update on page load
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
