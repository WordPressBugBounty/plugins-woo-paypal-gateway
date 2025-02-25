(function ($) {
    class PPCPManager {
        constructor(ppcp_manager) {
            this.ppcp_manager = ppcp_manager;
            this.productAddToCart = true;
            this.lastApiResponse = null;
            this.ppcp_address = [];
            this.paymentsClient = null;
            this.allowedPaymentMethods = [];
            this.merchantInfo = null;



            this.init();
            this.ppcp_cart_css();
        }

        init() {
            if (typeof this.ppcp_manager === 'undefined') {
                console.log("PPCP Manager configuration is undefined.");
                return false;
            }
            if (this.ppcp_manager.enabled_google_pay === 'yes') {
                this.loadGooglePaySdk();
            }
            if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                this.loadApplePaySdk();
            }
            this.manageVariations('#ppcp_product, .google-pay-container, .apple-pay-container');
            this.bindCheckoutEvents();
            this.update_paypal_checkout();
            this.debouncedUpdatePaypalCC = this.debounce_cc(this.update_paypal_cc.bind(this), 500);
            this.debouncedUpdatePaypalCheckout = this.debounce(this.update_paypal_checkout.bind(this), 500);
            this.debouncedUpdateGooglePay = this.debounce_google(this.update_google_pay.bind(this), 500);
            this.debouncedUpdateApplePay = this.debounce_apple(this.update_apple_pay.bind(this), 500);
            if (this.isCheckoutPage() === false) {
                this.debouncedUpdatePaypalCheckout();
                this.debouncedUpdateGooglePay();
                this.debouncedUpdateApplePay();
            }
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
                const {billingAddress, shippingAddress} = customerData;
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

        throttle(func, limit) {
            let lastCall = 0;
            return function (...args) {
                const now = Date.now();
                if (now - lastCall >= limit) {
                    lastCall = now;
                    func.apply(this, args);
                }
            };
        }

        debounce(func, delay) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        }

        debounce_google(func, delay) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        }

        debounce_apple(func, delay) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        }

        debounce_cc(func, delay) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        }

        bindCheckoutEvents() {
            $('form.checkout').on('checkout_place_order_wpg_paypal_checkout_cc', (event) => {
                event.preventDefault();
                return this.handleCheckoutSubmit(event);
            });
            const eventSelectors = 'updated_cart_totals wc_fragments_refreshed wc_fragment_refresh wc_fragments_loaded updated_checkout ppcp_block_ready ppcp_checkout_updated wc_update_cart wc_cart_emptied wpg_change_method';
            const checkoutSelectors = 'updated_cart_totals wc_fragments_refreshed wc_fragments_loaded updated_checkout ppcp_cc_block_ready ppcp_cc_checkout_updated update_checkout';
            $(document.body).on(eventSelectors, (event) => {
                this.debouncedUpdatePaypalCheckout();
                this.debouncedUpdateGooglePay();
                this.debouncedUpdateApplePay();
            });
            $(document.body).on(checkoutSelectors, () => {
                this.debouncedUpdatePaypalCC();
            });
            $('form.checkout').on('click', 'input[name="payment_method"]', () => {
                $(document.body).trigger('wpg_change_method');
                this.togglePlaceOrderButton();
            });
        }

        handleCheckoutSubmit() {
            if (this.isPpcpCCSelected() && this.isCardFieldEligible()) {
                if ($('form.checkout').hasClass('paypal_cc_submitting')) {
                    return false;
                }
                $('form.checkout').addClass('paypal_cc_submitting');
                $(document.body).trigger('submit_paypal_cc_form');
                return false;
            }
            return true;
        }

        update_paypal_checkout() {
            this.ppcp_cart_css();
            this.renderSmartButton();
            this.togglePlaceOrderButton();
        }

        update_paypal_cc() {
            if (this.isCardFieldEligible()) {
                this.renderCardFields();
                $('#place_order, .wc-block-components-checkout-place-order-button').show();
            } else {
                $('.wc_payment_method.payment_method_wpg_paypal_checkout_cc').hide();
                $('#radio-control-wc-payment-method-options-wpg_paypal_checkout_cc').parent('label').parent('div').hide();
                if (this.isPpcpCCSelected())
                    $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
            }
            this.togglePlaceOrderButton();
        }

        isPpcpSelected() {
            if (this.ppcp_manager.is_wpg_change_payment_method === 'yes') {
                return false;
            }
            return $('#payment_method_wpg_paypal_checkout').is(':checked') || $('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'wpg_paypal_checkout';
        }

        isPpcpCCSelected() {
            return $('#payment_method_wpg_paypal_checkout_cc').is(':checked') || $('input[name="radio-control-wc-payment-method-options"]:checked').val() === 'wpg_paypal_checkout_cc';
        }

        isCardFieldEligible() {
            return this.isCheckoutPage() && this.ppcp_manager.advanced_card_payments === 'yes' && typeof wpg_paypal_sdk !== 'undefined' && wpg_paypal_sdk.CardFields().isEligible();
        }

        togglePlaceOrderButton() {
            const isPpcpSelected = this.isPpcpSelected();
            const isPpcpCCSelected = this.isPpcpCCSelected();
            if (isPpcpSelected) {
                $('#ppcp_checkout, .google-pay-container.checkout, .apple-pay-container.checkout').show();
                $('#place_order, .wc-block-components-checkout-place-order-button').hide();
            } else {
                $('#ppcp_checkout, .google-pay-container.checkout, .apple-pay-container.checkout').hide();
                $('#place_order, .wc-block-components-checkout-place-order-button').show();
            }
            if (isPpcpCCSelected && this.isCardFieldEligible()) {
                $('#place_order, .wc-block-components-checkout-place-order-button').show();
            }

        }

        renderSmartButton() {
            const selectors = this.ppcp_manager.button_selector;
            $.each(selectors, (key, selector) => {
                const elements = jQuery(".ppcp-button-container.ppcp_mini_cart");
                if (elements.length > 1) {
                    elements.slice(0, -1).remove(); // Removes all except the last one
                }
                if (!$(selector).length || $(selector).children().length || typeof wpg_paypal_sdk === 'undefined') {
                    return;
                }
                const isExpressCheckout = selector === '#ppcp_checkout_top';
                const isMiniCart = selector === '#ppcp_mini_cart';
                const ppcpStyle = {
                    layout: isMiniCart
                            ? this.ppcp_manager.mini_cart_style_layout
                            : (isExpressCheckout ? this.ppcp_manager.express_checkout_style_layout : this.ppcp_manager.style_layout),
                    color: isMiniCart
                            ? this.ppcp_manager.mini_cart_style_color
                            : (isExpressCheckout ? this.ppcp_manager.express_checkout_style_color : this.ppcp_manager.style_color),
                    shape: isMiniCart
                            ? this.ppcp_manager.mini_cart_style_shape
                            : (isExpressCheckout ? this.ppcp_manager.express_checkout_style_shape : this.ppcp_manager.style_shape),
                    label: isMiniCart
                            ? this.ppcp_manager.mini_cart_style_label
                            : (isExpressCheckout ? this.ppcp_manager.express_checkout_style_label : this.ppcp_manager.style_label)
                };
                if (ppcpStyle.layout === 'horizontal') {
                    ppcpStyle.tagline = 'false';
                }
                ppcpStyle.height = ppcpStyle.height = Number(
                        isExpressCheckout ? this.ppcp_manager?.express_checkout_button_height :
                        isMiniCart ? this.ppcp_manager?.mini_cart_button_height :
                        this.ppcp_manager?.button_height
                        ) || 48;

                wpg_paypal_sdk.Buttons({
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
                $('<input>', {type: 'hidden', name: 'ppcp-add-to-cart', value: $("[name='add-to-cart']").val()}).appendTo('form.cart');
                data = $('form.cart').serialize();
            } else {
                data = $('form.woocommerce-cart-form').serialize();
            }
            return fetch(this.ppcp_manager.create_order_url_for_paypal, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data
            }).then(res => res.json()).then(data => {
                this.hideSpinner();
                if (data.success !== undefined) {
                    const messages = data.data.messages ?? data.data;
                    this.showError(messages);
                    return null;
                }
                return data.orderID;
            });
        }

        onApproveHandler(data, actions) {
            this.showSpinner();
            if (this.isCheckoutPage()) {
                $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${data.orderID}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, function (data) {
                    window.location.href = data.data.redirect;
                });
            } else {
                window.location.href = `${this.ppcp_manager.checkout_url}?paypal_order_id=${data.orderID}&paypal_payer_id=${data.payerID}&from=${this.ppcp_manager.page}`;
            }
        }

        showSpinner(containerSelector = '.woocommerce') {
            if (jQuery('.wc-block-checkout__main').length || jQuery('.wp-block-woocommerce-cart').length) {
                jQuery('.wc-block-checkout__main, .wp-block-woocommerce-cart').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
            } else if (jQuery(containerSelector).length) {
                jQuery(containerSelector).block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
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
            console.log(error_message);
            let $checkout_form;
            if ($('form.checkout').length) {
                $checkout_form = $('form.checkout');
            } else if ($('.woocommerce-notices-wrapper').length) {
                $checkout_form = $('.woocommerce-notices-wrapper');
            } else if ($('.woocommerce').length) {
                $checkout_form = $('.woocommerce');
            } else if ($('.wc-block-components-notices').length) {
                $checkout_form = $('.wc-block-components-notices').first();
            }
            if ($checkout_form && $checkout_form.length) {
                $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
                if (!error_message || (typeof error_message !== 'string' && !Array.isArray(error_message))) {
                    error_message = ['An unknown error occurred.'];
                } else if (typeof error_message === 'string') {
                    error_message = [error_message];
                }
                let errorHTML = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout" role="alert" aria-live="assertive"><ul class="woocommerce-error">';
                $.each(error_message, (index, value) => {
                    errorHTML += `<li>${value}</li>`;
                });
                errorHTML += '</ul></div>';
                $checkout_form.prepend(errorHTML).removeClass('processing').unblock();
                $checkout_form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
                const scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout').filter(function () {
                    return $(this).is(':visible') && $(this).offset() !== undefined;
                }).first();
                if (scrollElement.length) {
                    const offset = scrollElement.offset();
                    if (offset) {
                        $('html, body').animate({scrollTop: offset.top - 100}, 1000);
                    }
                }
                $(document.body).trigger('checkout_error', [error_message]);
            } else {
                const errorMessagesString = Array.isArray(error_message) ? error_message.join('<br>') : typeof error_message === 'string' ? error_message : 'An unknown error occurred.';
                $(document.body).trigger('ppcp_checkout_error', errorMessagesString);
            }
        }

        renderCardFields() {
            const checkoutSelector = this.getCheckoutSelectorCss();
            if ($('#wpg_paypal_checkout_cc-card-number').length === 0 || typeof wpg_paypal_sdk === 'undefined') {
                return;
            }
            $(checkoutSelector).addClass('CardFields');
            const cardStyle = {
                input: {fontSize: '16px', fontFamily: 'Helvetica, Arial, sans-serif', fontWeight: '400', color: '#32325d', padding: '12px 14px', borderRadius: '4px', border: '1px solid #ccd0d5', background: '#ffffff', boxShadow: 'none', transition: 'border-color 0.15s ease, box-shadow 0.15s ease'},
                '.invalid': {color: '#fa755a', border: '1px solid #fa755a', boxShadow: 'none'},
                '::placeholder': {color: '#aab7c4'},
                'input:focus': {outline: 'none', border: '1px solid #4a90e2', boxShadow: '0 0 4px rgba(74, 144, 226, 0.3)'},
                '.valid': {border: '1px solid #3ac569', color: '#32325d', boxShadow: 'none'}
            };
            const cardFields = wpg_paypal_sdk.CardFields({
                style: cardStyle,
                createOrder: () => this.createCardOrder(checkoutSelector),
                onApprove: (payload) => payload && payload.orderID ? this.submitCardFields(payload) : console.error("No valid payload returned during onApprove:", payload),
                onError: (err) => {
                    this.hideSpinner();
                    this.handleCardFieldsError(err, checkoutSelector);
                }
            });
            if (cardFields.isEligible()) {
                if ($("#wpg_paypal_checkout_cc-card-number").html().trim() === "") {
                    const numberField = cardFields.NumberField();
                    $("#wpg_paypal_checkout_cc-card-number").empty();
                    $("#wpg_paypal_checkout_cc-card-expiry").empty();
                    $("#wpg_paypal_checkout_cc-card-cvc").empty();
                    numberField.render("#wpg_paypal_checkout_cc-card-number");
                    numberField.setAttribute("placeholder", "4111 1111 1111 1111");
                    cardFields.ExpiryField().render("#wpg_paypal_checkout_cc-card-expiry");
                    cardFields.CVVField().render("#wpg_paypal_checkout_cc-card-cvc");
                }
            } else {
                $('.payment_box.payment_method_wpg_paypal_checkout_cc').hide();
                if (this.isPpcpCCSelected())
                    $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
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
            return fetch(this.ppcp_manager.create_order_url_for_cc, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: data}).then(res => res.json()).then(data => {
                if (data.success !== undefined) {
                    this.hideSpinner();
                    this.showError(data.data.messages);
                    return '';
                }
                return data.orderID;
            });
        }

        submitCardFields(payload) {
            this.showSpinner();
            $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${payload.orderID}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, (data) => {
                window.location.href = data.data.redirect;
            });
        }

        handleCardFieldsError(errorString, checkoutSelector) {
            $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
            $(checkoutSelector).removeClass('processing paypal_cc_submitting CardFields createOrder').unblock();
            try {
                if (errorString instanceof Error) {
                    var messageContent = errorString.message;
                    var jsonMatch = messageContent.match(/{[\s\S]*}$/);
                    if (jsonMatch) {
                        var errorJsonString = jsonMatch[0].trim();
                        var error = JSON.parse(errorJsonString);
                        var message = (error.details && Array.isArray(error.details) && error.details.length > 0) ? error.details[0].description : error.message || "An unknown error occurred.";
                    }
                } else if (typeof errorString === 'object' && errorString !== null) {
                    var message = (errorString.details && Array.isArray(errorString.details) && errorString.details.length > 0) ? errorString.details[0].description : errorString.message || "An unknown error occurred.";
                }
            } catch (err) {
                var message = "An unknown error occurred.";
            }
            this.showError(message);
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

        ppcp_cart_css() {
            /*const $button = $('.checkout-button');
            const width = $button.outerWidth();
            const $container = $('.ppcp-button-container.ppcp_cart');
            if (width && $container.length) {
                $container.width(width);
            }
            if ($button.css('float') !== 'none') {
                $container.css('float', $button.css('float'));
            }*/
        }

        manageVariations(selector) {
            if ($('.variations_form').length) {
                $('.variations_form, .single_variation').on('show_variation', function (event, variation) {
                    if (variation.is_purchasable && variation.is_in_stock) {
                        $(selector).show();
                    } else {
                        $(selector).hide();
                    }
                }).on('hide_variation', function () {
                    $(selector).hide();
                });
            }
        }

        loadGooglePaySdk() {
            const sdkUrl = "https://pay.google.com/gp/p/js/pay.js";
            const script = document.createElement("script");
            script.src = sdkUrl;
            script.onload = () => this.onGooglePayLoaded();
            script.onerror = () => this.removeGooglePayContainer();
            document.head.appendChild(script);
        }

        async onGooglePayLoaded() {
            if (!this.isGooglePayAvailable()) {
                this.removeGooglePayContainer();
                return;
            }
            const paymentsClient = this.getGooglePaymentsClient();
            const googlePayConfig = await this.getGooglePayConfig();
            if (!googlePayConfig || !googlePayConfig.allowedPaymentMethods || googlePayConfig.allowedPaymentMethods.length === 0) {
                this.removeGooglePayContainer();
                return;
            }
            const {allowedPaymentMethods} = googlePayConfig;
            try {
                const response = await paymentsClient.isReadyToPay(this.getGoogleIsReadyToPayRequest(allowedPaymentMethods));
                if (response.result) {
                    this.addGooglePayButton();
                } else {
                    this.removeGooglePayContainer();
                    console.log("Google Pay is not available for this configuration");
                }
            } catch (error) {
                console.error("Google Pay readiness check failed:", error);
                this.removeGooglePayContainer();
            }
        }

        isGooglePayAvailable() {
            return typeof wpg_paypal_sdk !== "undefined" &&
                    typeof wpg_paypal_sdk.Googlepay !== "undefined" &&
                    typeof google !== "undefined";
        }

        removeGooglePayContainer() {
            const containers = document.querySelectorAll('.google-pay-container');
            containers.forEach(container => {
                container.remove();
            });
        }

        getGooglePaymentsClient() {
            if (!this.paymentsClient && typeof google !== "undefined") {
                this.paymentsClient = new google.payments.api.PaymentsClient({
                    environment: this.ppcp_manager.environment || "TEST",
                    paymentDataCallbacks: {
                        onPaymentAuthorized: this.onPaymentAuthorized.bind(this),
                    },
                });
            }
            return this.paymentsClient;
        }

        async getGooglePayConfig() {
            try {
                if (!this.allowedPaymentMethods || !this.merchantInfo) {
                    const googlePayConfig = await wpg_paypal_sdk.Googlepay().config();
                    this.allowedPaymentMethods = googlePayConfig.allowedPaymentMethods || [];
                    this.merchantInfo = googlePayConfig.merchantInfo || {};
                }
                return {
                    allowedPaymentMethods: this.allowedPaymentMethods,
                    merchantInfo: this.merchantInfo,
                };
            } catch (error) {
                console.error("Failed to fetch Google Pay configuration:", error);
                return {allowedPaymentMethods: [], merchantInfo: {}};
            }
        }

        getGoogleIsReadyToPayRequest(allowedPaymentMethods) {
            return {
                apiVersion: 2,
                apiVersionMinor: 0,
                allowedPaymentMethods,
            };
        }

        addGooglePayButton() {
            const containers = document.querySelectorAll(".google-pay-container");
            containers.forEach(container => {
                this.renderGooglePayButton(container);
            });
        }

        async update_google_pay() {
            if (this.ppcp_manager.enabled_google_pay !== 'yes' || !this.isGooglePayAvailable()) {
                this.removeGooglePayContainer();
                return;
            }
            const paymentsClient = this.getGooglePaymentsClient();
            const googlePayConfig = await this.getGooglePayConfig();
            const allowedPaymentMethods = googlePayConfig?.allowedPaymentMethods;
            if (!allowedPaymentMethods?.length) {
                this.removeGooglePayContainer();
                return;
            }
            try {
                const response = await paymentsClient.isReadyToPay(this.getGoogleIsReadyToPayRequest(allowedPaymentMethods));
                if (!response.result) {
                    this.removeGooglePayContainer();
                    return;
                }
                $('.google-pay-container').each((_, container) => {
                    $(container).empty();
                    this.renderGooglePayButton(container);
                });
            } catch (error) {
                console.error("Google Pay readiness check failed:", error);
                this.removeGooglePayContainer();
            }
        }

        renderGooglePayButton(container) {
            if (!container) {
                return;
            }
            const paymentsClient = this.getGooglePaymentsClient();
            const button = paymentsClient.createButton({
                buttonColor: 'default',
                buttonType: 'plain',
                buttonRadius: 4,
                buttonLocale: this.ppcp_manager.locale,
                buttonSizeMode: 'fill',
                onClick: this.onGooglePaymentButtonClicked.bind(this),
            });
            container.innerHTML = ''; // Clear existing content
            container.appendChild(button);
        }

        async onGooglePaymentButtonClicked() {
            try {
                const transactionInfo = await this.ppcpGettransactionInfo();
                if (transactionInfo?.success) {
                    this.ppcp_manager.cart_total = transactionInfo.data?.cart_total || this.ppcp_manager.cart_total;
                }

                const paymentDataRequest = await this.getGooglePaymentDataRequest();
                const paymentsClient = this.getGooglePaymentsClient();
                const paymentData = await paymentsClient.loadPaymentData(paymentDataRequest);

                await this.processGooglePayPayment(paymentData);
            } catch (error) {
                console.error("Google Pay Button Click Error:", error);
            }
        }

        async getGooglePaymentDataRequest() {
            const {allowedPaymentMethods, merchantInfo} = await this.getGooglePayConfig();
            return {
                apiVersion: 2,
                apiVersionMinor: 0,
                allowedPaymentMethods,
                transactionInfo: this.getGoogleTransactionInfo(),
                merchantInfo,
                callbackIntents: ["PAYMENT_AUTHORIZATION"],
            };
        }

        async ppcpGettransactionInfo() {
            const data = this.isProductPage()
                    ? $('form.cart').serialize()
                    : $('form.woocommerce-cart-form').serialize();

            try {
                const response = await fetch(this.ppcp_manager.get_transaction_info_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: data,
                });
                return await response.json();
            } catch (error) {
                console.error("Transaction Info Fetch Error:", error);
                return null;
            }
        }

        getGoogleTransactionInfo() {
            return {
                currencyCode: this.ppcp_manager.currency || "USD",
                totalPriceStatus: "FINAL",
                totalPrice: this.ppcp_manager.cart_total || "0.00",
            };
        }

        async onPaymentAuthorized(paymentData) {
            try {
                await this.processGooglePayPayment(paymentData);
                return {transactionState: "SUCCESS"};
            } catch (error) {
                console.error("Payment Authorization Error:", error);
                return {
                    transactionState: "ERROR",
                    error: {
                        intent: "PAYMENT_AUTHORIZATION",
                        message: error.message,
                    },
                };
            }
        }

        async processGooglePayPayment(paymentData) {
            try {
                const orderId = await this.createOrder('.google-pay-container');
                if (!orderId)
                    throw new Error("Order creation failed.");

                const result = await wpg_paypal_sdk.Googlepay().confirmOrder({
                    orderId,
                    paymentMethodData: paymentData.paymentMethodData,
                });

                if (result.status === "APPROVED") {
                    this.onApproveHandler({orderId}, null);
                } else {
                    throw new Error("Google Pay order confirmation failed.");
                }
            } catch (error) {
                console.error("Google Pay Processing Error:", error);
            }
        }

        loadApplePaySdk() {
            const script = document.createElement('script');
            script.src = 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js';
            script.onload = () => this.onApplePayLoaded();
            script.onerror = () => {
                this.removeApplePayContainer();
                console.error("Failed to load Apple Pay SDK.");
            };
            document.head.appendChild(script);
        }

        addApplePayButton() {
            const containers = document.querySelectorAll(".apple-pay-container");
            containers.forEach(container => {
                container.innerHTML = ''; // Clear any existing content
                const applePayButton = document.createElement('apple-pay-button');
                applePayButton.setAttribute('buttonstyle', 'black');
                applePayButton.setAttribute('type', 'plain');
                container.appendChild(applePayButton);

                // Add the click event listener for each button
                applePayButton.addEventListener('click', () => this.onApplePayButtonClicked(container));
            });
        }

        update_apple_pay() {
            if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                this.addApplePayButton();
            }
        }

        removeApplePayContainer() {
            const containers = document.querySelectorAll('.apple-pay-container');
            containers.forEach(container => {
                container.remove();
            });
        }

        async onApplePayLoaded() {
            if (!window.ApplePaySession) {
                console.log("Apple Pay cannot make payments on this device.");
                this.removeApplePayContainer();
                return;
            }
            if (!ApplePaySession.canMakePayments()) {
                console.log("Apple Pay cannot make payments on this device.");
                this.removeApplePayContainer();
                return;
            }
            const applePay = wpg_paypal_sdk.Applepay();
            try {
                const config = await applePay.config({environment: this.ppcp_manager.environment || "TEST"});
                if (config.isEligible) {
                    this.addApplePayButton();
                } else {
                    console.log("Apple Pay is not eligible for this configuration.");
                    this.removeApplePayContainer();
                }
            } catch (error) {
                console.error("Failed to load Apple Pay configuration:", error);
                this.removeApplePayContainer();
            }
        }

        addApplePayButton() {
            if (!window.ApplePaySession) {
                console.log("Apple Pay cannot make payments on this device.");
                this.removeApplePayContainer();
                return;
            }
            if (!ApplePaySession.canMakePayments()) {
                console.log("Apple Pay cannot make payments on this device.");
                this.removeApplePayContainer();
                return;
            }
            const containers = document.querySelectorAll(".apple-pay-container");
            if (containers.length === 0) {
                return;
            }

            containers.forEach(container => {
                container.innerHTML = ''; // Clear any existing content
                const applePayButton = document.createElement('apple-pay-button');
                applePayButton.setAttribute('buttonstyle', 'black');
                applePayButton.setAttribute('type', 'plain');
                container.appendChild(applePayButton);
                applePayButton.addEventListener('click', () => this.onApplePayButtonClicked());
            });
        }

        async onApplePayButtonClicked(container) {
            const session = new ApplePaySession(4, {
                countryCode: this.ppcp_manager.countryCode || "US",
                currencyCode: this.ppcp_manager.currency || "USD",
                merchantCapabilities: ["supports3DS", "supportsEMV"],
                supportedNetworks: ["visa", "masterCard", "amex", "discover"],
                total: {
                    label: "Total Amount",
                    amount: this.ppcp_manager.cart_total || "0.00"
                },
                requiredBillingContactFields: ["name", "phone", "email", "postalAddress"],
                requiredShippingContactFields: ["postalAddress", "name", "email"]
            });

            try {
                session.onvalidatemerchant = async (event) => {
                    try {
                        const applePay = wpg_paypal_sdk.Applepay();
                        const validationResult = await applePay.validateMerchant({
                            validationUrl: event.validationURL
                        });
                        session.completeMerchantValidation(validationResult.merchantSession);
                    } catch (error) {
                        console.error("Merchant validation failed:", error);
                        session.abort();
                    }
                };

                session.onpaymentauthorized = async (event) => {
                    try {
                        const applePay = wpg_paypal_sdk.Applepay();
                        const orderID = await this.createOrder(container);
                        if (!orderID) {
                            throw new Error("Order creation failed.");
                        }
                        const confirmResult = await applePay.confirmOrder({
                            orderId: orderID,
                            token: event.payment.token,
                            billingContact: event.payment.billingContact,
                            shippingContact: event.payment.shippingContact
                        });
                        const status = confirmResult?.approveApplePayPayment?.status;
                        if (status === "APPROVED") {
                            await session.completePayment({
                                status: ApplePaySession.STATUS_SUCCESS,
                            });
                            this.onApproveHandler({orderID}, null);
                        } else {
                            console.error("Order confirmation failed. Status:", status);
                            throw new Error("Apple Pay order confirmation failed.");
                        }
                    } catch (error) {
                        console.error("Payment authorization failed. Error details:", error);
                        session.completePayment(ApplePaySession.STATUS_FAILURE);
                    }
                };

                session.oncancel = () => {
                    console.log("Apple Pay session cancelled.");
                };

                session.begin();
            } catch (error) {
                console.error("Error during Apple Pay button click:", error);
            }
        }

    }

    $(function () {
        window.PPCPManager = PPCPManager;
        const ppcp_manager = window.ppcp_manager || {};
        new PPCPManager(ppcp_manager);
    });
})(jQuery);
