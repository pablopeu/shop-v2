<?php
/**
 * Admin - Products Management
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Productos';
$currency_config = read_json(APP_PATH . '/config/currency.json');

// Handle actions
$message = '';
$error = '';
$edit_product = null;

// Delete product
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    if (delete_product($product_id)) {
        $message = 'Producto eliminado exitosamente';
        log_admin_action('product_deleted', $_SESSION['username'], ['product_id' => $product_id]);
    } else {
        $error = 'Error al eliminar el producto';
    }
}

// Toggle product active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $product = get_product_by_id($product_id);

    if ($product) {
        $new_status = !$product['active'];
        $updated = update_product($product_id, ['active' => $new_status]);

        if ($updated) {
            $message = $new_status ? 'Producto activado' : 'Producto desactivado';
            log_admin_action('product_toggled', $_SESSION['username'], [
                'product_id' => $product_id,
                'new_status' => $new_status
            ]);
        }
    }
}

// Load product for editing
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_product = get_product_by_id($_GET['id']);
    if (!$edit_product) {
        $error = 'Producto no encontrado';
    }
}

// Handle form submission (create or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Get form data
        $product_data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'slug' => generate_slug($_POST['name'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'price_ars' => floatval($_POST['price_ars'] ?? 0),
            'price_usd' => floatval($_POST['price_usd'] ?? 0),
            'stock' => intval($_POST['stock'] ?? 0),
            'stock_alert' => intval($_POST['stock_alert'] ?? 5),
            'active' => isset($_POST['active']) ? true : false,
            'hide_when_out_of_stock' => isset($_POST['hide_when_out_of_stock']) ? true : false,
            'thumbnail' => sanitize_input($_POST['thumbnail'] ?? ''),
            'seo' => [
                'title' => sanitize_input($_POST['seo_title'] ?? ''),
                'description' => sanitize_input($_POST['seo_description'] ?? ''),
                'keywords' => sanitize_input($_POST['seo_keywords'] ?? '')
            ]
        ];

        // Validate required fields
        if (empty($product_data['name'])) {
            $error = 'El nombre es requerido';
        } elseif ($product_data['price_ars'] <= 0) {
            $error = 'El precio debe ser mayor a 0';
        } else {
            // Update or create
            $product_id = $_POST['product_id'] ?? null;

            if ($product_id) {
                // Update existing product
                if (update_product($product_id, $product_data)) {
                    $message = 'Producto actualizado exitosamente';
                    log_admin_action('product_updated', $_SESSION['username'], [
                        'product_id' => $product_id,
                        'name' => $product_data['name']
                    ]);
                    $edit_product = null;
                } else {
                    $error = 'Error al actualizar el producto';
                }
            } else {
                // Create new product
                $result = create_product($product_data);

                if (isset($result['success']) && $result['success']) {
                    $message = 'Producto creado exitosamente';
                    log_admin_action('product_created', $_SESSION['username'], [
                        'product_id' => $result['product']['id'],
                        'name' => $product_data['name']
                    ]);
                } else {
                    $error = $result['error'] ?? 'Error al crear el producto';
                }
            }
        }
    }
}

// Get all products
$products = get_all_products(false); // Include inactive

// Generate CSRF token
$csrf_token = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Admin</title>

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

        /* Layout */
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: #2c3e50;
            color: white;
            padding: 20px;
        }

        .sidebar-header {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 15px;
            display: block;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #34495e;
        }

        .sidebar-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #34495e;
        }

        .sidebar-footer a {
            color: #e74c3c;
            text-decoration: none;
        }

        /* Main Content */
        .main-content {
            padding: 30px;
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
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .card-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        /* Table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th,
        .products-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .products-table tbody tr:hover {
            background: #f8f9fa;
        }

        .product-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
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

        /* Stats */
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .products-table {
                font-size: 13px;
            }

            .products-table th,
            .products-table td {
                padding: 10px;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <?php echo htmlspecialchars($site_config['site_name']); ?> Admin
            </div>

            <ul class="sidebar-menu">
                <li><a href="<?php echo url('/admin/'); ?>">📊 Dashboard</a></li>
                <li><a href="<?php echo url('/admin/?page=productos-listado'); ?>" class="active">📦 Productos</a></li>
                <li><a href="<?php echo url('/admin/?page=ventas'); ?>">💰 Ventas</a></li>
                <li><a href="<?php echo url('/admin/?page=config'); ?>">⚙️ Configuración</a></li>
            </ul>

            <div class="sidebar-footer">
                <a href="/admin/logout.php">🚪 Cerrar sesión</a>
            </div>
        </div>

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
                    <div class="stat-value"><?php echo count($products); ?></div>
                    <div class="stat-label">Total Productos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php echo count(array_filter($products, fn($p) => $p['active'])); ?>
                    </div>
                    <div class="stat-label">Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php echo count(array_filter($products, fn($p) => $p['stock'] === 0)); ?>
                    </div>
                    <div class="stat-label">Sin Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php echo count(array_filter($products, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_alert'])); ?>
                    </div>
                    <div class="stat-label">Stock Bajo</div>
                </div>
            </div>

            <!-- Product Form -->
            <div class="card">
                <div class="card-header">
                    <?php echo $edit_product ? 'Editar Producto' : 'Agregar Nuevo Producto'; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php if ($edit_product): ?>
                        <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Nombre del Producto *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="thumbnail">URL de Imagen *</label>
                            <input type="text" id="thumbnail" name="thumbnail" required
                                   value="<?php echo htmlspecialchars($edit_product['thumbnail'] ?? ''); ?>"
                                   placeholder="https://...">
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Descripción</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="price_ars">Precio (ARS) *</label>
                            <input type="number" id="price_ars" name="price_ars" step="0.01" required
                                   value="<?php echo $edit_product['price_ars'] ?? ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="price_usd">Precio (USD) *</label>
                            <input type="number" id="price_usd" name="price_usd" step="0.01" required
                                   value="<?php echo $edit_product['price_usd'] ?? ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="stock">Stock *</label>
                            <input type="number" id="stock" name="stock" required
                                   value="<?php echo $edit_product['stock'] ?? 0; ?>">
                        </div>

                        <div class="form-group">
                            <label for="stock_alert">Alerta de Stock Bajo</label>
                            <input type="number" id="stock_alert" name="stock_alert"
                                   value="<?php echo $edit_product['stock_alert'] ?? 5; ?>">
                        </div>

                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" id="active" name="active"
                                       <?php echo (!isset($edit_product) || $edit_product['active']) ? 'checked' : ''; ?>>
                                <label for="active">Producto Activo (visible en el sitio)</label>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" id="hide_when_out_of_stock" name="hide_when_out_of_stock"
                                       <?php echo ($edit_product['hide_when_out_of_stock'] ?? false) ? 'checked' : ''; ?>>
                                <label for="hide_when_out_of_stock">Ocultar en galería cuando no hay stock</label>
                            </div>
                        </div>

                        <!-- SEO Fields -->
                        <div class="form-group full-width">
                            <label for="seo_title">SEO - Título</label>
                            <input type="text" id="seo_title" name="seo_title"
                                   value="<?php echo htmlspecialchars($edit_product['seo']['title'] ?? ''); ?>"
                                   maxlength="60">
                        </div>

                        <div class="form-group full-width">
                            <label for="seo_description">SEO - Descripción</label>
                            <textarea id="seo_description" name="seo_description" maxlength="160"><?php echo htmlspecialchars($edit_product['seo']['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="seo_keywords">SEO - Keywords (separadas por comas)</label>
                            <input type="text" id="seo_keywords" name="seo_keywords"
                                   value="<?php echo htmlspecialchars($edit_product['seo']['keywords'] ?? ''); ?>">
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="save_product" class="btn btn-primary">
                            <?php echo $edit_product ? '💾 Actualizar Producto' : '➕ Crear Producto'; ?>
                        </button>
                        <?php if ($edit_product): ?>
                            <a href="<?php echo url('/admin/?page=productos-listado'); ?>" class="btn btn-secondary">❌ Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Products List -->
            <div class="card">
                <div class="card-header">Lista de Productos</div>

                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Precio (ARS)</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                    No hay productos. Crea tu primer producto arriba.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($product['thumbnail']); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="product-thumbnail">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    </td>
                                    <td><?php echo format_price($product['price_ars'], 'ARS'); ?></td>
                                    <td>
                                        <?php echo $product['stock']; ?>
                                        <?php if ($product['stock'] === 0): ?>
                                            <span class="badge no-stock">Sin Stock</span>
                                        <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                                            <span class="badge low-stock">Bajo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $product['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $product['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="?action=edit&id=<?php echo urlencode($product['id']); ?>"
                                               class="btn btn-primary btn-sm">✏️ Editar</a>
                                            <a href="?action=toggle&id=<?php echo urlencode($product['id']); ?>"
                                               class="btn btn-secondary btn-sm"
                                               data-action="confirmToggleProduct"
                                               data-url="?action=toggle&id=<?php echo urlencode($product['id']); ?>">
                                                <?php echo $product['active'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                            </a>
                                            <a href="?action=delete&id=<?php echo urlencode($product['id']); ?>"
                                               class="btn btn-danger btn-sm"
                                               data-action="confirmDeleteProduct"
                                               data-url="?action=delete&id=<?php echo urlencode($product['id']); ?>">
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
    </div>

    <script nonce="<?= csp_nonce() ?>">
        /**
         * Confirmar toggle de estado del producto
         */
        function confirmToggleProduct(event, element, params) {
            if (event && event.preventDefault) event.preventDefault();
            const url = params?.url || element?.getAttribute('href');

            showModal({
                title: 'Cambiar Estado',
                message: '¿Estás seguro de que deseas cambiar el estado del producto?',
                icon: '🔄',
                confirmText: 'Cambiar',
                confirmType: 'primary',
                onConfirm: function() {
                    window.location.href = url;
                }
            });
        }

        /**
         * Confirmar eliminación de producto
         */
        function confirmDeleteProduct(event, element, params) {
            if (event && event.preventDefault) event.preventDefault();
            const url = params?.url || element?.getAttribute('href');

            showModal({
                title: 'Eliminar Producto',
                message: '¿Estás seguro de que deseas eliminar este producto?',
                details: 'Esta acción no se puede deshacer.',
                icon: '🗑️',
                confirmText: 'Eliminar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = url;
                }
            });
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================
        window.confirmToggleProduct = confirmToggleProduct;
        window.confirmDeleteProduct = confirmDeleteProduct;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
