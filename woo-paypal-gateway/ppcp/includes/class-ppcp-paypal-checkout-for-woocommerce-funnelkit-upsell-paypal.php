<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell')) {
    return;
}

/**
 * FunnelKit Upsell gateway bridge for PPCP PayPal gateway.
 */
class PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell_PayPal extends PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell {

    /**
     * FunnelKit gateway key (must match WC gateway id).
     *
     * @var string
     */
    public $key = 'wpg_paypal_checkout';
}
