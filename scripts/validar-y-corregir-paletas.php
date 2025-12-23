<?php
/**
 * Validador y Corrector de Contraste para Paletas
 *
 * Este script lee paletas-populares.json y corrige cualquier paleta
 * que tenga contraste bajo entre primary y background (< 4.5:1)
 */

// Copiar funciones de cálculo de color
function calculate_luminance($hex) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = $r / 255;
    $g = $g / 255;
    $b = $b / 255;

    $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

    return $luminance;
}

function calculate_contrast_ratio($color1, $color2) {
    $lum1 = calculate_luminance($color1);
    $lum2 = calculate_luminance($color2);

    $lighter = max($lum1, $lum2);
    $darker = min($lum1, $lum2);

    $ratio = ($lighter + 0.05) / ($darker + 0.05);

    return $ratio;
}

/**
 * Oscurece un color hex en un porcentaje
 */
function darken_color($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, (int)($r * (1 - $percent / 100)));
    $g = max(0, (int)($g * (1 - $percent / 100)));
    $b = max(0, (int)($b * (1 - $percent / 100)));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Aclara un color hex en un porcentaje
 */
function lighten_color($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = min(255, (int)($r + (255 - $r) * ($percent / 100)));
    $g = min(255, (int)($g + (255 - $g) * ($percent / 100)));
    $b = min(255, (int)($b + (255 - $b) * ($percent / 100)));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Corrige el contraste entre dos colores
 * Intenta oscurecer primary primero, si no funciona aclara background
 */
function fix_contrast($primary, $background, $min_ratio = 4.5) {
    $contrast = calculate_contrast_ratio($primary, $background);

    if ($contrast >= $min_ratio) {
        return ['primary' => $primary, 'background' => $background, 'changed' => false];
    }

    // Intentar oscurecer primary
    $new_primary = $primary;
    $attempts = 0;
    $max_attempts = 20;

    while ($contrast < $min_ratio && $attempts < $max_attempts) {
        $new_primary = darken_color($new_primary, 10);
        $contrast = calculate_contrast_ratio($new_primary, $background);
        $attempts++;
    }

    // Si logramos buen contraste, devolver
    if ($contrast >= $min_ratio) {
        return ['primary' => $new_primary, 'background' => $background, 'changed' => true];
    }

    // Si no, intentar con background más claro
    $new_background = $background;
    $new_primary = $primary; // Resetear primary
    $contrast = calculate_contrast_ratio($new_primary, $new_background);
    $attempts = 0;

    while ($contrast < $min_ratio && $attempts < $max_attempts) {
        $new_background = lighten_color($new_background, 10);
        $contrast = calculate_contrast_ratio($new_primary, $new_background);
        $attempts++;
    }

    // Si aún no funciona, forzar blanco como background
    if ($contrast < $min_ratio) {
        $new_background = '#ffffff';
        $contrast = calculate_contrast_ratio($new_primary, $new_background);

        // Si primary es muy claro, oscurecerlo
        if ($contrast < $min_ratio) {
            while ($contrast < $min_ratio && $attempts < $max_attempts) {
                $new_primary = darken_color($new_primary, 10);
                $contrast = calculate_contrast_ratio($new_primary, $new_background);
                $attempts++;
            }
        }
    }

    return ['primary' => $new_primary, 'background' => $new_background, 'changed' => true];
}

echo "🔍 Validando y corrigiendo contraste de paletas...\n\n";

// Leer JSON de paletas
$json_file = __DIR__ . '/../public_html/assets/data/paletas-populares.json';
if (!file_exists($json_file)) {
    die("❌ Error: No se encontró paletas-populares.json\n");
}

$palettes = json_decode(file_get_contents($json_file), true);
if (!$palettes) {
    die("❌ Error: No se pudo leer el JSON\n");
}

echo "📊 Total de paletas: " . count($palettes) . "\n\n";

$corrected = 0;
$already_ok = 0;

foreach ($palettes as &$palette) {
    $primary = $palette['mapped']['primary'];
    $background = $palette['mapped']['background'];

    $contrast = calculate_contrast_ratio($primary, $background);

    if ($contrast < 4.5) {
        echo "⚠️  Paleta #{$palette['id']}: Contraste bajo ({$contrast}:1)\n";
        echo "    Primary: {$primary}, Background: {$background}\n";

        $fixed = fix_contrast($primary, $background);

        $palette['mapped']['primary'] = $fixed['primary'];
        $palette['mapped']['background'] = $fixed['background'];

        $new_contrast = calculate_contrast_ratio($fixed['primary'], $fixed['background']);

        echo "    ✅ Corregido: Primary: {$fixed['primary']}, Background: {$fixed['background']}\n";
        echo "    Nuevo contraste: {$new_contrast}:1\n\n";

        $corrected++;
    } else {
        $already_ok++;
    }
}

echo "\n";
echo "✅ Validación completada!\n";
echo "📊 Paletas OK: {$already_ok}\n";
echo "🔧 Paletas corregidas: {$corrected}\n\n";

if ($corrected > 0) {
    // Guardar JSON corregido
    $output = json_encode($palettes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($json_file, $output)) {
        echo "💾 Archivo actualizado: paletas-populares.json\n";
        echo "📏 Tamaño: " . number_format(strlen($output)) . " bytes\n\n";

        // Mostrar algunas paletas corregidas como ejemplo
        echo "📋 Ejemplo de paletas corregidas:\n";
        echo "===================================\n\n";

        $shown = 0;
        foreach ($palettes as $palette) {
            if ($shown >= 3) break;

            $contrast = calculate_contrast_ratio(
                $palette['mapped']['primary'],
                $palette['mapped']['background']
            );

            if ($contrast >= 4.5) {
                echo "Paleta #{$palette['id']}:\n";
                echo "  Primary: {$palette['mapped']['primary']}\n";
                echo "  Background: {$palette['mapped']['background']}\n";
                echo "  Contraste: {$contrast}:1 ✅\n\n";
                $shown++;
            }
        }

        echo "✅ Script completado exitosamente!\n";
    } else {
        echo "❌ Error al guardar el archivo\n";
        exit(1);
    }
} else {
    echo "✨ ¡Todas las paletas ya tienen buen contraste!\n";
}
