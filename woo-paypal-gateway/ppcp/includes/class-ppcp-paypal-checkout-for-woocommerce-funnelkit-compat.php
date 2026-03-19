<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelKit (Checkout + Upsell + Post Purchase) compatibility for PPCP.
 */
class PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Compat {

    /**
     * Bootstrap hooks.
     *
     * @return void
     */
    public static function init() {
        add_filter('woocommerce_payment_gateway_supports', [__CLASS__, 'ensure_tokenization_support_for_funnelkit'], 10, 3);
        add_filter('wfocu_wc_get_supported_gateways', [__CLASS__, 'register_gateway_associative'], 10);
        add_action('woocommerce_new_order', [__CLASS__, 'copy_parent_order_payment_tokens'], 20);
        add_action('wp_loaded', [__CLASS__, 'register_upsell_gateway_loader'], 1);
    }

    /**
     * Register PPCP in FunnelKit supported-gateway list (associative format).
     *
     * @param mixed $gateways
     * @return array
     */
    public static function register_gateway_associative($gateways) {
        if (!is_array($gateways)) {
            $gateways = [];
        }

        $gateways['wpg_paypal_checkout'] = 'PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell_PayPal';
        $gateways['wpg_paypal_checkout_cc'] = 'PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell';

        return $gateways;
    }

    /**
     * Ensure PPCP CC gateway appears tokenization-capable while FunnelKit is active.
     *
     * @param bool  $supports
     * @param string $feature
     * @param mixed $gateway
     * @return bool
     */
    public static function ensure_tokenization_support_for_funnelkit($supports, $feature, $gateway) {
        if ('tokenization' !== $feature) {
            return $supports;
        }

        if (!in_array($gateway->id, ['wpg_paypal_checkout', 'wpg_paypal_checkout_cc'], true)) {
            return $supports;
        }

        if (!self::is_funnelkit_active()) {
            return $supports;
        }

        return true;
    }

    /**
     * Copy payment token metadata from parent to child order for FunnelKit orders.
     *
     * @param int $order_id
     * @return void
     */
    public static function copy_parent_order_payment_tokens($order_id) {
        if (!self::is_funnelkit_active()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        $parent_id = (int) $order->get_parent_id();
        if ($parent_id <= 0) {
            return;
        }

        $parent_order = wc_get_order($parent_id);
        if (!$parent_order instanceof WC_Order) {
            return;
        }

        if (!in_array($parent_order->get_payment_method(), ['wpg_paypal_checkout', 'wpg_paypal_checkout_cc'], true)) {
            return;
        }

        if (!in_array($order->get_payment_method(), ['wpg_paypal_checkout', 'wpg_paypal_checkout_cc'], true)) {
            $order->set_payment_method($parent_order->get_payment_method());
        }

        $child_payment_token = $order->get_meta('_payment_tokens_id', true);
        if (empty($child_payment_token)) {
            $parent_payment_token = $parent_order->get_meta('_payment_tokens_id', true);
            if (!empty($parent_payment_token)) {
                $order->update_meta_data('_payment_tokens_id', $parent_payment_token);
            }
        }

        $child_payment_source = $order->get_meta('_wpg_ppcp_used_payment_method', true);
        if (empty($child_payment_source)) {
            $parent_payment_source = $parent_order->get_meta('_wpg_ppcp_used_payment_method', true);
            if (!empty($parent_payment_source)) {
                $order->update_meta_data('_wpg_ppcp_used_payment_method', $parent_payment_source);
            }
        }

        $order->save();
    }

    /**
     * Replace default FunnelKit gateways loader and prepend PPCP gateway class.
     *
     * @return void
     */
    public static function register_upsell_gateway_loader() {
        if (!function_exists('WFOCU_Core') || !class_exists('WFOCU_Gateways')) {
            return;
        }

        $core = WFOCU_Core();
        if (isset($core->gateways) && is_object($core->gateways) && get_class($core->gateways) === 'WFOCU_Gateways') {
            $core->gateways = new class extends WFOCU_Gateways {

                public function get_supported_gateways() {
                    $filtered = parent::get_supported_gateways();
                    unset($filtered['wpg_paypal_checkout']);
                    unset($filtered['wpg_paypal_checkout_cc']);

                    return array_merge(
                        [
                            'wpg_paypal_checkout' => 'PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell_PayPal',
                            'wpg_paypal_checkout_cc' => 'PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell',

                        ],
                        $filtered
                    );
                }
            };
        }

    }

    /**
     * Check if any FunnelKit plugin is active.
     *
     * @return bool
     */
    protected static function is_funnelkit_active() {
        if (class_exists('WFACP_Core') || class_exists('WFOCU_Core') || class_exists('WFFN_Core')) {
            return true;
        }

        if (function_exists('WFACP_Core') || function_exists('WFOCU_Core') || function_exists('WFFN_Core')) {
            return true;
        }

        if (defined('WFACP_VERSION') || defined('WFOCU_VERSION') || defined('WFFN_VERSION')) {
            return true;
        }

        return false;
    }
}

PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Compat::init();
