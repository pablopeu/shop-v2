# Shared JavaScript Modules

Sistema centralizado de JavaScript para eliminar duplicación de código entre páginas.

## 📦 Módulos Disponibles

### 1. **utils.js** - Utilidades Comunes

Funciones helper compartidas entre todas las páginas.

```javascript
// Cookie Management
ShopUtils.setCookie('name', 'value', 365);
const value = ShopUtils.getCookie('name');

// Toast Notifications
ShopUtils.showToast('Producto agregado', 'success');
ShopUtils.showToast('Error al procesar', 'error');

// Price Formatting
const formattedPrice = ShopUtils.formatProductPrice(product, 'USD');
// Returns: "U$D 99.99" or "$ 1,234.56"

// Cart Helpers
ShopUtils.saveCart(cartArray);
const expired = ShopUtils.checkCartExpiration(); // Returns true if expired
```

### 2. **favorites.js** - Sistema de Favoritos

Gestión completa del sistema de favoritos.

```javascript
// Get/Save Favorites
const favorites = ShopFavorites.getFavorites(); // Returns array of product IDs
ShopFavorites.saveFavorites(['prod-1', 'prod-2']);

// Toggle Favorite
ShopFavorites.toggleFavorite('product-id-123');

// Update UI
ShopFavorites.updateFavoritesCount(); // Updates badge
ShopFavorites.updateFavoriteButtons(); // Updates heart icons

// Panel Management
ShopFavorites.renderFavoritesPanel(productsArray, '/producto/');
ShopFavorites.openFavoritesPanel();
ShopFavorites.closeFavoritesPanel();

// Remove from Favorites
ShopFavorites.removeFromFavorites('product-id-123');

// Navigate to Favorites Page
ShopFavorites.goToFavoritesPage('/favoritos');
```

### 3. **cart.js** - Sistema de Carrito

Gestión completa del carrito de compras.

```javascript
// Get Cart
const cart = ShopCart.getCart(); // Returns cart array from localStorage

// Add to Cart
ShopCart.addToCart('product-id-123', event);

// Update Badge
ShopCart.updateCartBadge(); // Updates cart count badge

// Manage Quantities
ShopCart.updateQuantity('product-id-123', 1);  // Increment
ShopCart.updateQuantity('product-id-123', -1); // Decrement

// Remove from Cart
ShopCart.removeFromCart('product-id-123');

// Panel Management
await ShopCart.renderCartPanel(productsArray, {
    exchangeRate: 1200,
    apiGetPromotion: '/api/get_promotion.php'
});

ShopCart.openCartPanel();
ShopCart.closeCartPanel();

// Checkout
await ShopCart.goToCheckout('/api/sync_cart.php', '/carrito');
```

## 🔧 Cómo Usar en tus Páginas

### Paso 1: Incluir los archivos

```php
<!-- En el <head> o antes de </body> -->
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/utils.js'); ?>"></script>
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/favorites.js'); ?>"></script>
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/shared/cart.js'); ?>"></script>
```

### Paso 2: Usar en tu código

```javascript
// En tus funciones wrapper para event delegation
function myAddToCart(event, element, params) {
    const productId = params?.productId;
    if (productId) {
        ShopCart.addToCart(productId, event);
    }
}

// Export para event-handlers.js
window.myAddToCart = myAddToCart;
```

### Paso 3: Configurar renderizado (opcional)

```javascript
// Configurar productos globales para los panels
window.products = <?php echo json_encode($products); ?>;
window.exchangeRate = <?php echo $currency_config['exchange_rate']; ?>;
window.API_GET_PROMOTION = '<?php echo url('/api/get_promotion.php'); ?>';

// Inicializar en DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    ShopUtils.checkCartExpiration();
    ShopCart.updateCartBadge();
    ShopFavorites.updateFavoritesCount();
    ShopFavorites.updateFavoriteButtons();
});
```

## 📋 Backward Compatibility

Todos los módulos exportan funciones al namespace global para compatibilidad:

```javascript
// utils.js
window.setCookie = ShopUtils.setCookie;
window.getCookie = ShopUtils.getCookie;
window.showToast = ShopUtils.showToast;

// favorites.js
window.toggleFavorite = ShopFavorites.toggleFavorite;
window.getFavorites = ShopFavorites.getFavorites;
// ... etc

// cart.js
window.addToCart = ShopCart.addToCart;
window.removeFromCart = ShopCart.removeFromCart;
// ... etc
```

Esto permite que el código existente siga funcionando sin cambios.

## 🎯 Ventajas

1. **DRY (Don't Repeat Yourself)**: Una sola implementación de cada función
2. **Mantenibilidad**: Cambios en un solo lugar
3. **Namespace Limpio**: `ShopUtils`, `ShopFavorites`, `ShopCart`
4. **Modular**: Incluir solo lo que necesitas
5. **Documentado**: JSDoc comments en todas las funciones
6. **Testeable**: Funciones puras y bien definidas

## 🔍 Debugging

Todas las funciones principales incluyen `console.log()` para debugging:

```javascript
// Verás en la consola:
[ShopCart.addToCart] Adding product: prod-123
[ShopCart.addToCart] Current cart before adding: [...]
[ShopCart.addToCart] Cart saved to localStorage: [...]
```

## 📝 Notas Importantes

- **Dependencias**: `cart.js` y `favorites.js` dependen de `utils.js`
- **Orden de carga**: Cargar `utils.js` primero
- **CSP**: Todos los scripts deben tener `nonce="<?= csp_nonce() ?>"`
- **Event Delegation**: Usar con el sistema `event-handlers.js`

## 🔄 Migración desde Código Duplicado

Para migrar una página existente:

1. Incluir los archivos shared
2. Eliminar funciones duplicadas (setCookie, getCookie, etc.)
3. Reemplazar llamadas directas con `ShopUtils.*`, `ShopFavorites.*`, etc.
4. Testear funcionalidad

## 📚 Ver También

- `CLAUDE.md` - Reglas del proyecto
- `docs/PLAN_MIGRACION_THEMES.md` - Plan de migración completo
- `event-handlers.js` - Sistema de event delegation

---

**Creado**: 2025-12-07
**Fase**: Fase 3 - JavaScript Centralizado
**Commits**: ac1d4bd, 4310c32
