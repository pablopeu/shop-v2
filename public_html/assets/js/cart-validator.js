/**
 * Cart Validator - Validates cart items against product availability
 * This script should be included in all public pages to ensure cart integrity
 */


// Validate cart and update count
async function validateCartAndUpdateCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');

    if (cart.length === 0) {
        updateCartCountDisplay(0);
        return;
    }

    const productIds = cart.map(item => item.product_id || item.id);

    try {
        // Use global API_GET_PRODUCTS if available, otherwise fallback to relative path
        const apiUrl = typeof API_GET_PRODUCTS !== 'undefined' ? API_GET_PRODUCTS : '/api/?endpoint=get-products';

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ product_ids: productIds })
        });


        if (!response.ok) {
            // If API fails, just update count without validation
            updateCartCountDisplay(cart.reduce((sum, item) => sum + item.quantity, 0));
            return;
        }

        const products = await response.json();

        // Clean cart by removing unavailable products
        const cleanedCart = cart.filter(item => {
            const productId = item.product_id || item.id;
            const product = products.find(p => p.id === productId);

            // Only remove if product doesn't exist or is not active
            // DO NOT remove products with stock <= 0 (let checkout handle that)
            if (!product || !product.active) {
                return false;
            }

            // Adjust quantity if it exceeds available stock
            if (item.quantity > product.stock) {
                item.quantity = product.stock;
            }

            return true;
        });

        // If cart was cleaned, save it
        if (cleanedCart.length !== cart.length) {
            localStorage.setItem('cart', JSON.stringify(cleanedCart));
        } else {
        }

        // Update count with valid items only
        const validCount = cleanedCart.reduce((sum, item) => sum + item.quantity, 0);
        updateCartCountDisplay(validCount);

    } catch (error) {
        console.error('[CART VALIDATOR] Error validating cart:', error);
        // Fallback: show count without validation
        updateCartCountDisplay(cart.reduce((sum, item) => sum + item.quantity, 0));
    }
}

// Update cart count display
function updateCartCountDisplay(count) {
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement) {
        cartCountElement.textContent = count;
    }
}

// Simple update (without validation) for immediate feedback
function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
    updateCartCountDisplay(count);
}

// Validate on page load
document.addEventListener('DOMContentLoaded', () => {
    validateCartAndUpdateCount();
});

// NOTE: Removed 'storage' event listener because it was causing race conditions
// when saving to localStorage in the same tab. The storage event is meant for
// cross-tab synchronization, but here it was reading stale data and clearing
// the cart. DOMContentLoaded validation is sufficient for single-tab usage.
