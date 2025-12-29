<?php
/**
 * Admin - Logistics Configuration (Multi-Carrier)
 * Configuración de logística multi-carrier
 */

require_admin();

$message = '';
$error = '';

// Test connection
$test_response_json = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        require_once APP_PATH . '/includes/carriers.php';
        $test_result = zipnova_test_connection();

        // Guardar respuesta completa para mostrar en modal
        $test_response_json = json_encode($test_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

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
            // Inicializar config vacío si no existe
            $config = [
                'tag' => 'ZNVA',
                'name' => 'Zipnova',
                'type' => 'zipnova',
                'enabled' => false,
                'mode' => 'production',
                'credentials' => [
                    'account_id' => '',
                    'client_id' => '',
                    'client_secret' => ''
                ],
                'api_urls' => [
                    'sandbox' => 'https://api.zipnova.com.ar/v2',
                    'production' => 'https://api.zipnova.com.ar/v2'
                ],
                'origin' => [
                    'origin_id' => '',
                    'name' => '',
                    'address' => '',
                    'city' => '',
                    'province' => '',
                    'postal_code' => '',
                    'country' => 'AR',
                    'phone' => '',
                    'email' => ''
                ],
                'default_package' => [
                    'weight' => 1,
                    'length' => 20,
                    'width' => 15,
                    'height' => 10
                ],
                'options' => [
                    'webhook_secret' => '',
                    'auto_create_shipment' => true,
                    'shipping_cost_margin' => 0,
                    'cache_quotes_minutes' => 5,
                    'timeout_seconds' => 30,
                    'max_retries' => 3
                ],
                'enabled_services' => [
                    'standard' => true,
                    'express' => true,
                    'same_day' => false
                ]
            ];
        }

        // Update carrier metadata
        $config['tag'] = strtoupper(sanitize_input($_POST['carrier_tag'] ?? 'ZNVA'));
        $config['name'] = sanitize_input($_POST['carrier_name'] ?? 'Zipnova');
        $config['type'] = sanitize_input($_POST['carrier_type'] ?? 'zipnova');

        // Update configuration
        $config['enabled'] = isset($_POST['zipnova_enabled']);
        $config['mode'] = $_POST['zipnova_mode'] ?? 'production';

        // Preservar api_urls si no están en el config
        if (!isset($config['api_urls'])) {
            $config['api_urls'] = [
                'sandbox' => 'https://api.zipnova.com.ar/v2',
                'production' => 'https://api.zipnova.com.ar/v2'
            ];
        }

        // Preservar estructura de credentials
        if (!isset($config['credentials'])) {
            $config['credentials'] = [];
        }

        // Credentials - siempre actualizar, incluso si están vacíos
        $config['credentials']['account_id'] = sanitize_input($_POST['zipnova_account_id'] ?? '');
        $config['credentials']['client_id'] = sanitize_input($_POST['zipnova_client_id'] ?? '');
        $config['credentials']['client_secret'] = sanitize_input($_POST['zipnova_client_secret'] ?? '');

        // Origin configuration
        $config['origin'] = [
            'origin_id' => sanitize_input($_POST['origin_id'] ?? ''),
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

        // Preservar estructura de options
        if (!isset($config['options'])) {
            $config['options'] = [];
        }

        // Options
        $config['options']['auto_create_shipment'] = isset($_POST['auto_create_shipment']);
        $config['options']['send_customer_email'] = isset($_POST['send_customer_email']);
        $config['options']['shipping_cost_margin'] = (float)($_POST['shipping_cost_margin'] ?? 0);
        $config['options']['cache_quotes_minutes'] = (int)($_POST['cache_quotes_minutes'] ?? 5);
        $config['options']['webhook_secret'] = sanitize_input($_POST['webhook_secret'] ?? '');

        // Preservar valores que no están en el formulario
        $config['options']['timeout_seconds'] = $config['options']['timeout_seconds'] ?? 30;
        $config['options']['max_retries'] = $config['options']['max_retries'] ?? 3;

        // Enabled services
        $config['enabled_services'] = [
            'standard' => isset($_POST['service_standard']),
            'express' => isset($_POST['service_express']),
            'same_day' => isset($_POST['service_same_day'])
        ];

        // Dispatch method configuration (pickup vs dropoff)
        $preferred_dispatch = sanitize_input($_POST['dispatch_preferred'] ?? 'pickup');
        $config['dispatch_method'] = [
            'preferred' => $preferred_dispatch,
            'description' => 'Método preferido para entregar paquetes al carrier',
            'options' => [
                'pickup' => [
                    'enabled' => true,
                    'label' => 'Recolección a domicilio',
                    'logistic_types' => ['carrier_pickup', 'home_pickup', 'pickup']
                ],
                'dropoff' => [
                    'enabled' => ($preferred_dispatch === 'dropoff'),
                    'label' => 'Entrega en punto Zipnova',
                    'logistic_types' => ['xd_dropoff', 'carrier_dropoff', 'seller_dropoff', 'dropoff'],
                    'point_id' => sanitize_input($_POST['dropoff_point_id'] ?? ''),
                    'point_name' => sanitize_input($_POST['dropoff_point_name'] ?? ''),
                    'point_address' => sanitize_input($_POST['dropoff_point_address'] ?? '')
                ]
            ]
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
            <strong>🚚 Configuración de Logística:</strong> Obtené tus credenciales en el panel de Zipnova (Configuración → Integraciones), configurá tus datos de origen, probá la conexión y configurá el webhook con la URL proporcionada abajo.
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
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="zipnova_enabled" <?php echo $shipping_config['enabled'] ? 'checked' : ''; ?>>
                            <span>Habilitar integración con Zipnova</span>
                        </label>
                        <div class="help-text">Usa la API de producción de Zipnova (https://api.zipnova.com.ar/v2/)</div>
                    </div>
                    <input type="hidden" name="zipnova_mode" value="production">
                </div>

                <!-- Credenciales -->
                <div class="card">
                    <div class="card-title">
                        🔐 Credenciales de API
                    </div>
                    <div class="card-description">
                        Para generar tus credenciales ingresa a: <strong>Configuración → Integraciones → Gestionar credenciales y webhooks</strong> en tu cuenta de Zipnova.
                        <br><br>
                        Las requests utilizan <strong>autenticación básica HTTP</strong>.
                    </div>

                    <div class="form-group">
                        <label>Account ID</label>
                        <input type="text" name="zipnova_account_id"
                               value="<?php echo htmlspecialchars($shipping_config['credentials']['account_id'] ?? ''); ?>"
                               placeholder="3355">
                        <div class="help-text">
                            ID de tu cuenta en Zipnova (lo encontrás en tu panel de Zipnova)
                        </div>
                    </div>

                    <div class="form-group">
                        <label>API Token</label>
                        <input type="text" name="zipnova_client_id"
                               value="<?php echo htmlspecialchars($shipping_config['credentials']['client_id'] ?? ''); ?>"
                               placeholder="token_xxxxxxxxxx">
                        <div class="help-text">
                            <strong>Usuario de autenticación básica:</strong> Copia el API Token generado en Zipnova
                        </div>
                    </div>

                    <div class="form-group">
                        <label>API Secret</label>
                        <input type="password" name="zipnova_client_secret"
                               value="<?php echo htmlspecialchars($shipping_config['credentials']['client_secret'] ?? ''); ?>"
                               placeholder="secret_xxxxxxxxxx">
                        <div class="help-text">
                            <strong>Contraseña de autenticación básica:</strong> Copia el API Secret generado en Zipnova (se guarda de forma segura)
                        </div>
                    </div>
                </div>

                <!-- Configuración de Origen -->
                <div class="card">
                    <div class="card-title">
                        📍 Dirección de Origen
                    </div>
                    <div class="card-description">
                        Dirección desde donde se envían los productos. Creá primero la ubicación en tu panel de Zipnova.
                    </div>

                    <div class="form-group">
                        <label>Origin ID</label>
                        <input type="text" name="origin_id"
                               value="<?php echo htmlspecialchars($shipping_config['origin']['origin_id'] ?? ''); ?>"
                               placeholder="9323"
                               required>
                        <div class="help-text">
                            ID de la ubicación de origen configurada en Zipnova (lo encontrás en Configuración → Ubicaciones)
                        </div>
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

                <!-- Método de Despacho -->
                <div class="card">
                    <div class="card-title">
                        🚚 Método de Despacho
                    </div>
                    <div class="card-description">
                        Cómo entregas los paquetes al carrier para su distribución
                    </div>

                    <div class="form-group">
                        <label>Método Preferido</label>
                        <select name="dispatch_preferred" id="dispatch_preferred" data-onchange="toggleDropoffFields">
                            <option value="pickup" <?php echo ($shipping_config['dispatch_method']['preferred'] ?? 'pickup') === 'pickup' ? 'selected' : ''; ?>>
                                📍 Recolección a domicilio (Pickup) - El carrier recoge en mi dirección
                            </option>
                            <option value="dropoff" <?php echo ($shipping_config['dispatch_method']['preferred'] ?? 'pickup') === 'dropoff' ? 'selected' : ''; ?>>
                                🏪 Entrega en punto Zipnova (Drop-off) - Yo llevo el paquete a un punto
                            </option>
                        </select>
                        <div class="help-text">
                            Esta configuración filtrará las cotizaciones para mostrar solo servicios compatibles con tu método preferido.
                            El cliente NO verá esta configuración - es transparente para él.
                        </div>
                    </div>

                    <div id="dropoff-fields" style="display: <?php echo ($shipping_config['dispatch_method']['preferred'] ?? 'pickup') === 'dropoff' ? 'block' : 'none'; ?>;">
                        <div class="form-group">
                            <label>Point ID del Punto de Entrega (Opcional)</label>
                            <input type="text" name="dropoff_point_id"
                                   value="<?php echo htmlspecialchars($shipping_config['dispatch_method']['options']['dropoff']['point_id'] ?? ''); ?>"
                                   placeholder="Dejar vacío para cualquier punto">
                            <div class="help-text">
                                <strong>Opcional:</strong> Si lo dejas vacío, podrás entregar en <strong>cualquier punto de la red Zipnova/Correo Argentino</strong>.<br>
                                Si especificas un point_id, solo podrás usar ese punto específico (útil si hay un punto cerca de ti).<br>
                                <em>Los point_id disponibles aparecen en las cotizaciones que tienen "origin_points".</em>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nombre del Punto (opcional)</label>
                            <input type="text" name="dropoff_point_name"
                                   value="<?php echo htmlspecialchars($shipping_config['dispatch_method']['options']['dropoff']['point_name'] ?? ''); ?>"
                                   placeholder="Zipnova Punto Palermo">
                        </div>

                        <div class="form-group">
                            <label>Dirección del Punto (opcional)</label>
                            <input type="text" name="dropoff_point_address"
                                   value="<?php echo htmlspecialchars($shipping_config['dispatch_method']['options']['dropoff']['point_address'] ?? ''); ?>"
                                   placeholder="Av. Santa Fe 1234, CABA">
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
                        <label class="checkbox-label">
                            <input type="checkbox" name="send_customer_email"
                                   <?php echo ($shipping_config['options']['send_customer_email'] ?? false) ? 'checked' : ''; ?>>
                            <span>Enviar email del comprador al carrier (Zipnova)</span>
                        </label>
                        <div class="help-text">Si está habilitado, el carrier recibirá el email del comprador al generar la etiqueta. Útil para notificaciones de seguimiento.</div>
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

    <!-- Modal para mostrar respuesta de Zipnova -->
    <div id="responseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 8px; padding: 24px; max-width: 800px; max-height: 80vh; width: 90%; display: flex; flex-direction: column;">
            <h3 style="margin: 0 0 16px 0; font-size: 18px; color: #333;">📋 Respuesta de Zipnova API</h3>

            <div style="flex: 1; overflow: auto; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 16px; margin-bottom: 16px;">
                <pre id="responseContent" style="margin: 0; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; color: #333; white-space: pre-wrap; word-wrap: break-word;"></pre>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button id="copyResponseBtn" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    📋 Copiar al portapapeles
                </button>
                <button id="closeModalBtn" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    ✕ Cerrar
                </button>
            </div>
        </div>
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

        function showResponseModal(jsonResponse) {
            const modal = document.getElementById('responseModal');
            const content = document.getElementById('responseContent');
            content.textContent = jsonResponse;
            modal.style.display = 'flex';
        }

        function closeModal() {
            const modal = document.getElementById('responseModal');
            modal.style.display = 'none';
        }

        function copyResponse() {
            const content = document.getElementById('responseContent').textContent;
            const btn = document.getElementById('copyResponseBtn');
            const originalText = btn.innerHTML;
            const originalBg = btn.style.background;

            navigator.clipboard.writeText(content).then(() => {
                // Cambiar a estado "copiado"
                btn.innerHTML = '✓ Copiado';
                btn.style.background = '#218838';

                // Restaurar después de 2 segundos
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = originalBg;
                }, 2000);
            }).catch(err => {
                console.error('Error al copiar:', err);
                // Mostrar error en el botón
                btn.innerHTML = '✗ Error';
                btn.style.background = '#dc3545';

                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = originalBg;
                }, 2000);
            });
        }

        // Inicializar event listeners cuando el DOM esté listo
        window.addEventListener('DOMContentLoaded', function() {
            // Event listener para botón de copiar
            const copyBtn = document.getElementById('copyResponseBtn');
            if (copyBtn) {
                copyBtn.addEventListener('click', copyResponse);
            }

            // Event listener para botón de cerrar
            const closeBtn = document.getElementById('closeModalBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }

            // Cerrar modal al hacer click fuera del contenido
            const modal = document.getElementById('responseModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }

            // Mostrar modal si hay respuesta de test
            <?php if ($test_response_json !== null): ?>
            showResponseModal(<?php echo json_encode($test_response_json); ?>);
            <?php endif; ?>
        });

        /**
         * Toggle dropoff fields visibility based on dispatch method selection
         */
        function toggleDropoffFields(event, element, params) {
            const selectValue = element.value;
            const dropoffFields = document.getElementById('dropoff-fields');

            if (dropoffFields) {
                dropoffFields.style.display = (selectValue === 'dropoff') ? 'block' : 'none';
            }
        }

        // Export for event delegation
        window.toggleDropoffFields = toggleDropoffFields;
    </script>
</body>
</html>
