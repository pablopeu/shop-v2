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

    <div class="info-box">
        <h2>🔗 Enlaces de Prueba</h2>
        <p><a href="<?php echo url('/'); ?>" target="_blank">Ver Frontend →</a></p>
        <p><a href="<?php echo url('/admin/?page=config-themes'); ?>" target="_blank">Configurar Themes →</a></p>
        <p><a href="<?php echo url('/admin/?page=generador-themes'); ?>" target="_blank">Generador de Themes →</a></p>
    </div>

    <div class="info-box">
        <h2>📝 Instrucciones</h2>
        <p>1. Verifica que el theme activo sea el correcto</p>
        <p>2. Verifica que "Tarjetas redondeadas" y "Botones redondeados" tengan el valor esperado</p>
        <p>3. Verifica que las variables CSS de border-radius tengan los valores correctos</p>
        <p>4. Si los valores son incorrectos, regenera el theme desde el generador</p>
        <p>5. Si los valores son correctos pero no se ven en el frontend, limpia el cache del navegador (Ctrl+Shift+R)</p>
    </div>

    <div style="margin-top: 40px; padding: 20px; background: #fffbf0; border-radius: 8px; border-left: 4px solid #ffaa00;">
        <strong>⚠️ Importante:</strong> Este archivo es solo para diagnóstico.
        Elimínalo después de resolver el problema o restringe su acceso.
    </div>
</body>
</html>
