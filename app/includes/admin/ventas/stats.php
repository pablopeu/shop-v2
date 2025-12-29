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

    // 2. Unpaid Orders (Impago): count + total amount in pesos
    $unpaid_orders_data = array_filter($non_archived_orders, fn($o) => $o['status'] === 'impago');
    $unpaid_orders = count($unpaid_orders_data);
    $unpaid_amount = array_reduce($unpaid_orders_data, function($sum, $order) {
        return $sum + floatval($order['total']);
    }, 0);

    // 3. Paid Orders (Pagado): count + gross amount (without discounting fees)
    $paid_orders = array_filter($non_archived_orders, fn($o) => $o['status'] === 'pagado');
    $confirmed_orders = count($paid_orders);
    $paid_amount_gross = array_reduce($paid_orders, function($sum, $order) {
        return $sum + floatval($order['total']);
    }, 0);

    // 4. Total Fees: sum of all MP fees from non-archived paid orders
    $total_fees = array_reduce($paid_orders, function($sum, $order) {
        if (isset($order['mercadopago_data']['total_fees'])) {
            return $sum + floatval($order['mercadopago_data']['total_fees']);
        }
        return $sum;
    }, 0);

    // 5. Net Revenue: paid amount - fees
    $net_revenue = array_reduce($paid_orders, function($sum, $order) {
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
        'unpaid_orders' => $unpaid_orders,
        'unpaid_amount' => $unpaid_amount,
        'confirmed_orders' => $confirmed_orders,
        'paid_amount_gross' => $paid_amount_gross,
        'total_fees' => $total_fees,
        'net_revenue' => $net_revenue
    ];
}
