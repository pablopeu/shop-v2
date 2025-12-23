<?php
/**
 * Test directo del endpoint de shipping (sin HTTP)
 * Ejecutar: php test-shipping-direct.php
 */

echo "=== DIAGNÓSTICO SHIPPING ===\n\n";

// 1. Verificar estructura de archivos
echo "1. Verificando archivos...\n";
$files_to_check = [
    'public_html/api/index.php',
    'app/pages/api/shipping.php',
    'app/includes/carriers.php',
    'app/includes/api_helpers.php',
    'app/config/shipping.json'
];

foreach ($files_to_check as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    echo "   " . ($exists ? '✓' : '✗') . " $file\n";
}

// 2. Verificar configuración de Zipnova
echo "\n2. Verificando configuración de Zipnova...\n";
$config_file = __DIR__ . '/app/config/shipping.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);

    if (isset($config['carriers']['ZNVA'])) {
        $znva = $config['carriers']['ZNVA'];
        echo "   Enabled: " . ($znva['enabled'] ? 'SÍ' : 'NO') . "\n";
        echo "   Mode: " . ($znva['mode'] ?? 'N/A') . "\n";
        echo "   Account ID: " . (empty($znva['credentials']['account_id']) ? 'NO CONFIGURADO' : 'Configurado') . "\n";
        echo "   Client ID: " . (empty($znva['credentials']['client_id']) ? 'NO CONFIGURADO' : 'Configurado') . "\n";
        echo "   Client Secret: " . (empty($znva['credentials']['client_secret']) ? 'NO CONFIGURADO' : 'Configurado') . "\n";
        echo "   Origin ID: " . (empty($znva['origin']['origin_id']) ? 'NO CONFIGURADO' : 'Configurado') . "\n";
    } else {
        echo "   ✗ No se encontró configuración de ZNVA\n";
    }
} else {
    echo "   ✗ Archivo de configuración no existe\n";
}

// 3. Verificar registro en API
echo "\n3. Verificando registro en API router...\n";
$api_index = file_get_contents(__DIR__ . '/public_html/api/index.php');
if (strpos($api_index, "'shipping'") !== false) {
    echo "   ✓ Endpoint 'shipping' encontrado en API router\n";
} else {
    echo "   ✗ Endpoint 'shipping' NO encontrado en API router\n";
}

// 4. Verificar logs directory
echo "\n4. Verificando directorio de logs...\n";
$logs_dir = __DIR__ . '/logs/zipnova';
if (is_dir($logs_dir)) {
    echo "   ✓ Directorio existe: $logs_dir\n";
    $log_files = glob($logs_dir . '/*.log');
    echo "   Archivos de log encontrados: " . count($log_files) . "\n";
    if (count($log_files) > 0) {
        $latest = array_pop($log_files);
        echo "   Último log: " . basename($latest) . "\n";
    }
} else {
    echo "   ℹ Directorio no existe (se creará automáticamente)\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
