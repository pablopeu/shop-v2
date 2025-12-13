<?php
/**
 * Admin Header Component
 * Top bar with page title and action buttons
 *
 * Cargado por: app/pages/admin/*.php
 * Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
 */

if (!isset($page_title)) {
    $page_title = 'Panel de Administración';
}

// Get current user info if available
$username = $_SESSION['username'] ?? 'Admin';
?>

<style nonce="<?= csp_nonce() ?>">
    .admin-topbar {
        background: white;
        border-bottom: 1px solid #e0e0e0;
        padding: 15px 20px;
        margin: -15px -20px 20px -20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .admin-topbar-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .admin-topbar-top {
        display: none;
    }

    .admin-logo {
        display: none;
    }

    /* Show hamburger button in mobile only */
    @media (max-width: 768px) {
        .admin-topbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
    }

    .admin-topbar h1 {
        font-size: 22px;
        color: #2c3e50;
        margin: 0;
    }

    /* Hamburger Menu Button */
    .hamburger-btn {
        display: none;
        background: #2c3e50;
        border: none;
        color: white;
        padding: 10px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 20px;
        line-height: 1;
        transition: all 0.3s;
    }

    .hamburger-btn:hover {
        background: #34495e;
    }

    .hamburger-btn:active {
        transform: scale(0.95);
    }

    @media (max-width: 768px) {
        .hamburger-btn {
            display: block;
        }
    }

    .admin-topbar-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .admin-topbar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
        background: #f8f9fa;
        border-radius: 6px;
        font-size: 14px;
        color: #2c3e50;
    }

    .admin-topbar-user .username {
        font-weight: 600;
    }

    .admin-topbar .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        cursor: pointer;
        border: none;
        transition: all 0.3s;
    }

    .admin-topbar .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .admin-topbar .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .admin-topbar .btn-logout {
        background: #e74c3c;
        color: white;
    }

    .admin-topbar .btn-logout:hover {
        background: #c0392b;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    }

    /* Tablet layout: una sola fila horizontal con todos los elementos */
    @media (max-width: 1024px) and (min-width: 769px) {
        /* Contenedor principal en fila */
        .admin-topbar {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            padding: 15px 20px !important;
            gap: 10px !important;
        }

        /* Logo sin width 100% */
        .admin-topbar-top {
            display: flex !important;
            align-items: center !important;
            order: 1 !important;
            flex-shrink: 0 !important;
            width: auto !important;
        }

        .admin-logo {
            display: block !important;
            max-height: 55px !important;
            height: auto !important;
            max-width: 140px !important;
            object-fit: contain !important;
        }

        /* Ocultar el hamburger dentro de admin-topbar-top */
        .admin-topbar-top .hamburger-btn {
            display: none !important;
        }

        /* Título flexible */
        .admin-topbar-left {
            display: flex !important;
            order: 2 !important;
            flex: 1 !important;
            justify-content: flex-start !important;
            padding: 0 !important;
            margin-left: 10px !important;
        }

        .admin-topbar-left h1 {
            font-size: 15px !important;
            text-align: left !important;
            font-weight: 600 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        /* Acciones: usuario + botones */
        .admin-topbar-actions {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            gap: 8px !important;
            order: 3 !important;
            flex-shrink: 0 !important;
            position: relative !important;
        }

        /* Usuario visible */
        .admin-topbar-user {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 6px 10px !important;
            background: #f8f9fa !important;
            border-radius: 5px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
            flex-shrink: 0 !important;
        }

        /* Botones visibles */
        .admin-topbar-buttons {
            display: flex !important;
            gap: 6px !important;
            flex-shrink: 0 !important;
        }

        .admin-topbar-buttons .btn {
            padding: 7px 10px !important;
            font-size: 11px !important;
            border-radius: 5px !important;
            white-space: nowrap !important;
            flex-shrink: 0 !important;
        }

        /* Hamburger pseudo-elemento al final */
        .admin-topbar-actions::after {
            content: '☰' !important;
            display: block !important;
            background: #2c3e50 !important;
            color: white !important;
            padding: 8px 12px !important;
            border-radius: 5px !important;
            font-size: 18px !important;
            cursor: pointer !important;
            margin-left: 8px !important;
            transition: all 0.3s !important;
            flex-shrink: 0 !important;
            user-select: none !important;
        }

        .admin-topbar-actions:hover::after {
            background: #34495e !important;
        }
    }

    /* Mobile layout: reorganize completely */
    @media (max-width: 768px) {
        .admin-topbar {
            flex-direction: column;
            align-items: stretch;
            padding: 12px 15px;
            gap: 12px;
        }

        .admin-logo {
            display: block;
            max-height: 40px;
            height: auto;
            max-width: 120px;
            object-fit: contain;
        }

        .admin-topbar-left {
            order: 2;
            width: 100%;
            justify-content: center;
        }

        .admin-topbar h1 {
            font-size: 16px;
            text-align: center;
        }

        /* Bottom row: Actions */
        .admin-topbar-actions {
            order: 3;
            flex-direction: column;
            width: 100%;
            gap: 8px;
        }

        .admin-topbar-user {
            width: 100%;
            justify-content: center;
            padding: 10px;
        }

        /* Buttons row */
        .admin-topbar-buttons {
            display: flex;
            gap: 8px;
            width: 100%;
        }

        .admin-topbar .btn {
            flex: 1;
            text-align: center;
            padding: 12px;
        }

        .hamburger-btn {
            flex-shrink: 0;
        }
    }
</style>

<script nonce="<?= csp_nonce() ?>">
// Hacer el pseudo-elemento hamburger clickeable en tablet
document.addEventListener('DOMContentLoaded', function() {
    if (window.matchMedia('(min-width: 769px) and (max-width: 1024px)').matches) {
        const actions = document.querySelector('.admin-topbar-actions');
        if (actions) {
            actions.addEventListener('click', function(e) {
                // Detectar si el click fue en el área del pseudo-elemento (últimos 50px a la derecha)
                const rect = this.getBoundingClientRect();
                const clickX = e.clientX - rect.left;
                if (clickX > rect.width - 50) {
                    if (typeof toggleSidebar === 'function') {
                        toggleSidebar();
                    }
                }
            });
        }
    }
});
</script>

<div class="admin-topbar">
    <!-- Top row: Logo + Burger (mobile only) -->
    <div class="admin-topbar-top">
        <?php if (!empty($site_config['logo']['path'])): ?>
            <img src="<?php echo htmlspecialchars(url($site_config['logo']['path'])); ?>"
                 alt="<?php echo htmlspecialchars($site_config['logo']['alt'] ?? $site_config['site_name']); ?>"
                 class="admin-logo">
        <?php else: ?>
            <div class="admin-logo"></div>
        <?php endif; ?>
        <button class="hamburger-btn" data-action="toggleSidebar" aria-label="Toggle menu">
            ☰
        </button>
    </div>

    <!-- Page title -->
    <div class="admin-topbar-left">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </div>

    <!-- Actions -->
    <div class="admin-topbar-actions">
        <div class="admin-topbar-user">
            <span>👤</span>
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
        </div>
        <div class="admin-topbar-buttons">
            <a href="<?php echo url('/'); ?>" class="btn btn-secondary" target="_blank">🌐 Ver Sitio</a>
            <a href="<?php echo url('/admin/logout.php'); ?>" class="btn btn-logout">🚪 Cerrar Sesión</a>
        </div>
    </div>
</div>
