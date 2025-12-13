/**
 * Sistema de Event Delegation para CSP con Nonces
 *
 * En lugar de usar onclick="foo()" en el HTML, usamos:
 *   data-action="foo"
 *   data-action="foo" data-params='{"id": 123}'
 *
 * Este script captura todos los eventos y ejecuta las funciones correspondientes.
 * Compatible con CSP estricto (nonces, sin 'unsafe-inline')
 */

(function() {
    'use strict';

    // Guard para evitar inicialización múltiple
    if (window.__eventHandlersInitialized) {
        return;
    }
    window.__eventHandlersInitialized = true;

    // Registry de acciones disponibles
    const actionRegistry = {};

    /**
     * Registrar una acción
     * @param {string} name - Nombre de la acción
     * @param {function} handler - Función a ejecutar
     */
    window.registerAction = function(name, handler) {
        actionRegistry[name] = handler;
    };

    /**
     * Ejecutar una acción por nombre
     * @param {string} name - Nombre de la acción
     * @param {Event} event - Evento original
     * @param {HTMLElement} element - Elemento que disparó la acción
     * @param {object} params - Parámetros adicionales
     */
    function executeAction(name, event, element, params) {
        if (actionRegistry[name]) {
            return actionRegistry[name](event, element, params);
        } else if (typeof window[name] === 'function') {
            // Fallback: buscar función global
            return window[name](event, element, params);
        } else {
            console.warn('Acción no encontrada:', name);
            return undefined;
        }
    }

    /**
     * Parsear parámetros del elemento
     */
    function getParams(element) {
        const paramsStr = element.getAttribute('data-params');
        if (paramsStr) {
            try {
                return JSON.parse(paramsStr);
            } catch (e) {
                console.error('Error parseando data-params:', paramsStr);
                return {};
            }
        }

        // Recoger todos los data-* como parámetros
        const params = {};
        for (const attr of element.attributes) {
            if (attr.name.startsWith('data-') && attr.name !== 'data-action' && attr.name !== 'data-params') {
                const key = attr.name.slice(5).replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                params[key] = attr.value;
            }
        }
        return params;
    }

    /**
     * Event delegation para clicks
     */
    document.addEventListener('click', function(event) {
        const element = event.target.closest('[data-action]');
        if (!element) return;

        const action = element.getAttribute('data-action');
        const params = getParams(element);

        // Prevenir comportamiento default si es un link
        if (element.tagName === 'A' && !element.getAttribute('href')?.startsWith('http')) {
            event.preventDefault();
        }

        const result = executeAction(action, event, element, params);

        // Si el elemento tiene data-stop-propagation, detener la propagación
        if (element.hasAttribute('data-stop-propagation')) {
            event.stopPropagation();
        }

        // Si la función retorna false, prevenir propagación
        if (result === false) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    /**
     * Event delegation para change
     */
    document.addEventListener('change', function(event) {
        const element = event.target.closest('[data-onchange]');
        if (!element) return;

        const action = element.getAttribute('data-onchange');
        const params = getParams(element);
        params.value = element.value;
        params.checked = element.checked;

        executeAction(action, event, element, params);
    });

    /**
     * Event delegation para input
     */
    document.addEventListener('input', function(event) {
        const element = event.target.closest('[data-oninput]');
        if (!element) return;

        const action = element.getAttribute('data-oninput');
        const params = getParams(element);
        params.value = element.value;

        executeAction(action, event, element, params);
    });

    /**
     * Event delegation para submit
     */
    document.addEventListener('submit', function(event) {
        const form = event.target.closest('[data-onsubmit]');
        if (!form) return;

        const action = form.getAttribute('data-onsubmit');
        const params = getParams(form);

        const result = executeAction(action, event, form, params);

        if (result === false) {
            event.preventDefault();
        }
    });

    /**
     * Event delegation para keyup
     */
    document.addEventListener('keyup', function(event) {
        const element = event.target.closest('[data-onkeyup]');
        if (!element) return;

        const action = element.getAttribute('data-onkeyup');
        const params = getParams(element);
        params.key = event.key;
        params.keyCode = event.keyCode;
        params.value = element.value;

        executeAction(action, event, element, params);
    });

    /**
     * Event delegation para keydown
     */
    document.addEventListener('keydown', function(event) {
        const element = event.target.closest('[data-onkeydown]');
        if (!element) return;

        const action = element.getAttribute('data-onkeydown');
        const params = getParams(element);
        params.key = event.key;
        params.keyCode = event.keyCode;

        executeAction(action, event, element, params);
    });

    /**
     * Event delegation para focus
     */
    document.addEventListener('focus', function(event) {
        const element = event.target.closest('[data-onfocus]');
        if (!element) return;

        const action = element.getAttribute('data-onfocus');
        const params = getParams(element);

        executeAction(action, event, element, params);
    }, true);

    /**
     * Event delegation para blur
     */
    document.addEventListener('blur', function(event) {
        const element = event.target.closest('[data-onblur]');
        if (!element) return;

        const action = element.getAttribute('data-onblur');
        const params = getParams(element);

        executeAction(action, event, element, params);
    }, true);

    // Indicar que el sistema está listo
    document.dispatchEvent(new CustomEvent('eventHandlersReady'));

    console.log('Sistema de event delegation inicializado');
})();
