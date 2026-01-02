# Informe de Paths Hardcodeados en Shop V2

**Fecha**: 2026-01-02
**Análisis**: Revisión exhaustiva del código para detectar violaciones de RULE 2 (NO HARDCODED PATHS)

---

## Resumen Ejecutivo

Se encontraron **12 archivos** con paths hardcodeados que violan la regla de no hardcodear paths. Los problemas se clasifican en:

- **Archivos de configuración JSON**: 2 archivos (carousel.json, site.json)
- **Archivos .htaccess**: 2 archivos (public_html/.htaccess, public_html/api/.htaccess)
- **Scripts PHP con paths absolutos**: 4 archivos (process-email-queue.php, email.php, config-rutas-sistema.php, api/process-email-queue.php)
- **Scripts PHP con dominios hardcodeados**: 4 archivos (cancel-order.php, pedido.php, config-email-queue.php, crear-preferencia-mp.php)

---

## 🔴 CRÍTICO - Archivos de Configuración JSON

### 1. app/config/carousel.json

**Problema**: Múltiples paths con `/shopv2/` hardcodeado en images y links de productos

**Líneas afectadas**: 4, 7, 12, 15, 28, 31, 36, 39, 52, 55, 60, 63

**Ejemplos**:
```json
"image": "/shopv2/assets/uploads/products/prod-692b859b18d28-210574f2/692b859b18de5_1764459931.webp"
"link": "/shopv2/producto/corta-pizza-star-wars"
```

**Solución requerida**:
- Los paths de imágenes deberían ser relativos sin `/shopv2/`
- Ejemplo: `"/assets/uploads/products/..."` en lugar de `"/shopv2/assets/uploads/..."`
- Los links de productos deberían ser: `"/producto/corta-pizza-star-wars"` sin `/shopv2/`

**Impacto**: Alto - Afecta todas las imágenes del carrusel en producción

---

### 2. app/config/site.json

**Problema**: Paths con `/shopv2/` en meta tags de Open Graph

**Líneas afectadas**: 30, 31, 32

**Ejemplos**:
```json
"og_url": "/shopv2/",
"og_url_secure": "/shopv2/",
"og_image": "/shopv2/assets/uploads/og-images/og_image_1764553168.jpg"
```

**Solución requerida**:
- og_url debería ser relativa: `"/"`
- og_image debería ser: `"/assets/uploads/og-images/..."`

**Impacto**: Medio - Afecta compartir en redes sociales

---

## 🟡 IMPORTANTE - Archivos .htaccess

### 3. public_html/.htaccess

**Problema**: RewriteBase hardcodeado

**Línea afectada**: 12

**Código actual**:
```apache
RewriteBase /shopv2/
```

**Solución requerida**:
- Este archivo requiere análisis cuidadoso ya que .htaccess usa paths del servidor web
- Podría ser necesario mantener este path o usar variables de entorno

**Impacto**: Alto - Afecta todo el routing del frontend

---

### 4. public_html/api/.htaccess

**Problema**: RewriteBase hardcodeado

**Línea afectada**: 6

**Código actual**:
```apache
RewriteBase /shopv2/api/
```

**Solución requerida**:
- Similar al anterior, requiere análisis para determinar si es inevitable
- Evaluar si es posible usar path dinámico

**Impacto**: Alto - Afecta routing de API

---

## 🟠 MEDIO - Scripts PHP con Paths Absolutos

### 5. app/scripts/process-email-queue.php

**Problema**: Path absoluto del servidor de producción hardcodeado

**Línea afectada**: 31

**Código actual**:
```php
$app_path = '/home2/uv0023/shop-v2-app/';
```

**Solución requerida**:
- Este es un fallback cuando no encuentra el path relativo
- Debería usar una constante de entorno o archivo de configuración
- O mejor aún, no asumir un path específico del servidor

**Impacto**: Medio - Solo afecta cuando el script se ejecuta desde ubicaciones inesperadas

---

### 6. app/includes/email.php

**Problema**: Path hardcodeado para credenciales

**Línea afectada**: 19

**Código actual**:
```php
$credentials_path = '/home/notification_credentials.json';
```

**Solución requerida**:
- Este es un fallback por defecto
- Considerar usar variable de entorno o constante definida en config
- Ejemplo: `getenv('CREDENTIALS_PATH') ?? '/home/notification_credentials.json'`

**Impacto**: Bajo - Es un fallback que raramente se usa

---

### 7. app/pages/admin/config-rutas-sistema.php

**Problema**: Path por defecto hardcodeado

**Línea afectada**: 34

**Código actual**:
```php
$current_credentials_path = file_exists($credentials_path_file)
    ? trim(file_get_contents($credentials_path_file))
    : '/home/notification_credentials.json';
```

**Solución requerida**:
- Similar al anterior, es un fallback
- Podría moverse a una constante de configuración

**Impacto**: Bajo - Es valor por defecto en interfaz de configuración

---

### 8. public_html/api/process-email-queue.php

**Problema**: URL de ejemplo en comentario

**Línea afectada**: 7

**Código actual**:
```php
* wget -q -O /dev/null "https://peu.net/shopv2/api/process-email-queue.php?secret=YOUR_SECRET"
```

**Solución requerida**:
- Cambiar a: `https://[YOUR-DOMAIN]/[BASE-PATH]/api/process-email-queue.php?secret=YOUR_SECRET`
- O simplemente: `https://example.com/api/process-email-queue.php?secret=YOUR_SECRET`

**Impacto**: Muy bajo - Solo es documentación en comentario

---

## 🟢 BAJO - Scripts PHP con Dominios Hardcodeados

### 9. app/pages/api/cancel-order.php

**Problema**: Dominio en lista de allowed_origins

**Línea afectada**: 29

**Código actual**:
```php
$allowed_origins = [
    'https://peu.net',
    'http://localhost:8000',
    'http://127.0.0.1:8000'
];
```

**Solución requerida**:
- Mover a configuración: `APP_PATH . '/config/security.json'`
- O usar variable de entorno
- O derivar del `$_SERVER['HTTP_HOST']` con validación

**Impacto**: Medio - Afecta seguridad CORS pero tiene localhost como fallback

---

### 10. app/pages/frontend/pedido.php

**Problema**: Dominio hardcodeado como fallback

**Línea afectada**: 39

**Código actual**:
```php
$base_url = rtrim($app_config['app_url'] ?? 'https://peu.net', '/');
```

**Solución requerida**:
- Usar: `$_SERVER['HTTP_HOST']` como fallback
- O asegurar que `app_config['app_url']` siempre esté definido

**Impacto**: Bajo - Es fallback que raramente se activa

---

### 11. app/pages/admin/config-email-queue.php

**Problema**: Dominio hardcodeado como fallback

**Línea afectada**: 49

**Código actual**:
```php
$site_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'peu.net') . url('/api/process-email-queue.php?secret=email_queue_cron_2024');
```

**Solución requerida**:
- Ya usa `$_SERVER['HTTP_HOST']` como primario (correcto)
- El fallback `peu.net` podría cambiarse a `'localhost'` o leer de config

**Impacto**: Muy bajo - Solo se usa si `$_SERVER['HTTP_HOST']` no está definido

---

### 12. app/pages/api/crear-preferencia-mp.php

**Problema**: Dominio hardcodeado como fallback

**Código detectado**:
```php
$host = $_SERVER['HTTP_HOST'] ?? 'peu.net';
```

**Solución requerida**:
- Similar al anterior, cambiar fallback a valor de configuración

**Impacto**: Bajo - Es fallback que raramente se usa

---

## Priorización de Correcciones

### 🔴 PRIORIDAD ALTA (Corregir primero)

1. **app/config/carousel.json** - Afecta visualización del carrusel en producción
2. **app/config/site.json** - Afecta meta tags de redes sociales
3. **public_html/.htaccess** - Evaluar si es posible hacer dinámico
4. **public_html/api/.htaccess** - Evaluar si es posible hacer dinámico

### 🟡 PRIORIDAD MEDIA (Corregir después)

5. **app/scripts/process-email-queue.php** - Mejorar detección de paths
6. **app/pages/api/cancel-order.php** - Mover allowed_origins a config

### 🟢 PRIORIDAD BAJA (Opcional)

7. **app/includes/email.php** - Fallback raramente usado
8. **app/pages/admin/config-rutas-sistema.php** - Fallback raramente usado
9. **public_html/api/process-email-queue.php** - Solo comentario de documentación
10. **app/pages/frontend/pedido.php** - Fallback raramente usado
11. **app/pages/admin/config-email-queue.php** - Fallback raramente usado
12. **app/pages/api/crear-preferencia-mp.php** - Fallback raramente usado

---

## Notas Adicionales

### Archivos Excluidos del Análisis

Los siguientes archivos contienen referencias a paths hardcodeados pero fueron excluidos porque son **documentación**:

- `CLAUDE.md` (documentación del proyecto)
- `README.md` (documentación del proyecto)
- Archivos en `docs/` (documentación técnica)
- `.github/workflows/deploy-ftp.yml` (configuración de CI/CD)

### Buenas Prácticas Encontradas

El análisis también reveló que el código **SÍ sigue las buenas prácticas** en:

- ✅ No se encontraron `require` o `include` con paths absolutos hardcodeados
- ✅ No se encontraron paths hardcodeados en atributos HTML (`href`, `src`, `action`)
- ✅ No se encontraron paths hardcodeados en archivos CSS
- ✅ No se encontraron paths hardcodeados en archivos JavaScript
- ✅ La mayoría del código usa correctamente `APP_PATH`, `PUBLIC_PATH`, y `url()`

---

## Recomendaciones Generales

1. **Archivos JSON de configuración**: Todos los paths deben ser relativos sin `/shopv2/`
   - El sistema de rendering debe agregar `BASE_PATH` automáticamente

2. **Archivos .htaccess**: Investigar si Apache permite variables de entorno en `RewriteBase`
   - Alternativa: Generar .htaccess dinámicamente desde instalador

3. **Dominios hardcodeados**: Crear archivo `app/config/security.json` con:
   ```json
   {
     "allowed_origins": ["https://peu.net", "http://localhost:8000"],
     "default_domain": "peu.net"
   }
   ```

4. **Paths de credenciales**: Definir constantes en `config.php`:
   ```php
   define('DEFAULT_CREDENTIALS_PATH', '/home/notification_credentials.json');
   ```

---

## ⚙️ Actualización del Instalador

### CRÍTICO: instalador.php también fue actualizado

El archivo `instalador.php` genera dinámicamente el archivo `public_html/.htaccess` durante la instalación. Se actualizó la función `create_htaccess_files()` para que sea consistente con los cambios aplicados.

**Cambios realizados:**

1. **RewriteBase condicional**:
   - Solo se incluye `RewriteBase` cuando `base_path` no está vacío
   - Si `base_path` es `/` o vacío, se omite y Apache lo auto-detecta
   ```php
   if ($base_path !== '' && $base_path !== '/') {
       $public_htaccess .= "RewriteBase " . $rewrite_base . "\n";
   } else {
       $public_htaccess .= "# RewriteBase auto-detectado por Apache\n";
   }
   ```

2. **Paths relativos en RewriteCond**:
   - Antes: `RewriteCond %{REQUEST_URI} ^" . $base_path . "/api/`
   - Ahora: `RewriteCond %{REQUEST_URI} /api/`
   - Esto permite que las reglas funcionen independientemente del `base_path`

**Impacto**: El instalador ahora genera archivos .htaccess portables que funcionan en cualquier entorno sin hardcodear paths.

---

**Fin del informe**
