<?php
if (!defined('ABSPATH')) {
    exit;
}

class Woo_Paypal_Gateway_PayPal_Advanced_API_Handler {

    public $gateway;
    public $API_Endpoint;

    public function __construct($gateway) {
        $this->gateway = $gateway;
        $this->API_Endpoint = $this->gateway->testmode ? $this->gateway->testurl : $this->gateway->liveurl;
        $this->seller_protection = $this->gateway->get_option('seller_protection', 'disabled');
        if (!class_exists('Woo_Paypal_Gateway_Calculations')) {
            require_once( WPG_PLUGIN_DIR . '/includes/class-woo-paypal-gateway-calculations.php' );
        }
        $this->gateway_calculation = new Woo_Paypal_Gateway_Calculations($this->gateway);
    }

    public function get_token($order, $post_data, $force_new_token = false) {
        try {
            if (!$force_new_token && $order->get_meta('_SECURETOKENHASH') == md5(json_encode($post_data))) {
                return array(
                    'SECURETOKEN' => $order->get_meta('_SECURETOKEN'),
                    'SECURETOKENID' => $order->get_meta('_SECURETOKENID'),
                );
            }
            $post_data['SECURETOKENID'] = uniqid() . md5($order->get_order_key());
            $post_data['CREATESECURETOKEN'] = 'Y';
            $post_data['SILENTTRAN'] = 'TRUE';
            $post_data['ERRORURL'] = WC()->api_request_url('Woo_Paypal_Gateway_PayPal_Advanced');
            $post_data['RETURNURL'] = WC()->api_request_url('Woo_Paypal_Gateway_PayPal_Advanced');
            $post_data['URLMETHOD'] = 'POST';
            $response = wp_remote_post($this->gateway->testmode ? $this->gateway->testurl : $this->gateway->liveurl, array(
                'method' => 'POST',
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                'body' => urldecode(http_build_query(apply_filters('woo-paypal-gateway_payflow_request', $post_data, $order), null, '&')),
                'timeout' => 70,
                'user-agent' => 'WooCommerce',
                'httpversion' => '1.1'
            ));
            if (is_wp_error($response)) {
                wc_add_notice(__('There was a problem connecting to the payment gateway.', 'woo-paypal-gateway'));
                return false;
            }
            if (empty($response['body'])) {
                wc_add_notice(__('Empty Paypal response.', 'woo-paypal-gateway'));
                return false;
            }
            parse_str($response['body'], $parsed_response);
            if (isset($parsed_response['RESULT']) && in_array($parsed_response['RESULT'], array(160, 161, 162))) {
                return $this->get_token($order, $post_data, $force_new_token);
            } elseif (isset($parsed_response['RESULT']) && $parsed_response['RESULT'] == 0 && !empty($parsed_response['SECURETOKEN'])) {
                $order->update_meta_data('_SECURETOKEN', $parsed_response['SECURETOKEN']);
                $order->update_meta_data('_SECURETOKENID', $parsed_response['SECURETOKENID']);
                $order->update_meta_data('_SECURETOKENHASH', md5(json_encode($post_data)));
                $order->save_meta_data();
                return array(
                    'SECURETOKEN' => $parsed_response['SECURETOKEN'],
                    'SECURETOKENID' => $parsed_response['SECURETOKENID']
                );
            } else {
                $order->update_status('failed', __('PayPal Pro (Payflow) token generation failed: ', 'woo-paypal-gateway') . '(' . $parsed_response['RESULT'] . ') ' . '"' . $parsed_response['RESPMSG'] . '"');
                wc_add_notice(__('Payment error:', 'woo-paypal-gateway') . ' ' . $parsed_response['RESPMSG'], 'error');
                return false;
            }
        } catch (Exception $ex) {
            
        }
    }

    protected function _get_post_data($order) {
        try {
            $post_data = array();
            $post_data['USER'] = $this->gateway->paypal_user;
            $post_data['VENDOR'] = $this->gateway->paypal_vendor;
            $post_data['PARTNER'] = $this->gateway->paypal_partner;
            $post_data['PWD'] = $this->gateway->paypal_password;
            $post_data['TENDER'] = 'C';
            $post_data['TRXTYPE'] = $this->gateway->paymentaction;
            $post_data['AMT'] = $order->get_total();
            $post_data['CURRENCY'] = ( $order->get_currency() );
            $post_data['CUSTIP'] = $this->get_user_ip();
            $post_data['EMAIL'] = $order->get_billing_email();
            $post_data['INVNUM'] = $this->gateway->invoice_prefix . $order->get_order_number();
            $post_data['BUTTONSOURCE'] = 'mbjtechnolabs_SP';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            $post_data['CUSTOM'] = apply_filters('wpg_paypal_advanced_custom_parameter', json_encode(array('order_id' => $order->get_id(), 'order_key' => $order->get_order_key())), $order);
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
            $post_data['NOTIFYURL'] = apply_filters('wpg_paypal_advanced_notify_url', add_query_arg('wpg_ipn_action', 'ipn', WC()->api_request_url('Woo_Paypal_Gateway_IPN_Handler')));
            if ($this->gateway->soft_descriptor) {
                $post_data['MERCHDESCR'] = $this->gateway->soft_descriptor;
            }
            $item_loop = 0;
            if (sizeof($order->get_items()) > 0) {
                $ITEMAMT = 0;
                foreach ($order->get_items() as $item) {
                    $_product = $item->get_product();
                    if ($item['qty']) {
                        $post_data['L_NAME' . $item_loop] = $item['name'];
                        $post_data['L_COST' . $item_loop] = $order->get_item_total($item, true);
                        $post_data['L_QTY' . $item_loop] = $item['qty'];
                        if ($_product->get_sku()) {
                            $post_data['L_SKU' . $item_loop] = $_product->get_sku();
                        }
                        $ITEMAMT += $order->get_item_total($item, true) * $item['qty'];
                        $item_loop++;
                    }
                }
                if (( $order->get_total_shipping() + $order->get_shipping_tax() ) > 0) {
                    $post_data['L_NAME' . $item_loop] = 'Shipping';
                    $post_data['L_DESC' . $item_loop] = 'Shipping and shipping taxes';
                    $post_data['L_COST' . $item_loop] = $order->get_total_shipping() + $order->get_shipping_tax();
                    $post_data['L_QTY' . $item_loop] = 1;
                    $ITEMAMT += $order->get_total_shipping() + $order->get_shipping_tax();
                    $item_loop++;
                }
                if ($order->get_total_discount(false) > 0) {
                    $post_data['L_NAME' . $item_loop] = 'Order Discount';
                    $post_data['L_DESC' . $item_loop] = 'Discounts including tax';
                    $post_data['L_COST' . $item_loop] = '-' . $order->get_total_discount(false);
                    $post_data['L_QTY' . $item_loop] = 1;
                    $item_loop++;
                }
                $ITEMAMT = round($ITEMAMT, 2);
                if (absint($order->get_total() * 100) !== absint($ITEMAMT * 100)) {
                    $post_data['L_NAME' . $item_loop] = 'Rounding amendment';
                    $post_data['L_DESC' . $item_loop] = 'Correction if rounding is off (this can happen with tax inclusive prices)';
                    $post_data['L_COST' . $item_loop] = ( absint($order->get_total() * 100) - absint($ITEMAMT * 100) ) / 100;
                    $post_data['L_QTY' . $item_loop] = 1;
                }
                $post_data['ITEMAMT'] = $order->get_total();
            }
            $post_data['ORDERDESC'] = 'Order ' . $order->get_order_number() . ' on ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
            $post_data['FIRSTNAME'] = $order->get_billing_first_name();
            $post_data['LASTNAME'] = $order->get_billing_last_name();
            $post_data['STREET'] = $order->get_billing_address_1() . ' ' . $order->get_billing_address_2();
            $post_data['CITY'] = $order->get_billing_city();
            $post_data['STATE'] = $order->get_billing_state();
            $post_data['COUNTRY'] = $order->get_billing_country();
            $post_data['ZIP'] = $order->get_billing_postcode();
            if ($order->get_shipping_address_1()) {
                $post_data['SHIPTOFIRSTNAME'] = $order->get_shipping_first_name();
                $post_data['SHIPTOLASTNAME'] = $order->get_shipping_last_name();
                $post_data['SHIPTOSTREET'] = $order->get_shipping_address_1();
                $post_data['SHIPTOCITY'] = $order->get_shipping_city();
                $post_data['SHIPTOSTATE'] = $order->get_shipping_state();
                $post_data['SHIPTOCOUNTRY'] = $order->get_shipping_country();
                $post_data['SHIPTOZIP'] = $order->get_shipping_postcode();
            }
            return $post_data;
        } catch (Exception $ex) {
            
        }
    }

    public function get_transaction_details($transaction_id = 0) {
        try {
            $url = $this->gateway->testmode ? $this->gateway->testurl : $this->gateway->liveurl;
            $post_data = array();
            $post_data['USER'] = $this->gateway->paypal_user;
            $post_data['VENDOR'] = $this->gateway->paypal_vendor;
            $post_data['PARTNER'] = $this->gateway->paypal_partner;
            $post_data['PWD'] = $this->gateway->paypal_password;
            $post_data['TRXTYPE'] = 'I';
            $post_data['ORIGID'] = $transaction_id;
            // Request the full record so the return handler can reconcile the amount,
            // currency and invoice against the WooCommerce order before completing it.
            $post_data['VERBOSITY'] = 'HIGH';
            $post_data['BUTTONSOURCE'] = 'mbjtechnolabs_SP';
            $response = wp_remote_post($url, array(
                'method' => 'POST',
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                'body' => urldecode(http_build_query(apply_filters('woo-paypal-gateway_payflow_transaction_details_request', $post_data, null, '&'))),
                'timeout' => 70,
                'user-agent' => 'WooCommerce',
                'httpversion' => '1.1',
            ));
            if (is_wp_error($response)) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Error ' . wc_print_r($response->get_error_message(), true));

                throw new Exception(__('There was a problem connecting to the payment gateway.', 'woo-paypal-gateway'));
            }
            parse_str($response['body'], $parsed_response);
            Woo_Paypal_Gateway_PayPal_Advanced::log('transaction_details ' . wc_print_r($parsed_response, true));
            if (isset($parsed_response['RESULT']) && '0' === $parsed_response['RESULT']) {
                return $parsed_response;
            }
            return false;
        } catch (Exception $ex) {
            
        }
    }

    public function request_process_refund($order_id, $amount = null, $reason = '') {
        try {
            $order = wc_get_order($order_id);
            $url = $this->gateway->testmode ? $this->gateway->testurl : $this->gateway->liveurl;
            if (!$order || !$order->get_transaction_id() || !$this->gateway->paypal_user || !$this->gateway->paypal_vendor || !$this->gateway->paypal_password) {
                return false;
            }
            $details = $this->get_transaction_details($order->get_transaction_id());
            if ($details && strtolower($details['TRANSSTATE']) === '3') {
                $order->add_order_note(__('This order cannot be refunded due to an authorized only transaction.  Please use cancel instead.', 'woo-paypal-gateway'));
                Woo_Paypal_Gateway_PayPal_Advanced::log('Refund order # ' . $order_id . ': authorized only transactions need to use cancel/void instead.');
                throw new Exception(__('This order cannot be refunded due to an authorized only transaction.  Please use cancel instead.', 'woo-paypal-gateway'));
            }
            $post_data = array();
            $post_data['USER'] = $this->gateway->paypal_user;
            $post_data['VENDOR'] = $this->gateway->paypal_vendor;
            $post_data['PARTNER'] = $this->gateway->paypal_partner;
            $post_data['PWD'] = $this->gateway->paypal_password;
            $post_data['TRXTYPE'] = 'C';
            $post_data['ORIGID'] = $order->get_transaction_id();
            $post_data['BUTTONSOURCE'] = 'mbjtechnolabs_SP';
            if (!is_null($amount)) {
                $post_data['AMT'] = number_format($amount, 2, '.', '');
                $post_data['CURRENCY'] = $order->get_currency();
            }
            if ($reason) {
                if (255 < strlen($reason)) {
                    $reason = substr($reason, 0, 252) . '...';
                }
                $post_data['COMMENT1'] = html_entity_decode($reason, ENT_NOQUOTES, 'UTF-8');
            }
            $response = wp_remote_post($url, array(
                'method' => 'POST',
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.
                'body' => urldecode(http_build_query(apply_filters('woo-paypal-gateway_payflow_refund_request', $post_data, null, '&'))),
                'timeout' => 70,
                'user-agent' => 'WooCommerce',
                'httpversion' => '1.1',
            ));
            parse_str($response['body'], $parsed_response);
            if (is_wp_error($response)) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Error ' . wc_print_r($response->get_error_message(), true));
                throw new Exception(__('There was a problem connecting to the payment gateway.', 'woo-paypal-gateway'));
            }
            if (!isset($parsed_response['RESULT'])) {
                throw new Exception(__('Unexpected response from PayPal.', 'woo-paypal-gateway'));
            }
            if ('0' !== $parsed_response['RESULT']) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Parsed Response (refund) ' . wc_print_r($parsed_response, true));
            } else {
                // translators: 1: Refunded amount, 2: PayPal PNREF (transaction reference) ID.
                $order->add_order_note(sprintf(__('Refunded %1$s - PNREF: %2$s', 'woo-paypal-gateway'), wc_price(number_format($amount, 2, '.', '')), $parsed_response['PNREF']));
                return true;
            }
            return false;
        } catch (Exception $ex) {
            
        }
    }

    public function request_return_handler() {
        try {
            @ob_clean();
            header('HTTP/1.1 200 OK');
            // PayFlow silent-post / return endpoint. PayPal posts here server-side with no
            // WordPress nonce; the request is validated by the order-key check in
            // is_return_for_order() and by a server-side transaction inquiry below.
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            $result = isset($_POST['RESULT']) ? absint(wp_unslash($_POST['RESULT'])) : null;
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
            $INVOICE = isset($_POST['INVOICE']) ? wc_clean(wp_unslash($_POST['INVOICE'])) : '';
            $INVOICE = str_replace($this->gateway->invoice_prefix, '', $INVOICE);
            $order_id = isset($INVOICE) ? absint(ltrim($INVOICE, '#')) : 0;
            if (is_null($result) || empty($order_id)) {
                echo "Invalid request.";
                exit;
            }
            $order = wc_get_order($order_id);
            if (!$order || $order->get_payment_method() !== $this->gateway->id) {
                echo "Invalid request.";
                exit;
            }
            // The return endpoint is reachable unauthenticated and every field in $_POST is
            // attacker-controllable. Confirm the return really belongs to this order before
            // acting on it, so a sequential order id cannot be driven by a third party.
            if (!$this->is_return_for_order($order)) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Return handler: order key mismatch for order #' . $order_id);
                echo "Invalid request.";
                exit;
            }
            switch ($result) {
                case 0 :
                case 127 :
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                    $txn_id = (!empty($_POST['PNREF']) ) ? wc_clean(wp_unslash($_POST['PNREF'])) : '';
                    // Never trust the claimed-success POST on its own. Require a server-side
                    // PayFlow inquiry (authenticated with the merchant credentials) that both
                    // succeeds and reconciles against the order. A failed inquiry - including a
                    // forged or replayed reference - is a failure, not a completed payment.
                    $details = !empty($txn_id) ? $this->get_transaction_details($txn_id) : false;
                    if (!$details || !$this->is_transaction_valid_for_order($order, $details)) {
                        Woo_Paypal_Gateway_PayPal_Advanced::log('Return handler: unable to verify transaction "' . $txn_id . '" for order #' . $order_id);
                        $order->update_status('failed', __('PayPal Pro (Payflow) payment could not be verified.', 'woo-paypal-gateway'));
                        $redirect = $order->get_checkout_payment_url(true);
                        $redirect = add_query_arg('wc_error', urlencode(__('Your payment could not be verified. Please try again.', 'woo-paypal-gateway')), $redirect);
                        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
                            $redirect = str_replace('http:', 'https:', $redirect);
                        }
                        break;
                    }
                    if (strtolower($details['TRANSSTATE']) === '3') {
                        $order->update_meta_data('_paypalpro_charge_captured', 'no');
                        $order->save_meta_data();
                        $order->set_transaction_id($txn_id);
                        // translators: %s: PayPal PayFlow transaction ID.
                        $order->update_status('on-hold', sprintf(__('PayPal Pro (PayFlow) charge authorized (Charge ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woo-paypal-gateway'), $txn_id));
                        wc_reduce_stock_levels($order->get_id());
                    } else {
                        // translators: %s: PayPal Payflow transaction reference (PNREF).
                        $order->add_order_note(sprintf(__('PayPal Pro (Payflow) payment completed (PNREF: %s)', 'woo-paypal-gateway'), $txn_id));
                        $order->payment_complete($txn_id);
                    }
                    WC()->cart->empty_cart();
                    $redirect = $order->get_checkout_order_received_url();
                    break;
                case 126 :
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                    $order->add_order_note(isset($_POST['RESPMSG']) ? wc_clean(wp_unslash($_POST['RESPMSG'])) : '');
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean(), which WPCS does not recognise as a sanitizing function.
                    $order->add_order_note(isset($_POST['PREFPSMSG']) ? wc_clean(wp_unslash($_POST['PREFPSMSG'])) : '');
                    $order->update_status('on-hold', __('The payment was flagged by a fraud filter. Please check your PayPal Manager account to review and accept or deny the payment and then mark this order "processing" or "cancelled".', 'woo-paypal-gateway'));
                    WC()->cart->empty_cart();
                    $redirect = $order->get_checkout_order_received_url();
                    break;
                default :
                    $respmsg = isset($_POST['RESPMSG']) ? sanitize_text_field(wp_unslash($_POST['RESPMSG'])) : '';
                    $order->update_status('failed', $respmsg);
                    $redirect = $order->get_checkout_payment_url(true);
                    $redirect = add_query_arg('wc_error', urlencode($respmsg), $redirect);
                    if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
                        $redirect = str_replace('http:', 'https:', $redirect);
                    }
                    break;
            }
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            wp_safe_redirect($redirect);
            exit;
        } catch (Exception $ex) {

        }
    }

    /**
     * Confirm the PayFlow return actually belongs to the given order.
     *
     * The order key is embedded server-side in the CUSTOM field when the secure token is
     * created and is echoed back by PayFlow on return. Requiring it to match prevents an
     * unauthenticated third party from driving the return handler against an arbitrary
     * (sequential) order id. When PayFlow does not echo the field we fall back to the
     * verified inquiry + amount reconciliation performed by the caller.
     *
     * @param WC_Order $order
     * @return bool
     */
    protected function is_return_for_order($order) {
        try {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- PayFlow posts this return directly to the store; no nonce can survive the gateway round trip. Authenticity is established by matching the server-embedded order key below.
            $custom = isset($_POST['CUSTOM']) ? wc_clean(wp_unslash($_POST['CUSTOM'])) : '';
            if (empty($custom)) {
                return true;
            }
            $data = json_decode($custom, true);
            if (!is_array($data) || empty($data['order_key'])) {
                return true;
            }
            if (!empty($data['order_id']) && absint($data['order_id']) !== $order->get_id()) {
                return false;
            }
            return hash_equals((string) $order->get_order_key(), (string) $data['order_key']);
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Reconcile a verified PayFlow inquiry against the order before completing it.
     *
     * The inquiry (TRXTYPE=I, VERBOSITY=HIGH) is authenticated with the merchant
     * credentials, so its fields - unlike the return POST - cannot be forged by the client.
     * The transaction must be for this invoice and for the full order amount and currency;
     * any mismatch is treated as a failed payment.
     *
     * @param WC_Order $order
     * @param array    $details Parsed PayFlow inquiry response.
     * @return bool
     */
    protected function is_transaction_valid_for_order($order, $details) {
        try {
            $expected_invoice = $this->gateway->invoice_prefix . $order->get_order_number();
            if (!empty($details['INVNUM']) && (string) $details['INVNUM'] !== (string) $expected_invoice) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Return handler: invoice mismatch (' . $details['INVNUM'] . ' != ' . $expected_invoice . ')');
                return false;
            }
            if (!isset($details['AMT']) || '' === $details['AMT']) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Return handler: inquiry returned no amount to reconcile.');
                return false;
            }
            if (absint(round((float) $details['AMT'] * 100)) !== absint(round((float) $order->get_total() * 100))) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Return handler: amount mismatch (' . $details['AMT'] . ' != ' . $order->get_total() . ')');
                return false;
            }
            if (!empty($details['CURRENCY']) && strtoupper($details['CURRENCY']) !== strtoupper($order->get_currency())) {
                Woo_Paypal_Gateway_PayPal_Advanced::log('Return handler: currency mismatch (' . $details['CURRENCY'] . ' != ' . $order->get_currency() . ')');
                return false;
            }
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function request_receipt_page($order_id) {
        try {
            wp_enqueue_script('wc-credit-card-form');
            $order = new WC_Order($order_id);
            $url = $this->gateway->testmode ? 'https://pilot-payflowlink.paypal.com' : 'https://payflowlink.paypal.com';
            $post_data = $this->_get_post_data($order);
            $token = $this->get_token($order, $post_data);
            if (!$token) {
                wc_print_notices();
                return;
            }
            echo wp_kses_post(wpautop(__('Enter your payment details below and click "Confirm and pay" to securely pay for your order.', 'woo-paypal-gateway')));
            ?>
            <form method="POST" action="<?php echo esc_url($url); ?>">
                <div id="payment">
                    <label style="padding:10px 0 0 10px;display:block;">
                        <?php
                        echo esc_html($this->gateway->title) . ' ';
                        echo '<div style="vertical-align:middle;display:inline-block;margin:2px 0 0 .5em;">' . wp_kses_post($this->gateway->get_icon()) . '</div>';
                        ?>
                    </label>
                    <div class="payment_box">
                        <p>
                            <?php
                            echo esc_html($this->gateway->description);
                            if ($this->gateway->testmode) {
                                echo ' ' . esc_html__('TEST/SANDBOX MODE ENABLED. In test mode, you can use the card number 4111111111111111 with any CVC and a valid expiration date.', 'woo-paypal-gateway');
                            }
                            ?>
                        </p>
                        <fieldset id="paypal_pro_payflow-cc-form">
                            <p class="form-row form-row-wide">
                                <label for="paypal_pro_payflow-card-number">
                                    <?php esc_html_e('Card Number ', 'woo-paypal-gateway'); ?><span class="required">*</span>
                                </label>
                                <input type="text" id="paypal_pro_payflow-card-number" class="input-text wc-credit-card-form-card-number" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" name="CARDNUM" />
                            </p>
                            <p class="form-row form-row-first">
                                <label for="paypal_pro_payflow-card-expiry">
                                    <?php esc_html_e('Expiry (MM/YY) ', 'woo-paypal-gateway'); ?><span class="required">*</span>
                                </label>
                                <input type="text" id="paypal_pro_payflow-card-expiry" class="input-text wc-credit-card-form-card-expiry" autocomplete="off" placeholder="MM / YY" name="EXPDATE" />
                            </p>
                            <p class="form-row form-row-last">
                                <label for="paypal_pro_payflow-card-cvc">
                                    <?php esc_html_e('Card Code ', 'woo-paypal-gateway'); ?><span class="required">*</span>
                                </label>
                                <input type="text" id="paypal_pro_payflow-card-cvc" class="input-text wc-credit-card-form-card-cvc" autocomplete="off" placeholder="CVC" name="CVV2" />
                            </p>
                            <input type="hidden" name="SECURETOKEN" value="<?php echo esc_attr($token['SECURETOKEN']); ?>" />
                            <input type="hidden" name="SECURETOKENID" value="<?php echo esc_attr($token['SECURETOKENID']); ?>" />
                            <input type="hidden" name="SILENTTRAN" value="TRUE" />
                        </fieldset>
                    </div>
                    <input type="submit" value="<?php esc_attr_e('Confirm and pay', 'woo-paypal-gateway'); ?>" class="submit buy button" style="float:right;" />
                </div>
            </form>
            <?php
        } catch (Exception $ex) {
            
        }
    }

    public function get_user_ip() {
        try {
            return WC_Geolocation::get_ip_address();
        } catch (Exception $ex) {
            
        }
    }
}
