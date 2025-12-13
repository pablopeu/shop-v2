<?php
/**
 * Admin - Edit Promotion
 */


require_admin();

$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Editar Promoción';
$promotion_id = $_GET['id'] ?? '';

if (empty($promotion_id)) {
    header('Location: ' . url('/admin/?page=promociones-listado'));
    exit;
}

$promotion = get_promotion_by_id($promotion_id);
if (!$promotion) {
    header('Location: ' . url('/admin/?page=promociones-listado&error=not_found'));
    exit;
}

$all_products = get_all_products(false);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_promotion'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $promotion_data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'type' => sanitize_input($_POST['type'] ?? 'percentage'),
            'value' => floatval($_POST['value'] ?? 0),
            'application' => sanitize_input($_POST['application'] ?? 'all'),
            'products' => $_POST['products'] ?? [],
            'condition_type' => sanitize_input($_POST['condition_type'] ?? 'any'),
            'minimum_amount' => floatval($_POST['minimum_amount'] ?? 0),
            'period_type' => sanitize_input($_POST['period_type'] ?? 'permanent'),
            'start_date' => sanitize_input($_POST['start_date'] ?? ''),
            'end_date' => sanitize_input($_POST['end_date'] ?? ''),
            'active' => isset($_POST['active']) ? true : false
        ];

        if (empty($promotion_data['name'])) {
            $error = 'El nombre de la promoción es requerido';
        } elseif ($promotion_data['value'] <= 0) {
            $error = 'El valor del descuento debe ser mayor a 0';
        } elseif ($promotion_data['period_type'] === 'limited' && (empty($promotion_data['start_date']) || empty($promotion_data['end_date']))) {
            $error = 'Las fechas de vigencia son requeridas para promociones con período limitado';
        } elseif ($promotion_data['period_type'] === 'limited' && strtotime($promotion_data['end_date']) < strtotime($promotion_data['start_date'])) {
            $error = 'La fecha de fin debe ser posterior a la fecha de inicio';
        } elseif (update_promotion($promotion_id, $promotion_data)) {
            $message = 'Promoción actualizada exitosamente';
            log_admin_action('promotion_updated', $_SESSION['username'], [
                'promotion_id' => $promotion_id,
                'name' => $promotion_data['name']
            ]);
            $promotion = get_promotion_by_id($promotion_id);
        } else {
            $error = 'Error al actualizar la promoción';
        }
    }
}

$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Promoción - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 30px; max-width: 1200px; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; transition: all 0.3s; border: none; }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-primary:hover { background: #45a049; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        .form-group label .required { color: #dc3545; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #4CAF50; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .checkbox-group input { width: auto; }
        .section-divider { margin: 30px 0; padding: 15px 0; border-top: 2px solid #f0f0f0; font-size: 18px; font-weight: 600; color: #2c3e50; }
        .products-selection { max-height: 300px; overflow-y: auto; border: 2px solid #e0e0e0; border-radius: 6px; padding: 15px; background: #fafafa; }
        .product-checkbox { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 6px; transition: all 0.2s; margin-bottom: 5px; background: white; border: 1px solid #e8e8e8; }
        .product-checkbox:hover { background: #f0f8ff; border-color: #4CAF50; }
        .product-checkbox input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; flex-shrink: 0; }
        .product-checkbox label { cursor: pointer; user-select: none; flex: 1; margin: 0; color: #333; font-size: 14px; }
        .info-box { background: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 6px; padding: 15px; margin-bottom: 20px; }
        .info-box p { color: #555; font-size: 14px; margin: 0; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } .form-grid { grid-template-columns: 1fr; } }
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

        <div class="info-box">
            <p><strong>Información:</strong></p>
            <p>• Las promociones se aplican automáticamente al carrito cuando se cumplen las condiciones</p>
            <p>• Las promociones con período limitado solo estarán activas durante las fechas especificadas</p>
        </div>

        <div class="card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="section-divider">Información de la Promoción</div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="name">Nombre de la Promoción <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($promotion['name']); ?>">
                        <small style="color: #666;">Un nombre descriptivo para identificar la promoción internamente</small>
                    </div>
                    <div class="form-group">
                        <label for="type">Tipo de Descuento <span class="required">*</span></label>
                        <select id="type" name="type" required>
                            <option value="percentage" <?php echo $promotion['type'] === 'percentage' ? 'selected' : ''; ?>>Porcentaje (%)</option>
                            <option value="fixed" <?php echo $promotion['type'] === 'fixed' ? 'selected' : ''; ?>>Monto Fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="value">Valor del Descuento <span class="required">*</span></label>
                        <input type="number" id="value" name="value" step="0.01" required value="<?php echo $promotion['value']; ?>" min="0.01">
                        <small style="color: #666;">Ejemplo: 15 para 15% o $1500 según el tipo</small>
                    </div>
                </div>

                <div class="section-divider">Aplicación de Productos</div>
                <div class="form-group full-width">
                    <label for="application">Aplicar a:</label>
                    <select id="application" name="application" data-onchange="toggleProductsSelection">
                        <option value="all" <?php echo $promotion['application'] === 'all' ? 'selected' : ''; ?>>Todos los productos</option>
                        <option value="specific" <?php echo $promotion['application'] === 'specific' ? 'selected' : ''; ?>>Productos específicos</option>
                    </select>
                </div>

                <div id="products_container" class="form-group full-width" style="display: <?php echo $promotion['application'] === 'specific' ? 'block' : 'none'; ?>;">
                    <label>Seleccionar Productos:</label>
                    <div class="products-selection">
                        <?php foreach ($all_products as $product): ?>
                            <div class="product-checkbox">
                                <input type="checkbox" name="products[]" value="<?php echo htmlspecialchars($product['id']); ?>"
                                       id="product_<?php echo htmlspecialchars($product['id']); ?>"
                                       <?php echo in_array($product['id'], $promotion['products']) ? 'checked' : ''; ?>>
                                <label for="product_<?php echo htmlspecialchars($product['id']); ?>">
                                    <?php echo htmlspecialchars($product['name']); ?> (<?php echo format_price($product['price_ars'], 'ARS'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-divider">Condiciones de Compra</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="condition_type">Tipo de Condición</label>
                        <select id="condition_type" name="condition_type" data-onchange="toggleMinimumAmount">
                            <option value="any" <?php echo $promotion['condition_type'] === 'any' ? 'selected' : ''; ?>>Sin mínimo (aplicar siempre)</option>
                            <option value="minimum" <?php echo $promotion['condition_type'] === 'minimum' ? 'selected' : ''; ?>>Monto mínimo de compra</option>
                        </select>
                    </div>
                    <div class="form-group" id="minimum_amount_container" style="display: <?php echo $promotion['condition_type'] === 'minimum' ? 'block' : 'none'; ?>;">
                        <label for="minimum_amount">Monto Mínimo (ARS)</label>
                        <input type="number" id="minimum_amount" name="minimum_amount" step="0.01" value="<?php echo $promotion['minimum_amount']; ?>" min="0">
                        <small style="color: #666;">El carrito debe superar este monto para aplicar la promoción</small>
                    </div>
                </div>

                <div class="section-divider">Vigencia</div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="period_type">Tipo de Período</label>
                        <select id="period_type" name="period_type" data-onchange="togglePeriodDates">
                            <option value="permanent" <?php echo $promotion['period_type'] === 'permanent' ? 'selected' : ''; ?>>Permanente (siempre activa)</option>
                            <option value="limited" <?php echo $promotion['period_type'] === 'limited' ? 'selected' : ''; ?>>Período Limitado</option>
                        </select>
                    </div>
                </div>

                <div id="period_dates_container" class="form-grid" style="display: <?php echo $promotion['period_type'] === 'limited' ? 'grid' : 'none'; ?>;">
                    <div class="form-group">
                        <label for="start_date">Fecha de Inicio <span class="required">*</span></label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $promotion['start_date'] ?? ''; ?>" <?php echo $promotion['period_type'] === 'limited' ? 'required' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Fecha de Fin <span class="required">*</span></label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $promotion['end_date'] ?? ''; ?>" <?php echo $promotion['period_type'] === 'limited' ? 'required' : ''; ?>>
                    </div>
                </div>

                <div class="section-divider">Estado</div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="active" name="active" <?php echo $promotion['active'] ? 'checked' : ''; ?>>
                        <label for="active">Promoción Activa (visible y disponible para usuarios)</label>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" name="save_promotion" class="btn btn-primary">Guardar Cambios</button>
                    <a href="<?php echo url('/admin/?page=promociones-listado'); ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <script nonce="<?= csp_nonce() ?>">
        function toggleProductsSelection(value) {
            document.getElementById('products_container').style.display = value === 'specific' ? 'block' : 'none';
        }

        function toggleMinimumAmount(value) {
            document.getElementById('minimum_amount_container').style.display = value === 'minimum' ? 'block' : 'none';
        }

        function togglePeriodDates(value) {
            const container = document.getElementById('period_dates_container');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            if (value === 'limited') {
                container.style.display = 'grid';
                startDate.required = true;
                endDate.required = true;
            } else {
                container.style.display = 'none';
                startDate.required = false;
                endDate.required = false;
            }
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: toggleProductsSelection
         * Compatible con event delegation
         */
        const _toggleProductsSelection = toggleProductsSelection;
        window.toggleProductsSelection = function(eventOrValue, element, params) {
            const value = element?.value || (typeof eventOrValue === 'string' ? eventOrValue : null);
            if (value) return _toggleProductsSelection(value);
        };

        /**
         * Wrapper: toggleMinimumAmount
         * Compatible con event delegation
         */
        const _toggleMinimumAmount = toggleMinimumAmount;
        window.toggleMinimumAmount = function(eventOrValue, element, params) {
            const value = element?.value || (typeof eventOrValue === 'string' ? eventOrValue : null);
            if (value) return _toggleMinimumAmount(value);
        };

        /**
         * Wrapper: togglePeriodDates
         * Compatible con event delegation
         */
        const _togglePeriodDates = togglePeriodDates;
        window.togglePeriodDates = function(eventOrValue, element, params) {
            const value = element?.value || (typeof eventOrValue === 'string' ? eventOrValue : null);
            if (value) return _togglePeriodDates(value);
        };
    </script>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
