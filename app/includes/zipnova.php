<?php
/**
 * Zipnova Shipping Integration
 *
 * Funciones para integración con la API de Zipnova
 * Documentación: /zipnova/
 */

if (!defined('BASE_PATH')) {
    die('Acceso directo no permitido');
}

/**
 * Carga la configuración de Zipnova
 */
function zipnova_get_config() {
    $config_file = BASE_PATH . '/app/config/shipping.json';
    if (!file_exists($config_file)) {
        return null;
    }
    $config = json_decode(file_get_contents($config_file), true);
    return $config['zipnova'] ?? null;
}

/**
 * Guarda la configuración de Zipnova
 */
function zipnova_save_config($zipnova_config) {
    $config_file = BASE_PATH . '/app/config/shipping.json';
    $config = json_decode(file_get_contents($config_file), true);
    $config['zipnova'] = $zipnova_config;
    return file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Obtiene la URL base de la API según el modo (sandbox/production)
 */
function zipnova_get_api_url() {
    $config = zipnova_get_config();
    if (!$config) return null;

    $mode = $config['mode'] ?? 'sandbox';
    return $config['api_urls'][$mode] ?? $config['api_urls']['sandbox'];
}

/**
 * Realiza una petición HTTP a la API de Zipnova
 */
function zipnova_api_request($endpoint, $method = 'GET', $data = null, $use_auth = true) {
    $config = zipnova_get_config();
    if (!$config) {
        return ['success' => false, 'error' => 'Configuración de Zipnova no encontrada'];
    }

    $base_url = zipnova_get_api_url();
    $url = $base_url . $endpoint;

    $headers = ['Content-Type: application/json'];

    if ($use_auth) {
        // Verificar si el token está disponible y no ha expirado
        if (empty($config['credentials']['access_token'])) {
            return ['success' => false, 'error' => 'No hay token de acceso configurado'];
        }

        // Verificar expiración del token
        if (isset($config['credentials']['token_expires_at']) &&
            $config['credentials']['token_expires_at'] !== null &&
            time() >= $config['credentials']['token_expires_at']) {
            // Intentar refrescar el token
            $refresh_result = zipnova_refresh_token();
            if (!$refresh_result['success']) {
                return ['success' => false, 'error' => 'Token expirado y no se pudo renovar'];
            }
            $config = zipnova_get_config(); // Recargar config con nuevo token
        }

        $headers[] = 'Authorization: Bearer ' . $config['credentials']['access_token'];
    }

    $timeout = $config['options']['timeout_seconds'] ?? 30;
    $max_retries = $config['options']['max_retries'] ?? 3;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    // Retry logic
    $retries = 0;
    $response = false;
    $http_code = 0;

    while ($retries < $max_retries) {
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response !== false && $http_code < 500) {
            break; // Éxito o error del cliente (no reintentar)
        }

        $retries++;
        if ($retries < $max_retries) {
            sleep(pow(2, $retries)); // Exponential backoff
        }
    }

    $curl_error = curl_error($ch);
    curl_close($ch);

    // Log de la request
    zipnova_log('API Request', [
        'method' => $method,
        'endpoint' => $endpoint,
        'http_code' => $http_code,
        'retries' => $retries,
        'success' => $response !== false
    ]);

    if ($response === false) {
        return ['success' => false, 'error' => 'Error de conexión: ' . $curl_error];
    }

    $result = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'data' => $result];
    } else {
        return [
            'success' => false,
            'error' => $result['message'] ?? 'Error desconocido',
            'http_code' => $http_code,
            'details' => $result
        ];
    }
}

/**
 * Autenticación OAuth 2.0
 * Obtiene un access token usando client credentials
 */
function zipnova_authenticate() {
    $config = zipnova_get_config();
    if (!$config) {
        return ['success' => false, 'error' => 'Configuración no encontrada'];
    }

    $client_id = $config['credentials']['client_id'] ?? '';
    $client_secret = $config['credentials']['client_secret'] ?? '';

    if (empty($client_id) || empty($client_secret)) {
        return ['success' => false, 'error' => 'Credenciales no configuradas'];
    }

    // Realizar request de autenticación (sin usar zipnova_api_request)
    $base_url = zipnova_get_api_url();
    $url = $base_url . '/oauth/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code !== 200) {
        zipnova_log('Authentication Failed', ['http_code' => $http_code]);
        return ['success' => false, 'error' => 'Error de autenticación'];
    }

    $result = json_decode($response, true);

    if (isset($result['access_token'])) {
        // Actualizar configuración con el nuevo token
        $config['credentials']['access_token'] = $result['access_token'];
        $config['credentials']['refresh_token'] = $result['refresh_token'] ?? '';
        $config['credentials']['token_expires_at'] = time() + ($result['expires_in'] ?? 3600);

        zipnova_save_config($config);
        zipnova_log('Authentication Success', ['expires_in' => $result['expires_in']]);

        return ['success' => true, 'data' => $result];
    }

    return ['success' => false, 'error' => 'Token no recibido'];
}

/**
 * Refresca el token de acceso usando el refresh token
 */
function zipnova_refresh_token() {
    $config = zipnova_get_config();
    if (!$config) {
        return ['success' => false, 'error' => 'Configuración no encontrada'];
    }

    $refresh_token = $config['credentials']['refresh_token'] ?? '';

    if (empty($refresh_token)) {
        // Si no hay refresh token, intentar autenticación completa
        return zipnova_authenticate();
    }

    $base_url = zipnova_get_api_url();
    $url = $base_url . '/oauth/token';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code !== 200) {
        // Si falla el refresh, intentar autenticación completa
        zipnova_log('Token Refresh Failed', ['http_code' => $http_code]);
        return zipnova_authenticate();
    }

    $result = json_decode($response, true);

    if (isset($result['access_token'])) {
        $config['credentials']['access_token'] = $result['access_token'];
        $config['credentials']['refresh_token'] = $result['refresh_token'] ?? $refresh_token;
        $config['credentials']['token_expires_at'] = time() + ($result['expires_in'] ?? 3600);

        zipnova_save_config($config);
        zipnova_log('Token Refreshed', ['expires_in' => $result['expires_in']]);

        return ['success' => true, 'data' => $result];
    }

    return ['success' => false, 'error' => 'No se pudo refrescar el token'];
}

/**
 * Cotiza envíos
 *
 * @param array $origin Datos de origen (postal_code, city, province, country)
 * @param array $destination Datos de destino
 * @param array $packages Array de paquetes con weight, length, width, height, declared_value
 * @return array Resultado con cotizaciones disponibles
 */
function zipnova_get_quotes($origin, $destination, $packages) {
    $config = zipnova_get_config();

    if (!$config || !$config['enabled']) {
        return ['success' => false, 'error' => 'Zipnova no está habilitado'];
    }

    // Usar origen por defecto si no se proporciona
    if (empty($origin)) {
        $origin = [
            'postal_code' => $config['origin']['postal_code'],
            'city' => $config['origin']['city'],
            'province' => $config['origin']['province'],
            'country' => $config['origin']['country']
        ];
    }

    // Aplicar dimensiones por defecto si no se proporcionan
    foreach ($packages as &$package) {
        if (!isset($package['weight'])) {
            $package['weight'] = $config['default_package']['weight'];
        }
        if (!isset($package['length'])) {
            $package['length'] = $config['default_package']['length'];
        }
        if (!isset($package['width'])) {
            $package['width'] = $config['default_package']['width'];
        }
        if (!isset($package['height'])) {
            $package['height'] = $config['default_package']['height'];
        }
    }

    $request_data = [
        'origin' => $origin,
        'destination' => $destination,
        'packages' => $packages
    ];

    $result = zipnova_api_request('/shipments/quotes', 'POST', $request_data);

    if ($result['success']) {
        // Aplicar margen de costo si está configurado
        $margin = $config['options']['shipping_cost_margin'] ?? 0;
        if ($margin > 0 && isset($result['data']['quotes'])) {
            foreach ($result['data']['quotes'] as &$quote) {
                if (isset($quote['cost'])) {
                    $quote['original_cost'] = $quote['cost'];
                    $quote['cost'] = $quote['cost'] * (1 + $margin / 100);
                }
            }
        }
    }

    zipnova_log('Quote Request', [
        'success' => $result['success'],
        'destination' => $destination['postal_code'] ?? 'N/A',
        'packages_count' => count($packages)
    ]);

    return $result;
}

/**
 * Crea un envío en Zipnova
 *
 * @param array $shipment_data Datos del envío completos
 * @return array Resultado con ID del envío creado
 */
function zipnova_create_shipment($shipment_data) {
    $config = zipnova_get_config();

    if (!$config || !$config['enabled']) {
        return ['success' => false, 'error' => 'Zipnova no está habilitado'];
    }

    // Usar origen por defecto si no se proporciona
    if (!isset($shipment_data['origin'])) {
        $shipment_data['origin'] = [
            'name' => $config['origin']['name'],
            'address' => $config['origin']['address'],
            'city' => $config['origin']['city'],
            'province' => $config['origin']['province'],
            'postal_code' => $config['origin']['postal_code'],
            'country' => $config['origin']['country'],
            'phone' => $config['origin']['phone'],
            'email' => $config['origin']['email']
        ];
    }

    $result = zipnova_api_request('/shipments', 'POST', $shipment_data);

    if ($result['success']) {
        // Guardar el envío localmente
        $shipment_id = $result['data']['id'] ?? uniqid('shp_');
        zipnova_save_shipment($shipment_id, [
            'zipnova_id' => $shipment_id,
            'reference' => $shipment_data['reference'] ?? '',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'data' => $result['data']
        ]);
    }

    zipnova_log('Shipment Created', [
        'success' => $result['success'],
        'reference' => $shipment_data['reference'] ?? 'N/A'
    ]);

    return $result;
}

/**
 * Consulta el estado de un envío
 *
 * @param string $shipment_id ID del envío en Zipnova
 * @return array Resultado con datos del envío
 */
function zipnova_get_shipment($shipment_id) {
    $result = zipnova_api_request('/shipments/' . $shipment_id, 'GET');

    if ($result['success']) {
        // Actualizar datos locales
        zipnova_update_shipment_status($shipment_id, $result['data']);
    }

    zipnova_log('Shipment Status Check', [
        'shipment_id' => $shipment_id,
        'success' => $result['success']
    ]);

    return $result;
}

/**
 * Cancela un envío
 *
 * @param string $shipment_id ID del envío en Zipnova
 * @return array Resultado de la cancelación
 */
function zipnova_cancel_shipment($shipment_id) {
    $result = zipnova_api_request('/shipments/' . $shipment_id . '/cancel', 'POST');

    if ($result['success']) {
        zipnova_update_shipment_status($shipment_id, ['status' => 'cancelled']);
    }

    zipnova_log('Shipment Cancelled', [
        'shipment_id' => $shipment_id,
        'success' => $result['success']
    ]);

    return $result;
}

/**
 * Verifica la firma de un webhook
 *
 * @param string $payload JSON payload del webhook
 * @param string $signature Firma recibida en header
 * @return bool True si la firma es válida
 */
function zipnova_webhook_verify($payload, $signature) {
    $config = zipnova_get_config();
    if (!$config) return false;

    $secret = $config['options']['webhook_secret'] ?? '';
    if (empty($secret)) return false;

    $calculated_signature = hash_hmac('sha256', $payload, $secret);

    return hash_equals($calculated_signature, $signature);
}

/**
 * Guarda un envío localmente
 */
function zipnova_save_shipment($shipment_id, $data) {
    $shipments_dir = BASE_PATH . '/data/shipments';
    if (!is_dir($shipments_dir)) {
        mkdir($shipments_dir, 0755, true);
    }

    $file = $shipments_dir . '/' . $shipment_id . '.json';
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Carga un envío local
 */
function zipnova_load_shipment($shipment_id) {
    $file = BASE_PATH . '/data/shipments/' . $shipment_id . '.json';
    if (!file_exists($file)) return null;

    return json_decode(file_get_contents($file), true);
}

/**
 * Actualiza el estado de un envío local
 */
function zipnova_update_shipment_status($shipment_id, $new_data) {
    $shipment = zipnova_load_shipment($shipment_id);
    if (!$shipment) return false;

    if (isset($new_data['status'])) {
        $shipment['status'] = $new_data['status'];
    }

    $shipment['updated_at'] = date('Y-m-d H:i:s');
    $shipment['data'] = array_merge($shipment['data'] ?? [], $new_data);

    return zipnova_save_shipment($shipment_id, $shipment);
}

/**
 * Obtiene todos los envíos locales
 */
function zipnova_get_all_shipments($filter = []) {
    $shipments_dir = BASE_PATH . '/data/shipments';
    if (!is_dir($shipments_dir)) return [];

    $files = glob($shipments_dir . '/*.json');
    $shipments = [];

    foreach ($files as $file) {
        $shipment = json_decode(file_get_contents($file), true);

        // Aplicar filtros
        if (!empty($filter['status']) && $shipment['status'] !== $filter['status']) {
            continue;
        }
        if (!empty($filter['reference']) && strpos($shipment['reference'], $filter['reference']) === false) {
            continue;
        }

        $shipments[] = $shipment;
    }

    // Ordenar por fecha de creación (más recientes primero)
    usort($shipments, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    return $shipments;
}

/**
 * Log de eventos de Zipnova
 */
function zipnova_log($event, $data = []) {
    $logs_dir = BASE_PATH . '/logs/zipnova';
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $log_file = $logs_dir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf(
        "[%s] %s: %s\n",
        $timestamp,
        $event,
        json_encode($data, JSON_UNESCAPED_UNICODE)
    );

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Prueba la conexión con Zipnova
 */
function zipnova_test_connection() {
    // Primero intentar autenticarse
    $auth_result = zipnova_authenticate();

    if (!$auth_result['success']) {
        return [
            'success' => false,
            'error' => 'Error de autenticación: ' . $auth_result['error']
        ];
    }

    // Intentar hacer una petición simple (puede ser un endpoint de health check)
    // Si no existe, podemos intentar obtener cotizaciones con datos de prueba
    $config = zipnova_get_config();
    $test_quote = zipnova_get_quotes(
        null, // usar origen por defecto
        [
            'postal_code' => '5000',
            'city' => 'Córdoba',
            'province' => 'Córdoba',
            'country' => 'AR'
        ],
        [[
            'weight' => 1,
            'length' => 20,
            'width' => 15,
            'height' => 10,
            'declared_value' => 1000
        ]]
    );

    if ($test_quote['success']) {
        return [
            'success' => true,
            'message' => 'Conexión exitosa con Zipnova',
            'mode' => $config['mode'],
            'api_url' => zipnova_get_api_url()
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Error al consultar API: ' . $test_quote['error']
        ];
    }
}
