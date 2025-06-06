<?php

defined('ABSPATH') || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_Pay_Later {

    public $setting_obj;
    public $api_log;
    public $settings;
    public $title;
    public $enabled;
    public $is_sandbox;
    public $client_id;
    public $secret;
    public $enabled_pay_later_messaging;
    public $pay_later_messaging_page_type;
    public $pay_later_messaging_cart_shortcode;
    public $pay_later_messaging_home_shortcode;
    public $pay_later_messaging_category_shortcode;
    public $pay_later_messaging_product_shortcode;
    public $pay_later_messaging_payment_shortcode;
    protected static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->ppcp_load_class();
        $this->ppcp_get_properties();
        $this->ppcp_pay_later_messaging_properties();
        $this->ppcp_add_hooks();
    }

    public function ppcp_load_class() {
        try {
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-settings.php';
            }
            if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Log')) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-log.php';
            }
            $this->setting_obj = PPCP_Paypal_Checkout_For_Woocommerce_Settings::instance();
            $this->settings = $this->setting_obj->get_load();
            $this->api_log = PPCP_Paypal_Checkout_For_Woocommerce_Log::instance();
        } catch (Exception $ex) {
            $this->api_log->log("The exception was created on line: " . $ex->getLine(), 'error');
            $this->api_log->log($ex->getMessage(), 'error');
        }
    }

    public function ppcp_get_properties() {
        $this->title = $this->setting_obj->get('title', 'PayPal');
        $this->enabled = 'yes' === $this->setting_obj->get('enabled', 'yes');
        $this->is_sandbox = 'yes' === $this->setting_obj->get('sandbox', 'no');
        if ($this->is_sandbox) {
            $this->client_id = $this->setting_obj->get('rest_client_id_sandbox');
            $this->secret = $this->setting_obj->get('rest_secret_id_sandbox');
        } else {
            $this->client_id = $this->setting_obj->get('rest_client_id_live');
            $this->secret = $this->setting_obj->get('rest_secret_id_live');
        }
        
        $this->enabled_pay_later_messaging = 'yes' === $this->setting_obj->get('enabled_pay_later_messaging', 'no');
        $this->pay_later_messaging_page_type = $this->setting_obj->get('pay_later_messaging_page_type', array());
        if (empty($this->pay_later_messaging_page_type)) {
            $this->enabled_pay_later_messaging = false;
        }
    }

    public function ppcp_pay_later_messaging_properties() {
        if ($this->enabled_pay_later_messaging) {
            $this->pay_later_messaging_home_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_home_shortcode', 'no');
            $this->pay_later_messaging_category_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_category_shortcode', 'no');
            $this->pay_later_messaging_product_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_product_shortcode', 'no');
            $this->pay_later_messaging_cart_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_cart_shortcode', 'no');
            $this->pay_later_messaging_payment_shortcode = 'yes' === $this->setting_obj->get('pay_later_messaging_payment_shortcode', 'no');
        }
    }

    public function ppcp_add_hooks() {
        if ($this->enabled_pay_later_messaging && $this->is_valid_for_use()) {
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'home') && $this->pay_later_messaging_home_shortcode === false) {
                add_filter('the_content', array($this, 'ppcp_pay_later_messaging_home_page_content'), 10);
                add_action('woocommerce_before_shop_loop', array($this, 'ppcp_pay_later_messaging_home_page'), 10);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'category') && $this->pay_later_messaging_category_shortcode === false) {
                add_action('woocommerce_before_shop_loop', array($this, 'ppcp_pay_later_messaging_category_page'), 10);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'product') && $this->pay_later_messaging_product_shortcode === false) {
                add_action('woocommerce_single_product_summary', array($this, 'ppcp_pay_later_messaging_product_page'), 11);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'cart') && $this->pay_later_messaging_cart_shortcode === false) {
                add_action('woocommerce_before_cart_table', array($this, 'ppcp_pay_later_messaging_cart_page'), 9);
                add_action('woocommerce_proceed_to_checkout', array($this, 'ppcp_pay_later_messaging_cart_page'), 9);
            }
            if ($this->is_paypal_pay_later_messaging_enable_for_page($page = 'payment') && $this->pay_later_messaging_payment_shortcode === false) {
                add_action('woocommerce_before_checkout_form', array($this, 'ppcp_pay_later_messaging_payment_page'), 4);
                add_action('ppcp_display_paypal_button_checkout_page', array($this, 'ppcp_pay_later_messaging_payment_page'), 9);
            }
            add_shortcode('ppcp_bnpl_message', array($this, 'ppcp_bnpl_message_shortcode'), 10);
        }
    }

    public function is_valid_for_use() {
        if (!empty($this->client_id) && !empty($this->secret) && $this->enabled) {
            return true;
        }
        return false;
    }

    public function ppcp_pay_later_messaging_home_page_content($content) {
        if ((is_home() || is_front_page())) {
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            wp_enqueue_script('ppcp-pay-later-messaging-home', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/home.js', array('jquery'), WPG_PLUGIN_VERSION, true);
            $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement = 'home');
            $content = '<div class="ppcp_message_home"></div>' . $content;
            return $content;
        }
        return $content;
    }

    public function ppcp_pay_later_messaging_home_page() {
        if (is_shop()) {
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            wp_enqueue_script('ppcp-pay-later-messaging-home', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/home.js', array('jquery'), WPG_PLUGIN_VERSION, true);
            $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement = 'home');
            echo '<div class="ppcp_message_home"></div>';
        }
    }

    public function ppcp_pay_later_messaging_category_page() {
        if (is_shop() === false && $this->pay_later_messaging_category_shortcode === false) {
            wp_enqueue_script('ppcp-checkout-js');
            wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
            wp_enqueue_script('ppcp-pay-later-messaging-category', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/category.js', array('jquery'), WPG_PLUGIN_VERSION, true);
            $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement = 'category');
            echo '<div class="ppcp_message_category"></div>';
        }
    }

    public function ppcp_pay_later_messaging_product_page() {
        wp_enqueue_script('ppcp-checkout-js');
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        wp_enqueue_script('ppcp-pay-later-messaging-product', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/product.js', array('jquery'), WPG_PLUGIN_VERSION, true);
        $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement = 'product');
        echo '<div class="ppcp_message_product"></div>';
    }

    public function ppcp_pay_later_messaging_cart_page() {
        if (isset(WC()->cart) && WC()->cart->is_empty()) {
            return false;
        }
        wp_enqueue_script('ppcp-checkout-js');
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        wp_enqueue_script('ppcp-pay-later-messaging-cart', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/cart.js', array('jquery'), WPG_PLUGIN_VERSION, true);
        if (isset(WC()->cart) && WC()->cart->needs_payment()) {
            $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement = 'cart');
            echo '<div class="ppcp_message_cart"></div>';
        }
    }

    public function ppcp_pay_later_messaging_payment_page() {
        if (WC()->cart->is_empty()) {
            return false;
        }
        if (ppcp_has_active_session()) {
            return false;
        }
        wp_enqueue_script('ppcp-checkout-js');
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        wp_enqueue_script('ppcp-pay-later-messaging-payment', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/payment.js', array('jquery'), WPG_PLUGIN_VERSION, true);
        $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement = 'payment');
        echo '<div class="ppcp_message_payment"></div>';
    }

    public function is_paypal_pay_later_messaging_enable_for_page($page = '') {
        if (empty($page)) {
            return false;
        }
        if(empty($this->pay_later_messaging_page_type)) {
            return false;
        }
        if (in_array($page, $this->pay_later_messaging_page_type)) {
            return true;
        }
        return false;
    }

    public function ppcp_paypal_pay_later_messaging_js_enqueue($placement = '', $atts = null) {
        if (!empty($placement)) {
            $enqueue_script_param = array();
            $enqueue_script_param['amount'] = $this->ppcp_get_order_total();
            switch ($placement) {
                case 'home':
                    $required_keys = array(
                        'pay_later_messaging_home_layout_type' => 'flex',
                        'pay_later_messaging_home_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_home_text_layout_logo_position' => 'left',
                        'pay_later_messaging_home_text_layout_text_size' => '12',
                        'pay_later_messaging_home_text_layout_text_color' => 'black',
                        'pay_later_messaging_home_flex_layout_color' => 'blue',
                        'pay_later_messaging_home_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    wp_localize_script('ppcp-pay-later-messaging-home', 'ppcp_pay_later_messaging', $enqueue_script_param);
                    break;
                case 'category':
                    $required_keys = array(
                        'pay_later_messaging_category_layout_type' => 'flex',
                        'pay_later_messaging_category_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_category_text_layout_logo_position' => 'left',
                        'pay_later_messaging_category_text_layout_text_size' => '12',
                        'pay_later_messaging_category_text_layout_text_color' => 'black',
                        'pay_later_messaging_category_flex_layout_color' => 'blue',
                        'pay_later_messaging_category_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    wp_localize_script('ppcp-pay-later-messaging-category', 'ppcp_pay_later_messaging', $enqueue_script_param);
                    break;
                case 'product':
                    $required_keys = array(
                        'pay_later_messaging_product_layout_type' => 'text',
                        'pay_later_messaging_product_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_product_text_layout_logo_position' => 'left',
                        'pay_later_messaging_product_text_layout_text_size' => '12',
                        'pay_later_messaging_product_text_layout_text_color' => 'black',
                        'pay_later_messaging_product_flex_layout_color' => 'blue',
                        'pay_later_messaging_product_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    wp_localize_script('ppcp-pay-later-messaging-product', 'ppcp_pay_later_messaging', $enqueue_script_param);
                    break;
                case 'cart':
                    $required_keys = array(
                        'pay_later_messaging_cart_layout_type' => 'text',
                        'pay_later_messaging_cart_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_cart_text_layout_logo_position' => 'left',
                        'pay_later_messaging_cart_text_layout_text_size' => '12',
                        'pay_later_messaging_cart_text_layout_text_color' => 'black',
                        'pay_later_messaging_cart_flex_layout_color' => 'blue',
                        'pay_later_messaging_cart_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    wp_localize_script('ppcp-pay-later-messaging-cart', 'ppcp_pay_later_messaging', $enqueue_script_param);
                    break;
                case 'payment':
                    $required_keys = array(
                        'pay_later_messaging_payment_layout_type' => 'text',
                        'pay_later_messaging_payment_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_payment_text_layout_logo_position' => 'left',
                        'pay_later_messaging_payment_text_layout_text_size' => '12',
                        'pay_later_messaging_payment_text_layout_text_color' => 'black',
                        'pay_later_messaging_payment_flex_layout_color' => 'blue',
                        'pay_later_messaging_payment_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    wp_localize_script('ppcp-pay-later-messaging-payment', 'ppcp_pay_later_messaging', $enqueue_script_param);
                    break;
                case 'shortcode':
                    $atts['amount'] = $enqueue_script_param['amount'];
                    wp_localize_script('ppcp-pay-later-messaging-shortcode', 'ppcp_pay_later_messaging', $atts);
                    break;
                default:
                    break;
            }
        }
    }

    public function ppcp_get_default_attribute_pay_later_messaging($placement = '') {
        if (!empty($placement)) {
            $enqueue_script_param = array();
            $enqueue_script_param['amount'] = $this->ppcp_get_order_total();
            switch ($placement) {
                case 'home':
                    $required_keys = array(
                        'pay_later_messaging_home_layout_type' => 'flex',
                        'pay_later_messaging_home_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_home_text_layout_logo_position' => 'left',
                        'pay_later_messaging_home_text_layout_text_size' => '12',
                        'pay_later_messaging_home_text_layout_text_color' => 'black',
                        'pay_later_messaging_home_flex_layout_color' => 'blue',
                        'pay_later_messaging_home_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'category':
                    $required_keys = array(
                        'pay_later_messaging_category_layout_type' => 'flex',
                        'pay_later_messaging_category_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_category_text_layout_logo_position' => 'left',
                        'pay_later_messaging_category_text_layout_text_size' => '12',
                        'pay_later_messaging_category_text_layout_text_color' => 'black',
                        'pay_later_messaging_category_flex_layout_color' => 'blue',
                        'pay_later_messaging_category_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'product':
                    $required_keys = array(
                        'pay_later_messaging_product_layout_type' => 'text',
                        'pay_later_messaging_product_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_product_text_layout_logo_position' => 'left',
                        'pay_later_messaging_product_text_layout_text_size' => '12',
                        'pay_later_messaging_product_text_layout_text_color' => 'black',
                        'pay_later_messaging_product_flex_layout_color' => 'blue',
                        'pay_later_messaging_product_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'cart':
                    $required_keys = array(
                        'pay_later_messaging_cart_layout_type' => 'text',
                        'pay_later_messaging_cart_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_cart_text_layout_logo_position' => 'left',
                        'pay_later_messaging_cart_text_layout_text_size' => '12',
                        'pay_later_messaging_cart_text_layout_text_color' => 'black',
                        'pay_later_messaging_cart_flex_layout_color' => 'blue',
                        'pay_later_messaging_cart_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                case 'payment':
                    $required_keys = array(
                        'pay_later_messaging_payment_layout_type' => 'text',
                        'pay_later_messaging_payment_text_layout_logo_type' => 'primary',
                        'pay_later_messaging_payment_text_layout_logo_position' => 'left',
                        'pay_later_messaging_payment_text_layout_text_size' => '12',
                        'pay_later_messaging_payment_text_layout_text_color' => 'black',
                        'pay_later_messaging_payment_flex_layout_color' => 'blue',
                        'pay_later_messaging_payment_flex_layout_ratio' => '8x1'
                    );
                    foreach ($required_keys as $key => $value) {
                        $enqueue_script_param[$key] = isset($this->settings[$key]) ? $this->settings[$key] : $value;
                    }
                    return $enqueue_script_param;
                default:
                    break;
            }
        }
    }

    public function ppcp_get_order_total() {
        global $product;
        $total = 0;
        $order_id = absint(get_query_var('order-pay'));
        if (is_product()) {
            $total = $product->get_price();
        } elseif (0 < $order_id) {
            $order = wc_get_order($order_id);
            $total = (float) $order->get_total();
        } elseif (isset(WC()->cart) && 0 < WC()->cart->total) {
            $total = (float) WC()->cart->total;
        }
        return $total;
    }

    public function ppcp_bnpl_message_shortcode($atts) {
        if (empty($atts['placement'])) {
            return '';
        }
        if (!in_array($atts['placement'], array('home', 'category', 'product', 'cart', 'payment'))) {
            return;
        }
        if ($this->is_paypal_pay_later_messaging_enable_for_page($page = $atts['placement']) === false) {
            return false;
        }
        if ($this->is_paypal_pay_later_messaging_enable_for_shoerpage($page = $atts['placement']) === false) {
            return false;
        }
        $placement = $atts['placement'];
        if (!isset($atts['style'])) {
            $atts['style'] = $this->ppcp_pay_later_messaging_get_default_value('style', $placement);
        }
        if ($atts['style'] === 'text') {
            $default_array = array(
                'placement' => 'home',
                'style' => $atts['style'],
                'logotype' => $this->ppcp_pay_later_messaging_get_default_value('logotype', $placement),
                'logoposition' => $this->ppcp_pay_later_messaging_get_default_value('logoposition', $placement),
                'textsize' => $this->ppcp_pay_later_messaging_get_default_value('textsize', $placement),
                'textcolor' => $this->ppcp_pay_later_messaging_get_default_value('textcolor', $placement),
            );
        } else {
            $default_array = array(
                'placement' => 'home',
                'style' => $atts['style'],
                'color' => $this->ppcp_pay_later_messaging_get_default_value('color', $placement),
                'ratio' => $this->ppcp_pay_later_messaging_get_default_value('ratio', $placement)
            );
        }
        $atts = array_merge(
                $default_array, (array) $atts
        );
        wp_enqueue_script('ppcp-checkout-js');
        wp_enqueue_style("ppcp-paypal-checkout-for-woocommerce-public");
        wp_enqueue_script('ppcp-pay-later-messaging-shortcode', WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/pay-later-messaging/shortcode.js', array('jquery'), WPG_PLUGIN_VERSION, true);
        $this->ppcp_paypal_pay_later_messaging_js_enqueue($placement_default = 'shortcode', $atts);
        return '<div class="ppcp_message_shortcode"></div>';
    }

    public function ppcp_pay_later_messaging_get_default_value($key, $placement) {
        if (!empty($key) && !empty($placement)) {
            $param = $this->ppcp_get_default_attribute_pay_later_messaging($placement);
            $map_keys = array('placement' => '', 'style' => 'pay_later_messaging_default_layout_type', 'logotype' => 'pay_later_messaging_default_text_layout_logo_type', 'logoposition' => 'pay_later_messaging_default_text_layout_logo_position', 'textsize' => 'pay_later_messaging_default_text_layout_text_size', 'textcolor' => 'pay_later_messaging_default_text_layout_text_color', 'color' => 'pay_later_messaging_default_flex_layout_color', 'ratio' => 'pay_later_messaging_default_flex_layout_ratio');
            if (!empty($map_keys[$key])) {
                $default_key = str_replace('default', $placement, $map_keys[$key]);
                if (!empty($param[$default_key])) {
                    return $param[$default_key];
                }
            }
            return '';
        }
    }

    public function is_paypal_pay_later_messaging_enable_for_shoerpage($page = '') {
        switch ($page) {
            case 'home':
                if ($this->pay_later_messaging_home_shortcode) {
                    return true;
                }
                break;
            case 'category':
                if ($this->pay_later_messaging_category_shortcode) {
                    return true;
                }
                break;
            case 'product':
                if ($this->pay_later_messaging_product_shortcode) {
                    return true;
                }
                break;
            case 'cart':
                if ($this->pay_later_messaging_cart_shortcode) {
                    return true;
                }
                break;
            case 'payment':
                if ($this->pay_later_messaging_payment_shortcode) {
                    return true;
                }
                break;
            default:
                break;
        }
        return false;
    }
}
