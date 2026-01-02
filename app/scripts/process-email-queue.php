#!/usr/bin/env php
<?php
/**
 * Email Queue Processor - Ultra-Minimal Standalone Version
 * Este script es COMPLETAMENTE autónomo - NO carga functions.php ni bootstrap
 */

// Suppress ALL errors and warnings (solo log a archivo)
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Prevent execution via web browser (check if running via command line)
if (isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(403);
    die("Access denied\n");
}

// Remove automatic PHP headers (some servers send them even in CLI mode)
if (function_exists('header_remove')) {
    @header_remove();
}

// Define constants
define('CLI_MODE', true);
define('APP_ENTRY_POINT', true);

// Auto-detect app path
$app_path = __DIR__ . '/../';
if (!file_exists($app_path . 'config/config.php')) {
    // Si el script se ejecuta desde otra ubicación, intentar detectar desde cwd
    $app_path = getcwd() . '/app/';
    if (!file_exists($app_path . 'config/config.php')) {
        echo "[ERROR] App path not found. Please run this script from the project root or app/scripts directory.\n";
        exit(1);
    }
}

// Load config manually
$config_file = $app_path . 'config/config.php';
$config = require $config_file;
define('APP_PATH', $config['app_path']);

// Set $_SERVER['HTTP_HOST'] for CLI mode (needed by email.php)
if (!isset($_SERVER['HTTP_HOST'])) {
    // Extract host from app_url in config
    $app_url = $config['app_url'] ?? 'http://localhost';
    $parsed_url = parse_url($app_url);
    $_SERVER['HTTP_HOST'] = $parsed_url['host'] ?? 'localhost';
}

// ==== STANDALONE JSON FUNCTIONS (no dependencies) ====
// Estas funciones son usadas por email.php, por eso las definimos aquí

function read_json($file, $associative = true) {
    if (!file_exists($file)) {
        return $associative ? [] : new stdClass();
    }

    $content = @file_get_contents($file);
    if ($content === false) {
        return $associative ? [] : new stdClass();
    }

    $data = @json_decode($content, $associative);
    return ($data !== null) ? $data : ($associative ? [] : new stdClass());
}

function write_json($file, $data, $pretty = true) {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty) {
        $flags |= JSON_PRETTY_PRINT;
    }

    $json = json_encode($data, $flags);
    return @file_put_contents($file, $json, LOCK_EX) !== false;
}

// ==== MINIMAL EMAIL PROCESSING ====

function process_queue_standalone() {
    $queue_file = APP_PATH . '/data/email_queue.json';
    $failed_log_file = APP_PATH . '/data/email_failed.json';

    if (!file_exists($queue_file)) {
        return ['sent' => 0, 'failed' => 0, 'pending' => 0, 'skipped' => 0];
    }

    $queue = read_json($queue_file);
    $failed_emails = file_exists($failed_log_file) ? read_json($failed_log_file) : [];

    $stats = ['sent' => 0, 'failed' => 0, 'pending' => 0, 'skipped' => 0];

    // Load email.php only when needed (inside try-catch)
    $email_loaded = false;

    foreach ($queue as $key => $email) {
        if ($email['status'] !== 'pending') {
            $stats['skipped']++;
            continue;
        }

        // Load email.php on first pending email
        if (!$email_loaded) {
            try {
                require_once APP_PATH . '/includes/email.php';
                $email_loaded = true;
            } catch (Exception $e) {
                echo "[ERROR] Failed to load email.php: " . $e->getMessage() . "\n";
                return $stats;
            }
        }

        // Process email
        try {
            $result = send_queued_email($email);

            if ($result['success']) {
                $queue[$key]['status'] = 'sent';
                $queue[$key]['sent_at'] = date('Y-m-d H:i:s');
                $stats['sent']++;
            } else {
                $queue[$key]['attempts']++;
                $queue[$key]['last_attempt'] = date('Y-m-d H:i:s');
                $queue[$key]['error'] = $result['error'];

                if ($queue[$key]['attempts'] >= ($queue[$key]['max_attempts'] ?? 3)) {
                    $queue[$key]['status'] = 'failed';
                    $queue[$key]['failed_at'] = date('Y-m-d H:i:s');
                    $stats['failed']++;
                    $failed_emails[] = $queue[$key];
                } else {
                    $stats['pending']++;
                }
            }
        } catch (Exception $e) {
            $queue[$key]['attempts']++;
            $queue[$key]['error'] = $e->getMessage();
            $stats['pending']++;
        }
    }

    // Save updated queue
    write_json($queue_file, $queue);

    // Save failed log
    if (!empty($failed_emails)) {
        write_json($failed_log_file, $failed_emails);
    }

    return $stats;
}

// ==== MAIN EXECUTION ====

echo "[" . date('Y-m-d H:i:s') . "] Starting email queue processing...\n";

$start_time = microtime(true);

try {
    $stats = process_queue_standalone();

    $elapsed = round((microtime(true) - $start_time) * 1000, 2);

    echo "[" . date('Y-m-d H:i:s') . "] Queue processing completed in {$elapsed}ms\n";
    echo "  - Sent: {$stats['sent']}\n";
    echo "  - Failed: {$stats['failed']}\n";
    echo "  - Pending: {$stats['pending']}\n";
    echo "  - Skipped: {$stats['skipped']}\n";

    if ($stats['failed'] > 0) {
        echo "  WARNING: {$stats['failed']} email(s) failed after max attempts\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
exit(0);
