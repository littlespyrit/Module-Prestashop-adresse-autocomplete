/**
 * BAN Address Autocomplete
 * Branche un champ "adresse" sur l'API Adresse (api-adresse.data.gouv.fr).
 * Vanilla JS, aucune dépendance externe.
 */
(function () {
    'use strict';

    var config = window.banAddrConfig || {
        minChars: 3,
        limit: 5,
        apiUrl: 'https://api-adresse.data.gouv.fr/search/',
        colors: { bg: '#ffffff', text: '#333333', hover: '#f5f5f5', hoverText: '#333333', border: '#dddddd', radius: 4 },
        fields: { address1: 'field-address1', postcode: 'field-postcode', city: 'field-city', country: 'field-id_country' }
    };

    // FIELD_MAP construit à partir des ID configurés dans le BO du module
    var FIELD_MAP = {
        address1: '#' + config.fields.address1,
        postcode: '#' + config.fields.postcode,
        city: '#' + config.fields.city,
        country: '#' + config.fields.country
    };

    var debounceTimer = null;
    var currentAbortController = null;

    function qs(selector) {
        return document.querySelector(selector);
    }

    function createSuggestionBox(input) {
        var box = document.createElement('div');
        box.className = 'ban-addr-suggestions';
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(box);
        return box;
    }

    function clearSuggestions(box) {
        if (box) {
            box.innerHTML = '';
            box.style.display = 'none';
        }
    }

    function renderSuggestions(box, features) {
        box.innerHTML = '';

        if (!features.length) {
            box.style.display = 'none';
            return;
        }

        features.forEach(function (feature) {
            var props = feature.properties;
            var item = document.createElement('div');
            item.className = 'ban-addr-suggestion';
            item.textContent = props.label;

            // Priorité maximale : couleur posée directement en inline style + !important,
            // rien dans le CSS du thème ne peut passer devant ça.
            if (config.colors && config.colors.text) {
                item.style.setProperty('color', resolveColor(config.colors.text), 'important');
            }
            item.addEventListener('mouseenter', function () {
                if (config.colors && config.colors.hoverText) {
                    item.style.setProperty('color', resolveColor(config.colors.hoverText), 'important');
                }
                if (config.colors && config.colors.hover) {
                    item.style.setProperty('background-color', resolveColor(config.colors.hover), 'important');
                }
            });
            item.addEventListener('mouseleave', function () {
                if (config.colors && config.colors.text) {
                    item.style.setProperty('color', resolveColor(config.colors.text), 'important');
                }
                item.style.setProperty('background-color', 'transparent', 'important');
            });

            item.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                applySuggestion(props);
                clearSuggestions(box);
            });
            item.addEventListener('mousedown', function (e) {
                // Empêche le blur du champ (et donc la fermeture de certains accordéons)
                // de se déclencher avant que le clic ne soit traité
                e.preventDefault();
            });
            box.appendChild(item);
        });

        box.style.display = 'block';
    }

    function applySuggestion(props) {
        var addressField = qs(FIELD_MAP.address1);
        var postcodeField = qs(FIELD_MAP.postcode);
        var cityField = qs(FIELD_MAP.city);

        if (addressField) {
            // housenumber + street si dispo, sinon le label complet moins CP/ville
            var streetLine = props.housenumber
                ? props.housenumber + ' ' + props.street
                : (props.name || props.street || props.label);
            addressField.value = streetLine;
        }
        if (postcodeField && props.postcode) {
            postcodeField.value = props.postcode;
            postcodeField.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (cityField && props.city) {
            cityField.value = props.city;
            cityField.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function fetchSuggestions(query, box) {
        if (currentAbortController) {
            currentAbortController.abort();
        }
        currentAbortController = new AbortController();

        var url = config.apiUrl + '?q=' + encodeURIComponent(query) + '&limit=' + config.limit + '&autocomplete=1';

        fetch(url, { signal: currentAbortController.signal })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('BAN API error: ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                renderSuggestions(box, data.features || []);
            })
            .catch(function (err) {
                if (err.name !== 'AbortError') {
                    console.error('[BanAddr] erreur requête API :', err);
                    // Echec silencieux : l'utilisateur peut toujours saisir l'adresse à la main
                    clearSuggestions(box);
                }
            });
    }

    function handleInput(e, box) {
        var value = e.target.value.trim();

        clearTimeout(debounceTimer);

        if (value.length < config.minChars) {
            clearSuggestions(box);
            return;
        }

        debounceTimer = setTimeout(function () {
            fetchSuggestions(value, box);
        }, 250);
    }

    /**
     * Branche les écouteurs sur un champ #address1 donné (une seule fois par champ)
     */
    function bindField(input) {
        if (input.dataset.banAddrBound) {
            return;
        }
        input.dataset.banAddrBound = '1';
        input.setAttribute('autocomplete', 'off');

        var box = createSuggestionBox(input);

        input.addEventListener('input', function (e) {
            handleInput(e, box);
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !box.contains(e.target)) {
                clearSuggestions(box);
            }
        });
    }

    /**
     * Cherche le champ adresse et le branche s'il est présent.
     * Appelé au chargement ET à chaque changement du DOM, pour couvrir
     * le cas des formulaires chargés en AJAX (tunnel de commande PrestaShop).
     */
    function tryBind() {
        var input = qs(FIELD_MAP.address1);
        if (input) {
            bindField(input);
        }
    }

    // Si la valeur est le nom d'une variable CSS (--color-beige), on l'enveloppe
    // dans var(...) pour que le navigateur la résolve. Sinon on la garde telle quelle (hex).
    function resolveColor(value) {
        if (!value) {
            return null;
        }
        if (value.indexOf('--') === 0) {
            return 'var(' + value + ')';
        }
        return value;
    }

    function applyColors() {
        var root = document.documentElement;
        if (config.colors) {
            if (config.colors.bg) root.style.setProperty('--ban-addr-bg', resolveColor(config.colors.bg));
            if (config.colors.text) root.style.setProperty('--ban-addr-text', resolveColor(config.colors.text));
            if (config.colors.hover) root.style.setProperty('--ban-addr-hover', resolveColor(config.colors.hover));
            if (config.colors.hoverText) root.style.setProperty('--ban-addr-hover-text', resolveColor(config.colors.hoverText));
            if (config.colors.border) root.style.setProperty('--ban-addr-border', resolveColor(config.colors.border));
            if (config.colors.radius !== undefined && config.colors.radius !== null) {
                root.style.setProperty('--ban-addr-radius', config.colors.radius + 'px');
            }
        }
    }

    function init() {
        applyColors();
        tryBind();

        var observer = new MutationObserver(function () {
            tryBind();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
