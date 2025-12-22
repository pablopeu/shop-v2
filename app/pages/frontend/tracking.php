<?php
/**
 * Shipping Tracking Page
 * Página para rastrear el estado de un envío
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

require_once APP_PATH . '/includes/orders.php';
require_once APP_PATH . '/includes/zipnova.php';

// Get tracking ID from URL
$tracking_id = $_GET['id'] ?? '';
$order_token = $_GET['token'] ?? '';

$order = null;
$shipping = null;
$error = '';

// Try to find order by tracking ID or order token
if (!empty($tracking_id)) {
    // Search for order with this shipping tracking ID
    $orders = get_all_orders();
    foreach ($orders as $o) {
        if (isset($o['shipping']['tracking_id']) && $o['shipping']['tracking_id'] === $tracking_id) {
            $order = $o;
            $shipping = $o['shipping'];
            break;
        }
    }

    if (!$order) {
        $error = 'No se encontró ningún envío con ese ID de tracking';
    }
} elseif (!empty($order_token)) {
    // Get order by token
    $order = get_order_by_token($order_token);
    if ($order && isset($order['shipping'])) {
        $shipping = $order['shipping'];
        $tracking_id = $shipping['tracking_id'] ?? '';
    } elseif ($order) {
        $error = 'Esta orden no tiene información de envío';
    } else {
        $error = 'Orden no encontrada';
    }
} else {
    $error = 'Por favor proporciona un ID de tracking o token de orden';
}

// Get site config
$site_config = read_json(APP_PATH . '/config/site.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

// Load active theme colors
$active_theme = $theme_config['active_theme'] ?? 'minimal';
$theme_json_path = PUBLIC_PATH . "/assets/themes/{$active_theme}/theme.json";
$theme_colors = [];
if (file_exists($theme_json_path)) {
    $theme_data = json_decode(file_get_contents($theme_json_path), true);
    $theme_colors = $theme_data['colors'] ?? [];
}

// Status translations
$status_translations = [
    'pending' => 'Pendiente de recolección',
    'in_transit' => 'En tránsito',
    'out_for_delivery' => 'En reparto',
    'delivered' => 'Entregado',
    'failed' => 'Fallido',
    'returned' => 'Devuelto',
    'cancelled' => 'Cancelado'
];

$status_icons = [
    'pending' => '⏳',
    'in_transit' => '🚚',
    'out_for_delivery' => '📦',
    'delivered' => '✅',
    'failed' => '❌',
    'returned' => '🔄',
    'cancelled' => '🚫'
];

$page_title = 'Rastrear Envío';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title . ' - ' . $site_config['site_name']); ?></title>
    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: <?php echo $theme_colors['primary'] ?? '#3498db'; ?>;
            --text-color: <?php echo $theme_colors['text'] ?? '#2c3e50'; ?>;
            --bg-color: <?php echo $theme_colors['background'] ?? '#ffffff'; ?>;
            --border-color: <?php echo $theme_colors['border'] ?? '#e0e0e0'; ?>;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: var(--bg-color);
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        header h1 {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        header p {
            color: #666;
            font-size: 14px;
        }

        .card {
            background: var(--bg-color);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .error-box {
            background: #fee;
            border-left: 4px solid #c00;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error-box h2 {
            color: #c00;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_transit { background: #d1ecf1; color: #0c5460; }
        .status-out_for_delivery { background: #d4edda; color: #155724; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-returned { background: #f8d7da; color: #721c24; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .info-item label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }

        .info-item value {
            display: block;
            font-size: 16px;
            color: var(--text-color);
        }

        .timeline {
            position: relative;
            padding-left: 40px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -31px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        .timeline-item.completed::before {
            background: #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }

        .timeline-date {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .timeline-content {
            font-weight: 500;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .card {
                padding: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><?php echo htmlspecialchars($site_config['site_name']); ?></h1>
            <p>Rastreo de Envíos</p>
        </div>
    </header>

    <div class="container">
        <?php if ($error): ?>
            <div class="error-box">
                <h2>❌ Error</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <a href="<?php echo url('/'); ?>" class="back-link">← Volver al inicio</a>
        <?php elseif ($order && $shipping): ?>
            <div class="card">
                <h2 style="margin-bottom: 20px;">📦 Información del Envío</h2>

                <?php
                $status = $shipping['status'] ?? 'pending';
                $status_label = $status_translations[$status] ?? $status;
                $status_icon = $status_icons[$status] ?? '📦';
                ?>

                <div class="status-badge status-<?php echo $status; ?>">
                    <?php echo $status_icon . ' ' . $status_label; ?>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <label>Número de Orden</label>
                        <value><?php echo htmlspecialchars($order['order_number']); ?></value>
                    </div>

                    <?php if (!empty($tracking_id)): ?>
                    <div class="info-item">
                        <label>ID de Tracking</label>
                        <value><?php echo htmlspecialchars($tracking_id); ?></value>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($shipping['service_name'])): ?>
                    <div class="info-item">
                        <label>Método de Envío</label>
                        <value><?php echo htmlspecialchars($shipping['service_name']); ?></value>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($shipping['estimated_delivery'])): ?>
                    <div class="info-item">
                        <label>Entrega Estimada</label>
                        <value><?php echo htmlspecialchars($shipping['estimated_delivery']); ?> días</value>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($shipping['address'])): ?>
                <div class="info-item" style="margin-bottom: 20px;">
                    <label>Dirección de Entrega</label>
                    <value>
                        <?php
                        $addr = $shipping['address'];
                        echo htmlspecialchars($addr['street'] ?? '');
                        if (!empty($addr['city'])) echo '<br>' . htmlspecialchars($addr['city']);
                        if (!empty($addr['province'])) echo ', ' . htmlspecialchars($addr['province']);
                        if (!empty($addr['postal_code'])) echo ' (' . htmlspecialchars($addr['postal_code']) . ')';
                        ?>
                    </value>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($shipping['history']) && count($shipping['history']) > 0): ?>
            <div class="card">
                <h2 style="margin-bottom: 20px;">📋 Historial de Seguimiento</h2>
                <div class="timeline">
                    <?php foreach (array_reverse($shipping['history']) as $event): ?>
                    <div class="timeline-item <?php echo isset($event['status']) && in_array($event['status'], ['delivered']) ? 'completed' : ''; ?>">
                        <div class="timeline-date">
                            <?php echo date('d/m/Y H:i', strtotime($event['date'])); ?>
                        </div>
                        <div class="timeline-content">
                            <?php
                            if (isset($event['status'])) {
                                $event_status = $status_translations[$event['status']] ?? $event['status'];
                                echo htmlspecialchars($event_status);
                            } elseif (isset($event['updates'])) {
                                echo 'Información actualizada';
                            } else {
                                echo 'Evento registrado';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <a href="<?php echo url('/'); ?>" class="back-link">← Volver al inicio</a>
        <?php endif; ?>
    </div>
</body>
</html>
