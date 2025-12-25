# Guía de Uso: Sistema de Envíos con Zipnova

## Índice
1. [Introducción](#introducción)
2. [Configuración Inicial](#configuración-inicial)
3. [Flujo de Envíos](#flujo-de-envíos)
4. [Gestión desde el Admin](#gestión-desde-el-admin)
5. [Tracking para Clientes](#tracking-para-clientes)
6. [Solución de Problemas](#solución-de-problemas)

---

## Introducción

El sistema de envíos con Zipnova permite:
- 📦 Cotizar envíos en tiempo real durante el checkout
- 🚚 Crear envíos automáticamente al confirmar una orden
- 📍 Rastrear envíos con actualizaciones en tiempo real
- 📧 Notificar automáticamente a clientes sobre cambios de estado

---

## Configuración Inicial

### 1. Obtener Credenciales de Zipnova

1. Ingresa al panel de Zipnova: https://www.zipnova.com
2. Ve a **Configuración → API**
3. Copia tus credenciales:
   - **Client ID**: `client_id_xxx`
   - **Client Secret**: `secret_xxx`

### 2. Configurar en el Admin

1. Ingresa al admin de tu tienda
2. Ve a: **Envíos → Configuración de Logística**
3. Completa los siguientes datos:

#### Configuración General
- ✅ **Habilitar integración con Zipnova**: Marca la casilla
- **Modo de Operación**:
  - 🧪 **Sandbox**: Para pruebas (recomendado inicialmente)
  - 🚀 **Producción**: Para operación real

#### Credenciales de API
- **Client ID**: Pega tu client ID
- **Client Secret**: Pega tu secret
- Haz clic en **🔍 Probar Conexión** para verificar que funciona

#### Dirección de Origen
Completa con los datos de tu negocio (desde donde se envían los productos):
- Nombre del remitente
- Dirección completa
- Ciudad y Provincia
- Código Postal
- Teléfono y Email

#### Dimensiones por Defecto
Define las dimensiones y peso promedio de tus paquetes:
- Peso (kg)
- Largo, Ancho, Alto (cm)

Estos valores se usarán para cotizar envíos cuando no tengas dimensiones específicas por producto.

#### Opciones
- **Crear envío automáticamente**: ✅ Recomendado - El envío se crea en Zipnova al confirmar la orden
- **Margen de costo**: Porcentaje adicional sobre el costo de envío (ej: 10%)
- **Cache de cotizaciones**: Tiempo en minutos para cachear cotizaciones (5 min recomendado)

#### Servicios Habilitados
Selecciona qué tipos de envío ofrecer:
- ✅ **Envío Estándar**: Entrega regular
- ✅ **Envío Express**: Entrega rápida
- ⬜ **Envío el Mismo Día**: Solo si está disponible en tu zona

#### Webhooks
La URL del webhook ya está configurada automáticamente:
```
https://tutienda.com/api/shipping?action=webhook
```

Copia esta URL y configúrala en el panel de Zipnova:
1. Panel de Zipnova → **Webhooks**
2. Agrega la URL
3. Selecciona eventos: `shipment.status_changed`

**Opcional**: Configura un "Webhook Secret" para mayor seguridad.

### 3. Guardar y Probar

1. Haz clic en **💾 Guardar Configuración**
2. Verifica que aparezca el mensaje de éxito
3. Haz clic en **🔍 Probar Conexión** para confirmar que todo funciona

---

## Flujo de Envíos

### Para el Cliente (Frontend)

#### 1. En el Checkout
1. El cliente completa sus datos de contacto
2. Selecciona "📦 Envío a domicilio"
3. Completa la dirección de envío
4. Hace clic en **📦 Calcular Costo de Envío**
5. Se muestran las opciones disponibles:
   - 🚚 Envío Estándar - $2,500 (3-5 días)
   - ⚡ Envío Express - $4,000 (1-2 días)
6. Selecciona el método preferido
7. El total se actualiza automáticamente con el costo de envío
8. Completa el pago normalmente

#### 2. Después de la Compra
El cliente recibe:
- ✉️ Email de confirmación con número de orden
- 📦 Email cuando el envío es creado con tracking ID
- 🚚 Emails automáticos con cada cambio de estado:
  - "Tu pedido está en camino"
  - "Tu pedido está en reparto"
  - "Tu pedido ha sido entregado"

#### 3. Rastrear el Envío
El cliente puede rastrear su envío de dos formas:

**Opción A**: Desde el email
- Clic en el link "Ver seguimiento completo"

**Opción B**: Directamente en la web
- Ir a: `https://tutienda.com/tracking?id=TRACKING_ID`

---

## Gestión desde el Admin

### Ver Todos los Envíos

1. Ve a: **Envíos → Logística Zipnova**
2. Verás la lista de todos los envíos con:
   - Número de orden
   - Cliente
   - Destino
   - Tracking ID
   - Estado actual
   - Fecha

### Filtrar Envíos

Usa los filtros disponibles:
- **Por estado**: Pendientes, En tránsito, Entregados, etc.
- **Buscar**: Por número de orden, cliente o tracking ID

### Acciones Disponibles

#### Para envíos sin crear en Zipnova:
- **Crear Envío**: Crea el envío manualmente en Zipnova

#### Para envíos ya creados:
- **Sincronizar**: Actualiza el estado desde Zipnova
- **Cancelar**: Cancela el envío en Zipnova (si aún no fue entregado)
- **Ver Orden**: Abre los detalles completos de la orden

### Creación Manual de Envíos

Si tienes "auto crear envío" desactivado:

1. Ve a **Logística Zipnova**
2. Encuentra la orden
3. Haz clic en **Crear Envío**
4. Confirma la acción
5. El envío se crea en Zipnova y el cliente recibe el tracking

### Sincronizar Estado

Para obtener el estado más reciente desde Zipnova:

1. Encuentra el envío en la lista
2. Haz clic en **Sincronizar**
3. El estado se actualiza automáticamente
4. Si el estado cambió, el cliente recibe una notificación

---

## Tracking para Clientes

### Información Mostrada

La página de tracking muestra:
- 📦 Estado actual del envío
- 📋 Número de orden
- 🔢 Tracking ID
- 🚚 Método de envío
- 📅 Entrega estimada
- 📍 Dirección de entrega
- 📜 Historial completo de eventos

### Estados Posibles

| Estado | Significado | Icono |
|--------|------------|-------|
| Pendiente | Esperando recolección | ⏳ |
| En tránsito | Viajando hacia destino | 🚚 |
| En reparto | Salió para entrega | 📦 |
| Entregado | Entregado exitosamente | ✅ |
| Fallido | Problema en la entrega | ❌ |
| Devuelto | Retornando al origen | 🔄 |
| Cancelado | Envío cancelado | 🚫 |

---

## Solución de Problemas

### El botón "Calcular Costo" no funciona

**Problema**: El cliente hace clic pero no aparecen cotizaciones.

**Solución**:
1. Verifica que Zipnova esté habilitado en la configuración
2. Verifica que las credenciales sean correctas
3. Prueba la conexión desde **Configuración de Logística**
4. Revisa los logs en: `/logs/zipnova/YYYY-MM-DD.log`

### Las cotizaciones son muy altas

**Problema**: Los costos de envío parecen incorrectos.

**Solución**:
1. Verifica las dimensiones por defecto (peso, tamaño)
2. Ajusta el "Margen de costo" si está muy alto
3. Contacta a Zipnova para verificar tarifas

### El envío no se crea automáticamente

**Problema**: La orden se confirma pero no aparece en Zipnova.

**Solución**:
1. Verifica que "Crear envío automáticamente" esté habilitado
2. Verifica que la orden tenga `delivery_method = shipping`
3. Verifica que tenga todos los datos de dirección completos
4. Crea el envío manualmente desde **Logística Zipnova**

### Los webhooks no funcionan

**Problema**: El estado no se actualiza automáticamente.

**Solución**:
1. Verifica que la URL del webhook esté configurada en Zipnova
2. Verifica que tu sitio sea accesible públicamente (no localhost)
3. Si usas webhook secret, verifica que coincida
4. Sincroniza manualmente el estado mientras tanto

### Error "Token expirado"

**Problema**: Aparece error de token al usar Zipnova.

**Solución**:
1. El sistema debería renovar el token automáticamente
2. Si persiste, borra y vuelve a ingresar las credenciales
3. Guarda la configuración nuevamente

### No aparecen opciones de envío en checkout

**Problema**: Solo aparece "Retiro en persona".

**Solución**:
1. Verifica que Zipnova esté habilitado
2. Verifica que al menos un servicio esté habilitado (Estándar/Express)
3. Verifica que no haya productos con `delivery_options = 'pickup_only'`

---

## Datos de Prueba (Sandbox)

Mientras estés en modo Sandbox, puedes usar estos datos para probar:

### Código Postal de Prueba
- **1043**: CABA (entrega rápida)
- **5000**: Córdoba (entrega estándar)
- **8000**: Bahía Blanca (entrega estándar)

### Tracking de Prueba
En sandbox, los estados cambiarán automáticamente cada cierto tiempo para simular el progreso del envío.

---

## Contacto y Soporte

### Soporte de Zipnova
- 🌐 Web: https://www.zipnova.com
- 📚 Documentación: https://docs.zipnova.com
- 💬 Ayuda: https://ayuda.zipnova.com

### Documentación Técnica
Para desarrolladores, consulta:
- `/docs/zipnova-shipping-integration-plan.md`: Plan completo de integración
- `/docs/API_REFERENCE.md`: Referencia de endpoints (próximamente)

---

**Última actualización**: 2025-12-22
**Versión del sistema**: 2.0
