<?php
/**
 * Admin - Analytics & Tracking Configuration
 * Google Analytics, Facebook Pixel, Google Tag Manager
 */


require_admin();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_analytics'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = APP_PATH . '/config/analytics.json';

        $config = [
            'google_analytics' => [
                'enabled' => isset($_POST['ga_enabled']),
                'measurement_id' => sanitize_input($_POST['ga_measurement_id'] ?? ''),
                'tracking_code' => sanitize_input($_POST['ga_tracking_code'] ?? '')
            ],
            'facebook_pixel' => [
                'enabled' => isset($_POST['fb_enabled']),
                'pixel_id' => sanitize_input($_POST['fb_pixel_id'] ?? ''),
                'track_page_view' => isset($_POST['fb_track_page_view']),
                'track_add_to_cart' => isset($_POST['fb_track_add_to_cart']),
                'track_purchase' => isset($_POST['fb_track_purchase']),
                'track_initiate_checkout' => isset($_POST['fb_track_initiate_checkout'])
            ],
            'google_tag_manager' => [
                'enabled' => isset($_POST['gtm_enabled']),
                'container_id' => sanitize_input($_POST['gtm_container_id'] ?? '')
            ]
        ];

        if (write_json($config_file, $config)) {
            $message = '✅ Configuración de tracking guardada exitosamente';
            log_admin_action('analytics_config_updated', $_SESSION['username']);
        } else {
            $error = '❌ Error al guardar la configuración';
        }
    }
}

$analytics_config = read_json(APP_PATH . '/config/analytics.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Tracking & Analytics';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking & Analytics - Admin</title>
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
        .form-group input[type="text"] { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; font-family: 'Courier New', monospace; }
        .form-group input:focus { outline: none; border-color: #667eea; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal; padding: 10px; background: #f8f9fa; border-radius: 6px; margin-bottom: 8px; }
        .checkbox-label input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }

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
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }

        .help-text { font-size: 12px; color: #666; margin-top: 4px; line-height: 1.4; }
        .alert-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .alert-box strong { color: #0d47a1; }
        .alert-box p { color: #0d47a1; margin: 5px 0; font-size: 13px; }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .cards-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
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

        <div class="alert-box">
            <strong>📊 Tracking & Analytics</strong>
            <p>Configura Google Analytics, Facebook Pixel y Google Tag Manager para rastrear el comportamiento de tus usuarios y optimizar tus campañas de marketing.</p>
            <p><strong>⚠️ Importante:</strong> Los códigos de tracking se cargarán automáticamente en todas las páginas de tu tienda cuando estén habilitados.</p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="cards-grid">
                <!-- Tarjeta: Google Analytics 4 -->
                <div class="card">
                    <div class="card-title">
                        📈 Google Analytics 4
                    </div>
                    <p class="card-description">Rastrea visitas, eventos y conversiones con Google Analytics 4 (GA4)</p>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="ga_enabled" <?php echo ($analytics_config['google_analytics']['enabled'] ?? false) ? 'checked' : ''; ?>>
                            <span><strong>Habilitar Google Analytics 4</strong></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Measurement ID</label>
                        <input type="text" name="ga_measurement_id"
                               value="<?php echo htmlspecialchars($analytics_config['google_analytics']['measurement_id'] ?? ''); ?>"
                               placeholder="G-XXXXXXXXXX" autocomplete="off">
                        <div class="help-text">Formato: G-XXXXXXXXXX</div>
                    </div>

                    <div class="form-group">
                        <label>Tracking Code (Opcional)</label>
                        <input type="text" name="ga_tracking_code"
                               value="<?php echo htmlspecialchars($analytics_config['google_analytics']['tracking_code'] ?? ''); ?>"
                               placeholder="G-XXXXXXXXXX" autocomplete="off">
                        <div class="help-text">Copia de seguridad del código</div>
                    </div>

                    <div class="alert-box" style="background: #fff3cd; border-left-color: #ff9800;">
                        <strong>🔍 Cómo obtener tu Measurement ID:</strong>
                        <p>1. Ve a <a href="https://analytics.google.com" target="_blank">Google Analytics</a></p>
                        <p>2. Administrador → Flujos de datos → Elige tu flujo web</p>
                        <p>3. Copia el ID de medición (G-XXXXXXXXXX)</p>
                    </div>
                </div>

                <!-- Tarjeta: Facebook Pixel -->
                <div class="card">
                    <div class="card-title">
                        📘 Facebook Pixel
                    </div>
                    <p class="card-description">Rastrea conversiones y optimiza campañas publicitarias en Facebook e Instagram</p>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="fb_enabled" <?php echo ($analytics_config['facebook_pixel']['enabled'] ?? false) ? 'checked' : ''; ?>>
                            <span><strong>Habilitar Facebook Pixel</strong></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Pixel ID</label>
                        <input type="text" name="fb_pixel_id"
                               value="<?php echo htmlspecialchars($analytics_config['facebook_pixel']['pixel_id'] ?? ''); ?>"
                               placeholder="123456789012345" autocomplete="off">
                        <div class="help-text">Formato: 15 dígitos numéricos</div>
                    </div>

                    <div class="form-group">
                        <label style="margin-bottom: 10px;"><strong>Eventos a rastrear:</strong></label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="fb_track_page_view" <?php echo ($analytics_config['facebook_pixel']['track_page_view'] ?? true) ? 'checked' : ''; ?>>
                            <span>PageView - Vista de página</span>
                        </label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="fb_track_add_to_cart" <?php echo ($analytics_config['facebook_pixel']['track_add_to_cart'] ?? true) ? 'checked' : ''; ?>>
                            <span>AddToCart - Agregar al carrito</span>
                        </label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="fb_track_initiate_checkout" <?php echo ($analytics_config['facebook_pixel']['track_initiate_checkout'] ?? true) ? 'checked' : ''; ?>>
                            <span>InitiateCheckout - Iniciar checkout</span>
                        </label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="fb_track_purchase" <?php echo ($analytics_config['facebook_pixel']['track_purchase'] ?? true) ? 'checked' : ''; ?>>
                            <span>Purchase - Compra completada</span>
                        </label>
                    </div>

                    <div class="alert-box" style="background: #fff3cd; border-left-color: #ff9800;">
                        <strong>🔍 Cómo obtener tu Pixel ID:</strong>
                        <p>1. Ve a <a href="https://business.facebook.com/events_manager" target="_blank">Administrador de eventos</a></p>
                        <p>2. Selecciona tu pixel</p>
                        <p>3. Copia el ID del pixel (15 dígitos)</p>
                    </div>
                </div>

                <!-- Tarjeta: Google Tag Manager -->
                <div class="card">
                    <div class="card-title">
                        🏷️ Google Tag Manager
                    </div>
                    <p class="card-description">Gestiona todas tus etiquetas de marketing en un solo lugar</p>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="gtm_enabled" <?php echo ($analytics_config['google_tag_manager']['enabled'] ?? false) ? 'checked' : ''; ?>>
                            <span><strong>Habilitar Google Tag Manager</strong></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Container ID</label>
                        <input type="text" name="gtm_container_id"
                               value="<?php echo htmlspecialchars($analytics_config['google_tag_manager']['container_id'] ?? ''); ?>"
                               placeholder="GTM-XXXXXXX" autocomplete="off">
                        <div class="help-text">Formato: GTM-XXXXXXX</div>
                    </div>

                    <div class="alert-box" style="background: #fff3cd; border-left-color: #ff9800;">
                        <strong>🔍 Cómo obtener tu Container ID:</strong>
                        <p>1. Ve a <a href="https://tagmanager.google.com" target="_blank">Google Tag Manager</a></p>
                        <p>2. Selecciona tu contenedor</p>
                        <p>3. Copia el ID del contenedor (GTM-XXXXXXX)</p>
                    </div>
                </div>

                <!-- Botón de guardar centrado -->
                <div class="btn-save-container">
                    <button type="submit" name="save_analytics" class="btn-save">
                        💾 Guardar Configuración
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>
</body>
</html>
