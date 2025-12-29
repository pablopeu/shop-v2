<?php
/**
 * Admin - Envíos Archivados
 * Listado de envíos archivados con opciones de restaurar o eliminar
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Envíos Archivados';
$currency_config = read_json(APP_PATH . '/config/currency.json');
$csrf_token = generate_csrf_token();

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'order_restored') {
        $message = 'Envío restaurado exitosamente';
    } elseif ($_GET['msg'] === 'order_deleted') {
        $message = 'Envío eliminado exitosamente';
    }
}

// Restore order
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $order_id = $_GET['id'];

    if (restore_archived_order($order_id)) {
        $message = 'Envío restaurado exitosamente';
        log_admin_action('order_restored', $_SESSION['username'], ['order_id' => $order_id]);
    } else {
        $error = 'Error al restaurar el envío';
    }
}

// Delete order permanently
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $order_id = $_GET['id'];

    if (delete_archived_order($order_id)) {
        $message = 'Envío eliminado exitosamente';
        log_admin_action('order_deleted', $_SESSION['username'], ['order_id' => $order_id]);
    } else {
        $error = 'Error al eliminar el envío';
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_orders = $_POST['selected_orders'] ?? [];

    if (!empty($selected_orders)) {
        $success_count = 0;
        foreach ($selected_orders as $order_id) {
            if ($action === 'restore') {
                if (restore_archived_order($order_id)) {
                    $success_count++;
                }
            } elseif ($action === 'delete') {
                if (delete_archived_order($order_id)) {
                    $success_count++;
                }
            }
        }

        $message = "$success_count envío(s) procesado(s) exitosamente";
        log_admin_action('bulk_archived_orders_action', $_SESSION['username'], [
            'action' => $action,
            'count' => $success_count
        ]);
    } else {
        $error = 'No se seleccionaron envíos';
    }
}

// Get all archived orders
$all_archived = get_archived_orders();

// Apply filters
$filter_status = $_GET['filter'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Apply status filter
if ($filter_status === 'all') {
    $orders = $all_archived;
} else {
    $orders = array_filter($all_archived, fn($o) => $o['status'] === $filter_status);
}

// Apply search filter
if (!empty($search_query)) {
    $orders = array_filter($orders, function($order) use ($search_query) {
        $search_lower = mb_strtolower($search_query);
        return stripos($order['order_number'], $search_query) !== false ||
               stripos($order['id'], $search_query) !== false ||
               stripos(mb_strtolower($order['customer_name'] ?? ''), $search_lower) !== false ||
               stripos(mb_strtolower($order['customer_email'] ?? ''), $search_lower) !== false;
    });
}

// Sort orders by archived date (newest first)
usort($orders, function($a, $b) {
    $date_a = $a['archived_date'] ?? $a['date'];
    $date_b = $b['archived_date'] ?? $b['date'];
    return strtotime($date_b) - strtotime($date_a);
});

// Calculate stats
$total_archived = count($all_archived);
$impagos = count(array_filter($all_archived, fn($o) => $o['status'] === 'impago'));
$pagados = count(array_filter($all_archived, fn($o) => $o['status'] === 'pagado'));
$entregadas = count(array_filter($all_archived, fn($o) => $o['status'] === 'entregada'));

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>

    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        h1 {
            color: #2d3748;
            margin-bottom: 20px;
        }

        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .filters-card {
            padding: 20px;
            margin-bottom: 20px;
        }

        .filters-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        tr:hover {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .compact-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            gap: 20px;
            flex-wrap: wrap;
        }

        .bulk-actions-section {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .bulk-actions-section select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        #selectedCount {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <h1>📦 Envíos Archivados</h1>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_archived; ?></div>
                <div class="stat-label">Total Archivados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pagados; ?></div>
                <div class="stat-label">Pagados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $entregadas; ?></div>
                <div class="stat-label">Entregadas</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filters-card">
            <form method="GET" action="">
                <input type="hidden" name="page" value="envios-archivo">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="search">Buscar</label>
                        <input type="text" id="search" name="search" placeholder="Número de orden, cliente..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="filter">Estado</label>
                        <select id="filter" name="filter">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                            <option value="impago" <?php echo $filter_status === 'impago' ? 'selected' : ''; ?>>Impagos</option>
                            <option value="pagado" <?php echo $filter_status === 'pagado' ? 'selected' : ''; ?>>Pagados</option>
                            <option value="entregada" <?php echo $filter_status === 'entregada' ? 'selected' : ''; ?>>Entregadas</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                        <a href="?page=envios-archivo" class="btn btn-secondary">Limpiar</a>
                    </div>
                    <div class="filter-group">
                        <a href="<?php echo url('/admin/?page=envios-pendientes'); ?>" class="btn btn-secondary">
                            ← Volver a Envíos
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="card">
            <div class="compact-actions-bar">
                <form method="POST" id="bulkForm" class="bulk-actions-section">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <select name="bulk_action" id="bulkAction">
                        <option value="">Acciones en lote...</option>
                        <option value="restore">Restaurar</option>
                        <option value="delete">Eliminar Permanentemente</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar a Seleccionadas</button>
                    <span id="selectedCount"></span>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Orden</th>
                        <th>Cliente</th>
                        <th>Fecha Archivado</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <p><strong>No hay envíos archivados</strong></p>
                                <p style="font-size: 13px; margin-top: 8px;">Los envíos archivados aparecerán aquí</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $total = $order['total'] ?? 0;
                            $currency = $order['currency'] ?? $currency_config['primary'];
                            $exchange_rate = $order['exchange_rate'] ?? 1;
                            $status = $order['status'] ?? 'impago';
                            $archived_date = $order['archived_date'] ?? $order['date'];
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_orders[]" value="<?php echo $order['id']; ?>" form="bulkForm" class="order-checkbox">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                    <small style="color: #94a3b8;"><?php echo htmlspecialchars(substr($order['id'], 0, 20)); ?>...</small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                                    <small style="color: #94a3b8;"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($archived_date)); ?></td>
                                <td>
                                    <strong>$<?php echo number_format($total, 2); ?></strong><br>
                                    <small style="color: #94a3b8;"><?php echo $currency; ?></small>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'impago' => '#fbbf24',
                                        'pagado' => '#10b981',
                                        'entregada' => '#3b82f6'
                                    ];
                                    $color = $status_colors[$status] ?? '#64748b';
                                    ?>
                                    <span style="display: inline-block; padding: 4px 8px; background: <?php echo $color; ?>22; color: <?php echo $color; ?>; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button type="button" class="btn btn-primary btn-sm" data-action="confirmRestore" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                            ↩️ Restaurar
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" data-action="confirmDelete" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                            🗑️ Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        // Update selected count
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.order-checkbox:checked').length;
            const countEl = document.getElementById('selectedCount');
            if (checked > 0) {
                countEl.textContent = `${checked} seleccionada${checked > 1 ? 's' : ''}`;
            } else {
                countEl.textContent = '';
            }
        }

        document.querySelectorAll('.order-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        // Confirm restore
        function confirmRestore(event, element, params) {
            const orderId = params?.orderId;
            const orderNumber = params?.orderNumber || orderId;

            showModal({
                title: 'Restaurar Envío',
                message: `¿Restaurar el envío ${orderNumber}? Volverá a la lista de envíos pendientes.`,
                icon: '↩️',
                confirmType: 'primary',
                onConfirm: function() {
                    window.location.href = `?page=envios-archivo&action=restore&id=${encodeURIComponent(orderId)}`;
                }
            });
        }

        // Confirm delete
        function confirmDelete(event, element, params) {
            const orderId = params?.orderId;
            const orderNumber = params?.orderNumber || orderId;

            showModal({
                title: 'Eliminar Permanentemente',
                message: `¿Eliminar permanentemente el envío ${orderNumber}? Esta acción NO se puede deshacer.`,
                icon: '⚠️',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?page=envios-archivo&action=delete&id=${encodeURIComponent(orderId)}`;
                }
            });
        }

        // Export for event delegation
        window.confirmRestore = confirmRestore;
        window.confirmDelete = confirmDelete;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
