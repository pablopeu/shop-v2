/**
 * Favorites Management - Shared Functions
 * Gestión de favoritos compartida entre todas las páginas
 */

window.ShopFavorites = window.ShopFavorites || {};

/**
 * Get favorites from cookie
 * @returns {Array} Array of product IDs
 */
ShopFavorites.getFavorites = function() {
    const favorites = ShopUtils.getCookie('favorites');
    return favorites ? JSON.parse(favorites) : [];
};

/**
 * Save favorites to cookie
 * @param {Array} favorites - Array of product IDs
 */
ShopFavorites.saveFavorites = function(favorites) {
    ShopUtils.setCookie('favorites', JSON.stringify(favorites), 365); // 1 year expiration
};

/**
 * Update favorites count badge
 */
ShopFavorites.updateFavoritesCount = function() {
    const favorites = ShopFavorites.getFavorites();
    const countElement = document.getElementById('favorites-count');
    if (countElement) {
        countElement.textContent = favorites.length;
    }
};

/**
 * Toggle favorite status for a product
 * @param {string} productId - Product ID to toggle
 */
ShopFavorites.toggleFavorite = function(productId) {
    let favorites = ShopFavorites.getFavorites();
    const heartBtn = document.getElementById('favorite-btn-' + productId);
    const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;

    const index = favorites.indexOf(productId);
    if (index > -1) {
        // Remove from favorites
        favorites.splice(index, 1);
        if (heartBtn) heartBtn.classList.remove('active');
        if (heartIcon) {
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
        }
    } else {
        // Add to favorites
        favorites.push(productId);
        if (heartBtn) heartBtn.classList.add('active');
        if (heartIcon) {
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
        }
    }

    ShopFavorites.saveFavorites(favorites);
    ShopFavorites.updateFavoritesCount();

    // Update panel if render function exists
    if (typeof ShopFavorites.renderFavoritesPanel === 'function') {
        ShopFavorites.renderFavoritesPanel();
    }
};

/**
 * Update favorite buttons on page load
 */
ShopFavorites.updateFavoriteButtons = function() {
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
};

/**
 * Render favorites panel
 * @param {Array} products - Array of all products
 * @param {string} productUrlBase - Base URL for product pages (e.g., '/producto/')
 */
ShopFavorites.renderFavoritesPanel = function(products, productUrlBase) {
    const favorites = ShopFavorites.getFavorites();
    const body = document.getElementById('favorites-panel-body');

    if (!body) {
        console.warn('Favorites panel body not found');
        return;
    }

    if (favorites.length === 0) {
        body.innerHTML = '<div class="cart-empty">No tienes favoritos</div>';
        return;
    }

    // Use global products if not provided
    if (!products && typeof window.products !== 'undefined') {
        products = window.products;
    }

    // Use global productUrlBase if not provided
    if (!productUrlBase && typeof window.productUrlBase !== 'undefined') {
        productUrlBase = window.productUrlBase;
    }

    if (!products) {
        console.warn('Products array not available');
        return;
    }

    if (!productUrlBase) {
        console.warn('Product URL base not available, using default');
        productUrlBase = '/producto/'; // Fallback
    }

    let html = '';
    favorites.forEach(productId => {
        const product = products.find(p => p.id === productId);
        if (!product) return;

        const price = ShopUtils.formatProductPrice(product);

        html += `
            <div class="favorite-mini-card">
                <div data-action="goToProduct" data-url="${productUrlBase}${encodeURIComponent(product.slug)}" style="display: flex; align-items: center; flex: 1; cursor: pointer;">
                    <img src="${product.thumbnail || ''}" alt="${product.name}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                    <div style="flex: 1; padding-left: 12px;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 4px; color: #333;">${product.name}</div>
                        <div style="font-size: 14px; color: #667eea; font-weight: 700;">${price}</div>
                    </div>
                </div>
                <button class="favorite-remove-btn" data-action="removeFromFavorites" data-product-id="${product.id}" data-stop-propagation="true">Eliminar</button>
            </div>
        `;
    });

    body.innerHTML = html;
};

/**
 * Open favorites panel
 */
ShopFavorites.openFavoritesPanel = function() {
    if (typeof ShopFavorites.renderFavoritesPanel === 'function') {
        ShopFavorites.renderFavoritesPanel();
    }
    const panel = document.getElementById('favorites-panel');
    const overlay = document.getElementById('favorites-overlay');
    if (panel) panel.classList.add('open');
    if (overlay) overlay.classList.add('open');
};

/**
 * Close favorites panel
 */
ShopFavorites.closeFavoritesPanel = function() {
    const panel = document.getElementById('favorites-panel');
    const overlay = document.getElementById('favorites-overlay');
    if (panel) panel.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
};

/**
 * Go to favorites page
 * @param {string} favoritesUrl - URL to favorites page
 */
ShopFavorites.goToFavoritesPage = function(favoritesUrl) {
    window.location.href = favoritesUrl;
};

/**
 * Remove product from favorites
 * @param {string} productId - Product ID to remove
 */
ShopFavorites.removeFromFavorites = function(productId) {
    let favorites = ShopFavorites.getFavorites();
    favorites = favorites.filter(id => id !== productId);
    ShopFavorites.saveFavorites(favorites);

    // Update UI
    ShopFavorites.updateFavoritesCount();

    if (typeof ShopFavorites.renderFavoritesPanel === 'function') {
        ShopFavorites.renderFavoritesPanel();
    }

    // Update heart button if exists on the page
    const heartBtn = document.getElementById('favorite-btn-' + productId);
    const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;
    if (heartBtn) heartBtn.classList.remove('active');
    if (heartIcon) {
        heartIcon.classList.remove('fas');
        heartIcon.classList.add('far');
    }
};

// Export for backward compatibility
window.getFavorites = ShopFavorites.getFavorites;
window.saveFavorites = ShopFavorites.saveFavorites;
window.updateFavoritesCount = ShopFavorites.updateFavoritesCount;
window.toggleFavorite = ShopFavorites.toggleFavorite;
window.updateFavoriteButtons = ShopFavorites.updateFavoriteButtons;
window.openFavoritesPanel = ShopFavorites.openFavoritesPanel;
window.closeFavoritesPanel = ShopFavorites.closeFavoritesPanel;
window.removeFromFavorites = ShopFavorites.removeFromFavorites;
