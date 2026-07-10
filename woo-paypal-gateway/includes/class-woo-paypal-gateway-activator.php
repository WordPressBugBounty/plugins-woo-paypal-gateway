<?php

/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    Woo_Paypal_Gateway
 * @subpackage Woo_Paypal_Gateway/includes
 * @author     easypayment
 */
class Woo_Paypal_Gateway_Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Sets the activation timestamp and plugin version for migration tracking.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		if ( ! get_option( 'wpg_activation_time' ) ) {
			update_option( 'wpg_activation_time', time() );
		}

		if ( defined( 'WPG_PLUGIN_VERSION' ) ) {
			update_option( 'wpg_ppcp_db_version', WPG_PLUGIN_VERSION );
		}
	}
}
