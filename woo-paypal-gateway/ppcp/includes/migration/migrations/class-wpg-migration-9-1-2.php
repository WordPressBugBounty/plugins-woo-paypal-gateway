<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration to 9.1.2.
 *
 * Upgrades the 3D Secure liability-shift handling default from the original,
 * unprotected "accept" behavior to the recommended "smart" mode.
 *
 * "accept" was only ever a silent, backward-compatible default: it captures card
 * payments even when the shopper failed the card issuer's authentication challenge —
 * the classic stolen-card signal behind fraud chargebacks. "smart" blocks only those
 * failed/rejected bank authentications (letting the shopper retry) while still
 * processing every other payment, so it removes the chargeback exposure without
 * declining legitimate customers.
 *
 * Merchants who deliberately chose "review" or "reject" are left untouched.
 */
class WPG_Migration_9_1_2 extends WPG_Migration_Base {

	public function get_version() {
		return '9.1.2';
	}

	public function get_description() {
		return 'Upgrade 3D Secure handling default from "accept" to "smart" for chargeback protection.';
	}

	public function requires_woocommerce() {
		return false;
	}

	public function up() {
		$settings = $this->get_paypal_settings();

		// No PayPal settings yet — nothing to upgrade. The runtime default already
		// resolves to "smart" for such installs.
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return true;
		}

		$current = isset( $settings['3ds_liability_handling'] ) ? $settings['3ds_liability_handling'] : '';

		// Only move the insecure/silent default forward. Preserve deliberate choices.
		if ( '' === $current || 'accept' === $current ) {
			$settings['3ds_liability_handling'] = 'smart';
			$this->save_paypal_settings( $settings );
		}

		return true;
	}

	public function verify() {
		$settings = $this->get_paypal_settings();

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return true;
		}

		$value = isset( $settings['3ds_liability_handling'] ) ? $settings['3ds_liability_handling'] : '';

		// After this migration the value must never be the unprotected default.
		return '' !== $value && 'accept' !== $value;
	}
}
