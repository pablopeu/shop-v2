<?php
/**
 * Admin - Edit Product
 */


require_once APP_PATH . '/includes/upload.php';


// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Editar Producto';

// Get product ID
$product_id = $_GET['id'] ?? '';

if (empty($product_id)) {
    header('Location: ' . url('/admin/?page=productos-listado'));
    exit;
}

// Load product
$product = get_product_by_id($product_id);

if (!$product) {
    header('Location: ' . url('/admin/?page=productos-listado&error=not_found'));
    exit;
}

// Handle image deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_image' && isset($_GET['index'])) {
    $index = intval($_GET['index']);
    $product = get_product_by_id($product_id);

    if ($product && isset($product['images'][$index])) {
        $image_path = $product['images'][$index];

        // Delete physical file
        $file_deleted = false;
        if (!empty($image_path)) {
            // Build absolute path to file
            $base_dir = __DIR__ . '/..';

            // Remove BASE_PATH from image path if present (fix for duplicated paths)
            $clean_path = $image_path;
            if (defined('BASE_PATH') && !empty(BASE_PATH)) {
                $clean_path = str_replace(BASE_PATH, '', $image_path);
            }

            // Try different path formats
            $paths_to_try = [
                $clean_path,
                ltrim($clean_path, '/'),
                '/' . ltrim($clean_path, '/'),
            ];

            foreach ($paths_to_try as $path) {
                $full_path = $base_dir . $path;

                if (file_exists($full_path) && is_file($full_path)) {
                    $file_deleted = unlink($full_path);
                    if ($file_deleted) {
                        break;
                    }
                }
            }

            // Also try using the delete_uploaded_image function
            if (!$file_deleted) {
                $file_deleted = delete_uploaded_image($clean_path);
            }
        }

        // Remove from array
        array_splice($product['images'], $index, 1);

        // Update thumbnail if needed (use first image)
        $product['thumbnail'] = !empty($product['images']) ? $product['images'][0] : '';

        // Save
        if (update_product($product_id, $product)) {
            $msg = $file_deleted ? 'image_deleted' : 'image_removed_only';
            header('Location: ' . url('/admin/?page=productos-editar&id=' . $product_id . '&msg=' . $msg));
            exit;
        }
    }
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Get current product images and clean BASE_PATH if present
        $current_images = $product['images'] ?? [$product['thumbnail']];

        // Remove BASE_PATH from existing images to ensure portability
        if (defined('BASE_PATH') && !empty(BASE_PATH)) {
            $current_images = array_map(function($img) {
                if (is_string($img)) {
                    return str_replace(BASE_PATH, '', $img);
                } elseif (is_array($img) && isset($img['url'])) {
                    $img['url'] = str_replace(BASE_PATH, '', $img['url']);
                    return $img;
                }
                return $img;
            }, $current_images);
        }

        // Handle image reordering
        if (isset($_POST['images_order']) && !empty($_POST['images_order'])) {
            $order = json_decode($_POST['images_order'], true);
            if (is_array($order)) {
                $reordered_images = [];
                foreach ($order as $index) {
                    if (isset($current_images[$index])) {
                        $reordered_images[] = $current_images[$index];
                    }
                }
                $current_images = $reordered_images;
            }
        }

        // Handle new image uploads
        if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
            // Ensure product directory exists
            $product_image_dir = __DIR__ . "/../images/products/{$product_id}";
            if (!is_dir($product_image_dir)) {
                mkdir($product_image_dir, 0755, true);
            }

            $upload_result = upload_multiple_images($_FILES['product_images'], "products/{$product_id}");

            if (!empty($upload_result['errors'])) {
                $error = 'Errores al subir imágenes: ' . implode(', ', $upload_result['errors']);
            }

            if (!empty($upload_result['files'])) {
                $current_images = array_merge($current_images, $upload_result['files']);
            }
        }

        // Get form data
        $product_data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'slug' => generate_slug($_POST['name'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'price_ars' => floatval($_POST['price_ars'] ?? 0),
            'price_usd' => floatval($_POST['price_usd'] ?? 0),
            'stock' => intval($_POST['stock'] ?? 0),
            'stock_alert' => intval($_POST['stock_alert'] ?? 5),
            'active' => isset($_POST['active']) ? true : false,
            'pickup_only' => isset($_POST['pickup_only']) ? true : false,
            'weight' => floatval($_POST['weight'] ?? 500),
            'dimensions' => [
                'length' => floatval($_POST['length'] ?? 20),
                'width' => floatval($_POST['width'] ?? 15),
                'height' => floatval($_POST['height'] ?? 10)
            ],
            'images' => $current_images,
            'thumbnail' => !empty($current_images) ? $current_images[0] : '',
            'seo' => [
                'title' => sanitize_input($_POST['seo_title'] ?? ''),
                'description' => sanitize_input($_POST['seo_description'] ?? ''),
                'keywords' => sanitize_input($_POST['seo_keywords'] ?? '')
            ]
        ];

        // Validate required fields
        if (empty($product_data['name'])) {
            $error = 'El nombre es requerido';
        } elseif ($product_data['price_ars'] <= 0 && $product_data['price_usd'] <= 0) {
            $error = 'Debe ingresar al menos un precio (ARS o USD) mayor a 0';
        } elseif (empty($product_data['images'])) {
            $error = 'Debe haber al menos una imagen';
        } else {
            // Update product
            $update_result = update_product($product_id, $product_data);
            if ($update_result['success']) {
                log_admin_action('product_updated', $_SESSION['username'], [
                    'product_id' => $product_id,
                    'name' => $product_data['name']
                ]);

                // Redirect to product listing
                header('Location: ' . url('/admin/?page=productos-listado&msg=product_updated'));
                exit;
            } else {
                $error = $update_result['message'] ?? 'Error al actualizar el producto';
            }
        }
    }
}

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'image_deleted') {
        $message = 'Imagen eliminada exitosamente del servidor y del producto';
    } elseif ($_GET['msg'] === 'image_removed_only') {
        $message = 'Imagen removida del producto (advertencia: el archivo físico no pudo ser eliminado)';
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Admin</title>
    <!-- SortableJS CSS no es necesario -->

    <style nonce="<?= csp_nonce() ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            max-width: 1200px;
        }

        /* Messages */
        .message {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .message.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
            font-size: 13px;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .form-group small {
            font-size: 11px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .section-divider {
            margin: 15px 0 10px 0;
            padding: 8px 0;
            border-top: 2px solid #f0f0f0;
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
        }

        .product-preview {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-preview img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }

        .product-preview-info h3 {
            margin-bottom: 3px;
            color: #2c3e50;
            font-size: 16px;
        }

        .product-preview-info p {
            color: #666;
            font-size: 12px;
        }

        /* Image Gallery */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .image-item {
            position: relative;
            background: #f8f9fa;
            border-radius: 6px;
            overflow: hidden;
            cursor: move;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .image-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .image-item.sortable-ghost {
            opacity: 0.4;
        }

        .image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .image-item .drag-handle {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: grab;
        }

        .image-item .btn-delete-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .image-item .btn-delete-image:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .image-item .image-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }

        .file-input-wrapper input[type="file"]:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .btn-save {
            padding: 12px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save.changed {
            background: #dc3545;
            animation: pulse 1.5s infinite;
        }

        .btn-save.saved {
            background: #28a745;
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
            <?php include APP_PATH . '/includes/admin/header.php'; ?>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Product Preview -->
            <div class="product-preview">
                <img src="<?php echo htmlspecialchars($product['thumbnail']); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div class="product-preview-info">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p>ID: <?php echo htmlspecialchars($product['id']); ?></p>
                    <p>Stock: <?php echo $product['stock']; ?> unidades</p>
                </div>
            </div>

            <!-- Product Form -->
            <div class="card">
                <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="images_order" id="images_order">

                    <!-- Basic Information -->
                    <div class="section-divider">📝 Información Básica</div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="name">
                                Nombre del Producto <span class="required">*</span>
                            </label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label for="description">Descripción</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="section-divider">🖼️ Imágenes del Producto</div>

                    <?php
                    // Ensure product has images array
                    $product_images = $product['images'] ?? [$product['thumbnail']];
                    if (empty($product_images) && !empty($product['thumbnail'])) {
                        $product_images = [$product['thumbnail']];
                    }
                    ?>

                    <?php if (!empty($product_images)): ?>
                        <div class="form-group">
                            <label>Imágenes Actuales (arrastra para reordenar, la primera será la principal)</label>
                            <div class="image-gallery" id="image-gallery">
                                <?php foreach ($product_images as $index => $image_url): ?>
                                    <div class="image-item" data-index="<?php echo $index; ?>">
                                        <span class="drag-handle">⋮⋮</span>
                                        <img src="<?php echo htmlspecialchars(url($image_url)); ?>" alt="Imagen del producto">
                                        <?php if ($index === 0): ?>
                                            <span class="image-badge">PRINCIPAL</span>
                                        <?php endif; ?>
                                        <a href="javascript:void(0)"
                                           class="btn-delete-image"
                                           data-action="confirmDeleteImage"
                                           data-product-id="<?php echo $product_id; ?>"
                                           data-index="<?php echo $index; ?>">✕</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="product_images">Agregar Nuevas Imágenes</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="product_images" name="product_images[]" accept="image/*" multiple>
                        </div>
                        <small style="color: #666; margin-top: 5px; display: block;">
                            Formatos: JPG, PNG, GIF, WebP. Tamaño máximo: 5MB por imagen. Puedes seleccionar múltiples imágenes.
                        </small>
                    </div>

                    <!-- New Images Preview -->
                    <div class="form-group" id="newImagesPreview" style="display: none;">
                        <label>Nuevas imágenes a agregar:</label>
                        <div class="image-gallery" id="new-image-gallery"></div>
                    </div>

                    <!-- Pricing -->
                    <div class="section-divider">💰 Precios</div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="price_ars">
                                Precio en Pesos (ARS)
                            </label>
                            <input type="number" id="price_ars" name="price_ars" step="0.01"
                                   value="<?php echo $product['price_ars']; ?>">
                            <small style="color: #666;">Al menos un precio debe estar completo</small>
                        </div>

                        <div class="form-group">
                            <label for="price_usd">
                                Precio en Dólares (USD)
                            </label>
                            <input type="number" id="price_usd" name="price_usd" step="0.01"
                                   value="<?php echo $product['price_usd']; ?>">
                            <small style="color: #666;">Puede dejarse vacío si solo usas ARS</small>
                        </div>
                    </div>

                    <!-- Stock -->
                    <div class="section-divider">📦 Inventario</div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="stock">
                                Stock Disponible <span class="required">*</span>
                            </label>
                            <input type="number" id="stock" name="stock" required
                                   value="<?php echo $product['stock']; ?>" min="0">
                        </div>

                        <div class="form-group">
                            <label for="stock_alert">Alerta de Stock Bajo</label>
                            <input type="number" id="stock_alert" name="stock_alert"
                                   value="<?php echo $product['stock_alert']; ?>" min="0">
                        </div>
                    </div>

                    <!-- Dimensions & Weight -->
                    <div class="section-divider">📐 Dimensiones y Peso</div>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">
                        Estos datos se usan para calcular gastos de envío.
                    </p>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="weight">Peso (gramos)</label>
                            <input type="number" id="weight" name="weight" step="1" min="0"
                                   value="<?php echo $product['weight'] ?? 500; ?>"
                                   placeholder="500">
                        </div>

                        <div class="form-group">
                            <label for="length">Largo (cm)</label>
                            <input type="number" id="length" name="length" step="0.1" min="0"
                                   value="<?php echo $product['dimensions']['length'] ?? 20; ?>"
                                   placeholder="20">
                        </div>

                        <div class="form-group">
                            <label for="width">Ancho (cm)</label>
                            <input type="number" id="width" name="width" step="0.1" min="0"
                                   value="<?php echo $product['dimensions']['width'] ?? 15; ?>"
                                   placeholder="15">
                        </div>

                        <div class="form-group">
                            <label for="height">Alto (cm)</label>
                            <input type="number" id="height" name="height" step="0.1" min="0"
                                   value="<?php echo $product['dimensions']['height'] ?? 10; ?>"
                                   placeholder="10">
                        </div>
                    </div>

                    <!-- SEO -->
                    <div class="section-divider">🔍 SEO</div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="seo_title">Título SEO</label>
                            <input type="text" id="seo_title" name="seo_title"
                                   maxlength="60"
                                   value="<?php echo htmlspecialchars($product['seo']['title'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label for="seo_description">Descripción SEO</label>
                            <textarea id="seo_description" name="seo_description"
                                      maxlength="160"><?php echo htmlspecialchars($product['seo']['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="seo_keywords">Keywords (separadas por comas)</label>
                            <input type="text" id="seo_keywords" name="seo_keywords"
                                   value="<?php echo htmlspecialchars($product['seo']['keywords'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="section-divider">⚙️ Estado</div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="active" name="active"
                                   <?php echo $product['active'] ? 'checked' : ''; ?>>
                            <label for="active">
                                Producto Activo (visible en el sitio público)
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="pickup_only" name="pickup_only"
                                   <?php echo ($product['pickup_only'] ?? false) ? 'checked' : ''; ?>>
                            <label for="pickup_only">
                                🏪 Solo Retiro en Persona (sin opción de envío)
                            </label>
                        </div>
                        <small style="color: #666; margin-left: 28px; display: block;">
                            Si activas esta opción, el producto solo podrá ser retirado en persona y no estará disponible la opción de envío en el checkout.
                        </small>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" name="save_product" class="btn-save" id="saveBtn">
                            💾 Guardar Cambios
                        </button>
                        <a href="<?php echo url('/admin/?page=productos-listado'); ?>" class="btn btn-secondary">
                            ❌ Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>

    <!-- SortableJS Local -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/Sortable.min.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>">
        console.log('=== PRODUCT EDIT SCRIPT LOADING ===');

        const form = document.getElementById('productForm');
        const saveBtn = document.getElementById('saveBtn');
        const gallery = document.getElementById('image-gallery');
        const fileInput = document.getElementById('product_images');
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');

        console.log('Form:', form);
        console.log('File input:', fileInput);
        console.log('Gallery:', gallery);

        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;
        let hasUnsavedChanges = false;

        // Store original values
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }
        });

        // Update "PRINCIPAL" badge
        function updateBadges() {
            if (!gallery) return;
            const items = gallery.querySelectorAll('.image-item');
            items.forEach((item, index) => {
                const existingBadge = item.querySelector('.image-badge');
                if (existingBadge) {
                    existingBadge.remove();
                }
                if (index === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'image-badge';
                    badge.textContent = 'PRINCIPAL';
                    item.appendChild(badge);
                }
            });
        }

        // Initialize SortableJS if gallery exists
        if (gallery) {
            Sortable.create(gallery, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                    markChanged();
                    updateBadges();
                }
            });
        }

        // Mark form as changed
        function markChanged() {
            saveBtn.classList.add('changed');
            saveBtn.classList.remove('saved');
            hasUnsavedChanges = true;
            updateUIForChanges();
        }

        // Update UI based on unsaved changes
        function updateUIForChanges() {
            // UI updates can be added here if needed
        }

        // Detect changes in inputs
        inputs.forEach(input => {
            input.addEventListener('input', checkForChanges);
            input.addEventListener('change', checkForChanges);
        });

        // Array to store accumulated files
        let pendingFiles = [];

        // Detect file selection and accumulate files
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                console.log('File input changed, files:', this.files.length);

                if (this.files.length > 0) {
                    // Add new files to pending array
                    Array.from(this.files).forEach(file => {
                        console.log('Adding file:', file.name);
                        pendingFiles.push(file);
                    });

                    console.log('Total pending files:', pendingFiles.length);
                    markChanged();
                    updatePendingFilesPreview();

                    // Clear the input so the same file can be selected again
                    this.value = '';
                }
            });
        }

        // Remove file from pending array
        function removePendingFile(index) {
            console.log('Removing file at index:', index);
            pendingFiles.splice(index, 1);
            updatePendingFilesPreview();

            if (pendingFiles.length === 0) {
                hideNewImagesPreview();
            }
        }

        // Update preview of pending files
        function updatePendingFilesPreview() {
            const newImagesPreview = document.getElementById('newImagesPreview');
            const newImageGallery = document.getElementById('new-image-gallery');

            console.log('Updating preview, pending files:', pendingFiles.length);
            console.log('Preview element:', newImagesPreview);
            console.log('Gallery element:', newImageGallery);

            if (!newImagesPreview || !newImageGallery) {
                console.error('Preview elements not found!');
                return;
            }

            if (pendingFiles.length === 0) {
                newImagesPreview.style.display = 'none';
                return;
            }

            newImageGallery.innerHTML = '';

            // Process files in order (don't sort to keep user's selection order)
            pendingFiles.forEach((file, index) => {
                const reader = new FileReader();

                reader.onerror = function(error) {
                    console.error('Error reading file:', file.name, error);
                };

                reader.onload = function(e) {
                    console.log('File loaded:', file.name);
                    const div = document.createElement('div');
                    div.className = 'image-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}" style="width: 100%; height: 120px; object-fit: cover;">
                        <span class="image-badge">NUEVA</span>
                        <button type="button" class="btn-delete-image" data-action="removePendingFile" data-index="${index}" title="Eliminar">✕</button>
                    `;
                    newImageGallery.appendChild(div);
                };

                reader.readAsDataURL(file);
            });

            newImagesPreview.style.display = 'block';
            console.log('Preview shown');
        }

        // Hide preview of new images
        function hideNewImagesPreview() {
            const newImagesPreview = document.getElementById('newImagesPreview');
            if (newImagesPreview) {
                newImagesPreview.style.display = 'none';
            }
        }

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

            if (hasChanges || pendingFiles.length > 0) {
                markChanged();
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        }

        // Before submit, save image order and validate prices
        form.addEventListener('submit', (e) => {
            // Validate prices
            const priceArs = parseFloat(document.getElementById('price_ars').value) || 0;
            const priceUsd = parseFloat(document.getElementById('price_usd').value) || 0;

            if (priceArs <= 0 && priceUsd <= 0) {
                e.preventDefault();
                showModal({
                    title: 'Precio Requerido',
                    message: 'Debe ingresar al menos un precio (ARS o USD) mayor a 0.',
                    details: 'Complete el campo de precio en pesos argentinos (ARS) o dólares (USD) antes de guardar el producto.',
                    icon: '⚠️',
                    iconClass: 'warning',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
                return false;
            }

            // Save image order
            if (gallery) {
                const items = Array.from(gallery.children);
                const order = items.map(item => parseInt(item.dataset.index));
                document.getElementById('images_order').value = JSON.stringify(order);
            }

            // Update file input with pending files before submit
            if (pendingFiles.length > 0 && fileInput) {
                try {
                    const dataTransfer = new DataTransfer();
                    pendingFiles.forEach(file => {
                        dataTransfer.items.add(file);
                    });
                    fileInput.files = dataTransfer.files;
                    console.log('Updated file input with', fileInput.files.length, 'files');
                } catch (error) {
                    console.error('Error updating file input:', error);
                }
            }

            // Remove beforeunload listener when saving
            hasUnsavedChanges = false;
        });

        // Show saved state
        if (saveSuccess) {
            saveBtn.classList.add('saved');
            hasUnsavedChanges = false;
            setTimeout(() => {
                saveBtn.classList.remove('saved');
            }, 3000);
        }

        // Initial UI state
        updateUIForChanges();

        // Confirm delete image
        function confirmDeleteImage(productId, imageIndex) {
            showModal({
                title: 'Eliminar Imagen',
                message: '¿Estás seguro de que deseas eliminar esta imagen?',
                details: 'Esta acción eliminará permanentemente el archivo del servidor y no se puede deshacer.',
                icon: '🗑️',
                confirmText: 'Eliminar',
                cancelText: 'Cancelar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?page=productos-editar&id=${productId}&action=delete_image&index=${imageIndex}`;
                }
            });
        }

        // ============================================================================
        // WRAPPERS FOR EVENT DELEGATION COMPATIBILITY
        // ============================================================================

        /**
         * Wrapper: confirmDeleteImage
         * Compatible con llamadas directas y event delegation
         */
        const _confirmDeleteImage = confirmDeleteImage;
        window.confirmDeleteImage = function(eventOrProductId, element, params) {
            const productId = params?.productId || (typeof eventOrProductId === 'string' ? eventOrProductId : null);
            const imageIndex = params?.index || (typeof arguments[1] === 'number' ? arguments[1] : null);
            if (productId && imageIndex !== null) return _confirmDeleteImage(productId, imageIndex);
        };

        /**
         * Wrapper: removePendingFile
         * Compatible con llamadas directas y event delegation
         */
        const _removePendingFile = removePendingFile;
        window.removePendingFile = function(eventOrIndex, element, params) {
            const index = params?.index || (typeof eventOrIndex === 'number' ? eventOrIndex : null);
            if (index !== null) return _removePendingFile(index);
        };
    </script>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
