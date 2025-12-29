<?php
/**
 * Pseudo-Cron System
 * Sistema de procesamiento automático de cola de emails
 * Se ejecuta en cada visita al sitio con throttling inteligente
 */

/**
 * Ejecuta el pseudo-cron si es necesario
 * Solo procesa si pasó suficiente tiempo desde la última ejecución
 *
 * @param int $interval_seconds Intervalo mínimo entre ejecuciones (default: 60 segundos)
 * @return array|null Resultado del procesamiento o null si no se ejecutó
 */
function run_pseudo_cron($interval_seconds = 60) {
    $lock_file = APP_PATH . '/data/pseudo_cron_lock.json';

    // Leer última ejecución
    $last_run = null;
    if (file_exists($lock_file)) {
        $lock_data = @json_decode(file_get_contents($lock_file), true);
        $last_run = $lock_data['last_run'] ?? null;
    }

    // Verificar si es necesario ejecutar
    $now = time();
    if ($last_run && ($now - $last_run) < $interval_seconds) {
        // No ha pasado suficiente tiempo
        return null;
    }

    // Intentar adquirir lock (evitar ejecuciones concurrentes)
    $lock_acquired = @file_put_contents(
        $lock_file,
        json_encode(['last_run' => $now, 'pid' => getmypid()]),
        LOCK_EX
    );

    if (!$lock_acquired) {
        // No se pudo adquirir el lock, otra instancia está ejecutando
        return null;
    }

    // Ejecutar procesamiento de cola de emails
    try {
        $stats = process_email_queue(10); // Procesar hasta 10 emails

        // Actualizar estadísticas
        update_pseudo_cron_stats($stats);

        // Log success
        error_log("Pseudo-cron executed successfully: " . json_encode($stats));

        return $stats;
    } catch (Exception $e) {
        error_log("Pseudo-cron error: " . $e->getMessage());
        return [
            'error' => $e->getMessage(),
            'sent' => 0,
            'failed' => 0,
            'pending' => 0
        ];
    }
}

/**
 * Actualiza estadísticas del pseudo-cron
 */
function update_pseudo_cron_stats($stats) {
    $stats_file = APP_PATH . '/data/pseudo_cron_stats.json';

    $current_stats = file_exists($stats_file)
        ? json_decode(file_get_contents($stats_file), true)
        : [
            'total_executions' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
            'last_execution' => null,
            'executions_today' => 0,
            'last_reset_date' => date('Y-m-d')
        ];

    // Reset daily counter if it's a new day
    if ($current_stats['last_reset_date'] !== date('Y-m-d')) {
        $current_stats['executions_today'] = 0;
        $current_stats['last_reset_date'] = date('Y-m-d');
    }

    // Update stats
    $current_stats['total_executions']++;
    $current_stats['executions_today']++;
    $current_stats['total_sent'] += $stats['sent'] ?? 0;
    $current_stats['total_failed'] += $stats['failed'] ?? 0;
    $current_stats['last_execution'] = date('Y-m-d H:i:s');
    $current_stats['last_result'] = $stats;

    file_put_contents($stats_file, json_encode($current_stats, JSON_PRETTY_PRINT));
}

/**
 * Obtiene estadísticas del pseudo-cron
 */
function get_pseudo_cron_stats() {
    $stats_file = APP_PATH . '/data/pseudo_cron_stats.json';

    if (!file_exists($stats_file)) {
        return [
            'total_executions' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
            'last_execution' => null,
            'executions_today' => 0,
            'last_reset_date' => date('Y-m-d'),
            'last_result' => null
        ];
    }

    return json_decode(file_get_contents($stats_file), true);
}

/**
 * Verifica si hay un cron real configurado
 * Detecta si el script está siendo ejecutado por cron o por pseudo-cron
 */
function is_real_cron_active() {
    $lock_file = APP_PATH . '/data/pseudo_cron_lock.json';

    if (!file_exists($lock_file)) {
        return false;
    }

    $lock_data = json_decode(file_get_contents($lock_file), true);
    $last_run = $lock_data['last_run'] ?? 0;

    // Si la última ejecución fue hace menos de 2 minutos y es muy consistente,
    // probablemente hay un cron real
    // Esto es una heurística simple - podría mejorarse

    return false; // Por ahora, asumimos pseudo-cron
}

/**
 * Ejecuta el procesamiento manualmente (desde admin panel)
 */
function run_manual_email_processing() {
    try {
        $stats = process_email_queue(50); // Procesar hasta 50 emails en modo manual
        update_pseudo_cron_stats($stats);
        return [
            'success' => true,
            'stats' => $stats
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Obtiene información del sistema para la página de configuración
 */
function get_email_queue_system_info() {
    $queue_file = APP_PATH . '/data/email_queue.json';
    $failed_file = APP_PATH . '/data/email_failed.json';

    $queue = file_exists($queue_file) ? json_decode(file_get_contents($queue_file), true) : [];
    $failed = file_exists($failed_file) ? json_decode(file_get_contents($failed_file), true) : [];

    $pending_count = 0;
    $processing_count = 0;

    foreach ($queue as $email) {
        if ($email['status'] === 'pending') {
            $pending_count++;
        }
    }

    $stats = get_pseudo_cron_stats();

    return [
        'mode' => is_real_cron_active() ? 'cron' : 'pseudo-cron',
        'pending_emails' => $pending_count,
        'failed_emails' => count($failed),
        'last_execution' => $stats['last_execution'],
        'executions_today' => $stats['executions_today'],
        'total_sent' => $stats['total_sent'],
        'total_failed' => $stats['total_failed'],
        'last_result' => $stats['last_result']
    ];
}
