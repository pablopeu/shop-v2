/**
 * Ventas Bulk Actions - Gestión de acciones masivas
 * Módulo ES6 para manejar toda la lógica de acciones masivas sobre órdenes
 */

import { showToast } from './ventas-utils.js';

/**
 * Toggle all checkboxes in the orders list
 * @param {HTMLInputElement} source - The "select all" checkbox element
 */
export function toggleAllCheckboxes(source) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
    updateSelectedCount();
}

/**
 * Update the count of selected orders
 */
export function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const count = checkboxes.length;
    const countElement = document.getElementById('selectedCount');

    if (count > 0) {
        countElement.textContent = `${count} orden(es) seleccionada(s)`;
        countElement.style.fontWeight = 'bold';
        countElement.style.color = '#4CAF50';
    } else {
        countElement.textContent = '';
    }

    // Update "select all" checkbox state
    const allCheckboxes = document.querySelectorAll('.order-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
    }
}

/**
 * Confirm bulk action before execution
 * @returns {boolean} false to prevent default form submission
 */
export function confirmBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');

    if (!action) {
        showToast('Por favor selecciona una acción');
        return false;
    }

    if (checkboxes.length === 0) {
        showToast('Por favor selecciona al menos una orden');
        return false;
    }

    // Show confirmation modal
    showBulkActionModal(action, checkboxes.length);
    return false; // Prevent form submission, will be handled by modal
}

/**
 * Show bulk action confirmation modal
 * @param {string} action - The action to perform (pending, cobrada, shipped, delivered, cancel, archive)
 * @param {number} count - Number of selected orders
 */
export function showBulkActionModal(action, count) {
    const modal = document.getElementById('confirmBulkModal');
    const icon = document.getElementById('confirmIcon');
    const title = document.getElementById('confirmTitle');
    const description = document.getElementById('confirmDescription');
    const details = document.getElementById('confirmDetails');
    const confirmBtn = document.getElementById('confirmButton');

    const actionConfig = {
        'pending': {
            icon: '⏳',
            iconClass: 'warning',
            title: 'Marcar como Pendiente',
            description: 'Las siguientes órdenes cambiarán su estado a "Pendiente":',
            effects: ['El estado de las órdenes será actualizado', 'No se realizarán cambios en el stock'],
            btnClass: 'modal-btn-confirm',
            btnText: 'Marcar como Pendiente'
        },
        'cobrada': {
            icon: '💰',
            iconClass: 'warning',
            title: 'Marcar como Cobrada',
            description: 'Las siguientes órdenes cambiarán su estado a "Cobrada":',
            effects: ['El estado de las órdenes será actualizado', 'Se reducirá el stock si aún no se ha hecho', 'Se considerarán cobradas para reportes'],
            btnClass: 'modal-btn-confirm',
            btnText: 'Marcar como Cobradas'
        },
        'shipped': {
            icon: '🚚',
            iconClass: 'warning',
            title: 'Marcar como Enviada',
            description: 'Las siguientes órdenes cambiarán su estado a "Enviada":',
            effects: ['El estado de las órdenes será actualizado', 'Se marcarán como en tránsito'],
            btnClass: 'modal-btn-confirm',
            btnText: 'Marcar como Enviadas'
        },
        'delivered': {
            icon: '📦',
            iconClass: 'warning',
            title: 'Marcar como Entregada',
            description: 'Las siguientes órdenes cambiarán su estado a "Entregada":',
            effects: ['El estado de las órdenes será actualizado', 'Se marcarán como completadas'],
            btnClass: 'modal-btn-confirm',
            btnText: 'Marcar como Entregadas'
        },
        'cancel': {
            icon: '❌',
            iconClass: 'danger',
            title: 'Cancelar Órdenes',
            description: 'Esta acción cancelará las órdenes seleccionadas:',
            effects: ['Las órdenes serán marcadas como "Canceladas"', '⚠️ El stock de los productos será RESTAURADO', 'Esta acción no se puede deshacer fácilmente'],
            btnClass: 'modal-btn-danger',
            btnText: 'Cancelar Órdenes'
        },
        'archive': {
            icon: '📁',
            iconClass: 'warning',
            title: 'Archivar Órdenes',
            description: 'Las órdenes seleccionadas serán movidas al archivo:',
            effects: ['Las órdenes NO aparecerán en el listado principal', 'Podrán ser restauradas desde el Archivo de Ventas', 'No se realizarán cambios en el stock'],
            btnClass: 'modal-btn-confirm',
            btnText: 'Archivar Órdenes'
        }
    };

    const config = actionConfig[action];

    if (!config) {
        showToast('Acción no reconocida');
        return;
    }

    // Set icon
    icon.textContent = config.icon;
    icon.className = 'confirm-modal-icon ' + config.iconClass;

    // Set title and description
    title.textContent = config.title;
    description.textContent = config.description;

    // Set details
    details.innerHTML = `
        <strong>${count} orden(es) seleccionada(s)</strong>
        <p style="margin: 10px 0; font-size: 13px; color: #666;">Esta acción afectará a:</p>
        <ul>
            ${config.effects.map(effect => `<li>${effect}</li>`).join('')}
        </ul>
    `;

    // Configure button
    confirmBtn.className = 'modal-btn ' + config.btnClass;
    confirmBtn.textContent = config.btnText;

    // Show modal
    modal.classList.add('active');
}

/**
 * Close bulk action confirmation modal
 */
export function closeConfirmModal() {
    document.getElementById('confirmBulkModal').classList.remove('active');
}

/**
 * Execute the confirmed bulk action
 */
export function executeBulkAction() {
    // Close modal
    closeConfirmModal();

    // Submit form
    document.getElementById('bulkForm').submit();
}
