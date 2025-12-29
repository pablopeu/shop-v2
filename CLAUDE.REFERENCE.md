# Referencia Técnica del Sistema

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

## Configuration Files

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

## Testing and Diagnostics

Diagnostic scripts are in `public_html/scripts/`:

- `diagnostico.php` - System diagnostics
- `test-dolarapi.php` - Test currency API
- `test-layout.php` - Test page layout
- `reset-admin.php` - Reset admin password
