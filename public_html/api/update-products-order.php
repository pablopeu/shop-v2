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
 * API Endpoint - Update Products Display Order
 */

// Error handling - catch all errors and return JSON
try {
    // Functions already loaded by bootstrap
    // Products already loaded by bootstrap
    // Auth already loaded by bootstrap
    // Session already started by bootstrap

    // Check admin authentication
    if (!is_admin_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Get the JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }

    if (!isset($data['product_order']) || !is_array($data['product_order'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Datos inválidos: se requiere product_order array']);
        exit;
    }

    // Update the products order
    $result = update_products_display_order($data['product_order']);

    // Log the action
    if ($result['success']) {
        log_admin_action('update_products_order', $_SESSION['username'], [
            'products_count' => count($data['product_order'])
        ]);
    }

    // Return the result
    header('Content-Type: application/json');
    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error fatal: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
