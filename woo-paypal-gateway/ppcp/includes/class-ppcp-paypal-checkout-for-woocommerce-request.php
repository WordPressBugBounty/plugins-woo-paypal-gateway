<?php

// This class builds and processes PayPal API requests and handles PayPal's return and
// webhook callbacks. Those requests originate from PayPal's servers (return redirects and
// server-to-server webhooks) or from WooCommerce checkout flows whose nonce WooCommerce
// verifies upstream, so a plugin-level nonce check is not applicable to the superglobal
// reads in this file. Input is still unslashed and sanitized at each read; only the nonce
// sniffs are disabled.
// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Request
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Request/includes
 * @author     easypayment
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.
class PPCP_Paypal_Checkout_For_Woocommerce_Request extends WC_Payment_Gateway {

    /**
     * @since    1.0.0
     */
    public $log_enabled = false;
    public static $log = false;
    public $request;
    public $id;
    public $decimals;
    public $is_sandbox;
    public $debug;
    public $ppcp_currency;
    public $client_id;
    public $secret;
    public $token_url;
    public $access_token;
    public $order_url;
    public $paypal_oauth_api;
    public $paypal_order_api;
    public $paypal_refund_api;
    public $auth;
    public $webhook;
    public $basicAuth;
    public $webhook_id;
    public $webhook_url;
    public $generate_token_url;
    public $client_token;
    public $paymentaction;
    public $authorized_order_status;
    public $payee_preferred;
    public $invoice_id_prefix;
    public $soft_descriptor;
    public $brand_name;
    public $landing_page;
    public $advanced_card_payments;
    public $AVSCodes;
    public $CVV2Codes;
    public $logger;
    public $merchant_id;
    public $send_items;
    public $api_response;
    public $payment_token;
    public $id_token_url;
    public $payment_tokens_url;
    public $setup_tokens_url;
    public $ppcp_locale;
    public $sandbox_merchant_id;
    public $live_merchant_id;
    public $partner_client_id;
    public $tracking_api_url;
    public $threed_secure_contingency;

    /**
     * True when the last capture attempt was refused only because PayPal had not
     * finished settling the buyer's approval yet (a still-running or just-completed
     * 3-D Secure / banking-app authentication), as opposed to a terminal failure.
     *
     * Callers use it to keep the PayPal order in the session so the shopper can
     * simply retry, instead of tearing the payment session down.
     *
     * @var bool
     */
    public $capture_blocked_pending = false;
    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        try {
            $this->id = 'wpg_paypal_checkout';
            $this->is_sandbox = 'yes' === $this->get_option('sandbox', 'no');
            $this->debug = 'yes' === $this->get_option('debug', 'yes');
            $this->ppcp_currency = array('AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'INR', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD');
            $this->log_enabled = $this->debug;
            $this->sandbox_merchant_id = $this->get_option('sandbox_merchant_id', '');
            $this->live_merchant_id = $this->get_option('live_merchant_id', '');
            if ($this->is_sandbox) {
                $this->partner_client_id = 'AdQrAvT3Oc02ojpanh-4jlZDUP4mDt1H2fauytlXXU91lzSuyPmsHyFDwmwNwNEBcY_XTH9pSIb9Lt66';
                $this->client_id = $this->get_option('rest_client_id_sandbox');
                $this->secret = $this->get_option('rest_secret_id_sandbox');
                $this->token_url = 'https://api.sandbox.paypal.com/v1/oauth2/token';
                $this->access_token = get_transient('ppcp_sandbox_access_token');
                $this->order_url = 'https://api.sandbox.paypal.com/v2/checkout/orders/';
                $this->paypal_oauth_api = 'https://api.sandbox.paypal.com/v1/oauth2/token/';
                $this->paypal_order_api = 'https://api.sandbox.paypal.com/v2/checkout/orders/';
                $this->paypal_refund_api = 'https://api.sandbox.paypal.com/v2/payments/captures/';
                $this->auth = 'https://api.sandbox.paypal.com/v2/payments/authorizations/';
                $this->webhook = 'https://api.sandbox.paypal.com/v1/notifications/webhooks';
                $this->basicAuth = base64_encode($this->client_id . ":" . $this->secret);
                $this->webhook_id = 'ppcp_sandbox_webhook_id';
                $this->webhook_url = 'https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature';
                $this->generate_token_url = 'https://api.sandbox.paypal.com/v1/identity/generate-token';
                $this->client_token = get_transient('ppcp_sandbox_client_token');
                $this->id_token_url = 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
                $this->payment_tokens_url = 'https://api-m.sandbox.paypal.com/v3/vault/payment-tokens';
                $this->setup_tokens_url = 'https://api-m.sandbox.paypal.com/v3/vault/setup-tokens';
                $this->merchant_id = $this->sandbox_merchant_id;
                $this->tracking_api_url = 'https://api-m.sandbox.paypal.com/v1/shipping/trackers-batch';
            } else {
                $this->partner_client_id = 'AfEf_pXdoWtQRqLJ_E3B_20i_TvZb6N3gf1M9s9A8FddJcG9yyoL_M1Ob9OqhflggcdGI_7STlYopHmR';
                $this->client_token = get_transient('ppcp_client_token');
                $this->client_id = $this->get_option('rest_client_id_live');
                $this->secret = $this->get_option('rest_secret_id_live');
                $this->token_url = 'https://api.paypal.com/v1/oauth2/token';
                $this->access_token = get_transient('ppcp_access_token');
                $this->order_url = 'https://api.paypal.com/v2/checkout/orders/';
                $this->paypal_oauth_api = 'https://api.paypal.com/v1/oauth2/token/';
                $this->paypal_order_api = 'https://api.paypal.com/v2/checkout/orders/';
                $this->paypal_refund_api = 'https://api.paypal.com/v2/payments/captures/';
                $this->auth = 'https://api.paypal.com/v2/payments/authorizations/';
                $this->webhook = 'https://api.paypal.com/v1/notifications/webhooks';
                $this->basicAuth = base64_encode($this->client_id . ":" . $this->secret);
                $this->webhook_id = 'ppcp_live_webhook_id';
                $this->webhook_url = 'https://api.paypal.com/v1/notifications/verify-webhook-signature';
                $this->generate_token_url = 'https://api.paypal.com/v1/identity/generate-token';
                $this->id_token_url = 'https://api.paypal.com/v1/oauth2/token';
                $this->payment_tokens_url = 'https://api-m.paypal.com/v3/vault/payment-tokens';
                $this->setup_tokens_url = 'https://api-m.paypal.com/v3/vault/setup-tokens';
                $this->merchant_id = $this->live_merchant_id;
                $this->tracking_api_url = 'https://api-m.paypal.com/v1/shipping/trackers-batch';
            }
            $this->paymentaction = $this->get_option('paymentaction', 'capture');
            $this->authorized_order_status = $this->get_option('authorized_order_status', 'on-hold');
            $this->payee_preferred = 'yes' === $this->get_option('payee_preferred', 'no');
            $this->invoice_id_prefix = $this->get_option('invoice_id_prefix', 'WC-PPCP');
            $this->soft_descriptor = $this->get_option('soft_descriptor', '');
            $this->brand_name = $this->get_option('brand_name', get_bloginfo('name'));
            $this->landing_page = $this->get_option('landing_page', 'NO_PREFERENCE');
            $this->advanced_card_payments = 'yes' === $this->get_option('enable_advanced_card_payments', 'no');
            $this->decimals = $this->ppcp_get_number_of_decimal_digits();
            $this->send_items = 'yes' === $this->get_option('send_items', 'yes');
            $this->threed_secure_contingency = $this->get_option('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
            $this->AVSCodes = array("A" => "Address Matches Only (No ZIP)",
                "B" => "Address Matches Only (No ZIP)",
                "C" => "This tranaction was declined.",
                "D" => "Address and Postal Code Match",
                "E" => "This transaction was declined.",
                "F" => "Address and Postal Code Match",
                "G" => "Global Unavailable - N/A",
                "I" => "International Unavailable - N/A",
                "N" => "None - Transaction was declined.",
                "P" => "Postal Code Match Only (No Address)",
                "R" => "Retry - N/A",
                "S" => "Service not supported - N/A",
                "U" => "Unavailable - N/A",
                "W" => "Nine-Digit ZIP Code Match (No Address)",
                "X" => "Exact Match - Address and Nine-Digit ZIP",
                "Y" => "Address and five-digit Zip match",
                "Z" => "Five-Digit ZIP Matches (No Address)");

            $this->CVV2Codes = array(
                "E" => "N/A",
                "M" => "Match",
                "N" => "No Match",
                "P" => "Not Processed - N/A",
                "S" => "Service Not Supported - N/A",
                "U" => "Service Unavailable - N/A",
                "X" => "No Response - N/A"
            );
            add_filter('wpg_ppcp_add_payment_source', array($this, 'wpg_ppcp_add_payment_source'), 10, 2);
            add_action('init', [$this, 'localize_button_text']);
        } catch (Exception $ex) {
            
        }
    }

    public function localize_button_text() {
        $this->order_button_text = __('Continue to payment', 'woo-paypal-gateway');
    }

    private function ppcp_set_order_session_data($paypal_order_id, $status, $woo_order_id = 0) {
        if (empty($paypal_order_id) || empty($status)) {
            return;
        }

        woo_paypal_gateway_ppcp_set_paypal_order_session_data($paypal_order_id, $status, $woo_order_id);
    }

    private function ppcp_get_order_session_data() {
        return woo_paypal_gateway_ppcp_get_paypal_order_session_data();
    }


    private function ppcp_get_paypal_order_id_from_session() {
        return woo_paypal_gateway_ppcp_get_paypal_order_id_from_session();
    }

    /**
     * Whether a PayPal order status means "the buyer has acted, PayPal has not
     * finished settling it yet" rather than a terminal failure.
     *
     * An Orders v2 order sits in PAYER_ACTION_REQUIRED for the whole duration of a
     * 3-D Secure challenge, and PayPal can still report PAYER_ACTION_REQUIRED or
     * CREATED for a short window after the buyer returns from an out-of-band
     * authentication — a banking-app approval, which the shopper may take a minute
     * or more to complete on a second device. An empty status means the status could
     * not be read at all (network blip, error body), which is likewise not evidence
     * of failure. VOIDED is the only genuinely dead state, so it is not listed here.
     *
     * @param string $status Upper-case PayPal order status, '' when unknown.
     * @return bool
     */
    private function ppcp_is_pending_approval_status($status) {
        return in_array($status, array('', 'CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED'), true);
    }

    private function ppcp_validate_order_for_capture($paypal_order_id, $woo_order_id = 0) {
        $this->capture_blocked_pending = false;

        // First check session status to avoid unnecessary API call
        $session_data   = woo_paypal_gateway_ppcp_get_paypal_order_session_data();
        $session_status = ! empty( $session_data['status'] ) ? strtoupper( $session_data['status'] ) : '';

        if ( $session_status === 'APPROVED' || $session_status === 'CAPTURE' ) {
            $this->ppcp_set_order_session_data( $paypal_order_id, 'approved', $woo_order_id );
            return true;
        }

        // Fall back to live API check.
        //
        // Reading the status once and failing immediately turns PayPal's settling
        // delay into a dead checkout: a buyer who authenticates in their banking app
        // is redirected back to the store before the order has moved to APPROVED, and
        // the shopper is left on the checkout page with "PayPal order is not approved
        // yet" for a payment they did complete. A still-settling order is therefore
        // re-read a few times, with a short pause between attempts, before capture is
        // refused. The request-scoped snapshot is dropped between attempts so each
        // re-read genuinely asks PayPal again.
        $attempts = (int) apply_filters('wpg_ppcp_pending_approval_poll_attempts', 3); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        $attempts = max(1, min(6, $attempts));
        $delays   = (array) apply_filters('wpg_ppcp_pending_approval_poll_delays', array(1, 2, 2)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        $paypal_status = '';

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($attempt > 0) {
                $delay = isset($delays[$attempt - 1]) ? (int) $delays[$attempt - 1] : 2;
                if ($delay > 0) {
                    sleep(min(5, $delay));
                }
                self::ppcp_forget_order_snapshot($paypal_order_id);
                $this->ppcp_log('PayPal order ' . $paypal_order_id . ' still settling (status: ' . $paypal_status . '). Re-checking, attempt ' . ($attempt + 1) . ' of ' . $attempts . '.');
            }

            $order_details = $this->ppcp_get_checkout_details($paypal_order_id, true);
            $paypal_status = is_object($order_details) && !empty($order_details->status) ? strtoupper($order_details->status) : '';

            if ($paypal_status === 'APPROVED' || $paypal_status === 'COMPLETED') {
                $this->ppcp_set_order_session_data($paypal_order_id, 'approved', $woo_order_id);
                return true;
            }
            if (!$this->ppcp_is_pending_approval_status($paypal_status)) {
                // Terminal state (e.g. VOIDED) — waiting cannot change the answer.
                break;
            }
        }

        $this->ppcp_log('Capture skipped. PayPal order is not approved. Current status: ' . $paypal_status);
        $this->capture_blocked_pending = $this->ppcp_is_pending_approval_status($paypal_status);
        if (function_exists('wc_add_notice')) {
            if ($this->capture_blocked_pending) {
                // Retryable: the buyer's approval is still in flight at PayPal. Tell them
                // that, rather than asking them to "approve before capture" — wording that
                // means nothing to a shopper who has just approved in their banking app.
                wc_add_notice(__('Your bank is still confirming this payment. Please wait a few seconds and place your order again — you have not been charged twice.', 'woo-paypal-gateway'), 'error');
            } else {
                wc_add_notice(__('PayPal order is not approved yet. Please approve the payment before capture.', 'woo-paypal-gateway'), 'error');
            }
        }
        return false;
    }

    /**
     * Confirm that the approved PayPal order actually belongs to the WooCommerce
     * order that is about to be marked as paid.
     *
     * Broken access control fix (Patchstack — PayPal Order Rebinding): the public
     * create-order and capture endpoints accept a browser-supplied PayPal order id
     * and a session-stored WooCommerce order id independently of each other. Without
     * this reconciliation an unauthenticated buyer could approve a low-value PayPal
     * order, plant a higher-value WooCommerce order id into the plugin session, and
     * capture the cheap PayPal order to settle the expensive WooCommerce order.
     *
     * The captured PayPal order is therefore reconciled against the target order on:
     *   - custom_id  : a PayPal order created for an existing WooCommerce order embeds
     *                  {order_id, order_key}; when that binding is present and points
     *                  at a real order it must be exactly this order.
     *   - amount + currency : the purchase-unit total must equal what the plugin would
     *                  charge for this order, in the same currency. This blocks binding
     *                  a cheaper PayPal order (created from a different cart/order) even
     *                  when its custom_id carries no resolvable order reference.
     *
     * Fails CLOSED: if the PayPal order details cannot be retrieved, or any field does
     * not match, capture is refused and the buyer can retry.
     *
     * @param string $paypal_order_id PayPal order id resolved by the caller.
     * @param int    $woo_order_id    Target WooCommerce order id.
     * @return bool True only when the PayPal order belongs to the target order.
     */
    public function ppcp_confirm_paypal_order_for_woo_order($paypal_order_id, $woo_order_id) {
        $order = wc_get_order($woo_order_id);
        if (!$order instanceof WC_Order || empty($paypal_order_id)) {
            $this->ppcp_log('Capture blocked: missing order context while reconciling PayPal order ' . $paypal_order_id . '.');
            return false;
        }

        // Authoritative snapshot from PayPal. Force a refresh so a pre-approval cached
        // copy cannot be used to satisfy the check (a snapshot fetched live earlier in
        // this same request is reused instead of a second roundtrip), and retry once so
        // a transient fetch failure does not permanently block a legitimate buyer.
        $details = $this->ppcp_get_checkout_details($paypal_order_id, true);
        for ($attempt = 0; $attempt < 1 && empty($details); $attempt++) {
            $details = $this->ppcp_get_checkout_details($paypal_order_id, true);
        }
        $purchase_unit = (is_object($details) && !empty($details->purchase_units[0])) ? $details->purchase_units[0] : null;
        if (empty($purchase_unit)) {
            $this->ppcp_log('Capture blocked: could not fetch PayPal order ' . $paypal_order_id . ' details for reconciliation.');
            $this->ppcp_add_capture_reconciliation_notice();
            return false;
        }

        // 1) custom_id binding. Reject when the PayPal order is bound to a different
        //    existing WooCommerce order than the one being completed.
        $custom_id = isset($purchase_unit->custom_id) ? (string) $purchase_unit->custom_id : '';
        if ($custom_id !== '') {
            $decoded = json_decode($custom_id, true);
            if (is_array($decoded) && isset($decoded['order_id']) && is_numeric($decoded['order_id'])) {
                $bound_order_id  = absint($decoded['order_id']);
                $bound_order_key = isset($decoded['order_key']) ? (string) $decoded['order_key'] : '';
                $bound_order     = $bound_order_id > 0 ? wc_get_order($bound_order_id) : false;
                if ($bound_order instanceof WC_Order) {
                    $key_matches = ($bound_order_key === '' || hash_equals((string) $bound_order->get_order_key(), $bound_order_key));
                    if ($bound_order_id !== absint($woo_order_id) || !$key_matches) {
                        $this->ppcp_log(sprintf('Capture blocked: PayPal order %s is bound to WooCommerce order %d but capture targets order %d.', $paypal_order_id, $bound_order_id, absint($woo_order_id)));
                        $this->ppcp_add_capture_reconciliation_notice();
                        return false;
                    }
                }
            }
        }

        // 2) Amount + currency. The approved purchase-unit total must equal what the
        //    plugin would charge for the target order.
        $paypal_currency = isset($purchase_unit->amount->currency_code) ? strtoupper((string) $purchase_unit->amount->currency_code) : '';
        $paypal_value    = isset($purchase_unit->amount->value) ? (float) $purchase_unit->amount->value : null;
        if ($paypal_value === null || $paypal_currency === '') {
            $this->ppcp_log('Capture blocked: PayPal order ' . $paypal_order_id . ' is missing amount/currency for reconciliation.');
            $this->ppcp_add_capture_reconciliation_notice();
            return false;
        }

        $this->decimals    = $this->ppcp_get_number_of_decimal_digits($this->ppcp_get_currency($woo_order_id));
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        $expected_currency = strtoupper((string) apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)));
        $order_details     = $this->ppcp_get_details_from_order($woo_order_id);
        $expected_source   = (is_array($order_details) && isset($order_details['order_total'])) ? $order_details['order_total'] : $order->get_total();
        $expected_value    = (float) woo_paypal_gateway_ppcp_round($expected_source, $this->decimals);

        if ($paypal_currency !== $expected_currency) {
            $this->ppcp_log(sprintf('Capture blocked: currency mismatch for order %d (PayPal %s vs expected %s).', absint($woo_order_id), $paypal_currency, $expected_currency));
            $this->ppcp_add_capture_reconciliation_notice();
            return false;
        }

        // Both sides are rounded to the currency's precision, so a legitimate match is
        // exact; the epsilon only absorbs float representation noise.
        $epsilon = ($this->decimals > 0) ? (0.5 / pow(10, $this->decimals)) : 0.5;
        if (abs($paypal_value - $expected_value) > $epsilon) {
            $this->ppcp_log(sprintf('Capture blocked: amount mismatch for order %d (PayPal %s vs expected %s %s).', absint($woo_order_id), $paypal_value, $expected_value, $expected_currency));
            $this->ppcp_add_capture_reconciliation_notice();
            return false;
        }

        return true;
    }

    /**
     * Surface a generic, shopper-facing error when a capture is refused because the
     * PayPal order could not be reconciled with the WooCommerce order.
     */
    private function ppcp_add_capture_reconciliation_notice() {
        if (function_exists('wc_add_notice')) {
            wc_add_notice(__('We could not verify your PayPal payment for this order. Please try again.', 'woo-paypal-gateway'), 'error');
        }
    }

    /**
     * Recover an order whose capture already completed at PayPal.
     *
     * When a capture succeeds at PayPal but the success response is lost (network
     * timeout, a garbled body, or a duplicate/retried request), PayPal answers a
     * subsequent capture with ORDER_ALREADY_CAPTURED / ORDER_ALREADY_COMPLETED. The
     * money was taken, but the normal capture path returned false and left the order
     * unpaid. This re-fetches the PayPal order and completes the WooCommerce order
     * from the existing capture instead of failing.
     *
     * This only ever runs from the capture error branch (a path that otherwise always
     * fails), so it cannot alter a capture that succeeds normally. It reuses the
     * fail-closed custom_id + amount + currency reconciliation before completing, so a
     * mismatched or unrelated PayPal order can never be used to mark this order paid.
     * The customer is not charged again — the order is settled against the capture id
     * that already exists at PayPal.
     *
     * @param mixed  $order           The WooCommerce order (WC_Order expected).
     * @param int    $woo_order_id    Target WooCommerce order id.
     * @param string $paypal_order_id PayPal order id being captured.
     * @return bool True when the order was recovered and marked paid.
     */
    private function ppcp_recover_already_captured_order($order, $woo_order_id, $paypal_order_id) {
        try {
            if (!$order instanceof WC_Order || empty($paypal_order_id)) {
                return false;
            }
            // Never complete an order we cannot reconcile — same fail-closed check used
            // on the normal capture path (custom_id + amount + currency, force refresh).
            if (!$this->ppcp_confirm_paypal_order_for_woo_order($paypal_order_id, $woo_order_id)) {
                return false;
            }
            if ($order->has_status(wc_get_is_paid_statuses())) {
                // Already completed on another request/instance — treat as recovered.
                return true;
            }
            $details = $this->ppcp_get_checkout_details($paypal_order_id, true);
            $purchase_unit = (is_object($details) && !empty($details->purchase_units[0])) ? $details->purchase_units[0] : null;
            if (empty($purchase_unit) || empty($purchase_unit->payments)) {
                return false;
            }
            $capture = isset($purchase_unit->payments->captures[0]) ? $purchase_unit->payments->captures[0] : null;
            if ($capture && isset($capture->status) && $capture->status === 'COMPLETED' && !empty($capture->id)) {
                $api_response = json_decode(wp_json_encode($details), true);
                if (function_exists('woo_paypal_gateway_set_order_payment_method_title_from_paypal_response')) {
                    woo_paypal_gateway_set_order_payment_method_title_from_paypal_response($order, $api_response);
                }
                $order->payment_complete($capture->id);
                $order->update_meta_data('_payment_status', 'COMPLETED');
                $order->update_meta_data('_wpg_paypal_order_id', $paypal_order_id);
                $order->add_order_note(__('Recovered a PayPal capture that had already completed at PayPal but whose response was not received on the first attempt. The order is marked paid against the existing capture; the customer was not charged again.', 'woo-paypal-gateway'));
                // translators: 1: payment method title, 2: transaction ID.
                $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $capture->id));
                $order->save_meta_data();
                $this->ppcp_log('Recovered ORDER_ALREADY_CAPTURED for order #' . $woo_order_id . ' with capture ' . $capture->id);
                return true;
            }
            return false;
        } catch (\Throwable $ex) {
            $this->ppcp_log('Already-captured recovery failed for order #' . $woo_order_id . ': ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Recover an order whose authorization already exists at PayPal.
     *
     * The authorize counterpart of ppcp_recover_already_captured_order: when the
     * authorize response is lost or a duplicate authorize is sent, PayPal answers
     * ORDER_ALREADY_AUTHORIZED. The authorization exists, but the normal path returned
     * false and left the order unprocessed. This re-fetches the order and applies the
     * authorized status from the existing authorization. Runs only from the authorize
     * error branch, and reconciles fail-closed before touching the order.
     *
     * @param mixed  $order
     * @param int    $woo_order_id
     * @param string $paypal_order_id
     * @return bool
     */
    private function ppcp_recover_already_authorized_order($order, $woo_order_id, $paypal_order_id) {
        try {
            if (!$order instanceof WC_Order || empty($paypal_order_id)) {
                return false;
            }
            if (!$this->ppcp_confirm_paypal_order_for_woo_order($paypal_order_id, $woo_order_id)) {
                return false;
            }
            // Already progressed on another request/instance.
            if ($order->get_meta('_auth_transaction_id') || $order->has_status(wc_get_is_paid_statuses())) {
                return true;
            }
            $details = $this->ppcp_get_checkout_details($paypal_order_id, true);
            $purchase_unit = (is_object($details) && !empty($details->purchase_units[0])) ? $details->purchase_units[0] : null;
            if (empty($purchase_unit) || empty($purchase_unit->payments)) {
                return false;
            }
            $authorization = isset($purchase_unit->payments->authorizations[0]) ? $purchase_unit->payments->authorizations[0] : null;
            $status = ($authorization && isset($authorization->status)) ? strtoupper($authorization->status) : '';
            if ($authorization && !empty($authorization->id) && in_array($status, array('AUTHORIZED', 'CREATED'), true)) {
                $api_response = json_decode(wp_json_encode($details), true);
                if (function_exists('woo_paypal_gateway_set_order_payment_method_title_from_paypal_response')) {
                    woo_paypal_gateway_set_order_payment_method_title_from_paypal_response($order, $api_response);
                }
                $order->set_transaction_id($authorization->id);
                $order->update_meta_data('_payment_status', $status);
                $order->update_meta_data('_auth_transaction_id', $authorization->id);
                $order->update_meta_data('_payment_action', $this->paymentaction);
                $order->add_order_note(__('Recovered a PayPal authorization that already existed at PayPal but whose response was not received on the first attempt. The order is set to the authorized status against the existing authorization.', 'woo-paypal-gateway'));
                // translators: 1: payment method title, 2: transaction ID.
                $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $authorization->id));
                $order->save_meta_data();
                $order->update_status($this->authorized_order_status);
                $this->ppcp_log('Recovered ORDER_ALREADY_AUTHORIZED for order #' . $woo_order_id . ' with authorization ' . $authorization->id);
                return true;
            }
            return false;
        } catch (\Throwable $ex) {
            $this->ppcp_log('Already-authorized recovery failed for order #' . $woo_order_id . ': ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Recursively mask credential-bearing values before they are written to the
     * WooCommerce log. Log files under wp-content/uploads/wc-logs/ are viewable
     * from WooCommerce > Status > Logs, so tokens and API secrets must never be
     * stored there in plaintext. Only the last 4 characters are kept so entries
     * remain correlatable while being useless to an attacker.
     *
     * @param mixed $data Decoded response array (or any scalar).
     * @return mixed Same structure with sensitive values masked.
     */
    public function ppcp_redact_sensitive_data($data) {
        static $sensitive_keys = array(
            'access_token',
            'refresh_token',
            'id_token',
            'client_token',
            'client_id',
            'client_secret',
            'payer_id',
            'authCode',
            'sharedId',
            'code_verifier',
            'seller_nonce',
        );
        // Buyer PII: masked (not fully redacted) so logs stay usable for support
        // correlation without exposing full contact details.
        static $pii_keys = array(
            'email_address',
            'national_number',
        );
        // API responses decoded as objects (stdClass) must be masked too; this method
        // is only used to build log output, so casting to an array is safe.
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (!is_array($data)) {
            return $data;
        }
        $redacted = array();
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $redacted[$key] = $this->ppcp_redact_sensitive_data($value);
            } elseif (is_string($key) && in_array($key, $sensitive_keys, true) && is_string($value) && $value !== '') {
                $redacted[$key] = '***REDACTED***' . (strlen($value) > 8 ? substr($value, -4) : '');
            } elseif (is_string($key) && in_array($key, $pii_keys, true) && is_string($value) && $value !== '') {
                $redacted[$key] = $this->ppcp_mask_pii_value($key, $value);
            } else {
                $redacted[$key] = $value;
            }
        }
        return $redacted;
    }

    private function ppcp_mask_pii_value($key, $value) {
        if ($key === 'email_address') {
            $at = strpos($value, '@');
            if ($at > 0) {
                return substr($value, 0, 1) . '***' . substr($value, $at);
            }
            return substr($value, 0, 1) . '***';
        }
        // Phone numbers: keep the last two digits for correlation.
        return str_repeat('*', max(0, strlen($value) - 2)) . substr($value, -2);
    }

    public function request($url, $args, $action_name = 'default') {
        try {
            if ($action_name === 'generate_signup_link') {
                $this->ppcp_log($action_name);
            } else {
                $this->ppcp_log($action_name . ' : ' . $url);
            }

            $result = wp_remote_get($url, $args);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
            } else {
                $body = wp_remote_retrieve_body($result);
                $response = !empty($body) ? json_decode($body, true) : '';
                $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($result));
                $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($result));
                // Never log raw bodies for credential-bearing endpoints; log a
                // redacted copy of the decoded response instead.
                if (is_array($response)) {
                    $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($response), true));
                } elseif (in_array($action_name, array('get_access_token', 'get_credentials'), true)) {
                    $this->ppcp_log('Response Body: [redacted - credential response was not valid JSON]');
                } else {
                    $this->ppcp_log('Response Body: ' . wc_print_r($body, true));
                }
                return $response;
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function ppcp_application_context($woo_order_id, $return = false) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler')) {
            require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-locale_handler.php';
        }
        $this->ppcp_locale = PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler::instance();
        $base_url = untrailingslashit(WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'));
        $application_context = array(
            'brand_name' => $this->brand_name,
            'locale' => $this->valid_bcp47_code(),
            'landing_page' => $this->landing_page,
            'shipping_preference' => $this->ppcp_shipping_preference($woo_order_id),
            //'user_action' => is_checkout() ? 'PAY_NOW' : 'CONTINUE',
            'user_action' => 'PAY_NOW',
            'return_url' => add_query_arg(['ppcp_action' => 'ppcp_regular_capture', 'utm_nooverride' => '1'], $base_url),
            'cancel_url' => add_query_arg(['ppcp_action' => 'cancel_order', 'utm_nooverride' => '1'], $base_url)
        );
        if ($return) {
            $application_context['return_url'] = add_query_arg(array('ppcp_action' => 'ppcp_regular_capture', 'utm_nooverride' => '1'), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'));
        }
        return $application_context;
    }

    public function ppcp_shipping_preference($woo_order_id = null) {
        // Detect current page
        $page = isset($_GET['from']) && !empty($_GET['from'])
            ? sanitize_text_field(wp_unslash($_GET['from']))
            : (is_cart() ? 'cart' : (is_checkout() || is_checkout_pay_page() ? 'checkout' : (is_product() ? 'product' : null)));

        // Determine if shipping is needed
        $needs_shipping = false;

        // Use cart if available
        if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) {
            $needs_shipping = WC()->cart->needs_shipping();
        }
        // If cart not available, try order ID
        elseif ($woo_order_id) {
            $order = wc_get_order($woo_order_id);
            if ($order) {
                $needs_shipping = $order->needs_shipping_address();
            }
        }
        
        // Default preference
        $shipping_preference = $needs_shipping ? 'GET_FROM_FILE' : 'NO_SHIPPING';

        // Determine shipping preference based on page and need for shipping
        if ($page === null) {
            return $needs_shipping ? 'GET_FROM_FILE' : 'NO_SHIPPING';
        }

        switch ($page) {
            case 'product':
            case 'cart':
            case 'express_checkout':
                $shipping_preference = $needs_shipping ? 'GET_FROM_FILE' : 'NO_SHIPPING';
                break;

            case 'checkout':
            case 'pay_page':
                $shipping_preference = $needs_shipping ? 'SET_PROVIDED_ADDRESS' : 'NO_SHIPPING';
                break;
        }

        return $shipping_preference;
    }


    public function get_genrate_token() {
        try {
            if (function_exists('woo_paypal_gateway_ppcp_is_order_received_request') ? woo_paypal_gateway_ppcp_is_order_received_request() : is_wc_endpoint_url('order-received')) {
                return;
            }
            // Failure backoff: when the mint fails (e.g. the account is not
            // provisioned for Advanced Card Processing, or PayPal is slow), the
            // client-token transient never gets set, so without this marker EVERY
            // page render that wants the token repeats the slow, doomed roundtrip
            // and blocks the buyer each time. Skip page-render retries for a few
            // minutes after a failure; the hourly prewarm cron (free — nobody is
            // waiting on it) still retries regardless.
            $fail_marker_key = $this->is_sandbox ? 'ppcp_sandbox_client_token_failed' : 'ppcp_client_token_failed';
            if (!wp_doing_cron() && false !== get_transient($fail_marker_key)) {
                return;
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
                if (false === $this->access_token && $this->is_valid_for_use() === true) {
                    // The OAuth roundtrip itself failed with real credentials set;
                    // back off instead of repeating it on every page render.
                    set_transient($fail_marker_key, 1, 10 * MINUTE_IN_SECONDS);
                    return;
                }
            }
            if ($this->is_valid_for_use() === true && $this->access_token) {
                $response = wp_remote_post($this->generate_token_url, array(
                    'method' => 'POST',
                    'timeout' => $this->ppcp_interactive_timeout(),
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking' => true,
                    'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, 'Accept-Language' => 'en_US', 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB'),
                    'cookies' => array()
                        )
                );
                $this->ppcp_log('Get Genrate token Request ' . $this->generate_token_url);
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                    set_transient($fail_marker_key, 1, 10 * MINUTE_IN_SECONDS);
                } else {
                    $api_response = json_decode(wp_remote_retrieve_body($response), true);
                    $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                    $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                    $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                    if (!empty($api_response['client_token'])) {
                        if ($this->is_sandbox) {
                            set_transient('ppcp_sandbox_client_token', $api_response['client_token'], ($api_response['expires_in'] - 200));
                        } else {
                            set_transient('ppcp_client_token', $api_response['client_token'], ($api_response['expires_in'] - 200));
                        }
                        $this->client_token = $api_response['client_token'];
                        delete_transient($fail_marker_key);
                    } else {
                        // A 2xx/4xx body without a client_token (e.g. Advanced Card
                        // Processing not enabled on the account) is a failure too.
                        set_transient($fail_marker_key, 1, 10 * MINUTE_IN_SECONDS);
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function is_valid_for_use() {
        try {
            if (empty($this->client_id) && empty($this->secret)) {
                return false;
            }
            return true;
        } catch (Exception $ex) {

        }
    }

    /**
     * Generate (and cache) the SDK client token required by PayPal Fastlane.
     *
     * Fastlane needs the PayPal JS SDK to be loaded with a `data-sdk-client-token`
     * attribute. The token is minted via v1/oauth2/token with
     * response_type=client_token and intent=sdk_init, scoped to the shop domain.
     * PayPal validates the requesting page origin against `domains[]`, so a
     * mismatched domain silently disables Fastlane in the browser.
     *
     * @return string|false The SDK client token, or false when unavailable.
     */
    public function get_fastlane_sdk_client_token() {
        try {
            $transient_key = $this->is_sandbox ? 'ppcp_sandbox_fastlane_sdk_token' : 'ppcp_fastlane_sdk_token';
            $sdk_client_token = get_transient($transient_key);
            if (!empty($sdk_client_token)) {
                return $sdk_client_token;
            }
            // Failure backoff, mirroring get_genrate_token(): a mint that fails
            // (unprovisioned account, slow PayPal) would otherwise re-block every
            // checkout render. Fastlane silently stays off during the backoff and
            // the regular card fields keep working.
            $fail_marker_key = $transient_key . '_failed';
            if (!wp_doing_cron() && false !== get_transient($fail_marker_key)) {
                return false;
            }
            if ($this->is_valid_for_use() !== true) {
                return false;
            }
            $domain = '';
            if (!empty($_SERVER['HTTP_HOST'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $domain = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
            }
            if (empty($domain)) {
                $domain = (string) wp_parse_url(home_url(), PHP_URL_HOST);
            }
            $domain = strtolower(trim($domain));
            $domain = preg_replace('/:\d+$/', '', $domain);
            $domain = preg_replace('/^www\./', '', $domain);
            if (empty($domain)) {
                return false;
            }
            $response = wp_remote_post($this->paypal_oauth_api, array(
                'method' => 'POST',
                'timeout' => $this->ppcp_interactive_timeout(),
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . $this->basicAuth,
                    'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
                ),
                'body' => array(
                    'grant_type' => 'client_credentials',
                    'response_type' => 'client_token',
                    'intent' => 'sdk_init',
                    'domains[]' => $domain,
                ),
                'cookies' => array(),
                    )
            );
            $this->ppcp_log('Fastlane SDK client token request for domain: ' . $domain);
            if (is_wp_error($response)) {
                $this->ppcp_log('Fastlane SDK client token error: ' . wc_print_r($response->get_error_message(), true));
                set_transient($fail_marker_key, 1, 10 * MINUTE_IN_SECONDS);
                return false;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Fastlane SDK client token response code: ' . wp_remote_retrieve_response_code($response));
            if (!empty($api_response['access_token'])) {
                $expires_in = isset($api_response['expires_in']) ? absint($api_response['expires_in']) : 3600;
                set_transient($transient_key, $api_response['access_token'], max(60, $expires_in - 100));
                delete_transient($fail_marker_key);
                return $api_response['access_token'];
            }
            $this->ppcp_log('Fastlane SDK client token response missing access_token: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            set_transient($fail_marker_key, 1, 10 * MINUTE_IN_SECONDS);
        } catch (Exception $ex) {

        }
        return false;
    }

    /**
     * Process a checkout paid with a PayPal Fastlane single-use token.
     *
     * The Fastlane JS component tokenizes the buyer's card client-side; the
     * resulting single-use token is charged in one server-side Orders v2 call
     * (create with payment_source.card.single_use_token, processed immediately).
     *
     * 3DS note: verification is forced to SCA_WHEN_REQUIRED regardless of the
     * SCA_ALWAYS admin setting — Fastlane authenticates the buyer inside its own
     * component and the one-shot server flow has no client round-trip to complete
     * an external 3DS challenge, so SCA_ALWAYS would fail every Fastlane payment.
     *
     * @param int    $woo_order_id     WooCommerce order id.
     * @param string $single_use_token Fastlane single-use payment token id.
     * @param bool   $save_card        Whether the buyer asked to vault the card.
     * @return bool True when the payment completed.
     */
    public function wpg_ppcp_fastlane_payment($woo_order_id, $single_use_token, $save_card = false) {
        try {
            $payment_source = array(
                'card' => array(
                    'single_use_token' => $single_use_token,
                    'attributes' => array(
                        'verification' => array('method' => 'SCA_WHEN_REQUIRED'),
                    ),
                ),
            );
            if ($save_card && is_user_logged_in()) {
                $payment_source['card']['attributes']['vault'] = array(
                    'store_in_vault' => 'ON_SUCCESS',
                    'usage_type' => 'MERCHANT',
                );
                if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                    require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
                }
                $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
                $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id($this->is_sandbox);
                if (!empty($paypal_generated_customer_id)) {
                    $payment_source['card']['attributes']['customer'] = array('id' => $paypal_generated_customer_id);
                }
            }
            $order = wc_get_order($woo_order_id);
            if ($order instanceof WC_Order) {
                $order->update_meta_data('_wpg_ppcp_used_payment_method', 'card');
                $order->update_meta_data('_wpg_ppcp_fastlane', 'yes');
                $order->save();
                $order->add_order_note(__('Processing payment with Fastlane by PayPal.', 'woo-paypal-gateway'));
            }
            // The post-payment token-save handler keys off this session value; the
            // Fastlane flow arrives via the normal WooCommerce submit, which never
            // passes through the wc-api `used` query param that usually sets it.
            if (function_exists('woo_paypal_gateway_ppcp_set_session')) {
                woo_paypal_gateway_ppcp_set_session('wpg_payment_method', 'card');
            }
            return true === $this->wpg_ppcp_capture_order_using_payment_method_token($woo_order_id, '', null, $payment_source);
        } catch (Exception $ex) {
            $this->ppcp_log('Fastlane payment exception: ' . $ex->getMessage(), 'error');
        }
        return false;
    }

    public function ppcp_log($message, $level = 'info') {
        if ($this->log_enabled) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->log($level, $message, array('source' => 'wpg_paypal_checkout'));
        }
    }

    public function ppcp_paypalauthassertion() {
        $temp = array(
            "alg" => "none"
        );
        $returnData = base64_encode(json_encode($temp)) . '.';
        // Use environment-correct partner id and audience. Previously both were
        // hardcoded to sandbox, so Apple Pay domain registration failed in live mode.
        $partner_merchant_id = $this->is_sandbox ? WPG_SANDBOX_PARTNER_MERCHANT_ID : WPG_LIVE_PARTNER_MERCHANT_ID;
        $wallet_domains_audience = $this->is_sandbox
            ? "https://api-m.sandbox.paypal.com/v1/customer/wallet-domains"
            : "https://api-m.paypal.com/v1/customer/wallet-domains";
        $temp = array(
            "iss" => $partner_merchant_id,
            "payer_id" => $this->merchant_id,
            "aud" => $wallet_domains_audience
        );
        $returnData .= base64_encode(json_encode($temp)) . '.';
        return $returnData;
    }

    public function wpg_register_apple_domain() {
        try {
            $this->get_genrate_token();
            // Register the store's own domain (was hardcoded to an unrelated domain,
            // which made Apple Pay domain association impossible for real merchants).
            $store_domain = wp_parse_url(home_url(), PHP_URL_HOST);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            $store_domain = apply_filters('wpg_ppcp_apple_pay_domain', $store_domain);
            $body_request = array(
                'provider_type' => 'APPLE_PAY',
                'domain' => array('name' => $store_domain)
            );
            $wallet_domains = $this->is_sandbox ? 'https://api-m.sandbox.paypal.com/v1/customer/wallet-domains' : 'https://api-m.paypal.com/v1/customer/wallet-domains';
            $arg = array(
                'method' => 'POST',
                'timeout' => 60,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id(), 'Paypal-Auth-Assertion' => $this->ppcp_paypalauthassertion()),
                'body' => array(),
                'cookies' => array()
            );
            $response = wp_remote_post($wallet_domains, $arg);
            $this->ppcp_log('Register domain Request URL: ' . wc_print_r($wallet_domains, true));
            $this->ppcp_log('Register domain Request Header: ' . wc_print_r($arg['headers'], true));
            $this->ppcp_log('Register domain Request Body: ' . wc_print_r($body_request, true));
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
            } else {
                $return_response = array();
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($api_response['status'])) {
                    $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                    $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                    $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                    return $return_response;
                } else {
                    $error_message = $this->ppcp_get_readable_message($api_response);
                    $this->ppcp_log('Error Message : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_create_order_request($woo_order_id = null) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            // Derive decimal precision from the currency actually sent to PayPal (order/
            // active currency), not the store base, so zero-decimal currencies (JPY/HUF/
            // TWD) in multi-currency stores are not rejected with a 422. Set before the
            // cart/order details are built since they round using $this->decimals.
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($this->ppcp_get_currency($woo_order_id));
            if ($woo_order_id == null) {
                $cart = $this->ppcp_get_details_from_cart();
            } else {
                $cart = $this->ppcp_get_details_from_order($woo_order_id);
            }
            $order_total = woo_paypal_gateway_ppcp_round($cart['order_total'], $this->decimals);
            if ((float) $order_total <= 0) {
                $this->ppcp_log('Order creation skipped: order total is ' . $order_total . '. PayPal does not accept zero or negative amounts.');
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Your order total is zero. PayPal cannot process this payment.', 'woo-paypal-gateway'), 'error');
                }
                return false;
            }
            $reference_id = wc_generate_order_key();
            woo_paypal_gateway_ppcp_set_session('ppcp_reference_id', $reference_id);
            $intent = ($this->paymentaction === 'capture') ? 'CAPTURE' : 'AUTHORIZE';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            $intent = apply_filters('wpg_ppcp_payment_intent', $intent, $woo_order_id ? wc_get_order($woo_order_id) : null);
            $body_request = array(
                'intent' => $intent,
                'application_context' => $this->ppcp_application_context($woo_order_id, $return = false),
                'payment_method' => array('payee_preferred' => ($this->payee_preferred) ? 'IMMEDIATE_PAYMENT_REQUIRED' : 'UNRESTRICTED'),
                'purchase_units' =>
                array(
                    0 =>
                    array(
                        'reference_id' => $reference_id,
                        'amount' =>
                        array(
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                            'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                            'value' => woo_paypal_gateway_ppcp_round($cart['order_total'], $this->decimals),
                            'breakdown' => array()
                        )
                    ),
                ),
            );
            if ($woo_order_id != null) {
                $order = wc_get_order($woo_order_id);
                if ('wpg_paypal_checkout_cc' === $order->get_payment_method()) {
                    $body_request['payment_source']['card'] = array('attributes' => array('verification' => array('method' => $this->threed_secure_contingency)));
                }
                $body_request['purchase_units'][0]['soft_descriptor'] = $this->soft_descriptor;
                $body_request['purchase_units'][0]['invoice_id'] = $this->invoice_id_prefix . str_replace("#", "", $order->get_order_number());
                $body_request['purchase_units'][0]['custom_id'] = wp_json_encode(
                        array(
                            'order_id' => $order->get_id(),
                            'order_key' => $order->get_order_key(),
                        )
                );
            } else {
                $body_request['purchase_units'][0]['invoice_id'] = $reference_id;
                $body_request['purchase_units'][0]['custom_id'] = wp_json_encode(
                        array(
                            'order_id' => $reference_id,
                            'order_key' => $reference_id,
                        )
                );
            }
            if ($this->send_items === true) {
            if (isset($cart['total_item_amount']) && $cart['total_item_amount'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['item_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['total_item_amount'], $this->decimals)
                );
            }
            if (isset($cart['shipping']) && $cart['shipping'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['shipping'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['shipping'], $this->decimals)
                );
            }
            if (isset($cart['ship_discount_amount']) && $cart['ship_discount_amount'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['shipping_discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $this->decimals),
                );
            }
            if (isset($cart['order_tax']) && $cart['order_tax'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['tax_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['order_tax'], $this->decimals)
                );
            }
            if (isset($cart['discount']) && $cart['discount'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['discount'], $this->decimals)
                );
            }
                if (isset($cart['items']) && !empty($cart['items'])) {
                    foreach ($cart['items'] as $key => $order_items) {
                        $description = !empty($order_items['description']) ? $order_items['description'] : '';
                        if (strlen($description) > 127) {
                            $description = substr($description, 0, 124) . '...';
                        }
                        $name = $order_items['name'];
                        if (strlen($name) > 127) {
                            $name = substr($name, 0, 124) . '...';
                        }
                        $body_request['purchase_units'][0]['items'][$key] = array(
                            'name' => $name,
                            'description' => html_entity_decode($description, ENT_NOQUOTES, 'UTF-8'),
                            'sku' => $order_items['sku'],
                            'category' => $order_items['category'],
                            'quantity' => $order_items['quantity'],
                            'unit_amount' => array(
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                                'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                                'value' => woo_paypal_gateway_ppcp_round($order_items['amount'], $this->decimals)
                            ),
                        );
                    }
                }
            }
            if ($woo_order_id != null) {
                $order = wc_get_order($woo_order_id);
                if ($order->needs_shipping_address() || WC()->cart->needs_shipping_address()) {
                    if (($order->has_shipping_address())) {
                        $shipping_first_name = $order->get_shipping_first_name();
                        $shipping_last_name = $order->get_shipping_last_name();
                        $shipping_address_1 = $order->get_shipping_address_1();
                        $shipping_address_2 = $order->get_shipping_address_2();
                        $shipping_city = $order->get_shipping_city();
                        $shipping_state = $order->get_shipping_state();
                        $shipping_postcode = $order->get_shipping_postcode();
                        $shipping_country = $order->get_shipping_country();
                    } else {
                        $shipping_first_name = $order->get_billing_first_name();
                        $shipping_last_name = $order->get_billing_last_name();
                        $shipping_address_1 = $order->get_billing_address_1();
                        $shipping_address_2 = $order->get_billing_address_2();
                        $shipping_city = $order->get_billing_city();
                        $shipping_state = $order->get_billing_state();
                        $shipping_postcode = $order->get_billing_postcode();
                        $shipping_country = $order->get_billing_country();
                    }
                    // PayPal requires shipping.name.full_name when shipping_preference is
                    // SET_PROVIDED_ADDRESS. The PayPal Android app rejects orders that omit
                    // it with a generic "Order cannot be delivered to this address" message
                    // (desktop/iOS are more lenient). Fall back to billing name if either
                    // shipping name part is missing.
                    $full_name = trim($shipping_first_name . ' ' . $shipping_last_name);
                    if ($full_name === '') {
                        $full_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                    }
                    if ($full_name !== '') {
                        $body_request['purchase_units'][0]['shipping']['name']['full_name'] = $full_name;
                    }
                    // Normalize country code (uppercase ISO 3166-1 alpha-2) and postal code
                    // (trim + uppercase) to match what PayPal expects. Mismatched case has
                    // been observed to cause stricter validators (notably the PayPal Android
                    // app) to reject otherwise-valid addresses.
                    $shipping_country  = strtoupper(trim((string) $shipping_country));
                    $shipping_postcode = strtoupper(trim((string) $shipping_postcode));
                    if ($shipping_country !== '') {
                        $body_request['purchase_units'][0]['shipping']['address'] = array(
                            'address_line_1' => $shipping_address_1,
                            'address_line_2' => $shipping_address_2,
                            'admin_area_2' => $shipping_city,
                            'admin_area_1' => $shipping_state,
                            'postal_code' => $shipping_postcode,
                            'country_code' => $shipping_country,
                        );
                    }
                }
            } else {
                if (true === WC()->cart->needs_shipping_address()) {
                    if (is_user_logged_in()) {
                        $cart_first_name = $cart['shipping_address']['first_name'] ?? '';
                        $cart_last_name  = $cart['shipping_address']['last_name']  ?? '';
                        $full_name = trim($cart_first_name . ' ' . $cart_last_name);
                        if ($full_name === '') {
                            $billing_first = $cart['billing_address']['first_name'] ?? '';
                            $billing_last  = $cart['billing_address']['last_name']  ?? '';
                            $full_name = trim($billing_first . ' ' . $billing_last);
                        }
                        if ($full_name !== '') {
                            $body_request['purchase_units'][0]['shipping']['name']['full_name'] = $full_name;
                        }
                        // Some countries (e.g. DE) don't use admin_area_1 (state) in WC, so
                        // don't require it. Normalize country code (uppercase) and postal
                        // code (trim + uppercase) - stricter PayPal clients (Android app)
                        // have been observed to reject mismatched-case addresses with a
                        // generic "Order cannot be delivered to this address" message.
                        if (!empty($cart['shipping_address']['address_1']) && !empty($cart['shipping_address']['city']) && !empty($cart['shipping_address']['postcode']) && !empty($cart['shipping_address']['country'])) {
                            $body_request['purchase_units'][0]['shipping']['address'] = array(
                                'address_line_1' => $cart['shipping_address']['address_1'],
                                'address_line_2' => $cart['shipping_address']['address_2'] ?? '',
                                'admin_area_2' => $cart['shipping_address']['city'],
                                'admin_area_1' => $cart['shipping_address']['state'] ?? '',
                                'postal_code' => strtoupper(trim((string) $cart['shipping_address']['postcode'])),
                                'country_code' => strtoupper(trim((string) $cart['shipping_address']['country'])),
                            );
                        }
                    }
                }
            }
            if ( 'SET_PROVIDED_ADDRESS' === $body_request['application_context']['shipping_preference']
                && empty( $body_request['purchase_units'][0]['shipping']['address'] ) ) {
                $body_request['application_context']['shipping_preference'] = 'GET_FROM_FILE';
            }
            $body_request = $this->ppcp_set_payer_details($woo_order_id, $body_request);
            if (woo_paypal_gateway_ppcp_is_paypal_vault_required()) {
                $body_request = $this->ppcp_add_payment_source_parameter($body_request);
            }
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            if (isset($body_request['purchase_units'][0])) {
                $body_request['purchase_units'][0] = $this->ppcp_enforce_breakdown_invariant($body_request['purchase_units'][0], $this->decimals);
            }
            $used_payment_method = sanitize_text_field(wp_unslash($_GET['ppcp_used_payment_method'] ?? ''));
            $allowed_payment_sources = ['bancontact', 'blik', 'eps', 'ideal', 'mybank', 'p24']; // add other APMs as needed
            if (in_array($used_payment_method, $allowed_payment_sources, true)) {
                $full_name = '';
                $country_code = '';
                if (!empty($body_request['payer']['name']['given_name']) && !empty($body_request['payer']['name']['surname'])) {
                    $full_name = trim($body_request['payer']['name']['given_name'] . ' ' . $body_request['payer']['name']['surname']);
                }
                if (empty($country_code)) {
                    $country_code = $body_request['purchase_units'][0]['shipping']['address']['country_code'] ?? WC()->customer->get_billing_country();
                }
                if (!empty($full_name) && !empty($country_code)) {
                    $body_request['payment_source'] = [
                        $used_payment_method => [
                            'name' => $full_name,
                            'country_code' => strtoupper($country_code),
                        ]
                    ];
                    unset($body_request['application_context']);
                    unset($body_request['payment_method']);
                    //$body_request['processing_instruction'] = 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL';
                } else {
                    $this->ppcp_log("Missing APM payer info: name = [$full_name], country = [$country_code]");
                }
            }
            $this->ppcp_add_log_details('Create order');
            $this->ppcp_log('Order Request : ' . wc_print_r($this->ppcp_redact_sensitive_data($body_request), true));
            $body_request = json_encode($body_request);
            $response = wp_remote_post($this->paypal_order_api, array(
                'method' => 'POST',
                'timeout' => $this->ppcp_interactive_timeout(),
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                'body' => $body_request,
                'cookies' => array()
                    )
            );
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                if (ob_get_length()) {
                    ob_end_clean();
                }
                // Send a definitive error so the front-end button stops spinning on a
                // transient network failure (DNS/TLS/timeout) instead of hanging.
                wp_send_json_error($error_message);
            } else {
                if (ob_get_length())
                    ob_end_clean();
                $return_response = array();
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($api_response['status'])) {
                    $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                    $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                    $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                    $payer_action_url = woo_paypal_gateway_ppcp_get_payer_action_url_from_paypal_response($api_response);
                    if ($payer_action_url) {
                        $return_response['payer_action_url'] = $payer_action_url;
                    }
                    $return_response['orderID'] = $api_response['id'];
                    $this->ppcp_set_order_session_data($api_response['id'], 'created', $woo_order_id);
                    if (!empty(isset($woo_order_id) && !empty($woo_order_id))) {
                        woo_paypal_gateway_ppcp_set_session('ppcp_paypal_transaction_details', $api_response);
                    }
                    wp_send_json($return_response, 200);
                    exit();
                } else {
                    if(!empty($api_response)) {
                        $error_message = $this->ppcp_get_readable_message($api_response);
                        $this->ppcp_log('Error Message : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                        wp_send_json_error($error_message);
                    } else {
                        wp_send_json_error(wp_remote_retrieve_response_message($response));
                    }
                    
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_get_extra_offset_line_item($amount) {
        try {
            return array(
                'name' => 'Line Item Amount Offset',
                'description' => 'Adjust cart calculation discrepancy',
                'quantity' => 1,
                'amount' => woo_paypal_gateway_ppcp_round($amount, $this->decimals),
            );
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_get_number_of_decimal_digits($currency_code = null) {
        try {
            return $this->ppcp_is_currency_supports_zero_decimal($currency_code) ? 0 : 2;
        } catch (Exception $ex) {
            return 2;
        }
    }

    public function ppcp_get_access_token() {
        try {
            if ($this->is_valid_for_use() === false) {
                return false;
            }
            $headers = array(
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . $this->basicAuth,
                'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB'
            );
            $body = array('grant_type' => 'client_credentials');
            $response = wp_remote_post($this->paypal_oauth_api, array(
                'method' => 'POST',
                'timeout' => $this->ppcp_interactive_timeout(),
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => $headers,
                'body' => $body
            ));
            $this->ppcp_log('Get access token Request: ' . $this->paypal_oauth_api);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message: ' . $error_message);
                return false;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
            $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
            $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            if (!empty($api_response['access_token'])) {
                $transient_key = $this->is_sandbox ? 'ppcp_sandbox_access_token' : 'ppcp_access_token';
                $expires_in = isset($api_response['expires_in']) ? (int) $api_response['expires_in'] : 0;
                $buffer_seconds = 600;
                $cache_duration = max(0, $expires_in - $buffer_seconds);
                set_transient($transient_key, $api_response['access_token'], $cache_duration);
                $this->access_token = $api_response['access_token'];
                return $this->access_token;
            }
            $this->ppcp_log('Error: Access token not found in the response');
            return false;
        } catch (Exception $ex) {
            $this->ppcp_log('Exception caught: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Enforce the 3D Secure liability-shift decision before an Advanced Card
     * (wpg_paypal_checkout_cc) payment is captured or authorized.
     *
     * This is the single choke-point shared by every card capture/authorize path
     * (standard checkout, block checkout, and the classic return handler), so a card
     * order can never be charged when 3D Secure says it must be rejected or retried.
     *
     * Fails open (returns true) for non-card gateways and when the 3DS handler is
     * unavailable. For the card gateway, if PayPal order details cannot be fetched
     * (after retries) it fails CLOSED (returns false) so a transient fetch failure can
     * never silently bypass the 3D Secure liability check.
     *
     * @param int    $woo_order_id    WooCommerce order id.
     * @param string $paypal_order_id PayPal order id already resolved by the caller.
     * @return bool True to proceed with capture/authorize, false to stop.
     */
    public function ppcp_enforce_3ds_liability($woo_order_id, $paypal_order_id) {
        $order = wc_get_order($woo_order_id);
        if (!is_object($order) || empty($paypal_order_id)) {
            return true;
        }
        // The Advanced Card gateway is always enforced (unchanged behaviour). Card-backed
        // wallets (Google Pay / Apple Pay) under the main gateway also carry a 3DS
        // authentication result, but enforcing it could decline wallet payments that
        // succeed today, so wallet enforcement is opt-in. Default: behave exactly as
        // before for non-card gateways — skip with no extra API call — so no live wallet
        // payment is affected. Enable with:
        //   add_filter( 'wpg_ppcp_enforce_wallet_3ds', '__return_true' );
        if ('wpg_paypal_checkout_cc' !== $order->get_payment_method()) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            if (!apply_filters('wpg_ppcp_enforce_wallet_3ds', false, $woo_order_id)) {
                return true;
            }
        }
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_3DS')) {
            $threeds_file = WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-3ds.php';
            if (file_exists($threeds_file)) {
                include_once $threeds_file;
            }
        }
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_3DS')) {
            return true;
        }
        // Force a fresh fetch so we evaluate the authentication_result PayPal attaches
        // after the buyer completes 3D Secure, not a pre-authentication cached snapshot.
        // A snapshot fetched live earlier in this same request (e.g. by the rebinding
        // reconciliation) is reused — it postdates the 3DS challenge just the same.
        $api_response = $this->ppcp_get_checkout_details($paypal_order_id, true);
        // Retry once: a transient fetch failure must not silently bypass the
        // 3D Secure liability check for a card payment.
        for ($attempt = 0; $attempt < 1 && empty($api_response); $attempt++) {
            $api_response = $this->ppcp_get_checkout_details($paypal_order_id, true);
        }
        if (empty($api_response)) {
            // Fail closed for the card gateway: we could not verify the 3DS result, so
            // do not capture. The buyer can retry.
            $this->ppcp_log('3DS enforcement: could not fetch order details for ' . $paypal_order_id . ' after retries; blocking capture.');
            woo_paypal_gateway_send_error(array('message' => __('We could not verify your card with your bank. Please try again.', 'woo-paypal-gateway')));
            return false;
        }
        $decision = PPCP_Paypal_Checkout_For_Woocommerce_3DS::instance()->evaluate($order, $api_response);
        // Surface the message through woo_paypal_gateway_send_error() so it reaches both the classic
        // checkout (wc_add_notice) and the block checkout (stored for the redirect), which
        // does not render session notices added during the return handler.
        if (PPCP_Paypal_Checkout_For_Woocommerce_3DS::REJECT === $decision) {
            woo_paypal_gateway_send_error(array('message' => __('We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'woo-paypal-gateway')));
            return false;
        }
        if (PPCP_Paypal_Checkout_For_Woocommerce_3DS::RETRY === $decision) {
            woo_paypal_gateway_send_error(array('message' => __('We could not confirm your card with your bank. Please try again or use a different card.', 'woo-paypal-gateway')));
            return false;
        }
        return true;
    }

    public function ppcp_order_capture_request($woo_order_id, $need_to_update_order = true) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $order = wc_get_order($woo_order_id);
            if ($need_to_update_order && is_object($order)) {
                $this->ppcp_update_order($order);
            } elseif (is_object($order)) {
                $this->ppcp_repair_shipping_total_if_zero($order);
            }
            $session_order_data = $this->ppcp_get_order_session_data();
            $paypal_order_id = !empty($session_order_data['id']) ? $session_order_data['id'] : $this->ppcp_get_paypal_order_id_from_session();
            if (empty($paypal_order_id)) {
                return false;
            }
            // Direct-capture paths (public cc_capture / return capture) accept a
            // browser-supplied PayPal order id against a session-stored WooCommerce
            // order id and never patch the PayPal order to re-sync it, so the approved
            // order must be reconciled with the target order before it is captured.
            if (!$need_to_update_order && !$this->ppcp_confirm_paypal_order_for_woo_order($paypal_order_id, $woo_order_id)) {
                return false;
            }
            if (!$this->ppcp_validate_order_for_capture($paypal_order_id, $woo_order_id)) {
                return false;
            }
            if (!$this->ppcp_enforce_3ds_liability($woo_order_id, $paypal_order_id)) {
                return false;
            }
            $this->ppcp_add_log_details('Capture payment for order');
            $this->ppcp_log('Request : ' . wc_print_r($this->paypal_order_api . $paypal_order_id . '/capture', true));
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            do_action('wpg_ppcp_order_capture', $order);
            $capture_attempt = 0;
            do {
                $retry_capture = false;
                // Each attempt needs its own idempotency key: retrying with the original
                // PayPal-Request-Id after fixing a DUPLICATE_INVOICE_ID would be de-duplicated
                // by PayPal and simply replay the failed response.
                $request_id_context = 'capture-' . $paypal_order_id . ($capture_attempt > 0 ? '-retry' . $capture_attempt : '');
                // The capture attempt changes (or may change) the order's state at
                // PayPal, so the request-scoped snapshot is no longer authoritative —
                // e.g. the ORDER_ALREADY_CAPTURED recovery below must re-fetch live.
                self::ppcp_forget_order_snapshot($paypal_order_id);
                $response = wp_remote_post($this->paypal_order_api . $paypal_order_id . '/capture', array(
                    'timeout' => $this->ppcp_interactive_timeout(),
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking' => true,
                    'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id($request_id_context)),
                        )
                );
                if (is_wp_error($response)) {
                    break;
                }
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                if ($capture_attempt === 0 && empty($api_response['id']) && is_object($order) && $this->ppcp_response_has_issue($api_response, 'DUPLICATE_INVOICE_ID')) {
                    // The merchant account already holds a transaction with this invoice id
                    // (e.g. the store was reinstalled and order numbers restarted). Patch a
                    // unique suffix onto the invoice and retry the capture once so the
                    // shopper's approved payment is not lost.
                    $unique_invoice_id = $this->invoice_id_prefix . str_replace("#", "", $order->get_order_number()) . '-R' . substr((string) time(), -6);
                    $this->ppcp_log('Capture failed with DUPLICATE_INVOICE_ID for order #' . $woo_order_id . '. Retrying once with unique invoice_id: ' . $unique_invoice_id);
                    if ($this->ppcp_patch_invoice_id($paypal_order_id, $unique_invoice_id)) {
                        // translators: %s: the regenerated PayPal invoice ID.
                        $order->add_order_note(sprintf(__('PayPal rejected the invoice ID as a duplicate. Capture was retried with a unique invoice ID: %s', 'woo-paypal-gateway'), $unique_invoice_id));
                        $capture_attempt++;
                        $retry_capture = true;
                    }
                }
            } while ($retry_capture);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($error_message, 'error');
                }
                return false;
            } else {
                $return_response = array();
                if (isset($api_response['id']) && !empty($api_response['id'])) {
                    $this->ppcp_update_order_address_from_paypal_capture($woo_order_id, $api_response);
                    $return_response['paypal_order_id'] = $api_response['id'];
                    $this->ppcp_set_order_session_data($api_response['id'], 'capture', $woo_order_id);
                    if (is_object($order)) {
                        // Persist the PayPal order id so webhooks can be matched to this
                        // order locally, without an extra Get Order Details API call.
                        $order->update_meta_data('_wpg_paypal_order_id', $api_response['id']);
                        $order->save_meta_data();
                    }
                    $this->ppcp_log('Capture response for order #' . $woo_order_id . ': PayPal order status=' . (isset($api_response['status']) ? $api_response['status'] : 'N/A'));
                    if (isset($api_response['status']) && $api_response['status'] === 'COMPLETED') {
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        do_action('wpg_ppcp_save_payment_method_details', $woo_order_id, $api_response);
                        $payment_source = isset($api_response['payment_source']) ? $api_response['payment_source'] : '';
                        if (!empty($payment_source['card'])) {
                            $order->add_order_note($this->ppcp_build_card_details_note($payment_source['card']));
                        }
                        $processor_response = isset($api_response['purchase_units']['0']['payments']['captures']['0']['processor_response']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['processor_response'] : '';
                        if (!empty($processor_response['avs_code'])) {
                            $avs_response_order_note = __('Address Verification Result', 'woo-paypal-gateway');
                            $avs_response_order_note .= "\n";
                            $avs_response_order_note .= $processor_response['avs_code'];
                            if (isset($this->AVSCodes[$processor_response['avs_code']])) {
                                $avs_response_order_note .= ' : ' . $this->AVSCodes[$processor_response['avs_code']];
                            }
                            $order->add_order_note($avs_response_order_note);
                        }
                        if (!empty($processor_response['cvv_code'])) {
                            $cvv2_response_code = __('Card Security Code Result', 'woo-paypal-gateway');
                            $cvv2_response_code .= "\n";
                            $cvv2_response_code .= $processor_response['cvv_code'];
                            if (isset($this->CVV2Codes[$processor_response['cvv_code']])) {
                                $cvv2_response_code .= ' : ' . $this->CVV2Codes[$processor_response['cvv_code']];
                            }
                            $order->add_order_note($cvv2_response_code);
                        }
                        $currency_code = isset($api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['currency_code']) ? $api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['currency_code'] : '';
                        $paypal_fee = isset($api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['value']) ? $api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['value'] : '';
                        if ($paypal_fee !== '' && floatval($paypal_fee) > 0) {
                            $order->update_meta_data('_paypal_fee', $paypal_fee);
                            $order->update_meta_data('_paypal_fee_currency_code', $currency_code);
                            $order->save_meta_data();
                        }
                        $transaction_id = isset($api_response['purchase_units']['0']['payments']['captures']['0']['id']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['id'] : '';
                        $seller_protection = isset($api_response['purchase_units']['0']['payments']['captures']['0']['seller_protection']['status']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['seller_protection']['status'] : '';
                        $payment_status = isset($api_response['purchase_units']['0']['payments']['captures']['0']['status']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['status'] : '';
                        $this->ppcp_log('Capture payment_status for order #' . $woo_order_id . ': capture status=' . ($payment_status ? $payment_status : 'N/A') . ', transaction_id=' . ($transaction_id ? $transaction_id : 'N/A'));
                        if ($payment_status === 'COMPLETED') {
                            woo_paypal_gateway_set_order_payment_method_title_from_paypal_response($order, $api_response);
                            $order->payment_complete($transaction_id);
                            // translators: 1: Payment method title, 2: Payment status (e.g., Completed, Pending).
                            $order->add_order_note(sprintf(__('Payment via %1$s : %2$s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), ucfirst(strtolower($payment_status))));
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                            apply_filters('woocommerce_payment_successful_result', array('result' => 'success'), $woo_order_id);
                            $order->update_meta_data('_payment_status', $payment_status);
                            // translators: 1: Payment method title, 2: Transaction ID.
                            $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $transaction_id));
                            $order->add_order_note('Seller Protection Status: ' . woo_paypal_gateway_ppcp_readable($seller_protection));
                            $order->save_meta_data();
                            return true;
                        } else {
                            $payment_status_reason = isset($api_response['purchase_units']['0']['payments']['captures']['0']['status_details']['reason']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['status_details']['reason'] : '';
                            $bool = woo_paypal_gateway_ppcp_update_woo_order_status($woo_order_id, $payment_status, $payment_status_reason, $processor_response);
                            $order->update_meta_data('_payment_status', $payment_status);
                            $order->save_meta_data();
                            if (!empty($transaction_id)) {
                                // Persist the capture id even while the payment is pending so the
                                // order can be refunded once it settles (refunds are gated on the
                                // transaction id). Reload after the status update above so we do
                                // not overwrite the on-hold status set on a separate instance.
                                $fresh_order = wc_get_order($woo_order_id);
                                if ($fresh_order instanceof WC_Order && !$fresh_order->get_transaction_id() && !$fresh_order->has_status(wc_get_is_paid_statuses())) {
                                    $fresh_order->set_transaction_id($transaction_id);
                                    $fresh_order->save();
                                }
                            }
                            $order->add_order_note(
                            sprintf(
                                    /* translators: 1: payment method title, 2: transaction ID */
                                    __( '%1$s Transaction ID: %2$s', 'woo-paypal-gateway' ),
                                    $order->get_payment_method_title(),
                                    $transaction_id
                                )
                            );
                            $order->add_order_note('Seller Protection Status: ' . woo_paypal_gateway_ppcp_readable($seller_protection));
                            return $bool;
                        }
                    } else {
                        return false;
                    }
                } else {
                    // Lost-response / duplicate-capture recovery: PayPal reports the order
                    // as already captured, so the funds were taken even though no id came
                    // back here. Complete the order from the existing capture instead of
                    // failing it. Reconciles fail-closed before completing (see method).
                    if ($this->ppcp_response_has_issue($api_response, 'ORDER_ALREADY_CAPTURED')
                        || $this->ppcp_response_has_issue($api_response, 'ORDER_ALREADY_COMPLETED')) {
                        if ($this->ppcp_recover_already_captured_order($order, $woo_order_id, $paypal_order_id) === true) {
                            return true;
                        }
                    }
                    if (function_exists('wc_add_notice')) {
                        if(!empty($api_response)) {
                            $error_message = $this->ppcp_get_readable_message($api_response);
                            wc_add_notice($error_message, 'error');
                            $order->add_order_note($error_message);
                        } else {
                            $error_message = wp_remote_retrieve_response_message($response);
                            wc_add_notice($error_message, 'error');
                            $order->add_order_note($error_message);
                        }
                    }
                    return false;
                }
            }
        } catch (\Throwable $ex) {
            $this->ppcp_log('Capture exception for order #' . $woo_order_id . ': ' . $ex->getMessage());
            return false;
        }
    }

    private function ppcp_response_has_issue($api_response, $issue) {
        if (empty($api_response['details']) || !is_array($api_response['details'])) {
            return false;
        }
        foreach ($api_response['details'] as $detail) {
            if (isset($detail['issue']) && $detail['issue'] === $issue) {
                return true;
            }
        }
        return false;
    }

    private function ppcp_patch_invoice_id($paypal_order_id, $invoice_id) {
        $reference_id = woo_paypal_gateway_ppcp_get_session('ppcp_reference_id');
        if (empty($reference_id)) {
            $this->ppcp_log('Invoice ID patch skipped: reference_id missing from session.');
            return false;
        }
        $patch_request = array(
            array(
                'op' => 'add',
                'path' => "/purchase_units/@reference_id=='$reference_id'/invoice_id",
                'value' => $invoice_id,
            ),
        );
        // Mutating PATCH: drop the request-scoped snapshot of this order.
        self::ppcp_forget_order_snapshot($paypal_order_id);
        $response = wp_remote_request($this->paypal_order_api . $paypal_order_id, array(
            'timeout' => $this->ppcp_interactive_timeout(),
            'method' => 'PATCH',
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer " . $this->access_token,
                'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
                'PayPal-Request-Id' => $this->generate_request_id(),
            ),
            'body' => wp_json_encode($patch_request),
            'cookies' => array(),
        ));
        if (is_wp_error($response)) {
            $this->ppcp_log('Invoice ID patch failed: ' . $response->get_error_message());
            return false;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $this->ppcp_log('Invoice ID patch response code: ' . $response_code);
        return $response_code >= 200 && $response_code < 300;
    }

    public function ppcp_update_order_address_from_paypal_capture($order_id, $capture_response) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $payment_source = $capture_response['payment_source'] ?? [];
        $payer = $capture_response['payer'] ?? [];
        $shipping_unit = $capture_response['purchase_units'][0]['shipping'] ?? [];

        // Resolve the wallet/card source and the underlying card object.
        // For card source, $card === $source_data. For google_pay/apple_pay, the card lives at $source_data['card'].
        $source_data = [];
        $card = [];
        if (!empty($payment_source['google_pay'])) {
            $source_data = $payment_source['google_pay'];
            $card = is_array($source_data) ? ($source_data['card'] ?? []) : [];
        } elseif (!empty($payment_source['apple_pay'])) {
            $source_data = $payment_source['apple_pay'];
            $card = is_array($source_data) ? ($source_data['card'] ?? []) : [];
        } elseif (!empty($payment_source['card'])) {
            $source_data = $payment_source['card'];
            $card = $source_data;
        } elseif (!empty($payment_source['paypal'])) {
            $source_data = $payment_source['paypal'];
        } elseif (!empty($payment_source) && is_array($payment_source)) {
            $source_data = reset($payment_source);
        }
        if (!is_array($source_data)) {
            $source_data = [];
        }
        if (!is_array($card)) {
            $card = [];
        }

        // Billing address: prefer card.billing_address, fall back to payer.address.
        $card_billing = $card['billing_address'] ?? [];
        if (!is_array($card_billing)) {
            $card_billing = [];
        }
        $has_card_billing = !empty($card_billing['address_line_1']) || !empty($card_billing['postal_code']) || !empty($card_billing['country_code']);
        $billing_address = [];
        if ($has_card_billing) {
            $billing_address = $card_billing;
        } elseif (!empty($payer['address']) && is_array($payer['address'])) {
            $billing_address = $payer['address'];
        }

        // Shipping address: read from purchase_units[0].shipping.address.
        // Fall back to billing as last resort (preserves previous behavior).
        $shipping_address_raw = (is_array($shipping_unit) && !empty($shipping_unit['address']) && is_array($shipping_unit['address']))
            ? $shipping_unit['address']
            : [];
        if (empty($shipping_address_raw['address_line_1']) && !empty($billing_address['address_line_1'])) {
            $shipping_address_raw = $billing_address;
        }

        $phone_number = $source_data['phone_number']['national_number']
            ?? ($payer['phone']['phone_number']['national_number'] ?? '');
        $email = $source_data['email_address']
            ?? ($payer['email_address'] ?? ($shipping_unit['email_address'] ?? ''));

        // Billing name from source.name (string or {given_name, surname}); fall back to payer.name.
        $first_name = '';
        $last_name = '';
        $name = $source_data['name'] ?? ($card['name'] ?? '');
        if (is_array($name)) {
            $first_name = $name['given_name'] ?? '';
            $last_name = $name['surname'] ?? '';
        } elseif (is_string($name) && $name !== '') {
            $name_parts = explode(' ', trim($name), 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
        }
        if ($first_name === '' && $last_name === '' && !empty($payer['name']) && is_array($payer['name'])) {
            $first_name = $payer['name']['given_name'] ?? '';
            $last_name = $payer['name']['surname'] ?? '';
        }

        // Shipping name: prefer purchase_units[0].shipping.name.full_name, fall back to billing names.
        $shipping_first_name = $first_name;
        $shipping_last_name = $last_name;
        $shipping_name_full = $shipping_unit['name']['full_name'] ?? '';
        if (is_string($shipping_name_full) && trim($shipping_name_full) !== '') {
            $sparts = explode(' ', trim($shipping_name_full), 2);
            $shipping_first_name = $sparts[0] ?? $shipping_first_name;
            $shipping_last_name = $sparts[1] ?? $shipping_last_name;
        }

        if (empty($order->get_billing_first_name()) && $first_name) {
            $order->set_billing_first_name($first_name);
        }
        if (empty($order->get_billing_last_name()) && $last_name) {
            $order->set_billing_last_name($last_name);
        }

        if (empty($order->get_billing_address_1()) && !empty($billing_address['address_line_1'])) {
            $order->set_billing_address_1($billing_address['address_line_1']);
            if (method_exists($order, 'set_billing_address_2')) {
                $order->set_billing_address_2($billing_address['address_line_2'] ?? '');
            }
            $order->set_billing_city($billing_address['admin_area_2'] ?? '');
            $order->set_billing_state($billing_address['admin_area_1'] ?? '');
            $order->set_billing_postcode($billing_address['postal_code'] ?? '');
            $order->set_billing_country($billing_address['country_code'] ?? '');
        }

        if (empty($order->get_billing_phone()) && $phone_number) {
            $order->set_billing_phone($phone_number);
        }
        if (empty($order->get_billing_email()) && $email) {
            $order->set_billing_email($email);
        }

        if (empty($order->get_shipping_first_name()) && $shipping_first_name) {
            $order->set_shipping_first_name($shipping_first_name);
        }
        if (empty($order->get_shipping_last_name()) && $shipping_last_name) {
            $order->set_shipping_last_name($shipping_last_name);
        }

        if (empty($order->get_shipping_address_1()) && !empty($shipping_address_raw['address_line_1'])) {
            $order->set_shipping_address_1($shipping_address_raw['address_line_1']);
            if (method_exists($order, 'set_shipping_address_2')) {
                $order->set_shipping_address_2($shipping_address_raw['address_line_2'] ?? '');
            }
            $order->set_shipping_city($shipping_address_raw['admin_area_2'] ?? '');
            $order->set_shipping_state($shipping_address_raw['admin_area_1'] ?? '');
            $order->set_shipping_postcode($shipping_address_raw['postal_code'] ?? '');
            $order->set_shipping_country($shipping_address_raw['country_code'] ?? '');
        }

        $order->save();
    }

    public function ppcp_order_auth_request($woo_order_id, $enforce_binding = false) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $order = wc_get_order($woo_order_id);
            // Direct authorize paths (public cc_capture / return handler) must confirm
            // the approved PayPal order belongs to this WooCommerce order BEFORE the
            // order is patched/re-bound, otherwise a cheaper approved order could be
            // authorized against a higher-value order (PayPal order rebinding).
            if ($enforce_binding) {
                $session_order_data = $this->ppcp_get_order_session_data();
                $paypal_order_id = !empty($session_order_data['id']) ? $session_order_data['id'] : $this->ppcp_get_paypal_order_id_from_session();
                if (empty($paypal_order_id) || !$this->ppcp_confirm_paypal_order_for_woo_order($paypal_order_id, $woo_order_id)) {
                    return false;
                }
            }
            if (is_object($order)) {
                $this->ppcp_update_order($order);
            }
            $session_order_data = $this->ppcp_get_order_session_data();
            $paypal_order_id = !empty($session_order_data['id']) ? $session_order_data['id'] : $this->ppcp_get_paypal_order_id_from_session();
            if (!$this->ppcp_enforce_3ds_liability($woo_order_id, $paypal_order_id)) {
                return false;
            }
            $this->ppcp_add_log_details('Authorize payment for order');
            $this->ppcp_log('Request : ' . wc_print_r($this->paypal_order_api . $paypal_order_id . '/authorize', true));
            // State-changing call: drop the request-scoped snapshot so the
            // ORDER_ALREADY_AUTHORIZED recovery re-fetches live state.
            self::ppcp_forget_order_snapshot($paypal_order_id);
            $response = wp_remote_post($this->paypal_order_api . $paypal_order_id . '/authorize', array(
                'timeout' => $this->ppcp_interactive_timeout(),
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('auth-' . $paypal_order_id)),
                    )
            );
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($error_message, 'error');
                }
                return false;
            } else {
                $return_response = array();
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                if (!empty($api_response['id'])) {
                    $this->ppcp_update_order_address_from_paypal_capture($woo_order_id, $api_response);
                    $return_response['paypal_order_id'] = $api_response['id'];
                    $this->ppcp_set_order_session_data($api_response['id'], 'approved', $woo_order_id);
                    $transaction_id = isset($api_response['purchase_units']['0']['payments']['authorizations']['0']['id']) ? $api_response['purchase_units']['0']['payments']['authorizations']['0']['id'] : '';
                    $seller_protection = isset($api_response['purchase_units']['0']['payments']['authorizations']['0']['seller_protection']['status']) ? $api_response['purchase_units']['0']['payments']['authorizations']['0']['seller_protection']['status'] : '';
                    $payment_status = isset($api_response['purchase_units']['0']['payments']['authorizations']['0']['status']) ? strtoupper($api_response['purchase_units']['0']['payments']['authorizations']['0']['status']) : '';
                    $payment_status_reason = isset($api_response['purchase_units']['0']['payments']['authorizations']['0']['status_details']['reason']) ? $api_response['purchase_units']['0']['payments']['authorizations']['0']['status_details']['reason'] : '';
                    $this->ppcp_log('Authorize response status for order #' . $woo_order_id . ': order_status=' . (isset($api_response['status']) ? $api_response['status'] : 'N/A') . ', authorization_status=' . ($payment_status ? $payment_status : 'N/A'));
                    if (in_array($payment_status, array('AUTHORIZED', 'CREATED'), true)) {
                        $order->set_transaction_id($transaction_id);
                        $order->update_meta_data('_payment_status', $payment_status);
                        $order->update_meta_data('_auth_transaction_id', $transaction_id);
                        $order->update_meta_data('_payment_action', $this->paymentaction);
                        $order->save_meta_data();
                        // translators: 1: Payment method title, 2: Transaction ID.
                        $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $transaction_id));
                        $order->add_order_note('Seller Protection Status: ' . woo_paypal_gateway_ppcp_readable($seller_protection));
                        $order->update_status($this->authorized_order_status);
                        $order->add_order_note($this->get_payment_authorized_note());
                    } else {
                        $this->ppcp_log('Authorize request did not return a successful authorization status for order #' . $woo_order_id . '. Received status: ' . ($payment_status ? $payment_status : 'N/A'));
                        woo_paypal_gateway_ppcp_update_woo_order_status($woo_order_id, $payment_status ? $payment_status : 'FAILED', $payment_status_reason);
                        $order->add_order_note(
                            sprintf(
                                // translators: %1$s: PayPal authorization status.
                                __('PayPal authorization was not successful. Received status: %1$s.', 'woo-paypal-gateway'),
                                $payment_status ? $payment_status : __('N/A', 'woo-paypal-gateway')
                            )
                        );
                        return false;
                    }
                    woo_paypal_gateway_clear_ppcp_session_and_cart();
                    return true;
                } else {
                    // Lost-response / duplicate-authorize recovery — see capture path.
                    if ($this->ppcp_response_has_issue($api_response, 'ORDER_ALREADY_AUTHORIZED')
                        || $this->ppcp_response_has_issue($api_response, 'ORDER_ALREADY_CAPTURED')
                        || $this->ppcp_response_has_issue($api_response, 'ORDER_ALREADY_COMPLETED')) {
                        if ($this->ppcp_recover_already_authorized_order($order, $woo_order_id, $paypal_order_id) === true) {
                            woo_paypal_gateway_clear_ppcp_session_and_cart();
                            return true;
                        }
                    }
                    if (function_exists('wc_add_notice')) {
                        $error_message = $this->ppcp_get_readable_message($api_response);
                        wc_add_notice($error_message, 'error');
                    }
                    return false;
                }
            }
        } catch (\Throwable $ex) {
            $this->ppcp_log('Auth exception for order #' . $woo_order_id . ': ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Request-scoped snapshots of PayPal orders fetched during THIS PHP request,
     * keyed by PayPal order id.
     *
     * The rebinding reconciliation (ppcp_confirm_paypal_order_for_woo_order), the
     * 3D Secure liability check and the already-captured/authorized recovery all
     * force-refresh the SAME PayPal order back to back inside a single
     * capture/authorize request — each one a sequential PayPal roundtrip the buyer
     * waits on. A snapshot fetched earlier in this same request is exactly as
     * authoritative as a second fetch (both run after buyer approval / 3DS
     * authentication), so it is shared here. Cross-request caches (the session
     * copy, which can predate approval or authentication) are still bypassed by
     * force-refresh callers.
     *
     * Static because one flow can span the Request singleton and the gateway
     * instances that extend it. Cleared for an order id whenever a mutating call
     * (PATCH / capture / authorize) is sent for it, so post-mutation readers —
     * e.g. the ORDER_ALREADY_CAPTURED recovery — always re-fetch live state.
     *
     * @var array<string, object>
     */
    protected static $order_snapshot_memo = array();

    /**
     * Forget the request-scoped snapshot for a PayPal order. Must be called before
     * any request that can change the order's state at PayPal.
     *
     * @param string $paypal_order_id PayPal order id.
     */
    protected static function ppcp_forget_order_snapshot($paypal_order_id) {
        unset(self::$order_snapshot_memo[(string) $paypal_order_id]);
    }

    /**
     * Timeout (seconds) for PayPal calls made while a buyer is actively waiting:
     * page render token minting, create order, get details, PATCH, capture and
     * authorize.
     *
     * These calls previously used a 60s timeout; with several of them running
     * sequentially inside one checkout request, a single slow or hung connection
     * could hold the buyer (and a PHP worker) for minutes. PayPal answers
     * interactive REST calls well under 30s, so 30s is a safe ceiling. Stores on
     * exceptionally slow networks can raise it:
     *   add_filter( 'wpg_ppcp_interactive_http_timeout', function () { return 60; } );
     *
     * @return int
     */
    public function ppcp_interactive_timeout() {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        $timeout = (int) apply_filters('wpg_ppcp_interactive_http_timeout', 30);
        return max(5, $timeout);
    }

    /**
     * Record PayPal's reported status for an order in the session without ever losing
     * ground on an approval the buyer has already given.
     *
     * Reading an order must not be able to un-approve it. PayPal reports
     * PAYER_ACTION_REQUIRED for the entire life of a 3-D Secure challenge and can
     * still report it — or CREATED — for a moment after the buyer returns from a
     * banking-app approval. Writing that over the 'approved' marker the return handler
     * had just stored made the capture gate, and
     * woo_paypal_gateway_ppcp_get_paypal_order_id_from_session() (which only answers
     * for an approved order), behave as though the buyer had never approved at all:
     * the shopper was bounced back to the checkout page with "PayPal order is not
     * approved yet" for a payment they had completed.
     *
     * So the status only moves forward for a given PayPal order id
     * (created -> approved -> capture), the woo_order_id binding already stored for
     * that order is preserved instead of being reset to 0, and only VOIDED — the one
     * status that means the order is genuinely dead — is allowed to move it back, so a
     * cancelled order still yields a fresh payment attempt rather than a stuck session.
     *
     * @param string $paypal_order_id PayPal order id the status belongs to.
     * @param string $raw_status      Status string as reported by PayPal ('' when unknown).
     * @return void
     */
    private function ppcp_store_order_status_in_session($paypal_order_id, $raw_status) {
        $reported = strtoupper((string) $raw_status);

        if ($reported === 'COMPLETED') {
            $status = 'capture';
        } elseif ($reported === 'APPROVED') {
            $status = 'approved';
        } else {
            $status = 'created';
        }

        $session_data = woo_paypal_gateway_ppcp_get_paypal_order_session_data();
        $same_order   = !empty($session_data['id']) && (string) $session_data['id'] === (string) $paypal_order_id;
        $woo_order_id = ($same_order && !empty($session_data['woo_order_id'])) ? absint($session_data['woo_order_id']) : 0;

        if ($same_order && $reported !== 'VOIDED') {
            $rank         = array('created' => 0, 'approved' => 1, 'capture' => 2);
            $current      = isset($session_data['status']) ? strtolower((string) $session_data['status']) : 'created';
            $current_rank = isset($rank[$current]) ? $rank[$current] : 0;
            if ($current_rank > $rank[$status]) {
                $status = $current;
            }
        }

        $this->ppcp_set_order_session_data($paypal_order_id, $status, $woo_order_id);
    }

    /**
     * Build the "Card Details" order note from PayPal's card payment source.
     *
     * PayPal only returns the fields it has: `type` in particular is routinely absent,
     * and `brand` can be too for some networks and for vaulted charges. Reading them
     * unconditionally emitted "Undefined array key" warnings into the site's error log
     * on ordinary, successful payments. Only the details actually supplied are listed.
     *
     * @param array $card payment_source.card from a PayPal response.
     * @return string Order note body.
     */
    private function ppcp_build_card_details_note($card) {
        $card  = (array) $card;
        $note  = __('Card Details', 'woo-paypal-gateway');
        $lines = array(
            'Last digits' => isset($card['last_digits']) ? $card['last_digits'] : '',
            'Brand'       => isset($card['brand']) ? woo_paypal_gateway_ppcp_readable($card['brand']) : '',
            'Card type'   => isset($card['type']) ? woo_paypal_gateway_ppcp_readable($card['type']) : '',
        );
        foreach ($lines as $label => $value) {
            if ('' !== $value && null !== $value) {
                $note .= "\n" . $label . ' : ' . $value;
            }
        }
        return $note;
    }

    public function ppcp_get_checkout_details($paypal_order_id, $force_refresh = false) {
        try {
            if (is_wc_endpoint_url('order-received')) {
                return;
            }
            if (empty($paypal_order_id)) {
                $this->ppcp_log('Get Order Details skipped: PayPal order ID is empty.');
                return;
            }
            // Reuse a snapshot already fetched from PayPal during THIS request (and not
            // invalidated by a mutating call since) for ordinary readers.
            //
            // Force-refresh callers are NOT served from it. The order's state at PayPal
            // can change while a single request is still running — the reconciliation
            // fetch at the top of a capture routinely lands while a 3-D Secure approval
            // is still settling, and the approval gate that runs microseconds later must
            // be able to see the newer state. Serving the memo to force-refresh callers
            // made every "fetch it live and retry" loop in this class a no-op, so a
            // shopper who had approved was told their order was not approved.
            $memo_key = (string) $paypal_order_id;
            if (!$force_refresh && isset(self::$order_snapshot_memo[$memo_key])) {
                return self::$order_snapshot_memo[$memo_key];
            }
            // The 3D Secure liability check must read the authentication_result that PayPal
            // only adds to the order AFTER the buyer completes the challenge. The session
            // cache can hold a snapshot taken before authentication, so callers that need
            // the post-authentication state pass $force_refresh to bypass it.
            $cached_id = $this->ppcp_get_paypal_order_id_from_session();
            $cached_details = woo_paypal_gateway_ppcp_get_session('ppcp_paypal_transaction_details');
            if (!$force_refresh && $cached_id === $paypal_order_id && !empty($cached_details)) {
                return $cached_details;
            }
            if (empty($this->access_token)) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->ppcp_add_log_details('Get Order Details');
            $endpoint = $this->paypal_order_api . $paypal_order_id;
            $this->ppcp_log('Endpoint: ' . $endpoint);
            $response = wp_remote_get($endpoint, array(
                'timeout' => $this->ppcp_interactive_timeout(),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->access_token,
                    'Prefer' => 'return=representation',
                    'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
                ),
            ));
            if (is_wp_error($response)) {
                $this->ppcp_log('Error: ' . wc_print_r($response->get_error_message(), true));
                return null;
            }
            $code = wp_remote_retrieve_response_code($response);
            $message = wp_remote_retrieve_response_message($response);
            $body_raw = wp_remote_retrieve_body($response);
            $body = json_decode($body_raw);
            $this->ppcp_log('Response Code: ' . $code);
            $this->ppcp_log('Response Message: ' . $message);
            $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($body), true));
            $this->ppcp_store_order_status_in_session($paypal_order_id, isset($body->status) ? $body->status : '');
            woo_paypal_gateway_ppcp_set_session('ppcp_paypal_transaction_details', $body);
            // Only a real order snapshot (it always carries an id) is memoized —
            // never a transient failure or a PayPal error body, so retry loops in
            // the callers still re-fetch after a failed attempt.
            if (is_object($body) && !empty($body->id)) {
                self::$order_snapshot_memo[$memo_key] = $body;
            }
            return $body;
        } catch (Exception $ex) {
            $this->ppcp_log('Exception in ppcp_get_checkout_details: ' . $ex->getMessage());
            return null;
        }
    }

    public function ppcp_get_details_from_cart() {
        try {
            $rounded_total = $this->ppcp_get_rounded_total_in_cart();
            $discounts = WC()->cart->get_cart_discount_total();
            $details = array(
                'total_item_amount' => woo_paypal_gateway_ppcp_round(WC()->cart->cart_contents_total, $this->decimals) + $discounts,
                'order_tax' => woo_paypal_gateway_ppcp_round(WC()->cart->tax_total + WC()->cart->shipping_tax_total, $this->decimals),
                'shipping' => woo_paypal_gateway_ppcp_round(WC()->cart->shipping_total, $this->decimals),
                'items' => $this->ppcp_get_paypal_line_items_from_cart(),
                'shipping_address' => $this->ppcp_get_address_from_customer(),
                'email' => WC()->customer->get_billing_email(),
            );
            return $this->ppcp_get_details($details, $discounts, $rounded_total, WC()->cart->total);
        } catch (Exception $ex) {

        }
    }

    public function ppcp_is_currency_supports_zero_decimal($currency_code = null) {
        try {
            // Evaluate the currency actually being sent to PayPal. When no code is given,
            // resolve the active (multi-currency aware) currency rather than the store
            // base, so zero-decimal currencies are detected correctly.
            if ($currency_code === null) {
                $currency_code = $this->ppcp_get_currency();
            }
            if (class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Currency')) {
                return PPCP_Paypal_Checkout_For_Woocommerce_Currency::instance()->is_zero_decimal($currency_code);
            }
            return in_array(strtoupper((string) $currency_code), array('HUF', 'JPY', 'TWD'), true);
        } catch (Exception $ex) {
            return false;
        }
    }

    public function ppcp_get_rounded_total_in_cart() {
        try {
            $rounded_total = 0;
            foreach (WC()->cart->cart_contents as $cart_item_key => $values) {
                $amount = woo_paypal_gateway_ppcp_round($values['line_subtotal'] / $values['quantity'], $this->decimals);
                $rounded_total += woo_paypal_gateway_ppcp_round($amount * $values['quantity'], $this->decimals);
            }
            return $rounded_total;
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_get_paypal_line_items_from_cart() {
        try {
            $items = array();
            foreach (WC()->cart->cart_contents as $cart_item_key => $values) {
                $desc = '';
                $amount = woo_paypal_gateway_ppcp_round($values['line_subtotal'] / $values['quantity'], $this->decimals);
                $product = $values['data'];
                $name = $product->get_name();
                $sku = $product->get_sku();
                $category = $product->needs_shipping() ? 'PHYSICAL_GOODS' : 'DIGITAL_GOODS';
                if (is_object($product) && $product->is_type('variation')) {
                    if (!empty($values['variation']) && is_array($values['variation'])) {
                        foreach ($values['variation'] as $key => $value) {
                            $key = str_replace(array('attribute_pa_', 'attribute_', 'Pa_', 'pa_'), '', $key);
                            $desc .= ' ' . ucwords($key) . ': ' . $value;
                        }
                        $desc = trim($desc);
                    }
                }
                $name = wp_strip_all_tags($name);
                if (strlen($name) > 127) {
                    $name = substr($name, 0, 124) . '...';
                }
                $desc = !empty($desc) ? $desc : '';
                if (strlen($desc) > 127) {
                    $desc = substr($desc, 0, 124) . '...';
                }
                $desc = strip_shortcodes($desc);
                $desc = str_replace("\n", " ", $desc);
                $desc = preg_replace('/\s+/', ' ', $desc);
                $item = array(
                    'name' => $name,
                    'description' => $desc,
                    'sku' => $sku,
                    'category' => $category,
                    'quantity' => $values['quantity'],
                    'amount' => $amount,
                );
                $items[] = $item;
            }

            return $items;
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_get_address_from_customer() {
        try {
            $customer = WC()->customer;
            if ($customer->get_shipping_address() || $customer->get_shipping_address_2()) {
                $shipping_first_name = $customer->get_shipping_first_name();
                $shipping_last_name = $customer->get_shipping_last_name();
                $shipping_address_1 = $customer->get_shipping_address();
                $shipping_address_2 = $customer->get_shipping_address_2();
                $shipping_city = $customer->get_shipping_city();
                $shipping_state = $customer->get_shipping_state();
                $shipping_postcode = $customer->get_shipping_postcode();
                $shipping_country = $customer->get_shipping_country();
            } else {
                $shipping_first_name = $customer->get_billing_first_name();
                $shipping_last_name = $customer->get_billing_last_name();
                $shipping_address_1 = $customer->get_billing_address_1();
                $shipping_address_2 = $customer->get_billing_address_2();
                $shipping_city = $customer->get_billing_city();
                $shipping_state = $customer->get_billing_state();
                $shipping_postcode = $customer->get_billing_postcode();
                $shipping_country = $customer->get_billing_country();
            }
            return array(
                'first_name' => $shipping_first_name,
                'last_name' => $shipping_last_name,
                'company' => '',
                'address_1' => $shipping_address_1,
                'address_2' => $shipping_address_2,
                'city' => $shipping_city,
                'state' => $shipping_state,
                'postcode' => $shipping_postcode,
                'country' => $shipping_country,
                'phone' => $customer->get_billing_phone(),
            );
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_get_details($details, $discounts, $rounded_total, $total) {
        try {
            $discounts = woo_paypal_gateway_ppcp_round($discounts, $this->decimals);
            $details['order_total'] = woo_paypal_gateway_ppcp_round(
                    $details['total_item_amount'] + $details['order_tax'] + $details['shipping'] - $discounts, $this->decimals
            );
            $diff = 0;
            if ($details['total_item_amount'] != $rounded_total) {
                $diff = round($details['total_item_amount'] + $discounts - $rounded_total, $this->decimals);
                if (abs($diff) > 0.000001 && 0.0 !== (float) $diff) {
                    $extra_line_item = $this->ppcp_get_extra_offset_line_item($diff);
                    $details['items'][] = $extra_line_item;
                    $details['total_item_amount'] += $extra_line_item['amount'];
                    $details['total_item_amount'] = woo_paypal_gateway_ppcp_round($details['total_item_amount'], $this->decimals);
                    $details['order_total'] += $extra_line_item['amount'];
                    $details['order_total'] = woo_paypal_gateway_ppcp_round($details['order_total'], $this->decimals);
                }
            }
            if (0 == $details['total_item_amount']) {
                unset($details['items']);
            }
            if ($details['total_item_amount'] != $rounded_total) {
                unset($details['items']);
            }
            if ($details['total_item_amount'] == $discounts) {
                unset($details['items']);
            } else if ($discounts > 0 && $discounts < $details['total_item_amount'] && !empty($details['items'])) {
                $details['discount'] = $discounts;
            }
            $details['discount'] = $discounts;
            $details['ship_discount_amount'] = 0;
            $wc_order_total = woo_paypal_gateway_ppcp_round($total, $this->decimals);
            $discounted_total = woo_paypal_gateway_ppcp_round($details['order_total'], $this->decimals);
            if ($wc_order_total != $discounted_total) {
                if ($discounted_total < $wc_order_total) {
                    $details['order_tax'] += $wc_order_total - $discounted_total;
                    $details['order_tax'] = woo_paypal_gateway_ppcp_round($details['order_tax'], $this->decimals);
                } else {
                    $details['ship_discount_amount'] += $wc_order_total - $discounted_total;
                    $details['ship_discount_amount'] = woo_paypal_gateway_ppcp_round($details['ship_discount_amount'], $this->decimals);
                    $details['ship_discount_amount'] = abs($details['ship_discount_amount']);
                }
                $details['order_total'] = $wc_order_total;
            }
            if (!is_numeric($details['shipping'])) {
                $details['shipping'] = 0;
            }
            $lisum = 0;
            if (!empty($details['items'])) {
                foreach ($details['items'] as $li => $values) {
                    $lisum += $values['quantity'] * $values['amount'];
                }
            }
            if (abs($lisum) > 0.000001 && 0.0 !== (float) $diff) {
                $details['items'][] = $this->ppcp_get_extra_offset_line_item($details['total_item_amount'] - $lisum);
            }
            return $details;
        } catch (Exception $ex) {

        }
    }

    /**
     * Fail-safe breakdown guard.
     *
     * PayPal rejects an order with HTTP 422 (ITEM_TOTAL_MISMATCH / AMOUNT_MISMATCH)
     * when the amount breakdown does not reconcile: the line items must sum to
     * breakdown.item_total, and item_total + shipping + tax + handling + insurance
     * − shipping_discount − discount must equal amount.value. Per-line rounding,
     * coupon distribution or tax-inclusive pricing can make these disagree by a cent
     * and fail the whole checkout.
     *
     * This runs on the fully-assembled purchase unit just before the request is sent.
     * If the breakdown reconciles (the normal case) the purchase unit is returned
     * untouched. If it does not, the breakdown and items are dropped so PayPal receives
     * only amount.value, which always validates. Dropping the breakdown can never turn
     * a currently-successful order into a failure — an accepted order already reconciles,
     * so the guard is a no-op for it — it only rescues carts that would otherwise 422.
     *
     * @param array $purchase_unit The purchase_units[0] array (amount + optional items).
     * @param int   $decimals      Currency decimal places.
     * @return array
     */
    private function ppcp_enforce_breakdown_invariant($purchase_unit, $decimals) {
        if (!is_array($purchase_unit)) {
            return $purchase_unit;
        }
        // Escape hatch: a store can disable the guard via
        //   add_filter( 'wpg_ppcp_enforce_breakdown_invariant', '__return_false' );
        // (only reverts to the previous behaviour, which was a possible 422).
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        if (!apply_filters('wpg_ppcp_enforce_breakdown_invariant', true, $purchase_unit)) {
            return $purchase_unit;
        }
        $has_breakdown = !empty($purchase_unit['amount']['breakdown']);
        $has_items = !empty($purchase_unit['items']);
        if (!$has_breakdown && !$has_items) {
            return $purchase_unit;
        }
        if ($this->ppcp_breakdown_reconciles($purchase_unit, $decimals)) {
            return $purchase_unit;
        }
        // Breakdown does not reconcile — strip breakdown + items so PayPal only sees
        // the authoritative total. The amount value/currency are preserved exactly.
        $currency = isset($purchase_unit['amount']['currency_code']) ? $purchase_unit['amount']['currency_code'] : '';
        $value    = isset($purchase_unit['amount']['value']) ? $purchase_unit['amount']['value'] : 0;
        unset($purchase_unit['items']);
        $purchase_unit['amount'] = array(
            'currency_code' => $currency,
            'value'         => $value,
        );
        $this->ppcp_log('Breakdown did not reconcile to amount.value; dropped breakdown+items to avoid a PayPal 422 (amount preserved).');
        return $purchase_unit;
    }

    /**
     * Whether a purchase unit's items and breakdown reconcile to amount.value.
     *
     * @param array $purchase_unit
     * @param int   $decimals
     * @return bool True when consistent (or nothing to check), false when PayPal would 422.
     */
    private function ppcp_breakdown_reconciles($purchase_unit, $decimals) {
        $epsilon = 0.0000001;
        $amount_value = isset($purchase_unit['amount']['value']) ? (float) $purchase_unit['amount']['value'] : 0.0;
        $bd = (isset($purchase_unit['amount']['breakdown']) && is_array($purchase_unit['amount']['breakdown']))
            ? $purchase_unit['amount']['breakdown'] : array();
        $val = function ($key) use ($bd) {
            return isset($bd[$key]['value']) ? (float) $bd[$key]['value'] : 0.0;
        };
        $item_total = $val('item_total');
        if (!empty($bd)) {
            $sum = $item_total + $val('shipping') + $val('tax_total') + $val('handling') + $val('insurance')
                - $val('shipping_discount') - $val('discount');
            if (abs(round($sum - $amount_value, $decimals)) > $epsilon) {
                return false;
            }
        }
        if (!empty($purchase_unit['items']) && is_array($purchase_unit['items'])) {
            $li_sum = 0.0;
            foreach ($purchase_unit['items'] as $item) {
                $unit = isset($item['unit_amount']['value']) ? (float) $item['unit_amount']['value'] : 0.0;
                $qty  = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
                $li_sum += $unit * $qty;
            }
            // Items are only valid alongside a matching item_total.
            if (abs(round($li_sum - $item_total, $decimals)) > $epsilon) {
                return false;
            }
        }
        return true;
    }

    public function ppcp_get_details_from_order($order_id) {
        try {
            $order = wc_get_order($order_id);
            if ($order) {
                $this->ppcp_repair_shipping_total_if_zero($order);
            }
            $rounded_total = $this->ppcp_get_rounded_total_in_order($order);
            $details = array(
                'total_item_amount' => woo_paypal_gateway_ppcp_round($order->get_subtotal(), $this->decimals),
                'order_tax' => woo_paypal_gateway_ppcp_round($order->get_total_tax(), $this->decimals),
                'shipping' => woo_paypal_gateway_ppcp_round($order->get_shipping_total(), $this->decimals),
                'items' => $this->ppcp_get_paypal_line_items_from_order($order),
            );
            $details = $this->ppcp_get_details($details, $order->get_total_discount(), $rounded_total, $order->get_total());
            return $details;
        } catch (Exception $ex) {

        }
    }

    /**
     * WC_Checkout::create_order can persist an order with shipping_total = 0
     * while shipping line items carry the correct cost — the line items read
     * from WC()->shipping()->get_packages() rates cache while shipping_total
     * reads from WC()->cart->get_shipping_total(), and the two diverge when
     * the cart's shipping calculation hadn't completed at order-save time.
     * Detect that exact mismatch and repair via calculate_totals(false), which
     * sums existing line totals without recomputing taxes. No-op when the
     * order is already consistent.
     */
    protected function ppcp_repair_shipping_total_if_zero($order) {
        try {
            if (!$order instanceof WC_Order) {
                return;
            }
            if ((float) $order->get_shipping_total() > 0) {
                return;
            }
            $shipping_items_total = 0.0;
            foreach ($order->get_items('shipping') as $shipping_item) {
                $shipping_items_total += (float) $shipping_item->get_total();
            }
            if ($shipping_items_total > 0) {
                $order->calculate_totals(false);
                return;
            }
            if (!WC()->cart || !WC()->cart->needs_shipping()) {
                return;
            }
            add_filter('woocommerce_cart_ready_to_calc_shipping', '__return_true', 1000);
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
            if ((float) WC()->cart->get_shipping_total() <= 0) {
                return;
            }
            $packages = WC()->shipping()->get_packages();
            $chosen_methods = WC()->session ? WC()->session->get('chosen_shipping_methods', array()) : array();
            foreach ($order->get_items('shipping') as $existing_item) {
                $order->remove_item($existing_item->get_id());
            }
            foreach ($packages as $pkg_index => $pkg) {
                if (empty($pkg['rates'])) {
                    continue;
                }
                $rate_id = isset($chosen_methods[$pkg_index]) ? $chosen_methods[$pkg_index] : key($pkg['rates']);
                if (!isset($pkg['rates'][$rate_id])) {
                    $rate_id = key($pkg['rates']);
                }
                $rate = $pkg['rates'][$rate_id];
                $item = new WC_Order_Item_Shipping();
                $item->set_method_title($rate->label);
                $item->set_method_id($rate->method_id);
                $item->set_instance_id($rate->instance_id);
                $item->set_total($rate->cost);
                if ($rate->get_shipping_tax() > 0) {
                    $item->set_taxes(array('total' => $rate->get_taxes()));
                }
                $order->add_item($item);
            }
            $order->calculate_totals(false);
        } catch (Exception $ex) {

        }
    }

    public function ppcp_get_paypal_line_items_from_order($order) {
        try {
            $items = array();
            foreach ($order->get_items() as $cart_item_key => $values) {
                $desc = '';
                $amount = woo_paypal_gateway_ppcp_round($values['line_subtotal'] / $values['qty'], $this->decimals);
                $product = $values->get_product();
                $name = $product->get_name();
                $sku = $product->get_sku();
                $category = $product->needs_shipping() ? 'PHYSICAL_GOODS' : 'DIGITAL_GOODS';
                if (is_object($product)) {
                    if ($product->is_type('variation')) {
                        if (!empty($values['variation']) && is_array($values['variation'])) {
                            foreach ($values['variation'] as $key => $value) {
                                $key = str_replace(array('attribute_pa_', 'attribute_', 'Pa_', 'pa_'), '', $key);
                                $desc .= ' ' . ucwords($key) . ': ' . $value;
                            }
                            $desc = trim($desc);
                        }
                    }
                }
                $item = array(
                    'name' => $name,
                    'description' => $desc,
                    'sku' => $sku,
                    'category' => $category,
                    'quantity' => $values['quantity'],
                    'amount' => $amount,
                );
                $items[] = $item;
            }
            return $items;
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_get_rounded_total_in_order($order) {
        try {

            $order = wc_get_order($order);
            $rounded_total = 0;
            foreach ($order->get_items() as $cart_item_key => $values) {
                $amount = woo_paypal_gateway_ppcp_round($values['line_subtotal'] / $values['qty'], $this->decimals);
                $rounded_total += woo_paypal_gateway_ppcp_round($amount * $values['qty'], $this->decimals);
            }
            return $rounded_total;
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_refund_order($order_id, $amount, $reason, $transaction_id) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->ppcp_add_log_details('Refund Request');
            $this->ppcp_log('Endpoint: ' . $this->paypal_refund_api . $transaction_id . '/refund');
            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                return new WP_Error('error', __('Order not found for refund.', 'woo-paypal-gateway'));
            }
            // Refunds go to the captures API, which rejects an authorization id.
            // Orders captured before 9.1.4 stored the authorization id as the
            // transaction id, and authorize-only orders have no capture at all —
            // fail with a clear message instead of a cryptic PayPal error.
            $auth_transaction_id = $order->get_meta('_auth_transaction_id');
            if (!empty($auth_transaction_id) && $transaction_id === $auth_transaction_id) {
                $this->ppcp_log('Refund blocked for order #' . $order_id . ': transaction id ' . $transaction_id . ' is an authorization id, not a capture id.');
                return new WP_Error('error', __('This order cannot be refunded automatically because only captured payments can be refunded. If the payment is still authorize-only, capture it first. If it was already captured, refund the capture from your PayPal dashboard.', 'woo-paypal-gateway'));
            }
            // Match decimal precision to the order currency the refund is sent in.
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($order->get_currency());
            $reason = !empty($reason) ? $reason : 'Refund';
            $body_request = array('note_to_payer' => $reason);
            if (!empty($amount) && $amount > 0) {
                // Merge, do not overwrite: keep note_to_payer alongside the amount.
                $body_request['amount'] = array(
                    'value' => woo_paypal_gateway_ppcp_round($amount, $this->decimals),
                    'currency_code' => $order->get_currency()
                );
            }
            // Retry-safe idempotency key: identical on a retry of the SAME refund (a
            // failed WC refund is deleted, so the refunded total is unchanged), but
            // distinct for a later legitimate refund of the same amount (the already
            // refunded total has advanced), so repeat equal partial refunds are not
            // wrongly de-duplicated by PayPal.
            $refund_sequence = $order->get_total_refunded();
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            $body_request = json_encode($body_request);
            $this->ppcp_log('Refund request: ' . $body_request);
            $response = wp_remote_post($this->paypal_refund_api . $transaction_id . '/refund', array(
                'timeout' => 60,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('refund-' . $order_id . '-' . $amount . '-' . $refund_sequence)),
                'body' => $body_request,
                'cookies' => array()
                    )
            );
            if (is_wp_error($response)) {
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                $order->add_order_note('Error Failed Message : ' . wc_print_r($error_message, true));
                return new WP_Error('error', $error_message);
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
            $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
            $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            if (isset($api_response['status']) && $api_response['status'] == "COMPLETED") {
                $gross_amount = isset($api_response['seller_payable_breakdown']['gross_amount']['value']) ? $api_response['seller_payable_breakdown']['gross_amount']['value'] : '';
                $refund_transaction_id = isset($api_response['id']) ? $api_response['id'] : '';
                // translators: 1: Refunded amount, 2: Refund transaction ID.
                $order->add_order_note(sprintf(__('Refunded %1$s - Refund ID: %2$s', 'woo-paypal-gateway'), $gross_amount, $refund_transaction_id));
            } else {
                if (!empty($api_response['details'][0]['description'])) {
                    $order->add_order_note('Error Message : ' . wc_print_r($api_response['details'][0]['description'], true));
                    throw new Exception($api_response['details'][0]['description']);
                }
                return false;
            }
            return true;
        } catch (Exception $ex) {
            return new WP_Error('error', $ex->getMessage());
        }
    }

    public function ppcp_update_order($order) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }

            $patch_request = array();
            $update_amount_request = array();
            $reference_id = woo_paypal_gateway_ppcp_get_session('ppcp_reference_id');
            $order_id = $order->get_id();
            // Match decimal precision to the order currency actually sent to PayPal.
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($order->get_currency());
            $cart = $this->ppcp_get_details_from_order($order_id);

            $order_total = woo_paypal_gateway_ppcp_round($cart['order_total'], $this->decimals);
            if ((float) $order_total <= 0) {
                $this->ppcp_log('Update order skipped: order total is ' . $order_total . ' for order #' . $order_id . '. PayPal does not accept zero or negative amounts.');
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Your order total is zero. PayPal cannot process this payment.', 'woo-paypal-gateway'), 'error');
                }
                return false;
            }

            // Shipping or Billing Address
            if ($order->has_shipping_address()) {
                $shipping_address_1 = $order->get_shipping_address_1();
                $shipping_address_2 = $order->get_shipping_address_2();
                $shipping_city = $order->get_shipping_city();
                $shipping_state = $order->get_shipping_state();
                $shipping_postcode = $order->get_shipping_postcode();
                $shipping_country = $order->get_shipping_country();
            } else {
                $shipping_address_1 = $order->get_billing_address_1();
                $shipping_address_2 = $order->get_billing_address_2();
                $shipping_city = $order->get_billing_city();
                $shipping_state = $order->get_billing_state();
                $shipping_postcode = $order->get_billing_postcode();
                $shipping_country = $order->get_billing_country();
            }
            
            $shipping_address_request = array();

            if (!empty($shipping_address_1) && !empty($shipping_city)) {
                // array_filter drops empty components (e.g. a blank address_line_2)
                // so PayPal never receives empty-string address fields.
                $shipping_address_request = array_filter(array(
                    'address_line_1' => $shipping_address_1,
                    'address_line_2' => $shipping_address_2,
                    'admin_area_2' => $shipping_city,
                    'admin_area_1' => $shipping_state,
                    'postal_code' => $shipping_postcode,
                    'country_code' => $shipping_country,
                ));
            }

            if ($this->send_items === true) {
            if (isset($cart['total_item_amount']) && $cart['total_item_amount'] > 0) {
                $update_amount_request['item_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['total_item_amount'], $this->decimals)
                );
            }
            if (isset($cart['discount']) && $cart['discount'] > 0) {
                $update_amount_request['discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['discount'], $this->decimals)
                );
            }
            if (isset($cart['shipping']) && $cart['shipping'] > 0) {
                $update_amount_request['shipping'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['shipping'], $this->decimals)
                );
            }
            if (isset($cart['ship_discount_amount']) && $cart['ship_discount_amount'] > 0) {
                $update_amount_request['shipping_discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $this->decimals),
                );
            }
            if (isset($cart['order_tax']) && $cart['order_tax'] > 0) {
                $update_amount_request['tax_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($order_id)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['order_tax'], $this->decimals)
                );
            }
            }

            $amount_value = array(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($order_id)),
                'value' => woo_paypal_gateway_ppcp_round($cart['order_total'], $this->decimals),
            );
            if (!empty($update_amount_request)) {
                $amount_value['breakdown'] = $update_amount_request;
            }
            // Fail-safe: if the breakdown does not reconcile to amount.value, drop it so
            // the PATCH cannot be rejected by PayPal with a breakdown mismatch. Reuses the
            // same guard as order create (no items in a PATCH amount, so only the
            // breakdown-sum check applies). A consistent breakdown is left untouched.
            $guarded_pu = $this->ppcp_enforce_breakdown_invariant(array('amount' => $amount_value), $this->decimals);
            if (isset($guarded_pu['amount'])) {
                $amount_value = $guarded_pu['amount'];
            }
            $patch_request[] = array(
                'op' => 'add',
                'path' => "/purchase_units/@reference_id=='$reference_id'/amount",
                'value' => $amount_value,
            );

            if(!empty($shipping_address_request)) {
                // Update shipping address
                $patch_request[] = array(
                    'op' => 'add',
                    'path' => "/purchase_units/@reference_id=='$reference_id'/shipping/address",
                    'value' => $shipping_address_request
                );
            }

            // Update invoice ID
            $patch_request[] = array(
                'op' => 'add',
                'path' => "/purchase_units/@reference_id=='$reference_id'/invoice_id",
                'value' => $this->invoice_id_prefix . str_replace("#", "", $order->get_order_number())
            );

            // Update custom ID
            $update_custom_id = wp_json_encode(
                    array(
                        'order_id' => $order->get_id(),
                        'order_key' => $order->get_order_key(),
                    )
            );
            $patch_request[] = array(
                'op' => 'add',
                'path' => "/purchase_units/@reference_id=='$reference_id'/custom_id",
                'value' => $update_custom_id
            );

            // Convert the patch request array to JSON
            $patch_request_json = json_encode($patch_request);

            // Retrieve the PayPal order ID and send the patch request to update the order
            $paypal_order_id = $this->ppcp_get_paypal_order_id_from_session();
            if (empty($paypal_order_id)) {
                $this->ppcp_log('Update order skipped: PayPal order ID is missing from session.');
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Unable to update order — PayPal session has expired. Please try again.', 'woo-paypal-gateway'), 'error');
                }
                return false;
            }
            $this->ppcp_add_log_details('Update order');
            $this->ppcp_log('Endpoint: ' . $this->paypal_order_api . $paypal_order_id);
            $this->ppcp_log('Request: ' . wc_print_r($patch_request_json, true));

            // The PATCH changes amounts/addresses at PayPal: any snapshot fetched
            // earlier in this request no longer reflects the order.
            self::ppcp_forget_order_snapshot($paypal_order_id);
            // Send the request to PayPal
            $response = wp_remote_request($this->paypal_order_api . $paypal_order_id, array(
                'timeout' => $this->ppcp_interactive_timeout(),
                'method' => 'PATCH',
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer " . $this->access_token,
                    "prefer" => "return=representation",
                    'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
                    'PayPal-Request-Id' => $this->generate_request_id()
                ),
                'body' => $patch_request_json,
                'cookies' => array()
            ));

            // Handle response errors or log response details
            if (is_wp_error($response)) {
                if (function_exists('wc_add_notice')) {
                    $error_message = $response->get_error_message();
                    $this->ppcp_log('Error Message : ' . wc_print_r($response, true));
                    wc_add_notice($error_message, 'error');
                }
                return false;
            } else {
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            }
        } catch (Exception $ex) {
            $this->ppcp_log('Exception: ' . $ex->getMessage());
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('An error occurred while updating the order.', 'woo-paypal-gateway'), 'error');
            }
        }
    }

    public function ppcp_show_details_authorized_payment($authorization_id) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->ppcp_add_log_details('Show details for authorized payment');
            $this->ppcp_log('Endpoint: ' . $this->auth . $authorization_id);
            $response = wp_remote_get($this->auth . $authorization_id, array(
                'timeout' => 60,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB'),
                'body' => array(),
                'cookies' => array()
                    )
            );
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
            } else {
                $api_response = json_decode(wp_remote_retrieve_body($response));
                $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                woo_paypal_gateway_ppcp_set_session('ppcp_paypal_transaction_details', $api_response);
                return $api_response;
            }
        } catch (Exception $ex) {
            
        }
    }

    /**
     * Void an uncaptured authorization at PayPal so the hold on the buyer's
     * funds is released immediately instead of expiring after ~29 days.
     *
     * @param int $woo_order_id WooCommerce order id.
     * @return bool True when PayPal confirmed the void (HTTP 204).
     */
    public function ppcp_void_authorized_payment($woo_order_id) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $order = wc_get_order($woo_order_id);
            if (!$order instanceof WC_Order) {
                return false;
            }
            $authorization_id = $order->get_meta('_auth_transaction_id');
            if (empty($authorization_id)) {
                return false;
            }
            $this->ppcp_add_log_details('Void authorized payment');
            $this->ppcp_log('Request : ' . wc_print_r($this->auth . $authorization_id . '/void', true));
            $response = wp_remote_post($this->auth . $authorization_id . '/void', array(
                'timeout' => 60,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('void-' . $woo_order_id)),
                'cookies' => array()
                    )
            );
            if (is_wp_error($response)) {
                $this->ppcp_log('Error Message : ' . wc_print_r($response->get_error_message(), true));
                return false;
            }
            $response_code = wp_remote_retrieve_response_code($response);
            $this->ppcp_log('Response Code: ' . $response_code);
            if (204 === (int) $response_code) {
                $order->update_meta_data('_payment_status', 'VOIDED');
                $order->save_meta_data();
                // translators: %s: PayPal authorization ID.
                $order->add_order_note(sprintf(__('Authorization %s voided at PayPal. The hold on the customer\'s funds has been released.', 'woo-paypal-gateway'), $authorization_id));
                return true;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            if (!empty($api_response)) {
                // translators: %1$s: authorization ID, %2$s: error message from PayPal.
                $order->add_order_note(sprintf(__('Void of authorization %1$s failed: %2$s', 'woo-paypal-gateway'), $authorization_id, $this->ppcp_get_readable_message($api_response)));
            }
            return false;
        } catch (Exception $ex) {
            $this->ppcp_log('Void exception for order #' . $woo_order_id . ': ' . $ex->getMessage());
            return false;
        }
    }

    public function ppcp_capture_authorized_payment($woo_order_id) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $order = wc_get_order($woo_order_id);
            if (!$order instanceof WC_Order) {
                return false;
            }
            // Match decimal precision to the order currency so a deferred (authorize
            // payment action) capture of a zero-decimal currency is not rejected with 422.
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($order->get_currency());
            $capture_arg = array(
                'amount' =>
                array(
                    'value' => woo_paypal_gateway_ppcp_round($order->get_total(), $this->decimals),
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id)),
                ),
                'invoice_id' => $this->invoice_id_prefix . str_replace("#", "", $order->get_order_number()),
                'final_capture' => true,
            );
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($capture_arg);
            $body_request = json_encode($body_request);
            $authorization_id = $order->get_meta('_auth_transaction_id');
            $this->ppcp_add_log_details('Capture authorized payment');
            $this->ppcp_log('Request : ' . wc_print_r($this->auth . $authorization_id . '/capture', true));
            $response = wp_remote_post($this->auth . $authorization_id . '/capture', array(
                'timeout' => 60,
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('auth-capture-' . $woo_order_id)),
                'body' => $body_request,
                'cookies' => array()
                    )
            );
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($error_message, 'error');
                }
                return false;
            } else {
                $return_response = array();
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                if (!empty($api_response['id'])) {
                    $return_response['paypal_order_id'] = $api_response['id'];
                    $this->ppcp_set_order_session_data($api_response['id'], 'capture', $woo_order_id);
                    $payment_source = isset($api_response['payment_source']) ? $api_response['payment_source'] : '';
                    if (!empty($payment_source['card'])) {
                        $order->add_order_note($this->ppcp_build_card_details_note($payment_source['card']));
                    }
                    $processor_response = isset($api_response['purchase_units']['0']['payments']['captures']['0']['processor_response']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['processor_response'] : '';
                    if (!empty($processor_response['avs_code'])) {
                        $avs_response_order_note = __('Address Verification Result', 'woo-paypal-gateway');
                        $avs_response_order_note .= "\n";
                        $avs_response_order_note .= $processor_response['avs_code'];
                        if (isset($this->AVSCodes[$processor_response['avs_code']])) {
                            $avs_response_order_note .= ' : ' . $this->AVSCodes[$processor_response['avs_code']];
                        }
                        $order->add_order_note($avs_response_order_note);
                    }
                    if (!empty($processor_response['cvv_code'])) {
                        $cvv2_response_code = __('Card Security Code Result', 'woo-paypal-gateway');
                        $cvv2_response_code .= "\n";
                        $cvv2_response_code .= $processor_response['cvv_code'];
                        if (isset($this->CVV2Codes[$processor_response['cvv_code']])) {
                            $cvv2_response_code .= ' : ' . $this->CVV2Codes[$processor_response['cvv_code']];
                        }
                        $order->add_order_note($cvv2_response_code);
                    }
                    $currency_code = isset($api_response['seller_receivable_breakdown']['paypal_fee']['currency_code']) ? $api_response['seller_receivable_breakdown']['paypal_fee']['currency_code'] : '';
                    $paypal_fee = isset($api_response['seller_receivable_breakdown']['paypal_fee']['value']) ? $api_response['seller_receivable_breakdown']['paypal_fee']['value'] : '';
                    if ($paypal_fee !== '' && floatval($paypal_fee) > 0) {
                        $order->update_meta_data('_paypal_fee', $paypal_fee);
                        $order->update_meta_data('_paypal_fee_currency_code', $currency_code);
                        $order->save_meta_data();
                    }
                    $transaction_id = isset($api_response['id']) ? $api_response['id'] : '';
                    $seller_protection = isset($api_response['seller_protection']['status']) ? $api_response['seller_protection']['status'] : '';
                    $payment_status = isset($api_response['status']) ? $api_response['status'] : '';
                    $order->update_meta_data('_payment_status', $payment_status);
                    $order->save_meta_data();
                    // translators: 1: Payment method title, 2: Transaction ID.
                    $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $transaction_id));
                    $order->add_order_note('Seller Protection Status: ' . woo_paypal_gateway_ppcp_readable($seller_protection));
                    if ($payment_status === 'COMPLETED') {
                        woo_paypal_gateway_set_order_payment_method_title_from_paypal_response($order, $api_response);
                        // Replace the authorization id with the capture id and persist it
                        // explicitly: when the capture was triggered by an admin status
                        // change the order is already in a paid status, so
                        // payment_complete() below is a no-op and would never save the
                        // capture id. Refunds are sent to the captures API, which
                        // rejects an authorization id.
                        if (!empty($transaction_id)) {
                            $order->set_transaction_id($transaction_id);
                            $order->save();
                        }
                        $order->payment_complete($transaction_id);
                        // translators: 1: Payment method title, 2: Payment status.
                        $order->add_order_note(sprintf(__('Payment via %1$s : %2$s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), ucfirst(strtolower($payment_status))));
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        apply_filters('woocommerce_payment_successful_result', array('result' => 'success'), $woo_order_id);
                        return true;
                    } else {
                        $payment_status_reason = isset($api_response['purchase_units']['0']['payments']['captures']['0']['status_details']['reason']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['status_details']['reason'] : '';
                        $bool = woo_paypal_gateway_ppcp_update_woo_order_status($woo_order_id, $payment_status, $payment_status_reason, $processor_response);
                        if (!empty($transaction_id)) {
                            // Reload so the status update above is not clobbered, then
                            // persist the capture id for the eventual refund.
                            $fresh_order = wc_get_order($woo_order_id);
                            if ($fresh_order instanceof WC_Order) {
                                $fresh_order->set_transaction_id($transaction_id);
                                $fresh_order->save();
                            }
                        }
                        return $bool;
                    }
                    return true;
                } else {
                    if (function_exists('wc_add_notice')) {
                        $error_message = $this->ppcp_get_readable_message($api_response);
                        wc_add_notice($error_message, 'error');
                    }
                    return false;
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_add_log_details($action_name = null) {
        // translators: %s: Plugin version number.
        $this->ppcp_log(sprintf(__('Payment Gateway for PayPal on WooCommerce: %1$s', 'woo-paypal-gateway'), WPG_PLUGIN_VERSION));
        // translators: %s: WooCommerce version number.
        $this->ppcp_log(sprintf(__('WooCommerce Version: %1$s', 'woo-paypal-gateway'), WC_VERSION));
        $mode = $this->is_sandbox ? 'Yes' : 'No';
        $this->ppcp_log("Test Mode: " . $mode);
        $this->ppcp_log('Action Name : ' . $action_name);
    }

    public function ppcp_get_readable_message($error) {
        // Some callers pass the raw wp_remote_* response array (with
        // 'headers'/'body'/'response' keys) rather than the decoded JSON body.
        // Unwrap it so the parser below can read PayPal's error shape — and so we
        // never hand a non-string back to add_order_note(), where wpdb rejects an
        // array value ("Unsupported value type (array)").
        if (is_array($error) && !isset($error['name']) && !isset($error['message']) && isset($error['body']) && is_string($error['body'])) {
            $decoded_body = json_decode($error['body'], true);
            if (is_array($decoded_body)) {
                $error = $decoded_body;
            }
        }
        $message = '';
        if (is_array($error) && isset($error['name'])) {
            switch ($error['name']) {
                case 'VALIDATION_ERROR':
                    if (!empty($error['details']) && is_array($error['details'])) {
                        foreach ($error['details'] as $e) {
                            $message .= "\t" . $e['field'] . "\n\t" . $e['issue'] . "\n\n";
                        }
                    }
                    break;
                case 'INVALID_REQUEST':
                    if (!empty($error['details']) && is_array($error['details'])) {
                        foreach ($error['details'] as $e) {
                            if (isset($e['field']) && isset($e['description'])) {
                                $message .= "\t" . $e['field'] . "\n\t" . $e['description'] . "\n\n";
                            } elseif (isset($e['issue'])) {
                                $message .= "\t" . $e['issue'] . "\n\n";
                            }
                        }
                    }
                    break;
                case 'BUSINESS_ERROR':
                    $message .= isset($error['message']) ? $error['message'] : '';
                    break;
                case 'UNPROCESSABLE_ENTITY' :
                    if (!empty($error['details']) && is_array($error['details'])) {
                        foreach ($error['details'] as $e) {
                            $issue = isset($e['issue']) ? $e['issue'] : '';
                            $description = isset($e['description']) ? $e['description'] : '';
                            $message .= "\t" . $issue . ": " . $description . "\n\n";
                        }
                    }
                    break;
            }
        }
        if (!empty($message)) {
            // Built from the structured details above.
        } else if (is_array($error) && !empty($error['message'])) {
            $message = $error['message'];
        } else if (is_array($error) && !empty($error['error_description'])) {
            $message = $error['error_description'];
        } else if (is_string($error)) {
            $message = $error;
        } else {
            // Never return a non-string: add_order_note() -> wpdb cannot store an array.
            $message = __('An unexpected error occurred while processing the payment. Please try again.', 'woo-paypal-gateway');
        }
        return is_string($message) ? $message : (string) $message;
    }

    public function ppcp_create_webhooks_request() {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            if ($this->is_valid_for_use() === true && $this->access_token) {
                $webhook_request = array();
                $webhook_request['url'] = add_query_arg(array('ppcp_action' => 'webhook_handler', 'utm_nooverride' => '1'), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'));
                $webhook_request['event_types'][] = array('name' => 'CHECKOUT.ORDER.APPROVED');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.AUTHORIZATION.CREATED');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.AUTHORIZATION.VOIDED');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.CAPTURE.COMPLETED');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.CAPTURE.DECLINED');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.CAPTURE.DENIED');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.CAPTURE.PENDING');
                $webhook_request['event_types'][] = array('name' => 'PAYMENT.CAPTURE.REFUNDED');
                $webhook_request['event_types'][] = array('name' => 'CUSTOMER.DISPUTE.CREATED');
                $webhook_request['event_types'][] = array('name' => 'CUSTOMER.DISPUTE.UPDATED');
                $webhook_request['event_types'][] = array('name' => 'CUSTOMER.DISPUTE.RESOLVED');
                $webhook_request = woo_paypal_gateway_ppcp_remove_empty_key($webhook_request);
                $webhook_request = json_encode($webhook_request);
                $response = wp_remote_post($this->webhook, array(
                    'method' => 'POST',
                    'timeout' => 60,
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking' => true,
                    'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                    'body' => $webhook_request,
                    'cookies' => array()
                        )
                );
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                } else {
                    ob_start();
                    $return_response = array();
                    $api_response = json_decode(wp_remote_retrieve_body($response), true);
                    $this->ppcp_log('function called: ppcp_create_webhooks_request');
                    if (!empty($api_response['id'])) {
                        $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                        $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                        $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                        update_option($this->webhook_id, $api_response['id']);
                    } else {
                        $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                        $error = $this->ppcp_get_readable_message($api_response);
                        $this->ppcp_log('Response Message: ' . wc_print_r($error, true));
                        if (isset($api_response['name']) && strpos($api_response['name'], 'WEBHOOK_NUMBER_LIMIT_EXCEEDED') !== false) {
                            $this->ppcp_delete_first_webhook();
                        } elseif ($api_response['name'] && strpos($api_response['name'], 'WEBHOOK_URL_ALREADY_EXISTS') !== false) {
                            $this->ppcp_delete_exiting_webhook();
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_delete_exiting_webhook() {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $response = wp_remote_get($this->webhook, array('headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB')));
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($api_response['webhooks'])) {
                foreach ($api_response['webhooks'] as $key => $webhooks) {
                    if (isset($webhooks['url']) && strpos($webhooks['url'], site_url()) !== false) {
                        $response = wp_remote_request($this->webhook . '/' . $webhooks['id'], array(
                            'timeout' => 60,
                            'method' => 'DELETE',
                            'redirection' => 5,
                            'httpversion' => '1.1',
                            'blocking' => true,
                            'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                            'cookies' => array()
                                )
                        );
                        $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                        $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                        $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_delete_first_webhook() {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $response = wp_remote_get($this->webhook, array('headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB')));
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($api_response['webhooks'])) {
                foreach ($api_response['webhooks'] as $key => $webhooks) {
                    $response = wp_remote_request($this->webhook . $webhooks['id'], array(
                        'timeout' => 60,
                        'method' => 'DELETE',
                        'redirection' => 5,
                        'httpversion' => '1.1',
                        'blocking' => true,
                        'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                        'cookies' => array()
                            )
                    );
                    $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                    $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                    $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                    return false;
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_handle_webhook_request_handler() {
        try {
            $bool = false;
            if ($this->is_valid_for_use() === true && $this->access_token == false) {
                $this->ppcp_get_access_token();
            }
            if ($this->is_valid_for_use() === true && $this->access_token) {
                $posted_raw = woo_paypal_gateway_ppcp_get_raw_data();
                if (empty($posted_raw)) {
                    return true;
                }
                $headers = $this->getallheaders_value();
                $headers = array_change_key_case($headers, CASE_UPPER);
                $posted = json_decode($posted_raw, true);
                $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($posted), true));
                $this->ppcp_log('Headers: ' . wc_print_r($headers, true));
                $bool = $this->ppcp_validate_webhook_event($headers, $posted);
                if ($bool) {
                    // False here means the order could not be resolved yet
                    // (e.g. the webhook raced the WC order creation) — a non-2xx
                    // response tells PayPal to retry delivery instead of us
                    // silently dropping the event.
                    return $this->ppcp_update_order_status($posted);
                }
            }
        } catch (\Throwable $ex) {
            // A processing error must NOT be silently acknowledged, or PayPal will not
            // redeliver and the event is lost. Return false so PayPal retries. The status
            // handlers are idempotent (has_status guards + refund-gap dedup), so a
            // redelivered event that was already processed is a safe no-op.
            $this->ppcp_log('Webhook processing exception; asking PayPal to retry delivery: ' . $ex->getMessage());
            return false;
        }
        return true;
    }

    public function getallheaders_value() {
        try {
            if (!function_exists('getallheaders')) {
                return $this->getallheaders_custome();
            } else {
                return getallheaders();
            }
        } catch (Exception $ex) {
            
        }
    }

    public function getallheaders_custome() {
        try {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_validate_webhook_event($headers, $body) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->ppcp_prepare_webhook_validate_request($headers, $body);
            if (!empty($this->request)) {
                $response = wp_remote_post($this->webhook_url, array(
                    'method' => 'POST',
                    'timeout' => 60,
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking' => true,
                    'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                    'body' => json_encode($this->request),
                    'cookies' => array()
                        )
                );
            } else {
                return false;
            }
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Webhook Error Message : ' . wc_print_r($error_message, true));
                return false;
            } else {
                $api_response = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($api_response['verification_status']) && 'SUCCESS' === $api_response['verification_status']) {
                    $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                    $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                    $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
                    return true;
                } else {
                    return false;
                }
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    public function ppcp_prepare_webhook_validate_request($headers, $body) {
        try {
            $this->request = array();
            $webhook_id = get_option($this->webhook_id, false);
            $this->request['transmission_id'] = $headers['PAYPAL-TRANSMISSION-ID'];
            $this->request['transmission_time'] = $headers['PAYPAL-TRANSMISSION-TIME'];
            $this->request['cert_url'] = $headers['PAYPAL-CERT-URL'];
            $this->request['auth_algo'] = $headers['PAYPAL-AUTH-ALGO'];
            $this->request['transmission_sig'] = $headers['PAYPAL-TRANSMISSION-SIG'];
            $this->request['webhook_id'] = $webhook_id;
            $this->request['webhook_event'] = $body;
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_update_order_status($posted) {
        $incoming_event = isset($posted['event_type']) ? $posted['event_type'] : '';
        if (0 === strpos($incoming_event, 'CUSTOMER.DISPUTE.')) {
            return $this->ppcp_handle_dispute_event($posted);
        }
        $order = false;
        $had_order_reference = false;
        if (!empty($posted['resource']['purchase_units'][0]['custom_id'])) {
            $had_order_reference = true;
            $order = $this->ppcp_get_paypal_order($posted['resource']['purchase_units'][0]['custom_id']);
        } elseif (!empty($posted['resource']['custom_id'])) {
            $had_order_reference = true;
            $order = $this->ppcp_get_paypal_order($posted['resource']['custom_id']);
        }
        if (!$order) {
            $paypal_order_id = '';
            if (!empty($posted['resource']['id']) && !empty($posted['resource']['purchase_units'])) {
                $paypal_order_id = $posted['resource']['id'];
            } elseif (!empty($posted['resource']['supplementary_data']['related_ids']['order_id'])) {
                $paypal_order_id = $posted['resource']['supplementary_data']['related_ids']['order_id'];
            }
            if (!empty($paypal_order_id)) {
                $had_order_reference = true;
                // Cheap local lookup first: the capture flow stores the PayPal order id
                // on the WC order, so most webhooks can be matched without an API call.
                $order = $this->ppcp_get_order_by_paypal_order_id($paypal_order_id);
                if (!$order) {
                    $this->ppcp_log('Webhook custom_id lookup failed — fetching latest order details from PayPal: ' . $paypal_order_id);
                    $fresh_details = $this->ppcp_get_checkout_details($paypal_order_id);
                    if ($fresh_details && !empty($fresh_details->purchase_units[0]->custom_id)) {
                        $order = $this->ppcp_get_paypal_order($fresh_details->purchase_units[0]->custom_id);
                        if ($order) {
                            // Remember the mapping so later webhooks for this order match locally.
                            $order->update_meta_data('_wpg_paypal_order_id', $paypal_order_id);
                            $order->save_meta_data();
                        }
                    }
                }
            }
        }
        if (!$order) {
            // CHECKOUT.ORDER.APPROVED is informational (no status change is made
            // even when resolved) and can legitimately never resolve — the buyer
            // may approve in the popup and abandon before a WC order is created.
            // Retrying it would just make PayPal redeliver a no-op for days.
            if ($had_order_reference && 'CHECKOUT.ORDER.APPROVED' !== $incoming_event) {
                $this->ppcp_log('Webhook order resolution failed for event_type=' . $incoming_event . ' — requesting PayPal retry.');
                return false;
            }
            return true;
        }
        if (isset($posted['event_type']) && !empty($posted['event_type'])) {
            $order->add_order_note('Webhooks Update : ' . (isset($posted['summary']) ? $posted['summary'] : $posted['event_type']));
            if (isset($posted['resource']['status']) && !empty($posted['resource']['status'])) {
                $this->ppcp_log('Payment status: ' . $posted['resource']['status']);
            }
            if (isset($posted['resource']['id']) && !empty($posted['resource']['id'])) {
                // For order-level events (e.g. CHECKOUT.ORDER.APPROVED) this is the PayPal
                // order id, not a capture/transaction id — label it accordingly.
                $this->ppcp_log('PayPal Resource ID: ' . $posted['resource']['id']);
            }
            if (isset($posted['resource']['status']) && isset($posted['resource']['id'])) {
                $event_type     = isset($posted['event_type']) ? $posted['event_type'] : '';
                $resource_status = isset($posted['resource']['status']) ? $posted['resource']['status'] : '';
                $resource_id     = isset($posted['resource']['id']) ? $posted['resource']['id'] : '';
                $this->ppcp_log(
                    'Webhook event received: event_type=' . $event_type .
                    ', resource_status=' . $resource_status .
                    ', resource_id=' . $resource_id .
                    ', order_id=' . ($order ? $order->get_id() : 'unknown')
                );
                switch ($event_type) {
                    case 'CHECKOUT.ORDER.APPROVED':
                        $this->ppcp_log(
                            'CHECKOUT.ORDER.APPROVED webhook received for order #' .
                            ($order ? $order->get_id() : 'unknown') .
                            '. No order status change — awaiting capture or authorization event.'
                        );
                        break;
                    case 'PAYMENT.AUTHORIZATION.CREATED':
                        $this->ppcp_log('Processing PAYMENT.AUTHORIZATION.CREATED for order #' . ($order ? $order->get_id() : 'unknown'));
                        $this->payment_status_authorized($order);
                        break;
                    case 'PAYMENT.AUTHORIZATION.DENIED':
                        $this->ppcp_log('Processing PAYMENT.AUTHORIZATION.DENIED for order #' . ($order ? $order->get_id() : 'unknown') . '. Marking as failed.');
                        $this->payment_status_denied($order, $posted);
                        break;
                    case 'PAYMENT.AUTHORIZATION.VOIDED':
                        $this->ppcp_log('Processing PAYMENT.AUTHORIZATION.VOIDED for order #' . ($order ? $order->get_id() : 'unknown') . '. Marking as failed.');
                        $this->payment_status_voided($order, $posted);
                        break;
                    case 'PAYMENT.CAPTURE.COMPLETED':
                        $this->ppcp_log('Processing PAYMENT.CAPTURE.COMPLETED for order #' . ($order ? $order->get_id() : 'unknown'));
                        $this->payment_status_completed($order, $posted);
                        break;
                    case 'PAYMENT.CAPTURE.DECLINED':
                    case 'PAYMENT.CAPTURE.DENIED':
                        $this->ppcp_log('Processing ' . $event_type . ' for order #' . ($order ? $order->get_id() : 'unknown') . '. Marking as failed.');
                        $this->payment_status_denied($order, $posted);
                        break;
                    case 'PAYMENT.CAPTURE.PENDING':
                        $this->ppcp_log('Processing PAYMENT.CAPTURE.PENDING for order #' . ($order ? $order->get_id() : 'unknown') . '. Setting on-hold.');
                        $this->payment_status_on_hold($order, $posted);
                        break;
                    case 'PAYMENT.CAPTURE.REFUNDED':
                        $this->ppcp_log('Processing PAYMENT.CAPTURE.REFUNDED for order #' . ($order ? $order->get_id() : 'unknown'));
                        $this->payment_status_refunded($order, $posted);
                        break;
                    default:
                        $this->ppcp_log('Unhandled webhook event_type: ' . $event_type . ' for order #' . ($order ? $order->get_id() : 'unknown'));
                        break;
                }
            }
        }
        return true;
    }

    /**
     * Handle CUSTOMER.DISPUTE.* webhook events.
     *
     * Dispute events do not carry the order's custom_id, so the order is
     * resolved from the disputed transaction (capture) id instead.
     *
     * @param array $posted Decoded webhook payload.
     */
    public function ppcp_handle_dispute_event($posted) {
        try {
            $event_type = isset($posted['event_type']) ? $posted['event_type'] : '';
            $resource   = isset($posted['resource']) ? $posted['resource'] : array();

            $dispute_id = '';
            if (!empty($resource['dispute_id'])) {
                $dispute_id = $resource['dispute_id'];
            } elseif (!empty($resource['id'])) {
                $dispute_id = $resource['id'];
            }

            // A dispute can reference more than one captured transaction; handle
            // each matching order.
            $transactions = array();
            if (!empty($resource['disputed_transactions']) && is_array($resource['disputed_transactions'])) {
                $transactions = $resource['disputed_transactions'];
            }

            $matched = false;
            foreach ($transactions as $txn) {
                $capture_id = isset($txn['seller_transaction_id']) ? $txn['seller_transaction_id'] : '';
                $order = $this->ppcp_get_order_by_capture_id($capture_id);
                if (!$order) {
                    continue;
                }
                $matched = true;
                $this->ppcp_apply_dispute_to_order($order, $event_type, $dispute_id, $resource, $posted);
            }

            if (!$matched) {
                $this->ppcp_log('Dispute webhook received but no matching order found. dispute_id=' . $dispute_id);
            }
            // Only ask PayPal to retry when there were transactions to resolve
            // against but none matched yet (e.g. capture id not persisted yet);
            // an empty transaction list has nothing to gain from a retry.
            return $matched || empty($transactions);
        } catch (Exception $ex) {
            $this->ppcp_log('Dispute webhook handling error: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Apply a dispute event's effect to a single resolved order.
     *
     * @param WC_Order $order      Resolved order.
     * @param string   $event_type Webhook event type.
     * @param string   $dispute_id PayPal dispute id.
     * @param array    $resource   Webhook resource payload.
     * @param array    $posted     Full webhook payload (for hooks).
     */
    private function ppcp_apply_dispute_to_order($order, $event_type, $dispute_id, $resource, $posted) {
        try {
            $status  = isset($resource['status']) ? $resource['status'] : '';
            $reason  = isset($resource['reason']) ? $resource['reason'] : '';
            $stage   = isset($resource['dispute_life_cycle_stage']) ? $resource['dispute_life_cycle_stage'] : '';
            $amount  = '';
            if (!empty($resource['dispute_amount']['value'])) {
                $amount = $resource['dispute_amount']['value'] . ' ' . (isset($resource['dispute_amount']['currency_code']) ? $resource['dispute_amount']['currency_code'] : '');
            }

            $this->ppcp_log('Processing ' . $event_type . ' for order #' . $order->get_id() . '. dispute_id=' . $dispute_id . ', status=' . $status);

            $order->update_meta_data('_wpg_paypal_dispute_id', $dispute_id);
            $order->update_meta_data('_wpg_paypal_dispute_status', $status);

            switch ($event_type) {
                case 'CUSTOMER.DISPUTE.CREATED':
                    $note = sprintf(
                        /* translators: 1: dispute id, 2: reason, 3: amount, 4: stage */
                        __('PayPal dispute opened (ID: %1$s). Reason: %2$s. Amount: %3$s. Stage: %4$s. Please respond in the PayPal Resolution Center.', 'woo-paypal-gateway'),
                        $dispute_id,
                        $reason ? $reason : 'N/A',
                        $amount ? $amount : 'N/A',
                        $stage ? $stage : 'N/A'
                    );
                    $order->add_order_note($note);
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    if (apply_filters('wpg_ppcp_dispute_set_on_hold', true, $order, $posted) && !$order->has_status(array('cancelled', 'refunded', 'on-hold'))) {
                        // Remember the current status so it can be restored on resolution.
                        $order->update_meta_data('_wpg_ppcp_dispute_prev_status', $order->get_status());
                        $order->update_status('on-hold', __('Order placed on hold due to an open PayPal dispute. ', 'woo-paypal-gateway'));
                    }
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    do_action('wpg_ppcp_dispute_created', $order, $posted);
                    break;

                case 'CUSTOMER.DISPUTE.RESOLVED':
                    $outcome = '';
                    if (!empty($resource['dispute_outcome']['outcome_code'])) {
                        $outcome = $resource['dispute_outcome']['outcome_code'];
                    }
                    $note = sprintf(
                        /* translators: 1: dispute id, 2: outcome */
                        __('PayPal dispute resolved (ID: %1$s). Outcome: %2$s.', 'woo-paypal-gateway'),
                        $dispute_id,
                        $outcome ? $outcome : $status
                    );
                    $order->add_order_note($note);
                    // Restore the pre-dispute status if we placed the order on hold.
                    $prev_status = $order->get_meta('_wpg_ppcp_dispute_prev_status');
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    if ($prev_status && $order->has_status('on-hold') && apply_filters('wpg_ppcp_dispute_restore_status', true, $order, $posted)) {
                        $order->update_status($prev_status, __('Restoring status after PayPal dispute resolution. ', 'woo-paypal-gateway'));
                    }
                    $order->delete_meta_data('_wpg_ppcp_dispute_prev_status');
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    do_action('wpg_ppcp_dispute_resolved', $order, $posted);
                    break;

                default: // CUSTOMER.DISPUTE.UPDATED and any future dispute sub-event.
                    $note = sprintf(
                        /* translators: 1: dispute id, 2: status, 3: stage */
                        __('PayPal dispute updated (ID: %1$s). Status: %2$s. Stage: %3$s.', 'woo-paypal-gateway'),
                        $dispute_id,
                        $status ? $status : 'N/A',
                        $stage ? $stage : 'N/A'
                    );
                    $order->add_order_note($note);
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    do_action('wpg_ppcp_dispute_updated', $order, $posted);
                    break;
            }

            $order->save();
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            do_action('wpg_ppcp_dispute_event', $event_type, $order, $posted);
        } catch (Exception $ex) {
            $this->ppcp_log('Dispute webhook handling error: ' . $ex->getMessage());
        }
    }

    /**
     * Resolve a WooCommerce order from a PayPal capture (transaction) id.
     *
     * @param string $capture_id PayPal capture id.
     * @return WC_Order|false
     */
    public function ppcp_get_order_by_capture_id($capture_id) {
        if (empty($capture_id)) {
            return false;
        }
        $orders = wc_get_orders(array(
            'transaction_id' => $capture_id,
            'limit'          => 1,
            'return'         => 'objects',
        ));
        if (!empty($orders) && is_array($orders)) {
            return $orders[0];
        }
        // Fallback: authorization id stored separately.
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Order must be located by the PayPal capture id stored in order meta; there is no alternative lookup key.
        $orders = wc_get_orders(array(
            'limit'      => 1,
            'return'     => 'objects',
            'meta_key'   => '_auth_transaction_id',
            'meta_value' => $capture_id,
        ));
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        if (!empty($orders) && is_array($orders)) {
            return $orders[0];
        }
        return false;
    }

    /**
     * Acquire a short-lived, atomic per-order completion lock.
     *
     * Uses the same INSERT IGNORE mechanism as the renewal lock: exactly one concurrent
     * caller inserts the transient rows and wins. The lock is stored as a transient so a
     * short TTL auto-expires it as a backstop even if a holder dies before releasing.
     *
     * @param int $order_id
     * @param int $ttl Seconds before the lock self-expires.
     * @return bool True if this caller acquired the lock.
     */
    private function ppcp_acquire_completion_lock($order_id, $ttl = 45) {
        global $wpdb;
        $lock_key = 'wpg_ppcp_complete_lock_' . absint($order_id);
        if (get_transient($lock_key) !== false) {
            return false;
        }
        $option_name     = '_transient_' . $lock_key;
        $expiration_name = '_transient_timeout_' . $lock_key;
        $now    = time();
        $expire = $now + max(5, (int) $ttl);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no'), (%s, %s, 'no')",
            $expiration_name, $expire, $option_name, $now
        ));
        return $result > 0;
    }

    private function ppcp_release_completion_lock($order_id) {
        delete_transient('wpg_ppcp_complete_lock_' . absint($order_id));
    }

    public function payment_status_completed($order, $posted) {
        if ($order->has_status(wc_get_is_paid_statuses())) {
            $this->ppcp_log('Aborting, Order #' . $order->get_id() . ' is already complete.');
            exit;
        }
        $order_id = $order->get_id();
        // Serialize completion: two concurrent CAPTURE.COMPLETED deliveries (or a
        // redelivery racing the first) could both pass the has_status check above before
        // either writes the status. If the lock is held, another process is completing
        // this order, so skip — the lock auto-expires as a backstop.
        if (!$this->ppcp_acquire_completion_lock($order_id)) {
            $this->ppcp_log('Webhook completion for order #' . $order_id . ' skipped: completion lock held by another process.');
            return;
        }
        try {
            // Re-check under the lock in case the other holder just completed the order.
            $fresh = wc_get_order($order_id);
            if ($fresh instanceof WC_Order && $fresh->has_status(wc_get_is_paid_statuses())) {
                $this->ppcp_log('Order #' . $order_id . ' already completed under lock; skipping duplicate.');
                return;
            }
            $resource_status = isset($posted['resource']['status']) ? strtoupper($posted['resource']['status']) : '';
            $this->ppcp_log('Webhook payment completion check for order #' . $order->get_id() . ': event=' . (isset($posted['event_type']) ? $posted['event_type'] : 'N/A') . ', resource_status=' . $resource_status);
            if ('COMPLETED' === $resource_status) {
                $this->payment_complete($order);
            } else {
                if ('PENDING' === $resource_status) {
                    if (!empty($posted['resource']['status_details']['reason'])) {
                        // translators: %1$s: pending reason from PayPal.
                        $this->payment_on_hold($order, sprintf(__('Payment pending (%1$s).', 'woo-paypal-gateway'), $posted['resource']['status_details']['reason']));
                    } else {
                        $this->payment_on_hold($order, __('Payment is pending at PayPal.', 'woo-paypal-gateway'));
                    }
                } elseif ('AUTHORIZED' === $resource_status || 'CREATED' === $resource_status) {
                    $this->payment_on_hold($order, $this->get_payment_authorized_note());
                } else {
                    $this->ppcp_log('Webhook payment status is not successful for order #' . $order->get_id() . '. Marking order as failed. Status: ' . $resource_status);
                    $this->payment_status_failed($order);
                }
            }
        } finally {
            $this->ppcp_release_completion_lock($order_id);
        }
    }

    private function get_payment_authorized_note() {
        $capture_statuses = $this->get_capture_order_statuses();

        if (empty($capture_statuses)) {
            return __('Payment authorized. Capture statuses are not configured.', 'woo-paypal-gateway');
        }

        $status_labels = array();
        $order_statuses = wc_get_order_statuses();

        foreach ($capture_statuses as $status) {
            $status_key = 'wc-' . $status;
            if (isset($order_statuses[$status_key])) {
                $status_labels[] = $order_statuses[$status_key];
            } else {
                $status_labels[] = ucfirst(str_replace('-', ' ', $status));
            }
        }

        // translators: %s is a comma-separated list of WooCommerce order status labels.
        return sprintf(__('Payment authorized. Change the order to one of your configured capture statuses (%s) to capture funds.', 'woo-paypal-gateway'), implode(', ', $status_labels));
    }

    private function get_capture_order_statuses() {
        $capture_statuses = $this->get_option('capture_order_statuses', array('processing', 'completed'));

        if (!is_array($capture_statuses)) {
            $capture_statuses = array('processing', 'completed');
        }

        $capture_statuses = array_values(array_filter(array_map('sanitize_key', $capture_statuses)));

        return empty($capture_statuses) ? array('processing', 'completed') : $capture_statuses;
    }

    public function payment_complete($order, $txn_id = '', $note = '') {
        if (!$order->has_status(array('processing', 'completed'))) {
            $order->add_order_note($note);
            $order->payment_complete($txn_id);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            apply_filters('woocommerce_payment_successful_result', array('result' => 'success'), $order);
            woo_paypal_gateway_clear_ppcp_session_and_cart();
        }
    }

    public function payment_on_hold($order, $reason = '') {
        if (!$order->has_status(array('processing', 'completed', 'refunded'))) {
            $order->update_status('on-hold', $reason);
        }
    }

    public function payment_status_pending($order, $posted) {
        if (!$order->has_status(array('processing', 'completed', 'refunded'))) {
            $this->payment_status_completed($order, $posted);
        }
    }

    public function payment_status_failed($order) {
        if (!$order->has_status(array('failed'))) {
            $order->update_status('failed');
        }
    }

    public function payment_status_denied($order) {
        $this->payment_status_failed($order);
    }

    public function payment_status_expired($order) {
        $this->payment_status_failed($order);
    }

    public function payment_status_voided($order) {
        $this->payment_status_failed($order);
    }

    public function payment_status_refunded($order, $posted) {
        if (!$order instanceof WC_Order || !isset($posted['resource']['seller_payable_breakdown']['total_refunded_amount']['value'])) {
            return;
        }
        $resource = $posted['resource'];
        $refunded_amount = floatval($resource['seller_payable_breakdown']['total_refunded_amount']['value']);
        $refunded_currency = $resource['seller_payable_breakdown']['total_refunded_amount']['currency_code'] ?? '';
        $order_total = floatval($order->get_total());
        $order_currency = $order->get_currency();
        if ($refunded_currency !== '' && strtoupper($refunded_currency) !== strtoupper($order_currency)) {
            $order->add_order_note("PayPal refund currency mismatch: $refunded_currency vs $order_currency");
            return;
        }

        // Record any refund that happened outside WooCommerce (e.g. in the PayPal
        // dashboard, including partial refunds) as a real WC_Order_Refund so the order
        // reflects it. Dedup is by the refunded-total GAP, not by refund id: PayPal
        // reports its cumulative total_refunded_amount, so we only create a WC refund
        // for the amount PayPal has refunded beyond what WooCommerce already records.
        //   - Refund initiated from WC admin: WC already recorded it, so the gap is 0 and
        //     nothing is duplicated.
        //   - Refund initiated in the PayPal dashboard: the gap is the new amount, which
        //     is recorded here.
        //   - Webhook redelivery: the gap is already closed, so it is a no-op.
        // wc_create_refund() only writes the local record; it does NOT call the gateway
        // API, so this can never trigger a second refund at PayPal.
        $decimals    = wc_get_price_decimals();
        $wc_refunded = floatval($order->get_total_refunded());
        $gap         = round($refunded_amount - $wc_refunded, $decimals);
        $remaining   = round($order_total - $wc_refunded, $decimals);
        $amount_to_record = min($gap, max(0.0, $remaining));
        if ($amount_to_record > 0) {
            try {
                $wc_refund = wc_create_refund(array(
                    'amount'        => wc_format_decimal($amount_to_record, $decimals),
                    'reason'        => __('Refunded via PayPal.', 'woo-paypal-gateway'),
                    'order_id'      => $order->get_id(),
                    'restock_items' => false,
                ));
                if (is_wp_error($wc_refund)) {
                    $order->add_order_note('Could not record PayPal refund in WooCommerce: ' . $wc_refund->get_error_message());
                } else {
                    $paypal_refund_id = isset($resource['id']) ? (string) $resource['id'] : '';
                    if ($paypal_refund_id !== '' && is_object($wc_refund)) {
                        $wc_refund->update_meta_data('_ppcp_paypal_refund_id', $paypal_refund_id);
                        $wc_refund->save_meta_data();
                    }
                    $order->add_order_note(
                        sprintf(
                            /* translators: 1: amount, 2: currency, 3: PayPal refund id */
                            __('Recorded a PayPal refund of %1$s %2$s (PayPal refund ID: %3$s).', 'woo-paypal-gateway'),
                            wc_format_decimal($amount_to_record, $decimals),
                            $order_currency,
                            $paypal_refund_id !== '' ? $paypal_refund_id : 'n/a'
                        )
                    );
                }
            } catch (\Throwable $ex) {
                $this->ppcp_log('Failed to record PayPal refund for order #' . $order->get_id() . ': ' . $ex->getMessage());
            }
        }

        // Preserve the existing full-refund status transition. wc_create_refund above may
        // already move a fully-refunded order to the "refunded" status; this is a safety
        // net for the status only.
        $formatted_refund = wc_format_decimal($refunded_amount, $decimals);
        $formatted_total  = wc_format_decimal($order_total, $decimals);
        if ($order_total > 0 && $formatted_refund >= $formatted_total && !$order->has_status(['refunded'])) {
            $order->update_status(
                'refunded',
                sprintf(
                    /* translators: 1: refunded amount, 2: refunded currency */
                    __('Marked as refunded via PayPal. Total refunded: %1$s %2$s.', 'woo-paypal-gateway'),
                    $formatted_refund,
                    $order_currency
                )
            );
        }
    }

    public function payment_status_authorized($order) {
        if ($order->has_status(array('pending'))) {
            $order->update_status($this->authorized_order_status, $this->get_payment_authorized_note());
        }
    }

    public function payment_status_on_hold($order) {
        if ($order->has_status(array('pending'))) {
            $order->update_status('on-hold');
        }
    }

    public function ppcp_get_order_by_paypal_order_id($paypal_order_id) {
        if (empty($paypal_order_id)) {
            return false;
        }
        $orders = wc_get_orders(array(
            'limit' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Order must be located by the PayPal order id stored in order meta; there is no alternative lookup key.
            'meta_query' => array(
                array(
                    'key' => '_wpg_paypal_order_id',
                    'value' => $paypal_order_id,
                ),
            ),
        ));
        if (empty($orders)) {
            return false;
        }
        $order = reset($orders);
        // Some order data stores ignore unsupported query vars and would return an
        // unrelated order — trust the match only after re-checking the meta value.
        if ($order instanceof WC_Order && $order->get_meta('_wpg_paypal_order_id') === $paypal_order_id) {
            $this->ppcp_log('Order matched locally via _wpg_paypal_order_id: ' . $order->get_id());
            return $order;
        }
        return false;
    }

    public function ppcp_get_paypal_order($raw_custom) {
        $custom = json_decode($raw_custom);
        if ($custom && is_object($custom)) {
            $order_id = $custom->order_id;
            $order_key = $custom->order_key;
        } else {
            $this->ppcp_log('Order ID and key were not found in "custom_id".');
            return false;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            $order_id = wc_get_order_id_by_order_key($order_key);
            $order = wc_get_order($order_id);
        }
        if (!$order || !hash_equals($order->get_order_key(), $order_key)) {
            $this->ppcp_log('Order lookup via custom_id failed (order key mismatch or placeholder custom_id) — a fallback lookup will run where available.');
            return false;
        }
        $this->ppcp_log('Order  match : ' . $order_id);

        return $order;
    }

    public function generate_request_id( $context = '' ) {
        $base = site_url() . '-' . ( $this->is_sandbox ? 'sandbox' : 'live' );

        if ( ! empty( $context ) ) {
            // A logical context was supplied (capture / auth / refund / token-capture).
            // Return a DETERMINISTIC id so the same logical operation always produces the
            // same PayPal-Request-Id. PayPal then de-duplicates a retried request instead
            // of creating a second charge or refund. Distinct operations use distinct
            // contexts, so they never collide.
            return 'wpg-' . md5( $base . '-' . $context );
        }

        // No logical context (non money-moving calls, e.g. webhook verification, order
        // creation, token listing): fall back to a unique, non-idempotent id.
        static $counter = 0;
        $counter++;

        return substr( md5( $base ), 0, 12 ) . '-' . time() . '-' . getmypid() . '-' . $counter;
    }

    public function ppcp_get_phone_national_number($billing_phone, $billing_country = '') {
        $digits = preg_replace('/[^0-9]/', '', (string) $billing_phone);
        if ($digits === '') {
            return '';
        }
        // Strip a leading international dialing prefix.
        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }
        // PayPal expects national_number WITHOUT the country calling code, but shoppers
        // routinely enter numbers like "+1 650 555 5555". Strip the calling code for the
        // billing country when the remainder is still long enough to be a national number.
        if (!empty($billing_country) && function_exists('WC') && isset(WC()->countries) && method_exists(WC()->countries, 'get_country_calling_code')) {
            $calling_code = WC()->countries->get_country_calling_code($billing_country);
            $calling_code = is_array($calling_code) ? reset($calling_code) : $calling_code;
            $calling_code = preg_replace('/[^0-9]/', '', (string) $calling_code);
            if ($calling_code !== '' && strpos($digits, $calling_code) === 0 && strlen($digits) - strlen($calling_code) >= 7) {
                $digits = substr($digits, strlen($calling_code));
            }
        }
        return $digits;
    }

    public function ppcp_set_payer_details($woo_order_id, $body_request) {
        if ($woo_order_id != null) {
            $order = wc_get_order($woo_order_id);
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            $billing_email = $order->get_billing_email();
            $billing_phone = $order->get_billing_phone();
            if (!empty($billing_email)) {
                $body_request['payer']['email_address'] = $billing_email;
            }
            if (!empty($billing_phone)) {
                $phone_national_number = $this->ppcp_get_phone_national_number($billing_phone, $order->get_billing_country());
                if ($phone_national_number !== '') {
                    $body_request['payer']['phone']['phone_number']['national_number'] = $phone_national_number;
                }
            }
            if (!empty($first_name)) {
                $body_request['payer']['name']['given_name'] = $first_name;
            }
            if (!empty($last_name)) {
                $body_request['payer']['name']['surname'] = $last_name;
            }
            $address_1 = $order->get_billing_address_1();
            $address_2 = $order->get_billing_address_2();
            $city = $order->get_billing_city();
            $state = $order->get_billing_state();
            $postcode = $order->get_billing_postcode();
            $country = $order->get_billing_country();
            if (!empty($address_1) && !empty($city) && !empty($state) && !empty($postcode) && !empty($country)) {
                $body_request['payer']['address'] = array(
                    'address_line_1' => $address_1,
                    'address_line_2' => $address_2,
                    'admin_area_2' => $city,
                    'admin_area_1' => $state,
                    'postal_code' => $postcode,
                    'country_code' => $country,
                );
            }
        } else {
            if (is_user_logged_in()) {
                $customer = WC()->customer;
                $first_name = $customer->get_billing_first_name();
                $last_name = $customer->get_billing_last_name();
                $address_1 = $customer->get_billing_address_1();
                $address_2 = $customer->get_billing_address_2();
                $city = $customer->get_billing_city();
                $state = $customer->get_billing_state();
                $postcode = $customer->get_billing_postcode();
                $country = $customer->get_billing_country();
                $email_address = WC()->customer->get_billing_email();
                $billing_phone = $customer->get_billing_phone();
                if (!empty($first_name)) {
                    $body_request['payer']['name']['given_name'] = $first_name;
                }
                if (!empty($last_name)) {
                    $body_request['payer']['name']['surname'] = $last_name;
                }
                if (!empty($email_address)) {
                    $body_request['payer']['email_address'] = $email_address;
                }
                if (!empty($billing_phone)) {
                    $phone_national_number = $this->ppcp_get_phone_national_number($billing_phone, $country);
                    if ($phone_national_number !== '') {
                        $body_request['payer']['phone']['phone_number']['national_number'] = $phone_national_number;
                    }
                }
                if (!empty($address_1) && !empty($city) && !empty($state) && !empty($postcode) && !empty($country)) {
                    $body_request['payer']['address'] = array(
                        'address_line_1' => $address_1,
                        'address_line_2' => $address_2,
                        'admin_area_2' => $city,
                        'admin_area_1' => $state,
                        'postal_code' => $postcode,
                        'country_code' => $country,
                    );
                }
            }
        }
        return $body_request;
    }

    public function ppcp_regular_create_order_request($woo_order_id = null, $return_url = true) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $return_response = [];
            if ($this->ppcp_get_order_total($woo_order_id) === 0) {
                $wc_notice = __('Sorry, your session has expired.', 'woo-paypal-gateway');
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($wc_notice);
                }
                if (wp_doing_cron() || (defined('WP_CLI') && WP_CLI) || !wp_doing_ajax()) {
                    $this->api_log->log('Zero-total order skipped for token capture: ' . $woo_order_id, 'error');
                    return false;
                }
                wp_send_json_error($wc_notice);
                exit();
            }
            // Match decimal precision to the currency actually sent (order/active), not
            // the store base, before building the cart (which rounds internally).
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($this->ppcp_get_currency($woo_order_id));
            if ($woo_order_id == null) {
                $cart = $this->ppcp_get_details_from_cart();
            } else {
                $cart = $this->ppcp_get_details_from_order($woo_order_id);
            }
            $decimals = $this->decimals;
            $reference_id = wc_generate_order_key();
            // Canonical session key. Readers (ppcp_update_order, the invoice-id patch,
            // the express shipping patch) all read 'ppcp_reference_id'; writing the
            // unprefixed key here left the redirect-flow amount PATCH unable to match the
            // purchase unit (path @reference_id==''), so post-approval amount/address
            // re-syncs silently no-op'd. Write the key the readers actually read.
            woo_paypal_gateway_ppcp_set_session('ppcp_reference_id', $reference_id);
            $intent = ($this->paymentaction === 'capture') ? 'CAPTURE' : 'AUTHORIZE';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            $intent = apply_filters('wpg_ppcp_payment_intent', $intent, $woo_order_id ? wc_get_order($woo_order_id) : null);
            $body_request = array(
                'intent' => $intent,
                'application_context' => $this->ppcp_application_context($woo_order_id, $return_url = true),
                'payment_method' => array('payee_preferred' => ($this->payee_preferred) ? 'IMMEDIATE_PAYMENT_REQUIRED' : 'UNRESTRICTED'),
                'purchase_units' =>
                array(
                    0 =>
                    array(
                        'reference_id' => $reference_id,
                        'amount' =>
                        array(
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                            'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['order_total']),
                            'value' => $cart['order_total'],
                            'breakdown' => array()
                        )
                    ),
                ),
            );
            if ($woo_order_id != null) {
                $order = wc_get_order($woo_order_id);
                $body_request['purchase_units'][0]['invoice_id'] = $this->invoice_id_prefix . str_replace("#", "", $order->get_order_number());
                $body_request['purchase_units'][0]['custom_id'] = wp_json_encode(
                    array(
                        'order_id' => $order->get_id(),
                        'order_key' => $order->get_order_key(),
                    )
                );
            } else {
                $body_request['purchase_units'][0]['invoice_id'] = $reference_id;
                $body_request['purchase_units'][0]['custom_id'] = wp_json_encode(
                    array(
                        'order_id' => $reference_id,
                        'order_key' => $reference_id,
                    )
                );
            }
            $body_request['purchase_units'][0]['payee']['merchant_id'] = $this->merchant_id;
            if ($this->send_items === true) {
                if (isset($cart['total_item_amount']) && $cart['total_item_amount'] > 0) {
                    $body_request['purchase_units'][0]['amount']['breakdown']['item_total'] = array(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['total_item_amount']),
                        'value' => $cart['total_item_amount'],
                    );
                }
                if (isset($cart['shipping']) && $cart['shipping'] > 0) {
                    $body_request['purchase_units'][0]['amount']['breakdown']['shipping'] = array(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['shipping']),
                        'value' => $cart['shipping'],
                    );
                }
                if (isset($cart['ship_discount_amount']) && $cart['ship_discount_amount'] > 0) {
                    $body_request['purchase_units'][0]['amount']['breakdown']['shipping_discount'] = array(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $decimals)),
                        'value' => woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $decimals),
                    );
                }
                if (isset($cart['order_tax']) && $cart['order_tax'] > 0) {
                    $body_request['purchase_units'][0]['amount']['breakdown']['tax_total'] = array(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['order_tax']),
                        'value' => $cart['order_tax'],
                    );
                }
                if (isset($cart['discount']) && $cart['discount'] > 0) {
                    $body_request['purchase_units'][0]['amount']['breakdown']['discount'] = array(
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['discount']),
                        'value' => $cart['discount'],
                    );
                }
                if (isset($cart['items']) && !empty($cart['items'])) {
                    foreach ($cart['items'] as $key => $order_items) {
                        $description = !empty($order_items['description']) ? strip_shortcodes($order_items['description']) : '';
                        $product_name = !empty($order_items['name']) ? wp_strip_all_tags($order_items['name']) : '';
                        if (strlen($description) > 127) {
                            $description = substr($description, 0, 124) . '...';
                        }
                        if (strlen($product_name) > 127) {
                            $product_name = substr($product_name, 0, 124) . '...';
                        }
                        $body_request['purchase_units'][0]['items'][$key] = array(
                            'name' => $product_name,
                            'description' => html_entity_decode($description, ENT_NOQUOTES, 'UTF-8'),
                            'sku' => !empty($order_items['sku']) ? $order_items['sku'] : '',
                            'category' => !empty($order_items['category']) ? $order_items['category'] : '',
                            'quantity' => $order_items['quantity'],
                            'unit_amount' => array(
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                                'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $order_items['amount']),
                                'value' => woo_paypal_gateway_ppcp_round($order_items['amount'], $decimals)
                            ),
                        );
                    }
                }
            }
            if ($woo_order_id != null) {
                $order = wc_get_order($woo_order_id);
                if ($order->has_shipping_address()) {
                    $shipping_first_name = $order->get_shipping_first_name();
                    $shipping_last_name = $order->get_shipping_last_name();
                    $shipping_address_1 = $order->get_shipping_address_1();
                    $shipping_address_2 = $order->get_shipping_address_2();
                    $shipping_city = $order->get_shipping_city();
                    $shipping_state = $order->get_shipping_state();
                    $shipping_postcode = $order->get_shipping_postcode();
                    $shipping_country = $order->get_shipping_country();
                } else {
                    $shipping_first_name = $order->get_billing_first_name();
                    $shipping_last_name = $order->get_billing_last_name();
                    $shipping_address_1 = $order->get_billing_address_1();
                    $shipping_address_2 = $order->get_billing_address_2();
                    $shipping_city = $order->get_billing_city();
                    $shipping_state = $order->get_billing_state();
                    $shipping_postcode = $order->get_billing_postcode();
                    $shipping_country = $order->get_billing_country();
                }
                $shipping_country = strtoupper($shipping_country);
                if ($order->needs_shipping_address() || WC()->cart->needs_shipping()) {
                    if (!empty($shipping_first_name) && !empty($shipping_last_name)) {
                        $body_request['purchase_units'][0]['shipping']['name']['full_name'] = $shipping_first_name . ' ' . $shipping_last_name;
                    }
                    woo_paypal_gateway_ppcp_set_session('is_shipping_added', 'yes');
                    $body_request['purchase_units'][0]['shipping']['address'] = array(
                        'address_line_1' => $shipping_address_1,
                        'address_line_2' => $shipping_address_2,
                        'admin_area_2' => $shipping_city,
                        'admin_area_1' => $shipping_state,
                        'postal_code' => $shipping_postcode,
                        'country_code' => $shipping_country,
                    );
                }
            } else {
                if (true === WC()->cart->needs_shipping()) {
                    if (is_user_logged_in()) {
                        if (!empty($cart['shipping_address']['first_name']) && !empty($cart['shipping_address']['last_name'])) {
                            $body_request['purchase_units'][0]['shipping']['name']['full_name'] = $cart['shipping_address']['first_name'] . ' ' . $cart['shipping_address']['last_name'];
                        }
                        if (!empty($cart['shipping_address']['address_1']) && !empty($cart['shipping_address']['city']) && !empty($cart['shipping_address']['country'])) {
                            $body_request['purchase_units'][0]['shipping']['address'] = array(
                                'address_line_1' => $cart['shipping_address']['address_1'],
                                'address_line_2' => $cart['shipping_address']['address_2'],
                                'admin_area_2' => $cart['shipping_address']['city'],
                                'admin_area_1' => $cart['shipping_address']['state'],
                                'postal_code' => $cart['shipping_address']['postcode'],
                                'country_code' => strtoupper($cart['shipping_address']['country']),
                            );
                            woo_paypal_gateway_ppcp_set_session('is_shipping_added', 'yes');
                        }
                    }
                }
            }
            $body_request = $this->ppcp_set_payer_details($woo_order_id, $body_request);
            if (woo_paypal_gateway_ppcp_is_paypal_vault_required()) {
                $body_request = $this->ppcp_add_payment_source_parameter($body_request);
            }
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            if (isset($body_request['purchase_units'][0])) {
                $body_request['purchase_units'][0] = $this->ppcp_enforce_breakdown_invariant($body_request['purchase_units'][0], $this->decimals);
            }
            $body_request = json_encode($body_request);
            $this->api_response = wp_remote_post($this->paypal_order_api, array(
                'method' => 'POST',
                'timeout' => $this->ppcp_interactive_timeout(),
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                'body' => $body_request,
                'cookies' => array()
                    )
            );
            if (is_wp_error($this->api_response)) {
                $error_message = $this->api_response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('This payment was unable to be processed successfully. Please try again with another payment method.', 'woo-paypal-gateway'), 'error');
                }
                return array(
                    'result' => 'fail',
                    'redirect' => ''
                );
            } else {
                if (ob_get_length()) {
                    ob_end_clean();
                }
                $this->api_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                if (!empty($this->api_response['status'])) {
                    $this->ppcp_set_order_session_data($this->api_response['id'], 'created', $woo_order_id);
                    if (!empty($this->api_response['links'])) {
                        foreach ($this->api_response['links'] as $key => $link_result) {
                            if ('approve' === $link_result['rel'] || 'payer-action' === $link_result['rel']) {
                                return array(
                                    'result' => 'success',
                                    'redirect' => $link_result['href']
                                );
                            }
                        }
                    }
                    return array(
                        'result' => 'fail',
                        'redirect' => ''
                    );
                } else {
                    $error_email_notification_param = array(
                        'request' => 'create_order',
                        'order_id' => $woo_order_id
                    );
                    $error_message = $this->ppcp_get_readable_message($this->api_response, $error_email_notification_param);
                    if ($order instanceof WC_Order) {
                        $order->add_order_note($error_message);
                    }
                    if (function_exists('wc_add_notice')) {
                        wc_add_notice(__('This payment was unable to be processed successfully. Please try again with another payment method.', 'woo-paypal-gateway'), 'error');
                    }
                    return array(
                        'result' => 'fail',
                        'redirect' => ''
                    );
                }
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function ppcp_get_order_total($order_id = null) {
        global $product;
        $total = 0;
        if ($order_id !== null) {
            $order = wc_get_order($order_id);
        }
        $order_pay_order_id = absint(get_query_var('order-pay'));
        if (is_product()) {
            $total = $product->get_price();
        } elseif (0 < $order_pay_order_id) {
            $order = wc_get_order($order_pay_order_id);
            $total = (float) $order->get_total();
        } elseif (isset(WC()->cart) && 0 < WC()->cart->total) {
            $total = (float) WC()->cart->total;
        } elseif (0 < $order_id) {
            $order = wc_get_order($order_id);
            $total = (float) $order->get_total();
        }
        return $total;
    }

    public function ppcp_get_currency($woo_order_id = null) {
        $currency_code = '';

        if ($woo_order_id != null) {
            $order = wc_get_order($woo_order_id);
            // Guard against a stale/invalid order id so we don't fatal on false->get_currency().
            $currency_code = $order instanceof WC_Order ? $order->get_currency() : get_woocommerce_currency();
        } elseif (class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Currency')) {
            // No order context (cart / express checkout): honour the shopper's
            // active currency so multi-currency plugins are respected.
            $currency_code = PPCP_Paypal_Checkout_For_Woocommerce_Currency::instance()->get_active_currency();
        } else {
            $currency_code = get_woocommerce_currency();
        }

        return $currency_code;
    }

    public function ppcp_regular_capture() {
        if (isset($_GET['token']) && !empty($_GET['token'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
            $paypal_order_id = wc_clean(wp_unslash($_GET['token']));
            $this->ppcp_set_order_session_data($paypal_order_id, 'approved');
        } else {
            wp_safe_redirect(wc_get_checkout_url());
            exit();
        }
        $order_id = woo_paypal_gateway_ppcp_get_awaiting_payment_order_id();
        if (woo_paypal_gateway_ppcp_is_valid_order($order_id) === false || empty($order_id)) {
            wp_safe_redirect(wc_get_checkout_url());
            exit();
        }
        $order = wc_get_order($order_id);
        if ($this->paymentaction === 'capture') {
            $is_success = $this->ppcp_order_capture_request($order_id, $need_to_update_order = false);
        } else {
            $is_success = $this->ppcp_order_auth_request($order_id, true);
        }
        $order->update_meta_data('_paymentaction', $this->paymentaction);
        $order->update_meta_data('_enviorment', ($this->is_sandbox) ? 'sandbox' : 'live');
        $order->save_meta_data();
        if ($is_success) {
            woo_paypal_gateway_clear_ppcp_session_and_cart();
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            wp_safe_redirect(apply_filters('woocommerce_get_return_url', $order->get_checkout_order_received_url(), $order));
        } else {
            // A capture refused only because PayPal has not finished settling the
            // buyer's approval is retryable against the same PayPal order. Dropping the
            // session here forced the shopper to start a whole new payment (and left the
            // approved-but-uncaptured order behind at PayPal), so it is kept in that case.
            if (empty($this->capture_blocked_pending)) {
                unset(WC()->session->ppcp_session);
            }
            WC()->session->set('reload_checkout', null);
            wp_safe_redirect(woo_paypal_gateway_get_checkout_url());
        }
        exit();
    }

    public function ppcp_add_payment_source_parameter($request) {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $wpg_payment_method = woo_paypal_gateway_ppcp_get_session('wpg_payment_method');
            if (empty($wpg_payment_method)) {
                return $request;
            }
            $attributes = [];
            $billing_address = [];
            $billing_full_name = '';
            switch ($wpg_payment_method) {
                case 'card':
                    $this->handle_card_payment($request, $attributes, $billing_address, $billing_full_name);
                    break;
                case 'credit':
                case 'paypal':
                    $this->handle_paypal_payment($request, $attributes);
                    break;
                case 'alternative_pay':
                    $this->handle_google_pay_payment($request, $attributes);
                    break;
                default:
                    break;
            }
            return $request;
        } catch (Exception $ex) {
            return $request;
        }
    }

    private function handle_card_payment(&$request, &$attributes, &$billing_address, &$billing_full_name) {
        $attributes = [
            'vault' => [
                'store_in_vault' => 'ON_SUCCESS',
                'usage_type' => 'MERCHANT'
            ]
        ];
        if (!empty($request['payer']['address'])) {
            $billing_address = [
                'address_line_1' => $request['payer']['address']['address_line_1'] ?? '',
                'address_line_2' => $request['payer']['address']['address_line_2'] ?? '',
                'admin_area_2' => $request['payer']['address']['admin_area_2'] ?? '',
                'admin_area_1' => $request['payer']['address']['admin_area_1'] ?? '',
                'postal_code' => $request['payer']['address']['postal_code'] ?? '',
                'country_code' => strtoupper($request['payer']['address']['country_code'] ?? '')
            ];
        }
        $first_name = $request['payer']['name']['given_name'] ?? '';
        $last_name = $request['payer']['name']['surname'] ?? '';
        $billing_full_name = trim("$first_name $last_name");
        $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id($this->is_sandbox);
        if (!empty($paypal_generated_customer_id)) {
            $attributes['customer'] = ['id' => $paypal_generated_customer_id];
        }
        // First time this card is stored on file. Current behaviour uses usage
        // SUBSEQUENT; the network-accurate value for the initial store is FIRST, applied
        // only when the recommended-classification filter is enabled (default off).
        $card_stored_credential = [
            'payment_initiator' => 'CUSTOMER',
            'payment_type' => 'UNSCHEDULED',
            'usage' => 'SUBSEQUENT'
        ];
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
        if (apply_filters('wpg_ppcp_use_recommended_stored_credentials', false, 'first', 'card')) {
            $card_stored_credential['usage'] = 'FIRST';
        }
        $request['payment_source']['card'] = [
            'name' => $billing_full_name,
            'billing_address' => $billing_address,
            'attributes' => $attributes,
            'stored_credential' => $card_stored_credential
        ];
    }

    private function handle_paypal_payment(&$request, &$attributes) {
        $attributes = [
            'vault' => [
                'store_in_vault' => 'ON_SUCCESS',
                'usage_type' => 'MERCHANT',
                'permit_multiple_payment_tokens' => true
            ]
        ];
        $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id($this->is_sandbox);
        if (!empty($paypal_generated_customer_id)) {
            $attributes['customer'] = ['id' => $paypal_generated_customer_id];
        }
        $request['payment_source']['paypal']['attributes'] = $attributes;
        if (!isset($request['application_context']['return_url'])) {
            $base_url = untrailingslashit(WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'));
            $request['payment_source']['paypal']['experience_context'] = [
                'return_url' => add_query_arg(['ppcp_action' => 'ppcp_regular_capture', 'utm_nooverride' => '1'], $base_url),
                'cancel_url' => add_query_arg(['ppcp_action' => 'cancel_order', 'utm_nooverride' => '1'], $base_url)
            ];
        }
    }

    private function handle_google_pay_payment(&$request, &$attributes) {
        $attributes = [
            'vault' => [
                'store_in_vault' => 'ON_SUCCESS',
                'usage_type' => 'MERCHANT'
            ]
        ];
        $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id($this->is_sandbox);
        if (!empty($paypal_generated_customer_id)) {
            $attributes['customer'] = ['id' => $paypal_generated_customer_id];
        }
        $request['payment_source']['google_pay'] = [
            'attributes' => $attributes
        ];
    }

    public function ppcp_get_id_token() {
        try {
            if (is_wc_endpoint_url('order-received')) {
                return;
            }
            $headers = array(
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . $this->basicAuth,
                'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB'
            );
            $body = array('grant_type' => 'client_credentials', 'response_type' => 'id_token');
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $paypal_customer_id = $this->payment_token->get_paypal_customer_id($this->is_sandbox);
            if (!empty($paypal_customer_id)) {
                $body['target_customer_id'] = $paypal_customer_id;
            }
            $response = wp_remote_post($this->id_token_url, array(
                'method' => 'POST',
                'timeout' => $this->ppcp_interactive_timeout(),
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking' => true,
                'headers' => $headers,
                'body' => $body
            ));
            $this->ppcp_log('Get ID token Request: ' . $this->id_token_url);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error Message: ' . $error_message);
                return '';
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Response Code: ' . wp_remote_retrieve_response_code($response));
            $this->ppcp_log('Response Message: ' . wp_remote_retrieve_response_message($response));
            $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            if (!empty($api_response['id_token'])) {
                return $api_response['id_token'];
            }
            return '';
        } catch (Exception $ex) {
            $this->ppcp_log('Exception caught: ' . $ex->getMessage());
            return '';
        }
    }

    /**
     * Create a standalone PayPal order for a buyer-approved offer charge
     * (FunnelKit upsell without a vault token). The buyer is redirected to the
     * approve link and returns to $return_url.
     *
     * @param WC_Order $order       Parent order (payer/billing source).
     * @param float    $total       Offer amount.
     * @param string   $description Offer description.
     * @param string   $return_url  URL PayPal redirects to after approval.
     * @param string   $cancel_url  URL PayPal redirects to on cancel.
     * @return array|false array('id' => ..., 'approve_url' => ...) or false.
     */
    public function wpg_ppcp_create_offer_redirect_order($order, $total, $description, $return_url, $cancel_url) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            if (!$order instanceof WC_Order || (float) $total <= 0) {
                return false;
            }
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($order->get_currency());
            $body_request = array(
                'intent' => ($this->paymentaction === 'capture') ? 'CAPTURE' : 'AUTHORIZE',
                'application_context' => array(
                    'brand_name' => $this->brand_name,
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => $return_url,
                    'cancel_url' => $cancel_url,
                ),
                'purchase_units' => array(
                    array(
                        'reference_id' => wc_generate_order_key(),
                        'description' => substr(wp_strip_all_tags((string) $description), 0, 127),
                        'amount' => array(
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                            'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $order->get_currency()),
                            'value' => woo_paypal_gateway_ppcp_round((float) $total, $this->decimals),
                        ),
                    ),
                ),
            );
            $this->ppcp_add_log_details('Create offer redirect order');
            $response = wp_remote_post($this->paypal_order_api, array(
                'timeout' => $this->ppcp_interactive_timeout(),
                'httpversion' => '1.1',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('offer-redirect-' . $order->get_id())),
                'body' => json_encode($body_request),
            ));
            if (is_wp_error($response)) {
                $this->ppcp_log('Error Message : ' . wc_print_r($response->get_error_message(), true));
                return false;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            if (empty($api_response['id'])) {
                return false;
            }
            $approve_url = '';
            if (!empty($api_response['links'])) {
                foreach ($api_response['links'] as $link) {
                    if (isset($link['rel']) && 'approve' === $link['rel']) {
                        $approve_url = $link['href'];
                        break;
                    }
                }
            }
            if (empty($approve_url)) {
                return false;
            }
            return array('id' => $api_response['id'], 'approve_url' => $approve_url);
        } catch (Exception $ex) {
            $this->ppcp_log('Offer redirect order exception: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Capture (or authorize, per the configured payment action) a buyer-approved
     * standalone PayPal order created by wpg_ppcp_create_offer_redirect_order().
     *
     * @param string   $paypal_order_id PayPal order id.
     * @param WC_Order $order           Parent order, for notes/logging only.
     * @return string|false Capture/authorization id, or false.
     */
    public function wpg_ppcp_capture_offer_redirect_order($paypal_order_id, $order = null) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $details = $this->ppcp_get_checkout_details($paypal_order_id, true);
            $status = is_object($details) && !empty($details->status) ? strtoupper($details->status) : '';
            if (!in_array($status, array('APPROVED', 'COMPLETED'), true)) {
                $this->ppcp_log('Offer redirect capture skipped, order not approved. Status: ' . $status);
                return false;
            }
            $endpoint = ($this->paymentaction === 'capture') ? '/capture' : '/authorize';
            // State-changing call: drop the request-scoped snapshot of this order.
            self::ppcp_forget_order_snapshot($paypal_order_id);
            $response = wp_remote_post($this->paypal_order_api . $paypal_order_id . $endpoint, array(
                'timeout' => $this->ppcp_interactive_timeout(),
                'httpversion' => '1.1',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('offer-capture-' . $paypal_order_id)),
            ));
            if (is_wp_error($response)) {
                $this->ppcp_log('Error Message : ' . wc_print_r($response->get_error_message(), true));
                return false;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
            $txn_id = '';
            if (!empty($api_response['purchase_units'][0]['payments']['captures'][0]['id'])) {
                $capture = $api_response['purchase_units'][0]['payments']['captures'][0];
                if (!in_array($capture['status'], array('COMPLETED', 'PENDING'), true)) {
                    return false;
                }
                $txn_id = $capture['id'];
            } elseif (!empty($api_response['purchase_units'][0]['payments']['authorizations'][0]['id'])) {
                $authorization = $api_response['purchase_units'][0]['payments']['authorizations'][0];
                if (!in_array($authorization['status'], array('CREATED', 'PENDING'), true)) {
                    return false;
                }
                $txn_id = $authorization['id'];
            }
            if (empty($txn_id)) {
                return false;
            }
            if ($order instanceof WC_Order) {
                // translators: %s: transaction ID.
                $order->add_order_note(sprintf(__('Offer charge approved by buyer at PayPal. Transaction ID: %s', 'woo-paypal-gateway'), $txn_id));
            }
            return $txn_id;
        } catch (Exception $ex) {
            $this->ppcp_log('Offer redirect capture exception: ' . $ex->getMessage());
            return false;
        }
    }

    public function wpg_ppcp_capture_order_using_payment_method_token($woo_order_id = null, $invoice_id = '', $charge_override = null, $payment_source_override = null) {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            // One-click offer charges (FunnelKit upsells, CheckoutWC order bumps)
            // pass the parent order for its vault token but must charge only the
            // offer amount — never the order's own total — and must not touch
            // the (already paid) order's status or transaction id.
            $is_offer_charge = is_array($charge_override) && isset($charge_override['total']) && (float) $charge_override['total'] > 0;
            $return_response = [];
            if (!$is_offer_charge && $this->ppcp_get_order_total($woo_order_id) === 0) {
                $wc_notice = __('Sorry, your session has expired.', 'woo-paypal-gateway');
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($wc_notice);
                }
                if (wp_doing_cron() || (defined('WP_CLI') && WP_CLI) || !wp_doing_ajax()) {
                    $this->api_log->log('Zero-total order skipped for token capture: ' . $woo_order_id, 'error');
                    return false;
                }
                wp_send_json_error($wc_notice);
                exit();
            }

            // Match decimal precision to the order currency actually sent to PayPal.
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($this->ppcp_get_currency($woo_order_id));
            $cart = $this->ppcp_get_details_from_order($woo_order_id);

            $decimals = $this->decimals;
            $reference_id = wc_generate_order_key();
            // Canonical session key. Readers (ppcp_update_order, the invoice-id patch,
            // the express shipping patch) all read 'ppcp_reference_id'; writing the
            // unprefixed key here left the redirect-flow amount PATCH unable to match the
            // purchase unit (path @reference_id==''), so post-approval amount/address
            // re-syncs silently no-op'd. Write the key the readers actually read.
            woo_paypal_gateway_ppcp_set_session('ppcp_reference_id', $reference_id);
            $order = wc_get_order($woo_order_id);
            $intent = ($this->paymentaction === 'capture') ? 'CAPTURE' : 'AUTHORIZE';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            $intent = apply_filters('wpg_ppcp_payment_intent', $intent, $order);
            $body_request = array(
                'intent' => $intent,
                'application_context' => $this->ppcp_application_context($woo_order_id, $return_url = true),
                'payment_method' => array('payee_preferred' => ($this->payee_preferred) ? 'IMMEDIATE_PAYMENT_REQUIRED' : 'UNRESTRICTED'),
                'purchase_units' =>
                array(
                    0 =>
                    array(
                        'reference_id' => $reference_id,
                        'amount' =>
                        array(
                            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                            'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['order_total']),
                            'value' => $cart['order_total'],
                            'breakdown' => array()
                        )
                    ),
                ),
            );
            $body_request['purchase_units'][0]['invoice_id'] = $this->invoice_id_prefix . $invoice_id . str_replace("#", "", $order->get_order_number());
            $body_request['purchase_units'][0]['custom_id'] = wp_json_encode(
                array(
                    'order_id' => $order->get_id(),
                    'order_key' => $order->get_order_key(),
                )
            );
            $body_request['purchase_units'][0]['payee']['merchant_id'] = $this->merchant_id;
            if ($this->send_items === true) {
            if (isset($cart['total_item_amount']) && $cart['total_item_amount'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['item_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['total_item_amount']),
                    'value' => $cart['total_item_amount'],
                );
            }
            if (isset($cart['shipping']) && $cart['shipping'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['shipping'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['shipping']),
                    'value' => $cart['shipping'],
                );
            }
            if (isset($cart['ship_discount_amount']) && $cart['ship_discount_amount'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['shipping_discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $decimals)),
                    'value' => woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $decimals),
                );
            }
            if (isset($cart['order_tax']) && $cart['order_tax'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['tax_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['order_tax']),
                    'value' => $cart['order_tax'],
                );
            }
            if (isset($cart['discount']) && $cart['discount'] > 0) {
                $body_request['purchase_units'][0]['amount']['breakdown']['discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $cart['discount']),
                    'value' => $cart['discount'],
                );
            }
                if (isset($cart['items']) && !empty($cart['items'])) {
                    foreach ($cart['items'] as $key => $order_items) {
                        $description = !empty($order_items['description']) ? strip_shortcodes($order_items['description']) : '';
                        $product_name = !empty($order_items['name']) ? wp_strip_all_tags($order_items['name']) : '';
                        if (strlen($description) > 127) {
                            $description = substr($description, 0, 124) . '...';
                        }
                        if (strlen($product_name) > 127) {
                            $product_name = substr($product_name, 0, 124) . '...';
                        }
                        $body_request['purchase_units'][0]['items'][$key] = array(
                            'name' => $product_name,
                            'description' => html_entity_decode($description, ENT_NOQUOTES, 'UTF-8'),
                            'sku' => !empty($order_items['sku']) ? $order_items['sku'] : '',
                            'category' => !empty($order_items['category']) ? $order_items['category'] : '',
                            'quantity' => $order_items['quantity'],
                            'unit_amount' => array(
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                                'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $order_items['amount']),
                                'value' => woo_paypal_gateway_ppcp_round($order_items['amount'], $this->decimals)
                            ),
                        );
                    }
                }
            }
            $order = wc_get_order($woo_order_id);
            if ($order->has_shipping_address()) {
                $shipping_first_name = $order->get_shipping_first_name();
                $shipping_last_name = $order->get_shipping_last_name();
                $shipping_address_1 = $order->get_shipping_address_1();
                $shipping_address_2 = $order->get_shipping_address_2();
                $shipping_city = $order->get_shipping_city();
                $shipping_state = $order->get_shipping_state();
                $shipping_postcode = $order->get_shipping_postcode();
                $shipping_country = $order->get_shipping_country();
            } else {
                $shipping_first_name = $order->get_billing_first_name();
                $shipping_last_name = $order->get_billing_last_name();
                $shipping_address_1 = $order->get_billing_address_1();
                $shipping_address_2 = $order->get_billing_address_2();
                $shipping_city = $order->get_billing_city();
                $shipping_state = $order->get_billing_state();
                $shipping_postcode = $order->get_billing_postcode();
                $shipping_country = $order->get_billing_country();
            }
            $shipping_country = strtoupper($shipping_country);
            if ($order->needs_shipping_address()) {
                if (!empty($shipping_first_name) && !empty($shipping_last_name)) {
                    $body_request['purchase_units'][0]['shipping']['name']['full_name'] = $shipping_first_name . ' ' . $shipping_last_name;
                }
                woo_paypal_gateway_ppcp_set_session('is_shipping_added', 'yes');
                $body_request['purchase_units'][0]['shipping']['address'] = array(
                    'address_line_1' => $shipping_address_1,
                    'address_line_2' => $shipping_address_2,
                    'admin_area_2' => $shipping_city,
                    'admin_area_1' => $shipping_state,
                    'postal_code' => $shipping_postcode,
                    'country_code' => $shipping_country,
                );
            }
            if ($is_offer_charge) {
                // Charge exactly the offer amount. Item/breakdown detail is dropped
                // because the offer data cannot be reconciled against the parent
                // order's totals without risking a 422 mismatch.
                $override_total = woo_paypal_gateway_ppcp_round((float) $charge_override['total'], $decimals);
                $body_request['purchase_units'][0]['amount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency($woo_order_id), $override_total),
                    'value' => $override_total,
                );
                unset($body_request['purchase_units'][0]['items']);
                if (!empty($charge_override['description'])) {
                    $body_request['purchase_units'][0]['description'] = substr(wp_strip_all_tags((string) $charge_override['description']), 0, 127);
                }
            }
            $body_request = $this->ppcp_set_payer_details($woo_order_id, $body_request);
            if (!empty($payment_source_override) && is_array($payment_source_override)) {
                // Caller supplied the payment source directly (e.g. a Fastlane
                // single-use token) — skip the saved-vault-token lookup filter.
                $body_request['payment_source'] = $payment_source_override;
            } else {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                $body_request = apply_filters('wpg_ppcp_add_payment_source', $body_request, $woo_order_id);
            }
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            // Fail-safe: drop a non-reconciling breakdown so the create+capture cannot be
            // rejected by PayPal with a breakdown mismatch (same guard as the other
            // create paths). Applied once, before the retry loop: the duplicate-invoice
            // retry below only mutates invoice_id, which does not affect amounts.
            if (isset($body_request['purchase_units'][0])) {
                $body_request['purchase_units'][0] = $this->ppcp_enforce_breakdown_invariant($body_request['purchase_units'][0], $this->decimals);
            }
            // PayPal enforces unique invoice IDs per merchant account, so a shopper who
            // retries a previously-attempted order (or a store whose order numbers were
            // reset) can hit DUPLICATE_INVOICE_ID on this single create+capture call.
            // The standard capture flow already recovers from this; mirror it here by
            // retrying once with a uniquified invoice id so the payment goes through
            // instead of the checkout dead-ending.
            $token_capture_attempt = 0;
            do {
                $retry_for_duplicate_invoice = false;
                $encoded_body_request = json_encode($body_request);
                $this->ppcp_add_log_details('Order using payment token');
                $this->ppcp_log('Request : ' . wc_print_r($this->paypal_order_api, true));
                $request_id_context = 'token-capture-' . $woo_order_id . ($token_capture_attempt > 0 ? '-retry' . $token_capture_attempt : '');
                $this->api_response = wp_remote_post($this->paypal_order_api, array(
                    'method' => 'POST',
                    'timeout' => $this->ppcp_interactive_timeout(),
                    'redirection' => 5,
                    'httpversion' => '1.1',
                    'blocking' => true,
                    'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id($request_id_context)),
                    'body' => $encoded_body_request,
                    'cookies' => array()
                        )
                );
                if (is_wp_error($this->api_response)) {
                    break;
                }
                // Only the first attempt may trigger a duplicate-invoice retry. A
                // response carrying a 'status' is a completed/pending capture, not a
                // rejection, so leave it untouched.
                if ($token_capture_attempt === 0 && ($order instanceof WC_Order)) {
                    $duplicate_check_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                    if (empty($duplicate_check_response['status']) && $this->ppcp_response_has_issue($duplicate_check_response, 'DUPLICATE_INVOICE_ID')) {
                        $unique_invoice_id = $this->invoice_id_prefix . $invoice_id . str_replace('#', '', $order->get_order_number()) . '-R' . substr((string) time(), -6);
                        $this->ppcp_log('Token capture failed with DUPLICATE_INVOICE_ID for order #' . $woo_order_id . '. Retrying once with unique invoice_id: ' . $unique_invoice_id);
                        $body_request['purchase_units'][0]['invoice_id'] = $unique_invoice_id;
                        // translators: %s: the regenerated PayPal invoice ID.
                        $order->add_order_note(sprintf(__('PayPal rejected the invoice ID as a duplicate. Payment was retried with a unique invoice ID: %s', 'woo-paypal-gateway'), $unique_invoice_id));
                        $token_capture_attempt++;
                        $retry_for_duplicate_invoice = true;
                    }
                }
            } while ($retry_for_duplicate_invoice);
            if (is_wp_error($this->api_response)) {
                $error_message = $this->api_response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                // Return a strict false on network failure so callers (e.g. subscription
                // renewals) reliably detect the failure instead of receiving null.
                return false;
            } else {
                if (ob_get_length()) {
                    ob_end_clean();
                }
                $api_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));
                if (!empty($api_response['status']) && $api_response['status'] == 'COMPLETED') {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    do_action('wpg_ppcp_save_payment_method_details', $woo_order_id, $api_response);
                    $payment_source = isset($api_response['payment_source']) ? $api_response['payment_source'] : '';
                    if (!empty($payment_source['card'])) {
                        $order->add_order_note($this->ppcp_build_card_details_note($payment_source['card']));
                    }
                    $processor_response = isset($api_response['purchase_units']['0']['payments']['captures']['0']['processor_response']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['processor_response'] : '';
                    if (!empty($processor_response['avs_code'])) {
                        $avs_response_order_note = __('Address Verification Result', 'woo-paypal-gateway');
                        $avs_response_order_note .= "\n";
                        $avs_response_order_note .= $processor_response['avs_code'];
                        if (isset($this->AVSCodes[$processor_response['avs_code']])) {
                            $avs_response_order_note .= ' : ' . $this->AVSCodes[$processor_response['avs_code']];
                        }
                        $order->add_order_note($avs_response_order_note);
                    }
                    if (!empty($processor_response['cvv_code'])) {
                        $cvv2_response_code = __('Card Security Code Result', 'woo-paypal-gateway');
                        $cvv2_response_code .= "\n";
                        $cvv2_response_code .= $processor_response['cvv_code'];
                        if (isset($this->CVV2Codes[$processor_response['cvv_code']])) {
                            $cvv2_response_code .= ' : ' . $this->CVV2Codes[$processor_response['cvv_code']];
                        }
                        $order->add_order_note($cvv2_response_code);
                    }
                    $currency_code = isset($api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['currency_code']) ? $api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['currency_code'] : '';
                    $paypal_fee = isset($api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['value']) ? $api_response['purchase_units'][0]['payments']['captures'][0]['seller_receivable_breakdown']['paypal_fee']['value'] : '';
                    if ($paypal_fee !== '' && floatval($paypal_fee) > 0) {
                        $order->update_meta_data('_paypal_fee', $paypal_fee);
                        $order->update_meta_data('_paypal_fee_currency_code', $currency_code);
                        $order->save_meta_data();
                    }
                    $transaction_id = isset($api_response['purchase_units']['0']['payments']['captures']['0']['id']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['id'] : '';
                    $seller_protection = isset($api_response['purchase_units']['0']['payments']['captures']['0']['seller_protection']['status']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['seller_protection']['status'] : '';
                    $payment_status = isset($api_response['purchase_units']['0']['payments']['captures']['0']['status']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['status'] : '';
                    if (empty($transaction_id) && !empty($api_response['purchase_units'][0]['payments']['authorizations'][0]['id'])) {
                        // AUTHORIZE intent: the response carries an authorization,
                        // not a capture. Without this branch an authorize-mode
                        // token charge (e.g. a subscription renewal) succeeded at
                        // PayPal but was reported as a failure here.
                        $authorization = $api_response['purchase_units'][0]['payments']['authorizations'][0];
                        $auth_status = isset($authorization['status']) ? strtoupper($authorization['status']) : '';
                        if (!in_array($auth_status, array('CREATED', 'PENDING'), true)) {
                            // translators: %s: authorization status.
                            $order->add_order_note(sprintf(__('Payment authorization was not successful. Status: %s', 'woo-paypal-gateway'), $auth_status ? $auth_status : 'UNKNOWN'));
                            return false;
                        }
                        if ($is_offer_charge) {
                            // translators: %s: PayPal authorization ID.
                            $order->add_order_note(sprintf(__('Additional offer amount authorized via saved payment method. Authorization ID: %s', 'woo-paypal-gateway'), $authorization['id']));
                            return $authorization['id'];
                        }
                        $order->update_meta_data('_auth_transaction_id', $authorization['id']);
                        $order->update_meta_data('_payment_action', 'authorize');
                        $order->update_meta_data('_payment_status', $auth_status);
                        $order->save_meta_data();
                        $order->set_transaction_id($authorization['id']);
                        $order->save();
                        $order->update_status('on-hold', __('Payment authorized via saved payment method. Change the order to one of your configured capture statuses to capture funds.', 'woo-paypal-gateway'));
                        return true;
                    }
                    if ($is_offer_charge) {
                        // The order passed in may be the already-paid parent order:
                        // record the extra charge as a note and hand the capture id
                        // back to the caller, but leave order status/transaction id
                        // untouched.
                        if ('COMPLETED' === $payment_status || 'PENDING' === $payment_status) {
                            // translators: 1: charged amount, 2: transaction ID.
                            $order->add_order_note(sprintf(__('Additional offer charge of %1$s completed via saved payment method. Transaction ID: %2$s', 'woo-paypal-gateway'), wc_price((float) $charge_override['total'], array('currency' => $order->get_currency())), $transaction_id));
                            return !empty($transaction_id) ? $transaction_id : true;
                        }
                        // translators: %s: PayPal capture status.
                        $order->add_order_note(sprintf(__('Offer charge attempt was not completed. PayPal capture status: %s', 'woo-paypal-gateway'), $payment_status ? $payment_status : 'UNKNOWN'));
                        return false;
                    }
                    if ($payment_status == 'COMPLETED') {
                        woo_paypal_gateway_set_order_payment_method_title_from_paypal_response($order, $api_response);
                        $order->payment_complete($transaction_id);
                        // translators: 1: Payment method title, 2: Payment status.
                        $order->add_order_note(sprintf(__('Payment via %1$s : %2$s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), ucfirst(strtolower($payment_status))));
                        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                        apply_filters('woocommerce_payment_successful_result', array('result' => 'success'), $woo_order_id);
                        $order->update_meta_data('_payment_status', $payment_status);
                        $order->save_meta_data();
                        // translators: 1: Payment method title, 2: Transaction ID.
                        $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $transaction_id));
                        $order->add_order_note('Seller Protection Status: ' . woo_paypal_gateway_ppcp_readable($seller_protection));
                    } else {
                        $payment_status_reason = isset($api_response['purchase_units']['0']['payments']['captures']['0']['status_details']['reason']) ? $api_response['purchase_units']['0']['payments']['captures']['0']['status_details']['reason'] : '';
                        $order->update_meta_data('_payment_status', $payment_status);
                        $order->save_meta_data();
                        // translators: 1: Payment method title, 2: Transaction ID.
                        $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $order->get_payment_method_title(), $transaction_id));
                        $order->add_order_note('Seller Protection Status: ' . woo_paypal_gateway_ppcp_readable($seller_protection));
                        $bool = woo_paypal_gateway_ppcp_update_woo_order_status($woo_order_id, $payment_status, $payment_status_reason, $processor_response);
                        if (!empty($transaction_id)) {
                            // Persist the capture id even while pending so the order stays
                            // refundable once it settles (reload to keep the on-hold status).
                            $fresh_order = wc_get_order($woo_order_id);
                            if ($fresh_order instanceof WC_Order && !$fresh_order->get_transaction_id() && !$fresh_order->has_status(wc_get_is_paid_statuses())) {
                                $fresh_order->set_transaction_id($transaction_id);
                                $fresh_order->save();
                            }
                        }
                        return $bool;
                    }
                    return true;
                } else {
                    $error_email_notification_param = array(
                        'request' => 'create_order',
                        'order_id' => $woo_order_id
                    );
                    $error_message = $this->ppcp_get_readable_message($this->api_response, $error_email_notification_param);
                    if (!empty(isset($woo_order_id) && !empty($woo_order_id))) {
                        $order->add_order_note($error_message);
                    }
                    return false;
                }
            }
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getFile() . ' ' . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
        // Never return null: any fall-through (network error, exception) is a failure.
        return false;
    }

    public function wpg_ppcp_add_payment_source($body_request, $order_id) {
        try {

            $order = wc_get_order($order_id);
            $user_id = (int) $order->get_customer_id();
            $all_payment_tokens = $this->wpg_ppcp_get_all_payment_tokens_for_renewal($user_id);
            $payment_tokens_id = $order->get_meta('_payment_tokens_id');
            if (empty($all_payment_tokens) && empty($payment_tokens_id)) {
                $order->add_order_note("Payment token unavailable for order renewal");
                return $body_request;
            }
            // Whether the stored token was found in the vault list but listed without a
            // payment_source block, which is not the same as not being there at all.
            $matched_without_source = false;
            if (!empty($all_payment_tokens) && !empty($payment_tokens_id)) {
                foreach ((array) $all_payment_tokens as $key => $paypal_payment_token) {
                    if (!is_array($paypal_payment_token)
                        || !isset($paypal_payment_token['id'])
                        || $paypal_payment_token['id'] !== $payment_tokens_id) {
                        continue;
                    }
                    // PayPal does not always return the payment_source block for a listed
                    // token. That is not an error and the token is still chargeable — the
                    // vault id is what identifies the stored credential — so fall through
                    // to the stored-id path below, which charges this exact token under
                    // the type recorded on the order, instead of reading a missing key.
                    if (empty($paypal_payment_token['payment_source']) || !is_array($paypal_payment_token['payment_source'])) {
                        $matched_without_source = true;
                        break;
                    }
                    foreach ($paypal_payment_token['payment_source'] as $type_key => $payment_tokens_data) {
                        $body_request['payment_source'] = array($type_key => array('vault_id' => $payment_tokens_id));
                        $this->applyStoredCredentialParameter($type_key, $body_request, 'recurring');
                        $order->update_meta_data('_wpg_ppcp_used_payment_method', $type_key);
                        $order->save();
                        return $body_request;
                    }
                }
            }
            // First-token fallback. This charges the customer's FIRST vault token and
            // rewrites the subscription's stored token id. It is only safe when NO
            // specific token was recorded on the order (legacy/tokenless renewal).
            //
            // When a specific _payment_tokens_id WAS stored but was not matched above,
            // charging a different saved card would bill the customer's wrong card, so
            // the fallback is suppressed by default. The code below then uses the stored
            // id directly, so PayPal charges the correct token or fails cleanly — it
            // never charges a different card. A store that truly wants the old behaviour
            // can re-enable it via the filter (default false).
            $allow_first_token_fallback = empty($payment_tokens_id)
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                || (bool) apply_filters('wpg_ppcp_renewal_charge_first_token_when_stored_missing', false, $order_id);
            if (!empty($payment_tokens_id) && !$allow_first_token_fallback) {
                $this->ppcp_log($matched_without_source
                    ? 'Renewal order #' . $order_id . ': stored _payment_tokens_id is in the customer vault list but PayPal returned no payment_source for it; charging that token under its recorded type.'
                    : 'Renewal order #' . $order_id . ': stored _payment_tokens_id not found in the customer vault list; first-token fallback suppressed to avoid charging a different saved card.');
            }
            if (!empty($all_payment_tokens) && $allow_first_token_fallback) {
                foreach ((array) $all_payment_tokens as $key => $paypal_payment_token) {
                    // Same guard as above: skip entries PayPal listed without a usable
                    // payment_source rather than reading a missing key off them.
                    if (!is_array($paypal_payment_token)
                        || empty($paypal_payment_token['id'])
                        || empty($paypal_payment_token['payment_source'])
                        || !is_array($paypal_payment_token['payment_source'])) {
                        continue;
                    }
                    foreach ($paypal_payment_token['payment_source'] as $type_key => $payment_tokens_data) {
                        $order->update_meta_data('_payment_tokens_id', $paypal_payment_token['id']);
                        $body_request['payment_source'] = array($type_key => array('vault_id' => $paypal_payment_token['id']));
                        $this->applyStoredCredentialParameter($type_key, $body_request, 'recurring');
                        $wpg_ppcp_payment_method_title = woo_paypal_gateway_ppcp_get_payment_method_title($type_key);
                        $order->set_payment_method_title($wpg_ppcp_payment_method_title);
                        $order->update_meta_data('_wpg_ppcp_used_payment_method', $type_key);
                        $order->save();
                        return $body_request;
                    }
                }
            }
            if (!isset($body_request['payment_source'])) {
                if (empty($all_payment_tokens) && !empty($payment_tokens_id)) {
                    $payment_method = $order->get_meta('_wpg_ppcp_used_payment_method');
                    if (!in_array($payment_method, ['paypal', 'card', 'google_pay', 'apple_pay', 'venmo'], true)) {
                        $payment_method = 'paypal';
                    }
                    $body_request['payment_source'] = array($payment_method => array('vault_id' => $payment_tokens_id));
                    $this->applyStoredCredentialParameter($payment_method, $body_request, 'recurring');
                } elseif (!empty($payment_tokens_id)) {
                    // Stored token id present but not in the fetched vault list (e.g. the
                    // list was paginated). Charge that exact stored token under its own
                    // recorded type so the correct card is used — never a different one.
                    $payment_method = $order->get_meta('_wpg_ppcp_used_payment_method');
                    if (!in_array($payment_method, ['paypal', 'card', 'google_pay', 'apple_pay', 'venmo'], true)) {
                        $payment_method = 'paypal';
                    }
                    $body_request['payment_source'] = array($payment_method => array('vault_id' => $payment_tokens_id));
                    $this->applyStoredCredentialParameter($payment_method, $body_request, 'recurring');
                }
            }
        } catch (Exception $ex) {
            return $body_request;
        }
        // Only (re)apply a title when we resolved a payment method above; otherwise leave
        // the order's existing title untouched (guards against an undefined variable).
        if (isset($payment_method) && !empty($payment_method)) {
            $order->set_payment_method_title($payment_method);
            $order->save();
        }
        return $body_request;
    }

    private function applyStoredCredentialParameter($paymentMethod, &$bodyRequest, $context = 'unscheduled') {
        $storedCredentials = [];
        switch ($paymentMethod) {
            case 'card':
            case 'apple_pay':
            case 'google_pay':
                // Current behaviour: merchant-initiated, unscheduled, subsequent.
                $storedCredentials = array(
                    'payment_initiator' => 'MERCHANT',
                    'payment_type' => 'UNSCHEDULED',
                    'usage' => 'SUBSEQUENT'
                );
                // Network-accurate classification (opt-in). A scheduled subscription
                // renewal should be RECURRING rather than UNSCHEDULED. Off by default so
                // live decline behaviour is unchanged until a store enables and tests it:
                //   add_filter( 'wpg_ppcp_use_recommended_stored_credentials', '__return_true' );
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                if (apply_filters('wpg_ppcp_use_recommended_stored_credentials', false, $context, $paymentMethod)) {
                    if ($context === 'recurring') {
                        $storedCredentials['payment_type'] = 'RECURRING';
                    }
                }
                break;
        }
        if (!empty($storedCredentials)) {
            $bodyRequest['payment_source'][$paymentMethod]['stored_credential'] = $storedCredentials;
        }
    }

    public function wpg_ppcp_get_all_payment_tokens_for_renewal($user_id) {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id_for_user($user_id, $this->is_sandbox);
            if ($paypal_generated_customer_id === false) {
                return false;
            }
            $args = array(
                'method' => 'GET',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                'body' => array()
            );
            $payment_tokens_url = add_query_arg(array('customer_id' => $paypal_generated_customer_id), untrailingslashit($this->payment_tokens_url));
            $api_response = wp_remote_post($payment_tokens_url, $args);
            $api_response = json_decode(wp_remote_retrieve_body($api_response), true);
            if (ob_get_length()) {
                ob_end_clean();
            }
            if (!empty($api_response['customer']['id']) && isset($api_response['payment_tokens'])) {
                return $api_response['payment_tokens'];
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_paypal_setup_tokens_sub_change_payment($order_id) {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $body_request = array();
            $body_request['payment_source']['paypal']['description'] = "Billing Agreement";
            $body_request['payment_source']['paypal']['permit_multiple_payment_tokens'] = true;
            $body_request['payment_source']['paypal']['usage_pattern'] = 'IMMEDIATE';
            $body_request['payment_source']['paypal']['usage_type'] = 'MERCHANT';
            $body_request['payment_source']['paypal']['customer_type'] = 'CONSUMER';
            $body_request['payment_source']['paypal']['experience_context'] = array(
                'shipping_preference' => 'GET_FROM_FILE',
                'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                'brand_name' => $this->brand_name,
                'locale' => $this->valid_bcp47_code(),
                'return_url' => add_query_arg(array('ppcp_action' => 'paypal_create_payment_token_sub_change_payment', 'utm_nooverride' => '1', 'customer_id' => get_current_user_id(), 'order_id' => $order_id, 'order_key' => ($change_payment_order instanceof WC_Order ? $change_payment_order->get_order_key() : '')), untrailingslashit(WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'))),
                'cancel_url' => wc_get_checkout_url()
            );
            $user_id = get_current_user_id();
            $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id_for_user($user_id, $this->is_sandbox);
            if (!empty($paypal_generated_customer_id)) {
                $body_request['customer']['id'] = $paypal_generated_customer_id;
            }
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            $body_request = json_encode($body_request);
            $args = array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                'body' => $body_request
            );
            $this->api_response = wp_remote_post($this->setup_tokens_url, $args);
            if (is_wp_error($this->api_response)) {
                $error_message = $this->api_response->get_error_message();
                $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
            } else {
                $this->ppcp_log('Response : ' . wc_print_r($this->api_response, true));
                $this->api_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                if (!empty($this->api_response['id'])) {
                    if (!empty($this->api_response['links'])) {
                        foreach ($this->api_response['links'] as $key => $link_result) {
                            if ('approve' === $link_result['rel']) {
                                return array(
                                    'result' => 'success',
                                    'redirect' => $link_result['href']
                                );
                            }
                        }
                    }
                    return array(
                        'result' => 'failure',
                        'redirect' => woo_paypal_gateway_ppcp_get_view_sub_order_url($order_id)
                    );
                } else {
                    $error_email_notification_param = array(
                        'request' => 'setup_tokens'
                    );
                    $error_message = $this->ppcp_get_readable_message($this->api_response, $error_email_notification_param);
                    wc_add_notice($error_message, 'error');
                    return array(
                        'result' => 'failure',
                        'redirect' => woo_paypal_gateway_ppcp_get_view_sub_order_url($order_id)
                    );
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_paypal_create_payment_token_sub_change_payment() {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $body_request = array();
            if (isset($_GET['approval_token_id']) && isset($_GET['order_id'])) {
                // Only the logged-in owner of the order may change its subscription payment
                // method. Verify ownership (and the order key when present) before exchanging
                // the setup token or writing any payment token onto the order, otherwise a
                // guessable order id would let one customer overwrite another's billing token.
                $ownership_order = wc_get_order(absint(wp_unslash($_GET['order_id'])));
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                $ownership_key = isset($_GET['order_key']) ? wc_clean(wp_unslash($_GET['order_key'])) : '';
                if (!$ownership_order instanceof WC_Order
                        || !is_user_logged_in()
                        || (int) $ownership_order->get_user_id() !== get_current_user_id()
                        || ($ownership_key !== '' && !$ownership_order->key_is_valid($ownership_key))) {
                    wp_safe_redirect(wc_get_page_permalink('myaccount'));
                    exit();
                }
                $body_request['payment_source']['token'] = array(
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                    'id' => wc_clean(wp_unslash($_GET['approval_token_id'])),
                    'type' => 'SETUP_TOKEN'
                );
                $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
                $body_request = json_encode($body_request);
                $args = array(
                    'method' => 'POST',
                    'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                    'body' => $body_request
                );

                $this->api_response = wp_remote_post($this->payment_tokens_url, $args);
                if (ob_get_length()) {
                    ob_end_clean();
                }
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                $order_id = wc_clean(wp_unslash($_GET['order_id']));
                $order = wc_get_order($order_id);
                if (is_wp_error($this->api_response)) {
                    $error_message = $this->api_response->get_error_message();
                    $this->ppcp_log('Error Message : ' . wc_print_r($error_message, true));
                } else {
                    $this->api_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                    $this->ppcp_log('Response : ' . wc_print_r($this->api_response, true));
                    if (!empty($this->api_response['id'])) {
                        $customer_id = $this->api_response['customer']['id'] ?? '';
                        if (isset($customer_id) && !empty($customer_id)) {
                            $this->payment_token->add_paypal_customer_id($customer_id, $this->is_sandbox);
                        }
                        $order->update_meta_data('_ppcp_used_payment_method', 'paypal');
                        $order->save();
                        $this->save_payment_token($order, $this->api_response['id']);
                        if (woo_paypal_gateway_ppcp_get_token_id_by_token($this->api_response['id']) === '') {
                            $token = new WC_Payment_Token_CC();
                            if (0 != $order->get_user_id()) {
                                $wc_customer_id = $order->get_user_id();
                            } else {
                                $wc_customer_id = get_current_user_id();
                            }
                            if (isset($this->api_response['payment_source']['paypal']['email_address'])) {
                                $email_address = $this->api_response['payment_source']['paypal']['email_address'];
                            } elseif ($this->api_response['payment_source']['paypal']['payer_id']) {
                                $email_address = $this->api_response['payment_source']['paypal']['payer_id'];
                            } else {
                                $email_address = 'PayPal Vault';
                            }
                            $token->set_token($this->api_response['id']);
                            $token->set_gateway_id($order->get_payment_method());
                            $token->set_card_type('PayPal Vault');
                            $token->set_last4(substr($this->api_response['id'], -4));
                            $token->set_expiry_month( gmdate( 'm' ) );
                            $token->set_expiry_year( gmdate( 'Y', strtotime( '+20 years' ) ) );
                            $token->set_user_id($wc_customer_id);
                            if ($token->validate()) {
                                $token->save();
                                update_metadata('payment_token', $token->get_id(), '_ppcp_used_payment_method', 'paypal');
                                wp_safe_redirect(woo_paypal_gateway_ppcp_get_view_sub_order_url($order_id));
                                exit();
                            } else {
                                $order->add_order_note('ERROR MESSAGE: ' . __('Invalid or missing payment token fields.', 'woo-paypal-gateway'));
                            }
                        }
                        wp_safe_redirect(woo_paypal_gateway_ppcp_get_view_sub_order_url($order_id));
                        exit();
                    } else {
                        $error_email_notification_param = array(
                            'request' => 'create_payment_token'
                        );
                        $error_message = $this->ppcp_get_readable_message($this->api_response, $error_email_notification_param);
                        wc_add_notice($error_message, 'error');
                        wp_safe_redirect(woo_paypal_gateway_ppcp_get_view_sub_order_url($order_id));
                        exit();
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    /**
     * Zero-total signup (free-trial subscription or charge-upon-release
     * pre-order) with no saved payment method: vault the buyer's PayPal wallet
     * via a setup token without charging anything. Returns the process_payment
     * result array with the PayPal approval redirect.
     *
     * @param int $order_id WooCommerce order id.
     * @return array{result:string, redirect:string}
     */
    public function ppcp_paypal_setup_tokens_zero_total($order_id) {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $body_request = array();
            $body_request['payment_source']['paypal']['description'] = "Billing Agreement";
            $body_request['payment_source']['paypal']['permit_multiple_payment_tokens'] = true;
            $body_request['payment_source']['paypal']['usage_pattern'] = 'IMMEDIATE';
            $body_request['payment_source']['paypal']['usage_type'] = 'MERCHANT';
            $body_request['payment_source']['paypal']['customer_type'] = 'CONSUMER';
            $body_request['payment_source']['paypal']['experience_context'] = array(
                'shipping_preference' => 'NO_SHIPPING',
                'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                'brand_name' => $this->brand_name,
                'locale' => $this->valid_bcp47_code(),
                'return_url' => add_query_arg(array('ppcp_action' => 'paypal_create_payment_token_zero_total', 'utm_nooverride' => '1', 'order_id' => $order_id, 'order_key' => $order->get_order_key()), untrailingslashit(WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'))),
                'cancel_url' => wc_get_checkout_url()
            );
            $user_id = (int) $order->get_customer_id();
            $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id_for_user($user_id, $this->is_sandbox);
            if (!empty($paypal_generated_customer_id)) {
                $body_request['customer']['id'] = $paypal_generated_customer_id;
            }
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            $body_request = json_encode($body_request);
            $args = array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('zero-total-setup-' . $order_id)),
                'body' => $body_request
            );
            $this->ppcp_add_log_details('Create setup token for zero-total signup');
            $this->api_response = wp_remote_post($this->setup_tokens_url, $args);
            if (is_wp_error($this->api_response)) {
                $this->ppcp_log('Error Message : ' . wc_print_r($this->api_response->get_error_message(), true));
            } else {
                $this->api_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($this->api_response), true));
                if (!empty($this->api_response['id']) && !empty($this->api_response['links'])) {
                    foreach ($this->api_response['links'] as $link_result) {
                        if ('approve' === $link_result['rel']) {
                            return array(
                                'result' => 'success',
                                'redirect' => $link_result['href']
                            );
                        }
                    }
                } elseif (!empty($this->api_response)) {
                    $error_message = $this->ppcp_get_readable_message($this->api_response, array('request' => 'setup_tokens'));
                    if (function_exists('wc_add_notice')) {
                        wc_add_notice($error_message, 'error');
                    }
                }
            }
        } catch (Exception $ex) {
            $this->ppcp_log('Zero-total setup token exception: ' . $ex->getMessage());
        }
        if (function_exists('wc_add_notice') && 0 === wc_notice_count('error')) {
            wc_add_notice(__('We could not start the PayPal approval for your free signup. Please try again.', 'woo-paypal-gateway'), 'error');
        }
        return array('result' => 'failure', 'redirect' => wc_get_checkout_url());
    }

    /**
     * Return handler for the zero-total signup approval: exchange the approved
     * setup token for a vault payment token, attach it to the order (and its
     * subscriptions), then complete the order WITHOUT charging - marking it as
     * pre-ordered when it contains a charge-upon-release pre-order.
     */
    public function ppcp_paypal_create_payment_token_zero_total() {
        $order = null;
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $order_id = isset($_GET['order_id']) ? absint(wp_unslash($_GET['order_id'])) : 0;
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
            $order_key = isset($_GET['order_key']) ? wc_clean(wp_unslash($_GET['order_key'])) : '';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
            $approval_token_id = isset($_GET['approval_token_id']) ? wc_clean(wp_unslash($_GET['approval_token_id'])) : '';
            $order = $order_id ? wc_get_order($order_id) : null;
            // This callback only vaults a payment method for a genuinely zero-total signup;
            // it never captures money. Refuse to "complete" an order that actually needs
            // payment (or is already paid), otherwise a paid order whose key the buyer holds
            // could be marked complete here without any charge.
            if (!$order instanceof WC_Order || !$order->key_is_valid($order_key) || empty($approval_token_id)
                    || (float) $order->get_total() > 0 || $order->is_paid()) {
                wp_safe_redirect(wc_get_checkout_url());
                exit();
            }
            $body_request = array(
                'payment_source' => array(
                    'token' => array(
                        'id' => $approval_token_id,
                        'type' => 'SETUP_TOKEN'
                    ),
                ),
            );
            $args = array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id('zero-total-exchange-' . $order_id)),
                'body' => json_encode($body_request)
            );
            $this->ppcp_add_log_details('Exchange setup token (zero-total signup)');
            $this->api_response = wp_remote_post($this->payment_tokens_url, $args);
            if (ob_get_length()) {
                ob_end_clean();
            }
            if (!is_wp_error($this->api_response)) {
                $this->api_response = json_decode(wp_remote_retrieve_body($this->api_response), true);
                $this->ppcp_log('Response : ' . wc_print_r($this->ppcp_redact_sensitive_data($this->api_response), true));
                if (!empty($this->api_response['id'])) {
                    $customer_id = isset($this->api_response['customer']['id']) ? $this->api_response['customer']['id'] : '';
                    if (!empty($customer_id)) {
                        $this->payment_token->add_paypal_customer_id($customer_id, $this->is_sandbox);
                    }
                    $order->update_meta_data('_wpg_ppcp_used_payment_method', 'paypal');
                    $order->save();
                    $this->save_payment_token($order, $this->api_response['id']);
                    woo_paypal_gateway_ppcp_complete_zero_total_order($order, $this->api_response['id']);
                    if (function_exists('WC') && WC()->cart) {
                        WC()->cart->empty_cart();
                    }
                    wp_safe_redirect($order->get_checkout_order_received_url());
                    exit();
                }
                $error_message = $this->ppcp_get_readable_message($this->api_response, array('request' => 'create_payment_token'));
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($error_message, 'error');
                }
            }
        } catch (Exception $ex) {
            $this->ppcp_log('Zero-total token exchange exception: ' . $ex->getMessage());
        }
        wp_safe_redirect(wc_get_checkout_url());
        exit();
    }

    /**
     * Create a PayPal setup token for the My-Account "Add payment method" flow.
     *
     * Unlike the subscription change-payment flow this is not tied to an order.
     * Returns the PayPal approval URL the customer must be redirected to.
     *
     * @return array{result:string, redirect:string}
     */
    public function ppcp_add_payment_method_setup_token() {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $user_id = get_current_user_id();
            $account_url = wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount'));

            $body_request = array();
            $body_request['payment_source']['paypal']['description'] = 'Billing Agreement';
            $body_request['payment_source']['paypal']['permit_multiple_payment_tokens'] = true;
            $body_request['payment_source']['paypal']['usage_pattern'] = 'IMMEDIATE';
            $body_request['payment_source']['paypal']['usage_type'] = 'MERCHANT';
            $body_request['payment_source']['paypal']['customer_type'] = 'CONSUMER';
            $body_request['payment_source']['paypal']['experience_context'] = array(
                'shipping_preference' => 'GET_FROM_FILE',
                'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                'brand_name' => $this->brand_name,
                'locale' => $this->valid_bcp47_code(),
                'return_url' => add_query_arg(array('ppcp_action' => 'ppcp_add_payment_method_callback', 'utm_nooverride' => '1', 'customer_id' => $user_id), untrailingslashit(WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'))),
                'cancel_url' => $account_url,
            );
            $paypal_generated_customer_id = $this->payment_token->get_paypal_customer_id_for_user($user_id, $this->is_sandbox);
            if (!empty($paypal_generated_customer_id)) {
                $body_request['customer']['id'] = $paypal_generated_customer_id;
            }
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            $body_request = json_encode($body_request);
            $args = array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                'body' => $body_request,
            );
            $response = wp_remote_post($this->setup_tokens_url, $args);
            if (is_wp_error($response)) {
                $this->ppcp_log('Add payment method setup token error: ' . wc_print_r($response->get_error_message(), true));
                return array('result' => 'failure', 'redirect' => $account_url);
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($api_response['links'])) {
                foreach ($api_response['links'] as $link_result) {
                    if ('approve' === $link_result['rel']) {
                        return array('result' => 'success', 'redirect' => $link_result['href']);
                    }
                }
            }
            $error_message = $this->ppcp_get_readable_message($api_response, array('request' => 'setup_tokens'));
            wc_add_notice($error_message, 'error');
            return array('result' => 'failure', 'redirect' => $account_url);
        } catch (Exception $ex) {
            $this->ppcp_log('Add payment method setup token exception: ' . $ex->getMessage());
            return array('result' => 'failure', 'redirect' => wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount')));
        }
    }

    /**
     * Callback after the customer approves the setup token: exchange it for a
     * permanent payment (vault) token and save it as a WC payment token.
     */
    public function ppcp_add_payment_method_create_token() {
        $account_url = wc_get_endpoint_url('payment-methods', '', wc_get_page_permalink('myaccount'));
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            if (empty($_GET['approval_token_id'])) {
                wp_safe_redirect($account_url);
                exit();
            }
            // This is a My-Account "add payment method" return: the saved token must belong
            // to the current logged-in user. Never trust a client-supplied customer_id as the
            // owner, or an attacker could plant a payment method on another account.
            if (!is_user_logged_in()) {
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
                exit();
            }
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $user_id = get_current_user_id();

            $body_request = array();
            $body_request['payment_source']['token'] = array(
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                'id' => wc_clean(wp_unslash($_GET['approval_token_id'])),
                'type' => 'SETUP_TOKEN',
            );
            $body_request = woo_paypal_gateway_ppcp_remove_empty_key($body_request);
            $body_request = json_encode($body_request);
            $args = array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => "Bearer " . $this->access_token, "prefer" => "return=representation", 'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB', 'PayPal-Request-Id' => $this->generate_request_id()),
                'body' => $body_request,
            );
            $response = wp_remote_post($this->payment_tokens_url, $args);
            if (ob_get_length()) {
                ob_end_clean();
            }
            if (is_wp_error($response)) {
                $this->ppcp_log('Add payment method create token error: ' . wc_print_r($response->get_error_message(), true));
                wc_add_notice(__('Unable to save your PayPal payment method. Please try again.', 'woo-paypal-gateway'), 'error');
                wp_safe_redirect($account_url);
                exit();
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($api_response['id'])) {
                $customer_id = isset($api_response['customer']['id']) ? $api_response['customer']['id'] : '';
                if (!empty($customer_id)) {
                    $this->payment_token->add_paypal_customer_id($customer_id, $this->is_sandbox);
                }
                if (woo_paypal_gateway_ppcp_get_token_id_by_token($api_response['id']) === '') {
                    $token = new WC_Payment_Token_CC();
                    $token->set_token($api_response['id']);
                    $token->set_gateway_id('wpg_paypal_checkout_cc');
                    $token->set_card_type('PayPal Vault');
                    $token->set_last4(substr($api_response['id'], -4));
                    $token->set_expiry_month(gmdate('m'));
                    $token->set_expiry_year(gmdate('Y', strtotime('+20 years')));
                    $token->set_user_id($user_id);
                    if ($token->validate()) {
                        $token->save();
                        update_metadata('payment_token', $token->get_id(), '_ppcp_used_payment_method', 'paypal');
                        wc_add_notice(__('Payment method successfully added.', 'woo-paypal-gateway'));
                    } else {
                        wc_add_notice(__('Invalid or missing payment token fields.', 'woo-paypal-gateway'), 'error');
                    }
                }
                wp_safe_redirect($account_url);
                exit();
            }
            $error_message = $this->ppcp_get_readable_message($api_response, array('request' => 'create_payment_token'));
            wc_add_notice($error_message, 'error');
            wp_safe_redirect($account_url);
            exit();
        } catch (Exception $ex) {
            $this->ppcp_log('Add payment method create token exception: ' . $ex->getMessage());
            wp_safe_redirect($account_url);
            exit();
        }
    }

    public function save_payment_token($order, $payment_tokens_id) {
        $order_id = $order->get_id();
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order_id)) {
            $subscriptions = wcs_get_subscriptions_for_order($order_id);
        } elseif (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order_id)) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order_id);
        } else {
            $subscriptions = array();
        }
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                $subscription->update_meta_data('_payment_tokens_id', $payment_tokens_id);
                $subscription->save();
            }
        } else {
            $order->update_meta_data('_payment_tokens_id', $payment_tokens_id);
            $order->save();
        }
    }

    public function valid_bcp47_code() {
        $locale = str_replace('_', '-', get_user_locale());
        if (preg_match('/^[a-z]{2}(?:-[A-Z][a-z]{3})?(?:-(?:[A-Z]{2}))?$/', $locale)) {
            return $locale;
        }
        $parts = explode('-', $locale);
        if (count($parts) === 3) {
            $ret = substr($locale, 0, strrpos($locale, '-'));
            if (false !== $ret) {
                return $ret;
            }
        }
        return 'en';
    }

    public function ppcp_add_tracking_api_info($order_id, $body_request) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Tracking')) {
            require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-tracking.php';
        }
        $ppcp_tracking = PPCP_Paypal_Checkout_For_Woocommerce_Tracking::get_instance();
        $carrier_list = $ppcp_tracking->carrier_name();
        if ($this->access_token === false) {
            $this->access_token = $this->ppcp_get_access_token();
        }
        $request = $body_request;
        foreach ($request['trackers'] as &$tracker) {
            $carrier_found = false;
            $input_carrier = isset($tracker['carrier']) ? $tracker['carrier'] : '';
            foreach ($carrier_list as $country_group) {
                if (empty($country_group['items']) || !is_array($country_group['items'])) {
                    continue;
                }
                foreach ($country_group['items'] as $carrier_code => $carrier_name) {
                    if (strcasecmp($carrier_name, $input_carrier) === 0) {
                        $carrier_found = true;
                        break 2;
                    }
                }
            }
            if (!$carrier_found && !empty($input_carrier)) {
                $tracker['carrier_name_other'] = $input_carrier;
                $tracker['carrier'] = 'OTHER';
            }
        }
        unset($tracker);
        $body_request = json_encode($request);
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->access_token,
                'prefer' => 'return=representation',
                'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
                'PayPal-Request-Id' => $this->generate_request_id(),
            ),
            'body' => $body_request,
        );
        $this->api_response = wp_remote_post($this->tracking_api_url, $args);
        if (ob_get_length()) {
            ob_end_clean();
        }
        $order = wc_get_order($order_id);
        $this->ppcp_log('PayPal Tracking Request: ' . wc_print_r($body_request, true));
        if (is_wp_error($this->api_response)) {
            $error_message = $this->api_response->get_error_message();
            $this->ppcp_log('PayPal Tracking Error: ' . $error_message);
            return false;
        }
        $response_body = wp_remote_retrieve_body($this->api_response);
        $this->ppcp_log('PayPal Tracking Response: ' . wc_print_r($response_body, true));
        $this->api_response = json_decode($response_body, true);
        if (empty($this->api_response['errors'])) {
            $tracker = isset($request['trackers'][0]) ? $request['trackers'][0] : array();
            $tracking_number = isset($tracker['tracking_number']) ? $tracker['tracking_number'] : 'N/A';
            if (isset($tracker['carrier_name_other']) && !empty($tracker['carrier_name_other'])) {
                $carrier = isset($tracker['carrier_name_other']) ? $tracker['carrier_name_other'] : 'N/A';
            } else {
                $carrier = isset($tracker['carrier']) ? $tracker['carrier'] : 'N/A';
            }
            $status = isset($tracker['status']) ? $tracker['status'] : 'N/A';
            $order->add_order_note("Tracking information submitted to PayPal:\nTracking Number: {$tracking_number}\nCarrier: {$carrier}\nStatus: {$status}");
            return true;
        }
        return false;
    }
    
    public function ppcp_update_order_from_cart() {
        try {
            if ($this->access_token === false) {
                $this->access_token = $this->ppcp_get_access_token();
            }

            // Check if we have the necessary session data
            $reference_id    = woo_paypal_gateway_ppcp_get_session('ppcp_reference_id');
            $session_data    = woo_paypal_gateway_ppcp_get_paypal_order_session_data();
            $paypal_order_id = ! empty( $session_data['id'] ) ? $session_data['id'] : '';

            if (empty($reference_id) || empty($paypal_order_id)) {
                $this->ppcp_log('Missing required session data: reference_id or paypal_order_id');
                return false;
            }

            $patch_request         = array();
            $update_amount_request = array();

            // Match decimal precision to the active currency actually sent to PayPal.
            $this->decimals = $this->ppcp_get_number_of_decimal_digits($this->ppcp_get_currency());
            // Get cart details instead of order details
            $cart = $this->ppcp_get_details_from_cart();

            $cart_total = woo_paypal_gateway_ppcp_round($cart['order_total'], $this->decimals);
            if ((float) $cart_total <= 0) {
                $this->ppcp_log('Update order from cart skipped: cart total is ' . $cart_total . '. PayPal does not accept zero or negative amounts.');
                return false;
            }

            // Shipping or Billing Address from customer.
            //
            // PayPal's pre-approval shipping-change callback only shares the buyer's
            // city/state/postcode/country — the street is withheld until the buyer
            // approves — so a street line stored on the WC customer at this point is
            // stale data from an earlier checkout on the same session. Never send
            // address_line_1/2 here; PayPal already holds the buyer's full address.
            $shipping_address_request = array();
            $customer                 = WC()->customer;

            if ($customer && is_a($customer, 'WC_Customer')) {
                if ($customer->get_shipping_city() || $customer->get_shipping_postcode()) {
                    $shipping_city      = $customer->get_shipping_city();
                    $shipping_state     = $customer->get_shipping_state();
                    $shipping_postcode  = $customer->get_shipping_postcode();
                    $shipping_country   = $customer->get_shipping_country();
                } else {
                    $shipping_city      = $customer->get_billing_city();
                    $shipping_state     = $customer->get_billing_state();
                    $shipping_postcode  = $customer->get_billing_postcode();
                    $shipping_country   = $customer->get_billing_country();
                }

                if (!empty($shipping_city) && !empty($shipping_country)) {
                    $shipping_address_request = array_filter(array(
                        'admin_area_2' => $shipping_city,
                        'admin_area_1' => $shipping_state,
                        'postal_code'  => $shipping_postcode,
                        'country_code' => $shipping_country,
                    ));
                }
            }

            if ($this->send_items === true) {
            if (isset($cart['total_item_amount']) && $cart['total_item_amount'] > 0) {
                $update_amount_request['item_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                    'value'         => woo_paypal_gateway_ppcp_round($cart['total_item_amount'], $this->decimals)
                );
            }
            if (isset($cart['discount']) && $cart['discount'] > 0) {
                $update_amount_request['discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                    'value'         => woo_paypal_gateway_ppcp_round($cart['discount'], $this->decimals)
                );
            }
            if (isset($cart['shipping']) && $cart['shipping'] > 0) {
                $update_amount_request['shipping'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                    'value'         => woo_paypal_gateway_ppcp_round($cart['shipping'], $this->decimals)
                );
            }
            if (isset($cart['ship_discount_amount']) && $cart['ship_discount_amount'] > 0) {
                $update_amount_request['shipping_discount'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                    'value'         => woo_paypal_gateway_ppcp_round($cart['ship_discount_amount'], $this->decimals),
                );
            }
            if (isset($cart['order_tax']) && $cart['order_tax'] > 0) {
                $update_amount_request['tax_total'] = array(
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                    'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                    'value'         => woo_paypal_gateway_ppcp_round($cart['order_tax'], $this->decimals)
                );
            }
            }

            $amount_value = array(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                'value'         => woo_paypal_gateway_ppcp_round($cart['order_total'], $this->decimals),
            );
            if (!empty($update_amount_request)) {
                $amount_value['breakdown'] = $update_amount_request;
            }
            // Fail-safe: drop a non-reconciling breakdown so the PATCH is not rejected by
            // PayPal for a breakdown mismatch (same guard as order create/update).
            $guarded_pu = $this->ppcp_enforce_breakdown_invariant(array('amount' => $amount_value), $this->decimals);
            if (isset($guarded_pu['amount'])) {
                $amount_value = $guarded_pu['amount'];
            }
            $patch_request[] = array(
                'op'    => 'add',
                'path'  => "/purchase_units/@reference_id=='$reference_id'/amount",
                'value' => $amount_value,
            );

            // =========================
            // NEW: shipping options block
            // =========================
            $shipping_options = array();

            $packages       = WC()->shipping()->get_packages();
            $chosen_methods = WC()->session->get('chosen_shipping_methods', array());

            if (!empty($packages)) {
                foreach ($packages as $package_index => $package) {

                    if (empty($package['rates']) || !is_array($package['rates'])) {
                        continue;
                    }

                    $chosen_for_package = isset($chosen_methods[$package_index]) ? $chosen_methods[$package_index] : '';

                    foreach ($package['rates'] as $rate_id => $rate) {

                        // Base cost
                        $rate_cost = (float) $rate->get_cost();

                        $shipping_options[] = array(
                            'id'       => $rate_id, // e.g. flat_rate:1
                            'label'    => $rate->get_label(),
                            'type'     => 'SHIPPING',
                            'selected' => ($rate_id === $chosen_for_package),
                            'amount'   => array(
                                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                                'currency_code' => apply_filters('wpg_ppcp_woocommerce_currency', $this->ppcp_get_currency()),
                                'value'         => woo_paypal_gateway_ppcp_round($rate_cost, $this->decimals),
                            ),
                        );
                    }

                    // Typically only one package is sent to PayPal
                    break;
                }
            }

            $this->ppcp_log('Shipping options sent to PayPal: ' . wc_print_r($shipping_options, true));

            if (!empty($shipping_options)) {
                $patch_request[] = array(
                    'op'   => 'add',
                    'path' => "/purchase_units/@reference_id=='$reference_id'/shipping/options",
                    'value' => $shipping_options,
                );
            }
            // =========================
            // END shipping options block
            // =========================

            // Update shipping address if available
            if (!empty($shipping_address_request) && !empty(array_filter($shipping_address_request))) {
                $patch_request[] = array(
                    'op'   => 'add',
                    'path' => "/purchase_units/@reference_id=='$reference_id'/shipping/address",
                    'value' => $shipping_address_request
                );
            }

            // Update invoice ID for cart (using reference ID since no order number exists yet)
            $patch_request[] = array(
                'op'   => 'add',
                'path' => "/purchase_units/@reference_id=='$reference_id'/invoice_id",
                'value' => $reference_id
            );

            // Update custom ID for cart
            $update_custom_id = wp_json_encode(
                array(
                    'order_id'  => $reference_id,
                    'order_key' => $reference_id,
                )
            );
            $patch_request[] = array(
                'op'   => 'add',
                'path' => "/purchase_units/@reference_id=='$reference_id'/custom_id",
                'value' => $update_custom_id
            );

            // Convert the patch request array to JSON
            $patch_request_json = json_encode($patch_request);

            // Check if JSON encoding failed
            if ($patch_request_json === false) {
                $this->ppcp_log('JSON encode error: ' . json_last_error_msg());
                return false;
            }

            $this->ppcp_add_log_details('Update order from cart');
            $this->ppcp_log('Endpoint: ' . $this->paypal_order_api . $paypal_order_id);
            $this->ppcp_log('Request: ' . $patch_request_json);

            // The PATCH changes amounts/shipping at PayPal: drop any snapshot
            // fetched earlier in this request.
            self::ppcp_forget_order_snapshot($paypal_order_id);
            // Send the request to PayPal
            $response = wp_remote_request($this->paypal_order_api . $paypal_order_id, array(
                'timeout'     => $this->ppcp_interactive_timeout(),
                'method'      => 'PATCH',
                'redirection' => 5,
                'httpversion' => '1.1',
                'blocking'    => true,
                'headers'     => array(
                    'Content-Type'                  => 'application/json',
                    'Authorization'                 => "Bearer " . $this->access_token,
                    "prefer"                        => "return=representation",
                    'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
                    'PayPal-Request-Id'             => $this->generate_request_id()
                ),
                'body'    => $patch_request_json,
                'cookies' => array()
            ));

            // Handle response errors or log response details
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->ppcp_log('Error updating PayPal order: ' . $error_message);
                return false;
            } else {
                $response_code    = wp_remote_retrieve_response_code($response);
                $response_message = wp_remote_retrieve_response_message($response);
                $api_response     = json_decode(wp_remote_retrieve_body($response), true);

                $this->ppcp_log('Response Code: ' . $response_code);
                $this->ppcp_log('Response Message: ' . $response_message);
                $this->ppcp_log('Response Body: ' . wc_print_r($this->ppcp_redact_sensitive_data($api_response), true));

                return ($response_code >= 200 && $response_code < 300);
            }
        } catch (Exception $ex) {
            $this->ppcp_log('Exception in ppcp_update_order_from_cart: ' . $ex->getMessage());
            return false;
        }
    }
}
