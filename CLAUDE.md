# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Shop V2 is a **professional e-commerce platform refactored with security-first architecture**. The key principle is that **ALL private code is OUTSIDE public_html and INACCESSIBLE from the internet**.

**Project Language**: Spanish (all code, comments, and UI are in Spanish)

**Repository**: https://github.com/pablopeu/shop-v2

**Production URL**: https://peu.net/shopv2

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

## Critical Project Rules

### 1. Language and Communication
- **All code, comments, variable names, and UI text MUST be in Spanish**
- Error messages, logs, and user-facing text are in Spanish

### 2. Deployment Workflow
- **GitHub Actions auto-deploys to production on every commit**
- When making local commits, ALWAYS push to GitHub
- Production deploys automatically via FTP to https://peu.net/shopv2
- Never commit without pushing to GitHub

### 3. NO Alert Boxes or Message Boxes
- **NEVER use `alert()`, `confirm()`, `prompt()`, or any native browser dialogs**
- The project has a **reusable custom modal** component at `app/includes/admin/modal.php`
- All user communication and alerts MUST use the custom modal (see MODAL_GUIDELINES.md)

### 4. NO Hardcoded Paths
- **The project NEVER uses hardcoded paths**
- All paths use the environment detection system (production/testing/development)
- If you find hardcoded paths during editing, FIX them immediately
- Use constants: `APP_PATH`, `PUBLIC_PATH`, `DATA_PATH`, etc.
- Use the `url()` helper for all URL generation

### 5. NEVER Ask User to Create JSON Files
- **NEVER instruct the user to manually create JSON files**
- All JSON files are created automatically by:
  - Backend system functions (`read_json()`, `write_json()`)
  - The installer (`public_html/install/installer.php`)
- If a JSON file is missing, the system creates it with default structure
- User should NEVER touch JSON files directly

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
GET /carrito             → app/pages/frontend/carrito.php
GET /checkout            → app/pages/frontend/checkout.php
GET /favoritos           → app/pages/frontend/favoritos.php
GET /track               → app/pages/frontend/track.php
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
- **Coupons**: `app/data/coupons.json`
- **Promotions**: `app/data/promotions.json`
- **Reviews**: `app/data/reviews.json`

### File Locking

All JSON read/write operations use file locking via `read_json()` and `write_json()` in `app/includes/functions.php` to prevent race conditions.

## Key Functions and Includes

### Core System Files (loaded in bootstrap.php)

- `app/includes/functions.php` - Core utilities (JSON read/write, redirects, etc.)
- `app/includes/security.php` - Security headers, CSRF protection
- `app/includes/router.php` - Router class for URL routing
- `app/includes/auth.php` - Authentication functions (`require_admin()`, session management)
- `app/includes/products.php` - Product management functions
- `app/includes/orders.php` - Order management functions
- `app/includes/mercadopago.php` - MercadoPago payment integration
- `app/includes/email.php` - Email sending functions
- `app/includes/telegram.php` - Telegram bot notifications
- `app/includes/coupons.php` - Coupon system
- `app/includes/promotions.php` - Promotions system

### HTML Components (NOT function libraries)

These should be included in pages, NOT in bootstrap.php:

- `app/includes/carousel.php`
- `app/includes/mobile-menu.php`
- `app/includes/tracking-events.php`
- `app/includes/tracking-scripts.php`

## Common Development Commands

### PHP Development Server (Development Only)

```bash
cd /home/pablo/shop-v2/public_html
php -S localhost:8000
```

Access at: `http://localhost:8000`

### Testing Payment Webhooks Locally

```bash
# Test webhook processing
php public_html/webhook.php
```

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

- **`app/config/config.php`** - Main configuration (auto-generated, NEVER commit)
- **`app/config/config.example.php`** - Template for config.php
- **`app/config/paths.php`** - Path definitions
- **`app/config/payment.json`** - Payment gateway settings
- **`app/config/site.json`** - Site metadata
- **`app/config/theme.json`** - Active theme configuration

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

## Admin Panel Structure

### Admin Pages Location

All admin pages are in `app/pages/admin/`:

- Dashboard: `index.php`
- Products: `productos-listado.php`, `productos-nuevo.php`, `productos-editar.php`
- Sales: `ventas.php`, `archivo-ventas.php`
- Coupons: `cupones-listado.php`, `cupones-nuevo.php`, `cupones-editar.php`
- Promotions: `promociones-listado.php`, `promociones-nuevo.php`
- Config: `config-sitio.php`, `config-payment.php`, `config-themes.php`

### Admin Components

- **Header**: `app/includes/admin/header.php`
- **Modal**: `app/includes/admin/modal.php`
- **Styles**: `app/includes/admin/styles.php`
- **Common Styles**: `app/includes/admin/admin-common-styles.php`

## Theme System

Themes are located in `public_html/assets/themes/` with the following structure:

```
themes/
├── archivo/
│   ├── bold/theme.json
│   ├── elegant/theme.json
│   └── minimal/theme.json
└── classic/theme.json
```

Active theme is set in `app/config/theme.json`.

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

Production uses `/shopv2` as base path. This is handled in:
- `.htaccess` FallbackResource
- `url()` helper function
- Router URL parsing

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