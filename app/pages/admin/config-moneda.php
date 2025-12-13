<?php
require_admin();

// Check for message from POST-REDIRECT-GET pattern
$message = '';
if (isset($_SESSION['config_message'])) {
    $message = $_SESSION['config_message'];
    unset($_SESSION['config_message']); // Clear it so it doesn't show again
}
$error = '';

// Handle fetch from API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_api'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config = read_json(APP_PATH . '/config/currency.json');
        $dollar_type = $config['dollar_type'] ?? 'blue';
        $api_data = get_dolarapi_rate($dollar_type);
        if ($api_data) {
            $config['api_compra'] = round($api_data['compra'], 2);
            $config['api_venta'] = round($api_data['venta'], 2);
            $config['api_casa'] = $api_data['casa'];
            $config['api_nombre'] = $api_data['nombre'];
            $config['last_update'] = $api_data['fechaActualizacion'];

            if (write_json(APP_PATH . '/config/currency.json', $config)) {
                log_admin_action('currency_api_fetched', $_SESSION['username'], $api_data);

                // POST-REDIRECT-GET pattern
                $_SESSION['config_message'] = 'Tipo de cambio actualizado desde la API: $' . number_format($api_data['venta'], 2);

                header('Location: ' . url('/admin/?page=config-moneda'), true, 303);
                exit;
            } else {
                $error = 'Error al guardar datos de la API';
            }
        } else {
            $error = 'No se pudo conectar con la API de DolarAPI';
        }
    }
}

// Handle save configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config = read_json(APP_PATH . '/config/currency.json');
        $config['primary'] = sanitize_input($_POST['primary'] ?? 'ARS');
        $config['secondary'] = sanitize_input($_POST['secondary'] ?? 'USD');

        // Dollar type configuration (blue, oficial, bolsa)
        $dollar_type = sanitize_input($_POST['dollar_type'] ?? 'blue');
        if (!in_array($dollar_type, ['blue', 'oficial', 'bolsa'])) {
            $dollar_type = 'blue';
        }
        $config['dollar_type'] = $dollar_type;

        // API configuration
        $config['api_enabled'] = isset($_POST['api_enabled']);
        $config['manual_override'] = isset($_POST['manual_override']);

        // Exchange rate value
        if ($config['manual_override']) {
            // Manual override - use user input
            $config['exchange_rate'] = round(floatval($_POST['exchange_rate'] ?? 1000), 2);
            $config['exchange_rate_source'] = 'manual';
        } else if ($config['api_enabled']) {
            // API enabled without override - fetch from API
            $api_data = get_dolarapi_rate($dollar_type);
            if ($api_data) {
                $config['exchange_rate'] = round($api_data['venta'], 2);
                $config['exchange_rate_source'] = 'api';
                $config['api_compra'] = round($api_data['compra'], 2);
                $config['api_venta'] = round($api_data['venta'], 2);
                $config['api_casa'] = $api_data['casa'];
                $config['api_nombre'] = $api_data['nombre'];
                $config['last_update'] = $api_data['fechaActualizacion'];
            } else {
                // API failed, keep current value
                $error = 'Advertencia: API no disponible, se mantiene el valor actual';
            }
        } else {
            // Manual mode
            $config['exchange_rate'] = round(floatval($_POST['exchange_rate'] ?? 1000), 2);
            $config['exchange_rate_source'] = 'manual';
        }

        if (write_json(APP_PATH . '/config/currency.json', $config)) {
            log_admin_action('currency_config_updated', $_SESSION['username'], $config);

            // POST-REDIRECT-GET pattern: Store message in session and redirect
            // This forces browser to make a fresh GET request, avoiding cache issues
            $_SESSION['config_message'] = $message ?: 'Configuración guardada exitosamente';

            header('Location: ' . url('/admin/?page=config-moneda'), true, 303);
            exit;
        } else {
            $error = 'Error al guardar configuración';
        }
    }
}

// FORCE clear file stat cache before reading config
clearstatcache(true, APP_PATH . '/config/currency.json');

// CRITICAL: Prevent browser caching - force fresh data on every load
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$config = read_json(APP_PATH . '/config/currency.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = '💱 Configuración de Moneda';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Get time since last update
$last_update_text = 'Nunca';
if (isset($config['last_update'])) {
    $last_update_time = strtotime($config['last_update']);
    $diff = time() - $last_update_time;
    if ($diff < 60) {
        $last_update_text = 'Hace < 1min';
    } elseif ($diff < 3600) {
        $last_update_text = 'Hace ' . floor($diff / 60) . 'min';
    } elseif ($diff < 86400) {
        $last_update_text = 'Hace ' . floor($diff / 3600) . 'h';
    } else {
        $last_update_text = date('d/m/Y H:i', $last_update_time);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moneda y Cambio - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

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

        /* Cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
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
        }

        /* Rate Display - Compact */
        .rate-display-compact {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }

        .rate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .rate-title {
            font-weight: 600;
            font-size: 14px;
        }

        .rate-subtitle {
            opacity: 0.85;
            font-size: 12px;
        }

        .rate-values {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .rate-item {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .rate-item .label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .rate-item .value {
            font-size: 24px;
            font-weight: 700;
        }

        .update-btn-compact {
            margin-top: 15px;
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .update-btn-compact:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        /* Form Elements */
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
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

        /* Radio Groups */
        .radio-group {
            display: flex;
            gap: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
        }

        .radio-option:hover {
            border-color: #3498db;
            background: rgba(52, 152, 219, 0.05);
        }

        .radio-option input[type="radio"] {
            margin-right: 6px;
            cursor: pointer;
        }

        .radio-option input[type="radio"]:checked ~ span {
            font-weight: 600;
            color: #3498db;
        }

        .radio-option span {
            font-size: 13px;
            white-space: nowrap;
        }

        /* Checkbox Groups */
        .checkbox-group {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .checkbox-group:hover {
            border-color: #3498db;
            background: rgba(52, 152, 219, 0.05);
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-weight: 500;
            cursor: pointer;
            font-size: 14px;
        }

        /* Info Boxes */
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.6;
            color: #004085;
            margin-bottom: 20px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.6;
            color: #856404;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-save {
            width: 100%;
            padding: 14px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-save.changed {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            animation: pulse 2s infinite;
        }

        .btn-save.saved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.9; }
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        hr {
            border: none;
            border-top: 2px solid #e0e0e0;
            margin: 25px 0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 15px; }
        }

        @media (max-width: 768px) {
            .main-content { padding: 10px; }
            .grid-2 { grid-template-columns: 1fr; }
            .rate-values { grid-template-columns: 1fr; gap: 10px; }
            .radio-group { flex-direction: column; }

            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="password"],
            select,
            textarea {
                font-size: 16px !important;
            }

            .btn {
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>
    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- API Status - Compact -->
        <?php if ($config['api_enabled'] ?? false): ?>
        <div class="rate-display-compact">
            <div class="rate-header">
                <div>
                    <div class="rate-title">
                        <?= isset($config['api_nombre']) ? htmlspecialchars($config['api_nombre']) : 'Cotización Actual' ?>
                    </div>
                    <?php if (isset($config['api_casa'])): ?>
                        <div class="rate-subtitle"><?= htmlspecialchars($config['api_casa']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <div class="rate-subtitle"><?= $last_update_text ?></div>
                </div>
            </div>
            <div class="rate-values">
                <div class="rate-item">
                    <div class="label">💰 Compra</div>
                    <div class="value">$<?= number_format($config['api_compra'] ?? 0, 2) ?></div>
                </div>
                <div class="rate-item">
                    <div class="label">💵 Venta</div>
                    <div class="value">$<?= number_format($config['api_venta'] ?? 0, 2) ?></div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <button type="submit" name="fetch_api" class="update-btn-compact">
                    🔄 Actualizar Cotización desde API
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Configuration Card -->
        <div class="card">
            <div class="card-header">
                <h2>⚙️ Configuración de Moneda</h2>
            </div>

            <form method="POST" id="configForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="grid-2">
                    <div class="form-group">
                        <label for="primary">💰 Moneda Principal</label>
                        <select id="primary" name="primary">
                            <option value="ARS" <?= ($config['primary'] ?? 'ARS') === 'ARS' ? 'selected' : '' ?>>ARS - Peso Argentino</option>
                            <option value="USD" <?= ($config['primary'] ?? 'ARS') === 'USD' ? 'selected' : '' ?>>USD - Dólar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="secondary">💵 Moneda Secundaria</label>
                        <select id="secondary" name="secondary">
                            <option value="USD" <?= ($config['secondary'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD - Dólar</option>
                            <option value="ARS" <?= ($config['secondary'] ?? 'USD') === 'ARS' ? 'selected' : '' ?>>ARS - Peso Argentino</option>
                        </select>
                    </div>
                </div>

                <hr>

                <div class="form-group">
                    <label>📊 Tipo de Cotización del Dólar</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="dollar_type" value="blue" <?= ($config['dollar_type'] ?? 'blue') === 'blue' ? 'checked' : '' ?>>
                            <span>💵</span>
                            <span>Blue</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="dollar_type" value="oficial" <?= ($config['dollar_type'] ?? 'blue') === 'oficial' ? 'checked' : '' ?>>
                            <span>🏦</span>
                            <span>Oficial</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="dollar_type" value="bolsa" <?= ($config['dollar_type'] ?? 'blue') === 'bolsa' ? 'checked' : '' ?>>
                            <span>📈</span>
                            <span>Bolsa (MEP)</span>
                        </label>
                    </div>
                    <small>Tipo de cotización para calcular precios</small>
                </div>

                <hr>

                <div class="grid-2">
                    <div>
                        <label>⚙️ Configuración del Tipo de Cambio</label>

                        <div class="checkbox-group" style="margin-top: 10px;">
                            <input type="checkbox" id="api_enabled" name="api_enabled" <?= ($config['api_enabled'] ?? false) ? 'checked' : '' ?>>
                            <label for="api_enabled">🌐 Usar API de DolarAPI (automático)</label>
                        </div>
                        <small style="display: block; margin: -10px 0 15px 0; color: #6c757d;">
                            La cotización se actualiza cada 30 minutos
                        </small>

                        <!-- Warning when API is enabled without override -->
                        <?php if (($config['api_enabled'] ?? false) && !($config['manual_override'] ?? false)): ?>
                        <div class="warning-box" id="api-warning">
                            <strong>⚠️ Modo Automático Activo</strong>
                            El tipo de cambio se obtiene desde DolarAPI (actualmente $<?= number_format($config['api_venta'] ?? 0, 2) ?>).<br>
                            Para usar tu propio valor, activa "Ignorar API" abajo.
                        </div>
                        <?php endif; ?>

                        <div class="checkbox-group" id="override-group">
                            <input type="checkbox" id="manual_override" name="manual_override" <?= ($config['manual_override'] ?? false) ? 'checked' : '' ?>>
                            <label for="manual_override">Ignorar API y usar valor manual</label>
                        </div>
                        <small id="override-help" style="color: #dc3545; display: block; margin-bottom: 15px;">
                            Solo activa esto para establecer tipo de cambio fijo
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="exchange_rate">💱 Tu Tipo de Cambio</label>
                        <input
                            type="number"
                            id="exchange_rate"
                            name="exchange_rate"
                            step="0.01"
                            required
                            value="<?= $config['exchange_rate'] ?? 1000 ?>"
                            <?= (($config['api_enabled'] ?? false) && !($config['manual_override'] ?? false)) ? 'readonly style="background: #f8f9fa; cursor: not-allowed;"' : '' ?>
                        >
                        <small>
                            $1 USD = $<?= number_format($config['exchange_rate'] ?? 1000, 2) ?> ARS
                            <?php if (isset($config['exchange_rate_source'])): ?>
                                | Fuente: <?= $config['exchange_rate_source'] === 'api' ? '🌐 API' : '✍️ Manual' ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <button type="submit" name="save_config" class="btn-save" id="saveBtn">💾 Guardar Configuración</button>
            </form>
        </div>
    </div>
    <script nonce="<?= csp_nonce() ?>">
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = form.querySelectorAll('input, select');
        const apiEnabled = document.getElementById('api_enabled');
        const manualOverride = document.getElementById('manual_override');
        const exchangeRateInput = document.getElementById('exchange_rate');
        const overrideGroup = document.getElementById('override-group');

        let originalValues = {};
        let saveSuccess = <?= $message ? 'true' : 'false' ?>;

        inputs.forEach(i => {
            if (i.type === 'checkbox' || i.type === 'radio') {
                originalValues[i.name] = i.type === 'checkbox' ? i.checked : i.value;
            } else {
                originalValues[i.name] = i.value;
            }
        });

        // Update UI based on checkbox states
        function updateUI() {
            const apiWarning = document.getElementById('api-warning');
            const overrideHelp = document.getElementById('override-help');

            if (apiEnabled.checked) {
                overrideGroup.style.display = 'flex';
                if (manualOverride.checked) {
                    // Manual mode - user can edit
                    exchangeRateInput.readOnly = false;
                    exchangeRateInput.style.background = '';
                    exchangeRateInput.style.cursor = '';
                    if (apiWarning) apiWarning.style.display = 'none';
                    if (overrideHelp) {
                        overrideHelp.style.color = '#28a745';
                        overrideHelp.innerHTML = 'Modo manual activado. Establece tu propio tipo de cambio';
                    }
                } else {
                    // API mode - user cannot edit
                    exchangeRateInput.readOnly = true;
                    exchangeRateInput.style.background = '#f8f9fa';
                    exchangeRateInput.style.cursor = 'not-allowed';
                    if (apiWarning) apiWarning.style.display = 'block';
                    if (overrideHelp) {
                        overrideHelp.style.color = '#dc3545';
                        overrideHelp.innerHTML = 'Solo activa esto para establecer tipo de cambio fijo';
                    }
                }
            } else {
                // API disabled - user can always edit
                overrideGroup.style.display = 'none';
                exchangeRateInput.readOnly = false;
                exchangeRateInput.style.background = '';
                exchangeRateInput.style.cursor = '';
                if (apiWarning) apiWarning.style.display = 'none';
            }
        }

        // Check for changes
        function checkChanges() {
            let changed = Array.from(inputs).some(inp => {
                if (inp.type === 'checkbox') {
                    return inp.checked !== originalValues[inp.name];
                } else if (inp.type === 'radio') {
                    const selectedRadio = form.querySelector(`input[name="${inp.name}"]:checked`);
                    return selectedRadio && selectedRadio.value !== originalValues[inp.name];
                }
                return inp.value !== originalValues[inp.name];
            });
            saveBtn.classList.toggle('changed', changed);
            saveBtn.classList.toggle('saved', !changed && saveSuccess);
        }

        apiEnabled.addEventListener('change', updateUI);
        manualOverride.addEventListener('change', updateUI);
        updateUI();

        inputs.forEach(i => {
            i.addEventListener('input', checkChanges);
            i.addEventListener('change', checkChanges);
        });

        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => saveBtn.classList.remove('saved'), 3000);
        }
    </script>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>
</body>
</html>
