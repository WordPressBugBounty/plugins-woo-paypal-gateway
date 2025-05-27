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
        var r = (t[o] = {i: o, l: !1, exports: {}});
        return e[o].call(r.exports, r, r.exports, n), (r.l = !0), r.exports;
    }
    n.m = e;
    n.c = t;
    n.d = function (e, t, o) {
        if (!n.o(e, t)) {
            Object.defineProperty(e, t, {enumerable: !0, get: o});
        }
    };
    n.r = function (e) {
        if (typeof Symbol !== "undefined" && Symbol.toStringTag) {
            Object.defineProperty(e, Symbol.toStringTag, {value: "Module"});
        }
        Object.defineProperty(e, "__esModule", {value: !0});
    };
    n.t = function (e, t) {
        if (1 & t && (e = n(e)), 8 & t)
            return e;
        if (4 & t && typeof e === "object" && e && e.__esModule)
            return e;
        var o = Object.create(null);
        if (n.r(o), Object.defineProperty(o, "default", {enumerable: !0, value: e}), 2 & t && typeof e !== "string") {
            for (var r in e)
                n.d(o, r, function (t) {
                    return e[t];
                }.bind(null, r));
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
                var o = n(0),
                        r = n(4),
                        c = n(2),
                        i = n(3),
                        u = n(1);

                if (typeof wpg_paypal_checkout_manager_block === 'undefined') {
                    return false;
                }
                const l = Object(i.getSetting)("wpg_paypal_checkout_data", {});
                const p = () => Object(u.decodeEntities)(l.description || "");
                const {useEffect} = wp.element;
                const ppcp_settings = wpg_paypal_checkout_manager_block.settins;
                const device_class = wpg_paypal_checkout_manager_block.is_mobile;
                const button_class = wpg_paypal_checkout_manager_block.button_class;

                const Content_PPCP_Smart_Button_Checkout_Top = (props) => {
                    const {billing, shippingData} = props;

                    useEffect(() => {
                        jQuery(document.body).trigger("ppcp_checkout_updated");
                    }, []);

                    const isGooglePayEnabled = wpg_paypal_checkout_manager_block.is_google_pay_enable_for_express_checkout === 'yes';
                    const isApplePayEnabled = wpg_paypal_checkout_manager_block.is_apple_pay_enable_for_express_checkout === 'yes';
                    const isCheckoutButtonTopEnabled = ppcp_settings.enable_checkout_button_top === 'yes';

                    return createElement("div", {},
                            isCheckoutButtonTopEnabled && [
                                createElement("div", {
                                    key: "ppcp_checkout_top",
                                    id: "ppcp_checkout_top",
                                    className: device_class
                                }),
                                createElement("div", {
                                    key: "ppcp_checkout_top_alternative",
                                    id: "ppcp_checkout_top_alternative",
                                    className: device_class
                                })
                            ],
                            isGooglePayEnabled &&
                            createElement("div", {
                                key: "google_pay_button",
                                className: "google-pay-container express_checkout " + device_class,
                                style: {height: "40px"},
                                'data-context': 'express_checkout'
                            }),
                            isApplePayEnabled &&
                            createElement("div", {
                                key: "apple_pay_button",
                                className: "apple-pay-container express_checkout " + device_class,
                                style: {height: "40px"},
                                'data-context': 'express_checkout'
                            })
                            );
                };
                const Content_PPCP_Smart_Button_Cart_Bottom = (props) => {
                    const {billing, shippingData} = props;

                    useEffect(() => {
                        jQuery(document.body).trigger("ppcp_checkout_updated");
                    }, []);

                    const isGooglePayEnabledForCart = wpg_paypal_checkout_manager_block.is_google_pay_enable_for_cart === 'yes';
                    const isApplePayEnabledForCart = wpg_paypal_checkout_manager_block.is_apple_pay_enable_for_cart === 'yes';
                    const showCartButton = ppcp_settings.show_on_cart === 'yes';

                    return createElement("div", {},
                            showCartButton && createElement("div", {
                                id: "ppcp_cart",
                                className: button_class
                            }),
                            isGooglePayEnabledForCart && createElement("div", {
                                className: "google-pay-container cart " + button_class,
                                style: {height: "48px"},
                                'data-context': 'cart'
                            }),
                            isApplePayEnabledForCart && createElement("div", {
                                className: "apple-pay-container cart " + button_class,
                                style: {height: "48px"},
                                'data-context': 'cart'
                            })
                            );
                };



                const ContentPPCPCheckout = (props) => {
                    const {billing, shippingData, ...i} = props;
                    if (l.use_place_order === true) {
                        return createElement(
                                "div",
                                {className: "ppcp_checkout_parent"},
                                createElement("input", {type: "hidden", name: "form", value: "checkout"}),
                                createElement("p", null, l.description || '')
                                );
                    }
                    const isGooglePayEnabledForCheckout = wpg_paypal_checkout_manager_block.is_google_pay_enable_for_checkout === 'yes';
                    const isApplePayEnabledForCheckout = wpg_paypal_checkout_manager_block.is_apple_pay_enable_for_checkout === 'yes';
                    return createElement(
                            "div",
                            {className: "ppcp_checkout_parent"},
                            createElement("input", {type: "hidden", name: "form", value: "checkout"}),
                            createElement("div", {id: "ppcp_checkout", className: button_class}),
                            isGooglePayEnabledForCheckout && createElement("div", {
                                className: "google-pay-container checkout " + button_class,
                                style: {height: "48px"},
                                'data-context': 'checkout'
                            }),
                            isApplePayEnabledForCheckout && createElement("div", {
                                className: "apple-pay-container checkout " + button_class,
                                style: {height: "48px"},
                                'data-context': 'checkout'
                            })
                            );
                };
                const s = {
                    name: "wpg_paypal_checkout",
                    label: createElement("span", {style: {width: "100%"}}, l.title, createElement("img", {src: l.icons, style: {float: "right", marginLeft: "20px", display: "flex", justifyContent: "flex-end", paddingRight: "10px"}})),
                    placeOrderButtonLabel: Object(c.__)(wpg_paypal_checkout_manager_block.placeOrderButtonLabel),
                    content: createElement(ContentPPCPCheckout, null),
                    edit: Object(o.createElement)(p, null),
                    canMakePayment: () => Promise.resolve(true),
                    ariaLabel: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                    supports: {
                        features: l.supports || [],
                        showSavedCards: false,
                        showSaveOption: false
                    }
                };
                Object(r.registerPaymentMethod)(s);


                const {is_order_confirm_page, is_paylater_enable_incart_page, page} = wpg_paypal_checkout_manager_block;

                if (page === "checkout" && is_order_confirm_page === "no" && ppcp_settings && (ppcp_settings.enable_checkout_button_top === "yes" || wpg_paypal_checkout_manager_block.is_google_pay_enable_for_express_checkout === 'yes' || wpg_paypal_checkout_manager_block.is_apple_pay_enable_for_express_checkout === 'yes')) {
                    const commonExpressPaymentMethodConfig = {
                        name: "wpg_paypal_checkout_top",
                        label: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        content: createElement(Content_PPCP_Smart_Button_Checkout_Top, null),
                        edit: Object(o.createElement)(p, null),
                        ariaLabel: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        canMakePayment: () => true,
                        paymentMethodId: "wpg_paypal_checkout",
                        supports: {features: l.supports || []}
                    };
                    Object(r.registerExpressPaymentMethod)(commonExpressPaymentMethodConfig);
                } else if (page === "cart" && ppcp_settings && (ppcp_settings.show_on_cart === "yes" || wpg_paypal_checkout_manager_block.is_google_pay_enable_for_cart === 'yes' || wpg_paypal_checkout_manager_block.is_apple_pay_enable_for_cart === 'yes')) {
                    const commonExpressPaymentMethodConfig = {
                        name: "wpg_paypal_checkout_top",
                        label: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        content: createElement(Content_PPCP_Smart_Button_Cart_Bottom, null),
                        edit: Object(o.createElement)(p, null),
                        ariaLabel: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        canMakePayment: () => true,
                        paymentMethodId: "wpg_paypal_checkout",
                        supports: {features: l.supports || []}
                    };
                    Object(r.registerExpressPaymentMethod)(commonExpressPaymentMethodConfig);
                }
            }
]);

document.addEventListener("DOMContentLoaded", function () {
    setTimeout(function () {
        jQuery(document.body).trigger("ppcp_block_ready");
    }, 3);
});

const ppcp_uniqueEvents = new Set([
    "experimental__woocommerce_blocks-checkout-set-shipping-address",
    "experimental__woocommerce_blocks-checkout-set-billing-address",
    "experimental__woocommerce_blocks-checkout-set-email-address",
    "experimental__woocommerce_blocks-checkout-render-checkout-form",
    "experimental__woocommerce_blocks-checkout-set-active-payment-method",
]);

ppcp_uniqueEvents.forEach(function (action) {
    addAction(action, "c", function () {
        setTimeout(function () {
            jQuery(document.body).trigger("ppcp_checkout_updated");
        }, 3);
    });
});

function showErrorUsingShowNotice(error_message) {
    wp.data.dispatch('core/notices').createNotice(
            'error',
            error_message,
            {
                isDismissible: true,
                context: 'wc/checkout'
            }
    );
}

jQuery(document.body).on('ppcp_checkout_error', function (event, errorMessages) {
    showErrorUsingShowNotice(errorMessages);
});