# Componentes Reutilizables - Shop V2

Documentación completa de todos los componentes frontend reutilizables del sistema.

**Última actualización**: 2025-12-08

---

## 📑 Tabla de Contenidos

- [Introducción](#introducción)
- [Convenciones](#convenciones)
- [Cart Panel](#cart-panel)
- [Favorites Panel](#favorites-panel)
- [Product Card](#product-card)
- [Review Card](#review-card)
- [Quantity Selector](#quantity-selector)
- [Coupon Form](#coupon-form)
- [Breadcrumb](#breadcrumb)
- [Share Buttons](#share-buttons)
- [Crear Componentes Nuevos](#crear-componentes-nuevos)
- [Buenas Prácticas](#buenas-prácticas)

---

## Introducción

Los componentes son funciones PHP que generan HTML reutilizable. Se diseñaron para:

✅ **Eliminar código duplicado** - Mismos componentes en múltiples páginas
✅ **Facilitar mantenimiento** - Un solo lugar para modificar
✅ **Garantizar consistencia** - Mismo aspecto y comportamiento
✅ **Simplificar desarrollo** - Menos HTML por escribir

**Ubicación**: Todos los componentes están en `app/includes/frontend/`

---

## Convenciones

### Estructura de un Componente

```php
<?php
/**
 * Nombre del Componente
 * Descripción breve de qué hace
 *
 * @param array $data Datos principales del componente
 * @param array $options Opciones de configuración (opcional)
 * @return void Imprime HTML directamente
 */
function render_nombre_componente($data = [], $options = []) {
    // 1. Extraer opciones con valores por defecto
    $option_1 = $options['option_1'] ?? 'default_value';
    $option_2 = $options['option_2'] ?? true;

    // 2. Procesar datos si es necesario
    $processed_data = process_data($data);

    // 3. Renderizar HTML
    ?>
    <div class="componente">
        <!-- HTML aquí -->
    </div>
    <?php
}
?>
```

### Naming Convention

| Tipo | Formato | Ejemplo |
|------|---------|---------|
| **Archivo** | `kebab-case.php` | `product-card.php` |
| **Función** | `render_snake_case()` | `render_product_card()` |
| **Clases CSS** | `kebab-case` | `.product-card` |

### Uso en Páginas

```php
<?php
// 1. Incluir componente
require_once APP_PATH . '/includes/frontend/product-card.php';

// 2. Llamar función con datos
render_product_card($product, [
    'currency' => 'USD',
    'show_favorite_btn' => true
]);
?>
```

---

## Cart Panel

Panel lateral que muestra el contenido del carrito.

### Ubicación

- **Archivo**: `app/includes/frontend/cart-panel.php`
- **Función**: `render_cart_panel($options = [])`

### Opciones

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `show_go_to_page_btn` | `bool` | `true` | Mostrar botón "Ir al Carrito" |

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/cart-panel.php';

// Uso básico (todos los botones visibles)
render_cart_panel();

// Personalizado (ocultar botón de ir al carrito)
render_cart_panel(['show_go_to_page_btn' => false]);
?>
```

### HTML Generado

```html
<div class="cart-panel" id="cart-panel">
    <div class="cart-panel-header">
        <h2>🛒 Tu Carrito</h2>
        <button class="cart-close" data-action="closeCartPanel">&times;</button>
    </div>
    <div class="cart-items" id="cart-items">
        <!-- Items del carrito (generados por JavaScript) -->
    </div>
    <div class="cart-footer">
        <div class="cart-total">
            Total: <span id="cart-total-price">$0.00</span>
        </div>
        <button data-action="goToCheckout" class="btn-checkout">
            💳 Ir a Pagar
        </button>
        <button data-action="goToCartPage" class="btn-go-to-cart">
            🛒 Ir al Carrito
        </button>
    </div>
</div>
```

### JavaScript Requerido

- `shop-cart.js` - Gestión de carrito (localStorage)
- `event-handlers.js` - Delegación de eventos

### Funciones Relacionadas

```javascript
// Abrir panel
ShopCart.openCartPanel();

// Cerrar panel
ShopCart.closeCartPanel();

// Obtener items
const items = ShopCart.getCart();
```

---

## Favorites Panel

Panel lateral que muestra los productos favoritos.

### Ubicación

- **Archivo**: `app/includes/frontend/favorites-panel.php`
- **Función**: `render_favorites_panel($options = [])`

### Opciones

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `show_go_to_page_btn` | `bool` | `true` | Mostrar botón "Ver Todos" |

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/favorites-panel.php';

// Uso básico
render_favorites_panel();

// Sin botón de "Ver Todos"
render_favorites_panel(['show_go_to_page_btn' => false]);
?>
```

### HTML Generado

```html
<div class="cart-panel" id="favorites-panel">
    <div class="cart-panel-header">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            Mis Favoritos
        </h2>
        <button class="cart-close" data-action="closeFavoritesPanel">&times;</button>
    </div>
    <div class="favorites-items" id="favorites-items">
        <!-- Items de favoritos (generados por JavaScript) -->
    </div>
    <div class="cart-footer">
        <button data-action="goToFavoritesPage" class="btn-go-to-favorites">
            ❤️ Ver Todos los Favoritos
        </button>
    </div>
</div>
```

### JavaScript Requerido

- `shop-favorites.js` - Gestión de favoritos (localStorage)
- `event-handlers.js` - Delegación de eventos

### Funciones Relacionadas

```javascript
// Abrir panel
ShopFavorites.openFavoritesPanel();

// Cerrar panel
ShopFavorites.closeFavoritesPanel();

// Obtener favoritos
const favorites = ShopFavorites.getFavorites();
```

---

## Product Card

Tarjeta visual de un producto con imagen, nombre, precio y acciones.

### Ubicación

- **Archivo**: `app/includes/frontend/product-card.php`
- **Función**: `render_product_card($product, $options = [])`

### Parámetros

#### `$product` (array)

Datos del producto:

```php
[
    'slug' => 'producto-ejemplo',
    'name' => 'Producto Ejemplo',
    'thumbnail' => '/uploads/products/producto-1-thumb.jpg',
    'price' => 1000.00,
    'stock' => 50,
    'discount_price' => 850.00,  // Opcional
    'promotion' => [             // Opcional
        'type' => 'percentage',
        'value' => 15
    ]
]
```

#### `$options` (array)

Opciones de configuración:

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `currency` | `string` | `'USD'` | Moneda para mostrar precio |
| `show_favorite_btn` | `bool` | `true` | Mostrar botón de favoritos |
| `show_add_to_cart` | `bool` | `true` | Mostrar botón de agregar |
| `show_promotion_ribbon` | `bool` | `true` | Mostrar ribbon de promoción |

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/product-card.php';

// Uso básico
render_product_card($product);

// Personalizado
render_product_card($product, [
    'currency' => 'ARS',
    'show_favorite_btn' => true,
    'show_add_to_cart' => false
]);

// En loop
foreach ($products as $product) {
    render_product_card($product, ['currency' => $selected_currency]);
}
?>
```

### HTML Generado

```html
<div class="product-card" data-action="goToProductPage" data-slug="producto-ejemplo">
    <!-- Promotion Ribbon (si aplica) -->
    <div class="promotion-ribbon">
        <span>-15%</span>
    </div>

    <!-- Imagen -->
    <div class="product-image">
        <img src="/shopv2/uploads/products/producto-1-thumb.jpg"
             alt="Producto Ejemplo"
             style="width: 100%; height: 100%; object-fit: cover;">
    </div>

    <!-- Información -->
    <div class="product-info">
        <h3 class="product-name">Producto Ejemplo</h3>

        <!-- Precio con descuento -->
        <div class="product-price">
            <span class="price-original">$1,000.00</span>
            <span class="price-discount">$850.00</span>
        </div>

        <!-- Acciones -->
        <div class="product-actions">
            <button class="btn-favorite"
                    data-action="toggleFavorite"
                    data-slug="producto-ejemplo"
                    title="Agregar a favoritos">
                ♥
            </button>
            <button class="btn-add-to-cart"
                    data-action="addToCartFromCard"
                    data-slug="producto-ejemplo">
                🛒 Agregar
            </button>
        </div>
    </div>
</div>
```

### Estilos CSS

Los estilos están en `public_html/assets/themes/_base/components.css`:

```css
.product-card {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

.product-image {
    width: 100%;
    height: 250px;
    overflow: hidden;
}

.promotion-ribbon {
    position: absolute;
    top: 10px;
    right: -30px;
    background: var(--color-danger);
    color: white;
    padding: 5px 40px;
    transform: rotate(45deg);
}
```

---

## Review Card

Tarjeta de una review de producto con calificación, autor y fecha.

### Ubicación

- **Archivo**: `app/includes/frontend/review-card.php`
- **Función**: `render_review_card($review)`

### Parámetros

#### `$review` (array)

```php
[
    'id' => '1',
    'product_slug' => 'producto-ejemplo',
    'user_name' => 'Juan Pérez',
    'rating' => 5,
    'comment' => 'Excelente producto, muy recomendado!',
    'created_at' => '2025-01-15 10:30:00'
]
```

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/review-card.php';

// Una review
render_review_card($review);

// En loop
foreach ($reviews as $review) {
    render_review_card($review);
}
?>
```

### HTML Generado

```html
<div class="review">
    <div class="review-header">
        <span class="review-author">Juan Pérez</span>
        <span class="review-date">15/01/2025</span>
    </div>
    <div class="review-rating">
        <span class="star filled">★</span>
        <span class="star filled">★</span>
        <span class="star filled">★</span>
        <span class="star filled">★</span>
        <span class="star filled">★</span>
    </div>
    <div class="review-comment">
        <p>Excelente producto, muy recomendado!</p>
    </div>
</div>
```

### Generación de Estrellas

El componente incluye lógica para generar estrellas llenas y vacías:

```php
// Rating de 4/5
for ($i = 1; $i <= 5; $i++) {
    if ($i <= $review['rating']) {
        echo '<span class="star filled">★</span>';
    } else {
        echo '<span class="star">☆</span>';
    }
}
```

---

## Quantity Selector

Selector de cantidad con botones +/- y input numérico.

### Ubicación

- **Archivo**: `app/includes/frontend/quantity-selector.php`
- **Función**: `render_quantity_selector($options = [])`

### Opciones

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `id` | `string` | `'quantity-input'` | ID del input |
| `value` | `int` | `1` | Valor inicial |
| `min` | `int` | `1` | Valor mínimo |
| `max` | `int` | `999` | Valor máximo |
| `disabled` | `bool` | `false` | Deshabilitar selector |

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/quantity-selector.php';

// Uso básico
render_quantity_selector();

// Personalizado
render_quantity_selector([
    'id' => 'product-qty',
    'value' => 2,
    'min' => 1,
    'max' => $product['stock'],
    'disabled' => $product['stock'] === 0
]);
?>
```

### HTML Generado

```html
<div class="quantity-selector-modern">
    <button class="quantity-btn-modern"
            data-action="decreaseQuantity"
            data-input-id="quantity-input">
        <i class="fas fa-minus"></i>
    </button>
    <input type="number"
           id="quantity-input"
           value="1"
           min="1"
           max="50"
           readonly>
    <button class="quantity-btn-modern"
            data-action="increaseQuantity"
            data-input-id="quantity-input">
        <i class="fas fa-plus"></i>
    </button>
</div>
```

### JavaScript Requerido

Las funciones `increaseQuantity` y `decreaseQuantity` deben estar definidas en la página:

```javascript
function increaseQuantity(event, element, params) {
    const input = document.getElementById(params.inputId);
    const max = parseInt(input.max);
    const current = parseInt(input.value);
    if (current < max) {
        input.value = current + 1;
    }
}

function decreaseQuantity(event, element, params) {
    const input = document.getElementById(params.inputId);
    const min = parseInt(input.min);
    const current = parseInt(input.value);
    if (current > min) {
        input.value = current - 1;
    }
}

window.increaseQuantity = increaseQuantity;
window.decreaseQuantity = decreaseQuantity;
```

---

## Coupon Form

Formulario para aplicar cupones de descuento.

### Ubicación

- **Archivo**: `app/includes/frontend/coupon-form.php`
- **Función**: `render_coupon_form()`

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/coupon-form.php';
render_coupon_form();
?>
```

### HTML Generado

```html
<div class="coupon-section">
    <div class="coupon-input">
        <input type="text"
               id="couponCode"
               placeholder="Código de cupón"
               maxlength="20">
        <button data-action="applyCoupon">Aplicar</button>
    </div>
    <small style="display: block; color: #666; font-size: 0.85rem; margin-top: 0.5rem;">
        Solo se puede aplicar un cupón por compra
    </small>
    <div id="couponApplied" style="display: none;">
        <!-- Cupón aplicado (generado por JavaScript) -->
    </div>
</div>
```

### JavaScript Requerido

La función `applyCoupon` debe validar y aplicar el cupón:

```javascript
function applyCoupon(event, element, params) {
    const code = document.getElementById('couponCode').value.trim();

    if (!code) {
        ShopUtils.showToast('Ingresa un código de cupón', 'error');
        return;
    }

    // Validar cupón con backend
    fetch('<?= url('/api/validate-coupon.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code })
    })
    .then(res => res.json())
    .then(data => {
        if (data.valid) {
            // Aplicar descuento
            document.getElementById('couponApplied').style.display = 'block';
            document.getElementById('couponApplied').innerHTML =
                `✅ Cupón "${code}" aplicado: -${data.discount}%`;
        } else {
            ShopUtils.showToast('Cupón inválido o expirado', 'error');
        }
    });
}

window.applyCoupon = applyCoupon;
```

---

## Breadcrumb

Navegación breadcrumb para indicar la ubicación actual.

### Ubicación

- **Archivo**: `app/includes/frontend/breadcrumb.php`
- **Función**: `render_breadcrumb($items, $options = [])`

### Parámetros

#### `$items` (array)

Array de items del breadcrumb:

```php
[
    ['label' => 'Inicio', 'url' => url('/')],
    ['label' => 'Productos', 'url' => url('/productos')],
    ['label' => 'Producto Actual', 'url' => null]  // null = item activo
]
```

#### `$options` (array)

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `separator` | `string` | `'/'` | Separador entre items |
| `home_icon` | `string` | `'🏠'` | Icono para item "Inicio" |

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/breadcrumb.php';

// Uso básico
render_breadcrumb([
    ['label' => 'Inicio', 'url' => url('/')],
    ['label' => 'Productos', 'url' => null]
]);

// Con separador personalizado
render_breadcrumb([
    ['label' => 'Inicio', 'url' => url('/')],
    ['label' => 'Categoría', 'url' => url('/categoria/tecnologia')],
    ['label' => 'Producto', 'url' => null]
], ['separator' => '>']);
?>
```

### HTML Generado

```html
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="/shopv2/">🏠 Inicio</a>
        </li>
        <li class="breadcrumb-separator">/</li>
        <li class="breadcrumb-item">
            <a href="/shopv2/productos">Productos</a>
        </li>
        <li class="breadcrumb-separator">/</li>
        <li class="breadcrumb-item active" aria-current="page">
            Producto Actual
        </li>
    </ol>
</nav>
```

### Accesibilidad

El componente incluye atributos ARIA para mejorar la accesibilidad:
- `aria-label="breadcrumb"` en el `<nav>`
- `aria-current="page"` en el item activo

---

## Share Buttons

Botones para compartir en redes sociales y copiar enlace.

### Ubicación

- **Archivo**: `app/includes/frontend/share-buttons.php`
- **Función**: `render_share_buttons($data)`

### Parámetros

#### `$data` (array)

```php
[
    'url' => 'https://ejemplo.com/producto/ejemplo',
    'title' => 'Producto Ejemplo'
]
```

### Uso

```php
<?php
require_once APP_PATH . '/includes/frontend/share-buttons.php';

render_share_buttons([
    'url' => get_base_url() . '/producto/' . $product['slug'],
    'title' => $product['name']
]);
?>
```

### HTML Generado

```html
<div class="share-section-modern">
    <!-- Copiar Link -->
    <button class="share-btn-icon"
            data-action="copyLink"
            data-url="https://ejemplo.com/producto/ejemplo"
            title="Copiar enlace">
        <i class="fas fa-link"></i>
    </button>

    <!-- Facebook -->
    <a href="https://www.facebook.com/sharer/sharer.php?u=https://ejemplo.com/producto/ejemplo"
       class="share-btn-icon"
       target="_blank"
       rel="noopener noreferrer"
       title="Compartir en Facebook">
        <i class="fab fa-facebook-f"></i>
    </a>

    <!-- Twitter -->
    <a href="https://twitter.com/intent/tweet?url=https://ejemplo.com/producto/ejemplo&text=Producto%20Ejemplo"
       class="share-btn-icon"
       target="_blank"
       rel="noopener noreferrer"
       title="Compartir en Twitter">
        <i class="fab fa-twitter"></i>
    </a>

    <!-- WhatsApp -->
    <a href="https://wa.me/?text=Producto%20Ejemplo%20https://ejemplo.com/producto/ejemplo"
       class="share-btn-icon"
       target="_blank"
       rel="noopener noreferrer"
       title="Compartir en WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
</div>
```

### JavaScript Requerido

La función `copyLink` copia la URL al portapapeles:

```javascript
function copyLink(event, element, params) {
    const url = params.url;

    navigator.clipboard.writeText(url).then(() => {
        ShopUtils.showToast('✅ Enlace copiado al portapapeles', 'success');
    }).catch(() => {
        ShopUtils.showToast('❌ Error al copiar enlace', 'error');
    });
}

window.copyLink = copyLink;
```

---

## Crear Componentes Nuevos

### Plantilla Base

```php
<?php
/**
 * Nombre del Componente
 * Descripción de lo que hace
 */

// Security check
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderizar [Nombre del Componente]
 *
 * @param array $data Datos principales
 * @param array $options Opciones de configuración
 * @return void
 */
function render_mi_componente($data = [], $options = []) {
    // Extraer opciones con defaults
    $show_something = $options['show_something'] ?? true;
    $custom_class = $options['custom_class'] ?? '';

    // Validar datos requeridos
    if (empty($data)) {
        return;
    }

    // Preparar variables
    $title = htmlspecialchars($data['title'] ?? '');
    $description = htmlspecialchars($data['description'] ?? '');

    ?>
    <div class="mi-componente <?php echo $custom_class; ?>">
        <h3><?php echo $title; ?></h3>
        <p><?php echo $description; ?></p>

        <?php if ($show_something): ?>
        <div class="extra-content">
            <!-- Contenido condicional -->
        </div>
        <?php endif; ?>

        <!-- Botones con event delegation -->
        <button data-action="myAction"
                data-id="<?php echo htmlspecialchars($data['id']); ?>">
            Hacer algo
        </button>
    </div>
    <?php
}
?>
```

### Checklist

Al crear un nuevo componente, verificar:

- [ ] Security check `if (!defined('APP_ENTRY_POINT'))`
- [ ] Función con prefijo `render_`
- [ ] Parámetros documentados con PHPDoc
- [ ] Opciones con valores por defecto
- [ ] Sanitización con `htmlspecialchars()` en output
- [ ] Event delegation con `data-action` (NO `onclick`)
- [ ] CSS en `public_html/assets/themes/_base/components.css`
- [ ] Documentación en este archivo

---

## Buenas Prácticas

### 1. Siempre Sanitizar Output

```php
// ✅ CORRECTO
<h3><?php echo htmlspecialchars($product['name']); ?></h3>

// ❌ INCORRECTO
<h3><?php echo $product['name']; ?></h3>
```

### 2. Usar Event Delegation

```php
// ✅ CORRECTO
<button data-action="myFunction" data-param="value">Click</button>

// ❌ INCORRECTO
<button onclick="myFunction('value')">Click</button>
```

### 3. Opciones con Defaults

```php
// ✅ CORRECTO
function render_component($data, $options = []) {
    $show_button = $options['show_button'] ?? true;
    $custom_class = $options['custom_class'] ?? '';
}

// ❌ INCORRECTO
function render_component($data, $show_button, $custom_class) {
    // Fuerza pasar todos los parámetros
}
```

### 4. Validar Datos Requeridos

```php
// ✅ CORRECTO
function render_component($data) {
    if (empty($data['required_field'])) {
        return; // No renderizar si faltan datos
    }
    // ...
}

// ❌ INCORRECTO
function render_component($data) {
    echo $data['required_field']; // Error si no existe
}
```

### 5. Usar url() Helper

```php
// ✅ CORRECTO
<img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>">

// ❌ INCORRECTO
<img src="<?php echo $product['thumbnail']; ?>">
```

### 6. Separar Lógica de Presentación

```php
// ✅ CORRECTO
function render_component($data) {
    // Preparar datos
    $formatted_price = format_currency($data['price']);
    $is_available = $data['stock'] > 0;

    // Renderizar
    ?>
    <div>
        <p><?php echo $formatted_price; ?></p>
        <?php if ($is_available): ?>
            <button>Comprar</button>
        <?php endif; ?>
    </div>
    <?php
}

// ❌ INCORRECTO
function render_component($data) {
    ?>
    <div>
        <p>$<?php echo number_format($data['price'], 2); ?></p>
        <?php if ($data['stock'] > 0): ?>
            <button>Comprar</button>
        <?php endif; ?>
    </div>
    <?php
}
```

### 7. Documentar Componentes Complejos

```php
/**
 * Renderizar tarjeta de producto
 *
 * @param array $product Datos del producto (slug, name, price, thumbnail, stock)
 * @param array $options Opciones:
 *                       - currency (string): Moneda para precio (default: 'USD')
 *                       - show_favorite_btn (bool): Mostrar botón favorito (default: true)
 *                       - show_add_to_cart (bool): Mostrar botón agregar (default: true)
 * @return void
 *
 * @example
 * render_product_card($product, ['currency' => 'ARS', 'show_favorite_btn' => false]);
 */
function render_product_card($product, $options = []) {
    // ...
}
```

---

## Componentes Futuros

Componentes planeados para implementar:

- [ ] **Rating Stars** - Selector de calificación (1-5 estrellas)
- [ ] **Product Gallery** - Galería de imágenes con zoom
- [ ] **Pagination** - Paginación de resultados
- [ ] **Filter Sidebar** - Filtros de productos (precio, categoría, etc.)
- [ ] **Newsletter Form** - Formulario de suscripción
- [ ] **Notification Badge** - Badge con contador (carrito, favoritos)
- [ ] **Loading Spinner** - Spinner de carga
- [ ] **Empty State** - Mensaje cuando no hay resultados

---

*Última actualización: 2025-12-08*
