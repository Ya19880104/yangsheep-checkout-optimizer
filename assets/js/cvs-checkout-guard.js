/* Classic checkout: require a complete store only for explicit provider contracts. */
(function ($) {
    'use strict';

    var providers = { ry_ecpay: true, woomp_ecpay: true, paynow: true };
    var fallbackMessage = '尚未選擇超商取貨門市，請先選擇超商。';

    function own(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    function currentContracts(form) {
        var element = form.querySelector('#ys-cvs-checkout-contracts') ||
            document.getElementById('ys-cvs-checkout-contracts');
        if (element) {
            // Shipping-zone/rate fragments supersede the initial localized configuration.
            try {
                var contracts = JSON.parse(element.getAttribute('data-contracts'));
                return Array.isArray(contracts) ? contracts : [];
            } catch (error) {
                return []; // Backend validation remains authoritative if metadata is unavailable.
            }
        }
        var config = window.ysCvsCheckoutGuard;
        return config && Array.isArray(config.contracts) ? config.contracts : [];
    }

    function validStore(contract, values) {
        var fields = contract.fields;
        if (!fields || typeof fields !== 'object' || Array.isArray(fields) ||
            typeof contract.expected !== 'string' || contract.expected.trim() === '') {
            return false;
        }
        var extra = contract.extra_fields === undefined ? [] : contract.extra_fields;
        if (!Array.isArray(extra)) {
            return false;
        }
        var names = [fields.id, fields.name, fields.address, fields.service].concat(extra);
        if (names.some(function (name) {
            return typeof name !== 'string' || name === '' || !own(values, name) ||
                values[name].length !== 1 || !/[^\s\u200B\uFEFF]/.test(values[name][0]);
        })) {
            return false;
        }
        return values[fields.service][0] === contract.expected;
    }

    function invalidStore(form) {
        var contracts = currentContracts(form);
        var shipping = [];
        var shippingCounts = Object.create(null);
        var values = Object.create(null);

        // Serialize afresh: disabled fields/fieldsets, unchecked radios and stale nodes do not count.
        $(form).serializeArray().forEach(function (field) {
            if (/^shipping_method(?:\[\d+\])?$/.test(field.name)) {
                shipping.push(field);
                shippingCounts[field.name] = (shippingCounts[field.name] || 0) + 1;
            }
            if (!own(values, field.name)) {
                values[field.name] = [];
            }
            values[field.name].push(field.value.trim());
        });

        return shipping.some(function (field) {
            var matches = contracts.filter(function (contract) {
                return contract && typeof contract === 'object' &&
                    own(providers, contract.provider) && contract.rate === field.value;
            });
            if (!matches.length) {
                return false; // Leave postal services and custom providers to their own validators.
            }
            return matches.length !== 1 || shippingCounts[field.name] !== 1 ||
                !validStore(matches[0], values);
        });
    }

    function allowCheckout(form, event) {
        if (!invalidStore(form)) {
            return true;
        }
        event.preventDefault();
        // WooCommerce reads the last handler result; later handlers cannot undo this rejection.
        event.stopImmediatePropagation();
        var config = window.ysCvsCheckoutGuard;
        window.alert(config && typeof config.message === 'string' && config.message.trim() ? config.message : fallbackMessage);
        var button = form.querySelector('#choose-cvs-btn, .ry-choose-cvs');
        if (button) {
            button.focus();
        }
        return false;
    }

    document.addEventListener('click', function (event) {
        var button = event.target instanceof Element ? event.target.closest('#place_order') : null;
        var form = button && (button.form || button.closest('form.checkout'));
        if (form && form.matches('form.checkout')) {
            allowCheckout(form, event);
        }
    }, true);

    // Covers Enter and requestSubmit before native/bubbling checkout handlers run.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form instanceof Element && form.matches('form.checkout')) {
            allowCheckout(form, event);
        }
    }, true);

    function bindWooCommerceGuard() {
        $('form.checkout')
            .off('checkout_place_order.ysCvsCheckoutGuard')
            .on('checkout_place_order.ysCvsCheckoutGuard', function (event) {
                return allowCheckout(this, event);
            });
    }

    $(function () {
        bindWooCommerceGuard();
        $(document.body).on('init_checkout.ysCvsCheckoutGuard updated_checkout.ysCvsCheckoutGuard', bindWooCommerceGuard);
    });
})(jQuery);
