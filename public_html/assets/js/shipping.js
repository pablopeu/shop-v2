/**
 * Shipping Module - Zipnova Integration
 * Handles shipping quote requests and selection
 */

(function() {
    'use strict';

    // State
    let selectedShippingService = null;
    let shippingCost = 0;
    let shippingQuotes = [];

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initShippingHandlers();
    });

    /**
     * Initialize event handlers
     */
    function initShippingHandlers() {
        // Get quote button
        const getQuoteBtn = document.getElementById('get-shipping-quote');
        if (getQuoteBtn) {
            getQuoteBtn.addEventListener('click', handleGetQuote);
        }

        // Auto-quote when postal code changes and has enough data
        const postalCodeInput = document.getElementById('shipping_postal_code');
        const cityInput = document.getElementById('shipping_city');
        const provinceSelect = document.getElementById('shipping_province');

        if (postalCodeInput && cityInput && provinceSelect) {
            // Add event listeners for auto-quote
            postalCodeInput.addEventListener('blur', maybeAutoQuote);
            cityInput.addEventListener('blur', maybeAutoQuote);
            provinceSelect.addEventListener('change', maybeAutoQuote);
        }

        // Listen to delivery method changes
        const deliveryMethodRadios = document.querySelectorAll('input[name="delivery_method"]');
        deliveryMethodRadios.forEach(radio => {
            radio.addEventListener('change', handleDeliveryMethodChange);
        });
    }

    /**
     * Handle delivery method change (pickup vs shipping)
     */
    function handleDeliveryMethodChange(event) {
        const shippingFields = document.getElementById('shipping-fields');

        if (event.target.value === 'shipping') {
            shippingFields.classList.remove('hidden');
            // Reset shipping selection
            resetShipping();
        } else {
            shippingFields.classList.add('hidden');
            // Clear shipping cost if pickup selected
            updateShippingCost(0);
        }
    }

    /**
     * Maybe auto-quote if all required fields are filled
     */
    function maybeAutoQuote() {
        const postalCode = document.getElementById('shipping_postal_code').value.trim();
        const city = document.getElementById('shipping_city').value.trim();
        const province = document.getElementById('shipping_province').value;

        if (postalCode && city && province) {
            // Auto quote after a short delay
            setTimeout(handleGetQuote, 500);
        }
    }

    /**
     * Handle get quote button click
     */
    async function handleGetQuote() {
        // Validate required fields
        const postalCode = document.getElementById('shipping_postal_code').value.trim();
        const city = document.getElementById('shipping_city').value.trim();
        const province = document.getElementById('shipping_province').value;
        const country = document.getElementById('shipping_country').value;

        if (!postalCode || !city || !province) {
            showError('Por favor completá todos los campos de dirección');
            return;
        }

        // Show loading
        showLoading(true);
        hideError();
        hideQuotes();

        // Calculate total weight from cart
        const weight = calculateCartWeight();
        const declaredValue = calculateCartValue();

        // Build request
        const destination = {
            postal_code: postalCode,
            city: city,
            province: province,
            country: country
        };

        try {
            // Call API
            const response = await fetch(window.BASE_PATH + '/api/shipping?action=quotes', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    destination: destination,
                    weight: weight,
                    declared_value: declaredValue
                })
            });

            const result = await response.json();

            if (result.success && result.data && result.data.quotes) {
                shippingQuotes = result.data.quotes;
                displayQuotes(shippingQuotes);
            } else {
                showError(result.error || 'No se pudieron obtener cotizaciones de envío');
            }
        } catch (error) {
            console.error('Error getting shipping quotes:', error);
            showError('Error al obtener cotizaciones. Por favor intenta nuevamente.');
        } finally {
            showLoading(false);
        }
    }

    /**
     * Display shipping quotes
     */
    function displayQuotes(quotes) {
        const quotesContainer = document.getElementById('shipping-quotes');
        const quotesWrapper = document.getElementById('shipping-quotes-container');

        if (!quotesContainer || !quotesWrapper) return;

        // Clear previous quotes
        quotesContainer.innerHTML = '';

        if (!quotes || quotes.length === 0) {
            showError('No hay métodos de envío disponibles para esta dirección');
            return;
        }

        // Create radio options for each quote
        quotes.forEach((quote, index) => {
            const option = createQuoteOption(quote, index);
            quotesContainer.appendChild(option);
        });

        // Show quotes container
        quotesWrapper.classList.remove('hidden');

        // Auto-select first option if only one available
        if (quotes.length === 1) {
            const firstRadio = quotesContainer.querySelector('input[type="radio"]');
            if (firstRadio) {
                firstRadio.checked = true;
                handleQuoteSelection(quotes[0]);
            }
        }
    }

    /**
     * Create quote option element
     */
    function createQuoteOption(quote, index) {
        const label = document.createElement('label');
        label.className = 'radio-option';

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'shipping_method_quote';
        radio.value = quote.service_id || `service_${index}`;
        radio.dataset.cost = quote.cost || 0;
        radio.dataset.days = quote.estimated_days || '';
        radio.dataset.serviceId = quote.service_id || '';

        radio.addEventListener('change', () => handleQuoteSelection(quote));

        const contentDiv = document.createElement('div');
        contentDiv.style.width = '100%';

        // Service name and delivery time
        const title = document.createElement('strong');
        title.textContent = quote.service_name || quote.service_id || 'Envío';

        const description = document.createElement('p');
        description.className = 'option-description';

        let descText = '';
        if (quote.estimated_days) {
            descText += `Entrega estimada: ${quote.estimated_days} días`;
        }
        if (quote.description) {
            descText += (descText ? ' • ' : '') + quote.description;
        }
        description.textContent = descText || 'Envío a domicilio';

        // Cost
        const cost = document.createElement('div');
        cost.style.marginTop = '0.5rem';
        cost.style.fontWeight = '600';
        cost.style.fontSize = '1.1em';
        cost.textContent = formatCurrency(quote.cost || 0);

        contentDiv.appendChild(title);
        contentDiv.appendChild(description);
        contentDiv.appendChild(cost);

        label.appendChild(radio);
        label.appendChild(contentDiv);

        return label;
    }

    /**
     * Handle quote selection
     */
    function handleQuoteSelection(quote) {
        selectedShippingService = quote.service_id;
        shippingCost = parseFloat(quote.cost) || 0;

        // Update hidden fields
        document.getElementById('shipping_service_id').value = selectedShippingService;
        document.getElementById('shipping_cost').value = shippingCost;
        document.getElementById('shipping_estimated_days').value = quote.estimated_days || '';

        // Update total
        updateShippingCost(shippingCost);

        // Dispatch custom event for checkout to update
        document.dispatchEvent(new CustomEvent('shippingSelected', {
            detail: {
                serviceId: selectedShippingService,
                cost: shippingCost,
                estimatedDays: quote.estimated_days
            }
        }));
    }

    /**
     * Update shipping cost in the order summary
     */
    function updateShippingCost(cost) {
        shippingCost = cost;

        // Try to find shipping cost display element
        const shippingCostEl = document.getElementById('shipping-cost');
        if (shippingCostEl) {
            shippingCostEl.textContent = formatCurrency(cost);
        }

        // Trigger total recalculation
        recalculateTotal();
    }

    /**
     * Recalculate order total including shipping
     */
    function recalculateTotal() {
        // Get subtotal element
        const subtotalEl = document.getElementById('order-subtotal');
        if (!subtotalEl) return;

        const subtotal = parseFloat(subtotalEl.dataset.value || subtotalEl.textContent.replace(/[^0-9.]/g, '')) || 0;
        const total = subtotal + shippingCost;

        // Update total display
        const totalEl = document.getElementById('order-total');
        if (totalEl) {
            totalEl.textContent = formatCurrency(total);
            totalEl.dataset.value = total;
        }
    }

    /**
     * Calculate total cart weight from cart data
     */
    function calculateCartWeight() {
        // Try to get weight from cart data attribute or element
        const cartDataEl = document.getElementById('cart-data');
        if (cartDataEl && cartDataEl.dataset.totalWeight) {
            return parseFloat(cartDataEl.dataset.totalWeight) || 1;
        }

        // Fallback: calculate from visible items if available
        const itemWeights = document.querySelectorAll('[data-item-weight]');
        let totalWeight = 0;

        itemWeights.forEach(item => {
            const weight = parseFloat(item.dataset.itemWeight) || 0;
            const quantity = parseInt(item.dataset.itemQuantity) || 1;
            totalWeight += weight * quantity;
        });

        // Return calculated weight or default 1kg
        return totalWeight > 0 ? totalWeight : 1;
    }

    /**
     * Calculate total cart value
     */
    function calculateCartValue() {
        const subtotalEl = document.getElementById('order-subtotal');
        if (subtotalEl) {
            const value = parseFloat(subtotalEl.dataset.value || subtotalEl.textContent.replace(/[^0-9.]/g, ''));
            return value || 0;
        }

        // Try alternative methods
        const totalEl = document.querySelector('[data-cart-total]');
        if (totalEl) {
            return parseFloat(totalEl.dataset.cartTotal) || 0;
        }

        return 0;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Show loading indicator
     */
    function showLoading(show) {
        const loadingEl = document.getElementById('shipping-loading');
        if (loadingEl) {
            if (show) {
                loadingEl.classList.remove('hidden');
            } else {
                loadingEl.classList.add('hidden');
            }
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        const errorEl = document.getElementById('shipping-error');
        const errorMessageEl = document.getElementById('shipping-error-message');

        if (errorEl && errorMessageEl) {
            errorMessageEl.textContent = message;
            errorEl.classList.remove('hidden');
        }
    }

    /**
     * Hide error message
     */
    function hideError() {
        const errorEl = document.getElementById('shipping-error');
        if (errorEl) {
            errorEl.classList.add('hidden');
        }
    }

    /**
     * Hide quotes
     */
    function hideQuotes() {
        const quotesWrapper = document.getElementById('shipping-quotes-container');
        if (quotesWrapper) {
            quotesWrapper.classList.add('hidden');
        }
    }

    /**
     * Reset shipping selection
     */
    function resetShipping() {
        selectedShippingService = null;
        shippingCost = 0;
        shippingQuotes = [];

        document.getElementById('shipping_service_id').value = '';
        document.getElementById('shipping_cost').value = '0';
        document.getElementById('shipping_estimated_days').value = '';

        hideQuotes();
        hideError();
        updateShippingCost(0);
    }

    // Expose functions globally if needed
    window.ShippingModule = {
        getQuote: handleGetQuote,
        resetShipping: resetShipping,
        updateShippingCost: updateShippingCost
    };

})();
