<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Gateway
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Gateway/includes
 * @author     easypayment
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.
class PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC extends PPCP_Paypal_Checkout_For_Woocommerce_Gateway {

    public $dcc_applies;
    public $enabled;
    public $enable_save_card;

    public function __construct() {
        parent::__construct();
        $this->supports = array(
            'products',
            'refunds',
            'pre-orders',
            'subscriptions',
            'subscription_cancellation',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_amount_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'subscription_date_changes',
            'multiple_subscriptions'
        );
        $this->init_form_fields();
        $this->plugin_name = 'ppcp-paypal-checkout-cc';
        $this->title = $this->advanced_card_payments_title;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        $this->icon = apply_filters('woocommerce_ppcp_cc_icon', WPG_PLUGIN_ASSET_URL . 'assets/images/paypal-monogram.svg');
        $this->id = 'wpg_paypal_checkout_cc';
        $this->has_fields = true;
        $this->method_title = _x('Credit or Debit Card (PayPal) By Easy Payment', 'Important', 'woo-paypal-gateway');
        $this->method_description = _x('Advanced Card Processing.', 'Important', 'woo-paypal-gateway');
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate')) {
            include_once (WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-dcc-validate.php');
        }
        $this->enabled = $this->cc_enable = $this->get_option('enable_advanced_card_payments', 'no');
        $this->enable_save_card = 'yes' === $this->get_option('enable_save_card', 'no');
        if (!$this->enable_save_card && (function_exists('WFOCU_Core') || defined('WFOCU_VERSION') || class_exists('WFOCU_Core', false))) {
            $this->enable_save_card = true;
        }
        $this->sandbox = 'yes' === $this->get_option('sandbox', 'no');
        if ($this->enable_save_card) {
            $this->supports[] = 'tokenization';
            $this->supports[] = 'add_payment_method';
        }
        $this->dcc_applies = PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate::instance();
        // Same label filter the block checkout applies, so EU-compliance layers
        // (e.g. Germanized's pay-now wording) reach classic checkout too.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API following the plugin's established wpg_ppcp_* prefix.
        $this->order_button_text = apply_filters('wpg_ppcp_checkout_button_label', __('Place order', 'woo-paypal-gateway'));
    }

    public function payment_fields() {
        if ($this->sandbox) {
            echo '<div class="wpg_ppcp_sanbdox_notice" style="margin: 5px 0 20px 8px; font-size: 13px;display:none;">';
            echo esc_html_x('Sandbox Mode Enabled.', 'Important', 'woo-paypal-gateway') . '<br>';
            echo esc_html_x('Use test card 4111 1111 1111 1111 with any future expiration date and any CVV.', 'Important', 'woo-paypal-gateway');
            echo '</div>';
        }
        if ($this->supports('tokenization') && (is_checkout() || is_checkout_pay_page())) {
            $this->tokenization_script();
            $this->saved_payment_methods();
            $this->form();
            echo '<div id="payments-sdk__contingency-lightbox"></div>';
            $this->save_payment_method_checkbox();
        } else {
            $this->form();
            echo '<div id="payments-sdk__contingency-lightbox"></div>';
        }
    }

    /**
     * Handle the My-Account "Add payment method" submission.
     *
     * Creates a PayPal setup token and redirects the customer to PayPal to
     * approve saving their payment method. On return the token is exchanged for
     * a permanent vault token (see request::ppcp_add_payment_method_create_token).
     *
     * @return array
     */
    public function add_payment_method() {
        if (!is_user_logged_in()) {
            wc_add_notice(__('You must be logged in to add a payment method.', 'woo-paypal-gateway'), 'error');
            return array('result' => 'failure', 'redirect' => wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
        }
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $result = $request->ppcp_add_payment_method_setup_token();

        // The approval URL is on paypal.com, which wp_safe_redirect() would
        // block, so redirect manually to the external approval page.
        if (!empty($result['redirect']) && 'success' === $result['result']) {
            wp_redirect($result['redirect']); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
            exit;
        }

        return array(
            'result'   => 'failure',
            'redirect' => wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')),
        );
    }

    public function save_payment_method_checkbox() {
        $html = sprintf(
                '<p class="form-row woocommerce-SavedPaymentMethods-saveNew wpg_ppcp_save_card">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
                esc_attr($this->id),
                esc_html__('Save to account', 'woo-paypal-gateway')
        );
        /**
         * Filter the saved payment method checkbox HTML
         *
         * @since 2.6.0
         * @param string $html Checkbox HTML.
         * @param WC_Payment_Gateway $this Payment gateway instance.
         * @return string
         */
        echo apply_filters('woocommerce_payment_gateway_save_new_payment_method_option_html', $html, $this); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook fired as an integration point; it is not a global this plugin declares.
    }

    public function admin_options() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect logic, no state change.
        if (isset($_GET['section']) && 'wpg_paypal_checkout_cc' === sanitize_text_field(wp_unslash($_GET['section']))) {
            wp_safe_redirect(
                    admin_url(
                            'admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_paypal_checkout_cc'
                    )
            );
            exit;
        }
    }

    public function form() {
        wp_enqueue_script('ppcp-checkout-js');
        wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        ?>
        <div id="wc-<?php echo esc_attr($this->id); ?>-form" class='wc-credit-card-form wc-payment-form'>
            <div class="wpg-paypal-cc-field full-width">
                <label for="wpg_paypal_checkout_cc-card-number" style="display: none;">
                    <?php echo esc_html_x('Card number', 'Important', 'woo-paypal-gateway'); ?>
                </label>
                <div id="wpg_paypal_checkout_cc-card-number"></div>
            </div>

            <div class="wpg-paypal-cc-field half-width">
                <label for="wpg_paypal_checkout_cc-card-expiry" style="display: none;">
                    <?php echo esc_html_x('Expiration date', 'Important', 'woo-paypal-gateway'); ?>
                </label>
                <div id="wpg_paypal_checkout_cc-card-expiry"></div>
            </div>
            <div class="wpg-paypal-cc-field half-width">
                <label for="wpg_paypal_checkout_cc-card-cvc" style="display: none;">
                    <?php echo esc_html_x('Security code', 'Important', 'woo-paypal-gateway'); ?>
                </label>

                <div class="wpg-cvc-wrapper">
                    <div id="wpg_paypal_checkout_cc-card-cvc"></div>
                    <div class="wpg-ppcp-card-cvv-icon" style="display: none;">
                        <svg class="wpg-card-cvc-icon" width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="var(--colorIconCardCvc)" role="img" aria-labelledby="cvcDesc">
                            <path opacity=".2" fill-rule="evenodd" clip-rule="evenodd" d="M15.337 4A5.493 5.493 0 0013 8.5c0 1.33.472 2.55 1.257 3.5H4a1 1 0 00-1 1v1a1 1 0 001 1h16a1 1 0 001-1v-.6a5.526 5.526 0 002-1.737V18a2 2 0 01-2 2H3a2 2 0 01-2-2V6a2 2 0 012-2h12.337zm6.707.293c.239.202.46.424.662.663a2.01 2.01 0 00-.662-.663z"></path>
                            <path opacity=".4" fill-rule="evenodd" clip-rule="evenodd" d="M13.6 6a5.477 5.477 0 00-.578 3H1V6h12.6z"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M18.5 14a5.5 5.5 0 110-11 5.5 5.5 0 010 11zm-2.184-7.779h-.621l-1.516.77v.786l1.202-.628v3.63h.943V6.22h-.008zm1.807.629c.448 0 .762.251.762.613 0 .393-.37.668-.904.668h-.235v.668h.283c.565 0 .95.282.95.691 0 .393-.377.66-.911.66-.393 0-.786-.126-1.194-.37v.786c.44.189.88.291 1.312.291 1.029 0 1.736-.526 1.736-1.288 0-.535-.33-.967-.88-1.14.472-.157.778-.573.778-1.045 0-.738-.652-1.241-1.595-1.241a3.143 3.143 0 00-1.234.267v.77c.378-.212.763-.33 1.132-.33zm3.394 1.713c.574 0 .974.338.974.778 0 .463-.4.785-.974.785-.346 0-.707-.11-1.076-.337v.809c.385.173.778.26 1.163.26.204 0 .392-.032.573-.08a4.313 4.313 0 00.644-2.262l-.015-.33a1.807 1.807 0 00-.967-.252 3 3 0 00-.448.032V6.944h1.132a4.423 4.423 0 00-.362-.723h-1.587v2.475a3.9 3.9 0 01.943-.133z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <?php if (function_exists('woo_paypal_gateway_ppcp_is_fastlane_enabled') && woo_paypal_gateway_ppcp_is_fastlane_enabled()) : ?>
                <input type="hidden" id="wpg_ppcp_fastlane_token" name="wpg_ppcp_fastlane_token" value="" autocomplete="off" />
            <?php endif; ?>
        </div>
        <?php
    }

    public function get_icon() {
        $title_options = $this->card_labels();
        $images = [];
        $totalIcons = 0;
        foreach ($title_options as $icon_key => $icon_value) {
            if (!in_array($icon_key, $this->disable_cards)) {
                if ($this->dcc_applies->can_process_card($icon_key)) {
                    $iconUrl = esc_url(WPG_PLUGIN_ASSET_URL) . 'assets/' . esc_attr($icon_key) . '.svg';
                    $iconTitle = esc_attr($icon_value);
                    $images[] = sprintf('<img title="%s" src="%s" class="ppcp-card-icon ae-icon-%s" /> ', $iconTitle, $iconUrl, $iconTitle);
                    $totalIcons++;
                }
            }
        }
        return implode('', $images) . '<div class="ppcp-clearfix"></div>';
    }

    public function get_block_icon() {
        $title_options = $this->card_labels();
        $images = [];
        foreach ($title_options as $icon_key => $icon_value) {
            if (!in_array($icon_key, $this->disable_cards)) {
                if ($this->dcc_applies->can_process_card($icon_key)) {
                    $iconUrl = esc_url(WPG_PLUGIN_ASSET_URL) . 'assets/' . esc_attr($icon_key) . '.svg';
                    $images[] = $iconUrl;
                }
            }
        }
        return $images;
    }

    private function card_labels(): array {
        return array(
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'maestro' => 'Maestro',
            'amex' => 'American Express',
            'discover' => 'Discover',
            'jcb' => 'JCB',
            'elo' => 'Elo',
            'hiper' => 'Hiper',
        );
    }

    public function is_credentials_set() {
        if (!empty($this->client_id) && !empty($this->secret_id)) {
            return true;
        } else {
            return false;
        }
    }

    public function process_payment($woo_order_id) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $order = wc_get_order($woo_order_id);
        $is_success = false;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only token handling, nonce verified elsewhere.
        $token = isset($_POST['wc-wpg_paypal_checkout_cc-payment-token']) ? sanitize_text_field(wp_unslash($_POST['wc-wpg_paypal_checkout_cc-payment-token'])) : '';
        if ($order instanceof WC_Order && ((float) $order->get_total() <= 0 || woo_paypal_gateway_ppcp_order_requires_preorder_tokenization($order))) {
            // Zero-total signup (free trial) or charge-upon-release pre-order:
            // vault instead of charging. A chosen saved token completes the
            // order directly; otherwise the buyer approves a vault setup token.
            // Charge-upon-release pre-orders carry their FULL total, so they
            // are routed here explicitly instead of through the charging flow.
            if (!empty($token) && 'new' !== $token) {
                $wc_token = WC_Payment_Tokens::get($token);
                if ($wc_token && (int) $wc_token->get_user_id() === get_current_user_id()) {
                    $this->request->save_payment_token($order, $wc_token->get_token());
                    woo_paypal_gateway_ppcp_complete_zero_total_order($order, $wc_token->get_token());
                    WC()->cart->empty_cart();
                    return array('result' => 'success', 'redirect' => $this->get_return_url($order));
                }
            }
            return $this->request->ppcp_paypal_setup_tokens_zero_total($woo_order_id);
        }
        // Fastlane by PayPal: the JS component already tokenized the buyer's card
        // into a single-use token, so the payment is completed in one server-side
        // Orders v2 call — no CardFields round-trip, no approval redirect.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout nonce verified by process_checkout().
        $fastlane_token = isset($_POST['wpg_ppcp_fastlane_token']) ? sanitize_text_field(wp_unslash($_POST['wpg_ppcp_fastlane_token'])) : '';
        if (!empty($fastlane_token) && function_exists('woo_paypal_gateway_ppcp_is_fastlane_enabled') && woo_paypal_gateway_ppcp_is_fastlane_enabled()) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $save_card = $this->enable_save_card && !empty($_POST['wc-wpg_paypal_checkout_cc-new-payment-method']);
            if ($order instanceof WC_Order) {
                $order->update_meta_data('_payment_action', $this->paymentaction);
                $order->update_meta_data('enviorment', $this->sandbox ? 'sandbox' : 'live');
                $order->save_meta_data();
            }
            $is_success = $this->request->wpg_ppcp_fastlane_payment($woo_order_id, $fastlane_token, $save_card);
            if ($is_success) {
                woo_paypal_gateway_clear_ppcp_session_and_cart();
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }
            if (function_exists('wc_add_notice') && function_exists('wc_notice_count') && 0 === wc_notice_count('error')) {
                wc_add_notice(__('Your Fastlane payment could not be processed. Please try again or enter your card details manually.', 'woo-paypal-gateway'), 'error');
            }
            return array(
                'result' => 'failure',
                'redirect' => wc_get_cart_url(),
            );
        }
        if (!empty($token) && 'new' !== $token) {
            // The checkout posts the WooCommerce token id; charging needs the PayPal
            // vault id that token stands for, recorded on the order where
            // wpg_ppcp_add_payment_source() reads it. Without this the lookup found no
            // _payment_tokens_id on a freshly created order and fell back to whatever
            // the customer's PayPal vault happened to list first — or, when that listing
            // came back empty, attached no payment source at all. PayPal then answered
            // with a plain CREATED order awaiting approval instead of charging the card,
            // and the shopper was shown "Something went wrong. Please contact us to get
            // assistance." Binding the chosen token also means a customer with several
            // saved cards is charged the one they actually selected.
            $wc_token = WC_Payment_Tokens::get($token);
            if (!$wc_token instanceof WC_Payment_Token
                || get_current_user_id() < 1
                || (int) $wc_token->get_user_id() !== get_current_user_id()
                || $wc_token->get_gateway_id() !== $this->id) {
                wc_add_notice(__('The saved payment method you selected is not available. Please choose another payment method.', 'woo-paypal-gateway'), 'error');
                return array(
                    'result'   => 'failure',
                    'redirect' => wc_get_checkout_url(),
                );
            }
            $this->request->save_payment_token($order, $wc_token->get_token());
            $order->update_meta_data('_wpg_ppcp_used_payment_method', $this->ppcp_payment_source_type_for_token($wc_token));
            $order->save_meta_data();
            $is_success = $this->request->wpg_ppcp_capture_order_using_payment_method_token($woo_order_id);
            unset(WC()->session->ppcp_session);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for context, no state change.
        } elseif (isset($_GET['from']) && 'checkout' === sanitize_text_field(wp_unslash($_GET['from']))) {
            woo_paypal_gateway_ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
            $checkout_post = woo_paypal_gateway_ppcp_get_session('wpg_ppcp_block_checkout_post');
            if (!empty($checkout_post)) {
                $order = wc_get_order($woo_order_id);
                if(isset($checkout_post['customer_note']) && !empty($checkout_post['customer_note'])) {
                    $order->set_customer_note($checkout_post['customer_note']);
                }
                $order->set_created_via('store-api');
                $order->save();
            }
            $this->request->ppcp_create_order_request($woo_order_id);
            exit;
        } elseif ($paypal_order_id = woo_paypal_gateway_ppcp_get_paypal_order_id_from_session()) {
            $is_success = ($this->paymentaction === 'capture') ? $this->request->ppcp_order_capture_request($woo_order_id) : $this->request->ppcp_order_auth_request($woo_order_id);
            $order->update_meta_data('_payment_action', $this->paymentaction);
            $order->update_meta_data('enviorment', $this->sandbox ? 'sandbox' : 'live');
            $order->save_meta_data();
            // Preserve the session when the capture is only waiting on PayPal to finish
            // settling the buyer's approval, so the shopper can simply place the order again.
            if (empty($this->request->capture_blocked_pending)) {
                unset(WC()->session->ppcp_session);
            }
        } elseif ($this->ppcp_is_store_api_checkout() && !$this->ppcp_allow_card_wallet_redirect($woo_order_id)) {
            // Block checkout (Store API) card payment with nothing to charge against.
            //
            // The card gateway completes a block-checkout payment through the PayPal
            // CardFields component: the shopper's card is submitted to PayPal in the
            // browser, any 3-D Secure challenge runs there, and only then is the
            // approved PayPal order captured. The Store API submission that WooCommerce
            // Blocks sends alongside it is therefore never the payment — reaching this
            // point means it arrived while the CardFields round-trip was still in flight
            // and there is, correctly, no approved PayPal order to capture yet.
            //
            // The generic fallback below would answer that by creating a PayPal *wallet*
            // order and returning its approval link, and Blocks would navigate the
            // browser to paypal.com. Shoppers reported exactly that: card details
            // entered, Place order clicked, no 3-D Secure prompt, and PayPal's login
            // page instead. It also silently moved a card payment onto a flow that never
            // performs card authentication, so the 3-D Secure liability-shift check in
            // PPCP_Paypal_Checkout_For_Woocommerce_3DS was bypassed altogether.
            //
            // A card payment must never be redirected to the PayPal wallet, so fail
            // cleanly and leave the CardFields flow — which owns this checkout — to
            // finish. Only the block checkout is affected: the classic checkout, the
            // order-pay page and the PayPal wallet gateway (whose "Place order then
            // redirect to PayPal" flow is a real, supported setting) all still reach the
            // fallback below unchanged.
            if (ob_get_length()) {
                ob_end_clean();
            }
            if (function_exists('wc_add_notice') && function_exists('wc_notice_count') && 0 === wc_notice_count('error')) {
                wc_add_notice(__('Your card payment could not be completed. Please check your card details and place your order again.', 'woo-paypal-gateway'), 'error');
            }
            return array(
                'result'   => 'failure',
                'redirect' => woo_paypal_gateway_get_checkout_url(),
            );
        } else {
            if (ob_get_length()) {
                ob_end_clean();
            }
            woo_paypal_gateway_ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
            return $this->request->ppcp_regular_create_order_request($woo_order_id);
        }
        if ($is_success) {
            woo_paypal_gateway_clear_ppcp_session_and_cart();
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }
        return [
            'result' => 'failure',
            'redirect' => wc_get_cart_url(),
        ];
    }

    /**
     * Whether this payment is being processed for a WooCommerce Blocks (Store API)
     * checkout submission.
     *
     * Deliberately independent of anything the browser sends: a shopper running a
     * cached copy of the block bundle from before this release must be protected too.
     * The classic checkout posts to ?wc-ajax=checkout and this plugin's own
     * create-order endpoint to ?wc-api=..., neither of which is a REST request, so
     * only the block checkout answers true here.
     *
     * @return bool
     */
    /**
     * Escape hatch for a store that genuinely needs the old PayPal-wallet fallback on
     * a block-checkout card payment. Off by default: redirecting a card shopper to the
     * PayPal wallet skips card authentication entirely.
     *
     * @param int $woo_order_id Order being paid.
     * @return bool
     */
    private function ppcp_allow_card_wallet_redirect($woo_order_id) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        return (bool) apply_filters('wpg_ppcp_allow_card_wallet_redirect', false, $woo_order_id);
    }

    private function ppcp_is_store_api_checkout() {
        if (function_exists('WC') && is_callable(array(WC(), 'is_rest_api_request')) && WC()->is_rest_api_request()) {
            return true;
        }
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Which PayPal payment source a saved token should be charged against.
     *
     * The vault id alone does not tell PayPal whether it is a card or a PayPal
     * account, and the order is created with payment_source.{type}.vault_id, so the
     * type has to be right or the charge is rejected.
     *
     * @param WC_Payment_Token $wc_token Saved token chosen at checkout.
     * @return string One of paypal|card|google_pay|apple_pay|venmo.
     */
    private function ppcp_payment_source_type_for_token($wc_token) {
        $stored = get_metadata('payment_token', $wc_token->get_id(), '_ppcp_used_payment_method', true);
        if (in_array($stored, array('paypal', 'card', 'google_pay', 'apple_pay', 'venmo'), true)) {
            return $stored;
        }
        // Tokens vaulted from a PayPal account are stored as CC tokens carrying a
        // "PayPal Vault" card type; anything else is a real card.
        if (is_callable(array($wc_token, 'get_card_type'))
            && 'paypal vault' === strtolower((string) $wc_token->get_card_type())) {
            return 'paypal';
        }
        return 'card';
    }

    public function can_refund_order($order) {
        $has_api_creds = false;
        if (!empty($this->client_id) && !empty($this->secret_id)) {
            $has_api_creds = true;
        }
        return $order && $order->get_transaction_id() && $has_api_creds;
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error', __('Refund failed.', 'woo-paypal-gateway'));
        }
        include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $transaction_id = $order->get_transaction_id();
        $bool = $this->request->ppcp_refund_order($order_id, $amount, $reason, $transaction_id);
        return $bool;
    }

    public function is_available() {
        if ('yes' === $this->get_option('admin_mode')) {
            if (current_user_can('administrator') || current_user_can('shop_manager')) {
                return $this->is_credentials_set() && $this->cc_enable === 'yes';
            }
            return false;
        }
        if ($this->is_credentials_set() && $this->cc_enable === 'yes') {
            return true;
        }
        return false;
    }

    public function process_subscription_payment($order, $amount_to_charge) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $order_id = $order->get_id();
        $result = $this->request->wpg_ppcp_capture_order_using_payment_method_token($order_id);
        // Treat anything other than a strict true (false, or null from a network
        // error/exception) as a failed renewal so retry/dunning can trigger.
        if (true !== $result) {
            $order = wc_get_order($order_id);
            if ($order && !in_array($order->get_status(), array('processing', 'completed', 'on-hold'), true)) {
                $order->update_status('failed', __('Subscription renewal payment failed at PayPal.', 'woo-paypal-gateway'));
            }
        }
    }

    public function free_signup_order_payment($order_id) {
        try {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
            $token_id = isset($_POST['wc-wpg_paypal_checkout_cc-payment-token']) ? wc_clean(wp_unslash($_POST['wc-wpg_paypal_checkout_cc-payment-token'])) : '';
            if (!empty($token_id) && $token_id !== 'new') {
                if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
                    include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                }
                $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
                $order = wc_get_order($order_id);
                if (!$order) {
                    wc_add_notice(__('Payment error: order not found.', 'woo-paypal-gateway'), 'error');
                    return array('result' => 'failure');
                }
                $token = WC_Payment_Tokens::get($token_id);
                if ($token && $token->get_user_id() === get_current_user_id()) {
                    $order->payment_complete($token->get_token());
                    $this->request->save_payment_token($order, $token->get_token());
                    WC()->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                }
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            wc_add_notice(__('Payment error: unable to process with saved payment method.', 'woo-paypal-gateway'), 'error');
            return array('result' => 'failure');
        } catch (Exception $ex) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Free signup CC payment error: ' . $ex->getMessage(), array('source' => 'wpg_paypal_checkout'));
            }
            wc_add_notice(__('Payment error: unable to process with saved payment method.', 'woo-paypal-gateway'), 'error');
            return array('result' => 'failure');
        }
    }
}
