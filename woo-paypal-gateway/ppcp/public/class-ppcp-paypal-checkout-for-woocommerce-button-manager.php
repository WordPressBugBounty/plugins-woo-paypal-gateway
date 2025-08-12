<?php

/**
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager/public
 * @author     easypayment
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager {

    private $plugin_name;
    private $version;
    public $request;
    public $woocommerce_wpg_paypal_checkout_settings;
    public $checkout_details;
    public $procceed = 1;
    public $reject = 2;
    public $retry = 3;
    public $title;
    public $enabled;
    public $sandbox;
    public $client_id;
    public $secret;
    public $webhook_id;
    public $client_token;
    public $ppcp_currency_list;
    public $ppcp_currency;
    public $show_on_product_page;
    public $show_on_cart;
    public $show_on_checkout_page;
    public $enable_checkout_button_top;
    public $show_on_mini_cart;
    public $order_review_page_title;
    public $order_review_page_description;
    public $paymentaction;
    public $advanced_card_payments;
    public $threed_secure_contingency;
    public $enabled_pay_later_messaging;
    public $pay_later_messaging_page_type;
    public $set_billing_address;
    public $AVSCodes;
    public $CVV2Codes;
    public $logger;
    public $disable_funding;
    public $style_layout;
    public $style_color;
    public $style_shape;
    public $style_label;
    public $button_size;
    public $button_height;
    public $mini_cart_style_layout;
    public $mini_cart_style_color;
    public $mini_cart_style_shape;
    public $mini_cart_style_label;
    public $mini_cart_button_size;
    public $mini_cart_button_height;
    public $express_checkout_style_layout;
    public $express_checkout_style_color;
    public $express_checkout_style_shape;
    public $express_checkout_style_label;
    public $express_checkout_button_height;
    public $is_mobile;
    public $enabled_google_pay;
    public $google_pay_pages;
    public $enabled_apple_pay;
    public $apple_pay_pages;
    public $payment_token;
    public $min_cart_button_location;
    public $cart_button_location;
    public $cart_priority;
    public $min_cart_priority;
    public $ppcp_locale;
    public $device_class;
    public $button_class;
    public $mini_cart_button_class;
    public $use_place_order;
    public $google_pay_style_label;
    public $google_pay_style_color;
    public $google_pay_style_shape;
    public $google_pay_express_checkout_style_label;
    public $google_pay_express_checkout_style_color;
    public $google_pay_express_checkout_style_shape;
    public $google_pay_mini_cart_style_label;
    public $google_pay_mini_cart_style_color;
    public $google_pay_mini_cart_style_shape;
    public $apple_pay_style_label;
    public $apple_pay_style_color;
    public $apple_pay_style_shape;
    public $apple_pay_express_checkout_style_label;
    public $apple_pay_express_checkout_style_color;
    public $apple_pay_express_checkout_style_shape;
    public $apple_pay_mini_cart_style_label;
    public $apple_pay_mini_cart_style_color;
    public $apple_pay_mini_cart_style_shape;
    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->plugin_name = 'woo-paypal-gateway';
        $this->version = WPG_PLUGIN_VERSION;
        $this->checkout_details = '';
        if (empty($this->woocommerce_wpg_paypal_checkout_settings)) {
            $this->woocommerce_wpg_paypal_checkout_settings = get_option('woocommerce_wpg_paypal_checkout_settings', true);
        }
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();

        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler')) {
            require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-locale_handler.php';
        }
        $this->ppcp_locale = PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler::instance();

        $this->get_properties();
        if ($this->is_valid_for_use() === true) {
            if (!has_action('woocommerce_api_' . strtolower('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'))) {
                add_action('woocommerce_api_' . strtolower('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'), array($this, 'handle_wc_api'));
            }
            $this->ppcp_add_hooks();
        }
    }

    public function get_properties() {
        $this->title = 'PayPal';
        $this->enabled = 'yes' === $this->ppcp_get_settings('enabled', 'yes');
        $this->sandbox = 'yes' === $this->ppcp_get_settings('sandbox', 'no');
        if ($this->sandbox) {
            $this->client_id = $this->ppcp_get_settings('rest_client_id_sandbox');
            $this->secret = $this->ppcp_get_settings('rest_secret_id_sandbox');
            $this->webhook_id = 'ppcp_sandbox_webhook_id';
            $this->client_token = get_transient('ppcp_sandbox_client_token');
        } else {
            $this->client_id = $this->ppcp_get_settings('rest_client_id_live');
            $this->secret = $this->ppcp_get_settings('rest_secret_id_live');
            $this->webhook_id = 'ppcp_live_webhook_id';
            $this->client_token = get_transient('ppcp_client_token');
        }
        if (empty($this->client_id) || empty($this->secret)) {
            $this->enabled = false;
        }
        $this->ppcp_currency_list = array('AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'INR', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD');
        $this->ppcp_currency = in_array(get_woocommerce_currency(), $this->ppcp_currency_list) ? get_woocommerce_currency() : 'USD';

        $paypal_button_pages = $this->ppcp_get_settings('paypal_button_pages', array('express_checkout', 'checkout', 'mini_cart'));
        $this->show_on_product_page = in_array('product', $paypal_button_pages, true);
        $this->show_on_cart = in_array('cart', $paypal_button_pages, true);
        $this->show_on_checkout_page = true;
        $this->enable_checkout_button_top = in_array('express_checkout', $paypal_button_pages, true);
        $this->show_on_mini_cart = in_array('mini_cart', $paypal_button_pages, true);

        $this->is_mobile = wp_is_mobile();
        $this->device_class = $this->is_mobile ? 'mobile' : 'desktop';
        $this->paymentaction = $this->ppcp_get_settings('paymentaction', 'capture');
        $this->advanced_card_payments = 'yes' === $this->ppcp_get_settings('enable_advanced_card_payments', 'no');
        $this->threed_secure_contingency = $this->ppcp_get_settings('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
        $this->enabled_pay_later_messaging = 'yes' === $this->ppcp_get_settings('enabled_pay_later_messaging', 'no');
        $this->pay_later_messaging_page_type = $this->ppcp_get_settings('pay_later_messaging_page_type', array());
        $this->min_cart_button_location = 'below';
        $this->cart_button_location = $this->ppcp_get_settings('cart_button_location', 'below');
        $this->min_cart_priority = ($this->min_cart_button_location === 'below') ? 30 : 5;
        $this->cart_priority = ($this->cart_button_location === 'below') ? 30 : 11;
        $this->enabled_google_pay = 'yes' === $this->ppcp_get_settings('enabled_google_pay', 'no');
        $this->google_pay_pages = $this->ppcp_get_settings('google_pay_pages', array('express_checkout'));
        if (empty($this->google_pay_pages)) {
            $this->enabled_google_pay = false;
        }
        $this->enabled_apple_pay = 'yes' === $this->ppcp_get_settings('enabled_apple_pay', 'no');
        $this->apple_pay_pages = $this->ppcp_get_settings('apple_pay_pages', array());
        if (empty($this->apple_pay_pages)) {
            $this->enabled_apple_pay = false;
        }
        if (is_ssl() === false) {
            $this->enabled_apple_pay = false;
        }
        if (empty($this->pay_later_messaging_page_type)) {
            $this->enabled_pay_later_messaging = false;
        }
        $this->set_billing_address = 'yes' === $this->ppcp_get_settings('set_billing_address', 'yes');
        $this->set_billing_address = is_user_logged_in() ? false : true;
        if (wc_ship_to_billing_address_only()) {
            $this->set_billing_address = true;
        }
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
        $this->use_place_order = 'yes' === $this->ppcp_get_settings('use_place_order', 'no');
    }

    public function ppcp_add_hooks() {
        add_action('wp', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('woocommerce_after_add_to_cart_form', array($this, 'display_paypal_button_product_page'), 1);
        add_action('woocommerce_proceed_to_checkout', array($this, 'display_paypal_button_cart_page'), $this->cart_priority);
        add_action('woocommerce_review_order_after_submit', array($this, 'display_paypal_button_checkout_page'));
        add_action('addonify_floating_cart_sidebar_cart_footer', array($this, 'display_paypal_button_mini_cart_page'), $this->min_cart_priority);
        add_action('woofc_below_buttons', array($this, 'display_paypal_button_mini_cart_page'), $this->min_cart_priority);
        add_action('xoo_wsc_after_footer_btns', array($this, 'display_paypal_button_mini_cart_page'), $this->min_cart_priority);
        add_action('woocommerce_widget_shopping_cart_buttons', array($this, 'display_paypal_button_mini_cart_page'), $this->min_cart_priority);
        add_action('xt_woofc_after_cart_body_footer', array($this, 'display_paypal_button_mini_cart_page'), $this->min_cart_priority);
        add_action('fkcart_after_checkout_button', array($this, 'display_paypal_button_mini_cart_page_for_funnelkit_sliding_cart'), $this->min_cart_priority);

        add_action('init', array($this, 'init'));
        add_filter('script_loader_tag', array($this, 'ppcp_clean_url'), 10, 2);
        add_action('wp_loaded', array($this, 'ppcp_session_manager'), 999);
        add_action('wp_head', array($this, 'ppcp_add_header_meta'), 0);
        add_filter('the_title', array($this, 'ppcp_endpoint_page_titles'));
        add_action('woocommerce_cart_emptied', array($this, 'maybe_clear_session_data'));
        add_action('woocommerce_available_payment_gateways', array($this, 'maybe_disable_other_gateways'));
        add_filter('woocommerce_default_address_fields', array($this, 'filter_default_address_fields'));
        add_action('woocommerce_checkout_process', array($this, 'copy_checkout_details_to_post'));
        add_action('woocommerce_cart_shipping_packages', array($this, 'maybe_add_shipping_information'));
        add_filter('body_class', array($this, 'ppcp_add_class_order_review_page'));
        add_action('woocommerce_before_checkout_form', array($this, 'ppcp_order_review_page_description'), 9);
        add_action('woocommerce_order_status_processing', array($this, 'ppcp_capture_payment'));
        add_action('woocommerce_order_status_completed', array($this, 'ppcp_capture_payment'));
        add_action('woocommerce_order_status_cancelled', array($this, 'ppcp_cancel_authorization'));
        add_action('woocommerce_order_status_refunded', array($this, 'ppcp_cancel_authorization'));
        add_filter('woocommerce_order_actions', array($this, 'ppcp_add_capture_charge_order_action'));
        add_action('woocommerce_order_action_ppcp_capture_charge', array($this, 'ppcp_maybe_capture_charge'));
        add_action('woocommerce_before_checkout_form', array($this, 'ppcp_update_checkout_field_details'));
        add_action('woocommerce_review_order_before_submit', array($this, 'ppcp_cancel_button'));
        add_action('wp_loaded', array($this, 'ppcp_create_webhooks'));
        add_action('woocommerce_pay_order_after_submit', array($this, 'ppcp_add_order_id'));
        add_action('wp_loaded', array($this, 'ppcp_prevent_add_to_cart_woo_action'), 1);
        add_action('woocommerce_get_checkout_url', array($this, 'ppcp_woocommerce_get_checkout_url'), 9999, 1);
        add_filter('woocommerce_get_return_url', array($this, 'wpg_remove_paypal_order_id_from_return_url'), 9999, 2);
        if (!has_action('woocommerce_before_checkout_form', array($this, 'display_paypal_button_top_checkout_page'))) {
            add_action('woocommerce_before_checkout_form', array($this, 'display_paypal_button_top_checkout_page'), 10);
        }
        add_filter('woocommerce_available_payment_gateways', array($this, 'wpg_ppcp_short_gateway'), 9999);
        add_action('wp_loaded', array($this, 'ppcp_block_set_address'), 999);
        add_action('admin_init', array($this, 'ppcp_admin_init'), 100);
        add_action('wpg_ppcp_save_payment_method_details', array($this, 'wpg_ppcp_save_payment_method_details'), 10, 2);
        add_filter('wpg_ppcp_currency', array($this, 'wpg_ppcp_currency'), 10, 1);
        add_action('wp_ajax_ppcp_get_updated_total', array($this, 'ppcp_get_updated_total'));
        add_action('wp_ajax_nopriv_ppcp_get_updated_total', array($this, 'ppcp_get_updated_total'));
        add_action('wp_ajax_ppcp_get_product_total', array($this, 'ppcp_get_product_total'));
        add_action('wp_ajax_nopriv_ppcp_get_product_total', array($this, 'ppcp_get_product_total'));
    }

    public function enqueue_scripts() {
        try {
            if (is_checkout() && !empty($this->checkout_details) && !empty($_GET['paypal_order_id'])) {
                wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-order-capture', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/ppcp-paypal-checkout-for-woocommerce-order-capture.js', array('jquery'), $this->version, false);
                return;
            }
            if (is_checkout() && $this->advanced_card_payments && $this->client_token === false) {
                //delete_transient('ppcp_sandbox_client_token');
                //delete_transient('ppcp_client_token');
                $this->request->get_genrate_token();
                //$this->get_properties();
            }
            $this->ppcp_paypal_button_style_properties();
            $ppcp_js_arg = array();
            $ppcp_js_arg['client-id'] = $this->client_id;
            $ppcp_js_arg['currency'] = apply_filters('wpg_ppcp_currency', $this->ppcp_currency);
            if (!isset($this->disable_funding['venmo'])) {
                $ppcp_js_arg['enable-funding'] = 'venmo,paylater';
            }
            if ($this->disable_funding !== false && count($this->disable_funding) > 0) {
                $ppcp_js_arg['disable-funding'] = implode(',', $this->disable_funding);
            }
            if ($this->sandbox) {
                if (is_user_logged_in() && WC()->customer && WC()->customer->get_billing_country() && 2 === strlen(WC()->customer->get_billing_country())) {
                    $ppcp_js_arg['buyer-country'] = WC()->customer->get_billing_country();
                }
            }
            $is_product_page = is_product();
            $needs_shipping = false;
            $shipping_disabled = get_option('woocommerce_ship_to_countries') === 'disabled';
            if (!$shipping_disabled && $is_product_page) {
                $product = wc_get_product();
                if ($product instanceof WC_Product && $product->needs_shipping()) {
                    $needs_shipping = true;
                }
            }
            if (WC()->cart && !WC()->cart->is_empty() && WC()->cart->needs_shipping()) {
                $needs_shipping = true;
            }
            $page = '';
            $is_pay_page = 'no';
            $button_selector = array();
            if (is_product()) {
                $page = 'product';
                if ($this->show_on_product_page) {
                    $button_selector['ppcp_product_page'] = '#ppcp_product';
                }
            } elseif (is_cart() && !WC()->cart->is_empty()) {
                $page = 'cart';
                if ($this->show_on_cart) {
                    $button_selector['ppcp_cart'] = '#ppcp_cart';
                    $button_selector['ppcp_checkout'] = '#ppcp_checkout';
                    $button_selector['ppcp_checkout_top'] = '#ppcp_checkout_top';
                }
            } elseif (is_checkout_pay_page()) {
                $page = 'checkout';
                $button_selector['ppcp_checkout'] = '#ppcp_checkout';
                $is_pay_page = 'yes';
            } elseif (is_checkout()) {
                $page = 'checkout';
                if ($this->show_on_checkout_page) {
                    $button_selector['ppcp_checkout'] = '#ppcp_checkout';
                    $button_selector['ppcp_checkout_top'] = '#ppcp_checkout_top';
                }
            }
            if ($this->show_on_mini_cart) {
                $button_selector['ppcp_mini_cart'] = '#ppcp_mini_cart';
            }
            $ppcp_js_arg['commit'] = ( $page === 'checkout' ) ? 'true' : 'false';
            $ppcp_js_arg['intent'] = ( $this->paymentaction === 'capture' ) ? 'capture' : 'authorize';
            $ppcp_js_arg['locale'] = $this->ppcp_locale->get_valid_locale();
            if (is_wpg_paypal_vault_required()) {
                $ppcp_js_arg['vault'] = 'true';
            }
            $components = array("buttons");
            if ($this->enabled_google_pay) {
                $components[] = "googlepay";
            }
            if ($this->enabled_apple_pay) {
                $components[] = "applepay";
            }
            if ((is_checkout() || is_checkout_pay_page()) && $this->advanced_card_payments) {
                array_push($components, "card-fields");
            }
            if ($this->enabled_pay_later_messaging) {
                array_push($components, 'messages');
            }
            array_push($components, 'funding-eligibility');
            array_push($components, 'payment-fields');

            if (!empty($components)) {
                $ppcp_js_arg['components'] = implode(',', $components);
            }
            if ($this->is_mobile) {
                $this->express_checkout_style_layout = 'vertical';
            }
            $product_id = is_product() ? get_the_ID() : 0;
            $js_url = add_query_arg($ppcp_js_arg, 'https://www.paypal.com/sdk/js');

            wp_register_script('ppcp-checkout-js', $js_url, array(), null, false);
            wp_enqueue_script('jquery-blockui');
            wp_register_script('ppcp-paypal-checkout-for-woocommerce-public', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/ppcp-paypal-checkout-for-woocommerce-public.js', array('jquery'), WPG_PLUGIN_VERSION, false);

            $order_id = absint( get_query_var( 'order-pay' ) );

            $query_args = array(
                'ppcp_action' => 'create_order',
                'utm_nooverride' => '1',
                'used' => 'card',
                'from' => is_checkout_pay_page() ? 'pay_page' : $page,
            );

            if ($order_id > 0) {
                $query_args['order_id'] = $order_id;
            }

            $create_order_url_for_cc = add_query_arg($query_args, WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'));

            wp_localize_script('ppcp-paypal-checkout-for-woocommerce-public', 'ppcp_manager', array(
            'style_color' => $this->style_color,
            'style_shape' => $this->style_shape,
            'style_label' => $this->style_label,
            'style_layout' => $this->style_layout,
            'button_size' => $this->button_size,
            'button_height' => $this->button_height,
            'express_checkout_style_color' => $this->express_checkout_style_color,
            'express_checkout_style_shape' => $this->express_checkout_style_shape,
            'express_checkout_style_label' => $this->express_checkout_style_label,
            'express_checkout_style_layout' => $this->express_checkout_style_layout,
            'express_checkout_button_height' => $this->express_checkout_button_height,
            'mini_cart_style_color' => $this->mini_cart_style_color,
            'mini_cart_style_shape' => $this->mini_cart_style_shape,
            'mini_cart_style_label' => $this->mini_cart_style_label,
            'mini_cart_style_layout' => $this->mini_cart_style_layout,
            'mini_cart_button_size' => $this->mini_cart_button_size,
            'mini_cart_button_height' => $this->mini_cart_button_height,
            'google_pay_style_label' => $this->google_pay_style_label,
            'google_pay_style_color' => $this->google_pay_style_color,
            'google_pay_style_shape' => $this->google_pay_style_shape,
            'google_pay_express_checkout_style_label' => $this->google_pay_express_checkout_style_label,
            'google_pay_express_checkout_style_color' => $this->google_pay_express_checkout_style_color,
            'google_pay_express_checkout_style_shape' => $this->google_pay_express_checkout_style_shape,
            'google_pay_mini_cart_style_label' => $this->google_pay_mini_cart_style_label,
            'google_pay_mini_cart_style_color' => $this->google_pay_mini_cart_style_color,
            'google_pay_mini_cart_style_shape' => $this->google_pay_mini_cart_style_shape,
            'apple_pay_style_label' => $this->apple_pay_style_label,
            'apple_pay_style_color' => $this->apple_pay_style_color,
            'apple_pay_style_shape' => $this->apple_pay_style_shape,
            'apple_pay_express_checkout_style_label' => $this->apple_pay_express_checkout_style_label,
            'apple_pay_express_checkout_style_color' => $this->apple_pay_express_checkout_style_color,
            'apple_pay_express_checkout_style_shape' => $this->apple_pay_express_checkout_style_shape,
            'apple_pay_mini_cart_style_label' => $this->apple_pay_mini_cart_style_label,
            'apple_pay_mini_cart_style_color' => $this->apple_pay_mini_cart_style_color,
            'apple_pay_mini_cart_style_shape' => $this->apple_pay_mini_cart_style_shape,
            'page' => $page,
            'is_pay_page' => $is_pay_page,
            'checkout_url' => wc_get_checkout_url(),
            'display_order_page' => add_query_arg(array('ppcp_action' => 'display_order_page', 'utm_nooverride' => '1'), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')),
            'cc_capture' => add_query_arg(array('ppcp_action' => 'cc_capture', 'utm_nooverride' => '1'), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')),
            'create_order_url_for_paypal' => add_query_arg(array('ppcp_action' => 'create_order', 'utm_nooverride' => '1', 'used' => 'paypal', 'from' => is_checkout_pay_page() ? 'pay_page' : $page), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')),
            'create_order_url_for_google_pay' => add_query_arg(array('ppcp_action' => 'create_order', 'utm_nooverride' => '1', 'used' => 'alternative_pay', 'from' => is_checkout_pay_page() ? 'pay_page' : $page), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')),
            'create_order_url_for_cc' => $create_order_url_for_cc,
            'get_transaction_info_url' => add_query_arg(array('ppcp_action' => 'get_transaction_info', 'utm_nooverride' => '1', 'from' => is_checkout_pay_page() ? 'pay_page' : $page), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager')),
            'cancel_url' => wc_get_cart_url(),
            'paymentaction' => $this->paymentaction,
            'advanced_card_payments' => ($this->advanced_card_payments === true) ? 'yes' : 'no',
            'threed_secure_contingency' => $this->threed_secure_contingency,
            'woocommerce_process_checkout' => wp_create_nonce('woocommerce-process_checkout'),
            'button_selector' => $button_selector,
            'enabled_google_pay' => $this->should_enable_google_pay_for_page($page) ? 'yes' : 'no',
            'enabled_apple_pay' => $this->should_enable_apple_pay_for_page($page) ? 'yes' : 'no',
            'is_apple_pay_enable_checkout' => $this->is_apple_pay_enable_for_page('checkout') ? 'yes' : 'no',
            'is_google_pay_enabled_checkout' => $this->is_google_pay_enable_for_page('checkout') ? 'yes' : 'no',
            'locale' => explode('-', get_bloginfo('language'))[0] ?? 'en',
            'is_wpg_change_payment_method' => is_wpg_change_payment_method() ? 'yes' : 'no',
            'environment' => $this->sandbox ? 'TEST' : 'PRODUCTION',
            'button_height' => $this->button_height,
            'express_checkout_button_height' => $this->express_checkout_button_height,
            'mini_cart_button_height' => $this->mini_cart_button_height,
            'ajax_nonce' => wp_create_nonce('ppcp_ajax_nonce'),
            'currency' => get_woocommerce_currency(),
            'cart_total' => WC()->cart ? WC()->cart->get_total('edit') : '0.00',
            'is_product_page' => $is_product_page,
            'needs_shipping' => $needs_shipping ? '1' : '0',
            'ajax_url' => admin_url('admin-ajax.php'),
            'use_place_order' => $this->use_place_order,
            'product_id' => $product_id,
            'is_block_enable' => wpg_is_using_block_cart_or_checkout() ? 'yes' : 'no'
            )
            );
        } catch (Exception $ex) {
            
        }
    }

    public function enqueue_styles() {
        wp_register_style('ppcp-paypal-checkout-for-woocommerce-public', plugin_dir_url(__FILE__) . 'css/ppcp-paypal-checkout-for-woocommerce-public.css', array(), $this->version, 'all');
    }

    public function is_valid_for_use() {
        if (!empty($this->client_id) && !empty($this->secret) && ($this->enabled || $this->advanced_card_payments)) {
            return true;
        }
        return false;
    }

    public function calculate_button_radius($shape, $height) {
        return $shape === 'pill' ? round($height / 2) : 4;
    }

    public function display_paypal_button_product_page() {
        if ($this->enabled === false) {
            return;
        }
        global $product;
        if (!is_product() || !$product->is_in_stock() || $product->is_type('external') || $product->is_type('grouped')) {
            return;
        }
        if ($this->show_on_product_page || $this->is_google_pay_enable_for_page('product') || $this->is_apple_pay_enable_for_page('product')) {
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            echo '<div class="ppcp-button-container">';
            if ($this->show_on_product_page) {
                echo '<div id="ppcp_product" class="' . $this->button_class . '"></div>';
            }
            if ($this->is_google_pay_enable_for_page('product')) {
                echo '<div data-context="product" class="google-pay-container product ' . $this->button_class . '" style="height: ' . (int) $this->button_height . 'px;"></div>';
            }
            if ($this->is_apple_pay_enable_for_page('product')) {
                $button_height = (int) $this->button_height;
                $button_shape = $this->apple_pay_style_shape;
                $button_radius = $this->calculate_button_radius($button_shape, $button_height);
                $shape_class = $button_shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect';
                echo '<div data-context="product" class="apple-pay-container product ' . esc_attr($this->button_class . ' ' . $shape_class) . '" style="--button-height: ' . $button_height . 'px; --button-radius: ' . $button_radius . 'px; height: ' . $button_height . 'px;"></div>';
            }
            echo '</div>';
        }
    }

    public function display_paypal_button_cart_page() {
        if ($this->enabled === false) {
            return;
        }
        if ($this->show_on_cart || $this->is_google_pay_enable_for_page('cart') || $this->is_apple_pay_enable_for_page('cart')) {
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            echo '<div class="ppcp-button-container ppcp_cart">';
            if ($this->cart_priority === 30) {
                echo '<div class="ppcp-proceed-to-checkout-button-separator ' . $this->button_class . '"><span>' . _x('Or', 'Important', 'woo-paypal-gateway') . '</span></div>';
            }
            if ($this->show_on_cart) {
                echo '<div id="ppcp_cart" class="cart ' . $this->button_class . '"></div>';
            }
            if ($this->is_google_pay_enable_for_page('cart')) {
                echo '<div data-context="cart" class="google-pay-container cart ' . esc_attr($this->button_class) . '" style="height: ' . (int) $this->button_height . 'px;"></div>';
            }
            if ($this->is_apple_pay_enable_for_page('cart')) {
                $button_height = (int) $this->button_height;
                $button_shape = $this->apple_pay_style_shape;
                $button_radius = $this->calculate_button_radius($button_shape, $button_height);
                $shape_class = $button_shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect';
                echo '<div data-context="cart" class="apple-pay-container cart ' . esc_attr($this->button_class . ' ' . $shape_class) . '" style="--button-height: ' . $button_height . 'px; --button-radius: ' . $button_radius . 'px; height: ' . $button_height . 'px;"></div>';
            }

            if ($this->cart_priority === 11) {
                echo '<div class="ppcp-proceed-to-checkout-button-separator ' . $this->button_class . '"><span>' . _x('Or', 'Important', 'woo-paypal-gateway') . '</span></div>';
            }
            echo '</div>';
        }
    }

    public function display_paypal_button_mini_cart_page() {
        if ($this->enabled === false) {
            return;
        }
        if (WC()->cart->needs_payment() === false) {
            return '';
        }
        $html = '';
        if ($this->show_on_mini_cart || $this->is_google_pay_enable_for_page('mini_cart') || $this->is_apple_pay_enable_for_page('mini_cart')) {
            $this->ppcp_paypal_button_style_properties();
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            $html .= '<div class="ppcp-button-container ppcp_mini_cart">';
            if ($this->show_on_mini_cart) {
                $html .= '<div id="ppcp_mini_cart" class="' . $this->mini_cart_button_class . '"></div>';
            }
            if ($this->is_google_pay_enable_for_page('mini_cart')) {
                $html .= '<div data-context="mini_cart" class="google-pay-container mini_cart ' . $this->mini_cart_button_class . '" style="height: ' . (int) $this->mini_cart_button_height . 'px;"></div>';
            }
            if ($this->is_apple_pay_enable_for_page('mini_cart')) {
                $button_height = (int) $this->mini_cart_button_height;
                $button_shape = $this->apple_pay_mini_cart_style_shape;
                $button_radius = $this->calculate_button_radius($button_shape, $button_height);
                $shape_class = $button_shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect';
                $html .= '<div data-context="mini_cart" class="apple-pay-container mini_cart ' . esc_attr($this->mini_cart_button_class . ' ' . $shape_class) . '" style="--button-height: ' . $button_height . 'px; --button-radius: ' . $button_radius . 'px; height: ' . $button_height . 'px;"></div>';
            }
            $html .= '</div>';
        }
        echo $html;
    }

    public function display_paypal_button_mini_cart_page_for_funnelkit_sliding_cart() {
        if ($this->enabled === false) {
            return;
        }
        if (WC()->cart->needs_payment() === false) {
            return '';
        }
        $html = '';
        if ($this->show_on_mini_cart || $this->is_google_pay_enable_for_page('mini_cart') || $this->is_apple_pay_enable_for_page('mini_cart')) {
            $this->ppcp_paypal_button_style_properties();
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            $html .= '<div class="fkcart-checkout-wrap fkcart-panel"><div class="ppcp-button-container ppcp_mini_cart">';
            if ($this->show_on_mini_cart) {
                $html .= '<div id="ppcp_mini_cart" class="' . $this->mini_cart_button_class . '"></div>';
            }
            if ($this->is_google_pay_enable_for_page('mini_cart')) {
                $html .= '<div data-context="mini_cart" class="google-pay-container mini_cart ' . $this->mini_cart_button_class . '" style="height: ' . (int) $this->mini_cart_button_height . 'px;"></div>';
            }
            if ($this->is_apple_pay_enable_for_page('mini_cart')) {
                $button_height = (int) $this->mini_cart_button_height;
                $button_shape = $this->apple_pay_mini_cart_style_shape;
                $button_radius = $this->calculate_button_radius($button_shape, $button_height);
                $shape_class = $button_shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect';
                $html .= '<div data-context="mini_cart" class="apple-pay-container mini_cart ' . esc_attr($this->mini_cart_button_class . ' ' . $shape_class) . '" style="--button-height: ' . $button_height . 'px; --button-radius: ' . $button_radius . 'px; height: ' . $button_height . 'px;"></div>';
            }
            $html .= '</div></div>';
        }
        echo $html;
    }

    public function display_paypal_button_checkout_page() {
        if ($this->enabled === false) {
            return;
        }
        if (ppcp_has_active_session() === true) {
            if (is_checkout()) {
                wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            }
            return false;
        }
        if ($this->show_on_checkout_page || $this->is_google_pay_enable_for_page('checkout') || $this->is_apple_pay_enable_for_page('checkout')) {
            $this->ppcp_paypal_button_style_properties();
            if ($this->use_place_order === false) {
                wp_enqueue_script('ppcp-checkout-js');
                wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
                wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
                echo '<div class="ppcp-button-container">';
                echo '<div id="ppcp_checkout" class="checkout ' . $this->button_class . '"></div>';
                if ($this->is_google_pay_enable_for_page('checkout')) {
                    echo '<div data-context="checkout" class="google-pay-container checkout ' . $this->button_class . '" style="height: ' . (int) $this->button_height . 'px;"></div>';
                }
                if ($this->is_apple_pay_enable_for_page('checkout')) {
                    $button_height = (int) $this->button_height;
                    $button_shape = $this->apple_pay_style_shape;
                    $button_radius = $this->calculate_button_radius($button_shape, $button_height);
                    $shape_class = $button_shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect';
                    echo '<div data-context="checkout" class="apple-pay-container checkout ' . esc_attr($this->button_class . ' ' . $shape_class) . '" style="--button-height: ' . $button_height . 'px; --button-radius: ' . $button_radius . 'px; height: ' . $button_height . 'px;"></div>';
                }
                echo '</div>';
            } elseif ($this->advanced_card_payments) {
                wp_enqueue_script('ppcp-checkout-js');
                wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
                wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            }
        } elseif ($this->advanced_card_payments) {
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        }
    }

    public function display_paypal_button_top_checkout_page() {
        if ($this->enabled === false) {
            return;
        }
        if (ppcp_has_active_session() === true) {
            return false;
        }
        $is_google_pay_enabled = $this->is_google_pay_enable_for_page('express_checkout');
        $is_apple_pay_enabled = $this->is_apple_pay_enable_for_page('express_checkout');
        $is_paypal_enabled = $this->enable_checkout_button_top === true;
        if (!$is_google_pay_enabled && !$is_apple_pay_enabled && !$is_paypal_enabled) {
            return;
        }
        wp_enqueue_script('ppcp-checkout-js');
        wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-public');
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        echo '<div class="ppcp-button-container">';
        echo '<fieldset>';
        echo '<legend class="express-title">' . _x('Express Checkout', 'Important', 'woo-paypal-gateway') . '</legend>';
        echo '<div class="wc_ppcp_express_checkout_gateways">';
        echo '<div class="express_payment_method_ppcp ' . $this->device_class . '">';
        if ($is_paypal_enabled) {
            echo '<div id="ppcp_checkout_top" class="' . $this->device_class . '"></div>';
            echo '<div id="ppcp_checkout_top_alternative" class="' . $this->device_class . '"></div>';
        }
        if ($is_google_pay_enabled) {
            echo '<div data-context="express_checkout" class="google-pay-container express_checkout ' . $this->device_class . '" style="height: ' . (int) $this->express_checkout_button_height . 'px;"></div>';
        }
        if ($is_apple_pay_enabled) {
            $button_height = (int) $this->express_checkout_button_height;
            $button_shape = $this->apple_pay_express_checkout_style_shape;
            $button_radius = $this->calculate_button_radius($button_shape, $button_height);
            $shape_class = $button_shape === 'pill' ? 'apple-shape-pill' : 'apple-shape-rect';
            echo '<div data-context="express_checkout" class="apple-pay-container express_checkout ' . esc_attr($this->device_class . ' ' . $shape_class) . '" style="--button-height: ' . $button_height . 'px; --button-radius: ' . $button_radius . 'px; height: ' . $button_height . 'px;"></div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</fieldset>';
        echo '<span class="express-divider">' . _x('Or continue below', 'Important', 'woo-paypal-gateway') . '</span>';
        echo '</div>';
        remove_action('woocommerce_before_checkout_form', array($this, 'display_paypal_button_top_checkout_page'), 10);
    }

    public function ppcp_add_header_meta() {
        if ($this->is_valid_for_use() === true) {
            echo '<meta http-equiv="X-UA-Compatible" content="IE=edge" />';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        }
    }

    public function ppcp_get_settings($key, $default = false) {
        if (!isset($this->woocommerce_wpg_paypal_checkout_settings[$key])) {
            return $default;
        }
        $value = $this->woocommerce_wpg_paypal_checkout_settings[$key];
        if (empty($value)) {
            return is_array($default) ? [] : '';
        }
        return $value;
    }

    public function ppcp_endpoint_page_titles($title) {
        if (!is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && !empty($_GET['paypal_payer_id'])) {
            $title = _x('Confirm your PayPal order', 'Important', 'woo-paypal-gateway');
            remove_filter('the_title', array($this, 'ppcp_endpoint_page_titles'));
        }
        return $title;
    }

    public function handle_wc_api() {
        if (!empty($_GET['ppcp_action'])) {
            if (isset($_GET['used']) && !empty($_GET['used'])) {
                ppcp_set_session('wpg_payment_method', wc_clean($_GET['used']));
            }
            switch ($_GET['ppcp_action']) {
                case "webhook_handler":
                    $this->ppcp_handle_webhook_request();
                    ob_clean();
                    header('HTTP/1.1 200 OK');
                    exit();
                    break;
                case "cancel_order":
                    unset(WC()->session->ppcp_session);
                    wp_redirect(wc_get_cart_url());
                    exit();
                    break;
                case "create_order":
                    if (isset($_GET['from']) && 'pay_page' === $_GET['from']) {
                        if (!empty($_GET['order_id'])) {
                            $woo_order_id = absint($_GET['order_id']);
                        } elseif (!empty($_POST['woo_order_id'])) {
                            $woo_order_id = absint($_POST['woo_order_id']);
                        } else {
                            $woo_order_id = 0; // fallback or error handling
                        }
                        ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
                        $this->request->ppcp_create_order_request($woo_order_id);
                        exit();
                    } elseif (isset($_GET['from']) && 'checkout' === $_GET['from']) {
                        if (isset($_POST) && !empty($_POST)) {
                            if (isset($_POST['radio-control-wc-payment-method-options'])) {
                                $address = array();
                                $address['radio-control-wc-payment-method-options'] = wc_clean($_POST['radio-control-wc-payment-method-options']);
                                $address['payment_method'] = wc_clean($_POST['radio-control-wc-payment-method-options']);

                                $billing_address = json_decode(stripslashes($_POST['billing_address']), true);
                                foreach ($billing_address as $key => $address_value) {
                                    $address[$key] = $address_value;
                                }
                                $shipping_address = json_decode(stripslashes($_POST['shipping_address']), true);
                                foreach ($shipping_address as $key => $address_value) {
                                    $address[$key] = $address_value;
                                }

                                $_POST = $address;
                                if (!empty($shipping_address)) {
                                    add_filter('woocommerce_checkout_fields', function ($fields) {
                                        $fields['billing']['billing_phone']['required'] = false; // Make phone field optional
                                        return $fields;
                                    });
                                }
                            }

                            add_action('woocommerce_after_checkout_validation', array($this, 'maybe_start_checkout'), 10, 2);
                            WC()->checkout->process_checkout();
                            if (wc_notice_count('error') > 0) {
                                WC()->session->set('reload_checkout', true);
                                $error_messages_data = wc_get_notices('error');
                                $error_messages = array();
                                foreach ($error_messages_data as $key => $value) {
                                    $error_messages[] = $value['notice'];
                                }
                                wc_clear_notices();
                                ob_start();
                                wp_send_json_error(array('messages' => $error_messages));
                                exit;
                            }
                            exit();
                        } else {
                            $_GET['from'] = 'cart';
                            $this->request->ppcp_create_order_request();
                            exit();
                        }
                    } elseif (isset($_GET['from']) && 'product' === $_GET['from']) {
                        try {
                            PPCP_Paypal_Checkout_For_Woocommerce_Product::ppcp_add_to_cart_action();
                            $this->request->ppcp_create_order_request();
                            exit();
                        } catch (Exception $ex) {
                            
                        }
                    } else {
                        $this->request->ppcp_create_order_request();
                        exit();
                    }
                    break;
                case "display_order_page":
                    $this->ppcp_display_order_page();
                    break;
                case "ppcp_regular_capture":
                    $this->request->ppcp_regular_capture();
                    exit();
                case "cc_capture":
                    wc_clear_notices();
                    ppcp_set_session('ppcp_paypal_order_id', wc_clean($_GET['paypal_order_id']));
                    $this->ppcp_cc_capture();
                    break;
                case "get_transaction_info":
                    if (isset($_GET['from']) && 'pay_page' === $_GET['from']) {
                        $woo_order_id = $_POST['woo_order_id'];
                        ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
                        wp_send_json_success(array(
                            'cart_total' => WC()->cart->total,
                            'needs_shipping' => WC()->cart->needs_shipping() ? '1' : '0'
                        ));
                        exit();
                    } elseif (isset($_GET['from']) && 'checkout' === $_GET['from']) {
                        if (isset($_POST) && !empty($_POST)) {
                            if (isset($_POST['radio-control-wc-payment-method-options'])) {
                                $address = array();
                                $address['radio-control-wc-payment-method-options'] = wc_clean($_POST['radio-control-wc-payment-method-options']);
                                $address['payment_method'] = wc_clean($_POST['radio-control-wc-payment-method-options']);

                                $billing_address = json_decode(stripslashes($_POST['billing_address']), true);
                                foreach ($billing_address as $key => $address_value) {
                                    $address[$key] = $address_value;
                                }

                                $shipping_address = json_decode(stripslashes($_POST['shipping_address']), true);
                                foreach ($shipping_address as $key => $address_value) {
                                    $address[$key] = $address_value;
                                }

                                $_POST = $address;

                                if (!empty($shipping_address)) {
                                    add_filter('woocommerce_checkout_fields', function ($fields) {
                                        $fields['billing']['billing_phone']['required'] = false;
                                        return $fields;
                                    });
                                }
                            }

                            add_action('woocommerce_after_checkout_validation', array($this, 'maybe_start_checkout'), 10, 2);
                            WC()->checkout->process_checkout();

                            if (wc_notice_count('error') > 0) {
                                WC()->session->set('reload_checkout', true);
                                $error_messages_data = wc_get_notices('error');
                                $error_messages = array();
                                foreach ($error_messages_data as $key => $value) {
                                    $error_messages[] = $value['notice'];
                                }
                                wc_clear_notices();
                                ob_start();
                                wp_send_json_error(array('messages' => $error_messages));
                                exit;
                            }

                            wp_send_json_success(array(
                                'cart_total' => WC()->cart->total,
                                'needs_shipping' => WC()->cart->needs_shipping() ? '1' : '0'
                            ));
                            exit();
                        } else {
                            $_GET['from'] = 'cart';
                            wp_send_json_success(array(
                                'cart_total' => WC()->cart->total,
                                'needs_shipping' => WC()->cart->needs_shipping() ? '1' : '0'
                            ));
                            exit();
                        }
                    } elseif (isset($_GET['from']) && 'product' === $_GET['from']) {
                        try {
                            PPCP_Paypal_Checkout_For_Woocommerce_Product::ppcp_add_to_cart_action();
                            wp_send_json_success(array(
                                'cart_total' => WC()->cart->total,
                                'needs_shipping' => WC()->cart->needs_shipping() ? '1' : '0'
                            ));
                            exit();
                        } catch (Exception $ex) {
                            // Optionally handle exception
                        }
                    } else {
                        PPCP_Paypal_Checkout_For_Woocommerce_Product::ppcp_add_to_cart_action();
                        wp_send_json_success(array(
                            'cart_total' => WC()->cart->total,
                            'needs_shipping' => WC()->cart->needs_shipping() ? '1' : '0'
                        ));
                        exit();
                    }
                    break;

                case "paypal_create_payment_token_sub_change_payment":
                    $this->request->ppcp_paypal_create_payment_token_sub_change_payment();
                    exit();
            }
        }
    }

    public function paypal_billing_details() {
        if (empty($this->checkout_details)) {
            return false;
        }
        ?>
        <div class="ppcp_billing_details">
            <?php if (wc_ship_to_billing_address_only() && WC()->cart->needs_shipping()) : ?>
                <h3><?php esc_html_e('Billing &amp; Shipping', 'woo-paypal-gateway'); ?>&nbsp;&nbsp;&nbsp;<a class="ppcp_edit_billing_address"><?php _e('Edit', 'woo-paypal-gateway'); ?></a></h3>
            <?php else : ?>
                <h3><?php esc_html_e('Billing details', 'woo-paypal-gateway'); ?>&nbsp;&nbsp;&nbsp;<a class="ppcp_edit_billing_address"><?php _e('Edit', 'woo-paypal-gateway'); ?></a></h3>
            <?php
            endif;
            echo WC()->countries->get_formatted_address($this->get_mapped_billing_address($this->checkout_details));
            ?>

        </div>
        <?php
    }

    public function paypal_shipping_details() {
        if (empty($this->checkout_details)) {
            return false;
        }
        ?>
        <div class="ppcp_shipping_details">
            <h3><?php _e('Shipping details', 'woo-paypal-gateway'); ?>&nbsp;&nbsp;&nbsp;<a class="ppcp_edit_shipping_address"><?php _e('Edit', 'woo-paypal-gateway'); ?></a></h3>
            <?php echo WC()->countries->get_formatted_address($this->get_mapped_shipping_address($this->checkout_details)); ?>
        </div><?php
    }

    public function is_full_billing($billing) {
        return !empty($billing['first_name']) && !empty($billing['address_1']) && !empty($billing['postcode']) && !empty($billing['country']);
    }

    public function get_mapped_billing_address($checkout_details) {
        if (empty($checkout_details->payment_source) && empty($checkout_details->payer)) {
            return [];
        }
        $payer = $checkout_details->payer ?? null;
        $payment_source = $checkout_details->payment_source ?? null;
        $phone = '';
        if (!empty($payer->phone->phone_number->national_number)) {
            $phone = $payer->phone->phone_number->national_number;
        } elseif (!empty($_POST['billing_phone'])) {
            $phone = wc_clean($_POST['billing_phone']);
        }
        $billing_address = [
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => '',
            'phone' => $phone,
            'email' => ''
        ];
        $source = $payment_source->google_pay ?? $payment_source->apple_pay ?? $payment_source->card ?? null;
        if ($source) {
            $full_name = $source->name ?? '';
            $name_parts = explode(' ', $full_name, 2);
            $billing_address['first_name'] = $name_parts[0] ?? '';
            $billing_address['last_name'] = $name_parts[1] ?? '';
            $billing_address['address_1'] = $source->card->billing_address->address_line_1 ?? '';
            $billing_address['address_2'] = $source->card->billing_address->address_line_2 ?? '';
            $billing_address['city'] = $source->card->billing_address->admin_area_2 ?? '';
            $billing_address['state'] = $source->card->billing_address->admin_area_1 ?? '';
            $billing_address['postcode'] = $source->card->billing_address->postal_code ?? '';
            $billing_address['country'] = $source->card->billing_address->country_code ?? '';
            $billing_address['email'] = $checkout_details->purchase_units[0]->shipping->email_address ?? '';
        } elseif ($payer) {
            $payer = $checkout_details->payer ?? null;
            $paypal_address = $payer->address ?? ($checkout_details->purchase_units[0]->shipping->address ?? null);
            $paypal_billing = array(
                'first_name' => $payer->name->given_name ?? '',
                'last_name' => $payer->name->surname ?? '',
                'company' => $payer->business_name ?? '',
                'address_1' => $paypal_address->address_line_1 ?? '',
                'address_2' => $paypal_address->address_line_2 ?? '',
                'city' => $paypal_address->admin_area_2 ?? '',
                'state' => $paypal_address->admin_area_1 ?? '',
                'postcode' => $paypal_address->postal_code ?? '',
                'country' => $paypal_address->country_code ?? '',
                'email' => $payer->email_address ?? ($checkout_details->purchase_units[0]->shipping->email_address ?? ''),
                'phone' => $payer->phone->phone_number->national_number ?? '',
            );
            if ($this->is_full_billing($paypal_billing)) {
                $billing_address = $paypal_billing;
            } else {
                $user_billing = array();
                $user = wp_get_current_user();
                if ($user && $user->ID) {
                    $user_billing = array(
                        'first_name' => get_user_meta($user->ID, 'billing_first_name', true),
                        'last_name' => get_user_meta($user->ID, 'billing_last_name', true),
                        'company' => get_user_meta($user->ID, 'billing_company', true),
                        'address_1' => get_user_meta($user->ID, 'billing_address_1', true),
                        'address_2' => get_user_meta($user->ID, 'billing_address_2', true),
                        'city' => get_user_meta($user->ID, 'billing_city', true),
                        'state' => get_user_meta($user->ID, 'billing_state', true),
                        'postcode' => get_user_meta($user->ID, 'billing_postcode', true),
                        'country' => get_user_meta($user->ID, 'billing_country', true),
                        'email' => get_user_meta($user->ID, 'billing_email', true),
                        'phone' => get_user_meta($user->ID, 'billing_phone', true),
                    );
                }
                $billing_address = $this->is_full_billing($user_billing) ? $user_billing : $paypal_billing;
            }
        }
        // Merge shipping into empty billing fields only
        if (empty($billing_address['address_1']) && $this->set_billing_address === true) {
            $shipping_address = $this->get_mapped_shipping_address();
            foreach ($billing_address as $key => $value) {
                if (empty($value) && isset($shipping_address[$key]) && !empty($shipping_address[$key])) {
                    $billing_address[$key] = $shipping_address[$key];
                }
            }
        }
        // State validation
        if (!empty($billing_address['state'])) {
            $validated_state = $this->validate_checkout($billing_address['country'], $billing_address['state'], 'billing');
            if ($validated_state) {
                $billing_address['state'] = $validated_state;
            } else {
                $states_list = WC()->countries->get_states();
                if (!empty($states_list[$billing_address['country']])) {
                    $state_key = array_search($billing_address['state'], $states_list[$billing_address['country']]);
                    $billing_address['state'] = $state_key ?: $billing_address['state'];
                }
            }
        }
        return $billing_address;
    }

    public function get_mapped_shipping_address() {
        if (empty($this->checkout_details->purchase_units[0]) || empty($this->checkout_details->purchase_units[0]->shipping)) {
            return array();
        }
        if (!empty($this->checkout_details->purchase_units[0]->shipping->name->full_name)) {
            $name = explode(' ', $this->checkout_details->purchase_units[0]->shipping->name->full_name);
            $first_name = array_shift($name);
            $last_name = implode(' ', $name);
        } else {
            $first_name = '';
            $last_name = '';
        }
        $shipping_country = !empty($this->checkout_details->purchase_units[0]->shipping->address->country_code) ? $this->checkout_details->purchase_units[0]->shipping->address->country_code : '';
        $shipping_state = !empty($this->checkout_details->purchase_units[0]->shipping->address->admin_area_1) ? $this->checkout_details->purchase_units[0]->shipping->address->admin_area_1 : '';
        if (!empty($shipping_state)) {
            if ($this->validate_checkout($shipping_country, $shipping_state, 'shipping')) {
                $shipping_state = $this->validate_checkout($shipping_country, $shipping_state, 'shipping');
            } else {
                $states_list = WC()->countries->get_states();
                if (isset($shipping_country) && isset($states_list[$shipping_country])) {
                    $state_key = array_search($shipping_state, $states_list[$shipping_country]);
                    $shipping_state = $state_key;
                }
            }
        }
        $result = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'address_1' => !empty($this->checkout_details->purchase_units[0]->shipping->address->address_line_1) ? $this->checkout_details->purchase_units[0]->shipping->address->address_line_1 : '',
            'address_2' => !empty($this->checkout_details->purchase_units[0]->shipping->address->address_line_2) ? $this->checkout_details->purchase_units[0]->shipping->address->address_line_2 : '',
            'city' => !empty($this->checkout_details->purchase_units[0]->shipping->address->admin_area_2) ? $this->checkout_details->purchase_units[0]->shipping->address->admin_area_2 : '',
            'state' => $shipping_state,
            'postcode' => !empty($this->checkout_details->purchase_units[0]->shipping->address->postal_code) ? $this->checkout_details->purchase_units[0]->shipping->address->postal_code : '',
            'country' => $shipping_country,
        );
        if (!empty($this->checkout_details->payer->business_name)) {
            $result['company'] = $this->checkout_details->payer->business_name;
        }
        return $result;
    }

    public function account_registration() {
        $checkout = WC()->checkout();
        if (!is_user_logged_in() && $checkout->enable_signup) {
            if ($checkout->enable_guest_checkout) {
                ?>
                <p class="form-row form-row-wide create-account">
                    <input class="input-checkbox" id="createaccount" <?php checked(( true === $checkout->get_value('createaccount') || ( true === apply_filters('woocommerce_create_account_default_checked', false) )), true) ?> type="checkbox" name="createaccount" value="1" /> <label for="createaccount" class="checkbox"><?php _e('Create an account?', 'woo-paypal-gateway'); ?></label>
                </p>
                <?php
            }
            if (!empty($checkout->checkout_fields['account'])) {
                ?>
                <div class="create-account">
                    <p><?php _e('Create an account by entering the information below. If you are a returning customer please login at the top of the page.', 'woo-paypal-gateway'); ?></p>
                    <?php foreach ($checkout->checkout_fields['account'] as $key => $field) : ?>
                        <?php woocommerce_form_field($key, $field, $checkout->get_value($key)); ?>
                    <?php endforeach; ?>
                    <div class="clear"></div>
                </div>
                <?php
            }
        }
    }

    public function maybe_disable_other_gateways($gateways) {
        if ($this->show_on_checkout_page === false && $this->advanced_card_payments === false && empty($this->checkout_details)) {
            if (isset($gateways['wpg_paypal_checkout'])) {
                unset($gateways['wpg_paypal_checkout']);
            }
        }
        if (is_checkout() && !empty($_GET['paypal_order_id'])) {
            foreach ($gateways as $id => $gateway) {
                if ('wpg_paypal_checkout' !== $id) {
                    unset($gateways[$id]);
                }
            }
            if (function_exists('WC') && WC()->session && method_exists(WC()->session, 'set')) {
                WC()->session->set('chosen_payment_method', 'wpg_paypal_checkout');
            }
        }
        if (is_cart() || ( is_checkout() && !is_checkout_pay_page() )) {
            if (isset($gateways['wpg_paypal_checkout']) && isset(WC()->cart) && ( 0 >= WC()->cart->total )) {
                unset($gateways['wpg_paypal_checkout']);
            }
        }
        return $gateways;
    }

    public function filter_default_address_fields($fields) {
        if (empty($this->checkout_details)) {
            return $fields;
        }
        if ($this->enabled === false) {
            return $fields;
        }
        if (method_exists(WC()->cart, 'needs_shipping') && !WC()->cart->needs_shipping()) {
            $not_required_fields = array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country');
            foreach ($not_required_fields as $not_required_field) {
                if (array_key_exists($not_required_field, $fields)) {
                    $fields[$not_required_field]['required'] = false;
                }
            }
        }
        if (array_key_exists('state', $fields)) {
            $fields['state']['required'] = false;
        }
        return $fields;
    }

    public function copy_checkout_details_to_post() {
        if (empty($this->checkout_details)) {
            $this->checkout_details = ppcp_get_session('ppcp_paypal_transaction_details', false);

            if (empty($this->checkout_details)) {
                if (!empty($_GET['paypal_order_id'])) {
                    $this->checkout_details = $this->request->ppcp_get_checkout_details($_GET['paypal_order_id']);
                }
            }
            if (empty($this->checkout_details)) {
                return;
            }
            ppcp_set_session('ppcp_paypal_transaction_details', $this->checkout_details);
        }
        if (!isset($_POST['payment_method']) || ( 'wpg_paypal_checkout' !== $_POST['payment_method'] ) || empty($this->checkout_details)) {
            return;
        }
        $shipping_details = $this->get_mapped_shipping_address($this->checkout_details);
        $billing_details = $this->get_mapped_billing_address($this->checkout_details);
        $this->update_customer_addresses_from_paypal($shipping_details, $billing_details);
    }

    public function maybe_add_shipping_information($packages) {
        if (empty($this->checkout_details) || (isset($_GET['from']) && $_GET['from'] === 'checkout')) {
            return $packages;
        }
        $destination = $this->get_mapped_shipping_address($this->checkout_details);
        if (!empty($destination) && is_array($destination)) {
            $packages[0]['destination'] = isset($packages[0]['destination']) && is_array($packages[0]['destination']) ? $packages[0]['destination'] : [];
            $packages[0]['destination']['country'] = $destination['country'] ?? '';
            $packages[0]['destination']['state'] = $destination['state'] ?? '';
            $packages[0]['destination']['postcode'] = $destination['postcode'] ?? '';
            $packages[0]['destination']['city'] = $destination['city'] ?? '';
            $packages[0]['destination']['address'] = $destination['address_1'] ?? '';
            $packages[0]['destination']['address_2'] = $destination['address_2'] ?? '';
        }
        return $packages;
    }

    public function init() {
        if (version_compare(WC_VERSION, '3.3', '<')) {
            add_filter('wc_checkout_params', array($this, 'filter_wc_checkout_params'), 10, 1);
        } else {
            add_filter('woocommerce_get_script_data', array($this, 'filter_wc_checkout_params'), 10, 2);
        }
    }

    public function filter_wc_checkout_params($params, $handle = '') {
        if ('wc-checkout' !== $handle && !doing_action('wc_checkout_params')) {
            return $params;
        }
        $fields = array('paypal_order_id', 'paypal_payer_id');
        $params['wc_ajax_url'] = remove_query_arg('wc-ajax', $params['wc_ajax_url']);
        foreach ($fields as $field) {
            if (!empty($_GET[$field])) {
                $params['wc_ajax_url'] = add_query_arg($field, $_GET[$field], $params['wc_ajax_url']);
            }
        }
        $params['wc_ajax_url'] = add_query_arg('wc-ajax', '%%endpoint%%', $params['wc_ajax_url']);
        return $params;
    }

    public function ppcp_session_manager() {
        try {
            if (!empty($_GET['paypal_order_id']) && !empty($_GET['paypal_payer_id'])) {
                if (isset($_GET['from']) && 'product' === $_GET['from']) {
                    if (function_exists('wc_clear_notices')) {
                        wc_clear_notices();
                    }
                }
                ppcp_set_session('ppcp_paypal_order_id', wc_clean($_GET['paypal_order_id']));
                if (empty($this->checkout_details)) {
                    $this->checkout_details = ppcp_get_session('ppcp_paypal_transaction_details', false);
                    if ($this->checkout_details === false) {
                        $this->checkout_details = $this->request->ppcp_get_checkout_details($_GET['paypal_order_id']);
                        ppcp_set_session('ppcp_paypal_transaction_details', $this->checkout_details);
                    }
                }
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_display_order_page() {
        $this->checkout_details = $this->request->ppcp_get_checkout_details($_GET['paypal_order_id']);
        ppcp_set_session('ppcp_paypal_transaction_details', $this->checkout_details);
        if (empty($this->checkout_details)) {
            return false;
        }
        if (!empty($this->checkout_details)) {
            $shipping_details = $this->get_mapped_shipping_address($this->checkout_details);
            $billing_details = $this->get_mapped_billing_address($this->checkout_details);
            $this->update_customer_addresses_from_paypal($shipping_details, $billing_details);
        }
        $order_id = absint(ppcp_get_session('order_awaiting_payment'));
        if (empty($order_id)) {
            $order_id = ppcp_get_session('ppcp_woo_order_id');
        }
        $order = wc_get_order($order_id);
        $this->checkout_details = $this->checkout_details;
        if ($this->paymentaction === 'capture' && !empty($this->checkout_details->status) && $this->checkout_details->status == 'COMPLETED' && $order !== false) {
            $transaction_id = isset($this->checkout_details->purchase_units[0]->payments->captures[0]->id) ? $this->checkout_details->purchase_units['0']->payments->captures[0]->id : '';
            $seller_protection = isset($this->checkout_details->purchase_units[0]->payments->captures[0]->seller_protection->status) ? $this->checkout_details->purchase_units[0]->payments->captures[0]->seller_protection->status : '';
            $payment_source = isset($this->checkout_details->payment_source) ? $this->checkout_details->payment_source : '';
            if (!empty($payment_source->card)) {
                $card_response_order_note = __('Card Details', 'woo-paypal-gateway');
                $card_response_order_note .= "\n";
                $card_response_order_note .= 'Last digits : ' . $payment_source->card->last_digits;
                $card_response_order_note .= "\n";
                $card_response_order_note .= 'Brand : ' . ppcp_readable($payment_source->card->brand);
                $card_response_order_note .= "\n";
                $card_response_order_note .= 'Card type : ' . ppcp_readable($payment_source->card->type);
                $order->add_order_note($card_response_order_note);
            }
            $processor_response = isset($this->checkout_details->purchase_units[0]->payments->captures[0]->processor_response) ? $this->checkout_details->purchase_units[0]->payments->captures[0]->processor_response : '';
            if (!empty($processor_response->avs_code)) {
                $avs_response_order_note = __('Address Verification Result', 'woo-paypal-gateway');
                $avs_response_order_note .= "\n";
                $avs_response_order_note .= $processor_response->avs_code;
                if (isset($this->AVSCodes[$processor_response->avs_code])) {
                    $avs_response_order_note .= ' : ' . $this->AVSCodes[$processor_response->avs_code];
                }
                $order->add_order_note($avs_response_order_note);
            }
            if (!empty($processor_response->cvv_code)) {
                $cvv2_response_code = __('Card Security Code Result', 'woo-paypal-gateway');
                $cvv2_response_code .= "\n";
                $cvv2_response_code .= $processor_response->cvv_code;
                if (isset($this->CVV2Codes[$processor_response->cvv_code])) {
                    $cvv2_response_code .= ' : ' . $this->CVV2Codes[$processor_response->cvv_code];
                }
                $order->add_order_note($cvv2_response_code);
            }
            $currency_code = isset($this->checkout_details->purchase_units[0]->payments->captures[0]->seller_receivable_breakdown->paypal_fee->currency_code) ? $this->checkout_details->purchase_units[0]->payments->captures[0]->seller_receivable_breakdown->paypal_fee->currency_code : '';
            $paypal_fee = isset($this->checkout_details->purchase_units[0]->payments->captures[0]->seller_receivable_breakdown->paypal_fee->value) ? $this->checkout_details->purchase_units[0]->payments->captures[0]->seller_receivable_breakdown->paypal_fee->value : '';
            if ($paypal_fee !== '' && floatval($paypal_fee) > 0) {
                $order->update_meta_data('_paypal_fee', $paypal_fee);
                $order->update_meta_data('_paypal_fee_currency_code', $currency_code);
                $order->save_meta_data();
            }
            $payment_status = isset($this->checkout_details->purchase_units[0]->payments->captures[0]->status) ? $this->checkout_details->purchase_units[0]->payments->captures[0]->status : '';
            if ($payment_status == 'COMPLETED') {
                wpg_set_order_payment_method_title_from_paypal_response($order, $this->checkout_details);
                $order->payment_complete($transaction_id);
                // translators: %1$s is the payment method title, %2$s is the formatted payment status.
                $order->add_order_note(sprintf(__('Payment via %1$s: %2$s.', 'woo-paypal-gateway'), $this->title, ucfirst(strtolower($payment_status))));
            } else {
                $payment_status_reason = $payment_status_reason = isset($this->checkout_details->purchase_units[0]->payments->authorizations[0]->status_details->reason) ? $this->checkout_details->purchase_units[0]->payments->authorizations[0]->status_details->reason : '';
                ppcp_update_woo_order_status($order_id, $payment_status, $payment_status_reason);
            }
            apply_filters('woocommerce_payment_successful_result', array('result' => 'success'), $order_id);
            // translators: %1$s is the payment method title, %2$s is the PayPal transaction ID.
            $order->add_order_note(sprintf(__('%1$s Transaction ID: %2$s', 'woo-paypal-gateway'), $this->title, $transaction_id));
            $order->add_order_note('Seller Protection Status: ' . ppcp_readable($seller_protection));
        } elseif ($this->paymentaction === 'authorize' && !empty($this->checkout_details->status) && $this->checkout_details->status == 'COMPLETED' && $order !== false) {
            $transaction_id = isset($this->checkout_details->purchase_units[0]->payments->authorizations[0]->id) ? $this->checkout_details->purchase_units['0']->payments->authorizations[0]->id : '';
            $seller_protection = isset($this->checkout_details->purchase_units[0]->payments->authorizations[0]->seller_protection->status) ? $this->checkout_details->purchase_units[0]->payments->authorizations[0]->seller_protection->status : '';
            $payment_status = isset($this->checkout_details->purchase_units[0]->payments->authorizations[0]->status) ? $this->checkout_details->purchase_units[0]->payments->authorizations[0]->status : '';
            $payment_status_reason = isset($this->checkout_details->purchase_units[0]->payments->authorizations[0]->status_details->reason) ? $this->checkout_details->purchase_units[0]->payments->authorizations[0]->status_details->reason : '';
            if (!empty($payment_status_reason)) {
                // translators: %1$s is the payment method title, %2$s is the pending reason from PayPal.
                $order->add_order_note(sprintf(__('Payment via %1$s Pending. PayPal reason: %2$s.', 'woo-paypal-gateway'), $this->title, $payment_status_reason));
            }
            apply_filters('woocommerce_payment_successful_result', array('result' => 'success'), $order_id);
            $order->update_meta_data('_transaction_id', $transaction_id);
            $order->update_meta_data('_payment_status', $payment_status);
            $order->update_meta_data('_auth_transaction_id', $transaction_id);
            $order->update_meta_data('_payment_action', $this->paymentaction);
            $order->save_meta_data();
            // translators: %1$s is the payment method title, %2$s is the PayPal transaction ID.
            $order->add_order_note(sprintf(__('Payment via %1$s. Transaction ID: %2$s', 'woo-paypal-gateway'), $this->title, $transaction_id));
            $order->add_order_note('Seller Protection Status: ' . ppcp_readable($seller_protection));
            $order->update_status('on-hold');
            $order->add_order_note(__('Payment authorized. Change payment status to processing or complete to capture funds.', 'woo-paypal-gateway'));
        }
        wpg_clear_ppcp_session_and_cart();
        wp_safe_redirect($order->get_checkout_order_received_url());
        exit();
    }

    public function update_customer_addresses_from_paypal($shipping_details, $billing_details) {
        try {
            if (!empty(WC()->customer)) {
                $customer = WC()->customer;
                if (!empty($billing_details['first_name'])) {
                    $customer->set_billing_first_name($billing_details['first_name']);
                }
                if (!empty($billing_details['last_name'])) {
                    $customer->set_billing_last_name($billing_details['last_name']);
                }
                if (!empty($billing_details['address_1'])) {
                    $customer->set_billing_address_1($billing_details['address_1']);
                    $customer->set_billing_address($billing_details['address_1']);
                }
                if (!empty($billing_details['address_2'])) {
                    $customer->set_billing_address_2($billing_details['address_2']);
                }
                if (!empty($billing_details['city'])) {
                    $customer->set_billing_city($billing_details['city']);
                }
                if (!empty($billing_details['email'])) {
                    $customer->set_email($billing_details['email']);
                    $customer->set_billing_email($billing_details['email']);
                }
                if (!empty($billing_details['postcode'])) {
                    $customer->set_billing_postcode($billing_details['postcode']);
                }
                if (!empty($billing_details['state'])) {
                    $customer->set_billing_state($billing_details['state']);
                }
                if (!empty($billing_details['country'])) {
                    $customer->set_billing_country($billing_details['country']);
                }
                if (!empty($billing_details['phone'])) {
                    $customer->set_billing_phone($billing_details['phone']);
                }
                if (!empty($shipping_details['first_name'])) {
                    $customer->set_shipping_first_name($shipping_details['first_name']);
                }
                if (!empty($shipping_details['last_name'])) {
                    $customer->set_shipping_last_name($shipping_details['last_name']);
                }
                if (!empty($shipping_details['address_1'])) {
                    $customer->set_shipping_address($shipping_details['address_1']);
                    $customer->set_shipping_address_1($shipping_details['address_1']);
                }
                if (!empty($shipping_details['address_2'])) {
                    $customer->set_shipping_address_2($shipping_details['address_2']);
                }
                if (!empty($shipping_details['city'])) {
                    $customer->set_shipping_city($shipping_details['city']);
                }
                if (!empty($shipping_details['postcode'])) {
                    $customer->set_shipping_postcode($shipping_details['postcode']);
                }
                if (!empty($shipping_details['state'])) {
                    $customer->set_shipping_state($shipping_details['state']);
                }
                if (!empty($shipping_details['country'])) {
                    $customer->set_shipping_country($shipping_details['country']);
                }
                $customer->save();
            }
        } catch (Exception $ex) {
            
        }
    }

    public function maybe_start_checkout($data, $errors = null) {
        try {
            if (is_null($errors)) {
                $error_messages = wc_get_notices('error');
                wc_clear_notices();
            } else {
                $error_messages = $errors->get_error_messages();
            }
            if (empty($error_messages)) {
                $this->set_customer_data($_POST);
            } else {
                ob_start();
                wp_send_json_error(array('messages' => $error_messages));
                exit;
            }
        } catch (Exception $ex) {
            
        }
    }

    function set_customer_data($data) {
        try {
            $customer = WC()->customer;
            $billing_first_name = empty($data['billing_first_name']) ? '' : wc_clean($data['billing_first_name']);
            $billing_last_name = empty($data['billing_last_name']) ? '' : wc_clean($data['billing_last_name']);
            $billing_country = empty($data['billing_country']) ? '' : wc_clean($data['billing_country']);
            $billing_address_1 = empty($data['billing_address_1']) ? '' : wc_clean($data['billing_address_1']);
            $billing_address_2 = empty($data['billing_address_2']) ? '' : wc_clean($data['billing_address_2']);
            $billing_city = empty($data['billing_city']) ? '' : wc_clean($data['billing_city']);
            $billing_state = empty($data['billing_state']) ? '' : wc_clean($data['billing_state']);
            $billing_postcode = empty($data['billing_postcode']) ? '' : wc_clean($data['billing_postcode']);
            $billing_phone = empty($data['billing_phone']) ? '' : wc_clean($data['billing_phone']);
            $billing_email = empty($data['billing_email']) ? '' : wc_clean($data['billing_email']);
            if (isset($data['ship_to_different_address'])) {
                $shipping_first_name = empty($data['shipping_first_name']) ? '' : wc_clean($data['shipping_first_name']);
                $shipping_last_name = empty($data['shipping_last_name']) ? '' : wc_clean($data['shipping_last_name']);
                $shipping_country = empty($data['shipping_country']) ? '' : wc_clean($data['shipping_country']);
                $shipping_address_1 = empty($data['shipping_address_1']) ? '' : wc_clean($data['shipping_address_1']);
                $shipping_address_2 = empty($data['shipping_address_2']) ? '' : wc_clean($data['shipping_address_2']);
                $shipping_city = empty($data['shipping_city']) ? '' : wc_clean($data['shipping_city']);
                $shipping_state = empty($data['shipping_state']) ? '' : wc_clean($data['shipping_state']);
                $shipping_postcode = empty($data['shipping_postcode']) ? '' : wc_clean($data['shipping_postcode']);
            } else {
                $shipping_first_name = $billing_first_name;
                $shipping_last_name = $billing_last_name;
                $shipping_country = $billing_country;
                $shipping_address_1 = $billing_address_1;
                $shipping_address_2 = $billing_address_2;
                $shipping_city = $billing_city;
                $shipping_state = $billing_state;
                $shipping_postcode = $billing_postcode;
            }
            $customer->set_shipping_country($shipping_country);
            $customer->set_shipping_address($shipping_address_1);
            $customer->set_shipping_address_2($shipping_address_2);
            $customer->set_shipping_city($shipping_city);
            $customer->set_shipping_state($shipping_state);
            $customer->set_shipping_postcode($shipping_postcode);
            $customer->set_shipping_first_name($shipping_first_name);
            $customer->set_shipping_last_name($shipping_last_name);
            $customer->set_billing_first_name($billing_first_name);
            $customer->set_billing_last_name($billing_last_name);
            $customer->set_billing_country($billing_country);
            $customer->set_billing_address_1($billing_address_1);
            $customer->set_billing_address_2($billing_address_2);
            $customer->set_billing_city($billing_city);
            $customer->set_billing_state($billing_state);
            $customer->set_billing_postcode($billing_postcode);
            $customer->set_billing_phone($billing_phone);
            $customer->set_billing_email($billing_email);
        } catch (Exception $ex) {
            
        }
    }

    public function maybe_clear_session_data() {
        try {
            if (ppcp_has_active_session()) {
                unset(WC()->session->ppcp_session);
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_add_class_order_review_page($classes) {
        try {
            if (!class_exists('WooCommerce') || WC()->session == null) {
                return $classes;
            }
            if (ppcp_has_active_session()) {
                $classes[] = 'ppcp-order-review';
            }
        } catch (Exception $ex) {
            return $classes;
        }
        return $classes;
    }

    public function ppcp_order_review_page_description() {
        if (ppcp_has_active_session()) {
            ?>
            <div class="order_review_page_description">
                <p>
                    <?php
                    echo wp_kses_post(_x("<strong>You're almost done!</strong><br>Review your information before you place your order.", 'Important', 'woo-paypal-gateway'));
                    ?>
                </p>
            </div>
            <?php
        }
    }

    public function ppcp_capture_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $payment_method = $order->get_payment_method();
        $payment_action = $order->get_meta('_payment_action');
        $auth_transaction_id = $order->get_meta('_auth_transaction_id');
        if (('wpg_paypal_checkout' === $payment_method || 'wpg_paypal_checkout_cc' === $payment_method) && $payment_action === 'authorize' && !empty($auth_transaction_id)) {
            $trans_details = $this->request->ppcp_show_details_authorized_payment($auth_transaction_id);
            if ($this->ppcp_is_authorized_only($trans_details)) {
                $this->request->ppcp_capture_authorized_payment($order_id);
            }
        }
    }

    public function ppcp_cancel_authorization($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $payment_method = $order->get_payment_method();
        $transaction_id = $order->get_transaction_id();
        $payment_action = $order->get_meta('_payment_action');
        if (('wpg_paypal_checkout' === $payment_method || 'wpg_paypal_checkout_cc' === $payment_method) && $transaction_id && $payment_action === 'authorize') {
            
        }
    }

    public function ppcp_add_capture_charge_order_action($actions) {
        if (!isset($_REQUEST['post'])) {
            return $actions;
        }
        $order = wc_get_order($_REQUEST['post']);
        if (empty($order)) {
            return $actions;
        }
        $payment_method = $order->get_payment_method();
        $paypal_status = $order->get_meta('_payment_status');
        $payment_action = $order->get_meta('_payment_action');
        if ('wpg_paypal_checkout' !== $payment_method && 'wpg_paypal_checkout_cc' !== $payment_method) {
            return $actions;
        }
        if (!is_array($actions)) {
            $actions = array();
        }
        if ('CREATED' !== $paypal_status && $payment_action === 'authorize') {
            $actions['ppcp_capture_charge'] = esc_html__('Capture Charge', 'woo-paypal-gateway');
        }
        return $actions;
    }

    public function ppcp_maybe_capture_charge($order) {
        if (!is_object($order)) {
            $order = wc_get_order($order);
        }
        $this->ppcp_capture_payment($order->get_id());
        return true;
    }

    public function ppcp_is_authorized_only($trans_details = array()) {
        if (!is_wp_error($trans_details) && !empty($trans_details)) {
            $payment_status = '';
            if (isset($trans_details->status) && !empty($trans_details->status)) {
                $payment_status = $trans_details->status;
            }
            if ('CREATED' === $payment_status) {
                return true;
            }
        }
        return false;
    }

    public function ppcp_clean_url($tag, $handle) {
        if ('ppcp-checkout-js' === $handle) {
            $client_token = '';
            $data_user_id_token = '';
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            /* $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
              if ($this->payment_token->does_paypal_customer_id_exist($this->sandbox)) {
              $id_token = $this->request->ppcp_get_id_token();
              if (!empty($id_token)) {
              $data_user_id_token = "data-user-id-token='{$id_token}'";
              }
              } */
            $data_partner_attribution_id = "data-partner-attribution-id='MBJTechnolabs_SI_SPB'";
            if (is_checkout() && $this->advanced_card_payments && !empty($this->client_token)) {
                $client_token = "data-client-token='{$this->client_token}'";
            }
            $tag = str_replace(
                    ' src=',
                    ' ' . $client_token . ' ' . $data_user_id_token . ' ' . $data_partner_attribution_id . ' data-namespace="wpg_paypal_sdk" src=',
                    $tag
            );
        }
        return $tag;
    }

    public function validate_checkout($country, $state, $sec) {
        $state_value = '';
        $valid_states = WC()->countries->get_states(isset($country) ? $country : ( 'billing' === $sec ? WC()->customer->get_country() : WC()->customer->get_shipping_country() ));
        if (!empty($valid_states) && is_array($valid_states)) {
            $valid_state_values = array_flip(array_map('strtolower', $valid_states));
            if (isset($valid_state_values[strtolower($state)])) {
                $state_value = $valid_state_values[strtolower($state)];
                return $state_value;
            }
        } else {
            return $state;
        }
        if (!empty($valid_states) && is_array($valid_states) && sizeof($valid_states) > 0) {
            if (!in_array($state, array_keys($valid_states))) {
                return false;
            } else {
                return $state;
            }
        }

        return $state_value;
    }

    public function ppcp_update_checkout_field_details() {
        if (empty($this->checkout_details)) {
            $this->checkout_details = ppcp_get_session('ppcp_paypal_transaction_details', false);
            if (empty($this->checkout_details)) {
                if (!empty($_GET['paypal_order_id'])) {
                    $this->checkout_details = $this->request->ppcp_get_checkout_details($_GET['paypal_order_id']);
                }
            }
            if (empty($this->checkout_details)) {
                return;
            }
            ppcp_set_session('ppcp_paypal_transaction_details', $this->checkout_details);
        }
        $states_list = WC()->countries->get_states();
        if (!empty($this->checkout_details)) {
            $shipping_address = $this->get_mapped_shipping_address();
            if (!empty($shipping_address)) {
                foreach ($shipping_address as $field => $value) {
                    if (!empty($value)) {
                        if ('state' == $field) {
                            if ($this->validate_checkout($shipping_address['country'], $value, 'shipping')) {
                                $_POST['shipping_' . $field] = $this->validate_checkout($shipping_address['country'], $value, 'shipping');
                            } else {
                                if (isset($shipping_address['country']) && isset($states_list[$shipping_address['country']])) {
                                    $state_key = array_search($value, $states_list[$shipping_address['country']]);
                                    $_POST['shipping_' . $field] = $state_key;
                                } else {
                                    $_POST['shipping_' . $field] = '';
                                }
                            }
                        } else {
                            $_POST['shipping_' . $field] = wc_clean(stripslashes($value));
                        }
                    }
                }
            }
            $billing_address = $this->get_mapped_billing_address($this->checkout_details);
            if (!empty($billing_address)) {
                foreach ($billing_address as $field => $value) {
                    if (!empty($value)) {
                        if ('state' == $field) {
                            if ($this->validate_checkout($billing_address['country'], $value, 'billing')) {
                                $_POST['billing_' . $field] = $this->validate_checkout($billing_address['country'], $value, 'billing');
                            } else {
                                if (isset($billing_address['country']) && isset($states_list[$billing_address['country']])) {
                                    $state_key = array_search($value, $states_list[$billing_address['country']]);
                                    $_POST['billing_' . $field] = $state_key;
                                } else {
                                    $_POST['billing_' . $field] = '';
                                }
                            }
                        } else {
                            $_POST['billing_' . $field] = wc_clean(stripslashes($value));
                        }
                    }
                }
            }
        }
    }

    public function ppcp_cancel_button() {
        if (ppcp_has_active_session()) {
            $order_button_text = _x('Cancel order', 'Important', 'woo-paypal-gateway');
            $cancel_order_url = add_query_arg(array('ppcp_action' => 'cancel_order', 'utm_nooverride' => '1', 'from' => 'checkout'), WC()->api_request_url('PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager'));
            echo apply_filters('ppcp_review_order_cance_button_html', '<a class="button alt ppcp_cancel" name="woocommerce_checkout_cancel_order" href="' . esc_attr($cancel_order_url) . '" >' . $order_button_text . '</a>');
        }
    }

    public function ppcp_create_webhooks() {
        if (false === get_transient('ppcp_is_webhook_process_started')) {
            if (ppcp_is_local_server() === false) {
                $webhook_id = get_option($this->webhook_id, '');
                if (empty($webhook_id)) {
                    $this->request->ppcp_create_webhooks_request();
                }
                set_transient('ppcp_is_webhook_process_started', 'done', 24 * HOUR_IN_SECONDS);
            }
        }
    }

    public function ppcp_handle_webhook_request() {
        $this->request->ppcp_handle_webhook_request_handler();
    }

    public function ppcp_cc_capture() {
        try {
            $ppcp_paypal_order_id = ppcp_get_session('ppcp_paypal_order_id');
            if (!empty($ppcp_paypal_order_id)) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                $this->request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
                $order_id = absint(WC()->session->get('order_awaiting_payment'));
                if (empty($order_id)) {
                    $order_id = ppcp_get_session('ppcp_woo_order_id');
                }
                $order = wc_get_order($order_id);
                if ($order === false) {
                    if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Checkout')) {
                        include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-checkout.php';
                    }
                    $api_response = $this->request->ppcp_get_checkout_details($ppcp_paypal_order_id);
                    $ppcp_checkout = PPCP_Paypal_Checkout_For_Woocommerce_Checkout::instance();
                    $order_id = $ppcp_checkout->ppcp_create_order();
                    if (0 === wc_notice_count('error')) {
                        $order = wc_get_order($order_id);
                        $this->request->ppcp_update_order($order);
                    } else {
                        if (ob_get_length())
                            ob_end_clean();
                        $error_messages_data = wc_get_notices('error');
                        $error_messages = array();
                        foreach ($error_messages_data as $key => $value) {
                            $error_messages[] = $value['notice'];
                        }
                        wc_clear_notices();
                        ob_start();
                        wp_send_json_error(array('messages' => $error_messages));
                        exit;
                    }
                }
                $payment_method = $order->get_payment_method();
                $requires_liability_shift = ( 'wpg_paypal_checkout_cc' === $payment_method );
                $is_success = false;
                if ($requires_liability_shift) {
                    $api_response = $this->request->ppcp_get_checkout_details($ppcp_paypal_order_id);
                    $liability_shift_result = $this->ppcp_liability_shift($order, $api_response);
                    switch ($liability_shift_result) {
                        case 1:
                            $is_success = ( $this->paymentaction === 'capture' ) ? $this->request->ppcp_order_capture_request($order_id, false) : $this->request->ppcp_order_auth_request($order_id);
                            $order->update_meta_data('_payment_action', $this->paymentaction);
                            $order->update_meta_data('enviorment', $this->sandbox ? 'sandbox' : 'live');
                            $order->save_meta_data();
                            break;
                        case 2:
                            if (function_exists('wc_add_notice')) {
                                wc_add_notice(
                                        __('We cannot process your order with the payment information that you provided. Please use an alternate payment method.', 'woo-paypal-gateway'),
                                        'error'
                                );
                            }
                            break;
                        case 3:
                            if (function_exists('wc_add_notice')) {
                                wc_add_notice(
                                        __('Something went wrong. Please try again.', 'woo-paypal-gateway'),
                                        'error'
                                );
                            }
                            break;
                    }
                } else {
                    $is_success = ( $this->paymentaction === 'capture' ) ? $this->request->ppcp_order_capture_request($order_id, false) : $this->request->ppcp_order_auth_request($order_id);
                    $order->update_meta_data('_payment_action', $this->paymentaction);
                    $order->update_meta_data('enviorment', $this->sandbox ? 'sandbox' : 'live');
                    $order->save_meta_data();
                }
                unset(WC()->session->ppcp_session);
                if (ob_get_length()) {
                    ob_end_clean();
                }
                if ($is_success) {
                    wpg_clear_ppcp_session_and_cart();
                    wp_send_json_success(array(
                        'result' => 'success',
                        'redirect' => apply_filters('woocommerce_get_return_url', $order->get_checkout_order_received_url(), $order),
                    ));
                } else {
                    wp_send_json_success(array(
                        'result' => 'failure',
                        'redirect' => wc_get_checkout_url(),
                    ));
                }
                exit();
            }
        } catch (Exception $ex) {
            
        }
    }

    public function ppcp_liability_shift($order, $response_object) {
        if (!empty($response_object)) {
            $response = json_decode(json_encode($response_object), true);
            if (!empty($response['payment_source']['card']['authentication_result']['liability_shift'])) {
                $LiabilityShift = isset($response['payment_source']['card']['authentication_result']['liability_shift']) ? strtoupper($response['payment_source']['card']['authentication_result']['liability_shift']) : '';
                $EnrollmentStatus = isset($response['payment_source']['card']['authentication_result']['three_d_secure']['enrollment_status']) ? strtoupper($response['payment_source']['card']['authentication_result']['three_d_secure']['enrollment_status']) : '';
                $AuthenticationResult = isset($response['payment_source']['card']['authentication_result']['three_d_secure']['authentication_status']) ? strtoupper($response['payment_source']['card']['authentication_result']['three_d_secure']['authentication_status']) : '';
                $liability_shift_order_note = __('3D Secure response', 'woo-paypal-gateway');
                $liability_shift_order_note .= "\n";
                $liability_shift_order_note .= 'Liability Shift : ' . ppcp_readable($LiabilityShift);
                $liability_shift_order_note .= "\n";
                $liability_shift_order_note .= 'Enrollment Status : ' . $EnrollmentStatus;
                $liability_shift_order_note .= "\n";
                $liability_shift_order_note .= 'Authentication Status : ' . $AuthenticationResult;
                if ($order) {
                    $order->add_order_note($liability_shift_order_note);
                }
                if ($LiabilityShift === 'POSSIBLE') {
                    return $this->procceed;
                }
                if ($LiabilityShift === 'UNKNOWN') {
                    return $this->retry;
                }
                if ($LiabilityShift === 'NO') {
                    if ($EnrollmentStatus === 'B' && empty($AuthenticationResult)) {
                        return $this->procceed;
                    }
                    if ($EnrollmentStatus === 'U' && empty($AuthenticationResult)) {
                        return $this->procceed;
                    }
                    if ($EnrollmentStatus === 'N' && empty($AuthenticationResult)) {
                        return $this->procceed;
                    }
                    if ($AuthenticationResult === 'R') {
                        return $this->reject;
                    }
                    if ($AuthenticationResult === 'N') {
                        return $this->reject;
                    }
                    if ($AuthenticationResult === 'U') {
                        return $this->retry;
                    }
                    if (!$AuthenticationResult) {
                        return $this->retry;
                    }
                    return $this->procceed;
                }
                return $this->procceed;
            } else {
                return $this->procceed;
            }
        }
        return $this->retry;
    }

    public function ppcp_add_order_id() {
        global $wp;
        $order_id = absint($wp->query_vars['order-pay']);
        ?>
        <input type="hidden" name="woo_order_id" value="<?php echo $order_id; ?>" />
        <?php
    }

    public function ppcp_paypal_button_style_properties() {
        $this->disable_funding = array();
        $this->style_layout = 'vertical';
        $this->style_color = 'gold';
        $this->style_shape = 'rect';
        $this->style_label = 'paypal';
        $this->button_size = 'responsive';
        $this->button_height = '48';
        $this->button_class = $this->device_class . ' ' . $this->button_size;
        $this->google_pay_style_label = 'plain';
        $this->google_pay_style_color = 'black';
        $this->google_pay_style_shape = 'rect';
        $this->apple_pay_style_label = 'plain';
        $this->apple_pay_style_color = 'black';
        $this->apple_pay_style_shape = 'rect';
        if (is_product()) {
            $this->disable_funding = $this->ppcp_get_settings('product_disallowed_funding_methods', array());
            $this->style_layout = $this->ppcp_get_settings('product_button_layout', 'horizontal');
            $this->style_color = $this->ppcp_get_settings('product_button_color', 'gold');
            $this->style_shape = $this->ppcp_get_settings('product_button_shape', 'rect');
            $this->style_label = $this->ppcp_get_settings('product_button_label', 'paypal');
            $this->button_size = $this->ppcp_get_settings('product_button_size', 'medium');
            $this->button_height = $this->ppcp_get_settings('product_button_height', '48');
            $this->button_class = $this->device_class . ' ' . $this->button_size;
            $this->google_pay_style_label = $this->ppcp_get_settings('google_pay_product_page_label', 'plain');
            $this->google_pay_style_color = $this->ppcp_get_settings('google_pay_product_page_color', 'black');
            $this->google_pay_style_shape = $this->ppcp_get_settings('google_pay_product_page_shape', 'rect');
            $this->apple_pay_style_label = $this->ppcp_get_settings('apple_pay_product_page_label', 'plain');
            $this->apple_pay_style_color = $this->ppcp_get_settings('apple_pay_product_page_color', 'black');
            $this->apple_pay_style_shape = $this->ppcp_get_settings('apple_pay_product_page_shape', 'rect');
        } elseif (is_cart()) {
            $this->disable_funding = $this->ppcp_get_settings('cart_disallowed_funding_methods', array());
            $this->style_layout = $this->ppcp_get_settings('cart_button_layout', 'vertical');
            $this->style_color = $this->ppcp_get_settings('cart_button_color', 'gold');
            $this->style_shape = $this->ppcp_get_settings('cart_button_shape', 'rect');
            $this->style_label = $this->ppcp_get_settings('cart_button_label', 'paypal');
            $this->button_size = $this->ppcp_get_settings('cart_button_size', 'responsive');
            $this->button_height = $this->ppcp_get_settings('cart_button_height', '48');
            $this->button_class = $this->device_class . ' ' . $this->button_size;
            $this->google_pay_style_label = $this->ppcp_get_settings('google_pay_cart_page_label', 'plain');
            $this->google_pay_style_color = $this->ppcp_get_settings('google_pay_cart_page_color', 'black');
            $this->google_pay_style_shape = $this->ppcp_get_settings('google_pay_cart_page_shape', 'rect');
            $this->apple_pay_style_label = $this->ppcp_get_settings('apple_pay_cart_page_label', 'plain');
            $this->apple_pay_style_color = $this->ppcp_get_settings('apple_pay_cart_page_color', 'black');
            $this->apple_pay_style_shape = $this->ppcp_get_settings('apple_pay_cart_page_shape', 'rect');
        } elseif (is_checkout() || is_checkout_pay_page()) {
            $this->disable_funding = $this->ppcp_get_settings('checkout_disallowed_funding_methods', array());
            $this->style_layout = $this->ppcp_get_settings('checkout_button_layout', 'vertical');
            $this->style_color = $this->ppcp_get_settings('checkout_button_color', 'gold');
            $this->style_shape = $this->ppcp_get_settings('checkout_button_shape', 'rect');
            $this->style_label = $this->ppcp_get_settings('checkout_button_label', 'paypal');
            $this->button_size = $this->ppcp_get_settings('checkout_button_size', 'responsive');
            $this->button_height = $this->ppcp_get_settings('checkout_button_height', '48');
            $this->button_class = $this->device_class . ' ' . $this->button_size;
            $this->google_pay_style_label = $this->ppcp_get_settings('google_pay_checkout_page_label', 'plain');
            $this->google_pay_style_color = $this->ppcp_get_settings('google_pay_checkout_page_color', 'black');
            $this->google_pay_style_shape = $this->ppcp_get_settings('google_pay_checkout_page_shape', 'rect');
            $this->apple_pay_style_label = $this->ppcp_get_settings('apple_pay_checkout_page_label', 'plain');
            $this->apple_pay_style_color = $this->ppcp_get_settings('apple_pay_checkout_page_color', 'black');
            $this->apple_pay_style_shape = $this->ppcp_get_settings('apple_pay_checkout_page_shape', 'rect');
        }
        $this->express_checkout_style_layout = $this->ppcp_get_settings('express_checkout_button_layout', 'horizontal');
        $this->express_checkout_style_color = $this->ppcp_get_settings('express_checkout_button_color', 'gold');
        $this->express_checkout_style_shape = $this->ppcp_get_settings('express_checkout_button_shape', 'rect');
        $this->express_checkout_style_label = $this->ppcp_get_settings('express_checkout_button_label', 'paypal');
        $this->express_checkout_button_height = $this->ppcp_get_settings('express_checkout_button_height', '40');
        $this->google_pay_express_checkout_style_label = $this->ppcp_get_settings('google_pay_express_checkout_page_label', 'plain');
        $this->google_pay_express_checkout_style_color = $this->ppcp_get_settings('google_pay_express_checkout_page_color', 'black');
        $this->google_pay_express_checkout_style_shape = $this->ppcp_get_settings('google_pay_express_checkout_page_shape', 'rect');
        $this->apple_pay_express_checkout_style_label = $this->ppcp_get_settings('apple_pay_express_checkout_page_label', 'plain');
        $this->apple_pay_express_checkout_style_color = $this->ppcp_get_settings('apple_pay_express_checkout_page_color', 'black');
        $this->apple_pay_express_checkout_style_shape = $this->ppcp_get_settings('apple_pay_express_checkout_page_shape', 'rect');
        $this->mini_cart_style_layout = $this->ppcp_get_settings('mini_cart_button_layout', 'horizontal');
        $this->mini_cart_style_color = $this->ppcp_get_settings('mini_cart_button_color', 'gold');
        $this->mini_cart_style_shape = $this->ppcp_get_settings('mini_cart_button_shape', 'rect');
        $this->mini_cart_style_label = $this->ppcp_get_settings('mini_cart_button_label', 'paypal');
        $this->mini_cart_button_size = $this->ppcp_get_settings('mini_cart_button_size', 'medium');
        $this->mini_cart_button_height = $this->ppcp_get_settings('mini_cart_button_height', '38');
        $this->mini_cart_button_class = $this->device_class . ' ' . $this->mini_cart_button_size;
        $this->google_pay_mini_cart_style_label = $this->ppcp_get_settings('google_pay_mini_cart_page_label', 'plain');
        $this->google_pay_mini_cart_style_color = $this->ppcp_get_settings('google_pay_mini_cart_page_color', 'black');
        $this->google_pay_mini_cart_style_shape = $this->ppcp_get_settings('google_pay_mini_cart_page_shape', 'rect');
        $this->apple_pay_mini_cart_style_label = $this->ppcp_get_settings('apple_pay_mini_cart_page_label', 'plain');
        $this->apple_pay_mini_cart_style_color = $this->ppcp_get_settings('apple_pay_mini_cart_page_color', 'black');
        $this->apple_pay_mini_cart_style_shape = $this->ppcp_get_settings('apple_pay_mini_cart_page_shape', 'rect');
    }

    public function ppcp_prevent_add_to_cart_woo_action() {
        if (isset($_REQUEST['ppcp-add-to-cart'])) {
            if (isset($_REQUEST['add-to-cart'])) {
                unset($_REQUEST['add-to-cart']);
                unset($_POST['add-to-cart']);
            }
        }
    }

    public function ppcp_prepare_order_data() {
        // Use generic session key names
        $session_billing = WC()->session->get('ppcp_billing_address');
        $session_shipping = WC()->session->get('ppcp_shipping_address');

        // Load fallback checkout details if session is empty
        if (empty($this->checkout_details)) {
            $this->checkout_details = ppcp_get_session('ppcp_paypal_transaction_details', false);

            if (empty($this->checkout_details)) {
                $ppcp_order_id = ppcp_get_session('ppcp_paypal_order_id');
                if (!empty($ppcp_order_id)) {
                    $this->checkout_details = $this->request->ppcp_get_checkout_details($ppcp_order_id);
                }
            }

            if (!empty($this->checkout_details)) {
                ppcp_set_session('ppcp_paypal_transaction_details', $this->checkout_details);
            }
        }

        // Final source: priority → session > checkout_details
        $billing_address = is_array($session_billing) && !empty($session_billing) ? $session_billing : $this->get_mapped_billing_address($this->checkout_details ?? [], !$this->set_billing_address);

        $shipping_address = is_array($session_shipping) && !empty($session_shipping) ? $session_shipping : $this->get_mapped_shipping_address($this->checkout_details ?? []);

        // Build order data
        $order_data = [
            'terms' => 1,
            'createaccount' => 0,
            'payment_method' => 'wpg_paypal_checkout',
            'ship_to_different_address' => false,
            'order_comments' => '',
            'shipping_method' => '',
            // Billing from selected source
            'billing_first_name' => $billing_address['first_name'] ?? '',
            'billing_last_name' => $billing_address['last_name'] ?? '',
            'billing_email' => $billing_address['email'] ?? '',
            'billing_company' => $billing_address['company'] ?? '',
            'billing_address_1' => $billing_address['address_1'] ?? '',
            'billing_address_2' => $billing_address['address_2'] ?? '',
            'billing_city' => $billing_address['city'] ?? '',
            'billing_state' => $billing_address['state'] ?? '',
            'billing_postcode' => $billing_address['postcode'] ?? '',
            'billing_country' => $billing_address['country'] ?? '',
            'billing_phone' => $billing_address['phone'] ?? '',
            // Shipping from selected source
            'shipping_first_name' => $shipping_address['first_name'] ?? '',
            'shipping_last_name' => $shipping_address['last_name'] ?? '',
            'shipping_company' => $shipping_address['company'] ?? '',
            'shipping_address_1' => $shipping_address['address_1'] ?? '',
            'shipping_address_2' => $shipping_address['address_2'] ?? '',
            'shipping_city' => $shipping_address['city'] ?? '',
            'shipping_state' => $shipping_address['state'] ?? '',
            'shipping_postcode' => $shipping_address['postcode'] ?? '',
            'shipping_country' => $shipping_address['country'] ?? '',
            'shipping_phone' => $shipping_address['phone'] ?? '',
        ];

        // Clean up sessions after use
        WC()->session->__unset('ppcp_billing_address');
        WC()->session->__unset('ppcp_shipping_address');

        return $order_data;
    }

    public function ppcp_woocommerce_get_checkout_url($checkout_url) {
        try {
            if (is_checkout() && ppcp_has_active_session()) {
                $checkout_url_parameter = array();
                if (isset($_GET['paypal_order_id'])) {
                    $checkout_url_parameter['paypal_order_id'] = wc_clean($_GET['paypal_order_id']);
                }
                if (isset($_GET['paypal_payer_id'])) {
                    $checkout_url_parameter['paypal_payer_id'] = wc_clean($_GET['paypal_payer_id']);
                }
                if (isset($_GET['from'])) {
                    $checkout_url_parameter['from'] = wc_clean($_GET['from']);
                }
                $checkout_url = add_query_arg($checkout_url_parameter, untrailingslashit($checkout_url));
            }
        } catch (Exception $ex) {
            return $checkout_url;
        }
        return $checkout_url;
    }

    public function ppcp_block_set_address() {
        if (empty($this->checkout_details)) {
            $this->checkout_details = ppcp_get_session('ppcp_paypal_transaction_details', false);
            if (empty($this->checkout_details)) {
                if (!empty($_GET['paypal_order_id'])) {
                    $this->checkout_details = $this->request->ppcp_get_checkout_details($_GET['paypal_order_id']);
                }
            }
            if (empty($this->checkout_details)) {
                return;
            }
            ppcp_set_session('ppcp_paypal_transaction_details', $this->checkout_details);
        }
        $shipping_details = $this->get_mapped_shipping_address($this->checkout_details);
        $billing_details = $this->get_mapped_billing_address($this->checkout_details);
        $this->update_customer_addresses_from_paypal($shipping_details, $billing_details);
    }

    public function ppcp_admin_init() {
        if (!function_exists('cacsp_load_textdomain')) {
            return;
        }
        $cacsp_option_always_scripts = get_option('cacsp_option_always_scripts', '');
        if (empty($cacsp_option_always_scripts)) {
            update_option('cacsp_option_always_scripts', 'https://www.paypal.com/');
            return;
        }
        if (strpos($cacsp_option_always_scripts, 'https://www.paypal.com/') === false) {
            $cacsp_option_always_scripts .= 'https://www.paypal.com/';
            update_option('cacsp_option_always_scripts', $cacsp_option_always_scripts, true);
        }
    }

    public function is_google_pay_enable_for_page($page = '') {
        if ($this->enabled_google_pay === false) {
            return false;
        }
        if (empty($page)) {
            return false;
        }
        if (empty($this->google_pay_pages)) {
            return false;
        }
        if (in_array($page, $this->google_pay_pages)) {
            return true;
        }
        return false;
    }

    public function is_apple_pay_enable_for_page($page = '') {
        if ($this->enabled_apple_pay === false) {
            return false;
        }
        if (empty($page)) {
            return false;
        }
        if (empty($this->apple_pay_pages)) {
            return false;
        }
        if (in_array($page, $this->apple_pay_pages)) {
            return true;
        }
        return false;
    }

    public function wpg_ppcp_save_payment_method_details($woo_order_id, $api_response) {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token')) {
                require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-payment-token.php';
            }
            $this->payment_token = PPCP_Paypal_Checkout_For_Woocommerce_Payment_Token::instance();
            $wpg_payment_method = ppcp_get_session('wpg_payment_method');
            if (empty($wpg_payment_method)) {
                return;
            }
            $order = wc_get_order($woo_order_id);
            $order->get_meta('_wpg_ppcp_used_payment_method', $wpg_payment_method);
            if (isset($api_response['payment_source'][$wpg_payment_method]['attributes']['vault']['customer']['id'])) {
                $customer_id = $api_response['payment_source'][$wpg_payment_method]['attributes']['vault']['customer']['id'];
                update_post_meta($woo_order_id, '_paypal_customer_id', $customer_id);
                $this->payment_token->add_paypal_customer_id($customer_id, $this->sandbox);
            }
            $this->payment_token->ppcp_wc_save_payment_token($woo_order_id, $api_response);
        } catch (Exception $ex) {
            
        }
    }

    public function wpg_ppcp_short_gateway($methods) {
        if (isset($_GET['paypal_order_id'])) {
            return $methods;
        }
        return $methods;
    }

    public function wpg_ppcp_currency($ppcp_currency) {
        if (class_exists('Yay_Currency\Helpers\YayCurrencyHelper')) {
            $apply_currency = Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
            if (isset($apply_currency['currency'])) {
                return $apply_currency['currency'];
            }
        }
        return $ppcp_currency;
    }

    public function ppcp_get_updated_total() {
        if (null === WC()->cart || !WC()->cart->get_cart_contents_count()) {
            wc_load_cart();
        }
        $shipping_address = !empty($_POST['shipping_address']) ? json_decode(stripslashes(sanitize_textarea_field(wp_unslash($_POST['shipping_address']))), true) : [];
        $billing_address = !empty($_POST['billing_address']) ? json_decode(stripslashes(sanitize_textarea_field(wp_unslash($_POST['billing_address']))), true) : [];
        $split_name = function ($full_name) {
            if (!is_string($full_name) || trim($full_name) === '') {
                return ['', ''];
            }
            $parts = preg_split('/\s+/', trim($full_name));
            $first = $parts[0] ?? '';
            $last = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
            return [$first, $last];
        };
        WC()->session->__unset('ppcp_billing_address');
        WC()->session->__unset('ppcp_shipping_address');
        if (!empty($shipping_address)) {
            [$first_name, $last_name] = $split_name($shipping_address['name'] ?? '');
            if (empty($last_name)) {
                $last_name = $shipping_address['surname'] ?? '';
            }
            WC()->customer->set_shipping_first_name($first_name);
            WC()->customer->set_shipping_last_name($last_name);
            WC()->customer->set_shipping_address_1($shipping_address['address1'] ?? '');
            WC()->customer->set_shipping_address_2($shipping_address['address2'] ?? '');
            WC()->customer->set_shipping_city($shipping_address['city'] ?? '');
            WC()->customer->set_shipping_postcode($shipping_address['postcode'] ?? '');
            WC()->customer->set_shipping_state($shipping_address['state'] ?? '');
            WC()->customer->set_shipping_country($shipping_address['country'] ?? '');
            WC()->session->set('customer_shipping_country', $shipping_address['country'] ?? '');
            WC()->session->set('customer_shipping_state', $shipping_address['state'] ?? '');
            WC()->session->set('customer_shipping_postcode', $shipping_address['postcode'] ?? '');
            WC()->session->set('customer_shipping_city', $shipping_address['city'] ?? '');
            WC()->session->set('customer_shipping_address', $shipping_address['address1'] ?? '');
            WC()->session->set('customer_shipping_address_1', $shipping_address['address1'] ?? '');
            WC()->session->set('customer_shipping_address_2', $shipping_address['address2'] ?? '');
            WC()->session->set('ppcp_shipping_address', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'address_1' => $shipping_address['address1'] ?? '',
                'address_2' => $shipping_address['address2'] ?? '',
                'city' => $shipping_address['city'] ?? '',
                'postcode' => $shipping_address['postcode'] ?? '',
                'state' => $shipping_address['state'] ?? '',
                'country' => $shipping_address['country'] ?? '',
                'phone' => $shipping_address['phoneNumber'] ?? '',
            ]);
        }
        if (!empty($billing_address)) {
            [$first_name, $last_name] = $split_name($billing_address['name'] ?? '');
            if (empty($last_name)) {
                $last_name = $billing_address['surname'] ?? '';
            }
            WC()->customer->set_billing_first_name($first_name);
            WC()->customer->set_billing_last_name($last_name);
            WC()->customer->set_billing_address_1($billing_address['address1'] ?? '');
            WC()->customer->set_billing_address_2($billing_address['address2'] ?? '');
            WC()->customer->set_billing_city($billing_address['city'] ?? '');
            WC()->customer->set_billing_postcode($billing_address['postcode'] ?? '');
            WC()->customer->set_billing_state($billing_address['state'] ?? '');
            WC()->customer->set_billing_country($billing_address['country'] ?? '');
            WC()->customer->set_billing_email($billing_address['emailAddress'] ?? '');
            WC()->customer->set_billing_phone($billing_address['phoneNumber'] ?? '');
            WC()->session->set('customer_billing_country', $billing_address['country'] ?? '');
            WC()->session->set('customer_billing_state', $billing_address['state'] ?? '');
            WC()->session->set('customer_billing_postcode', $billing_address['postcode'] ?? '');
            WC()->session->set('customer_billing_city', $billing_address['city'] ?? '');
            WC()->session->set('customer_billing_address', $billing_address['address1'] ?? '');
            WC()->session->set('customer_billing_address_1', $billing_address['address1'] ?? '');
            WC()->session->set('customer_billing_address_2', $billing_address['address2'] ?? '');
            WC()->session->set('ppcp_billing_address', [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $billing_address['emailAddress'] ?? '',
                'address_1' => $billing_address['address1'] ?? '',
                'address_2' => $billing_address['address2'] ?? '',
                'city' => $billing_address['city'] ?? '',
                'postcode' => $billing_address['postcode'] ?? '',
                'state' => $billing_address['state'] ?? '',
                'country' => $billing_address['country'] ?? '',
                'phone' => $billing_address['phoneNumber'] ?? '',
            ]);
        }
        try {
            WC()->customer->set_calculated_shipping(false);
            WC()->customer->save();
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
            $raw_total = WC()->cart->get_total('edit');
            wp_send_json_success([
                'total' => (string) $raw_total,
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Failed to update cart total.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function ppcp_get_product_total() {
        $product_id = absint($_POST['product_id'] ?? 0);
        $qty = floatval($_POST['quantity'] ?? 1);
        $product = wc_get_product($product_id);

        if (!$product || $qty <= 0) {
            wp_send_json_error(['message' => 'Invalid product or quantity']);
        }

        // Check if the product or variation is already in the cart
        $is_in_cart = false;
        if (WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $cart_product_id = (int) $cart_item['product_id'];
                $cart_variation_id = (int) $cart_item['variation_id'];

                if ($cart_product_id === $product_id || $cart_variation_id === $product_id) {
                    $is_in_cart = true;
                    break;
                }
            }
        }

        // Only include product price if NOT already in cart
        $product_price = $is_in_cart ? 0 : wc_get_price_to_display($product) * $qty;
        $cart_total = WC()->cart ? WC()->cart->get_cart_contents_total() : 0;
        $combined = wc_format_decimal($product_price + $cart_total, 2);

        wp_send_json_success([
            'product_price' => wc_format_decimal($product_price, 2),
            'cart_total' => wc_format_decimal($cart_total, 2),
            'combined_total' => $combined,
        ]);
    }

    public function should_enable_google_pay_for_page($page) {
        // Condition 1: Check if Google Pay is explicitly enabled for the current page
        if ($this->is_google_pay_enable_for_page($page)) {
            return true;
        }

        // Condition 2: If the current page is "product" or "cart"
        // AND Google Pay is enabled for "mini_cart", treat it as enabled
        if (in_array($page, ['product', 'cart'], true) && $this->is_google_pay_enable_for_page('mini_cart')) {
            return true;
        }

        // Condition 3: If the current page is "checkout"
        // AND Express Checkout is enabled, then allow Google Pay
        if ($page === 'checkout' && $this->is_google_pay_enable_for_page('express_checkout')) {
            return true;
        }

        // Default: Google Pay is not enabled for this context
        return false;
    }

    public function should_enable_apple_pay_for_page($page) {
        // Condition 1: Check if Apple Pay is explicitly enabled for the current page
        if ($this->is_apple_pay_enable_for_page($page)) {
            return true;
        }

        // Condition 2: If the current page is "product" or "cart"
        // AND Apple Pay is enabled for "mini_cart", treat it as enabled
        if (in_array($page, ['product', 'cart'], true) && $this->is_apple_pay_enable_for_page('mini_cart')) {
            return true;
        }

        // Condition 3: If the current page is "checkout"
        // AND Express Checkout is enabled, then allow Apple Pay
        if ($page === 'checkout' && $this->is_apple_pay_enable_for_page('express_checkout')) {
            return true;
        }

        // Default: Apple Pay is not enabled for this context
        return false;
    }

    public function wpg_remove_paypal_order_id_from_return_url($return_url, $order) {
        if (!$order instanceof WC_Order) {
            return $return_url;
        }
        return remove_query_arg('paypal_order_id', $return_url);
    }
}
