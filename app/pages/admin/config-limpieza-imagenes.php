<?php
require_admin();

$message = $error = '';

// Procesar eliminación de imágenes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_images'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido';
    } else {
        $deleted_count = 0;
        $failed_deletions = [];

        // Eliminar carpetas de productos seleccionadas
        if (isset($_POST['product_folders']) && is_array($_POST['product_folders'])) {
            foreach ($_POST['product_folders'] as $folder) {
                $folder = basename($folder); // Seguridad
                $path = __DIR__ . '/../images/products/' . $folder;
                if (is_dir($path)) {
                    if (delete_directory_recursive($path)) {
                        $deleted_count++;
                        log_admin_action('orphan_product_folder_deleted', $_SESSION['username'], ['folder' => $folder]);
                    } else {
                        $failed_deletions[] = $folder;
                    }
                }
            }
        }

        // Eliminar imágenes del carrusel seleccionadas
        if (isset($_POST['carousel_images']) && is_array($_POST['carousel_images'])) {
            foreach ($_POST['carousel_images'] as $image) {
                $image = basename($image); // Seguridad
                $path = __DIR__ . '/../images/carousel/' . $image;
                if (is_file($path)) {
                    if (unlink($path)) {
                        $deleted_count++;
                        log_admin_action('orphan_carousel_image_deleted', $_SESSION['username'], ['image' => $image]);
                    } else {
                        $failed_deletions[] = $image;
                    }
                }
            }
        }

        // Eliminar logos seleccionados
        if (isset($_POST['logo_images']) && is_array($_POST['logo_images'])) {
            foreach ($_POST['logo_images'] as $image) {
                $image = basename($image); // Seguridad
                $path = PUBLIC_PATH . '/assets/uploads/logos/' . $image;
                if (is_file($path)) {
                    if (unlink($path)) {
                        $deleted_count++;
                        log_admin_action('orphan_logo_deleted', $_SESSION['username'], ['image' => $image]);
                    } else {
                        $failed_deletions[] = $image;
                    }
                }
            }
        }

        if ($deleted_count > 0) {
            $message = "Se eliminaron $deleted_count elemento(s) correctamente.";
        }
        if (!empty($failed_deletions)) {
            $error = "No se pudieron eliminar: " . implode(', ', $failed_deletions);
        }
    }
}

// Función auxiliar para eliminar directorios recursivamente
function delete_directory_recursive($dir) {
    if (!is_dir($dir)) return false;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        is_dir($path) ? delete_directory_recursive($path) : unlink($path);
    }
    return rmdir($dir);
}

// Detectar imágenes huérfanas
function detect_orphan_images() {
    $orphans = [
        'product_folders' => [],
        'carousel_images' => [],
        'logo_images' => []
    ];

    // 1. Detectar carpetas de productos huérfanas
    $product_folders = [];
    $products_dir = __DIR__ . '/../images/products/';
    if (is_dir($products_dir)) {
        $folders = array_diff(scandir($products_dir), ['.', '..', '.htaccess']);
        foreach ($folders as $folder) {
            if (is_dir($products_dir . $folder)) {
                $product_folders[] = $folder;
            }
        }
    }

    // Obtener IDs de productos activos
    $active_products = read_json(APP_PATH . '/data/products.json');
    $active_ids = [];
    if (isset($active_products['products'])) {
        foreach ($active_products['products'] as $product) {
            $active_ids[] = $product['id'];
        }
    }

    // Obtener IDs de productos archivados
    $archived_products = read_json(APP_PATH . '/data/archived_products.json');
    $archived_ids = [];
    if (isset($archived_products['products'])) {
        foreach ($archived_products['products'] as $product) {
            $archived_ids[] = $product['id'];
        }
    }

    // Obtener todos los product_ids de órdenes activas
    $orders = read_json(APP_PATH . '/data/orders.json');
    $order_product_ids = [];
    if (isset($orders['orders'])) {
        foreach ($orders['orders'] as $order) {
            if (isset($order['items'])) {
                foreach ($order['items'] as $item) {
                    if (isset($item['product_id'])) {
                        $order_product_ids[] = $item['product_id'];
                    }
                }
            }
        }
    }

    // Obtener todos los product_ids de órdenes archivadas
    $archived_orders = read_json(APP_PATH . '/data/archived_orders.json');
    if (isset($archived_orders['orders'])) {
        foreach ($archived_orders['orders'] as $order) {
            if (isset($order['items'])) {
                foreach ($order['items'] as $item) {
                    if (isset($item['product_id'])) {
                        $order_product_ids[] = $item['product_id'];
                    }
                }
            }
        }
    }

    $order_product_ids = array_unique($order_product_ids);

    // Identificar carpetas huérfanas
    foreach ($product_folders as $folder) {
        $is_active = in_array($folder, $active_ids);
        $is_archived = in_array($folder, $archived_ids);
        $has_orders = in_array($folder, $order_product_ids);

        if (!$is_active && !$is_archived) {
            // Calcular tamaño de la carpeta
            $folder_path = $products_dir . $folder;
            $size = get_directory_size($folder_path);

            $orphans['product_folders'][] = [
                'folder' => $folder,
                'path' => $folder_path,
                'size' => $size,
                'has_orders' => $has_orders,
                'warning' => $has_orders ? 'ADVERTENCIA: Este producto tiene ventas asociadas' : null
            ];
        }
    }

    // 2. Detectar imágenes del carrusel huérfanas
    $carousel_dir = __DIR__ . '/../images/carousel/';
    $carousel_config = read_json(APP_PATH . '/config/carousel.json');
    $used_carousel_images = [];

    if (isset($carousel_config['slides'])) {
        foreach ($carousel_config['slides'] as $slide) {
            if (isset($slide['image'])) {
                $used_carousel_images[] = basename($slide['image']);
            }
        }
    }

    if (is_dir($carousel_dir)) {
        $carousel_files = array_diff(scandir($carousel_dir), ['.', '..', '.htaccess']);
        foreach ($carousel_files as $file) {
            $file_path = $carousel_dir . $file;
            if (is_file($file_path) && !in_array($file, $used_carousel_images)) {
                $orphans['carousel_images'][] = [
                    'file' => $file,
                    'path' => $file_path,
                    'size' => filesize($file_path)
                ];
            }
        }
    }

    // 3. Detectar logos huérfanos (combina footer y header)
    $logos_dir = PUBLIC_PATH . '/assets/uploads/logos/';
    $footer_config = read_json(APP_PATH . '/config/footer.json');
    $site_config = read_json(APP_PATH . '/config/site.json');

    // Recopilar todos los logos en uso
    $used_logos = [];

    // Logo del footer
    if (isset($footer_config['left_column']['logo']['path'])) {
        $used_logos[] = basename($footer_config['left_column']['logo']['path']);
    }

    // Logo del header
    if (isset($site_config['logo']['path'])) {
        $used_logos[] = basename($site_config['logo']['path']);
    }

    // Eliminar duplicados
    $used_logos = array_unique($used_logos);

    // Escanear directorio de logos
    if (is_dir($logos_dir)) {
        $logo_files = array_diff(scandir($logos_dir), ['.', '..', '.htaccess']);
        foreach ($logo_files as $file) {
            $file_path = $logos_dir . $file;
            if (is_file($file_path) && !in_array($file, $used_logos)) {
                $orphans['logo_images'][] = [
                    'file' => $file,
                    'path' => $file_path,
                    'size' => filesize($file_path)
                ];
            }
        }
    }

    return $orphans;
}

// Función auxiliar para calcular tamaño de directorio
function get_directory_size($dir) {
    $size = 0;
    if (!is_dir($dir)) return $size;

    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_file($path)) {
            $size += filesize($path);
        } elseif (is_dir($path)) {
            $size += get_directory_size($path);
        }
    }
    return $size;
}

// Función para formatear tamaño
function format_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

$orphans = detect_orphan_images();
$total_orphans = count($orphans['product_folders']) + count($orphans['carousel_images']) +
                 count($orphans['logo_images']);

$csrf_token = generate_csrf_token();
$page_title = 'Limpieza de Imágenes';
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1200px; }
        .content-header h1 { font-size: 24px; margin-bottom: 10px; color: #2c3e50; }
        .content-header p { color: #7f8c8d; margin-bottom: 20px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .stats { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); flex: 1; min-width: 200px; }
        .stat-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #2c3e50; }
        .stat-card.danger .value { color: #e74c3c; }
        .card { background: white; border-radius: 8px; padding: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h2 { font-size: 18px; margin-bottom: 15px; color: #2c3e50; display: flex; align-items: center; gap: 8px; }
        .card h2 .badge { background: #e74c3c; color: white; font-size: 12px; padding: 2px 8px; border-radius: 12px; }
        .orphan-list { margin-top: 15px; }
        .orphan-item { border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; }
        .orphan-item.warning { border-color: #f39c12; background: #fef5e7; }
        .orphan-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .orphan-item .info { flex: 1; }
        .orphan-item .info .name { font-weight: 600; color: #2c3e50; margin-bottom: 4px; }
        .orphan-item .info .details { font-size: 13px; color: #7f8c8d; }
        .orphan-item .info .warning-text { color: #d35400; font-weight: 600; margin-top: 5px; display: flex; align-items: center; gap: 5px; }
        .orphan-item .size { font-weight: 500; color: #95a5a6; min-width: 80px; text-align: right; }
        .empty-state { text-align: center; padding: 40px 20px; color: #95a5a6; }
        .empty-state .icon { font-size: 48px; margin-bottom: 15px; }
        .actions { margin-top: 20px; display: flex; gap: 10px; align-items: center; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-danger:disabled { background: #bdc3c7; cursor: not-allowed; }
        .btn-secondary { background: #95a5a6; color: white; }
        .btn-secondary:hover { background: #7f8c8d; }
        .select-info { color: #7f8c8d; font-size: 14px; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>
    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <div class="content-header">
            <h1>🗑️ Limpieza de Imágenes Huérfanas</h1>
            <p>Detecta y elimina imágenes que ya no están en uso en el sistema</p>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card <?= $total_orphans > 0 ? 'danger' : '' ?>">
                <h3>Total Huérfanas</h3>
                <div class="value"><?= $total_orphans ?></div>
            </div>
            <div class="stat-card">
                <h3>Productos</h3>
                <div class="value"><?= count($orphans['product_folders']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Carrusel</h3>
                <div class="value"><?= count($orphans['carousel_images']) ?></div>
            </div>
            <div class="stat-card">
                <h3>Logos</h3>
                <div class="value"><?= count($orphans['logo_images']) ?></div>
            </div>
        </div>

        <?php if ($total_orphans === 0): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="icon">✨</div>
                    <h3>¡Todo limpio!</h3>
                    <p>No se encontraron imágenes huérfanas en el sistema.</p>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" id="cleanupForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <?php if (!empty($orphans['product_folders'])): ?>
                    <div class="card">
                        <h2>📦 Carpetas de Productos <span class="badge"><?= count($orphans['product_folders']) ?></span></h2>
                        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">
                            Carpetas de productos que no existen en productos activos ni archivados
                        </p>
                        <div class="orphan-list">
                            <?php foreach ($orphans['product_folders'] as $item): ?>
                                <div class="orphan-item <?= $item['has_orders'] ? 'warning' : '' ?>">
                                    <input type="checkbox" name="product_folders[]" value="<?= htmlspecialchars($item['folder']) ?>" id="pf_<?= htmlspecialchars($item['folder']) ?>">
                                    <div class="info">
                                        <div class="name"><?= htmlspecialchars($item['folder']) ?></div>
                                        <div class="details"><?= htmlspecialchars($item['path']) ?></div>
                                        <?php if ($item['warning']): ?>
                                            <div class="warning-text">⚠️ <?= htmlspecialchars($item['warning']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="size"><?= format_size($item['size']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($orphans['carousel_images'])): ?>
                    <div class="card">
                        <h2>🎠 Imágenes del Carrusel <span class="badge"><?= count($orphans['carousel_images']) ?></span></h2>
                        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">
                            Imágenes en /images/carousel/ que no están siendo usadas
                        </p>
                        <div class="orphan-list">
                            <?php foreach ($orphans['carousel_images'] as $item): ?>
                                <div class="orphan-item">
                                    <input type="checkbox" name="carousel_images[]" value="<?= htmlspecialchars($item['file']) ?>" id="ci_<?= htmlspecialchars($item['file']) ?>">
                                    <div class="info">
                                        <div class="name"><?= htmlspecialchars($item['file']) ?></div>
                                        <div class="details"><?= htmlspecialchars($item['path']) ?></div>
                                    </div>
                                    <div class="size"><?= format_size($item['size']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($orphans['logo_images'])): ?>
                    <div class="card">
                        <h2>🏷️ Logos <span class="badge"><?= count($orphans['logo_images']) ?></span></h2>
                        <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 14px;">
                            Logos en /assets/uploads/logos/ que no están siendo usados (se excluyen logos del header y footer)
                        </p>
                        <div class="orphan-list">
                            <?php foreach ($orphans['logo_images'] as $item): ?>
                                <div class="orphan-item">
                                    <input type="checkbox" name="logo_images[]" value="<?= htmlspecialchars($item['file']) ?>" id="li_<?= htmlspecialchars($item['file']) ?>">
                                    <div class="info">
                                        <div class="name"><?= htmlspecialchars($item['file']) ?></div>
                                        <div class="details"><?= htmlspecialchars($item['path']) ?></div>
                                    </div>
                                    <div class="size"><?= format_size($item['size']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <button type="submit" name="delete_images" class="btn btn-danger" id="deleteBtn" disabled>
                        🗑️ Eliminar Seleccionadas
                    </button>
                    <button type="button" class="btn btn-secondary" id="selectAllBtn">
                        ☑️ Seleccionar Todas
                    </button>
                    <span class="select-info" id="selectInfo">Selecciona al menos una imagen</span>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        const deleteBtn = document.getElementById('deleteBtn');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const selectInfo = document.getElementById('selectInfo');
        const form = document.getElementById('cleanupForm');

        function updateDeleteButton() {
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            deleteBtn.disabled = checked.length === 0;
            selectInfo.textContent = checked.length > 0
                ? `${checked.length} elemento(s) seleccionado(s)`
                : 'Selecciona al menos una imagen';
        }

        checkboxes.forEach(cb => cb.addEventListener('change', updateDeleteButton));

        selectAllBtn?.addEventListener('click', () => {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            selectAllBtn.textContent = allChecked ? '☑️ Seleccionar Todas' : '◻️ Deseleccionar Todas';
            updateDeleteButton();
        });

        form?.addEventListener('submit', (e) => {
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            if (checked.length === 0) {
                e.preventDefault();
                return;
            }

            e.preventDefault();

            const hasWarnings = Array.from(checked).some(cb =>
                cb.closest('.orphan-item').classList.contains('warning')
            );

            let detailsMsg = 'Esta acción no se puede deshacer.';
            if (hasWarnings) {
                detailsMsg = '⚠️ <strong>ADVERTENCIA:</strong> Algunos elementos tienen ventas asociadas. Se recomienda NO eliminarlos.<br><br>' + detailsMsg;
            }

            showModal({
                title: '¿Estás seguro?',
                message: `¿Estás seguro de eliminar ${checked.length} elemento(s)?`,
                details: detailsMsg,
                icon: '🗑️',
                iconClass: 'danger',
                confirmText: 'Eliminar',
                cancelText: 'Cancelar',
                confirmType: 'danger',
                onConfirm: function() {
                    // Add hidden input to trigger POST processing
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_images';
                    deleteInput.value = '1';
                    form.appendChild(deleteInput);
                    form.submit();
                }
            });
        });
    </script>
</body>
</html>
