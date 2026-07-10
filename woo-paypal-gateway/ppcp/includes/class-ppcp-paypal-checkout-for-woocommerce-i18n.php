<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce/includes
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce_i18n {

    /**
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for backward compatibility and non-.org installs.
        load_plugin_textdomain(
                'woo-paypal-gateway', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

}
