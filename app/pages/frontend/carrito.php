<?php
/**
 * Shopping Cart Page
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

// Include promotions system
require_once APP_PATH . '/includes/promotions.php';

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Handle currency change
if (isset($_POST['change_currency'])) {
    $new_currency = $_POST['currency'] ?? 'ARS';
    if (in_array($new_currency, ['ARS', 'USD'])) {
        $_SESSION['currency'] = $new_currency;
        $selected_currency = $new_currency;
    }
}

// Check for messages
$cart_message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'expired') {
    $cart_message = 'Tu carrito ha expirado por inactividad (4 horas). Por favor, agrega los productos nuevamente.';
}

// If coming from expired checkout, clean up checkout session
if (isset($_GET['msg']) && $_GET['msg'] === 'checkout_expired') {
    // Clean checkout session to avoid double-click issue
    unset($_SESSION['checkout_start_time']);
    unset($_SESSION['checkout_id']);
    $cart_message = 'Tu sesión de checkout ha expirado. Por favor, continúa con el pago nuevamente.';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- SEO and Social Media Meta Tags -->
    <?php render_meta_tags($site_config); ?>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <?php if ($cart_message): ?>
    <div class="alert alert-warning">
        <strong>⚠️ Aviso:</strong> <?php echo htmlspecialchars($cart_message); ?>
    </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <?php
    require_once APP_PATH . '/includes/frontend/breadcrumb.php';
    render_breadcrumb([
        ['label' => 'Inicio', 'url' => url('/')],
        ['label' => 'Carrito', 'url' => null]
    ]);
    ?>

    <!-- Main Content -->
    <div class="container cart-page-container">

        <div class="cart-layout">
            <!-- Cart Items Section -->
            <div class="cart-items-section">
                <div class="cart-items-header">
                    <div class="items-title-with-icon">
                        <svg class="cart-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <h2 class="items-title">Productos</h2>
                    </div>
                    <span class="items-count" id="itemsCount">0 productos</span>
                </div>

                <div id="cartItemsContainer" class="cart-items-list cart-loading">
                    <div class="empty-cart-state">
                        <div class="empty-cart-icon">
                            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                        </div>
                        <h2 class="empty-cart-title">Tu carrito está vacío</h2>
                        <p class="empty-cart-message">¡Descubre nuestros productos y comienza a comprar!</p>
                        <a href="<?php echo url('/'); ?>" class="btn-shop-now">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            Explorar Tienda
                        </a>
                    </div>
                </div>
            </div>

            <!-- Summary Sidebar -->
            <div class="cart-summary-sidebar hidden" id="cartSummary">
                <div class="summary-card">
                    <h2 class="summary-title">Resumen del Pedido</h2>

                    <!-- Currency Toggle -->
                    <div class="currency-selector" id="currency-toggle">
                        <label class="currency-label">Moneda:</label>
                        <div class="currency-toggle-buttons">
                            <button class="currency-btn" data-currency="ARS" data-action="switchDisplayCurrency" data-curr="ARS">
                                <span class="currency-symbol">$</span>
                                <span class="currency-code">ARS</span>
                            </button>
                            <button class="currency-btn" data-currency="USD" data-action="switchDisplayCurrency" data-curr="USD">
                                <span class="currency-symbol">U$D</span>
                                <span class="currency-code">USD</span>
                            </button>
                        </div>
                    </div>

                    <!-- Coupon Section -->
                    <div class="summary-coupon-section">
                        <?php
                        require_once APP_PATH . '/includes/frontend/coupon-form.php';
                        render_coupon_form();
                        ?>
                    </div>

                    <!-- Totals -->
                    <div class="summary-totals-section">
                        <div id="summaryTotals"></div>
                    </div>

                    <!-- Checkout Button -->
                    <div class="summary-actions">
                        <button class="btn-checkout" id="checkoutBtn" data-action="goToCheckout">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                            </svg>
                            Continuar con el Pago
                        </button>
                        <a href="<?php echo url('/'); ?>" class="btn-continue-shopping">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 12H5M12 19l-7-7 7-7"></path>
                            </svg>
                            Seguir Comprando
                        </a>
                    </div>

                    <!-- Security Badge -->
                    <div class="summary-security">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>Compra 100% segura</span>
                    </div>
                </div>
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
        <?php
        // Obtener base_path de configuración
        $app_config = require APP_PATH . '/config/config.php';
        $base_path = rtrim($app_config['base_path'] ?? '', '/');
        ?>
        window.BASE_PATH = '<?php echo htmlspecialchars($base_path); ?>';
        const CURRENCY = '<?php echo $selected_currency; ?>';
        const EXCHANGE_RATE = <?php echo $currency_config['exchange_rate']; ?>;
        const BASE_URL = '<?php echo url('/'); ?>';
        let displayCurrency = 'ARS'; // Currency for display purposes only

        let cartData = {
            items: [],
            coupon: null,
            currency: CURRENCY
        };

        // Store current products for validation
        let currentProducts = [];

        // Check if cart has expired (after 4 hours of inactivity)
        function checkCartExpiration() {
            const EXPIRATION_TIME = 4 * 60 * 60 * 1000; // 4 hours in milliseconds
            const timestamp = localStorage.getItem('cart_timestamp');

            if (timestamp) {
                const elapsed = Date.now() - parseInt(timestamp);
                if (elapsed > EXPIRATION_TIME) {
                    // Cart expired, clear it
                    localStorage.removeItem('cart');
                    localStorage.removeItem('cart_timestamp');
                    localStorage.removeItem('applied_coupon');
                    console.log('Cart expired and cleared after 4 hours of inactivity');
                    return true;
                }
            }
            return false;
        }

        // Check cart expiration on page load
        if (checkCartExpiration()) {
            window.location.href = '<?php echo url('/carrito?msg=expired'); ?>';
        }

        // Format product price intelligently
        // Format currency value with symbol
        function formatCurrency(amount, currency) {
            const symbol = currency === 'USD' ? 'U$D' : '$';
            if (currency === 'USD') {
                return symbol + ' ' + amount.toFixed(2).replace('.', ',');
            } else {
                return symbol + ' ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }
        }


        // Determine if all products in cart are USD-only
        function areAllProductsUSD(products, cart) {
            for (const item of cart) {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);
                if (!product) continue;

                const priceArs = parseFloat(product.price_ars || 0);
                if (priceArs > 0) {
                    return false; // Found a product with ARS price
                }
            }
            return true; // All products are USD-only
        }

        // Get product price value for calculations
        function getProductPrice(product, currency) {
            const priceArs = parseFloat(product.price_ars || 0);
            const priceUsd = parseFloat(product.price_usd || 0);

            if (currency === 'USD') {
                // All products USD, use USD price
                return priceUsd;
            } else {
                // Mixed or all ARS
                if (priceArs > 0) {
                    return priceArs;
                } else {
                    // USD product in mixed cart, convert to ARS
                    return priceUsd * EXCHANGE_RATE;
                }
            }
        }

        // Load cart from localStorage
        function loadCart() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const coupon = JSON.parse(localStorage.getItem('applied_coupon') || 'null');

            console.log('Cart loaded from localStorage:', cart);

            cartData.items = cart;
            cartData.coupon = coupon;
            cartData.currency = CURRENCY;

            if (cart.length === 0) {
                const container = document.getElementById('cartItemsContainer');
                container.innerHTML = `
                    <div class="empty-cart-state">
                        <div class="empty-cart-icon">
                            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                        </div>
                        <h2 class="empty-cart-title">Tu carrito está vacío</h2>
                        <p class="empty-cart-message">¡Descubre nuestros productos y comienza a comprar!</p>
                        <a href="${BASE_URL}" class="btn-shop-now">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            Explorar Tienda
                        </a>
                    </div>
                `;
                container.classList.remove('cart-loading');
                document.getElementById('cartSummary').classList.add('hidden');
                document.getElementById('itemsCount').textContent = '0 productos';
            } else {
                fetchCartProducts();
            }

            updateCartCount();
        }

        // Fetch product details for cart items
        async function fetchCartProducts() {
            const productIds = cartData.items.map(item => item.product_id || item.id);

            console.log('Fetching products for IDs:', productIds);

            try {
                const response = await fetch('<?php echo url('/api/?endpoint=get-products'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ product_ids: productIds })
                });

                console.log('API Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const products = await response.json();
                console.log('Products received from API:', products);

                // Store products globally for reuse
                currentProducts = products;

                // Validate cart items and remove unavailable products
                validateAndCleanCart(products);

                renderCart(products);
            } catch (error) {
                console.error('Error fetching products:', error);
                ShopUtils.showToast('Error al cargar los productos del carrito', 'error');
                // Fallback: render with limited data
                renderCart([]);
            }
        }

        // Validate cart items and remove unavailable products
        function validateAndCleanCart(products) {
            const removedItems = [];
            const originalLength = cartData.items.length;

            // Filter out items that are not available
            cartData.items = cartData.items.filter(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);

                // Check if product exists, is active, and has stock
                if (!product) {
                    removedItems.push({ name: 'Producto no encontrado', reason: 'eliminado' });
                    return false;
                }

                if (!product.active) {
                    removedItems.push({ name: product.name, reason: 'no disponible' });
                    return false;
                }

                if (product.stock <= 0) {
                    removedItems.push({ name: product.name, reason: 'sin stock' });
                    return false;
                }

                // Adjust quantity if exceeds available stock
                if (item.quantity > product.stock) {
                    item.quantity = product.stock;
                }

                return true;
            });

            // If items were removed, save cart and show notification
            if (removedItems.length > 0) {
                ShopUtils.saveCart(cartData.items);

                let message = 'Se eliminaron productos del carrito:\n';
                removedItems.forEach(item => {
                    message += `• ${item.name} (${item.reason})\n`;
                });

                // Show all removed items in a single toast
                setTimeout(() => {
                    ShopUtils.showToast(removedItems.map(i => `${i.name}: ${i.reason}`).join(', '), 'error');
                }, 500);
            }
        }

        // Render cart items
        async function renderCart(products) {
            if (cartData.items.length === 0) {
                loadCart();
                return;
            }

            // Store products globally for validation
            currentProducts = products;

            console.log('Rendering cart with products:', products);

            // Determine currency based on cart contents
            const allUSD = areAllProductsUSD(products, cartData.items);
            const effectiveCurrency = allUSD ? 'USD' : 'ARS';

            // Initialize display currency to match cart currency ONLY on first load
            // Don't overwrite if user manually changed it
            if (!displayCurrency) {
                displayCurrency = effectiveCurrency;
            }

            // Update currency button states with current displayCurrency
            updateCurrencyButtons(displayCurrency);

            let html = '';
            let validItems = [];

            // Fetch promotions for all products
            const promotionsPromises = cartData.items.map(async item => {
                const productId = item.product_id || item.id;
                try {
                    const response = await fetch(`<?php echo url('/api/?endpoint=get-promotion'); ?>&product_id=${productId}`);
                    const data = await response.json();
                    return { productId, promotion: data.promotion };
                } catch (error) {
                    console.error('Error fetching promotion for product:', productId, error);
                    return { productId, promotion: null };
                }
            });

            const promotionsData = await Promise.all(promotionsPromises);
            const promotionsMap = {};
            promotionsData.forEach(p => {
                promotionsMap[p.productId] = p.promotion;
            });

            cartData.items.forEach(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);

                if (!product) {
                    console.warn('Product not found:', productId);
                    return; // Skip invalid products
                }

                validItems.push(item);

                console.log('Product:', product.name, 'Thumbnail:', product.thumbnail, 'Images:', product.images);

                // Use displayCurrency for showing prices (user's preference)
                const price = getProductPrice(product, displayCurrency);
                const stockWarning = item.quantity > product.stock;
                const promotion = promotionsMap[productId];

                // Calculate discounted price if promotion exists
                let displayPrice = price;
                let hasPromotion = false;
                let hasCoupon = false;

                if (promotion) {
                    hasPromotion = true;
                    if (promotion.type === 'percentage') {
                        displayPrice = price - (price * promotion.value / 100);
                    } else {
                        displayPrice = price - promotion.value;
                    }
                    displayPrice = Math.max(0, displayPrice);
                } else if (cartData.coupon) {
                    // Item has NO promotion, so coupon applies
                    hasCoupon = true;
                    if (cartData.coupon.type === 'percentage') {
                        displayPrice = price - (price * cartData.coupon.value / 100);
                    } else {
                        // For fixed coupon, calculate proportional discount per item
                        // This is just for display - actual calculation is in calculateTotals
                        displayPrice = price - (cartData.coupon.value / item.quantity);
                    }
                    displayPrice = Math.max(0, displayPrice);
                }

                // Use thumbnail or first image
                // Handle both string images array and object images array
                let imageUrl = product.thumbnail;
                if (!imageUrl && product.images && product.images.length > 0) {
                    // Check if images is array of objects or strings
                    if (typeof product.images[0] === 'object' && product.images[0].url) {
                        imageUrl = product.images[0].url;
                    } else if (typeof product.images[0] === 'string') {
                        imageUrl = product.images[0];
                    }
                }

                let imageHtml = '';
                if (imageUrl) {
                    imageHtml = '<img src="' + imageUrl + '" alt="' + product.name.replace(/"/g, '&quot;') + '">';
                } else {
                    imageHtml = '<div class="no-image-placeholder">Sin imagen</div>';
                }

                html += `
                    <div class="cart-item-card">
                        <div class="cart-item-image">
                            ${imageHtml}
                        </div>
                        <div class="cart-item-info">
                            <div class="cart-item-header">
                                <h3 class="cart-item-name">${product.name}</h3>
                                <button class="cart-item-remove" data-action="removeItem" data-product-id="${productId}" title="Eliminar producto">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M18 6L6 18M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>

                            ${promotion ? `
                                <div class="cart-item-badge badge-promotion">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                    </svg>
                                    Promoción: -${promotion.value}${promotion.type === 'percentage' ? '%' : ' ' + displayCurrency}
                                </div>
                            ` : ''}
                            ${hasCoupon ? `
                                <div class="cart-item-badge badge-coupon">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 10v1a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"></path>
                                        <path d="M6 14v7"></path>
                                        <path d="M10 14v7"></path>
                                        <path d="M14 14v7"></path>
                                        <path d="M18 14v7"></path>
                                    </svg>
                                    Cupón: -${cartData.coupon.value}${cartData.coupon.type === 'percentage' ? '%' : ' ' + displayCurrency}
                                </div>
                            ` : ''}

                            <div class="cart-item-pricing">
                                ${hasPromotion || hasCoupon ? `
                                    <div class="cart-item-price-group">
                                        <span class="cart-item-price-original">${formatCurrency(price, displayCurrency)}</span>
                                        <span class="cart-item-price-discounted ${hasPromotion ? 'price-promotion' : 'price-coupon'}">
                                            ${formatCurrency(displayPrice, displayCurrency)}
                                        </span>
                                    </div>
                                ` : `
                                    <div class="cart-item-price-single">
                                        ${ShopUtils.formatProductPrice(product, displayCurrency)}
                                    </div>
                                `}
                                <span class="cart-item-quantity-label">× ${item.quantity}</span>
                            </div>

                            <div class="cart-item-footer">
                                <div class="cart-item-stock ${stockWarning ? 'stock-warning' : 'stock-ok'}">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        ${stockWarning ?
                                            '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>' :
                                            '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>'
                                        }
                                    </svg>
                                    ${stockWarning ?
                                        'Stock insuficiente (' + product.stock + ' disponibles)' :
                                        product.stock + ' en stock'
                                    }
                                </div>

                                <div class="cart-item-quantity-control">
                                    <button class="quantity-btn-minus" data-action="updateQuantity" data-product-id="${productId}" data-delta="-1" aria-label="Disminuir cantidad">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button>
                                    <input type="number" class="quantity-input" value="${item.quantity}" readonly aria-label="Cantidad">
                                    <button class="quantity-btn-plus" data-action="updateQuantity" data-product-id="${productId}" data-delta="1"
                                        ${item.quantity >= product.stock ? 'disabled' : ''} aria-label="Aumentar cantidad">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Clean invalid items from localStorage
            if (validItems.length !== cartData.items.length) {
                console.warn('Cleaning invalid items from cart');
                cartData.items = validItems;
                ShopUtils.saveCart(cartData.items);
            }

            // Check if we have any valid items to display
            if (html === '' || validItems.length === 0) {
                const container = document.getElementById('cartItemsContainer');
                container.innerHTML = `
                    <div class="empty-cart-state">
                        <div class="empty-cart-icon">
                            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                        </div>
                        <h2 class="empty-cart-title">Tu carrito está vacío</h2>
                        <p class="empty-cart-message">¡Descubre nuestros productos y comienza a comprar!</p>
                        <a href="${BASE_URL}" class="btn-shop-now">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            </svg>
                            Explorar Tienda
                        </a>
                    </div>
                `;
                container.classList.remove('cart-loading');
                document.getElementById('cartSummary').classList.add('hidden');
                document.getElementById('itemsCount').textContent = '0 productos';
                updateCartCount();
                return;
            }

            const container = document.getElementById('cartItemsContainer');
            container.innerHTML = html;
            container.classList.remove('cart-loading');
            document.getElementById('cartSummary').classList.remove('hidden');

            // Update items count
            const totalItems = validItems.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('itemsCount').textContent = `${totalItems} ${totalItems === 1 ? 'producto' : 'productos'}`;

            await calculateTotals(products);

            // Check coupon eligibility
            if (cartData.coupon) {
                // Verify if there are still eligible items for the coupon
                if (await hasEligibleItemsForCoupon(products)) {
                    showAppliedCoupon(cartData.coupon);
                } else {
                    // Auto-remove coupon if no eligible items
                    autoRemoveCoupon(cartData.coupon.code);
                }
            } else {
                // Check if there's a removed coupon and eligible items now
                const removedCoupon = JSON.parse(localStorage.getItem('removed_coupon') || 'null');
                if (removedCoupon && await hasEligibleItemsForCoupon(products)) {
                    showReapplyButton(removedCoupon);
                }
            }
        }

        // Update quantity


        // Remove item
        function removeItem(productId) {
            cartData.items = cartData.items.filter(i => (i.product_id || i.id) !== productId);
            ShopUtils.saveCart(cartData.items);
            loadCart();
            ShopUtils.showToast('Producto eliminado del carrito');
        }

        // Save cart to localStorage


        // Calculate totals
        async function calculateTotals(products) {
            let subtotal = 0;
            let promotionDiscount = 0;
            let couponDiscount = 0;

            // Determine currency based on cart contents
            const allUSD = areAllProductsUSD(products, cartData.items);
            const effectiveCurrency = allUSD ? 'USD' : 'ARS';

            // Use current displayCurrency (don't override user's manual selection)
            // Only initialize on first load if not set
            if (!displayCurrency) {
                displayCurrency = effectiveCurrency;
            }
            updateCurrencyButtons(displayCurrency);

            // Fetch promotions for all products
            const promotionsPromises = cartData.items.map(async item => {
                const productId = item.product_id || item.id;
                try {
                    const response = await fetch(`<?php echo url('/api/?endpoint=get-promotion'); ?>&product_id=${productId}`);
                    const data = await response.json();
                    return { productId, promotion: data.promotion };
                } catch (error) {
                    console.error('Error fetching promotion for product:', productId, error);
                    return { productId, promotion: null };
                }
            });

            const promotionsData = await Promise.all(promotionsPromises);
            const promotionsMap = {};
            promotionsData.forEach(p => {
                promotionsMap[p.productId] = p.promotion;
            });

            // Calculate subtotal and promotion discounts
            let subtotalWithoutPromotion = 0; // Items without promotion (eligible for coupon)

            cartData.items.forEach(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);
                if (!product) return;

                // Use displayCurrency for showing prices (user's preference)
                const price = getProductPrice(product, displayCurrency);
                const itemTotal = price * item.quantity;
                subtotal += itemTotal;

                // Calculate promotion discount for this item
                const promotion = promotionsMap[productId];
                if (promotion) {
                    let itemDiscount = 0;
                    if (promotion.type === 'percentage') {
                        itemDiscount = (price * promotion.value / 100) * item.quantity;
                    } else {
                        itemDiscount = promotion.value * item.quantity;
                    }
                    promotionDiscount += itemDiscount;
                } else {
                    // Item has NO promotion, so it's eligible for coupon
                    subtotalWithoutPromotion += itemTotal;
                }
            });

            // Apply coupon ONLY to items without promotion
            if (cartData.coupon && subtotalWithoutPromotion > 0) {
                // Check if minimum purchase requirement is still met
                const minPurchase = cartData.coupon.min_purchase || 0;

                // Convert subtotalWithoutPromotion to ARS if currency is USD
                const subtotalWithoutPromotionARS = effectiveCurrency === 'USD'
                    ? subtotalWithoutPromotion * EXCHANGE_RATE
                    : subtotalWithoutPromotion;

                if (minPurchase > 0 && subtotalWithoutPromotionARS < minPurchase) {
                    // Auto-remove coupon because minimum purchase is no longer met
                    const faltante = minPurchase - subtotalWithoutPromotionARS;
                    autoRemoveCoupon(cartData.coupon.code);
                    ShopUtils.showToast(`❌ El cupón "${cartData.coupon.code}" fue removido porque ya no cumple con el mínimo de compra ($${minPurchase.toFixed(2)}). Te faltan $${faltante.toFixed(2)}.`, 'warning');
                    return; // Exit early, totals will be recalculated without coupon
                }

                if (cartData.coupon.type === 'percentage') {
                    couponDiscount = subtotalWithoutPromotion * (cartData.coupon.value / 100);
                } else {
                    // For fixed discount, apply only up to the subtotal without promotion
                    couponDiscount = Math.min(cartData.coupon.value, subtotalWithoutPromotion);
                }
            }

            const total = subtotal - promotionDiscount - couponDiscount;

            // Calculate in both currencies
            // IMPORTANT: subtotal is already in displayCurrency, not effectiveCurrency
            let subtotalARS, subtotalUSD, couponDiscountARS, couponDiscountUSD, totalARS, totalUSD;

            if (displayCurrency === 'USD') {
                subtotalUSD = subtotal;
                subtotalARS = subtotal * EXCHANGE_RATE;
                couponDiscountUSD = couponDiscount;
                couponDiscountARS = couponDiscount * EXCHANGE_RATE;
                totalUSD = total;
                totalARS = total * EXCHANGE_RATE;
            } else {
                subtotalARS = subtotal;
                subtotalUSD = subtotal / EXCHANGE_RATE;
                couponDiscountARS = couponDiscount;
                couponDiscountUSD = couponDiscount / EXCHANGE_RATE;
                totalARS = total;
                totalUSD = total / EXCHANGE_RATE;
            }

            // Format price based on display currency
            function formatDisplayPrice(ars, usd) {
                const amount = displayCurrency === 'ARS' ? ars : usd;
                const symbol = displayCurrency === 'ARS' ? '$' : 'U$D';
                return `${symbol} ${amount.toFixed(2)}`;
            }

            let html = '';

            if (promotionDiscount > 0) {
                html += `
                    <div class="summary-row promotion">
                        <span>Descuento promoción:</span>
                        <span>-${formatDisplayPrice(promotionDiscount, promotionDiscount / EXCHANGE_RATE)}</span>
                    </div>
                `;
            }

            if (couponDiscount > 0) {
                html += `
                    <div class="summary-row discount">
                        <span>Cupón "${cartData.coupon.code}":</span>
                        <span id="discount-display" data-ars="${couponDiscountARS.toFixed(2)}" data-usd="${couponDiscountUSD.toFixed(2)}">
                            -${formatDisplayPrice(couponDiscountARS, couponDiscountUSD)}
                        </span>
                    </div>
                `;
            }

            html += `
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total-display" data-ars="${totalARS.toFixed(2)}" data-usd="${totalUSD.toFixed(2)}">
                        ${formatDisplayPrice(totalARS, totalUSD)}
                    </span>
                </div>
            `;

            document.getElementById('summaryTotals').innerHTML = html;
        }

        // Check if any cart item has active promotions
        async function checkCartHasPromotions() {
            for (const item of cartData.items) {
                const productId = item.product_id || item.id;
                try {
                    const response = await fetch(`<?php echo url('/api/?endpoint=get-promotion'); ?>&product_id=${productId}`);
                    const data = await response.json();
                    if (data.promotion) {
                        return true; // Found at least one item with promotion
                    }
                } catch (error) {
                    console.error('Error checking promotion for product:', productId, error);
                }
            }
            return false; // No items have promotions
        }

        // Check if there are eligible items for coupon (items without promotion)
        async function hasEligibleItemsForCoupon(products) {
            if (!cartData.items || cartData.items.length === 0) {
                return false;
            }

            // Fetch promotions for all items
            const promotionsPromises = cartData.items.map(async item => {
                const productId = item.product_id || item.id;
                try {
                    const response = await fetch(`<?php echo url('/api/?endpoint=get-promotion'); ?>&product_id=${productId}`);
                    const data = await response.json();
                    return { productId, promotion: data.promotion };
                } catch (error) {
                    console.error('Error fetching promotion for product:', productId, error);
                    return { productId, promotion: null };
                }
            });

            const promotionsData = await Promise.all(promotionsPromises);

            // Check if at least one item has no promotion
            for (const promoData of promotionsData) {
                if (!promoData.promotion) {
                    return true; // Found at least one item without promotion
                }
            }

            return false; // All items have promotions
        }

        // Apply coupon
        async function applyCoupon() {
            const code = document.getElementById('couponCode').value.trim().toUpperCase();

            if (!code) {
                ShopUtils.showToast('Ingresa un código de cupón', 'error');
                return;
            }

            // Check if there are eligible items (only if products are loaded)
            if (currentProducts.length > 0 && !await hasEligibleItemsForCoupon(currentProducts)) {
                ShopUtils.showToast('❌ No hay ítems elegibles para aplicar cupón. Los cupones no se aplican a productos en promoción.', 'error');
                return;
            }

            // Calculate subtotal without promotion for minimum purchase validation
            let subtotalWithoutPromotion = 0;
            for (const item of cartData.items) {
                const productId = item.product_id || item.id;
                const product = currentProducts.find(p => p.id === productId);
                if (product) {
                    // Check if product has promotion
                    try {
                        const promoResponse = await fetch(`<?php echo url('/api/?endpoint=get-promotion'); ?>&product_id=${productId}`);
                        const promoData = await promoResponse.json();

                        if (!promoData.promotion) {
                            // Item has no promotion, add to subtotal
                            const price = getProductPrice(product, displayCurrency);
                            subtotalWithoutPromotion += price * item.quantity;
                        }
                    } catch (error) {
                        console.error('Error checking promotion for subtotal:', error);
                        // If error, assume no promotion
                        const price = getProductPrice(product, displayCurrency);
                        subtotalWithoutPromotion += price * item.quantity;
                    }
                }
            }

            try {
                const response = await fetch('<?php echo url('/api/?endpoint=validate-coupon'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        code: code,
                        subtotal: subtotalWithoutPromotion
                    })
                });

                const result = await response.json();

                if (result.valid) {
                    cartData.coupon = result.coupon;
                    localStorage.setItem('applied_coupon', JSON.stringify(result.coupon));
                    localStorage.removeItem('removed_coupon'); // Clear any removed coupon state
                    showAppliedCoupon(result.coupon);
                    loadCart();
                    ShopUtils.showToast('✅ Cupón aplicado correctamente');
                } else {
                    ShopUtils.showToast('❌ ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error validating coupon:', error);
                ShopUtils.showToast('Error al validar el cupón', 'error');
            }
        }

        // Show applied coupon
        function showAppliedCoupon(coupon) {
            console.log('Showing applied coupon:', coupon);

            const couponAppliedDiv = document.getElementById('couponApplied');
            const couponInputDiv = document.querySelector('.coupon-input');

            if (couponAppliedDiv) {
                couponAppliedDiv.innerHTML = `
                    <div class="coupon-applied">
                        <span>✅ Cupón "${coupon.code}" aplicado</span>
                        <button class="coupon-remove" data-action="removeCoupon">×</button>
                    </div>
                `;
                couponAppliedDiv.classList.remove('hidden');
            }

            if (couponInputDiv) {
                couponInputDiv.classList.add('hidden');
            }

            // Remove reapply button if exists
            const reapplyBtn = document.getElementById('reapplyBtn');
            if (reapplyBtn) {
                reapplyBtn.remove();
            }
        }

        // Show reapply button for previously removed coupon
        async function showReapplyButton(coupon) {
            // First validate if coupon is still valid (not expired)
            try {
                const response = await fetch(window.BASE_PATH + '/api/?endpoint=validate-coupon', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        coupon_code: coupon.code,
                        cart_total: cartData.items.reduce((sum, item) => sum + (item.price * item.quantity), 0)
                    })
                });
                
                const result = await response.json();
                
                if (!result.valid) {
                    // Coupon is expired or invalid, show message and don't show reapply button
                    ShopUtils.showToast(`❌ El cupón "${coupon.code}" ya no está disponible: ${result.message}`, 'error');
                    // Remove expired coupon from localStorage
                    localStorage.removeItem('removed_coupon');
                    return;
                }
            } catch (error) {
                console.error('Error validating coupon:', error);
                // If validation fails, don't show reapply button
                return;
            }
            
            const couponInputDiv = document.querySelector('.coupon-input');
            if (couponInputDiv) {
                // Add reapply button next to the coupon input
                const existingBtn = document.getElementById('reapplyBtn');
                if (!existingBtn) {
                    const reapplyBtn = document.createElement('button');
                    reapplyBtn.id = 'reapplyBtn';
                    reapplyBtn.className = 'btn-reapply';
                    reapplyBtn.textContent = `🔄 Reaplicar "${coupon.code}"`;
                    reapplyBtn.setAttribute('data-action', 'reapplyCoupon');
                    couponInputDiv.parentElement.insertBefore(reapplyBtn, couponInputDiv.nextSibling);
                }
            }
        }

        // Remove coupon (manual removal by user)
        function removeCoupon() {
            console.log('Removing coupon');

            const removedCoupon = cartData.coupon;
            cartData.coupon = null;
            localStorage.removeItem('applied_coupon');

            // Save removed coupon for "Reaplicar" button
            if (removedCoupon) {
                localStorage.setItem('removed_coupon', JSON.stringify(removedCoupon));
            }

            const couponAppliedDiv = document.getElementById('couponApplied');
            const couponInputDiv = document.querySelector('.coupon-input');
            const couponCodeInput = document.getElementById('couponCode');

            if (couponAppliedDiv) {
                couponAppliedDiv.classList.add('hidden');
                couponAppliedDiv.innerHTML = '';
            }

            if (couponInputDiv) {
                couponInputDiv.classList.remove('hidden');
            }

            if (couponCodeInput) {
                couponCodeInput.value = '';
            }

            loadCart();
            ShopUtils.showToast('Cupón removido');
        }

        // Auto-remove coupon when no eligible items (system removal)
        function autoRemoveCoupon(couponCode) {
            console.log('Auto-removing coupon:', couponCode);

            const removedCoupon = cartData.coupon;
            cartData.coupon = null;
            localStorage.removeItem('applied_coupon');

            // Save removed coupon for "Reaplicar" button
            if (removedCoupon) {
                localStorage.setItem('removed_coupon', JSON.stringify(removedCoupon));
            }

            const couponAppliedDiv = document.getElementById('couponApplied');
            const couponInputDiv = document.querySelector('.coupon-input');

            if (couponAppliedDiv) {
                couponAppliedDiv.classList.add('hidden');
                couponAppliedDiv.innerHTML = '';
            }

            if (couponInputDiv) {
                couponInputDiv.classList.remove('hidden');
            }

            // Remove existing reapply button
            const existingBtn = document.getElementById('reapplyBtn');
            if (existingBtn) {
                existingBtn.remove();
            }

            ShopUtils.showToast('❌ El cupón "' + couponCode + '" fue removido porque no hay ítems elegibles en el carrito.', 'warning');
        }

        // Reapply last removed coupon
        async function reapplyCoupon() {
            const removedCoupon = JSON.parse(localStorage.getItem('removed_coupon') || 'null');
            if (removedCoupon && removedCoupon.code) {
                document.getElementById('couponCode').value = removedCoupon.code;
                await applyCoupon();
            }
        }

        // Update currency button states
        function updateCurrencyButtons(currency) {
            document.querySelectorAll('.currency-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector(`.currency-btn[data-currency="${currency}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }

        // Switch display currency without reloading page
        function switchDisplayCurrency(currency) {
            console.log('[switchDisplayCurrency] Called with currency:', currency);
            displayCurrency = currency;

            // Update button states
            updateCurrencyButtons(currency);

            // Re-render cart to update all prices (products, subtotal, discount, total)
            console.log('[switchDisplayCurrency] currentProducts:', currentProducts);
            if (currentProducts && currentProducts.length > 0) {
                console.log('[switchDisplayCurrency] Rendering cart with currentProducts');
                renderCart(currentProducts);
            } else {
                // Fetch products if not loaded yet
                console.log('[switchDisplayCurrency] Fetching products first');
                fetchCartProducts();
            }
        }

        // Go to checkout
        async function goToCheckout() {
            console.log('[goToCheckout] Function called');

            // Sync cart to PHP session first
            try {
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');
                console.log('[goToCheckout] Cart from localStorage:', cart);

                if (cart.length === 0) {
                    console.warn('[goToCheckout] Cart is empty, showing toast');
                    ShopUtils.showToast('El carrito está vacío', 'error');
                    return;
                }

                console.log('[goToCheckout] Cart has', cart.length, 'items, proceeding to sync');

                // Prepare cart data for API
                const syncData = {
                    cart: cart,
                    coupon_code: cartData.coupon ? cartData.coupon.code : null
                };

                // Sync to session
                const response = await fetch('<?php echo url('/api/?endpoint=sync-cart'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(syncData)
                });

                if (!response.ok) {
                    throw new Error('Failed to sync cart');
                }

                const result = await response.json();

                if (result.success) {
                    // Redirect to checkout with coupon if applied
                    let checkoutUrl = '<?php echo url('/checkout'); ?>';
                    if (cartData.coupon) {
                        checkoutUrl += '?coupon=' + encodeURIComponent(cartData.coupon.code);
                    }
                    window.location.href = checkoutUrl;
                } else {
                    ShopUtils.showToast('Error al procesar el carrito', 'error');
                }
            } catch (error) {
                console.error('Error syncing cart:', error);
                ShopUtils.showToast('Error al procesar el carrito', 'error');
            }
        }

        // Cookie helpers for favorites


        // Update cart count
        function updateCartCount() {
            const count = cartData.items.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Show toast


        // Initialize
        loadCart();
        ShopFavorites.updateFavoritesCount();

        // === Wrappers for Event Delegation System ===
        (function() {
            const _switchDisplayCurrency = switchDisplayCurrency;
            window.switchDisplayCurrency = function(eventOrCurr, element, params) {
                const curr = params?.curr || (typeof eventOrCurr === 'string' ? eventOrCurr : null);
                if (curr) return _switchDisplayCurrency(curr);
            };

            const _applyCoupon = applyCoupon;
            window.applyCoupon = function(event, element, params) {
                return _applyCoupon();
            };

            const _goToCheckout = goToCheckout;
            window.goToCheckout = function(event, element, params) {
                console.log('[Wrapper] goToCheckout wrapper called');
                console.log('[Wrapper] Event:', event);
                console.log('[Wrapper] Element:', element);
                console.log('[Wrapper] Params:', params);
                return _goToCheckout();
            };

            // updateQuantity - handle locally for cart page
            window.updateQuantity = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                const delta = params?.delta ? parseInt(params.delta) : (typeof arguments[1] === 'number' ? arguments[1] : 0);

                if (!id) return;

                // Update quantity in localStorage
                const cartItems = ShopCart.getCart();
                const itemIndex = cartItems.findIndex(item => item.product_id === id);

                if (itemIndex !== -1) {
                    cartItems[itemIndex].quantity += delta;

                    // Remove if quantity is 0 or less
                    if (cartItems[itemIndex].quantity <= 0) {
                        cartItems.splice(itemIndex, 1);
                    }

                    // Save cart
                    ShopUtils.saveCart(cartItems);
                    ShopCart.updateCartBadge();

                    // Reload the cart page
                    loadCart();
                    if (cartItems.length > 0) {
                        fetchCartProducts();
                    }
                }
            };

            const _removeItem = removeItem;
            window.removeItem = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return _removeItem(id);
            };

            const _removeCoupon = removeCoupon;
            window.removeCoupon = function(event, element, params) {
                return _removeCoupon();
            };

            const _reapplyCoupon = reapplyCoupon;
            window.reapplyCoupon = function(event, element, params) {
                return _reapplyCoupon();
            };

            // Expose function for cart.js to refresh the cart page after updates
            window.onCartUpdated = function() {
                // Reload cart data from localStorage
                loadCart();
                // Re-fetch and render products
                if (cartData.items.length > 0) {
                    fetchCartProducts();
                }
            };
        })();
    </script>

    <!-- Fallback API endpoint (simple version) -->
    <script nonce="<?= csp_nonce() ?>">
        // Simple fallback if API doesn't exist yet
        if (!window.fetch) {
            console.error('Fetch API not supported');
        }
    </script>

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
