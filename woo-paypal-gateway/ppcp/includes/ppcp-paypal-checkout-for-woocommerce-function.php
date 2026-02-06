<?php

if (!function_exists('ppcp_remove_empty_key')) {

    function ppcp_remove_empty_key($data) {
        $original = $data;
        $data = array_filter($data);
        $data = array_map(function ($e) {
            return is_array($e) ? ppcp_remove_empty_key($e) : $e;
        }, $data);
        return $original === $data ? $data : ppcp_remove_empty_key($data);
    }

}

if (!function_exists('ppcp_set_session')) {

    function ppcp_set_session($key, $value) {
        if (!class_exists('WooCommerce') || WC()->session == null) {
            return false;
        }
        $ppcp_session = WC()->session->get('ppcp_session');
        if (!is_array($ppcp_session)) {
            $ppcp_session = array();
        }
        $ppcp_session[$key] = $value;
        WC()->session->set('ppcp_session', $ppcp_session);
    }

}

if (!function_exists('ppcp_get_session')) {

    function ppcp_get_session($key) {
        if (!class_exists('WooCommerce') || WC()->session == null) {
            return false;
        }

        $ppcp_session = WC()->session->get('ppcp_session');
        if (!empty($ppcp_session[$key])) {
            return $ppcp_session[$key];
        }
        return false;
    }

}
if (!function_exists('ppcp_unset_session')) {

    function ppcp_unset_session($key) {
        if (!class_exists('WooCommerce') || WC()->session == null) {
            return false;
        }
        $ppcp_session = WC()->session->get('ppcp_session');
        if (!empty($ppcp_session[$key])) {
            unset($ppcp_session[$key]);
            WC()->session->set('ppcp_session', $ppcp_session);
        }
    }

}
if (!function_exists('ppcp_has_active_session')) {

    function ppcp_has_active_session() {
        $checkout_details = ppcp_get_session('ppcp_paypal_transaction_details');
        $ppcp_paypal_order_id = ppcp_get_session('ppcp_paypal_order_id');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for query var, no state change.
        $has_paypal_order_id = isset($_GET['paypal_order_id']);
        if (!empty($checkout_details) && !empty($ppcp_paypal_order_id) && $has_paypal_order_id) {
            return true;
        }
        if ($has_paypal_order_id) {
            return true;
        }
        return false;
    }

}

if (!function_exists('get_button_locale_code')) {

    function get_button_locale_code() {
        $_supportedLocale = array(
            'en_US', 'fr_XC', 'es_XC', 'zh_XC', 'en_AU', 'de_DE', 'nl_NL',
            'fr_FR', 'pt_BR', 'fr_CA', 'zh_CN', 'ru_RU', 'en_GB', 'zh_HK',
            'he_IL', 'it_IT', 'ja_JP', 'pl_PL', 'pt_PT', 'es_ES', 'sv_SE', 'zh_TW', 'tr_TR'
        );
        $wpml_locale = ppcp_get_wpml_locale();
        if ($wpml_locale) {
            if (in_array($wpml_locale, $_supportedLocale)) {
                return $wpml_locale;
            }
        }
        $locale = get_locale();
        if (get_locale() != '') {
            $locale = substr(get_locale(), 0, 5);
        }
        if (!in_array($locale, $_supportedLocale)) {
            $locale = 'en_US';
        }
        return $locale;
    }

}
if (!function_exists('ppcp_get_wpml_locale')) {

    function ppcp_get_wpml_locale() {
        $locale = false;
        if (defined('ICL_LANGUAGE_CODE') && function_exists('icl_object_id')) {
            global $sitepress;
            if (isset($sitepress)) {
                $locale = $sitepress->get_current_language();
            } else if (function_exists('pll_current_language')) {
                $locale = pll_current_language('locale');
            } else if (function_exists('pll_default_language')) {
                $locale = pll_default_language('locale');
            }
        }
        return $locale;
    }

}
if (!function_exists('ppcp_is_local_server')) {

    function ppcp_is_local_server() {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return;
        }
        if ($_SERVER['HTTP_HOST'] === 'localhost' || substr($_SERVER['REMOTE_ADDR'], 0, 3) === '10.' || substr($_SERVER['REMOTE_ADDR'], 0, 7) === '192.168') {
            return true;
        }
        $live_sites = [
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
        ];
        foreach ($live_sites as $ip) {
            if (!empty($_SERVER[$ip])) {
                return false;
            }
        }
        if (in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
            return true;
        }
        $fragments = explode('.', site_url());
        if (in_array(end($fragments), array('dev', 'local', 'localhost', 'test'))) {
            return true;
        }
        return false;
    }

}
if (!function_exists('ppcp_readable')) {

    function ppcp_readable($tex) {
        $tex = ucwords(strtolower(str_replace('_', ' ', $tex)));
        return $tex;
    }

}
if (!function_exists('ppcp_is_advanced_cards_available')) {

    function ppcp_is_advanced_cards_available() {
        try {
            $currency = get_woocommerce_currency();
            $country_state = wc_get_base_location();
            $available = array(
                'US' => array('AUD', 'CAD', 'EUR', 'GBP', 'JPY', 'USD'),
                'AU' => array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD'),
                'GB' => array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD'),
                'FR' => array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD'),
                'IT' => array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD'),
                'ES' => array('AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'USD')
            );
            if (isset($available[$country_state['country']]) && in_array($currency, $available[$country_state['country']])) {
                return true;
            }
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

}

if (!function_exists('ppcp_get_raw_data')) {
    if (!function_exists('ppcp_get_raw_data')) {

        function ppcp_get_raw_data() {
            try {
                if (function_exists('phpversion') && version_compare(phpversion(), '5.6', '>=')) {
                    return file_get_contents('php://input');
                }
                global $HTTP_RAW_POST_DATA;
                if (!isset($HTTP_RAW_POST_DATA)) {
                    $HTTP_RAW_POST_DATA = file_get_contents('php://input');
                }
                return $HTTP_RAW_POST_DATA;
            } catch (Exception $ex) {
                
            }
        }

    }
}
if (!function_exists('ppcp_key_generator')) {
    if (!function_exists('ppcp_key_generator')) {

        function ppcp_key_generator() {
            $key = md5(microtime());
            $new_key = '';
            for ($i = 1; $i <= 19; $i++) {
                $new_key .= $key[$i];
                if ($i % 5 == 0 && $i != 19)
                    $new_key .= '';
            }
            return strtoupper($new_key);
        }

    }
}

if (!function_exists('is_wpg_using_woocommerce_blocks')) {

    function is_wpg_using_woocommerce_blocks() {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_page_id')) {
            return false;
        }
        $checkout_page_id = wc_get_page_id('checkout');
        if ($checkout_page_id > 0 && has_block('woocommerce/checkout', $checkout_page_id)) {
            return true;
        }
        return false;
    }

}

if (!function_exists('wpg_ppcp_pop_last_error')) {

    function wpg_ppcp_pop_last_error() {
        $key = wpg_ppcp_error_key();
        if (!$key) {
            return '';
        }
        $msg = (string) get_transient($key);
        if ($msg) {
            delete_transient($key);
        }
        return $msg;
    }

}

if (!function_exists('wpg_get_checkout_url')) {

    function wpg_get_checkout_url() {
        $checkout_url = wc_get_page_permalink('checkout');
        if ($checkout_url) {
            // Force SSL if needed.
            if (is_ssl() || 'yes' === get_option('woocommerce_force_ssl_checkout')) {
                $checkout_url = str_replace('http:', 'https:', $checkout_url);
            }
        }

        return $checkout_url;
    }

}

if (!function_exists('wpg_ppcp_error_key')) {

    function wpg_ppcp_error_key() {
        $customer_id = (function_exists('WC') && WC()->session) ? WC()->session->get_customer_id() : '';
        if (!$customer_id) {
            $customer_id = (string) get_current_user_id();
        }
        if (!$customer_id) {
            $customer_id = wp_get_session_token();
        }
        if (!$customer_id) {
            return null;
        }
        return 'wpg_ppcp_err_' . md5($customer_id);
    }

}

if (!function_exists('wpg_send_error')) {

    function wpg_send_error($payload) {
        $message = isset($payload['message']) ? $payload['message'] : __('Something went wrong. Please try again.', 'woo-paypal-gateway');
        if (function_exists('is_wpg_using_woocommerce_blocks') && is_wpg_using_woocommerce_blocks()) {
            $customer_id = (function_exists('WC') && WC()->session) ? WC()->session->get_customer_id() : '';
            if (!$customer_id) {
                $customer_id = (string) get_current_user_id();
            }
            if (!$customer_id) {
                $customer_id = wp_get_session_token();
            }
            $key = 'wpg_ppcp_err_' . md5($customer_id);
            set_transient($key, $message, 30);
        } else {
            wc_add_notice($message, 'error');
        }
    }

}

if (!function_exists('ppcp_update_woo_order_status')) {

    function ppcp_update_woo_order_status($orderid, $payment_status, $pending_reason, $processor_response = null) {
        try {
            if (empty($pending_reason)) {
                $pending_reason = $payment_status;
            }

            $order = wc_get_order($orderid);
            if (!$order) {
                return false;
            }
            $pr = is_array($processor_response) ? $processor_response : (is_object($processor_response) ? (array) $processor_response : []);
            $response_code = isset($pr['response_code']) ? strtoupper((string) $pr['response_code']) : '';
            $avs_code = isset($pr['avs_code']) ? strtoupper((string) $pr['avs_code']) : '';
            $cvv_code = isset($pr['cvv_code']) ? strtoupper((string) $pr['cvv_code']) : '';
            $human_detail = '';
            if ($response_code) {
                $mapped = wpg_get_process_code_message($response_code);
                if ($mapped) {
                    $human_detail = $mapped;
                }
            }
            $avs_hint = '';
            if ($avs_code && in_array($avs_code, ['N', 'C', 'I', 'R', 'S', 'U', 'G'], true)) {
                $avs_hint = __('Billing address didn’t match the card (AVS).', 'woo-paypal-gateway');
            }
            $cvv_hint = '';
            if ($cvv_code && in_array($cvv_code, ['N', 'P', 'S', 'U'], true)) {
                $cvv_hint = __('Security code (CVV) didn’t match.', 'woo-paypal-gateway');
            }
            $pending_reason_text = '';
            if (in_array(strtoupper($payment_status), ['DECLINED', 'PENDING'], true)) {
                switch (strtoupper($pending_reason)) {
                    case 'BUYER_COMPLAINT':
                        $pending_reason_text = __('The payer opened a dispute with PayPal.', 'woo-paypal-gateway');
                        break;
                    case 'CHARGEBACK':
                        $pending_reason_text = __('The issuer reversed the charge (chargeback).', 'woo-paypal-gateway');
                        break;
                    case 'ECHECK':
                        $pending_reason_text = __('Payment by eCheck—awaiting clearance.', 'woo-paypal-gateway');
                        break;
                    case 'INTERNATIONAL_WITHDRAWAL':
                        $pending_reason_text = __('Action required in your PayPal account (international withdrawal).', 'woo-paypal-gateway');
                        break;
                    case 'PENDING_REVIEW':
                        $pending_reason_text = __('Payment pending manual review.', 'woo-paypal-gateway');
                        break;
                    case 'RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION':
                        $pending_reason_text = __('Payee receiving preference requires action (often currency related).', 'woo-paypal-gateway');
                        break;
                    case 'REFUNDED':
                        $pending_reason_text = __('Funds were refunded.', 'woo-paypal-gateway');
                        break;
                    case 'TRANSACTION_APPROVED_AWAITING_FUNDING':
                        $pending_reason_text = __('Approved—waiting for payer to send funds.', 'woo-paypal-gateway');
                        break;
                    case 'UNILATERAL':
                        $pending_reason_text = __('Payee does not have a PayPal account.', 'woo-paypal-gateway');
                        break;
                    case 'VERIFICATION_REQUIRED':
                        $pending_reason_text = __('Payee’s PayPal account requires verification.', 'woo-paypal-gateway');
                        break;
                    case 'OTHER':
                    case 'NONE':
                    default:
                        $pending_reason_text = ''; // suppress generic/noisy text
                        break;
                }
            }
            $reasons = array_values(array_filter(array_unique([$human_detail, $avs_hint, $cvv_hint])));
            if (!$reasons && $pending_reason_text) {
                $reasons[] = $pending_reason_text;
            }
            $final_detail = $reasons ? implode(' ', $reasons) : __('The transaction could not be completed.', 'woo-paypal-gateway');
            $success = true;
            switch (strtoupper($payment_status)) {
                case 'PENDING':
                    $order->update_status(
                            'on-hold',
                            sprintf(
                                    /* translators: 1: payment method title, 2: pending reason text */
                                    __('Payment via %1$s pending. %2$s', 'woo-paypal-gateway'),
                                    $order->get_payment_method_title(),
                                    $pending_reason_text ?: ''
                            )
                    );
                    $success = true;
                    break;
                case 'DECLINED':
                    $order->update_status(
                            'failed',
                            sprintf(
                                    /* translators: 1: payment method title, 2: decline reason text */
                                    __('Payment via %1$s declined. %2$s', 'woo-paypal-gateway'),
                                    $order->get_payment_method_title(),
                                    $final_detail
                            )
                    );
                    $payload = [
                        'message' => apply_filters(
                                'woocommerce_add_error',
                                sprintf(
                                        /* translators: %s: payment failure reason */
                                        __('Payment declined. %s', 'woo-paypal-gateway'),
                                        $final_detail
                                )
                        ),
                        'code' => $response_code ?: null,
                        'avs' => $avs_code ?: null,
                        'cvv' => $cvv_code ?: null,
                        'redirect' => null,
                    ];
                    if ($response_code || $avs_code || $cvv_code) {
                        $order->add_order_note(
                                sprintf(
                                        /* translators: 1: processor response code, 2: AVS result, 3: CVV result */
                                        __('Processor response: code=%1$s, AVS=%2$s, CVV=%3$s', 'woo-paypal-gateway'),
                                        $response_code ?: '-',
                                        $avs_code ?: '-',
                                        $cvv_code ?: '-'
                                )
                        );
                    }
                    wpg_send_error($payload);
                    return false;
                case 'FAILED':
                    $order->update_status(
                            'failed',
                            sprintf(
                                    /* translators: 1: payment method title, 2: failure reason */
                                    __('Payment via %1$s failed. %2$s', 'woo-paypal-gateway'),
                                    $order->get_payment_method_title(),
                                    $final_detail
                            )
                    );
                    $payload = [
                        'message' => apply_filters(
                                'woocommerce_add_error',
                                sprintf(
                                        /* translators: %s: payment failure reason */
                                        __('Payment failed. %s', 'woo-paypal-gateway'),
                                        $final_detail
                                )
                        ),
                        'code' => $response_code ?: null,
                        'avs' => $avs_code ?: null,
                        'cvv' => $cvv_code ?: null,
                        'redirect' => null,
                    ];
                    if ($response_code || $avs_code || $cvv_code) {
                        $order->add_order_note(
                                sprintf(
                                        /* translators: 1: processor response code, 2: AVS result, 3: CVV result */
                                        __('Processor response: code=%1$s, AVS=%2$s, CVV=%3$s', 'woo-paypal-gateway'),
                                        $response_code ?: '-',
                                        $avs_code ?: '-',
                                        $cvv_code ?: '-'
                                )
                        );
                    }
                    wpg_send_error($payload);
                    return false;

                case 'PARTIALLY_REFUNDED':
                    $order->update_status('on-hold');
                    $order->add_order_note(
                            sprintf(
                                    /* translators: 1: payment method title, 2: PayPal refund reason */
                                    __('Payment via %1$s partially refunded. PayPal reason: %2$s.', 'woo-paypal-gateway'),
                                    $order->get_payment_method_title(),
                                    $pending_reason
                            )
                    );
                    $success = true;
                    break;

                case 'REFUNDED':
                    $order->update_status('refunded');
                    $order->add_order_note(
                            sprintf(
                                    /* translators: 1: payment method title, 2: PayPal refund reason */
                                    __('Payment via %1$s refunded. PayPal reason: %2$s.', 'woo-paypal-gateway'),
                                    $order->get_payment_method_title(),
                                    $pending_reason
                            )
                    );

                    $success = true;
                    break;

                default:
                    $success = true;
                    break;
            }
            return $success;
        } catch (Exception $ex) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice(__('An error occurred while updating the order status.', 'woo-paypal-gateway'), 'error');
            }
            return false;
        }
    }

}

if (!function_exists('ppcp_round')) {

    function ppcp_round($price, $precision) {
        $round_price = round($price, $precision);
        return number_format($round_price, $precision, '.', '');
    }

}

if (!function_exists('ppcp_get_awaiting_payment_order_id')) {

    function ppcp_get_awaiting_payment_order_id() {
        try {
            $ppcp_woo_order_id = absint(ppcp_get_session('ppcp_woo_order_id'));
            if ($ppcp_woo_order_id > 0) {
                return $ppcp_woo_order_id;
            }
            $order_awaiting_payment = absint(WC()->session->get('order_awaiting_payment'));
            if ($order_awaiting_payment > 0) {
                return $order_awaiting_payment;
            }
            $store_api_draft_order = absint(WC()->session->get('store_api_draft_order', 0));
            if ($store_api_draft_order > 0) {
                return $store_api_draft_order;
            }
            return 0;
        } catch (Exception $ex) {
            return 0;
        }
    }

}

if (!function_exists('ppcp_is_valid_order')) {

    function ppcp_is_valid_order($order_id) {
        $order = $order_id ? wc_get_order($order_id) : null;
        if ($order) {
            return true;
        }
        return false;
    }

}

if (!function_exists('wpg_get_raw_data')) {

    function wpg_get_raw_data() {
        try {
            if (function_exists('phpversion') && version_compare(phpversion(), '5.6', '>=')) {
                return file_get_contents('php://input');
            }
            global $HTTP_RAW_POST_DATA;
            if (!isset($HTTP_RAW_POST_DATA)) {
                $HTTP_RAW_POST_DATA = file_get_contents('php://input');
            }
            return $HTTP_RAW_POST_DATA;
        } catch (Exception $ex) {
            
        }
    }

}

if (!function_exists('is_wpg_checkout_block_enabled')) {

    function is_wpg_checkout_block_enabled() {
        try {
            if (!class_exists('Automattic\WooCommerce\Blocks\Package')) {
                return false;
            }
            $features = \Automattic\WooCommerce\Blocks\Package::container()->get('feature-registry');
            return $features->is_registered('blockified-checkout') && $features->is_active('blockified-checkout');
        } catch (Exception $ex) {
            return false;
        }
    }

}

if (!function_exists('is_wpg_checkout_block_page')) {

    function is_wpg_checkout_block_page() {
        return is_cart() || is_checkout() || is_checkout_pay_page();
    }

}

if (!function_exists('is_wpg_change_payment_method')) {

    function is_wpg_change_payment_method() {
        return (isset($_GET['pay_for_order']) && (isset($_GET['change_payment_method']) || isset($_GET['change_gateway_flag'])));
    }

}

if (!function_exists('is_wpg_cart_contains_pre_order')) {

    function is_wpg_cart_contains_pre_order() {
        if (class_exists('WC_Pre_Orders_Cart')) {
            return WC_Pre_Orders_Cart::cart_contains_pre_order();
        } else {
            return false;
        }
    }

}

if (!function_exists('is_wpg_pre_order_activated')) {

    function is_wpg_pre_order_activated() {
        return class_exists('WC_Pre_Orders_Order');
    }

}

if (!function_exists('is_wpg_cart_contains_subscription')) {

    function is_wpg_cart_contains_subscription() {
        if (class_exists('WC_Subscriptions_Order') && class_exists('WC_Subscriptions_Cart')) {
            return WC_Subscriptions_Cart::cart_contains_subscription();
        }
        return false;
    }

}

if (!function_exists('is_wpg_subscription_activated')) {

    function is_wpg_subscription_activated() {
        return class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order');
    }

}

if (!function_exists('is_wpg_paypal_vault_required')) {

    function is_wpg_paypal_vault_required() {
        // Ensure no notices or errors by validating conditions and classes
        if (function_exists('is_cart') && (is_cart() || is_checkout() || is_shop())) {
            if (is_wpg_cart_contains_subscription()) {
                return true;
            }
            if (class_exists('WC_Subscriptions_Cart') && function_exists('wcs_cart_contains_renewal') && wcs_cart_contains_renewal()) {
                return true;
            }
            if (function_exists('is_wpg_change_payment_method') && is_wpg_change_payment_method()) {
                return true;
            }
        }

        if (function_exists('is_order_pay') && is_order_pay()) {
            $order = class_exists('Utils') ? Utils::get_order_from_query_vars() : null;
            if (function_exists('is_wpg_change_payment_method') && is_wpg_change_payment_method()) {
                return true;
            }
            if ($order && is_wpg_subscription_activated() && class_exists('WC_Subscriptions_Order') && function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
                return true;
            }
        }

        if (function_exists('is_product') && is_product()) {
            global $post; // Get the global post object to fetch product ID
            $product_id = $post->ID ?? null;

            if ($product_id) {
                $product = wc_get_product($product_id); // Explicitly fetch the product object
                if ($product && is_a($product, 'WC_Product')) {
                    if (is_wpg_cart_contains_subscription()) {
                        return true;
                    }
                    if (class_exists('WC_Subscriptions_Product') && WC_Subscriptions_Product::is_subscription($product)) {
                        return true;
                    }
                }
            }
        }
        if (is_wpg_cart_contains_subscription()) {
            return true;
        }
        if (class_exists('WC_Subscriptions_Cart') && function_exists('wcs_cart_contains_renewal') && wcs_cart_contains_renewal()) {
            return true;
        }
        if (function_exists('is_wpg_change_payment_method') && is_wpg_change_payment_method()) {
            return true;
        }
        if (isset($_POST['wc-wpg_paypal_checkout_cc-new-payment-method']) && wc_string_to_bool(wc_clean($_POST['wc-wpg_paypal_checkout_cc-new-payment-method']))) {
            return true;
        }
        return false;
    }

}


if (!function_exists('ppcp_get_token_id_by_token')) {

    function ppcp_get_token_id_by_token($token_id) {
        try {
            global $wpdb;
            $tokens = $wpdb->get_row(
                    $wpdb->prepare(
                            "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s",
                            $token_id
                    )
            );
            if (isset($tokens->token_id)) {
                return $tokens->token_id;
            }
            return '';
        } catch (Exception $ex) {
            
        }
    }

}


if (!function_exists('wpg_ppcp_get_order_total')) {

    function wpg_ppcp_get_order_total($order_id = null) {
        try {
            global $product;
            $total = 0;
            if (is_null($order_id)) {
                $order_id = absint(get_query_var('order-pay'));
            }
            if (is_product()) {

                if ($product->is_type('variable')) {
                    $variation_id = $product->get_id();
                    $is_default_variation = false;

                    $available_variations = $product->get_available_variations();

                    if (!empty($available_variations) && is_array($available_variations)) {

                        foreach ($available_variations as $variation_values) {

                            $attributes = !empty($variation_values['attributes']) ? $variation_values['attributes'] : '';

                            if (!empty($attributes) && is_array($attributes)) {

                                foreach ($attributes as $key => $attribute_value) {

                                    $attribute_name = str_replace('attribute_', '', $key);
                                    $default_value = $product->get_variation_default_attribute($attribute_name);
                                    if ($default_value == $attribute_value) {
                                        $is_default_variation = true;
                                    } else {
                                        $is_default_variation = false;
                                        break;
                                    }
                                }
                            }

                            if ($is_default_variation) {
                                $variation_id = !empty($variation_values['variation_id']) ? $variation_values['variation_id'] : 0;
                                break;
                            }
                        }
                    }

                    $variable_product = wc_get_product($variation_id);
                    $total = (is_a($product, \WC_Product::class)) ? wc_get_price_including_tax($variable_product) : 1;
                } else {
                    $total = (is_a($product, \WC_Product::class)) ? wc_get_price_including_tax($product) : 1;
                }
            } elseif (0 < $order_id) {
                $order = wc_get_order($order_id);
                if ($order === false) {
                    if (isset(WC()->cart) && 0 < WC()->cart->total) {
                        $total = (float) WC()->cart->total;
                    } else {
                        return 0;
                    }
                } else {
                    $total = (float) $order->get_total();
                }
            } elseif (isset(WC()->cart) && 0 < WC()->cart->total) {
                $total = (float) WC()->cart->total;
            }
            return $total;
        } catch (Exception $ex) {
            return 0;
        }
    }

}


if (!function_exists('ppcp_get_view_sub_order_url')) {

    function ppcp_get_view_sub_order_url($order_id) {
        $view_subscription_url = wc_get_endpoint_url('view-subscription', $order_id, wc_get_page_permalink('myaccount'));
        return apply_filters('wcs_get_view_subscription_url', $view_subscription_url, $order_id);
    }

}

if (!function_exists('ppcp_get_token_id_by_token')) {

    function ppcp_get_token_id_by_token($token_id) {
        try {
            global $wpdb;
            $tokens = $wpdb->get_row(
                    $wpdb->prepare(
                            "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = %s",
                            $token_id
                    )
            );
            if (isset($tokens->token_id)) {
                return $tokens->token_id;
            }
            return '';
        } catch (Exception $ex) {
            
        }
    }

}

if (!function_exists('wpg_ppcp_reorder_methods')) {

    function wpg_ppcp_reorder_methods(&$methods, $class1, $class2, $position) {
        $index1 = array_search($class1, $methods, true);
        $index2 = array_search($class2, $methods, true);
        if ($index1 === false || $index2 === false) {
            return $methods;
        }
        unset($methods[$index2]);
        $methods = array_values($methods);
        $newIndex1 = array_search($class1, $methods, true);
        if ($position === 'after') {
            array_splice($methods, $newIndex1 + 1, 0, [$class2]);
        } elseif ($position === 'before') {
            array_splice($methods, $newIndex1, 0, [$class2]);
        }
        return $methods;
    }

}



if (!function_exists('wpg_is_vaulting_enable')) {

    function wpg_is_vaulting_enable($result) {
        $product_vaulting_enabled = false;
        $global_capability_active = false;

        // Check if any subscribed product has the vaulting capability
        if (!empty($result['products']) && is_array($result['products'])) {
            foreach ($result['products'] as $product) {
                if (
                        isset($product['vetting_status'], $product['capabilities']) &&
                        $product['vetting_status'] === 'SUBSCRIBED' &&
                        in_array('PAYPAL_WALLET_VAULTING_ADVANCED', $product['capabilities'], true)
                ) {
                    $product_vaulting_enabled = true;
                    break;
                }
            }
        }

        // Check global capability
        if (!empty($result['capabilities']) && is_array($result['capabilities'])) {
            foreach ($result['capabilities'] as $capability) {
                if (
                        isset($capability['name'], $capability['status']) &&
                        $capability['name'] === 'PAYPAL_WALLET_VAULTING_ADVANCED' &&
                        $capability['status'] === 'ACTIVE'
                ) {
                    $global_capability_active = true;
                    break;
                }
            }
        }

        return $product_vaulting_enabled && $global_capability_active;
    }

}

if (!function_exists('wpg_is_apple_pay_approved')) {

    function wpg_is_apple_pay_approved($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products'])) {
            foreach ($result['products'] as $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status']) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('APPLE_PAY', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $key => $capabilities) {
                        if (isset($capabilities['name']) && 'APPLE_PAY' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

}
if (!function_exists('wpg_is_google_pay_approved')) {

    function wpg_is_google_pay_approved($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products'])) {
            foreach ($result['products'] as $key => $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status']) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('GOOGLE_PAY', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $capabilities) {
                        if (isset($capabilities['name']) && 'GOOGLE_PAY' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

}

if (!function_exists('wpg_is_acdc_approved')) {

    function wpg_is_acdc_approved($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products']) && !empty($result['products'])) {
            foreach ($result['products'] as $key => $product) {
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status']) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('CUSTOM_CARD_PROCESSING', $product['capabilities'])) {
                    foreach ($result['capabilities'] as $key => $capabilities) {
                        if (isset($capabilities['name']) && 'CUSTOM_CARD_PROCESSING' === $capabilities['name'] && 'ACTIVE' === $capabilities['status']) {
                            return true;
                        }
                    }
                }
            }
        }
    }

}


if (!function_exists('wpg_manage_apple_domain_file')) {

    function wpg_manage_apple_domain_file($isSandbox) {
        $fileDir = ABSPATH . '.well-known';
        if (!wp_mkdir_p($fileDir)) {
            return false;
        }
        $wellKnownFile = trailingslashit($fileDir) . 'apple-developer-merchantid-domain-association';
        if (file_exists($wellKnownFile)) {
            if (!wp_delete_file($wellKnownFile)) {
                return false;
            }
        }
        $sourceFile = WPG_PLUGIN_DIR . '/ppcp/apple-domain/';
        $sourceFile .= $isSandbox ? 'sandbox/apple-developer-merchantid-domain-association' : 'production/apple-developer-merchantid-domain-association';
        if (!file_exists($sourceFile)) {
            return false;
        }
        if (!copy($sourceFile, $wellKnownFile)) {
            return false;
        }
        return true;
    }

}

if (!function_exists('is_existing_classic_user')) {

    function is_existing_classic_user() {
        global $wpdb;
        $classic_payment_option_keys = array(
            'woocommerce_wpg_paypal_express_settings',
            'woocommerce_wpg_braintree_settings',
            'woocommerce_wpg_paypal_pro_settings',
            'woocommerce_wpg_paypal_rest_settings',
            'woocommerce_wpg_paypal_pro_payflow_settings',
            'woocommerce_wpg_paypal_advanced_settings',
        );
        $placeholders = implode(',', array_fill(0, count($classic_payment_option_keys), '%s'));
        $result = $wpdb->get_var(
                $wpdb->prepare(
                        "SELECT option_name
             FROM {$wpdb->options}
             WHERE option_name IN ($placeholders)
             LIMIT 1",
                        $classic_payment_option_keys
                )
        );
        return null !== $result;
    }

}

if (!function_exists('get_active_classic_gateways')) {

    function get_active_classic_gateways() {

        $classic_gateway_option_map = array(
            'woocommerce_wpg_paypal_express_settings'      => 'wpg_paypal_express',
            'woocommerce_wpg_braintree_settings'           => 'wpg_braintree',
            'woocommerce_wpg_paypal_pro_settings'          => 'wpg_paypal_pro',
            'woocommerce_wpg_paypal_rest_settings'         => 'wpg_paypal_rest',
            'woocommerce_wpg_paypal_pro_payflow_settings'  => 'wpg_paypal_pro_payflow',
            'woocommerce_wpg_paypal_advanced_settings'     => 'wpg_paypal_advanced',
        );

        $active = array();

        foreach ($classic_gateway_option_map as $option_name => $gateway_id) {

            $settings = get_option($option_name, array());
            if (!is_array($settings)) {
                continue;
            }

            $enabled = isset($settings['enabled']) ? strtolower((string) $settings['enabled']) : 'no';
            if ($enabled !== 'yes') {
                continue;
            }

            $title = !empty($settings['title']) ? (string) $settings['title'] : $gateway_id;

            if (function_exists('WC') && WC() && WC()->payment_gateways()) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                if (isset($gateways[$gateway_id]) && !empty($gateways[$gateway_id]->get_title())) {
                    $title = (string) $gateways[$gateway_id]->get_title();
                }
            }

            $active[$gateway_id] = array(
                'id'           => $gateway_id,
                'title'        => $title,
                'option_name'  => $option_name,
                'settings_url' => admin_url(
                    'admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id
                ),
            );
        }

        return $active;
    }
}

if (!function_exists('wpg_set_order_payment_method_title_from_paypal_response')) {

    function wpg_set_order_payment_method_title_from_paypal_response($order, $paypal_response) {
        if (!$order instanceof WC_Order || empty($paypal_response['payment_source'])) {
            return;
        }
        $gateway_id = $order->get_payment_method();
        if (!in_array($gateway_id, ['wpg_paypal_checkout_cc', 'wpg_paypal_checkout'], true)) {
            $order->set_payment_method('wpg_paypal_checkout'); // force default
        }
        $source = $paypal_response['payment_source'];
        if (isset($source['google_pay'])) {
            $title = 'Google Pay (PayPal)';
        } elseif (isset($source['apple_pay'])) {
            $title = 'Apple Pay (PayPal)';
        } elseif (isset($source['card'])) {
            $title = 'Credit/Debit Card (PayPal)';
        } elseif (isset($source['paypal'])) {
            $title = 'PayPal';
        } else {
            $title = 'PayPal';
        }
        $order->set_payment_method_title($title);
    }

}

if (!function_exists('get_payer_action_url_from_paypal_response')) {

    function get_payer_action_url_from_paypal_response($response) {
        if (empty($response['links']) || !is_array($response['links'])) {
            return false;
        }
        foreach ($response['links'] as $link) {
            if (isset($link['rel']) && $link['rel'] === 'payer-action' && !empty($link['href'])) {
                return $link['href'];
            }
        }
        return false;
    }

}

if (!function_exists('wpg_ppcp_get_payment_method_title')) {

    function wpg_ppcp_get_payment_method_title($payment_name = '') {
        $final_payment_method_name = '';
        $list_payment_method = array(
            'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
            'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
            'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
            'blik' => __('BLIK', 'woo-paypal-gateway'),
            'eps' => __('eps', 'woo-paypal-gateway'),
            'ideal' => __('iDEAL', 'woo-paypal-gateway'),
            'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
            'mybank' => __('MyBank', 'woo-paypal-gateway'),
            'p24' => __('Przelewy24', 'woo-paypal-gateway'),
            'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
            'venmo' => __('Venmo', 'woo-paypal-gateway'),
            'paylater' => __('PayPal Pay Later', 'woo-paypal-gateway'),
            'paypal' => __('PayPal Checkout', 'woo-paypal-gateway'),
            'apple_pay' => __('Apple Pay', 'woo-paypal-gateway'),
            'google_pay' => __('Google Pay', 'woo-paypal-gateway'),
        );
        if (!empty($payment_name)) {
            $final_payment_method_name = $list_payment_method[$payment_name] ?? $payment_name;
        }
        return apply_filters('wpg_ppcp_get_payment_method_title', $final_payment_method_name, $payment_name, $list_payment_method);
    }

}

if (!function_exists('is_admin_checkout_page_edit_screen')) {

    function is_admin_checkout_page_edit_screen() {
        // Ensure we're in wp-admin and editing a post.
        if (!is_admin() || !isset($_GET['post']) || !isset($_GET['action'])) {
            return false;
        }

        // Only check when editing a post
        if ($_GET['action'] !== 'edit') {
            return false;
        }

        // Get the WooCommerce Checkout Page ID
        $checkout_page_id = get_option('woocommerce_checkout_page_id');
        $current_post_id = absint($_GET['post']);

        return ($current_post_id === absint($checkout_page_id));
    }

}

if (!function_exists('wpg_clear_ppcp_session_and_cart')) {

    function wpg_clear_ppcp_session_and_cart() {
        if (function_exists('WC')) {
            $wc = WC();

            if (isset($wc->session) && isset($wc->session->ppcp_session)) {
                unset($wc->session->ppcp_session);
                WC()->session->__unset('ppcp_session');
            }

            if (isset($wc->cart)) {
                $wc->cart->empty_cart();
            }
        }
    }

}

if (!function_exists('wpg_get_process_code_message')) {

    function wpg_get_process_code_message($code) {
        $processor_response_codes = [
            '0000' => __('Approved.', 'woo-paypal-gateway'),
            '00N7' => __('The card security code (CVV) was incorrect.', 'woo-paypal-gateway'),
            '0100' => __('Your bank needs to approve this. Please contact your card issuer.', 'woo-paypal-gateway'),
            '0390' => __('We could not find this account. Please check your card details.', 'woo-paypal-gateway'),
            '0500' => __('The bank declined this transaction. Please try another payment method.', 'woo-paypal-gateway'),
            '0580' => __('This transaction is not authorized. Please contact your card issuer.', 'woo-paypal-gateway'),
            '0800' => __('There was a processing issue. The payment was reversed.', 'woo-paypal-gateway'),
            '0880' => __('A security check failed. Please try again.', 'woo-paypal-gateway'),
            '0890' => __('The PIN was not accepted.', 'woo-paypal-gateway'),
            '0960' => __('Temporary system issue. Please try again later.', 'woo-paypal-gateway'),
            '0R00' => __('Payment was canceled.', 'woo-paypal-gateway'),
            '1000' => __('Only part of the amount was approved.', 'woo-paypal-gateway'),
            '10BR' => __('The bank declined this transaction.', 'woo-paypal-gateway'),
            '1300' => __('Invalid data format.', 'woo-paypal-gateway'),
            '1310' => __('Invalid amount.', 'woo-paypal-gateway'),
            '1312' => __('Card issuer/acquirer not allowed for this transaction.', 'woo-paypal-gateway'),
            '1317' => __('Invalid capture date.', 'woo-paypal-gateway'),
            '1320' => __('Unsupported currency for this card.', 'woo-paypal-gateway'),
            '1330' => __('Invalid account number.', 'woo-paypal-gateway'),
            '1335' => __('This card is not allowed for recurring payments.', 'woo-paypal-gateway'),
            '1340' => __('Invalid terminal configuration.', 'woo-paypal-gateway'),
            '1350' => __('Invalid merchant configuration.', 'woo-paypal-gateway'),
            '1352' => __('The account is restricted or inactive.', 'woo-paypal-gateway'),
            '1360' => __('Invalid processing code.', 'woo-paypal-gateway'),
            '1370' => __('Invalid merchant category.', 'woo-paypal-gateway'),
            '1380' => __('Invalid card expiry date.', 'woo-paypal-gateway'),
            '1382' => __('Invalid card security code (CVV).', 'woo-paypal-gateway'),
            '1384' => __('Invalid transaction state.', 'woo-paypal-gateway'),
            '1390' => __('Invalid order.', 'woo-paypal-gateway'),
            '1393' => __('The transaction could not be completed.', 'woo-paypal-gateway'),
            '5100' => __('The bank declined this transaction.', 'woo-paypal-gateway'),
            '5110' => __('The card security code (CVV) did not match.', 'woo-paypal-gateway'),
            '5120' => __('Insufficient funds.', 'woo-paypal-gateway'),
            '5130' => __('Incorrect PIN.', 'woo-paypal-gateway'),
            '5135' => __('Too many incorrect PIN attempts.', 'woo-paypal-gateway'),
            '5140' => __('This card is closed.', 'woo-paypal-gateway'),
            '5150' => __('Card capture requested by the bank.', 'woo-paypal-gateway'),
            '5160' => __('User not authorized for this transaction.', 'woo-paypal-gateway'),
            '5170' => __('Address verification failed.', 'woo-paypal-gateway'),
            '5180' => __('Invalid or restricted card.', 'woo-paypal-gateway'),
            '5190' => __('Address verification soft failure.', 'woo-paypal-gateway'),
            '5200' => __('Duplicate transaction.', 'woo-paypal-gateway'),
            '5210' => __('Invalid transaction.', 'woo-paypal-gateway'),
            '5400' => __('The card is expired.', 'woo-paypal-gateway'),
            '5500' => __('Incorrect PIN.', 'woo-paypal-gateway'),
            '5650' => __('Strong customer authentication required.', 'woo-paypal-gateway'),
            '5700' => __('This type of transaction is not permitted.', 'woo-paypal-gateway'),
            '5710' => __('Too many attempts. Please try again later.', 'woo-paypal-gateway'),
            '5180' => __('Invalid or restricted card. Please use another card.', 'woo-paypal-gateway'),
            '5190' => __('Address verification soft failure. Please check your billing address.', 'woo-paypal-gateway'),
            '5200' => __('Duplicate transaction. Please try again.', 'woo-paypal-gateway'),
            '5210' => __('Invalid transaction. Please try again.', 'woo-paypal-gateway'),
            '5400' => __('Expired card. Please use another card.', 'woo-paypal-gateway'),
            '5500' => __('Incorrect PIN. Please re-enter your PIN.', 'woo-paypal-gateway'),
            '5650' => __('Strong customer authentication required. Please try again.', 'woo-paypal-gateway'),
            '5700' => __('Transaction not permitted. Please use another payment method.', 'woo-paypal-gateway'),
            '5710' => __('Transaction attempts exceed limit. Please try again later.', 'woo-paypal-gateway'),
            '5800' => __('Reversal rejected. Please contact support.', 'woo-paypal-gateway'),
            '5900' => __('Invalid issue. Please try again.', 'woo-paypal-gateway'),
            '5910' => __('Issuer not available. Please try again later.', 'woo-paypal-gateway'),
            '5920' => __('Issuer temporarily not available. Please try again shortly.', 'woo-paypal-gateway'),
            '5930' => __('Card not activated. Please activate your card and try again.', 'woo-paypal-gateway'),
            '5950' => __('Updated card issued. Please use your new card.', 'woo-paypal-gateway'),
            '6300' => __('Account not on file. Please check your card details.', 'woo-paypal-gateway'),
            '7600' => __('Approved but not captured. No action needed.', 'woo-paypal-gateway'),
            '7700' => __('3DS authentication error. Please try again.', 'woo-paypal-gateway'),
            '7710' => __('Authentication failed. Please try again.', 'woo-paypal-gateway'),
            '7800' => __('BIN error. Please try another card.', 'woo-paypal-gateway'),
            '7900' => __('PIN error. Please try again with the correct PIN.', 'woo-paypal-gateway'),
            '8000' => __('Processor system error. Please try again later.', 'woo-paypal-gateway'),
            '8010' => __('Host key error. Please try again.', 'woo-paypal-gateway'),
            '8020' => __('Configuration error. Please contact support.', 'woo-paypal-gateway'),
            '8030' => __('Unsupported operation. Please try another payment method.', 'woo-paypal-gateway'),
            '8100' => __('Communication error. Please try again later.', 'woo-paypal-gateway'),
            '8110' => __('Communication error. Please try again shortly.', 'woo-paypal-gateway'),
            '8220' => __('System unavailable. Please try again later.', 'woo-paypal-gateway'),
            '9100' => __('Transaction declined. Please retry.', 'woo-paypal-gateway'),
            '9500' => __('Suspected fraud. Please use another payment method.', 'woo-paypal-gateway'),
            '9510' => __('Security violation. Please contact your card issuer.', 'woo-paypal-gateway'),
            '9520' => __('Card reported lost or stolen. Please use another card.', 'woo-paypal-gateway'),
            '9530' => __('Hold - call center. Please contact your card issuer.', 'woo-paypal-gateway'),
            '9540' => __('Card refused. Please use another payment method.', 'woo-paypal-gateway'),
            '9600' => __('Unrecognized response code. Please try again later.', 'woo-paypal-gateway'),
            'PCNR' => __('Contingencies not resolved. Please try again.', 'woo-paypal-gateway'),
            'PCVV' => __('CVV verification failed. Please check your CVV.', 'woo-paypal-gateway'),
            'PP06' => __('Account closed. Please use another card.', 'woo-paypal-gateway'),
            'PPRN' => __('Reattempt not permitted. Please use another payment method.', 'woo-paypal-gateway'),
            'PPAD' => __('Billing address error. Please check your billing details.', 'woo-paypal-gateway'),
            'PPAB' => __('Account blocked by issuer. Please contact your card issuer.', 'woo-paypal-gateway'),
            'PPAE' => __('American Express card disabled. Please use another card.', 'woo-paypal-gateway'),
            'PPAG' => __('Adult gaming not supported. Please use another payment method.', 'woo-paypal-gateway'),
            'PPAI' => __('Amount incompatible. Please try a different amount.', 'woo-paypal-gateway'),
            'PPAR' => __('Authorization result error. Please try again.', 'woo-paypal-gateway'),
            'PPAU' => __('MCC code error. Please contact support.', 'woo-paypal-gateway'),
            'PPAV' => __('Address verification failed. Please check your billing address.', 'woo-paypal-gateway'),
            'PPAX' => __('Amount exceeded. Please try a smaller amount.', 'woo-paypal-gateway'),
            'PPBG' => __('Gaming transaction error. Please use another payment method.', 'woo-paypal-gateway'),
            'PPC2' => __('CVV verification failed. Please check your CVV.', 'woo-paypal-gateway'),
            'PPCE' => __('Registration incomplete. Please complete registration.', 'woo-paypal-gateway'),
            'PPCO' => __('Country error. Please try another payment method.', 'woo-paypal-gateway'),
            'PPCR' => __('Credit error. Please try another payment method.', 'woo-paypal-gateway'),
            'PPCT' => __('Card type not supported. Please use another card.', 'woo-paypal-gateway'),
            'PPCU' => __('Invalid currency. Please select a supported currency.', 'woo-paypal-gateway'),
            'PPD3' => __('3D Secure authentication error. Please try again.', 'woo-paypal-gateway'),
            'PPDC' => __('Dynamic currency conversion not supported. Please try again.', 'woo-paypal-gateway'),
            'PPDI' => __('Diners Club card rejected. Please use another card.', 'woo-paypal-gateway'),
            'PPDV' => __('Authentication message error. Please try again.', 'woo-paypal-gateway'),
            'PPDT' => __('Decline threshold exceeded. Please try again later.', 'woo-paypal-gateway'),
            'PPEF' => __('Funding instrument expired. Please use another payment method.', 'woo-paypal-gateway'),
            'PPEL' => __('Frequency limit exceeded. Please try again later.', 'woo-paypal-gateway'),
            'PPER' => __('System error. Please try again later.', 'woo-paypal-gateway'),
            'PPEX' => __('Expiry date error. Please check your card details.', 'woo-paypal-gateway'),
            'PPFE' => __('Funding source already exists. Please use another payment method.', 'woo-paypal-gateway'),
            'PPFI' => __('Invalid funding instrument. Please try another payment method.', 'woo-paypal-gateway'),
            'PPFR' => __('Restricted funding instrument. Please try another payment method.', 'woo-paypal-gateway'),
            'PPFV' => __('Field validation failed. Please check your payment details.', 'woo-paypal-gateway'),
            'PPGR' => __('Gaming refund error. Please contact support.', 'woo-paypal-gateway'),
            'PPH1' => __('Processing error. Please try again.', 'woo-paypal-gateway'),
            'PPIF' => __('Idempotency failure. Please try again.', 'woo-paypal-gateway'),
            'PPII' => __('Invalid input. Please check your payment details.', 'woo-paypal-gateway'),
            'PPIM' => __('ID mismatch. Please try again.', 'woo-paypal-gateway'),
            'PPIT' => __('Invalid trace ID. Please try again.', 'woo-paypal-gateway'),
            'PPLR' => __('Late reversal. Please contact support.', 'woo-paypal-gateway'),
            'PPLS' => __('Status code error. Please try again.', 'woo-paypal-gateway'),
            'PPMB' => __('Missing business rule or data. Please try again.', 'woo-paypal-gateway'),
            'PPMC' => __('Mastercard blocked. Please use another card.', 'woo-paypal-gateway'),
            'PPMD' => __('Processing error. Please try again.', 'woo-paypal-gateway'),
            'PPNC' => __('Not supported. Please try another payment method.', 'woo-paypal-gateway'),
            'PPNL' => __('Network frequency limit exceeded. Please try again later.', 'woo-paypal-gateway'),
            'PPNM' => __('Merchant ID not found. Please contact support.', 'woo-paypal-gateway'),
            'PPNT' => __('Network error. Please try again later.', 'woo-paypal-gateway'),
            'PPPH' => __('Phone required for this transaction. Please update your details.', 'woo-paypal-gateway'),
            'PPPI' => __('Invalid product. Please try another product.', 'woo-paypal-gateway'),
            'PPPM' => __('Invalid payment method. Please try another payment method.', 'woo-paypal-gateway'),
            'PPQC' => __('Quasi-cash not supported. Please use another payment method.', 'woo-paypal-gateway'),
            'PPRE' => __('Refund not supported on pending transactions. Please try again later.', 'woo-paypal-gateway'),
            'PPRF' => __('Invalid parent transaction status. Please try again.', 'woo-paypal-gateway'),
            'PPRR' => __('Merchant not registered. Please contact support.', 'woo-paypal-gateway'),
            'PPS0' => __('Bank authorization mismatch. Please try again.', 'woo-paypal-gateway'),
            'PPS1' => __('Bank authorization already settled. Please try again.', 'woo-paypal-gateway'),
            'PPS2' => __('Bank authorization voided. Please try again.', 'woo-paypal-gateway'),
            'PPS3' => __('Bank authorization expired. Please try again.', 'woo-paypal-gateway'),
            'PPS4' => __('Currency mismatch. Please try again with the correct currency.', 'woo-paypal-gateway'),
            'PPS5' => __('Credit card mismatch. Please check your card details.', 'woo-paypal-gateway'),
            'PPS6' => __('Amount mismatch. Please try again with the correct amount.', 'woo-paypal-gateway'),
            'PPSC' => __('Score error. Please try again.', 'woo-paypal-gateway'),
            'PPSD' => __('Status description error. Please try again.', 'woo-paypal-gateway'),
            'PPSE' => __('American Express declined. Please use another card.', 'woo-paypal-gateway'),
            'PPTE' => __('Verification token expired. Please try again.', 'woo-paypal-gateway'),
            'PPTF' => __('Invalid trace reference. Please try again.', 'woo-paypal-gateway'),
            'PPTI' => __('Invalid transaction ID. Please try again.', 'woo-paypal-gateway'),
            'PPTR' => __('Verification token revoked. Please try again.', 'woo-paypal-gateway'),
            'PPTT' => __('Transaction type not supported. Please try another payment method.', 'woo-paypal-gateway'),
            'PPTV' => __('Invalid verification token. Please try again.', 'woo-paypal-gateway'),
            'PPUA' => __('User not authorized. Please contact support.', 'woo-paypal-gateway'),
            'PPUC' => __('Currency not supported. Please select a supported currency.', 'woo-paypal-gateway'),
            'PPUE' => __('Entity not supported. Please try another payment method.', 'woo-paypal-gateway'),
            'PPUI' => __('Installment not supported. Please try another payment method.', 'woo-paypal-gateway'),
            'PPUP' => __('POS flag not supported. Please try another payment method.', 'woo-paypal-gateway'),
            'PPUR' => __('Reversal not supported. Please contact support.', 'woo-paypal-gateway'),
            'PPVC' => __('Currency validation error. Please select a supported currency.', 'woo-paypal-gateway'),
            'PPVE' => __('Validation error. Please check your payment details.', 'woo-paypal-gateway'),
            'PPVT' => __('Virtual terminal not supported. Please try another payment method.', 'woo-paypal-gateway'),
        ];
        return $processor_response_codes[$code] ?? '';
    }

}