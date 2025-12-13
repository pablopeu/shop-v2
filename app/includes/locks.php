<?php
/**
 * Lock System for Preventing Race Conditions
 * Sistema de locks para prevenir condiciones de carrera
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Acquire a lock for a specific resource
 * Adquiere un lock para un recurso específico
 *
 * @param string $resource_type Tipo de recurso (ej: 'webhook', 'order', 'stock')
 * @param string $resource_id ID del recurso
 * @param int $timeout Tiempo máximo de espera para adquirir lock (segundos)
 * @return resource|false File pointer si adquirió el lock, false si falló
 */
function acquire_lock($resource_type, $resource_id, $timeout = 5) {
    $lock_dir = APP_PATH . '/data/locks';

    // Crear directorio de locks si no existe
    if (!is_dir($lock_dir)) {
        if (!mkdir($lock_dir, 0755, true)) {
            error_log("Failed to create locks directory: $lock_dir");
            return false;
        }
    }

    // Sanitizar nombres de archivo
    $resource_type = preg_replace('/[^a-z0-9_-]/i', '', $resource_type);
    $resource_id = preg_replace('/[^a-z0-9_-]/i', '', $resource_id);

    $lock_file = "$lock_dir/{$resource_type}_{$resource_id}.lock";

    // Abrir archivo de lock
    $fp = fopen($lock_file, 'c+');
    if (!$fp) {
        error_log("Failed to open lock file: $lock_file");
        return false;
    }

    // Intentar adquirir lock exclusivo sin bloquear
    // LOCK_EX = exclusive lock
    // LOCK_NB = non-blocking (retorna inmediatamente si no puede adquirir)
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        // No pudo adquirir el lock inmediatamente
        // Intentar con timeout
        $start_time = time();

        while (time() - $start_time < $timeout) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                // Lock adquirido
                write_lock_metadata($fp, $resource_type, $resource_id);
                return $fp;
            }

            // Esperar 50ms antes de reintentar
            usleep(50000);
        }

        // Timeout alcanzado sin adquirir lock
        fclose($fp);
        error_log("Failed to acquire lock after {$timeout}s: {$resource_type}/{$resource_id}");
        return false;
    }

    // Lock adquirido exitosamente
    write_lock_metadata($fp, $resource_type, $resource_id);
    return $fp;
}

/**
 * Release a previously acquired lock
 * Libera un lock previamente adquirido
 *
 * @param resource $lock_fp File pointer del lock
 * @param bool $delete_file Si debe eliminar el archivo de lock
 * @return bool True si se liberó correctamente
 */
function release_lock($lock_fp, $delete_file = true) {
    if (!is_resource($lock_fp)) {
        return false;
    }

    // Obtener metadata antes de cerrar
    $meta = stream_get_meta_data($lock_fp);
    $lock_file = $meta['uri'] ?? null;

    // Liberar lock
    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);

    // Eliminar archivo de lock si se solicitó
    if ($delete_file && $lock_file && file_exists($lock_file)) {
        @unlink($lock_file);
    }

    return true;
}

/**
 * Execute a callback with exclusive lock
 * Ejecuta un callback con lock exclusivo
 *
 * @param string $resource_type Tipo de recurso
 * @param string $resource_id ID del recurso
 * @param callable $callback Función a ejecutar con el lock
 * @param int $timeout Timeout para adquirir lock
 * @return mixed Resultado del callback o false si no pudo adquirir lock
 * @throws Exception Si el callback lanza excepción
 */
function with_lock($resource_type, $resource_id, callable $callback, $timeout = 5) {
    $lock_fp = acquire_lock($resource_type, $resource_id, $timeout);

    if (!$lock_fp) {
        error_log("with_lock failed: Could not acquire lock for {$resource_type}/{$resource_id}");
        return false;
    }

    try {
        $result = $callback();
        release_lock($lock_fp, true);
        return $result;
    } catch (Exception $e) {
        // Asegurar que el lock se libere incluso si hay excepción
        release_lock($lock_fp, true);
        throw $e;
    }
}

/**
 * Check if a resource is currently locked
 * Verifica si un recurso está actualmente bloqueado
 *
 * @param string $resource_type Tipo de recurso
 * @param string $resource_id ID del recurso
 * @return bool True si está bloqueado
 */
function is_locked($resource_type, $resource_id) {
    $lock_dir = APP_PATH . '/data/locks';

    $resource_type = preg_replace('/[^a-z0-9_-]/i', '', $resource_type);
    $resource_id = preg_replace('/[^a-z0-9_-]/i', '', $resource_id);

    $lock_file = "$lock_dir/{$resource_type}_{$resource_id}.lock";

    if (!file_exists($lock_file)) {
        return false;
    }

    // Intentar abrir y adquirir lock no bloqueante
    $fp = @fopen($lock_file, 'r');
    if (!$fp) {
        return false;
    }

    $is_locked = !flock($fp, LOCK_EX | LOCK_NB);

    if (!$is_locked) {
        // Pudimos adquirirlo, entonces no estaba bloqueado
        flock($fp, LOCK_UN);
    }

    fclose($fp);
    return $is_locked;
}

/**
 * Cleanup old lock files (older than threshold)
 * Limpia archivos de lock antiguos (más viejos que el umbral)
 *
 * @param int $max_age_seconds Edad máxima en segundos (default: 1 hora)
 * @return int Número de locks eliminados
 */
function cleanup_old_locks($max_age_seconds = 3600) {
    $lock_dir = APP_PATH . '/data/locks';

    if (!is_dir($lock_dir)) {
        return 0;
    }

    $now = time();
    $count = 0;

    $files = glob($lock_dir . '/*.lock');

    foreach ($files as $file) {
        $age = $now - filemtime($file);

        if ($age > $max_age_seconds) {
            // Verificar que no esté actualmente bloqueado
            $fp = @fopen($file, 'r');
            if ($fp) {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    // No está bloqueado, seguro para eliminar
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    @unlink($file);
                    $count++;
                } else {
                    // Está bloqueado, no eliminar
                    fclose($fp);
                }
            }
        }
    }

    return $count;
}

/**
 * Write lock metadata to lock file
 * Escribe metadata al archivo de lock
 *
 * @param resource $fp File pointer
 * @param string $resource_type Tipo de recurso
 * @param string $resource_id ID del recurso
 */
function write_lock_metadata($fp, $resource_type, $resource_id) {
    $metadata = json_encode([
        'resource_type' => $resource_type,
        'resource_id' => $resource_id,
        'pid' => getmypid(),
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'script' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown'
    ], JSON_PRETTY_PRINT);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $metadata);
    fflush($fp);
}

/**
 * Read lock metadata from lock file
 * Lee metadata del archivo de lock
 *
 * @param string $resource_type Tipo de recurso
 * @param string $resource_id ID del recurso
 * @return array|null Metadata o null si no existe
 */
function read_lock_metadata($resource_type, $resource_id) {
    $lock_dir = APP_PATH . '/data/locks';

    $resource_type = preg_replace('/[^a-z0-9_-]/i', '', $resource_type);
    $resource_id = preg_replace('/[^a-z0-9_-]/i', '', $resource_id);

    $lock_file = "$lock_dir/{$resource_type}_{$resource_id}.lock";

    if (!file_exists($lock_file)) {
        return null;
    }

    $content = @file_get_contents($lock_file);
    if (!$content) {
        return null;
    }

    return json_decode($content, true);
}
