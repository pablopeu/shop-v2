<?php
/**
 * Reusable Modal Component - Frontend
 *
 * Este componente proporciona un modal de confirmación reutilizable
 * para reemplazar los alert/confirm/prompt nativos del navegador.
 *
 * Cargado por: app/pages/frontend/*.php
 * Bootstrap ya maneja: APP_ENTRY_POINT, includes
 *
 * Uso:
 * 1. Incluir este archivo: <?php include APP_PATH . '/includes/frontend/modal.php'; ?>
 * 2. Llamar showModal() desde JavaScript con título, mensaje y callback
 */
?>

<style nonce="<?= csp_nonce() ?>">
    /* Modal Overlay - Usa variables CSS del theme system */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: var(--z-modal-backdrop, 10000);
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(2px);
    }

    .modal-overlay.active {
        display: flex;
    }

    /* Modal Container */
    .modal-container {
        background: var(--color-bg, white);
        border-radius: var(--border-radius-xl, 12px);
        padding: var(--spacing-2xl, 30px);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        text-align: center;
        box-shadow: var(--shadow-2xl, 0 20px 40px rgba(0,0,0,0.25));
        animation: modalSlideIn var(--transition-base, 0.3s) ease;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Modal Icon */
    .modal-icon {
        font-size: var(--font-size-4xl, 64px);
        margin-bottom: var(--spacing-md, 16px);
        display: block;
    }

    .modal-icon.warning {
        color: var(--color-warning, #f57c00);
    }

    .modal-icon.danger {
        color: var(--color-error, #c62828);
    }

    .modal-icon.success {
        color: var(--color-success, #2e7d32);
    }

    .modal-icon.info {
        color: var(--color-info, #1565c0);
    }

    /* Modal Title */
    .modal-title {
        font-size: var(--font-size-2xl, 24px);
        font-weight: var(--font-weight-bold, 700);
        color: var(--color-text, #2c3e50);
        margin-bottom: var(--spacing-sm, 12px);
        font-family: var(--font-family-heading, inherit);
    }

    /* Modal Message */
    .modal-message {
        font-size: var(--font-size-base, 16px);
        color: var(--color-text-light, #666);
        margin-bottom: var(--spacing-md, 16px);
        line-height: var(--line-height-relaxed, 1.6);
    }

    /* Modal Details */
    .modal-details {
        background: var(--color-bg-light, #f8f9fa);
        padding: var(--spacing-md, 16px);
        border-radius: var(--border-radius-lg, 8px);
        margin-bottom: var(--spacing-lg, 20px);
        text-align: left;
        font-size: var(--font-size-sm, 14px);
        color: var(--color-text-light, #555);
        line-height: var(--line-height-normal, 1.5);
        border: var(--border-width, 1px) solid var(--color-border, #e0e0e0);
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
        gap: var(--spacing-sm, 12px);
        justify-content: center;
    }

    .modal-btn {
        padding: var(--spacing-sm, 14px) var(--spacing-xl, 32px);
        border-radius: var(--border-radius-lg, 8px);
        font-size: var(--font-size-base, 16px);
        font-weight: var(--font-weight-semibold, 600);
        border: none;
        cursor: pointer;
        transition: var(--transition-base, 0.3s ease);
        text-decoration: none;
        display: inline-block;
        font-family: var(--font-family, inherit);
    }

    .modal-btn-cancel {
        background: var(--color-text-lighter, #6c757d);
        color: white;
    }

    .modal-btn-cancel:hover {
        background: var(--color-text-light, #5a6268);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg, 0 8px 16px rgba(0,0,0,0.15));
    }

    .modal-btn-confirm {
        background: var(--color-success, #4CAF50);
        color: white;
    }

    .modal-btn-confirm:hover {
        background: var(--color-success, #45a049);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg, 0 8px 16px rgba(76, 175, 80, 0.4));
    }

    .modal-btn-danger {
        background: var(--color-error, #dc3545);
        color: white;
    }

    .modal-btn-danger:hover {
        background: var(--color-error, #c82333);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg, 0 8px 16px rgba(220, 53, 69, 0.4));
    }

    .modal-btn-warning {
        background: var(--color-warning, #ffc107);
        color: var(--color-text, #333);
    }

    .modal-btn-warning:hover {
        background: var(--color-warning, #e0a800);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg, 0 8px 16px rgba(255, 193, 7, 0.4));
    }

    .modal-btn-primary {
        background: var(--color-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
        color: white;
    }

    .modal-btn-primary:hover {
        background: var(--color-primary-dark, var(--color-primary, #667eea));
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg, 0 8px 16px rgba(102, 126, 234, 0.4));
    }

    /* Responsive */
    @media (max-width: 768px) {
        .modal-container {
            width: 95%;
            padding: var(--spacing-lg, 24px);
        }

        .modal-icon {
            font-size: var(--font-size-3xl, 56px);
        }

        .modal-title {
            font-size: var(--font-size-xl, 20px);
        }

        .modal-message {
            font-size: var(--font-size-sm, 15px);
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
     * @param {string} [options.confirmType] - Tipo de acción: 'danger', 'warning', 'primary', 'confirm' (default: 'primary')
     * @param {Function} options.onConfirm - Callback al confirmar
     * @param {Function} [options.onCancel] - Callback al cancelar (opcional)
     * @param {boolean} [options.showCancel] - Mostrar botón cancelar (default: true)
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
        // Use innerHTML to allow HTML formatting (like <strong>, <br>, etc.)
        message.innerHTML = options.message || '¿Estás seguro?';

        // Set details (optional)
        if (options.details) {
            details.innerHTML = options.details;
            details.style.display = 'block';
        } else {
            details.style.display = 'none';
        }

        // Set button texts
        confirmBtn.textContent = options.confirmText || 'Aceptar';
        cancelBtn.textContent = options.cancelText || 'Cancelar';

        // Show/hide cancel button
        if (options.showCancel === false) {
            cancelBtn.style.display = 'none';
        } else {
            cancelBtn.style.display = 'inline-block';
        }

        // Set confirm button type
        confirmBtn.className = 'modal-btn';
        if (options.confirmType === 'danger') {
            confirmBtn.classList.add('modal-btn-danger');
        } else if (options.confirmType === 'warning') {
            confirmBtn.classList.add('modal-btn-warning');
        } else if (options.confirmType === 'primary') {
            confirmBtn.classList.add('modal-btn-primary');
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
