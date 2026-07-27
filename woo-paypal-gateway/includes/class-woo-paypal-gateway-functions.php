<?php

if (!defined('ABSPATH')) {
    exit;
}

function woo_paypal_gateway_get_posted_card($payment_method) {
    // Card details are read during gateway payment processing; WooCommerce verifies the
    // checkout nonce before the gateway runs, so no separate nonce check is performed here.
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
    $card_number = isset($_POST[$payment_method . '-card-number']) ? wc_clean(wp_unslash($_POST[$payment_method . '-card-number'])) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
    $card_cvc = isset($_POST[$payment_method . '-card-cvc']) ? wc_clean(wp_unslash($_POST[$payment_method . '-card-cvc'])) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
    $card_expiry = isset($_POST[$payment_method . '-card-expiry']) ? wc_clean(wp_unslash($_POST[$payment_method . '-card-expiry'])) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    $card_number = str_replace(array(' ', '-'), '', $card_number);
    $card_expiry = array_map('trim', explode('/', $card_expiry));
    $card_exp_month = str_pad($card_expiry[0], 2, "0", STR_PAD_LEFT);
    $card_exp_year = isset($card_expiry[1]) ? $card_expiry[1] : '';
    if (strlen($card_exp_year) == 2) {
        $card_exp_year += 2000;
    }
    $first_four = substr($card_number, 0, 4);
    return (object) array(
                'number' => $card_number,
                'type' => woo_paypal_gateway_card_type_from_account_number($first_four),
                'cvc' => $card_cvc,
                'exp_month' => $card_exp_month,
                'exp_year' => $card_exp_year,
    );
}

function woo_paypal_gateway_is_credit_supported() {
    if (substr(get_option("woocommerce_default_country"), 0, 2) == 'US' || substr(get_option("woocommerce_default_country"), 0, 2) == 'GB') {
        return true;
    } else {
        return false;
    }
}

function woo_paypal_gateway_card_type_from_account_number($account_number) {
    $types = array(
        'visa' => '/^4/',
        'mastercard' => '/^5[1-5]/',
        'amex' => '/^3[47]/',
        'discover' => '/^(6011|65|64[4-9]|622)/',
        'diners' => '/^(36|38|30[0-5])/',
        'jcb' => '/^35/',
        'maestro' => '/^(5018|5020|5038|6304|6759|676[1-3])/',
        'laser' => '/^(6706|6771|6709)/',
    );
    foreach ($types as $type => $pattern) {
        if (1 === preg_match($pattern, $account_number)) {
            return $type;
        }
    }
    return null;
}

function woo_paypal_gateway_round($price, $order) {
    $precision = 2;
    if (!woo_paypal_gateway_currency_has_decimals($order->get_currency())) {
        $precision = 0;
    }
    return round($price, $precision);
}

function woo_paypal_gateway_currency_has_decimals($currency) {
    if (in_array($currency, array('HUF', 'JPY', 'TWD'))) {
        return false;
    }
    return true;
}

function woo_paypal_gateway_set_session($key, $value) {
    if (!class_exists('WooCommerce') || WC()->session == null) {
        return false;
    }
    $wpg_session = WC()->session->get('wpg_session');
    if (!is_array($wpg_session)) {
        $wpg_session = array();
    }
    $wpg_session[$key] = $value;
    WC()->session->set('wpg_session', $wpg_session);
}

function woo_paypal_gateway_get_session($key) {
    if (!class_exists('WooCommerce') || WC()->session == null) {
        return false;
    }
    $wpg_session = WC()->session->get('wpg_session');
    if (!empty($wpg_session[$key])) {
        return $wpg_session[$key];
    }
    return false;
}

function woo_paypal_gateway_unset_session($key) {
    if (!class_exists('WooCommerce') || WC()->session == null) {
        return false;
    }
    $wpg_session = WC()->session->get('wpg_session');
    if (!empty($wpg_session[$key])) {
        unset($wpg_session[$key]);
        WC()->session->set('wpg_session', $wpg_session);
    }
}

function woo_paypal_gateway_is_express_checkout_ready_to_capture() {
    $TOKEN = woo_paypal_gateway_get_session('TOKEN');
    $PAYERID = woo_paypal_gateway_get_session('PAYERID');
    if (!empty($TOKEN) && !empty($PAYERID)) {
        return true;
    } else {
        return false;
    }
}

function woo_paypal_gateway_is_payment_method_saved() {
    if (is_user_logged_in()) {
        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'wpg_paypal_express');
        if (!empty($tokens)) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function woo_paypal_gateway_maybe_clear_session_data() {
    if (!class_exists('WooCommerce') || WC()->session == null) {
        return false;
    }
    WC()->session->set('wpg_session', '');
}

function woo_paypal_gateway_get_option($getway_name, $key, $default = false) {
    if (!empty($getway_name)) {
        $gateway_key = 'woocommerce_' . $getway_name . '_settings';
        $setting_value = get_option($gateway_key);
        if (!empty($setting_value)) {
            $value = !empty($setting_value[$key]) ? $setting_value[$key] : $default;
            return $value;
        }
    }
    return false;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Treated as a third-party integration surface (WooCommerce Subscriptions / Pre-Orders); renaming would break callers that rely on this name.
function is_cart_contains_pre_order() {
    if (class_exists('WC_Pre_Orders_Cart')) {
        if (WC_Pre_Orders_Cart::cart_contains_pre_order()) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Treated as a third-party integration surface (WooCommerce Subscriptions / Pre-Orders); renaming would break callers that rely on this name.
function is_pre_order_activated() {
    if (class_exists('WC_Pre_Orders_Order')) {
        return true;
    } else {
        return false;
    }
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Treated as a third-party integration surface (WooCommerce Subscriptions / Pre-Orders); renaming would break callers that rely on this name.
function is_cart_contains_subscription() {
    $cart_contains_subscription = false;
    if (class_exists('WC_Subscriptions_Order') && class_exists('WC_Subscriptions_Cart')) {
        $cart_contains_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
    }
    return $cart_contains_subscription;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Treated as a third-party integration surface (WooCommerce Subscriptions / Pre-Orders); renaming would break callers that rely on this name.
function is_subscription_activated() {
    if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
        return true;
    } else {
        return false;
    }
}

function woo_paypal_gateway_is_token_exist($gateway_id, $user_id, $token) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence check for a WooCommerce payment token by gateway/user/token; values bound via prepare() and there is no core API for this lookup.
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE gateway_id = %s AND user_id = %s AND token = %s", $gateway_id, $user_id, $token));
}

function woo_paypal_gateway_has_active_session() {
    if (!WC()->session) {
        return false;
    }
    $wpg_order_id = WC()->session->get('wpg_order_id');
    if (isset($wpg_order_id) && !empty($wpg_order_id)) {
        return true;
    }
}

function woo_paypal_gateway_clear_session_data() {
    if (!WC()->session) {
        return false;
    }
    WC()->session->set('wpg_order_details', null);
    WC()->session->set('wpg_order_id', null);
    unset(WC()->session->wpg_order_id);
    unset(WC()->session->wpg_order_details);
}

function woo_paypal_gateway_number_format($price) {
    $decimals = 2;

    if (!woo_paypal_gateway_currency_has_decimals(get_woocommerce_currency())) {
        $decimals = 0;
    }

    return number_format($price, $decimals, '.', '');
}

function woo_paypal_gateway_remove_empty_key($data) {
    $original = $data;
    $data = array_filter($data);
    $data = array_map(function ($e) {
        return is_array($e) ? woo_paypal_gateway_remove_empty_key($e) : $e;
    }, $data);
    return $original === $data ? $data : woo_paypal_gateway_remove_empty_key($data);
}

function woo_paypal_gateway_limit_length($string, $limit = 127) {
    $str_limit = $limit - 3;
    if (function_exists('mb_strimwidth')) {
        if (mb_strlen($string) > $limit) {
            $string = mb_strimwidth($string, 0, $str_limit) . '...';
        }
    } else {
        if (strlen($string) > $limit) {
            $string = substr($string, 0, $str_limit) . '...';
        }
    }
    return $string;
}

if (!function_exists('woo_paypal_gateway_evaluate_avs_cvv')) {

    /**
     * Screen a card processor's AVS and CVV2 result codes for fraud signals.
     *
     * Used by the legacy direct-card gateways (PayPal Pro, Payflow Pro, REST credit card),
     * which capture the card in a single request and have no 3D Secure liability shift.
     * Deliberately conservative so legitimate shoppers are not blocked:
     *
     *  - CVV2 'N' (no match) is a strong stolen-card signal — a genuine cardholder almost
     *    always has the correct security code — so it is flagged 'high'.
     *  - AVS 'N'/'C' (neither street nor ZIP matched) is a weaker signal because legitimate
     *    buyers mistype addresses and many non-US cards do not support AVS, so it is 'review'.
     *  - "unavailable / not processed / not supported / partial match" codes never flag.
     *
     * PayPal returns letter codes for Visa/Mastercard/Discover/Amex and numeric codes for
     * all other networks, where '0' means match and '1' means no match. Both formats are
     * screened here; every other numeric code ('2'-'4' = not processed / not supported /
     * partial) is treated as "no signal" and never flags, so legitimate shoppers on those
     * networks are not blocked.
     *
     * @param string $avs_code AVS result (letter Y, A, Z, N, C, U, G, ... or numeric 0-4).
     * @param string $cvv_code CVV2 match result (letter M, N, P, S, U, ... or numeric 0-4).
     * @return array{flag:bool, level:string, reason:string} level is '', 'review', or 'high'.
     */
    function woo_paypal_gateway_evaluate_avs_cvv($avs_code, $cvv_code) {
        $avs = strtoupper(trim((string) $avs_code));
        $cvv = strtoupper(trim((string) $cvv_code));

        if ('N' === $cvv || '1' === $cvv) {
            return array(
                'flag'   => true,
                'level'  => 'high',
                'reason' => __("Card security code (CVV2) did not match the issuing bank's records.", 'woo-paypal-gateway'),
            );
        }

        if (in_array($avs, array('N', 'C', '1'), true)) {
            return array(
                'flag'   => true,
                'level'  => 'review',
                'reason' => __('Billing address did not match the card (AVS no match).', 'woo-paypal-gateway'),
            );
        }

        return array('flag' => false, 'level' => '', 'reason' => '');
    }
}

if (!function_exists('woo_paypal_gateway_hold_order_for_avs_cvv')) {

    /**
     * Withhold fulfillment of an order that failed AVS/CVV screening.
     *
     * The legacy direct-card gateways charge the card in the same request, so we cannot
     * refuse the capture. Instead we record the payment but force the order to "on-hold"
     * with a prominent fraud note, so the merchant reviews (and refunds/cancels) before
     * shipping — which is exactly the "ship then chargeback" loss vector. This mirrors the
     * plugin's existing fraud-filter handling (Payflow RESULT 126 -> on-hold).
     *
     * @param WC_Order $order
     * @param array    $eval     Result of woo_paypal_gateway_evaluate_avs_cvv().
     * @param string   $avs_code Raw AVS code (for the order note/meta).
     * @param string   $cvv_code Raw CVV2 code (for the order note/meta).
     * @param string   $txn_id   Processor transaction id, if available.
     */
    function woo_paypal_gateway_hold_order_for_avs_cvv($order, $eval, $avs_code, $cvv_code, $txn_id = '') {
        if (!is_object($order) || empty($eval['flag'])) {
            return;
        }
        if (!empty($txn_id)) {
            $order->set_transaction_id($txn_id);
        }
        $order->update_meta_data('_wpg_avs_code', (string) $avs_code);
        $order->update_meta_data('_wpg_cvv2_match', (string) $cvv_code);
        $order->update_meta_data('_wpg_fraud_screen', $eval['level']);
        $order->save_meta_data();

        $prefix = ('high' === $eval['level'])
            ? __('HIGH fraud risk — do not ship. ', 'woo-paypal-gateway')
            : __('Fraud review needed — verify the buyer before shipping. ', 'woo-paypal-gateway');

        $order->update_status(
            'on-hold',
            $prefix . $eval['reason'] . ' ' . sprintf(
                /* translators: 1: AVS code, 2: CVV2 match code. */
                __('(AVS: %1$s, CVV2: %2$s). Refund or cancel this order if you cannot verify the cardholder.', 'woo-paypal-gateway'),
                '' !== (string) $avs_code ? $avs_code : '—',
                '' !== (string) $cvv_code ? $cvv_code : '—'
            )
        );
    }
}
