<?php
/**
 * Admin - System Backup
 * Create, download and manage complete site backups
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');

// Page title for header
$page_title = '💾 Backup del Sistema';

// Backup del código privado crítico (APP_PATH contiene: config, data, includes, pages)
$backup_source = APP_PATH;
$backups_dir = APP_PATH . '/data/backups';

// Create backups directory if it doesn't exist
if (!file_exists($backups_dir)) {
    mkdir($backups_dir, 0700, true);
}

// Handle messages
$message = '';
$error = '';

// Check for session messages (from redirects)
if (isset($_SESSION['success_msg'])) {
    $message = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

/**
 * Get list of existing backups
 */
function getBackupsList($backups_dir) {
    $backups = [];

    if (!is_dir($backups_dir)) {
        return $backups;
    }

    $files = scandir($backups_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        if (preg_match('/^backup_(\d{8}_\d{6})\.tar\.gz$/', $file, $matches)) {
            $filepath = $backups_dir . '/' . $file;
            $backups[] = [
                'filename' => $file,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'date' => filemtime($filepath),
                'formatted_date' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }

    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    return $backups;
}

/**
 * Format bytes to human readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get available disk space in MB
 */
function getAvailableSpace($path) {
    $free = disk_free_space($path);
    return $free ? round($free / 1024 / 1024, 2) : 0;
}

/**
 * Recursive copy of directory with exclusions
 */
function recursiveCopy($source, $dest, $exclude = []) {
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($dest)) {
        mkdir($dest, 0700, true);
    }

    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        // Check exclusions - mejorado para detectar correctamente carpetas
        $skip = false;
        $source_path = $source . '/' . $file;

        foreach ($exclude as $excluded) {
            // Verificar si el nombre del archivo/carpeta coincide con la exclusión
            if ($file === $excluded) {
                $skip = true;
                break;
            }

            // Verificar si la ruta contiene la exclusión
            if (strpos($source_path, '/' . $excluded) !== false) {
                $skip = true;
                break;
            }

            // Verificar exclusiones con wildcards (temp_backup_*)
            if (strpos($excluded, '*') !== false) {
                $pattern = '/^' . str_replace('*', '.*', preg_quote($excluded, '/')) . '$/';
                if (preg_match($pattern, $file)) {
                    $skip = true;
                    break;
                }
            }
        }

        if ($skip) {
            error_log(">>> Excluyendo: " . $source_path);
            continue;
        }

        $dest_path = $dest . '/' . $file;

        if (is_dir($source_path)) {
            recursiveCopy($source_path, $dest_path, $exclude);
        } else {
            copy($source_path, $dest_path);
        }
    }

    closedir($dir);
    return true;
}

/**
 * Recursive delete of directory
 */
function recursiveDelete($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            recursiveDelete($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
    return true;
}

/**
 * Get paths to secret files and their parent directories from configuration
 */
function getSecretFilePaths() {
    $secret_files = [];

    // Payment credentials path
    $payment_path_file = APP_PATH . '/.payment_credentials_path';
    if (file_exists($payment_path_file)) {
        $payment_path = trim(file_get_contents($payment_path_file));
        if (file_exists($payment_path)) {
            $secret_files[] = $payment_path;
        }
    }

    // System credentials path (SMTP, Telegram)
    $credentials_path_file = APP_PATH . '/.credentials_path';
    if (file_exists($credentials_path_file)) {
        $credentials_path = trim(file_get_contents($credentials_path_file));
        if (file_exists($credentials_path)) {
            $secret_files[] = $credentials_path;
        }
    }

    // Users path
    $users_path_file = APP_PATH . '/.users_path';
    if (file_exists($users_path_file)) {
        $users_path = trim(file_get_contents($users_path_file));
        if (file_exists($users_path)) {
            $secret_files[] = $users_path;
        }
    }

    return array_unique($secret_files);
}

/**
 * Get unique directories containing secret files
 * Groups secret files by their parent directory
 */
function getSecretDirectories() {
    $secret_files = getSecretFilePaths();
    $directories = [];

    foreach ($secret_files as $file_path) {
        $dir = dirname($file_path);
        $dir_name = basename($dir);

        if (!isset($directories[$dir])) {
            $directories[$dir] = [
                'path' => $dir,
                'name' => $dir_name,
                'files' => []
            ];
        }

        $directories[$dir]['files'][] = basename($file_path);
    }

    return $directories;
}

/**
 * Create complete system backup with full directory structure
 */
function createBackup($backup_source, $backups_dir) {
    // Aumentar tiempo de ejecución para backups grandes
    set_time_limit(300); // 5 minutos
    ini_set('memory_limit', '512M');

    error_log(">>> createBackup() INICIADA");
    error_log(">>> backup_source: " . $backup_source);
    error_log(">>> backups_dir: " . $backups_dir);

    $timestamp = date('Ymd_His');
    $backup_filename = "backup_{$timestamp}.tar.gz";
    $backup_filepath = $backups_dir . '/' . $backup_filename;

    error_log(">>> backup_filename: " . $backup_filename);
    error_log(">>> backup_filepath: " . $backup_filepath);

    // Check available space (require at least 500MB)
    $available_mb = getAvailableSpace($backups_dir);
    error_log(">>> Espacio disponible: " . $available_mb . " MB");

    if ($available_mb < 500) {
        error_log(">>> ERROR: Espacio insuficiente");
        return [
            'success' => false,
            'message' => "Espacio insuficiente en disco. Disponible: {$available_mb}MB. Mínimo requerido: 500MB"
        ];
    }

    // Create backup using PHP PharData
    error_log(">>> Creando backup con PharData");

    try {
        // Create temporary directory for backup structure FUERA de backups_dir
        // para evitar recursión infinita al copiar
        $temp_dir = dirname($backups_dir) . '/temp_backup_' . $timestamp;
        if (!mkdir($temp_dir, 0700, true)) {
            throw new Exception('No se pudo crear directorio temporal');
        }

        error_log(">>> Directorio temporal: " . $temp_dir);

        // Crear estructura: raiz/ y public_html/
        $raiz_dir = $temp_dir . '/raiz';
        $public_dir = $temp_dir . '/public_html';

        mkdir($raiz_dir, 0700, true);
        mkdir($public_dir, 0700, true);

        // 1. RAIZ: Copy shop-v2-app directory (APP_PATH)
        error_log(">>> [RAIZ] Copiando shop-v2-app...");
        $app_dest = $raiz_dir . '/shop-v2-app';
        // Excluir: carpeta backups, el directorio temporal actual, y .git
        $temp_dir_name = basename($temp_dir);
        error_log(">>> Excluyendo directorio temporal: " . $temp_dir_name);
        recursiveCopy(APP_PATH, $app_dest, ['backups', $temp_dir_name, 'temp_backup_*', '.git']);

        // 2. RAIZ: Copy secret directories (maintaining folder structure)
        $secret_directories = getSecretDirectories();
        error_log(">>> [RAIZ] Carpetas de secretos encontradas: " . count($secret_directories));

        foreach ($secret_directories as $dir_info) {
            $source_dir = $dir_info['path'];
            $dir_name = $dir_info['name'];
            $dest_dir = $raiz_dir . '/' . $dir_name;

            // Copy entire directory - USAR LAS MISMAS EXCLUSIONES
            if (is_dir($source_dir)) {
                recursiveCopy($source_dir, $dest_dir, ['backups', $temp_dir_name, 'temp_backup_*', '.git']);
                error_log(">>> [RAIZ] Copiada carpeta completa: " . $dir_name . " (" . count($dir_info['files']) . " archivos)");
            } else {
                // Fallback: if it's not a directory, copy just the files
                foreach ($dir_info['files'] as $filename) {
                    $source_file = $source_dir . '/' . $filename;
                    $dest_file = $raiz_dir . '/' . $filename;
                    if (file_exists($source_file) && copy($source_file, $dest_file)) {
                        error_log(">>> [RAIZ] Copiado archivo: " . $filename);
                    }
                }
            }
        }

        // 3. PUBLIC_HTML: Copy public directory contents (TODO EL CONTENIDO)
        if (defined('PUBLIC_PATH')) {
            // Usar el nombre de la carpeta real del public_path (dinámico)
            $public_folder_name = basename(PUBLIC_PATH);
            error_log(">>> [PUBLIC_HTML] Copiando $public_folder_name...");

            // Crear estructura public_html/[nombre-carpeta]/
            $public_dest = $public_dir . '/' . $public_folder_name;
            mkdir($public_dest, 0700, true);

            // Copiar TODO el contenido de PUBLIC_PATH (código PHP, assets, uploads, etc.)
            recursiveCopy(PUBLIC_PATH, $public_dest, ['.git']);
            error_log(">>> [PUBLIC_HTML] Copiado TODO el contenido de $public_folder_name/");
        }

        // Create tar.gz from temp directory
        $temp_tar = $backup_filepath . '.temp.tar';
        error_log(">>> Creando archivo tar...");

        $phar = new PharData($temp_tar);
        $phar->buildFromDirectory($temp_dir);

        // Compress to .tar.gz
        error_log(">>> Comprimiendo a gzip...");
        $phar->compress(Phar::GZ);

        // Remove temporary .tar file
        @unlink($temp_tar);

        // Rename .tar.gz file to final name
        if (file_exists($temp_tar . '.gz')) {
            rename($temp_tar . '.gz', $backup_filepath);
        }

        // Clean up temporary directory
        error_log(">>> Limpiando directorio temporal...");
        recursiveDelete($temp_dir);

        error_log(">>> Backup creado exitosamente");

    } catch (Exception $e) {
        error_log(">>> ERROR: " . $e->getMessage());

        // Clean up on error
        if (isset($temp_dir) && is_dir($temp_dir)) {
            recursiveDelete($temp_dir);
        }
        if (isset($temp_tar) && file_exists($temp_tar)) {
            @unlink($temp_tar);
        }
        if (isset($temp_tar) && file_exists($temp_tar . '.gz')) {
            @unlink($temp_tar . '.gz');
        }

        return [
            'success' => false,
            'message' => 'Error al crear el backup: ' . $e->getMessage()
        ];
    }

    // Verify backup was created
    if (!file_exists($backup_filepath)) {
        error_log(">>> ERROR: El archivo no existe después de crear");
        return [
            'success' => false,
            'message' => 'El archivo de backup no se creó. Verifica permisos del directorio: ' . $backups_dir
        ];
    }

    if (filesize($backup_filepath) === 0) {
        error_log(">>> ERROR: El archivo está vacío");
        return [
            'success' => false,
            'message' => 'El archivo de backup se creó pero está vacío'
        ];
    }

    error_log(">>> Backup exitoso: " . formatBytes(filesize($backup_filepath)));

    // Set restrictive permissions on backup file
    chmod($backup_filepath, 0600);

    // Clean old backups (keep only last 10)
    $backups = getBackupsList($backups_dir);
    if (count($backups) > 10) {
        $to_delete = array_slice($backups, 10);
        foreach ($to_delete as $backup) {
            @unlink($backup['filepath']);
        }
    }

    return [
        'success' => true,
        'filename' => $backup_filename,
        'filepath' => $backup_filepath,
        'size' => filesize($backup_filepath)
    ];
}

// Handle AJAX backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode([
            'success' => false,
            'message' => 'Token de seguridad inválido'
        ]);
        exit;
    }

    // Create backup
    if (isset($_POST['create_backup'])) {
        $result = createBackup($backup_source, $backups_dir);

        if ($result['success']) {
            $size_formatted = formatBytes($result['size']);
            echo json_encode([
                'success' => true,
                'message' => "Backup creado exitosamente",
                'filename' => $result['filename'],
                'size' => $size_formatted
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        exit;
    }
}

// Handle regular form submissions (delete backup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_msg'] = '❌ Token de seguridad inválido';
        header('Location: ?page=config-backup');
        exit;
    }

    // Delete backup
    if (isset($_POST['delete_backup'])) {
        $filename = sanitize_input($_POST['backup_filename'] ?? '');
        $filepath = $backups_dir . '/' . $filename;

        // Security: verify filename is valid backup format
        if (preg_match('/^backup_\d{8}_\d{6}\.tar\.gz$/', $filename) && file_exists($filepath)) {
            if (unlink($filepath)) {
                $_SESSION['success_msg'] = "✅ Backup eliminado: {$filename}";
            } else {
                $_SESSION['error_msg'] = "❌ Error al eliminar el backup";
            }
        } else {
            $_SESSION['error_msg'] = "❌ Backup no válido o no existe";
        }

        // Redirect to prevent form resubmission
        header('Location: ?page=config-backup');
        exit;
    }
}

// Handle download
if (isset($_GET['download'])) {
    $filename = sanitize_input($_GET['download']);
    $filepath = $backups_dir . '/' . $filename;

    // Security: verify filename is valid backup format
    if (preg_match('/^backup_\d{8}_\d{6}\.tar\.gz$/', $filename) && file_exists($filepath)) {
        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($filepath);
        exit;
    } else {
        $_SESSION['error_msg'] = "❌ Backup no válido o no existe";
        header('Location: ?page=config-backup');
        exit;
    }
}

// Get list of backups
$backups_list = getBackupsList($backups_dir);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }

        /* Layout */
        .main-content { margin-left: 260px; padding: 20px; max-width: 1200px; }

        /* Header */
        .content-header { margin-bottom: 20px; }
        .content-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
        .content-header p { color: #6c757d; font-size: 14px; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }

        /* Cards */
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .card-header h2 { font-size: 18px; color: #2c3e50; }
        .card-body { }
        .card-warning { border-left: 4px solid #ff9800; }

        /* Buttons */
        .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .btn-lg { padding: 14px 28px; font-size: 16px; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: #f8f9fa; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { font-weight: 600; color: #495057; font-size: 14px; }
        .table td { color: #6c757d; font-size: 14px; }
        .table tbody tr:hover { background: #f8f9fa; }
        .table code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .info-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .info-item strong { display: block; margin-bottom: 5px; color: #495057; font-size: 13px; }
        .info-item code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }

        /* Backup Info */
        .backup-info h4 { color: #495057; margin-bottom: 10px; font-size: 1.1em; }
        .backup-info ul { margin-left: 20px; color: #6c757d; }
        .backup-info ul li { margin-bottom: 5px; }

        /* Utilities */
        .text-danger { color: #dc3545 !important; }
        .text-success { color: #28a745 !important; }
        .text-muted { color: #6c757d !important; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 15px; }
            .info-grid { grid-template-columns: 1fr; }
            .table { font-size: 13px; }
            .btn { width: 100%; margin-bottom: 5px; }
            .progress-container { padding: 30px 20px; }
        }
    </style>
    <?php include APP_PATH . '/includes/admin/admin-common-styles.php'; ?>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>
            <div class="content-header">
                <h1><?php echo $page_title; ?></h1>
                <p>Crear y gestionar copias de seguridad completas del sitio</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>📊 Información del Sistema</h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Directorio a Respaldar:</strong>
                            <code><?php echo htmlspecialchars($backup_source); ?></code>
                        </div>
                        <div class="info-item">
                            <strong>Directorio de Backups:</strong>
                            <code><?php echo htmlspecialchars($backups_dir); ?></code>
                        </div>
                        <div class="info-item">
                            <strong>Espacio Disponible:</strong>
                            <span class="<?php echo getAvailableSpace($backups_dir) < 500 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo getAvailableSpace($backups_dir); ?> MB
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Backups Almacenados:</strong>
                            <?php echo count($backups_list); ?> / 10 (máximo)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Backup -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>💾 Crear Backup Completo</h2>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        El backup incluirá <strong>TODA la estructura del sistema</strong> organizada para fácil restauración.
                    </p>

                    <div class="backup-info mb-3">
                        <h4>📦 Estructura del Backup:</h4>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 10px 0; font-family: monospace; font-size: 13px;">
                            backup_YYYYMMDD_HHMMSS.tar.gz/<br>
                            ├── <strong>raiz/</strong> (todo lo que va en /home2/uv0023/)<br>
                            │   ├── shop-v2-app/<br>
                            │   ├── <em>[carpeta-secretos-sistema]/</em> *<br>
                            │   ├── <em>[carpeta-secretos-pago]/</em> *<br>
                            │   └── <em>[carpeta-secretos-usuarios]/</em> *<br>
                            └── <strong>public_html/</strong> (todo lo que va en /home2/uv0023/public_html/)<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;└── shopv2/<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── index.php<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── webhook.php<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── admin/<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── api/<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── assets/<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;├── uploads/<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└── .htaccess<br>
                            <br>
                            <small style="color: #6c757d;">* Carpetas configuradas en /admin/?page=config-rutas-sistema</small>
                        </div>

                        <h4 class="mt-3">✅ Contenido Incluido:</h4>
                        <ul>
                            <li><strong>📁 raiz/shop-v2-app/</strong>
                                <ul style="margin-left: 20px; margin-top: 5px;">
                                    <li>⚙️ Configuraciones: site.json, payment.json, theme.json</li>
                                    <li>💾 Datos: productos, pedidos, cupones, promociones, reviews</li>
                                    <li>📁 Código PHP: includes, pages, funciones</li>
                                </ul>
                            </li>
                            <li><strong>🔐 raiz/carpetas_de_secretos/</strong> (configuradas en admin)
                                <ul style="margin-left: 20px; margin-top: 5px;">
                                    <li>Carpeta de secretos de sistema (SMTP, Telegram, usuarios)</li>
                                    <li>Carpeta de secretos de pago (MercadoPago)</li>
                                    <li>Se copian las <strong>carpetas completas</strong>, no solo archivos individuales</li>
                                    <li>Rutas leídas desde: /admin/?page=config-rutas-sistema</li>
                                </ul>
                            </li>
                            <li><strong>🌐 public_html/shopv2/</strong> (TODO el código público)
                                <ul style="margin-left: 20px; margin-top: 5px;">
                                    <li>📄 index.php, webhook.php</li>
                                    <li>📁 admin/, api/, assets/</li>
                                    <li>🖼️ uploads/ (imágenes y archivos subidos)</li>
                                    <li>🔧 .htaccess (configuración de URLs)</li>
                                </ul>
                            </li>
                        </ul>

                        <h4 class="mt-3">❌ Excluye:</h4>
                        <ul>
                            <li>📦 /app/data/backups (evita recursión)</li>
                            <li>🔧 .git (control de versiones - no necesario en backup)</li>
                        </ul>

                        <h4 class="mt-3">🔄 Restauración Fácil:</h4>
                        <ol style="margin-left: 20px; color: #6c757d; font-size: 14px;">
                            <li>Extraer <code>backup_YYYYMMDD_HHMMSS.tar.gz</code></li>
                            <li>Copiar <strong>todo</strong> el contenido de <code>raiz/*</code> → <code>/home2/uv0023/</code>
                                <ul style="margin-top: 5px;">
                                    <li>Incluye: shop-v2-app/ y todas las carpetas de secretos</li>
                                </ul>
                            </li>
                            <li>Copiar contenido de <code>public_html/shopv2/*</code> → <code>/home2/uv0023/public_html/shopv2/</code></li>
                        </ol>
                        <p class="mt-2" style="color: #6c757d; font-size: 13px;">
                            <strong>Importante:</strong> Las carpetas de secretos se restauran automáticamente con sus rutas originales.
                        </p>
                    </div>

                    <form method="POST" id="backupForm" data-onsubmit="confirmBackup">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="create_backup" value="1">
                        <button type="submit" class="btn btn-primary btn-lg">
                            💾 Crear Backup Ahora
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Backups -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>📦 Backups Disponibles</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($backups_list)): ?>
                        <p class="text-muted">No hay backups disponibles. Crea uno usando el botón de arriba.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>📅 Fecha y Hora</th>
                                        <th>📦 Archivo</th>
                                        <th>💾 Tamaño</th>
                                        <th>⚙️ Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups_list as $backup): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($backup['formatted_date']); ?></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($backup['filename']); ?></code>
                                            </td>
                                            <td><?php echo formatBytes($backup['size']); ?></td>
                                            <td>
                                                <a href="?page=config-backup&download=<?php echo urlencode($backup['filename']); ?>"
                                                   class="btn btn-sm btn-primary"
                                                   title="Descargar backup">
                                                    📥 Descargar
                                                </a>

                                                <form method="POST" style="display: inline;" id="deleteForm_<?php echo htmlspecialchars($backup['filename']); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                    <input type="hidden" name="delete_backup" value="1">
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger"
                                                            title="Eliminar backup"
                                                            data-action="confirmDelete"
                                                            data-filename="<?php echo htmlspecialchars($backup['filename'], ENT_QUOTES); ?>">
                                                        🗑️ Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

    </div>

    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <style nonce="<?= csp_nonce() ?>">
        /* Progress bar dentro del modal */
        #modalProgressContainer {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        #modalProgressContainer.active {
            display: block;
        }
        .modal-progress-bar-container {
            width: 100%;
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin: 15px 0;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .modal-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            border-radius: 6px;
            transition: width 0.3s ease, background 0.5s ease;
            position: relative;
        }
        .modal-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .modal-success-message {
            color: #28a745;
            font-weight: 600;
            margin: 15px 0;
        }
        .modal-error-message {
            color: #dc3545;
            font-weight: 600;
            margin: 15px 0;
        }
    </style>

    <script nonce="<?= csp_nonce() ?>">
        // Confirmar creación de backup usando modal con progress integrado
        function confirmBackup(event) {
            event.preventDefault();

            const modal = document.getElementById('confirmModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDetails = document.getElementById('modalDetails');
            const confirmBtn = document.getElementById('modalConfirmBtn');
            const cancelBtn = document.getElementById('modalCancelBtn');
            const modalActions = modal.querySelector('.modal-actions');

            // Configurar modal inicial
            modalIcon.textContent = '💾';
            modalIcon.className = 'modal-icon info';
            modalTitle.textContent = 'Crear Backup Completo';
            modalMessage.textContent = '¿Deseas crear un backup completo del sitio?';
            modalDetails.innerHTML = `
                <strong>📥</strong> Una vez completado, aparecerá en la lista de backups disponibles para descarga.<br><br>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 10px; border-radius: 4px;">
                    <strong style="color: #856404;">⚠️ ADVERTENCIA DE SEGURIDAD:</strong><br>
                    <span style="color: #856404;">El backup contiene credenciales sensibles (claves API, configuraciones, datos).<br>
                    <strong>NO lo compartas públicamente ni lo subas a repositorios.</strong></span>
                </div>
            `;
            modalDetails.style.display = 'block';

            confirmBtn.textContent = 'Crear Backup';
            confirmBtn.className = 'modal-btn modal-btn-confirm';
            cancelBtn.textContent = 'Cancelar';

            // Limpiar progress container anterior si existe
            const oldProgress = document.getElementById('modalProgressContainer');
            if (oldProgress) {
                oldProgress.remove();
            }

            // Mostrar modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Remover listeners anteriores y agregar nuevo
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            // Listener para cancelar
            newCancelBtn.addEventListener('click', function() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });

            // Listener para confirmar - NO cierra el modal
            newConfirmBtn.addEventListener('click', function() {
                // Deshabilitar botones
                newConfirmBtn.disabled = true;
                newCancelBtn.disabled = true;
                newConfirmBtn.style.opacity = '0.5';
                newCancelBtn.style.opacity = '0.5';
                newConfirmBtn.style.cursor = 'not-allowed';
                newCancelBtn.style.cursor = 'not-allowed';

                // Crear y agregar progress container
                const progressContainer = document.createElement('div');
                progressContainer.id = 'modalProgressContainer';
                progressContainer.className = 'active';
                progressContainer.innerHTML = `
                    <p style="color: #6c757d; text-align: center; margin: 10px 0;">
                        <strong>Creando backup...</strong><br>
                        Por favor espera.
                    </p>
                    <div class="modal-progress-bar-container">
                        <div class="modal-progress-bar" id="modalProgressBar" style="width: 0%;"></div>
                    </div>
                    <div id="modalResult"></div>
                `;
                modalActions.parentNode.insertBefore(progressContainer, modalActions.nextSibling);

                // Tiempo mínimo del progress bar (6 segundos)
                const MIN_DURATION = 6000;
                const startTime = Date.now();

                // Animar progress bar
                const progressBar = document.getElementById('modalProgressBar');
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 1;
                    if (progress <= 95) {
                        progressBar.style.width = progress + '%';
                    }
                }, MIN_DURATION / 95); // Distribuir el progreso en el tiempo mínimo

                // Enviar backup via AJAX
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                formData.append('create_backup', '1');
                formData.append('ajax', '1');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Calcular tiempo restante para alcanzar los 6 segundos mínimos
                    const elapsedTime = Date.now() - startTime;
                    const remainingTime = Math.max(0, MIN_DURATION - elapsedTime);

                    // Esperar el tiempo restante antes de mostrar resultado
                    setTimeout(() => {
                        clearInterval(progressInterval);
                        progressBar.style.width = '100%';

                        const resultDiv = document.getElementById('modalResult');
                        const progressText = progressContainer.querySelector('p');
                        const progressBarContainer = progressContainer.querySelector('.modal-progress-bar-container');

                        if (data.success) {
                            // Cambiar color del progress bar a verde
                            progressBar.style.background = 'linear-gradient(90deg, #28a745, #20c997)';

                            progressText.innerHTML = `
                                <strong style="color: #28a745;">✅ Backup completado</strong>
                            `;

                            // Agregar información del archivo debajo del progress bar
                            resultDiv.innerHTML = `
                                <div style="color: #6c757d; margin-top: 15px; text-align: center;">
                                    <strong>Archivo:</strong> ${data.filename}<br>
                                    <strong>Tamaño:</strong> ${data.size}
                                </div>
                                <button class="modal-btn modal-btn-confirm" data-action="reloadBackupPage" style="margin-top: 20px;">
                                    Cerrar y Actualizar
                                </button>
                            `;
                        } else {
                            // Cambiar color del progress bar a rojo
                            progressBar.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';

                            progressText.innerHTML = `
                                <strong style="color: #dc3545;">❌ Error al crear backup</strong>
                            `;

                            // Agregar botón de cerrar
                            resultDiv.innerHTML = `
                                <div style="color: #6c757d; margin-top: 15px; text-align: center;">
                                    ${data.message}
                                </div>
                                <button class="modal-btn modal-btn-cancel" data-action="closeModalAndCleanup" style="margin-top: 20px;">
                                    Cerrar
                                </button>
                            `;
                        }
                    }, remainingTime);
                })
                .catch(error => {
                    // Calcular tiempo restante para alcanzar los 6 segundos mínimos
                    const elapsedTime = Date.now() - startTime;
                    const remainingTime = Math.max(0, MIN_DURATION - elapsedTime);

                    setTimeout(() => {
                        clearInterval(progressInterval);
                        progressBar.style.width = '100%';
                        progressBar.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';

                        const resultDiv = document.getElementById('modalResult');
                        const progressText = progressContainer.querySelector('p');

                        progressText.innerHTML = `
                            <strong style="color: #dc3545;">❌ Error de conexión</strong>
                        `;

                        resultDiv.innerHTML = `
                            <div style="color: #6c757d; margin-top: 15px; text-align: center;">
                                ${error.message}
                            </div>
                            <button class="modal-btn modal-btn-cancel" data-action="closeModalAndCleanup" style="margin-top: 20px;">
                                Cerrar
                            </button>
                        `;
                    }, remainingTime);
                });
            });

            return false;
        }

        // Confirmar eliminación de backup
        function confirmDelete(filename) {
            console.log('confirmDelete llamada para:', filename);

            // Encontrar el formulario por ID
            const formId = 'deleteForm_' + filename;
            const formToSubmit = document.getElementById(formId);

            if (!formToSubmit) {
                console.error('No se encontró el formulario con ID:', formId);
                showModal({
                    title: 'Error',
                    message: 'No se puede encontrar el formulario de eliminación.',
                    details: 'Por favor, recarga la página e intenta nuevamente.',
                    icon: '❌',
                    iconClass: 'danger',
                    confirmText: 'Entendido',
                    showCancel: false,
                    confirmType: 'danger',
                    onConfirm: function() {}
                });
                return false;
            }

            console.log('Formulario encontrado:', formToSubmit);

            const modal = document.getElementById('confirmModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDetails = document.getElementById('modalDetails');
            const confirmBtn = document.getElementById('modalConfirmBtn');
            const cancelBtn = document.getElementById('modalCancelBtn');

            // Configurar modal
            modalIcon.textContent = '🗑️';
            modalIcon.className = 'modal-icon danger';
            modalTitle.textContent = 'Eliminar Backup';
            modalMessage.textContent = '¿Estás seguro de eliminar este backup?';
            modalDetails.innerHTML = '<strong>Archivo:</strong> ' + filename + '<br><br><strong>⚠️ Esta acción no se puede deshacer.</strong>';
            modalDetails.style.display = 'block';

            confirmBtn.textContent = 'Eliminar';
            confirmBtn.className = 'modal-btn modal-btn-danger';
            cancelBtn.textContent = 'Cancelar';

            // Limpiar event listeners previos clonando los botones
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            // Evento cancelar
            newCancelBtn.onclick = function() {
                console.log('Cancelando eliminación');
                modal.classList.remove('active');
                document.body.style.overflow = '';
            };

            // Evento confirmar
            newConfirmBtn.onclick = function() {
                console.log('Confirmando eliminación - enviando formulario');
                modal.classList.remove('active');
                document.body.style.overflow = '';

                // Enviar el formulario
                console.log('Ejecutando submit()...');
                formToSubmit.submit();
            };

            // Mostrar modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            console.log('Modal mostrado');
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: confirmBackup
         * Compatible con llamadas directas y event delegation
         */
        const _confirmBackup = confirmBackup;
        window.confirmBackup = function(event, element, params) {
            if (event && event.preventDefault) event.preventDefault();
            return _confirmBackup(event);
        };

        /**
         * Wrapper: confirmDelete
         * Compatible con llamadas directas y event delegation
         */
        const _confirmDelete = confirmDelete;
        window.confirmDelete = function(eventOrFilename, element, params) {
            const filename = params?.filename || (typeof eventOrFilename === 'string' ? eventOrFilename : null);
            if (filename) return _confirmDelete(filename);
        };

        /**
         * Wrapper: reloadBackupPage
         * Compatible con event delegation
         */
        window.reloadBackupPage = function(event, element, params) {
            window.location.href = '?page=config-backup';
        };

        /**
         * Wrapper: closeModalAndCleanup
         * Compatible con event delegation
         */
        window.closeModalAndCleanup = function(event, element, params) {
            closeModal();
            const progressContainer = document.getElementById('modalProgressContainer');
            if (progressContainer) {
                progressContainer.remove();
            }
        };
    </script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
