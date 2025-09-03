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
            this.pageContext = 'unknown';
            this.ppcp_used_payment_method = null;
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
            this.debouncedUpdatePaypalCC = this.debounce_cc(this.update_paypal_cc.bind(this), 500);
            this.debouncedUpdatePaypalCheckout = this.debounce(this.update_paypal_checkout.bind(this), 500);
            this.debouncedUpdateGooglePay = this.debounce_google(this.update_google_pay.bind(this), 500);
            this.debouncedUpdateApplePay = this.debounce_apple(this.update_apple_pay.bind(this), 500);
            if (this.isCheckoutPage() === false) {
                this.debouncedUpdatePaypalCheckout();
                this.debouncedUpdateGooglePay();
                this.debouncedUpdateApplePay();

            } else {
                this.debouncedUpdatePaypalCheckout();
                this.debouncedUpdateGooglePay();
                this.debouncedUpdateApplePay();
                this.debouncedUpdatePaypalCC();
            }

            setTimeout(function () {
                $('#wfacp_smart_buttons.wfacp-dynamic-checkout-loading .dynamic-checkout__skeleton').hide();
            }, 1000);
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

            fields.phoneNumber = prefix === 'billing' ?
                    jQuery('#billing-phone').val() || jQuery('#shipping-phone').val() :
                    jQuery('#shipping-phone').val() || jQuery('#billing-phone').val();

            let customerData = {};
            let addressData = {};

            if (!fields.addressLine1) {
                if (typeof wp !== 'undefined' && wp.data?.select) {
                    try {
                        customerData = wp.data.select('wc/store/cart').getCustomerData();
                    } catch (e) {
                        console.warn('Could not fetch customerData:', e);
                    }
                }
                const {billingAddress, shippingAddress} = customerData;
                addressData = (prefix === 'billing') ? billingAddress : shippingAddress;

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

            // Start with the standard fields
            const result = {
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

            // Add ALL other fields from addressData (including custom fields)
            if (addressData && Object.keys(addressData).length > 0) {
                Object.keys(addressData).forEach(key => {
                    const fieldKey = `${prefix}_${key}`;
                    // Only add if it's not already in our standard fields
                    if (!result.hasOwnProperty(fieldKey)) {
                        result[fieldKey] = addressData[key] !== undefined && addressData[key] !== null
                                ? addressData[key]
                                : '';
                    }
                });
            }

            return result;
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
            $('#order_review').on('submit', (event) => {
                event.preventDefault();
                return this.handleCheckoutSubmit();
            });
            const eventSelectors = 'added_to_cart updated_cart_totals wc_fragments_refreshed wc_fragment_refresh wc_fragments_loaded updated_checkout ppcp_block_ready ppcp_checkout_updated wc_update_cart wc_cart_emptied wpg_change_method fkwcs_express_button_init';
            const checkoutSelectors = 'updated_cart_totals wc_fragments_refreshed wc_fragments_loaded updated_checkout ppcp_cc_block_ready ppcp_cc_checkout_updated update_checkout wpg_change_method';
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
            if ($('input[name="wc-wpg_paypal_checkout_cc-payment-token"]:checked').length > 0) {
                return true;
            }
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
            if ($('#ppcp_checkout_top').length === 0) {
                const $applePay = $('div.apple-pay-container[data-context="express_checkout"]');
                const $googlePay = $('div.google-pay-container[data-context="express_checkout"]');
                const hasAppleOnly = $applePay.length > 0 && $googlePay.length === 0;
                const hasGoogleOnly = $googlePay.length > 0 && $applePay.length === 0;
                if (hasAppleOnly && !$applePay.hasClass('mobile')) {
                    $applePay.css('min-width', '480px');
                }
                if (hasGoogleOnly && !$googlePay.hasClass('mobile')) {
                    $googlePay.css('min-width', '480px');
                }
            }
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
            const usePlaceOrder = this.ppcp_manager.use_place_order === '1';
            const showElement = (selector) => {
                document.querySelectorAll(selector).forEach(el => {
                    el.style.removeProperty('display');
                });
            };
            const hideElement = (selector) => {
                document.querySelectorAll(selector).forEach(el => {
                    el.style.setProperty('display', 'none', 'important');
                });
            };
            if (isPpcpSelected) {
                if (usePlaceOrder) {
                    showElement('#place_order, .wc-block-components-checkout-place-order-button');
                    hideElement('#ppcp_checkout, .google-pay-container.checkout, .apple-pay-container.checkout');
                } else {
                    showElement('#ppcp_checkout, .google-pay-container.checkout, .apple-pay-container.checkout');
                    hideElement('#place_order, .wc-block-components-checkout-place-order-button');
                }
            } else {
                hideElement('#ppcp_checkout, .google-pay-container.checkout, .apple-pay-container.checkout');
                showElement('#place_order, .wc-block-components-checkout-place-order-button');
            }

            if (isPpcpCCSelected && this.isCardFieldEligible()) {
                showElement('#place_order, .wc-block-components-checkout-place-order-button');
            }
        }

        renderSmartButton() {
            const selectors = this.ppcp_manager.button_selector;
            $.each(selectors, (key, selector) => {
                const elements = jQuery(".ppcp-button-container.ppcp_mini_cart");
                if (elements.length > 1) {
                    elements.slice(0, -1).remove();
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
                            : (isExpressCheckout ? this.ppcp_manager.express_checkout_style_label : this.ppcp_manager.style_label),
                    height: Number(
                            isExpressCheckout
                            ? this.ppcp_manager.express_checkout_button_height
                            : isMiniCart
                            ? this.ppcp_manager.mini_cart_button_height
                            : this.ppcp_manager.button_height
                            ) || 48
                };
                if (ppcpStyle.layout === 'horizontal') {
                    ppcpStyle.tagline = 'false';
                }
                const baseH = parseFloat(ppcpStyle.height) || 48;       // handles "48" or "48px"
                const heightPx = `${Math.max(0, baseH - 1)}px`;         // reduce by 1px (no negatives)

                const targets = [];
                if (isMiniCart || selector === '#ppcp_cart') {
                    targets.push('#ppcp_cart', '.google-pay-container.cart', '.apple-pay-container.cart');
                } else if (isExpressCheckout) {
                    targets.push('#ppcp_checkout_top', '#ppcp_checkout_top_alternative',
                            '.google-pay-container.express_checkout', '.apple-pay-container.express_checkout');
                } else if (selector === '#ppcp_product') {
                    targets.push('#ppcp_product', '.google-pay-container.product', '.apple-pay-container.product');
                } else if (selector === '#ppcp_checkout') {
                    targets.push('#ppcp_checkout', '.google-pay-container.checkout', '.apple-pay-container.checkout');
                }

                if (targets.length) {
                    document.querySelectorAll(targets.join(',')).forEach(el => {
                        el.style.setProperty('--button-height', heightPx);
                    });
                }

                const styledFundingSources = [
                    wpg_paypal_sdk.FUNDING.PAYPAL,
                    wpg_paypal_sdk.FUNDING.PAYLATER
                ];
                if (selector === '#ppcp_checkout') {
                    let fundingSources = wpg_paypal_sdk.getFundingSources();
                    if (fundingSources.length) {
                        const hideTagline = this.ppcp_manager.is_google_pay_enabled_checkout === 'yes' || this.ppcp_manager.is_apple_pay_enable_checkout === 'yes';
                        fundingSources.forEach((fundingSource) => {
                            if (fundingSource === wpg_paypal_sdk.FUNDING.CARD && this.isCardFieldEligible()) {
                                return;
                            }
                            const options = {
                                fundingSource,
                                onClick: async (data, actions) => {
                                    this.ppcp_used_payment_method = fundingSource;

                                    if (this.ppcp_manager.is_block_enable === 'yes' && window.wp?.data) {
                                        const vDisp = wp.data.dispatch('wc/store/validation');
                                        const vSel = wp.data.select('wc/store/validation');
                                        const cartSel = wp.data.select('wc/store/cart');
                                        const chkSel = wp.data.select('wc/store/checkout');

                                        // 0) Wait until Blocks is idle (addresses/rates/coupons/customer updates)
                                        const waitUntilIdle = async () => {
                                            for (let i = 0; i < 30; i++) {
                                                const busy =
                                                        cartSel?.isLoading?.() ||
                                                        cartSel?.isCouponsUpdating?.() ||
                                                        cartSel?.isCustomerDataUpdating?.() ||
                                                        chkSel?.isProcessing?.(); // some builds expose this
                                                if (!busy)
                                                    return;
                                                await new Promise(r => setTimeout(r, 50));
                                            }
                                        };
                                        await waitUntilIdle();

                                        // 1) Ask Blocks to reveal native messages
                                        vDisp.showAllValidationErrors();

                                        // 2) Give the validation store a tick to recalc & render
                                        await new Promise(r => setTimeout(r, 0));

                                        // 3) Re-check using BOTH the boolean and the map (some builds lag the boolean)
                                        const hasErrorsBool =
                                                typeof vSel.hasValidationErrors === 'function' && vSel.hasValidationErrors();
                                        const errorMap = typeof vSel.getValidationErrorMap === 'function'
                                                ? vSel.getValidationErrorMap()
                                                : {};
                                        const hasErrors = hasErrorsBool || (errorMap && Object.keys(errorMap).length > 0);

                                        if (hasErrors) {
                                            // keep focus on form; errors are already shown natively
                                            return actions?.reject ? actions.reject() : false;
                                        }
                                    }

                                    // proceed to wallet
                                    return true;
                                }
                                ,
                                createOrder: () => this.createOrder(selector),
                                onApprove: (data, actions) => this.onApproveHandler(data, actions),
                                onCancel: () => this.onCancelHandler(),
                                onError: (err) => this.onErrorHandler(err)
                            };
                            let style = {...ppcpStyle};
                            if (hideTagline) {
                                style.tagline = 'false';
                                const {layout, ...base} = style;
                                style = base; // remove layout
                            }
                            let cleanStyleBase = {...style};
                            if (styledFundingSources.includes(fundingSource)) {
                                options.style = {...cleanStyleBase};
                            } else {
                                const {color, ...cleanStyle} = cleanStyleBase;
                                options.style = {...cleanStyle};
                            }
                            const button = wpg_paypal_sdk.Buttons(options);
                            if (button.isEligible()) {
                                button.render(selector);
                            }
                        });
                    }
                } else if (selector === '#ppcp_checkout_top') {

                    const expressFundingSources = [
                        wpg_paypal_sdk.FUNDING.PAYPAL,
                        wpg_paypal_sdk.FUNDING.VENMO,
                        wpg_paypal_sdk.FUNDING.PAYLATER,
                        wpg_paypal_sdk.FUNDING.CREDIT
                    ];
                    const renderSelectors = ['#ppcp_checkout_top', '#ppcp_checkout_top_alternative'];
                    let renderedCount = 0;
                    for (let i = 0; i < expressFundingSources.length; i++) {
                        const fundingSource = expressFundingSources[i];
                        const targetSelector = renderSelectors[renderedCount];
                        if (!targetSelector || !$(targetSelector).length) {
                            continue;
                        }
                        const {layout, ...cleanStyle} = ppcpStyle;
                        let style = {...cleanStyle};
                        if (fundingSource === wpg_paypal_sdk.FUNDING.VENMO) {
                            style = {
                                ...style,
                                color: 'blue'
                            };
                        } else if (fundingSource === wpg_paypal_sdk.FUNDING.CREDIT) {
                            style = {
                                ...style,
                                color: 'darkblue'
                            };
                        }
                        const options = {
                            style,
                            fundingSource,
                            createOrder: () => this.createOrder(targetSelector),
                            onApprove: (data, actions) => this.onApproveHandler(data, actions),
                            onCancel: () => this.onCancelHandler(),
                            onError: (err) => this.onErrorHandler(err)
                        };
                        const button = wpg_paypal_sdk.Buttons(options);
                        if (button.isEligible()) {
                            button.render(targetSelector);
                            renderedCount++;
                        }
                        if (renderedCount >= 2) {
                            break;
                        }
                    }
                    if (renderedCount < 2 && $('#ppcp_checkout_top_alternative').length) {
                        if ($('div.apple-pay-container[data-context="express_checkout"]').length === 0 && $('div.google-pay-container[data-context="express_checkout"]').length === 0) {
                            if ($('#ppcp_checkout_top').length && !$('#ppcp_checkout_top').hasClass('mobile')) {
                                $('#ppcp_checkout_top').css('min-width', '480px');
                            }
                        }
                        $('#ppcp_checkout_top_alternative').remove();
                    }

                } else {
                    wpg_paypal_sdk.Buttons({
                        style: ppcpStyle,
                        createOrder: () => this.createOrder(selector),
                        onApprove: (data, actions) => this.onApproveHandler(data, actions),
                        onCancel: () => this.onCancelHandler(),
                        onError: (err) => this.onErrorHandler(err)
                    }).render(selector);
                }
            });
            var $targets = $('#ppcp_product, #ppcp_cart, #ppcp_mini_cart, #ppcp_checkout, #ppcp_checkout_top, #ppcp_checkout_top_alternative');
            setTimeout(function () {
                $targets.css({background: '', 'background-color': ''});
                $targets.each(function () {
                    this.style.setProperty('--wpg-skel-fallback-bg', 'transparent');
                });
                $targets.addClass('bg-cleared');
            }, 1);
        }

        createOrder(selector) {
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data;
            if (selector === '#ppcp_checkout_top') {
            } else if (this.isCheckoutPage()) {
                data = $(selector).closest('form').serialize();
                if (this.ppcp_manager.is_block_enable === 'yes') {
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

            const fundingMethod = this.ppcp_used_payment_method;
            const createOrderUrl = this.ppcp_manager.create_order_url_for_paypal + (this.ppcp_manager.create_order_url_for_paypal.includes('?') ? '&' : '?') + 'ppcp_used_payment_method=' + encodeURIComponent(fundingMethod);
            return fetch(createOrderUrl, {
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

        googleapplecreateOrder() {
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data = '';
            switch (this.pageContext) {
                case 'checkout':
                    data = $('form.wc-block-checkout__form').serialize();
                    if (this.ppcp_manager.is_block_enable === 'yes') {
                        const billingAddress = this.getBillingAddress();
                        const shippingAddress = this.getShippingAddress();

                        data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                        data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                        data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                    }
                    break;
                case 'express_checkout':
                case 'product':
                    break;
                default:
                    data = $('form.woocommerce-cart-form').serialize();
                    break;
            }

            return fetch(this.ppcp_manager.create_order_url_for_google_pay, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data
            }).then(res => res.json()).then(data => {
                this.hideSpinner();
                if (data.success !== undefined && data.success === false) {
                    const messages = typeof data.data === 'string'
                            ? data.data
                            : (data.data.messages ?? 'An unknown error occurred.');

                    this.showError(messages);
                    throw new Error(messages);
                    return null;
                }
                return data.orderID;
            });
        }

        onApproveHandler(data, actions) {
            this.showSpinner();
            const order_id = data.orderID || data.orderId || '';
            const payer_id = data.payerID || data.payerId || '';
            if (!order_id) {
                return;
            }
            if (this.isCheckoutPage()) {
                const url = `${this.ppcp_manager.cc_capture}&paypal_order_id=${encodeURIComponent(order_id)}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                $.post(url, (response) => {
                    if (response?.data?.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        if (response?.success === false) {
                            const messages = response.data?.messages ?? ['An unknown error occurred.'];
                            this.showError(messages);
                            this.hideSpinner();
                            return null;
                        }
                    }
                });
                return;
            }
            let redirectUrl = `${this.ppcp_manager.checkout_url}?paypal_order_id=${encodeURIComponent(order_id)}&from=${this.ppcp_manager.page}`;
            if (payer_id) {
                redirectUrl += `&paypal_payer_id=${encodeURIComponent(payer_id)}`;
            }
            window.location.href = redirectUrl;
        }

        showSpinner(containerSelector = '.woocommerce') {
            if (jQuery('.wc-block-checkout__main').length || jQuery('.wp-block-woocommerce-cart').length) {
                jQuery('.wc-block-checkout__main, .wp-block-woocommerce-cart').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
            }  else if (jQuery('form.checkout').length) {
                jQuery('form.checkout').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
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
            if (typeof error_message === 'undefined' || error_message === null) {
                return;
            }
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
                } else if (error_message?.data?.messages && Array.isArray(error_message.data.messages)) {
                    error_message = error_message.data.messages;
                }
                let errorHTML = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout" role="alert" aria-live="assertive"><ul class="woocommerce-error">';
                $.each(error_message, (index, value) => {
                    errorHTML += `<li>${value}</li>`;
                });
                errorHTML += '</ul></div>';
                $checkout_form.prepend(errorHTML).removeClass('processing').unblock();
                $checkout_form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
                const scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout').filter(function () {
                    const $el = $(this);
                    if (!$el.length || !$el.is(':visible')) {
                        return false;
                    }
                    const offset = $el.offset?.();
                    return offset && typeof offset.top !== 'undefined';
                }).first();
                if (scrollElement.length) {
                    const offset = scrollElement.offset();
                    if (offset && typeof offset.top !== 'undefined') {
                        $('html, body').animate({scrollTop: offset.top - 100}, 1000);
                    }
                }
                //$(document.body).trigger('checkout_error', [error_message]);
            } else {
                const errorMessagesString = Array.isArray(error_message)
                        ? error_message.join('<br>')
                        : typeof error_message === 'string'
                        ? error_message
                        : 'An unknown error occurred.';

                $(document.body).trigger('ppcp_checkout_error', errorMessagesString);
            }
        }

        renderCardFields() {
            jQuery(document.body).trigger('wc-credit-card-form-init');
            const checkoutSelector = this.getCheckoutSelectorCss();
            if ($('#wpg_paypal_checkout_cc-card-number').length === 0 || typeof wpg_paypal_sdk === 'undefined') {
                return;
            }
            $(checkoutSelector).addClass('CardFields');
            const cardStyle = {
                input: {fontSize: '18px', fontFamily: 'Helvetica, Arial, sans-serif', fontWeight: '400', color: '#32325d', padding: '12px 14px', borderRadius: '4px', border: '1px solid #ccd0d5', background: '#ffffff', boxShadow: 'none', transition: 'border-color 0.15s ease, box-shadow 0.15s ease'},
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
                setTimeout(function () {
                    $('.wpg-paypal-cc-field label, .wpg-ppcp-card-cvv-icon').show();
                }, 1600);
                setTimeout(function () {
                    $('.wpg_ppcp_sanbdox_notice').show();
                }, 1900);
            } else {
                console.log('Advanced Card Payments not Eligible', cardFields.isEligible());
                $('.payment_box.payment_method_wpg_paypal_checkout_cc').hide();
                if (this.isPpcpCCSelected()) {
                    $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
                }
            }
            $(document.body).on('submit_paypal_cc_form', () => {
                cardFields.submit().catch((error) => {
                    this.handleCardFieldsError(error, checkoutSelector);
                });
            });
        }

        createCardOrder(checkoutSelector) {
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data;
            if (this.ppcp_manager.is_block_enable === 'yes') {
                data = $('form.wc-block-checkout__form').serialize();
                const billingAddress = this.getBillingAddress();
                const shippingAddress = this.getShippingAddress();
                data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
            } else {
                data = $(checkoutSelector).closest('form').serialize();
            }
            return fetch(this.ppcp_manager.create_order_url_for_cc, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data
            })
                    .then(res => res.json())
                    .then(data => {
                        if (!data || data.success === false) {
                            const messages = data.data.messages ?? data.data;
                            this.hideSpinner();
                            this.showError(messages || 'An unknown error occurred while creating the order.');
                            return Promise.reject();
                        }
                        return data.orderID;
                    })
                    .catch(err => {
                        this.hideSpinner();
                        return Promise.reject();
                    });
        }

        submitCardFields(payload) {
            $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${payload.orderID}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, (data) => {
                window.location.href = data.data.redirect;
            });
        }

        handleCardFieldsError(errorString, checkoutSelector) {
            $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
            $(checkoutSelector).removeClass('processing paypal_cc_submitting CardFields createOrder').unblock();
            let message = "An unknown error occurred with your payment. Please try again.";
            let raw = errorString instanceof Error ? errorString.message : String(errorString);
            if (raw.includes('Expected reject')) {
                return true;
            }
            try {
                if (raw.includes('INVALID_NUMBER')) {
                    message = 'Please enter a valid card number.';
                } else if (raw.includes('INVALID_CVV')) {
                    message = 'Please enter a valid CVV.';
                } else if (raw.includes('INVALID_EXPIRATION') || raw.includes('INVALID_EXPIRY_DATE')) {
                    message = 'Please enter a valid expiration date.';
                } else {
                    const jsonStart = raw.indexOf('{');
                    const jsonEnd = raw.lastIndexOf('}') + 1;
                    if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                        const jsonString = raw.slice(jsonStart, jsonEnd);
                        const error = JSON.parse(jsonString);
                        if ((error.message && error.message.includes('Expected reject')) ||
                                (error.details?.[0]?.description && error.details[0].description.includes('Expected reject'))) {
                            return true;
                        }
                        if (error.success === false && error.data && error.data.messages && Array.isArray(error.data.messages)) {
                            if (error.data.messages.some(msg => msg.includes('Expected reject'))) {
                                return true;
                            }
                            message = error.data.messages.map(msg => msg.replace(/<\/?[^>]+(>|$)/g, ''))
                                    .join('\n');
                        } else if (error.details && Array.isArray(error.details)) {
                            const cardError = error.details.find(detail =>
                                detail.issue === 'VALIDATION_ERROR' ||
                                        detail.issue === 'INVALID_NUMBER' ||
                                        detail.issue === 'INVALID_CVV' ||
                                        detail.issue === 'INVALID_EXPIRY_DATE' ||
                                        detail.field?.includes('/card/')
                            );
                            if (cardError) {
                                switch (cardError.issue) {
                                    case 'INVALID_NUMBER':
                                        message = 'Please enter a valid card number.';
                                        break;
                                    case 'INVALID_CVV':
                                        message = 'Please enter a valid CVV.';
                                        break;
                                    case 'INVALID_EXPIRY_DATE':
                                        message = 'Please enter a valid expiration date.';
                                        break;
                                    case 'VALIDATION_ERROR':
                                        if (cardError.field?.includes('/card/number')) {
                                            message = 'Please enter a valid card number.';
                                        } else if (cardError.field?.includes('/card/expiry')) {
                                            message = 'Please enter a valid expiration date.';
                                        } else if (cardError.field?.includes('/card/security_code')) {
                                            message = 'Please enter a valid CVV.';
                                        }
                                        break;
                                }
                            }
                        }
                        if (message === "An unknown error occurred with your payment. Please try again.") {
                            if (error.details?.[0]?.description) {
                                message = error.details[0].description;
                            } else if (error.message) {
                                message = error.message;
                            }
                        }
                    }
                }
            } catch (err) {
                if (raw.includes('INVALID_NUMBER')) {
                    message = 'Please enter a valid card number.';
                } else if (raw.includes('INVALID_CVV')) {
                    message = 'Please enter a valid CVV.';
                } else if (raw.includes('INVALID_EXPIRATION') || raw.includes('INVALID_EXPIRY_DATE')) {
                    message = 'Please enter a valid expiration date.';
                } else {
                    const lastColon = raw.lastIndexOf(':');
                    message = lastColon > 0 ? raw.slice(lastColon + 1).trim() : raw;
                }
            }
            message = message.replace(/\.$/, '').trim();
            this.showError(message);
            this.hideSpinner();
            return false;
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
            script.onload = () => setTimeout(() => this.onGooglePayLoaded(), 10);
            script.onerror = () => this.removeGooglePayContainer();
            document.head.appendChild(script);
        }

        async onGooglePayLoaded() {
            if (!this.isGooglePayAvailable()) {
                console.log("Google Pay is not available for this configuration");
                this.removeGooglePayContainer();
                return;
            }
            const paymentsClient = this.getGooglePaymentsClient();
            const googlePayConfig = await this.getGooglePayConfig();
            if (!googlePayConfig || !googlePayConfig.allowedPaymentMethods || googlePayConfig.allowedPaymentMethods.length === 0) {
                console.log("Google Pay is not available for this configuration");
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
                console.log("Google Pay is not available for this configuration", error);
                this.removeGooglePayContainer();
            }
        }

        isGooglePayAvailable() {
            return typeof wpg_paypal_sdk !== "undefined" && typeof wpg_paypal_sdk.Googlepay !== "undefined" && typeof google !== "undefined";
        }

        removeGooglePayContainer() {
            const containers = document.querySelectorAll('.google-pay-container');
            containers.forEach(container => {
                container.remove();
            });
        }

        getGooglePaymentsClient() {
            if (!this.paymentsClient && typeof google !== "undefined") {
                const paymentDataCallbacks = {
                    onPaymentAuthorized: this.onPaymentAuthorized.bind(this)
                };
                if (this.ppcp_manager.needs_shipping === "1") {
                    paymentDataCallbacks.onPaymentDataChanged = this.onPaymentDataChanged.bind(this);
                }
                this.paymentsClient = new google.payments.api.PaymentsClient({
                    environment: this.ppcp_manager.environment || "TEST",
                    paymentDataCallbacks: paymentDataCallbacks
                });
            }
            return this.paymentsClient;
        }

        async onPaymentDataChanged(intermediatePaymentData) {
            try {
                const {callbackTrigger, shippingAddress} = intermediatePaymentData;
                if (callbackTrigger !== 'SHIPPING_ADDRESS' || !shippingAddress) {
                    return {};
                }
                const updatedTotal = await this.fetchUpdatedTotalFromBackend({
                    address1: shippingAddress.address1 || '',
                    address2: shippingAddress.address2 || '',
                    city: shippingAddress.locality || '',
                    state: shippingAddress.administrativeArea || '',
                    postcode: shippingAddress.postalCode || '',
                    country: shippingAddress.countryCode || ''
                });
                return {
                    newTransactionInfo: {
                        totalPriceStatus: 'ESTIMATED',
                        totalPrice: updatedTotal,
                        currencyCode: this.ppcp_manager.currency
                    }
                };
            } catch (error) {
                console.error("Error in onPaymentDataChanged:", error);
                return {};
            }
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
                console.log("Google Pay is not available for this configuration");
                this.removeGooglePayContainer();
                return;
            }
            const paymentsClient = this.getGooglePaymentsClient();
            const googlePayConfig = await this.getGooglePayConfig();
            const allowedPaymentMethods = googlePayConfig?.allowedPaymentMethods;
            if (!allowedPaymentMethods?.length) {
                console.log("Google Pay is not available for this configuration");
                this.removeGooglePayContainer();
                return;
            }
            try {
                const response = await paymentsClient.isReadyToPay(this.getGoogleIsReadyToPayRequest(allowedPaymentMethods));
                if (!response.result) {
                    console.log("Google Pay is not available for this configuration");
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
            const context = container.getAttribute('data-context') || 'product';
            const labelMap = {
                product: this.ppcp_manager.google_pay_style_label,
                cart: this.ppcp_manager.google_pay_style_label,
                checkout: this.ppcp_manager.google_pay_style_label,
                express_checkout: this.ppcp_manager.google_pay_express_checkout_style_label,
                mini_cart: this.ppcp_manager.google_pay_mini_cart_style_label
            };
            const colorMap = {
                product: this.ppcp_manager.google_pay_style_color,
                cart: this.ppcp_manager.google_pay_style_color,
                checkout: this.ppcp_manager.google_pay_style_color,
                express_checkout: this.ppcp_manager.google_pay_express_checkout_style_color,
                mini_cart: this.ppcp_manager.google_pay_mini_cart_style_color
            };
            const shapeMap = {
                product: this.ppcp_manager.google_pay_style_shape,
                cart: this.ppcp_manager.google_pay_style_shape,
                checkout: this.ppcp_manager.google_pay_style_shape,
                express_checkout: this.ppcp_manager.google_pay_express_checkout_style_shape,
                mini_cart: this.ppcp_manager.google_pay_mini_cart_style_shape
            };
            const heightMap = {
                product: this.ppcp_manager.button_height,
                cart: this.ppcp_manager.button_height,
                checkout: this.ppcp_manager.button_height,
                express_checkout: this.ppcp_manager.express_checkout_button_height,
                mini_cart: this.ppcp_manager.mini_cart_button_height
            };
            const buttonType = labelMap[context] || 'plain';
            const buttonColor = colorMap[context] || 'black';
            const buttonShape = shapeMap[context] || 'rect';
            const buttonHeight = parseInt(heightMap[context]) || 40;
            let buttonRadius;
            if (buttonShape === 'rect') {
                buttonRadius = 4;
            } else {
                buttonRadius = Math.round(buttonHeight / 2);
            }
            const paymentsClient = this.getGooglePaymentsClient();
            const button = paymentsClient.createButton({
                buttonColor: buttonColor,
                buttonType: buttonType,
                buttonRadius: buttonRadius,
                buttonLocale: this.ppcp_manager.locale,
                buttonSizeMode: 'fill',
                onClick: this.onGooglePaymentButtonClicked.bind(this)
            });
            button.setAttribute('data-context', context);
            container.innerHTML = '';
            container.appendChild(button);
            var $targets = $('.google-pay-container');
            setTimeout(function () {
                $targets.css({background: '', 'background-color': ''});
                $targets.each(function () {
                    this.style.setProperty('--wpg-skel-fallback-bg', 'transparent');
                });
                $targets.addClass('bg-cleared');
            }, 1);
        }

        async onGooglePaymentButtonClicked(event) {
            try {
                this.showSpinner();
                const button = event?.target?.closest('button.gpay-card-info-container');
                const clickedWrapper = button?.parentElement;
                this.pageContext = clickedWrapper?.getAttribute('data-context') || 'unknown';
                const transactionInfo = await this.ppcpGettransactionInfo();
                if (transactionInfo.success === false) {
                    const messages = transactionInfo.data?.messages ?? transactionInfo.data ?? ['Unknown error'];
                    this.showError(messages);
                    this.hideSpinner();
                    throw new Error("");
                }
                if (transactionInfo?.success) {
                    this.ppcp_manager.cart_total = transactionInfo.data?.cart_total || this.ppcp_manager.cart_total;
                }
                const paymentDataRequest = await this.getGooglePaymentDataRequest();
                const paymentsClient = this.getGooglePaymentsClient();
                const paymentData = await paymentsClient.loadPaymentData(paymentDataRequest);
            } catch (error) {
                this.hideSpinner();
                if (error?.statusCode === "CANCELED") {
                    console.warn("Google Pay was cancelled by the user.");
                    return;
                }
                console.error("Google Pay Button Click Error:", error);
            }
        }

        async getGooglePaymentDataRequest() {
            const {allowedPaymentMethods, merchantInfo} = await this.getGooglePayConfig();
            const shippingRequired = this.ppcp_manager.needs_shipping === "1";
            const paymentDataRequest = {
                apiVersion: 2,
                apiVersionMinor: 0,
                allowedPaymentMethods: allowedPaymentMethods,
                transactionInfo: this.getGoogleTransactionInfo(),
                merchantInfo: merchantInfo,
                emailRequired: true,
                callbackIntents: ['PAYMENT_AUTHORIZATION']
            };
            if (shippingRequired) {
                paymentDataRequest.callbackIntents.push('SHIPPING_ADDRESS');
                paymentDataRequest.shippingAddressRequired = true;
                paymentDataRequest.shippingAddressParameters = {
                    phoneNumberRequired: true
                };
            }
            return paymentDataRequest;
        }

        async ppcpGettransactionInfo() {
            try {
                this.showSpinner();
                $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
                let data = '';
                const isBlockCheckout = this.ppcp_manager.is_block_enable === 'yes';
                switch (this.pageContext) {
                    case 'checkout':
                        data = isBlockCheckout ? $('form.wc-block-checkout__form').serialize() : $('form.checkout').serialize();
                        if (isBlockCheckout) {
                            const billingAddress = this.getBillingAddress();
                            const shippingAddress = this.getShippingAddress();
                            data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                            data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                            data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                        }
                        break;
                    case 'product':
                        $('<input>', {
                            type: 'hidden',
                            name: 'ppcp-add-to-cart',
                            value: $("[name='add-to-cart']").val()
                        }).appendTo('form.cart');
                        data = $('form.cart').serialize();
                        break;
                    case 'express_checkout':
                        break;
                    default:
                        data = $('form.woocommerce-cart-form').serialize();
                        break;
                }
                const transactionInfoUrl = `${this.ppcp_manager.get_transaction_info_url}&form=${encodeURIComponent(this.pageContext)}&used=google_pay`;
                const response = await fetch(transactionInfoUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: data,
                });
                return await response.json();
            } catch (error) {
                console.error('Error in ppcpGettransactionInfo:', error);
                return null;
            } finally {
                this.hideSpinner();
            }
        }

        getGoogleTransactionInfo() {
            return {
                currencyCode: this.ppcp_manager.currency || "USD",
                totalPriceStatus: "ESTIMATED",
                totalPrice: this.ppcp_manager.cart_total || "0.00",
            };
        }

        async onPaymentAuthorized(paymentData) {
            try {
                if (this.pageContext !== 'checkout') {
                    const billingRaw = paymentData?.paymentMethodData?.info?.billingAddress || {};
                    const shippingRaw = paymentData?.shippingAddress || {};
                    const email = paymentData?.email || '';
                    const billingAddress = {
                        name: billingRaw.name || '',
                        surname: '',
                        address1: billingRaw.address1 || '',
                        address2: billingRaw.address2 || '',
                        city: billingRaw.locality || '',
                        state: billingRaw.administrativeArea || '',
                        postcode: billingRaw.postalCode || '',
                        country: billingRaw.countryCode || '',
                        phoneNumber: billingRaw.phoneNumber || '',
                        emailAddress: email || ''
                    };
                    const shippingAddress = {
                        name: shippingRaw.name || '',
                        surname: '',
                        address1: shippingRaw.address1 || '',
                        address2: shippingRaw.address2 || '',
                        city: shippingRaw.locality || '',
                        state: shippingRaw.administrativeArea || '',
                        postcode: shippingRaw.postalCode || '',
                        country: shippingRaw.countryCode || '',
                        phoneNumber: shippingRaw.phoneNumber || ''
                    };
                    const updatedTotal = await this.fetchUpdatedTotalFromBackend({
                        shipping_address: {
                            address1: shippingAddress.address1 || '',
                            address2: shippingAddress.address2 || '',
                            city: shippingAddress.city || '',
                            state: shippingAddress.state || '',
                            postcode: shippingAddress.postcode || '',
                            country: shippingAddress.country || '',
                            name: shippingAddress.name || '',
                            phoneNumber: shippingAddress.phoneNumber || ''
                        },
                        billing_address: {
                            address1: billingAddress.address1 || '',
                            address2: billingAddress.address2 || '',
                            city: billingAddress.city || '',
                            state: billingAddress.state || '',
                            postcode: billingAddress.postcode || '',
                            country: billingAddress.country || '',
                            name: billingAddress.name || '',
                            phoneNumber: billingAddress.phoneNumber || '',
                            emailAddress: billingAddress.emailAddress || ''
                        }
                    });
                    await this.fetchUpdatedTotalFromBackend(shippingAddress, billingAddress);
                }
                const orderId = await this.googleapplecreateOrder();
                if (!orderId) {
                    throw new Error("Order creation failed.");
                }
                const result = await wpg_paypal_sdk.Googlepay().confirmOrder({
                    orderId,
                    paymentMethodData: paymentData.paymentMethodData,
                });
                if (result.status === "PAYER_ACTION_REQUIRED") {
                    await wpg_paypal_sdk.Googlepay().initiatePayerAction({orderId});
                }
                this.onApproveHandler({orderID: orderId}, 'google_pay');
                return {transactionState: "SUCCESS"};
            } catch (error) {
                this.showError(error.message || "Google Pay failed.");
                this.hideSpinner();
                return {
                    transactionState: "ERROR",
                    error: {
                        intent: "PAYMENT_AUTHORIZATION",
                        message: error.message || "Google Pay failed."
                    }
                };
            }
        }

        async fetchUpdatedTotalFromBackend(shippingAddress, billingAddress = null) {
            try {
                const response = await fetch(this.ppcp_manager.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'ppcp_get_updated_total',
                        security: this.ppcp_manager.ajax_nonce,
                        shipping_address: JSON.stringify(shippingAddress),
                        billing_address: billingAddress ? JSON.stringify(billingAddress) : '',
                        context: this.pageContext
                    })
                });
                const result = await response.json();
                if (result?.success && result.data?.total) {
                    return result.data.total;
                }
                return this.ppcp_manager.cart_total;
            } catch (error) {
                console.error('Error fetching updated total:', error);
                return this.ppcp_manager.cart_total;
        }
        }

        formatAmount(amount) {
            if (typeof amount === 'number') {
                return amount.toFixed(2);
            }
            if (typeof amount === 'string') {
                const parsed = parseFloat(amount);
                return isNaN(parsed) ? "0.00" : parsed.toFixed(2);
            }
            return "0.00";
        }

        prefetchProductTotal() {
            const isApplePayEnabled = this.ppcp_manager?.enabled_apple_pay === 'yes';
            const isProductPage = document.querySelector('.apple-pay-container[data-context="product"]');
            if (!isApplePayEnabled || !isProductPage)
                return;

            const baseProductId = parseInt(this.ppcp_manager?.product_id || 0);
            const defaultQty = parseFloat(document.querySelector('input.qty')?.value || '1');
            this.fetchProductTotal(baseProductId, defaultQty);

            jQuery('form.variations_form').on('found_variation', (event, variation) => {
                const variationId = variation.variation_id;
                const qty = parseFloat(jQuery('input.qty').val()) || 1;
                this.fetchProductTotal(variationId, qty);
            });
        }

        fetchProductTotal(productId, quantity = 1) {
            const data = new URLSearchParams({
                action: 'ppcp_get_product_total',
                product_id: productId,
                quantity: quantity
            });

            fetch(this.ppcp_manager.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data.toString()
            })
                    .then(res => res.json())
                    .then(response => {
                        if (response.success && response.data?.combined_total) {
                            this.ppcp_manager.cart_total = response.data.combined_total;
                        }
                    });
        }

        loadApplePaySdk() {
            const script = document.createElement('script');
            script.src = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';
            script.onload = () => this.onApplePayLoaded();
            script.onerror = () => {
                this.removeApplePayContainer();
                console.error("Failed to load Apple Pay SDK.");
            };
            document.head.appendChild(script);
        }

        update_apple_pay() {
            if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                this.addApplePayButton();
            } else {
                this.removeApplePayContainer();
            }
        }

        removeApplePayContainer() {
            setTimeout(() => {
                const containers = document.querySelectorAll('.apple-pay-container');
                containers.forEach(container => {
                    container.remove();
                });
            }, 500);
        }

        async onApplePayLoaded() {
            if (window.location.protocol !== 'https:') {
                console.log("Apple Pay requires HTTPS. Current protocol:", window.location.protocol);
                this.removeApplePayContainer();
                return;
            }
            if (!window.ApplePaySession) {
                console.log("Apple Pay is not supported on this device.");
                this.removeApplePayContainer();
                return;
            }
            if (!ApplePaySession.canMakePayments()) {
                console.log("Apple Pay cannot make payments on this device.");
                this.removeApplePayContainer();
                return;
            }
            const applepay = wpg_paypal_sdk.Applepay();
            try {
                const config = await applepay.config({environment: this.ppcp_manager.environment || "TEST"});
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
            const containers = document.querySelectorAll(".apple-pay-container");
            if (containers.length === 0) {
                return;
            }
            this.prefetchProductTotal();
            containers.forEach(container => {
                container.innerHTML = '';
                const applePayButton = document.createElement('apple-pay-button');
                const context = container.getAttribute('data-context') || 'product';
                const labelMap = {
                    product: this.ppcp_manager.apple_pay_style_label,
                    cart: this.ppcp_manager.apple_pay_style_label,
                    checkout: this.ppcp_manager.apple_pay_style_label,
                    express_checkout: this.ppcp_manager.apple_pay_express_checkout_style_label,
                    mini_cart: this.ppcp_manager.apple_pay_mini_cart_style_label
                };
                const colorMap = {
                    product: this.ppcp_manager.apple_pay_style_color,
                    cart: this.ppcp_manager.apple_pay_style_color,
                    checkout: this.ppcp_manager.apple_pay_style_color,
                    express_checkout: this.ppcp_manager.apple_pay_express_checkout_style_color,
                    mini_cart: this.ppcp_manager.apple_pay_mini_cart_style_color
                };
                const heightMap = {
                    product: this.ppcp_manager.button_height,
                    cart: this.ppcp_manager.button_height,
                    checkout: this.ppcp_manager.button_height,
                    express_checkout: this.ppcp_manager.express_checkout_button_height,
                    mini_cart: this.ppcp_manager.mini_cart_button_height
                };
                const shapeMap = {
                    product: this.ppcp_manager.apple_pay_style_shape,
                    cart: this.ppcp_manager.apple_pay_style_shape,
                    checkout: this.ppcp_manager.apple_pay_style_shape,
                    express_checkout: this.ppcp_manager.apple_pay_express_checkout_style_shape,
                    mini_cart: this.ppcp_manager.apple_pay_mini_cart_style_shape
                };
                const buttonType = labelMap[context] || 'plain';
                const buttonColor = colorMap[context] || 'black';
                const buttonHeight = parseInt(heightMap[context]) || 40;
                const buttonShape = shapeMap[context] || 'rect';
                const buttonRadius = buttonShape === 'pill' ? 20 : 4;

                container.classList.add(buttonShape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect');

                container.style.setProperty('--button-height', `${buttonHeight}px`);
                container.style.setProperty('--button-radius', `${buttonRadius}px`);
                container.style.height = `${buttonHeight}px`;

                applePayButton.setAttribute('type', buttonType);
                applePayButton.setAttribute('buttonstyle', buttonColor);
                applePayButton.setAttribute('data-context', context);
                container.appendChild(applePayButton);
                applePayButton.addEventListener('click', () => this.onApplePayButtonClicked(container));
                var $targets = $('.apple-pay-container');
                setTimeout(function () {
                    $targets.css({background: '', 'background-color': ''});
                    $targets.each(function () {
                        this.style.setProperty('--wpg-skel-fallback-bg', 'transparent');
                    });
                    $targets.addClass('bg-cleared');
                }, 1);
            });
        }

        onApplePayButtonClicked(container) {
            try {
                this.showSpinner();
                this.pageContext = container?.getAttribute('data-context') || 'unknown';
                if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
                    console.warn('Apple Pay is not available on this device or browser.');
                    this.hideSpinner();
                    return;
                }
                const applepay = wpg_paypal_sdk.Applepay();
                const needsShipping = this.ppcp_manager.needs_shipping === "1";
                const paymentRequest = {
                    countryCode: this.ppcp_manager.country || "US",
                    currencyCode: this.ppcp_manager.currency || "USD",
                    supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
                    merchantCapabilities: ['supports3DS'],
                    requiredBillingContactFields: ["postalAddress", "email", "phone", "name"],
                    total: {
                        label: this.ppcp_manager.store_label || "Total",
                        amount: this.formatAmount(this.ppcp_manager.cart_total),
                        type: "final"
                    }
                };
                if (needsShipping) {
                    paymentRequest.requiredShippingContactFields = ["postalAddress", "name", "phone", "email"];
                } else {
                    paymentRequest.requiredShippingContactFields = ["name", "phone", "email"];
                }
                const session = new ApplePaySession(4, paymentRequest);
                session.onvalidatemerchant = async (event) => {
                    try {
                        const validation = await applepay.validateMerchant({
                            validationUrl: event.validationURL,
                            displayName: this.ppcp_manager.store_label || "Store"
                        });
                        session.completeMerchantValidation(validation.merchantSession);
                    } catch (err) {
                        console.error('Merchant validation failed:', err);
                        session.abort();
                        this.hideSpinner();
                    }
                };

                if (needsShipping) {
                    let newTotal = {
                        label: this.ppcp_manager.store_label || "Store",
                        amount: "0.00",
                        type: "final"
                    };
                    session.onshippingcontactselected = async (event) => {
                        try {
                            if (this.pageContext === 'product') {
                                const transactionInfo = await this.ppcpGettransactionInfo();
                                if (transactionInfo.success === false) {
                                    const messages = transactionInfo.data?.messages ?? transactionInfo.data ?? ['Unknown error'];
                                    this.showError(messages);
                                    throw new Error(messages);
                                }
                                if (transactionInfo?.success) {
                                    this.ppcp_manager.cart_total = transactionInfo.data?.cart_total || this.ppcp_manager.cart_total;
                                }
                            }
                            const shipping = event.shippingContact;
                            if (!shipping || !shipping.countryCode || !shipping.postalCode) {
                                throw new Error("Shipping address is incomplete");
                            }
                            const updatedTotal = await this.fetchUpdatedTotalFromBackend({
                                city: shipping.locality || '',
                                state: shipping.administrativeArea || '',
                                postcode: shipping.postalCode || '',
                                country: shipping.countryCode || ''
                            });
                            newTotal.amount = updatedTotal;
                            const update = {
                                newTotal: newTotal
                            };
                            session.completeShippingContactSelection(update);
                        } catch (error) {
                            session.completeShippingContactSelection({});
                        }
                    };
                }

                session.onpaymentauthorized = async (event) => {
                    try {
                        if (this.pageContext === 'checkout' || this.pageContext === 'product') {
                            const transactionInfo = await this.ppcpGettransactionInfo();
                            if (transactionInfo.success === false) {
                                const messages = transactionInfo.data?.messages ?? transactionInfo.data ?? ['Unknown error'];
                                this.showError(messages);
                                throw new Error(messages);
                            }
                            if (transactionInfo?.success) {
                                this.ppcp_manager.cart_total = transactionInfo.data?.cart_total || this.ppcp_manager.cart_total;
                            }
                        }
                        const billingRaw = event.payment?.billingContact || {};
                        const shippingRaw = event.payment?.shippingContact || {};
                        if (this.pageContext !== 'checkout') {
                            const emailAddress = billingRaw.emailAddress || shippingRaw.emailAddress || billingRaw.email || shippingRaw.email || '';
                            const billingAddress = {
                                name: billingRaw.givenName || '',
                                surname: billingRaw.familyName || '',
                                address1: billingRaw.addressLines?.[0] || '',
                                address2: billingRaw.addressLines?.[1] || '',
                                city: billingRaw.locality || '',
                                state: billingRaw.administrativeArea || '',
                                postcode: billingRaw.postalCode || '',
                                country: billingRaw.countryCode || '',
                                phoneNumber: billingRaw.phoneNumber || '',
                                emailAddress: emailAddress
                            };
                            const shippingAddress = {
                                name: shippingRaw.givenName || '',
                                surname: shippingRaw.familyName || '',
                                address1: shippingRaw.addressLines?.[0] || '',
                                address2: shippingRaw.addressLines?.[1] || '',
                                city: shippingRaw.locality || '',
                                state: shippingRaw.administrativeArea || '',
                                postcode: shippingRaw.postalCode || '',
                                country: shippingRaw.countryCode || '',
                                phoneNumber: shippingRaw.phoneNumber || ''
                            };
                            await this.fetchUpdatedTotalFromBackend(shippingAddress, billingAddress);
                        }
                        const orderId = await this.googleapplecreateOrder();
                        if (!orderId) {
                            throw new Error("Order creation failed.");
                        }
                        const result = await applepay.confirmOrder({
                            orderId: orderId,
                            token: event.payment.token,
                            billingContact: event.payment.billingContact
                        });
                        const status = result?.approveApplePayPayment?.status;
                        if (status === "APPROVED") {
                            this.showSpinner();
                            const order_id = orderId;
                            const payer_id = '';
                            if (!order_id) {
                                console.error('[ApplePay] Missing order ID after approval.');
                                return;
                            }
                            if (this.isCheckoutPage()) {
                                const url = `${this.ppcp_manager.cc_capture}&paypal_order_id=${encodeURIComponent(order_id)}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                                $.post(url, (response) => {
                                    if (response?.data?.redirect) {
                                        session.completePayment({
                                            status: ApplePaySession.STATUS_SUCCESS
                                        });
                                        window.location.href = response.data.redirect;
                                    } else {
                                        if (response?.success === false) {
                                            const messages = response.data?.messages ?? ['An unknown error occurred.'];
                                            session.completePayment({
                                                status: ApplePaySession.STATUS_FAILURE
                                            });
                                            this.showError(messages);
                                            this.hideSpinner();
                                            return null;
                                        }
                                    }
                                });
                                return;
                            }
                            session.completePayment({
                                status: ApplePaySession.STATUS_SUCCESS
                            });
                            let redirectUrl = `${this.ppcp_manager.checkout_url}?paypal_order_id=${encodeURIComponent(order_id)}&from=${this.ppcp_manager.page}`;
                            if (payer_id) {
                                redirectUrl += `&paypal_payer_id=${encodeURIComponent(payer_id)}`;
                            }
                            window.location.href = redirectUrl;
                            this.hideSpinner();
                        } else {
                            throw new Error("Apple Pay confirmation returned non-APPROVED status.");
                        }
                    } catch (error) {
                        session.completePayment({
                            status: ApplePaySession.STATUS_FAILURE
                        });
                        this.showError(error?.message || String(error) || 'An unknown error occurred.');
                        this.hideSpinner();
                    }
                };

                session.oncancel = () => {
                    console.log("Apple Pay session cancelled.");
                    this.hideSpinner();
                };
                session.begin();
            } catch (err) {
                console.error('Apple Pay session initialization failed:', err);
                this.showError("Apple Pay could not be initialized.");
                this.hideSpinner();
            }
        }
    }

    $(function () {
        window.PPCPManager = PPCPManager;
        const ppcp_manager = window.ppcp_manager || {};
        window.ppcpManagerInstance = new PPCPManager(ppcp_manager);
    });
})(jQuery);