# Directrices para Uso de Modales

## Regla Principal

**NUNCA usar `alert()`, `confirm()` o `prompt()` nativos del navegador.**

Todos los diálogos de confirmación, alertas y mensajes deben usar el componente modal personalizado ubicado en `/admin/includes/modal.php`.

## ¿Por qué usar modales personalizados?

1. **Experiencia de usuario consistente**: Los modales nativos del navegador no pueden estilizarse y se ven diferentes en cada navegador
2. **Mejor diseño**: Los modales personalizados mantienen el diseño y marca del sitio
3. **Más información**: Permiten mostrar títulos, mensajes detallados, iconos personalizados
4. **Accesibilidad**: Mejor control sobre accesibilidad y comportamiento
5. **Mobile-friendly**: Los modales nativos pueden verse mal en dispositivos móviles

## Cómo usar el componente modal

### 1. Incluir el componente

En cualquier página PHP del admin, incluir antes del cierre de `</body>`:

```php
<!-- Modal Component -->
<?php include __DIR__ . '/includes/modal.php'; ?>
```

### 2. Sintaxis básica

```javascript
showModal({
    title: 'Título del Modal',
    message: 'Mensaje principal',
    details: 'Información adicional (opcional)',
    icon: '⚠️',
    confirmText: 'Confirmar',
    cancelText: 'Cancelar',
    confirmType: 'danger', // 'danger', 'warning', 'primary'
    onConfirm: function() {
        // Acción a realizar al confirmar
    },
    onCancel: function() {
        // Acción a realizar al cancelar (opcional)
    }
});
```

### 3. Ejemplos de uso

#### Ejemplo 1: Confirmar eliminación

```javascript
function confirmDelete(itemId, itemName) {
    showModal({
        title: 'Eliminar Elemento',
        message: `¿Estás seguro de que deseas eliminar "${itemName}"?`,
        details: 'Esta acción no se puede deshacer.',
        icon: '🗑️',
        confirmText: 'Eliminar',
        confirmType: 'danger',
        onConfirm: function() {
            window.location.href = `?action=delete&id=${itemId}`;
        }
    });
}
```

#### Ejemplo 2: Confirmar acción con redirección

```javascript
function confirmAction(url, actionName) {
    showModal({
        title: `Confirmar ${actionName}`,
        message: `¿Deseas continuar con esta acción?`,
        icon: '❓',
        confirmText: 'Continuar',
        confirmType: 'primary',
        onConfirm: function() {
            window.location.href = url;
        }
    });
}
```

#### Ejemplo 3: Alerta informativa (sin cancelar)

```javascript
function showAlert(message) {
    showModal({
        title: 'Atención',
        message: message,
        icon: 'ℹ️',
        confirmText: 'Entendido',
        confirmType: 'primary',
        cancelText: 'Cerrar',
        onConfirm: function() {}
    });
}
```

#### Ejemplo 4: Confirmar envío de formulario

```javascript
function confirmFormSubmit(formId, actionName) {
    showModal({
        title: `Confirmar ${actionName}`,
        message: '¿Estás seguro de que deseas realizar esta acción?',
        details: 'Revisa que todos los datos sean correctos antes de continuar.',
        icon: '📝',
        confirmText: 'Confirmar',
        confirmType: 'primary',
        onConfirm: function() {
            document.getElementById(formId).submit();
        }
    });
}
```

#### Ejemplo 5: Acción masiva

```javascript
function confirmBulkAction() {
    const selected = document.querySelectorAll('.checkbox:checked');
    const count = selected.length;

    if (count === 0) {
        showModal({
            title: 'Sin Elementos Seleccionados',
            message: 'Debes seleccionar al menos un elemento.',
            icon: '⚠️',
            confirmText: 'Entendido',
            confirmType: 'primary',
            onConfirm: function() {}
        });
        return;
    }

    showModal({
        title: 'Confirmar Acción Masiva',
        message: `¿Aplicar esta acción a ${count} elemento${count > 1 ? 's' : ''}?`,
        icon: '📦',
        confirmText: 'Confirmar',
        confirmType: 'warning',
        onConfirm: function() {
            document.getElementById('bulkForm').submit();
        }
    });
}
```

## Parámetros del modal

| Parámetro | Tipo | Requerido | Descripción | Valor por defecto |
|-----------|------|-----------|-------------|-------------------|
| `title` | string | No | Título del modal | "Confirmar Acción" |
| `message` | string | No | Mensaje principal | "¿Estás seguro?" |
| `details` | string | No | Información adicional en caja gris | null (no se muestra) |
| `icon` | string | No | Emoji o icono para el título | "⚠️" |
| `confirmText` | string | No | Texto del botón confirmar | "Confirmar" |
| `cancelText` | string | No | Texto del botón cancelar | "Cancelar" |
| `confirmType` | string | No | Estilo del botón: 'primary', 'danger', 'warning' | 'primary' |
| `onConfirm` | function | Sí | Callback al confirmar | - |
| `onCancel` | function | No | Callback al cancelar | null |

## Tipos de confirmación

### Primary (Verde)
Para acciones normales, positivas o de confirmación general:
```javascript
confirmType: 'primary'
```
Ejemplos: Guardar, Activar, Restaurar, Continuar

### Warning (Amarillo)
Para acciones que requieren atención pero no son destructivas:
```javascript
confirmType: 'warning'
```
Ejemplos: Desactivar, Cambiar estado, Acciones masivas

### Danger (Rojo)
Para acciones destructivas o irreversibles:
```javascript
confirmType: 'danger'
```
Ejemplos: Eliminar, Archivar, Borrar permanentemente

## Helpers disponibles

### confirmAndRedirect()
```javascript
confirmAndRedirect(url, {
    title: 'Título',
    message: 'Mensaje',
    icon: '🔗',
    confirmType: 'primary'
});
```

### confirmAndSubmit()
```javascript
confirmAndSubmit('formId', {
    title: 'Título',
    message: 'Mensaje',
    icon: '📝',
    confirmType: 'primary'
});
```

## Iconos recomendados

- ⚠️ Advertencia general
- 🗑️ Eliminar
- ✅ Confirmar/Activar
- ❌ Desactivar/Rechazar
- 📦 Archivar
- ↩️ Restaurar/Deshacer
- ℹ️ Información
- 📝 Editar/Formulario
- 🔍 Buscar/Ver
- 💾 Guardar
- 🔄 Recargar/Actualizar
- 🚀 Publicar/Enviar
- 🔐 Seguridad

## Cerrar el modal

El modal se puede cerrar de varias formas:
1. Haciendo clic en el botón "Cancelar"
2. Haciendo clic en la X de cerrar
3. Haciendo clic fuera del modal (en el overlay)
4. Presionando la tecla ESC

## Accesibilidad

- El modal captura el foco automáticamente al abrirse
- El botón de confirmar recibe el foco inicial
- La tecla ESC cierra el modal
- El modal bloquea el scroll del body mientras está abierto
- El modal es responsive y se adapta a móviles

## Ejemplos en el código

Consultar estos archivos para ver implementaciones reales:

- `/admin/productos-listado.php` - Modales para activar, desactivar, archivar y acciones masivas
- `/admin/productos-archivados.php` - Modales para restaurar y eliminar permanentemente

## Migración de código existente

### Antes (❌ No hacer):
```javascript
onclick="return confirm('¿Estás seguro?')"
```

### Después (✅ Hacer):
```javascript
onclick="confirmAction('url', 'nombre')"

// En el script:
function confirmAction(url, name) {
    showModal({
        title: 'Confirmar Acción',
        message: `¿Estás seguro de que deseas ${name}?`,
        confirmType: 'primary',
        onConfirm: function() {
            window.location.href = url;
        }
    });
}
```

---

**Última actualización**: 2025-01-11
**Archivo ubicado en**: `/admin/includes/modal.php`
