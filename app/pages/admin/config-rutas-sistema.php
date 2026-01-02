<?php
/**
 * Admin - System Paths Configuration
 * Consolidate all system paths configuration in one place
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$main_config = require APP_PATH . '/config/config.php';

// Page title for header
$page_title = '🗺️ Rutas del Sistema';

// File paths
$users_path_file = APP_PATH . '/.users_path';
$credentials_path_file = APP_PATH . '/.credentials_path';
$payment_credentials_path_file = PAYMENT_CREDENTIALS_PATH_FILE;

// Get current paths
$current_app_path = $main_config['app_path'] ?? APP_PATH;
$current_public_path = $main_config['public_path'] ?? PUBLIC_PATH;

$current_users_path = file_exists($users_path_file)
    ? trim(file_get_contents($users_path_file))
    : APP_PATH . '/data/passwords/users.json';

$current_credentials_path = file_exists($credentials_path_file)
    ? trim(file_get_contents($credentials_path_file))
    : dirname(APP_PATH) . '/notification_credentials.json';

if (file_exists($payment_credentials_path_file)) {
    $current_payment_path = trim(file_get_contents($payment_credentials_path_file));
} else {
    // Default path: try parent directory of APP_PATH first (for production setup),
    // then fall back to APP_PATH/data (for development)
    $parent_dir_path = dirname(APP_PATH) . '/payment_credentials.json';
    if (file_exists($parent_dir_path)) {
        $current_payment_path = $parent_dir_path;
    } else {
        $current_payment_path = APP_PATH . '/data/payment_credentials.json';
    }
}

// Handle messages
$message = '';
$error = '';

// Helper function to check if path is outside webroot
function is_outside_webroot($path) {
    $webroot_indicators = ['public_html', 'www', 'htdocs', 'web'];
    $path_lower = strtolower($path);

    foreach ($webroot_indicators as $indicator) {
        if (strpos($path_lower, '/' . $indicator . '/') !== false) {
            return false;
        }
    }

    return true;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save Users Location
    if (isset($_POST['save_users_location'])) {
        $new_users_path = sanitize_input($_POST['users_path'] ?? '');

        if (empty($new_users_path)) {
            $error = '❌ El path no puede estar vacío';
        } elseif (!is_outside_webroot($new_users_path)) {
            $error = '❌ El path debe estar FUERA del directorio público (public_html, www, htdocs, etc.)';
        } else {
            $old_path = $current_users_path;
            $actions_performed = [];

            // Ensure directory exists
            $dir = dirname($new_users_path);
            if (!file_exists($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $error = '❌ No se pudo crear el directorio: ' . $dir . ' (verifica permisos)';
                } else {
                    $actions_performed[] = 'Directorio creado';
                }
            }

            // Verify directory is writable
            if (empty($error) && !is_writable($dir)) {
                $error = '❌ El directorio no es escribible: ' . $dir . ' (verifica permisos)';
            }

            if (empty($error)) {
                // Copy existing users file to new location
                if (file_exists($old_path)) {
                    $users_content = file_get_contents($old_path);
                    if (file_put_contents($new_users_path, $users_content) !== false) {
                        $actions_performed[] = 'Archivo de usuarios movido';

                        // Set restrictive permissions
                        @chmod($new_users_path, 0600);
                        $actions_performed[] = 'Permisos establecidos (600)';

                        // Save path reference
                        file_put_contents($users_path_file, $new_users_path);
                        $current_users_path = $new_users_path;

                        // Optionally delete old file (only if it was in the default location)
                        if (strpos($old_path, 'data/passwords/') !== false) {
                            @unlink($old_path);
                            $actions_performed[] = 'Archivo anterior eliminado';
                        }

                        $action_details = implode(' • ', $actions_performed);
                        $message = '✅ Ubicación de usuarios guardada exitosamente en: ' . $new_users_path . '<br><small>' . $action_details . '</small>';
                        log_admin_action('users_location_updated', $_SESSION['username']);
                    } else {
                        $error = '❌ Error al escribir el archivo de usuarios (verifica permisos del directorio)';
                    }
                } else {
                    $error = '❌ No se encontró el archivo de usuarios actual en: ' . $old_path;
                }
            }
        }
    }

    // Save Notification Credentials Path
    elseif (isset($_POST['save_notification_path'])) {
        $new_path = sanitize_input($_POST['credentials_path'] ?? '');

        if (empty($new_path)) {
            $error = '❌ El path no puede estar vacío';
        } elseif (!is_outside_webroot($new_path)) {
            $error = '❌ El path debe estar FUERA del directorio público (public_html, www, htdocs, etc.)';
        } else {
            // Ensure directory exists
            $dir = dirname($new_path);
            if (!file_exists($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $error = '❌ No se pudo crear el directorio: ' . $dir . ' (verifica permisos)';
                }
            }

            // Verify directory is writable
            if (empty($error) && !is_writable($dir)) {
                $error = '❌ El directorio no es escribible: ' . $dir . ' (verifica permisos)';
            }

            if (empty($error)) {
                // Save path reference
                if (file_put_contents($credentials_path_file, $new_path) !== false) {
                    $current_credentials_path = $new_path;
                    $message = '✅ Path de credenciales de notificaciones guardado exitosamente: ' . $new_path;
                    log_admin_action('notification_credentials_path_updated', $_SESSION['username']);
                } else {
                    $error = '❌ Error al guardar el path de credenciales';
                }
            }
        }
    }

    // Save Payment Credentials Path
    elseif (isset($_POST['save_payment_path'])) {
        $new_path = sanitize_input($_POST['payment_credentials_path'] ?? '');

        if (empty($new_path)) {
            $error = '❌ El path no puede estar vacío';
        } elseif (!is_outside_webroot($new_path)) {
            $error = '❌ El path debe estar FUERA del directorio público (public_html, www, htdocs, etc.)';
        } else {
            // Ensure directory exists
            $dir = dirname($new_path);
            if (!file_exists($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $error = '❌ No se pudo crear el directorio: ' . $dir . ' (verifica permisos)';
                }
            }

            // Verify directory is writable
            if (empty($error) && !is_writable($dir)) {
                $error = '❌ El directorio no es escribible: ' . $dir . ' (verifica permisos)';
            }

            if (empty($error)) {
                // Save path reference
                if (file_put_contents($payment_credentials_path_file, $new_path) !== false) {
                    $current_payment_path = $new_path;
                    $message = '✅ Path de credenciales de pago guardado exitosamente: ' . $new_path;
                    log_admin_action('payment_credentials_path_updated', $_SESSION['username']);
                } else {
                    $error = '❌ Error al guardar el path de credenciales de pago';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutas del Sistema - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 260px;
            padding: 15px 20px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header h2 {
            font-size: 18px;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            background: #f8f9fa;
        }

        .form-group input[type="text"]:disabled,
        .form-group input[type="password"]:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #7f8c8d;
            font-size: 12px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
        }

        .danger-box {
            background: #ffe8e8;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-badge.safe {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .cards-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            input[type="text"],
            input[type="password"],
            select,
            textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="cards-grid">
            <!-- HOSTING PATHS (READ-ONLY) -->
            <div class="card">
                <div class="card-header">
                    <h2>🏠 Directorios del Hosting</h2>
                </div>

                <div class="warning-box">
                    <strong>⚠️ Solo Lectura:</strong><br>
                    Estos paths se configuran durante la instalación y NO deben modificarse. Modificarlos haría que el sistema deje de funcionar inmediatamente.
                </div>

                <form>
                    <div class="form-group">
                        <label for="app_path">
                            Directorio Raíz (Privado)
                            <?php if (is_outside_webroot($current_app_path)): ?>
                                <span class="status-badge safe">✓ SEGURO</span>
                            <?php else: ?>
                                <span class="status-badge danger">⚠ EXPUESTO</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="app_path" value="<?php echo htmlspecialchars($current_app_path); ?>" disabled>
                        <small>Ubicación del código privado (app/). Archivo de configuración: <code>config/config.php</code></small>
                    </div>

                    <div class="form-group">
                        <label for="public_path">
                            Directorio Público
                            <?php if (!is_outside_webroot($current_public_path)): ?>
                                <span class="status-badge safe">✓ CORRECTO</span>
                            <?php else: ?>
                                <span class="status-badge warning">⚠ INUSUAL</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="public_path" value="<?php echo htmlspecialchars($current_public_path); ?>" disabled>
                        <small>Ubicación del código público (public_html/). Archivo de configuración: <code>config/config.php</code></small>
                    </div>

                    <div class="info-box">
                        <strong>ℹ️ Restauración desde Backup:</strong><br>
                        Si necesitas restaurar el sitio desde un backup en otra carpeta, edita manualmente el archivo <code>app/config/config.php</code> y actualiza los valores de <code>app_path</code> y <code>public_path</code>.
                    </div>
                </form>
            </div>

            <!-- PAYMENT CREDENTIALS PATH -->
            <div class="card">
                <div class="card-header">
                    <h2>💳 Secretos de Pago</h2>
                </div>

                <div class="warning-box">
                    <strong>⚠️ Importante - Seguridad:</strong><br>
                    Este archivo contiene credenciales sensibles de Mercado Pago (access_token, public_key).<br>
                    Debe estar almacenado <strong>fuera del directorio público</strong> del sitio web.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="payment_credentials_path">
                            Path del archivo de credenciales de pago
                            <?php if (is_outside_webroot($current_payment_path)): ?>
                                <span class="status-badge safe">✓ SEGURO</span>
                            <?php else: ?>
                                <span class="status-badge danger">🚨 RIESGO</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="payment_credentials_path" name="payment_credentials_path"
                               value="<?php echo htmlspecialchars($current_payment_path); ?>"
                               placeholder="/home/payment_credentials.json" required>
                        <small>Ruta absoluta donde se guardarán las credenciales de pago (debe estar FUERA de public_html/www)</small>
                    </div>

                    <div class="info-box">
                        <strong>📝 Ejemplos de paths seguros:</strong><br><br>
                        <strong>Desarrollo local:</strong> <code>/home/payment_credentials.json</code><br>
                        <strong>Hosting cPanel:</strong> <code>/home2/username/payment_credentials.json</code><br>
                        <strong>VPS:</strong> <code>/home/usuario/payment_credentials.json</code><br><br>
                        <strong>❌ NUNCA uses:</strong> <code>/var/www/html/...</code>, <code>/public_html/...</code>, <code>/www/...</code>
                    </div>

                    <div class="info-box">
                        <strong>ℹ️ Nota:</strong> Las credenciales (access_token, public_key) se editan desde <strong>"Medios de Pago → Configuración"</strong>.
                    </div>

                    <button type="submit" name="save_payment_path" class="btn btn-primary">
                        💾 Guardar Path de Pago
                    </button>
                </form>
            </div>

            <!-- NOTIFICATION CREDENTIALS PATH -->
            <div class="card">
                <div class="card-header">
                    <h2>🔔 Secretos de Notificaciones</h2>
                </div>

                <div class="warning-box">
                    <strong>⚠️ Importante - Seguridad:</strong><br>
                    Este archivo contiene credenciales sensibles (contraseñas SMTP, tokens de Telegram).<br>
                    Debe estar almacenado <strong>fuera del directorio público</strong> del sitio web.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="credentials_path">
                            Path del archivo de credenciales de notificaciones
                            <?php if (is_outside_webroot($current_credentials_path)): ?>
                                <span class="status-badge safe">✓ SEGURO</span>
                            <?php else: ?>
                                <span class="status-badge danger">🚨 RIESGO</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="credentials_path" name="credentials_path"
                               value="<?php echo htmlspecialchars($current_credentials_path); ?>"
                               placeholder="/home/notification_credentials.json" required>
                        <small>Ruta absoluta donde se guardarán las credenciales de SMTP y Telegram (debe estar FUERA de public_html/www)</small>
                    </div>

                    <div class="info-box">
                        <strong>📝 Ejemplos de paths seguros:</strong><br><br>
                        <strong>Desarrollo local:</strong> <code>/home/notification_credentials.json</code><br>
                        <strong>Hosting cPanel:</strong> <code>/home2/username/notification_credentials.json</code><br>
                        <strong>VPS:</strong> <code>/home/usuario/credentials.json</code><br><br>
                        <strong>❌ NUNCA uses:</strong> <code>/var/www/html/...</code>, <code>/public_html/...</code>, <code>/www/...</code>
                    </div>

                    <div class="info-box">
                        <strong>ℹ️ Nota:</strong> Las credenciales (usuario SMTP, tokens Telegram) se editan desde <strong>"Email y Notificaciones → Configuración"</strong>.
                    </div>

                    <button type="submit" name="save_notification_path" class="btn btn-primary">
                        💾 Guardar Path de Notificaciones
                    </button>
                </form>
            </div>

            <!-- USERS FILE PATH -->
            <div class="card">
                <div class="card-header">
                    <h2>👥 Archivo de Usuarios</h2>
                </div>

                <div class="warning-box">
                    <strong>⚠️ Importante - Seguridad:</strong><br>
                    Los datos de usuarios administradores (credenciales, contraseñas hasheadas) deben almacenarse <strong>fuera del directorio público</strong> del sitio web para que NO sean accesibles desde internet.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="users_path">
                            Path del archivo de usuarios
                            <?php if (is_outside_webroot($current_users_path)): ?>
                                <span class="status-badge safe">✓ SEGURO</span>
                            <?php else: ?>
                                <span class="status-badge danger">🚨 RIESGO</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" id="users_path" name="users_path"
                               value="<?php echo htmlspecialchars($current_users_path); ?>"
                               placeholder="/home/admin_users.json" required>
                        <small>Ruta absoluta donde se guardarán los usuarios administradores (debe estar FUERA de public_html/www)</small>
                    </div>

                    <div class="info-box">
                        <strong>📝 Ejemplos de paths seguros:</strong><br><br>
                        <strong>Desarrollo local:</strong> <code>/home/admin_users.json</code><br>
                        <strong>Hosting cPanel:</strong> <code>/home2/username/admin_users.json</code><br>
                        <strong>VPS:</strong> <code>/home/usuario/secure/users.json</code><br><br>
                        <strong>❌ NUNCA uses:</strong> <code>/var/www/html/...</code>, <code>/public_html/...</code>, <code>/www/...</code>
                    </div>

                    <?php if (!is_outside_webroot($current_users_path)): ?>
                    <div class="danger-box">
                        <strong>⚠️ ATENCIÓN - Ubicación Actual:</strong><br>
                        <span style="color: #dc3545; font-weight: bold;">🚨 RIESGO: Este archivo está DENTRO del webroot y es potencialmente accesible desde internet.</span>
                    </div>
                    <?php endif; ?>

                    <div class="info-box">
                        <strong>🔒 Al guardar:</strong><br>
                        1. Se creará automáticamente el directorio si no existe<br>
                        2. Se copiará el archivo actual a la nueva ubicación<br>
                        3. Se establecerán permisos restrictivos (600 - solo owner puede leer)<br>
                        4. El archivo en la ubicación anterior será eliminado si está dentro de data/passwords/
                    </div>

                    <button type="submit" name="save_users_location" class="btn btn-primary">
                        💾 Guardar Ubicación de Usuarios
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>
</body>
</html>
