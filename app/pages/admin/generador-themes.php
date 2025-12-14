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
        }

        @media (max-width: 768px) {
            .compact-grid-2 { grid-template-columns: 1fr; }
            .cards-grid { grid-template-columns: 1fr; }
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
            padding: 8px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .radio-group label:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .radio-group input[type="radio"] {
            cursor: pointer;
            margin: 0;
        }

        .radio-group label:has(input[type="radio"]:checked) {
            border-color: #667eea;
            background: #f0f2ff;
            font-weight: 500;
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

                <!-- Botón de generación -->
                <div style="margin-top: 32px; text-align: center;">
                    <button type="submit" name="generate_theme" class="btn-generate">
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

        // Exportar función para event delegation
        window.generateSlug = generateSlug;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
