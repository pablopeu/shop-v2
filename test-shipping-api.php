<?php
/**
 * Script de prueba para el endpoint de shipping
 * Ejecutar desde línea de comandos: php test-shipping-api.php
 */

// Simular llamada al endpoint
$url = 'http://localhost/shopv2/api/?endpoint=shipping&action=quotes';

$data = [
    'destination' => [
        'postal_code' => '5000',
        'city' => 'Córdoba',
        'province' => 'Córdoba',
        'country' => 'AR'
    ],
    'weight' => 500,
    'declared_value' => 1000
];

echo "=== TEST SHIPPING API ===\n";
echo "URL: $url\n";
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== RESPONSE ===\n";
echo "HTTP Code: $http_code\n";
if ($error) {
    echo "cURL Error: $error\n";
}
echo "Response: $response\n";

// Try to decode JSON
$decoded = json_decode($response, true);
if ($decoded) {
    echo "\n=== DECODED RESPONSE ===\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
}
