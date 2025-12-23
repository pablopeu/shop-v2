<?php
/**
 * Procesador de Paletas de ColorHunt
 *
 * Este script lee paletas.csv y genera paletas-mapeadas.json
 * usando el mapeo inteligente del generador de themes
 */

// =============================================================================
// FUNCIONES DE CÁLCULO DE COLOR - Copiadas de theme-generator.php
// =============================================================================

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

function map_colors_intelligently($colors) {
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

    // Extraer colores ordenados
    $sorted = array_column($colors_with_lum, 'hex');

    // Detectar el color más oscuro para texto
    $darkest = $sorted[0];
    $darkest_lum = calculate_luminance($darkest);

    // Detectar el color más claro para background
    $lightest = $sorted[3];
    $lightest_lum = calculate_luminance($lightest);

    // Si el más claro es muy oscuro, usar blanco como background
    $background = ($lightest_lum > 0.5) ? $lightest : '#ffffff';

    // Si el más oscuro es muy claro, usar negro como texto
    $text = ($darkest_lum < 0.5) ? $darkest : '#000000';

    // Validar contraste text/background
    $contrast = calculate_contrast_ratio($text, $background);
    if ($contrast < 4.5) {
        // Si no hay suficiente contraste, forzar negro sobre blanco
        if ($lightest_lum > 0.5) {
            $text = '#000000';
        } else {
            $background = '#ffffff';
            $text = $darkest;
        }
    }

    // Asignar primary, secondary, accent de los colores medios
    // Priorizar colores saturados para primary
    $middle_colors = [$sorted[1], $sorted[2]];

    // Si tenemos un color muy vibrante, usarlo como primary
    $primary = $sorted[1]; // Color medio-oscuro
    $secondary = $sorted[2]; // Color medio-claro

    // El accent puede ser el más oscuro si no se usa como text
    $accent = ($darkest !== $text) ? $darkest : $sorted[2];

    return [
        'primary' => $primary,
        'secondary' => $secondary,
        'accent' => $accent,
        'text' => $text,
        'background' => $background
    ];
}

echo "🎨 Procesando paletas de ColorHunt...\n\n";

// Leer CSV
$csv_file = __DIR__ . '/paletas.csv';
if (!file_exists($csv_file)) {
    die("❌ Error: No se encontró paletas.csv\n");
}

$csv_content = file_get_contents($csv_file);
$lines = explode("\n", trim($csv_content));

echo "📊 Total de paletas encontradas: " . count($lines) . "\n\n";

$paletas_mapeadas = [];
$errores = 0;

foreach ($lines as $index => $line) {
    $line = trim($line);

    // Saltar líneas vacías
    if (empty($line)) {
        continue;
    }

    // Extraer colores
    $colors_raw = explode(',', $line);

    // Validar que haya 4 colores
    if (count($colors_raw) !== 4) {
        echo "⚠️  Línea " . ($index + 1) . " - Formato inválido (no tiene 4 colores)\n";
        $errores++;
        continue;
    }

    // Agregar # si no lo tienen
    $colors = array_map(function($color) {
        $color = trim($color);
        return (strpos($color, '#') === 0) ? $color : '#' . $color;
    }, $colors_raw);

    // Mapear colores usando la función inteligente
    $mapped = map_colors_intelligently($colors);

    if (!$mapped) {
        echo "⚠️  Línea " . ($index + 1) . " - Error en mapeo\n";
        $errores++;
        continue;
    }

    // Agregar a array de resultados
    $paletas_mapeadas[] = [
        'id' => $index + 1,
        'colors' => $colors,
        'mapped' => $mapped
    ];

    // Mostrar progreso cada 20 paletas
    if (($index + 1) % 20 === 0) {
        echo "✅ Procesadas " . ($index + 1) . " paletas...\n";
    }
}

echo "\n";
echo "✅ Procesamiento completado!\n";
echo "📊 Paletas procesadas exitosamente: " . count($paletas_mapeadas) . "\n";
echo "⚠️  Errores: " . $errores . "\n\n";

// Guardar JSON
$output_file = __DIR__ . '/paletas-mapeadas.json';
$json_output = json_encode($paletas_mapeadas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents($output_file, $json_output)) {
    echo "💾 Archivo generado: paletas-mapeadas.json\n";
    echo "📏 Tamaño: " . number_format(strlen($json_output)) . " bytes\n";

    // Mostrar ejemplo de las primeras 3 paletas
    echo "\n📋 Ejemplo de las primeras 3 paletas:\n";
    echo "=====================================\n\n";

    for ($i = 0; $i < min(3, count($paletas_mapeadas)); $i++) {
        $paleta = $paletas_mapeadas[$i];
        echo "Paleta #" . $paleta['id'] . ":\n";
        echo "  Colores originales: " . implode(', ', $paleta['colors']) . "\n";
        echo "  Mapeado:\n";
        echo "    Primary:    " . $paleta['mapped']['primary'] . "\n";
        echo "    Secondary:  " . $paleta['mapped']['secondary'] . "\n";
        echo "    Accent:     " . $paleta['mapped']['accent'] . "\n";
        echo "    Text:       " . $paleta['mapped']['text'] . "\n";
        echo "    Background: " . $paleta['mapped']['background'] . "\n";
        echo "\n";
    }

    echo "✅ Script completado exitosamente!\n";
} else {
    echo "❌ Error al guardar paletas-mapeadas.json\n";
    exit(1);
}
