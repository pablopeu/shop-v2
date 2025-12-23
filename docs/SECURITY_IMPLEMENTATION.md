# Guía de Implementación de Seguridad - Shop V2

**Documento Técnico**
**Versión:** 2.0
**Fecha:** 15 de Diciembre de 2025
**Estado:** CONFIDENCIAL

---

## Índice

1. [Arquitectura de Seguridad](#arquitectura-de-seguridad)
2. [Password Policy](#password-policy)
3. [Rate Limiting](#rate-limiting)
4. [CSRF Protection](#csrf-protection)
5. [Log Rotation](#log-rotation)
6. [Content Security Policy](#content-security-policy)
7. [Checklist de Seguridad](#checklist-de-seguridad)

---

## Arquitectura de Seguridad

### Principio Fundamental: Código Privado Fuera de Web Root

```
shop-v2/
├── app/                    # PRIVADO - Inaccesible vía HTTP
│   ├── config/             # Configuraciones sensibles
│   ├── includes/           # Funciones del sistema
│   ├── pages/              # Vistas/Controladores
│   └── data/               # JSON data files
│
└── public_html/            # PÚBLICO - Web root
    ├── index.php           # Entry point 1
    ├── webhook.php         # Entry point 2
    ├── admin/
    │   ├── index.php       # Entry point 3
    │   └── login.php       # Entry point 4
    └── api/
        └── index.php       # Entry point 5
```

### Entry Points

El sistema tiene **exactamente 5 entry points**:

1. `/public_html/index.php` - Frontend
2. `/public_html/webhook.php` - Webhooks MercadoPago
3. `/public_html/admin/index.php` - Panel admin
4. `/public_html/admin/login.php` - Login admin
5. `/public_html/api/index.php` - API router

Todos los demás archivos en `/app/` DEBEN incluir el check de seguridad:

```php
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}
```

---

## Password Policy

### Implementación

**Ubicación:** `app/includes/security.php`

### Requisitos de Contraseñas

- **Longitud mínima:** 12 caracteres
- **Longitud máxima:** 128 caracteres (prevención de DoS)
- **Complejidad:**
  - Al menos una letra mayúscula
  - Al menos una letra minúscula
  - Al menos un número
  - Al menos un símbolo especial

### Funciones Disponibles

#### `validate_password_strength($password)`

Valida si una contraseña cumple con la política de seguridad.

**Uso:**

```php
$password = 'MiPassword123!';
$validation = validate_password_strength($password);

if (!$validation['valid']) {
    // Mostrar errores
    foreach ($validation['errors'] as $error) {
        echo "❌ $error\n";
    }
} else {
    // Contraseña válida
    echo "✅ Contraseña segura (score: {$validation['strength_score']}/100)\n";
}
```

**Retorno:**

```php
[
    'valid' => bool,                // True si cumple todos los requisitos
    'errors' => array,              // Lista de errores (vacío si valid = true)
    'strength_score' => int         // Score de 0 a 100
]
```

#### `calculate_password_strength_score($password)`

Calcula un score numérico de la fortaleza de la contraseña (0-100).

**Criterios de puntuación:**

- **Longitud:** hasta 40 puntos (2 puntos por carácter)
- **Mayúsculas:** 15 puntos
- **Minúsculas:** 15 puntos
- **Números:** 15 puntos
- **Símbolos:** 15 puntos
- **Bonus complejidad:** 10 puntos (si tiene >10 caracteres únicos)

#### `get_password_strength_level($score)`

Convierte el score en un nivel descriptivo.

**Niveles:**

| Score | Nivel | Color | Descripción |
|-------|-------|-------|-------------|
| 0-29 | Muy débil | 🔴 Rojo | Fácil de adivinar |
| 30-49 | Débil | 🟠 Naranja | Podría ser más segura |
| 50-69 | Aceptable | 🟡 Amarillo | Seguridad media |
| 70-84 | Fuerte | 🟢 Verde | Buena contraseña |
| 85-100 | Muy fuerte | 🟢 Verde brillante | Excelente |

**Uso:**

```php
$score = calculate_password_strength_score('MiPassword123!');
$level = get_password_strength_level($score);

echo "{$level['level']}: {$level['message']}";
// Output: "Fuerte: Buena contraseña"
```

### Integración en el Sistema

La validación de password policy está integrada en:

1. **`create_admin_user()`** - Creación de usuarios
2. **`change_admin_password()`** - Cambio de contraseña

**Ejemplo de error:**

```php
$result = create_admin_user('admin', 'weak', 'admin@example.com');

if (!$result['success']) {
    // $result['errors'] contendrá:
    // [
    //   "La contraseña debe tener al menos 12 caracteres",
    //   "Debe contener al menos un símbolo especial"
    // ]
}
```

---

## Rate Limiting

### Función: `api_rate_limit($max_requests, $window_seconds)`

**Ubicación:** `app/includes/rate_limit.php`

**Uso en endpoints:**

```php
// Permitir 10 requests por minuto
api_rate_limit(10, 60);

// Permitir 5 requests cada 10 minutos
api_rate_limit(5, 600);
```

### Endpoints con Rate Limiting Implementado

| Endpoint | Límite | Ventana | Propósito |
|----------|--------|---------|-----------|
| `admin/login.php` | 5 | 15 min | Prevenir brute force |
| `webhook.php` | 100 | 1 min | Prevenir abuse |
| `api/validate_coupon.php` | 20 | 1 min | Prevenir enumeración |
| `api/sync_cart.php` | 30 | 1 min | Prevenir abuse |
| `api/cancel_order.php` | 10 | 1 min | Prevenir abuse |
| `api/get_products.php` | 30 | 1 min | Prevenir scraping |
| `api/get_promotion.php` | 30 | 1 min | Prevenir abuse |
| `api/get_shared_wishlist.php` | 60 | 1 min | Uso normal |
| `api/send-test-email.php` | 5 | 10 min | Prevenir spam |
| `api/send-telegram-test.php` | 5 | 10 min | Prevenir spam |

### Almacenamiento

Los límites se almacenan en archivos JSON en `app/data/rate_limits/`:

```
app/data/rate_limits/
├── {IP}_{identifier}.json
└── ...
```

Cada archivo contiene:

```json
{
    "count": 3,
    "first_request": 1702648200,
    "last_request": 1702648230
}
```

### Respuesta cuando se excede el límite

```
HTTP/1.1 429 Too Many Requests
Content-Type: application/json

{
    "error": "Rate limit exceeded",
    "retry_after": 45
}
```

---

## CSRF Protection

### Generación de Token

**Función:** `generate_csrf_token()`

```php
// En el formulario
$csrf_token = generate_csrf_token();

echo '<input type="hidden" name="csrf_token" value="' . $csrf_token . '">';
```

### Validación de Token

**Función:** `validate_csrf_token($token)`

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token inválido');
    }

    // Procesar formulario
}
```

### Expiración

Los tokens CSRF expiran después de **1 hora**.

### Origin Validation

Para APIs, también se valida el origen de las peticiones:

```php
$allowed_origins = [
    'https://peu.net',
    'http://localhost:8000',
    'http://127.0.0.1:8000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

// Validar origin...
```

**Implementado en:**

- `api/cancel_order.php`

---

## Log Rotation

### Sistema Automático de Rotación

**Ubicación:** `app/includes/log_rotation.php`

### Funciones Principales

#### `rotate_log_if_needed($log_file, $max_size_mb, $keep_rotations)`

Rota un log si excede el tamaño máximo.

**Parámetros:**

- `$log_file`: Ruta completa al log
- `$max_size_mb`: Tamaño máximo en MB (default: 10)
- `$keep_rotations`: Número de rotaciones a mantener (default: 5)

**Ejemplo:**

```php
// Rotar si excede 20 MB, mantener 10 rotaciones
rotate_log_if_needed('/path/to/app.log', 20, 10);
```

**Resultado:**

```
app.log           # Archivo actual
app.log.1.gz      # Primera rotación (comprimida)
app.log.2.gz      # Segunda rotación
...
app.log.10.gz     # Décima rotación (la más antigua se elimina al rotar)
```

#### `archive_rotated_log($log_file)`

Comprime un log rotado con gzip (máxima compresión).

#### `cleanup_old_logs($logs_dir, $days_to_keep)`

Elimina logs archivados más antiguos que X días (default: 90).

#### `rotate_all_system_logs()`

Rota todos los logs del sistema automáticamente.

**Logs gestionados:**

- `security.log` (10 MB max, 5 rotaciones)
- `admin_actions.log` (10 MB max, 5 rotaciones)
- `mp_logs.json` (20 MB max, 10 rotaciones)
- `webhook_log.json` (20 MB max, 10 rotaciones)
- `errors.log` (50 MB max, 10 rotaciones)

### Script de Rotación Manual

**Ubicación:** `public_html/scripts/rotate-logs.php`

**Ejecutar manualmente:**

```bash
cd /home/pablo/shop-v2
php public_html/scripts/rotate-logs.php
```

**Salida:**

```
=== Rotación de Logs del Sistema ===
Fecha: 2025-12-15 14:30:00

Resultados:
  - Logs rotados: 2
  - Logs archivados (comprimidos): 2
  - Logs antiguos eliminados: 5

✅ Rotación completada exitosamente
```

### Crontab (Automatización)

Ejecutar diariamente a las 3 AM:

```bash
crontab -e
```

Agregar:

```cron
0 3 * * * cd /home/pablo/shop-v2 && php public_html/scripts/rotate-logs.php >> /tmp/log-rotation.log 2>&1
```

### Anonimización de IPs (GDPR Compliance)

**Función:** `anonymize_ip_for_log($ip)`

Las IPs en logs se hashean con SHA-256 antes de guardar:

```php
$ip = '192.168.1.100';
$hashed = anonymize_ip_for_log($ip);
// Output: "a3f4b2c1d5e6f7a8"
```

**Función helper:** `secure_log($message, $level, $context)`

```php
secure_log('Usuario intentó acceso no autorizado', 'warning', [
    'username' => 'admin',
    'endpoint' => '/api/sensitive'
]);
```

**Resultado en log:**

```json
{
    "timestamp": "2025-12-15 14:30:00",
    "level": "warning",
    "message": "Usuario intentó acceso no autorizado",
    "ip_hash": "a3f4b2c1d5e6f7a8",
    "user_agent": "Mozilla/5.0...",
    "context": {
        "username": "admin",
        "endpoint": "/api/sensitive"
    }
}
```

---

## Content Security Policy

### CSP Implementada

**Ubicación:** `app/includes/security.php` → `set_security_headers()`

```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' 'nonce-{NONCE}' 'unsafe-eval' https://sdk.mercadopago.com;
  style-src 'self' 'unsafe-inline';
  connect-src 'self' https://api.mercadopago.com;
  frame-src https://*.mercadopago.com;
```

### Uso de Nonces

**Todos** los scripts y estilos inline DEBEN usar nonces:

```php
<!-- ❌ INCORRECTO - Bloqueado por CSP -->
<script>
    console.log('Hola');
</script>

<!-- ✅ CORRECTO -->
<script nonce="<?= csp_nonce() ?>">
    console.log('Hola');
</script>
```

### Event Delegation

**NO usar** event handlers inline:

```html
<!-- ❌ INCORRECTO -->
<button onclick="myFunction()">Click</button>

<!-- ✅ CORRECTO -->
<button data-action="myFunction">Click</button>
```

**JavaScript:**

```javascript
function myFunction(event, element, params) {
    console.log('Clicked!');
}

// Exportar para event delegation
window.myFunction = myFunction;
```

**Incluir event-handlers.js:**

```html
<script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
```

---

## Checklist de Seguridad

### Para Nuevos Endpoints

- [ ] Define `APP_ENTRY_POINT` al inicio
- [ ] Incluye bootstrap correcto
- [ ] Implementa rate limiting con `api_rate_limit()`
- [ ] Valida todos los inputs con `sanitize_input()`
- [ ] Usa CSRF tokens para operaciones state-changing
- [ ] Valida Origin header si es crítico
- [ ] Usa `read_json()` y `write_json()` para operaciones JSON
- [ ] Retorna errores genéricos al usuario (no revelar detalles internos)
- [ ] Logea eventos importantes con `secure_log()`

### Para Nuevas Páginas

- [ ] Incluye security check: `if (!defined('APP_ENTRY_POINT'))`
- [ ] Usa nonces en todos los `<script>` y `<style>` inline
- [ ] NO usa event handlers inline (onclick, onchange, etc.)
- [ ] Usa `data-action` con event delegation
- [ ] Incluye `event-handlers.js` al final
- [ ] Usa `url()` helper para todas las URLs e imágenes
- [ ] Genera y valida CSRF tokens en formularios
- [ ] Incluye modal component (`app/includes/admin/modal.php`)

### Para Autenticación

- [ ] Usa `hash_password()` con Argon2id
- [ ] Valida password policy con `validate_password_strength()`
- [ ] Implementa rate limiting en login (5 intentos/15 min)
- [ ] Regenera session ID después del login
- [ ] Verifica session timeout (1 hora)
- [ ] Logea intentos fallidos con `log_admin_action()`

### Para Logs

- [ ] Anonimiza IPs con `anonymize_ip_for_log()`
- [ ] Usa `secure_log()` para eventos de seguridad
- [ ] NO logees contraseñas, tokens, o datos sensibles
- [ ] Implementa rotación con `rotate_log_if_needed()`
- [ ] Configura cron job para rotación automática

---

## Buenas Prácticas

### 1. Defense in Depth

Implementa **múltiples capas** de seguridad:

- Entry point validation
- Rate limiting
- CSRF protection
- Input sanitization
- Origin validation
- Session timeout
- Audit logging

### 2. Principle of Least Privilege

- Código privado fuera de web root
- Permisos mínimos en archivos (640 para configs, 750 para directorios)
- Solo 5 entry points públicos

### 3. Fail Securely

- Errores genéricos al usuario
- Logging detallado interno
- Rate limiting estricto en caso de fallo

### 4. Security by Default

- CSP estricta activada por defecto
- HTTPS enforced
- Security headers completos
- Password policy aplicada automáticamente

---

## Contacto y Soporte

Para reportar vulnerabilidades o consultas de seguridad:

- **Email:** security@example.com
- **Proceso:** Responsible Disclosure
- **SLA:** Respuesta en 24 horas para críticos

---

**FIN DEL DOCUMENTO**

*Documento confidencial - Uso interno únicamente*
*Última actualización: 15 de Diciembre de 2025*
