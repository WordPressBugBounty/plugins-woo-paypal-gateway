/**
 * Fastlane by PayPal — checkout controller.
 *
 * Flow overview:
 *  - The PayPal JS SDK is loaded with the `fastlane` component and a
 *    data-sdk-client-token, so `wpg_paypal_sdk.Fastlane()` is available when the
 *    merchant/buyer context is eligible (otherwise this file is a no-op and the
 *    regular Advanced Card fields keep working).
 *  - A watermark is rendered under the billing email field and the email is
 *    watched. When a Fastlane member email is recognized the OTP authentication
 *    flow runs; on success the member's saved card and addresses are applied to
 *    the checkout and the card fields are replaced with a "tokenized card" tile.
 *  - Non-members can click the signup link inside the card gateway to enter
 *    their card through the Fastlane payment component (and enroll).
 *  - Either way the result is a single-use payment token. It is submitted with
 *    the normal WooCommerce checkout (hidden input `wpg_ppcp_fastlane_token` on
 *    classic checkout, `paymentMethodData` on block checkout) and charged
 *    server-side in one Orders v2 call.
 */
(function ($) {
    'use strict';

    var GATEWAY_ID = 'wpg_paypal_checkout_cc';
    var CARD_FORM_SELECTOR = '#wc-wpg_paypal_checkout_cc-form';

    function WPGFastlane(settings) {
        this.settings = settings || {};
        this.fastlane = null;
        this.identity = null;
        this.profile = null;
        this.FastlanePaymentComponent = null;
        this.FastlaneWatermarkComponent = null;
        this.paymentComponent = null;
        this.watermarkComponent = null;
        this.watermarkMounted = false;
        this.token = null;            // {id, brand, lastDigits, expiry, name}
        this.customerContextId = null;
        this.canceledEmails = [];
        this.lastLookupEmail = '';
        this.authInProgress = false;
        this.isBlocks = this.settings.is_block_enable === 'yes' && $('.wc-block-checkout').length > 0;
        this.flow = this.settings.fastlane_flow === 'express_button' ? 'express_button' : 'email_detection';
    }

    WPGFastlane.prototype = {

        initialize: function () {
            var self = this;
            if (this.settings.fastlane !== 'yes') {
                return;
            }
            this.waitForSdk(function () {
                self.createFastlane();
            });
        },

        waitForSdk: function (callback, attempt) {
            var self = this;
            attempt = attempt || 0;
            if (typeof window.wpg_paypal_sdk !== 'undefined') {
                callback();
                return;
            }
            if (attempt > 100) {
                return;
            }
            setTimeout(function () {
                self.waitForSdk(callback, attempt + 1);
            }, 100);
        },

        createFastlane: async function () {
            var self = this;
            try {
                if (!window.wpg_paypal_sdk.Fastlane) {
                    // SDK decided the context is not Fastlane-eligible.
                    return;
                }
                this.fastlane = await window.wpg_paypal_sdk.Fastlane();
                this.identity = this.fastlane.identity;
                this.profile = this.fastlane.profile;
                this.FastlanePaymentComponent = this.fastlane.FastlanePaymentComponent;
                this.FastlaneWatermarkComponent = this.fastlane.FastlaneWatermarkComponent;
            } catch (err) {
                window.console && console.log('Fastlane could not be initialized:', err);
                return;
            }
            this.bindEvents();
            this.setupTokenGuard();
            if (this.settings.fastlane_watermark === 'yes') {
                this.mountWatermark();
            }
            if (this.flow === 'express_button') {
                this.setupExpressButton();
            } else {
                this.mountSignupLink();
            }
            this.authenticateOnPageLoad();
        },

        // "Authenticate On Page Load": when the billing email is already
        // populated (e.g. a logged-in returning customer, or a checkout that
        // remembered the last order), start the Fastlane lookup/OTP right away
        // instead of waiting for an email edit.
        //
        // The email field is not necessarily populated at the moment the SDK
        // finishes loading: WooCommerce (classic) and the checkout block hydrate
        // field values from the customer session/store asynchronously, so a
        // single immediate read would miss a prefilled email on navigation
        // (it only "worked" on a hard refresh because the server-rendered value
        // was present from the start). Poll briefly until the email appears.
        authenticateOnPageLoad: function (attempt) {
            var self = this;
            if (this.settings.fastlane_pageload !== 'yes') {
                return;
            }
            attempt = attempt || 0;
            if (this.token || this.authInProgress || this.customerContextId) {
                return;
            }
            var email = this.getEmail();
            if (this.isValidEmail(email)) {
                // The auto path respects an email the customer explicitly
                // dismissed, so we don't re-prompt on every reload; manual
                // re-entry (onEmailChange) clears that and always retries.
                if (this.canceledEmails.indexOf(email.toLowerCase()) !== -1) {
                    return;
                }
                this.triggerAuthentication(email, false);
                return;
            }
            // Give the field up to ~9s (30 * 300ms) to hydrate before giving up.
            if (attempt >= 30) {
                return;
            }
            setTimeout(function () {
                self.authenticateOnPageLoad(attempt + 1);
            }, 300);
        },

        bindEvents: function () {
            var self = this;
            var debouncedEmail = this.debounce(function () {
                self.onEmailChange();
            }, 400);
            $(document.body).on('input change', 'input[name="billing_email"], #email', debouncedEmail);

            // Checkout fragments re-render wipes our DOM changes; re-apply them.
            $(document.body).on('updated_checkout ppcp_cc_checkout_updated ppcp_cc_block_ready', function () {
                if (self.settings.fastlane_watermark === 'yes') {
                    self.mountWatermark();
                }
                if (self.flow === 'express_button') {
                    self.setupExpressButton();
                }
                if (self.token) {
                    self.ensureTokenizedCard();
                    self.syncTokenField();
                } else {
                    self.mountSignupLink();
                }
            });

            $(document).on('click', '.wpg-fastlane-express-button', function (e) {
                e.preventDefault();
                self.onExpressButtonClick();
            });

            // A failed submit may have consumed the single-use token server-side;
            // drop it and fall back to the regular card fields (mirrors the
            // behavior of other Fastlane integrations).
            $(document.body).on('checkout_error', function () {
                if (self.token) {
                    self.clearToken();
                }
            });

            $(document).on('click', '.wpg-fastlane-signup-link', function (e) {
                e.preventDefault();
                self.openModal();
            });
            $(document).on('click', '.wpg-fastlane-tokenize', function (e) {
                e.preventDefault();
                self.onModalContinue();
            });
            $(document).on('click', '.wpg-fastlane-modal-cancel', function (e) {
                e.preventDefault();
                self.closeModal();
            });
            $(document).on('click', '.wpg-fastlane-card-cancel', function (e) {
                e.preventDefault();
                self.clearToken();
            });
            $(document).on('click', '.wpg-fastlane-card-change', function (e) {
                e.preventDefault();
                self.changeCard();
            });
        },

        /* ------------------------------------------------------------------ */
        /* Email detection / member authentication                             */
        /* ------------------------------------------------------------------ */

        getEmail: function () {
            var email = $('input[name="billing_email"]').val() || $('#email').val() || '';
            return String(email).trim();
        },

        isValidEmail: function (email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        onEmailChange: function () {
            var email = this.getEmail();
            if (this.flow !== 'email_detection') {
                return;
            }
            if (!this.identity || this.authInProgress || this.token) {
                return;
            }
            if (!this.isValidEmail(email)) {
                return;
            }
            // Manual entry is an explicit intent to use Fastlane, so always
            // retry — including an email a previous (possibly spurious) attempt
            // recorded as canceled. This is what unblocks the "re-typing the
            // email does nothing" state.
            var idx = this.canceledEmails.indexOf(email.toLowerCase());
            if (idx !== -1) {
                this.canceledEmails.splice(idx, 1);
            }
            this.triggerAuthentication(email, false);
        },

        triggerAuthentication: async function (email, showSignup) {
            var self = this;
            // Re-entrancy guard only — never a per-email "already tried" guard,
            // which is what left a failed lookup permanently un-retryable.
            if (this.authInProgress || this.token) {
                return;
            }
            this.authInProgress = true;
            this.lastLookupEmail = email;
            try {
                var lookup = await this.lookupCustomerWithRetry(email);
                if (lookup && lookup.customerContextId) {
                    var result = await this.identity.triggerAuthenticationFlow(lookup.customerContextId);
                    var state = result && result.authenticationState;
                    if (state === 'succeeded' && result.profileData) {
                        this.customerContextId = lookup.customerContextId;
                        this.applyProfile(result.profileData);
                    } else if (state === 'canceled') {
                        // Only the auto page-load path honours this later; manual
                        // re-entry clears it and retries.
                        this.canceledEmails.push(email.toLowerCase());
                    }
                } else if (showSignup) {
                    // Express button clicked by a non-member: go straight to the
                    // Fastlane card entry / enrollment modal.
                    this.openModal();
                } else {
                    // Not a member: surface the signup link inside the card gateway.
                    this.mountSignupLink();
                }
            } catch (err) {
                window.console && console.log('Fastlane authentication error:', err);
            } finally {
                this.authInProgress = false;
            }
        },

        // A member's email should consistently return a customerContextId. On
        // rapid reloads the SDK session/state may not be ready yet and the first
        // lookup can come back empty (or throw), which previously made Fastlane
        // fire only intermittently. Retry a few times before concluding the
        // shopper is not a Fastlane member.
        lookupCustomerWithRetry: async function (email, attempt) {
            attempt = attempt || 0;
            var wait = function (ms) {
                return new Promise(function (resolve) {
                    setTimeout(resolve, ms);
                });
            };
            try {
                var res = await this.identity.lookupCustomerByEmail(email);
                if (res && res.customerContextId) {
                    return res;
                }
                if (attempt < 2 && this.getEmail() === email) {
                    await wait(400);
                    return this.lookupCustomerWithRetry(email, attempt + 1);
                }
                return res;
            } catch (err) {
                if (attempt < 2 && this.getEmail() === email) {
                    await wait(400);
                    return this.lookupCustomerWithRetry(email, attempt + 1);
                }
                throw err;
            }
        },

        /* ------------------------------------------------------------------ */
        /* Express button flow                                                 */
        /* ------------------------------------------------------------------ */

        setupExpressButton: function () {
            if (this.flow !== 'express_button' || !this.fastlane) {
                return;
            }
            var $container = $('.wpg-fastlane-express-container');
            if (!$container.length) {
                // Block checkout: the PHP renderer does not run there, so
                // inject the same markup above the checkout block.
                var $anchor = $('.wp-block-woocommerce-checkout, .wc-block-checkout').first();
                if (!$anchor.length) {
                    return;
                }
                var logo = this.settings.fastlane_logo ? '<img src="' + this.esc(this.settings.fastlane_logo) + '" alt="Fastlane" class="wpg-fastlane-express-logo" />' : 'Fastlane';
                $anchor.before(
                    '<div id="wpg-fastlane-express" class="wpg-fastlane-express-container" style="display:none;">' +
                        '<button type="button" class="wpg-fastlane-express-button">' + logo + '</button>' +
                    '</div>'
                );
                $container = $('.wpg-fastlane-express-container');
            }
            $container.show();
        },

        onExpressButtonClick: function () {
            if (this.authInProgress) {
                return;
            }
            if (this.token) {
                this.scrollToPlaceOrder();
                return;
            }
            var email = this.getEmail();
            if (!email) {
                this.showNotice(this.settings.fastlane_email_empty || 'Please provide an email address before using Fastlane.');
                this.focusEmailField();
                return;
            }
            if (!this.isValidEmail(email)) {
                this.showNotice(this.settings.fastlane_email_invalid || 'Please enter a valid email address to continue with Fastlane.');
                this.focusEmailField();
                return;
            }
            this.lastLookupEmail = email;
            this.triggerAuthentication(email, true);
        },

        // Guide the customer to the field Fastlane needs: scroll the email
        // input into view and focus it so they can type immediately.
        focusEmailField: function () {
            var $email = $('input[name="billing_email"], #email').filter(':visible').first();
            if (!$email.length || !$email.offset()) {
                return;
            }
            $('html, body').animate({scrollTop: Math.max(0, $email.offset().top - 160)}, 500, function () {
                $email.trigger('focus');
            });
        },

        applyProfile: function (profileData) {
            var card = profileData.card;
            var billing = card && card.paymentSource && card.paymentSource.card ? card.paymentSource.card.billingAddress : null;
            var name = profileData.name || {};
            var shipping = profileData.shippingAddress || null;

            if (billing) {
                // The card's billing address carries no phone number; fall back
                // to the phone stored on the profile's shipping address.
                this.fillAddress('billing', $.extend({}, this.normalizeAddress(billing), {
                    firstName: name.firstName,
                    lastName: name.lastName,
                    phoneNumber: this.extractPhone(billing) || this.extractPhone(shipping)
                }));
            }
            if (shipping && this.settings.needs_shipping === '1') {
                var shippingName = shipping.name || {};
                this.fillAddress('shipping', $.extend({}, this.normalizeAddress(shipping.address || shipping), {
                    firstName: shippingName.firstName || name.firstName,
                    lastName: shippingName.lastName || name.lastName,
                    phoneNumber: this.extractPhone(shipping)
                }));
            }
            this.refreshCheckout();
            if (card) {
                this.setToken(this.mapCardToToken(card));
            }
        },

        changeCard: async function () {
            var self = this;
            if (!this.profile || !this.customerContextId) {
                return;
            }
            try {
                var result = await this.profile.showCardSelector();
                if (result && result.selectionChanged && result.selectedCard) {
                    var billing = result.selectedCard.paymentSource && result.selectedCard.paymentSource.card ? result.selectedCard.paymentSource.card.billingAddress : null;
                    if (billing) {
                        this.fillAddress('billing', this.normalizeAddress(billing));
                        this.refreshCheckout();
                    }
                    this.setToken(this.mapCardToToken(result.selectedCard));
                }
            } catch (err) {
                window.console && console.log('Fastlane card selector error:', err);
            }
        },

        /* ------------------------------------------------------------------ */
        /* Guest enrollment modal                                              */
        /* ------------------------------------------------------------------ */

        openModal: async function () {
            var self = this;
            if (!this.FastlanePaymentComponent) {
                return;
            }
            if (!$('.wpg-fastlane-overlay').length) {
                $(document.body).append(
                    '<div class="wpg-fastlane-overlay" style="display:none;">' +
                        '<div class="wpg-fastlane-modal" role="dialog" aria-modal="true">' +
                            '<div class="wpg-fastlane-modal-body"></div>' +
                            '<div class="wpg-fastlane-modal-buttons">' +
                                '<button type="button" class="button alt wpg-fastlane-tokenize">' + this.esc(this.settings.fastlane_continue || 'Continue') + '</button>' +
                                '<a href="#" class="wpg-fastlane-modal-cancel">' + this.esc(this.settings.fastlane_cancel || 'Cancel') + '</a>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }
            try {
                var options = {};
                var shippingPrefill = this.getShippingPrefill();
                if (this.settings.needs_shipping === '1' && shippingPrefill) {
                    options.shippingAddress = shippingPrefill;
                }
                this.paymentComponent = await this.FastlanePaymentComponent(options);
                $('.wpg-fastlane-modal-body').empty();
                await this.paymentComponent.render('.wpg-fastlane-modal-body');
                $('.wpg-fastlane-overlay').show();
                $(document.body).addClass('wpg-fastlane-modal-open');
            } catch (err) {
                window.console && console.log('Fastlane payment component error:', err);
                this.closeModal();
            }
        },

        closeModal: function () {
            $('.wpg-fastlane-overlay').hide();
            $(document.body).removeClass('wpg-fastlane-modal-open');
        },

        onModalContinue: async function () {
            var self = this;
            if (!this.paymentComponent) {
                return;
            }
            var $button = $('.wpg-fastlane-tokenize');
            $button.prop('disabled', true);
            try {
                var response = await this.paymentComponent.getPaymentToken();
                if (response && response.id) {
                    var source = response.paymentSource && response.paymentSource.card ? response.paymentSource.card : {};
                    if (source.billingAddress) {
                        var billing = this.normalizeAddress(source.billingAddress);
                        billing.firstName = billing.firstName || this.splitName(source.name).first;
                        billing.lastName = billing.lastName || this.splitName(source.name).last;
                        this.fillAddress('billing', billing, true);
                        this.refreshCheckout();
                    }
                    this.closeModal();
                    this.setToken({
                        id: response.id,
                        brand: source.brand || '',
                        lastDigits: source.lastDigits || '',
                        expiry: source.expiry || '',
                        name: source.name || ''
                    });
                }
            } catch (err) {
                window.console && console.log('Fastlane tokenization error:', err);
                this.showNotice(this.settings.fastlane_card_error);
            } finally {
                $button.prop('disabled', false);
            }
        },

        /* ------------------------------------------------------------------ */
        /* Token handoff + tokenized card UI                                   */
        /* ------------------------------------------------------------------ */

        setToken: function (token) {
            var self = this;
            this.token = token;
            window.wpgPPCPFastlaneToken = token ? token.id : '';
            this.syncTokenField();
            this.selectCardGateway();
            this.displayTokenizedCard();
            this.scrollToPlaceOrder();
            // Address autofill above triggers one or more WooCommerce
            // `updated_checkout` cycles that replace the payment box with a
            // fresh, visible card form. The MutationObserver guard catches those,
            // but re-assert on a few timers as a belt-and-suspenders in case the
            // final DOM settles after the observer's debounce window — this is
            // what made the tile appear only intermittently on reload.
            [300, 800, 1500].forEach(function (delay) {
                setTimeout(function () {
                    self.ensureTokenizedCard();
                }, delay);
            });
        },

        // Idempotent: make sure the tokenized-card tile reflects the current
        // token state without needless flicker. Renders the tile when a token
        // exists and the raw card form is showing without one; does nothing when
        // the tile is already in place.
        ensureTokenizedCard: function () {
            if (!this.token) {
                return;
            }
            var $form = $(CARD_FORM_SELECTOR);
            if (!$form.length) {
                return;
            }
            var hasTile = $('.wpg-fastlane-tokenized-card').length > 0;
            var formHidden = $form.is(':hidden');
            if (hasTile && formHidden) {
                return;
            }
            this.displayTokenizedCard();
        },

        // Watch the classic checkout for DOM replacements (WooCommerce rewrites
        // the order-review/payment markup on every `update_checkout`) and
        // re-assert the tokenized card. This is event-driven, so it survives
        // races the one-shot `updated_checkout` handler could lose. Scoped to
        // classic checkout — the block checkout is React-owned and re-injects
        // through its own update path.
        setupTokenGuard: function () {
            var self = this;
            if (this.isBlocks || this._tokenGuardSet || typeof window.MutationObserver === 'undefined') {
                return;
            }
            var container = document.querySelector('form.checkout') || document.querySelector('#order_review');
            if (!container) {
                return;
            }
            var scheduled = false;
            this._tokenObserver = new MutationObserver(function () {
                if (scheduled || !self.token) {
                    return;
                }
                scheduled = true;
                setTimeout(function () {
                    scheduled = false;
                    self.ensureTokenizedCard();
                }, 80);
            });
            this._tokenObserver.observe(container, {childList: true, subtree: true});
            this._tokenGuardSet = true;
        },

        clearToken: function () {
            this.token = null;
            window.wpgPPCPFastlaneToken = '';
            this.lastLookupEmail = '';
            $('#wpg_ppcp_fastlane_token').val('');
            $('.wpg-fastlane-tokenized-card').remove();
            $(CARD_FORM_SELECTOR).show();
            $('.wpg_ppcp_sanbdox_notice').show();
            $('.payment_method_wpg_paypal_checkout_cc .woocommerce-SavedPaymentMethods').show();
            this.mountSignupLink();
        },

        syncTokenField: function () {
            var $field = $('#wpg_ppcp_fastlane_token');
            if ($field.length && this.token) {
                $field.val(this.token.id);
            }
        },

        selectCardGateway: function () {
            var $classic = $('#payment_method_' + GATEWAY_ID);
            if ($classic.length && !$classic.is(':checked')) {
                $classic.prop('checked', true).trigger('click');
            }
            var $blocksRadio = $('#radio-control-wc-payment-method-options-' + GATEWAY_ID);
            if ($blocksRadio.length && !$blocksRadio.is(':checked')) {
                $blocksRadio.trigger('click');
            }
            if (this.isBlocks && window.wp && wp.data && wp.data.dispatch) {
                try {
                    var paymentStore = wp.data.dispatch('wc/store/payment');
                    if (paymentStore && typeof paymentStore.__internalSetActivePaymentMethod === 'function') {
                        paymentStore.__internalSetActivePaymentMethod(GATEWAY_ID);
                    }
                } catch (e) {
                }
            }
            // Never pay with a previously saved WooCommerce token while a
            // Fastlane token is active.
            var $newRadio = $('input[name="wc-wpg_paypal_checkout_cc-payment-token"][value="new"]');
            if ($newRadio.length && !$newRadio.is(':checked')) {
                $newRadio.prop('checked', true).trigger('click');
            }
        },

        displayTokenizedCard: function () {
            if (!this.token) {
                return;
            }
            var $form = $(CARD_FORM_SELECTOR);
            if (!$form.length) {
                return;
            }
            $('.wpg-fastlane-tokenized-card').remove();
            $('.wpg-fastlane-signup-link').remove();
            var label = this.esc(this.readableBrand(this.token.brand)) + ' &bull;&bull;&bull;&bull; ' + this.esc(this.token.lastDigits);
            var iconUrl = this.brandIconUrl(this.token.brand);
            var icon = iconUrl ? '<img src="' + this.esc(iconUrl) + '" alt="" class="wpg-fastlane-card-icon" />' : '';
            var changeLink = this.customerContextId ? '<a href="#" class="wpg-fastlane-card-change">' + this.esc(this.settings.fastlane_change_card || 'Choose a different card') + '</a>' : '';
            var html =
                '<div class="wpg-fastlane-tokenized-card">' +
                    '<div class="wpg-fastlane-card-summary">' + icon + '<span class="wpg-fastlane-card-label">' + label + '</span></div>' +
                    '<div class="wpg-fastlane-card-actions">' +
                        changeLink +
                        '<a href="#" class="wpg-fastlane-card-cancel">' + this.esc(this.settings.fastlane_use_different_card || 'Enter card details manually') + '</a>' +
                    '</div>' +
                '</div>';
            $form.hide().after(html);
            // Keep the "Save to account" checkbox visible: saving works with
            // Fastlane too — the single-use token is vaulted ON_SUCCESS for
            // logged-in customers.
            $('.wpg_ppcp_sanbdox_notice').hide();
            $('.payment_method_wpg_paypal_checkout_cc .woocommerce-SavedPaymentMethods').hide();
        },

        /* ------------------------------------------------------------------ */
        /* Watermark + signup link                                             */
        /* ------------------------------------------------------------------ */

        mountWatermark: async function () {
            var self = this;
            if (!this.FastlaneWatermarkComponent) {
                return;
            }
            var $target = $('#billing_email_field');
            if (!$target.length) {
                $target = $('#email').closest('.wc-block-components-text-input');
            }
            if (!$target.length || $('#wpg-fastlane-watermark').length) {
                return;
            }
            $target.append('<div id="wpg-fastlane-watermark"></div>');
            try {
                this.watermarkComponent = await this.FastlaneWatermarkComponent({includeAdditionalInfo: true});
                this.watermarkComponent.render('#wpg-fastlane-watermark');
            } catch (err) {
                $('#wpg-fastlane-watermark').remove();
            }
        },

        mountSignupLink: function () {
            // The signup link belongs to the email-detection flow only; the
            // express-button flow opens the enrollment modal from the button.
            if (this.flow !== 'email_detection' || this.settings.fastlane_signup !== 'yes' || this.token) {
                return;
            }
            var $form = $(CARD_FORM_SELECTOR);
            if (!$form.length || $('.wpg-fastlane-signup-link').length) {
                return;
            }
            var logo = this.settings.fastlane_logo ? '<img src="' + this.esc(this.settings.fastlane_logo) + '" alt="Fastlane" class="wpg-fastlane-logo" />' : '<span>Fastlane</span>';
            $form.before(
                '<a href="#" class="wpg-fastlane-signup-link">' +
                    '<span>' + this.esc(this.settings.fastlane_signup_text || 'Pay faster with') + '</span> ' + logo +
                '</a>'
            );
        },

        /* ------------------------------------------------------------------ */
        /* Address helpers                                                     */
        /* ------------------------------------------------------------------ */

        // Accepts both PayPal-SDK-shaped and Braintree-shaped Fastlane addresses.
        normalizeAddress: function (address) {
            address = address || {};
            return {
                firstName: address.firstName || '',
                lastName: address.lastName || '',
                addressLine1: address.addressLine1 || address.streetAddress || '',
                addressLine2: address.addressLine2 || address.extendedAddress || '',
                adminArea2: address.adminArea2 || address.locality || '',
                adminArea1: address.adminArea1 || address.region || '',
                postalCode: address.postalCode || '',
                countryCode: address.countryCode || address.countryCodeAlpha2 || '',
                phoneNumber: this.extractPhone(address)
            };
        },

        extractPhone: function (source) {
            if (!source) {
                return '';
            }
            if (source.phoneNumber && typeof source.phoneNumber === 'object') {
                return source.phoneNumber.nationalNumber || '';
            }
            return source.phoneNumber || '';
        },

        fillAddress: function (prefix, address, onlyWhenEmpty) {
            if (!address) {
                return;
            }
            if (this.isBlocks) {
                this.fillBlocksAddress(prefix, address);
                return;
            }
            var setField = function (field, value) {
                if (value === undefined || value === null || value === '') {
                    return;
                }
                var $input = $('#' + prefix + '_' + field);
                if (!$input.length) {
                    return;
                }
                if (onlyWhenEmpty && $input.val()) {
                    return;
                }
                $input.val(value).trigger('change');
            };
            // Country first: WooCommerce rebuilds the State field when the
            // country changes, so state must be (re)applied after that rebuild
            // or it is wiped — e.g. a Fastlane profile shipping address in a
            // different country than the currently selected one.
            setField('country', address.countryCode);
            setField('first_name', address.firstName);
            setField('last_name', address.lastName);
            setField('address_1', address.addressLine1);
            setField('address_2', address.addressLine2);
            setField('city', address.adminArea2);
            setField('postcode', address.postalCode);
            setField('phone', address.phoneNumber);
            var self = this;
            var applyState = function () {
                if (!address.adminArea1) {
                    return;
                }
                var $state = $('#' + prefix + '_state');
                if (!$state.length) {
                    return;
                }
                if (onlyWhenEmpty && $state.val()) {
                    return;
                }
                // Fastlane returns the state sometimes as a code ("GJ", "LA")
                // and sometimes as a full name ("Gujarat", "Louisiana"); the
                // WooCommerce dropdown only accepts option values (codes), so
                // resolve either form against the rendered options.
                var value = address.adminArea1;
                if ($state.is('select')) {
                    value = self.resolveStateOption($state, value);
                    if (!value) {
                        return;
                    }
                }
                if ($state.val() !== value) {
                    $state.val(value).trigger('change');
                }
            };
            applyState();
            $(document.body).one('country_to_state_changed', applyState);
            setTimeout(applyState, 500);
            setTimeout(applyState, 1500);
            if (prefix === 'shipping' && $('#ship-to-different-address-checkbox').length) {
                // Ensure the shipping fields we filled are actually used.
                var billingSame = this.sameAddress(address);
                if (!billingSame && !$('#ship-to-different-address-checkbox').is(':checked')) {
                    $('#ship-to-different-address-checkbox').prop('checked', true).trigger('change');
                }
            }
        },

        fillBlocksAddress: function (prefix, address) {
            if (!window.wp || !wp.data || !wp.data.dispatch) {
                return;
            }
            try {
                var cart = wp.data.dispatch('wc/store/cart');
                var data = {
                    first_name: address.firstName || '',
                    last_name: address.lastName || '',
                    address_1: address.addressLine1 || '',
                    address_2: address.addressLine2 || '',
                    city: address.adminArea2 || '',
                    state: address.adminArea1 || '',
                    postcode: address.postalCode || '',
                    country: address.countryCode || ''
                };
                if (address.phoneNumber) {
                    data.phone = address.phoneNumber;
                }
                if (prefix === 'billing') {
                    data.email = this.getEmail();
                    cart.setBillingAddress(data);
                } else {
                    cart.setShippingAddress(data);
                }
            } catch (e) {
                window.console && console.log('Fastlane block address error:', e);
            }
        },

        // Match a state given as either a code ("GJ") or a full name
        // ("Gujarat") against a WooCommerce state <select>; returns the
        // option value (code) or '' when there is no match.
        resolveStateOption: function ($select, state) {
            var target = String(state || '').trim().toLowerCase();
            if (!target) {
                return '';
            }
            var resolved = '';
            $select.find('option').each(function () {
                var value = String($(this).val() || '').trim().toLowerCase();
                var label = String($(this).text() || '').trim().toLowerCase();
                if (value && (value === target || label === target)) {
                    resolved = $(this).val();
                    return false;
                }
            });
            return resolved;
        },

        sameAddress: function (shipping) {
            var billing1 = $('#billing_address_1').val() || '';
            var billingZip = $('#billing_postcode').val() || '';
            return billing1 === (shipping.addressLine1 || '') && billingZip === (shipping.postalCode || '');
        },

        getShippingPrefill: function () {
            var prefix = $('#ship-to-different-address-checkbox').is(':checked') ? 'shipping' : 'billing';
            var get = function (field) {
                return $('#' + prefix + '_' + field).val() || '';
            };
            var address = {
                firstName: get('first_name'),
                lastName: get('last_name'),
                streetAddress: get('address_1'),
                extendedAddress: get('address_2'),
                locality: get('city'),
                region: get('state'),
                postalCode: get('postcode'),
                countryCodeAlpha2: get('country'),
                phoneNumber: get('phone') || $('#billing_phone').val() || ''
            };
            if (!address.streetAddress || !address.postalCode) {
                return null;
            }
            return address;
        },

        refreshCheckout: function () {
            if (this.isBlocks) {
                return; // Block checkout recalculates from the store dispatches.
            }
            $(document.body).trigger('update_checkout');
        },

        /* ------------------------------------------------------------------ */
        /* Misc utilities                                                      */
        /* ------------------------------------------------------------------ */

        mapCardToToken: function (card) {
            var source = card.paymentSource && card.paymentSource.card ? card.paymentSource.card : {};
            return {
                id: card.id,
                brand: source.brand || '',
                lastDigits: source.lastDigits || '',
                expiry: source.expiry || '',
                name: source.name || ''
            };
        },

        readableBrand: function (brand) {
            brand = String(brand || '').replace(/_/g, ' ').toLowerCase();
            return brand.replace(/\b\w/g, function (c) {
                return c.toUpperCase();
            });
        },

        brandIconUrl: function (brand) {
            var normalized = String(brand || '').toLowerCase().replace(/[^a-z]/g, '');
            var mapping = {
                visa: 'visa',
                mastercard: 'mastercard',
                mc: 'mastercard',
                amex: 'amex',
                americanexpress: 'amex',
                discover: 'discover',
                jcb: 'jcb',
                maestro: 'maestro',
                elo: 'elo',
                hiper: 'hiper'
            };
            if (!this.settings.fastlane_card_icon_base || !mapping[normalized]) {
                return '';
            }
            return this.settings.fastlane_card_icon_base + mapping[normalized] + '.svg';
        },

        splitName: function (fullName) {
            var parts = String(fullName || '').trim().split(/\s+/);
            return {
                first: parts.shift() || '',
                last: parts.join(' ')
            };
        },

        // Post-authentication scroll: block checkout smooth-scrolls to the
        // card gateway radio (100px offset); classic checkout animates to the
        // Place order button with a 200px offset, which lands on the payment
        // section directly above it.
        // Bring the payment section into view after Fastlane fills the card.
        //
        // On a fresh authentication the member address autofill fires a
        // WooCommerce `update_checkout` immediately after this runs, which
        // re-renders the order column and moves/replaces the scroll target — so
        // a single scroll gets undone (that's why it "worked" only on reload,
        // where no autofill re-render happens). Scroll now, then re-scroll once
        // the checkout settles, with a timed fallback if no re-render fires.
        scrollToPlaceOrder: function () {
            var self = this;
            this.doScrollToCard();
            $(document.body)
                .off('updated_checkout.wpgFastlaneScroll')
                .one('updated_checkout.wpgFastlaneScroll', function () {
                    // Let the tile re-assert (ensureTokenizedCard) before scrolling.
                    setTimeout(function () {
                        self.doScrollToCard();
                    }, 200);
                });
            // Fallback for flows where no updated_checkout follows (e.g. no
            // shipping recalculation), so both paths end up scrolled the same.
            setTimeout(function () {
                self.doScrollToCard();
            }, 1200);
        },

        getScrollTarget: function () {
            if (this.isBlocks) {
                var radio = document.getElementById('radio-control-wc-payment-method-options-' + GATEWAY_ID);
                if (radio) {
                    return radio;
                }
            }
            var $tile = $('.wpg-fastlane-tokenized-card:visible').first();
            if ($tile.length) {
                return $tile[0];
            }
            var $box = $('.payment_method_wpg_paypal_checkout_cc:visible, #payment:visible').first();
            if ($box.length) {
                return $box[0];
            }
            var $po = $('#place_order:visible, .wc-block-components-checkout-place-order-button:visible').first();
            return $po.length ? $po[0] : null;
        },

        doScrollToCard: function () {
            var target = this.getScrollTarget();
            if (!target) {
                return;
            }
            var top = Math.max(0, target.getBoundingClientRect().top + window.scrollY - 50);
            // Skip if we are already essentially there, to avoid a jarring
            // second animation when the layout did not actually move.
            if (Math.abs(top - window.scrollY) < 60) {
                return;
            }
            // WooCommerce-style scroll-to (default 'swing' easing), slowed to
            // 1000ms for a gentler glide to the Fastlane card.
            $('html, body').stop(true).animate({scrollTop: top}, 1000);
        },

        showNotice: function (message) {
            if (!message) {
                return;
            }
            var $anchor = $('form.checkout, .wc-block-checkout__main').first();
            if (!$anchor.length) {
                window.alert(message);
                return;
            }
            $('.wpg-fastlane-notice').remove();
            $anchor.prepend('<div class="woocommerce-NoticeGroup wpg-fastlane-notice" role="alert"><ul class="woocommerce-error"><li>' + this.esc(message) + '</li></ul></div>');
        },

        esc: function (value) {
            return String(value === undefined || value === null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        debounce: function (fn, delay) {
            var timer;
            return function () {
                var args = arguments, ctx = this;
                clearTimeout(timer);
                timer = setTimeout(function () {
                    fn.apply(ctx, args);
                }, delay);
            };
        }
    };

    $(function () {
        if (typeof window.ppcp_manager === 'undefined') {
            return;
        }
        window.wpgPPCPFastlane = new WPGFastlane(window.ppcp_manager);
        window.wpgPPCPFastlane.initialize();
    });

})(jQuery);
