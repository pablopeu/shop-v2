# Sistema de Cola de Emails - Documentación

## Descripción

El sistema de cola de emails permite enviar emails de forma asíncrona, mejorando significativamente el rendimiento del checkout y las operaciones del panel de administración.

**Antes**:
- Checkout: 2-5 segundos (bloqueado por envío de email)
- Cambio de estado: 2-5 segundos por orden
- Bulk actions: 2-5 segundos × cantidad de órdenes

**Después**:
- Checkout: ~650ms ✅
- Cambio de estado: instantáneo ✅
- Bulk actions: instantáneo ✅
- Emails se envían en background cada 1-5 minutos

---

## Configuración del Cron Job

### 1. Editar Crontab

```bash
crontab -e
```

### 2. Agregar la siguiente línea

**Opción 1: Ejecutar cada minuto (recomendado para producción)**
```bash
*/1 * * * * /usr/bin/php /home/pablo/shop-v2/app/scripts/process-email-queue.php >> /home/pablo/shop-v2/logs/email-queue.log 2>&1
```

**Opción 2: Ejecutar cada 5 minutos (menor carga del servidor)**
```bash
*/5 * * * * /usr/bin/php /home/pablo/shop-v2/app/scripts/process-email-queue.php >> /home/pablo/shop-v2/logs/email-queue.log 2>&1
```

### 3. Crear directorio de logs

```bash
mkdir -p /home/pablo/shop-v2/logs
chmod 755 /home/pablo/shop-v2/logs
```

### 4. Verificar que funciona

Después de configurar el cron, espera 1-5 minutos y revisa el log:

```bash
tail -f /home/pablo/shop-v2/logs/email-queue.log
```

Deberías ver algo como:
```
[2024-01-15 10:30:00] Starting email queue processing...
[2024-01-15 10:30:01] Queue processing completed in 234.56ms
  - Sent: 3
  - Failed: 0
  - Pending: 0
  - Skipped: 2
```

---

## Cómo Funciona

### 1. Envío de Email (Inmediato)

Cuando el sistema necesita enviar un email:

```php
// Antes (síncrono - bloqueaba 2-5 segundos):
send_order_confirmation_email($order);

// Ahora (asíncrono - tarda <1ms):
queue_email('order_confirmation', ['order' => $order], 'high');
```

El email se guarda en `app/data/email_queue.json` y retorna inmediatamente.

### 2. Procesamiento en Background

El cron job ejecuta `process-email-queue.php` cada 1-5 minutos:

1. Lee `email_queue.json`
2. Procesa hasta 10 emails pendientes
3. Marca como enviados los exitosos
4. Reintenta los fallidos (hasta 3 intentos)
5. Mueve emails irrecuperables a `email_failed.json`

### 3. Monitoreo en Dashboard

El panel de administración muestra:
- Widget con contador de emails fallidos
- Alerta visible si hay errores
- Página dedicada `/admin/?page=emails-fallidos` con:
  - Lista de emails fallidos
  - Detalles del error
  - Opción para reintentar
  - Opción para limpiar registro

---

## Prioridades de Emails

Los emails se procesan según su prioridad:

- **high**: Confirmaciones de pago, nuevas órdenes (procesados primero)
- **normal**: Notificaciones de estado (procesados segundo)
- **low**: Notificaciones secundarias (procesados último)

---

## Tipos de Email Soportados

| Tipo | Descripción | Prioridad |
|------|-------------|-----------|
| `order_confirmation` | Confirmación de pedido al cliente | high |
| `admin_new_order` | Nueva orden al admin | high |
| `payment_approved` | Pago aprobado | high |
| `payment_rejected` | Pago rechazado | normal |
| `payment_pending` | Pago pendiente | normal |
| `order_shipped` | Pedido enviado | normal |
| `order_in_delivery` | Pedido en reparto | normal |
| `order_delivered` | Pedido entregado | normal |
| `order_paid` | Pedido pagado | normal |
| `shipping_preparation` | Preparación de envío | normal |
| `admin_chargeback` | Alerta de contracargo | high |

---

## Monitoreo y Troubleshooting

### Ver Emails en Cola

```bash
cat /home/pablo/shop-v2/app/data/email_queue.json | python3 -m json.tool
```

### Ver Emails Fallidos

```bash
cat /home/pablo/shop-v2/app/data/email_failed.json | python3 -m json.tool
```

### Ver Log del Cron

```bash
# Ver últimas 50 líneas
tail -n 50 /home/pablo/shop-v2/logs/email-queue.log

# Ver en tiempo real
tail -f /home/pablo/shop-v2/logs/email-queue.log

# Ver solo errores
grep "ERROR\|FAILED" /home/pablo/shop-v2/logs/email-queue.log
```

### Ejecutar Manualmente (para testing)

```bash
/usr/bin/php /home/pablo/shop-v2/app/scripts/process-email-queue.php
```

---

## Estructura de Archivos

```
shop-v2/
├── app/
│   ├── data/
│   │   ├── email_queue.json         # Cola de emails pendientes
│   │   └── email_failed.json        # Emails fallidos (dashboard)
│   ├── includes/
│   │   └── email.php                # Funciones queue_email(), process_email_queue()
│   ├── scripts/
│   │   ├── process-email-queue.php  # Script ejecutado por cron
│   │   └── README-EMAIL-QUEUE.md    # Esta documentación
│   └── pages/admin/
│       └── emails-fallidos.php      # Página de administración
└── logs/
    └── email-queue.log              # Log del cron job
```

---

## Funciones Disponibles

### `queue_email($type, $data, $priority = 'normal')`

Agrega un email a la cola.

**Parámetros**:
- `$type` (string): Tipo de email (ver tabla arriba)
- `$data` (array): Datos del email (debe incluir `['order' => $order]`)
- `$priority` (string): 'high', 'normal', o 'low'

**Ejemplo**:
```php
queue_email('order_confirmation', ['order' => $order], 'high');
```

### `process_email_queue($batch_size = 10)`

Procesa emails en la cola (llamado por cron).

**Retorna**:
```php
[
    'sent' => 3,      // Emails enviados exitosamente
    'failed' => 0,    // Emails que fallaron definitivamente
    'pending' => 2,   // Emails pendientes
    'skipped' => 5    // Emails ya procesados
]
```

### `get_failed_emails_count()`

Obtiene cantidad de emails fallidos.

### `get_failed_emails()`

Obtiene lista de emails fallidos con detalles.

### `retry_failed_email($email_id)`

Reintenta un email fallido.

### `clear_failed_emails()`

Limpia el registro de emails fallidos.

---

## Migración desde Sistema Síncrono

Todos los emails del sistema fueron migrados automáticamente:

- `app/pages/frontend/checkout-new.php` ✅
- `app/includes/admin/ventas/actions.php` ✅
- `app/pages/admin/reprocesar-pago-mp.php` ✅

No se requiere acción adicional - el sistema ya está usando la cola.

---

## FAQ

**¿Los emails se envían inmediatamente?**
No, se envían cada 1-5 minutos (según configuración del cron).

**¿Qué pasa si un email falla?**
Se reintenta hasta 3 veces. Si sigue fallando, aparece en el dashboard.

**¿Puedo ver qué emails están en cola?**
Sí, en `app/data/email_queue.json` o ejecutando el script manualmente.

**¿El cron afecta el rendimiento del sitio?**
No, se ejecuta en background y procesa solo 10 emails por vez.

**¿Qué pasa si borro email_queue.json?**
Se crea automáticamente vacío. Los emails en cola se pierden.

**¿Puedo cambiar la frecuencia del cron?**
Sí, edita el crontab. Recomendado: 1-5 minutos.

---

## Soporte

Si encuentras problemas:

1. Revisa el log: `tail -f /home/pablo/shop-v2/logs/email-queue.log`
2. Verifica emails fallidos en: `/admin/?page=emails-fallidos`
3. Ejecuta manualmente el script para ver errores en tiempo real
4. Verifica que el cron esté configurado: `crontab -l`
