#!/usr/bin/env php
<?php
/**
 * Email Queue Processor
 *
 * Este script procesa la cola de emails pendientes de envío
 * Debe ejecutarse mediante cron job cada 1-5 minutos
 *
 * Ejemplo de crontab:
 * */1 * * * * /usr/bin/php /home/pablo/shop-v2/app/scripts/process-email-queue.php >> /home/pablo/shop-v2/logs/email-queue.log 2>&1
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line');
}

// Bootstrap the application
$bootstrap_path = __DIR__ . '/../../app/bootstrap.php';

if (!file_exists($bootstrap_path)) {
    // Try production path
    $bootstrap_path = '/home2/uv0023/shop-v2-app/bootstrap.php';

    if (!file_exists($bootstrap_path)) {
        die("Error: bootstrap.php not found\n");
    }
}

require_once $bootstrap_path;

// Start processing
echo "[" . date('Y-m-d H:i:s') . "] Starting email queue processing...\n";

$start_time = microtime(true);

try {
    // Process up to 10 emails per run
    $stats = process_email_queue(10);

    $elapsed = round((microtime(true) - $start_time) * 1000, 2);

    echo "[" . date('Y-m-d H:i:s') . "] Queue processing completed in {$elapsed}ms\n";
    echo "  - Sent: {$stats['sent']}\n";
    echo "  - Failed: {$stats['failed']}\n";
    echo "  - Pending: {$stats['pending']}\n";
    echo "  - Skipped: {$stats['skipped']}\n";

    // Alert if there are failed emails
    if ($stats['failed'] > 0) {
        echo "  ⚠️  WARNING: {$stats['failed']} email(s) failed after max attempts\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n";
exit(0);
