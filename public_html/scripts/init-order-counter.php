<?php
/**
 * Script de migración: Inicializar contador de órdenes
 * Este script debe ejecutarse UNA VEZ para migrar al nuevo sistema de contadores
 */

define('APP_ENTRY_POINT', true);

// Bootstrap de la aplicación
if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
} else {
    require_once __DIR__ . '/../../app/bootstrap.php';
}

echo "<h1>Inicialización de Contador de Órdenes</h1>";
echo "<pre>";

$orders_file = APP_PATH . '/data/orders.json';
$data = read_json($orders_file);

// Verificar si ya existe el contador
if (isset($data['counters'])) {
    echo "✅ El contador ya existe:\n";
    print_r($data['counters']);
    echo "\n¿Desea reinicializarlo? (Esto puede causar duplicados)\n";
    echo "Para reinicializar, elimine manualmente el campo 'counters' del archivo orders.json\n";
    exit;
}

echo "📊 Analizando órdenes existentes...\n\n";

// Inicializar estructura de contadores
$data['counters'] = [];

// Buscar el número máximo por año
$max_by_year = [];
foreach ($data['orders'] ?? [] as $order) {
    if (preg_match('/^ORD-(\d{4})-(\d+)$/', $order['order_number'], $matches)) {
        $year = $matches[1];
        $number = intval($matches[2]);

        if (!isset($max_by_year[$year]) || $number > $max_by_year[$year]) {
            $max_by_year[$year] = $number;
        }
    }
}

// Establecer contadores
foreach ($max_by_year as $year => $max_number) {
    $data['counters'][$year] = $max_number;
    echo "Año $year: contador inicializado en $max_number\n";
}

// Guardar
if (write_json($orders_file, $data)) {
    echo "\n✅ Contador inicializado exitosamente\n";
    echo "\nContadores guardados:\n";
    print_r($data['counters']);
    echo "\nLa próxima orden del " . date('Y') . " será: ORD-" . date('Y') . "-" . sprintf('%05d', ($data['counters'][date('Y')] ?? 0) + 1) . "\n";
} else {
    echo "\n❌ Error al guardar el archivo\n";
}

echo "\n</pre>";
echo "<p><a href='" . url('/admin/') . "'>← Volver al Dashboard</a></p>";
