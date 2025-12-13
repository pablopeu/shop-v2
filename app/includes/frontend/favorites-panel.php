<?php
/**
 * Componente: Favorites Panel
 *
 * Panel lateral de favoritos (slide-in)
 * Usado en: home.php, producto.php, favoritos.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza el panel de favoritos
 *
 * @param array $options Opciones de visualización:
 *   - 'show_go_to_page_btn' (bool) Mostrar botón "Ir a Favoritos" (default: true)
 */
function render_favorites_panel($options = []) {
    $defaults = [
        'show_go_to_page_btn' => true
    ];

    $options = array_merge($defaults, $options);
    ?>

    <!-- Favorites Overlay -->
    <div class="cart-overlay" id="favorites-overlay" data-action="closeFavoritesPanel"></div>

    <!-- Favorites Panel -->
    <div class="cart-panel" id="favorites-panel">
        <div class="cart-panel-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg"
                     width="24"
                     height="24"
                     viewBox="0 0 24 24"
                     fill="currentColor"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round"
                     style="vertical-align: middle; margin-right: 8px;">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                Mis Favoritos
            </h2>
            <button class="cart-close" data-action="closeFavoritesPanel">&times;</button>
        </div>

        <div class="cart-panel-body" id="favorites-panel-body">
            <div class="cart-empty">No tienes favoritos</div>
        </div>

        <?php if ($options['show_go_to_page_btn']): ?>
        <div class="cart-panel-footer" id="favorites-panel-footer">
            <button data-action="goToFavoritesPage"
                    class="btn"
                    style="width: 100%; text-align: center; border: none; cursor: pointer;">
                Ir a Favoritos
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php
}
