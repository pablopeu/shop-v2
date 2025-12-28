<?php
/**
 * Admin - Sales/Orders Management
 *
 * Panel principal de gestión de ventas y órdenes.
 * Este archivo es el controlador principal que:
 * - Maneja acciones de actualización/cancelación de órdenes
 * - Aplica filtros y búsqueda
 * - Calcula estadísticas del dashboard
 * - Renderiza las vistas del panel
 *
 * Módulos utilizados:
 * - actions.php: Procesamiento de acciones POST/GET
 * - filters.php: Filtrado y búsqueda de órdenes
 * - stats.php: Cálculo de métricas y estadísticas
 * - views.php: Componentes de vista HTML
 *
 * @package Admin
 * @subpackage Ventas
 */


// ============================================================================
// CONFIGURACIÓN E INICIALIZACIÓN
// ============================================================================

// Define admin access constant for included modules
define('ADMIN_ACCESS', true);

// Core dependencies


// Check admin authentication
require_admin();

// Get site configurations
$site_config = read_json(APP_PATH . '/config/site.json');

// Page title for header
$page_title = 'Gestión de Ventas';

// ============================================================================
// INCLUIR MÓDULOS DE VENTAS
// ============================================================================

require_once APP_PATH . '/includes/admin/ventas/actions.php';  // Manejo de acciones
require_once APP_PATH . '/includes/admin/ventas/filters.php';  // Filtrado de órdenes
require_once APP_PATH . '/includes/admin/ventas/stats.php';    // Estadísticas
require_once APP_PATH . '/includes/admin/ventas/views.php';    // Componentes de vista

// ============================================================================
// PROCESAMIENTO DE DATOS
// ============================================================================

// 1. Handle POST/GET actions (update, cancel, bulk actions)
$action_result = handle_order_actions();
$message = $action_result['message'];
$error = $action_result['error'];

// 2. Get all orders and apply filters
$all_orders = get_all_orders();
$filters = get_filter_params();
$orders = apply_order_filters($all_orders, $filters);

// 3. Calculate dashboard statistics
$stats = calculate_order_stats($all_orders);
extract($stats); // Extract stats variables for backward compatibility

// 4. Generate CSRF token for forms
$csrf_token = generate_csrf_token();

// 5. Get logged user info
$user = get_logged_user();

// 6. Status labels for UI display (estados universales)
$status_labels = [
    // Estados de pago
    'impago' => ['label' => 'Impago', 'color' => '#FF9800'],
    'pagado' => ['label' => 'Pagado', 'color' => '#4CAF50'],

    // Estados de logística
    'lista_retiro' => ['label' => 'Lista para Retiro', 'color' => '#00BCD4'],
    'en_transito' => ['label' => 'En Tránsito', 'color' => '#2196F3'],
    'en_reparto' => ['label' => 'En Reparto', 'color' => '#03A9F4'],
    'entregada' => ['label' => 'Entregada', 'color' => '#4CAF50'],

    // Estados de problema
    'fallida' => ['label' => 'Fallida', 'color' => '#FF5722'],
    'devuelta' => ['label' => 'Devuelta', 'color' => '#9C27B0'],
    'cancelada' => ['label' => 'Cancelada', 'color' => '#f44336'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#f44336']
];

// ============================================================================
// VISTA HTML
// ============================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ventas - Admin</title>
    <link rel="stylesheet" href="<?php echo url('/assets/css/admin/ventas.css'); ?>">
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

            <!-- Stats -->
            <?php render_stats_cards($stats); ?>

            <!-- Advanced Filters -->
            <?php render_advanced_filters($filters); ?>

            <!-- Bulk Actions and Status Filters - Compact Layout -->
            <?php
            render_compact_actions_bar($filters, $csrf_token);
            render_orders_table($orders, $filters, $status_labels);
            ?>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" data-action="closeOrderModal">&times;</span>
                <h2 id="modalOrderNumber">Orden #</h2>
            </div>
            <div id="modalOrderContent" class="modal-body">
                <!-- Content will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-action="closeOrderModal">Cancelar</button>
                <button type="button" id="btnSaveChanges" class="btn-save" data-action="saveAllChanges">
                    💾 Guardar Cambios
                </button>
            </div>
        </div>
    </div>

    <!-- Archive Order Modal -->
    <div id="archiveModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <span class="modal-close" data-action="closeArchiveModal">&times;</span>
                <h2>📦 Archivar Pedido</h2>
            </div>
            <div style="padding: 20px;">
                <p style="margin-bottom: 20px; font-size: 16px; color: #555;">
                    ¿Estás seguro de que deseas archivar la orden <strong id="archiveOrderNumber"></strong>?
                </p>
                <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    La orden se moverá al archivo de ventas. Podrás consultarla en la sección de ventas archivadas.
                </p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button data-action="closeArchiveModal" class="btn btn-secondary">
                        Cancelar
                    </button>
                    <a id="confirmArchiveBtn" href="#" class="btn btn-success">
                        Confirmar Archivado
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Action Confirmation Modal -->
    <div id="confirmBulkModal" class="modal">
        <div class="modal-content confirm-modal-content">
            <div class="confirm-modal-icon" id="confirmIcon">⚠️</div>
            <h2 class="confirm-modal-title" id="confirmTitle">Confirmar Acción</h2>
            <p class="confirm-modal-description" id="confirmDescription"></p>
            <div class="confirm-modal-details" id="confirmDetails"></div>
            <div class="confirm-modal-actions">
                <button class="modal-btn modal-btn-cancel" data-action="closeConfirmModal">
                    Cancelar
                </button>
                <button class="modal-btn" id="confirmButton" data-action="executeBulkAction">
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Modal -->
    <div id="unsavedChangesModal" class="modal" style="z-index: 10001;">
        <div class="modal-content" style="max-width: 500px; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); position: relative;">
            <span class="modal-close" data-action="cancelCloseOrderModal" style="position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #999; cursor: pointer; line-height: 20px; transition: color 0.3s;">&times;</span>
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 48px; margin-bottom: 10px;">⚠️</div>
                <h2 style="margin: 0; color: #333; font-size: 22px;">Cambios sin guardar</h2>
            </div>
            <p style="text-align: center; color: #666; margin-bottom: 25px; line-height: 1.5;">
                Hay cambios sin guardar en este formulario. Si cierras ahora, perderás estos cambios.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button data-action="confirmCloseOrderModal"
                        style="padding: 12px 24px; background: #95a5a6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Salir sin guardar
                </button>
                <button data-action="cancelCloseOrderModal"
                        style="padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Quedarme para guardar
                </button>
            </div>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>" type="module">
        // Import utility functions
        import { showToast, copyPaymentLink, formatPrice } from '<?php echo url('/assets/js/admin/ventas-utils.js'); ?>';
        import { initModal, viewOrder, switchTab, sendCustomMessage, saveAllChanges,
                 closeOrderModal, confirmCloseOrderModal, cancelCloseOrderModal,
                 showArchiveModal, closeArchiveModal } from '<?php echo url('/assets/js/admin/ventas-modal.js'); ?>';
        import { toggleAllCheckboxes, updateSelectedCount, confirmBulkAction,
                 showBulkActionModal, closeConfirmModal, executeBulkAction } from '<?php echo url('/assets/js/admin/ventas-bulk-actions.js'); ?>';

        // Expose utility functions immediately (don't need data)
        window.showToast = showToast;
        window.copyPaymentLink = copyPaymentLink;
        window.formatPrice = formatPrice;

        // Initialize modal module and expose functions when page loads
        const ordersData = <?php echo json_encode($orders); ?>;
        const token = '<?php echo $csrf_token; ?>';

        // Export CSRF token to global scope for API calls
        window.csrfToken = token;

        // Initialize immediately (before DOMContentLoaded)
        initModal(ordersData, token);

        // Expose modal functions globally (compatible with event delegation)
        window.viewOrder = function(eventOrId, element, params) {
            const orderId = params?.orderId || (typeof eventOrId === 'string' ? eventOrId : null);
            if (orderId) return viewOrder(orderId);
        };
        window.switchTab = switchTab;
        window.sendCustomMessage = sendCustomMessage;
        window.saveAllChanges = saveAllChanges;
        window.closeOrderModal = closeOrderModal;
        window.confirmCloseOrderModal = confirmCloseOrderModal;
        window.cancelCloseOrderModal = cancelCloseOrderModal;
        window.showArchiveModal = function(eventOrId, element, params) {
            const orderId = params?.orderId || (typeof eventOrId === 'string' ? eventOrId : null);
            const orderNumber = params?.orderNumber || (typeof arguments[1] === 'string' ? arguments[1] : null);
            if (orderId) return showArchiveModal(orderId, orderNumber);
        };
        window.closeArchiveModal = closeArchiveModal;

        // Expose bulk actions functions globally (compatible with event delegation)
        window.toggleAllCheckboxes = function(eventOrCheckbox, element, params) {
            const checkbox = element || (eventOrCheckbox instanceof HTMLElement ? eventOrCheckbox : document.getElementById('selectAll'));
            return toggleAllCheckboxes(checkbox);
        };
        window.updateSelectedCount = updateSelectedCount;
        window.confirmBulkAction = function(event, element, params) {
            // Prevent form submission if not confirmed
            if (event && event.preventDefault) event.preventDefault();
            return confirmBulkAction();
        };
        window.showBulkActionModal = showBulkActionModal;
        window.closeConfirmModal = closeConfirmModal;
        window.executeBulkAction = executeBulkAction;

        // Setup event listeners
        document.getElementById('confirmBulkModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', updateSelectedCount);
    </script>

    <script nonce="<?= csp_nonce() ?>">
        /**
         * Exporta las órdenes seleccionadas a formato CSV
         */
        function exportSelectedToCSV() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const orderIds = Array.from(checkboxes).map(cb => cb.value);

            if (orderIds.length === 0) {
                if (window.showToast) {
                    window.showToast('⚠️ Debes seleccionar al menos una venta para exportar');
                }
                return;
            }

            // Create form and submit to export API
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo url('/api/?endpoint=export-orders'); ?>';
            form.target = '_blank';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = token;
            form.appendChild(csrfInput);

            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = 'csv';
            form.appendChild(formatInput);

            const prefixInput = document.createElement('input');
            prefixInput.type = 'hidden';
            prefixInput.name = 'prefix';
            prefixInput.value = 'ventas';
            form.appendChild(prefixInput);

            orderIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            if (window.showToast) {
                window.showToast('📊 Exportando ' + orderIds.length + ' venta(s) a CSV...');
            }
        }

        /**
         * Solicita e imprime la etiqueta de envío
         */
        function printShippingLabel(event, element, params) {
            const orderId = params?.orderId;
            const shipmentId = params?.shipmentId;

            if (!orderId && !shipmentId) {
                if (window.showToast) {
                    window.showToast('⚠️ Error: No se pudo identificar el envío');
                }
                return;
            }

            // Disable button
            if (element) {
                element.disabled = true;
                element.textContent = '⏳ Obteniendo...';
            }

            // Build API URL para verificar primero si hay error
            const apiUrl = '<?php echo url('/api/?endpoint=print-shipping-label'); ?>' +
                          (orderId ? '&order_id=' + encodeURIComponent(orderId) : '') +
                          (shipmentId ? '&shipment_id=' + encodeURIComponent(shipmentId) : '') +
                          '&format=pdf&action=download';

            // Usar fetch para detectar errores antes de abrir ventana
            fetch(apiUrl, {
                method: 'HEAD', // Solo headers para verificar sin descargar
                credentials: 'same-origin'
            })
            .then(response => {
                if (response.ok) {
                    // Todo bien, abrir PDF en nueva ventana
                    window.open(apiUrl, '_blank');
                    if (window.showToast) {
                        window.showToast('✅ Abriendo etiqueta...');
                    }
                } else if (response.status === 409) {
                    // Error 409: Etiqueta aún no disponible
                    if (window.showToast) {
                        window.showToast('⏳ La etiqueta se está generando. Espera unos segundos y vuelve a intentar.');
                    }
                } else if (response.status === 404) {
                    if (window.showToast) {
                        window.showToast('❌ Etiqueta no encontrada. Verifica que el envío esté creado.');
                    }
                } else {
                    if (window.showToast) {
                        window.showToast('❌ Error al obtener la etiqueta. Código: ' + response.status);
                    }
                }
            })
            .catch(error => {
                console.error('Error checking label:', error);
                if (window.showToast) {
                    window.showToast('❌ Error de conexión al obtener la etiqueta');
                }
            })
            .finally(() => {
                // Restore button
                if (element) {
                    element.disabled = false;
                    element.textContent = '🖨️ Etiqueta';
                }
            });
        }

        /**
         * Crea un envío en Zipnova y luego imprime la etiqueta
         */
        function createAndPrintShippingLabel(event, element, params) {
            const orderId = params?.orderId;

            if (!orderId) {
                if (window.showToast) {
                    window.showToast('⚠️ Error: No se pudo identificar la orden');
                }
                return;
            }

            // Disable button and show loading state
            const originalText = element ? element.textContent : '';
            if (element) {
                element.disabled = true;
                element.textContent = '⏳ Creando envío...';
            }

            // Get CSRF token (usar la variable global window.csrfToken)
            const csrfToken = window.csrfToken || '<?php echo $csrf_token; ?>';

            // Build API URL
            const apiUrl = '<?php echo url('/api/?endpoint=create-shipment-from-order'); ?>';

            // Create shipment via API
            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    order_id: orderId,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (window.showToast) {
                        window.showToast('✅ Envío creado exitosamente');
                    }

                    // Wait a moment for UI feedback, then print label
                    setTimeout(() => {
                        // Update button to show we're getting label
                        if (element) {
                            element.textContent = '⏳ Obteniendo etiqueta...';
                        }

                        // Now get the label using the created shipment_id
                        const shipmentId = data.data.shipment_id;

                        // Call printShippingLabel with the new shipment_id
                        printShippingLabel(event, element, {
                            orderId: orderId,
                            shipmentId: shipmentId
                        });

                        // Reload page after a delay to show updated data
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }, 500);
                } else {
                    if (window.showToast) {
                        window.showToast('❌ Error: ' + (data.error || 'No se pudo crear el envío'));
                    }

                    // Restore button state
                    if (element) {
                        element.disabled = false;
                        element.textContent = originalText;
                    }
                }
            })
            .catch(error => {
                console.error('Error creating shipment:', error);
                if (window.showToast) {
                    window.showToast('❌ Error de conexión al crear el envío');
                }

                // Restore button state
                if (element) {
                    element.disabled = false;
                    element.textContent = originalText;
                }
            });
        }

        // Export for event delegation
        window.printShippingLabel = printShippingLabel;
        window.createAndPrintShippingLabel = createAndPrintShippingLabel;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
