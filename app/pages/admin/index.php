<?php
/**
 * Admin Dashboard
 * ENTRY POINT: This is a valid entry point for the application
 */

// Define security constant to prevent direct file access


// Set security headers

// Start session

// Require admin authentication
require_admin();

// Check session timeout
$credentials = file_exists(APP_PATH . '/config/credentials.php')
    ? require APP_PATH . '/config/credentials.php'
    : ['security' => ['session_lifetime' => 3600]];

if (!check_session_timeout($credentials['security']['session_lifetime'])) {
    redirect(url('/admin/login.php?timeout=1'));
}

// Get statistics
$all_products = get_all_products();
$active_products = get_all_products(true);
$low_stock = get_low_stock_products();
$out_of_stock = get_out_of_stock_products();

$orders_data = read_json(APP_PATH . '/data/orders.json');
$orders = $orders_data['orders'] ?? [];

// Calculate net income from collected sales (cobrada status, not archived)
$net_income = 0;
foreach ($orders as $order) {
    // Only cobrada orders that are not archived
    if (($order['status'] === 'cobrada') && !($order['archived'] ?? false)) {
        // If we have MercadoPago data with net amount, use that
        if (isset($order['mercadopago_data']['net_received_amount'])) {
            $net_income += floatval($order['mercadopago_data']['net_received_amount']);
        } else {
            // Otherwise use the full order total (presencial payments, etc.)
            $net_income += floatval($order['total']);
        }
    }
}

$promotions_data = read_json(APP_PATH . '/data/promotions.json');
$active_promotions = array_filter($promotions_data['promotions'] ?? [], function($p) {
    return $p['active'] ?? false;
});

$coupons_data = read_json(APP_PATH . '/data/coupons.json');
$active_coupons = array_filter($coupons_data['coupons'] ?? [], function($c) {
    return $c['active'] ?? false;
});

// Load dashboard config
$dashboard_config = read_json(APP_PATH . '/config/dashboard.json');

$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 20px;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            padding: 10px 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .stat-card h3 {
            font-size: 10px;
            color: #7f8c8d;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 1px;
        }

        .stat-card .label {
            font-size: 11px;
            color: #95a5a6;
        }

        .stat-card.warning {
            border-left: 4px solid #f39c12;
        }

        .stat-card.danger {
            border-left: 4px solid #e74c3c;
        }

        .stat-card.success {
            border-left: 4px solid #27ae60;
        }

        .stat-card.info {
            border-left: 4px solid #3498db;
        }

        /* Responsive stats grid */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
        }

        /* Alerts */
        .alerts {
            margin-bottom: 15px;
        }

        .alert {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .alert-icon {
            font-size: 20px;
        }

        .alert-warning {
            border-left: 4px solid #f39c12;
        }

        .alert-danger {
            border-left: 4px solid #e74c3c;
        }

        .alert-info {
            border-left: 4px solid #3498db;
        }

        .alert-content h4 {
            font-size: 13px;
            margin-bottom: 3px;
            color: #2c3e50;
        }

        .alert-content p {
            font-size: 12px;
            color: #7f8c8d;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .quick-actions h2 {
            font-size: 15px;
            margin-bottom: 12px;
            color: #2c3e50;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .action-btn {
            display: block;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .action-btn .icon {
            font-size: 24px;
            margin-bottom: 6px;
        }

        .action-btn .text {
            font-size: 12px;
            font-weight: 600;
        }

        .user-info {
            background: rgba(52, 152, 219, 0.1);
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
        }

        .user-info strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php
    // Get site config for sidebar
    $site_config = read_json(APP_PATH . '/config/site.json');
    $page_title = 'Dashboard';
    include APP_PATH . '/includes/admin/sidebar.php';
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <?php if ($dashboard_config['widgets']['productos_activos'] ?? true): ?>
            <div class="stat-card success">
                <h3>Productos Activos</h3>
                <div class="value"><?php echo count($active_products); ?></div>
                <div class="label">de <?php echo count($all_products); ?> totales</div>
            </div>
            <?php endif; ?>

            <?php if ($dashboard_config['widgets']['stock_bajo'] ?? true): ?>
            <div class="stat-card warning">
                <h3>Stock Bajo</h3>
                <div class="value"><?php echo count($low_stock); ?></div>
                <div class="label">productos necesitan reposición</div>
            </div>
            <?php endif; ?>

            <?php if ($dashboard_config['widgets']['sin_stock'] ?? true): ?>
            <div class="stat-card danger">
                <h3>Sin Stock</h3>
                <div class="value"><?php echo count($out_of_stock); ?></div>
                <div class="label">productos agotados</div>
            </div>
            <?php endif; ?>

            <?php if ($dashboard_config['widgets']['ordenes_totales'] ?? true): ?>
            <div class="stat-card info">
                <h3>Órdenes Totales</h3>
                <div class="value"><?php echo count($orders); ?></div>
                <div class="label">pedidos registrados</div>
            </div>
            <?php endif; ?>

            <?php if ($dashboard_config['widgets']['ingreso_neto_ventas'] ?? true): ?>
            <div class="stat-card success">
                <h3>Ingreso Neto</h3>
                <div class="value">$<?php echo number_format($net_income, 2, ',', '.'); ?></div>
                <div class="label">ventas cobradas (después de comisiones)</div>
            </div>
            <?php endif; ?>

            <?php if ($dashboard_config['widgets']['promociones'] ?? true): ?>
            <div class="stat-card success">
                <h3>Promociones</h3>
                <div class="value"><?php echo count($active_promotions); ?></div>
                <div class="label">activas ahora</div>
            </div>
            <?php endif; ?>

            <?php if ($dashboard_config['widgets']['cupones'] ?? true): ?>
            <div class="stat-card info">
                <h3>Cupones</h3>
                <div class="value"><?php echo count($active_coupons); ?></div>
                <div class="label">disponibles</div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Alerts -->
        <?php if (count($out_of_stock) > 0 || count($low_stock) > 0): ?>
        <div class="alerts">
            <h2 style="margin-bottom: 15px; color: #2c3e50;">⚠️ Alertas</h2>

            <?php if (count($out_of_stock) > 0): ?>
            <div class="alert alert-danger">
                <div class="alert-icon">🚨</div>
                <div class="alert-content">
                    <h4>Productos sin stock</h4>
                    <p><?php echo count($out_of_stock); ?> productos están agotados y no pueden venderse.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($low_stock) > 0): ?>
            <div class="alert alert-warning">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <h4>Stock bajo</h4>
                    <p><?php echo count($low_stock); ?> productos tienen stock bajo. Considera reponerlos pronto.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Acciones Rápidas</h2>
            <div class="actions-grid">
                <?php if ($dashboard_config['quick_actions']['productos'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=productos-listado'); ?>" class="action-btn">
                    <div class="icon">📦</div>
                    <div class="text">Productos</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['ventas'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=ventas'); ?>" class="action-btn">
                    <div class="icon">💰</div>
                    <div class="text">Ventas</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['cupones'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=cupones-listado'); ?>" class="action-btn">
                    <div class="icon">🎫</div>
                    <div class="text">Cupones</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['config'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=config-sitio'); ?>" class="action-btn">
                    <div class="icon">⚙️</div>
                    <div class="text">Configuración</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['envios'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=envios-pendientes'); ?>" class="action-btn">
                    <div class="icon">📦</div>
                    <div class="text">Envíos</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['promociones'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=promociones-listado'); ?>" class="action-btn">
                    <div class="icon">🎯</div>
                    <div class="text">Promociones</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['notificaciones'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=notificaciones'); ?>" class="action-btn">
                    <div class="icon">🔔</div>
                    <div class="text">Notificaciones</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['payment'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=config-payment'); ?>" class="action-btn">
                    <div class="icon">💳</div>
                    <div class="text">Medios de Pago</div>
                </a>
                <?php endif; ?>

                <?php if ($dashboard_config['quick_actions']['analytics'] ?? true): ?>
                <a href="<?php echo url('/admin/?page=config-analytics'); ?>" class="action-btn">
                    <div class="icon">📊</div>
                    <div class="text">Analytics</div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
