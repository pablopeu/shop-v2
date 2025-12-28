/**
 * Ventas Modal - Gestión del modal de órdenes  
 * Módulo ES6 para manejar toda la lógica del modal de detalles de orden
 */

import { showToast, formatPrice } from './ventas-utils.js';

// Variables globales del módulo
let orders = [];
let csrfToken = '';
let modalHasUnsavedChanges = false;
let modalOriginalValues = {};
let modalUserHasInteracted = false;

/**
 * Inicializar el módulo con datos necesarios
 * @param {Array} ordersData - Array de órdenes
 * @param {string} token - CSRF token
 */
export function initModal(ordersData, token) {
    orders = ordersData;
    csrfToken = token;
    setupModalEventListeners();
}

/**
 * Setup event listeners para los modales
 */
function setupModalEventListeners() {
    document.getElementById('orderModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeOrderModal();
    });

    document.getElementById('archiveModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeArchiveModal();
    });

    document.getElementById('unsavedChangesModal')?.addEventListener('click', function(e) {
        if (e.target === this) cancelCloseOrderModal();
    });
}

export function viewOrder(orderId) {
    const order = orders.find(o => o.id === orderId);
    if (!order) return;

    document.getElementById('modalOrderNumber').textContent = 'Orden ' + order.order_number;

    let html = `
        <div class="modal-tabs">
            <button class="modal-tab active" data-action="switchTab" data-tab-id="tab-details">📋 Detalles</button>
            <button class="modal-tab" data-action="switchTab" data-tab-id="tab-payments">💳 Pagos</button>
            <button class="modal-tab" data-action="switchTab" data-tab-id="tab-status">📦 Estado & Tracking</button>
            <button class="modal-tab" data-action="switchTab" data-tab-id="tab-communication">💬 Comunicación</button>
        </div>

        <!-- TAB 1: Detalles -->
        <div id="tab-details" class="modal-tab-content active">
            <div class="form-group">
                <label><strong>Cliente:</strong></label>
                <p>${order.customer_name || 'N/A'}<br>
                   ${order.customer_email || ''}<br>
                   ${order.customer_phone || ''}</p>
            </div>

            <div class="form-group">
                <label><strong>Preferencia de Contacto:</strong></label>
                <p>${order.contact_preference === 'telegram' ? '📱 Telegram' : '📧 Email'}</p>
            </div>

            ${order.shipping_address ? `
            <div class="form-group">
                <label><strong>Dirección de Envío:</strong></label>
                <p>${order.shipping_address.address}<br>
                   ${order.shipping_address.city}, CP ${order.shipping_address.postal_code}</p>
            </div>
            ` : ''}

            ${order.notes && order.notes.trim() ? `
            <div class="form-group" style="background: #fff9e6; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;">
                <label><strong>💬 Mensaje del Cliente:</strong></label>
                <p style="margin-top: 10px; white-space: pre-wrap;">${order.notes}</p>
            </div>
            ` : ''}

            <div class="form-group">
                <label><strong>Productos:</strong></label>
                <div class="order-items">
                    ${order.items.map(item => `
                        <div class="order-item">
                            <span>${item.name} (x${item.quantity})</span>
                            <strong>${formatPrice(item.final_price, order.currency)}</strong>
                        </div>
                    `).join('')}
                    <div class="order-item" style="margin-top: 10px; padding-top: 10px;">
                        <span><strong>Subtotal:</strong></span>
                        <strong>${formatPrice(order.total, order.currency)}</strong>
                    </div>
                    ${order.mercadopago_data && order.mercadopago_data.total_fees ? `
                    <div class="order-item" style="color: #dc3545;">
                        <span>Comisión MercadoPago:</span>
                        <strong>- ${formatPrice(order.mercadopago_data.total_fees, order.currency)}</strong>
                    </div>
                    <div class="order-item" style="border-top: 2px solid #4CAF50; margin-top: 5px; padding-top: 10px; background: #f0f9f0;">
                        <span><strong>Neto Recibido:</strong></span>
                        <strong style="color: #4CAF50; font-size: 16px;">${formatPrice(order.mercadopago_data.net_received_amount || order.total, order.currency)}</strong>
                    </div>
                    ` : `
                    <div class="order-item" style="border-top: 2px solid #ccc; margin-top: 5px; padding-top: 10px;">
                        <span><strong>Total:</strong></span>
                        <strong>${formatPrice(order.total, order.currency)}</strong>
                    </div>
                    `}
                </div>
            </div>
        </div>

        <!-- TAB 2: Pagos -->
        <div id="tab-payments" class="modal-tab-content">
            <div class="form-group">
                <label><strong>Método de Pago:</strong></label>
                <p>${
                    order.payment_method === 'mercadopago' ? '💳 Mercadopago' :
                    order.payment_method === 'arrangement' ? '🤝 Arreglo con vendedor' :
                    order.payment_method === 'pickup_payment' ? '💵 Pago al retirar' :
                    '💵 Presencial'
                }</p>
            </div>

            ${order.mercadopago_data ? `
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea;">
                <label><strong>📊 Detalles de Mercadopago:</strong></label>
                <div style="margin-top: 10px; font-size: 13px;">
                    ${order.mercadopago_data.payment_id ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Payment ID:</span>
                        <span style="font-family: monospace;">${order.mercadopago_data.payment_id}</span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.status ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Estado:</span>
                        <span>
                            <span style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; color: white;
                                  background: ${order.mercadopago_data.status === 'approved' ? '#4CAF50' :
                                                 order.mercadopago_data.status === 'pending' || order.mercadopago_data.status === 'in_process' ? '#FFA726' : '#f44336'};">
                                ${order.mercadopago_data.status.toUpperCase()}
                            </span>
                            ${order.mercadopago_data.status_detail ? `<span style="color: #999; font-size: 11px; margin-left: 8px;">(${order.mercadopago_data.status_detail})</span>` : ''}
                        </span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.transaction_amount ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Monto:</span>
                        <span><strong>${order.mercadopago_data.currency_id || 'ARS'} $${parseFloat(order.mercadopago_data.transaction_amount).toFixed(2)}</strong></span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.payment_method_id ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Método:</span>
                        <span>${order.mercadopago_data.payment_type_id || 'N/A'} - ${order.mercadopago_data.payment_method_id}</span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.installments && order.mercadopago_data.installments > 1 ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Cuotas:</span>
                        <span>${order.mercadopago_data.installments}x</span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.card_last_four_digits ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Tarjeta:</span>
                        <span>**** **** **** ${order.mercadopago_data.card_last_four_digits}</span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.date_created ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Fecha creación:</span>
                        <span>${new Date(order.mercadopago_data.date_created).toLocaleString('es-AR')}</span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.date_approved ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Fecha aprobación:</span>
                        <span style="color: #4CAF50; font-weight: 600;">${new Date(order.mercadopago_data.date_approved).toLocaleString('es-AR')}</span>
                    </div>
                    ` : ''}
                    ${order.mercadopago_data.payer_email ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                        <span style="color: #666; font-weight: 600;">Email pagador:</span>
                        <span>${order.mercadopago_data.payer_email}</span>
                    </div>
                    ` : ''}
                </div>
                ${order.mercadopago_data.payment_id ? `
                <a href="verificar-pago-mp.php?payment_id=${order.mercadopago_data.payment_id}"
                   target="_blank"
                   style="display: inline-block; margin-top: 12px; padding: 6px 12px; background: #667eea; color: white;
                          text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;">
                    🔍 Ver detalles completos en MP
                </a>
                ` : ''}
            </div>
            ` : ''}

            ${order.payment_error ? `
            <div class="form-group" style="background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ff9800;">
                <label><strong>⚠️ Error de Pago:</strong></label>
                <div style="margin-top: 10px; font-size: 13px;">
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Mensaje:</span>
                        <span style="color: #d32f2f; font-family: monospace; font-size: 12px;">${order.payment_error.message}</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Fecha del error:</span>
                        <span>${new Date(order.payment_error.date).toLocaleString('es-AR')}</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                        <span style="color: #666; font-weight: 600;">Modo:</span>
                        <span>${order.payment_error.sandbox_mode ? 'Sandbox (prueba)' : 'Producción'}</span>
                    </div>
                </div>
                <small style="display: block; margin-top: 10px; color: #856404;">
                    💡 Este error indica un problema técnico al procesar el pago (error de API, problemas de conexión, etc.)
                </small>
            </div>
            ` : ''}

            ${order.chargebacks && order.chargebacks.length > 0 ? `
            <div class="form-group" style="background: #ffebee; padding: 15px; border-radius: 6px; border-left: 4px solid #f44336;">
                <label><strong>🚨 Contracargos (Chargebacks):</strong></label>
                <div style="margin-top: 10px;">
                    ${order.chargebacks.map((cb, index) => `
                        <div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: ${index < order.chargebacks.length - 1 ? '10px' : '0'}; border: 1px solid #ffcdd2;">
                            <div style="font-size: 13px;">
                                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                    <span style="color: #666; font-weight: 600;">Chargeback ID:</span>
                                    <span style="font-family: monospace; font-size: 12px;">${cb.chargeback_id}</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                    <span style="color: #666; font-weight: 600;">Acción:</span>
                                    <span>
                                        <span style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; color: white;
                                              background: ${cb.action === 'created' ? '#ff9800' : cb.action === 'lost' ? '#f44336' : cb.action === 'won' ? '#4CAF50' : '#999'};">
                                            ${cb.action ? cb.action.toUpperCase() : 'UNKNOWN'}
                                        </span>
                                    </span>
                                </div>
                                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                    <span style="color: #666; font-weight: 600;">Payment ID:</span>
                                    <span style="font-family: monospace; font-size: 12px;">${cb.payment_id}</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                                    <span style="color: #666; font-weight: 600;">Fecha:</span>
                                    <span>${new Date(cb.date).toLocaleString('es-AR')}</span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <small style="display: block; margin-top: 12px; color: #c62828; font-weight: 600;">
                    ⚠️ Un contracargo indica que el comprador disputó el pago con su banco.
                    ${order.chargebacks.some(cb => cb.action === 'created' || cb.action === 'lost') ? 'El stock fue restaurado automáticamente.' : ''}
                </small>
            </div>
            ` : ''}

            ${order.payment_link ? `
            <div class="form-group" style="background: #e3f2fd; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                <label><strong>🔗 Link de Pago de Mercadopago:</strong></label>
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 8px;">
                    <input type="text" value="${order.payment_link}" readonly
                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: monospace; background: white;">
                    <button type="button" data-action="copyPaymentLink" data-payment-link="${order.payment_link}"
                            style="padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                        📋 Copiar
                    </button>
                </div>
                <small style="color: #666; display: block; margin-top: 8px;">
                    ${order.payment_status === 'approved' ? '✅ Pago aprobado' :
                      order.payment_status === 'pending' ? '⏳ Pago pendiente' :
                      order.payment_status === 'rejected' ? '❌ Pago rechazado' :
                      '📝 Esperando pago'}
                </small>
            </div>
            ` : ''}

            <!-- Historial de Cambios de Estado -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea;">
                <label><strong>📋 Historial de Cambios de Estado:</strong></label>
                <div style="margin-top: 15px;">
                    ${order.status_history && order.status_history.length > 0 ?
                        order.status_history.map((change, index) => `
                            <div style="background: white; padding: 12px; margin-bottom: ${index < order.status_history.length - 1 ? '10px' : '0'}; border-radius: 4px; border-left: 3px solid ${
                                change.status === 'impago' ? '#FF9800' :
                                change.status === 'pagado' ? '#4CAF50' :
                                change.status === 'pendiente' ? '#9E9E9E' :
                                change.status === 'lista_retiro' ? '#00BCD4' :
                                change.status === 'en_transito' ? '#2196F3' :
                                change.status === 'en_reparto' ? '#03A9F4' :
                                change.status === 'entregada' ? '#4CAF50' :
                                change.status === 'fallida' ? '#FF5722' :
                                change.status === 'devuelta' ? '#9C27B0' :
                                change.status === 'cancelada' || change.status === 'rechazada' ? '#f44336' : '#999'
                            };">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <span style="font-weight: 600; color: #333;">
                                        ${change.status === 'impago' ? '💵 Impago' :
                                          change.status === 'pagado' ? '✅ Pagado' :
                                          change.status === 'pendiente' ? '⏳ Pendiente de Envío' :
                                          change.status === 'lista_retiro' ? '📋 Lista para Retiro' :
                                          change.status === 'en_transito' ? '🚚 En Tránsito' :
                                          change.status === 'en_reparto' ? '🚴 En Reparto' :
                                          change.status === 'entregada' ? '📦 Entregada' :
                                          change.status === 'fallida' ? '❌ Fallida' :
                                          change.status === 'devuelta' ? '↩️ Devuelta' :
                                          change.status === 'cancelada' ? '🚫 Cancelada' :
                                          change.status === 'rechazada' ? '⛔ Rechazada' :
                                          change.status}
                                    </span>
                                    <span style="font-size: 12px; color: #666;">
                                        ${new Date(change.date).toLocaleString('es-AR')}
                                    </span>
                                </div>
                                ${change.user ? `
                                <div style="font-size: 12px; color: #999;">
                                    👤 Por: ${change.user}
                                </div>
                                ` : ''}
                            </div>
                        `).join('') :
                        '<div style="text-align: center; color: #999; padding: 20px; font-style: italic;">No hay cambios de estado registrados</div>'
                    }
                </div>
            </div>
        </div>

        <!-- TAB 3: Estado & Tracking -->
        <div id="tab-status" class="modal-tab-content">
            <!-- Current Status Badge -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid ${
                order.status === 'impago' ? '#FF9800' :
                order.status === 'pagado' ? '#4CAF50' :
                order.status === 'pendiente' ? '#9E9E9E' :
                order.status === 'lista_retiro' ? '#00BCD4' :
                order.status === 'en_transito' ? '#2196F3' :
                order.status === 'en_reparto' ? '#03A9F4' :
                order.status === 'entregada' ? '#4CAF50' :
                order.status === 'fallida' ? '#FF5722' :
                order.status === 'devuelta' ? '#9C27B0' :
                order.status === 'cancelada' || order.status === 'rechazada' ? '#f44336' : '#999'
            };">
                <label style="margin-bottom: 10px; display: block;"><strong>📊 Estado Actual:</strong></label>
                <div style="display: inline-block;">
                    <span style="padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; color: white; background: ${
                        order.status === 'impago' ? '#FF9800' :
                        order.status === 'pagado' ? '#4CAF50' :
                        order.status === 'pendiente' ? '#9E9E9E' :
                        order.status === 'lista_retiro' ? '#00BCD4' :
                        order.status === 'en_transito' ? '#2196F3' :
                        order.status === 'en_reparto' ? '#03A9F4' :
                        order.status === 'entregada' ? '#4CAF50' :
                        order.status === 'fallida' ? '#FF5722' :
                        order.status === 'devuelta' ? '#9C27B0' :
                        order.status === 'cancelada' || order.status === 'rechazada' ? '#f44336' : '#999'
                    };">
                        ${order.status === 'impago' ? '💵 Impago' :
                          order.status === 'pagado' ? '✅ Pagado' :
                          order.status === 'pendiente' ? '⏳ Pendiente de Envío' :
                          order.status === 'lista_retiro' ? '📋 Lista para Retiro' :
                          order.status === 'en_transito' ? '🚚 En Tránsito' :
                          order.status === 'en_reparto' ? '🚴 En Reparto' :
                          order.status === 'entregada' ? '📦 Entregada' :
                          order.status === 'fallida' ? '❌ Fallida' :
                          order.status === 'devuelta' ? '↩️ Devuelta' :
                          order.status === 'cancelada' ? '🚫 Cancelada' :
                          order.status === 'rechazada' ? '⛔ Rechazada' :
                          order.status.toUpperCase()}
                    </span>
                </div>
            </div>

            <form method="POST" action="" id="formStatus" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="order_id" value="${order.id}">
                <input type="hidden" name="update_status" value="1">

                <div class="form-group">
                    <label for="status"><strong>Cambiar Estado:</strong></label>
                    <select name="status" id="status" style="font-weight: 600;">
                        <optgroup label="Estados de Pago">
                            <option value="impago" ${order.status === 'impago' ? 'selected' : ''}>💵 Impago</option>
                            <option value="pagado" ${order.status === 'pagado' ? 'selected' : ''}>✅ Pagado</option>
                        </optgroup>
                        <optgroup label="Estados de Logística">
                            <option value="pendiente" ${order.status === 'pendiente' ? 'selected' : ''}>⏳ Pendiente de Envío</option>
                            <option value="lista_retiro" ${order.status === 'lista_retiro' ? 'selected' : ''}>📋 Lista para Retiro</option>
                            <option value="en_transito" ${order.status === 'en_transito' ? 'selected' : ''}>🚚 En Tránsito</option>
                            <option value="en_reparto" ${order.status === 'en_reparto' ? 'selected' : ''}>🚴 En Reparto</option>
                            <option value="entregada" ${order.status === 'entregada' ? 'selected' : ''}>📦 Entregada</option>
                        </optgroup>
                        <optgroup label="Estados de Problema">
                            <option value="fallida" ${order.status === 'fallida' ? 'selected' : ''}>❌ Fallida</option>
                            <option value="devuelta" ${order.status === 'devuelta' ? 'selected' : ''}>↩️ Devuelta</option>
                            <option value="cancelada" ${order.status === 'cancelada' ? 'selected' : ''}>🚫 Cancelada</option>
                            <option value="rechazada" ${order.status === 'rechazada' ? 'selected' : ''}>⛔ Rechazada</option>
                        </optgroup>
                    </select>
                </div>
            </form>

            <hr style="margin: 20px 0;">

            <form method="POST" action="" id="formTracking" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="order_id" value="${order.id}">
                <input type="hidden" name="add_tracking" value="1">

                <div class="form-group">
                    <label for="tracking_number"><strong>Número de Seguimiento:</strong></label>
                    <input type="text" name="tracking_number" id="tracking_number"
                           value="${order.tracking_number || ''}" placeholder="Ej: CA123456789AR">
                </div>

                <div class="form-group">
                    <label for="tracking_url"><strong>URL de Seguimiento:</strong></label>
                    <input type="text" name="tracking_url" id="tracking_url"
                           value="${order.tracking_url || ''}" placeholder="https://...">
                </div>
            </form>
        </div>

        <!-- TAB 4: Comunicación -->
        <div id="tab-communication" class="modal-tab-content">
            <form onsubmit="sendCustomMessage(event, '${order.id}')">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="order_id" value="${order.id}">

                <div class="form-group">
                    <label><strong>Enviar Mensaje al Cliente:</strong></label>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                        Medio elegido: <strong>${order.contact_preference === 'telegram' ? '📱 Telegram' : '📧 Email'}</strong>
                        ${order.contact_preference === 'telegram' && !order.telegram_chat_id ? '<br><span style="color: #dc3545;">⚠️ No hay chat_id de Telegram registrado</span>' : ''}
                    </p>
                    <textarea name="custom_message" id="custom_message"
                              rows="4"
                              placeholder="Escribe tu mensaje personalizado aquí..."
                              required
                              style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; font-family: inherit;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    📤 Enviar Mensaje
                </button>
            </form>

            <hr style="margin: 30px 0;">

            <div class="form-group">
                <label><strong>📋 Historial de Comunicación:</strong></label>
                <div class="message-history">
                    ${order.notes && order.notes.trim() ? `
                        <div class="message-item" style="background-color: #fff9e6; border-left: 4px solid #ffc107;">
                            <div class="message-header">
                                <div class="message-meta">
                                    <span class="message-channel" style="background-color: #ffc107; color: #000;">💬 Mensaje Inicial</span>
                                    <span>${new Date(order.date || order.created_at).toLocaleString('es-AR')}</span>
                                </div>
                            </div>
                            <div class="message-body" style="white-space: pre-wrap;"><strong>Cliente escribió en el checkout:</strong><br>${order.notes}</div>
                        </div>
                    ` : ''}
                    ${order.messages && order.messages.length > 0 ?
                        order.messages.map(msg => `
                            <div class="message-item">
                                <div class="message-header">
                                    <div class="message-meta">
                                        <span class="message-channel ${msg.channel}">${msg.channel === 'email' ? '📧 Email' : '📱 Telegram'}</span>
                                        <span>${new Date(msg.date).toLocaleString('es-AR')}</span>
                                    </div>
                                </div>
                                <div class="message-body">${msg.message}</div>
                            </div>
                        `).join('') :
                        (order.notes && order.notes.trim() ? '' : '<div class="no-messages">No hay mensajes enviados aún</div>')
                    }
                </div>
            </div>
        </div>
    `;

    document.getElementById('modalOrderContent').innerHTML = html;
    document.getElementById('orderModal').classList.add('active');

    // Setup unsaved changes detection for modal forms
    setupModalChangeDetection();
}

export function switchTab(event, element, params) {
    // Obtener tabId desde params o desde el primer argumento si se llama directamente
    const tabId = params?.tabId || (typeof event === 'string' ? event : null);

    if (!tabId) return;

    document.querySelectorAll('.modal-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.modal-tab-content').forEach(content => content.classList.remove('active'));

    // Si element existe (llamada desde event delegation), usarlo; si no, usar event.target
    const targetElement = element || (event?.target);
    if (targetElement) {
        targetElement.classList.add('active');
    }

    document.getElementById(tabId).classList.add('active');
}

export function sendCustomMessage(event, orderId) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    const message = formData.get('custom_message');

    if (!message || message.trim() === '') {
        showNotification('Por favor escribe un mensaje', 'warning');
        return;
    }

    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Enviando...';

    // Debug: Log FormData contents
    console.log('=== SENDING MESSAGE ===');
    console.log('Order ID:', formData.get('order_id'));
    console.log('CSRF Token:', formData.get('csrf_token'));
    console.log('Message:', formData.get('custom_message'));
    console.log('=======================');

    // Send message via AJAX
    fetch('api/send-custom-message.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text().then(text => {
            console.log('Response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        console.log('Parsed data:', data);
        if (data.success) {
            showNotification('Mensaje enviado exitosamente', 'success');

            // Update local orders array with new message
            const orderIndex = orders.findIndex(o => o.id === orderId);
            if (orderIndex !== -1) {
                const message = formData.get('custom_message');
                const newMessage = {
                    date: new Date().toISOString().replace('T', ' ').substring(0, 19),
                    channel: data.channel,
                    message: message,
                    sent_by: 'admin'
                };

                // Initialize messages array if it doesn't exist
                if (!orders[orderIndex].messages) {
                    orders[orderIndex].messages = [];
                }

                // Add new message to the beginning
                orders[orderIndex].messages.unshift(newMessage);

                console.log('Updated order with new message:', newMessage);
            }

            form.reset();
            // Reload order to show new message in history
            viewOrder(orderId);
        } else {
            showNotification('Error: ' + (data.message || 'No se pudo enviar el mensaje'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error: ' + error.message, 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
    });
}

export function saveAllChanges() {
    const btnSave = document.getElementById('btnSaveChanges');

    // Get current tab to know which form to submit
    const activeTab = document.querySelector('.modal-tab-content.active');

    if (!activeTab) {
        // No active tab, just close
        closeOrderModal();
        return;
    }

    // Find forms in the active tab
    const forms = activeTab.querySelectorAll('form');

    if (forms.length === 0) {
        // No forms in this tab, just close
        closeOrderModal();
        return;
    }

    // Check if forms have changed
    if (!modalHasUnsavedChanges) {
        // No changes to save, just close
        closeOrderModal();
        return;
    }

    // Submit the first form found (should be the only one per tab that needs backend submission)
    const form = forms[0];

    // Check if this is the sendCustomMessage form (skip it)
    if (form.getAttribute('onsubmit') && form.getAttribute('onsubmit').includes('sendCustomMessage')) {
        showToast('ℹ️ Usa el botón "Enviar Mensaje" para este formulario');
        return;
    }

    // Disable button to prevent double submission
    btnSave.disabled = true;
    btnSave.textContent = '⏳ Guardando...';

    // Remove the onsubmit="return false;" temporarily to allow real submission
    form.removeAttribute('onsubmit');

    // Submit the form
    form.submit();
}

function setupModalChangeDetection() {
    const modalContent = document.getElementById('modalOrderContent');
    const forms = modalContent.querySelectorAll('form');
    const inputs = modalContent.querySelectorAll('input, select, textarea');
    const globalSaveButton = document.getElementById('btnSaveChanges');

    // Store original values (skip inputs without name or id)
    modalOriginalValues = {};
    inputs.forEach(input => {
        const key = input.name || input.id;
        if (key) {
            modalOriginalValues[key] = input.type === 'checkbox' ? input.checked : input.value;
        }
    });

    // Reset state
    modalHasUnsavedChanges = false;
    modalUserHasInteracted = false; // Reset interaction flag

    // Reset global save button style
    if (globalSaveButton) {
        globalSaveButton.classList.remove('has-changes');
    }

    // Detect changes - only after a small delay to avoid false positives from browser autocomplete
    setTimeout(() => {
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                modalUserHasInteracted = true;
                checkModalChanges(inputs, globalSaveButton);
            });
            input.addEventListener('change', () => {
                modalUserHasInteracted = true;
                checkModalChanges(inputs, globalSaveButton);
            });
        });
    }, 100);

    // Mark as saved when form is submitted
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            modalHasUnsavedChanges = false;
            modalUserHasInteracted = false;
        });
    });
}

function checkModalChanges(inputs, globalSaveButton) {
    let hasChanges = false;
    inputs.forEach(input => {
        const key = input.name || input.id;
        if (!key) return; // Skip inputs without name or id

        const currentValue = input.type === 'checkbox' ? input.checked : input.value;
        const originalValue = modalOriginalValues[key];

        // Only compare if we have an original value
        if (originalValue !== undefined && currentValue !== originalValue) {
            hasChanges = true;
        }
    });

    modalHasUnsavedChanges = hasChanges;

    // Update global button class
    if (globalSaveButton) {
        if (hasChanges) {
            globalSaveButton.classList.add('has-changes');
        } else {
            globalSaveButton.classList.remove('has-changes');
        }
    }
}

export function closeOrderModal() {
    // Only show warning if user actually interacted with the form AND there are changes
    if (modalUserHasInteracted && modalHasUnsavedChanges) {
        // Show custom unsaved changes modal
        document.getElementById('unsavedChangesModal').classList.add('active');
    } else {
        // Close directly - no interaction or no changes
        document.getElementById('orderModal').classList.remove('active');
        modalHasUnsavedChanges = false;
        modalUserHasInteracted = false;
    }
}

export function confirmCloseOrderModal() {
    // User confirmed to leave without saving
    modalHasUnsavedChanges = false;
    modalUserHasInteracted = false;
    document.getElementById('unsavedChangesModal').classList.remove('active');
    document.getElementById('orderModal').classList.remove('active');
}

export function cancelCloseOrderModal() {
    // User wants to stay and save
    document.getElementById('unsavedChangesModal').classList.remove('active');

    // Focus on the first save button in the modal
    const modalContent = document.getElementById('modalOrderContent');
    const saveButton = modalContent.querySelector('button[type="submit"]');

    if (saveButton) {
        // Scroll to the button
        saveButton.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Wait for scroll to finish, then focus and add highlight
        setTimeout(() => {
            const originalTransform = saveButton.style.transform || '';
            const originalBoxShadow = saveButton.style.boxShadow || '';

            saveButton.focus();
            saveButton.style.transform = 'scale(1.05)';
            saveButton.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.4)';

            // Remove highlight after 1 second
            setTimeout(() => {
                saveButton.style.transform = originalTransform;
                saveButton.style.boxShadow = originalBoxShadow;
            }, 1000);
        }, 500);
    }
}

export function showArchiveModal(orderId, orderNumber) {
    document.getElementById('archiveOrderNumber').textContent = orderNumber;
    // Construir URL completa para asegurar que la redirección funcione
    const baseUrl = window.location.pathname + window.location.search.split('&')[0]; // Mantener solo ?page=ventas
    const archiveUrl = baseUrl + (baseUrl.includes('?') ? '&' : '?') + 'action=archive&id=' + encodeURIComponent(orderId);
    document.getElementById('confirmArchiveBtn').href = archiveUrl;
    document.getElementById('archiveModal').classList.add('active');
}

export function closeArchiveModal() {
    document.getElementById('archiveModal').classList.remove('active');
}
