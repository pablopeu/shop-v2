<?php
/**
 * Image Upload Functions
 * Bootstrap ya maneja: APP_ENTRY_POINT, security checks, session
 */

/**
 * Upload an image file
 * @param array $file - $_FILES array element
 * @param string $destination_dir - Directory to upload to (e.g., 'products', 'hero', 'carousel')
 * @param array $allowed_types - Allowed MIME types
 * @param int $max_size - Max file size in bytes (default 5MB)
 * @return array - ['success' => bool, 'file_path' => string, 'error' => string]
 */
function upload_image($file, $destination_dir, $allowed_types = null, $max_size = 5242880) {
    // Default allowed types
    if ($allowed_types === null) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    }

    // Check if file was uploaded
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Error en la carga del archivo'];
    }

    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No se seleccionó ningún archivo'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'El archivo es demasiado grande'];
        default:
            return ['success' => false, 'error' => 'Error desconocido al subir el archivo'];
    }

    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'El archivo excede el tamaño máximo permitido (5MB)'];
    }

    // VALIDACIÓN DE CONTENIDO REAL - Verificar que sea una imagen válida
    // getimagesize() lee los magic bytes del archivo, no confía en extensión/MIME
    $image_info = @getimagesize($file['tmp_name']);

    if ($image_info === false) {
        return ['success' => false, 'error' => 'El archivo no es una imagen válida'];
    }

    // Verificar que el tipo de imagen detectado sea permitido
    $allowed_image_types = [
        IMAGETYPE_JPEG,  // 2
        IMAGETYPE_PNG,   // 3
        IMAGETYPE_GIF,   // 1
        IMAGETYPE_WEBP   // 18
    ];

    if (!in_array($image_info[2], $allowed_image_types)) {
        return ['success' => false, 'error' => 'Tipo de imagen no permitido. Solo se permiten JPG, PNG, GIF o WebP'];
    }

    // Mapear tipo de imagen a MIME type para consistencia
    $imagetype_mime_map = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_GIF => 'image/gif',
        IMAGETYPE_WEBP => 'image/webp'
    ];
    $mime_type = $imagetype_mime_map[$image_info[2]] ?? null;

    // Validación adicional con finfo si está disponible (doble verificación)
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $finfo_mime = $finfo->file($file['tmp_name']);

        // Si finfo detecta algo diferente, rechazar
        if ($finfo_mime && !in_array($finfo_mime, $allowed_types)) {
            return ['success' => false, 'error' => 'Tipo de archivo no permitido. Solo se permiten imágenes'];
        }
    }

    // Generar nombre único con extensión basada en el tipo REAL detectado
    // NO usar la extensión proporcionada por el usuario (podría ser falsa)
    $imagetype_extension_map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp'
    ];
    $extension = $imagetype_extension_map[$image_info[2]] ?? 'jpg';
    $filename = uniqid() . '_' . time() . '.' . $extension;

    // Create destination directory if it doesn't exist
    $upload_dir = PUBLIC_PATH . '/assets/uploads/' . $destination_dir;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $destination = $upload_dir . '/' . $filename;
    $relative_path = '/assets/uploads/' . $destination_dir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Error al mover el archivo'];
    }

    // Set proper permissions
    chmod($destination, 0644);

    return [
        'success' => true,
        'file_path' => $relative_path,
        'filename' => $filename
    ];
}

/**
 * Upload multiple images
 * @param array $files - $_FILES array element (with multiple files)
 * @param string $destination_dir
 * @param bool $sort_by_name - Sort files alphabetically by original filename before uploading
 * @return array - ['success' => bool, 'files' => array, 'errors' => array]
 */
function upload_multiple_images($files, $destination_dir, $sort_by_name = true) {
    $uploaded_files = [];
    $errors = [];

    // Reorganize $_FILES array for multiple files
    $file_count = count($files['name']);

    // Create array of file data with indices
    $files_data = [];
    for ($i = 0; $i < $file_count; $i++) {
        // Skip empty files
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $files_data[] = [
            'index' => $i,
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];
    }

    // Sort by filename alphabetically if requested
    if ($sort_by_name) {
        usort($files_data, function($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
    }

    // Process files in order
    foreach ($files_data as $file_data) {
        $file = [
            'name' => $file_data['name'],
            'type' => $file_data['type'],
            'tmp_name' => $file_data['tmp_name'],
            'error' => $file_data['error'],
            'size' => $file_data['size']
        ];

        $result = upload_image($file, $destination_dir);

        if ($result['success']) {
            $uploaded_files[] = $result['file_path'];
        } else {
            $errors[] = $file_data['name'] . ': ' . $result['error'];
        }
    }

    return [
        'success' => count($uploaded_files) > 0,
        'files' => $uploaded_files,
        'errors' => $errors
    ];
}

/**
 * Delete an uploaded image
 * @param string $file_path - Relative path to the file (e.g., '/images/products/image.jpg')
 * @return bool
 */
function delete_uploaded_image($file_path) {
    $full_path = __DIR__ . '/..' . $file_path;

    if (file_exists($full_path) && is_file($full_path)) {
        return unlink($full_path);
    }

    return false;
}

/**
 * Get file size in human readable format
 * @param string $file_path
 * @return string
 */
function get_file_size_human($file_path) {
    $full_path = __DIR__ . '/..' . $file_path;

    if (!file_exists($full_path)) {
        return 'N/A';
    }

    $bytes = filesize($full_path);
    $units = ['B', 'KB', 'MB', 'GB'];

    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Validate image dimensions (optional)
 * @param string $file_path
 * @param int $max_width
 * @param int $max_height
 * @return array
 */
function validate_image_dimensions($file_path, $max_width = null, $max_height = null) {
    $full_path = __DIR__ . '/..' . $file_path;

    if (!file_exists($full_path)) {
        return ['valid' => false, 'error' => 'Archivo no encontrado'];
    }

    $image_info = getimagesize($full_path);

    if ($image_info === false) {
        return ['valid' => false, 'error' => 'No es una imagen válida'];
    }

    $width = $image_info[0];
    $height = $image_info[1];

    if ($max_width && $width > $max_width) {
        return ['valid' => false, 'error' => "El ancho excede {$max_width}px"];
    }

    if ($max_height && $height > $max_height) {
        return ['valid' => false, 'error' => "La altura excede {$max_height}px"];
    }

    return [
        'valid' => true,
        'width' => $width,
        'height' => $height
    ];
}
