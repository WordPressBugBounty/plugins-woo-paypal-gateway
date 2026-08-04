<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Pre-Orders compatibility.
 *
 * Handles payment flow differences for pre-order products:
 *
 * - "Charge upon release" orders carry their FULL total (WC Pre-Orders only
 *   reformats the displayed total), but must never be charged at checkout.
 *   The gateways route them into the setup-token vault flow (see
 *   woo_paypal_gateway_ppcp_order_requires_preorder_tokenization()), which
 *   stores a token and calls WC_Pre_Orders_Order::mark_order_as_pre_ordered()
 *   instead of charging.
 * - Express smart buttons are hidden and express order creation is blocked
 *   for such carts, because the wallet flow can only create a CHARGING PayPal
 *   order.
 * - When WC Pre-Orders releases the pre-order, the completion hook charges
 *   the stored token with the merchant's configured payment action — no
 *   intent override (an earlier revision here forced AUTHORIZE, which
 *   dead-ended capture-mode stores).
 * - "Charge upfront" pre-orders flow through the standard payment path.
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
		add_filter( 'wpg_ppcp_hide_express_buttons', array( $this, 'maybe_hide_express_buttons' ), 10, 2 );
		add_action( 'woocommerce_api_' . strtolower( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ), array( $this, 'maybe_block_express_order_creation' ), 5 );
		add_action( 'wc_pre_orders_process_pre_order_completion_payment_wpg_paypal_checkout', array( $this, 'process_pre_order_payment' ) );
		add_action( 'wc_pre_orders_process_pre_order_completion_payment_wpg_paypal_checkout_cc', array( $this, 'process_pre_order_payment' ) );
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
	 * Hide express smart buttons for charge-upon-release contexts: the wallet
	 * flow creates a charging PayPal order, which must not happen for orders
	 * that may only be tokenized. The classic checkout routes these orders
	 * into the vault flow instead.
	 *
	 * @param bool   $hide    Current decision.
	 * @param string $context Button context (product|cart|mini_cart|express_checkout).
	 * @return bool
	 */
	public function maybe_hide_express_buttons( $hide, $context ) {
		if ( $hide ) {
			return $hide;
		}

		if ( 'product' === $context && $this->current_product_is_charged_upon_release() ) {
			return true;
		}

		return $this->cart_has_charge_upon_release();
	}

	/**
	 * Hard server-side guard behind the hidden buttons: refuse express PayPal
	 * order creation while the cart contains a charge-upon-release pre-order.
	 * Runs before the button manager's own wc-api handler.
	 */
	public function maybe_block_express_order_creation() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing check; the blocked handler performs its own verification.
		$ppcp_action = isset( $_GET['ppcp_action'] ) ? sanitize_text_field( wp_unslash( $_GET['ppcp_action'] ) ) : '';
		if ( 'create_order' !== $ppcp_action ) {
			return;
		}
		if ( ! $this->cart_has_charge_upon_release() ) {
			return;
		}
		wp_send_json_error( array(
			'messages' => array(
				__( 'This pre-order will be charged when it is released, so express checkout is not available. Please place your order through the regular checkout form.', 'woo-paypal-gateway' ),
			),
		) );
	}

	/**
	 * Process the deferred payment when a pre-order is released.
	 *
	 * Reuses the plugin's Request class for API calls, ensuring consistent
	 * authentication, logging, and intent (CAPTURE vs AUTHORIZE) handling —
	 * the merchant's configured payment action applies.
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

	public function declare_support( $gateways ) {
		$gateways[] = 'wpg_paypal_checkout';
		$gateways[] = 'wpg_paypal_checkout_cc';
		return $gateways;
	}

	/**
	 * Whether the product on the current single-product page is charged upon
	 * release.
	 *
	 * @return bool
	 */
	private function current_product_is_charged_upon_release() {
		if ( ! class_exists( 'WC_Pre_Orders_Product' ) || ! method_exists( 'WC_Pre_Orders_Product', 'product_is_charged_upon_release' ) ) {
			return false;
		}
		global $product;
		$current = $product instanceof WC_Product ? $product : ( function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : false );

		return $current instanceof WC_Product && WC_Pre_Orders_Product::product_is_charged_upon_release( $current );
	}

	private function cart_has_charge_upon_release() {
		if ( ! class_exists( 'WC_Pre_Orders_Cart' ) || ! method_exists( 'WC_Pre_Orders_Cart', 'cart_contains_pre_order' ) ) {
			return false;
		}

		if ( ! function_exists( 'WC' ) || empty( WC()->cart ) ) {
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
