<?php
/**
 * Componente: Cart Panel
 *
 * Panel lateral del carrito de compras (slide-in)
 * Usado en: home.php, producto.php, carrito.php, checkout.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza el panel del carrito
 *
 * @param array $options Opciones de visualización:
 *   - 'show_checkout_btn' (bool) Mostrar botón de checkout (default: true)
 */
function render_cart_panel($options = []) {
    $defaults = [
        'show_checkout_btn' => true
    ];

    $options = array_merge($defaults, $options);
    ?>

    <!-- Cart Overlay -->
    <div class="cart-overlay" id="cart-overlay" data-action="closeCartPanel"></div>

    <!-- Cart Panel -->
    <div class="cart-panel" id="cart-panel">
        <div class="cart-panel-header">
            <h2>🛒 Tu Carrito</h2>
            <button class="cart-close" data-action="closeCartPanel">&times;</button>
        </div>

        <div class="cart-panel-body" id="cart-panel-body">
            <div class="cart-empty">Tu carrito está vacío</div>
        </div>

        <div class="cart-panel-footer hidden" id="cart-panel-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cart-total">$0.00</span>
            </div>

            <?php if ($options['show_checkout_btn']): ?>
            <button data-action="goToCheckout"
                    class="btn"
                    style="width: 100%; text-align: center; border: none; cursor: pointer;">
                Ver Carrito Completo
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php
}
