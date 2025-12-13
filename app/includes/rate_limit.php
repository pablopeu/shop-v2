<?php
/**
 * Rate Limiting System
 * Previene ataques de fuerza bruta limitando intentos
 */

/**
 * Check rate limit for identifier
 * @param string $identifier Unique identifier (IP, username, etc)
 * @param int $max_attempts Maximum attempts allowed
 * @param int $period Time period in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => timestamp]
 */
function check_rate_limit($identifier, $max_attempts = 5, $period = 900) {
    $identifier = sanitize_input($identifier);
    $hash = md5($identifier);
    $file = __DIR__ . "/../data/rate_limits/{$hash}.json";

    // Create directory if needed
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $now = time();
    $data = read_json($file);

    // Initialize data structure
    if (empty($data) || !isset($data['attempts'])) {
        $data = [
            'identifier' => $identifier,
            'attempts' => [],
            'blocked_until' => null
        ];
    }

    // Check if currently blocked
    if ($data['blocked_until'] && $now < $data['blocked_until']) {
        return [
            'allowed' => false,
            'remaining' => 0,
            'reset_at' => $data['blocked_until'],
            'retry_after' => $data['blocked_until'] - $now
        ];
    }

    // Clean old attempts outside the period
    $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $period) {
        return ($now - $timestamp) < $period;
    });

    // Reset block if period has passed
    if ($data['blocked_until'] && $now >= $data['blocked_until']) {
        $data['blocked_until'] = null;
        $data['attempts'] = [];
    }

    $attempt_count = count($data['attempts']);
    $remaining = max(0, $max_attempts - $attempt_count);

    // Calculate reset time (oldest attempt + period)
    $reset_at = !empty($data['attempts']) ? min($data['attempts']) + $period : $now + $period;

    write_json($file, $data);

    return [
        'allowed' => $attempt_count < $max_attempts,
        'remaining' => $remaining,
        'reset_at' => $reset_at,
        'retry_after' => 0
    ];
}

/**
 * Record failed attempt
 * @param string $identifier Unique identifier
 * @param int $max_attempts Maximum attempts before block
 * @param int $period Time period in seconds
 * @return bool Whether user is now blocked
 */
function record_failed_attempt($identifier, $max_attempts = 5, $period = 900) {
    $identifier = sanitize_input($identifier);
    $hash = md5($identifier);
    $file = __DIR__ . "/../data/rate_limits/{$hash}.json";

    $now = time();
    $data = read_json($file);

    if (empty($data)) {
        $data = [
            'identifier' => $identifier,
            'attempts' => [],
            'blocked_until' => null
        ];
    }

    // Add new attempt
    $data['attempts'][] = $now;

    // Clean old attempts
    $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($now, $period) {
        return ($now - $timestamp) < $period;
    });

    $attempt_count = count($data['attempts']);

    // Block if max attempts reached
    if ($attempt_count >= $max_attempts) {
        $data['blocked_until'] = $now + $period;
        write_json($file, $data);

        // Log the block
        error_log("Rate limit: Blocked identifier '$identifier' until " . date('Y-m-d H:i:s', $data['blocked_until']));

        return true; // User is now blocked
    }

    write_json($file, $data);
    return false; // Not blocked yet
}

/**
 * Reset rate limit for identifier
 * @param string $identifier Unique identifier
 */
function reset_rate_limit($identifier) {
    $identifier = sanitize_input($identifier);
    $hash = md5($identifier);
    $file = __DIR__ . "/../data/rate_limits/{$hash}.json";

    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Clean old rate limit files (older than 24 hours)
 * Should be called periodically via cron or during low traffic
 */
function cleanup_rate_limits() {
    $dir = __DIR__ . '/../data/rate_limits/';
    if (!is_dir($dir)) {
        return;
    }

    $now = time();
    $files = glob($dir . '*.json');

    foreach ($files as $file) {
        // Delete files older than 24 hours
        if (($now - filemtime($file)) > 86400) {
            unlink($file);
        }
    }
}
