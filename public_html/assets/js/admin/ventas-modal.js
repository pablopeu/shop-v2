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
            <button class="modal-tab active" data-action="switchTab" data-tab-id="tab-resumen">📋 Resumen de Orden</button>
            <button class="modal-tab" data-action="switchTab" data-tab-id="tab-envio">🚚 Envío y Logística</button>
            <button class="modal-tab" data-action="switchTab" data-tab-id="tab-comunicacion">💬 Comunicación</button>
        </div>

        <!-- TAB 1: Resumen de Orden (Detalles + Pagos fusionados) -->
        <div id="tab-resumen" class="modal-tab-content active">
            <!-- Información del Cliente -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>👤 Información del Cliente</strong></label>
                <div style="display: grid; gap: 8px;">
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Nombre:</span>
                        <span style="font-weight: 500;">${order.customer_name || 'N/A'}</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Email:</span>
                        <span>${order.customer_email || 'N/A'}</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Teléfono:</span>
                        <span>${order.customer_phone || 'N/A'}</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Contacto preferido:</span>
                        <span>${order.contact_preference === 'telegram' ? '📱 Telegram' : '📧 Email'}</span>
                    </div>
                </div>
            </div>

            ${order.shipping_address ? `
            <div class="form-group" style="background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #4CAF50;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>📍 Dirección de Envío</strong></label>
                <p style="margin: 0; line-height: 1.6;">${order.shipping_address.address}<br>
                   ${order.shipping_address.city}, CP ${order.shipping_address.postal_code}</p>
            </div>
            ` : ''}

            ${order.notes && order.notes.trim() ? `
            <div class="form-group" style="background: #fff9e6; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>💬 Mensaje del Cliente</strong></label>
                <p style="margin: 0; white-space: pre-wrap; line-height: 1.6; color: #555;">${order.notes}</p>
            </div>
            ` : ''}

            <!-- Productos y Totales -->
            <div class="form-group" style="background: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>🛒 Productos</strong></label>
                <div class="order-items">
                    ${order.items.map(item => `
                        <div class="order-item">
                            <span>${item.name} <span style="color: #999;">(x${item.quantity})</span></span>
                            <strong>${formatPrice(item.final_price, order.currency)}</strong>
                        </div>
                    `).join('')}
                    <div class="order-item" style="margin-top: 10px; padding-top: 10px; border-top: 2px solid #e0e0e0;">
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
                    <div class="order-item" style="border-top: 2px solid #4CAF50; margin-top: 5px; padding-top: 10px;">
                        <span><strong>Total:</strong></span>
                        <strong>${formatPrice(order.total, order.currency)}</strong>
                    </div>
                    `}
                </div>
            </div>

            <!-- Detalles de Pago -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>💳 Información de Pago</strong></label>
                <div style="display: grid; gap: 8px;">
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Método:</span>
                        <span>${
                            order.payment_method === 'mercadopago' ? '💳 Mercadopago' :
                            order.payment_method === 'arrangement' ? '🤝 Arreglo con vendedor' :
                            order.payment_method === 'pickup_payment' ? '💵 Pago al retirar' :
                            '💵 Presencial'
                        }</span>
                    </div>
                </div>
            </div>

            ${order.mercadopago_data ? `
            <div class="form-group" style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196F3;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>📊 Detalles de Mercadopago</strong></label>
                <div style="display: grid; gap: 6px; font-size: 13px;">
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
                    ${order.mercadopago_data.date_approved ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                        <span style="color: #666; font-weight: 600;">Fecha aprobación:</span>
                        <span style="color: #4CAF50; font-weight: 600;">${new Date(order.mercadopago_data.date_approved).toLocaleString('es-AR')}</span>
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
            <div class="form-group" style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ff9800;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>⚠️ Error de Pago</strong></label>
                <div style="font-size: 13px;">
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                        <span style="color: #666; font-weight: 600;">Mensaje:</span>
                        <span style="color: #d32f2f; font-family: monospace; font-size: 12px;">${order.payment_error.message}</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                        <span style="color: #666; font-weight: 600;">Fecha:</span>
                        <span>${new Date(order.payment_error.date).toLocaleString('es-AR')}</span>
                    </div>
                </div>
            </div>
            ` : ''}

            ${order.chargebacks && order.chargebacks.length > 0 ? `
            <div class="form-group" style="background: #ffebee; padding: 15px; border-radius: 8px; border-left: 4px solid #f44336;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>🚨 Contracargos</strong></label>
                <div style="margin-top: 10px;">
                    ${order.chargebacks.map((cb, index) => `
                        <div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: ${index < order.chargebacks.length - 1 ? '10px' : '0'}; border: 1px solid #ffcdd2;">
                            <div style="font-size: 13px;">
                                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                    <span style="color: #666; font-weight: 600;">Chargeback ID:</span>
                                    <span style="font-family: monospace; font-size: 12px;">${cb.chargeback_id}</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                                    <span style="color: #666; font-weight: 600;">Acción:</span>
                                    <span>
                                        <span style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; color: white;
                                              background: ${cb.action === 'created' ? '#ff9800' : cb.action === 'lost' ? '#f44336' : cb.action === 'won' ? '#4CAF50' : '#999'};">
                                            ${cb.action ? cb.action.toUpperCase() : 'UNKNOWN'}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}

            ${order.payment_link ? `
            <div class="form-group" style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196F3;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>🔗 Link de Pago</strong></label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" value="${order.payment_link}" readonly
                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: monospace; background: white;">
                    <button type="button" data-action="copyPaymentLink" data-payment-link="${order.payment_link}"
                            style="padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                        📋 Copiar
                    </button>
                </div>
            </div>
            ` : ''}

            <!-- Timeline de Estados -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                <label style="font-size: 15px; margin-bottom: 15px; display: block; color: #333;"><strong>📋 Historial de Estados</strong></label>
                <div style="position: relative;">
                    ${order.status_history && order.status_history.length > 0 ?
                        order.status_history.map((change, index) => `
                            <div style="position: relative; padding-left: 30px; margin-bottom: ${index < order.status_history.length - 1 ? '20px' : '0'};">
                                <div style="position: absolute; left: 0; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: ${
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
                                }; border: 3px solid white; box-shadow: 0 0 0 1px #e0e0e0;"></div>
                                ${index < order.status_history.length - 1 ? `<div style="position: absolute; left: 6px; top: 19px; bottom: -20px; width: 2px; background: #e0e0e0;"></div>` : ''}
                                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #e0e0e0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <span style="font-weight: 600; color: #333; font-size: 14px;">
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
                                    </div>
                                    <div style="font-size: 12px; color: #999;">
                                        ${new Date(change.date).toLocaleString('es-AR')}
                                        ${change.user ? ` • Por: ${change.user}` : ''}
                                    </div>
                                </div>
                            </div>
                        `).join('') :
                        '<div style="text-align: center; color: #999; padding: 20px; font-style: italic;">No hay cambios de estado registrados</div>'
                    }
                </div>
            </div>
        </div>

        <!-- TAB 2: Envío y Logística -->
        <div id="tab-envio" class="modal-tab-content">
            <!-- Información del Servicio de Envío -->
            ${order.delivery_method ? `
            <div class="form-group" style="background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #4CAF50;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>📦 Método de Entrega</strong></label>
                <div style="display: grid; gap: 8px;">
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Tipo:</span>
                        <span style="font-weight: 500;">${order.delivery_method === 'shipping' ? '🚚 Envío a domicilio' : '🏪 Retiro en local'}</span>
                    </div>
                    ${order.shipping_quote_data && order.shipping_quote_data.carrier_name ? `
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Carrier:</span>
                        <span>${order.shipping_quote_data.carrier_name}</span>
                    </div>
                    ` : ''}
                    ${order.shipping_quote_data && order.shipping_quote_data.service_name ? `
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Servicio:</span>
                        <span>${order.shipping_quote_data.service_name}</span>
                    </div>
                    ` : ''}
                    ${order.shipping_quote_data && order.shipping_quote_data.price ? `
                    <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Costo:</span>
                        <span><strong>${formatPrice(order.shipping_quote_data.price, 'ARS')}</strong></span>
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}

            <!-- Estado Actual del Envío -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid ${
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
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>📊 Estado Actual</strong></label>
                <div style="display: inline-block;">
                    <span style="padding: 10px 20px; border-radius: 8px; font-size: 15px; font-weight: 600; color: white; background: ${
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

            <!-- Tracking del Carrier (Automático) -->
            ${order.shipping && (order.shipping.tracking_number || order.shipping.tracking_url || order.shipping.carrier_shipment_id) ? `
            <div class="form-group" style="background: #e3f2fd; padding: 15px; border-radius: 8px; border-left: 4px solid #2196F3;">
                <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>🔍 Tracking del Carrier</strong></label>
                <div style="display: grid; gap: 10px; font-size: 14px;">
                    ${order.shipping.carrier ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Carrier:</span>
                        <span style="font-weight: 500;">${order.shipping.carrier}</span>
                    </div>
                    ` : ''}
                    ${order.shipping.carrier_shipment_id ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Shipment ID:</span>
                        <span style="font-family: monospace; font-size: 13px;">${order.shipping.carrier_shipment_id}</span>
                    </div>
                    ` : ''}
                    ${order.shipping.tracking_number ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px;">
                        <span style="color: #666; font-weight: 600;">Número de Tracking:</span>
                        <span style="font-family: monospace; font-size: 14px; font-weight: 600; color: #2196F3;">${order.shipping.tracking_number}</span>
                    </div>
                    ` : ''}
                    ${order.shipping.tracking_url ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px; align-items: center;">
                        <span style="color: #666; font-weight: 600;">Rastrear:</span>
                        <a href="${order.shipping.tracking_url}" target="_blank"
                           style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #2196F3; color: white;
                                  text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; width: fit-content;">
                            🔗 Ver tracking en carrier
                        </a>
                    </div>
                    ` : ''}
                    ${order.shipping.label_url ? `
                    <div style="display: grid; grid-template-columns: 150px 1fr; gap: 10px; align-items: center;">
                        <span style="color: #666; font-weight: 600;">Etiqueta:</span>
                        <button type="button" class="btn btn-sm"
                                style="background: #667eea; color: white; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; width: fit-content;"
                                data-action="printShippingLabel"
                                data-order-id="${order.id}"
                                data-shipment-id="${order.shipping.carrier_shipment_id || ''}">
                            🖨️ Imprimir Etiqueta
                        </button>
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}

            <!-- Cambiar Estado Logístico -->
            <form method="POST" action="" id="formStatus" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="order_id" value="${order.id}">
                <input type="hidden" name="update_status" value="1">

                <div class="form-group" style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                    <label for="status" style="font-size: 15px; margin-bottom: 10px; display: block; color: #333;"><strong>🔄 Cambiar Estado</strong></label>
                    <select name="status" id="status" style="font-weight: 600; padding: 10px; font-size: 14px; width: 100%; border-radius: 6px; border: 2px solid #e0e0e0;">
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
                    <small style="display: block; margin-top: 8px; color: #666; font-size: 12px;">
                        💡 El cambio se guardará al hacer clic en "Guardar Cambios"
                    </small>
                </div>
            </form>

            <!-- Tracking Manual (Solo si no hay tracking automático) -->
            ${!order.shipping || (!order.shipping.tracking_number && !order.shipping.tracking_url) ? `
            <form method="POST" action="" id="formTracking" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="${csrfToken}">
                <input type="hidden" name="order_id" value="${order.id}">
                <input type="hidden" name="add_tracking" value="1">

                <div class="form-group" style="background: #fff9e6; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                    <label style="font-size: 15px; margin-bottom: 12px; display: block; color: #333;"><strong>✏️ Tracking Manual</strong></label>
                    <small style="display: block; margin-bottom: 12px; color: #856404; font-size: 13px;">
                        ⚠️ Solo usar si el envío no se creó mediante carrier. Si se generó etiqueta, el tracking es automático.
                    </small>

                    <div style="margin-bottom: 12px;">
                        <label for="tracking_number" style="display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 13px;">Número de Seguimiento:</label>
                        <input type="text" name="tracking_number" id="tracking_number"
                               value="${order.tracking_number || ''}"
                               placeholder="Ej: CA123456789AR"
                               style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                    </div>

                    <div>
                        <label for="tracking_url" style="display: block; margin-bottom: 6px; font-weight: 600; color: #555; font-size: 13px;">URL de Seguimiento:</label>
                        <input type="text" name="tracking_url" id="tracking_url"
                               value="${order.tracking_url || ''}"
                               placeholder="https://..."
                               style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
            </form>
            ` : ''}
        </div>

        <!-- TAB 3: Comunicación -->
        <div id="tab-comunicacion" class="modal-tab-content">
            <!-- Enviar Nuevo Mensaje -->
            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                <form onsubmit="sendCustomMessage(event, '${order.id}')">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="order_id" value="${order.id}">

                    <label style="font-size: 15px; margin-bottom: 10px; display: block; color: #333;"><strong>📤 Enviar Mensaje al Cliente</strong></label>

                    <div style="background: ${order.contact_preference === 'telegram' ? '#e3f2fd' : '#fff3e0'}; padding: 10px; border-radius: 6px; margin-bottom: 12px; border-left: 4px solid ${order.contact_preference === 'telegram' ? '#2196F3' : '#FF9800'};">
                        <div style="font-size: 13px; color: #555;">
                            <strong>Canal de envío:</strong> ${order.contact_preference === 'telegram' ? '📱 Telegram' : '📧 Email'}
                            ${order.contact_preference === 'telegram' && !order.telegram_chat_id ? '<br><span style="color: #dc3545; font-weight: 600;">⚠️ No hay chat_id de Telegram registrado</span>' : ''}
                        </div>
                    </div>

                    <textarea name="custom_message" id="custom_message"
                              rows="4"
                              placeholder="Escribe tu mensaje personalizado aquí..."
                              required
                              style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical;"></textarea>

                    <button type="submit" class="btn btn-primary" style="margin-top: 10px; padding: 10px 20px; font-size: 14px; font-weight: 600;">
                        📤 Enviar Mensaje
                    </button>
                </form>
            </div>

            <!-- Historial de Mensajes Estilo Chat -->
            <div class="form-group" style="margin-top: 20px;">
                <label style="font-size: 15px; margin-bottom: 15px; display: block; color: #333;"><strong>💬 Historial de Comunicación</strong></label>
                <div style="background: #ffffff; border-radius: 8px; border: 1px solid #e0e0e0; padding: 15px; max-height: 500px; overflow-y: auto;">
                    ${order.notes && order.notes.trim() ? `
                        <!-- Mensaje inicial del cliente -->
                        <div style="margin-bottom: 20px; display: flex; flex-direction: column; align-items: flex-start;">
                            <div style="background: #fff9e6; padding: 12px 16px; border-radius: 12px; border-bottom-left-radius: 4px; max-width: 80%; border-left: 3px solid #ffc107; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                    <span style="display: inline-block; padding: 3px 8px; background: #ffc107; color: #000; border-radius: 4px; font-size: 11px; font-weight: 700;">💬 CLIENTE</span>
                                    <span style="font-size: 11px; color: #999;">${new Date(order.date || order.created_at).toLocaleString('es-AR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})}</span>
                                </div>
                                <div style="color: #555; font-size: 14px; line-height: 1.5; white-space: pre-wrap;">${order.notes}</div>
                                <div style="margin-top: 6px; font-size: 11px; color: #999; font-style: italic;">Mensaje escrito en el checkout</div>
                            </div>
                        </div>
                    ` : ''}
                    ${order.messages && order.messages.length > 0 ?
                        order.messages.map(msg => `
                            <div style="margin-bottom: 20px; display: flex; flex-direction: column; align-items: flex-end;">
                                <div style="background: ${msg.channel === 'email' ? '#e3f2fd' : '#e8f5e9'}; padding: 12px 16px; border-radius: 12px; border-bottom-right-radius: 4px; max-width: 80%; border-right: 3px solid ${msg.channel === 'email' ? '#2196F3' : '#4CAF50'}; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                        <span style="display: inline-block; padding: 3px 8px; background: ${msg.channel === 'email' ? '#2196F3' : '#4CAF50'}; color: white; border-radius: 4px; font-size: 11px; font-weight: 700;">${msg.channel === 'email' ? '📧 EMAIL' : '📱 TELEGRAM'}</span>
                                        <span style="font-size: 11px; color: #999;">${new Date(msg.date).toLocaleString('es-AR', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'})}</span>
                                    </div>
                                    <div style="color: #333; font-size: 14px; line-height: 1.5; white-space: pre-wrap;">${msg.message}</div>
                                </div>
                            </div>
                        `).join('') :
                        (!order.notes || !order.notes.trim() ? '<div style="text-align: center; color: #999; padding: 40px 20px; font-style: italic; font-size: 14px;">No hay mensajes enviados aún. ¡Envía el primer mensaje al cliente!</div>' : '')
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

    // Check if there are unsaved changes
    if (!modalHasUnsavedChanges) {
        // No changes to save, just close
        closeOrderModal();
        return;
    }

    // Find ALL forms in ALL tabs that have changed values
    const modalContent = document.getElementById('modalOrderContent');
    const allForms = modalContent.querySelectorAll('form');
    let formToSubmit = null;

    // Check each form to find the one with changes
    for (const form of allForms) {
        // Skip sendCustomMessage forms
        if (form.getAttribute('onsubmit') && form.getAttribute('onsubmit').includes('sendCustomMessage')) {
            continue;
        }

        // Check if this form has changed inputs
        const inputs = form.querySelectorAll('input, select, textarea');
        for (const input of inputs) {
            const key = input.name || input.id;
            if (key && modalOriginalValues[key] !== undefined) {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== modalOriginalValues[key]) {
                    formToSubmit = form;
                    break;
                }
            }
        }

        if (formToSubmit) break;
    }

    if (!formToSubmit) {
        // No form with changes found, just close
        closeOrderModal();
        return;
    }

    // Disable button to prevent double submission
    btnSave.disabled = true;
    btnSave.textContent = '⏳ Guardando...';

    // Remove the onsubmit="return false;" temporarily to allow real submission
    formToSubmit.removeAttribute('onsubmit');

    // Submit the form with changes
    formToSubmit.submit();
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

    // Call saveAllChanges() to save and close automatically
    saveAllChanges();
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
