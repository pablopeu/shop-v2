/**
 * Unsaved Changes Warning
 * Shows custom modal when leaving page with unsaved changes
 */

(function() {
    let hasUnsavedChanges = false;
    let formSubmitted = false;
    let pendingNavigation = null;

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

    // Create modal HTML
    function createModal() {
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
    }

    // Show modal
    function showModal() {
        const modal = document.getElementById('unsaved-changes-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    // Hide modal
    function hideModal() {
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

    // Track changes in all form inputs
    function trackFormChanges() {
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            // Track changes on all input types
            const inputs = form.querySelectorAll('input, textarea, select');

            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    if (!formSubmitted) {
                        hasUnsavedChanges = true;
                        setSaveButtonColor(true); // Red - unsaved changes
                    }
                });

                input.addEventListener('change', () => {
                    if (!formSubmitted) {
                        hasUnsavedChanges = true;
                        setSaveButtonColor(true); // Red - unsaved changes
                    }
                });
            });

            // Reset flag when form is submitted
            form.addEventListener('submit', () => {
                formSubmitted = true;
                hasUnsavedChanges = false;
                setSaveButtonColor(false); // Green - clean state
            });
        });
    }

    // Intercept internal navigation (links)
    function interceptNavigation() {
        document.addEventListener('click', (e) => {
            // Check if click is on a link or inside a link
            const link = e.target.closest('a');

            if (link && link.href && !formSubmitted && hasUnsavedChanges) {
                // Ignore links that open in new tab or are downloads
                if (link.target === '_blank' || link.download) {
                    return;
                }

                // Check if it's an internal navigation
                const currentOrigin = window.location.origin;
                const linkOrigin = new URL(link.href).origin;

                if (currentOrigin === linkOrigin) {
                    e.preventDefault();
                    pendingNavigation = link.href;
                    showModal();
                }
            }
        });
    }

    // Setup modal buttons
    function setupModalButtons() {
        const stayBtn = document.getElementById('unsaved-stay-btn');
        const leaveBtn = document.getElementById('unsaved-leave-btn');

        if (stayBtn) {
            stayBtn.addEventListener('click', () => {
                hideModal();
                focusOnSaveButton();
                pendingNavigation = null;
            });
        }

        if (leaveBtn) {
            leaveBtn.addEventListener('click', () => {
                hideModal();
                hasUnsavedChanges = false; // Allow navigation
                if (pendingNavigation) {
                    window.location.href = pendingNavigation;
                }
            });
        }
    }

    // Warn before closing tab/window (native dialog - can't be customized)
    function warnBeforeUnload(e) {
        if (hasUnsavedChanges && !formSubmitted) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    }

    // Initialize when DOM is ready
    function init() {
        createModal();
        trackFormChanges();
        interceptNavigation();
        setupModalButtons();
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
