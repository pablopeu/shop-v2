<?php
/**
 * Script de Rotación de Logs
 * Ejecutar manualmente o vía cron para rotar logs del sistema
 *
 * Uso:
 *   php public_html/scripts/rotate-logs.php
 *
 * Crontab (ejecutar diariamente a las 3 AM):
 *   0 3 * * * cd /home/pablo/shop-v2 && php public_html/scripts/rotate-logs.php >> /tmp/log-rotation.log 2>&1
 */

define('APP_ENTRY_POINT', true);

// Bootstrap de la aplicación
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    require_once __DIR__ . '/../../app/bootstrap.php';
}

// Cargar módulo de log rotation
require_once APP_PATH . '/includes/log_rotation.php';

echo "=== Rotación de Logs del Sistema ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Ejecutar rotación
$stats = rotate_all_system_logs();

// Mostrar resultados
echo "Resultados:\n";
echo "  - Logs rotados: {$stats['rotated']}\n";
echo "  - Logs archivados (comprimidos): {$stats['archived']}\n";
echo "  - Logs antiguos eliminados: {$stats['deleted']}\n";

if (!empty($stats['errors'])) {
    echo "\nErrores:\n";
    foreach ($stats['errors'] as $error) {
        echo "  ❌ $error\n";
    }
    exit(1);
}

echo "\n✅ Rotación completada exitosamente\n";
exit(0);
