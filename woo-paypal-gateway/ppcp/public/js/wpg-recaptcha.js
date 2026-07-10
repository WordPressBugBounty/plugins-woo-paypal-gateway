(function () {
    'use strict';

    var params = window.wpg_recaptcha_params || {};
    var siteKey = params.site_key || '';

    if (!siteKey) {
        return;
    }

    function getToken(action) {
        return new Promise(function (resolve) {
            if (typeof grecaptcha === 'undefined') {
                resolve('');
                return;
            }
            grecaptcha.ready(function () {
                grecaptcha.execute(siteKey, { action: action }).then(resolve).catch(function () {
                    resolve('');
                });
            });
        });
    }

    function setTokenField(token) {
        var field = document.getElementById('wpg_recaptcha_token');
        if (field) {
            field.value = token;
        }
    }

    function refreshToken() {
        getToken('checkout').then(setTokenField);
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshToken();

        var form = document.querySelector('form.checkout, form#order_review');
        if (form) {
            form.addEventListener('submit', function (e) {
                var field = document.getElementById('wpg_recaptcha_token');
                if (!field) {
                    return;
                }
                e.preventDefault();
                getToken('checkout').then(function (token) {
                    setTokenField(token);
                    HTMLFormElement.prototype.submit.call(form);
                });
            }, { once: true });
        }
    });

    document.body.addEventListener('ppcp_checkout_updated', function () {
        refreshToken();
    });

    if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('updated_checkout ppcp_checkout_updated', function () {
            refreshToken();
        });

        jQuery(document.body).on('checkout_place_order', function () {
            return getToken('checkout').then(function (token) {
                setTokenField(token);
                return true;
            });
        });
    }

    // Blocks checkout: expose for use by onPaymentSetup
    window.wpg_get_recaptcha_token = function (action) {
        return getToken(action || 'checkout');
    };
})();
