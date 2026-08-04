(function ($) {
    class PPCPManager {
        constructor(ppcp_manager) {
            // 9.0.66
            this.ppcp_manager = ppcp_manager;
            this.productAddToCart = true;
            this.lastApiResponse = null;
            this.ppcp_address = [];
            this.paymentsClient = null;
            this.allowedPaymentMethods = [];
            this.merchantInfo = null;
            this.pageContext = 'unknown';
            this.ppcp_used_payment_method = null;
            this.googleSelectedShippingId = '';
            this.googleShippingIdMap = {};
            this.appleSelectedShippingId = '';
            this.appleShippingIdMap = {};
            // How long the card-submit latch may stay set before a further Place order
            // click is allowed through. Long enough to cover a real 3-D Secure challenge
            // (a banking-app approval can take a minute or two), short enough that a
            // buyer whose challenge window was lost is not stuck on a dead button.
            this.ccSubmitLatchTimeout = 3 * 60 * 1000;
            // The CardFields instance that owns the currently mounted hosted fields.
            this.cardFieldsInstance = null;
            this.init();
            this.ppcp_cart_css();
        }

        init() {
            if (typeof this.ppcp_manager === 'undefined') {
                console.log("PPCP Manager configuration is undefined.");
                return false;
            }
            const lastError = (ppcp_manager?.last_error || '').trim();
            if (lastError) {
                console.log(lastError);
                $(document.body).trigger('ppcp_checkout_error', lastError);
            }
            this.debouncedTogglePlaceOrderButton = this.debounce_place_order(this.togglePlaceOrderButton.bind(this), 4);
            this.manageVariations('#ppcp_product, .google-pay-container, .apple-pay-container');
            this.manageSubscriptionOptions();
            this.prefetchProductTotal();
            this.bindCheckoutEvents();
            if (this.ppcp_manager.advanced_card_payments === 'yes') {
                this.debouncedUpdatePaypalCC = this.debounce_cc(this.syncPayPalCardFields.bind(this), 500);
            }
            this.debouncedUpdatePaypalCheckout = this.debounce(this.syncPayPalCheckoutButtons.bind(this), 500);
            if (this.ppcp_manager.enabled_google_pay === 'yes') {
                this.debouncedUpdateGooglePay = this.debounce_google(this.syncGooglePayButton.bind(this), 500);
            }
            if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                this.debouncedUpdateApplePay = this.debounce_apple(this.syncApplePayButton.bind(this), 500);
            }
            if (this.isCheckoutPage() === false) {
                this.debouncedUpdatePaypalCheckout();
                if (this.ppcp_manager.enabled_google_pay === 'yes') {
                    this.debouncedUpdateGooglePay();
                }
                if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                    this.debouncedUpdateApplePay();
                }
            } else {
                this.debouncedUpdatePaypalCheckout();
                if (this.ppcp_manager.enabled_google_pay === 'yes') {
                    this.debouncedUpdateGooglePay();
                }
                if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                    this.debouncedUpdateApplePay();
                }
                if (this.ppcp_manager.advanced_card_payments === 'yes') {
                    this.debouncedUpdatePaypalCC();
                }
            }
            setTimeout(function () {
                $('#wfacp_smart_buttons.wfacp-dynamic-checkout-loading .dynamic-checkout__skeleton').hide();
            }, 1000);
        }
        
        t(key, fallback = '') {
            const raw = this.ppcp_manager ? this.ppcp_manager[key] : undefined;
            if (raw === null || raw === undefined || raw === '') {
                return fallback;
            }
            const v = String(raw);
            return (v && v !== 'undefined' && v !== 'null') ? v : fallback;
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
            if (this.isValidAddress(prefix, address)) {
                return address;
            }
            // Fall back to the other address (e.g. when "Use same address for billing"
            // is checked on Block Checkout the billing address is empty in the store).
            // Re-key the fallback to the requested prefix, otherwise it is posted with
            // the wrong field names (shipping_* instead of billing_*) and the resulting
            // WooCommerce order is created without a billing address.
            const otherPrefix = prefix === 'billing' ? 'shipping' : 'billing';
            const otherAddress = this.getAddress(otherPrefix);
            if (this.isValidAddress(otherPrefix, otherAddress)) {
                return this.rekeyAddress(otherAddress, otherPrefix, prefix);
            }
            return address;
        }

        // Re-map an address object's field keys from one prefix to another, e.g.
        // { shipping_address_1: '...' } -> { billing_address_1: '...' }.
        rekeyAddress(address, fromPrefix, toPrefix) {
            if (!address) {
                return address;
            }
            const result = {};
            const search = `${fromPrefix}_`;
            Object.keys(address).forEach(key => {
                if (key.indexOf(search) === 0) {
                    result[`${toPrefix}_${key.substring(search.length)}`] = address[key];
                } else {
                    result[key] = address[key];
                }
            });
            return result;
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

        // Convert a Google Pay / Apple Pay wallet address to WooCommerce form-field format.
        // Used as a fallback when the Block Checkout form has not been filled in
        // before the customer clicks the express wallet button. Apple Pay populates
        // both `name` (given) and `surname` (family); Google Pay puts the full name
        // in `name` only.
        walletToWCFormat(walletAddr, prefix) {
            if (!walletAddr) {
                return {};
            }
            const rawName = (walletAddr.name || '').trim();
            const rawSurname = (walletAddr.surname || '').trim();
            let firstName = '';
            let lastName = '';
            if (rawName && rawSurname) {
                firstName = rawName;
                lastName = rawSurname;
            } else if (rawName) {
                const parts = rawName.split(/\s+/);
                firstName = parts.shift() || '';
                lastName = parts.join(' ');
            } else if (rawSurname) {
                lastName = rawSurname;
            }
            const result = {
                [`${prefix}_first_name`]: firstName,
                [`${prefix}_last_name`]: lastName,
                [`${prefix}_address_1`]: walletAddr.address1 || '',
                [`${prefix}_address_2`]: walletAddr.address2 || '',
                [`${prefix}_city`]: walletAddr.city || '',
                [`${prefix}_state`]: walletAddr.state || '',
                [`${prefix}_postcode`]: walletAddr.postcode || '',
                [`${prefix}_country`]: walletAddr.country || '',
                [`${prefix}_phone`]: walletAddr.phoneNumber || ''
            };
            if (prefix === 'billing' && walletAddr.emailAddress) {
                result.billing_email = walletAddr.emailAddress;
            }
            return result;
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

        debounce_place_order(func, delay) {
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
            $('form.checkout').on('click', 'input[name="payment_method"]', () => {
                this.debouncedTogglePlaceOrderButton();
                $(document.body).trigger('wpg_change_method');
            });
            $('#order_review').on('click', 'input[name="payment_method"]', () => {
                this.debouncedTogglePlaceOrderButton();
                $(document.body).trigger('wpg_change_method');
            });
            if (this.ppcp_manager.is_pay_page === 'yes') {
                this.debouncedTogglePlaceOrderButton();
            }
            $(document.body).on('ppcp_cc_checkout_updated ppcp_checkout_updated ppcp_cc_block_ready ppcp_block_ready', () => {
                this.debouncedTogglePlaceOrderButton();
            });
            $('form.checkout').on('checkout_place_order_wpg_paypal_checkout_cc', (event) => {
                event.preventDefault();
                return this.handleCheckoutSubmit(event);
            });

            // Any checkout error ends the in-flight card submit, so release the latch and
            // let the buyer retry rather than leaving the Place order button inert.
            $(document.body).on('checkout_error ppcp_checkout_error', () => {
                this.clearCardFieldsSubmitLatch();
            });

            $('#order_review').on('submit', (event) => {
                if (this.isPpcpCCSelected()) {
                    event.preventDefault();
                    return this.handleCheckoutSubmit();
                }
            });

            const eventSelectors = 'added_to_cart updated_cart_totals wc_fragments_refreshed wc_fragment_refresh wc_fragments_loaded updated_checkout ppcp_block_ready ppcp_checkout_updated ppcp_minicart_ready ppcp_minicart_updated wc_update_cart wc_cart_emptied wpg_change_method fkwcs_express_button_init';
            const checkoutSelectors = 'updated_cart_totals wc_fragments_refreshed wc_fragments_loaded updated_checkout ppcp_cc_block_ready ppcp_cc_checkout_updated update_checkout wpg_change_method';
            $(document.body).on(eventSelectors, (event) => {
                this.debouncedUpdatePaypalCheckout();
                if (this.ppcp_manager.enabled_google_pay === 'yes') {
                    this.debouncedUpdateGooglePay();
                }
                if (this.ppcp_manager.enabled_apple_pay === 'yes') {
                    this.debouncedUpdateApplePay();
                }
            });
            if (this.ppcp_manager.advanced_card_payments === 'yes') {
                $(document.body).on(checkoutSelectors, () => {
                    this.debouncedUpdatePaypalCC();
                });
            }

        }

        handleCheckoutSubmit() {
            var fastlaneToken = $('#wpg_ppcp_fastlane_token').val() || window.wpgPPCPFastlaneToken || '';
            if (fastlaneToken) {
                // Fastlane already tokenized the card; the single-use token rides
                // along with the normal WooCommerce submit and is charged
                // server-side, so skip the CardFields round-trip entirely.
                return true;
            }
            var selected = $('input[name="wc-wpg_paypal_checkout_cc-payment-token"]:checked').val();
            if (selected && selected !== 'new') {
                return true; // only return true for saved token (not for 'new')
            }
            if (this.isPpcpCCSelected() && this.isCardFieldEligible()) {
                // The latch prevents a double submit while the CardFields round-trip —
                // which includes any 3-D Secure challenge — is in flight. It used to be
                // cleared only by handleCardFieldsError, so a challenge that never
                // settled left it set forever: when the buyer authorises in their banking
                // app the OS can discard the challenge popup, cardFields.submit() never
                // resolves or rejects, and from then on every Place order click silently
                // did nothing. Expire the latch so the buyer can always try again.
                const $form = $(this.getCheckoutSelectorCss());
                if ($form.hasClass('paypal_cc_submitting')) {
                    const startedAt = parseInt($form.attr('data-wpg-cc-submitting-at') || '0', 10);
                    if (startedAt && (Date.now() - startedAt) < this.ccSubmitLatchTimeout) {
                        return false;
                    }
                    this.clearCardFieldsSubmitLatch();
                }
                $form.addClass('paypal_cc_submitting').attr('data-wpg-cc-submitting-at', String(Date.now()));
                $(document.body).trigger('submit_paypal_cc_form');
                return false;
            }
            return true;
        }

        /**
         * Release the card-submit latch so the Place order button works again.
         */
        clearCardFieldsSubmitLatch() {
            $('form.checkout, form.cart, #order_review')
                .removeClass('paypal_cc_submitting')
                .removeAttr('data-wpg-cc-submitting-at');
        }

        syncPayPalCheckoutButtons() {
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

        }

        syncPayPalCardFields() {
            if (this.isCardFieldEligible()) {
                this.renderCardFields();
                $('#place_order, .wc-block-components-checkout-place-order-button').show();
            } else {
                $('.wc_payment_method.payment_method_wpg_paypal_checkout_cc').hide();
                $('#radio-control-wc-payment-method-options-wpg_paypal_checkout_cc').parent('label').parent('div').hide();
                if (this.isPpcpCCSelected())
                    $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
            }

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
            const PO_CLASSIC = '#place_order';
            const PO_BLOCKS = '.wc-block-components-checkout-place-order-button';
            const WALLETS = '#ppcp_checkout, #ppcp_order_pay, .google-pay-container.checkout, .apple-pay-container.checkout';
            const setVisible = (sel, visible, displayShown = '') => {
                const $el = jQuery(sel);
                $el.toggleClass('wpg_place_order_hide', !visible);
                $el.css('display', visible ? (displayShown || '') : 'none');
            };
            const showPO = () => {
                setVisible(PO_CLASSIC, true, 'inline-block');
                setVisible(PO_BLOCKS, true, 'inline-flex');
            };
            const hidePO = () => {
                setVisible(PO_CLASSIC, false);
                setVisible(PO_BLOCKS, false);
            };
            const showWallets = () => setVisible(WALLETS, true);
            const hideWallets = () => setVisible(WALLETS, false);
            if (isPpcpSelected) {
                if (usePlaceOrder) {
                    hideWallets();
                    showPO();
                } else {
                    hidePO();
                    showWallets();
                }
            } else {
                showPO();
                hideWallets();
            }
            if (isPpcpCCSelected && this.isCardFieldEligible()) {
                showPO();
                hideWallets();
            }
        }

        onShippingChange(data, actions) {
            if (!data || !data.shipping_address) {
                return actions.reject();
            }

            // Simple address mapping (same as before)
            const shippingAddress = {
                city: data.shipping_address.city || '',
                state: data.shipping_address.state || '',
                countryCode: data.shipping_address.country_code || '',
                postalCode: data.shipping_address.postal_code || ''
            };

            // NEW: detect which shipping method was selected in the PayPal UI
            const selectedOption =
                data.selected_shipping_option ||
                data.selectedShippingOption ||
                null;

            const selectedShippingId =
                selectedOption && selectedOption.id ? selectedOption.id : '';

            // Validate address via AJAX (this will also update PayPal order)
            return fetch(this.ppcp_manager.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'ppcp_validate_shipping_address',
                    security: this.ppcp_manager.ajax_nonce,
                    shipping_address: JSON.stringify(shippingAddress),
                    // NEW: send selected shipping method id to PHP
                    selected_shipping_id: selectedShippingId
                })
            })
                .then(res => res.json())
                .then(result => {
                    if (result && result.success) {
                        return actions.resolve();
                    } else {
                        return actions.reject();
                    }
                })
                .catch(() => {
                    return actions.reject();
                });
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
                const isMiniCart = selector === '#ppcp_mini_cart' || selector === '#ppcp_mini_cart_block';
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
                    targets.push('#ppcp_cart', '#ppcp_mini_cart', '#ppcp_mini_cart_block', '.google-pay-container.cart', '.apple-pay-container.cart', '.google-pay-container.mini_cart', '.apple-pay-container.mini_cart');
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

                const addShippingCallbacks = selector !== '#ppcp_checkout';

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
                            onError: (err) => this.onErrorHandler(err),
                            // Add this line only:
                            onShippingChange: addShippingCallbacks ? (data, actions) => this.onShippingChange(data, actions) : null
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
                    const buttonOptions = {
                        style: ppcpStyle,
                        createOrder: () => this.createOrder(selector),
                        onApprove: (data, actions) => this.onApproveHandler(data, actions),
                        onCancel: () => this.onCancelHandler(),
                        onError: (err) => this.onErrorHandler(err)
                    };
                    if (addShippingCallbacks) {
                        buttonOptions.onShippingChange = (data, actions) => this.onShippingChange(data, actions);
                    }
                    wpg_paypal_sdk.Buttons(buttonOptions).render(selector);
                }
            });
            var $targets = $('#ppcp_product, #ppcp_cart, #ppcp_mini_cart, #ppcp_mini_cart_block, #ppcp_checkout, #ppcp_checkout_top, #ppcp_checkout_top_alternative');
            setTimeout(function () {
                $targets.css({background: '', 'background-color': ''});
                $targets.each(function () {
                    this.style.setProperty('--wpg-skel-fallback-bg', 'transparent');
                });
                $targets.addClass('bg-cleared');
            }, 1);
        }
        
        getFromValue(selector) {
            if (selector === '#ppcp_product') return 'product';
            if (selector === '#ppcp_cart') return 'cart';
            if (selector === '#ppcp_mini_cart' || selector === '#ppcp_mini_cart_block') return 'cart';
            if (selector === '#ppcp_checkout_top') return 'express_checkout';
            if (selector === '#ppcp_checkout') return 'checkout';
            if (selector === '#ppcp_order_pay') return 'pay_page';

            return 'checkout'; // fallback default
        }

        createOrder(selector) {
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data;
            const isMiniCart = selector === '#ppcp_mini_cart' || selector === '#ppcp_mini_cart_block';
            if (isMiniCart) {
                data = '';
            } else if (selector === '#ppcp_checkout_top') {
            } else if (this.isCheckoutPage()) {
                data = $(selector).closest('form').serialize();
                if (this.ppcp_manager.is_block_enable === 'yes') {
                    const notes = jQuery('.wc-block-components-textarea').val() || '';
                    data += '&customer_note=' + encodeURIComponent(notes);
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
            const from = this.getFromValue(selector);

            let createOrderUrl = this.ppcp_manager.create_order_url_for_paypal
                .replace(/([?&])from=[^&]*/i, '$1') // remove old `from`
                .replace(/[?&]$/, '');              // cleanup

            createOrderUrl += (createOrderUrl.includes('?') ? '&' : '?')
                + 'from=' + from
                + '&ppcp_used_payment_method=' + encodeURIComponent(fundingMethod);

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
                    const selector = `[data-context="${this.pageContext}"]`;
                    data = $(selector).closest('form').serialize();
                    const isBlockCheckout = this.ppcp_manager.is_block_enable === 'yes';
                    if (isBlockCheckout && $('form.wc-block-checkout__form').length) {
                        const notes = jQuery('.wc-block-components-textarea').val() || '';
                        data += '&customer_note=' + encodeURIComponent(notes);
                        let billingAddress = this.getBillingAddress();
                        let shippingAddress = this.getShippingAddress();
                        // User-entered Block Checkout fields take precedence. Only fall back to
                        // the wallet address when the customer clicked an express wallet button
                        // without filling in the form, otherwise the WC order is created blank.
                        if ((!billingAddress || !billingAddress.billing_address_1) && this.walletBillingAddress) {
                            const walletBilling = this.walletToWCFormat(this.walletBillingAddress, 'billing');
                            if (walletBilling.billing_address_1) {
                                billingAddress = walletBilling;
                            }
                        }
                        if ((!shippingAddress || !shippingAddress.shipping_address_1) && this.walletShippingAddress) {
                            const walletShipping = this.walletToWCFormat(this.walletShippingAddress, 'shipping');
                            if (walletShipping.shipping_address_1) {
                                shippingAddress = walletShipping;
                            }
                        }
                        data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                        data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                        data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                    } else if ($('form.checkout').length) {
                        data = $('form.checkout').serialize();
                    } else if ($('form.woocommerce-cart-form').length) {
                        data = $('form.woocommerce-cart-form').serialize();
                    }
                    break;
                case 'express_checkout':
                case 'product':
                    break;
                default:
                    data = $('form.woocommerce-cart-form').serialize();
                    break;
            }
            
            let createOrderUrl = this.ppcp_manager.create_order_url_for_google_pay;

    createOrderUrl = createOrderUrl
        .replace(/([?&])from=[^&]*/i, '$1') 
        .replace(/[?&]$/, '');                

    createOrderUrl += (createOrderUrl.includes('?') ? '&' : '?')
        + 'from=' + encodeURIComponent(this.pageContext); 

            return fetch(createOrderUrl, {
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
                this.hideSpinner();
                this.showError(this.ppcp_manager.unknown_error || 'We could not process your PayPal approval. Please try again.');
                return;
            }
            if (this.isCheckoutPage() || this.ppcp_manager.skip_order_review === 'yes') {
                const url = `${this.ppcp_manager.cc_capture}&paypal_order_id=${encodeURIComponent(order_id)}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                $.post(url, (response) => {
                    if (response?.data?.redirect) {
                        window.location.href = response.data.redirect;
                        return;
                    }
                    // Any non-redirect response (explicit failure, or an unexpected/empty
                    // body): stop the spinner and surface an error rather than hanging.
                    this.hideSpinner();
                    const messages = response?.data?.messages
                        ?? (this.ppcp_manager.unknown_error || 'An error occurred while completing your payment. If your account was charged, please contact us before trying again.');
                    this.showError(messages);
                    if (response?.success === false) {
                        let redirectUrl = `${this.ppcp_manager.checkout_url}?paypal_order_id=${encodeURIComponent(order_id)}&from=${this.ppcp_manager.page}`;
                        if (payer_id) {
                            redirectUrl += `&paypal_payer_id=${encodeURIComponent(payer_id)}`;
                        }
                        window.location.href = redirectUrl;
                    }
                }).fail(() => {
                    // Network/HTTP failure. The capture may have already succeeded server
                    // side, so do not encourage a blind retry that could double-charge.
                    this.hideSpinner();
                    this.showError('We could not confirm your payment. If your account was charged, please contact us before trying again.');
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
            } else if (jQuery('form.checkout').length) {
                jQuery('form.checkout').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
            } else if (jQuery(containerSelector).length) {
                jQuery(containerSelector).block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
            }
            
            if (jQuery('#wfacp-sec-wrapper').length) {
                jQuery('#wfacp-sec-wrapper').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
            }
        }

        hideSpinner(containerSelector = '.woocommerce') {
            if (jQuery('.wc-block-checkout__main').length || jQuery('.wp-block-woocommerce-cart').length) {
                jQuery('.wc-block-checkout__main, .wp-block-woocommerce-cart').unblock();
            } else if (jQuery('form.checkout').length) {
                jQuery('form.checkout').unblock();
            } else if (jQuery(containerSelector).length) {
                jQuery(containerSelector).unblock();
            }
            
            if (jQuery('#wfacp-sec-wrapper').length) {
                jQuery('#wfacp-sec-wrapper').unblock();
            }
        }

        onCancelHandler() {
            window.location.reload(true);
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
            } else if ($('form#wfacp_checkout_form').length) {
                $checkout_form = $('form#wfacp_checkout_form').first();
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
            // renderCardFields() re-runs on every checkout refresh, but the hosted fields
            // are only mounted once. Only the CardFields instance that actually mounted
            // its fields can submit them, so when they survived the refresh the live
            // instance must be left alone — building another one here and pointing Place
            // order at it hands the click to an object the SDK refuses to submit, and
            // nothing happens. A refresh that wipes the fields empties the container, and
            // then we do want a fresh instance.
            const fieldsMounted = ($('#wpg_paypal_checkout_cc-card-number').html() || '').trim() !== '';
            if (fieldsMounted && this.cardFieldsInstance) {
                return;
            }
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
                onApprove: (payload) => {
                    if (!payload || !payload.orderID) {
                        console.error("No valid payload returned during onApprove:", payload);
                        return;
                    }
                    // Returned so cardFields.submit() only settles once the capture has
                    // finished — the block checkout waits on that. The no-op catch marks
                    // the rejection handled so an SDK build that ignores this return value
                    // cannot leave an "uncaught (in promise)" error in the console; the
                    // rejection is still delivered to any SDK build that does await it.
                    const capture = this.submitCardFields(payload);
                    capture.catch(() => {});
                    return capture;
                },
                onError: (err) => {
                    this.handleCardFieldsError(err, checkoutSelector);
                }
            });
            if (cardFields.isEligible()) {
                const numberField = cardFields.NumberField();
                $("#wpg_paypal_checkout_cc-card-number").empty();
                $("#wpg_paypal_checkout_cc-card-expiry").empty();
                $("#wpg_paypal_checkout_cc-card-cvc").empty();
                numberField.render("#wpg_paypal_checkout_cc-card-number");
                numberField.setAttribute("placeholder", "1234 1234 1234 1234");
                cardFields.ExpiryField().render("#wpg_paypal_checkout_cc-card-expiry");
                cardFields.CVVField().render("#wpg_paypal_checkout_cc-card-cvc");
                // Remember which instance owns the mounted fields; the guard above uses
                // it to leave a working card form untouched on the next refresh.
                this.cardFieldsInstance = cardFields;
                setTimeout(function () {
                    $('.wpg-paypal-cc-field label, .wpg-ppcp-card-cvv-icon').show();
                }, 1600);
                setTimeout(function () {
                    $('.wpg_ppcp_sanbdox_notice').show();
                }, 1900);
            } else {
                console.log('Advanced Card Payments not Eligible', cardFields.isEligible());
                this.cardFieldsInstance = null;
                $('.payment_box.payment_method_wpg_paypal_checkout_cc').hide();
                if (this.isPpcpCCSelected()) {
                    $('#payment_method_wpg_paypal_checkout').prop('checked', true).trigger('click');
                }
            }
            // renderCardFields() re-runs on every updated_checkout / fragment refresh, so
            // binding without unbinding accumulated one handler per re-render: a single
            // Place order click then fired cardFields.submit() once per handler, across
            // CardFields instances that were never rendered (the render above is guarded)
            // and whose createOrder callbacks raced to mint competing PayPal orders into
            // the same session slot. Only the newest instance may handle the submit.
            $(document.body).off('submit_paypal_cc_form.wpgppcp').on('submit_paypal_cc_form.wpgppcp', () => {
                cardFields.submit().catch((error) => {
                    this.handleCardFieldsError(error, checkoutSelector);
                });
            });
            // Awaitable entry point for the block checkout. It resolves the live
            // instance at call time rather than closing over this render's, so it stays
            // correct across the re-renders that rebuild the fields.
            window.wpgPPCPSubmitCardFields = () => this.submitCardFieldsFlow();
        }

        createCardOrder(checkoutSelector) {
            this.showSpinner();
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove();
            let data;
            if (this.ppcp_manager.is_block_enable === 'yes') {
                data = $('form.wc-block-checkout__form').serialize();
                // WooCommerce Blocks disables every field while the checkout is
                // processing, and jQuery.serialize() skips disabled inputs — so by the
                // time this runs the serialized form is empty. The server keys its whole
                // block address mapping on radio-control-wc-payment-method-options, so
                // without it the billing details below are never applied and create-order
                // fails with "Billing … is a required field" / "Invalid payment method".
                // Read the selection directly (:checked still works on a disabled radio)
                // and append it when serialize() did not supply it.
                if (!/(^|&)radio-control-wc-payment-method-options=/.test(data)) {
                    let selectedMethod = $('input[name="radio-control-wc-payment-method-options"]:checked').val();
                    if (!selectedMethod && typeof wp !== 'undefined' && wp.data?.select) {
                        try {
                            selectedMethod = wp.data.select('wc/store/payment')?.getActivePaymentMethod?.();
                        } catch (e) {}
                    }
                    if (selectedMethod) {
                        data += '&radio-control-wc-payment-method-options=' + encodeURIComponent(selectedMethod);
                    }
                }
                const notes = jQuery('.wc-block-components-textarea').val() || '';
                data += '&customer_note=' + encodeURIComponent(notes);
                const billingAddress = this.getBillingAddress();
                const shippingAddress = this.getShippingAddress();
                data += '&billing_address=' + encodeURIComponent(JSON.stringify(billingAddress));
                data += '&shipping_address=' + encodeURIComponent(JSON.stringify(shippingAddress));
                data += `&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                // Vaulting has to be requested when the PayPal order is created, so the
                // shopper's "Save payment information to my account" choice must ride along
                // with this request. On the block checkout that checkbox is a React control
                // with no name attribute, so serialize() above cannot see it; the block
                // integration mirrors the choice onto window.wpgPPCPShouldSaveCard for us.
                // Without this the card was simply never saved. The classic checkout needs
                // nothing here — it renders a real named input that serialize() picks up.
                if (window.wpgPPCPShouldSaveCard) {
                    data += '&wc-wpg_paypal_checkout_cc-new-payment-method=true';
                }
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
                            const messages = data?.data?.messages ?? data?.data;
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

        /**
         * Capture the approved PayPal order and send the buyer to the thank-you page.
         *
         * Returns a promise so callers can wait for the payment to actually finish.
         * The block checkout needs that: it has to keep WooCommerce Blocks out of the
         * way until the CardFields round-trip (3-D Secure included) has completed.
         *
         * Rejections are already reported to the shopper here, so they carry the
         * "Expected reject" marker that handleCardFieldsError() treats as
         * already-handled — that keeps the classic checkout, whose submit handler
         * pipes rejections into handleCardFieldsError(), from showing the same error
         * a second time.
         */
        submitCardFields(payload) {
            return new Promise((resolve, reject) => {
                const alreadyReported = (message) => reject(new Error('Expected reject: ' + message));
                $.post(`${this.ppcp_manager.cc_capture}&paypal_order_id=${payload.orderID}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`, (data) => {
                    if (data?.data?.redirect) {
                        // A refused capture — PayPal declining the card for fraud or AVS,
                        // say — is still answered with a redirect, back to the checkout
                        // rather than to the thank-you page. Navigate either way (the
                        // decline notice is rendered on the page we land on), but only
                        // report success when the payment actually succeeded: the block
                        // checkout turns a resolved promise into "payment method ready"
                        // and would place the order for a card that was declined.
                        const declined = data?.data?.result === 'failure';
                        window.location.href = data.data.redirect;
                        if (declined) {
                            alreadyReported('the payment was refused');
                            return;
                        }
                        resolve(data.data.redirect);
                        return;
                    }
                    // No redirect (server error or unexpected/empty body): show the error
                    // instead of navigating to '/undefined'. Release the submit latch too,
                    // otherwise the Place order button stays dead after the error.
                    this.hideSpinner();
                    this.clearCardFieldsSubmitLatch();
                    const messages = data?.data?.messages
                        ?? (this.ppcp_manager.unknown_error || 'An error occurred while completing your payment. If your account was charged, please contact us before trying again.');
                    this.showError(messages);
                    alreadyReported(Array.isArray(messages) ? messages.join(' ') : String(messages));
                }).fail(() => {
                    this.hideSpinner();
                    this.clearCardFieldsSubmitLatch();
                    const message = 'We could not confirm your payment. If your account was charged, please contact us before trying again.';
                    this.showError(message);
                    alreadyReported(message);
                });
            });
        }

        /**
         * Run the whole card round-trip and resolve only once it has settled.
         *
         * Exposed on window as wpgPPCPSubmitCardFields for the block checkout. The
         * classic checkout keeps using the submit_paypal_cc_form event and is not
         * affected by this method.
         */
        submitCardFieldsFlow() {
            const checkoutSelector = this.getCheckoutSelectorCss();
            if (!this.cardFieldsInstance) {
                return Promise.reject(new Error('PayPal card fields are not ready yet. Please try again.'));
            }
            return this.cardFieldsInstance.submit().catch((error) => {
                // handleCardFieldsError() puts the message on the page itself, so flag the
                // rejection as already-reported and let the caller stay quiet about it.
                this.handleCardFieldsError(error, checkoutSelector);
                // Read .message rather than testing instanceof Error — the SDK rejects
                // from inside its own iframe, so those are not instances of our Error.
                const raw = (error && error.message) ? String(error.message) : String(error);
                throw raw.indexOf('Expected reject') !== -1 ? error : new Error('Expected reject: ' + raw);
            });
        }

        handleCardFieldsError(errorString, checkoutSelector) {
            $('#place_order, #wc-wpg_paypal_checkout-cc-form').unblock();
            $(checkoutSelector).removeClass('processing paypal_cc_submitting CardFields createOrder').unblock();
            this.clearCardFieldsSubmitLatch();

            const t = (key, fallback) => {
              const v = (this.ppcp_manager && this.ppcp_manager[key]) ? String(this.ppcp_manager[key]) : '';
              return v ? v : fallback;
            };

            const UNKNOWN_FALLBACK = "An unknown error occurred with your payment. Please try again.";
            const INVALID_NUM_FALLBACK = "Please enter a valid card number.";
            const INVALID_CVV_FALLBACK = "Please enter a valid CVV.";
            const INVALID_EXP_FALLBACK = "Please enter a valid expiration date.";

            let message = t('unknown_error', UNKNOWN_FALLBACK);

            let raw = errorString instanceof Error ? errorString.message : String(errorString);
            if (raw.includes('Expected reject')) {
              return true;
            }

            try {
              if (raw.includes('INVALID_NUMBER')) {
                message = t('invalid_number', INVALID_NUM_FALLBACK);
              } else if (raw.includes('INVALID_CVV')) {
                message = t('invalid_cvv', INVALID_CVV_FALLBACK);
              } else if (raw.includes('INVALID_EXPIRATION') || raw.includes('INVALID_EXPIRY_DATE')) {
                message = t('invalid_expiry', INVALID_EXP_FALLBACK);
              } else {
                const jsonStart = raw.indexOf('{');
                const jsonEnd = raw.lastIndexOf('}') + 1;

                if (jsonStart !== -1 && jsonEnd !== -1 && jsonEnd > jsonStart) {
                  const jsonString = raw.slice(jsonStart, jsonEnd);
                  const error = JSON.parse(jsonString);

                  if (
                    (error.message && error.message.includes('Expected reject')) ||
                    (error.details?.[0]?.description && error.details[0].description.includes('Expected reject'))
                  ) {
                    return true;
                  }

                  if (error.success === false && error.data && error.data.messages && Array.isArray(error.data.messages)) {
                    if (error.data.messages.some(msg => msg.includes('Expected reject'))) {
                      return true;
                    }
                    message = error.data.messages
                      .map(msg => msg.replace(/<\/?[^>]+(>|$)/g, ''))
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
                          message = t('invalid_number', INVALID_NUM_FALLBACK);
                          break;
                        case 'INVALID_CVV':
                          message = t('invalid_cvv', INVALID_CVV_FALLBACK);
                          break;
                        case 'INVALID_EXPIRY_DATE':
                          message = t('invalid_expiry', INVALID_EXP_FALLBACK);
                          break;
                        case 'VALIDATION_ERROR':
                          if (cardError.field?.includes('/card/number')) {
                            message = t('invalid_number', INVALID_NUM_FALLBACK);
                          } else if (cardError.field?.includes('/card/expiry')) {
                            message = t('invalid_expiry', INVALID_EXP_FALLBACK);
                          } else if (cardError.field?.includes('/card/security_code')) {
                            message = t('invalid_cvv', INVALID_CVV_FALLBACK);
                          }
                          break;
                      }
                    }
                  }

                  // If still default unknown message, try PayPal-provided message
                  if (message === t('unknown_error', UNKNOWN_FALLBACK)) {
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
                message = t('invalid_number', INVALID_NUM_FALLBACK);
              } else if (raw.includes('INVALID_CVV')) {
                message = t('invalid_cvv', INVALID_CVV_FALLBACK);
              } else if (raw.includes('INVALID_EXPIRATION') || raw.includes('INVALID_EXPIRY_DATE')) {
                message = t('invalid_expiry', INVALID_EXP_FALLBACK);
              } else {
                const lastColon = raw.lastIndexOf(':');
                message = lastColon > 0 ? raw.slice(lastColon + 1).trim() : raw;
              }
            }

            message = String(message || '').replace(/\.$/, '').trim();
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
            $('.payment_box.payment_method_wpg_paypal_checkout').each(function () {
                if (($(this).html() || '').trim() === '') {
                    $(this).hide();
                }
            });
        }

        manageVariations(selector) {
            if ($('.variations_form').length) {
                const self = this;
                const getCurrentProductId = () => {
                    const variationInput = document.querySelector('input.variation_id');
                    const variationId = parseInt(variationInput?.value || 0, 10);
                    return variationId > 0 ? variationId : parseInt(self.ppcp_manager?.product_id || 0, 10);
                };
                const getQty = () => parseFloat(document.querySelector('input.qty')?.value || '1') || 1;

                $('.variations_form, .single_variation').on('show_variation', function (event, variation) {
                    if (variation.is_purchasable && variation.is_in_stock) {
                        $(selector).show();
                        self.fetchProductTotal(variation.variation_id || getCurrentProductId(), getQty());
                    } else {
                        $(selector).hide();
                    }
                }).on('hide_variation', function () {
                    $(selector).hide();
                });

                $(document).off('change.wpg_product_qty input.wpg_product_qty', 'input.qty')
                    .on('change.wpg_product_qty input.wpg_product_qty', 'input.qty', () => {
                        self.fetchProductTotal(getCurrentProductId(), getQty());
                    });

                // Product-option compat layers (Add-ons / TM EPO / WAPF) fire this
                // after updating their hidden cost fields; re-fetch so the wallet
                // total reflects the newly selected options.
                $(document.body).off('ppcp_checkout_updated.wpg_product_total')
                    .on('ppcp_checkout_updated.wpg_product_total', () => {
                        self.fetchProductTotal(getCurrentProductId(), getQty());
                    });
            }
        }

        manageSubscriptionOptions() {
            const $radios = $('input[name^="convert_to_sub"]');
            if (!$radios.length) {
                return;
            }
            const toggleButtons = () => {
                const val = $('input[name^="convert_to_sub"]:checked').val();
                const isSubscription = val && val !== '0';
                $('.google-pay-container.product, .apple-pay-container.product').toggle(!isSubscription);
                $('#ppcp_product').find('[data-funding-source]').each(function () {
                    const source = $(this).attr('data-funding-source');
                    if (source === 'paypal' || source === 'card') {
                        $(this).show();
                    } else {
                        $(this).toggle(!isSubscription);
                    }
                });
            };
            $radios.on('change', toggleButtons);
            const observer = new MutationObserver(() => toggleButtons());
            const productContainer = document.getElementById('ppcp_product');
            if (productContainer) {
                observer.observe(productContainer, { childList: true, subtree: true });
            }
            toggleButtons();
        }

        isPayPalGooglePaySdkReady() {
            return typeof wpg_paypal_sdk !== "undefined" && typeof wpg_paypal_sdk.Googlepay !== "undefined" && typeof google !== "undefined";
        }

        removeGooglePayContainer() {
            const containers = document.querySelectorAll('.google-pay-container');
            containers.forEach(container => {
                container.remove();
            });
        }

        getGooglePaymentsClient( { requireOnPaymentDataChanged = false } = {}) {
            if (typeof google === "undefined") {
                return null;
            }
            if (!this.paymentsClient || this.paymentsClientRequiresDataChanged !== requireOnPaymentDataChanged) {
                const paymentDataCallbacks = {
                    onPaymentAuthorized: this.onPaymentAuthorized.bind(this)
                };
                if (requireOnPaymentDataChanged) {
                    paymentDataCallbacks.onPaymentDataChanged = this.onPaymentDataChanged.bind(this);
                }
                this.paymentsClient = new google.payments.api.PaymentsClient({
                    environment: this.ppcp_manager.environment || "TEST",
                    paymentDataCallbacks
                });
                this.paymentsClientRequiresDataChanged = requireOnPaymentDataChanged;
            }
            return this.paymentsClient;
        }

        async onPaymentDataChanged(intermediatePaymentData) {
            try {
                const {callbackTrigger, shippingAddress, shippingOptionData} = intermediatePaymentData || {};
                const mapAddr = (addr = {}) => ({
                    address1: addr.address1 || '',
                    address2: addr.address2 || '',
                    city: addr.locality || '',
                    state: addr.administrativeArea || '',
                    postcode: addr.postalCode || '',
                    country: addr.countryCode || ''
                });
                if (callbackTrigger === 'INITIALIZE') {
                    if (shippingAddress) {
                        const mapped = mapAddr(shippingAddress);
                        const vr = await this.validateShippingAddressFromBackend({
                            city: mapped.city,
                            state: mapped.state,
                            countryCode: mapped.country,
                            postalCode: mapped.postcode
                        });
                        if (!vr || vr.success !== true) {
                            const msg = (vr && vr.data) ? String(vr.data) : this.t('shipping_unserviceable', 'We do not ship to this address.');
                            return {
                                error: {
                                    reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                    message: msg,
                                    intent: 'SHIPPING_ADDRESS'
                                }
                            };
                        }
                        await this.fetchUpdatedTotalFromBackend(mapped);
                        const tx = this.getGoogleTransactionInfo();
                        tx.totalPriceStatus = 'ESTIMATED';
                        return {
                            newTransactionInfo: tx,
                            newShippingOptionParameters: this.getGoogleShippingOptionParametersOrPlaceholder()
                        };
                    }
                    return {};
                }
                if (callbackTrigger === 'SHIPPING_ADDRESS' && shippingAddress) {
                    const selectedShippingId = shippingOptionData && shippingOptionData.id ? shippingOptionData.id : '';
                    const mapped = mapAddr(shippingAddress);
                    const vr = await this.validateShippingAddressFromBackend({
                        city: mapped.city,
                        state: mapped.state,
                        countryCode: mapped.country,
                        postalCode: mapped.postcode
                    }, selectedShippingId);
                    if (!vr || vr.success !== true) {
                        const msg = (vr && vr.data) ? String(vr.data) : this.t('shipping_unserviceable', 'We do not ship to this address.');
                        return {
                            error: {
                                reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
                                message: msg,
                                intent: 'SHIPPING_ADDRESS'
                            }
                        };
                    }
                    await this.fetchUpdatedTotalFromBackend(mapped, null, selectedShippingId);
                    const tx = this.getGoogleTransactionInfo();
                    tx.totalPriceStatus = 'ESTIMATED';
                    return {
                        newTransactionInfo: tx,
                        newShippingOptionParameters: this.getGoogleShippingOptionParametersOrPlaceholder()
                    };
                }
                if (callbackTrigger === 'SHIPPING_OPTION' && shippingOptionData && shippingOptionData.id) {
                    if (shippingOptionData.id === 'pending') {
                        const tx = this.getGoogleTransactionInfo();
                        tx.totalPriceStatus = 'ESTIMATED';
                        return {
                            newTransactionInfo: tx,
                            newShippingOptionParameters: this.getGoogleShippingOptionParametersOrPlaceholder()
                        };
                    }
                    const safeId = shippingOptionData.id;
                    const internalId = (this.googleShippingIdMap && this.googleShippingIdMap[safeId]) ? this.googleShippingIdMap[safeId] : safeId;
                    this.googleSelectedShippingId = internalId;
                    const addr = shippingAddress ? mapAddr(shippingAddress) : {};
                    await this.fetchUpdatedTotalFromBackend(addr, null, internalId);
                    const tx = this.getGoogleTransactionInfo();
                    tx.totalPriceStatus = 'ESTIMATED';
                    return {
                        newTransactionInfo: tx,
                        newShippingOptionParameters: this.getGoogleShippingOptionParametersOrPlaceholder()
                    };
                }
                return {};
            } catch (e) {
                console.error('onPaymentDataChanged error:', e);
                return {};
            }
        }

        async getGooglePayConfig() {
            try {
                if (!this.allowedPaymentMethods || !this.merchantInfo) {
                    const googlePayConfig = await wpg_paypal_sdk.Googlepay().config();
                    // PayPal reports merchant-level Google Pay eligibility here. When the
                    // account is not provisioned for Google Pay, Google still issues a
                    // token but PayPal cannot decrypt it, so confirmOrder fails at the
                    // very end with INVALID_GOOGLE_PAY_TOKEN — don't offer the button.
                    if (googlePayConfig && googlePayConfig.isEligible === false) {
                        console.warn('[Google Pay] The connected PayPal merchant account is not eligible for Google Pay in this environment. Enable Google Pay on the PayPal account (Account Settings → Payment methods) or contact PayPal support.');
                        return { allowedPaymentMethods: [], merchantInfo: {} };
                    }
                    let methods = googlePayConfig.allowedPaymentMethods || [];
                    methods = methods.map(method => {
                        const m = { ...method };
                        m.parameters = { ...(m.parameters || {}) };
                        m.parameters.billingAddressRequired = true;
                        m.parameters.billingAddressParameters = {
                            ...(m.parameters.billingAddressParameters || {}),
                            // FULL returns the street-level billing address (address1/locality/
                            // etc.). The default MIN format only returns name, country and
                            // postcode, which leaves the order billing address incomplete when
                            // the checkout form is not filled in.
                            format: 'FULL',
                            phoneNumberRequired: true,
                        };
                        return m;
                    });
                    this.allowedPaymentMethods = methods;
                    this.merchantInfo = googlePayConfig.merchantInfo || {};
                    this.googlePayApiVersion = googlePayConfig.apiVersion || 2;
                    this.googlePayApiVersionMinor = googlePayConfig.apiVersionMinor || 0;
                    // The Google Pay config response carries the PayPal account country;
                    // feed it into transactionInfo.countryCode. The store base country
                    // stays as fallback for SDK builds that omit it.
                    if (googlePayConfig.countryCode) {
                        this.googlePayMerchantCountry = googlePayConfig.countryCode;
                    }
                }
                return {
                    allowedPaymentMethods: this.allowedPaymentMethods,
                    merchantInfo: this.merchantInfo,
                };
            } catch (error) {
                console.error("Failed to fetch Google Pay configuration:", error);
                return { allowedPaymentMethods: [], merchantInfo: {} };
            }
        }

        getGoogleIsReadyToPayRequest(allowedPaymentMethods) {
            return {
                apiVersion: this.googlePayApiVersion || 2,
                apiVersionMinor: this.googlePayApiVersionMinor || 0,
                allowedPaymentMethods,
            };
        }

        async syncGooglePayButton() {
            if (this.ppcp_manager.enabled_google_pay !== 'yes') {
                this.removeGooglePayContainer();
                return;
            }
            if (!this.isPayPalGooglePaySdkReady()) {
                console.log('[Google Pay] SDK not available (script not loaded or unsupported)');
                this.removeGooglePayContainer();
                return;
            }
            const googlePayConfig = await this.getGooglePayConfig();
            const allowedPaymentMethods = googlePayConfig?.allowedPaymentMethods;
            if (!allowedPaymentMethods || !allowedPaymentMethods.length) {
                console.log('[Google Pay] No allowed payment methods');
                this.removeGooglePayContainer();
                return;
            }
            const shippingRequired = this.ppcp_manager.needs_shipping === "1";
            const paymentsClient = this.getGooglePaymentsClient({requireOnPaymentDataChanged: shippingRequired});
            try {
                const response = await paymentsClient.isReadyToPay(this.getGoogleIsReadyToPayRequest(allowedPaymentMethods));
                if (!response.result) {
                    console.log('[Google Pay] isReadyToPay returned false');
                    this.removeGooglePayContainer();
                    return;
                }
                document.querySelectorAll('.google-pay-container').forEach(container => {
                    container.innerHTML = '';
                    this.renderGooglePayButton(container);
                });
            } catch (error) {
                console.error('[Google Pay] isReadyToPay failed', error);
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
            button.querySelector('.gpay-button').style.setProperty('border-radius', `${buttonRadius}px`, 'important');
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
                const button = event?.target?.closest('button');
                const clickedWrapper = button?.parentElement;
                this.pageContext = clickedWrapper?.getAttribute('data-context') || 'unknown';
                const transactionInfo = await this.ppcpGettransactionInfo();
                if (transactionInfo?.success === false) {
                    const messages = transactionInfo.data?.messages ?? transactionInfo.data ?? ['Unknown error'];
                    this.showError(messages);
                    this.hideSpinner();
                    throw new Error(messages);
                }
                if (transactionInfo?.success && transactionInfo.data) {
                    if (typeof this.setTotalsFromResponse === 'function') {
                        this.setTotalsFromResponse(transactionInfo.data);
                    }
                }
                const shippingRequired = this.ppcp_manager.needs_shipping === "1" && this.pageContext !== 'checkout';
                const paymentsClient = this.getGooglePaymentsClient({requireOnPaymentDataChanged: shippingRequired});
                const paymentDataRequest = await this.getGooglePaymentDataRequest();
                await paymentsClient.loadPaymentData(paymentDataRequest);
            } catch (error) {
                console.error('[GPay] loadPaymentData error:', error);
                this.hideSpinner();
                if (error?.statusCode === "CANCELED") {
                    window.location.reload(true);
                    console.warn("Google Pay was cancelled by the user.");
                    return;
                }
                console.error("Google Pay Button Click Error:", error);
            }
        }

        getGoogleShippingOptionParameters() {
            const methods = Array.isArray(this.ppcp_manager.shipping_methods) ? this.ppcp_manager.shipping_methods : [];
            if (!methods.length) {
                return null;
            }
            this.googleShippingIdMap = {};
            const formatPrice = (amount) => {
                const n = parseFloat(String(amount || "0").replace(/,/g, "")) || 0;
                const currency = this.ppcp_manager.currency || "USD";
                const locale   = this.ppcp_manager.locale   || "en-US";
                return new Intl.NumberFormat(locale, {
                    style: "currency",
                    currency
                }).format(n);
            };
            let defaultInternalId = this.googleSelectedShippingId;
            if (!defaultInternalId) {
                const selected = methods.find(m => m.is_selected);
                defaultInternalId = selected?.id || methods[0].id;
            }
            const options = methods.map(method => {
                const internalId = method.id;
                const safeId = internalId.replace(/[^a-zA-Z0-9 _-]/g, "_");
                this.googleShippingIdMap[safeId] = internalId;
                const formattedAmount = formatPrice(method.amount);
                return {
                    id: safeId,
                    label: method.label || internalId,
                    description: formattedAmount
                };
            });
            let defaultSafeId = options[0].id;
            const found = options.find(opt => this.googleShippingIdMap[opt.id] === defaultInternalId);
            if (found) defaultSafeId = found.id;
            return {
                defaultSelectedOptionId: defaultSafeId,
                shippingOptions: options
            };
        }

        getGoogleShippingOptionParametersOrPlaceholder() {
            const real = this.getGoogleShippingOptionParameters();
            if (real && Array.isArray(real.shippingOptions) && real.shippingOptions.length > 0) {
                return real;
            }
            return {
                defaultSelectedOptionId: 'pending',
                shippingOptions: [{
                    id: 'pending',
                    label: this.t('calculating_shipping', 'Shipping'),
                    description: this.t('calculating_shipping_desc', 'Calculated after address selection')
                }]
            };
        }

        async getGooglePaymentDataRequest() {
            const {allowedPaymentMethods, merchantInfo} = await this.getGooglePayConfig();
            const shippingRequired = this.ppcp_manager.needs_shipping === "1" && this.pageContext !== 'checkout';
            const callbackIntents = ['PAYMENT_AUTHORIZATION'];
            if (shippingRequired) {
                callbackIntents.push('SHIPPING_ADDRESS', 'SHIPPING_OPTION');
            }
            const paymentDataRequest = {
                apiVersion: this.googlePayApiVersion || 2,
                apiVersionMinor: this.googlePayApiVersionMinor || 0,
                allowedPaymentMethods,
                transactionInfo: this.getGoogleTransactionInfo(),
                merchantInfo,
                emailRequired: true,
                callbackIntents
            };
            if (shippingRequired) {
                paymentDataRequest.shippingAddressRequired = true;
                paymentDataRequest.shippingAddressParameters = {
                    phoneNumberRequired: true
                };
                paymentDataRequest.shippingOptionRequired = true;
                paymentDataRequest.shippingOptionParameters = this.getGoogleShippingOptionParametersOrPlaceholder();
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
                    body: data
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
            const currency = this.ppcp_manager?.currency || "USD";
            const country  = this.googlePayMerchantCountry || this.ppcp_manager?.country || "US";
            const toNum = (v) => {
                const f = parseFloat((v ?? "").toString().replace(/,/g, ""));
                return Number.isFinite(f) ? f : 0;
            };
            const toStr = (v) => toNum(v).toFixed(2);
            const displayItems = [];
            const items = Array.isArray(this.ppcp_manager?.cart_items) ? this.ppcp_manager.cart_items : [];
            for (const it of items) {
                const name = it?.name ?? "Item";
                const qty  = Math.max(1, parseInt(it?.quantity, 10) || 1);
                const lineTotal = ("subtotal" in it) ? toNum(it.subtotal) : toNum(it.price) * qty;
                displayItems.push({
                    label: `${name}${qty > 1 ? ` × ${qty}` : ""}`,
                    type: "LINE_ITEM",
                    price: toStr(lineTotal)
                });
            }
            const needsShipping = String(this.ppcp_manager?.needs_shipping ?? "0") === "1";
            const shippingTotal = toNum(this.ppcp_manager?.shipping_total);
            if (needsShipping && shippingTotal > 0) {
                displayItems.push({
                    label: "Shipping",
                    type: "SHIPPING_OPTION",
                    price: toStr(shippingTotal)
                });
            }
            const taxTotal = toNum(this.ppcp_manager?.tax_total);
            if (taxTotal > 0) {
                displayItems.push({
                    label: "Tax",
                    type: "TAX",
                    price: toStr(taxTotal)
                });
            }
            const discountTotal = toNum(this.ppcp_manager?.discount_total);
            if (discountTotal > 0) {
                displayItems.push({
                    label: "Discount",
                    type: "DISCOUNT",
                    price: toStr(-discountTotal)
                });
            }
            const total = toNum(this.ppcp_manager?.cart_total);
            return {
                displayItems,
                currencyCode: currency,
                countryCode: country,
                totalPriceStatus: "ESTIMATED",
                totalPrice: toStr(total),
                totalPriceLabel: "Total",
                checkoutOption: "DEFAULT"
            };
        }

        async onPaymentAuthorized(paymentData) {
            try {
                // Always extract wallet addresses so googleapplecreateOrder() can use them
                // as a fallback when the Block Checkout form has not been filled.
                const billingRaw  = paymentData?.paymentMethodData?.info?.billingAddress || {};
                const shippingRaw = paymentData?.shippingAddress || {};
                const email       = paymentData?.email || '';
                const billingAddress = {
                    name:         billingRaw.name || '',
                    surname:      '',
                    address1:     billingRaw.address1 || '',
                    address2:     billingRaw.address2 || '',
                    city:         billingRaw.locality || '',
                    state:        billingRaw.administrativeArea || '',
                    postcode:     billingRaw.postalCode || '',
                    country:      billingRaw.countryCode || '',
                    phoneNumber:  billingRaw.phoneNumber || '',
                    emailAddress: email || ''
                };
                const shippingAddress = {
                    name:        shippingRaw.name || '',
                    surname:     '',
                    address1:    shippingRaw.address1 || '',
                    address2:    shippingRaw.address2 || '',
                    city:        shippingRaw.locality || '',
                    state:       shippingRaw.administrativeArea || '',
                    postcode:    shippingRaw.postalCode || '',
                    country:     shippingRaw.countryCode || '',
                    phoneNumber: shippingRaw.phoneNumber || ''
                };
                this.walletBillingAddress = billingAddress;
                this.walletShippingAddress = shippingAddress;
                if (this.pageContext !== 'checkout') {
                    await this.fetchUpdatedTotalFromBackend(shippingAddress, billingAddress);
                }
                if (this.googleSelectedShippingId === 'pending' || !this.googleSelectedShippingId) {
                    const methods = Array.isArray(this.ppcp_manager.shipping_methods) ? this.ppcp_manager.shipping_methods : [];
                    if (methods.length) {
                        const pick = methods.find(m => m.is_selected) || methods[0];
                        this.googleSelectedShippingId = pick.id;
                    }
                }
                const orderId = await this.googleapplecreateOrder();
                if (!orderId) {
                    throw new Error("Order creation failed.");
                }
                const result = await wpg_paypal_sdk.Googlepay().confirmOrder({
                    orderId,
                    paymentMethodData: paymentData.paymentMethodData
                }).catch(err => {
                    // The SDK rejects with internal error names (e.g.
                    // APPROVE_GOOGLE_PAY_VALIDATION_ERROR); log those for debugging but
                    // show the shopper the translated message instead.
                    console.error('[Google Pay] confirmOrder error:', err);
                    throw new Error(this.t('google_pay_not_approved', 'Google Pay payment could not be approved. Please try again or use a different payment method.'));
                });
                // approveGooglePayPayment reports the outcome via the order status.
                // Depending on the SDK build it is returned at the top level or nested
                // under approveGooglePayPayment. Only APPROVED (or PAYER_ACTION_REQUIRED,
                // once the payer completes the required action) may proceed to capture;
                // any other value means PayPal rejected the token — e.g. an undecryptable
                // Google Pay token (INVALID_GOOGLE_PAY_TOKEN) — and must not be captured
                // or reported as a success. This mirrors the Apple Pay confirmOrder guard.
                const status = result?.status ?? result?.approveGooglePayPayment?.status ?? null;
                if (status === "PAYER_ACTION_REQUIRED") {
                    await wpg_paypal_sdk.Googlepay().initiatePayerAction({ orderId });
                } else if (status !== "APPROVED") {
                    throw new Error(this.t('google_pay_not_approved', 'Google Pay payment could not be approved. Please try again or use a different payment method.'));
                }
                this.onApproveHandler({ orderID: orderId }, 'google_pay');
                return { transactionState: "SUCCESS" };
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

        setTotalsFromResponse(d = {}) {
            if (!d) {
                return;
            }

            this.ppcp_manager.cart_total       = d.total          ?? d.cart_total ?? this.ppcp_manager.cart_total;
            this.ppcp_manager.cart_items       = Array.isArray(d.cart_items) ? d.cart_items : (this.ppcp_manager.cart_items || []);
            this.ppcp_manager.shipping_total   = d.shipping_total ?? this.ppcp_manager.shipping_total ?? "0.00";
            this.ppcp_manager.tax_total        = d.tax_total      ?? this.ppcp_manager.tax_total      ?? "0.00";
            this.ppcp_manager.discount_total   = d.discount_total ?? this.ppcp_manager.discount_total ?? "0.00";
            this.ppcp_manager.currency         = d.currency       ?? this.ppcp_manager.currency;
            this.ppcp_manager.needs_shipping   = d.needs_shipping ?? this.ppcp_manager.needs_shipping ?? "0";
            this.ppcp_manager.shipping_methods = Array.isArray(d.shipping_methods)
                ? d.shipping_methods
                : (this.ppcp_manager.shipping_methods || []);

            const selected = (this.ppcp_manager.shipping_methods || []).find(m => m.is_selected);
            if (selected && selected.id) {
                this.googleSelectedShippingId = selected.id;
                this.appleSelectedShippingId  = selected.id;
            }
        }

        
        async validateShippingAddressFromBackend(shippingAddress, selectedShippingId = '') {
            const params = new URLSearchParams({
                action: 'ppcp_validate_shipping_address',
                security: this.ppcp_manager.ajax_nonce,
                shipping_address: JSON.stringify(shippingAddress || {})
            });
            if (selectedShippingId) {
                params.append('selected_shipping_id', selectedShippingId);
            }
            const res = await fetch(this.ppcp_manager.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            });
            // admin-ajax.php always returns 200, so parse body
            const result = await res.json().catch(() => null);
            return result;
        }

        async fetchUpdatedTotalFromBackend(shippingAddress, billingAddress = null, selectedShippingId = '') {
            try {
                const params = new URLSearchParams({
                    action: 'ppcp_get_updated_total',
                    security: this.ppcp_manager.ajax_nonce,
                    shipping_address: JSON.stringify(shippingAddress || {}),
                    billing_address: billingAddress ? JSON.stringify(billingAddress) : '',
                    context: this.pageContext
                });
                if (selectedShippingId) {
                    params.append('selected_shipping_id', selectedShippingId);
                }
                const res = await fetch(this.ppcp_manager.ajax_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params
                });
                const result = await res.json();
                if (result?.success && result.data) {
                    if (typeof this.setTotalsFromResponse === 'function') {
                        this.setTotalsFromResponse(result.data);
                    }
                    if (result.data.total != null) {
                        const n = parseFloat(String(result.data.total).replace(/,/g, ''));
                        return Number.isFinite(n) ? n.toFixed(2) : String(result.data.total);
                    }
                }
                const n = parseFloat(String(this.ppcp_manager.cart_total || '0').replace(/,/g, ''));
                return Number.isFinite(n) ? n.toFixed(2) : (this.ppcp_manager.cart_total || '0.00');
            } catch (e) {
                console.error('Error fetching updated total:', e);
                const n = parseFloat(String(this.ppcp_manager.cart_total || '0').replace(/,/g, ''));
                return Number.isFinite(n) ? n.toFixed(2) : (this.ppcp_manager.cart_total || '0.00');
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
            const hasProductContainer = document.querySelector('#ppcp_product, .google-pay-container[data-context="product"], .apple-pay-container[data-context="product"]');
            if (!hasProductContainer) {
                const hasApplePayContainer = document.querySelector('.apple-pay-container');
                if (hasApplePayContainer) {
                    this.fetchProductTotal(0, 0);
                }
                return;
            }
            const getCurrentProductId = () => {
                const variationInput = document.querySelector('input.variation_id');
                const variationId = parseInt(variationInput?.value || 0, 10);
                return variationId > 0
                    ? variationId
                    : parseInt(this.ppcp_manager?.product_id || 0, 10);
            };
            const getQty = () => parseFloat(document.querySelector('input.qty')?.value || '1') || 1;
            this.fetchProductTotal(getCurrentProductId(), getQty());
        }

        fetchProductTotal(productId, quantity = 1) {
            const data = new URLSearchParams({
                action: 'ppcp_get_product_total',
                security: this.ppcp_manager.ajax_nonce,
                product_id: productId,
                quantity: quantity
            });

            // Product-option plugins (Product Add-ons, TM Extra Product Options,
            // Advanced Product Fields) maintain hidden cost fields on form.cart;
            // the server-side wpg_ppcp_product_total filter reads them from this
            // request, so the wallet-sheet total includes the option costs.
            ['addon_costs', 'tm_epo_costs', 'wapf_costs'].forEach((field) => {
                const input = document.querySelector(`form.cart input[name="${field}"]`);
                if (input && input.value !== '') {
                    data.append(field, input.value);
                }
            });

            fetch(this.ppcp_manager.ajax_url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data.toString()
            }).then(res => res.json())
            .then(response => {
                if (response.success && response.data?.combined_total) {
                    this.ppcp_manager.cart_total = response.data.combined_total;
                }
            });
        }

        removeApplePayContainer() {
            setTimeout(() => {
                const containers = document.querySelectorAll('.apple-pay-container');
                containers.forEach(container => {
                    container.remove();
                });
            }, 500);
        }

        async syncApplePayButton() {
            if (this.ppcp_manager.enabled_apple_pay !== 'yes') {
                this.removeApplePayContainer();
                return;
            }
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
            if (typeof wpg_paypal_sdk === 'undefined' || typeof wpg_paypal_sdk.Applepay !== 'function') {
                this.removeApplePayContainer();
                return;
            }
            try {
                const applepay = wpg_paypal_sdk.Applepay();
                const config = await applepay.config({ environment: this.ppcp_manager.environment || "TEST" });
                if (config && config.isEligible) {
                    this.renderApplePayButton();
                } else {
                    console.log("Apple Pay is not eligible for this configuration.");
                    this.removeApplePayContainer();
                }
            } catch (error) {
                console.error("Failed to load Apple Pay configuration:", error);
                this.removeApplePayContainer();
            }
        }

        renderApplePayButton() {
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
        
        getAppleShippingMethods() {
            const methods = Array.isArray(this.ppcp_manager.shipping_methods) ? this.ppcp_manager.shipping_methods : [];
            if (!methods.length) {
                return [];
            }
            this.appleShippingIdMap = {};
            let defaultInternalId = this.appleSelectedShippingId;
            if (!defaultInternalId) {
                const selected = methods.find(m => m.is_selected);
                defaultInternalId = (selected && selected.id) ? selected.id : methods[0].id;
            }
            this.appleSelectedShippingId = defaultInternalId;

            const appleMethods = methods.map(method => {
                const internalId = method.id;
                const safeId = internalId.replace(/[^a-zA-Z0-9 _-]/g, "_");
                this.appleShippingIdMap[safeId] = internalId;

                return {
                    label: method.label || internalId,
                    amount: this.formatAmount(method.amount),
                    identifier: safeId,
                    detail: method.description || ''
                };
            });
            return appleMethods;
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
                const needsShipping = this.ppcp_manager.needs_shipping === "1" && this.pageContext !== 'checkout';
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
                    const methods = this.getAppleShippingMethods();
                    if (methods.length) {
                        paymentRequest.shippingMethods = methods;
                    } else {
                        paymentRequest.shippingMethods = [{
                            label: 'Shipping',
                            amount: '0.00',
                            identifier: 'pending',
                            detail: 'Calculated after address selection'
                        }];
                    }
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
                    session.onshippingcontactselected = async (event) => {
                        try {
                            if (this.pageContext === 'product') {
                                const transactionInfo = await this.ppcpGettransactionInfo();
                                if (transactionInfo?.success === false) {
                                    const messages = transactionInfo.data?.messages ?? transactionInfo.data ?? ['Unknown error'];
                                    this.showError(messages);
                                    throw new Error(messages);
                                    session.completePayment({
                                        status: ApplePaySession.STATUS_FAILURE
                                    });
                                }
                                if (transactionInfo?.success && transactionInfo.data && typeof this.setTotalsFromResponse === 'function') {
                                    this.setTotalsFromResponse(transactionInfo.data);
                                }
                            }

                            const shipping = event.shippingContact;
                            if (!shipping || !shipping.countryCode || !shipping.postalCode) {
                                throw new Error(this.t('shipping_address_incomplete', 'Shipping address is incomplete.'));
                            }

                            // ✅ Validate shipping country/address via the same AJAX endpoint used by PayPal onShippingChange
                            const vr = await this.validateShippingAddressFromBackend({
                                city: shipping.locality || '',
                                state: shipping.administrativeArea || '',
                                countryCode: shipping.countryCode || '',
                                postalCode: shipping.postalCode || ''
                            });
                            if (!vr || vr.success !== true) {
                                const msg = (vr && vr.data) ? String(vr.data) : this.t('shipping_unserviceable', 'We do not ship to this address.');
                                const errors = [];
                                if (window.ApplePayError) {
                                    errors.push(new ApplePayError('shippingContactInvalid', 'postalAddress', msg));
                                }
                                session.completeShippingContactSelection({
                                    errors,
                                    newTotal: {
                                        label: this.ppcp_manager.store_label || "Store",
                                        amount: this.formatAmount(this.ppcp_manager.cart_total),
                                        type: "final"
                                    }
                                });
                                return;
                            }

                            const updatedTotal = await this.fetchUpdatedTotalFromBackend({
                                city:    shipping.locality || '',
                                state:   shipping.administrativeArea || '',
                                postcode: shipping.postalCode || '',
                                country: shipping.countryCode || ''
                            });

                            const methods = this.getAppleShippingMethods();
                            const update = {
                                newTotal: {
                                    label: this.ppcp_manager.store_label || "Store",
                                    amount: updatedTotal,
                                    type: "final"
                                }
                            };

                            if (methods.length) {
                                update.newShippingMethods = methods;
                            }

                            session.completeShippingContactSelection(update);
                        } catch (error) {
                            console.error('[ApplePay] onshippingcontactselected error:', error);
                            const fallbackMethods = this.getAppleShippingMethods();
                            const update = {
                                newTotal: {
                                    label: this.ppcp_manager.store_label || "Store",
                                    amount: this.formatAmount(this.ppcp_manager.cart_total),
                                    type: "final"
                                }
                            };
                            if (fallbackMethods.length) {
                                update.newShippingMethods = fallbackMethods;
                            }
                            session.completeShippingContactSelection(update);
                        }
                    };

                    session.onshippingmethodselected = async (event) => {
                        try {
                            const method = event.shippingMethod;
                            if (!method || !method.identifier) {
                                throw new Error(this.t('invalid_shipping_method', 'Invalid shipping method.'));
                            }
                            if (method.identifier === 'pending') {
                                session.completeShippingMethodSelection({
                                    newTotal: {
                                        label: this.ppcp_manager.store_label || "Store",
                                        amount: this.formatAmount(this.ppcp_manager.cart_total),
                                        type: "final"
                                    }
                                });
                                return;
                            }
                            const internalId = this.appleShippingIdMap[method.identifier] || method.identifier;
                            this.appleSelectedShippingId = internalId;

                            const updatedTotal = await this.fetchUpdatedTotalFromBackend(
                                {}, // shipping address unchanged
                                null, // billing not needed here
                                internalId
                            );

                            const update = {
                                newTotal: {
                                    label: this.ppcp_manager.store_label || "Store",
                                    amount: updatedTotal,
                                    type: "final"
                                }
                            };

                            session.completeShippingMethodSelection(update);
                        } catch (error) {
                            console.error('[ApplePay] onshippingmethodselected error:', error);
                            session.completeShippingMethodSelection({
                                newTotal: {
                                    label: this.ppcp_manager.store_label || "Store",
                                    amount: this.formatAmount(this.ppcp_manager.cart_total),
                                    type: "final"
                                }
                            });
                        }
                    };
                }

                session.onpaymentauthorized = async (event) => {
                    try {
                        
                        const transactionInfo = await this.ppcpGettransactionInfo();
                        if (transactionInfo.success === false) {
                            const messages = transactionInfo.data?.messages ?? transactionInfo.data ?? ['Unknown error'];
                            this.showError(messages);
                            throw new Error(messages);
                            session.completePayment({
                                status: ApplePaySession.STATUS_FAILURE
                            });
                        }
                        if (transactionInfo?.success) {
                            this.ppcp_manager.cart_total = transactionInfo.data?.cart_total || this.ppcp_manager.cart_total;
                        }
                        

                        const billingRaw = event.payment?.billingContact || {};
                        const shippingRaw = event.payment?.shippingContact || {};
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
                        // Store wallet addresses so googleapplecreateOrder() can fall back to
                        // them when the Block Checkout form is empty.
                        this.walletBillingAddress = billingAddress;
                        this.walletShippingAddress = shippingAddress;

                        if (needsShipping) {
                            await this.fetchUpdatedTotalFromBackend(
                                shippingAddress,
                                billingAddress,
                                this.appleSelectedShippingId || ''
                            );
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

                            if (this.isCheckoutPage() || this.ppcp_manager.skip_order_review === 'yes') {
                                const url = `${this.ppcp_manager.cc_capture}&paypal_order_id=${encodeURIComponent(order_id)}&woocommerce-process-checkout-nonce=${this.ppcp_manager.woocommerce_process_checkout}`;
                                $.post(url, (response) => {
                                    if (response?.data?.redirect) {
                                        session.completePayment({
                                            status: ApplePaySession.STATUS_SUCCESS
                                        });
                                        window.location.href = response.data.redirect;
                                        return;
                                    }
                                    // Non-redirect response (failure or unexpected/empty body):
                                    // close the Apple Pay sheet and surface an error instead of
                                    // leaving it spinning.
                                    session.completePayment({
                                        status: ApplePaySession.STATUS_FAILURE
                                    });
                                    this.hideSpinner();
                                    const messages = response?.data?.messages ?? [this.t('unknown_error_short', 'An unknown error occurred.')];
                                    this.showError(messages);
                                    if (response?.success === false) {
                                        let redirectUrl = `${this.ppcp_manager.checkout_url}?paypal_order_id=${encodeURIComponent(order_id)}&from=${this.ppcp_manager.page}`;
                                        window.location.href = redirectUrl;
                                    }
                                }).fail(() => {
                                    session.completePayment({
                                        status: ApplePaySession.STATUS_FAILURE
                                    });
                                    this.hideSpinner();
                                    this.showError('We could not confirm your payment. If your account was charged, please contact us before trying again.');
                                });
                            } else {
                                session.completePayment({
                                    status: ApplePaySession.STATUS_SUCCESS
                                });
                                let redirectUrl = `${this.ppcp_manager.checkout_url}?paypal_order_id=${encodeURIComponent(order_id)}&from=${this.ppcp_manager.page}`;
                                window.location.href = redirectUrl;
                                this.hideSpinner();
                            }
                        } else {
                            throw new Error(this.t('apple_pay_not_approved', 'Apple Pay confirmation returned non-APPROVED status.'));
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
                    window.location.reload(true);
                    console.log("Apple Pay session cancelled.");
                    this.hideSpinner();
                };
                session.begin();
            } catch (err) {
                console.error('Apple Pay session initialization failed:', err);
                this.showError(this.t('apple_pay_init_failed', 'Apple Pay could not be initialized.'));
                this.hideSpinner();
            }
        }
    }
    $(function () {
        $('.woocommerce #payment #place_order, .woocommerce-page #payment #place_order').hide();
        window.PPCPManager = PPCPManager;
        const ppcp_manager = window.ppcp_manager || {};
        window.ppcpManagerInstance = new PPCPManager(ppcp_manager);
    });
})(jQuery);