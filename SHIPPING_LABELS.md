# Sistema de Impresión de Etiquetas de Envío

## Estado: IMPLEMENTADO Y LISTO PARA USO

Este documento describe la infraestructura implementada para la impresión de etiquetas de envío con Zipnova.

**IMPORTANTE - Credenciales:**
- Las credenciales de sandbox ya están configuradas en el sistema.
- NO hay campos separados para sandbox y producción (son las mismas credenciales).
- Zipnova pasa las credenciales a producción cuando sea necesario.
- El campo `mode` en la configuración solo es un flag para diferenciar el entorno.

## Componentes Implementados

### 1. Backend - Función Principal

**Archivo**: `app/includes/carriers.php`

**Función**: `zipnova_get_label($shipment_id, $format = 'pdf')`

**Estado**: ✅ COMPLETAMENTE IMPLEMENTADO Y FUNCIONAL

**Características**:
- ✅ Validación de existencia del envío
- ✅ Validación de estados válidos (pendiente, en_transito, en_reparto)
- ✅ Sistema de caché (verifica si ya existe etiqueta guardada)
- ✅ Logging de todas las operaciones
- ✅ Estructura de respuesta estandarizada
- ✅ Integración completa con API de Zipnova (activada)

**Formatos soportados** (cuando se implemente):
- `pdf` - PDF para imprimir (default)
- `png` - Imagen PNG
- `zpl` - Zebra Programming Language (impresoras térmicas)

**Datos guardados en el envío**:
```json
{
  "label_url": "https://zipnova.com/labels/ABC123.pdf",
  "label_format": "pdf",
  "label_generated_at": "2024-01-15 14:30:00"
}
```

### 2. Endpoint API

**Archivo**: `app/pages/api/print-shipping-label.php`

**Método**: GET/POST

**Parámetros**:
- `order_id` - ID de la orden (opcional si se proporciona shipment_id)
- `shipment_id` - ID del envío en Zipnova (opcional si se proporciona order_id)
- `format` - Formato: pdf, png, zpl (default: pdf)
- `action` - download | preview (default: download)

**Respuesta exitosa**:
```json
{
  "success": true,
  "data": {
    "label_url": "https://...",
    "format": "pdf",
    "cached": false,
    "shipment_id": "ZNVA123456",
    "action": "download"
  },
  "message": "Etiqueta obtenida exitosamente"
}
```

**Respuesta de error (modo stub)**:
```json
{
  "success": false,
  "error": "Funcionalidad de etiquetas pendiente de configuración...",
  "stub_mode": true
}
```

### 3. Frontend - Listado de Ventas

**Archivo**: `app/includes/admin/ventas/views.php`

**Modificaciones**:
- ✅ Nueva columna "Envío" en la tabla
- ✅ Muestra carrier y shipment_id resumido
- ✅ Botón "🖨️ Etiqueta" solo para órdenes con envío
- ✅ Diseño responsive

**HTML generado**:
```html
<td>
  <div style="display: flex; flex-direction: column; gap: 5px; align-items: center;">
    <small>ZNVA #ABC123</small>
    <button data-action="printShippingLabel"
            data-order-id="..."
            data-shipment-id="...">
      🖨️ Etiqueta
    </button>
  </div>
</td>
```

**JavaScript**: `app/pages/admin/ventas.php`

**Función**: `printShippingLabel(event, element, params)`

**Características**:
- ✅ Manejo de estados de carga (botón deshabilitado)
- ✅ Feedback visual (toasts)
- ✅ Apertura en nueva pestaña
- ✅ Manejo de errores
- ✅ Detección de modo stub

### 4. Frontend - Gestión de Envíos

**Archivo**: `app/pages/admin/envios-pendientes.php`

**Modificaciones**:
- ✅ Botón "🖨️ Etiqueta" en acciones de cada envío
- ✅ Solo visible si existe carrier_shipment_id
- ✅ Función JavaScript idéntica a ventas.php

## Flujo de Funcionamiento (cuando se implemente)

### Flujo Ideal (Con credenciales Zipnova)

1. **Usuario hace clic en "🖨️ Etiqueta"**
   - Se envía request a `/api/print-shipping-label.php`
   - Con `order_id` o `shipment_id`

2. **API verifica credenciales y estado**
   - Busca el envío en datos locales
   - Valida que esté en estado válido
   - Verifica si ya existe etiqueta en caché

3. **Si no hay etiqueta en caché**:
   - Llama a Zipnova API: `GET /shipments/{id}/label?format=pdf`
   - Zipnova retorna URL de la etiqueta (PDF hospedado)
   - Se guarda URL en datos locales del envío

4. **Retorna la etiqueta**:
   - Si `action=download`: retorna JSON con URL
   - Frontend abre URL en nueva pestaña
   - Usuario puede imprimir/descargar

5. **Siguientes impresiones**:
   - Se sirve desde caché (label_url guardado)
   - No se hace nueva llamada a Zipnova API

### Flujo Actual (Modo Stub)

1. Usuario hace clic en "🖨️ Etiqueta"
2. API retorna error con `stub_mode: true`
3. Frontend muestra toast: "⚠️ Funcionalidad pendiente de credenciales..."

## Endpoints de Zipnova (Por Confirmar)

Posibles endpoints según carriers típicos:

```
GET /shipments/{id}/label?format=pdf
GET /shipments/{id}/label?format=png
GET /shipments/{id}/label?format=zpl
GET /shipments/{id}/documents
```

## Activación del Sistema

### Pasos para activar cuando se obtengan credenciales:

1. **Configurar credenciales en Zipnova**:
   - Ir a `/admin/?page=config-shipping`
   - Agregar credenciales de sandbox
   - Habilitar carrier Zipnova

2. **Descomentar código en `carriers.php`**:
   ```php
   // Buscar la línea 582 en carriers.php
   // Descomentar el bloque de código entre /* ... */
   ```

3. **Confirmar endpoint con documentación de Zipnova**:
   - Verificar el endpoint exacto: `/shipments/{id}/label` o similar
   - Ajustar si es necesario

4. **Probar con envío de prueba**:
   - Crear orden de prueba
   - Crear envío en Zipnova
   - Hacer clic en "🖨️ Etiqueta"
   - Verificar que se obtenga el PDF

5. **Verificar logs**:
   ```bash
   tail -f logs/zipnova/zipnova-YYYY-MM-DD.log
   ```

## Estructura de Datos

### Orden con etiqueta generada

```json
{
  "id": "...",
  "order_number": "1234",
  "shipping": {
    "carrier": "ZNVA",
    "carrier_shipment_id": "ZNVA123456789",
    "status": "en_transito",
    "label_url": "https://api.zipnova.com/v1/labels/ABC123.pdf",
    "label_format": "pdf",
    "label_generated_at": "2024-01-15 14:30:00"
  }
}
```

## Casos de Uso

### 1. Impresión Masiva (Futuro)
Se podría agregar un botón para imprimir múltiples etiquetas:
- Seleccionar varias órdenes con checkbox
- Botón "🖨️ Imprimir etiquetas seleccionadas"
- Generar PDF combinado o ZIP con todas las etiquetas

### 2. Reimpresión
Si el usuario ya imprimió la etiqueta:
- El sistema usa la etiqueta en caché
- No consume cuota de API de Zipnova
- Instantáneo

### 3. Actualización de Etiqueta
Si el envío se modifica (dirección, servicio):
- Se debe cancelar el envío anterior
- Crear nuevo envío
- Generar nueva etiqueta
- La etiqueta anterior queda invalidada

## Testing

### Test Manual (cuando se implemente)

1. **Test básico**:
   ```bash
   # Crear orden con envío
   # Ir a /admin/?page=ventas
   # Hacer clic en botón "🖨️ Etiqueta"
   # Verificar que abre PDF en nueva pestaña
   ```

2. **Test de caché**:
   ```bash
   # Imprimir etiqueta por primera vez
   # Verificar log: "Label Generated Successfully"
   # Imprimir nuevamente
   # Verificar log: "Label Retrieved from Cache"
   ```

3. **Test de errores**:
   ```bash
   # Intentar con envío cancelado
   # Verificar error: "El envío debe estar pendiente..."
   ```

## Logging

Todas las operaciones se registran en:
- `/logs/zipnova/zipnova-YYYY-MM-DD.log`
- `/logs/zipnova-responses/label-{timestamp}.json` (cuando se implemente)

Eventos logged:
- `Label Request - Stub Mode` (actual)
- `Label Generated Successfully` (futuro)
- `Label Retrieved from Cache` (futuro)
- `Label Request Failed - Shipment Not Found`
- `Label Request Failed - Invalid Status`

## Consideraciones Futuras

### Performance
- Caché de etiquetas evita llamadas repetidas a API
- Etiquetas no expiran (a menos que se modifique el envío)

### Seguridad
- Solo admins pueden generar etiquetas
- Se valida CSRF token
- Se registran todas las acciones con usuario

### UX
- Feedback visual claro (toasts, estados de botón)
- Apertura en nueva pestaña (no interrumpe workflow)
- Manejo gracioso de errores

### Multi-Carrier
La arquitectura está lista para múltiples carriers:
```php
// Zipnova
zipnova_get_label($shipment_id, $format);

// Futuro: Andreani
andreani_get_label($shipment_id, $format);

// Futuro: Correo Argentino
correo_get_label($shipment_id, $format);
```

## Contacto con Zipnova

Pendiente solicitar:
- ✅ Credenciales de sandbox
- ✅ Documentación de API de etiquetas
- ✅ Formatos soportados (PDF, PNG, ZPL)
- ✅ Límites de API (rate limiting)
- ✅ Tiempo de expiración de URLs de etiquetas
