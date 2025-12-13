# AGENTS.md - Guía para Agentes de Código

## Comandos de Build/Test/Lint

Este proyecto PHP no tiene sistema de build automatizado. Para testing:

```bash
# Servidor de desarrollo local
cd public_html && php -S localhost:8000

# Testing de webhooks
php public_html/webhook.php

# Scripts de diagnóstico
php public_html/scripts/diagnostico.php
php public_html/scripts/test-webhook.php
```

## Estilo de Código y Convenciones

### Lenguaje y Comunicación
- **TODO el código, comentarios y UI debe estar en ESPAÑOL**
- Nombres de variables, funciones y mensajes en español
- Nunca usar `alert()`, `confirm()` o `prompt()` - usar modal personalizado

### Estructura y Seguridad
- Todo código privado debe estar en `/app/` con verificación `APP_ENTRY_POINT`
- Solo 4 puntos de entrada permitidos: index.php, admin/index.php, admin/login.php, webhook.php
- Usar `read_json()` y `write_json()` para operaciones JSON thread-safe
- Nunca usar paths hardcoded - usar constantes `APP_PATH`, `PUBLIC_PATH`, `url()`

### Importación y Organización
- Los includes principales se cargan en `bootstrap.php`
- Componentes HTML se incluyen cuando se necesitan, no en bootstrap
- Validar siempre entrada de usuario y usar tokens CSRF

### Manejo de Errores
- Usar `error_log()` para debugging en producción
- Crear scripts de diagnóstico en `public_html/scripts/` para troubleshooting
- Los errores de usuario se muestran en español vía modal personalizado

### Nombres de Variables
- Spanish: `nombre_producto`, `precio_total`, `usuario_activo`
- Functions: `obtener_productos()`, `validar_usuario()`, `procesar_pago()`
- Constants: `APP_PATH`, `DATA_PATH`, `MAX_INTENTOS`