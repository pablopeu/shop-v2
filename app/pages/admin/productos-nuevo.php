<?php
/**
 * Admin - Add New Product
 */


require_once APP_PATH . '/includes/upload.php';


// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Nuevo Producto';

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Validate required fields first
        if (empty($_POST['name'])) {
            $error = 'El nombre es requerido';
        } elseif ((floatval($_POST['price_ars'] ?? 0) <= 0) && (floatval($_POST['price_usd'] ?? 0) <= 0)) {
            $error = 'Debe ingresar al menos un precio (ARS o USD) mayor a 0';
        } elseif (!isset($_FILES['product_images']) || empty($_FILES['product_images']['name'][0])) {
            $error = 'Debe subir al menos una imagen del producto';
        } else {
            // Generate product ID FIRST
            $product_id = generate_id('prod-');

            // Create product directory BEFORE uploading images
            $product_image_dir = __DIR__ . "/../images/products/{$product_id}";
            if (!is_dir($product_image_dir)) {
                mkdir($product_image_dir, 0755, true);
            }

            // Handle image uploads to the product's directory
            $upload_result = upload_multiple_images($_FILES['product_images'], "products/{$product_id}");

            if (!empty($upload_result['errors'])) {
                $error = 'Errores al subir imágenes: ' . implode(', ', $upload_result['errors']);
            } elseif (empty($upload_result['files'])) {
                $error = 'No se pudo subir ninguna imagen';
            } else {
                $images = $upload_result['files'];

                // Get form data
                $product_data = [
                    'id' => $product_id, // Pass the pre-generated ID
                    'name' => sanitize_input($_POST['name'] ?? ''),
                    'slug' => generate_slug($_POST['name'] ?? ''),
                    'description' => sanitize_input($_POST['description'] ?? ''),
                    'price_ars' => floatval($_POST['price_ars'] ?? 0),
                    'price_usd' => floatval($_POST['price_usd'] ?? 0),
                    'stock' => intval($_POST['stock'] ?? 0),
                    'stock_alert' => intval($_POST['stock_alert'] ?? 5),
                    'active' => isset($_POST['active']) ? true : false,
                    'pickup_only' => isset($_POST['pickup_only']) ? true : false,
                    'weight' => floatval($_POST['weight'] ?? 500), // Default 500g
                    'dimensions' => [
                        'length' => floatval($_POST['length'] ?? 20),
                        'width' => floatval($_POST['width'] ?? 15),
                        'height' => floatval($_POST['height'] ?? 10)
                    ],
                    'images' => $images,
                    'thumbnail' => $images[0], // First image is thumbnail
                    'seo' => [
                        'title' => sanitize_input($_POST['seo_title'] ?? ''),
                        'description' => sanitize_input($_POST['seo_description'] ?? ''),
                        'keywords' => sanitize_input($_POST['seo_keywords'] ?? '')
                    ]
                ];

                // Create new product
                $result = create_product($product_data);

                if (isset($result['success']) && $result['success']) {
                    $message = 'Producto creado exitosamente';
                    log_admin_action('product_created', $_SESSION['username'], [
                        'product_id' => $product_id,
                        'name' => $product_data['name']
                    ]);

                    // Redirect to list after success
                    header('Location: ' . url('/admin/?page=productos-listado&msg=product_created'));
                    exit;
                } else {
                    $error = $result['message'] ?? 'Error al crear el producto';

                    // Clean up images if product creation failed
                    foreach ($images as $img) {
                        delete_uploaded_image($img);
                    }
                    if (is_dir($product_image_dir)) {
                        rmdir($product_image_dir);
                    }
                }
            }
        }
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
    <title>Agregar Producto - Admin</title>

    <style nonce="<?= csp_nonce() ?>">
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-main: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --danger: #ef4444;
            --radius: 0.5rem;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            line-height: 1.5;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            max-width: 1600px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Form Layout */
        .form-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            align-items: start;
        }

        .form-main {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: sticky;
            top: 2rem;
        }

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border: 1px solid var(--border);
        }

        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .required {
            color: var(--danger);
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #fff;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-text {
            display: block;
            margin-top: 0.375rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Grid inputs */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #f9fafb;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: #f0fdf4;
        }

        .upload-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        /* Image Gallery */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .image-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border);
            background: #fff;
        }

        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-item:hover .image-actions {
            opacity: 1;
        }

        .image-actions {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .btn-delete-image {
            background: var(--danger);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-badge {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(79, 70, 229, 0.9);
            color: white;
            font-size: 0.7rem;
            padding: 4px;
            text-align: center;
            font-weight: 600;
        }

        /* Toggle Switch */
        .toggle-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            padding: 0.5rem 0;
        }

        .toggle-input {
            display: none;
        }

        .toggle-slider {
            position: relative;
            width: 44px;
            height: 24px;
            background-color: var(--border);
            border-radius: 24px;
            transition: .3s;
        }

        .toggle-slider:before {
            content: "";
            position: absolute;
            height: 20px;
            width: 20px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            border-radius: 50%;
            transition: .3s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .toggle-input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        .toggle-input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }

        .toggle-label {
            font-weight: 500;
            color: var(--text-main);
        }

        /* Buttons */
        .btn-base {
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: 1px solid transparent;
            font-size: 0.95rem;
            height: 46px; /* Fixed height for consistency */
            line-height: 1;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: var(--text-main);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--bg-main);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
            .form-layout {
                grid-template-columns: 1fr;
            }
            .form-sidebar {
                position: static;
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

        <div class="page-header">
            <h1 class="page-title">Nuevo Producto</h1>
            <a href="<?php echo url('/admin/?page=productos-listado'); ?>" class="btn-base btn-secondary">
                Cancelar
            </a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Product Form -->
        <form method="POST" action="" enctype="multipart/form-data" id="productForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-layout">
                <!-- Main Column -->
                <div class="form-main">
                    <!-- Basic Info -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📝 Información Básica</h2>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="name">Nombre del Producto <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   placeholder="Ej: Remera Deportiva Premium">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Descripción</label>
                            <textarea id="description" name="description" class="form-control"
                                      placeholder="Descripción detallada del producto..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">🖼️ Imágenes del Producto</h2>
                        </div>
                        
                        <div class="form-group">
                            <div class="upload-area" id="uploadArea">
                                <span class="upload-icon">☁️</span>
                                <p style="font-weight: 500; margin-bottom: 0.5rem;">Haz clic o arrastra imágenes aquí</p>
                                <p class="form-text">Soporta JPG, PNG, GIF, WebP. Máx 5MB.</p>
                                <input type="file" id="product_images" name="product_images[]" accept="image/*" multiple hidden>
                            </div>
                        </div>

                        <div class="image-gallery" id="image-gallery"></div>
                        <input type="hidden" id="images_order" name="images_order" value="">
                    </div>

                    <!-- Pricing -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">💰 Precios</h2>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label" for="price_ars">Precio ARS</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 12px; top: 10px; color: #6b7280;">$</span>
                                    <input type="number" id="price_ars" name="price_ars" class="form-control" step="0.01"
                                           style="padding-left: 30px;"
                                           value="<?php echo htmlspecialchars($_POST['price_ars'] ?? ''); ?>"
                                           placeholder="0.00">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="price_usd">Precio USD</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 12px; top: 10px; color: #6b7280;">u$s</span>
                                    <input type="number" id="price_usd" name="price_usd" class="form-control" step="0.01"
                                           style="padding-left: 45px;"
                                           value="<?php echo htmlspecialchars($_POST['price_usd'] ?? ''); ?>"
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <p class="form-text" style="margin-top: 10px;">Debe ingresar al menos un precio.</p>
                    </div>

                    <!-- Inventory -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📦 Inventario</h2>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label" for="stock">Stock Disponible <span class="required">*</span></label>
                                <input type="number" id="stock" name="stock" class="form-control" required
                                       value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="stock_alert">Alerta de Stock Bajo</label>
                                <input type="number" id="stock_alert" name="stock_alert" class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['stock_alert'] ?? '0'); ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Dimensions & Weight -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📐 Dimensiones y Peso</h2>
                        </div>
                        <p class="form-text" style="margin-bottom: 1rem;">Estos datos se usan para calcular gastos de envío.</p>
                        <div class="form-group">
                            <label class="form-label" for="weight">Peso (gramos)</label>
                            <input type="number" id="weight" name="weight" class="form-control" step="1" min="0"
                                   value="<?php echo htmlspecialchars($_POST['weight'] ?? '500'); ?>"
                                   placeholder="500">
                        </div>
                        <div class="grid-3">
                            <div class="form-group">
                                <label class="form-label" for="length">Largo (cm)</label>
                                <input type="number" id="length" name="length" class="form-control" step="0.1" min="0"
                                       value="<?php echo htmlspecialchars($_POST['length'] ?? '20'); ?>"
                                       placeholder="20">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="width">Ancho (cm)</label>
                                <input type="number" id="width" name="width" class="form-control" step="0.1" min="0"
                                       value="<?php echo htmlspecialchars($_POST['width'] ?? '15'); ?>"
                                       placeholder="15">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="height">Alto (cm)</label>
                                <input type="number" id="height" name="height" class="form-control" step="0.1" min="0"
                                       value="<?php echo htmlspecialchars($_POST['height'] ?? '10'); ?>"
                                       placeholder="10">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Column -->
                <div class="form-sidebar">
                    <!-- Publish Action -->
                    <div class="card" style="border-color: var(--primary);">
                        <div class="card-header">
                            <h2 class="card-title">🚀 Publicación</h2>
                        </div>
                        <button type="submit" name="save_product" class="btn-base btn-primary" id="saveBtn">
                            💾 Crear Producto
                        </button>
                    </div>

                    <!-- Status -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">⚙️ Estado</h2>
                        </div>

                        <label class="toggle-switch">
                            <span class="toggle-label">Producto Activo</span>
                            <input type="checkbox" class="toggle-input" id="active" name="active"
                                   <?php echo (isset($_POST['save_product']) ? (isset($_POST['active']) ? 'checked' : '') : 'checked'); ?>>
                            <span class="toggle-slider"></span>
                        </label>

                        <hr style="border: 0; border-top: 1px solid var(--border); margin: 1rem 0;">

                        <label class="toggle-switch">
                            <span class="toggle-label">Solo Retiro</span>
                            <input type="checkbox" class="toggle-input" id="pickup_only" name="pickup_only"
                                   <?php echo (isset($_POST['pickup_only']) ? 'checked' : ''); ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <p class="form-text">Si activas esto, no se ofrecerá envío.</p>
                    </div>

                    <!-- SEO -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">🔍 SEO (Opcional)</h2>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="seo_title">Título SEO</label>
                            <input type="text" id="seo_title" name="seo_title" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['seo_title'] ?? ''); ?>"
                                   maxlength="60"
                                   placeholder="Título para buscadores">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="seo_description">Descripción SEO</label>
                            <textarea id="seo_description" name="seo_description" class="form-control"
                                      style="min-height: 80px;"
                                      maxlength="160"
                                      placeholder="Descripción para buscadores"><?php echo htmlspecialchars($_POST['seo_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="seo_keywords">Keywords</label>
                            <input type="text" id="seo_keywords" name="seo_keywords" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['seo_keywords'] ?? ''); ?>"
                                   placeholder="Ej: remera, deportiva, moda">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- SortableJS Local -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/Sortable.min.js'); ?>"></script>
    <script nonce="<?= csp_nonce() ?>">
        const form = document.getElementById('productForm');
        const saveBtn = document.getElementById('saveBtn');
        const fileInput = document.getElementById('product_images');
        const uploadArea = document.getElementById('uploadArea');
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');
        const priceArs = document.getElementById('price_ars');
        const priceUsd = document.getElementById('price_usd');
        const gallery = document.getElementById('image-gallery');
        const imagesOrderInput = document.getElementById('images_order');

        let hasChanges = false;
        let selectedFiles = [];
        let sortableInstance = null;

        // Upload Area Interactions
        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--primary)';
            uploadArea.style.backgroundColor = '#f0fdf4';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = 'var(--border)';
            uploadArea.style.backgroundColor = '#f9fafb';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'var(--border)';
            uploadArea.style.backgroundColor = '#f9fafb';
            
            if (e.dataTransfer.files.length > 0) {
                handleFiles(e.dataTransfer.files);
            }
        });

        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFiles(this.files);
            }
        });

        function handleFiles(files) {
            const newFiles = Array.from(files);
            selectedFiles = [...selectedFiles, ...newFiles];
            
            // Sort alphabetically if needed, or just append
            // selectedFiles = sortFilesByName(selectedFiles); 
            
            renderImageGallery();
            updateDataTransfer();
            hasChanges = true;
        }

        // Render image preview gallery
        async function renderImageGallery() {
            gallery.innerHTML = '';

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
                const div = document.createElement('div');
                div.className = 'image-item';
                div.dataset.index = index;
                div.innerHTML = `
                    <img src="${dataUrl}" alt="${name}">
                    <div class="image-actions">
                        <button type="button" class="btn-delete-image" data-action="removeImage" data-index="${index}">✕</button>
                    </div>
                    ${index === 0 ? '<span class="image-badge">PRINCIPAL</span>' : ''}
                `;
                gallery.appendChild(div);
            });

            initializeSortable();
        }

        // Initialize SortableJS
        function initializeSortable() {
            if (sortableInstance) {
                sortableInstance.destroy();
            }

            if (gallery && selectedFiles.length > 0) {
                sortableInstance = Sortable.create(gallery, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: function() {
                        reorderFiles();
                        renderImageGallery(); // Re-render to update badges
                        hasChanges = true;
                    }
                });
            }
        }

        // Reorder files array based on DOM order
        function reorderFiles() {
            const items = Array.from(gallery.children);
            const newOrder = items.map(item => parseInt(item.dataset.index));
            const reorderedFiles = newOrder.map(i => selectedFiles[i]);
            selectedFiles = reorderedFiles;
            
            // Update hidden input
            imagesOrderInput.value = JSON.stringify(newOrder);
            updateDataTransfer();
        }

        // Remove image
        window.removeImage = function(event, index) {
            event.stopPropagation(); // Prevent bubbling if any
            selectedFiles.splice(index, 1);
            renderImageGallery();
            updateDataTransfer();
            hasChanges = true;
        };

        // Update FileList in input
        function updateDataTransfer() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        // Detect changes
        inputs.forEach(input => {
            input.addEventListener('input', () => hasChanges = true);
            input.addEventListener('change', () => hasChanges = true);
        });

        // Form submission
        form.addEventListener('submit', (e) => {
            const arsValue = parseFloat(priceArs.value) || 0;
            const usdValue = parseFloat(priceUsd.value) || 0;

            if (arsValue <= 0 && usdValue <= 0) {
                e.preventDefault();
                showModal({
                    title: 'Precio Requerido',
                    message: 'Debe ingresar al menos un precio (ARS o USD) mayor a 0.',
                    icon: '⚠️',
                    iconClass: 'warning',
                    confirmText: 'Entendido'
                });
                return false;
            }

            updateDataTransfer();
            hasChanges = false;
        });

        // Wrapper for event delegation
        const _removeImage = removeImage;
        window.removeImage = function(eventOrEvent, element, params) {
            const index = params?.index || (typeof arguments[1] === 'number' ? arguments[1] : null);
            if (index !== null) return _removeImage(eventOrEvent, index);
        };
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Unsaved Changes Warning -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
