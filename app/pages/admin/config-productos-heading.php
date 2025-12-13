<?php
/**
 * Admin - Products Heading Configuration
 */


require_admin();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = APP_PATH . '/config/products-heading.json';

        $config = [
            'enabled' => isset($_POST['enabled']),
            'heading' => sanitize_input($_POST['heading'] ?? ''),
            'subheading' => sanitize_input($_POST['subheading'] ?? '')
        ];

        if (write_json($config_file, $config)) {
            $message = 'Configuración guardada exitosamente';
            log_admin_action('products_heading_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

$config = read_json(APP_PATH . '/config/products-heading.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Encabezado de Productos';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encabezado de Productos - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 900px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { min-height: 60px; resize: vertical; font-family: inherit; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
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

        <div class="card">
            <form method="POST" action="" id="configForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled"
                               <?php echo ($config['enabled'] ?? true) ? 'checked' : ''; ?>>
                        <label for="enabled">Mostrar encabezado de sección de productos</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="heading">Título Principal</label>
                    <input type="text" id="heading" name="heading"
                           value="<?php echo htmlspecialchars($config['heading'] ?? 'Nuestros Productos'); ?>"
                           placeholder="Ej: Nuestros Productos">
                    <small style="color: #666; margin-top: 5px; display: block;">Este es el título grande de la sección</small>
                </div>

                <div class="form-group">
                    <label for="subheading">Subtítulo (opcional)</label>
                    <textarea id="subheading" name="subheading"
                              placeholder="Ej: Descubre nuestra selección premium..."><?php echo htmlspecialchars($config['subheading'] ?? ''); ?></textarea>
                    <small style="color: #666; margin-top: 5px; display: block;">Texto adicional debajo del título</small>
                </div>

                <button type="submit" name="save_config" class="btn-save" id="saveBtn">
                    💾 Guardar Configuración
                </button>
            </form>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;

        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }
        });

        inputs.forEach(input => {
            input.addEventListener('input', checkForChanges);
            input.addEventListener('change', checkForChanges);
        });

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

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

        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => saveBtn.classList.remove('saved'), 3000);
        }
    </script>
</body>
</html>
