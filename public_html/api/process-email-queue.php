<?php
/**
 * API Endpoint - Process Email Queue
 * Este endpoint puede ser llamado por cron mediante URL
 *
 * Uso en cron:
 * wget -q -O /dev/null "https://peu.net/shopv2/api/process-email-queue.php?secret=YOUR_SECRET"
 */

define('APP_ENTRY_POINT', true);
require_once __DIR__ . '/../../app/bootstrap.php';

// Security: Require secret token
$secret_token = 'email_queue_cron_2024'; // TODO: Mover a config segura

if (!isset($_GET['secret']) || $_GET['secret'] !== $secret_token) {
    http_response_code(403);
    die('Forbidden');
}

// Set execution time limit
set_time_limit(60);

// Start processing
$start_time = microtime(true);

try {
    // Process up to 10 emails per run
    $stats = process_email_queue(10);

    $elapsed = round((microtime(true) - $start_time) * 1000, 2);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'elapsed_ms' => $elapsed,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
