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
$saved_theme_slug = '';

// Valores del formulario (mantener después de guardar)
$form_values = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'author' => 'Shop Team',
    'color_primary' => '#000000',
    'color_secondary' => '#d4af37',
    'color_accent' => '#4facfe',
    'color_text' => '#1a1a1a',
    'color_background' => '#ffffff',
    'card_buttons' => 'show',
    'card_buttons_position' => 'center',
    'card_buttons_spacing' => 'normal',
    'card_buttons_vertical_spacing' => 'normal',
    'button_style' => 'solid',
    'button_rounded' => false,
    'button_shadow' => false,
    'button_height' => 'normal',
    'button_width' => 'auto',
    'button_icon' => 'show',
    'button_hover' => 'lift',
    'product_gallery_layout' => 'thumbnails-bottom',
    'product_image_size' => 'medium',
    'product_thumbnail_size' => 'medium',
    'product_show_breadcrumb' => true,
    'product_show_share' => true,
    'product_show_sku' => true,
    'product_show_stock' => true,
    'product_show_nav_buttons' => true,
    'product_show_image_counter' => true,
    // Efectos visuales
    'animations' => 'smooth',
    'glassmorphism' => false,
    'gradient_effects' => false,
    'transform_3d' => false,
    // Tipografía avanzada
    'font_family_heading' => 'sans-serif',
    'heading_weight' => '600',
    // Spacing
    'base_unit' => '12px',
    'spacing_scale' => 'proportional',
    // Layout
    'container_width' => '1200px',
    'grid_gap' => '28px',
    'sidebar_width' => '300px',
    // Footer
    'footer_bg_color' => '#292c2f',
    'footer_text_color' => '#ffffff',
];

// Si viene de redirect después de guardar, cargar el theme
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_slug = sanitize_input($_GET['edit']);
    $edit_theme_path = PUBLIC_PATH . "/assets/themes/{$edit_slug}/theme.json";

    if (file_exists($edit_theme_path)) {
        $edit_config = read_json($edit_theme_path);

        // Poblar form_values con los datos del theme
        $form_values = [
            'name' => $edit_config['name'] ?? '',
            'slug' => $edit_config['slug'] ?? '',
            'description' => $edit_config['description'] ?? '',
            'author' => $edit_config['author'] ?? 'Shop Team',

            // Colores principales
            'color_primary' => $edit_config['colors']['primary'] ?? '#000000',
            'color_secondary' => $edit_config['colors']['secondary'] ?? '#d4af37',
            'color_accent' => $edit_config['colors']['accent'] ?? '#4facfe',
            'color_text' => $edit_config['colors']['text'] ?? '#1a1a1a',
            'color_background' => $edit_config['colors']['background'] ?? '#ffffff',

            // Colores de estado (NUEVO - faltaban!)
            'color_success' => $edit_config['colors']['success'] ?? '#2e7d32',
            'color_warning' => $edit_config['colors']['warning'] ?? '#f57c00',
            'color_error' => $edit_config['colors']['error'] ?? '#c62828',
            'color_info' => $edit_config['colors']['info'] ?? '#1565c0',

            // Tipografía base (NUEVO - faltaban!)
            'font_family' => $edit_config['typography']['font_family'] ?? 'sans-serif',
            'font_size' => $edit_config['typography']['base_size'] ?? '16px',
            'line_height' => $edit_config['typography']['line_height'] ?? '1.5',

            // Cards
            'card_border' => $edit_config['components']['cards']['border'] ?? false,
            'card_shadow' => $edit_config['features']['shadow_style'] ?? 'subtle',
            'card_rounded' => ($edit_config['features']['border_style'] ?? 'sharp') === 'rounded',
            'card_hover' => $edit_config['components']['cards']['hover_effect'] ?? 'glow',
            'card_buttons' => $edit_config['components']['cards']['buttons'] ?? 'show',
            'card_buttons_position' => $edit_config['components']['cards']['buttons_position'] ?? 'center',
            'card_buttons_spacing' => $edit_config['components']['cards']['buttons_spacing'] ?? 'normal',
            'card_buttons_vertical_spacing' => $edit_config['components']['cards']['buttons_vertical_spacing'] ?? 'normal',

            // Buttons
            'button_style' => $edit_config['components']['buttons']['style'] ?? 'solid',
            'button_rounded' => $edit_config['components']['buttons']['rounded'] ?? false,
            'button_shadow' => $edit_config['components']['buttons']['shadow'] ?? false,
            'button_transform' => $edit_config['components']['buttons']['transform'] ?? false,
            'button_height' => $edit_config['components']['buttons']['height'] ?? 'normal',
            'button_width' => $edit_config['components']['buttons']['width'] ?? 'auto',
            'button_icon' => $edit_config['components']['buttons']['icon'] ?? 'show',
            'button_hover' => $edit_config['components']['buttons']['hover'] ?? 'lift',

            // Vista de Producto
            'product_gallery_layout' => $edit_config['components']['product_view']['gallery_layout'] ?? 'thumbnails-bottom',
            'product_image_size' => $edit_config['components']['product_view']['image_size'] ?? 'medium',
            'product_thumbnail_size' => $edit_config['components']['product_view']['thumbnail_size'] ?? 'medium',
            'product_show_breadcrumb' => $edit_config['components']['product_view']['show_breadcrumb'] ?? true,
            'product_show_share' => $edit_config['components']['product_view']['show_share'] ?? true,
            'product_show_sku' => $edit_config['components']['product_view']['show_sku'] ?? true,
            'product_show_stock' => $edit_config['components']['product_view']['show_stock'] ?? true,
            'product_show_nav_buttons' => $edit_config['components']['product_view']['show_nav_buttons'] ?? true,
            'product_show_image_counter' => $edit_config['components']['product_view']['show_image_counter'] ?? true,

            // Efectos visuales
            'animations' => $edit_config['features']['animations'] ?? 'smooth',
            'glassmorphism' => $edit_config['features']['glassmorphism'] ?? false,
            'gradient_effects' => $edit_config['features']['gradient_effects'] ?? false,
            'transform_3d' => $edit_config['features']['transform_3d'] ?? false,

            // Tipografía avanzada
            'font_family_heading' => $edit_config['typography']['font_family_heading'] ?? 'sans-serif',
            'heading_weight' => $edit_config['typography']['heading_weight'] ?? '600',

            // Spacing
            'base_unit' => $edit_config['spacing']['base_unit'] ?? '12px',
            'spacing_scale' => $edit_config['spacing']['scale'] ?? 'proportional',

            // Layout
            'container_width' => $edit_config['layout']['container_width'] ?? '1200px',
            'grid_gap' => $edit_config['layout']['grid_gap'] ?? '28px',
            'sidebar_width' => $edit_config['layout']['sidebar_width'] ?? '300px',

            // Footer
            'footer_bg_color' => $edit_config['footer']['background_color'] ?? '#292c2f',
            'footer_text_color' => $edit_config['footer']['text_color'] ?? '#ffffff',

            // Original slug para edición
            'original_slug' => $edit_config['slug'] ?? '',
        ];

        if (isset($_GET['msg']) && $_GET['msg'] === 'saved') {
            $message = 'Theme guardado exitosamente. Puedes seguir editando o activarlo.';
        }
    }
}

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
            'card_buttons_vertical_spacing' => sanitize_input($_POST['card_buttons_vertical_spacing'] ?? 'normal'),

            // Componentes: Buttons
            'button_style' => sanitize_input($_POST['button_style'] ?? 'solid'),
            'button_rounded' => isset($_POST['button_rounded']),
            'button_shadow' => isset($_POST['button_shadow']),
            'button_height' => sanitize_input($_POST['button_height'] ?? 'normal'),
            'button_width' => sanitize_input($_POST['button_width'] ?? 'auto'),
            'button_icon' => sanitize_input($_POST['button_icon'] ?? 'show'),
            'button_hover' => sanitize_input($_POST['button_hover'] ?? 'lift'),

            // Componentes: Vista Producto
            'product_gallery_layout' => sanitize_input($_POST['product_gallery_layout'] ?? 'thumbnails-bottom'),
            'product_image_size' => sanitize_input($_POST['product_image_size'] ?? 'medium'),
            'product_thumbnail_size' => sanitize_input($_POST['product_thumbnail_size'] ?? 'medium'),
            'product_show_breadcrumb' => isset($_POST['product_show_breadcrumb']),
            'product_show_share' => isset($_POST['product_show_share']),
            'product_show_sku' => isset($_POST['product_show_sku']),
            'product_show_stock' => isset($_POST['product_show_stock']),
            'product_show_nav_buttons' => isset($_POST['product_show_nav_buttons']),
            'product_show_image_counter' => isset($_POST['product_show_image_counter']),

            // Efectos Visuales
            'animations' => sanitize_input($_POST['animations'] ?? 'smooth'),
            'glassmorphism' => isset($_POST['glassmorphism']),
            'gradient_effects' => isset($_POST['gradient_effects']),
            'transform_3d' => isset($_POST['transform_3d']),

            // Tipografía Avanzada
            'font_family_heading' => sanitize_input($_POST['font_family_heading'] ?? 'sans-serif'),
            'heading_weight' => sanitize_input($_POST['heading_weight'] ?? '600'),

            // Spacing
            'base_unit' => sanitize_input($_POST['base_unit'] ?? '12px'),
            'spacing_scale' => sanitize_input($_POST['spacing_scale'] ?? 'proportional'),

            // Layout
            'container_width' => sanitize_input($_POST['container_width'] ?? '1200px'),
            'grid_gap' => sanitize_input($_POST['grid_gap'] ?? '28px'),
            'sidebar_width' => sanitize_input($_POST['sidebar_width'] ?? '300px'),

            // Footer
            'footer_bg_color' => sanitize_input($_POST['footer_bg_color'] ?? '#292c2f'),
            'footer_text_color' => sanitize_input($_POST['footer_text_color'] ?? '#ffffff')
        ];

        // Validar (pasar original_slug si estamos editando un theme existente)
        $original_slug = sanitize_input($_POST['original_slug'] ?? '');
        $validation = validate_theme_input($data['slug'], $data['name'], [
            'primary' => $data['color_primary'],
            'secondary' => $data['color_secondary'],
            'background' => $data['color_background']
        ], $original_slug);

        if (!$validation['valid']) {
            $error = implode('<br>', $validation['errors']);
        } else {
            // Generar theme
            $result = generate_theme($data);

            if ($result['success']) {
                // Mensaje de éxito base
                $message = $result['message'];

                // Agregar warnings si existen (no bloquean el guardado)
                if (!empty($validation['warnings'])) {
                    $message .= '<br><br>⚠️ <strong>Advertencias:</strong><br>' . implode('<br>', $validation['warnings']);
                }

                $saved_theme_slug = $result['slug'];
                log_admin_action('theme_generated', $_SESSION['username'], [
                    'slug' => $result['slug'],
                    'name' => $data['name']
                ]);

                // Redirigir al modo edición del theme recién guardado
                redirect(url('/admin/?page=generador-themes&edit=' . urlencode($result['slug']) . '&msg=saved'));
            } else {
                $error = $result['message'];
            }
        }

        // Si hubo error de validación, mantener los valores ingresados
        if (!empty($error)) {
            $form_values = $data;
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

// AJAX: Activar Theme
if (isset($_GET['action']) && $_GET['action'] === 'activate_theme') {
    header('Content-Type: application/json');

    $json_input = json_decode(file_get_contents('php://input'), true);
    $slug = sanitize_input($json_input['slug'] ?? '');

    if (empty($slug)) {
        echo json_encode(['success' => false, 'message' => 'Slug no proporcionado']);
        exit;
    }

    // Verificar que el theme exista
    $theme_dir = PUBLIC_PATH . "/assets/themes/{$slug}";
    if (!is_dir($theme_dir)) {
        echo json_encode(['success' => false, 'message' => 'Theme no encontrado']);
        exit;
    }

    // Actualizar theme activo
    $theme_config = read_json(APP_PATH . '/config/theme.json');
    $theme_config['active_theme'] = $slug;

    if (write_json(APP_PATH . '/config/theme.json', $theme_config)) {
        log_admin_action('theme_activated', $_SESSION['username'], ['slug' => $slug]);
        echo json_encode(['success' => true, 'message' => "Theme '{$slug}' activado exitosamente"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al activar el theme']);
    }
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

// Cargar paletas populares desde el archivo JSON
$paletas_file = PUBLIC_PATH . '/assets/data/paletas-populares.json';
$paletas_json = '[]'; // Default vacío
$paletas_array = []; // Array para contar
if (file_exists($paletas_file)) {
    $paletas_content = file_get_contents($paletas_file);
    // Validar que sea JSON válido
    $decoded = json_decode($paletas_content, true);
    if ($decoded !== null) {
        $paletas_json = $paletas_content;
        $paletas_array = $decoded;
    }
}

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

        .btn-preview:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

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
            height: 50px;
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

        /* Tabs de navegación */
        .tabs-navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-button {
            padding: 12px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            margin-bottom: -2px;
        }

        .tab-button:hover {
            color: #333;
            background: #f5f5f5;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive tabs */
        @media (max-width: 768px) {
            .tabs-navigation {
                gap: 4px;
            }

            .tab-button {
                padding: 10px 12px;
                font-size: 13px;
            }
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
            <div class="message success">
                <?php echo $message; ?>
                <?php if (isset($_GET['edit']) && isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
                    <br><br>
                    <button data-action="activateTheme" data-slug="<?php echo htmlspecialchars($_GET['edit']); ?>" class="btn-activate-theme" style="background: #2e7d32; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                        ✅ Activar Theme Ahora
                    </button>
                    <small style="display: block; margin-top: 8px; color: #666;">
                        Puedes seguir editando este theme o activarlo para verlo en el frontend.
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
        <?php endif; ?>

        <!-- Formulario -->
            <form method="POST" action="" id="theme-generator-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="original_slug" id="original-slug" value="<?php echo htmlspecialchars($form_values['original_slug'] ?? ''); ?>">

                <!-- Método de Creación -->
                <div class="card card-full" style="margin-bottom: 20px;">
                    <div class="card-title">
                        🎨 Método de Creación
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="display: block; margin-bottom: 12px; font-weight: 500; color: #555;">Elige cómo crear tu theme:</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="creation_method" value="edit" checked data-onchange="toggleCreationMethod">
                                Modificar existente
                            </label>
                            <label>
                                <input type="radio" name="creation_method" value="new" data-onchange="toggleCreationMethod">
                                Crear nuevo
                            </label>
                        </div>
                    </div>
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

                <!-- Tabs de Navegación -->
                <div class="tabs-navigation">
                    <button type="button" class="tab-button active" data-tab="general">
                        📝 Información General
                    </button>
                    <button type="button" class="tab-button" data-tab="colors">
                        🎨 Colores y Efectos
                    </button>
                    <button type="button" class="tab-button" data-tab="typography">
                        ✍️ Tipografía y Layout
                    </button>
                    <button type="button" class="tab-button" data-tab="components">
                        🎛️ Componentes
                    </button>
                    <button type="button" class="tab-button" data-tab="product">
                        🖼️ Vista de Producto
                    </button>
                </div>

                <!-- Tab 1: Información General -->
                <div class="tab-content active" id="tab-general">
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
                               value="<?php echo htmlspecialchars($form_values['name']); ?>"
                               data-onchange="generateSlug">
                        <small class="helper-text">Nombre descriptivo del theme</small>
                    </div>

                    <div class="form-group">
                        <label for="theme-slug">Slug *</label>
                        <input type="text" name="slug" id="theme-slug" required
                               pattern="^[a-z0-9\-]+$"
                               placeholder="mi-theme-personalizado"
                               value="<?php echo htmlspecialchars($form_values['slug']); ?>">
                        <small class="helper-text">Identificador único (solo minúsculas, números y guiones). Se genera automáticamente.</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Descripción</label>
                        <textarea name="description" id="description"
                                  placeholder="Describe las características de tu theme..."><?php echo htmlspecialchars($form_values['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="author">Autor</label>
                        <input type="text" name="author" id="author" value="<?php echo htmlspecialchars($form_values['author']); ?>">
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
                </div>
                <!-- Fin Tab 1: Información General -->

                <!-- Tab 2: Colores y Efectos -->
                <div class="tab-content" id="tab-colors">
                <!-- Grid: Colores (ancho completo) -->
                <div class="cards-grid">
                    <!-- Colores -->
                    <div class="card card-full" id="colors-section">
                    <div class="card-title">
                        🎨 Colores
                    </div>

                    <!-- Selector de Paletas Populares -->
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #444;">Paletas Populares</h4>
                            <span style="font-size: 12px; color: #888;">(<?php echo count($paletas_array); ?> paletas disponibles)</span>
                        </div>
                        <div id="popular-palettes-grid" style="
                            display: grid;
                            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                            gap: 12px;
                            max-height: 300px;
                            overflow-y: auto;
                            padding: 4px;
                            background: #f9f9f9;
                            border-radius: 8px;
                            border: 1px solid #e0e0e0;
                        ">
                            <!-- Las paletas se cargan dinámicamente aquí -->
                        </div>
                    </div>

                    <div class="color-grid">
                        <div class="form-group">
                            <label>Primary *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_primary" value="<?php echo htmlspecialchars($form_values['color_primary']); ?>" id="color-primary">
                                <input type="text" value="<?php echo htmlspecialchars($form_values['color_primary']); ?>" readonly>
                            </div>
                            <small class="helper-text">Color principal del theme</small>
                        </div>

                        <div class="form-group">
                            <label>Secondary *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_secondary" value="<?php echo htmlspecialchars($form_values['color_secondary']); ?>" id="color-secondary">
                                <input type="text" value="<?php echo htmlspecialchars($form_values['color_secondary']); ?>" readonly>
                            </div>
                            <small class="helper-text">Color secundario (acentos)</small>
                        </div>

                        <div class="form-group">
                            <label>Accent</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_accent" value="<?php echo htmlspecialchars($form_values['color_accent']); ?>" id="color-accent">
                                <input type="text" value="<?php echo htmlspecialchars($form_values['color_accent']); ?>" readonly>
                            </div>
                            <small class="helper-text">Color de acento adicional</small>
                        </div>

                        <div class="form-group">
                            <label>Text *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_text" value="<?php echo htmlspecialchars($form_values['color_text']); ?>" id="color-text">
                                <input type="text" value="<?php echo htmlspecialchars($form_values['color_text']); ?>" readonly>
                            </div>
                            <small class="helper-text">Color del texto principal</small>
                        </div>

                        <div class="form-group">
                            <label>Background *</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="color_background" value="<?php echo htmlspecialchars($form_values['color_background']); ?>" id="color-background">
                                <input type="text" value="<?php echo htmlspecialchars($form_values['color_background']); ?>" readonly>
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

                <!-- Efectos Visuales -->
                <div class="card card-full" style="margin-top: 20px;">
                    <div class="card-title">
                        ✨ Efectos Visuales
                    </div>

                    <div class="form-group">
                        <label>Animaciones</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="animations" value="none" <?php echo ($form_values['animations'] ?? 'smooth') === 'none' ? 'checked' : ''; ?>>
                                Sin animaciones
                            </label>
                            <label>
                                <input type="radio" name="animations" value="smooth" <?php echo ($form_values['animations'] ?? 'smooth') === 'smooth' ? 'checked' : ''; ?>>
                                Suaves
                            </label>
                            <label>
                                <input type="radio" name="animations" value="fluid" <?php echo ($form_values['animations'] ?? 'smooth') === 'fluid' ? 'checked' : ''; ?>>
                                Fluidas
                            </label>
                        </div>
                        <small class="helper-text">Estilo de animaciones en hover y transiciones</small>
                    </div>

                    <div class="form-group">
                        <label style="display: block; margin-bottom: 8px;">Efectos Especiales</label>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="glassmorphism" <?php echo ($form_values['glassmorphism'] ?? false) ? 'checked' : ''; ?>>
                                Glassmorphism (efecto vidrio)
                            </label>
                            <label>
                                <input type="checkbox" name="gradient_effects" <?php echo ($form_values['gradient_effects'] ?? false) ? 'checked' : ''; ?>>
                                Efectos de gradiente
                            </label>
                            <label>
                                <input type="checkbox" name="transform_3d" <?php echo ($form_values['transform_3d'] ?? false) ? 'checked' : ''; ?>>
                                Transformaciones 3D
                            </label>
                        </div>
                        <small class="helper-text">Efectos visuales avanzados para componentes</small>
                    </div>
                </div>
                </div>
                </div>
                <!-- Fin Tab 2: Colores y Efectos -->

                <!-- Tab 3: Tipografía y Layout -->
                <div class="tab-content" id="tab-typography">
                <div class="cards-grid">
                    <!-- Tipografía Avanzada -->
                    <div class="card">
                        <div class="card-title">
                            ✍️ Tipografía Avanzada
                        </div>

                        <div class="form-group">
                            <label>Familia de Fuente para Headings</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="font_family_heading" value="sans-serif" <?php echo ($form_values['font_family_heading'] ?? 'sans-serif') === 'sans-serif' ? 'checked' : ''; ?>>
                                    Sans-serif
                                </label>
                                <label>
                                    <input type="radio" name="font_family_heading" value="serif" <?php echo ($form_values['font_family_heading'] ?? 'sans-serif') === 'serif' ? 'checked' : ''; ?>>
                                    Serif
                                </label>
                                <label>
                                    <input type="radio" name="font_family_heading" value="monospace" <?php echo ($form_values['font_family_heading'] ?? 'sans-serif') === 'monospace' ? 'checked' : ''; ?>>
                                    Monospace
                                </label>
                            </div>
                            <small class="helper-text">Fuente para títulos y encabezados</small>
                        </div>

                        <div class="form-group">
                            <label for="heading_weight">Peso de Headings</label>
                            <select name="heading_weight" id="heading_weight">
                                <option value="400" <?php echo ($form_values['heading_weight'] ?? '600') === '400' ? 'selected' : ''; ?>>400 (Normal)</option>
                                <option value="500" <?php echo ($form_values['heading_weight'] ?? '600') === '500' ? 'selected' : ''; ?>>500 (Medium)</option>
                                <option value="600" <?php echo ($form_values['heading_weight'] ?? '600') === '600' ? 'selected' : ''; ?>>600 (Semibold)</option>
                                <option value="700" <?php echo ($form_values['heading_weight'] ?? '600') === '700' ? 'selected' : ''; ?>>700 (Bold)</option>
                                <option value="800" <?php echo ($form_values['heading_weight'] ?? '600') === '800' ? 'selected' : ''; ?>>800 (Extra Bold)</option>
                            </select>
                            <small class="helper-text">Grosor de los títulos</small>
                        </div>
                    </div>

                    <!-- Espaciado -->
                    <div class="card">
                        <div class="card-title">
                            📐 Espaciado
                        </div>

                        <div class="form-group">
                            <label for="base_unit">Unidad Base</label>
                            <select name="base_unit" id="base_unit">
                                <option value="8px" <?php echo ($form_values['base_unit'] ?? '12px') === '8px' ? 'selected' : ''; ?>>8px (Compacto)</option>
                                <option value="12px" <?php echo ($form_values['base_unit'] ?? '12px') === '12px' ? 'selected' : ''; ?>>12px (Normal)</option>
                                <option value="16px" <?php echo ($form_values['base_unit'] ?? '12px') === '16px' ? 'selected' : ''; ?>>16px (Espacioso)</option>
                                <option value="20px" <?php echo ($form_values['base_unit'] ?? '12px') === '20px' ? 'selected' : ''; ?>>20px (Extra espacioso)</option>
                            </select>
                            <small class="helper-text">Unidad base para cálculo de espacios</small>
                        </div>

                        <div class="form-group">
                            <label>Escala de Espaciado</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="spacing_scale" value="proportional" <?php echo ($form_values['spacing_scale'] ?? 'proportional') === 'proportional' ? 'checked' : ''; ?>>
                                    Proporcional
                                </label>
                                <label>
                                    <input type="radio" name="spacing_scale" value="geometric" <?php echo ($form_values['spacing_scale'] ?? 'proportional') === 'geometric' ? 'checked' : ''; ?>>
                                    Geométrica
                                </label>
                            </div>
                            <small class="helper-text">Cómo escalan los espacios (xs, sm, md, lg, xl)</small>
                        </div>
                    </div>
                </div>

                <div class="cards-grid" style="margin-top: 20px;">
                    <!-- Layout -->
                    <div class="card">
                        <div class="card-title">
                            📏 Layout
                        </div>

                        <div class="form-group">
                            <label for="container_width">Ancho del Contenedor</label>
                            <select name="container_width" id="container_width">
                                <option value="1140px" <?php echo ($form_values['container_width'] ?? '1200px') === '1140px' ? 'selected' : ''; ?>>1140px (Estándar)</option>
                                <option value="1200px" <?php echo ($form_values['container_width'] ?? '1200px') === '1200px' ? 'selected' : ''; ?>>1200px (Normal)</option>
                                <option value="1320px" <?php echo ($form_values['container_width'] ?? '1200px') === '1320px' ? 'selected' : ''; ?>>1320px (Ancho)</option>
                                <option value="1440px" <?php echo ($form_values['container_width'] ?? '1200px') === '1440px' ? 'selected' : ''; ?>>1440px (Extra ancho)</option>
                            </select>
                            <small class="helper-text">Ancho máximo del contenido</small>
                        </div>

                        <div class="form-group">
                            <label for="grid_gap">Espacio entre Columnas</label>
                            <select name="grid_gap" id="grid_gap">
                                <option value="16px" <?php echo ($form_values['grid_gap'] ?? '28px') === '16px' ? 'selected' : ''; ?>>16px (Compacto)</option>
                                <option value="24px" <?php echo ($form_values['grid_gap'] ?? '28px') === '24px' ? 'selected' : ''; ?>>24px (Ajustado)</option>
                                <option value="28px" <?php echo ($form_values['grid_gap'] ?? '28px') === '28px' ? 'selected' : ''; ?>>28px (Normal)</option>
                                <option value="32px" <?php echo ($form_values['grid_gap'] ?? '28px') === '32px' ? 'selected' : ''; ?>>32px (Espacioso)</option>
                                <option value="40px" <?php echo ($form_values['grid_gap'] ?? '28px') === '40px' ? 'selected' : ''; ?>>40px (Muy espacioso)</option>
                            </select>
                            <small class="helper-text">Espacio entre columnas en grids</small>
                        </div>

                        <div class="form-group">
                            <label for="sidebar_width">Ancho del Sidebar</label>
                            <select name="sidebar_width" id="sidebar_width">
                                <option value="250px" <?php echo ($form_values['sidebar_width'] ?? '300px') === '250px' ? 'selected' : ''; ?>>250px (Estrecho)</option>
                                <option value="300px" <?php echo ($form_values['sidebar_width'] ?? '300px') === '300px' ? 'selected' : ''; ?>>300px (Normal)</option>
                                <option value="350px" <?php echo ($form_values['sidebar_width'] ?? '300px') === '350px' ? 'selected' : ''; ?>>350px (Ancho)</option>
                            </select>
                            <small class="helper-text">Ancho de sidebars y columnas secundarias</small>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="card">
                        <div class="card-title">
                            🦶 Footer
                        </div>

                        <div class="form-group">
                            <label>Color de Fondo</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="footer_bg_color" value="<?php echo htmlspecialchars($form_values['footer_bg_color'] ?? '#292c2f'); ?>">
                                <input type="text" name="footer_bg_color_text" value="<?php echo htmlspecialchars($form_values['footer_bg_color'] ?? '#292c2f'); ?>" readonly>
                            </div>
                            <small class="helper-text">Color de fondo del footer</small>
                        </div>

                        <div class="form-group">
                            <label>Color de Texto</label>
                            <div class="color-input-wrapper">
                                <input type="color" name="footer_text_color" value="<?php echo htmlspecialchars($form_values['footer_text_color'] ?? '#ffffff'); ?>">
                                <input type="text" name="footer_text_color_text" value="<?php echo htmlspecialchars($form_values['footer_text_color'] ?? '#ffffff'); ?>" readonly>
                            </div>
                            <small class="helper-text">Color del texto en el footer</small>
                        </div>
                    </div>
                </div>
                </div>
                <!-- Fin Tab 3 -->

                <!-- Tab 4: Componentes -->
                <div class="tab-content" id="tab-components">
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
                                <input type="radio" name="card_buttons" value="show" <?php echo ($form_values['card_buttons'] === 'show') ? 'checked' : ''; ?>>
                                Mostrar todos los botones
                            </label>
                            <label>
                                <input type="radio" name="card_buttons" value="cart_only" <?php echo ($form_values['card_buttons'] === 'cart_only') ? 'checked' : ''; ?>>
                                Solo botón de carrito
                            </label>
                            <label>
                                <input type="radio" name="card_buttons" value="hide" <?php echo ($form_values['card_buttons'] === 'hide') ? 'checked' : ''; ?>>
                                Ocultar (card clickeable)
                            </label>
                        </div>
                        <small class="helper-text">Con "Solo botón de carrito" la card será clickeable y mostrará un botón de agregar al carrito</small>
                    </div>

                    <div class="form-group" id="card-buttons-position-group">
                        <label>Posición de Botones</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_buttons_position" value="center" <?php echo ($form_values['card_buttons_position'] === 'center') ? 'checked' : ''; ?>>
                                Centrado
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_position" value="left" <?php echo ($form_values['card_buttons_position'] === 'left') ? 'checked' : ''; ?>>
                                Izquierda
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_position" value="right" <?php echo ($form_values['card_buttons_position'] === 'right') ? 'checked' : ''; ?>>
                                Derecha
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="card-buttons-spacing-group">
                        <label>Espaciado de Botones</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_buttons_spacing" value="normal" <?php echo ($form_values['card_buttons_spacing'] === 'normal') ? 'checked' : ''; ?>>
                                Normal
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_spacing" value="compact" <?php echo ($form_values['card_buttons_spacing'] === 'compact') ? 'checked' : ''; ?>>
                                Compacto
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_spacing" value="spacious" <?php echo ($form_values['card_buttons_spacing'] === 'spacious') ? 'checked' : ''; ?>>
                                Amplio
                            </label>
                        </div>
                        <small class="helper-text">Espacio horizontal entre botones (solo visible con 2+ botones)</small>
                    </div>

                    <div class="form-group" id="card-buttons-vertical-spacing-group">
                        <label>Separación Vertical</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="card_buttons_vertical_spacing" value="normal" <?php echo ($form_values['card_buttons_vertical_spacing'] ?? 'normal') === 'normal' ? 'checked' : ''; ?>>
                                Normal
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_vertical_spacing" value="compact" <?php echo ($form_values['card_buttons_vertical_spacing'] ?? 'normal') === 'compact' ? 'checked' : ''; ?>>
                                Compacto
                            </label>
                            <label>
                                <input type="radio" name="card_buttons_vertical_spacing" value="spacious" <?php echo ($form_values['card_buttons_vertical_spacing'] ?? 'normal') === 'spacious' ? 'checked' : ''; ?>>
                                Amplio
                            </label>
                        </div>
                        <small class="helper-text">Espacio vertical entre el contenido y los botones</small>
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
                                    <input type="radio" name="button_style" value="solid" <?php echo ($form_values['button_style'] ?? 'solid') === 'solid' ? 'checked' : ''; ?>>
                                    Sólido
                                </label>
                                <label>
                                    <input type="radio" name="button_style" value="outline" <?php echo ($form_values['button_style'] ?? 'solid') === 'outline' ? 'checked' : ''; ?>>
                                    Outline
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Altura del Botón</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="button_height" value="compact" <?php echo ($form_values['button_height'] ?? 'normal') === 'compact' ? 'checked' : ''; ?>>
                                    Compacto
                                </label>
                                <label>
                                    <input type="radio" name="button_height" value="normal" <?php echo ($form_values['button_height'] ?? 'normal') === 'normal' ? 'checked' : ''; ?>>
                                    Normal
                                </label>
                                <label>
                                    <input type="radio" name="button_height" value="large" <?php echo ($form_values['button_height'] ?? 'normal') === 'large' ? 'checked' : ''; ?>>
                                    Grande
                                </label>
                            </div>
                            <small class="helper-text">Controla el padding vertical de los botones</small>
                        </div>

                        <div class="form-group">
                            <label>Ancho del Botón</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="button_width" value="auto" <?php echo ($form_values['button_width'] ?? 'auto') === 'auto' ? 'checked' : ''; ?>>
                                    Automático (ajustado al contenido)
                                </label>
                                <label>
                                    <input type="radio" name="button_width" value="full" <?php echo ($form_values['button_width'] ?? 'auto') === 'full' ? 'checked' : ''; ?>>
                                    Ancho completo (100%)
                                </label>
                                <label>
                                    <input type="radio" name="button_width" value="fixed" <?php echo ($form_values['button_width'] ?? 'auto') === 'fixed' ? 'checked' : ''; ?>>
                                    Ancho fijo (200px)
                                </label>
                            </div>
                            <small class="helper-text">Define el ancho de los botones en las cards</small>
                        </div>

                        <div class="form-group">
                            <label>Iconos en Botones</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="button_icon" value="show" <?php echo ($form_values['button_icon'] ?? 'show') === 'show' ? 'checked' : ''; ?>>
                                    Mostrar iconos (🛒 ❤️)
                                </label>
                                <label>
                                    <input type="radio" name="button_icon" value="hide" <?php echo ($form_values['button_icon'] ?? 'show') === 'hide' ? 'checked' : ''; ?>>
                                    Ocultar iconos (solo texto)
                                </label>
                            </div>
                            <small class="helper-text">Muestra u oculta los emojis en los botones de las cards</small>
                        </div>

                        <div class="form-group">
                            <label>Efecto Hover</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="button_hover" value="none" <?php echo ($form_values['button_hover'] ?? 'lift') === 'none' ? 'checked' : ''; ?>>
                                    Sin efecto
                                </label>
                                <label>
                                    <input type="radio" name="button_hover" value="lift" <?php echo ($form_values['button_hover'] ?? 'lift') === 'lift' ? 'checked' : ''; ?>>
                                    Elevación (lift)
                                </label>
                                <label>
                                    <input type="radio" name="button_hover" value="glow" <?php echo ($form_values['button_hover'] ?? 'lift') === 'glow' ? 'checked' : ''; ?>>
                                    Resplandor (glow)
                                </label>
                                <label>
                                    <input type="radio" name="button_hover" value="darken" <?php echo ($form_values['button_hover'] ?? 'lift') === 'darken' ? 'checked' : ''; ?>>
                                    Oscurecer
                                </label>
                            </div>
                            <small class="helper-text">Efecto visual cuando el usuario pasa el cursor sobre el botón</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="button_rounded" <?php echo ($form_values['button_rounded'] ?? false) ? 'checked' : ''; ?>>
                                    Botones redondeados
                                </label>
                                <label>
                                    <input type="checkbox" name="button_shadow" <?php echo ($form_values['button_shadow'] ?? false) ? 'checked' : ''; ?>>
                                    Con sombra
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                <!-- Fin Tab 4: Componentes -->

                <!-- Tab 5: Vista de Producto -->
                <div class="tab-content" id="tab-product">
                <div class="cards-grid">
                    <!-- Componentes: Vista Producto -->
                    <div class="card">
                        <div class="card-title">
                            🖼️ Vista de Producto
                        </div>

                        <div class="form-group">
                            <label>Layout de Galería</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="product_gallery_layout" value="thumbnails-bottom" <?php echo ($form_values['product_gallery_layout'] ?? 'thumbnails-bottom') === 'thumbnails-bottom' ? 'checked' : ''; ?>>
                                    Miniaturas abajo
                                </label>
                                <label>
                                    <input type="radio" name="product_gallery_layout" value="thumbnails-left" <?php echo ($form_values['product_gallery_layout'] ?? 'thumbnails-bottom') === 'thumbnails-left' ? 'checked' : ''; ?>>
                                    Miniaturas a la izquierda
                                </label>
                                <label>
                                    <input type="radio" name="product_gallery_layout" value="thumbnails-right" <?php echo ($form_values['product_gallery_layout'] ?? 'thumbnails-bottom') === 'thumbnails-right' ? 'checked' : ''; ?>>
                                    Miniaturas a la derecha
                                </label>
                            </div>
                            <small class="helper-text">Posición de las miniaturas en la galería del producto</small>
                        </div>

                        <div class="form-group">
                            <label>Tamaño de Imagen Principal</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="product_image_size" value="small" <?php echo ($form_values['product_image_size'] ?? 'medium') === 'small' ? 'checked' : ''; ?>>
                                    Pequeña (60%)
                                </label>
                                <label>
                                    <input type="radio" name="product_image_size" value="medium" <?php echo ($form_values['product_image_size'] ?? 'medium') === 'medium' ? 'checked' : ''; ?>>
                                    Mediana (80%)
                                </label>
                                <label>
                                    <input type="radio" name="product_image_size" value="large" <?php echo ($form_values['product_image_size'] ?? 'medium') === 'large' ? 'checked' : ''; ?>>
                                    Grande (100%)
                                </label>
                            </div>
                            <small class="helper-text">Ancho de la galería respecto al contenedor</small>
                        </div>

                        <div class="form-group">
                            <label>Secciones Visibles</label>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="product_show_breadcrumb" <?php echo ($form_values['product_show_breadcrumb'] ?? true) ? 'checked' : ''; ?>>
                                    Mostrar breadcrumb (Inicio > Categoría > Producto)
                                </label>
                                <label>
                                    <input type="checkbox" name="product_show_share" <?php echo ($form_values['product_show_share'] ?? true) ? 'checked' : ''; ?>>
                                    Mostrar botones de compartir en redes
                                </label>
                                <label>
                                    <input type="checkbox" name="product_show_sku" <?php echo ($form_values['product_show_sku'] ?? true) ? 'checked' : ''; ?>>
                                    Mostrar SKU del producto
                                </label>
                                <label>
                                    <input type="checkbox" name="product_show_stock" <?php echo ($form_values['product_show_stock'] ?? true) ? 'checked' : ''; ?>>
                                    Mostrar disponibilidad en stock
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tamaño de Miniaturas</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" name="product_thumbnail_size" value="small" <?php echo ($form_values['product_thumbnail_size'] ?? 'medium') === 'small' ? 'checked' : ''; ?>>
                                    Pequeñas (60px)
                                </label>
                                <label>
                                    <input type="radio" name="product_thumbnail_size" value="medium" <?php echo ($form_values['product_thumbnail_size'] ?? 'medium') === 'medium' ? 'checked' : ''; ?>>
                                    Medianas (80px)
                                </label>
                                <label>
                                    <input type="radio" name="product_thumbnail_size" value="large" <?php echo ($form_values['product_thumbnail_size'] ?? 'medium') === 'large' ? 'checked' : ''; ?>>
                                    Grandes (100px)
                                </label>
                            </div>
                            <small class="helper-text">Tamaño de las miniaturas en la galería</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="product_show_nav_buttons" <?php echo ($form_values['product_show_nav_buttons'] ?? true) ? 'checked' : ''; ?>>
                                Mostrar botones de navegación (‹ ›)
                            </label>
                            <small class="helper-text">Botones para navegar entre imágenes</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label>
                                <input type="checkbox" name="product_show_image_counter" <?php echo ($form_values['product_show_image_counter'] ?? true) ? 'checked' : ''; ?>>
                                Mostrar contador de imágenes (1/5)
                            </label>
                            <small class="helper-text">Indicador de imagen actual</small>
                        </div>
                    </div>
                </div>
                </div>
                </div>
                <!-- Fin Tab 5: Vista de Producto -->

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
        // Paletas populares cargadas desde PHP
        const POPULAR_PALETTES = <?php echo $paletas_json; ?>;

        // ===== SISTEMA DE TABS =====
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;

                    // Remover active de todos los botones y contenidos
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Agregar active al botón clickeado y su contenido
                    this.classList.add('active');
                    const targetContent = document.getElementById('tab-' + targetTab);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });

            // Mostrar selector de theme si "Modificar existente" está seleccionado por defecto
            const editRadio = document.querySelector('input[name="creation_method"][value="edit"]');
            if (editRadio && editRadio.checked) {
                const editDiv = document.getElementById('edit-theme');
                if (editDiv) {
                    editDiv.style.display = 'block';
                }
            }

            // Renderizar paletas populares
            renderPalettesGrid();
        });

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
            const editDiv = document.getElementById('edit-theme');

            // Mostrar selector solo si es editar
            if (value === 'edit') {
                editDiv.style.display = 'block';
            } else {
                editDiv.style.display = 'none';
            }
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
                cardButtonsVerticalSpacing: document.querySelector('[name="card_buttons_vertical_spacing"]:checked')?.value || 'normal',

                // Buttons
                buttonStyle: document.querySelector('[name="button_style"]:checked')?.value || 'solid',
                buttonRounded: document.querySelector('[name="button_rounded"]')?.checked || false,
                buttonShadow: document.querySelector('[name="button_shadow"]')?.checked || false,
                buttonHeight: document.querySelector('[name="button_height"]:checked')?.value || 'normal',
                buttonWidth: document.querySelector('[name="button_width"]:checked')?.value || 'auto',
                buttonIcon: document.querySelector('[name="button_icon"]:checked')?.value || 'show',
                buttonHover: document.querySelector('[name="button_hover"]:checked')?.value || 'lift'
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

            // Determinar padding de botones según altura (para preview de botones standalone)
            let previewButtonPadding = '12px 28px'; // normal
            if (config.buttonHeight === 'compact') {
                previewButtonPadding = '8px 20px';
            } else if (config.buttonHeight === 'large') {
                previewButtonPadding = '16px 36px';
            }

            // Determinar ancho de botones (para preview de botones standalone)
            let previewButtonWidth = 'auto';
            if (config.buttonWidth === 'full') {
                previewButtonWidth = '100%';
            } else if (config.buttonWidth === 'fixed') {
                previewButtonWidth = '200px';
            }

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

                // Determinar spacing horizontal de botones
                let buttonsGap = '8px';
                if (config.cardButtonsSpacing === 'compact') {
                    buttonsGap = '4px';
                } else if (config.cardButtonsSpacing === 'spacious') {
                    buttonsGap = '12px';
                }

                // Determinar padding de botones según altura
                let buttonPadding = '8px 16px'; // normal
                if (config.buttonHeight === 'compact') {
                    buttonPadding = '6px 12px';
                } else if (config.buttonHeight === 'large') {
                    buttonPadding = '12px 24px';
                }

                // Determinar ancho de botones
                let buttonWidth = 'auto';
                if (config.buttonWidth === 'full') {
                    buttonWidth = '100%';
                } else if (config.buttonWidth === 'fixed') {
                    buttonWidth = '200px';
                }

                // Determinar separación vertical de botones
                let buttonsMarginTop = '10px';
                if (config.cardButtonsVerticalSpacing === 'compact') {
                    buttonsMarginTop = '5px';
                } else if (config.cardButtonsVerticalSpacing === 'spacious') {
                    buttonsMarginTop = '20px';
                }

                // Generar botones si están habilitados
                let buttonsHTML = '';
                const btnStyle = config.buttonStyle === 'solid'
                    ? `background: ${config.primary}; color: white; border: none;`
                    : `background: transparent; color: ${config.primary}; border: 2px solid ${config.primary};`;

                // Determinar texto de botones según si se muestran iconos
                const cartIcon = config.buttonIcon === 'show' ? '🛒 ' : '';
                const heartIcon = config.buttonIcon === 'show' ? '❤️' : 'Favorito';

                if (config.cardButtons === 'show') {
                    // Mostrar todos los botones
                    buttonsHTML = `
                        <div style="display: flex; gap: ${buttonsGap}; justify-content: ${buttonsAlign}; margin-top: ${buttonsMarginTop};">
                            <button style="padding: ${buttonPadding}; width: ${buttonWidth}; ${btnStyle} border-radius: ${btnBorderRadius}; font-size: 0.85em; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                ${cartIcon}Comprar
                            </button>
                            <button style="padding: ${buttonPadding}; width: ${buttonWidth}; background: transparent; color: ${config.secondary}; border: 1px solid ${config.secondary}; border-radius: ${btnBorderRadius}; font-size: 0.85em; cursor: pointer;">
                                ${heartIcon}
                            </button>
                        </div>
                    `;
                } else if (config.cardButtons === 'cart_only') {
                    // Solo mostrar botón de carrito
                    buttonsHTML = `
                        <div style="display: flex; justify-content: ${buttonsAlign}; margin-top: ${buttonsMarginTop};">
                            <button style="padding: ${buttonPadding}; width: ${buttonWidth}; ${btnStyle} border-radius: ${btnBorderRadius}; font-size: 0.85em; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                ${cartIcon}Agregar al Carrito
                            </button>
                        </div>
                    `;
                }

                // Card clickeable cuando buttons están ocultos o es cart_only
                const isClickable = config.cardButtons === 'hide' || config.cardButtons === 'cart_only';

                return `
                    <div class="preview-card" style="background: white; border: ${borderStyle}; border-radius: ${borderRadius}; box-shadow: ${boxShadow}; overflow: hidden; transition: all 0.3s; ${isClickable ? 'cursor: pointer;' : ''}">
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
                            <button style="padding: ${previewButtonPadding}; width: ${previewButtonWidth}; background: ${config.primary}; color: white; border: none; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Primary Button
                            </button>
                            <button style="padding: ${previewButtonPadding}; width: ${previewButtonWidth}; background: ${config.secondary}; color: white; border: none; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Secondary Button
                            </button>
                        ` : `
                            <button style="padding: ${previewButtonPadding}; width: ${previewButtonWidth}; background: transparent; color: ${config.primary}; border: 2px solid ${config.primary}; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
                                Primary Button
                            </button>
                            <button style="padding: ${previewButtonPadding}; width: ${previewButtonWidth}; background: transparent; color: ${config.secondary}; border: 2px solid ${config.secondary}; border-radius: ${btnBorderRadius}; font-weight: 500; cursor: pointer; box-shadow: ${btnBoxShadow};">
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
                    document.getElementById('original-slug').value = config.slug || ''; // Recordar slug original para edición
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

                    const cardButtonsVerticalSpacing = config.components?.cards?.buttons_vertical_spacing || 'normal';
                    document.querySelector(`[name="card_buttons_vertical_spacing"][value="${cardButtonsVerticalSpacing}"]`)?.click();

                    // Poblar buttons
                    const buttonStyle = config.components?.buttons?.style || 'solid';
                    document.querySelector(`[name="button_style"][value="${buttonStyle}"]`)?.click();

                    const buttonRounded = document.querySelector('[name="button_rounded"]');
                    if (buttonRounded) buttonRounded.checked = config.components?.buttons?.rounded || false;

                    const buttonShadow = document.querySelector('[name="button_shadow"]');
                    if (buttonShadow) buttonShadow.checked = config.components?.buttons?.shadow || false;

                    const buttonHeight = config.components?.buttons?.height || 'normal';
                    document.querySelector(`[name="button_height"][value="${buttonHeight}"]`)?.click();

                    const buttonWidth = config.components?.buttons?.width || 'auto';
                    document.querySelector(`[name="button_width"][value="${buttonWidth}"]`)?.click();

                    const buttonIcon = config.components?.buttons?.icon || 'show';
                    document.querySelector(`[name="button_icon"][value="${buttonIcon}"]`)?.click();

                    const buttonHover = config.components?.buttons?.hover || 'lift';
                    document.querySelector(`[name="button_hover"][value="${buttonHover}"]`)?.click();

                    // Mostrar sección de actualizar paleta
                    const editPaletteSection = document.getElementById('edit-palette-section');
                    if (editPaletteSection) {
                        editPaletteSection.style.display = 'block';

                        // Cargar paletas populares en esta sección
                        loadEditPalettes();
                    }

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
            const verticalSpacingGroup = document.getElementById('card-buttons-vertical-spacing-group');

            function toggleCardButtonsOptions() {
                const selected = document.querySelector('[name="card_buttons"]:checked')?.value;

                if (selected === 'hide') {
                    positionGroup.style.display = 'none';
                    spacingGroup.style.display = 'none';
                    verticalSpacingGroup.style.display = 'none';
                } else {
                    positionGroup.style.display = 'block';
                    spacingGroup.style.display = 'block';
                    verticalSpacingGroup.style.display = 'block';
                }
            }

            cardButtonsRadios.forEach(radio => {
                radio.addEventListener('change', toggleCardButtonsOptions);
            });

            // Ejecutar al cargar
            toggleCardButtonsOptions();

            // Si estamos en modo edición (parámetro ?edit= en URL), configurar interfaz
            const urlParams = new URLSearchParams(window.location.search);
            const editSlug = urlParams.get('edit');
            const originalSlug = document.getElementById('original-slug').value;

            if (editSlug && originalSlug) {
                // Cambiar el radio a "Editar theme existente"
                const editRadio = document.querySelector('[name="creation_method"][value="edit"]');
                if (editRadio) {
                    editRadio.checked = true;

                    // Ocultar secciones de otros métodos
                    document.getElementById('palette-import').style.display = 'none';
                    document.getElementById('edit-theme').style.display = 'block';
                }

                // Mostrar sección de actualizar paleta
                const editPaletteSection = document.getElementById('edit-palette-section');
                if (editPaletteSection) {
                    editPaletteSection.style.display = 'block';

                    // Cargar paletas populares automáticamente
                    loadEditPalettes();
                }

                // Pre-seleccionar el theme en el dropdown
                const editSelect = document.getElementById('edit-theme-select');
                if (editSelect) {
                    editSelect.value = editSlug;
                }
            }
        });

        // Función para activar theme
        function activateTheme(event, element, params) {
            const slug = params?.slug;
            if (!slug) return;

            const btn = element;
            const originalText = btn.textContent;
            btn.textContent = 'Activando...';
            btn.disabled = true;

            fetch('<?php echo url('/admin/?page=generador-themes&action=activate_theme'); ?>', {
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
                    // Actualizar el botón para indicar que ya está activo
                    btn.textContent = '✅ Theme Activo';
                    btn.style.background = '#4caf50';
                    btn.disabled = true;

                    // Mostrar mensaje temporal en lugar de modal
                    const successMsg = document.createElement('div');
                    successMsg.className = 'message success';
                    successMsg.style.marginTop = '10px';
                    successMsg.textContent = '✅ Theme activado exitosamente. Ya está visible en el frontend.';
                    btn.parentElement.appendChild(successMsg);

                    // Remover mensaje después de 5 segundos
                    setTimeout(() => {
                        successMsg.remove();
                    }, 5000);
                } else {
                    // Solo mostrar error si falla
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'message error';
                    errorMsg.style.marginTop = '10px';
                    errorMsg.textContent = '❌ ' + (data.message || 'Error al activar el theme');
                    btn.parentElement.appendChild(errorMsg);
                }
            })
            .catch(err => {
                btn.textContent = originalText;
                btn.disabled = false;
                showModal({
                    title: 'Error',
                    message: 'Error de conexión al activar el theme',
                    icon: '❌'
                });
            });
        }

        // ===== PALETAS POPULARES =====

        // Renderizar grid de paletas populares
        function renderPalettesGrid() {
            const grid = document.getElementById('popular-palettes-grid');
            if (!grid || !POPULAR_PALETTES || POPULAR_PALETTES.length === 0) return;

            grid.innerHTML = '';

            POPULAR_PALETTES.forEach((palette, index) => {
                const paletteCard = document.createElement('div');
                paletteCard.style.cssText = `
                    background: white;
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 8px;
                    cursor: pointer;
                    transition: all 0.2s;
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                `;

                // Crear mini preview con los 5 colores
                const colorsPreview = document.createElement('div');
                colorsPreview.style.cssText = `
                    display: grid;
                    grid-template-columns: repeat(5, 1fr);
                    gap: 3px;
                    height: 40px;
                    border-radius: 4px;
                    overflow: hidden;
                `;

                const colors = [
                    palette.mapped?.primary || palette.primary,
                    palette.mapped?.secondary || palette.secondary,
                    palette.mapped?.accent || palette.accent,
                    palette.mapped?.text || palette.text,
                    palette.mapped?.background || palette.background
                ];

                colors.forEach(color => {
                    const colorSquare = document.createElement('div');
                    colorSquare.style.cssText = `
                        background: ${color};
                        border: 1px solid rgba(0,0,0,0.1);
                    `;
                    colorsPreview.appendChild(colorSquare);
                });

                paletteCard.appendChild(colorsPreview);

                // Agregar nombre de la paleta si existe
                if (palette.name) {
                    const paletteName = document.createElement('div');
                    paletteName.textContent = palette.name;
                    paletteName.style.cssText = `
                        font-size: 11px;
                        color: #666;
                        text-align: center;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    `;
                    paletteCard.appendChild(paletteName);
                }

                // Hover effect
                paletteCard.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#667eea';
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 8px rgba(102, 126, 234, 0.2)';
                });

                paletteCard.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#e0e0e0';
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });

                // Click para seleccionar paleta
                paletteCard.addEventListener('click', function() {
                    selectPopularPalette(palette);
                });

                grid.appendChild(paletteCard);
            });
        }

        // Seleccionar y aplicar una paleta popular
        function selectPopularPalette(palette) {
            // Aplicar colores a los inputs (soporta formato con y sin .mapped)
            const colors = palette.mapped || palette;
            const colorMappings = {
                'color-primary': colors.primary,
                'color-secondary': colors.secondary,
                'color-accent': colors.accent,
                'color-text': colors.text,
                'color-background': colors.background
            };

            Object.entries(colorMappings).forEach(([inputId, color]) => {
                const colorInput = document.getElementById(inputId);
                const wrapper = colorInput?.closest('.color-input-wrapper');
                const textInput = wrapper?.querySelector('input[type="text"]');

                if (colorInput && textInput && color) {
                    colorInput.value = color;
                    textInput.value = color;

                    // Disparar evento change para que se detecte el cambio
                    colorInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            // Mostrar feedback visual
            showModal({
                title: '✓ Paleta Aplicada',
                message: palette.name ? `Paleta "${palette.name}" aplicada correctamente` : 'Paleta aplicada correctamente',
                icon: '🎨'
            });
        }

        // Exportar funciones para event delegation
        window.generateSlug = generateSlug;
        window.toggleCreationMethod = toggleCreationMethod;
        window.loadThemeForEdit = loadThemeForEdit;
        window.showPreview = showPreview;
        window.activateTheme = activateTheme;
        window.renderPalettesGrid = renderPalettesGrid;
        window.selectPopularPalette = selectPopularPalette;

        // ===== DETECCIÓN DE CAMBIOS EN FORMULARIO =====
        let formInitialState = null;
        let formHasChanges = false;
        let allowNavigation = false;

        // Capturar estado inicial del formulario
        function captureFormState() {
            const form = document.getElementById('theme-generator-form');
            if (!form) return null;

            const formData = new FormData(form);
            const state = {};

            // Capturar todos los campos del formulario
            for (let [key, value] of formData.entries()) {
                state[key] = value;
            }

            // Capturar también color pickers (pueden no estar en FormData si no cambiaron)
            const colorInputs = form.querySelectorAll('input[type="color"]');
            colorInputs.forEach(input => {
                state[input.id] = input.value;
            });

            // Capturar textareas
            const textareas = form.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                state[textarea.id || textarea.name] = textarea.value;
            });

            return JSON.stringify(state);
        }

        // Verificar si hay cambios en el formulario
        function checkFormChanges() {
            if (!formInitialState) return false;

            const currentState = captureFormState();
            formHasChanges = (formInitialState !== currentState);
            return formHasChanges;
        }

        // Confirmar navegación si hay cambios
        function confirmNavigation(callback) {
            if (!formHasChanges || allowNavigation) {
                callback();
                return;
            }

            showModal({
                title: 'Cambios sin guardar',
                message: '¿Deseas salir sin guardar los cambios en el theme?',
                icon: '⚠️',
                confirmType: 'danger',
                onConfirm: function() {
                    allowNavigation = true;
                    callback();
                }
            });
        }

        // Inicializar detección de cambios cuando carga la página
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('theme-generator-form');
            if (!form) return;

            // Capturar estado inicial después de un pequeño delay (para que carguen todos los campos)
            setTimeout(() => {
                formInitialState = captureFormState();
            }, 500);

            // Detectar cambios en todos los campos
            form.addEventListener('input', checkFormChanges);
            form.addEventListener('change', checkFormChanges);

            // Interceptar submit del formulario para permitir navegación
            form.addEventListener('submit', function(e) {
                // Al hacer submit, permitir que la página se recargue sin confirmación
                allowNavigation = true;
                formHasChanges = false;
            });

            // Interceptar navegación en links del sidebar
            document.querySelectorAll('.admin-sidebar a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (formHasChanges && !allowNavigation) {
                        e.preventDefault();
                        const href = this.href;
                        confirmNavigation(() => {
                            window.location.href = href;
                        });
                    }
                });
            });

            // NOTA: No usamos beforeunload porque solo puede mostrar alerts nativos del navegador
            // En su lugar, solo interceptamos clicks en links del sidebar (arriba) con el modal reutilizable
        });

        // Marcar como guardado exitosamente después de submit AJAX
        function markFormAsSaved() {
            formInitialState = captureFormState();
            formHasChanges = false;
            allowNavigation = false;
        }

        // Exportar función para que otros scripts puedan usarla
        window.markFormAsSaved = markFormAsSaved;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
