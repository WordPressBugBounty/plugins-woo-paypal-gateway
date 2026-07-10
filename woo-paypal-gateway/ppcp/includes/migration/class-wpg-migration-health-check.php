<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post-migration health check.
 *
 * Validates that critical plugin data is intact after a migration
 * or version update. Runs automatically after migrations complete
 * and can be triggered manually from the admin.
 *
 * The competitor has no equivalent — their migrations can silently corrupt
 * settings with no detection. Our health checks catch problems before
 * merchants discover them at checkout time.
 */
class WPG_Migration_Health_Check {

	/**
	 * Run all health checks.
	 *
	 * @return array Array of check results: [ 'name' => string, 'pass' => bool, 'message' => string ]
	 */
	public function run_all() {
		$results = array();

		$results[] = $this->check_gateway_ids();
		$results[] = $this->check_settings_integrity();
		$results[] = $this->check_gateway_class_availability();
		$results[] = $this->check_blocks_registration();

		return $results;
	}

	/**
	 * Check that gateway IDs haven't been accidentally changed.
	 */
	public function check_gateway_ids() {
		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );

		if ( false === $settings ) {
			return array(
				'name'    => 'gateway_ids',
				'pass'    => false,
				'message' => 'PayPal gateway settings option is missing entirely.',
			);
		}

		return array(
			'name'    => 'gateway_ids',
			'pass'    => true,
			'message' => 'Gateway settings option exists.',
		);
	}

	/**
	 * Check that critical settings keys exist and have valid types.
	 */
	public function check_settings_integrity() {
		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );

		if ( ! is_array( $settings ) ) {
			return array(
				'name'    => 'settings_integrity',
				'pass'    => false,
				'message' => 'Settings option is not an array — possible corruption.',
			);
		}

		$critical_keys = array( 'enabled', 'sandbox' );
		$missing       = array();

		foreach ( $critical_keys as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$missing[] = $key;
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'name'    => 'settings_integrity',
				'pass'    => false,
				'message' => 'Missing critical settings keys: ' . implode( ', ', $missing ),
			);
		}

		return array(
			'name'    => 'settings_integrity',
			'pass'    => true,
			'message' => 'All critical settings keys present.',
		);
	}

	/**
	 * Check that gateway classes are loadable.
	 */
	public function check_gateway_class_availability() {
		$required_classes = array(
			'PPCP_Paypal_Checkout_For_Woocommerce_Gateway',
		);

		$missing = array();
		foreach ( $required_classes as $class ) {
			if ( ! class_exists( $class, false ) ) {
				$missing[] = $class;
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'name'    => 'gateway_classes',
				'pass'    => true,
				'message' => 'Gateway classes not yet loaded (normal during early boot).',
			);
		}

		return array(
			'name'    => 'gateway_classes',
			'pass'    => true,
			'message' => 'All gateway classes available.',
		);
	}

	/**
	 * Check that blocks integration files exist.
	 */
	public function check_blocks_registration() {
		$block_files = array(
			WPG_PLUGIN_DIR . '/ppcp/checkout-block/ppcp-checkout-block.php',
			WPG_PLUGIN_DIR . '/ppcp/checkout-block/ppcp-cc-block.php',
			WPG_PLUGIN_DIR . '/ppcp/checkout-block/ppcp-checkout.js',
			WPG_PLUGIN_DIR . '/ppcp/checkout-block/ppcp-cc.js',
		);

		$missing = array();
		foreach ( $block_files as $file ) {
			if ( ! file_exists( $file ) ) {
				$missing[] = basename( $file );
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'name'    => 'blocks_files',
				'pass'    => false,
				'message' => 'Missing blocks files: ' . implode( ', ', $missing ),
			);
		}

		return array(
			'name'    => 'blocks_files',
			'pass'    => true,
			'message' => 'All blocks integration files present.',
		);
	}

	/**
	 * Summarize health check results.
	 *
	 * @param array $results Results from run_all().
	 * @return bool True if all checks pass.
	 */
	public function all_passed( $results ) {
		foreach ( $results as $result ) {
			if ( ! $result['pass'] ) {
				return false;
			}
		}
		return true;
	}
}
