# Arquitectura del Sistema - Shop V2

Documentación completa de la arquitectura del sistema de e-commerce Shop V2.

**Última actualización**: 2025-12-08

---

## 📑 Tabla de Contenidos

- [Principios de Arquitectura](#principios-de-arquitectura)
- [Estructura de Directorios](#estructura-de-directorios)
- [Puntos de Entrada](#puntos-de-entrada)
- [Sistema de Routing](#sistema-de-routing)
- [Bootstrap y Carga del Sistema](#bootstrap-y-carga-del-sistema)
- [Almacenamiento de Datos](#almacenamiento-de-datos)
- [Sistema de Themes](#sistema-de-themes)
- [Sistema de Componentes](#sistema-de-componentes)
- [JavaScript Modular](#javascript-modular)
- [Integración de Pagos](#integración-de-pagos)
- [Sistema de Seguridad](#sistema-de-seguridad)
- [Flujo de Requests](#flujo-de-requests)

---

## Principios de Arquitectura

### 1. Seguridad por Diseño

**Principio fundamental**: TODO el código privado está fuera de `public_html/` y es inaccesible vía HTTP.

```
shop-v2/
├── app/              # PRIVADO (fuera del web root)
└── public_html/      # PÚBLICO (web root del servidor)
```

### 2. Puntos de Entrada Únicos

El sistema tiene exactamente **4 puntos de entrada** PHP:

1. `public_html/index.php` - Frontend público
2. `public_html/admin/index.php` - Panel administrativo
3. `public_html/admin/login.php` - Login de admin
4. `public_html/webhook.php` - Webhooks externos (MercadoPago)

Todos los demás archivos PHP en `app/` están protegidos y no pueden ser accedidos directamente.

### 3. Arquitectura MVC Simplificada

```
Model (JSON)  ←→  Controller (pages/)  ←→  View (components/)
    ↓                    ↓                        ↓
app/data/          app/pages/            app/includes/
```

- **Models**: Archivos JSON en `app/data/` (products, orders, etc.)
- **Controllers**: Scripts PHP en `app/pages/` (lógica de negocio)
- **Views**: Componentes reutilizables en `app/includes/frontend/` y `app/includes/admin/`

### 4. Separación de Concerns

```
Sistema
├── Core (functions, security, auth)      → app/includes/
├── Business Logic (products, orders)     → app/includes/
├── Presentation (components, themes)     → app/includes/frontend/, public_html/assets/
└── Data (JSON storage)                   → app/data/
```

---

## Estructura de Directorios

### Árbol Completo Comentado

```
shop-v2/
│
├── app/                                    # Código privado (INACCESIBLE vía HTTP)
│   │
│   ├── config/                             # Configuración sensible
│   │   ├── config.php                      # Config principal (auto-generado, gitignored)
│   │   ├── config.example.php              # Template para config.php
│   │   ├── paths.php                       # Definición de paths del sistema
│   │   ├── theme.json                      # Theme activo actual
│   │   ├── site.json                       # Metadata del sitio (nombre, logo)
│   │   ├── payment.json                    # Credenciales de MercadoPago
│   │   ├── footer.json                     # Configuración del footer
│   │   └── currency.json                   # Config de monedas y exchange rate
│   │
│   ├── includes/                           # Sistema de funciones y librerías
│   │   │
│   │   ├── bootstrap.php                   # Inicialización del sistema completo
│   │   ├── functions.php                   # Funciones core (JSON, redirects, etc.)
│   │   ├── security.php                    # Security headers, CSP, CSRF
│   │   ├── auth.php                        # Autenticación y gestión de sesiones
│   │   ├── router.php                      # Router class (URLs limpias)
│   │   ├── theme-loader.php                # Sistema de themes + cache CSS
│   │   │
│   │   ├── products.php                    # CRUD de productos
│   │   ├── orders.php                      # Gestión de pedidos
│   │   ├── mercadopago.php                 # Integración MercadoPago API
│   │   ├── email.php                       # Sistema de envío de emails
│   │   ├── telegram.php                    # Notificaciones vía Telegram
│   │   ├── coupons.php                     # Sistema de cupones de descuento
│   │   ├── promotions.php                  # Sistema de promociones
│   │   │
│   │   ├── frontend/                       # Componentes reutilizables frontend
│   │   │   ├── cart-panel.php              # Panel lateral de carrito
│   │   │   ├── favorites-panel.php         # Panel lateral de favoritos
│   │   │   ├── product-card.php            # Tarjeta de producto
│   │   │   ├── review-card.php             # Tarjeta de review
│   │   │   ├── quantity-selector.php       # Selector de cantidad
│   │   │   ├── coupon-form.php             # Formulario de cupones
│   │   │   ├── breadcrumb.php              # Navegación breadcrumb
│   │   │   └── share-buttons.php           # Botones de compartir en redes
│   │   │
│   │   └── admin/                          # Componentes del panel admin
│   │       ├── header.php                  # Header con usuario y logout
│   │       ├── sidebar.php                 # Sidebar de navegación
│   │       ├── modal.php                   # Modal reutilizable (NO usar alert())
│   │       ├── styles.php                  # Estilos centralizados del admin
│   │       └── admin-common-styles.php     # Estilos comunes entre páginas
│   │
│   ├── pages/                              # Vistas y lógica de páginas
│   │   │
│   │   ├── frontend/                       # Páginas públicas del sitio
│   │   │   ├── home.php                    # Página de inicio (/)
│   │   │   ├── producto.php                # Detalle de producto (/producto/:slug)
│   │   │   ├── carrito.php                 # Página de carrito (/carrito)
│   │   │   ├── checkout.php                # Proceso de checkout (/checkout)
│   │   │   ├── favoritos.php               # Lista de favoritos (/favoritos)
│   │   │   ├── track.php                   # Seguimiento de pedido (/track)
│   │   │   ├── buscar.php                  # Búsqueda de productos (/buscar)
│   │   │   ├── preview.php                 # Preview de themes (/preview)
│   │   │   └── maintenance.php             # Página de mantenimiento
│   │   │
│   │   └── admin/                          # Páginas del panel de administración
│   │       ├── index.php                   # Dashboard (?page=index)
│   │       ├── productos-listado.php       # Lista de productos
│   │       ├── productos-nuevo.php         # Crear producto
│   │       ├── productos-editar.php        # Editar producto
│   │       ├── ventas.php                  # Lista de ventas
│   │       ├── archivo-ventas.php          # Archivo de ventas antiguas
│   │       ├── cupones-listado.php         # Lista de cupones
│   │       ├── cupones-nuevo.php           # Crear cupón
│   │       ├── cupones-editar.php          # Editar cupón
│   │       ├── promociones-listado.php     # Lista de promociones
│   │       ├── promociones-nuevo.php       # Crear promoción
│   │       ├── config-sitio.php            # Config general del sitio
│   │       ├── config-payment.php          # Config de MercadoPago
│   │       ├── config-themes.php           # Selector de themes
│   │       └── config-currency.php         # Config de monedas
│   │
│   └── data/                               # Almacenamiento JSON (file-locked)
│       ├── products.json                   # Lista maestra de productos
│       ├── products/                       # Archivos individuales por producto
│       │   ├── producto-1.json
│       │   ├── producto-2.json
│       │   └── ...
│       ├── orders.json                     # Pedidos activos
│       ├── archived_orders.json            # Pedidos archivados (>30 días)
│       ├── coupons.json                    # Cupones de descuento
│       ├── promotions.json                 # Promociones activas
│       ├── reviews.json                    # Reviews de productos
│       ├── webhook_log.json                # Log de webhooks recibidos
│       └── mp_logs.json                    # Logs detallados de MercadoPago
│
└── public_html/                            # Código público (WEB ROOT)
    │
    ├── .htaccess                           # Configuración Apache (rewrite rules)
    ├── index.php                           # ⚡ PUNTO ENTRADA: Frontend
    ├── webhook.php                         # ⚡ PUNTO ENTRADA: Webhooks MercadoPago
    │
    ├── admin/                              # Panel de administración
    │   ├── .htaccess                       # Protección adicional del admin
    │   ├── index.php                       # ⚡ PUNTO ENTRADA: Admin panel
    │   └── login.php                       # ⚡ PUNTO ENTRADA: Admin login
    │
    ├── assets/                             # Assets públicos (CSS, JS, images)
    │   │
    │   ├── themes/                         # Sistema de themes modular
    │   │   │
    │   │   ├── _base/                      # CSS base compartido por todos los themes
    │   │   │   ├── reset.css               # Reset de estilos del navegador
    │   │   │   ├── layout.css              # Sistema de layout y grid
    │   │   │   ├── components.css          # Componentes base (botones, cards, etc.)
    │   │   │   ├── utilities.css           # Utilidades (spacing, colors, etc.)
    │   │   │   ├── pages.css               # Estilos globales de páginas
    │   │   │   └── pages/                  # CSS específico por página
    │   │   │       ├── home.css            # Estilos exclusivos de home
    │   │   │       ├── producto.css        # Estilos exclusivos de producto
    │   │   │       ├── carrito.css         # Estilos exclusivos de carrito
    │   │   │       ├── checkout.css        # Estilos exclusivos de checkout
    │   │   │       └── ...
    │   │   │
    │   │   ├── minimal/                    # Theme "Minimal" (default)
    │   │   │   ├── theme.json              # Metadata del theme (nombre, autor, etc.)
    │   │   │   ├── variables.css           # Variables CSS del theme
    │   │   │   └── theme.css               # Estilos específicos del theme
    │   │   │
    │   │   └── classic/                    # Theme "Classic"
    │   │       ├── theme.json
    │   │       ├── variables.css
    │   │       └── theme.css
    │   │
    │   ├── js/                             # JavaScript modular
    │   │   ├── event-handlers.js           # Sistema de delegación de eventos (CSP)
    │   │   ├── shop-utils.js               # Namespace ShopUtils (formateo, toast, etc.)
    │   │   ├── shop-cart.js                # Namespace ShopCart (gestión de carrito)
    │   │   ├── shop-favorites.js           # Namespace ShopFavorites (gestión de favoritos)
    │   │   ├── currency-switcher.js        # Cambio de moneda (USD/ARS)
    │   │   ├── cart-validator.js           # Validación de carrito al checkout
    │   │   └── mobile-menu.js              # Menú móvil hamburguesa
    │   │
    │   ├── css/                            # CSS complementarios
    │   │   └── mobile-menu.css             # Estilos del menú móvil
    │   │
    │   └── cache/                          # CSS cacheado (auto-generado, gitignored)
    │       ├── .gitignore                  # Ignorar archivos de cache
    │       └── theme-*.min.css             # Archivos CSS minificados con hash MD5
    │
    ├── uploads/                            # Archivos subidos por usuarios
    │   └── products/                       # Imágenes de productos
    │       ├── producto-1.jpg
    │       ├── producto-1-thumb.jpg
    │       └── ...
    │
    └── install/                            # Instalador del sistema (eliminar post-install)
        ├── installer.php                   # Wizard de instalación interactivo
        └── README.md                       # Instrucciones del instalador
```

---

## Puntos de Entrada

### 1. Frontend Entry Point (`public_html/index.php`)

**Responsabilidades**:
- Inicializar el sistema con `bootstrap.php`
- Definir `APP_ENTRY_POINT` constant
- Cargar el Router
- Mapear rutas a archivos PHP
- Renderizar la página solicitada

**Flujo**:
```php
<?php
// 1. Definir punto de entrada
define('APP_ENTRY_POINT', true);

// 2. Cargar bootstrap
require_once '../app/includes/bootstrap.php';

// 3. Crear router
$router = new Router();

// 4. Definir rutas
$router->get('/', APP_PATH . '/pages/frontend/home.php');
$router->get('/producto/:slug', APP_PATH . '/pages/frontend/producto.php');
$router->get('/carrito', APP_PATH . '/pages/frontend/carrito.php');
// ... más rutas

// 5. Resolver y ejecutar
$router->resolve($_SERVER['REQUEST_URI']);
?>
```

### 2. Admin Entry Point (`public_html/admin/index.php`)

**Responsabilidades**:
- Inicializar el sistema
- Definir `APP_ENTRY_POINT` y `ADMIN_AREA`
- Requerir autenticación (`require_admin()`)
- Mapear query parameter `?page=` a archivos
- Cargar página del admin

**Flujo**:
```php
<?php
// 1. Definir puntos de entrada
define('APP_ENTRY_POINT', true);
define('ADMIN_AREA', true);

// 2. Cargar bootstrap
require_once '../../app/includes/bootstrap.php';

// 3. Requerir autenticación
require_admin();

// 4. Mapear páginas
$page = $_GET['page'] ?? 'index';
$pages_map = [
    'index' => APP_PATH . '/pages/admin/index.php',
    'productos-listado' => APP_PATH . '/pages/admin/productos-listado.php',
    // ... más páginas
];

// 5. Cargar página
if (isset($pages_map[$page])) {
    require $pages_map[$page];
} else {
    header('HTTP/1.0 404 Not Found');
    echo '404 - Página no encontrada';
}
?>
```

### 3. Admin Login (`public_html/admin/login.php`)

**Responsabilidades**:
- Inicializar sistema mínimo (sin requerer auth)
- Procesar formulario de login
- Validar credenciales
- Crear sesión
- Redirigir a admin panel

### 4. Webhook Entry Point (`public_html/webhook.php`)

**Responsabilidades**:
- Inicializar sistema
- Validar origen de request (IPs de MercadoPago)
- Verificar firma HMAC (X-Signature)
- Rate limiting
- Procesar notificación
- Actualizar estado del pedido

**Flujo**:
```php
<?php
// 1. Definir entrada
define('APP_ENTRY_POINT', true);

// 2. Cargar bootstrap
require_once '../app/includes/bootstrap.php';

// 3. Rate limiting
check_rate_limit('webhook', 100, 60);

// 4. Validar IP
if (!is_mercadopago_ip($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit;
}

// 5. Validar firma
if (!validate_webhook_signature($_SERVER['HTTP_X_SIGNATURE'])) {
    http_response_code(401);
    exit;
}

// 6. Procesar notificación
$data = json_decode(file_get_contents('php://input'), true);
process_mercadopago_notification($data);

// 7. Responder
http_response_code(200);
?>
```

---

## Sistema de Routing

### Router Class (`app/includes/router.php`)

El Router maneja las URLs limpias del frontend.

**Características**:
- URLs amigables (`/producto/nombre` en lugar de `?page=producto&slug=nombre`)
- Parámetros dinámicos (`:slug`, `:id`, etc.)
- Soporte para GET/POST/PUT/DELETE
- Fallback a 404

**Ejemplo de uso**:
```php
$router = new Router();

// Ruta simple
$router->get('/', APP_PATH . '/pages/frontend/home.php');

// Ruta con parámetros
$router->get('/producto/:slug', APP_PATH . '/pages/frontend/producto.php');
// Los parámetros están disponibles en $_GET['slug']

// Ruta con múltiples parámetros
$router->get('/categoria/:category/page/:page', APP_PATH . '/pages/frontend/categoria.php');
// $_GET['category'] y $_GET['page']

// Resolver request
$router->resolve($_SERVER['REQUEST_URI']);
```

### .htaccess Configuration

El routing depende de `.htaccess` para reescribir URLs:

```apache
RewriteEngine On
RewriteBase /shopv2/

# Si no es un archivo ni directorio, enviar a index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

---

## Bootstrap y Carga del Sistema

### Secuencia de Inicialización

`app/includes/bootstrap.php` es el primer archivo cargado por todos los entry points.

**Orden de carga**:

```php
1. Constantes de paths (APP_PATH, PUBLIC_PATH, DATA_PATH)
   ↓
2. Funciones core (read_json, write_json, url, etc.)
   ↓
3. Seguridad (set_security_headers, CSP, CSRF)
   ↓
4. Sesión PHP (session_start con seguridad)
   ↓
5. Autenticación (funciones de login/logout)
   ↓
6. Router class
   ↓
7. Theme loader (render_theme_css, cache)
   ↓
8. Funciones de negocio:
   - products.php
   - orders.php
   - mercadopago.php
   - email.php
   - telegram.php
   - coupons.php
   - promotions.php
```

**Importante**: `bootstrap.php` NO incluye componentes HTML (cart-panel, product-card, etc.). Los componentes se cargan bajo demanda en cada página.

---

## Almacenamiento de Datos

### JSON como Database

El sistema usa archivos JSON en lugar de SQL database.

**Ventajas**:
- Sin dependencias de MySQL/PostgreSQL
- Portable y fácil de backup
- Legible y editable manualmente
- Perfecto para catálogos pequeños (<10,000 productos)

### Estructura de Datos

#### Products (`app/data/products.json`)

Lista maestra de todos los productos:

```json
[
    {
        "id": "1",
        "slug": "producto-ejemplo",
        "name": "Producto Ejemplo",
        "description": "Descripción del producto",
        "price": 1000.00,
        "stock": 50,
        "thumbnail": "/uploads/products/producto-1-thumb.jpg",
        "images": [
            "/uploads/products/producto-1.jpg",
            "/uploads/products/producto-1-alt.jpg"
        ],
        "category": "tecnologia",
        "tags": ["nuevo", "destacado"],
        "active": true,
        "created_at": "2025-01-15 10:30:00"
    }
]
```

Cada producto también tiene un archivo individual en `app/data/products/producto-1.json` con la misma estructura.

#### Orders (`app/data/orders.json`)

```json
[
    {
        "id": "ORD-20250112-001",
        "customer": {
            "name": "Juan Pérez",
            "email": "juan@example.com",
            "phone": "123456789"
        },
        "items": [
            {
                "product_slug": "producto-ejemplo",
                "product_name": "Producto Ejemplo",
                "quantity": 2,
                "unit_price": 1000.00,
                "subtotal": 2000.00
            }
        ],
        "total": 2000.00,
        "currency": "USD",
        "status": "pendiente",
        "payment_id": "12345678",
        "payment_method": "mercadopago",
        "created_at": "2025-01-12 14:30:00",
        "stock_reduced": false
    }
]
```

#### Coupons (`app/data/coupons.json`)

```json
[
    {
        "id": "1",
        "code": "VERANO2025",
        "type": "percentage",
        "value": 15,
        "min_purchase": 500.00,
        "max_uses": 100,
        "used_count": 25,
        "valid_from": "2025-01-01",
        "valid_until": "2025-03-31",
        "active": true
    }
]
```

#### Promotions (`app/data/promotions.json`)

```json
[
    {
        "id": "1",
        "name": "Descuento Tecnología",
        "type": "percentage",
        "value": 20,
        "applies_to": "category",
        "category": "tecnologia",
        "start_date": "2025-01-01",
        "end_date": "2025-01-31",
        "active": true
    }
]
```

### File Locking

Todas las operaciones JSON usan file locking para prevenir race conditions:

```php
// Lectura con lock
function read_json($file_path) {
    $fp = fopen($file_path, 'r');
    flock($fp, LOCK_SH); // Shared lock
    $content = fread($fp, filesize($file_path));
    flock($fp, LOCK_UN);
    fclose($fp);
    return json_decode($content, true);
}

// Escritura con lock
function write_json($file_path, $data) {
    $fp = fopen($file_path, 'w');
    flock($fp, LOCK_EX); // Exclusive lock
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}
```

---

## Sistema de Themes

### Arquitectura de 3 Capas

```
┌─────────────────────┐
│   Page CSS          │  Específico de cada página
│   pages/home.css    │
└──────────┬──────────┘
           │ Sobrescribe
┌──────────▼──────────┐
│   Theme CSS         │  Específico del theme activo
│   minimal/theme.css │
└──────────┬──────────┘
           │ Sobrescribe
┌──────────▼──────────┐
│   Base CSS          │  Compartido por todos
│   _base/reset.css   │
│   _base/layout.css  │
└─────────────────────┘
```

### Orden de Carga

1. **Font Awesome** (CDN)
2. **Base CSS** (compartido):
   - `reset.css` - Reset de navegador
   - `layout.css` - Sistema de layout
   - `components.css` - Componentes base
   - `utilities.css` - Utilidades
   - `pages.css` - Estilos globales
3. **Theme CSS** (específico):
   - `variables.css` - Variables del theme
   - `theme.css` - Estilos del theme
4. **Page CSS** (si existe):
   - `pages/{nombre-pagina}.css`

### Cache CSS Automático

**En desarrollo**:
- 7 archivos individuales
- Sin minificación
- Cambios inmediatos

**En producción**:
- 1 archivo combinado
- Minificado automáticamente
- Versionado con MD5
- Resultado: `theme-minimal-a1b2c3d4.min.css`

**Detección automática**:
```php
$is_production = strpos(APP_PATH, '/home2/uv0023/') !== false;
```

**Regeneración**:
- Automática al modificar cualquier CSS
- Nuevo hash MD5 basado en timestamps
- Elimina versiones antiguas

Ver [THEME_SYSTEM.md](THEME_SYSTEM.md) para documentación completa.

---

## Sistema de Componentes

### Componentes Frontend

Componentes reutilizables PHP que generan HTML.

**Ubicación**: `app/includes/frontend/`

**Convención de nombres**:
- Archivo: `nombre-componente.php`
- Función: `render_nombre_componente($data, $options)`

**Ejemplo de componente**:

```php
<?php
// app/includes/frontend/product-card.php

function render_product_card($product, $options = []) {
    $currency = $options['currency'] ?? 'USD';
    $show_favorite_btn = $options['show_favorite_btn'] ?? true;
    $show_add_to_cart = $options['show_add_to_cart'] ?? true;

    ?>
    <div class="product-card" data-slug="<?php echo htmlspecialchars($product['slug']); ?>">
        <div class="product-image">
            <img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
                 alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>
        <div class="product-info">
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p class="price"><?php echo format_product_price($product, $currency); ?></p>

            <?php if ($show_favorite_btn): ?>
            <button data-action="toggleFavorite" data-slug="<?php echo htmlspecialchars($product['slug']); ?>">
                ♥
            </button>
            <?php endif; ?>

            <?php if ($show_add_to_cart): ?>
            <button data-action="addToCart" data-slug="<?php echo htmlspecialchars($product['slug']); ?>">
                Agregar al Carrito
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
```

**Uso en página**:

```php
<?php
require_once APP_PATH . '/includes/frontend/product-card.php';

foreach ($products as $product) {
    render_product_card($product, [
        'currency' => 'USD',
        'show_favorite_btn' => true,
        'show_add_to_cart' => true
    ]);
}
?>
```

### Componentes Disponibles

| Componente | Función | Propósito |
|------------|---------|-----------|
| cart-panel.php | `render_cart_panel()` | Panel lateral de carrito |
| favorites-panel.php | `render_favorites_panel()` | Panel lateral de favoritos |
| product-card.php | `render_product_card($product, $options)` | Tarjeta de producto |
| review-card.php | `render_review_card($review)` | Tarjeta de review |
| quantity-selector.php | `render_quantity_selector($options)` | Selector de cantidad |
| coupon-form.php | `render_coupon_form()` | Formulario de cupones |
| breadcrumb.php | `render_breadcrumb($items, $options)` | Navegación breadcrumb |
| share-buttons.php | `render_share_buttons($data)` | Botones de compartir |

Ver [COMPONENTS.md](COMPONENTS.md) para documentación completa.

---

## JavaScript Modular

### Namespaces Globales

Todo el JavaScript está organizado en **namespaces** para evitar colisiones:

```javascript
window.ShopUtils = { ... };    // Utilidades generales
window.ShopCart = { ... };     // Gestión de carrito
window.ShopFavorites = { ... }; // Gestión de favoritos
```

### Módulos Principales

#### 1. ShopUtils (`shop-utils.js`)

Utilidades de formateo, sanitización y UI:

```javascript
ShopUtils.formatCurrency(price, currency)
ShopUtils.updatePrice(price, currency)
ShopUtils.sanitizeInput(input)
ShopUtils.showToast(message, type)
```

#### 2. ShopCart (`shop-cart.js`)

Gestión del carrito (localStorage):

```javascript
ShopCart.addToCart(slug, quantity)
ShopCart.getCart()
ShopCart.updateQuantity(slug, newQty)
ShopCart.removeFromCart(slug)
ShopCart.clearCart()
ShopCart.getCartCount()
ShopCart.openCartPanel()
ShopCart.closeCartPanel()
```

#### 3. ShopFavorites (`shop-favorites.js`)

Gestión de favoritos (localStorage):

```javascript
ShopFavorites.toggleFavorite(slug)
ShopFavorites.isFavorite(slug)
ShopFavorites.getFavorites()
ShopFavorites.getFavoritesCount()
ShopFavorites.openFavoritesPanel()
ShopFavorites.closeFavoritesPanel()
```

#### 4. Event Handlers (`event-handlers.js`)

Sistema de delegación de eventos compatible con CSP:

```html
<!-- HTML -->
<button data-action="myFunction" data-param="value">Click</button>

<!-- JavaScript -->
<script nonce="<?= csp_nonce() ?>">
    function myFunction(event, element, params) {
        console.log(params.param); // "value"
    }
    window.myFunction = myFunction;
</script>
```

Ver [JAVASCRIPT_MODULES.md](JAVASCRIPT_MODULES.md) para documentación completa.

---

## Integración de Pagos

### MercadoPago Flow

```
1. Usuario en checkout
   ↓
2. Frontend crea preferencia (mercadopago.js)
   ↓
3. Backend guarda pedido con status "pendiente"
   ↓
4. MercadoPago Checkout abierto
   ↓
5. Usuario paga
   ↓
6. MercadoPago envía webhook a /webhook.php
   ↓
7. Webhook valida y actualiza pedido
   ↓
8. Si "approved": Reducir stock, enviar email, notificar Telegram
   ↓
9. Redirigir usuario a página de confirmación
```

### Webhook Security

El webhook implementa múltiples capas de seguridad:

1. **Rate limiting**: 100 requests/60 segundos
2. **IP validation**: Solo IPs de MercadoPago
3. **HMAC signature**: Valida X-Signature header
4. **Timestamp validation**: Previene replay attacks (ventana de 5 min)
5. **Logging**: Todas las notificaciones se registran

### Payment Status Mapping

```
approved      → cobrada    (reducir stock)
authorized    → pendiente
pending       → pendiente
in_process    → pendiente
in_mediation  → pendiente
rejected      → rechazada  (restaurar stock)
cancelled     → rechazada  (restaurar stock)
refunded      → cancelada  (restaurar stock)
charged_back  → cancelada  (restaurar stock)
```

---

## Sistema de Seguridad

### Content Security Policy (CSP)

CSP estricto con nonces para prevenir XSS:

```php
// En security.php
$nonce = $_SESSION['csp_nonce'];
header("Content-Security-Policy: script-src 'self' 'nonce-{$nonce}' https://trusted-cdn.com");
```

**Reglas**:
- ✅ Scripts inline necesitan `nonce="<?= csp_nonce() ?>"`
- ✅ Usar `data-action` en lugar de `onclick`
- ❌ NO usar `eval()` ni `new Function()`

### CSRF Protection

Tokens en todos los formularios:

```php
// Generar token
$csrf_token = generate_csrf_token();

// En formulario
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

// Validar
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    die('Token inválido');
}
```

### Session Security

```php
// Configuración segura de sesión
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,    // Solo HTTPS
    'httponly' => true,  // No accesible vía JS
    'samesite' => 'Lax'
]);

// Timeout automático (1 hora)
check_session_timeout(3600);
```

### File Access Protection

Todos los archivos en `app/` incluyen:

```php
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}
```

Solo los 4 entry points definen esta constante.

---

## Flujo de Requests

### Frontend Request

```
1. Usuario visita https://peu.net/shopv2/producto/ejemplo
   ↓
2. Apache reescribe a /shopv2/index.php
   ↓
3. index.php define APP_ENTRY_POINT
   ↓
4. Carga bootstrap.php
   ↓
5. Inicializa Router
   ↓
6. Router parsea URL y extrae :slug = "ejemplo"
   ↓
7. Router incluye app/pages/frontend/producto.php
   ↓
8. producto.php carga datos del producto
   ↓
9. producto.php renderiza HTML con componentes
   ↓
10. Browser recibe HTML completo
```

### Admin Request

```
1. Usuario visita https://peu.net/shopv2/admin/?page=productos-listado
   ↓
2. admin/index.php define APP_ENTRY_POINT y ADMIN_AREA
   ↓
3. Carga bootstrap.php
   ↓
4. Ejecuta require_admin() (verifica sesión)
   ↓
5. Si no autenticado → redirect a login.php
   ↓
6. Si autenticado → mapea "productos-listado" a archivo PHP
   ↓
7. Incluye app/pages/admin/productos-listado.php
   ↓
8. Página procesa lógica y renderiza HTML
   ↓
9. Browser recibe HTML completo
```

### Webhook Request

```
1. MercadoPago envía POST a /webhook.php
   ↓
2. webhook.php define APP_ENTRY_POINT
   ↓
3. Carga bootstrap.php
   ↓
4. Rate limiting (100 req/60s)
   ↓
5. Validación de IP (solo MercadoPago IPs)
   ↓
6. Validación de firma HMAC (X-Signature)
   ↓
7. Validación de timestamp (ventana de 5 min)
   ↓
8. Procesa notificación:
   - Obtiene payment info de MP API
   - Actualiza estado del pedido
   - Reduce/restaura stock según status
   - Envía email de confirmación
   - Notifica vía Telegram
   ↓
9. Registra en webhook_log.json y mp_logs.json
   ↓
10. Responde HTTP 200 OK
```

---

## Mejoras Futuras

### Roadmap Técnico

#### Corto Plazo
- [ ] Implementar caché APCu para configuraciones
- [ ] Lazy loading de imágenes de productos
- [ ] Compresión de imágenes automática (WebP)
- [ ] API REST para integraciones externas

#### Mediano Plazo
- [ ] Migración a PostgreSQL para mejor concurrencia
- [ ] Sistema de templates (Twig/Blade)
- [ ] WebSocket para notificaciones en tiempo real
- [ ] CDN para assets estáticos

#### Largo Plazo
- [ ] Microservicios para pagos y notificaciones
- [ ] Sistema de caché distribuido (Redis)
- [ ] Búsqueda con Elasticsearch
- [ ] PWA (Progressive Web App)

---

*Última actualización: 2025-12-08*
