<?php
/**
 * Auto-Update Exchange Rate Script
 * This script checks if the exchange rate needs updating (30+ minutes since last update)
 * and triggers an automatic update via JavaScript when a user loads the page
 *
 * Include this file at the end of <body> on public pages
 * Example: <?php include __DIR__ . '/includes/auto-update-exchange.php'; ?>
 */

// Only run if currency API is enabled
$currency_config = read_json(__DIR__ . '/../config/currency.json');

if ($currency_config['api_enabled'] ?? false):
?>
<script nonce="<?= csp_nonce() ?>">
(function() {
    'use strict';

    // Auto-update exchange rate if needed
    function autoUpdateExchangeRate() {
        fetch('<?= url('/api/?endpoint=update-exchange-rate') ?>', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.updated) {
                console.log('[Exchange Rate] Cotización actualizada:', data.data);
            } else if (data.success && !data.needs_update) {
                console.log('[Exchange Rate] Cotización actualizada recientemente, no se requiere actualización');
            } else {
                console.warn('[Exchange Rate] No se pudo actualizar la cotización:', data.message);
            }
        })
        .catch(error => {
            console.error('[Exchange Rate] Error al actualizar cotización:', error);
        });
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoUpdateExchangeRate);
    } else {
        autoUpdateExchangeRate();
    }
})();
</script>
<?php endif; ?>
