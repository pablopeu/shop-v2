<?php
/**
 * Frontend Header Component
 * Header reutilizable para todas las páginas del frontend
 *
 * Variables esperadas:
 * - $site_config: Configuración del sitio
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Si no se pasó site_config, cargarlo
if (!isset($site_config)) {
    $site_config = read_json(APP_PATH . '/config/site.json');
}
?>
<header class="header">
    <div class="header-content">
        <a href="<?php echo url('/'); ?>" class="logo"><?php render_site_logo($site_config); ?></a>

        <!-- Search Form -->
        <form method="GET" action="<?php echo url('/buscar'); ?>" class="header-search-form">
            <div class="header-search-box">
                <input type="text"
                       name="q"
                       placeholder="Buscar productos..."
                       class="header-search-input"
                       value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                       aria-label="Buscar productos">
                <button type="submit" class="header-search-btn" aria-label="Buscar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
            </div>
        </form>

        <nav class="nav">
            <a href="<?php echo url('/'); ?>">Inicio</a>
            <a href="#" data-action="openFavoritesPanel">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                Favoritos
            </a>
            <a href="<?php echo url('/track'); ?>">
                <span class="nav-icon">📦</span>
                Rastrear
            </a>
            <a href="#" class="cart-link" data-action="openCartPanel">
                <span class="nav-icon">🛒</span>
                Carrito
                <span class="cart-badge hidden" id="cart-count">0</span>
            </a>
        </nav>
    </div>
</header>

<style nonce="<?= csp_nonce() ?>">
/* Nav Icon - Asegurar que todos los iconos estén a la izquierda */
.nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 20px;
    line-height: 1;
}

/* Header Search Styles */
.header-search-form {
    flex: 0 1 400px;
    margin: 0 20px;
}

.header-search-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.header-search-input {
    width: 100%;
    padding: 10px 45px 10px 15px;
    border: 2px solid var(--color-border, #e0e0e0);
    border-radius: 25px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: var(--color-bg-light, #f8f9fa);
}

.header-search-input:focus {
    outline: none;
    border-color: var(--color-primary, #667eea);
    background: var(--color-white, white);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
}

.header-search-btn {
    position: absolute;
    right: 5px;
    background: var(--color-primary, #667eea);
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--color-white, white);
}

.header-search-btn:hover {
    background: var(--color-primary-dark, #5568d3);
    transform: scale(1.05);
}

.header-search-btn:active {
    transform: scale(0.95);
}

.header-search-btn svg {
    width: 18px;
    height: 18px;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .header-content {
        flex-wrap: wrap;
        gap: 15px;
    }

    .header-search-form {
        order: 3;
        flex: 1 1 100%;
        margin: 0;
    }

    .logo {
        order: 1;
    }

    .nav {
        order: 2;
    }
}

@media (max-width: 768px) {
    .header-search-input {
        font-size: 13px;
        padding: 8px 40px 8px 12px;
    }

    .header-search-btn {
        width: 32px;
        height: 32px;
    }

    .header-search-btn svg {
        width: 16px;
        height: 16px;
    }
}
</style>
