<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class PPCP_Checkout_CC_Block extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'wpg_paypal_checkout_cc';
    public $pay_later;
    public $version;

    public function initialize() {
        $this->version = WPG_PLUGIN_VERSION;
        $this->settings = get_option('woocommerce_wpg_paypal_checkout_settings', []);
        // Guard the includes so a missing file (e.g. the plugin folder being
        // replaced mid-request during an update) disables this payment block
        // for the request instead of fataling the whole site.
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC')) {
            $gateway_file = WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-gateway-cc.php';
            if (file_exists($gateway_file)) {
                include_once $gateway_file;
            }
        }
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC')) {
            return;
        }
        $this->gateway = new PPCP_Paypal_Checkout_For_Woocommerce_Gateway_CC();
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later')) {
            $pay_later_file = WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-pay-later-messaging.php';
            if (file_exists($pay_later_file)) {
                include_once $pay_later_file;
            }
        }
        if (class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later')) {
            $this->pay_later = PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later::instance();
        }
    }

    public function is_active() {
        if (!is_object($this->gateway)) {
            return false;
        }
        return $this->gateway->is_available();
    }

    public function get_supported_features() {
        if (!is_object($this->gateway)) {
            return array();
        }
        return $this->gateway->supports;
    }

    public function get_payment_method_script_handles() {
        if (!function_exists('has_block') || !woo_paypal_gateway_is_using_block_cart_or_checkout()) {
            return [];
        }
        wp_enqueue_script('ppcp-checkout-js');
        if (woo_paypal_gateway_ppcp_has_active_session() === false) {
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
        }
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        wp_register_script('wpg_paypal_cc-blocks-integration', WPG_PLUGIN_ASSET_URL . 'ppcp/checkout-block/ppcp-cc.js', array('jquery', 'react', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n', 'wp-polyfill', 'wp-element', 'wp-plugins'), WPG_PLUGIN_VERSION, true);
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wpg_paypal_cc-blocks-integration', 'woo-paypal-gateway');
        }
        wp_enqueue_script('wpg_paypal_checkout');
        return ['wpg_paypal_cc-blocks-integration'];
    }

    private function is_blocks_checkout() {
        if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\BlocksWooUtils' ) ) {
            return \Automattic\WooCommerce\Blocks\Utils\BlocksWooUtils::is_checkout_block();
        }
        if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && function_exists( 'has_block' ) ) {
            $checkout_page_id = wc_get_page_id( 'checkout' );
            global $post;
            if ( $checkout_page_id && $post instanceof \WP_Post && (int) $post->ID === $checkout_page_id && has_block( 'woocommerce/checkout', $post ) ) {
                return true;
            }
        }
        return false;
    }

    public function get_payment_method_data() {
        if (!is_object($this->gateway)) {
            return array();
        }
        $page = '';
        $is_pay_page = '';
        if (is_product()) {
            $page = 'product';
        } else if (is_cart()) {
            $page = 'cart';
        } elseif (is_checkout_pay_page()) {
            $page = 'checkout';
            $is_pay_page = 'yes';
        } elseif (is_checkout() || $this->is_blocks_checkout()) {
            $page = 'checkout';
        }
        $is_paylater_enable_incart_page = 'no';
        if (is_object($this->pay_later) && $this->pay_later->is_paypal_pay_later_messaging_enable_for_page($page = 'cart') && $this->pay_later->pay_later_messaging_cart_shortcode === false) {
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
            'is_order_confirm_page' => (woo_paypal_gateway_ppcp_has_active_session() === false) ? 'no' : 'yes',
            'is_paylater_enable_incart_page' => $is_paylater_enable_incart_page,
            'page' => $page,
            'card_number' => _x('Card number', 'Important', 'woo-paypal-gateway'),
            'expiration_date' => _x('Expiration date', 'Important', 'woo-paypal-gateway'),
            'security_code' => _x('Security code', 'Important', 'woo-paypal-gateway'),
        ];
    }
}
