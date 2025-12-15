<?php
/**
 * Sistema de Rotación y Archivado de Logs
 * Maneja la rotación automática de archivos de log para prevenir crecimiento descontrolado
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Rotar logs si exceden el tamaño máximo
 *
 * @param string $log_file Ruta completa al archivo de log
 * @param int $max_size_mb Tamaño máximo en MB antes de rotar (default: 10MB)
 * @param int $keep_rotations Número de rotaciones a mantener (default: 5)
 * @return bool True si se rotó, false si no fue necesario
 */
function rotate_log_if_needed($log_file, $max_size_mb = 10, $keep_rotations = 5) {
    if (!file_exists($log_file)) {
        return false;
    }

    $max_size_bytes = $max_size_mb * 1024 * 1024;
    $current_size = filesize($log_file);

    // No necesita rotación
    if ($current_size < $max_size_bytes) {
        return false;
    }

    // Rotar archivos existentes
    for ($i = $keep_rotations - 1; $i >= 1; $i--) {
        $old_file = $log_file . '.' . $i;
        $new_file = $log_file . '.' . ($i + 1);

        if (file_exists($old_file)) {
            // Si llegamos al límite de rotaciones, eliminar el más antiguo
            if ($i == $keep_rotations - 1) {
                unlink($old_file);
            } else {
                rename($old_file, $new_file);
            }
        }
    }

    // Mover el log actual a .1
    rename($log_file, $log_file . '.1');

    // Crear nuevo archivo vacío
    touch($log_file);
    chmod($log_file, 0640);

    error_log("Log rotado: $log_file (tamaño: " . round($current_size / 1024 / 1024, 2) . " MB)");

    return true;
}

/**
 * Archivar logs antiguos (comprimir con gzip)
 *
 * @param string $log_file Ruta al archivo de log rotado
 * @return bool True si se archivó exitosamente
 */
function archive_rotated_log($log_file) {
    if (!file_exists($log_file)) {
        return false;
    }

    // Solo archivar logs .1 o superiores (no el actual)
    if (!preg_match('/\.\d+$/', $log_file)) {
        return false;
    }

    $gzip_file = $log_file . '.gz';

    // Si ya existe el archivo comprimido, no volver a comprimir
    if (file_exists($gzip_file)) {
        return false;
    }

    // Comprimir el archivo
    $fp_in = fopen($log_file, 'rb');
    $fp_out = gzopen($gzip_file, 'wb9'); // Máxima compresión

    if (!$fp_in || !$fp_out) {
        return false;
    }

    while (!feof($fp_in)) {
        gzwrite($fp_out, fread($fp_in, 1024 * 1024)); // Leer 1MB a la vez
    }

    fclose($fp_in);
    gzclose($fp_out);

    // Eliminar el archivo original después de comprimir
    unlink($log_file);

    error_log("Log archivado y comprimido: $gzip_file");

    return true;
}

/**
 * Eliminar logs archivados más antiguos que X días
 *
 * @param string $logs_dir Directorio de logs
 * @param int $days_to_keep Días a mantener (default: 90)
 * @return int Número de archivos eliminados
 */
function cleanup_old_logs($logs_dir, $days_to_keep = 90) {
    if (!is_dir($logs_dir)) {
        return 0;
    }

    $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
    $deleted_count = 0;

    $files = glob($logs_dir . '/*.gz');

    foreach ($files as $file) {
        $file_time = filemtime($file);

        if ($file_time < $cutoff_time) {
            unlink($file);
            $deleted_count++;
            error_log("Log antiguo eliminado: $file (antigüedad: " . round((time() - $file_time) / 86400) . " días)");
        }
    }

    return $deleted_count;
}

/**
 * Rotar todos los logs del sistema
 *
 * @return array Estadísticas de rotación
 */
function rotate_all_system_logs() {
    $stats = [
        'rotated' => 0,
        'archived' => 0,
        'deleted' => 0,
        'errors' => []
    ];

    // Lista de logs a rotar
    $logs_to_rotate = [
        APP_PATH . '/data/logs/security.log' => ['max_size' => 10, 'keep' => 5],
        APP_PATH . '/data/logs/admin_actions.log' => ['max_size' => 10, 'keep' => 5],
        APP_PATH . '/data/logs/mp_logs.json' => ['max_size' => 20, 'keep' => 10],
        APP_PATH . '/data/logs/webhook_log.json' => ['max_size' => 20, 'keep' => 10],
        APP_PATH . '/data/logs/errors.log' => ['max_size' => 50, 'keep' => 10],
    ];

    foreach ($logs_to_rotate as $log_file => $config) {
        try {
            if (rotate_log_if_needed($log_file, $config['max_size'], $config['keep'])) {
                $stats['rotated']++;

                // Archivar el log rotado (.1)
                if (archive_rotated_log($log_file . '.1')) {
                    $stats['archived']++;
                }
            }
        } catch (Exception $e) {
            $stats['errors'][] = "Error rotando $log_file: " . $e->getMessage();
        }
    }

    // Limpiar logs antiguos (mayores a 90 días)
    $logs_dir = APP_PATH . '/data/logs';
    $stats['deleted'] = cleanup_old_logs($logs_dir, 90);

    return $stats;
}

/**
 * Sanitizar IPs en logs antes de escribir
 * Hash de IPs para anonimizar datos personales (GDPR compliance)
 *
 * @param string $ip Dirección IP
 * @return string IP hasheada
 */
function anonymize_ip_for_log($ip) {
    // Usar hash con salt secreto del sistema
    $salt = defined('APP_SECRET_KEY') ? APP_SECRET_KEY : 'default_salt_change_me';
    return substr(hash('sha256', $ip . $salt), 0, 16);
}

/**
 * Función helper para log seguro con IP anonimizada
 *
 * @param string $message Mensaje a logear
 * @param string $level Nivel: info, warning, error
 * @param array $context Contexto adicional
 */
function secure_log($message, $level = 'info', $context = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'ip_hash' => anonymize_ip_for_log($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
        'context' => $context
    ];

    $log_file = APP_PATH . '/data/logs/security.log';
    $log_dir = dirname($log_file);

    // Crear directorio si no existe
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    // Escribir log
    file_put_contents(
        $log_file,
        json_encode($log_entry) . "\n",
        FILE_APPEND | LOCK_EX
    );

    // Verificar si necesita rotación después de escribir
    rotate_log_if_needed($log_file);
}
