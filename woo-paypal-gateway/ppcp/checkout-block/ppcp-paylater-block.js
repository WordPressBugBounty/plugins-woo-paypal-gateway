/**
 * Renders the PayPal Pay Later message inside the WooCommerce cart/checkout
 * blocks order summary (ExperimentalOrderMeta slot). Classic cart/checkout
 * pages keep using the hook based output in
 * class-ppcp-paypal-checkout-for-woocommerce-pay-later-messaging.php.
 *
 * Configuration is localized as ppcp_paylater_block_params:
 * - placement: 'cart' | 'payment' (matches the gateway Pay Later settings)
 * - style: PayPal Messages style object built from the gateway settings
 * - amount: server side fallback amount
 */
(function () {
    'use strict';
    if (typeof window.wp === 'undefined' || !window.wp.element || !window.wp.plugins) {
        return;
    }
    if (typeof window.wc === 'undefined' || !window.wc.blocksCheckout) {
        return;
    }
    var params = window.ppcp_paylater_block_params;
    if (!params || !params.placement) {
        return;
    }
    var createElement = window.wp.element.createElement;
    var useEffect = window.wp.element.useEffect;
    var useRef = window.wp.element.useRef;
    var registerPlugin = window.wp.plugins.registerPlugin;
    var ExperimentalOrderMeta = window.wc.blocksCheckout.ExperimentalOrderMeta;
    var TotalsWrapper = window.wc.blocksCheckout.TotalsWrapper;

    if (!ExperimentalOrderMeta || !TotalsWrapper) {
        return;
    }

    var getAmount = function (cart) {
        var totals = cart && cart.cartTotals ? cart.cartTotals : null;
        if (totals && typeof totals.total_price !== 'undefined') {
            var minorUnit = parseInt(totals.currency_minor_unit, 10);
            if (isNaN(minorUnit)) {
                minorUnit = 2;
            }
            return parseInt(totals.total_price, 10) / Math.pow(10, minorUnit);
        }
        return parseFloat(params.amount) || 0;
    };

    var PayLaterMessage = function (props) {
        var container = useRef(null);
        var amount = getAmount(props.cart);
        var sdk = window.wpg_paypal_sdk;
        var canRender = !!(sdk && typeof sdk.Messages === 'function' && amount > 0);
        useEffect(function () {
            if (canRender && container.current) {
                sdk.Messages({
                    amount: amount,
                    placement: params.placement,
                    style: params.style || {}
                }).render(container.current);
            }
        }, [canRender, amount]);
        if (!canRender) {
            return null;
        }
        var layout = params.style && params.style.layout ? params.style.layout : 'text';
        return createElement(TotalsWrapper, null,
                createElement('div', {className: 'wc-block-components-totals-item'},
                        createElement('div', {
                            ref: container,
                            className: 'ppcp_message_' + params.placement + ' ppcp_' + layout
                        })
                        )
                );
    };

    registerPlugin('wpg-ppcp-paylater-messaging', {
        render: function () {
            return createElement(ExperimentalOrderMeta, null, createElement(PayLaterMessage, null));
        },
        scope: 'woocommerce-checkout'
    });
})();
