<?php
/**
 * Admin - Configuración de Cola de Emails
 * Sistema de procesamiento automático de emails
 */

// Security check
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Cola de Emails';
$csrf_token = generate_csrf_token();

// Handle actions
$message = '';
$error = '';

// Manual processing
if (isset($_POST['process_now'])) {
    if (validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $result = run_manual_email_processing();
        if ($result['success']) {
            $stats = $result['stats'];
            $message = sprintf(
                'Procesamiento manual completado: %d enviados, %d fallidos, %d pendientes',
                $stats['sent'],
                $stats['failed'],
                $stats['pending']
            );
            log_admin_action('manual_email_processing', $_SESSION['username'], $stats);
        } else {
            $error = 'Error al procesar: ' . $result['error'];
        }
    } else {
        $error = 'Token de seguridad inválido';
    }
}

// Get system info
$system_info = get_email_queue_system_info();

// Detect paths for cron command
$site_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('/api/process-email-queue.php?secret=email_queue_cron_2024');
$log_path = dirname(APP_PATH) . '/logs/email-queue.log';

// Generate cron command (usando wget - más compatible con cPanel)
$cron_command = "wget -q -O - \"$site_url\" >> $log_path 2>&1";

// Comando alternativo (PHP CLI - si wget no funciona)
$detected_php_path = '/usr/bin/php'; // Default
$script_path = APP_PATH . '/scripts/process-email-queue.php';
$cron_command_alt = "$detected_php_path $script_path 2>/dev/null >> $log_path";

$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>

    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 20px;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 15px; }
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .card h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .card h3 {
            font-size: 16px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #34495e;
        }

        .message {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #fff3cd;
            color: #856404;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
        }

        .stat-card h4 {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card .label {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 15px 0;
            position: relative;
        }

        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 6px 12px;
            background: #4a5568;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .copy-btn:hover {
            background: #2d3748;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }

        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-box p {
            color: #0d47a1;
            font-size: 13px;
            line-height: 1.6;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }

        .warning-box h4 {
            color: #856404;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .warning-box p {
            color: #856404;
            font-size: 13px;
            line-height: 1.6;
        }

        .instructions {
            margin-top: 20px;
        }

        .instructions ol {
            margin-left: 20px;
            line-height: 1.8;
        }

        .instructions li {
            margin-bottom: 10px;
            color: #495057;
        }

        .collapsible-section {
            margin-top: 20px;
        }

        .collapsible-header {
            background: #f8f9fa;
            padding: 12px 15px;
            cursor: pointer;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }

        .collapsible-header:hover {
            background: #e9ecef;
        }

        .collapsible-content {
            display: none;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 6px 6px;
        }

        .collapsible-content.active {
            display: block;
        }

        .toggle-icon {
            transition: transform 0.3s;
        }

        .toggle-icon.active {
            transform: rotate(180deg);
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

        <!-- Estado del Sistema -->
        <div class="card">
            <h2>📧 Sistema de Cola de Emails</h2>

            <div class="info-box">
                <h4>🚀 Modo Actual: Pseudo-Cron Automático</h4>
                <p>
                    El sistema procesa automáticamente la cola de emails cada vez que hay visitas al sitio (máximo 1 vez por minuto).
                    No requiere configuración - funciona inmediatamente. ✅
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Emails Pendientes</h4>
                    <div class="value"><?php echo $system_info['pending_emails']; ?></div>
                    <div class="label">en cola de envío</div>
                </div>

                <div class="stat-card">
                    <h4>Emails Fallidos</h4>
                    <div class="value"><?php echo $system_info['failed_emails']; ?></div>
                    <div class="label">necesitan atención</div>
                </div>

                <div class="stat-card">
                    <h4>Procesados Hoy</h4>
                    <div class="value"><?php echo $system_info['executions_today']; ?></div>
                    <div class="label">ejecuciones</div>
                </div>

                <div class="stat-card">
                    <h4>Total Enviados</h4>
                    <div class="value"><?php echo $system_info['total_sent']; ?></div>
                    <div class="label">desde el inicio</div>
                </div>
            </div>

            <p style="margin-top: 15px; color: #6c757d; font-size: 14px;">
                <strong>Última ejecución:</strong>
                <?php echo $system_info['last_execution'] ? htmlspecialchars($system_info['last_execution']) : 'Nunca'; ?>
            </p>

            <?php if ($system_info['last_result']): ?>
                <p style="margin-top: 8px; color: #6c757d; font-size: 13px;">
                    Último resultado: <?php echo $system_info['last_result']['sent']; ?> enviados,
                    <?php echo $system_info['last_result']['failed']; ?> fallidos,
                    <?php echo $system_info['last_result']['pending']; ?> pendientes
                </p>
            <?php endif; ?>

            <form method="POST" action="" style="margin-top: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" name="process_now" class="btn btn-primary">
                    ⚡ Procesar Cola Ahora
                </button>
            </form>
        </div>

        <!-- Mejora Opcional: Cron Real -->
        <div class="card">
            <div class="collapsible-section">
                <div class="collapsible-header" data-action="toggleSection" data-target="cron-instructions">
                    <div>
                        <h2 style="margin: 0;">💡 Mejora Opcional: Configurar Cron Real</h2>
                        <p style="font-size: 13px; color: #6c757d; margin-top: 4px;">
                            Para mejor rendimiento independiente del tráfico del sitio
                        </p>
                    </div>
                    <span class="toggle-icon">▼</span>
                </div>

                <div class="collapsible-content" id="cron-instructions">
                    <div class="warning-box">
                        <h4>⚠️ Esto es completamente opcional</h4>
                        <p>
                            El sistema ya funciona automáticamente con pseudo-cron. Solo configura esto si quieres
                            que los emails se procesen incluso cuando no hay visitas al sitio.
                        </p>
                    </div>

                    <h3>Instrucciones para cPanel:</h3>

                    <div class="instructions">
                        <ol>
                            <li>Inicia sesión en <strong>cPanel</strong></li>
                            <li>Busca y haz click en <strong>"Cron Jobs"</strong> (sección Avanzado)</li>
                            <li>Configura la frecuencia:
                                <ul style="margin-top: 8px; margin-left: 20px;">
                                    <li>Minuto: <code>*/1</code> (cada minuto)</li>
                                    <li>Hora: <code>*</code></li>
                                    <li>Día: <code>*</code></li>
                                    <li>Mes: <code>*</code></li>
                                    <li>Día de la semana: <code>*</code></li>
                                </ul>
                            </li>
                            <li>Copia y pega este comando (RECOMENDADO - más compatible con cPanel):</li>
                        </ol>
                    </div>

                    <div class="code-block">
                        <button class="copy-btn" data-action="copyToClipboard" data-text="<?php echo htmlspecialchars($cron_command); ?>">
                            📋 Copiar
                        </button>
                        <code id="cron-command"><?php echo htmlspecialchars($cron_command); ?></code>
                    </div>

                    <div class="info-box" style="margin-top: 20px;">
                        <h4>💡 Comando Alternativo (Si wget no funciona)</h4>
                        <p>Si el comando de arriba no funciona, prueba con PHP CLI:</p>
                        <div class="code-block" style="margin-top: 10px;">
                            <button class="copy-btn" data-action="copyToClipboard" data-text="<?php echo htmlspecialchars($cron_command_alt); ?>">
                                📋 Copiar
                            </button>
                            <code><?php echo htmlspecialchars($cron_command_alt); ?></code>
                        </div>
                    </div>

                    <div class="instructions">
                        <ol start="5">
                            <li>Haz click en <strong>"Add New Cron Job"</strong></li>
                            <li>Espera 1-2 minutos y verifica que funcione revisando el log</li>
                        </ol>
                    </div>

                    <h3>Configuración:</h3>
                    <ul style="margin-left: 20px; line-height: 1.8; color: #495057;">
                        <li><strong>URL del endpoint:</strong> <code><?php echo htmlspecialchars($site_url); ?></code></li>
                        <li><strong>Log:</strong> <code><?php echo $log_path; ?></code></li>
                    </ul>

                    <div class="info-box" style="margin-top: 20px;">
                        <h4>💡 ¿Cómo saber si funciona?</h4>
                        <p>
                            Una vez configurado, el cron job creará el archivo de log. Puedes ver su contenido
                            desde el File Manager de cPanel o contactar a tu hosting para verificar que se está ejecutando.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enlaces Relacionados -->
        <div class="card">
            <h2>🔗 Enlaces Relacionados</h2>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <a href="<?php echo url('/admin/?page=emails-fallidos'); ?>" class="btn btn-secondary">
                    📧 Ver Emails Fallidos
                </a>
                <a href="<?php echo url('/admin/?page=config-email-templates'); ?>" class="btn btn-secondary">
                    📝 Plantillas de Email
                </a>
            </div>
        </div>
    </div>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
        function toggleSection(event, element, params) {
            const targetId = params?.target;
            if (!targetId) return;

            const content = document.getElementById(targetId);
            const icon = element.querySelector('.toggle-icon');

            if (content) {
                content.classList.toggle('active');
                icon.classList.toggle('active');
            }
        }

        function copyToClipboard(event, element, params) {
            const text = params?.text;
            if (!text) return;

            navigator.clipboard.writeText(text).then(() => {
                element.textContent = '✅ Copiado!';
                setTimeout(() => {
                    element.textContent = '📋 Copiar';
                }, 2000);
            }).catch(err => {
                console.error('Error copying:', err);
            });
        }

        // Export for event delegation
        window.toggleSection = toggleSection;
        window.copyToClipboard = copyToClipboard;
    </script>

    <!-- Event handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
