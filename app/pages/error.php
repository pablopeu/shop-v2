<?php
/**
 * Error 404 Page
 * Página de error cuando no se encuentra una ruta
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Get site configuration
$site_config = read_json(APP_PATH . '/config/site.json');

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada - <?php echo htmlspecialchars($site_config['site_name'] ?? 'Shop'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .error-code {
            font-size: 120px;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 20px;
        }

        h1 {
            color: #2d3748;
            font-size: 32px;
            margin-bottom: 15px;
        }

        p {
            color: #718096;
            font-size: 18px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">🔍</div>
        <div class="error-code">404</div>
        <h1>Página no encontrada</h1>
        <p>Lo sentimos, la página que buscas no existe o ha sido movida.</p>
        <a href="<?php echo url('/'); ?>" class="btn">Volver al inicio</a>
    </div>
</body>
</html>
