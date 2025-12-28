<?php
/**
 * Zipnova Shipping Integration
 *
 * Funciones para integración con la API de Zipnova
 * Documentación: /zipnova/
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Acceso directo no permitido');
}

/**
 * Carga la configuración de un carrier específico
 * @param string $carrier_tag Tag del carrier (ej: 'ZNVA')
 * @return array|null Configuración del carrier
 */
function get_carrier_config($carrier_tag = 'ZNVA') {
    $config_file = __DIR__ . '/../config/shipping.json';
    if (!file_exists($config_file)) {
        return null;
    }
    $config = json_decode(file_get_contents($config_file), true);

    // Try new multi-carrier structure first
    if (isset($config['carriers'][$carrier_tag])) {
        return $config['carriers'][$carrier_tag];
    }

    // Backward compatibility: try legacy structure
    if ($carrier_tag === 'ZNVA' && isset($config['zipnova'])) {
        return $config['zipnova'];
    }

    return null;
}

/**
 * Carga la configuración de Zipnova (backward compatibility)
 */
function zipnova_get_config() {
    return get_carrier_config('ZNVA');
}

/**
 * Guarda la configuración de un carrier
 * @param string $carrier_tag Tag del carrier
 * @param array $carrier_config Configuración del carrier
 * @return int|false Bytes written or false on failure
 */
function save_carrier_config($carrier_tag, $carrier_config) {
    $config_file = __DIR__ . '/../config/shipping.json';
    $config = file_exists($config_file)
        ? json_decode(file_get_contents($config_file), true)
        : ['carriers' => []];

    // Initialize carriers array if not exists
    if (!isset($config['carriers'])) {
        $config['carriers'] = [];
    }

    // Save to new structure
    $config['carriers'][$carrier_tag] = $carrier_config;

    return file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Guarda la configuración de Zipnova (backward compatibility)
 */
function zipnova_save_config($zipnova_config) {
    return save_carrier_config('ZNVA', $zipnova_config);
}

/**
 * Obtiene todos los carriers configurados
 * @return array Array de carriers con sus configs
 */
function get_all_carriers() {
    $config_file = __DIR__ . '/../config/shipping.json';
    if (!file_exists($config_file)) {
        return [];
    }
    $config = json_decode(file_get_contents($config_file), true);
    return $config['carriers'] ?? [];
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
 * Usa HTTP Basic Authentication con API Token y API Secret
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 */
function zipnova_api_request($endpoint, $method = 'GET', $data = null, $use_auth = true, $order_id = null) {
    $config = zipnova_get_config();
    if (!$config) {
        return ['success' => false, 'error' => 'Configuración de Zipnova no encontrada'];
    }

    $base_url = zipnova_get_api_url();
    $url = $base_url . $endpoint;

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];

    if ($use_auth) {
        // Verificar que las credenciales estén configuradas
        $api_token = $config['credentials']['client_id'] ?? '';
        $api_secret = $config['credentials']['client_secret'] ?? '';

        if (empty($api_token) || empty($api_secret)) {
            return ['success' => false, 'error' => 'API Token y API Secret no están configurados'];
        }

        // HTTP Basic Authentication
        // Usuario: API Token, Contraseña: API Secret
        $auth = base64_encode($api_token . ':' . $api_secret);
        $headers[] = 'Authorization: Basic ' . $auth;
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

    // Log de la request (con order_id si está presente)
    zipnova_log('API Request', [
        'method' => $method,
        'endpoint' => $endpoint,
        'http_code' => $http_code,
        'retries' => $retries,
        'success' => $response !== false
    ], $order_id);

    if ($response === false) {
        // Guardar error en archivo JSON separado
        zipnova_save_response_json($endpoint, $method, [
            'request' => $data,
            'error' => $curl_error,
            'http_code' => 0
        ], $order_id);
        return ['success' => false, 'error' => 'Error de conexión: ' . $curl_error];
    }

    $result = json_decode($response, true);

    // Guardar respuesta completa en archivo JSON separado para debug
    zipnova_save_response_json($endpoint, $method, [
        'request' => $data,
        'response' => $result,
        'http_code' => $http_code,
        'raw_response' => $response
    ], $order_id);

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
 * Verifica las credenciales de API
 * Nota: Con HTTP Basic Auth no se necesita obtener tokens,
 * las credenciales se envían en cada request
 */
function zipnova_authenticate() {
    $config = zipnova_get_config();
    if (!$config) {
        return ['success' => false, 'error' => 'Configuración no encontrada'];
    }

    $api_token = $config['credentials']['client_id'] ?? '';
    $api_secret = $config['credentials']['client_secret'] ?? '';

    if (empty($api_token) || empty($api_secret)) {
        return ['success' => false, 'error' => 'API Token y API Secret no configurados'];
    }

    // Con HTTP Basic Auth, simplemente verificamos que las credenciales existan
    // La validación real ocurre cuando hacemos requests a la API
    zipnova_log('Credentials Check', ['api_token' => substr($api_token, 0, 10) . '...']);

    return [
        'success' => true,
        'message' => 'Credenciales configuradas. Se validarán en cada request con HTTP Basic Auth.'
    ];
}

/**
 * Parsea una duración en formato ISO 8601 a días
 * Ejemplo: "P2DT10H" = 2 días, 10 horas → 3 días (redondeado)
 *
 * @param string $duration Duración en formato ISO 8601 (ej: "P2DT10H1S")
 * @return int Número de días (redondeado hacia arriba si hay horas)
 */
function parse_iso8601_duration_to_days($duration) {
    if (empty($duration) || !is_string($duration)) {
        return 0;
    }

    // Pattern para parsear ISO 8601 duration
    // Formato: P[n]Y[n]M[n]DT[n]H[n]M[n]S
    $pattern = '/P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?/';

    if (!preg_match($pattern, $duration, $matches)) {
        return 0;
    }

    $days = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;
    $hours = isset($matches[4]) && $matches[4] !== '' ? (int)$matches[4] : 0;

    // Si hay horas, redondear al día siguiente
    if ($hours > 0) {
        $days += 1;
    }

    return $days;
}

/**
 * Calcula el rango de días de entrega desde los tiempos ISO 8601
 * Usa los nuevos campos 'times.total' en lugar de 'min'/'max' deprecados
 *
 * @param array $delivery_time Objeto delivery_time de Zipnova
 * @return string Rango de días (ej: "3-5") o "A confirmar"
 */
function calculate_delivery_days($delivery_time) {
    // Priorizar nuevos campos ISO 8601
    if (isset($delivery_time['times']['total'])) {
        $min_duration = $delivery_time['times']['total']['min'] ?? '';
        $max_duration = $delivery_time['times']['total']['max'] ?? '';

        $min_days = parse_iso8601_duration_to_days($min_duration);
        $max_days = parse_iso8601_duration_to_days($max_duration);

        if ($min_days > 0 && $max_days > 0) {
            return $min_days . '-' . $max_days;
        } elseif ($min_days > 0) {
            return (string)$min_days;
        }
    }

    // Fallback a campos deprecados si no hay nuevos datos
    $min = $delivery_time['min'] ?? 0;
    $max = $delivery_time['max'] ?? 0;
    if ($min > 0 && $max > 0) {
        return $min . '-' . $max;
    }

    return 'A confirmar';
}

/**
 * Cotiza envíos usando API v2 de Zipnova
 *
 * @param array $destination Datos de destino (city, state, zipcode)
 * @param array $items Array de items con sku, weight, height, width, length, description, classification_id
 * @param float|null $declared_value Valor declarado total (se calcula automáticamente si es null)
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 * @return array Resultado con cotizaciones disponibles
 */
function zipnova_get_quotes($destination, $items, $declared_value = null, $order_id = null) {
    // Log al inicio para confirmar que se llama la función
    zipnova_log('zipnova_get_quotes CALLED', [
        'destination' => $destination,
        'items_count' => count($items),
        'declared_value' => $declared_value
    ], $order_id);

    $config = zipnova_get_config();

    if (!$config || !$config['enabled']) {
        zipnova_log('zipnova_get_quotes ERROR', ['error' => 'Zipnova no está habilitado'], $order_id);
        return ['success' => false, 'error' => 'Zipnova no está habilitado'];
    }

    // Validar que existan account_id y origin_id
    $account_id = $config['credentials']['account_id'] ?? '';
    $origin_id = $config['origin']['origin_id'] ?? '';

    if (empty($account_id)) {
        return ['success' => false, 'error' => 'Account ID no configurado'];
    }

    if (empty($origin_id)) {
        return ['success' => false, 'error' => 'Origin ID no configurado'];
    }

    // Calcular declared_value si no se proporciona
    if ($declared_value === null) {
        $declared_value = 0;
        foreach ($items as $item) {
            $declared_value += ($item['declared_value'] ?? 0);
        }
    }

    // Formatear items según estructura de Zipnova
    $formatted_items = [];
    foreach ($items as $item) {
        $formatted_items[] = [
            'sku' => $item['sku'] ?? 'PROD-' . uniqid(),
            'weight' => (int)($item['weight'] ?? $config['default_package']['weight']),
            'height' => (int)($item['height'] ?? $config['default_package']['height']),
            'width' => (int)($item['width'] ?? $config['default_package']['width']),
            'length' => (int)($item['length'] ?? $config['default_package']['length']),
            'description' => $item['description'] ?? 'Producto',
            'classification_id' => (int)($item['classification_id'] ?? 1)
        ];
    }

    $request_data = [
        'account_id' => $account_id,
        'origin_id' => $origin_id,
        'declared_value' => (float)$declared_value,
        'items' => $formatted_items,
        'destination' => [
            'city' => $destination['city'] ?? '',
            'state' => $destination['state'] ?? $destination['province'] ?? '',
            'zipcode' => $destination['zipcode'] ?? $destination['postal_code'] ?? ''
        ]
    ];

    $result = zipnova_api_request('/shipments/quote', 'POST', $request_data, true, $order_id);

    if ($result['success']) {
        // Transformar respuesta de Zipnova al formato esperado por el frontend
        if (isset($result['data']['all_results']) && is_array($result['data']['all_results'])) {
            $quotes = [];
            foreach ($result['data']['all_results'] as $zipnova_quote) {
                // Usar nueva función que parsea tiempos ISO 8601
                $delivery_time = $zipnova_quote['delivery_time'] ?? [];
                $estimated_days = calculate_delivery_days($delivery_time);

                $quotes[] = [
                    // Identificadores de servicio
                    'service_id' => $zipnova_quote['service_type']['code'] ?? 'unknown',
                    'service_type_id' => $zipnova_quote['service_type']['id'] ?? null,
                    'service_type_code' => $zipnova_quote['service_type']['code'] ?? '',
                    'service_name' => ($zipnova_quote['carrier']['name'] ?? 'Carrier') . ' - ' . ($zipnova_quote['service_type']['name'] ?? 'Service'),

                    // Información del carrier
                    'carrier_id' => $zipnova_quote['carrier']['id'] ?? null,
                    'carrier_name' => $zipnova_quote['carrier']['name'] ?? '',
                    'carrier_logo' => $zipnova_quote['carrier']['logo'] ?? '',
                    'carrier_rating' => $zipnova_quote['carrier']['rating'] ?? null,

                    // Tipo de logística
                    'logistic_type' => $zipnova_quote['logistic_type'] ?? '',

                    // Tiempos de entrega
                    'estimated_days' => $estimated_days,
                    'estimated_delivery' => $zipnova_quote['delivery_time']['estimated_delivery'] ?? null,
                    'estimation_expires_at' => $zipnova_quote['delivery_time']['estimation_expires_at'] ?? null,

                    // Costos (desglosados)
                    'cost' => (float)($zipnova_quote['amounts']['price_incl_tax'] ?? 0),
                    'price_shipment' => (float)($zipnova_quote['amounts']['price_shipment'] ?? 0),
                    'price_insurance' => (float)($zipnova_quote['amounts']['price_insurance'] ?? 0),
                    'price_base' => (float)($zipnova_quote['amounts']['price'] ?? 0),

                    // IDs de tarifa (necesarios para crear envío)
                    'rate_id' => $zipnova_quote['rate']['id'] ?? null,
                    'tariff_id' => $zipnova_quote['rate']['tariff_id'] ?? null,
                    'rate_source' => $zipnova_quote['rate']['source'] ?? null,

                    // Tags (cheapest, fastest, etc.)
                    'tags' => $zipnova_quote['tags'] ?? []
                ];
            }
            $result['data']['quotes'] = $quotes;

            // Filtrar cotizaciones según método de despacho preferido del vendedor
            $dispatch_method = $config['dispatch_method'] ?? null;
            if ($dispatch_method && isset($dispatch_method['preferred'])) {
                $preferred = $dispatch_method['preferred'];
                $valid_logistic_types = $dispatch_method['options'][$preferred]['logistic_types'] ?? [];

                if (!empty($valid_logistic_types) && !empty($quotes)) {
                    $filtered_quotes = [];
                    foreach ($quotes as $quote) {
                        $quote_logistic_type = $quote['logistic_type'] ?? '';
                        // Verificar si el logistic_type de la cotización coincide con los permitidos
                        if (in_array($quote_logistic_type, $valid_logistic_types)) {
                            $filtered_quotes[] = $quote;
                        }
                    }

                    // Solo reemplazar si hay quotes filtradas, sino mantener todas
                    if (!empty($filtered_quotes)) {
                        $result['data']['quotes'] = $filtered_quotes;
                        zipnova_log('Quotes Filtered by Dispatch Method', [
                            'preferred_method' => $preferred,
                            'valid_logistic_types' => $valid_logistic_types,
                            'total_quotes' => count($quotes),
                            'filtered_quotes' => count($filtered_quotes)
                        ], $order_id);
                    } else {
                        zipnova_log('No Quotes Match Preferred Dispatch Method', [
                            'preferred_method' => $preferred,
                            'valid_logistic_types' => $valid_logistic_types,
                            'available_logistic_types' => array_unique(array_column($quotes, 'logistic_type'))
                        ], $order_id);
                    }
                }
            }
        }

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

    // Log detallado con la respuesta completa para debug
    zipnova_log('Quote Request', [
        'success' => $result['success'],
        'destination' => $destination['zipcode'] ?? $destination['postal_code'] ?? 'N/A',
        'items_count' => count($items),
        'declared_value' => $declared_value,
        'full_response' => $result  // <-- RESPUESTA COMPLETA PARA DEBUG
    ], $order_id);

    return $result;
}

/**
 * Crea un envío en Zipnova
 *
 * @param array $shipment_data Datos del envío completos
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 * @return array Resultado con ID del envío creado
 */
function zipnova_create_shipment($shipment_data, $order_id = null) {
    $config = zipnova_get_config();

    if (!$config || !$config['enabled']) {
        return ['success' => false, 'error' => 'Zipnova no está habilitado'];
    }

    // Determinar método de despacho y configurar origin según preferencia del vendedor
    $dispatch_method = $config['dispatch_method'] ?? null;
    $preferred_method = $dispatch_method['preferred'] ?? 'pickup';

    if ($preferred_method === 'dropoff') {
        // Drop-off: el vendedor entrega en un punto Zipnova
        if (!isset($shipment_data['origin'])) {
            $point_id = $dispatch_method['options']['dropoff']['point_id'] ?? null;

            // Si hay point_id configurado, usarlo
            // Si NO hay point_id, significa que se puede entregar en cualquier punto de la red
            if (!empty($point_id)) {
                $shipment_data['origin'] = [
                    'point_id' => $point_id
                ];

                zipnova_log('Using Specific Drop-off Point', [
                    'point_id' => $point_id,
                    'point_name' => $dispatch_method['options']['dropoff']['point_name'] ?? 'N/A'
                ], $order_id);
            } else {
                // No especificar origin ni origin_id
                // El logistic_type del rate_id indica que es dropoff
                // Zipnova permite entregar en cualquier punto de su red
                zipnova_log('Using Flexible Drop-off', [
                    'note' => 'No point_id specified - can deliver to any Zipnova network point'
                ], $order_id);
            }
        }
    } else {
        // Pickup: recolección en domicilio del vendedor
        if (!isset($shipment_data['origin_id'])) {
            $shipment_data['origin_id'] = $config['origin']['origin_id'] ?? '';

            zipnova_log('Using Pickup from Origin', [
                'origin_id' => $shipment_data['origin_id']
            ], $order_id);
        }
    }

    // Agregar account_id al request (CRÍTICO para Zipnova)
    $account_id = $config['credentials']['account_id'] ?? '';
    if (!empty($account_id)) {
        $shipment_data['account_id'] = $account_id;
    }

    // Controlar envío de email del cliente a Zipnova
    // Si send_customer_email = false, NO enviar el email para evitar que Zipnova envíe notificaciones duplicadas
    $send_customer_email = $config['options']['send_customer_email'] ?? false;
    if (!$send_customer_email && isset($shipment_data['destination']['email'])) {
        zipnova_log('Customer Email Removed', [
            'note' => 'Email no enviado a Zipnova (send_customer_email=false)',
            'removed_email' => $shipment_data['destination']['email']
        ], $order_id);
        unset($shipment_data['destination']['email']);
    }

    $result = zipnova_api_request('/shipments', 'POST', $shipment_data, true, $order_id);

    if ($result['success']) {
        // Guardar el envío localmente
        $shipment_id = $result['data']['id'] ?? uniqid('shp_');
        zipnova_save_shipment($shipment_id, [
            'zipnova_id' => $shipment_id,
            'reference' => $shipment_data['reference'] ?? '',
            'status' => 'pendiente',  // Estado en español (consistente con sistema)
            'created_at' => date('Y-m-d H:i:s'),
            'data' => $result['data']
        ]);
    }

    zipnova_log('Shipment Created', [
        'success' => $result['success'],
        'reference' => $shipment_data['reference'] ?? 'N/A'
    ], $order_id);

    return $result;
}

/**
 * Consulta el estado de un envío
 *
 * @param string $shipment_id ID del envío en Zipnova
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 * @return array Resultado con datos del envío
 */
function zipnova_get_shipment($shipment_id, $order_id = null) {
    $result = zipnova_api_request('/shipments/' . $shipment_id, 'GET', null, true, $order_id);

    if ($result['success']) {
        // Actualizar datos locales
        zipnova_update_shipment_status($shipment_id, $result['data']);
    }

    zipnova_log('Shipment Status Check', [
        'shipment_id' => $shipment_id,
        'success' => $result['success']
    ], $order_id);

    return $result;
}

/**
 * Cancela un envío
 *
 * @param string $shipment_id ID del envío en Zipnova
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 * @return array Resultado de la cancelación
 */
function zipnova_cancel_shipment($shipment_id, $order_id = null) {
    $result = zipnova_api_request('/shipments/' . $shipment_id . '/cancel', 'POST', null, true, $order_id);

    if ($result['success']) {
        zipnova_update_shipment_status($shipment_id, ['status' => 'cancelled']);
    }

    zipnova_log('Shipment Cancelled', [
        'shipment_id' => $shipment_id,
        'success' => $result['success']
    ], $order_id);

    return $result;
}

/**
 * Obtiene la etiqueta de envío (PDF o imagen)
 *
 * NOTA: Esta función es un stub/esqueleto pendiente de implementación
 * cuando se obtengan las credenciales de sandbox de Zipnova.
 *
 * El endpoint exacto puede variar según la documentación de Zipnova.
 * Opciones típicas:
 * - GET /shipments/{id}/label
 * - GET /shipments/{id}/label/pdf
 * - GET /shipments/{id}/documents
 *
 * @param string $shipment_id ID del envío en Zipnova
 * @param string $format Formato deseado: 'pdf', 'png', 'zpl' (default: 'pdf')
 * @return array ['success' => bool, 'data' => ['label_url' => string, 'format' => string], 'error' => string]
 */
function zipnova_get_label($shipment_id, $format = 'pdf', $order_id = null) {
    // Validar que el envío existe
    $shipment = zipnova_load_shipment($shipment_id);
    if (!$shipment) {
        zipnova_log('Label Request Failed - Shipment Not Found', [
            'shipment_id' => $shipment_id,
            'error' => 'Envío no encontrado'
        ], $order_id);

        return [
            'success' => false,
            'error' => 'Envío no encontrado'
        ];
    }

    // Validar que el envío está en un estado que permite generar etiqueta
    // Típicamente solo se puede generar etiqueta si el envío fue creado exitosamente
    $valid_statuses = ['pendiente', 'en_transito', 'en_reparto'];
    if (!in_array($shipment['status'] ?? '', $valid_statuses)) {
        zipnova_log('Label Request Failed - Invalid Status', [
            'shipment_id' => $shipment_id,
            'status' => $shipment['status'] ?? 'unknown',
            'error' => 'El envío no está en un estado válido para generar etiqueta'
        ], $order_id);

        return [
            'success' => false,
            'error' => 'El envío debe estar pendiente o en tránsito para generar la etiqueta'
        ];
    }

    // Verificar si ya tenemos una etiqueta guardada
    if (isset($shipment['label_url']) && !empty($shipment['label_url'])) {
        zipnova_log('Label Retrieved from Cache', [
            'shipment_id' => $shipment_id,
            'format' => $format
        ], $order_id);

        return [
            'success' => true,
            'data' => [
                'label_url' => $shipment['label_url'],
                'format' => $shipment['label_format'] ?? $format,
                'cached' => true
            ]
        ];
    }

    // Endpoint correcto según documentación de Zipnova:
    // GET /shipments/{id}/documentation?what=label&format=pdf
    // La respuesta incluye el PDF en base64 en el campo 'content'
    $endpoint = '/shipments/' . $shipment_id . '/documentation?what=label&format=' . $format;

    $result = zipnova_api_request($endpoint, 'GET', null, true, $order_id);

    if ($result['success']) {
        // Zipnova devuelve el PDF en base64 en el campo 'body'
        $base64_content = $result['data']['body'] ?? '';

        if (empty($base64_content)) {
            zipnova_log('Label Generation Failed - No Content', [
                'shipment_id' => $shipment_id,
                'error' => 'Respuesta de Zipnova sin contenido base64'
            ], $order_id);

            return [
                'success' => false,
                'error' => 'La etiqueta no contiene datos'
            ];
        }

        // Decodificar base64
        $pdf_content = base64_decode($base64_content);

        if ($pdf_content === false) {
            zipnova_log('Label Generation Failed - Invalid Base64', [
                'shipment_id' => $shipment_id,
                'error' => 'Error al decodificar base64'
            ], $order_id);

            return [
                'success' => false,
                'error' => 'Error al decodificar la etiqueta'
            ];
        }

        // Crear directorio para etiquetas si no existe
        $labels_dir = __DIR__ . '/../data/labels';
        if (!is_dir($labels_dir)) {
            mkdir($labels_dir, 0755, true);
        }

        // Guardar PDF en el servidor
        $filename = 'label_' . $shipment_id . '_' . time() . '.' . $format;
        $filepath = $labels_dir . '/' . $filename;

        if (file_put_contents($filepath, $pdf_content) === false) {
            zipnova_log('Label Generation Failed - Save Error', [
                'shipment_id' => $shipment_id,
                'error' => 'Error al guardar archivo PDF'
            ], $order_id);

            return [
                'success' => false,
                'error' => 'Error al guardar la etiqueta'
            ];
        }

        // Generar URL relativa del PDF
        $label_url = '/data/labels/' . $filename;

        // Guardar en datos locales y cambiar estado a "en_preparacion"
        $shipment['label_url'] = $label_url;
        $shipment['label_format'] = $format;
        $shipment['label_generated_at'] = date('Y-m-d H:i:s');
        $shipment['status'] = 'en_preparacion'; // Cambiar estado cuando se genera etiqueta

        zipnova_save_shipment($shipment_id, $shipment);

        zipnova_log('Label Generated Successfully', [
            'shipment_id' => $shipment_id,
            'format' => $format,
            'label_url' => $label_url,
            'filesize' => strlen($pdf_content),
            'new_status' => 'en_preparacion'
        ], $order_id);

        // Actualizar estado de la orden y enviar email al cliente
        require_once __DIR__ . '/orders.php';
        require_once __DIR__ . '/email.php';

        // Buscar la orden por shipment_id
        $orders = get_all_orders();
        foreach ($orders as $ord) {
            if (isset($ord['shipping']['carrier_shipment_id']) &&
                $ord['shipping']['carrier_shipment_id'] === $shipment_id) {

                // Actualizar estado del envío en la orden
                update_order_shipping_status($ord['id'], 'en_preparacion');

                // Enviar email de notificación al cliente
                send_shipping_preparation_email($ord);

                zipnova_log('Order Status Updated to en_preparacion', [
                    'order_id' => $ord['id'],
                    'order_number' => $ord['order_number'],
                    'shipment_id' => $shipment_id
                ], $order_id ?? $ord['order_number']);

                break;
            }
        }

        return [
            'success' => true,
            'data' => [
                'label_url' => $label_url,
                'format' => $format,
                'cached' => false
            ]
        ];
    }

    zipnova_log('Label Generation Failed', [
        'shipment_id' => $shipment_id,
        'error' => $result['error'] ?? 'Unknown error'
    ], $order_id);

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
    $shipments_dir = __DIR__ . '/../data/shipments';
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
    $file = __DIR__ . '/../data/shipments/' . $shipment_id . '.json';
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
    $shipments_dir = __DIR__ . '/../data/shipments';
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
 * @param string $event Tipo de evento
 * @param array $data Datos del evento
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 */
function zipnova_log($event, $data = [], $order_id = null) {
    // Usar ruta absoluta desde la raíz del proyecto
    $logs_dir = dirname(dirname(__DIR__)) . '/logs/zipnova';
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $log_file = $logs_dir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');

    // Agregar order_id al contexto si está presente
    if ($order_id !== null) {
        $data['order_id'] = $order_id;
    }

    $log_entry = sprintf(
        "[%s]%s %s: %s\n",
        $timestamp,
        $order_id ? " [ORDER: $order_id]" : "",
        $event,
        json_encode($data, JSON_UNESCAPED_UNICODE)
    );

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Guarda respuesta de API en archivo JSON separado para debug
 * @param string $endpoint El endpoint llamado (ej: '/shipments/quote')
 * @param string $method El método HTTP usado (GET, POST, etc)
 * @param array $data Array con request, response, http_code, etc
 * @param string|null $order_id Identificador de orden (para rastrear conversación completa)
 */
function zipnova_save_response_json($endpoint, $method, $data, $order_id = null) {
    // Usar ruta absoluta desde la raíz del proyecto
    $logs_dir = dirname(dirname(__DIR__)) . '/logs/zipnova-responses';
    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    // Generar nombre de archivo con timestamp, order_id (si existe) y endpoint
    $timestamp = date('Y-m-d_H-i-s');
    $endpoint_name = trim(str_replace('/', '_', $endpoint), '_');
    $order_prefix = $order_id ? "ORDER-{$order_id}_" : '';
    $filename = sprintf('%s_%s%s_%s.json', $timestamp, $order_prefix, $endpoint_name, $method);
    $filepath = $logs_dir . '/' . $filename;

    // Preparar datos completos para el log
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'order_id' => $order_id,  // Agregar order_id al JSON
        'endpoint' => $endpoint,
        'method' => $method,
        'http_code' => $data['http_code'] ?? 0,
        'request' => $data['request'] ?? null,
        'response' => $data['response'] ?? null,
        'error' => $data['error'] ?? null,
        'raw_response' => $data['raw_response'] ?? null
    ];

    // Guardar con formato legible
    file_put_contents(
        $filepath,
        json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Calcula peso total desde productos del carrito
 * @param array $cart_items Items del carrito con información de productos
 * @return float Peso total en kg
 */
function zipnova_calculate_cart_weight($cart_items) {
    $total_weight = 0;
    $config = zipnova_get_config();
    $default_weight = $config['default_package']['weight'] ?? 1;

    foreach ($cart_items as $item) {
        $quantity = $item['quantity'] ?? 1;
        $product_weight = 0;

        // Intentar obtener peso del producto
        if (isset($item['weight']) && $item['weight'] > 0) {
            $product_weight = (float)$item['weight'];
        } elseif (isset($item['product_id'])) {
            // Cargar producto completo si solo tenemos el ID
            require_once __DIR__ . '/products.php';
            $product = get_product_by_id($item['product_id']);
            if ($product && isset($product['weight']) && $product['weight'] > 0) {
                $product_weight = (float)$product['weight'];
            }
        }

        // Si no hay peso definido, usar peso por defecto
        if ($product_weight === 0) {
            $product_weight = $default_weight;
        }

        $total_weight += $product_weight * $quantity;
    }

    // Mínimo 0.1kg
    return max(0.1, $total_weight);
}

/**
 * Calcula dimensiones del paquete desde productos del carrito
 * @param array $cart_items Items del carrito con información de productos
 * @return array Array con length, width, height en cm
 */
function zipnova_calculate_cart_dimensions($cart_items) {
    $config = zipnova_get_config();
    $default_dims = $config['default_package'];

    $max_length = 0;
    $max_width = 0;
    $total_height = 0;

    foreach ($cart_items as $item) {
        $quantity = $item['quantity'] ?? 1;
        $product_length = 0;
        $product_width = 0;
        $product_height = 0;

        // Intentar obtener dimensiones del producto
        if (isset($item['length']) && isset($item['width']) && isset($item['height'])) {
            $product_length = (float)$item['length'];
            $product_width = (float)$item['width'];
            $product_height = (float)$item['height'];
        } elseif (isset($item['product_id'])) {
            // Cargar producto completo si solo tenemos el ID
            require_once __DIR__ . '/products.php';
            $product = get_product_by_id($item['product_id']);
            if ($product) {
                $product_length = (float)($product['length'] ?? 0);
                $product_width = (float)($product['width'] ?? 0);
                $product_height = (float)($product['height'] ?? 0);
            }
        }

        // Si no hay dimensiones, usar defaults
        if ($product_length === 0) {
            $product_length = $default_dims['length'];
            $product_width = $default_dims['width'];
            $product_height = $default_dims['height'];
        }

        // Acumular dimensiones
        $max_length = max($max_length, $product_length);
        $max_width = max($max_width, $product_width);
        $total_height += $product_height * $quantity; // Apilar productos
    }

    return [
        'length' => max(1, $max_length),
        'width' => max(1, $max_width),
        'height' => max(1, $total_height)
    ];
}

/**
 * Calcula valor declarado desde productos del carrito
 * @param array $cart_items Items del carrito
 * @return float Valor total declarado
 */
function zipnova_calculate_cart_value($cart_items) {
    $total_value = 0;

    foreach ($cart_items as $item) {
        $quantity = $item['quantity'] ?? 1;
        $price = $item['final_price_ars'] ?? $item['price_ars'] ?? 0;
        $total_value += $price * $quantity;
    }

    return max(0, $total_value);
}

/**
 * Construye paquetes para Zipnova desde el carrito
 * @param array $cart_items Items del carrito
 * @return array Array de paquetes para la API de Zipnova
 */
function zipnova_build_packages_from_cart($cart_items) {
    $weight = zipnova_calculate_cart_weight($cart_items);
    $dimensions = zipnova_calculate_cart_dimensions($cart_items);
    $declared_value = zipnova_calculate_cart_value($cart_items);

    return [[
        'weight' => $weight,
        'length' => $dimensions['length'],
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
        'declared_value' => $declared_value
    ]];
}

/**
 * Prueba la conexión con Zipnova usando HTTP Basic Auth
 */
function zipnova_test_connection() {
    // Verificar que las credenciales estén configuradas
    $auth_check = zipnova_authenticate();

    if (!$auth_check['success']) {
        return [
            'success' => false,
            'error' => $auth_check['error']
        ];
    }

    // Probar la conexión con una cotización de prueba
    // Esto validará las credenciales con HTTP Basic Auth
    $config = zipnova_get_config();
    $test_quote = zipnova_get_quotes(
        [
            'city' => 'Córdoba',
            'state' => 'Córdoba',
            'zipcode' => '5000'
        ],
        [[
            'sku' => 'TEST-001',
            'weight' => 100,
            'height' => 10,
            'width' => 10,
            'length' => 10,
            'description' => 'Producto de prueba',
            'classification_id' => 1
        ]],
        1000 // declared_value
    );

    if ($test_quote['success']) {
        return [
            'success' => true,
            'message' => 'Conexión exitosa con Zipnova usando HTTP Basic Auth',
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

// ============================================================================
// MULTI-CARRIER HELPER FUNCTIONS
// ============================================================================

/**
 * Mapea el estado de un carrier a un estado base del sistema
 * @param string $carrier_type Tipo de carrier (zipnova, etc.)
 * @param string $carrier_status Estado del carrier
 * @return string Estado base del sistema
 */
function map_carrier_status_to_base($carrier_type, $carrier_status) {
    $mappings = [
        'zipnova' => [
            'pending' => 'pendiente',
            'ready' => 'en_preparacion',
            'in_transit' => 'en_transito',
            'out_for_delivery' => 'en_reparto',
            'delivered' => 'entregada',
            'failed' => 'fallida',
            'returned' => 'devuelta',
            'cancelled' => 'cancelada'
        ]
    ];

    if (isset($mappings[$carrier_type][$carrier_status])) {
        return $mappings[$carrier_type][$carrier_status];
    }

    // Default fallback
    return 'pendiente';
}

/**
 * Obtiene el label legible para un estado base
 * @param string $base_status Estado base del sistema
 * @return string Label del estado
 */
function get_status_label($base_status) {
    $labels = [
        'pendiente' => 'Pendiente',
        'en_preparacion' => 'En preparación',
        'en_transito' => 'En tránsito',
        'en_reparto' => 'En reparto',
        'entregada' => 'Entregada',
        'fallida' => 'Fallida',
        'devuelta' => 'Devuelta',
        'cancelada' => 'Cancelada',
        // Legacy states
        'cobrada' => 'Cobrada',
        'enviada' => 'Enviada'
    ];

    return $labels[$base_status] ?? ucfirst($base_status);
}

/**
 * Renderiza un estado con el tag del carrier si existe
 * @param array $shipping Objeto shipping de la orden
 * @return string HTML del estado con badge
 */
function render_shipping_status($shipping) {
    if (!$shipping) {
        return '<span class="status-badge status-pending">Sin envío</span>';
    }

    $status = $shipping['status'] ?? 'pendiente';
    $carrier = $shipping['carrier'] ?? null;
    $status_label = get_status_label($status);

    $class = 'status-badge status-' . str_replace('_', '-', $status);

    if ($carrier) {
        return '<span class="' . $class . '">' . htmlspecialchars($status_label) .
               ' <span class="carrier-tag">' . htmlspecialchars($carrier) . '</span></span>';
    }

    return '<span class="' . $class . '">' . htmlspecialchars($status_label) . '</span>';
}

/**
 * Obtiene el nombre de un carrier por su tag
 * @param string $carrier_tag Tag del carrier
 * @return string Nombre del carrier
 */
function get_carrier_name($carrier_tag) {
    $config = get_carrier_config($carrier_tag);
    return $config['name'] ?? $carrier_tag;
}
