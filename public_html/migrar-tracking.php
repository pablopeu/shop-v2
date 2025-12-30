<?php
/**
 * Script de Migración: Tracking Numbers
 * Ejecutar una sola vez desde: /migrar-tracking.php
 *
 * ELIMINAR ESTE ARCHIVO DESPUÉS DE EJECUTARLO
 */

// Mostrar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ENTRY_POINT', true);

// Detectar entorno y cargar bootstrap
$possible_paths = [
    '/home2/uv0023/shop-v2-app/config/bootstrap.php',
    __DIR__ . '/../app/config/bootstrap.php',
    dirname(__DIR__) . '/app/config/bootstrap.php',
];

echo "<pre>DEBUG - Buscando bootstrap.php:\n";
echo "__DIR__ = " . __DIR__ . "\n";
echo "dirname(__DIR__) = " . dirname(__DIR__) . "\n\n";

$bootstrap_found = false;
foreach ($possible_paths as $path) {
    $exists = file_exists($path) ? 'SÍ' : 'NO';
    echo "[$exists] $path\n";
    if (file_exists($path)) {
        require_once $path;
        $bootstrap_found = true;
        echo "\n✅ Bootstrap cargado desde: $path\n</pre>\n\n";
        break;
    }
}

if (!$bootstrap_found) {
    echo "\n❌ No se encontró bootstrap.php en ninguna ruta\n";
    echo "\nListado de archivos en __DIR__:\n";
    print_r(scandir(__DIR__));
    echo "\nListado de archivos en dirname(__DIR__):\n";
    print_r(scandir(dirname(__DIR__)));
    die("</pre>");
}

// Solo admin puede ejecutar
require_admin();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<style>body{font-family:monospace;padding:40px;background:#1e1e1e;color:#fff}";
echo ".ok{color:#4CAF50}.skip{color:#FF9800}.err{color:#f44336}</style></head><body>";

echo "<h1>🔄 Migración de Tracking Numbers</h1>";

$shipments_dir = APP_PATH . '/data/shipments';

if (!is_dir($shipments_dir)) {
    echo "<p class='err'>❌ No hay shipments</p></body></html>";
    exit;
}

$files = glob($shipments_dir . '/*.json');
$stats = ['total' => count($files), 'ok' => 0, 'skip' => 0, 'err' => 0];

echo "<p>📦 Total: {$stats['total']}</p><hr>";

foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    $order_id = $data['order_id'] ?? null;

    if (!$order_id) { $stats['skip']++; continue; }

    // Leer tracking de forma tolerante
    $tracking = $data['tracking_number'] ?? null;
    if (!$tracking && isset($data['data'])) {
        $tracking = $data['data']['tracking_number']
            ?? $data['data']['tracking_code']
            ?? $data['data']['carrier_tracking_number']
            ?? null;
    }

    if (!$tracking) { $stats['skip']++; continue; }

    $order = get_order_by_id($order_id);
    if (!$order) { $stats['err']++; continue; }

    $current = $order['shipping']['tracking_number'] ?? null;
    if ($current === $tracking) { $stats['skip']++; continue; }

    if (update_order_shipping_info($order_id, ['tracking_number' => $tracking])) {
        echo "<p class='ok'>✅ {$order['order_number']}: {$tracking}</p>";
        $stats['ok']++;
    } else {
        echo "<p class='err'>❌ Error: {$order['order_number']}</p>";
        $stats['err']++;
    }
}

echo "<hr><h2>📊 Resumen</h2>";
echo "<p>✅ Actualizados: <strong>{$stats['ok']}</strong></p>";
echo "<p class='skip'>⏭️ Omitidos: {$stats['skip']}</p>";
echo "<p class='err'>❌ Errores: {$stats['err']}</p>";
echo "<hr><p><strong>⚠️ ELIMINAR este archivo ahora: public_html/migrar-tracking.php</strong></p>";
echo "</body></html>";
