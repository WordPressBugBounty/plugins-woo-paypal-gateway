<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Paypal_Gateway
 * @subpackage Woo_Paypal_Gateway/includes
 * @author     easypayment <wpeasypayment@gmail.com>
 */
class Woo_Paypal_Gateway {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Woo_Paypal_Gateway_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('WPG_PLUGIN_VERSION')) {
            $this->version = WPG_PLUGIN_VERSION;
        } else {
            $this->version = '9.0.8';
        }
        $this->plugin_name = 'woo-paypal-gateway';
        if (!defined('WPG_PLUGIN_NAME')) {
            define('WPG_PLUGIN_NAME', $this->plugin_name);
        }
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        add_action('init', array($this, 'add_endpoint'), 0);
        add_action('init', array($this, 'handle_api_requests'), 999999);
        add_action('wpg_paypal_payment_api_ipn', array($this, 'wpg_paypal_payment_api_ipn'));
        add_action('http_api_curl', array($this, 'wpg_http_api_curl_ec_add_curl_parameter'), 10, 3);
        $prefix = is_network_admin() ? 'network_admin_' : '';
        add_filter("{$prefix}plugin_action_links_" . WPG_PLUGIN_BASENAME, array($this, 'wpg_plugin_action_links'), 10, 4);
        add_action('woocommerce_cart_emptied', array($this, 'wpg_clear_session'), 1);
        add_action('woocommerce_cart_item_removed', array($this, 'wpg_clear_session'), 1);
        add_action('woocommerce_update_cart_action_cart_updated', array($this, 'wpg_clear_session'), 1);
        add_action('admin_notices', array($this, 'cpp_admin_notice'));
        add_action('wp_ajax_ppcp_admin_notice_action', array($this, 'ppcp_admin_notice_action'), 10);
        add_action('wp_ajax_ppcp_dismiss_notice', array($this, 'ppcp_dismiss_notice'), 10);
        add_action('admin_notices', array($this, 'display_google_reviews_notice'), 999999);
        add_action('wp_ajax_hide_google_reviews_notice', array($this, 'hide_google_reviews_notice'));
        add_action('wp_ajax_remind_me_later_google_reviews_notice', array($this, 'remind_me_later_google_reviews_notice'));
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Woo_Paypal_Gateway_Loader. Orchestrates the hooks of the plugin.
     * - Woo_Paypal_Gateway_i18n. Defines internationalization functionality.
     * - Woo_Paypal_Gateway_Admin. Defines all hooks for the admin area.
     * - Woo_Paypal_Gateway_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-paypal-gateway-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-paypal-gateway-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-woo-paypal-gateway-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-woo-paypal-gateway-public.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-woo-paypal-gateway-functions.php';

        if (!class_exists('Woo_Paypal_Gateway_Calculations')) {
            require_once plugin_dir_path(dirname(__FILE__)) . '/includes/class-woo-paypal-gateway-calculations.php';
        }

        $this->loader = new Woo_Paypal_Gateway_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Woo_Paypal_Gateway_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new Woo_Paypal_Gateway_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new Woo_Paypal_Gateway_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles', 0);
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('plugins_loaded', $plugin_admin, 'init_wpg_paypal_payment');
        $this->loader->add_filter('woocommerce_payment_gateways', $plugin_admin, 'wpg_pal_payment_for_woo_add_payment_method_class', 9999, 1);
    }

    private function define_public_hooks() {

        $plugin_public = new Woo_Paypal_Gateway_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles', 0);
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Woo_Paypal_Gateway_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    public function handle_api_requests() {
        global $wp;
        if (isset($_GET['wpg_ipn_action']) && $_GET['wpg_ipn_action'] == 'ipn') {
            $wp->query_vars['Woo_Paypal_Gateway'] = $_GET['wpg_ipn_action'];
        }
        if (!empty($wp->query_vars['Woo_Paypal_Gateway'])) {
            ob_start();
            $api = strtolower(esc_attr($wp->query_vars['Woo_Paypal_Gateway']));
            do_action('wpg_paypal_payment_api_' . $api);
            ob_end_clean();
            die('1');
        }
    }

    public function add_endpoint() {
        add_rewrite_endpoint('Woo_Paypal_Gateway', EP_ALL);
    }

    public function wpg_paypal_payment_api_ipn() {
        require_once( WPG_PLUGIN_DIR . '/includes/paypal-ipn/class-woo-paypal-gateway-ipn-handler.php' );
        $Woo_Paypal_Gateway_IPN_Handler_Object = new Woo_Paypal_Gateway_IPN_Handler();
        $Woo_Paypal_Gateway_IPN_Handler_Object->check_response();
    }

    public function wpg_http_api_curl_ec_add_curl_parameter($handle, $r, $url) {
        try {
            if ((strstr($url, 'https://') && strstr($url, '.paypal.com'))) {
                curl_setopt($handle, CURLOPT_VERBOSE, 1);
                curl_setopt($handle, CURLOPT_SSLVERSION, 6);
            }
        } catch (Exception $ex) {
            
        }
    }

    public function wpg_plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
        $custom_actions = array(
            'configure' => sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout'), __('Settings', 'woo-paypal-gateway')),
            'docs' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://wordpress.org/plugins/woo-paypal-gateway/', __('Docs', 'woo-paypal-gateway')),
            'support' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://wordpress.org/support/plugin/woo-paypal-gateway/', __('Support', 'woo-paypal-gateway')),
            'review' => sprintf('<a href="%s" target="_blank">%s</a>', 'https://wordpress.org/support/plugin/woo-paypal-gateway/reviews/', __('Write a Review', 'woo-paypal-gateway')),
        );
        return array_merge($custom_actions, $actions);
    }

    public function wpg_clear_session() {
        wpg_clear_session_data();
    }

    public function cpp_admin_notice() {
        global $current_user;
        $user_id = $current_user->ID;
        if (false === ( $ppcp_wp_review_request = get_transient('ppcp_wp_review_request') ) && !get_user_meta($user_id, 'ppcp_wp_review_request')) :
            ?>
            <div class="ppcp_wp_review_request notice notice-info is-dismissible">
                <h3 style="margin: 10px 0;"><?php _e('Payment Gateway for PayPal on WooCommerce', 'woo-paypal-gateway'); ?></h3>
                <p><?php esc_html_e('Hey, We would like to thank you for using our plugin. We would appreciate it if you could take a moment to drop a quick review that will inspire us to keep going.', 'woo-paypal-gateway'); ?></p>
                <p>
                    <a class="button button-secondary" style="color:#333; border-color:#ccc; background:#efefef;" data-type="later">Remind me later</a>
                    <a class="button button-primary" data-type="add_review">Review now</a>
                </p>
            </div>
        <?php endif; ?>
        <script type="text/javascript">
            (function ($) {
                "use strict";
                var data_obj = {
                    action: 'ppcp_admin_notice_action',
                    ppcp_admin_notice_action_type: ''
                };
                $(document).on('click', '.ppcp_wp_review_request a.button', function (e) {
                    e.preventDefault();
                    var elm = $(this);
                    var btn_type = elm.attr('data-type');
                    if (btn_type == 'add_review') {
                        window.open('https://wordpress.org/support/plugin/woo-paypal-gateway/reviews/');
                    }
                    elm.parents('.ppcp_wp_review_request').hide();

                    data_obj['ppcp_admin_notice_action_type'] = btn_type;
                    $.ajax({
                        url: ajaxurl,
                        data: data_obj,
                        type: 'POST'
                    });

                }).on('click', '.ppcp_wp_review_request .notice-dismiss', function (e) {
                    e.preventDefault();
                    var elm = $(this);
                    elm.parents('.ppcp_wp_review_request').hide();
                    data_obj['ppcp_admin_notice_action_type'] = 'review_closed';
                    $.ajax({
                        url: ajaxurl,
                        data: data_obj,
                        type: 'POST'
                    });

                }).on('click', '.ppcp_wp_billing_phone_notice .notice-dismiss', function (e) {
                    e.preventDefault();
                    var elm = $(this);
                    elm.parents('.ppcp_wp_billing_phone_notice').hide();
                    data_obj['ppcp_admin_notice_action_type'] = 'ppcp_wp_billing_phone_notice';
                    $.ajax({
                        url: ajaxurl,
                        data: data_obj,
                        type: 'POST'
                    });

                });
            })(jQuery);
        </script>
        <?php
    }

    public function ppcp_admin_notice_action() {
        if (isset($_POST['ppcp_admin_notice_action_type']) && 'later' === $_POST['ppcp_admin_notice_action_type']) {
            set_transient('ppcp_wp_review_request', 'yes', MONTH_IN_SECONDS);
        } elseif (isset($_POST['ppcp_admin_notice_action_type']) && 'add_review' === $_POST['ppcp_admin_notice_action_type']) {
            global $current_user;
            $user_id = $current_user->ID;
            add_user_meta($user_id, 'ppcp_wp_review_request', 'true', true);
        } elseif (isset($_POST['ppcp_admin_notice_action_type']) && 'review_closed' === $_POST['ppcp_admin_notice_action_type']) {
            set_transient('ppcp_wp_review_request', 'yes', MONTH_IN_SECONDS);
        } elseif (isset($_POST['ppcp_admin_notice_action_type']) && 'ppcp_wp_billing_phone_notice' === $_POST['ppcp_admin_notice_action_type']) {
            global $current_user;
            $user_id = $current_user->ID;
            add_user_meta($user_id, 'ppcp_wp_billing_phone_notice', 'true', true);
        }
    }

    public function ppcp_dismiss_notice() {
        global $current_user;
        $user_id = $current_user->ID;
        if (!empty($_POST['action']) && $_POST['action'] == 'ppcp_dismiss_notice') {
            if (!empty($_POST['action']) && $_POST['action'] === 'ppcp_dismiss_notice') {
                add_user_meta($user_id, $_POST['data'], 'true', true);
                wp_send_json_success();
            }
        }
    }

    public function display_google_reviews_notice() {
        $user_id = get_current_user_id();
        $hide_notice = get_user_meta($user_id, 'hide_google_reviews_notice', true);
        $remind_me_later = get_user_meta($user_id, 'remind_me_later_google_reviews_notice', true);

        if ($hide_notice || ($remind_me_later && time() < $remind_me_later)) {
            return;
        }

        $plugin_slug = 'widgets-for-google-reviews-and-ratings/widgets-for-google-reviews-and-ratings.php';
        $plugin_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug);

        if ($plugin_installed || (isset($_GET['action']) && $_GET['action'] === 'install-plugin')) {
            return;
        }
        ?>
        <style>
            .google-reviews-notice {
                background-color: #f5f5f5;
                border-left: 4px solid #34A853;
                padding: 20px;
                border-radius: 5px;
                position: relative;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-sizing: border-box;
            }
            .google-reviews-notice .content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
            }
            .google-reviews-notice .icon {
                margin-right: 15px;
            }
            .google-reviews-notice .icon img {
                width: 100px;
            }
            .google-reviews-notice .message {
                flex-grow: 1;
            }
            .google-reviews-notice h2 {
                font-size: 18px;
                margin: 0 0 5px;
                font-weight: bold;
                color: #333;
            }
            .google-reviews-notice p {
                margin: 0;
                font-size: 14px;
                color: #666;
            }
            .google-reviews-notice .buttons {
                display: flex;
                gap: 10px;
            }
            .google-reviews-notice a.button-do-not-remind {
                padding: 10px 20px;
                background-color: #ddd;
                color: #333;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
            }
            .google-reviews-notice a.button-do-not-remind:hover {
                background-color: #ccc;
            }
            .google-reviews-notice a.button-install {
                padding: 10px 20px;
                background-color: #4285F4;
                color: #fff;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
            }
            .google-reviews-notice a.button-install:hover {
                background-color: #3367D6;
            }
            .google-reviews-notice .dismiss {
                position: absolute;
                top: -2px;
                right: -2px;
                font-size: 18px;
                color: #888;
                cursor: pointer;
                display: block;
            }
            .google-reviews-notice .dismiss:hover {
                color: #555;
            }
        </style>
        <div class="notice google-reviews-notice" id="google-reviews-notice">
            <div class="content">
                <div class="icon">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google Logo">
                </div>
                <div class="message">
                    <h2>Boost Your Site with Google Reviews</h2>
                    <p>Increase sales and build trust by displaying real customer reviews. Improve your SEO rankings and boost credibility—all for free and with no API key required!</p>
                </div>
                <div class="buttons">
                    <a class="button-do-not-remind" href="#" id="do-not-remind-again">Do not remind me again</a>
                    <a class="button-install" href="<?php echo wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=widgets-for-google-reviews-and-ratings'), 'install-plugin_widgets-for-google-reviews-and-ratings'); ?>">Install Now</a>
                </div>
            </div>
            <span class="dismiss dashicons dashicons-dismiss google-dismiss-promo" title="Dismiss"></span>
        </div>
        <?php
    }

    public function hide_google_reviews_notice() {
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'hide_google_reviews_notice', true);
        wp_send_json_success();
    }

    public function remind_me_later_google_reviews_notice() {
        $user_id = get_current_user_id();
        $remind_me_time = strtotime('+7 days');
        update_user_meta($user_id, 'remind_me_later_google_reviews_notice', $remind_me_time);
        wp_send_json_success();
    }
}
