<?php
/**
 * Home Page - Public Site
 *
 * Cargado por: public_html/index.php vía Router
 * Ruta: GET /
 */

// Bootstrap ya maneja: APP_ENTRY_POINT, includes, session, security headers

// Include promotions
require_once APP_PATH . '/includes/promotions.php';

// Get all active products
$products = get_all_products(true);

// Filter out products that are out of stock and should be hidden
$products = array_filter($products, function($product) {
    // Show product if it has stock OR if hide_when_out_of_stock is not set/false
    $hide_when_no_stock = $product['hide_when_out_of_stock'] ?? false;
    if ($hide_when_no_stock && $product['stock'] <= 0) {
        return false; // Hide this product
    }
    return true; // Show this product
});

// Reindex array to maintain order after filtering
$products = array_values($products);

// Get site configuration
$site_config = read_json(APP_PATH . '/config/site.json');
$hero_config = read_json(APP_PATH . '/config/hero.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');
$currency_config = read_json(APP_PATH . '/config/currency.json');
$products_heading_config = read_json(APP_PATH . '/config/products-heading.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_config['site_name']); ?> - E-commerce</title>
    <meta name="description" content="<?php echo htmlspecialchars($site_config['site_description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site_config['site_keywords']); ?>">

    <!-- SEO and Social Media Meta Tags -->
    <?php render_meta_tags($site_config); ?>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Carousel CSS -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/carousel.css'); ?>">

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <!-- Carousel Section (priority over hero) -->
    <?php
    $carousel_config = read_json(APP_PATH . '/config/carousel.json');
    $show_carousel = ($carousel_config['enabled'] ?? false) && !empty($carousel_config['slides']);
    $show_hero = ($hero_config['enabled'] ?? false) && !$show_carousel; // Hero only if carousel is disabled
    ?>

    <?php if ($show_hero): ?>
    <!-- Hero Section (only if carousel is disabled) -->
    <section class="hero <?php echo !empty($hero_config['image']) ? 'has-image' : ''; ?>"
             <?php if (!empty($hero_config['image'])): ?>
                style="background-image: url('<?php echo htmlspecialchars($hero_config['image']); ?>');"
             <?php elseif (!empty($hero_config['background_color'])): ?>
                style="--hero-bg: <?php echo htmlspecialchars($hero_config['background_color']); ?>; background: var(--hero-bg);"
             <?php endif; ?>>
        <h1><?php echo htmlspecialchars($hero_config['title']); ?></h1>
        <?php if (!empty($hero_config['subtitle'])): ?>
            <p><?php echo htmlspecialchars($hero_config['subtitle']); ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Carousel Section -->
    <?php include APP_PATH . '/includes/carousel.php'; ?>

    <!-- Products Section -->
    <div class="container">
        <?php if ($products_heading_config['enabled'] ?? true): ?>
            <?php if (!empty($products_heading_config['heading'])): ?>
                <h2 class="section-title"><?php echo htmlspecialchars($products_heading_config['heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($products_heading_config['subheading'])): ?>
                <p class="section-subheading">
                    <?php echo htmlspecialchars($products_heading_config['subheading']); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <h3>No hay productos disponibles</h3>
                <p>Pronto agregaremos productos a nuestra tienda.</p>
                <br>
                <a href="<?php echo url('/admin/login.php'); ?>" class="btn">Ir al Admin Panel</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php
                require_once APP_PATH . '/includes/frontend/product-card.php';
                foreach ($products as $product):
                    render_product_card($product, ['currency' => $selected_currency]);
                endforeach;
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- WhatsApp Button -->
    <?php if ($site_config['whatsapp']['enabled']): ?>
    <?php
    // Priorizar custom_link si está configurado, sino usar number
    if (!empty($site_config['whatsapp']['custom_link'])) {
        $whatsapp_url = $site_config['whatsapp']['custom_link'];
    } else {
        $whatsapp_number = preg_replace('/[^0-9]/', '', $site_config['whatsapp']['number']);
        $whatsapp_message = urlencode($site_config['whatsapp']['message']);
        $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_message;
    }
    ?>
    <a href="<?php echo htmlspecialchars($whatsapp_url); ?>"
       class="whatsapp-button"
       target="_blank"
       title="Contáctanos por WhatsApp">
        <svg viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>
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

    <!-- Shared JS Modules (MUST load BEFORE main script) -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>

    <!-- Shared JS Modules (MUST load BEFORE main script) -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>

    <script nonce="<?= csp_nonce() ?>">
        // Products data for cart panel (global for shared modules)
        window.productUrlBase = '<?php echo url('/producto/'); ?>';
        window.products = <?php
            // Deep clone products to avoid reference issues
            $products_for_js = json_decode(json_encode($products), true);

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
        const exchangeRate = <?php echo $currency_config['exchange_rate']; ?>;
        const API_GET_PRODUCTS = '<?php echo url('/api/get_products.php'); ?>';


        async function renderCartPanel() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const body = document.getElementById('cart-panel-body');
            const footer = document.getElementById('cart-panel-footer');
            const totalEl = document.getElementById('cart-total');

            console.log('Cart from localStorage:', cart);
            console.log('Products array:', products);

            if (cart.length === 0) {
                body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
                footer.classList.add('hidden');
                return;
            }

            // Fetch promotions for all products in cart
            const promotionsPromises = cart.map(async item => {
                try {
                    const response = await fetch(`<?php echo url('/api/get_promotion.php'); ?>?product_id=${item.product_id}`);
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

            let totalARS = 0;
            let totalUSD = 0;
            let allProductsUSD = true;
            let html = '';
            let validCart = [];

            cart.forEach(item => {
                const product = products.find(p => p.id === item.product_id);
                console.log('Looking for product:', item.product_id, 'Found:', product);
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

                // Determinar si el producto está en USD o ARS
                let itemPriceARS = 0;
                let itemPriceUSD = 0;
                let displayPrice = '';
                let isUSDProduct = false;

                if (priceUSD > 0 && originalPriceARS === 0) {
                    // Producto solo en USD - mostrar USD con ARS entre paréntesis
                    isUSDProduct = true;
                    itemPriceUSD = priceUSD;
                    itemPriceARS = priceUSD * exchangeRate;

                    if (hasPromotion) {
                        displayPrice = `
                            <div class="price-block">
                                <div class="price-promo-badge">🎉 -${promotion.value}${promotion.type === 'percentage' ? '%' : ' USD'}</div>
                                <div>
                                    <span class="price-strikethrough">U$D ${originalPriceUSD.toFixed(2)}</span>
                                    <span class="price-current">U$D ${priceUSD.toFixed(2)}</span>
                                </div>
                                <div class="price-conversion">($ ${itemPriceARS.toFixed(2)} ARS)</div>
                            </div>
                        `;
                    } else {
                        displayPrice = `
                            <div class="price-block">
                                <div>U$D ${priceUSD.toFixed(2)}</div>
                                <div class="price-conversion">($ ${itemPriceARS.toFixed(2)} ARS)</div>
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
                                    <span class="price-strikethrough">$ ${originalPriceARS.toFixed(2)}</span>
                                    <span class="price-current">$ ${priceARS.toFixed(2)}</span>
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
                                    <span class="price-strikethrough">$ ${originalPriceARS.toFixed(2)}</span>
                                    <span class="price-current">$ ${priceARS.toFixed(2)}</span>
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
                console.warn('Cleaning invalid items from cart');
                localStorage.setItem('cart', JSON.stringify(validCart));
                ShopCart.updateCartBadge();
            }

            // Check if we have any valid items to display
            if (html === '' || validCart.length === 0) {
                body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
                footer.classList.add('hidden');
                return;
            }

            body.innerHTML = html;

            // Mostrar total en USD si todos los productos están en USD, sino en ARS
            if (allProductsUSD && totalUSD > 0) {
                totalEl.textContent = 'U$D ' + totalUSD.toFixed(2);
            } else {
                totalEl.textContent = '$' + totalARS.toFixed(2);
            }

            footer.classList.remove('hidden');
        }

        // Save cart with timestamp


        // Check if cart has expired (after 4 hours of inactivity)


        // Go to checkout - sync cart first
        async function goToCheckout() {
            try {
                console.log('[goToCheckout] Reading cart from localStorage...');
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                console.log('[goToCheckout] Cart contents:', cart);

                if (cart.length === 0) {
                    console.warn('[goToCheckout] Cart is empty!');
                    ShopUtils.showToast('El carrito está vacío', 'error');
                    return;
                }

                // Sync to session
                const response = await fetch('<?php echo url('/api/sync_cart.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ cart: cart })
                });

                if (!response.ok) {
                    throw new Error('Failed to sync cart');
                }

                const result = await response.json();

                if (result.success) {
                    // Redirect to cart page (full view)
                    window.location.href = '<?php echo url('/carrito'); ?>';
                } else {
                    ShopUtils.showToast('Error al procesar el carrito', 'error');
                }
            } catch (error) {
                console.error('Error syncing cart:', error);
                ShopUtils.showToast('Error al procesar el carrito', 'error');
            }
        }

        // Cookie helpers for favorites



        // Toggle favorite


        // Check if products are in favorites on page load
        function updateFavoriteButtons() {
            const favorites = ShopFavorites.getFavorites();
            favorites.forEach(productId => {
                const heartBtn = document.getElementById('favorite-btn-' + productId);
                const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;
                if (heartBtn) heartBtn.classList.add('active');
                if (heartIcon) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                }
            });
        }

        // Show toast notification


        // Update badge and render cart on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Check if cart has expired
            ShopUtils.checkCartExpiration();

            ShopCart.updateCartBadge();
            ShopCart.renderCartPanel();
            ShopFavorites.updateFavoritesCount();
            updateFavoriteButtons();
            ShopFavorites.renderFavoritesPanel(products, '<?php echo url('/producto/'); ?>');
        });

        // ===== Favorites Panel Functions =====


        function goToFavoritesPage() {
            window.location.href = '<?php echo url('/favoritos'); ?>';
        }


        // === Wrappers for Event Delegation System ===
        // NOTE: These wrappers adapt the shared modules to the event delegation system
        (function() {
            window.openCartPanel = function(event, element, params) {
                if (event && event.preventDefault) event.preventDefault();
                return ShopCart.openCartPanel();
            };

            window.goToProduct = function(event, element, params) {
                const url = params?.url;
                if (url) window.location.href = url;
            };

            window.toggleFavorite = function(eventOrId, element, params) {
                if (params?.stopPropagation && eventOrId?.stopPropagation) eventOrId.stopPropagation();
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return ShopFavorites.toggleFavorite(id);
            };

            window.addToCart = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return ShopCart.addToCart(id, eventOrId);
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

            const _goToCheckout = goToCheckout; // Save reference to local function
            window.goToCheckout = function(event, element, params) {
                return _goToCheckout(); // Call local function via saved reference
            };

            // Favorites Panel Wrappers
            window.openFavoritesPanel = function(event, element, params) {
                if (event && event.preventDefault) event.preventDefault();
                return ShopFavorites.openFavoritesPanel();
            };

            window.closeFavoritesPanel = function(event, element, params) {
                return ShopFavorites.closeFavoritesPanel();
            };

            const _goToFavoritesPage = goToFavoritesPage; // Save reference to local function
            window.goToFavoritesPage = function(event, element, params) {
                return _goToFavoritesPage(); // Call local function via saved reference
            };

            window.removeFromFavorites = function(eventOrId, element, params) {
                if (params?.stopPropagation && eventOrId?.stopPropagation) eventOrId.stopPropagation();
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return ShopFavorites.removeFromFavorites(id);
            };
        })();
    </script>

    <!-- Cart Validator -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/cart-validator.js'); ?>"></script>

    <!-- Carousel JS -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/carousel.js'); ?>"></script>

    <!-- Mobile Menu -->
    <?php include APP_PATH . '/includes/mobile-menu.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/mobile-menu.js'); ?>"></script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Auto-update Exchange Rate -->
    <?php include APP_PATH . '/includes/auto-update-exchange.php'; ?>
</body>
</html>
