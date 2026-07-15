<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mondial Relay compatibility.
 *
 * Mondial Relay requires the customer to pick a Point Relais® pickup location.
 * That selection is not part of the standard WooCommerce address, so when a
 * customer pays with PayPal (including express) using a Mondial Relay shipping
 * method, we must ensure a relay point has been chosen before the order can be
 * completed. This class adds that validation guard.
 */
class WPG_Mondial_Relay_Compat {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_relay_point' ), 10, 2 );
	}

	private function is_active() {
		return function_exists( 'MRWP_plugin_updater' )
			|| class_exists( 'MRWP_Shipping_Method', false )
			|| class_exists( 'Mr_Shipping_Method', false );
	}

	/**
	 * Block checkout when a Mondial Relay method is selected without a relay point.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Errors object.
	 */
	public function validate_relay_point( $data, $errors ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}
		if ( ! $this->is_mondial_relay_selected() ) {
			return;
		}
		if ( $this->has_relay_point_selected() ) {
			return;
		}

		$errors->add(
			'wpg_mondial_relay_point',
			__( 'Please choose a Point Relais® pickup location before placing your order.', 'woo-paypal-gateway' )
		);
	}

	/**
	 * Whether the chosen shipping method is a Mondial Relay method.
	 *
	 * @return bool
	 */
	private function is_mondial_relay_selected() {
		$chosen = function_exists( 'wc_get_chosen_shipping_method_ids' ) ? wc_get_chosen_shipping_method_ids() : array();
		$method_ids = array( 'mondial_relay', 'mondialrelay' );
		if ( class_exists( 'MRWP_Shipping_Method', false ) && isset( MRWP_Shipping_Method::$method_id ) ) {
			$method_ids[] = MRWP_Shipping_Method::$method_id;
		}
		$method_ids = apply_filters( 'wpg_ppcp_mondial_relay_method_ids', $method_ids );

		foreach ( (array) $chosen as $chosen_id ) {
			foreach ( $method_ids as $method_id ) {
				if ( false !== strpos( $chosen_id, $method_id ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a relay pickup point has been selected.
	 *
	 * Detection is filterable because different Mondial Relay plugins persist the
	 * chosen point under different session / request keys.
	 *
	 * @return bool
	 */
	private function has_relay_point_selected() {
		$selected = false;

		$post_keys = array( 'mondialrelay_current_relay', 'mondial_relay_id', 'mrwp_relay_id', 'relay_id' );
		foreach ( $post_keys as $key ) {
			if ( ! empty( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$selected = true;
				break;
			}
		}

		if ( ! $selected && function_exists( 'WC' ) && WC()->session ) {
			$session_keys = array( 'mondialrelay_relay', 'mondial_relay_point', 'mrwp_relay', 'mondialrelay_current_relay' );
			foreach ( $session_keys as $key ) {
				if ( ! empty( WC()->session->get( $key ) ) ) {
					$selected = true;
					break;
				}
			}
		}

		return (bool) apply_filters( 'wpg_ppcp_mondial_relay_selected', $selected );
	}
}
