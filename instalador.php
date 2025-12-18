<?php
/**
 * Shop V2 - Instalador Minimalista
 * Instalación rápida en 3 pasos con mínima fricción
 *
 * IMPORTANTE: Este archivo se auto-elimina después de la instalación
 */

// Detectar carpetas del paquete (app/ y public_html/ deben estar en el mismo directorio que el instalador)
$local_app_path = __DIR__ . '/app';
$local_public_path = __DIR__ . '/public_html';

if (file_exists($local_app_path) && file_exists($local_public_path)) {
    // Instalación desde paquete descargado

    // Detectar rutas inteligentes
    $current_dir = __DIR__;
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
    // (el contenido de public_html/ del paquete se moverá aquí)
    $suggested_public_path = $current_dir;

    // Auto-detectar base_path desde la URL
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
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
    // Sin estructura válida - el usuario debe descomprimir el paquete completo
    $current_dir = __DIR__;
    $app_exists = file_exists($local_app_path) ? '✅ Existe' : '❌ NO existe';
    $public_exists = file_exists($local_public_path) ? '✅ Existe' : '❌ NO existe';

    die('
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><title>Error - Estructura Incompleta</title></head>
    <body style="font-family: sans-serif; padding: 50px; background: #f8d7da;">
        <div style="max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px;">
            <h1 style="color: #721c24;">⚠️ Error: Estructura Incompleta</h1>
            <p style="font-size: 16px; color: #721c24; margin: 20px 0;">
                No se encontraron las carpetas <code>app/</code> y <code>public_html/</code> necesarias.
            </p>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 13px;">
                <strong>Información de diagnóstico:</strong><br><br>
                <strong>Directorio del instalador:</strong><br>
                <code>' . htmlspecialchars($current_dir) . '</code><br><br>
                <strong>Buscando carpeta app/ en:</strong><br>
                <code>' . htmlspecialchars($local_app_path) . '</code> ' . $app_exists . '<br><br>
                <strong>Buscando carpeta public_html/ en:</strong><br>
                <code>' . htmlspecialchars($local_public_path) . '</code> ' . $public_exists . '
            </div>

            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; color: #856404;">
                <strong>⚠️ Posibles causas:</strong><br>
                1. Estás accediendo a un instalador.php de una instalación previa (las carpetas ya fueron movidas)<br>
                2. No subiste todas las carpetas por FTP<br>
                3. El archivo no se descomprimió correctamente
            </div>

            <p style="color: #721c24;">
                <strong>✅ Solución:</strong><br>
                1. Descarga el archivo <code>shop-v2-test.tar.gz</code> nuevo<br>
                2. Descomprímelo completamente en tu computadora<br>
                3. Sube TODAS las carpetas y archivos a una carpeta nueva por FTP<br>
                4. Accede al <code>instalador.php</code> desde esa carpeta nueva<br><br>

                <strong>Estructura correcta al descomprimir:</strong><br>
                <code style="background: #f8f9fa; padding: 10px; display: block; margin-top: 10px;">
                /tu-carpeta/<br>
                ├── instalador.php<br>
                ├── README_INSTALACION.md<br>
                ├── app/ ← Esta carpeta debe existir<br>
                └── public_html/ ← Esta carpeta debe existir
                </code>
            </p>
        </div>
    </body></html>
    ');
}

// Prevent re-installation (allow force reinstall with ?force=1)
if (file_exists(INSTALLER_APP_PATH . '/config/config.php') && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html lang="es"><head><meta charset="UTF-8"><title>Ya Instalado</title></head>
    <body style="font-family: sans-serif; text-align: center; padding: 50px;">
        <h1>⚠️ Sistema Ya Instalado</h1>
        <p>El sistema ya ha sido instalado.</p>
        <p><strong>IMPORTANTE:</strong> Elimina el archivo instalador.php por seguridad.</p>
        <hr><p><a href="public_html/">Ir al sitio</a> | <a href="public_html/admin/">Admin</a></p>
        <hr style="margin: 30px 0;">
        <p style="color: #dc3545; font-size: 14px;">
            <strong>¿Necesitas reinstalar?</strong><br>
            <a href="?force=1" style="color: #dc3545; font-weight: bold;">⚠️ Forzar Reinstalación</a>
        </p>
    </body></html>
    ');
}

session_start();

// Process installation
$step = $_POST['step'] ?? $_GET['step'] ?? 1;
$errors = [];
$success = false;

// Step 5: Self-delete installer
if ($step == 5 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'YES') {
        delete_installer();
        header('Location: public_html/admin/login.php');
        exit;
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

    // Validate paths
    if (empty($_SESSION['config']['app_path'])) $errors[] = 'Ruta de aplicación requerida';
    if (empty($_SESSION['config']['public_path'])) $errors[] = 'Ruta pública requerida';

    if (empty($errors)) {
        $step = 3; // Go to admin form
    }
}

// Step 3: Process admin and install
if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_username'])) {
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
 * Move installation folders from temporary location to final location
 */
function move_installation_folders($target_app_path, $target_public_path) {
    $source_app = INSTALLER_APP_PATH;
    $source_public = INSTALLER_PUBLIC_PATH;

    // PASO 1: Mover carpeta app/ a su ubicación final
    if ($source_app !== $target_app_path) {
        // Crear directorio padre si no existe
        $parent_dir = dirname($target_app_path);
        if (!is_dir($parent_dir)) {
            if (!mkdir($parent_dir, 0755, true)) {
                error_log("No se pudo crear directorio padre: $parent_dir");
                return false;
            }
        }

        // Mover carpeta app/
        if (!rename($source_app, $target_app_path)) {
            error_log("No se pudo mover $source_app a $target_app_path");
            return false;
        }
    }

    // PASO 2: Mover contenido de public_html/ a su ubicación final
    if ($source_public !== $target_public_path) {
        // Si el directorio de destino no existe, crear
        if (!is_dir($target_public_path)) {
            if (!mkdir($target_public_path, 0755, true)) {
                error_log("No se pudo crear directorio público: $target_public_path");
                return false;
            }
        }

        // Mover todo el contenido de public_html/ a la ubicación final
        $items = scandir($source_public);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $source_item = $source_public . '/' . $item;
            $target_item = $target_public_path . '/' . $item;

            if (!rename($source_item, $target_item)) {
                error_log("No se pudo mover $source_item a $target_item");
                return false;
            }
        }

        // Eliminar carpeta public_html/ vacía
        @rmdir($source_public);
    }

    return true;
}

/**
 * Main installation function
 */
function install_system($config) {
    try {
        $app_path = $config['app_path'];
        $public_path = $config['public_path'];

        // PASO 1: Mover carpetas desde ubicación temporal a ubicación final
        if (!move_installation_folders($app_path, $public_path)) {
            throw new Exception('Error al mover las carpetas de instalación');
        }

        // Create directory structure
        create_directory_structure($app_path, $public_path);

        // Generate security keys
        $secret_key = bin2hex(random_bytes(32));
        $webhook_secret = bin2hex(random_bytes(16));
        $bypass_code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

        // Create config.php
        create_config_php($app_path, $config, $secret_key);

        // Create all configuration JSON files
        create_configuration_json_files($app_path, $config, $webhook_secret, $bypass_code);

        // Create all data JSON files
        create_data_json_files($app_path, $config);

        // Create .htaccess files
        create_htaccess_files($app_path, $public_path);

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
    $theme = ['active_theme' => 'minimal'];
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
function create_htaccess_files($app_path, $public_path) {
    // Protect app directory
    $app_htaccess = "# Security: Block ALL access to application code\n";
    $app_htaccess .= "Require all denied\n";
    $app_htaccess .= "Options -Indexes\n";
    file_put_contents($app_path . '/.htaccess', $app_htaccess);

    // Public root .htaccess (only if doesn't exist)
    $public_htaccess_path = $public_path . '/.htaccess';
    if (!file_exists($public_htaccess_path)) {
        $public_htaccess = "# Rewrite rules\n";
        $public_htaccess .= "RewriteEngine On\n\n";
        $public_htaccess .= "# Redirect to HTTPS (production only)\n";
        $public_htaccess .= "RewriteCond %{HTTPS} off\n";
        $public_htaccess .= "RewriteCond %{HTTP_HOST} !^localhost [NC]\n";
        $public_htaccess .= "RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n\n";
        $public_htaccess .= "# Frontend routing\n";
        $public_htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $public_htaccess .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $public_htaccess .= "RewriteRule ^(.*)$ index.php?route=/$1 [QSA,L]\n\n";
        $public_htaccess .= "# Security\n";
        $public_htaccess .= "Options -Indexes\n\n";
        $public_htaccess .= "# Protect sensitive files\n";
        $public_htaccess .= "<FilesMatch \"(\\.env|\\.git|config\\.php)\">\n";
        $public_htaccess .= "    Require all denied\n";
        $public_htaccess .= "</FilesMatch>\n";
        file_put_contents($public_htaccess_path, $public_htaccess);
    }
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
    return @unlink($installer_file);
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
            <p>Instalación rápida en 3 pasos</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($step == 1 ? 20 : ($step == 2 ? 40 : ($step == 3 ? 60 : ($step == 4 ? 80 : 100)))); ?>%;"></div>
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
                        <a href="public_html/admin/login.php">→ Login Admin</a>
                        |
                        <a href="public_html/">→ Ver Sitio</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
