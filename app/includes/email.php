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
    $subject = "🚚 ¡Tu Pedido Está en Camino! - #{$order['order_number']}";

    // Build order items list
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

    $content = "
    <p>¡Buenas noticias! Tu pedido ha sido despachado y está en camino.</p>

    <div class='order-info'>
        <p><strong>📝 Orden:</strong> #{$order['order_number']}</p>
        <p><strong>📅 Fecha de envío:</strong> " . date('d/m/Y H:i') . "</p>
    </div>

    <h3>📦 Productos enviados:</h3>
    $items_html

    $tracking_html

    <p><strong>📬 ¿Cuándo llega mi pedido?</strong></p>
    <p>El tiempo de entrega estimado es de 3 a 7 días hábiles, dependiendo de tu ubicación. Te enviaremos otra notificación cuando el paquete esté cerca de ser entregado.</p>

    <p><strong>💡 Consejos para recibir tu pedido:</strong></p>
    <ul>
        <li>Asegúrate de que haya alguien disponible en la dirección de entrega</li>
        <li>Verifica que el número de contacto proporcionado esté correcto</li>
        <li>Si no estás, el transportista dejará un aviso para coordinar la entrega</li>
    </ul>

    <p style='margin-top: 30px;'>Si tienes alguna pregunta sobre tu envío, no dudes en contactarnos.</p>
    ";

    $html = get_default_email_template([
        'order' => $order,
        'title' => '🚚 Tu pedido está en camino',
        'content' => $content
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
 * Send order out for delivery notification to customer
 */
function send_order_in_delivery_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_in_delivery'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "🚴 ¡Tu Pedido Está Muy Cerca! - #{$order['order_number']}";

    // Build order items list
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

    $content = "
    <p>¡Excelentes noticias! 🎉</p>
    <p>Tu pedido salió para entrega y <strong>llegará hoy</strong> o en las próximas horas.</p>

    <div class='order-info' style='background: #fff3cd; border-left-color: #ffc107;'>
        <p><strong>📝 Orden:</strong> #{$order['order_number']}</p>
        <p><strong>🚴 Estado:</strong> En Reparto</p>
        <p><strong>📅 Fecha estimada de entrega:</strong> Hoy</p>
    </div>

    <h3>📦 Productos en camino:</h3>
    $items_html

    $tracking_html

    <div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #2196F3;'>
        <h3 style='margin-top: 0; color: #1976d2;'>📍 Preparate para recibir tu pedido</h3>
        <p><strong>Consejos importantes:</strong></p>
        <ul>
            <li><strong>Estate atento:</strong> El repartidor podría llamar o tocar el timbre pronto</li>
            <li><strong>Verifica tu teléfono:</strong> Asegúrate de tenerlo cerca por si te contactan</li>
            <li><strong>Prepara tu DNI:</strong> Algunos envíos requieren verificación de identidad</li>
            <li><strong>Espacio disponible:</strong> Ten un lugar listo para recibir el paquete</li>
        </ul>
    </div>

    <div style='background: #fff9e6; padding: 15px; border-radius: 8px; text-align: center;'>
        <p style='margin: 0; font-weight: 600;'>⏰ <strong>¡Ya casi llega!</strong></p>
        <p style='margin: 10px 0;'>Mantente atento a tu puerta o teléfono</p>
    </div>

    <p style='margin-top: 30px;'>Si tienes alguna pregunta, estamos aquí para ayudarte.</p>
    ";

    $html = get_default_email_template([
        'order' => $order,
        'title' => '🚴 ¡Tu pedido está muy cerca!',
        'content' => $content
    ]);

    return send_email($to, $subject, $html);
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
    $subject = "📦 ¡Tu Pedido Ha Sido Entregado! - #{$order['order_number']}";

    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

    // Build order items list
    $items_html = '<ul class="items-list">';
    foreach ($order['items'] as $item) {
        $items_html .= "<li><strong>{$item['name']}</strong> x{$item['quantity']}</li>";
    }
    $items_html .= '</ul>';

    $content = "
    <p>¡Tu pedido ha sido entregado exitosamente! 🎉</p>

    <div class='order-info' style='background: #e8f5e9; border-left-color: #4CAF50;'>
        <p><strong>📝 Orden:</strong> #{$order['order_number']}</p>
        <p><strong>✅ Estado:</strong> Entregado</p>
        <p><strong>📅 Fecha de entrega:</strong> " . date('d/m/Y H:i') . "</p>
    </div>

    <h3>📦 Productos entregados:</h3>
    $items_html

    <div style='background: #fff9e6; padding: 20px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #ffc107;'>
        <h3 style='margin-top: 0; color: #f57c00;'>💬 Tu opinión nos importa</h3>
        <p>Nos encantaría conocer tu experiencia con nosotros. Tu feedback nos ayuda a mejorar cada día.</p>
        <p><strong>¿Cómo fue tu experiencia con:</strong></p>
        <ul>
            <li>La calidad de los productos</li>
            <li>El tiempo de entrega</li>
            <li>El servicio de atención</li>
        </ul>
        <p>Contáctanos y cuéntanos qué te pareció tu compra. ¡Tu opinión es muy valiosa para nosotros!</p>
    </div>

    <div style='text-align: center; margin: 40px 0;'>
        <h3 style='color: #667eea;'>¡Gracias por confiar en $site_name!</h3>
        <p style='font-size: 16px;'>Esperamos verte pronto por aquí. 😊</p>
    </div>

    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;'>
        <p style='margin: 0; font-weight: 600; color: #667eea;'>¿Te gustó tu compra?</p>
        <p style='margin: 10px 0;'>¡Comparte tu experiencia con tus amigos!</p>
    </div>
    ";

    $html = get_default_email_template([
        'order' => $order,
        'title' => '📦 ¡Pedido Entregado!',
        'content' => $content
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
