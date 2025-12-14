<?php
/**
 * Admin - Generador de Themes
 * Interfaz para crear themes personalizados del frontend
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

require_admin();
require_once APP_PATH . '/includes/theme-generator.php';

$message = '';
$error = '';

// Procesamiento POST - Generar Theme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_theme'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Sanitizar datos
        $data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'slug' => sanitize_input($_POST['slug'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'author' => sanitize_input($_POST['author'] ?? 'Shop Team'),

            // Colores
            'color_primary' => sanitize_input($_POST['color_primary'] ?? '#000000'),
            'color_secondary' => sanitize_input($_POST['color_secondary'] ?? '#d4af37'),
            'color_accent' => sanitize_input($_POST['color_accent'] ?? '#4facfe'),
            'color_text' => sanitize_input($_POST['color_text'] ?? '#1a1a1a'),
            'color_background' => sanitize_input($_POST['color_background'] ?? '#ffffff'),
            'color_success' => sanitize_input($_POST['color_success'] ?? '#2e7d32'),
            'color_warning' => sanitize_input($_POST['color_warning'] ?? '#f57c00'),
            'color_error' => sanitize_input($_POST['color_error'] ?? '#c62828'),
            'color_info' => sanitize_input($_POST['color_info'] ?? '#1565c0'),

            // Tipografía
            'font_family' => sanitize_input($_POST['font_family'] ?? 'sans-serif'),
            'font_size' => sanitize_input($_POST['font_size'] ?? '16px'),
            'line_height' => sanitize_input($_POST['line_height'] ?? '1.5'),

            // Componentes: Cards
            'card_border' => isset($_POST['card_border']),
            'card_shadow' => sanitize_input($_POST['card_shadow'] ?? 'subtle'),
            'card_rounded' => isset($_POST['card_rounded']),
            'card_hover' => sanitize_input($_POST['card_hover'] ?? 'glow'),
            'card_buttons' => sanitize_input($_POST['card_buttons'] ?? 'show'),
            'card_buttons_position' => sanitize_input($_POST['card_buttons_position'] ?? 'center'),
            'card_buttons_spacing' => sanitize_input($_POST['card_buttons_spacing'] ?? 'normal'),

            // Componentes: Buttons
            'button_style' => sanitize_input($_POST['button_style'] ?? 'solid'),
            'button_rounded' => isset($_POST['button_rounded']),
            'button_shadow' => isset($_POST['button_shadow'])
        ];

        // Validar
        $validation = validate_theme_input($data['slug'], $data['name'], [
            'primary' => $data['color_primary'],
            'secondary' => $data['color_secondary'],
            'background' => $data['color_background']
        ]);

        if (!$validation['valid']) {
            $error = implode('<br>', $validation['errors']);
        } else {
            // Generar theme
            $result = generate_theme($data);

            if ($result['success']) {
                $message = $result['message'];
                log_admin_action('theme_generated', $_SESSION['username'], [
                    'slug' => $result['slug'],
                    'name' => $data['name']
                ]);

                // Opcionalmente redirigir a config-themes
                // redirect(url('/admin/?page=config-themes&msg=theme_created'));
            } else {
                $error = $result['message'];
            }
        }
    }
}

// ============================================================================
// AJAX ENDPOINTS
// ============================================================================

// AJAX: Mapear Paleta Inteligentemente
if (isset($_GET['action']) && $_GET['action'] === 'map_palette') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents('php://input'), true);
    $colors_array = $json_input['colors'] ?? [];

    // Validar que sean 4 colores válidos
    if (count($colors_array) !== 4) {
        echo json_encode(['success' => false, 'message' => 'Debes proporcionar exactamente 4 colores']);
        exit;
    }

    // Validar formato hex
    foreach ($colors_array as $color) {
        if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            echo json_encode(['success' => false, 'message' => 'Formato de color inválido: ' . $color]);
            exit;
        }
    }

    $mapped = map_colors_intelligently($colors_array);

    if (!$mapped) {
        echo json_encode(['success' => false, 'message' => 'Error al mapear los colores']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'colors' => $mapped,
        'raw_colors' => $colors_array
    ]);
    exit;
}

// AJAX: Obtener Configuración de Theme
if (isset($_GET['action']) && $_GET['action'] === 'get_theme_config') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents('php://input'), true);
    $slug = sanitize_input($json_input['slug'] ?? '');

    if (empty($slug)) {
        echo json_encode(['success' => false, 'message' => 'Slug no proporcionado']);
        exit;
    }

    // Obtener config del theme
    $theme_dir = PUBLIC_PATH . "/assets/themes/{$slug}";
    $config_file = $theme_dir . '/theme.json';

    if (!file_exists($config_file)) {
        echo json_encode(['success' => false, 'message' => 'Theme no encontrado']);
        exit;
    }

    $config = read_json($config_file);

    if (!$config) {
        echo json_encode(['success' => false, 'message' => 'Error al leer configuración del theme']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    exit;
}

// ============================================================================
// PÁGINA PRINCIPAL
// ============================================================================

// Cargar themes disponibles (para clonar en fase 3)
$available_themes = get_available_themes();

// Generar CSRF token
$csrf_token = generate_csrf_token();

// Config del sitio
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Generador de Themes';
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>

    <!-- ESTILOS BASE DEL ADMIN (igual que todas las páginas) -->
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1400px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }

        /* Cards Grid System */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: box-shadow 0.3s;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .card-full {
            grid-column: 1 / -1;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-description {
            color: #666;
            font-size: 13px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .form-group { margin-bottom: 15px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .form-group textarea { min-height: 80px; resize: vertical; font-family: inherit; }

        .compact-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .btn-save-container {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-save {
            padding: 14px 40px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .cards-grid { grid-template-columns: 1fr; }

            /* Color grid en tablet - 2 columnas */
            .color-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .compact-grid-2 { grid-template-columns: 1fr; }
            .cards-grid { grid-template-columns: 1fr; }

            /* Color grid en mobile - 1 columna */
            .color-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================
           ESTILOS ESPECÍFICOS DEL GENERADOR SOLAMENTE
           ============================================ */

        /* Info box */
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 6px;
        }

        .info-box p {
            margin: 0;
            color: #0d47a1;
            line-height: 1.6;
            font-size: 14px;
        }

        /* Color picker con preview */
        .color-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .color-input-wrapper input[type="color"] {
            width: 50px;
            height: 38px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
        }

        .color-input-wrapper input[type="text"] {
            flex: 1;
        }

        /* Radio groups horizontales */
        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: 400;
            font-size: 14px;
            padding: 4px 0;
        }

        .radio-group input[type="radio"] {
            cursor: pointer;
            margin: 0;
            width: 18px;
            height: 18px;
        }

        .radio-group label:has(input[type="radio"]:checked) {
            font-weight: 500;
            color: #667eea;
        }

        /* Checkbox groups */
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 400;
            padding: 8px 10px;
            border-radius: 6px;
            transition: background 0.3s;
            font-size: 14px;
        }

        .checkbox-group label:hover {
            background: #f8f9ff;
        }

        .checkbox-group input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
            margin: 0;
        }

        /* Helper text */
        small.helper-text {
            display: block;
            margin-top: 6px;
            color: #777;
            font-size: 12px;
            line-height: 1.4;
        }
    </style>

    <!-- Admin Common Responsive Styles -->
    <?php include APP_PATH . '/includes/admin/admin-common-styles.php'; ?>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <h1 style="margin-bottom: 24px;">✨ Generador de Themes</h1>

        <!-- Info Box -->
        <div class="info-box">
            <p>
                <strong>ℹ️ Crea themes personalizados para tu tienda</strong><br>
                Define colores, tipografía y estilos de componentes. El sistema generará automáticamente
                todos los archivos CSS necesarios con más de 150 variables personalizadas.
            </p>
        </div>

        <!-- Mensajes -->
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
        <?php endif; ?>

        <!-- Formulario -->
            <form method="POST" action="" id="theme-generator-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Método de Creación -->
                <div class="card card-full" style="margin-bottom: 20px;">
                    <div class="card-title">
                        🎨 Método de Creación
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 12px; font-weight: 500; color: #555;">Elige cómo crear tu theme:</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="creation_method" value="new" checked data-onchange="toggleCreationMethod">
                                Nuevo desde cero
                            </label>
                            <label>
                                <input type="radio" name="creation_method" value="palette" data-onchange="toggleCreationMethod">
                                Importar paleta de ColorHunt
                            </label>
                            <label>
                                <input type="radio" name="creation_method" value="edit" data-onchange="toggleCreationMethod">
                                Editar theme existente
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Importar Paleta de ColorHunt -->
                <div class="card card-full" id="palette-import" style="display: none; margin-bottom: 20px;">
                    <div class="card-title">
                        🎨 Importar Paleta de ColorHunt
                    </div>

                    <!-- Opción 1: Paletas Populares -->
                    <div style="margin-bottom: 30px;">
                        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #2c3e50;">
                            ⭐ Opción 1: Paletas Populares
                        </h3>
                        <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                            Selecciona una de las 114 paletas más populares de ColorHunt:
                        </p>

                        <!-- Loading state -->
                        <div id="palettes-loading" style="text-align: center; padding: 20px; color: #999;">
                            ⏳ Cargando paletas populares...
                        </div>

                        <!-- Grid de paletas -->
                        <div id="palettes-grid" style="display: none; max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; background: #fafafa;">
                            <!-- Las paletas se cargarán aquí dinámicamente -->
                        </div>
                    </div>

                    <!-- Separador -->
                    <div style="border-top: 2px dashed #e0e0e0; margin: 30px 0; position: relative;">
                        <span style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: white; padding: 0 10px; color: #999; font-size: 13px;">
                            O
                        </span>
                    </div>

                    <!-- Opción 2: Paleta Personalizada -->
                    <div>
                        <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #2c3e50;">
                            ✏️ Opción 2: Paleta Personalizada
                        </h3>

                        <div class="info-box" style="margin-bottom: 15px;">
                            <p style="font-size: 13px;">
                                <strong>ℹ️ Cómo usar:</strong><br>
                                1. Ve a <a href="https://colorhunt.co" target="_blank" rel="noopener" style="color: #667eea; text-decoration: underline;">ColorHunt.co</a><br>
                                2. Elige una paleta que te guste (debe tener 4 colores)<br>
                                3. Haz click en el ícono de copiar (📋) en la paleta<br>
                                4. Pega directamente aquí los 4 colores
                            </p>
                        </div>

                        <div class="form-group">
                            <label for="palette-colors">Pega los 4 colores aquí (uno por línea o separados por espacios)</label>
                            <textarea id="palette-colors" rows="5" style="font-family: monospace; font-size: 14px; line-height: 1.8;" placeholder="#005461
#018790
#00B7B5
#F4F4F4"></textarea>
                            <small class="helper-text">Copia directamente desde ColorHunt y pégalos aquí. El sistema detectará automáticamente los 4 colores.</small>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 16px;">
                        <button type="button" data-action="mapPalette" class="btn-save">
                            🎨 Aplicar Mapeo Inteligente
                        </button>
                    </div>

                    <small class="helper-text" style="display: block; text-align: center; margin-top: 12px;">
                        El sistema mapeará automáticamente los colores a primary, secondary, accent, background y text con validación de contraste WCAG.
                    </small>
                </div>

                <!-- Editar Theme Existente -->
                <div class="card card-full" id="edit-theme" style="display: none; margin-bottom: 20px;">
                    <div class="card-title">
                        ✏️ Editar Theme Existente
                    </div>

                    <div class="info-box" style="margin-bottom: 20px;">
                        <p>
                            <strong>ℹ️ Cómo funciona:</strong><br>
                            Selecciona un theme existente para cargarlo en el formulario.<br>
                            Modifica lo que necesites y regenera el theme (se sobrescribirá el original).
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="edit-theme-select">Selecciona el theme a editar</label>
                        <select id="edit-theme-select" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                            <option value="">-- Selecciona un theme --</option>
                            <?php foreach ($available_themes as $slug => $theme): ?>
                                <option value="<?php echo htmlspecialchars($slug); ?>">
                                    <?php echo htmlspecialchars($theme['name']); ?> (<?php echo htmlspecialchars($slug); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="helper-text">El theme seleccionado se cargará en el formulario para editarlo</small>
                    </div>

                    <div style="text-align: center; margin-top: 16px;">
                        <button type="button" data-action="loadThemeForEdit" class="btn-save">
                            📥 Cargar Theme
                        </button>
                    </div>
                </div>

                <!-- Grid: Información Básica + Tipografía -->
                <div class="cards-grid">
                    <!-- Información Básica -->
                    <div class="card">
                    <div class="card-title">
                        📋 Información Básica
                    </div>

                    <div class="form-group">
                        <label for="theme-name">Nombre del Theme *</label>
                        <input type="text" name="name" id="theme-name" required
                               placeholder="Ej: Mi Theme Personalizado"
                               data-onchange="generateSlug">
                        <small class="helper-text">Nombre descriptivo del theme</small>
                    </div>

                    <div class="form-group">
                        <label for="theme-slug">Slug *</label>
                        <input type="text" name="slug" id="theme-slug" required
                               pattern="[a-z0-9-]+"
                               placeholder="mi-theme-personalizado">
                        <small class="helper-text">Identificador único (solo minúsculas, números y guiones). Se genera automáticamente.</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Descripción</label>
                        <textarea name="description" id="description"
                                  placeholder="Describe las características de tu theme..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="author">Autor</label>
                        <input type="text" name="author" id="author" value="Shop Team">
                    </div>
                </div>

                    <!-- Tipografía -->
                    <div class="card">
                        <div class="card-title">
                            📝 Tipografía
                        </div>

                        <div class="form-group">
                            <label>Familia de Fuente</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="font_family" value="sans-serif" checked>
                                    Sans-serif
                                </label>
                                <label>
                                    <input type="radio" name="font_family" value="serif">
                                    Serif
                                </label>
                                <label>
                                    <input type="radio" name="font_family" value="monospace">
                                    Monospace
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="font_size">Tamaño Base</label>
                            <select name="font_size" id="font_size">
                                <option value="14px">14px</option>
                                <option value="16px" selected>16px</option>
                                <option value="18px">18px</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="line_height">Altura de Línea</label>
                            <select name="line_height" id="line_height">
                                <option value="1.4">1.4</option>
                                <option value="1.5" selected>1.5</option>
                                <option value="1.6">1.6</option>
                                <option value="1.8">1.8</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Grid: Colores (ancho completo) -->
                <div class="cards-grid">
                    <!-- Colores -->
                    <div class="card card-full">
                    <div class="card-title">
                        🎨 Colores
                    </div>

                    <div class="color-grid">
                        <div class="form-group">
                            <label>Primary *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_primary" value="#000000" id="color-primary">
                                <input type="text" value="#000000" readonly>
                            </div>
                            <small class="helper-text">Color principal del theme</small>
                        </div>

                        <div class="form-group">
                            <label>Secondary *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_secondary" value="#d4af37" id="color-secondary">
                                <input type="text" value="#d4af37" readonly>
                            </div>
                            <small class="helper-text">Color secundario (acentos)</small>
                        </div>

                        <div class="form-group">
                            <label>Accent</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_accent" value="#4facfe" id="color-accent">
                                <input type="text" value="#4facfe" readonly>
                            </div>
                            <small class="helper-text">Color de acento adicional</small>
                        </div>

                        <div class="form-group">
                            <label>Text *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_text" value="#1a1a1a" id="color-text">
                                <input type="text" value="#1a1a1a" readonly>
                            </div>
                            <small class="helper-text">Color del texto principal</small>
                        </div>

                        <div class="form-group">
                            <label>Background *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_background" value="#ffffff" id="color-background">
                                <input type="text" value="#ffffff" readonly>
                            </div>
                            <small class="helper-text">Color de fondo</small>
                        </div>
                    </div>

                    <details style="margin-top: 20px;">
                        <summary style="cursor: pointer; font-weight: 500; color: #667eea;">
                            Colores de Estado (Opcional)
                        </summary>

                        <div class="color-grid" style="margin-top: 16px;">
                            <div class="form-group">
                                <label>Success</label>
                                <div class="color-input-wrapper">
                                    <input type="color" name="color_success" value="#2e7d32">
                                    <input type="text" value="#2e7d32" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Warning</label>
                                <div class="color-input-wrapper">
                                    <input type="color" name="color_warning" value="#f57c00">
                                    <input type="text" value="#f57c00" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Error</label>
                                <div class="color-input-wrapper">
                                    <input type="color" name="color_error" value="#c62828">
                                    <input type="text" value="#c62828" readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Info</label>
                                <div class="color-input-wrapper">
                                    <input type="color" name="color_info" value="#1565c0">
                                    <input type="text" value="#1565c0" readonly>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
                </div>

                <!-- Grid: Cards + Buttons -->
                <div class="cards-grid">
                    <!-- Componentes: Cards -->
                    <div class="card">
                    <div class="card-title">
                        🎴 Componentes: Cards de Productos
                    </div>

                    <div class="form-group">
                        <label>Sombreado</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_shadow" value="none">
                                Sin sombra
                            </label>
                            <label>
                                <input type="radio" name="card_shadow" value="subtle" checked>
                                Sutil
                            </label>
                            <label>
                                <input type="radio" name="card_shadow" value="medium">
                                Medio
                            </label>
                            <label>
                                <input type="radio" name="card_shadow" value="deep">
                                Profundo
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Efecto Hover</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_hover" value="none">
                                Sin efecto
                            </label>
                            <label>
                                <input type="radio" name="card_hover" value="lift">
                                Elevación
                            </label>
                            <label>
                                <input type="radio" name="card_hover" value="glow" checked>
                                Brillo
                            </label>
                            <label>
                                <input type="radio" name="card_hover" value="lift-3d">
                                Elevación 3D
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="card_border" checked>
                                Mostrar borde
                            </label>
                            <label>
                                <input type="checkbox" name="card_rounded">
                                Bordes redondeados
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Botones en Cards</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_buttons" value="show" checked>
                                Mostrar botones
                            </label>
                            <label>
                                <input type="radio" name="card_buttons" value="hide">
                                Ocultar (card clickeable)
                            </label>
                        </div>
                        <small class="helper-text">Si se ocultan, toda la card será clickeable</small>
                    </div>

                    <div class="form-group" id="card-buttons-position-group">
                        <label>Posición de Botones</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_buttons_position" value="center" checked>
                                Centrado
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_position" value="left">
                                Izquierda
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_position" value="right">
                                Derecha
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="card-buttons-spacing-group">
                        <label>Espaciado de Botones</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_buttons_spacing" value="normal" checked>
                                Normal
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_spacing" value="compact">
                                Compacto
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_spacing" value="spacious">
                                Amplio
                            </label>
                        </div>
                    </div>
                </div>

                    <!-- Componentes: Buttons -->
                    <div class="card">
                    <div class="card-title">
                        🔘 Componentes: Botones
                    </div>

                    <div class="form-group">
                        <label>Estilo</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="button_style" value="solid" checked>
                                Sólido
                            </label>
                            <label>
                                <input type="radio" name="button_style" value="outline">
                                Outline
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="button_rounded">
                                Botones redondeados
                            </label>
                            <label>
                                <input type="checkbox" name="button_shadow">
                                Con sombra
                            </label>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Botones de acción -->
                <div style="margin-top: 32px; text-align: center; display: flex; gap: 16px; justify-content: center;">
                    <button type="button" data-action="showPreview" style="padding: 14px 40px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                        👁️ Preview
                    </button>
                    <button type="submit" name="generate_theme" class="btn-save">
                        💾 Generar Theme
                    </button>
                </div>
            </form>
    </div>

    <!-- JavaScript -->
    <script nonce="<?= csp_nonce() ?>">
        // Auto-generar slug desde nombre
        function generateSlug(event, element, params) {
            const name = element.value;
            const slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-+|-+$/g, '');

            document.getElementById('theme-slug').value = slug;
        }

        // Toggle método de creación
        function toggleCreationMethod(event, element, params) {
            const value = element.value;
            const paletteDiv = document.getElementById('palette-import');
            const editDiv = document.getElementById('edit-theme');

            // Ocultar todos
            paletteDiv.style.display = 'none';
            editDiv.style.display = 'none';

            // Mostrar el seleccionado
            if (value === 'palette') {
                paletteDiv.style.display = 'block';
            } else if (value === 'edit') {
                editDiv.style.display = 'block';
            }
        }

        // Mapear paleta de ColorHunt
        function mapPalette(event, element, params) {
            event.preventDefault();

            // Obtener el texto del textarea
            const paletteText = document.getElementById('palette-colors').value.trim();

            if (!paletteText) {
                showModal({
                    title: 'Error',
                    message: 'Por favor pega los 4 colores de ColorHunt',
                    icon: '⚠️',
                    confirmType: 'danger'
                });
                return;
            }

            // Extraer todos los colores hex del texto (formato #RRGGBB)
            const hexPattern = /#[a-fA-F0-9]{6}/g;
            const colors = paletteText.match(hexPattern);

            // Validar que sean exactamente 4 colores
            if (!colors || colors.length !== 4) {
                showModal({
                    title: 'Error de Formato',
                    message: `Se encontraron ${colors ? colors.length : 0} colores. Necesitas exactamente 4 colores en formato #RRGGBB`,
                    icon: '⚠️',
                    confirmType: 'danger'
                });
                return;
            }

            // Mostrar loading en el botón
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '⏳ Mapeando...';
            btn.disabled = true;

            // Enviar petición AJAX
            fetch('<?php echo url('/admin/?page=generador-themes&action=map_palette'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ colors: colors })
            })
            .then(res => res.json())
            .then(data => {
                btn.textContent = originalText;
                btn.disabled = false;

                if (data.success) {
                    // Poblar inputs de color
                    document.getElementById('color-primary').value = data.colors.primary;
                    document.getElementById('color-secondary').value = data.colors.secondary;
                    document.getElementById('color-accent').value = data.colors.accent;
                    document.getElementById('color-background').value = data.colors.background;
                    document.getElementById('color-text').value = data.colors.text;

                    // Sincronizar text inputs
                    document.querySelector('#color-primary').parentElement.querySelector('input[type="text"]').value = data.colors.primary;
                    document.querySelector('#color-secondary').parentElement.querySelector('input[type="text"]').value = data.colors.secondary;
                    document.querySelector('#color-accent').parentElement.querySelector('input[type="text"]').value = data.colors.accent;
                    document.querySelector('#color-background').parentElement.querySelector('input[type="text"]').value = data.colors.background;
                    document.querySelector('#color-text').parentElement.querySelector('input[type="text"]').value = data.colors.text;

                    showModal({
                        title: 'Éxito',
                        message: '✅ Paleta mapeada exitosamente con contraste WCAG validado',
                        icon: '🎨',
                        confirmType: 'success'
                    });
                } else {
                    showModal({
                        title: 'Error',
                        message: data.message,
                        icon: '⚠️',
                        confirmType: 'danger'
                    });
                }
            })
            .catch(error => {
                btn.textContent = originalText;
                btn.disabled = false;

                showModal({
                    title: 'Error de Red',
                    message: 'Error al conectar con el servidor: ' + error,
                    icon: '⚠️',
                    confirmType: 'danger'
                });
            });
        }

        // Cargar paletas populares
        let popularPalettes = [];

        function loadPopularPalettes() {
            const loadingDiv = document.getElementById('palettes-loading');
            const gridDiv = document.getElementById('palettes-grid');

            fetch('<?php echo url('/assets/data/paletas-populares.json'); ?>')
                .then(res => res.json())
                .then(data => {
                    popularPalettes = data;

                    // Ocultar loading, mostrar grid
                    loadingDiv.style.display = 'none';
                    gridDiv.style.display = 'grid';

                    // Renderizar paletas
                    renderPalettesGrid(data);
                })
                .catch(error => {
                    loadingDiv.innerHTML = '❌ Error al cargar paletas populares';
                    console.error('Error loading palettes:', error);
                });
        }

        function renderPalettesGrid(palettes) {
            const gridDiv = document.getElementById('palettes-grid');

            // Crear grid responsivo
            gridDiv.style.display = 'grid';
            gridDiv.style.gridTemplateColumns = 'repeat(auto-fill, minmax(120px, 1fr))';
            gridDiv.style.gap = '12px';

            // Renderizar cada paleta
            palettes.forEach(palette => {
                const paletteCard = document.createElement('div');
                paletteCard.className = 'palette-card';
                paletteCard.setAttribute('data-action', 'selectPopularPalette');
                paletteCard.setAttribute('data-palette-id', palette.id);
                paletteCard.style.cursor = 'pointer';
                paletteCard.style.borderRadius = '8px';
                paletteCard.style.overflow = 'hidden';
                paletteCard.style.border = '2px solid transparent';
                paletteCard.style.transition = 'all 0.2s';
                paletteCard.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';

                // Crear los 4 cuadros de colores
                paletteCard.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; height: 80px;">
                        <div style="background: ${palette.colors[0]};"></div>
                        <div style="background: ${palette.colors[1]};"></div>
                        <div style="background: ${palette.colors[2]};"></div>
                        <div style="background: ${palette.colors[3]};"></div>
                    </div>
                    <div style="background: white; padding: 6px; text-align: center; font-size: 11px; color: #666;">
                        #${palette.id}
                    </div>
                `;

                // Hover effect
                paletteCard.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#667eea';
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.3)';
                });

                paletteCard.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('selected')) {
                        this.style.borderColor = 'transparent';
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                    }
                });

                gridDiv.appendChild(paletteCard);
            });
        }

        function selectPopularPalette(event, element, params) {
            const paletteId = parseInt(params?.paletteId);
            if (!paletteId) return;

            // Encontrar la paleta
            const palette = popularPalettes.find(p => p.id === paletteId);
            if (!palette) return;

            // Remover selección anterior
            document.querySelectorAll('.palette-card').forEach(card => {
                card.classList.remove('selected');
                card.style.borderColor = 'transparent';
                card.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            });

            // Marcar como seleccionada
            element.classList.add('selected');
            element.style.borderColor = '#667eea';
            element.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.5)';

            // Aplicar colores mapeados directamente (ya vienen procesados del JSON)
            const mapped = palette.mapped;

            document.getElementById('color-primary').value = mapped.primary;
            document.getElementById('color-secondary').value = mapped.secondary;
            document.getElementById('color-accent').value = mapped.accent;
            document.getElementById('color-background').value = mapped.background;
            document.getElementById('color-text').value = mapped.text;

            // Sincronizar text inputs
            document.querySelector('#color-primary').parentElement.querySelector('input[type="text"]').value = mapped.primary;
            document.querySelector('#color-secondary').parentElement.querySelector('input[type="text"]').value = mapped.secondary;
            document.querySelector('#color-accent').parentElement.querySelector('input[type="text"]').value = mapped.accent;
            document.querySelector('#color-background').parentElement.querySelector('input[type="text"]').value = mapped.background;
            document.querySelector('#color-text').parentElement.querySelector('input[type="text"]').value = mapped.text;

            // Scroll al formulario de colores para que el usuario vea los cambios
            document.getElementById('colors-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Cargar paletas cuando se muestra la sección
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar paletas al inicio si la sección de palette está visible
            const paletteImport = document.getElementById('palette-import');
            if (paletteImport && paletteImport.style.display !== 'none') {
                loadPopularPalettes();
            }
        });

        // También cargar cuando se cambia a método de creación "palette"
        const creationMethodRadios = document.querySelectorAll('[name="creation_method"]');
        creationMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'palette') {
                    // Solo cargar si aún no se han cargado
                    if (popularPalettes.length === 0) {
                        loadPopularPalettes();
                    }
                }
            });
        });

        // Sincronizar color picker con text input
        document.querySelectorAll('.color-input-wrapper').forEach(wrapper => {
            const colorInput = wrapper.querySelector('input[type="color"]');
            const textInput = wrapper.querySelector('input[type="text"]');

            if (colorInput && textInput) {
                colorInput.addEventListener('input', function() {
                    textInput.value = this.value;
                });

                textInput.addEventListener('input', function() {
                    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                        colorInput.value = this.value;
                    }
                });
            }
        });

        // Preview del theme
        function showPreview(event, element, params) {
            event.preventDefault();

            // Leer valores del formulario
            const config = {
                // Colores
                primary: document.getElementById('color-primary').value || '#000000',
                secondary: document.getElementById('color-secondary').value || '#d4af37',
                accent: document.getElementById('color-accent').value || '#4facfe',
                text: document.getElementById('color-text').value || '#1a1a1a',
                background: document.getElementById('color-background').value || '#ffffff',

                // Tipografía
                fontFamily: document.querySelector('[name="font_family"]:checked')?.value || 'sans-serif',
                fontSize: document.querySelector('[name="font_size"]').value || '16px',
                lineHeight: document.querySelector('[name="line_height"]').value || '1.5',

                // Cards
                cardBorder: document.querySelector('[name="card_border"]')?.checked || false,
                cardShadow: document.querySelector('[name="card_shadow"]:checked')?.value || 'subtle',
                cardRounded: document.querySelector('[name="card_rounded"]')?.checked || false,
                cardHover: document.querySelector('[name="card_hover"]:checked')?.value || 'glow',
                cardButtons: document.querySelector('[name="card_buttons"]:checked')?.value || 'show',
                cardButtonsPosition: document.querySelector('[name="card_buttons_position"]:checked')?.value || 'center',
                cardButtonsSpacing: document.querySelector('[name="card_buttons_spacing"]:checked')?.value || 'normal',

                // Buttons
                buttonStyle: document.querySelector('[name="button_style"]:checked')?.value || 'solid',
                buttonRounded: document.querySelector('[name="button_rounded"]')?.checked || false,
                buttonShadow: document.querySelector('[name="button_shadow"]')?.checked || false
            };

            // Generar estilos CSS
            const borderRadius = config.cardRounded ? '8px' : '0';
            const borderStyle = config.cardBorder ? `1px solid ${config.primary}20` : 'none';

            let boxShadow = 'none';
            if (config.cardShadow === 'subtle') boxShadow = '0 2px 4px rgba(0,0,0,0.08)';
            else if (config.cardShadow === 'medium') boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            else if (config.cardShadow === 'deep') boxShadow = '0 8px 20px rgba(0,0,0,0.18)';

            const btnBorderRadius = config.buttonRounded ? '50px' : '4px';
            const btnBoxShadow = config.buttonShadow ? '0 2px 4px rgba(0,0,0,0.15)' : 'none';

            // Helper: Generar card de producto
            function generateProductCard(config, borderStyle, borderRadius, boxShadow, btnBorderRadius, btnBoxShadow, index) {
                const gradients = [
                    `linear-gradient(135deg, ${config.primary}20, ${config.secondary}20)`,
                    `linear-gradient(135deg, ${config.accent}20, ${config.primary}20)`
                ];
                const prices = ['$99.99', '$149.99'];

                // Determinar alineación de botones
                let buttonsAlign = 'center';
                if (config.cardButtonsPosition === 'left') buttonsAlign = 'flex-start';
                else if (config.cardButtonsPosition === 'right') buttonsAlign = 'flex-end';

                // Determinar spacing de botones
                let buttonsGap = '8px';
                let buttonPadding = '8px 16px';
                if (config.cardButtonsSpacing === 'compact') {
                    buttonsGap = '4px';
                    buttonPadding = '6px 12px';
                } else if (config.cardButtonsSpacing === 'spacious') {
                    buttonsGap = '12px';
                    buttonPadding = '10px 20px';
                }

                // Generar botones si están habilitados
                let buttonsHTML = '';
                if (config.cardButtons === 'show') {
                    const btnStyle = config.buttonStyle === 'solid'
                        ? `background: ${config.primary}; color: white; border: none;`
                        : `background: transparent; color: ${config.primary}; border: 2px solid ${config.primary};`;

                    buttonsHTML = `
                        <div style="display: flex; gap: ${buttonsGap}; justify-content: ${buttonsAlign}; margin-top: 10px;">
                            <button style="padding: ${buttonPadding}; ${btnStyle} border-radius: ${btnBorderRadius}; font-size: 0.85em; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                🛒 Comprar
                            </button>
                            <button style="padding: ${buttonPadding}; background: transparent; color: ${config.secondary}; border: 1px solid ${config.secondary}; border-radius: ${btnBorderRadius}; font-size: 0.85em; cursor: pointer;">
                                ❤️
                            </button>
                        </div>
                    `;
                }

                return `
                    <div class="preview-card" style="background: white; border: ${borderStyle}; border-radius: ${borderRadius}; box-shadow: ${boxShadow}; overflow: hidden; transition: all 0.3s; ${config.cardButtons === 'hide' ? 'cursor: pointer;' : ''}">
                        <div style="width: 100%; height: 150px; background: ${gradients[index - 1]};"></div>
                        <div style="padding: 12px;">
                            <h3 style="font-size: 1.05em; margin-bottom: 6px; color: ${config.primary};">Producto Ejemplo</h3>
                            <p style="font-size: 0.85em; color: ${config.text}80; margin-bottom: 8px;">Descripción corta del producto</p>
                            <p style="font-size: 1.1em; font-weight: 600; color: ${config.secondary};">${prices[index - 1]}</p>
                            ${buttonsHTML}
                        </div>
                    </div>
                `;
            }

            // Generar HTML del preview
            const previewHTML = `
                <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, ${config.fontFamily}; font-size: ${config.fontSize}; line-height: ${config.lineHeight}; background: ${config.background}; color: ${config.text}; padding: 20px; max-height: 85vh; overflow-y: auto;">

                    <!-- Paleta de Colores -->
                    <h2 style="margin-bottom: 15px; color: ${config.primary}; font-size: 1.3em;">🎨 Paleta de Colores</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 30px;">
                        <div style="text-align: center;">
                            <div style="width: 100%; height: 80px; background: ${config.primary}; border-radius: ${borderRadius}; margin-bottom: 8px;"></div>
                            <small style="font-size: 12px;">Primary</small><br>
                            <code style="font-size: 11px; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">${config.primary}</code>
                        </div>
                        <div style="text-align: center;">
                            <div style="width: 100%; height: 80px; background: ${config.secondary}; border-radius: ${borderRadius}; margin-bottom: 8px;"></div>
                            <small style="font-size: 12px;">Secondary</small><br>
                            <code style="font-size: 11px; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">${config.secondary}</code>
                        </div>
                        <div style="text-align: center;">
                            <div style="width: 100%; height: 80px; background: ${config.accent}; border-radius: ${borderRadius}; margin-bottom: 8px;"></div>
                            <small style="font-size: 12px;">Accent</small><br>
                            <code style="font-size: 11px; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">${config.accent}</code>
                        </div>
                        <div style="text-align: center;">
                            <div style="width: 100%; height: 80px; background: ${config.background}; border: 1px solid #ddd; border-radius: ${borderRadius}; margin-bottom: 8px;"></div>
                            <small style="font-size: 12px;">Background</small><br>
                            <code style="font-size: 11px; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">${config.background}</code>
                        </div>
                        <div style="text-align: center;">
                            <div style="width: 100%; height: 80px; background: ${config.text}; border-radius: ${borderRadius}; margin-bottom: 8px;"></div>
                            <small style="font-size: 12px;">Text</small><br>
                            <code style="font-size: 11px; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">${config.text}</code>
                        </div>
                    </div>

                    <!-- Tipografía -->
                    <h2 style="margin-bottom: 15px; color: ${config.primary}; font-size: 1.3em;">📝 Tipografía</h2>
                    <div style="margin-bottom: 30px; padding: 15px; background: white; border-radius: ${borderRadius}; border: ${borderStyle};">
                        <h1 style="font-size: 2em; margin-bottom: 10px; color: ${config.primary};">Heading 1</h1>
                        <h2 style="font-size: 1.5em; margin-bottom: 10px; color: ${config.primary};">Heading 2</h2>
                        <h3 style="font-size: 1.2em; margin-bottom: 10px; color: ${config.primary};">Heading 3</h3>
                        <p style="margin-bottom: 10px; color: ${config.text};">Este es un párrafo de ejemplo con el tamaño de fuente base (${config.fontSize}) y altura de línea ${config.lineHeight}. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                        <small style="color: ${config.text}80;">Texto pequeño con opacidad</small>
                    </div>

                    <!-- Botones -->
                    <h2 style="margin-bottom: 15px; color: ${config.primary}; font-size: 1.3em;">🔘 Botones</h2>
                    <div style="display: flex; gap: 12px; margin-bottom: 30px; flex-wrap: wrap;">
                        ${config.buttonStyle === 'solid' ? `
                            <button style="padding: 12px 28px; background: ${config.primary}; color: white; border: none; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Primary Button
                            </button>
                            <button style="padding: 12px 28px; background: ${config.secondary}; color: white; border: none; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Secondary Button
                            </button>
                        ` : `
                            <button style="padding: 12px 28px; background: transparent; color: ${config.primary}; border: 2px solid ${config.primary}; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Primary Button
                            </button>
                            <button style="padding: 12px 28px; background: transparent; color: ${config.secondary}; border: 2px solid ${config.secondary}; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Secondary Button
                            </button>
                        `}
                    </div>

                    <!-- Cards de Producto -->
                    <h2 style="margin-bottom: 15px; color: ${config.primary}; font-size: 1.3em;">🎴 Cards de Producto</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                        ${generateProductCard(config, borderStyle, borderRadius, boxShadow, btnBorderRadius, btnBoxShadow, 1)}
                        ${generateProductCard(config, borderStyle, borderRadius, boxShadow, btnBorderRadius, btnBoxShadow, 2)}
                    </div>

                    <p style="margin-top: 20px; text-align: center; font-size: 0.85em; color: ${config.text}80;">
                        ℹ️ Este es un preview aproximado. El theme generado incluirá más variaciones y estados.
                    </p>
                </div>
            `;

            // Agregar CSS para hover effect de cards
            let hoverCSS = '';
            if (config.cardHover === 'lift') {
                hoverCSS = '.preview-card:hover { transform: translateY(-8px); box-shadow: 0 8px 16px rgba(0,0,0,0.15) !important; }';
            } else if (config.cardHover === 'glow') {
                hoverCSS = `.preview-card:hover { box-shadow: 0 0 0 3px ${config.primary}33, ${boxShadow} !important; }`;
            } else if (config.cardHover === 'lift-3d') {
                hoverCSS = '.preview-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 16px 32px rgba(0,0,0,0.22) !important; }';
            }

            const styleTag = document.createElement('style');
            styleTag.innerHTML = hoverCSS;
            document.head.appendChild(styleTag);

            // Mostrar modal con el preview
            showModal({
                title: '👁️ Preview del Theme',
                message: '',
                details: previewHTML,
                confirmText: 'Cerrar',
                showCancel: false,
                onConfirm: function() {
                    // Limpiar estilos temporales
                    document.head.removeChild(styleTag);

                    // Restaurar estilos del modal
                    const modalContainer = document.querySelector('.modal-container');
                    const cancelBtn = document.getElementById('modalCancelBtn');

                    if (modalContainer) {
                        modalContainer.style.maxWidth = '500px';
                        modalContainer.style.maxHeight = '80vh';
                    }

                    if (cancelBtn) {
                        cancelBtn.style.display = '';
                    }
                }
            });

            // Ajustar estilos del modal para preview
            const modalContainer = document.querySelector('.modal-container');
            const cancelBtn = document.getElementById('modalCancelBtn');

            if (modalContainer) {
                // En desktop: modal más grande para evitar scroll
                if (window.innerWidth >= 1024) {
                    modalContainer.style.maxWidth = '1100px';
                    modalContainer.style.maxHeight = '95vh';
                } else {
                    modalContainer.style.maxWidth = '90%';
                    modalContainer.style.maxHeight = '85vh';
                }
            }

            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
        }

        // Cargar theme para editar
        function loadThemeForEdit(event, element, params) {
            event.preventDefault();

            const select = document.getElementById('edit-theme-select');
            const slug = select.value;

            if (!slug) {
                showModal({
                    title: 'Error',
                    message: 'Por favor selecciona un theme',
                    icon: '⚠️',
                    confirmType: 'danger'
                });
                return;
            }

            // Mostrar loading en el botón
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '⏳ Cargando...';
            btn.disabled = true;

            // Obtener config del theme
            fetch('<?php echo url('/admin/?page=generador-themes&action=get_theme_config'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ slug: slug })
            })
            .then(res => res.json())
            .then(data => {
                btn.textContent = originalText;
                btn.disabled = false;

                if (data.success) {
                    const config = data.config;

                    // Poblar información básica
                    document.getElementById('theme-name').value = config.name || '';
                    document.getElementById('theme-slug').value = config.slug || '';
                    document.getElementById('description').value = config.description || '';
                    document.getElementById('author').value = config.author || '';

                    // Poblar colores
                    document.getElementById('color-primary').value = config.colors.primary || '#000000';
                    document.getElementById('color-secondary').value = config.colors.secondary || '#d4af37';
                    document.getElementById('color-accent').value = config.colors.accent || '#4facfe';
                    document.getElementById('color-text').value = config.colors.text || '#1a1a1a';
                    document.getElementById('color-background').value = config.colors.background || '#ffffff';

                    // Sincronizar text inputs de colores
                    document.querySelector('#color-primary').parentElement.querySelector('input[type="text"]').value = config.colors.primary;
                    document.querySelector('#color-secondary').parentElement.querySelector('input[type="text"]').value = config.colors.secondary;
                    document.querySelector('#color-accent').parentElement.querySelector('input[type="text"]').value = config.colors.accent;
                    document.querySelector('#color-background').parentElement.querySelector('input[type="text"]').value = config.colors.background;
                    document.querySelector('#color-text').parentElement.querySelector('input[type="text"]').value = config.colors.text;

                    // Poblar tipografía
                    const fontFamily = config.typography?.font_family || 'sans-serif';
                    document.querySelector(`[name="font_family"][value="${fontFamily}"]`)?.click();

                    document.querySelector('[name="font_size"]').value = config.typography?.base_size || '16px';
                    document.querySelector('[name="line_height"]').value = config.typography?.line_height || '1.5';

                    // Poblar cards
                    const cardBorder = document.querySelector('[name="card_border"]');
                    if (cardBorder) cardBorder.checked = config.components?.cards?.border || false;

                    const cardShadow = config.components?.cards?.shadow || 'subtle';
                    document.querySelector(`[name="card_shadow"][value="${cardShadow}"]`)?.click();

                    const cardRounded = document.querySelector('[name="card_rounded"]');
                    if (cardRounded) cardRounded.checked = config.components?.cards?.rounded || false;

                    const cardHover = config.components?.cards?.hover_effect || 'glow';
                    document.querySelector(`[name="card_hover"][value="${cardHover}"]`)?.click();

                    // Poblar card buttons
                    const cardButtons = config.components?.cards?.buttons || 'show';
                    document.querySelector(`[name="card_buttons"][value="${cardButtons}"]`)?.click();

                    const cardButtonsPosition = config.components?.cards?.buttons_position || 'center';
                    document.querySelector(`[name="card_buttons_position"][value="${cardButtonsPosition}"]`)?.click();

                    const cardButtonsSpacing = config.components?.cards?.buttons_spacing || 'normal';
                    document.querySelector(`[name="card_buttons_spacing"][value="${cardButtonsSpacing}"]`)?.click();

                    // Poblar buttons
                    const buttonStyle = config.components?.buttons?.style || 'solid';
                    document.querySelector(`[name="button_style"][value="${buttonStyle}"]`)?.click();

                    const buttonRounded = document.querySelector('[name="button_rounded"]');
                    if (buttonRounded) buttonRounded.checked = config.components?.buttons?.rounded || false;

                    const buttonShadow = document.querySelector('[name="button_shadow"]');
                    if (buttonShadow) buttonShadow.checked = config.components?.buttons?.shadow || false;

                    // Scroll al inicio del formulario
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    showModal({
                        title: 'Error',
                        message: data.message,
                        icon: '⚠️',
                        confirmType: 'danger'
                    });
                }
            })
            .catch(error => {
                btn.textContent = originalText;
                btn.disabled = false;

                showModal({
                    title: 'Error de Red',
                    message: 'Error al conectar con el servidor: ' + error,
                    icon: '⚠️',
                    confirmType: 'danger'
                });
            });
        }

        // Toggle opciones de botones en cards
        document.addEventListener('DOMContentLoaded', function() {
            const cardButtonsRadios = document.querySelectorAll('[name="card_buttons"]');
            const positionGroup = document.getElementById('card-buttons-position-group');
            const spacingGroup = document.getElementById('card-buttons-spacing-group');

            function toggleCardButtonsOptions() {
                const selected = document.querySelector('[name="card_buttons"]:checked')?.value;

                if (selected === 'hide') {
                    positionGroup.style.display = 'none';
                    spacingGroup.style.display = 'none';
                } else {
                    positionGroup.style.display = 'block';
                    spacingGroup.style.display = 'block';
                }
            }

            cardButtonsRadios.forEach(radio => {
                radio.addEventListener('change', toggleCardButtonsOptions);
            });

            // Ejecutar al cargar
            toggleCardButtonsOptions();
        });

        // Exportar funciones para event delegation
        window.generateSlug = generateSlug;
        window.toggleCreationMethod = toggleCreationMethod;
        window.mapPalette = mapPalette;
        window.selectPopularPalette = selectPopularPalette;
        window.loadThemeForEdit = loadThemeForEdit;
        window.showPreview = showPreview;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
