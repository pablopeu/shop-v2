<?php
// Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
/**
 * Ventas Filters - Filtrado y búsqueda de órdenes
 * Maneja todos los filtros aplicados a las órdenes
 */

// Prevent direct access
if (!defined('ADMIN_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Get filter parameters from GET request
 * @return array Filter parameters
 */
function get_filter_params() {
    return [
        'status' => $_GET['filter'] ?? 'all',
        'search' => $_GET['search'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? ''
    ];
}

/**
 * Apply filters to orders array
 * @param array $all_orders All orders from database
 * @param array $filters Filter parameters
 * @return array Filtered and sorted orders
 */
function apply_order_filters($all_orders, $filters) {
    $orders = $all_orders;

    // Apply status filter
    if ($filters['status'] !== 'all') {
        $orders = array_filter($orders, function($order) use ($filters) {
            return $order['status'] === $filters['status'];
        });
    }

    // Apply search filter (order number or customer name/email)
    if (!empty($filters['search'])) {
        $orders = array_filter($orders, function($order) use ($filters) {
            $search_lower = mb_strtolower($filters['search']);
            return stripos($order['order_number'], $filters['search']) !== false ||
                   stripos(mb_strtolower($order['customer_name'] ?? ''), $search_lower) !== false ||
                   stripos(mb_strtolower($order['customer_email'] ?? ''), $search_lower) !== false;
        });
    }

    // Apply date from filter
    if (!empty($filters['date_from'])) {
        $orders = array_filter($orders, function($order) use ($filters) {
            return strtotime($order['date']) >= strtotime($filters['date_from'] . ' 00:00:00');
        });
    }

    // Apply date to filter
    if (!empty($filters['date_to'])) {
        $orders = array_filter($orders, function($order) use ($filters) {
            return strtotime($order['date']) <= strtotime($filters['date_to'] . ' 23:59:59');
        });
    }

    // Sort by date (newest first)
    usort($orders, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    return $orders;
}
