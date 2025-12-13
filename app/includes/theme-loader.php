<?php
/**
 * Theme Loader
 * Carga dinámica de CSS de themes según configuración
 *
 * Orden de carga:
 * 1. Base: reset, layout, components, utilities
 * 2. Theme: variables, theme.css
 * 3. Components: carousel, mobile-menu, etc.
 *
 * IMPORTANTE: Este archivo es cargado por bootstrap.php
 */

/**
 * Render theme CSS links
 * Genera los tags <link> para cargar el theme activo
 *
 * @param string $active_theme Slug del theme activo (minimal, elegant, fresh, bold)
 * @return void Imprime los tags <link>
 */
function render_theme_css($active_theme = 'minimal') {
    // Validar theme dinámicamente usando los themes disponibles
    $available_themes = get_available_themes();
    $valid_themes = array_keys($available_themes);

    if (!in_array($active_theme, $valid_themes)) {
        $active_theme = 'minimal';
    }

    // Font Awesome 4.7 for icons (compatible with footer design)
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">' . PHP_EOL . '    ';

    // Base path para themes
    $base_path = '/assets/themes';

    // Array de archivos CSS a cargar en orden
    $css_files = [
        // 1. Base CSS (compartido por todos los themes)
        "{$base_path}/_base/reset.css",
        "{$base_path}/_base/layout.css",
        "{$base_path}/_base/components.css",
        "{$base_path}/_base/utilities.css",
        "{$base_path}/_base/pages.css",

        // 2. Theme-specific CSS
        "{$base_path}/{$active_theme}/variables.css",
        "{$base_path}/{$active_theme}/theme.css",
    ];

    // Generar tags <link> para cada archivo
    foreach ($css_files as $css_file) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(url($css_file)) . '">' . PHP_EOL . '    ';
    }

    // Cargar CSS específico de página (si existe)
    $page_css = get_current_page_css();
    if ($page_css) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(url($page_css)) . '">' . PHP_EOL . '    ';
    }
}

/**
 * Get current page CSS file
 * Detecta la página actual y retorna la ruta al archivo CSS específico si existe
 *
 * @return string|null Ruta al archivo CSS de la página o null si no existe
 */
function get_current_page_css() {
    // Intentar detectar la página actual desde diferentes fuentes
    $page_name = null;

    // 1. Desde el Router (para páginas frontend como /producto/:slug)
    global $router;
    if (isset($router) && method_exists($router, 'getMatchedRoute')) {
        $route = $router->getMatchedRoute();
        if ($route) {
            // Extraer nombre de archivo desde la ruta
            // Ejemplo: app/pages/frontend/producto.php → producto
            $page_name = basename($route, '.php');
        }
    }

    // 2. Desde el query parameter ?page= (para admin panel)
    if (!$page_name && isset($_GET['page'])) {
        $page_name = $_GET['page'];
    }

    // 3. Desde el script actual
    if (!$page_name) {
        $script_name = basename($_SERVER['SCRIPT_FILENAME'], '.php');
        // Mapear nombres de scripts a nombres de páginas
        $script_map = [
            'index' => 'home',  // index.php del frontend = home
        ];
        $page_name = $script_map[$script_name] ?? $script_name;
    }

    // Si no se pudo detectar, retornar null
    if (!$page_name) {
        return null;
    }

    // Verificar si existe el archivo CSS para esta página
    $css_path = "/assets/themes/_base/pages/{$page_name}.css";
    $css_file_path = PUBLIC_PATH . $css_path;

    if (file_exists($css_file_path)) {
        return $css_path;
    }

    return null;
}

/**
 * Get theme configuration
 * Lee el archivo theme.json del theme activo
 *
 * @param string $active_theme Slug del theme activo
 * @return array|null Configuración del theme o null si no existe
 */
function get_theme_config($active_theme = 'minimal') {
    $theme_json_path = PUBLIC_PATH . "/assets/themes/{$active_theme}/theme.json";

    if (!file_exists($theme_json_path)) {
        return null;
    }

    $json_content = file_get_contents($theme_json_path);
    return json_decode($json_content, true);
}

/**
 * Get all available themes
 * Lista todos los themes disponibles en /themes/
 *
 * @return array Array de themes con su configuración
 */
function get_available_themes() {
    $themes_dir = PUBLIC_PATH . '/assets/themes';
    $themes = [];

    // Leer directorios en /themes/
    if (!is_dir($themes_dir)) {
        return $themes;
    }

    $directories = array_diff(scandir($themes_dir), ['.', '..', '_base']);

    foreach ($directories as $dir) {
        $theme_path = $themes_dir . '/' . $dir;

        // Verificar que sea un directorio y tenga theme.json
        if (is_dir($theme_path) && file_exists($theme_path . '/theme.json')) {
            $config = json_decode(file_get_contents($theme_path . '/theme.json'), true);

            if ($config) {
                $themes[$dir] = $config;
            }
        }
    }

    return $themes;
}

/**
 * Cache theme configuration
 * Implementación simple de cache para configuración de theme
 * (Para futuras optimizaciones)
 */
function cache_theme_config($theme_slug, $ttl = 3600) {
    // TODO: Implementar cache con APCu o file-based cache
    // Por ahora retorna la config sin cache
    return get_theme_config($theme_slug);
}

/**
 * Combine CSS files into one
 * Combina múltiples archivos CSS en un solo string
 *
 * @param array $css_files Array de rutas a archivos CSS
 * @return string Contenido CSS combinado
 */
function combine_css_files($css_files) {
    $combined = '';

    foreach ($css_files as $file) {
        $file_path = PUBLIC_PATH . $file;

        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $combined .= "/* File: {$file} */\n";
            $combined .= $content . "\n\n";
        }
    }

    return $combined;
}

/**
 * Minify CSS content
 * Minifica CSS eliminando espacios innecesarios y comentarios
 *
 * @param string $css Contenido CSS
 * @return string CSS minificado
 */
function minify_css($css) {
    // Eliminar comentarios
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

    // Eliminar espacios en blanco
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);

    // Eliminar espacios alrededor de : ; { }
    $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);

    // Eliminar último punto y coma
    $css = preg_replace('/;}/', '}', $css);

    return trim($css);
}

/**
 * Get CSS version hash
 * Genera un hash basado en los timestamps de los archivos CSS
 *
 * @param array $css_files Array de rutas a archivos CSS
 * @return string Hash MD5
 */
function get_css_version($css_files) {
    $timestamps = '';

    foreach ($css_files as $file) {
        $file_path = PUBLIC_PATH . $file;
        if (file_exists($file_path)) {
            $timestamps .= filemtime($file_path);
        }
    }

    return md5($timestamps);
}

/**
 * Get cached CSS
 * Obtiene CSS combinado y cacheado, regenera si es necesario
 *
 * @param string $active_theme Slug del theme activo
 * @param bool $use_cache Si usar caché (default: true en producción)
 * @return string|null URL al archivo CSS cacheado o null si no usar caché
 */
function get_cached_css($active_theme = 'minimal', $use_cache = null) {
    // Auto-detectar si usar caché basado en entorno
    if ($use_cache === null) {
        // Usar caché en producción (detectar por path)
        $use_cache = strpos(APP_PATH, '/home2/uv0023/') !== false;
    }

    if (!$use_cache) {
        return null; // No usar caché, cargar archivos individuales
    }

    // Archivos CSS a combinar
    $base_path = '/assets/themes';
    $css_files = [
        "{$base_path}/_base/reset.css",
        "{$base_path}/_base/layout.css",
        "{$base_path}/_base/components.css",
        "{$base_path}/_base/utilities.css",
        "{$base_path}/_base/pages.css",
        "{$base_path}/{$active_theme}/variables.css",
        "{$base_path}/{$active_theme}/theme.css",
    ];

    // Generar versión basada en timestamps
    $version = get_css_version($css_files);
    $cache_file = "theme-{$active_theme}-{$version}.min.css";
    $cache_path = PUBLIC_PATH . "/assets/cache/{$cache_file}";

    // Si no existe el archivo cacheado, generarlo
    if (!file_exists($cache_path)) {
        // Limpiar cachés antiguos de este theme
        clear_css_cache($active_theme, $version);

        // Combinar archivos
        $combined = combine_css_files($css_files);

        // Minificar
        $minified = minify_css($combined);

        // Guardar en caché
        file_put_contents($cache_path, $minified);
        chmod($cache_path, 0644);
    }

    return "/assets/cache/{$cache_file}";
}

/**
 * Clear CSS cache
 * Limpia archivos de caché antiguos
 *
 * @param string|null $theme_slug Slug del theme (null = limpiar todos)
 * @param string|null $keep_version Versión a mantener (null = limpiar todas)
 * @return int Número de archivos eliminados
 */
function clear_css_cache($theme_slug = null, $keep_version = null) {
    $cache_dir = PUBLIC_PATH . '/assets/cache';
    $deleted = 0;

    if (!is_dir($cache_dir)) {
        return 0;
    }

    $files = glob($cache_dir . '/theme-*.min.css');

    foreach ($files as $file) {
        $filename = basename($file);

        // Si hay theme específico, solo procesar ese theme
        if ($theme_slug && strpos($filename, "theme-{$theme_slug}-") !== 0) {
            continue;
        }

        // Si hay versión a mantener, no eliminar ese archivo
        if ($keep_version && strpos($filename, "-{$keep_version}.min.css") !== false) {
            continue;
        }

        if (unlink($file)) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Render theme CSS with cache support
 * Versión mejorada de render_theme_css que soporta caché
 *
 * @param string $active_theme Slug del theme activo
 * @param bool $use_cache Si usar CSS cacheado (default: auto)
 * @return void Imprime los tags <link>
 */
function render_theme_css_cached($active_theme = 'minimal', $use_cache = null) {
    // Validar theme
    $available_themes = get_available_themes();
    $valid_themes = array_keys($available_themes);

    if (!in_array($active_theme, $valid_themes)) {
        $active_theme = 'minimal';
    }

    // Font Awesome
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">' . PHP_EOL . '    ';

    // Intentar usar CSS cacheado
    $cached_css = get_cached_css($active_theme, $use_cache);

    if ($cached_css) {
        // Usar archivo único cacheado
        echo '<link rel="stylesheet" href="' . htmlspecialchars(url($cached_css)) . '">' . PHP_EOL . '    ';
    } else {
        // Usar archivos individuales (modo desarrollo)
        $base_path = '/assets/themes';
        $css_files = [
            "{$base_path}/_base/reset.css",
            "{$base_path}/_base/layout.css",
            "{$base_path}/_base/components.css",
            "{$base_path}/_base/utilities.css",
            "{$base_path}/_base/pages.css",
            "{$base_path}/{$active_theme}/variables.css",
            "{$base_path}/{$active_theme}/theme.css",
        ];

        foreach ($css_files as $css_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(url($css_file)) . '">' . PHP_EOL . '    ';
        }
    }

    // Cargar CSS específico de página (siempre individual)
    $page_css = get_current_page_css();
    if ($page_css) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(url($page_css)) . '">' . PHP_EOL . '    ';
    }
}

// NOTE: validate_theme() moved to includes/functions.php to avoid duplication
