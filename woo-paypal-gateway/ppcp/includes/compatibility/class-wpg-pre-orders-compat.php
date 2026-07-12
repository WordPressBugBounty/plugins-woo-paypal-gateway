<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Pre-Orders compatibility.
 *
 * Handles payment flow differences for pre-order products:
 * - "Charge upon release" requires vaulting the payment method
 * - "Charge upfront" works with standard payment capture
 * - Processes the deferred payment when the pre-order is released
 */
class WPG_Pre_Orders_Compat {

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

		add_filter( 'wpg_ppcp_vault_required', array( $this, 'maybe_require_vault' ) );
		add_filter( 'wpg_ppcp_payment_intent', array( $this, 'maybe_change_intent' ), 10, 2 );
		add_action( 'wc_pre_orders_process_pre_order_completion_payment_wpg_paypal_checkout', array( $this, 'process_pre_order_payment' ) );
		add_filter( 'wpg_ppcp_localize_script_data', array( $this, 'add_pre_order_js_data' ) );
		add_filter( 'wc_pre_orders_supported_payment_gateways', array( $this, 'declare_support' ) );
	}

	private function is_active() {
		return class_exists( 'WC_Pre_Orders', false );
	}

	/**
	 * Force vault when cart contains a charge-upon-release pre-order.
	 */
	public function maybe_require_vault( $required ) {
		if ( $required ) {
			return true;
		}

		if ( $this->cart_has_charge_upon_release() ) {
			return true;
		}

		return false;
	}

	/**
	 * For charge-upon-release, we don't capture now — we just vault the method.
	 */
	public function maybe_change_intent( $intent, $order ) {
		if ( ! $order ) {
			return $intent;
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
			if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order ) ) {
				return 'authorize';
			}
		}

		return $intent;
	}

	/**
	 * Process the deferred payment when a pre-order is released.
	 *
	 * Reuses the plugin's Request class for API calls, ensuring consistent
	 * authentication, logging, and intent (CAPTURE vs AUTHORIZE) handling.
	 *
	 * @param WC_Order $order The pre-order being released.
	 */
	public function process_pre_order_payment( $order ) {
		$vault_token = $order->get_meta( '_payment_tokens_id', true );

		if ( empty( $vault_token ) ) {
			$order->update_status( 'failed', __( 'Pre-order payment failed: no saved payment token found.', 'woo-paypal-gateway' ) );
			return;
		}

		try {
			if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Request' ) ) {
				include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
			}

			$request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
			$result = $request->wpg_ppcp_capture_order_using_payment_method_token( $order->get_id() );
			if ( $result !== true ) {
				$order = wc_get_order( $order->get_id() );
				if ( $order && ! in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
					$order->update_status( 'failed', __( 'Pre-order payment capture failed.', 'woo-paypal-gateway' ) );
				}
			}
		} catch ( \Throwable $e ) {
			$order->update_status( 'failed', sprintf(
				/* translators: %s: error message */
				__( 'Pre-order payment failed: %s', 'woo-paypal-gateway' ),
				$e->getMessage()
			) );
		}
	}

	public function add_pre_order_js_data( $data ) {
		if ( $this->cart_has_charge_upon_release() ) {
			$data['is_pre_order_charge_upon_release'] = 'yes';
		}
		return $data;
	}

	public function declare_support( $gateways ) {
		$gateways[] = 'wpg_paypal_checkout';
		return $gateways;
	}

	private function cart_has_charge_upon_release() {
		if ( ! class_exists( 'WC_Pre_Orders_Cart' ) || ! method_exists( 'WC_Pre_Orders_Cart', 'cart_contains_pre_order' ) ) {
			return false;
		}

		if ( ! WC_Pre_Orders_Cart::cart_contains_pre_order() ) {
			return false;
		}

		return class_exists( 'WC_Pre_Orders_Product' )
			&& method_exists( 'WC_Pre_Orders_Product', 'product_is_charged_upon_release' )
			&& WC_Pre_Orders_Product::product_is_charged_upon_release( WC_Pre_Orders_Cart::get_pre_order_product() );
	}
}
