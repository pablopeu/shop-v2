<?php
/**
 * Admin Router
 * Este es el ÚNICO punto de entrada para el panel de administración
 *
 * SECURITY: Este archivo define APP_ENTRY_POINT para autorizar
 * el acceso a los archivos del sistema.
 *
 * Routing: Usa query params (?page=productos-listado) en lugar de path segments
 */

// Define como punto de entrada autorizado
define('APP_ENTRY_POINT', true);
define('ADMIN_AREA', true);

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
    require_once __DIR__ . '/../../app/bootstrap.php';
}

// Require admin authentication
require_admin();

// Check session timeout
$session_lifetime = 3600; // 1 hora por defecto
if (!check_session_timeout($session_lifetime)) {
    redirect(url('/admin/login.php?timeout=1'));
}

// Obtener la página solicitada desde query param
$page = $_GET['page'] ?? 'index';

// Sanitizar el nombre de la página (solo alfanuméricos y guiones)
$page = preg_replace('/[^a-z0-9\-]/', '', strtolower($page));

// Mapeo de páginas disponibles
$pages_map = [
    'index' => 'pages/admin/index.php',
    
    // Productos
    'productos-listado' => 'pages/admin/productos-listado.php',
    'productos-nuevo' => 'pages/admin/productos-nuevo.php',
    'productos-editar' => 'pages/admin/productos-editar.php',
    'productos-archivados' => 'pages/admin/productos-archivados.php',
    'productos' => 'pages/admin/productos.php',
    
    // Ventas
    'ventas' => 'pages/admin/ventas.php',
    'archivo-ventas' => 'pages/admin/archivo-ventas.php',
    'reviews-listado' => 'pages/admin/reviews-listado.php',
    
    // Envíos
    'envios-pendientes' => 'pages/admin/envios-pendientes.php',
    'envios-archivo' => 'pages/admin/envios-archivo.php',
    'shipping-list' => 'pages/admin/shipping-list.php',
    
    // Promociones
    'promociones-listado' => 'pages/admin/promociones-listado.php',
    'promociones-nuevo' => 'pages/admin/promociones-nuevo.php',
    'promociones-editar' => 'pages/admin/promociones-editar.php',
    'promociones-archivados' => 'pages/admin/promociones-archivados.php',
    
    // Cupones
    'cupones-listado' => 'pages/admin/cupones-listado.php',
    'cupones-nuevo' => 'pages/admin/cupones-nuevo.php',
    'cupones-editar' => 'pages/admin/cupones-editar.php',
    'cupones-archivados' => 'pages/admin/cupones-archivados.php',
    
    // Notificaciones
    'notificaciones' => 'pages/admin/notificaciones.php',

    // Medios de Pago
    'config-payment' => 'pages/admin/config-payment.php',
    'reprocesar-pago-mp' => 'pages/admin/reprocesar-pago-mp.php',
    'verificar-pago-mp' => 'pages/admin/verificar-pago-mp.php',
    
    // Analytics
    'config-analytics' => 'pages/admin/config-analytics.php',
    
    // Configuración
    'config-sistema' => 'pages/admin/config-sistema.php',
    'config-sitio' => 'pages/admin/config-sitio.php',
    'config-rutas-sistema' => 'pages/admin/config-rutas-sistema.php',
    'config-moneda' => 'pages/admin/config-moneda.php',
    'config-backup' => 'pages/admin/config-backup.php',
    'config-themes' => 'pages/admin/config-themes.php',
    'generador-themes' => 'pages/admin/generador-themes.php',
    'config-hero' => 'pages/admin/config-hero.php',
    'config-carrusel' => 'pages/admin/config-carrusel.php',
    'config-footer' => 'pages/admin/config-footer.php',
    'config-dashboard' => 'pages/admin/config-dashboard.php',
    'config-productos-heading' => 'pages/admin/config-productos-heading.php',
    'config-shipping' => 'pages/admin/config-shipping.php',
    
    // Mantenimiento
    'config-mantenimiento' => 'pages/admin/config-mantenimiento.php',
    'config-limpieza-imagenes' => 'pages/admin/config-limpieza-imagenes.php',
];

// Verificar si la página existe en el mapa
if (!isset($pages_map[$page])) {
    // Página no encontrada, redirigir al dashboard
    redirect(url('/admin/'));
    exit;
}

// Cargar la página
$page_file = APP_PATH . '/' . $pages_map[$page];

if (!file_exists($page_file)) {
    // Archivo no existe, redirigir al dashboard
    redirect(url('/admin/'));
    exit;
}

// Debug: agregar comentario HTML para verificar que se está cargando correctamente
if (isset($_GET['debug'])) {
    echo "<!-- DEBUG: Loading page: $page from file: $page_file -->\n";
}

// Incluir la página
require $page_file;
