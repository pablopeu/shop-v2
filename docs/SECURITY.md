# Guía de Seguridad - Shop V2

Documentación completa de las prácticas y sistemas de seguridad implementados.

**Última actualización**: 2025-12-08

---

## 📑 Tabla de Contenidos

- [Principios de Seguridad](#principios-de-seguridad)
- [Arquitectura Segura](#arquitectura-segura)
- [Content Security Policy (CSP)](#content-security-policy-csp)
- [CSRF Protection](#csrf-protection)
- [Session Security](#session-security)
- [Input Validation](#input-validation)
- [Output Sanitization](#output-sanitization)
- [File Upload Security](#file-upload-security)
- [API Security](#api-security)
- [Webhook Security](#webhook-security)
- [Rate Limiting](#rate-limiting)
- [Security Headers](#security-headers)
- [Password Security](#password-security)
- [Prevención de Vulnerabilidades](#prevención-de-vulnerabilidades)
- [Auditoría y Logging](#auditoría-y-logging)
- [Security Checklist](#security-checklist)

---

## Principios de Seguridad

### Defense in Depth

El sistema implementa múltiples capas de seguridad:

```
┌─────────────────────────────────────┐
│  1. Arquitectura (código privado)  │
├─────────────────────────────────────┤
│  2. Authentication (sesiones)       │
├─────────────────────────────────────┤
│  3. CSRF Tokens                     │
├─────────────────────────────────────┤
│  4. Input Validation                │
├─────────────────────────────────────┤
│  5. Output Sanitization             │
├─────────────────────────────────────┤
│  6. CSP Headers                     │
├─────────────────────────────────────┤
│  7. Rate Limiting                   │
└─────────────────────────────────────┘
```

### Least Privilege

- Código privado inaccesible vía HTTP
- Sesiones con permisos mínimos
- Admin panel separado del frontend

### Fail Secure

- Errores NO revelan información sensible
- Acceso denegado por defecto
- Validación estricta de entrada

---

## Arquitectura Segura

### Separación de Código

**Principio fundamental**: TODO el código sensible está fuera de `public_html/`

```
shop-v2/
├── app/              # PRIVADO (inaccesible vía HTTP)
│   ├── config/       # Configuración sensible
│   ├── includes/     # Funciones del sistema
│   ├── pages/        # Lógica de negocio
│   └── data/         # Datos JSON
│
└── public_html/      # PÚBLICO (web root)
    ├── index.php     # Punto de entrada
    ├── admin/        # Admin panel
    ├── assets/       # CSS, JS, imágenes
    └── uploads/      # Archivos subidos
```

**Ventajas**:
- ✅ Config con credenciales inaccesible
- ✅ Lógica de negocio protegida
- ✅ Datos JSON no descargables
- ✅ Includes PHP no ejecutables directamente

### Direct Access Protection

Todos los archivos en `app/` incluyen:

```php
<?php
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}
?>
```

Solo los 4 entry points definen `APP_ENTRY_POINT`:
1. `public_html/index.php`
2. `public_html/admin/index.php`
3. `public_html/admin/login.php`
4. `public_html/webhook.php`

### .htaccess Protection

```apache
# Proteger directorios sensibles
<Directory "/home/pablo/shop-v2/app">
    Require all denied
</Directory>

# Bloquear acceso a archivos sensibles
<FilesMatch "\\.(json|log|txt)$">
    Require all denied
</FilesMatch>
```

---

## Content Security Policy (CSP)

### Configuración Actual

```php
// En app/includes/security.php
$nonce = $_SESSION['csp_nonce'];

header("Content-Security-Policy:
    default-src 'self';
    script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'
        https://cdnjs.cloudflare.com
        https://sdk.mercadopago.com;
    style-src 'self' 'unsafe-inline'
        https://cdnjs.cloudflare.com;
    img-src 'self' data: https:;
    font-src 'self' https://cdnjs.cloudflare.com;
    connect-src 'self' https://api.mercadopago.com;
    frame-src https://www.mercadopago.com;
");
```

### Explicación de Directivas

| Directiva | Valor | Propósito |
|-----------|-------|-----------|
| `default-src` | `'self'` | Por defecto solo mismo origen |
| `script-src` | `'self' 'nonce-{nonce}'` | Scripts solo con nonce |
| `script-src` | `'unsafe-eval'` | Permite `eval()` (MercadoPago) |
| `style-src` | `'unsafe-inline'` | Permite estilos inline |
| `img-src` | `https:` | Imágenes de cualquier HTTPS |
| `connect-src` | URLs específicas | AJAX solo a APIs permitidas |
| `frame-src` | MercadoPago | iframes solo de pago |

### Uso de Nonces

**Generación** (una vez por sesión):

```php
// En security.php
if (!isset($_SESSION['csp_nonce'])) {
    $_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
}

function csp_nonce() {
    return $_SESSION['csp_nonce'];
}
```

**Uso en HTML**:

```html
<!-- ✅ CORRECTO: Script inline con nonce -->
<script nonce="<?= csp_nonce() ?>">
    console.log('Permitido por CSP');
</script>

<!-- ✅ CORRECTO: Style inline con nonce -->
<style nonce="<?= csp_nonce() ?>">
    .class { color: red; }
</style>

<!-- ❌ BLOQUEADO: Sin nonce -->
<script>
    console.log('Bloqueado por CSP');
</script>
```

### Event Delegation (CSP Compatible)

**Problema**: CSP bloquea `onclick`, `onchange`, etc.

```html
<!-- ❌ BLOQUEADO POR CSP -->
<button onclick="myFunction()">Click</button>
```

**Solución**: Event delegation con `data-action`

```html
<!-- ✅ PERMITIDO -->
<button data-action="myFunction">Click</button>

<script nonce="<?= csp_nonce() ?>">
    function myFunction(event, element, params) {
        console.log('Función ejecutada');
    }

    window.myFunction = myFunction;
</script>

<script nonce="<?= csp_nonce() ?>" src="<?= url('/assets/js/event-handlers.js') ?>"></script>
```

---

## CSRF Protection

### Implementación

**Generación de Token**:

```php
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

**Validación de Token**:

```php
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
```

### Uso en Formularios

```php
<?php
$csrf_token = generate_csrf_token();
?>

<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

    <!-- Campos del formulario -->

    <button type="submit">Guardar</button>
</form>
```

**Validación en Backend**:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token inválido');
    }

    // Procesar formulario
}
```

### CSRF en AJAX

```javascript
fetch('<?= url('/api/save.php') ?>', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= generate_csrf_token() ?>'
    },
    body: JSON.stringify(data)
});
```

**Validación en API**:

```php
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!validate_csrf_token($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token inválido']);
    exit;
}
```

---

## Session Security

### Configuración Segura

```php
// En security.php
session_set_cookie_params([
    'lifetime' => 0,              // Expira al cerrar navegador
    'path' => '/',
    'domain' => '',
    'secure' => true,             // Solo HTTPS
    'httponly' => true,           // No accesible vía JavaScript
    'samesite' => 'Lax'           // CSRF protection
]);

session_name('SHOP_SESSION');
session_start();

// Regenerar ID periódicamente
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {  // 30 min
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
```

### Session Timeout

```php
function check_session_timeout($max_lifetime = 3600) {
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > $max_lifetime)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}
```

**Uso**:

```php
// En cada página protegida
if (!check_session_timeout(3600)) {  // 1 hora
    redirect(url('/admin/login.php?timeout=1'));
}
```

### Session Hijacking Prevention

```php
// Verificar IP y User-Agent
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} else {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] ||
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        // Posible hijacking
        session_unset();
        session_destroy();
        die('Sesión inválida');
    }
}
```

---

## Input Validation

### Sanitización de Input

```php
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
```

**Uso**:

```php
$username = sanitize_input($_POST['username'] ?? '');
$email = sanitize_input($_POST['email'] ?? '');
```

### Validación de Email

```php
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
```

### Validación de Números

```php
function validate_number($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }

    if ($min !== null && $value < $min) {
        return false;
    }

    if ($max !== null && $value > $max) {
        return false;
    }

    return true;
}
```

### Whitelist Validation

```php
function validate_slug($slug) {
    // Solo letras, números, guiones
    return preg_match('/^[a-z0-9\-]+$/', $slug);
}

function validate_status($status) {
    $allowed = ['pendiente', 'cobrada', 'rechazada', 'cancelada'];
    return in_array($status, $allowed);
}
```

---

## Output Sanitization

### Prevención de XSS

**Siempre sanitizar output**:

```php
// ✅ CORRECTO
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// ❌ PELIGROSO
echo $user_input;  // XSS vulnerability
```

### Context-Aware Encoding

**En HTML**:
```php
<div><?= htmlspecialchars($text) ?></div>
```

**En atributos HTML**:
```php
<input value="<?= htmlspecialchars($value, ENT_QUOTES) ?>">
```

**En JavaScript**:
```javascript
const userInput = <?= json_encode($input, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
```

**En URLs**:
```php
<a href="<?= url('/producto/' . urlencode($slug)) ?>">Link</a>
```

### Rich Text (si se implementa)

```php
// Usar librería HTML Purifier
$config = HTMLPurifier_Config::createDefault();
$purifier = new HTMLPurifier($config);
$clean_html = $purifier->purify($user_html);
```

---

## File Upload Security

### Validación de Uploads

```php
function validate_upload($file, $allowed_types, $max_size) {
    // Verificar que se subió sin errores
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir archivo'];
    }

    // Verificar tamaño
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Archivo muy grande'];
    }

    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
    }

    // Verificar extensión
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed_exts)) {
        return ['success' => false, 'error' => 'Extensión no permitida'];
    }

    return ['success' => true];
}
```

### Subida Segura

```php
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024;  // 5 MB

$validation = validate_upload($_FILES['image'], $allowed_types, $max_size);

if (!$validation['success']) {
    die($validation['error']);
}

// Generar nombre único
$unique_name = bin2hex(random_bytes(16)) . '.' . $ext;

// Mover a directorio seguro
$upload_dir = PUBLIC_PATH . '/uploads/products/';
$target_path = $upload_dir . $unique_name;

if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
    // Archivo subido exitosamente
    chmod($target_path, 0644);
} else {
    die('Error al mover archivo');
}
```

### Prevención de Directory Traversal

```php
function secure_file_path($filename) {
    // Remover path components
    $filename = basename($filename);

    // Remover caracteres peligrosos
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);

    return $filename;
}
```

---

## API Security

### Authentication

```php
function require_api_key() {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $valid_key = hash('sha256', SECRET_KEY . 'api');

    if (!hash_equals($valid_key, $api_key)) {
        http_response_code(401);
        echo json_encode(['error' => 'API key inválida']);
        exit;
    }
}
```

### Rate Limiting

```php
function check_api_rate_limit($identifier, $max_requests = 100, $window = 60) {
    $cache_key = "api_ratelimit_{$identifier}";
    $cache_file = DATA_PATH . "/cache/{$cache_key}.json";

    $data = file_exists($cache_file)
        ? json_decode(file_get_contents($cache_file), true)
        : ['count' => 0, 'reset_at' => time() + $window];

    // Verificar si venció la ventana
    if (time() > $data['reset_at']) {
        $data = ['count' => 0, 'reset_at' => time() + $window];
    }

    $data['count']++;

    if ($data['count'] > $max_requests) {
        http_response_code(429);
        echo json_encode([
            'error' => 'Too many requests',
            'retry_after' => $data['reset_at'] - time()
        ]);
        exit;
    }

    file_put_contents($cache_file, json_encode($data));
}
```

### CORS (si es necesario)

```php
// Permitir solo orígenes específicos
$allowed_origins = [
    'https://example.com',
    'https://app.example.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
}
```

---

## Webhook Security

### Validación de Firma HMAC

```php
function validate_webhook_signature($signature, $payload, $secret) {
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
```

**Uso en webhook.php**:

```php
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$secret = MERCADOPAGO_WEBHOOK_SECRET;

if (!validate_webhook_signature($signature, $payload, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

### Validación de IP

```php
function is_mercadopago_ip($ip) {
    $allowed_ips = [
        '209.225.49.0/24',
        '216.33.197.0/24',
        '216.33.196.0/24'
        // ... más rangos de MercadoPago
    ];

    foreach ($allowed_ips as $range) {
        if (ip_in_range($ip, $range)) {
            return true;
        }
    }

    return false;
}

function ip_in_range($ip, $range) {
    list($subnet, $mask) = explode('/', $range);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = ~((1 << (32 - $mask)) - 1);
    return ($ip_long & $mask) === ($subnet_long & $mask);
}
```

### Timestamp Validation

```php
function validate_timestamp($timestamp, $max_age = 300) {
    $now = time();
    $diff = abs($now - $timestamp);

    return $diff <= $max_age;  // 5 minutos
}
```

---

## Rate Limiting

### Implementación

```php
function check_rate_limit($identifier, $max_attempts, $window_seconds) {
    $cache_key = "ratelimit_{$identifier}";
    $cache_file = DATA_PATH . "/cache/{$cache_key}.json";

    $data = file_exists($cache_file)
        ? json_decode(file_get_contents($cache_file), true)
        : ['attempts' => 0, 'reset_at' => time() + $window_seconds];

    if (time() > $data['reset_at']) {
        $data = ['attempts' => 0, 'reset_at' => time() + $window_seconds];
    }

    $data['attempts']++;

    if ($data['attempts'] > $max_attempts) {
        http_response_code(429);
        $retry_after = $data['reset_at'] - time();
        header("Retry-After: $retry_after");
        die("Too many requests. Try again in {$retry_after} seconds.");
    }

    file_put_contents($cache_file, json_encode($data));
}
```

**Uso**:

```php
// Login: 5 intentos por 15 minutos
check_rate_limit($_SERVER['REMOTE_ADDR'], 5, 900);

// Webhook: 100 requests por 60 segundos
check_rate_limit('webhook_' . $_SERVER['REMOTE_ADDR'], 100, 60);

// API: 1000 requests por hora
check_rate_limit('api_' . $api_key, 1000, 3600);
```

---

## Security Headers

### Headers Implementados

```php
// En security.php
function set_security_headers() {
    // CSP (ver sección dedicada)
    header("Content-Security-Policy: ...");

    // Prevenir MIME sniffing
    header("X-Content-Type-Options: nosniff");

    // Prevenir clickjacking
    header("X-Frame-Options: SAMEORIGIN");

    // XSS Protection (legacy, CSP es mejor)
    header("X-XSS-Protection: 1; mode=block");

    // HSTS (forzar HTTPS)
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Permissions Policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}
```

---

## Password Security

### Hashing

```php
// Crear hash
$password = 'password123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verificar
$valid = password_verify($password, $hash);

// Re-hash si necesario (actualizar algorithm)
if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    // Guardar $new_hash en base de datos
}
```

### Validación de Fortaleza

```php
function validate_password_strength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Mínimo 8 caracteres';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Al menos una mayúscula';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Al menos una minúscula';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Al menos un número';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Al menos un símbolo especial';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
```

---

## Prevención de Vulnerabilidades

### SQL Injection (si se migra a SQL)

```php
// ✅ CORRECTO: Prepared statements
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);

// ❌ PELIGROSO: Concatenación
$query = "SELECT * FROM users WHERE email = '$email'";  // SQL Injection!
```

### XSS (Cross-Site Scripting)

```php
// ✅ CORRECTO
echo htmlspecialchars($user_input);

// ❌ PELIGROSO
echo $user_input;  // XSS!
```

### CSRF (Cross-Site Request Forgery)

```php
// ✅ CORRECTO: Usar tokens
if (!validate_csrf_token($_POST['csrf_token'])) {
    die('CSRF attack detected');
}

// ❌ PELIGROSO: Sin validación
```

### Path Traversal

```php
// ✅ CORRECTO
$filename = basename($_GET['file']);
$filepath = UPLOAD_DIR . '/' . $filename;

// ❌ PELIGROSO
$filepath = UPLOAD_DIR . '/' . $_GET['file'];
// Atacante puede usar: ?file=../../../etc/passwd
```

### Command Injection

```php
// ✅ CORRECTO: Escapar argumentos
$filename = escapeshellarg($user_input);
$output = shell_exec("ls $filename");

// ❌ PELIGROSO
$output = shell_exec("ls $user_input");
// Atacante puede usar: file.txt; rm -rf /
```

---

## Auditoría y Logging

### Eventos a Logear

```php
function log_security_event($type, $details) {
    $log_file = DATA_PATH . '/security_log.json';

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => $type,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'details' => $details
    ];

    $logs = file_exists($log_file)
        ? json_decode(file_get_contents($log_file), true)
        : [];

    $logs[] = $log_entry;

    // Mantener últimos 1000 eventos
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }

    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
}
```

**Uso**:

```php
// Login exitoso
log_security_event('login_success', [
    'username' => $username
]);

// Login fallido
log_security_event('login_failed', [
    'username' => $username,
    'reason' => 'invalid_password'
]);

// CSRF detectado
log_security_event('csrf_attack', [
    'endpoint' => $_SERVER['REQUEST_URI']
]);

// Rate limit excedido
log_security_event('rate_limit_exceeded', [
    'identifier' => $identifier,
    'endpoint' => $_SERVER['REQUEST_URI']
]);
```

---

## Security Checklist

### Pre-Deploy

- [ ] Todos los archivos en `app/` tienen security check
- [ ] Passwords hasheadas con `password_hash()`
- [ ] CSRF tokens en todos los formularios
- [ ] Output sanitizado con `htmlspecialchars()`
- [ ] Input validado con whitelist
- [ ] CSP configurado con nonces
- [ ] Sessions seguras (httponly, secure, samesite)
- [ ] Rate limiting en endpoints críticos
- [ ] Webhooks validan firma HMAC
- [ ] File uploads validados (tipo, tamaño, extensión)
- [ ] Security headers configurados
- [ ] `config.php` en `.gitignore`
- [ ] Sin credenciales hardcoded
- [ ] Logs de seguridad habilitados

### Post-Deploy

- [ ] HTTPS funcionando (certificado válido)
- [ ] Security headers presentes (verificar con securityheaders.com)
- [ ] CSP sin errores en console
- [ ] Admin panel solo accesible con auth
- [ ] Timeout de sesión funciona
- [ ] Rate limiting funciona
- [ ] Webhooks rechazan firmas inválidas
- [ ] File uploads solo permiten tipos válidos
- [ ] Logs de seguridad registrando eventos

### Auditoría Mensual

- [ ] Revisar logs de seguridad
- [ ] Verificar intentos de login fallidos
- [ ] Revisar eventos de rate limiting
- [ ] Actualizar dependencias (si aplica)
- [ ] Revisar permisos de archivos
- [ ] Verificar que `config.php` no está en repo
- [ ] Test de penetración básico
- [ ] Verificar backups de datos

---

*Última actualización: 2025-12-08*
