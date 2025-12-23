<?php
/**
 * Admin - Logistics Configuration (Multi-Carrier)
 * Configuración de logística multi-carrier
 */

require_admin();

$message = '';
$error = '';

// Test connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        require_once APP_PATH . '/includes/carriers.php';
        $test_result = zipnova_test_connection();

        if ($test_result['success']) {
            $message = $test_result['message'] . ' (Modo: ' . $test_result['mode'] . ')';
        } else {
            $error = $test_result['error'];
        }
    }
}

// Save shipping configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipping'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        require_once APP_PATH . '/includes/carriers.php';

        $config = zipnova_get_config();
        if (!$config) {
            $config = json_decode(file_get_contents(APP_PATH . '/config/shipping.json'), true)['zipnova'];
        }

        // Update carrier metadata
        $config['tag'] = strtoupper(sanitize_input($_POST['carrier_tag'] ?? 'ZNVA'));
        $config['name'] = sanitize_input($_POST['carrier_name'] ?? 'Zipnova');
        $config['type'] = sanitize_input($_POST['carrier_type'] ?? 'zipnova');

        // Update configuration
        $config['enabled'] = isset($_POST['zipnova_enabled']);
        $config['mode'] = $_POST['zipnova_mode'] ?? 'sandbox';

        // Credentials
        if (!empty($_POST['zipnova_client_id'])) {
            $config['credentials']['client_id'] = sanitize_input($_POST['zipnova_client_id']);
        }
        if (!empty($_POST['zipnova_client_secret'])) {
            $config['credentials']['client_secret'] = sanitize_input($_POST['zipnova_client_secret']);
        }

        // Origin configuration
        $config['origin'] = [
            'name' => sanitize_input($_POST['origin_name'] ?? ''),
            'address' => sanitize_input($_POST['origin_address'] ?? ''),
            'city' => sanitize_input($_POST['origin_city'] ?? ''),
            'province' => sanitize_input($_POST['origin_province'] ?? ''),
            'postal_code' => sanitize_input($_POST['origin_postal_code'] ?? ''),
            'country' => sanitize_input($_POST['origin_country'] ?? 'AR'),
            'phone' => sanitize_input($_POST['origin_phone'] ?? ''),
            'email' => sanitize_input($_POST['origin_email'] ?? '')
        ];

        // Default package dimensions
        $config['default_package'] = [
            'weight' => (float)($_POST['default_weight'] ?? 1),
            'length' => (float)($_POST['default_length'] ?? 20),
            'width' => (float)($_POST['default_width'] ?? 15),
            'height' => (float)($_POST['default_height'] ?? 10)
        ];

        // Options
        $config['options']['auto_create_shipment'] = isset($_POST['auto_create_shipment']);
        $config['options']['shipping_cost_margin'] = (float)($_POST['shipping_cost_margin'] ?? 0);
        $config['options']['cache_quotes_minutes'] = (int)($_POST['cache_quotes_minutes'] ?? 5);
        $config['options']['webhook_secret'] = sanitize_input($_POST['webhook_secret'] ?? '');

        // Enabled services
        $config['enabled_services'] = [
            'standard' => isset($_POST['service_standard']),
            'express' => isset($_POST['service_express']),
            'same_day' => isset($_POST['service_same_day'])
        ];

        // Save configuration
        if (zipnova_save_config($config)) {
            $_SESSION['shipping_config_message'] = 'Configuración de envíos guardada exitosamente';
            log_admin_action('shipping_config_updated', $_SESSION['username']);

            // Redirect to avoid form resubmission
            header('Location: ' . url('/admin/?page=config-shipping'));
            exit;
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Check for message from redirect
if (isset($_SESSION['shipping_config_message'])) {
    $message = $_SESSION['shipping_config_message'];
    unset($_SESSION['shipping_config_message']);
}

require_once APP_PATH . '/includes/carriers.php';
$shipping_config = zipnova_get_config();
$page_title = 'Configuración de Logística';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Get webhook URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$webhook_url = $protocol . $_SERVER['HTTP_HOST'] . url('/api/shipping/webhook');

// Provincias de Argentina
$provincias = [
    'Buenos Aires', 'CABA', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba',
    'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja',
    'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan',
    'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero',
    'Tierra del Fuego', 'Tucumán'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Logística - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1400px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }

        /* Cards Grid System */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: box-shadow 0.3s;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .card-full {
            grid-column: 1 / -1;
        }

        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-description {
            color: #666;
            font-size: 13px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .form-group { margin-bottom: 15px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 80px; font-family: inherit; }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-weight: normal;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .checkbox-label input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-save-container {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            padding: 14px 40px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save {
            background: #6c757d;
        }

        .btn-test {
            background: #28a745;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            line-height: 1.4;
        }

        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-box strong { color: #856404; }
        .alert-box p { color: #856404; margin: 5px 0; font-size: 13px; }

        .webhook-url {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
            border: 1px solid #dee2e6;
        }

        .copy-btn {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
            transition: all 0.2s;
        }
        .copy-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-enabled {
            background: #d4edda;
            color: #155724;
        }

        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .cards-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .cards-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="alert-box">
            <strong>🚚 Configuración de Logística</strong>
            <p>1. Obtené tus credenciales de API en el panel de Zipnova</p>
            <p>2. Configurá tus credenciales y datos de origen</p>
            <p>3. Probá la conexión antes de habilitar el sistema</p>
            <p>4. Configurá el webhook en Zipnova con la URL proporcionada abajo</p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="cards-grid">
                <!-- Información del Carrier -->
                <div class="card card-full">
                    <div class="card-title">
                        ℹ️ Información del Carrier
                    </div>
                    <div class="card-description">
                        Identificación y nombre del carrier para el sistema multi-carrier
                    </div>

                    <div class="form-group">
                        <label>Tag del Carrier (4 letras)</label>
                        <input type="text" name="carrier_tag"
                               value="<?php echo htmlspecialchars($shipping_config['tag'] ?? 'ZNVA'); ?>"
                               maxlength="4"
                               pattern="[A-Z0-9]{4}"
                               placeholder="ZNVA"
                               style="text-transform: uppercase; width: 150px;"
                               required>
                        <div class="help-text">Tag único de 4 letras para identificar este carrier (ej: ZNVA, ZNV2)</div>
                    </div>

                    <div class="form-group">
                        <label>Nombre Descriptivo</label>
                        <input type="text" name="carrier_name"
                               value="<?php echo htmlspecialchars($shipping_config['name'] ?? 'Zipnova'); ?>"
                               placeholder="Zipnova Principal"
                               required>
                        <div class="help-text">Nombre que se mostrará en el sistema</div>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Carrier</label>
                        <select name="carrier_type" required>
                            <option value="zipnova" <?php echo ($shipping_config['type'] ?? 'zipnova') === 'zipnova' ? 'selected' : ''; ?>>
                                Zipnova
                            </option>
                            <option value="manual" <?php echo ($shipping_config['type'] ?? '') === 'manual' ? 'selected' : ''; ?>>
                                Manual
                            </option>
                        </select>
                        <div class="help-text">Tipo de integración a utilizar</div>
                    </div>
                </div>

                <!-- Estado General -->
                <div class="card card-full">
                    <div class="card-title">
                        🚀 Estado del Sistema
                    </div>
                    <div class="card-description">
                        Estado actual:
                        <?php if ($shipping_config['enabled']): ?>
                            <span class="status-badge status-enabled">HABILITADO</span>
                        <?php else: ?>
                            <span class="status-badge status-disabled">DESHABILITADO</span>
                        <?php endif; ?>
                        - Modo: <strong><?php echo strtoupper($shipping_config['mode']); ?></strong>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="zipnova_enabled" <?php echo $shipping_config['enabled'] ? 'checked' : ''; ?>>
                            <span>Habilitar integración con Zipnova</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Modo de Operación</label>
                        <select name="zipnova_mode">
                            <option value="sandbox" <?php echo $shipping_config['mode'] === 'sandbox' ? 'selected' : ''; ?>>
                                Sandbox (Pruebas)
                            </option>
                            <option value="production" <?php echo $shipping_config['mode'] === 'production' ? 'selected' : ''; ?>>
                                Producción
                            </option>
                        </select>
                        <div class="help-text">En modo Sandbox las transacciones son de prueba</div>
                    </div>
                </div>

                <!-- Credenciales -->
                <div class="card">
                    <div class="card-title">
                        🔐 Credenciales de API
                    </div>
                    <div class="card-description">
                        Credenciales de autenticación OAuth 2.0 para Zipnova
                    </div>

                    <div class="form-group">
                        <label>Client ID</label>
                        <input type="text" name="zipnova_client_id"
                               value="<?php echo htmlspecialchars($shipping_config['credentials']['client_id'] ?? ''); ?>"
                               placeholder="client_id_xxx">
                        <div class="help-text">ID de cliente proporcionado por Zipnova</div>
                    </div>

                    <div class="form-group">
                        <label>Client Secret</label>
                        <input type="password" name="zipnova_client_secret"
                               value="<?php echo htmlspecialchars($shipping_config['credentials']['client_secret'] ?? ''); ?>"
                               placeholder="secret_xxx">
                        <div class="help-text">Secreto de cliente (se guarda de forma segura)</div>
                    </div>

                    <?php if (!empty($shipping_config['credentials']['access_token'])): ?>
                        <div class="form-group">
                            <span class="status-badge status-enabled">✓ Token Activo</span>
                            <?php if (isset($shipping_config['credentials']['token_expires_at'])): ?>
                                <div class="help-text">
                                    Expira: <?php echo date('d/m/Y H:i', $shipping_config['credentials']['token_expires_at']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Configuración de Origen -->
                <div class="card">
                    <div class="card-title">
                        📍 Dirección de Origen
                    </div>
                    <div class="card-description">
                        Dirección desde donde se envían los productos
                    </div>

                    <div class="form-group">
                        <label>Nombre del Remitente</label>
                        <input type="text" name="origin_name"
                               value="<?php echo htmlspecialchars($shipping_config['origin']['name'] ?? ''); ?>"
                               placeholder="Mi Tienda">
                    </div>

                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="origin_address"
                               value="<?php echo htmlspecialchars($shipping_config['origin']['address'] ?? ''); ?>"
                               placeholder="Av. Ejemplo 123">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Ciudad</label>
                            <input type="text" name="origin_city"
                                   value="<?php echo htmlspecialchars($shipping_config['origin']['city'] ?? ''); ?>"
                                   placeholder="CABA">
                        </div>

                        <div class="form-group">
                            <label>Provincia</label>
                            <select name="origin_province">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($provincias as $prov): ?>
                                    <option value="<?php echo $prov; ?>"
                                            <?php echo ($shipping_config['origin']['province'] ?? '') === $prov ? 'selected' : ''; ?>>
                                        <?php echo $prov; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Código Postal</label>
                            <input type="text" name="origin_postal_code"
                                   value="<?php echo htmlspecialchars($shipping_config['origin']['postal_code'] ?? ''); ?>"
                                   placeholder="1425">
                        </div>

                        <div class="form-group">
                            <label>País</label>
                            <select name="origin_country">
                                <option value="AR" <?php echo ($shipping_config['origin']['country'] ?? 'AR') === 'AR' ? 'selected' : ''; ?>>
                                    Argentina
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" name="origin_phone"
                                   value="<?php echo htmlspecialchars($shipping_config['origin']['phone'] ?? ''); ?>"
                                   placeholder="+54911xxxxxxxx">
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="origin_email"
                                   value="<?php echo htmlspecialchars($shipping_config['origin']['email'] ?? ''); ?>"
                                   placeholder="envios@mitienda.com">
                        </div>
                    </div>
                </div>

                <!-- Paquete por Defecto -->
                <div class="card">
                    <div class="card-title">
                        📦 Dimensiones por Defecto
                    </div>
                    <div class="card-description">
                        Dimensiones y peso por defecto para los paquetes
                    </div>

                    <div class="form-group">
                        <label>Peso (kg)</label>
                        <input type="number" step="0.1" name="default_weight"
                               value="<?php echo $shipping_config['default_package']['weight'] ?? 1; ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Largo (cm)</label>
                            <input type="number" step="0.1" name="default_length"
                                   value="<?php echo $shipping_config['default_package']['length'] ?? 20; ?>">
                        </div>

                        <div class="form-group">
                            <label>Ancho (cm)</label>
                            <input type="number" step="0.1" name="default_width"
                                   value="<?php echo $shipping_config['default_package']['width'] ?? 15; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alto (cm)</label>
                        <input type="number" step="0.1" name="default_height"
                               value="<?php echo $shipping_config['default_package']['height'] ?? 10; ?>">
                    </div>
                </div>

                <!-- Opciones -->
                <div class="card">
                    <div class="card-title">
                        ⚙️ Opciones
                    </div>
                    <div class="card-description">
                        Configuración de comportamiento y opciones avanzadas
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auto_create_shipment"
                                   <?php echo ($shipping_config['options']['auto_create_shipment'] ?? true) ? 'checked' : ''; ?>>
                            <span>Crear envío automáticamente al confirmar orden</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label>Margen de Costo (%)</label>
                        <input type="number" step="0.1" name="shipping_cost_margin"
                               value="<?php echo $shipping_config['options']['shipping_cost_margin'] ?? 0; ?>">
                        <div class="help-text">Porcentaje adicional sobre el costo de envío (ej: 10 para 10%)</div>
                    </div>

                    <div class="form-group">
                        <label>Cache de Cotizaciones (minutos)</label>
                        <input type="number" name="cache_quotes_minutes"
                               value="<?php echo $shipping_config['options']['cache_quotes_minutes'] ?? 5; ?>">
                        <div class="help-text">Tiempo de caché para cotizaciones duplicadas</div>
                    </div>
                </div>

                <!-- Servicios Habilitados -->
                <div class="card card-full">
                    <div class="card-title">
                        🚚 Servicios de Envío Habilitados
                    </div>
                    <div class="card-description">
                        Seleccioná qué tipos de envío ofrecer a tus clientes
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="service_standard"
                                       <?php echo ($shipping_config['enabled_services']['standard'] ?? true) ? 'checked' : ''; ?>>
                                <span>Envío Estándar</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="service_express"
                                       <?php echo ($shipping_config['enabled_services']['express'] ?? true) ? 'checked' : ''; ?>>
                                <span>Envío Express</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="service_same_day"
                                   <?php echo ($shipping_config['enabled_services']['same_day'] ?? false) ? 'checked' : ''; ?>>
                            <span>Envío el Mismo Día</span>
                        </label>
                    </div>
                </div>

                <!-- Webhook -->
                <div class="card card-full">
                    <div class="card-title">
                        🔔 Webhook
                    </div>
                    <div class="card-description">
                        Configurá esta URL en el panel de Zipnova para recibir actualizaciones automáticas
                    </div>

                    <div class="form-group">
                        <label>URL del Webhook</label>
                        <div class="webhook-url"><?php echo $webhook_url; ?></div>
                        <button type="button" class="copy-btn" onclick="copyWebhookUrl()">📋 Copiar URL</button>
                    </div>

                    <div class="form-group">
                        <label>Webhook Secret (opcional)</label>
                        <input type="password" name="webhook_secret"
                               value="<?php echo htmlspecialchars($shipping_config['options']['webhook_secret'] ?? ''); ?>"
                               placeholder="secret_para_validar_webhooks">
                        <div class="help-text">Secreto para validar la autenticidad de los webhooks</div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="btn-save-container">
                    <button type="submit" name="test_connection" class="btn btn-test">
                        🔍 Probar Conexión
                    </button>
                    <button type="submit" name="save_shipping" class="btn btn-save">
                        💾 Guardar Configuración
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        function copyWebhookUrl() {
            const url = "<?php echo $webhook_url; ?>";
            navigator.clipboard.writeText(url).then(() => {
                alert('URL del webhook copiada al portapapeles');
            }).catch(err => {
                console.error('Error al copiar:', err);
            });
        }
    </script>
</body>
</html>
