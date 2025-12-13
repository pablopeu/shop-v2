<?php
/**
 * Search Page with Filters
 */

// Define security constant to prevent direct file access


// Set security headers

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

// Start session

// Get search query and filters
$query = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$in_stock_only = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';

// Build filters array
$filters = [
    'active_only' => true,
    'sort' => $sort
];

if ($min_price !== null) {
    $filters['min_price'] = $min_price;
}

if ($max_price !== null) {
    $filters['max_price'] = $max_price;
}

if ($in_stock_only) {
    $filters['in_stock'] = true;
}

// Perform search
$results = search_products($query, $filters);

// Filter out products that are out of stock and should be hidden
$results = array_filter($results, function($product) {
    // Show product if it has stock OR if hide_when_out_of_stock is not set/false
    $hide_when_no_stock = $product['hide_when_out_of_stock'] ?? false;
    if ($hide_when_no_stock && $product['stock'] <= 0) {
        return false; // Hide this product
    }
    return true; // Show this product
});

// Get site configuration
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$currency_config = read_json(APP_PATH . '/config/currency.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Get all products for price range calculation
$all_products = get_all_products(true);
$prices = array_map(function($p) {
    return $p['price_ars'];
}, $all_products);

$absolute_min = !empty($prices) ? min($prices) : 0;
$absolute_max = !empty($prices) ? max($prices) : 10000;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar<?php echo !empty($query) ? ' - ' . htmlspecialchars($query) : ''; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <div class="search-header">
            <h1>
                <?php if (!empty($query)): ?>
                    Resultados para "<?php echo htmlspecialchars($query); ?>"
                <?php else: ?>
                    Todos los Productos
                <?php endif; ?>
            </h1>
            <p>Se encontraron <?php echo count($results); ?> productos</p>
        </div>

        <div class="search-layout">
            <!-- Filters -->
            <aside class="filters">
                <h2>🔍 Filtros</h2>

                <form method="GET" action="<?php echo url('/buscar'); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">

                    <!-- Sort -->
                    <div class="filter-group">
                        <h3>Ordenar por</h3>
                        <select name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Más nuevos</option>
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Mejores valorados</option>
                        </select>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-group">
                        <h3>Rango de Precio (ARS)</h3>
                        <input type="number"
                               name="min_price"
                               placeholder="Mínimo"
                               min="0"
                               value="<?php echo $min_price !== null ? $min_price : ''; ?>">
                        <input type="number"
                               name="max_price"
                               placeholder="Máximo"
                               min="0"
                               value="<?php echo $max_price !== null ? $max_price : ''; ?>">
                        <small class="filter-helper-text">
                            Rango: $<?php echo number_format($absolute_min, 0, ',', '.'); ?> - $<?php echo number_format($absolute_max, 0, ',', '.'); ?>
                        </small>
                    </div>

                    <!-- Availability -->
                    <div class="filter-group">
                        <h3>Disponibilidad</h3>
                        <label>
                            <input type="checkbox" name="in_stock" value="1" <?php echo $in_stock_only ? 'checked' : ''; ?>>
                            <span>Solo con stock</span>
                        </label>
                    </div>

                    <button type="submit" class="apply-filters">Aplicar Filtros</button>
                    <a href="<?php echo url('/buscar?q=' . urlencode($query)); ?>" class="clear-filters">Limpiar Filtros</a>
                </form>
            </aside>

            <!-- Results -->
            <div class="results">
                <?php if (empty($results)): ?>
                    <div class="no-results">
                        <h2>No se encontraron resultados</h2>
                        <?php if (!empty($query)): ?>
                            <p>No encontramos productos que coincidan con "<?php echo htmlspecialchars($query); ?>"</p>
                            <p>Intenta con otros términos de búsqueda o <a href="<?php echo url('/buscar'); ?>">explora todos los productos</a></p>
                        <?php else: ?>
                            <p>No hay productos disponibles en este momento.</p>
                            <a href="<?php echo url('/'); ?>" class="btn">Volver al Inicio</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php
                        require_once APP_PATH . '/includes/frontend/product-card.php';
                        foreach ($results as $product):
                            render_product_card($product, [
                                'currency' => $selected_currency,
                                'show_favorite_btn' => true,
                                'show_add_to_cart' => true
                            ]);
                        endforeach;
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

        <!-- Shared JavaScript Modules -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>

<script nonce="<?= csp_nonce() ?>">
        const API_GET_PRODUCTS = '<?php echo url('/api/get_products.php'); ?>';

        // Cookie helpers for favorites

        // Update cart count

        ShopCart.updateCartBadge();
        ShopFavorites.updateFavoritesCount();

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: goToProductPage
         */
        function goToProductPage(event, element, params) {
            const slug = params?.slug;
            if (slug) {
                window.location.href = '<?php echo url('/producto/'); ?>' + encodeURIComponent(slug);
            }
        }

        /**
         * Wrapper: goToProduct (para tarjetas clickeables en theme Modernist)
         */
        function goToProduct(event, element, params) {
            const url = params?.url;
            if (url) window.location.href = url;
        }

        // Export for event delegation
        window.goToProductPage = goToProductPage;
        window.goToProduct = goToProduct;
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>

    <!-- Cart Validator -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/cart-validator.js'); ?>"></script>
    <!-- Mobile Menu -->
    <?php include APP_PATH . '/includes/mobile-menu.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- Auto-update Exchange Rate -->
    <?php include APP_PATH . '/includes/auto-update-exchange.php'; ?>
</body>
</html>
