<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Gateway
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Gateway/includes
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC extends PPCP_Paypal_Checkout_For_Woocommerce_Gateway {

    public $dcc_applies;
    public $enabled;
    public $enable_save_card;

    public function __construct() {
        parent::__construct();
        $this->supports = array(
            'products',
            'refunds',
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
        $this->icon = apply_filters('woocommerce_ppcp_cc_icon', WPG_PLUGIN_ASSET_URL . 'assets/images/wpg_cards.png');
        $this->id = 'wpg_paypal_checkout_cc';
        $this->has_fields = true;
        $this->method_title = __('Credit or Debit Card (PayPal) By Easy Payment', 'woo-paypal-gateway');
        $this->method_description = __('Advanced Card Processing.', 'woo-paypal-gateway');
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate')) {
            include_once ( WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-dcc-validate.php');
        }
        $this->enabled = $this->cc_enable = $this->get_option('enable_advanced_card_payments', 'no');
        $this->enable_save_card = 'yes' === $this->get_option('enable_save_card', 'no');
        if ($this->enable_save_card) {
            $this->supports[] = 'tokenization';
        }
        $this->dcc_applies = PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate::instance();
    }

    public function payment_fields() {
        if ($this->supports('tokenization') && is_checkout()) {
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

    public function save_payment_method_checkbox() {
        $html = sprintf(
                '<p class="form-row woocommerce-SavedPaymentMethods-saveNew wpg_ppcp_save_card" style="display: none;">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
                esc_attr($this->id),
                esc_html__('Save to account', 'woocommerce')
        );
        /**
         * Filter the saved payment method checkbox HTML
         *
         * @since 2.6.0
         * @param string $html Checkbox HTML.
         * @param WC_Payment_Gateway $this Payment gateway instance.
         * @return string
         */
        echo apply_filters('woocommerce_payment_gateway_save_new_payment_method_option_html', $html, $this); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function admin_options() {
        if (isset($_GET['section']) && 'wpg_paypal_checkout_cc' === $_GET['section']) {
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_paypal_checkout_cc'));
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
                    <?php esc_html_e('Card number', 'woo-paypal-gateway'); ?>
                </label>
                <div id="wpg_paypal_checkout_cc-card-number"></div>
            </div>

            <div class="wpg-paypal-cc-field half-width">
                <label for="wpg_paypal_checkout_cc-card-expiry" style="display: none;">
                    <?php esc_html_e('Expiration date', 'woo-paypal-gateway'); ?>
                </label>
                <div id="wpg_paypal_checkout_cc-card-expiry"></div>
            </div>

            <div class="wpg-paypal-cc-field half-width">
                <label for="wpg_paypal_checkout_cc-card-cvc" style="display: none;">
                    <?php esc_html_e('Security code', 'woo-paypal-gateway'); ?>
                </label>
                <div id="wpg_paypal_checkout_cc-card-cvc"></div>
            </div>
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
            'visa' => _x(
                    'Visa',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'mastercard' => _x(
                    'Mastercard',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'maestro' => _x(
                    'Maestro',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'amex' => _x(
                    'American Express',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'discover' => _x(
                    'Discover',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'jcb' => _x(
                    'JCB',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'elo' => _x(
                    'Elo',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
            'hiper' => _x(
                    'Hiper',
                    'Name of credit card',
                    'woo-paypal-gateway'
            ),
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
        $token = isset($_POST['wc-wpg_paypal_checkout_cc-payment-token']) ? wc_clean($_POST['wc-wpg_paypal_checkout_cc-payment-token']) : '';
        if (!empty($token) && $token !== 'new') {
            $is_success = $this->request->wpg_ppcp_capture_order_using_payment_method_token($woo_order_id);
            unset(WC()->session->ppcp_session);
        } elseif (isset($_GET['from']) && $_GET['from'] === 'checkout') {
            ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
            $this->request->ppcp_create_order_request($woo_order_id);
            exit;
        } elseif ($paypal_order_id = ppcp_get_session('ppcp_paypal_order_id')) {
            $is_success = ($this->paymentaction === 'capture') ? $this->request->ppcp_order_capture_request($woo_order_id) : $this->request->ppcp_order_auth_request($woo_order_id);
            $order->update_meta_data('_payment_action', $this->paymentaction);
            $order->update_meta_data('enviorment', $this->sandbox ? 'sandbox' : 'live');
            $order->save_meta_data();
            unset(WC()->session->ppcp_session);
        } else {
            if (ob_get_length()) {
                ob_end_clean();
            }
            return $this->request->ppcp_regular_create_order_request($woo_order_id);
        }
        if ($is_success) {
            WC()->cart->empty_cart();
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
        if ($this->is_credentials_set() && $this->cc_enable === 'yes') {
            return true;
        }
        return false;
    }

    public function process_subscription_payment($order, $amount_to_charge) {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
            }
            $order_id = $order->get_id();
            $this->payment_request->wpg_ppcp_capture_order_using_payment_method_token($order_id);
        } catch (Exception $ex) {
            
        }
    }
}
