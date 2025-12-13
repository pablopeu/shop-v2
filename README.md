# 🛒 Shop E-commerce V2

Sistema de e-commerce profesional con **arquitectura de seguridad primero** y **sistema de themes modular**.

## 📑 Tabla de Contenidos

- [🔒 Seguridad](#-seguridad)
- [📁 Estructura del Proyecto](#-estructura-del-proyecto)
- [🚀 Instalación](#-instalación)
- [🎨 Sistema de Themes](#-sistema-de-themes)
- [🧩 Componentes Reutilizables](#-componentes-reutilizables)
- [📦 Módulos JavaScript](#-módulos-javascript)
- [⚙️ Configuración](#️-configuración)
- [🔄 Workflow de Desarrollo](#-workflow-de-desarrollo)
- [🚢 Deployment Automático](#-deployment-automático)
- [📚 Documentación Adicional](#-documentación-adicional)

---

## 🔒 Seguridad

### Principio Fundamental

**TODO el código privado está FUERA de `public_html/` y es INACCESIBLE desde internet.**

### Características de Seguridad

✅ **4 únicos puntos de entrada** (vs 50+ en V1)
✅ **Código sensible fuera del web root**
✅ **Content Security Policy (CSP)** con nonces
✅ **CSRF Protection** en todos los formularios
✅ **Rate Limiting** en webhooks y API
✅ **Session Security** con timeout automático
✅ **Security Headers** automáticos
✅ **File Locking** en operaciones JSON

---

## 📁 Estructura del Proyecto

```
shop-v2/
├── app/                                    # PRIVADO (inaccesible vía HTTP)
│   ├── config/                             # Configuración sensible
│   │   ├── config.php                      # Config principal (auto-generado)
│   │   ├── config.example.php              # Template de configuración
│   │   ├── paths.php                       # Definición de paths
│   │   ├── theme.json                      # Theme activo
│   │   ├── site.json                       # Metadata del sitio
│   │   ├── payment.json                    # Config MercadoPago
│   │   ├── footer.json                     # Config footer
│   │   └── currency.json                   # Config monedas
│   │
│   ├── includes/                           # Sistema de funciones
│   │   ├── bootstrap.php                   # Inicialización del sistema
│   │   ├── functions.php                   # Utilidades core
│   │   ├── security.php                    # Seguridad y CSP
│   │   ├── auth.php                        # Autenticación
│   │   ├── router.php                      # Routing system
│   │   ├── theme-loader.php                # Sistema de themes + cache CSS
│   │   ├── products.php                    # Gestión de productos
│   │   ├── orders.php                      # Gestión de pedidos
│   │   ├── mercadopago.php                 # Integración MercadoPago
│   │   ├── email.php                       # Envío de emails
│   │   ├── telegram.php                    # Notificaciones Telegram
│   │   ├── coupons.php                     # Sistema de cupones
│   │   ├── promotions.php                  # Sistema de promociones
│   │   │
│   │   ├── frontend/                       # Componentes reutilizables
│   │   │   ├── cart-panel.php              # Panel de carrito
│   │   │   ├── favorites-panel.php         # Panel de favoritos
│   │   │   ├── product-card.php            # Tarjeta de producto
│   │   │   ├── review-card.php             # Tarjeta de review
│   │   │   ├── quantity-selector.php       # Selector de cantidad
│   │   │   ├── coupon-form.php             # Formulario de cupones
│   │   │   ├── breadcrumb.php              # Breadcrumb navigation
│   │   │   └── share-buttons.php           # Botones de compartir
│   │   │
│   │   └── admin/                          # Componentes admin
│   │       ├── header.php                  # Header del panel
│   │       ├── sidebar.php                 # Sidebar de navegación
│   │       ├── modal.php                   # Modal reutilizable
│   │       ├── styles.php                  # Estilos centralizados
│   │       └── admin-common-styles.php     # Estilos comunes
│   │
│   ├── pages/                              # Vistas/Controllers
│   │   ├── frontend/                       # Páginas públicas
│   │   │   ├── home.php                    # Página principal
│   │   │   ├── producto.php                # Detalle de producto
│   │   │   ├── carrito.php                 # Carrito de compras
│   │   │   ├── checkout.php                # Proceso de pago
│   │   │   ├── favoritos.php               # Lista de favoritos
│   │   │   ├── track.php                   # Seguimiento de pedido
│   │   │   ├── buscar.php                  # Búsqueda de productos
│   │   │   └── preview.php                 # Preview de themes
│   │   │
│   │   └── admin/                          # Panel de administración
│   │       ├── index.php                   # Dashboard
│   │       ├── productos-*.php             # Gestión de productos
│   │       ├── ventas.php                  # Gestión de ventas
│   │       ├── archivo-ventas.php          # Archivo de ventas
│   │       ├── cupones-*.php               # Gestión de cupones
│   │       ├── promociones-*.php           # Gestión de promociones
│   │       └── config-*.php                # Configuraciones
│   │
│   └── data/                               # Datos JSON (file-locked)
│       ├── products.json                   # Lista de productos
│       ├── products/                       # Productos individuales
│       ├── orders.json                     # Pedidos activos
│       ├── archived_orders.json            # Pedidos archivados
│       ├── coupons.json                    # Cupones de descuento
│       ├── promotions.json                 # Promociones activas
│       ├── reviews.json                    # Reviews de productos
│       ├── webhook_log.json                # Log de webhooks
│       └── mp_logs.json                    # Logs de MercadoPago
│
└── public_html/                            # PÚBLICO (web root)
    ├── index.php                           # Punto entrada frontend
    │
    ├── admin/                              # Panel de administración
    │   ├── index.php                       # Punto entrada admin
    │   └── login.php                       # Login de admin
    │
    ├── webhook.php                         # Webhooks MercadoPago
    │
    ├── assets/                             # Assets públicos
    │   ├── themes/                         # Sistema de themes
    │   │   ├── _base/                      # CSS base (compartido)
    │   │   │   ├── reset.css               # Reset de navegador
    │   │   │   ├── layout.css              # Sistema de layout
    │   │   │   ├── components.css          # Componentes base
    │   │   │   ├── utilities.css           # Utilidades CSS
    │   │   │   ├── pages.css               # Estilos globales
    │   │   │   └── pages/                  # CSS por página
    │   │   │       ├── home.css
    │   │   │       ├── producto.css
    │   │   │       ├── carrito.css
    │   │   │       └── ...
    │   │   │
    │   │   ├── minimal/                    # Theme Minimal (default)
    │   │   │   ├── theme.json              # Metadata del theme
    │   │   │   ├── variables.css           # Variables CSS
    │   │   │   └── theme.css               # Estilos del theme
    │   │   │
    │   │   └── classic/                    # Theme Classic
    │   │       ├── theme.json
    │   │       ├── variables.css
    │   │       └── theme.css
    │   │
    │   ├── js/                             # JavaScript modular
    │   │   ├── event-handlers.js           # Event delegation system
    │   │   ├── shop-utils.js               # ShopUtils namespace
    │   │   ├── shop-cart.js                # ShopCart namespace
    │   │   ├── shop-favorites.js           # ShopFavorites namespace
    │   │   ├── currency-switcher.js        # Cambio de moneda
    │   │   ├── cart-validator.js           # Validación de carrito
    │   │   └── mobile-menu.js              # Menú móvil
    │   │
    │   ├── css/                            # CSS complementarios
    │   │   └── mobile-menu.css
    │   │
    │   └── cache/                          # CSS cacheado (auto-generado)
    │       ├── .gitignore                  # Ignorar archivos de cache
    │       └── theme-*.min.css             # CSS minificado
    │
    ├── uploads/                            # Archivos subidos
    │   └── products/                       # Imágenes de productos
    │
    └── install/                            # Instalador (eliminar post-install)
        └── installer.php
```

---

## 🚀 Instalación

### Requisitos

- **PHP**: 7.4 o superior
- **Extensiones**: `json`, `fileinfo`, `curl`
- **Web Server**: Apache con `.htaccess` habilitado
- **Permisos**: Escritura en `app/data/` y `public_html/uploads/`

### Pasos de Instalación

1. **Clonar el repositorio**:
   ```bash
   git clone https://github.com/pablopeu/shop-v2.git
   cd shop-v2
   ```

2. **Configurar web server**: Document root debe apuntar a `public_html/`

3. **Ejecutar instalador**: Abrir en navegador:
   ```
   http://tu-dominio.com/shopv2/install/installer.php
   ```

4. **Seguir el wizard**:
   - Verificación de requisitos
   - Configuración de paths
   - Configuración de seguridad
   - Creación de admin
   - Configuración de pagos

5. **Eliminar instalador**: Usar el botón de auto-eliminación al finalizar

### Verificación Post-Instalación

```bash
# Verificar permisos
ls -la app/data/          # Debe ser escribible
ls -la public_html/uploads/  # Debe ser escribible

# Verificar configuración
cat app/config/config.php    # Debe existir

# Probar acceso
curl http://tu-dominio.com/shopv2/
curl http://tu-dominio.com/shopv2/admin/login.php
```

---

## 🎨 Sistema de Themes

### ✨ Frontend 100% Themeable

**El frontend es completamente personalizable** mediante CSS variables, sin necesidad de modificar código.

#### Estado del Proyecto de Theming

✅ **FASE 1 - Eliminar Estilos Inline**: 100% Completo
- 10 archivos PHP frontend sin estilos inline
- 3 archivos includes frontend limpios
- ~25 clases CSS reutilizables creadas

✅ **FASE 2 - Variables CSS**: 100% Completo
- +45 variables CSS en `variables.css`
- 0 colores hardcoded en archivos CSS
- Sistema completamente variable-based

✅ **FASE 3 - Refactorizar JS**: 90% Completo
- 30+ utility classes en `utilities.css`
- Event delegation system implementado
- Manipulaciones críticas refactorizadas

**Resultado**: El frontend puede cambiar completamente de apariencia editando solo `variables.css`.

### Arquitectura de Themes

El sistema de themes separa:
- **Base CSS**: Compartido por todos los themes (`/assets/themes/_base/`)
- **Theme CSS**: Específico de cada theme (`/assets/themes/{nombre}/`)
- **Page CSS**: Específico de cada página (`/assets/themes/_base/pages/`)

### Orden de Carga CSS

```
1. Font Awesome (CDN)
2. Base CSS:
   - reset.css
   - layout.css
   - components.css
   - utilities.css      ← Nuevo: utility classes
   - pages.css
3. Theme CSS:
   - variables.css      ← Variables CSS (100% themeable)
   - theme.css
4. Page CSS (si existe):
   - pages/{nombre-pagina}.css
```

### Cache CSS Automático

**Desarrollo** (local):
- Carga 7 archivos individuales
- Sin minificación
- Cambios instantáneos

**Producción**:
- Carga 1 archivo combinado
- Minificado automáticamente
- Versionado con MD5 (cache-busting)
- **Resultado**: 66% menos tamaño, 7x menos requests

### Crear un Nuevo Theme

1. Crear directorio:
   ```bash
   mkdir public_html/assets/themes/mi-theme
   ```

2. Crear `theme.json`:
   ```json
   {
       "name": "Mi Theme",
       "slug": "mi-theme",
       "version": "1.0.0",
       "author": "Tu Nombre",
       "description": "Descripción del theme"
   }
   ```

3. Crear `variables.css` (ver `classic/variables.css` como referencia):
   ```css
   :root {
       /* Colores Principales */
       --color-primary: #007bff;
       --color-primary-dark: #0056b3;
       --color-primary-light: #3395ff;

       /* Colores de Estado */
       --color-success: #28a745;
       --color-warning: #ffc107;
       --color-error: #dc3545;
       --color-info: #17a2b8;

       /* Colores de Texto */
       --color-text: #333;
       --color-text-light: #666;
       --color-text-muted: #999;

       /* Colores de Fondo */
       --color-bg: #ffffff;
       --color-bg-light: #f8f9fa;
       --color-bg-dark: #f5f5f5;

       /* Tipografía */
       --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

       /* Espaciado y Layout */
       --spacing-md: 1rem;
       --border-radius: 4px;

       /* ... más de 45 variables disponibles */
   }
   ```

   **Ver todas las variables**: Consultar `public_html/assets/themes/classic/variables.css` para la lista completa de variables CSS disponibles.

4. Crear `theme.css`:
   ```css
   /* Sobrescribir estilos base */
   .btn-primary {
       background: var(--color-primary);
       border-radius: var(--border-radius);
   }
   ```

5. Activar theme:
   ```bash
   # Editar app/config/theme.json
   {
       "active_theme": "mi-theme"
   }
   ```

Ver [docs/THEME_SYSTEM.md](docs/THEME_SYSTEM.md) para documentación completa.

---

## 🧩 Componentes Reutilizables

### Componentes Frontend

Todos los componentes están en `app/includes/frontend/`:

#### 1. Cart Panel

```php
<?php
require_once APP_PATH . '/includes/frontend/cart-panel.php';
render_cart_panel();
?>
```

#### 2. Favorites Panel

```php
<?php
require_once APP_PATH . '/includes/frontend/favorites-panel.php';
render_favorites_panel(['show_go_to_page_btn' => true]);
?>
```

#### 3. Product Card

```php
<?php
require_once APP_PATH . '/includes/frontend/product-card.php';
render_product_card($product, [
    'currency' => 'USD',
    'show_favorite_btn' => true,
    'show_add_to_cart' => true
]);
?>
```

#### 4. Review Card

```php
<?php
require_once APP_PATH . '/includes/frontend/review-card.php';
render_review_card($review);
?>
```

#### 5. Quantity Selector

```php
<?php
require_once APP_PATH . '/includes/frontend/quantity-selector.php';
render_quantity_selector([
    'id' => 'qty-input',
    'value' => 1,
    'min' => 1,
    'max' => $product['stock'],
    'disabled' => false
]);
?>
```

#### 6. Coupon Form

```php
<?php
require_once APP_PATH . '/includes/frontend/coupon-form.php';
render_coupon_form();
?>
```

#### 7. Breadcrumb

```php
<?php
require_once APP_PATH . '/includes/frontend/breadcrumb.php';
render_breadcrumb([
    ['label' => 'Inicio', 'url' => url('/')],
    ['label' => 'Productos', 'url' => url('/productos')],
    ['label' => 'Producto Actual', 'url' => null]
]);
?>
```

#### 8. Share Buttons

```php
<?php
require_once APP_PATH . '/includes/frontend/share-buttons.php';
render_share_buttons([
    'url' => 'https://ejemplo.com/producto',
    'title' => 'Nombre del Producto'
]);
?>
```

---

## 📦 Módulos JavaScript

### Arquitectura Modular

Todo el JavaScript está organizado en **namespaces globales** para evitar colisiones:

### 1. ShopUtils (`shop-utils.js`)

Utilidades generales del sistema:

```javascript
// Formatear moneda
ShopUtils.formatCurrency(1234.56, 'USD'); // "$1,234.56"
ShopUtils.formatCurrency(1234.56, 'ARS'); // "$1.234,56"

// Actualizar precio en DOM
ShopUtils.updatePrice(price, currency);

// Sanitizar entrada
ShopUtils.sanitizeInput('<script>alert("xss")</script>'); // Texto limpio

// Notificaciones toast
ShopUtils.showToast('Mensaje', 'success'); // success|error|info
```

### 2. ShopCart (`shop-cart.js`)

Gestión del carrito de compras:

```javascript
// Agregar producto al carrito
ShopCart.addToCart(productSlug, quantity);

// Obtener contenido del carrito
const cart = ShopCart.getCart(); // Array de items

// Actualizar cantidad
ShopCart.updateQuantity(productSlug, newQuantity);

// Eliminar producto
ShopCart.removeFromCart(productSlug);

// Vaciar carrito
ShopCart.clearCart();

// Abrir/cerrar panel
ShopCart.openCartPanel();
ShopCart.closeCartPanel();

// Obtener total de items
const count = ShopCart.getCartCount();
```

### 3. ShopFavorites (`shop-favorites.js`)

Gestión de productos favoritos:

```javascript
// Agregar/quitar favorito
ShopFavorites.toggleFavorite(productSlug);

// Verificar si es favorito
const isFav = ShopFavorites.isFavorite(productSlug); // true|false

// Obtener lista de favoritos
const favs = ShopFavorites.getFavorites(); // Array de slugs

// Abrir/cerrar panel
ShopFavorites.openFavoritesPanel();
ShopFavorites.closeFavoritesPanel();

// Obtener conteo
const count = ShopFavorites.getFavoritesCount();
```

### 4. Event Delegation (`event-handlers.js`)

Sistema de delegación de eventos compatible con CSP:

```html
<!-- HTML: Usar data-action en lugar de onclick -->
<button data-action="myFunction" data-param="value">Click</button>

<!-- JavaScript: Definir función y exportar -->
<script nonce="<?= csp_nonce() ?>">
    function myFunction(event, element, params) {
        console.log(params.param); // "value"
    }

    window.myFunction = myFunction; // Exportar
</script>

<!-- IMPORTANTE: Incluir event-handlers.js -->
<script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
```

Soporta:
- `data-action="functionName"` (clicks)
- `data-onchange="functionName"` (change events)
- `data-onsubmit="functionName"` (form submits)

Todos los `data-*` attributes se pasan como objeto `params`.

---

## ⚙️ Configuración

### Archivos de Configuración

Todos en `app/config/`:

#### 1. `config.php` (Auto-generado)

```php
<?php
define('DB_TYPE', 'json'); // Tipo de almacenamiento
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'hash_bcrypt');
define('SECRET_KEY', 'random_key');
define('SESSION_TIMEOUT', 3600);
define('MAINTENANCE_MODE', false);
?>
```

**⚠️ NUNCA hacer commit de este archivo** (incluido en `.gitignore`)

#### 2. `theme.json`

```json
{
    "active_theme": "minimal"
}
```

#### 3. `site.json`

```json
{
    "site_name": "Mi Tienda",
    "site_description": "Descripción",
    "logo": "/uploads/logo.png",
    "favicon": "/uploads/favicon.ico"
}
```

#### 4. `payment.json`

```json
{
    "mercadopago": {
        "enabled": true,
        "access_token": "APP_USR-...",
        "public_key": "APP_USR-..."
    }
}
```

#### 5. `currency.json`

```json
{
    "primary": "USD",
    "exchange_rate": 1200.50,
    "last_updated": "2025-12-08 10:30:00",
    "auto_update": true
}
```

---

## 🔄 Workflow de Desarrollo

### Branches

- **`main`**: Producción (auto-deploy a peu.net/shopv2)
- **`feature/*`**: Nuevas funcionalidades
- **`fix/*`**: Correcciones de bugs
- **`refactor/*`**: Refactorings

### Desarrollo Local

```bash
# Crear branch de feature
git checkout -b feature/nueva-funcionalidad

# Hacer cambios
# ... editar archivos ...

# Commit frecuente con prefijos semánticos
git add .
git commit -m "feat: agregar componente de rating"

# Push a GitHub
git push origin feature/nueva-funcionalidad

# Crear Pull Request en GitHub
gh pr create --title "Feature: Rating de Productos"
```

### Prefijos de Commits

- `feat:` - Nueva funcionalidad
- `fix:` - Corrección de bug
- `refactor:` - Refactorización sin cambio funcional
- `style:` - Cambios de formato/estilo
- `docs:` - Actualización de documentación
- `chore:` - Tareas de mantenimiento
- `test:` - Agregar/modificar tests

### Testing Local

```bash
# PHP Dev Server
cd public_html
php -S localhost:8000

# Acceder a:
# http://localhost:8000 (frontend)
# http://localhost:8000/admin/login.php (admin)
```

**⚠️ IMPORTANTE**: El servidor de desarrollo NO replica la estructura de producción. Siempre testear en producción después del deploy.

---

## 🚢 Deployment Automático

### GitHub Actions

Cada push a `main` ejecuta **auto-deploy** a producción vía FTP.

### Workflow (`.github/workflows/deploy.yml`)

```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: FTP Deploy
        uses: SamKirkland/FTP-Deploy-Action@4.3.0
        with:
          server: ${{ secrets.FTP_SERVER }}
          username: ${{ secrets.FTP_USERNAME }}
          password: ${{ secrets.FTP_PASSWORD }}
```

### Estructura de Producción

```
/home2/uv0023/
├── shop-v2-app/              # app/ (código privado)
└── public_html/shopv2/       # public_html/ (código público)
```

### Proceso de Deploy

1. **Push a `main`**:
   ```bash
   git push origin main
   ```

2. **GitHub Actions se activa automáticamente**:
   - Descarga código del repo
   - Conecta via FTP a peu.net
   - Sube cambios al servidor

3. **Verificar deploy**:
   - Ver logs en GitHub Actions
   - Testear en https://peu.net/shopv2

### Secrets Requeridos

Configurados en GitHub Settings → Secrets:
- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

---

## 📚 Documentación Adicional

### Documentación del Sistema

- **[THEME_SYSTEM.md](docs/THEME_SYSTEM.md)** - Sistema de themes y cache CSS
- **[PLAN_THEMING_100.md](docs/PLAN_THEMING_100.md)** - ✨ Proyecto Frontend 100% Themeable (COMPLETADO)
- **[PLAN_MIGRACION_THEMES.md](docs/PLAN_MIGRACION_THEMES.md)** - Plan de migración frontend
- **[SECURITY-V2.md](SECURITY-V2.md)** - Arquitectura de seguridad
- **[CLAUDE.md](CLAUDE.md)** - Guía para Claude Code (AI assistant)

### Guías de Desarrollo

- **[install/README.md](install/README.md)** - Instalador del sistema
- **[.github/DEPLOY.md](.github/DEPLOY.md)** - Configuración de deployment

### Componentes Admin

- **[app/includes/admin/MODAL_GUIDELINES.md](app/includes/admin/MODAL_GUIDELINES.md)** - Uso del modal reutilizable

---

## 🛠️ Convenciones de Código

### PHP

- **Todo en español**: Variables, funciones, comentarios, UI
- **Paths**: Usar constantes `APP_PATH`, `PUBLIC_PATH`, `url()`
- **URLs**: Siempre usar helper `url('/ruta')`
- **JSON**: Usar `read_json()` y `write_json()` (file locking automático)
- **Security check**: Todos los archivos en `app/`:
  ```php
  if (!defined('APP_ENTRY_POINT')) {
      die('Direct access not permitted');
  }
  ```

### JavaScript

- **Namespaces**: `ShopUtils`, `ShopCart`, `ShopFavorites`
- **Event delegation**: Usar `data-action` (NO `onclick`)
- **Exportar funciones**: `window.myFunction = myFunction;`
- **CSP nonces**: Todos los `<script>` inline necesitan `nonce="<?= csp_nonce() ?>"`

### CSS

- **Variables CSS**: Definir en `theme/variables.css`
- **No hardcodear colores**: Usar `var(--color-primary)`
- **Mobile-first**: Media queries de menor a mayor
- **BEM naming**: `.block__element--modifier`

### HTML

- **NO usar**: `alert()`, `confirm()`, `prompt()`
- **Usar**: `showModal()` del componente modal
- **Rutas de imágenes**: `url($product['thumbnail'])`
- **Escapar output**: `htmlspecialchars()` siempre

---

## 🤝 Contribuir

1. Fork el repositorio
2. Crear branch de feature (`git checkout -b feature/amazing-feature`)
3. Commit cambios (`git commit -m 'feat: add amazing feature'`)
4. Push al branch (`git push origin feature/amazing-feature`)
5. Abrir Pull Request

---

## 📄 Licencia

Este proyecto es de código abierto. Licencia GNU GPL v2

---

## 👨‍💻 Autor

**Pablo** - [GitHub](https://github.com/pablopeu)

---

## 🔗 Links Útiles

- **Repositorio**: https://github.com/pablopeu/shop-v2
- **Producción**: https://peu.net/shopv2
- **MercadoPago Docs**: https://www.mercadopago.com.ar/developers/es/docs

---

*Última actualización: 2025-12-09* - ✨ Frontend 100% Themeable Completado
