<?php

defined('ABSPATH') || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_Seller_Onboarding {

    public $dcc_applies;
    public $on_board_host;
    public $sandbox;
    public $settings;
    public $host;
    public $partner_merchant_id;
    public $sandbox_partner_merchant_id;
    public $api_request;
    public $result;
    protected static $_instance = null;
    public $api_log;
    public $is_sandbox;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        try {
            $this->wpg_load_class();
            $this->sandbox_partner_merchant_id = WPG_SANDBOX_PARTNER_MERCHANT_ID;
            $this->partner_merchant_id = WPG_LIVE_PARTNER_MERCHANT_ID;
            $this->on_board_host = WPG_ONBOARDING_URL;

            add_action('wc_ajax_wpg_login_seller', array($this, 'wpg_login_seller'));
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function wpg_load_class() {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-dcc-validate.php';
            }
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-settings.php';
            }
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
            }
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Log')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-log.php';
            }
            $this->api_log = PPCP_Paypal_Checkout_For_Woocommerce_Log::instance();
            $this->settings = PPCP_Paypal_Checkout_For_Woocommerce_Settings::instance();
            $this->dcc_applies = PPCP_Paypal_Checkout_For_Woocommerce_DCC_Validate::instance();
            $this->api_request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function nonce() {
        return 'a1233wtergfsdt4365tzrshgfbaewa36AGa1233wtergfsdt4365tzrshgfbaewa36AG';
    }

    public function data() {
        $data = $this->default_data();
        return $data;
    }

    public function wpg_generate_signup_link($sandbox) {
        $this->api_request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
        $this->is_sandbox = ($sandbox === 'yes') ? true : false;
        $host_url = $this->on_board_host;
        $args = array(
            'method' => 'POST',
            'body' => $this->data(),
            'headers' => array(),
        );
        return $this->api_request->request($host_url, $args, 'generate_signup_link');
    }

    public function wpg_generate_signup_link_for_google_pay($sandbox) {
        $this->api_request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
        $this->is_sandbox = ($sandbox === 'yes') ? true : false;
        $host_url = $this->on_board_host;
        $args = array(
            'method' => 'POST',
            'body' => $this->google_pay_data(),
            'headers' => array(),
        );
        return $this->api_request->request($host_url, $args, 'generate_signup_link');
    }

    public function wpg_generate_signup_link_for_apple_pay($sandbox) {
        $this->api_request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
        $this->is_sandbox = ($sandbox === 'yes') ? true : false;
        $host_url = $this->on_board_host;
        $args = array(
            'method' => 'POST',
            'body' => $this->apple_pay_data(),
            'headers' => array(),
        );
        return $this->api_request->request($host_url, $args, 'generate_signup_link');
    }

    private function default_data() {
        $nonce = wp_create_nonce('wpg_onboarding_nonce');
        $current_user = wp_get_current_user();
        $user_email = is_email($current_user->user_email) ? $current_user->user_email : '';
        $return_url = add_query_arg(
                array(
                    'sandbox' => ($this->is_sandbox ? 'yes' : 'no'),
                    'wpg_onboarding_nonce' => $nonce,
                ),
                admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_api_settings')
        );
        return array(
            'email' => $user_email,
            'sandbox' => ($this->is_sandbox) ? 'yes' : 'no',
            'return_url' => $return_url,
            'return_url_description' => __('Return to your shop.', 'woo-paypal-gateway'),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
            ),
        );
    }

    private function google_pay_data() {
        $nonce = wp_create_nonce('wpg_onboarding_nonce');
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $return_url = add_query_arg(
                array(
                    'sandbox' => ($this->is_sandbox ? 'yes' : 'no'),
                    'wpg_onboarding_nonce' => $nonce,
                ),
                admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_api_settings')
        );

        return array(
            'email' => $user_email,
            'sandbox' => ($this->is_sandbox) ? 'yes' : 'no',
            'return_url' => $return_url,
            'return_url_description' => __('Return to your shop.', 'woo-paypal-gateway'),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
                'PAYMENT_METHODS'
            ),
            'capabilities' => array(
                'APPLE_PAY',
                'GOOGLE_PAY'
            )
        );
    }

    private function apple_pay_data() {
        $nonce = wp_create_nonce('wpg_onboarding_nonce');
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $return_url = add_query_arg(
                array(
                    'sandbox' => ($this->is_sandbox ? 'yes' : 'no'),
                    'wpg_onboarding_nonce' => $nonce,
                ),
                admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_api_settings')
        );
        return array(
            'email' => $user_email,
            'sandbox' => ($this->is_sandbox) ? 'yes' : 'no',
            'return_url' => $return_url,
            'return_url_description' => __('Return to your shop.', 'woo-paypal-gateway'),
            'products' => array(
                $this->dcc_applies->for_country_currency() ? 'PPCP' : 'EXPRESS_CHECKOUT',
                'PAYMENT_METHODS'
            ),
            'capabilities' => array(
                'APPLE_PAY',
                'GOOGLE_PAY'
            )
        );
    }

    public function wpg_login_seller() {
        try {
            $connection_time = get_option('wpg_connection_time');
            if ($connection_time == '') {
                $connection_time = time();
                update_option('wpg_connection_time', $connection_time);
            }
            $posted_raw = wpg_get_raw_data();
            if (empty($posted_raw)) {
                return false;
            }
            $data = json_decode($posted_raw, true);
            $this->wpg_get_credentials($data);
            set_transient('wpg_ppcp_display_success_popup', true, 5 * MINUTE_IN_SECONDS);
            exit();
        } catch (Exception $ex) {
            $this->log_error($ex);
        }
    }

    public function wpg_get_access_token($data) {
        try {
            if (empty($data['authCode'])) {
                return false;
            }
            $this->api_request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
            $authCode = $data['authCode'];
            $sharedId = $data['sharedId'];
            $url = trailingslashit($this->host) . 'v1/oauth2/token/';
            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($sharedId . ':'),
                ),
                'body' => array(
                    'grant_type' => 'authorization_code',
                    'code' => $authCode,
                    'code_verifier' => $this->nonce(),
                ),
            );
            $result = $this->api_request->request($url, $args, 'get_access_token');
            if (isset($result['access_token'])) {
                return $result['access_token'];
            }
        } catch (Exception $ex) {
            $this->log_error($ex);
            $transient_key = $this->is_sandbox ? 'wpg_sandbox_seller_onboarding_process_failed' : 'wpg_live_seller_onboarding_process_failed';
            set_transient($transient_key, 'yes', 29000);
            return false;
        }
    }

    public function wpg_get_seller_rest_api_credentials($token) {
        try {
            $partner_merchant_id = $this->is_sandbox ? $this->sandbox_partner_merchant_id : $this->partner_merchant_id;
            $this->api_request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
            $url = trailingslashit($this->host) . 'v1/customer/partners/' . $partner_merchant_id . '/merchant-integrations/credentials/';
            $args = array(
                'method' => 'GET',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ),
            );
            $result = $this->api_request->request($url, $args, 'get_credentials');
            if (!isset($result['client_id']) || !isset($result['client_secret'])) {
                return false;
            }
            return $result;
        } catch (Exception $ex) {
            $this->log_error($ex);
            return false;
        }
    }

    public function wpg_listen_for_merchant_id() {
        if (empty($_GET['merchantIdInPayPal'])) {
            return;
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }
        if (empty($_GET['wpg_onboarding_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['wpg_onboarding_nonce'])),'wpg_onboarding_nonce')) {
            wp_die('Invalid nonce', 403);
        }

        $admin_return_url = admin_url(
                'admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_api_settings'
        );

        if (!empty($_GET['section'])) {
            $admin_return_url = add_query_arg(
                    array('wpg_section' => sanitize_text_field(wp_unslash($_GET['section']))),
                    $admin_return_url
            );
        }

        try {
            delete_transient('wpg_ppcp_sandbox_onboarding_status');
            delete_transient('wpg_ppcp_live_onboarding_status');

            $is_email_confirmed = !empty($_GET['isEmailConfirmed']) ? sanitize_text_field(wp_unslash($_GET['isEmailConfirmed'])) : '';

            if ($is_email_confirmed === 'false') {
                set_transient('wpg_primary_email_not_confirmed', 'yes', 29000);
            }

            $merchant_id = sanitize_text_field(wp_unslash($_GET['merchantIdInPayPal']));
            $sandbox_raw = !empty($_GET['sandbox']) ? sanitize_text_field(wp_unslash($_GET['sandbox'])) : '';
            $is_sandbox = ($sandbox_raw === 'yes');

            $settings = get_option('woocommerce_wpg_paypal_checkout_settings', array());

            if ($is_sandbox) {
                $settings['sandbox_merchant_id'] = $merchant_id;
            } else {
                $settings['live_merchant_id'] = $merchant_id;
            }

            update_option('woocommerce_wpg_paypal_checkout_settings', $settings);

            if (ob_get_length()) {
                ob_end_clean();
            }

            wp_safe_redirect($admin_return_url, 302);
            exit;
        } catch (Exception $ex) {

            if (ob_get_length()) {
                ob_end_clean();
            }

            wp_safe_redirect($admin_return_url, 302);
            exit;
        }
    }

    public function wpg_get_credentials($data) {
        try {
            $this->is_sandbox = isset($data['env']) && 'sandbox' === $data['env'];
            $this->host = $this->is_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            $this->clear_transients_and_options();
            $token = $this->wpg_get_access_token($data);
            $credentials = $this->wpg_get_seller_rest_api_credentials($token);
            $new_settings = array(
                'sandbox' => $this->is_sandbox ? 'yes' : 'no',
                'enabled' => 'yes',
                'description' => __('Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account', 'woo-paypal-gateway'),
            );
            if (!empty($credentials['client_secret']) && !empty($credentials['client_id'])) {
                if ($this->is_sandbox) {
                    $new_settings['rest_secret_id_sandbox'] = $credentials['client_secret'];
                    $new_settings['rest_client_id_sandbox'] = $credentials['client_id'];
                    $new_settings['sandbox_merchant_id'] = $credentials['payer_id'];
                    $new_settings['ppcp_email_sandbox'] = '';
                } else {
                    $new_settings['rest_secret_id_live'] = $credentials['client_secret'];
                    $new_settings['rest_client_id_live'] = $credentials['client_id'];
                    $new_settings['live_merchant_id'] = $credentials['payer_id'];
                    $new_settings['ppcp_email_live'] = '';
                }
                delete_transient('wpg_ppcp_live_onboarding_status');
                delete_transient('wpg_ppcp_sandbox_onboarding_status');
                $this->update_gateway_settings($new_settings);
                $transient_key = $this->is_sandbox ? 'wpg_sandbox_seller_onboarding_process_done' : 'wpg_live_seller_onboarding_process_done';
                set_transient($transient_key, 'yes', 29000);
            }
        } catch (Exception $ex) {
            $this->log_error($ex);
        }
    }

    public function wpg_track_seller_onboarding_status($merchant_id, $sandbox) {
        if ($sandbox) {
            $partner_merchant_id = $this->sandbox_partner_merchant_id;
        } else {
            $partner_merchant_id = $this->partner_merchant_id;
        }
        try {
            $this->api_request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
            $this->api_request->is_sandbox = $sandbox;
            $access_token = $this->api_request->ppcp_get_access_token();
            if (empty($access_token)) {
                return;
            }
            $host = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
            $url = trailingslashit($host) .
                    'v1/customer/partners/' . $partner_merchant_id .
                    '/merchant-integrations/' . $merchant_id;
            $args = array(
                'method' => 'GET',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ),
            );
            $result = $this->api_request->request($url, $args, 'seller_onboarding_status');
            return $result;
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
            return false;
        }
    }

    public function is_valid_site_request() {
        if (!isset($_REQUEST['section']) || !in_array(sanitize_text_field(wp_unslash($_REQUEST['section'])), array('wpg_paypal_checkout'), true)) {
            return false;
        }
        if (!current_user_can('manage_options')) {
            return false;
        }
        return true;
    }

    public function update_gateway_settings($new_settings) {
        $option_key = 'woocommerce_wpg_paypal_checkout_settings';
        $existing_settings = get_option($option_key, array());
        $merged_settings = array_merge($existing_settings, $new_settings);
        update_option($option_key, $merged_settings, true);
        wp_cache_delete($option_key, 'options');
    }

    private function clear_transients_and_options() {
        delete_transient('ppcp_sandbox_access_token');
        delete_transient('ppcp_live_access_token');
        delete_transient('ppcp_sandbox_client_token');
        delete_transient('ppcp_live_client_token');
        delete_option('ppcp_sandbox_webhook_id');
        delete_option('ppcp_live_webhook_id');
    }

    private function log_error($ex) {
        $this->api_log->log('The exception was created on line: ' . $ex->getLine(), 'error');
        $this->api_log->log($ex->getMessage(), 'error');
    }
}
