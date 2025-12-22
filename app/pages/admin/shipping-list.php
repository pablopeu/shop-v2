<?php
/**
 * Admin - Gestión de Envíos con Zipnova
 * Lista y gestiona todos los envíos creados en Zipnova
 */

require_admin();
require_once APP_PATH . '/includes/zipnova.php';
require_once APP_PATH . '/includes/orders.php';

$message = '';
$error = '';
$csrf_token = generate_csrf_token();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $action = $_POST['action'] ?? '';
        $order_id = $_POST['order_id'] ?? '';

        if ($action === 'create_shipment' && $order_id) {
            $result = create_zipnova_shipment_for_order($order_id);
            if ($result['success']) {
                $message = 'Envío creado exitosamente en Zipnova';
            } else {
                $error = 'Error al crear envío: ' . ($result['error'] ?? 'Error desconocido');
            }
        } elseif ($action === 'sync_status') {
            $shipment_id = $_POST['shipment_id'] ?? '';
            if ($shipment_id) {
                $result = zipnova_get_shipment($shipment_id);
                if ($result['success']) {
                    $message = 'Estado sincronizado exitosamente';
                } else {
                    $error = 'Error al sincronizar: ' . ($result['error'] ?? 'Error desconocido');
                }
            }
        } elseif ($action === 'cancel_shipment') {
            $shipment_id = $_POST['shipment_id'] ?? '';
            if ($shipment_id) {
                $result = zipnova_cancel_shipment($shipment_id);
                if ($result['success']) {
                    $message = 'Envío cancelado exitosamente';
                } else {
                    $error = 'Error al cancelar: ' . ($result['error'] ?? 'Error desconocido');
                }
            }
        }
    }
}

// Check for messages from redirect
if (isset($_SESSION['shipping_list_message'])) {
    $message = $_SESSION['shipping_list_message'];
    unset($_SESSION['shipping_list_message']);
}

// Get all orders with shipping
$all_orders = get_all_orders();
$shipping_orders = array_filter($all_orders, function($order) {
    return isset($order['shipping']) &&
           $order['shipping'] !== null &&
           ($order['delivery_method'] ?? 'pickup') === 'shipping';
});

// Apply filters
$filter_status = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';

if ($filter_status !== 'all') {
    $shipping_orders = array_filter($shipping_orders, function($order) use ($filter_status) {
        return ($order['shipping']['status'] ?? 'pending') === $filter_status;
    });
}

if (!empty($search_query)) {
    $shipping_orders = array_filter($shipping_orders, function($order) use ($search_query) {
        return stripos($order['order_number'], $search_query) !== false ||
               stripos($order['customer_name'], $search_query) !== false ||
               stripos($order['shipping']['tracking_id'] ?? '', $search_query) !== false;
    });
}

// Sort by date (newest first)
usort($shipping_orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Stats
$total_shipments = count($shipping_orders);
$pending_count = count(array_filter($shipping_orders, fn($o) => ($o['shipping']['status'] ?? 'pending') === 'pending'));
$in_transit_count = count(array_filter($shipping_orders, fn($o) => ($o['shipping']['status'] ?? '') === 'in_transit'));
$delivered_count = count(array_filter($shipping_orders, fn($o) => ($o['shipping']['status'] ?? '') === 'delivered'));

$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Gestión de Envíos';

$status_labels = [
    'pending' => 'Pendiente',
    'in_transit' => 'En tránsito',
    'out_for_delivery' => 'En reparto',
    'delivered' => 'Entregado',
    'failed' => 'Fallido',
    'returned' => 'Devuelto',
    'cancelled' => 'Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Envíos - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1400px; }

        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { font-size: 24px; color: #333; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters select,
        .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filters input[type="search"] {
            min-width: 250px;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_transit { background: #d1ecf1; color: #0c5460; }
        .status-out_for_delivery { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-returned { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary { background: #3498db; color: white; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }

        .actions {
            display: flex;
            gap: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
        }

        @media (max-width: 768px) {
            .table-container { overflow-x: auto; }
            table { min-width: 900px; }
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

        <div class="header">
            <h1>📦 Gestión de Envíos con Zipnova</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Envíos</h3>
                <div class="value"><?php echo $total_shipments; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pendientes</h3>
                <div class="value"><?php echo $pending_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>En Tránsito</h3>
                <div class="value"><?php echo $in_transit_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>Entregados</h3>
                <div class="value"><?php echo $delivered_count; ?></div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <input type="hidden" name="page" value="shipping-list">

                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                    <option value="in_transit" <?php echo $filter_status === 'in_transit' ? 'selected' : ''; ?>>En tránsito</option>
                    <option value="out_for_delivery" <?php echo $filter_status === 'out_for_delivery' ? 'selected' : ''; ?>>En reparto</option>
                    <option value="delivered" <?php echo $filter_status === 'delivered' ? 'selected' : ''; ?>>Entregados</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelados</option>
                </select>

                <input type="search" name="search" placeholder="Buscar por orden, cliente o tracking..."
                       value="<?php echo htmlspecialchars($search_query); ?>">

                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if (!empty($search_query) || $filter_status !== 'all'): ?>
                    <a href="?page=shipping-list" class="btn btn-secondary">Limpiar filtros</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <?php if (empty($shipping_orders)): ?>
                <div class="empty-state">
                    <h3>No hay envíos para mostrar</h3>
                    <p>Los envíos aparecerán aquí cuando se procesen órdenes con envío a domicilio.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Cliente</th>
                            <th>Destino</th>
                            <th>Tracking ID</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shipping_orders as $order): ?>
                            <?php
                            $shipping = $order['shipping'];
                            $status = $shipping['status'] ?? 'pending';
                            $has_zipnova = !empty($shipping['zipnova_shipment_id']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($shipping['service_name'] ?? 'Envío estándar'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td>
                                    <?php
                                    $addr = $shipping['address'] ?? [];
                                    echo htmlspecialchars($addr['city'] ?? '');
                                    if (!empty($addr['province'])) echo ', ' . htmlspecialchars($addr['province']);
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($shipping['tracking_id'])): ?>
                                        <code><?php echo htmlspecialchars($shipping['tracking_id']); ?></code>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo $status_labels[$status] ?? $status; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($order['date'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if (!$has_zipnova): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="create_shipment">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn btn-success"
                                                        onclick="return confirm('¿Crear envío en Zipnova?')">
                                                    Crear Envío
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="sync_status">
                                                <input type="hidden" name="shipment_id" value="<?php echo $shipping['zipnova_shipment_id']; ?>">
                                                <button type="submit" class="btn btn-primary">Sincronizar</button>
                                            </form>

                                            <?php if ($status !== 'delivered' && $status !== 'cancelled'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="action" value="cancel_shipment">
                                                    <input type="hidden" name="shipment_id" value="<?php echo $shipping['zipnova_shipment_id']; ?>">
                                                    <button type="submit" class="btn btn-danger"
                                                            onclick="return confirm('¿Cancelar este envío?')">
                                                        Cancelar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <a href="<?php echo url('/admin/?page=ventas&order=' . $order['id']); ?>"
                                           class="btn btn-secondary">Ver Orden</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
