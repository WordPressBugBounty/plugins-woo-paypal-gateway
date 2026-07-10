<?php

if (!defined('ABSPATH')) {
    exit;
}

class PPCP_Paypal_Checkout_For_Woocommerce_Subscriptions extends PPCP_Paypal_Checkout_For_Woocommerce_Gateway {

    public function __construct() {
        parent::__construct();
        if (class_exists('WC_Subscriptions_Order')) {
            if (!has_action('woocommerce_scheduled_subscription_payment_wpg_paypal_checkout', array($this, 'scheduled_subscription_payment'))) {
                add_action('woocommerce_scheduled_subscription_payment_wpg_paypal_checkout', array($this, 'scheduled_subscription_payment'), 10, 2);
            }
            add_filter('woocommerce_subscription_payment_meta', array($this, 'add_subscription_payment_meta'), 10, 2);
            add_filter('woocommerce_subscription_validate_payment_meta', array($this, 'validate_subscription_payment_meta'), 10, 3);
            add_action('wcs_resubscribe_order_created', array($this, 'delete_resubscribe_meta'), 10);
            add_action('woocommerce_subscription_failing_payment_method_updated_wpg_paypal_checkout', array($this, 'update_failing_payment_method'), 10, 2);
            add_action('woocommerce_subscription_status_cancelled', array($this, 'handle_subscription_cancelled') );
        }
    }

    public function is_subscription($order_id) {
        return ( function_exists('wcs_order_contains_subscription') && ( wcs_order_contains_subscription($order_id) || wcs_is_subscription($order_id) || wcs_order_contains_renewal($order_id) ) );
    }

    public function process_payment($order_id) {
        if ($this->is_subscription($order_id)) {
            if (is_wpg_change_payment_method()) {
                return parent::subscription_change_payment($order_id);
            } elseif ($this->free_signup_with_token_payment_tokenization($order_id) == true) {
                return parent::free_signup_order_payment($order_id);
            } else {
                return parent::process_payment($order_id);
            }
        } else {
            return parent::process_payment($order_id);
        }
    }

    public function wpg_is_free_signup_with_free_trial() {
        if (wpg_ppcp_get_order_total() === 0) {
            return true;
        }
        return false;
    }

    public function scheduled_subscription_payment($amount_to_charge, $renewal_order) {
        if ($renewal_order->get_meta('_subscription_payment_processed') === 'yes') {
            return;
        }

        $lock_key = 'wpg_renewal_lock_' . $renewal_order->get_id();
        $lock_acquired = $this->acquire_renewal_lock($lock_key);
        if (!$lock_acquired) {
            return;
        }

        $payment_tokens_id = $renewal_order->get_meta('_payment_tokens_id', true);
        if (empty($payment_tokens_id)) {
            $this->wpg_scheduled_subscription_payment_retry_compability($renewal_order);
            $payment_tokens_id = $renewal_order->get_meta('_payment_tokens_id', true);
        }

        if (empty($payment_tokens_id)) {
            $renewal_order->update_status('failed', __('Subscription renewal failed: no saved payment token found. The customer needs to update their payment method.', 'woo-paypal-gateway'));
            delete_transient($lock_key);
            return;
        }

        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($renewal_order->get_id())) {
            $subscriptions = wcs_get_subscriptions_for_order($renewal_order->get_id());
        } elseif (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($renewal_order->get_id())) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order->get_id());
        } else {
            $subscriptions = array();
        }
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                $parent_order = wc_get_order($subscription->get_parent_id());
                if ($parent_order) {
                    $wpg_used_payment_method = $parent_order->get_meta('_wpg_ppcp_used_payment_method');
                    if (!empty($wpg_used_payment_method)) {
                        $renewal_order->update_meta_data('_wpg_ppcp_used_payment_method', $wpg_used_payment_method);
                    }
                }
            }
        }
        $renewal_order->update_meta_data('_enviorment', ($this->sandbox) ? 'sandbox' : 'live');
        $renewal_order->save();

        try {
            parent::process_subscription_payment($renewal_order, $amount_to_charge);

            $renewal_order = wc_get_order($renewal_order->get_id());
            if ($renewal_order && in_array($renewal_order->get_status(), array('processing', 'completed', 'on-hold'), true)) {
                $renewal_order->update_meta_data('_subscription_payment_processed', 'yes');
                $renewal_order->save();
            }
        } catch (\Throwable $e) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error('Subscription renewal error: ' . $e->getMessage(), array('source' => 'wpg_paypal_checkout'));
            }
            $renewal_order = wc_get_order($renewal_order->get_id());
            if ($renewal_order && !in_array($renewal_order->get_status(), array('processing', 'completed', 'on-hold', 'failed'), true)) {
                $renewal_order->update_status('failed', sprintf(
                    /* translators: %s: error message */
                    __('Subscription renewal payment failed: %s', 'woo-paypal-gateway'),
                    $e->getMessage()
                ));
            }
        } finally {
            delete_transient($lock_key);
        }
    }

    public function add_subscription_payment_meta($payment_meta, $subscription) {
        $payment_meta[$this->id] = array(
            'post_meta' => array(
                '_payment_tokens_id' => array(
                    'value' => $subscription->get_meta('_payment_tokens_id', true),
                    'label' => 'Payment Tokens ID',
                )
            )
        );
        return $payment_meta;
    }

    public function validate_subscription_payment_meta($payment_method_id, $payment_meta, $subscription) {
        if ($this->id === $payment_method_id) {
            if (empty($payment_meta['post_meta']['_payment_tokens_id']['value'])) {
                $parent_id = $subscription->get_parent_id();
                if ($parent_id) {
                    $parent_order = wc_get_order($parent_id);
                    if ($parent_order) {
                        $payment_tokens_id = $parent_order->get_meta('_payment_tokens_id', true);
                        if (!empty($payment_tokens_id)) {
                            $subscription->update_meta_data('_payment_tokens_id', $payment_tokens_id);
                            $subscription->save();
                        } else {
                            throw new Exception('A "_payment_tokens_id" value is required.');
                        }
                    } else {
                        throw new Exception('A "_payment_tokens_id" value is required.');
                    }
                } else {
                    throw new Exception('A "_payment_tokens_id" value is required.');
                }
            }
        }
    }

    public function delete_resubscribe_meta($resubscribe_order) {
        $resubscribe_order->delete_meta_data('_payment_tokens_id');
        $resubscribe_order->save_meta_data();
    }

    public function update_failing_payment_method($subscription, $renewal_order) {
        $subscription->update_meta_data('_payment_tokens_id', $renewal_order->get_meta('_payment_tokens_id', true));
        $subscription->set_payment_method_title($renewal_order->get_payment_method_title());
        $subscription->save();
    }

    public function handle_subscription_cancelled($subscription) {
        if ($subscription->get_payment_method() !== $this->id) {
            return;
        }

        $subscription->add_order_note(__('Subscription cancelled. PayPal vault token retained for potential reactivation.', 'woo-paypal-gateway'));
    }

    public function free_signup_with_token_payment_tokenization($order_id) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $token_value = isset($_POST['wc-wpg_paypal_checkout-payment-token']) ? sanitize_text_field(wp_unslash($_POST['wc-wpg_paypal_checkout-payment-token'])) : '';
        if (!empty($token_value) && $token_value !== 'new') {
            $order = wc_get_order($order_id);
            if ($order && $order->get_total() == 0) {
                return true;
            }
        }
        return false;
    }

    private function acquire_renewal_lock($lock_key) {
        global $wpdb;
        $option_name = '_transient_' . $lock_key;
        $expiration_name = '_transient_timeout_' . $lock_key;
        $now = time();
        $expire = $now + 5 * MINUTE_IN_SECONDS;

        $existing = get_transient($lock_key);
        if ($existing !== false) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no'), (%s, %s, 'no')",
            $expiration_name, $expire, $option_name, $now
        ));

        return $result > 0;
    }

    public function wpg_scheduled_subscription_payment_retry_compability($renewal_order) {
        $payment_tokens_id = $renewal_order->get_meta('_payment_tokens_id', true);
        if (!empty($payment_tokens_id)) {
            return;
        }

        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($renewal_order->get_id())) {
            $subscriptions = wcs_get_subscriptions_for_order($renewal_order->get_id());
        } elseif (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($renewal_order->get_id())) {
            $subscriptions = wcs_get_subscriptions_for_renewal_order($renewal_order->get_id());
        } else {
            $subscriptions = array();
        }
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                $parent_order = wc_get_order($subscription->get_parent_id());
                if ($parent_order) {
                    $payment_tokens_id = $parent_order->get_meta('_payment_tokens_id', true);
                    if (!empty($payment_tokens_id)) {
                        $subscription->update_meta_data('_payment_tokens_id', $payment_tokens_id);
                        $subscription->save();
                        $renewal_order->update_meta_data('_payment_tokens_id', $payment_tokens_id);
                        $renewal_order->save();
                    }
                    $paypal_subscription_id = $parent_order->get_meta('_paypal_subscription_id', true);
                    if (!empty($paypal_subscription_id)) {
                        $subscription->update_meta_data('_paypal_subscription_id', $paypal_subscription_id);
                        $subscription->save();
                        $renewal_order->update_meta_data('_paypal_subscription_id', $paypal_subscription_id);
                        $renewal_order->save();
                    }
                }
            }
        }
    }
}
