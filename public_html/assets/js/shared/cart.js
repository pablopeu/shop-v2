/**
 * Shopping Cart Management - Shared Functions
 * Gestión del carrito de compras compartida entre todas las páginas
 */

window.ShopCart = window.ShopCart || {};

/**
 * Get cart from localStorage
 * @returns {Array} Cart items array
 */
ShopCart.getCart = function() {
    return JSON.parse(localStorage.getItem('cart') || '[]');
};

/**
 * Add product to cart
 * @param {string} productId - Product ID to add
 * @param {Event} event - Optional event object to stop propagation
 */
ShopCart.addToCart = function(productId, event) {
    if (event && event.stopPropagation) {
        event.stopPropagation();
    }


    // Get current cart from localStorage
    let cart = ShopCart.getCart();

    // Check if product already exists (support both formats)
    const existingItem = cart.find(item => (item.product_id || item.id) === productId);

    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            product_id: productId,
            quantity: 1
        });
    }

    // Save to localStorage with timestamp
    ShopUtils.saveCart(cart);

    // Update UI
    ShopCart.updateCartBadge();

    if (typeof ShopCart.renderCartPanel === 'function') {
        ShopCart.renderCartPanel();
    }

    if (typeof ShopCart.openCartPanel === 'function') {
        ShopCart.openCartPanel();
    }
};

/**
 * Update cart badge with item count
 */
ShopCart.updateCartBadge = function() {
    const cart = ShopCart.getCart();
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cart-count');

    if (badge) {
        badge.textContent = totalItems;
        if (totalItems > 0) {
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
};

/**
 * Update quantity of a product in cart
 * @param {string} productId - Product ID
 * @param {number} change - Change in quantity (+1 or -1)
 */
ShopCart.updateQuantity = function(productId, change) {
    let cart = ShopCart.getCart();
    const item = cart.find(i => i.product_id === productId);

    if (item) {
        item.quantity += change;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i.product_id !== productId);
        }
    }

    ShopUtils.saveCart(cart);
    ShopCart.updateCartBadge();

    // Only render cart panel if the panel exists on the page
    if (typeof ShopCart.renderCartPanel === 'function' && document.getElementById('cart-panel-body')) {
        ShopCart.renderCartPanel();
    }

    // Notify cart page to refresh (if on cart page)
    if (typeof window.onCartUpdated === 'function') {
        window.onCartUpdated();
    }
};

/**
 * Remove product from cart
 * @param {string} productId - Product ID to remove
 */
ShopCart.removeFromCart = function(productId) {
    let cart = ShopCart.getCart();
    cart = cart.filter(i => i.product_id !== productId);
    ShopUtils.saveCart(cart);
    ShopCart.updateCartBadge();

    // Only render cart panel if the panel exists on the page
    if (typeof ShopCart.renderCartPanel === 'function' && document.getElementById('cart-panel-body')) {
        ShopCart.renderCartPanel();
    }

    // Notify cart page to refresh (if on cart page)
    if (typeof window.onCartUpdated === 'function') {
        window.onCartUpdated();
    }
};

/**
 * Render cart panel with products
 * @param {Array} products - Array of all products
 * @param {Object} options - Rendering options
 */
ShopCart.renderCartPanel = async function(products, options = {}) {
    const cart = ShopCart.getCart();
    const body = document.getElementById('cart-panel-body');
    const footer = document.getElementById('cart-panel-footer');
    const totalEl = document.getElementById('cart-total');

    if (!body) {
        return;
    }


    // Use global products if not provided
    if (!products && typeof window.products !== 'undefined') {
        products = window.products;
    }


    if (cart.length === 0) {
        body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
        if (footer) footer.classList.add('hidden');
        return;
    }

    // Get exchange rate and API endpoint from options or globals
    const exchangeRate = options.exchangeRate || window.exchangeRate || 1;
    const apiGetPromotion = options.apiGetPromotion || window.API_GET_PROMOTION || '';

    // Fetch promotions for all products in cart
    const promotionsPromises = cart.map(async item => {
        if (!apiGetPromotion) return { productId: item.product_id, promotion: null };

        try {
            const response = await fetch(`${apiGetPromotion}?product_id=${item.product_id}`);
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
        if (!products) {
            return;
        }

        const product = products.find(p => p.id === item.product_id);

        if (!product) {
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
                // Fixed discount
                priceARS = Math.max(0, priceARS - promotion.value);
                priceUSD = Math.max(0, priceUSD - (promotion.value / exchangeRate));
            }
        }

        // Determine if product is USD or ARS
        let itemPriceARS = 0;
        let itemPriceUSD = 0;
        let displayPrice = '';

        if (priceUSD > 0 && originalPriceARS === 0) {
            // USD product
            itemPriceUSD = priceUSD;
            itemPriceARS = priceUSD * exchangeRate;
            displayPrice = hasPromotion ?
                `<div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <div style="color: #ff6b6b; font-weight: 600; font-size: 0.85rem;">🎉 -${promotion.value}${promotion.type === 'percentage' ? '%' : ' USD'}</div>
                    <div>
                        <span style="text-decoration: line-through; color: #999; font-size: 0.9rem;">U$D ${originalPriceUSD.toFixed(2)}</span>
                        <span style="color: #ff6b6b; font-weight: 700; margin-left: 0.5rem;">U$D ${priceUSD.toFixed(2)}</span>
                    </div>
                    <div style="color: #666; font-size: 0.85rem;">($ ${itemPriceARS.toFixed(2)} ARS)</div>
                </div>` :
                `<div style="display: flex; flex-direction: column;">
                    <div>U$D ${priceUSD.toFixed(2)}</div>
                    <div style="color: #666; font-size: 0.85rem;">($ ${itemPriceARS.toFixed(2)} ARS)</div>
                </div>`;
        } else if (priceARS > 0) {
            // ARS product
            allProductsUSD = false;
            itemPriceARS = priceARS;
            displayPrice = hasPromotion ?
                `<div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <div style="color: #ff6b6b; font-weight: 600; font-size: 0.85rem;">🎉 -${promotion.value}${promotion.type === 'percentage' ? '%' : ' ARS'}</div>
                    <div>
                        <span style="text-decoration: line-through; color: #999; font-size: 0.9rem;">$ ${originalPriceARS.toFixed(2)}</span>
                        <span style="color: #ff6b6b; font-weight: 700; margin-left: 0.5rem;">$ ${priceARS.toFixed(2)}</span>
                    </div>
                </div>` :
                '$ ' + priceARS.toFixed(2);
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
        localStorage.setItem('cart', JSON.stringify(validCart));
        ShopCart.updateCartBadge();
    }

    // Check if we have valid items to display
    if (html === '' || validCart.length === 0) {
        body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
        if (footer) footer.classList.add('hidden');
        return;
    }

    body.innerHTML = html;

    // Display total
    if (totalEl) {
        if (allProductsUSD && totalUSD > 0) {
            totalEl.textContent = 'U$D ' + totalUSD.toFixed(2);
        } else {
            totalEl.textContent = '$' + totalARS.toFixed(2);
        }
    }

    if (footer) footer.classList.remove('hidden');
};

/**
 * Open cart panel
 */
ShopCart.openCartPanel = function() {
    if (typeof ShopCart.renderCartPanel === 'function') {
        ShopCart.renderCartPanel();
    }

    const panel = document.getElementById('cart-panel');
    const overlay = document.getElementById('cart-overlay');
    if (panel) panel.classList.add('open');
    if (overlay) overlay.classList.add('open');
};

/**
 * Close cart panel
 */
ShopCart.closeCartPanel = function() {
    const panel = document.getElementById('cart-panel');
    const overlay = document.getElementById('cart-overlay');
    if (panel) panel.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
};

/**
 * Go to checkout - sync cart to session and redirect
 * @param {string} syncCartUrl - URL to sync cart API endpoint
 * @param {string} checkoutUrl - URL to checkout page
 */
ShopCart.goToCheckout = async function(syncCartUrl, checkoutUrl) {
    try {
        const cart = ShopCart.getCart();

        if (cart.length === 0) {
            if (typeof ShopUtils.showToast === 'function') {
                ShopUtils.showToast('El carrito está vacío', 'error');
            }
            return;
        }

        // Sync to session
        const response = await fetch(syncCartUrl, {
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
            // Redirect to checkout/cart page
            window.location.href = checkoutUrl;
        } else {
            if (typeof ShopUtils.showToast === 'function') {
                ShopUtils.showToast('Error al procesar el carrito', 'error');
            }
        }
    } catch (error) {
        console.error('Error syncing cart:', error);
        if (typeof ShopUtils.showToast === 'function') {
            ShopUtils.showToast('Error al procesar el carrito', 'error');
        }
    }
};

// Export for backward compatibility
window.addToCart = ShopCart.addToCart;
window.updateCartBadge = ShopCart.updateCartBadge;
window.updateQuantity = ShopCart.updateQuantity;
window.removeFromCart = ShopCart.removeFromCart;
window.renderCartPanel = ShopCart.renderCartPanel;
window.openCartPanel = ShopCart.openCartPanel;
window.closeCartPanel = ShopCart.closeCartPanel;
window.goToCheckout = ShopCart.goToCheckout;
