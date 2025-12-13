<?php
/**
 * Reusable Modal Component
 *
 * Este componente proporciona un modal de confirmación reutilizable
 * para reemplazar los msgbox/alerts nativos del navegador.
 *
 * Cargado por: app/pages/admin/*.php
 * Bootstrap ya maneja: APP_ENTRY_POINT, includes, session
 *
 * Uso:
 * 1. Incluir este archivo: <?php include APP_PATH . '/includes/admin/modal.php'; ?>
 * 2. Llamar showModal() desde JavaScript con título, mensaje y callback
 */
?>

<style nonce="<?= csp_nonce() ?>">
    /* Modal Overlay */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    /* Modal Container */
    .modal-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        text-align: center;
    }

    /* Modal Icon */
    .modal-icon {
        font-size: 48px;
        margin-bottom: 12px;
        display: block;
    }

    .modal-icon.warning {
        color: #ffc107;
    }

    .modal-icon.danger {
        color: #dc3545;
    }

    .modal-icon.success {
        color: #4CAF50;
    }

    .modal-icon.info {
        color: #2196F3;
    }

    /* Modal Title */
    .modal-title {
        font-size: 20px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 10px;
    }

    /* Modal Message */
    .modal-message {
        font-size: 14px;
        color: #666;
        margin-bottom: 12px;
        line-height: 1.5;
    }

    /* Modal Details */
    .modal-details {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
        text-align: left;
        font-size: 14px;
        color: #555;
        line-height: 1.5;
    }

    .modal-details ul {
        margin: 10px 0;
        padding-left: 20px;
    }

    .modal-details li {
        margin: 5px 0;
    }

    /* Modal Actions */
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .modal-btn {
        padding: 12px 30px;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .modal-btn-cancel {
        background: #6c757d;
        color: white;
    }

    .modal-btn-cancel:hover {
        background: #5a6268;
    }

    .modal-btn-confirm {
        background: #4CAF50;
        color: white;
    }

    .modal-btn-confirm:hover {
        background: #45a049;
    }

    .modal-btn-danger {
        background: #dc3545;
        color: white;
    }

    .modal-btn-danger:hover {
        background: #c82333;
    }

    .modal-btn-warning {
        background: #ffc107;
        color: #333;
    }

    .modal-btn-warning:hover {
        background: #e0a800;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .modal-container {
            width: 95%;
            padding: 20px;
        }

        .modal-icon {
            font-size: 48px;
        }

        .modal-title {
            font-size: 20px;
        }

        .modal-actions {
            flex-direction: column-reverse;
        }

        .modal-btn {
            width: 100%;
        }
    }
</style>

<!-- Modal HTML -->
<div class="modal-overlay" id="confirmModal" data-action="handleModalOverlayClick">
    <div class="modal-container">
        <div class="modal-icon" id="modalIcon">⚠️</div>
        <h2 class="modal-title" id="modalTitle">Confirmar Acción</h2>
        <p class="modal-message" id="modalMessage">¿Estás seguro de que deseas realizar esta acción?</p>
        <div class="modal-details" id="modalDetails" style="display: none;"></div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" data-action="closeModal" type="button" id="modalCancelBtn">
                Cancelar
            </button>
            <button class="modal-btn" id="modalConfirmBtn" type="button">
                Confirmar
            </button>
        </div>
    </div>
</div>

<script nonce="<?= csp_nonce() ?>">
    /**
     * Configuración global del modal
     */
    let modalCallback = null;
    let modalCancelCallback = null;

    /**
     * Muestra el modal de confirmación
     *
     * @param {Object} options - Opciones del modal
     * @param {string} options.title - Título del modal
     * @param {string} options.message - Mensaje principal
     * @param {string} [options.details] - Detalles adicionales (opcional)
     * @param {string} [options.icon] - Icono del modal (default: ⚠️)
     * @param {string} [options.iconClass] - Clase CSS para el icono: 'warning', 'danger', 'success', 'info'
     * @param {string} [options.confirmText] - Texto del botón confirmar (default: "Confirmar")
     * @param {string} [options.cancelText] - Texto del botón cancelar (default: "Cancelar")
     * @param {string} [options.confirmType] - Tipo de acción: 'danger', 'warning', 'primary' (default: 'primary')
     * @param {Function} options.onConfirm - Callback al confirmar
     * @param {Function} [options.onCancel] - Callback al cancelar (opcional)
     */
    function showModal(options) {
        const modal = document.getElementById('confirmModal');
        const icon = document.getElementById('modalIcon');
        const title = document.getElementById('modalTitle');
        const message = document.getElementById('modalMessage');
        const details = document.getElementById('modalDetails');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        const cancelBtn = document.getElementById('modalCancelBtn');

        // Set icon
        icon.textContent = options.icon || '⚠️';
        icon.className = 'modal-icon';
        if (options.iconClass) {
            icon.classList.add(options.iconClass);
        }

        // Set content
        title.textContent = options.title || 'Confirmar Acción';
        message.textContent = options.message || '¿Estás seguro?';

        // Set details (optional)
        if (options.details) {
            details.innerHTML = options.details;
            details.style.display = 'block';
        } else {
            details.style.display = 'none';
        }

        // Set button texts
        confirmBtn.textContent = options.confirmText || 'Confirmar';
        cancelBtn.textContent = options.cancelText || 'Cancelar';

        // Set confirm button type
        confirmBtn.className = 'modal-btn';
        if (options.confirmType === 'danger') {
            confirmBtn.classList.add('modal-btn-danger');
        } else if (options.confirmType === 'warning') {
            confirmBtn.classList.add('modal-btn-warning');
        } else {
            confirmBtn.classList.add('modal-btn-confirm');
        }

        // Store callbacks
        modalCallback = options.onConfirm;
        modalCancelCallback = options.onCancel || null;

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        // Focus on confirm button
        setTimeout(() => confirmBtn.focus(), 100);
    }

    /**
     * Cierra el modal
     * Compatible con event delegation: (event, element, params)
     */
    function closeModal(event, element, params) {
        const modal = document.getElementById('confirmModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';

        // Execute cancel callback if exists
        if (modalCancelCallback) {
            modalCancelCallback();
        }

        // Clear callbacks
        modalCallback = null;
        modalCancelCallback = null;
    }

    /**
     * Maneja el click en el overlay (cerrar al hacer click fuera)
     * Compatible con event delegation: (event, element, params)
     */
    function handleModalOverlayClick(event, element, params) {
        // Solo cerrar si el click fue directamente en el overlay, no en el contenido
        if (event.target.id === 'confirmModal') {
            closeModal();
        }
    }

    /**
     * Ejecuta la acción confirmada
     */
    document.getElementById('modalConfirmBtn')?.addEventListener('click', function() {
        if (modalCallback) {
            modalCallback();
        }
        closeModal();
    });

    /**
     * Cerrar modal con tecla ESC
     */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('confirmModal');
            if (modal.classList.contains('active')) {
                closeModal();
            }
        }
    });

    /**
     * Helper: Confirmar y redirigir a URL
     * Útil para reemplazar confirm() en enlaces
     */
    function confirmAndRedirect(url, options) {
        showModal({
            ...options,
            onConfirm: function() {
                window.location.href = url;
            }
        });
    }

    /**
     * Helper: Confirmar y enviar formulario
     * Útil para reemplazar confirm() en forms
     */
    function confirmAndSubmit(formId, options) {
        showModal({
            ...options,
            onConfirm: function() {
                document.getElementById(formId).submit();
            }
        });
    }
</script>
