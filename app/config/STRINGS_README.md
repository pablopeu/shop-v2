# Sistema de Textos Configurables (i18n)

Sistema centralizado de textos para permitir internacionalización (i18n) en el futuro.

## 📝 Archivo: `strings.json`

Archivo JSON que contiene todos los textos de la aplicación organizados por idioma y categoría.

### Estructura:

```json
{
    "es": {
        "category": {
            "key": "Texto en español"
        }
    }
}
```

### Categorías Disponibles:

- **cart**: Textos del carrito de compras
- **favorites**: Textos de favoritos
- **product**: Textos de productos
- **checkout**: Textos de checkout
- **order**: Textos de pedidos
- **search**: Textos de búsqueda
- **user**: Textos de usuario/cuenta
- **common**: Textos comunes (botones, acciones)
- **errors**: Mensajes de error
- **notifications**: Notificaciones
- **footer**: Textos del footer

## 🔧 Uso del Helper `__()`

### Sintaxis Básica:

```php
__('category.key')
```

### Ejemplos:

```php
// Texto simple
<?php echo __('cart.title'); ?>
// Output: "Tu Carrito"

// Atajo para echo
<?php _e('cart.empty'); ?>
// Output: "Tu carrito está vacío"

// Con reemplazos
<?php echo __('errors.min_length', ['min' => '8']); ?>
// Output: "Mínimo 8 caracteres"

// En atributos HTML
<button title="<?php echo __('cart.add'); ?>">
    <?php _e('cart.add'); ?>
</button>
```

### Ejemplos de Uso en Código:

#### En PHP:
```php
// Mensajes
$message = __('notifications.saved');

// Títulos de página
$page_title = __('checkout.title');

// Placeholders
<input type="text" placeholder="<?php echo __('search.placeholder'); ?>">

// Botones
<button><?php _e('common.save'); ?></button>

// Validaciones
if (empty($email)) {
    $error = __('errors.required');
}
```

#### En JavaScript (vía PHP):
```javascript
// Pasar textos a JavaScript
<script nonce="<?= csp_nonce() ?>">
    const strings = {
        cartEmpty: '<?php echo __('cart.empty'); ?>',
        cartAdded: '<?php echo __('cart.item_added'); ?>',
        error: '<?php echo __('common.error'); ?>'
    };

    // Usar en funciones
    function showCartEmpty() {
        alert(strings.cartEmpty);
    }
</script>
```

## 🌍 Funciones Disponibles:

### `__($key, $replacements = [])`
Obtiene un texto traducido.
```php
__('cart.title') // "Tu Carrito"
__('errors.min_length', ['min' => '8']) // "Mínimo 8 caracteres"
```

### `_e($key, $replacements = [])`
Imprime directamente un texto traducido.
```php
_e('cart.empty') // Echo "Tu carrito está vacío"
```

### `get_available_languages()`
Retorna array de idiomas disponibles.
```php
$langs = get_available_languages(); // ['es']
```

### `set_language($lang)`
Establece el idioma actual.
```php
set_language('en'); // Cambiar a inglés (cuando esté disponible)
```

### `get_current_language()`
Obtiene el idioma actual.
```php
$lang = get_current_language(); // 'es'
```

## 🎯 Reemplazos en Textos:

Los textos pueden contener placeholders con `{variable}`:

```json
{
    "es": {
        "errors": {
            "min_length": "Mínimo {min} caracteres",
            "max_length": "Máximo {max} caracteres"
        }
    }
}
```

Uso:
```php
__('errors.min_length', ['min' => '8']) // "Mínimo 8 caracteres"
__('errors.max_length', ['max' => '100']) // "Máximo 100 caracteres"
```

## 📋 Guía de Migración:

### Antes (hardcoded):
```php
<h2>Tu Carrito</h2>
<p>Tu carrito está vacío</p>
<button>Agregar al Carrito</button>
```

### Después (con strings):
```php
<h2><?php _e('cart.title'); ?></h2>
<p><?php _e('cart.empty'); ?></p>
<button><?php _e('cart.add'); ?></button>
```

## 🔍 Debugging:

Si una clave no se encuentra, se retorna la clave misma y se registra en error_log:

```php
echo __('invalid.key'); // Output: "invalid.key"
// Error log: [strings.php] Key not found: invalid.key
```

## 🌐 Agregar Nuevos Idiomas:

Para agregar inglés (ejemplo futuro):

```json
{
    "es": {
        "cart": {
            "title": "Tu Carrito"
        }
    },
    "en": {
        "cart": {
            "title": "Your Cart"
        }
    }
}
```

Cambiar idioma:
```php
set_language('en');
echo __('cart.title'); // "Your Cart"
```

## ✅ Ventajas:

1. **Centralización**: Todos los textos en un solo lugar
2. **i18n Ready**: Preparado para multi-idioma
3. **Mantenibilidad**: Cambiar textos sin tocar código
4. **Consistencia**: Mismos textos en toda la app
5. **Reutilización**: Un texto, múltiples usos
6. **Búsqueda fácil**: Encontrar todos los textos rápidamente

## 📝 Mejores Prácticas:

1. **Usar claves descriptivas**: `cart.add` mejor que `btn1`
2. **Agrupar por contexto**: Todos los textos de cart juntos
3. **Evitar HTML en strings**: Solo texto plano
4. **Usar reemplazos**: Para textos dinámicos con variables
5. **Documentar nuevas claves**: Agregar comentarios en JSON

## 🔗 Ver También:

- `app/config/strings.json` - Archivo de textos
- `app/includes/strings.php` - Implementación del helper
- `docs/PLAN_MIGRACION_THEMES.md` - Plan completo

---

**Creado**: 2025-12-07
**Fase**: Fase 4 - Textos Configurables
**Total de textos**: ~120 strings en español
