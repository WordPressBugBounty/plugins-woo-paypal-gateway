<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration to 9.1.0.
 *
 * First migration using the new registry system. Handles:
 * - Seeds default values for new features (all disabled by default for backward compat)
 * - Migrates the ad-hoc flag-based migrations into the formal system
 * - Ensures existing installs have all expected settings keys
 */
class WPG_Migration_9_1_0 extends WPG_Migration_Base {

	public function get_version() {
		return '9.1.0';
	}

	public function get_description() {
		return 'Initialize migration system and seed defaults for new features.';
	}

	public function requires_woocommerce() {
		return false;
	}

	public function up() {
		$this->seed_paypal_defaults();
		$this->seed_cc_defaults();
		$this->mark_adhoc_migrations_done();

		return true;
	}

	public function verify() {
		$settings = $this->get_paypal_settings();

		if ( ! is_array( $settings ) ) {
			return false;
		}

		if ( empty( $settings ) ) {
			return true;
		}

		return array_key_exists( 'recaptcha_enabled', $settings );
	}

	/**
	 * Add default values for new features to PayPal settings.
	 *
	 * Only adds keys that don't already exist — never overwrites merchant choices.
	 */
	private function seed_paypal_defaults() {
		$settings = $this->get_paypal_settings();

		if ( empty( $settings ) ) {
			return;
		}

		$this->add_default( $settings, 'recaptcha_enabled', 'no' );
		$this->add_default( $settings, 'recaptcha_site_key', '' );
		$this->add_default( $settings, 'recaptcha_secret_key', '' );
		$this->add_default( $settings, 'recaptcha_threshold', '0.5' );
		$this->add_default( $settings, 'recaptcha_skip_logged_in', 'no' );

		$this->add_default( $settings, 'minicart_enabled', 'no' );
		$this->add_default( $settings, 'minicart_gpay_enabled', 'no' );
		$this->add_default( $settings, 'minicart_applepay_enabled', 'no' );

		$this->add_default( $settings, 'cache_enabled', 'yes' );

		$this->add_default( $settings, 'vault_v3_enabled', 'no' );

		$this->save_paypal_settings( $settings );
	}

	/**
	 * Add default values for new CC gateway features.
	 */
	private function seed_cc_defaults() {
		$settings = $this->get_cc_settings();

		if ( empty( $settings ) ) {
			return;
		}

		$this->add_default( $settings, '3ds_liability_handling', 'accept' );
		$this->add_default( $settings, '3ds_logging', 'no' );

		$this->save_cc_settings( $settings );
	}

	/**
	 * Mark the old ad-hoc migrations as complete so they don't re-run.
	 *
	 * The gateway constructor checks these flags on every instantiation.
	 * We ensure they're set so the old migration code becomes a no-op,
	 * and future versions can safely remove those methods.
	 */
	private function mark_adhoc_migrations_done() {
		if ( ! get_option( '_new_version_description_icon_type_redirect_icon_applied', false ) ) {
			update_option( '_new_version_description_icon_type_redirect_icon_applied', 'yes' );
		}
		if ( ! get_option( '_wpg_button_pages_migrated', false ) ) {
			update_option( '_wpg_button_pages_migrated', true );
		}
	}
}
