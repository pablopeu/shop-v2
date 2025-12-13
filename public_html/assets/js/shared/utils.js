/**
 * Shop Utilities - Shared Helper Functions
 * Funciones utilitarias compartidas entre todas las páginas
 */

window.ShopUtils = window.ShopUtils || {};

/**
 * Cookie Management
 */
ShopUtils.setCookie = function(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/';
};

ShopUtils.getCookie = function(name) {
    const nameEQ = name + '=';
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) {
            return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
    }
    return null;
};

/**
 * Toast Notifications
 */
ShopUtils.showToast = function(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) {
        console.warn('Toast element not found');
        return;
    }

    toast.textContent = message;
    toast.className = 'toast show ' + (type === 'error' ? 'error' : '');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
};

/**
 * Format product price for display
 * @param {Object} product - Product object with price_ars and price_usd
 * @param {string} currency - Optional currency override (ARS or USD)
 * @returns {string} Formatted price string
 */
ShopUtils.formatProductPrice = function(product, currency) {
    const priceARS = parseFloat(product.price_ars) || 0;
    const priceUSD = parseFloat(product.price_usd) || 0;

    // If currency is specified, use that
    if (currency === 'USD' && priceUSD > 0) {
        return `U$D ${priceUSD.toFixed(2)}`;
    }

    if (currency === 'ARS' && priceARS > 0) {
        return `$ ${priceARS.toFixed(2)}`;
    }

    // Auto-detect from product prices
    if (priceUSD > 0 && priceARS === 0) {
        return `U$D ${priceUSD.toFixed(2)}`;
    } else if (priceARS > 0) {
        return `$ ${priceARS.toFixed(2)}`;
    }

    return '$0.00';
};

/**
 * Save cart to localStorage with timestamp
 */
ShopUtils.saveCart = function(cart) {
    console.log('[ShopUtils.saveCart] items:', cart.length);
    localStorage.setItem('cart', JSON.stringify(cart));
    localStorage.setItem('cart_timestamp', Date.now().toString());
};

/**
 * Check if cart has expired (after 4 hours of inactivity)
 */
ShopUtils.checkCartExpiration = function() {
    const EXPIRATION_TIME = 4 * 60 * 60 * 1000; // 4 hours in milliseconds
    const timestamp = localStorage.getItem('cart_timestamp');

    if (timestamp) {
        const elapsed = Date.now() - parseInt(timestamp);
        if (elapsed > EXPIRATION_TIME) {
            // Cart expired, clear it
            localStorage.removeItem('cart');
            localStorage.removeItem('cart_timestamp');
            console.log('Cart expired and cleared after 4 hours of inactivity');
            return true;
        }
    }
    return false;
};

// Export for backward compatibility
window.setCookie = ShopUtils.setCookie;
window.getCookie = ShopUtils.getCookie;
window.showToast = ShopUtils.showToast;
