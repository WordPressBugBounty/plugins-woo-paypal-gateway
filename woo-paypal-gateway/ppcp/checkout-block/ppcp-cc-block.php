<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class PPCP_Checkout_CC_Block extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'wpg_paypal_checkout_cc';
    public $pay_later;
    public $version;

    public function initialize() {
        $this->version = WPG_PLUGIN_VERSION;
        $this->settings = get_option('woocommerce_wpg_paypal_checkout_settings', []);
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC')) {
            include_once ( WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-gateway-cc.php');
        }
        $this->gateway = new PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC();
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later')) {
            include_once ( WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-pay-later-messaging.php');
        }
        $this->pay_later = PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later::instance();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        if (!function_exists('has_block') || !wpg_is_using_block_cart_or_checkout()) {
            return [];
        }
        wp_enqueue_script('ppcp-checkout-js');
        if (ppcp_has_active_session() === false) {
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
        }
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        wp_register_script('wpg_paypal_cc-blocks-integration', WPG_PLUGIN_ASSET_URL . 'ppcp/checkout-block/ppcp-cc.js', array('jquery', 'react', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n', 'wp-polyfill', 'wp-element', 'wp-plugins'), WPG_PLUGIN_VERSION, true);
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wpg_paypal_checkout_cc-blocks-integration', 'woo-paypal-gateway');
        }
        wp_enqueue_script('wpg_paypal_checkout');
        return ['wpg_paypal_cc-blocks-integration'];
    }

    public function get_payment_method_data() {
        $page = '';
        $is_pay_page = '';
        if (is_product()) {
            $page = 'product';
        } else if (is_cart() && WC()->cart && !WC()->cart->is_empty()) {
            $page = 'cart';
        } elseif (is_checkout_pay_page()) {
            $page = 'checkout';
            $is_pay_page = 'yes';
        } elseif (is_checkout()) {
            $page = 'checkout';
        }
        $is_paylater_enable_incart_page = 'no';
        if ($this->pay_later->is_paypal_pay_later_messaging_enable_for_page($page = 'cart') && $this->pay_later->pay_later_messaging_cart_shortcode === false) {
            $is_paylater_enable_incart_page = 'yes';
        } else {
            $is_paylater_enable_incart_page = 'no';
        }
        return [
            'cc_title' => $this->gateway->title,
            'description' => $this->get_setting('description'),
            'supports' => $this->get_supported_features(),
            'icons' => $this->gateway->get_block_icon(),
            'enable_save_card' => $this->gateway->enable_save_card,
            'is_order_confirm_page' => (ppcp_has_active_session() === false) ? 'no' : 'yes',
            'is_paylater_enable_incart_page' => $is_paylater_enable_incart_page,
            'page' => $page,
            'card_number' => _x('Card number', 'Important', 'woo-paypal-gateway'),
            'expiration_date' => _x('Expiration date', 'Important', 'woo-paypal-gateway'),
            'security_code' => _x('Security code', 'Important', 'woo-paypal-gateway'),
        ];
    }
}
