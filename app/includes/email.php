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
        // Try parent directory of APP_PATH first (production setup)
        $credentials_path = dirname(APP_PATH) . '/notification_credentials.json';
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

    // Get template from JSON
    $template = get_email_template_config('order_created', $order);

    if (!$template) {
        error_log("Failed to load email template: order_created");
        return false;
    }

    $html = get_default_email_template([
        'order' => $order,
        'title' => $template['title'],
        'content' => $template['content']
    ]);

    return send_email($to, $template['subject'], $html);
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

    // Get template from JSON
    $template = get_email_template_config('order_shipped', $order);

    if (!$template) {
        error_log("Failed to load email template: order_shipped");
        return false;
    }

    $html = get_default_email_template([
        'order' => $order,
        'title' => $template['title'],
        'content' => $template['content']
    ]);

    return send_email($to, $template['subject'], $html);
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

    // Get template from JSON
    $template = get_email_template_config('order_paid', $order);

    if (!$template) {
        error_log("Failed to load email template: order_paid");
        return false;
    }

    $html = get_default_email_template([
        'order' => $order,
        'title' => $template['title'],
        'content' => $template['content']
    ]);

    return send_email($to, $template['subject'], $html);
}

/**
 * Send order out for delivery notification to customer
 */
function send_order_in_delivery_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_in_delivery'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];

    // Get template from JSON
    $template = get_email_template_config('order_in_delivery', $order);

    if (!$template) {
        error_log("Failed to load email template: order_in_delivery");
        return false;
    }

    $html = get_default_email_template([
        'order' => $order,
        'title' => $template['title'],
        'content' => $template['content']
    ]);

    return send_email($to, $template['subject'], $html);
}

/**
 * Send order delivered notification to customer
 */
function send_order_delivered_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_delivered'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];

    // Get template from JSON
    $template = get_email_template_config('order_delivered', $order);

    if (!$template) {
        error_log("Failed to load email template: order_delivered");
        return false;
    }

    $html = get_default_email_template([
        'order' => $order,
        'title' => $template['title'],
        'content' => $template['content']
    ]);

    return send_email($to, $template['subject'], $html);
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

    // Get template from JSON
    $template = get_email_template_config('admin_new_order', $order);

    if (!$template) {
        error_log("Failed to load email template: admin_new_order");
        return false;
    }

    $html = get_default_email_template([
        'order' => $order,
        'title' => $template['title'],
        'content' => $template['content']
    ]);

    return send_email($to, $template['subject'], $html);
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
 * Send shipping preparation notification to customer
 * Enviado cuando se genera la etiqueta de envío (estado: en_preparacion)
 */
function send_shipping_preparation_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['shipping_preparation'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Tu Pedido Está en Preparación! - #{$order['order_number']}";

    $html = get_email_template('shipping_preparation', [
        'order' => $order
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
 * Get email template from JSON configuration
 *
 * @param string $template_name Template name (order_created, order_shipped, etc.)
 * @param array $order Order data
 * @return array Template data with subject, title, content
 */
function get_email_template_config($template_name, $order = null) {
    $templates_file = __DIR__ . '/../config/email-templates.json';
    $templates = read_json($templates_file);

    if (!isset($templates[$template_name])) {
        error_log("Email template not found: $template_name");
        return null;
    }

    $template = $templates[$template_name];

    // Process variables if order is provided
    if ($order) {
        $template = process_template_variables($template, $order);
    }

    return $template;
}

/**
 * Process template variables with order data
 *
 * @param array $template Template with subject, title, content
 * @param array $order Order data
 * @return array Template with variables replaced
 */
function process_template_variables($template, $order) {
    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

    // Build items list
    $items_html = '<ul class="items-list">';
    foreach ($order['items'] as $item) {
        $items_html .= "<li><strong>{$item['name']}</strong> x{$item['quantity']}</li>";
    }
    $items_html .= '</ul>';

    // Build tracking info if available
    $tracking_html = '';
    if (!empty($order['tracking_number'])) {
        $tracking_html = "
        <div class='order-info' style='background: #e3f2fd; border-left-color: #2196F3;'>
            <p><strong>📦 Número de seguimiento:</strong> {$order['tracking_number']}</p>";

        if (!empty($order['tracking_url'])) {
            $tracking_html .= "
            <p><a href='{$order['tracking_url']}' class='button' style='background: #2196F3;'>Rastrear Envío</a></p>";
        }

        $tracking_html .= "</div>";
    }

    // Calculate total
    $currency = $order['currency'] === 'USD' ? 'U$D' : '$';
    $total = $currency . ' ' . number_format($order['total'], 2);

    // Variables to replace
    $variables = [
        '{order_number}' => $order['order_number'],
        '{date}' => date('d/m/Y H:i'),
        '{items_list}' => $items_html,
        '{tracking_info}' => $tracking_html,
        '{total}' => $total,
        '{site_name}' => $site_name
    ];

    // Replace in subject, title, and content
    $template['subject'] = str_replace(array_keys($variables), array_values($variables), $template['subject']);
    $template['title'] = str_replace(array_keys($variables), array_values($variables), $template['title']);
    $template['content'] = str_replace(array_keys($variables), array_values($variables), $template['content']);

    return $template;
}

/**
 * Get email footer with social media links
 */
function get_email_footer() {
    $site_config = read_json(__DIR__ . '/../config/site.json');
    $footer_config = read_json(__DIR__ . '/../config/footer.json');

    $site_name = $site_config['site_name'] ?? 'Mi Tienda';
    $contact_email = $site_config['contact_email'] ?? '';
    $site_url = url('/');

    // Get social media links from footer config
    $social = $footer_config['right_column']['social'] ?? [];
    $facebook = $social['facebook'] ?? '';
    $twitter = $social['twitter'] ?? '';
    $instagram = $social['instagram'] ?? '';
    $telegram = $social['telegram'] ?? '';

    // WhatsApp
    $whatsapp_number = $site_config['whatsapp']['number'] ?? '';
    $whatsapp_link = $whatsapp_number ? "https://wa.me/{$whatsapp_number}" : '';

    $footer_html = "
    <table width='100%' cellpadding='0' cellspacing='0' style='background: #292c2f; color: #ffffff; padding: 30px 20px; margin-top: 30px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td align='center' style='padding-bottom: 20px;'>
                            <h2 style='margin: 0; color: #ffffff; font-size: 20px;'>$site_name</h2>
                        </td>
                    </tr>
                    ";

    // Social media icons
    if ($facebook || $twitter || $instagram || $whatsapp_link || $telegram) {
        $footer_html .= "
                    <tr>
                        <td align='center' style='padding-bottom: 20px;'>
                            <div style='display: inline-block;'>";

        if ($facebook) {
            $footer_html .= "
                                <a href='$facebook' style='text-decoration: none; margin: 0 10px; display: inline-block;'>
                                    <img src='https://img.icons8.com/ios-filled/30/ffffff/facebook-new.png' alt='Facebook' style='width: 24px; height: 24px;'>
                                </a>";
        }

        if ($twitter) {
            $footer_html .= "
                                <a href='$twitter' style='text-decoration: none; margin: 0 10px; display: inline-block;'>
                                    <img src='https://img.icons8.com/ios-filled/30/ffffff/twitter.png' alt='Twitter' style='width: 24px; height: 24px;'>
                                </a>";
        }

        if ($instagram) {
            $footer_html .= "
                                <a href='$instagram' style='text-decoration: none; margin: 0 10px; display: inline-block;'>
                                    <img src='https://img.icons8.com/ios-filled/30/ffffff/instagram-new.png' alt='Instagram' style='width: 24px; height: 24px;'>
                                </a>";
        }

        if ($whatsapp_link) {
            $footer_html .= "
                                <a href='$whatsapp_link' style='text-decoration: none; margin: 0 10px; display: inline-block;'>
                                    <img src='https://img.icons8.com/ios-filled/30/ffffff/whatsapp.png' alt='WhatsApp' style='width: 24px; height: 24px;'>
                                </a>";
        }

        if ($telegram) {
            $footer_html .= "
                                <a href='$telegram' style='text-decoration: none; margin: 0 10px; display: inline-block;'>
                                    <img src='https://img.icons8.com/ios-filled/30/ffffff/telegram-app.png' alt='Telegram' style='width: 24px; height: 24px;'>
                                </a>";
        }

        $footer_html .= "
                            </div>
                        </td>
                    </tr>";
    }

    $footer_html .= "
                    <tr>
                        <td align='center' style='padding-bottom: 15px;'>
                            <a href='$site_url' style='color: #ffffff; text-decoration: none; font-size: 14px;'>Visitar Tienda Online</a>
                        </td>
                    </tr>";

    if ($contact_email) {
        $footer_html .= "
                    <tr>
                        <td align='center' style='padding-bottom: 15px;'>
                            <a href='mailto:$contact_email' style='color: #ffffff; text-decoration: none; font-size: 14px;'>$contact_email</a>
                        </td>
                    </tr>";
    }

    $footer_html .= "
                    <tr>
                        <td align='center' style='font-size: 12px; color: #999; padding-top: 15px; border-top: 1px solid #444;'>
                            &copy; " . date('Y') . " $site_name. Todos los derechos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    ";

    return $footer_html;
}

/**
 * Get default email template if specific template not found
 */
function get_default_email_template($vars) {
    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Mi Tienda';

    $order = $vars['order'] ?? null;
    $title = $vars['title'] ?? 'Notificación';
    $content = $vars['content'] ?? '<p>Gracias por tu compra.</p>';

    $footer = get_email_footer();

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
            .content { padding: 40px 30px; background: #ffffff; }
            .content h2 { color: #333; font-size: 22px; margin-top: 0; }
            .order-info { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px 20px; margin: 20px 0; }
            .order-info p { margin: 5px 0; }
            .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; }
            .button:hover { background: #5568d3; }
            .items-list { margin: 20px 0; }
            .items-list li { padding: 8px 0; border-bottom: 1px solid #eee; }
        </style>
    </head>
    <body>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px 0;'>
            <tr>
                <td align='center'>
                    <div class='container'>
                        <div class='header'>
                            <h1>$site_name</h1>
                        </div>
                        <div class='content'>
                            <h2>$title</h2>
                            $content
                            " . ($order ? "
                            <div class='order-info'>
                                <p><strong>📝 Número de pedido:</strong> #{$order['order_number']}</p>
                            </div>
                            " : "") . "
                        </div>
                        $footer
                    </div>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    return $html;
}

/**
 * Queue an email for asynchronous sending
 *
 * @param string $type Email type (order_confirmation, order_shipped, etc.)
 * @param array $data Email data (order, subject, to, etc.)
 * @param string $priority Priority: high, normal, low (default: normal)
 * @return bool Success
 */
function queue_email($type, $data, $priority = 'normal') {
    $queue_file = APP_PATH . '/data/email_queue.json';

    // Initialize queue if not exists
    if (!file_exists($queue_file)) {
        write_json($queue_file, []);
    }

    // Load current queue
    $queue = read_json($queue_file);

    // Generate unique ID
    $email_id = 'email_' . time() . '_' . bin2hex(random_bytes(4));

    // Create email entry
    $email_entry = [
        'id' => $email_id,
        'type' => $type,
        'data' => $data,
        'priority' => $priority,
        'status' => 'pending',
        'attempts' => 0,
        'max_attempts' => 3,
        'created_at' => date('Y-m-d H:i:s'),
        'last_attempt' => null,
        'error' => null
    ];

    // Add to queue
    $queue[] = $email_entry;

    // Save queue
    if (write_json($queue_file, $queue)) {
        error_log("Email queued successfully: $email_id - Type: $type");
        return true;
    } else {
        error_log("Failed to queue email: $email_id - Type: $type");
        return false;
    }
}

/**
 * Clean old sent emails from queue
 * Removes emails with status 'sent' older than $days_old
 *
 * @param int $days_old Days to keep sent emails (default: 7)
 * @return int Number of emails removed
 */
function clean_old_sent_emails($days_old = 7) {
    $queue_file = APP_PATH . '/data/email_queue.json';

    if (!file_exists($queue_file)) {
        return 0;
    }

    $queue = read_json($queue_file);
    $original_count = count($queue);
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_old days"));

    // Filter out old sent emails
    $queue = array_filter($queue, function($email) use ($cutoff_date) {
        // Keep if not sent, or if sent recently
        if ($email['status'] !== 'sent') {
            return true;
        }

        // Remove if sent before cutoff date
        $sent_at = $email['sent_at'] ?? $email['created_at'];
        return $sent_at >= $cutoff_date;
    });

    // Reindex array
    $queue = array_values($queue);

    $removed = $original_count - count($queue);

    if ($removed > 0) {
        write_json($queue_file, $queue);
        error_log("Email queue cleanup: Removed $removed old sent emails");
    }

    return $removed;
}

/**
 * Process email queue (called by cron job)
 *
 * @param int $batch_size Maximum emails to process per run (default: 10)
 * @return array Statistics (sent, failed, pending)
 */
function process_email_queue($batch_size = 10) {
    $queue_file = APP_PATH . '/data/email_queue.json';
    $failed_log_file = APP_PATH . '/data/email_failed.json';

    if (!file_exists($queue_file)) {
        return ['sent' => 0, 'failed' => 0, 'pending' => 0];
    }

    // Load queue
    $queue = read_json($queue_file);
    $failed_emails = file_exists($failed_log_file) ? read_json($failed_log_file) : [];

    $stats = ['sent' => 0, 'failed' => 0, 'pending' => 0, 'skipped' => 0];
    $processed = 0;

    // Sort by priority (high -> normal -> low) and created_at
    usort($queue, function($a, $b) {
        $priority_order = ['high' => 0, 'normal' => 1, 'low' => 2];
        $a_priority = $priority_order[$a['priority']] ?? 1;
        $b_priority = $priority_order[$b['priority']] ?? 1;

        if ($a_priority !== $b_priority) {
            return $a_priority - $b_priority;
        }

        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

    foreach ($queue as $key => $email) {
        // Skip if already processed
        if ($email['status'] !== 'pending') {
            if ($email['status'] === 'sent') {
                $stats['sent']++;
            } elseif ($email['status'] === 'failed') {
                $stats['failed']++;
            }
            $stats['skipped']++;
            continue;
        }

        // Respect batch size
        if ($processed >= $batch_size) {
            $stats['pending']++;
            continue;
        }

        // Process email
        $result = send_queued_email($email);

        if ($result['success']) {
            // Mark as sent
            $queue[$key]['status'] = 'sent';
            $queue[$key]['sent_at'] = date('Y-m-d H:i:s');
            $stats['sent']++;

            error_log("Email sent successfully: {$email['id']} - Type: {$email['type']}");
        } else {
            // Increment attempts
            $queue[$key]['attempts']++;
            $queue[$key]['last_attempt'] = date('Y-m-d H:i:s');
            $queue[$key]['error'] = $result['error'];

            if ($queue[$key]['attempts'] >= $queue[$key]['max_attempts']) {
                // Mark as failed and move to failed log
                $queue[$key]['status'] = 'failed';
                $queue[$key]['failed_at'] = date('Y-m-d H:i:s');
                $stats['failed']++;

                // Add to failed log for dashboard alert
                $failed_emails[] = $queue[$key];

                error_log("Email FAILED after {$queue[$key]['attempts']} attempts: {$email['id']} - Error: {$result['error']}");
            } else {
                error_log("Email attempt {$queue[$key]['attempts']}/{$queue[$key]['max_attempts']} failed: {$email['id']} - Error: {$result['error']}");
                $stats['pending']++;
            }
        }

        $processed++;
    }

    // Save updated queue
    write_json($queue_file, $queue);

    // Save failed log
    if (!empty($failed_emails)) {
        write_json($failed_log_file, $failed_emails);
    }

    // Clean old sent emails (once per day - check last cleanup time)
    $cleanup_lock_file = APP_PATH . '/data/email_cleanup_lock.json';
    $last_cleanup = file_exists($cleanup_lock_file) ? read_json($cleanup_lock_file) : ['last_run' => 0];

    if (time() - $last_cleanup['last_run'] > 86400) { // 24 hours
        $removed = clean_old_sent_emails(7); // Remove emails older than 7 days
        write_json($cleanup_lock_file, ['last_run' => time(), 'last_removed' => $removed]);
    }

    return $stats;
}

/**
 * Send a queued email
 *
 * @param array $email Email entry from queue
 * @return array ['success' => bool, 'error' => string]
 */
function send_queued_email($email) {
    $type = $email['type'];
    $data = $email['data'];

    try {
        // Determine which email function to call based on type
        switch ($type) {
            case 'order_confirmation':
                $result = send_order_confirmation_email($data['order']);
                break;

            case 'payment_approved':
                $result = send_payment_approved_email($data['order']);
                break;

            case 'payment_rejected':
                $result = send_payment_rejected_email($data['order'], $data['status_detail'] ?? '');
                break;

            case 'payment_pending':
                $result = send_payment_pending_email($data['order']);
                break;

            case 'order_shipped':
                $result = send_order_shipped_email($data['order']);
                break;

            case 'order_in_delivery':
                $result = send_order_in_delivery_email($data['order']);
                break;

            case 'order_delivered':
                $result = send_order_delivered_email($data['order']);
                break;

            case 'order_paid':
                $result = send_order_paid_email($data['order']);
                break;

            case 'admin_new_order':
                $result = send_admin_new_order_email($data['order']);
                break;

            case 'admin_chargeback':
                $result = send_admin_chargeback_alert($data['order'], $data['chargeback']);
                break;

            case 'shipping_preparation':
                $result = send_shipping_preparation_email($data['order']);
                break;

            default:
                return ['success' => false, 'error' => "Unknown email type: $type"];
        }

        if ($result) {
            return ['success' => true, 'error' => null];
        } else {
            return ['success' => false, 'error' => 'Email function returned false'];
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get failed emails count (for dashboard alert)
 *
 * @return int Number of failed emails
 */
function get_failed_emails_count() {
    $failed_log_file = APP_PATH . '/data/email_failed.json';

    if (!file_exists($failed_log_file)) {
        return 0;
    }

    $failed = read_json($failed_log_file);
    return count($failed);
}

/**
 * Get failed emails (for admin view)
 *
 * @return array Failed emails
 */
function get_failed_emails() {
    $failed_log_file = APP_PATH . '/data/email_failed.json';

    if (!file_exists($failed_log_file)) {
        return [];
    }

    return read_json($failed_log_file);
}

/**
 * Clear failed emails log
 *
 * @return bool Success
 */
function clear_failed_emails() {
    $failed_log_file = APP_PATH . '/data/email_failed.json';
    return write_json($failed_log_file, []);
}

/**
 * Retry a failed email
 *
 * @param string $email_id Email ID to retry
 * @return bool Success
 */
function retry_failed_email($email_id) {
    $queue_file = APP_PATH . '/data/email_queue.json';
    $failed_log_file = APP_PATH . '/data/email_failed.json';

    if (!file_exists($queue_file) || !file_exists($failed_log_file)) {
        return false;
    }

    $queue = read_json($queue_file);
    $failed = read_json($failed_log_file);

    // Find email in queue and reset status
    foreach ($queue as $key => $email) {
        if ($email['id'] === $email_id && $email['status'] === 'failed') {
            $queue[$key]['status'] = 'pending';
            $queue[$key]['attempts'] = 0;
            $queue[$key]['error'] = null;

            // Remove from failed log
            $failed = array_filter($failed, function($e) use ($email_id) {
                return $e['id'] !== $email_id;
            });

            // Save changes
            write_json($queue_file, $queue);
            write_json($failed_log_file, array_values($failed));

            return true;
        }
    }

    return false;
}
