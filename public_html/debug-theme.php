<?php
/**
 * Debug Theme System
 * Script de diagnóstico para verificar el estado del sistema de themes
 */

// Bootstrap
define('APP_ENTRY_POINT', true);

// Cargar configuración de ruta al bootstrap (generada por instalador)
$bootstrap_config = __DIR__ . '/bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        die('Bootstrap file not found. Please check your installation.');
    }
} else {
    // Fallback para desarrollo (estructura relativa)
    require_once __DIR__ . '/../app/bootstrap.php';
}

header('Content-Type: text/html; charset=UTF-8');

// Leer configuración
$theme_config = read_json(APP_PATH . '/config/theme.json');
$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Obtener themes disponibles
require_once APP_PATH . '/includes/theme-loader.php';
$available_themes = get_available_themes();

// Verificar archivos del theme activo
$theme_dir = PUBLIC_PATH . "/assets/themes/{$active_theme}";
$theme_json_exists = file_exists($theme_dir . '/theme.json');
$variables_css_exists = file_exists($theme_dir . '/variables.css');
$theme_css_exists = file_exists($theme_dir . '/theme.css');

// Leer contenido de variables.css si existe
$variables_css_content = '';
if ($variables_css_exists) {
    $variables_css_content = file_get_contents($theme_dir . '/variables.css');
}

// Leer theme.json si existe
$theme_json_content = [];
if ($theme_json_exists) {
    $theme_json_content = json_decode(file_get_contents($theme_dir . '/theme.json'), true);
}

// Cargar theme de referencia (modern-compact) para comparación
$reference_theme = 'modern-compact';
$reference_dir = PUBLIC_PATH . "/assets/themes/{$reference_theme}";
$reference_json = [];
$reference_css = '';
if (file_exists($reference_dir . '/theme.json')) {
    $reference_json = json_decode(file_get_contents($reference_dir . '/theme.json'), true);
}
if (file_exists($reference_dir . '/variables.css')) {
    $reference_css = file_get_contents($reference_dir . '/variables.css');
}

/**
 * Comparar dos arrays recursivamente
 */
function array_diff_recursive($array1, $array2, $path = '') {
    $differences = [];

    foreach ($array1 as $key => $value) {
        $current_path = $path ? "$path.$key" : $key;

        if (!isset($array2[$key])) {
            $differences[$current_path] = [
                'status' => 'removed',
                'current' => $value,
                'reference' => null
            ];
        } elseif (is_array($value) && is_array($array2[$key])) {
            $sub_diff = array_diff_recursive($value, $array2[$key], $current_path);
            $differences = array_merge($differences, $sub_diff);
        } elseif ($value !== $array2[$key]) {
            $differences[$current_path] = [
                'status' => 'changed',
                'current' => $value,
                'reference' => $array2[$key]
            ];
        }
    }

    // Buscar keys que existen en array2 pero no en array1
    foreach ($array2 as $key => $value) {
        $current_path = $path ? "$path.$key" : $key;
        if (!isset($array1[$key])) {
            $differences[$current_path] = [
                'status' => 'added',
                'current' => null,
                'reference' => $value
            ];
        }
    }

    return $differences;
}

/**
 * Extraer variables CSS de un contenido CSS
 */
function extract_css_variables($css_content) {
    $variables = [];
    preg_match_all('/--([a-z0-9-]+):\s*([^;]+);/i', $css_content, $matches);

    if (!empty($matches[1])) {
        foreach ($matches[1] as $index => $var_name) {
            $variables['--' . $var_name] = trim($matches[2][$index]);
        }
    }

    return $variables;
}

// Comparar configuraciones
$config_differences = [];
if (!empty($theme_json_content) && !empty($reference_json)) {
    $config_differences = array_diff_recursive($theme_json_content, $reference_json);
}

// Comparar variables CSS
$current_css_vars = extract_css_variables($variables_css_content);
$reference_css_vars = extract_css_variables($reference_css);
$css_differences = [];

foreach ($current_css_vars as $var_name => $var_value) {
    if (!isset($reference_css_vars[$var_name])) {
        $css_differences[$var_name] = [
            'status' => 'added',
            'current' => $var_value,
            'reference' => null
        ];
    } elseif ($var_value !== $reference_css_vars[$var_name]) {
        $css_differences[$var_name] = [
            'status' => 'changed',
            'current' => $var_value,
            'reference' => $reference_css_vars[$var_name]
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Theme System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 { color: #005461; }
        h2 {
            color: #018790;
            margin-top: 30px;
            border-bottom: 2px solid #00B7B5;
            padding-bottom: 10px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.ok { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        pre {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #00B7B5;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.5;
        }
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .theme-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .theme-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
        }
        .theme-item.active {
            border-color: #00B7B5;
            background: #f0fffe;
        }
        .key-value {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            margin: 5px 0;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .key { font-weight: bold; color: #005461; }
        .value { color: #333; }
    </style>
</head>
<body>
    <h1>🔍 Debug Theme System</h1>
    <p>Diagnóstico del sistema de themes en producción</p>

    <div class="info-box">
        <h2>📋 Configuración Actual</h2>
        <div class="key-value">
            <div class="key">Theme Activo:</div>
            <div class="value"><strong><?php echo htmlspecialchars($active_theme); ?></strong></div>
        </div>
        <div class="key-value">
            <div class="key">Archivo theme.json:</div>
            <div class="value">
                <?php if ($theme_json_exists): ?>
                    <span class="status ok">✓ Existe</span>
                <?php else: ?>
                    <span class="status error">✗ No existe</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="key-value">
            <div class="key">Archivo variables.css:</div>
            <div class="value">
                <?php if ($variables_css_exists): ?>
                    <span class="status ok">✓ Existe</span>
                    <span>(<?php echo number_format(strlen($variables_css_content)); ?> caracteres)</span>
                <?php else: ?>
                    <span class="status error">✗ No existe</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="key-value">
            <div class="key">Archivo theme.css:</div>
            <div class="value">
                <?php if ($theme_css_exists): ?>
                    <span class="status ok">✓ Existe</span>
                <?php else: ?>
                    <span class="status error">✗ No existe</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="key-value">
            <div class="key">Directorio del theme:</div>
            <div class="value"><code><?php echo htmlspecialchars($theme_dir); ?></code></div>
        </div>
    </div>

    <?php if (!empty($theme_json_content)): ?>
    <div class="info-box">
        <h2>📄 Configuración del Theme (theme.json)</h2>
        <div class="key-value">
            <div class="key">Nombre:</div>
            <div class="value"><?php echo htmlspecialchars($theme_json_content['name'] ?? 'N/A'); ?></div>
        </div>
        <div class="key-value">
            <div class="key">Descripción:</div>
            <div class="value"><?php echo htmlspecialchars($theme_json_content['description'] ?? 'N/A'); ?></div>
        </div>
        <div class="key-value">
            <div class="key">Tarjetas redondeadas:</div>
            <div class="value">
                <?php
                $cards_rounded = $theme_json_content['components']['cards']['rounded'] ?? false;
                echo $cards_rounded ? '<span class="status ok">✓ Sí</span>' : '<span class="status error">✗ No</span>';
                ?>
            </div>
        </div>
        <div class="key-value">
            <div class="key">Botones redondeados:</div>
            <div class="value">
                <?php
                $buttons_rounded = $theme_json_content['components']['buttons']['rounded'] ?? false;
                echo $buttons_rounded ? '<span class="status ok">✓ Sí</span>' : '<span class="status error">✗ No</span>';
                ?>
            </div>
        </div>

        <?php if (isset($theme_json_content['borders']['radius'])): ?>
        <div class="key-value">
            <div class="key">Border Radius Config:</div>
            <div class="value">
                <pre><?php echo htmlspecialchars(json_encode($theme_json_content['borders']['radius'], JSON_PRETTY_PRINT)); ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($variables_css_exists): ?>
    <div class="info-box">
        <h2>🎨 Variables CSS (Primeras líneas de border-radius)</h2>
        <pre><?php
        // Extraer solo las líneas de border-radius
        $lines = explode("\n", $variables_css_content);
        $border_lines = [];
        $in_border_section = false;

        foreach ($lines as $line) {
            if (strpos($line, '/* Bordes') !== false || strpos($line, 'Bordes') !== false) {
                $in_border_section = true;
            }

            if ($in_border_section && strpos($line, '--border-radius') !== false) {
                $border_lines[] = $line;
            }

            if ($in_border_section && count($border_lines) > 0 && trim($line) === '') {
                break;
            }
        }

        echo htmlspecialchars(implode("\n", array_slice($border_lines, 0, 10)));
        ?></pre>
    </div>
    <?php endif; ?>

    <div class="info-box">
        <h2>🎭 Themes Disponibles (<?php echo count($available_themes); ?>)</h2>
        <div class="theme-list">
            <?php foreach ($available_themes as $slug => $info): ?>
                <div class="theme-item <?php echo $slug === $active_theme ? 'active' : ''; ?>">
                    <strong><?php echo htmlspecialchars($info['name'] ?? $slug); ?></strong>
                    <?php if ($slug === $active_theme): ?>
                        <span class="status ok">ACTIVO</span>
                    <?php endif; ?>
                    <br>
                    <small style="color: #666;"><?php echo htmlspecialchars($slug); ?></small>
                    <?php if (isset($info['archived']) && $info['archived']): ?>
                        <br><span class="status error">Archivado</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($config_differences)): ?>
    <div class="info-box">
        <h2>🔄 Diferencias de Configuración vs <?php echo htmlspecialchars($reference_theme); ?></h2>
        <p>Configuraciones que son <strong>diferentes</strong> entre tu theme y <?php echo htmlspecialchars($reference_theme); ?>:</p>

        <div style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 15px; border-radius: 6px; margin-top: 15px;">
            <?php
            $count = 0;
            foreach ($config_differences as $path => $diff):
                if ($diff['status'] === 'changed'):
                    $count++;
            ?>
                <div style="margin-bottom: 15px; padding: 10px; background: white; border-left: 4px solid #00B7B5; border-radius: 4px;">
                    <div style="font-weight: bold; color: #005461; margin-bottom: 5px;">
                        📝 <?php echo htmlspecialchars($path); ?>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                        <div>
                            <span style="color: #666;">Tu theme:</span>
                            <code style="background: #e3f2fd; padding: 2px 6px; border-radius: 3px; display: inline-block; margin-left: 5px;">
                                <?php echo htmlspecialchars(is_bool($diff['current']) ? ($diff['current'] ? 'true' : 'false') : json_encode($diff['current'])); ?>
                            </code>
                        </div>
                        <div>
                            <span style="color: #666;"><?php echo htmlspecialchars($reference_theme); ?>:</span>
                            <code style="background: #fff3e0; padding: 2px 6px; border-radius: 3px; display: inline-block; margin-left: 5px;">
                                <?php echo htmlspecialchars(is_bool($diff['reference']) ? ($diff['reference'] ? 'true' : 'false') : json_encode($diff['reference'])); ?>
                            </code>
                        </div>
                    </div>
                </div>
            <?php
                endif;
            endforeach;
            ?>
            <?php if ($count === 0): ?>
                <p style="color: #999; font-style: italic;">No hay diferencias en la configuración.</p>
            <?php else: ?>
                <p style="margin-top: 15px; font-weight: bold; color: #005461;">
                    Total: <?php echo $count; ?> configuraciones diferentes
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($css_differences)): ?>
    <div class="info-box">
        <h2>🎨 Diferencias de Variables CSS vs <?php echo htmlspecialchars($reference_theme); ?></h2>
        <p>Variables CSS que tienen <strong>valores diferentes</strong>:</p>

        <div style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 15px; border-radius: 6px; margin-top: 15px;">
            <?php
            $css_count = 0;
            // Agrupar por categoría (color, spacing, border, etc.)
            $grouped = [];
            foreach ($css_differences as $var_name => $diff) {
                if ($diff['status'] === 'changed') {
                    $category = 'other';
                    if (strpos($var_name, 'color') !== false) $category = 'colors';
                    elseif (strpos($var_name, 'spacing') !== false) $category = 'spacing';
                    elseif (strpos($var_name, 'border') !== false) $category = 'borders';
                    elseif (strpos($var_name, 'font') !== false) $category = 'typography';
                    elseif (strpos($var_name, 'shadow') !== false) $category = 'shadows';

                    if (!isset($grouped[$category])) {
                        $grouped[$category] = [];
                    }
                    $grouped[$category][$var_name] = $diff;
                    $css_count++;
                }
            }

            $category_labels = [
                'colors' => '🎨 Colores',
                'spacing' => '📏 Espaciado',
                'borders' => '🔲 Bordes',
                'typography' => '✍️ Tipografía',
                'shadows' => '✨ Sombras',
                'other' => '🔧 Otros'
            ];

            foreach ($grouped as $category => $vars):
            ?>
                <h3 style="color: #018790; margin: 20px 0 10px 0; font-size: 16px;">
                    <?php echo $category_labels[$category] ?? 'Otros'; ?> (<?php echo count($vars); ?>)
                </h3>
                <?php foreach ($vars as $var_name => $diff): ?>
                    <div style="margin-bottom: 10px; padding: 8px; background: white; border-left: 3px solid #00B7B5; border-radius: 3px; font-size: 13px;">
                        <div style="display: grid; grid-template-columns: 200px 1fr 1fr; gap: 10px; align-items: center;">
                            <code style="font-weight: bold; color: #005461;">
                                <?php echo htmlspecialchars($var_name); ?>
                            </code>
                            <div>
                                <span style="color: #666; font-size: 11px;">Tu theme:</span><br>
                                <code style="background: #e3f2fd; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo htmlspecialchars($diff['current']); ?>
                                </code>
                            </div>
                            <div>
                                <span style="color: #666; font-size: 11px;"><?php echo htmlspecialchars($reference_theme); ?>:</span><br>
                                <code style="background: #fff3e0; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo htmlspecialchars($diff['reference']); ?>
                                </code>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <p style="margin-top: 20px; font-weight: bold; color: #005461; padding-top: 15px; border-top: 2px solid #e0e0e0;">
                Total: <?php echo $css_count; ?> variables CSS diferentes
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="info-box">
        <h2>🔗 Enlaces de Prueba</h2>
        <p><a href="<?php echo url('/'); ?>" target="_blank">Ver Frontend →</a></p>
        <p><a href="<?php echo url('/admin/?page=config-themes'); ?>" target="_blank">Configurar Themes →</a></p>
        <p><a href="<?php echo url('/admin/?page=generador-themes'); ?>" target="_blank">Generador de Themes →</a></p>
    </div>

    <div class="info-box">
        <h2>📝 Cómo Usar Este Diagnóstico</h2>
        <ol style="line-height: 2;">
            <li>
                <strong>Verifica el theme activo</strong> en la sección "Configuración Actual"
            </li>
            <li>
                <strong>Revisa las diferencias de configuración</strong> vs <?php echo htmlspecialchars($reference_theme); ?>:
                <ul style="margin-top: 5px;">
                    <li>Si modificaste una opción y NO aparece en "Diferencias de Configuración", esa opción <span style="color: #c62828; font-weight: bold;">NO se está guardando</span></li>
                    <li>Si aparece en "Diferencias de Configuración" pero NO en "Diferencias de Variables CSS", esa opción <span style="color: #f57c00; font-weight: bold;">NO está generando CSS</span></li>
                </ul>
            </li>
            <li>
                <strong>Prueba hacer cambios incrementales</strong>:
                <ul style="margin-top: 5px;">
                    <li>Carga <?php echo htmlspecialchars($reference_theme); ?> en el generador</li>
                    <li>Cambia SOLO UNA configuración a la vez</li>
                    <li>Guarda y verifica si aparece aquí en las diferencias</li>
                    <li>Esto te ayudará a identificar qué opciones funcionan y cuáles no</li>
                </ul>
            </li>
            <li>
                Si los cambios se ven aquí pero no en el frontend, limpia el cache del navegador (Ctrl+Shift+R)
            </li>
        </ol>
    </div>

    <div class="info-box" style="background: #fff9e6; border-left: 4px solid #f57c00;">
        <h2>💡 Tip para Identificar Configuraciones Rotas</h2>
        <p style="margin-bottom: 10px;">Para identificar qué configuraciones NO están funcionando:</p>
        <ol style="line-height: 1.8;">
            <li>Ve al generador de themes</li>
            <li>Carga <strong><?php echo htmlspecialchars($reference_theme); ?></strong></li>
            <li>Cambia UNA sola opción (por ejemplo: color primario)</li>
            <li>Guarda con un nombre de prueba</li>
            <li>Activa ese theme de prueba</li>
            <li>Recarga esta página de diagnóstico</li>
            <li>Verifica si el cambio aparece en "Diferencias de Variables CSS"</li>
        </ol>
        <p style="margin-top: 15px; padding: 10px; background: white; border-radius: 4px;">
            <strong>Si NO aparece:</strong> Esa configuración es un placeholder y no está implementada.<br>
            <strong>Si aparece:</strong> Esa configuración funciona correctamente.
        </p>
    </div>

    <div style="margin-top: 40px; padding: 20px; background: #fffbf0; border-radius: 8px; border-left: 4px solid #ffaa00;">
        <strong>⚠️ Importante:</strong> Este archivo es solo para diagnóstico.
        Elimínalo después de resolver el problema o restringe su acceso.
    </div>
</body>
</html>
