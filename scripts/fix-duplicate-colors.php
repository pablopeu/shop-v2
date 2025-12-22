#!/usr/bin/env php
<?php
/**
 * Script para corregir paletas con colores duplicados
 * Genera colores armoniosos basados en los colores únicos de cada paleta
 */

// Rutas
$paletas_file = __DIR__ . '/../public_html/assets/data/paletas-populares.json';
$backup_file = __DIR__ . '/../public_html/assets/data/paletas-populares.backup-' . date('Y-m-d-His') . '.json';

// Verificar que existe el archivo
if (!file_exists($paletas_file)) {
    die("ERROR: No se encuentra el archivo paletas-populares.json\n");
}

// Cargar paletas
$json = file_get_contents($paletas_file);
$paletas = json_decode($json, true);

if ($paletas === null) {
    die("ERROR: No se pudo parsear el JSON\n");
}

echo "=== CORRECTOR DE COLORES DUPLICADOS ===\n\n";
echo "Total de paletas: " . count($paletas) . "\n\n";

// Estadísticas
$stats = [
    'total' => count($paletas),
    'con_duplicados' => 0,
    'corregidas' => 0,
    'errores' => 0
];

// Procesar cada paleta
foreach ($paletas as $index => &$paleta) {
    if (!isset($paleta['mapped'])) {
        continue;
    }

    $mapped = $paleta['mapped'];

    // Detectar duplicados
    $colors = [
        'primary' => $mapped['primary'] ?? null,
        'secondary' => $mapped['secondary'] ?? null,
        'accent' => $mapped['accent'] ?? null,
        'text' => $mapped['text'] ?? null,
        'background' => $mapped['background'] ?? null
    ];

    // Filtrar nulls
    $colors = array_filter($colors);

    // Contar ocurrencias
    $color_counts = array_count_values($colors);
    $duplicates = array_filter($color_counts, function($count) {
        return $count >= 2;
    });

    if (empty($duplicates)) {
        continue; // No hay duplicados
    }

    $stats['con_duplicados']++;

    echo "Paleta #{$paleta['id']} - Duplicados encontrados:\n";
    foreach ($duplicates as $color => $count) {
        echo "  $color aparece $count veces\n";
    }

    // Corregir duplicados
    try {
        $paleta['mapped'] = fixDuplicateColors($mapped, $paleta['colors'] ?? []);
        $stats['corregidas']++;

        echo "  ✓ Corregida\n";
        echo "  Nueva paleta: \n";
        echo "    Primary: {$paleta['mapped']['primary']}\n";
        echo "    Secondary: {$paleta['mapped']['secondary']}\n";
        echo "    Accent: {$paleta['mapped']['accent']}\n";
        echo "    Text: {$paleta['mapped']['text']}\n";
        echo "    Background: {$paleta['mapped']['background']}\n";

    } catch (Exception $e) {
        $stats['errores']++;
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Crear backup
echo "Creando backup en: " . basename($backup_file) . "\n";
file_put_contents($backup_file, $json);

// Guardar paletas corregidas
echo "Guardando paletas corregidas...\n";
$new_json = json_encode($paletas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($paletas_file, $new_json);

// Mostrar estadísticas
echo "\n=== RESUMEN ===\n";
echo "Total de paletas: {$stats['total']}\n";
echo "Con duplicados: {$stats['con_duplicados']}\n";
echo "Corregidas: {$stats['corregidas']}\n";
echo "Errores: {$stats['errores']}\n";
echo "\nBackup guardado en: scripts/" . basename($backup_file) . "\n";
echo "¡Proceso completado!\n";

// ===== FUNCIONES AUXILIARES =====

/**
 * Corrige colores duplicados en una paleta
 */
function fixDuplicateColors($mapped, $original_colors) {
    $result = $mapped;

    // Obtener colores únicos de la paleta original para tener más opciones
    $available_colors = array_unique(array_merge(
        $original_colors,
        array_values($mapped)
    ));

    // Detectar qué colores están duplicados
    $color_usage = [];
    foreach ($mapped as $role => $color) {
        if (!isset($color_usage[$color])) {
            $color_usage[$color] = [];
        }
        $color_usage[$color][] = $role;
    }

    // Para cada color duplicado, generar alternativas
    foreach ($color_usage as $color => $roles) {
        if (count($roles) < 2) {
            continue; // No está duplicado
        }

        // El primer rol mantiene el color original
        // Los demás roles necesitan colores nuevos
        for ($i = 1; $i < count($roles); $i++) {
            $role = $roles[$i];
            $new_color = generateHarmoniousColor($color, $role, $available_colors, $result);
            $result[$role] = $new_color;
        }
    }

    return $result;
}

/**
 * Genera un color armonioso basado en el color base y el rol
 */
function generateHarmoniousColor($base_color, $role, $available_colors, $current_mapped) {
    // Primero intentar usar un color de la paleta original que no esté en uso
    foreach ($available_colors as $color) {
        if (!in_array($color, $current_mapped) && $color !== $base_color) {
            return $color;
        }
    }

    // Si no hay colores disponibles, generar uno basado en teoría del color
    list($h, $s, $l) = hexToHsl($base_color);

    switch ($role) {
        case 'secondary':
            // Color análogo (30° en el círculo cromático)
            $h = ($h + 30) % 360;
            $s = max(20, min(100, $s - 10)); // Ligeramente menos saturado
            break;

        case 'accent':
            // Color complementario o triádico
            $h = ($h + 120) % 360;
            $s = max(40, min(100, $s + 10)); // Más saturado
            $l = max(30, min(70, $l));
            break;

        case 'text':
            // Color oscuro para texto
            $l = 10; // Muy oscuro
            $s = max(0, $s - 40); // Poco saturado
            break;

        case 'background':
            // Color claro para fondo
            $l = 95; // Muy claro
            $s = max(0, $s - 60); // Muy poco saturado
            break;

        case 'primary':
            // Variación del color base
            $h = ($h + 15) % 360;
            break;
    }

    return hslToHex($h, $s, $l);
}

/**
 * Convierte color hexadecimal a HSL
 */
function hexToHsl($hex) {
    // Limpiar #
    $hex = ltrim($hex, '#');

    // Convertir a RGB
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;

    if ($max === $min) {
        $h = $s = 0; // Gris acromático
    } else {
        $diff = $max - $min;
        $s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

        switch ($max) {
            case $r:
                $h = (($g - $b) / $diff + ($g < $b ? 6 : 0)) / 6;
                break;
            case $g:
                $h = (($b - $r) / $diff + 2) / 6;
                break;
            case $b:
                $h = (($r - $g) / $diff + 4) / 6;
                break;
        }
    }

    return [
        round($h * 360),
        round($s * 100),
        round($l * 100)
    ];
}

/**
 * Convierte HSL a color hexadecimal
 */
function hslToHex($h, $s, $l) {
    $h = $h / 360;
    $s = $s / 100;
    $l = $l / 100;

    if ($s == 0) {
        $r = $g = $b = $l; // Gris acromático
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = hueToRgb($p, $q, $h + 1/3);
        $g = hueToRgb($p, $q, $h);
        $b = hueToRgb($p, $q, $h - 1/3);
    }

    $r = round($r * 255);
    $g = round($g * 255);
    $b = round($b * 255);

    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

/**
 * Helper para convertir hue a RGB
 */
function hueToRgb($p, $q, $t) {
    if ($t < 0) $t += 1;
    if ($t > 1) $t -= 1;
    if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if ($t < 1/2) return $q;
    if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}
