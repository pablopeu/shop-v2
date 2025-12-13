<?php
/**
 * Admin - Site Information Configuration
 */


require_admin();

$message = '';
$error = '';
$logo_uploaded = false; // Track if logo was just uploaded

// Update site config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = APP_PATH . '/config/site.json';
        $config = read_json($config_file);

        $config['site_name'] = sanitize_input($_POST['site_name'] ?? '');
        $config['site_description'] = sanitize_input($_POST['site_description'] ?? '');
        $config['site_keywords'] = sanitize_input($_POST['site_keywords'] ?? '');
        $config['site_owner'] = sanitize_input($_POST['site_owner'] ?? '');
        $config['contact_email'] = sanitize_input($_POST['contact_email'] ?? '');
        $config['contact_phone'] = sanitize_input($_POST['contact_phone'] ?? '');
        $config['footer_text'] = sanitize_input($_POST['footer_text'] ?? '');

        // WhatsApp configuration (new structure)
        $config['whatsapp'] = [
            'enabled' => isset($_POST['whatsapp_enabled']),
            'number' => sanitize_input($_POST['whatsapp_number'] ?? ''),
            'message' => sanitize_input($_POST['whatsapp_message'] ?? 'Hola! Me interesa un producto de su tienda'),
            'custom_link' => sanitize_input($_POST['whatsapp_custom_link'] ?? ''),
            'display_text' => sanitize_input($_POST['whatsapp_display_text'] ?? '')
        ];

        // Keep old whatsapp_number for backward compatibility
        $config['whatsapp_number'] = $config['whatsapp']['number'];

        // Shared wishlist configuration
        $shared_wishlist_expiry_days = intval($_POST['shared_wishlist_expiry_days'] ?? 30);
        if ($shared_wishlist_expiry_days < 1) {
            $shared_wishlist_expiry_days = 30; // Default to 30 days if invalid
        }
        $config['shared_wishlist_expiry_days'] = $shared_wishlist_expiry_days;

        // Meta tags configuration
        $config['meta_tags'] = [
            'og_title' => sanitize_input($_POST['og_title'] ?? ''),
            'og_type' => sanitize_input($_POST['og_type'] ?? 'website'),
            'og_url' => sanitize_input($_POST['og_url'] ?? ''),
            'og_url_secure' => sanitize_input($_POST['og_url_secure'] ?? ''),
            'og_image' => sanitize_input($_POST['og_image'] ?? ''),
            'og_site_name' => sanitize_input($_POST['og_site_name'] ?? ''),
            'og_description' => sanitize_input($_POST['og_description'] ?? ''),
            'content_type' => sanitize_input($_POST['content_type'] ?? 'text/html; charset=utf-8'),
            'og_image_width' => sanitize_input($_POST['og_image_width'] ?? '1280'),
            'og_image_height' => sanitize_input($_POST['og_image_height'] ?? '960'),
            'twitter_card' => sanitize_input($_POST['twitter_card'] ?? 'summary_large_image')
        ];

        // Handle OG image upload
        if (isset($_FILES['og_image_file']) && $_FILES['og_image_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = PUBLIC_PATH . '/assets/uploads/og-images/';

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['og_image_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'og_image_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['og_image_file']['tmp_name'], $upload_path)) {
                    // Delete old OG image if exists
                    if (!empty($config['meta_tags']['og_image']) && strpos($config['meta_tags']['og_image'], '/assets/uploads/og-images/') !== false) {
                        $old_og_path = PUBLIC_PATH . parse_url($config['meta_tags']['og_image'], PHP_URL_PATH);
                        if (file_exists($old_og_path)) {
                            unlink($old_og_path);
                        }
                    }

                    $config['meta_tags']['og_image'] = url('/assets/uploads/og-images/' . $new_filename);
                } else {
                    $error = 'Error al subir la imagen Open Graph. Verifique los permisos del directorio.';
                }
            } else {
                $error = 'Formato de archivo no permitido para OG Image. Use JPG, PNG o WebP';
            }
        }

        // Handle logo upload
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = PUBLIC_PATH . '/assets/uploads/logos/';

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'logo_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_path)) {
                    // Delete old logo if exists
                    if (!empty($config['logo']['path'])) {
                        $old_logo_path = PUBLIC_PATH . $config['logo']['path'];
                        if (file_exists($old_logo_path)) {
                            unlink($old_logo_path);
                        }
                    }

                    $config['logo']['path'] = '/assets/uploads/logos/' . $new_filename;
                    $config['logo']['enabled'] = true;
                    $logo_uploaded = true;
                    $message = 'Logo subido exitosamente';
                } else {
                    $error = 'Error al subir el logo. Verifique los permisos del directorio.';
                }
            } else {
                $error = 'Formato de archivo no permitido. Use JPG, PNG, GIF, SVG o WebP';
            }
        }

        // Update logo settings (but keep enabled=true if just uploaded)
        if (!$logo_uploaded) {
            $config['logo']['enabled'] = isset($_POST['logo_enabled']);
        }
        $config['logo']['alt'] = sanitize_input($_POST['logo_alt'] ?? 'Logo');

        if (write_json($config_file, $config)) {
            if (empty($message)) {
                $message = 'Configuración guardada exitosamente';
            }
            log_admin_action('site_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Delete logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logo'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = APP_PATH . '/config/site.json';
        $config = read_json($config_file);

        if (!empty($config['logo']['path']) && file_exists(__DIR__ . '/..' . $config['logo']['path'])) {
            unlink(__DIR__ . '/..' . $config['logo']['path']);
        }

        $config['logo']['path'] = '';
        $config['logo']['enabled'] = false;

        if (write_json($config_file, $config)) {
            $message = 'Logo eliminado exitosamente';
            log_admin_action('logo_deleted', $_SESSION['username'], []);
        } else {
            $error = 'Error al eliminar el logo';
        }
    }
}

$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Información del Sitio';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del Sitio - Admin</title>
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
        .form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
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
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }

        .logo-preview {
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-danger {
            padding: 8px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .cards-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .compact-grid-2 { grid-template-columns: 1fr; }
            .cards-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="configForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="cards-grid">
                <!-- Tarjeta: Información Básica -->
                <div class="card">
                    <div class="card-title">
                        ℹ️ Información Básica
                    </div>
                    <div class="form-group">
                        <label for="site_name">Nombre del Sitio *</label>
                        <input type="text" id="site_name" name="site_name" required
                               value="<?php echo htmlspecialchars($site_config['site_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="site_owner">Propietario / Responsable</label>
                        <input type="text" id="site_owner" name="site_owner"
                               value="<?php echo htmlspecialchars($site_config['site_owner'] ?? ''); ?>"
                               placeholder="Ej: Juan Pérez">
                        <small style="color: #666; font-size: 11px; display: block; margin-top: 3px;">
                            Usado en comunicaciones con clientes
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="site_description">Descripción del Sitio (SEO)</label>
                        <textarea id="site_description" name="site_description"><?php echo htmlspecialchars($site_config['site_description'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Tarjeta: Logo -->
                <div class="card">
                    <div class="card-title">
                        🖼️ Logo del Sitio
                    </div>
                    <p class="card-description">Imagen recomendada: 170x85px (ratio 2:1). Formatos: JPG, PNG, GIF, SVG, WebP</p>

                    <?php if (!empty($site_config['logo']['path'])): ?>
                        <div class="logo-preview">
                            <div class="logo-info">
                                <img src="<?php echo htmlspecialchars(url($site_config['logo']['path'])); ?>"
                                     alt="Logo actual"
                                     style="max-width: 170px; max-height: 85px; border: 1px solid #ddd; border-radius: 4px;">
                                <div>
                                    <strong>Logo Actual</strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars(basename($site_config['logo']['path'])); ?></small>
                                </div>
                            </div>
                            <button type="button" data-action="confirmDeleteLogo" class="btn-danger">
                                🗑️ Eliminar
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="logo_file">Subir Nuevo Logo</label>
                        <input type="file" id="logo_file" name="logo_file" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label for="logo_alt">Texto Alternativo</label>
                        <input type="text" id="logo_alt" name="logo_alt"
                               value="<?php echo htmlspecialchars($site_config['logo']['alt'] ?? 'Logo'); ?>"
                               placeholder="Logo de Mi Tienda">
                    </div>

                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="logo_enabled" <?php echo ($site_config['logo']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span style="font-weight: normal;">Mostrar logo en el sitio</span>
                    </label>
                </div>

                <!-- Tarjeta: Contacto -->
                <div class="card">
                    <div class="card-title">
                        📧 Información de Contacto
                    </div>
                    <div class="form-group">
                        <label for="contact_email">Email de Contacto</label>
                        <input type="email" id="contact_email" name="contact_email"
                               value="<?php echo htmlspecialchars($site_config['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Teléfono de Contacto</label>
                        <input type="text" id="contact_phone" name="contact_phone"
                               value="<?php echo htmlspecialchars($site_config['contact_phone'] ?? ''); ?>"
                               placeholder="+54 9 11 1234-5678">
                    </div>
                </div>

                <!-- Tarjeta: SEO -->
                <div class="card">
                    <div class="card-title">
                        🔍 SEO
                    </div>
                    <div class="form-group">
                        <label for="site_keywords">Palabras Clave (separadas por comas)</label>
                        <input type="text" id="site_keywords" name="site_keywords"
                               value="<?php echo htmlspecialchars($site_config['site_keywords'] ?? ''); ?>"
                               placeholder="ecommerce, tienda, productos">
                    </div>
                </div>

                <!-- Tarjeta: Footer -->
                <div class="card">
                    <div class="card-title">
                        📄 Footer
                    </div>
                    <div class="form-group">
                        <label for="footer_text">Texto del Footer</label>
                        <input type="text" id="footer_text" name="footer_text"
                               value="<?php echo htmlspecialchars($site_config['footer_text'] ?? ''); ?>"
                               placeholder="© 2025 Mi Tienda. Todos los derechos reservados.">
                    </div>
                </div>

                <!-- Tarjeta: Favoritos Compartidos -->
                <div class="card">
                    <div class="card-title">
                        ❤️ Favoritos Compartidos
                    </div>
                    <p class="card-description">Configura el tiempo de vencimiento de los links cortos para compartir listas de favoritos</p>
                    <div class="form-group">
                        <label for="shared_wishlist_expiry_days">Días hasta vencimiento del link</label>
                        <input type="number" id="shared_wishlist_expiry_days" name="shared_wishlist_expiry_days"
                               value="<?php echo intval($site_config['shared_wishlist_expiry_days'] ?? 30); ?>"
                               min="1"
                               max="365"
                               placeholder="30">
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            Los links compartidos expirarán después de este número de días (1-365). Valor por defecto: 30 días
                        </small>
                    </div>
                </div>

                <!-- Tarjeta: WhatsApp (ocupa ancho completo) -->
                <div class="card card-full">
                    <div class="card-title">
                        💬 Configuración de WhatsApp
                    </div>
                    <p class="card-description">Configura el botón flotante de WhatsApp que aparecerá en tu sitio</p>

                    <label style="margin-bottom: 15px; cursor: pointer; display: block;">
                        <input type="checkbox" name="whatsapp_enabled" <?php echo ($site_config['whatsapp']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span style="font-weight: normal;">Mostrar botón de WhatsApp en el sitio</span>
                    </label>

                    <div class="compact-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label for="whatsapp_number">Número de WhatsApp</label>
                            <input type="text" id="whatsapp_number" name="whatsapp_number"
                                   value="<?php echo htmlspecialchars($site_config['whatsapp']['number'] ?? $site_config['whatsapp_number'] ?? ''); ?>"
                                   placeholder="5491112345678">
                        </div>
                        <div class="form-group">
                            <label for="whatsapp_message">Mensaje predeterminado</label>
                            <input type="text" id="whatsapp_message" name="whatsapp_message"
                                   value="<?php echo htmlspecialchars($site_config['whatsapp']['message'] ?? 'Hola! Me interesa un producto de su tienda'); ?>"
                                   placeholder="Hola! Me interesa un producto de su tienda">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="whatsapp_custom_link">Link personalizado de WhatsApp (opcional)</label>
                        <input type="text" id="whatsapp_custom_link" name="whatsapp_custom_link"
                               value="<?php echo htmlspecialchars($site_config['whatsapp']['custom_link'] ?? ''); ?>"
                               placeholder="https://api.whatsapp.com/message/XXXXX">
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            Si usas un link personalizado, este tendrá prioridad sobre el número de WhatsApp
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="whatsapp_display_text">Texto a mostrar (opcional)</label>
                        <input type="text" id="whatsapp_display_text" name="whatsapp_display_text"
                               value="<?php echo htmlspecialchars($site_config['whatsapp']['display_text'] ?? ''); ?>"
                               placeholder="WhatsApp: +54 9 11 1234-5678">
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            Texto que se mostrará en lugar del número. Si está vacío, se muestra el número directamente
                        </small>
                    </div>
                </div>

                <!-- Tarjeta: Meta Tags (ocupa ancho completo) -->
                <div class="card card-full">
                    <div class="card-title">
                        🏷️ Meta Tags para SEO y Redes Sociales
                    </div>
                    <p class="card-description">
                        Configura los meta tags Open Graph y Twitter Card para mejorar cómo se comparte tu sitio en redes sociales.
                        Los campos se precargan automáticamente con los datos del sitio, pero puedes personalizarlos.
                    </p>

                    <div class="compact-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label for="og_title">OG Title</label>
                            <input type="text" id="og_title" name="og_title"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['og_title'] ?? $site_config['site_name'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($site_config['site_name'] ?? 'Mi Tienda'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="og_type">OG Type</label>
                            <input type="text" id="og_type" name="og_type"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['og_type'] ?? 'website'); ?>"
                                   placeholder="website">
                        </div>
                    </div>

                    <div class="compact-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label for="og_url">OG URL</label>
                            <input type="text" id="og_url" name="og_url"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['og_url'] ?? (get_base_url() . url('/'))); ?>"
                                   placeholder="<?php echo htmlspecialchars(get_base_url() . url('/')); ?>">
                        </div>
                        <div class="form-group">
                            <label for="og_url_secure">OG URL Secure</label>
                            <input type="text" id="og_url_secure" name="og_url_secure"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['og_url_secure'] ?? (get_base_url() . url('/'))); ?>"
                                   placeholder="<?php echo htmlspecialchars(get_base_url() . url('/')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="og_site_name">OG Site Name</label>
                        <input type="text" id="og_site_name" name="og_site_name"
                               value="<?php echo htmlspecialchars($site_config['meta_tags']['og_site_name'] ?? $site_config['site_name'] ?? ''); ?>"
                               placeholder="<?php echo htmlspecialchars($site_config['site_name'] ?? 'Mi Tienda'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="og_description">OG Description</label>
                        <textarea id="og_description" name="og_description" rows="2"
                                  placeholder="<?php echo htmlspecialchars($site_config['site_description'] ?? ''); ?>"><?php echo htmlspecialchars($site_config['meta_tags']['og_description'] ?? $site_config['site_description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>OG Image (1280x960px recomendado)</label>
                        <?php if (!empty($site_config['meta_tags']['og_image'])): ?>
                            <div style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                <img src="<?php echo htmlspecialchars($site_config['meta_tags']['og_image']); ?>"
                                     alt="OG Image actual"
                                     style="max-width: 300px; border: 1px solid #ddd; border-radius: 4px;">
                                <br><small style="color: #666;">Imagen actual</small>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="og_image_file" name="og_image_file" accept="image/*">
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            O ingresa una URL de imagen externa:
                        </small>
                        <input type="text" id="og_image" name="og_image"
                               value="<?php echo htmlspecialchars($site_config['meta_tags']['og_image'] ?? ''); ?>"
                               placeholder="https://ejemplo.com/imagen.jpg"
                               style="margin-top: 5px;">
                    </div>

                    <div class="compact-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group">
                            <label for="og_image_width">OG Image Width</label>
                            <input type="text" id="og_image_width" name="og_image_width"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['og_image_width'] ?? '1280'); ?>"
                                   placeholder="1280">
                        </div>
                        <div class="form-group">
                            <label for="og_image_height">OG Image Height</label>
                            <input type="text" id="og_image_height" name="og_image_height"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['og_image_height'] ?? '960'); ?>"
                                   placeholder="960">
                        </div>
                    </div>

                    <div class="compact-grid-2">
                        <div class="form-group">
                            <label for="content_type">Content Type</label>
                            <input type="text" id="content_type" name="content_type"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['content_type'] ?? 'text/html; charset=utf-8'); ?>"
                                   placeholder="text/html; charset=utf-8">
                        </div>
                        <div class="form-group">
                            <label for="twitter_card">Twitter Card</label>
                            <input type="text" id="twitter_card" name="twitter_card"
                                   value="<?php echo htmlspecialchars($site_config['meta_tags']['twitter_card'] ?? 'summary_large_image'); ?>"
                                   placeholder="summary_large_image">
                        </div>
                    </div>
                </div>

                <!-- Botón de guardar centrado -->
                <div class="btn-save-container">
                    <button type="submit" name="save_config" class="btn-save" id="saveBtn">
                        💾 Guardar Configuración
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = form.querySelectorAll('input, textarea');
        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;
        let hasUnsavedChanges = false;

        // Store original values
        inputs.forEach(input => {
            originalValues[input.name] = input.value;
        });

        // Detect changes
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                checkForChanges();
            });
        });

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                if (input.value !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

            hasUnsavedChanges = hasChanges;

            if (hasChanges) {
                saveBtn.classList.add('changed');
                saveBtn.classList.remove('saved');
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        }

        // Prevent leaving page with unsaved changes - Native browser dialog
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = ''; // Required for Chrome
            }
        });

        // Intercept navigation links to show custom modal
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"])');

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (hasUnsavedChanges && !link.closest('form')) {
                        e.preventDefault();
                        const targetUrl = link.href;

                        showModal({
                            title: '⚠️ Cambios sin Guardar',
                            message: 'Tienes cambios sin guardar en la configuración del sitio.',
                            details: 'Si sales ahora, perderás todos los cambios realizados.',
                            icon: '⚠️',
                            confirmText: 'Salir sin Guardar',
                            cancelText: 'Quedarse',
                            confirmType: 'danger',
                            onConfirm: function() {
                                hasUnsavedChanges = false; // Allow navigation
                                window.location.href = targetUrl;
                            }
                        });
                    }
                });
            });
        });

        // Reset unsaved changes flag on successful save
        form.addEventListener('submit', () => {
            hasUnsavedChanges = false;
        });

        // Show saved state
        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => {
                saveBtn.classList.remove('saved');
            }, 3000);
        }
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
        /**
         * Confirmar eliminación del logo
         */
        function confirmDeleteLogo() {
            showModal({
                title: '⚠️ Eliminar Logo',
                message: '¿Estás seguro de que deseas eliminar el logo del sitio?',
                details: '🚨 <strong>ADVERTENCIA:</strong> Esta acción es irreversible. Una vez eliminado, no podrás recuperar el archivo del logo.',
                icon: '🗑️',
                iconClass: 'danger',
                confirmText: 'Sí, Eliminar Logo',
                cancelText: 'No, Conservar',
                confirmType: 'danger',
                onConfirm: function() {
                    // Create a hidden form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = '<?php echo $csrf_token; ?>';
                    form.appendChild(csrfInput);

                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_logo';
                    deleteInput.value = '1';
                    form.appendChild(deleteInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Wrapper for event delegation
        window.confirmDeleteLogo = confirmDeleteLogo;
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
