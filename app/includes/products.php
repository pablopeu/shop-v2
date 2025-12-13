<?php
/**
 * Products Management System
 * CRUD operations for products with inventory control
 */

require_once __DIR__ . '/functions.php';

/**
 * Get all products
 * @param bool $active_only Only return active products
 * @return array Products array
 */
function get_all_products($active_only = false) {
    $products_file = __DIR__ . '/../data/products.json';
    $data = read_json($products_file);

    if (!isset($data['products']) || !is_array($data['products'])) {
        return [];
    }

    $products = $data['products'];

    if ($active_only) {
        $products = array_filter($products, function($product) {
            return $product['active'] === true;
        });
    }

    // Sort by display_order field (set by drag-and-drop in admin)
    usort($products, function($a, $b) {
        return ($a['display_order'] ?? 9999) - ($b['display_order'] ?? 9999);
    });

    // DO NOT apply url() here - PHP views already do it
    // Only get_product_by_id() applies url() for API responses

    return array_values($products);
}

/**
 * Get product by ID
 * @param string $product_id Product ID
 * @return array|null Product data or null
 */
function get_product_by_id($product_id) {
    $product_file = __DIR__ . "/../data/products/{$product_id}.json";

    if (!file_exists($product_file)) {
        return null;
    }

    $product = read_json($product_file);

    // Apply url() to all images for subdirectory support
    if (isset($product['images']) && is_array($product['images'])) {
        foreach ($product['images'] as &$image) {
            if (is_array($image) && isset($image['url'])) {
                $image['url'] = url($image['url']);
            } elseif (is_string($image)) {
                $image = url($image);
            }
        }
        unset($image); // Break reference
    }

    // Apply url() to thumbnail
    if (isset($product['thumbnail']) && !empty($product['thumbnail'])) {
        $product['thumbnail'] = url($product['thumbnail']);
    } else {
        // Ensure thumbnail exists - use first image if not set
        if (isset($product['images']) && !empty($product['images'])) {
            if (is_array($product['images'][0])) {
                // Images is array of objects
                $product['thumbnail'] = $product['images'][0]['url'] ?? '';
            } else {
                // Images is array of strings
                $product['thumbnail'] = $product['images'][0];
            }
        }
    }

    return $product;
}

/**
 * Get product by slug
 * @param string $slug Product slug
 * @return array|null Product data or null
 */
function get_product_by_slug($slug) {
    $products = get_all_products();

    foreach ($products as $product) {
        if ($product['slug'] === $slug) {
            return get_product_by_id($product['id']);
        }
    }

    return null;
}

/**
 * Create new product
 * @param array $data Product data
 * @return array ['success' => bool, 'message' => string, 'product_id' => string|null]
 */
function create_product($data) {
    // Validate required fields
    $required = ['name', 'price_ars', 'stock'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            return [
                'success' => false,
                'message' => "El campo '$field' es requerido.",
                'product_id' => null
            ];
        }
    }

    // Use provided ID or generate a new one
    $product_id = isset($data['id']) && $data['id'] !== ''
        ? $data['id']
        : generate_id('prod-');

    $slug = isset($data['slug']) && $data['slug'] !== ''
        ? generate_slug($data['slug'])
        : generate_slug($data['name']);

    // Ensure slug is unique
    $slug = ensure_unique_slug($slug);

    // Calculate USD price if not provided
    $price_usd = isset($data['price_usd']) && $data['price_usd'] > 0
        ? $data['price_usd']
        : convert_currency($data['price_ars'], 'ARS', 'USD');

    // Get current max order
    $products = get_all_products();
    $max_order = 0;
    foreach ($products as $p) {
        if (($p['order'] ?? 0) > $max_order) {
            $max_order = $p['order'];
        }
    }

    // Get images and thumbnail
    $images = $data['images'] ?? [];
    $thumbnail = !empty($images) ? $images[0] : '';

    // Prepare product data for listing
    $product_summary = [
        'id' => $product_id,
        'name' => sanitize_input($data['name']),
        'slug' => $slug,
        'price_ars' => floatval($data['price_ars']),
        'price_usd' => round($price_usd, 2),
        'stock' => intval($data['stock']),
        'stock_alert' => intval($data['stock_alert'] ?? 5),
        'thumbnail' => $thumbnail,
        'rating_avg' => 0,
        'rating_count' => 0,
        'active' => isset($data['active']) ? (bool)$data['active'] : true,
        'hide_when_out_of_stock' => isset($data['hide_when_out_of_stock']) ? (bool)$data['hide_when_out_of_stock'] : false,
        'order' => $max_order + 1,
        'created_at' => get_timestamp()
    ];

    // Prepare full product data
    $product_full = [
        'id' => $product_id,
        'name' => sanitize_input($data['name']),
        'slug' => $slug,
        'description' => sanitize_input($data['description'] ?? ''),
        'price_ars' => floatval($data['price_ars']),
        'price_usd' => round($price_usd, 2),
        'stock' => intval($data['stock']),
        'stock_alert' => intval($data['stock_alert'] ?? 5),
        'active' => isset($data['active']) ? (bool)$data['active'] : true,
        'hide_when_out_of_stock' => isset($data['hide_when_out_of_stock']) ? (bool)$data['hide_when_out_of_stock'] : false,
        'seo' => [
            'title' => sanitize_input($data['seo']['title'] ?? $data['seo_title'] ?? $data['name'] . ' - Mi Tienda'),
            'description' => sanitize_input($data['seo']['description'] ?? $data['seo_description'] ?? ''),
            'keywords' => sanitize_input($data['seo']['keywords'] ?? $data['seo_keywords'] ?? '')
        ],
        'images' => $images,
        'thumbnail' => $thumbnail,
        'created_at' => get_timestamp(),
        'updated_at' => get_timestamp()
    ];

    // Save to products list
    $products_file = __DIR__ . '/../data/products.json';
    $products_data = read_json($products_file);

    if (!isset($products_data['products'])) {
        $products_data = ['products' => []];
    }

    $products_data['products'][] = $product_summary;

    if (!write_json($products_file, $products_data)) {
        return [
            'success' => false,
            'message' => 'Error al guardar el producto en la lista.',
            'product_id' => null
        ];
    }

    // Save full product data
    $product_file = __DIR__ . "/../data/products/{$product_id}.json";

    if (!write_json($product_file, $product_full)) {
        return [
            'success' => false,
            'message' => 'Error al guardar los detalles del producto.',
            'product_id' => null
        ];
    }

    // Create image directory if it doesn't exist (might already be created)
    $image_dir = __DIR__ . "/../images/products/{$product_id}";
    if (!is_dir($image_dir)) {
        mkdir($image_dir, 0755, true);
    }

    // Log action
    $current_user = get_logged_user();
    log_admin_action('create_product', $current_user['username'] ?? 'system', [
        'product_id' => $product_id,
        'name' => $data['name']
    ]);

    return [
        'success' => true,
        'message' => 'Producto creado exitosamente.',
        'product_id' => $product_id,
        'product' => $product_full
    ];
}

/**
 * Update product
 * @param string $product_id Product ID
 * @param array $data Updated product data
 * @return array ['success' => bool, 'message' => string]
 */
function update_product($product_id, $data) {
    $product_file = __DIR__ . "/../data/products/{$product_id}.json";

    if (!file_exists($product_file)) {
        return [
            'success' => false,
            'message' => 'Producto no encontrado.'
        ];
    }

    $product = read_json($product_file);

    // Update fields
    if (isset($data['name'])) {
        $product['name'] = sanitize_input($data['name']);
    }

    if (isset($data['description'])) {
        $product['description'] = sanitize_input($data['description']);
    }

    if (isset($data['price_ars'])) {
        $product['price_ars'] = floatval($data['price_ars']);
    }

    if (isset($data['price_usd'])) {
        $product['price_usd'] = floatval($data['price_usd']);
    } elseif (isset($data['price_ars'])) {
        // Recalculate USD price
        $product['price_usd'] = round(convert_currency($product['price_ars'], 'ARS', 'USD'), 2);
    }

    if (isset($data['stock'])) {
        $old_stock = $product['stock'];
        $product['stock'] = intval($data['stock']);

        // Log stock change
        log_stock_change($product_id, $old_stock, $product['stock'], 'manual_update');
    }

    if (isset($data['stock_alert'])) {
        $product['stock_alert'] = intval($data['stock_alert']);
    }

    if (isset($data['active'])) {
        $product['active'] = (bool)$data['active'];
    }

    if (isset($data['pickup_only'])) {
        $product['pickup_only'] = (bool)$data['pickup_only'];
    }

    // Update images and thumbnail
    if (isset($data['images'])) {
        $product['images'] = $data['images'];
        // Update thumbnail to first image if images exist
        if (!empty($data['images'])) {
            $product['thumbnail'] = $data['images'][0];
        }
    }

    if (isset($data['thumbnail'])) {
        $product['thumbnail'] = $data['thumbnail'];
    }

    if (isset($data['slug'])) {
        $new_slug = generate_slug($data['slug']);
        if ($new_slug !== $product['slug']) {
            $product['slug'] = ensure_unique_slug($new_slug, $product_id);
        }
    }

    // Update SEO (support both nested and flat structure)
    if (isset($data['seo']) && is_array($data['seo'])) {
        if (isset($data['seo']['title'])) {
            $product['seo']['title'] = sanitize_input($data['seo']['title']);
        }
        if (isset($data['seo']['description'])) {
            $product['seo']['description'] = sanitize_input($data['seo']['description']);
        }
        if (isset($data['seo']['keywords'])) {
            $product['seo']['keywords'] = sanitize_input($data['seo']['keywords']);
        }
    } else {
        // Fallback to flat structure
        if (isset($data['seo_title'])) {
            $product['seo']['title'] = sanitize_input($data['seo_title']);
        }
        if (isset($data['seo_description'])) {
            $product['seo']['description'] = sanitize_input($data['seo_description']);
        }
        if (isset($data['seo_keywords'])) {
            $product['seo']['keywords'] = sanitize_input($data['seo_keywords']);
        }
    }

    $product['updated_at'] = get_timestamp();

    // Save full product data
    if (!write_json($product_file, $product)) {
        return [
            'success' => false,
            'message' => 'Error al actualizar el producto.'
        ];
    }

    // Update product in listing
    update_product_in_listing($product_id, $product);

    // Log action
    $current_user = get_logged_user();
    log_admin_action('update_product', $current_user['username'] ?? 'system', [
        'product_id' => $product_id,
        'name' => $product['name']
    ]);

    return [
        'success' => true,
        'message' => 'Producto actualizado exitosamente.'
    ];
}

/**
 * Delete product
 * @param string $product_id Product ID
 * @return array ['success' => bool, 'message' => string]
 */
function delete_product($product_id) {
    // Remove from listing
    $products_file = __DIR__ . '/../data/products.json';
    $products_data = read_json($products_file);

    if (!isset($products_data['products'])) {
        return [
            'success' => false,
            'message' => 'Error al acceder a la lista de productos.'
        ];
    }

    $product_name = '';
    $products_data['products'] = array_filter($products_data['products'], function($p) use ($product_id, &$product_name) {
        if ($p['id'] === $product_id) {
            $product_name = $p['name'];
            return false;
        }
        return true;
    });

    $products_data['products'] = array_values($products_data['products']);

    write_json($products_file, $products_data);

    // Delete product file
    $product_file = __DIR__ . "/../data/products/{$product_id}.json";
    if (file_exists($product_file)) {
        unlink($product_file);
    }

    // Delete images directory
    $image_dir = __DIR__ . "/../images/products/{$product_id}";
    if (is_dir($image_dir)) {
        $files = glob($image_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($image_dir);
    }

    // Delete associated reviews
    $reviews_file = __DIR__ . '/../data/reviews.json';
    if (file_exists($reviews_file)) {
        $reviews_data = read_json($reviews_file);
        if (isset($reviews_data['reviews'])) {
            $original_count = count($reviews_data['reviews']);
            $reviews_data['reviews'] = array_filter(
                $reviews_data['reviews'],
                fn($review) => $review['product_id'] !== $product_id
            );
            $reviews_data['reviews'] = array_values($reviews_data['reviews']);

            $deleted_reviews = $original_count - count($reviews_data['reviews']);
            if ($deleted_reviews > 0) {
                write_json($reviews_file, $reviews_data);
                error_log("Deleted {$deleted_reviews} reviews for product {$product_id}");
            }
        }
    }

    // Log action
    $current_user = get_logged_user();
    log_admin_action('delete_product', $current_user['username'] ?? 'system', [
        'product_id' => $product_id,
        'name' => $product_name
    ]);

    return [
        'success' => true,
        'message' => 'Producto eliminado exitosamente.'
    ];
}

/**
 * Update product in listing (sync summary data)
 * @param string $product_id Product ID
 * @param array $product Full product data
 */
function update_product_in_listing($product_id, $product) {
    $products_file = __DIR__ . '/../data/products.json';
    $products_data = read_json($products_file);

    if (!isset($products_data['products'])) {
        return;
    }

    foreach ($products_data['products'] as &$p) {
        if ($p['id'] === $product_id) {
            $p['name'] = $product['name'];
            $p['slug'] = $product['slug'];
            $p['price_ars'] = $product['price_ars'];
            $p['price_usd'] = $product['price_usd'];
            $p['stock'] = $product['stock'];
            $p['stock_alert'] = $product['stock_alert'];
            $p['active'] = $product['active'];

            // Update thumbnail if images exist
            if (!empty($product['images'])) {
                // Handle both array of strings and array of objects
                if (is_array($product['images'][0])) {
                    $p['thumbnail'] = $product['images'][0]['url'] ?? '';
                } else {
                    $p['thumbnail'] = $product['images'][0];
                }
            } elseif (!empty($product['thumbnail'])) {
                $p['thumbnail'] = $product['thumbnail'];
            }

            break;
        }
    }

    write_json($products_file, $products_data);
}

/**
 * Ensure slug is unique
 * @param string $slug Desired slug
 * @param string $exclude_id Product ID to exclude from check
 * @return string Unique slug
 */
function ensure_unique_slug($slug, $exclude_id = null) {
    $products = get_all_products();
    $slugs = [];

    foreach ($products as $product) {
        if ($exclude_id && $product['id'] === $exclude_id) {
            continue;
        }
        $slugs[] = $product['slug'];
    }

    $original_slug = $slug;
    $counter = 1;

    while (in_array($slug, $slugs)) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

/**
 * Update product stock
 * @param string $product_id Product ID
 * @param int $quantity Quantity to add/subtract (negative to subtract)
 * @param string $reason Reason for stock change
 * @return array ['success' => bool, 'message' => string, 'new_stock' => int]
 */
function update_stock($product_id, $quantity, $reason = 'manual') {
    $product = get_product_by_id($product_id);

    if (!$product) {
        return [
            'success' => false,
            'message' => 'Producto no encontrado.',
            'new_stock' => 0
        ];
    }

    $old_stock = $product['stock'];
    $new_stock = $old_stock + $quantity;

    if ($new_stock < 0) {
        return [
            'success' => false,
            'message' => 'Stock insuficiente.',
            'new_stock' => $old_stock
        ];
    }

    $product['stock'] = $new_stock;
    $product['updated_at'] = get_timestamp();

    $product_file = __DIR__ . "/../data/products/{$product_id}.json";
    write_json($product_file, $product);

    // Update in listing
    update_product_in_listing($product_id, $product);

    // Log stock change
    log_stock_change($product_id, $old_stock, $new_stock, $reason);

    // Check for low stock alert
    if ($new_stock <= $product['stock_alert'] && $new_stock > 0) {
        // TODO: Send low stock email
        error_log("Low stock alert for product {$product_id}: {$new_stock} units remaining");
    }

    return [
        'success' => true,
        'message' => 'Stock actualizado exitosamente.',
        'new_stock' => $new_stock
    ];
}

/**
 * Log stock change
 * @param string $product_id Product ID
 * @param int $old_stock Old stock value
 * @param int $new_stock New stock value
 * @param string $reason Reason for change
 */
function log_stock_change($product_id, $old_stock, $new_stock, $reason) {
    $log_file = __DIR__ . '/../data/stock_logs.json';
    $logs = read_json($log_file);

    if (!isset($logs['logs'])) {
        $logs = ['logs' => []];
    }

    $current_user = get_logged_user();

    $logs['logs'][] = [
        'product_id' => $product_id,
        'old_stock' => $old_stock,
        'new_stock' => $new_stock,
        'change' => $new_stock - $old_stock,
        'reason' => $reason,
        'user' => $current_user['username'] ?? 'system',
        'timestamp' => get_timestamp()
    ];

    // Keep only last 1000 logs
    if (count($logs['logs']) > 1000) {
        $logs['logs'] = array_slice($logs['logs'], -1000);
    }

    write_json($log_file, $logs);
}

/**
 * Get products with low stock
 * @param int $threshold Stock threshold (default: 5)
 * @return array Products with low stock
 */
function get_low_stock_products($threshold = null) {
    $products = get_all_products(true); // Only active products

    return array_filter($products, function($product) use ($threshold) {
        $alert_level = $threshold ?? $product['stock_alert'] ?? 5;
        return $product['stock'] > 0 && $product['stock'] <= $alert_level;
    });
}

/**
 * Get products out of stock
 * @return array Products with zero stock
 */
function get_out_of_stock_products() {
    $products = get_all_products(true);

    return array_filter($products, function($product) {
        return $product['stock'] === 0;
    });
}

/**
 * Search products
 * @param string $query Search query
 * @param array $filters Additional filters
 * @return array Matching products
 */
function search_products($query, $filters = []) {
    $products = get_all_products(!empty($filters['active_only']));

    if (!empty($query)) {
        $query = strtolower($query);
        $products = array_filter($products, function($product) use ($query) {
            $product_full = get_product_by_id($product['id']);
            return stripos($product_full['name'], $query) !== false ||
                   stripos($product_full['description'] ?? '', $query) !== false;
        });
    }

    // Apply filters
    if (isset($filters['min_price'])) {
        $products = array_filter($products, function($p) use ($filters) {
            return $p['price_ars'] >= $filters['min_price'];
        });
    }

    if (isset($filters['max_price'])) {
        $products = array_filter($products, function($p) use ($filters) {
            return $p['price_ars'] <= $filters['max_price'];
        });
    }

    if (isset($filters['in_stock']) && $filters['in_stock']) {
        $products = array_filter($products, function($p) {
            return $p['stock'] > 0;
        });
    }

    // Sort
    if (isset($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                usort($products, fn($a, $b) => $a['price_ars'] - $b['price_ars']);
                break;
            case 'price_desc':
                usort($products, fn($a, $b) => $b['price_ars'] - $a['price_ars']);
                break;
            case 'newest':
                usort($products, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
                break;
            case 'rating':
                usort($products, fn($a, $b) => $b['rating_avg'] - $a['rating_avg']);
                break;
        }
    }

    return array_values($products);
}

/**
 * Archive a product
 * @param string $product_id Product ID
 * @return bool Success status
 */
function archive_product($product_id) {
    $products_file = __DIR__ . '/../data/products.json';
    $archived_file = __DIR__ . '/../data/archived_products.json';

    // Load products
    $products_data = read_json($products_file);
    $archived_data = read_json($archived_file);

    if (!isset($archived_data['products'])) {
        $archived_data = ['products' => []];
    }

    // Find and remove product from products.json
    $product_to_archive = null;
    foreach ($products_data['products'] as $index => $product) {
        if ($product['id'] === $product_id) {
            $product_to_archive = $product;
            $product_to_archive['archived_date'] = get_timestamp();
            array_splice($products_data['products'], $index, 1);
            break;
        }
    }

    if (!$product_to_archive) {
        return false;
    }

    // Load full product data
    $product_full_file = __DIR__ . "/../data/products/{$product_id}.json";
    if (file_exists($product_full_file)) {
        $full_product = read_json($product_full_file);
        $full_product['archived_date'] = get_timestamp();

        // Save to archived with full data
        array_unshift($archived_data['products'], $product_to_archive);

        // Also keep full product file in archived location
        $archived_dir = __DIR__ . '/../data/archived_products';
        if (!is_dir($archived_dir)) {
            mkdir($archived_dir, 0755, true);
        }
        write_json("{$archived_dir}/{$product_id}.json", $full_product);

        // Delete original full product file
        unlink($product_full_file);
    }

    // Save products.json
    write_json($products_file, $products_data);

    // Save archived_products.json
    write_json($archived_file, $archived_data);

    return true;
}

/**
 * Unarchive a product (restore from archive)
 * @param string $product_id Product ID
 * @return bool Success status
 */
function unarchive_product($product_id) {
    $products_file = __DIR__ . '/../data/products.json';
    $archived_file = __DIR__ . '/../data/archived_products.json';

    // Load data
    $products_data = read_json($products_file);
    $archived_data = read_json($archived_file);

    if (!isset($products_data['products'])) {
        $products_data = ['products' => []];
    }

    // Find and remove product from archived_products.json
    $product_to_restore = null;
    foreach ($archived_data['products'] as $index => $product) {
        if ($product['id'] === $product_id) {
            $product_to_restore = $product;
            unset($product_to_restore['archived_date']); // Remove archived_date field
            array_splice($archived_data['products'], $index, 1);
            break;
        }
    }

    if (!$product_to_restore) {
        return false;
    }

    // Restore full product data
    $archived_product_file = __DIR__ . "/../data/archived_products/{$product_id}.json";
    if (file_exists($archived_product_file)) {
        $full_product = read_json($archived_product_file);
        unset($full_product['archived_date']); // Remove archived_date field

        // Restore to products directory
        $product_file = __DIR__ . "/../data/products/{$product_id}.json";
        write_json($product_file, $full_product);

        // Delete from archived directory
        unlink($archived_product_file);
    }

    // Add back to products.json
    $products_data['products'][] = $product_to_restore;

    // Save both files
    write_json($products_file, $products_data);
    write_json($archived_file, $archived_data);

    return true;
}

/**
 * Get all archived products
 * @return array Archived products array
 */
function get_archived_products() {
    $archived_file = __DIR__ . '/../data/archived_products.json';
    $data = read_json($archived_file);

    if (!isset($data['products']) || !is_array($data['products'])) {
        return [];
    }

    return $data['products'];
}

/**
 * Delete archived product permanently
 * @param string $product_id Product ID
 * @return array ['success' => bool, 'message' => string]
 */
function delete_archived_product($product_id) {
    $archived_file = __DIR__ . '/../data/archived_products.json';
    $archived_data = read_json($archived_file);

    if (!isset($archived_data['products'])) {
        return [
            'success' => false,
            'message' => 'Error al acceder a productos archivados.'
        ];
    }

    $product_name = '';
    $found = false;

    // Remove from archived products list
    foreach ($archived_data['products'] as $index => $product) {
        if ($product['id'] === $product_id) {
            $product_name = $product['name'];
            array_splice($archived_data['products'], $index, 1);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return [
            'success' => false,
            'message' => 'Producto archivado no encontrado.'
        ];
    }

    // Save archived products file
    write_json($archived_file, $archived_data);

    // Delete full product file from archived directory
    $archived_product_file = __DIR__ . "/../data/archived_products/{$product_id}.json";
    if (file_exists($archived_product_file)) {
        unlink($archived_product_file);
    }

    // Delete images directory
    $image_dir = __DIR__ . "/../images/products/{$product_id}";
    if (is_dir($image_dir)) {
        $files = glob($image_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($image_dir);
    }

    // Log action
    $current_user = get_logged_user();
    log_admin_action('delete_archived_product', $current_user['username'] ?? 'system', [
        'product_id' => $product_id,
        'name' => $product_name
    ]);

    return [
        'success' => true,
        'message' => 'Producto eliminado permanentemente.'
    ];
}

/**
 * Update products display order
 * @param array $product_order Array of product IDs in desired order
 * @return array ['success' => bool, 'message' => string]
 */
function update_products_display_order($product_order) {
    $products_file = __DIR__ . '/../data/products.json';
    $products_data = read_json($products_file);

    if (!isset($products_data['products']) || !is_array($products_data['products'])) {
        return [
            'success' => false,
            'message' => 'Error al acceder a la lista de productos'
        ];
    }

    // Create a mapping of product_id => display_order
    $order_map = [];
    foreach ($product_order as $index => $product_id) {
        $order_map[$product_id] = $index;
    }

    // Update display_order for each product
    $updated_count = 0;
    foreach ($products_data['products'] as &$product) {
        if (isset($order_map[$product['id']])) {
            $product['display_order'] = $order_map[$product['id']];
            $updated_count++;
        } elseif (!isset($product['display_order'])) {
            // Assign a high order number to products not in the order array
            $product['display_order'] = 9999;
        }
    }

    // Save the updated products file
    if (write_json($products_file, $products_data)) {
        return [
            'success' => true,
            'message' => "$updated_count productos reordenados exitosamente"
        ];
    }

    return [
        'success' => false,
        'message' => 'Error al guardar el nuevo orden'
    ];
}
