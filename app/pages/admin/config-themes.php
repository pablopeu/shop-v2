<?php
/**
 * Admin - Themes Configuration
 * Selector de themes visuales para el sitio
 */


require_once APP_PATH . '/includes/theme-loader.php';
require_once APP_PATH . '/includes/theme-generator.php';

require_admin();

$message = '';
$error = '';

// Check for success message from redirect
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Archivar theme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_theme'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $slug = sanitize_input($_POST['theme_slug'] ?? '');
        $result = archive_theme($slug);

        if ($result['success']) {
            log_admin_action('theme_archived', $_SESSION['username'], ['slug' => $slug]);
            $_SESSION['success_message'] = $result['message'];
            redirect(url('/admin/?page=config-themes'));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Desarchivar theme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_theme'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $slug = sanitize_input($_POST['theme_slug'] ?? '');
        $result = unarchive_theme($slug);

        if ($result['success']) {
            log_admin_action('theme_unarchived', $_SESSION['username'], ['slug' => $slug]);
            $_SESSION['success_message'] = $result['message'];
            redirect(url('/admin/?page=config-themes'));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Borrar theme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_theme'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $slug = sanitize_input($_POST['theme_slug'] ?? '');
        $result = delete_theme($slug);

        if ($result['success']) {
            log_admin_action('theme_deleted', $_SESSION['username'], ['slug' => $slug]);
            $_SESSION['success_message'] = $result['message'];
            redirect(url('/admin/?page=config-themes'));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Update theme configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = APP_PATH . '/config/theme.json';
        $config = read_json($config_file);

        $selected_theme = sanitize_input($_POST['active_theme'] ?? 'minimal');

        // Validar que el theme existe
        $validation = validate_theme($selected_theme);

        if ($validation['valid']) {
            $config['active_theme'] = $selected_theme;

            if (write_json($config_file, $config)) {
                log_admin_action('theme_changed', $_SESSION['username'], ['theme' => $selected_theme]);
                // Redirect to regenerate page with new CSRF token
                $_SESSION['success_message'] = 'Theme cambiado exitosamente a: ' . ucfirst($selected_theme);
                redirect(url('/admin/?page=config-themes'));
                exit;
            } else {
                $error = 'Error al guardar la configuración';
            }
        } else {
            $error = 'Theme inválido o incompleto. Archivos faltantes: ' . implode(', ', $validation['missing_files']);
        }
    }
}

// Get current configuration
$theme_config = read_json(APP_PATH . '/config/theme.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Configuración de Themes';
$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Get all available themes
$available_themes = get_available_themes();

$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Themes - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 260px;
            padding: 30px;
            max-width: 1200px;
        }

        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .theme-card {
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .theme-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .theme-card.active {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }

        .theme-preview {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            text-transform: uppercase;
            position: relative;
        }

        .theme-preview::after {
            content: 'Preview';
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 12px;
            background: rgba(0,0,0,0.3);
            padding: 4px 8px;
            border-radius: 4px;
        }

        .theme-card.active .theme-preview::before {
            content: '✓ Activo';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
        }

        .theme-info {
            padding: 20px;
        }

        .theme-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
            text-transform: capitalize;
        }

        .theme-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .theme-colors {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .color-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #e0e0e0;
        }

        .theme-meta {
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }

        .select-button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }

        .select-button:hover {
            background: #5568d3;
        }

        .select-button.active {
            background: #28a745;
            cursor: default;
        }

        .select-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .preview-button {
            width: 100%;
            padding: 12px;
            background: #f0f0f0;
            color: #333;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
        }

        .preview-button:hover {
            background: #e0e0e0;
            border-color: #667eea;
            color: #667eea;
        }

        .action-button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .archive-button {
            background: #ffc107;
            color: #000;
        }

        .archive-button:hover {
            background: #e0a800;
        }

        .unarchive-button {
            background: #17a2b8;
            color: white;
        }

        .unarchive-button:hover {
            background: #138496;
        }

        .delete-button {
            background: #dc3545;
            color: white;
        }

        .delete-button:hover {
            background: #c82333;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .filter-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .archived-theme {
            opacity: 0.7;
        }

        .archived-theme .theme-info {
            position: relative;
        }

        .archived-theme .theme-info::before {
            content: '🗃️ ARCHIVADO';
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        /* Theme-specific preview colors */
        .theme-card[data-theme="minimal"] .theme-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .theme-card[data-theme="elegant"] .theme-preview {
            background: linear-gradient(135deg, #000000 0%, #d4af37 100%);
        }

        .theme-card[data-theme="fresh"] .theme-preview {
            background: linear-gradient(135deg, #4caf50 0%, #ff9800 100%);
        }

        .theme-card[data-theme="bold"] .theme-preview {
            background: linear-gradient(135deg, #e74c3c 0%, #000000 100%);
        }

        .theme-card[data-theme="luxury"] .theme-preview {
            background: linear-gradient(135deg, #FAF7F2 0%, #C9A961 100%);
        }

        .theme-card[data-theme="vibrant"] .theme-preview {
            background: linear-gradient(135deg, #FF006E 0%, #FFBE0B 50%, #3A86FF 100%);
        }

        .theme-card[data-theme="dark"] .theme-preview {
            background: linear-gradient(135deg, #0A0A0F 0%, #00F0FF 50%, #B24DFF 100%);
        }

        .theme-card[data-theme="classic"] .theme-preview {
            background: linear-gradient(135deg, #000000 0%, #d4af37 100%);
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #1976d2;
        }

        .info-box p {
            color: #555;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>🎨 Themes Disponibles</h2>

            <div class="info-box">
                <h3>ℹ️ Información</h3>
                <p>
                    Los themes cambian el aspecto visual del sitio sin modificar su funcionalidad.
                    Cada theme tiene su propia paleta de colores, tipografía y estilo.
                    Los cambios se aplican inmediatamente en todo el sitio.
                </p>
            </div>

            <!-- Filtros de Themes -->
            <div style="margin: 20px 0; display: flex; gap: 10px;">
                <button type="button" class="filter-tab active" data-action="filterThemes" data-filter="active">
                    📁 Activos (<?php echo count(array_filter($available_themes, function($t) { return !($t['archived'] ?? false); })); ?>)
                </button>
                <button type="button" class="filter-tab" data-action="filterThemes" data-filter="archived">
                    🗃️ Archivados (<?php echo count(array_filter($available_themes, function($t) { return ($t['archived'] ?? false); })); ?>)
                </button>
                <button type="button" class="filter-tab" data-action="filterThemes" data-filter="all">
                    📂 Todos (<?php echo count($available_themes); ?>)
                </button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="themes-grid">
                    <?php foreach ($available_themes as $theme_slug => $theme_info): ?>
                        <?php
                        $is_archived = $theme_info['archived'] ?? false;
                        $archive_class = $is_archived ? 'archived-theme' : 'active-theme';
                        ?>
                        <div class="theme-card <?php echo $active_theme === $theme_slug ? 'active' : ''; ?> <?php echo $archive_class; ?>"
                             data-theme="<?php echo htmlspecialchars($theme_slug); ?>"
                             data-archived="<?php echo $is_archived ? 'true' : 'false'; ?>"
                             style="<?php echo $is_archived ? 'display: none;' : ''; ?>">

                            <div class="theme-preview">
                                <?php echo strtoupper(substr($theme_slug, 0, 1)); ?>
                            </div>

                            <div class="theme-info">
                                <div class="theme-name">
                                    <?php echo htmlspecialchars($theme_info['name'] ?? ucfirst($theme_slug)); ?>
                                </div>

                                <div class="theme-description">
                                    <?php echo htmlspecialchars($theme_info['description'] ?? 'Sin descripción'); ?>
                                </div>

                                <div class="theme-colors">
                                    <div class="color-circle" style="background: <?php echo htmlspecialchars($theme_info['colors']['primary'] ?? $theme_info['primary_color'] ?? '#667eea'); ?>"></div>
                                    <div class="color-circle" style="background: <?php echo htmlspecialchars($theme_info['colors']['secondary'] ?? $theme_info['secondary_color'] ?? '#764ba2'); ?>"></div>
                                </div>

                                <div class="theme-meta">
                                    Autor: <?php echo htmlspecialchars($theme_info['author'] ?? 'Claude Code'); ?><br>
                                    Versión: <?php echo htmlspecialchars($theme_info['version'] ?? '1.0.0'); ?>
                                </div>

                                <!-- Preview Button -->
                                <button type="button" class="preview-button"
                                        data-action="previewTheme"
                                        data-url="<?php echo url('/preview?theme=' . urlencode($theme_slug)); ?>">
                                    👁️ Vista Previa
                                </button>

                                <?php if ($active_theme === $theme_slug): ?>
                                    <button type="button" class="select-button active" disabled>
                                        ✓ Theme Activo
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="save_theme" class="select-button"
                                            data-action="selectTheme"
                                            data-theme="<?php echo htmlspecialchars($theme_slug); ?>">
                                        Activar Theme
                                    </button>
                                <?php endif; ?>

                                <!-- Botones de Gestión -->
                                <div class="theme-actions" style="margin-top: 10px; display: flex; gap: 8px;">
                                    <?php
                                    $is_archived = $theme_info['archived'] ?? false;
                                    $protected_themes = ['minimal', 'classic', 'elegant', 'bold'];
                                    $is_protected = in_array($theme_slug, $protected_themes);
                                    ?>

                                    <?php if ($is_archived): ?>
                                        <button type="button" class="action-button unarchive-button"
                                                data-action="unarchiveTheme"
                                                data-slug="<?php echo htmlspecialchars($theme_slug); ?>">
                                            📂 Desarchivar
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="action-button archive-button"
                                                data-action="archiveTheme"
                                                data-slug="<?php echo htmlspecialchars($theme_slug); ?>">
                                            📁 Archivar
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!$is_protected && $active_theme !== $theme_slug): ?>
                                        <button type="button" class="action-button delete-button"
                                                data-action="deleteTheme"
                                                data-slug="<?php echo htmlspecialchars($theme_slug); ?>"
                                                data-name="<?php echo htmlspecialchars($theme_info['name'] ?? $theme_slug); ?>">
                                            🗑️ Eliminar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="active_theme" value="">
            </form>
        </div>

        <div class="section">
            <h2>📊 Theme Actual</h2>
            <p><strong>Theme Activo:</strong> <?php echo htmlspecialchars(ucfirst($active_theme)); ?></p>
            <p><strong>Archivo de configuración:</strong> /config/theme.json</p>
            <p><strong>Archivos CSS cargados:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                <li>/themes/_base/reset.css</li>
                <li>/themes/_base/layout.css</li>
                <li>/themes/_base/components.css</li>
                <li>/themes/_base/utilities.css</li>
                <li>/themes/_base/pages.css</li>
                <li>/themes/<?php echo htmlspecialchars($active_theme); ?>/variables.css</li>
                <li>/themes/<?php echo htmlspecialchars($active_theme); ?>/theme.css</li>
            </ul>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        // Preview on hover
        document.querySelectorAll('.theme-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                }
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });

        /**
         * Preview theme in new window
         */
        function previewTheme(event, element, params) {
            const url = params?.url;
            if (url) {
                window.open(url, 'theme_preview');
            }
        }

        /**
         * Select theme for activation
         */
        function selectTheme(event, element, params) {
            const theme = params?.theme;
            if (theme) {
                const input = document.querySelector('input[name=active_theme]');
                if (input) {
                    input.value = theme;
                }
            }
        }

        // Archivar theme
        function archiveTheme(event, element, params) {
            const slug = params?.slug;

            if (!slug) {
                console.error('No slug provided');
                return;
            }

            showModal({
                title: 'Confirmar Archivado',
                message: '¿Archivar este theme? Podrás desarchivarlo después.',
                icon: '📁',
                confirmType: 'warning',
                onConfirm: function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="archive_theme" value="1">
                        <input type="hidden" name="theme_slug" value="${slug}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Desarchivar theme
        function unarchiveTheme(event, element, params) {
            const slug = params?.slug;

            if (!slug) {
                console.error('No slug provided');
                return;
            }

            showModal({
                title: 'Confirmar Desarchivado',
                message: '¿Desarchivar este theme?',
                icon: '📂',
                confirmType: 'primary',
                onConfirm: function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="unarchive_theme" value="1">
                        <input type="hidden" name="theme_slug" value="${slug}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Filtrar themes
        function filterThemes(event, element, params) {
            const filter = params?.filter || 'active';
            const allCards = document.querySelectorAll('.theme-card');
            const allTabs = document.querySelectorAll('.filter-tab');

            // Actualizar tabs activos
            allTabs.forEach(tab => tab.classList.remove('active'));
            element.classList.add('active');

            // Filtrar cards
            allCards.forEach(card => {
                const isArchived = card.dataset.archived === 'true';

                if (filter === 'all') {
                    card.style.display = '';
                } else if (filter === 'active') {
                    card.style.display = isArchived ? 'none' : '';
                } else if (filter === 'archived') {
                    card.style.display = isArchived ? '' : 'none';
                }
            });
        }

        // Eliminar theme
        function deleteTheme(event, element, params) {
            const slug = params?.slug;
            const name = params?.name || slug;

            if (!slug) {
                console.error('No slug provided');
                return;
            }

            showModal({
                title: 'Confirmar Eliminación',
                message: `¿Eliminar permanentemente el theme "${name}"? Esta acción no se puede deshacer.`,
                icon: '🗑️',
                confirmType: 'danger',
                onConfirm: function() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="delete_theme" value="1">
                        <input type="hidden" name="theme_slug" value="${slug}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================
        window.previewTheme = previewTheme;
        window.selectTheme = selectTheme;
        window.archiveTheme = archiveTheme;
        window.unarchiveTheme = unarchiveTheme;
        window.deleteTheme = deleteTheme;
        window.filterThemes = filterThemes;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
