<?php
/**
 * Componente: Product Card
 *
 * Tarjeta de producto reutilizable para grid de productos
 * Usado en: home.php, buscar.php, favoritos.php
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

/**
 * Renderiza una tarjeta de producto
 *
 * @param array $product Datos del producto
 * @param array $options Opciones de visualización:
 *   - 'show_promotion' (bool) Mostrar ribbon de promoción (default: true)
 *   - 'show_favorite_btn' (bool) Mostrar botón de favoritos (default: true)
 *   - 'show_stock' (bool) Mostrar información de stock (default: true)
 *   - 'show_pickup_badge' (bool) Mostrar badge de retiro en persona (default: true)
 *   - 'show_add_to_cart' (bool) Mostrar botón agregar al carrito (default: true)
 *   - 'currency' (string) Moneda seleccionada (default: ARS)
 */
function render_product_card($product, $options = []) {
    // Opciones por defecto
    $defaults = [
        'show_promotion' => true,
        'show_favorite_btn' => true,
        'show_stock' => true,
        'show_pickup_badge' => true,
        'show_add_to_cart' => true,
        'currency' => 'ARS'
    ];

    $options = array_merge($defaults, $options);

    // Verificar promoción activa si está habilitado
    $active_promotion = null;
    if ($options['show_promotion']) {
        $active_promotion = get_active_promotion_for_product($product['id']);
    }

    $is_out_of_stock = $product['stock'] === 0;

    // Leer configuración del theme
    $theme_config = read_json(APP_PATH . '/config/theme.json');
    $active_theme = $theme_config['active_theme'] ?? 'minimal';

    // Obtener configuración de botones en cards
    $card_buttons = 'show'; // Default
    $card_buttons_position = 'center';
    $card_buttons_spacing = 'normal';
    $card_buttons_vertical_spacing = 'normal';

    $theme_json_path = PUBLIC_PATH . "/assets/themes/{$active_theme}/theme.json";
    if (file_exists($theme_json_path)) {
        $theme_json = read_json($theme_json_path);
        $card_buttons = $theme_json['components']['cards']['buttons'] ?? 'show';
        $card_buttons_position = $theme_json['components']['cards']['buttons_position'] ?? 'center';
        $card_buttons_spacing = $theme_json['components']['cards']['buttons_spacing'] ?? 'normal';
        $card_buttons_vertical_spacing = $theme_json['components']['cards']['buttons_vertical_spacing'] ?? 'normal';
    }

    // Convertir position a CSS
    $buttons_justify = 'center';
    if ($card_buttons_position === 'left') $buttons_justify = 'flex-start';
    elseif ($card_buttons_position === 'right') $buttons_justify = 'flex-end';

    // Convertir spacing a CSS
    $buttons_gap = '8px';
    if ($card_buttons_spacing === 'compact') $buttons_gap = '4px';
    elseif ($card_buttons_spacing === 'spacious') $buttons_gap = '12px';

    // Convertir vertical spacing a CSS
    $buttons_margin_top = '10px';
    if ($card_buttons_vertical_spacing === 'compact') $buttons_margin_top = '5px';
    elseif ($card_buttons_vertical_spacing === 'spacious') $buttons_margin_top = '20px';

    // Determinar si la tarjeta completa debe ser clickeable
    // Clickeable si el theme es 'modern-compact' o si buttons está en 'hide' o 'cart_only'
    $card_clickeable = ($active_theme === 'modern-compact') ||
                       ($card_buttons === 'hide') ||
                       ($card_buttons === 'cart_only');
    ?>

    <div class="product-card" <?php if ($card_clickeable): ?>data-action="goToProduct" data-url="<?php echo url('/producto/' . urlencode($product['slug'])); ?>"<?php endif; ?>>
        <?php if ($active_promotion && $options['show_promotion']): ?>
        <!-- Promotion Ribbon -->
        <div class="promotion-ribbon">
            <span>-<?php echo $active_promotion['value']; ?><?php echo $active_promotion['type'] === 'percentage' ? '%' : '$'; ?> OFF</span>
        </div>
        <?php endif; ?>

        <div class="product-image" data-action="goToProduct" data-url="<?php echo url('/producto/' . urlencode($product['slug'])); ?>">
            <?php if (!empty($product['thumbnail'])): ?>
                <img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                Sin imagen
            <?php endif; ?>

            <?php if ($options['show_favorite_btn']): ?>
            <button class="favorite-heart-card"
                    data-action="toggleFavorite"
                    data-product-id="<?php echo $product['id']; ?>"
                    data-stop-propagation="true"
                    id="favorite-btn-<?php echo $product['id']; ?>"
                    title="Agregar a favoritos">
                <i class="far fa-heart"></i>
            </button>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>

            <div class="product-price">
                <?php echo format_product_price($product, $options['currency']); ?>
            </div>

            <?php if ($options['show_stock']): ?>
            <div class="product-stock">
                <?php if ($product['stock'] === 0): ?>
                    <span class="stock-badge stock-out">Sin stock</span>
                <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                    <span class="stock-badge stock-low">¡Últimas unidades!</span>
                <?php else: ?>
                    Stock disponible: <?php echo $product['stock']; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($options['show_pickup_badge'] && !empty($product['pickup_only'])): ?>
            <div class="pickup-only-badge">
                🏪 Solo retiro en persona
            </div>
            <?php endif; ?>

            <?php if ($card_buttons !== 'hide'): ?>
            <div class="product-buttons" style="display: flex; gap: <?php echo $buttons_gap; ?>; justify-content: <?php echo $buttons_justify; ?>; margin-top: <?php echo $buttons_margin_top; ?>;">
                <?php if ($card_buttons === 'show'): ?>
                <!-- Mostrar ambos botones -->
                <button class="btn btn-secondary"
                        data-action="goToProduct"
                        data-url="<?php echo url('/producto/' . urlencode($product['slug'])); ?>"
                        <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>
                    Ver detalle
                </button>
                <?php endif; ?>

                <?php if ($options['show_add_to_cart'] && ($card_buttons === 'show' || $card_buttons === 'cart_only')): ?>
                <button class="btn btn-add-cart"
                        data-action="addToCart"
                        data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                        <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>
                    <?php echo ($card_buttons === 'cart_only') ? '🛒 Agregar al Carrito' : '🛒 Agregar'; ?>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
}
