<?php
/**
 * Admin - Products List
 *
 * Cargado por: public_html/admin/index.php vía Router (?page=productos-listado)
 * Bootstrap ya maneja: APP_ENTRY_POINT, includes, session, security headers
 */

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Gestión de Productos';
$currency_config = read_json(APP_PATH . '/config/currency.json');

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'product_updated') {
        $message = 'Producto actualizado exitosamente';
    } elseif ($_GET['msg'] === 'product_created') {
        $message = 'Producto creado exitosamente';
    } elseif ($_GET['msg'] === 'product_archived') {
        $message = 'Producto archivado exitosamente';
    }
}

// Archive product
if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    if (archive_product($product_id)) {
        $message = 'Producto archivado exitosamente';
        log_admin_action('product_archived', $_SESSION['username'], ['product_id' => $product_id]);
    } else {
        $error = 'Error al archivar el producto';
    }
}

// Toggle product active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $product = get_product_by_id($product_id);

    if ($product) {
        $new_status = !$product['active'];
        $updated = update_product($product_id, ['active' => $new_status]);

        if ($updated['success']) {
            $message = $new_status ? 'Producto activado' : 'Producto desactivado';
            log_admin_action('product_toggled', $_SESSION['username'], [
                'product_id' => $product_id,
                'new_status' => $new_status
            ]);
        }
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_products = $_POST['selected_products'] ?? [];

    if (!empty($selected_products)) {
        $success_count = 0;
        foreach ($selected_products as $product_id) {
            if ($action === 'archive') {
                if (archive_product($product_id)) {
                    $success_count++;
                }
            } elseif ($action === 'activate') {
                if (update_product($product_id, ['active' => true])) {
                    $success_count++;
                }
            } elseif ($action === 'deactivate') {
                if (update_product($product_id, ['active' => false])) {
                    $success_count++;
                }
            }
        }

        $message = "$success_count producto(s) procesado(s) exitosamente";
        log_admin_action('bulk_products_action', $_SESSION['username'], [
            'action' => $action,
            'count' => $success_count
        ]);
    } else {
        $error = 'No se seleccionaron productos';
    }
}

// Get all products
$all_products = get_all_products(false); // Include inactive

// Apply filters
$filter_status = $_GET['filter'] ?? 'all';
$filter_stock = $_GET['stock'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Apply status filter
if ($filter_status === 'all') {
    $products = $all_products;
} elseif ($filter_status === 'active') {
    $products = array_filter($all_products, fn($p) => $p['active']);
} elseif ($filter_status === 'inactive') {
    $products = array_filter($all_products, fn($p) => !$p['active']);
} else {
    $products = $all_products;
}

// Apply stock filter
if ($filter_stock === 'in_stock') {
    $products = array_filter($products, fn($p) => $p['stock'] > 0);
} elseif ($filter_stock === 'out_of_stock') {
    $products = array_filter($products, fn($p) => $p['stock'] === 0);
} elseif ($filter_stock === 'low_stock') {
    $products = array_filter($products, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_alert']);
}

// Apply search filter
if (!empty($search_query)) {
    $products = array_filter($products, function($product) use ($search_query) {
        $search_lower = mb_strtolower($search_query);
        return stripos($product['id'], $search_query) !== false ||
               stripos(mb_strtolower($product['name']), $search_lower) !== false;
    });
}

// Sort products by display_order (lowest first), fallback to created_at for products without display_order
usort($products, function($a, $b) {
    $order_a = $a['display_order'] ?? 9999;
    $order_b = $b['display_order'] ?? 9999;

    if ($order_a === $order_b) {
        // If same order, sort by created_at (newest first)
        return strtotime($b['created_at'] ?? '2000-01-01') - strtotime($a['created_at'] ?? '2000-01-01');
    }

    return $order_a - $order_b;
});

// Calculate stats
$total_products = count($all_products);
$active_products = count(array_filter($all_products, fn($p) => $p['active']));
$out_of_stock = count(array_filter($all_products, fn($p) => $p['stock'] === 0));
$low_stock = count(array_filter($all_products, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_alert']));

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Productos - Admin</title>

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
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th,
        .products-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .products-table td {
            font-size: 14px;
        }

        .products-table tbody tr:hover {
            background: #f8f9fa;
        }

        .product-thumbnail {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
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

        .badge.low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .badge.no-stock {
            background: #f8d7da;
            color: #721c24;
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
            grid-template-columns: 1fr 1fr 1fr auto;
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
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4CAF50;
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
                padding: 15px;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .products-table {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .products-table {
                font-size: 12px;
                min-width: 800px;
            }

            .products-table th,
            .products-table td {
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

            .mobile-card-checkbox {
                margin-right: 10px;
            }

            .mobile-card-thumbnail {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 4px;
                margin-right: 12px;
            }
        }
        /* Drag Handle */
        .drag-handle {
            cursor: grab;
            font-size: 20px;
            color: #999;
            user-select: none;
            padding: 5px;
            display: inline-block;
            transition: all 0.2s;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .drag-handle:hover {
            color: #4CAF50;
            background: #f0f8ff;
            border-radius: 4px;
            transform: scale(1.1);
        }

        .sortable-ghost {
            opacity: 0.4;
            background: #e3f2fd !important;
            border: 2px dashed #4CAF50 !important;
        }

        .sortable-chosen {
            background: #f0f8ff;
        }
    </style>

    <!-- SortableJS Local -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/Sortable.min.js'); ?>"></script>
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
                    <a href="<?php echo url('/admin/?page=productos-nuevo'); ?>" class="btn btn-primary">
                        ➕ Nuevo Producto
                    </a>
                    <a href="<?php echo url('/admin/?page=productos-archivados'); ?>" class="btn btn-secondary">
                        📦 Ver Archivados
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_products; ?></div>
                    <div class="stat-label">Total Productos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $active_products; ?></div>
                    <div class="stat-label">Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $out_of_stock; ?></div>
                    <div class="stat-label">Sin Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $low_stock; ?></div>
                    <div class="stat-label">Stock Bajo</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Buscar</label>
                            <input type="text" id="search" name="search"
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   placeholder="Nombre o ID del producto">
                        </div>

                        <div class="filter-group">
                            <label for="filter">Estado</label>
                            <select id="filter" name="filter">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Activos</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="stock">Stock</label>
                            <select id="stock" name="stock">
                                <option value="all" <?php echo $filter_stock === 'all' ? 'selected' : ''; ?>>Todos</option>
                                <option value="in_stock" <?php echo $filter_stock === 'in_stock' ? 'selected' : ''; ?>>Con Stock</option>
                                <option value="out_of_stock" <?php echo $filter_stock === 'out_of_stock' ? 'selected' : ''; ?>>Sin Stock</option>
                                <option value="low_stock" <?php echo $filter_stock === 'low_stock' ? 'selected' : ''; ?>>Stock Bajo</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions Bar -->
            <form method="POST" id="bulkForm">
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <span id="selectedCount">0 productos seleccionados</span>
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

                <!-- Products List -->
                <div class="card">
                    <div class="card-header">
                        <?php if (empty($products)): ?>
                            Todos los Productos
                        <?php else: ?>
                            Mostrando <?php echo count($products); ?> de <?php echo $total_products; ?> productos
                        <?php endif; ?>
                    </div>

                    <div class="table-container">
                        <table class="products-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" data-onchange="toggleSelectAll">
                                </th>
                                <th style="width: 30px;"></th>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Peso/Dim</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                        No hay productos que coincidan con los filtros.
                                        <a href="<?php echo url('/admin/?page=productos-nuevo'); ?>" style="color: #4CAF50;">Crear nuevo producto</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr data-id="<?php echo htmlspecialchars($product['id']); ?>">
                                        <td>
                                            <input type="checkbox" name="selected_products[]"
                                                   value="<?php echo htmlspecialchars($product['id']); ?>"
                                                   class="product-checkbox"
                                                   data-onchange="updateBulkActions">
                                        </td>
                                        <td>
                                            <span class="drag-handle">⋮⋮</span>
                                        </td>
                                        <td>
                                            <img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="product-thumbnail">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                            <small style="color: #999;">ID: <?php echo htmlspecialchars($product['id']); ?></small>
                                        </td>
                                        <td><?php echo format_product_price($product, 'ARS'); ?></td>
                                        <td>
                                            <?php echo $product['stock']; ?>
                                            <?php if ($product['stock'] === 0): ?>
                                                <span class="badge no-stock">Sin Stock</span>
                                            <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                                                <span class="badge low-stock">Bajo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.85rem; color: #666;">
                                            <?php
                                            $weight = $product['weight'] ?? 500;
                                            $length = $product['dimensions']['length'] ?? 20;
                                            $width = $product['dimensions']['width'] ?? 15;
                                            $height = $product['dimensions']['height'] ?? 10;
                                            echo number_format($weight, 0) . 'g<br>';
                                            echo number_format($length, 1) . '×' . number_format($width, 1) . '×' . number_format($height, 1) . 'cm';
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $product['active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $product['active'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="<?php echo url('/admin/?page=productos-editar&id=' . urlencode($product['id'])); ?>"
                                                   class="btn btn-primary btn-sm">✏️ Editar</a>
                                                <button type="button" class="btn btn-secondary btn-sm"
                                                   data-action="confirmToggleProduct" data-product-id="<?php echo urlencode($product['id']); ?>" data-is-active="<?php echo $product['active'] ? 'true' : 'false'; ?>">
                                                    <?php echo $product['active'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                        data-action="confirmArchiveProduct" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                    📦 Archivar
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
                <?php if (empty($products)): ?>
                    <div class="card">
                        <p style="text-align: center; color: #999; padding: 20px;">
                            No hay productos que coincidan con los filtros.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="mobile-card">
                            <div class="mobile-card-header">
                                <div style="display: flex; align-items: center; flex: 1;">
                                    <input type="checkbox" name="selected_products[]"
                                           value="<?php echo htmlspecialchars($product['id']); ?>"
                                           class="product-checkbox mobile-card-checkbox"
                                           form="bulkForm"
                                           data-onchange="updateBulkActions">
                                    <img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="mobile-card-thumbnail">
                                    <div>
                                        <div class="mobile-card-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <small style="color: #999;">ID: <?php echo htmlspecialchars($product['id']); ?></small>
                                    </div>
                                </div>
                            </div>

                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Precio:</span>
                                    <span class="mobile-card-value"><strong><?php echo format_product_price($product, 'ARS'); ?></strong></span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Stock:</span>
                                    <span class="mobile-card-value">
                                        <?php echo $product['stock']; ?>
                                        <?php if ($product['stock'] === 0): ?>
                                            <span class="badge no-stock">Sin Stock</span>
                                        <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                                            <span class="badge low-stock">Bajo</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Estado:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $product['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $product['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="mobile-card-actions">
                                <a href="<?php echo url('/admin/?page=productos-editar&id=' . urlencode($product['id'])); ?>"
                                   class="btn btn-primary btn-sm">Editar</a>
                                <button type="button" class="btn btn-secondary btn-sm"
                                   data-action="confirmToggleProduct" data-product-id="<?php echo urlencode($product['id']); ?>" data-is-active="<?php echo $product['active'] ? 'true' : 'false'; ?>">
                                    <?php echo $product['active'] ? 'Desactivar' : 'Activar'; ?>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        data-action="confirmArchiveProduct" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                    Archivar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
        /**
         * Confirmar cambio de estado de producto (Activar/Desactivar)
         */
        function confirmToggleProduct(productId, isActive) {
            const action = isActive ? 'desactivar' : 'activar';
            const actionCap = isActive ? 'Desactivar' : 'Activar';

            showModal({
                title: `${actionCap} Producto`,
                message: `¿Estás seguro de que deseas ${action} este producto?`,
                details: isActive
                    ? 'El producto dejará de mostrarse en el catálogo público.'
                    : 'El producto volverá a mostrarse en el catálogo público.',
                icon: isActive ? '❌' : '✅',
                iconClass: isActive ? 'warning' : 'success',
                confirmText: actionCap,
                confirmType: isActive ? 'warning' : 'primary',
                onConfirm: function() {
                    window.location.href = `?action=toggle&id=${productId}`;
                }
            });
        }

        /**
         * Confirmar archivo de producto
         */
        function confirmArchiveProduct(productId, productName) {
            showModal({
                title: 'Archivar Producto',
                message: `¿Estás seguro de que deseas archivar "${productName}"?`,
                details: 'El producto se moverá al archivo y no aparecerá en el listado principal. Podrás restaurarlo desde la sección de Productos Archivados.',
                icon: '📦',
                iconClass: 'warning',
                confirmText: 'Archivar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=archive&id=${productId}`;
                }
            });
        }

        /**
         * Confirmar acción masiva
         */
        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const action = document.getElementById('bulkAction').value;
            const count = checkboxes.length;

            // Validaciones
            if (count === 0) {
                showModal({
                    title: 'Sin Productos Seleccionados',
                    message: 'Debes seleccionar al menos un producto para realizar una acción masiva.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: 'Cerrar',
                    onConfirm: function() {}
                });
                return;
            }

            if (!action) {
                showModal({
                    title: 'Acción No Seleccionada',
                    message: 'Debes seleccionar una acción para aplicar a los productos seleccionados.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: 'Cerrar',
                    onConfirm: function() {}
                });
                return;
            }

            // Configurar modal según la acción
            let title, message, icon, iconClass, confirmType;

            if (action === 'activate') {
                title = 'Activar Productos';
                message = `¿Activar ${count} producto${count > 1 ? 's' : ''}?`;
                icon = '✅';
                iconClass = 'success';
                confirmType = 'primary';
            } else if (action === 'deactivate') {
                title = 'Desactivar Productos';
                message = `¿Desactivar ${count} producto${count > 1 ? 's' : ''}?`;
                icon = '❌';
                iconClass = 'warning';
                confirmType = 'warning';
            } else if (action === 'archive') {
                title = 'Archivar Productos';
                message = `¿Archivar ${count} producto${count > 1 ? 's' : ''}?`;
                icon = '📦';
                iconClass = 'danger';
                confirmType = 'danger';
            }

            showModal({
                title: title,
                message: message,
                details: `Esta acción se aplicará a ${count} producto${count > 1 ? 's seleccionados' : ' seleccionado'}.`,
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
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBar.classList.add('show');
                selectedCount.textContent = `${count} producto${count > 1 ? 's' : ''} seleccionado${count > 1 ? 's' : ''}`;
            } else {
                bulkBar.classList.remove('show');
                selectAll.checked = false;
            }
        }

        // Initialize SortableJS for drag and drop reordering
        document.addEventListener('DOMContentLoaded', function() {
            const tbody = document.querySelector('.products-table tbody');

            // Check if SortableJS library is loaded
            if (typeof Sortable === 'undefined') {
                console.error('SortableJS library not loaded!');
                return;
            }

            if (tbody && tbody.children.length > 0) {  // Initialize if there are products
                console.log('Initializing SortableJS with', tbody.children.length, 'rows');

                const sortable = new Sortable(tbody, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onStart: function(evt) {
                        console.log('Drag started');
                    },
                    onEnd: function(evt) {
                        console.log('Drag ended - saving new order...');

                        // Get the new order of product IDs
                        const rows = tbody.querySelectorAll('tr[data-id]');
                        const productOrder = Array.from(rows).map(row => row.getAttribute('data-id'));

                        // Save the new order via AJAX
                        fetch('<?php echo url('/api/?endpoint=update-products-order'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            credentials: 'same-origin',  // Include session cookies
                            body: JSON.stringify({
                                product_order: productOrder
                            })
                        })
                        .then(response => {
                            // Check if response is OK before parsing JSON
                            if (!response.ok) {
                                return response.text().then(text => {
                                    throw new Error(`HTTP ${response.status}: ${text}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                console.log('Orden actualizado:', data.message);
                                // Show success notification
                                showModal({
                                    title: 'Orden Actualizado',
                                    message: 'El orden de los productos se ha actualizado exitosamente.',
                                    icon: '✅',
                                    iconClass: 'success',
                                    confirmText: 'Entendido',
                                    confirmType: 'primary',
                                    cancelText: null,
                                    onConfirm: function() {}
                                });
                            } else {
                                console.error('Error al actualizar el orden:', data.message);
                                showModal({
                                    title: 'Error al Guardar',
                                    message: 'No se pudo guardar el nuevo orden de productos.',
                                    details: data.message || 'Error desconocido. La página se recargará para restaurar el orden original.',
                                    icon: '❌',
                                    iconClass: 'danger',
                                    confirmText: 'Recargar Página',
                                    confirmType: 'danger',
                                    cancelText: null,
                                    onConfirm: function() {
                                        location.reload();
                                    }
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error completo:', error);
                            showModal({
                                title: 'Error de Conexión',
                                message: 'No se pudo comunicar con el servidor para guardar el nuevo orden.',
                                details: error.message || 'La página se recargará para restaurar el orden original.',
                                icon: '⚠️',
                                iconClass: 'warning',
                                confirmText: 'Recargar Página',
                                confirmType: 'warning',
                                cancelText: null,
                                onConfirm: function() {
                                    location.reload();
                                }
                            });
                        });
                    }
                });
            }
        });

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

            const _confirmToggleProduct = confirmToggleProduct;
            window.confirmToggleProduct = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                const isActive = params?.isActive === 'true';
                if (id) return _confirmToggleProduct(id, isActive);
            };

            const _confirmArchiveProduct = confirmArchiveProduct;
            window.confirmArchiveProduct = function(eventOrId, element, params) {
                const id = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
                const name = params?.productName || '';
                if (id) return _confirmArchiveProduct(id, name);
            };
        })();
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
