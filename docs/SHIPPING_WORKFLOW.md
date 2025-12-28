# Sistema de Estados de Envío y Notificaciones

**Documentación del flujo completo de envíos con Zipnova**

Última actualización: 2025-12-28

---

## Índice

1. [Configuración](#configuración)
2. [Estados del Sistema](#estados-del-sistema)
3. [Flujo Completo](#flujo-completo)
4. [Webhooks de Zipnova](#webhooks-de-zipnova)
5. [Sistema de Emails](#sistema-de-emails)
6. [Funciones Principales](#funciones-principales)
7. [Troubleshooting](#troubleshooting)

---

## Configuración

### 1. Configuración de Zipnova

**Archivo**: `app/config/shipping.json`

```json
{
  "carriers": {
    "ZNVA": {
      "tag": "ZNVA",
      "name": "Zipnova",
      "enabled": true,
      "mode": "sandbox",  // o "production"
      "credentials": {
        "account_id": "tu_account_id",
        "client_id": "tu_client_id",
        "client_secret": "tu_client_secret"
      },
      "options": {
        "send_customer_email": false,  // ⚠️ IMPORTANTE
        "auto_create_shipment": true,
        "webhook_secret": "tu_webhook_secret",
        "cache_quotes_minutes": 5
      }
    }
  }
}
```

#### Opción Crítica: `send_customer_email`

**Valor recomendado**: `false`

**¿Por qué?**
- Cuando es `true`: Zipnova enviará emails automáticos al cliente
- Cuando es `false`: Solo tu sistema envía emails (evita duplicación)

**Comportamiento**:
```php
// En zipnova_create_shipment():
if (!$send_customer_email && isset($shipment_data['destination']['email'])) {
    unset($shipment_data['destination']['email']); // NO enviar email a API
}
```

**Log generado**:
```
[2025-12-28 14:30:15] [ORDER: 12345] Customer Email Removed: {
  "note": "Email no enviado a Zipnova (send_customer_email=false)",
  "removed_email": "cliente@email.com"
}
```

---

### 2. Configuración de Emails

**Archivo**: `app/config/email.json`

```json
{
  "notifications": {
    "customer": {
      "order_confirmation": true,
      "payment_approved": true,
      "shipping_preparation": true,  // ← Email cuando genera etiqueta
      "order_shipped": true,          // ← Email cuando está en tránsito
      "order_delivered": true
    },
    "admin": {
      "new_order": true,
      "low_stock": true
    }
  },
  "admin_email": "admin@tienda.com",
  "from_email": "noreply@tienda.com",
  "from_name": "Mi Tienda"
}
```

---

## Estados del Sistema

### Estados Base (Internos)

El sistema usa estados en español que son consistentes en toda la aplicación:

| Estado Base | Label | Descripción | Color Badge |
|-------------|-------|-------------|-------------|
| `pendiente` | Pendiente | Envío creado, etiqueta no generada | amarillo |
| `en_preparacion` | En preparación | Etiqueta generada, esperando entrega | azul |
| `en_transito` | En tránsito | Carrier recibió paquete, en camino | azul claro |
| `en_reparto` | En reparto | Paquete en reparto, cerca de destino | naranja |
| `entregada` | Entregada | Paquete entregado al cliente | verde |
| `fallida` | Fallida | Intento de entrega fallido | rojo |
| `devuelta` | Devuelta | Paquete devuelto al remitente | morado |
| `cancelada` | Cancelada | Envío cancelado | gris |

### Mapeo de Estados de Zipnova

**Función**: `map_carrier_status_to_base()`
**Ubicación**: `app/includes/carriers.php`

```php
$mappings = [
    'zipnova' => [
        'pending'           => 'pendiente',
        'ready'             => 'en_preparacion',    // ← NUEVO
        'in_transit'        => 'en_transito',
        'out_for_delivery'  => 'en_reparto',
        'delivered'         => 'entregada',
        'failed'            => 'fallida',
        'returned'          => 'devuelta',
        'cancelled'         => 'cancelada'
    ]
];
```

**¿Por qué mapear?**
- Zipnova usa estados en inglés
- El sistema usa estados en español
- Permite agregar otros carriers (Andreani, OCA, etc.) con sus propios estados
- Centraliza la lógica de conversión

---

## Flujo Completo

### Paso a Paso desde la Orden hasta la Entrega

```
┌─────────────────────────────────────────────────────────────┐
│ 1. CLIENTE COMPLETA COMPRA                                  │
│    Estado: pendiente                                        │
│    Email: "Pedido Confirmado"                               │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. SISTEMA CREA ENVÍO EN ZIPNOVA (automático o manual)     │
│    Función: zipnova_create_shipment()                       │
│    - NO envía email del cliente a Zipnova (si configured)  │
│    - Crea shipment en API de Zipnova                        │
│    - Guarda shipment_id en la orden                         │
│    Estado: pendiente (sin cambios)                          │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. ADMIN GENERA ETIQUETA                                    │
│    Acción: Click en "Imprimir Etiqueta" en admin           │
│    Función: zipnova_get_label()                             │
│    - Obtiene PDF de etiqueta desde Zipnova                  │
│    - Guarda PDF en /app/data/labels/                        │
│    - ⚡ CAMBIA ESTADO → en_preparacion                      │
│    - Actualiza orden                                         │
│    - ✉️ ENVÍA EMAIL AL CLIENTE                             │
│    Estado: en_preparacion                                   │
│    Email: "Tu Pedido Está en Preparación!"                  │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. ADMIN IMPRIME Y ENTREGA PAQUETE A ZIPNOVA               │
│    - Admin imprime etiqueta física                          │
│    - Pega etiqueta al paquete                               │
│    - Entrega paquete a Zipnova (pickup o dropoff)           │
│    Estado: en_preparacion (sin cambios en sistema)          │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. ZIPNOVA RECIBE PAQUETE → WEBHOOK                         │
│    Zipnova envía: POST /api/shipping?action=webhook         │
│    Payload: { "status": "in_transit", ... }                 │
│    Sistema procesa:                                          │
│    - Mapea "in_transit" → "en_transito"                     │
│    - Actualiza orden                                         │
│    - ✉️ ENVÍA EMAIL AL CLIENTE                             │
│    Estado: en_transito                                      │
│    Email: "Tu pedido está en camino"                        │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 6. PAQUETE EN REPARTO → WEBHOOK                             │
│    Payload: { "status": "out_for_delivery", ... }           │
│    - Mapea "out_for_delivery" → "en_reparto"                │
│    - Actualiza orden                                         │
│    - ✉️ ENVÍA EMAIL AL CLIENTE                             │
│    Estado: en_reparto                                       │
│    Email: "Tu pedido está en reparto"                       │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│ 7. PAQUETE ENTREGADO → WEBHOOK                              │
│    Payload: { "status": "delivered", ... }                  │
│    - Mapea "delivered" → "entregada"                        │
│    - Actualiza orden                                         │
│    - ✉️ ENVÍA EMAIL AL CLIENTE                             │
│    Estado: entregada                                        │
│    Email: "Tu pedido ha sido entregado"                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Webhooks de Zipnova

### Configuración del Webhook

**URL del webhook**: `https://tu-dominio.com/shopv2/api/shipping?action=webhook`

**Método**: `POST`

**Headers requeridos**:
```
Content-Type: application/json
X-Zipnova-Signature: [firma HMAC]
```

### Estructura del Payload

```json
{
  "event": "status_update",
  "shipment_id": "67890",
  "tracking_id": "ZNVA1234567890",
  "status": "in_transit",
  "timestamp": "2025-12-28T14:30:00Z",
  "location": {
    "city": "Buenos Aires",
    "state": "Buenos Aires"
  }
}
```

### Procesamiento del Webhook

**Archivo**: `app/pages/api/shipping.php`

**Flujo**:
1. ✅ Validar firma HMAC (si `webhook_secret` está configurado)
2. ✅ Parsear JSON payload
3. ✅ Actualizar shipment local
4. ✅ Buscar orden por tracking_id
5. ✅ Mapear estado de Zipnova → estado base
6. ✅ Actualizar estado de la orden
7. ✅ Enviar email al cliente
8. ✅ Loguear todo el proceso

**Código simplificado**:
```php
// 1. Validar firma
if (!zipnova_webhook_verify($payload, $signature)) {
    return error('Invalid signature');
}

// 2. Procesar datos
$shipment_id = $data['shipment_id'];
$new_status = $data['status'];  // "in_transit"

// 3. Mapear estado
$base_status = map_carrier_status_to_base('zipnova', $new_status);
// "in_transit" → "en_transito"

// 4. Actualizar orden
update_shipping_status_by_tracking($tracking_id, $new_status);

// 5. Enviar email
send_shipping_status_notification($order, $new_status);
```

### Seguridad del Webhook

**Verificación de firma HMAC**:
```php
function zipnova_webhook_verify($payload, $signature) {
    $config = zipnova_get_config();
    $secret = $config['options']['webhook_secret'] ?? '';

    if (empty($secret)) {
        return true; // Sin verificación si no hay secret
    }

    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
```

**Configurar webhook_secret** en `app/config/shipping.json`:
```json
{
  "options": {
    "webhook_secret": "tu_secreto_muy_seguro_aqui"
  }
}
```

---

## Sistema de Emails

### Emails Automáticos por Estado

| Estado | Función | Asunto | Cuándo se envía |
|--------|---------|--------|-----------------|
| `en_preparacion` | `send_shipping_preparation_email()` | "¡Tu Pedido Está en Preparación!" | Al generar etiqueta |
| `en_transito` | `send_shipping_status_notification()` | "Tu pedido está en camino" | Webhook de Zipnova |
| `en_reparto` | `send_shipping_status_notification()` | "Tu pedido está en reparto" | Webhook de Zipnova |
| `entregada` | `send_shipping_status_notification()` | "Tu pedido ha sido entregado" | Webhook de Zipnova |

### Función de Email: En Preparación

**Ubicación**: `app/includes/email.php`

```php
/**
 * Send shipping preparation notification to customer
 * Enviado cuando se genera la etiqueta de envío (estado: en_preparacion)
 */
function send_shipping_preparation_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    // Verificar si la notificación está habilitada
    if (!($config['notifications']['customer']['shipping_preparation'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Tu Pedido Está en Preparación! - #{$order['order_number']}";

    $html = get_email_template('shipping_preparation', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}
```

### Función de Email: Otros Estados

**Ubicación**: `app/pages/api/shipping.php`

```php
function send_shipping_status_notification($order, $new_status) {
    // Mapear estado de carrier a estado base
    $base_status = map_carrier_status_to_base('zipnova', $new_status);

    // Mensajes según estado base
    $status_messages = [
        'pendiente' => 'Tu envío está siendo procesado',
        'en_preparacion' => 'Tu pedido está en preparación',
        'en_transito' => 'Tu pedido está en camino',
        'en_reparto' => 'Tu pedido está en reparto',
        'entregada' => 'Tu pedido ha sido entregado',
        'fallida' => 'Hubo un problema con tu envío',
        'devuelta' => 'Tu envío está siendo devuelto',
        'cancelada' => 'Tu envío ha sido cancelado'
    ];

    $subject = $status_messages[$base_status] ?? 'Actualización de tu envío';

    // Construir email con datos de la orden
    $body = "
    <h2>{$subject}</h2>
    <p>Hola {$order['customer_name']},</p>
    <p>Te informamos que el estado de tu envío ha cambiado.</p>
    <p><strong>Orden:</strong> {$order['order_number']}</p>
    <p><strong>Estado:</strong> {$subject}</p>
    ";

    if (!empty($tracking_id)) {
        $tracking_url = url('/tracking?id=' . urlencode($tracking_id));
        $body .= "<p><a href='{$tracking_url}'>Ver seguimiento completo</a></p>";
    }

    return send_email($order['customer_email'], $subject, $body);
}
```

### Template de Email Personalizado (Opcional)

Para crear un template específico para "en preparación":

**Crear archivo**: `app/templates/email/shipping_preparation.php`

```php
<?php
/**
 * Template de email: Envío en preparación
 * Variables disponibles: $order
 */
$site_config = read_json(APP_PATH . '/config/site.json');
$site_name = $site_config['site_name'] ?? 'Mi Tienda';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .status-badge { background: #2196F3; color: white; padding: 5px 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 ¡Tu pedido está en preparación!</h1>
        </div>
        <div class="content">
            <p>Hola <strong><?= htmlspecialchars($order['customer_name']) ?></strong>,</p>

            <p>¡Buenas noticias! Hemos generado la etiqueta de envío y tu pedido está siendo preparado para su despacho.</p>

            <p><strong>Número de pedido:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
            <p><strong>Estado actual:</strong> <span class="status-badge">En preparación</span></p>

            <p>En breve será entregado al carrier y recibirás una nueva notificación cuando esté en camino.</p>

            <p>Gracias por tu compra,<br><?= $site_name ?></p>
        </div>
    </div>
</body>
</html>
```

---

## Funciones Principales

### 1. Crear Envío

**Función**: `zipnova_create_shipment($shipment_data, $order_id = null)`
**Ubicación**: `app/includes/carriers.php`

**Uso**:
```php
$shipment_data = [
    'rate_id' => $quote_data['rate_id'],
    'external_id' => $order['order_number'],
    'reference' => $order['order_number'],
    'declared_value' => 15000,
    'origin_id' => $config['origin']['origin_id'],
    'items' => [...],
    'destination' => [
        'name' => 'Juan Pérez',
        'street' => 'Av. Corrientes',
        'street_number' => '1234',
        'city' => 'Buenos Aires',
        'state' => 'Buenos Aires',
        'zipcode' => 'C1043AAZ',
        'phone' => '+54 11 1234-5678',
        'email' => 'cliente@email.com',  // Se elimina si send_customer_email=false
        'document' => '12345678'
    ]
];

$result = zipnova_create_shipment($shipment_data, $order['order_number']);

if ($result['success']) {
    $shipment_id = $result['data']['id'];
    // Guardar shipment_id en la orden
}
```

**Comportamiento clave**:
```php
// Dentro de la función:
$send_customer_email = $config['options']['send_customer_email'] ?? false;

if (!$send_customer_email && isset($shipment_data['destination']['email'])) {
    zipnova_log('Customer Email Removed', [
        'note' => 'Email no enviado a Zipnova (send_customer_email=false)',
        'removed_email' => $shipment_data['destination']['email']
    ], $order_id);

    unset($shipment_data['destination']['email']);
}
```

---

### 2. Generar Etiqueta

**Función**: `zipnova_get_label($shipment_id, $format = 'pdf', $order_id = null)`
**Ubicación**: `app/includes/carriers.php`

**Uso**:
```php
$result = zipnova_get_label($shipment_id, 'pdf', $order_number);

if ($result['success']) {
    $label_url = $result['data']['label_url'];
    // /data/labels/label_67890_1640000000.pdf
}
```

**Comportamiento clave**:
```php
// Dentro de la función, después de generar la etiqueta:

// 1. Cambiar estado del shipment
$shipment['status'] = 'en_preparacion';
zipnova_save_shipment($shipment_id, $shipment);

// 2. Buscar orden y actualizar
$orders = get_all_orders();
foreach ($orders as $ord) {
    if ($ord['shipping']['carrier_shipment_id'] === $shipment_id) {
        // 3. Actualizar estado en orden
        update_order_shipping_status($ord['id'], 'en_preparacion');

        // 4. Enviar email al cliente
        send_shipping_preparation_email($ord);

        break;
    }
}
```

---

### 3. Actualizar Estado desde Webhook

**Función**: `update_shipping_status_by_tracking($tracking_id, $new_status, $extra_data = [])`
**Ubicación**: `app/includes/orders.php`

**Uso**:
```php
// Llamado desde el webhook
$tracking_id = 'ZNVA1234567890';
$new_status = 'in_transit';  // Estado de Zipnova

$updated = update_shipping_status_by_tracking($tracking_id, $new_status, $webhook_data);

if ($updated) {
    // Enviar notificación al cliente
}
```

**Comportamiento interno**:
```php
// 1. Buscar orden por tracking_id
foreach ($data['orders'] as &$order) {
    if ($order['shipping']['tracking_id'] === $tracking_id) {

        // 2. Guardar estado original de carrier
        $order['shipping']['carrier_status'] = $new_status;  // "in_transit"

        // 3. Mapear a estado base del sistema
        $base_status = map_carrier_status_to_base('zipnova', $new_status);
        $order['shipping']['status'] = $base_status;  // "en_transito"

        // 4. Actualizar timestamp
        $order['shipping']['updated_at'] = date('Y-m-d H:i:s');

        // 5. Agregar a historial
        $order['shipping']['history'][] = [
            'date' => date('Y-m-d H:i:s'),
            'updates' => [
                'carrier_status' => $new_status,
                'base_status' => $base_status
            ]
        ];

        // 6. Guardar orden
        write_json($orders_file, $data);
        return true;
    }
}
```

---

### 4. Mapeo de Estados

**Función**: `map_carrier_status_to_base($carrier_type, $carrier_status)`
**Ubicación**: `app/includes/carriers.php`

**Uso**:
```php
$base_status = map_carrier_status_to_base('zipnova', 'in_transit');
// Resultado: "en_transito"

$base_status = map_carrier_status_to_base('zipnova', 'ready');
// Resultado: "en_preparacion"
```

**Extensión para otros carriers**:
```php
function map_carrier_status_to_base($carrier_type, $carrier_status) {
    $mappings = [
        'zipnova' => [
            'pending' => 'pendiente',
            'ready' => 'en_preparacion',
            'in_transit' => 'en_transito',
            // ...
        ],
        'andreani' => [  // ← Futuro carrier
            'ingresado' => 'pendiente',
            'en_camino' => 'en_transito',
            // ...
        ]
    ];

    return $mappings[$carrier_type][$carrier_status] ?? 'pendiente';
}
```

---

### 5. Obtener Label del Estado

**Función**: `get_status_label($base_status)`
**Ubicación**: `app/includes/carriers.php`

**Uso**:
```php
$label = get_status_label('en_preparacion');
// Resultado: "En preparación"

$label = get_status_label('en_transito');
// Resultado: "En tránsito"
```

**Para mostrar en UI**:
```php
<span class="status-badge status-<?= $order['shipping']['status'] ?>">
    <?= get_status_label($order['shipping']['status']) ?>
</span>
```

---

## Troubleshooting

### Problema 1: El estado no cambia a "en_preparacion" al generar etiqueta

**Síntomas**:
- Admin genera etiqueta
- PDF se descarga correctamente
- Pero el estado sigue en "pendiente"

**Causas posibles**:
1. La orden no tiene `carrier_shipment_id` asociado
2. Error al buscar la orden por shipment_id
3. La función `update_order_shipping_status()` no existe o falla

**Solución**:
```bash
# Ver logs de Zipnova
tail -f /home/pablo/shop-v2/logs/zipnova/$(date +%Y-%m-%d).log | grep "ORDER:"

# Verificar que la orden tiene shipment_id
# En la orden JSON debe existir:
{
  "shipping": {
    "carrier_shipment_id": "67890"
  }
}
```

**Verificar función**:
```php
// Verificar que existe esta función en app/includes/orders.php
function update_order_shipping_status($order_id, $new_status) { ... }
```

---

### Problema 2: No llega email al cliente en ningún estado

**Síntomas**:
- Estados se actualizan correctamente
- Pero no llegan emails

**Causas posibles**:
1. SMTP no configurado
2. Notificación deshabilitada en `email.json`
3. Email del cliente no existe en la orden

**Solución**:

1. **Verificar configuración SMTP**:
```bash
# Verificar credenciales SMTP
cat /home/notification_credentials.json
```

2. **Verificar notificaciones habilitadas**:
```json
// app/config/email.json
{
  "notifications": {
    "customer": {
      "shipping_preparation": true  // ← Debe ser true
    }
  }
}
```

3. **Probar envío de email**:
```php
// Desde admin: /admin/?page=config-email
// Click en "Enviar Email de Prueba"
```

---

### Problema 3: Webhooks no están actualizando el estado

**Síntomas**:
- Admin entrega paquete a Zipnova
- Zipnova recibe el paquete
- Estado no cambia a "en_transito"

**Causas posibles**:
1. Webhook no configurado en Zipnova
2. Firma HMAC inválida
3. URL del webhook incorrecta

**Solución**:

1. **Verificar URL del webhook en Zipnova**:
```
Debe ser: https://tu-dominio.com/shopv2/api/shipping?action=webhook
```

2. **Ver logs de webhooks**:
```bash
tail -f /home/pablo/shop-v2/logs/zipnova/$(date +%Y-%m-%d).log | grep Webhook
```

3. **Verificar webhook_secret**:
```json
// app/config/shipping.json
{
  "options": {
    "webhook_secret": "debe_coincidir_con_zipnova"
  }
}
```

4. **Desactivar verificación temporalmente** (solo para debug):
```php
// En app/includes/carriers.php
function zipnova_webhook_verify($payload, $signature) {
    return true;  // ← TEMPORAL SOLO PARA DEBUG
}
```

---

### Problema 4: Email se duplica (llega desde Zipnova Y desde el sistema)

**Síntomas**:
- Cliente recibe 2 emails por cada cambio de estado
- Uno del sistema y otro de Zipnova

**Causa**:
- `send_customer_email` está en `true`

**Solución**:
```json
// app/config/shipping.json
{
  "options": {
    "send_customer_email": false  // ← Cambiar a false
  }
}
```

**Verificar en logs**:
```bash
grep "Customer Email Removed" /home/pablo/shop-v2/logs/zipnova/*.log
```

Debería aparecer:
```
[2025-12-28 14:30:15] [ORDER: 12345] Customer Email Removed: {
  "note": "Email no enviado a Zipnova (send_customer_email=false)"
}
```

---

### Problema 5: Estado se actualiza pero no envía email

**Síntomas**:
- Estado cambia correctamente (visible en admin)
- No llega email al cliente

**Causa**:
- La función `send_shipping_preparation_email()` o `send_shipping_status_notification()` no se está ejecutando

**Solución**:

1. **Verificar que la función existe**:
```bash
grep -n "function send_shipping_preparation_email" app/includes/email.php
```

2. **Agregar logs temporales**:
```php
// En zipnova_get_label(), después de actualizar estado:
error_log("DEBUG: Enviando email de preparación para orden: " . $ord['id']);
$email_sent = send_shipping_preparation_email($ord);
error_log("DEBUG: Email enviado: " . ($email_sent ? 'SI' : 'NO'));
```

3. **Ver logs de PHP**:
```bash
tail -f /var/log/apache2/error.log | grep "DEBUG:"
```

---

### Problema 6: Logs no tienen order_id

**Síntomas**:
- Los logs de Zipnova no muestran `[ORDER: 12345]`
- Los archivos JSON no tienen prefijo `ORDER-12345_`

**Causa**:
- Las funciones de Zipnova no están recibiendo el `order_id`

**Solución**:

Verificar que las llamadas a funciones de Zipnova incluyen el order_id:

```php
// ✅ CORRECTO
zipnova_create_shipment($shipment_data, $order['order_number']);
zipnova_get_label($shipment_id, 'pdf', $order_number);

// ❌ INCORRECTO
zipnova_create_shipment($shipment_data);  // Sin order_id
zipnova_get_label($shipment_id);  // Sin order_id
```

---

## Diagrama de Datos

### Estructura de Orden con Shipping

```json
{
  "id": "order_123",
  "order_number": "ORD-12345",
  "customer_email": "cliente@email.com",
  "customer_name": "Juan Pérez",
  "delivery_method": "shipping",
  "shipping": {
    "method": "standard",
    "service_name": "Envío Estándar",
    "cost": 2500,
    "carrier": "ZNVA",
    "carrier_shipment_id": "67890",
    "carrier_status": "in_transit",
    "tracking_id": "ZNVA1234567890",
    "status": "en_transito",
    "address": {
      "name": "Juan Pérez",
      "street": "Av. Corrientes 1234",
      "city": "Buenos Aires",
      "province": "Buenos Aires",
      "postal_code": "C1043AAZ",
      "country": "AR",
      "phone": "+54 11 1234-5678"
    },
    "estimated_delivery": "3-5",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T14:20:00Z",
    "history": [
      {
        "date": "2024-01-15T10:30:00Z",
        "updates": {
          "carrier_status": "pending",
          "base_status": "pendiente"
        }
      },
      {
        "date": "2024-01-15T12:00:00Z",
        "updates": {
          "carrier_status": "ready",
          "base_status": "en_preparacion"
        }
      },
      {
        "date": "2024-01-15T14:20:00Z",
        "updates": {
          "carrier_status": "in_transit",
          "base_status": "en_transito"
        }
      }
    ]
  }
}
```

### Estructura de Shipment Local

```json
{
  "67890": {
    "zipnova_id": "67890",
    "reference": "ORD-12345",
    "status": "en_transito",
    "created_at": "2024-01-15 10:30:00",
    "label_url": "/data/labels/label_67890_1640000000.pdf",
    "label_format": "pdf",
    "label_generated_at": "2024-01-15 12:00:00",
    "data": {
      "id": "67890",
      "tracking_number": "ZNVA1234567890",
      "service": {
        "name": "Envío Estándar",
        "type": "standard_delivery"
      }
    }
  }
}
```

---

## Checklist de Implementación

### Configuración Inicial

- [ ] Configurar credenciales de Zipnova en `shipping.json`
- [ ] Configurar `send_customer_email: false`
- [ ] Configurar `webhook_secret`
- [ ] Habilitar notificaciones de email en `email.json`
- [ ] Configurar SMTP en `/home/notification_credentials.json`
- [ ] Registrar URL del webhook en Zipnova

### Pruebas

- [ ] Crear orden de prueba con envío
- [ ] Verificar creación de shipment en Zipnova
- [ ] Generar etiqueta desde admin
- [ ] Verificar cambio de estado a "en_preparacion"
- [ ] Verificar recepción de email "En preparación"
- [ ] Simular webhook con Postman (in_transit)
- [ ] Verificar cambio de estado a "en_transito"
- [ ] Verificar recepción de email "En camino"
- [ ] Verificar logs en `/logs/zipnova/`
- [ ] Verificar archivos JSON en `/logs/zipnova-responses/`

### Monitoreo

- [ ] Configurar alertas para webhooks fallidos
- [ ] Monitorear logs diariamente
- [ ] Verificar que todos los emails están llegando
- [ ] Revisar órdenes con estados inconsistentes

---

## Referencias

- **API de Zipnova**: https://api.zipnova.com.ar/v2/docs
- **Código fuente**:
  - Estados: `app/includes/carriers.php:1125-1167`
  - Emails: `app/includes/email.php:530-545`
  - Webhooks: `app/pages/api/shipping.php:293-372`
  - Generación de etiqueta: `app/includes/carriers.php:629-800`

---

**Última actualización**: 2025-12-28
**Versión del documento**: 1.0
**Autor**: Sistema de documentación automática
