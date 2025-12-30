# Plan de Implementación: Sistema de Instalador Mejorado V2

**Branch**: `feature/installer-system`
**Fecha**: 2025-12-17
**Autor**: Claude Code

---

## 📋 Resumen Ejecutivo

Diseño e implementación de un instalador **MINIMALISTA** para Shop V2 con **mínima fricción**. El instalador solo pide lo estrictamente necesario para arrancar el sistema (paths + usuario admin), crea TODOS los archivos JSON en blanco con estructuras válidas, y permite que el administrador complete toda la configuración desde el panel de administración.

**Filosofía**: Menos preguntas = Instalación más rápida = Mejor experiencia de usuario.

---

## 🎯 Objetivos del Proyecto

### Objetivos Principales

1. **Instalación ultra-rápida**: Solo 3 pasos (Bienvenida → Paths → Admin → Listo)
2. **Mínimos datos requeridos**: Solo paths, username, email y password del admin
3. **Generación automática de JSONs**: Crear TODOS los archivos JSON con valores por defecto mínimos/vacíos
4. **Configuración posterior desde admin**: El admin completa TODA la configuración desde el panel
5. **Auto-destrucción segura**: El instalador se elimina automáticamente después de completar

### Objetivos Secundarios

- Detección automática de paths (solo confirmar)
- Validación mínima pero efectiva
- Interfaz limpia y moderna
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

### Flujo de Instalación (SIMPLIFICADO)

```
┌─────────────────────────────────────────────────────────────┐
│                    PASO 1: Bienvenida                        │
│                                                             │
│  🔒 Shop V2 - Instalador Minimalista                        │
│                                                             │
│  ✨ Instalación en 3 simples pasos                          │
│  ✨ Configuración completa desde el panel admin             │
│  ✨ Todo listo en menos de 2 minutos                        │
│                                                             │
│  ⚠️ IMPORTANTE:                                             │
│  • Código privado FUERA de public_html (seguridad)          │
│  • Eliminar carpeta /install/ después de instalar           │
│                                                             │
│  [Botón: Comenzar Instalación →]                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│              PASO 2: Configuración de Rutas                  │
│                                                             │
│  📁 Rutas detectadas automáticamente:                       │
│                                                             │
│  Ruta Aplicación:  [/home/usuario/shop-v2/app]             │
│  Ruta Pública:     [/home/usuario/shop-v2/public_html]     │
│  URL del Sitio:    [http://localhost]                      │
│  Base Path:        [/shopv2]                               │
│                                                             │
│  💡 Puedes editarlas si es necesario                        │
│                                                             │
│  [← Atrás]  [Siguiente →]                                   │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         PASO 3: Usuario Administrador                        │
│                                                             │
│  👤 Crea tu usuario administrador:                          │
│                                                             │
│  Usuario:           [admin]                                 │
│  Email:             [admin@ejemplo.com]                     │
│  Contraseña:        [••••••••]                              │
│  Confirmar:         [••••••••]                              │
│                                                             │
│  ⚠️ Mínimo 8 caracteres                                     │
│                                                             │
│  [← Atrás]  [Instalar Sistema →]                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│               PASO 4: Instalando Sistema...                  │
│                                                             │
│  [████████████████████] 100%                                │
│                                                             │
│  ✅ Creando estructura de directorios                       │
│  ✅ Generando config.php con clave secreta                  │
│  ✅ Creando 28 archivos JSON con valores por defecto        │
│  ✅ Creando usuario administrador                           │
│  ✅ Configurando permisos de seguridad                      │
│  ✅ Creando archivos .htaccess                              │
│                                                             │
│  ⏳ Espera un momento...                                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  PASO 5: ¡Listo! 🎉                         │
│                                                             │
│  ✅ Sistema instalado correctamente                         │
│                                                             │
│  📋 Próximos pasos:                                         │
│  1️⃣ Accede al panel de administración                      │
│  2️⃣ Configura tu sitio (nombre, logo, etc.)                │
│  3️⃣ Agrega productos                                        │
│  4️⃣ Configura MercadoPago (opcional)                       │
│                                                             │
│  🗑️ ELIMINAR INSTALADOR                                    │
│  [Botón: Auto-eliminar y Ir al Admin →]                    │
│                                                             │
│  O accede manualmente:                                      │
│  → Login Admin | → Ver Sitio                                │
└─────────────────────────────────────────────────────────────┘
```

**Total de pasos para el usuario**: 3 clicks + completar formulario simple = Listo en 2 minutos

---

## 📁 Archivos a Crear

### Principio de Valores por Defecto

**TODOS los archivos JSON se crean con valores MÍNIMOS y GENÉRICOS**. El administrador los completa después desde el panel.

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
    'app_name' => 'Shop V2',
    'app_url' => [URL detectada/ingresada],
    'base_path' => [base path detectado/ingresado],
    'secret_key' => [generado automáticamente - 64 chars hex],
    'csrf_token_expiry' => 3600,
    'app_path' => [path ingresado],
    'public_path' => [path ingresado],
    'maintenance_mode' => false,
    'debug' => false,
    'log_errors' => true,
];
```

### 2. Archivos JSON de Configuración (app/config/)

**Todos con valores por defecto mínimos - Se completan desde admin**

#### 2.1 `site.json` - Configuración del Sitio

```json
{
    "site_name": "Mi Tienda",
    "site_description": "Tienda en línea",
    "site_keywords": "ecommerce, tienda, productos",
    "contact_email": "[email del admin]",
    "contact_phone": "",
    "footer_text": "© 2025 Mi Tienda. Todos los derechos reservados.",
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
        "alt": "Mi Tienda"
    },
    "site_owner": "",
    "whatsapp_number": "",
    "meta_tags": {
        "og_title": "Mi Tienda",
        "og_type": "website",
        "og_url": "",
        "og_url_secure": "",
        "og_image": "",
        "og_site_name": "Mi Tienda",
        "og_description": "Tienda en línea",
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
    "from_email": "[email del admin]",
    "from_name": "Mi Tienda",
    "admin_email": "[email del admin]",
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
        "enabled": false,
        "access_token": "",
        "public_key": "",
        "webhook_secret": "[generado automáticamente - 32 chars hex]",
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
    "primary": "ARS",
    "secondary": "USD",
    "exchange_rate": 1500,
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
    "title": "Bienvenido a Mi Tienda",
    "subtitle": "Descubre nuestros productos",
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

**TODOS se crean vacíos** - El admin agrega datos desde el panel

#### 3.1 `users.json` - Usuarios del Sistema

```json
{
    "users": [
        {
            "id": "admin-[uniqid]",
            "username": "[ingresado en instalador]",
            "password": "[hash Argon2ID del password]",
            "email": "[ingresado en instalador]",
            "name": "",
            "role": "admin",
            "created_at": "[timestamp]",
            "last_login": null
        }
    ]
}
```

#### 3.2 Archivos de Datos Vacíos

**Todos estos archivos se crean vacíos con su estructura base**:

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

### 1. Validación de Inputs (MÍNIMA)

**Solo se validan 3 cosas**:

#### Validaciones de Paths

- Verificar permisos de escritura en app_path y public_path
- Validar que `app_path` esté FUERA de `public_path` (seguridad)
- Verificar que las rutas sean absolutas

#### Validaciones de Usuario Admin

- Email válido (formato básico)
- Contraseña mínimo 8 caracteres
- Confirmar contraseña (deben coincidir)
- Username no vacío y sin espacios

**Eso es todo**. Sin validaciones complejas para mantener velocidad de instalación.

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

---

## 🧪 Testing del Instalador

### Escenarios de Prueba

#### 1. Instalación Rápida (Happy Path)

- Confirmar paths detectados automáticamente
- Ingresar usuario admin básico
- Completar instalación en menos de 2 minutos
- Verificar que todos los archivos JSON se crearon

#### 2. Validación de Errores

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

### Fase 2: Formularios y Validación (SIMPLIFICADO)

- [ ] Implementar formulario de Paso 2 (Paths con detección automática)
- [ ] Implementar formulario de Paso 3 (Admin: user, email, password)
- [ ] Crear validación mínima frontend (JavaScript)
- [ ] Crear validación mínima backend (PHP)

### Fase 3: Generación de Archivos (TODO VACÍO/MÍNIMO)

- [ ] Implementar función para crear `config.php` con valores básicos
- [ ] Implementar función para crear TODOS los JSONs de configuración con valores por defecto
- [ ] Implementar función para crear TODOS los JSONs de datos vacíos
- [ ] Implementar función para crear usuario admin en users.json

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

### Complejidad Total: **MEDIA** (Simplificado)

- **Líneas de código estimadas**: 800-1200 líneas (menos de la mitad que versión compleja)
- **Archivos a crear/modificar**: 1 archivo (installer.php mejorado)
- **Funciones a implementar**: ~12 funciones
- **Archivos JSON a crear**: 28 archivos (todos con valores por defecto)
- **Tiempo de instalación usuario**: < 2 minutos

### Desglose por Componente

| Componente | Complejidad | Prioridad | Cambio vs V1 |
|------------|-------------|-----------|--------------|
| Wizard UI | Baja | Alta | Simplificado (3 pasos) |
| Validación | Baja | Media | Solo lo esencial |
| Generación de JSONs | Media | Crítica | Valores por defecto |
| Auto-eliminación | Baja | Alta | Sin cambios |
| Testing | Baja | Media | Menos escenarios |

---

## 🎯 Criterios de Éxito

### Funcionales

- ✅ Instalación completa en menos de 2 minutos
- ✅ Todos los 28 archivos JSON se crean con valores por defecto
- ✅ El usuario admin puede hacer login inmediatamente
- ✅ El sistema arranca sin errores (aunque esté "vacío")
- ✅ El instalador se auto-elimina exitosamente

### No Funcionales

- ✅ **Experiencia ultra-rápida**: Mínima fricción, máxima velocidad
- ✅ Interfaz limpia y minimalista
- ✅ Solo pide lo ESTRICTAMENTE necesario
- ✅ Responsive en móviles y tablets
- ✅ Cumple todas las reglas del proyecto (español, CSP, modales)

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
