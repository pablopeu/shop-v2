<?php
/**
 * Script de Migración: Tracking Numbers de Zipnova
 *
 * Migra los tracking_number de los shipments locales a las órdenes correspondientes.
 * Útil para etiquetas creadas antes de implementar la lectura tolerante.
 *
 * Este script lee todos los shipments guardados en app/data/shipments/,
 * extrae el tracking_number de forma tolerante y actualiza las órdenes.
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require admin authentication
require_admin();

// Page title
$page_title = 'Migración de Tracking Numbers';

// Process migration if POST request
$migration_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $migration_result = [
            'success' => false,
            'error' => 'Token de seguridad inválido'
        ];
    } else {
        $migration_result = migrate_tracking_numbers();
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

/**
 * Función principal de migración
 */
function migrate_tracking_numbers() {
    $shipments_dir = APP_PATH . '/data/shipments';

    // Verificar que exista el directorio
    if (!is_dir($shipments_dir)) {
        return [
            'success' => false,
            'error' => 'No hay shipments guardados localmente',
            'stats' => [
                'total_shipments' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0
            ]
        ];
    }

    // Obtener todos los archivos de shipments
    $shipment_files = glob($shipments_dir . '/*.json');

    $stats = [
        'total_shipments' => count($shipment_files),
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'details' => []
    ];

    foreach ($shipment_files as $file) {
        $shipment_id = basename($file, '.json');
        $shipment_data = json_decode(file_get_contents($file), true);

        if (!$shipment_data) {
            $stats['errors']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'status' => 'error',
                'message' => 'Error al leer archivo de shipment'
            ];
            continue;
        }

        // Obtener order_id del shipment
        $order_id = $shipment_data['order_id'] ?? null;
        if (!$order_id) {
            $stats['skipped']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'status' => 'skipped',
                'message' => 'Shipment sin order_id asociado'
            ];
            continue;
        }

        // Leer tracking_number de forma tolerante
        $tracking_number = null;

        // 1. Intentar desde el campo directo del shipment
        $tracking_number = $shipment_data['tracking_number'] ?? null;

        // 2. Intentar desde data.tracking_number (respuesta completa de Zipnova)
        if (!$tracking_number && isset($shipment_data['data'])) {
            $tracking_number = $shipment_data['data']['tracking_number']
                ?? $shipment_data['data']['tracking_code']
                ?? $shipment_data['data']['carrier_tracking_number']
                ?? null;
        }

        if (!$tracking_number) {
            $stats['skipped']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'order_id' => $order_id,
                'order_number' => $shipment_data['order_number'] ?? 'N/A',
                'status' => 'skipped',
                'message' => 'No se encontró tracking_number en ningún campo'
            ];
            continue;
        }

        // Obtener la orden
        $order = get_order_by_id($order_id);
        if (!$order) {
            $stats['errors']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'order_id' => $order_id,
                'status' => 'error',
                'message' => 'Orden no encontrada'
            ];
            continue;
        }

        // Verificar si la orden ya tiene tracking_number
        $current_tracking = $order['shipping']['tracking_number'] ?? $order['tracking_number'] ?? null;

        if ($current_tracking === $tracking_number) {
            $stats['skipped']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'order_id' => $order_id,
                'order_number' => $order['order_number'] ?? $order_id,
                'status' => 'skipped',
                'message' => 'Ya tiene el mismo tracking_number'
            ];
            continue;
        }

        // Actualizar la orden con el tracking_number
        $updated = update_order_shipping_info($order_id, [
            'tracking_number' => $tracking_number
        ]);

        if ($updated) {
            $stats['updated']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'order_id' => $order_id,
                'order_number' => $order['order_number'] ?? $order_id,
                'tracking_number' => $tracking_number,
                'status' => 'updated',
                'message' => 'Tracking actualizado exitosamente'
            ];

            error_log("MIGRATION: Tracking actualizado - Order {$order['order_number']}: $tracking_number");
        } else {
            $stats['errors']++;
            $stats['details'][] = [
                'shipment_id' => $shipment_id,
                'order_id' => $order_id,
                'order_number' => $order['order_number'] ?? $order_id,
                'status' => 'error',
                'message' => 'Error al actualizar la orden'
            ];
        }
    }

    // Log del resultado de la migración
    log_admin_action('migrate_tracking_numbers', $_SESSION['username'], $stats);

    return [
        'success' => true,
        'stats' => $stats
    ];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link rel="stylesheet" href="<?php echo url('/assets/css/admin.css'); ?>">
    <style nonce="<?= csp_nonce() ?>">
        .migration-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .migration-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .migration-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .migration-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            margin: 0 0 10px 0;
            color: #1976D2;
        }

        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .info-box li {
            margin: 5px 0;
        }

        .migration-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .btn-migrate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-migrate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.updated { border-left: 4px solid #4CAF50; }
        .stat-card.updated .stat-value { color: #4CAF50; }

        .stat-card.skipped { border-left: 4px solid #FF9800; }
        .stat-card.skipped .stat-value { color: #FF9800; }

        .stat-card.errors { border-left: 4px solid #f44336; }
        .stat-card.errors .stat-value { color: #f44336; }

        .stat-card.total { border-left: 4px solid #2196F3; }
        .stat-card.total .stat-value { color: #2196F3; }

        .details-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .details-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .details-table th {
            background: #f5f5f5;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }

        .details-table td {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.updated {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.skipped {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge.error {
            background: #ffebee;
            color: #c62828;
        }

        .success-message {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .error-message {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/header.php'; ?>

    <div class="migration-container">
        <div class="migration-header">
            <h1>🔄 Migración de Tracking Numbers</h1>
            <p>Actualiza las órdenes con los números de seguimiento de etiquetas ya creadas</p>
        </div>

        <div class="info-box">
            <h3>ℹ️ ¿Qué hace este script?</h3>
            <p>Este script migra los tracking numbers de las etiquetas ya creadas a las órdenes correspondientes.</p>
            <ul>
                <li>Lee todos los shipments guardados localmente en <code>app/data/shipments/</code></li>
                <li>Extrae el tracking_number de forma tolerante (tracking_number, tracking_code, carrier_tracking_number)</li>
                <li>Actualiza las órdenes que no tienen tracking_number guardado</li>
                <li>Es seguro ejecutar múltiples veces (no duplica ni sobreescribe)</li>
            </ul>
        </div>

        <?php if ($migration_result): ?>
            <?php if ($migration_result['success']): ?>
                <div class="success-message">
                    <strong>✅ Migración completada exitosamente</strong>
                </div>

                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-value"><?php echo $migration_result['stats']['total_shipments']; ?></div>
                        <div class="stat-label">Total Shipments</div>
                    </div>
                    <div class="stat-card updated">
                        <div class="stat-value"><?php echo $migration_result['stats']['updated']; ?></div>
                        <div class="stat-label">Actualizados</div>
                    </div>
                    <div class="stat-card skipped">
                        <div class="stat-value"><?php echo $migration_result['stats']['skipped']; ?></div>
                        <div class="stat-label">Omitidos</div>
                    </div>
                    <div class="stat-card errors">
                        <div class="stat-value"><?php echo $migration_result['stats']['errors']; ?></div>
                        <div class="stat-label">Errores</div>
                    </div>
                </div>

                <?php if (!empty($migration_result['stats']['details'])): ?>
                    <h3 style="margin: 30px 0 15px 0;">Detalles de la migración</h3>
                    <div class="details-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Shipment ID</th>
                                    <th>Tracking Number</th>
                                    <th>Estado</th>
                                    <th>Mensaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($migration_result['stats']['details'] as $detail): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($detail['order_number'] ?? $detail['order_id'] ?? 'N/A'); ?></td>
                                        <td><code><?php echo htmlspecialchars(substr($detail['shipment_id'], 0, 20)); ?>...</code></td>
                                        <td><?php echo htmlspecialchars($detail['tracking_number'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $detail['status']; ?>">
                                                <?php echo strtoupper($detail['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($detail['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="error-message">
                    <strong>❌ Error:</strong> <?php echo htmlspecialchars($migration_result['error']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="migration-form">
            <h2 style="margin: 0 0 20px 0;">Ejecutar Migración</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Haz clic en el botón para iniciar la migración de tracking numbers.
                El proceso es seguro y puede ejecutarse múltiples veces.
            </p>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="submit" class="btn-migrate">
                    🚀 Ejecutar Migración
                </button>
            </form>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="<?php echo url('/admin/?page=ventas'); ?>" style="color: #667eea; text-decoration: none;">
                ← Volver a Gestión de Ventas
            </a>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
