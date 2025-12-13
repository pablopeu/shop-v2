# Guía de Desarrollo - Shop V2

Guía completa para desarrolladores que trabajan en Shop V2.

**Última actualización**: 2025-12-08

---

## 📑 Tabla de Contenidos

- [Setup Inicial](#setup-inicial)
- [Entorno de Desarrollo](#entorno-de-desarrollo)
- [Workflow de Git](#workflow-de-git)
- [Convenciones de Código](#convenciones-de-código)
- [Estructura de Archivos](#estructura-de-archivos)
- [Crear Nuevas Páginas](#crear-nuevas-páginas)
- [Trabajar con Componentes](#trabajar-con-componentes)
- [JavaScript y CSP](#javascript-y-csp)
- [Debugging](#debugging)
- [Testing](#testing)
- [Deploy](#deploy)
- [Troubleshooting](#troubleshooting)

---

## Setup Inicial

### Requisitos

- **PHP**: 7.4 o superior
- **Git**: 2.30 o superior
- **Composer**: (opcional, no usado actualmente)
- **Editor**: VS Code recomendado

### Clonar el Repositorio

```bash
git clone https://github.com/pablopeu/shop-v2.git
cd shop-v2
```

### Configuración Local

1. **Copiar configuración de ejemplo**:
   ```bash
   cp app/config/config.example.php app/config/config.php
   ```

2. **Editar `config.php`**:
   ```php
   <?php
   define('DB_TYPE', 'json');
   define('ADMIN_USERNAME', 'admin');
   define('ADMIN_PASSWORD', password_hash('admin123', PASSWORD_BCRYPT));
   define('SECRET_KEY', bin2hex(random_bytes(32)));
   define('SESSION_TIMEOUT', 3600);
   define('MAINTENANCE_MODE', false);
   ?>
   ```

3. **Verificar permisos**:
   ```bash
   chmod 750 app/data/
   chmod 755 public_html/uploads/
   ```

4. **Iniciar servidor de desarrollo**:
   ```bash
   cd public_html
   php -S localhost:8000
   ```

5. **Acceder**:
   - Frontend: `http://localhost:8000`
   - Admin: `http://localhost:8000/admin/login.php`

---

## Entorno de Desarrollo

### VS Code Extensions Recomendadas

```json
{
    "recommendations": [
        "bmewburn.vscode-intelephense-client",  // PHP IntelliSense
        "xdebug.php-debug",                      // PHP Debugging
        "esbenp.prettier-vscode",                // Formateo de código
        "dbaeumer.vscode-eslint",                // JavaScript Linting
        "ms-vscode.vscode-json",                 // JSON Tools
        "formulahendry.auto-close-tag",          // Auto-cerrar tags HTML
        "ritwickdey.liveserver"                  // Live reload (opcional)
    ]
}
```

### Settings de VS Code

```json
{
    "editor.tabSize": 4,
    "editor.insertSpaces": true,
    "files.encoding": "utf8",
    "files.eol": "\n",
    "php.suggest.basic": true,
    "php.validate.enable": true,
    "php.validate.executablePath": "/usr/bin/php",
    "[php]": {
        "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
    },
    "[javascript]": {
        "editor.defaultFormatter": "esbenp.prettier-vscode"
    }
}
```

### Estructura del Workspace

```
shop-v2/
├── .vscode/
│   ├── settings.json       # Settings del proyecto
│   ├── extensions.json     # Extensions recomendadas
│   └── launch.json         # Config de debugging
├── app/
├── public_html/
├── docs/
└── .gitignore
```

---

## Workflow de Git

### Branches

El proyecto usa Git Flow simplificado:

- **`main`**: Rama principal (producción, auto-deploy)
- **`feature/*`**: Nuevas funcionalidades
- **`fix/*`**: Correcciones de bugs
- **`refactor/*`**: Refactorings sin cambio funcional
- **`docs/*`**: Actualizaciones de documentación

### Crear Feature Branch

```bash
# Actualizar main
git checkout main
git pull origin main

# Crear feature branch
git checkout -b feature/nueva-funcionalidad

# Trabajar en la feature
# ... hacer cambios ...

# Commit frecuente
git add .
git commit -m "feat: agregar componente de rating"

# Push al branch
git push origin feature/nueva-funcionalidad
```

### Prefijos de Commits

Usar [Conventional Commits](https://www.conventionalcommits.org/):

| Prefijo | Uso | Ejemplo |
|---------|-----|---------|
| `feat:` | Nueva funcionalidad | `feat: agregar sistema de ratings` |
| `fix:` | Corrección de bug | `fix: corregir cálculo de total en carrito` |
| `refactor:` | Refactoring sin cambio funcional | `refactor: extraer lógica de pago a función` |
| `style:` | Cambios de formato/estilo | `style: formatear código con prettier` |
| `docs:` | Documentación | `docs: actualizar README con nueva API` |
| `chore:` | Tareas de mantenimiento | `chore: actualizar .gitignore` |
| `test:` | Tests | `test: agregar tests para checkout` |

### Pull Requests

1. **Crear PR en GitHub**:
   ```bash
   gh pr create --title "Feature: Sistema de Ratings" --body "Descripción..."
   ```

2. **Template de PR**:
   ```markdown
   ## Descripción
   [Descripción de los cambios]

   ## Tipo de Cambio
   - [ ] Nueva funcionalidad
   - [ ] Bug fix
   - [ ] Refactoring
   - [ ] Documentación

   ## Testing
   - [ ] Probado localmente
   - [ ] Probado en producción
   - [ ] Sin errores en console

   ## Checklist
   - [ ] Código sigue convenciones del proyecto
   - [ ] Documentación actualizada
   - [ ] Sin hardcoded paths
   - [ ] CSP compatible (sin onclick/eval)
   - [ ] Todo en español
   ```

3. **Merge a main**:
   ```bash
   # Después de review y aprobación
   git checkout main
   git merge feature/nueva-funcionalidad
   git push origin main  # Esto dispara auto-deploy
   ```

### Commits Intermedios

Durante tareas largas, hacer commits intermedios:

```bash
# Cada 10-15 minutos de cambios
git add .
git commit -m "chore: checkpoint - implementando sistema de ratings"
git push origin feature/nueva-funcionalidad
```

---

## Convenciones de Código

### PHP

#### Naming

- **Variables**: `snake_case`
- **Funciones**: `snake_case`
- **Clases**: `PascalCase`
- **Constantes**: `UPPER_CASE`

```php
// ✅ CORRECTO
$user_name = 'Juan';
function get_user_data() { }
class ProductManager { }
define('MAX_ITEMS', 100);

// ❌ INCORRECTO
$userName = 'Juan';
function getUserData() { }
class product_manager { }
define('max_items', 100);
```

#### Todo en Español

```php
// ✅ CORRECTO
$nombre_producto = 'Laptop';
$precio_total = 1500.00;
function calcular_descuento($precio, $porcentaje) { }

// ❌ INCORRECTO
$product_name = 'Laptop';
$total_price = 1500.00;
function calculate_discount($price, $percentage) { }
```

#### Security Check

Todos los archivos en `app/`:

```php
<?php
// Siempre al inicio del archivo
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}
?>
```

#### Sanitización

```php
// ✅ CORRECTO
echo htmlspecialchars($user_input);
echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8');

// ❌ INCORRECTO
echo $user_input;  // XSS vulnerability
```

#### Paths y URLs

```php
// ✅ CORRECTO
require_once APP_PATH . '/includes/functions.php';
$image_url = url('/uploads/products/image.jpg');

// ❌ INCORRECTO
require_once '/home/pablo/shop-v2/app/includes/functions.php';
$image_url = '/shopv2/uploads/products/image.jpg';
```

#### File Operations

```php
// ✅ CORRECTO (con file locking)
$data = read_json(APP_PATH . '/data/products.json');
write_json(APP_PATH . '/data/products.json', $data);

// ❌ INCORRECTO (sin file locking)
$data = json_decode(file_get_contents($file), true);
file_put_contents($file, json_encode($data));
```

### JavaScript

#### Naming

- **Variables**: `camelCase`
- **Funciones**: `camelCase`
- **Constantes**: `UPPER_CASE`
- **Namespaces**: `PascalCase`

```javascript
// ✅ CORRECTO
const userName = 'Juan';
function getUserData() { }
const MAX_ITEMS = 100;
window.ShopUtils = { };

// ❌ INCORRECTO
const user_name = 'Juan';
function get_user_data() { }
const maxItems = 100;
window.shopUtils = { };
```

#### Event Delegation

```html
<!-- ✅ CORRECTO -->
<button data-action="myFunction">Click</button>

<!-- ❌ INCORRECTO -->
<button onclick="myFunction()">Click</button>
```

#### Exportar Funciones

```javascript
// ✅ CORRECTO
function myFunction() { }
window.myFunction = myFunction;

// ❌ INCORRECTO (no accesible desde event-handlers.js)
function myFunction() { }
```

#### Validación

```javascript
// ✅ CORRECTO
function addToCart(event, element, params) {
    if (!params.slug) {
        ShopUtils.showToast('Error: producto no encontrado', 'error');
        return;
    }
    // ...
}

// ❌ INCORRECTO (sin validación)
function addToCart(event, element, params) {
    ShopCart.addToCart(params.slug, 1);  // Puede fallar
}
```

### CSS

#### BEM Naming

```css
/* ✅ CORRECTO */
.product-card { }
.product-card__image { }
.product-card__title { }
.product-card--featured { }

/* ❌ INCORRECTO */
.productCard { }
.product_card_image { }
```

#### Variables CSS

```css
/* ✅ CORRECTO (usar variables) */
.button {
    background: var(--color-primary);
    border-radius: var(--border-radius);
}

/* ❌ INCORRECTO (hardcoded) */
.button {
    background: #007bff;
    border-radius: 4px;
}
```

#### Mobile-First

```css
/* ✅ CORRECTO */
.product-grid {
    grid-template-columns: 1fr;  /* Mobile por defecto */
}

@media (min-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .product-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
```

---

## Estructura de Archivos

### Dónde Crear Cada Tipo de Archivo

| Tipo de Archivo | Ubicación | Ejemplo |
|----------------|-----------|---------|
| **Página frontend** | `app/pages/frontend/` | `producto.php` |
| **Página admin** | `app/pages/admin/` | `productos-listado.php` |
| **Componente frontend** | `app/includes/frontend/` | `product-card.php` |
| **Componente admin** | `app/includes/admin/` | `modal.php` |
| **Función de negocio** | `app/includes/` | `products.php` |
| **JavaScript** | `public_html/assets/js/` | `shop-utils.js` |
| **CSS base** | `public_html/assets/themes/_base/` | `components.css` |
| **CSS de theme** | `public_html/assets/themes/{theme}/` | `theme.css` |
| **CSS de página** | `public_html/assets/themes/_base/pages/` | `home.css` |
| **Imagen de producto** | `public_html/uploads/products/` | `producto-1.jpg` |
| **Config JSON** | `app/config/` | `site.json` |
| **Datos JSON** | `app/data/` | `products.json` |

---

## Crear Nuevas Páginas

### 1. Página Frontend

```php
<?php
/**
 * Frontend - [Nombre de la Página]
 * [Descripción]
 */

// Security check
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Verificar modo mantenimiento
if (is_maintenance_mode()) {
    require_once APP_PATH . '/pages/frontend/maintenance.php';
    exit;
}

// Cargar configuraciones
$site_config = read_json(APP_PATH . '/config/site.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');
$active_theme = $theme_config['active_theme'] ?? 'minimal';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Estilos inline con nonce -->
    <style nonce="<?= csp_nonce() ?>">
        /* Estilos específicos de esta página */
    </style>
</head>
<body>
    <!-- Header -->
    <?php include APP_PATH . '/includes/header-frontend.php'; ?>

    <!-- Contenido principal -->
    <main class="container">
        <h1>Mi Página</h1>
        <!-- Contenido aquí -->
    </main>

    <!-- JavaScript con nonce -->
    <script nonce="<?= csp_nonce() ?>">
        function myFunction(event, element, params) {
            // Lógica aquí
        }

        window.myFunction = myFunction;
    </script>

    <!-- Event Handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>

    <!-- Footer -->
    <?php include APP_PATH . '/includes/footer-frontend.php'; ?>
</body>
</html>
```

### 2. Página Admin

```php
<?php
/**
 * Admin - [Nombre de la Página]
 * [Descripción]
 */

// Security check
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require auth
require_admin();

// Procesar formulario
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Procesar datos
        $message = 'Datos guardados exitosamente';
    }
}

// Cargar datos
$data = read_json(APP_PATH . '/data/file.json');
$csrf_token = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin - Página</title>

    <!-- Estilos -->
    <?php include APP_PATH . '/includes/admin/styles.php'; ?>

    <style nonce="<?= csp_nonce() ?>">
        /* Estilos específicos */
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Título</h2>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <!-- Campos del formulario -->

                <button type="submit" class="btn-primary">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- JavaScript -->
    <script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
</body>
</html>
```

### 3. Registrar Ruta

**Frontend** (`public_html/index.php`):
```php
$router->get('/mi-pagina', APP_PATH . '/pages/frontend/mi-pagina.php');
```

**Admin** (`public_html/admin/index.php`):
```php
$pages_map = [
    // ...
    'mi-pagina' => APP_PATH . '/pages/admin/mi-pagina.php',
];
```

---

## Trabajar con Componentes

### Crear Componente

1. **Crear archivo** en `app/includes/frontend/mi-componente.php`:

```php
<?php
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

function render_mi_componente($data = [], $options = []) {
    $title = htmlspecialchars($data['title'] ?? '');
    $show_button = $options['show_button'] ?? true;

    ?>
    <div class="mi-componente">
        <h3><?= $title ?></h3>

        <?php if ($show_button): ?>
        <button data-action="doSomething">Hacer algo</button>
        <?php endif; ?>
    </div>
    <?php
}
?>
```

2. **Usar componente** en página:

```php
<?php
require_once APP_PATH . '/includes/frontend/mi-componente.php';

render_mi_componente([
    'title' => 'Mi Título'
], [
    'show_button' => true
]);
?>
```

### Componentes con CSS

1. **Agregar CSS** a `public_html/assets/themes/_base/components.css`:

```css
.mi-componente {
    border: 1px solid var(--border-color);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
}

.mi-componente h3 {
    margin-bottom: var(--spacing-sm);
}
```

2. **El CSS se carga automáticamente** con `render_theme_css()`

---

## JavaScript y CSP

### Template de Función

```html
<button data-action="myFunction" data-param="value">Click</button>

<script nonce="<?= csp_nonce() ?>">
    function myFunction(event, element, params) {
        // event: Evento DOM
        // element: Elemento que disparó el evento
        // params: Objeto con data-* attributes

        console.log(params.param); // "value"

        // Lógica aquí
    }

    // IMPORTANTE: Exportar
    window.myFunction = myFunction;
</script>

<!-- IMPORTANTE: Incluir event-handlers.js -->
<script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
```

### Validación

```javascript
function processForm(event, element, params) {
    event.preventDefault();

    // Validar campos
    const name = element.name.value.trim();
    if (!name) {
        ShopUtils.showToast('El nombre es requerido', 'error');
        return;
    }

    // Procesar
    // ...
}
```

### AJAX con Fetch

```javascript
function saveData(event, element, params) {
    const data = { name: 'Juan', age: 30 };

    fetch('<?= url('/api/save.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= generate_csrf_token() ?>'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(result => {
        if (result.success) {
            ShopUtils.showToast('Guardado exitosamente', 'success');
        } else {
            ShopUtils.showToast('Error: ' + result.error, 'error');
        }
    })
    .catch(err => {
        ShopUtils.showToast('Error de conexión', 'error');
    });
}
```

---

## Debugging

### PHP Debugging

#### Error Log

```php
// Escribir al error log de PHP
error_log('Debug: $variable = ' . print_r($variable, true));

// Ver error log
tail -f /var/log/php/error.log
```

#### Var Dump

```php
// Solo en desarrollo
if (strpos(APP_PATH, '/home/pablo/shop-v2') !== false) {
    echo '<pre>';
    var_dump($variable);
    echo '</pre>';
}
```

### JavaScript Debugging

```javascript
// Console logs
console.log('Debug:', variable);
console.table(array);
console.error('Error:', error);

// Breakpoints en DevTools
debugger;

// Network panel para AJAX
fetch(url).then(res => {
    console.log('Response:', res);
    return res.json();
});
```

### MySQL Query Log (si se migra a SQL)

```php
// Log de queries
function log_query($query) {
    $log_file = APP_PATH . '/data/query_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $query\n", FILE_APPEND);
}
```

---

## Testing

### Manual Testing Checklist

Antes de hacer commit:

- [ ] Página se carga sin errores 500
- [ ] Console de JavaScript sin errores
- [ ] Formularios envían correctamente
- [ ] CSRF tokens funcionan
- [ ] Sanitización de output (sin XSS)
- [ ] Responsive (mobile, tablet, desktop)
- [ ] Navegación funciona
- [ ] Botones de favoritos/carrito funcionan
- [ ] Logout funciona (admin)

### Testing en Producción

Después del deploy:

```bash
# 1. Verificar deploy exitoso
curl -I https://peu.net/shopv2/

# 2. Probar página específica
curl https://peu.net/shopv2/producto/ejemplo

# 3. Verificar JSON válido (API)
curl https://peu.net/shopv2/api/products.php | jq .

# 4. Verificar logs
ssh usuario@peu.net
tail -f ~/shop-v2-app/data/error_log.txt
```

---

## Deploy

### Proceso Automático

1. **Commit y push a main**:
   ```bash
   git add .
   git commit -m "feat: nueva funcionalidad"
   git push origin main
   ```

2. **GitHub Actions se activa automáticamente**

3. **Verificar deploy**:
   - Ver logs en: https://github.com/pablopeu/shop-v2/actions
   - Esperar ✅ verde
   - Testear en: https://peu.net/shopv2

### Rollback

Si el deploy falla:

```bash
# Ver últimos commits
git log --oneline -5

# Revertir al commit anterior
git revert HEAD
git push origin main  # Dispara nuevo deploy con rollback
```

---

## Troubleshooting

### Error: "Direct access not permitted"

**Causa**: Archivo en `app/` accedido directamente

**Solución**: Asegurar que `APP_ENTRY_POINT` está definido en entry point

### Error: "CSRF token invalid"

**Causa**: Token no coincide o expiró

**Solución**:
```php
// Regenerar token
$csrf_token = generate_csrf_token();

// Verificar sesión activa
session_start();
```

### Error: "Call to undefined function"

**Causa**: Función no cargada

**Solución**: Verificar que el include está en `bootstrap.php` o en la página

### Error: CSP blocking inline script

**Causa**: Script sin nonce

**Solución**:
```html
<!-- ❌ -->
<script>console.log('test');</script>

<!-- ✅ -->
<script nonce="<?= csp_nonce() ?>">console.log('test');</script>
```

### Error: Function not found (event-handlers.js)

**Causa**: Función no exportada a `window`

**Solución**:
```javascript
function myFunction() { }
window.myFunction = myFunction;  // ← Agregar esto
```

---

*Última actualización: 2025-12-08*
