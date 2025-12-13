# Manual de Creación de Themes - Shop V2

## 📚 Índice

1. [Introducción](#introducción)
2. [Guía Rápida: Crear tu Primer Theme](#guía-rápida-crear-tu-primer-theme)
3. [Arquitectura del Sistema](#arquitectura-del-sistema)
4. [Variables CSS: Referencia Completa](#variables-css-referencia-completa)
5. [Estructura de Archivos](#estructura-de-archivos)
6. [Guía Paso a Paso](#guía-paso-a-paso)
7. [Ejemplos Prácticos](#ejemplos-prácticos)
8. [Sistema de Caché](#sistema-de-caché)
9. [Troubleshooting](#troubleshooting)
10. [Mejores Prácticas](#mejores-prácticas)

---

## Introducción

El sistema de themes de Shop V2 te permite crear apariencias visuales completamente personalizadas para tu tienda online sin modificar código PHP. Todo se maneja mediante **CSS Variables** (Custom Properties), lo que hace que los themes sean:

- ✅ **Fáciles de crear**: Solo necesitas CSS
- ✅ **Mantenibles**: Cambios centralizados en variables
- ✅ **Reutilizables**: Un theme funciona en toda la aplicación
- ✅ **Performantes**: Sistema de caché automático

### ¿Qué puedes personalizar?

- 🎨 Colores (primarios, secundarios, estados, fondos, textos)
- 📝 Tipografía (fuentes, tamaños, pesos, interlineado)
- 📏 Espaciado (márgenes, padding, gaps)
- 🔲 Bordes (radios, grosores, estilos)
- 💫 Sombras (tamaños, intensidades)
- ⚡ Transiciones (velocidades, efectos)
- 📐 Layout (anchos, alturas, breakpoints)

---

## Guía Rápida: Crear tu Primer Theme

**Tiempo estimado:** 10 minutos

### Paso 1: Crear la carpeta del theme

```bash
cd /home/pablo/shop-v2
mkdir -p public_html/assets/themes/mi-theme
```

### Paso 2: Crear theme.json

```bash
cat > public_html/assets/themes/mi-theme/theme.json << 'EOF'
{
    "name": "Mi Theme",
    "slug": "mi-theme",
    "version": "1.0.0",
    "author": "Tu Nombre",
    "description": "Mi primer theme personalizado",
    "supports": {
        "dark_mode": false,
        "custom_colors": true
    }
}
EOF
```

### Paso 3: Crear variables.css

```bash
cat > public_html/assets/themes/mi-theme/variables.css << 'EOF'
:root {
    /* Colores Principales */
    --color-primary: #ff6b6b;
    --color-primary-dark: #ee5a52;
    --color-secondary: #4ecdc4;

    /* Tipografía */
    --font-family: 'Arial', sans-serif;
    --font-size-base: 16px;

    /* Espaciado */
    --spacing-md: 1rem;

    /* Bordes */
    --border-radius-md: 8px;
}
EOF
```

### Paso 4: Crear theme.css (opcional)

```bash
cat > public_html/assets/themes/mi-theme/theme.css << 'EOF'
/* Estilos específicos de Mi Theme */

.btn-primary {
    background: var(--color-primary);
    border-radius: var(--border-radius-md);
}
EOF
```

### Paso 5: Activar el theme

Edita `/app/config/theme.json`:

```json
{
    "active_theme": "mi-theme"
}
```

¡Listo! Tu theme ya está activo. Refresca el navegador para verlo.

---

## Arquitectura del Sistema

### Estructura de Directorios

```
shop-v2/
├── app/
│   └── config/
│       └── theme.json              # Configuración del theme activo
│
└── public_html/
    └── assets/
        └── themes/
            ├── _base/               # CSS base (NO modificar)
            │   ├── reset.css        # Reset de navegador
            │   ├── layout.css       # Sistema de layout
            │   ├── components.css   # Componentes base
            │   ├── utilities.css    # Utilidades
            │   ├── pages.css        # Estilos globales
            │   └── pages/           # CSS por página
            │       ├── home.css
            │       ├── producto.css
            │       └── ...
            │
            ├── mi-theme/            # TU THEME (personalizable)
            │   ├── theme.json       # Metadata
            │   ├── variables.css    # Variables CSS (OBLIGATORIO)
            │   ├── theme.css        # Estilos propios (opcional)
            │   └── preview.png      # Vista previa (opcional)
            │
            ├── classic/             # Theme ejemplo
            ├── foto/                # Theme ejemplo
            └── archivo/             # Themes archivados
```

### Orden de Carga CSS

El sistema carga CSS en cascada (cada capa sobrescribe la anterior):

```
1. Font Awesome (CDN)
   ↓
2. Base CSS (_base/)
   - reset.css
   - layout.css
   - components.css
   - utilities.css
   - pages.css
   ↓
3. Theme Variables (tu-theme/variables.css)
   ↓
4. Theme Styles (tu-theme/theme.css)
   ↓
5. Page CSS (_base/pages/{página}.css)
```

**Esto significa:**
- Variables de tu theme sobrescriben valores por defecto
- Estilos de `theme.css` sobrescriben estilos base
- No necesitas `!important` (usa especificidad correcta)

---

## Variables CSS: Referencia Completa

Esta es la lista completa de variables CSS que puedes personalizar en tu theme. **Todas son opcionales** pero se recomienda definir al menos las principales.

### 🎨 Colores Principales

```css
:root {
    /* Color Primario (botones, links, acentos) */
    --color-primary: #667eea;
    --color-primary-dark: #5568d3;
    --color-primary-light: #818cf8;
    --color-primary-rgb: 102, 126, 234;

    /* Color Secundario (elementos de apoyo) */
    --color-secondary: #6c757d;
    --color-secondary-dark: #5a6268;
    --color-secondary-light: #adb5bd;
    --color-secondary-rgb: 108, 117, 125;
}
```

### ✅ Colores de Estado

```css
:root {
    /* Success (acciones exitosas, confirmaciones) */
    --color-success: #4CAF50;
    --color-success-dark: #45a049;
    --color-success-light: #66bb6a;
    --color-success-bg: #e8f5e9;

    /* Warning (advertencias, pendientes) */
    --color-warning: #FF9800;
    --color-warning-dark: #f57c00;
    --color-warning-light: #ffa726;
    --color-warning-bg: #fff3e0;
    --color-warning-text: #856404;

    /* Error (errores, rechazos, cancelaciones) */
    --color-error: #f44336;
    --color-error-dark: #e53935;
    --color-error-light: #ef5350;
    --color-error-bg: #ffebee;
    --color-error-text: #721c24;

    /* Info (información, estados confirmados) */
    --color-info: #2196F3;
    --color-info-dark: #1976d2;
    --color-info-light: #42a5f5;
    --color-info-bg: #e3f2fd;

    /* Purple (estados de envío) */
    --color-purple: #9C27B0;
    --color-purple-dark: #7b1fa2;
    --color-purple-bg: #f3e5f5;
}
```

### 📝 Colores de Texto

```css
:root {
    --color-text: #333333;              /* Texto principal */
    --color-text-light: #666666;        /* Texto secundario */
    --color-text-lighter: #999999;      /* Texto terciario */
    --color-text-muted: #aaaaaa;        /* Texto deshabilitado */
    --color-text-dark: #1a1a1a;         /* Texto oscuro */
    --color-text-secondary: #555555;    /* Texto de apoyo */
}
```

### 🎨 Colores de Fondo

```css
:root {
    --color-bg: #ffffff;                /* Fondo principal */
    --color-bg-light: #f8f9fa;          /* Fondo claro */
    --color-bg-lighter: #fcfcfc;        /* Fondo muy claro */
    --color-bg-dark: #f5f5f5;           /* Fondo oscuro */
    --color-bg-darker: #e0e0e0;         /* Fondo muy oscuro */
}
```

### 🔲 Colores de Bordes

```css
:root {
    --color-border: #e0e0e0;            /* Borde estándar */
    --color-border-light: #eeeeee;      /* Borde claro */
    --color-border-dark: #bdbdbd;       /* Borde oscuro */
}
```

### 🎯 Colores Especiales

```css
:root {
    /* Colores Base */
    --color-white: #ffffff;
    --color-black: #000000;

    /* Promociones */
    --color-promo: #ff6b6b;
    --color-promo-dark: #ee5a52;
    --color-promo-light: #ff8a8a;

    /* Redes Sociales */
    --color-whatsapp: #25D366;

    /* Otros */
    --color-orange: #FF9800;
    --color-orange-light: #FFA726;
    --color-orange-dark: #F57C00;
    --color-orange-bg: #ffe0b2;
    --color-yellow: #ffeaa7;

    /* MercadoPago */
    --color-mp-blue: #009ee3;
}
```

### 📦 Estados de Pedidos (pedido.php)

```css
:root {
    /* Usadas en payment-status-box */
    --status-pending-color: var(--color-warning);
    --status-pending-bg: var(--color-warning-bg);

    --status-success-color: var(--color-success);
    --status-success-bg: var(--color-success-bg);

    --status-error-color: var(--color-error);
    --status-error-bg: var(--color-error-bg);

    --status-info-color: var(--color-info);
    --status-info-bg: var(--color-info-bg);

    --status-shipped-color: var(--color-purple);
    --status-shipped-bg: var(--color-purple-bg);

    --status-neutral-color: var(--color-text-lighter);
    --status-neutral-bg: var(--color-bg-light);
}
```

### 🛒 Checkout (checkout-new.php)

```css
:root {
    /* Colores principales del checkout */
    --checkout-bg-primary: #fafaf8;
    --checkout-bg-secondary: #ffffff;
    --checkout-text-primary: #1a1a1a;
    --checkout-text-secondary: #6b6b6b;
    --checkout-accent: #b8860b;
    --checkout-accent-hover: #8b6914;
    --checkout-border: #e5e5e0;
    --checkout-success: #2d5016;

    /* Sombras */
    --checkout-shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
    --checkout-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
    --checkout-accent-shadow: rgba(184, 134, 11, 0.1);
    --checkout-accent-bg-light: rgba(184, 134, 11, 0.03);

    /* Payment */
    --checkout-payment-hover-bg: #f8f9fa;

    /* Timer states */
    --checkout-timer-bg-start: #fff3cd;
    --checkout-timer-bg-end: #ffeeba;
    --checkout-timer-border: #ffeaa7;
    --checkout-timer-shadow: rgba(255, 193, 7, 0.2);
    --checkout-timer-warning-start: #f8d7da;
    --checkout-timer-warning-end: #f5c6cb;
    --checkout-timer-warning-border: #f1b0b7;
    --checkout-timer-danger-start: #f5c6cb;
    --checkout-timer-danger-end: #f1b0b7;
}
```

### 📐 Tipografía

```css
:root {
    /* Familias de fuentes */
    --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-family-heading: var(--font-family);
    --font-family-mono: 'SF Mono', Monaco, Consolas, monospace;

    /* Tamaños */
    --font-size-xs: 12px;
    --font-size-sm: 14px;
    --font-size-base: 16px;
    --font-size-lg: 18px;
    --font-size-xl: 22px;
    --font-size-2xl: 28px;
    --font-size-3xl: 36px;
    --font-size-4xl: 52px;

    /* Pesos */
    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;

    /* Interlineado */
    --line-height-tight: 1.2;
    --line-height-normal: 1.5;
    --line-height-relaxed: 1.8;
}
```

### 📏 Espaciado

```css
:root {
    --spacing-xs: 6px;
    --spacing-sm: 12px;
    --spacing-md: 20px;
    --spacing-lg: 28px;
    --spacing-xl: 36px;
    --spacing-2xl: 48px;
    --spacing-3xl: 64px;
}
```

### 🔲 Bordes

```css
:root {
    /* Radios */
    --border-radius-none: 0;
    --border-radius-sm: 2px;
    --border-radius-md: 4px;
    --border-radius-lg: 8px;
    --border-radius-xl: 12px;
    --border-radius-full: 9999px;

    /* Grosores */
    --border-width: 1px;
    --border-width-thick: 2px;
}
```

### 💫 Sombras

```css
:root {
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 8px 16px rgba(0,0,0,0.15);
    --shadow-xl: 0 12px 24px rgba(0,0,0,0.18);
    --shadow-2xl: 0 20px 40px rgba(0,0,0,0.25);

    --color-shadow: rgba(0, 0, 0, 0.15);
    --color-shadow-dark: rgba(0, 0, 0, 0.3);
}
```

### ⚡ Transiciones

```css
:root {
    --transition-fast: 0.2s ease;
    --transition-base: 0.3s ease;
    --transition-slow: 0.6s ease;
}
```

### 📐 Layout

```css
:root {
    /* Contenedores */
    --container-width: 1200px;

    /* Alturas */
    --header-height: 80px;
    --footer-height: 200px;

    /* Breakpoints (solo referencia) */
    --breakpoint-mobile: 480px;
    --breakpoint-tablet: 768px;
    --breakpoint-desktop: 1024px;
    --breakpoint-wide: 1200px;
}
```

### 🔢 Z-Index

```css
:root {
    --z-dropdown: 100;
    --z-sticky: 200;
    --z-fixed: 300;
    --z-modal-backdrop: 400;
    --z-modal: 500;
    --z-popover: 600;
    --z-tooltip: 700;
}
```

### 🎨 Gradientes

```css
:root {
    --gradient-primary: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
}
```

### 👣 Footer Distributed

```css
:root {
    --color-footer-bg: #292c2f;
    --color-footer-icon-bg: #4f4f4f;
    --color-footer-link: #5383d3;
    --color-footer-text-muted: #92999f;
}
```

---

## Estructura de Archivos

### Archivos Obligatorios

#### 1. theme.json (Obligatorio)

Define los metadatos del theme:

```json
{
    "name": "Nombre del Theme",
    "slug": "nombre-del-theme",
    "version": "1.0.0",
    "author": "Tu Nombre",
    "description": "Descripción breve del theme",
    "preview": "/assets/themes/nombre-del-theme/preview.png",
    "supports": {
        "dark_mode": false,
        "custom_colors": true
    }
}
```

**Campos:**
- `name`: Nombre legible del theme (mostrado en admin)
- `slug`: Identificador único (solo minúsculas, guiones, sin espacios)
- `version`: Versión semántica (1.0.0)
- `author`: Tu nombre o empresa
- `description`: Descripción corta (max 200 caracteres)
- `preview`: Ruta a imagen de vista previa (1200x800px recomendado)
- `supports.dark_mode`: ¿Soporta modo oscuro? (true/false)
- `supports.custom_colors`: ¿Permite personalización de colores? (true/false)

#### 2. variables.css (Obligatorio)

Define las CSS Custom Properties:

```css
/**
 * Mi Theme - Variables
 * Descripción breve del estilo
 */

:root {
    /* Define al menos estas variables básicas */
    --color-primary: #tu-color;
    --color-secondary: #tu-color;
    --color-text: #tu-color;
    --color-bg: #tu-color;
    --font-family: tu-fuente, sans-serif;
    --font-size-base: 16px;
    --spacing-md: 1rem;
    --border-radius-md: 4px;

    /* Agrega más variables según necesites */
}
```

### Archivos Opcionales

#### 3. theme.css (Opcional)

Estilos específicos que sobrescriben el CSS base:

```css
/**
 * Mi Theme - Estilos Específicos
 */

/* Sobrescribir componentes base */
.btn-primary {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
    border: none;
    box-shadow: var(--shadow-md);
}

.card {
    border-radius: var(--border-radius-xl);
    transition: transform var(--transition-base);
}

.card:hover {
    transform: translateY(-4px);
}

/* Agregar estilos únicos */
.hero-special {
    background: var(--gradient-primary);
    padding: var(--spacing-3xl) 0;
}
```

**Cuándo usar theme.css:**
- Cuando necesitas sobrescribir estilos base de manera compleja
- Para agregar componentes únicos de tu theme
- Para ajustar layouts específicos
- Cuando las variables CSS no son suficientes

**Cuándo NO usarlo:**
- Para cambiar solo colores → usa `variables.css`
- Para cambiar tamaños → usa `variables.css`
- Para estilos simples → usa `variables.css`

#### 4. preview.png (Opcional pero recomendado)

Imagen de vista previa del theme:
- **Tamaño recomendado:** 1200x800px
- **Formato:** PNG o JPG
- **Ubicación:** `/public_html/assets/themes/tu-theme/preview.png`

---

## Guía Paso a Paso

### Paso 1: Planificación del Theme

Antes de escribir código, define:

1. **Paleta de colores:**
   - Color primario
   - Color secundario
   - Colores de estado (success, warning, error)
   - Colores de texto y fondo

2. **Tipografía:**
   - Fuente principal (Google Fonts, system fonts, etc.)
   - Tamaños base (14px, 16px, 18px)
   - Pesos que usarás

3. **Estilo visual:**
   - Minimalista, moderno, elegante, colorido, etc.
   - Bordes redondeados o cuadrados
   - Sombras sutiles o pronunciadas

**Herramientas útiles:**
- [Adobe Color](https://color.adobe.com/) - Paletas de colores
- [Coolors](https://coolors.co/) - Generador de paletas
- [Google Fonts](https://fonts.google.com/) - Fuentes gratis
- [Type Scale](https://type-scale.com/) - Escalas tipográficas

### Paso 2: Crear la Estructura

```bash
cd /home/pablo/shop-v2

# Crear carpeta del theme
mkdir -p public_html/assets/themes/mi-theme-profesional

# Navegar a la carpeta
cd public_html/assets/themes/mi-theme-profesional
```

### Paso 3: Crear theme.json

```bash
cat > theme.json << 'EOF'
{
    "name": "Mi Theme Profesional",
    "slug": "mi-theme-profesional",
    "version": "1.0.0",
    "author": "Tu Nombre",
    "description": "Un theme moderno y profesional con colores vibrantes",
    "preview": "/assets/themes/mi-theme-profesional/preview.png",
    "supports": {
        "dark_mode": false,
        "custom_colors": true
    }
}
EOF
```

### Paso 4: Crear variables.css

Copia el template completo:

```bash
cat > variables.css << 'EOF'
/**
 * Mi Theme Profesional - Variables
 * Theme moderno con paleta vibrante y tipografía limpia
 */

:root {
    /* ========================================
       COLORES PRINCIPALES
       ======================================== */

    /* Primario */
    --color-primary: #667eea;
    --color-primary-dark: #5568d3;
    --color-primary-light: #818cf8;
    --color-primary-rgb: 102, 126, 234;

    /* Secundario */
    --color-secondary: #764ba2;
    --color-secondary-dark: #5d3a82;
    --color-secondary-light: #9575cd;
    --color-secondary-rgb: 118, 75, 162;

    /* ========================================
       COLORES DE ESTADO
       ======================================== */

    --color-success: #4CAF50;
    --color-success-dark: #45a049;
    --color-success-light: #66bb6a;
    --color-success-bg: #e8f5e9;

    --color-warning: #FF9800;
    --color-warning-dark: #f57c00;
    --color-warning-light: #ffa726;
    --color-warning-bg: #fff3e0;
    --color-warning-text: #856404;

    --color-error: #f44336;
    --color-error-dark: #e53935;
    --color-error-light: #ef5350;
    --color-error-bg: #ffebee;
    --color-error-text: #721c24;

    --color-info: #2196F3;
    --color-info-dark: #1976d2;
    --color-info-light: #42a5f5;
    --color-info-bg: #e3f2fd;

    /* ========================================
       COLORES DE TEXTO
       ======================================== */

    --color-text: #2c3e50;
    --color-text-light: #6c757d;
    --color-text-lighter: #95a5a6;
    --color-text-muted: #bdc3c7;
    --color-text-dark: #1a252f;
    --color-text-secondary: #555555;

    /* ========================================
       COLORES DE FONDO
       ======================================== */

    --color-bg: #ffffff;
    --color-bg-light: #f8f9fa;
    --color-bg-lighter: #fcfcfc;
    --color-bg-dark: #ecf0f1;
    --color-bg-darker: #dfe6e9;

    /* ========================================
       COLORES DE BORDES
       ======================================== */

    --color-border: #dfe4ea;
    --color-border-light: #f1f3f5;
    --color-border-dark: #ced4da;

    /* ========================================
       TIPOGRAFÍA
       ======================================== */

    --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    --font-family-heading: var(--font-family);
    --font-family-mono: 'SF Mono', Monaco, Consolas, monospace;

    --font-size-xs: 12px;
    --font-size-sm: 14px;
    --font-size-base: 16px;
    --font-size-lg: 18px;
    --font-size-xl: 22px;
    --font-size-2xl: 28px;
    --font-size-3xl: 36px;
    --font-size-4xl: 52px;

    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;

    --line-height-tight: 1.2;
    --line-height-normal: 1.5;
    --line-height-relaxed: 1.8;

    /* ========================================
       ESPACIADO
       ======================================== */

    --spacing-xs: 6px;
    --spacing-sm: 12px;
    --spacing-md: 20px;
    --spacing-lg: 28px;
    --spacing-xl: 36px;
    --spacing-2xl: 48px;
    --spacing-3xl: 64px;

    /* ========================================
       BORDES
       ======================================== */

    --border-radius-none: 0;
    --border-radius-sm: 4px;
    --border-radius-md: 8px;
    --border-radius-lg: 12px;
    --border-radius-xl: 16px;
    --border-radius-full: 9999px;

    --border-width: 1px;
    --border-width-thick: 2px;

    /* ========================================
       SOMBRAS
       ======================================== */

    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.12);
    --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.15);
    --shadow-xl: 0 12px 24px rgba(0, 0, 0, 0.18);
    --shadow-2xl: 0 20px 40px rgba(0, 0, 0, 0.25);

    /* ========================================
       TRANSICIONES
       ======================================== */

    --transition-fast: 0.2s ease;
    --transition-base: 0.3s ease;
    --transition-slow: 0.6s ease;

    /* ========================================
       LAYOUT
       ======================================== */

    --container-width: 1200px;
    --header-height: 80px;
    --footer-height: 200px;

    /* ========================================
       COLORES ESPECIALES
       ======================================== */

    --color-white: #ffffff;
    --color-black: #000000;
    --color-promo: #ff6b6b;
    --color-whatsapp: #25D366;

    /* ========================================
       GRADIENTES
       ======================================== */

    --gradient-primary: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
}
EOF
```

### Paso 5: Crear theme.css (opcional)

Solo si necesitas estilos personalizados:

```bash
cat > theme.css << 'EOF'
/**
 * Mi Theme Profesional - Estilos Específicos
 */

/* Botones con gradiente */
.btn-primary {
    background: var(--gradient-primary);
    border: none;
    box-shadow: var(--shadow-md);
    transition: all var(--transition-base);
}

.btn-primary:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

/* Cards con efecto hover */
.product-card {
    transition: transform var(--transition-base);
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

/* Hero section especial */
.hero {
    background: var(--gradient-primary);
}
EOF
```

### Paso 6: Activar el Theme

Edita el archivo de configuración:

```bash
cd /home/pablo/shop-v2

# Editar config
nano app/config/theme.json
```

Cambia el `active_theme`:

```json
{
    "active_theme": "mi-theme-profesional"
}
```

Guarda (Ctrl+O, Enter, Ctrl+X).

### Paso 7: Limpiar Caché

```bash
rm -f public_html/assets/cache/theme-*.min.css
```

### Paso 8: Probar el Theme

1. Abre el navegador
2. Visita: https://peu.net/shopv2
3. Refresca (Ctrl+F5 para limpiar caché del navegador)
4. Verifica que los colores y estilos se apliquen

### Paso 9: Ajustar y Refinar

1. Inspecciona con DevTools (F12)
2. Identifica elementos que necesitan ajustes
3. Modifica `variables.css` o `theme.css`
4. Refresca y revisa
5. Repite hasta que estés satisfecho

---

## Ejemplos Prácticos

### Ejemplo 1: Theme Oscuro (Dark Mode)

```css
/* dark-theme/variables.css */
:root {
    /* Invertir colores de fondo y texto */
    --color-bg: #1a1a1a;
    --color-bg-light: #2c2c2c;
    --color-bg-dark: #0d0d0d;

    --color-text: #e0e0e0;
    --color-text-light: #b0b0b0;
    --color-text-lighter: #808080;

    --color-border: #404040;

    /* Ajustar colores primarios para mejor contraste */
    --color-primary: #818cf8;
    --color-primary-dark: #667eea;

    /* Sombras más sutiles */
    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.5);
    --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.6);
}
```

### Ejemplo 2: Theme Minimalista

```css
/* minimal-theme/variables.css */
:root {
    /* Paleta monocromática */
    --color-primary: #000000;
    --color-primary-dark: #1a1a1a;
    --color-secondary: #666666;

    /* Tipografía simple */
    --font-family: 'Helvetica Neue', Arial, sans-serif;
    --font-size-base: 15px;

    /* Espaciado generoso */
    --spacing-md: 24px;
    --spacing-lg: 40px;

    /* Sin bordes redondeados */
    --border-radius-md: 0;
    --border-radius-lg: 0;

    /* Sombras mínimas */
    --shadow-sm: none;
    --shadow-md: 0 1px 2px rgba(0, 0, 0, 0.05);
}
```

### Ejemplo 3: Theme Colorido (Vibrant)

```css
/* vibrant-theme/variables.css */
:root {
    /* Colores vibrantes */
    --color-primary: #ff6b6b;
    --color-primary-dark: #ee5a52;
    --color-secondary: #4ecdc4;
    --color-secondary-dark: #45b8b0;

    /* Estados con colores brillantes */
    --color-success: #51cf66;
    --color-warning: #ffd43b;
    --color-error: #ff6b9d;
    --color-info: #339af0;

    /* Fondos con color */
    --color-bg: #fff9f9;
    --color-bg-light: #ffe9e9;

    /* Bordes muy redondeados */
    --border-radius-md: 16px;
    --border-radius-lg: 24px;

    /* Sombras coloridas */
    --shadow-md: 0 4px 12px rgba(255, 107, 107, 0.2);
}
```

### Ejemplo 4: Theme Corporativo

```css
/* corporate-theme/variables.css */
:root {
    /* Colores corporativos */
    --color-primary: #003d82;        /* Azul corporativo */
    --color-primary-dark: #002952;
    --color-secondary: #6c757d;      /* Gris neutro */

    /* Tipografía profesional */
    --font-family: 'Segoe UI', Tahoma, sans-serif;
    --font-size-base: 15px;

    /* Espaciado preciso */
    --spacing-md: 16px;
    --spacing-lg: 24px;

    /* Bordes sutiles */
    --border-radius-md: 2px;
    --border-radius-lg: 4px;

    /* Colores de texto formales */
    --color-text: #212529;
    --color-text-light: #495057;
}
```

---

## Sistema de Caché

### Cómo Funciona

El sistema detecta automáticamente el entorno:

- **Desarrollo** (`/home/pablo/shop-v2`): Archivos individuales sin caché
- **Producción** (`/home2/uv0023/`): Archivo único cacheado y minificado

### Flujo de Caché

```
1. Primera carga en producción:
   ✓ Combina 7 archivos CSS
   ✓ Minifica el resultado
   ✓ Genera hash MD5 basado en timestamps
   ✓ Guarda: theme-mi-theme-a1b2c3d4.min.css

2. Cargas subsiguientes:
   ✓ Verifica si existe cache
   ✓ Compara timestamps
   ✓ Usa cache si está actualizado

3. Modificas variables.css:
   ✓ Nuevo hash: e5f6g7h8
   ✓ Genera nuevo cache
   ✓ Elimina cache antiguo
```

### Limpiar Caché Manualmente

```bash
# Limpiar todo el caché
rm -f /home/pablo/shop-v2/public_html/assets/cache/theme-*.min.css

# Limpiar solo un theme específico
rm -f /home/pablo/shop-v2/public_html/assets/cache/theme-mi-theme-*.min.css
```

---

## Troubleshooting

### Problema 1: Los cambios CSS no se reflejan

**Síntomas:**
- Modificaste `variables.css` pero no ves cambios
- Los colores siguen siendo los antiguos

**Soluciones:**

1. **Limpiar caché del servidor:**
   ```bash
   rm -f public_html/assets/cache/theme-*.min.css
   ```

2. **Limpiar caché del navegador:**
   - Chrome/Firefox: `Ctrl + F5`
   - Safari: `Cmd + Shift + R`

3. **Verificar que el theme esté activo:**
   ```bash
   cat app/config/theme.json
   # Debe mostrar: "active_theme": "tu-theme"
   ```

4. **Verificar permisos:**
   ```bash
   chmod 644 public_html/assets/themes/tu-theme/variables.css
   chmod 755 public_html/assets/cache/
   ```

### Problema 2: El theme no carga (página sin estilos)

**Síntomas:**
- Página aparece sin CSS
- Elementos desalineados
- Tipografía incorrecta

**Soluciones:**

1. **Verificar que el theme existe:**
   ```bash
   ls -la public_html/assets/themes/tu-theme/
   # Debe mostrar: theme.json, variables.css
   ```

2. **Verificar sintaxis de theme.json:**
   ```bash
   cat public_html/assets/themes/tu-theme/theme.json
   # Debe ser JSON válido
   ```

3. **Verificar sintaxis de variables.css:**
   - Abre variables.css en un editor
   - Busca errores de sintaxis (llaves sin cerrar, punto y coma faltantes)

4. **Revisar consola del navegador (F12):**
   - Buscar errores 404 (archivos no encontrados)
   - Buscar errores de CSS

### Problema 3: Colores no se aplican correctamente

**Síntomas:**
- Algunos elementos tienen color, otros no
- Colores fallback aparecen en lugar de tus colores

**Soluciones:**

1. **Verificar que las variables estén definidas:**
   ```css
   /* Asegúrate de definir TODAS las variables que uses */
   :root {
       --color-primary: #667eea;  /* ✓ */
       /* --color-secondary: ???  ✗ (sin definir) */
   }
   ```

2. **Usar nombres correctos de variables:**
   ```css
   /* Correcto */
   .btn { background: var(--color-primary); }

   /* Incorrecto (typo) */
   .btn { background: var(--color-primari); }
   ```

3. **Agregar fallbacks:**
   ```css
   .btn {
       /* Si --color-primary no existe, usa #667eea */
       background: var(--color-primary, #667eea);
   }
   ```

### Problema 4: Theme se ve mal en mobile

**Síntomas:**
- Se ve bien en desktop pero mal en mobile
- Elementos cortados o solapados

**Soluciones:**

1. **Verificar responsive en DevTools:**
   - F12 → Toggle device toolbar
   - Probar diferentes tamaños

2. **Ajustar espaciados para mobile:**
   ```css
   /* En theme.css */
   @media (max-width: 768px) {
       :root {
           --spacing-lg: 20px;
           --font-size-base: 14px;
       }
   }
   ```

3. **No modificar layout base:**
   - El CSS base ya es responsive
   - Solo ajusta colores y tipografía

### Problema 5: Caché no se genera

**Síntomas:**
- No aparece archivo en `/assets/cache/`
- Siempre carga archivos individuales

**Soluciones:**

1. **Verificar permisos del directorio:**
   ```bash
   chmod 755 public_html/assets/cache/
   chown www-data:www-data public_html/assets/cache/
   ```

2. **Verificar espacio en disco:**
   ```bash
   df -h
   ```

3. **Verificar que estés en producción:**
   ```bash
   pwd
   # Debe ser: /home2/uv0023/... (producción)
   # Si es: /home/pablo/... (desarrollo, no usa cache)
   ```

---

## Mejores Prácticas

### ✅ DO (Hacer)

1. **Usar variables CSS siempre que sea posible**
   ```css
   /* ✓ Correcto */
   .btn { background: var(--color-primary); }

   /* ✗ Evitar */
   .btn { background: #667eea; }
   ```

2. **Definir todas las variables principales**
   - Colores (primary, secondary, text, bg, border)
   - Tipografía (family, sizes, weights)
   - Espaciado (spacing-md como mínimo)
   - Bordes (border-radius-md como mínimo)

3. **Usar nombres semánticos**
   ```css
   /* ✓ Correcto */
   --color-primary: #667eea;
   --color-success: #4CAF50;

   /* ✗ Evitar */
   --color-azul: #667eea;
   --color-verde: #4CAF50;
   ```

4. **Agregar comentarios descriptivos**
   ```css
   :root {
       /* ========================================
          COLORES PRINCIPALES
          ======================================== */

       /* Color primario usado en botones y links */
       --color-primary: #667eea;
   }
   ```

5. **Probar en todas las páginas**
   - Home
   - Producto
   - Carrito
   - Checkout
   - Pedido (tracking)
   - Gracias (confirmación)

6. **Probar responsive**
   - Mobile (320px - 480px)
   - Tablet (768px - 1024px)
   - Desktop (1200px+)

7. **Mantener consistencia**
   - Usa la misma paleta en todo el theme
   - Usa la misma tipografía
   - Mantén el mismo estilo de bordes

### ❌ DON'T (No hacer)

1. **No modificar archivos _base/**
   ```bash
   # ✗ No modificar
   public_html/assets/themes/_base/components.css
   public_html/assets/themes/_base/layout.css

   # ✓ Crear tus propios estilos en
   public_html/assets/themes/tu-theme/theme.css
   ```

2. **No usar !important**
   ```css
   /* ✗ Evitar */
   .btn { background: red !important; }

   /* ✓ Mejor: aumentar especificidad */
   .product-card .btn-primary { background: red; }
   ```

3. **No hardcodear colores**
   ```css
   /* ✗ Evitar */
   .card { background: #ffffff; }

   /* ✓ Usar variables */
   .card { background: var(--color-bg); }
   ```

4. **No definir variables inline en HTML**
   ```html
   <!-- ✗ Evitar -->
   <div style="--color-primary: red;">

   <!-- ✓ Definir en variables.css -->
   ```

5. **No duplicar código base en theme.css**
   ```css
   /* ✗ No copiar código de _base/ */
   .btn { padding: 10px 20px; ... }

   /* ✓ Solo sobrescribir lo necesario */
   .btn { background: var(--color-primary); }
   ```

### 🎯 Consejos Pro

1. **Usa gradientes para dar profundidad:**
   ```css
   --gradient-primary: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
   ```

2. **Define RGB para transparencias:**
   ```css
   --color-primary: #667eea;
   --color-primary-rgb: 102, 126, 234;

   /* Usar con rgba() */
   .overlay {
       background: rgba(var(--color-primary-rgb), 0.8);
   }
   ```

3. **Crea variantes de colores:**
   ```css
   --color-primary: #667eea;
   --color-primary-dark: #5568d3;    /* -10% lightness */
   --color-primary-light: #818cf8;   /* +10% lightness */
   ```

4. **Usa system fonts para mejor performance:**
   ```css
   --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
   ```

5. **Define transiciones consistentes:**
   ```css
   --transition-base: 0.3s ease;

   .btn, .card, .link {
       transition: all var(--transition-base);
   }
   ```

---

## Recursos Adicionales

### Herramientas de Diseño

- **Paletas de colores:**
  - [Adobe Color](https://color.adobe.com/)
  - [Coolors](https://coolors.co/)
  - [Color Hunt](https://colorhunt.co/)

- **Tipografía:**
  - [Google Fonts](https://fonts.google.com/)
  - [Font Pair](https://www.fontpair.co/)
  - [Type Scale](https://type-scale.com/)

- **Espaciado y Layout:**
  - [Spacing Calculator](https://hihayk.github.io/scale/)
  - [Grid Calculator](https://gridcalculator.dk/)

- **Sombras:**
  - [SmoothShadow](https://shadows.brumm.af/)
  - [Box Shadow Generator](https://cssgenerator.org/box-shadow-css-generator.html)

### Referencias CSS

- [MDN: CSS Variables](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)
- [Can I Use: CSS Variables](https://caniuse.com/css-variables)
- [CSS Tricks: Custom Properties](https://css-tricks.com/a-complete-guide-to-custom-properties/)

---

## Changelog

### 2025-12-13
- ✅ Agregada sección completa de CSS variables
- ✅ Agregada guía paso a paso de creación
- ✅ Agregados ejemplos prácticos de themes
- ✅ Ampliado troubleshooting
- ✅ Agregadas mejores prácticas

### 2025-12-08
- ✅ Documentación inicial creada
- ✅ Sistema de caché documentado
- ✅ Arquitectura explicada

---

*Documentación actualizada: 2025-12-13*
*Versión: 2.0.0*
*Autor: Shop V2 Team*
