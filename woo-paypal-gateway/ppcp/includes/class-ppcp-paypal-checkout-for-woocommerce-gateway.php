<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Gateway
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Gateway/includes
 * @author     easypayment
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
    public $use_place_order;
    public $redirect_icon;
    public $icon_type;
    public $show_redirect_icon;

    public function __construct() {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        if (class_exists('WooCommerce')) {
            $this->maybe_migrate_or_initialize_paypal_button_pages_setting();
            $this->maybe_migrate_or_initialize_description_icon_type_redirect_icon();
        }
        $this->get_properties();

        $this->plugin_name = 'ppcp-paypal-checkout';
        $this->title = $this->get_option('title', 'PayPal');
        $this->disable_cards = $this->get_option('disable_cards', array());
        $this->description = $this->get_option('description', '');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        if (!has_action('woocommerce_admin_order_totals_after_total', array('PPCP_Paypal_Checkout_For_Woocommerce_Gateway', 'ppcp_display_order_fee'))) {
            add_action('woocommerce_admin_order_totals_after_total', array($this, 'ppcp_display_order_fee'));
        }
        $title = __('Credit or Debit Card', 'woo-paypal-gateway');
        $this->advanced_card_payments_title = $this->get_option('advanced_card_payments_title', $title);
        if (ppcp_has_active_session()) {
            $this->order_button_text = __('Confirm your PayPal order', 'woo-paypal-gateway');
        } else {
            $this->order_button_text = __('Proceed to PayPal', 'woo-paypal-gateway');
        }
        add_action('admin_notices', array($this, 'display_paypal_admin_notice'));
        if (!has_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'wpg_sanitized_paypal_client_secret'])) {
            add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, [$this, 'wpg_sanitized_paypal_client_secret'], 999, 1);
        }
        add_action('woocommerce_after_settings_checkout', array($this, 'wpg_ppcp_display_other_plugin'));
    }

    public function setup_properties() {
        $this->id = 'wpg_paypal_checkout';
        $this->method_title = __('PayPal Gateway By Easy Payment', 'woo-paypal-gateway');
        $this->method_description = __('PayPal, Pay Later, Venmo, Credit & Debit Cards, Google Pay, Apple Pay — and many more.', 'woo-paypal-gateway');
        $this->has_fields = true;
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
            //$this->enabled = 'no';
            //$this->cc_enable = 'no';
        }
        $this->paymentaction = $this->get_option('paymentaction', 'capture');
        $this->advanced_card_payments = 'yes' === $this->get_option('enable_advanced_card_payments', 'no');
        $this->threed_secure_contingency = $this->get_option('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
        $this->use_place_order = 'yes' === $this->get_option('use_place_order', 'no');
        $this->show_redirect_icon = 'yes' === $this->get_option('show_redirect_icon', 'yes');
        $this->icon_type = $this->get_option('icon_type', 'monogram');
        switch ($this->icon_type) {
            case 'wordmark':
                $this->icon = WPG_PLUGIN_ASSET_URL . 'assets/images/paypal-wordmark.svg';
                break;
            case 'combination':
                $this->icon = WPG_PLUGIN_ASSET_URL . 'assets/images/paypal-combination.svg';
                break;
            case 'monogram':
            default:
                $this->icon = WPG_PLUGIN_ASSET_URL . 'assets/images/paypal-monogram.svg';
                break;
        }
        $this->redirect_icon = WPG_PLUGIN_ASSET_URL . 'assets/images/wpg-popup.svg';
        $this->wpg_ppcp_get_onboarding_status();
        if (isset($_GET['section']) && $_GET['section'] === 'wpg_paypal_checkout') {
            $this->wpg_section = isset($_GET['wpg_section']) ? sanitize_text_field($_GET['wpg_section']) : 'wpg_api_settings';
        }
    }

    protected function maybe_migrate_or_initialize_description_icon_type_redirect_icon() {
        if (get_option('_new_version_description_icon_type_redirect_icon_applied', false)) {
            return;
        }
        $option_key = $this->plugin_id . $this->id . '_settings';
        $raw_settings = get_option($option_key, array());
        $activation_time = get_option('wpg_activation_time', time());
        $is_new_user = (!$activation_time) || (time() - intval($activation_time) <= 10 * 60);
        if ($is_new_user) {
            $raw_settings['description'] = '';
            $raw_settings['icon_type'] = 'monogram';
            $raw_settings['show_redirect_icon'] = 'yes';
        } else {
            $raw_settings['icon_type'] = 'combination';
            $raw_settings['show_redirect_icon'] = 'no';
        }
        update_option($option_key, $raw_settings);
        update_option('_new_version_description_icon_type_redirect_icon_applied', 'yes');
        $this->settings = $raw_settings;
    }

    protected function maybe_migrate_or_initialize_paypal_button_pages_setting() {
        if (get_option('_wpg_button_pages_migrated', false)) {
            return;
        }
        $option_key = $this->plugin_id . $this->id . '_settings';
        $raw_settings = get_option($option_key, array());
        $selected_pages = array();
        $is_fresh_install = (
                !isset($raw_settings['show_on_product_page']) &&
                !isset($raw_settings['show_on_cart']) &&
                !isset($raw_settings['show_on_mini_cart']) &&
                !isset($raw_settings['enable_checkout_button_top'])
                );

        if ($is_fresh_install) {
            $selected_pages = array('express_checkout', 'checkout', 'mini_cart');
        } else {
            if (isset($raw_settings['show_on_product_page']) && $raw_settings['show_on_product_page'] === 'yes') {
                $selected_pages[] = 'product';
            }
            if (isset($raw_settings['show_on_cart']) && $raw_settings['show_on_cart'] === 'yes') {
                $selected_pages[] = 'cart';
            }
            if (isset($raw_settings['enable_checkout_button_top']) && $raw_settings['enable_checkout_button_top'] === 'yes') {
                $selected_pages[] = 'express_checkout';
            }
            $selected_pages[] = 'checkout';
            if (isset($raw_settings['show_on_mini_cart']) && $raw_settings['show_on_mini_cart'] === 'yes') {
                $selected_pages[] = 'mini_cart';
            }
        }
        $raw_settings['paypal_button_pages'] = array_unique($selected_pages);
        update_option($option_key, $raw_settings);
        update_option('_wpg_button_pages_migrated', true);
        $this->settings = $raw_settings;
    }

    public function is_available() {
        if ($this->is_credentials_set() && $this->enabled === 'yes') {
            return true;
        }
        return false;
    }

    public function wpg_ppcp_get_onboarding_status() {
        if (empty($this->merchant_id) || !$this->is_credentials_set()) {
            return;
        }
        if (get_transient($this->available_end_point_key) !== false) {
            return;
        }
        set_transient($this->available_end_point_key, [], MINUTE_IN_SECONDS);
        $availableEndpoints = [];
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
                $availableEndpoints['google_pay'] = 'SUBSCRIBED';
                $this->update_option('enabled_google_pay', 'yes');
            } else {
                $this->update_option('enabled_google_pay', 'no');
            }
            if (wpg_is_apple_pay_approved($result)) {
                $availableEndpoints['apple_pay'] = 'SUBSCRIBED';
            } else {
                $this->update_option('enabled_apple_pay', 'no');
            }
            if (wpg_is_vaulting_enable($result)) {
                $availableEndpoints['save_card'] = 'SUBSCRIBED';
            }
            if (!empty($result['primary_email'])) {
                $this->update_option($this->sandbox ? 'ppcp_email_sandbox' : 'ppcp_email_live', sanitize_email($result['primary_email']));
            }
            set_transient($this->available_end_point_key, $availableEndpoints, DAY_IN_SECONDS);
        }
    }

    public function wpg_is_end_point_enable($end_point) {
        if (empty($this->merchant_id) || !$this->is_credentials_set()) {
            return false;
        }
        $available_end_point = get_transient($this->available_end_point_key);
        if ($available_end_point === false) {
            $this->wpg_ppcp_get_onboarding_status();
            $available_end_point = get_transient($this->available_end_point_key);
        }
        if (isset($available_end_point[$end_point]) && $available_end_point[$end_point] === 'SUBSCRIBED') {
            return true;
        } else {
            return false;
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
        if (!$this->is_credentials_set() && !is_existing_classic_user()) {
            $wpg_section = isset($_GET['wpg_section']) ? sanitize_text_field($_GET['wpg_section']) : 'wpg_api_settings';
            if ($wpg_section !== 'wpg_api_settings') {
                $api_settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout');
                // translators: %1$s: URL to the PayPal API settings page.
                $message = sprintf(__('<strong>PayPal Setup Required:</strong> Connect your PayPal account or enter your Client ID and Secret in the <a href="%1$s">API settings</a> to begin accepting payments.', 'woo-paypal-gateway'), esc_url($api_settings_url));
                printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', wp_kses_post($message));
            }
        }
        self::$notice_shown = true;
    }

    public function payment_fields() {
        $description = $this->get_description();
        if (!empty($description) && $this->show_redirect_icon === false) {
            echo wpautop(wptexturize($description));
        }
        if ($this->show_redirect_icon === true) {
            ?>
            <div class="wc_ppcp_wpg_container">
                <?php
                echo '<img src="' . esc_url($this->redirect_icon) . '" />';
                if (is_add_payment_method_page()) {
                    $message = esc_html__('Click the PayPal button to add your payment method.', 'woo-paypal-gateway');
                } elseif ($this->use_place_order) {
                    $message = sprintf(
                            esc_html__('Click the "%s" button below to process your order.', 'woo-paypal-gateway'),
                            esc_html($this->order_button_text)
                    );
                } else {
                    $message = esc_html__('Click a payment option below to process your order.', 'woo-paypal-gateway');
                }
                echo '<p>' . $message . '</p>';
                ?>
            </div>
            <?php
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
        $this->display_paypal_admin_notice();
        wp_enqueue_script('wc-clipboard');
        if (function_exists('wc_back_header')) {
            wc_back_header($this->get_method_title(), __('Return to payments', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        } else {
            echo '<h2>' . $this->method_title;
            wc_back_link(__('Return to payments', 'woo-paypal-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            echo '</h2>';
        }
        echo '<br/>';
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
            'wpg_paypal_checkout_cc' => __('Advanced Card Payments', 'woo-paypal-gateway'),
            'wpg_google_pay' => __('Google Pay', 'woo-paypal-gateway'),
            'wpg_apple_pay' => __('Apple Pay', 'woo-paypal-gateway'),
            'wpg_ppcp_paylater' => __('Pay Later Messaging', 'woo-paypal-gateway'),
            'wpg_advanced_settings' => __('Additional Settings', 'woo-paypal-gateway'),
            'wpg_foq' => __('FAQ', 'woo-paypal-gateway'),
        );
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active_class = ($key === $current_tab) ? 'nav-tab-active' : '';

            $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=' . $key);
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
        } elseif ($this->wpg_section === 'wpg_paypal_checkout_cc') {
            $is_advanced_cc_enable = ($this->wpg_is_end_point_enable('advanced_cc') === true) ? 'yes' : 'no';
            $is_save_card_enable = ($this->wpg_is_end_point_enable('save_card') === true) ? 'yes' : 'no';
            $wpg_advanced_cc_settings = $this->settings_obj->wpg_advanced_cc_settings($is_advanced_cc_enable, $is_save_card_enable);
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_cc_settings));
        } elseif ($this->wpg_section === 'wpg_ppcp_paylater') {
            $wpg_ppcp_paylater_settings = $this->settings_obj->wpg_ppcp_paylater_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_ppcp_paylater_settings));
        } elseif ($this->wpg_section === 'wpg_advanced_settings') {
            $wpg_advanced_settings = $this->settings_obj->wpg_advanced_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_settings));
        } elseif ($this->wpg_section === 'wpg_google_pay') {
            $is_google_pay_enable = ($this->wpg_is_end_point_enable('google_pay') === true) ? 'yes' : 'no';
            $wpg_google_pay_settings = $this->settings_obj->wpg_ppcp_google_pay_settings($is_google_pay_enable);
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_google_pay_settings));
        } elseif ($this->wpg_section === 'wpg_foq') {
            $wpg_foq = $this->settings_obj->wpg_foq();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_foq));
        } elseif ($this->wpg_section === 'wpg_apple_pay') {
            $is_apple_pay_enable = ($this->wpg_is_end_point_enable('apple_pay') === true) ? 'yes' : 'no';
            $wpg_apple_pay_settings = $this->settings_obj->wpg_ppcp_apple_pay_settings($is_apple_pay_enable);
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_apple_pay_settings));
        } else {
            $this->form_fields = $this->settings_obj->ppcp_setting_fields();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $this->form_fields));
        }
    }

    public function process_payment($woo_order_id) {
        if (isset($_GET['from']) && 'checkout' === $_GET['from'] && isset($_GET['used']) && 'alternative_pay' === $_GET['used'] && isset($_GET['ppcp_action']) && 'get_transaction_info' === $_GET['ppcp_action']) {
            wp_send_json_success(array('cart_total' => WC()->cart->total));
            exit();
        }
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
                    wpg_clear_ppcp_session_and_cart();
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
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                                                                          ?></label>
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
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                            ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.                                                                                                                                                                                                 ?> />
                    <button type="button" class="button-secondary <?php echo esc_attr($data['button_class']); ?>" data-tip="Copied!">Copy</button>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.                                        ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function admin_scripts() {
        if (isset($_GET['section']) && ('wpg_paypal_checkout' === $_GET['section'] || 'wpg_paypal_checkout_cc' === $_GET['section'])) {
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
                'wpg_setting_url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_api_settings')
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
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                                                           ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    $connected_email = $this->sandbox ? $this->get_option('ppcp_email_sandbox') : $this->get_option('ppcp_email_live');
                    ?>
                    <div class="wpg_ppcp_paypal_connection">
                        <div class="wpg_ppcp_paypal_connection_status">
                            <h3>
                                <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                <?php echo esc_html__('PayPal Account Successfully Connected!', 'woo-paypal-gateway'); ?>
                            </h3>

                            <?php if (!empty($connected_email)) : ?>
                                <p style="margin: 5px 0 0; color: #555;">
                                    <strong><?php esc_html_e('Connected Email:', 'woo-paypal-gateway'); ?></strong>
                                    <?php echo esc_html($connected_email); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="button wpg-ppcp-disconnect"><?php echo __('Disconnect PayPal Account', 'woo-paypal-gateway'); ?></button>
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
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                      ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.                                                                                                                                        ?> />
                        <?php echo $this->get_description_html($data); // WPCS: XSS ok.                            ?>
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
            if (false !== get_transient('wpg_sandbox_seller_onboarding_process_done')) {
                return $settings;
            }
            $is_sandbox = isset($settings['sandbox']) && $settings['sandbox'] === 'yes';
            $environment = $is_sandbox ? 'sandbox' : 'live';
            $client_id_key = "rest_client_id_{$environment}";
            $secret_id_key = "rest_secret_id_{$environment}";
            $email_key = "ppcp_email_{$environment}";
            $client_id = isset($settings[$client_id_key]) ? sanitize_text_field($settings[$client_id_key]) : '';
            $secret_id = isset($settings[$secret_id_key]) ? sanitize_text_field($settings[$secret_id_key]) : '';
            $settings[$email_key] = '';
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
            $label = ($testmode === 'no') ? __('Click to Connect PayPal', 'woo-paypal-gateway') : __('Click to Connect PayPal', 'woo-paypal-gateway');
            ob_start();
            ?>
            <tr valign="top" style="display:none;">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                                    ?></label>
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
        ?>
        <div class="wpg-paypal-onboard-wrap">
            <div class="wpg-paypal-connect-row">
                <a target="_blank"
                   class="button wpg-paypal-connect-button"
                   id="<?php echo esc_attr($id); ?>"
                   data-paypal-onboard-complete="onboardingCallback"
                   href="<?php echo esc_url($url); ?>"
                   data-paypal-button="true">
                       <?php echo esc_html($label); ?>
                </a>
                <span class="wpg-paypal-recommend"><?php esc_html_e('Recommended', 'woo-paypal-gateway'); ?></span>
            </div>

            <div class="wpg-paypal-separator">
                <span>───────────</span>
                <?php esc_html_e('OR', 'woo-paypal-gateway'); ?>
                <span>───────────</span>
            </div>

            <a href="#" class="wpg_paypal_checkout_gateway_manual_credential_input">
                <?php esc_html_e('Click here to insert credentials manually', 'woo-paypal-gateway'); ?>
            </a>
        </div>
        <?php
    }

    public function wpg_get_signup_link($testmode = 'yes') {
        $env = ($testmode === 'yes') ? 'sandbox' : 'live';
        $key = 'wpg_signup_link_' . $env;
        $link = get_transient($key);
        if ($link) {
            return $link;
        }

        try {
            $result = $this->seller_onboarding->wpg_generate_signup_link($testmode);
            if (isset($result['result'], $result['body']) && $result['result'] === 'success') {
                $json = json_decode($result['body']);
                if (!empty($json->links)) {
                    foreach ($json->links as $l) {
                        if ($l->rel === 'action_url') {
                            set_transient($key, $l->href, 5 * MINUTE_IN_SECONDS);
                            return (string) $l->href;
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            
        }

        return false;
    }

    public function wpg_get_signup_link_for_google_pay($testmode = 'yes') {
        $env = ($testmode === 'yes') ? 'sandbox' : 'live';
        $key = 'wpg_google_pay_signup_link_' . $env;
        $link = get_transient($key);
        if ($link) {
            return $link;
        }
        try {
            $result = $this->seller_onboarding->wpg_generate_signup_link_for_google_pay($testmode);
            if (isset($result['result'], $result['body']) && $result['result'] === 'success') {
                $json = json_decode($result['body']);
                if (!empty($json->links)) {
                    foreach ($json->links as $l) {
                        if ($l->rel === 'action_url') {
                            set_transient($key, $l->href, 5 * MINUTE_IN_SECONDS);
                            return (string) $l->href;
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
        return false;
    }

    public function wpg_get_signup_link_for_apple_pay($testmode = 'yes') {
        $env = ($testmode === 'yes') ? 'sandbox' : 'live';
        $key = 'wpg_apple_pay_signup_link_' . $env;
        $link = get_transient($key);
        if ($link) {
            return $link;
        }
        try {
            $result = $this->seller_onboarding->wpg_generate_signup_link_for_apple_pay($testmode);
            if (isset($result['result'], $result['body']) && $result['result'] === 'success') {
                $json = json_decode($result['body']);
                if (!empty($json->links)) {
                    foreach ($json->links as $l) {
                        if ($l->rel === 'action_url') {
                            set_transient($key, $l->href, 5 * MINUTE_IN_SECONDS);
                            return (string) $l->href;
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
        return false;
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
                                <?php echo __('Which PayPal buttons (e.g. Pay Later, Venmo, SEPA, iDEAL, Mercado Pago, Bancontact, etc.) will appear?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('The PayPal buttons are shown automatically based on several factors, including:', 'woo-paypal-gateway'); ?></p>
                                <ul>
                                    <li><?php echo __('The buyer’s country', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Their device type', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('The funding sources they’ve enabled', 'woo-paypal-gateway'); ?></li>
                                </ul>
                                <p><?php echo __('As a result, each buyer may see a different set of buttons. Pay Later options vary by country and may display different buttons.', 'woo-paypal-gateway'); ?></p>
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question" aria-expanded="false">
                                <?php echo __('How can I hide specific PayPal buttons?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('By default, all PayPal buttons are enabled. To hide specific buttons, use the <strong>Disable Specific Payment Buttons</strong> option.', 'woo-paypal-gateway'); ?></p>
                                <h4><?php echo __('Example: To hide the Pay Later button on the Checkout Page:', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>PayPal Settings</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Find the <strong>Disable Specific Payment Buttons</strong> setting.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Select <strong>Pay Later</strong> from the dropdown list.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-question" aria-expanded="false">
                                <?php echo __('How can I show only the "PayPal" button on the Checkout page?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('To display only the "PayPal" button on the Checkout page, follow these steps:', 'woo-paypal-gateway'); ?></p>
                                <h4><?php echo __('Step 1: Update PayPal Settings', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>PayPal Settings</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Under <strong>Disable Specific Payment Buttons</strong>, select <strong>Credit or Debit Card</strong> and any other methods you want to hide.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                                <h4><?php echo __('Step 2: Update Advanced Card Payments Settings', 'woo-paypal-gateway'); ?></h4>
                                <ol>
                                    <li><?php echo __('Go to <strong>Advanced Card Payments</strong> settings.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Disable the option <strong>Enable Advanced Credit/Debit Card</strong>.', 'woo-paypal-gateway'); ?></li>
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
                                    <li><?php echo __('Enable the option <strong>Enable Advanced Credit/Debit Card</strong>.', 'woo-paypal-gateway'); ?></li>
                                    <li><?php echo __('Click <strong>Save Changes</strong>.', 'woo-paypal-gateway'); ?></li>
                                </ol>
                                <p><?php echo __('Once you save the changes in both sections, only the "Credit or Debit Card" payment method will appear on the Checkout page.', 'woo-paypal-gateway'); ?></p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-question" aria-expanded="false">
                                <?php echo __('What is PayPal Pay Later?', 'woo-paypal-gateway'); ?>
                                <span class="faq-toggle"></span>
                            </div>
                            <div class="faq-answer">
                                <p><?php echo __('PayPal Pay Later lets your customers split their purchases into interest-free payments over time, such as "Pay in 4" or monthly installments. You get paid in full upfront, while customers enjoy flexible payment options.', 'woo-paypal-gateway'); ?></p>
                                <p><?php echo __('This can help increase your sales, with many merchants seeing higher conversion rates and larger order sizes.', 'woo-paypal-gateway'); ?></p>
                                <p>
                                    <a href="https://www.paypal.com/us/business/accept-payments/checkout/installments" target="_blank" rel="noopener noreferrer">
                                        <?php echo __('Learn more about Pay Later on PayPal’s website.', 'woo-paypal-gateway'); ?>
                                    </a>
                                </p>
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
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                                                         ?></label>
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
        if (isset($data['type']) && $data['type'] === 'gpay_title' && !empty($data['description'])) {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <div class="ppcp-google-pay-notice-box">
                <?php echo '<strong>' . esc_html__('Important: ', 'woo-paypal-gateway') . '</strong>'; ?>
                <?php echo wp_kses_post($data['description']); ?>
            </div>
            <?php
            return ob_get_clean();
        }
        return '';
    }

    public function generate_advanced_card_pay_title_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'advanced_card_pay_title' && !empty($data['description'])) {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <style>
                .ppcp-advanced_card_pay-notice-box {
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
            <div class="ppcp-advanced_card_pay-notice-box">
                <?php echo '<strong>' . esc_html__('Important: ', 'woo-paypal-gateway') . '</strong>'; ?>
                <?php echo $data['description']; ?>
            </div>
            <?php
            return ob_get_clean();
        }
        return '';
    }

    public function generate_apple_title_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'apple_title' && !empty($data['description'])) {
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
                <?php echo '<strong>' . esc_html__('Important: ', 'woo-paypal-gateway') . '</strong>'; ?>
                <?php echo wp_kses_post($data['description']); ?>
            </div>
            <?php
            return ob_get_clean();
        }
        return '';
    }

    public function generate_pay_later_messaging_title_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'pay_later_messaging_title') {
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
                    border-left: 4px solid #72aee6;
                    font-size: 14px;
                    margin-top: 15px;
                    margin-bottom: 15px;
                }
            </style>
            <div class="ppcp-apple-title-notice-box">
                <?php echo $data['description']; ?>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_disallowed_funding_methods_note_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'disallowed_funding_methods_note') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> </label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
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
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                                                                                                                         ?></label>
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
                        <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.         ?>
                    </label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <?php
                    // Determine the URL for PayPal Apple Pay based on the environment
                    $paypal_apple_pay_url = $this->sandbox ? 'https://www.sandbox.paypal.com/uccservicing/apm/applepay' : 'https://www.paypal.com/uccservicing/apm/applepay';
                    ?>
                    <a href="<?php echo esc_url($paypal_apple_pay_url); ?>" class="button" target="_blank">
                        <?php echo __('Register Your Domain with Apple Pay', 'woo-paypal-gateway'); ?>
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

    public function wpg_ppcp_display_other_plugin() {
        static $shown = false;
        if ($shown) {
            return;
        }
        $shown = true;

        if (!isset($_GET['section']) || $_GET['section'] !== 'wpg_paypal_checkout' || (isset($_GET['wpg_section']) && $_GET['wpg_section'] !== 'wpg_api_settings')) {
            return;
        }

        $country = get_option('woocommerce_default_country');
        if (strpos($country, 'IN') !== 0) {
            return;
        }

        $plugins = [
            [
                'slug' => 'easy-payment-gateway-for-razorpay-and-for-woocommerce',
                'title' => __('Easy Payment Gateway for Razorpay and for WooCommerce', 'woo-paypal-gateway'),
                'description' => __('Accept payments through UPI, Cards, and Net Banking using Razorpay — developed by an official Razorpay Partner.', 'woo-paypal-gateway'),
                'image' => 'https://ps.w.org/easy-payment-gateway-for-razorpay-and-for-woocommerce/assets/icon-256x256.png',
            ],
            [
                'slug' => 'payment-gateway-for-phonepe-and-for-woocommerce',
                'title' => __('Payment Gateway for PhonePe and for WooCommerce', 'woo-paypal-gateway'),
                'description' => __('Accept PhonePe UPI, Wallet, and Cards in WooCommerce with a secure, fast, and seamless payment gateway developed by a PhonePe Partner.', 'woo-paypal-gateway'),
                'image' => 'https://ps.w.org/payment-gateway-for-phonepe-and-for-woocommerce/assets/icon-256x256.png',
            ]
        ];
        ?>
        <div class="wpg-india-highlight">
            <div class="section-title"><?php _e('Recommended payment method for Indian merchants', 'woo-paypal-gateway'); ?></div>
            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                <?php
                foreach ($plugins as $plugin) :
                    $plugin_file = $plugin['slug'] . '/' . $plugin['slug'] . '.php';
                    $plugin_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_file);
                    $plugin_active = is_plugin_active($plugin_file);

                    if ($plugin_installed || $plugin_active) {
                        continue;
                    }

                    $install_url = wp_nonce_url(
                            self_admin_url('update.php?action=install-plugin&plugin=' . $plugin['slug']),
                            'install-plugin_' . $plugin['slug']
                    );
                    ?>
                    <div class="wpg-india-plugin" style="display: flex; max-width: 430px; border: 1px solid #ccc; padding: 16px; border-radius: 8px; background: #fff;">
                        <img src="<?php echo esc_url($plugin['image']); ?>" alt="<?php esc_attr_e('Plugin Logo', 'woo-paypal-gateway'); ?>" style="width: 64px; height: 64px; margin-right: 16px;">
                        <div class="plugin-info">
                            <h3 style="margin-top: 0;">
                                <a href="https://wordpress.org/plugins/<?php echo esc_attr($plugin['slug']); ?>/" target="_blank">
                                    <?php echo $plugin['title']; ?>
                                </a>
                            </h3>
                            <p><?php echo $plugin['description']; ?></p>
                            <div class="author">
                                <?php _e('By', 'woo-paypal-gateway'); ?>
                                <a href="https://profiles.wordpress.org/easypayment/" target="_blank">Easy Payment</a>
                            </div>
                            <div class="plugin-actions" style="margin-top: 8px;">
                                <a class="install-now button" href="<?php echo esc_url($install_url); ?>">
                                    <?php _e('Install Now', 'woo-paypal-gateway'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
