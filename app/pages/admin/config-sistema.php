<?php
/**
 * Admin - System Configuration
 * Configure admin password
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');

// Page title for header
$page_title = '⚙️ Cambiar Contraseña';

// Handle messages
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = '❌ Todos los campos son requeridos';
        } elseif ($new_password !== $confirm_password) {
            $error = '❌ Las nuevas contraseñas no coinciden';
        } elseif (strlen($new_password) < 8) {
            $error = '❌ La nueva contraseña debe tener al menos 8 caracteres';
        } else {
            $result = change_admin_password($_SESSION['user_id'], $current_password, $new_password);

            if ($result['success']) {
                $message = '✅ Contraseña actualizada exitosamente';
            } else {
                $error = '❌ ' . $result['message'];
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
    <title>Cambiar Contraseña - Admin</title>
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

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            max-width: 900px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header h2 {
            font-size: 20px;
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
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="number"]:focus,
        .form-group select:focus {
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
            margin-right: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="password"],
            select,
            textarea {
                font-size: 16px !important;
            }

            .btn {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
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

        <!-- Password Change Section -->
        <div class="card">
            <div class="card-header">
                <h2>🔑 Cambiar Contraseña de Administrador</h2>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante - Seguridad:</strong><br>
                Asegúrate de usar una contraseña segura con al menos 8 caracteres. Se recomienda usar una combinación de letras mayúsculas, minúsculas, números y símbolos.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Contraseña Actual</label>
                    <input type="password" id="current_password" name="current_password"
                           placeholder="Tu contraseña actual" required autocomplete="current-password">
                    <small>Ingresa tu contraseña actual para confirmar tu identidad</small>
                </div>

                <div class="form-group">
                    <label for="new_password">Nueva Contraseña</label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                    <small>Debe tener al menos 8 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Nueva Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repite la nueva contraseña" required autocomplete="new-password">
                    <small>Vuelve a ingresar la nueva contraseña para confirmar</small>
                </div>

                <div class="info-box">
                    <strong>🔒 Al cambiar la contraseña:</strong><br>
                    1. Deberás usar la nueva contraseña en tu próximo inicio de sesión<br>
                    2. Se registrará un log de este cambio por seguridad<br>
                    3. Guarda tu nueva contraseña en un lugar seguro
                </div>

                <button type="submit" name="change_password" class="btn btn-primary">
                    🔐 Cambiar Contraseña
                </button>
            </form>
        </div>

        <div class="info-box">
            <strong>💡 Otras Configuraciones del Sistema:</strong><br>
            Para configurar las rutas de archivos sensibles (usuarios, credenciales de pago, etc.),
            visita <a href="<?php echo url('/admin/?page=config-rutas-sistema'); ?>" style="color: #007bff; text-decoration: underline;">
                Configuración → Rutas del Sistema
            </a>
        </div>
    </div>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>
</body>
</html>
