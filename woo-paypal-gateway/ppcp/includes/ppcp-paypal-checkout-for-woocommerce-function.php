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
        if (!empty($checkout_details) && !empty($ppcp_paypal_order_id) && isset($_GET['paypal_order_id'])) {
            return true;
        }
        if (isset($_GET['paypal_order_id'])) {
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
if (!function_exists('ppcp_update_woo_order_status')) {

    function ppcp_update_woo_order_status($orderid, $payment_status, $pending_reason) {
        try {
            if (empty($pending_reason)) {
                $pending_reason = $payment_status;
            }
            $order = wc_get_order($orderid);
            switch (strtoupper($payment_status)) :
                case 'DECLINED' :
                case 'PENDING' :
                    switch (strtoupper($pending_reason)) {
                        case 'BUYER_COMPLAINT':
                            $pending_reason_text = __('BUYER_COMPLAINT: The payer initiated a dispute for this captured payment with PayPal.', 'woo-paypal-gateway');
                            break;
                        case 'CHARGEBACK':
                            $pending_reason_text = __('CHARGEBACK: The captured funds were reversed in response to the payer disputing this captured payment with the issuer of the financial instrument used to pay for this captured payment.', 'woo-paypal-gateway');
                            break;
                        case 'ECHECK':
                            $pending_reason_text = __('ECHECK: The payer paid by an eCheck that has not yet cleared.', 'woo-paypal-gateway');
                            break;
                        case 'INTERNATIONAL_WITHDRAWAL':
                            $pending_reason_text = __('INTERNATIONAL_WITHDRAWAL: Visit your online account. In your **Account Overview**, accept and deny this payment.', 'woo-paypal-gateway');
                            break;
                        case 'OTHER':
                            $pending_reason_text = __('No additional specific reason can be provided. For more information about this captured payment, visit your account online or contact PayPal.', 'woo-paypal-gateway');
                            break;
                        case 'PENDING_REVIEW':
                            $pending_reason_text = __('PENDING_REVIEW: The captured payment is pending manual review.', 'woo-paypal-gateway');
                            break;
                        case 'RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION':
                            $pending_reason_text = __('RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION: The payee has not yet set up appropriate receiving preferences for their account. For more information about how to accept or deny this payment, visit your account online. This reason is typically offered in scenarios such as when the currency of the captured payment is different from the primary holding currency of the payee.', 'woo-paypal-gateway');
                            break;
                        case 'REFUNDED':
                            $pending_reason_text = __('REFUNDED: The captured funds were refunded.', 'woo-paypal-gateway');
                            break;
                        case 'TRANSACTION_APPROVED_AWAITING_FUNDING':
                            $pending_reason_text = __('TRANSACTION_APPROVED_AWAITING_FUNDING: The payer must send the funds for this captured payment. This code generally appears for manual EFTs.', 'woo-paypal-gateway');
                            break;
                        case 'UNILATERAL':
                            $pending_reason_text = __('UNILATERAL: The payee does not have a PayPal account.', 'woo-paypal-gateway');
                            break;
                        case 'VERIFICATION_REQUIRED':
                            $pending_reason_text = __('VERIFICATION_REQUIRED: The payee\'s PayPal account is not verified.', 'woo-paypal-gateway');
                            break;
                        case 'none':
                        default:
                            $pending_reason_text = __('No pending reason provided.', 'woo-paypal-gateway');
                            break;
                    }
                    if ($payment_status === 'PENDING') {
                        $order->update_status('on-hold', sprintf(__('Payment via %s Pending. PayPal Pending reason: %s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), $pending_reason_text));
                    }
                    if ($payment_status === 'DECLINED') {
                        $order->update_status('failed', sprintf(__('Payment via %s declined. PayPal declined reason: %s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), $pending_reason_text));
                    }
                    break;
                case 'PARTIALLY_REFUNDED' :
                    $order->update_status('on-hold');
                    $order->add_order_note(sprintf(__('Payment via %s partially refunded. PayPal reason: %s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), $pending_reason));
                case 'REFUNDED' :
                    $order->update_status('refunded');
                    $order->add_order_note(sprintf(__('Payment via %s refunded. PayPal reason: %s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), $pending_reason));
                case 'FAILED' :
                    $order->update_status('failed', sprintf(__('Payment via %s failed. PayPal reason: %s.', 'woo-paypal-gateway'), $order->get_payment_method_title(), $pending_reason));
                    break;
                default:
                    break;
            endswitch;
            return;
        } catch (Exception $ex) {
            
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
            $order_id = absint(WC()->session->get('order_awaiting_payment'));
            if (!$order_id) {
                $order_id = absint(wc()->session->get('store_api_draft_order', 0));
            }
            return $order_id;
        } catch (Exception $ex) {
            
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
        return ( isset($_GET['pay_for_order']) && ( isset($_GET['change_payment_method']) || isset($_GET['change_gateway_flag'])) );
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
                    $total = ( is_a($product, \WC_Product::class) ) ? wc_get_price_including_tax($variable_product) : 1;
                } else {
                    $total = ( is_a($product, \WC_Product::class) ) ? wc_get_price_including_tax($product) : 1;
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

if (!function_exists('wpg_ppcp_short_payment_method')) {

    function wpg_ppcp_short_payment_method(&$array, $keyX, $keyY, $position = 'before') {
        if (array_key_exists($keyX, $array) && array_key_exists($keyY, $array)) {
            $valueY = $array[$keyY];
            unset($array[$keyY]);

            $keys = array_keys($array);
            $indexX = array_search($keyX, $keys, true);

            if ($position === 'before') {
                $array = array_slice($array, 0, $indexX, true) +
                        array($keyY => $valueY) +
                        $array;
            } elseif ($position === 'after') {
                $array = array_slice($array, 0, $indexX + 1, true) +
                        array($keyY => $valueY) +
                        $array;
            }
        }
        return $array;
    }

}

if (!function_exists('wpg_is_vaulting_enable')) {

    function wpg_is_vaulting_enable($result) {
        if (isset($result['products']) && isset($result['capabilities']) && !empty($result['products']) && !empty($result['products'])) {
            foreach ($result['products'] as $product) {
                if ($product['name'] === 'ADVANCED_VAULTING' &&
                        isset($product['vetting_status']) && $product['vetting_status'] === 'SUBSCRIBED' &&
                        isset($product['capabilities']) && in_array('PAYPAL_WALLET_VAULTING_ADVANCED', $product['capabilities'])) {
                    return true;
                }
            }
        }
        return false;
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
                if (isset($product['vetting_status']) && ('SUBSCRIBED' === $product['vetting_status'] || 'APPROVED' === $product['vetting_status'] ) && isset($product['capabilities']) && is_array($product['capabilities']) && in_array('CUSTOM_CARD_PROCESSING', $product['capabilities'])) {
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
        // Define the .well-known directory
        $fileDir = ABSPATH . '.well-known';

        // Log the target directory path
        error_log('Target .well-known directory: ' . $fileDir);

        // Create the .well-known directory if it doesn't exist
        if (!wp_mkdir_p($fileDir)) {
            error_log('Failed to create .well-known directory at: ' . $fileDir);
            return false; // Stop execution if the directory couldn't be created
        }
        error_log('Successfully created or verified .well-known directory at: ' . $fileDir);

        // Path to the Apple domain association file in .well-known
        $wellKnownFile = trailingslashit($fileDir) . 'apple-developer-merchantid-domain-association';
        error_log('Target file path: ' . $wellKnownFile);

        // Remove the existing file if it exists
        if (file_exists($wellKnownFile)) {
            if (!unlink($wellKnownFile)) {
                error_log('Failed to remove existing file at: ' . $wellKnownFile);
                return false; // Stop execution if the file couldn't be deleted
            }
            error_log('Successfully removed existing file at: ' . $wellKnownFile);
        } else {
            error_log('No existing file found at: ' . $wellKnownFile);
        }

        // Determine the source file path based on sandbox or production
        $sourceFile = WPG_PLUGIN_DIR . '/ppcp/apple-domain/';
        $sourceFile .= $isSandbox ? 'sandbox/apple-developer-merchantid-domain-association' : 'production/apple-developer-merchantid-domain-association';
        error_log('Source file path: ' . $sourceFile);

        // Check if the source file exists
        if (!file_exists($sourceFile)) {
            error_log('Source file does not exist at: ' . $sourceFile);
            return false;
        }
        error_log('Source file found at: ' . $sourceFile);

        // Copy the correct file to .well-known
        if (!copy($sourceFile, $wellKnownFile)) {
            error_log('Failed to copy file from ' . $sourceFile . ' to ' . $wellKnownFile);
            return false;
        }
        error_log('Successfully copied file from ' . $sourceFile . ' to ' . $wellKnownFile);

        return true; // Successfully updated the file
    }
}



