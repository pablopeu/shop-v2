<?php
/**
 * Debug Theme System - Testing Tool
 * Herramienta para testear y documentar configuraciones del generador de themes
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

// ============================================================================
// PROCESAR GUARDADO DE COMENTARIOS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_comments'])) {
    $comments_data = json_decode($_POST['comments_data'] ?? '[]', true);

    if (!empty($comments_data)) {
        // Log en /logs (raíz del proyecto, no en /app/logs)
        $project_root = dirname(APP_PATH);
        $log_file = $project_root . '/logs/theme-testing.log';
        $log_dir = dirname($log_file);

        // Crear directorio de logs si no existe
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        // Preparar entrada de log en formato CSV

        // Verificar si el archivo existe para agregar header
        $add_header = !file_exists($log_file);

        $log_entry = '';

        // Agregar header solo si el archivo es nuevo
        if ($add_header) {
            $log_entry .= "setting,original value,modified value,comment\n";
        }

        // Función helper para formatear valores CSV
        function csv_value($value) {
            // Convertir a string
            $str = is_string($value) ? $value : json_encode($value);

            // Escapar comillas dobles
            $str = str_replace('"', '""', $str);

            // Encerrar en comillas
            return '"' . $str . '"';
        }

        // Agregar cada entrada como línea CSV
        foreach ($comments_data as $item) {
            $setting = $item['setting'] ?? 'Unknown';
            $original = $item['reference'] ?? '';
            $modified = $item['current'] ?? '';
            $comment = $item['comment'] ?? '';

            // Formatear valores
            $setting_csv = csv_value($setting);
            $original_csv = csv_value($original);
            $modified_csv = csv_value($modified);

            // Comentario: escapar y encerrar si necesario
            $comment_escaped = str_replace('"', '""', $comment);
            if (strpos($comment, ',') !== false || strpos($comment, '"') !== false || strpos($comment, "\n") !== false) {
                $comment_escaped = '"' . $comment_escaped . '"';
            }

            $log_entry .= "{$setting_csv},{$original_csv},{$modified_csv},{$comment_escaped}\n";
        }

        // Guardar log
        file_put_contents($log_file, $log_entry, FILE_APPEND);

        $save_success = true;
    }
}

// ============================================================================
// CARGAR CONFIGURACIONES
// ============================================================================
require_once APP_PATH . '/includes/theme-loader.php';

$theme_config = read_json(APP_PATH . '/config/theme.json');
$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Theme activo
$theme_dir = PUBLIC_PATH . "/assets/themes/{$active_theme}";
$theme_json_content = [];
if (file_exists($theme_dir . '/theme.json')) {
    $theme_json_content = json_decode(file_get_contents($theme_dir . '/theme.json'), true);
}

// Theme de referencia (modern-compact)
$reference_theme = 'modern-compact';
$reference_dir = PUBLIC_PATH . "/assets/themes/{$reference_theme}";
$reference_json = [];
if (file_exists($reference_dir . '/theme.json')) {
    $reference_json = json_decode(file_get_contents($reference_dir . '/theme.json'), true);
}

/**
 * Comparar dos arrays recursivamente
 */
function array_diff_recursive($array1, $array2, $path = '', $ignore_keys = []) {
    $differences = [];

    foreach ($array1 as $key => $value) {
        $current_path = $path ? "$path.$key" : $key;

        // Ignorar keys especificados
        if (in_array($key, $ignore_keys) && $path === '') {
            continue;
        }

        if (!isset($array2[$key])) {
            $differences[$current_path] = [
                'status' => 'removed',
                'current' => $value,
                'reference' => null
            ];
        } elseif (is_array($value) && is_array($array2[$key])) {
            $sub_diff = array_diff_recursive($value, $array2[$key], $current_path, $ignore_keys);
            $differences = array_merge($differences, $sub_diff);
        } elseif ($value !== $array2[$key]) {
            $differences[$current_path] = [
                'status' => 'changed',
                'current' => $value,
                'reference' => $array2[$key]
            ];
        }
    }

    return $differences;
}

// Comparar configuraciones (ignorando name, slug, preview_image, updated_at)
$config_differences = [];
if (!empty($theme_json_content) && !empty($reference_json)) {
    $ignore_keys = ['name', 'slug', 'preview_image', 'updated_at'];
    $config_differences = array_diff_recursive($theme_json_content, $reference_json, '', $ignore_keys);
}

// Filtrar solo los "changed"
$config_differences = array_filter($config_differences, function($diff) {
    return $diff['status'] === 'changed';
});

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Testing Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #005461;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-box h2 {
            color: #018790;
            margin-bottom: 15px;
            border-bottom: 2px solid #00B7B5;
            padding-bottom: 10px;
        }
        .diff-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .diff-row {
            border-bottom: 1px solid #e0e0e0;
        }
        .diff-row:nth-child(even) {
            background: #f9f9f9;
        }
        .diff-row:nth-child(odd) {
            background: white;
        }
        .diff-row td {
            padding: 8px 12px;
            vertical-align: middle;
        }
        .col-setting {
            width: 25%;
            font-weight: 600;
            color: #005461;
        }
        .col-reference {
            width: 30%;
            color: #666;
        }
        .col-current {
            width: 30%;
            color: #333;
        }
        .col-checkbox {
            width: 50px;
            text-align: center;
        }
        .diff-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .value-content {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .label-prefix {
            font-size: 10px;
            color: #999;
            text-transform: uppercase;
            margin-right: 4px;
        }
        .btn {
            padding: 12px 24px;
            background: #00B7B5;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #009795;
            transform: translateY(-2px);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #018790;
        }
        .btn-secondary:hover {
            background: #016670;
        }
        .comment-section {
            display: none;
            margin-top: 30px;
        }
        .comment-section.active {
            display: block;
        }
        .comment-item {
            margin-bottom: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        .comment-label {
            font-weight: bold;
            color: #005461;
            margin-bottom: 8px;
        }
        .comment-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .comment-input:focus {
            outline: none;
            border-color: #00B7B5;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .stats {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #1565c0;
            font-weight: bold;
        }
        .scroll-container {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 10px;
        }
        /* Scrollbar styling */
        .scroll-container::-webkit-scrollbar {
            width: 8px;
        }
        .scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .scroll-container::-webkit-scrollbar-thumb {
            background: #00B7B5;
            border-radius: 4px;
        }
        .scroll-container::-webkit-scrollbar-thumb:hover {
            background: #009795;
        }
    </style>
</head>
<body>
    <h1>🔬 Theme Testing Tool</h1>
    <p class="subtitle">Herramienta de testing para el generador de themes</p>

    <?php if (isset($save_success)): ?>
        <div class="success-message">
            ✅ Comentarios guardados exitosamente en: <code>/logs/theme-testing.log</code>
        </div>
    <?php endif; ?>

    <div class="stats">
        📊 Theme Activo: <strong><?php echo htmlspecialchars($active_theme); ?></strong>
        | Comparando con: <strong><?php echo htmlspecialchars($reference_theme); ?></strong>
        | Diferencias encontradas: <strong><?php echo count($config_differences); ?></strong>
    </div>

    <div class="info-box">
        <h2>🔄 Diferencias de Configuración</h2>

        <?php if (empty($config_differences)): ?>
            <p style="color: #999; font-style: italic;">No hay diferencias con el theme de referencia.</p>
        <?php else: ?>
            <div class="scroll-container">
                <table class="diff-table">
                    <thead>
                        <tr style="background: #005461; color: white; font-weight: bold;">
                            <th style="padding: 10px; text-align: left;">Setting</th>
                            <th style="padding: 10px; text-align: left;"><?php echo htmlspecialchars($reference_theme); ?></th>
                            <th style="padding: 10px; text-align: left;"><?php echo htmlspecialchars($active_theme); ?></th>
                            <th style="padding: 10px; text-align: center;">✓</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($config_differences as $path => $diff): ?>
                            <tr class="diff-row">
                                <td class="col-setting"><?php echo htmlspecialchars($path); ?></td>
                                <td class="col-reference">
                                    <span class="value-content">
                                        <?php echo htmlspecialchars(is_bool($diff['reference']) ? ($diff['reference'] ? 'true' : 'false') : json_encode($diff['reference'])); ?>
                                    </span>
                                </td>
                                <td class="col-current">
                                    <span class="value-content">
                                        <?php echo htmlspecialchars(is_bool($diff['current']) ? ($diff['current'] ? 'true' : 'false') : json_encode($diff['current'])); ?>
                                    </span>
                                </td>
                                <td class="col-checkbox">
                                    <?php
                                    // Formatear valores para data attributes
                                    $current_val = is_bool($diff['current']) ? ($diff['current'] ? 'true' : 'false') : (is_string($diff['current']) ? $diff['current'] : json_encode($diff['current']));
                                    $reference_val = is_bool($diff['reference']) ? ($diff['reference'] ? 'true' : 'false') : (is_string($diff['reference']) ? $diff['reference'] : json_encode($diff['reference']));
                                    ?>
                                    <input type="checkbox"
                                           class="diff-checkbox"
                                           data-setting="<?php echo htmlspecialchars($path); ?>"
                                           data-current="<?php echo htmlspecialchars($current_val); ?>"
                                           data-reference="<?php echo htmlspecialchars($reference_val); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                <button type="button" id="btn-generate-comments" class="btn">
                    💬 Generar Comentarios (<span id="selected-count">0</span> seleccionados)
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sección de Comentarios -->
    <div id="comment-section" class="comment-section info-box">
        <h2>📝 Agregar Comentarios a los Settings</h2>
        <div id="comment-list"></div>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <button type="button" id="btn-save-log" class="btn btn-secondary">
                💾 Agregar Comentarios al LOG
            </button>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        // Contador de seleccionados
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.diff-checkbox:checked').length;
            document.getElementById('selected-count').textContent = checked;

            const btn = document.getElementById('btn-generate-comments');
            btn.disabled = checked === 0;
        }

        // Event listeners para checkboxes
        document.querySelectorAll('.diff-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // Generar formulario de comentarios
        document.getElementById('btn-generate-comments').addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.diff-checkbox:checked');
            const commentList = document.getElementById('comment-list');

            // Limpiar lista anterior
            commentList.innerHTML = '';

            // Generar campos de comentario para cada checkbox seleccionado
            checkedBoxes.forEach((checkbox, index) => {
                const setting = checkbox.dataset.setting;
                const current = checkbox.dataset.current;
                const reference = checkbox.dataset.reference;

                const commentItem = document.createElement('div');
                commentItem.className = 'comment-item';
                commentItem.innerHTML = `
                    <div class="comment-label">${setting}</div>
                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;">
                        Current: ${current} | Reference: ${reference}
                    </div>
                    <textarea
                        class="comment-input"
                        data-setting="${setting}"
                        data-current="${current}"
                        data-reference="${reference}"
                        placeholder="Escribe tu comentario sobre este setting (ej: 'Funciona correctamente', 'No se aplica en frontend', etc.)"
                        rows="2"></textarea>
                `;

                commentList.appendChild(commentItem);
            });

            // Mostrar sección de comentarios
            document.getElementById('comment-section').classList.add('active');

            // Scroll hacia la sección de comentarios
            document.getElementById('comment-section').scrollIntoView({ behavior: 'smooth' });
        });

        // Guardar comentarios al LOG
        document.getElementById('btn-save-log').addEventListener('click', function() {
            const textareas = document.querySelectorAll('.comment-input');
            const comments = [];

            textareas.forEach(textarea => {
                // Permitir comentarios vacíos
                comments.push({
                    setting: textarea.dataset.setting,
                    current: textarea.dataset.current,
                    reference: textarea.dataset.reference,
                    comment: textarea.value.trim()
                });
            });

            // Crear formulario y enviarlo
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="save_comments" value="1">
                <input type="hidden" name="theme_name" value="<?php echo htmlspecialchars($active_theme); ?>">
                <input type="hidden" name="reference_name" value="<?php echo htmlspecialchars($reference_theme); ?>">
                <input type="hidden" name="comments_data" value='${JSON.stringify(comments)}'>
            `;

            document.body.appendChild(form);
            form.submit();
        });

        // Inicializar contador
        updateSelectedCount();
    </script>
</body>
</html>
