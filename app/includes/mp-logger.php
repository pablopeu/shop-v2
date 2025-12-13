<?php
/**
 * MercadoPago Logger - Sistema de logs detallado para debugging
 * Guarda logs en la raíz del proyecto para fácil acceso
 */

/**
 * Log MercadoPago events to debug file in project root
 */
function log_mp_debug($event_type, $message, $data = null) {
    $log_file = __DIR__ . '/../mp_debug.log';

    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "\n\n" . str_repeat('=', 80) . "\n";
    $log_entry .= "[$timestamp] $event_type\n";
    $log_entry .= str_repeat('-', 80) . "\n";
    $log_entry .= "$message\n";

    if ($data !== null) {
        $log_entry .= "\nDatos:\n";
        $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    $log_entry .= str_repeat('=', 80) . "\n";

    // Append to file
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Also log to PHP error log for server logs
    error_log("[$event_type] $message");
}

/**
 * Log webhook received
 */
function log_webhook_received($data, $headers, $client_ip) {
    // Extract payment/data ID from multiple possible locations
    $data_id = $data['data']['id'] ?? $data['id'] ?? $data['resource'] ?? null;

    // Extract type from type or topic field
    $webhook_type = $data['type'] ?? $data['topic'] ?? 'unknown';

    log_mp_debug('WEBHOOK_RECEIVED', 'Webhook recibido de MercadoPago', [
        'client_ip' => $client_ip,
        'webhook_type' => $webhook_type,
        'action' => $data['action'] ?? 'unknown',
        'data_id' => $data_id,
        'headers' => $headers,
        'full_payload' => $data
    ]);
}

/**
 * Log payment details retrieved from MP API
 */
function log_payment_details($payment_id, $payment_data) {
    // Extract important fields
    $important_fields = [
        'id' => $payment_data['id'] ?? null,
        'status' => $payment_data['status'] ?? null,
        'status_detail' => $payment_data['status_detail'] ?? null,
        'transaction_amount' => $payment_data['transaction_amount'] ?? null,
        'transaction_amount_refunded' => $payment_data['transaction_amount_refunded'] ?? null,
        'currency_id' => $payment_data['currency_id'] ?? null,
        'payment_method_id' => $payment_data['payment_method_id'] ?? null,
        'payment_type_id' => $payment_data['payment_type_id'] ?? null,
        'installments' => $payment_data['installments'] ?? null,
        'external_reference' => $payment_data['external_reference'] ?? null,
        'date_created' => $payment_data['date_created'] ?? null,
        'date_approved' => $payment_data['date_approved'] ?? null,
        'date_last_updated' => $payment_data['date_last_updated'] ?? null,

        // IMPORTANT: Fee and net amount details
        'fee_details' => $payment_data['fee_details'] ?? null,
        'transaction_details' => $payment_data['transaction_details'] ?? null,

        // Payer info
        'payer' => [
            'email' => $payment_data['payer']['email'] ?? null,
            'identification' => $payment_data['payer']['identification'] ?? null
        ],

        // Card info
        'card' => [
            'first_six_digits' => $payment_data['card']['first_six_digits'] ?? null,
            'last_four_digits' => $payment_data['card']['last_four_digits'] ?? null
        ]
    ];

    log_mp_debug('PAYMENT_DETAILS', "Detalles del pago obtenidos - Payment ID: $payment_id", $important_fields);
}

/**
 * Log order status update
 */
function log_order_update($order_id, $old_status, $new_status, $payment_status) {
    log_mp_debug('ORDER_UPDATE', "Orden actualizada: $order_id", [
        'order_id' => $order_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'payment_status' => $payment_status
    ]);
}

/**
 * Log notification sent
 */
function log_notification_sent($type, $recipient, $success, $order_id) {
    $status = $success ? 'EXITOSA' : 'FALLIDA';
    log_mp_debug('NOTIFICATION', "Notificación $type enviada ($status)", [
        'type' => $type,
        'recipient' => $recipient,
        'success' => $success,
        'order_id' => $order_id
    ]);
}

/**
 * Log webhook validation
 */
function log_webhook_validation($validation_type, $result, $details = []) {
    $status = $result ? 'PASSED' : 'FAILED';
    log_mp_debug('WEBHOOK_VALIDATION', "Validación de webhook: $validation_type - $status", $details);
}

/**
 * Log preference creation
 */
function log_preference_created($order_id, $preference_id, $preference_data) {
    log_mp_debug('PREFERENCE_CREATED', "Preferencia creada para orden: $order_id", [
        'order_id' => $order_id,
        'preference_id' => $preference_id,
        'items_count' => count($preference_data['items'] ?? []),
        'total_items_value' => array_sum(array_map(function($item) {
            return ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0);
        }, $preference_data['items'] ?? [])),
        'back_urls' => $preference_data['back_urls'] ?? null,
        'notification_url' => $preference_data['notification_url'] ?? null
    ]);
}

/**
 * Log error
 */
function log_mp_error($context, $error_message, $details = []) {
    log_mp_debug('ERROR', "$context - Error: $error_message", $details);
}
