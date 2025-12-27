<?php
/**
 * Orders Management Functions
 * Sistema de gestión de órdenes de compra
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/products.php';

/**
 * Get all orders
 * @return array Orders array
 */
function get_all_orders() {
    $orders_file = __DIR__ . '/../data/orders.json';
    $data = read_json($orders_file);
    return $data['orders'] ?? [];
}

/**
 * Get order by ID
 * @param string $order_id Order ID
 * @return array|null Order data or null
 */
function get_order_by_id($order_id) {
    $orders = get_all_orders();

    foreach ($orders as $order) {
        // Usar comparación flexible para evitar problemas con tipos (string vs int)
        if ($order['id'] == $order_id) {
            return $order;
        }
    }

    return null;
}

/**
 * Get order by tracking token
 * @param string $token Tracking token
 * @return array|null Order data or null
 */
function get_order_by_token($token) {
    $orders = get_all_orders();

    foreach ($orders as $order) {
        if (isset($order['tracking_token']) && $order['tracking_token'] === $token) {
            return $order;
        }
    }

    return null;
}

/**
 * Generate order number (internal - NO escribe al archivo)
 * Esta función solo calcula el próximo número, NO incrementa el contador
 * El contador se incrementa en create_order() bajo file lock
 *
 * @param array $data Datos del archivo orders.json
 * @return array ['number' => 'ORD-2025-00001', 'counter_value' => 1]
 */
function calculate_next_order_number($data) {
    $year = date('Y');

    // Inicializar estructura de contadores si no existe
    if (!isset($data['counters'])) {
        $data['counters'] = [];
    }

    // Obtener o inicializar el contador para el año actual
    if (!isset($data['counters'][$year])) {
        // Si es un año nuevo, buscar el número máximo de órdenes existentes del año
        // para evitar duplicados en caso de migración
        $max_number = 0;
        foreach ($data['orders'] ?? [] as $order) {
            if (preg_match("/^ORD-$year-(\d+)$/", $order['order_number'], $matches)) {
                $number = intval($matches[1]);
                if ($number > $max_number) {
                    $max_number = $number;
                }
            }
        }
        $data['counters'][$year] = $max_number;
    }

    // Incrementar el contador
    $next_counter = $data['counters'][$year] + 1;
    $order_number = sprintf("ORD-%s-%05d", $year, $next_counter);

    return [
        'number' => $order_number,
        'counter_value' => $next_counter
    ];
}

/**
 * Build shipping data structure from order data
 * @param array $order_data Order data
 * @return array|null Shipping data or null
 */
function build_shipping_data($order_data) {
    // If delivery method is pickup, no shipping data needed
    if (($order_data['delivery_method'] ?? 'pickup') === 'pickup') {
        return null;
    }

    // Build shipping address
    $shipping_address = null;
    if (isset($order_data['shipping_address'])) {
        if (is_array($order_data['shipping_address'])) {
            $shipping_address = $order_data['shipping_address'];
        } else {
            // Legacy format - single string
            $shipping_address = [
                'street' => $order_data['shipping_address'],
                'city' => $order_data['shipping_city'] ?? '',
                'province' => $order_data['shipping_province'] ?? '',
                'postal_code' => $order_data['shipping_postal_code'] ?? '',
                'country' => $order_data['shipping_country'] ?? 'AR',
                'notes' => $order_data['shipping_notes'] ?? ''
            ];
        }
    }

    return [
        'method' => $order_data['shipping_service_id'] ?? 'standard',
        'service_name' => $order_data['shipping_service_name'] ?? 'Envío estándar',
        'cost' => (float)($order_data['shipping_cost'] ?? 0),
        'address' => $shipping_address,
        'estimated_delivery' => $order_data['shipping_estimated_days'] ?? null,

        // Multi-carrier fields
        'carrier' => null,                    // Tag del carrier (ZNVA, etc.)
        'carrier_shipment_id' => null,        // ID del envío en el carrier
        'carrier_status' => null,             // Estado raw del carrier API

        // Backward compatibility
        'zipnova_shipment_id' => null,        // Legacy field (deprecated)
        'tracking_id' => null,                // Tracking ID público

        // Estado base del sistema
        'status' => 'pendiente',              // Estado base universal
        'created_at' => null,
        'updated_at' => null,
        'history' => []
    ];
}

/**
 * Create new order
 * @param array $order_data Order data
 * @return array Created order with ID or error
 */
function create_order($order_data) {
    $orders_file = __DIR__ . '/../data/orders.json';

    // Validate required fields
    $required = ['items', 'total', 'currency', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($order_data[$field])) {
            return ['error' => "Missing required field: $field"];
        }
    }

    // Validate stock availability for all items
    foreach ($order_data['items'] as $item) {
        $product = get_product_by_id($item['product_id']);
        if (!$product) {
            return ['error' => "Product not found: {$item['product_id']}"];
        }

        if ($product['stock'] < $item['quantity']) {
            return ['error' => "Insufficient stock for: {$product['name']}"];
        }
    }

    // Generate order ID (but NOT order number yet)
    $order_id = generate_id('order-');

    // CRÍTICO: TODO bajo file lock para atomicidad
    // Ensure directory exists
    $orders_dir = dirname($orders_file);
    if (!is_dir($orders_dir)) {
        if (!mkdir($orders_dir, 0755, true)) {
            error_log("Failed to create orders directory: $orders_dir");
            return ['error' => 'System error: Could not create order (dir)'];
        }
    }

    // Open file for read/write, create if doesn't exist
    $fp = @fopen($orders_file, 'c+');
    if (!$fp) {
        $error_msg = error_get_last();
        error_log("Failed to open orders file: " . ($error_msg['message'] ?? 'unknown error'));
        return ['error' => 'System error: Could not create order (open)'];
    }

    // Acquire exclusive lock (blocks until available, timeout after 10 seconds)
    $lock_start = microtime(true);
    $lock_acquired = false;

    while (!$lock_acquired && (microtime(true) - $lock_start) < 10) {
        $lock_acquired = flock($fp, LOCK_EX | LOCK_NB);
        if (!$lock_acquired) {
            usleep(100000); // Sleep 100ms before retry
        }
    }

    if (!$lock_acquired) {
        fclose($fp);
        error_log("Failed to acquire lock for order creation (timeout after 10s)");
        return ['error' => 'System error: Could not create order (lock timeout)'];
    }

    // Read current data UNDER LOCK
    $content = stream_get_contents($fp);
    $data = $content ? json_decode($content, true) : [];

    if (!isset($data['orders'])) {
        $data['orders'] = [];
    }

    if (!isset($data['counters'])) {
        $data['counters'] = [];
    }

    // Calculate next order number (NO escribe, solo calcula)
    $order_number_data = calculate_next_order_number($data);
    $order_number = $order_number_data['number'];
    $year = date('Y');

    // Update counter in memory
    $data['counters'][$year] = $order_number_data['counter_value'];
    $tracking_token = generate_token(32);
    $timestamp = get_timestamp();

    // Get current exchange rate from currency config
    $currency_config = read_json(__DIR__ . '/../config/currency.json');
    $exchange_rate = $currency_config['exchange_rate'] ?? 1000;

    // Build complete order
    $order = [
        'id' => $order_id,
        'order_number' => $order_number,
        'user_id' => $order_data['user_id'] ?? 'guest',
        'date' => $timestamp,
        'items' => $order_data['items'],
        'currency' => $order_data['currency'],
        'exchange_rate' => $exchange_rate,
        'subtotal' => $order_data['subtotal'],
        'discount_promotion' => $order_data['discount_promotion'] ?? 0,
        'discount_coupon' => $order_data['discount_coupon'] ?? 0,
        'coupon_code' => $order_data['coupon_code'] ?? null,
        'shipping_cost' => $order_data['shipping_cost'] ?? 0,
        'total' => $order_data['total'],
        'status' => 'impago',
        'status_history' => [
            [
                'status' => 'impago',
                'date' => $timestamp,
                'user' => 'system'
            ]
        ],
        'payment_method' => $order_data['payment_method'],
        'payment_status' => 'pending',
        'payment_id' => $order_data['payment_id'] ?? null,
        'payment_link' => $order_data['payment_link'] ?? null,
        'tracking_number' => null,
        'tracking_url' => null,
        'tracking_token' => $tracking_token,
        'shipping_address' => $order_data['shipping_address'] ?? null,
        'customer_email' => $order_data['customer_email'] ?? null,
        'customer_phone' => $order_data['customer_phone'] ?? null,
        'customer_name' => $order_data['customer_name'] ?? null,
        'contact_preference' => $order_data['contact_preference'] ?? 'email',
        'telegram_chat_id' => $order_data['telegram_chat_id'] ?? null,
        'delivery_method' => $order_data['delivery_method'] ?? 'pickup',
        'notes' => $order_data['notes'] ?? '',
        'shipping' => build_shipping_data($order_data),
        'emails_sent' => [
            'confirmation' => false,
            'status_update' => false
        ],
        'stock_reduced' => false
    ];

    // Add order to array IN MEMORY
    $data['orders'][] = $order;

    // Write EVERYTHING (order + counter) in ONE atomic operation
    ftruncate($fp, 0);
    rewind($fp);
    $json_written = fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);

    // Release lock and close file
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($json_written !== false) {
        // IMPORTANT: Stock is NOT reduced when order is created
        // Stock will be reduced when:
        // 1. Payment status is updated to 'completed' (MercadoPago webhook)
        // 2. Admin manually confirms payment and reduces stock
        // This prevents stock being blocked by unpaid orders

        // Log action
        log_admin_action('order_created', 'system', [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total' => $order_data['total'],
            'payment_method' => $order_data['payment_method']
        ]);

        return ['success' => true, 'order' => $order];
    }

    error_log("Failed to write order to file");
    return ['error' => 'Failed to save order'];
}

/**
 * Update order status
 * @param string $order_id Order ID
 * @param string $new_status New status
 * @param string $user User who changed status
 * @return bool Success status
 */
function update_order_status($order_id, $new_status, $user = 'system') {
    $orders_file = __DIR__ . '/../data/orders.json';
    $data = read_json($orders_file);

    if (!isset($data['orders'])) {
        return false;
    }

    $found = false;
    $order_index = null;
    foreach ($data['orders'] as $index => &$order) {
        if ($order['id'] === $order_id) {
            $old_status = $order['status'];
            $order['status'] = $new_status;
            $order['status_history'][] = [
                'status' => $new_status,
                'date' => get_timestamp(),
                'user' => $user
            ];

            // Reduce stock when changing to "pagado" (payment confirmed) if not already reduced
            if ($new_status === 'pagado' && !($order['stock_reduced'] ?? false)) {
                foreach ($order['items'] as $item) {
                    update_stock($item['product_id'], -$item['quantity'], "Orden {$order['order_number']} marcada como pagado (pago confirmado) por {$user}");
                }
                $order['stock_reduced'] = true;

                log_admin_action('stock_reduced_on_payment', $user, [
                    'order_id' => $order_id,
                    'order_number' => $order['order_number']
                ]);
            }

            // Crear envío automáticamente al marcar como pagado si es envío a domicilio
            // y aún no tiene carrier_shipment_id
            if ($new_status === 'pagado' &&
                ($order['delivery_method'] ?? 'pickup') === 'shipping' &&
                empty($order['shipping']['carrier_shipment_id'] ?? null)) {

                // Require carriers.php si no está incluido
                $carriers_file = __DIR__ . '/carriers.php';
                if (file_exists($carriers_file)) {
                    require_once $carriers_file;

                    // Intentar crear el envío en Zipnova
                    try {
                        $result = zipnova_create_shipment($order);

                        if ($result['success']) {
                            // Actualizar orden con los datos del envío
                            if (!isset($order['shipping'])) {
                                $order['shipping'] = [];
                            }
                            $order['shipping']['carrier'] = 'ZNVA';
                            $order['shipping']['carrier_shipment_id'] = $result['data']['shipment_id'] ?? null;
                            $order['shipping']['tracking_id'] = $result['data']['tracking_number'] ?? null;
                            $order['shipping']['status'] = 'pendiente';
                            $order['shipping']['carrier_status'] = $result['data']['status'] ?? 'pending';
                            $order['shipping']['created_at'] = date('Y-m-d H:i:s');

                            log_admin_action('shipment_auto_created', $user, [
                                'order_id' => $order_id,
                                'order_number' => $order['order_number'],
                                'shipment_id' => $result['data']['shipment_id'] ?? null
                            ]);
                        } else {
                            // Log el error pero no fallar la actualización de estado
                            log_admin_action('shipment_auto_creation_failed', $user, [
                                'order_id' => $order_id,
                                'order_number' => $order['order_number'],
                                'error' => $result['error'] ?? 'Unknown error'
                            ]);
                        }
                    } catch (Exception $e) {
                        // Log el error pero continuar
                        error_log("Error al crear envío automático para orden {$order_id}: " . $e->getMessage());
                    }
                }
            }

            // Restore stock when order is cancelled or rejected
            // and stock was previously reduced
            if (in_array($new_status, ['cancelada', 'rechazada']) && ($order['stock_reduced'] ?? false)) {
                foreach ($order['items'] as $item) {
                    update_stock($item['product_id'], $item['quantity'], "Orden {$order['order_number']} cancelada/rechazada por {$user}");
                }
                $order['stock_reduced'] = false;

                log_admin_action('stock_restored_on_cancellation', $user, [
                    'order_id' => $order_id,
                    'order_number' => $order['order_number']
                ]);
            }

            $found = true;
            $order_index = $index;
            break;
        }
    }

    if ($found) {
        write_json($orders_file, $data);
        log_admin_action('order_status_updated', $user, [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
        return true;
    }

    return false;
}

/**
 * Update order payment status
 * @param string $order_id Order ID
 * @param string $payment_status Payment status
 * @param string $payment_id Payment ID (optional)
 * @return bool Success status
 */
function update_order_payment($order_id, $payment_status, $payment_id = null) {
    $orders_file = __DIR__ . '/../data/orders.json';
    $data = read_json($orders_file);

    if (!isset($data['orders'])) {
        return false;
    }

    $found = false;
    foreach ($data['orders'] as &$order) {
        if ($order['id'] === $order_id) {
            $order['payment_status'] = $payment_status;
            if ($payment_id !== null) {
                $order['payment_id'] = $payment_id;
            }
            $found = true;
            break;
        }
    }

    if ($found) {
        return write_json($orders_file, $data);
    }

    return false;
}

/**
 * Add tracking information to order
 * @param string $order_id Order ID
 * @param string $tracking_number Tracking number
 * @param string $tracking_url Tracking URL (optional)
 * @return bool Success status
 */
function add_order_tracking($order_id, $tracking_number, $tracking_url = null) {
    $orders_file = __DIR__ . '/../data/orders.json';
    $data = read_json($orders_file);

    if (!isset($data['orders'])) {
        return false;
    }

    $found = false;
    foreach ($data['orders'] as &$order) {
        if ($order['id'] === $order_id) {
            $order['tracking_number'] = $tracking_number;
            $order['tracking_url'] = $tracking_url;
            $found = true;
            break;
        }
    }

    if ($found) {
        return write_json($orders_file, $data);
    }

    return false;
}

/**
 * Cancel order and restore stock
 * @param string $order_id Order ID
 * @param string $reason Cancellation reason
 * @param string $user User who cancelled
 * @return bool Success status
 */
function cancel_order($order_id, $reason = '', $user = 'system') {
    $order = get_order_by_id($order_id);

    if (!$order) {
        return false;
    }

    // Restore stock for all items
    foreach ($order['items'] as $item) {
        update_stock($item['product_id'], $item['quantity'], "Order {$order['order_number']} cancelled");
    }

    // Update order status
    update_order_status($order_id, 'cancelled', $user);

    // Log action
    log_admin_action('order_cancelled', $user, [
        'order_id' => $order_id,
        'order_number' => $order['order_number'],
        'reason' => $reason
    ]);

    return true;
}

/**
 * Get orders by status
 * @param string $status Order status
 * @return array Orders array
 */
function get_orders_by_status($status) {
    $orders = get_all_orders();
    return array_filter($orders, function($order) use ($status) {
        return $order['status'] === $status;
    });
}

/**
 * Get orders by user ID
 * @param string $user_id User ID
 * @return array Orders array
 */
function get_orders_by_user($user_id) {
    $orders = get_all_orders();
    return array_filter($orders, function($order) use ($user_id) {
        return $order['user_id'] === $user_id;
    });
}

/**
 * Validate coupon code
 * @param string $code Coupon code
 * @param float $subtotal Order subtotal
 * @return array Validation result with discount info or error
 */
function validate_coupon($code, $subtotal) {
    $coupons_file = __DIR__ . '/../data/coupons.json';
    $data = read_json($coupons_file);

    if (!isset($data['coupons'])) {
        return ['valid' => false, 'error' => 'Cupón no encontrado'];
    }

    foreach ($data['coupons'] as $coupon) {
        if (strtoupper($coupon['code']) === strtoupper($code)) {
            // Check if active
            if (!$coupon['active']) {
                return ['valid' => false, 'error' => 'Cupón inactivo'];
            }

            // Check dates
            $now = time();
            $start = strtotime($coupon['start_date']);
            $end = strtotime($coupon['end_date']);

            if ($now < $start || $now > $end) {
                return ['valid' => false, 'error' => 'Cupón expirado o aún no válido'];
            }

            // Check minimum purchase
            if ($subtotal < $coupon['min_purchase']) {
                return [
                    'valid' => false,
                    'error' => 'Compra mínima no alcanzada: ' . format_price($coupon['min_purchase'])
                ];
            }

            // Check usage limit
            if ($coupon['max_uses'] > 0 && $coupon['uses_count'] >= $coupon['max_uses']) {
                return ['valid' => false, 'error' => 'Cupón agotado'];
            }

            // Calculate discount
            $discount = 0;
            if ($coupon['type'] === 'percentage') {
                $discount = ($subtotal * $coupon['value']) / 100;
            } else {
                $discount = $coupon['value'];
            }

            return [
                'valid' => true,
                'coupon' => $coupon,
                'discount' => $discount
            ];
        }
    }

    return ['valid' => false, 'error' => 'Cupón no encontrado'];
}

// increment_coupon_usage() - Ahora definida en coupons.php (evitar duplicación)

/**
 * Archive an order
 * Moves order from orders.json to archived_orders.json
 * @param string $order_id Order ID
 * @return bool Success or failure
 */
function archive_order($order_id) {
    $orders_file = __DIR__ . '/../data/orders.json';
    $archived_file = __DIR__ . '/../data/archived_orders.json';

    // Load orders
    $orders_data = read_json($orders_file);
    $archived_data = read_json($archived_file);

    if (!isset($archived_data['orders'])) {
        $archived_data = ['orders' => []];
    }

    // Find and remove order from orders.json
    $order_to_archive = null;
    foreach ($orders_data['orders'] as $index => $order) {
        if ($order['id'] === $order_id) {
            $order_to_archive = $order;
            $order_to_archive['archived_date'] = date('Y-m-d H:i:s');
            array_splice($orders_data['orders'], $index, 1);
            break;
        }
    }

    if (!$order_to_archive) {
        return false;
    }

    // Add to archived orders
    array_unshift($archived_data['orders'], $order_to_archive);

    // Save both files
    return write_json($orders_file, $orders_data) && write_json($archived_file, $archived_data);
}

/**
 * Get all archived orders
 * @return array Archived orders array
 */
function get_archived_orders() {
    $archived_file = __DIR__ . '/../data/archived_orders.json';
    $data = read_json($archived_file);
    return $data['orders'] ?? [];
}

/**
 * Permanently delete an archived order
 * @param string $order_id Order ID
 * @return bool Success or failure
 */
function delete_archived_order($order_id) {
    $archived_file = __DIR__ . '/../data/archived_orders.json';
    $data = read_json($archived_file);

    if (!isset($data['orders'])) {
        return false;
    }

    foreach ($data['orders'] as $index => $order) {
        if ($order['id'] === $order_id) {
            array_splice($data['orders'], $index, 1);
            return write_json($archived_file, $data);
        }
    }

    return false;
}

/**
 * Restore archived order back to active orders
 * @param string $order_id Order ID
 * @return bool Success or failure
 */
function restore_archived_order($order_id) {
    $orders_file = __DIR__ . '/../data/orders.json';
    $archived_file = __DIR__ . '/../data/archived_orders.json';

    // Load both files
    $orders_data = read_json($orders_file);
    $archived_data = read_json($archived_file);

    if (!isset($archived_data['orders'])) {
        return false;
    }

    // Find and remove order from archived
    $order_to_restore = null;
    foreach ($archived_data['orders'] as $index => $order) {
        if ($order['id'] === $order_id) {
            $order_to_restore = $order;
            unset($order_to_restore['archived_date']); // Remove archived date
            array_splice($archived_data['orders'], $index, 1);
            break;
        }
    }

    if (!$order_to_restore) {
        return false;
    }

    // Add back to active orders
    array_unshift($orders_data['orders'], $order_to_restore);

    // Save both files
    return write_json($orders_file, $orders_data) && write_json($archived_file, $archived_data);
}

/**
 * Create shipment in Zipnova for an order
 * @param string $order_id Order ID
 * @return array Result with success status
 */
function create_zipnova_shipment_for_order($order_id) {
    require_once __DIR__ . '/zipnova.php';

    // Check if Zipnova is enabled and configured to auto-create
    $config = zipnova_get_config();
    if (!$config || !$config['enabled'] || !($config['options']['auto_create_shipment'] ?? false)) {
        return ['success' => false, 'error' => 'Zipnova no está habilitado o auto-creación desactivada'];
    }

    // Get order
    $order = get_order_by_id($order_id);
    if (!$order) {
        return ['success' => false, 'error' => 'Orden no encontrada'];
    }

    // Check if order has shipping data
    if (!isset($order['shipping']) || !$order['shipping']) {
        return ['success' => false, 'error' => 'La orden no tiene datos de envío'];
    }

    // Check if shipment already created (check both new and legacy fields)
    if (!empty($order['shipping']['carrier_shipment_id']) || !empty($order['shipping']['zipnova_shipment_id'])) {
        return ['success' => false, 'error' => 'El envío ya fue creado en Zipnova'];
    }

    // Build shipment data
    $shipment_data = [
        'service_id' => $order['shipping']['method'],
        'destination' => [
            'name' => $order['customer_name'],
            'address' => $order['shipping']['address']['street'],
            'city' => $order['shipping']['address']['city'],
            'province' => $order['shipping']['address']['province'],
            'postal_code' => $order['shipping']['address']['postal_code'],
            'country' => $order['shipping']['address']['country'],
            'phone' => $order['customer_phone'],
            'email' => $order['customer_email']
        ],
        'customer' => [
            'name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'phone' => $order['customer_phone']
        ],
        'reference' => $order['order_number'],
        'packages' => [[
            'declared_value' => $order['total']
        ]]
    ];

    // Create shipment in Zipnova
    $result = zipnova_create_shipment($shipment_data);

    if ($result['success']) {
        // Update order with shipment info
        $shipment_id = $result['data']['id'] ?? null;
        $tracking_id = $result['data']['tracking_id'] ?? $shipment_id;
        $carrier_status = $result['data']['status'] ?? 'pending';

        // Get carrier tag from config
        $carrier_tag = $config['tag'] ?? 'ZNVA';

        // Map carrier status to base status
        $base_status = map_carrier_status_to_base('zipnova', $carrier_status);

        update_order_shipping_info($order_id, [
            // Multi-carrier fields
            'carrier' => $carrier_tag,
            'carrier_shipment_id' => $shipment_id,
            'carrier_status' => $carrier_status,

            // Legacy backward compatibility
            'zipnova_shipment_id' => $shipment_id,
            'tracking_id' => $tracking_id,

            // Base status
            'status' => $base_status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    return $result;
}

/**
 * Update order shipping information
 * @param string $order_id Order ID
 * @param array $shipping_info Shipping info to update
 * @return bool Success status
 */
function update_order_shipping_info($order_id, $shipping_info) {
    $orders_file = __DIR__ . '/../data/orders.json';
    $data = read_json($orders_file);

    if (!isset($data['orders'])) {
        return false;
    }

    foreach ($data['orders'] as &$order) {
        if ($order['id'] === $order_id) {
            if (!isset($order['shipping'])) {
                $order['shipping'] = [];
            }

            // Update shipping fields
            foreach ($shipping_info as $key => $value) {
                $order['shipping'][$key] = $value;
            }

            // Update timestamp
            $order['shipping']['updated_at'] = date('Y-m-d H:i:s');

            // Add to history
            if (!isset($order['shipping']['history'])) {
                $order['shipping']['history'] = [];
            }
            $order['shipping']['history'][] = [
                'date' => date('Y-m-d H:i:s'),
                'updates' => $shipping_info
            ];

            return write_json($orders_file, $data);
        }
    }

    return false;
}

/**
 * Update shipping status from webhook
 * @param string $tracking_id Tracking ID
 * @param string $new_status New status
 * @param array $extra_data Extra data from webhook
 * @return bool Success status
 */
function update_shipping_status_by_tracking($tracking_id, $new_status, $extra_data = []) {
    $orders_file = __DIR__ . '/../data/orders.json';
    $data = read_json($orders_file);

    if (!isset($data['orders'])) {
        return false;
    }

    foreach ($data['orders'] as &$order) {
        if (isset($order['shipping']['tracking_id']) && $order['shipping']['tracking_id'] === $tracking_id) {
            // Get carrier type to map status correctly
            $carrier = $order['shipping']['carrier'] ?? null;
            $carrier_type = 'zipnova'; // Default, can be extended later

            if ($carrier) {
                require_once __DIR__ . '/zipnova.php';
                $carrier_config = get_carrier_config($carrier);
                $carrier_type = $carrier_config['type'] ?? 'zipnova';
            }

            // Save raw carrier status
            $order['shipping']['carrier_status'] = $new_status;

            // Map to base status
            require_once __DIR__ . '/zipnova.php';
            $base_status = map_carrier_status_to_base($carrier_type, $new_status);
            $order['shipping']['status'] = $base_status;
            $order['shipping']['updated_at'] = date('Y-m-d H:i:s');

            // Add to history
            if (!isset($order['shipping']['history'])) {
                $order['shipping']['history'] = [];
            }
            $order['shipping']['history'][] = [
                'date' => date('Y-m-d H:i:s'),
                'carrier_status' => $new_status,
                'status' => $base_status,
                'data' => $extra_data
            ];

            // TODO: Send notification to customer about status change

            return write_json($orders_file, $data);
        }
    }

    return false;
}
