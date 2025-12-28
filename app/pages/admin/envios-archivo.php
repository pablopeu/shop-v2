<?php
/**
 * Admin - Envíos Archivados
 * Listado de envíos archivados con opciones de restaurar o eliminar
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Check admin authentication
require_admin();

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
$lista_retiro = count(array_filter($all_archived, fn($o) => $o['status'] === 'lista_retiro'));
$en_transito = count(array_filter($all_archived, fn($o) => $o['status'] === 'en_transito'));
$enviadas = $en_transito; // Alias para stats card
$entregadas = count(array_filter($all_archived, fn($o) => $o['status'] === 'entregada'));

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envíos Archivados - Admin</title>

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
            padding: 15px 12px 15px 20px;
        }

        /* Ajustar para que el contenido llegue hasta el borde */
        .filters-card,
        .bulk-actions-bar,
        .card {
            margin-right: 0;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 8px;
            padding: 15px 0 15px 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .card-header {
            padding-right: 15px;
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
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .orders-table th:first-child,
        .orders-table td:first-child {
            padding-left: 15px;
        }

        .orders-table th:last-child,
        .orders-table td:last-child {
            padding-right: 15px;
        }

        .orders-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .orders-table td {
            font-size: 14px;
        }

        .orders-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.cobrada {
            background: #d4edda;
            color: #155724;
        }

        .badge.enviada {
            background: #cce5ff;
            color: #004085;
        }

        .badge.entregada {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge.cancelada {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        /* Filters */
        .filters-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: auto 1fr 1fr auto auto auto;
            gap: 12px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            height: 42px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        /* Asegurar altura consistente en filters-row */
        .filters-row .btn,
        .filters-row a.btn {
            height: 42px;
            min-height: 42px;
            padding: 8px 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Contenedor para alinear anchos */
        .content-wrapper {
            max-width: 100%;
        }

        .filters-card,
        .bulk-actions-bar,
        .card {
            max-width: 100%;
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
        }

        .header-actions .btn {
            font-size: 13px;
        }

        /* Table Container for Mobile Scroll */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0;
            padding: 0;
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
                padding: 15px;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .orders-table {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .orders-table {
                font-size: 12px;
                min-width: 800px;
            }

            .orders-table th,
            .orders-table td {
                padding: 8px 6px;
            }

            .actions {
                flex-direction: column;
                gap: 5px;
            }

            .actions .btn {
                width: 100%;
                padding: 6px 10px;
            }

            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions > div {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .header-actions .btn {
                width: 100%;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 8px;
            }

            .bulk-actions-bar {
                flex-direction: column;
                gap: 8px;
            }

            .bulk-actions-bar select,
            .bulk-actions-bar .btn {
                width: 100%;
            }

            /* Better touch targets */
            .btn {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* Mobile Cards View */
        .mobile-cards {
            display: none;
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: none;
            z-index: 10000;
            min-width: 300px;
            max-width: 500px;
            animation: slideIn 0.3s ease;
        }

        .toast.show {
            display: block;
        }

        .toast.success {
            background: #28a745;
        }

        .toast.error {
            background: #dc3545;
        }

        .toast.warning {
            background: #ffc107;
            color: #333;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .toast {
                right: 10px;
                left: 10px;
                min-width: auto;
                bottom: 10px;
            }
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
                border-left: 4px solid #999;
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

            .mobile-card-checkbox {
                margin-right: 10px;
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

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_archived; ?></div>
                <div class="stat-label">Total Archivados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $enviadas; ?></div>
                <div class="stat-label">Enviadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $entregadas; ?></div>
                <div class="stat-label">Entregadas</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <a href="<?php echo url('/admin/?page=envios-pendientes'); ?>" class="btn btn-secondary">
                            Volver a Envíos
                        </a>
                    </div>

                    <div class="filter-group">
                        <label for="search">Buscar</label>
                        <input type="text" id="search" name="search"
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Nº Orden, Cliente, Email">
                    </div>

                    <div class="filter-group">
                        <label for="filter">Estado</label>
                        <select id="filter" name="filter">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="cobrada" <?php echo $filter_status === 'cobrada' ? 'selected' : ''; ?>>Cobrada</option>
                            <option value="enviada" <?php echo $filter_status === 'enviada' ? 'selected' : ''; ?>>Enviada</option>
                            <option value="entregada" <?php echo $filter_status === 'entregada' ? 'selected' : ''; ?>>Entregada</option>
                            <option value="cancelada" <?php echo $filter_status === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <span id="selectedCount">0 envíos seleccionados</span>
                <select name="bulk_action" id="bulkAction">
                    <option value="">Seleccionar acción...</option>
                    <option value="restore">Restaurar</option>
                    <option value="delete">Eliminar Permanentemente</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" data-action="confirmBulkAction">
                    Aplicar
                </button>
            </div>

            <!-- Orders List -->
            <div class="card">
                <div class="card-header">
                    <?php if (empty($orders)): ?>
                        Todos los Envíos Archivados
                    <?php else: ?>
                        Mostrando <?php echo count($orders); ?> de <?php echo $total_archived; ?> envíos archivados
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" data-onchange="toggleSelectAll">
                                </th>
                                <th>Nº Orden</th>
                                <th>Cliente</th>
                                <th>Fecha Orden</th>
                                <th>Fecha Archivo</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                        No hay envíos archivados que coincidan con los filtros.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr data-id="<?php echo htmlspecialchars($order['id']); ?>">
                                        <td>
                                            <input type="checkbox" name="selected_orders[]"
                                                   value="<?php echo htmlspecialchars($order['id']); ?>"
                                                   class="order-checkbox"
                                                   data-onchange="updateBulkActions">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                            <small style="color: #999;">ID: <?php echo htmlspecialchars(substr($order['id'], 0, 10)); ?>...</small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                                            <small style="color: #999;"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($order['date'])); ?><br>
                                            <small style="color: #999;"><?php echo date('H:i', strtotime($order['date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $archived_date = $order['archived_date'] ?? null;
                                            if ($archived_date):
                                            ?>
                                                <?php echo date('d/m/Y', strtotime($archived_date)); ?><br>
                                                <small style="color: #999;"><?php echo date('H:i', strtotime($archived_date)); ?></small>
                                            <?php else: ?>
                                                <small style="color: #999;">N/A</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo format_price($order['total']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $order['status']; ?>">
                                                <?php
                                                    $status_labels = [
                                                        'impago' => 'Impago',
                                                        'pagado' => 'Pagado',
                                                        'lista_retiro' => 'Lista para Retiro',
                                                        'en_transito' => 'En Tránsito',
                                                        'en_reparto' => 'En Reparto',
                                                        'entregada' => 'Entregada',
                                                        'fallida' => 'Fallida',
                                                        'devuelta' => 'Devuelta',
                                                        'cancelada' => 'Cancelada',
                                                        'rechazada' => 'Rechazada'
                                                    ];
                                                    echo $status_labels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" class="btn btn-primary btn-sm"
                                                        data-action="viewOrder" data-order-id="<?php echo $order['id']; ?>">
                                                    👁️ Ver
                                                </button>
                                                <button type="button" class="btn btn-warning btn-sm"
                                                        data-action="confirmRestoreOrder" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                                    ↩️ Restaurar
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                        data-action="confirmDeleteOrder" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
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
        </form>

        <!-- Mobile Cards View -->
        <div class="mobile-cards">
            <?php if (empty($orders)): ?>
                <div class="card">
                    <p style="text-align: center; color: #999; padding: 20px;">
                        No hay envíos archivados que coincidan con los filtros.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <div style="display: flex; align-items: center; flex: 1;">
                                <input type="checkbox" name="selected_orders[]"
                                       value="<?php echo htmlspecialchars($order['id']); ?>"
                                       class="order-checkbox mobile-card-checkbox"
                                       form="bulkForm"
                                       data-onchange="updateBulkActions">
                                <div>
                                    <div class="mobile-card-title"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                    <small style="color: #999;"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-body">
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Fecha Orden:</span>
                                <span class="mobile-card-value"><?php echo date('d/m/Y H:i', strtotime($order['date'])); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Archivado:</span>
                                <span class="mobile-card-value">
                                    <?php
                                    $archived_date = $order['archived_date'] ?? null;
                                    echo $archived_date ? date('d/m/Y H:i', strtotime($archived_date)) : 'N/A';
                                    ?>
                                </span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Total:</span>
                                <span class="mobile-card-value"><strong><?php echo format_price($order['total']); ?></strong></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Estado:</span>
                                <span class="mobile-card-value">
                                    <span class="badge <?php echo $order['status']; ?>">
                                        <?php
                                            $status_labels = [
                                                'impago' => 'Impago',
                                                'pagado' => 'Pagado',
                                                'lista_retiro' => 'Lista para Retiro',
                                                'en_transito' => 'En Tránsito',
                                                'en_reparto' => 'En Reparto',
                                                'entregada' => 'Entregada',
                                                'fallida' => 'Fallida',
                                                'devuelta' => 'Devuelta',
                                                'cancelada' => 'Cancelada',
                                                'rechazada' => 'Rechazada'
                                            ];
                                            echo $status_labels[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="mobile-card-actions">
                            <button type="button" class="btn btn-primary btn-sm"
                                    data-action="viewOrder" data-order-id="<?php echo $order['id']; ?>">
                                Ver Detalles
                            </button>
                            <button type="button" class="btn btn-warning btn-sm"
                                    data-action="confirmRestoreOrder" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                Restaurar
                            </button>
                            <button type="button" class="btn btn-danger btn-sm"
                                    data-action="confirmDeleteOrder" data-order-id="<?php echo $order['id']; ?>" data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>">
                                Eliminar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast para notificaciones -->
    <div class="toast" id="toast"></div>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
        // CSRF Token for API requests
        const token = '<?php echo $csrf_token; ?>';

        /**
         * Ver detalles de orden en modal
         */
        function viewOrder(orderId) {
            // Fetch order details from archived orders
            fetch('<?php echo url('/api/?endpoint=get-archived-order'); ?>&id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        let itemsHtml = '<table style="width: 100%; margin-top: 10px; border-collapse: collapse;">';
                        itemsHtml += '<thead><tr style="background: #f8f9fa;"><th style="padding: 8px; text-align: left;">Producto</th><th style="padding: 8px; text-align: center;">Cant.</th><th style="padding: 8px; text-align: right;">Precio</th></tr></thead><tbody>';

                        order.items.forEach(item => {
                            itemsHtml += `<tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 8px;">${item.name}</td>
                                <td style="padding: 8px; text-align: center;">${item.quantity}</td>
                                <td style="padding: 8px; text-align: right;">${item.price_formatted || item.price}</td>
                            </tr>`;
                        });

                        itemsHtml += '</tbody></table>';

                        // Format shipping address if it exists
                        let addressText = '';
                        if (order.shipping_address) {
                            if (typeof order.shipping_address === 'object') {
                                const addr = order.shipping_address;
                                const street = addr.street || addr.address || '';
                                const province = addr.province || addr.state || '';
                                const parts = [];
                                if (street) parts.push(street);
                                if (addr.city) parts.push(addr.city);
                                if (province) parts.push(province);
                                if (addr.postal_code) parts.push(`(${addr.postal_code})`);
                                addressText = parts.join(', ');
                            } else {
                                addressText = order.shipping_address;
                            }
                        }

                        // Format shipping information
                        let shippingHtml = '';
                        if (order.shipping) {
                            const shipping = order.shipping;
                            shippingHtml = '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">';
                            shippingHtml += '<h4 style="margin: 0 0 10px 0; color: #667eea;">📦 Información de Envío</h4>';

                            if (shipping.carrier) {
                                shippingHtml += `<p><strong>Carrier:</strong> ${shipping.carrier} - ${shipping.service_name || 'N/A'}</p>`;
                            }
                            if (shipping.carrier_shipment_id) {
                                shippingHtml += `<p><strong>ID de Envío:</strong> ${shipping.carrier_shipment_id}</p>`;
                            }
                            if (shipping.tracking_id) {
                                shippingHtml += `<p><strong>Tracking ID:</strong> ${shipping.tracking_id}</p>`;
                            }
                            if (shipping.status) {
                                const statusLabels = {
                                    'pendiente': '⏳ Pendiente',
                                    'en_transito': '🚚 En Tránsito',
                                    'en_reparto': '🚴 En Reparto',
                                    'entregada': '📦 Entregada',
                                    'cancelada': '❌ Cancelada',
                                    'rechazada': '⛔ Rechazada',
                                    'devuelta': '↩️ Devuelta',
                                    'fallida': '⚠️ Fallida'
                                };
                                shippingHtml += `<p><strong>Estado de Envío:</strong> ${statusLabels[shipping.status] || shipping.status}</p>`;
                            }
                            if (shipping.cost) {
                                shippingHtml += `<p><strong>Costo de Envío:</strong> $${shipping.cost}</p>`;
                            }
                            if (shipping.estimated_delivery) {
                                shippingHtml += `<p><strong>Entrega Estimada:</strong> ${shipping.estimated_delivery} días</p>`;
                            }
                            if (shipping.label_url || shipping.carrier_shipment_id) {
                                shippingHtml += '<hr style="margin: 10px 0;">';
                                shippingHtml += '<button data-action="printShippingLabel" data-order-id="' + order.id + '" ';
                                if (shipping.carrier_shipment_id) {
                                    shippingHtml += 'data-shipment-id="' + shipping.carrier_shipment_id + '" ';
                                }
                                shippingHtml += 'class="btn btn-sm btn-primary" style="background: #28a745; width: 100%;">🖨️ Ver/Imprimir Etiqueta</button>';
                            }

                            shippingHtml += '</div>';
                        }

                        let detailsHtml = `
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <p><strong>Nº Orden:</strong> ${order.order_number}</p>
                                    <p><strong>Cliente:</strong> ${order.customer_name || 'N/A'}</p>
                                    <p><strong>Email:</strong> ${order.customer_email || 'N/A'}</p>
                                    <p><strong>Teléfono:</strong> ${order.customer_phone || 'N/A'}</p>
                                </div>
                                <div>
                                    <p><strong>Estado:</strong> <span style="background: #999; color: white; padding: 4px 8px; border-radius: 4px;">${order.status}</span></p>
                                    <p><strong>Fecha Orden:</strong> ${order.date ? new Date(order.date).toLocaleString('es-AR') : 'N/A'}</p>
                                    ${order.archived_date ? `<p><strong>Fecha Archivo:</strong> ${new Date(order.archived_date).toLocaleString('es-AR')}</p>` : ''}
                                    <p><strong>Método de Entrega:</strong> ${order.delivery_method === 'pickup' ? '🏪 Retiro en Local' : '🚚 Envío a Domicilio'}</p>
                                    <p><strong>Total:</strong> <span style="font-size: 1.2em; color: #28a745;">${order.total_formatted || order.total}</span></p>
                                </div>
                            </div>
                            ${addressText ? `<p style="background: #f0f0f0; padding: 10px; border-radius: 6px;"><strong>📍 Dirección de Envío:</strong><br>${addressText}</p>` : ''}
                            ${order.notes ? `<p style="background: #fff3cd; padding: 10px; border-radius: 6px;"><strong>📝 Notas:</strong><br>${order.notes}</p>` : ''}
                            ${shippingHtml}
                            <hr style="margin: 15px 0;">
                            <h4 style="margin-bottom: 10px;">🛍️ Productos del Pedido</h4>
                            ${itemsHtml}
                        `;

                        showModal({
                            title: 'Detalles de Envío Archivado',
                            message: 'Información completa del envío archivado',
                            details: detailsHtml,
                            icon: '📦',
                            iconClass: 'info',
                            confirmText: 'Cerrar',
                            confirmType: 'primary',
                            cancelText: null,
                            onConfirm: function() {}
                        });
                    } else {
                        showModal({
                            title: 'Error',
                            message: 'No se pudo cargar la información del envío.',
                            icon: '❌',
                            iconClass: 'danger',
                            confirmText: 'Cerrar',
                            confirmType: 'danger',
                            cancelText: null,
                            onConfirm: function() {}
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showModal({
                        title: 'Error',
                        message: 'Error de conexión al cargar el envío.',
                        icon: '⚠️',
                        iconClass: 'warning',
                        confirmText: 'Cerrar',
                        confirmType: 'warning',
                        cancelText: null,
                        onConfirm: function() {}
                    });
                });
        }

        /**
         * Confirmar restauración de envío
         */
        function confirmRestoreOrder(orderId, orderNumber) {
            showModal({
                title: 'Restaurar Envío',
                message: `¿Estás seguro de que deseas restaurar "${orderNumber}"?`,
                details: 'El envío volverá a la lista principal de envíos pendientes.',
                icon: '↩️',
                iconClass: 'warning',
                confirmText: 'Restaurar',
                confirmType: 'warning',
                onConfirm: function() {
                    window.location.href = `?action=restore&id=${orderId}`;
                }
            });
        }

        /**
         * Confirmar eliminación permanente de envío
         */
        function confirmDeleteOrder(orderId, orderNumber) {
            showModal({
                title: 'Eliminar Envío Permanentemente',
                message: `¿Estás seguro de que deseas eliminar "${orderNumber}"?`,
                details: 'Esta acción no se puede deshacer. El envío será eliminado permanentemente del sistema.',
                icon: '🗑️',
                iconClass: 'danger',
                confirmText: 'Eliminar Permanentemente',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=delete&id=${orderId}`;
                }
            });
        }

        /**
         * Confirmar acción masiva
         */
        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const action = document.getElementById('bulkAction').value;
            const count = checkboxes.length;

            // Validaciones
            if (count === 0) {
                showModal({
                    title: 'Sin Envíos Seleccionados',
                    message: 'Debes seleccionar al menos un envío para realizar una acción masiva.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
                return;
            }

            if (!action) {
                showModal({
                    title: 'Acción No Seleccionada',
                    message: 'Debes seleccionar una acción para aplicar a los envíos seleccionados.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
                return;
            }

            // Configurar modal según la acción
            let title, message, icon, iconClass, confirmType;

            if (action === 'restore') {
                title = 'Restaurar Envíos';
                message = `¿Restaurar ${count} envío${count > 1 ? 's' : ''}?`;
                icon = '↩️';
                iconClass = 'warning';
                confirmType = 'warning';
            } else if (action === 'delete') {
                title = 'Eliminar Envíos Permanentemente';
                message = `¿Eliminar permanentemente ${count} envío${count > 1 ? 's' : ''}?`;
                icon = '🗑️';
                iconClass = 'danger';
                confirmType = 'danger';
            }

            showModal({
                title: title,
                message: message,
                details: `Esta acción se aplicará a ${count} envío${count > 1 ? 's seleccionados' : ' seleccionado'}.`,
                icon: icon,
                iconClass: iconClass,
                confirmText: 'Confirmar',
                confirmType: confirmType,
                onConfirm: function() {
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        // Handle checkbox selection for bulk actions
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBar.classList.add('show');
                selectedCount.textContent = `${count} envío${count > 1 ? 's' : ''} seleccionado${count > 1 ? 's' : ''}`;
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

            const _viewOrder = viewOrder;
            window.viewOrder = function(eventOrId, element, params) {
                const id = params?.orderId || (typeof eventOrId === 'string' ? eventOrId : null);
                if (id) return _viewOrder(id);
            };

            const _confirmRestoreOrder = confirmRestoreOrder;
            window.confirmRestoreOrder = function(eventOrId, element, params) {
                const id = params?.orderId || (typeof eventOrId === 'string' ? eventOrId : null);
                const orderNumber = params?.orderNumber || (typeof element === 'string' ? element : '');
                if (id) return _confirmRestoreOrder(id, orderNumber);
            };

            const _confirmDeleteOrder = confirmDeleteOrder;
            window.confirmDeleteOrder = function(eventOrId, element, params) {
                const id = params?.orderId || (typeof eventOrId === 'string' ? eventOrId : null);
                const orderNumber = params?.orderNumber || (typeof element === 'string' ? element : '');
                if (id) return _confirmDeleteOrder(id, orderNumber);
            };
        })();

        /**
         * Muestra una notificación toast
         */
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            if (!toast) return;

            toast.textContent = message;
            toast.className = 'toast show ' + type;

            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        /**
         * Solicita e imprime la etiqueta de envío
         */
        function printShippingLabel(event, element, params) {
            const orderId = params?.orderId;
            const shipmentId = params?.shipmentId;

            if (!orderId && !shipmentId) {
                showToast('⚠️ Error: No se pudo identificar el envío', 'warning');
                return;
            }

            // Disable button
            if (element) {
                element.disabled = true;
                element.textContent = '⏳ Obteniendo...';
            }

            // Build API URL
            const apiUrl = '<?php echo url('/api/?endpoint=print-shipping-label'); ?>' +
                          (orderId ? '&order_id=' + encodeURIComponent(orderId) : '') +
                          (shipmentId ? '&shipment_id=' + encodeURIComponent(shipmentId) : '') +
                          '&format=pdf&action=download';

            // Usar fetch para detectar errores antes de abrir ventana
            fetch(apiUrl, {
                method: 'HEAD',
                credentials: 'same-origin'
            })
            .then(response => {
                if (response.ok) {
                    // Todo bien, abrir PDF en nueva ventana
                    window.open(apiUrl, '_blank');
                    showToast('✅ Abriendo etiqueta...', 'success');
                } else if (response.status === 409) {
                    showToast('⏳ La etiqueta se está generando. Espera unos segundos y vuelve a intentar.', 'warning');
                } else if (response.status === 404) {
                    showToast('❌ Etiqueta no encontrada. Verifica que el envío esté creado.', 'error');
                } else {
                    showToast('❌ Error al obtener la etiqueta. Código: ' + response.status, 'error');
                }
            })
            .catch(error => {
                console.error('Error checking label:', error);
                showToast('❌ Error de conexión al obtener la etiqueta', 'error');
            })
            .finally(() => {
                // Restore button
                if (element) {
                    element.disabled = false;
                    element.textContent = '🖨️ Etiqueta';
                }
            });
        }

        // Export for event delegation
        window.printShippingLabel = printShippingLabel;
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
