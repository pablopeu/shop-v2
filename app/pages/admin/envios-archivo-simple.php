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

        <h1>Envíos Archivados - Versión Simplificada</h1>

        <p><strong>Total de envíos archivados:</strong> <?php echo $total; ?></p>

        <p>Esta es una versión simplificada para debug. Si esta página funciona, el problema es el tamaño del HTML en la versión completa.</p>

        <p><a href="<?php echo url('/admin/?page=envios-pendientes'); ?>">← Volver a Envíos Pendientes</a></p>
    </div>
</body>
</html>
<?php
error_log("ENVIOS-ARCHIVO-SIMPLE - Finished successfully");
?>
