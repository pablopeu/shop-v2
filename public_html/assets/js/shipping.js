/**
 * Shipping Module - Zipnova Integration
 * Handles shipping quote requests and selection
 */

console.log('📦 shipping.js: Archivo cargado');

(function() {
    'use strict';

    console.log('📦 shipping.js: IIFE ejecutándose');

    // State
    let selectedShippingService = null;
    let shippingCost = 0;
    let shippingQuotes = [];

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔧 Shipping module: DOMContentLoaded event fired');
        initShippingHandlers();
    });

    /**
     * Initialize event handlers
     */
    function initShippingHandlers() {
        console.log('🔧 initShippingHandlers() called');
        console.log('🔧 window.BASE_PATH:', window.BASE_PATH);

        // Get quote button
        const getQuoteBtn = document.getElementById('get-shipping-quote');
        console.log('🔧 Button element:', getQuoteBtn);

        if (getQuoteBtn) {
            console.log('✅ Button found! Adding event listener...');
            getQuoteBtn.addEventListener('click', handleGetQuote);
            console.log('✅ Event listener added successfully');
        } else {
            console.error('❌ Button with id="get-shipping-quote" NOT FOUND!');
        }

        // Auto-quote disabled - user must click the button manually
        // (removed event listeners for auto-quote)

        // Listen to delivery method changes
        const deliveryMethodRadios = document.querySelectorAll('input[name="delivery_method"]');
        deliveryMethodRadios.forEach(radio => {
            radio.addEventListener('change', handleDeliveryMethodChange);
        });
    }

    /**
     * Handle delivery method change (pickup, home_delivery, pickup_point)
     */
    function handleDeliveryMethodChange(event) {
        const shippingFields = document.getElementById('shipping-fields');
        const deliveryMethod = event.target.value;

        console.log('🔄 Método de entrega cambiado a:', deliveryMethod);

        if (deliveryMethod === 'home_delivery' || deliveryMethod === 'pickup_point') {
            // Mostrar campos de dirección
            shippingFields.classList.remove('hidden');
            // Reset shipping selection
            resetShipping();

            // Si ya hay cotizaciones cargadas, volver a filtrarlas
            if (shippingQuotes.length > 0) {
                displayQuotes(shippingQuotes);
            }
        } else {
            // Ocultar campos de dirección (retiro en persona)
            shippingFields.classList.add('hidden');
            // Clear shipping cost if pickup selected
            updateShippingCost(0);
        }
    }


    /**
     * Handle get quote button click
     */
    async function handleGetQuote() {
        console.log('🚀 handleGetQuote() llamado');

        // Validate required fields
        const postalCode = document.getElementById('shipping_postal_code').value.trim();
        const city = document.getElementById('shipping_city').value.trim();
        const province = document.getElementById('shipping_province').value;
        const country = document.getElementById('shipping_country').value;

        console.log('📍 Datos de envío:', { postalCode, city, province, country });

        if (!postalCode || !city || !province) {
            showError('Por favor completá todos los campos de dirección');
            return;
        }

        // Cambiar texto del botón
        const button = document.getElementById('get-shipping-quote');
        const originalText = button.textContent;
        button.textContent = '⏳ Cotizando...';
        button.disabled = true;

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
            console.log('📦 Calculando peso y valor:', { weight, declaredValue });
            console.log('🌐 Llamando a API:', window.BASE_PATH + '/api/?endpoint=shipping&action=quotes');

            // Call API
            const response = await fetch(window.BASE_PATH + '/api/?endpoint=shipping&action=quotes', {
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

            console.log('📡 Response status:', response.status);
            const result = await response.json();
            console.log('📄 Response data:', result);

            if (result.success && result.data && result.data.quotes) {
                shippingQuotes = result.data.quotes;
                console.log('✅ Cotizaciones recibidas:', shippingQuotes.length);
                displayQuotes(shippingQuotes);

                // Cambiar texto del botón con el costo
                if (shippingQuotes.length > 0) {
                    const minCost = Math.min(...shippingQuotes.map(q => q.cost || 0));
                    button.textContent = `💰 Desde ${formatCurrency(minCost)}`;
                } else {
                    button.textContent = originalText;
                }
            } else {
                console.error('❌ Error en respuesta:', result.error);
                showError(result.error || 'No se pudieron obtener cotizaciones de envío');
                button.textContent = originalText;
            }
        } catch (error) {
            console.error('❌ Error getting shipping quotes:', error);
            showError('Error al obtener cotizaciones. Por favor intenta nuevamente.');
            button.textContent = originalText;
        } finally {
            showLoading(false);
            button.disabled = false;
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

        // Filtrar según el método de entrega elegido
        const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked')?.value || 'home_delivery';
        console.log('🔍 Método de entrega seleccionado:', deliveryMethod);

        const filteredQuotes = quotes.filter(quote => {
            if (deliveryMethod === 'pickup_point') {
                // Mostrar solo opciones de punto de entrega
                return quote.service_type_code === 'pickup_point';
            } else if (deliveryMethod === 'home_delivery') {
                // Mostrar solo opciones de entrega a domicilio
                return quote.service_type_code !== 'pickup_point';
            } else {
                // Si es 'pickup', no mostrar ninguna cotización (retiro en persona)
                return false;
            }
        });

        console.log('✅ Cotizaciones filtradas:', filteredQuotes.length, 'de', quotes.length);

        if (filteredQuotes.length === 0) {
            const errorMsg = deliveryMethod === 'pickup_point'
                ? 'No hay opciones de punto de entrega disponibles para esta dirección'
                : 'No hay opciones de envío a domicilio disponibles para esta dirección';
            showError(errorMsg);
            return;
        }

        // Usar las cotizaciones filtradas
        quotes = filteredQuotes;

        const INITIAL_DISPLAY = 4;
        let showingAll = false;

        // Function to render quotes
        function renderQuotes(showAll) {
            quotesContainer.innerHTML = '';

            const quotesToShow = showAll ? quotes : quotes.slice(0, INITIAL_DISPLAY);

            quotesToShow.forEach((quote, index) => {
                const option = createQuoteOption(quote, index);
                quotesContainer.appendChild(option);
            });

            // Add "Ver más" button if there are more than INITIAL_DISPLAY quotes
            if (quotes.length > INITIAL_DISPLAY) {
                const btnContainer = document.createElement('div');
                btnContainer.style.textAlign = 'center';
                btnContainer.style.marginTop = '0.75rem';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn-secondary';
                btn.style.padding = '0.5rem 1.5rem';
                btn.style.fontSize = '0.875rem';
                btn.textContent = showAll ? `▲ Ver menos (mostrando ${quotes.length})` : `▼ Ver más opciones (${quotes.length - INITIAL_DISPLAY} más)`;

                btn.onclick = () => {
                    showingAll = !showingAll;
                    renderQuotes(showingAll);
                };

                btnContainer.appendChild(btn);
                quotesContainer.appendChild(btnContainer);
            }
        }

        // Initial render
        renderQuotes(false);

        // Show quotes container
        quotesWrapper.classList.remove('hidden');
        console.log('✅ Contenedor de cotizaciones mostrado (hidden class removed)');

        // Scroll to quotes so user sees them
        setTimeout(() => {
            quotesWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            console.log('📍 Scroll to quotes container');
        }, 100);

        // Auto-select cheapest or first option
        let autoSelectedQuote = null;
        let autoSelectedRadio = null;

        if (quotes.length === 1) {
            // Only one option - auto-select it
            autoSelectedQuote = quotes[0];
            autoSelectedRadio = quotesContainer.querySelector('input[type="radio"]');
        } else {
            // Multiple options - try to auto-select the cheapest one
            const cheapestQuote = quotes.find(q => q.tags && q.tags.includes('cheapest'));
            if (cheapestQuote) {
                autoSelectedQuote = cheapestQuote;
                const radioIndex = quotes.indexOf(cheapestQuote);
                const allRadios = quotesContainer.querySelectorAll('input[type="radio"]');
                autoSelectedRadio = allRadios[radioIndex];
                console.log('💰 Auto-seleccionando la opción más barata:', cheapestQuote.service_name);
            }
        }

        if (autoSelectedQuote && autoSelectedRadio) {
            autoSelectedRadio.checked = true;
            handleQuoteSelection(autoSelectedQuote);
            console.log('✅ Opción auto-seleccionada:', autoSelectedQuote.service_name);
        } else {
            console.log('⚠️ No se auto-seleccionó ninguna opción - usuario debe elegir manualmente');
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

        // Datos básicos de cotización
        radio.dataset.cost = quote.cost || 0;
        radio.dataset.days = quote.estimated_days || '';
        radio.dataset.serviceId = quote.service_id || '';
        radio.dataset.serviceName = quote.service_name || '';

        // IDs de servicio
        radio.dataset.serviceTypeId = quote.service_type_id || '';
        radio.dataset.serviceTypeCode = quote.service_type_code || '';

        // Información del carrier
        radio.dataset.carrierId = quote.carrier_id || '';
        radio.dataset.carrierName = quote.carrier_name || '';
        radio.dataset.carrierLogo = quote.carrier_logo || '';
        radio.dataset.carrierRating = quote.carrier_rating || '';

        // Tipo de logística
        radio.dataset.logisticType = quote.logistic_type || '';

        // Tiempos de entrega
        radio.dataset.estimatedDelivery = quote.estimated_delivery || '';
        radio.dataset.estimationExpiresAt = quote.estimation_expires_at || '';

        // Desglose de costos
        radio.dataset.priceShipment = quote.price_shipment || 0;
        radio.dataset.priceInsurance = quote.price_insurance || 0;
        radio.dataset.priceBase = quote.price_base || 0;

        // IDs de tarifa (críticos para crear envío)
        radio.dataset.rateId = quote.rate_id || '';
        radio.dataset.tariffId = quote.tariff_id || '';
        radio.dataset.rateSource = quote.rate_source || '';

        // Tags
        radio.dataset.tags = JSON.stringify(quote.tags || []);

        radio.addEventListener('change', () => handleQuoteSelection(quote));

        const contentDiv = document.createElement('div');
        contentDiv.style.width = '100%';

        // Service name and delivery time
        const titleContainer = document.createElement('div');
        titleContainer.style.display = 'flex';
        titleContainer.style.alignItems = 'center';
        titleContainer.style.gap = '0.5rem';
        titleContainer.style.flexWrap = 'wrap';

        const title = document.createElement('strong');
        title.textContent = quote.service_name || quote.service_id || 'Envío';
        titleContainer.appendChild(title);

        // Add "MÁS BARATA" badge if this is the cheapest option
        if (quote.tags && quote.tags.includes('cheapest')) {
            const badge = document.createElement('span');
            badge.style.cssText = 'background: #27ae60; color: white; padding: 0.15rem 0.5rem; border-radius: 3px; font-size: 0.75rem; font-weight: 600;';
            badge.textContent = '💰 MÁS BARATA';
            titleContainer.appendChild(badge);
        }

        // Add "MÁS RÁPIDA" badge if this is the fastest option
        if (quote.tags && quote.tags.includes('fastest')) {
            const badge = document.createElement('span');
            badge.style.cssText = 'background: #3498db; color: white; padding: 0.15rem 0.5rem; border-radius: 3px; font-size: 0.75rem; font-weight: 600;';
            badge.textContent = '⚡ MÁS RÁPIDA';
            titleContainer.appendChild(badge);
        }

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

        contentDiv.appendChild(titleContainer);
        contentDiv.appendChild(description);
        contentDiv.appendChild(cost);

        // Si es una opción de punto de entrega, mostrar los puntos disponibles
        console.log('📍 Verificando pickup points para quote:', quote.service_name);
        console.log('   - service_type_code:', quote.service_type_code);
        console.log('   - pickup_points:', quote.pickup_points);
        console.log('   - length:', quote.pickup_points ? quote.pickup_points.length : 'N/A');

        if (quote.service_type_code === 'pickup_point' && quote.pickup_points && quote.pickup_points.length > 0) {
            console.log('✅ Creando contenedor de pickup points con', quote.pickup_points.length, 'puntos');
            const pickupPointsContainer = document.createElement('div');
            pickupPointsContainer.className = 'pickup-points-container hidden';
            pickupPointsContainer.style.cssText = 'margin-top: 1rem; padding: 0.75rem; background: var(--checkout-bg-secondary); border-radius: 8px;';
            pickupPointsContainer.dataset.serviceId = quote.service_id;

            const pickupPointsTitle = document.createElement('p');
            pickupPointsTitle.style.cssText = 'font-weight: 600; margin-bottom: 0.5rem; font-size: 0.875rem;';
            pickupPointsTitle.textContent = '📍 Seleccioná un punto de retiro:';
            pickupPointsContainer.appendChild(pickupPointsTitle);

            const pickupPointsSelect = document.createElement('select');
            pickupPointsSelect.className = 'pickup-point-select';
            pickupPointsSelect.style.cssText = 'width: 100%; padding: 0.5rem; border: 1px solid var(--checkout-border); border-radius: 4px; font-size: 0.875rem;';
            pickupPointsSelect.required = true;

            // Opción por defecto
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Seleccionar punto de retiro...';
            pickupPointsSelect.appendChild(defaultOption);

            // Agregar cada punto de entrega
            quote.pickup_points.forEach((point, idx) => {
                const option = document.createElement('option');
                option.value = point.point_id;
                option.dataset.pointData = JSON.stringify(point);

                const distance = point.location?.geolocation?.distance;
                const distanceText = distance ? ` (${distance}m)` : '';
                option.textContent = `${point.description} - ${point.location.street} ${point.location.street_number}, ${point.location.city}${distanceText}`;

                pickupPointsSelect.appendChild(option);
            });

            pickupPointsContainer.appendChild(pickupPointsSelect);
            contentDiv.appendChild(pickupPointsContainer);

            // Mostrar/ocultar puntos de entrega cuando se selecciona/deselecciona el radio
            radio.addEventListener('change', function() {
                // Ocultar todos los contenedores de puntos de entrega
                document.querySelectorAll('.pickup-points-container').forEach(container => {
                    container.classList.add('hidden');
                });

                // Mostrar el contenedor correspondiente si este radio está seleccionado
                if (this.checked) {
                    pickupPointsContainer.classList.remove('hidden');
                }
            });

            // Guardar el punto seleccionado cuando cambia el select
            pickupPointsSelect.addEventListener('change', function() {
                const selectedPointId = this.value;
                console.log('📍 Punto de entrega seleccionado:', selectedPointId);

                // Guardar en campo hidden
                setHiddenField('shipping_pickup_point_id', selectedPointId);
            });
        }

        label.appendChild(radio);
        label.appendChild(contentDiv);

        return label;
    }

    /**
     * Handle quote selection
     */
    function handleQuoteSelection(quote) {
        console.log('🚢 handleQuoteSelection called with quote:', quote);
        selectedShippingService = quote.service_id;
        shippingCost = parseFloat(quote.cost) || 0;

        // Update hidden fields - campos básicos
        setHiddenField('shipping_service_id', selectedShippingService);
        setHiddenField('shipping_cost', shippingCost);
        setHiddenField('shipping_estimated_days', quote.estimated_days || '');
        setHiddenField('shipping_service_name', quote.service_name || '');

        console.log('✅ Campos básicos llenados - service_id:', selectedShippingService, 'cost:', shippingCost);

        // IDs de servicio
        setHiddenField('shipping_service_type_id', quote.service_type_id || '');
        setHiddenField('shipping_service_type_code', quote.service_type_code || '');

        // Información del carrier
        setHiddenField('shipping_carrier_id', quote.carrier_id || '');
        setHiddenField('shipping_carrier_name', quote.carrier_name || '');
        setHiddenField('shipping_carrier_logo', quote.carrier_logo || '');
        setHiddenField('shipping_carrier_rating', quote.carrier_rating || '');

        // Tipo de logística
        setHiddenField('shipping_logistic_type', quote.logistic_type || '');

        // Tiempos de entrega
        setHiddenField('shipping_estimated_delivery', quote.estimated_delivery || '');
        setHiddenField('shipping_estimation_expires_at', quote.estimation_expires_at || '');

        // Desglose de costos
        setHiddenField('shipping_price_shipment', quote.price_shipment || 0);
        setHiddenField('shipping_price_insurance', quote.price_insurance || 0);
        setHiddenField('shipping_price_base', quote.price_base || 0);

        // IDs de tarifa (críticos para crear envío)
        setHiddenField('shipping_rate_id', quote.rate_id || '');
        setHiddenField('shipping_tariff_id', quote.tariff_id || '');
        setHiddenField('shipping_rate_source', quote.rate_source || '');

        // Tags
        setHiddenField('shipping_tags', JSON.stringify(quote.tags || []));

        // Limpiar pickup_point_id si no es una opción de punto de entrega
        if (quote.service_type_code !== 'pickup_point') {
            setHiddenField('shipping_pickup_point_id', '');
        }

        // Update total
        updateShippingCost(shippingCost);

        // Dispatch custom event for checkout to update
        document.dispatchEvent(new CustomEvent('shippingSelected', {
            detail: {
                serviceId: selectedShippingService,
                cost: shippingCost,
                estimatedDays: quote.estimated_days,
                quote: quote
            }
        }));
    }

    /**
     * Helper para setear campos ocultos de forma segura
     */
    function setHiddenField(id, value) {
        const field = document.getElementById(id);
        if (field) {
            field.value = value;
        }
    }

    /**
     * Update shipping cost in the order summary
     */
    function updateShippingCost(cost) {
        shippingCost = cost;

        // Try to find shipping cost display element
        const shippingCostEl = document.getElementById('shipping-cost');
        const shippingCostRow = document.getElementById('shipping-cost-row');

        if (shippingCostEl) {
            shippingCostEl.textContent = formatCurrency(cost);
            shippingCostEl.dataset.value = cost;
        }

        // Show/hide shipping row based on cost
        if (shippingCostRow) {
            if (cost > 0) {
                shippingCostRow.style.display = 'flex';
            } else {
                shippingCostRow.style.display = 'none';
            }
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
