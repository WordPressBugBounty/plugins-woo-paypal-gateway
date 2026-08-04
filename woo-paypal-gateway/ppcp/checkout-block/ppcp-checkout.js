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


                const l = Object(i.getSetting)("wpg_paypal_checkout_data", {});
                const {useEffect, useRef, useState} = wp.element;
                const ppcp_settings = l.settings || l.settins;
                const device_class = l.is_mobile;
                const button_class = l.button_class;

                /**
                 * Block editor preview.
                 *
                 * The components below render the empty containers that the PayPal JS SDK
                 * fills on the front end. Nothing loads the SDK inside the block editor, so
                 * those containers stay empty there and the placement looks like it is
                 * missing.
                 *
                 * The SDK cannot be loaded in the editor either: its canvas is a srcdoc
                 * iframe, window.location.host is empty in such a document, and the SDK
                 * refuses to bootstrap without one ("Can not read window host"). So `edit`
                 * embeds a small same-origin page of ours which draws the merchant's real
                 * PayPal, Venmo, Pay Later, Google Pay and Apple Pay buttons in an ordinary
                 * document, where the SDK works. See PPCP_Editor_Button_Preview.
                 *
                 * The frame is inert -- pointer-events: none, and its buttons have no
                 * createOrder callback -- so a real button here is still only a picture.
                 * It reports its height back, so the placement is sized to whatever PayPal
                 * decided to draw; a placement whose buttons cannot be drawn stays empty.
                 */
                const preview_data = l.preview || {};

                const previewPlacements = (context) => {
                    const settings = ppcp_settings || {};
                    if (context === "cart") {
                        return {
                            paypal: settings.show_on_cart === "yes",
                            google: l.is_google_pay_enable_for_cart === "yes",
                            apple: l.is_apple_pay_enable_for_cart === "yes"
                        };
                    }
                    if (context === "express_checkout") {
                        return {
                            paypal: settings.enable_checkout_button_top === "yes",
                            google: l.is_google_pay_enable_for_express_checkout === "yes",
                            apple: l.is_apple_pay_enable_for_express_checkout === "yes"
                        };
                    }
                    return {
                        paypal: true,
                        google: l.is_google_pay_enable_for_checkout === "yes",
                        apple: l.is_apple_pay_enable_for_checkout === "yes"
                    };
                };

                const WPGButtonPreview = (props) => {
                    const context = props.context;
                    const enabled = previewPlacements(context);
                    const frameRef = useRef(null);
                    const [height, setHeight] = useState(0);

                    useEffect(() => {
                        const reported = {};
                        const onMessage = (event) => {
                            const frame = frameRef.current;
                            if (!frame || !event.data || event.data.type !== "wpg-ppcp-preview-height") {
                                return;
                            }
                            if (event.source !== frame.contentWindow) {
                                return;
                            }
                            const drawn = parseInt(event.data.height, 10);
                            if (drawn > 0) {
                                setHeight(drawn);
                            }
                            // A button the placement offers but the preview could not draw
                            // says so once, so it never just looks like it went missing.
                            const skipped = event.data.skipped || {};
                            Object.keys(skipped).forEach((button) => {
                                if (reported[button]) {
                                    return;
                                }
                                reported[button] = true;
                                if (window.console && window.console.warn) {
                                    window.console.warn("[wpg-ppcp] " + context + " preview: " + button + " not drawn (" + skipped[button] + ")");
                                }
                            });
                        };
                        // The frame lives in the editor canvas, which is itself a frame, so
                        // the message can arrive in either window depending on the editor.
                        const windows = [window];
                        const canvas = frameRef.current && frameRef.current.ownerDocument.defaultView;
                        if (canvas && canvas !== window) {
                            windows.push(canvas);
                        }
                        windows.forEach((w) => w.addEventListener("message", onMessage));
                        return () => windows.forEach((w) => w.removeEventListener("message", onMessage));
                    }, []);

                    const buttons = [];
                    if (enabled.paypal) {
                        buttons.push("paypal");
                    }
                    if (enabled.google) {
                        buttons.push("googlepay");
                    }
                    if (enabled.apple) {
                        buttons.push("applepay");
                    }
                    if (!preview_data.url || buttons.length === 0) {
                        return null;
                    }
                    const heights = preview_data.heights || {};
                    const startingHeight = parseInt(heights[context], 10) > 0 ? parseInt(heights[context], 10) : 40;

                    return createElement("iframe", {
                        ref: frameRef,
                        className: "wpg-ppcp-editor-preview " + context,
                        title: Object(c.__)("PayPal button preview", "woo-paypal-gateway"),
                        src: preview_data.url + "&context=" + encodeURIComponent(context) + "&buttons=" + encodeURIComponent(buttons.join(",")),
                        scrolling: "no",
                        style: {
                            display: "block",
                            width: "100%",
                            height: (height || startingHeight) + "px",
                            border: "0",
                            overflow: "hidden",
                            // A real button here is still only a picture: nothing in the
                            // editor can be clicked through to PayPal.
                            pointerEvents: "none"
                        }
                    });
                };

                const Content_PPCP_Smart_Button_Checkout_Top = (props) => {
                    const {billing, shippingData} = props;

                    useEffect(() => {
                        document.body.dispatchEvent(new CustomEvent("ppcp_checkout_updated", { bubbles: true }));
                        if (typeof jQuery !== "undefined") {
                            jQuery(document.body).trigger("ppcp_checkout_updated");
                        }
                    }, []);

                    const isGooglePayEnabled = l.is_google_pay_enable_for_express_checkout === 'yes';
                    const isApplePayEnabled = l.is_apple_pay_enable_for_express_checkout === 'yes';
                    const isCheckoutButtonTopEnabled = ppcp_settings.enable_checkout_button_top === 'yes';

                    return [
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
                                    "data-context": "express_checkout"
                                }),
                        isApplePayEnabled &&
                                createElement("div", {
                                    key: "apple_pay_button",
                                    className: "apple-pay-container express_checkout " + device_class,
                                    style: {height: "40px"},
                                    "data-context": "express_checkout"
                                })
                    ];

                };
                const Content_PPCP_Smart_Button_Cart_Bottom = (props) => {
                    const {billing, shippingData} = props;
                    useEffect(() => {
                        document.body.dispatchEvent(new CustomEvent("ppcp_checkout_updated", { bubbles: true }));
                        if (typeof jQuery !== "undefined") {
                            jQuery(document.body).trigger("ppcp_checkout_updated");
                        }
                    }, []);

                    const isGooglePayEnabledForCart = l.is_google_pay_enable_for_cart === 'yes';
                    const isApplePayEnabledForCart = l.is_apple_pay_enable_for_cart === 'yes';
                    const showCartButton = ppcp_settings.show_on_cart === 'yes';

                    return [
                        showCartButton && createElement("div", {
                            key: "ppcp_cart",
                            id: "ppcp_cart",
                            className: button_class
                        }),
                        isGooglePayEnabledForCart && createElement("div", {
                            key: "gpay_cart",
                            className: "google-pay-container cart " + button_class,
                            style: {height: "48px"},
                            "data-context": "cart"
                        }),
                        isApplePayEnabledForCart && createElement("div", {
                            key: "apay_cart",
                            className: "apple-pay-container cart " + button_class,
                            style: {height: "48px"},
                            "data-context": "cart"
                        })
                    ];
                };
                const ContentPPCPCheckout = (props) => {
                    const {billing, shippingData, eventRegistration, emitResponse, ...i} = props;
                    const {onPaymentSetup} = eventRegistration || {};
                    useEffect(() => {
                        document.body.dispatchEvent(new CustomEvent("ppcp_checkout_updated", { bubbles: true }));
                        if (typeof jQuery !== "undefined") {
                            jQuery(document.body).trigger("ppcp_checkout_updated");
                        }
                    }, []);
                    useEffect(() => {
                        if (!onPaymentSetup || typeof window.wpg_get_recaptcha_token !== 'function') {
                            return;
                        }
                        const unsubscribe = onPaymentSetup(async () => {
                            var token = await window.wpg_get_recaptcha_token('checkout');
                            return {
                                type: emitResponse.responseTypes.SUCCESS,
                                meta: { paymentMethodData: { wpg_recaptcha_token: token || '' } }
                            };
                        });
                        return () => unsubscribe();
                    }, [onPaymentSetup, emitResponse]);
                    if (l.is_order_confirm_page === 'yes') {
                        return null; // empty element
                      }
                    if (l.use_place_order === true) {
                        if (l.show_redirect_icon === 'yes') {
                            return createElement(
                                    "div",
                                    {className: "ppcp_checkout_parent"},
                                    createElement("input", {type: "hidden", name: "form", value: "checkout"}),
                                    createElement(
                                            "div",
                                            {className: "wc_ppcp_wpg_container"},
                                            l.redirect_icon && createElement("img", {
                                                src: l.redirect_icon,
                                                alt: "PayPal"
                                            }),
                                            createElement(
                                                    "p",
                                                    null,
                                                    l.placeOrderDescription || ''
                                                    )
                                            )
                                    );
                        } else if (l.show_redirect_icon === 'no') {
                            return createElement(
                                    "div",
                                    {className: "ppcp_checkout_parent"},
                                    createElement("input", {type: "hidden", name: "form", value: "checkout"}),
                                    createElement(
                                            "p",
                                            null,
                                            l.description || ''
                                            )
                                    );
                        }
                    }


                    const isGooglePayEnabledForCheckout = l.is_google_pay_enable_for_checkout === 'yes';
                    const isApplePayEnabledForCheckout = l.is_apple_pay_enable_for_checkout === 'yes';
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
                const Edit_PPCP_Smart_Button_Checkout_Top = () => createElement(WPGButtonPreview, {context: "express_checkout"});
                const Edit_PPCP_Smart_Button_Cart_Bottom = () => createElement(WPGButtonPreview, {context: "cart"});
                const EditPPCPCheckout = (props) => {
                    // With "use place order" the front-end content is text the editor can
                    // render as-is; otherwise it is the SDK button container, which needs
                    // the static preview.
                    if (l.use_place_order === true) {
                        return createElement(ContentPPCPCheckout, props);
                    }
                    return createElement(WPGButtonPreview, {context: "checkout"});
                };

                const s = {
                    name: "wpg_paypal_checkout",
                    label: createElement("span", {style: {width: "100%"}}, l.title, createElement("img", {src: l.icons, style: {float: "right", marginLeft: "20px", display: "flex", justifyContent: "flex-end", paddingRight: "10px"}})),
                    placeOrderButtonLabel: Object(c.__)(l.placeOrderButtonLabel),
                    content: createElement(ContentPPCPCheckout, null),
                    edit: createElement(EditPPCPCheckout, null),
                    // The block method is only registered when the gateway is available
                    // server-side (is_active() -> gateway->is_available(): credentials set,
                    // enabled), so PayPal is genuinely usable whenever this runs. Keep the
                    // default true so the primary method is never hidden on a working store,
                    // but guard on the localized data being present and let a store add its
                    // own eligibility (e.g. by currency/country) via a global override
                    // instead of always claiming availability.
                    canMakePayment: () => {
                        try {
                            if (!l) {
                                return Promise.resolve(false);
                            }
                            if (typeof window.wpgPPCPCanMakePayment === 'function') {
                                return Promise.resolve(!!window.wpgPPCPCanMakePayment(l));
                            }
                        } catch (e) {}
                        return Promise.resolve(true);
                    },
                    ariaLabel: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                    supports: {
                        features: l.supports || [],
                        showSavedCards: false,
                        showSaveOption: false
                    }
                };
                Object(r.registerPaymentMethod)(s);


                const {is_order_confirm_page, is_paylater_enable_incart_page, page} = l;

                if (page === "checkout" && is_order_confirm_page === "no" && ppcp_settings && (ppcp_settings.enable_checkout_button_top === "yes" || l.is_google_pay_enable_for_express_checkout === 'yes' || l.is_apple_pay_enable_for_express_checkout === 'yes')) {
                    const commonExpressPaymentMethodConfig = {
                        name: "wpg_paypal_checkout_top",
                        label: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        content: createElement(Content_PPCP_Smart_Button_Checkout_Top, null),
                        edit: createElement(Edit_PPCP_Smart_Button_Checkout_Top, null),
                        ariaLabel: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        canMakePayment: () => true,
                        paymentMethodId: "wpg_paypal_checkout",
                        supports: {features: l.supports || []}
                    };
                    Object(r.registerExpressPaymentMethod)(commonExpressPaymentMethodConfig);
                } else if (page === "cart" && ppcp_settings && (ppcp_settings.show_on_cart === "yes" || l.is_google_pay_enable_for_cart === 'yes' || l.is_apple_pay_enable_for_cart === 'yes')) {
                    const commonExpressPaymentMethodConfig = {
                        name: "wpg_paypal_cart_bottom",
                        label: Object(u.decodeEntities)(l.title || Object(c.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                        content: createElement(Content_PPCP_Smart_Button_Cart_Bottom, null),
                        edit: createElement(Edit_PPCP_Smart_Button_Cart_Bottom, null),
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
    document.body.dispatchEvent(new CustomEvent("ppcp_block_ready", { bubbles: true }));
    if (typeof jQuery !== "undefined") {
        jQuery(document.body).trigger("ppcp_block_ready");
    }
});

const ppcp_uniqueEvents = new Set([
    "experimental__woocommerce_blocks-checkout-set-active-payment-method",
]);

ppcp_uniqueEvents.forEach(function (action) {
    addAction(action, "c", function () {
        document.body.dispatchEvent(new CustomEvent("ppcp_checkout_updated", { bubbles: true }));
        if (typeof jQuery !== "undefined") {
            jQuery(document.body).trigger("ppcp_checkout_updated");
        }
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

document.body.addEventListener('ppcp_checkout_error', function (event) {
    if (event.detail) {
        showErrorUsingShowNotice(event.detail);
    }
});

if (typeof jQuery !== "undefined") {
    jQuery(document.body).on('ppcp_checkout_error', function (event, errorMessages) {
        showErrorUsingShowNotice(errorMessages);
    });
}
/**
 * Store API freshness observer (audit P13).
 *
 * The server extends every Store API cart response with fresh plugin state
 * under extensions.wpg_ppcp (see PPCP_Store_API_Extension). This observer
 * watches the cart data store and, whenever that payload changes, mirrors it
 * to window.wpgPPCPStoreApiCart and fires the `ppcp_store_api_updated` jQuery
 * event with the fresh data.
 *
 * Purely passive: it rewires no existing flow, so behaviour is unchanged
 * unless a script explicitly consumes the global or the event. Guarded so it
 * silently no-ops when wp.data or the cart store is unavailable.
 */
(function () {
    'use strict';
    if (typeof wp === 'undefined' || !wp.data || typeof wp.data.subscribe !== 'function') {
        return;
    }
    var CART_STORE = 'wc/store/cart';
    var lastSerialized = null;
    function readExtension() {
        try {
            var select = wp.data.select(CART_STORE);
            if (!select || typeof select.getCartData !== 'function') {
                return null;
            }
            var cart = select.getCartData();
            if (!cart || !cart.extensions || !cart.extensions.wpg_ppcp) {
                return null;
            }
            return cart.extensions.wpg_ppcp;
        } catch (e) {
            return null;
        }
    }
    wp.data.subscribe(function () {
        var data = readExtension();
        if (data === null) {
            return;
        }
        var serialized;
        try {
            serialized = JSON.stringify(data);
        } catch (e) {
            return;
        }
        if (serialized === lastSerialized) {
            return;
        }
        lastSerialized = serialized;
        window.wpgPPCPStoreApiCart = data;
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).trigger('ppcp_store_api_updated', [data]);
        }
        try {
            document.body.dispatchEvent(new CustomEvent('ppcp_store_api_updated', {bubbles: true, detail: data}));
        } catch (e) {}
    });
})();
