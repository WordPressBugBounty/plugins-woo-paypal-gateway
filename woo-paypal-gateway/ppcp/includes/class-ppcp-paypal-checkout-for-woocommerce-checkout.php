<?php

if (class_exists('WC_Checkout')) {



    class PPCP_Paypal_Checkout_For_Woocommerce_Checkout extends WC_Checkout {

        protected static $_instance = null;

        public static function instance() {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function process_checkout() {
            try {
                wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
                wc_set_time_limit(0);
                do_action('woocommerce_before_checkout_process');

                if (WC()->cart->is_empty()) {
                    // translators: %s: URL to the shop page.
                    throw new Exception(sprintf(
                                            __('Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'woo-paypal-gateway'),
                                            esc_url(wc_get_page_permalink('shop'))
                                    ));
                }

                do_action('woocommerce_checkout_process');

                if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')) {
                    require_once WPG_PLUGIN_DIR . '/ppcp/public/class-ppcp-paypal-checkout-for-woocommerce-button-manager.php';
                }

                $smart_button = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
                $posted_data = $smart_button->ppcp_prepare_order_data();

                $this->update_session($posted_data);
                $this->process_customer($posted_data);

                $draft_order_id = $this->wpg_get_store_api_draft_order_id();

                if ($draft_order_id) {
                    $order = wc_get_order($draft_order_id);

                    // Draft order missing/stale -> fallback to classic order creation
                    if ($order) {
                        $this->wpg_sync_order_from_cart($order);
                        $order->save();
                        $order_id = $draft_order_id;
                    } else {
                        $order_id = $this->create_order($posted_data);
                        if (is_wp_error($order_id)) {
                            throw new Exception($order_id->get_error_message());
                        }
                        $order = wc_get_order($order_id);
                    }
                } else {
                    $order_id = $this->create_order($posted_data);
                    if (is_wp_error($order_id)) {
                        throw new Exception($order_id->get_error_message());
                    }
                    $order = wc_get_order($order_id);
                }

                if (!$order) {
                    throw new Exception(__('Unable to create order.', 'woo-paypal-gateway'));
                }

                do_action('woocommerce_checkout_order_processed', $order_id, $posted_data, $order);

                if (apply_filters('woocommerce_cart_needs_payment', $order->needs_payment(), WC()->cart)) {
                    $this->process_order_payment($order_id, 'wpg_paypal_checkout');
                } else {
                    $this->process_order_without_payment($order_id);
                }
            } catch (Exception $e) {
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($e->getMessage(), 'error');
                }
            }
            $this->send_ajax_failure_response();
        }

        public function ppcp_create_order() {
            try {
                wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
                wc_set_time_limit(0);
                do_action('woocommerce_before_checkout_process');

                if (WC()->cart->is_empty()) {
                    // translators: %s: URL to the shop page.
                    throw new Exception(sprintf(
                                            __('Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'woo-paypal-gateway'),
                                            esc_url(wc_get_page_permalink('shop'))
                                    ));
                }

                do_action('woocommerce_checkout_process');

                $errors = new WP_Error();

                if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')) {
                    require_once WPG_PLUGIN_DIR . '/ppcp/public/class-ppcp-paypal-checkout-for-woocommerce-button-manager.php';
                }

                $smart_button = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
                $posted_data = $smart_button->ppcp_prepare_order_data();

                $this->update_session($posted_data);

                // Keep your existing behaviour: validate only if shipping is required.
                $needs_shipping = (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) ? WC()->cart->needs_shipping() : false;

                if ($needs_shipping) {
                    $this->validate_checkout($posted_data, $errors);
                    foreach ($errors->errors as $code => $messages) {
                        $data = $errors->get_error_data($code);
                        foreach ($messages as $message) {
                            wc_add_notice($message, 'error', $data);
                        }
                    }
                }

                if (0 === wc_notice_count('error')) {
                    $this->process_customer($posted_data);

                    $draft_order_id = $this->wpg_get_store_api_draft_order_id();

                    if ($draft_order_id) {
                        $order = wc_get_order($draft_order_id);

                        // Draft order missing/stale -> fallback to classic order creation
                        if ($order) {
                            $this->wpg_sync_order_from_cart($order);
                            $order->save();
                            $order_id = $draft_order_id;
                        } else {
                            $order_id = $this->create_order($posted_data);
                            if (is_wp_error($order_id)) {
                                throw new Exception($order_id->get_error_message());
                            }
                            $order = wc_get_order($order_id);
                        }
                    } else {
                        $order_id = $this->create_order($posted_data);
                        if (is_wp_error($order_id)) {
                            throw new Exception($order_id->get_error_message());
                        }
                        $order = wc_get_order($order_id);
                    }

                    if (!$order) {
                        throw new Exception(__('Unable to create order.', 'woo-paypal-gateway'));
                    }

                    return $order_id;
                }
            } catch (Exception $ex) {
                wc_add_notice($ex->getMessage(), 'error');
            }
        }

        public function wpg_get_store_api_draft_order_id() {
            if (!function_exists('WC') || !WC()->session) {
                return 0;
            }
            $order_id = absint(WC()->session->get('store_api_draft_order', 0));
            if (!$order_id) {
                return 0;
            }
            WC()->session->set('order_awaiting_payment', $order_id);
            return $order_id;
        }

        protected function wpg_sync_order_from_cart($order) {
            try {
                if (class_exists('\Automattic\WooCommerce\StoreApi\Utilities\OrderController')) {
                    $controller = new \Automattic\WooCommerce\StoreApi\Utilities\OrderController();
                    $controller->update_order_from_cart($order, true);
                    return;
                }
                if (function_exists('WC') && WC()->checkout()) {
                    WC()->checkout()->set_data_from_cart($order);
                    $order->calculate_totals(true);
                }
            } catch (\Throwable $e) {
                
            }
        }
        
        public function wpg_ppcp_validate_checkout( $posted_data ) {
            $errors = new WP_Error();
            $this->validate_checkout( $posted_data, $errors );
            if ( $errors->has_errors() ) {
                foreach ( $errors->errors as $code => $messages ) {
                    $data = $errors->get_error_data( $code );
                    foreach ( $messages as $message ) {
                        wc_add_notice( $message, 'error', $data );
                    }
                }
            }
            return $errors;
        }
    }

}