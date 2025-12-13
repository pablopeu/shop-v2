<?php
/**
 * Admin - Carousel Configuration
 */


require_once APP_PATH . '/includes/upload.php';

require_admin();

$message = '';
$error = '';

// Handle slide deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_slide' && isset($_GET['index'])) {
    $index = intval($_GET['index']);
    $config = read_json(APP_PATH . '/config/carousel.json');

    if (isset($config['slides'][$index])) {
        $image_path = $config['slides'][$index]['image'];

        // Delete physical file
        if (strpos($image_path, '/images/') === 0) {
            delete_uploaded_image($image_path);
        }

        // Remove from array
        array_splice($config['slides'], $index, 1);

        // Save
        if (write_json(APP_PATH . '/config/carousel.json', $config)) {
            header('Location: ' . url('/admin/?page=config-carrusel&msg=slide_deleted'));
            exit;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config = read_json(APP_PATH . '/config/carousel.json');

        // Start with existing slides from form data
        $slides = [];

        // Rebuild slides array from form data (existing slides)
        if (isset($_POST['slide_images']) && is_array($_POST['slide_images'])) {
            foreach ($_POST['slide_images'] as $index => $image) {
                $link = sanitize_input($_POST['slide_links'][$index] ?? '');
                $link_type = 'none';

                // Check if custom link is being used
                if ($link === '__CUSTOM__') {
                    $custom_link = trim(sanitize_input($_POST['slide_custom_links'][$index] ?? ''));

                    // Normalize URL - add https:// if no protocol
                    if (!empty($custom_link)) {
                        if (!preg_match('/^https?:\/\//i', $custom_link)) {
                            $custom_link = 'https://' . $custom_link;
                        }
                        $link = $custom_link;
                        $link_type = 'custom';
                    } else {
                        $link = '';
                        $link_type = 'none';
                    }
                } elseif (!empty($link)) {
                    // Has a link from product select
                    $link_type = 'product';
                } else {
                    $link_type = 'none';
                }

                $slides[] = [
                    'image' => sanitize_input($image),
                    'title' => sanitize_input($_POST['slide_titles'][$index] ?? ''),
                    'subtitle' => sanitize_input($_POST['slide_subtitles'][$index] ?? ''),
                    'link' => $link,
                    'link_type' => $link_type,
                    'product_id' => sanitize_input($_POST['slide_product_ids'][$index] ?? '')
                ];
            }
        }

        // Handle new slide image uploads (custom images added via button)
        if (isset($_FILES['carousel_images']) && !empty($_FILES['carousel_images']['name'][0])) {
            $upload_result = upload_multiple_images($_FILES['carousel_images'], 'carousel');

            if (!empty($upload_result['errors'])) {
                $error = 'Errores al subir imágenes: ' . implode(', ', $upload_result['errors']);
            }

            if (!empty($upload_result['files'])) {
                // Check if we have new slides with metadata
                $new_slide_indices = [];
                if (isset($_POST['new_slide_file_indices']) && is_array($_POST['new_slide_file_indices'])) {
                    foreach ($_POST['new_slide_file_indices'] as $idx => $file_index) {
                        $new_slide_indices[intval($file_index)] = $idx;
                    }
                }

                // Process uploaded files
                foreach ($upload_result['files'] as $file_index => $file_path) {
                    // Check if this file has associated slide metadata
                    if (isset($new_slide_indices[$file_index])) {
                        $meta_index = $new_slide_indices[$file_index];
                        $link = sanitize_input($_POST['new_slide_links'][$meta_index] ?? '');
                        $link_type = 'none';

                        // Check if custom link is being used
                        if ($link === '__CUSTOM__') {
                            $custom_link = trim(sanitize_input($_POST['new_slide_custom_links'][$meta_index] ?? ''));

                            // Normalize URL - add https:// if no protocol
                            if (!empty($custom_link)) {
                                if (!preg_match('/^https?:\/\//i', $custom_link)) {
                                    $custom_link = 'https://' . $custom_link;
                                }
                                $link = $custom_link;
                                $link_type = 'custom';
                            } else {
                                $link = '';
                            }
                        } elseif (!empty($link)) {
                            $link_type = 'product';
                        }

                        $slides[] = [
                            'image' => $file_path,
                            'title' => sanitize_input($_POST['new_slide_titles'][$meta_index] ?? ''),
                            'subtitle' => sanitize_input($_POST['new_slide_subtitles'][$meta_index] ?? ''),
                            'link' => $link,
                            'link_type' => $link_type,
                            'product_id' => ''
                        ];
                    } else {
                        // Regular upload without metadata
                        $slides[] = [
                            'image' => $file_path,
                            'title' => '',
                            'subtitle' => '',
                            'link' => '',
                            'link_type' => 'none'
                        ];
                    }
                }
            }
        }

        // Update config
        $config['enabled'] = isset($_POST['enabled']);
        $config['alignment'] = sanitize_input($_POST['alignment'] ?? 'center');
        $config['auto_advance_time'] = intval($_POST['auto_advance_time'] ?? 5000);
        $config['background_color'] = sanitize_input($_POST['background_color'] ?? '#f5f5f5');
        $config['slides'] = $slides;

        if (empty($error)) {
            if (write_json(APP_PATH . '/config/carousel.json', $config)) {
                $message = 'Configuración del carrusel guardada exitosamente';
                log_admin_action('carousel_config_updated', $_SESSION['username'], $config);
            } else {
                $error = 'Error al guardar la configuración';
            }
        }
    }
}

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'slide_deleted') {
        $message = 'Slide eliminado exitosamente';
    }
}

$carousel_config = read_json(APP_PATH . '/config/carousel.json');
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Configuración del Carrusel';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Load all visible products for the link selector
$all_products = get_all_products();

// Enrich each product with images from individual files
// (get_all_products only loads basic info from products.json, images are in individual files)
foreach ($all_products as &$product) {
    $full_product = get_product_by_id($product['id']);
    if ($full_product && isset($full_product['images'])) {
        $product['images'] = $full_product['images'];
        $product['thumbnail'] = $full_product['thumbnail'] ?? '';
    }
}
unset($product); // Break reference

$visible_products = array_filter($all_products, function($product) {
    $hide_when_no_stock = $product['hide_when_out_of_stock'] ?? false;
    if ($hide_when_no_stock && $product['stock'] <= 0) {
        return false;
    }
    return true;
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrusel - Admin</title>
    <!-- SortableJS CSS no es necesario - el plugin funciona solo con JS -->
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 900px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { min-height: 60px; resize: vertical; font-family: inherit; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: auto; }

        /* Slides Gallery */
        .slides-gallery { display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; }
        .slide-item { position: relative; background: #f8f9fa; border-radius: 8px; padding: 15px; border: 2px solid transparent; transition: all 0.3s; cursor: move; }
        .slide-item:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2); }
        .slide-item.sortable-ghost { opacity: 0.4; }
        .slide-content { display: grid; grid-template-columns: 200px 1fr; gap: 15px; align-items: start; }
        .slide-image { position: relative; }
        .slide-image img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; }
        .drag-handle { position: absolute; top: 5px; left: 5px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: grab; }
        .btn-delete-slide { position: absolute; top: 5px; right: 5px; background: #dc3545; color: white; border: none; width: 28px; height: 28px; border-radius: 50%; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .btn-delete-slide:hover { background: #c82333; transform: scale(1.1); }
        .slide-fields { display: flex; flex-direction: column; gap: 10px; }
        .slide-fields input, .slide-fields textarea, .slide-fields select { padding: 8px 10px; font-size: 13px; border: 2px solid #e0e0e0; border-radius: 6px; transition: border-color 0.3s; }
        .slide-fields input:focus, .slide-fields textarea:focus, .slide-fields select:focus { outline: none; border-color: #667eea; }
        .slide-fields .custom-link-input { border-color: #28a745; background: #f0fff4; }
        .slide-fields .custom-link-input:focus { border-color: #218838; }

        /* Upload Area */
        .upload-area {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem 2rem; /* Reduced 50% from 3rem */
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9fafb;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .upload-area.drag-over {
            border-color: #667eea;
            background: #e8f0ff;
            transform: scale(1.02);
        }
        .upload-icon {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }
        .upload-area p {
            margin: 0;
            color: #555;
        }
        .upload-area .form-text {
            font-size: 0.875rem;
            color: #888;
            margin-top: 0.5rem;
        }

        /* Products Section */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .product-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        .product-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }
        .product-card.selected {
            border-color: #28a745;
            background: #f0fff4;
        }
        .product-card-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .product-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .product-card-price {
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
        }
        .btn-add-products {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
        }
        .btn-add-products:hover {
            background: #218838;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        .btn-add-products:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        .products-search {
            margin-bottom: 15px;
        }
        .products-search input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        .products-search input:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Image Gallery Preview */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .image-item-new {
            position: relative;
            aspect-ratio: 1;
            border-radius: 6px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            background: #fff;
        }
        .image-item-new img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-item-new .image-actions {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
        }
        .image-item-new .btn-delete-image {
            background: #dc3545;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 0 6px 0 6px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .image-item-new .btn-delete-image:hover {
            background: #c82333;
        }
        .image-item-new .btn-add-to-slides {
            position: absolute;
            bottom: 8px;
            left: 8px;
            right: 8px;
            background: rgba(40, 167, 69, 0.95);
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .image-item-new .btn-add-to-slides:hover {
            background: rgba(33, 136, 56, 0.98);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.3);
        }

        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        /* Clase para el grid compacto */
        .config-compact-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: start; }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .slide-content { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .config-compact-grid { grid-template-columns: 1fr; gap: 15px; }
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" enctype="multipart/form-data" id="configForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="slides_order" id="slides_order">

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled"
                               <?php echo ($carousel_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <label for="enabled">Mostrar Carrusel en la página principal</label>
                    </div>
                </div>

                <!-- Configuración Básica - Compacta en una fila -->
                <div class="form-group">
                    <div class="config-compact-grid">
                        <!-- Alineación -->
                        <div>
                            <label for="alignment" style="display: block; margin-bottom: 8px;">Alineación</label>
                            <select id="alignment" name="alignment" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                                <option value="center" <?php echo ($carousel_config['alignment'] ?? 'center') === 'center' ? 'selected' : ''; ?>>Centrado</option>
                                <option value="left" <?php echo ($carousel_config['alignment'] ?? 'center') === 'left' ? 'selected' : ''; ?>>Izquierda</option>
                                <option value="right" <?php echo ($carousel_config['alignment'] ?? 'center') === 'right' ? 'selected' : ''; ?>>Derecha</option>
                            </select>
                        </div>

                        <!-- Tiempo de Auto-Avance -->
                        <div>
                            <label for="auto_advance_time" style="display: block; margin-bottom: 8px;">Auto-Avance (ms)</label>
                            <input type="number" id="auto_advance_time" name="auto_advance_time" min="1000" max="30000" step="500"
                                   value="<?php echo intval($carousel_config['auto_advance_time'] ?? 5000); ?>"
                                   style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                            <small style="color: #666; margin-top: 3px; display: block; font-size: 12px;">1000ms = 1s</small>
                        </div>

                        <!-- Color de Fondo -->
                        <div>
                            <label for="background_color" style="display: block; margin-bottom: 8px;">Color de Fondo</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="color" id="background_color" name="background_color"
                                       value="<?php echo htmlspecialchars($carousel_config['background_color'] ?? '#f5f5f5'); ?>"
                                       style="width: 50px; height: 42px; border: 2px solid #e0e0e0; border-radius: 6px; cursor: pointer;">
                                <input type="text" id="background_color_text" readonly
                                       value="<?php echo htmlspecialchars($carousel_config['background_color'] ?? '#f5f5f5'); ?>"
                                       style="flex: 1; padding: 10px 8px; border: 2px solid #e0e0e0; border-radius: 6px; font-family: monospace; background: #f9f9f9; font-size: 13px;">
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($carousel_config['slides'])): ?>
                    <div class="form-group">
                        <label>Slides Actuales (arrastra para reordenar)</label>
                        <div class="slides-gallery" id="slides-gallery">
                            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                                <div class="slide-item" data-index="<?php echo $index; ?>">
                                    <div class="slide-content">
                                        <div class="slide-image">
                                            <span class="drag-handle">⋮⋮</span>
                                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>" alt="Slide <?php echo $index + 1; ?>">
                                            <a href="javascript:void(0)"
                                               class="btn-delete-slide"
                                               data-action="confirmDeleteSlide"
                                               data-index="<?php echo $index; ?>">✕</a>
                                        </div>
                                        <div class="slide-fields">
                                            <input type="text" name="slide_titles[<?php echo $index; ?>]"
                                                   placeholder="Título del slide (opcional)"
                                                   value="<?php echo htmlspecialchars($slide['title'] ?? ''); ?>">
                                            <textarea name="slide_subtitles[<?php echo $index; ?>]"
                                                      placeholder="Subtítulo (opcional)"><?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?></textarea>
                                            <?php
                                                $link_type = $slide['link_type'] ?? 'none';
                                                $is_custom = $link_type === 'custom';
                                                $current_link = $slide['link'] ?? '';
                                            ?>
                                            <select name="slide_links[<?php echo $index; ?>]" class="slide-link-select" data-onchange="toggleCustomLink">
                                                <option value="" <?php echo (!$is_custom && empty($current_link)) ? 'selected' : ''; ?>>-- Sin enlace --</option>
                                                <option value="__CUSTOM__" <?php echo $is_custom ? 'selected' : ''; ?>>🔗 URL personalizada</option>
                                                <?php foreach ($visible_products as $product): ?>
                                                    <?php
                                                        $product_link = url('/producto/' . $product['slug']);
                                                        $selected = (!$is_custom && $current_link === $product_link) ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($product_link); ?>" <?php echo $selected; ?>>
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                        (Stock: <?php echo $product['stock']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="slide_custom_links[<?php echo $index; ?>]"
                                                   placeholder="https://ejemplo.com"
                                                   class="custom-link-input"
                                                   value="<?php echo $is_custom ? htmlspecialchars($current_link) : ''; ?>"
                                                   style="display: <?php echo $is_custom ? 'block' : 'none'; ?>;">
                                            <input type="hidden" name="slide_images[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($slide['image']); ?>">
                                            <input type="hidden" name="slide_product_ids[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($slide['product_id'] ?? ''); ?>">
                                            <input type="hidden" name="slide_link_types[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($link_type); ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Product Selection Section -->
                <div class="form-group">
                    <label>Agregar Productos del Shop al Carrusel</label>
                    <small style="color: #666; margin-bottom: 10px; display: block;">
                        Selecciona productos de tu tienda para agregarlos al carrusel. El título puede ser editado localmente sin afectar el producto original.
                    </small>

                    <div class="products-search">
                        <input type="text" id="productsSearch" placeholder="🔍 Buscar productos por nombre...">
                    </div>

                    <div class="products-grid" id="productsGrid">
                        <?php foreach ($visible_products as $product): ?>
                            <div class="product-card" data-product-id="<?php echo htmlspecialchars($product['id']); ?>"
                                 data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                 data-product-image="<?php echo htmlspecialchars($product['images'][0] ?? ''); ?>"
                                 data-product-image-url="<?php echo htmlspecialchars(url($product['images'][0] ?? '')); ?>"
                                 data-product-link="<?php echo htmlspecialchars(url('/producto/' . ($product['slug'] ?? ''))); ?>"
                                 data-product-slug="<?php echo htmlspecialchars($product['slug'] ?? ''); ?>">
                                <?php if (!empty($product['images'][0])): ?>
                                    <img src="<?php echo htmlspecialchars(url($product['images'][0])); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="product-card-image">
                                <?php else: ?>
                                    <div class="product-card-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;">
                                        Sin imagen
                                    </div>
                                <?php endif; ?>
                                <div class="product-card-title"><?php echo htmlspecialchars($product['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" id="addProductsBtn" class="btn-add-products" disabled>
                        ➕ Agregar Productos Seleccionados al Carrusel
                    </button>
                </div>

                <div class="form-group">
                    <label>Agregar Imágenes Personalizadas</label>
                    <small style="color: #666; margin-bottom: 10px; display: block;">
                        Selecciona imágenes y luego usa el botón "➕ Agregar como Slide" en cada una para editarlas antes de guardar.
                    </small>
                    <div class="upload-area" id="uploadArea">
                        <span class="upload-icon">☁️</span>
                        <p style="font-weight: 500; margin-bottom: 0.5rem;">Haz clic o arrastra imágenes aquí</p>
                        <p class="form-text">Formatos: JPG, PNG, GIF, WebP. Máx 5MB por imagen.</p>
                        <input type="file" id="carousel_images" name="carousel_images[]" accept="image/*" multiple hidden>
                    </div>
                    <div class="image-gallery" id="imageGallery"></div>
                </div>

                <button type="submit" name="save_config" class="btn-save" id="saveBtn">
                    💾 Guardar Configuración
                </button>
            </form>
        </div>
    </div>

    <!-- SortableJS Local -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/Sortable.min.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>">
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const gallery = document.getElementById('slides-gallery');
        const fileInput = document.getElementById('carousel_images');
        const uploadArea = document.getElementById('uploadArea');
        const imageGallery = document.getElementById('imageGallery');
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');

        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;
        let selectedFiles = [];
        let filesInSlides = new Set(); // Track which file indices are already in slides
        let hasChanges = false;

        // Store original values
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }
        });

        // Initialize SortableJS
        if (gallery) {
            Sortable.create(gallery, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                onEnd: () => markChanged()
            });
        }

        function markChanged() {
            saveBtn.classList.add('changed');
            saveBtn.classList.remove('saved');
        }

        // Detect changes
        inputs.forEach(input => {
            input.addEventListener('input', checkForChanges);
            input.addEventListener('change', checkForChanges);
        });

        if (fileInput) {
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    markChanged();
                }
            });
        }

        function checkForChanges() {
            hasChanges = false;
            inputs.forEach(input => {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

            if (hasChanges || (fileInput && fileInput.files.length > 0)) {
                markChanged();
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        }

        // Alert user before leaving page with unsaved changes
        let isNavigatingAway = false;
        let isSubmitting = false;

        // Interceptar clics en enlaces para mostrar modal personalizado
        document.addEventListener('click', function(e) {
            // Solo si hay cambios sin guardar y NO estamos haciendo submit
            if (!saveBtn.classList.contains('changed') && !hasChanges) {
                return;
            }

            if (isSubmitting) {
                return;
            }

            // Buscar si el clic fue en un enlace o dentro de uno
            let target = e.target;
            while (target && target !== document) {
                // Ignorar el botón de submit y botones del formulario
                if (target.type === 'submit' || target.closest('#configForm')) {
                    return;
                }

                if (target.tagName === 'A' && target.href && !target.href.startsWith('javascript:')) {
                    // Prevenir navegación inmediata
                    e.preventDefault();

                    // Mostrar modal personalizado
                    showModal({
                        title: 'Cambios sin guardar',
                        message: '¿Deseas salir sin guardar los cambios?',
                        details: 'Si sales ahora, perderás todos los cambios realizados en esta página.',
                        icon: '⚠️',
                        iconClass: 'warning',
                        confirmText: 'Salir sin guardar',
                        cancelText: 'Continuar editando',
                        confirmType: 'danger',
                        onConfirm: function() {
                            isNavigatingAway = true;
                            window.location.href = target.href;
                        }
                    });

                    return false;
                }
                target = target.parentElement;
            }
        }, true); // useCapture = true para interceptar antes que otros handlers

        // Mantener beforeunload para casos de cierre de pestaña/refresh
        // (Los navegadores modernos no permiten modales personalizados aquí)
        window.addEventListener('beforeunload', function(e) {
            if (!isNavigatingAway && (saveBtn.classList.contains('changed') || hasChanges)) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // Save order before submit
        form.addEventListener('submit', () => {
            // Mark that we're submitting to avoid alerts
            isSubmitting = true;
            hasChanges = false;
            isNavigatingAway = true;

            if (gallery) {
                const items = Array.from(gallery.children);
                const order = items.map(item => parseInt(item.dataset.index));
                document.getElementById('slides_order').value = JSON.stringify(order);
            }
        });

        // Show saved state
        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => saveBtn.classList.remove('saved'), 3000);
        }

        // ===== Drag & Drop and Preview Functionality =====

        // Click to upload
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag over
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });

        // Drag leave
        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
        });

        // Drop
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) {
                handleFiles(e.dataTransfer.files);
            }
        });

        // File input change
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFiles(e.target.files);
            }
        });

        // Handle files
        function handleFiles(files) {
            const newFiles = Array.from(files);
            selectedFiles = [...selectedFiles, ...newFiles];
            renderImageGallery();
            updateDataTransfer();
            hasChanges = true;
            markChanged();
        }

        // Render image gallery with preview
        async function renderImageGallery() {
            imageGallery.innerHTML = '';

            if (selectedFiles.length === 0) {
                return;
            }

            const readPromises = selectedFiles.map((file, index) => {
                return new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        resolve({
                            index: index,
                            dataUrl: e.target.result,
                            name: file.name
                        });
                    };
                    reader.readAsDataURL(file);
                });
            });

            const readFiles = await Promise.all(readPromises);

            readFiles.forEach(({index, dataUrl, name}) => {
                // Skip files that are already added as slides
                if (filesInSlides.has(index)) {
                    return;
                }

                const div = document.createElement('div');
                div.className = 'image-item-new';
                div.innerHTML = `
                    <img src="${dataUrl}" alt="${name}">
                    <div class="image-actions">
                        <button type="button" class="btn-delete-image" data-action="removeNewImage" data-index="${index}">✕</button>
                    </div>
                    <button type="button" class="btn-add-to-slides" data-action="addImageToSlides" data-index="${index}">
                        ➕ Agregar como Slide
                    </button>
                `;
                imageGallery.appendChild(div);
            });
        }

        // Remove image from preview
        window.removeNewImage = function(index) {
            selectedFiles.splice(index, 1);
            renderImageGallery();
            updateDataTransfer();

            if (selectedFiles.length === 0) {
                hasChanges = false;
                checkForChanges();
            }
        };

        // Add image to slides section for editing
        window.addImageToSlides = function(index) {
            const file = selectedFiles[index];
            if (!file) return;

            // Get or create slides gallery
            let slidesGallery = document.getElementById('slides-gallery');
            if (!slidesGallery) {
                // Create slides gallery section if it doesn't exist
                const existingSection = document.querySelector('.form-group label');
                const slidesSection = document.createElement('div');
                slidesSection.className = 'form-group';
                slidesSection.innerHTML = '<label>Slides Actuales (arrastra para reordenar)</label><div class="slides-gallery" id="slides-gallery"></div>';

                // Insert after checkbox group
                const checkboxGroup = document.querySelector('.checkbox-group').parentElement;
                checkboxGroup.parentNode.insertBefore(slidesSection, checkboxGroup.nextSibling);
                slidesGallery = document.getElementById('slides-gallery');

                // Initialize Sortable
                Sortable.create(slidesGallery, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    onEnd: () => markChanged()
                });
            }

            // Get current slide count
            const currentSlides = slidesGallery.querySelectorAll('.slide-item');
            const slideIndex = currentSlides.length;

            // Read file as data URL for preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const dataUrl = e.target.result;

                // Create slide HTML
                const slideDiv = document.createElement('div');
                slideDiv.className = 'slide-item new-slide';
                slideDiv.dataset.index = slideIndex;
                slideDiv.dataset.fileIndex = index;
                slideDiv.innerHTML = `
                    <div class="slide-content">
                        <div class="slide-image">
                            <span class="drag-handle">⋮⋮</span>
                            <img src="${dataUrl}" alt="Slide ${slideIndex + 1}">
                            <a href="javascript:void(0)" class="btn-delete-slide" data-action="removeSlideItem">✕</a>
                        </div>
                        <div class="slide-fields">
                            <input type="text" name="new_slide_titles[]" placeholder="Título del slide (opcional)" value="">
                            <textarea name="new_slide_subtitles[]" placeholder="Subtítulo (opcional)"></textarea>
                            <select name="new_slide_links[]" class="slide-link-select" data-onchange="toggleCustomLink">
                                <option value="">-- Sin enlace --</option>
                                <option value="__CUSTOM__">🔗 URL personalizada</option>
                                <?php foreach ($visible_products as $product): ?>
                                    <?php $product_link = url('/producto/' . $product['slug']); ?>
                                    <option value="<?php echo htmlspecialchars($product_link); ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                        (Stock: <?php echo $product['stock']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="new_slide_custom_links[]" placeholder="https://ejemplo.com"
                                   class="custom-link-input" style="display: none;">
                            <input type="hidden" name="new_slide_file_indices[]" value="${index}">
                        </div>
                    </div>
                `;

                slidesGallery.appendChild(slideDiv);

                // Add event listeners for the new inputs
                const inputs = slideDiv.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    input.addEventListener('input', checkForChanges);
                    input.addEventListener('change', checkForChanges);
                });

                markChanged();

                // Mark file as added to slides (don't delete it, just hide from preview)
                filesInSlides.add(index);
                renderImageGallery();
            };
            reader.readAsDataURL(file);
        };

        // Toggle custom link input visibility
        window.toggleCustomLink = function(selectElement) {
            const slideFields = selectElement.closest('.slide-fields');
            const customLinkInput = slideFields.querySelector('.custom-link-input');

            if (selectElement.value === '__CUSTOM__') {
                customLinkInput.style.display = 'block';
                customLinkInput.focus();
            } else {
                customLinkInput.style.display = 'none';
                customLinkInput.value = '';
            }
        };

        // Remove slide item from gallery
        window.removeSlideItem = function(btn) {
            const slideItem = btn.closest('.slide-item');
            const fileIndex = slideItem.dataset.fileIndex;

            // If it's a new slide with file reference, restore to preview gallery
            if (fileIndex !== undefined && slideItem.classList.contains('new-slide')) {
                const hiddenInput = slideItem.querySelector('input[name="new_slide_file_indices[]"]');
                if (hiddenInput) {
                    const index = parseInt(hiddenInput.value);
                    // Remove from filesInSlides and re-render to show it again
                    filesInSlides.delete(index);
                    renderImageGallery();
                }
            }

            slideItem.remove();
            markChanged();
        };

        // Update file input DataTransfer
        function updateDataTransfer() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        // ===== Color Picker Sync =====
        const colorPicker = document.getElementById('background_color');
        const colorText = document.getElementById('background_color_text');

        if (colorPicker && colorText) {
            colorPicker.addEventListener('input', function() {
                colorText.value = this.value;
                markChanged();
            });
        }

        // ===== Delete Slide Confirmation Modal =====
        function confirmDeleteSlide(index) {
            showModal({
                title: 'Eliminar Slide',
                message: '¿Estás seguro de que deseas eliminar este slide?',
                details: 'Esta acción no se puede deshacer.',
                icon: '🗑️',
                confirmText: 'Eliminar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = '<?php echo url('/admin/?page=config-carrusel'); ?>&action=delete_slide&index=' + index;
                }
            });
        }

        // ===== Product Selection for Carousel =====
        const productsGrid = document.getElementById('productsGrid');
        const addProductsBtn = document.getElementById('addProductsBtn');
        const productsSearch = document.getElementById('productsSearch');
        const productCards = document.querySelectorAll('.product-card');
        let selectedProducts = [];

        // Toggle product selection
        productCards.forEach(card => {
            card.addEventListener('click', function() {
                const productId = this.dataset.productId;

                if (this.classList.contains('selected')) {
                    // Deselect
                    this.classList.remove('selected');
                    selectedProducts = selectedProducts.filter(id => id !== productId);
                } else {
                    // Select
                    this.classList.add('selected');
                    selectedProducts.push(productId);
                }

                // Update button state
                addProductsBtn.disabled = selectedProducts.length === 0;
            });
        });

        // Search products
        if (productsSearch) {
            productsSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();

                productCards.forEach(card => {
                    const productName = card.dataset.productName.toLowerCase();
                    if (productName.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Add selected products to carousel (dynamically to DOM)
        if (addProductsBtn) {
            addProductsBtn.addEventListener('click', function() {
                if (selectedProducts.length === 0) return;

                // Get or create slides gallery
                let slidesGallery = document.getElementById('slides-gallery');

                if (!slidesGallery) {
                    // Create slides gallery section if it doesn't exist
                    const slidesSection = document.createElement('div');
                    slidesSection.className = 'form-group';
                    slidesSection.innerHTML = '<label>Slides Actuales (arrastra para reordenar)</label><div class="slides-gallery" id="slides-gallery"></div>';

                    // Insert before products section
                    const productsSection = document.querySelector('.form-group');
                    productsSection.parentNode.insertBefore(slidesSection, productsSection);
                    slidesGallery = document.getElementById('slides-gallery');

                    // Initialize Sortable if gallery was just created
                    Sortable.create(slidesGallery, {
                        animation: 150,
                        handle: '.drag-handle',
                        ghostClass: 'sortable-ghost',
                        onEnd: () => markChanged()
                    });
                }

                // Get current slide count for indexing
                const currentSlides = slidesGallery.querySelectorAll('.slide-item');
                let slideIndex = currentSlides.length;

                // Add each selected product as a slide
                selectedProducts.forEach(productId => {
                    const productCard = document.querySelector(`.product-card[data-product-id="${productId}"]`);
                    if (!productCard) return;

                    const productName = productCard.dataset.productName;
                    const productImage = productCard.dataset.productImage; // Original path
                    const productImageUrl = productCard.dataset.productImageUrl; // Full URL with basepath
                    const productLink = productCard.dataset.productLink; // Full URL with basepath

                    // Create slide HTML
                    const slideHtml = `
                        <div class="slide-item" data-index="${slideIndex}">
                            <div class="slide-content">
                                <div class="slide-image">
                                    <span class="drag-handle">⋮⋮</span>
                                    <img src="${productImageUrl}" alt="Slide ${slideIndex + 1}">
                                    <a href="javascript:void(0)" class="btn-delete-slide" data-action="confirmDeleteSlide" data-index="${slideIndex}">✕</a>
                                </div>
                                <div class="slide-fields">
                                    <input type="text" name="slide_titles[${slideIndex}]"
                                           placeholder="Título del slide (opcional)"
                                           value="${productName}">
                                    <textarea name="slide_subtitles[${slideIndex}]"
                                              placeholder="Subtítulo (opcional)"></textarea>
                                    <select name="slide_links[${slideIndex}]">
                                        <option value="">-- Sin enlace --</option>
                                        <?php foreach ($visible_products as $product): ?>
                                            <?php $product_link = url('/producto/' . $product['slug']); ?>
                                            <option value="<?php echo htmlspecialchars($product_link); ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                                (Stock: <?php echo $product['stock']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="slide_images[${slideIndex}]" value="${productImage}">
                                    <input type="hidden" name="slide_product_ids[${slideIndex}]" value="${productId}">
                                </div>
                            </div>
                        </div>
                    `;

                    slidesGallery.insertAdjacentHTML('beforeend', slideHtml);

                    // Set the correct link option
                    const lastSlide = slidesGallery.lastElementChild;
                    const selectElement = lastSlide.querySelector('select[name^="slide_links"]');
                    if (selectElement) {
                        // Find the option that matches the product link
                        const options = selectElement.querySelectorAll('option');
                        let found = false;
                        options.forEach(option => {
                            if (option.value === productLink) {
                                option.selected = true;
                                found = true;
                            }
                        });

                        if (!found) {
                            console.warn('Could not find matching option for product link:', productLink);
                        }
                    }

                    slideIndex++;
                });

                // Clear selection
                selectedProducts = [];
                productCards.forEach(card => card.classList.remove('selected'));
                addProductsBtn.disabled = true;

                // Mark as changed
                markChanged();

                console.log('[CAROUSEL] Added', selectedProducts.length, 'products to carousel');
            });
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: confirmDeleteSlide
         * Compatible con llamadas directas y event delegation
         */
        const _confirmDeleteSlide = confirmDeleteSlide;
        window.confirmDeleteSlide = function(eventOrIndex, element, params) {
            const index = params?.index || (typeof eventOrIndex === 'number' ? eventOrIndex : null);
            if (index !== null) return _confirmDeleteSlide(index);
        };

        /**
         * Wrapper: toggleCustomLink
         * Compatible con llamadas directas y event delegation
         */
        const _toggleCustomLink = toggleCustomLink;
        window.toggleCustomLink = function(eventOrElement, element, params) {
            const selectElement = element || (eventOrElement && eventOrElement.target) || eventOrElement;
            if (selectElement) return _toggleCustomLink(selectElement);
        };

        /**
         * Wrapper: removeNewImage
         * Compatible con llamadas directas y event delegation
         */
        const _removeNewImage = removeNewImage;
        window.removeNewImage = function(eventOrIndex, element, params) {
            const index = params?.index || (typeof eventOrIndex === 'number' ? eventOrIndex : null);
            if (index !== null) return _removeNewImage(index);
        };

        /**
         * Wrapper: addImageToSlides
         * Compatible con llamadas directas y event delegation
         */
        const _addImageToSlides = addImageToSlides;
        window.addImageToSlides = function(eventOrIndex, element, params) {
            const index = params?.index || (typeof eventOrIndex === 'number' ? eventOrIndex : null);
            if (index !== null) return _addImageToSlides(index);
        };

        /**
         * Wrapper: removeSlideItem
         * Compatible con llamadas directas y event delegation
         */
        const _removeSlideItem = removeSlideItem;
        window.removeSlideItem = function(eventOrBtn, element, params) {
            const btn = element || (eventOrBtn instanceof HTMLElement ? eventOrBtn : null);
            if (btn) return _removeSlideItem(btn);
        };
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
