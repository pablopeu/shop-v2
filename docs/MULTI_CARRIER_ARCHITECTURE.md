# Arquitectura Multi-Carrier para Sistema de Envíos

## Objetivo

Integrar Zipnova y futuros carriers en el sistema existente de gestión de envíos, usando tags de 4 letras para identificar carriers.

## Carriers Soportados

Los carriers se identifican con tags de 4 letras personalizables:

| Tipo | Tag (ejemplo) | Nombre (personalizable) |
|------|---------------|-------------------------|
| Zipnova | ZNVA | Zipnova Principal |
| Zipnova | ZNV2 | Zipnova Secundaria |
| Otro | OTRO | Otro carrier |

**Nota:** El tag y nombre son editables por el usuario en la configuración.

## Estructura de Datos

### 1. Objeto `shipping` en Orders

```json
{
  "method": "standard",
  "service_name": "Envío estándar",
  "cost": 2500,
  "address": {
    "street": "...",
    "city": "...",
    "province": "...",
    "postal_code": "...",
    "country": "AR"
  },
  "estimated_delivery": "3-5",

  // Multi-carrier fields
  "carrier": "ZNVA",                    // Tag del carrier (ZNVA, OTRO, null)
  "carrier_shipment_id": "123456",      // ID del envío en el carrier
  "carrier_status": "in_transit",       // Estado RAW del carrier API
  "tracking_id": "TRACK123",            // Tracking ID público

  // Estado base universal
  "status": "en_transito",              // Estado base del sistema (pendiente, en_transito, entregada, etc.)

  // Metadata
  "created_at": "2025-12-22 10:00:00",
  "updated_at": "2025-12-22 12:00:00",
  "history": []
}

// Nota: El tag del carrier se renderiza dinámicamente en la UI
// Display: "En tránsito (ZNVA)" se genera desde status + carrier
}
```

### 2. Backward Compatibility

Para órdenes existentes con `zipnova_shipment_id`:
- Se lee `zipnova_shipment_id` si `carrier_shipment_id` no existe
- Se asume `carrier = "ZNVA"` si tiene `zipnova_shipment_id`
- No se requiere migración de datos

## Mapeo de Estados (Dinámico)

### Estados Base del Sistema (Universales)

| Estado | Descripción | Display sin carrier | Display con carrier |
|--------|-------------|---------------------|---------------------|
| `pendiente` | Pendiente de procesamiento | Pendiente | Pendiente (ZNVA) |
| `en_transito` | En camino | En tránsito | En tránsito (ZNVA) |
| `en_reparto` | Salió para entrega | En reparto | En reparto (ZNVA) |
| `entregada` | Entregada al cliente | Entregada | Entregada (ZNVA) |
| `fallida` | Problema en la entrega | Fallida | Fallida (ZNVA) |
| `devuelta` | Devuelto al origen | Devuelta | Devuelta (ZNVA) |
| `cancelada` | Envío cancelado | Cancelada | Cancelada (ZNVA) |

**Estados actuales del sistema (legacy):**
- `cobrada` → se mapea a `pendiente`
- `enviada` → se mapea a `en_transito`
- `entregada` → se mantiene igual

### Mapeo de Carriers a Estados Base

Cada carrier mapea sus estados propios a los estados base del sistema:

**Zipnova (ZNVA):**
```
carrier_status    →  status (base)
──────────────────────────────────
pending           →  pendiente
in_transit        →  en_transito
out_for_delivery  →  en_reparto
delivered         →  entregada
failed            →  fallida
returned          →  devuelta
cancelled         →  cancelada
```

**Otros carriers (futuro):**
Cada carrier define su propio mapeo a los mismos estados base.

### Rendering Dinámico en UI

```php
// Ejemplo de rendering
function render_shipping_status($order) {
    $status = $order['shipping']['status'];  // Estado base
    $carrier = $order['shipping']['carrier'] ?? null;  // Tag del carrier

    $status_label = get_status_label($status);  // "En tránsito"

    if ($carrier) {
        return $status_label . ' <span class="carrier-badge">' . $carrier . '</span>';
        // Output: "En tránsito (ZNVA)"
    }

    return $status_label;  // "En tránsito"
}
```

**Ventaja:**
- Con 100 carriers, sigues teniendo solo 7 estados base
- El tag se agrega dinámicamente según el carrier
- Escalable infinitamente

## Configuración Multi-Carrier

### Archivo: `/app/config/shipping.json`

```json
{
  "carriers": {
    "ZNVA": {
      "tag": "ZNVA",           // Tag de 4 letras (personalizable por usuario)
      "name": "Zipnova Principal",  // Nombre descriptivo (personalizable)
      "type": "zipnova",       // Tipo de carrier (determina qué integración usar)
      "enabled": false,
      "mode": "sandbox",
      "credentials": {
        "client_id": "",
        "client_secret": "",
        "access_token": "",
        "refresh_token": "",
        "token_expires_at": null
      },
      "api_urls": {
        "sandbox": "https://sandbox.zipnova.com/api/v1",
        "production": "https://api.zipnova.com/api/v1"
      },
      "origin": {
        "name": "",
        "address": "",
        "city": "",
        "province": "",
        "postal_code": "",
        "country": "AR",
        "phone": "",
        "email": ""
      },
      "default_package": {
        "weight": 1,
        "length": 20,
        "width": 15,
        "height": 10
      },
      "options": {
        "webhook_secret": "",
        "auto_create_shipment": true,
        "shipping_cost_margin": 0,
        "cache_quotes_minutes": 5,
        "timeout_seconds": 30,
        "max_retries": 3
      },
      "enabled_services": {
        "standard": true,
        "express": true,
        "same_day": false
      }
    }
  }
}
```

## Integración en Gestión de Envíos

### Página: `envios-pendientes.php`

**Cambios:**

1. **Nueva columna en tabla:** Carrier
   - Muestra tag + icono (ej: "🚚 ZNVA")
   - Vacío si es envío manual

2. **Nuevo filtro:** Por Carrier
   - Todos
   - ZNVA (Zipnova)
   - Manual (sin carrier)

3. **Columna Estado mejorada:**
   - Estado del sistema (enviada, entregada)
   - Estado del carrier cuando aplica (En tránsito ZNVA)

4. **Acciones específicas por carrier:**
   - Si carrier = ZNVA y no tiene carrier_shipment_id:
     - Botón "Crear Envío en Zipnova"
   - Si carrier = ZNVA y tiene carrier_shipment_id:
     - Botón "Sincronizar Estado"
     - Botón "Cancelar Envío"
     - Botón "Ver Tracking"

### Página: `config-shipping.php`

**Cambios:**

1. **UI con Tabs:**
   ```
   ┌─────────────────────────────────────────┐
   │ [ZNVA - Zipnova Principal] [+ Agregar]  │
   ├─────────────────────────────────────────┤
   │                                         │
   │  ┌─ Información del Carrier ─────────┐ │
   │  │ Tag (4 letras): [ZNVA]            │ │
   │  │ Nombre: [Zipnova Principal]       │ │
   │  │ Tipo: [Zipnova ▼]                 │ │
   │  │ Habilitado: [✓]                   │ │
   │  └───────────────────────────────────┘ │
   │                                         │
   │  Configuración de Zipnova               │
   │  (formulario actual)                    │
   │                                         │
   └─────────────────────────────────────────┘
   ```

2. **Campos editables:**
   - **Tag**: Input de 4 letras (validación: solo letras mayúsculas/números)
   - **Nombre**: Input de texto libre para describir el carrier
   - **Tipo**: Select con opciones (Zipnova, Manual, etc.)

3. **Futuro:**
   - Botón "+ Agregar Carrier" para agregar nuevos
   - Cada carrier en su propio tab
   - Validación de tags únicos

## Archivos a Modificar

### 1. `/app/includes/orders.php`

- Modificar `build_shipping_data()` para usar campos multi-carrier
- Mantener backward compatibility con `zipnova_shipment_id`
- Agregar `get_carrier_name()` helper
- Agregar `get_carrier_status_label()` helper

### 2. `/app/includes/zipnova.php`

- Agregar funciones para leer/escribir usando estructura multi-carrier
- Mantener funciones legacy para backward compatibility

### 3. `/app/pages/admin/envios-pendientes.php`

- Agregar columna Carrier
- Agregar filtro por carrier
- Mostrar acciones específicas del carrier
- Mejorar display de estados

### 4. `/app/pages/admin/config-shipping.php`

- Reestructurar UI con tabs
- Leer/escribir usando estructura multi-carrier

### 5. `/app/config/shipping.json`

- Reestructurar a formato multi-carrier
- Migración automática desde formato anterior

## Archivos a Eliminar

- `/app/pages/admin/shipping-list.php` - Funcionalidad integrada en envios-pendientes.php

## Menú del Admin

**Antes:**
```
📦 Envíos
  ├─ 📋 Gestión de envíos
  ├─ 🚚 Logística
  └─ 📦 Archivo
```

**Después:**
```
📦 Envíos
  ├─ 📋 Gestión de envíos  (integra todo)
  └─ 📦 Archivo
```

## Migración

1. **No requiere migración de datos** - backward compatible
2. **Configuración:** Se migra automáticamente al abrir config-shipping.php
3. **Órdenes antiguas:** Se interpretan automáticamente

## Ventajas

1. ✅ Sin duplicación de funcionalidad
2. ✅ Escalable para múltiples carriers
3. ✅ Backward compatible - no rompe órdenes existentes
4. ✅ UI centralizada y consistente
5. ✅ Fácil agregar nuevos carriers en el futuro

---

**Siguiente paso:** Revisar y aprobar arquitectura antes de implementar
