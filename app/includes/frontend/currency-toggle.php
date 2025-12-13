<?php
/**
 * Componente: Currency Toggle
 *
 * Selector de moneda (ARS/USD)
 * Puede ser usado en: header-frontend.php, checkout.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza un selector de moneda
 *
 * @param array $options Opciones de visualización:
 *   - 'current_currency' (string) Moneda actual seleccionada (default: ARS)
 *   - 'style' (string) Estilo de visualización: 'buttons' o 'dropdown' (default: buttons)
 *   - 'class' (string) Clase CSS adicional (default: '')
 */
function render_currency_toggle($options = []) {
    $defaults = [
        'current_currency' => 'ARS',
        'style' => 'buttons',
        'class' => ''
    ];

    $options = array_merge($defaults, $options);

    $class_attr = $options['class'] ? ' ' . $options['class'] : '';
    ?>

    <?php if ($options['style'] === 'buttons'): ?>
    <!-- Currency Toggle - Buttons Style -->
    <div class="currency-toggle<?php echo $class_attr; ?>">
        <button class="currency-btn <?php echo $options['current_currency'] === 'ARS' ? 'active' : ''; ?>"
                data-action="switchCurrency"
                data-currency="ARS">
            ARS $
        </button>
        <button class="currency-btn <?php echo $options['current_currency'] === 'USD' ? 'active' : ''; ?>"
                data-action="switchCurrency"
                data-currency="USD">
            USD $
        </button>
    </div>

    <?php else: ?>
    <!-- Currency Toggle - Dropdown Style -->
    <div class="currency-toggle-dropdown<?php echo $class_attr; ?>">
        <select data-action="switchCurrencyDropdown" class="currency-select">
            <option value="ARS" <?php echo $options['current_currency'] === 'ARS' ? 'selected' : ''; ?>>
                🇦🇷 ARS $
            </option>
            <option value="USD" <?php echo $options['current_currency'] === 'USD' ? 'selected' : ''; ?>>
                💵 USD $
            </option>
        </select>
    </div>
    <?php endif; ?>

    <?php
}
