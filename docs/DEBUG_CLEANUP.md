# INFORME: Puntos de Debug en Shop-V2

**Fecha**: 2026-01-04
**Objetivo**: Identificar y priorizar la eliminación de código de debug del sistema
**Estado**: Sistema en testing (peu.net/shopv2) - No está en producción real aún

---

## 📊 RESUMEN EJECUTIVO

| Tipo | Cantidad | Impacto |
|------|----------|---------|
| `error_log()` en PHP | **376** | Alto - Genera ~45,000 líneas de log/día |
| `console.log/warn/error` en JS | **268** | Medio - Ruido en consola del navegador |
| `print_r()` | **4** | Bajo |
| `var_export()` | **7** | Bajo (6 en instalador temporal) |
| Comentarios TODO/FIXME/DEBUG | **58** | Bajo - Documentación |

### Impacto Estimado en Producción

Con 100 usuarios diarios:
- **index.php**: 2,000 requests × 20 logs = **40,000 líneas/día**
- **checkout-new.php**: 50 checkouts × 40 logs = **2,000 líneas/día**
- **shipping.js**: 50 cotizaciones × 54 console.log = **2,700 líneas en consola**
- **admin/index.php**: 200 requests × 5 logs = **1,000 líneas/día**

**Total**: ~45,000+ líneas de debug/día solo en archivos críticos

---

## 🔴 PRIORIDAD CRÍTICA (Eliminar inmediatamente)

### 1. public_html/index.php
**Impacto**: Se ejecuta en CADA request del frontend
**Líneas**: 2, 12, 17, 21, 27, 34, 38, 41, 43, 53, 55, 59, 62-64, 66, 71, 74, 89, 92, 94, 95

```php
error_log("=== INDEX.PHP START ===");
error_log("INDEX.PHP - Line 10: About to define APP_ENTRY_POINT");
error_log("INDEX.PHP - Line 15: APP_ENTRY_POINT defined");
// ... (20 líneas de debug total)
error_log("=== INDEX.PHP END ===");
```

**Acción**: Eliminar TODAS las líneas de error_log de este archivo

---

### 2. public_html/admin/index.php
**Impacto**: Se ejecuta en CADA página del admin
**Líneas**: 124-126, 130, 141, 143

```php
error_log("ADMIN ROUTER - Requested page: $page");
error_log("ADMIN ROUTER - Page file: $page_file");
error_log("ADMIN ROUTER - File exists: " . (file_exists($page_file) ? 'YES' : 'NO'));
error_log("ADMIN ROUTER - About to require: $page_file");
error_log("ADMIN ROUTER - Page required successfully");
```

**Acción**: Eliminar TODAS las líneas de error_log del router admin

---

### 3. app/includes/router.php
**Impacto**: Se ejecuta en CADA request (frontend y admin)
**Líneas**: 30-48 (método dispatch completo tiene logs)

```php
error_log("ROUTER - Method: $method, URI after parse: $uri");
error_log("ROUTER - Available routes: " . print_r(array_keys($this->routes[$method] ?? []), true));
error_log("ROUTER - Exact match found for $uri -> " . $this->routes[$method][$uri]);
error_log("ROUTER - Pattern match found for $uri -> $file");
error_log("ROUTER - No match found for $uri, returning 404");
```

**Problema adicional**: Usa `print_r()` dentro de error_log, generando logs enormes

**Acción**: Eliminar todos los error_log del método dispatch()

---

### 4. app/pages/frontend/checkout-new.php
**Impacto**: Alta frecuencia, crítico para experiencia de usuario
**Debug PHP**: Líneas 221-277, 451-454 (40+ logs)
**Debug JS**: Líneas 2616-2619, 2365, etc (26+ logs)

**PHP**:
```php
error_log("Checkout: Cupón recibido desde carrito: " . $_SESSION['applied_coupon']);
error_log("Checkout: Cupón code: " . ($coupon_code ?? 'NULL'));
error_log("DEBUG Checkout POST - shipping_service_id: " . ($shipping_service_id ?: 'EMPTY'));
error_log("DEBUG Checkout POST - delivery_method: " . $delivery_method);
```

**JavaScript**:
```javascript
console.log('📤 Enviando formulario con shipping data:');
console.log('  - shipping_service_id:', shippingServiceId || 'EMPTY');
console.log('⚠️ updateConfirmationSummary: Faltan campos requeridos');
```

**Acción**:
- Eliminar TODOS los error_log de debug
- Reducir console.log a máximo 5 críticos
- Mantener solo logs de errores reales

---

### 5. app/pages/api/shipping.php
**Impacto**: Se ejecuta en cada cotización de envío
**Líneas**: 12-15, 21-22, 31

```php
error_log('=== SHIPPING.PHP CALLED ===');
error_log('REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log('QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'NONE'));
error_log('GET params: ' . json_encode($_GET));
error_log('Action determined: ' . ($action ?: 'EMPTY'));
```

**Acción**: Eliminar todos los logs de debug, mantener solo errores críticos

---

## 🟠 PRIORIDAD ALTA (Reducir significativamente)

### 6. public_html/webhook.php
**Debug actual**: 22 error_log + logs de MP Logger
**Líneas críticas**: 317-341, 109-110

```php
// Debug de input
error_log("WEBHOOK DEBUG - Raw php://input length: " . strlen($input));
error_log("WEBHOOK DEBUG - Raw php://input: " . $input);
error_log("WEBHOOK DEBUG - JSON decode error: " . json_last_error_msg());

// Debug de signature
error_log("WEBHOOK DEBUG - Signature validation - data_id: $data_id");
error_log("WEBHOOK DEBUG - Full request_data: " . json_encode($request_data));
```

**Acción**:
- Eliminar logs que dicen "WEBHOOK DEBUG"
- Mantener logs de MP Logger (sistema legítimo)
- Mantener logs de errores de validación

---

### 7. app/pages/api/create-shipment-from-order.php
**Debug actual**: 20 error_log

**Acción**: Reducir a solo errores críticos (máximo 5 logs)

---

### 8. app/includes/coupons.php
**Debug actual**: 16 error_log

**Acción**: Eliminar logs de debug de aplicación de cupones, mantener solo errores

---

### 9. public_html/assets/js/shipping.js
**Debug actual**: 54 console.log/warn/error

```javascript
console.log('📦 shipping.js: Archivo cargado');
console.log('🔧 Shipping module: DOMContentLoaded event fired');
console.log('🚀 handleGetQuote() llamado');
console.log('📍 Datos de envío:', { postalCode, city, province, country });
console.log('📦 Items construidos:', items);
console.log('✅ Cotizaciones recibidas:', shippingQuotes.length);
console.log('📍 Punto de entrega seleccionado:', selectedPointId);
```

**Acción**: Reducir a máximo 10 console.log críticos:
- Mantener: Errores de API (console.error)
- Mantener: Warnings de validación (console.warn)
- Eliminar: Logs de flujo normal, emojis decorativos

---

### 10. public_html/assets/js/places-autocomplete-new.js
**Debug actual**: 42 console.log/error

```javascript
console.log('🔑 Session token generado:', sessionToken);
console.log('📍 Configurando autocomplete con Places API (New) REST...');
console.log('🔍 Buscando predicciones con REST API para:', query);
console.log('✅ Predicciones recibidas:', data);
console.error('❌ Error al obtener predicciones:', error);
```

**Acción**: Reducir a máximo 5 console.log:
- Mantener: Errores de API (console.error)
- Eliminar: Logs informativos de flujo normal

---

### 11. public_html/assets/js/address-validator.js
**Debug actual**: 25 console.log/warn/error

**Acción**: Reducir a máximo 5 console.log críticos

---

## 🟡 PRIORIDAD MEDIA (Revisar y optimizar)

### 12. app/includes/email.php
**Debug actual**: 26 error_log

**Recomendación**:
- Mantener logs de errores SMTP
- Eliminar logs de éxitos de envío
- Reducir de 26 a ~8 logs

---

### 13. app/includes/telegram.php
**Debug actual**: 27 error_log

**Recomendación**:
- Mantener logs de errores de envío
- Eliminar logs de notificaciones exitosas
- Reducir de 27 a ~8 logs

---

### 14. app/includes/functions.php
**Debug actual**: 16 error_log

**Recomendación**: Revisar logs de DolarAPI y operaciones JSON

---

### 15. app/pages/frontend/carrito.php (inline JS)
**Debug actual**: 20 console.log/warn

**Acción**: Reducir a máximo 5 console.log críticos

---

### 16. public_html/assets/js/cart-validator.js
**Debug actual**: 11 console.log/warn

**Acción**: Reducir a máximo 3 console.log

---

### 17. public_html/assets/js/carousel.js
**Debug actual**: 9 console.log

**Acción**: Eliminar todos (no críticos)

---

### 18. public_html/assets/js/shared/cart.js
**Debug actual**: 15 console.log/warn/error

**Acción**: Reducir a máximo 5 console.log

---

## 🟢 MANTENER (Logs legítimos de auditoría y seguridad)

### Archivos con logs de auditoría y seguridad:

#### app/pages/api/export-orders.php
```php
error_log("SECURITY: Export rate limit excedido - User: {$_SESSION['username']}, IP: $client_ip");
error_log("SECURITY: CSRF token inválido en export - User: {$_SESSION['username']}, IP: $client_ip");
error_log("AUDIT: Export realizado - " . json_encode($export_log));
```
**Razón**: Auditoría de seguridad crítica
**Acción**: MANTENER

---

#### app/pages/api/cancel-order.php
```php
error_log("Cancel Order: Origen no permitido: $origin desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
error_log("Cancel Order: CSRF token inválido para orden: {$data['order_id']}");
```
**Razón**: Logs de seguridad
**Acción**: MANTENER

---

#### app/includes/orders.php
Logs de creación de órdenes y errores críticos (líneas 186, 195, 203, 312)

**Razón**: Crítico para debugging de producción
**Acción**: MANTENER

---

#### app/includes/mp-logger.php (Sistema MP Logger)
**Función**: `log_mp_debug()`
**Archivo de salida**: `/mp_debug.log` (raíz del proyecto)
**Uso**: 13 llamadas en webhook.php

**Logs de**: WEBHOOK_RECEIVED, PAYMENT_DETAILS, ORDER_UPDATE, NOTIFICATION

**Razón**: Sistema diseñado específicamente para logs de MercadoPago en producción
**Acción**: MANTENER pero verificar rotación de logs

---

## 📝 CASOS ESPECIALES

### instalador.php
**Debug actual**: 56 error_log + múltiples comentarios DEBUG

**Contexto**: Este archivo se auto-elimina después de la instalación

**Acción**: MANTENER (solo se usa durante instalación inicial)

---

### public_html/debug-theme.php
**Contexto**: Archivo completo dedicado a debugging del sistema de themes

**Acción**: EVALUAR si es necesario en producción, considerar eliminar o proteger

---

### app/pages/admin/envios-archivo-simple.php
```php
error_log("ENVIOS-ARCHIVO-SIMPLE - Starting");
error_log("ENVIOS-ARCHIVO-SIMPLE - Total archived orders: $total");
error_log("ENVIOS-ARCHIVO-SIMPLE - PROBLEMATIC ORDERS FOUND:");
```

**Contexto**: Archivo de testing según comentario en línea 3

**Acción**: EVALUAR si aún se usa, considerar eliminar

---

## 📋 COMENTARIOS TODO/FIXME/DEBUG

### TODO (Tareas pendientes):

1. **app/includes/products.php:596**
   ```php
   // TODO: Send low stock email
   ```
   **Acción**: Implementar o eliminar comentario

2. **app/includes/theme-loader.php:171**
   ```php
   // TODO: Implementar cache con APCu o file-based cache
   ```
   **Acción**: Evaluar implementación

3. **app/includes/orders.php:998**
   ```php
   // TODO: Send notification to customer about status change
   ```
   **Acción**: Implementar o eliminar

4. **public_html/api/process-email-queue.php:14**
   ```php
   $secret_token = 'email_queue_cron_2024'; // TODO: Mover a config segura
   ```
   **Acción**: CRÍTICO - Mover a config.php

---

### Comentarios DEBUG explícitos:

1. **instalador.php**: Múltiples "DEBUG:" (líneas 271-393)
2. **public_html/webhook.php**: "DEBUGGING:" (líneas 35, 108, 316, 338)
3. **app/includes/carriers.php:195, 563, 569**: Comentarios "para debug"
4. **app/pages/frontend/checkout-new.php:450**: "DEBUG: Log shipping data received"

**Acción**: Eliminar comentarios con palabra "DEBUG" una vez limpiados los logs

---

## 📊 TOP 10 ARCHIVOS CON MÁS DEBUG

| # | Archivo | error_log | console.log | Total |
|---|---------|-----------|-------------|-------|
| 1 | instalador.php | 56 | 0 | 56 |
| 2 | public_html/assets/js/shipping.js | 0 | 54 | 54 |
| 3 | public_html/assets/js/places-autocomplete-new.js | 0 | 42 | 42 |
| 4 | app/includes/telegram.php | 27 | 0 | 27 |
| 5 | app/includes/email.php | 26 | 0 | 26 |
| 6 | public_html/assets/js/address-validator.js | 0 | 25 | 25 |
| 7 | public_html/webhook.php | 22 | 0 | 22 |
| 8 | app/pages/api/config-backup.php | 21 | 0 | 21 |
| 9 | app/pages/api/create-shipment-from-order.php | 20 | 0 | 20 |
| 10 | app/pages/frontend/carrito.php | 0 | 20 | 20 |

---

## 🎯 PLAN DE ACCIÓN RECOMENDADO

### Fase 1: Críticos (Semana 1)
- [ ] Limpiar index.php (eliminar 20 logs)
- [ ] Limpiar admin/index.php (eliminar 5 logs)
- [ ] Limpiar router.php (eliminar 8 logs)
- [ ] Limpiar checkout-new.php (reducir de 40 a 5 logs)
- [ ] Limpiar shipping.php API (eliminar todos los logs de debug)

**Impacto**: Reducción del 80% de logs diarios

---

### Fase 2: Alta prioridad (Semana 2)
- [ ] Limpiar webhook.php (reducir de 22 a 8 logs)
- [ ] Limpiar shipping.js (reducir de 54 a 10 console.log)
- [ ] Limpiar places-autocomplete-new.js (reducir de 42 a 5 console.log)
- [ ] Limpiar address-validator.js (reducir de 25 a 5 console.log)
- [ ] Limpiar coupons.php (eliminar 16 logs)

**Impacto**: Sistema con logs solo para debugging real

---

### Fase 3: Media prioridad (Semana 3)
- [ ] Optimizar email.php (reducir de 26 a 8 logs)
- [ ] Optimizar telegram.php (reducir de 27 a 8 logs)
- [ ] Limpiar JavaScript de carrito (reducir console.log)
- [ ] Revisar functions.php (optimizar 16 logs)

**Impacto**: Sistema con logs mínimos y relevantes

---

### Fase 4: Limpieza final (Semana 4)
- [ ] Resolver TODOs pendientes
- [ ] Eliminar comentarios DEBUG
- [ ] Evaluar debug-theme.php
- [ ] Evaluar envios-archivo-simple.php
- [ ] Verificar rotación de mp_debug.log

**Impacto**: Código limpio y profesional

---

## 📈 MÉTRICAS DE ÉXITO

### Antes de la limpieza:
- ~376 error_log() en código
- ~268 console.log/warn/error en JavaScript
- ~45,000 líneas de log por día
- Archivos de log creciendo rápidamente

### Después de la limpieza (objetivo):
- ~50 error_log() (solo errores y auditoría)
- ~30 console.log/error (solo errores críticos)
- ~2,000 líneas de log por día (95% de reducción)
- Logs claros y relevantes

---

## ⚠️ PRECAUCIONES

1. **No eliminar logs de seguridad**: SECURITY, AUDIT, CSRF, rate limiting
2. **No eliminar logs de errores críticos**: Fallos de API, errores de pago
3. **Mantener MP Logger**: Sistema diseñado para producción
4. **Testear después de cada limpieza**: Verificar que no se rompió funcionalidad
5. **Hacer commits incrementales**: No limpiar todo de una vez

---

## 🔍 HERRAMIENTAS PARA IDENTIFICAR DEBUG

### Comandos grep útiles:

```bash
# Buscar error_log en PHP
grep -r "error_log(" app/ public_html/ --include="*.php" | wc -l

# Buscar console.log en JS
grep -r "console\." public_html/assets/js/ --include="*.js" | wc -l

# Buscar comentarios DEBUG
grep -r "// DEBUG" app/ public_html/ --include="*.php" --include="*.js"

# Buscar TODO/FIXME
grep -r "// TODO\|// FIXME" app/ public_html/ --include="*.php" --include="*.js"
```

---

## 🆕 DEBUGS AGREGADOS EN SESIÓN 2026-01-04 (22:00)

### app/pages/api/create-shipment-from-order.php
**Líneas**: 228, 241-246
**Contexto**: Agregados durante fix de consistencia barrio/localidad

```php
error_log("API CreateShipment: Usando destination de quote_data - city: $city_to_use");
error_log("API CreateShipment: FALLBACK - CABA detectada (CP: $postal_code_numeric) - usando barrio: $barrio");
error_log("API CreateShipment: FALLBACK - CABA detectada (CP: $postal_code_numeric) pero no hay barrio - usando city: $city_to_use");
error_log("API CreateShipment: FALLBACK - Fuera de CABA (CP: $postal_code_numeric) - usando localidad: $city_to_use");
```

**Recomendación**: MANTENER temporalmente para verificar que el fix funciona correctamente, luego ELIMINAR en próxima limpieza.

---

## 📚 DOCUMENTOS RELACIONADOS

- **CLAUDE.md**: Reglas del proyecto (RULE 7 sobre debugging local vs producción)
- **SECURITY_AUDIT_REPORT.md**: Auditoría de seguridad general del sistema
- **mp_debug.log**: Logs específicos de MercadoPago (verificar rotación)

---

**Última actualización**: 2026-01-04 22:10
**Generado por**: Claude Code análisis automático del repositorio
