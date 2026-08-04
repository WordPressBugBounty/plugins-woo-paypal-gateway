<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles responses from PayPal IPN.
 */
class Woo_Paypal_Gateway_IPN_Handler {

    public function __construct() {
        $this->liveurl = 'https://ipnpb.paypal.com/cgi-bin/webscr';
        $this->testurl = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
    }

    public function check_response() {
        // PayPal IPN is a server-to-server callback from PayPal and carries no WordPress
        // nonce. Authenticity is verified by posting the payload back to PayPal (validate_ipn)
        // and by confirming the receiver account in valid_response().
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (!empty($_POST) && !empty($_POST['ipn_track_id'])) {
            if (!empty($_POST) && $this->validate_ipn()) {
                $posted = wp_unslash($_POST);
                $this->valid_response($posted);
                exit;
            }
            wp_die('PayPal IPN Request Failure', 'PayPal IPN', array('response' => 500));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    public function valid_response($posted) {
        $order = !empty($posted['custom']) ? $this->get_paypal_order($posted['custom']) : false;
        if ($order) {
            $posted['payment_status'] = strtolower($posted['payment_status']);
            // Confirm the payment was actually received by the store's own PayPal account.
            // A verified IPN only proves the transaction is real, not that the money reached
            // the merchant; without this check an IPN for a payment sent to a different (for
            // example the attacker's own, or a free sandbox) account would be accepted.
            if (!$this->validate_receiver($posted)) {
                $this->wpg_add_log('Aborting: IPN receiver does not match the configured merchant account.');
                exit;
            }
            $this->wpg_add_log('Found order #' . $order->get_id());
            $this->wpg_add_log('Payment status: ' . $posted['payment_status']);
            if (method_exists($this, 'payment_status_' . $posted['payment_status'])) {
                call_user_func(array($this, 'payment_status_' . $posted['payment_status']), $order, $posted);
            }
        }
    }

    public function validate_ipn() {
        $this->wpg_add_log('Checking IPN response is valid');
        $validate_ipn = array('cmd' => '_notify-validate');
        // PayPal IPN postback verification; the payload comes from PayPal and has no nonce.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $post_log = wp_unslash($_POST);
        $validate_ipn += wp_unslash($_POST);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $params = array(
            'body' => $validate_ipn,
            'timeout' => 60,
            'httpversion' => '1.1',
            'compress' => false,
            'decompress' => false,
            'user-agent' => 'WooCommerce/' . WC()->version
        );

        // The sandbox-vs-live verification endpoint must be decided by the store's own
        // configuration, never by a request field: a client-supplied "test_ipn" flag would
        // otherwise let an attacker have a free sandbox transaction validated as genuine.
        $paypal_adr = $this->is_sandbox_mode() ? $this->testurl : $this->liveurl;

        $response = wp_safe_remote_post($paypal_adr, $params);
        if (!empty($post_log['custom'])) {
            $post_log['custom'] = '*****************';
        }

        if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 && strstr($response['body'], 'VERIFIED')) {
            $this->wpg_add_log('Received valid response from PayPal');
            return true;
        }
        $this->wpg_add_log('Received invalid response from PayPal');
        if (is_wp_error($response)) {
            $this->wpg_add_log('Error response: ' . $response->get_error_message());
        }
        return false;
    }

    public function validate_transaction_type($txn_type) {
        $accepted_types = array('cart', 'instant', 'express_checkout', 'web_accept', 'masspay', 'send_money', 'webaccept');
        if (!in_array(strtolower($txn_type), $accepted_types)) {
            $this->wpg_add_log('Aborting, Invalid type:' . $txn_type);
            exit;
        }
    }

    public function validate_currency($order, $currency) {
        $order_currency = $order->get_currency();
        if ($order_currency != $currency) {
            $this->wpg_add_log('Payment error: Currencies do not match (sent "' . $order_currency . '" | returned "' . $currency . '")');
            // translators: %s: Currency code returned by PayPal.
            $order->update_status('on-hold', sprintf(__('Validation error: PayPal currencies do not match (code %s).', 'woo-paypal-gateway'), $currency));
            exit;
        }
    }

    public function validate_amount($order, $amount) {
        if (number_format($order->get_total(), 2, '.', '') != number_format($amount, 2, '.', '')) {
            $this->wpg_add_log('Payment error: Amounts do not match (gross ' . $amount . ')');
            // translators: %s: Gross amount from PayPal.
            $order->update_status('on-hold', sprintf(__('Validation error: PayPal amounts do not match (gross %s).', 'woo-paypal-gateway'), $amount));
            exit;
        }
    }

    public function payment_status_completed($order, $posted) {
        if ($order->has_status('completed') || $order->is_paid()) {
            $this->wpg_add_log('Aborting, Order #' . $order->get_id() . ' is already paid.');
            exit;
        }
        $this->validate_transaction_type($posted['txn_type']);
        $this->validate_currency($order, $posted['mc_currency']);
        $this->validate_amount($order, $posted['mc_gross']);
        if ('completed' === $posted['payment_status']) {
            $this->payment_complete($order, (!empty($posted['txn_id']) ? wc_clean($posted['txn_id']) : ''), __('IPN payment completed', 'woo-paypal-gateway'));
        } else {
            // translators: %s: PayPal pending reason.
            $this->payment_on_hold($order, sprintf(__('Payment pending: %s', 'woo-paypal-gateway'), $posted['pending_reason']));
        }
    }

    public function payment_status_pending($order, $posted) {
        $this->payment_status_completed($order, $posted);
    }

    public function payment_status_failed($order, $posted) {
        // translators: %s: Payment status.
        $order->update_status('failed', sprintf(__('Payment %s via IPN.', 'woo-paypal-gateway'), wc_clean($posted['payment_status'])));
    }

    public function payment_status_denied($order, $posted) {
        $this->payment_status_failed($order, $posted);
    }

    public function payment_status_expired($order, $posted) {
        $this->payment_status_failed($order, $posted);
    }

    public function payment_status_voided($order, $posted) {
        $this->payment_status_failed($order, $posted);
    }

    public function payment_status_refunded($order, $posted) {
        if ($order->get_total() == ($posted['mc_gross'] * -1)) {
            // translators: %s: Payment status.
            $order->add_order_note(sprintf(__('Payment %s via IPN.', 'woo-paypal-gateway'), wc_clean($posted['payment_status'])));
            // translators: %s: Payment status.
            $order->update_status('refunded', sprintf(__('Payment %s via IPN.', 'woo-paypal-gateway'), strtolower($posted['payment_status'])));
            // translators: 1: Order number, 2: PayPal reason code.
            $this->send_ipn_email_notification(sprintf(__('Payment for order %1$s refunded', 'woo-paypal-gateway'), '<a class="link" href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">' . $order->get_order_number() . '</a>'), sprintf(__('Order #%1$s has been marked as refunded - PayPal reason code: %2$s', 'woo-paypal-gateway'), $order->get_order_number(), $posted['reason_code']));
        }
    }

    public function payment_status_reversed($order, $posted) {
        // translators: %s: Payment status.
        $order->add_order_note(sprintf(__('Payment %s via IPN.', 'woo-paypal-gateway'), wc_clean($posted['payment_status'])));
        // translators: %s: Payment status.
        $order->update_status('on-hold', sprintf(__('Payment %s via IPN.', 'woo-paypal-gateway'), wc_clean($posted['payment_status'])));
        // translators: 1: Order number, 2: PayPal reason code.
        $this->send_ipn_email_notification(sprintf(__('Payment for order %1$s reversed', 'woo-paypal-gateway'), '<a class="link" href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">' . $order->get_order_number() . '</a>'), sprintf(__('Order #%1$s has been marked on-hold due to a reversal - PayPal reason code: %2$s', 'woo-paypal-gateway'), $order->get_order_number(), wc_clean($posted['reason_code'])));
    }

    public function payment_status_canceled_reversal($order, $posted) {
        // translators: %s: Payment status.
        $order->add_order_note(sprintf(__('Payment %s via IPN.', 'woo-paypal-gateway'), wc_clean($posted['payment_status'])));
        // translators: 1: Order number.
        $this->send_ipn_email_notification(sprintf(__('Reversal cancelled for order #%1$s', 'woo-paypal-gateway'), $order->get_order_number()),sprintf(__('Order #%1$s has had a reversal cancelled. Please check the status of payment and update the order status accordingly here: %2$s', 'woo-paypal-gateway'), $order->get_order_number(), esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit'))));
    }

    public function send_ipn_email_notification($subject, $message) {
        $new_order_settings = get_option('woocommerce_new_order_settings', array());
        $mailer = WC()->mailer();
        $message = $mailer->wrap_message($subject, $message);
        $mailer->send(!empty($new_order_settings['recipient']) ? $new_order_settings['recipient'] : get_option('admin_email'), wp_strip_all_tags($subject), $message);
    }

    public function get_paypal_order($raw_custom) {
        $custom = json_decode($raw_custom);
        if ($custom && is_object($custom)) {
            $order_id = $custom->order_id;
            $order_key = $custom->order_key;
        } else {
            $this->wpg_add_log('Error: Order ID and key were not found in "custom".');
            return false;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            $order_id = wc_get_order_id_by_order_key($order_key);
            $order = wc_get_order($order_id);
        }
        if (!$order || !is_string($order_key) || !hash_equals($order->get_order_key(), $order_key)) {
            $this->wpg_add_log('Error: Order Keys do not match.');
            return false;
        }
        return $order;
    }

    /**
     * Read the PayPal Checkout gateway settings (holds the connected merchant identity).
     *
     * @return array
     */
    protected function get_merchant_settings() {
        $settings = get_option('woocommerce_wpg_paypal_checkout_settings', array());
        return is_array($settings) ? $settings : array();
    }

    /**
     * Whether IPN postbacks should be verified against the PayPal sandbox.
     *
     * Derived from the store's own configuration (never from the request), and filterable
     * so a developer can opt a test store in explicitly.
     *
     * @return bool
     */
    public function is_sandbox_mode() {
        $settings = $this->get_merchant_settings();
        $is_sandbox = isset($settings['sandbox']) && 'yes' === $settings['sandbox'];
        return (bool) apply_filters('woo_paypal_gateway_ipn_use_sandbox', $is_sandbox);
    }

    /**
     * Confirm the IPN's receiver is the store's own PayPal account.
     *
     * Compares the posted receiver e-mail / merchant id against the connected account.
     * Returns true when it matches, or when the store identity or the posted receiver
     * fields are unavailable to compare (so legacy setups are not broken); returns false
     * only when a comparison was possible and failed.
     *
     * @param array $posted
     * @return bool
     */
    public function validate_receiver($posted) {
        // Safety valve: the receiver check can be disabled without a code release (e.g. via a
        // small mu-plugin) should any specific store configuration ever misfire.
        if (!apply_filters('woo_paypal_gateway_ipn_validate_receiver', true, $posted)) {
            return true;
        }
        $settings = $this->get_merchant_settings();
        $is_sandbox = $this->is_sandbox_mode();
        $expected_email = $is_sandbox
            ? (isset($settings['ppcp_email_sandbox']) ? $settings['ppcp_email_sandbox'] : '')
            : (isset($settings['ppcp_email_live']) ? $settings['ppcp_email_live'] : '');
        $expected_merchant = $is_sandbox
            ? (isset($settings['sandbox_merchant_id']) ? $settings['sandbox_merchant_id'] : '')
            : (isset($settings['live_merchant_id']) ? $settings['live_merchant_id'] : '');
        $receiver_email = isset($posted['receiver_email']) ? $posted['receiver_email'] : (isset($posted['business']) ? $posted['business'] : '');
        $receiver_id = isset($posted['receiver_id']) ? $posted['receiver_id'] : '';
        $checked = false;
        if (!empty($expected_email) && !empty($receiver_email)) {
            $checked = true;
            if (0 === strcasecmp(trim($expected_email), trim($receiver_email))) {
                return true;
            }
        }
        if (!empty($expected_merchant) && !empty($receiver_id)) {
            $checked = true;
            if (hash_equals((string) $expected_merchant, (string) $receiver_id)) {
                return true;
            }
        }
        if (!$checked) {
            $this->wpg_add_log('Notice: no comparable merchant/receiver identity available to validate IPN; allowing.');
            return true;
        }
        return false;
    }

    public function payment_complete($order, $txn_id = '', $note = '') {
        $order->add_order_note($note);
        $order->payment_complete($txn_id);
    }

    public function payment_on_hold($order, $reason = '') {
        $order->update_status('on-hold', $reason);
        // Reduce stock through the modern, idempotent helper. WC_Order::reduce_order_stock()
        // was deprecated in WooCommerce 3.0 and no longer exists on current WC_Order, so the
        // old call could fatal on an eCheck/pending IPN — a server-to-server request where an
        // uncaught error just makes PayPal retry the callback. wc_reduce_stock_levels() is what
        // this plugin's other gateways already use, and it will not double-reduce because it is
        // guarded by the order's stock-reduced flag (the on-hold transition may already have
        // reduced stock via WooCommerce core).
        if (function_exists('wc_reduce_stock_levels')) {
            wc_reduce_stock_levels($order->get_id());
        }
        // IPN is a server-to-server callback with no shopper session; WC()->cart can be null in
        // that context (as it can under WP-CLI or cron). Only empty a cart that actually exists.
        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    public function wpg_add_log($message, $level = 'info') {
        if (empty($this->log)) {
            $this->log = wc_get_logger();
        }
        $this->log->log($level, $message, array('source' => 'wpg_ipn'));
    }
}
