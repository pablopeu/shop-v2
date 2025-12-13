<?php
/**
 * Coupons Management Functions
 */

/**
 * Get all coupons
 */
function get_all_coupons($active_only = false) {
    $coupons_data = read_json(__DIR__ . '/../data/coupons.json');
    $coupons = $coupons_data['coupons'] ?? [];

    if ($active_only) {
        $coupons = array_filter($coupons, fn($c) => $c['active']);
    }

    return $coupons;
}

/**
 * Get coupon by ID
 */
function get_coupon_by_id($coupon_id) {
    $coupons = get_all_coupons(false);

    foreach ($coupons as $coupon) {
        if ($coupon['id'] === $coupon_id) {
            return $coupon;
        }
    }

    return null;
}

/**
 * Get coupon by code
 */
function get_coupon_by_code($code) {
    $coupons = get_all_coupons(false);

    foreach ($coupons as $coupon) {
        if (strtoupper($coupon['code']) === strtoupper($code)) {
            return $coupon;
        }
    }

    return null;
}

/**
 * Create new coupon
 */
function create_coupon($coupon_data) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    // Check if code already exists
    if (get_coupon_by_code($coupon_data['code'])) {
        return [
            'success' => false,
            'error' => 'Ya existe un cupón con ese código'
        ];
    }

    // Generate unique ID
    $coupon_id = 'coupon-' . uniqid('', true) . '-' . bin2hex(random_bytes(4));

    // Prepare coupon
    $new_coupon = [
        'id' => $coupon_id,
        'code' => strtoupper($coupon_data['code']),
        'type' => $coupon_data['type'], // percentage or fixed
        'value' => floatval($coupon_data['value']),
        'min_purchase' => floatval($coupon_data['min_purchase'] ?? 0),
        'max_uses' => intval($coupon_data['max_uses'] ?? 0),
        'uses_count' => 0,
        'one_per_user' => $coupon_data['one_per_user'] ?? false,
        'start_date' => $coupon_data['start_date'],
        'end_date' => $coupon_data['end_date'],
        'applicable_to' => $coupon_data['applicable_to'] ?? 'all', // all or specific
        'products' => $coupon_data['products'] ?? [],
        'not_combinable' => $coupon_data['not_combinable'] ?? false,
        'active' => $coupon_data['active'] ?? true,
        'created_by' => $_SESSION['username'] ?? 'admin',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    // Add to array
    $coupons_data['coupons'][] = $new_coupon;

    // Save
    if (write_json($file_path, $coupons_data)) {
        return [
            'success' => true,
            'coupon' => $new_coupon
        ];
    }

    return [
        'success' => false,
        'error' => 'Error al crear el cupón'
    ];
}

/**
 * Update coupon
 */
function update_coupon($coupon_id, $coupon_data) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    $found = false;
    foreach ($coupons_data['coupons'] as &$coupon) {
        if ($coupon['id'] === $coupon_id) {
            // Check if changing code and if it already exists
            if (isset($coupon_data['code']) && $coupon_data['code'] !== $coupon['code']) {
                $existing = get_coupon_by_code($coupon_data['code']);
                if ($existing && $existing['id'] !== $coupon_id) {
                    return false; // Code already exists
                }
            }

            // Update fields
            if (isset($coupon_data['code'])) {
                $coupon['code'] = strtoupper($coupon_data['code']);
            }
            if (isset($coupon_data['type'])) {
                $coupon['type'] = $coupon_data['type'];
            }
            if (isset($coupon_data['value'])) {
                $coupon['value'] = floatval($coupon_data['value']);
            }
            if (isset($coupon_data['min_purchase'])) {
                $coupon['min_purchase'] = floatval($coupon_data['min_purchase']);
            }
            if (isset($coupon_data['max_uses'])) {
                $coupon['max_uses'] = intval($coupon_data['max_uses']);
            }
            if (isset($coupon_data['one_per_user'])) {
                $coupon['one_per_user'] = $coupon_data['one_per_user'];
            }
            if (isset($coupon_data['start_date'])) {
                $coupon['start_date'] = $coupon_data['start_date'];
            }
            if (isset($coupon_data['end_date'])) {
                $coupon['end_date'] = $coupon_data['end_date'];
            }
            if (isset($coupon_data['applicable_to'])) {
                $coupon['applicable_to'] = $coupon_data['applicable_to'];
            }
            if (isset($coupon_data['products'])) {
                $coupon['products'] = $coupon_data['products'];
            }
            if (isset($coupon_data['not_combinable'])) {
                $coupon['not_combinable'] = $coupon_data['not_combinable'];
            }
            if (isset($coupon_data['active'])) {
                $coupon['active'] = $coupon_data['active'];
            }

            $found = true;
            break;
        }
    }

    if (!$found) {
        return false;
    }

    return write_json($file_path, $coupons_data);
}

/**
 * Delete coupon
 */
function delete_coupon($coupon_id) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    $coupons_data['coupons'] = array_filter($coupons_data['coupons'], function($c) use ($coupon_id) {
        return $c['id'] !== $coupon_id;
    });
    $coupons_data['coupons'] = array_values($coupons_data['coupons']);

    return write_json($file_path, $coupons_data);
}

/**
 * Archive coupon
 */
function archive_coupon($coupon_id) {
    $coupon = get_coupon_by_id($coupon_id);
    if (!$coupon) return false;

    // Add to archived coupons
    $archived_file = __DIR__ . '/../data/archived_coupons.json';
    $archived_data = read_json($archived_file);
    if (!isset($archived_data['coupons'])) {
        $archived_data = ['coupons' => []];
    }

    $coupon['archived_at'] = gmdate('Y-m-d\TH:i:s\Z');
    $archived_data['coupons'][] = $coupon;
    write_json($archived_file, $archived_data);

    // Remove from active coupons
    return delete_coupon($coupon_id);
}

/**
 * Get archived coupons
 */
function get_archived_coupons() {
    $archived_file = __DIR__ . '/../data/archived_coupons.json';
    $archived_data = read_json($archived_file);
    return $archived_data['coupons'] ?? [];
}

/**
 * Restore archived coupon
 */
function restore_coupon($coupon_id) {
    $archived_file = __DIR__ . '/../data/archived_coupons.json';
    $archived_data = read_json($archived_file);

    $coupon_to_restore = null;
    foreach ($archived_data['coupons'] ?? [] as $index => $coupon) {
        if ($coupon['id'] === $coupon_id) {
            $coupon_to_restore = $coupon;
            unset($archived_data['coupons'][$index]);
            break;
        }
    }

    if (!$coupon_to_restore) return false;

    // Remove archived_at field
    unset($coupon_to_restore['archived_at']);
    $archived_data['coupons'] = array_values($archived_data['coupons']);
    write_json($archived_file, $archived_data);

    // Add back to active coupons
    $coupons_file = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($coupons_file);
    if (!isset($coupons_data['coupons'])) {
        $coupons_data = ['coupons' => []];
    }
    $coupons_data['coupons'][] = $coupon_to_restore;

    return write_json($coupons_file, $coupons_data);
}

/**
 * Delete archived coupon permanently
 */
function delete_archived_coupon($coupon_id) {
    $archived_file = __DIR__ . '/../data/archived_coupons.json';
    $archived_data = read_json($archived_file);

    $initial_count = count($archived_data['coupons'] ?? []);
    $archived_data['coupons'] = array_filter($archived_data['coupons'] ?? [], function($c) use ($coupon_id) {
        return $c['id'] !== $coupon_id;
    });
    $archived_data['coupons'] = array_values($archived_data['coupons']);

    $deleted = count($archived_data['coupons']) < $initial_count;

    if ($deleted && write_json($archived_file, $archived_data)) {
        return ['success' => true, 'message' => 'Cupón eliminado permanentemente'];
    }

    return ['success' => false, 'message' => 'Error al eliminar el cupón'];
}

/**
 * Increment coupon usage
 */
function increment_coupon_usage($coupon_id) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    foreach ($coupons_data['coupons'] as &$coupon) {
        if ($coupon['id'] === $coupon_id) {
            $coupon['uses_count']++;
            break;
        }
    }

    return write_json($file_path, $coupons_data);
}

/**
 * Apply coupon to order (checkout)
 * Returns discount in both ARS and USD
 *
 * @param string $code Coupon code
 * @param float $subtotal_ars Subtotal in ARS
 * @param float $subtotal_usd Subtotal in USD
 * @param array $cart_items Cart items (optional, for future product-specific coupons)
 * @return array ['valid' => bool, 'discount_ars' => float, 'discount_usd' => float, 'message' => string]
 */
function apply_coupon_to_order($code, $subtotal_ars, $subtotal_usd, $cart_items = []) {
    error_log("apply_coupon_to_order: Buscando cupón '$code'");
    $coupon = get_coupon_by_code($code);

    // Coupon not found
    if (!$coupon) {
        error_log("apply_coupon_to_order: Cupón NO ENCONTRADO");
        return [
            'valid' => false,
            'discount_ars' => 0,
            'discount_usd' => 0,
            'message' => 'Cupón no válido'
        ];
    }

    error_log("apply_coupon_to_order: Cupón encontrado - Active: " . ($coupon['active'] ? 'SI' : 'NO'));

    // Check if coupon is active
    if (!$coupon['active']) {
        error_log("apply_coupon_to_order: Cupón NO ACTIVO");
        return [
            'valid' => false,
            'discount_ars' => 0,
            'discount_usd' => 0,
            'message' => 'Cupón no activo'
        ];
    }

    // Check expiration date
    $now = time();
    $end_date = strtotime($coupon['end_date']);
    error_log("apply_coupon_to_order: Verificando fechas - Now: $now, End: $end_date, Start: " . strtotime($coupon['start_date']));

    if ($end_date && $now > $end_date) {
        error_log("apply_coupon_to_order: Cupón EXPIRADO");
        $expired_date = date('d/m/Y', $end_date);
        return [
            'valid' => false,
            'discount_ars' => 0,
            'discount_usd' => 0,
            'message' => "Cupón expirado el {$expired_date}"
        ];
    }

    // Check start date
    $start_date = strtotime($coupon['start_date']);
    if ($start_date && $now < $start_date) {
        error_log("apply_coupon_to_order: Cupón AÚN NO VÁLIDO");
        $valid_from = date('d/m/Y', $start_date);
        return [
            'valid' => false,
            'discount_ars' => 0,
            'discount_usd' => 0,
            'message' => "Cupón válido desde el {$valid_from}"
        ];
    }

    // Check max uses
    error_log("apply_coupon_to_order: Max uses: {$coupon['max_uses']}, Uses count: {$coupon['uses_count']}");
    if ($coupon['max_uses'] > 0 && $coupon['uses_count'] >= $coupon['max_uses']) {
        error_log("apply_coupon_to_order: Cupón AGOTADO");
        return [
            'valid' => false,
            'discount_ars' => 0,
            'discount_usd' => 0,
            'message' => 'Cupón agotado'
        ];
    }

    // Check minimum purchase
    error_log("apply_coupon_to_order: Min purchase: {$coupon['min_purchase']}, Subtotal ARS: $subtotal_ars");
    if ($coupon['min_purchase'] > 0 && $subtotal_ars < $coupon['min_purchase']) {
        error_log("apply_coupon_to_order: NO ALCANZA MÍNIMO DE COMPRA");
        $min_purchase_formatted = '$' . number_format($coupon['min_purchase'], 2);
        $current_total_formatted = '$' . number_format($subtotal_ars, 2);
        return [
            'valid' => false,
            'discount_ars' => 0,
            'discount_usd' => 0,
            'message' => "Mínimo de compra: {$min_purchase_formatted} (tu total: {$current_total_formatted})"
        ];
    }

    // Calculate discount
    $discount_ars = 0;
    $discount_usd = 0;

    if ($coupon['type'] === 'percentage') {
        $discount_ars = $subtotal_ars * ($coupon['value'] / 100);
        $discount_usd = $subtotal_usd * ($coupon['value'] / 100);
        error_log("apply_coupon_to_order: Cupón VÁLIDO - Tipo: percentage, Valor: {$coupon['value']}%, Descuento ARS: $discount_ars");
    } else {
        // Fixed discount
        $discount_ars = min($coupon['value'], $subtotal_ars);
        $discount_usd = $discount_ars / ($subtotal_ars / $subtotal_usd); // Proportional USD discount
        error_log("apply_coupon_to_order: Cupón VÁLIDO - Tipo: fixed, Valor: {$coupon['value']}, Descuento ARS: $discount_ars");
    }

    return [
        'valid' => true,
        'discount_ars' => $discount_ars,
        'discount_usd' => $discount_usd,
        'coupon' => $coupon,
        'message' => 'Cupón aplicado correctamente'
    ];
}
