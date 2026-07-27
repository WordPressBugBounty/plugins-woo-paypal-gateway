<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WFOCU_Gateway')) {
    return;
}

/**
 * FunnelKit Upsell gateway bridge for PPCP.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.
class PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell extends WFOCU_Gateway {

    /**
     * FunnelKit gateway key (must match WC gateway id).
     *
     * @var string
     */
    public $key = 'wpg_paypal_checkout_cc';

    /**
     * Whether this gateway supports offer refunds.
     *
     * @var bool
     */
    public $refund_supported = true;

    /**
     * PayPal order id being resumed after a buyer-approval redirect.
     *
     * @var string
     */
    protected $resume_paypal_order_id = '';

    /**
     * Set the approved PayPal order to resume (redirect-return flow).
     *
     * @param string $paypal_order_id PayPal order id.
     */
    public function set_paypal_order($paypal_order_id) {
        $this->resume_paypal_order_id = (string) $paypal_order_id;
    }

    /**
     * Singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Check whether order (or parent order) has reusable PPCP vault token.
     *
     * @param WC_Order $order
     * @return bool
     */
    public function has_token($order) {
        if (!$order instanceof WC_Order) {
            return false;
        }

        $order_id = $order->get_id();
        $fresh_order = wc_get_order($order_id);
        if ($fresh_order instanceof WC_Order && !empty($fresh_order->get_meta('_payment_tokens_id', true))) {
            return true;
        }

        $parent_id = (int) $order->get_parent_id();
        if ($parent_id > 0) {
            $parent_order = wc_get_order($parent_id);
            if ($parent_order instanceof WC_Order && !empty($parent_order->get_meta('_payment_tokens_id', true))) {
                return true;
            }
        }

        $user_id = (int) $order->get_customer_id();
        if ($user_id > 0) {
            foreach (['wpg_paypal_checkout', 'wpg_paypal_checkout_cc'] as $gateway_id) {
                $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, $gateway_id);
                if (!empty($tokens)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Process accepted upsell charge via stored PPCP token.
     *
     * @param WC_Order $order
     * @return array
     */
    public function process_charge($order) {
        if (!$order instanceof WC_Order) {
            return $this->handle_result(false);
        }

        // Resuming after the buyer approved the offer at PayPal (token-less flow).
        if (!empty($this->resume_paypal_order_id) && class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
            $txn_id = $request->wpg_ppcp_capture_offer_redirect_order($this->resume_paypal_order_id, $order);
            $this->resume_paypal_order_id = '';
            if ($txn_id) {
                if (function_exists('WFOCU_Core')) {
                    WFOCU_Core()->data->set('_transaction_id', $txn_id);
                }
                return $this->handle_result(true);
            }
            return $this->handle_result(false);
        }

        if (!$this->has_token($order)) {
            // No vault token: send the buyer to PayPal to approve the offer
            // amount, exactly like a normal purchase (reference behavior).
            $this->maybe_start_redirect_approval($order);
            return $this->handle_result(false);
        }

        if (class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
            $invoice_id = '-wfocu-' . uniqid() . '-';
            // In FunnelKit's batching mode the order passed in is the parent
            // order, so the charge amount must come from the upsell package,
            // not from the order's totals. Fall back to the order totals only
            // when no package is available (new-order mode, where the order
            // contains exactly the offer).
            $charge_override = null;
            if (function_exists('WFOCU_Core')) {
                $package = WFOCU_Core()->data->get('_upsell_package');
                if (is_array($package) && isset($package['total']) && (float) $package['total'] > 0) {
                    $charge_override = array(
                        'total' => (float) $package['total'],
                        'description' => __('FunnelKit upsell offer', 'woo-paypal-gateway'),
                    );
                }
            }
            $result = $request->wpg_ppcp_capture_order_using_payment_method_token($order->get_id(), $invoice_id, $charge_override);
            if ($result) {
                if (function_exists('WFOCU_Core')) {
                    $txn_id = is_string($result) ? $result : $order->get_transaction_id();
                    if (!empty($txn_id)) {
                        WFOCU_Core()->data->set('_transaction_id', $txn_id);
                    }
                }

                return $this->handle_result(true);
            }
        }

        return $this->handle_result(false);
    }

    /**
     * Whether this gateway is enabled in FunnelKit upsell settings.
     *
     * @param false|WC_Order $order Optional.
     * @return bool
     */
    public function is_enabled($order = false) {
        if (!function_exists('WFOCU_Core')) {
            return false;
        }

        $chosen_gateways = WFOCU_Core()->data->get_option('gateways');

        return is_array($chosen_gateways) && in_array($this->key, $chosen_gateways, true);
    }

    /**
     * Render transaction id for FunnelKit admin.
     *
     * @param string   $transaction_id
     * @param int|null $order_id
     * @return string
     */
    /**
     * Token-less offers: create a standalone PayPal order for the offer amount
     * and hand FunnelKit's frontend a redirect to the PayPal approval page.
     * The upsell package is stashed in the FunnelKit session so the return
     * handler can resume the offer after approval. Mirrors the reference
     * implementation's behavior.
     *
     * @param WC_Order $order Parent order.
     */
    protected function maybe_start_redirect_approval($order) {
        if (!function_exists('WFOCU_Core') || !class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            return;
        }
        $package = WFOCU_Core()->data->get('_upsell_package');
        $total = is_array($package) && isset($package['total']) ? (float) $package['total'] : (float) $order->get_total();
        if ($total <= 0) {
            return;
        }
        $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $return_url = add_query_arg(
            array(
                'order_id' => $order->get_id(),
                'order_key' => $order->get_order_key(),
            ),
            WC()->api_request_url('wpg_ppcp_funnelkit_return')
        );
        $cancel_url = method_exists(WFOCU_Core()->public, 'get_the_upsell_url')
            ? WFOCU_Core()->public->get_the_upsell_url(WFOCU_Core()->data->get_current_offer())
            : $order->get_checkout_order_received_url();
        $created = $request->wpg_ppcp_create_offer_redirect_order($order, $total, __('FunnelKit upsell offer', 'woo-paypal-gateway'), $return_url, $cancel_url);
        if (!$created) {
            return;
        }
        // Preserve the offer context across the redirect.
        WFOCU_Core()->data->set('paypal_order_id', $created['id'], 'wpg_ppcp');
        if (is_array($package)) {
            WFOCU_Core()->data->set('upsell_package', $package, 'wpg_ppcp');
        }
        WFOCU_Core()->data->save('wpg_ppcp');
        WFOCU_Core()->data->save();
        wp_send_json(array(
            'success' => true,
            'data' => array('redirect_url' => $created['approve_url']),
        ));
    }

    /**
     * Refund a previously charged upsell offer (FunnelKit admin action).
     * FunnelKit posts txn_id and amt with the request.
     *
     * @param WC_Order $order Order the offer belongs to.
     * @return bool
     */
    public function process_refund_offer($order) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
        $transaction_id = isset($_POST['txn_id']) ? wc_clean(wp_unslash($_POST['txn_id'])) : false;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
        $amount = isset($_POST['amt']) ? round((float) wc_clean(wp_unslash($_POST['amt'])), 2) : false;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if (!$transaction_id || !$amount || !$order instanceof WC_Order) {
            return false;
        }
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            return false;
        }
        $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        $result = $request->ppcp_refund_order($order->get_id(), $amount, __('FunnelKit upsell refund', 'woo-paypal-gateway'), $transaction_id);
        return true === $result;
    }

    public function get_transaction_link($transaction_id = '', $order_id = null) {
        if ('' === $transaction_id) {
            return '';
        }

        return sprintf('<span class="wfocu_txn_id">%s</span>', esc_html($transaction_id));
    }
}

PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell::get_instance();