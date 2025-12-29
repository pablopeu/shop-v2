<?php
/**
 * Admin - Emails Fallidos
 * Vista de emails que fallaron después de múltiples intentos
 */

// Security check
if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Emails Fallidos';
$csrf_token = generate_csrf_token();

// Handle actions
$message = '';
$error = '';

// Retry email
if (isset($_GET['action']) && $_GET['action'] === 'retry' && isset($_GET['id'])) {
    if (validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $email_id = $_GET['id'];
        if (retry_failed_email($email_id)) {
            $message = 'Email reintentado. Será procesado en la próxima ejecución del cron.';
            log_admin_action('email_retried', $_SESSION['username'], ['email_id' => $email_id]);
        } else {
            $error = 'Error al reintentar el email';
        }
    } else {
        $error = 'Token de seguridad inválido';
    }
}

// Clear all failed emails
if (isset($_POST['clear_all'])) {
    if (validate_csrf_token($_POST['csrf_token'] ?? '')) {
        if (clear_failed_emails()) {
            $message = 'Todos los emails fallidos han sido eliminados del registro';
            log_admin_action('failed_emails_cleared', $_SESSION['username'], []);
        } else {
            $error = 'Error al limpiar el registro';
        }
    } else {
        $error = 'Token de seguridad inválido';
    }
}

// Get all failed emails
$failed_emails = get_failed_emails();

// Email type labels
$type_labels = [
    'order_confirmation' => 'Confirmación de Pedido',
    'payment_approved' => 'Pago Aprobado',
    'payment_rejected' => 'Pago Rechazado',
    'payment_pending' => 'Pago Pendiente',
    'order_shipped' => 'Pedido Enviado',
    'order_in_delivery' => 'Pedido en Reparto',
    'order_delivered' => 'Pedido Entregado',
    'order_paid' => 'Pedido Pagado',
    'admin_new_order' => 'Nueva Orden (Admin)',
    'admin_chargeback' => 'Alerta de Contracargo (Admin)',
    'shipping_preparation' => 'Preparación de Envío'
];

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

        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 200px;
        }

        .stat h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .stat .value {
            font-size: 32px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.danger {
            background: #fee;
            color: #c00;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
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

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #a71d2a;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .error-detail {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
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

        <div class="card">
            <h2>📧 Emails Fallidos</h2>

            <?php if (count($failed_emails) > 0): ?>
                <div class="stats">
                    <div class="stat">
                        <h3>Total Fallidos</h3>
                        <div class="value"><?php echo count($failed_emails); ?></div>
                    </div>
                </div>

                <div class="actions">
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <button type="submit" name="clear_all" class="btn btn-danger btn-sm"
                                data-action="confirmClear">
                            🗑️ Limpiar Registro
                        </button>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Destinatario</th>
                            <th>Intentos</th>
                            <th>Último Intento</th>
                            <th>Error</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failed_emails as $email): ?>
                            <?php
                            $to = 'N/A';
                            if (isset($email['data']['order']['customer_email'])) {
                                $to = $email['data']['order']['customer_email'];
                            }
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars(substr($email['id'], 0, 16)); ?>...</code></td>
                                <td><?php echo htmlspecialchars($type_labels[$email['type']] ?? $email['type']); ?></td>
                                <td><?php echo htmlspecialchars($to); ?></td>
                                <td><span class="badge danger"><?php echo $email['attempts']; ?>/<?php echo $email['max_attempts']; ?></span></td>
                                <td><?php echo htmlspecialchars($email['last_attempt'] ?? 'N/A'); ?></td>
                                <td>
                                    <div class="error-detail" title="<?php echo htmlspecialchars($email['error'] ?? 'Error desconocido'); ?>">
                                        <?php echo htmlspecialchars($email['error'] ?? 'Error desconocido'); ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="?action=retry&id=<?php echo urlencode($email['id']); ?>" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm"
                                                data-action="confirmRetry"
                                                data-email-id="<?php echo htmlspecialchars($email['id']); ?>">
                                            🔄 Reintentar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>✅ No hay emails fallidos</h3>
                    <p>Todos los emails se están enviando correctamente.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
        function confirmClear(event, element, params) {
            event.preventDefault();
            showModal({
                title: 'Confirmar Limpieza',
                message: '¿Estás seguro de que deseas eliminar todos los emails fallidos del registro? Esta acción no se puede deshacer.',
                icon: '⚠️',
                confirmType: 'danger',
                onConfirm: function() {
                    element.closest('form').submit();
                }
            });
        }

        function confirmRetry(event, element, params) {
            event.preventDefault();
            const emailId = params?.emailId || 'desconocido';
            showModal({
                title: 'Confirmar Reintento',
                message: '¿Reintentar el envío de este email? Se agregará a la cola y será procesado por el cron job.',
                icon: '🔄',
                confirmType: 'primary',
                onConfirm: function() {
                    element.closest('form').submit();
                }
            });
        }

        // Export for event delegation
        window.confirmClear = confirmClear;
        window.confirmRetry = confirmRetry;
    </script>

    <!-- Event handlers -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
