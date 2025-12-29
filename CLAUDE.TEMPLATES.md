# Templates para Nuevas Páginas

## Security and CSP Requirements

**ALL new PHP files MUST follow these rules:**

1. ✅ **Security check at the top** (except entry points)
2. ✅ **Use nonces for ALL inline scripts and styles**
3. ✅ **Use data-action attributes instead of onclick/onchange**
4. ✅ **Include event-handlers.js for event delegation**
5. ✅ **Use url() helper for image paths**
6. ✅ **Everything in Spanish** (code, comments, UI)

## Template for New Admin Pages

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

## Template for New Frontend Pages

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

## Common Event Delegation Patterns

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

## Helper Snippets

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
