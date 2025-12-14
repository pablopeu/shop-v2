<?php
/**
 * Admin Sidebar Component with Submenu
 *
 * Cargado por: app/pages/admin/*.php
 * Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
 */

if (!isset($site_config)) {
    $site_config = read_json(APP_PATH . '/config/site.json');
}

// Usar ?page= para detección de página activa
$current_page = $_GET['page'] ?? 'index';
?>

<style nonce="<?= csp_nonce() ?>">
    /* Sidebar Styles */
    .sidebar {
        background: #2c3e50;
        color: white;
        padding: 20px 0;
        width: 260px;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding-bottom: 60px;
        z-index: 1000;
        transition: transform 0.3s ease;
        -webkit-overflow-scrolling: touch;
    }

    .sidebar::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.2);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 4px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.5);
    }

    /* Mobile Sidebar */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }
    }

    .sidebar-header {
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 20px;
    }

    .sidebar-header h2 {
        font-size: 20px;
        margin-bottom: 5px;
    }

    .sidebar-header p {
        font-size: 13px;
        opacity: 0.7;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li {
        margin-bottom: 0;
    }

    .sidebar-menu a,
    .sidebar-menu .menu-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        cursor: pointer;
    }

    .sidebar-menu a:hover,
    .sidebar-menu .menu-item:hover,
    .sidebar-menu a.active {
        background: rgba(255,255,255,0.1);
        color: white;
    }

    .sidebar-menu a.active {
        border-left: 3px solid #3498db;
    }

    /* Submenu Styles */
    .submenu {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        background: rgba(0,0,0,0.2);
    }

    .submenu.open {
        max-height: 500px;
    }

    .submenu li a {
        padding: 10px 20px 10px 45px;
        font-size: 14px;
        border-left: 3px solid transparent;
    }

    .submenu li a:hover {
        background: rgba(255,255,255,0.05);
        border-left-color: #3498db;
    }

    .submenu li a.active {
        background: rgba(255,255,255,0.1);
        border-left-color: #3498db;
        color: white;
    }

    .menu-arrow {
        transition: transform 0.3s;
        font-size: 12px;
    }

    .menu-arrow.rotated {
        transform: rotate(90deg);
    }

    /* Nested Submenu Styles */
    .submenu .submenu {
        background: rgba(0,0,0,0.3);
        max-height: 0;
    }

    .submenu .submenu.open {
        max-height: 400px;
    }

    .submenu .submenu li a {
        padding-left: 65px;
        font-size: 13px;
    }

    .submenu .menu-item {
        padding: 10px 20px 10px 45px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: rgba(255,255,255,0.8);
    }

    .submenu .menu-item:hover {
        background: rgba(255,255,255,0.05);
        color: white;
    }

    /* Config Container - Items at same level as Configuracion */
    .config-container > li > .menu-item,
    .config-container > li > a {
        padding: 12px 20px !important;
        font-size: 16px;
    }

    /* Sidebar Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    @media (max-width: 1024px) {
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
    }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2><?php echo htmlspecialchars($site_config['site_name']); ?></h2>
        <p>Panel de Administración</p>
    </div>

    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li>
            <a href="<?php echo url('/admin/'); ?>" class="<?php echo $current_page === 'index' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
        </li>

        <!-- Productos -->
        <li>
            <div class="menu-item" data-action="toggleSubmenu" data-menu-id="productos" data-redirect-url="<?php echo url('/admin/?page=productos-listado'); ?>">
                <span>📦 Productos</span>
                <span class="menu-arrow" id="arrow-productos">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['productos-listado', 'productos-nuevo', 'productos-editar', 'productos-archivados']) ? 'open' : ''; ?>"
                id="submenu-productos">
                <li>
                    <a href="<?php echo url('/admin/?page=productos-listado'); ?>"
                       class="<?php echo $current_page === 'productos-listado' ? 'active' : ''; ?>">
                        📋 Listado de Productos
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/?page=productos-nuevo'); ?>"
                       class="<?php echo $current_page === 'productos-nuevo' ? 'active' : ''; ?>">
                        ➕ Agregar Producto
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/?page=productos-archivados'); ?>"
                       class="<?php echo $current_page === 'productos-archivados' ? 'active' : ''; ?>">
                        📦 Productos Archivados
                    </a>
                </li>
            </ul>
        </li>

        <!-- Ventas -->
        <li>
            <div class="menu-item" data-action="toggleSubmenu" data-menu-id="ventas" data-redirect-url="<?php echo url('/admin/?page=ventas'); ?>">
                <span>💰 Ventas</span>
                <span class="menu-arrow" id="arrow-ventas">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['ventas', 'archivo-ventas']) ? 'open' : ''; ?>"
                id="submenu-ventas">
                <li>
                    <a href="<?php echo url('/admin/?page=ventas'); ?>"
                       class="<?php echo $current_page === 'ventas' ? 'active' : ''; ?>">
                        📋 Gestión de Ventas
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/?page=archivo-ventas'); ?>"
                       class="<?php echo $current_page === 'archivo-ventas' ? 'active' : ''; ?>">
                        📦 Archivo de Ventas
                    </a>
                </li>
            </ul>
        </li>

        <!-- Envíos -->
        <li>
            <div class="menu-item" data-action="toggleSubmenu" data-menu-id="envios" data-redirect-url="<?php echo url('/admin/?page=envios-pendientes'); ?>">
                <span>📦 Envíos</span>
                <span class="menu-arrow" id="arrow-envios">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['envios-pendientes', 'envios-archivo']) ? 'open' : ''; ?>"
                id="submenu-envios">
                <li>
                    <a href="<?php echo url('/admin/?page=envios-pendientes'); ?>"
                       class="<?php echo $current_page === 'envios-pendientes' ? 'active' : ''; ?>">
                        📋 Gestión de envíos
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/?page=envios-archivo'); ?>"
                       class="<?php echo $current_page === 'envios-archivo' ? 'active' : ''; ?>">
                        📦 Archivo
                    </a>
                </li>
            </ul>
        </li>

        <!-- Promociones y Cupones -->
        <li>
            <div class="menu-item" data-action="toggleSubmenu" data-menu-id="promociones-cupones">
                <span>🎯 Promociones y Cupones</span>
                <span class="menu-arrow" id="arrow-promociones-cupones">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['promociones-listado', 'promociones-nuevo', 'promociones-editar', 'promociones-archivados', 'cupones-listado', 'cupones-nuevo', 'cupones-editar', 'cupones-archivados']) ? 'open' : ''; ?>"
                id="submenu-promociones-cupones">
                <li>
                    <div class="menu-item" data-action="toggleSubmenu" data-menu-id="promociones">
                        <span>🎁 Promociones</span>
                        <span class="menu-arrow" id="arrow-promociones">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['promociones-listado', 'promociones-nuevo', 'promociones-editar', 'promociones-archivados']) ? 'open' : ''; ?>"
                        id="submenu-promociones">
                        <li>
                            <a href="<?php echo url('/admin/?page=promociones-listado'); ?>"
                               class="<?php echo $current_page === 'promociones-listado' ? 'active' : ''; ?>">
                                📋 Listado de Promociones
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=promociones-archivados'); ?>"
                               class="<?php echo $current_page === 'promociones-archivados' ? 'active' : ''; ?>">
                                📦 Promociones Archivadas
                            </a>
                        </li>
                    </ul>
                </li>
                <li>
                    <div class="menu-item" data-action="toggleSubmenu" data-menu-id="cupones">
                        <span>🎫 Cupones</span>
                        <span class="menu-arrow" id="arrow-cupones">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['cupones-listado', 'cupones-nuevo', 'cupones-editar', 'cupones-archivados']) ? 'open' : ''; ?>"
                        id="submenu-cupones">
                        <li>
                            <a href="<?php echo url('/admin/?page=cupones-listado'); ?>"
                               class="<?php echo $current_page === 'cupones-listado' ? 'active' : ''; ?>">
                                📋 Listado de Cupones
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=cupones-archivados'); ?>"
                               class="<?php echo $current_page === 'cupones-archivados' ? 'active' : ''; ?>">
                                📦 Cupones Archivados
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </li>

        <!-- Configuracion -->
        <li>
            <div class="menu-item" data-action="toggleSubmenu" data-menu-id="configuraciones">
                <span>⚙️ Configuracion</span>
                <span class="menu-arrow" id="arrow-configuraciones">▶</span>
            </div>
            <ul class="submenu config-container <?php echo in_array($current_page, ['config-sistema', 'config-rutas-sistema', 'config-backup', 'config-sitio', 'config-dashboard', 'config-themes', 'generador-themes', 'config-hero', 'config-carrusel', 'config-footer', 'config-payment', 'config-moneda', 'reprocesar-pago-mp', 'notificaciones', 'config-analytics']) ? 'open' : ''; ?>" id="submenu-configuraciones">
                <!-- Sistema -->
                <li>
                    <div class="menu-item" data-action="toggleSubmenu" data-menu-id="config-sistema-group">
                        <span>🔧 Sistema</span>
                        <span class="menu-arrow" id="arrow-config-sistema-group">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['config-sistema', 'config-rutas-sistema', 'config-backup']) ? 'open' : ''; ?>"
                        id="submenu-config-sistema-group">
                        <li>
                            <a href="<?php echo url('/admin/?page=config-sistema'); ?>"
                               class="<?php echo $current_page === 'config-sistema' ? 'active' : ''; ?>">
                                🔐 Credenciales del Sistema
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-rutas-sistema'); ?>"
                               class="<?php echo $current_page === 'config-rutas-sistema' ? 'active' : ''; ?>">
                                🗺️ Rutas del Sistema
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-backup'); ?>"
                               class="<?php echo $current_page === 'config-backup' ? 'active' : ''; ?>">
                                💾 Backup
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- General -->
                <li>
                    <div class="menu-item" data-action="toggleSubmenu" data-menu-id="config-general-group">
                        <span>🌐 General</span>
                        <span class="menu-arrow" id="arrow-config-general-group">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['config-sitio', 'config-dashboard']) ? 'open' : ''; ?>"
                        id="submenu-config-general-group">
                        <li>
                            <a href="<?php echo url('/admin/?page=config-sitio'); ?>"
                               class="<?php echo $current_page === 'config-sitio' ? 'active' : ''; ?>">
                                📄 Información del Sitio
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-dashboard'); ?>"
                               class="<?php echo $current_page === 'config-dashboard' ? 'active' : ''; ?>">
                                📊 Dashboard
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- Apariencia -->
                <li>
                    <div class="menu-item" data-action="toggleSubmenu" data-menu-id="config-visuales">
                        <span>🎨 Apariencia</span>
                        <span class="menu-arrow" id="arrow-config-visuales">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['config-themes', 'generador-themes', 'config-hero', 'config-carrusel', 'config-footer']) ? 'open' : ''; ?>"
                        id="submenu-config-visuales">
                        <li>
                            <a href="<?php echo url('/admin/?page=config-themes'); ?>"
                               class="<?php echo $current_page === 'config-themes' ? 'active' : ''; ?>">
                                🎨 Themes
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=generador-themes'); ?>"
                               class="<?php echo $current_page === 'generador-themes' ? 'active' : ''; ?>">
                                ✨ Generador de Themes
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-hero'); ?>"
                               class="<?php echo $current_page === 'config-hero' ? 'active' : ''; ?>">
                                🖼️ Hero Principal
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-carrusel'); ?>"
                               class="<?php echo $current_page === 'config-carrusel' ? 'active' : ''; ?>">
                                🎠 Carrusel
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-footer'); ?>"
                               class="<?php echo $current_page === 'config-footer' ? 'active' : ''; ?>">
                                🦶 Footer
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- Medios de Pago -->
                <li>
                    <div class="menu-item" data-action="toggleSubmenu" data-menu-id="payment">
                        <span>💳 Medios de Pago</span>
                        <span class="menu-arrow" id="arrow-payment">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['config-payment', 'config-moneda', 'reprocesar-pago-mp']) ? 'open' : ''; ?>"
                        id="submenu-payment">
                        <li>
                            <a href="<?php echo url('/admin/?page=config-payment'); ?>"
                               class="<?php echo $current_page === 'config-payment' ? 'active' : ''; ?>">
                                💳 MercadoPago
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=config-moneda'); ?>"
                               class="<?php echo $current_page === 'config-moneda' ? 'active' : ''; ?>">
                                💱 Moneda y Cambio
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo url('/admin/?page=reprocesar-pago-mp'); ?>"
                               class="<?php echo $current_page === 'reprocesar-pago-mp' ? 'active' : ''; ?>">
                                🔄 Reprocesar Pagos
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- Email y Notificaciones -->
                <li>
                    <a href="<?php echo url('/admin/?page=notificaciones'); ?>"
                       class="<?php echo $current_page === 'notificaciones' ? 'active' : ''; ?>">
                        🔔 Email y Notificaciones
                    </a>
                </li>
                <!-- Tracking & Analytics -->
                <li>
                    <a href="<?php echo url('/admin/?page=config-analytics'); ?>"
                       class="<?php echo $current_page === 'config-analytics' ? 'active' : ''; ?>">
                        📊 Tracking & Analytics
                    </a>
                </li>
            </ul>
        </li>

        <!-- Mantenimiento -->
        <li>
            <div class="menu-item" data-action="toggleSubmenu" data-menu-id="mantenimiento">
                <span>🚧 Mantenimiento</span>
                <span class="menu-arrow" id="arrow-mantenimiento">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['config-mantenimiento', 'config-limpieza-imagenes']) ? 'open' : ''; ?>"
                id="submenu-mantenimiento">
                <li>
                    <a href="<?php echo url('/admin/?page=config-mantenimiento'); ?>"
                       class="<?php echo $current_page === 'config-mantenimiento' ? 'active' : ''; ?>">
                        🚧 Modo Mantenimiento
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/?page=config-limpieza-imagenes'); ?>"
                       class="<?php echo $current_page === 'config-limpieza-imagenes' ? 'active' : ''; ?>">
                        🗑️ Limpieza de Imágenes
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script nonce="<?= csp_nonce() ?>">
    // Toggle Sidebar for Mobile
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');

        // Prevent body scroll when sidebar is open
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Close sidebar when clicking overlay
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }
    });

    // toggleSubmenu - Compatible con data-action
    // Puede llamarse como: toggleSubmenu(event, element, params)
    // donde params contiene: menuId, redirectUrl
    function toggleSubmenu(event, element, params) {
        const menuId = params?.menuId || (typeof event === 'string' ? event : null);
        const redirectUrl = params?.redirectUrl || (typeof arguments[1] === 'string' ? arguments[1] : null);

        if (!menuId) return;

        const submenu = document.getElementById('submenu-' + menuId);
        const arrow = document.getElementById('arrow-' + menuId);

        if (!submenu) return;

        if (submenu.classList.contains('open')) {
            submenu.classList.remove('open');
            if (arrow) arrow.classList.remove('rotated');
        } else {
            submenu.classList.add('open');
            if (arrow) arrow.classList.add('rotated');
        }

        // Redirect if URL is provided
        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    }

    // Auto-open submenu if on a submenu page
    document.addEventListener('DOMContentLoaded', function() {
        const openSubmenus = document.querySelectorAll('.submenu.open');
        openSubmenus.forEach(submenu => {
            const menuId = submenu.id.replace('submenu-', '');
            const arrow = document.getElementById('arrow-' + menuId);
            if (arrow) {
                arrow.classList.add('rotated');
            }
        });
    });
</script>

<!-- Session Monitor - Auto-redirects if session expires -->
<script nonce="<?= csp_nonce() ?>">
    // Define BASE_PATH for JavaScript to use
    <?php
    // IMPORTANT: Use $sidebar_config to avoid overwriting global $config variable
    $sidebar_config = require APP_PATH . '/config/config.php';
    if (!is_array($sidebar_config) && file_exists(APP_PATH . '/config/config.php')) {
        $sidebar_config = require APP_PATH . '/config/config.php';
    }
    $base_path = isset($sidebar_config['base_path']) ? $sidebar_config['base_path'] : '';
    ?>
    window.BASE_PATH = '<?php echo htmlspecialchars($base_path); ?>';
</script>
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/session-monitor.js'); ?>"></script>
<!-- Event Delegation System for CSP -->
<script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
