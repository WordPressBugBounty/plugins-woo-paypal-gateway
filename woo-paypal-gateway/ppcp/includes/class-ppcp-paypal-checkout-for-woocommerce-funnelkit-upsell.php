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
    public $refund_supported = false;

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

        if (!empty($order->get_meta('_payment_tokens_id', true))) {
            return true;
        }

        $parent_id = (int) $order->get_parent_id();
        if ($parent_id <= 0) {
            return false;
        }

        $parent_order = wc_get_order($parent_id);
        if (!$parent_order instanceof WC_Order) {
            return false;
        }

        return !empty($parent_order->get_meta('_payment_tokens_id', true));

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

        if (!$this->has_token($order)) {
            return $this->handle_result(false);
        }

        if (class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            $request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
            $invoice_id = '-wfocu-';
            $result = $request->wpg_ppcp_capture_order_using_payment_method_token($order->get_id(), $invoice_id);
            if ($result) {
                if (!empty($order->get_transaction_id()) && function_exists('WFOCU_Core')) {
                    WFOCU_Core()->data->set('_transaction_id', $order->get_transaction_id());
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
    public function get_transaction_link($transaction_id = '', $order_id = null) {
        if ('' === $transaction_id) {
            return '';
        }

        return sprintf('<span class="wfocu_txn_id">%s</span>', esc_html($transaction_id));
    }
}

PPCP_Paypal_Checkout_For_Woocommerce_FunnelKit_Upsell::get_instance();