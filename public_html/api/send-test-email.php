<?php
define('APP_ENTRY_POINT', true);
// Bootstrap de la aplicación - detectar entorno
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    require_once __DIR__ . '/../../app/bootstrap.php';
}

/**
 * Send Test Email API
 * Sends a test email to validate customer email address during checkout
 */

// Apply rate limiting: 5 emails per 10 minutes per IP
api_rate_limit(5, 600);

// Require JSON Content-Type
require_json_content_type();

// Set security headers
set_security_headers();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

$email = sanitize_input($data['email'] ?? '');
$name = sanitize_input($data['name'] ?? '');

// Validate email
if (empty($email) || !validate_email($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email inválido']);
    exit;
}

// Get site configuration
$site_config = read_json(APP_PATH . '/config/site.json');
$site_name = $site_config['site_name'] ?? 'Mi Tienda';

// Prepare email content
$subject = "✅ Email de prueba - $site_name";

$html_body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .success-icon {
            font-size: 48px;
            text-align: center;
            margin: 20px 0;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class='header'>
        <h1>✅ Email de Prueba</h1>
    </div>
    <div class='content'>
        <p>Hola " . htmlspecialchars($name) . ",</p>

        <div class='success-icon'>✅</div>

        <p><strong>¡Excelente! Tu email está configurado correctamente.</strong></p>

        <p>Este es un mensaje de prueba para verificar que podemos enviarte notificaciones sobre tu pedido.</p>

        <p>Si recibiste este correo, significa que:</p>
        <ul>
            <li>✅ Tu dirección de email es válida</li>
            <li>✅ Nuestro sistema puede comunicarse contigo</li>
            <li>✅ Recibirás todas las actualizaciones de tu compra</li>
        </ul>

        <p><strong>Próximos pasos:</strong></p>
        <p>Puedes continuar con tu compra en $site_name. Te enviaremos un email de confirmación cuando completes tu pedido.</p>

        <div class='footer'>
            <p>Este es un email automático de prueba de $site_name</p>
            <p>Si no solicitaste este email, puedes ignorarlo de manera segura.</p>
        </div>
    </div>
</body>
</html>
";

$plain_body = "
Hola $name,

✅ ¡Excelente! Tu email está configurado correctamente.

Este es un mensaje de prueba para verificar que podemos enviarte notificaciones sobre tu pedido.

Si recibiste este correo, significa que:
- Tu dirección de email es válida
- Nuestro sistema puede comunicarse contigo
- Recibirás todas las actualizaciones de tu compra

Próximos pasos:
Puedes continuar con tu compra en $site_name. Te enviaremos un email de confirmación cuando completes tu pedido.

---
Este es un email automático de prueba de $site_name
Si no solicitaste este email, puedes ignorarlo de manera segura.
";

// Send the test email
$result = send_email($email, $subject, $html_body, $plain_body);

if ($result) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Email de prueba enviado correctamente'
    ]);
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo enviar el email. Por favor verifica tu configuración de email.'
    ]);
}
