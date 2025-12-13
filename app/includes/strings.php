<?php
/**
 * Strings / Translations Helper
 * Sistema de textos configurables para i18n futuro
 *
 * IMPORTANTE: Este archivo es cargado por bootstrap.php
 */

/**
 * Get translated string
 * Obtiene un texto traducido desde el archivo strings.json
 *
 * @param string $key Clave del texto en formato "category.key" (ej: "cart.title")
 * @param array $replacements Array asociativo de reemplazos (ej: ['name' => 'Juan'])
 * @return string Texto traducido o la clave si no se encuentra
 *
 * Ejemplos de uso:
 * __('cart.empty') → "Tu carrito está vacío"
 * __('cart.title') → "Tu Carrito"
 * __('errors.min_length', ['min' => '8']) → "Mínimo 8 caracteres"
 * __('product.stock_available') → "Stock disponible"
 */
function __($key, $replacements = []) {
    static $strings = null;

    // Cargar strings la primera vez
    if ($strings === null) {
        $strings_file = APP_PATH . '/config/strings.json';

        if (!file_exists($strings_file)) {
            error_log('[strings.php] strings.json not found at: ' . $strings_file);
            return $key; // Return key if file doesn't exist
        }

        $strings = read_json($strings_file);

        if (empty($strings)) {
            error_log('[strings.php] Failed to load strings.json or file is empty');
            return $key;
        }
    }

    // Obtener idioma de sesión o usar español por defecto
    $lang = $_SESSION['lang'] ?? 'es';

    // Si el idioma no existe, usar español
    if (!isset($strings[$lang])) {
        $lang = 'es';
    }

    // Separar la clave por puntos (ej: "cart.title" → ["cart", "title"])
    $keys = explode('.', $key);
    $value = $strings[$lang] ?? $strings['es'];

    // Navegar por el array usando las claves
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            // Si no se encuentra la clave, retornar la clave original
            error_log('[strings.php] Key not found: ' . $key);
            return $key;
        }
    }

    // Si el valor final no es string, retornar la clave
    if (!is_string($value)) {
        error_log('[strings.php] Invalid value type for key: ' . $key);
        return $key;
    }

    // Aplicar reemplazos si existen
    // Ejemplo: "Mínimo {min} caracteres" con ['min' => '8'] → "Mínimo 8 caracteres"
    foreach ($replacements as $search => $replace) {
        $value = str_replace('{' . $search . '}', $replace, $value);
    }

    return $value;
}

/**
 * Get translated string and echo it
 * Atajo para imprimir directamente un texto traducido
 *
 * @param string $key Clave del texto
 * @param array $replacements Array de reemplazos
 * @return void
 */
function _e($key, $replacements = []) {
    echo __($key, $replacements);
}

/**
 * Get available languages
 * Retorna array de idiomas disponibles en strings.json
 *
 * @return array Array de códigos de idioma disponibles
 */
function get_available_languages() {
    static $strings = null;

    if ($strings === null) {
        $strings = read_json(APP_PATH . '/config/strings.json');
    }

    return array_keys($strings);
}

/**
 * Set current language
 * Establece el idioma actual en la sesión
 *
 * @param string $lang Código de idioma (ej: 'es', 'en')
 * @return bool True si el idioma existe, false si no
 */
function set_language($lang) {
    $available = get_available_languages();

    if (in_array($lang, $available)) {
        $_SESSION['lang'] = $lang;
        return true;
    }

    return false;
}

/**
 * Get current language
 * Obtiene el idioma actual de la sesión
 *
 * @return string Código de idioma actual
 */
function get_current_language() {
    return $_SESSION['lang'] ?? 'es';
}
