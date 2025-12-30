#!/usr/bin/env php
<?php
/**
 * Script de Migración: Tracking Numbers de Zipnova
 *
 * Migra los tracking_number de los shipments locales a las órdenes correspondientes.
 * Uso: php app/scripts/migrar-tracking-numbers.php
 */

// Determinar ruta al bootstrap
$bootstrap_path = __DIR__ . '/../config/bootstrap.php';

if (!file_exists($bootstrap_path)) {
    die("ERROR: No se encontró bootstrap.php en {$bootstrap_path}\n");
}

require_once $bootstrap_path;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔄 MIGRACIÓN DE TRACKING NUMBERS DE ZIPNOVA                ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$shipments_dir = APP_PATH . '/data/shipments';

// Verificar que exista el directorio
if (!is_dir($shipments_dir)) {
    echo "❌ No hay shipments guardados localmente\n";
    echo "   Directorio: {$shipments_dir}\n\n";
    exit(1);
}

// Obtener todos los archivos de shipments
$shipment_files = glob($shipments_dir . '/*.json');

if (empty($shipment_files)) {
    echo "⚠️  No se encontraron archivos de shipments\n\n";
    exit(0);
}

echo "📦 Total de shipments encontrados: " . count($shipment_files) . "\n";
echo "🔍 Procesando...\n\n";

$stats = [
    'total' => count($shipment_files),
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0
];

foreach ($shipment_files as $file) {
    $shipment_id = basename($file, '.json');
    $shipment_data = json_decode(file_get_contents($file), true);

    if (!$shipment_data) {
        echo "   ❌ Error al leer: {$shipment_id}\n";
        $stats['errors']++;
        continue;
    }

    // Obtener order_id del shipment
    $order_id = $shipment_data['order_id'] ?? null;
    if (!$order_id) {
        echo "   ⚠️  Shipment sin order_id: {$shipment_id}\n";
        $stats['skipped']++;
        continue;
    }

    // Leer tracking_number de forma tolerante
    $tracking_number = null;

    // 1. Intentar desde el campo directo del shipment
    $tracking_number = $shipment_data['tracking_number'] ?? null;

    // 2. Intentar desde data.tracking_number (respuesta completa de Zipnova)
    if (!$tracking_number && isset($shipment_data['data'])) {
        $tracking_number = $shipment_data['data']['tracking_number']
            ?? $shipment_data['data']['tracking_code']
            ?? $shipment_data['data']['carrier_tracking_number']
            ?? null;
    }

    if (!$tracking_number) {
        $order_number = $shipment_data['order_number'] ?? $order_id;
        echo "   ⚠️  Sin tracking: Orden {$order_number}\n";
        $stats['skipped']++;
        continue;
    }

    // Obtener la orden
    $order = get_order_by_id($order_id);
    if (!$order) {
        echo "   ❌ Orden no encontrada: {$order_id}\n";
        $stats['errors']++;
        continue;
    }

    $order_number = $order['order_number'] ?? $order_id;

    // Verificar si la orden ya tiene tracking_number
    $current_tracking = $order['shipping']['tracking_number'] ?? $order['tracking_number'] ?? null;

    if ($current_tracking === $tracking_number) {
        echo "   ⏭️  Ya tiene tracking: Orden {$order_number}\n";
        $stats['skipped']++;
        continue;
    }

    // Actualizar la orden con el tracking_number
    $updated = update_order_shipping_info($order_id, [
        'tracking_number' => $tracking_number
    ]);

    if ($updated) {
        echo "   ✅ ACTUALIZADO: Orden {$order_number} → {$tracking_number}\n";
        $stats['updated']++;
    } else {
        echo "   ❌ Error al actualizar: Orden {$order_number}\n";
        $stats['errors']++;
    }
}

// Mostrar resumen
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📊 RESUMEN DE LA MIGRACIÓN                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "  📦 Total de shipments:     {$stats['total']}\n";
echo "  ✅ Actualizados:           {$stats['updated']}\n";
echo "  ⏭️  Omitidos:              {$stats['skipped']}\n";
echo "  ❌ Errores:                {$stats['errors']}\n";
echo "\n";

if ($stats['updated'] > 0) {
    echo "✨ Migración completada exitosamente\n";
    echo "   Los tracking numbers ahora aparecen en el CRM\n\n";
    exit(0);
} else {
    echo "⚠️  No se actualizó ninguna orden\n";
    echo "   Todas las órdenes ya tienen tracking o no hay tracking disponible\n\n";
    exit(0);
}
