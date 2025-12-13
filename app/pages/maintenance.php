<?php
/**
 * Maintenance Mode Page
 * Se muestra cuando el sitio está en modo mantenimiento
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

$maintenance_config = read_json(APP_PATH . '/config/maintenance.json');
$message = $maintenance_config['message'] ?? 'Sitio en mantenimiento. Volveremos pronto.';
$site_config = read_json(APP_PATH . '/config/site.json');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - <?php echo htmlspecialchars($site_config['site_name'] ?? 'Tienda'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .maintenance-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .maintenance-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .message {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .info {
            font-size: 14px;
            color: #888;
            margin-top: 40px;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .maintenance-container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 24px;
            }

            .message {
                font-size: 16px;
            }

            .maintenance-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="logo"><?php echo htmlspecialchars($site_config['site_name'] ?? 'Tienda'); ?></div>
        <div class="maintenance-icon">🚧</div>
        <h1>Sitio en Mantenimiento</h1>
        <div class="message">
            <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
        <div class="info">
            Estamos trabajando para mejorar tu experiencia.<br>
            Volvé a intentar en unos minutos.
        </div>
    </div>
</body>
</html>
