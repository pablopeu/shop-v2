<?php
/**
 * Shop V2 Installer
 * Instalador interactivo con énfasis en SEGURIDAD
 *
 * IMPORTANTE: Este archivo debe ser eliminado después de la instalación
 */

// Detectar ruta de app/ según entorno
if (file_exists('/home2/uv0023/shop-v2-app')) {
    // Producción
    define('INSTALLER_APP_PATH', '/home2/uv0023/shop-v2-app');
} else {
    // Desarrollo
    define('INSTALLER_APP_PATH', __DIR__ . '/../../app');
}

// Prevent re-installation (allow force reinstall with ?force=1)
if (file_exists(INSTALLER_APP_PATH . '/config/config.php') && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html><head><title>Already Installed</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⚠️ Sistema Ya Instalado</h1>
        <p>El sistema ya ha sido instalado.</p>
        <p><strong>IMPORTANTE:</strong> Elimina la carpeta /install/ por seguridad.</p>
        <hr>
        <p><a href="../">Ir al sitio</a> | <a href="../admin/">Admin</a></p>
        <hr style="margin: 30px 0;">
        <p style="color: #dc3545; font-size: 14px;">
            <strong>¿Necesitas reinstalar?</strong><br>
            <a href="?force=1" style="color: #dc3545; font-weight: bold;">⚠️ Forzar Reinstalación (esto borrará la configuración actual)</a>
        </p>
    </body></html>
    ');
}

session_start();

// Process installation
$step = $_POST['step'] ?? $_GET['step'] ?? 1;
$errors = [];
$success = false;

// Step 4: Self-delete installer
if ($step == 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'YES') {
        delete_installer();
        // Redirect to site after deletion
        header('Location: ../');
        exit;
    }
}

// Step 2: Process configuration
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = [
        'app_path' => $_POST['app_path'] ?? '',
        'public_path' => $_POST['public_path'] ?? '',
        'app_url' => $_POST['app_url'] ?? '',
        'base_path' => $_POST['base_path'] ?? '',
        'admin_username' => $_POST['admin_username'] ?? '',
        'admin_password' => $_POST['admin_password'] ?? '',
        'admin_email' => $_POST['admin_email'] ?? '',
    ];

    // Validate
    if (empty($config['app_path'])) $errors[] = 'Ruta de aplicación requerida';
    if (empty($config['public_path'])) $errors[] = 'Ruta pública requerida';
    if (empty($config['admin_username'])) $errors[] = 'Usuario admin requerido';
    if (empty($config['admin_password'])) $errors[] = 'Contraseña admin requerida';
    if (strlen($config['admin_password']) < 8) $errors[] = 'Contraseña debe tener al menos 8 caracteres';

    if (empty($errors)) {
        $success = install_system($config);
        if ($success) {
            $step = 3; // Success page
        } else {
            $errors[] = 'Error durante la instalación';
        }
    }
}

function install_system($config) {
    try {
        // Generate secret key
        $secret_key = bin2hex(random_bytes(32));

        // Create config.php
        $config_content = "<?php\n/**\n * Auto-generated Configuration\n * Generated: " . date('Y-m-d H:i:s') . "\n */\n\nreturn [\n";
        $config_content .= "    'app_name' => 'Shop E-commerce',\n";
        $config_content .= "    'app_url' => " . var_export($config['app_url'], true) . ",\n";
        $config_content .= "    'base_path' => " . var_export($config['base_path'], true) . ",\n";
        $config_content .= "    'secret_key' => " . var_export($secret_key, true) . ",\n";
        $config_content .= "    'csrf_token_expiry' => 3600,\n";
        $config_content .= "    'app_path' => " . var_export($config['app_path'], true) . ",\n";
        $config_content .= "    'public_path' => " . var_export($config['public_path'], true) . ",\n";
        $config_content .= "    'maintenance_mode' => false,\n";
        $config_content .= "    'debug' => false,\n";
        $config_content .= "    'log_errors' => true,\n";
        $config_content .= "];\n";

        file_put_contents(INSTALLER_APP_PATH . '/config/config.php', $config_content);

        // Create admin user
        $admin_data = [
            'users' => [[
                'id' => 'admin-' . uniqid(),
                'username' => $config['admin_username'],
                'password' => password_hash($config['admin_password'], PASSWORD_ARGON2ID),
                'email' => $config['admin_email'],
                'role' => 'admin',
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null
            ]]
        ];

        if (!is_dir(INSTALLER_APP_PATH . '/data')) {
            mkdir(INSTALLER_APP_PATH . '/data', 0750, true);
        }

        file_put_contents(
            INSTALLER_APP_PATH . '/data/users.json',
            json_encode($admin_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Create .htaccess protection
        create_htaccess_protection();

        // Set permissions
        chmod(INSTALLER_APP_PATH . '/config/config.php', 0640);
        chmod(INSTALLER_APP_PATH . '/data', 0750);
        chmod(INSTALLER_APP_PATH . '/data/users.json', 0640);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

function create_htaccess_protection() {
    // Protect app directory
    $app_htaccess = "# Security: Block ALL access to application code\nRequire all denied\nOptions -Indexes\n";
    file_put_contents(INSTALLER_APP_PATH . '/.htaccess', $app_htaccess);

    // Public root .htaccess
    $public_htaccess = "# Rewrite rules\nRewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^(.*)$ index.php?route=/$1 [QSA,L]\n\n# Security\nOptions -Indexes\n";
    file_put_contents(__DIR__ . '/../.htaccess', $public_htaccess);
}

function delete_installer() {
    $install_dir = __DIR__;

    // Recursive delete function
    $delete_recursive = function($dir) use (&$delete_recursive) {
        if (!is_dir($dir)) {
            return unlink($dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $delete_recursive($path) : unlink($path);
        }

        return rmdir($dir);
    };

    return $delete_recursive($install_dir);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Instalador Shop V2 - Seguridad Profesional</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            max-width: 800px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 40px;
        }
        .security-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .security-warning h3 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .security-warning ul {
            color: #856404;
            margin-left: 20px;
            line-height: 1.8;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group small {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 13px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
        }
        .success-box h2 {
            color: #155724;
            margin-bottom: 20px;
        }
        .success-box .security-checklist {
            text-align: left;
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .success-box .security-checklist h3 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .success-box .security-checklist ol {
            margin-left: 20px;
            line-height: 2;
        }
        .success-box .security-checklist strong {
            color: #dc3545;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Instalador Shop V2</h1>
            <p>Sistema con Seguridad Profesional</p>
        </div>

        <div class="content">
            <?php if ($step == 1): ?>
                <!-- Step 1: Welcome & Security Info -->
                <div class="security-warning">
                    <h3>⚠️ IMPORTANTE: Arquitectura de Seguridad</h3>
                    <ul>
                        <li><strong>TODO el código de aplicación estará FUERA de public_html</strong></li>
                        <li><strong>Solo 4 archivos serán accesibles desde internet:</strong>
                            <ul>
                                <li>public_html/index.php (frontend)</li>
                                <li>public_html/admin/index.php (admin panel)</li>
                                <li>public_html/admin/login.php (login)</li>
                                <li>public_html/webhook.php (webhooks externos)</li>
                            </ul>
                        </li>
                        <li><strong>Archivos de configuración, datos y código estarán protegidos nativamente</strong></li>
                        <li><strong>Después de instalar: ELIMINAR la carpeta /install/</strong></li>
                    </ul>
                </div>

                <form method="POST" action="?step=2">
                    <div class="form-group">
                        <label>🗂️ Ruta de la Aplicación (código privado)</label>
                        <input type="text" name="app_path" value="<?php echo dirname(dirname(__DIR__)) . '/app'; ?>" required>
                        <small>Código fuera de public_html (inaccesible desde web)</small>
                    </div>

                    <div class="form-group">
                        <label>🌐 Ruta Pública (solo archivos públicos)</label>
                        <input type="text" name="public_path" value="<?php echo dirname(__DIR__); ?>" required>
                        <small>Solo archivos necesarios para el web server</small>
                    </div>

                    <div class="form-group">
                        <label>🔗 URL del Sitio</label>
                        <input type="url" name="app_url" value="http://<?php echo $_SERVER['HTTP_HOST'] ?? 'localhost'; ?>" required>
                        <small>URL completa del sitio</small>
                    </div>

                    <div class="form-group">
                        <label>📁 Base Path (si está en subdirectorio)</label>
                        <input type="text" name="base_path" value="/shopv2" placeholder="Dejar vacío si está en raíz">
                        <small>Ej: /shopv2 si está en http://dominio.com/shopv2/</small>
                    </div>

                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;">

                    <h3 style="margin-bottom: 20px; color: #333;">👤 Usuario Administrador</h3>

                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="admin_username" value="admin" required>
                    </div>

                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="admin_password" required>
                        <small>Mínimo 8 caracteres (recomendado: 16+ con mayúsculas, números y símbolos)</small>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="admin_email" required>
                    </div>

                    <button type="submit" class="btn">🚀 Instalar Sistema Seguro</button>
                </form>

            <?php elseif ($step == 2): ?>
                <!-- Step 2: Errors -->
                <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
                <a href="?step=1" class="btn">← Volver</a>

            <?php elseif ($step == 3): ?>
                <!-- Step 3: Success -->
                <div class="success-box">
                    <h2>✅ ¡Instalación Completada!</h2>
                    <p>El sistema ha sido instalado con éxito</p>

                    <div class="security-checklist">
                        <h3>🔒 TAREAS DE SEGURIDAD CRÍTICAS</h3>
                        <ol>
                            <li><strong>Verificar permisos</strong> de archivos:
                                <ul>
                                    <li>app/config/config.php: 0640</li>
                                    <li>app/data/: 0750</li>
                                </ul>
                            </li>
                            <li><strong>Cambiar contraseña del admin</strong> después del primer login</li>
                            <li><strong>Configurar SSL/HTTPS</strong> en producción</li>
                            <li><strong>Configurar backups</strong> automáticos de /app/data/</li>
                        </ol>
                    </div>

                    <div style="background: #f8d7da; border: 2px solid #dc3545; border-radius: 8px; padding: 20px; margin: 30px 0;">
                        <h3 style="color: #721c24; margin-bottom: 15px;">🗑️ Eliminar Instalador</h3>
                        <p style="color: #721c24; margin-bottom: 15px;">
                            Por seguridad, debes eliminar la carpeta <code>/install/</code> ahora.
                        </p>
                        <form method="POST" action="?step=4" id="deleteInstallerForm">
                            <input type="hidden" name="confirm_delete" value="YES">
                            <button type="button" onclick="confirmDeleteInstaller()" style="background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%;">
                                🗑️ Eliminar Instalador y Finalizar
                            </button>
                        </form>
                        <p style="color: #721c24; font-size: 13px; margin-top: 10px;">
                            Serás redirigido al sitio después de eliminar
                        </p>
                    </div>

                    <p style="margin: 20px 0; font-size: 14px; color: #666;">
                        O accede manualmente:
                        <a href="../" style="color: #667eea; text-decoration: none; font-weight: 600;">→ Ir al Sitio</a>
                        &nbsp;|&nbsp;
                        <a href="../admin/login.php" style="color: #667eea; text-decoration: none; font-weight: 600;">→ Login Admin</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($step == 3 && $success): ?>
    <!-- Modal Reutilizable -->
    <?php
    // Path al modal dependiendo del entorno
    if (file_exists(INSTALLER_APP_PATH . '/includes/admin/modal.php')) {
        include INSTALLER_APP_PATH . '/includes/admin/modal.php';
    }
    ?>

    <script>
    function confirmDeleteInstaller() {
        showModal({
            icon: '🗑️',
            title: '¿Eliminar Instalador?',
            message: 'El instalador será eliminado permanentemente. Esta acción no se puede deshacer.',
            details: 'Serás redirigido al sitio después de completar.',
            confirmText: 'Sí, Eliminar',
            cancelText: 'Cancelar',
            confirmType: 'danger',
            onConfirm: function() {
                document.getElementById('deleteInstallerForm').submit();
            }
        });
    }
    </script>
    <?php endif; ?>
</body>
</html>
