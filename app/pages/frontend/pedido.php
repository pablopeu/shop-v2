<?php
/**
 * Order Tracking Page
 * URL: /pedido.php?order={order-id}&token={secure-token}
 */

// Get order info from URL
$order_id = $_GET['order'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($order_id) || empty($token)) {
    $error = 'Link de seguimiento inválido';
    $order = null;
} else {
    // Get order by token
    $order = get_order_by_token($token);

    if (!$order || $order['id'] !== $order_id) {
        $error = 'Pedido no encontrado';
        $order = null;
    } else {
        $error = null;
    }
}

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');
$app_config = require APP_PATH . '/config/config.php';

$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Get all products for cart/favorites panels
$all_products = get_all_products(true); // Only active products

// Helper: Generar URL absoluta completa (con dominio)
function get_absolute_url($path) {
    global $app_config;
    // Use current server host if app_url is not configured
    $default_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base_url = rtrim($app_config['app_url'] ?? $default_url, '/');
    $base_path = rtrim($app_config['base_path'] ?? '', '/');
    $path = ltrim($path, '/');

    return $base_url . $base_path . '/' . $path;
}

// Funciones helper para tracking timeline
function get_status_icon($status) {
    $icons = [
        'pendiente' => '⏳',
        'en_preparacion' => '📦',
        'en_transito' => '🚚',
        'en_reparto' => '🏃',
        'entregada' => '✅',
        'fallida' => '❌',
        'devuelta' => '↩️',
        'cancelada' => '🚫'
    ];
    return $icons[$status] ?? '📌';
}

function get_status_label($status) {
    $labels = [
        'pendiente' => 'Pendiente',
        'en_preparacion' => 'En Preparación',
        'en_transito' => 'En Tránsito',
        'en_reparto' => 'En Reparto',
        'entregada' => 'Entregada',
        'fallida' => 'Intento Fallido',
        'devuelta' => 'Devuelta',
        'cancelada' => 'Cancelada'
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function get_status_color_class($status) {
    $colors = [
        'entregada' => 'status-success',
        'en_transito' => 'status-info',
        'en_reparto' => 'status-info',
        'fallida' => 'status-error',
        'devuelta' => 'status-warning',
        'cancelada' => 'status-error',
        'pendiente' => 'status-muted',
        'en_preparacion' => 'status-info'
    ];
    return $colors[$status] ?? 'status-default';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $order ? "Pedido {$order['order_number']}" : 'Seguimiento de Pedido'; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <?php if ($error): ?>
        <!-- Error State -->
        <div class="pedido-error-container">
            <div class="pedido-error-icon">🔍</div>
            <h2><?php echo htmlspecialchars($error); ?></h2>
            <p>Por favor verifica el link de seguimiento que recibiste por email.</p>
            <a href="<?php echo url('/'); ?>" class="pedido-error-link">Volver al inicio</a>
        </div>

    <?php else: ?>
        <!-- Información del Pedido -->
        <div class="pedido-order-header">
            <h1>Pedido #<?php echo htmlspecialchars($order['order_number']); ?></h1>
            <div class="pedido-order-date">
                Realizado el <?php echo date('d/m/Y', strtotime($order['date'])); ?> a las <?php echo date('H:i', strtotime($order['date'])); ?>
            </div>
        </div>

        <!-- Timeline de Tracking del Carrier -->
        <?php if (!empty($order['shipping']['carrier']) && !empty($order['shipping']['history'])): ?>
        <div class="tracking-timeline-section">
            <h2>📦 Seguimiento de Envío</h2>

            <?php if (!empty($order['tracking_number'])): ?>
            <div class="tracking-number">
                <strong>Número de seguimiento:</strong> <?= htmlspecialchars($order['tracking_number']) ?>
            </div>
            <?php endif; ?>

            <div class="timeline">
                <?php
                $history = $order['shipping']['history'] ?? [];

                // Ordenar por fecha descendente (más reciente primero)
                usort($history, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });

                foreach ($history as $index => $event):
                    $is_current = ($index === 0);
                    $status = $event['status'] ?? 'desconocido';
                    $date = date('d/m/Y H:i', strtotime($event['date']));
                    $icon = get_status_icon($status);
                    $color_class = get_status_color_class($status);
                ?>
                <div class="timeline-event <?= $is_current ? 'current' : '' ?> <?= $color_class ?>">
                    <div class="timeline-icon"><?= $icon ?></div>
                    <div class="timeline-content">
                        <div class="timeline-status"><?= get_status_label($status) ?></div>
                        <div class="timeline-date"><?= $date ?></div>
                        <?php if (!empty($event['data']['location']['city'])): ?>
                        <div class="timeline-location">
                            📍 <?= htmlspecialchars($event['data']['location']['city']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($order['tracking_url'])): ?>
            <div class="tracking-external-link-container">
                <a href="<?= htmlspecialchars($order['tracking_url']) ?>" target="_blank" rel="noopener" class="tracking-external-link">
                    Ver seguimiento completo en el carrier →
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Productos Comprados -->
        <div class="order-products-section">
            <h2>🛍️ Productos Comprados</h2>

            <?php
            // Obtener productos del carrito para imágenes
            $all_products = get_all_products(true);
            $products_by_id = [];
            foreach ($all_products as $p) {
                $products_by_id[$p['id']] = $p;
            }

            foreach ($order['items'] as $item):
                $product = $products_by_id[$item['product_id']] ?? null;
                $thumbnail = $product ? url($product['thumbnail']) : null;
            ?>
            <div class="product-item">
                <?php if ($thumbnail): ?>
                <img src="<?= $thumbnail ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="product-image">
                <?php endif; ?>
                <div class="product-details">
                    <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="product-quantity">Cantidad: <?= $item['quantity'] ?></div>
                </div>
                <div class="product-price">
                    <?= format_price($item['final_price'], $order['currency']) ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="order-total">
                <div class="order-total-row">
                    <span class="order-total-label">Subtotal:</span>
                    <span class="order-total-value"><?= format_price($order['subtotal'], $order['currency']) ?></span>
                </div>

                <?php if ($order['discount_coupon'] > 0): ?>
                <div class="order-total-row-discount">
                    <span class="order-total-label">Descuento (<?= htmlspecialchars($order['coupon_code']) ?>):</span>
                    <span class="order-total-value">-<?= format_price($order['discount_coupon'], $order['currency']) ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($order['shipping']['cost']) && $order['shipping']['cost'] > 0): ?>
                <div class="order-total-row">
                    <span class="order-total-label">
                        Gastos de envío<?php if (!empty($order['shipping']['carrier_name'])): ?> (<?= htmlspecialchars($order['shipping']['carrier_name']) ?>)<?php endif; ?>:
                    </span>
                    <span class="order-total-value"><?= format_price($order['shipping']['cost'], $order['currency']) ?></span>
                </div>
                <?php endif; ?>

                <div class="order-total-row-final">
                    <span class="order-total-label">Total:</span>
                    <span class="order-total-amount"><?= format_price($order['total'], $order['currency']) ?></span>
                </div>
            </div>
        </div>

        <!-- Link Permanente -->
        <div class="tracking-link-card">
            <h2>🔗 Link Permanente de Seguimiento</h2>
            <p>Guarda este link para consultar tu pedido en cualquier momento:</p>
            <div class="link-display">
                <input type="text" value="<?= htmlspecialchars(get_absolute_url("/pedido?order={$order['id']}&token={$order['tracking_token']}")) ?>" readonly id="tracking-link-input">
                <button data-action="copyLink">Copiar</button>
            </div>
            <p class="tracking-link-explanation">
                No necesitas volver a ingresar tu email y número de pedido. Con este link puedes acceder directamente.
            </p>
        </div>

        <!-- Botón volver al inicio -->
        <div class="pedido-back-button-container">
            <a href="<?php echo url('/'); ?>" class="pedido-back-button">
                🏠 Volver al Inicio
            </a>
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
    </script>

    <script nonce="<?= csp_nonce() ?>">
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

            button.textContent = '✅ Copiado!';
            button.classList.add('btn-copied');

            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('btn-copied');
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
