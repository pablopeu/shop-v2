# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Shop V2 is a **professional e-commerce platform refactored with security-first architecture**. The key principle is that **ALL private code is OUTSIDE public_html and INACCESSIBLE from the internet**.

**Project Language**: Spanish (all code, comments, and UI are in Spanish)

**Repository**: https://github.com/pablopeu/shop-v2

**Production URL**: https://peu.net/shopv2

---

## ⚠️ ESTADO ACTUAL DEL PROYECTO

**IMPORTANTE**: Aunque el código se está desplegando automáticamente en el servidor de producción (peu.net/shopv2), el sistema **AÚN NO ESTÁ EN PRODUCCIÓN REAL**.

- El servidor https://peu.net/shopv2 se usa para **pruebas y desarrollo**
- NO hay clientes reales usando el sistema actualmente
- Los datos (ventas, productos, etc.) son de prueba
- El sistema está en fase de testing antes del lanzamiento real

**Implicaciones para desarrollo**:
- Se puede probar directamente en peu.net/shopv2 después de cada deploy
- Los errores no afectan a usuarios reales
- Se pueden hacer cambios sin el nivel de precaución de un sistema en producción real

**NOTA**: Esta sección será eliminada cuando el sistema entre en producción real con clientes activos.

---

# ⚠️ CRITICAL RULES - NEVER BREAK THESE

## ❌ RULE 1: NO ALERT BOXES

### NEVER use:
```javascript
alert('Mensaje');           // ❌ PROHIBIDO
confirm('¿Estás seguro?');  // ❌ PROHIBIDO
prompt('Ingresa valor:');   // ❌ PROHIBIDO
```

### ALWAYS use instead:
```javascript
// Para confirmaciones:
showModal({
    title: 'Confirmar',
    message: '¿Estás seguro?',
    onConfirm: function() { /* acción */ }
});

// Para notificaciones simples (feedback visual):
btn.textContent = '✅ Guardado';
btn.classList.add('success');
setTimeout(() => {
    btn.textContent = 'Original';
    btn.classList.remove('success');
}, 2000);
```

**Location**: Modal component at `app/includes/admin/modal.php`

---

## ❌ RULE 2: NO HARDCODED PATHS

### NEVER use:
```php
require '/home/pablo/shop-v2/app/file.php';           // ❌ PROHIBIDO
$path = '/var/www/html/uploads/image.jpg';            // ❌ PROHIBIDO
echo '<link href="/shopv2/assets/style.css">';        // ❌ PROHIBIDO
```

### ALWAYS use instead:
```php
require APP_PATH . '/file.php';                       // ✅ CORRECTO
$path = PUBLIC_PATH . '/uploads/image.jpg';          // ✅ CORRECTO
echo '<link href="' . url('/assets/style.css') . '">'; // ✅ CORRECTO
```

**Constants**: `APP_PATH`, `PUBLIC_PATH`, `DATA_PATH`, `url()`

---

## ❌ RULE 3: ALWAYS PUSH TO GITHUB

### NEVER do:
```bash
git commit -m "cambios"
# [No hacer push]  ❌ PROHIBIDO
```

### ALWAYS do:
```bash
git commit -m "cambios"
git push origin branch-name  # ✅ OBLIGATORIO
```

**Why**: GitHub Actions auto-deploys to production. Local commits without push = no deployment.

---

## ❌ RULE 4: TODO EN ESPAÑOL

### NEVER use:
```php
$message = 'User not found';              // ❌ PROHIBIDO
function getUserData() { }                // ❌ PROHIBIDO
// Get the user information               // ❌ PROHIBIDO
```

### ALWAYS use:
```php
$mensaje = 'Usuario no encontrado';       // ✅ CORRECTO
function obtenerDatosUsuario() { }        // ✅ CORRECTO
// Obtener la información del usuario    // ✅ CORRECTO
```

**Applies to**: Code, comments, variables, UI text, error messages, logs.

---

## ❌ RULE 5: NO MANUAL JSON FILES

### NEVER instruct:
```
"Crea un archivo data/usuarios.json con..."  ❌ PROHIBIDO
```

### ALWAYS do:
```php
// El sistema crea automáticamente:
$data = read_json(APP_PATH . '/data/usuarios.json');
write_json(APP_PATH . '/data/usuarios.json', $datos);
```

**System handles**: All JSON creation/defaults automatically.

---

## ❌ RULE 6: NO INLINE EVENT HANDLERS - USE EVENT DELEGATION

### NEVER use:
```html
<button onclick="myFunction()">Click</button>           <!-- ❌ PROHIBIDO -->
<input onchange="handleChange()">                       <!-- ❌ PROHIBIDO -->
<form onsubmit="return validate()">                     <!-- ❌ PROHIBIDO -->
```

### ALWAYS use instead:
```html
<!-- HTML: Use data-action attributes -->
<button data-action="myFunction">Click</button>         <!-- ✅ CORRECTO -->
<input data-onchange="handleChange">                    <!-- ✅ CORRECTO -->
<form data-onsubmit="validate">                         <!-- ✅ CORRECTO -->

<!-- JavaScript: Create wrapper for event delegation -->
<script nonce="<?= csp_nonce() ?>">
    function myFunction(event, element, params) {
        // Your code here
    }

    // Export for event delegation
    window.myFunction = myFunction;
</script>

<!-- Always include event-handlers.js -->
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
```

**Why**: Strict CSP (Content Security Policy) blocks inline event handlers. All scripts must use nonces.

**Event delegation system**: `public_html/assets/js/event-handlers.js`

---

# END CRITICAL RULES
**Review these BEFORE writing any code**

---

## Claude Code Workflow Rules

### 1. Permission and Autonomy
- **No permission required** for Debian or PHP commands - execute directly
- **No permission required** for GitHub operations - commit and push automatically
- Once you understand the task, **proceed without asking for confirmation**
- Focus on completing the task efficiently

### 2. Repository and Deployment Context
- **Origin repository**: https://github.com/pablopeu/shop (V1 - legacy)
- **Current repository**: https://github.com/pablopeu/shop-v2 (V2 - refactored)
- **Every local commit MUST be pushed to GitHub** - no exceptions
- GitHub Actions auto-deploys via FTP to production on every push

### 3. Testing and Debugging Context
- **Production URL**: https://peu.net/shopv2
- When user reports errors, they refer to **production** (peu.net/shopv2), NOT local git
- Local git does NOT have:
  - Sales data (ventas)
  - Products (artículos)
  - MercadoPago payments
- To diagnose production issues:
  - Add debug logs (`error_log()`) to relevant sections
  - Create diagnostic scripts if needed
  - Never assume local behavior matches production

### 4. Code Quality Standards
- If you encounter **hardcoded paths** during editing, FIX them immediately
- Use environment constants: `APP_PATH`, `PUBLIC_PATH`, `DATA_PATH`
- Use `url()` helper for all URL generation
- All code, comments, and UI text in **Spanish**

## Critical Security Architecture

### Directory Structure

```
shop-v2/
├── app/                    # PRIVATE (outside web root - NEVER accessible via HTTP)
│   ├── config/             # Sensitive configuration
│   ├── includes/           # System functions
│   ├── pages/              # Views/Controllers
│   └── data/               # JSON data files
│
└── public_html/            # PUBLIC (web root)
    ├── index.php           # ONLY frontend entry point
    ├── admin/
    │   ├── index.php       # ONLY admin panel entry point
    │   └── login.php       # Admin login entry point
    ├── webhook.php         # Webhooks entry point (MercadoPago)
    ├── assets/             # Public CSS, JS, images
    └── api/                # API endpoints
```

### Only 4 Entry Points

The application has exactly 4 PHP entry points (vs 50+ in V1):

1. **`/public_html/index.php`** - Frontend main entry (defines `APP_ENTRY_POINT`)
2. **`/public_html/admin/index.php`** - Admin panel (defines `APP_ENTRY_POINT` + `ADMIN_AREA`)
3. **`/public_html/admin/login.php`** - Admin login (defines `APP_ENTRY_POINT`)
4. **`/public_html/webhook.php`** - External webhooks (defines `APP_ENTRY_POINT`)

**IMPORTANT**: All other PHP files in `/app/` are protected and MUST NOT define `APP_ENTRY_POINT`. They will fail with "Direct access not permitted" if accessed directly.

### Environment Detection

The codebase automatically detects three environments:

1. **Production**: `/home2/uv0023/shop-v2-app/` exists
2. **Testing**: `/home/pablo/shop-v2-local-test/shop-v2-app/` exists
3. **Development**: Relative paths from `__DIR__`

This affects how `bootstrap.php` is loaded in entry points.

## Routing System

### Frontend Routing (index.php)

Uses a centralized Router class with clean URLs:

```
GET /                    → app/pages/frontend/home.php
GET /producto/:slug      → app/pages/frontend/producto.php
GET /buscar              → app/pages/frontend/buscar.php
GET /carrito             → app/pages/frontend/carrito.php
GET/POST /checkout       → app/pages/frontend/checkout-new.php (new vertical checkout with shipping)
GET /checkout-return     → app/pages/frontend/checkout-return.php
GET /favoritos           → app/pages/frontend/favoritos.php
GET /pedido              → app/pages/frontend/pedido.php
GET /track               → app/pages/frontend/track.php
GET /tracking            → app/pages/frontend/tracking.php
GET /gracias             → app/pages/frontend/gracias.php
GET /pendiente           → app/pages/frontend/pendiente.php
GET /preview             → app/pages/frontend/preview.php
```

Routing is handled by `.htaccess` with `FallbackResource /shopv2/index.php`.

### Admin Routing (admin/index.php)

Uses query parameters instead of path segments:

```
GET /admin/?page=index                → app/pages/admin/index.php
GET /admin/?page=productos-listado    → app/pages/admin/productos-listado.php
GET /admin/?page=ventas               → app/pages/admin/ventas.php
GET /admin/?page=config-sitio         → app/pages/admin/config-sitio.php
```

All admin pages are mapped in `public_html/admin/index.php` in the `$pages_map` array.

## Data Storage

### JSON-Based Storage

All data is stored in JSON files under `/app/data/`:

- **Products**: `app/data/products.json` and individual files in `app/data/products/`
- **Orders**: `app/data/orders.json`
- **Archived orders**: `app/data/archived_orders.json`
- **Shipments**: `app/data/shipments/` (shipping data per order)
- **Coupons**: `app/data/coupons.json`
- **Promotions**: `app/data/promotions.json`
- **Reviews**: `app/data/reviews.json`
- **Cache**: `app/data/cache/` (shipping quotes cache, etc.)
- **Logs**: `app/data/webhook_log.json`, `app/data/mp_logs.json`

### File Locking

All JSON read/write operations use file locking via `read_json()` and `write_json()` in `app/includes/functions.php` to prevent race conditions.

## Key Functions and Includes

### Core System Files (loaded in bootstrap.php)

**Main System Functions:**
- `app/includes/functions.php` - Core utilities (JSON read/write, redirects, URL helpers, sanitization)
- `app/includes/security.php` - Security headers, CSP with nonces, CSRF protection
- `app/includes/router.php` - Router class for URL routing (frontend)
- `app/includes/auth.php` - Authentication, session management, admin login
- `app/includes/rate_limit.php` - Rate limiting for API endpoints
- `app/includes/locks.php` - File locking for concurrent JSON operations
- `app/includes/log_rotation.php` - Log rotation and cleanup
- `app/includes/upload.php` - File upload handling and validation
- `app/includes/api_helpers.php` - API response helpers
- `app/includes/strings.php` - String manipulation utilities

**Business Logic:**
- `app/includes/products.php` - Product management (CRUD, stock, pricing)
- `app/includes/orders.php` - Order management (create, update, archive)
- `app/includes/carriers.php` - **Shipping integration (Zipnova, multi-carrier architecture)**
- `app/includes/coupons.php` - Coupon system (validation, application)
- `app/includes/promotions.php` - Promotions system (2x1, discounts)

**External Integrations:**
- `app/includes/mercadopago.php` - MercadoPago payment API
- `app/includes/mp-logger.php` - MercadoPago detailed logging
- `app/includes/email.php` - Email sending (order confirmations, notifications)
- `app/includes/telegram.php` - Telegram bot notifications

**Theming:**
- `app/includes/theme-loader.php` - Dynamic theme loading
- `app/includes/theme-generator.php` - Theme generation and customization

### Admin Components (includes/admin/)

- `app/includes/admin/header.php` - Admin panel header
- `app/includes/admin/sidebar.php` - Admin panel sidebar navigation
- `app/includes/admin/modal.php` - Reusable modal component
- `app/includes/admin/styles.php` - Admin-specific styles
- `app/includes/admin/admin-common-styles.php` - Common admin styles
- `app/includes/admin/session-monitor.js` - Session timeout monitor
- `app/includes/admin/unsaved-changes-warning.js` - Warn on unsaved changes
- `app/includes/admin/ventas/` - Sales-specific components:
  - `actions.php` - Sales actions (archive, export, etc.)
  - `filters.php` - Sales filtering logic
  - `stats.php` - Sales statistics
  - `views.php` - Sales view renderers

### Frontend Components (includes/frontend/)

- `app/includes/frontend/modal.php` - Frontend modal component
- `app/includes/frontend/breadcrumb.php` - Breadcrumb navigation
- `app/includes/frontend/cart-panel.php` - Shopping cart panel
- `app/includes/frontend/product-card.php` - Product card component
- `app/includes/frontend/quantity-selector.php` - Quantity selector
- `app/includes/frontend/review-card.php` - Product review card
- `app/includes/frontend/favorites-panel.php` - Favorites/wishlist panel
- `app/includes/frontend/currency-toggle.php` - Currency switcher
- `app/includes/frontend/coupon-form.php` - Coupon input form
- `app/includes/frontend/share-buttons.php` - Social share buttons

### HTML Components (NOT function libraries)

These should be included in pages, NOT in bootstrap.php:

- `app/includes/header-frontend.php` - Frontend header
- `app/includes/carousel.php` - Product carousel
- `app/includes/mobile-menu.php` - Mobile menu
- `app/includes/tracking-events.php` - Analytics events
- `app/includes/tracking-scripts.php` - Analytics scripts
- `app/includes/auto-update-exchange.php` - Auto-update currency rates

## Common Development Commands

### Git Workflow

```bash
# Make changes, then commit and push
git add .
git commit -m "feat: descripción del cambio"
git push origin main   # IMPORTANT: Always push to GitHub

# GitHub Actions will automatically deploy to production
```

**CRITICAL**: Every local commit MUST be pushed to GitHub. The auto-deploy system deploys to production on every push to `main`.

### Fix File Permissions (Production)

```bash
# Private code (read-only by web server)
chmod 750 app/
chmod 640 app/config/config.php
chmod 750 app/data/
chmod 640 app/data/*.json

# Public code
chmod 755 public_html/
chmod 644 public_html/*.php
```

## Important Development Guidelines

### Modal Usage - NO alert() or confirm()

**NEVER use native browser `alert()`, `confirm()`, or `prompt()`.**

Always use the custom modal component located at `app/includes/admin/modal.php`.

```javascript
// ❌ WRONG
onclick="return confirm('¿Estás seguro?')"

// ✅ CORRECT
showModal({
    title: 'Confirmar Acción',
    message: '¿Estás seguro de que deseas continuar?',
    icon: '⚠️',
    confirmType: 'danger',
    onConfirm: function() {
        window.location.href = url;
    }
});
```

See full modal guidelines in `app/includes/admin/MODAL_GUIDELINES.md`.

### Never Create Direct File Access

**NEVER create PHP files that can be accessed directly via HTTP** outside of the 4 entry points.

All new PHP files should:
1. Be placed in `/app/` directory
2. Start with the security check:
   ```php
   if (!defined('APP_ENTRY_POINT')) {
       die('Direct access not permitted');
   }
   ```
3. Be loaded via the router or included from an entry point

### URL Generation

Use the `url()` helper function for all URLs:

```php
// Generate URLs
url('/') // Returns base URL
url('/producto/example') // Returns full URL with base path
url('/admin/?page=productos-listado')

// Redirects
redirect(url('/admin/'));
```

### Configuration Files

**System Configuration:**
- **`app/config/config.php`** - Main configuration (auto-generated, NEVER commit)
- **`app/config/config.example.php`** - Template for config.php
- **`app/config/paths.php`** - Path definitions (APP_PATH, PUBLIC_PATH, DATA_PATH)

**Feature Configuration (JSON):**
- **`app/config/site.json`** - Site metadata (name, description, logo, contact)
- **`app/config/theme.json`** - Active theme configuration
- **`app/config/payment.json`** - MercadoPago settings (credentials, preferences)
- **`app/config/shipping.json`** - **Shipping carriers config (Zipnova, multi-carrier)**
- **`app/config/currency.json`** - Currency settings (primary, exchange rates)
- **`app/config/email.json`** - Email configuration (SMTP, templates)
- **`app/config/telegram.json`** - Telegram bot configuration
- **`app/config/analytics.json`** - Google Analytics, Meta Pixel config
- **`app/config/footer.json`** - Footer content and links
- **`app/config/hero.json`** - Hero section configuration
- **`app/config/carousel.json`** - Product carousel settings
- **`app/config/dashboard.json`** - Admin dashboard widgets
- **`app/config/products-heading.json`** - Products section heading
- **`app/config/maintenance.json`** - Maintenance mode settings
- **`app/config/strings.json`** - Multi-language strings (future i18n)

## Creating New PHP Files

### Security and CSP Requirements

**ALL new PHP files MUST follow these rules:**

1. ✅ **Security check at the top** (except entry points)
2. ✅ **Use nonces for ALL inline scripts and styles**
3. ✅ **Use data-action attributes instead of onclick/onchange**
4. ✅ **Include event-handlers.js for event delegation**
5. ✅ **Use url() helper for image paths**
6. ✅ **Everything in Spanish** (code, comments, UI)

### Template for New Admin Pages

```php
<?php
/**
 * Admin - [Nombre de la Página]
 * [Descripción breve]
 */

// Security check - ALWAYS include this
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require admin authentication
require_admin();

// Initialize variables
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_data'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Process form data
        $data = sanitize_input($_POST['data'] ?? '');

        // Save to JSON
        $config = read_json(APP_PATH . '/config/your-config.json');
        $config['data'] = $data;

        if (write_json(APP_PATH . '/config/your-config.json', $config)) {
            $message = 'Datos guardados exitosamente';
            log_admin_action('data_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar los datos';
        }
    }
}

// Load configuration
$config = read_json(APP_PATH . '/config/your-config.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Configuración';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>

    <!-- IMPORTANT: Use nonce for ALL inline styles -->
    <style nonce="<?= csp_nonce() ?>">
        /* Your CSS here */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" id="myForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="data">Datos</label>
                    <input type="text" id="data" name="data" value="<?php echo htmlspecialchars($config['data'] ?? ''); ?>">
                </div>

                <!-- WRONG: onclick="myFunction()" -->
                <!-- CORRECT: data-action="myFunction" -->
                <button type="button" data-action="confirmSave" class="btn-save">
                    💾 Guardar
                </button>
            </form>
        </div>
    </div>

    <!-- IMPORTANT: Use nonce for ALL inline scripts -->
    <script nonce="<?= csp_nonce() ?>">
        // Your JavaScript here

        function confirmSave(event, element, params) {
            showModal({
                title: 'Confirmar',
                message: '¿Guardar los cambios?',
                onConfirm: function() {
                    document.getElementById('myForm').submit();
                }
            });
        }

        // ALWAYS export for event delegation
        window.confirmSave = confirmSave;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- IMPORTANT: ALWAYS include event-handlers.js -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
```

### Template for New Frontend Pages

```php
<?php
/**
 * Frontend - [Nombre de la Página]
 * [Descripción breve]
 */

// Security check - ALWAYS include this
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once APP_PATH . '/pages/frontend/maintenance.php';
    exit;
}

// Load configuration
$site_config = read_json(APP_PATH . '/config/site.json');
$footer_config = read_json(APP_PATH . '/config/footer.json');
$currency_config = read_json(APP_PATH . '/config/currency.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <!-- IMPORTANT: Use nonce for ALL inline styles -->
    <style nonce="<?= csp_nonce() ?>">
        /* Your custom CSS here */
    </style>
</head>
<body>
    <!-- Header Component -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <!-- Main Content -->
    <div class="container">
        <h1>Mi Página</h1>

        <!-- Product Cards Example -->
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <!-- WRONG: onclick="goToProduct('<?= $product['slug'] ?>')" -->
                <!-- CORRECT: data-action with params -->
                <div class="product-card" data-action="goToProduct" data-slug="<?php echo htmlspecialchars($product['slug']); ?>">
                    <div class="product-image">
                        <!-- IMPORTANT: Use url() for image paths -->
                        <img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p><?php echo format_product_price($product, $selected_currency); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toast for notifications -->
    <div class="toast" id="toast"></div>

    <!-- IMPORTANT: Use nonce for ALL inline scripts -->
    <script nonce="<?= csp_nonce() ?>">
        // Your JavaScript here

        function goToProduct(event, element, params) {
            const slug = params?.slug;
            if (slug) {
                window.location.href = '<?php echo url('/producto/'); ?>' + encodeURIComponent(slug);
            }
        }

        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // ALWAYS export for event delegation
        window.goToProduct = goToProduct;
        window.showToast = showToast;
    </script>

    <!-- IMPORTANT: ALWAYS include event-handlers.js -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>

    <!-- Cart Validator -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/cart-validator.js'); ?>"></script>

    <!-- Mobile Menu -->
    <?php include APP_PATH . '/includes/mobile-menu.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- Auto-update Exchange Rate (if enabled) -->
    <?php include APP_PATH . '/includes/auto-update-exchange.php'; ?>
</body>
</html>
```

### Common Event Delegation Patterns

```html
<!-- Button with simple action -->
<button data-action="myFunction">Click</button>

<!-- Button with parameters -->
<button data-action="deleteItem" data-item-id="123" data-item-name="Product">Delete</button>

<!-- Form validation -->
<form data-onsubmit="validateForm">
    <!-- form fields -->
</form>

<!-- Input change handler -->
<input type="checkbox" data-onchange="toggleOption" data-option-name="enabled">

<!-- Link with confirmation -->
<a href="<?php echo url('/delete?id=123'); ?>"
   data-action="confirmDelete"
   data-item-id="123">Delete</a>
```

```javascript
// JavaScript wrapper functions
function myFunction(event, element, params) {
    // event: The DOM event (click, change, etc.)
    // element: The element that triggered the action
    // params: Object with all data-* attributes

    console.log('Action triggered!');
}

function deleteItem(event, element, params) {
    event.preventDefault(); // Prevent default if needed

    const itemId = params?.itemId;
    const itemName = params?.itemName;

    showModal({
        title: 'Confirmar Eliminación',
        message: `¿Eliminar "${itemName}"?`,
        onConfirm: function() {
            // Perform deletion
        }
    });
}

function validateForm(event, element, params) {
    event.preventDefault();

    // Validate form
    if (isValid) {
        element.submit();
    }
}

// ALWAYS export to window
window.myFunction = myFunction;
window.deleteItem = deleteItem;
window.validateForm = validateForm;
```

### CSP Nonce Helper

```php
// ALWAYS use csp_nonce() for inline scripts and styles
<script nonce="<?= csp_nonce() ?>">
    // Your code
</script>

<style nonce="<?= csp_nonce() ?>">
    /* Your styles */
</style>

// For external scripts - NO nonce needed
<script src="<?php echo url('/assets/js/file.js'); ?>"></script>
```

### Image Path Helper

```php
// WRONG - Will cause 404 errors
<img src="<?php echo $product['thumbnail']; ?>">

// CORRECT - Use url() helper
<img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>">

// With object-fit for proper display
<img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
     alt="<?php echo htmlspecialchars($product['name']); ?>"
     style="width: 100%; height: 100%; object-fit: cover;">
```

## Deployment

### GitHub Actions Auto-Deploy

**IMPORTANT**: Every commit to GitHub `main` branch automatically deploys to production via FTP.

- **Repository**: https://github.com/pablopeu/shop-v2
- **Production URL**: https://peu.net/shopv2
- **Deploy Trigger**: Automatic on every push to `main`

**Workflow**:
1. Make changes locally
2. Commit locally: `git commit -m "feat: descripción"`
3. **ALWAYS push to GitHub**: `git push origin main`
4. GitHub Actions automatically deploys to production via FTP

Required secrets (already configured in GitHub Settings → Secrets):
- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

See `.github/DEPLOY.md` for full deployment documentation.

### Production Structure

```
/home2/uv0023/
├── shop-v2-app/              # Private code (app/)
└── public_html/shopv2/        # Public code (public_html/)
```

### Installation

1. Run installer: `http://your-domain.com/shopv2/install/installer.php`
2. Follow setup wizard
3. **IMPORTANT**: Delete `/install/` directory after installation (use auto-delete button)

## MercadoPago Integration

### Webhook Security

The webhook (`public_html/webhook.php`) implements multiple security layers:

1. **Rate limiting** - 100 requests per 60 seconds
2. **IP validation** - Verifies request comes from MercadoPago IPs
3. **X-Signature validation** - HMAC signature verification
4. **Timestamp validation** - Prevents replay attacks (5-minute window)

All webhook events are logged in `app/data/webhook_log.json` and detailed MP logs in `app/data/mp_logs.json`.

### Payment Status Mapping

```
approved        → cobrada (stock reduced)
authorized      → pendiente
pending         → pendiente
in_process      → pendiente
in_mediation    → pendiente
rejected        → rechazada (stock restored)
cancelled       → rechazada (stock restored)
refunded        → cancelada (stock restored)
charged_back    → cancelada (stock restored)
```

## Shipping Integration (Zipnova & Multi-Carrier)

### Overview

The system includes a **full shipping/logistics integration** with Zipnova as the primary carrier, built on an extensible **multi-carrier architecture** for future integrations (Andreani, Correo Argentino, etc.).

### Key Components

**Backend:**
- **Configuration**: `app/config/shipping.json` - Multi-carrier settings
- **Core Logic**: `app/includes/carriers.php` (931 lines) - Universal carrier integration
- **Admin Panel**: `app/pages/admin/config-shipping.php` - Carrier configuration
- **Shipment Management**:
  - `app/pages/admin/envios-pendientes.php` - Pending shipments
  - `app/pages/admin/envios-archivo.php` - Archived shipments
- **API Endpoints**: `app/pages/api/shipping.php` - Quotes, create, track
- **Data Storage**: `app/data/shipments/` - Per-order shipping data

**Frontend:**
- **New Checkout**: `app/pages/frontend/checkout-new.php` (2800+ lines) - Vertical layout with shipping
- **JavaScript Module**: `public_html/assets/js/shipping.js` (500+ lines) - Frontend shipping logic

**Logs:**
- `/logs/zipnova/` - Daily event logs
- `/logs/zipnova-responses/` - Debug JSON responses

### Multi-Carrier Architecture

**Carrier Identification:**
- Carriers identified by **4-letter tags** (ZNVA for Zipnova, etc.)
- Extensible for future carriers (ANDR, OCAS, etc.)

**Universal Base Status:**
```
pendiente       → Shipment created, not yet dispatched
en_transito     → In transit to destination
en_reparto      → Out for delivery
entregada       → Successfully delivered
cancelada       → Cancelled by seller/customer
rechazada       → Rejected by recipient
devuelta        → Returned to sender
fallida         → Delivery failed
```

**Per-Carrier Configuration:**
```json
{
  "carriers": {
    "ZNVA": {
      "tag": "ZNVA",
      "name": "Zipnova",
      "type": "zipnova",
      "enabled": false,
      "mode": "sandbox",
      "credentials": {
        "account_id": "...",
        "client_id": "...",
        "client_secret": "..."
      },
      "origin": {
        "origin_id": "...",
        "name": "...",
        "address": "...",
        "city": "...",
        "province": "...",
        "postal_code": "...",
        "country": "AR",
        "phone": "...",
        "email": "..."
      },
      "default_package": {
        "weight": 500,
        "length": 20,
        "width": 15,
        "height": 10
      },
      "options": {
        "webhook_secret": "...",
        "auto_create_shipment": false,
        "shipping_cost_margin": 0,
        "cache_quotes_minutes": 30,
        "timeout_seconds": 30,
        "max_retries": 3
      },
      "enabled_services": {
        "standard": true,
        "express": true,
        "same_day": false
      }
    }
  }
}
```

### Orders Structure with Shipping

New `shipping` object in orders:

```json
{
  "shipping": {
    "method": "standard",
    "service_name": "Envío Estándar",
    "cost": 2500,
    "carrier": "ZNVA",
    "carrier_shipment_id": "123456",
    "carrier_status": "in_transit",
    "tracking_id": "TRACK123",
    "status": "en_transito",
    "address": {
      "name": "Juan Pérez",
      "street": "Av. Corrientes 1234",
      "city": "Buenos Aires",
      "province": "Buenos Aires",
      "postal_code": "C1043AAZ",
      "country": "AR",
      "phone": "+54 11 1234-5678"
    },
    "estimated_delivery": "3-5",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T14:20:00Z",
    "history": [
      {
        "status": "pendiente",
        "timestamp": "2024-01-15T10:30:00Z",
        "notes": "Shipment created"
      },
      {
        "status": "en_transito",
        "timestamp": "2024-01-15T14:20:00Z",
        "notes": "Picked up by carrier"
      }
    ]
  }
}
```

### Common Shipping Functions

**Carrier Configuration:**
```php
get_carrier_config($carrier_tag)  // Get config for a specific carrier
get_all_carriers()                 // List all configured carriers
```

**Zipnova API:**
```php
// Get shipping quotes
zipnova_get_quotes($destination, $items, $value)

// Create shipment
zipnova_create_shipment($data)

// Get shipment status
zipnova_get_shipment($shipment_id)

// Cancel shipment
zipnova_cancel_shipment($shipment_id)

// Test API connection
zipnova_test_connection()
```

**Helper Functions:**
```php
// Calculate delivery time from ISO 8601 duration
calculate_delivery_days($delivery_time)  // e.g., "P3DT2H" → "3-5 días"

// Parse ISO 8601 duration to days
parse_iso8601_duration_to_days($duration)

// Build packages from cart items
zipnova_build_packages_from_cart($cart_items)

// Calculate cart metrics
zipnova_calculate_cart_weight($cart_items)
zipnova_calculate_cart_dimensions($cart_items)
zipnova_calculate_cart_value($cart_items, $currency)

// Status mapping
map_carrier_status_to_base($type, $status)  // Map carrier status → base status
get_status_label($status)                   // Get human-readable label

// Render status HTML
render_shipping_status($status)
```

**Logging:**
```php
zipnova_log($message, $level, $context)           // Log events
zipnova_save_response_json($response, $endpoint)  // Save debug JSON
```

### New Vertical Checkout (`checkout-new.php`)

**Features:**
- Vertical responsive layout (2-column on desktop, stacked on mobile)
- Step-by-step validation (blocked until previous steps complete)
- Delivery method selection (pickup vs shipping)
- Real-time shipping quotes from Zipnova
- Automatic weight/dimension calculation from cart
- Shipping cost integration in total
- Session timeout (1 hour)
- Multi-currency support (ARS/USD)
- MercadoPago integration with shipping cost included

**Shipping Calculation:**
- Weight from product data or defaults (500g per item if missing)
- Dimensions from product data or defaults (20×15×10 cm if missing)
- Declared value = total cart value in ARS
- Automatic package consolidation

**Flow:**
1. Select delivery method (retiro/envío)
2. If envío → Enter shipping address
3. Click "Cotizar Envío" → Get real-time quotes from Zipnova
4. Select shipping service → Cost added to total
5. Complete customer info
6. Proceed to MercadoPago payment (includes shipping cost)

### API Endpoints

**Shipping API (`/api/shipping`):**

```php
// Get quotes
GET  /api/shipping?action=quotes&postal_code=1234&city=...
POST /api/shipping (with full address + cart data)

// Create shipment
POST /api/shipping?action=create

// Track shipment
GET  /api/shipping?action=track&id=SHIPMENT_ID

// Webhook (for carrier status updates)
POST /api/shipping (with webhook signature)
```

### Admin Shipment Management

**Pending Shipments (`envios-pendientes.php`):**
- List all pending shipments
- Filter by status, reference, date
- Create shipment in carrier system
- Cancel shipment
- View tracking details
- Export to CSV

**Archived Shipments (`envios-archivo.php`):**
- Historical record of completed/cancelled shipments
- Same filters and export options

### Security Features

**Zipnova API:**
- HTTP Basic Authentication (client_id:client_secret)
- Retry logic with exponential backoff (max 3 retries)
- Request timeout (30 seconds default)
- Rate limiting
- Webhook signature validation
- Detailed logging of all requests/responses

**Data Validation:**
- Address validation (required fields, postal code format)
- Weight/dimension validation
- Package value validation
- Service availability checks

### Future Carrier Integration

The architecture is ready for:
- **Andreani** (tag: ANDR)
- **Correo Argentino** (tag: OCAS)
- **DHL** (tag: DHLE)
- Custom carriers with adapter pattern

**Adding a new carrier:**
1. Create carrier config in `shipping.json`
2. Implement carrier-specific functions in `carriers.php`
3. Map carrier statuses to base statuses
4. Add to admin UI in `config-shipping.php`

## API Endpoints

The system exposes several API endpoints under `/api/`:

### Shipping API
- **`/api/shipping`** - Shipping operations (quotes, create, track, webhook)
  - `?action=quotes` - Get shipping quotes
  - `?action=create` - Create shipment
  - `?action=track&id=ID` - Track shipment

### Product API
- **`/api/get-products`** - Get product list (with filters)
- **`/api/update-products-order`** - Update product display order

### Order API
- **`/api/get-order`** - Get active order details
- **`/api/get-archived-order`** - Get archived order details
- **`/api/cancel-order`** - Cancel order
- **`/api/export-orders`** - Export orders to CSV
- **`/api/export-archived-orders`** - Export archived orders

### Coupon & Promotion API
- **`/api/validate-coupon`** - Validate coupon code
- **`/api/get-promotion`** - Get active promotion details

### Payment API
- **`/api/crear-preferencia-mp`** - Create MercadoPago payment preference

### Utility API
- **`/api/sync-cart`** - Sync cart between sessions
- **`/api/create-short-link`** - Create short link for sharing
- **`/api/get-shared-wishlist`** - Get shared wishlist
- **`/api/update-exchange-rate`** - Update currency exchange rate
- **`/api/send-test-email`** - Send test email (admin)
- **`/api/send-telegram-test`** - Send test Telegram message (admin)

**API Response Format:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message",
  "error": "Error message if success=false"
}
```

**Authentication:**
- Admin APIs require active session (`require_admin()`)
- Public APIs use rate limiting
- CSRF tokens for state-changing operations

## Admin Panel Structure

### Admin Pages Location

All admin pages are in `app/pages/admin/`:

**Dashboard:**
- `index.php` - Main dashboard with stats

**Products:**
- `productos-listado.php` - Product listing
- `productos-nuevo.php` - Create new product
- `productos-editar.php` - Edit product
- `productos-archivados.php` - Archived products
- `productos.php` - Legacy product page

**Sales:**
- `ventas.php` - Active sales/orders
- `archivo-ventas.php` - Archived sales

**Shipping:** (NEW)
- `envios-pendientes.php` - Pending shipments management
- `envios-archivo.php` - Archived shipments

**Coupons:**
- `cupones-listado.php` - Coupon listing
- `cupones-nuevo.php` - Create new coupon
- `cupones-editar.php` - Edit coupon
- `cupones-archivados.php` - Archived coupons

**Promotions:**
- `promociones-listado.php` - Promotion listing
- `promociones-nuevo.php` - Create new promotion
- `promociones-editar.php` - Edit promotion
- `promociones-archivados.php` - Archived promotions

**Reviews:**
- `reviews-listado.php` - Product reviews management

**Configuration:**
- `config-sitio.php` - Site settings (name, logo, contact)
- `config-payment.php` - MercadoPago configuration
- `config-shipping.php` - **Shipping carriers config (Zipnova)**
- `config-themes.php` - Theme selection and customization
- `config-moneda.php` - Currency settings
- `config-analytics.php` - Analytics configuration
- `config-carrusel.php` - Carousel settings
- `config-dashboard.php` - Dashboard widgets
- `config-footer.php` - Footer content
- `config-hero.php` - Hero section
- `config-mantenimiento.php` - Maintenance mode
- `config-sistema.php` - System settings
- `config-productos-heading.php` - Products section heading
- `config-rutas-sistema.php` - System routes configuration
- `config-limpieza-imagenes.php` - Image cleanup utilities
- `config-backup.php` - Backup management

**Utilities:**
- `generador-themes.php` - Theme generator/customizer
- `notificaciones.php` - Notifications management
- `reprocesar-pago-mp.php` - Reprocess MercadoPago payment

### Admin Components

- **Header**: `app/includes/admin/header.php`
- **Sidebar**: `app/includes/admin/sidebar.php`
- **Modal**: `app/includes/admin/modal.php`
- **Styles**: `app/includes/admin/styles.php`
- **Common Styles**: `app/includes/admin/admin-common-styles.php`
- **Session Monitor**: `app/includes/admin/session-monitor.js`
- **Unsaved Changes Warning**: `app/includes/admin/unsaved-changes-warning.js`

## Theme System

Themes are located in `public_html/assets/themes/` with the following structure:

```
themes/
├── _base/                  # Base CSS variables
├── classic/                # Classic elegant theme
│   ├── theme.json
│   ├── theme.css
│   └── variables.css
├── modern/                 # Modern minimalist theme
│   ├── theme.json
│   ├── theme.css
│   └── variables.css
├── modern-compact/         # Compact modern theme (NEW)
│   ├── theme.json
│   ├── theme.css
│   └── variables.css
└── archivo/                # Archive of multiple theme variants
    ├── minimal/
    ├── bold/
    ├── elegant/
    ├── dark/
    ├── luxury/
    ├── vibrant/
    └── fresh/
```

Active theme is set in `app/config/theme.json`.

**Theme System Functions:**
- `render_theme_css($theme_name)` - Load theme CSS dynamically
- Theme generator available at: `admin/?page=generador-themes`

## Common Utilities

### JSON Operations

```php
// Read JSON with file locking
$data = read_json('/path/to/file.json');

// Write JSON with file locking and pretty print
write_json('/path/to/file.json', $data);
```

### Authentication

```php
// Require admin authentication
require_admin(); // Redirects to login if not authenticated

// Check session timeout
check_session_timeout(3600); // 1 hour

// Logout
logout();
```

### Security

```php
// Set security headers (includes CSP with nonces)
set_security_headers();

// CSP Nonce - use in ALL inline scripts and styles
csp_nonce(); // Returns current session nonce

// CSRF protection
generate_csrf_token(); // Generate token
validate_csrf_token($token); // Verify token

// Rate limiting
check_rate_limit($identifier, $max_attempts, $window_seconds);
```

### Content Security Policy (CSP)

The project uses **strict CSP** with nonces to prevent XSS attacks:

```php
// Current CSP Policy (in app/includes/security.php):
script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://cdnjs.cloudflare.com https://sdk.mercadopago.com ...;
style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com ...;
```

**Key Rules:**
- ✅ All inline `<script>` and `<style>` tags MUST have `nonce="<?= csp_nonce() ?>"`
- ✅ External scripts (from `/assets/js/`) don't need nonces
- ✅ NO `onclick`, `onchange`, `onsubmit` - use `data-action` instead
- ❌ Scripts without nonce will be **blocked by the browser**
- ⚠️ `'unsafe-eval'` is enabled (required by MercadoPago SDK)

**Event Delegation System:**
- All event handlers use the custom event delegation system
- Located at: `public_html/assets/js/event-handlers.js`
- Automatically processes `data-action`, `data-onchange`, `data-onsubmit` attributes
- Functions are called with signature: `function(event, element, params)`

## Testing and Diagnostics

Diagnostic scripts are in `public_html/scripts/`:

- `diagnostico.php` - System diagnostics
- `test-dolarapi.php` - Test currency API
- `test-layout.php` - Test page layout
- `reset-admin.php` - Reset admin password

## Important Notes

### Base Path Configuration

**CRITICAL**: The system uses a dynamic BASE_PATH that changes per environment.

**Where BASE_PATH is stored:**
- **File**: `public_html/bootstrap_path.php` (auto-generated by installer)
- **Content**: `define('BASE_PATH', '/shopv2');` (production) or `define('BASE_PATH', '');` (local)
- **NEVER commit** this file - it's environment-specific

**How it works:**
- **Production**: `/shopv2` (peu.net/shopv2)
- **Local Development**: `` (empty string for localhost)
- **Testing**: Custom path as needed

**Files that use BASE_PATH:**
- `public_html/.htaccess` - FallbackResource (must use variable, NO hardcoded paths)
- `public_html/admin/.htaccess` - Admin routing
- `url()` helper function - Prepends BASE_PATH to all URLs
- Router URL parsing - Strips BASE_PATH from REQUEST_URI

**IMPORTANT .htaccess Rules:**
- ✅ `.htaccess` files MUST use `RewriteBase` or `FallbackResource` with BASE_PATH variable
- ❌ **NEVER hardcode** `/shopv2` in .htaccess
- ✅ Use: `FallbackResource /index.php` (relative)
- ❌ Don't use: `FallbackResource /shopv2/index.php` (hardcoded)

**Example .htaccess (correct):**
```apache
RewriteEngine On
RewriteBase /
FallbackResource /index.php
```

The `index.php` entry point reads `bootstrap_path.php` to get BASE_PATH and handles routing accordingly.

### Stock Management

Stock is automatically:
- **Reduced** when payment status becomes `approved`
- **Restored** when payment is `rejected`, `cancelled`, `refunded`, or `charged_back`

Stock changes are logged and tracked with `stock_reduced` flag in orders.

### File Upload Paths

Product images are stored in: `public_html/uploads/products/`

Image URLs should use `url('/uploads/products/filename.jpg')`.

## Development Checklist

When adding new features or modifying code:

### Security
- [ ] No new direct entry points (use existing 4)
- [ ] All private code in `/app/` with APP_ENTRY_POINT check
- [ ] Use `read_json()` and `write_json()` for thread-safe JSON operations
- [ ] Validate all user input
- [ ] Use CSRF tokens for state-changing operations
- [ ] **CSP Compliance**: All inline scripts and styles use `nonce="<?= csp_nonce() ?>"`
- [ ] **Event Delegation**: Use `data-action` instead of `onclick/onchange/onsubmit`
- [ ] **Include event-handlers.js** at the end of every page with JavaScript

### Project Rules
- [ ] All code, comments, and UI text in Spanish
- [ ] Use custom modals instead of `alert()`, `confirm()`, `prompt()`
- [ ] NO hardcoded paths - use constants (`APP_PATH`, `PUBLIC_PATH`, etc.)
- [ ] Use `url()` helper for all URL generation (especially for images)
- [ ] NEVER instruct user to create JSON files manually
- [ ] NO inline event handlers - always use event delegation pattern
- [ ] Export all JavaScript functions to `window` for event delegation

### Code Quality
- [ ] Images use `url($product['thumbnail'])` with `object-fit: cover`
- [ ] All forms include CSRF token validation
- [ ] JavaScript functions follow event delegation signature: `function(event, element, params)`
- [ ] All modals use `showModal()` instead of native dialogs

### Deployment
- [ ] los testeos siempre se hacen en https://peu.net/shopv2 luego del deploy
- [ ] Commit locally with descriptive message
- [ ] Push to GitHub (triggers auto-deploy to production)
- [ ] Verify deployment succeeded at https://peu.net/shopv2
- a menos que yo te diga que el deploy fallo, no asumas que el deploy fallo