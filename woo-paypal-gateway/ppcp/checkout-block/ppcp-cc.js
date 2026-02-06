var {createElement} = wp.element;
var {registerPlugin} = wp.plugins;
var {ExperimentalOrderMeta} = wc.blocksCheckout;
var {registerExpressPaymentMethod, registerPaymentMethod} = wc.wcBlocksRegistry;
var {addAction} = wp.hooks;

(function (e) {
    var t = {};

    function n(o) {
        if (t[o])
            return t[o].exports;
        var r = (t[o] = {
            i: o,
            l: !1,
            exports: {},
        });
        return e[o].call(r.exports, r, r.exports, n), (r.l = !0), r.exports;
    }

    n.m = e;
    n.c = t;
    n.d = function (e, t, o) {
        if (!n.o(e, t)) {
            Object.defineProperty(e, t, {
                enumerable: !0,
                get: o,
            });
        }
    };
    n.r = function (e) {
        if (typeof Symbol !== "undefined" && Symbol.toStringTag) {
            Object.defineProperty(e, Symbol.toStringTag, {
                value: "Module",
            });
        }
        Object.defineProperty(e, "__esModule", {
            value: !0,
        });
    };
    n.t = function (e, t) {
        if (1 & t && (e = n(e)), 8 & t)
            return e;
        if (4 & t && typeof e === "object" && e && e.__esModule)
            return e;
        var o = Object.create(null);
        if (n.r(o), Object.defineProperty(o, "default", {enumerable: !0, value: e}), 2 & t && typeof e !== "string") {
            for (var r in e) {
                n.d(o, r, function (t) {
                    return e[t];
                }.bind(null, r));
            }
        }
        return o;
    };
    n.n = function (e) {
        var t = e && e.__esModule ? function () {
            return e.default;
        } : function () {
            return e;
        };
        return n.d(t, "a", t), t;
    };
    n.o = function (e, t) {
        return Object.prototype.hasOwnProperty.call(e, t);
    };
    n.p = "";
    n(n.s = 6);
})([
    function (e, t) {
        e.exports = window.wp.element;
    },
    function (e, t) {
        e.exports = window.wp.htmlEntities;
    },
    function (e, t) {
        e.exports = window.wp.i18n;
    },
    function (e, t) {
        e.exports = window.wc.wcSettings;
    },
    function (e, t) {
        e.exports = window.wc.wcBlocksRegistry;
    },
    ,
            function (e, t, n) {
                "use strict";
                n.r(t);
                var o, r = n(0), c = n(4), i = n(2), u = n(3), a = n(1);
                const l = Object(u.getSetting)("wpg_paypal_checkout_cc_data", {});
                const iconsElements = createElement(
                        'span',
                        {style: {display: 'flex', gap: '4px'}},
                        l.icons.map((icon, i) =>
                            createElement('img', {
                                key: `icon-${i}`,
                                src: icon,
                                style: {
                                    display: 'inline-block',
                                    verticalAlign: 'middle'
                                }
                            })
                        )
                        );

                const {is_order_confirm_page, is_paylater_enable_incart_page, page} = l;
                const {useEffect} = window.wp.element;

                const Content_PPCP_CC = (props) => {
                    const {eventRegistration, emitResponse } = props;
                    const {onPaymentSetup, onCheckoutValidation} = eventRegistration;
                    
                    useEffect(() => {
                        jQuery(document.body).trigger("ppcp_cc_checkout_updated");
                    }, []);

                    useEffect(() => {
                        const blockElements = '.wc-block-components-checkout-place-order-button, .wp-block-woocommerce-checkout-fields-block #contact-fields, .wp-block-woocommerce-checkout-fields-block #billing-fields, .wp-block-woocommerce-checkout-fields-block #payment-method';

                        const unsubscribe = onPaymentSetup(async () => {
                            try {
                                wp.data.dispatch(wc.wcBlocksData.CHECKOUT_STORE_KEY).__internalSetIdle();
                                // Defensive: re-check before submit
                                const hasErrors = wp?.data?.select('wc/store/validation')?.hasValidationErrors?.() === true;
                                if (hasErrors) {
                                    jQuery(blockElements).unblock();
                                    return false; // halt
                                }
                                jQuery(blockElements).block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
                                jQuery(document.body).trigger('submit_paypal_cc_form'); // only when clean
                                return true;
                            } catch (e) {
                                jQuery(blockElements).unblock();
                                return false;
                            } finally {
                                jQuery(blockElements).unblock();
                            }
                        });

                        return () => unsubscribe();
                    }, [onPaymentSetup]);


                    useEffect(() => {
                        const unsubscribe = onCheckoutValidation(() => {
                            const hasErrors = wp?.data?.select('wc/store/validation')?.hasValidationErrors?.() === true;
                            return hasErrors ? false : true; // stop vs proceed
                        });
                        return () => unsubscribe();
                    }, [onCheckoutValidation]);


                    return createElement(
                            'div',
                            {
                                id: 'wc-wpg_paypal_checkout_cc-form',
                                className: 'wc-credit-card-form wc-payment-form'
                            },
                            createElement(
                                    'div',
                                    {className: 'wpg-paypal-cc-field full-width'},
                                    createElement(
                                            'label',
                                            {
                                                htmlFor: 'wpg_paypal_checkout_cc-card-number',
                                                style: {display: 'none'}
                                            },
                                            l.card_number
                                            ),
                                    createElement('div', {id: 'wpg_paypal_checkout_cc-card-number'})
                                    ),
                            createElement(
                                    'div',
                                    {className: 'wpg-paypal-cc-field half-width'},
                                    createElement(
                                            'label',
                                            {
                                                htmlFor: 'wpg_paypal_checkout_cc-card-expiry',
                                                style: {display: 'none'}
                                            },
                                            l.expiration_date
                                            ),
                                    createElement('div', {id: 'wpg_paypal_checkout_cc-card-expiry'})
                                    ),
                            createElement(
                                    'div',
                                    {className: 'wpg-paypal-cc-field half-width'},
                                    createElement(
                                            'label',
                                            {
                                                htmlFor: 'wpg_paypal_checkout_cc-card-cvc',
                                                style: {display: 'none'}
                                            },
                                            l.security_code
                                            ),
                                    createElement(
                                            'div',
                                            {className: 'wpg-cvc-wrapper'},
                                            createElement('div', {id: 'wpg_paypal_checkout_cc-card-cvc'}),
                                            createElement(
                                                    'div',
                                                    {className: 'wpg-ppcp-card-cvv-icon', style: {display: 'none'}},
                                                    createElement(
                                                            'svg',
                                                            {
                                                                className: 'wpg-card-cvc-icon',
                                                                width: '24',
                                                                height: '24',
                                                                viewBox: '0 0 24 24',
                                                                xmlns: 'http://www.w3.org/2000/svg',
                                                                fill: 'var(--colorIconCardCvc)',
                                                                role: 'img',
                                                                'aria-labelledby': 'cvcDesc'
                                                            },
                                                            createElement('path', {
                                                                opacity: '.2',
                                                                fillRule: 'evenodd',
                                                                clipRule: 'evenodd',
                                                                d: 'M15.337 4A5.493 5.493 0 0013 8.5c0 1.33.472 2.55 1.257 3.5H4a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1v-.6a5.526 5.526 0 002-1.737V18a2 2 0 01-2 2H3a2 2 0 01-2-2V6a2 2 0 012-2h12.337zm6.707.293c.239.202.46.424.662.663a2.01 2.01 0 00-.662-.663z'
                                                            }),
                                                            createElement('path', {
                                                                opacity: '.4',
                                                                fillRule: 'evenodd',
                                                                clipRule: 'evenodd',
                                                                d: 'M13.6 6a5.477 5.477 0 00-.578 3H1V6h12.6z'
                                                            }),
                                                            createElement('path', {
                                                                fillRule: 'evenodd',
                                                                clipRule: 'evenodd',
                                                                d: 'M18.5 14a5.5 5.5 0 110-11 5.5 5.5 0 010 11zm-2.184-7.779h-.621l-1.516.77v.786l1.202-.628v3.63h.943V6.22h-.008zm1.807.629c.448 0 .762.251.762.613 0 .393-.37.668-.904.668h-.235v.668h.283c.565 0 .95.282.95.691 0 .393-.377.66-.911.66-.393 0-.786-.126-1.194-.37v.786c.44.189.88.291 1.312.291 1.029 0 1.736-.526 1.736-1.288 0-.535-.33-.967-.88-1.14.472-.157.778-.573.778-1.045 0-.738-.652-1.241-1.595-1.241a3.143 3.143 0 00-1.234.267v.77c.378-.212.763-.33 1.132-.33zm3.394 1.713c.574 0 .974.338.974.778 0 .463-.4.785-.974.785-.346 0-.707-.11-1.076-.337v.809c.385.173.778.26 1.163.26.204 0 .392-.032.573-.08a4.313 4.313 0 00.644-2.262l-.015-.33a1.807 1.807 0 00-.967-.252 3 3 0 00-.448.032V6.944h1.132a4.423 4.423 0 00-.362-.723h-1.587v2.475a3.9 3.9 0 01.943-.133z'
                                                            })
                                                            )
                                                    )
                                            )
                                    )
                            );
                };

                const s = {
                    name: 'wpg_paypal_checkout_cc',
                    label: createElement(
                            'span',
                            {
                                style: {
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    width: '100%'
                                }
                            },
                            l.cc_title,
                            iconsElements
                            ),
                    content: createElement(Content_PPCP_CC, null),
                    edit: createElement(Content_PPCP_CC, null),
                    canMakePayment: () => {
                        if (typeof wpg_paypal_sdk !== 'undefined' && wpg_paypal_sdk.CardFields().isEligible()) {
                            return Promise.resolve(true);
                        }
                        return Promise.resolve(false);
                    },
                    ariaLabel: Object(a.decodeEntities)(l.cc_title || Object(i.__)('Payment via PayPal', 'woo-gutenberg-products-block')),
                    supports: {
                        features: o != null ? o : l.supports,
                        showSavedCards: l.enable_save_card,
                        showSaveOption: l.enable_save_card
                    }
                };
                Object(c.registerPaymentMethod)(s);
            }
]);

const ppcp_cc_uniqueEvents = new Set([
    "experimental__woocommerce_blocks-checkout-set-active-payment-method",
]);

ppcp_cc_uniqueEvents.forEach(function (action) {
    addAction(action, "c", function () {
        setTimeout(function () {
            jQuery(document.body).trigger("ppcp_cc_checkout_updated");
        }, 3);
    });
});

document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        jQuery(document.body).trigger('ppcp_cc_block_ready');
    }, 3);
});
