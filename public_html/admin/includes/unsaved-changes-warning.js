/**
 * Unsaved Changes Warning
 * Shows custom modal when leaving page with unsaved changes
 */

(function() {
    // Guard para evitar inicialización múltiple
    if (window.__unsavedChangesWarningInitialized) {
        return;
    }
    window.__unsavedChangesWarningInitialized = true;

    let hasUnsavedChanges = false;
    let formSubmitted = false;
    let pendingNavigation = null;
    let initialValues = {}; // Para comparar valores originales
    let userHasInteracted = false; // Solo activar después de primera interacción

    // Find save button
    function findSaveButton() {
        return document.querySelector('button[name="save_email"], button[name="save_telegram"], button[name="save_payment"], button[name="save_credentials"], button[name="save_path"], button[type="submit"]');
    }

    // Set save button color based on state
    function setSaveButtonColor(hasChanges) {
        const saveButton = findSaveButton();
        if (saveButton) {
            if (hasChanges) {
                // Red - unsaved changes
                saveButton.style.background = '#e74c3c';
                saveButton.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
            } else {
                // Green - clean state
                saveButton.style.background = '#27ae60';
                saveButton.style.boxShadow = '0 2px 8px rgba(39, 174, 96, 0.3)';
            }
        }
    }

    // Verificar si el modal reutilizable está disponible
    function hasReusableModal() {
        return typeof window.showModal === 'function';
    }

    // Mostrar modal de cambios sin guardar
    function showUnsavedChangesModal() {
        if (hasReusableModal()) {
            // Usar el modal reutilizable del sistema
            window.showModal({
                title: 'Cambios sin guardar',
                message: 'Hay cambios que no han sido guardados.',
                details: 'Si sales ahora, se perderán todos los cambios realizados. ¿Deseas salir sin guardar?',
                icon: '⚠️',
                iconClass: 'warning',
                confirmText: 'Salir sin guardar',
                cancelText: 'Quedarme y guardar',
                confirmType: 'danger',
                onConfirm: function() {
                    hasUnsavedChanges = false; // Desactivar para permitir navegación
                    if (pendingNavigation) {
                        window.location.href = pendingNavigation;
                    }
                },
                onCancel: function() {
                    focusOnSaveButton();
                    pendingNavigation = null;
                }
            });
        } else {
            // Fallback: crear modal propio solo si no existe el reutilizable
            createFallbackModal();
            const modal = document.getElementById('unsaved-changes-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }
    }

    // Crear modal de fallback (solo si no hay modal reutilizable)
    function createFallbackModal() {
        // No crear si ya existe
        if (document.getElementById('unsaved-changes-modal')) {
            return;
        }

        const modalHTML = `
            <div id="unsaved-changes-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center;">
                <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); animation: slideDown 0.3s ease;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>
                        <h2 style="font-size: 22px; color: #2c3e50; margin-bottom: 10px;">Cambios sin guardar</h2>
                        <p style="font-size: 15px; color: #666; line-height: 1.5;">
                            Hay cambios que no han sido guardados. Si sales ahora, se perderán todos los cambios realizados.
                        </p>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="unsaved-stay-btn" style="
                            padding: 12px 24px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border: none;
                            border-radius: 6px;
                            font-size: 14px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: transform 0.2s;
                        ">
                            💾 Quedarme y guardar
                        </button>
                        <button id="unsaved-leave-btn" style="
                            padding: 12px 24px;
                            background: #e74c3c;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            font-size: 14px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: transform 0.2s;
                        ">
                            Salir sin guardar
                        </button>
                    </div>
                </div>
            </div>
            <style>
                @keyframes slideDown {
                    from {
                        transform: translateY(-50px);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                #unsaved-stay-btn:hover, #unsaved-leave-btn:hover {
                    transform: translateY(-2px);
                }
            </style>
        `;

        const div = document.createElement('div');
        div.innerHTML = modalHTML;
        document.body.appendChild(div.firstElementChild);
        document.body.appendChild(div.lastElementChild); // Style tag

        // Setup buttons
        setupFallbackModalButtons();
    }

    // Configurar botones del modal fallback
    function setupFallbackModalButtons() {
        const stayBtn = document.getElementById('unsaved-stay-btn');
        const leaveBtn = document.getElementById('unsaved-leave-btn');

        if (stayBtn) {
            stayBtn.addEventListener('click', () => {
                hideFallbackModal();
                focusOnSaveButton();
                pendingNavigation = null;
            });
        }

        if (leaveBtn) {
            leaveBtn.addEventListener('click', () => {
                hideFallbackModal();
                hasUnsavedChanges = false;
                if (pendingNavigation) {
                    window.location.href = pendingNavigation;
                }
            });
        }
    }

    // Ocultar modal fallback
    function hideFallbackModal() {
        const modal = document.getElementById('unsaved-changes-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Find save button and focus on it
    function focusOnSaveButton() {
        const saveButton = findSaveButton();

        if (saveButton) {
            // Scroll to button with smooth animation
            saveButton.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Wait for scroll to finish, then focus and add highlight
            setTimeout(() => {
                saveButton.focus();

                // Add temporary highlight animation
                const originalTransform = saveButton.style.transform;
                const originalBoxShadow = saveButton.style.boxShadow;

                saveButton.style.transform = 'scale(1.05)';
                saveButton.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.4)';

                setTimeout(() => {
                    saveButton.style.transform = originalTransform;
                    saveButton.style.boxShadow = originalBoxShadow;
                }, 1000);
            }, 500);
        }
    }

    // Obtener valor de un input
    function getInputValue(input) {
        if (input.type === 'checkbox') {
            return input.checked;
        } else if (input.type === 'radio') {
            return input.checked ? input.value : null;
        }
        return input.value;
    }

    // Generar clave única para un input
    function getInputKey(input) {
        return input.name || input.id || input.getAttribute('data-field');
    }

    // Capturar valores iniciales de todos los inputs
    function captureInitialValues() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                const key = getInputKey(input);
                if (key) {
                    initialValues[key] = getInputValue(input);
                }
            });
        });
    }

    // Verificar si hay cambios reales comparando con valores iniciales
    function checkForRealChanges() {
        const forms = document.querySelectorAll('form');
        let hasChanges = false;

        forms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                const key = getInputKey(input);
                if (key && initialValues.hasOwnProperty(key)) {
                    const currentValue = getInputValue(input);
                    if (currentValue !== initialValues[key]) {
                        hasChanges = true;
                    }
                }
            });
        });

        return hasChanges;
    }

    // Set para trackear inputs ya monitoreados (evitar duplicados)
    const monitoredInputs = new WeakSet();

    // Agregar listeners a un input individual
    function attachInputListeners(input) {
        // Evitar duplicados
        if (monitoredInputs.has(input)) {
            return;
        }
        monitoredInputs.add(input);

        // Capturar valores iniciales en el primer focus/click (lazy initialization)
        const captureOnFirstInteraction = () => {
            if (!userHasInteracted) {
                userHasInteracted = true;
                captureInitialValues();
                console.log('[Unsaved Changes] Valores iniciales capturados después de primera interacción');
            }
        };

        input.addEventListener('focus', captureOnFirstInteraction, { once: true });
        input.addEventListener('click', captureOnFirstInteraction, { once: true });

        input.addEventListener('input', () => {
            if (!formSubmitted && userHasInteracted) {
                // Verificar si hay cambios reales
                hasUnsavedChanges = checkForRealChanges();
                setSaveButtonColor(hasUnsavedChanges);
            }
        });

        input.addEventListener('change', () => {
            if (!formSubmitted && userHasInteracted) {
                // Verificar si hay cambios reales
                hasUnsavedChanges = checkForRealChanges();
                setSaveButtonColor(hasUnsavedChanges);
            }
        });
    }

    // Track changes in all form inputs
    function trackFormChanges() {
        const forms = document.querySelectorAll('form');

        // Capturar valores iniciales después de que el DOM esté listo
        setTimeout(captureInitialValues, 100);

        forms.forEach(form => {
            // Track changes on all input types
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => attachInputListeners(input));

            // Reset flag when form is submitted
            form.addEventListener('submit', () => {
                formSubmitted = true;
                hasUnsavedChanges = false;
                setSaveButtonColor(false); // Green - clean state
            });
        });
    }

    // Observar cambios en el DOM para detectar nuevos inputs dinámicos
    function observeDynamicInputs() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    // Si el nodo agregado es un input/textarea/select
                    if (node.nodeType === 1) {
                        if (node.matches && node.matches('input, textarea, select')) {
                            attachInputListeners(node);
                            // Capturar valor inicial del nuevo input
                            const key = getInputKey(node);
                            if (key) {
                                initialValues[key] = getInputValue(node);
                            }
                        }
                        // Si el nodo contiene inputs/textareas/selects
                        const newInputs = node.querySelectorAll && node.querySelectorAll('input, textarea, select');
                        if (newInputs) {
                            newInputs.forEach(input => {
                                attachInputListeners(input);
                                // Capturar valor inicial del nuevo input
                                const key = getInputKey(input);
                                if (key) {
                                    initialValues[key] = getInputValue(input);
                                }
                            });
                        }
                    }
                });

                // Cuando se eliminan nodos, marcar como cambios
                if (mutation.removedNodes.length > 0 && userHasInteracted) {
                    setTimeout(() => {
                        hasUnsavedChanges = checkForRealChanges();
                        setSaveButtonColor(hasUnsavedChanges);
                    }, 50);
                }
            });
        });

        // Observar todo el documento
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Intercept internal navigation (links)
    function interceptNavigation() {
        document.addEventListener('click', (e) => {
            // Check if click is on a link or inside a link
            const link = e.target.closest('a');

            // Solo interceptar si el usuario ha interactuado y hay cambios
            if (link && link.href && !formSubmitted && hasUnsavedChanges && userHasInteracted) {
                // Ignore links that open in new tab or are downloads
                if (link.target === '_blank' || link.download) {
                    return;
                }

                // Check if it's an internal navigation
                const currentOrigin = window.location.origin;
                try {
                    const linkOrigin = new URL(link.href).origin;

                    if (currentOrigin === linkOrigin) {
                        e.preventDefault();
                        pendingNavigation = link.href;
                        showUnsavedChangesModal();
                    }
                } catch (err) {
                    // URL inválida, permitir comportamiento default
                }
            }
        });
    }

    // Warn before closing tab/window (native dialog - can't be customized)
    function warnBeforeUnload(e) {
        // Solo advertir si el usuario ha interactuado y hay cambios sin guardar
        if (hasUnsavedChanges && !formSubmitted && userHasInteracted) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    }

    // Initialize when DOM is ready
    function init() {
        trackFormChanges();
        observeDynamicInputs(); // Observar inputs dinámicos
        interceptNavigation();
        window.addEventListener('beforeunload', warnBeforeUnload);

        // Set initial button color to green (clean state)
        setSaveButtonColor(false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
