<?php
/**
 * API - Preview Email Template
 * Genera vista previa de un template de email
 */

if (!defined('APP_ENTRY_POINT')) {
    die('Direct access not permitted');
}

// Require admin authentication
require_admin();

// Check if this is the correct endpoint
if (($_GET['endpoint'] ?? '') !== 'preview-email-template') {
    return;
}

header('Content-Type: application/json');

try {
    // Get template data from POST
    $template_name = $_POST['template_name'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';

    if (empty($template_name) || empty($subject) || empty($title) || empty($content)) {
        echo json_encode([
            'success' => false,
            'error' => 'Datos incompletos'
        ]);
        exit;
    }

    // Create sample order data for preview
    $sample_order = [
        'id' => 'preview-123',
        'order_number' => '2025-001',
        'date' => date('Y-m-d H:i:s'),
        'customer_name' => 'Juan Pérez',
        'customer_email' => 'cliente@ejemplo.com',
        'customer_phone' => '+54 11 1234-5678',
        'currency' => 'ARS',
        'total' => 15500,
        'items' => [
            [
                'name' => 'Producto de Ejemplo 1',
                'quantity' => 2,
                'price' => 5000,
                'final_price' => 10000
            ],
            [
                'name' => 'Producto de Ejemplo 2',
                'quantity' => 1,
                'price' => 5500,
                'final_price' => 5500
            ]
        ],
        'tracking_number' => 'TRACK123456789',
        'tracking_url' => 'https://ejemplo.com/tracking/TRACK123456789'
    ];

    // Create template array
    $template = [
        'subject' => $subject,
        'title' => $title,
        'content' => $content
    ];

    // Process variables
    $processed_template = process_template_variables($template, $sample_order);

    // Generate HTML using default template
    $html = get_default_email_template([
        'order' => $sample_order,
        'title' => $processed_template['title'],
        'content' => $processed_template['content']
    ]);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'subject' => $processed_template['subject']
    ]);

} catch (Exception $e) {
    error_log("Preview email template error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al generar la vista previa: ' . $e->getMessage()
    ]);
}

exit;
