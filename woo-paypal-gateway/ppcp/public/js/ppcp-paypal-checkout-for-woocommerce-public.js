class PPCPManager {
    constructor(ppcp_manager) {
        console.log('3');
        this.ppcp_manager = ppcp_manager;
        this.productAddToCart = true;
        this.lastApiResponse = null;
        this.ppcp_address = [];
        this.init();
    }

    init() {
        console.log('12');
        if (typeof this.ppcp_manager === 'undefined') {
            return false;
        }

        // Initialize button render, event listeners, etc.
        this.setupVariations();
        this.renderSmartButton();
        this.bindCheckoutEvents();
    }

    isCheckoutPage() {
        return 'checkout' === this.ppcp_manager.page;
    }

    isProductPage() {
        return 'product' === this.ppcp_manager.page;
    }

    isCartPage() {
        return 'cart' === this.ppcp_manager.page;
    }

    isSale() {
        return 'capture' === this.ppcp_manager.paymentaction;
    }

    setupVariations() {
        const selector = this.ppcp_manager.button_selector;

        if ($('.variations_form').length) {
            $('.variations_form').on('show_variation', () => {
                $(selector).show();
            }).on('hide_variation', () => {
                $(selector).hide();
            });
        }
    }

    bindCheckoutEvents() {
        $('form.checkout').on('checkout_place_order_wpg_paypal_checkout', (event) => this.handleCheckoutSubmit(event));
        $(document.body).on('updated_cart_totals updated_checkout ppcp_block_ready ppcp_checkout_updated', () => this.handleCartUpdate());
        $('form.checkout').on('click', 'input[name="payment_method"]', () => this.togglePlaceOrderButton());
    }

    handleCheckoutSubmit(event) {
        if (this.isPpcpSelected()) {
            if (this.isHostedFieldEligible()) {
                event.preventDefault();
                if ($('form.checkout').hasClass('paypal_cc_submitting')) {
                    return false;
                } else {
                    $('form.checkout').addClass('paypal_cc_submitting');
                    $(document.body).trigger('submit_paypal_cc_form');
                }
                return false;
            }
        }
        return true;
    }

    handleCartUpdate() {
        console.log('72');
        this.togglePlaceOrderButton();
        setTimeout(() => {
            this.renderSmartButton();
            if (this.isHostedFieldEligible()) {
                $('.checkout_cc_separator').show();
                $('#wc-wpg_paypal_checkout-cc-form').show();
                this.renderHostedFields();
            } else {
                $('.checkout_cc_separator').hide();
                $('#wc-wpg_paypal_checkout-cc-form').hide();
            }
        }, 300);
    }

    isPpcpSelected() {
        return $('#payment_method_wpg_paypal_checkout').is(':checked');
    }

    isHostedFieldEligible() {
        if (this.isCheckoutPage() && this.ppcp_manager.advanced_card_payments === 'yes') {
            return typeof paypal !== 'undefined' && paypal.HostedFields.isEligible();
        }
        return false;
    }

    togglePlaceOrderButton() {
        if (this.isHostedFieldEligible()) {
            $('#place_order').show();
        } else {
            this.isPpcpSelected() ? $('#place_order').hide() : $('#place_order').show();
        }
    }

    renderSmartButton() {
        const selectors = this.ppcp_manager.button_selector;
        console.log('107');
        $.each(selectors, (key, selector) => {
            if (!$(selector).length || $(selector).children().length) return;

            if (typeof paypal === 'undefined') return false;

            const ppcpStyle = {
                layout: this.ppcp_manager.style_layout,
                color: this.ppcp_manager.style_color,
                shape: this.ppcp_manager.style_shape,
                label: this.ppcp_manager.style_label
            };

            paypal.Buttons({
                style: ppcpStyle,
                createOrder: () => this.createOrder(selector),
                onApprove: (data, actions) => this.onApproveHandler(data, actions),
                onCancel: () => this.onCancelHandler(),
                onError: (err) => this.onErrorHandler(err)
            }).render(selector);
        });
    }

    createOrder(selector) {
        let data;
        if (this.isCheckoutPage()) {
            data = $(selector).closest('form').serialize();
        } else if (this.isProductPage()) {
            const add_to_cart = $("[name='add-to-cart']").val();
            $('<input>', {
                type: 'hidden',
                name: 'ppcp-add-to-cart',
                value: add_to_cart
            }).appendTo('form.cart');
            data = $('form.cart').serialize();
        } else {
            data = $('form.woocommerce-cart-form').serialize();
        }

        return fetch(this.ppcp_manager.create_order_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        }).then(res => res.json()).then(data => {
            if (data.success !== undefined) {
                this.showError('<ul class="woocommerce-error" role="alert">' + data.data.messages + '</ul>');
                return null;
            }
            return data.orderID;
        });
    }

    onApproveHandler(data, actions) {
        if (this.isCheckoutPage()) {
            $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${data.orderID}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, function (data) {
                window.location.href = data.data.redirect;
            });
        } else {
            actions.redirect(`${this.ppcp_manager.checkout_url}?paypal_order_id=${data.orderID}&paypal_payer_id=${data.payerID}&from=${this.ppcp_manager.page}`);
        }
    }

    onCancelHandler() {
        // Handle cancel logic
    }

    onErrorHandler(err) {
        console.error(err);
        // Handle error logic
    }

    showError(message) {
        let displayError = $('.woocommerce');
        if (!displayError.length) displayError = $('form.checkout');
        displayError.prepend(`<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">${message}</div>`);
        displayError.removeClass('processing').unblock();
    }

    renderHostedFields() {
        let checkoutSelector = this.getCheckoutSelectorCss();
        if ($(checkoutSelector).is('.HostedFields')) {
            return false;
        }
        if (!this.isCCPaymentMethodSelected()) {
            return false;
        }
        if (typeof paypal === 'undefined') {
            return;
        }

        $(checkoutSelector).addClass('HostedFields');
        paypal.HostedFields.render({
            createOrder: () => this.createHostedOrder(checkoutSelector),
            onCancel: (data, actions) => actions.redirect(this.ppcp_manager.cancel_url),
            onError: (err) => console.log(err),
            fields: {
                number: {selector: '#wpg_paypal_checkout-card-number', placeholder: '•••• •••• •••• ••••'},
                cvv: {selector: '#wpg_paypal_checkout-card-cvc', placeholder: 'CVC'},
                expirationDate: {selector: '#wpg_paypal_checkout-card-expiry', placeholder: 'MM / YY'}
            }
        }).then((hf) => this.bindHostedFields(hf, checkoutSelector));
    }

    createHostedOrder(checkoutSelector) {
        let data = this.isCheckoutPage() ? $(checkoutSelector).serialize() : '';

        return fetch(this.ppcp_manager.create_order_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        }).then(res => res.json()).then(data => {
            if (data.success !== undefined) {
                this.showError('<ul class="woocommerce-error" role="alert">' + data.data.messages + '</ul>');
                return '';
            }
            return data.orderID;
        });
    }

    bindHostedFields(hf, checkoutSelector) {
        hf.on('cardTypeChange', (event) => {
            if (event.cards.length === 1) {
                $('#wpg_paypal_checkout-card-number').removeClass().addClass(event.cards[0].type.replace("-", ""));
                $('#wpg_paypal_checkout-card-number').addClass("input-text wc-credit-card-form-card-number hosted-field-braintree braintree-hosted-fields-valid");
            }
        });

        $(document.body).on('submit_paypal_cc_form', (event) => {
            event.preventDefault();
            hf.submit().then(
                (payload) => this.submitHostedFields(payload, checkoutSelector),
                (error) => this.handleHostedFieldsError(error, checkoutSelector)
            );
        });
    }

    submitHostedFields(payload, checkoutSelector) {
        $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${payload.orderId}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, function (data) {
            window.location.href = data.data.redirect;
        });
    }

    handleHostedFieldsError(error, checkoutSelector) {
        $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
        $(checkoutSelector).removeClass('processing paypal_cc_submitting HostedFields createOrder').unblock();
        let errorMessage = error.details && error.details[0].description ? error.details[0].description : error.message;
        if (errorMessage) {
            this.showError(`<ul class="woocommerce-error" role="alert">${errorMessage}</ul>`);
        }
    }

    getCheckoutSelectorCss() {
        return this.isCheckoutPage() ? 'form.checkout' : 'form.cart';
    }

    isCCPaymentMethodSelected() {
        return this.getSelectedPaymentMethod() === 'wpg_paypal_checkout_cc';
    }

    getSelectedPaymentMethod() {
        return $('input[name="payment_method"]:checked').val();
    }
}

// Instantiate the class when the document is ready
jQuery(function ($) {
    const ppcp_manager = window.ppcp_manager || {}; // You should have the manager object loaded from your backend
    new PPCPManager(ppcp_manager);
});
