<?php
/**
 * Admin - Envíos Archivados (Versión Simplificada para Debug)
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

error_log("ENVIOS-ARCHIVO-SIMPLE - Starting");

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Envíos Archivados (Debug)';
$user = get_logged_user();

// Get archived orders count
$all_archived = get_archived_orders();
$total = count($all_archived);

error_log("ENVIOS-ARCHIVO-SIMPLE - Total archived orders: $total");

// Diagnose data issues
$problematic_orders = [];
foreach ($all_archived as $index => $order) {
    // Check for malformed data
    if (!isset($order['id'])) {
        $problematic_orders[] = "Index $index: Missing ID";
        continue;
    }

    // Check for recursive/circular references
    if (json_encode($order) === false) {
        $problematic_orders[] = "Order {$order['id']}: Cannot JSON encode (circular reference?)";
    }

    // Check for extremely large data
    $size = strlen(json_encode($order));
    if ($size > 100000) {
        $problematic_orders[] = "Order {$order['id']}: Very large data ($size bytes)";
    }
}

if (!empty($problematic_orders)) {
    error_log("ENVIOS-ARCHIVO-SIMPLE - PROBLEMATIC ORDERS FOUND:");
    foreach ($problematic_orders as $issue) {
        error_log("  - $issue");
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin</title>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <h1>Envíos Archivados - Diagnóstico</h1>

        <p><strong>Total de envíos archivados:</strong> <?php echo $total; ?></p>

        <?php if (!empty($problematic_orders)): ?>
            <div style="background: #fee; border: 2px solid #c00; padding: 15px; margin: 15px 0;">
                <h3 style="color: #c00; margin-top: 0;">⚠️ Problemas Detectados:</h3>
                <ul>
                    <?php foreach ($problematic_orders as $issue): ?>
                        <li><?php echo htmlspecialchars($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div style="background: #efe; border: 2px solid #0c0; padding: 15px; margin: 15px 0;">
                <p style="color: #060; margin: 0;">✅ No se detectaron problemas en los datos archivados.</p>
            </div>
        <?php endif; ?>

        <h3>Lista de Órdenes Archivadas:</h3>
        <table border="1" cellpadding="5" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Fecha Archivado</th>
                    <th>Tamaño JSON</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_archived as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['archived_date'] ?? $order['date'] ?? 'N/A'); ?></td>
                        <td><?php echo number_format(strlen(json_encode($order))); ?> bytes</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px;"><a href="<?php echo url('/admin/?page=envios-pendientes'); ?>">← Volver a Envíos Pendientes</a></p>
    </div>
</body>
</html>
<?php
error_log("ENVIOS-ARCHIVO-SIMPLE - Finished successfully");
?>
