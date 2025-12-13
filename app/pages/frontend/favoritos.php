<?php
/**
 * Favorites / Wishlist Page
 */

// Define security constant to prevent direct file access


// Set security headers

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

// Start session

// Get site configuration
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$currency_config = read_json(APP_PATH . '/config/currency.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Favoritos - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <!-- Favorites page styles are managed by theme system in themes/_base/pages.css -->
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1>❤️ Mis Favoritos</h1>
            <button class="share-wishlist" data-action="shareWishlist">
                🔗 Compartir Mi Lista
            </button>
        </div>

        <div class="favorites-container" id="favoritesContainer">
            <div class="empty-state">
                <h2>💔</h2>
                <h3>No tenes favoritos aún</h3>
                <p>Agrega productos a tu lista de favoritos para verlos aquí</p>
                <a href="<?php echo url('/'); ?>" class="btn btn-primary btn-large">Explorar Productos</a>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Shared JS Modules (MUST load BEFORE main script) -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>

    <script nonce="<?= csp_nonce() ?>">
        const CURRENCY = '<?php echo $selected_currency; ?>';
        const EXCHANGE_RATE = <?php echo $currency_config['exchange_rate']; ?>;
        const API_GET_PRODUCTS = '<?php echo url('/api/get_products.php'); ?>';

        // Cookie helpers


        // Format product price intelligently


        // Load favorites
        function loadFavorites() {
            const favorites = ShopFavorites.getFavorites();

            // Update favorites count in header if element exists
            const favoritesCountEl = document.getElementById('favorites-count');
            if (favoritesCountEl) {
                favoritesCountEl.textContent = favorites.length;
            }

            if (favorites.length === 0) {
                showEmptyState();
                return;
            }

            fetchFavoriteProducts(favorites);
        }

        // Show empty state
        function showEmptyState() {
            document.getElementById('favoritesContainer').innerHTML = `
                <div class="empty-state">
                    <h2>💔</h2>
                    <h3>No tenes favoritos aún</h3>
                    <p>Agrega productos a tu lista de favoritos para verlos aquí</p>
                    <a href="<?php echo url('/'); ?>" class="btn btn-primary btn-large">Explorar Productos</a>
                </div>
            `;
        }

        // Fetch favorite products
        async function fetchFavoriteProducts(favoriteIds) {
            try {
                console.log('Fetching favorites for IDs:', favoriteIds);
                const response = await fetch(API_GET_PRODUCTS, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ product_ids: favoriteIds })
                });

                if (!response.ok) {
                    console.error('API response not OK:', response.status, response.statusText);
                    showEmptyState();
                    return;
                }

                const products = await response.json();
                console.log('Products received from API:', products);

                if (!Array.isArray(products) || products.length === 0) {
                    console.warn('No products returned from API');
                    showEmptyState();
                    return;
                }

                renderFavorites(products);
            } catch (error) {
                console.error('Error fetching favorites:', error);
                showEmptyState();
            }
        }

        // Render favorites
        function renderFavorites(products) {
            if (products.length === 0) {
                showEmptyState();
                return;
            }

            console.log('Rendering', products.length, 'products');

            let html = '<div class="favorites-grid">';

            products.forEach((product, index) => {
                console.log(`Product ${index}:`, {
                    id: product.id,
                    slug: product.slug,
                    name: product.name,
                    thumbnail: product.thumbnail
                });

                const inStock = product.stock > 0;
                const slug = product.slug || product.id || 'unknown'; // Fallback chain

                if (!slug || slug === 'unknown') {
                    console.error('Product missing both slug and id:', product);
                }

                // Thumbnail already includes full path from API, use directly
                const thumbnailUrl = product.thumbnail || '<?php echo url("/images/placeholder.png"); ?>';

                html += `
                    <div class="favorite-card">
                        <div class="favorite-image-container">
                            <img src="${thumbnailUrl}"
                                 alt="${product.name}"
                                 data-action="goToProduct"
                                 data-slug="${slug}">
                            <button class="favorite-heart" data-action="removeFavorite" data-product-id="${product.id}" title="Eliminar de favoritos">
                                ❤️
                            </button>
                        </div>

                        <div class="favorite-info">
                            <div class="favorite-name" data-action="goToProduct" data-slug="${slug}">
                                ${product.name}
                            </div>

                            ${product.pickup_only ? '<div class="favorite-pickup-badge">🏪 Solo retiro en persona</div>' : ''}

                            <div class="favorite-actions">
                                <button class="favorite-btn favorite-btn-add"
                                        data-action="addToCart"
                                        data-product-id="${product.id}"
                                        ${!inStock ? 'disabled' : ''}>
                                    ${!inStock ? 'Agotado' : 'Agregar'}
                                </button>
                                <button class="favorite-btn favorite-btn-view" data-action="goToProduct" data-slug="${slug}">
                                    Ver
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            document.getElementById('favoritesContainer').innerHTML = html;
        }

        // Remove from favorites
        function removeFavorite(productId) {
            let favorites = ShopFavorites.getFavorites();
            favorites = favorites.filter(id => id !== productId);
            ShopFavorites.saveFavorites(favorites);

            loadFavorites();
            ShopUtils.showToast('💔 Eliminado de favoritos');
        }

        // Add to cart


        // Go to product
        function goToProduct(slug) {
            window.location.href = '<?php echo url('/producto/'); ?>' + encodeURIComponent(slug);
        }

        // Share wishlist
        async function shareWishlist() {
            const favorites = ShopFavorites.getFavorites();

            if (favorites.length === 0) {
                ShopUtils.showToast('⚠️ Tu lista de favoritos está vacía');
                return;
            }

            try {
                // Llamar al API para generar código corto
                const response = await fetch('<?php echo url('/api/create_short_link.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ product_ids: favorites })
                });

                if (!response.ok) {
                    throw new Error('Error al generar link');
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Error desconocido');
                }

                // Crear URL corta con el código
                const shareUrl = window.location.origin + '<?php echo url('/favoritos'); ?>?s=' + data.code;

                // Copy to clipboard
                navigator.clipboard.writeText(shareUrl).then(() => {
                    ShopUtils.showToast('🔗 Link corto copiado al portapapeles');
                }).catch(() => {
                    // Fallback: mostrar el URL en el modal
                    showModal({
                        title: 'Compartir tu lista de favoritos',
                        message: 'Copia este link para compartir tu lista:',
                        details: `<div class="share-url-box">${shareUrl}</div>`,
                        icon: '🔗',
                        iconClass: 'info',
                        confirmText: 'Cerrar',
                        showCancel: false,
                        confirmType: 'primary',
                        onConfirm: function() {}
                    });
                });
            } catch (error) {
                console.error('Error al crear link:', error);
                ShopUtils.showToast('❌ Error al generar link');
            }
        }

        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Show toast


        // Check if shared wishlist
        const urlParams = new URLSearchParams(window.location.search);
        const sharedFavorites = urlParams.get('shared');
        const shortCode = urlParams.get('s');

        if (shortCode) {
            // Load wishlist from short code
            fetch('<?php echo url('/api/get_shared_wishlist.php'); ?>?code=' + encodeURIComponent(shortCode))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.product_ids) {
                        ShopFavorites.saveFavorites(data.product_ids);
                        ShopUtils.showToast('✅ Lista de favoritos compartida cargada');
                        loadFavorites();
                    } else {
                        ShopUtils.showToast('❌ ' + (data.error || 'Error al cargar lista'));
                    }
                })
                .catch(error => {
                    console.error('Error loading shared wishlist:', error);
                    ShopUtils.showToast('❌ Error al cargar lista');
                })
                .finally(() => {
                    // Remove query parameter from URL
                    window.history.replaceState({}, document.title, '<?php echo url('/favoritos'); ?>');
                });
        } else if (sharedFavorites) {
            // Backward compatibility: Load shared favorites from old format
            const sharedIds = sharedFavorites.split(',');
            ShopFavorites.saveFavorites(sharedIds);
            ShopUtils.showToast('✅ Lista de favoritos compartida cargada');
            loadFavorites();

            // Remove query parameter from URL
            window.history.replaceState({}, document.title, '<?php echo url('/favoritos'); ?>');
        } else {
            // Initialize normally
            loadFavorites();
        }

        updateCartCount();

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: goToProduct
         */
        const _goToProduct = goToProduct;
        window.goToProduct = function(eventOrSlug, element, params) {
            const slug = params?.slug || (typeof eventOrSlug === 'string' ? eventOrSlug : null);
            if (slug) return _goToProduct(slug);
        };

        /**
         * Wrapper: removeFavorite
         */
        const _removeFavorite = removeFavorite;
        window.removeFavorite = function(eventOrId, element, params) {
            const productId = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
            if (productId) return _removeFavorite(productId);
        };

        /**
         * Wrapper: addToCart (uses ShopCart module)
         */
        window.addToCart = function(eventOrId, element, params) {
            const productId = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
            if (productId) return ShopCart.addToCart(productId);
        };

        /**
         * Wrapper: shareWishlist
         */
        const _shareWishlist = shareWishlist;
        window.shareWishlist = function(event, element, params) {
            return _shareWishlist();
        };
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/frontend/modal.php'; ?>

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
</body>
</html>
