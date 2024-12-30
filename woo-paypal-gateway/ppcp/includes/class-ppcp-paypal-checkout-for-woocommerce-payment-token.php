<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token {

    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function add_paypal_customer_id($customer_id, $is_sandbox) {
        if (is_user_logged_in() && !empty($customer_id) && !$this->does_paypal_customer_id_exist($is_sandbox)) {
            $meta_key = $this->generate_customer_id_meta_key($is_sandbox);
            update_user_meta(get_current_user_id(), $meta_key, sanitize_text_field($customer_id));
        }
    }

    public function get_paypal_customer_id($is_sandbox) {
        return is_user_logged_in() ? $this->get_paypal_customer_id_for_user(get_current_user_id(), $is_sandbox) : false;
    }

    public function get_paypal_customer_id_for_user($user_id, $is_sandbox) {
        if (!empty($user_id)) {
            $meta_key = $this->generate_customer_id_meta_key($is_sandbox);
            $customer_id = get_user_meta($user_id, $meta_key, true);
            return !empty($customer_id) ? sanitize_text_field($customer_id) : false;
        }
        return false;
    }

    public function does_paypal_customer_id_exist($is_sandbox) {
        return is_user_logged_in() && get_user_meta(get_current_user_id(), $this->generate_customer_id_meta_key($is_sandbox), true);
    }

    private function generate_customer_id_meta_key($is_sandbox) {
        return $is_sandbox ? 'sandbox_wpg_ppcp_paypal_customer_id' : 'wpg_ppcp_paypal_customer_id';
    }

    public function save_payment_token($order, $payment_token_id) {
        $order_id = $order->get_id();
        $wpg_ppcp_used_payment_method = $order->get_meta('_wpg_ppcp_used_payment_method', true);
        $subscriptions = $this->get_order_subscriptions($order_id);

        foreach ($subscriptions as $subscription) {
            $this->update_subscription_meta($subscription, $payment_token_id, $wpg_ppcp_used_payment_method);
        }

        if (empty($subscriptions)) {
            $order->update_meta_data('_payment_tokens_id', $payment_token_id);
        }

        $order->save();
    }

    private function get_order_subscriptions($order_id) {
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order_id)) {
            return wcs_get_subscriptions_for_order($order_id);
        } elseif (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order_id)) {
            return wcs_get_subscriptions_for_renewal_order($order_id);
        }
        return [];
    }

    private function update_subscription_meta($subscription, $payment_token_id, $wpg_ppcp_used_payment_method) {
        $subscription->update_meta_data('_payment_tokens_id', $payment_token_id);
        if (!empty($wpg_ppcp_used_payment_method)) {
            $subscription->update_meta_data('_wpg_ppcp_used_payment_method', $wpg_ppcp_used_payment_method);
        }
        $subscription->save();
    }

    public function ppcp_wc_save_payment_token($order_id, $api_response) {
        $payment_token = $this->extract_payment_token($api_response);
        if (!$payment_token) {
            return;
        }

        $order = wc_get_order($order_id);
        $this->save_payment_token($order, $payment_token);

        if (ppcp_get_token_id_by_token($payment_token) === '') {
            $this->process_payment_token($order, $api_response, $payment_token);
        }
    }

    private function extract_payment_token($api_response) {
        $sources = ['card', 'paypal', 'venmo', 'apple_pay'];
        foreach ($sources as $source) {
            if (!empty($api_response['payment_source'][$source]['attributes']['vault']['id'])) {
                return $api_response['payment_source'][$source]['attributes']['vault']['id'];
            }
        }
        return '';
    }

    private function process_payment_token($order, $api_response, $payment_token) {
        $user_id = $this->get_user_id_from_order($order);
        $source = $this->determine_payment_source($api_response);

        $token = new WC_Payment_Token_CC();
        $token->set_token($payment_token);
        $token->set_gateway_id($order->get_payment_method());
        $token->set_user_id($user_id);

        if ($source === 'paypal' || $source === 'venmo') {
            $this->process_token_for_paypal_or_venmo($token, $api_response, $source);
        } elseif ($source === 'card' || $source === 'apple_pay') {
            $this->process_token_for_card($token, $api_response, $source);
        }

        if ($token->validate()) {
            $token->save();
            update_metadata('payment_token', $token->get_id(), '_wpg_ppcp_used_payment_method', $source);
        } else {
            $order->add_order_note(__('Invalid or missing payment token fields.', 'paypal-for-woocommerce'));
        }
    }

    private function determine_payment_source($api_response) {
        foreach (['card', 'paypal', 'venmo', 'apple_pay'] as $source) {
            if (!empty($api_response['payment_source'][$source])) {
                return $source;
            }
        }
        return '';
    }

    private function process_token_for_paypal_or_venmo($token, $api_response, $source) {
        $email_or_payer_id = $api_response['payment_source'][$source]['email_address'] ?? $api_response['payment_source'][$source]['payer_id'] ?? ucfirst($source);
        $token->set_card_type($email_or_payer_id);
        $token->set_last4(substr($token->get_token(), -4));
        $token->set_expiry_month(date('m'));
        $token->set_expiry_year(date('Y', strtotime('+20 years')));
    }

    private function process_token_for_card($token, $api_response, $source) {
        $payment_card = $api_response['payment_source'][$source]['card'] ?? [];
        $token->set_card_type($payment_card['brand'] ?? '');
        $token->set_last4($payment_card['last_digits'] ?? '');

        if (!empty($payment_card['expiry'])) {
            [$exp_year, $exp_month] = array_map('trim', explode('-', $payment_card['expiry']));
            $token->set_expiry_month($exp_month);
            $token->set_expiry_year($exp_year);
        } else {
            $token->set_expiry_month(date('m'));
            $token->set_expiry_year(date('Y', strtotime('+5 years')));
        }
    }

    private function get_user_id_from_order($order) {
        return $order->get_user_id() ?: get_current_user_id();
    }
}
