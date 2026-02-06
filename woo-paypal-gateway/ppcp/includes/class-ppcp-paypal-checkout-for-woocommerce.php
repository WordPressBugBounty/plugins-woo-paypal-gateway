<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce/ppcp/includes
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce {

    protected $loader;
    protected $plugin_name;
    protected $version;
    public $button_manager;
    public $subscription_support_enabled;

    public function __construct() {
        if (defined('WPG_PLUGIN_VERSION')) {
            $this->version = WPG_PLUGIN_VERSION;
        } else {
            $this->version = '5.1.0';
        }
        $this->plugin_name = 'woo-paypal-gateway';
        add_filter('woocommerce_payment_gateways', array($this, 'ppcp_woocommerce_payment_gateways'), 999);

        $this->load_dependencies();
        $seller_onboarding = PPCP_Paypal_Checkout_For_Woocommerce_Seller_Onboarding::instance();
        if (!has_action('admin_init', array($seller_onboarding, 'wpg_listen_for_merchant_id'))) {
            add_action('admin_init', array($seller_onboarding, 'wpg_listen_for_merchant_id'));
        }
        $this->set_locale();
        $this->define_public_hooks();
        add_action('woocommerce_update_option', array($this, 'ppcp_cc_gateway_status_handler'), 10, 1);
    }

    private function load_dependencies() {
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/ppcp-paypal-checkout-for-woocommerce-function.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-loader.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-i18n.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/public/class-ppcp-paypal-checkout-for-woocommerce-button-manager.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-product.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-pay-later-messaging.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-seller-onboarding.php';
        require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-tracking.php';
        PPCP_Paypal_Checkout_For_Woocommerce_Tracking::get_instance();
        $this->loader = new PPCP_Paypal_Checkout_For_Woocommerce_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new PPCP_Paypal_Checkout_For_Woocommerce_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_public_hooks() {
        $this->button_manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
        PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later::instance();
        PPCP_Paypal_Checkout_For_Woocommerce_Seller_Onboarding::instance();
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    public function ppcp_woocommerce_payment_gateways($methods) {
        $this->subscription_support_enabled = class_exists('WC_Subscriptions') && function_exists('wcs_create_renewal_order');
        include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-gateway.php';
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-gateway-cc.php';
        }
        if ($this->subscription_support_enabled) {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Subscriptions')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/subscriptions/class-ppcp-paypal-checkout-for-woocommerce-subscriptions.php';
            }
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Subscriptions_CC')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/subscriptions/class-ppcp-paypal-checkout-for-woocommerce-subscriptions-cc.php';
            }
        }
        $methods[] = $this->subscription_support_enabled ? 'PPCP_Paypal_Checkout_For_Woocommerce_Subscriptions_CC' : 'PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC';
        $methods[] = $this->subscription_support_enabled ? 'PPCP_Paypal_Checkout_For_Woocommerce_Subscriptions' : 'PPCP_Paypal_Checkout_For_Woocommerce_Gateway';
        $methods = array_reverse($methods);
        return $methods;
    }

    public function ppcp_cc_gateway_status_handler($option_name) {
        if (isset($_GET['wpg_section'])) {
            return true;
        }
        $paypal_settings_option = 'woocommerce_wpg_paypal_checkout_settings';
        $gateway_setting_key = 'enable_advanced_card_payments';
        if (is_array($option_name) && isset($option_name['id']) && $option_name['id'] === 'woocommerce_wpg_paypal_checkout_cc_settings') {
            $paypal_settings = get_option($paypal_settings_option, []);
            $advanced_card_enabled = isset($paypal_settings[$gateway_setting_key]) ? $paypal_settings[$gateway_setting_key] : 'no';
            remove_action('woocommerce_update_option', [$this, 'ppcp_cc_gateway_status_handler'], 10);
            if ('yes' === $advanced_card_enabled) {
                $paypal_settings[$gateway_setting_key] = 'no';
                update_option($paypal_settings_option, $paypal_settings);
                $enabled = 'yes';
            } else {
                $paypal_settings[$gateway_setting_key] = 'yes';
                update_option($paypal_settings_option, $paypal_settings);
                $enabled = 'no';
            }
            add_action('woocommerce_update_option', [$this, 'ppcp_cc_gateway_status_handler'], 10, 1);
            if (wp_doing_ajax()) {
                wp_send_json_success(!wc_string_to_bool($enabled));
                wp_die();
            }
        }
    }
}
