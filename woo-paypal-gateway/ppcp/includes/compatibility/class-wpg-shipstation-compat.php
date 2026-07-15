<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce ShipStation compatibility.
 *
 * When the official WooCommerce ShipStation Gateway plugin posts a shipment
 * notification, push the tracking number + carrier to PayPal so it is attached
 * to the transaction. This improves PayPal Seller Protection and dispute
 * posture. The actual PayPal call is delegated to the existing tracking
 * pipeline (PPCP_Paypal_Checkout_For_Woocommerce_Tracking::handle_notification).
 */
class WPG_ShipStation_Compat {

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

		// Fired by the WooCommerce ShipStation Gateway plugin as
		// do_action( 'woocommerce_shipstation_shipnotify', $order, $args ).
		add_action( 'woocommerce_shipstation_shipnotify', array( $this, 'handle_shipnotify' ), 10, 2 );
	}

	private function is_active() {
		return function_exists( 'woocommerce_shipstation_init' ) || class_exists( 'WC_ShipStation_Integration', false );
	}

	/**
	 * Relay a ShipStation shipment notification to PayPal.
	 *
	 * @param WC_Order $order The order being shipped.
	 * @param array    $args  Tracking args: tracking_number, carrier, ship_date.
	 */
	public function handle_shipnotify( $order, $args ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Tracking' ) ) {
			return;
		}
		// Only relay orders paid through our PayPal gateways.
		$payment_method = $order->get_payment_method();
		$supported      = apply_filters(
			'wpg_ppcp_tracking_supported_gateways',
			array( 'wpg_paypal_checkout', 'wpg_paypal_checkout_cc' )
		);
		if ( ! in_array( $payment_method, $supported, true ) ) {
			return;
		}

		PPCP_Paypal_Checkout_For_Woocommerce_Tracking::get_instance()->handle_notification( $order, $args );
	}
}
