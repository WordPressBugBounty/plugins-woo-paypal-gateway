class WPGPayPalSettingsUI {
    constructor() {
        this.pageTypes = ['home', 'category', 'product', 'cart', 'payment'];
        this.sectionMap = {
            product: 'woocommerce_wpg_paypal_checkout_product_button_settings',
            cart: 'woocommerce_wpg_paypal_checkout_cart_button_settings',
            express_checkout: 'woocommerce_wpg_paypal_checkout_express_checkout_button_settings',
            mini_cart: 'woocommerce_wpg_paypal_checkout_mini_cart_button_settings',
            checkout: 'woocommerce_wpg_paypal_checkout_checkout_button_settings',
        };
        this.init();
    }

    init() {
        this.setupOnboarding();
        this.setupToggleHandlers();
        this.setupSandboxSwitching();
        this.setupManualCredentialToggle();
        this.setupPayLaterMessaging();
        this.setupCollapsibles();
        this.setupAppleGooglePay();
        this.enforceReadonlySelect2Option('woocommerce_wpg_paypal_checkout_paypal_button_pages', ['checkout']);
    }

    setupOnboarding() {
        window.onboardingCallback = (authCode, sharedId) => {
            window.onbeforeunload = '';
            if (typeof PAYPAL !== 'undefined' && PAYPAL.apps && PAYPAL.apps.Signup && PAYPAL.apps.Signup.MiniBrowser && typeof PAYPAL.apps.Signup.MiniBrowser.closeFlow === 'function') {
                PAYPAL.apps.Signup.MiniBrowser.closeFlow();
            }
            jQuery('#wpbody').block({
                message: '<div class="nexa-spinner-wrap"><div class="nexa-loader"></div><strong>Configuring connection to PayPal…</strong></div>',
                css: {
                    border: 'none',
                    padding: '20px',
                    backgroundColor: '#fff',
                    borderRadius: '8px',
                    boxShadow: '0 0 12px rgba(0, 0, 0, 0.1)',
                    fontSize: '15px',
                    fontWeight: '500',
                    color: '#333',
                    width: '315px'
                },
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6,
                    cursor: 'wait'
                }
            });
            const is_sandbox = document.querySelector('#woocommerce_wpg_paypal_checkout_sandbox');
            fetch(ppcp_param.wpg_onboarding_endpoint, {
                method: 'POST',
                headers: {'content-type': 'application/json'},
                body: JSON.stringify({
                    authCode,
                    sharedId,
                    nonce: ppcp_param.wpg_onboarding_endpoint_nonce,
                    env: is_sandbox && is_sandbox.value === 'yes' ? 'sandbox' : 'production'
                })
            }).finally(() => {
                this.onboardingInProgress = false;
                window.location.href = window.location.href;
            });
        };
    }

    setupToggleHandlers() {
        jQuery(".button.wpg-ppcp-disconnect").click(() => {
            const sandbox = jQuery('#woocommerce_wpg_paypal_checkout_sandbox').val() === 'yes';
            const prefix = sandbox ? 'sandbox' : 'live';
            jQuery(`#woocommerce_wpg_paypal_checkout_rest_client_id_${prefix}`).val('');
            jQuery(`#woocommerce_wpg_paypal_checkout_rest_secret_id_${prefix}`).val('');
            jQuery('.woocommerce-save-button').prop('disabled', false).click();
        });
    }

    setupSandboxSwitching() {
        const $sandboxToggle = jQuery('#woocommerce_wpg_paypal_checkout_sandbox');
        const $liveFields = jQuery('#woocommerce_wpg_paypal_checkout_rest_client_id_live, #woocommerce_wpg_paypal_checkout_rest_secret_id_live').closest('tr');
        const $sandboxFields = jQuery('#woocommerce_wpg_paypal_checkout_rest_client_id_sandbox, #woocommerce_wpg_paypal_checkout_rest_secret_id_sandbox').closest('tr');
       
        $sandboxToggle.change(function () {
            $liveFields.hide();
            $sandboxFields.hide();
            jQuery('#woocommerce_wpg_paypal_checkout_sandbox_disconnect').closest('tr').hide();
            jQuery('#woocommerce_wpg_paypal_checkout_live_disconnect').closest('tr').hide();
            jQuery('#wpg_guide').hide();

            if (jQuery(this).val() === 'yes') {
                jQuery('#woocommerce_wpg_paypal_checkout_live_onboarding').closest('tr').hide();
                if (ppcp_param.is_sandbox_connected === 'yes') {
                    jQuery('#woocommerce_wpg_paypal_checkout_sandbox_onboarding').closest('tr').hide();
                    jQuery('#woocommerce_wpg_paypal_checkout_sandbox_disconnect').closest('tr').show();
                } else {
                    jQuery('#woocommerce_wpg_paypal_checkout_sandbox_onboarding').closest('tr').show();
                    jQuery('#woocommerce_wpg_paypal_checkout_sandbox_disconnect').closest('tr').hide();
                }
            } else {
                jQuery('#woocommerce_wpg_paypal_checkout_sandbox_onboarding').closest('tr').hide();
                if (ppcp_param.is_live_connected === 'yes') {
                    jQuery('#woocommerce_wpg_paypal_checkout_live_disconnect').closest('tr').show();
                    jQuery('#woocommerce_wpg_paypal_checkout_live_onboarding').closest('tr').hide();
                } else {
                    jQuery('#woocommerce_wpg_paypal_checkout_live_onboarding').closest('tr').show();
                    jQuery('#woocommerce_wpg_paypal_checkout_live_disconnect').closest('tr').hide();
                }
            }
        }).change();
    }

    setupManualCredentialToggle() {
        const $sandboxFields = jQuery('#woocommerce_wpg_paypal_checkout_rest_client_id_sandbox, #woocommerce_wpg_paypal_checkout_rest_secret_id_sandbox').closest('tr');
        const $liveFields = jQuery('#woocommerce_wpg_paypal_checkout_rest_client_id_live, #woocommerce_wpg_paypal_checkout_rest_secret_id_live').closest('tr');
        const $sandboxGuide = jQuery('#woocommerce_paypal_smart_checkout_sandbox_api_credentials, #woocommerce_paypal_smart_checkout_sandbox_api_credentials + p');
        const $liveGuide = jQuery('#woocommerce_paypal_smart_checkout_api_credentials, #woocommerce_paypal_smart_checkout_api_credentials + p');

        jQuery(".wpg_paypal_checkout_gateway_manual_credential_input").on('click', function (e) {
            e.preventDefault();
            const isSandbox = jQuery('#woocommerce_wpg_paypal_checkout_sandbox').val() === 'yes';
            if (isSandbox) {
                $sandboxFields.toggle();
                jQuery('#wpg_guide').toggle();
                $sandboxGuide.toggle();
            } else {
                $liveFields.toggle();
                jQuery('#wpg_guide').toggle();
                $liveGuide.toggle();
            }
        });
    }

    setupPayLaterMessaging() {
        const toggleFields = () => {
            const enabled = jQuery('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging').is(':checked');
            const selectedPages = jQuery('#woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').val() || [];
            jQuery('.pay_later_messaging_field').closest('tr').toggle(enabled);
            this.pageTypes.forEach(type => {
                const show = enabled && selectedPages.includes(type);
                jQuery(`.pay_later_messaging_${type}_field`).closest('tr').toggle(show);
                jQuery(`.pay_later_messaging_${type}_field`).closest('tr').closest('table').toggle(show);

                jQuery(`#woocommerce_wpg_paypal_checkout_pay_later_messaging_${type}_page_settings`).toggle(show);
            });
        };
        jQuery('#woocommerce_wpg_paypal_checkout_enabled_pay_later_messaging, #woocommerce_wpg_paypal_checkout_pay_later_messaging_page_type').change(toggleFields);
        toggleFields();
    }

    setupCollapsibles() {
        const updateSections = () => {
            const selected = jQuery('#woocommerce_wpg_paypal_checkout_paypal_button_pages').val() || [];
            jQuery('h3.ppcp-collapsible-section').each((_, el) => {
                const $el = jQuery(el);
                const key = Object.keys(this.sectionMap).find(k => this.sectionMap[k] === $el.attr('id'));
                const show = selected.includes(key);
                $el.toggle(show);
                $el.nextUntil('h3.ppcp-collapsible-section').hide();
            });
        };
        jQuery('h3.ppcp-collapsible-section').on('click', function () {
            const $this = jQuery(this);
            const isActive = $this.hasClass('active');
            jQuery('h3.ppcp-collapsible-section.active').removeClass('active').nextUntil('h3.ppcp-collapsible-section').slideUp(200);
            if (!isActive) {
                $this.addClass('active').nextUntil('h3.ppcp-collapsible-section').slideDown(200);
            }
        });
        jQuery('#woocommerce_wpg_paypal_checkout_paypal_button_pages').on('change', updateSections);
        updateSections();
    }

    setupAppleGooglePay() {
        const togglePaySection = (prefix) => {
            const enabled = jQuery(`#woocommerce_wpg_paypal_checkout_enabled_${prefix}_pay`).is(':checked');
            const selectedPages = jQuery(`#woocommerce_wpg_paypal_checkout_${prefix}_pay_pages`).val() || [];
            const allPages = ['product', 'cart', 'mini_cart', 'express_checkout', 'checkout'];
            allPages.forEach(page => {
                const heading = jQuery(`#woocommerce_wpg_paypal_checkout_${prefix}_pay_${page}_page_settings`);
                const table = heading.next('table.form-table');
                heading.toggle(enabled && selectedPages.includes(page));
                table.toggle(enabled && selectedPages.includes(page));
            });
            jQuery(`#woocommerce_wpg_paypal_checkout_${prefix}_pay_pages`).closest('tr').toggle(enabled);
        };
        ['apple', 'google'].forEach(type => {
            togglePaySection(type);
            jQuery(`#woocommerce_wpg_paypal_checkout_enabled_${type}_pay, #woocommerce_wpg_paypal_checkout_${type}_pay_pages`).change(() => togglePaySection(type));
        });
    }

    enforceReadonlySelect2Option(selectId, lockedValues = []) {
        const $select = jQuery(`#${selectId}`);
        const update = () => {
            let current = $select.val() || [];
            lockedValues.forEach(val => {
                if (!current.includes(val))
                    current.push(val);
            });
            $select.val(current).trigger('change.select2');
            setTimeout(() => {
                const $container = $select.next('.select2-container');
                $container.find('.select2-selection__choice').each(function () {
                    const $choice = jQuery(this);
                    const label = $choice.attr('title');
                    lockedValues.forEach(val => {
                        const lockedLabel = $select.find(`option[value="${val}"]`).text();
                        if (label === lockedLabel) {
                            $choice.css({
                                backgroundColor: '#f0f0f0',
                                borderColor: '#ccc',
                                color: '#666',
                                cursor: 'not-allowed',
                                opacity: 0.6,
                            });
                            $choice.find('.select2-selection__choice__remove').remove();
                            if (!$choice.find('.ppcp-lock-icon').length) {
                                $choice.append('<span class="ppcp-lock-icon" style="margin-left:4px;">🔒</span>');
                            }
                        }
                    });
                });
            }, 50);
        };
        update();
        $select.on('change', update);
    }
}

jQuery(document).ready(() => new WPGPayPalSettingsUI());

function wpg_ppcp_display_success_popup() {
    const modal = document.getElementById('wpg_ppcp_display_success_popup');
    const burstLayer = document.getElementById('wpg_ppcp_display_success_popup_burst_layer');

    if (!modal || !burstLayer) {
      return;
    }

    // A small, pleasant palette
    const colors = [
      '#22c55e', '#34d399', '#60a5fa', '#fbbf24',
      '#fb7185', '#a78bfa', '#f472b6', '#94a3b8'
    ];

    function openModal() {
      if (!modal || !burstLayer) return;
      
      modal.classList.add('is_open');
      document.body.style.overflow = 'hidden';
      crackerBurst();
      setTimeout(crackerBurstCorners, 120);
    }

    function closeModal() {
      if (!modal) return;
      
      modal.classList.remove('is_open');
      document.body.style.overflow = '';
      clearBurstLayer();
    }

    function clearBurstLayer() {
      if (!burstLayer) return;
      burstLayer.innerHTML = '';
    }

    // Burst from center of popup
    function crackerBurst() {
      burst(260, 140, 5);
    }

    // Two small bursts from top corners
    function crackerBurstCorners() {
      burst(80, 70, 5, 0.9);
      burst(440, 70, 5, 0.9);
    }

    /**
     * Create burst at (ox, oy) within modal.
     */
    function burst(ox, oy, count, power = 1) {
      if (!burstLayer) return;
      
      const rect = burstLayer.getBoundingClientRect();
      if (!rect.width || !rect.height) return;

      for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'wpg_ppcp_display_success_popup_confetti';

        // Random color + size variety
        const color = colors[Math.floor(Math.random() * colors.length)];
        const w = 6 + Math.random() * 8;
        const h = 4 + Math.random() * 6;
        el.style.background = color;
        el.style.width = w + 'px';
        el.style.height = h + 'px';

        // Start position
        el.style.left = ox + 'px';
        el.style.top = oy + 'px';

        // Physics-ish randomness
        const angle = (Math.random() * Math.PI * 2);
        const dist = (60 + Math.random() * 200) * power;
        const x1 = Math.cos(angle) * dist;
        const y1 = Math.sin(angle) * dist + (90 * power);
        const rot = (Math.random() * 720 - 360) + 'deg';

        // Start offsets
        el.style.setProperty('--wpg_ppcp_display_success_popup_x0', (Math.random() * 10 - 5) + 'px');
        el.style.setProperty('--wpg_ppcp_display_success_popup_y0', (Math.random() * 10 - 5) + 'px');

        // End offsets
        el.style.setProperty('--wpg_ppcp_display_success_popup_x1', x1 + 'px');
        el.style.setProperty('--wpg_ppcp_display_success_popup_y1', y1 + 'px');
        el.style.setProperty('--wpg_ppcp_display_success_popup_rot', rot);

        // Duration
        const dur = (1500 + Math.random() * 700) + 'ms';
        el.style.setProperty('--wpg_ppcp_display_success_popup_dur', dur);

        burstLayer.appendChild(el);

        // Cleanup after animation
        setTimeout(() => el.remove(), 1300);
      }
    }

    // Close handlers
    modal.addEventListener('click', (e) => {
      if (e.target.hasAttribute('data-wpg_ppcp_display_success_popup_close')) closeModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeModal();
    });

    // Open the modal
    openModal();
  }

  // Auto-open for demo purposes
  window.addEventListener('load', function() {
    wpg_ppcp_display_success_popup();
  });
