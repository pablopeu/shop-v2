#!/usr/bin/env php
<?php
/**
 * Email Queue Processor - Standalone Version
 * Este script NO usa bootstrap para evitar headers HTTP
 */

// Suppress all output except our echoes
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Start output buffering to catch any unwanted output
ob_start();

// Define constants
define('CLI_MODE', true);
define('APP_ENTRY_POINT', true);

// Auto-detect app path
$app_path = __DIR__ . '/../';
if (!file_exists($app_path . 'includes/functions.php')) {
    $app_path = '/home2/uv0023/shop-v2-app/';
}

if (!file_exists($app_path . 'includes/functions.php')) {
    ob_end_clean();
    die("Error: App path not found\n");
}

// Load config manually
$config_file = $app_path . 'config/config.php';
if (file_exists($config_file)) {
    $config = require $config_file;
    define('APP_PATH', $config['app_path']);
    define('PUBLIC_PATH', $config['public_path']);
    define('APP_ROOT', dirname(APP_PATH));
} else {
    // Fallback: use detected path
    define('APP_PATH', rtrim($app_path, '/'));
    define('PUBLIC_PATH', APP_PATH . '/../public_html');
    define('APP_ROOT', dirname(APP_PATH));
}

// Load minimal dependencies (NO bootstrap - too many headers/sessions)
require_once APP_PATH . '/includes/functions.php';
require_once APP_PATH . '/includes/email.php';

// Clean any unwanted output from includes
ob_end_clean();

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
