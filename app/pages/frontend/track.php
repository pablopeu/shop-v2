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

// Pre-llenar formulario desde parámetros GET (útil para links desde emails)
$prefill_email = $_GET['email'] ?? '';
$prefill_order = $_GET['order'] ?? '';

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

/**
 * Normaliza un número de orden para comparación flexible
 * Acepta: ORD-2025-00072, ord-2025-72, ORD-2025-072, etc.
 * Retorna: array con partes normalizadas o null si formato inválido
 */
function normalize_order_number($order_number) {
    // Limpiar espacios y convertir a mayúsculas
    $order_number = strtoupper(trim($order_number));

    // Agregar prefijo ORD- si no lo tiene
    if (!preg_match('/^ORD-/', $order_number)) {
        $order_number = 'ORD-' . $order_number;
    }

    // Extraer partes: ORD-YYYY-NNNNN
    if (preg_match('/^ORD-(\d{4})-(\d+)$/', $order_number, $matches)) {
        return [
            'year' => $matches[1],
            'number' => (int)$matches[2] // Convertir a int elimina ceros de padding
        ];
    }

    return null;
}

/**
 * Compara dos números de orden de forma flexible
 */
function orders_match($order_number_1, $order_number_2) {
    $norm1 = normalize_order_number($order_number_1);
    $norm2 = normalize_order_number($order_number_2);

    if (!$norm1 || !$norm2) {
        return false;
    }

    return $norm1['year'] === $norm2['year'] &&
           $norm1['number'] === $norm2['number'];
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
                orders_match($order['order_number'], $order_number)) {
                $found_order = $order;
                break;
            }
        }

        if ($found_order) {
            // Redirect immediately to pedido page
            redirect(url("/pedido?order={$found_order['id']}&token={$found_order['tracking_token']}"));
            exit;
        } else {
            $error = 'No se encontró ningún pedido con esos datos. Por favor verifica:<br>
                     • Que el email sea exactamente el que usaste al comprar<br>
                     • Que el número de pedido esté correcto (lo encontrarás en el email de confirmación)<br>
                     • Si el problema persiste, contacta con soporte adjuntando tu email de confirmación';
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

    <!-- Formulario de búsqueda -->
    <div class="track-form-container">
            <h1>📦 Rastrear mi Pedido</h1>
            <p>Ingresa tus datos para ver el estado de tu compra</p>

            <?php if ($error): ?>
                <div class="track-form-error">
                    <?php echo $error; // El error ya está sanitizado y puede contener HTML ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="track-form" id="trackForm">
                <div class="track-form-group">
                    <label for="email">📧 Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="tu@email.com"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? $prefill_email); ?>"
                    >
                    <div class="track-form-help">El email que usaste al realizar la compra</div>
                </div>

                <div class="track-form-group">
                    <label for="order_number">🔢 Número de Pedido</label>
                    <input
                        type="text"
                        id="order_number"
                        name="order_number"
                        placeholder="ORD-2025-72 o 2025-72"
                        required
                        value="<?php echo htmlspecialchars($_POST['order_number'] ?? $prefill_order); ?>"
                    >
                    <div class="track-form-help">Puedes usar mayúsculas, minúsculas, con o sin ceros (ej: ord-2025-72)</div>
                </div>

                <button type="submit" class="track-form-submit">
                    🔍 Buscar Pedido
                </button>
            </form>

            <div class="track-form-divider">
                <span>o</span>
            </div>

            <div class="track-form-footer">
                <a href="<?php echo url('/'); ?>" class="track-form-link">
                    🏠 Volver al inicio
                </a>
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

    <!-- Track Form Enhancement: Remember email -->
    <script nonce="<?= csp_nonce() ?>">
        (function() {
            const form = document.getElementById('trackForm');
            const emailInput = document.getElementById('email');

            // Cargar email guardado al cargar la página (solo si está vacío)
            if (emailInput && !emailInput.value) {
                const savedEmail = localStorage.getItem('track_last_email');
                if (savedEmail) {
                    emailInput.value = savedEmail;
                }
            }

            // Guardar email cuando se envía el formulario
            if (form) {
                form.addEventListener('submit', function() {
                    if (emailInput && emailInput.value) {
                        localStorage.setItem('track_last_email', emailInput.value);
                    }
                });
            }
        })();
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
