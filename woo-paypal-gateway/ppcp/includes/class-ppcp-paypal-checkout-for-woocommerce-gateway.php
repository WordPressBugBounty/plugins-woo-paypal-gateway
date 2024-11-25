<?php

/**
 * @since      1.0.0
 * @package    PPCP_Paypal_Checkout_For_Woocommerce_Gateway
 * @subpackage PPCP_Paypal_Checkout_For_Woocommerce_Gateway/includes
 * @author     PayPal <wpeasypayment@gmail.com>
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Gateway extends WC_Payment_Gateway_CC {

    /**
     * @since    1.0.0
     */
    public $request;
    public $settings_obj;
    public $plugin_name;
    public $sandbox;
    public $rest_client_id_sandbox;
    public $sandbox_secret_id;
    public $live_client_id;
    public $live_secret_id;
    public $client_id;
    public $secret_id;
    public $paymentaction;
    public $advanced_card_payments;
    public $threed_secure_contingency;
    public static $log = false;
    public $disable_cards;
    public $advanced_card_payments_title;
    public $cc_enable;
    static $ppcp_display_order_fee = 0;
    static $notice_shown = false;
    public $wpg_section;

    public function __construct() {
        $this->setup_properties();
        $this->init_form_fields();
        $this->init_settings();
        $this->get_properties();
        $this->plugin_name = 'ppcp-paypal-checkout';
        $this->title = $this->get_option('title', 'PayPal');
        $this->disable_cards = $this->get_option('disable_cards', array());
        $this->description = $this->get_option('description', '');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        if (!has_action('woocommerce_admin_order_totals_after_total', array('PPCP_Paypal_Checkout_For_Woocommerce_Gateway', 'ppcp_display_order_fee'))) {
            add_action('woocommerce_admin_order_totals_after_total', array($this, 'ppcp_display_order_fee'));
        }
        $this->advanced_card_payments_title = $this->get_option('advanced_card_payments_title', 'Credit or Debit Card');
        if (ppcp_has_active_session()) {
            $this->order_button_text = $this->get_option('order_review_page_button_text', 'Confirm your PayPal order');
        }
        add_action('admin_notices', array($this, 'display_paypal_admin_notice'));
        add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'wpg_sanitized_paypal_client_secret'), 999, 1);
    }

    public function setup_properties() {
        $this->id = 'wpg_paypal_checkout';
        $this->method_title = __('PayPal Checkout', 'woo-paypal-gateway');
        $this->method_description = __('Effortlessly integrate PayPal with your WooCommerce store! Enter your Client ID and Secret, enable payment methods, and customize settings—all backed by an Official PayPal Partner.', 'woo-paypal-gateway');
        $this->has_fields = true;
    }

    public function get_properties() {
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->cc_enable = $this->get_option('enable_advanced_card_payments', 'yes');
        $this->supports = array(
            'products',
            'refunds'
        );
        $this->sandbox = 'yes' === $this->get_option('sandbox', 'no');
        $this->rest_client_id_sandbox = $this->get_option('rest_client_id_sandbox', '');
        $this->sandbox_secret_id = $this->get_option('rest_secret_id_sandbox', '');
        $this->live_client_id = $this->get_option('rest_client_id_live', '');
        $this->live_secret_id = $this->get_option('rest_secret_id_live', '');
        if ($this->sandbox) {
            $this->client_id = $this->rest_client_id_sandbox;
            $this->secret_id = $this->sandbox_secret_id;
        } else {
            $this->client_id = $this->live_client_id;
            $this->secret_id = $this->live_secret_id;
        }
        if (!$this->is_credentials_set()) {
            $this->enabled = 'no';
            $this->cc_enable = 'no';
        }
        $this->paymentaction = $this->get_option('paymentaction', 'capture');
        $this->advanced_card_payments = 'yes' === $this->get_option('enable_advanced_card_payments', 'yes');
        $this->threed_secure_contingency = $this->get_option('3d_secure_contingency', 'SCA_WHEN_REQUIRED');
        $this->wpg_section = isset($_GET['wpg_section']) ? sanitize_text_field($_GET['wpg_section']) : 'wpg_api_settings';
    }

    function display_paypal_admin_notice() {

        $error_message = get_transient('wpg_invalid_client_secret_message');
        if ($error_message) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . esc_html($error_message) . '</p>';
            echo '</div>';
            delete_transient('wpg_invalid_client_secret_message');
        }
        if (self::$notice_shown) {
            return;
        }
        if (!$this->is_credentials_set()) {
            if (isset($_GET['wpg_section'])) {
                $wpg_section = sanitize_text_field($_GET['wpg_section']);
            } else {
                $wpg_section = (isset($_GET['section']) && $_GET['section'] === 'wpg_paypal_checkout') ? 'wpg_api_settings' : '';
            }
            if ($wpg_section !== 'wpg_api_settings') {
                $message = sprintf(__('<strong>PayPal Payments Setup Required:</strong> Your PayPal integration is almost complete! To start accepting payments, please enter your <strong>PayPal Client ID</strong> and <strong>Secret Key</strong> in the <a href="%1$s">API Settings tab</a>.', 'woo-paypal-gateway'), admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout'));
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . $message . '</p>';
                echo '</div>';
            }
        }
        self::$notice_shown = true;
    }

    public function payment_fields() {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description));
        }
        do_action('display_paypal_button_checkout_page');
    }

    public function is_credentials_set() {
        if (!empty($this->client_id) && !empty($this->secret_id)) {
            return true;
        } else {
            return false;
        }
    }

    public function init_form_fields() {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {
            include 'class-ppcp-paypal-checkout-for-woocommerce-settings.php';
        }
        $this->settings_obj = PPCP_Paypal_Checkout_For_Woocommerce_Settings::instance();
        $this->form_fields = $this->settings_obj->ppcp_setting_fields();
    }

    public function process_admin_options() {
        delete_transient('ppcp_sandbox_access_token');
        delete_transient('ppcp_live_access_token');
        delete_transient('ppcp_sandbox_client_token');
        delete_transient('ppcp_live_client_token');
        delete_option('ppcp_sandbox_webhook_id');
        delete_option('ppcp_live_webhook_id');
        parent::process_admin_options();
    }

    public function admin_options() {
        wp_enqueue_script('wc-clipboard');
        echo '<h2>' . __('PayPal Settings', '');
        wc_back_link(__('Return to payments', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        echo '</h2>';

        $this->output_tabs($this->wpg_section);
        if ($this->wpg_section === 'wpg_api_settings' && !$this->is_credentials_set()) {
            echo '<br/>';
            echo '<div style="background: #f9f9f9;border-spacing: 2px; border-color: gray; padding: 20px; margin-bottom: 20px;max-width:858px;">
                 <h4 style="margin: 0 0 15px; font-size: 14px; font-weight: bold; display: flex; align-items: center;">
                    <span style="font-size: 20px; margin-right: 8px;"></span> Here\'s how to get your client ID and client secret:
                 </h4>
                <ol style="margin: 10px 0 0 20px; padding: 0; font-size: 14px; line-height: 1.8; color: #333;">
                    <li>Select <a href="https://developer.paypal.com/dashboard/" target="_blank" style="color: #007cba; text-decoration: none;">Log in to Dashboard</a> and log in or sign up.</li>
<li>Select <strong>Apps & Credentials</strong>.</li>
<li>New accounts come with a <strong>Default Application</strong> in the <strong>REST API apps</strong> section. To create a new project, select <strong>Create App</strong>.</li>
<li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> for your app.</li>
<li>Paste them into the fields on this page and click <strong>Save Changes</strong>.</li>
                </ol>
            </div>';
        }
        $this->admin_option();
    }

    public function output_tabs($current_tab) {
        $tabs = array(
            'wpg_api_settings' => __('API Settings', 'your-plugin-domain'),
            'wpg_paypal_checkout' => __('PayPal Checkout', 'your-plugin-domain'),
            'wpg_advanced_cc' => __('Advanced Card Payments', 'your-plugin-domain'),
            'wpg_ppcp_paylater' => __('Pay Later Messaging', 'your-plugin-domain'),
            'wpg_advanced_settings' => __('Additional Settings', 'your-plugin-domain'),
        );
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $active_class = ($key === $current_tab) ? 'nav-tab-active' : '';
            $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id . '&wpg_section=' . $key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($active_class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
    }

    public function admin_option() {
        echo '<table class="form-table">' . $this->generate_settings_html($this->get_form_fields(), false) . '</table>'; // WPCS: XSS ok.
    }

    public function get_form_fields() {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {
            include 'class-ppcp-paypal-checkout-for-woocommerce-settings.php';
        }
        $this->settings_obj = PPCP_Paypal_Checkout_For_Woocommerce_Settings::instance();
        if ($this->wpg_section === 'wpg_api_settings') {
            $default_api_settings = $this->settings_obj->default_api_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $default_api_settings));
        } elseif ($this->wpg_section === 'wpg_paypal_checkout') {
            $wpg_paypal_checkout_settings = $this->settings_obj->wpg_paypal_checkout_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_paypal_checkout_settings));
        } elseif ($this->wpg_section === 'wpg_advanced_cc') {
            $wpg_advanced_cc_settings = $this->settings_obj->wpg_advanced_cc_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_cc_settings));
        } elseif ($this->wpg_section === 'wpg_ppcp_paylater') {
            $wpg_ppcp_paylater_settings = $this->settings_obj->wpg_ppcp_paylater_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_ppcp_paylater_settings));
        } elseif ($this->wpg_section === 'wpg_advanced_settings') {
            $wpg_advanced_settings = $this->settings_obj->wpg_advanced_settings();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $wpg_advanced_settings));
        } else {
            $this->form_fields = $this->settings_obj->ppcp_setting_fields();
            return apply_filters('woocommerce_settings_api_form_fields_' . $this->id, array_map(array($this, 'set_defaults'), $this->form_fields));
        }
    }

    public function process_payment($woo_order_id) {
        if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Request')) {
            include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        }
        $this->request = new PPCP_Paypal_Checkout_For_Woocommerce_Request($this);
        $is_success = false;
        if (isset($_GET['from']) && 'checkout' === $_GET['from']) {
            ppcp_set_session('ppcp_woo_order_id', $woo_order_id);
            $this->request->ppcp_create_order_request($woo_order_id);
            exit();
        } else {
            $ppcp_paypal_order_id = ppcp_get_session('ppcp_paypal_order_id');
            if (!empty($ppcp_paypal_order_id)) {
                include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
                $this->request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
                $order = wc_get_order($woo_order_id);
                if ($this->paymentaction === 'capture') {
                    $is_success = $this->request->ppcp_order_capture_request($woo_order_id);
                } else {
                    $is_success = $this->request->ppcp_order_auth_request($woo_order_id);
                }
                $order->update_meta_data('_payment_action', $this->paymentaction);
                $order->update_meta_data('enviorment', ($this->sandbox) ? 'sandbox' : 'live');
                $order->save_meta_data();
                if ($is_success) {
                    WC()->cart->empty_cart();
                    unset(WC()->session->ppcp_session);
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                } else {
                    unset(WC()->session->ppcp_session);
                    return array(
                        'result' => 'failure',
                        'redirect' => wc_get_cart_url()
                    );
                }
            } else {
                $result = $this->request->ppcp_regular_create_order_request($woo_order_id);
                if (ob_get_length()) {
                    ob_end_clean();
                }
                return $result;
            }
        }
    }

    public function get_transaction_url($order) {
        $enviorment = $order->get_meta('enviorment');
        if ($enviorment === 'sandbox') {
            $this->view_transaction_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        } else {
            $this->view_transaction_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
        }
        return parent::get_transaction_url($order);
    }

    public function can_refund_order($order) {
        $has_api_creds = false;
        if (!empty($this->client_id) && !empty($this->secret_id)) {
            $has_api_creds = true;
        }
        return $order && $order->get_transaction_id() && $has_api_creds;
    }

    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error', __('Refund failed.', 'woo-paypal-gateway'));
        }
        include_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
        $this->request = new PPCP_Paypal_Checkout_For_Woocommerce_Request();
        $transaction_id = $order->get_transaction_id();
        $bool = $this->request->ppcp_refund_order($order_id, $amount, $reason, $transaction_id);
        return $bool;
    }

    public function ppcp_display_order_fee($order_id) {
        if (self::$ppcp_display_order_fee > 0) {
            return;
        }
        self::$ppcp_display_order_fee = 1;
        $order = wc_get_order($order_id);
        $fee = $order->get_meta('_paypal_fee');
        $payment_method = $order->get_payment_method();
        if ('wpg_paypal_checkout' !== $payment_method) {
            return false;
        }
        $currency = $order->get_meta('_paypal_fee_currency_code');
        if ($order->get_status() == 'refunded') {
            return true;
        }
        ?>
        <tr>
            <td class="label stripe-fee">
                <?php echo wc_help_tip(__('This represents the fee PayPal collects for the transaction.', 'woo-paypal-gateway')); ?>
                <?php esc_html_e('PayPal Fee:', 'woo-paypal-gateway'); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                -&nbsp;<?php echo wc_price($fee, array('currency' => $currency)); ?>
            </td>
        </tr>
        <?php
    }

    public function get_icon() {
        $icon = $this->icon ? '<img src="' . WC_HTTPS::force_https_url($this->icon) . '" alt="' . esc_attr($this->get_title()) . '" />' : '';
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function generate_wpg_paypal_checkout_text_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'wpg_paypal_checkout_text') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                                 ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <button type="button" class="button ppcp-disconnect"><?php echo __('Disconnect', ''); ?></button>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_copy_text_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => array(),
        );
        $data = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                   ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.                                                                     ?> />
                    <button type="button" class="button-secondary <?php echo esc_attr($data['button_class']); ?>" data-tip="Copied!">Copy</button>
                    <?php echo $this->get_description_html($data); // WPCS: XSS ok.               ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function admin_scripts() {
        if (isset($_GET['section']) && 'wpg_paypal_checkout' === $_GET['section']) {
            wp_enqueue_style('ppcp-paypal-checkout-for-woocommerce-admin', WPG_PLUGIN_ASSET_URL . 'ppcp/admin/css/ppcp-paypal-checkout-for-woocommerce-admin.css', array(), WPG_PLUGIN_VERSION, 'all');
            wp_enqueue_script('ppcp-paypal-checkout-for-woocommerce-admin', WPG_PLUGIN_ASSET_URL . 'ppcp/admin/js/ppcp-paypal-checkout-for-woocommerce-admin.js', array('jquery'), WPG_PLUGIN_VERSION, false);
            wp_localize_script('ppcp-paypal-checkout-for-woocommerce-admin', 'ppcp_param', array(
                'woocommerce_currency' => get_woocommerce_currency(),
                'is_advanced_cards_available' => ppcp_is_advanced_cards_available() ? 'yes' : 'no',
                'mode' => $this->sandbox ? 'sandbox' : 'live',
                'is_sandbox_connected' => (!empty($this->rest_client_id_sandbox) && !empty($this->sandbox_secret_id)) ? 'yes' : 'no',
                'is_live_connected' => (!empty($this->live_client_id) && !empty($this->live_secret_id)) ? 'yes' : 'no',
            ));
        }
    }

    public function generate_wpg_ppcp_text_html($field_key, $data) {
        if (isset($data['type']) && $data['type'] === 'wpg_ppcp_text') {
            $field_key = $this->get_field_key($field_key);
            ob_start();
            ?>
            <tr valign="top" style="display:none;">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.                                                                                                  ?></label>
                </th>
                <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                    <div class="wpg_ppcp_paypal_connection_image">
                        <div class="wpg_ppcp_paypal_connection_image_status">
                            <img src="<?php echo WPG_PLUGIN_ASSET_URL . 'assets/images/mark.png'; ?>" width="32" height="32">
                        </div>
                    </div>
                    <div class="wpg_ppcp_paypal_connection">
                        <div class="wpg_ppcp_paypal_connection_status">
                            <h3><?php echo __('PayPal Account Successfully Connected!', 'woo-paypal-gateway'); ?></h3>
                        </div>
                    </div>
                    <button type="button" class="button wpg-ppcp-disconnect"><?php echo __('Change PayPal Account', 'woo-paypal-gateway'); ?></button>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }
    }

    public function generate_text_html($key, $data) {
        if (isset($data['gateway']) && $data['gateway'] === 'wpg') {
            $field_key = $this->get_field_key($key);
            $defaults = array(
                'title' => '',
                'disabled' => false,
                'class' => '',
                'css' => '',
                'placeholder' => '',
                'type' => 'text',
                'desc_tip' => false,
                'description' => '',
                'custom_attributes' => array(),
            );
            $data = wp_parse_args($data, $defaults);
            ob_start();
            ?>
            <tr valign="top" style="display:none;">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.             ?></label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                        <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok.             ?> />
                        <?php echo $this->get_description_html($data); // WPCS: XSS ok. ?>
                    </fieldset>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        } else {
            parent::generate_text_html($key, $data);
        }
    }

    public function wpg_sanitized_paypal_client_secret($settings) {
        if ($this->wpg_section === 'wpg_api_settings') {
            $is_sandbox = isset($settings['sandbox']) && $settings['sandbox'] === 'yes';
            $environment = $is_sandbox ? 'sandbox' : 'live';
            $client_id_key = "rest_client_id_{$environment}";
            $secret_id_key = "rest_secret_id_{$environment}";
            $client_id = isset($settings[$client_id_key]) ? sanitize_text_field($settings[$client_id_key]) : '';
            $secret_id = isset($settings[$secret_id_key]) ? sanitize_text_field($settings[$secret_id_key]) : '';
            if (!empty($client_id) && !empty($secret_id)) {
                $paypal_oauth_api = $is_sandbox ? 'https://api.sandbox.paypal.com/v1/oauth2/token/' : 'https://api.paypal.com/v1/oauth2/token/';
                $basicAuth = base64_encode("{$client_id}:{$secret_id}");
                if (!$this->wpg_validate_paypal_client_secret($is_sandbox, $paypal_oauth_api, $basicAuth)) {
                    $error_message = __('The PayPal Client ID and Secret key you entered are invalid. Ensure you are using the correct credentials for the selected environment (Sandbox or Live).', 'woocommerce');
                    set_transient('wpg_invalid_client_secret_message', $error_message, 5000);
                    $settings[$client_id_key] = '';
                    $settings[$secret_id_key] = '';
                    ob_get_clean();
                    wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_api_settings'));
                    exit;
                }
            }
        }
        return $settings;
    }

    public function wpg_validate_paypal_client_secret($is_sandbox, $paypal_oauth_api, $basicAuth) {
        try {
            $headers = [
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . $basicAuth,
                'PayPal-Partner-Attribution-Id' => 'MBJTechnolabs_SI_SPB',
            ];
            $body = ['grant_type' => 'client_credentials'];
            $response = wp_remote_post($paypal_oauth_api, [
                'method' => 'POST',
                'timeout' => 60,
                'headers' => $headers,
                'body' => $body,
            ]);
            if (is_wp_error($response)) {
                return false;
            }
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($api_response['access_token'])) {
                return $api_response['access_token'];
            }
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }
}
