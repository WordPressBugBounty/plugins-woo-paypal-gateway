<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CheckoutWC Order Bumps / one-click upsell compatibility.
 *
 * CheckoutWC's post-purchase "one-click" order bumps charge an additional
 * product after the main order is placed, reusing the customer's stored payment
 * method. This class:
 *   1. advertises our PayPal gateways as one-click capable (only when vaulting
 *      is enabled) via the `cfw_one_click_supported_gateways` filter,
 *   2. forces the vault token to be saved during the initial checkout when
 *      post-purchase bumps are present, and
 *   3. charges / refunds a bump against the saved PayPal vault token, reusing
 *      the same off-session token-capture path as subscription renewals and the
 *      FunnelKit upsell bridge.
 *
 * Because CheckoutWC is a premium plugin, the exact one-click interface can vary
 * by version; every callback is guarded defensively.
 */
class WPG_CheckoutWC_Order_Bumps_Compat {

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

		add_filter( 'cfw_one_click_supported_gateways', array( $this, 'add_supported_gateways' ) );
		// Ensure a reusable token is stored when post-purchase bumps exist.
		add_filter( 'wpg_ppcp_vault_required', array( $this, 'maybe_force_vault' ), 10, 1 );
	}

	private function is_active() {
		return defined( 'CFW_NAME' ) || function_exists( 'cfw_is_checkout' ) || class_exists( 'Objectiv\Plugins\Checkout\Main', false );
	}

	/**
	 * Register our gateways with CheckoutWC's one-click engine.
	 *
	 * @param array $gateways Existing supported gateways keyed by gateway id.
	 * @return array
	 */
	public function add_supported_gateways( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			$gateways = array();
		}
		foreach ( array( 'wpg_paypal_checkout', 'wpg_paypal_checkout_cc' ) as $gateway_id ) {
			$gateways[ $gateway_id ] = array(
				'path'  => __FILE__,
				'class' => __CLASS__,
			);
		}
		return $gateways;
	}

	/**
	 * Force vaulting during initial checkout when a post-purchase one-click bump
	 * is present, so it can be charged later without re-authentication.
	 *
	 * @param bool $required Current requirement.
	 * @return bool
	 */
	public function maybe_force_vault( $required ) {
		if ( $required ) {
			return true;
		}
		return $this->has_post_purchase_bumps();
	}

	/**
	 * Whether any published, displayable post-purchase one-click bump exists.
	 *
	 * @return bool
	 */
	private function has_post_purchase_bumps() {
		if ( ! class_exists( '\Objectiv\Plugins\Checkout\Factories\BumpFactory', false ) ) {
			return false;
		}
		try {
			$bumps = \Objectiv\Plugins\Checkout\Factories\BumpFactory::get_all();
			if ( empty( $bumps ) ) {
				return false;
			}
			foreach ( $bumps as $bump ) {
				if ( ! is_object( $bump ) || ! method_exists( $bump, 'get_display_location' ) ) {
					continue;
				}
				if ( 'post_purchase_one_click' !== $bump->get_display_location() ) {
					continue;
				}
				$displayable = ! method_exists( $bump, 'is_displayable' ) || $bump->is_displayable();
				$published   = ! method_exists( $bump, 'is_published' ) || $bump->is_published();
				if ( $displayable && $published ) {
					return true;
				}
			}
		} catch ( Exception $ex ) {
			return false;
		}
		return false;
	}

	/**
	 * Charge a one-click order bump against the customer's saved PayPal token.
	 *
	 * CheckoutWC calls this with the bump order (its total reflects the bump
	 * amount) and the bump product data.
	 *
	 * @param WC_Order $order   The bump/upsell order to charge.
	 * @param array    $product Bump product data (may include a bump id).
	 * @return bool True on success.
	 */
	public function process_offer_payment( $order, $product = array() ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Request' ) ) {
			return false;
		}

		$this->ensure_token_on_order( $order );

		// CheckoutWC hands us the parent order (for its saved payment method)
		// plus the bump's own price data. Charge ONLY the bump amount — charging
		// the order object's totals would bill the buyer's whole order again.
		$bump_total = isset( $product['price'] ) ? (float) $product['price'] : 0;
		if ( $bump_total <= 0 ) {
			$order->add_order_note( __( 'Order bump charge skipped: no bump price was supplied, so the charge could not be scoped to the bump amount.', 'woo-paypal-gateway' ) );
			return false;
		}
		$bump_product_id = ! empty( $product['variation_id'] ) ? $product['variation_id'] : ( isset( $product['id'] ) ? $product['id'] : 0 );
		$bump_product    = $bump_product_id ? wc_get_product( $bump_product_id ) : false;

		$request    = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
		$invoice_id = '-cfw-bump-' . uniqid() . '-';
		$result     = $request->wpg_ppcp_capture_order_using_payment_method_token(
			$order->get_id(),
			$invoice_id,
			array(
				'total'       => $bump_total,
				'description' => $bump_product ? $bump_product->get_name() : __( 'Order bump', 'woo-paypal-gateway' ),
			)
		);

		if ( $result ) {
			$bump_id = isset( $product['bump_id'] ) ? $product['bump_id'] : ( isset( $product['id'] ) ? $product['id'] : 'bump' );
			$txn_id  = is_string( $result ) ? $result : $order->get_transaction_id();
			$order->update_meta_data( 'cfw_offer_txn_resp_' . sanitize_key( $bump_id ), $txn_id );
			$order->save();
			return true;
		}
		return false;
	}

	/**
	 * Refund a previously-charged one-click order bump.
	 *
	 * @param WC_Order $order      The order.
	 * @param array    $offer_data Must contain transaction_id and refund_amount.
	 * @return bool
	 */
	public function process_offer_refund( $order, $offer_data = array() ) {
		if ( ! $order instanceof WC_Order || empty( $offer_data['transaction_id'] ) ) {
			return false;
		}
		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Request' ) ) {
			return false;
		}
		$request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
		if ( ! method_exists( $request, 'ppcp_refund_order' ) ) {
			return false;
		}
		$amount = isset( $offer_data['refund_amount'] ) ? $offer_data['refund_amount'] : null;
		$reason = __( 'CheckoutWC order bump refund', 'woo-paypal-gateway' );
		return (bool) $request->ppcp_refund_order( $order->get_id(), $amount, $reason, $offer_data['transaction_id'] );
	}

	/**
	 * Copy a usable vault token onto the bump order when it is missing, sourcing
	 * it from the parent order or the customer's saved tokens.
	 *
	 * @param WC_Order $order Bump order.
	 */
	private function ensure_token_on_order( $order ) {
		if ( ! empty( $order->get_meta( '_payment_tokens_id' ) ) ) {
			return;
		}
		$parent_id = (int) $order->get_parent_id();
		if ( $parent_id > 0 ) {
			$parent = wc_get_order( $parent_id );
			if ( $parent instanceof WC_Order && ! empty( $parent->get_meta( '_payment_tokens_id' ) ) ) {
				$order->update_meta_data( '_payment_tokens_id', $parent->get_meta( '_payment_tokens_id' ) );
				$order->save();
			}
		}
		// If still empty, wpg_ppcp_add_payment_source resolves from the user's
		// stored PayPal tokens at capture time.
	}
}
