<?php
/**
 * Main Entry Point
 * Este es el ÚNICO punto de entrada público para el frontend
 *
 * SECURITY: Este archivo define APP_ENTRY_POINT para autorizar
 * el acceso a los archivos del sistema.
 */

// Define como punto de entrada autorizado
define('APP_ENTRY_POINT', true);

// Bootstrap de la aplicación
// Cargar configuración de ruta al bootstrap (generada por instalador)
$bootstrap_config = __DIR__ . '/bootstrap_path.php';
if (file_exists($bootstrap_config)) {
    require_once $bootstrap_config;
    if (defined('BOOTSTRAP_PATH') && file_exists(BOOTSTRAP_PATH)) {
        require_once BOOTSTRAP_PATH;
    } else {
        die('Bootstrap file not found. Please check your installation.');
    }
} else {
    // Fallback para desarrollo (estructura relativa)
    require_once __DIR__ . '/../app/bootstrap.php';
}

// Obtener la ruta desde REQUEST_URI (FallbackResource mode)
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove base_path prefix dynamically (set by installer)
$base_path = $config['base_path'] ?? '';
$route = parse_url($request_uri, PHP_URL_PATH);

// Remove base_path prefix if it exists and is not root
if ($base_path && $base_path !== '/' && $base_path !== '') {
    $pattern = '#^' . preg_quote($base_path, '#') . '#';
    $route = preg_replace($pattern, '', $route);
}

$route = $route ?: '/';

// Router principal
$router = new Router();

// Definir rutas públicas
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

// Ejecutar router
$router->dispatch($route);
