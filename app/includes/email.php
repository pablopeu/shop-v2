<?php
/**
 * Email System
 * Sistema de envío de emails para notificaciones
 */

require_once __DIR__ . '/functions.php';

/**
 * Get secure credentials from external file
 * Returns notification credentials (SMTP + Telegram) stored outside webroot
 */
function get_secure_credentials() {
    $credentials_path_file = __DIR__ . '/../.credentials_path';

    // Get path to credentials file
    if (!file_exists($credentials_path_file)) {
        error_log("Credentials path file not found. Using default path.");
        $credentials_path = '/home/notification_credentials.json';
    } else {
        $credentials_path = trim(file_get_contents($credentials_path_file));
    }

    // Read credentials file
    if (!file_exists($credentials_path)) {
        error_log("Credentials file not found at: $credentials_path");
        return [
            'smtp' => [
                'host' => '',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls'
            ],
            'telegram' => [
                'bot_token' => '',
                'chat_id' => ''
            ]
        ];
    }

    $credentials = @json_decode(file_get_contents($credentials_path), true);

    if (!$credentials || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON in credentials file: " . json_last_error_msg());
        return [
            'smtp' => ['host' => '', 'port' => 587, 'username' => '', 'password' => '', 'encryption' => 'tls'],
            'telegram' => ['bot_token' => '', 'chat_id' => '']
        ];
    }

    return $credentials;
}

/**
 * Get email configuration with defaults if file doesn't exist
 */
function get_email_config() {
    $config_file = __DIR__ . '/../config/email.json';

    if (!file_exists($config_file)) {
        // Create default config
        $site_config = read_json(__DIR__ . '/../config/site.json');
        $default_config = [
            'enabled' => true,
            'method' => 'mail',
            'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'tienda.com'),
            'from_name' => $site_config['site_name'] ?? 'Mi Tienda',
            'admin_email' => '',
            'smtp' => [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls'
            ],
            'notifications' => [
                'customer' => [
                    'order_created' => true,
                    'payment_approved' => true,
                    'payment_rejected' => true,
                    'payment_pending' => true,
                    'order_paid' => true,
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

        write_json($config_file, $default_config);
        return $default_config;
    }

    return read_json($config_file);
}

/**
 * Send email using configured method
 */
function send_email($to, $subject, $html_body, $plain_body = '') {
    $config = get_email_config();

    if (!($config['enabled'] ?? true)) {
        error_log("Email system disabled - would send to: $to");
        return false;
    }

    $from_email = $config['from_email'] ?? 'noreply@tienda.com';
    $from_name = $config['from_name'] ?? 'Mi Tienda';

    // Prepare plain text version if not provided
    if (empty($plain_body)) {
        $plain_body = strip_tags($html_body);
    }

    // Use configured method
    $method = $config['method'] ?? 'mail';

    if ($method === 'smtp') {
        // Get credentials from secure external file
        $credentials = get_secure_credentials();
        return send_email_smtp($to, $subject, $html_body, $plain_body, $from_email, $from_name, $credentials['smtp']);
    } else {
        return send_email_native($to, $subject, $html_body, $plain_body, $from_email, $from_name);
    }
}

/**
 * Send email using PHP's native mail() function
 * REQUIRES: sendmail, postfix, or another MTA installed on the server
 */
function send_email_native($to, $subject, $html_body, $plain_body, $from_email, $from_name) {
    // Check if mail function is available
    if (!function_exists('mail')) {
        error_log("PHP mail() function is not available on this server");
        return false;
    }

    $boundary = md5(time());

    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plain_body . "\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $html_body . "\r\n";
    $message .= "--$boundary--";

    $result = @mail($to, $subject, $message, $headers);

    if ($result) {
        error_log("Email sent successfully to: $to - Subject: $subject");
    } else {
        error_log("Email failed to send to: $to - Subject: $subject - Possible cause: No MTA (sendmail/postfix) installed or configured");
    }

    return $result;
}

/**
 * Send email using SMTP
 */
function send_email_smtp($to, $subject, $html_body, $plain_body, $from_email, $from_name, $smtp_config) {
    $host = $smtp_config['host'] ?? 'smtp.gmail.com';
    $port = $smtp_config['port'] ?? 587;
    $username = $smtp_config['username'] ?? '';
    $password = $smtp_config['password'] ?? '';
    $encryption = $smtp_config['encryption'] ?? 'tls';

    // Validación básica
    if (empty($username) || empty($password)) {
        error_log("SMTP: Username or password not configured");
        return false;
    }

    try {
        // Conectar al servidor SMTP
        $errno = 0;
        $errstr = '';

        if ($encryption === 'ssl') {
            $smtp_host = 'ssl://' . $host;
            $smtp = @fsockopen($smtp_host, $port, $errno, $errstr, 30);
        } else {
            $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
        }

        if (!$smtp) {
            error_log("SMTP: Could not connect to $host:$port - Error: $errno - $errstr");
            return false;
        }

        // Leer respuesta del servidor
        $response = fgets($smtp, 515);
        error_log("SMTP: Initial response: $response");

        if (substr($response, 0, 3) != '220') {
            error_log("SMTP: Connection failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // EHLO
        fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        error_log("SMTP: EHLO response: " . trim($response));

        // STARTTLS si es necesario
        if ($encryption === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            error_log("SMTP: STARTTLS response: $response");

            if (substr($response, 0, 3) != '220') {
                error_log("SMTP: STARTTLS failed - Response: $response");
                fclose($smtp);
                return false;
            }

            // Habilitar crypto
            stream_set_blocking($smtp, true);
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP: Failed to enable TLS encryption");
                fclose($smtp);
                return false;
            }

            // EHLO nuevamente después de TLS
            fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $response = '';
            while ($line = fgets($smtp, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            error_log("SMTP: EHLO after TLS response: " . trim($response));
        }

        // AUTH LOGIN
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = fgets($smtp, 515);
        error_log("SMTP: AUTH LOGIN response: $response");

        if (substr($response, 0, 3) != '334') {
            error_log("SMTP: AUTH LOGIN failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // Enviar username
        fputs($smtp, base64_encode($username) . "\r\n");
        $response = fgets($smtp, 515);
        error_log("SMTP: Username response: $response");

        if (substr($response, 0, 3) != '334') {
            error_log("SMTP: Username authentication failed - Response: $response - Check username: $username");
            fclose($smtp);
            return false;
        }

        // Enviar password
        fputs($smtp, base64_encode($password) . "\r\n");
        $response = fgets($smtp, 515);
        error_log("SMTP: Password response: $response");

        if (substr($response, 0, 3) != '235') {
            error_log("SMTP: Password authentication failed - Response: $response - Check App Password is correct");
            fclose($smtp);
            return false;
        }

        // MAIL FROM
        fputs($smtp, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP: MAIL FROM failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP: RCPT TO failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '354') {
            error_log("SMTP: DATA command failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // Construir mensaje
        $boundary = md5(time());
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "\r\n";

        $message = $headers;
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $plain_body . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n";
        $message .= "--$boundary--\r\n";
        $message .= ".\r\n";

        // Enviar mensaje
        fputs($smtp, $message);
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP: Message send failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);

        error_log("SMTP: Email sent successfully to $to");
        return true;

    } catch (Exception $e) {
        error_log("SMTP Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation email to customer
 */
function send_order_confirmation_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_created'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "Confirmación de Pedido #{$order['order_number']}";

    $html = get_email_template('order_confirmation', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send payment approved email to customer
 */
function send_payment_approved_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['payment_approved'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Pago Aprobado! - Pedido #{$order['order_number']}";

    $html = get_email_template('payment_approved', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send payment rejected email to customer
 */
function send_payment_rejected_email($order, $status_detail = '') {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['payment_rejected'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "Problema con el Pago - Pedido #{$order['order_number']}";

    $payment_message = get_payment_message('rejected', $status_detail);

    $html = get_email_template('payment_rejected', [
        'order' => $order,
        'payment_message' => $payment_message
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send payment pending email to customer
 */
function send_payment_pending_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['payment_pending'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "Pago Pendiente - Pedido #{$order['order_number']}";

    $html = get_email_template('payment_pending', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send order shipped email to customer
 */
function send_order_shipped_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_shipped'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Tu Pedido Fue Enviado! - #{$order['order_number']}";

    $html = get_email_template('order_shipped', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send order paid notification to customer
 */
function send_order_paid_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_paid'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Pago Confirmado! - #{$order['order_number']}";

    $html = get_email_template('order_paid', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send new order notification to admin
 */
function send_admin_new_order_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['admin']['new_order'] ?? true)) {
        return false;
    }

    $to = $config['admin_email'] ?? 'admin@tienda.com';
    $subject = "🛒 Nueva Orden #{$order['order_number']}";

    $html = get_email_template('admin_new_order', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send chargeback alert to admin
 */
function send_admin_chargeback_alert($order, $chargeback) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['admin']['chargeback_alert'] ?? true)) {
        return false;
    }

    $to = $config['admin_email'] ?? 'admin@tienda.com';
    $subject = "🚨 CONTRACARGO - Orden #{$order['order_number']}";

    $html = get_email_template('admin_chargeback_alert', [
        'order' => $order,
        'chargeback' => $chargeback
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Get email template with variables replaced
 */
function get_email_template($template_name, $vars = []) {
    $template_file = __DIR__ . '/../templates/email/' . $template_name . '.php';

    if (!file_exists($template_file)) {
        error_log("Email template not found: $template_file");
        return get_default_email_template($vars);
    }

    // Extract variables
    extract($vars);

    // Start output buffering
    ob_start();

    // Include template
    include $template_file;

    // Get contents and clean buffer
    $html = ob_get_clean();

    return $html;
}

/**
 * Get default email template if specific template not found
 */
function get_default_email_template($vars) {
    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Mi Tienda';

    $order = $vars['order'] ?? null;

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #667eea; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$site_name</h1>
            </div>
            <div class='content'>
                <p>Gracias por tu compra.</p>
                " . ($order ? "<p><strong>Número de pedido:</strong> {$order['order_number']}</p>" : "") . "
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $site_name. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return $html;
}
