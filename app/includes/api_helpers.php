<?php
/**
 * API Helper Functions
 * Funciones auxiliares para endpoints de API
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Apply rate limiting to API endpoint
 * Aplica rate limiting a un endpoint de API y termina la ejecución si se excede
 *
 * @param int $max_requests Máximo de requests permitidos
 * @param int $window_seconds Ventana de tiempo en segundos
 * @param string|null $identifier Identificador personalizado (default: IP + endpoint)
 * @return void Termina ejecución si se excede el límite
 */
function api_rate_limit($max_requests = 20, $window_seconds = 60, $identifier = null) {
    // Identificador único: IP + archivo del endpoint
    if ($identifier === null) {
        $identifier = $_SERVER['REMOTE_ADDR'] . ':' . basename($_SERVER['SCRIPT_FILENAME']);
    }

    $rate_limit = check_rate_limit($identifier, $max_requests, $window_seconds);

    if (!$rate_limit['allowed']) {
        $retry_after = $rate_limit['retry_after'] ?? 60;

        http_response_code(429);
        header('Retry-After: ' . $retry_after);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'error' => 'Demasiadas solicitudes. Intenta nuevamente en unos momentos.',
            'retry_after' => $retry_after
        ]);

        exit;
    }
}

/**
 * Require JSON Content-Type header
 * Verifica que el Content-Type sea application/json
 *
 * @return void Termina ejecución si no es JSON
 */
function require_json_content_type() {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';

    // Permitir con o sin charset
    if (strpos($content_type, 'application/json') === false) {
        http_response_code(415);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'error' => 'Content-Type debe ser application/json'
        ]);

        exit;
    }
}

/**
 * Validate API input against schema
 * Valida datos de entrada contra un esquema
 *
 * @param array $data Datos a validar
 * @param array $schema Esquema de validación
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_api_input($data, $schema) {
    foreach ($schema as $field => $rules) {
        // Verificar campo requerido
        if (isset($rules['required']) && $rules['required']) {
            if (!isset($data[$field])) {
                return [
                    'valid' => false,
                    'error' => "Campo requerido: $field"
                ];
            }
        }

        // Si el campo no está presente y no es requerido, continuar
        if (!isset($data[$field])) {
            continue;
        }

        $value = $data[$field];

        // Validar tipo
        if (isset($rules['type'])) {
            $actual_type = gettype($value);
            $expected_type = $rules['type'];

            // Normalizar 'integer' a 'int' para comparación
            if ($expected_type === 'integer') {
                $expected_type = 'int';
            }
            if ($actual_type === 'integer') {
                $actual_type = 'int';
            }

            if ($actual_type !== $expected_type) {
                return [
                    'valid' => false,
                    'error' => "Tipo inválido para '$field': se esperaba $expected_type, se recibió $actual_type"
                ];
            }
        }

        // Validar longitud de string
        if (isset($rules['max_length']) && is_string($value)) {
            if (strlen($value) > $rules['max_length']) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' excede longitud máxima de {$rules['max_length']}"
                ];
            }
        }

        if (isset($rules['min_length']) && is_string($value)) {
            if (strlen($value) < $rules['min_length']) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' no alcanza longitud mínima de {$rules['min_length']}"
                ];
            }
        }

        // Validar patrón regex
        if (isset($rules['pattern']) && is_string($value)) {
            if (!preg_match($rules['pattern'], $value)) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' no cumple el formato requerido"
                ];
            }
        }

        // Validar cantidad de elementos en array
        if (isset($rules['max_items']) && is_array($value)) {
            if (count($value) > $rules['max_items']) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' excede cantidad máxima de {$rules['max_items']} elementos"
                ];
            }
        }

        if (isset($rules['min_items']) && is_array($value)) {
            if (count($value) < $rules['min_items']) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' requiere al menos {$rules['min_items']} elementos"
                ];
            }
        }

        // Validar valores permitidos (enum)
        if (isset($rules['allowed_values']) && is_array($rules['allowed_values'])) {
            if (!in_array($value, $rules['allowed_values'], true)) {
                return [
                    'valid' => false,
                    'error' => "Valor no permitido para '$field'"
                ];
            }
        }

        // Validar rango numérico
        if (isset($rules['min']) && is_numeric($value)) {
            if ($value < $rules['min']) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' debe ser mayor o igual a {$rules['min']}"
                ];
            }
        }

        if (isset($rules['max']) && is_numeric($value)) {
            if ($value > $rules['max']) {
                return [
                    'valid' => false,
                    'error' => "Campo '$field' debe ser menor o igual a {$rules['max']}"
                ];
            }
        }
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Send API error response and exit
 * Envía respuesta de error en formato JSON y termina ejecución
 *
 * @param string $error Mensaje de error
 * @param int $status_code Código HTTP de estado (default: 400)
 * @return void Termina ejecución
 */
function api_error($error, $status_code = 400) {
    http_response_code($status_code);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'error' => $error
    ]);

    exit;
}

/**
 * Send API success response and exit
 * Envía respuesta exitosa en formato JSON y termina ejecución
 *
 * @param mixed $data Datos a devolver
 * @param string|null $message Mensaje opcional
 * @return void Termina ejecución
 */
function api_success($data, $message = null) {
    http_response_code(200);
    header('Content-Type: application/json');

    $response = ['success' => true];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if (is_array($data)) {
        $response = array_merge($response, $data);
    } else {
        $response['data'] = $data;
    }

    echo json_encode($response);
    exit;
}
