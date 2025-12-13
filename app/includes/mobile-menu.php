<?php
/**
 * Mobile Menu Drawer Component (HTML only)
 * JavaScript handles interaction in mobile-menu.js
 */

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 0;
    }
}
?>

<!-- Compact Header (Mobile + Scroll) -->
<div class="header-compact">
    <div class="header-content">
        <a href="<?php echo url('/'); ?>" class="logo">
            <?php
            if (isset($site_config)) {
                render_site_logo($site_config, 'compact-logo');
            }
            ?>
        </a>
        <!-- Hamburger button will be injected here by JS -->
    </div>
</div>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay"></div>

<!-- Mobile Menu Drawer -->
<div class="mobile-menu-drawer">
    <div class="mobile-menu-header">
        <div class="mobile-menu-title">Menú</div>
        <button class="mobile-menu-close" aria-label="Cerrar menú">&times;</button>
    </div>
    <nav class="mobile-menu-nav">
        <a href="<?php echo url('/'); ?>">
            <span class="mobile-menu-icon">🏠</span>
            Inicio
        </a>
        <a href="<?php echo url('/buscar'); ?>">
            <span class="mobile-menu-icon">🔍</span>
            Buscar
        </a>
        <a href="<?php echo url('/favoritos'); ?>">
            <span class="mobile-menu-icon">❤️</span>
            Favoritos
        </a>
        <a href="<?php echo url('/carrito'); ?>" id="mobile-cart-link">
            <span class="mobile-menu-icon">🛒</span>
            Carrito
            <?php if ($cart_count > 0): ?>
                <span class="mobile-menu-badge"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo url('/track'); ?>">
            <span class="mobile-menu-icon">📦</span>
            Rastreo
        </a>
    </nav>
</div>
