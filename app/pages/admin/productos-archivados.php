<?php
/**
 * Admin - Archived Products List 
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Productos Archivados';
$currency_config = read_json(APP_PATH . '/config/currency.json');

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'product_restored') {
        $message = 'Producto restaurado exitosamente';
    } elseif ($_GET['msg'] === 'product_deleted') {
        $message = 'Producto eliminado permanentemente';
    }
}

// Unarchive product (restore)
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    if (unarchive_product($product_id)) {
        $message = 'Producto restaurado exitosamente';
        log_admin_action('product_restored', $_SESSION['username'], ['product_id' => $product_id]);
    } else {
        $error = 'Error al restaurar el producto';
    }
}

// Delete permanently
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    $result = delete_archived_product($product_id);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_products = $_POST['selected_products'] ?? [];

    if (!empty($selected_products)) {
        $success_count = 0;
        $error_count = 0;

        foreach ($selected_products as $product_id) {
            if ($action === 'restore') {
                if (unarchive_product($product_id)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } elseif ($action === 'delete') {
                $result = delete_archived_product($product_id);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }

        if ($success_count > 0) {
            $action_text = $action === 'restore' ? 'restaurado(s)' : 'eliminado(s)';
            $message = "$success_count producto(s) $action_text exitosamente";

            if ($error_count > 0) {
                $message .= " ($error_count fallaron)";
            }

            log_admin_action('bulk_archived_products_action', $_SESSION['username'], [
                'action' => $action,
                'success_count' => $success_count,
                'error_count' => $error_count
            ]);
        } else {
            $error = 'No se pudieron procesar los productos seleccionados';
        }
    } else {
        $error = 'No se seleccionaron productos';
    }
}

// Get all archived products
$archived_products = get_archived_products();

// Sort by archived date (newest first)
usort($archived_products, function($a, $b) {
    return strtotime($b['archived_date'] ?? '2000-01-01') - strtotime($a['archived_date'] ?? '2000-01-01');
});

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Archivados - Admin</title>

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

        .badge.archived {
            background: #e2e3e5;
            color: #383d41;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Warning Box */
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .warning-box strong {
            color: #856404;
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
            .products-table {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
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
            }

            .actions .btn {
                width: 100%;
            }

            .bulk-actions-bar {
                flex-direction: column;
                gap: 8px;
            }

            .bulk-actions-bar select,
            .bulk-actions-bar .btn {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 8px;
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
                    <a href="<?php echo url('/admin/?page=productos-listado'); ?>" class="btn btn-secondary">
                        ← Volver a Productos
                    </a>
                </div>
            </div>

            <!-- Warning -->
            <?php if (!empty($archived_products)): ?>
            <div class="warning-box">
                <strong>⚠️ Productos Archivados:</strong> Estos productos han sido archivados y no aparecen en el sitio público. Puedes restaurarlos o eliminarlos permanentemente.
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($archived_products); ?></div>
                    <div class="stat-label">Productos Archivados</div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <form method="POST" id="bulkForm">
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <span id="selectedCount">0 productos seleccionados</span>
                    <select name="bulk_action" id="bulkAction">
                        <option value="">Seleccionar acción...</option>
                        <option value="restore">Restaurar</option>
                        <option value="delete">Eliminar Permanentemente</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" data-action="confirmBulkAction">
                        Aplicar
                    </button>
                </div>

                <!-- Archived Products List -->
                <div class="card">
                    <div class="card-header">Productos Archivados</div>

                    <div class="table-container">
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" data-onchange="toggleSelectAll">
                                    </th>
                                    <th>Imagen</th>
                                    <th>Nombre</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Peso/Dim</th>
                                    <th>Archivado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archived_products)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                            No hay productos archivados.
                                            <a href="<?php echo url('/admin/?page=productos-listado'); ?>" style="color: #4CAF50;">Ir a productos activos</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($archived_products as $product): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_products[]"
                                                       value="<?php echo htmlspecialchars($product['id']); ?>"
                                                       class="product-checkbox"
                                                       data-onchange="updateBulkActions">
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
                                            <td><?php echo $product['stock']; ?></td>
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
                                                <span class="badge archived">Archivado</span><br>
                                                <small style="color: #999;">
                                                    <?php
                                                    $date = new DateTime($product['archived_date'] ?? 'now');
                                                    echo $date->format('d/m/Y H:i');
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="actions">
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                            data-action="confirmRestoreProduct"
                                                            data-product-id="<?php echo urlencode($product['id']); ?>"
                                                            data-product-name="<?php echo htmlspecialchars(addslashes($product['name'])); ?>">
                                                        ↩️ Restaurar
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                            data-action="confirmDeleteProduct"
                                                            data-product-id="<?php echo urlencode($product['id']); ?>"
                                                            data-product-name="<?php echo htmlspecialchars(addslashes($product['name'])); ?>">
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
                <?php if (empty($archived_products)): ?>
                    <div class="card">
                        <p style="text-align: center; color: #999; padding: 20px;">
                            No hay productos archivados.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($archived_products as $product): ?>
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
                                    <span class="mobile-card-value"><?php echo $product['stock']; ?></span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Archivado:</span>
                                    <span class="mobile-card-value">
                                        <?php
                                        $date = new DateTime($product['archived_date'] ?? 'now');
                                        echo $date->format('d/m/Y H:i');
                                        ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Estado:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge archived">Archivado</span>
                                    </span>
                                </div>
                            </div>

                            <div class="mobile-card-actions">
                                <button type="button" class="btn btn-primary btn-sm"
                                        data-action="confirmRestoreProduct"
                                        data-product-id="<?php echo urlencode($product['id']); ?>"
                                        data-product-name="<?php echo htmlspecialchars(addslashes($product['name'])); ?>">
                                    Restaurar
                                </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        data-action="confirmDeleteProduct"
                                        data-product-id="<?php echo urlencode($product['id']); ?>"
                                        data-product-name="<?php echo htmlspecialchars(addslashes($product['name'])); ?>">
                                    Eliminar
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
         * Confirmar restauración de producto
         */
        function confirmRestoreProduct(productId, productName) {
            showModal({
                title: 'Restaurar Producto',
                message: `¿Estás seguro de que deseas restaurar "${productName}"?`,
                details: 'El producto volverá al listado principal de productos activos y estará disponible en el catálogo público.',
                icon: '↩️',
                iconClass: 'info',
                confirmText: 'Restaurar',
                confirmType: 'primary',
                onConfirm: function() {
                    window.location.href = `?action=restore&id=${productId}`;
                }
            });
        }

        /**
         * Confirmar eliminación permanente de producto
         */
        function confirmDeleteProduct(productId, productName) {
            showModal({
                title: '⚠️ Eliminar Permanentemente',
                message: `¿Estás COMPLETAMENTE SEGURO de que deseas eliminar "${productName}"?`,
                details: '🚨 ADVERTENCIA: Esta acción es IRREVERSIBLE. Se eliminarán permanentemente todos los datos del producto, incluyendo imágenes y estadísticas. Esta acción NO se puede deshacer.',
                icon: '🗑️',
                iconClass: 'danger',
                confirmText: 'Sí, Eliminar Permanentemente',
                cancelText: 'No, Conservar Producto',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=delete&id=${productId}`;
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
                    iconClass: 'warning',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    onConfirm: function() {}
                });
                return;
            }

            if (!action) {
                showModal({
                    title: 'Acción No Seleccionada',
                    message: 'Debes seleccionar una acción para aplicar a los productos seleccionados.',
                    icon: '⚠️',
                    iconClass: 'warning',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    onConfirm: function() {}
                });
                return;
            }

            // Configurar modal según la acción
            let title, message, details, icon, iconClass, confirmType, confirmText;

            if (action === 'restore') {
                title = 'Restaurar Productos';
                message = `¿Restaurar ${count} producto${count > 1 ? 's' : ''}?`;
                details = `${count > 1 ? 'Los productos volverán' : 'El producto volverá'} al listado principal de productos activos y ${count > 1 ? 'estarán disponibles' : 'estará disponible'} en el catálogo público.`;
                icon = '↩️';
                iconClass = 'info';
                confirmType = 'primary';
                confirmText = 'Restaurar';
            } else if (action === 'delete') {
                title = '⚠️ Eliminar Permanentemente';
                message = `¿Estás COMPLETAMENTE SEGURO de que deseas eliminar ${count} producto${count > 1 ? 's' : ''}?`;
                details = `🚨 ADVERTENCIA: Esta acción es IRREVERSIBLE. Se eliminarán permanentemente todos los datos de ${count > 1 ? 'los productos seleccionados' : 'este producto'}, incluyendo imágenes y estadísticas. Esta acción NO se puede deshacer.`;
                icon = '🗑️';
                iconClass = 'danger';
                confirmType = 'danger';
                confirmText = 'Sí, Eliminar Permanentemente';
            }

            showModal({
                title: title,
                message: message,
                details: details,
                icon: icon,
                iconClass: iconClass,
                confirmText: confirmText,
                cancelText: 'Cancelar',
                confirmType: confirmType,
                onConfirm: function() {
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        /**
         * Toggle select all checkboxes
         */
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        /**
         * Update bulk actions bar visibility
         */
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

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: confirmRestoreProduct
         * Compatible con llamadas directas y event delegation
         */
        const _confirmRestoreProduct = confirmRestoreProduct;
        window.confirmRestoreProduct = function(eventOrId, element, params) {
            const productId = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
            const productName = params?.productName || (typeof arguments[1] === 'string' ? arguments[1] : null);
            if (productId && productName) return _confirmRestoreProduct(productId, productName);
        };

        /**
         * Wrapper: confirmDeleteProduct
         * Compatible con llamadas directas y event delegation
         */
        const _confirmDeleteProduct = confirmDeleteProduct;
        window.confirmDeleteProduct = function(eventOrId, element, params) {
            const productId = params?.productId || (typeof eventOrId === 'string' ? eventOrId : null);
            const productName = params?.productName || (typeof arguments[1] === 'string' ? arguments[1] : null);
            if (productId && productName) return _confirmDeleteProduct(productId, productName);
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

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
