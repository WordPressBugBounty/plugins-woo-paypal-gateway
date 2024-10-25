(function ($) {
    class PPCPManager {
        constructor(ppcp_manager) {
            this.ppcp_manager = ppcp_manager;
            this.productAddToCart = true;
            this.lastApiResponse = null;
            this.ppcp_address = [];
            this.init();
        }

        init() {
            if (typeof this.ppcp_manager === 'undefined') return false;
            this.setupVariations();
            this.renderSmartButton();
            this.bindCheckoutEvents();
        }

        getAddress(prefix) {
            const fields = {
                addressLine1: jQuery(`#${prefix}_address_1`).val(),
                addressLine2: jQuery(`#${prefix}_address_2`).val(),
                adminArea1: jQuery(`#${prefix}_state`).val(),
                adminArea2: jQuery(`#${prefix}_city`).val(),
                postalCode: jQuery(`#${prefix}_postcode`).val(),
                countryCode: jQuery(`#${prefix}_country`).val(),
                firstName: jQuery(`#${prefix}_first_name`).val(),
                lastName: jQuery(`#${prefix}_last_name`).val(),
                email: jQuery(`#${prefix}_email`).val()
            };
            fields.phoneNumber = prefix === 'billing' ? jQuery('#billing-phone').val() || jQuery('#shipping-phone').val() : jQuery('#shipping-phone').val() || jQuery('#billing-phone').val();

            if (!fields.addressLine1) {
                const customerData = wp.data.select('wc/store/cart').getCustomerData();
                const { billingAddress, shippingAddress } = customerData;
                const addressData = (prefix === 'billing') ? billingAddress : shippingAddress;
                Object.assign(fields, {
                    addressLine1: addressData.address_1,
                    addressLine2: addressData.address_2,
                    adminArea1: addressData.state,
                    adminArea2: addressData.city,
                    postalCode: addressData.postcode,
                    countryCode: addressData.country,
                    firstName: addressData.first_name,
                    lastName: addressData.last_name,
                    email: prefix === 'billing' ? billingAddress.email || shippingAddress.email : shippingAddress.email || billingAddress.email
                });
            }

            return {
                [`${prefix}_address_1`]: fields.addressLine1 || '',
                [`${prefix}_address_2`]: fields.addressLine2 || '',
                [`${prefix}_state`]: fields.adminArea1 || '',
                [`${prefix}_city`]: fields.adminArea2 || '',
                [`${prefix}_postcode`]: fields.postalCode || '',
                [`${prefix}_country`]: fields.countryCode || '',
                [`${prefix}_first_name`]: fields.firstName || '',
                [`${prefix}_last_name`]: fields.lastName || '',
                [`${prefix}_email`]: fields.email || '',
                [`${prefix}_phone`]: fields.phoneNumber || ''
            };
        }

        getValidAddress(prefix) {
            const address = this.getAddress(prefix);
            return this.isValidAddress(prefix, address) ? address : this.getAddress(prefix === 'billing' ? 'shipping' : 'billing');
        }

        getBillingAddress() {
            return this.getValidAddress('billing');
        }

        getShippingAddress() {
            return this.getValidAddress('shipping');
        }

        isValidAddress(prefix, address) {
            return address && address[`${prefix}_address_1`];
        }

        isCheckoutPage() {
            return this.ppcp_manager.page === 'checkout';
        }

        isProductPage() {
            return this.ppcp_manager.page === 'product';
        }

        isCartPage() {
            return this.ppcp_manager.page === 'cart';
        }

        isSale() {
            return this.ppcp_manager.paymentaction === 'capture';
        }

        setupVariations() {
            const selector = this.ppcp_manager.button_selector;
            if ($('.variations_form').length) {
                $('.variations_form').on('show_variation', () => $(selector).show()).on('hide_variation', () => $(selector).hide());
            }
        }

        bindCheckoutEvents() {
            $('form.checkout').on('checkout_place_order_wpg_paypal_checkout_cc', (event) => {
                event.preventDefault();
                return this.handleCheckoutSubmit(event);
            });
            $(document.body).on('updated_cart_totals updated_checkout ppcp_block_ready ppcp_checkout_updated', () => this.update_paypal_checkout());
            $(document.body).on('updated_cart_totals updated_checkout ppcp_cc_block_ready ppcp_cc_updated', () => this.update_paypal_cc());
            $('form.checkout').on('click', 'input[name="payment_method"]', () => this.togglePlaceOrderButton());
        }

        handleCheckoutSubmit() {
            if (this.isPpcpCCSelected() && this.isCardFieldEligible()) {
                if ($('form.checkout').hasClass('paypal_cc_submitting')) return false;
                $('form.checkout').addClass('paypal_cc_submitting');
                $(document.body).trigger('submit_paypal_cc_form');
                return false;
            }
            return true;
        }

        update_paypal_checkout() {
            this.renderSmartButton();
            this.togglePlaceOrderButton();
        }

        update_paypal_cc() {
            if(this.isCardFieldEligible()) {
                this.renderCardFields();
                $('#place_order, .wc-block-components-checkout-place-order-button').show();
            } else {
                $('.wc_payment_method.payment_method_wpg_paypal_checkout_cc').hide();
                $('#radio-control-wc-payment-method-options-wpg_paypal_checkout_cc').parent('label').parent('div').hide();
                if (this.isPpcpCCSelected()) $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
            }
            this.togglePlaceOrderButton();
        }

        isPpcpSelected() {
            return $('#payment_method_wpg_paypal_checkout').is(':checked') || $('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'wpg_paypal_checkout';
        }

        isPpcpCCSelected() {
            return $('#payment_method_wpg_paypal_checkout_cc').is(':checked') || $('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'wpg_paypal_checkout_cc';
        }

        isCardFieldEligible() {
            return this.isCheckoutPage() && this.ppcp_manager.advanced_card_payments === 'yes' && typeof paypal !== 'undefined' && paypal.CardFields().isEligible();
        }

        togglePlaceOrderButton() {
            const isPpcpSelected = this.isPpcpSelected();
            const isPpcpCCSelected = this.isPpcpCCSelected();
            if (isPpcpSelected) {
                 $('#place_order, .wc-block-components-checkout-place-order-button').hide();
            } else {
                $('#place_order, .wc-block-components-checkout-place-order-button').show();
            }
            if (isPpcpCCSelected) {
                if(this.isCardFieldEligible()) {
                    $('#place_order, .wc-block-components-checkout-place-order-button').show();
                } else {
                    
                }
            } 
        }

        renderSmartButton() {
            const selectors = this.ppcp_manager.button_selector;
            $.each(selectors, (key, selector) => {
                if (!$(selector).length || $(selector).children().length || typeof paypal === 'undefined') return;
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
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data;
            if (selector === '#ppcp_checkout_top') {
                // Handle specific logic here
            } else if (this.isCheckoutPage()) {
                data = $(selector).closest('form').serialize();
                if (typeof wpg_paypal_checkout_manager_block !== 'undefined' && wpg_paypal_checkout_manager_block.is_block_enable === 'yes') {
                    const billingAddress = this.getBillingAddress();
                    const shippingAddress = this.getShippingAddress();
                    data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                    data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                    data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                }
            } else if (this.isProductPage()) {
                $('<input>', { type: 'hidden', name: 'ppcp-add-to-cart', value: $("[name='add-to-cart']").val() }).appendTo('form.cart');
                data = $('form.cart').serialize();
            } else {
                data = $('form.woocommerce-cart-form').serialize();
            }
            return fetch(this.ppcp_manager.create_order_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            }).then(res => res.json()).then(data => {
                if (data.success !== undefined) {
                    this.showError(data.data.messages);
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

        showSpinner(containerSelector = '.woocommerce') {
            if (jQuery('.wc-block-checkout__main').length || jQuery('.wp-block-woocommerce-cart').length) {
                jQuery('.wc-block-checkout__main, .wp-block-woocommerce-cart').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
            } else if (jQuery(containerSelector).length) {
                jQuery(containerSelector).block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
            }
        }

        hideSpinner(containerSelector = '.woocommerce') {
            if (jQuery('.wc-block-checkout__main').length || jQuery('.wp-block-woocommerce-cart').length) {
                jQuery('.wc-block-checkout__main, .wp-block-woocommerce-cart').unblock();
            } else if (jQuery(containerSelector).length) {
                jQuery(containerSelector).unblock();
            }
        }

        onCancelHandler() {
            this.hideSpinner();
        }

        onErrorHandler(err) {
            this.hideSpinner();
        }

        showError(error_message) {
            const $checkout_form = $('form.checkout');
            if ($checkout_form.length) {
                $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
                let errorHTML = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert">';
                $.each(error_message, (index, value) => { errorHTML += `<li>${value}</li>`; });
                errorHTML += '</ul></div>';
                $checkout_form.prepend(errorHTML).removeClass('processing').unblock();
                $checkout_form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
                const scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout').length ? $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout') : $checkout_form;
                $('html, body').animate({ scrollTop: scrollElement.offset().top - 100 }, 1000);
                $(document.body).trigger('checkout_error', [error_message]);
            } else {
                const errorMessagesString = error_message.join("<br>");
                $(document.body).trigger('ppcp_checkout_error', errorMessagesString);
            }
        }

        renderCardFields() {
            const checkoutSelector = this.getCheckoutSelectorCss();
            if ($(checkoutSelector).is('.CardFields') || $('#wpg_paypal_checkout_cc-card-number').length === 0 || typeof paypal === 'undefined') return;
            $(checkoutSelector).addClass('CardFields');
            const cardFields = paypal.CardFields({
                createOrder: () => this.createCardOrder(checkoutSelector),
                onApprove: (payload) => payload && payload.orderID ? this.submitCardFields(payload) : console.error("No valid payload returned during onApprove:", payload),
                fields: {
                    number: { selector: '#wpg_paypal_checkout_cc-card-number', placeholder: '•••• •••• •••• ••••' },
                    cvv: { selector: '#wpg_paypal_checkout_cc-card-cvc', placeholder: 'CVC' },
                    expirationDate: { selector: '#wpg_paypal_checkout_cc-card-expiry', placeholder: 'MM / YY' }
                },
                onError: (err) => { this.hideSpinner(); console.error("Error during card field rendering:", err); this.handleCardFieldsError(err, checkoutSelector); }
            });
            if (cardFields.isEligible()) {
                cardFields.NumberField().render("#wpg_paypal_checkout_cc-card-number");
                cardFields.ExpiryField().render("#wpg_paypal_checkout_cc-card-expiry");
                cardFields.CVVField().render("#wpg_paypal_checkout_cc-card-cvc");
            } else {
                $('.payment_box.payment_method_wpg_paypal_checkout_cc').hide();
                if (this.isPpcpCCSelected()) $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
            }

            $(document.body).on('submit_paypal_cc_form', () => cardFields.submit());
        }

        createCardOrder(checkoutSelector) {
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data;
            if (typeof wpg_paypal_checkout_manager_block !== 'undefined' && wpg_paypal_checkout_manager_block.is_block_enable === 'yes') {
                data = $('form.wc-block-checkout__form').serialize();
                const billingAddress = this.getBillingAddress();
                const shippingAddress = this.getShippingAddress();
                data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
            } else {
                data = $(checkoutSelector).closest('form').serialize();
            }
            return fetch(this.ppcp_manager.create_order_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            }).then(res => res.json()).then(data => {
                if (data.success !== undefined) {
                    this.showError(data.data.messages);
                    return '';
                }
                return data.orderID;
            });
        }

        submitCardFields(payload) {
            $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${payload.orderID}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, (data) => {
                window.location.href = data.data.redirect;
            });
        }

        handleCardFieldsError(error, checkoutSelector) {
            $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
            $(checkoutSelector).removeClass('processing paypal_cc_submitting CardFields createOrder').unblock();
            error.details && error.details[0].description ? error.details[0].description : error.message;
            this.hideSpinner();
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

    $(function () {
        window.PPCPManager = PPCPManager;
        const ppcp_manager = window.ppcp_manager || {};
        new PPCPManager(ppcp_manager);
    });
})(jQuery);
