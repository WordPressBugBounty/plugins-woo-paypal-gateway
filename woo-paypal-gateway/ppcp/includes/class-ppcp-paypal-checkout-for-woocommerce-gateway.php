<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Gateway
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Gateway/includes
 * @author     PayPal <wpeasypayment@gmail.com>
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Gateway extends WC_Payment_Gateway_CC {

    /**
     * @since    1.0.0
     */
    public $request;
    public $settings_obj;
    public $plugin_name;
    public $sandbox;
    public $rest_client_id_sandbox;
    public $sandbox_secret_id;
    public $live_client_id;
    public $live_secret_id;
    public $client_id;
    public $secret_id;
    public $paymentaction;
    public $advanced_card_payments;
    public $threed_secure_contingency;
    public static $log = false;
    public $disable_cards;
    public $advanced_card_payments_title;
    public $cc_enable;
    static $ppcp_display_order_fee = 0;



    public function __construct() {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->get_properties();
        $this->plugin_name = 'ppcp-paypal-checkout';
        $this->title = $this->get_option('title', 'PayPal');
        $this->disable_cards = $this->get_option('disable_cards', array());
        $this->description = __('Accept PayPal, PayPal Credit and alternative payment types.', 'woo-paypal-gateway');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        if (!has_action('woocommerce_admin_order_totals_after_total', array('PPCP_Paypal_Checkout_For_Woocommerce_Gateway', 'ppcp_display_order_fee'))) {
            add_action('woocommerce_admin_order_totals_after_total', array($this, 'ppcp_display_order_fee'));
        }
        
        $this->advanced_card_payments_title = $this->get_option('advanced_card_payments_title', 'Credit or Debit Card');
        
        if (ppcp_has_active_session()) {
            $this->order_button_text = $this->get_option('order_review_page_button_text', 'Confirm your PayPal order');
        }
    }

    public function setup_properties() {
        $this->id = 'wpg_paypal_checkout';
        $this->method_title = __('PayPal Checkout', 'woo-paypal-gateway');
        $this->method_description = __('PayPal Checkout with Smart Payment Buttons gives your buyers a simplified and secure checkout experience.', 'woo-paypal-gateway');
        $this->has_fields = true;
        
    }

    public function get_properties() {
        $this->enabled = $this->get_option('enabled', 'no');
        $this->cc_enable = $this->get_option('enable_advanced_card_payments', 'no');
        $this->supports = array(
            'products',
            'refunds',
            'pay_button'
        );
        $this->pay_button_id = 'ppcp_cart';
        $this->sandbox = 'yes' === $this->get_option('sandbox', 'no');
        $this->rest_client_id_sandbox = $this->get_option('rest_client_id_sandbox', '');
        $this->sandbox_secret_id = $this->get_option('rest_secret_id_sandbox', '');
        $this->live_client_id = $this->get_option('rest_client_id_live', '');
        $this->live_secret_id = $this->get_option('rest_secret_id_live', '');
        if ($this->sandbox) {
            $this->client_id = $this->rest_client_id_sandbox;
            $this->secret_id = $this->sandbox_secret_id;
        } else {
            $this->client_id = $this->live_client_id;
            $this->secret_id = $this->live_secret_id;
        }
        if (!$this->is_credentials_set()) {
            $this->enabled = 'no';
            $this->cc_enable = 'no';
        }
        $this->paymentaction = $this->get_option('paymentaction', 'capture');
        $this->advanced_card_payments = 'yes' === $this->get_option('enable_advanced_card_payments', 'no');
        if (ppcp_is_advanced_cards_available() === false) {
            $this->advanced_card_payments = false;
        }
        $this->threed_secure_contingency = $this->get_option('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
    }

    public function payment_fields() {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }
        do_action('display_paypal_button_checkout_page');
    }

    public function is_credentials_set() {
        if (!empty($this->client_id) && !empty($this->secret_id)) {
            return true;
        } else {
            return false;
        }
    }

    public function init_form_fields() {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {
            include 'class-ppcp-paypal-checkout-for-woocommerce-settings.php';
        }
        $this->settings_obj = PPCP_Paypal_Checkout_For_Woocommerce_Settings::instance();
        $this->form_fields = $this->settings_obj->ppcp_setting_fields();
    }

    public function process_admin_options() {
        delete_transient('ppcp_sandbox_access_token');
        delete_transient('ppcp_live_access_token');
        delete_transient('ppcp_sandbox_client_token');
        delete_transient('ppcp_live_client_token');
        delete_option('ppcp_sandbox_webhook_id');
        delete_option('ppcp_live_webhook_id');
        parent::process_admin_options();
    }

    public function admin_options() {
        wp_enqueue_script('wc-clipboard');
        parent::admin_options();
    }

    public function process_payment($woo_order_id) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $this->request = new PPCP_Paypal_Checkout_For_Woocommerce_Request($this);
        $is_success = false;
        if (isset($_GET['from']) && 'checkout' === $_GET['from']) {
            ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
            $this->request->ppcp_create_order_request($woo_order_id);
            exit();
        } else {
            $ppcp_paypal_order_id = ppcp_get_session('ppcp_paypal_order_id');
            if (!empty($ppcp_paypal_order_id)) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                $this->request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
                $order = wc_get_order($woo_order_id);
                if ($this->paymentaction === 'capture') {
                    $is_success = $this->request->ppcp_order_capture_request($woo_order_id);
                } else {
                    $is_success = $this->request->ppcp_order_auth_request($woo_order_id);
                }
                $order->update_meta_data('_payment_action', $this->paymentaction);
                $order->update_meta_data('enviorment', ($this->sandbox) ? 'sandbox' : 'live');
                $order->save_meta_data();
                if ($is_success) {
                    WC()->cart->empty_cart();
                    unset(WC()->session->ppcp_session);
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                } else {
                    unset(WC()->session->ppcp_session);
                    return array(
                        'result' => 'failure',
                        'redirect' => wc_get_cart_url()
                    );
                }
            } else {
                $result = $this->request->ppcp_regular_create_order_request($woo_order_id);
                if (ob_get_length()) {
                    ob_end_clean();
                }
                return $result;
            }
        }
    }

    public function get_transaction_url($order) {
        $enviorment = $order->get_meta('enviorment');
        if ($enviorment === 'sandbox') {
            $this->view_transaction_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        } else {
            $this->view_transaction_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        }
        return parent::get_transaction_url($order);
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
        $this->request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
        $transaction_id = $order->get_transaction_id();
        $bool = $this->request->ppcp_refund_order($order_id, $amount, $reason, $transaction_id);
        return $bool;
    }

    public function ppcp_display_order_fee($order_id) {
        if(self::$ppcp_display_order_fee > 0) {
            return;
        }
        self::$ppcp_display_order_fee = 1;
        $order = wc_get_order($order_id);
        $fee = $order->get_meta('_paypal_fee');
        $payment_method = $order->get_payment_method();
        if ('wpg_paypal_checkout' !== $payment_method) {
            return false;
        }
        $currency = $order->get_meta('_paypal_fee_currency_code');
        if ($order->get_status() == 'refunded') {
            return true;
        }
        ?>
        <tr>
            <td class="label stripe-fee">
        <?php echo wc_help_tip(__('This represents the fee PayPal collects for the transaction.', 'woo-paypal-gateway')); ?>
        <?php esc_html_e('PayPal Fee:', 'woo-paypal-gateway'); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                -&nbsp;<?php echo wc_price($fee, array('currency' => $currency)); ?>
            </td>
        </tr>
        <?php
    }

    public function get_icon() {
        $icon = $this->icon ? '<img src="' . WC_HTTPS::force_https_url($this->icon) . '" alt="' . esc_attr($this->get_title()) . '" />' : '';
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function generate_wpg_paypal_checkout_text_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'wpg_paypal_checkout_text') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                   ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <button type="button" class="button ppcp-disconnect"><?php echo __('Disconnect', ''); ?></button>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_copy_text_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );
        $data = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                     ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.                                      ?> />
                    <button type="button" class="button-secondary <?php echo esc_attr($data['button_class']); ?>" data-tip="Copied!">Copy</button>
        <?php echo $this->get_description_html($data); // WPCS: XSS ok.         ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function admin_scripts() {
        if (isset($_GET['section']) && 'wpg_paypal_checkout' === $_GET['section']) {
            wp_enqueue_style('ppcp-paypal-checkout-for-woocommerce-admin', WPG_PLUGIN_ASSET_URL . 'ppcp/admin/css/ppcp-paypal-checkout-for-woocommerce-admin.css', array(), WPG_PLUGIN_VERSION, 'all');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-admin', WPG_PLUGIN_ASSET_URL . 'ppcp/admin/js/ppcp-paypal-checkout-for-woocommerce-admin.js', array('jquery'), WPG_PLUGIN_VERSION, false);
            wp_localize_script('ppcp-paypal-checkout-for-woocommerce-admin', 'ppcp_param', array(
                'woocommerce_currency' => get_woocommerce_currency(),
                'is_advanced_cards_available' => ppcp_is_advanced_cards_available() ? 'yes' : 'no',
            ));
        }
    }
}
