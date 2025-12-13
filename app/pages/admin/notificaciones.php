<?php
/**
 * Admin - Notification Settings
 * Configure email and Telegram notifications
 */



// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');

// Page title for header
$page_title = '🔔 Configuración de Notificaciones';

// File paths
$email_config_file = APP_PATH . '/config/email.json';
$telegram_config_file = APP_PATH . '/config/telegram.json';
$credentials_path_file = APP_PATH . '/.credentials_path';

// Get current credentials from secure file
$credentials = get_secure_credentials();
$smtp_credentials = $credentials['smtp'] ?? ['host' => 'smtp.gmail.com', 'port' => 587, 'username' => '', 'password' => '', 'encryption' => 'tls'];
$telegram_credentials = $credentials['telegram'] ?? ['bot_token' => '', 'chat_id' => ''];

// Default configurations
$default_email_config = [
    'enabled' => true,
    'method' => 'mail',
    'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'tienda.com'),
    'from_name' => $site_config['site_name'] ?? 'Mi Tienda',
    'admin_email' => '',
    'notifications' => [
        'customer' => [
            'order_created' => true,
            'payment_approved' => true,
            'payment_rejected' => true,
            'payment_pending' => true,
            'order_shipped' => true,
            'chargeback_notice' => true
        ],
        'admin' => [
            'new_order' => true,
            'payment_approved' => true,
            'chargeback_alert' => true,
            'low_stock_alert' => true
        ]
    ]
];

$default_telegram_config = [
    'enabled' => false,
    'notifications' => [
        'new_order' => true,
        'payment_approved' => true,
        'payment_rejected' => false,
        'chargeback_alert' => true,
        'low_stock_alert' => true,
        'high_value_order' => true,
        'high_value_threshold' => 50000
    ]
];

// CRITICAL: Clear file stat cache and prevent browser caching
clearstatcache(true, $email_config_file);
clearstatcache(true, $telegram_config_file);

// Prevent browser caching - force fresh data on every load
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Load current config or use defaults
$email_config = file_exists($email_config_file)
    ? read_json($email_config_file)
    : $default_email_config;

$telegram_config = file_exists($telegram_config_file)
    ? read_json($telegram_config_file)
    : $default_telegram_config;

// Check for messages from POST-REDIRECT-GET pattern
$message = '';
if (isset($_SESSION['notification_message'])) {
    $message = $_SESSION['notification_message'];
    unset($_SESSION['notification_message']);
}

$error = '';
if (isset($_SESSION['notification_error'])) {
    $error = $_SESSION['notification_error'];
    unset($_SESSION['notification_error']);
}

$test_result = '';
if (isset($_SESSION['notification_test'])) {
    $test_result = $_SESSION['notification_test'];
    unset($_SESSION['notification_test']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save Email Configuration
    if (isset($_POST['save_email'])) {
        $email_config = [
            'enabled' => isset($_POST['email_enabled']),
            'method' => sanitize_input($_POST['email_method'] ?? 'mail'),
            'from_email' => sanitize_input($_POST['from_email'] ?? ''),
            'from_name' => sanitize_input($_POST['from_name'] ?? ''),
            'admin_email' => sanitize_input($_POST['admin_email'] ?? ''),
            'notifications' => [
                'customer' => [
                    'order_created' => isset($_POST['email_customer_order_created']),
                    'payment_approved' => isset($_POST['email_customer_payment_approved']),
                    'payment_rejected' => isset($_POST['email_customer_payment_rejected']),
                    'payment_pending' => isset($_POST['email_customer_payment_pending']),
                    'order_shipped' => isset($_POST['email_customer_order_shipped']),
                    'chargeback_notice' => isset($_POST['email_customer_chargeback_notice'])
                ],
                'admin' => [
                    'new_order' => isset($_POST['email_admin_new_order']),
                    'payment_approved' => isset($_POST['email_admin_payment_approved']),
                    'chargeback_alert' => isset($_POST['email_admin_chargeback_alert']),
                    'low_stock_alert' => isset($_POST['email_admin_low_stock_alert'])
                ]
            ]
        ];

        // Save SMTP credentials to secure file
        // Remove spaces from password (Gmail App Passwords are shown with spaces: "abcd efgh ijkl mnop")
        $smtp_password = sanitize_input($_POST['smtp_password'] ?? '');
        $smtp_password = str_replace(' ', '', $smtp_password);

        $new_smtp_credentials = [
            'host' => sanitize_input($_POST['smtp_host'] ?? 'smtp.gmail.com'),
            'port' => (int)($_POST['smtp_port'] ?? 587),
            'username' => sanitize_input($_POST['smtp_username'] ?? ''),
            'password' => $smtp_password,
            'encryption' => sanitize_input($_POST['smtp_encryption'] ?? 'tls')
        ];

        // Get credentials path (must match get_secure_credentials())
        $credentials_path = file_exists($credentials_path_file)
            ? trim(file_get_contents($credentials_path_file))
            : '/home/notification_credentials.json';

        // CRITICAL: Clear cache before re-reading
        clearstatcache(true, $credentials_path);

        // CRITICAL: Re-read current credentials from file to preserve all data
        $current_credentials = get_secure_credentials();

        // DEBUG: Log what we read
        error_log("SMTP Save - Current credentials before merge: " . json_encode($current_credentials));

        // Merge new SMTP credentials while preserving Telegram and other credentials
        $all_credentials = array_merge($current_credentials, [
            'smtp' => $new_smtp_credentials
        ]);

        // DEBUG: Log what we're about to save
        error_log("SMTP Save - Credentials after merge: " . json_encode($all_credentials));

        // Save credentials
        $json_content = json_encode($all_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($credentials_path, $json_content) !== false) {
            @chmod($credentials_path, 0600);
            $smtp_credentials = $new_smtp_credentials;
        }

        if (write_json($email_config_file, $email_config)) {
            // POST-REDIRECT-GET pattern
            $_SESSION['notification_message'] = '✅ Configuración de email y credenciales SMTP guardadas exitosamente';
            header('Location: ' . url('/admin/?page=notificaciones'), true, 303);
            exit;
        } else {
            $_SESSION['notification_error'] = '❌ Error al guardar la configuración de email';
            header('Location: ' . url('/admin/?page=notificaciones'), true, 303);
            exit;
        }
    }

    // Save Telegram Configuration
    if (isset($_POST['save_telegram'])) {
        $telegram_config = [
            'enabled' => isset($_POST['telegram_enabled']),
            'notifications' => [
                'new_order' => isset($_POST['telegram_new_order']),
                'payment_approved' => isset($_POST['telegram_payment_approved']),
                'payment_rejected' => isset($_POST['telegram_payment_rejected']),
                'chargeback_alert' => isset($_POST['telegram_chargeback_alert']),
                'low_stock_alert' => isset($_POST['telegram_low_stock_alert']),
                'high_value_order' => isset($_POST['telegram_high_value_order']),
                'high_value_threshold' => (float)($_POST['high_value_threshold'] ?? 50000)
            ]
        ];

        // Save Telegram credentials to secure file
        $new_telegram_credentials = [
            'bot_token' => sanitize_input($_POST['telegram_bot_token'] ?? ''),
            'chat_id' => sanitize_input($_POST['telegram_chat_id'] ?? '')
        ];

        // Get credentials path (must match get_secure_credentials())
        $credentials_path = file_exists($credentials_path_file)
            ? trim(file_get_contents($credentials_path_file))
            : '/home/notification_credentials.json';

        // CRITICAL: Clear cache before re-reading
        clearstatcache(true, $credentials_path);

        // CRITICAL: Re-read current credentials from file to preserve all data
        $current_credentials = get_secure_credentials();

        // DEBUG: Log what we read
        error_log("Telegram Save - Current credentials before merge: " . json_encode($current_credentials));

        // Merge new Telegram credentials while preserving SMTP and other credentials
        $all_credentials = array_merge($current_credentials, [
            'telegram' => $new_telegram_credentials
        ]);

        // DEBUG: Log what we're about to save
        error_log("Telegram Save - Credentials after merge: " . json_encode($all_credentials));

        // Save credentials
        $json_content = json_encode($all_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($credentials_path, $json_content) !== false) {
            @chmod($credentials_path, 0600);
            $telegram_credentials = $new_telegram_credentials;

            // Get bot username from Telegram API
            $bot_username = update_telegram_bot_username();
            if ($bot_username) {
                $telegram_config['bot_username'] = $bot_username;
            }
        }

        if (write_json($telegram_config_file, $telegram_config)) {
            // POST-REDIRECT-GET pattern
            $msg = '✅ Configuración de Telegram y credenciales guardadas exitosamente';
            if ($bot_username) {
                $msg .= ' (Bot: @' . htmlspecialchars($bot_username) . ')';
            }
            $_SESSION['notification_message'] = $msg;
            header('Location: ' . url('/admin/?page=notificaciones'), true, 303);
            exit;
        } else {
            $_SESSION['notification_error'] = '❌ Error al guardar la configuración de Telegram';
            header('Location: ' . url('/admin/?page=notificaciones'), true, 303);
            exit;
        }
    }

    // Test Email
    if (isset($_POST['test_email'])) {
        $test_email = sanitize_input($_POST['test_email_address'] ?? '');
        if (!empty($test_email)) {
            $result = send_email(
                $test_email,
                'Email de Prueba - ' . $site_config['site_name'],
                '<h1>✅ Email de Prueba</h1><p>Si estás leyendo esto, tu configuración de email funciona correctamente.</p><p><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>',
                'Email de Prueba. Si estás leyendo esto, tu configuración funciona correctamente. Fecha: ' . date('d/m/Y H:i:s')
            );

            if ($result) {
                $_SESSION['notification_test'] = '✅ Email de prueba enviado correctamente a ' . htmlspecialchars($test_email);
            } else {
                // Check which method was used to provide specific error message
                $current_method = $email_config['method'] ?? 'mail';
                if ($current_method === 'mail') {
                    $_SESSION['notification_test'] = '❌ Error al enviar email. PHP mail() requiere un MTA (sendmail/postfix) instalado. <strong>Recomendación:</strong> Cambia a SMTP o instala postfix: <code>sudo apt-get install postfix</code>';
                } else {
                    $_SESSION['notification_test'] = '❌ Error al enviar email. Verifica tus credenciales SMTP en "Configuración del Sistema".';
                }
            }
        } else {
            $_SESSION['notification_test'] = '❌ Debes ingresar una dirección de email';
        }
        header('Location: ' . url('/admin/?page=notificaciones'), true, 303);
        exit;
    }

    // Test Telegram
    if (isset($_POST['test_telegram'])) {
        // Auto-save telegram configuration before testing
        $telegram_config = [
            'enabled' => isset($_POST['telegram_enabled']),
            'notifications' => [
                'new_order' => isset($_POST['telegram_new_order']),
                'payment_approved' => isset($_POST['telegram_payment_approved']),
                'payment_rejected' => isset($_POST['telegram_payment_rejected']),
                'chargeback_alert' => isset($_POST['telegram_chargeback_alert']),
                'low_stock_alert' => isset($_POST['telegram_low_stock_alert']),
                'high_value_order' => isset($_POST['telegram_high_value_order']),
                'high_value_threshold' => (float)($_POST['high_value_threshold'] ?? 50000)
            ]
        ];

        write_json($telegram_config_file, $telegram_config);

        // Auto-save Telegram credentials before testing
        $new_telegram_credentials = [
            'bot_token' => sanitize_input($_POST['telegram_bot_token'] ?? ''),
            'chat_id' => sanitize_input($_POST['telegram_chat_id'] ?? '')
        ];

        // Get credentials path (must match get_secure_credentials())
        $credentials_path = file_exists($credentials_path_file)
            ? trim(file_get_contents($credentials_path_file))
            : '/home/notification_credentials.json';

        // CRITICAL: Clear cache before re-reading
        clearstatcache(true, $credentials_path);

        // CRITICAL: Re-read current credentials from file to preserve all data
        $current_credentials = get_secure_credentials();

        // DEBUG: Log what we read
        error_log("Telegram Test - Current credentials before merge: " . json_encode($current_credentials));

        // Merge new Telegram credentials while preserving SMTP and other credentials
        $all_credentials = array_merge($current_credentials, [
            'telegram' => $new_telegram_credentials
        ]);

        // DEBUG: Log what we're about to save
        error_log("Telegram Test - Credentials after merge: " . json_encode($all_credentials));

        // Save credentials
        $json_content = json_encode($all_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($credentials_path, $json_content) !== false) {
            @chmod($credentials_path, 0600);
            $telegram_credentials = $new_telegram_credentials;

            // Get bot username from Telegram API
            $bot_username = update_telegram_bot_username();
            if ($bot_username) {
                $telegram_config['bot_username'] = $bot_username;
                write_json($telegram_config_file, $telegram_config);
            }
        }

        // Now test
        $result = send_telegram_test();

        if ($result) {
            $msg = '✅ Mensaje de prueba enviado correctamente a Telegram';
            if ($bot_username) {
                $msg .= ' (Bot: @' . htmlspecialchars($bot_username) . ')';
            }
            $_SESSION['notification_test'] = $msg;
        } else {
            $_SESSION['notification_test'] = '❌ Error al enviar mensaje de prueba. Verifica tu bot_token y chat_id.';
        }
        header('Location: ' . url('/admin/?page=notificaciones'), true, 303);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 15px 20px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .message.info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        /* Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header h2 {
            font-size: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header .icon {
            font-size: 24px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="password"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #7f8c8d;
            font-size: 12px;
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }

        /* Section */
        .form-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .form-section h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* SMTP Fields */
        .smtp-fields {
            display: none;
            margin-top: 15px;
        }

        .smtp-fields.show {
            display: block;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }

        .status-badge.enabled {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.disabled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Test Section */
        .test-section {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }

        .test-section h4 {
            font-size: 14px;
            color: #856404;
            margin-bottom: 10px;
        }

        .test-input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .test-input-group .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        /* Table Container for Mobile Scroll */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -15px;
            padding: 0 15px;
        }

        @media (min-width: 1025px) {
            .table-container {
                overflow-x: visible;
                margin: 0;
                padding: 0;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .filters-row {
                grid-template-columns: 1fr !important;
            }

            table {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            table {
                font-size: 12px;
                min-width: 800px;
            }

            table th,
            table td {
                padding: 8px 6px !important;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px;
            }

            .actions {
                flex-direction: column !important;
                gap: 5px !important;
            }

            .actions .btn {
                width: 100%;
                padding: 6px 10px;
            }

            .bulk-actions-bar {
                flex-direction: column;
                gap: 8px;
            }

            .bulk-actions-bar select,
            .bulk-actions-bar .btn {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr !important;
            }

            .form-row {
                flex-direction: column;
            }

            /* Better touch targets */
            .btn {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="password"],
            select,
            textarea {
                font-size: 16px !important;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($test_result): ?>
        <div class="message <?php echo strpos($test_result, '✅') !== false ? 'success' : 'error'; ?>">
            <?php echo $test_result; ?>
        </div>
        <?php endif; ?>

        <div class="cards-grid">
            <!-- EMAIL CONFIGURATION -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <span class="icon">📧</span>
                        Configuración de Email
                    </h2>
                    <span class="status-badge <?php echo $email_config['enabled'] ? 'enabled' : 'disabled'; ?>">
                        <?php echo $email_config['enabled'] ? 'Activado' : 'Desactivado'; ?>
                    </span>
                </div>

                <form method="POST">
                    <!-- Enable/Disable -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="email_enabled" name="email_enabled"
                               <?php echo $email_config['enabled'] ? 'checked' : ''; ?>>
                        <label for="email_enabled">Activar sistema de emails</label>
                    </div>

                    <!-- Basic Settings -->
                    <div class="form-group">
                        <label for="email_method">Método de Envío</label>
                        <select id="email_method" name="email_method" data-onchange="toggleSmtpFields">
                            <option value="mail" <?php echo $email_config['method'] === 'mail' ? 'selected' : ''; ?>>
                                PHP mail() - Nativo
                            </option>
                            <option value="smtp" <?php echo $email_config['method'] === 'smtp' ? 'selected' : ''; ?>>
                                SMTP - Externo
                            </option>
                        </select>
                        <small>mail() usa el servidor local, SMTP permite Gmail, etc.</small>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="from_email">Email Remitente</label>
                            <input type="email" id="from_email" name="from_email"
                                   value="<?php echo htmlspecialchars($email_config['from_email']); ?>"
                                   autocomplete="email" required>
                            <small>Email que aparece como remitente</small>
                        </div>

                        <div class="form-group">
                            <label for="from_name">Nombre Remitente</label>
                            <input type="text" id="from_name" name="from_name"
                                   value="<?php echo htmlspecialchars($email_config['from_name']); ?>"
                                   autocomplete="name" required>
                            <small>Nombre que aparece como remitente</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">Email del Administrador</label>
                        <input type="email" id="admin_email" name="admin_email"
                               value="<?php echo htmlspecialchars($email_config['admin_email']); ?>"
                               autocomplete="email" required>
                        <small>Recibirás notificaciones de órdenes, chargebacks, etc.</small>
                    </div>

                    <!-- SMTP Settings -->
                    <div class="smtp-fields <?php echo $email_config['method'] === 'smtp' ? 'show' : ''; ?>" id="smtp-fields">
                        <div class="form-section">
                            <h3>🔐 Credenciales SMTP</h3>

                            <div class="info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; border-radius: 4px; font-size: 12px;">
                                <strong>🔒 Seguridad:</strong> Las credenciales se guardan en un archivo fuera del directorio público.
                                <a href="<?php echo url('/admin/?page=config-rutas-sistema'); ?>" style="color: #856404; text-decoration: underline;">Configurar ubicación →</a>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label for="smtp_host">Host SMTP</label>
                                    <input type="text" id="smtp_host" name="smtp_host"
                                           value="<?php echo htmlspecialchars($smtp_credentials['host']); ?>"
                                           placeholder="smtp.gmail.com"
                                           autocomplete="off">
                                </div>

                                <div class="form-group">
                                    <label for="smtp_port">Puerto</label>
                                    <input type="number" id="smtp_port" name="smtp_port"
                                           value="<?php echo $smtp_credentials['port']; ?>"
                                           placeholder="587"
                                           autocomplete="off">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="smtp_username">Usuario SMTP (tu email de Gmail)</label>
                                <input type="text" id="smtp_username" name="smtp_username"
                                       value="<?php echo htmlspecialchars($smtp_credentials['username']); ?>"
                                       placeholder="tu-email@gmail.com"
                                       autocomplete="email">
                            </div>

                            <div class="form-group">
                                <label for="smtp_password">Contraseña SMTP (App Password)</label>
                                <input type="password" id="smtp_password" name="smtp_password"
                                       value="<?php echo htmlspecialchars($smtp_credentials['password']); ?>"
                                       placeholder="••••••••••••••••"
                                       autocomplete="current-password">
                                <small>Para Gmail, usa una <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a> en lugar de tu contraseña normal</small>
                            </div>

                            <div class="form-group">
                                <label for="smtp_encryption">Encriptación</label>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo $smtp_credentials['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (recomendado para puerto 587)</option>
                                    <option value="ssl" <?php echo $smtp_credentials['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (para puerto 465)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Notifications -->
                    <div class="form-section">
                        <h3>👤 Notificaciones al Cliente</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_order_created" name="email_customer_order_created"
                                   <?php echo $email_config['notifications']['customer']['order_created'] ? 'checked' : ''; ?>>
                            <label for="email_customer_order_created">Confirmación de orden creada</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_payment_approved" name="email_customer_payment_approved"
                                   <?php echo $email_config['notifications']['customer']['payment_approved'] ? 'checked' : ''; ?>>
                            <label for="email_customer_payment_approved">Pago aprobado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_payment_rejected" name="email_customer_payment_rejected"
                                   <?php echo $email_config['notifications']['customer']['payment_rejected'] ? 'checked' : ''; ?>>
                            <label for="email_customer_payment_rejected">Pago rechazado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_payment_pending" name="email_customer_payment_pending"
                                   <?php echo $email_config['notifications']['customer']['payment_pending'] ? 'checked' : ''; ?>>
                            <label for="email_customer_payment_pending">Pago pendiente</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_order_shipped" name="email_customer_order_shipped"
                                   <?php echo $email_config['notifications']['customer']['order_shipped'] ? 'checked' : ''; ?>>
                            <label for="email_customer_order_shipped">Orden enviada</label>
                        </div>
                    </div>

                    <!-- Admin Notifications -->
                    <div class="form-section">
                        <h3>👨‍💼 Notificaciones al Administrador</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_new_order" name="email_admin_new_order"
                                   <?php echo $email_config['notifications']['admin']['new_order'] ? 'checked' : ''; ?>>
                            <label for="email_admin_new_order">Nueva orden recibida</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_payment_approved" name="email_admin_payment_approved"
                                   <?php echo $email_config['notifications']['admin']['payment_approved'] ? 'checked' : ''; ?>>
                            <label for="email_admin_payment_approved">Pago aprobado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_chargeback_alert" name="email_admin_chargeback_alert"
                                   <?php echo $email_config['notifications']['admin']['chargeback_alert'] ? 'checked' : ''; ?>>
                            <label for="email_admin_chargeback_alert">Alerta de contracargo (crítico)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_low_stock_alert" name="email_admin_low_stock_alert"
                                   <?php echo $email_config['notifications']['admin']['low_stock_alert'] ? 'checked' : ''; ?>>
                            <label for="email_admin_low_stock_alert">Alerta de stock bajo</label>
                        </div>
                    </div>

                    <!-- Test Section -->
                    <div class="test-section">
                        <h4>🧪 Probar Configuración de Email</h4>
                        <div class="test-input-group">
                            <div class="form-group">
                                <label for="test_email_address">Email de Prueba</label>
                                <input type="email" id="test_email_address" name="test_email_address"
                                       placeholder="tu-email@example.com"
                                       autocomplete="email">
                            </div>
                            <button type="submit" name="test_email" class="btn btn-info">
                                📤 Enviar Test
                            </button>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_email" class="btn btn-primary">
                            💾 Guardar Configuración de Email
                        </button>
                    </div>
                </form>
            </div>

            <!-- TELEGRAM CONFIGURATION -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <span class="icon">📱</span>
                        Configuración de Telegram
                    </h2>
                    <span class="status-badge <?php echo $telegram_config['enabled'] ? 'enabled' : 'disabled'; ?>">
                        <?php echo $telegram_config['enabled'] ? 'Activado' : 'Desactivado'; ?>
                    </span>
                </div>

                <form method="POST">
                    <!-- Enable/Disable -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="telegram_enabled" name="telegram_enabled"
                               <?php echo $telegram_config['enabled'] ? 'checked' : ''; ?>>
                        <label for="telegram_enabled">Activar notificaciones por Telegram</label>
                    </div>

                    <!-- Bot Configuration -->
                    <div class="form-section">
                        <h3>🔐 Credenciales de Telegram</h3>

                        <div class="info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; border-radius: 4px; font-size: 12px;">
                            <strong>🔒 Seguridad:</strong> Las credenciales se guardan en un archivo fuera del directorio público.
                            <a href="<?php echo url('/admin/?page=config-rutas-sistema'); ?>" style="color: #856404; text-decoration: underline;">Configurar ubicación →</a>
                        </div>

                        <div class="form-group">
                            <label for="telegram_bot_token">Bot Token</label>
                            <input type="text" id="telegram_bot_token" name="telegram_bot_token"
                                   value="<?php echo htmlspecialchars($telegram_credentials['bot_token']); ?>"
                                   placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                            <small>Obtén tu token de <a href="https://t.me/BotFather" target="_blank">@BotFather</a></small>
                        </div>

                        <div class="form-group">
                            <label for="telegram_chat_id">Chat ID</label>
                            <input type="text" id="telegram_chat_id" name="telegram_chat_id"
                                   value="<?php echo htmlspecialchars($telegram_credentials['chat_id']); ?>"
                                   placeholder="123456789">
                            <small>ID del chat/canal donde recibirás mensajes</small>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="form-section">
                        <h3>🔔 Tipos de Notificaciones</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_new_order" name="telegram_new_order"
                                   <?php echo $telegram_config['notifications']['new_order'] ? 'checked' : ''; ?>>
                            <label for="telegram_new_order">Nueva orden</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_payment_approved" name="telegram_payment_approved"
                                   <?php echo $telegram_config['notifications']['payment_approved'] ? 'checked' : ''; ?>>
                            <label for="telegram_payment_approved">Pago aprobado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_payment_rejected" name="telegram_payment_rejected"
                                   <?php echo $telegram_config['notifications']['payment_rejected'] ? 'checked' : ''; ?>>
                            <label for="telegram_payment_rejected">Pago rechazado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_chargeback_alert" name="telegram_chargeback_alert"
                                   <?php echo $telegram_config['notifications']['chargeback_alert'] ? 'checked' : ''; ?>>
                            <label for="telegram_chargeback_alert">🚨 Alerta de contracargo (crítico)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_low_stock_alert" name="telegram_low_stock_alert"
                                   <?php echo $telegram_config['notifications']['low_stock_alert'] ? 'checked' : ''; ?>>
                            <label for="telegram_low_stock_alert">Alerta de stock bajo</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_high_value_order" name="telegram_high_value_order"
                                   <?php echo $telegram_config['notifications']['high_value_order'] ? 'checked' : ''; ?>>
                            <label for="telegram_high_value_order">Destacar órdenes de alto valor 🌟</label>
                        </div>
                    </div>

                    <!-- High Value Threshold -->
                    <div class="form-group">
                        <label for="high_value_threshold">Umbral para Órdenes de Alto Valor</label>
                        <input type="number" id="high_value_threshold" name="high_value_threshold"
                               value="<?php echo $telegram_config['notifications']['high_value_threshold']; ?>"
                               step="0.01" min="0">
                        <small>Órdenes iguales o mayores a este monto se destacan con 🌟</small>
                    </div>

                    <!-- Test Section -->
                    <div class="test-section">
                        <h4>🧪 Probar Conexión con Telegram</h4>
                        <p style="font-size: 13px; color: #856404; margin-bottom: 10px;">
                            Envía un mensaje de prueba a tu bot para verificar la configuración
                        </p>
                        <button type="submit" name="test_telegram" class="btn btn-info">
                            📤 Enviar Mensaje de Prueba
                        </button>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_telegram" class="btn btn-success">
                            💾 Guardar Configuración de Telegram
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <script nonce="<?= csp_nonce() ?>">
        function toggleSmtpFields() {
            const method = document.getElementById('email_method').value;
            const smtpFields = document.getElementById('smtp-fields');

            if (method === 'smtp') {
                smtpFields.classList.add('show');
            } else {
                smtpFields.classList.remove('show');
            }
        }

        function updatePortByEncryption() {
            const encryption = document.getElementById('smtp_encryption').value;
            const portField = document.getElementById('smtp_port');

            // Solo actualizar si el usuario no ha cambiado manualmente a un puerto no estándar
            const currentPort = parseInt(portField.value);
            const standardPorts = [587, 465, 25, 2525];

            // Si el puerto actual es estándar o está vacío, actualizarlo
            if (!currentPort || standardPorts.includes(currentPort)) {
                if (encryption === 'tls') {
                    portField.value = 587;
                } else if (encryption === 'ssl') {
                    portField.value = 465;
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleSmtpFields();

            // Add event listener to encryption selector
            const encryptionSelect = document.getElementById('smtp_encryption');
            if (encryptionSelect) {
                encryptionSelect.addEventListener('change', updatePortByEncryption);
            }
        });

        // Wrapper for event delegation
        const _toggleSmtpFields = toggleSmtpFields;
        window.toggleSmtpFields = function(event, element, params) {
            return _toggleSmtpFields();
        };
    </script>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
