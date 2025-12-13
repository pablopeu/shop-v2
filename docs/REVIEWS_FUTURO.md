# Sistema de Reviews - Documentación para Implementación Futura

**Fecha:** 2025-12-10
**Estado:** Parcialmente implementado - NO funcional para usuarios

---

## ✅ Lo que SÍ existe:

### 1. Visualización en Frontend
**Archivo:** `/app/pages/frontend/producto.php` (líneas 47-70, 371-394)

- Muestra reviews aprobados en la página de producto
- Calcula rating promedio (estrellas)
- Muestra hasta 5 reviews por producto
- Componente visual de review cards

**Componente:** `/app/includes/frontend/review-card.php`
- Función `render_review_card($review, $options)`
- Muestra: nombre de usuario, rating, comentario, fecha

### 2. Panel de Administración
**Archivo:** `/app/pages/admin/reviews-listado.php`

Funcionalidades:
- Lista todos los reviews
- Permite aprobar/rechazar/eliminar reviews
- Filtra por estado (pending, approved, rejected)
- Muestra estadísticas (total, pendientes, aprobados, rechazados)
- Distingue "compras verificadas"
- Acciones disponibles:
  - `?action=approve&id=XXX` - Aprobar review
  - `?action=reject&id=XXX` - Rechazar review
  - `?action=delete&id=XXX` - Eliminar review

### 3. Estructura de Datos
**Archivo:** `/app/data/reviews.json`

```json
{
    "reviews": [
        {
            "id": "string (UUID)",
            "product_id": "string",
            "user_name": "string",
            "rating": "int (1-5)",
            "comment": "string",
            "status": "pending|approved|rejected",
            "verified_purchase": "boolean",
            "created_at": "string (ISO 8601)",
            "approved_at": "string (ISO 8601)",
            "approved_by": "string (admin username)"
        }
    ]
}
```

**Estado actual:** Archivo vacío `{"reviews": []}`

---

## ❌ Lo que NO existe (pendiente de implementar):

### 1. Formulario Frontend para Crear Reviews
**Ubicación sugerida:** `/app/pages/frontend/producto.php` (después de la sección de reviews existentes)

Debe incluir:
- Selector de estrellas (1-5) con interactividad
- Campo de texto para nombre del usuario
- Campo de textarea para comentario
- Botón de envío
- Validación del lado del cliente
- Mensaje de confirmación/error

### 2. API Endpoint para Recibir Reviews
**Archivo a crear:** `/public_html/api/submit-review.php`

Debe:
- Validar datos recibidos (product_id, user_name, rating, comment)
- Generar ID único (UUID)
- Establecer status inicial como "pending"
- Opcionalmente: verificar si el usuario compró el producto (verificar en orders.json)
- Guardar en `/app/data/reviews.json`
- Implementar rate limiting para prevenir spam
- Devolver respuesta JSON con éxito/error

### 3. Sistema de Notificaciones
**Sugerencia:** Integrar con sistema de notificaciones existente

- Notificar al admin cuando hay reviews pendientes de aprobación
- Integración con Telegram bot (si aplica)
- Badge/contador en el sidebar del admin

### 4. Validación de Compra Verificada
**Lógica sugerida:**

```php
function is_verified_purchase($user_email, $product_id) {
    $orders = get_all_orders();
    foreach ($orders as $order) {
        if ($order['email'] === $user_email &&
            $order['status'] === 'cobrada') {
            foreach ($order['items'] as $item) {
                if ($item['product_id'] === $product_id) {
                    return true;
                }
            }
        }
    }
    return false;
}
```

---

## 🎯 Plan de Implementación Sugerido

### Fase 1: Backend (API)
1. Crear `/public_html/api/submit-review.php`
2. Implementar validación y rate limiting
3. Implementar guardado en reviews.json
4. Testing con Postman/curl

### Fase 2: Frontend (Formulario)
1. Crear componente de formulario de review
2. Agregar selector de estrellas interactivo
3. Implementar validación del lado del cliente
4. Conectar con API endpoint
5. Mostrar mensajes de éxito/error

### Fase 3: Moderación y Notificaciones
1. Implementar contador de reviews pendientes en sidebar
2. Notificaciones (email/Telegram) para reviews pendientes
3. Testing del flujo completo

### Fase 4: Verificación de Compra (Opcional)
1. Agregar campo de email en formulario de review
2. Implementar lógica de verificación de compra
3. Mostrar badge "Compra Verificada" en reviews verificados

---

## 🔧 Consideraciones Técnicas

### CSP (Content Security Policy)
- El formulario debe usar `data-action` para el envío (no `onsubmit`)
- Los scripts deben tener `nonce="<?= csp_nonce() ?>"`
- Seguir el patrón del sistema de event delegation

### Seguridad
- **Rate Limiting:** Máximo 1 review por producto por IP/sesión cada X horas
- **Sanitización:** Usar `htmlspecialchars()` en todos los outputs
- **Validación:**
  - Rating: 1-5 (integer)
  - Nombre: mínimo 2 caracteres, máximo 100
  - Comentario: mínimo 10 caracteres, máximo 1000
- **Anti-spam:** Considerar CAPTCHA si hay abuso

### UX/UI
- Mostrar mensaje claro: "Tu review será publicado después de ser aprobado por nuestro equipo"
- Deshabilitar botón de envío después de submit (prevenir duplicados)
- Mostrar feedback inmediato (toast/modal de confirmación)
- No permitir múltiples reviews del mismo usuario para el mismo producto

---

## 📝 Notas Adicionales

- El sistema actual de reviews está **deshabilitado** del sidebar y dashboard (2025-12-10)
- Los archivos del sistema se mantienen en el codebase para futura implementación
- No hay reviews existentes en la base de datos (`reviews.json` está vacío)
- La página admin `reviews-listado.php` está funcional pero inaccesible desde UI

---

## 🚀 Activación Futura

Cuando se decida implementar el sistema completo:

1. Restaurar opciones en sidebar (`/app/includes/admin/sidebar.php`)
2. Restaurar shortcut en dashboard (`/app/pages/admin/index.php`)
3. Implementar fases 1-4 del plan sugerido
4. Actualizar documentación del proyecto (CLAUDE.md)
5. Testing completo en desarrollo antes de producción

---

**Fin de documento**
