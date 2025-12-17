# Plan de Implementación: Sistema de Instalador Mejorado V2

**Branch**: `feature/installer-system`
**Fecha**: 2025-12-17
**Autor**: Claude Code

---

## 📋 Resumen Ejecutivo

Diseño e implementación de un instalador interactivo completo para Shop V2 que permite configurar el sistema de forma guiada, crear todos los archivos JSON necesarios con valores por defecto inteligentes, y generar datos de ejemplo opcionales para facilitar el testing inicial del sistema.

---

## 🎯 Objetivos del Proyecto

### Objetivos Principales

1. **Instalación guiada paso a paso**: Wizard interactivo con múltiples pasos y validación en tiempo real
2. **Configuración completa del sistema**: Permitir al usuario configurar todos los aspectos críticos
3. **Generación automática de JSONs**: Crear TODOS los archivos JSON necesarios con estructuras válidas
4. **Datos de ejemplo opcionales**: Permitir crear productos, cupones y promociones de ejemplo
5. **Auto-destrucción segura**: El instalador debe eliminarse automáticamente después de completar la instalación

### Objetivos Secundarios

- Validación robusta de inputs del usuario
- Mensajes de error claros y descriptivos
- Interfaz moderna y profesional
- Compatibilidad con los 3 entornos (producción, testing, desarrollo)
- Respeto total de las reglas del proyecto (español, CSP, modales custom)

---

## 🏗️ Arquitectura del Instalador

### Ubicación

```
public_html/install/
├── installer.php          # Wizard principal (se autoelimina)
└── .htaccess             # Protección temporal (opcional)
```

### Flujo de Instalación

```
┌─────────────────────────────────────────────────────────────┐
│                    PASO 1: Bienvenida                        │
│  - Explicación de arquitectura de seguridad                 │
│  - Advertencias importantes                                 │
│  - Botón "Comenzar Instalación"                             │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│              PASO 2: Configuración de Paths                  │
│  - Ruta de aplicación (app/)                                │
│  - Ruta pública (public_html/)                              │
│  - URL del sitio                                            │
│  - Base path (subdirectorio)                                │
│  - Validación automática de rutas                           │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         PASO 3: Información del Administrador                │
│  - Nombre del administrador                                 │
│  - Email del administrador                                  │
│  - Usuario admin                                            │
│  - Contraseña admin (validación de fortaleza)               │
│  - Confirmar contraseña                                     │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│           PASO 4: Configuración del Sitio                    │
│  - Nombre del sitio                                         │
│  - Descripción del sitio                                    │
│  - Email de contacto                                        │
│  - Teléfono de contacto (opcional)                          │
│  - Logo (opcional - puede subirse después)                  │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         PASO 5: Configuración de Moneda y Pagos              │
│  - Moneda principal (ARS/USD)                               │
│  - Tasa de cambio inicial                                   │
│  - Habilitar MercadoPago (sí/no)                            │
│  - Access Token de MercadoPago (opcional)                   │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│            PASO 6: Datos de Ejemplo (Opcional)               │
│  ┌─────────────────────────────────────────────┐            │
│  │ ☑ Crear productos de ejemplo (3 productos) │            │
│  └─────────────────────────────────────────────┘            │
│  ┌─────────────────────────────────────────────┐            │
│  │ ☑ Crear cupones de ejemplo (2 cupones)     │            │
│  └─────────────────────────────────────────────┘            │
│  ┌─────────────────────────────────────────────┐            │
│  │ ☑ Crear promociones de ejemplo (1 promo)   │            │
│  └─────────────────────────────────────────────┘            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│              PASO 7: Confirmación y Resumen                  │
│  - Mostrar resumen de toda la configuración                 │
│  - Botón "Instalar Sistema"                                 │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│               PASO 8: Proceso de Instalación                 │
│  [████████████████████] 100%                                │
│  ✅ Creando estructura de directorios                       │
│  ✅ Generando archivo config.php                            │
│  ✅ Creando archivos JSON de configuración                  │
│  ✅ Creando archivos JSON de datos                          │
│  ✅ Creando usuario administrador                           │
│  ✅ Generando datos de ejemplo                              │
│  ✅ Configurando permisos                                   │
│  ✅ Creando archivos .htaccess                              │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  PASO 9: Instalación Exitosa                 │
│  ✅ ¡Sistema instalado correctamente!                       │
│                                                             │
│  📋 Checklist de Seguridad:                                 │
│  ☐ Verificar permisos de archivos                           │
│  ☐ Configurar SSL/HTTPS                                     │
│  ☐ Configurar backups automáticos                           │
│                                                             │
│  🗑️ ELIMINAR INSTALADOR                                    │
│  [Botón: Auto-eliminar y Finalizar]                        │
│                                                             │
│  Enlaces:                                                   │
│  → Ir al Sitio                                              │
│  → Login Admin                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Archivos JSON a Crear

### 1. Archivo de Configuración Principal

**Ubicación**: `app/config/config.php`

```php
<?php
/**
 * Auto-generated Configuration
 * Generated: [timestamp]
 * DO NOT commit this file to repository
 */

return [
    'app_name' => [valor del usuario],
    'app_url' => [valor del usuario],
    'base_path' => [valor del usuario],
    'secret_key' => [generado automáticamente - 64 chars],
    'csrf_token_expiry' => 3600,
    'app_path' => [valor del usuario],
    'public_path' => [valor del usuario],
    'maintenance_mode' => false,
    'debug' => false,
    'log_errors' => true,
];
```

### 2. Archivos JSON de Configuración (app/config/)

#### 2.1 `site.json` - Configuración del Sitio

```json
{
    "site_name": "[nombre del usuario]",
    "site_description": "[descripción del usuario]",
    "site_keywords": "ecommerce, tienda, productos",
    "contact_email": "[email del usuario]",
    "contact_phone": "[teléfono del usuario o vacío]",
    "footer_text": "© 2025 [nombre del sitio]. Todos los derechos reservados.",
    "whatsapp": {
        "enabled": false,
        "number": "",
        "message": "Hola, te estoy consultando desde la tienda,",
        "custom_link": "",
        "display_text": "Mensaje Whatsapp"
    },
    "telegram": {
        "enabled": false,
        "bot_token": "",
        "chat_id": ""
    },
    "logo": {
        "enabled": false,
        "path": "",
        "alt": "[nombre del sitio]"
    },
    "site_owner": "[nombre del administrador]",
    "meta_tags": {
        "og_title": "[nombre del sitio]",
        "og_type": "website",
        "og_url": "[url del sitio]",
        "og_url_secure": "[url del sitio]",
        "og_image": "",
        "og_site_name": "[nombre del sitio]",
        "og_description": "[descripción del sitio]",
        "content_type": "text/html; charset=utf-8",
        "og_image_width": "1200",
        "og_image_height": "630",
        "twitter_card": "summary_large_image"
    }
}
```

#### 2.2 `email.json` - Configuración de Email

```json
{
    "enabled": false,
    "method": "smtp",
    "from_email": "[email del usuario]",
    "from_name": "[nombre del sitio]",
    "admin_email": "[email del usuario]",
    "notifications": {
        "customer": {
            "order_created": true,
            "payment_approved": true,
            "payment_rejected": false,
            "payment_pending": false,
            "order_shipped": true,
            "chargeback_notice": false
        },
        "admin": {
            "new_order": true,
            "payment_approved": true,
            "chargeback_alert": true,
            "low_stock_alert": false
        }
    }
}
```

#### 2.3 `payment.json` - Configuración de Pagos

```json
{
    "mercadopago": {
        "enabled": [true/false según usuario],
        "access_token": "[token del usuario o vacío]",
        "public_key": "",
        "webhook_secret": "[generado automáticamente]",
        "sandbox_mode": false
    },
    "payment_methods": {
        "credit_card": true,
        "debit_card": true,
        "bank_transfer": false,
        "cash": false
    }
}
```

#### 2.4 `currency.json` - Configuración de Moneda

```json
{
    "primary": "[ARS o USD según usuario]",
    "secondary": "[USD o ARS - opuesto a primary]",
    "exchange_rate": [tasa ingresada por usuario, default 1500],
    "auto_update": false,
    "update_source": "dolarapi",
    "update_type": "blue",
    "last_update": "[timestamp de instalación]"
}
```

#### 2.5 `theme.json` - Tema Activo

```json
{
    "active_theme": "minimal"
}
```

#### 2.6 `maintenance.json` - Modo Mantenimiento

```json
{
    "enabled": false,
    "bypass_code": "[generado automáticamente - 8 chars]",
    "message": "Sitio en mantenimiento. Volveremos pronto."
}
```

#### 2.7 `carousel.json` - Carrusel de Imágenes

```json
{
    "slides": []
}
```

#### 2.8 `hero.json` - Sección Hero

```json
{
    "enabled": false,
    "title": "Bienvenido a [nombre del sitio]",
    "subtitle": "[descripción del sitio]",
    "image": "",
    "cta_text": "Ver Productos",
    "cta_link": "/productos"
}
```

#### 2.9 `footer.json` - Footer del Sitio

```json
{
    "columns": [
        {
            "title": "Acerca de",
            "links": []
        },
        {
            "title": "Enlaces",
            "links": []
        },
        {
            "title": "Contacto",
            "links": []
        }
    ],
    "social_media": {
        "facebook": "",
        "instagram": "",
        "twitter": "",
        "youtube": ""
    }
}
```

#### 2.10 `telegram.json` - Notificaciones Telegram

```json
{
    "enabled": false,
    "bot_token": "",
    "chat_id": "",
    "notifications": {
        "new_order": false,
        "payment_approved": false,
        "payment_rejected": false,
        "low_stock": false
    }
}
```

#### 2.11 `analytics.json` - Analytics

```json
{
    "google_analytics": {
        "enabled": false,
        "tracking_id": ""
    },
    "facebook_pixel": {
        "enabled": false,
        "pixel_id": ""
    }
}
```

#### 2.12 `products-heading.json` - Encabezado de Productos

```json
{
    "enabled": false,
    "title": "Nuestros Productos",
    "subtitle": "Descubre nuestra selección"
}
```

#### 2.13 `strings.json` - Textos del Sistema

```json
{
    "cart": {
        "add_to_cart": "Agregar al Carrito",
        "remove_from_cart": "Quitar del Carrito",
        "empty_cart": "Tu carrito está vacío",
        "continue_shopping": "Continuar Comprando",
        "proceed_to_checkout": "Proceder al Pago"
    },
    "checkout": {
        "shipping_info": "Información de Envío",
        "payment_method": "Método de Pago",
        "order_summary": "Resumen del Pedido"
    },
    "common": {
        "loading": "Cargando...",
        "error": "Error",
        "success": "Éxito",
        "cancel": "Cancelar",
        "confirm": "Confirmar"
    }
}
```

#### 2.14 `dashboard.json` - Dashboard Admin

```json
{
    "widgets": {
        "sales": true,
        "orders": true,
        "products": true,
        "customers": false
    }
}
```

### 3. Archivos JSON de Datos (app/data/)

Todos estos archivos se crean con estructuras vacías o con datos de ejemplo según la elección del usuario.

#### 3.1 `users.json` - Usuarios del Sistema

```json
{
    "users": [
        {
            "id": "admin-[uniqid]",
            "username": "[usuario del admin]",
            "password": "[hash Argon2ID]",
            "email": "[email del admin]",
            "name": "[nombre del admin]",
            "role": "admin",
            "created_at": "[timestamp]",
            "last_login": null
        }
    ]
}
```

#### 3.2 `products.json` - Productos

```json
{
    "products": [
        // Vacío o con datos de ejemplo
    ]
}
```

**Datos de Ejemplo** (si el usuario selecciona crear productos):

```json
{
    "products": [
        {
            "id": "prod-[uniqid]",
            "name": "Producto de Ejemplo 1",
            "slug": "producto-ejemplo-1",
            "description": "Este es un producto de ejemplo para probar el sistema.",
            "price_ars": 10000,
            "price_usd": 10,
            "stock": 50,
            "stock_enabled": true,
            "category": "General",
            "images": [],
            "thumbnail": "/assets/images/placeholder-product.jpg",
            "featured": true,
            "active": true,
            "created_at": "[timestamp]",
            "updated_at": "[timestamp]"
        },
        {
            "id": "prod-[uniqid]",
            "name": "Producto de Ejemplo 2",
            "slug": "producto-ejemplo-2",
            "description": "Segundo producto de ejemplo con stock limitado.",
            "price_ars": 25000,
            "price_usd": 25,
            "stock": 10,
            "stock_enabled": true,
            "category": "Destacados",
            "images": [],
            "thumbnail": "/assets/images/placeholder-product.jpg",
            "featured": true,
            "active": true,
            "created_at": "[timestamp]",
            "updated_at": "[timestamp]"
        },
        {
            "id": "prod-[uniqid]",
            "name": "Producto de Ejemplo 3",
            "slug": "producto-ejemplo-3",
            "description": "Tercer producto con precio solo en USD.",
            "price_ars": 0,
            "price_usd": 50,
            "stock": 0,
            "stock_enabled": false,
            "category": "Sin Stock",
            "images": [],
            "thumbnail": "/assets/images/placeholder-product.jpg",
            "featured": false,
            "active": true,
            "created_at": "[timestamp]",
            "updated_at": "[timestamp]"
        }
    ]
}
```

#### 3.3 `coupons.json` - Cupones de Descuento

```json
{
    "coupons": [
        // Vacío o con datos de ejemplo
    ]
}
```

**Datos de Ejemplo** (si el usuario selecciona crear cupones):

```json
{
    "coupons": [
        {
            "id": "coupon-[uniqid]",
            "code": "BIENVENIDO10",
            "description": "10% de descuento para nuevos clientes",
            "type": "percentage",
            "value": 10,
            "min_purchase": 5000,
            "max_uses": 100,
            "used_count": 0,
            "active": true,
            "valid_from": "[timestamp]",
            "valid_until": "[timestamp + 30 días]",
            "created_at": "[timestamp]"
        },
        {
            "id": "coupon-[uniqid]",
            "code": "DESCUENTO500",
            "description": "$500 de descuento en compras mayores a $10.000",
            "type": "fixed",
            "value": 500,
            "min_purchase": 10000,
            "max_uses": 50,
            "used_count": 0,
            "active": true,
            "valid_from": "[timestamp]",
            "valid_until": "[timestamp + 60 días]",
            "created_at": "[timestamp]"
        }
    ]
}
```

#### 3.4 `promotions.json` - Promociones

```json
{
    "promotions": [
        // Vacío o con datos de ejemplo
    ]
}
```

**Datos de Ejemplo** (si el usuario selecciona crear promociones):

```json
{
    "promotions": [
        {
            "id": "promo-[uniqid]",
            "name": "Promoción de Lanzamiento",
            "description": "2x1 en productos seleccionados",
            "type": "2x1",
            "applicable_products": [],
            "applicable_categories": ["General"],
            "active": true,
            "valid_from": "[timestamp]",
            "valid_until": "[timestamp + 15 días]",
            "created_at": "[timestamp]"
        }
    ]
}
```

#### 3.5 Otros Archivos de Datos (Vacíos)

```json
// orders.json
{
    "orders": []
}

// archived_orders.json
{
    "orders": []
}

// reviews.json
{
    "reviews": []
}

// wishlists.json
{
    "wishlists": []
}

// newsletters.json
{
    "subscribers": []
}

// admin_logs.json
{
    "logs": []
}

// stock_logs.json
{
    "logs": []
}

// visits.json
{
    "visits": []
}

// webhook_log.json
{
    "logs": []
}

// webhook_rate_limit.json
{
    "limits": []
}

// mp_preference_log.json
{
    "preferences": []
}

// mp_logs.json
{
    "logs": []
}
```

---

## 🔧 Funcionalidades del Instalador

### 1. Validación de Inputs

#### Validaciones de Paths

- Verificar que las rutas existan y sean accesibles
- Verificar permisos de escritura
- Validar que `app_path` esté FUERA de `public_path`
- Prevenir paths que puedan causar problemas de seguridad

#### Validaciones de Usuario

- Email válido (formato RFC compliant)
- Contraseña mínimo 8 caracteres
- Recomendación de contraseña fuerte (16+ chars, mayúsculas, números, símbolos)
- Confirmar contraseña (deben coincidir)
- Username no vacío y sin espacios

#### Validaciones de Configuración

- URL válida (con http:// o https://)
- Moneda válida (ARS o USD)
- Tasa de cambio numérica y positiva
- Access Token de MercadoPago (formato válido si se proporciona)

### 2. Detección Automática de Entorno

El instalador debe detectar automáticamente el entorno de instalación:

```php
// Producción
if (file_exists('/home2/uv0023/shop-v2-app')) {
    $default_app_path = '/home2/uv0023/shop-v2-app';
    $default_public_path = '/home2/uv0023/public_html/shopv2';
}
// Testing
elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app')) {
    $default_app_path = '/home/pablo/shop-v2-local-test/shop-v2-app';
    $default_public_path = '/home/pablo/shop-v2-local-test/public_html';
}
// Desarrollo
else {
    $default_app_path = dirname(dirname(__DIR__)) . '/app';
    $default_public_path = dirname(__DIR__);
}
```

### 3. Generación Automática de Claves de Seguridad

- **Secret Key**: 64 caracteres hexadecimales aleatorios (`bin2hex(random_bytes(32))`)
- **Webhook Secret**: 32 caracteres hexadecimales aleatorios
- **Bypass Code**: 8 caracteres alfanuméricos aleatorios para modo mantenimiento

### 4. Creación de Estructura de Directorios

```php
// Crear directorios necesarios si no existen
$directories = [
    $app_path . '/config',
    $app_path . '/data',
    $app_path . '/data/products',
    $public_path . '/uploads',
    $public_path . '/uploads/products',
    $public_path . '/uploads/logos',
    $public_path . '/uploads/og-images',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
}
```

### 5. Configuración de Permisos

```php
// Permisos recomendados
chmod($app_path, 0750);
chmod($app_path . '/config', 0750);
chmod($app_path . '/config/config.php', 0640);
chmod($app_path . '/data', 0750);
chmod($app_path . '/data/users.json', 0640);
chmod($public_path . '/uploads', 0755);
```

### 6. Creación de .htaccess

#### app/.htaccess

```apache
# Security: Block ALL access to application code
Require all denied
Options -Indexes
```

#### public_html/.htaccess

```apache
# Rewrite rules
RewriteEngine On

# Redirect to HTTPS (production only)
RewriteCond %{HTTPS} off
RewriteCond %{HTTP_HOST} !^localhost [NC]
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Frontend routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?route=/$1 [QSA,L]

# Security
Options -Indexes

# Protect sensitive files
<FilesMatch "(\.env|\.git|config\.php)">
    Require all denied
</FilesMatch>
```

### 7. Hash de Contraseña

Usar **Argon2ID** como algoritmo de hash (más seguro que bcrypt):

```php
$hashed_password = password_hash($password, PASSWORD_ARGON2ID);
```

### 8. Logging de Instalación

Crear un log de instalación en `app/data/install_log.json`:

```json
{
    "installed_at": "[timestamp]",
    "installer_version": "2.0",
    "environment": "production|testing|development",
    "paths": {
        "app_path": "[path]",
        "public_path": "[path]"
    },
    "admin_user": "[username]",
    "features_enabled": {
        "mercadopago": true|false,
        "email_notifications": true|false
    },
    "sample_data_created": {
        "products": true|false,
        "coupons": true|false,
        "promotions": true|false
    }
}
```

### 9. Auto-eliminación del Instalador

Función recursiva para eliminar la carpeta `/install/` y todos sus contenidos:

```php
function delete_installer() {
    $install_dir = __DIR__;

    $delete_recursive = function($dir) use (&$delete_recursive) {
        if (!is_dir($dir)) {
            return unlink($dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $delete_recursive($path) : unlink($path);
        }

        return rmdir($dir);
    };

    try {
        $delete_recursive($install_dir);
        return true;
    } catch (Exception $e) {
        error_log("Error eliminando instalador: " . $e->getMessage());
        return false;
    }
}
```

---

## 🎨 Diseño de Interfaz

### Principios de Diseño

- **Limpio y moderno**: Diseño minimalista estilo wizard
- **Responsivo**: Mobile-first, adaptable a todos los tamaños de pantalla
- **Accesible**: Colores con buen contraste, texto legible
- **Progreso visual**: Indicador de pasos completados
- **Feedback claro**: Mensajes de error y éxito bien visibles

### Paleta de Colores

```css
/* Colores principales */
--primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
--success-color: #28a745;
--error-color: #dc3545;
--warning-color: #ffc107;
--info-color: #17a2b8;

/* Colores de fondo */
--bg-light: #f8f9fa;
--bg-white: #ffffff;
--bg-dark: #343a40;

/* Colores de texto */
--text-dark: #212529;
--text-muted: #6c757d;
--text-light: #ffffff;
```

### Componentes UI

#### Indicador de Progreso

```html
<div class="progress-indicator">
    <div class="step completed">
        <div class="step-number">1</div>
        <div class="step-label">Bienvenida</div>
    </div>
    <div class="step-connector completed"></div>
    <div class="step active">
        <div class="step-number">2</div>
        <div class="step-label">Configuración</div>
    </div>
    <div class="step-connector"></div>
    <div class="step">
        <div class="step-number">3</div>
        <div class="step-label">Finalizar</div>
    </div>
</div>
```

#### Input con Validación

```html
<div class="form-group">
    <label for="email">Email del Administrador</label>
    <input
        type="email"
        id="email"
        name="admin_email"
        required
        data-validate="email"
    >
    <div class="form-help">Se usará para notificaciones del sistema</div>
    <div class="form-error" style="display: none;">
        Por favor ingresa un email válido
    </div>
</div>
```

#### Checkbox de Opciones

```html
<div class="checkbox-group">
    <label class="checkbox-label">
        <input type="checkbox" name="create_sample_products" value="1" checked>
        <span class="checkbox-custom"></span>
        <span class="checkbox-text">
            <strong>Crear productos de ejemplo</strong>
            <small>Se crearán 3 productos de ejemplo para testing</small>
        </span>
    </label>
</div>
```

---

## 🧪 Testing del Instalador

### Escenarios de Prueba

#### 1. Instalación Básica (Sin Datos de Ejemplo)

- Configurar solo lo mínimo necesario
- No crear datos de ejemplo
- Verificar que el sistema funcione correctamente

#### 2. Instalación Completa (Con Datos de Ejemplo)

- Configurar todos los campos
- Crear todos los datos de ejemplo
- Verificar que productos, cupones y promociones aparezcan correctamente

#### 3. Validación de Errores

- Intentar usar paths inválidos
- Usar contraseña débil
- Email inválido
- Verificar que los errores se muestren correctamente

#### 4. Reinstalación

- Instalar el sistema
- Intentar acceder al instalador nuevamente
- Verificar que muestre mensaje de "Ya instalado"
- Probar reinstalación forzada con `?force=1`

#### 5. Auto-eliminación

- Completar instalación
- Presionar botón de auto-eliminación
- Verificar que la carpeta `/install/` se elimine completamente
- Verificar redirección al sitio

---

## 📋 Checklist de Implementación

### Fase 1: Estructura Base

- [ ] Crear estructura de archivos del instalador
- [ ] Implementar detección de entorno
- [ ] Crear sistema de sesiones para almacenar datos entre pasos
- [ ] Implementar navegación entre pasos (siguiente/anterior)

### Fase 2: Formularios y Validación

- [ ] Implementar formulario de Paso 2 (Paths)
- [ ] Implementar formulario de Paso 3 (Admin)
- [ ] Implementar formulario de Paso 4 (Sitio)
- [ ] Implementar formulario de Paso 5 (Moneda/Pagos)
- [ ] Implementar formulario de Paso 6 (Datos de Ejemplo)
- [ ] Crear sistema de validación frontend (JavaScript)
- [ ] Crear sistema de validación backend (PHP)

### Fase 3: Generación de Archivos

- [ ] Implementar función para crear `config.php`
- [ ] Implementar función para crear todos los JSONs de configuración
- [ ] Implementar función para crear JSONs de datos vacíos
- [ ] Implementar función para crear datos de ejemplo (productos)
- [ ] Implementar función para crear datos de ejemplo (cupones)
- [ ] Implementar función para crear datos de ejemplo (promociones)

### Fase 4: Configuración del Sistema

- [ ] Implementar creación de usuario administrador
- [ ] Implementar creación de estructura de directorios
- [ ] Implementar configuración de permisos
- [ ] Implementar creación de archivos `.htaccess`
- [ ] Implementar generación de claves de seguridad

### Fase 5: Interfaz y UX

- [ ] Diseñar interfaz del wizard
- [ ] Implementar indicador de progreso
- [ ] Crear animaciones y transiciones
- [ ] Implementar mensajes de error/éxito
- [ ] Implementar barra de progreso en instalación

### Fase 6: Finalización

- [ ] Implementar página de resumen
- [ ] Implementar proceso de instalación con feedback
- [ ] Implementar página de éxito
- [ ] Implementar auto-eliminación del instalador
- [ ] Crear checklist de seguridad post-instalación

### Fase 7: Testing y Documentación

- [ ] Probar instalación en entorno de desarrollo
- [ ] Probar instalación en entorno de testing
- [ ] Probar todos los escenarios de validación
- [ ] Probar reinstalación y auto-eliminación
- [ ] Documentar proceso de instalación
- [ ] Crear guía de troubleshooting

---

## 🚨 Reglas Críticas del Proyecto

### ✅ CUMPLIMIENTO OBLIGATORIO

- **TODO en español**: Código, comentarios, variables, UI, mensajes
- **NO usar alert/confirm**: Usar modal custom de `app/includes/admin/modal.php`
- **NO hardcodear paths**: Usar constantes detectadas automáticamente
- **CSP Compliance**: Todos los scripts inline deben tener `nonce="<?= csp_nonce() ?>"`
- **Event Delegation**: Usar `data-action` en lugar de `onclick/onchange/onsubmit`
- **Auto-destrucción**: El instalador DEBE poder auto-eliminarse de forma segura

### ⚠️ Consideraciones de Seguridad

1. **Validar TODOS los inputs del usuario**
2. **Nunca confiar en datos del frontend**
3. **Usar prepared statements (aunque no hay DB, aplicar principio)**
4. **Generar claves seguras con `random_bytes()`**
5. **Hash de contraseñas con Argon2ID**
6. **Permisos restrictivos en archivos sensibles**
7. **Logging de todas las acciones críticas**

---

## 📚 Referencias

### Archivos Clave a Revisar

- `app/config/config.example.php` - Template de configuración
- `app/includes/functions.php` - Función `get_default_json_structure()`
- `app/includes/security.php` - Funciones de seguridad
- `public_html/install/installer.php` - Instalador actual (base)

### Documentación del Proyecto

- `CLAUDE.md` - Reglas críticas del proyecto
- `app/includes/admin/MODAL_GUIDELINES.md` - Guía de uso de modales
- `.github/DEPLOY.md` - Proceso de deployment

---

## ⏱️ Estimación de Complejidad

### Complejidad Total: **ALTA**

- **Líneas de código estimadas**: 1500-2000 líneas
- **Archivos a crear/modificar**: 2-3 archivos
- **Funciones a implementar**: ~20 funciones
- **Archivos JSON a crear**: 28 archivos

### Desglose por Componente

| Componente | Complejidad | Prioridad |
|------------|-------------|-----------|
| Wizard UI | Media | Alta |
| Validación | Alta | Crítica |
| Generación de JSONs | Alta | Crítica |
| Datos de Ejemplo | Media | Media |
| Auto-eliminación | Baja | Alta |
| Testing | Media | Alta |

---

## 🎯 Criterios de Éxito

### Funcionales

- ✅ El instalador completa la instalación sin errores
- ✅ Todos los archivos JSON se crean correctamente
- ✅ El usuario admin puede hacer login después de instalar
- ✅ Los datos de ejemplo aparecen correctamente en el panel admin
- ✅ El instalador se auto-elimina exitosamente

### No Funcionales

- ✅ Interfaz intuitiva y fácil de usar
- ✅ Mensajes de error claros y útiles
- ✅ Responsive en móviles y tablets
- ✅ Cumple todas las reglas del proyecto (español, CSP, modales)
- ✅ Código limpio y bien documentado

---

## 📝 Notas Adicionales

### Mejoras Futuras (Post-MVP)

1. **Importación de configuración**: Permitir importar configuración desde JSON
2. **Verificación de requisitos**: PHP version, extensiones, permisos
3. **Backup automático**: Crear backup antes de reinstalación
4. **Setup wizard post-instalación**: Guía paso a paso en primer acceso

### Limitaciones Conocidas

- El instalador NO puede cambiar permisos si no tiene privilegios
- La auto-eliminación puede fallar si hay problemas de permisos
- La detección de entorno depende de paths específicos

---

## ✅ Conclusión

Este plan proporciona una guía detallada y completa para implementar un instalador profesional que cumpla con todas las reglas del proyecto y proporcione una experiencia de instalación excelente para el usuario.

**Próximos Pasos**:
1. Revisar y aprobar este plan
2. Comenzar implementación por fases
3. Testing exhaustivo en cada fase
4. Deploy a producción

---

**Fin del Documento**
