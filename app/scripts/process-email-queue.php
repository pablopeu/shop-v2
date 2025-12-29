#!/usr/bin/env php
<?php
/**
 * Email Queue Processor
 *
 * Este script procesa la cola de emails pendientes de envío
 * Debe ejecutarse mediante cron job cada 1-5 minutos
 *
 * Ejemplo de crontab (cada minuto):
 * Minuto: asterisco-slash-1, Resto: asteriscos
 * /usr/bin/php /path/to/process-email-queue.php >> /path/to/email-queue.log 2>&1
 */

// Allow both CLI and web execution (for cPanel cron)
// cPanel cron jobs sometimes run as web requests

// Define as CLI mode to prevent HTTP headers and pseudo-cron execution
define('CLI_MODE', true);
define('APP_ENTRY_POINT', true);

// Auto-detect environment and load bootstrap
$bootstrap_path = null;

// Try relative path (development)
if (file_exists(__DIR__ . '/../bootstrap.php')) {
    $bootstrap_path = __DIR__ . '/../bootstrap.php';
}
// Try production path
elseif (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    $bootstrap_path = '/home2/uv0023/shop-v2-app/bootstrap.php';
}

if (!$bootstrap_path) {
    die("Error: bootstrap.php not found\n");
}

// Load bootstrap (which loads all necessary includes)
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
