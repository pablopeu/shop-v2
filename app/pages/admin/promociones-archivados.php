<?php
/**
 * Admin - Archived Promotions List
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Promociones Archivadas';

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'promotion_restored') {
        $message = 'Promoción restaurada exitosamente';
    } elseif ($_GET['msg'] === 'promotion_deleted') {
        $message = 'Promoción eliminada permanentemente';
    }
}

// Restore promotion
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $promotion_id = $_GET['id'];

    if (restore_promotion($promotion_id)) {
        $message = 'Promoción restaurada exitosamente';
        log_admin_action('promotion_restored', $_SESSION['username'], ['promotion_id' => $promotion_id]);
    } else {
        $error = 'Error al restaurar la promoción';
    }
}

// Delete permanently
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $promotion_id = $_GET['id'];

    $result = delete_archived_promotion($promotion_id);
    if ($result['success']) {
        $message = $result['message'];
        log_admin_action('promotion_deleted_permanently', $_SESSION['username'], ['promotion_id' => $promotion_id]);
    } else {
        $error = $result['message'];
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_promotions = $_POST['selected_promotions'] ?? [];

    if (!empty($selected_promotions)) {
        $success_count = 0;
        $error_count = 0;

        foreach ($selected_promotions as $promotion_id) {
            if ($action === 'restore') {
                if (restore_promotion($promotion_id)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } elseif ($action === 'delete') {
                $result = delete_archived_promotion($promotion_id);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $action_text = $action === 'restore' ? 'restaurada(s)' : 'eliminada(s)';
            $message = "$success_count promoción(es) $action_text exitosamente";

            if ($error_count > 0) {
                $message .= " ($error_count fallaron)";
            }

            log_admin_action('bulk_archived_promotions_action', $_SESSION['username'], [
                'action' => $action,
                'success_count' => $success_count,
                'error_count' => $error_count
            ]);
        } else {
            $error = 'No se pudieron procesar las promociones seleccionadas';
        }
    } else {
        $error = 'No se seleccionaron promociones';
    }
}

// Get all archived promotions
$archived_promotions = get_archived_promotions();

// Sort by archived date (newest first)
usort($archived_promotions, function($a, $b) {
    return strtotime($b['archived_at'] ?? '2000-01-01') - strtotime($a['archived_at'] ?? '2000-01-01');
});

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promociones Archivadas - Admin</title>

    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
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

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .card-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .promotions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .promotions-table th,
        .promotions-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .promotions-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .promotions-table td {
            font-size: 14px;
        }

        .promotions-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.percentage {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge.fixed {
            background: #fff3cd;
            color: #856404;
        }

        .badge.expired {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.valid {
            background: #d4edda;
            color: #155724;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .bulk-actions-bar {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 12px;
        }

        .bulk-actions-bar.show {
            display: flex;
        }

        .bulk-actions-bar select {
            padding: 6px 12px;
            border: 1px solid #ffc107;
            border-radius: 4px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
        }

        .header-actions .btn {
            font-size: 13px;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -15px;
            padding: 0 15px;
        }

        .info-badge {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
            color: #0c5460;
        }

        @media (min-width: 1025px) {
            .table-container {
                overflow-x: visible;
                margin: 0;
                padding: 0;
            }
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
            .promotions-table {
                min-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .promotions-table {
                font-size: 12px;
                min-width: 700px;
            }

            .promotions-table th,
            .promotions-table td {
                padding: 8px 6px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Header Actions -->
        <div class="header-actions">
            <div>
                <a href="<?php echo url('/admin/?page=promociones-listado'); ?>" class="btn btn-secondary">
                    ← Volver a Promociones
                </a>
            </div>
        </div>

        <!-- Info -->
        <div class="info-badge">
            📦 Las promociones archivadas se guardan aquí. Puedes restaurarlas al listado principal o eliminarlas permanentemente.
        </div>

        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <span id="selectedCount">0 promociones seleccionadas</span>
                <select name="bulk_action" id="bulkAction">
                    <option value="">Seleccionar acción...</option>
                    <option value="restore">Restaurar</option>
                    <option value="delete">Eliminar Permanentemente</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" data-action="confirmBulkAction">
                    Aplicar
                </button>
            </div>

            <!-- Archived Promotions List -->
            <div class="card">
                <div class="card-header">
                    Promociones Archivadas (<?php echo count($archived_promotions); ?>)
                </div>

                <div class="table-container">
                    <table class="promotions-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" data-onchange="toggleSelectAll">
                                </th>
                                <th>Nombre</th>
                                <th>Tipo / Descuento</th>
                                <th>Aplicación</th>
                                <th>Período</th>
                                <th>Archivado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($archived_promotions)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                        No hay promociones archivadas.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($archived_promotions as $promo):
                                    $now = time();
                                    $is_expired = $promo['period_type'] === 'limited' && strtotime($promo['end_date']) < $now;
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_promotions[]"
                                                   value="<?php echo htmlspecialchars($promo['id']); ?>"
                                                   class="promotion-checkbox"
                                                   data-onchange="updateBulkActions">
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($promo['name']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $promo['type']; ?>">
                                                <?php echo $promo['type'] === 'percentage' ? '%' : '$'; ?>
                                            </span>
                                            <strong>
                                                <?php echo $promo['type'] === 'percentage'
                                                    ? $promo['value'] . '% OFF'
                                                    : format_price($promo['value'], 'ARS') . ' OFF'; ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $promo['application'] === 'all' ? 'Todo el sitio' : count($promo['products']) . ' productos'; ?></td>
                                        <td>
                                            <?php if ($promo['period_type'] === 'permanent'): ?>
                                                Permanente
                                            <?php else: ?>
                                                <?php echo date('d/m/Y', strtotime($promo['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($promo['end_date'])); ?>
                                                <br>
                                                <?php if ($is_expired): ?>
                                                    <span class="badge expired">Expirado</span>
                                                <?php else: ?>
                                                    <span class="badge valid">Vigente</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($promo['archived_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="javascript:void(0)"
                                                   class="btn btn-primary btn-sm"
                                                   data-action="confirmRestorePromotion"
                                                   data-id="<?php echo urlencode($promo['id']); ?>"
                                                   data-name="<?php echo htmlspecialchars($promo['name'], ENT_QUOTES); ?>">
                                                    ↩️ Restaurar
                                                </a>
                                                <a href="javascript:void(0)"
                                                   class="btn btn-danger btn-sm"
                                                   data-action="confirmDeletePromotion"
                                                   data-id="<?php echo urlencode($promo['id']); ?>"
                                                   data-name="<?php echo htmlspecialchars($promo['name'], ENT_QUOTES); ?>">
                                                    🗑️ Eliminar
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        function confirmRestorePromotion(id, name) {
            showModal({
                title: 'Restaurar Promoción',
                message: `¿Estás seguro de que deseas restaurar la promoción "${name}"?`,
                details: 'La promoción volverá al listado principal de promociones activas.',
                icon: '↩️',
                confirmText: 'Restaurar',
                confirmType: 'primary',
                onConfirm: function() {
                    window.location.href = `?action=restore&id=${id}`;
                }
            });
        }

        function confirmDeletePromotion(id, name) {
            showModal({
                title: 'Eliminar Promoción Permanentemente',
                message: `¿Estás seguro de que deseas eliminar permanentemente la promoción "${name}"?`,
                details: 'ADVERTENCIA: Esta acción no se puede deshacer. La promoción será eliminada de forma permanente.',
                icon: '🗑️',
                confirmText: 'Eliminar Permanentemente',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=delete&id=${id}`;
                }
            });
        }

        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.promotion-checkbox:checked');
            const action = document.getElementById('bulkAction').value;
            const count = checkboxes.length;

            if (count === 0) {
                showModal({
                    title: 'Sin Promociones Seleccionadas',
                    message: 'Debes seleccionar al menos una promoción para realizar una acción masiva.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    onConfirm: function() {}
                });
                return;
            }

            if (!action) {
                showModal({
                    title: 'Acción No Seleccionada',
                    message: 'Debes seleccionar una acción para aplicar a las promociones seleccionadas.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    onConfirm: function() {}
                });
                return;
            }

            let title, message, icon, confirmType;

            if (action === 'restore') {
                title = 'Restaurar Promociones';
                message = `¿Restaurar ${count} promoción${count > 1 ? 'es' : ''}?`;
                icon = '↩️';
                confirmType = 'primary';
            } else if (action === 'delete') {
                title = 'Eliminar Promociones Permanentemente';
                message = `¿Eliminar permanentemente ${count} promoción${count > 1 ? 'es' : ''}?`;
                icon = '🗑️';
                confirmType = 'danger';
            }

            showModal({
                title: title,
                message: message,
                details: `Esta acción se aplicará a ${count} promoción${count > 1 ? 'es seleccionadas' : ' seleccionada'}.`,
                icon: icon,
                confirmText: 'Confirmar',
                confirmType: confirmType,
                onConfirm: function() {
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.promotion-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.promotion-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBar.classList.add('show');
                selectedCount.textContent = `${count} promoción${count > 1 ? 'es' : ''} seleccionada${count > 1 ? 's' : ''}`;
            } else {
                bulkBar.classList.remove('show');
                selectAll.checked = false;
            }
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: confirmRestorePromotion
         * Compatible con llamadas directas y event delegation
         */
        const _confirmRestorePromotion = confirmRestorePromotion;
        window.confirmRestorePromotion = function(eventOrId, element, params) {
            const id = params?.id || (typeof eventOrId === 'string' ? eventOrId : null);
            const name = params?.name || (typeof arguments[1] === 'string' ? arguments[1] : null);
            if (id && name) return _confirmRestorePromotion(id, name);
        };

        /**
         * Wrapper: confirmDeletePromotion
         * Compatible con llamadas directas y event delegation
         */
        const _confirmDeletePromotion = confirmDeletePromotion;
        window.confirmDeletePromotion = function(eventOrId, element, params) {
            const id = params?.id || (typeof eventOrId === 'string' ? eventOrId : null);
            const name = params?.name || (typeof arguments[1] === 'string' ? arguments[1] : null);
            if (id && name) return _confirmDeletePromotion(id, name);
        };

        /**
         * Wrapper: confirmBulkAction
         * Compatible con llamadas directas y event delegation
         */
        const _confirmBulkAction = confirmBulkAction;
        window.confirmBulkAction = function(event, element, params) {
            if (event && event.preventDefault) event.preventDefault();
            return _confirmBulkAction();
        };

        /**
         * Wrapper: toggleSelectAll
         * Compatible con llamadas directas y event delegation
         */
        const _toggleSelectAll = toggleSelectAll;
        window.toggleSelectAll = function(eventOrCheckbox, element, params) {
            const checkbox = element || (eventOrCheckbox instanceof HTMLElement ? eventOrCheckbox : null);
            if (checkbox) return _toggleSelectAll(checkbox);
        };

        /**
         * Wrapper: updateBulkActions
         * Compatible con llamadas directas y event delegation
         */
        const _updateBulkActions = updateBulkActions;
        window.updateBulkActions = function(event, element, params) {
            return _updateBulkActions();
        };
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
