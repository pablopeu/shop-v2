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

## Additional Documentation

For detailed information on specific topics, consult these files:

- **[CLAUDE.TEMPLATES.md](CLAUDE.TEMPLATES.md)** - Complete templates for creating new admin/frontend pages, event delegation patterns, CSP helpers
- **[CLAUDE.SHIPPING.md](CLAUDE.SHIPPING.md)** - Full shipping/logistics integration documentation (Zipnova, multi-carrier architecture)
- **[CLAUDE.REFERENCE.md](CLAUDE.REFERENCE.md)** - Technical reference: routing, data storage, functions, includes, configuration files, API endpoints, admin panel structure, theme system, utilities, MercadoPago integration, deployment info

**When to consult:**
- Creating new pages → See CLAUDE.TEMPLATES.md
- Working with shipping/logistics → See CLAUDE.SHIPPING.md
- Need technical details (API, routing, config files, etc.) → See CLAUDE.REFERENCE.md
