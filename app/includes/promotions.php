<?php
/**
 * Promotions Management Functions
 */

/**
 * Get all promotions
 * @param bool $active_only - Only return active promotions
 * @return array
 */
function get_all_promotions($active_only = false) {
    $promotions_data = read_json(__DIR__ . '/../data/promotions.json');
    $promotions = $promotions_data['promotions'] ?? [];

    if ($active_only) {
        $now = time();
        $promotions = array_filter($promotions, function($promo) use ($now) {
            if (!$promo['active']) return false;

            // Check if promotion is within valid period
            if ($promo['period_type'] === 'limited') {
                $start = strtotime($promo['start_date']);
                $end = strtotime($promo['end_date']);
                return $now >= $start && $now <= $end;
            }

            return true;
        });
    }

    return array_values($promotions);
}

/**
 * Get promotion by ID
 * @param string $promotion_id
 * @return array|null
 */
function get_promotion_by_id($promotion_id) {
    $promotions = get_all_promotions();
    foreach ($promotions as $promotion) {
        if ($promotion['id'] === $promotion_id) {
            return $promotion;
        }
    }
    return null;
}

/**
 * Create a new promotion
 * @param array $promotion_data
 * @return bool
 */
function create_promotion($promotion_data) {
    $promotions_data = read_json(__DIR__ . '/../data/promotions.json');

    // Generate unique ID
    $promotion_id = 'promo_' . uniqid();

    // Prepare promotion object
    $promotion = [
        'id' => $promotion_id,
        'name' => $promotion_data['name'],
        'type' => $promotion_data['type'], // 'percentage' or 'fixed'
        'value' => floatval($promotion_data['value']),
        'application' => $promotion_data['application'], // 'all' or 'specific'
        'products' => $promotion_data['products'] ?? [], // Array of product IDs
        'condition_type' => $promotion_data['condition_type'], // 'any' or 'minimum'
        'minimum_amount' => floatval($promotion_data['minimum_amount'] ?? 0),
        'period_type' => $promotion_data['period_type'], // 'permanent' or 'limited'
        'start_date' => $promotion_data['start_date'] ?? null,
        'end_date' => $promotion_data['end_date'] ?? null,
        'active' => isset($promotion_data['active']) ? true : false,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'updated_at' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    $promotions_data['promotions'][] = $promotion;

    return write_json(__DIR__ . '/../data/promotions.json', $promotions_data);
}

/**
 * Update an existing promotion
 * @param string $promotion_id
 * @param array $promotion_data
 * @return bool
 */
function update_promotion($promotion_id, $promotion_data) {
    $promotions_data = read_json(__DIR__ . '/../data/promotions.json');

    foreach ($promotions_data['promotions'] as &$promotion) {
        if ($promotion['id'] === $promotion_id) {
            $promotion['name'] = $promotion_data['name'];
            $promotion['type'] = $promotion_data['type'];
            $promotion['value'] = floatval($promotion_data['value']);
            $promotion['application'] = $promotion_data['application'];
            $promotion['products'] = $promotion_data['products'] ?? [];
            $promotion['condition_type'] = $promotion_data['condition_type'];
            $promotion['minimum_amount'] = floatval($promotion_data['minimum_amount'] ?? 0);
            $promotion['period_type'] = $promotion_data['period_type'];
            $promotion['start_date'] = $promotion_data['start_date'] ?? null;
            $promotion['end_date'] = $promotion_data['end_date'] ?? null;
            $promotion['active'] = (bool)($promotion_data['active'] ?? false);
            $promotion['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');

            return write_json(__DIR__ . '/../data/promotions.json', $promotions_data);
        }
    }

    return false;
}

/**
 * Delete a promotion
 * @param string $promotion_id
 * @return bool
 */
function delete_promotion($promotion_id) {
    $promotions_data = read_json(__DIR__ . '/../data/promotions.json');

    $promotions_data['promotions'] = array_filter($promotions_data['promotions'], function($promo) use ($promotion_id) {
        return $promo['id'] !== $promotion_id;
    });
    $promotions_data['promotions'] = array_values($promotions_data['promotions']);

    return write_json(__DIR__ . '/../data/promotions.json', $promotions_data);
}

/**
 * Archive promotion
 */
function archive_promotion($promotion_id) {
    $promotion = get_promotion_by_id($promotion_id);
    if (!$promotion) return false;

    // Add to archived promotions
    $archived_file = __DIR__ . '/../data/archived_promotions.json';
    $archived_data = read_json($archived_file);
    if (!isset($archived_data['promotions'])) {
        $archived_data = ['promotions' => []];
    }

    $promotion['archived_at'] = gmdate('Y-m-d\TH:i:s\Z');
    $archived_data['promotions'][] = $promotion;
    write_json($archived_file, $archived_data);

    // Remove from active promotions
    return delete_promotion($promotion_id);
}

/**
 * Get archived promotions
 */
function get_archived_promotions() {
    $archived_file = __DIR__ . '/../data/archived_promotions.json';
    $archived_data = read_json($archived_file);
    return $archived_data['promotions'] ?? [];
}

/**
 * Restore archived promotion
 */
function restore_promotion($promotion_id) {
    $archived_file = __DIR__ . '/../data/archived_promotions.json';
    $archived_data = read_json($archived_file);

    $promotion_to_restore = null;
    foreach ($archived_data['promotions'] ?? [] as $index => $promotion) {
        if ($promotion['id'] === $promotion_id) {
            $promotion_to_restore = $promotion;
            unset($archived_data['promotions'][$index]);
            break;
        }
    }

    if (!$promotion_to_restore) return false;

    // Remove archived_at field
    unset($promotion_to_restore['archived_at']);
    $archived_data['promotions'] = array_values($archived_data['promotions']);
    write_json($archived_file, $archived_data);

    // Add back to active promotions
    $promotions_file = __DIR__ . '/../data/promotions.json';
    $promotions_data = read_json($promotions_file);
    if (!isset($promotions_data['promotions'])) {
        $promotions_data = ['promotions' => []];
    }
    $promotions_data['promotions'][] = $promotion_to_restore;

    return write_json($promotions_file, $promotions_data);
}

/**
 * Delete archived promotion permanently
 */
function delete_archived_promotion($promotion_id) {
    $archived_file = __DIR__ . '/../data/archived_promotions.json';
    $archived_data = read_json($archived_file);

    $initial_count = count($archived_data['promotions'] ?? []);
    $archived_data['promotions'] = array_filter($archived_data['promotions'] ?? [], function($p) use ($promotion_id) {
        return $p['id'] !== $promotion_id;
    });
    $archived_data['promotions'] = array_values($archived_data['promotions']);

    $deleted = count($archived_data['promotions']) < $initial_count;

    if ($deleted && write_json($archived_file, $archived_data)) {
        return ['success' => true, 'message' => 'Promoción eliminada permanentemente'];
    }

    return ['success' => false, 'message' => 'Error al eliminar la promoción'];
}

/**
 * Get applicable promotions for a cart
 * @param array $cart_items
 * @param float $subtotal
 * @return array
 */
function get_applicable_promotions($cart_items, $subtotal) {
    $active_promotions = get_all_promotions(true);
    $applicable = [];

    foreach ($active_promotions as $promo) {
        // Check minimum amount condition
        if ($promo['condition_type'] === 'minimum' && $subtotal < $promo['minimum_amount']) {
            continue;
        }

        // Check product applicability
        if ($promo['application'] === 'specific') {
            $has_applicable_product = false;
            foreach ($cart_items as $item) {
                if (in_array($item['product_id'], $promo['products'])) {
                    $has_applicable_product = true;
                    break;
                }
            }
            if (!$has_applicable_product) {
                continue;
            }
        }

        $applicable[] = $promo;
    }

    return $applicable;
}

/**
 * Calculate promotion discount
 * @param array $promotion
 * @param float $subtotal
 * @return float
 */
function calculate_promotion_discount($promotion, $subtotal) {
    if ($promotion['type'] === 'percentage') {
        return ($subtotal * $promotion['value']) / 100;
    } else {
        return min($promotion['value'], $subtotal); // Don't discount more than subtotal
    }
}

/**
 * Get active promotion for a specific product
 * @param string $product_id
 * @return array|null - Returns promotion object or null if no active promotion
 */
function get_active_promotion_for_product($product_id) {
    $active_promotions = get_all_promotions(true);

    foreach ($active_promotions as $promo) {
        // Check if promotion applies to all products
        if ($promo['application'] === 'all') {
            return $promo;
        }

        // Check if promotion applies to this specific product
        if ($promo['application'] === 'specific' && in_array($product_id, $promo['products'])) {
            return $promo;
        }
    }

    return null;
}
