<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Germanized compatibility.
 *
 * Handles EU compliance requirements when WooCommerce Germanized is active:
 * - Validates legal checkboxes (terms, revocation, data privacy) during express checkout
 * - Renders legal checkboxes on the PayPal order review page
 * - Adjusts button labels for EU compliance
 * - Supports both Germanized free and Pro
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

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_legal_checkboxes' ), 1, 2 );
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_legal_checkboxes_on_review' ), 5 );
		add_filter( 'wpg_ppcp_checkout_button_label', array( $this, 'get_eu_compliant_label' ) );
		add_filter( 'wpg_ppcp_localize_script_data', array( $this, 'add_germanized_js_data' ) );
	}

	private function is_germanized_active() {
		return function_exists( 'wc_gzd_get_hook_priority' ) || class_exists( 'WooCommerce_Germanized', false );
	}

	/**
	 * Validate Germanized legal checkboxes during checkout processing.
	 *
	 * When PayPal express checkout bypasses the standard checkout form,
	 * Germanized's checkboxes are never rendered or validated. This hooks
	 * into checkout validation to ensure legal compliance.
	 *
	 * @param array    $data   Checkout posted data.
	 * @param WP_Error $errors Validation errors.
	 */
	public function validate_legal_checkboxes( $data, $errors ) {
		if ( ! $this->is_express_checkout_flow() ) {
			return;
		}

		if ( ! class_exists( 'WC_GZD_Legal_Checkbox_Manager' ) ) {
			return;
		}

		$manager = WC_GZD_Legal_Checkbox_Manager::instance();

		if ( ! method_exists( $manager, 'validate_checkout' ) ) {
			return;
		}

		$manager->validate_checkout( $_POST, $errors );
	}

	/**
	 * Render Germanized legal checkboxes on the PayPal order review page.
	 *
	 * When a customer is redirected from PayPal back to the review page,
	 * they need to accept legal terms before the order is placed.
	 */
	public function render_legal_checkboxes_on_review() {
		if ( ! $this->is_order_review_page() ) {
			return;
		}

		if ( ! class_exists( 'WC_GZD_Legal_Checkbox_Manager' ) ) {
			return;
		}

		$manager    = WC_GZD_Legal_Checkbox_Manager::instance();
		$checkboxes = $manager->get_checkboxes( array(
			'locations' => 'checkout',
			'is_shown'  => true,
		) );

		if ( empty( $checkboxes ) ) {
			return;
		}

		echo '<div class="wpg-germanized-legal-checkboxes">';
		foreach ( $checkboxes as $checkbox ) {
			if ( method_exists( $checkbox, 'render' ) ) {
				$checkbox->render();
			}
		}
		echo '</div>';
	}

	/**
	 * Get EU-compliant button label.
	 *
	 * EU consumer protection law requires the purchase button to clearly
	 * indicate a binding financial obligation. Germanized provides
	 * translated labels for this.
	 *
	 * @param string $label Default button label.
	 * @return string EU-compliant label.
	 */
	public function get_eu_compliant_label( $label ) {
		if ( function_exists( 'wc_gzd_get_legal_text_pay_now_button' ) ) {
			$gzd_label = wc_gzd_get_legal_text_pay_now_button();
			if ( ! empty( $gzd_label ) ) {
				return $gzd_label;
			}
		}

		return $label;
	}

	/**
	 * Add Germanized-specific data to the localized JS object.
	 *
	 * @param array $data Localized script data.
	 * @return array Modified data.
	 */
	public function add_germanized_js_data( $data ) {
		$data['is_germanized_active'] = 'yes';

		if ( function_exists( 'wc_gzd_get_legal_text_pay_now_button' ) ) {
			$data['gzd_pay_now_label'] = wc_gzd_get_legal_text_pay_now_button();
		}

		return $data;
	}

	/**
	 * Check if the current checkout is an express checkout flow.
	 *
	 * Express checkout flows bypass the standard checkout form, so
	 * Germanized's checkboxes may not be present in $_POST.
	 *
	 * @return bool
	 */
	private function is_express_checkout_flow() {
		return function_exists( 'ppcp_has_active_session' ) && ppcp_has_active_session();
	}

	/**
	 * Check if the current page is the PayPal order review page.
	 *
	 * @return bool
	 */
	private function is_order_review_page() {
		return is_checkout() && ! empty( WC()->session ) && WC()->session->get( 'ppcp_order_id' );
	}
}
