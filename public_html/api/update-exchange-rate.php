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
 * API Endpoint: Update Exchange Rate
 * Auto-updates exchange rate from DolarAPI if more than 30 minutes have passed
 */

// Apply rate limiting: 5 requests per minute per IP
api_rate_limit(5, 60);

header('Content-Type: application/json');

// FORCE clear file stat cache before reading config
clearstatcache(true, APP_PATH . '/config/currency.json');

// Read currency configuration
$config = read_json(APP_PATH . '/config/currency.json');

// Check if API is enabled
if (!($config['api_enabled'] ?? false)) {
    echo json_encode([
        'success' => false,
        'message' => 'API no está habilitada',
        'needs_update' => false
    ]);
    exit;
}

// Check if update is needed (more than 30 minutes)
$needs_update = false;
$time_since_update = null;

if (isset($config['last_update'])) {
    $last_update = strtotime($config['last_update']);
    $now = time();
    $time_since_update = $now - $last_update;

    if ($time_since_update >= 1800) { // 30 minutes = 1800 seconds
        $needs_update = true;
    }
} else {
    // No previous update, needs update
    $needs_update = true;
}

// If no update needed, return current data
if (!$needs_update) {
    echo json_encode([
        'success' => true,
        'message' => 'Cotización actualizada recientemente',
        'needs_update' => false,
        'time_since_update' => $time_since_update,
        'data' => [
            'exchange_rate' => $config['exchange_rate'] ?? 0,
            'last_update' => $config['last_update'] ?? null
        ]
    ]);
    exit;
}

// Update is needed - fetch from API
$dollar_type = $config['dollar_type'] ?? 'blue';
$api_data = get_dolarapi_rate($dollar_type);

if ($api_data === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener cotización de DolarAPI',
        'needs_update' => true,
        'data' => [
            'exchange_rate' => $config['exchange_rate'] ?? 0,
            'last_update' => $config['last_update'] ?? null
        ]
    ]);
    exit;
}

// CRITICAL: Preserve ALL user configuration fields before updating
// These fields should NEVER be overwritten by the API update
$preserved_fields = [
    'primary' => $config['primary'] ?? 'ARS',
    'secondary' => $config['secondary'] ?? 'USD',
    'dollar_type' => $config['dollar_type'] ?? 'blue',
    'api_enabled' => $config['api_enabled'] ?? false,
    'manual_override' => $config['manual_override'] ?? false
];

// Update config only if no manual override is active
if (!($config['manual_override'] ?? false)) {
    $config['exchange_rate'] = round($api_data['venta'], 2);
    $config['exchange_rate_source'] = 'api';
}

// Always update API values for display
$config['api_compra'] = round($api_data['compra'], 2);
$config['api_venta'] = round($api_data['venta'], 2);
$config['api_casa'] = $api_data['casa'];
$config['api_nombre'] = $api_data['nombre'];
$config['last_update'] = $api_data['fechaActualizacion'];

// Re-apply preserved fields to ensure they are not lost
foreach ($preserved_fields as $key => $value) {
    $config[$key] = $value;
}

// Save to file
if (write_json(APP_PATH . '/config/currency.json', $config)) {
    echo json_encode([
        'success' => true,
        'message' => 'Cotización actualizada exitosamente',
        'needs_update' => false,
        'updated' => true,
        'data' => [
            'dollar_type' => $dollar_type,
            'exchange_rate' => $config['exchange_rate'],
            'compra' => $api_data['compra'],
            'venta' => $api_data['venta'],
            'nombre' => $api_data['nombre'],
            'casa' => $api_data['casa'],
            'last_update' => $api_data['fechaActualizacion']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar cotización actualizada',
        'needs_update' => true
    ]);
}
