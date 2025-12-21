<?php
/**
 * Generador de Themes
 * Funciones para crear y personalizar themes del frontend
 *
 * Funciones principales:
 * - Cálculo de luminosidad y contraste WCAG
 * - Mapeo inteligente de paletas de colores
 * - Generación de variables CSS
 * - Generación de theme.css básico
 * - Validación y creación de themes
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// =============================================================================
// FUNCIONES DE CÁLCULO DE COLOR Y CONTRASTE
// =============================================================================

/**
 * Calcula la luminosidad relativa de un color hex
 * Basado en la fórmula WCAG 2.1
 *
 * @param string $hex Color en formato #RRGGBB
 * @return float Luminosidad relativa (0.0 = negro, 1.0 = blanco)
 */
function calculate_luminance($hex) {
    // Limpiar formato hex
    $hex = str_replace('#', '', $hex);

    // Convertir hex a RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Convertir a rango 0-1
    $r = $r / 255;
    $g = $g / 255;
    $b = $b / 255;

    // Aplicar transformación gamma (sRGB a linear RGB)
    $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

    // Calcular luminosidad relativa
    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

    return $luminance;
}

/**
 * Calcula el ratio de contraste entre dos colores
 * Basado en WCAG 2.1
 *
 * @param string $color1 Color en formato #RRGGBB
 * @param string $color2 Color en formato #RRGGBB
 * @return float Ratio de contraste (1.0 a 21.0)
 */
function calculate_contrast_ratio($color1, $color2) {
    $lum1 = calculate_luminance($color1);
    $lum2 = calculate_luminance($color2);

    // El más claro debe estar en el numerador
    $lighter = max($lum1, $lum2);
    $darker = min($lum1, $lum2);

    $ratio = ($lighter + 0.05) / ($darker + 0.05);

    return $ratio;
}

/**
 * Valida que el contraste cumpla con WCAG AA (4.5:1)
 *
 * @param string $foreground Color de primer plano #RRGGBB
 * @param string $background Color de fondo #RRGGBB
 * @return bool True si cumple WCAG AA
 */
function validate_wcag_contrast($foreground, $background) {
    $ratio = calculate_contrast_ratio($foreground, $background);
    return $ratio >= 4.5;
}

/**
 * Genera variantes de un color (dark, light, rgb)
 *
 * @param string $hex Color base en formato #RRGGBB
 * @return array ['dark' => '#...', 'light' => '#...', 'rgb' => 'R, G, B']
 */
function generate_color_variants($hex) {
    // Limpiar formato hex
    $hex = str_replace('#', '', $hex);

    // Convertir a RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Versión oscura (reducir 20%)
    $r_dark = max(0, (int)($r * 0.8));
    $g_dark = max(0, (int)($g * 0.8));
    $b_dark = max(0, (int)($b * 0.8));
    $dark = sprintf('#%02x%02x%02x', $r_dark, $g_dark, $b_dark);

    // Versión clara (incrementar hacia blanco 30%)
    $r_light = min(255, (int)($r + (255 - $r) * 0.3));
    $g_light = min(255, (int)($g + (255 - $g) * 0.3));
    $b_light = min(255, (int)($b + (255 - $b) * 0.3));
    $light = sprintf('#%02x%02x%02x', $r_light, $g_light, $b_light);

    // Versión RGB para rgba()
    $rgb = "$r, $g, $b";

    return [
        'dark' => $dark,
        'light' => $light,
        'rgb' => $rgb
    ];
}

// =============================================================================
// MAPEO INTELIGENTE DE COLORES
// =============================================================================

/**
 * Mapea 4 colores de una paleta a roles de theme
 * Analiza luminosidad y contraste para asignar primary, secondary, accent, background, text
 *
 * @param array $colors Array de 4 colores hex ['#...', '#...', '#...', '#...']
 * @return array Colores mapeados a roles ['primary' => '#...', 'secondary' => '#...', ...]
 */
function map_colors_intelligently($colors) {
    // Validar que sean exactamente 4 colores
    if (count($colors) !== 4) {
        return false;
    }

    // Calcular luminosidad de cada color
    $colors_with_lum = [];
    foreach ($colors as $color) {
        $colors_with_lum[] = [
            'hex' => $color,
            'luminance' => calculate_luminance($color)
        ];
    }

    // Ordenar por luminosidad (oscuro → claro)
    usort($colors_with_lum, function($a, $b) {
        return $a['luminance'] <=> $b['luminance'];
    });

    // Asignación inicial basada en luminosidad
    $darkest = $colors_with_lum[0]['hex'];     // Más oscuro
    $dark = $colors_with_lum[1]['hex'];        // Segundo más oscuro
    $light = $colors_with_lum[2]['hex'];       // Segundo más claro
    $lightest = $colors_with_lum[3]['hex'];    // Más claro

    // Determinar si el background es claro u oscuro
    $bg_luminance = $colors_with_lum[3]['luminance'];
    $is_light_bg = $bg_luminance > 0.5;

    // Asignar primary y validar contraste
    $primary = $darkest;
    $background = $lightest;

    // Validar contraste entre primary y background
    $contrast = calculate_contrast_ratio($primary, $background);

    // Si el contraste es insuficiente, ajustar primary
    if ($contrast < 4.5) {
        if ($is_light_bg) {
            // Background claro: forzar primary a negro
            $primary = '#000000';
        } else {
            // Background oscuro: forzar primary a blanco
            $primary = '#ffffff';
        }
    }

    // Determinar color de texto basado en luminosidad del background
    $text = $is_light_bg ? '#1a1a1a' : '#f5f5f5';

    // Asignar secondary y accent
    $secondary = $dark;
    $accent = $light;

    return [
        'primary' => $primary,
        'secondary' => $secondary,
        'accent' => $accent,
        'background' => $background,
        'text' => $text
    ];
}

// =============================================================================
// VALIDACIÓN
// =============================================================================

/**
 * Valida los datos de entrada para crear un theme
 *
 * @param string $slug Slug del theme
 * @param string $name Nombre del theme
 * @param array $colors Array de colores ['primary' => '#...', ...]
 * @param string $original_slug Slug original si estamos editando (opcional)
 * @return array ['valid' => bool, 'errors' => []]
 */
function validate_theme_input($slug, $name, $colors, $original_slug = '') {
    $errors = [];

    // Validar slug
    if (empty($slug)) {
        $errors[] = 'El slug es obligatorio';
    } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $errors[] = 'El slug solo puede contener letras minúsculas, números y guiones';
    } else {
        // Verificar que el slug no esté duplicado
        // Si estamos editando y el slug es el mismo que el original, no validar duplicados
        $is_editing_same_theme = !empty($original_slug) && $original_slug === $slug;

        if (!$is_editing_same_theme) {
            $theme_dir = PUBLIC_PATH . "/assets/themes/{$slug}";
            if (is_dir($theme_dir)) {
                $errors[] = "Ya existe un theme con el slug: {$slug}";
            }
        }
    }

    // Validar nombre
    if (empty($name)) {
        $errors[] = 'El nombre del theme es obligatorio';
    }

    // Validar colores
    $required_colors = ['primary', 'secondary', 'background'];
    foreach ($required_colors as $color_key) {
        if (empty($colors[$color_key])) {
            $errors[] = "El color {$color_key} es obligatorio";
        } elseif (!preg_match('/^#[a-f0-9]{6}$/i', $colors[$color_key])) {
            $errors[] = "El color {$color_key} debe tener formato #RRGGBB";
        }
    }

    // Warning si el contraste es bajo (no bloquea, solo advierte)
    if (!empty($colors['primary']) && !empty($colors['background'])) {
        $contrast = calculate_contrast_ratio($colors['primary'], $colors['background']);
        if ($contrast < 4.5) {
            $errors[] = "⚠️ Warning: El contraste entre primary y background es bajo ({$contrast}:1). Se recomienda >= 4.5:1 para accesibilidad";
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// =============================================================================
// GENERADORES DE CSS
// =============================================================================

/**
 * Genera el archivo variables.css con ~150 variables CSS
 *
 * @param array $config Configuración del theme (theme.json)
 * @return string Contenido del archivo variables.css
 */
function generate_variables_css($config) {
    $css = "/**\n";
    $css .= " * Variables CSS - " . $config['name'] . "\n";
    $css .= " * Generado automáticamente por el Generador de Themes\n";
    $css .= " */\n\n";
    $css .= ":root {\n";

    // === COLORES PRINCIPALES ===
    $css .= "    /* Colores Principales */\n";

    // Primary
    $primary_variants = generate_color_variants($config['colors']['primary']);
    $css .= "    --color-primary: {$config['colors']['primary']};\n";
    $css .= "    --color-primary-dark: {$primary_variants['dark']};\n";
    $css .= "    --color-primary-light: {$primary_variants['light']};\n";
    $css .= "    --color-primary-rgb: {$primary_variants['rgb']};\n\n";

    // Secondary
    $secondary_variants = generate_color_variants($config['colors']['secondary']);
    $css .= "    --color-secondary: {$config['colors']['secondary']};\n";
    $css .= "    --color-secondary-dark: {$secondary_variants['dark']};\n";
    $css .= "    --color-secondary-light: {$secondary_variants['light']};\n";
    $css .= "    --color-secondary-rgb: {$secondary_variants['rgb']};\n\n";

    // Accent
    if (!empty($config['colors']['accent'])) {
        $accent_variants = generate_color_variants($config['colors']['accent']);
        $css .= "    --color-accent: {$config['colors']['accent']};\n";
        $css .= "    --color-accent-dark: {$accent_variants['dark']};\n";
        $css .= "    --color-accent-light: {$accent_variants['light']};\n";
        $css .= "    --color-accent-rgb: {$accent_variants['rgb']};\n\n";
    }

    // === COLORES DE ESTADO ===
    $css .= "    /* Colores de Estado */\n";

    // Success
    $success = $config['colors']['success'] ?? '#2e7d32';
    $success_variants = generate_color_variants($success);
    $css .= "    --color-success: {$success};\n";
    $css .= "    --color-success-light: #d4edda;\n";
    $css .= "    --color-success-dark: {$success_variants['dark']};\n";
    $css .= "    --color-success-bg: #d1fae5;\n\n";

    // Warning
    $warning = $config['colors']['warning'] ?? '#f57c00';
    $warning_variants = generate_color_variants($warning);
    $css .= "    --color-warning: {$warning};\n";
    $css .= "    --color-warning-light: #fff3cd;\n";
    $css .= "    --color-warning-dark: {$warning_variants['dark']};\n";
    $css .= "    --color-warning-bg: #fff3e0;\n\n";

    // Error
    $error = $config['colors']['error'] ?? '#c62828';
    $error_variants = generate_color_variants($error);
    $css .= "    --color-error: {$error};\n";
    $css .= "    --color-error-light: #f8d7da;\n";
    $css .= "    --color-error-dark: {$error_variants['dark']};\n";
    $css .= "    --color-error-bg: #ffebee;\n\n";

    // Info
    $info = $config['colors']['info'] ?? '#1565c0';
    $css .= "    --color-info: {$info};\n";
    $css .= "    --color-info-light: #d1ecf1;\n";
    $css .= "    --color-info-bg: #e3f2fd;\n\n";

    // === COLORES NEUTROS ===
    $css .= "    /* Colores Neutros */\n";
    $text = $config['colors']['text'];
    $text_variants = generate_color_variants($text);
    $css .= "    --color-text: {$text};\n";
    $css .= "    --color-text-light: {$text_variants['light']};\n";
    $css .= "    --color-text-lighter: #757575;\n";
    $css .= "    --color-text-muted: #999999;\n";
    $css .= "    --color-text-dark: {$text_variants['dark']};\n\n";

    $bg = $config['colors']['background'];
    $bg_variants = generate_color_variants($bg);
    $css .= "    --color-bg: {$bg};\n";
    $css .= "    --color-bg-light: #fafafa;\n";
    $css .= "    --color-bg-lighter: #fcfcfc;\n";
    $css .= "    --color-bg-dark: {$bg_variants['dark']};\n";
    $css .= "    --color-bg-darker: #e8e8e8;\n\n";

    $css .= "    --color-border: #e0e0e0;\n";
    $css .= "    --color-border-light: #eeeeee;\n";
    $css .= "    --color-border-dark: #bdbdbd;\n\n";

    $css .= "    --color-shadow: rgba(0, 0, 0, 0.15);\n";
    $css .= "    --color-shadow-dark: rgba(0, 0, 0, 0.3);\n\n";

    // Colores base
    $css .= "    --color-white: #ffffff;\n";
    $css .= "    --color-black: #000000;\n\n";

    // === TIPOGRAFÍA ===
    $css .= "    /* Tipografía */\n";
    $font_family = $config['typography']['font_family'] ?? 'sans-serif';
    $css .= "    --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, {$font_family};\n";
    $css .= "    --font-family-heading: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, {$font_family};\n";
    $css .= "    --font-family-mono: 'SF Mono', Monaco, Consolas, monospace;\n\n";

    $css .= "    --font-size-xs: 12px;\n";
    $css .= "    --font-size-sm: 14px;\n";
    $css .= "    --font-size-base: {$config['typography']['base_size']};\n";
    $css .= "    --font-size-lg: 18px;\n";
    $css .= "    --font-size-xl: 22px;\n";
    $css .= "    --font-size-2xl: 28px;\n";
    $css .= "    --font-size-3xl: 36px;\n";
    $css .= "    --font-size-4xl: 52px;\n\n";

    $css .= "    --font-weight-normal: 400;\n";
    $css .= "    --font-weight-medium: 500;\n";
    $css .= "    --font-weight-semibold: 600;\n";
    $css .= "    --font-weight-bold: 700;\n\n";

    $line_height = $config['typography']['line_height'] ?? '1.5';
    $css .= "    --line-height-tight: 1.2;\n";
    $css .= "    --line-height-normal: {$line_height};\n";
    $css .= "    --line-height-relaxed: 1.8;\n\n";

    // === ESPACIADO ===
    $css .= "    /* Espaciado */\n";
    $base_unit = (int)str_replace('px', '', $config['spacing']['base_unit'] ?? '12px');
    $css .= "    --spacing-xs: " . (int)($base_unit * 0.5) . "px;\n";
    $css .= "    --spacing-sm: {$base_unit}px;\n";
    $css .= "    --spacing-md: " . (int)($base_unit * 1.67) . "px;\n";
    $css .= "    --spacing-lg: " . (int)($base_unit * 2.33) . "px;\n";
    $css .= "    --spacing-xl: " . (int)($base_unit * 3) . "px;\n";
    $css .= "    --spacing-2xl: " . (int)($base_unit * 4) . "px;\n";
    $css .= "    --spacing-3xl: " . (int)($base_unit * 5.33) . "px;\n\n";

    // === BORDES ===
    $css .= "    /* Bordes */\n";
    // Activar border-radius si CUALQUIERA de cards o buttons lo requiere
    $cards_rounded = $config['components']['cards']['rounded'] ?? false;
    $buttons_rounded = $config['components']['buttons']['rounded'] ?? false;
    $any_rounded = $cards_rounded || $buttons_rounded;

    if ($any_rounded) {
        $css .= "    --border-radius-none: 0;\n";
        $css .= "    --border-radius-sm: 2px;\n";
        $css .= "    --border-radius-md: 4px;\n";
        $css .= "    --border-radius-lg: 8px;\n";
        $css .= "    --border-radius-xl: 12px;\n";
        $css .= "    --border-radius-full: 9999px;\n\n";
    } else {
        $css .= "    --border-radius-none: 0;\n";
        $css .= "    --border-radius-sm: 0;\n";
        $css .= "    --border-radius-md: 0;\n";
        $css .= "    --border-radius-lg: 0;\n";
        $css .= "    --border-radius-xl: 0;\n";
        $css .= "    --border-radius-full: 0;\n\n";
    }

    $css .= "    --border-width: 1px;\n";
    $css .= "    --border-width-thick: 2px;\n\n";

    // === SOMBRAS ===
    $css .= "    /* Sombras */\n";
    $shadow_style = $config['components']['cards']['shadow'] ?? 'subtle';

    if ($shadow_style === 'none' || $shadow_style === false) {
        $css .= "    --shadow-sm: none;\n";
        $css .= "    --shadow-md: none;\n";
        $css .= "    --shadow-lg: none;\n";
        $css .= "    --shadow-xl: none;\n";
        $css .= "    --shadow-2xl: none;\n\n";
    } elseif ($shadow_style === 'subtle') {
        $css .= "    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.08);\n";
        $css .= "    --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.12);\n";
        $css .= "    --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.15);\n";
        $css .= "    --shadow-xl: 0 12px 24px rgba(0, 0, 0, 0.18);\n";
        $css .= "    --shadow-2xl: 0 20px 40px rgba(0, 0, 0, 0.25);\n\n";
    } elseif ($shadow_style === 'medium') {
        $css .= "    --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.12);\n";
        $css .= "    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);\n";
        $css .= "    --shadow-lg: 0 8px 20px rgba(0, 0, 0, 0.18);\n";
        $css .= "    --shadow-xl: 0 16px 32px rgba(0, 0, 0, 0.22);\n";
        $css .= "    --shadow-2xl: 0 24px 48px rgba(0, 0, 0, 0.3);\n\n";
    } else { // deep
        $css .= "    --shadow-sm: 0 4px 8px rgba(0, 0, 0, 0.15);\n";
        $css .= "    --shadow-md: 0 8px 16px rgba(0, 0, 0, 0.2);\n";
        $css .= "    --shadow-lg: 0 16px 32px rgba(0, 0, 0, 0.25);\n";
        $css .= "    --shadow-xl: 0 24px 48px rgba(0, 0, 0, 0.3);\n";
        $css .= "    --shadow-2xl: 0 32px 64px rgba(0, 0, 0, 0.4);\n\n";
    }

    // === TRANSICIONES ===
    $css .= "    /* Transiciones */\n";
    $css .= "    --transition-fast: 0.2s ease;\n";
    $css .= "    --transition-base: 0.4s ease;\n";
    $css .= "    --transition-slow: 0.6s ease;\n\n";

    // === LAYOUT ===
    $css .= "    /* Layout */\n";
    $css .= "    --container-width: {$config['layout']['container_width']};\n";
    $css .= "    --grid-gap: {$config['layout']['grid_gap']};\n";
    $css .= "    --header-height: 80px;\n";
    $css .= "    --footer-height: 220px;\n\n";

    // === BREAKPOINTS ===
    $css .= "    /* Breakpoints */\n";
    $css .= "    --breakpoint-mobile: 480px;\n";
    $css .= "    --breakpoint-tablet: 768px;\n";
    $css .= "    --breakpoint-desktop: 1024px;\n";
    $css .= "    --breakpoint-wide: 1200px;\n\n";

    // === Z-INDEX ===
    $css .= "    /* Z-Index */\n";
    $css .= "    --z-dropdown: 100;\n";
    $css .= "    --z-sticky: 200;\n";
    $css .= "    --z-fixed: 300;\n";
    $css .= "    --z-modal-backdrop: 400;\n";
    $css .= "    --z-modal: 500;\n";
    $css .= "    --z-popover: 600;\n";
    $css .= "    --z-tooltip: 700;\n";

    $css .= "}\n";

    return $css;
}

/**
 * Genera el archivo theme.css con estilos básicos
 *
 * @param array $config Configuración del theme (theme.json)
 * @return string Contenido del archivo theme.css
 */
function generate_theme_css_basic($config) {
    $css = "/**\n";
    $css .= " * Theme CSS - " . $config['name'] . "\n";
    $css .= " * Generado automáticamente por el Generador de Themes\n";
    $css .= " * Estilos personalizados del theme\n";
    $css .= " */\n\n";

    // === GLOBAL ===
    $css .= "/* =================================\n";
    $css .= "   GLOBAL\n";
    $css .= "   ================================= */\n\n";

    $css .= "body {\n";
    $css .= "    font-family: var(--font-family);\n";
    $css .= "    line-height: var(--line-height-normal);\n";
    $css .= "    color: var(--color-text);\n";
    $css .= "    background-color: var(--color-bg);\n";
    $css .= "}\n\n";

    // === HEADINGS ===
    $css .= "/* Headings */\n";
    $css .= "h1, h2, h3, h4, h5, h6 {\n";
    $css .= "    font-family: var(--font-family-heading);\n";
    $css .= "    font-weight: var(--font-weight-semibold);\n";
    $css .= "    line-height: var(--line-height-tight);\n";
    $css .= "    color: var(--color-text-dark);\n";
    $css .= "    margin-bottom: var(--spacing-md);\n";
    $css .= "}\n\n";

    // === LINKS ===
    $css .= "/* Links */\n";
    $css .= "a {\n";
    $css .= "    color: var(--color-primary);\n";
    $css .= "    text-decoration: none;\n";
    $css .= "    transition: var(--transition-base);\n";
    $css .= "}\n\n";

    $css .= "a:hover {\n";
    $css .= "    color: var(--color-secondary);\n";
    $css .= "}\n\n";

    // === PRODUCT CARDS ===
    $css .= "/* =================================\n";
    $css .= "   PRODUCT CARDS\n";
    $css .= "   ================================= */\n\n";

    $css .= ".product-card {\n";
    if ($config['components']['cards']['border']) {
        $css .= "    border: var(--border-width) solid var(--color-border);\n";
    }
    if ($config['components']['cards']['rounded']) {
        $css .= "    border-radius: var(--border-radius-lg);\n";
    }
    if ($config['components']['cards']['shadow'] && $config['components']['cards']['shadow'] !== 'none') {
        $css .= "    box-shadow: var(--shadow-md);\n";
    }
    $css .= "    background: var(--color-bg);\n";
    $css .= "    transition: var(--transition-base);\n";
    $css .= "}\n\n";

    // Card hover effect
    $hover_effect = $config['components']['cards']['hover_effect'] ?? 'glow';
    $css .= ".product-card:hover {\n";

    if ($hover_effect === 'lift') {
        $css .= "    transform: translateY(-8px);\n";
        if ($config['components']['cards']['shadow'] && $config['components']['cards']['shadow'] !== 'none') {
            $css .= "    box-shadow: var(--shadow-lg);\n";
        }
    } elseif ($hover_effect === 'glow') {
        if ($config['components']['cards']['shadow'] && $config['components']['cards']['shadow'] !== 'none') {
            $css .= "    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.2), var(--shadow-md);\n";
        } else {
            $css .= "    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.2);\n";
        }
    } elseif ($hover_effect === 'lift-3d') {
        $css .= "    transform: translateY(-8px) scale(1.02);\n";
        if ($config['components']['cards']['shadow'] && $config['components']['cards']['shadow'] !== 'none') {
            $css .= "    box-shadow: var(--shadow-xl);\n";
        }
    }

    $css .= "}\n\n";

    // Product Info (flexbox container)
    // Padding solo en top, left, right (NO en bottom para que margin-bottom de botones controle todo)
    $css .= ".product-info {\n";
    $css .= "    padding-top: var(--spacing-lg);\n";
    $css .= "    padding-left: var(--spacing-lg);\n";
    $css .= "    padding-right: var(--spacing-lg);\n";
    $css .= "    padding-bottom: 0;\n";
    $css .= "    display: flex;\n";
    $css .= "    flex-direction: column;\n";
    $css .= "    flex: 1;\n";
    $css .= "}\n\n";

    // Product Buttons Container
    // La separación vertical controla el espacio DESPUÉS de los botones (hacia el borde inferior del card)
    $vertical_spacing = $config['components']['cards']['buttons_vertical_spacing'] ?? 'normal';
    $margin_bottom_value = '12px'; // normal
    if ($vertical_spacing === 'compact') {
        $margin_bottom_value = '4px';
    } elseif ($vertical_spacing === 'spacious') {
        $margin_bottom_value = '20px';
    }

    $css .= ".product-buttons {\n";
    $css .= "    display: flex;\n";
    $css .= "    gap: var(--spacing-sm);\n";
    $css .= "    margin-top: 10px;\n";
    $css .= "    margin-bottom: {$margin_bottom_value} !important;\n";
    $css .= "}\n\n";

    // === BUTTONS ===
    $css .= "/* =================================\n";
    $css .= "   BUTTONS\n";
    $css .= "   ================================= */\n\n";

    $btn_style = $config['components']['buttons']['style'] ?? 'solid';
    $btn_rounded = $config['components']['buttons']['rounded'] ?? false;
    $btn_shadow = $config['components']['buttons']['shadow'] ?? false;
    $btn_height = $config['components']['buttons']['height'] ?? 'normal';
    $btn_width = $config['components']['buttons']['width'] ?? 'auto';
    $btn_icon = $config['components']['buttons']['icon'] ?? 'show';
    $btn_hover = $config['components']['buttons']['hover'] ?? 'lift';

    // Determinar padding según altura
    $padding_value = 'var(--spacing-sm) var(--spacing-lg)'; // normal
    if ($btn_height === 'compact') {
        $padding_value = 'var(--spacing-xs) var(--spacing-md)';
    } elseif ($btn_height === 'large') {
        $padding_value = 'var(--spacing-md) var(--spacing-xl)';
    }

    // Determinar ancho
    $width_value = 'auto'; // auto (por defecto)
    if ($btn_width === 'full') {
        $width_value = '100%';
    } elseif ($btn_width === 'fixed') {
        $width_value = '200px';
    }

    // Estilos base para todos los botones
    $css .= ".btn, .btn-primary, .btn-secondary, .btn-add-cart {\n";
    $css .= "    padding: {$padding_value};\n";
    if ($btn_width !== 'auto') {
        $css .= "    width: {$width_value};\n";
    }
    $css .= "    font-weight: var(--font-weight-medium);\n";
    $css .= "    transition: var(--transition-base);\n";
    $css .= "    cursor: pointer;\n";
    $css .= "    display: inline-block;\n";
    $css .= "    text-align: center;\n";

    // Border radius según configuración
    if ($btn_rounded) {
        $css .= "    border-radius: var(--border-radius-full);\n";
    } else {
        $css .= "    border-radius: var(--border-radius-sm);\n";
    }

    $css .= "}\n\n";

    // Ocultar iconos si está configurado
    if ($btn_icon === 'hide') {
        $css .= "/* Ocultar iconos en botones */\n";
        $css .= ".btn-buy::before, .btn-fav::before,\n";
        $css .= ".btn-add-cart .cart-icon,\n";
        $css .= "button[data-action=\"addToCart\"]::before {\n";
        $css .= "    display: none !important;\n";
        $css .= "}\n\n";
    }

    // Estilos específicos según el tipo (solid/outline)
    if ($btn_style === 'solid') {
        // Estilo sólido
        $css .= ".btn-primary, .btn-add-cart {\n";
        $css .= "    background: var(--color-primary);\n";
        $css .= "    color: var(--color-white);\n";
        $css .= "    border: none;\n";
        if ($btn_shadow) {
            $css .= "    box-shadow: var(--shadow-sm);\n";
        }
        $css .= "}\n\n";

        $css .= ".btn-primary:hover, .btn-add-cart:hover {\n";
        // Aplicar efecto hover según configuración
        if ($btn_hover === 'lift') {
            $css .= "    transform: translateY(-2px);\n";
            $css .= "    background: var(--color-primary-dark);\n";
            if ($btn_shadow) {
                $css .= "    box-shadow: var(--shadow-lg);\n";
            }
        } elseif ($btn_hover === 'glow') {
            $css .= "    background: var(--color-primary-dark);\n";
            $css .= "    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.3);\n";
        } elseif ($btn_hover === 'darken') {
            $css .= "    background: var(--color-primary-dark);\n";
            $css .= "    opacity: 0.9;\n";
        } elseif ($btn_hover === 'none') {
            $css .= "    background: var(--color-primary);\n";
        }
        $css .= "}\n\n";

        $css .= ".btn-secondary {\n";
        $css .= "    background: var(--color-secondary);\n";
        $css .= "    color: var(--color-white);\n";
        $css .= "    border: none;\n";
        if ($btn_shadow) {
            $css .= "    box-shadow: var(--shadow-sm);\n";
        }
        $css .= "}\n\n";

        $css .= ".btn-secondary:hover {\n";
        // Aplicar efecto hover según configuración
        if ($btn_hover === 'lift') {
            $css .= "    transform: translateY(-2px);\n";
            $css .= "    background: var(--color-secondary-dark);\n";
            if ($btn_shadow) {
                $css .= "    box-shadow: var(--shadow-lg);\n";
            }
        } elseif ($btn_hover === 'glow') {
            $css .= "    background: var(--color-secondary-dark);\n";
            $css .= "    box-shadow: 0 0 0 3px rgba(var(--color-secondary-rgb), 0.3);\n";
        } elseif ($btn_hover === 'darken') {
            $css .= "    background: var(--color-secondary-dark);\n";
            $css .= "    opacity: 0.9;\n";
        } elseif ($btn_hover === 'none') {
            $css .= "    background: var(--color-secondary);\n";
        }
        $css .= "}\n\n";
    } elseif ($btn_style === 'outline') {
        // Estilo outline
        $css .= ".btn-primary, .btn-add-cart {\n";
        $css .= "    background: transparent;\n";
        $css .= "    color: var(--color-primary);\n";
        $css .= "    border: var(--border-width-thick) solid var(--color-primary);\n";
        if ($btn_shadow) {
            $css .= "    box-shadow: var(--shadow-sm);\n";
        }
        $css .= "}\n\n";

        $css .= ".btn-primary:hover, .btn-add-cart:hover {\n";
        // Aplicar efecto hover según configuración (outline)
        if ($btn_hover === 'lift') {
            $css .= "    transform: translateY(-2px);\n";
            $css .= "    background: var(--color-primary);\n";
            $css .= "    color: var(--color-white);\n";
            if ($btn_shadow) {
                $css .= "    box-shadow: var(--shadow-lg);\n";
            }
        } elseif ($btn_hover === 'glow') {
            $css .= "    background: var(--color-primary);\n";
            $css .= "    color: var(--color-white);\n";
            $css .= "    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.3);\n";
        } elseif ($btn_hover === 'darken') {
            $css .= "    background: var(--color-primary);\n";
            $css .= "    color: var(--color-white);\n";
            $css .= "    opacity: 0.9;\n";
        } elseif ($btn_hover === 'none') {
            $css .= "    background: transparent;\n";
            $css .= "    color: var(--color-primary);\n";
        }
        $css .= "}\n\n";

        $css .= ".btn-secondary {\n";
        $css .= "    background: transparent;\n";
        $css .= "    color: var(--color-secondary);\n";
        $css .= "    border: var(--border-width-thick) solid var(--color-secondary);\n";
        if ($btn_shadow) {
            $css .= "    box-shadow: var(--shadow-sm);\n";
        }
        $css .= "}\n\n";

        $css .= ".btn-secondary:hover {\n";
        // Aplicar efecto hover según configuración (outline)
        if ($btn_hover === 'lift') {
            $css .= "    transform: translateY(-2px);\n";
            $css .= "    background: var(--color-secondary);\n";
            $css .= "    color: var(--color-white);\n";
            if ($btn_shadow) {
                $css .= "    box-shadow: var(--shadow-lg);\n";
            }
        } elseif ($btn_hover === 'glow') {
            $css .= "    background: var(--color-secondary);\n";
            $css .= "    color: var(--color-white);\n";
            $css .= "    box-shadow: 0 0 0 3px rgba(var(--color-secondary-rgb), 0.3);\n";
        } elseif ($btn_hover === 'darken') {
            $css .= "    background: var(--color-secondary);\n";
            $css .= "    color: var(--color-white);\n";
            $css .= "    opacity: 0.9;\n";
        } elseif ($btn_hover === 'none') {
            $css .= "    background: transparent;\n";
            $css .= "    color: var(--color-secondary);\n";
        }
        $css .= "}\n\n";
    }

    // === HEADER ===
    $css .= "/* =================================\n";
    $css .= "   HEADER\n";
    $css .= "   ================================= */\n\n";

    $css .= ".header {\n";
    $css .= "    background: var(--color-bg);\n";
    $css .= "    border-bottom: var(--border-width) solid var(--color-border);\n";
    if ($config['components']['cards']['shadow'] && $config['components']['cards']['shadow'] !== 'none') {
        $css .= "    box-shadow: var(--shadow-sm);\n";
    }
    $css .= "}\n\n";

    // === PRODUCT VIEW ===
    $css .= "/* =================================\n";
    $css .= "   PRODUCT VIEW\n";
    $css .= "   ================================= */\n\n";

    // Gallery Layout
    $gallery_layout = $config['components']['product_view']['gallery_layout'] ?? 'thumbnails-bottom';

    if ($gallery_layout === 'thumbnails-bottom') {
        $css .= ".product-gallery {\n";
        $css .= "    display: flex;\n";
        $css .= "    flex-direction: column;\n";
        $css .= "}\n\n";

        $css .= ".product-thumbnails {\n";
        $css .= "    display: flex;\n";
        $css .= "    flex-direction: row;\n";
        $css .= "    gap: var(--spacing-sm);\n";
        $css .= "    margin-top: var(--spacing-md);\n";
        $css .= "    justify-content: center;\n";
        $css .= "}\n\n";
    } elseif ($gallery_layout === 'thumbnails-left') {
        $css .= ".product-gallery {\n";
        $css .= "    display: flex;\n";
        $css .= "    flex-direction: row-reverse;\n";
        $css .= "}\n\n";

        $css .= ".product-thumbnails {\n";
        $css .= "    display: flex;\n";
        $css .= "    flex-direction: column;\n";
        $css .= "    gap: var(--spacing-sm);\n";
        $css .= "    margin-right: var(--spacing-md);\n";
        $css .= "}\n\n";
    } elseif ($gallery_layout === 'thumbnails-right') {
        $css .= ".product-gallery {\n";
        $css .= "    display: flex;\n";
        $css .= "    flex-direction: row;\n";
        $css .= "}\n\n";

        $css .= ".product-thumbnails {\n";
        $css .= "    display: flex;\n";
        $css .= "    flex-direction: column;\n";
        $css .= "    gap: var(--spacing-sm);\n";
        $css .= "    margin-left: var(--spacing-md);\n";
        $css .= "}\n\n";
    }

    // Main Image Size
    $image_size = $config['components']['product_view']['image_size'] ?? 'medium';
    $max_width = '500px'; // medium
    if ($image_size === 'small') {
        $max_width = '400px';
    } elseif ($image_size === 'large') {
        $max_width = '600px';
    }

    $css .= ".product-main-image {\n";
    $css .= "    max-width: {$max_width};\n";
    $css .= "    width: 100%;\n";
    $css .= "    height: auto;\n";
    $css .= "}\n\n";

    // Thumbnail Size
    $thumbnail_size = $config['components']['product_view']['thumbnail_size'] ?? 'medium';
    $thumb_size = '80px'; // medium
    if ($thumbnail_size === 'small') {
        $thumb_size = '60px';
    } elseif ($thumbnail_size === 'large') {
        $thumb_size = '100px';
    }

    $css .= ".product-thumbnail {\n";
    $css .= "    width: {$thumb_size};\n";
    $css .= "    height: {$thumb_size};\n";
    $css .= "    object-fit: cover;\n";
    $css .= "    cursor: pointer;\n";
    $css .= "    border: 2px solid transparent;\n";
    $css .= "    transition: var(--transition-base);\n";
    $css .= "}\n\n";

    $css .= ".product-thumbnail:hover,\n";
    $css .= ".product-thumbnail.active {\n";
    $css .= "    border-color: var(--color-primary);\n";
    $css .= "}\n\n";

    // Visibility of sections
    $show_breadcrumb = $config['components']['product_view']['show_breadcrumb'] ?? true;
    $show_share = $config['components']['product_view']['show_share'] ?? true;
    $show_sku = $config['components']['product_view']['show_sku'] ?? true;
    $show_stock = $config['components']['product_view']['show_stock'] ?? true;

    if (!$show_breadcrumb) {
        $css .= ".product-breadcrumb {\n";
        $css .= "    display: none !important;\n";
        $css .= "}\n\n";
    }

    if (!$show_share) {
        $css .= ".product-share,\n";
        $css .= ".share-buttons {\n";
        $css .= "    display: none !important;\n";
        $css .= "}\n\n";
    }

    if (!$show_sku) {
        $css .= ".product-sku {\n";
        $css .= "    display: none !important;\n";
        $css .= "}\n\n";
    }

    if (!$show_stock) {
        $css .= ".product-stock,\n";
        $css .= ".stock-indicator {\n";
        $css .= "    display: none !important;\n";
        $css .= "}\n\n";
    }

    // Ajustar contenedor de galería al tamaño de imagen
    $css .= ".product-gallery {\n";
    $css .= "    max-width: {$max_width};\n";
    $css .= "    margin: 0 auto;\n";
    $css .= "}\n\n";

    // === RESPONSIVE ===
    $css .= "/* =================================\n";
    $css .= "   RESPONSIVE\n";
    $css .= "   ================================= */\n\n";

    // Tablet and below
    $css .= "@media (max-width: 768px) {\n";
    $css .= "    .product-grid {\n";
    $css .= "        grid-template-columns: 1fr !important;\n";
    $css .= "        gap: var(--spacing-lg) !important;\n";
    $css .= "    }\n\n";

    $css .= "    .product-gallery {\n";
    $css .= "        max-width: 100% !important;\n";
    $css .= "        margin: 0 !important;\n";
    $css .= "    }\n\n";

    $css .= "    .product-main-image {\n";
    $css .= "        max-width: 100% !important;\n";
    $css .= "    }\n\n";

    // Force thumbnails to bottom on mobile/tablet
    if ($gallery_layout !== 'thumbnails-bottom') {
        $css .= "    .product-gallery {\n";
        $css .= "        flex-direction: column !important;\n";
        $css .= "    }\n\n";

        $css .= "    .product-thumbnails {\n";
        $css .= "        flex-direction: row !important;\n";
        $css .= "        margin: var(--spacing-md) 0 0 0 !important;\n";
        $css .= "        justify-content: center;\n";
        $css .= "    }\n\n";
    }

    $css .= "}\n\n";

    // Mobile only
    $css .= "@media (max-width: 480px) {\n";
    $css .= "    .product-thumbnail {\n";
    $css .= "        width: 60px !important;\n";
    $css .= "        height: 60px !important;\n";
    $css .= "    }\n";
    $css .= "}\n\n";

    return $css;
}

// =============================================================================
// FUNCIONES DE GESTIÓN DE THEMES
// =============================================================================

/**
 * Archiva un theme (lo marca como archivado en theme.json)
 *
 * @param string $slug Slug del theme a archivar
 * @return array ['success' => bool, 'message' => string]
 */
function archive_theme($slug) {
    $theme_file = PUBLIC_PATH . "/assets/themes/{$slug}/theme.json";

    if (!file_exists($theme_file)) {
        return [
            'success' => false,
            'message' => 'Theme no encontrado'
        ];
    }

    $config = read_json($theme_file);
    $config['archived'] = true;
    $config['updated_at'] = date('Y-m-d');

    if (write_json($theme_file, $config)) {
        return [
            'success' => true,
            'message' => 'Theme archivado exitosamente'
        ];
    }

    return [
        'success' => false,
        'message' => 'Error al archivar el theme'
    ];
}

/**
 * Desarchi un theme
 *
 * @param string $slug Slug del theme a desarchivar
 * @return array ['success' => bool, 'message' => string]
 */
function unarchive_theme($slug) {
    $theme_file = PUBLIC_PATH . "/assets/themes/{$slug}/theme.json";

    if (!file_exists($theme_file)) {
        return [
            'success' => false,
            'message' => 'Theme no encontrado'
        ];
    }

    $config = read_json($theme_file);
    $config['archived'] = false;
    $config['updated_at'] = date('Y-m-d');

    if (write_json($theme_file, $config)) {
        return [
            'success' => true,
            'message' => 'Theme desarchivado exitosamente'
        ];
    }

    return [
        'success' => false,
        'message' => 'Error al desarchivar el theme'
    ];
}

/**
 * Elimina un theme completamente
 *
 * @param string $slug Slug del theme a eliminar
 * @return array ['success' => bool, 'message' => string]
 */
function delete_theme($slug) {
    // No permitir borrar themes del sistema
    $protected_themes = ['minimal', 'classic', 'elegant', 'bold'];

    if (in_array($slug, $protected_themes)) {
        return [
            'success' => false,
            'message' => 'No se pueden eliminar los themes del sistema'
        ];
    }

    // Verificar que no sea el theme activo
    $theme_config = read_json(APP_PATH . '/config/theme.json');
    if ($theme_config['active_theme'] === $slug) {
        return [
            'success' => false,
            'message' => 'No se puede eliminar el theme activo. Activa otro theme primero.'
        ];
    }

    $theme_dir = PUBLIC_PATH . "/assets/themes/{$slug}";

    if (!is_dir($theme_dir)) {
        return [
            'success' => false,
            'message' => 'Theme no encontrado'
        ];
    }

    // Eliminar archivos recursivamente
    function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? delete_directory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    if (delete_directory($theme_dir)) {
        return [
            'success' => true,
            'message' => 'Theme eliminado exitosamente'
        ];
    }

    return [
        'success' => false,
        'message' => 'Error al eliminar el theme'
    ];
}

// =============================================================================
// FUNCIÓN PRINCIPAL DE GENERACIÓN
// =============================================================================

/**
 * Genera un nuevo theme completo
 * Crea directorio, theme.json, variables.css y theme.css
 *
 * @param array $data Datos del formulario
 * @return array ['success' => bool, 'message' => string, 'slug' => string]
 */
function generate_theme($data) {
    $slug = $data['slug'];
    $theme_dir = PUBLIC_PATH . "/assets/themes/{$slug}";

    // Crear directorio
    if (!is_dir($theme_dir)) {
        if (!mkdir($theme_dir, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Error al crear el directorio del theme'
            ];
        }
    }

    // Construir configuración del theme (theme.json)
    $config = [
        'name' => $data['name'],
        'slug' => $slug,
        'version' => '1.0.0',
        'description' => $data['description'] ?? 'Theme personalizado generado automáticamente',
        'author' => $data['author'] ?? 'Shop Team',
        'preview_image' => "/themes/{$slug}/preview.jpg",

        'features' => [
            'dark_mode' => false,
            'animations' => 'smooth',
            'border_style' => $data['card_rounded'] ? 'rounded' : 'sharp',
            'shadow_style' => $data['card_shadow'] ?? 'subtle',
            'color_scheme' => 'custom'
        ],

        'colors' => [
            'primary' => $data['color_primary'],
            'primary_dark' => generate_color_variants($data['color_primary'])['dark'],
            'primary_light' => generate_color_variants($data['color_primary'])['light'],
            'secondary' => $data['color_secondary'],
            'accent' => $data['color_accent'] ?? '#4facfe',
            'success' => $data['color_success'] ?? '#2e7d32',
            'warning' => $data['color_warning'] ?? '#f57c00',
            'error' => $data['color_error'] ?? '#c62828',
            'info' => $data['color_info'] ?? '#1565c0',
            'text' => $data['color_text'],
            'background' => $data['color_background']
        ],

        'typography' => [
            'font_family' => $data['font_family'] ?? 'sans-serif',
            'font_family_heading' => $data['font_family'] ?? 'sans-serif',
            'heading_weight' => '600',
            'base_size' => $data['font_size'] ?? '16px',
            'line_height' => $data['line_height'] ?? '1.5'
        ],

        'spacing' => [
            'base_unit' => '12px',
            'scale' => 'proportional'
        ],

        'components' => [
            'buttons' => [
                'style' => $data['button_style'] ?? 'solid',
                'rounded' => $data['button_rounded'] ?? false,
                'shadow' => $data['button_shadow'] ?? false,
                'height' => $data['button_height'] ?? 'normal',
                'width' => $data['button_width'] ?? 'auto',
                'icon' => $data['button_icon'] ?? 'show',
                'hover' => $data['button_hover'] ?? 'lift'
            ],
            'cards' => [
                'border' => $data['card_border'] ?? true,
                'shadow' => $data['card_shadow'] ?? 'subtle',
                'rounded' => $data['card_rounded'] ?? false,
                'hover_effect' => $data['card_hover'] ?? 'glow',
                'buttons' => $data['card_buttons'] ?? 'show',
                'buttons_position' => $data['card_buttons_position'] ?? 'center',
                'buttons_spacing' => $data['card_buttons_spacing'] ?? 'normal',
                'buttons_vertical_spacing' => $data['card_buttons_vertical_spacing'] ?? 'normal'
            ],
            'forms' => [
                'style' => 'modern',
                'border_style' => 'solid',
                'focus_ring' => true
            ],
            'product_view' => [
                'gallery_layout' => $data['product_gallery_layout'] ?? 'thumbnails-bottom',
                'image_size' => $data['product_image_size'] ?? 'medium',
                'thumbnail_size' => $data['product_thumbnail_size'] ?? 'medium',
                'show_breadcrumb' => $data['product_show_breadcrumb'] ?? true,
                'show_share' => $data['product_show_share'] ?? true,
                'show_sku' => $data['product_show_sku'] ?? true,
                'show_stock' => $data['product_show_stock'] ?? true
            ]
        ],

        'layout' => [
            'container_width' => '1200px',
            'grid_gap' => '28px',
            'sidebar_width' => '300px'
        ],

        'footer' => [
            'background_color' => '#292c2f',
            'text_color' => '#ffffff'
        ],

        'compatibility' => [
            'requires_php' => '7.4',
            'requires_css' => '3',
            'mobile_optimized' => true,
            'rtl_support' => false,
            'accessibility' => 'wcag-aa'
        ],

        'tags' => ['custom', 'generado', 'personalizado'],
        'created_at' => date('Y-m-d'),
        'updated_at' => date('Y-m-d')
    ];

    // Guardar theme.json
    if (!write_json($theme_dir . '/theme.json', $config)) {
        return [
            'success' => false,
            'message' => 'Error al guardar theme.json'
        ];
    }

    // Generar variables.css
    $variables_css = generate_variables_css($config);
    if (file_put_contents($theme_dir . '/variables.css', $variables_css) === false) {
        return [
            'success' => false,
            'message' => 'Error al guardar variables.css'
        ];
    }

    // Generar theme.css
    $theme_css = generate_theme_css_basic($config);
    if (file_put_contents($theme_dir . '/theme.css', $theme_css) === false) {
        return [
            'success' => false,
            'message' => 'Error al guardar theme.css'
        ];
    }

    // Establecer permisos
    chmod($theme_dir, 0755);
    chmod($theme_dir . '/theme.json', 0644);
    chmod($theme_dir . '/variables.css', 0644);
    chmod($theme_dir . '/theme.css', 0644);

    return [
        'success' => true,
        'message' => "Theme '{$data['name']}' generado exitosamente",
        'slug' => $slug
    ];
}
