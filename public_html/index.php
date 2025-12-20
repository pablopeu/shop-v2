<?php
error_log("=== INDEX.PHP START ===");

/**
 * Main Entry Point
 * Este es el ÚNICO punto de entrada público para el frontend
 *
 * SECURITY: Este archivo define APP_ENTRY_POINT para autorizar
 * el acceso a los archivos del sistema.
 */

error_log("INDEX.PHP - Line 10: About to define APP_ENTRY_POINT");

// Define como punto de entrada autorizado
define('APP_ENTRY_POINT', true);

error_log("INDEX.PHP - Line 15: APP_ENTRY_POINT defined");

// Bootstrap de la aplicación
// Detectar entorno y cargar bootstrap apropiadamente
error_log("INDEX.PHP - Line 19: About to check bootstrap paths");

if (file_exists('/home2/uv0023/shop-v2-app/bootstrap.php')) {
    error_log("INDEX.PHP - Line 22: Loading PRODUCTION bootstrap");
    // Producción
    require_once '/home2/uv0023/shop-v2-app/bootstrap.php';
    error_log("INDEX.PHP - Line 25: Production bootstrap loaded");
} elseif (file_exists('/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php')) {
    error_log("INDEX.PHP - Line 27: Loading TESTING bootstrap");
    // Testing local
    require_once '/home/pablo/shop-v2-local-test/shop-v2-app/bootstrap.php';
    error_log("INDEX.PHP - Line 30: Testing bootstrap loaded");
} else {
    error_log("INDEX.PHP - Line 32: Loading DEVELOPMENT bootstrap");
    // Desarrollo
    require_once __DIR__ . '/../app/bootstrap.php';
    error_log("INDEX.PHP - Line 35: Development bootstrap loaded");
}

error_log("INDEX.PHP - Line 38: Bootstrap loading complete");

// Obtener la ruta desde REQUEST_URI (FallbackResource mode)
error_log("INDEX.PHP - Line 41: About to parse REQUEST_URI");
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
error_log("INDEX.PHP - Line 43: REQUEST_URI = " . $request_uri);

// Remove base_path prefix dynamically (set by installer)
$base_path = $config['base_path'] ?? '';
$route = parse_url($request_uri, PHP_URL_PATH);

// Remove base_path prefix if it exists and is not root
if ($base_path && $base_path !== '/' && $base_path !== '') {
    $pattern = '#^' . preg_quote($base_path, '#') . '#';
    $route = preg_replace($pattern, '', $route);
    error_log("INDEX.PHP - Line 47: After removing base_path '$base_path' prefix, route = " . ($route ?: 'EMPTY'));
} else {
    error_log("INDEX.PHP - Line 47: No base_path to remove (base_path = '$base_path'), route = " . ($route ?: 'EMPTY'));
}

$route = $route ?: '/';
error_log("INDEX.PHP - Line 50: Final route = " . $route);

// DEBUG: Log routing info (TEMPORAL)
error_log("INDEX.PHP - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));
error_log("INDEX.PHP - Calculated route: " . $route);
error_log("INDEX.PHP - QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'NOT SET'));

error_log("INDEX.PHP - Line 57: About to create Router");

// Router principal
$router = new Router();

error_log("INDEX.PHP - Line 62: Router created");

// Definir rutas públicas
error_log("INDEX.PHP - Line 67: Defining routes");
$router->get('/', 'pages/frontend/home.php');
$router->get('/producto/:slug', 'pages/frontend/producto.php');
$router->get('/buscar', 'pages/frontend/buscar.php');
$router->get('/carrito', 'pages/frontend/carrito.php');
$router->get('/checkout', 'pages/frontend/checkout-new.php');
$router->post('/checkout', 'pages/frontend/checkout-new.php');
$router->get('/checkout-return', 'pages/frontend/checkout-return.php');
$router->get('/favoritos', 'pages/frontend/favoritos.php');
$router->get('/track', 'pages/frontend/track.php');
$router->post('/track', 'pages/frontend/track.php'); // POST para búsqueda de orden
$router->get('/pedido', 'pages/frontend/pedido.php');
$router->get('/gracias', 'pages/frontend/gracias.php');
$router->get('/pendiente', 'pages/frontend/pendiente.php');
$router->get('/preview', 'pages/frontend/preview.php');
error_log("INDEX.PHP - Line 80: All routes defined");

// Ejecutar router
error_log("INDEX.PHP - Line 83: About to dispatch route: " . $route);
$router->dispatch($route);
error_log("INDEX.PHP - Line 85: Router dispatch completed");
error_log("=== INDEX.PHP END ===");
