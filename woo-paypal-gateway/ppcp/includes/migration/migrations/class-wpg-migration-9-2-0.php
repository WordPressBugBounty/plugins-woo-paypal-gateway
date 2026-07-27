<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration to 9.2.0.
 *
 * Fastlane by PayPal is auto-enabled for stores that complete PayPal onboarding
 * from 9.2.0 onward (when their account is approved for FASTLANE_CHECKOUT). That
 * default is keyed off the absence of the 'enable_fastlane' setting, so without
 * this migration every existing merchant — who by definition never had the
 * Fastlane setting saved — would also have it switched on automatically on their
 * next admin load.
 *
 * The requirement is that Fastlane stays a manual opt-in for existing merchants.
 * This migration records an explicit 'enable_fastlane' => 'no' (and a matching
 * 'fastlane_pageload' => 'no') for any already-configured install that has not
 * chosen a value yet, so the onboarding-time auto-enable skips them. New installs
 * skip all migrations (see WPG_Migration_Registry) and therefore still receive
 * the enabled-by-default behavior.
 */
class WPG_Migration_9_2_0 extends WPG_Migration_Base {

	public function get_version() {
		return '9.2.0';
	}

	public function get_description() {
		return 'Keep Fastlane by PayPal off for existing stores; auto-enable applies to new onboardings only.';
	}

	public function requires_woocommerce() {
		return false;
	}

	public function up() {
		$settings = $this->get_paypal_settings();

		// No PayPal settings yet means this is not an established install; nothing
		// to grandfather. (Genuine fresh installs never reach migrations at all.)
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return true;
		}

		$changed = false;

		// Only seed a value where the merchant has not already made a choice.
		// Preserve any explicit setting so re-running this migration is a no-op.
		if ( ! array_key_exists( 'enable_fastlane', $settings ) ) {
			$settings['enable_fastlane'] = 'no';
			$changed                     = true;
		}

		if ( ! array_key_exists( 'fastlane_pageload', $settings ) ) {
			$settings['fastlane_pageload'] = 'no';
			$changed                       = true;
		}

		if ( $changed ) {
			$this->save_paypal_settings( $settings );
		}

		return true;
	}

	public function verify() {
		$settings = $this->get_paypal_settings();

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return true;
		}

		// After this migration an established install must carry an explicit
		// Fastlane enable value, so the onboarding auto-enable never fires for it.
		return array_key_exists( 'enable_fastlane', $settings );
	}
}
