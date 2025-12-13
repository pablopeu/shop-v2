# Módulos JavaScript - Shop V2

Documentación completa del sistema JavaScript modular del proyecto.

**Última actualización**: 2025-12-08

---

## 📑 Tabla de Contenidos

- [Arquitectura JavaScript](#arquitectura-javascript)
- [Namespaces Globales](#namespaces-globales)
- [ShopUtils](#shoputils)
- [ShopCart](#shopcart)
- [ShopFavorites](#shopfavorites)
- [Event Handlers](#event-handlers)
- [Otros Módulos](#otros-módulos)
- [Integración con CSP](#integración-con-csp)
- [Buenas Prácticas](#buenas-prácticas)
- [Crear Módulos Nuevos](#crear-módulos-nuevos)

---

## Arquitectura JavaScript

### Principios de Diseño

El JavaScript del sistema está diseñado con estos principios:

✅ **Modular** - Funcionalidades organizadas en namespaces
✅ **Sin Conflictos** - Namespaces globales evitan colisiones
✅ **CSP Compatible** - Sin `eval()`, sin inline handlers
✅ **Reutilizable** - Mismas funciones en múltiples páginas
✅ **Mantenible** - Un módulo, un propósito

### Sin Frameworks

El sistema NO usa frameworks como React, Vue o Angular. Razones:

- **Simplicidad**: JavaScript vanilla es suficiente
- **Performance**: Sin overhead de frameworks
- **Tamaño**: Archivos pequeños y rápidos
- **Control**: Total control sobre el código

### Ubicación

Todos los módulos están en: `public_html/assets/js/`

```
public_html/assets/js/
├── event-handlers.js      # Sistema de delegación de eventos
├── shop-utils.js          # Utilidades generales
├── shop-cart.js           # Gestión de carrito
├── shop-favorites.js      # Gestión de favoritos
├── currency-switcher.js   # Cambio de moneda
├── cart-validator.js      # Validación de carrito
└── mobile-menu.js         # Menú móvil
```

---

## Namespaces Globales

### ¿Por Qué Namespaces?

Sin namespaces, las funciones colisionan:

```javascript
// ❌ PROBLEMA: Colisión de nombres
// archivo1.js
function formatPrice(price) { ... }

// archivo2.js
function formatPrice(price) { ... }  // Sobrescribe la anterior
```

Con namespaces, todo está organizado:

```javascript
// ✅ SOLUCIÓN: Namespaces
// shop-utils.js
window.ShopUtils = {
    formatPrice: function(price) { ... }
};

// shop-cart.js
window.ShopCart = {
    formatPrice: function(price) { ... }  // No colisiona
};
```

### Namespaces del Sistema

| Namespace | Propósito | Archivo |
|-----------|-----------|---------|
| `ShopUtils` | Utilidades generales | `shop-utils.js` |
| `ShopCart` | Gestión de carrito | `shop-cart.js` |
| `ShopFavorites` | Gestión de favoritos | `shop-favorites.js` |

---

## ShopUtils

Módulo de utilidades generales para formateo, sanitización y UI.

### Ubicación

- **Archivo**: `public_html/assets/js/shop-utils.js`
- **Namespace**: `window.ShopUtils`

### Funciones

#### `formatCurrency(amount, currency)`

Formatea un número como moneda.

**Parámetros**:
- `amount` (number): Cantidad a formatear
- `currency` (string): Código de moneda (`'USD'` o `'ARS'`)

**Retorna**: `string` - Precio formateado

**Ejemplo**:
```javascript
ShopUtils.formatCurrency(1234.56, 'USD');
// Retorna: "$1,234.56"

ShopUtils.formatCurrency(1234.56, 'ARS');
// Retorna: "$1.234,56"
```

**Implementación**:
```javascript
formatCurrency: function(amount, currency) {
    if (currency === 'ARS') {
        return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&.').replace('.', ',').slice(0, -3);
    } else {
        return '$' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
}
```

#### `updatePrice(price, currency)`

Actualiza todos los elementos con clase `.price` en el DOM.

**Parámetros**:
- `price` (number): Nuevo precio
- `currency` (string): Moneda

**Retorna**: `void`

**Ejemplo**:
```javascript
// Actualizar todos los precios a USD
ShopUtils.updatePrice(1500, 'USD');
```

**Uso típico**: Cambio de moneda global

#### `sanitizeInput(input)`

Sanitiza texto para prevenir XSS.

**Parámetros**:
- `input` (string): Texto a sanitizar

**Retorna**: `string` - Texto sanitizado

**Ejemplo**:
```javascript
const userInput = '<script>alert("XSS")</script>';
const safe = ShopUtils.sanitizeInput(userInput);
// Retorna: "&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;"
```

**Implementación**:
```javascript
sanitizeInput: function(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}
```

#### `showToast(message, type)`

Muestra notificación toast temporal.

**Parámetros**:
- `message` (string): Mensaje a mostrar
- `type` (string): Tipo de toast (`'success'`, `'error'`, `'info'`)

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopUtils.showToast('Producto agregado al carrito', 'success');
ShopUtils.showToast('Error al procesar el pago', 'error');
ShopUtils.showToast('El producto está agotado', 'info');
```

**HTML requerido**:
```html
<div class="toast" id="toast"></div>
```

**CSS requerido**:
```css
.toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 4px;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 9999;
}

.toast.show {
    opacity: 1;
}

.toast.success { background: #28a745; color: white; }
.toast.error { background: #dc3545; color: white; }
.toast.info { background: #17a2b8; color: white; }
```

### Uso Completo

```html
<!DOCTYPE html>
<html>
<head>
    <!-- ... -->
</head>
<body>
    <!-- Toast container -->
    <div class="toast" id="toast"></div>

    <!-- Cargar módulo -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/shop-utils.js') ?>"></script>

    <!-- Usar en página -->
    <script nonce="<?= csp_nonce() ?>">
        // Formatear precio
        const formatted = ShopUtils.formatCurrency(1500.50, 'USD');
        console.log(formatted); // "$1,500.50"

        // Mostrar toast
        ShopUtils.showToast('Operación exitosa', 'success');
    </script>
</body>
</html>
```

---

## ShopCart

Módulo para gestionar el carrito de compras usando localStorage.

### Ubicación

- **Archivo**: `public_html/assets/js/shop-cart.js`
- **Namespace**: `window.ShopCart`

### Estructura del Carrito

El carrito se almacena en `localStorage` como JSON:

```javascript
[
    {
        "slug": "producto-1",
        "name": "Producto Ejemplo",
        "price": 1000.00,
        "quantity": 2,
        "thumbnail": "/uploads/products/producto-1-thumb.jpg"
    },
    {
        "slug": "producto-2",
        "name": "Otro Producto",
        "price": 500.00,
        "quantity": 1,
        "thumbnail": "/uploads/products/producto-2-thumb.jpg"
    }
]
```

### Funciones

#### `addToCart(productSlug, quantity, productData)`

Agrega un producto al carrito.

**Parámetros**:
- `productSlug` (string): Slug único del producto
- `quantity` (number): Cantidad a agregar
- `productData` (object): Datos del producto (name, price, thumbnail)

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopCart.addToCart('producto-ejemplo', 2, {
    name: 'Producto Ejemplo',
    price: 1000.00,
    thumbnail: '/uploads/products/producto-1-thumb.jpg'
});

// Mostrar confirmación
ShopUtils.showToast('Producto agregado al carrito', 'success');
```

#### `getCart()`

Obtiene todos los items del carrito.

**Retorna**: `array` - Array de items

**Ejemplo**:
```javascript
const cart = ShopCart.getCart();
console.log(cart);
// [{ slug: '...', name: '...', quantity: 2, ... }]
```

#### `updateQuantity(productSlug, newQuantity)`

Actualiza la cantidad de un producto.

**Parámetros**:
- `productSlug` (string): Slug del producto
- `newQuantity` (number): Nueva cantidad (0 para eliminar)

**Retorna**: `void`

**Ejemplo**:
```javascript
// Actualizar a 5 unidades
ShopCart.updateQuantity('producto-ejemplo', 5);

// Eliminar (cantidad 0)
ShopCart.updateQuantity('producto-ejemplo', 0);
```

#### `removeFromCart(productSlug)`

Elimina un producto del carrito.

**Parámetros**:
- `productSlug` (string): Slug del producto

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopCart.removeFromCart('producto-ejemplo');
ShopUtils.showToast('Producto eliminado', 'info');
```

#### `clearCart()`

Vacía completamente el carrito.

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopCart.clearCart();
ShopUtils.showToast('Carrito vaciado', 'success');
```

#### `getCartCount()`

Obtiene el total de items en el carrito.

**Retorna**: `number` - Total de items (suma de cantidades)

**Ejemplo**:
```javascript
const count = ShopCart.getCartCount();
console.log(`Tienes ${count} productos en el carrito`);

// Actualizar badge
document.getElementById('cart-badge').textContent = count;
```

#### `openCartPanel()`

Abre el panel lateral del carrito.

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopCart.openCartPanel();
```

**HTML requerido**: Componente `cart-panel.php`

#### `closeCartPanel()`

Cierra el panel lateral del carrito.

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopCart.closeCartPanel();
```

### Eventos Personalizados

El módulo dispara eventos personalizados cuando cambia el carrito:

```javascript
// Escuchar cambios en el carrito
document.addEventListener('cartUpdated', function(e) {
    const cart = e.detail.cart;
    console.log('Carrito actualizado:', cart);

    // Actualizar UI
    updateCartBadge();
    updateCartTotal();
});
```

### Uso Completo

```html
<!DOCTYPE html>
<html>
<head>
    <!-- ... -->
</head>
<body>
    <!-- Badge del carrito -->
    <button data-action="openCartPanel">
        🛒 Carrito <span id="cart-badge">0</span>
    </button>

    <!-- Cart Panel Component -->
    <?php
    require_once APP_PATH . '/includes/frontend/cart-panel.php';
    render_cart_panel();
    ?>

    <!-- Cargar módulo -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/shop-utils.js') ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/shop-cart.js') ?>"></script>

    <!-- Usar en página -->
    <script nonce="<?= csp_nonce() ?>">
        // Agregar producto al carrito
        function addToCartFromCard(event, element, params) {
            const slug = params.slug;

            // Obtener datos del producto (desde API o data attributes)
            fetch(`<?= url('/api/product.php') ?>?slug=${slug}`)
                .then(res => res.json())
                .then(product => {
                    ShopCart.addToCart(slug, 1, {
                        name: product.name,
                        price: product.price,
                        thumbnail: product.thumbnail
                    });

                    ShopUtils.showToast('✅ Producto agregado', 'success');

                    // Actualizar badge
                    updateCartBadge();
                });
        }

        function updateCartBadge() {
            const count = ShopCart.getCartCount();
            document.getElementById('cart-badge').textContent = count;
        }

        // Inicializar al cargar página
        document.addEventListener('DOMContentLoaded', function() {
            updateCartBadge();
        });

        // Exportar para event delegation
        window.addToCartFromCard = addToCartFromCard;
    </script>

    <!-- Event Handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
</body>
</html>
```

---

## ShopFavorites

Módulo para gestionar productos favoritos usando localStorage.

### Ubicación

- **Archivo**: `public_html/assets/js/shop-favorites.js`
- **Namespace**: `window.ShopFavorites`

### Estructura de Favoritos

Los favoritos se almacenan como array de slugs:

```javascript
["producto-1", "producto-2", "producto-3"]
```

### Funciones

#### `toggleFavorite(productSlug)`

Agrega o quita un producto de favoritos.

**Parámetros**:
- `productSlug` (string): Slug del producto

**Retorna**: `boolean` - `true` si se agregó, `false` si se quitó

**Ejemplo**:
```javascript
const added = ShopFavorites.toggleFavorite('producto-ejemplo');

if (added) {
    ShopUtils.showToast('❤️ Agregado a favoritos', 'success');
} else {
    ShopUtils.showToast('Quitado de favoritos', 'info');
}
```

#### `isFavorite(productSlug)`

Verifica si un producto está en favoritos.

**Parámetros**:
- `productSlug` (string): Slug del producto

**Retorna**: `boolean` - `true` si es favorito

**Ejemplo**:
```javascript
const isFav = ShopFavorites.isFavorite('producto-ejemplo');

// Actualizar botón de favorito
const btn = document.querySelector('[data-slug="producto-ejemplo"]');
if (isFav) {
    btn.classList.add('active');
    btn.innerHTML = '❤️';
} else {
    btn.classList.remove('active');
    btn.innerHTML = '♡';
}
```

#### `getFavorites()`

Obtiene la lista completa de favoritos.

**Retorna**: `array` - Array de slugs

**Ejemplo**:
```javascript
const favorites = ShopFavorites.getFavorites();
console.log('Favoritos:', favorites);
// ["producto-1", "producto-2"]
```

#### `getFavoritesCount()`

Obtiene el número de favoritos.

**Retorna**: `number` - Total de favoritos

**Ejemplo**:
```javascript
const count = ShopFavorites.getFavoritesCount();
document.getElementById('favorites-badge').textContent = count;
```

#### `openFavoritesPanel()`

Abre el panel lateral de favoritos.

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopFavorites.openFavoritesPanel();
```

#### `closeFavoritesPanel()`

Cierra el panel lateral de favoritos.

**Retorna**: `void`

**Ejemplo**:
```javascript
ShopFavorites.closeFavoritesPanel();
```

### Uso Completo

```html
<!DOCTYPE html>
<html>
<body>
    <!-- Botón de favoritos -->
    <button data-action="openFavoritesPanel">
        ❤️ Favoritos <span id="favorites-badge">0</span>
    </button>

    <!-- Favorites Panel Component -->
    <?php
    require_once APP_PATH . '/includes/frontend/favorites-panel.php';
    render_favorites_panel();
    ?>

    <!-- Product Card con botón de favorito -->
    <div class="product-card">
        <h3>Producto Ejemplo</h3>
        <button class="btn-favorite"
                data-action="toggleFavorite"
                data-slug="producto-ejemplo">
            ♡
        </button>
    </div>

    <!-- Cargar módulos -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/shop-utils.js') ?>"></script>
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/shop-favorites.js') ?>"></script>

    <!-- Lógica de página -->
    <script nonce="<?= csp_nonce() ?>">
        function toggleFavorite(event, element, params) {
            const slug = params.slug;
            const added = ShopFavorites.toggleFavorite(slug);

            // Actualizar botón
            if (added) {
                element.innerHTML = '❤️';
                element.classList.add('active');
                ShopUtils.showToast('Agregado a favoritos', 'success');
            } else {
                element.innerHTML = '♡';
                element.classList.remove('active');
                ShopUtils.showToast('Quitado de favoritos', 'info');
            }

            // Actualizar badge
            updateFavoritesBadge();
        }

        function updateFavoritesBadge() {
            const count = ShopFavorites.getFavoritesCount();
            document.getElementById('favorites-badge').textContent = count;
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            updateFavoritesBadge();

            // Marcar favoritos actuales
            const favorites = ShopFavorites.getFavorites();
            favorites.forEach(slug => {
                const btn = document.querySelector(`[data-slug="${slug}"]`);
                if (btn) {
                    btn.innerHTML = '❤️';
                    btn.classList.add('active');
                }
            });
        });

        // Exportar
        window.toggleFavorite = toggleFavorite;
    </script>

    <!-- Event Handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
</body>
</html>
```

---

## Event Handlers

Sistema de delegación de eventos compatible con CSP.

### Ubicación

- **Archivo**: `public_html/assets/js/event-handlers.js`
- **No usa namespace**: Es un sistema de delegación automática

### ¿Por Qué Event Delegation?

**Problema**: CSP estricto bloquea inline event handlers

```html
<!-- ❌ BLOQUEADO POR CSP -->
<button onclick="myFunction()">Click</button>
```

**Solución**: Event delegation con `data-action`

```html
<!-- ✅ PERMITIDO -->
<button data-action="myFunction">Click</button>
```

### Cómo Funciona

1. El sistema escucha clicks en todo el `document`
2. Si el elemento tiene `data-action`, extrae el nombre de la función
3. Busca la función en `window`
4. Llama a la función con 3 parámetros: `(event, element, params)`

### Attributes Soportados

| Attribute | Evento | Uso |
|-----------|--------|-----|
| `data-action` | `click` | Botones, links |
| `data-onchange` | `change` | Inputs, selects |
| `data-onsubmit` | `submit` | Formularios |

### Signature de Funciones

Todas las funciones deben seguir esta signature:

```javascript
function myFunction(event, element, params) {
    // event: Evento DOM original (MouseEvent, ChangeEvent, etc.)
    // element: El elemento que disparó el evento
    // params: Objeto con todos los data-* attributes
}
```

### Parámetros Automáticos

Todos los `data-*` attributes se convierten en objeto `params`:

```html
<button data-action="deleteItem"
        data-item-id="123"
        data-item-name="Producto">
    Delete
</button>
```

```javascript
function deleteItem(event, element, params) {
    console.log(params.itemId);    // "123"
    console.log(params.itemName);  // "Producto"
}

// IMPORTANTE: Exportar a window
window.deleteItem = deleteItem;
```

### Ejemplos Completos

#### Ejemplo 1: Click Simple

```html
<button data-action="showAlert">Click Me</button>

<script nonce="<?= csp_nonce() ?>">
    function showAlert(event, element, params) {
        alert('Botón clickeado!');
    }

    window.showAlert = showAlert;
</script>

<script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
```

#### Ejemplo 2: Click con Parámetros

```html
<button data-action="greet" data-name="Juan" data-age="30">
    Saludar
</button>

<script nonce="<?= csp_nonce() ?>">
    function greet(event, element, params) {
        const message = `Hola ${params.name}, tienes ${params.age} años`;
        ShopUtils.showToast(message, 'info');
    }

    window.greet = greet;
</script>
```

#### Ejemplo 3: Change Event

```html
<select data-onchange="changeCurrency">
    <option value="USD">USD</option>
    <option value="ARS">ARS</option>
</select>

<script nonce="<?= csp_nonce() ?>">
    function changeCurrency(event, element, params) {
        const currency = element.value;
        console.log('Moneda cambiada a:', currency);

        // Actualizar precios
        ShopUtils.updatePrice(currentPrice, currency);
    }

    window.changeCurrency = changeCurrency;
</script>
```

#### Ejemplo 4: Form Submit

```html
<form data-onsubmit="validateForm">
    <input type="text" name="email" required>
    <button type="submit">Enviar</button>
</form>

<script nonce="<?= csp_nonce() ?>">
    function validateForm(event, element, params) {
        event.preventDefault(); // Prevenir submit

        const email = element.email.value;

        if (!email.includes('@')) {
            ShopUtils.showToast('Email inválido', 'error');
            return;
        }

        // Procesar formulario
        element.submit();
    }

    window.validateForm = validateForm;
</script>
```

---

## Otros Módulos

### Currency Switcher

Cambia entre USD y ARS.

**Archivo**: `public_html/assets/js/currency-switcher.js`

**Uso**:
```javascript
// Cambiar moneda
switchCurrency('ARS');

// La página se recargará con la nueva moneda
```

### Cart Validator

Valida el carrito antes del checkout.

**Archivo**: `public_html/assets/js/cart-validator.js`

**Uso**:
```javascript
const isValid = validateCart();

if (!isValid) {
    ShopUtils.showToast('El carrito está vacío', 'error');
}
```

### Mobile Menu

Controla el menú móvil hamburguesa.

**Archivo**: `public_html/assets/js/mobile-menu.js`

**Uso**:
```javascript
// Abrir menú
openMobileMenu();

// Cerrar menú
closeMobileMenu();

// Toggle
toggleMobileMenu();
```

---

## Integración con CSP

### Content Security Policy

El sistema usa CSP estricto que bloquea:

❌ `eval()`
❌ `new Function()`
❌ Inline event handlers (`onclick`, `onchange`, etc.)
❌ Scripts sin nonce

### Reglas para JavaScript

#### 1. Todos los `<script>` inline necesitan nonce

```html
<!-- ✅ CORRECTO -->
<script nonce="<?= csp_nonce() ?>">
    console.log('Hola');
</script>

<!-- ❌ BLOQUEADO -->
<script>
    console.log('Hola');
</script>
```

#### 2. Scripts externos NO necesitan nonce

```html
<!-- ✅ CORRECTO (no necesita nonce) -->
<script src="<?= url('/assets/js/shop-utils.js') ?>"></script>
```

#### 3. Usar data-action en lugar de onclick

```html
<!-- ✅ CORRECTO -->
<button data-action="myFunction">Click</button>

<!-- ❌ BLOQUEADO -->
<button onclick="myFunction()">Click</button>
```

#### 4. Exportar funciones a window

```javascript
// ✅ CORRECTO
function myFunction() { ... }
window.myFunction = myFunction;

// ❌ ERROR: No será encontrada por event-handlers.js
function myFunction() { ... }
```

---

## Buenas Prácticas

### 1. Usar Namespaces

```javascript
// ✅ CORRECTO
window.ShopUtils = {
    formatPrice: function() { ... },
    showToast: function() { ... }
};

// ❌ INCORRECTO (contamina scope global)
function formatPrice() { ... }
function showToast() { ... }
```

### 2. Validar Datos

```javascript
// ✅ CORRECTO
function addToCart(event, element, params) {
    const slug = params.slug;

    if (!slug) {
        ShopUtils.showToast('Error: producto no encontrado', 'error');
        return;
    }

    // Procesar...
}

// ❌ INCORRECTO (sin validación)
function addToCart(event, element, params) {
    ShopCart.addToCart(params.slug, 1);  // Puede fallar si params.slug es undefined
}
```

### 3. Sanitizar Input del Usuario

```javascript
// ✅ CORRECTO
function submitReview(event, element, params) {
    const comment = element.comment.value;
    const sanitized = ShopUtils.sanitizeInput(comment);

    // Enviar sanitized al servidor
}

// ❌ INCORRECTO (posible XSS)
function submitReview(event, element, params) {
    const comment = element.comment.value;
    document.getElementById('preview').innerHTML = comment;  // XSS!
}
```

### 4. Usar Eventos Personalizados

```javascript
// ✅ CORRECTO: Comunicación entre módulos
ShopCart.addToCart = function(slug, qty, data) {
    // ... agregar al carrito

    // Disparar evento
    const event = new CustomEvent('cartUpdated', {
        detail: { cart: this.getCart() }
    });
    document.dispatchEvent(event);
};

// En otro archivo
document.addEventListener('cartUpdated', function(e) {
    updateCartBadge();
});
```

### 5. Documentar Funciones Complejas

```javascript
/**
 * Aplica un cupón de descuento al carrito
 *
 * @param {Event} event - Evento DOM
 * @param {HTMLElement} element - Elemento que disparó el evento
 * @param {Object} params - Parámetros del data-*
 * @returns {void}
 *
 * @example
 * <button data-action="applyCoupon" data-code="VERANO2025">Aplicar</button>
 */
function applyCoupon(event, element, params) {
    // ...
}
```

---

## Crear Módulos Nuevos

### Plantilla Base

```javascript
/**
 * Módulo: ShopNewFeature
 * Descripción: [Qué hace este módulo]
 *
 * @version 1.0.0
 * @author Tu Nombre
 */

(function() {
    'use strict';

    // Namespace
    window.ShopNewFeature = {

        /**
         * Inicializar módulo
         */
        init: function() {
            console.log('ShopNewFeature inicializado');
            this.loadFromStorage();
            this.attachEvents();
        },

        /**
         * Cargar datos desde localStorage
         */
        loadFromStorage: function() {
            const data = localStorage.getItem('shop_new_feature');
            return data ? JSON.parse(data) : [];
        },

        /**
         * Guardar datos en localStorage
         */
        saveToStorage: function(data) {
            localStorage.setItem('shop_new_feature', JSON.stringify(data));
        },

        /**
         * Adjuntar event listeners
         */
        attachEvents: function() {
            // Eventos personalizados si es necesario
            document.addEventListener('featureEvent', (e) => {
                this.handleFeatureEvent(e.detail);
            });
        },

        /**
         * Método público 1
         */
        doSomething: function(param) {
            console.log('Doing something with:', param);
            // ...
        },

        /**
         * Método público 2
         */
        getSomething: function() {
            return this.loadFromStorage();
        }

    };

    // Auto-inicializar al cargar DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.ShopNewFeature.init();
        });
    } else {
        window.ShopNewFeature.init();
    }

})();
```

### Checklist

Al crear un nuevo módulo:

- [ ] Usar IIFE para encapsular código
- [ ] Crear namespace en `window.ShopModuleName`
- [ ] Documentar funciones públicas con JSDoc
- [ ] Validar parámetros de entrada
- [ ] Usar `localStorage` para persistencia
- [ ] Disparar eventos personalizados si afecta otros módulos
- [ ] Auto-inicializar al cargar DOM
- [ ] Probar en todas las páginas que lo usan
- [ ] Agregar a esta documentación

---

*Última actualización: 2025-12-08*
