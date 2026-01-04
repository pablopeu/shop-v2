/**
 * Places Autocomplete usando Places API (New) con REST
 * NO usa APIs legacy - 100% Places API (New)
 */

(function() {
    'use strict';

    let sessionToken = null;
    let inlineMap = null;
    let inlineMarker = null;

    // Generar session token para facturación correcta
    function generateSessionToken() {
        return 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }

    // Inicializar autocomplete con Places API (New)
    window.initPlacesAutocompleteNew = function(map) {
        inlineMap = map;
        sessionToken = generateSessionToken();


        const inputElement = document.getElementById('address-autocomplete-input');
        const predictionsElement = document.getElementById('address-predictions');

        if (!inputElement || !predictionsElement) {
            console.error('❌ No se encontraron elementos necesarios');
            return;
        }


        // Escuchar input con debounce
        let debounceTimer;
        inputElement.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length < 3) {
                predictionsElement.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                getPlacePredictions(query, predictionsElement);
            }, 300);
        });

        // Ocultar dropdown al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!inputElement.contains(e.target) && !predictionsElement.contains(e.target)) {
                predictionsElement.style.display = 'none';
            }
        });


        // Si el input ya tiene un valor (precargado desde cookies), buscar automáticamente
        setTimeout(() => {
            const preloadedValue = inputElement.value.trim();
            if (preloadedValue && preloadedValue.length >= 3) {
                getPlacePredictions(preloadedValue, predictionsElement);
            }
        }, 500);
    };

    // Obtener predicciones usando REST API
    async function getPlacePredictions(query, predictionsElement) {

        const apiKey = window.googlePlacesConfig?.api_key;
        if (!apiKey) {
            console.error('❌ API key no disponible');
            return;
        }

        try {
            const response = await fetch('https://places.googleapis.com/v1/places:autocomplete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Goog-Api-Key': apiKey
                },
                body: JSON.stringify({
                    input: query,
                    includedRegionCodes: [window.googlePlacesConfig?.country_code?.toUpperCase() || 'AR'],
                    sessionToken: sessionToken
                })
            });

            if (!response.ok) {
                console.error('❌ Error en la API:', response.status, response.statusText);
                const errorText = await response.text();
                console.error('Error details:', errorText);
                return;
            }

            const data = await response.json();

            if (data.suggestions && data.suggestions.length > 0) {
                displayPredictions(data.suggestions, predictionsElement);
            } else {
                predictionsElement.style.display = 'none';
            }

        } catch (error) {
            console.error('❌ Error al obtener predicciones:', error);
        }
    }

    // Mostrar predicciones en dropdown
    function displayPredictions(suggestions, predictionsElement) {
        predictionsElement.innerHTML = '';

        suggestions.forEach(function(suggestion) {
            const item = document.createElement('div');
            item.style.cssText = 'padding: 12px; cursor: pointer; border-bottom: 1px solid #eee;';

            // Places API (New) usa suggestion.placePrediction.text.text
            const displayText = suggestion.placePrediction?.text?.text ||
                              suggestion.placePrediction?.structuredFormat?.mainText?.text ||
                              'Dirección';

            item.textContent = displayText;

            item.addEventListener('mouseenter', function() {
                this.style.background = '#f5f5f5';
            });

            item.addEventListener('mouseleave', function() {
                this.style.background = 'white';
            });

            item.addEventListener('click', function() {
                selectPrediction(suggestion, predictionsElement, displayText);
            });

            predictionsElement.appendChild(item);
        });

        predictionsElement.style.display = 'block';
    }

    // Seleccionar predicción y obtener detalles
    async function selectPrediction(suggestion, predictionsElement, displayText) {
        // Actualizar input
        document.getElementById('address-autocomplete-input').value = displayText;

        // Ocultar dropdown
        predictionsElement.style.display = 'none';

        // Obtener place ID
        const placeId = suggestion.placePrediction?.placeId;
        if (!placeId) {
            console.error('❌ No se encontró place_id en la sugerencia');
            return;
        }


        const apiKey = window.googlePlacesConfig?.api_key;
        try {
            const response = await fetch(`https://places.googleapis.com/v1/places/${placeId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Goog-Api-Key': apiKey,
                    'X-Goog-FieldMask': 'id,displayName,formattedAddress,location,addressComponents'
                }
            });

            if (!response.ok) {
                console.error('❌ Error al obtener detalles:', response.status);
                const errorText = await response.text();
                console.error('Error details:', errorText);
                return;
            }

            const place = await response.json();

            // Generar nuevo session token
            sessionToken = generateSessionToken();

            // Procesar el lugar seleccionado
            processPlaceDetails(place);

        } catch (error) {
            console.error('❌ Error al obtener detalles del lugar:', error);
        }
    }

    // Procesar detalles del lugar y actualizar mapa/formulario
    function processPlaceDetails(place) {

        // Obtener ubicación
        if (!place.location || !place.location.latitude || !place.location.longitude) {
            console.error('❌ El lugar no tiene información de ubicación');
            return;
        }

        const lat = place.location.latitude;
        const lng = place.location.longitude;


        // Centrar mapa
        inlineMap.setCenter({ lat, lng });
        inlineMap.setZoom(17);

        // Remover marker anterior
        if (inlineMarker) {
            inlineMarker.map = null;
        }

        // Crear nuevo marker
        const markerTitle = place.formattedAddress || place.displayName?.text || 'Dirección seleccionada';

        inlineMarker = new google.maps.marker.AdvancedMarkerElement({
            map: inlineMap,
            position: { lat, lng },
            title: markerTitle
        });


        // Actualizar formulario
        updateFormWithPlaceDetails(place);
    }

    // Actualizar formulario con detalles del lugar
    function updateFormWithPlaceDetails(place) {

        // Actualizar dirección
        if (place.formattedAddress) {
            document.getElementById('address-autocomplete-input').value = place.formattedAddress;
        }

        // Extraer componentes
        if (place.addressComponents && place.addressComponents.length > 0) {

            const components = {};

            place.addressComponents.forEach(component => {
                const types = component.types || [];

                // Dirección
                if (types.includes('street_number')) {
                    components.street_number = component.longText;
                }
                if (types.includes('route')) {
                    components.route = component.longText;
                }

                // Barrio (puede ser sublocality_level_1 o neighborhood)
                if (types.includes('sublocality_level_1') || types.includes('sublocality')) {
                    components.barrio = component.longText;
                }
                if (types.includes('neighborhood')) {
                    components.barrio = components.barrio || component.longText;
                }

                // Localidad/Ciudad
                if (types.includes('locality')) {
                    components.localidad = component.longText;
                }
                if (types.includes('administrative_area_level_2')) {
                    components.ciudad = component.longText;
                }

                // Provincia
                if (types.includes('administrative_area_level_1')) {
                    components.provincia = component.longText;
                }

                // Código Postal
                if (types.includes('postal_code')) {
                    components.codigo_postal = component.longText;
                }

                // País
                if (types.includes('country')) {
                    components.pais = component.shortText;
                }
            });


            // Construir dirección completa
            let direccionCompleta = '';
            if (components.route) {
                direccionCompleta = components.route;
                if (components.street_number) {
                    direccionCompleta += ' ' + components.street_number;
                }
            }

            // Actualizar campos del formulario (hidden inputs)
            const updateHiddenField = (id, value, label) => {
                const field = document.getElementById(id);
                if (field && value) {
                    field.value = value;
                }
            };

            updateHiddenField('shipping_address', direccionCompleta || place.formattedAddress, 'Dirección');
            updateHiddenField('shipping_postal_code', components.codigo_postal, 'Código Postal');
            updateHiddenField('shipping_city', components.localidad || components.ciudad, 'Ciudad/Localidad');
            updateHiddenField('shipping_barrio', components.barrio, 'Barrio'); // Para CABA (CP 1000-1499)
            updateHiddenField('shipping_province', components.provincia, 'Provincia');
            updateHiddenField('shipping_country', components.pais || 'AR', 'País');

            // Guardar datos normalizados
            let normalizedInput = document.getElementById('normalized_address_data');
            if (!normalizedInput) {
                normalizedInput = document.createElement('input');
                normalizedInput.type = 'hidden';
                normalizedInput.id = 'normalized_address_data';
                normalizedInput.name = 'normalized_address_data';
                document.getElementById('checkout-form').appendChild(normalizedInput);
            }

            const normalizedData = {
                formatted_address: place.formattedAddress,
                domicilio: direccionCompleta || place.formattedAddress,
                barrio: components.barrio,
                localidad: components.localidad,
                ciudad: components.ciudad,
                provincia: components.provincia,
                codigo_postal: components.codigo_postal,
                pais: components.pais || 'AR',
                place_id: place.id,
                location: place.location,
                components: components
            };

            normalizedInput.value = JSON.stringify(normalizedData);

            // Guardar en window.addressData para que shipping.js pueda acceder
            window.addressData = normalizedData;

                domicilio: direccionCompleta,
                barrio: components.barrio,
                localidad: components.localidad,
                ciudad: components.ciudad,
                provincia: components.provincia,
                codigo_postal: components.codigo_postal
            });

            // Habilitar botón de cotización ahora que la dirección está validada
            const quoteBtn = document.getElementById('get-shipping-quote');
            if (quoteBtn && quoteBtn.disabled) {
                quoteBtn.disabled = false;
                quoteBtn.title = '';

                // Agregar animación para llamar la atención del usuario
                quoteBtn.classList.add('pulse-animation');

                // Remover animación después de 5 segundos o cuando el usuario haga click
                const removeAnimation = () => {
                    quoteBtn.classList.remove('pulse-animation');
                    quoteBtn.removeEventListener('click', removeAnimation);
                };

                setTimeout(removeAnimation, 5000);
                quoteBtn.addEventListener('click', removeAnimation, { once: true });

            }

            // Guardar dirección validada en cookies para futuro uso
            saveAddressToCookies(normalizedData);
        }
    }

    /**
     * Guardar dirección validada en cookies
     */
    async function saveAddressToCookies(addressData) {
        try {
            const response = await fetch(window.BASE_PATH + '/api/?endpoint=save-address-cookies', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    address: addressData.domicilio || addressData.formatted_address,
                    postal_code: addressData.codigo_postal,
                    city: addressData.localidad || addressData.ciudad,
                    province: addressData.provincia,
                    country: addressData.pais || 'AR'
                })
            });

            if (response.ok) {
            } else {
            }
        } catch (error) {
        }
    }

})();
