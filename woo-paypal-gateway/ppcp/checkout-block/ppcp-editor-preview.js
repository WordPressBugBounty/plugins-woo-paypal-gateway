/**
 * Draws the merchant's real buttons on the block editor's preview page.
 *
 * This runs inside the small same-origin page PPCP_Editor_Button_Preview serves,
 * not in the editor itself: the editor canvas is a srcdoc iframe with no
 * window.location.host, and the PayPal SDK refuses to bootstrap in such a
 * document. Here it is an ordinary page, so the SDK behaves as it does on the
 * front end.
 *
 * The page renders the same containers as the front end -- #ppcp_checkout_top and
 * its alternative, #ppcp_cart, #ppcp_checkout, the wallet containers -- and loads
 * the same stylesheet, so the buttons are laid out by exactly the same rules. This
 * file fills them the same way the public script does: for the express placement,
 * one funding source per container, because that is how the front end reaches two
 * buttons side by side at the width the stylesheet gives them.
 *
 * The buttons are given no createOrder callback and the editor makes the frame
 * inert, so nothing drawn here can start a payment. A wallet is drawn only where
 * the shopper would get it: Google Pay behind isReadyToPay and Apple Pay behind
 * canMakePayments. What was drawn, and why anything was not, goes back to the
 * editor with the height so the placement is sized to it and the reason is visible
 * in the console rather than looking like a button that silently went missing.
 */
(function () {
    'use strict';

    var config = window.wpgPPCPPreview || {};
    var root = document.getElementById('wpg-ppcp-preview');
    if (!root) {
        return;
    }

    var style = config.style || {};
    var buttons = config.buttons || [];
    var context = config.context;
    var mobile = config.device === 'mobile';
    var height = parseInt(style.height, 10) > 0 ? parseInt(style.height, 10) : 40;
    var drawn = [];
    var skipped = {};

    function radius(shape) {
        return shape === 'pill' ? Math.round(height / 2) : 4;
    }

    function find(selector) {
        return root.querySelector(selector);
    }

    /**
     * The containers start as shimmering skeletons, exactly as they do on the front
     * end; the public script clears that once a button is in place, and so does this.
     */
    function clearSkeleton(container) {
        if (!container) {
            return;
        }
        container.style.background = '';
        container.style.backgroundColor = '';
        container.style.setProperty('--wpg-skel-fallback-bg', 'transparent');
        container.classList.add('bg-cleared');
    }

    function sizeContainer(container) {
        if (container) {
            // The front end shaves a pixel off so the button sits inside its container.
            container.style.setProperty('--button-height', Math.max(0, height - 1) + 'px');
        }
    }

    /**
     * A container that will not be filled is taken out, exactly as the public script
     * takes out a wallet container it cannot draw into. Leaving it behind would show
     * an empty skeleton, and -- because these containers share the row -- would squeeze
     * the buttons beside it narrower than the stylesheet intends, which PayPal answers
     * by drawing a shorter button.
     */
    function removeContainer(selector) {
        var container = find(selector);
        if (container && container.parentNode) {
            container.parentNode.removeChild(container);
        }
    }

    var CONTAINERS = {
        paypal: ['#ppcp_checkout_top', '#ppcp_checkout_top_alternative', '#ppcp_cart', '#ppcp_checkout'],
        googlepay: ['.google-pay-container'],
        applepay: ['.apple-pay-container']
    };

    function skip(button, reason) {
        skipped[button] = reason;
        (CONTAINERS[button] || []).forEach(removeContainer);
        if (window.console && window.console.warn) {
            window.console.warn('[wpg-ppcp] editor preview: ' + button + ' not drawn (' + reason + ')');
        }
        report();
    }

    function drew(button) {
        if (drawn.indexOf(button) === -1) {
            drawn.push(button);
        }
        report();
    }

    /**
     * Tell the editor how tall the drawn buttons ended up, and what it is looking
     * at. The editor's own document is one frame further up than this page's parent
     * when the canvas is iframed, so tell both; the editor ignores messages from
     * anything else.
     */
    function report() {
        var message = {
            type: 'wpg-ppcp-preview-height',
            context: context,
            height: Math.ceil(Math.max(
                root.getBoundingClientRect().height,
                document.body ? document.body.scrollHeight : 0
            )),
            drawn: drawn,
            skipped: skipped
        };
        [window.parent, window.top].forEach(function (target) {
            try {
                if (target && target !== window) {
                    target.postMessage(message, '*');
                }
            } catch (e) {}
        });
    }

    /** The style the front end hands PayPal for this placement. */
    function paypalStyle() {
        var built = {
            layout: style.layout === 'vertical' ? 'vertical' : 'horizontal',
            color: style.color || 'gold',
            shape: style.shape || 'rect',
            label: style.label || 'paypal',
            height: height
        };
        // Side by side, PayPal adds a strapline of its own ("Two easy ways to pay")
        // unless told not to. The front end turns it off, so the editor must too.
        if (built.layout === 'horizontal') {
            built.tagline = 'false';
        }
        return built;
    }

    /**
     * The express placement, drawn the way the front end draws it: one funding source
     * per container, in the same order, stopping at the two containers there are. The
     * layout is dropped, because a container holding a single funding source has none.
     */
    function drawExpressButtons(paypal) {
        var sources = [paypal.FUNDING.PAYPAL, paypal.FUNDING.VENMO, paypal.FUNDING.PAYLATER, paypal.FUNDING.CREDIT];
        var containers = [find('#ppcp_checkout_top'), find('#ppcp_checkout_top_alternative')];
        var base = paypalStyle();
        delete base.layout;
        var rendered = 0;
        var pending = [];

        sources.forEach(function (fundingSource) {
            if (rendered >= containers.length) {
                return;
            }
            var container = containers[rendered];
            if (!container) {
                return;
            }
            var buttonStyle = Object.assign({}, base);
            if (fundingSource === paypal.FUNDING.VENMO) {
                buttonStyle.color = 'blue';
            } else if (fundingSource === paypal.FUNDING.CREDIT) {
                buttonStyle.color = 'darkblue';
            }
            var button = paypal.Buttons({style: buttonStyle, fundingSource: fundingSource});
            if (typeof button.isEligible === 'function' && !button.isEligible()) {
                return;
            }
            rendered += 1;
            sizeContainer(container);
            pending.push(Promise.resolve(button.render(container)).then(function () {
                clearSkeleton(container);
            }));
        });

        if (!rendered) {
            skip('paypal', 'no eligible funding source for this account');
            return;
        }
        // With only one funding source there is nothing for the second container to
        // hold, so the front end drops it and lets the first one spread out.
        if (rendered < containers.length && containers[1]) {
            if (!find('.google-pay-container') && !find('.apple-pay-container') && !mobile && containers[0]) {
                containers[0].style.minWidth = '480px';
            }
            containers[1].parentNode.removeChild(containers[1]);
        }
        Promise.all(pending).then(function () {
            drew('paypal');
        }, function (err) {
            skip('paypal', 'render failed: ' + (err && err.message ? err.message : err));
        });
    }

    /** Every other placement is one container holding the whole stack. */
    function drawStackedButtons(paypal, container) {
        var button = paypal.Buttons({style: paypalStyle()});
        if (typeof button.isEligible === 'function' && !button.isEligible()) {
            skip('paypal', 'no eligible funding source for this account');
            return;
        }
        sizeContainer(container);
        Promise.resolve(button.render(container)).then(function () {
            clearSkeleton(container);
            drew('paypal');
        }, function (err) {
            skip('paypal', 'render failed: ' + (err && err.message ? err.message : err));
        });
    }

    if (buttons.indexOf('paypal') !== -1) {
        if (!window.paypal || typeof window.paypal.Buttons !== 'function') {
            skip('paypal', 'the PayPal SDK did not load');
        } else {
            try {
                if (context === 'express_checkout') {
                    drawExpressButtons(window.paypal);
                } else {
                    drawStackedButtons(window.paypal, find('#ppcp_cart') || find('#ppcp_checkout'));
                }
            } catch (e) {
                skip('paypal', 'render threw: ' + (e && e.message ? e.message : e));
            }
        }
    }

    if (buttons.indexOf('googlepay') !== -1) {
        var googleContainer = find('.google-pay-container');
        if (!window.google || !window.google.payments || !window.google.payments.api) {
            skip('googlepay', 'the Google Pay SDK did not load');
        } else {
            var wallet = config.google || {};
            var client = new window.google.payments.api.PaymentsClient({
                environment: config.environment === 'TEST' ? 'TEST' : 'PRODUCTION'
            });
            // The same question the front end asks before it draws the button: can
            // this browser pay with Google Pay at all?
            client.isReadyToPay({
                apiVersion: 2,
                apiVersionMinor: 0,
                allowedPaymentMethods: [{
                    type: 'CARD',
                    parameters: {
                        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                        allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA']
                    }
                }]
            }).then(function (response) {
                if (!response || !response.result) {
                    skip('googlepay', 'this browser cannot pay with Google Pay');
                    return;
                }
                googleContainer.appendChild(client.createButton({
                    buttonColor: wallet.color || 'black',
                    buttonType: wallet.label || 'plain',
                    buttonRadius: radius(wallet.shape),
                    buttonSizeMode: 'fill',
                    onClick: function () {}
                }));
                clearSkeleton(googleContainer);
                drew('googlepay');
            }, function (err) {
                skip('googlepay', 'isReadyToPay failed: ' + (err && err.message ? err.message : err));
            });
        }
    }

    if (buttons.indexOf('applepay') !== -1) {
        var appleContainer = find('.apple-pay-container');
        // Apple's button only draws in Safari, and only where the device can pay.
        if (!window.ApplePaySession || typeof window.ApplePaySession.canMakePayments !== 'function') {
            skip('applepay', 'this browser has no Apple Pay');
        } else if (!window.ApplePaySession.canMakePayments()) {
            skip('applepay', 'this device cannot pay with Apple Pay');
        } else {
            var appleStyle = config.apple || {};
            var appleButton = document.createElement('apple-pay-button');
            appleButton.setAttribute('type', appleStyle.label || 'plain');
            appleButton.setAttribute('buttonstyle', appleStyle.color || 'black');
            appleContainer.classList.add(appleStyle.shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect');
            appleContainer.style.setProperty('--button-height', height + 'px');
            appleContainer.style.setProperty('--button-radius', radius(appleStyle.shape) + 'px');
            appleContainer.appendChild(appleButton);
            clearSkeleton(appleContainer);
            drew('applepay');
        }
    }

    report();
    if (typeof window.ResizeObserver === 'function') {
        new window.ResizeObserver(report).observe(root);
    } else {
        window.setTimeout(report, 1000);
    }
})();
