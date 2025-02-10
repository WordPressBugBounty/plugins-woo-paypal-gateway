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
    static $notice_shown = false;
    public $wpg_section;
    public $is_live_seller_onboarding_done;
    public $is_sandbox_seller_onboarding_done;
    public $seller_onboarding;
    public $icon;
    public $supports;
    public $live_merchant_id;
    public $sandbox_merchant_id;
    public $merchant_id;
    public $available_end_point_key;

    public function __construct() {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->get_properties();
        $this->plugin_name = 'ppcp-paypal-checkout';
        $this->title = $this->get_option('title', 'PayPal');
        $this->disable_cards = $this->get_option('disable_cards', array());
        $this->description = $this->get_option('description', __('Pay via PayPal; you can pay with your credit card if you don’t have a PayPal account', 'woo-paypal-gateway'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        if (!has_action('woocommerce_admin_order_totals_after_total', array('PPCP_Paypal_Checkout_For_Woocommerce_Gateway', 'ppcp_display_order_fee'))) {
            add_action('woocommerce_admin_order_totals_after_total', array($this, 'ppcp_display_order_fee'));
        }
        $title = __('Credit or Debit Card', 'woo-paypal-gateway');
        $this->advanced_card_payments_title = $this->get_option('advanced_card_payments_title', $title);
        if (ppcp_has_active_session()) {
            $this->order_button_text = __('Confirm your PayPal order', 'woo-paypal-gateway');
        }
        add_action('admin_notices', array($this, 'display_paypal_admin_notice'));
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'wpg_sanitized_paypal_client_secret'), 999, 1);
        add_action('wpg_ppcp_get_onboarding_status', array($this, 'wpg_ppcp_get_onboarding_status'));
    }

    public function setup_properties() {
        $this->id = 'wpg_paypal_checkout';
        $this->method_title = __('PayPal Gateway By Easy Payment', 'woo-paypal-gateway');
        $this->method_description = __('Accept payments via PayPal, advanced credit or debit cards, Google Pay, Apple Pay, Pay Later, Venmo, Bancontact, BLIK, EPS, Giropay, iDEAL, MyBank, Przelewy24, Sofort, Mercado Pago, and SEPA Direct Debit —by an official PayPal Partner.', 'woo-paypal-gateway');
        $this->has_fields = true;
        $this->icon = 'https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png';
    }

    public function get_properties() {
        include_once ( WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-seller-onboarding.php');
        $this->seller_onboarding = PPCP_Paypal_Checkout_For_Woocommerce_Seller_Onboarding::instance();
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->cc_enable = $this->get_option('enable_advanced_card_payments', 'no');
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
        $this->sandbox = 'yes' === $this->get_option('sandbox', 'no');
        $this->rest_client_id_sandbox = $this->get_option('rest_client_id_sandbox', '');
        $this->sandbox_secret_id = $this->get_option('rest_secret_id_sandbox', '');
        $this->live_client_id = $this->get_option('rest_client_id_live', '');
        $this->live_secret_id = $this->get_option('rest_secret_id_live', '');
        $this->sandbox_merchant_id = $this->get_option('sandbox_merchant_id', '');
        $this->live_merchant_id = $this->get_option('live_merchant_id', '');
        if ($this->sandbox) {
            $this->client_id = $this->rest_client_id_sandbox;
            $this->secret_id = $this->sandbox_secret_id;
            $this->merchant_id = $this->sandbox_merchant_id;
            $this->available_end_point_key = 'wpg_ppcp_sandbox_onboarding_status';
        } else {
            $this->client_id = $this->live_client_id;
            $this->secret_id = $this->live_secret_id;
            $this->merchant_id = $this->live_merchant_id;
            $this->available_end_point_key = 'wpg_ppcp_live_onboarding_status';
        }
        if (!empty($this->rest_client_id_sandbox) && !empty($this->sandbox_secret_id)) {
            $this->is_sandbox_seller_onboarding_done = true;
        } else {
            $this->is_sandbox_seller_onboarding_done = false;
        }
        if (!empty($this->live_client_id) && !empty($this->live_secret_id)) {
            $this->is_live_seller_onboarding_done = true;
        } else {
            $this->is_live_seller_onboarding_done = false;
        }
        if (!$this->is_credentials_set()) {
            $this->enabled = 'no';
            $this->cc_enable = 'no';
        }
        $this->paymentaction = $this->get_option('paymentaction', 'capture');
        $this->advanced_card_payments = 'yes' === $this->get_option('enable_advanced_card_payments', 'no');
        $this->threed_secure_contingency = $this->get_option('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
        $this->wpg_section = isset($_GET['wpg_section']) ? sanitize_text_field($_GET['wpg_section']) : 'wpg_api_settings';
    }

    public function wpg_ppcp_get_onboarding_status() {
        if (!empty($this->merchant_id) && $this->is_credentials_set() && get_transient($this->available_end_point_key) === false) {
            set_transient($this->available_end_point_key, false, MINUTE_IN_SECONDS);
            $availableEndpoints = array();
            $result = $this->seller_onboarding->wpg_track_seller_onboarding_status($this->merchant_id, $this->sandbox);
            if (!empty($result['products'])) {
                delete_transient('wpg_ppcp_live_onboarding_status');
                delete_transient('wpg_ppcp_sandbox_onboarding_status');
                if (wpg_is_acdc_approved($result)) {
                    $availableEndpoints['advanced_cc'] = 'SUBSCRIBED';
                    $this->update_option('enable_advanced_card_payments', 'yes');
                } else {
                    $this->update_option('enable_advanced_card_payments', 'no');
                }
                if (wpg_is_google_pay_approved($result)) {
                    $this->update_option('enabled_google_pay', 'yes');
                    $availableEndpoints['google_pay'] = 'SUBSCRIBED';
                } else {
                    $this->update_option('enabled_google_pay', 'no');
                }
                if (wpg_is_apple_pay_approved($result)) {
                    $availableEndpoints['apple_pay'] = 'SUBSCRIBED';
                    //if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
                    // include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                    // }
                    //$this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
                    //$this->request->wpg_register_apple_domain();
                } else {
                    $this->update_option('enabled_apple_pay', 'no');
                }

                set_transient($this->available_end_point_key, $availableEndpoints, DAY_IN_SECONDS);
            }
        }
    }

    public function wpg_is_end_point_enable($end_point) {
        if (!empty($this->merchant_id) && $this->is_credentials_set() && get_transient($this->available_end_point_key) !== false) {
            $available_end_point = get_transient($this->available_end_point_key);
            if (isset($available_end_point[$end_point]) && $available_end_point[$end_point] === 'SUBSCRIBED') {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public function display_paypal_admin_notice() {
        $is_saller_onboarding_done = false;
        $is_saller_onboarding_failed = false;
        if (false !== get_transient('wpg_primary_email_not_confirmed')) {
            echo '<div class="notice notice-error is-dismissible"><p>'
            . __('Please verify the PayPal account to receive the payments.', 'woo-paypal-gateway')
            . '</p></div>';
        }
        if (false !== get_transient('wpg_sandbox_seller_onboarding_process_done')) {
            $is_saller_onboarding_done = true;
            delete_transient('wpg_sandbox_seller_onboarding_process_done');
        } elseif (false !== get_transient('wpg_live_seller_onboarding_process_done')) {
            $is_saller_onboarding_done = true;
            delete_transient('wpg_live_seller_onboarding_process_done');
        }
        if ($is_saller_onboarding_done) {
            echo '<div class="notice notice-success is-dismissible"><p>'
            . __('PayPal onboarding process successfully completed.', 'woo-paypal-gateway')
            . '</p></div>';
        } else {
            if (false !== get_transient('wpg_sandbox_seller_onboarding_process_failed')) {
                $is_saller_onboarding_failed = true;
                delete_transient('wpg_sandbox_seller_onboarding_process_failed');
            } elseif (false !== get_transient('wpg_live_seller_onboarding_process_failed')) {
                $is_saller_onboarding_failed = true;
                delete_transient('wpg_live_seller_onboarding_process_failed');
            }
            if ($is_saller_onboarding_failed) {
                echo '<div class="notice notice-error is-dismissible">'
                . '<p>We could not properly connect to PayPal. Please reload the page to continue.</p>'
                . '</div>';
            }
        }
        $error_message = get_transient('wpg_invalid_client_secret_message');
        if ($error_message) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . esc_html($error_message) . '</p>';
            echo '</div>';
            delete_transient('wpg_invalid_client_secret_message');
        }
        if (self::$notice_shown) {
            return;
        }
        if (!$this->is_credentials_set()) {
            if (isset($_GET['wpg_section'])) {
                $wpg_section = sanitize_text_field($_GET['wpg_section']);
            } else {
                $wpg_section = (isset($_GET['section']) && $_GET['section'] === 'wpg_paypal_checkout') ? 'wpg_api_settings' : '';
            }
            if ($wpg_section !== 'wpg_api_settings') {
                if (is_existing_classic_user() === false) {
                    $message = sprintf(
                            __('<strong>PayPal Setup Required:</strong> Your PayPal integration is almost complete! To start accepting payments, simply connect your PayPal account or enter your PayPal Client ID and Secret Key in the <a href="%1$s">API Settings</a>.', 'woo-paypal-gateway'),
                            admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout')
                    );

                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p>' . $message . '</p>';
                    echo '</div>';
                }
            }
        }
        self::$notice_shown = true;
    }

    public function payment_fields() {
        $description = $this->get_description();
        if ($description && is_ppcp_is_terms_page_published()) {
            echo wpautop(wptexturize($description));
        }
        if (is_wpg_change_payment_method() === false) {
            do_action('display_paypal_button_checkout_page');
        }
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
        $reset_tokens = false;
        if (isset($_GET['wpg_section']) && 'wpg_api_settings' === $_GET['wpg_section']) {
            $reset_tokens = true;
        } elseif (isset($_GET['section']) && 'wpg_paypal_checkout' === $_GET['section'] && !isset($_GET['wpg_section'])) {
            $reset_tokens = true;
        }
        if ($reset_tokens) {
            $this->reset_paypal_tokens_and_options();
        }
        parent::process_admin_options();
        if ($this->wpg_is_end_point_enable('apple_pay')) {
            wpg_manage_apple_domain_file($this->sandbox);
        }
    }

    private function reset_paypal_tokens_and_options() {
        $transients = [
            'ppcp_sandbox_access_token',
            'ppcp_access_token',
            'ppcp_sandbox_client_token',
            'ppcp_live_client_token',
            'wpg_ppcp_live_onboarding_status',
            'wpg_ppcp_sandbox_onboarding_status'
        ];
        $options = [
            'ppcp_sandbox_webhook_id',
            'ppcp_live_webhook_id'
        ];
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        foreach ($options as $option) {
            delete_option($option);
        }
    }

    public function admin_options() {
        do_action('wpg_ppcp_get_onboarding_status');
        wp_enqueue_script('wc-clipboard');
        echo '<h2>' . __('PayPal Settings', 'woo-paypal-gateway');
        wc_back_link(__('Return to payments', 'woo-paypal-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        echo '</h2>';

        $this->output_tabs($this->wpg_section);
        $this->admin_option();
        if ($this->wpg_section === 'wpg_api_settings' && !$this->is_credentials_set()) {
            echo '<br/>';
            echo '<div id="wpg_guide" style="background: #f9f9f9;border-spacing: 2px; border-color: gray; padding: 20px; margin-bottom: 20px;max-width:858px;display:none;">
                 <h4 style="margin: 0 0 15px; font-size: 14px; font-weight: bold; display: flex; align-items: center;">
                    <span style="font-size: 20px; margin-right: 8px;"></span> Here\'s how to get your client ID and client secret:
                 </h4>
                <ol style="margin: 10px 0 0 20px; padding: 0; font-size: 14px; line-height: 1.8; color: #333;">
                    <li>Select <a href="https://developer.paypal.com/dashboard/" target="_blank" style="color: #007cba; text-decoration: none;">Log in to Dashboard</a> and log in or sign up.</li>
<li>Select <strong>Apps & Credentials</strong>.</li>
<li>New accounts come with a <strong>Default Application</strong> in the <strong>REST API apps</strong> section. To create a new project, select <strong>Create App</strong>.</li>
<li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> for your app.</li>
<li>Paste them into the fields on this page and click <strong>Save Changes</strong>.</li>
                </ol>
            </div>';
        }
    }

    public function output_tabs($current_tab) {
        $tabs = array(
            'wpg_api_settings' => __('API Settings', 'woo-paypal-gateway'),
            'wpg_paypal_checkout' => __('PayPal Settings', 'woo-paypal-gateway'),
            'wpg_advanced_cc' => __('Advanced Card Payments', 'woo-paypal-gateway'),
            'wpg_google_pay' => __('Google Pay', 'woo-paypal-gateway'),
            'wpg_apple_pay' => __('Apple Pay', 'woo-paypal-gateway'),
            'wpg_ppcp_paylater' => __('Pay Later Messaging', 'woo-paypal-gateway'),
            'wpg_advanced_settings' => __('Additional Settings', 'woo-paypal-gateway'),
            'wpg_foq' => __('FAQ', 'woo-paypal-gateway'),
        );
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active_class = ($key === $current_tab) ? 'nav-tab-active' : '';
            $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id . '&wpg_section=' . $key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($active_class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
    }

    public function admin_option() {
        echo '<table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>'; // WPCS: XSS ok.
    }

    public function get_form_fields() {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {
            include 'class-ppcp-paypal-checkout-for-woocommerce-settings.php';
        }
        $this->settings_obj = PPCP_Paypal_Checkout_For_Woocommerce_Settings::instance();
        if ($this->wpg_section === 'wpg_api_settings') {
            $default_api_settings = $this->settings_obj->default_api_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $default_api_settings));
        } elseif ($this->wpg_section === 'wpg_paypal_checkout') {
            $wpg_paypal_checkout_settings = $this->settings_obj->wpg_paypal_checkout_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_paypal_checkout_settings));
        } elseif ($this->wpg_section === 'wpg_advanced_cc') {
            if ($this->wpg_is_end_point_enable('advanced_cc') === true) {
                $wpg_advanced_cc_settings = $this->settings_obj->wpg_advanced_cc_settings();
                return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_cc_settings));
            } else {
                $wpg_advanced_cc_onboard_settings = $this->settings_obj->wpg_advanced_cc_onboard_settings();
                return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_cc_onboard_settings));
            }
        } elseif ($this->wpg_section === 'wpg_ppcp_paylater') {
            $wpg_ppcp_paylater_settings = $this->settings_obj->wpg_ppcp_paylater_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_ppcp_paylater_settings));
        } elseif ($this->wpg_section === 'wpg_advanced_settings') {
            $wpg_advanced_settings = $this->settings_obj->wpg_advanced_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_settings));
        } elseif ($this->wpg_section === 'wpg_google_pay') {
            if ($this->wpg_is_end_point_enable('google_pay') === true) {
                $wpg_google_pay_settings = $this->settings_obj->wpg_ppcp_google_pay_settings();
                return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_google_pay_settings));
            } else {
                $this->update_option('enabled_google_pay', 'no');
                $wpg_google_pay_onboard_settings = $this->settings_obj->wpg_google_pay_onboard_settings();
                return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_google_pay_onboard_settings));
            }
        } elseif ($this->wpg_section === 'wpg_foq') {
            $wpg_foq = $this->settings_obj->wpg_foq();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_foq));
        } elseif ($this->wpg_section === 'wpg_apple_pay') {
            if ($this->wpg_is_end_point_enable('apple_pay') === true) {
                $wpg_apple_pay_settings = $this->settings_obj->wpg_ppcp_apple_pay_settings();
                return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_apple_pay_settings));
            } else {
                $this->update_option('enabled_apple_pay', 'no');
                $wpg_apple_pay_onboard_settings = $this->settings_obj->wpg_apple_pay_onboard_settings();
                return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_apple_pay_onboard_settings));
            }
        } else {
            $this->form_fields = $this->settings_obj->ppcp_setting_fields();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $this->form_fields));
        }
    }

    public function process_payment($woo_order_id) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $is_success = false;
        if (isset($_GET['from']) && 'checkout' === $_GET['from']) {
            ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
            $this->request->ppcp_create_order_request($woo_order_id);
            exit();
        } else {
            $ppcp_paypal_order_id = ppcp_get_session('ppcp_paypal_order_id');
            if (!empty($ppcp_paypal_order_id)) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
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
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $transaction_id = $order->get_transaction_id();
        $bool = $this->request->ppcp_refund_order($order_id, $amount, $reason, $transaction_id);
        return $bool;
    }

    public function ppcp_display_order_fee($order_id) {
        if (self::$ppcp_display_order_fee > 0) {
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
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <button type="button" class="button ppcp-disconnect"><?php echo __('Disconnect', 'woo-paypal-gateway'); ?></button>
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
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                  ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.                                                                                                                                      ?> />
                    <button type="button" class="button-secondary <?php echo esc_attr($data['button_class']); ?>" data-tip="Copied!">Copy</button>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.                           ?>
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
                'mode' => $this->sandbox ? 'sandbox' : 'live',
                'is_sandbox_connected' => (!empty($this->rest_client_id_sandbox) && !empty($this->sandbox_secret_id)) ? 'yes' : 'no',
                'is_live_connected' => (!empty($this->live_client_id) && !empty($this->live_secret_id)) ? 'yes' : 'no',
                'wpg_onboarding_endpoint' => WC_AJAX::get_endpoint('wpg_login_seller'),
                'wpg_onboarding_endpoint_nonce' => wp_create_nonce('wpg_login_seller'),
            ));
        }
    }

    public function generate_wpg_ppcp_text_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'wpg_ppcp_text') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top" style="display:none;">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                 ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <div class="wpg_ppcp_paypal_connection_image">
                        <div class="wpg_ppcp_paypal_connection_image_status">
                            <img src="<?php echo WPG_PLUGIN_ASSET_URL . 'assets/images/mark.png'; ?>" width="32" height="32">
                        </div>
                    </div>
                    <div class="wpg_ppcp_paypal_connection">
                        <div class="wpg_ppcp_paypal_connection_status">
                            <h3><?php echo __('PayPal Account Successfully Connected!', 'woo-paypal-gateway'); ?></h3>
                        </div>
                    </div>
                    <button type="button" class="button wpg-ppcp-disconnect"><?php echo __('Change PayPal Account', 'woo-paypal-gateway'); ?></button>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_text_html($key, $data) {
        if (isset($data['gateway']) && $data['gateway'] === 'wpg') {
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
            <tr valign="top" style="display:none;">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                            ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.                                                                              ?> />
                        <?php echo $this->get_description_html($data); // WPCS: XSS ok.             ?>
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        } else {
            return parent::generate_text_html($key, $data);
        }
    }

    public function wpg_sanitized_paypal_client_secret($settings) {
        if ($this->wpg_section === 'wpg_api_settings') {
            $is_sandbox = isset($settings['sandbox']) && $settings['sandbox'] === 'yes';
            $environment = $is_sandbox ? 'sandbox' : 'live';
            $client_id_key = "rest_client_id_{$environment}";
            $secret_id_key = "rest_secret_id_{$environment}";
            $client_id = isset($settings[$client_id_key]) ? sanitize_text_field($settings[$client_id_key]) : '';
            $secret_id = isset($settings[$secret_id_key]) ? sanitize_text_field($settings[$secret_id_key]) : '';
            if (!empty($client_id) && !empty($secret_id)) {
                $paypal_oauth_api = $is_sandbox ? 'https://api.sandbox.paypal.com/v1/oauth2/token/' : 'https://api.paypal.com/v1/oauth2/token/';
                $basicAuth = base64_encode("{$client_id}:{$secret_id}");
                if (!$this->wpg_validate_paypal_client_secret($is_sandbox, $paypal_oauth_api, $basicAuth)) {
                    $error_message = __('The PayPal Client ID and Secret key you entered are invalid. Ensure you are using the correct credentials for the selected environment (Sandbox or Live).', 'woo-paypal-gateway');
                    set_transient('wpg_invalid_client_secret_message', $error_message, 5000);
                    $settings[$client_id_key] = '';
                    $settings[$secret_id_key] = '';
                    ob_get_clean();
                    wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_api_settings'));
                    exit;
                }
            }
        }
        return $settings;
    }

    public function wpg_validate_paypal_client_secret($is_sandbox, $paypal_oauth_api, $basicAuth) {
        try {
            $headers = [
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . $basicAuth,
                'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
            ];
            $body = ['grant_type' => 'client_credentials'];
            $response = wp_remote_post($paypal_oauth_api, [
                'method' => 'POST',
                'timeout' => 60,
                'headers' => $headers,
                'body' => $body,
            ]);
            if (is_wp_error($response)) {
                return false;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($api_response['access_token'])) {
                return $api_response['access_token'];
            }
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function generate_wpg_paypal_checkout_onboarding_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'wpg_paypal_checkout_onboarding') {
            if (!empty($_GET['merchantIdInPayPal'])) {
                return;
            }
            $testmode = ( $data['mode'] === 'live' ) ? 'no' : 'yes';
            if ($testmode === 'yes' && $this->is_sandbox_seller_onboarding_done) {
                return;
            }
            if ($testmode === 'no' && $this->is_live_seller_onboarding_done) {
                return;
            }
            $field_key = $this->get_field_key($field_key);

            $args = array(
                'displayMode' => 'minibrowser',
            );
            $id = ($testmode === 'no') ? 'connect-to-production' : 'connect-to-sandbox';
            $label = ($testmode === 'no') ? __('Click to Connect PayPal', 'woo-paypal-gateway') : __('Click to Connect PayPal Sandbox', 'woo-paypal-gateway');
            ob_start();
            ?>
            <tr valign="top" style="display:none;">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                           ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    if ($this->is_live_seller_onboarding_done === false && $testmode === 'no' || $this->is_sandbox_seller_onboarding_done === false && $testmode === 'yes') {
                        $signup_link = $this->wpg_get_signup_link($testmode);
                        if ($signup_link) {
                            $url = add_query_arg($args, $signup_link);
                            $this->wpg_display_paypal_signup_button($url, $id, $label);
                            $script_url = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
                            ?>
                            <script type="text/javascript">
                                document.querySelectorAll('[data-paypal-onboard-complete=onboardingCallback]').forEach((element) => {
                                    element.addEventListener('click', (e) => {
                                        if ('undefined' === typeof PAYPAL) {
                                            e.preventDefault();
                                            alert('PayPal');
                                        }
                                    });
                                });</script>
                            <script id="paypal-js" src="<?php echo esc_url($script_url); ?>"></script> <?php
                        } else {
                            echo '<div style="display: inline;margin-right: 10px;vertical-align: middle;">' . __('The Connect to PayPal service is temporarily unavailable.', 'woo-paypal-gateway') . '</div>';
                            ?>
                            <a href="#" class="wpg_paypal_checkout_gateway_manual_credential_input"><?php echo __('Toggle to manual credential input', 'woo-paypal-gateway'); ?></a>
                            <?php
                        }
                    }
                    ?>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function wpg_display_paypal_signup_button($url, $id, $label) {
        ?><a target="_blank" class="button" id="<?php echo esc_attr($id); ?>" data-paypal-onboard-complete="onboardingCallback" href="<?php echo esc_url($url); ?>" data-paypal-button="true"><?php echo esc_html($label); ?></a>
        <span class="wpg_paypal_checkout_gateway_setting_sepraer"><?php echo __('OR', 'woo-paypal-gateway'); ?></span>
        <a href="#" class="wpg_paypal_checkout_gateway_manual_credential_input"><?php echo __('Toggle to manual credential input', 'woo-paypal-gateway'); ?></a>
        <?php
    }

    public function wpg_get_signup_link($testmode = 'yes') {
        try {

            $seller_onboarding_result = $this->seller_onboarding->wpg_generate_signup_link($testmode);
            if (isset($seller_onboarding_result['result']) && 'success' === $seller_onboarding_result['result'] && !empty($seller_onboarding_result['body'])) {
                $json = json_decode($seller_onboarding_result['body']);
                if (isset($json->links)) {
                    foreach ($json->links as $link) {
                        if ('action_url' === $link->rel) {
                            return (string) $link->href;
                        }
                    }
                } else {
                    return false;
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function wpg_get_signup_link_for_google_pay($testmode = 'yes') {
        try {

            $seller_onboarding_result = $this->seller_onboarding->wpg_generate_signup_link_for_google_pay($testmode);
            if (isset($seller_onboarding_result['result']) && 'success' === $seller_onboarding_result['result'] && !empty($seller_onboarding_result['body'])) {
                $json = json_decode($seller_onboarding_result['body']);
                if (isset($json->links)) {
                    foreach ($json->links as $link) {
                        if ('action_url' === $link->rel) {
                            return (string) $link->href;
                        }
                    }
                } else {
                    return false;
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function wpg_get_signup_link_for_apple_pay($testmode = 'yes') {
        try {

            $seller_onboarding_result = $this->seller_onboarding->wpg_generate_signup_link_for_apple_pay($testmode);
            if (isset($seller_onboarding_result['result']) && 'success' === $seller_onboarding_result['result'] && !empty($seller_onboarding_result['body'])) {
                $json = json_decode($seller_onboarding_result['body']);
                if (isset($json->links)) {
                    foreach ($json->links as $link) {
                        if ('action_url' === $link->rel) {
                            return (string) $link->href;
                        }
                    }
                } else {
                    return false;
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function process_subscription_payment($order, $amount_to_charge) {
        try {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
            $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
            $order_id = $order->get_id();
            $this->request->wpg_ppcp_capture_order_using_payment_method_token($order_id);
        } catch (Exception $ex) {
            
        }
    }

    public function subscription_change_payment($order_id) {
        include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        return $this->request->ppcp_paypal_setup_tokens_sub_change_payment($order_id);
    }

    public function generate_paypal_button_preview_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'paypal_button_preview') {
            ob_start();
            ?>
            <tr valign="top">
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <div class="ppcp-preview ppcp-button-preview" >
                        <h4>Button Styling Preview</h4>
                        <div id="ppcpCheckoutButtonPreview" class="ppcp-button-preview-inner"></div>
                    </div>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_foq_html_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'foq_html') {
            ob_start();
            ?>
            <tr valign="top">
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <div class="wpg_foq">
                        <div class="faq-item">
                            <div class="faq-question" aria-expanded="false">
                                <?php echo __('How can I show only the "PayPal" button on the Checkout page?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('To display only the "PayPal" button on the Checkout page, follow these steps:', 'woo-paypal-gateway'); ?></p>
                                <h4><?php echo __('Step 1: Update PayPal Settings', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>PayPal Settings > Checkout Page</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Under <strong>Hide Funding Method(s)</strong>, select <strong>Credit or Debit Card</strong> and any other methods you want to hide.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                                <h4><?php echo __('Step 2: Update Advanced Card Payments Settings', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>Advanced Card Payments</strong> settings.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Disable the option <strong>Enable Advanced Credit/Debit Card in the Payment Gateway List on the Checkout Page</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                                <p><?php echo __('Once you save the changes in both sections, only the "PayPal" button will appear on the Checkout page.', 'woo-paypal-gateway'); ?></p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-question" aria-expanded="false">
                                <?php echo __('How can I show only the "Credit or Debit Card" payment method on the Checkout page?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('To display only the "Credit or Debit Card" payment method on the Checkout page, follow these steps:', 'woo-paypal-gateway'); ?></p>
                                <h4><?php echo __('Step 1: Update PayPal Settings', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>PayPal Settings > Checkout Page Settings</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Disable the <strong>Enable PayPal</strong> option.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                                <h4><?php echo __('Step 2: Enable Advanced Credit/Debit Card Payments', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>Advanced Card Payments</strong> settings.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Enable the option <strong>Enable Advanced Credit/Debit Card in the Payment Gateway List on the Checkout Page</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                                <p><?php echo __('Once you save the changes in both sections, only the "Credit or Debit Card" payment method will appear on the Checkout page.', 'woo-paypal-gateway'); ?></p>
                            </div>
                        </div>
                        <div class="faq-item faq-highlight">
                            <div class="faq-question" aria-expanded="false">
                                <?php echo __('How can I contact support or request a new functionality?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('If you have any questions, encounter an issue, or have a new functionality request, please create a support ticket on our official support page at: ', 'woo-paypal-gateway'); ?>
                                    <a href="https://wordpress.org/support/plugin/woo-paypal-gateway/" target="_blank">https://wordpress.org/support/plugin/woo-paypal-gateway/</a>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <style>
                        .wpg_foq {
                            margin: 20px 0;


                        }
                        .wpg_foq h1 {
                            text-align: center;

                            margin-bottom: 30px;

                            font-weight: bold;
                        }
                        .faq-item {

                            border: 1px solid #ddd;

                            margin-bottom: 10px;

                            overflow: hidden;
                            transition: all 0.3s ease;
                        }

                        .faq-question {

                            padding: 15px 20px;
                            cursor: pointer;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            border-left: 3px solid transparent;
                            font-weight:600;
                            color:#1d2327;
                        }
                        .faq-question strong {

                            font-size: var(--font-size-heading);
                        }
                        .faq-toggle {
                            font-size: 20px;

                            font-weight: bold;
                            transition: transform 0.3s ease;
                        }
                        .faq-answer {
                            display: none;
                            padding: 15px 20px;
                            color:#3c434a;

                            border-top: 1px solid #ddd;
                        }
                        .faq-answer code {

                            border: 1px solid #ddd;

                            padding: 2px 4px;

                            font-family: "Courier New", Courier, monospace;

                        }
                        .faq-item.active .faq-question {


                        }
                        .faq-item.active .faq-answer {
                            display: block;
                        }
                        .faq-toggle {
                            display: inline-block;
                            width: 20px;
                            height: 20px;
                            background: url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" aria-hidden="true" focusable="false"><path d="M480-345 240-585l56-56 184 184 184-184 56 56-240 240Z"/></svg>') no-repeat center;
                            background-size: contain;
                            transition: transform 0.3s ease;
                            transform: rotate(0deg); /* Default position for closed */
                        }
                        .faq-item.active .faq-toggle {
                            transform: rotate(180deg); /* Arrow points down when expanded */
                        }

                    </style>
                    <script>
                                document.addEventListener("DOMContentLoaded", () => {
                                    const faqItems = document.querySelectorAll(".faq-item");
                                    faqItems.forEach(item => {
                                        const question = item.querySelector(".faq-question");
                                        question.addEventListener("click", () => {
                                            const isExpanded = item.classList.toggle("active");
                                            question.setAttribute("aria-expanded", isExpanded ? "true" : "false");
                                            faqItems.forEach(otherItem => {
                                                if (otherItem !== item) {
                                                    otherItem.classList.remove("active");
                                                    otherItem.querySelector(".faq-question").setAttribute("aria-expanded", "false");
                                                }
                                            });
                                        });
                                    });
                                });
                    </script>

                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_google_pay_onboard_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'google_pay_onboard') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                 ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    $args = array(
                        'displayMode' => 'minibrowser',
                    );
                    $id = 'connect-to-google';
                    $label = __('Click to Enable Google Pay', 'woo-paypal-gateway');
                    $testmode = $this->sandbox ? 'yes' : 'no';
                    $signup_link = $this->wpg_get_signup_link_for_google_pay($testmode);
                    if ($signup_link) {
                        $url = add_query_arg($args, $signup_link);
                        ?><a target="_blank" class="button" id="<?php echo esc_attr($id); ?>"  href="<?php echo esc_url($url); ?>" ><?php echo esc_html($label); ?></a><?php
                    }
                    ?>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_gpay_title_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'gpay_title') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <style>
                .ppcp-google-pay-notice-box {
                    padding: 12px 16px;
                    line-height: 1.5;
                    background-color: #fff;
                    color: #1e1e1e;
                    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.04), 0px 0px 0px 1px rgba(0, 0, 0, 0.1);
                    max-width: 864px;
                    border-left: 4px solid #ffb900;
                    font-size: 14px;
                    margin-top: 15px;
                    margin-bottom: 15px;
                }
            </style>

            <div class="ppcp-google-pay-notice-box">
                <?php echo '<strong>' . __('Important: ', 'woo-paypal-gateway') . '</strong>'; ?>
                <?php echo $data['description']; ?>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_apple_title_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'apple_title') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <style>
                .ppcp-apple-title-notice-box {
                    padding: 12px 16px;
                    line-height: 1.5;
                    background-color: #fff;
                    color: #1e1e1e;
                    box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.04), 0px 0px 0px 1px rgba(0, 0, 0, 0.1);
                    max-width: 864px;
                    border-left: 4px solid #ffb900;
                    font-size: 14px;
                    margin-top: 15px;
                    margin-bottom: 15px;
                }
            </style>
            <div class="ppcp-apple-title-notice-box">
                <?php echo '<strong>' . __('Important: ', 'woo-paypal-gateway') . '</strong>'; ?>
                <?php echo $data['description']; ?>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_apple_pay_onboard_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'apple_pay_onboard') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                 ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    $args = array(
                        'displayMode' => 'minibrowser',
                    );
                    $id = 'connect-to-google';
                    $label = __('Click to Enable Apple Pay', 'woo-paypal-gateway');
                    $testmode = $this->sandbox ? 'yes' : 'no';
                    $signup_link = $this->wpg_get_signup_link_for_apple_pay($testmode);
                    if ($signup_link) {
                        $url = add_query_arg($args, $signup_link);
                        ?><a target="_blank" class="button" id="<?php echo esc_attr($id); ?>"  href="<?php echo esc_url($url); ?>" ><?php echo esc_html($label); ?></a><?php
                    }
                    ?>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_apple_pay_domain_register_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'apple_pay_domain_register') {
            // Get the field key
            $field_key = $this->get_field_key($field_key);

            // Start output buffering
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>">
                        <?php echo wp_kses_post($data['title']); ?>
                        <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok. ?>
                    </label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    // Determine the URL for PayPal Apple Pay based on the environment
                    $paypal_apple_pay_url = $this->sandbox ? 'https://www.sandbox.paypal.com/uccservicing/apm/applepay' : 'https://www.paypal.com/uccservicing/apm/applepay';
                    ?>
                    <a href="<?php echo esc_url($paypal_apple_pay_url); ?>" class="button" target="_blank">
                        <?php echo __('Manage Domain Registration', 'woo-paypal-gateway'); ?>
                    </a>
                    <?php
                    echo '<p class="description">'
                    . __('Any (sub)domain displaying an Apple Pay button must be registered on the PayPal website. If the domain is not registered, the payment method will not work.', 'woo-paypal-gateway')
                    . '</p>';
                    ?>
                </td>
            </tr>
            <?php
            // Return the buffered output
            return ob_get_clean();
        }
    }
}
