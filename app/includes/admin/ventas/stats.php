<?php
// Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
/**
 * Ventas Stats - Cálculo de estadísticas
 * Calcula todas las métricas del panel de ventas
 */

// Prevent direct access
if (!defined('ADMIN_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Calculate order statistics for dashboard
 * @param array $all_orders All orders (including archived)
 * @return array Statistics array with all metrics
 */
function calculate_order_stats($all_orders) {
    // Filter out archived orders for statistics
    $non_archived_orders = array_filter($all_orders, fn($o) => !($o['archived'] ?? false));

    // 1. Total Orders: count + total amount in pesos (all non-archived orders, any status)
    $total_orders = count($non_archived_orders);
    $total_orders_amount = array_reduce($non_archived_orders, function($sum, $order) {
        return $sum + floatval($order['total']);
    }, 0);

    // 2. Pending Orders: count + total amount in pesos
    $pending_orders_data = array_filter($non_archived_orders, fn($o) => $o['status'] === 'pending' || $o['status'] === 'pendiente');
    $pending_orders = count($pending_orders_data);
    $pending_amount = array_reduce($pending_orders_data, function($sum, $order) {
        return $sum + floatval($order['total']);
    }, 0);

    // 3. Cobradas (Confirmed): count + gross amount (without discounting fees)
    $cobradas_orders = array_filter($non_archived_orders, fn($o) => $o['status'] === 'cobrada');
    $confirmed_orders = count($cobradas_orders);
    $cobradas_amount_gross = array_reduce($cobradas_orders, function($sum, $order) {
        return $sum + floatval($order['total']);
    }, 0);

    // 4. Total Fees: sum of all MP fees from non-archived collected orders
    $total_fees = array_reduce($cobradas_orders, function($sum, $order) {
        if (isset($order['mercadopago_data']['total_fees'])) {
            return $sum + floatval($order['mercadopago_data']['total_fees']);
        }
        return $sum;
    }, 0);

    // 5. Net Revenue: collected amount - fees
    $net_revenue = array_reduce($cobradas_orders, function($sum, $order) {
        if (isset($order['mercadopago_data']['net_received_amount'])) {
            return $sum + floatval($order['mercadopago_data']['net_received_amount']);
        } else {
            // For presencial payments or orders without MP data, use full total
            return $sum + floatval($order['total']);
        }
    }, 0);

    return [
        'total_orders' => $total_orders,
        'total_orders_amount' => $total_orders_amount,
        'pending_orders' => $pending_orders,
        'pending_amount' => $pending_amount,
        'confirmed_orders' => $confirmed_orders,
        'cobradas_amount_gross' => $cobradas_amount_gross,
        'total_fees' => $total_fees,
        'net_revenue' => $net_revenue
    ];
}
