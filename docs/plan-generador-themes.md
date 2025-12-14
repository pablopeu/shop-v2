# Plan: Generador de Themes para Shop V2

## Resumen Ejecutivo

Crear un **Generador de Themes** visual en el admin panel que permite:

1. ✅ **Importar paletas desde ColorHunt** con mapeo inteligente (contraste WCAG)
2. ✅ **Clonar themes existentes** para modificación rápida
3. ✅ **Personalizar componentes** (cards, buttons, galería)
4. ✅ **Generar automáticamente** archivos CSS (theme.json, variables.css, theme.css)

---

## Arquitectura de Archivos

### Nuevos Archivos

```
/app/includes/theme-generator.php          # Funciones auxiliares (~1000 líneas)
/app/pages/admin/generador-themes.php      # Interfaz + AJAX endpoints (~800 líneas)
/public_html/assets/js/theme-generator.js  # JavaScript (~400 líneas)
/public_html/assets/libs/vanilla-picker/   # Color picker library (MIT)
  ├── vanilla-picker.min.js
  ├── vanilla-picker.min.css
  └── LICENSE
```

### Modificaciones

```
/app/includes/admin/sidebar.php            # +12 líneas (nueva entrada menú)
/public_html/admin/index.php               # +1 línea (mapeo ruta)
```

---

## Implementación por Fases

### FASE 1: MVP - Generador Básico (9-13 horas) ⭐ PRIORIDAD ALTA

**Objetivo:** Generador funcional que crea themes nuevos desde cero.

#### 1.1 Funciones Auxiliares Básicas (2-3h)

**Archivo:** `/app/includes/theme-generator.php`

Implementar:

- `calculate_luminance($hex)` - Calcula luminosidad relativa (0.0-1.0)
  - Fórmula WCAG: `0.2126*R + 0.7152*G + 0.0722*B`

- `calculate_contrast_ratio($color1, $color2)` - Calcula ratio de contraste (1.0-21.0)
  - Fórmula: `(L_lighter + 0.05) / (L_darker + 0.05)`

- `validate_wcag_contrast($fg, $bg)` - Valida contraste >= 4.5:1 (WCAG AA)

- `map_colors_intelligently($colors_array)` - Mapea 4 colores a roles
  - Ordenar por luminosidad (oscuro → claro)
  - Asignar: primary (más oscuro), background (más claro), secondary, accent
  - **CRÍTICO:** Validar contraste primary vs background >= 4.5:1
  - Si contraste insuficiente: ajustar primary a #000000 o #ffffff según bg

- `generate_color_variants($hex)` - Genera dark, light, rgb
  - dark: reducir RGB en 20%
  - light: incrementar hacia blanco en 30%
  - rgb: formato "R, G, B" para rgba()

#### 1.2 Generadores de CSS (2-3h)

**Mismo archivo:** `/app/includes/theme-generator.php`

- `generate_variables_css($config)` - Genera ~150 variables CSS
  - Colores: primary, secondary, accent, success, warning, error, text, background
  - Derivados: -dark, -light, -rgb para cada color
  - Tipografía: font-family, sizes (xs a 4xl), weights, line-heights
  - Espaciado: escala de 6px a 64px
  - Bordes: radius según componente rounded (0px a 16px)
  - Sombras: según shadow_style (none/subtle/medium/deep)
  - Transiciones, layout, z-index

- `generate_theme_css_basic($config)` - Genera CSS básico (~200 líneas)
  - Global: body, headings, links
  - Product cards: border, shadow, rounded, hover_effect
  - Buttons: style (solid/outline/gradient), rounded, shadow
  - Header, Hero básicos

#### 1.3 Validación y Generación (1-2h)

**Mismo archivo:** `/app/includes/theme-generator.php`

- `validate_theme_input($slug, $name, $colors)` - Validaciones
  - Slug: no vacío, solo a-z0-9-, no duplicado
  - Nombre: no vacío
  - Colores: formato #RRGGBB válido
  - Contraste: warning si < 4.5:1 (no bloquea)

- `generate_theme($data)` - Función principal
  1. Crear directorio `/public_html/assets/themes/{slug}/`
  2. Generar `theme.json` con metadata
  3. Generar `variables.css`
  4. Generar `theme.css`
  5. `chmod 0644` archivos, `0755` directorio
  6. Retornar `['success' => true, 'slug' => ...]`

#### 1.4 Interfaz Básica (3-4h)

**Archivo:** `/app/pages/admin/generador-themes.php`

Estructura:
```php
<?php
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

require_admin();
require_once APP_PATH . '/includes/theme-generator.php';

// Procesamiento POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_theme'])) {
    // Validar CSRF
    // Sanitizar datos
    // Validar con validate_theme_input()
    // Generar con generate_theme()
    // Redirect a config-themes con mensaje
}

// Cargar themes disponibles para clonar
$available_themes = get_available_themes();
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Generador de Themes - Admin</title>
    <?php include APP_PATH . '/includes/admin/admin-common-styles.php'; ?>
    <style nonce="<?= csp_nonce() ?>">
        /* Estilos del formulario */
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <!-- Info Box -->
        <div class="info-box">
            ℹ️ Crea themes personalizados para tu tienda...
        </div>

        <!-- Formulario -->
        <form method="POST" action="" id="theme-generator-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <!-- Método de Creación -->
            <section class="form-section">
                <h2>Método de Creación</h2>
                <label>
                    <input type="radio" name="creation_method" value="new" checked>
                    Nuevo desde cero
                </label>
                <!-- Más opciones en Fase 2 y 3 -->
            </section>

            <!-- Información Básica -->
            <section class="form-section">
                <h2>Información Básica</h2>
                <div class="form-group">
                    <label>Nombre del Theme *</label>
                    <input type="text" name="name" id="theme-name" required
                           data-onchange="generateSlug">
                </div>
                <div class="form-group">
                    <label>Slug *</label>
                    <input type="text" name="slug" id="theme-slug" required
                           pattern="[a-z0-9-]+" placeholder="mi-theme">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description"></textarea>
                </div>
            </section>

            <!-- Colores -->
            <section class="form-section">
                <h2>Colores</h2>
                <div class="color-grid">
                    <div class="form-group">
                        <label>Primary *</label>
                        <input type="color" name="color_primary" value="#000000">
                    </div>
                    <div class="form-group">
                        <label>Secondary *</label>
                        <input type="color" name="color_secondary" value="#d4af37">
                    </div>
                    <!-- Más colores... -->
                </div>
            </section>

            <!-- Componentes: Cards -->
            <section class="form-section">
                <h2>Componentes: Cards</h2>
                <div class="form-group">
                    <label>Sombreado</label>
                    <label><input type="radio" name="card_shadow" value="none"> None</label>
                    <label><input type="radio" name="card_shadow" value="subtle" checked> Subtle</label>
                    <label><input type="radio" name="card_shadow" value="medium"> Medium</label>
                    <label><input type="radio" name="card_shadow" value="deep"> Deep</label>
                </div>
                <!-- Más opciones... -->
            </section>

            <button type="submit" name="generate_theme" class="btn-primary">
                💾 Generar Theme
            </button>
        </form>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        function generateSlug(event, element, params) {
            const name = element.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('theme-slug').value = slug;
        }
        window.generateSlug = generateSlug;
    </script>

    <?php include APP_PATH . '/includes/admin/modal.php'; ?>
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
```

**Usar `<input type="color">` nativo en MVP** (sin Vanilla Picker aún).

#### 1.5 Integración Sidebar (30min)

**Archivo:** `/app/includes/admin/sidebar.php`

Modificar línea 417 (array de páginas del submenu):
```php
<ul class="submenu <?php echo in_array($current_page, ['config-themes', 'generador-themes', 'config-hero', 'config-carrusel', 'config-footer']) ? 'open' : ''; ?>"
```

Agregar después de línea 423 (dentro submenu Apariencia):
```php
<li>
    <a href="<?php echo url('/admin/?page=generador-themes'); ?>"
       class="<?php echo $current_page === 'generador-themes' ? 'active' : ''; ?>">
        ✨ Generador de Themes
    </a>
</li>
```

**Archivo:** `/public_html/admin/index.php`

Agregar en `$pages_map`:
```php
'generador-themes' => 'generador-themes.php',
```

---

### FASE 2: Mapeo Inteligente de Paletas (1.5 horas) ⭐ PRIORIDAD MEDIA-ALTA

**Objetivo:** Permitir que el usuario pegue 4 colores de ColorHunt y el sistema los mapee inteligentemente.

#### 2.1 Endpoint AJAX (45min)

**Archivo:** `/app/pages/admin/generador-themes.php`

Agregar ANTES del HTML:
```php
// AJAX: Mapear Paleta Inteligentemente
if (isset($_GET['action']) && $_GET['action'] === 'map_palette') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents('php://input'), true);
    $colors_array = $json_input['colors'] ?? [];

    // Validar que sean 4 colores válidos
    if (count($colors_array) !== 4) {
        echo json_encode(['success' => false, 'message' => 'Debes proporcionar exactamente 4 colores']);
        exit;
    }

    // Validar formato hex
    foreach ($colors_array as $color) {
        if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            echo json_encode(['success' => false, 'message' => 'Formato de color inválido: ' . $color]);
            exit;
        }
    }

    $mapped = map_colors_intelligently($colors_array);

    echo json_encode([
        'success' => true,
        'colors' => $mapped,
        'raw_colors' => $colors_array
    ]);
    exit;
}
```

#### 2.2 UI para Paleta (45min)

**Mismo archivo:** Agregar al formulario después de "Método de Creación":

```html
<label>
    <input type="radio" name="creation_method" value="palette">
    Usar paleta de ColorHunt
</label>

<div id="palette-import" style="display: none;">
    <div class="info-box">
        ℹ️ Ve a <a href="https://colorhunt.co" target="_blank">ColorHunt.co</a>,
        elige una paleta que te guste y copia los 4 colores aquí.
    </div>

    <div class="palette-inputs">
        <div class="form-group">
            <label>Color 1</label>
            <input type="text" id="palette-color-1" placeholder="#213448" pattern="^#[a-fA-F0-9]{6}$">
        </div>
        <div class="form-group">
            <label>Color 2</label>
            <input type="text" id="palette-color-2" placeholder="#547792" pattern="^#[a-fA-F0-9]{6}$">
        </div>
        <div class="form-group">
            <label>Color 3</label>
            <input type="text" id="palette-color-3" placeholder="#94b4c1" pattern="^#[a-fA-F0-9]{6}$">
        </div>
        <div class="form-group">
            <label>Color 4</label>
            <input type="text" id="palette-color-4" placeholder="#eae0cf" pattern="^#[a-fA-F0-9]{6}$">
        </div>
    </div>

    <button type="button" data-action="mapPalette" class="btn-secondary">
        🎨 Aplicar Mapeo Inteligente
    </button>
</div>
```

**JavaScript:** Agregar a `/public_html/assets/js/theme-generator.js`:

```javascript
function mapPalette(event, element, params) {
    event.preventDefault();

    // Obtener los 4 colores
    const colors = [
        document.getElementById('palette-color-1').value.trim(),
        document.getElementById('palette-color-2').value.trim(),
        document.getElementById('palette-color-3').value.trim(),
        document.getElementById('palette-color-4').value.trim()
    ];

    // Validar que no estén vacíos
    if (colors.some(c => !c)) {
        showToast('Por favor completa los 4 colores', 'error');
        return;
    }

    // Validar formato hex
    const hexPattern = /^#[a-fA-F0-9]{6}$/;
    if (!colors.every(c => hexPattern.test(c))) {
        showToast('Formato inválido. Usa #RRGGBB (ej: #213448)', 'error');
        return;
    }

    fetch(urlBase + '/admin/?page=generador-themes&action=map_palette', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ colors: colors })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Poblar inputs de color
            document.querySelector('[name="color_primary"]').value = data.colors.primary;
            document.querySelector('[name="color_secondary"]').value = data.colors.secondary;
            document.querySelector('[name="color_accent"]').value = data.colors.accent;
            document.querySelector('[name="color_background"]').value = data.colors.background;
            document.querySelector('[name="color_text"]').value = data.colors.text;

            // Si hay color pickers, actualizarlos
            if (window.updateColorPickers) {
                updateColorPickers(data.colors);
            }

            showToast('Paleta mapeada exitosamente', 'success');
        } else {
            showToast(data.message, 'error');
        }
    });
}

window.mapPalette = mapPalette;
```

---

### FASE 3: Clonar Theme (2 horas) ⭐ PRIORIDAD MEDIA

**Objetivo:** Clonar themes existentes para modificación rápida.

#### 3.1 Función Clonado (1h)

**Archivo:** `/app/includes/theme-generator.php`

```php
function clone_theme($source_slug, $new_slug, $new_name) {
    $source_dir = PUBLIC_PATH . "/assets/themes/{$source_slug}";
    $dest_dir = PUBLIC_PATH . "/assets/themes/{$new_slug}";

    // Validar source existe
    if (!is_dir($source_dir)) {
        return ['success' => false, 'message' => 'Theme origen no existe'];
    }

    // Validar destino NO existe
    if (is_dir($dest_dir)) {
        return ['success' => false, 'message' => 'Ya existe un theme con ese slug'];
    }

    // Crear directorio destino
    mkdir($dest_dir, 0755, true);

    // Copiar archivos
    $files = ['theme.json', 'variables.css', 'theme.css'];
    foreach ($files as $file) {
        if (file_exists($source_dir . '/' . $file)) {
            copy($source_dir . '/' . $file, $dest_dir . '/' . $file);
        }
    }

    // Actualizar theme.json con nuevo slug y nombre
    $theme_config = read_json($dest_dir . '/theme.json');
    $theme_config['slug'] = $new_slug;
    $theme_config['name'] = $new_name;
    $theme_config['created_at'] = date('Y-m-d');
    $theme_config['updated_at'] = date('Y-m-d');
    write_json($dest_dir . '/theme.json', $theme_config);

    // chmod
    chmod($dest_dir, 0755);
    foreach ($files as $file) {
        if (file_exists($dest_dir . '/' . $file)) {
            chmod($dest_dir . '/' . $file, 0644);
        }
    }

    return ['success' => true, 'slug' => $new_slug];
}
```

#### 3.2 UI Clonar (1h)

**Archivo:** `/app/pages/admin/generador-themes.php`

Agregar endpoint AJAX:
```php
// AJAX: Get Theme Config
if (isset($_GET['action']) && $_GET['action'] === 'get_theme_config') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents('php://input'), true);
    $slug = $json_input['slug'] ?? '';

    $config = get_theme_config($slug);
    if (!$config) {
        echo json_encode(['success' => false, 'message' => 'Theme no encontrado']);
        exit;
    }

    echo json_encode(['success' => true, 'config' => $config]);
    exit;
}
```

Agregar al formulario:
```html
<label>
    <input type="radio" name="creation_method" value="clone">
    Clonar theme existente
</label>

<div id="clone-theme" style="display: none;">
    <div class="form-group">
        <label>Theme a clonar</label>
        <select id="clone-theme-select">
            <?php foreach ($available_themes as $slug => $theme): ?>
                <option value="<?php echo $slug; ?>">
                    <?php echo htmlspecialchars($theme['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" data-action="cloneTheme" class="btn-secondary">
            📋 Cargar Theme
        </button>
    </div>
</div>
```

JavaScript para poblar formulario desde config.

---

### FASE 4: Color Picker Avanzado (2.5 horas) ⭐ PRIORIDAD MEDIA

**Objetivo:** Mejorar UX con color pickers visuales y validación WCAG en tiempo real.

#### 4.1 Descargar Vanilla Picker (30min)

1. Ir a https://github.com/Sphinxxxx/vanilla-picker/releases
2. Descargar latest release
3. Crear directorio `/public_html/assets/libs/vanilla-picker/`
4. Copiar:
   - `vanilla-picker.min.js`
   - `vanilla-picker.min.css`
   - `LICENSE` (MIT)

#### 4.2 Integración (1h)

**Archivo:** `/app/pages/admin/generador-themes.php`

En `<head>`:
```html
<link rel="stylesheet" href="<?php echo url('/assets/libs/vanilla-picker/vanilla-picker.min.css'); ?>">
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/libs/vanilla-picker/vanilla-picker.min.js'); ?>"></script>
```

Cambiar inputs de `type="color"` a `type="text"`:
```html
<input type="text" name="color_primary" id="color-primary" value="#000000">
```

#### 4.3 JavaScript Picker (1h)

**Archivo:** `/public_html/assets/js/theme-generator.js`

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = ['color-primary', 'color-secondary', 'color-accent', 'color-text', 'color-background'];

    colorInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (!input) return;

        const parent = input.parentElement;
        const pickerBtn = document.createElement('button');
        pickerBtn.type = 'button';
        pickerBtn.className = 'color-picker-btn';
        pickerBtn.textContent = '🎨 Elegir';
        parent.appendChild(pickerBtn);

        const picker = new Picker({
            parent: pickerBtn,
            color: input.value,
            onChange: function(color) {
                input.value = color.hex;

                // Actualizar contraste si es primary o background
                if (inputId === 'color-primary' || inputId === 'color-background') {
                    updateContrast();
                }
            }
        });
    });
});
```

#### 4.4 Indicador de Contraste (1h)

**Endpoint AJAX en generador-themes.php:**
```php
// AJAX: Calculate Contrast
if (isset($_GET['action']) && $_GET['action'] === 'calculate_contrast') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents('php://input'), true);
    $fg = $json_input['foreground'] ?? '';
    $bg = $json_input['background'] ?? '';

    $ratio = calculate_contrast_ratio($fg, $bg);
    $level = $ratio >= 7.0 ? 'AAA' : ($ratio >= 4.5 ? 'AA' : 'Fail');

    echo json_encode([
        'ratio' => round($ratio, 1),
        'level' => $level,
        'passes_aa' => $ratio >= 4.5
    ]);
    exit;
}
```

**UI:** Agregar después de color inputs:
```html
<div id="contrast-indicator" class="contrast-indicator"></div>
```

**JavaScript:**
```javascript
function updateContrast() {
    const fg = document.getElementById('color-primary').value;
    const bg = document.getElementById('color-background').value;

    fetch(urlBase + '/admin/?page=generador-themes&action=calculate_contrast', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({foreground: fg, background: bg})
    })
    .then(res => res.json())
    .then(data => {
        const indicator = document.getElementById('contrast-indicator');

        if (data.level === 'AAA') {
            indicator.innerHTML = `⚠ Contraste: ${data.ratio}:1 (WCAG AAA ✓)`;
            indicator.className = 'contrast-indicator success';
        } else if (data.level === 'AA') {
            indicator.innerHTML = `⚠ Contraste: ${data.ratio}:1 (WCAG AA ✓)`;
            indicator.className = 'contrast-indicator warning';
        } else {
            indicator.innerHTML = `⚠ Contraste: ${data.ratio}:1 (Insuficiente ✗)`;
            indicator.className = 'contrast-indicator error';
        }
    });
}
```

---

## Estructura theme.json Generado

```json
{
    "name": "Mi Theme",
    "slug": "mi-theme",
    "version": "1.0.0",
    "description": "Theme personalizado",
    "author": "Shop Team",
    "preview_image": "/themes/mi-theme/preview.jpg",

    "features": {
        "dark_mode": false,
        "animations": "smooth",
        "border_style": "minimal",
        "shadow_style": "subtle",
        "color_scheme": "custom"
    },

    "colors": {
        "primary": "#000000",
        "primary_dark": "#1a1a1a",
        "primary_light": "#333333",
        "secondary": "#d4af37",
        "accent": "#4facfe",
        "success": "#2e7d32",
        "warning": "#f57c00",
        "error": "#c62828",
        "info": "#1565c0",
        "text": "#1a1a1a",
        "background": "#ffffff"
    },

    "typography": {
        "font_family": "sans-serif",
        "font_family_heading": "sans-serif",
        "heading_weight": "600",
        "base_size": "16px",
        "line_height": "1.5"
    },

    "spacing": {
        "base_unit": "12px",
        "scale": "proportional"
    },

    "components": {
        "buttons": {
            "style": "outline",
            "rounded": false,
            "shadow": false
        },
        "cards": {
            "border": true,
            "shadow": "subtle",
            "rounded": false,
            "hover_effect": "glow"
        },
        "forms": {
            "style": "modern",
            "border_style": "solid",
            "focus_ring": true
        }
    },

    "layout": {
        "container_width": "1200px",
        "grid_gap": "28px",
        "sidebar_width": "300px"
    },

    "footer": {
        "background_color": "#292c2f",
        "text_color": "#ffffff"
    },

    "compatibility": {
        "requires_php": "7.4",
        "requires_css": "3",
        "mobile_optimized": true,
        "rtl_support": false,
        "accessibility": "wcag-aa"
    },

    "tags": ["custom", "generado"],
    "created_at": "2025-12-14",
    "updated_at": "2025-12-14"
}
```

---

## Algoritmo de Mapeo Inteligente de Paletas

**Input:** Array de 4 colores hex ingresados manualmente por el usuario `['#213448', '#547792', '#94b4c1', '#eae0cf']`

**Proceso:**

1. Calcular luminosidad de cada color (0.0-1.0)
2. Ordenar de oscuro → claro
3. Asignación inicial:
   - `primary` = más oscuro
   - `background` = más claro
   - `secondary` = segundo más oscuro
   - `accent` = segundo más claro
4. Validar contraste primary vs background >= 4.5:1
5. Si contraste insuficiente:
   - Si background claro (lum > 0.5): forzar primary a #000000
   - Si background oscuro (lum <= 0.5): forzar primary a #ffffff
6. Determinar `text`:
   - Si background claro: text oscuro (#1a1a1a)
   - Si background oscuro: text claro (#f5f5f5)

**Output:**
```php
[
    'primary' => '#213448',
    'secondary' => '#547792',
    'accent' => '#94b4c1',
    'background' => '#eae0cf',
    'text' => '#1a1a1a'
]
```

---

## Testing

### Test Manual - Generador Básico

1. Acceder a `/admin/?page=generador-themes`
2. Llenar formulario:
   - Nombre: "Test Theme"
   - Slug: "test-theme" (auto-generado)
   - Colores con color pickers
3. Click "Generar Theme"
4. Verificar:
   - Directorio `/public_html/assets/themes/test-theme/` creado
   - Archivos: `theme.json`, `variables.css`, `theme.css`
   - Permisos: 0755 dir, 0644 files
5. Ir a `/admin/?page=config-themes`
6. Activar "Test Theme"
7. Ver frontend y verificar nuevos colores aplicados

### Test ColorHunt

1. Ir a https://colorhunt.co/palettes/popular
2. Copiar URL de paleta (ej: `https://colorhunt.co/palette/21344854779294b4c1eae0cf`)
3. En generador: seleccionar "Importar ColorHunt"
4. Pegar URL, click "Importar Paleta"
5. Verificar:
   - 4 colores poblados en inputs
   - Indicador de contraste muestra ratio
   - Si ratio < 4.5:1, mostrar warning

### Test Clonar

1. Seleccionar "Clonar theme existente"
2. Elegir "Classic" del dropdown
3. Click "Cargar Theme"
4. Verificar:
   - Todos los campos se poblan con valores de Classic
5. Cambiar nombre a "Classic Clone"
6. Generar
7. Verificar archivos clonados y modificados

---

## Orden de Ejecución Recomendado

### Semana 1: MVP
1. Día 1-2: Funciones auxiliares + generadores CSS
2. Día 3: Validación y función principal
3. Día 4-5: Interfaz básica
4. Día 5: Integración sidebar + testing

### Semana 2: Features Avanzadas
1. Día 1: Mapeo inteligente de paletas + Clonar theme
2. Día 2-3: Color picker avanzado + contraste
3. Día 4-5: Testing completo y fixes

---

## Archivos Críticos

**Prioridad 1 (core):**
- `/app/includes/theme-generator.php` (~1000 líneas)
- `/app/pages/admin/generador-themes.php` (~800 líneas)
- `/public_html/assets/js/theme-generator.js` (~400 líneas)

**Prioridad 2 (integración):**
- `/app/includes/admin/sidebar.php` (modificar)
- `/public_html/admin/index.php` (modificar)

**Prioridad 3 (UX):**
- `/public_html/assets/libs/vanilla-picker/` (color picker)

---

## Mejoras Post-MVP (Opcional)

Estas features pueden agregarse después del MVP si hay tiempo:

1. **Personalización Vista Producto** (2-3h)
   - Layout galería (thumbnails abajo/al lado)
   - Tamaño imagen principal
   - Secciones visibles (breadcrumb, share, etc.)

2. **Preview en Tiempo Real** (2h)
   - Iframe o ventana nueva mostrando theme
   - Actualización dinámica al cambiar colores

3. **Export/Import theme.json** (2h)
   - Descargar theme.json
   - Importar desde archivo JSON

4. **Presets de Paletas** (1h)
   - Biblioteca de 10-15 paletas predefinidas
   - 1-click para aplicar

5. **Duplicar Theme** (1h)
   - Desde config-themes.php
   - Botón "Duplicar" junto a "Activar"

---

## Total Estimado

- **Fase 1 (MVP):** 9-13 horas
- **Fase 2 (Mapeo Paletas):** 1.5 horas
- **Fase 3 (Clonar):** 2 horas
- **Fase 4 (Picker):** 2.5 horas

**Total core:** 15-19 horas

**Post-MVP opcional:** +8 horas

**Gran total:** 23-27 horas
