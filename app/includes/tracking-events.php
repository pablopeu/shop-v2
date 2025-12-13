<?php
/**
 * Tracking Event Functions
 * Helper functions to track events in Google Analytics and Facebook Pixel
 */

/**
 * Track Add to Cart event
 * @param array $product Product data
 * @param int $quantity Quantity added
 */
function track_add_to_cart($product, $quantity = 1) {
    $analytics_config = read_json(__DIR__ . '/../config/analytics.json');

    if (!($analytics_config['facebook_pixel']['enabled'] ?? false) ||
        !($analytics_config['facebook_pixel']['track_add_to_cart'] ?? true)) {
        return;
    }

    $product_id = htmlspecialchars($product['id'] ?? '');
    $product_name = htmlspecialchars($product['name'] ?? '');
    $price = floatval($product['price_ars'] ?? 0);
    $value = $price * $quantity;

    ?>
    <script>
        if (typeof fbq !== 'undefined') {
            fbq('track', 'AddToCart', {
                content_ids: ['<?php echo $product_id; ?>'],
                content_type: 'product',
                content_name: '<?php echo $product_name; ?>',
                value: <?php echo $value; ?>,
                currency: 'ARS'
            });
        }
    </script>
    <?php
}

/**
 * Track Initiate Checkout event
 * @param array $cart_items Cart items
 * @param float $total Total amount
 */
function track_initiate_checkout($cart_items, $total) {
    $analytics_config = read_json(__DIR__ . '/../config/analytics.json');

    if (!($analytics_config['facebook_pixel']['enabled'] ?? false) ||
        !($analytics_config['facebook_pixel']['track_initiate_checkout'] ?? true)) {
        return;
    }

    $content_ids = array_map(function($item) {
        return htmlspecialchars($item['id'] ?? '');
    }, $cart_items);

    ?>
    <script>
        if (typeof fbq !== 'undefined') {
            fbq('track', 'InitiateCheckout', {
                content_ids: <?php echo json_encode($content_ids); ?>,
                content_type: 'product',
                value: <?php echo floatval($total); ?>,
                currency: 'ARS',
                num_items: <?php echo count($cart_items); ?>
            });
        }
    </script>
    <?php
}

/**
 * Track Purchase event
 * @param array $order Order data
 */
function track_purchase($order) {
    $analytics_config = read_json(__DIR__ . '/../config/analytics.json');

    if (!($analytics_config['facebook_pixel']['enabled'] ?? false) ||
        !($analytics_config['facebook_pixel']['track_purchase'] ?? true)) {
        return;
    }

    $order_id = htmlspecialchars($order['order_number'] ?? '');
    $total = floatval($order['total'] ?? 0);

    $content_ids = array_map(function($item) {
        return htmlspecialchars($item['product_id'] ?? '');
    }, $order['items'] ?? []);

    ?>
    <script>
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Purchase', {
                content_ids: <?php echo json_encode($content_ids); ?>,
                content_type: 'product',
                value: <?php echo $total; ?>,
                currency: 'ARS',
                num_items: <?php echo count($order['items'] ?? []); ?>
            });
        }

        // Google Analytics Enhanced Ecommerce
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                transaction_id: '<?php echo $order_id; ?>',
                value: <?php echo $total; ?>,
                currency: 'ARS',
                items: <?php echo json_encode(array_map(function($item) {
                    return [
                        'id' => $item['product_id'] ?? '',
                        'name' => $item['name'] ?? '',
                        'quantity' => $item['quantity'] ?? 1,
                        'price' => $item['final_price'] ?? 0
                    ];
                }, $order['items'] ?? [])); ?>
            });
        }
    </script>
    <?php
}

/**
 * Track View Content event (Product page view)
 * @param array $product Product data
 */
function track_view_content($product) {
    $analytics_config = read_json(__DIR__ . '/../config/analytics.json');

    if (!($analytics_config['facebook_pixel']['enabled'] ?? false)) {
        return;
    }

    $product_id = htmlspecialchars($product['id'] ?? '');
    $product_name = htmlspecialchars($product['name'] ?? '');
    $price = floatval($product['price_ars'] ?? 0);

    ?>
    <script>
        if (typeof fbq !== 'undefined') {
            fbq('track', 'ViewContent', {
                content_ids: ['<?php echo $product_id; ?>'],
                content_type: 'product',
                content_name: '<?php echo $product_name; ?>',
                value: <?php echo $price; ?>,
                currency: 'ARS'
            });
        }
    </script>
    <?php
}
?>
