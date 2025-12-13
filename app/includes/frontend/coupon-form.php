<?php
/**
 * Componente: Coupon Form
 *
 * Formulario para aplicar cupones de descuento
 * Usado en: carrito.php, checkout-new.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza un formulario de cupón
 *
 * @param array $options Opciones de visualización:
 *   - 'show_note' (bool) Mostrar nota informativa (default: true)
 *   - 'class' (string) Clase CSS adicional (default: '')
 */
function render_coupon_form($options = []) {
    $defaults = [
        'show_note' => true,
        'class' => ''
    ];

    $options = array_merge($defaults, $options);

    $class_attr = $options['class'] ? ' ' . $options['class'] : '';
    ?>

    <div class="coupon-section<?php echo $class_attr; ?>">
        <div class="coupon-input">
            <input type="text"
                   id="couponCode"
                   placeholder="Código de cupón"
                   aria-label="Código de cupón">
            <button data-action="applyCoupon">Aplicar</button>
        </div>

        <?php if ($options['show_note']): ?>
        <small style="display: block; color: #666; font-size: 0.85rem; margin-top: 0.5rem;">
            Solo se puede aplicar un cupón por compra
        </small>
        <?php endif; ?>

        <div id="couponApplied" style="display: none;"></div>
    </div>

    <?php
}
