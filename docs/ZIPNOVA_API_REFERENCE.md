# Documentación API de Zipnova - Referencia

**Última actualización:** 2025-12-22
**Fuente oficial:** https://docs.zipnova.com/envios

---

## URLs Base

### Por Región
- 🇦🇷 **Argentina:** `https://api.zipnova.com.ar/v2`
- 🇨🇱 **Chile:** `https://api.zipnova.cl/v2`
- 🇲🇽 **México:** `https://api.zipnova.com.mx/v2`

### Migración de Dominio Legacy
Los dominios antiguos de Zippin deben actualizarse antes del **1 de Abril de 2026**:
- `api.zippin.com.ar` → `api.zipnova.com.ar`
- `api.zippin.cl` → `api.zipnova.cl`
- `api.zippin.com.mx` → `api.zipnova.com.mx`

---

## Autenticación

### Método 1: HTTP Basic Authentication (Recomendado para integraciones simples)
- **Usuario:** API Token
- **Contraseña:** API Secret
- **Generar credenciales:** Configuración > Integraciones > Gestionar credenciales y webhooks

```http
Authorization: Basic {base64(api_token:api_secret)}
```

### Método 2: OAuth 2.0 Bearer Token (Para marketplaces y SaaS)
- Requerido para plataformas multi-cuenta
- Access token por cuenta autorizada

```http
Authorization: Bearer {access_token}
```

---

## Headers Requeridos

Todas las requests deben incluir:

```http
Accept: application/json
Authorization: Basic {credentials} o Bearer {token}
Content-Type: application/json
```

---

## Versión de API

**Versión actual:** `v2`
**Nota:** Los endpoints OAuth no usan el prefijo de versión en sus URLs.

---

## Endpoints Principales

### 1. Cotizar Envíos
**Documentación:** https://docs.zipnova.com/envios/recursos-api/envios/cotizar-envios

**Endpoint:** `PENDIENTE DE VERIFICAR`
**Opciones posibles:**
- `/shipments/quotes` - ERROR: resource not found
- `/shipments/quote` (singular)
- `/quotes`
- `/shipment-quotes`

**Métodos probados:**
- POST: No soportado
- PUT: Resource not found

**Parámetros requeridos:**
- Account ID
- Valor declarado del envío
- Detalles de paquetes e items

**TODO:** Verificar endpoint correcto en panel de Zipnova o documentación oficial

### 2. Crear Envíos
**Documentación:** https://docs.zipnova.com/envios/recursos-api/crear-envios

**Endpoint:** `/shipments`
**Método:** POST

### 3. Seguimiento
**Portal:** https://app.zipnova.com.ar/track

---

## Recursos Adicionales

- **Portal de Documentación:** https://docs.zipnova.com/envios
- **Centro de Ayuda:** https://ayuda.zipnova.com
- **Guía de Integración API:** https://ayuda.zipnova.com/integraciones-personalizadas-api
- **Cómo cotizar y crear envíos:** https://ayuda.zipnova.com/migrated/cómo-cotizar-y-crear-un-envío

---

## Notas de Implementación

### Error Común: Método POST no soportado
```
Error: "The POST method is not supported for route v2/shipments/quotes"
Solución: Usar GET o PUT en lugar de POST para cotizaciones
```

### Formato de URL
```
Correcto: https://api.zipnova.com.ar/v2/shipments/quotes
Incorrecto: https://api.zipnova.com.ar/v2//shipments/quotes (doble slash)
```

---

## Fuentes

- [Documentación de Zipnova Envíos](https://docs.zipnova.com/envios)
- [Crear Envíos](https://docs.zipnova.com/envios/recursos-api/crear-envios)
- [Integración via API](https://ayuda.zipnova.com/integraciones-personalizadas-api)
- [URLs y Autenticación](https://docs.zipnova.com/envios/principios/urls-y-autenticacion)
