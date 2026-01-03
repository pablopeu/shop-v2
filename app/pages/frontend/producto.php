<?php
/**
 * Product Detail Page
 *
 * Cargado por: public_html/index.php vía Router
 * Ruta: GET /producto/:slug
 */

// Bootstrap ya maneja: APP_ENTRY_POINT, includes, session, security headers

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once APP_PATH . '/pages/maintenance.php';
    exit;
}

// Get product slug from router
global $router;
$slug = $router->getParam('slug') ?? '';

if (empty($slug)) {
    redirect(url('/'));
}

$product = get_product_by_slug($slug);

if (!$product) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Producto no encontrado</title></head><body><h1>Producto no encontrado</h1><a href="' . url('/') . '">Volver al inicio</a>
    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body></html>';
    exit;
}

// Get site configuration
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$currency_config = read_json(APP_PATH . '/config/currency.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Load active theme configuration for product view settings
$theme_json_path = PUBLIC_PATH . "/assets/themes/{$active_theme}/theme.json";
$theme_json = file_exists($theme_json_path) ? read_json($theme_json_path) : [];
$product_view_config = $theme_json['components']['product_view'] ?? [];

// Extract product view settings with defaults
$gallery_layout = $product_view_config['gallery_layout'] ?? 'thumbnails-bottom';
$show_breadcrumb = $product_view_config['show_breadcrumb'] ?? true;
$show_share = $product_view_config['show_share'] ?? true;
$show_stock = $product_view_config['show_stock'] ?? true;
$show_nav_buttons = $product_view_config['show_nav_buttons'] ?? true;
$show_image_counter = $product_view_config['show_image_counter'] ?? true;

// Get all products for cart panel
$all_products = get_all_products(true); // Only active products

// Get promotion if applicable
$promotions_data = read_json(APP_PATH . '/data/promotions.json');
$active_promotion = null;
$discounted_price = null;

foreach ($promotions_data['promotions'] ?? [] as $promo) {
    if (!$promo['active']) continue;

    // Check if promotion is valid by date
    $now = time();
    if (!empty($promo['start_date']) && strtotime($promo['start_date']) > $now) continue;
    if (!empty($promo['end_date']) && strtotime($promo['end_date']) < $now) continue;

    // Check if applies to this product
    if ($promo['scope'] === 'all' || in_array($product['id'], $promo['products'] ?? [])) {
        $active_promotion = $promo;

        $price = $product['price_' . strtolower($selected_currency)];
        if ($promo['type'] === 'percentage') {
            $discounted_price = $price * (1 - $promo['value'] / 100);
        } else {
            $discounted_price = $price - $promo['value'];
        }
        break;
    }
}

// Get applicable coupons for this product
$coupons_data = read_json(APP_PATH . '/data/coupons.json');
$applicable_coupons = [];

foreach ($coupons_data['coupons'] ?? [] as $coupon) {
    if (!$coupon['active']) continue;

    // Check if coupon is valid by date
    $now = time();
    if (!empty($coupon['start_date']) && strtotime($coupon['start_date']) > $now) continue;
    if (!empty($coupon['end_date']) && strtotime($coupon['end_date']) < $now) continue;

    // Check if applies to this product
    if ($coupon['applicable_to'] === 'all' || in_array($product['id'], $coupon['products'] ?? [])) {
        $applicable_coupons[] = $coupon;
    }
}

// Meta tags for SEO
$page_title = $product['seo']['title'] ?? $product['name'] . ' - ' . $site_config['site_name'];
$page_description = $product['seo']['description'] ?? substr($product['description'], 0, 160);

// Handle both image formats: array of strings or array of objects
$first_image = '';
if (!empty($product['images'])) {
    if (is_array($product['images'][0])) {
        $first_image = $product['images'][0]['url'] ?? '';
    } else {
        $first_image = $product['images'][0];
    }
}
$og_image = $first_image ? get_base_url() . $first_image : '';

// Record visit
$visits_file = APP_PATH . '/data/visits.json';
$visits_data = read_json($visits_file);
if (!isset($visits_data['products'][$product['id']])) {
    $visits_data['products'][$product['id']] = [
        'total_visits' => 0,
        'last_visit' => null
    ];
}
$visits_data['products'][$product['id']]['total_visits']++;
$visits_data['products'][$product['id']]['last_visit'] = get_timestamp();
write_json($visits_file, $visits_data);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($product['seo']['keywords'] ?? ''); ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($product['name']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:url" content="<?php echo get_base_url() . url('/producto/' . urlencode($slug)); ?>">
    <meta property="og:type" content="product">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($product['name']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <!-- Breadcrumb Component -->
    <?php if ($show_breadcrumb): ?>
    <?php
    require_once APP_PATH . '/includes/frontend/breadcrumb.php';
    render_breadcrumb([
        ['label' => 'Inicio', 'url' => url('/')],
        ['label' => $product['name'], 'url' => null]
    ], ['separator' => '/']);
    ?>
    <?php endif; ?>

    <!-- Product -->
    <div class="container-fluid container-fluid-limited">
        <div class="product-container">
            <div class="product-grid">
                <!-- Gallery -->
                <div class="product-gallery gallery-container <?php echo htmlspecialchars($gallery_layout); ?>">
                    <div class="main-image-container" id="mainImageContainer">
                        <?php if (!empty($product['images'])): ?>
                            <?php
                            // Get first image URL and alt text
                            $first_img_url = is_array($product['images'][0]) ? ($product['images'][0]['url'] ?? '') : $product['images'][0];
                            $first_img_alt = is_array($product['images'][0]) ? ($product['images'][0]['alt'] ?? $product['name']) : $product['name'];
                            ?>
                            <img src="<?php echo htmlspecialchars(url($first_img_url)); ?>"
                                 alt="<?php echo htmlspecialchars($first_img_alt); ?>"
                                 class="product-main-image main-image"
                                 id="mainImage">

                            <!-- Click to Zoom Indicator -->
                            <div class="zoom-indicator">
                                <i class="fas fa-search-plus"></i>
                                <span>Click to zoom</span>
                            </div>

                            <?php if ($show_nav_buttons && count($product['images']) > 1): ?>
                                <button class="gallery-nav prev" data-action="changeImage" data-direction="-1">‹</button>
                                <button class="gallery-nav next" data-action="changeImage" data-direction="1">›</button>
                            <?php endif; ?>

                            <?php if ($show_image_counter): ?>
                            <div class="image-counter">
                                <span id="currentImageIndex">1</span>/<?php echo count($product['images']); ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-image">Sin imagen disponible</div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($product['images']) && count($product['images']) > 1): ?>
                    <div class="product-thumbnails thumbnails">
                        <?php foreach ($product['images'] as $index => $image): ?>
                            <?php
                            $img_url = is_array($image) ? ($image['url'] ?? '') : $image;
                            $img_alt = is_array($image) ? ($image['alt'] ?? $product['name']) : $product['name'];
                            ?>
                            <div class="product-thumbnail thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                 data-action="selectImage" data-index="<?php echo $index; ?>">
                                <img src="<?php echo htmlspecialchars(url($img_url)); ?>"
                                     alt="<?php echo htmlspecialchars($img_alt); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <!-- Title with Favorite Heart -->
                    <div class="product-title-container">
                        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                        <button class="favorite-heart" data-action="toggleFavorite" data-product-id="<?php echo $product['id']; ?>" id="favorite-btn-<?php echo $product['id']; ?>" title="Agregar a favoritos">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>

                    <!-- Price with Stock Badge -->
                    <div class="price-stock-container">
                        <div class="price-wrapper">
                            <?php if ($active_promotion && $discounted_price): ?>
                                <div class="price-original-inline">
                                    <?php echo format_product_price($product, $selected_currency); ?>
                                </div>
                                <div class="price-current-large">
                                    <?php echo format_price($discounted_price, $selected_currency); ?>
                                </div>
                            <?php else: ?>
                                <div class="price-current-large">
                                    <?php echo format_product_price($product, $selected_currency); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($show_stock): ?>
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-badge-inline">En Stock</span>
                            <?php else: ?>
                                <span class="stock-badge-out">Sin Stock</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($active_promotion): ?>
                        <div class="promotion-tag">
                            <i class="fas fa-tag"></i> PROMOCIÓN: <?php echo $active_promotion['value']; ?><?php echo $active_promotion['type'] === 'percentage' ? '%' : '$'; ?> OFF
                        </div>
                    <?php endif; ?>

                    <!-- Benefits/Promotions Section -->
                    <?php if (!empty($product['pickup_only']) || $product['stock'] <= $product['stock_alert']): ?>
                        <div class="product-benefits">
                            <?php /* Coupons are hidden from frontend - only visible in checkout */ ?>

                            <?php if (!empty($product['pickup_only'])): ?>
                                <div class="benefit-item">
                                    <i class="fas fa-store"></i>
                                    <span>Solo retiro en persona</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($product['stock'] > 0 && $product['stock'] <= $product['stock_alert']): ?>
                                <div class="benefit-item benefit-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>¡Últimas <?php echo $product['stock']; ?> unidades disponibles!</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Quantity & Buttons Row - All in one line -->
                    <div class="quantity-actions-row">
                        <?php
                        require_once APP_PATH . '/includes/frontend/quantity-selector.php';
                        render_quantity_selector([
                            'id' => 'quantity-input',
                            'value' => 1,
                            'min' => 1,
                            'max' => $product['stock'],
                            'disabled' => $product['stock'] === 0
                        ]);
                        ?>

                        <button id="add-to-cart-btn"
                                class="btn-primary-action"
                                data-action="addToCartWithQuantity"
                                data-product-id="<?php echo $product['id']; ?>"
                                <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                            <?php echo $product['stock'] === 0 ? 'Agotado' : 'Agregar al Carrito'; ?>
                        </button>

                        <button class="btn-secondary-action"
                                data-action="buyNow"
                                data-product-id="<?php echo $product['id']; ?>"
                                <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                            Comprar
                        </button>
                    </div>

                    <!-- Description -->
                    <div class="product-description">
                        <h3>Descripción</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <!-- Share Buttons Component -->
                    <?php if ($show_share): ?>
                    <?php
                    require_once APP_PATH . '/includes/frontend/share-buttons.php';
                    render_share_buttons([
                        'url' => get_base_url() . '/producto/' . $slug,
                        'title' => $product['name']
                    ]);
                    ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" data-action="closeLightbox">
        <div class="lightbox-content">
            <button class="lightbox-close" data-action="closeLightbox">×</button>
            <img src="" alt="" class="lightbox-image" id="lightboxImage">
            <?php if (!empty($product['images']) && count($product['images']) > 1): ?>
                <button class="lightbox-nav prev" data-action="changeImage" data-direction="-1" data-stop-propagation="true">‹</button>
                <button class="lightbox-nav next" data-action="changeImage" data-direction="1" data-stop-propagation="true">›</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Shared JS Modules (MUST load BEFORE main script) -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>

    <script nonce="<?= csp_nonce() ?>">
        // Base path for subdirectory support
        const basePath = '<?php echo $config['base_path'] ?? ''; ?>';
        const API_GET_PRODUCTS = '<?php echo url('/api/?endpoint=get-products'); ?>';

        // Current product data
        const productData = <?php
            $current_product = $product;
            if (isset($current_product['thumbnail'])) {
                $current_product['thumbnail'] = url($current_product['thumbnail']);
            }
            if (isset($current_product['images']) && is_array($current_product['images'])) {
                foreach ($current_product['images'] as &$img) {
                    if (is_array($img) && isset($img['url'])) {
                        $img['url'] = url($img['url']);
                    } elseif (is_string($img)) {
                        $img = url($img);
                    }
                }
                unset($img);
            }
            echo json_encode($current_product);
        ?>;

        // All products data for cart panel (global for shared modules)
        window.products = <?php
            $products_for_js = json_decode(json_encode($all_products), true);
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
                    unset($img);
                }
            }
            unset($p);
            echo json_encode($products_for_js);
        ?>;

        // Product images data - normalize to consistent format
        const rawImages = <?php echo json_encode($product['images'] ?? []); ?>;
        const productImages = rawImages.map(img => {
            if (typeof img === 'string') {
                // Si la URL ya es absoluta (empieza con / o http), no agregar basePath
                const url = (img.startsWith('/') || img.startsWith('http')) ? img : basePath + img;
                return { url: url, alt: '<?php echo htmlspecialchars($product['name']); ?>' };
            }
            // Si la URL ya es absoluta, no agregar basePath
            const url = (img.url.startsWith('/') || img.url.startsWith('http')) ? img.url : basePath + img.url;
            return { ...img, url: url };
        });
        let currentImageIndex = 0;

        // Change image in gallery
        function changeImage(direction) {
            if (productImages.length === 0) return;

            currentImageIndex = (currentImageIndex + direction + productImages.length) % productImages.length;
            updateImage();
        }

        // Select specific image
        function selectImage(index) {
            currentImageIndex = index;
            updateImage();
        }

        // Update displayed image
        function updateImage() {
            const mainImage = document.getElementById('mainImage');
            const lightboxImage = document.getElementById('lightboxImage');
            const currentIndexEl = document.getElementById('currentImageIndex');

            if (mainImage && productImages[currentImageIndex]) {
                mainImage.src = productImages[currentImageIndex].url;
                mainImage.alt = productImages[currentImageIndex].alt;
            }

            if (lightboxImage && productImages[currentImageIndex]) {
                lightboxImage.src = productImages[currentImageIndex].url;
                lightboxImage.alt = productImages[currentImageIndex].alt;
            }

            if (currentIndexEl) {
                currentIndexEl.textContent = currentImageIndex + 1;
            }

            // Update thumbnail active state
            document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
                thumb.classList.toggle('active', index === currentImageIndex);
            });
        }

        // Open lightbox
        document.getElementById('mainImageContainer')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('main-image')) {
                document.getElementById('lightbox').classList.add('active');
                updateImage();
            }
        });

        // Close lightbox
        function closeLightbox(event) {
            if (event.target.id === 'lightbox' || event.target.classList.contains('lightbox-close')) {
                document.getElementById('lightbox').classList.remove('active');
            }
        }

        // ESC key to close lightbox
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('lightbox').classList.remove('active');
            }
            if (e.key === 'ArrowLeft') {
                changeImage(-1);
            }
            if (e.key === 'ArrowRight') {
                changeImage(1);
            }
        });

        // Save cart with timestamp


        // Check if cart has expired (after 4 hours of inactivity)


        // Check cart expiration on page load
        ShopUtils.checkCartExpiration();

        // Add to cart
        // Increase quantity
        function increaseQuantity() {
            const input = document.getElementById('quantity-input');
            const max = parseInt(input.getAttribute('max'));
            const current = parseInt(input.value);
            if (current < max) {
                input.value = current + 1;
            }
        }

        // Decrease quantity
        function decreaseQuantity() {
            const input = document.getElementById('quantity-input');
            const min = parseInt(input.getAttribute('min'));
            const current = parseInt(input.value);
            if (current > min) {
                input.value = current - 1;
            }
        }

        // Add to cart with selected quantity
        async function addToCartWithQuantity(productId) {
            const input = document.getElementById('quantity-input');
            const quantity = parseInt(input.value);

            let cart = JSON.parse(localStorage.getItem('cart') || '[]');

            // Check if product already in cart (support both formats)
            const existingItem = cart.find(item => (item.product_id || item.id) === productId);

            if (existingItem) {
                existingItem.quantity += quantity;
            } else {
                cart.push({
                    product_id: productId,
                    quantity: quantity,
                    added_at: new Date().toISOString()
                });
            }

            ShopUtils.saveCart(cart);
            updateCartCount();

            // openCartPanel already calls renderCartPanel internally, so just open
            // Wait for render to complete before opening
            await ShopCart.renderCartPanel();
            ShopCart.openCartPanel();

            // Reset quantity to 1 after adding to cart
            input.value = 1;
        }


        // Cookie helpers


        // Toggle favorite


        // Update favorites count in header


        // ===== Favorites Panel Functions =====
        function renderFavoritesPanel() {
            const favorites = ShopFavorites.getFavorites();
            const body = document.getElementById('favorites-panel-body');

            if (!body) return; // Panel doesn't exist on this page

            if (favorites.length === 0) {
                body.innerHTML = '<div class="cart-empty">No tienes favoritos</div>';
                return;
            }

            // Get all products from API (POST request with product_ids)
            fetch('<?php echo url('/api/?endpoint=get-products'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ product_ids: favorites })
            })
                .then(response => response.json())
                .then(data => {
                    const allProducts = Array.isArray(data) ? data : [];
                    let html = '';

                    favorites.forEach(productId => {
                        const product = allProducts.find(p => p.id === productId);
                        if (!product) return;

                        const price = ShopUtils.formatProductPrice(product);
                        const productUrl = '<?php echo url('/producto/'); ?>' + encodeURIComponent(product.slug);

                        html += `
                            <div class="favorite-mini-card">
                                <div data-action="goToProduct" data-url="${productUrl}" class="related-product-link">
                                    <img src="${product.thumbnail || ''}" alt="${product.name}" class="related-product-image">
                                    <div class="related-product-info">
                                        <div class="related-product-name">${product.name}</div>
                                        <div class="related-product-price">${price}</div>
                                    </div>
                                </div>
                                <button class="favorite-remove-btn" data-action="removeFromFavorites" data-product-id="${product.id}" data-stop-propagation="true">Eliminar</button>
                            </div>
                        `;
                    });

                    body.innerHTML = html || '<div class="cart-empty">No tienes favoritos</div>';
                })
                .catch(error => {
                    console.error('Error loading favorites:', error);
                    body.innerHTML = '<div class="cart-empty">Error al cargar favoritos</div>';
                });
        }


        function openFavoritesPanel() {
            renderFavoritesPanel();
            const panel = document.getElementById('favorites-panel');
            const overlay = document.getElementById('favorites-overlay');
            if (panel) panel.classList.add('open');
            if (overlay) overlay.classList.add('open');
        }

        function closeFavoritesPanel() {
            const panel = document.getElementById('favorites-panel');
            const overlay = document.getElementById('favorites-overlay');
            if (panel) panel.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }

        function goToFavoritesPage() {
            window.location.href = '<?php echo url('/favoritos'); ?>';
        }

        function removeFromFavorites(productId) {
            let favorites = ShopFavorites.getFavorites();
            favorites = favorites.filter(id => id !== productId);
            ShopFavorites.saveFavorites(favorites);

            // Update UI
            ShopFavorites.updateFavoritesCount();
            renderFavoritesPanel();

            // Update heart button if exists on the page
            const heartBtn = document.getElementById('favorite-btn-' + productId);
            const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;
            if (heartBtn) heartBtn.classList.remove('active');
            if (heartIcon) {
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
            }

            ShopUtils.showToast('💔 Eliminado de favoritos');
        }

        // Check if product is in favorites on page load
        document.addEventListener('DOMContentLoaded', function() {
            const favorites = ShopFavorites.getFavorites();
            const productId = '<?php echo $product['id']; ?>';

            // Update favorites count in header
            ShopFavorites.updateFavoritesCount();
            renderFavoritesPanel();

            if (favorites.indexOf(productId) > -1) {
                const heartBtn = document.getElementById('favorite-btn-' + productId);
                const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;
                if (heartBtn) heartBtn.classList.add('active');
                if (heartIcon) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                }
            }
        });

        // Copy link
        function copyLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(function() {
                ShopUtils.showToast('📋 Link copiado al portapapeles');
            });
        }

        // Show toast notification


        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Initialize
        updateCartCount();

        // Touch swipe support for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        let touchStartTime = 0;
        let touchMoveDistance = 0;

        const mainImageContainer = document.getElementById('mainImageContainer');

        function handleSwipe() {
            const swipeThreshold = 50;
            if (touchEndX < touchStartX - swipeThreshold) {
                changeImage(1); // Swipe left
            }
            if (touchEndX > touchStartX + swipeThreshold) {
                changeImage(-1); // Swipe right
            }
        }

        if (mainImageContainer) {
            mainImageContainer.addEventListener('touchstart', e => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartTime = Date.now();
                touchMoveDistance = 0;
            }, { passive: true });

            mainImageContainer.addEventListener('touchmove', e => {
                const touchCurrentX = e.changedTouches[0].screenX;
                touchMoveDistance = Math.abs(touchCurrentX - touchStartX);
            }, { passive: true });

            mainImageContainer.addEventListener('touchend', e => {
                touchEndX = e.changedTouches[0].screenX;
                const touchDuration = Date.now() - touchStartTime;

                // Only trigger swipe if it was quick and significant distance
                if (touchDuration < 300 && touchMoveDistance > 50) {
                    handleSwipe();
                }
            }, { passive: true });
        }

        // Cart Panel Functions
        async function renderCartPanel() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const body = document.getElementById('cart-panel-body');
            const footer = document.getElementById('cart-panel-footer');
            const totalEl = document.getElementById('cart-total');

            if (cart.length === 0) {
                body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
                footer.classList.add('hidden');
                return;
            }

            // Fetch promotions for all products in cart
            const promotionsPromises = cart.map(async item => {
                try {
                    const response = await fetch(`<?php echo url('/api/?endpoint=get-promotion'); ?>&product_id=${item.product_id}`);
                    const data = await response.json();
                    return { productId: item.product_id, promotion: data.promotion };
                } catch (error) {
                    console.error('Error fetching promotion for product:', item.product_id, error);
                    return { productId: item.product_id, promotion: null };
                }
            });

            const promotionsData = await Promise.all(promotionsPromises);
            const promotionsMap = {};
            promotionsData.forEach(p => {
                promotionsMap[p.productId] = p.promotion;
            });

            const exchangeRate = <?php echo $currency_config['exchange_rate']; ?>;
            let totalARS = 0;
            let totalUSD = 0;
            let allProductsUSD = true;
            let html = '';
            let validCart = [];

            cart.forEach(item => {
                const product = products.find(p => p.id === item.product_id);

                if (!product) {
                    console.warn('Product not found:', item.product_id);
                    return;
                }

                // Add to valid cart
                validCart.push(item);

                let priceARS = parseFloat(product.price_ars) || 0;
                let priceUSD = parseFloat(product.price_usd) || 0;

                // Check for promotion
                const promotion = promotionsMap[item.product_id];
                let hasPromotion = false;
                let originalPriceARS = priceARS;
                let originalPriceUSD = priceUSD;

                if (promotion) {
                    hasPromotion = true;
                    if (promotion.type === 'percentage') {
                        priceARS = priceARS - (priceARS * promotion.value / 100);
                        priceUSD = priceUSD - (priceUSD * promotion.value / 100);
                    } else {
                        // Fixed discount - apply in both currencies
                        priceARS = Math.max(0, priceARS - promotion.value);
                        priceUSD = Math.max(0, priceUSD - (promotion.value / exchangeRate));
                    }
                }

                let itemPriceARS = 0;
                let itemPriceUSD = 0;
                let displayPrice = '';

                if (priceUSD > 0 && originalPriceARS === 0) {
                    // Producto solo en USD - mostrar USD con ARS entre paréntesis
                    itemPriceUSD = priceUSD;
                    itemPriceARS = priceUSD * exchangeRate;

                    if (hasPromotion) {
                        displayPrice = `
                            <div class="price-block">
                                <div class="price-promo-badge">🎉 -${promotion.value}${promotion.type === 'percentage' ? '%' : ' USD'}</div>
                                <div>
                                    <span class="price-original">U$D ${originalPriceUSD.toFixed(2)}</span>
                                    <span class="price-discounted">U$D ${priceUSD.toFixed(2)}</span>
                                </div>
                                <div class="price-secondary">($ ${itemPriceARS.toFixed(2)} ARS)</div>
                            </div>
                        `;
                    } else {
                        displayPrice = `
                            <div class="price-container">
                                <div>U$D ${priceUSD.toFixed(2)}</div>
                                <div class="price-secondary">($ ${itemPriceARS.toFixed(2)} ARS)</div>
                            </div>
                        `;
                    }
                } else if (priceARS > 0 && originalPriceUSD === 0) {
                    // Producto solo en ARS
                    allProductsUSD = false;
                    itemPriceARS = priceARS;

                    if (hasPromotion) {
                        displayPrice = `
                            <div class="price-block">
                                <div class="price-promo-badge">🎉 -${promotion.value}${promotion.type === 'percentage' ? '%' : ' ARS'}</div>
                                <div>
                                    <span class="price-original">$ ${originalPriceARS.toFixed(2)}</span>
                                    <span class="price-discounted">$ ${priceARS.toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    } else {
                        displayPrice = '$ ' + priceARS.toFixed(2);
                    }
                } else if (priceARS > 0 && priceUSD > 0) {
                    // Producto con ambos precios - usar ARS
                    allProductsUSD = false;
                    itemPriceARS = priceARS;

                    if (hasPromotion) {
                        displayPrice = `
                            <div class="price-block">
                                <div class="price-promo-badge">🎉 -${promotion.value}${promotion.type === 'percentage' ? '%' : ' ARS'}</div>
                                <div>
                                    <span class="price-original">$ ${originalPriceARS.toFixed(2)}</span>
                                    <span class="price-discounted">$ ${priceARS.toFixed(2)}</span>
                                </div>
                            </div>
                        `;
                    } else {
                        displayPrice = '$ ' + priceARS.toFixed(2);
                    }
                }

                totalARS += itemPriceARS * item.quantity;
                totalUSD += itemPriceUSD * item.quantity;

                html += `
                    <div class="cart-item">
                        <img src="${product.thumbnail || ''}" class="cart-item-image" alt="${product.name}">
                        <div class="cart-item-details">
                            <div class="cart-item-name">${product.name}</div>
                            <div class="cart-item-price">${displayPrice}</div>
                            <div class="cart-item-quantity">
                                <button class="qty-btn" data-action="updateQuantity" data-product-id="${product.id}" data-delta="-1">-</button>
                                <span>${item.quantity}</span>
                                <button class="qty-btn" data-action="updateQuantity" data-product-id="${product.id}" data-delta="1">+</button>
                                <button class="cart-item-remove" data-action="removeFromCart" data-product-id="${product.id}">Eliminar</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Clean invalid items from localStorage
            if (validCart.length !== cart.length) {
                ShopUtils.saveCart(validCart);
                updateCartCount();
            }

            body.innerHTML = html || '<div class="cart-empty">Tu carrito está vacío</div>';

            if (allProductsUSD && totalUSD > 0) {
                totalEl.textContent = 'U$D ' + totalUSD.toFixed(2);
            } else {
                totalEl.textContent = '$' + totalARS.toFixed(2);
            }

            footer.classList.toggle('hidden', validCart.length === 0);
        }


        async function goToCheckout() {
            window.location.href = '<?php echo url('/carrito'); ?>';
        }

        // Buy Now function
        async function buyNow(productId) {
            const input = document.getElementById('quantity-input');
            const quantity = parseInt(input.value);

            let cart = JSON.parse(localStorage.getItem('cart') || '[]');

            // Check if product already in cart
            const existingItem = cart.find(item => (item.product_id || item.id) === productId);

            if (existingItem) {
                existingItem.quantity += quantity;
            } else {
                cart.push({
                    product_id: productId,
                    quantity: quantity,
                    added_at: new Date().toISOString()
                });
            }

            ShopUtils.saveCart(cart);

            // Sync cart to session before redirecting to checkout
            try {
                const response = await fetch('<?php echo url('/api/?endpoint=sync-cart'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        cart: cart,
                        coupon_code: null
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to sync cart');
                }

                // Redirect directly to checkout
                window.location.href = '<?php echo url('/checkout'); ?>';
            } catch (error) {
                console.error('Error syncing cart:', error);
                // Fallback: redirect anyway
                window.location.href = '<?php echo url('/checkout'); ?>';
            }
        }

        // === Wrappers for Event Delegation System ===
        // These wrap existing functions to be compatible with data-action attributes
        (function() {
            const _changeImage = changeImage;
            window.changeImage = function(eventOrDir, element, params) {
                const dir = params?.direction || (typeof eventOrDir === 'number' ? eventOrDir : parseInt(eventOrDir) || 1);
                if (params?.stopPropagation && eventOrDir?.stopPropagation) eventOrDir.stopPropagation();
                return _changeImage(parseInt(dir));
            };

            const _selectImage = selectImage;
            window.selectImage = function(eventOrIdx, element, params) {
                const idx = params?.index !== undefined ? parseInt(params.index) : (typeof eventOrIdx === 'number' ? eventOrIdx : 0);
                return _selectImage(idx);
            };

            // Shared module wrappers
            window.toggleFavorite = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return ShopFavorites.toggleFavorite(id);
            };

            window.updateQuantity = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                const delta = params?.delta ? parseInt(params.delta) : (typeof arguments[1] === 'number' ? arguments[1] : 0);
                if (id) return ShopCart.updateQuantity(id, delta);
            };

            window.removeFromCart = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return ShopCart.removeFromCart(id);
            };

            window.closeCartPanel = function(event, element, params) {
                return ShopCart.closeCartPanel();
            };

            window.openFavoritesPanel = function(event, element, params) {
                if (event && event.preventDefault) event.preventDefault();
                return ShopFavorites.openFavoritesPanel();
            };

            window.closeFavoritesPanel = function(event, element, params) {
                return ShopFavorites.closeFavoritesPanel();
            };

            window.removeFromFavorites = function(eventOrId, element, params) {
                if (params?.stopPropagation && eventOrId?.stopPropagation) eventOrId.stopPropagation();
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return ShopFavorites.removeFromFavorites(id);
            };

            // Page-specific function wrappers
            const _decreaseQuantity = decreaseQuantity;
            window.decreaseQuantity = function(event, element, params) {
                return _decreaseQuantity();
            };

            const _increaseQuantity = increaseQuantity;
            window.increaseQuantity = function(event, element, params) {
                return _increaseQuantity();
            };

            const _addToCartWithQuantity = addToCartWithQuantity;
            window.addToCartWithQuantity = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return _addToCartWithQuantity(id);
            };

            const _buyNow = buyNow;
            window.buyNow = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return _buyNow(id);
            };

            const _copyLink = copyLink;
            window.copyLink = function(event, element, params) {
                return _copyLink();
            };

            const _closeLightbox = closeLightbox;
            window.closeLightbox = function(event, element, params) {
                return _closeLightbox(event);
            };

            const _goToCheckout = goToCheckout;
            window.goToCheckout = function(event, element, params) {
                return _goToCheckout();
            };

            const _goToFavoritesPage = goToFavoritesPage;
            window.goToFavoritesPage = function(event, element, params) {
                return _goToFavoritesPage();
            };

            const _goToProduct = function(event, element, params) {
                const url = params?.url;
                if (url) window.location.href = url;
            };
            window.goToProduct = _goToProduct;

            // iOS Safari fix: Direct event listener for add to cart button
            // Safari iOS has issues with event delegation + async functions
            const addToCartBtn = document.getElementById('add-to-cart-btn');
            if (addToCartBtn) {
                // Click event (desktop and fallback)
                addToCartBtn.addEventListener('click', async function(e) {
                    if (this.disabled) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const productId = this.getAttribute('data-product-id');
                    if (productId) {
                        await addToCartWithQuantity(productId);
                    }
                }, { passive: false });

                // Touchend event for better iOS responsiveness
                addToCartBtn.addEventListener('touchend', async function(e) {
                    if (this.disabled) return;

                    e.preventDefault();
                    e.stopPropagation();

                    const productId = this.getAttribute('data-product-id');
                    if (productId) {
                        await addToCartWithQuantity(productId);
                    }
                }, { passive: false });
            }
        })();
    </script>

    <!-- Cart Validator -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/cart-validator.js'); ?>"></script>

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

    <!-- Mobile Menu -->
    <?php include APP_PATH . '/includes/mobile-menu.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/mobile-menu.js'); ?>"></script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- Auto-update Exchange Rate -->
    <?php include APP_PATH . '/includes/auto-update-exchange.php'; ?>
</body>
</html>
