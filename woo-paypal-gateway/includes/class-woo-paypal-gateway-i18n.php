<?php

/**
 * Define the internationalization functionality.
 * @since      1.0.0
 * @package    Woo_Paypal_Gateway
 * @subpackage Woo_Paypal_Gateway/includes
 * @author     easypayment
 */
class Woo_Paypal_Gateway_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        // Explicitly load bundled translations from the plugin's /languages/ directory.
        // WordPress can auto-load these just-in-time, but the call is kept for reliable
        // loading of shipped translation files across hosting configurations.
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Intentional load of translations bundled with the plugin.
        load_plugin_textdomain(
                'woo-paypal-gateway', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

}
