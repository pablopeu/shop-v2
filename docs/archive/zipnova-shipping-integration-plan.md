# Plan de Implementación: Integración de Envíos con Zipnova

**Branch:** `feature/zipnova-shipping-integration`
**Fecha de Creación:** 2025-12-22
**Proveedor:** Zipnova
**Documentación API:** `/zipnova/`

---

## 1. Introducción y Alcance

### 1.1 Objetivo
Implementar la funcionalidad completa de envíos/logística en el sistema de e-commerce, integrando con la API de Zipnova como proveedor de servicios de envío.

### 1.2 Alcance del Proyecto
- Cotización de envíos en tiempo real durante el checkout
- Creación automática de órdenes de envío al confirmar compra
- Panel de administración para gestionar configuración de Zipnova
- Seguimiento de estados de envío
- Gestión de webhooks para actualizaciones automáticas
- Integración con el flujo de checkout existente

### 1.3 Funcionalidades Principales
1. **Cotización de Envíos**: Calcular costos de envío según destino, dimensiones y peso
2. **Creación de Envíos**: Generar órdenes de envío automáticamente
3. **Seguimiento**: Tracking de envíos con estados actualizados
4. **Administración**: Panel backend para configurar credenciales y opciones
5. **Webhooks**: Recepción de actualizaciones de estado desde Zipnova

---

## 2. Documentación de la API de Zipnova

### 2.1 URLs Base
- **Argentina (Producción):** `https://api.zipnova.com.ar/v2/`
- **Chile:** `https://api.zipnova.cl/v2/`
- **México:** `https://api.zipnova.com.mx/v2/`

**IMPORTANTE - Credenciales Sandbox/Producción:**
- No existe URL de sandbox separada. Se usa la misma URL de producción.
- Las credenciales de sandbox y producción son las mismas (NO hay que crear campos ni variables nuevos).
- Zipnova pasa las mismas credenciales a producción cuando sea necesario.
- El sistema usa un flag `mode` en la configuración para diferenciar entre sandbox y producción, pero las credenciales son idénticas.

### 2.2 Autenticación
- **Método:** HTTP Basic Authentication
- **Credenciales:** API Token (usuario) y API Secret (contraseña)
- **Generar credenciales:** Configuración > Integraciones > Gestionar credenciales y webhooks

**Headers requeridos:**
```
Accept: application/json
Authorization: Basic {base64(api_token:api_secret)}
Content-Type: application/json
```

**Versión actual de API:** v2

### 2.3 Endpoints Principales

#### Cotizar Envíos
```
POST /shipments/quotes
```
**Request:**
```json
{
  "origin": {
    "postal_code": "1425",
    "city": "CABA",
    "province": "Buenos Aires",
    "country": "AR"
  },
  "destination": {
    "postal_code": "5000",
    "city": "Córdoba",
    "province": "Córdoba",
    "country": "AR"
  },
  "packages": [
    {
      "weight": 2.5,
      "length": 30,
      "width": 20,
      "height": 15,
      "declared_value": 50000
    }
  ]
}
```

#### Crear Envío
```
POST /shipments
```
**Request:**
```json
{
  "service_id": "standard",
  "origin": {...},
  "destination": {...},
  "packages": [...],
  "customer": {
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "phone": "+54911xxxx"
  },
  "reference": "ORDER-12345"
}
```

#### Consultar Estado
```
GET /shipments/{shipment_id}
```

### 2.4 Estados de Envío
- `pending`: Pendiente de recolección
- `in_transit`: En tránsito
- `out_for_delivery`: En reparto
- `delivered`: Entregado
- `failed`: Fallido
- `returned`: Devuelto

---

## 3. Cambios Necesarios en el Checkout

### 3.1 Archivo: `app/pages/frontend/checkout-new.php`

#### Modificaciones Requeridas:

1. **Agregar sección de método de envío** (línea ~200)
   - Radio buttons para seleccionar entre métodos de envío
   - Mostrar cotizaciones de Zipnova en tiempo real
   - Calcular y mostrar costo total con envío incluido

2. **Nuevos campos de dirección de envío** (después del formulario de contacto)
   - Dirección completa
   - Ciudad
   - Provincia/Estado
   - Código postal
   - País
   - Referencia/Observaciones de entrega

3. **JavaScript para cotización dinámica**
   - AJAX call al backend para obtener cotizaciones
   - Actualización del total al seleccionar método de envío
   - Validación de código postal

4. **Modificar flujo de procesamiento de orden**
   - Guardar datos de envío en la orden
   - Crear envío en Zipnova después de confirmar pago
   - Almacenar tracking_id de Zipnova

### 3.2 Campos de Formulario Necesarios

```html
<!-- Sección de Método de Envío -->
<div class="shipping-method-section">
  <h3>Método de Envío</h3>

  <div class="shipping-address">
    <input type="text" name="shipping_address" placeholder="Dirección" required>
    <input type="text" name="shipping_city" placeholder="Ciudad" required>
    <select name="shipping_province" required>
      <option value="">Seleccionar provincia</option>
      <!-- Opciones de provincias -->
    </select>
    <input type="text" name="shipping_postal_code" placeholder="Código Postal" required>
    <select name="shipping_country">
      <option value="AR">Argentina</option>
    </select>
    <textarea name="shipping_notes" placeholder="Referencias de entrega (opcional)"></textarea>
  </div>

  <div class="shipping-quotes" id="shipping-quotes">
    <!-- Cotizaciones dinámicas de Zipnova -->
  </div>

  <div class="shipping-cost-summary">
    <p>Subtotal: <span id="subtotal">$0.00</span></p>
    <p>Envío: <span id="shipping-cost">$0.00</span></p>
    <p><strong>Total: <span id="total">$0.00</span></strong></p>
  </div>
</div>
```

---

## 4. Nuevos Datos y Estructuras

### 4.1 Configuración de Zipnova
**Archivo:** `app/config/shipping.json`

```json
{
  "zipnova": {
    "enabled": true,
    "mode": "sandbox",
    "credentials": {
      "client_id": "",
      "client_secret": "",
      "access_token": "",
      "refresh_token": "",
      "token_expires_at": ""
    },
    "origin": {
      "name": "Tu Tienda",
      "address": "Calle Ejemplo 123",
      "city": "CABA",
      "province": "Buenos Aires",
      "postal_code": "1425",
      "country": "AR",
      "phone": "+54911xxxx",
      "email": "info@tutienda.com"
    },
    "default_package": {
      "weight": 1,
      "length": 20,
      "width": 15,
      "height": 10
    },
    "webhook_secret": "",
    "auto_create_shipment": true
  }
}
```

### 4.2 Estructura de Orden con Envío
**Modificar:** `app/includes/orders.php`

Agregar campos a la orden:
```php
[
  // Campos existentes...
  'shipping' => [
    'method' => 'zipnova_standard',
    'cost' => 2500.00,
    'tracking_id' => 'ZPN-123456789',
    'status' => 'pending',
    'estimated_delivery' => '2025-12-28',
    'address' => [
      'street' => 'Calle Ejemplo 456',
      'city' => 'Córdoba',
      'province' => 'Córdoba',
      'postal_code' => '5000',
      'country' => 'AR',
      'notes' => 'Entregar en recepción'
    ],
    'zipnova_shipment_id' => 'shp_abc123',
    'created_at' => '2025-12-22T10:00:00Z',
    'updated_at' => '2025-12-22T10:00:00Z'
  ]
]
```

### 4.3 Nuevo Archivo de Funciones
**Crear:** `app/includes/zipnova.php`

Funciones necesarias:
- `zipnova_authenticate()` - Obtener access token
- `zipnova_refresh_token()` - Renovar token
- `zipnova_get_quotes($origin, $destination, $packages)` - Cotizar envíos
- `zipnova_create_shipment($shipment_data)` - Crear envío
- `zipnova_get_shipment($shipment_id)` - Consultar estado
- `zipnova_cancel_shipment($shipment_id)` - Cancelar envío
- `zipnova_webhook_verify($payload, $signature)` - Verificar webhook

---

## 5. Nueva Sección en el Backend

### 5.1 Panel de Administración
**Crear:** `app/pages/admin/shipping-config.php`

#### Secciones del Panel:

1. **Configuración de Credenciales**
   - Client ID
   - Client Secret
   - Modo (Sandbox/Producción)
   - Botón para probar conexión
   - Estado de autenticación (token válido/expirado)

2. **Configuración de Origen**
   - Nombre del remitente
   - Dirección completa
   - Datos de contacto
   - Guardar como origen por defecto

3. **Configuración de Paquetes**
   - Dimensiones por defecto
   - Peso por defecto
   - Permitir configurar por producto

4. **Opciones de Envío**
   - Auto-crear envío al confirmar orden
   - Métodos de envío habilitados
   - Agregar margen al costo de envío (%)

5. **Webhooks**
   - URL del webhook
   - Secret para verificación
   - Log de webhooks recibidos

6. **Lista de Envíos**
   - Ver todos los envíos creados
   - Filtrar por estado
   - Ver tracking y detalles
   - Botón para sincronizar estado

### 5.2 API Endpoints del Backend
**Crear:** `app/pages/api/shipping.php`

Endpoints necesarios:
```
GET  /api/shipping/quotes         - Obtener cotizaciones
POST /api/shipping/create         - Crear envío
GET  /api/shipping/track/:id      - Consultar tracking
POST /api/shipping/webhook        - Recibir webhooks
GET  /api/shipping/test-connection - Probar conexión
```

### 5.3 Menú de Navegación
**Modificar:** Agregar enlace en el menú de administración

```php
// En el sidebar del admin
<a href="<?= url('/admin/shipping-config') ?>">
  <i class="icon-truck"></i>
  Configuración de Envíos
</a>
```

---

## 6. Plan de Implementación Paso a Paso

### Fase 1: Configuración Inicial (Prioridad: Alta)
- [ ] Crear archivo `app/config/shipping.json`
- [ ] Crear archivo `app/includes/zipnova.php` con funciones base
- [ ] Implementar autenticación OAuth con Zipnova
- [ ] Crear tabla/estructura de datos para envíos

### Fase 2: Backend de Administración (Prioridad: Alta)
- [ ] Crear `app/pages/admin/shipping-config.php`
- [ ] Implementar formulario de configuración de credenciales
- [ ] Implementar formulario de configuración de origen
- [ ] Agregar botón de prueba de conexión
- [ ] Agregar menú en el sidebar del admin

### Fase 3: API Endpoints (Prioridad: Alta)
- [ ] Crear `app/pages/api/shipping.php`
- [ ] Implementar endpoint `/api/shipping/quotes`
- [ ] Implementar endpoint `/api/shipping/create`
- [ ] Implementar endpoint `/api/shipping/track/:id`
- [ ] Implementar endpoint `/api/shipping/webhook`
- [ ] Agregar validación de datos y manejo de errores

### Fase 4: Modificaciones en Checkout (Prioridad: Alta)
- [ ] Agregar campos de dirección de envío en `checkout-new.php`
- [ ] Implementar JavaScript para cotización dinámica
- [ ] Agregar sección de selección de método de envío
- [ ] Actualizar cálculo de total con costo de envío
- [ ] Modificar flujo de procesamiento de orden

### Fase 5: Integración con Órdenes (Prioridad: Alta)
- [ ] Modificar `app/includes/orders.php` para incluir datos de envío
- [ ] Implementar creación automática de envío al confirmar orden
- [ ] Almacenar tracking_id en la orden
- [ ] Agregar página de tracking para el cliente

### Fase 6: Webhooks y Actualizaciones (Prioridad: Media)
- [ ] Implementar recepción de webhooks
- [ ] Verificar firma de webhooks
- [ ] Actualizar estado de envío automáticamente
- [ ] Enviar notificaciones al cliente sobre cambios de estado

### Fase 7: Panel de Gestión de Envíos (Prioridad: Media)
- [ ] Crear página de lista de envíos en admin
- [ ] Implementar filtros por estado
- [ ] Agregar vista de detalles de envío
- [ ] Implementar sincronización manual de estado
- [ ] Agregar opción de cancelar envío

### Fase 8: Testing y Optimización (Prioridad: Media)
- [ ] Probar flujo completo en sandbox
- [ ] Validar manejo de errores
- [ ] Optimizar performance de cotizaciones
- [ ] Agregar logs y debugging
- [ ] Documentar uso para el usuario final

### Fase 9: Características Avanzadas (Prioridad: Baja)
- [ ] Configurar dimensiones y peso por producto
- [ ] Implementar múltiples orígenes de envío
- [ ] Agregar opción de retiro en sucursal
- [ ] Integrar con sistema de impresión de etiquetas
- [ ] Dashboard con métricas de envíos

---

## 7. Consideraciones Técnicas

### 7.1 Seguridad
- Almacenar credenciales de forma segura (NO en git)
- Validar firma de webhooks
- Usar HTTPS para todas las comunicaciones
- Implementar rate limiting en endpoints de API

### 7.2 Performance
- Cache de tokens de autenticación
- Cache de cotizaciones por corto tiempo (5 min)
- Procesamiento asíncrono para creación de envíos
- Implementar retry logic para llamadas a la API

### 7.3 Manejo de Errores
- Log de errores de API
- Mensajes de error claros para el usuario
- Fallback si Zipnova no está disponible
- Notificaciones al admin en caso de problemas

### 7.4 Compatibilidad
- Mantener compatibilidad con checkout existente
- Permitir deshabilitar envíos de Zipnova
- Soporte para órdenes sin envío (productos digitales)

### 7.5 Base de Datos
El sistema actual usa archivos JSON. Para envíos se puede considerar:
- **Opción 1:** Continuar con JSON (archivo por envío en `/data/shipments/`)
- **Opción 2:** Migrar a SQLite para mejor performance
- **Recomendación:** Empezar con JSON, migrar a SQLite si hay problemas de performance

### 7.6 Logs y Auditoría
- Log de todas las llamadas a la API de Zipnova
- Log de webhooks recibidos
- Historial de cambios de estado
- Formato: `logs/zipnova-{date}.log`

---

## 8. Archivos a Crear/Modificar

### Crear:
- `app/config/shipping.json`
- `app/includes/zipnova.php`
- `app/pages/admin/shipping-config.php`
- `app/pages/api/shipping.php`
- `app/pages/frontend/tracking.php`
- `public_html/assets/js/shipping.js`
- `public_html/assets/css/shipping.css`
- `data/shipments/` (directorio)
- `logs/zipnova/` (directorio)

### Modificar:
- `app/pages/frontend/checkout-new.php`
- `app/includes/orders.php`
- `app/includes/functions.php` (helpers de envío)
- `app/pages/admin/[sidebar o menú principal]`
- `app/pages/admin/orders-list.php` (agregar columna de tracking)
- `app/pages/admin/order-detail.php` (mostrar info de envío)

---

## 9. Datos de Prueba (Sandbox)

### Credenciales de Prueba
```
NOTA: Las credenciales de sandbox ya están creadas.
NO se requieren campos adicionales ya que son las mismas que para producción.
Zipnova pasa las credenciales a producción cuando sea necesario.

Client ID: [Configurado en app/config/shipping.json]
Client Secret: [Configurado en app/config/shipping.json]
```

### Direcciones de Prueba
**Origen:**
```
Nombre: Test Store
Dirección: Av. Corrientes 1234
Ciudad: CABA
Provincia: Buenos Aires
CP: 1043
País: AR
```

**Destino:**
```
Nombre: Juan Pérez
Dirección: Av. Colón 567
Ciudad: Córdoba
Provincia: Córdoba
CP: 5000
País: AR
```

### Paquete de Prueba
```
Peso: 2kg
Largo: 30cm
Ancho: 20cm
Alto: 15cm
Valor declarado: ARS 50000
```

---

## 10. Criterios de Aceptación

### Funcionalidad Mínima (MVP):
- [x] Usuario puede ver cotizaciones de envío en checkout
- [x] Usuario puede seleccionar método de envío
- [x] Sistema crea envío automáticamente al confirmar orden
- [x] Admin puede configurar credenciales de Zipnova
- [x] Admin puede ver lista de envíos
- [x] Cliente puede ver tracking de su envío

### Nice to Have:
- [ ] Actualización automática de estado vía webhooks
- [ ] Impresión de etiquetas desde el admin
- [ ] Configuración de dimensiones por producto
- [ ] Múltiples orígenes de envío
- [ ] Dashboard con métricas

---

## 11. Estimación de Tiempo

| Fase | Tiempo Estimado |
|------|----------------|
| Fase 1: Configuración Inicial | 4-6 horas |
| Fase 2: Backend de Administración | 8-10 horas |
| Fase 3: API Endpoints | 6-8 horas |
| Fase 4: Modificaciones en Checkout | 8-10 horas |
| Fase 5: Integración con Órdenes | 6-8 horas |
| Fase 6: Webhooks | 4-6 horas |
| Fase 7: Panel de Gestión | 6-8 horas |
| Fase 8: Testing | 8-10 horas |
| **Total** | **50-66 horas** |

---

## 12. Recursos Adicionales

### Documentación de Zipnova
- Carpeta local: `/zipnova/`
- URLs y autenticación: `zipnova/urls-y-autenticacion.md`
- Cotizar envíos: `zipnova/cotizar-envios.md`
- Crear envíos: `zipnova/crear-envios.md`
- Estados de envío: `zipnova/estados-de-envio.md`
- Configuración: `zipnova/configuracion.md`

### Contacto Soporte Zipnova
- Web: https://www.zipnova.com
- Ayuda: https://ayuda.zipnova.com
- Documentación: https://docs.zipnova.com

---

## 13. Notas Finales

- Este plan es flexible y puede ajustarse según las necesidades
- Se recomienda implementar por fases para tener entregables funcionales
- Priorizar MVP antes de características avanzadas
- Mantener comunicación con Zipnova para resolver dudas de integración
- Documentar todo el proceso para futuras integraciones

---

**Última actualización:** 2025-12-22
**Estado:** En Planificación
**Próximo paso:** Revisar y aprobar plan con el equipo
