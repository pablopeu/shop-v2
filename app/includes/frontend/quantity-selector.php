<?php
/**
 * Componente: Quantity Selector
 *
 * Selector de cantidad con botones +/-
 * Usado en: producto.php, carrito.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza un selector de cantidad
 *
 * @param array $options Opciones de configuración:
 *   - 'id' (string) ID del input (default: 'quantity-input')
 *   - 'value' (int) Valor inicial (default: 1)
 *   - 'min' (int) Cantidad mínima (default: 1)
 *   - 'max' (int) Cantidad máxima (default: 99)
 *   - 'disabled' (bool) Deshabilitar selector (default: false)
 *   - 'class' (string) Clase CSS adicional (default: '')
 */
function render_quantity_selector($options = []) {
    $defaults = [
        'id' => 'quantity-input',
        'value' => 1,
        'min' => 1,
        'max' => 99,
        'disabled' => false,
        'class' => ''
    ];

    $options = array_merge($defaults, $options);

    $disabled_attr = $options['disabled'] ? 'disabled' : '';
    $class_attr = $options['class'] ? ' ' . $options['class'] : '';
    ?>

    <div class="quantity-selector-modern<?php echo $class_attr; ?>">
        <button class="quantity-btn-modern"
                data-action="decreaseQuantity"
                <?php echo $disabled_attr; ?>>
            <i class="fas fa-minus"></i>
        </button>

        <input type="number"
               id="<?php echo htmlspecialchars($options['id']); ?>"
               value="<?php echo intval($options['value']); ?>"
               min="<?php echo intval($options['min']); ?>"
               max="<?php echo intval($options['max']); ?>"
               <?php echo $disabled_attr; ?>>

        <button class="quantity-btn-modern"
                data-action="increaseQuantity"
                <?php echo $disabled_attr; ?>>
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <?php
}
