<?php
/**
 * Ventas Actions - Manejo de acciones POST
 * Procesa todas las acciones relacionadas con órdenes
 *
 * Cargado por: app/pages/admin/ventas.php
 * Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
 */

// Prevent direct access - ADMIN_ACCESS debe estar definido en la página que incluye este archivo
if (!defined('ADMIN_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * Handle all order-related actions
 * @return array ['message' => string, 'error' => string]
 */
function handle_order_actions() {
    $result = ['message' => '', 'error' => ''];

    // Check for message from redirect (e.g., from archivo-ventas)
    if (isset($_GET['message'])) {
        $result['message'] = $_GET['message'];
    }

    // Check for message from session (e.g., from archive action)
    if (isset($_SESSION['ventas_message'])) {
        $result['message'] = $_SESSION['ventas_message'];
        unset($_SESSION['ventas_message']);
    }

    // Update order status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $order_id = $_POST['order_id'] ?? '';
        $new_status = $_POST['status'] ?? '';

        if (update_order_status($order_id, $new_status, $_SESSION['username'])) {
            $result['message'] = 'Estado actualizado exitosamente';

            // Send notification when order is marked as in transit
            if ($new_status === 'en_transito') {
                $updated_order = get_order_by_id($order_id);
                if ($updated_order) {
                    $contact_preference = $updated_order['contact_preference'] ?? 'email';

                    error_log("Order {$order_id} marked as en_transito. Contact preference: {$contact_preference}");

                    if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                        // Send via Telegram
                        error_log("Sending Telegram notification to chat_id: {$updated_order['telegram_chat_id']}");
                        $telegram_result = send_telegram_order_shipped_to_customer($updated_order);
                        error_log("Telegram notification result: " . ($telegram_result ? 'SUCCESS' : 'FAILED'));
                    } elseif (!empty($updated_order['customer_email'])) {
                        // Queue email notification (async)
                        error_log("Queueing email notification to: {$updated_order['customer_email']}");
                        $email_result = queue_email('order_shipped', ['order' => $updated_order], 'normal');
                        error_log("Email queued: " . ($email_result ? 'SUCCESS' : 'FAILED'));
                    } else {
                        error_log("No valid contact method found for order {$order_id}");
                    }
                }
            }

            // Send notification when order is marked as en_reparto (out for delivery)
            if ($new_status === 'en_reparto') {
                $updated_order = get_order_by_id($order_id);
                if ($updated_order) {
                    $contact_preference = $updated_order['contact_preference'] ?? 'email';

                    error_log("Order {$order_id} marked as en_reparto. Contact preference: {$contact_preference}");

                    if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                        // Send via Telegram
                        error_log("Sending Telegram notification to chat_id: {$updated_order['telegram_chat_id']}");
                        $telegram_result = send_telegram_order_in_delivery_to_customer($updated_order);
                        error_log("Telegram notification result: " . ($telegram_result ? 'SUCCESS' : 'FAILED'));
                    } elseif (!empty($updated_order['customer_email'])) {
                        // Queue email notification (async)
                        error_log("Queueing email notification to: {$updated_order['customer_email']}");
                        $email_result = queue_email('order_in_delivery', ['order' => $updated_order], 'normal');
                        error_log("Email queued: " . ($email_result ? 'SUCCESS' : 'FAILED'));
                    } else {
                        error_log("No valid contact method found for order {$order_id}");
                    }
                }
            }

            // Send notification when order is marked as pagado (paid)
            if ($new_status === 'pagado') {
                $updated_order = get_order_by_id($order_id);
                if ($updated_order) {
                    // Send notification based on customer's contact preference
                    $contact_preference = $updated_order['contact_preference'] ?? 'email';

                    error_log("Order {$order_id} marked as pagado. Contact preference: {$contact_preference}");

                    if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                        // Send via Telegram
                        error_log("Sending Telegram notification to chat_id: {$updated_order['telegram_chat_id']}");
                        $telegram_result = send_telegram_order_paid_to_customer($updated_order);
                        error_log("Telegram notification result: " . ($telegram_result ? 'SUCCESS' : 'FAILED'));
                    } elseif (!empty($updated_order['customer_email'])) {
                        // Queue email notification (async)
                        error_log("Queueing email notification to: {$updated_order['customer_email']}");
                        $email_result = queue_email('order_paid', ['order' => $updated_order], 'normal');
                        error_log("Email queued: " . ($email_result ? 'SUCCESS' : 'FAILED'));
                    } else {
                        error_log("No valid contact method found for order {$order_id}");
                    }
                } else {
                    error_log("Could not retrieve updated order {$order_id}");
                }
            }

            // Send notification when order is marked as entregada (delivered)
            if ($new_status === 'entregada') {
                $updated_order = get_order_by_id($order_id);
                if ($updated_order) {
                    $contact_preference = $updated_order['contact_preference'] ?? 'email';

                    error_log("Order {$order_id} marked as entregada. Contact preference: {$contact_preference}");

                    if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                        // Send via Telegram
                        error_log("Sending Telegram notification to chat_id: {$updated_order['telegram_chat_id']}");
                        $telegram_result = send_telegram_order_delivered_to_customer($updated_order);
                        error_log("Telegram notification result: " . ($telegram_result ? 'SUCCESS' : 'FAILED'));
                    } elseif (!empty($updated_order['customer_email'])) {
                        // Queue email notification (async)
                        error_log("Queueing email notification to: {$updated_order['customer_email']}");
                        $email_result = queue_email('order_delivered', ['order' => $updated_order], 'normal');
                        error_log("Email queued: " . ($email_result ? 'SUCCESS' : 'FAILED'));
                    } else {
                        error_log("No valid contact method found for order {$order_id}");
                    }
                } else {
                    error_log("Could not retrieve updated order {$order_id}");
                }
            }
        } else {
            $result['error'] = 'Error al actualizar el estado';
        }
    }

    // Add tracking number
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tracking'])) {
        $order_id = $_POST['order_id'] ?? '';
        $tracking_number = sanitize_input($_POST['tracking_number'] ?? '');
        $tracking_url = sanitize_input($_POST['tracking_url'] ?? '');

        if (add_order_tracking($order_id, $tracking_number, $tracking_url)) {
            $result['message'] = 'Número de seguimiento agregado';
            log_admin_action('tracking_added', $_SESSION['username'], [
                'order_id' => $order_id,
                'tracking_number' => $tracking_number
            ]);
        } else {
            $result['error'] = 'Error al agregar el número de seguimiento';
        }
    }

    // Archive order
    if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
        $order_id = $_GET['id'];
        error_log("VENTAS DEBUG: Intentando archivar orden: " . $order_id);

        if (archive_order($order_id)) {
            error_log("VENTAS DEBUG: Orden archivada exitosamente, redirigiendo...");
            // Store success message in session
            $_SESSION['ventas_message'] = 'Orden archivada exitosamente';
            // Redirect back to ventas page to prevent going to dashboard
            $redirect_url = url('/admin/?page=ventas');
            error_log("VENTAS DEBUG: URL de redirección: " . $redirect_url);
            header('Location: ' . $redirect_url, true, 303);
            exit;
        } else {
            error_log("VENTAS DEBUG: Error al archivar la orden");
            $result['error'] = 'Error al archivar la orden';
        }
    }

    // Handle bulk actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $selected_orders = $_POST['selected_orders'] ?? [];

        if (!empty($selected_orders)) {
            $success_count = 0;
            foreach ($selected_orders as $order_id) {
                if ($action === 'archive') {
                    if (archive_order($order_id)) {
                        $success_count++;
                    }
                } elseif ($action === 'cancel') {
                    if (cancel_order($order_id, 'Cancelado en masa por admin', $_SESSION['username'])) {
                        $success_count++;
                    }
                } elseif (in_array($action, ['impago', 'pagado', 'pendiente', 'lista_retiro', 'en_transito', 'en_reparto', 'entregada', 'fallida', 'devuelta', 'cancelada', 'rechazada'])) {
                    if (update_order_status($order_id, $action, $_SESSION['username'])) {
                        $success_count++;

                        // Send notification when order is marked as in transit
                        if ($action === 'en_transito') {
                            $updated_order = get_order_by_id($order_id);
                            if ($updated_order) {
                                $contact_preference = $updated_order['contact_preference'] ?? 'email';

                                if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                                    send_telegram_order_shipped_to_customer($updated_order);
                                } elseif (!empty($updated_order['customer_email'])) {
                                    queue_email('order_shipped', ['order' => $updated_order], 'normal');
                                }
                            }
                        }

                        // Send notification when order is marked as en_reparto (out for delivery)
                        if ($action === 'en_reparto') {
                            $updated_order = get_order_by_id($order_id);
                            if ($updated_order) {
                                $contact_preference = $updated_order['contact_preference'] ?? 'email';

                                if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                                    send_telegram_order_in_delivery_to_customer($updated_order);
                                } elseif (!empty($updated_order['customer_email'])) {
                                    queue_email('order_in_delivery', ['order' => $updated_order], 'normal');
                                }
                            }
                        }

                        // Send notification when order is marked as entregada (delivered)
                        if ($action === 'entregada') {
                            $updated_order = get_order_by_id($order_id);
                            if ($updated_order) {
                                $contact_preference = $updated_order['contact_preference'] ?? 'email';

                                if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                                    send_telegram_order_delivered_to_customer($updated_order);
                                } elseif (!empty($updated_order['customer_email'])) {
                                    queue_email('order_delivered', ['order' => $updated_order], 'normal');
                                }
                            }
                        }
                    }
                }
            }

            $result['message'] = "$success_count orden(es) procesada(s) exitosamente";
        } else {
            $result['error'] = 'No se seleccionaron órdenes';
        }
    }

    return $result;
}
