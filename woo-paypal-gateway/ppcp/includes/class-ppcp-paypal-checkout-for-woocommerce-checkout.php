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

                $this->ppcp_load_button_manager();

                $smart_button = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
                $posted_data  = $smart_button->ppcp_prepare_order_data();

                $this->update_session($posted_data);
                $this->process_customer($posted_data);

                $order    = null;
                $order_id = null;

                $draft_order_id = $this->wpg_get_store_api_draft_order_id();

                if ($draft_order_id) {
                    $order = wc_get_order($draft_order_id);

                    if ($order) {
                        $this->wpg_sync_order_from_cart($order);
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

                $this->wpg_finalize_order_totals($order);

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

                $this->ppcp_load_button_manager();

                $smart_button = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
                $posted_data  = $smart_button->ppcp_prepare_order_data();

                $this->update_session($posted_data);

                $needs_shipping = (WC()->cart && !WC()->cart->is_empty()) ? WC()->cart->needs_shipping() : false;

                if ($needs_shipping) {
                    $errors = new WP_Error();
                    $this->validate_checkout($posted_data, $errors);
                    foreach ($errors->errors as $code => $messages) {
                        $data = $errors->get_error_data($code);
                        foreach ($messages as $message) {
                            wc_add_notice($message, 'error', $data);
                        }
                    }
                }

                if (0 !== wc_notice_count('error')) {
                    return null;
                }

                $this->process_customer($posted_data);

                $order    = null;
                $order_id = null;

                $draft_order_id = $this->wpg_get_store_api_draft_order_id();

                if ($draft_order_id) {
                    $order = wc_get_order($draft_order_id);

                    if ($order) {
                        $this->wpg_sync_order_from_cart($order);
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

                $this->wpg_finalize_order_totals($order);

                return $order_id;

            } catch (Exception $ex) {
                wc_add_notice($ex->getMessage(), 'error');
                return null;
            }
        }

        public function wpg_get_store_api_draft_order_id() {
            if (!WC()->session) {
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
            if (WC()->cart && WC()->cart->needs_shipping()) {
                WC()->shipping()->calculate_shipping(
                    WC()->cart->get_shipping_packages()
                );
            }

            try {
                if (class_exists('\Automattic\WooCommerce\StoreApi\Utilities\OrderController')) {
                    $controller = new \Automattic\WooCommerce\StoreApi\Utilities\OrderController();
                    $controller->update_order_from_cart($order, true);
                } elseif (WC()->checkout()) {
                    WC()->checkout()->set_data_from_cart($order);
                }
            } catch (\Throwable $e) {
                wc_get_logger()->error(
                    'wpg_sync_order_from_cart failed: ' . $e->getMessage(),
                    array('source' => 'woo-paypal-gateway')
                );
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

        protected function wpg_finalize_order_totals($order) {
            if (WC()->cart && WC()->cart->needs_shipping()) {
                WC()->shipping()->calculate_shipping(
                    WC()->cart->get_shipping_packages()
                );
            }

            if (WC()->cart && WC()->cart->needs_shipping() && empty($order->get_items('shipping'))) {
                $chosen_methods = WC()->session ? (array) WC()->session->get('chosen_shipping_methods') : array();
                $packages       = WC()->shipping()->get_packages();

                if (empty($chosen_methods) && !empty($packages)) {
                    foreach ($packages as $index => $package) {
                        if (!empty($package['rates'])) {
                            $chosen_methods[$index] = reset(array_keys($package['rates']));
                        }
                    }
                }

                $this->create_order_shipping_lines($order, $chosen_methods, $packages);
            }

            $order->calculate_totals(true);
            $order->save();
        }

        private function ppcp_load_button_manager() {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/public/class-ppcp-paypal-checkout-for-woocommerce-button-manager.php';
            }
        }
    }

}