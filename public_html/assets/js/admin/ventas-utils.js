/**
 * Ventas Utils - Funciones de utilidad
 * Módulo ES6 con funciones reutilizables para el panel de ventas
 */

/**
 * Muestra una notificación toast temporal
 * @param {string} message - Mensaje a mostrar
 */
export function showToast(message) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #333;
        color: white;
        padding: 15px 20px;
        border-radius: 6px;
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Copia un link de pago al portapapeles
 * @param {Event} event - Event object (for event delegation compatibility)
 * @param {HTMLElement} element - Element that triggered the action
 * @param {Object} params - Parameters object containing paymentLink
 */
export function copyPaymentLink(event, element, params) {
    // Soporte para llamadas directas con string o desde event delegation
    const link = params?.paymentLink || (typeof event === 'string' ? event : null);

    if (!link) return;

    navigator.clipboard.writeText(link).then(() => {
        showToast('✅ Link de pago copiado al portapapeles');
    }).catch(() => {
        // Fallback: mostrar el link para copiar manualmente
        const fallbackDiv = document.createElement('div');
        fallbackDiv.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10001;
            max-width: 500px;
        `;
        fallbackDiv.innerHTML = `
            <h3 style="margin: 0 0 15px 0;">📋 Copiar Link de Pago</h3>
            <p style="margin: 0 0 10px 0; color: #666;">No se pudo copiar automáticamente. Copia manualmente:</p>
            <input type="text" value="${link}" readonly
                   style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; margin-bottom: 15px;"
                   onclick="this.select()">
            <button onclick="this.parentElement.remove()"
                    style="background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; width: 100%;">
                Cerrar
            </button>
        `;
        document.body.appendChild(fallbackDiv);
    });
}

/**
 * Formatea un precio con su símbolo de moneda
 * @param {number} price - Precio a formatear
 * @param {string} currency - Código de moneda (ARS, USD)
 * @returns {string} Precio formateado
 */
export function formatPrice(price, currency) {
    const symbols = { 'ARS': '$', 'USD': 'U$D' };
    return (symbols[currency] || currency) + ' ' + price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}
