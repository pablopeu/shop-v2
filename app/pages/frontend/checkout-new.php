<?php
/**
 * Checkout Page - Nueva Pasarela Vertical
 *
 * Todos los pasos visibles verticalmente en una sola página
 * Layout de dos columnas en desktop
 * Responsive para mobile y tablet
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

require_once APP_PATH . '/includes/functions.php';
require_once APP_PATH . '/includes/products.php';
require_once APP_PATH . '/includes/orders.php';
require_once APP_PATH . '/includes/mercadopago.php';
require_once APP_PATH . '/includes/email.php';
require_once APP_PATH . '/includes/telegram.php';
require_once APP_PATH . '/includes/coupons.php';
require_once APP_PATH . '/includes/promotions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check checkout expiration (1 hour)
if (!isset($_SESSION['checkout_start_time'])) {
    // First time entering checkout - register start time
    $_SESSION['checkout_start_time'] = time();
    $_SESSION['checkout_id'] = generate_id('checkout-');
} else {
    // Already in checkout - check for expiration
    $elapsed = time() - $_SESSION['checkout_start_time'];
    if ($elapsed > 3600) { // 1 hour = 3600 seconds
        // Checkout expired - clean up and redirect
        unset($_SESSION['checkout_start_time']);
        unset($_SESSION['checkout_id']);
        unset($_SESSION['cart']);
        unset($_SESSION['applied_coupon']);
        
        $_SESSION['error'] = 'Tu sesión de checkout ha expirado (1 hora). Por favor, inicia el proceso nuevamente.';
        redirect(url('/carrito?msg=checkout_expired'));
        exit;
    }
}

// Get cart from session
$cart = $_SESSION['cart'] ?? [];

// Redirect to home if cart is empty
if (empty($cart)) {
    redirect(url('/'));
    exit;
}

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$currency_config = read_json(APP_PATH . '/config/currency.json');
$telegram_config = read_json(APP_PATH . '/config/telegram.json');
$payment_config = read_json(APP_PATH . '/config/payment.json');
$theme_config = read_json(APP_PATH . '/config/theme.json');

// Load active theme colors
$active_theme = $theme_config['active_theme'] ?? 'minimal';
$theme_json_path = PUBLIC_PATH . "/assets/themes/{$active_theme}/theme.json";
$theme_colors = [];
if (file_exists($theme_json_path)) {
    $theme_data = json_decode(file_get_contents($theme_json_path), true);
    $theme_colors = $theme_data['colors'] ?? [];
}

// Get exchange rate for USD to ARS conversion (use selling rate)
$exchange_rate = 0;
if (!empty($currency_config['api_venta']) && $currency_config['api_venta'] > 0) {
    // Use API selling rate if available
    $exchange_rate = $currency_config['api_venta'];
} elseif (!empty($currency_config['exchange_rate']) && $currency_config['exchange_rate'] > 0) {
    // Fallback to manual rate
    $exchange_rate = $currency_config['exchange_rate'];
}

// MercadoPago configuration
$payment_credentials = get_payment_credentials();
$mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
$mp_public_key = $mp_mode === 'sandbox' ?
    ($payment_credentials['mercadopago']['public_key_sandbox'] ?? '') :
    ($payment_credentials['mercadopago']['public_key_prod'] ?? '');

// Get cart items with product details
$cart_items = [];
$subtotal_ars = 0;
$subtotal_usd = 0;
$subtotal_without_promotion_ars = 0; // For coupon calculation
$subtotal_without_promotion_usd = 0;
$promotion_discount_ars = 0;
$promotion_discount_usd = 0;
$has_pickup_only = false;
$pickup_only_products = [];

foreach ($cart as $key => $value) {
    // Handle both cart formats
    if (is_array($value) && isset($value['product_id'])) {
        // New format: [0 => ['product_id' => 'xxx', 'quantity' => 1]]
        $product_id = $value['product_id'];
        $quantity = $value['quantity'];
    } else {
        // Old format: ['product_id' => quantity]
        $product_id = $key;
        $quantity = $value;
    }

    $product = get_product_by_id($product_id);

    if (!$product || !$product['active']) {
        continue;
    }

    // Check stock
    if ($product['stock'] < $quantity) {
        $_SESSION['error'] = "Stock insuficiente para {$product['name']}";
        redirect(url('/carrito'));
        exit;
    }

    // Check if pickup only
    if (isset($product['delivery_options']) && $product['delivery_options'] === 'pickup_only') {
        $has_pickup_only = true;
        $pickup_only_products[] = $product['name'];
    }

    $price_ars = $product['price_ars'];
    $price_usd = $product['price_usd'];

    // If product only has USD price, convert to ARS using exchange rate
    if ((empty($price_ars) || $price_ars == 0) && !empty($price_usd) && $price_usd > 0 && $exchange_rate > 0) {
        $price_ars = $price_usd * $exchange_rate;
    }

    // If product only has ARS price, convert to USD using exchange rate
    if ((empty($price_usd) || $price_usd == 0) && !empty($price_ars) && $price_ars > 0 && $exchange_rate > 0) {
        $price_usd = $price_ars / $exchange_rate;
    }

    // Check for active promotion
    $promotion = get_active_promotion_for_product($product_id);
    $item_promotion_discount_ars = 0;
    $item_promotion_discount_usd = 0;
    $final_price_ars = $price_ars;
    $final_price_usd = $price_usd;

    if ($promotion) {
        if ($promotion['type'] === 'percentage') {
            $item_promotion_discount_ars = ($price_ars * $promotion['value'] / 100) * $quantity;
            $item_promotion_discount_usd = ($price_usd * $promotion['value'] / 100) * $quantity;
            $final_price_ars = $price_ars - ($price_ars * $promotion['value'] / 100);
            $final_price_usd = $price_usd - ($price_usd * $promotion['value'] / 100);
        } else {
            // Fixed discount
            $item_promotion_discount_ars = $promotion['value'] * $quantity;
            $item_promotion_discount_usd = ($promotion['value'] / $exchange_rate) * $quantity;
            $final_price_ars = max(0, $price_ars - $promotion['value']);
            $final_price_usd = max(0, $price_usd - ($promotion['value'] / $exchange_rate));
        }
        $promotion_discount_ars += $item_promotion_discount_ars;
        $promotion_discount_usd += $item_promotion_discount_usd;
    }

    $item_subtotal_ars = $final_price_ars * $quantity;
    $item_subtotal_usd = $final_price_usd * $quantity;

    $cart_items[] = [
        'product_id' => $product_id,
        'name' => $product['name'],
        'thumbnail' => $product['thumbnail'] ?? '',
        'price_ars' => $price_ars,
        'price_usd' => $price_usd,
        'final_price_ars' => $final_price_ars,
        'final_price_usd' => $final_price_usd,
        'quantity' => $quantity,
        'subtotal_ars' => $item_subtotal_ars,
        'subtotal_usd' => $item_subtotal_usd,
        'promotion' => $promotion,
        'promotion_discount_ars' => $item_promotion_discount_ars,
        'promotion_discount_usd' => $item_promotion_discount_usd
    ];

    $subtotal_ars += $item_subtotal_ars;
    $subtotal_usd += $item_subtotal_usd;

    // Track subtotal without promotion (for coupon calculation)
    if (!$promotion) {
        $subtotal_without_promotion_ars += $item_subtotal_ars;
        $subtotal_without_promotion_usd += $item_subtotal_usd;
    }
}

// Handle currency selection
$checkout_currency = $_SESSION['checkout_currency'] ?? $currency_config['default_currency'] ?? 'ARS';
if (isset($_POST['switch_currency'])) {
    $checkout_currency = $_POST['currency'] === 'USD' ? 'USD' : 'ARS';
    $_SESSION['checkout_currency'] = $checkout_currency;
}

$selected_currency = $checkout_currency;

// Get coupon from session or query parameter (from cart)
if (!empty($_GET['coupon'])) {
    $_SESSION['applied_coupon'] = sanitize_input($_GET['coupon']);
    error_log("Checkout: Cupón recibido desde carrito: " . $_SESSION['applied_coupon']);
}

// Apply coupon if exists
$coupon_code = $_SESSION['applied_coupon'] ?? null;
error_log("Checkout: Cupón code: " . ($coupon_code ?? 'NULL'));
$coupon_discount_ars = 0;
$coupon_discount_usd = 0;
$coupon_discount = 0;

if ($coupon_code) {
    error_log("Checkout: Aplicando cupón. Subtotal sin promoción ARS: $subtotal_without_promotion_ars, USD: $subtotal_without_promotion_usd");

    // Apply coupon ONLY to items without promotion
    $result = apply_coupon_to_order($coupon_code, $subtotal_without_promotion_ars, $subtotal_without_promotion_usd, $cart_items);

    error_log("Checkout: Resultado de cupón - Valid: " . ($result['valid'] ? 'SI' : 'NO') . ", Descuento ARS: " . $result['discount_ars'] . ", USD: " . $result['discount_usd']);

    if ($result['valid']) {
        $coupon_discount_ars = $result['discount_ars'];
        $coupon_discount_usd = $result['discount_usd'];
        $coupon_discount = ($selected_currency === 'USD') ? $coupon_discount_usd : $coupon_discount_ars;

        // Calculate per-item coupon discount for display
        $coupon_data = $result['coupon'];
        error_log("Checkout: Procesando " . count($cart_items) . " items para aplicar cupón tipo: " . $coupon_data['type']);

        foreach ($cart_items as $idx => $item) {
            // Only apply coupon to items without promotion
            if (!$item['promotion']) {
                error_log("Checkout: Item " . $item['product_id'] . " SIN promoción - aplicando cupón");

                if ($coupon_data['type'] === 'percentage') {
                    // Apply percentage discount
                    $item_coupon_discount_ars = $item['price_ars'] * ($coupon_data['value'] / 100);
                    $item_coupon_discount_usd = $item['price_usd'] * ($coupon_data['value'] / 100);
                    $cart_items[$idx]['coupon_price_ars'] = $item['price_ars'] - $item_coupon_discount_ars;
                    $cart_items[$idx]['coupon_price_usd'] = $item['price_usd'] - $item_coupon_discount_usd;

                    error_log("Checkout: Cupón porcentual - Precio original ARS: {$item['price_ars']}, Precio con cupón: {$cart_items[$idx]['coupon_price_ars']}");
                } else {
                    // Fixed discount - distribute proportionally
                    if ($subtotal_without_promotion_ars > 0) {
                        $proportion = $item['subtotal_ars'] / $subtotal_without_promotion_ars;
                        $item_coupon_discount_ars = $coupon_discount_ars * $proportion;
                        $item_coupon_discount_usd = $coupon_discount_usd * $proportion;
                        $per_unit_discount_ars = $item_coupon_discount_ars / $item['quantity'];
                        $per_unit_discount_usd = $item_coupon_discount_usd / $item['quantity'];
                        $cart_items[$idx]['coupon_price_ars'] = $item['price_ars'] - $per_unit_discount_ars;
                        $cart_items[$idx]['coupon_price_usd'] = $item['price_usd'] - $per_unit_discount_usd;

                        error_log("Checkout: Cupón fijo - Precio original ARS: {$item['price_ars']}, Precio con cupón: {$cart_items[$idx]['coupon_price_ars']}");
                    }
                }
                $cart_items[$idx]['has_coupon'] = true;
            } else {
                error_log("Checkout: Item " . $item['product_id'] . " CON promoción - no se aplica cupón");
            }
        }
    }
}

// Calculate totals
$total_ars = $subtotal_ars - $coupon_discount_ars;
$total_usd = $subtotal_usd - $coupon_discount_usd;
$total = ($selected_currency === 'USD') ? $total_usd : $total_ars;

// Handle form submission
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Clean output buffer for AJAX requests to ensure clean JSON response
    if ($is_ajax) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
    }

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido';
    }

    // Get form data
    $customer_name = sanitize_input($_POST['customer_name'] ?? '');
    $customer_email = sanitize_input($_POST['customer_email'] ?? '');
    $country_code = sanitize_input($_POST['country_code'] ?? '+54');
    $customer_phone = sanitize_input($_POST['customer_phone'] ?? '');
    $contact_preference = 'email'; // Siempre email
    $delivery_method = sanitize_input($_POST['delivery_method'] ?? 'pickup');
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');

    // Combine country code and phone
    $full_phone = !empty($customer_phone) ? $country_code . ' ' . $customer_phone : '';

    // Validate required fields
    if (empty($customer_name)) {
        $errors[] = 'El nombre es requerido';
    }

    if (empty($customer_email) || !validate_email($customer_email)) {
        $errors[] = 'Email inválido';
    }

    // Validar teléfono (ahora requerido)
    if (empty($customer_phone)) {
        $errors[] = 'El teléfono es requerido';
    }

    if (!in_array($delivery_method, ['pickup', 'shipping'])) {
        $errors[] = 'Método de entrega inválido';
    }

    if (!in_array($payment_method, ['arrangement', 'pickup_payment', 'mercadopago'])) {
        $errors[] = 'Método de pago inválido';
    }

    // Shipping address (only for delivery)
    $shipping_address = null;
    $needs_shipping = ($delivery_method === 'shipping');

    if ($needs_shipping) {
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $postal_code = sanitize_input($_POST['postal_code'] ?? '');
        $state = sanitize_input($_POST['state'] ?? '');
        $country = sanitize_input($_POST['country'] ?? '');

        if (empty($address)) {
            $errors[] = 'La dirección es requerida para envío';
        }

        if (empty($city)) {
            $errors[] = 'La ciudad es requerida para envío';
        }

        if (empty($postal_code)) {
            $errors[] = 'El código postal es requerido para envío';
        }

        if (empty($state)) {
            $errors[] = 'La provincia/estado es requerida para envío';
        }

        if (empty($country)) {
            $errors[] = 'El país es requerido para envío';
        }

        if (empty($errors)) {
            $shipping_address = [
                'name' => $customer_name,
                'address' => $address,
                'city' => $city,
                'postal_code' => $postal_code,
                'state' => $state,
                'country' => $country,
                'phone' => $full_phone
            ];
        }
    }

    // If there are errors and this is AJAX, return JSON
    if (!empty($errors) && $is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $errors)
        ]);
        exit;
    }

    // If no errors, create order
    if (empty($errors)) {

        // Prepare order items
        $order_items = [];
        foreach ($cart_items as $item) {
            $order_items[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => ($selected_currency === 'USD') ? $item['price_usd'] : $item['price_ars'],
                'quantity' => $item['quantity'],
                'final_price' => ($selected_currency === 'USD') ? $item['subtotal_usd'] : $item['subtotal_ars']
            ];
        }

        // Prepare order data
        $order_data = [
            'items' => $order_items,
            'currency' => $selected_currency,
            'subtotal' => ($selected_currency === 'USD') ? $subtotal_usd : $subtotal_ars,
            'discount_promotion' => ($selected_currency === 'USD') ? $promotion_discount_usd : $promotion_discount_ars,
            'discount_coupon' => ($selected_currency === 'USD') ? $coupon_discount_usd : $coupon_discount_ars,
            'coupon_code' => $coupon_code,
            'total' => $total,
            'payment_method' => $payment_method,
            'shipping_address' => $shipping_address,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $full_phone,
            'contact_preference' => $contact_preference,
            'delivery_method' => $delivery_method,
            'notes' => sanitize_input($_POST['notes'] ?? '')
        ];

        // Create order
        $result = create_order($order_data);

        if (isset($result['success']) && $result['success']) {
            $order = $result['order'];

            // Increment coupon usage if applied
            if ($coupon_code) {
                increment_coupon_usage($coupon_code);
            }

            // Send order confirmation to customer (siempre por email)
            send_order_confirmation_email($order);

            // Save customer data to cookies for future checkouts (1 year expiration)
            $cookie_expiry = time() + (365 * 24 * 60 * 60); // 1 year
            $cookie_path = '/';
            setcookie('checkout_name', $customer_name, $cookie_expiry, $cookie_path);
            setcookie('checkout_email', $customer_email, $cookie_expiry, $cookie_path);
            setcookie('checkout_country_code', $country_code, $cookie_expiry, $cookie_path);
            setcookie('checkout_phone', $customer_phone, $cookie_expiry, $cookie_path);

            // Save shipping address if provided
            if ($shipping_address) {
                setcookie('checkout_address', $shipping_address['address'], $cookie_expiry, $cookie_path);
                setcookie('checkout_city', $shipping_address['city'], $cookie_expiry, $cookie_path);
                setcookie('checkout_postal_code', $shipping_address['postal_code'], $cookie_expiry, $cookie_path);
                setcookie('checkout_state', $shipping_address['state'], $cookie_expiry, $cookie_path);
                setcookie('checkout_country', $shipping_address['country'], $cookie_expiry, $cookie_path);
            }

            // Clear cart and coupon immediately after creating order (for all payment methods)
            unset($_SESSION['cart']);
            unset($_SESSION['coupon_code']);
            unset($_SESSION['applied_coupon']);

            // For non-mercadopago payments: send admin notifications immediately
            if ($payment_method !== 'mercadopago') {
                send_admin_new_order_email($order);
                send_telegram_new_order($order);

                // Redirect to thank you page
                header("Location: " . url("/gracias?order={$order['id']}&token={$order['tracking_token']}"));
                exit;
            } else {
                // For Mercadopago: return JSON for AJAX
                if ($is_ajax) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'order_id' => $order['id'],
                        'order_number' => $order['order_number'],
                        'tracking_token' => $order['tracking_token'],
                        'total' => $order['total'],
                        'currency' => $order['currency'],
                        'items' => $order['items']
                    ]);
                    exit;
                }
            }
        } else {
            $errors[] = $result['error'] ?? 'Error al procesar la orden';

            // If AJAX, return error as JSON
            if ($is_ajax) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Error al procesar la orden'
                ]);
                exit;
            }
        }
    }
}

// Generate CSRF token - Regenerar en cada carga para evitar tokens expirados
if (isset($_SESSION['csrf_token'])) {
    unset($_SESSION['csrf_token']);
    unset($_SESSION['csrf_token_time']);
}
$csrf_token = generate_csrf_token();

// Load saved customer data from cookies for form pre-fill
$saved_name = $_COOKIE['checkout_name'] ?? '';
$saved_email = $_COOKIE['checkout_email'] ?? '';
$saved_country_code = $_COOKIE['checkout_country_code'] ?? '+54';
$saved_phone = $_COOKIE['checkout_phone'] ?? '';
$saved_telegram = $_COOKIE['checkout_telegram'] ?? '';
$saved_contact_pref = $_COOKIE['checkout_contact_pref'] ?? '';
$saved_address = $_COOKIE['checkout_address'] ?? '';
$saved_city = $_COOKIE['checkout_city'] ?? '';
$saved_postal_code = $_COOKIE['checkout_postal_code'] ?? '';
$saved_state = $_COOKIE['checkout_state'] ?? '';
$saved_country = $_COOKIE['checkout_country'] ?? '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- SEO and Social Media Meta Tags -->
    <?php render_meta_tags($site_config); ?>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme ?? 'classic'); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/mobile-menu.css'); ?>">

    <style nonce="<?= csp_nonce() ?>">
        :root {
            /* Theme colors - dynamically loaded from active theme */
            --checkout-bg-primary: <?php echo $theme_colors['background'] ?? '#fafaf8'; ?>;
            --checkout-bg-secondary: #ffffff;
            --checkout-text-primary: <?php echo $theme_colors['text'] ?? '#1a1a1a'; ?>;
            --checkout-text-secondary: #6b6b6b;
            --checkout-accent: <?php echo $theme_colors['primary'] ?? '#b8860b'; ?>;
            --checkout-accent-hover: <?php echo $theme_colors['primary_dark'] ?? '#8b6914'; ?>;
            --checkout-border: #e5e5e0;
            --checkout-success: <?php echo $theme_colors['success'] ?? '#2d5016'; ?>;
            --checkout-shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --checkout-shadow-md: 0 4px 12px rgba(0,0,0,0.08);

            /* Efectos y sombras */
            --checkout-accent-shadow: rgba(102, 126, 234, 0.1);
            --checkout-accent-bg-light: rgba(102, 126, 234, 0.03);
            --checkout-payment-hover-bg: #f8f9fa;

            /* Estados de alerta y error */
            --checkout-warning: <?php echo $theme_colors['warning'] ?? '#ffc107'; ?>;
            --checkout-warning-text: #856404;
            --checkout-warning-bg: rgba(255, 193, 7, 0.1);
            --checkout-error: <?php echo $theme_colors['error'] ?? '#dc3545'; ?>;
            --checkout-error-text: #721c24;
            --checkout-error-bg: rgba(220, 53, 69, 0.05);

            /* Timer states */
            --checkout-timer-bg-start: #fff3cd;
            --checkout-timer-bg-end: #ffeeba;
            --checkout-timer-border: #ffeaa7;
            --checkout-timer-shadow: rgba(255, 193, 7, 0.2);
            --checkout-timer-warning-start: #f8d7da;
            --checkout-timer-warning-end: #f5c6cb;
            --checkout-timer-warning-border: #f1b0b7;
            --checkout-timer-danger-start: #f5c6cb;
            --checkout-timer-danger-end: #f1b0b7;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--checkout-bg-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .header {
            background: var(--checkout-bg-secondary);
            border-bottom: 2px solid var(--checkout-border);
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            
            font-size: 1.75rem;
            
            font-weight: 700;
            text-decoration: none;
            letter-spacing: -0.02em;
            transition: color 0.3s ease;
        }

        .logo:hover {
            opacity: 0.8;
        }

        .checkout-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem 3rem;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 3rem;
            align-items: start;
        }

        /* PASARELA (Izquierda) */
        .checkout-form-column {
            background: var(--checkout-bg-secondary);
            border: 2px solid var(--checkout-border);
            border-radius: 2px;
            padding: 2rem;
            box-shadow: var(--checkout-shadow-md);
        }

        .checkout-form-column h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--checkout-text-primary);
            letter-spacing: -0.03em;
            line-height: 1.1;
            text-decoration: none;
            position: relative;
        }

        .checkout-form-column h1::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: var(--checkout-accent);
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            clear: both;
        }

        /* Paso (accordion style) */
        .step-section {
            border: none;
            border-left: 3px solid var(--checkout-border);
            margin-bottom: 0.5rem;
            margin-left: 0.75rem;
            padding-left: 1rem;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .step-section::before {
            content: '';
            position: absolute;
            left: -0.75rem;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--checkout-accent);
            transform: scaleY(0);
            transform-origin: top;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .step-section.active::before {
            transform: scaleY(1);
        }

        .step-section.completed {
            border-left-color: var(--checkout-success);
        }

        .step-section.locked {
            opacity: 0.5;
            pointer-events: none;
        }

        .step-header {
            background: transparent;
            padding: 0.875rem 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            user-select: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .step-header:hover {
            transform: translateX(4px);
        }

        .step-number-circle {
            width: 48px;
            height: 48px;
            border-radius: 2px;
            background: var(--checkout-bg-primary);
            border: 2px solid var(--checkout-border);
            color: var(--checkout-text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            
            
            font-weight: 700;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .step-number-circle::after {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--checkout-accent);
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .step-section.active .step-number-circle {
            background: var(--checkout-accent);
            border-color: var(--checkout-accent);
            color: white;
        }

        .step-section.completed .step-number-circle {
            background: var(--checkout-success);
            border-color: var(--checkout-success);
            color: white;
        }

        .step-section.completed .step-number-circle::before {
            content: '✓';
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            z-index: 1;
        }

        .step-header-content {
            flex: 1;
        }

        .step-title {
            
            font-weight: 600;
            
            font-size: 1.35rem;
            margin: 0;
            color: var(--checkout-text-primary);
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .step-subtitle {
            font-size: 0.875rem;
            color: var(--checkout-text-secondary);
            margin: 0.35rem 0 0 0;
            font-weight: 400;
        }

        .step-indicator-icon {
            font-size: 1rem;
            color: var(--checkout-text-secondary);
            transition: transform 0.3s ease;
        }

        .step-section.active .step-indicator-icon {
            transform: rotate(180deg);
        }

        .step-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                        padding 0.4s cubic-bezier(0.4, 0, 0.2, 1),
                        opacity 0.3s ease;
            opacity: 0;
        }

        .step-section.active .step-content,
        .step-section.completed .step-content {
            max-height: 2000px;
            padding: 1rem 0 1.5rem;
            opacity: 1;
        }

        /* Form styles compactos */
        .form-group {
            margin-bottom: 0.875rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--checkout-text-primary);
            letter-spacing: 0.01em;
            text-transform: uppercase;
            
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--checkout-border);
            border-radius: 2px;
            
            font-size: 1rem;
            background: var(--checkout-bg-secondary);
            color: var(--checkout-text-primary);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--checkout-accent);
            box-shadow: 0 0 0 3px var(--checkout-accent-shadow);
        }

        .form-group small {
            display: block;
            margin-top: 0.4rem;
            font-size: 0.8125rem;
            color: var(--checkout-text-secondary);
            line-height: 1.4;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .radio-option {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border: 1px solid var(--checkout-border);
            border-radius: 2px;
            cursor: pointer;
            background: var(--checkout-bg-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .radio-option::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--checkout-accent);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .radio-option:hover {
            border-color: var(--checkout-accent);
            transform: translateX(2px);
        }

        .radio-option:has(input[type="radio"]:checked) {
            border-color: var(--checkout-accent);
            background: var(--checkout-accent-bg-light);
        }

        .radio-option:has(input[type="radio"]:checked)::before {
            transform: scaleX(1);
        }

        .radio-option input[type="radio"] {
            margin-top: 0.15rem;
            cursor: pointer;
            accent-color: var(--checkout-accent);
        }

        .radio-option input[type="radio"]:checked + span,
        .radio-option input[type="radio"]:checked + div {
            color: var(--checkout-text-primary);
        }

        .radio-option strong {
            font-weight: 600;
            color: var(--checkout-text-primary);
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .payment-method {
            display: flex;
            gap: 0.75rem;
            padding: 0.9rem;
            border: 2px solid var(--checkout-border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .payment-method:hover {
            border-color: var(--checkout-success);
            background: var(--checkout-payment-hover-bg);
        }

        .payment-method input[type="radio"]:checked ~ div {
            color: var(--checkout-success);
        }

        .phone-input-group {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 0.75rem;
        }

        .country-select {
            width: 100%;
        }

        .btn {
            padding: 0.875rem 2rem;
            border: 1px solid transparent;
            border-radius: 2px;
            
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-block;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: left 0.3s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--checkout-accent);
            color: white;
            box-shadow: var(--checkout-shadow-sm);
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--checkout-accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--checkout-shadow-md);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: var(--checkout-border);
            color: var(--checkout-text-secondary);
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-secondary {
            background: transparent;
            color: var(--checkout-text-primary);
            border-color: var(--checkout-border);
        }

        .btn-secondary:hover {
            background: var(--checkout-bg-primary);
            border-color: var(--checkout-text-secondary);
        }

        /* RESUMEN (Derecha) */
        .order-summary-column {
            position: sticky;
            top: 1.5rem;
            background: var(--checkout-text-primary);
            color: var(--checkout-bg-primary);
            border: 2px solid var(--checkout-text-primary);
            border-radius: 2px;
            padding: 2rem;
            box-shadow: var(--checkout-shadow-md);
            /* Prevent flash of empty content */
            opacity: 1 !important;
            visibility: visible !important;
            min-height: 400px;
        }

        .order-summary-column h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 1.5rem 0;
            letter-spacing: -0.02em;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .currency-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            padding: 0.25rem;
            border-radius: 2px;
        }

        .currency-btn {
            flex: 1;
            padding: 0.625rem;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 2px;
            cursor: pointer;
            
            font-size: 0.8125rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .currency-btn:hover {
            color: rgba(255, 255, 255, 0.9);
        }

        .currency-btn.active {
            background: var(--checkout-accent);
            color: white;
            border-color: var(--checkout-accent);
        }

        .summary-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            /* Prevent flash during load */
            opacity: 1 !important;
            visibility: visible !important;
        }

        .summary-item:last-of-type {
            margin-bottom: 1rem;
        }

        .summary-item img {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 2px;
            flex-shrink: 0;
            border: 1px solid rgba(255, 255, 255, 0.15);
            /* Prevent layout shift while loading */
            background: rgba(255, 255, 255, 0.1);
        }

        .summary-item-info {
            flex: 1;
        }

        .summary-item-name {
            font-weight: 500;
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
            color: var(--checkout-bg-primary);
            line-height: 1.3;
        }

        .summary-item-price {
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .summary-item > div:last-child {
            color: var(--checkout-bg-primary);
            font-weight: 600;
        }

        .summary-totals {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.625rem 0;
            font-size: 0.9375rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .summary-row.discount {
            color: var(--checkout-accent);
            font-weight: 600;
        }

        .summary-row.total {
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            margin-top: 0.75rem;
            padding-top: 1rem;
            
            
            font-size: 1.75rem;
            
            font-weight: 700;
            color: var(--checkout-bg-primary);
            letter-spacing: -0.02em;
        }

        #final-buy-button {
            width: 100%;
            padding: 1.125rem;
            font-size: 1rem;
            margin-top: 1.5rem;
            background: var(--checkout-bg-primary);
            color: var(--checkout-text-primary);
            border: 2px solid var(--checkout-bg-primary);
        }

        #final-buy-button:hover:not(:disabled) {
            background: var(--checkout-accent);
            border-color: var(--checkout-accent);
            color: white;
        }

        #final-buy-button:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .order-summary-column {
                position: static;
                order: -1;
            }

            .checkout-form-column h1 {
                font-size: 2rem;
            }

            /* Mostrar botón mobile al final del paso 4 */
            #final-buy-button-mobile {
                display: block !important;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }

            .checkout-container {
                padding: 0 1rem 2rem;
            }

            .checkout-form-column,
            .order-summary-column {
                padding: 1.5rem;
            }

            .checkout-form-column h1 {
                
            font-size: 1.75rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .step-section {
                margin-left: 1rem;
                padding-left: 1rem;
            }

            .step-header {
                padding: 1rem 0;
            }

            .step-number-circle {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }

            .step-title {
                font-size: 1.125rem;
            }

            .step-subtitle {
                font-size: 0.8125rem;
            }

            .btn {
                padding: 0.875rem 1.5rem;
                font-size: 0.875rem;
            }
        }

        .alert-box {
            background: var(--checkout-warning-bg);
            border-left: 3px solid var(--checkout-warning);
            border-radius: 2px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            font-size: 0.9375rem;
            color: var(--checkout-warning-text);
        }

        .errors {
            background: var(--checkout-error-bg);
            border-left: 3px solid var(--checkout-error);
            border-radius: 2px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
        }

        .errors h3 {

            font-size: 1.25rem;
            margin: 0 0 0.75rem 0;
            color: var(--checkout-error-text);
        }

        .errors ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .errors li {
            color: var(--checkout-error-text);
            margin-bottom: 0.25rem;
            line-height: 1.5;
        }

        /* Checkout Timer Styles */
        .checkout-timer {
            background: linear-gradient(135deg, var(--checkout-timer-bg-start) 0%, var(--checkout-timer-bg-end) 100%);
            border: 1px solid var(--checkout-timer-border);
            border-radius: 8px;
            margin-top: 0.5rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: 0 2px 8px var(--checkout-timer-shadow);
        }

        .timer-content {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            gap: 0.75rem;
        }

        .timer-icon {
            font-size: 1.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .timer-text {
            flex: 1;
        }

        .timer-label {
            font-size: 0.875rem;
            color: var(--checkout-warning-text);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .timer-countdown {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--checkout-warning-text);
            font-family: 'Courier New', monospace;
        }

        .checkout-timer.warning {
            background: linear-gradient(135deg, var(--checkout-timer-warning-start) 0%, var(--checkout-timer-warning-end) 100%);
            border-color: var(--checkout-timer-warning-border);
        }

        .checkout-timer.warning .timer-label,
        .checkout-timer.warning .timer-countdown {
            color: var(--checkout-error-text);
        }

        .checkout-timer.danger {
            background: linear-gradient(135deg, var(--checkout-timer-danger-start) 0%, var(--checkout-timer-danger-end) 100%);
            border-color: var(--checkout-error);
            animation: danger-pulse 1s infinite;
        }

        @keyframes danger-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .checkout-timer.danger .timer-label,
        .checkout-timer.danger .timer-countdown {
            color: var(--checkout-error-text);
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="<?php echo url('/'); ?>" class="logo"><?php render_site_logo($site_config); ?></a>
            <a href="<?php echo url('/carrito'); ?>" class="btn-secondary">← Volver al carrito</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="checkout-container">
        <?php if (!empty($errors)): ?>
        <div class="errors">
            <h3>⚠️ Por favor corrige los siguientes errores:</h3>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <!-- COLUMNA IZQUIERDA: Pasarela -->
            <div class="checkout-form-column">
                <h1>Finalizar Compra</h1>
                
                <!-- Checkout Timer -->
                <div class="checkout-timer" id="checkout-timer">
                    <div class="timer-content">
                        <span class="timer-icon">⏰</span>
                        <div class="timer-text">
                            <div class="timer-label">Tiempo restante para completar tu compra:</div>
                            <div class="timer-countdown" id="timer-countdown">--:--:--</div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="" id="checkout-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- PASO 1: Contacto -->
                    <div class="step-section active" id="step-1">
                        <div class="step-header" data-action="toggleStep" data-step="1">
                            <div class="step-number-circle">1</div>
                            <div class="step-header-content">
                                <h3 class="step-title">📋 Información de Contacto</h3>
                                <p class="step-subtitle" id="step-1-summary"></p>
                            </div>
                            <span class="step-indicator-icon">▼</span>
                        </div>
                        <div class="step-content">
                            <div class="form-group">
                                <label for="customer_name">Nombre completo *</label>
                                <input type="text" id="customer_name" name="customer_name"
                                       value="<?php echo htmlspecialchars($_POST['customer_name'] ?? $saved_name); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="customer_email">Email *</label>
                                <input type="email" id="customer_email" name="customer_email"
                                       value="<?php echo htmlspecialchars($_POST['customer_email'] ?? $saved_email); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="customer_phone">Teléfono *</label>
                                <div class="phone-input-group">
                                    <select id="country_code" name="country_code" class="country-select">
                                        <?php $selected_country = $_POST['country_code'] ?? $saved_country_code; ?>
                                        <option value="+54" <?php echo ($selected_country === '+54') ? 'selected' : ''; ?>>🇦🇷 +54</option>
                                        <option value="+1" <?php echo ($selected_country === '+1') ? 'selected' : ''; ?>>🇺🇸 +1</option>
                                        <option value="+52" <?php echo ($selected_country === '+52') ? 'selected' : ''; ?>>🇲🇽 +52</option>
                                    </select>
                                    <input type="tel" id="customer_phone" name="customer_phone" required
                                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? $saved_phone); ?>"
                                           placeholder="11 1234-5678">
                                </div>
                                <small>Para contacto sobre tu pedido</small>
                            </div>

                            <input type="hidden" name="contact_preference" value="email">
                        </div>
                    </div>

                    <!-- PASO 2: Envío -->
                    <div class="step-section locked" id="step-2">
                        <div class="step-header" data-action="toggleStep" data-step="2">
                            <div class="step-number-circle">2</div>
                            <div class="step-header-content">
                                <h3 class="step-title">🚚 Envío o Retiro</h3>
                                <p class="step-subtitle" id="step-2-summary"></p>
                            </div>
                            <span class="step-indicator-icon">▼</span>
                        </div>
                        <div class="step-content">
                            <?php if ($has_pickup_only): ?>
                            <div class="alert-box">
                                <strong>⚠️ Tu carrito contiene productos que solo pueden retirarse en persona</strong>
                            </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Método de entrega *</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="delivery_method" value="pickup" required>
                                        <div>
                                            <strong>🏪 Retiro en persona</strong>
                                            <p class="option-description">Coordinaremos lugar y horario</p>
                                        </div>
                                    </label>
                                    <label class="radio-option <?php echo $has_pickup_only ? 'disabled' : ''; ?>">
                                        <input type="radio" name="delivery_method" value="shipping"
                                               <?php echo $has_pickup_only ? 'disabled' : ''; ?> required>
                                        <div>
                                            <strong>📦 Envío a domicilio</strong>
                                            <p class="option-description">
                                                <?php echo $has_pickup_only ? 'No disponible para estos productos' : 'Completa tu dirección'; ?>
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div id="shipping-fields" class="hidden">
                                <div class="form-group">
                                    <label for="address">Dirección *</label>
                                    <input type="text" id="address" name="address" placeholder="Calle y número"
                                           value="<?php echo htmlspecialchars($_POST['address'] ?? $saved_address); ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="postal_code">Código Postal *</label>
                                        <input type="text" id="postal_code" name="postal_code"
                                               value="<?php echo htmlspecialchars($_POST['postal_code'] ?? $saved_postal_code); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="city">Ciudad *</label>
                                        <input type="text" id="city" name="city"
                                               value="<?php echo htmlspecialchars($_POST['city'] ?? $saved_city); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="state">Provincia / Estado *</label>
                                    <input type="text" id="state" name="state"
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? $saved_state); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="country">País *</label>
                                    <select id="country" name="country">
                                        <?php $selected_saved_country = $_POST['country'] ?? $saved_country; ?>
                                        <option value="Argentina" <?php echo ($selected_saved_country === 'Argentina' || empty($selected_saved_country)) ? 'selected' : ''; ?>>Argentina</option>
                                        <option value="Chile" <?php echo ($selected_saved_country === 'Chile') ? 'selected' : ''; ?>>Chile</option>
                                        <option value="Uruguay" <?php echo ($selected_saved_country === 'Uruguay') ? 'selected' : ''; ?>>Uruguay</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 3: Pago -->
                    <div class="step-section locked" id="step-3">
                        <div class="step-header" data-action="toggleStep" data-step="3">
                            <div class="step-number-circle">3</div>
                            <div class="step-header-content">
                                <h3 class="step-title">💳 Método de Pago</h3>
                                <p class="step-subtitle" id="step-3-summary"></p>
                            </div>
                            <span class="step-indicator-icon">▼</span>
                        </div>
                        <div class="step-content">
                            <div class="form-group">
                                <div class="payment-methods">
                                    <label class="payment-method" id="mercadopago-option">
                                        <input type="radio" name="payment_method" value="mercadopago" id="mercadopago-radio" required>
                                        <div>
                                            <strong>💳 Mercadopago</strong>
                                            <p class="option-description">Tarjeta de crédito/débito - Pago inmediato (solo ARS)</p>
                                        </div>
                                    </label>

                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="arrangement" required>
                                        <div>
                                            <strong>🤝 Arreglo con <?php echo htmlspecialchars($site_config['site_owner']); ?></strong>
                                            <p class="option-description">Coordinaremos el pago directamente</p>
                                        </div>
                                    </label>

                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="pickup_payment" required>
                                        <div>
                                            <strong>💵 Pago al retirar</strong>
                                            <p class="option-description">Efectivo o transferencia al retirar</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Comentarios (opcional)</label>
                                <textarea id="notes" name="notes" rows="2" placeholder="Horario preferido, referencias..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 4: Confirmar -->
                    <div class="step-section locked" id="step-4">
                        <div class="step-header" data-action="toggleStep" data-step="4">
                            <div class="step-number-circle">4</div>
                            <div class="step-header-content">
                                <h3 class="step-title">✅ Confirmar Pedido</h3>
                                <p class="step-subtitle">Revisa tu información antes de confirmar</p>
                            </div>
                            <span class="step-indicator-icon">▼</span>
                        </div>
                        <div class="step-content">
                            <div class="summary-box">
                                <p><strong>Contacto:</strong> <span id="confirm-name"></span> • <span id="confirm-email"></span></p>
                                <p><strong>Entrega:</strong> <span id="confirm-delivery"></span></p>
                                <p><strong>Pago:</strong> <span id="confirm-payment"></span></p>
                            </div>

                            <!-- Botón para mobile al final del paso 4 -->
                            <button type="button" id="final-buy-button-mobile" class="btn btn-primary btn-block hidden mt-lg" data-action="submitOrder" disabled>
                                🛒 Confirmar Pedido
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- COLUMNA DERECHA: Resumen -->
            <div class="order-summary-column">
                <h2>📦 Resumen del Pedido</h2>

                <!-- Currency Toggle -->
                <div class="currency-toggle">
                    <button class="currency-btn <?php echo $checkout_currency === 'ARS' ? 'active' : ''; ?>"
                            data-action="switchCurrency" data-currency="ARS">💵 ARS</button>
                    <button class="currency-btn <?php echo $checkout_currency === 'USD' ? 'active' : ''; ?>"
                            data-action="switchCurrency" data-currency="USD">💵 USD</button>
                </div>

                <?php foreach ($cart_items as $item): ?>
                <div class="summary-item">
                    <img src="<?php echo url($item['thumbnail'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="summary-item-info">
                        <div class="summary-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="summary-item-price">
                            <?php
                            $display_price = ($selected_currency === 'USD') ? $item['price_usd'] : $item['price_ars'];
                            $display_final_price = ($selected_currency === 'USD') ? $item['final_price_usd'] : $item['final_price_ars'];

                            // Show promotion or coupon
                            if ($item['promotion']) {
                                // Item has promotion - show strikethrough original price and red discounted price
                                echo $item['quantity'] . 'x <span class="price-original">' . format_price($display_price, $selected_currency) . '</span> ';
                                echo '<span class="price-promo">' . format_price($display_final_price, $selected_currency) . '</span>';
                                echo '<div class="promo-label">🎉 Promoción</div>';
                            } elseif (isset($item['has_coupon']) && $item['has_coupon']) {
                                // Item eligible for coupon - show strikethrough original price and green discounted price
                                $coupon_price = ($selected_currency === 'USD') ? $item['coupon_price_usd'] : $item['coupon_price_ars'];
                                echo $item['quantity'] . 'x <span class="price-original">' . format_price($display_price, $selected_currency) . '</span> ';
                                echo '<span class="price-coupon">' . format_price($coupon_price, $selected_currency) . '</span>';
                                echo '<div class="coupon-label">🎫 Cupón</div>';
                            } else {
                                // No discount
                                echo $item['quantity'] . 'x ' . format_price($display_price, $selected_currency);
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <?php
                        // Calculate subtotal with discounts applied
                        if ($item['promotion']) {
                            // Subtotal already includes promotion discount
                            $display_subtotal = ($selected_currency === 'USD') ? $item['subtotal_usd'] : $item['subtotal_ars'];
                        } elseif (isset($item['has_coupon']) && $item['has_coupon']) {
                            // Calculate subtotal with coupon
                            $coupon_price = ($selected_currency === 'USD') ? $item['coupon_price_usd'] : $item['coupon_price_ars'];
                            $display_subtotal = $coupon_price * $item['quantity'];
                        } else {
                            // No discount
                            $display_subtotal = ($selected_currency === 'USD') ? $item['subtotal_usd'] : $item['subtotal_ars'];
                        }
                        ?>
                        <strong><?php echo format_price($display_subtotal, $selected_currency); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="summary-totals">
                    <?php
                    // Show promotion discount
                    $display_promotion_discount = ($selected_currency === 'USD') ? $promotion_discount_usd : $promotion_discount_ars;
                    if ($display_promotion_discount > 0):
                    ?>
                    <div class="summary-row summary-row-promo">
                        <span>🎉 Descuento Promoción:</span>
                        <span>-<?php echo format_price($display_promotion_discount, $selected_currency); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($coupon_discount > 0): ?>
                    <div class="summary-row summary-row-coupon">
                        <span>🎫 Cupón (<?php echo htmlspecialchars($coupon_code); ?>):</span>
                        <span>-<?php echo format_price($coupon_discount, $selected_currency); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="summary-row total">
                        <span>Total:</span>
                        <span><?php echo format_price($total, $selected_currency); ?></span>
                    </div>
                </div>

                <button type="button" id="final-buy-button" class="btn btn-primary" disabled data-action="submitOrder">
                    🛒 Confirmar Pedido
                </button>
            </div>
        </div>
    </div>

    <!-- Mercadopago Payment Modal -->
    <div id="mercadopago-modal" class="mp-modal">
        <div class="mp-modal-content">
            <button data-action="closeMercadopagoModal" class="mp-close-btn">✕</button>
            <div>
                <h2 class="mp-title">💳 Pagar con Mercadopago</h2>
                <p class="mp-order-info">Orden <span id="mp-order-number"></span></p>
            </div>
            <div class="mp-summary">
                <h3>Resumen</h3>
                <div id="mp-order-items"></div>
                <div class="mp-summary-total">
                    <span>Total a pagar</span>
                    <span id="mp-total"></span>
                </div>
            </div>
            <div id="mp-loading" class="mp-loading">
                <div class="mp-loading-flex">
                    <div class="spinner"></div>
                    <p class="mp-loading-text">Preparando pago...</p>
                </div>
            </div>
            <div id="walletBrick_container"></div>
            <div id="mp-error-message" class="mp-error"></div>
            <div class="mp-info">
                <p><strong>💳 Pago seguro con Mercadopago</strong></p>
                <p>Al hacer clic en "Pagar", serás redirigido a Mercadopago para completar tu pago de forma segura.</p>
            </div>
        </div>
    </div>

    <style nonce="<?= csp_nonce() ?>">
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes flash {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.7; transform: scale(1.05); }
    }
    </style>

    <script nonce="<?= csp_nonce() ?>">
        // Datos del carrito procesados por PHP (con nombres, precios, promociones, cupones)
        const checkoutCartData = <?php echo json_encode($cart_items); ?>;
        const checkoutCurrency = '<?php echo $checkout_currency; ?>';

        // Step management
        let completedSteps = new Set();

        function toggleStep(stepNumber) {
            const step = document.getElementById(`step-${stepNumber}`);

            // No permitir abrir si está locked
            if (step.classList.contains('locked')) {
                return;
            }

            // Toggle active
            step.classList.toggle('active');
        }

        function unlockNextStep(currentStep) {
            const nextStep = document.getElementById(`step-${currentStep + 1}`);
            if (nextStep) {
                nextStep.classList.remove('locked');
                nextStep.classList.add('active');

                // Scroll to next step
                nextStep.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function markStepCompleted(stepNumber) {
            const step = document.getElementById(`step-${stepNumber}`);
            step.classList.add('completed');
            step.classList.remove('active');
            completedSteps.add(stepNumber);

            updateBuyButton();
        }

        function updateBuyButton() {
            const button = document.getElementById('final-buy-button');
            const buttonMobile = document.getElementById('final-buy-button-mobile');
            if (completedSteps.size === 4) {
                button.disabled = false;
                if (buttonMobile) buttonMobile.disabled = false;
            }
        }

        // Step 1: Contact validation
        function validateStep1() {
            const name = document.getElementById('customer_name').value.trim();
            const email = document.getElementById('customer_email').value.trim();
            const phone = document.getElementById('customer_phone').value.trim();

            // Validar que todos los campos requeridos estén completos
            const isValid = name && email && phone;

            if (isValid) {
                document.getElementById('step-1-summary').textContent = `${name} • ${email}`;
                markStepCompleted(1);
                unlockNextStep(1);
            }
        }

        // Add event listeners for contact fields
        document.getElementById('customer_name').addEventListener('input', validateStep1);
        document.getElementById('customer_email').addEventListener('input', validateStep1);
        document.getElementById('customer_phone').addEventListener('input', validateStep1);

        // Initial validation
        validateStep1();

        // Step 2: Delivery validation
        document.querySelectorAll('input[name="delivery_method"]').forEach(radio => {
            radio.addEventListener('change', validateStep2);
        });

        function validateStep2() {
            const method = document.querySelector('input[name="delivery_method"]:checked').value;
            document.getElementById('step-2-summary').textContent = method === 'pickup' ? '🏪 Retiro en persona' : '📦 Envío a domicilio';
            markStepCompleted(2);
            unlockNextStep(2);

            // Show/hide shipping fields
            const shippingFields = document.getElementById('shipping-fields');
            shippingFields.classList.toggle('hidden', method !== 'shipping');
        }

        // Step 3: Payment validation
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', validateStep3);
        });

        function validateStep3() {
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            let methodText = '';
            if (method === 'arrangement') methodText = '🤝 Arreglo';
            else if (method === 'pickup_payment') methodText = '💵 Pago al retirar';
            else if (method === 'mercadopago') methodText = '💳 Mercadopago';

            document.getElementById('step-3-summary').textContent = methodText;
            markStepCompleted(3);
            unlockNextStep(3);
            updateConfirmationSummary();
        }

        function updateConfirmationSummary() {
            document.getElementById('confirm-name').textContent = document.getElementById('customer_name').value;
            document.getElementById('confirm-email').textContent = document.getElementById('customer_email').value;

            const method = document.querySelector('input[name="delivery_method"]:checked').value;
            document.getElementById('confirm-delivery').textContent = method === 'pickup' ? 'Retiro en persona' : 'Envío a domicilio';

            const payment = document.querySelector('input[name="payment_method"]:checked').value;
            let paymentText = '';
            if (payment === 'arrangement') paymentText = 'Arreglo';
            else if (payment === 'pickup_payment') paymentText = 'Pago al retirar';
            else if (payment === 'mercadopago') paymentText = 'Mercadopago';
            document.getElementById('confirm-payment').textContent = paymentText;

            markStepCompleted(4);
        }

        // Current selected currency
        let currentCurrency = '<?php echo $checkout_currency; ?>';

        // Currency switcher
        function switchCurrency(currency) {
            if (currency === currentCurrency) return;

            // Create form and submit to change currency
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const currencyInput = document.createElement('input');
            currencyInput.type = 'hidden';
            currencyInput.name = 'currency';
            currencyInput.value = currency;
            form.appendChild(currencyInput);

            const switchInput = document.createElement('input');
            switchInput.type = 'hidden';
            switchInput.name = 'switch_currency';
            switchInput.value = '1';
            form.appendChild(switchInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Update payment methods based on currency
        function updatePaymentMethods() {
            const mercadopagoOption = document.getElementById('mercadopago-option');
            const mercadopagoRadio = document.getElementById('mercadopago-radio');

            if (currentCurrency === 'USD') {
                // Disable MercadoPago for USD
                mercadopagoOption.classList.add('disabled');
                mercadopagoRadio.disabled = true;

                // If MercadoPago was selected, deselect it
                if (mercadopagoRadio.checked) {
                    mercadopagoRadio.checked = false;
                }
            } else {
                // Enable MercadoPago for ARS
                mercadopagoOption.classList.remove('disabled');
                mercadopagoRadio.disabled = false;
            }
        }

        // Initialize on page load
        updatePaymentMethods();

        // Submit order function
        function submitOrder() {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

            if (!paymentMethod) {
                showModal({
                    title: 'Error',
                    message: 'Por favor selecciona un método de pago',
                    icon: '⚠️',
                    confirmText: 'Entendido'
                });
                return;
            }

            // If Mercadopago, show modal WITHOUT creating order yet
            if (paymentMethod.value === 'mercadopago') {
                showMercadopagoModalWithoutOrder();
            } else {
                // For other payment methods, submit form normally
                const form = document.getElementById('checkout-form');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'place_order';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            }
        }

        // Show Mercadopago modal WITHOUT creating order (order will be created when user clicks MP button)
        function showMercadopagoModalWithoutOrder() {
            // Usar datos del carrito procesados por PHP (incluye nombres, precios, promociones, cupones)
            if (!checkoutCartData || checkoutCartData.length === 0) {
                showModal({
                    title: 'Error',
                    message: 'Tu carrito está vacío',
                    icon: '⚠️',
                    confirmText: 'Entendido'
                });
                return;
            }

            // Calcular total del carrito usando los precios procesados (con promociones y cupones)
            let cartTotal = 0;
            let itemsHTML = '';

            checkoutCartData.forEach(item => {
                // Determinar el precio final a usar (cupón > promoción > precio normal)
                let finalPrice;
                if (checkoutCurrency === 'USD') {
                    finalPrice = item.coupon_price_usd || item.final_price_usd || item.price_usd;
                } else {
                    finalPrice = item.coupon_price_ars || item.final_price_ars || item.price_ars;
                }

                const itemTotal = finalPrice * item.quantity;
                cartTotal += itemTotal;

                itemsHTML += `
                    <div class="mp-item-row">
                        <span>${item.name} x${item.quantity}</span>
                        <span>$${parseFloat(itemTotal).toFixed(2)}</span>
                    </div>
                `;
            });

            // Mostrar modal
            document.getElementById('mp-order-number').textContent = '(Pendiente de confirmación)';
            document.getElementById('mp-order-items').innerHTML = itemsHTML;

            const currency = checkoutCurrency === 'USD' ? 'U$D' : '$';
            document.getElementById('mp-total').textContent = currency + ' ' + parseFloat(cartTotal).toFixed(2);

            // Limpiar el contenedor del Wallet Brick y mostrar botón personalizado
            const walletContainer = document.getElementById('walletBrick_container');
            walletContainer.innerHTML = `
                <div class="mp-btn-container">
                    <button data-action="processMercadoPagoPayment" class="mp-btn-primary">
                        💳 Pagar con MercadoPago
                    </button>
                </div>
            `;

            // Ocultar loading y error
            document.getElementById('mp-loading').classList.add('hidden');
            document.getElementById('mp-error-message').classList.remove('active');

            // Mostrar el modal
            document.getElementById('mercadopago-modal').classList.add('active');
        }

        // Process MercadoPago payment (called when user clicks "Pagar con MercadoPago" button)
        function processMercadoPagoPayment(event, element, params) {
            // Deshabilitar el botón para evitar múltiples clics
            const btn = element || document.querySelector('[data-action="processMercadoPagoPayment"]');
            if (!btn) return;

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Procesando...';

            // Crear la orden vía AJAX
            const formData = new FormData(document.getElementById('checkout-form'));
            formData.append('place_order', '1');

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Orden creada exitosamente
                    const orderId = data.order_id;
                    const trackingToken = data.tracking_token;

                    // Limpiar carrito (la orden ya se creó)
                    localStorage.removeItem('cart');
                    localStorage.removeItem('cart_timestamp');
                    localStorage.removeItem('applied_coupon');

                    // Ahora crear la preferencia de MercadoPago
                    return fetch('<?php echo url('/api/?endpoint=crear-preferencia-mp'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            order_id: orderId,
                            tracking_token: trackingToken
                        })
                    });
                } else {
                    throw new Error(data.error || 'Error al crear la orden');
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.init_point) {
                    // Redirigir a MercadoPago
                    window.location.href = data.init_point;
                } else {
                    throw new Error(data.error || 'Error al crear la preferencia de pago');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = originalText;

                showModal({
                    title: 'Error',
                    message: error.message || 'Ocurrió un error al procesar tu pago. Por favor intenta nuevamente.',
                    icon: '❌',
                    confirmText: 'Entendido'
                });
            });
        }

        // Create order via AJAX
        function createOrderAndShowMercadopagoModal() {
            const formData = new FormData(document.getElementById('checkout-form'));
            formData.append('place_order', '1');

            const submitBtn = document.getElementById('final-buy-button');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Creando orden...';

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Vaciar el carrito inmediatamente después de crear la orden
                    // (el stock se reserva pero no se descuenta hasta el pago)
                    localStorage.removeItem('cart');
                    localStorage.removeItem('cart_timestamp');
                    localStorage.removeItem('applied_coupon');

                    showMercadopagoModal(data);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                } else {
                    showModal({
                        title: 'Error',
                        message: data.error || 'Error desconocido',
                        icon: '❌',
                        confirmText: 'Entendido'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showModal({
                    title: 'Error',
                    message: 'Por favor intenta nuevamente.',
                    icon: '❌',
                    confirmText: 'Entendido'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        // Show Mercadopago modal
        function showMercadopagoModal(orderData) {
            console.log('Order data received:', orderData);

            document.getElementById('mp-order-number').textContent = '#' + orderData.order_number;

            let itemsHTML = '';
            if (orderData.items && orderData.items.length > 0) {
                orderData.items.forEach(item => {
                    const itemPrice = item.final_price || item.price || 0;
                    itemsHTML += `
                        <div class="mp-item-row">
                            <span>${item.name} x${item.quantity}</span>
                            <span>$${parseFloat(itemPrice).toFixed(2)}</span>
                        </div>
                    `;
                });
            } else {
                console.error('No items in order data');
            }
            document.getElementById('mp-order-items').innerHTML = itemsHTML;

            const currency = orderData.currency === 'USD' ? 'U$D' : '$';
            const total = orderData.total || 0;
            document.getElementById('mp-total').textContent = currency + ' ' + parseFloat(total).toFixed(2);

            document.getElementById('mercadopago-modal').classList.add('active');

            loadMercadopagoWalletBrick(orderData.order_id, orderData.tracking_token);
        }

        // Load Mercadopago Wallet Brick
        function loadMercadopagoWalletBrick(orderId, trackingToken) {
            const loading = document.getElementById('mp-loading');
            const errorMessage = document.getElementById('mp-error-message');

            loading.classList.remove('hidden');
            errorMessage.classList.remove('active');

            fetch('<?php echo url('/api/?endpoint=crear-preferencia-mp'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    tracking_token: trackingToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.preference_id) {
                    throw new Error(data.error || 'Error al crear preferencia');
                }

                const mp = new MercadoPago('<?php echo $mp_public_key; ?>', {
                    locale: 'es-AR'
                });

                const bricksBuilder = mp.bricks();
                document.getElementById('walletBrick_container').innerHTML = '';

                bricksBuilder.create('wallet', 'walletBrick_container', {
                    initialization: {
                        preferenceId: data.preference_id,
                    },
                    customization: {
                        texts: {
                            valueProp: 'security_safety',
                        },
                    },
                    callbacks: {
                        onReady: () => {
                            loading.classList.add('hidden');
                        },
                        onError: (error) => {
                            console.error('Error:', error);
                            errorMessage.textContent = 'Error al cargar el pago.';
                            errorMessage.classList.add('active');
                            loading.classList.add('hidden');
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error:', error);
                loading.classList.add('hidden');

                // Show error in the modal itself
                document.getElementById('walletBrick_container').innerHTML = `
                    <div class="mp-error-container">
                        <p class="mp-error-title">❌ ${error.message || 'Error al preparar el pago'}</p>
                        <p class="mp-error-description">Tu pedido fue creado exitosamente pero hubo un problema al cargar la pasarela de pago.</p>
                        <div class="mp-error-actions">
                            <button id="btn-understood" data-action="handleUnderstood" class="btn btn-success">
                                ✓ Entendido
                            </button>
                            <button id="btn-return-shop" data-action="returnToShop" class="btn btn-primary hidden">
                                🏠 Volver a la Tienda
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        function handleUnderstood() {
            const btnUnderstood = document.getElementById('btn-understood');
            const btnReturnShop = document.getElementById('btn-return-shop');

            // Ocultar botón de entendido
            btnUnderstood.classList.add('hidden');

            // Mostrar botón de volver a tienda con animación
            btnReturnShop.classList.remove('hidden');
            btnReturnShop.classList.add('inline-block');
        }

        function returnToShop() {
            closeMercadopagoModal();
        }

        function closeMercadopagoModal() {
            // Solo cerrar el modal sin redirigir - el usuario se queda en el checkout
            document.getElementById('mercadopago-modal').classList.remove('active');
        }

        // Checkout Timer Functionality
        let checkoutStartTime = <?php echo $_SESSION['checkout_start_time'] ?? time(); ?>;
        const checkoutDuration = 3600; // 1 hour in seconds
        let timerInterval;

        function updateCheckoutTimer() {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - checkoutStartTime;
            const remaining = Math.max(0, checkoutDuration - elapsed);
            
            if (remaining === 0) {
                // Timer expired
                clearInterval(timerInterval);
                document.getElementById('timer-countdown').textContent = '00:00:00';
                document.getElementById('checkout-timer').className = 'checkout-timer danger';
                
                // Show expiration modal and redirect
                showModal({
                    title: '⏰ Tiempo expirado',
                    message: 'Tu sesión de checkout ha expirado (1 hora). Serás redirigido al carrito para iniciar nuevamente.',
                    icon: '⏰',
                    confirmText: 'Entendido',
                    onConfirm: () => {
                        window.location.href = '<?php echo url('/carrito?msg=checkout_expired'); ?>';
                    }
                });
                return;
            }
            
            // Format time as HH:MM:SS
            const hours = Math.floor(remaining / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;
            
            const timeString = 
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
            
            document.getElementById('timer-countdown').textContent = timeString;
            
            // Update timer appearance based on remaining time
            const timerElement = document.getElementById('checkout-timer');
            if (remaining <= 300) { // Last 5 minutes
                timerElement.className = 'checkout-timer danger';
            } else if (remaining <= 900) { // Last 15 minutes
                timerElement.className = 'checkout-timer warning';
            } else {
                timerElement.className = 'checkout-timer';
            }
        }

        // Initialize timer
        timerInterval = setInterval(updateCheckoutTimer, 1000);
        updateCheckoutTimer(); // Update immediately

        // === Wrappers for Event Delegation System ===
        (function() {
            const _toggleStep = toggleStep;
            window.toggleStep = function(eventOrStep, element, params) {
                const step = params?.step ? parseInt(params.step) : (typeof eventOrStep === 'number' ? eventOrStep : null);
                if (step) return _toggleStep(step);
            };

            const _submitOrder = submitOrder;
            window.submitOrder = function(event, element, params) {
                return _submitOrder();
            };

            const _switchCurrency = switchCurrency;
            window.switchCurrency = function(eventOrCurrency, element, params) {
                const currency = params?.currency || (typeof eventOrCurrency === 'string' ? eventOrCurrency : null);
                if (currency) return _switchCurrency(currency);
            };

            const _closeMercadopagoModal = closeMercadopagoModal;
            window.closeMercadopagoModal = function(event, element, params) {
                return _closeMercadopagoModal();
            };

            const _handleUnderstood = handleUnderstood;
            window.handleUnderstood = function(event, element, params) {
                return _handleUnderstood();
            };

            const _returnToShop = returnToShop;
            window.returnToShop = function(event, element, params) {
                return _returnToShop();
            };

            // Exportar processMercadoPagoPayment para event delegation
            window.processMercadoPagoPayment = processMercadoPagoPayment;
        })();
    </script>

    <!-- Mercadopago SDK -->
    <script nonce="<?= csp_nonce() ?>" src="https://sdk.mercadopago.com/js/v2"></script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
