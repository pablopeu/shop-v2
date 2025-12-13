<?php
/**
 * Admin - Coupons List
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Gestión de Cupones';

// Handle actions
$message = '';
$error = '';

// Delete coupon
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $coupon_id = $_GET['id'];
    $coupons_data = read_json(APP_PATH . '/data/coupons.json');

    $coupons_data['coupons'] = array_filter($coupons_data['coupons'], function($c) use ($coupon_id) {
        return $c['id'] !== $coupon_id;
    });
    $coupons_data['coupons'] = array_values($coupons_data['coupons']);

    if (write_json(APP_PATH . '/data/coupons.json', $coupons_data)) {
        $message = 'Cupón eliminado exitosamente';
        log_admin_action('coupon_deleted', $_SESSION['username'], ['coupon_id' => $coupon_id]);
    } else {
        $error = 'Error al eliminar el cupón';
    }
}

// Toggle coupon active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $coupon_id = $_GET['id'];
    $coupons_data = read_json(APP_PATH . '/data/coupons.json');

    foreach ($coupons_data['coupons'] as &$coupon) {
        if ($coupon['id'] === $coupon_id) {
            $coupon['active'] = !$coupon['active'];
            $new_status = $coupon['active'];
            break;
        }
    }

    if (write_json(APP_PATH . '/data/coupons.json', $coupons_data)) {
        $message = $new_status ? 'Cupón activado' : 'Cupón desactivado';
        log_admin_action('coupon_toggled', $_SESSION['username'], [
            'coupon_id' => $coupon_id,
            'new_status' => $new_status
        ]);
    }
}

// Archive coupon
if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
    $coupon_id = $_GET['id'];

    if (archive_coupon($coupon_id)) {
        $message = 'Cupón archivado exitosamente';
        log_admin_action('coupon_archived', $_SESSION['username'], ['coupon_id' => $coupon_id]);
    } else {
        $error = 'Error al archivar el cupón';
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_coupons = $_POST['selected_coupons'] ?? [];

    if (!empty($selected_coupons)) {
        $success_count = 0;
        foreach ($selected_coupons as $coupon_id) {
            if ($action === 'archive') {
                if (archive_coupon($coupon_id)) {
                    $success_count++;
                }
            } elseif ($action === 'activate') {
                if (update_coupon($coupon_id, ['active' => true])) {
                    $success_count++;
                }
            } elseif ($action === 'deactivate') {
                if (update_coupon($coupon_id, ['active' => false])) {
                    $success_count++;
                }
            }
        }

        $message = "$success_count cupón(es) procesado(s) exitosamente";
        log_admin_action('bulk_coupons_action', $_SESSION['username'], [
            'action' => $action,
            'count' => $success_count
        ]);
    } else {
        $error = 'No se seleccionaron cupones';
    }
}

// Get all coupons
$coupons_data = read_json(APP_PATH . '/data/coupons.json');
$coupons = $coupons_data['coupons'] ?? [];

// Calculate stats
$now = time();
$total_coupons = count($coupons);
$active_coupons = count(array_filter($coupons, fn($c) => $c['active']));
$expired_coupons = count(array_filter($coupons, function($c) use ($now) {
    return strtotime($c['end_date']) < $now;
}));
$total_uses = array_sum(array_column($coupons, 'uses_count'));

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cupones - Admin</title>

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

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 15px 20px;
        }

        /* Messages */
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

        /* Buttons */
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

        /* Card */
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

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .stat-card {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .stat-label {
            color: #666;
            font-size: 12px;
        }

        /* Table */
        .coupons-table {
            width: 100%;
            border-collapse: collapse;
        }

        .coupons-table th,
        .coupons-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .coupons-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .coupons-table td {
            font-size: 14px;
        }

        .coupons-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.active {
            background: #d4edda;
            color: #155724;
        }

        .badge.inactive {
            background: #f8d7da;
            color: #721c24;
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

        .coupon-code {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-weight: bold;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
        }

        /* Bulk Actions Bar */
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

        /* Header Actions */
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

        /* Table Container for Mobile Scroll */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -15px;
            padding: 0 15px;
        }

        @media (min-width: 1025px) {
            .table-container {
                overflow-x: visible;
                margin: 0;
                padding: 0;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
            .coupons-table {
                min-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .coupons-table {
                font-size: 12px;
                min-width: 700px;
            }

            .coupons-table th,
            .coupons-table td {
                padding: 8px 6px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                width: 100%;
            }
        }

        /* Mobile Cards View */
        .mobile-cards {
            display: none;
        }

        @media (max-width: 768px) {
            .table-container {
                display: none !important;
            }

            .mobile-cards {
                display: block;
            }

            .mobile-card {
                background: white;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 12px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                border-left: 4px solid #3498db;
            }

            .mobile-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f0;
            }

            .mobile-card-title {
                font-weight: 600;
                color: #2c3e50;
                font-size: 15px;
                flex: 1;
            }

            .mobile-card-body {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 12px;
            }

            .mobile-card-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 14px;
            }

            .mobile-card-label {
                color: #666;
                font-weight: 500;
            }

            .mobile-card-value {
                color: #2c3e50;
                text-align: right;
            }

            .mobile-card-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding-top: 10px;
                border-top: 1px solid #f0f0f0;
            }

            .mobile-card-actions .btn {
                width: 100%;
                margin: 0;
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
                <a href="<?php echo url('/admin/?page=cupones-nuevo'); ?>" class="btn btn-primary">
                    ➕ Nuevo Cupón
                </a>
                <a href="<?php echo url('/admin/?page=cupones-archivados'); ?>" class="btn btn-secondary">
                    📦 Ver Archivados
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_coupons; ?></div>
                <div class="stat-label">Total Cupones</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_coupons; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $expired_coupons; ?></div>
                <div class="stat-label">Expirados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_uses; ?></div>
                <div class="stat-label">Usos Totales</div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <span id="selectedCount">0 cupones seleccionados</span>
                <select name="bulk_action" id="bulkAction">
                    <option value="">Seleccionar acción...</option>
                    <option value="activate">Activar</option>
                    <option value="deactivate">Desactivar</option>
                    <option value="archive">Archivar</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" data-action="confirmBulkAction">
                    Aplicar
                </button>
            </div>

            <!-- Coupons List -->
            <div class="card">
                <div class="card-header">Todos los Cupones</div>

                <div class="table-container">
                <table class="coupons-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" data-onchange="toggleSelectAll">
                            </th>
                            <th>Código</th>
                            <th>Tipo / Descuento</th>
                            <th>Vigencia</th>
                            <th>Usos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                    No hay cupones.
                                    <a href="<?php echo url('/admin/?page=cupones-nuevo'); ?>" style="color: #4CAF50;">Crear tu primer cupón</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon):
                                $is_expired = strtotime($coupon['end_date']) < $now;
                                $is_maxed = $coupon['max_uses'] > 0 && $coupon['uses_count'] >= $coupon['max_uses'];
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_coupons[]"
                                               value="<?php echo htmlspecialchars($coupon['id']); ?>"
                                               class="coupon-checkbox"
                                               data-onchange="updateBulkActions">
                                    </td>
                                    <td>
                                        <span class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $coupon['type']; ?>">
                                            <?php echo $coupon['type'] === 'percentage' ? '%' : '$'; ?>
                                        </span>
                                        <strong>
                                            <?php
                                            echo $coupon['type'] === 'percentage'
                                                ? $coupon['value'] . '% OFF'
                                                : format_price($coupon['value'], 'ARS') . ' OFF';
                                            ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($coupon['start_date'])); ?> -
                                        <?php echo date('d/m/Y', strtotime($coupon['end_date'])); ?>
                                        <br>
                                        <?php if ($is_expired): ?>
                                            <span class="badge expired">Expirado</span>
                                        <?php else: ?>
                                            <span class="badge valid">Vigente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $coupon['uses_count']; ?> /
                                        <?php echo $coupon['max_uses'] > 0 ? $coupon['max_uses'] : '∞'; ?>
                                        <?php if ($is_maxed): ?>
                                            <br><span class="badge inactive">Límite alcanzado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $coupon['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $coupon['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?php echo url('/admin/?page=cupones-editar&id=' . urlencode($coupon['id'])); ?>"
                                               class="btn btn-primary btn-sm">✏️ Editar</a>
                                            <a href="javascript:void(0)"
                                               class="btn btn-secondary btn-sm"
                                               data-action="confirmToggleCoupon" data-coupon-id="<?php echo urlencode($coupon['id']); ?>" data-is-active="<?php echo $coupon['active'] ? 'true' : 'false'; ?>">
                                                <?php echo $coupon['active'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                            </a>
                                            <a href="javascript:void(0)"
                                               class="btn btn-danger btn-sm"
                                               data-action="confirmArchiveCoupon" data-coupon-id="<?php echo urlencode($coupon['id']); ?>" data-coupon-code="<?php echo htmlspecialchars($coupon['code'], ENT_QUOTES); ?>">
                                                📦 Archivar
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

    <!-- Mobile Cards View -->
            <div class="mobile-cards">
                <?php if (empty($coupons)): ?>
                    <div class="card">
                        <p style="text-align: center; color: #999; padding: 20px;">
                            No hay cupones.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($coupons as $coupon):
                        $is_expired = strtotime($coupon['end_date']) < $now;
                        $is_maxed = $coupon['max_uses'] > 0 && $coupon['uses_count'] >= $coupon['max_uses'];
                    ?>
                        <div class="mobile-card">
                            <div class="mobile-card-header">
                                <div class="mobile-card-title">
                                    <span class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                </div>
                            </div>

                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Descuento:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $coupon['type']; ?>">
                                            <?php echo $coupon['type'] === 'percentage' ? '%' : '$'; ?>
                                        </span>
                                        <strong>
                                            <?php
                                            echo $coupon['type'] === 'percentage'
                                                ? $coupon['value'] . '% OFF'
                                                : format_price($coupon['value'], 'ARS') . ' OFF';
                                            ?>
                                        </strong>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Vigencia:</span>
                                    <span class="mobile-card-value">
                                        <?php echo date('d/m/Y', strtotime($coupon['start_date'])); ?> -
                                        <?php echo date('d/m/Y', strtotime($coupon['end_date'])); ?><br>
                                        <?php if ($is_expired): ?>
                                            <span class="badge expired">Expirado</span>
                                        <?php else: ?>
                                            <span class="badge valid">Vigente</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Usos:</span>
                                    <span class="mobile-card-value">
                                        <?php echo $coupon['uses_count']; ?> /
                                        <?php echo $coupon['max_uses'] > 0 ? $coupon['max_uses'] : '∞'; ?>
                                        <?php if ($is_maxed): ?>
                                            <br><span class="badge inactive">Límite alcanzado</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Estado:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $coupon['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $coupon['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="mobile-card-actions">
                                <a href="<?php echo url('/admin/?page=cupones-editar&id=' . urlencode($coupon['id'])); ?>"
                                   class="btn btn-primary btn-sm">Editar</a>
                                <a href="javascript:void(0)"
                                   class="btn btn-secondary btn-sm"
                                   data-action="confirmToggleCoupon" data-coupon-id="<?php echo urlencode($coupon['id']); ?>" data-is-active="<?php echo $coupon['active'] ? 'true' : 'false'; ?>">
                                    <?php echo $coupon['active'] ? 'Desactivar' : 'Activar'; ?>
                                </a>
                                <a href="javascript:void(0)"
                                   class="btn btn-danger btn-sm"
                                   data-action="confirmArchiveCoupon" data-coupon-id="<?php echo urlencode($coupon['id']); ?>" data-coupon-code="<?php echo htmlspecialchars($coupon['code'], ENT_QUOTES); ?>">
                                    Archivar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        function confirmToggleCoupon(id, isActive) {
            const action = isActive ? 'desactivar' : 'activar';
            showModal({
                title: isActive ? 'Desactivar Cupón' : 'Activar Cupón',
                message: `¿Estás seguro de que deseas ${action} este cupón?`,
                icon: isActive ? '❌' : '✅',
                confirmText: isActive ? 'Desactivar' : 'Activar',
                confirmType: 'warning',
                onConfirm: function() {
                    window.location.href = `<?php echo url('/admin/?page=cupones-listado'); ?>&action=toggle&id=${id}`;
                }
            });
        }

        function confirmArchiveCoupon(id, code) {
            showModal({
                title: 'Archivar Cupón',
                message: `¿Estás seguro de que deseas archivar el cupón "${code}"?`,
                details: 'El cupón se moverá al archivo y no aparecerá en el listado principal. Podrás restaurarlo desde la sección de Cupones Archivados.',
                icon: '📦',
                confirmText: 'Archivar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `<?php echo url('/admin/?page=cupones-listado'); ?>&action=archive&id=${id}`;
                }
            });
        }

        /**
         * Confirmar acción masiva
         */
        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.coupon-checkbox:checked');
            const action = document.getElementById('bulkAction').value;
            const count = checkboxes.length;

            // Validaciones
            if (count === 0) {
                showModal({
                    title: 'Sin Cupones Seleccionados',
                    message: 'Debes seleccionar al menos un cupón para realizar una acción masiva.',
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
                    message: 'Debes seleccionar una acción para aplicar a los cupones seleccionados.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    onConfirm: function() {}
                });
                return;
            }

            // Configurar modal según la acción
            let title, message, icon, confirmType;

            if (action === 'activate') {
                title = 'Activar Cupones';
                message = `¿Activar ${count} cupón${count > 1 ? 'es' : ''}?`;
                icon = '✅';
                confirmType = 'primary';
            } else if (action === 'deactivate') {
                title = 'Desactivar Cupones';
                message = `¿Desactivar ${count} cupón${count > 1 ? 'es' : ''}?`;
                icon = '❌';
                confirmType = 'warning';
            } else if (action === 'archive') {
                title = 'Archivar Cupones';
                message = `¿Archivar ${count} cupón${count > 1 ? 'es' : ''}?`;
                icon = '📦';
                confirmType = 'danger';
            }

            showModal({
                title: title,
                message: message,
                details: `Esta acción se aplicará a ${count} cupón${count > 1 ? 'es seleccionados' : ' seleccionado'}.`,
                icon: icon,
                confirmText: 'Confirmar',
                confirmType: confirmType,
                onConfirm: function() {
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        // Handle checkbox selection for bulk actions
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.coupon-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.coupon-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBar.classList.add('show');
                selectedCount.textContent = `${count} cupón${count > 1 ? 'es' : ''} seleccionado${count > 1 ? 's' : ''}`;
            } else {
                bulkBar.classList.remove('show');
                selectAll.checked = false;
            }
        }

        // === Wrappers for Event Delegation System ===
        (function() {
            const _confirmBulkAction = confirmBulkAction;
            window.confirmBulkAction = function(event, element, params) {
                return _confirmBulkAction();
            };

            const _toggleSelectAll = toggleSelectAll;
            window.toggleSelectAll = function(event, element, params) {
                return _toggleSelectAll(element);
            };

            const _updateBulkActions = updateBulkActions;
            window.updateBulkActions = function(event, element, params) {
                return _updateBulkActions();
            };

            const _confirmToggleCoupon = confirmToggleCoupon;
            window.confirmToggleCoupon = function(eventOrId, element, params) {
                const id = params?.couponId || (typeof eventOrId === 'string' ? eventOrId : null);
                const isActive = params?.isActive === 'true';
                if (id) return _confirmToggleCoupon(id, isActive);
            };

            const _confirmArchiveCoupon = confirmArchiveCoupon;
            window.confirmArchiveCoupon = function(eventOrId, element, params) {
                const id = params?.couponId || (typeof eventOrId === 'string' ? eventOrId : null);
                const code = params?.couponCode || '';
                if (id) return _confirmArchiveCoupon(id, code);
            };
        })();
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
