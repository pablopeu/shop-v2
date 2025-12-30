<?php
/**
 * Google Places API Integration
 * Servicio para normalización y validación de direcciones usando Google Places API
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Obtener configuración de Google Places API
 *
 * @return array|null Configuración de Google Places o null si no está disponible
 */
function google_places_get_config() {
    require_once APP_PATH . '/includes/carriers.php';
    $shipping_config = zipnova_get_config();

    return $shipping_config['google_places'] ?? null;
}

/**
 * Verificar si Google Places está habilitado
 *
 * @return bool True si está habilitado y configurado correctamente
 */
function google_places_is_enabled() {
    $config = google_places_get_config();

    if (!$config) {
        return false;
    }

    return (
        !empty($config['enabled']) &&
        !empty($config['api_key'])
    );
}

/**
 * Obtener API Key de Google Places
 *
 * @return string|null API Key o null si no está configurada
 */
function google_places_get_api_key() {
    $config = google_places_get_config();

    if (!$config || empty($config['api_key'])) {
        return null;
    }

    return $config['api_key'];
}

/**
 * Obtener código de país configurado para restricción de búsquedas
 *
 * @return string Código de país (ej: 'ar' para Argentina)
 */
function google_places_get_country_code() {
    $config = google_places_get_config();
    return $config['country_code'] ?? 'ar';
}

/**
 * Verificar si se requiere confirmación obligatoria de dirección
 *
 * @return bool True si la confirmación es obligatoria
 */
function google_places_requires_confirmation() {
    $config = google_places_get_config();
    return $config['require_confirmation'] ?? true;
}

/**
 * Validar y normalizar dirección usando Google Places API (vía servidor)
 * Nota: Esta función realiza llamadas directas a la API desde el servidor PHP
 *
 * @param array $address_data Datos de dirección a validar
 * @return array Resultado con dirección normalizada o error
 */
function google_places_validate_address($address_data) {
    if (!google_places_is_enabled()) {
        return [
            'success' => false,
            'error' => 'Google Places API no está habilitado'
        ];
    }

    $api_key = google_places_get_api_key();

    // Construir dirección completa para geocodificación
    $address_parts = [];
    if (!empty($address_data['address'])) {
        $address_parts[] = $address_data['address'];
    }
    if (!empty($address_data['city'])) {
        $address_parts[] = $address_data['city'];
    }
    if (!empty($address_data['province'])) {
        $address_parts[] = $address_data['province'];
    }
    if (!empty($address_data['postal_code'])) {
        $address_parts[] = $address_data['postal_code'];
    }

    $full_address = implode(', ', $address_parts);

    if (empty($full_address)) {
        return [
            'success' => false,
            'error' => 'No se proporcionó información de dirección'
        ];
    }

    // Usar Geocoding API para validar y normalizar
    $country_code = google_places_get_country_code();
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address' => $full_address,
        'components' => 'country:' . strtoupper($country_code),
        'key' => $api_key
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'error' => 'Error al conectar con Google Places API: ' . $error
        ];
    }

    curl_close($ch);

    if ($http_code !== 200) {
        return [
            'success' => false,
            'error' => 'Error HTTP ' . $http_code . ' al consultar Google Places API'
        ];
    }

    $result = json_decode($response, true);

    if (!$result || $result['status'] !== 'OK' || empty($result['results'])) {
        return [
            'success' => false,
            'error' => 'No se encontró la dirección especificada',
            'api_status' => $result['status'] ?? 'UNKNOWN'
        ];
    }

    // Extraer primer resultado (más relevante)
    $place = $result['results'][0];

    // Parsear componentes de dirección
    $normalized = [
        'formatted_address' => $place['formatted_address'],
        'latitude' => $place['geometry']['location']['lat'],
        'longitude' => $place['geometry']['location']['lng'],
        'place_id' => $place['place_id'],
        'components' => []
    ];

    // Extraer componentes estructurados
    $component_map = [
        'street_number' => 'street_number',
        'route' => 'route',
        'locality' => 'locality',
        'sublocality_level_1' => 'sublocality',
        'administrative_area_level_1' => 'province',
        'administrative_area_level_2' => 'department',
        'postal_code' => 'postal_code',
        'country' => 'country'
    ];

    foreach ($place['address_components'] as $component) {
        foreach ($component['types'] as $type) {
            if (isset($component_map[$type])) {
                $key = $component_map[$type];
                $normalized['components'][$key] = $component['long_name'];
                $normalized['components'][$key . '_short'] = $component['short_name'];
            }
        }
    }

    return [
        'success' => true,
        'address' => $normalized
    ];
}

/**
 * Generar datos para inicializar Google Places en frontend
 * Esta función devuelve configuración segura para uso en JavaScript
 *
 * @return array Configuración para frontend
 */
function google_places_get_frontend_config() {
    if (!google_places_is_enabled()) {
        return [
            'enabled' => false
        ];
    }

    return [
        'enabled' => true,
        'api_key' => google_places_get_api_key(),
        'country_code' => google_places_get_country_code(),
        'require_confirmation' => google_places_requires_confirmation(),
        'maps_js_url' => 'https://maps.googleapis.com/maps/api/js?key=' .
                        urlencode(google_places_get_api_key()) .
                        '&libraries=places&callback=initGooglePlaces'
    ];
}
