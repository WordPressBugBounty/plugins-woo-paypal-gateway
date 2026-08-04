<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Germanized compatibility.
 *
 * Handles EU compliance requirements when WooCommerce Germanized is active:
 *
 * - Forces the PayPal order-review step for express flows so Germanized's
 *   legal checkboxes (terms, revocation, data privacy) are rendered — by
 *   Germanized itself, on the checkout form — and validated by Germanized's
 *   own woocommerce_after_checkout_validation hook when the buyer places the
 *   order. Skipping straight from the PayPal popup to capture would complete
 *   purchases without any legal-checkbox acceptance, because no checkout form
 *   ever exists in that flow. (ppcp's classic flows already validate natively
 *   because the full checkout form is posted, so the review step is the only
 *   gap.)
 * - Applies Germanized's Button-Loesung pay-now wording (the
 *   woocommerce_gzd_order_submit_btn_text option) to the plugin's place-order
 *   button labels for EU-compliant purchase buttons.
 * - Supports both Germanized free and Pro.
 */
class WPG_Germanized_Compat {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( ! $this->is_germanized_active() ) {
			return;
		}

		add_filter( 'wpg_ppcp_skip_order_review', array( $this, 'maybe_force_order_review' ) );
		add_filter( 'wpg_ppcp_checkout_button_label', array( $this, 'get_eu_compliant_label' ) );
	}

	private function is_germanized_active() {
		return function_exists( 'wc_gzd_get_hook_priority' ) || class_exists( 'WooCommerce_Germanized', false );
	}

	/**
	 * Never skip the order review step while Germanized has active checkout
	 * checkboxes: the review page is the only express surface where the
	 * checkout form (and therefore Germanized's legal checkboxes) exists.
	 * Germanized renders and validates the checkboxes itself once the buyer
	 * is on that form — no duplicate rendering or validation needed here.
	 *
	 * @param bool $skip Whether the review step would be skipped.
	 * @return bool
	 */
	public function maybe_force_order_review( $skip ) {
		if ( ! $skip ) {
			return $skip;
		}

		// Checkbox registration happens on init; before that, err on the side
		// of showing the review step rather than skipping legal acceptance.
		if ( did_action( 'init' ) && ! $this->has_active_checkout_checkboxes() ) {
			return $skip;
		}

		return false;
	}

	/**
	 * Whether Germanized has any checkout legal checkbox enabled.
	 *
	 * @return bool
	 */
	private function has_active_checkout_checkboxes() {
		if ( ! class_exists( 'WC_GZD_Legal_Checkbox_Manager' ) ) {
			return false;
		}

		$manager = WC_GZD_Legal_Checkbox_Manager::instance();

		if ( ! method_exists( $manager, 'get_checkboxes' ) ) {
			return false;
		}

		$checkboxes = $manager->get_checkboxes( array(
			'locations' => 'checkout',
			'is_shown'  => true,
		) );

		return ! empty( $checkboxes );
	}

	/**
	 * Get EU-compliant button label.
	 *
	 * EU consumer protection law requires the purchase button to clearly
	 * indicate a binding financial obligation (Button-Loesung). Germanized
	 * stores its uniform submit-button wording in the
	 * woocommerce_gzd_order_submit_btn_text option and applies it to every
	 * gateway's order button; mirror that here for the plugin's own labels.
	 * (An earlier revision called wc_gzd_get_legal_text_pay_now_button(),
	 * which does not exist in any Germanized release — the option below is
	 * Germanized's real API.)
	 *
	 * @param string $label Default button label.
	 * @return string EU-compliant label.
	 */
	public function get_eu_compliant_label( $label ) {
		$gzd_label = get_option( 'woocommerce_gzd_order_submit_btn_text' );

		if ( empty( $gzd_label ) ) {
			// Germanized's own default for the submit-button wording.
			// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Deliberately reuses Germanized's translation so the label matches the rest of the checkout.
			$gzd_label = __( 'Buy Now', 'woocommerce-germanized' );
		}

		return ! empty( $gzd_label ) ? $gzd_label : $label;
	}
}
