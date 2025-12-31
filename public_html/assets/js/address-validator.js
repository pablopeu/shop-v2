/**
 * Address Validator usando Google Places API
 * Valida y normaliza direcciones de envío antes de cotizar
 */

(function() {
    'use strict';

    // Estado global del validador
    window.AddressValidator = {
        config: null,
        map: null,
        marker: null,
        autocomplete: null,
        normalizedAddress: null,
        isAddressValidated: false,
        callbacks: {
            onValidated: null,
            onCancel: null
        }
    };

    /**
     * Inicializar validador de direcciones
     * @param {Object} config - Configuración de Google Places
     */
    window.initAddressValidator = function(config) {
        if (!config || !config.enabled) {
            console.log('Google Places no está habilitado');
            return;
        }

        AddressValidator.config = config;
        console.log('Address Validator inicializado');
    };

    /**
     * Cargar Google Maps API de forma dinámica
     */
    window.loadGoogleMapsAPI = function(callback) {
        if (!AddressValidator.config || !AddressValidator.config.enabled) {
            if (callback) callback(false);
            return;
        }

        // Verificar si ya está cargado
        if (typeof google !== 'undefined' && google.maps) {
            if (callback) callback(true);
            return;
        }

        // Crear callback global
        window.initGooglePlaces = function() {
            console.log('Google Maps API cargada');
            if (callback) callback(true);
        };

        // Cargar script
        const script = document.createElement('script');
        script.src = AddressValidator.config.maps_js_url;
        script.async = true;
        script.defer = true;
        script.onerror = function() {
            console.error('Error al cargar Google Maps API');
            if (callback) callback(false);
        };

        document.head.appendChild(script);
    };

    /**
     * Mostrar modal de validación de dirección
     * @param {Object} addressData - Datos actuales de dirección del formulario
     * @param {Function} onConfirm - Callback cuando se confirma la dirección
     * @param {Function} onCancel - Callback cuando se cancela
     */
    window.showAddressValidationModal = function(addressData, onConfirm, onCancel) {
        if (!AddressValidator.config || !AddressValidator.config.enabled) {
            // Si no está habilitado, confirmar directamente sin validación
            if (onConfirm) onConfirm(addressData);
            return;
        }

        AddressValidator.callbacks.onValidated = onConfirm;
        AddressValidator.callbacks.onCancel = onCancel;

        // Crear modal
        const modalHTML = `
            <div id="addressValidationModal" class="address-validation-modal">
                <div class="address-validation-content">
                    <div class="address-validation-header">
                        <h3>🌍 Validar Dirección de Envío</h3>
                        <button type="button" class="close-modal-btn" data-action="closeAddressModal">✕</button>
                    </div>

                    <div class="address-validation-body">
                        <div class="validation-instructions">
                            <p><strong>📍 Verificando tu dirección de envío...</strong></p>
                            <p style="margin-bottom: 8px;">Estamos obteniendo los detalles completos de tu dirección. Verificá que la información sea correcta en el panel de abajo.</p>
                        </div>

                        <div class="address-search-container">
                            <label for="addressSearchInput" style="font-weight: 600; color: #333;">🔍 Buscar otra dirección (opcional):</label>
                            <input
                                type="text"
                                id="addressSearchInput"
                                class="address-search-input"
                                placeholder="Si la dirección no es correcta, buscá otra aquí..."
                                autocomplete="off"
                            >
                            <div class="help-text-small">
                                Solo si necesitás cambiar la dirección detectada
                            </div>
                        </div>

                        <div id="addressMap" class="address-map"></div>

                        <div id="normalizedAddressDisplay" class="normalized-address" style="display: none;">
                            <h4>✓ Dirección Normalizada:</h4>
                            <div id="normalizedAddressText" class="normalized-address-text"></div>
                            <div id="normalizedAddressComponents" class="normalized-address-components"></div>
                        </div>
                    </div>

                    <div class="address-validation-footer">
                        <button
                            type="button"
                            class="btn btn-cancel"
                            data-action="closeAddressModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            id="confirmAddressBtn"
                            class="btn btn-confirm"
                            data-action="confirmNormalizedAddress"
                            disabled
                        >
                            Confirmar Dirección
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Insertar modal en el DOM
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstElementChild);

        // Cargar Google Maps y luego inicializar
        loadGoogleMapsAPI(function(success) {
            if (success) {
                initializeMap(addressData);
            } else {
                alert('Error al cargar Google Maps. Por favor, intentá nuevamente.');
                closeAddressValidationModal();
            }
        });
    };

    /**
     * Inicializar mapa y autocomplete
     */
    function initializeMap(addressData) {
        // Coordenadas por defecto (centro de Argentina)
        const defaultCenter = { lat: -34.6037, lng: -58.3816 }; // Buenos Aires

        // Crear mapa
        const mapElement = document.getElementById('addressMap');
        AddressValidator.map = new google.maps.Map(mapElement, {
            center: defaultCenter,
            zoom: 13,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        });

        // Crear marker
        AddressValidator.marker = new google.maps.Marker({
            map: AddressValidator.map,
            draggable: false,
            animation: google.maps.Animation.DROP
        });

        // Configurar autocomplete
        const input = document.getElementById('addressSearchInput');
        const options = {
            fields: ['address_components', 'formatted_address', 'geometry', 'place_id'],
            strictBounds: false
        };

        // Restricción de país si está configurada
        if (AddressValidator.config.country_code) {
            options.componentRestrictions = {
                country: AddressValidator.config.country_code
            };
        }

        AddressValidator.autocomplete = new google.maps.places.Autocomplete(input, options);
        AddressValidator.autocomplete.bindTo('bounds', AddressValidator.map);

        // Listener para cuando se selecciona un lugar
        AddressValidator.autocomplete.addListener('place_changed', function() {
            const place = AddressValidator.autocomplete.getPlace();

            if (!place.geometry || !place.geometry.location) {
                alert('No se pudo obtener información de esta dirección. Por favor, seleccioná una opción de la lista de sugerencias.');
                return;
            }

            // Procesar y mostrar lugar
            processPlace(place);
        });

        // Pre-llenar con dirección actual si existe
        if (addressData && addressData.address) {
            const fullAddress = [
                addressData.address,
                addressData.city,
                addressData.province,
                addressData.postal_code
            ].filter(x => x).join(', ');

            input.value = fullAddress;

            // Hacer geocoding automático de la dirección inicial
            geocodeAddress(fullAddress);
        }
    }

    /**
     * Hacer geocoding de una dirección y centrar el mapa
     */
    function geocodeAddress(address) {
        if (!address || !google.maps.Geocoder) {
            return;
        }

        const geocoder = new google.maps.Geocoder();
        const request = {
            address: address
        };

        // Agregar restricción de país si está configurada
        if (AddressValidator.config.country_code) {
            request.componentRestrictions = {
                country: AddressValidator.config.country_code
            };
        }

        geocoder.geocode(request, function(results, status) {
            if (status === 'OK' && results && results.length > 0) {
                const result = results[0];

                // Centrar mapa en la ubicación
                AddressValidator.map.setCenter(result.geometry.location);
                AddressValidator.map.setZoom(15);

                // Mostrar marker
                AddressValidator.marker.setPosition(result.geometry.location);
                AddressValidator.marker.setVisible(true);
                AddressValidator.marker.setOpacity(0.8);
                AddressValidator.marker.setTitle('Verificando dirección...');

                console.log('📍 Dirección geocodificada:', result.formatted_address);

                // Si tiene place_id, obtener detalles completos (incluyendo barrio)
                if (result.place_id) {
                    getPlaceDetails(result.place_id);
                }
            } else {
                console.warn('No se pudo geocodificar la dirección inicial:', status);
            }
        });
    }

    /**
     * Obtener detalles completos del lugar usando place_id
     * Usa la nueva Places API (New) via REST
     */
    function getPlaceDetails(placeId) {
        if (!AddressValidator.config || !AddressValidator.config.api_key) {
            console.warn('API key no disponible');
            return;
        }

        // Usar la nueva Places API (New) - REST API
        const url = `https://places.googleapis.com/v1/places/${placeId}`;

        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Goog-Api-Key': AddressValidator.config.api_key,
                'X-Goog-FieldMask': 'id,formattedAddress,addressComponents,location,displayName'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('✅ Detalles del lugar obtenidos (New API):', data);

            // Convertir formato de la nueva API al formato antiguo para compatibilidad
            const place = convertNewAPIToLegacyFormat(data);

            // Procesar automáticamente
            processPlace(place);
        })
        .catch(error => {
            console.error('Error al obtener detalles del lugar:', error);
            // Fallback: el usuario puede buscar manualmente
        });
    }

    /**
     * Convertir formato de Places API (New) al formato legacy
     */
    function convertNewAPIToLegacyFormat(newAPIData) {
        // Convertir addressComponents al formato legacy
        const addressComponents = [];

        if (newAPIData.addressComponents) {
            newAPIData.addressComponents.forEach(component => {
                addressComponents.push({
                    long_name: component.longText || '',
                    short_name: component.shortText || '',
                    types: component.types || []
                });
            });
        }

        // Convertir location
        const location = newAPIData.location ? {
            lat: () => newAPIData.location.latitude,
            lng: () => newAPIData.location.longitude
        } : null;

        return {
            place_id: newAPIData.id,
            formatted_address: newAPIData.formattedAddress || '',
            address_components: addressComponents,
            geometry: {
                location: location
            },
            name: newAPIData.displayName?.text || ''
        };
    }

    /**
     * Procesar lugar seleccionado
     */
    function processPlace(place) {
        // Actualizar mapa
        AddressValidator.map.setCenter(place.geometry.location);
        AddressValidator.map.setZoom(17);

        // Actualizar marker (opaco = ubicación confirmada)
        AddressValidator.marker.setPosition(place.geometry.location);
        AddressValidator.marker.setVisible(true);
        AddressValidator.marker.setOpacity(1.0);
        AddressValidator.marker.setTitle('Ubicación confirmada');

        // Extraer componentes de dirección
        const components = extractAddressComponents(place.address_components);

        // Guardar dirección normalizada
        AddressValidator.normalizedAddress = {
            formatted_address: place.formatted_address,
            place_id: place.place_id,
            latitude: place.geometry.location.lat(),
            longitude: place.geometry.location.lng(),
            components: components
        };

        // Mostrar dirección normalizada
        displayNormalizedAddress(AddressValidator.normalizedAddress);

        // Habilitar botón de confirmar
        const confirmBtn = document.getElementById('confirmAddressBtn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
        }

        AddressValidator.isAddressValidated = true;
    }

    /**
     * Extraer componentes de dirección
     */
    function extractAddressComponents(addressComponents) {
        const components = {};
        const componentMap = {
            'street_number': 'street_number',
            'route': 'route',
            'locality': 'city',
            'sublocality_level_1': 'neighborhood',
            'administrative_area_level_1': 'province',
            'administrative_area_level_2': 'department',
            'postal_code': 'postal_code',
            'country': 'country'
        };

        addressComponents.forEach(component => {
            component.types.forEach(type => {
                if (componentMap[type]) {
                    const key = componentMap[type];
                    components[key] = component.long_name;
                    components[key + '_short'] = component.short_name;
                }
            });
        });

        // Construir dirección de calle
        if (components.route) {
            components.address = components.street_number
                ? components.route + ' ' + components.street_number
                : components.route;
        }

        return components;
    }

    /**
     * Mostrar dirección normalizada
     */
    function displayNormalizedAddress(normalized) {
        const displayDiv = document.getElementById('normalizedAddressDisplay');
        const textDiv = document.getElementById('normalizedAddressText');
        const componentsDiv = document.getElementById('normalizedAddressComponents');

        textDiv.textContent = normalized.formatted_address;

        // Mostrar componentes relevantes
        const componentsHTML = [];
        const comp = normalized.components;

        if (comp.address) {
            componentsHTML.push(`<div class="addr-component"><strong>Calle:</strong> ${comp.address}</div>`);
        }
        if (comp.neighborhood) {
            componentsHTML.push(`<div class="addr-component"><strong>Barrio:</strong> ${comp.neighborhood}</div>`);
        }
        if (comp.city) {
            componentsHTML.push(`<div class="addr-component"><strong>Ciudad:</strong> ${comp.city}</div>`);
        }
        if (comp.province) {
            componentsHTML.push(`<div class="addr-component"><strong>Provincia:</strong> ${comp.province}</div>`);
        }
        if (comp.postal_code) {
            componentsHTML.push(`<div class="addr-component"><strong>Código Postal:</strong> ${comp.postal_code}</div>`);
        }

        componentsDiv.innerHTML = componentsHTML.join('');
        displayDiv.style.display = 'block';
    }

    /**
     * Cerrar modal de validación
     */
    window.closeAddressValidationModal = function() {
        const modal = document.getElementById('addressValidationModal');
        if (modal) {
            modal.remove();
        }

        AddressValidator.normalizedAddress = null;
        AddressValidator.isAddressValidated = false;

        if (AddressValidator.callbacks.onCancel) {
            AddressValidator.callbacks.onCancel();
        }
    };

    /**
     * Confirmar dirección normalizada
     */
    window.confirmNormalizedAddress = function() {
        if (!AddressValidator.normalizedAddress) {
            alert('Por favor, buscá y seleccioná tu dirección del mapa primero.');
            return;
        }

        const normalized = AddressValidator.normalizedAddress;

        // Cerrar modal
        const modal = document.getElementById('addressValidationModal');
        if (modal) {
            modal.remove();
        }

        // Callback con dirección normalizada
        if (AddressValidator.callbacks.onValidated) {
            AddressValidator.callbacks.onValidated(normalized);
        }

        AddressValidator.isAddressValidated = true;
    };

    // Exportar funciones para event delegation
    window.closeAddressModal = closeAddressValidationModal;

})();
