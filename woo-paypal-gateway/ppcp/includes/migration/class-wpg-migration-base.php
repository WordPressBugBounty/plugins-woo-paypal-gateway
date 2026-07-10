<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base migration class with common helpers.
 *
 * Subclass this instead of implementing WPG_Migration_Interface directly.
 * Provides safe settings read/write, option helpers, and sensible defaults.
 */
abstract class WPG_Migration_Base implements WPG_Migration_Interface {

	protected $paypal_settings_key = 'woocommerce_wpg_paypal_checkout_settings';
	protected $cc_settings_key     = 'woocommerce_wpg_paypal_checkout_cc_settings';

	public function requires_woocommerce() {
		return false;
	}

	public function verify() {
		return true;
	}

	/**
	 * Read the main PayPal gateway settings.
	 *
	 * @return array
	 */
	protected function get_paypal_settings() {
		return get_option( $this->paypal_settings_key, array() );
	}

	/**
	 * Write the main PayPal gateway settings.
	 *
	 * @param array $settings
	 * @return bool
	 */
	protected function save_paypal_settings( $settings ) {
		return update_option( $this->paypal_settings_key, $settings );
	}

	/**
	 * Read the credit card gateway settings.
	 *
	 * @return array
	 */
	protected function get_cc_settings() {
		return get_option( $this->cc_settings_key, array() );
	}

	/**
	 * Write the credit card gateway settings.
	 *
	 * @param array $settings
	 * @return bool
	 */
	protected function save_cc_settings( $settings ) {
		return update_option( $this->cc_settings_key, $settings );
	}

	/**
	 * Add a default value to settings only if the key does not already exist.
	 *
	 * @param array  $settings  Settings array (passed by reference).
	 * @param string $key
	 * @param mixed  $default
	 */
	protected function add_default( &$settings, $key, $default ) {
		if ( ! array_key_exists( $key, $settings ) ) {
			$settings[ $key ] = $default;
		}
	}
}
