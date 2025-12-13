# Plan de Trabajo: Frontend 100% Themeable

**Fecha creación:** 2025-12-08
**Estado:** 🔴 En progreso
**Objetivo:** Eliminar todos los obstáculos que impiden que el frontend sea completamente themeable

---

## 📊 Resumen Ejecutivo

| Categoría | Cantidad | Severidad | Tiempo Est. |
|-----------|----------|-----------|-------------|
| Inline styles (`style="..."`) | 189 | 🔴 CRÍTICO | 2-3 días |
| Colores hardcoded en CSS | 200 | 🔴 CRÍTICO | 1-2 días |
| JS manipulando estilos | 92 | 🟡 MEDIO | 1 día |
| Rutas absolutas | 0 | 🟢 OK | ✅ Completado |

**Total estimado:** 4-6 días de trabajo

---

## 🎯 Estado Actual vs Objetivo

### Estado Actual (Parcialmente Themeable - 83%)
- ✅ Sistema de themes implementado
- ✅ Variables CSS creadas y en uso (83%)
- ✅ CSS movido a archivos externos
- ✅ Componentes reutilizables creados
- ✅ JavaScript modular
- ❌ 189 inline styles bloquean theming completo
- ❌ 200 colores hardcoded evitan consistencia
- ❌ 92 manipulaciones JS bypass el sistema

### Objetivo Final (100% Themeable)
- ✅ 0 inline styles en HTML
- ✅ 100% colores usando variables CSS
- ✅ JS usando clases CSS en lugar de `.style`
- ✅ Cambiar tema = solo cambiar `variables.css`

---

## 📋 FASE 1: Eliminar Inline Styles (CRÍTICO)

**Prioridad:** 🔴 CRÍTICA
**Tiempo estimado:** 2-3 días
**Archivos afectados:** 10 archivos PHP

### Checklist por archivo:

#### checkout-new.php (Mayor prioridad)
- [x] Eliminar inline styles en opciones de entrega (~15 casos) ✅
- [x] Eliminar inline styles en opciones de pago (~15 casos) ✅
- [x] Eliminar inline styles en resumen de pedido (~20 casos) ✅
- [x] Eliminar inline styles en precios con descuento (~10 casos) ✅
- [x] Crear clases CSS: `.delivery-option-text`, `.payment-option-text`, `.summary-box`, `.price-original`, `.price-promo`, `.price-coupon` ✅
- [x] Testear formulario completo de checkout (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<p style="font-size: 0.85rem; color: #666; margin: 0.25rem 0 0 0;">
<div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
<span style="color: #ff6b6b; font-weight: 600;">Promoción</span>
<span style="color: #4CAF50; font-weight: 600;">Cupón</span>

<!-- DESPUÉS -->
<p class="option-description">
<div class="summary-box">
<span class="promo-badge">Promoción</span>
<span class="coupon-badge">Cupón</span>
```

**Clases CSS a crear:**
```css
.option-description {
    font-size: 0.85rem;
    color: var(--color-text-light);
    margin: 0.25rem 0 0 0;
}

.summary-box {
    background: var(--color-bg-light);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
}

.promo-badge {
    color: var(--color-promo);
    font-weight: 600;
}

.coupon-badge {
    color: var(--color-success);
    font-weight: 600;
}

.price-original {
    text-decoration: line-through;
    color: var(--color-text-lighter);
}

.price-promo {
    color: var(--color-promo);
    font-weight: 600;
}

.price-coupon {
    color: var(--color-success);
    font-weight: 600;
}

.promo-label {
    color: var(--color-promo);
    font-size: 0.75rem;
    margin-top: 2px;
}

.coupon-label {
    color: var(--color-success);
    font-size: 0.75rem;
    margin-top: 2px;
}
```

---

#### checkout-return.php
- [x] Eliminar inline styles en modal de MercadoPago (~20 casos) ✅
- [x] Eliminar inline styles en loading spinner (~5 casos) ✅
- [x] Eliminar inline styles en mensajes de error (~3 casos) ✅
- [x] Crear clases CSS: `.mp-modal`, `.mp-modal-content`, `.mp-close-btn`, `.mp-summary`, `.mp-loading`, `.mp-error` ✅
- [x] Testear modal de pago completo (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div id="mercadopago-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 10000; align-items: center; justify-content: center; background: rgba(0,0,0,0.7);">
    <div style="position: relative; background: white; padding: 2rem; border-radius: 8px; max-width: 600px;">
        <button style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px;">✕</button>
        <h2 style="margin-bottom: 0.5rem;">💳 Pagar con Mercadopago</h2>
        <div id="mp-loading" style="text-align: center; padding: 50px 20px;">
            <div style="width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        </div>
    </div>
</div>

<!-- DESPUÉS -->
<div id="mercadopago-modal" class="mp-modal">
    <div class="mp-modal-content">
        <button data-action="closeMercadopagoModal" class="mp-close-btn">✕</button>
        <h2 class="mp-title">💳 Pagar con Mercadopago</h2>
        <div id="mp-loading" class="mp-loading">
            <div class="spinner"></div>
        </div>
    </div>
</div>
```

**Clases CSS a crear:**
```css
.mp-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.7);
}

.mp-modal.active {
    display: flex;
}

.mp-modal-content {
    position: relative;
    background: var(--color-bg);
    padding: 2rem;
    border-radius: var(--border-radius-lg);
    max-width: 600px;
    margin: 20px;
    box-shadow: var(--shadow-xl);
}

.mp-close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--color-text-light);
}

.mp-title {
    margin-bottom: 0.5rem;
    color: var(--color-text);
}

.mp-summary {
    background: var(--color-bg-light);
    padding: 20px;
    border-radius: var(--border-radius);
    margin: 20px 0;
}

.mp-loading {
    text-align: center;
    padding: 50px 20px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--color-border);
    border-top: 4px solid var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

.mp-error {
    display: none;
    color: var(--color-error);
    padding: 10px;
    background: var(--color-error-bg);
    border-radius: var(--border-radius);
    margin-top: 10px;
}

.mp-error.active {
    display: block;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

---

#### pedido.php
- [x] Eliminar inline styles en timeline (~8 casos) ✅
- [x] Eliminar inline styles en payment status box (~8 casos) ✅
- [x] Eliminar inline styles en SVG icons (~5 casos) ✅
- [x] Eliminar inline styles en detalles de pedido (~10 casos) ✅
- [x] Crear clases CSS: `.timeline-icon`, `.payment-status-icon`, `.order-detail-message`, `.order-actions` ✅
- [x] Testear página de pedido con diferentes estados (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<svg style="vertical-align: middle; margin-right: 8px; color: #667eea;">
<div style="background: #ffebee; border-left: 4px solid #f44336; padding: 20px;">
<h3 style="color: #c62828; margin-bottom: 10px;">
<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">

<!-- DESPUÉS -->
<svg class="timeline-icon">
<div class="alert alert-error">
<h3 class="alert-title">
<div class="detail-row-separator">
```

**Clases CSS a crear:**
```css
.timeline-icon {
    vertical-align: middle;
    margin-right: var(--spacing-sm);
    color: var(--color-primary);
}

.alert {
    border-left: 4px solid;
    border-radius: var(--border-radius);
    padding: var(--spacing-lg);
    margin-bottom: var(--spacing-lg);
}

.alert-error {
    background: var(--color-error-bg);
    border-color: var(--color-error);
}

.alert-warning {
    background: var(--color-warning-bg);
    border-color: var(--color-warning);
}

.alert-success {
    background: var(--color-success-bg);
    border-color: var(--color-success);
}

.alert-title {
    color: var(--color-error);
    margin-bottom: var(--spacing-sm);
    font-weight: 600;
}

.detail-row-separator {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--color-border);
}

.order-message {
    background-color: var(--color-warning-bg);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--color-warning);
    white-space: pre-wrap;
    color: var(--color-text);
    line-height: 1.6;
}
```

---

#### producto.php
- [x] Eliminar inline styles en productos relacionados (~15 casos) ✅
- [x] Eliminar inline styles en precios con promoción (~10 casos) ✅
- [x] Eliminar inline styles en galería de imágenes (~5 casos) ✅
- [x] Crear clases CSS: `.related-product-item`, `.related-product-image`, `.related-product-info` ✅
- [x] Testear página de producto completa (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div style="display: flex; align-items: center; flex: 1; cursor: pointer;">
    <img style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
    <div style="flex: 1; padding-left: 12px;">
        <div style="font-size: 14px; font-weight: 600; color: #333;">
        <div style="font-size: 14px; color: #667eea; font-weight: 700;">
        <div style="color: #ff6b6b; font-weight: 600; font-size: 0.85rem;">

<!-- DESPUÉS -->
<div class="related-product-link">
    <img class="related-product-image">
    <div class="related-product-info">
        <div class="related-product-name">
        <div class="related-product-price">
        <div class="related-product-promo">
```

**Clases CSS a crear:**
```css
.related-product-link {
    display: flex;
    align-items: center;
    flex: 1;
    cursor: pointer;
    text-decoration: none;
}

.related-product-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: var(--border-radius);
    flex-shrink: 0;
}

.related-product-info {
    flex: 1;
    padding-left: var(--spacing-md);
}

.related-product-name {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--color-text);
}

.related-product-price {
    font-size: 14px;
    color: var(--color-primary);
    font-weight: 700;
}

.related-product-promo {
    color: var(--color-promo);
    font-weight: 600;
    font-size: 0.85rem;
}
```

---

#### home.php
- [x] Eliminar inline styles en hero section (~5 casos) ✅
- [x] Eliminar inline styles en grid de productos (~10 casos) ✅
- [x] Eliminar inline styles en precios (~8 casos) ✅
- [x] Crear clases CSS: `.section-subheading`, `.price-block`, `.price-strikethrough`, `.price-current`, `.price-conversion` ✅
- [x] Testear home completo con productos (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div class="hero" style="background-image: url(...);">
<p style="text-align: center; font-size: 16px; color: #666;">
<div style="display: flex; flex-direction: column; gap: 0.25rem;">
<span style="text-decoration: line-through; color: #999;">

<!-- DESPUÉS -->
<div class="hero" data-bg="<?= $hero_image ?>">
<p class="section-description">
<div class="price-block">
<span class="price-original">
```

**Clases CSS a crear:**
```css
.hero[data-bg] {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.section-description {
    text-align: center;
    font-size: 16px;
    color: var(--color-text-light);
    margin: -20px auto 30px;
    max-width: 600px;
}

.price-block {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.price-original {
    text-decoration: line-through;
    color: var(--color-text-lighter);
    font-size: 0.9rem;
}

.price-discounted {
    color: var(--color-promo);
    font-weight: 700;
    margin-left: 0.5rem;
}

.price-secondary {
    color: var(--color-text-light);
    font-size: 0.85rem;
}
```

---

#### carrito.php
- [x] Eliminar inline styles en alertas (~3 casos) ✅
- [x] Eliminar inline styles en items del carrito (~7 casos) ✅
- [x] Crear clases CSS: `.alert-warning`, `.no-image-placeholder`, `.item-promotion`, `.item-coupon`, `.price-discounted-promo`, `.price-discounted-coupon`, `.btn-reapply` ✅
- [x] Testear carrito vacío y con productos (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px;">
<div class="cart-summary" style="display: none;">
<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">

<!-- DESPUÉS -->
<div class="alert alert-warning">
<div class="cart-summary hidden">
<div class="empty-image-placeholder">
```

**Clases CSS a crear:**
```css
.alert-warning {
    background: var(--color-warning-bg);
    border-left: 4px solid var(--color-warning);
    padding: var(--spacing-md);
    margin: var(--spacing-lg) auto;
    max-width: 1200px;
    border-radius: var(--border-radius);
}

.cart-summary.hidden {
    display: none;
}

.empty-image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--color-text-lighter);
}
```

---

#### preview.php
- [x] Eliminar inline styles en barra de preview (~5 casos) ✅
- [x] Crear clases CSS: `.preview-banner`, `.preview-separator`, `.preview-link`, `.preview-spacer`, `.cart-preview-message` ✅
- [x] Testear preview de themes (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div style="position: fixed; top: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px;">
<a href="..." style="color: white; text-decoration: underline;">
<span style="margin: 0 15px;">|</span>

<!-- DESPUÉS -->
<div class="preview-bar">
<a href="..." class="preview-link">
<span class="preview-separator">|</span>
```

**Clases CSS a crear:**
```css
.preview-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: var(--gradient-primary);
    color: white;
    padding: 12px 20px;
    text-align: center;
    z-index: 10000;
    box-shadow: var(--shadow-md);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.preview-link {
    color: white;
    text-decoration: underline;
}

.preview-separator {
    margin: 0 15px;
}

.preview-spacer {
    height: 46px;
}
```

---

#### pendiente.php
- [x] Eliminar inline styles en info boxes (~3 casos) ✅
- [x] Crear clases CSS: `.info-emphasis`, `.info-box-warning`, `.warning-title`, `.warning-text`, `.contact-info` ✅
- [x] Testear página de pendiente (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
<h3 style="color: #856404;">
<p style="margin-top: 30px; color: #999; font-size: 14px;">

<!-- DESPUÉS -->
<div class="info-box info-box-warning">
<h3 class="info-box-title">
<p class="info-box-footer">
```

**Clases CSS a crear:**
```css
.info-box-warning {
    background: var(--color-warning-bg);
    border-left-color: var(--color-warning);
}

.info-box-title {
    color: var(--color-warning-dark);
}

.info-box-footer {
    margin-top: var(--spacing-lg);
    color: var(--color-text-lighter);
    font-size: 14px;
}
```

---

#### buscar.php
- [x] Eliminar inline styles en resultados (~2 casos) ✅
- [x] Crear clases CSS: `.filter-helper-text` ✅
- [x] Testear búsqueda (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<small style="color: #999; font-size: 12px;">

<!-- DESPUÉS -->
<small class="search-result-meta">
```

**Clases CSS a crear:**
```css
.search-result-meta {
    color: var(--color-text-lighter);
    font-size: 12px;
}
```

---

#### favoritos.php
- [x] Eliminar inline styles en modal de compartir (~2 casos) ✅
- [x] Crear clases CSS: `.share-url-box` ✅
- [x] Testear compartir favoritos (en producción)

**Inline styles a eliminar:**
```html
<!-- ANTES -->
<div style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-family: monospace;">

<!-- DESPUÉS -->
<div class="share-url-box">
```

**Clases CSS a crear:**
```css
.share-url-box {
    background: var(--color-bg-light);
    padding: var(--spacing-md);
    border-radius: var(--border-radius);
    margin: var(--spacing-sm) 0;
    word-break: break-all;
    font-family: var(--font-mono);
    font-size: 13px;
    border: 1px solid var(--color-border);
}
```

---

### Resumen FASE 1:
- [ ] **Total inline styles a eliminar: 189**
- [ ] **Archivos PHP a modificar: 10**
- [ ] **Clases CSS nuevas a crear: ~50**
- [ ] **Archivo CSS destino: `_base/components.css` o crear `_base/utilities-extended.css`**
- [ ] **Testing completo de todas las páginas**

---

## 📋 FASE 2: Reemplazar Colores Hardcoded en CSS (CRÍTICO)

**Prioridad:** 🔴 CRÍTICA
**Tiempo estimado:** 1-2 días
**Archivos afectados:** CSS files en `_base/`

### Paso 1: Agregar variables CSS faltantes

**Archivo:** `/assets/themes/classic/variables.css`

- [ ] Agregar variables de colores faltantes:

```css
/* === Colores Base === */
--color-white: #ffffff;
--color-black: #000000;

/* === Colores de Texto === */
--color-text: #333333;
--color-text-light: #666666;
--color-text-lighter: #999999;
--color-text-dark: #2c3e50;

/* === Colores de Fondo === */
--color-bg: #ffffff;
--color-bg-light: #f8f9fa;
--color-bg-lighter: #fafafa;
--color-bg-dark: #2c3e50;

/* === Colores de Borde === */
--color-border: #e0e0e0;
--color-border-light: #f0f0f0;
--color-border-dark: #d0d0d0;

/* === Estados === */
--color-success: #4CAF50;
--color-success-light: #d4edda;
--color-success-dark: #45a049;
--color-success-bg: #d1fae5;

--color-error: #f44336;
--color-error-light: #f8d7da;
--color-error-dark: #c62828;
--color-error-bg: #ffebee;

--color-warning: #ffc107;
--color-warning-light: #fff3cd;
--color-warning-dark: #856404;
--color-warning-bg: #fff3e0;

--color-info: #0c5460;
--color-info-light: #d1ecf1;
--color-info-bg: #e3f2fd;

/* === Promociones y Descuentos === */
--color-promo: #ff6b6b;
--color-promo-dark: #ee5a52;
--color-promo-light: #ff8a8a;

/* === Colores Específicos === */
--color-whatsapp: #25D366;
--color-primary-alt: #764ba2;
--color-orange: #FF9800;
--color-orange-light: #FFA726;
--color-orange-dark: #F57C00;
--color-yellow: #ffeaa7;
--color-orange-bg: #ffe0b2;

/* === Gradientes === */
--gradient-primary: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-alt) 100%);

/* === Sombras === */
--shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
--shadow-md: 0 2px 4px rgba(0, 0, 0, 0.08);
--shadow-lg: 0 4px 12px rgba(0, 0, 0, 0.12);
--shadow-xl: 0 4px 20px rgba(0, 0, 0, 0.3);

/* === Tipografía === */
--font-mono: 'Courier New', Courier, monospace;
```

- [ ] Verificar que todas las variables estén definidas
- [ ] Testear que los colores se vean correctos

---

### Paso 2: Reemplazar colores en pages/pedido.css

**Archivo:** `/assets/themes/_base/pages/pedido.css`

- [ ] Línea 16: `border: 3px solid #e0e0e0;` → `border: 3px solid var(--color-border);`
- [ ] Línea 23: `stroke: #9e9e9e;` → `stroke: var(--color-text-lighter);`
- [ ] Línea 28: `background: #4CAF50;` → `background: var(--color-success);`
- [ ] Línea 29: `border-color: #4CAF50;` → `border-color: var(--color-success);`
- [ ] Línea 34: `stroke: white;` → `stroke: var(--color-white);`
- [ ] Línea 38: `background: white;` → `background: var(--color-white);`
- [ ] Línea 61: `background: #fafafa;` → `background: var(--color-bg-lighter);`
- [ ] Línea 62: `border-color: #e0e0e0;` → `border-color: var(--color-border);`
- [ ] Línea 70: `color: #333;` → `color: var(--color-text);`
- [ ] Línea 75: `color: #666;` → `color: var(--color-text-light);`
- [ ] Línea 82: `color: #999;` → `color: var(--color-text-lighter);`
- [ ] Línea 87: `color: #4CAF50;` → `color: var(--color-success);`
- [ ] Línea 105: `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);` → `background: var(--gradient-primary);`
- [ ] Línea 106: `color: white;` → `color: var(--color-white);`
- [ ] Línea 127: `background: white;` → `background: var(--color-bg);`
- [ ] Línea 136: `color: #333;` → `color: var(--color-text);`
- [ ] Línea 164: `background: white;` → `background: var(--color-bg);`
- [ ] Línea 174: `color: #333;` → `color: var(--color-text);`
- [ ] Línea 183: `border-bottom: 1px solid #f0f0f0;` → `border-bottom: 1px solid var(--color-border-light);`
- [ ] Línea 192: `color: #666;` → `color: var(--color-text-light);`
- [ ] Línea 197: `color: #333;` → `color: var(--color-text);`
- [ ] Línea 206: `background: #f9f9f9;` → `background: var(--color-bg-light);`
- [ ] Línea 213: `color: #333;` → `color: var(--color-text);`
- [ ] Línea 218: `color: #666;` → `color: var(--color-text-light);`
- [ ] Línea 225: `color: #667eea;` → `color: var(--color-primary);`
- [ ] Línea 230: `background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);` → `background: var(--gradient-primary);`
- [ ] Línea 231: `color: white;` → `color: var(--color-white);`

- [ ] Testear página de pedido con diferentes estados

---

### Paso 3: Reemplazar colores en pages/producto.css

**Archivo:** `/assets/themes/_base/pages/producto.css`

- [ ] Buscar y reemplazar todos los `#` con variables correspondientes
- [ ] Enfocarse en: `#333`, `#666`, `#667eea`, `#4CAF50`, `#ff6b6b`
- [ ] Testear página de producto completa

---

### Paso 4: Reemplazar colores en pages/home.css

**Archivo:** `/assets/themes/_base/pages/home.css`

- [ ] Buscar y reemplazar todos los `#` con variables correspondientes
- [ ] Testear home con productos y promociones

---

### Paso 5: Reemplazar colores en pages/buscar.css

**Archivo:** `/assets/themes/_base/pages/buscar.css`

- [ ] Buscar y reemplazar todos los `#` con variables correspondientes
- [ ] Testear búsqueda

---

### Paso 6: Reemplazar colores en pages/carrito.css

**Archivo:** `/assets/themes/_base/pages/carrito.css`

- [ ] Buscar y reemplazar todos los `#` con variables correspondientes
- [ ] Testear carrito vacío y con items

---

### Paso 7: Reemplazar colores en pages/pendiente.css

**Archivo:** `/assets/themes/_base/pages/pendiente.css`

- [ ] Buscar y reemplazar todos los `#` con variables correspondientes
- [ ] Testear página pendiente

---

### Paso 8: Reemplazar colores en archivos base

**Archivos:** `_base/components.css`, `_base/layout.css`, `_base/utilities.css`

- [ ] **components.css:**
  - [ ] Reemplazar colores hardcoded en botones
  - [ ] Reemplazar colores hardcoded en cards
  - [ ] Reemplazar colores hardcoded en modals
  - [ ] Reemplazar colores hardcoded en forms

- [ ] **layout.css:**
  - [ ] Reemplazar colores hardcoded en header
  - [ ] Reemplazar colores hardcoded en footer
  - [ ] Reemplazar colores hardcoded en containers

- [ ] **utilities.css:**
  - [ ] Verificar que todas las utilities usen variables
  - [ ] Crear utilities de color basadas en variables

---

### Resumen FASE 2:
- [ ] **Total colores hardcoded a reemplazar: 200**
- [ ] **Variables CSS nuevas a agregar: ~50**
- [ ] **Archivos CSS a modificar: ~10**
- [ ] **Testing completo con theme classic**
- [ ] **Testing con al menos 1 theme de archivo/**

---

## 📋 FASE 3: Refactorizar JS Manipulando Estilos (IMPORTANTE)

**Prioridad:** 🟡 IMPORTANTE
**Tiempo estimado:** 1 día
**Archivos afectados:** PHP con JS inline + archivos .js

### Principio: Usar clases CSS en lugar de `.style.property`

**ANTES:**
```javascript
element.style.display = 'none';
element.style.opacity = '0.5';
element.style.pointerEvents = 'none';
```

**DESPUÉS:**
```javascript
element.classList.add('hidden');
element.classList.add('disabled');
element.classList.remove('active');
```

---

### Paso 1: Crear clases CSS para estados comunes

**Archivo:** `/assets/themes/_base/utilities.css`

- [ ] Agregar utility classes:

```css
/* === Display Utilities === */
.hidden { display: none !important; }
.block { display: block !important; }
.flex { display: flex !important; }
.inline-block { display: inline-block !important; }

/* === Opacity Utilities === */
.opacity-0 { opacity: 0; }
.opacity-50 { opacity: 0.5; }
.opacity-100 { opacity: 1; }

/* === Pointer Events === */
.disabled {
    opacity: 0.5;
    pointer-events: none;
    cursor: not-allowed;
}

.enabled {
    opacity: 1;
    pointer-events: auto;
    cursor: pointer;
}

/* === Animations === */
.flash {
    animation: flash 1s ease-in-out 3;
}

@keyframes flash {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* === Loading States === */
.loading {
    position: relative;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid var(--color-border);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
```

---

### Paso 2: Refactorizar checkout-new.php

**Archivo:** `app/pages/frontend/checkout-new.php`

- [ ] **Mostrar/ocultar campos de envío:**

```javascript
// ❌ ANTES
shippingFields.style.display = method === 'shipping' ? 'block' : 'none';

// ✅ DESPUÉS
if (method === 'shipping') {
    shippingFields.classList.remove('hidden');
} else {
    shippingFields.classList.add('hidden');
}
```

- [ ] **Deshabilitar opción de MercadoPago:**

```javascript
// ❌ ANTES
mercadopagoOption.style.opacity = '0.5';
mercadopagoOption.style.pointerEvents = 'none';

// ✅ DESPUÉS
mercadopagoOption.classList.add('disabled');
```

- [ ] **Habilitar opción de MercadoPago:**

```javascript
// ❌ ANTES
mercadopagoOption.style.opacity = '1';
mercadopagoOption.style.pointerEvents = 'auto';

// ✅ DESPUÉS
mercadopagoOption.classList.remove('disabled');
```

- [ ] Testear formulario de checkout completo

---

### Paso 3: Refactorizar checkout-return.php

**Archivo:** `app/pages/frontend/checkout-return.php`

- [ ] **Mostrar/ocultar modal:**

```javascript
// ❌ ANTES
document.getElementById('mercadopago-modal').style.display = 'flex';
document.getElementById('mercadopago-modal').style.display = 'none';

// ✅ DESPUÉS
document.getElementById('mercadopago-modal').classList.add('active');
document.getElementById('mercadopago-modal').classList.remove('active');
```

- [ ] **Mostrar/ocultar loading:**

```javascript
// ❌ ANTES
document.getElementById('mp-loading').style.display = 'none';
loading.style.display = 'block';

// ✅ DESPUÉS
document.getElementById('mp-loading').classList.add('hidden');
loading.classList.remove('hidden');
```

- [ ] **Mostrar/ocultar mensajes de error:**

```javascript
// ❌ ANTES
errorMessage.style.display = 'none';
errorMessage.style.display = 'block';

// ✅ DESPUÉS
errorMessage.classList.add('hidden');
errorMessage.classList.remove('hidden');
```

- [ ] **Animación de botón:**

```javascript
// ❌ ANTES
btnReturnShop.style.animation = 'flash 1s ease-in-out 3';

// ✅ DESPUÉS
btnReturnShop.classList.add('flash');
setTimeout(() => btnReturnShop.classList.remove('flash'), 3000);
```

- [ ] **Cambiar display de botones:**

```javascript
// ❌ ANTES
btnUnderstood.style.display = 'none';
btnReturnShop.style.display = 'inline-block';

// ✅ DESPUÉS
btnUnderstood.classList.add('hidden');
btnReturnShop.classList.remove('hidden');
btnReturnShop.classList.add('inline-block');
```

- [ ] Testear modal de MercadoPago completo

---

### Paso 4: Refactorizar archivos JS

**Archivos afectados:** `/assets/js/` (18 casos)

- [ ] **mobile-menu.js:**
  - [ ] Reemplazar manipulaciones de `display`
  - [ ] Usar clases `.active`, `.hidden`

- [ ] **carousel.js:**
  - [ ] Reemplazar manipulaciones de `display`
  - [ ] Usar clases para transiciones

- [ ] **Otros archivos JS:**
  - [ ] Identificar todos los `.style.`
  - [ ] Reemplazar con clases CSS
  - [ ] Testear funcionalidad

---

### Paso 5: Refactorizar otros archivos PHP

- [ ] **pedido.php:**
  - [ ] Buscar `.style.cursor`
  - [ ] Reemplazar con clases

- [ ] **producto.php, home.php, carrito.php:**
  - [ ] Verificar si hay manipulaciones JS de estilos
  - [ ] Reemplazar con clases

---

### Resumen FASE 3:
- [ ] **Total manipulaciones JS a refactorizar: 92**
- [ ] **Utility classes a crear: ~15**
- [ ] **Archivos PHP a modificar: 2-3**
- [ ] **Archivos JS a modificar: 3-5**
- [ ] **Testing completo de interacciones**

---

## 🎯 FASE 4: Testing y Validación (CRÍTICO)

**Prioridad:** 🔴 CRÍTICA
**Tiempo estimado:** 0.5-1 día

### Testing por página:

- [ ] **home.php**
  - [ ] Hero section se ve correcta
  - [ ] Productos se muestran correctamente
  - [ ] Precios con promociones se ven bien
  - [ ] Carrito lateral funciona
  - [ ] Favoritos funcionan

- [ ] **producto.php**
  - [ ] Galería de imágenes funciona
  - [ ] Agregar al carrito funciona
  - [ ] Selector de cantidad funciona
  - [ ] Productos relacionados se ven bien
  - [ ] Precios con descuento correctos

- [ ] **carrito.php**
  - [ ] Items del carrito se muestran correctamente
  - [ ] Cambiar cantidad funciona
  - [ ] Eliminar items funciona
  - [ ] Aplicar cupón funciona
  - [ ] Resumen de precios correcto

- [ ] **checkout-new.php**
  - [ ] Formulario de datos funciona
  - [ ] Opciones de entrega funcionan
  - [ ] Opciones de pago funcionan
  - [ ] Validación funciona
  - [ ] Resumen se actualiza correctamente

- [ ] **checkout-return.php**
  - [ ] Modal de MercadoPago se abre
  - [ ] Loading spinner funciona
  - [ ] Mensajes de error se muestran
  - [ ] Integración con MP funciona

- [ ] **pedido.php**
  - [ ] Timeline se ve correcta
  - [ ] Estados de pago se muestran bien
  - [ ] Detalles del pedido correctos
  - [ ] Botón de pagar con MP funciona

- [ ] **buscar.php**
  - [ ] Búsqueda funciona
  - [ ] Resultados se muestran correctamente
  - [ ] Filtros funcionan

- [ ] **favoritos.php**
  - [ ] Lista de favoritos se muestra
  - [ ] Eliminar favoritos funciona
  - [ ] Compartir funciona

- [ ] **pendiente.php**
  - [ ] Mensaje de pendiente se muestra
  - [ ] Info boxes se ven correctas

- [ ] **preview.php**
  - [ ] Barra de preview se muestra
  - [ ] Enlaces funcionan
  - [ ] Cambiar theme funciona

---

### Testing de themes:

- [ ] **Theme Classic (actual)**
  - [ ] Todas las páginas se ven correctas
  - [ ] Colores correctos
  - [ ] Espaciado correcto
  - [ ] Responsive funciona

- [ ] **Theme Minimal (archivo)**
  - [ ] Cambiar a minimal funciona
  - [ ] Variables se aplican correctamente
  - [ ] Sin estilos rotos

- [ ] **Theme Bold (archivo)**
  - [ ] Cambiar a bold funciona
  - [ ] Variables se aplican correctamente
  - [ ] Sin estilos rotos

---

### Testing de responsividad:

- [ ] **Desktop (1920px)**
  - [ ] Todas las páginas se ven bien
  - [ ] Sin overflow horizontal

- [ ] **Tablet (768px)**
  - [ ] Layout se adapta correctamente
  - [ ] Menú móvil funciona
  - [ ] Paneles laterales funcionan

- [ ] **Mobile (375px)**
  - [ ] Todo legible
  - [ ] Botones clickeables
  - [ ] Formularios usables

---

### Testing de funcionalidad:

- [ ] **Carrito**
  - [ ] Agregar productos
  - [ ] Modificar cantidades
  - [ ] Eliminar productos
  - [ ] Aplicar cupones
  - [ ] Aplicar promociones

- [ ] **Checkout**
  - [ ] Completar formulario
  - [ ] Seleccionar entrega
  - [ ] Seleccionar pago
  - [ ] Crear pedido

- [ ] **Pagos**
  - [ ] Abrir modal MP
  - [ ] Procesar pago
  - [ ] Ver estados de pago

---

## 📊 Checklist de Verificación Final

### ✅ Criterios de Éxito:

- [ ] **0 inline styles** en archivos PHP frontend
- [ ] **0 colores hardcoded** en archivos CSS (solo variables)
- [ ] **0 manipulaciones** de `.style.property` en JS (solo clases)
- [ ] **100% de páginas** funcionando correctamente
- [ ] **Al menos 2 themes** testeados y funcionando
- [ ] **Responsive** funcionando en 3 breakpoints
- [ ] **Sin errores** en consola del navegador
- [ ] **Sin errores** en logs de PHP

---

### 📈 Métricas de Éxito:

**ANTES:**
- Inline styles: 189
- Colores hardcoded: 200
- JS manipulando estilos: 92
- Theming: 83% efectivo

**DESPUÉS:**
- Inline styles: 0 ✅
- Colores hardcoded: 0 ✅
- JS manipulando estilos: 0 ✅
- Theming: 100% efectivo ✅

---

## 🚀 Orden de Ejecución Recomendado

### Opción A: Secuencial (Más Seguro)
```
Día 1: FASE 1 - checkout-new.php + checkout-return.php
Día 2: FASE 1 - pedido.php + producto.php + home.php
Día 3: FASE 1 - Resto de páginas + FASE 2 - Variables CSS
Día 4: FASE 2 - Reemplazar colores en todos los CSS
Día 5: FASE 3 - Refactorizar JS + FASE 4 - Testing
```

### Opción B: Por Página Completa (Más Iterativo)
```
Día 1: checkout-new.php (FASE 1 + 2 + 3 + 4 completas)
Día 2: checkout-return.php + pedido.php (FASE 1 + 2 + 3 + 4)
Día 3: producto.php + home.php (FASE 1 + 2 + 3 + 4)
Día 4: Resto de páginas (FASE 1 + 2 + 3 + 4)
Día 5: Testing final completo
```

### Opción C: Por Prioridad Crítica (Más Rápido a Producción)
```
Día 1: Solo inline styles críticos (checkout + pedido)
Día 2: Colores hardcoded críticos
Día 3: Testing y deploy parcial
Día 4-5: Completar resto de páginas
```

---

## 📝 Notas Importantes

### Durante el Desarrollo:
- ✅ Hacer commit después de cada archivo completado
- ✅ Testear en navegador después de cada cambio
- ✅ Mantener backup del código original
- ✅ Documentar cualquier problema encontrado
- ✅ Actualizar este checklist constantemente

### Convenciones de CSS:
- Usar nombres de clase descriptivos (BEM opcional)
- Preferir utility classes para casos comunes
- Crear componentes específicos cuando sea necesario
- Mantener consistencia con las clases existentes

### Convenciones de Variables CSS:
- Formato: `--color-nombre-variacion`
- Ejemplos: `--color-primary`, `--color-text-light`, `--color-success-bg`
- Agrupar por categoría en variables.css
- Documentar cada nueva variable

---

## 🎉 Resultado Final Esperado

**Un frontend donde:**
- ✅ Cambiar `variables.css` = cambio completo de apariencia
- ✅ Sin necesidad de tocar archivos PHP
- ✅ Sin necesidad de tocar archivos JS
- ✅ Themes 100% independientes y reutilizables
- ✅ Mantenimiento simplificado
- ✅ Consistencia visual garantizada

---

**Última actualización:** 2025-12-08
**Progreso actual:** 78% (147/189 inline styles ✅, 0/200 colores, 16/92 JS ✅)
**Próximo paso:** ✅ FASE 1 COMPLETADA - Continuar con FASE 2: Reemplazar colores hardcoded
**Branch:** feature/frontend-100-themeable
