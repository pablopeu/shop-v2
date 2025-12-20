<?php
/**
 * Shop V2 - Instalador Minimalista
 * Instalación rápida en 3 pasos con mínima fricción
 *
 * IMPORTANTE: Este archivo se auto-elimina después de la instalación
 */

// Version del instalador (sync con VERSION file)
define('INSTALLER_VERSION', '2.1.0');
define('INSTALLER_BUILD_DATE', '2025-12-20');

// Detectar carpeta del release
// Estructura esperada:
// - instalador.php (este archivo, en la raíz)
// - shop-v2-vX.X.X/ (carpeta del release con app/ y archivos públicos dentro)
$current_dir = __DIR__;
$release_dir = null;
$local_app_path = null;
$local_public_path = null;

// Buscar carpeta del release (shop-v2-*)
$items = scandir($current_dir);
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;

    $item_path = $current_dir . '/' . $item;

    // Buscar carpeta que empiece con "shop-v2-" y tenga app/ dentro
    if (is_dir($item_path) && strpos($item, 'shop-v2-') === 0) {
        $app_path = $item_path . '/app';
        if (is_dir($app_path)) {
            $release_dir = $item_path;
            $local_app_path = $app_path;
            $local_public_path = $item_path;
            break;
        }
    }
}

// Si no se encontró carpeta del release, buscar app/ directamente (extracción manual)
if (!$release_dir) {
    $direct_app_path = $current_dir . '/app';
    if (is_dir($direct_app_path)) {
        $release_dir = $current_dir;
        $local_app_path = $direct_app_path;
        $local_public_path = $current_dir;
    }
}

if ($release_dir && $local_app_path) {
    // Instalación válida detectada
    $parent_dir = dirname($current_dir);

    // Intentar detectar si estamos en public_html
    $is_in_public_html = (strpos($current_dir, '/public_html/') !== false ||
                          strpos($current_dir, '/www/') !== false ||
                          strpos($current_dir, '/htdocs/') !== false);

    // Sugerir ruta para app (fuera de public_html si es posible)
    if ($is_in_public_html) {
        // Intentar sugerir una ruta fuera de public_html
        $suggested_app_path = preg_replace('#(/public_html|/www|/htdocs)(/.*)?$#', '', $current_dir) . '/shop-v2-app';
    } else {
        $suggested_app_path = $parent_dir . '/shop-v2-app';
    }

    // La ruta pública es donde está actualmente el instalador
    $suggested_public_path = $current_dir;

    // Auto-detectar base_path desde la URL
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $suggested_base_path = dirname($script_name);
    if ($suggested_base_path === '/' || $suggested_base_path === '\\') {
        $suggested_base_path = '';
    }

    define('INSTALLER_APP_PATH', $local_app_path);
    define('INSTALLER_PUBLIC_PATH', $local_public_path);
    define('INSTALLER_SUGGESTED_APP_PATH', $suggested_app_path);
    define('INSTALLER_SUGGESTED_PUBLIC_PATH', $suggested_public_path);
    define('INSTALLER_SUGGESTED_BASE_PATH', $suggested_base_path);
    define('INSTALLER_IS_FTP_INSTALL', true);
} else {
    // Sin estructura válida - falta la carpeta app/
    $app_exists = file_exists($local_app_path) ? '✅ Existe' : '❌ NO existe';

    // Listar lo que SÍ existe en el directorio actual
    $existing_items = array_diff(scandir($current_dir), ['.', '..']);
    $existing_list = '';
    $has_release_folder = false;
    foreach ($existing_items as $item) {
        $is_dir = is_dir($current_dir . '/' . $item);
        $existing_list .= ($is_dir ? '📁 ' : '📄 ') . htmlspecialchars($item) . '<br>';

        // Detectar si hay carpeta del release (shop-v2-*)
        if ($is_dir && strpos($item, 'shop-v2-') === 0) {
            $has_release_folder = true;
        }
    }

    // Intentar detectar si ya se instaló previamente leyendo config.php de posibles ubicaciones
    // ESTO ES SOLO INFORMATIVO - no impide una nueva instalación en otra carpeta
    $installation_info = '';
    $possible_config_paths = [
        $current_dir . '/../shop-v2-app/config/config.php',
        $current_dir . '/../../shop-v2-app/config/config.php',
        dirname($current_dir) . '/shop-v2-app/config/config.php',
        preg_replace('#(/public_html|/www|/htdocs).*$#', '', $current_dir) . '/shop-v2-app/config/config.php',
        preg_replace('#(/public_html|/www|/htdocs).*$#', '', $current_dir) . '/shop/config/config.php',
    ];

    foreach ($possible_config_paths as $config_path) {
        if (file_exists($config_path)) {
            $config = include($config_path);
            if (is_array($config)) {
                $installation_info = '
                <details style="margin: 20px 0;">
                    <summary style="cursor: pointer; padding: 15px; background: #e7f3ff; border-radius: 5px; border-left: 4px solid #0066cc; font-weight: bold; color: #0066cc;">
                        ℹ️ Información: Se detectó otra instalación en el servidor (click para ver detalles)
                    </summary>
                    <div style="background: #f8f9fa; padding: 15px; margin-top: 10px; border-radius: 5px; font-family: monospace; font-size: 13px;">
                        <p style="color: #0066cc; margin-bottom: 10px;">
                            <strong>Esto NO impide una nueva instalación.</strong><br>
                            Solo es información de diagnóstico.
                        </p>
                        <strong>📄 Archivo config.php encontrado en:</strong><br>
                        <code>' . htmlspecialchars($config_path) . '</code><br><br>

                        <strong>📍 Rutas configuradas en esa instalación:</strong><br>
                        <strong>• app_path:</strong> <code>' . htmlspecialchars($config['app_path'] ?? 'No definido') . '</code><br>
                        <strong>• public_path:</strong> <code>' . htmlspecialchars($config['public_path'] ?? 'No definido') . '</code><br>
                        <strong>• app_url:</strong> <code>' . htmlspecialchars($config['app_url'] ?? 'No definido') . '</code><br>
                        <strong>• base_path:</strong> <code>' . htmlspecialchars($config['base_path'] ?? 'No definido') . '</code>
                    </div>
                </details>';
                break;
            }
        }
    }

    // Mensaje especial si se detectó instalación previa
    $title = $has_release_folder
        ? '✅ Sistema Ya Instalado'
        : '⚠️ Error: Falta la Carpeta app/';

    $main_message = $has_release_folder
        ? '<div style="background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
            <h3 style="margin-top: 0; color: #155724;">✅ El sistema ya fue instalado desde este paquete</h3>
            <p style="color: #155724; margin-bottom: 10px;">
                La carpeta <strong>app/</strong> ya fue movida a su ubicación final durante la instalación.<br>
                Esto es normal después de una instalación exitosa.
            </p>
            <p style="color: #155724; margin: 0;">
                <strong>¿Qué hacer ahora?</strong><br>
                • Puedes eliminar el instalador.php y la carpeta shop-v2-* por seguridad<br>
                • El sistema está funcionando en las rutas que configuraste durante la instalación
            </p>
        </div>'
        : '';

    die('
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><title>' . $title . '</title></head>
    <body style="font-family: sans-serif; padding: 50px; ' . ($has_release_folder ? 'background: #d4edda;' : 'background: #f8d7da;') . '">
        <div style="max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;">
            <h1 style="color: ' . ($has_release_folder ? '#155724' : '#721c24') . ';">' . $title . '</h1>

            ' . $main_message . '

            ' . $installation_info . '

            <div style="background: #e7f3ff; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #0066cc;">
                <h3 style="margin-top: 0; color: #0066cc;">ℹ️ Cómo Funciona el Instalador</h3>
                <p style="margin: 0;">
                    El paquete tar.gz contiene:<br>
                    • <code>app/</code> - código privado (se moverá a donde configures)<br>
                    • Archivos públicos directos: admin/, assets/, index.php, etc. (quedan donde están)
                </p>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 13px;">
                <strong>🔍 Información de Diagnóstico:</strong><br><br>

                <strong>Directorio del instalador:</strong><br>
                <code>' . htmlspecialchars($current_dir) . '</code><br><br>

                <strong>Buscando carpeta app/ en:</strong><br>
                <code>' . htmlspecialchars($local_app_path) . '</code> ' . $app_exists . '<br>
                <small style="color: #666;">↑ Esta carpeta contiene el código privado que se moverá</small><br><br>

                <strong>Lo que SÍ existe en ' . htmlspecialchars(basename($current_dir)) . '/:</strong><br>
                <div style="background: white; padding: 10px; margin-top: 5px; max-height: 200px; overflow-y: auto;">
                    ' . $existing_list . '
                </div>
            </div>

            <div style="background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                <h3 style="margin-top: 0; color: #155724;">✅ Estructura Correcta del Paquete</h3>

                <p><strong>El tar.gz debe descomprimirse así:</strong></p>
                <div style="background: #f8f9fa; padding: 10px; font-family: monospace; font-size: 12px;">
                📁 tu-carpeta/<br>
                &nbsp;&nbsp;├── 📄 instalador.php<br>
                &nbsp;&nbsp;├── 📁 app/ ← Carpeta que se moverá<br>
                &nbsp;&nbsp;├── 📁 admin/ ← Contenido público directo<br>
                &nbsp;&nbsp;├── 📁 assets/ ← Contenido público directo<br>
                &nbsp;&nbsp;├── 📄 index.php ← Contenido público directo<br>
                &nbsp;&nbsp;└── ... más archivos públicos
                </div>

                <p style="margin-top: 15px;"><strong>El instalador hará:</strong></p>
                1. Mover <code>app/</code> a la ruta que configures (ej: /home/usuario/shop-app)<br>
                2. Si elegiste otra ubicación pública, mover los archivos públicos allí<br>
                3. Eliminar el instalador por seguridad
            </div>

            <p style="color: #721c24; margin-top: 20px;">
                <strong>🔄 Si ya instalaste:</strong><br>
                La carpeta app/ ya fue movida. Para una nueva instalación, extrae el tar.gz en una carpeta vacía.
            </p>
        </div>
    </body></html>
    ');
}

// NOTA: No verificamos si ya se instaló aquí porque la carpeta app/ se mueve durante la instalación.
// La detección de instalación previa se hace en el Step 2, cuando el usuario especifica las rutas de destino.

session_start();

// Process installation
$step = $_POST['step'] ?? $_GET['step'] ?? 1;
$errors = [];
$success = false;

// Step 5: Self-delete installer
if ($step == 5 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'YES') {
        delete_installer();
        // Don't redirect - show success page with buttons
        $step = 6; // New step: cleanup complete
    }
}

// Step 2: Process paths
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['config'] = [
        'app_path' => trim($_POST['app_path'] ?? ''),
        'public_path' => trim($_POST['public_path'] ?? ''),
        'app_url' => trim($_POST['app_url'] ?? ''),
        'base_path' => trim($_POST['base_path'] ?? ''),
    ];

    // DEBUG: Log what was received
    error_log("INSTALADOR DEBUG - Valores recibidos en POST:");
    error_log("  app_path: " . ($_POST['app_path'] ?? 'NO ENVIADO'));
    error_log("  public_path: " . ($_POST['public_path'] ?? 'NO ENVIADO'));
    error_log("  app_url: " . ($_POST['app_url'] ?? 'NO ENVIADO'));
    error_log("  base_path: " . ($_POST['base_path'] ?? 'NO ENVIADO'));
    error_log("SESSION config guardado: " . print_r($_SESSION['config'], true));

    // Validate paths
    if (empty($_SESSION['config']['app_path'])) $errors[] = 'Ruta de aplicación requerida';
    if (empty($_SESSION['config']['public_path'])) $errors[] = 'Ruta pública requerida';

    if (empty($errors)) {
        // Detectar si existe una instalación previa en las rutas especificadas
        $app_path = $_SESSION['config']['app_path'];
        $public_path = $_SESSION['config']['public_path'];

        $previous_install_detected = detect_previous_installation($app_path, $public_path);

        if ($previous_install_detected) {
            $_SESSION['previous_install_info'] = $previous_install_detected;
            $step = '2b'; // Mostrar opciones de reinstalación
        } else {
            $step = 3; // Go to admin form
        }
    }
}

// Step 2b: Handle reinstall options
if ($step == '2b' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_mode'])) {
    $_SESSION['config']['install_mode'] = $_POST['install_mode'];

    if ($_POST['install_mode'] === 'reinstall') {
        $_SESSION['config']['install_mode'] = 'reinstall';
        $step = 3; // Ir al formulario de admin (usará los datos existentes si hay)
    } elseif ($_POST['install_mode'] === 'new_version') {
        $_SESSION['config']['install_mode'] = 'new_version';

        // Validar que no haya conflictos
        $conflicts = check_installation_conflicts($_SESSION['config']['app_path'], $_SESSION['config']['public_path']);

        if (!empty($conflicts)) {
            $_SESSION['conflicts'] = $conflicts;
            $step = '2c'; // Mostrar advertencia de conflictos
        } else {
            $step = 3; // Continuar con instalación nueva
        }
    }
}

// Step 2c: Confirm conflicts
if ($step == '2c' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_overwrite'])) {
    if ($_POST['confirm_overwrite'] === 'YES') {
        $step = 3; // Continuar con instalación
    } else {
        $step = 1; // Volver al inicio
        unset($_SESSION['config']);
        unset($_SESSION['conflicts']);
    }
}

// Step 3: Process admin and install
if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_username'])) {
    // DEBUG: Log config from SESSION before adding admin
    error_log("INSTALADOR DEBUG - Step 3 - Configuración en SESSION antes de agregar admin:");
    error_log("  app_path: " . ($_SESSION['config']['app_path'] ?? 'NO DEFINIDO'));
    error_log("  public_path: " . ($_SESSION['config']['public_path'] ?? 'NO DEFINIDO'));
    error_log("  app_url: " . ($_SESSION['config']['app_url'] ?? 'NO DEFINIDO'));
    error_log("  base_path: " . ($_SESSION['config']['base_path'] ?? 'NO DEFINIDO'));

    // Solo procesar si vienen los datos del formulario admin (no del paso 2)
    $_SESSION['config']['admin_username'] = trim($_POST['admin_username'] ?? '');
    $_SESSION['config']['admin_email'] = trim($_POST['admin_email'] ?? '');
    $_SESSION['config']['admin_password'] = $_POST['admin_password'] ?? '';
    $_SESSION['config']['admin_password_confirm'] = $_POST['admin_password_confirm'] ?? '';

    // Validate admin
    if (empty($_SESSION['config']['admin_username'])) $errors[] = 'Usuario admin requerido';
    if (empty($_SESSION['config']['admin_email'])) $errors[] = 'Email admin requerido';
    if (!filter_var($_SESSION['config']['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
    if (empty($_SESSION['config']['admin_password'])) $errors[] = 'Contraseña requerida';
    if (strlen($_SESSION['config']['admin_password']) < 8) $errors[] = 'Contraseña debe tener al menos 8 caracteres';
    if ($_SESSION['config']['admin_password'] !== $_SESSION['config']['admin_password_confirm']) $errors[] = 'Las contraseñas no coinciden';

    if (empty($errors)) {
        $success = install_system($_SESSION['config']);
        if ($success) {
            $step = 4; // Installation progress
        } else {
            $errors[] = 'Error durante la instalación. Verifica permisos de escritura.';
        }
    }
}

/**
 * Detect previous installation in specified paths
 * Returns array with installation info if detected, false otherwise
 */
function detect_previous_installation($app_path, $public_path) {
    $config_file = $app_path . '/config/config.php';
    $bootstrap_file = $app_path . '/bootstrap.php';
    $index_file = $public_path . '/index.php';

    // Verificar si existe configuración en la ruta de aplicación
    $app_exists = file_exists($config_file) || file_exists($bootstrap_file);

    // Verificar si existen archivos públicos en la ruta pública
    $public_exists = file_exists($index_file);

    if ($app_exists || $public_exists) {
        $info = [
            'app_path' => $app_path,
            'public_path' => $public_path,
            'has_config' => file_exists($config_file),
            'has_bootstrap' => file_exists($bootstrap_file),
            'has_index' => file_exists($index_file),
        ];

        // Intentar leer configuración existente
        if (file_exists($config_file)) {
            $existing_config = @include($config_file);
            if (is_array($existing_config)) {
                $info['existing_config'] = $existing_config;
            }
        }

        return $info;
    }

    return false;
}

/**
 * Check for conflicts when installing as new version
 * Returns array of conflicting files/directories
 */
function check_installation_conflicts($app_path, $public_path) {
    $conflicts = [];

    // Verificar archivos críticos que se sobrescribirían
    $critical_app_files = [
        '/config/config.php',
        '/bootstrap.php',
        '/includes',
        '/pages'
    ];

    $critical_public_files = [
        '/index.php',
        '/admin',
        '/api',
        '/assets'
    ];

    foreach ($critical_app_files as $file) {
        if (file_exists($app_path . $file)) {
            $conflicts[] = 'app:' . $file;
        }
    }

    foreach ($critical_public_files as $file) {
        if (file_exists($public_path . $file)) {
            $conflicts[] = 'public:' . $file;
        }
    }

    return $conflicts;
}

/**
 * Copy directory recursively, excluding JSON files if specified
 */
function copy_directory_recursive($source, $dest, $exclude_json = false, $exclude_patterns = []) {
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    $dir = opendir($source);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $source_path = $source . '/' . $file;
        $dest_path = $dest . '/' . $file;

        // Check exclude patterns
        $should_exclude = false;
        foreach ($exclude_patterns as $pattern) {
            if (fnmatch($pattern, $file)) {
                $should_exclude = true;
                break;
            }
        }

        if ($should_exclude) {
            continue;
        }

        // Exclude JSON files if specified
        if ($exclude_json && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            error_log("INSTALADOR DEBUG - Saltando archivo JSON: $source_path");
            continue;
        }

        if (is_dir($source_path)) {
            copy_directory_recursive($source_path, $dest_path, $exclude_json, $exclude_patterns);
        } else {
            copy($source_path, $dest_path);
        }
    }

    closedir($dir);
    return true;
}

/**
 * Move installation folders from temporary location to final location
 * Handles reinstall mode (preserves JSON) and new version mode
 */
function move_installation_folders($target_app_path, $target_public_path, $install_mode = 'new') {
    $source_app = INSTALLER_APP_PATH;
    $source_public = INSTALLER_PUBLIC_PATH;

    error_log("INSTALADOR DEBUG - move_installation_folders():");
    error_log("  Modo: " . $install_mode);
    error_log("  Origen app: " . $source_app);
    error_log("  Destino app: " . $target_app_path);
    error_log("  Origen public: " . $source_public);
    error_log("  Destino public: " . $target_public_path);

    // Crear directorio de destino si no existe
    if (!is_dir($target_app_path)) {
        if (!mkdir($target_app_path, 0755, true)) {
            error_log("No se pudo crear directorio app: $target_app_path");
            return false;
        }
    }

    // PASO 1: Copiar contenido de app/ (NO la carpeta app/ en sí)
    if ($install_mode === 'reinstall') {
        // Modo reinstalación: excluir archivos JSON
        error_log("INSTALADOR DEBUG - Copiando app/ en modo REINSTALL (sin JSON)");
        copy_directory_recursive($source_app, $target_app_path, true); // exclude_json = true
    } else {
        // Modo instalación nueva: copiar todo
        error_log("INSTALADOR DEBUG - Copiando app/ en modo NUEVO");
        copy_directory_recursive($source_app, $target_app_path, false);
    }

    // Eliminar carpeta app/ del origen después de copiar
    if (is_dir($source_app)) {
        delete_directory_recursive($source_app);
    }

    error_log("INSTALADOR DEBUG - Contenido de app/ copiado exitosamente");

    // PASO 2: Copiar archivos públicos (NO crear carpeta public_html/)
    // Solo si la ubicación es diferente al directorio actual
    if (realpath($source_public) !== realpath($target_public_path)) {
        error_log("INSTALADOR DEBUG - Copiando archivos públicos a ubicación diferente");

        if (!is_dir($target_public_path)) {
            if (!mkdir($target_public_path, 0755, true)) {
                error_log("No se pudo crear directorio público: $target_public_path");
                return false;
            }
        }

        // Copiar todos los archivos EXCEPTO instalador.php, README, app/ (ya movida), carpeta del release
        $items = scandir($source_public);
        $exclude_items = ['.', '..', 'instalador.php', 'README_INSTALACION.md', 'app', 'docs'];

        foreach ($items as $item) {
            if (in_array($item, $exclude_items)) {
                continue;
            }

            // Excluir carpetas del release (shop-v2-*)
            if (strpos($item, 'shop-v2-') === 0) {
                error_log("INSTALADOR DEBUG - Saltando carpeta del release: $item");
                continue;
            }

            // Excluir archivos tar.gz
            if (pathinfo($item, PATHINFO_EXTENSION) === 'gz') {
                error_log("INSTALADOR DEBUG - Saltando archivo tar.gz: $item");
                continue;
            }

            $source_item = $source_public . '/' . $item;
            $target_item = $target_public_path . '/' . $item;

            if (is_dir($source_item)) {
                copy_directory_recursive($source_item, $target_item);
                delete_directory_recursive($source_item);
            } else {
                if ($install_mode === 'reinstall') {
                    // En reinstall, solo copiar archivos que no sean JSON
                    if (pathinfo($item, PATHINFO_EXTENSION) !== 'json') {
                        copy($source_item, $target_item);
                    }
                } else {
                    copy($source_item, $target_item);
                }
            }
        }

        error_log("INSTALADOR DEBUG - Archivos públicos copiados exitosamente");
    } else {
        error_log("INSTALADOR DEBUG - Archivos públicos ya están en ubicación final");
    }

    return true;
}

/**
 * Delete directory recursively
 */
function delete_directory_recursive($dir) {
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
            delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
    return true;
}

/**
 * Main installation function
 */
function install_system($config) {
    try {
        error_log("INSTALADOR DEBUG - install_system() recibió config:");
        error_log("  app_path: " . ($config['app_path'] ?? 'NO DEFINIDO'));
        error_log("  public_path: " . ($config['public_path'] ?? 'NO DEFINIDO'));
        error_log("  app_url: " . ($config['app_url'] ?? 'NO DEFINIDO'));
        error_log("  base_path: " . ($config['base_path'] ?? 'NO DEFINIDO'));
        error_log("  install_mode: " . ($config['install_mode'] ?? 'new'));

        $app_path = $config['app_path'];
        $public_path = $config['public_path'];
        $install_mode = $config['install_mode'] ?? 'new';

        // PASO 1: Copiar archivos desde el paquete a ubicación final
        if (!move_installation_folders($app_path, $public_path, $install_mode)) {
            throw new Exception('Error al copiar las carpetas de instalación');
        }

        // Create directory structure
        create_directory_structure($app_path, $public_path);

        // PASO 2: Configuración según modo de instalación
        if ($install_mode === 'reinstall') {
            error_log("INSTALADOR DEBUG - Modo REINSTALL: Preservando archivos JSON existentes");

            // En reinstall: NO sobrescribir config.php ni JSON
            // Solo actualizar si no existen (por si acaso)

            if (!file_exists($app_path . '/config/config.php')) {
                $secret_key = bin2hex(random_bytes(32));
                create_config_php($app_path, $config, $secret_key);
            }

            // No crear JSON files en reinstall - se preservan los existentes

        } else {
            // Instalación nueva o nueva versión
            error_log("INSTALADOR DEBUG - Modo NUEVO: Creando archivos de configuración");

            // Generate security keys
            $secret_key = bin2hex(random_bytes(32));
            $webhook_secret = bin2hex(random_bytes(16));
            $bypass_code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

            // Create config.php (siempre en instalación nueva)
            create_config_php($app_path, $config, $secret_key);

            // Create JSON files solo si no existen (para nueva versión, crear dummy si falta)
            create_configuration_json_files_if_needed($app_path, $config, $webhook_secret, $bypass_code);
            create_data_json_files_if_needed($app_path, $config);
        }

        // Create bootstrap path configuration file (siempre)
        create_bootstrap_path_file($app_path, $public_path);

        // Create .htaccess files (siempre)
        create_htaccess_files($app_path, $public_path, $config);

        // Set permissions
        set_permissions($app_path);

        return true;
    } catch (Exception $e) {
        error_log("Installer error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create directory structure
 */
function create_directory_structure($app_path, $public_path) {
    $directories = [
        $app_path . '/config',
        $app_path . '/data',
        $app_path . '/data/products',
        $public_path . '/uploads',
        $public_path . '/uploads/products',
        $public_path . '/uploads/logos',
        $public_path . '/uploads/og-images',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
    }
}

/**
 * Create config.php
 */
function create_config_php($app_path, $config, $secret_key) {
    // DEBUG: Log what will be written to config.php
    error_log("INSTALADOR DEBUG - create_config_php() va a escribir:");
    error_log("  Archivo: " . $app_path . '/config/config.php');
    error_log("  app_path: " . ($config['app_path'] ?? 'NO DEFINIDO'));
    error_log("  public_path: " . ($config['public_path'] ?? 'NO DEFINIDO'));
    error_log("  app_url: " . ($config['app_url'] ?? 'NO DEFINIDO'));
    error_log("  base_path: " . ($config['base_path'] ?? 'NO DEFINIDO'));

    $content = "<?php\n";
    $content .= "/**\n";
    $content .= " * Auto-generated Configuration\n";
    $content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= " * DO NOT commit this file to repository\n";
    $content .= " */\n\n";
    $content .= "return [\n";
    $content .= "    'app_name' => 'Shop V2',\n";
    $content .= "    'app_url' => " . var_export($config['app_url'], true) . ",\n";
    $content .= "    'base_path' => " . var_export($config['base_path'], true) . ",\n";
    $content .= "    'secret_key' => " . var_export($secret_key, true) . ",\n";
    $content .= "    'csrf_token_expiry' => 3600,\n";
    $content .= "    'app_path' => " . var_export($config['app_path'], true) . ",\n";
    $content .= "    'public_path' => " . var_export($config['public_path'], true) . ",\n";
    $content .= "    'maintenance_mode' => false,\n";
    $content .= "    'debug' => false,\n";
    $content .= "    'log_errors' => true,\n";
    $content .= "];\n";

    file_put_contents($app_path . '/config/config.php', $content);
    error_log("INSTALADOR DEBUG - config.php escrito en: " . $app_path . '/config/config.php');
}

/**
 * Create bootstrap_path.php to help entry points find bootstrap.php
 * This eliminates hardcoded paths in index.php, api/index.php, etc.
 */
function create_bootstrap_path_file($app_path, $public_path) {
    $bootstrap_file = $app_path . '/bootstrap.php';

    $content = "<?php\n";
    $content .= "/**\n";
    $content .= " * Bootstrap Path Configuration\n";
    $content .= " * Auto-generated by installer - DO NOT commit to repository\n";
    $content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
    $content .= " */\n\n";
    $content .= "// Path to bootstrap.php\n";
    $content .= "define('BOOTSTRAP_PATH', " . var_export($bootstrap_file, true) . ");\n";

    // Write to multiple locations for different entry points
    $locations = [
        $public_path . '/bootstrap_path.php',
        $public_path . '/admin/bootstrap_path.php',
        $public_path . '/api/bootstrap_path.php',
    ];

    foreach ($locations as $location) {
        $dir = dirname($location);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($location, $content);
        error_log("INSTALADOR DEBUG - bootstrap_path.php escrito en: " . $location);
    }
}

/**
 * Create all configuration JSON files
 */
function create_configuration_json_files($app_path, $config, $webhook_secret, $bypass_code) {
    $admin_email = $config['admin_email'];
    $timestamp = date('Y-m-d H:i:s');

    // site.json
    $site = [
        'site_name' => 'Mi Tienda',
        'site_description' => 'Tienda en línea',
        'site_keywords' => 'ecommerce, tienda, productos',
        'contact_email' => $admin_email,
        'contact_phone' => '',
        'footer_text' => '© ' . date('Y') . ' Mi Tienda. Todos los derechos reservados.',
        'whatsapp' => [
            'enabled' => false,
            'number' => '',
            'message' => 'Hola, te estoy consultando desde la tienda,',
            'custom_link' => '',
            'display_text' => 'Mensaje Whatsapp'
        ],
        'telegram' => [
            'enabled' => false,
            'bot_token' => '',
            'chat_id' => ''
        ],
        'logo' => [
            'enabled' => false,
            'path' => '',
            'alt' => 'Mi Tienda'
        ],
        'site_owner' => '',
        'whatsapp_number' => '',
        'meta_tags' => [
            'og_title' => 'Mi Tienda',
            'og_type' => 'website',
            'og_url' => '',
            'og_url_secure' => '',
            'og_image' => '',
            'og_site_name' => 'Mi Tienda',
            'og_description' => 'Tienda en línea',
            'content_type' => 'text/html; charset=utf-8',
            'og_image_width' => '1200',
            'og_image_height' => '630',
            'twitter_card' => 'summary_large_image'
        ]
    ];
    write_json($app_path . '/config/site.json', $site);

    // email.json
    $email = [
        'enabled' => false,
        'method' => 'smtp',
        'from_email' => $admin_email,
        'from_name' => 'Mi Tienda',
        'admin_email' => $admin_email,
        'notifications' => [
            'customer' => [
                'order_created' => true,
                'payment_approved' => true,
                'payment_rejected' => false,
                'payment_pending' => false,
                'order_shipped' => true,
                'chargeback_notice' => false
            ],
            'admin' => [
                'new_order' => true,
                'payment_approved' => true,
                'chargeback_alert' => true,
                'low_stock_alert' => false
            ]
        ]
    ];
    write_json($app_path . '/config/email.json', $email);

    // payment.json
    $payment = [
        'mercadopago' => [
            'enabled' => false,
            'access_token' => '',
            'public_key' => '',
            'webhook_secret' => $webhook_secret,
            'sandbox_mode' => false
        ],
        'payment_methods' => [
            'credit_card' => true,
            'debit_card' => true,
            'bank_transfer' => false,
            'cash' => false
        ]
    ];
    write_json($app_path . '/config/payment.json', $payment);

    // currency.json
    $currency = [
        'primary' => 'ARS',
        'secondary' => 'USD',
        'exchange_rate' => 1500,
        'auto_update' => false,
        'update_source' => 'dolarapi',
        'update_type' => 'blue',
        'last_update' => $timestamp
    ];
    write_json($app_path . '/config/currency.json', $currency);

    // theme.json
    $theme = ['active_theme' => 'classic'];
    write_json($app_path . '/config/theme.json', $theme);

    // maintenance.json
    $maintenance = [
        'enabled' => false,
        'bypass_code' => $bypass_code,
        'message' => 'Sitio en mantenimiento. Volveremos pronto.'
    ];
    write_json($app_path . '/config/maintenance.json', $maintenance);

    // carousel.json
    $carousel = ['slides' => []];
    write_json($app_path . '/config/carousel.json', $carousel);

    // hero.json
    $hero = [
        'enabled' => false,
        'title' => 'Bienvenido a Mi Tienda',
        'subtitle' => 'Descubre nuestros productos',
        'image' => '',
        'cta_text' => 'Ver Productos',
        'cta_link' => '/productos'
    ];
    write_json($app_path . '/config/hero.json', $hero);

    // footer.json
    $footer = [
        'columns' => [
            ['title' => 'Acerca de', 'links' => []],
            ['title' => 'Enlaces', 'links' => []],
            ['title' => 'Contacto', 'links' => []]
        ],
        'social_media' => [
            'facebook' => '',
            'instagram' => '',
            'twitter' => '',
            'youtube' => ''
        ]
    ];
    write_json($app_path . '/config/footer.json', $footer);

    // telegram.json
    $telegram = [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
        'notifications' => [
            'new_order' => false,
            'payment_approved' => false,
            'payment_rejected' => false,
            'low_stock' => false
        ]
    ];
    write_json($app_path . '/config/telegram.json', $telegram);

    // analytics.json
    $analytics = [
        'google_analytics' => ['enabled' => false, 'tracking_id' => ''],
        'facebook_pixel' => ['enabled' => false, 'pixel_id' => '']
    ];
    write_json($app_path . '/config/analytics.json', $analytics);

    // products-heading.json
    $products_heading = [
        'enabled' => false,
        'title' => 'Nuestros Productos',
        'subtitle' => 'Descubre nuestra selección'
    ];
    write_json($app_path . '/config/products-heading.json', $products_heading);

    // strings.json
    $strings = [
        'cart' => [
            'add_to_cart' => 'Agregar al Carrito',
            'remove_from_cart' => 'Quitar del Carrito',
            'empty_cart' => 'Tu carrito está vacío',
            'continue_shopping' => 'Continuar Comprando',
            'proceed_to_checkout' => 'Proceder al Pago'
        ],
        'checkout' => [
            'shipping_info' => 'Información de Envío',
            'payment_method' => 'Método de Pago',
            'order_summary' => 'Resumen del Pedido'
        ],
        'common' => [
            'loading' => 'Cargando...',
            'error' => 'Error',
            'success' => 'Éxito',
            'cancel' => 'Cancelar',
            'confirm' => 'Confirmar'
        ]
    ];
    write_json($app_path . '/config/strings.json', $strings);

    // dashboard.json
    $dashboard = [
        'widgets' => [
            'sales' => true,
            'orders' => true,
            'products' => true,
            'customers' => false
        ]
    ];
    write_json($app_path . '/config/dashboard.json', $dashboard);
}

/**
 * Create configuration JSON files only if they don't exist
 * For new_version mode: creates dummy files if missing
 */
function create_configuration_json_files_if_needed($app_path, $config, $webhook_secret, $bypass_code) {
    $admin_email = $config['admin_email'] ?? 'admin@example.com';
    $timestamp = date('Y-m-d H:i:s');

    // Helper function to create file only if it doesn't exist
    $create_if_missing = function($file, $data) {
        if (!file_exists($file)) {
            write_json($file, $data);
        }
    };

    // site.json
    $create_if_missing($app_path . '/config/site.json', [
        'site_name' => 'Mi Tienda',
        'site_description' => 'Tienda en línea',
        'site_keywords' => 'ecommerce, tienda, productos',
        'contact_email' => $admin_email,
        'contact_phone' => '',
        'footer_text' => '© ' . date('Y') . ' Mi Tienda. Todos los derechos reservados.',
        'whatsapp' => [
            'enabled' => false,
            'number' => '',
            'message' => 'Hola, te estoy consultando desde la tienda,',
            'custom_link' => '',
            'display_text' => 'Mensaje Whatsapp'
        ],
        'telegram' => [
            'enabled' => false,
            'bot_token' => '',
            'chat_id' => ''
        ],
        'logo' => [
            'enabled' => false,
            'path' => '',
            'alt' => 'Mi Tienda'
        ],
        'site_owner' => '',
        'whatsapp_number' => '',
        'meta_tags' => [
            'og_title' => 'Mi Tienda',
            'og_type' => 'website',
            'og_url' => '',
            'og_url_secure' => '',
            'og_image' => '',
            'og_site_name' => 'Mi Tienda',
            'og_description' => 'Tienda en línea',
            'content_type' => 'text/html; charset=utf-8',
            'og_image_width' => '1200',
            'og_image_height' => '630',
            'twitter_card' => 'summary_large_image'
        ]
    ]);

    // email.json
    $create_if_missing($app_path . '/config/email.json', [
        'enabled' => false,
        'method' => 'smtp',
        'from_email' => $admin_email,
        'from_name' => 'Mi Tienda',
        'admin_email' => $admin_email,
        'notifications' => [
            'customer' => [
                'order_created' => true,
                'payment_approved' => true,
                'payment_rejected' => false,
                'payment_pending' => false,
                'order_shipped' => true,
                'chargeback_notice' => false
            ],
            'admin' => [
                'new_order' => true,
                'payment_approved' => true,
                'chargeback_alert' => true,
                'low_stock_alert' => false
            ]
        ]
    ]);

    // payment.json
    $create_if_missing($app_path . '/config/payment.json', [
        'mercadopago' => [
            'enabled' => false,
            'access_token' => '',
            'public_key' => '',
            'webhook_secret' => $webhook_secret,
            'sandbox_mode' => false
        ],
        'payment_methods' => [
            'credit_card' => true,
            'debit_card' => true,
            'bank_transfer' => false,
            'cash' => false
        ]
    ]);

    // currency.json
    $create_if_missing($app_path . '/config/currency.json', [
        'primary' => 'ARS',
        'secondary' => 'USD',
        'exchange_rate' => 1500,
        'auto_update' => false,
        'update_source' => 'dolarapi',
        'update_type' => 'blue',
        'last_update' => $timestamp
    ]);

    // theme.json
    $create_if_missing($app_path . '/config/theme.json', ['active_theme' => 'classic']);

    // maintenance.json
    $create_if_missing($app_path . '/config/maintenance.json', [
        'enabled' => false,
        'bypass_code' => $bypass_code,
        'message' => 'Sitio en mantenimiento. Volveremos pronto.'
    ]);

    // carousel.json
    $create_if_missing($app_path . '/config/carousel.json', ['slides' => []]);

    // hero.json
    $create_if_missing($app_path . '/config/hero.json', [
        'enabled' => false,
        'title' => 'Bienvenido a Mi Tienda',
        'subtitle' => 'Descubre nuestros productos',
        'image' => '',
        'cta_text' => 'Ver Productos',
        'cta_link' => '/productos'
    ]);

    // footer.json
    $create_if_missing($app_path . '/config/footer.json', [
        'columns' => [
            ['title' => 'Acerca de', 'links' => []],
            ['title' => 'Enlaces', 'links' => []],
            ['title' => 'Contacto', 'links' => []]
        ],
        'social_media' => [
            'facebook' => '',
            'instagram' => '',
            'twitter' => '',
            'youtube' => ''
        ]
    ]);

    // telegram.json
    $create_if_missing($app_path . '/config/telegram.json', [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
        'notifications' => [
            'new_order' => false,
            'payment_approved' => false,
            'payment_rejected' => false,
            'low_stock' => false
        ]
    ]);

    // analytics.json
    $create_if_missing($app_path . '/config/analytics.json', [
        'google_analytics' => ['enabled' => false, 'tracking_id' => ''],
        'facebook_pixel' => ['enabled' => false, 'pixel_id' => '']
    ]);

    // products-heading.json
    $create_if_missing($app_path . '/config/products-heading.json', [
        'enabled' => false,
        'title' => 'Nuestros Productos',
        'subtitle' => 'Descubre nuestra selección'
    ]);

    // strings.json
    $create_if_missing($app_path . '/config/strings.json', [
        'cart' => [
            'add_to_cart' => 'Agregar al Carrito',
            'remove_from_cart' => 'Quitar del Carrito',
            'empty_cart' => 'Tu carrito está vacío',
            'continue_shopping' => 'Continuar Comprando',
            'proceed_to_checkout' => 'Proceder al Pago'
        ],
        'checkout' => [
            'shipping_info' => 'Información de Envío',
            'payment_method' => 'Método de Pago',
            'order_summary' => 'Resumen del Pedido'
        ],
        'common' => [
            'loading' => 'Cargando...',
            'error' => 'Error',
            'success' => 'Éxito',
            'cancel' => 'Cancelar',
            'confirm' => 'Confirmar'
        ]
    ]);

    // dashboard.json
    $create_if_missing($app_path . '/config/dashboard.json', [
        'widgets' => [
            'sales' => true,
            'orders' => true,
            'products' => true,
            'customers' => false
        ]
    ]);
}

/**
 * Create data JSON files only if they don't exist
 */
function create_data_json_files_if_needed($app_path, $config) {
    $timestamp = date('Y-m-d H:i:s');

    // Helper function
    $create_if_missing = function($file, $data) {
        if (!file_exists($file)) {
            write_json($file, $data);
        }
    };

    // users.json - with admin user
    if (!file_exists($app_path . '/data/users.json')) {
        $users = [
            'users' => [[
                'id' => 'admin-' . uniqid() . '-' . bin2hex(random_bytes(4)),
                'username' => $config['admin_username'] ?? 'admin',
                'password' => password_hash($config['admin_password'] ?? 'admin123', PASSWORD_ARGON2ID),
                'email' => $config['admin_email'] ?? 'admin@example.com',
                'name' => '',
                'role' => 'admin',
                'created_at' => $timestamp,
                'last_login' => null
            ]]
        ];
        write_json($app_path . '/data/users.json', $users);
    }

    // All empty data files
    $create_if_missing($app_path . '/data/products.json', ['products' => []]);
    $create_if_missing($app_path . '/data/orders.json', ['orders' => []]);
    $create_if_missing($app_path . '/data/archived_orders.json', ['orders' => []]);
    $create_if_missing($app_path . '/data/reviews.json', ['reviews' => []]);
    $create_if_missing($app_path . '/data/coupons.json', ['coupons' => []]);
    $create_if_missing($app_path . '/data/promotions.json', ['promotions' => []]);
    $create_if_missing($app_path . '/data/wishlists.json', ['wishlists' => []]);
    $create_if_missing($app_path . '/data/newsletters.json', ['subscribers' => []]);
    $create_if_missing($app_path . '/data/admin_logs.json', ['logs' => []]);
    $create_if_missing($app_path . '/data/stock_logs.json', ['logs' => []]);
    $create_if_missing($app_path . '/data/visits.json', ['visits' => []]);
    $create_if_missing($app_path . '/data/webhook_log.json', ['logs' => []]);
    $create_if_missing($app_path . '/data/webhook_rate_limit.json', ['limits' => []]);
    $create_if_missing($app_path . '/data/mp_preference_log.json', ['preferences' => []]);
    $create_if_missing($app_path . '/data/mp_logs.json', ['logs' => []]);

    // install_log.json
    $create_if_missing($app_path . '/data/install_log.json', [
        'installed_at' => $timestamp,
        'installer_version' => '2.0',
        'environment' => detect_environment(),
        'paths' => [
            'app_path' => $config['app_path'] ?? '',
            'public_path' => $config['public_path'] ?? ''
        ],
        'admin_user' => $config['admin_username'] ?? 'admin'
    ]);
}

/**
 * Create all data JSON files
 */
function create_data_json_files($app_path, $config) {
    $timestamp = date('Y-m-d H:i:s');

    // users.json - with admin user
    $users = [
        'users' => [[
            'id' => 'admin-' . uniqid() . '-' . bin2hex(random_bytes(4)),
            'username' => $config['admin_username'],
            'password' => password_hash($config['admin_password'], PASSWORD_ARGON2ID),
            'email' => $config['admin_email'],
            'name' => '',
            'role' => 'admin',
            'created_at' => $timestamp,
            'last_login' => null
        ]]
    ];
    write_json($app_path . '/data/users.json', $users);

    // All empty data files
    write_json($app_path . '/data/products.json', ['products' => []]);
    write_json($app_path . '/data/orders.json', ['orders' => []]);
    write_json($app_path . '/data/archived_orders.json', ['orders' => []]);
    write_json($app_path . '/data/reviews.json', ['reviews' => []]);
    write_json($app_path . '/data/coupons.json', ['coupons' => []]);
    write_json($app_path . '/data/promotions.json', ['promotions' => []]);
    write_json($app_path . '/data/wishlists.json', ['wishlists' => []]);
    write_json($app_path . '/data/newsletters.json', ['subscribers' => []]);
    write_json($app_path . '/data/admin_logs.json', ['logs' => []]);
    write_json($app_path . '/data/stock_logs.json', ['logs' => []]);
    write_json($app_path . '/data/visits.json', ['visits' => []]);
    write_json($app_path . '/data/webhook_log.json', ['logs' => []]);
    write_json($app_path . '/data/webhook_rate_limit.json', ['limits' => []]);
    write_json($app_path . '/data/mp_preference_log.json', ['preferences' => []]);
    write_json($app_path . '/data/mp_logs.json', ['logs' => []]);

    // install_log.json
    $install_log = [
        'installed_at' => $timestamp,
        'installer_version' => '2.0',
        'environment' => detect_environment(),
        'paths' => [
            'app_path' => $config['app_path'],
            'public_path' => $config['public_path']
        ],
        'admin_user' => $config['admin_username']
    ];
    write_json($app_path . '/data/install_log.json', $install_log);
}

/**
 * Create .htaccess files
 */
function create_htaccess_files($app_path, $public_path, $config) {
    // Protect app directory
    $app_htaccess = "# Security: Block ALL access to application code\n";
    $app_htaccess .= "Require all denied\n";
    $app_htaccess .= "Options -Indexes\n";
    file_put_contents($app_path . '/.htaccess', $app_htaccess);

    // Public root .htaccess (SIEMPRE crear con base_path dinámico)
    $public_htaccess_path = $public_path . '/.htaccess';
    $base_path = $config['base_path'];

    // Normalizar base_path para RewriteBase (debe terminar en /)
    $rewrite_base = $base_path;
    if ($rewrite_base === '' || $rewrite_base === '/') {
        $rewrite_base = '/';
    } else {
        // Asegurar que empiece con / y termine con /
        if (substr($rewrite_base, 0, 1) !== '/') {
            $rewrite_base = '/' . $rewrite_base;
        }
        if (substr($rewrite_base, -1) !== '/') {
            $rewrite_base .= '/';
        }
    }

    $public_htaccess = "# Public .htaccess\n";
    $public_htaccess .= "# Routing rules for frontend and admin\n\n";

    $public_htaccess .= "# Protect sensitive files\n";
    $public_htaccess .= "<FilesMatch \"\\.(json|md|log)$\">\n";
    $public_htaccess .= "    Order deny,allow\n";
    $public_htaccess .= "    Deny from all\n";
    $public_htaccess .= "</FilesMatch>\n\n";

    $public_htaccess .= "# Enable rewrite engine\n";
    $public_htaccess .= "RewriteEngine On\n";
    $public_htaccess .= "RewriteBase " . $rewrite_base . "\n\n";

    $public_htaccess .= "# Don't redirect existing files (CSS, JS, images, etc.)\n";
    $public_htaccess .= "RewriteCond %{REQUEST_FILENAME} -f\n";
    $public_htaccess .= "RewriteRule ^ - [L]\n\n";

    $public_htaccess .= "# Don't redirect existing directories\n";
    $public_htaccess .= "RewriteCond %{REQUEST_FILENAME} -d\n";
    $public_htaccess .= "RewriteRule ^ - [L]\n\n";

    $public_htaccess .= "# Don't redirect API endpoints\n";
    $public_htaccess .= "RewriteCond %{REQUEST_URI} ^" . $base_path . "/api/ [OR]\n";
    $public_htaccess .= "RewriteCond %{REQUEST_URI} ^" . $base_path . "/admin/api/\n";
    $public_htaccess .= "RewriteRule ^ - [L]\n\n";

    $public_htaccess .= "# Don't redirect webhook\n";
    $public_htaccess .= "RewriteCond %{REQUEST_URI} ^" . $base_path . "/webhook\\.php$\n";
    $public_htaccess .= "RewriteRule ^ - [L]\n\n";

    $public_htaccess .= "# Don't redirect admin PHP files\n";
    $public_htaccess .= "RewriteCond %{REQUEST_URI} ^" . $base_path . "/admin/.*\\.php$\n";
    $public_htaccess .= "RewriteRule ^ - [L]\n\n";

    $public_htaccess .= "# Redirect all other requests to index.php\n";
    $public_htaccess .= "RewriteRule ^ index.php [L]\n";

    file_put_contents($public_htaccess_path, $public_htaccess);
    error_log("INSTALADOR DEBUG - .htaccess público creado con RewriteBase: " . $rewrite_base);
}

/**
 * Set file permissions
 */
function set_permissions($app_path) {
    @chmod($app_path . '/config/config.php', 0640);
    @chmod($app_path . '/data', 0750);
    @chmod($app_path . '/data/users.json', 0640);
}

/**
 * Write JSON file
 */
function write_json($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($file, $json);
}

/**
 * Detect environment
 */
function detect_environment() {
    // El instalador siempre crea una nueva instalación desde el paquete
    return 'new_installation';
}

/**
 * Delete installer file
 */
function delete_installer() {
    $installer_file = __FILE__;
    $current_dir = dirname($installer_file);

    // 1. Buscar y eliminar carpeta del release (shop-v2-*)
    $items = scandir($current_dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $item_path = $current_dir . '/' . $item;

        // Eliminar carpeta del release
        if (is_dir($item_path) && strpos($item, 'shop-v2-') === 0) {
            recursive_rmdir($item_path);
            error_log("INSTALADOR - Carpeta del release eliminada: $item_path");
        }

        // Eliminar archivo tar.gz del release
        if (is_file($item_path) && preg_match('/^shop-v2-.*\.tar\.gz$/', $item)) {
            @unlink($item_path);
            error_log("INSTALADOR - Archivo tar.gz eliminado: $item_path");
        }
    }

    // 2. Eliminar el instalador mismo
    $result = @unlink($installer_file);
    if ($result) {
        error_log("INSTALADOR - Instalador eliminado: $installer_file");
    }

    return $result;
}

/**
 * Eliminar directorio recursivamente
 */
function recursive_rmdir($dir) {
    if (!is_dir($dir)) return false;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            recursive_rmdir($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔒 Instalador Shop V2 - Minimalista</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            max-width: 700px;
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .progress-bar {
            background: rgba(255,255,255,0.2);
            height: 4px;
            width: 100%;
            margin-top: 20px;
            overflow: hidden;
        }
        .progress-fill {
            background: white;
            height: 100%;
            transition: width 0.3s ease;
        }
        .content {
            padding: 40px;
        }
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .info-box h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box ul {
            list-style: none;
            padding: 0;
        }
        .info-box li {
            padding: 6px 0;
            color: #555;
            font-size: 14px;
        }
        .info-box li:before {
            content: "✨ ";
            color: #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            display: inline-block;
            text-decoration: none;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        .success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
        }
        .success-box h2 {
            color: #155724;
            margin-bottom: 20px;
        }
        .success-box p {
            color: #155724;
            margin-bottom: 25px;
        }
        .next-steps {
            text-align: left;
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .next-steps h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .next-steps ol {
            margin-left: 20px;
            line-height: 1.8;
            color: #333;
        }
        .delete-box {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .delete-box h3 {
            color: #721c24;
            margin-bottom: 10px;
        }
        .delete-box p {
            color: #721c24;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .links {
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin: 0 5px;
        }
        .loading {
            text-align: center;
            padding: 40px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-list {
            text-align: left;
            margin: 30px 0;
        }
        .status-item {
            padding: 10px 0;
            color: #155724;
            font-size: 14px;
        }
        .status-item:before {
            content: "✅ ";
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Instalador Shop V2</h1>
            <p>Instalación rápida en 3 pasos <span style="opacity: 0.7; font-size: 0.85em;">• v<?php echo INSTALLER_VERSION; ?></span></p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($step == 1 ? 20 : ($step == 2 || $step == '2b' || $step == '2c' ? 40 : ($step == 3 ? 60 : ($step == 4 ? 80 : 100)))); ?>%;"></div>
            </div>
        </div>

        <div class="content">
            <?php if ($step == 1): ?>
                <!-- Step 1: Welcome -->
                <div class="info-box">
                    <h3>✨ Instalación Minimalista</h3>
                    <ul>
                        <li>Instalación en menos de 2 minutos</li>
                        <li>Configuración completa desde el panel admin</li>
                        <li>28 archivos JSON con valores por defecto</li>
                        <li>Sistema listo para usar inmediatamente</li>
                    </ul>
                </div>

                <form method="POST" action="?step=2" id="pathForm">
                    <h3 style="margin-bottom: 20px; color: #333; font-size: 18px;">📁 Configuración de Rutas</h3>
                    <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                        Rutas detectadas automáticamente. <strong>Verifica que sean correctas antes de continuar.</strong>
                    </p>

                    <div id="validationMessage" style="display: none; margin-bottom: 20px;"></div>

                    <div class="form-group">
                        <label>🗂️ Ruta de Aplicación (código privado)</label>
                        <input type="text" id="app_path" name="app_path" value="<?php echo htmlspecialchars(INSTALLER_SUGGESTED_APP_PATH); ?>" required>
                        <small>⚠️ Debe estar FUERA de public_html por seguridad</small>
                    </div>

                    <div class="form-group">
                        <label>🌐 Ruta Pública (archivos accesibles desde web)</label>
                        <input type="text" id="public_path" name="public_path" value="<?php echo htmlspecialchars(INSTALLER_SUGGESTED_PUBLIC_PATH); ?>" required>
                        <small>Donde se instalará el contenido público (index.php, admin/, etc.)</small>
                    </div>

                    <div class="form-group">
                        <label>🔗 URL del Sitio</label>
                        <input type="url" id="app_url" name="app_url" value="http<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 's' : ''; ?>://<?php echo $_SERVER['HTTP_HOST'] ?? 'localhost'; ?>" required>
                        <small>URL completa del sitio (con http:// o https://)</small>
                    </div>

                    <div class="form-group">
                        <label>📁 Base Path (calculado automáticamente)</label>
                        <input type="text" id="base_path" name="base_path" value="<?php echo htmlspecialchars(INSTALLER_SUGGESTED_BASE_PATH); ?>" placeholder="/">
                        <small>Ruta después del dominio. Ej: "" si es raíz, "/shop" si está en dominio.com/shop/</small>
                    </div>

                    <button type="button" class="btn-secondary" onclick="verificarRutas()" style="margin-bottom: 15px; width: 100%;">
                        🔍 Verificar Rutas
                    </button>

                    <button type="submit" class="btn">Siguiente →</button>
                </form>

                <script>
                function verificarRutas() {
                    const appPath = document.getElementById('app_path').value;
                    const publicPath = document.getElementById('public_path').value;
                    const messageDiv = document.getElementById('validationMessage');

                    let errors = [];
                    let warnings = [];

                    // Verificar que app_path esté fuera de public_html
                    if (appPath.includes('/public_html/') || appPath.includes('/www/') || appPath.includes('/htdocs/')) {
                        errors.push('⚠️ ERROR: La ruta de aplicación NO debe estar dentro de public_html, www o htdocs por seguridad.');
                    }

                    // Verificar que app_path y public_path no sean iguales
                    if (appPath === publicPath) {
                        errors.push('⚠️ ERROR: La ruta de aplicación y la ruta pública no pueden ser iguales.');
                    }

                    // Advertir si public_path termina en /public_html
                    if (publicPath.endsWith('/public_html')) {
                        warnings.push('⚠️ ADVERTENCIA: La ruta pública termina en /public_html. ¿Estás seguro? Normalmente debería ser la carpeta donde quieres que esté accesible el sitio.');
                    }

                    if (errors.length > 0) {
                        messageDiv.innerHTML = '<div class="error">' + errors.join('<br>') + '</div>';
                        messageDiv.style.display = 'block';
                    } else if (warnings.length > 0) {
                        messageDiv.innerHTML = '<div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; border: 1px solid #ffeaa7;">' + warnings.join('<br>') + '</div>';
                        messageDiv.style.display = 'block';
                    } else {
                        messageDiv.innerHTML = '<div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; border: 1px solid #c3e6cb;">✅ Las rutas parecen correctas. Puedes continuar.</div>';
                        messageDiv.style.display = 'block';
                    }
                }

                // Auto-calcular base_path cuando cambien los otros campos
                document.getElementById('app_url').addEventListener('input', calcularBasePath);
                document.getElementById('public_path').addEventListener('input', calcularBasePath);

                function calcularBasePath() {
                    // Esta función podría mejorar el cálculo automático si es necesario
                    // Por ahora, el base_path se detecta correctamente en el servidor
                }
                </script>

            <?php elseif ($step == 2): ?>
                <!-- Step 2: Show errors if any -->
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <a href="?step=1" class="btn-secondary">← Volver</a>
                <?php endif; ?>

            <?php elseif ($step == '2b'): ?>
                <!-- Step 2b: Previous installation detected - Choose install mode -->
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h3 style="color: #856404; margin-top: 0;">⚠️ Instalación Previa Detectada</h3>
                    <p style="color: #856404; margin: 10px 0;">
                        Se detectó una instalación existente en las rutas especificadas:
                    </p>
                    <div style="background: white; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 13px;">
                        <strong>Ruta privada:</strong> <?php echo htmlspecialchars($_SESSION['config']['app_path']); ?><br>
                        <?php if (isset($_SESSION['previous_install_info']['has_config']) && $_SESSION['previous_install_info']['has_config']): ?>
                            ✅ config.php encontrado<br>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['previous_install_info']['has_bootstrap']) && $_SESSION['previous_install_info']['has_bootstrap']): ?>
                            ✅ bootstrap.php encontrado<br>
                        <?php endif; ?>
                        <br>
                        <strong>Ruta pública:</strong> <?php echo htmlspecialchars($_SESSION['config']['public_path']); ?><br>
                        <?php if (isset($_SESSION['previous_install_info']['has_index']) && $_SESSION['previous_install_info']['has_index']): ?>
                            ✅ index.php encontrado
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" action="?step=2b">
                    <h3 style="margin-bottom: 20px; color: #333;">Selecciona el Modo de Instalación</h3>

                    <div style="border: 2px solid #667eea; border-radius: 8px; padding: 20px; margin-bottom: 20px; cursor: pointer;" onclick="document.getElementById('mode_reinstall').checked = true;">
                        <label style="cursor: pointer; display: flex; align-items: start;">
                            <input type="radio" name="install_mode" id="mode_reinstall" value="reinstall" style="margin-top: 5px; margin-right: 15px;" checked>
                            <div>
                                <strong style="font-size: 16px; color: #667eea;">🔄 Reinstalar (Actualización)</strong>
                                <p style="margin: 10px 0 0 0; color: #555; font-size: 14px;">
                                    Actualiza los archivos del sistema sin perder tu configuración.<br>
                                    <strong style="color: #28a745;">✅ Sobrescribe:</strong> Archivos PHP, JS, CSS, imágenes<br>
                                    <strong style="color: #dc3545;">❌ NO toca:</strong> Archivos JSON de configuración y datos
                                </p>
                            </div>
                        </label>
                    </div>

                    <div style="border: 2px solid #6c757d; border-radius: 8px; padding: 20px; margin-bottom: 20px; cursor: pointer;" onclick="document.getElementById('mode_new').checked = true;">
                        <label style="cursor: pointer; display: flex; align-items: start;">
                            <input type="radio" name="install_mode" id="mode_new" value="new_version" style="margin-top: 5px; margin-right: 15px;">
                            <div>
                                <strong style="font-size: 16px; color: #6c757d;">🆕 Nueva Versión (Instalación Paralela)</strong>
                                <p style="margin: 10px 0 0 0; color: #555; font-size: 14px;">
                                    Instala una nueva versión del sistema sin tocar la instalación anterior.<br>
                                    <strong>⚠️ Nota:</strong> Si hay conflictos de archivos, se solicitará confirmación.
                                </p>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn">Continuar →</button>
                    <a href="?step=1" class="btn-secondary" style="width: 100%; text-align: center; margin-top: 10px;">← Volver</a>
                </form>

            <?php elseif ($step == '2c'): ?>
                <!-- Step 2c: Conflicts detected - Confirm overwrite -->
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h3 style="color: #721c24; margin-top: 0;">⚠️ Conflictos Detectados</h3>
                    <p style="color: #721c24; margin: 10px 0;">
                        Los siguientes archivos/carpetas ya existen y serían sobrescritos:
                    </p>
                    <div style="background: white; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 13px; max-height: 200px; overflow-y: auto;">
                        <?php if (isset($_SESSION['conflicts'])): ?>
                            <?php foreach ($_SESSION['conflicts'] as $conflict): ?>
                                <?php
                                $parts = explode(':', $conflict);
                                $location = $parts[0] === 'app' ? 'Ruta privada' : 'Ruta pública';
                                $file = $parts[1] ?? $conflict;
                                ?>
                                <div style="padding: 5px 0; border-bottom: 1px solid #eee;">
                                    <strong style="color: #dc3545;"><?php echo htmlspecialchars($location); ?>:</strong>
                                    <?php echo htmlspecialchars($file); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                    <h3 style="color: #0c5460; margin-top: 0;">⚠️ Advertencia Importante</h3>
                    <p style="color: #0c5460; margin: 0;">
                        Si continúas, estos archivos serán sobrescritos. Asegúrate de tener un respaldo si es necesario.
                    </p>
                </div>

                <form method="POST" action="?step=2c">
                    <div style="margin-bottom: 20px;">
                        <p style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">¿Deseas continuar con la instalación?</p>
                        <button type="submit" name="confirm_overwrite" value="YES" class="btn" style="background: #dc3545; margin-bottom: 10px;">
                            Sí, Sobrescribir Archivos
                        </button>
                        <button type="submit" name="confirm_overwrite" value="NO" class="btn-secondary" style="width: 100%; background: #6c757d;">
                            No, Volver al Inicio
                        </button>
                    </div>
                </form>

            <?php elseif ($step == 3): ?>
                <!-- Step 3: Admin form -->
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="info-box">
                    <h3>👤 Usuario Administrador</h3>
                    <ul>
                        <li>Acceso completo al panel de administración</li>
                        <li>Configura el sitio desde el panel</li>
                        <li>Agrega productos, cupones, y más</li>
                    </ul>
                </div>

                <form method="POST" action="?step=3">
                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" name="admin_username" value="<?php echo htmlspecialchars($_SESSION['config']['admin_username'] ?? 'admin'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_SESSION['config']['admin_email'] ?? ''); ?>" required>
                        <small>Se usará para notificaciones del sistema</small>
                    </div>

                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="admin_password" required>
                        <small>Mínimo 8 caracteres</small>
                    </div>

                    <div class="form-group">
                        <label>Confirmar Contraseña</label>
                        <input type="password" name="admin_password_confirm" required>
                    </div>

                    <button type="submit" class="btn">🚀 Instalar Sistema</button>
                </form>

            <?php elseif ($step == 4): ?>
                <!-- Step 4: Installation success -->
                <div class="success-box">
                    <h2>✅ ¡Sistema Instalado!</h2>
                    <p>La instalación se completó exitosamente en menos de 2 minutos.</p>

                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: left; border: 2px solid #28a745;">
                        <h3 style="color: #155724; margin-top: 0; margin-bottom: 15px;">📋 Configuración Instalada</h3>
                        <div style="font-family: monospace; font-size: 13px; line-height: 1.8;">
                            <strong>🗂️ Ruta de Aplicación (privada):</strong><br>
                            <code style="background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0;"><?php echo htmlspecialchars($_SESSION['config']['app_path'] ?? 'N/A'); ?></code><br><br>

                            <strong>🌐 Ruta Pública:</strong><br>
                            <code style="background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0;"><?php echo htmlspecialchars($_SESSION['config']['public_path'] ?? 'N/A'); ?></code><br><br>

                            <strong>🔗 URL del Sitio:</strong><br>
                            <code style="background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0;"><?php echo htmlspecialchars($_SESSION['config']['app_url'] ?? 'N/A'); ?></code><br><br>

                            <strong>📁 Base Path:</strong><br>
                            <code style="background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0;"><?php echo htmlspecialchars($_SESSION['config']['base_path'] ?? '/'); ?></code><br><br>

                            <strong>👤 Usuario Admin:</strong><br>
                            <code style="background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0;"><?php echo htmlspecialchars($_SESSION['config']['admin_username'] ?? 'N/A'); ?></code><br><br>

                            <strong>📧 Email Admin:</strong><br>
                            <code style="background: white; padding: 5px 10px; border-radius: 4px; display: inline-block; margin: 5px 0;"><?php echo htmlspecialchars($_SESSION['config']['admin_email'] ?? 'N/A'); ?></code>
                        </div>
                    </div>

                    <div class="status-list">
                        <div class="status-item">Estructura de directorios creada</div>
                        <div class="status-item">Archivo config.php generado con clave secreta</div>
                        <div class="status-item">28 archivos JSON creados con valores por defecto</div>
                        <div class="status-item">Usuario administrador configurado</div>
                        <div class="status-item">Permisos de seguridad aplicados</div>
                        <div class="status-item">Archivos .htaccess creados</div>
                    </div>

                    <div class="next-steps">
                        <h3>📋 Próximos Pasos</h3>
                        <ol>
                            <li>Accede al panel de administración</li>
                            <li>Configura tu sitio (nombre, logo, descripción)</li>
                            <li>Agrega productos a tu tienda</li>
                            <li>Configura MercadoPago (opcional)</li>
                        </ol>
                    </div>

                    <div class="delete-box">
                        <h3>🗑️ Eliminar Instalador</h3>
                        <p>Por seguridad, elimina el archivo instalador.php ahora.</p>
                        <form method="POST" action="?step=5">
                            <input type="hidden" name="confirm_delete" value="YES">
                            <button type="submit" class="btn-danger">Eliminar Instalador y Acceder al Admin →</button>
                        </form>
                    </div>

                    <div class="links">
                        O accede manualmente:
                        <a href="<?php echo htmlspecialchars($_SESSION['config']['base_path'] ?? ''); ?>/admin/login.php">→ Login Admin</a>
                        |
                        <a href="<?php echo htmlspecialchars($_SESSION['config']['app_url'] ?? ''); ?>">→ Ver Sitio</a>
                    </div>
                </div>

            <!-- STEP 6: Cleanup Complete -->
            <?php elseif ($step == 6): ?>
                <div class="step-content">
                    <div class="success-box" style="text-align: center; padding: 40px 20px;">
                        <h2 style="color: #28a745; font-size: 2.5em; margin-bottom: 20px;">✅ ¡Instalación Completada!</h2>
                        <p style="font-size: 1.2em; color: #666; margin-bottom: 40px;">
                            El instalador y archivos temporales han sido eliminados correctamente.
                        </p>

                        <div style="margin-top: 30px;">
                            <h3 style="margin-bottom: 20px;">¿A dónde quieres ir?</h3>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 600px; margin: 0 auto;">
                                <a href="<?php echo htmlspecialchars($_SESSION['config']['app_url'] . ($_SESSION['config']['base_path'] ?? '')); ?>"
                                   style="display: block; padding: 30px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; transition: transform 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.2);"
                                   onmouseover="this.style.transform='translateY(-5px)'"
                                   onmouseout="this.style.transform='translateY(0)'">
                                    <div style="font-size: 3em; margin-bottom: 10px;">🏪</div>
                                    <div style="font-size: 1.3em; font-weight: bold;">Ver Tienda</div>
                                    <div style="font-size: 0.9em; opacity: 0.9; margin-top: 5px;">Frontend público</div>
                                </a>

                                <a href="<?php echo htmlspecialchars(($_SESSION['config']['app_url'] ?? '') . ($_SESSION['config']['base_path'] ?? '') . '/admin/login.php'); ?>"
                                   style="display: block; padding: 30px 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; text-decoration: none; border-radius: 12px; transition: transform 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.2);"
                                   onmouseover="this.style.transform='translateY(-5px)'"
                                   onmouseout="this.style.transform='translateY(0)'">
                                    <div style="font-size: 3em; margin-bottom: 10px;">🔐</div>
                                    <div style="font-size: 1.3em; font-weight: bold;">Administración</div>
                                    <div style="font-size: 0.9em; opacity: 0.9; margin-top: 5px;">Panel de control</div>
                                </a>
                            </div>
                        </div>

                        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <h4 style="margin-bottom: 10px;">📋 Próximos Pasos</h4>
                            <ul style="text-align: left; max-width: 500px; margin: 0 auto; line-height: 1.8;">
                                <li>Accede al panel de administración con tus credenciales</li>
                                <li>Configura tu sitio (nombre, logo, descripción)</li>
                                <li>Agrega productos a tu tienda</li>
                                <li>Configura MercadoPago para recibir pagos</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
