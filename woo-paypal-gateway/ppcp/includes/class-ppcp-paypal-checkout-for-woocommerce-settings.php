<?php

defined('ABSPATH') || exit;

if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Settings')) {

    class PPCP_Paypal_Checkout_For_Woocommerce_Settings {

        public $gateway_key;
        public $settings = array();
        protected static $_instance = null;

        public static function instance() {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function __construct() {
            $this->gateway_key = 'woocommerce_wpg_paypal_checkout_settings';
        }

        public function get($id, $default = false) {
            if (!$this->has($id)) {
                return $default;
            }
            return empty($this->settings[$id]) ? $default : $this->settings[$id];
        }

        public function get_load() {
            return get_option($this->gateway_key, array());
        }

        public function has($id) {
            $this->load();
            return array_key_exists($id, $this->settings);
        }

        public function set($id, $value) {
            $this->load();
            $this->settings[$id] = $value;
        }

        public function persist() {
            update_option($this->gateway_key, $this->settings);
        }

        public function load() {
            if ($this->settings) {
                return false;
            }
            $this->settings = get_option($this->gateway_key, array());
        }

        public function default_api_settings() {
            return array(
                'sandbox' => array(
                    'title' => __('Environment', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'label' => __('Select PayPal Environment', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'description' => __('Choose the PayPal environment. Select "Sandbox" for testing transactions (no real transactions will occur) or "Production" for live transactions.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'options' => array(
                        'yes' => __('Sandbox (Test Mode)', 'woo-paypal-gateway'),
                        'no' => __('Production (Live)', 'woo-paypal-gateway'),
                    ),
                ),
                'live_onboarding' => array(
                    'title' => __('Connect to PayPal', 'woo-paypal-gateway'),
                    'type' => 'wpg_paypal_checkout_onboarding',
                    'gateway' => 'wpg_paypal_checkout',
                    'mode' => 'live',
                    'description' => __('Setup or link an existing PayPal account.', 'woo-paypal-gateway'),
                    'desc_tip' => ''
                ),
                'rest_client_id_live' => array(
                    'title' => __('Live Client ID', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter PayPal Client ID.', 'woo-paypal-gateway'),
                    'default' => '',
                    'gateway' => 'wpg',
                    'desc_tip' => true,
                ),
                'rest_secret_id_live' => array(
                    'title' => __('Live Secret key', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter PayPal Secret key.', 'woo-paypal-gateway'),
                    'default' => '',
                    'gateway' => 'wpg',
                    'desc_tip' => true
                ),
                'live_disconnect' => array(
                    'title' => __('PayPal Connection', 'woo-paypal-gateway'),
                    'type' => 'wpg_ppcp_text',
                    'mode' => 'live',
                    'description' => '',
                    'gateway' => 'wpg',
                    'desc_tip' => '',
                ),
                'sandbox_onboarding' => array(
                    'title' => __('Connect to PayPal', 'woo-paypal-gateway'),
                    'type' => 'wpg_paypal_checkout_onboarding',
                    'gateway' => 'wpg_paypal_checkout',
                    'mode' => 'sandbox',
                    'description' => __('Setup or link an existing PayPal account.', 'woo-paypal-gateway'),
                    'desc_tip' => ''
                ),
                'rest_client_id_sandbox' => array(
                    'title' => __('Sandbox Client ID', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter PayPal Client ID.', 'woo-paypal-gateway'),
                    'default' => '',
                    'gateway' => 'wpg',
                    'desc_tip' => true,
                ),
                'rest_secret_id_sandbox' => array(
                    'title' => __('Sandbox Secret key', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter PayPal Secret key.', 'woo-paypal-gateway'),
                    'default' => '',
                    'gateway' => 'wpg',
                    'desc_tip' => true,
                ),
                'sandbox_disconnect' => array(
                    'title' => __('PayPal Connection', 'woo-paypal-gateway'),
                    'type' => 'wpg_ppcp_text',
                    'mode' => 'sandbox',
                    'description' => '',
                    'gateway' => 'wpg',
                    'desc_tip' => ''
                ),
            );
        }

        public function wpg_paypal_checkout_settings() {
            $button_height = array(
                '' => __('Select Height', 'woo-paypal-gateway')
            );

            for ($i = 25; $i < 56; $i++) {
                // translators: %d is the button height in pixels.
                $button_height[$i] = sprintf(__('%d px', 'woo-paypal-gateway'), $i);
            }



            $default_settings = array(
                'paypal_button_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'gpay_title',
                    'description' => wp_kses_post(sprintf(
                                    // translators: %s: Link to the WooCommerce settings FAQ page.
                                    __('PayPal buttons (e.g. Pay Later, Venmo, SEPA, iDEAL, Mercado Pago, Sofort, Bancontact, etc.) are shown automatically based on factors such as the buyer’s country and their device type. For more details, <a href="%s" target="_blank">see the FAQ</a>.', 'woo-paypal-gateway'),
                                    admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout&wpg_section=wpg_foq')
                            )),
                ),
                'gateway_enable_disable' => array(
                    'title' => __('PayPal Settings', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal', 'woo-paypal-gateway'),
                    'description' => __('Enable this option to activate the PayPal gateway. Uncheck to disable it.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('The title displayed to customers during checkout.', 'woo-paypal-gateway'),
                    'default' => __('PayPal', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woo-paypal-gateway'),
                    'type' => 'textarea',
                    'description' => __('The description displayed to customers during checkout.', 'woo-paypal-gateway'),
                    'default' => __('Pay via PayPal; you can pay with your credit card if you don’t have a PayPal account', 'woo-paypal-gateway'),
                    'desc_tip' => true
            ));

            $button_manager_settings_product_page = array(
                'ppcp_button_header' => array(
                    'title' => __('Product Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                    'description' => '',
                ),
                'show_on_product_page' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                    'description' => '',
                ),
                'product_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers on the product page only.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
                        'paylater' => __('Pay Later', 'woo-paypal-gateway'),
                        'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
                        'blik' => __('BLIK', 'woo-paypal-gateway'),
                        'eps' => __('eps', 'woo-paypal-gateway'),
                        'giropay' => __('giropay', 'woo-paypal-gateway'),
                        'ideal' => __('iDEAL', 'woo-paypal-gateway'),
                        'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
                        'mybank' => __('MyBank', 'woo-paypal-gateway'),
                        'p24' => __('Przelewy24', 'woo-paypal-gateway'),
                        'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
                        'sofort' => __('Sofort', 'woo-paypal-gateway'),
                        'venmo' => __('Venmo', 'woo-paypal-gateway')
                    ),
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select funding methods to hide.', 'woo-paypal-gateway'),
                    )
                ),
                'product_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'horizontal',
                    'desc_tip' => true,
                    'options' => array(
                        'horizontal' => __('Horizontal (Recommended)', 'woo-paypal-gateway'),
                        'vertical' => __('Vertical', 'woo-paypal-gateway')
                    ),
                ),
                'product_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'product_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'product_button_size' => array(
                    'title' => __('Button Width', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'medium',
                    'options' => array(
                        'small' => __('Small', 'woo-paypal-gateway'),
                        'medium' => __('Medium', 'woo-paypal-gateway'),
                        'large' => __('Large', 'woo-paypal-gateway'),
                        'responsive' => __('Responsive', 'woo-paypal-gateway'),
                    ),
                ),
                'product_button_height' => array(
                    'title' => __('Button Height', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => '48',
                    'options' => $button_height,
                ),
                'product_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                )
            );
            $button_manager_settings_cart_page = array(
                'cart_button_settings' => array(
                    'title' => __('Cart Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'show_on_cart' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'no'
                ),
                'cart_button_location' => array(
                    'title' => __('Button Location (Classic only)', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('The location of the payment buttons in relation to the Proceed to checkout button.', 'woo-paypal-gateway'),
                    'default' => 'below',
                    'desc_tip' => true,
                    'options' => array(
                        'below' => __('Below checkout button', 'woo-paypal-gateway'),
                        'above' => __('Above checkout button', 'woo-paypal-gateway'),
                    )
                ),
                'cart_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => array('card'),
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
                        'paylater' => __('Pay Later', 'woo-paypal-gateway'),
                        'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
                        'blik' => __('BLIK', 'woo-paypal-gateway'),
                        'eps' => __('eps', 'woo-paypal-gateway'),
                        'giropay' => __('giropay', 'woo-paypal-gateway'),
                        'ideal' => __('iDEAL', 'woo-paypal-gateway'),
                        'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
                        'mybank' => __('MyBank', 'woo-paypal-gateway'),
                        'p24' => __('Przelewy24', 'woo-paypal-gateway'),
                        'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
                        'sofort' => __('Sofort', 'woo-paypal-gateway'),
                        'venmo' => __('Venmo', 'woo-paypal-gateway')
                    ),
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select funding methods to hide.', 'woo-paypal-gateway'),
                    )
                ),
                'cart_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'vertical',
                    'desc_tip' => true,
                    'options' => array(
                        'vertical' => __('Vertical (Recommended)', 'woo-paypal-gateway'),
                        'horizontal' => __('Horizontal', 'woo-paypal-gateway'),
                    ),
                ),
                'cart_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'cart_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'cart_button_size' => array(
                    'title' => __('Button Width', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'responsive',
                    'options' => array(
                        'small' => __('Small', 'woo-paypal-gateway'),
                        'medium' => __('Medium', 'woo-paypal-gateway'),
                        'large' => __('Large', 'woo-paypal-gateway'),
                        'responsive' => __('Responsive (Recommended)', 'woo-paypal-gateway'),
                    ),
                ),
                'cart_button_height' => array(
                    'title' => __('Button Height', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => '48',
                    'options' => $button_height,
                ),
                'cart_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
            );
            $button_manager_settings_checkout_page = array(
                'checkout_button_settings' => array(
                    'title' => __('Checkout Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'checkout_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
                        'paylater' => __('Pay Later', 'woo-paypal-gateway'),
                        'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
                        'blik' => __('BLIK', 'woo-paypal-gateway'),
                        'eps' => __('eps', 'woo-paypal-gateway'),
                        'giropay' => __('giropay', 'woo-paypal-gateway'),
                        'ideal' => __('iDEAL', 'woo-paypal-gateway'),
                        'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
                        'mybank' => __('MyBank', 'woo-paypal-gateway'),
                        'p24' => __('Przelewy24', 'woo-paypal-gateway'),
                        'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
                        'sofort' => __('Sofort', 'woo-paypal-gateway'),
                        'venmo' => __('Venmo', 'woo-paypal-gateway')
                    ),
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select funding methods to hide.', 'woo-paypal-gateway'),
                    )
                ),
                'checkout_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'vertical',
                    'desc_tip' => true,
                    'options' => array(
                        'vertical' => __('Vertical (Recommended)', 'woo-paypal-gateway'),
                        'horizontal' => __('Horizontal', 'woo-paypal-gateway'),
                    ),
                ),
                'checkout_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'checkout_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'checkout_button_size' => array(
                    'title' => __('Button Width', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'responsive',
                    'options' => array(
                        'small' => __('Small', 'woo-paypal-gateway'),
                        'medium' => __('Medium', 'woo-paypal-gateway'),
                        'large' => __('Large', 'woo-paypal-gateway'),
                        'responsive' => __('Responsive (Recommended)', 'woo-paypal-gateway'),
                    ),
                ),
                'checkout_button_height' => array(
                    'title' => __('Button Height', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => '48',
                    'options' => $button_height,
                ),
                'checkout_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
                'use_place_order' => array(
                    'title' => __('Use Place Order Button', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'value' => 'yes',
                    'desc_tip' => false,
                    'description' => __('Enable this to use the default Place Order button on the checkout page instead of showing PayPal buttons. This setting does not affect Express Checkout.',
                            'woo-paypal-gateway'),
                ),
            );
            $button_manager_settings_express_checkout_page = array(
                'express_checkout_button_settings' => array(
                    'title' => __('Express Checkout', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'enable_checkout_button_top' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => 'ppcp_express_checkout_button_settings',
                    'type' => 'checkbox',
                    'label' => __('Enable Express Checkout (Display the PayPal button at the top of the checkout page).', 'woo-paypal-gateway'),
                    'default' => 'yes'
                ),
                'express_checkout_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_express_checkout_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'horizontal',
                    'desc_tip' => true,
                    'options' => array(
                        'horizontal' => __('Horizontal (Recommended)', 'woo-paypal-gateway'),
                        'vertical' => __('Vertical', 'woo-paypal-gateway'),
                    ),
                ),
                'express_checkout_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_express_checkout_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'express_checkout_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_express_checkout_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'express_checkout_button_height' => array(
                    'title' => __('Button Height', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => '40',
                    'options' => $button_height,
                ),
                'express_checkout_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_express_checkout_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
            );
            $button_manager_settings_mini_cart_page = array(
                'mini_cart_button_settings' => array(
                    'title' => __('Mini Cart (Side Cart)', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'show_on_mini_cart' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'yes'
                ),
                'min_cart_button_location' => array(
                    'title' => __('Button Location', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('The location of the payment buttons in relation to the Proceed to checkout button.', 'woo-paypal-gateway'),
                    'default' => 'below',
                    'desc_tip' => true,
                    'options' => array(
                        'below' => __('Below checkout button', 'woo-paypal-gateway'),
                        'above' => __('Above checkout button', 'woo-paypal-gateway'),
                    )
                ),
                'mini_cart_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'horizontal',
                    'desc_tip' => true,
                    'options' => array(
                        'horizontal' => __('Horizontal (Recommended)', 'woo-paypal-gateway'),
                        'vertical' => __('Vertical', 'woo-paypal-gateway'),
                    ),
                ),
                'mini_cart_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'mini_cart_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'mini_cart_button_size' => array(
                    'title' => __('Button Width', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'medium',
                    'options' => array(
                        'small' => __('Small', 'woo-paypal-gateway'),
                        'medium' => __('Medium', 'woo-paypal-gateway'),
                        'large' => __('Large', 'woo-paypal-gateway'),
                        'responsive' => __('Responsive (Recommended)', 'woo-paypal-gateway'),
                    ),
                ),
                'mini_cart_button_height' => array(
                    'title' => __('Button Height', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => '38',
                    'options' => $button_height,
                ),
                'mini_cart_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
            );

            $settings = apply_filters('ppcp_settings', array_merge($default_settings, $button_manager_settings_product_page, $button_manager_settings_cart_page, $button_manager_settings_express_checkout_page, $button_manager_settings_checkout_page, $button_manager_settings_mini_cart_page));
            return $settings;
        }

        public function wpg_advanced_cc_settings() {
            $cards_list = array(
                'visa' => _x('Visa', 'Name of credit card', 'woo-paypal-gateway'),
                'mastercard' => _x('Mastercard', 'Name of credit card', 'woo-paypal-gateway'),
                'amex' => _x('American Express', 'Name of credit card', 'woo-paypal-gateway'),
                'discover' => _x('Discover', 'Name of credit card', 'woo-paypal-gateway'),
                'jcb' => _x('JCB', 'Name of credit card', 'woo-paypal-gateway'),
                'elo' => _x('Elo', 'Name of credit card', 'woo-paypal-gateway'),
                'hiper' => _x('Hiper', 'Name of credit card', 'woo-paypal-gateway'),
            );
            return array(
                'advanced_card_pay_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'advanced_card_pay_title', // You may rename this if it's not only for Google Pay
                    'description' => __(
                            'To use Advanced Credit/Debit Card (PayPal), your PayPal account must meet eligibility requirements. Please ensure your account is approved for advanced card processing. <a target="_blank" href="https://developer.paypal.com/docs/multiparty/checkout/advanced/#eligibility">View eligibility requirements</a>.',
                            'woo-paypal-gateway'
                    ),
                ),
                'enable_advanced_card_payments' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Advanced Credit/Debit Card', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'description' => __('Enable the Advanced Credit/Debit Card payment option as a separate gateway on the checkout page. This gateway typically offers a lower PayPal fee compared to the PayPal Smart Button. The fee is 2.59% + $0.49* per transaction for Advanced Credit/Debit Card payments and 3.49% + $0.49* for PayPal Smart Buttons. It supports major cards like Visa, Mastercard, and American Express, providing a secure and streamlined payment experience.', 'woo-paypal-gateway'),
                    'desc_tip' => true
                ),
                'advanced_card_payments_title' => array(
                    'title' => __('Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __("Set the title for the Advanced Credit/Debit Card payment method as it will appear on the checkout page (e.g., 'Pay with Credit/Debit Card')..", 'woo-paypal-gateway'),
                    'default' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                    'desc_tip' => true
                ),
                'disable_cards' => array(
                    'title' => __('Disable specific credit cards', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select advanced_cc_fields_group',
                    'default' => array(),
                    'desc_tip' => true,
                    'description' => __(
                            'By default all possible credit cards will be accepted. You can disable some cards, if you wish.',
                            'woo-paypal-gateway'
                    ),
                    'options' => $cards_list,
                ),
                '3d_secure_contingency' => array(
                    'title' => __('Contingency for 3D Secure', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => array(
                        'SCA_WHEN_REQUIRED' => __('3D Secure when required', 'woo-paypal-gateway'),
                        'SCA_ALWAYS' => __('Always trigger 3D Secure', 'woo-paypal-gateway'),
                    ),
                    'default' => 'SCA_WHEN_REQUIRED',
                    'desc_tip' => true,
                    'description' => __('3D Secure benefits cardholders and merchants by providing an additional layer of verification using Verified by Visa, MasterCard SecureCode and American Express SafeKey.', 'woo-paypal-gateway'),
                ),
                'enable_save_card' => array(
                    'title' => __('Card Save Enabled', 'woo-paypal-gateway'),
                    'label' => __('Enable card saving at checkout', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'description' => __('When enabled, customers can securely save their card details using PayPal for faster future checkouts.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                )
            );
        }

        public function wpg_advanced_cc_onboard_settings() {
            return array(
                'advanced_card_onboard_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'title', // This adds an informational section
                    'description' => __('Advanced Credit/Debit Card payments are not enabled in your PayPal account. <br><br><strong>Supported Countries:</strong><br>Australia, Austria, Belgium, Bulgaria, Canada, China, Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hong Kong, Hungary, Ireland, Italy, Japan, Latvia, Liechtenstein, Lithuania, Luxembourg, Malta, Netherlands, Norway, Poland, Portugal, Romania, Singapore, Slovakia, Slovenia, Spain, Sweden, United States, United Kingdom<br><br>', 'woo-paypal-gateway'),
                ),
            );
        }

        public function wpg_ppcp_google_pay_settings() {
            return array(
                'google_pay_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'gpay_title',
                    'description' => __(
                            'To show the Google Pay button, Google Pay must be enabled in your PayPal account (<a target="_blank" href="https://developer.paypal.com/docs/checkout/apm/google-pay/#set-up-your-sandbox-account-to-accept-google-pay">setup guide</a>) and the page must be served over a secure (HTTPS) connection.',
                            'woo-paypal-gateway'
                    ),
                ),
                'enabled_google_pay' => array(
                    'title' => __('Enable Google Pay', 'woo-paypal-gateway'),
                    'label' => __('Enable Google Pay', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Check this box to display the Google Pay button on selected pages.', 'woo-paypal-gateway'),
                    'default' => 'no',
                ),
                'google_pay_pages' => array(
                    'title' => __('Select Page(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'css' => 'width: 100%;',
                    'class' => 'wc-enhanced-select',
                    'default' => array('express_checkout'),
                    'options' => array(
                        'product' => __('Product', 'woo-paypal-gateway'),
                        'cart' => __('Cart', 'woo-paypal-gateway'),
                        'mini_cart' => __('Mini Cart', 'woo-paypal-gateway'),
                        'express_checkout' => __('Express Checkout', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                    ),
                    'description' => __('Set the page(s) to display the Google Pay button.', 'woo-paypal-gateway'),
                ),
            );
        }

        public function wpg_google_pay_onboard_settings() {
            return array(
                'google_onboard_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => sprintf(
                            // translators: %1$s is the supported countries list. %2$s is the supported currencies list.
                            __('Google Pay is not enabled in your PayPal account. Click below to enable it.<br><br><strong>Supported Countries:</strong><br>%1$s<br><br><strong>Supported Currencies:</strong><br>%2$s', 'woo-paypal-gateway'),
                            'Australia, Austria, Belgium, Bulgaria, Canada, China, Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hong Kong, Hungary, Ireland, Italy, Japan, Latvia, Liechtenstein, Lithuania, Luxembourg, Malta, Netherlands, Norway, Poland, Portugal, Romania, Singapore, Slovakia, Slovenia, Spain, Sweden, United States, United Kingdom',
                            'AUD, BRL, CAD, CHF, CZK, DKK, EUR, GBP, HKD, HUF, ILS, JPY, MXN, NOK, NZD, PHP, PLN, SEK, SGD, THB, TWD, USD'
                    ),
                ),
                'enabled_google_pay_onboard' => array(
                    'title' => __('Enable Google Pay', 'woo-paypal-gateway'),
                    'type' => 'google_pay_onboard',
                    'default' => 'no',
                    'description' => '',
                    'desc_tip' => true
                )
            );
        }

        public function wpg_apple_pay_onboard_settings() {
            return array(
                'apple_onboard_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'title', // This adds an informational section
                    'description' => sprintf(
                            // translators: %1$s is the list of supported countries. %2$s is the list of supported currencies.
                            __('Apple Pay is not enabled in your PayPal account. Click below to enable it.<br><br><strong>Supported Countries:</strong><br>%1$s<br><br><strong>Supported Currencies:</strong><br>%2$s', 'woo-paypal-gateway'),
                            'Australia, Austria, Belgium, Bulgaria, Canada, China, Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hong Kong, Hungary, Ireland, Italy, Japan, Latvia, Liechtenstein, Lithuania, Luxembourg, Malta, Netherlands, Norway, Poland, Portugal, Romania, Singapore, Slovakia, Slovenia, Spain, Sweden, United States, United Kingdom',
                            'AUD, BRL, CAD, CHF, CZK, DKK, EUR, GBP, HKD, HUF, ILS, JPY, MXN, NOK, NZD, PHP, PLN, SEK, SGD, THB, TWD, USD'
                    ),
                ),
                'enabled_apple_pay_onboard' => array(
                    'title' => __('Enable Apple Pay', 'woo-paypal-gateway'),
                    'type' => 'apple_pay_onboard',
                    'default' => 'no',
                    'description' => '',
                    'desc_tip' => true
                )
            );
        }

        public function wpg_ppcp_apple_pay_settings() {
            return array(
                'apple_pay_note' => array(
                    'title' => __('Important Note', 'woo-paypal-gateway'),
                    'type' => 'apple_title',
                    'description' => wp_kses_post(__('To show the Apple Pay button, Apple Pay must be enabled in your PayPal account (<a target="_blank" href="https://developer.paypal.com/docs/checkout/apm/apple-pay/#set-up-your-sandbox-account-to-accept-apple-pay">setup guide</a>). The page must also be served over a secure (HTTPS) connection.', 'woo-paypal-gateway')),
                ),
                'apple_pay_domain_register' => array(
                    'title' => __('Domain Registration', 'woo-paypal-gateway'),
                    'type' => 'apple_pay_domain_register',
                    'description' => __('Apple requires that the website domain be registered on PayPal. Payments will not be processed if the Apple Pay button is used on an unregistered domain.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'enabled_apple_pay' => array(
                    'title' => __('Enable Apple Pay', 'woo-paypal-gateway'),
                    'label' => __('Enable Apple Pay on your store', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Check this box to display the Apple Pay button on selected pages.', 'woo-paypal-gateway'),
                    'default' => 'no',
                ),
                'apple_pay_pages' => array(
                    'title' => __('Select Page(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'css' => 'width: 100%;',
                    'class' => 'wc-enhanced-select',
                    'default' => array(),
                    'options' => array(
                        'product' => __('Product', 'woo-paypal-gateway'),
                        'cart' => __('Cart', 'woo-paypal-gateway'),
                        'mini_cart' => __('Mini Cart', 'woo-paypal-gateway'),
                        'express_checkout' => __('Express Checkout', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                    ),
                    'description' => __('Set the page(s) to display the Apple Pay button.', 'woo-paypal-gateway'),
                ),
            );
        }

        public function wpg_foq() {
            return array(
                'foq_note' => array(
                    'type' => 'foq_html'
                ),
            );
        }

        public function wpg_ppcp_paylater_settings() {
            return array(
                'enabled_pay_later_messaging' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('Enable Pay Later Messaging', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Displays Pay Later messaging for available offers.', 'woo-paypal-gateway'),
                    'default' => 'no'
                ),
                'pay_later_messaging_page_type' => array(
                    'title' => __('Page Type', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'css' => 'width: 100%;',
                    'class' => 'wc-enhanced-select pay_later_messaging_field',
                    'default' => '',
                    'options' => array('home' => __('Home', 'woo-paypal-gateway'), 'category' => __('Category', 'woo-paypal-gateway'), 'product' => __('Product', 'woo-paypal-gateway'), 'cart' => __('Cart', 'woo-paypal-gateway'), 'payment' => __('Payment', 'woo-paypal-gateway')),
                    'description' => __('Set the page(s) you want to display messaging on, and then adjust that page\'s display option below.', 'woo-paypal-gateway'),
                ),
                'pay_later_messaging_home_page_settings' => array(
                    'title' => __('Home Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_home_field',
                ),
                'pay_later_messaging_home_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field',
                    'description' => '',
                    'default' => 'flex',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on Home page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_home_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_preview_shortcode preview_shortcode',
                    'description' => '',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'button_class' => 'home_copy_text',
                    'default' => '[ppcp_bnpl_message placement="home"]'
                ),
                'pay_later_messaging_category_page_settings' => array(
                    'title' => __('Category Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field',
                ),
                'pay_later_messaging_category_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field',
                    'description' => '',
                    'default' => 'flex',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on category page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_category_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'category_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="category"]'
                ),
                'pay_later_messaging_product_page_settings' => array(
                    'title' => __('Product Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field',
                ),
                'pay_later_messaging_product_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field',
                    'description' => '',
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on product page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_product_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'product_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="product"]'
                ),
                'pay_later_messaging_cart_page_settings' => array(
                    'title' => __('Cart Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field',
                ),
                'pay_later_messaging_cart_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field',
                    'description' => '',
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on cart page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_cart_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'cart_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="cart"]'
                ),
                'pay_later_messaging_payment_page_settings' => array(
                    'title' => __('Payment Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field',
                ),
                'pay_later_messaging_payment_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field',
                    'description' => '',
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on payment page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_payment_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'payment_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="payment"]'
            ));
        }

        public function wpg_advanced_settings() {
            $advanced_settings = array(
                'paymentaction' => array(
                    'title' => __('Payment action', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woo-paypal-gateway'),
                    'default' => 'capture',
                    'desc_tip' => true,
                    'options' => array(
                        'capture' => __('Capture', 'woo-paypal-gateway'),
                        'authorize' => __('Authorize', 'woo-paypal-gateway'),
                    ),
                ),
                'brand_name' => array(
                    'title' => __('Brand Name', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls what users see as the brand / company name on PayPal review pages.', 'woo-paypal-gateway'),
                    'default' => get_bloginfo('name'),
                    'desc_tip' => true,
                ),
                'landing_page' => array(
                    'title' => __('Landing Page', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('The type of landing page to show on the PayPal site for customer checkout. PayPal Account Optional must be checked for this option to be used.', 'woo-paypal-gateway'),
                    'options' => array('LOGIN' => __('Login', 'woo-paypal-gateway'),
                        'BILLING' => __('Billing', 'woo-paypal-gateway'),
                        'NO_PREFERENCE' => __('No Preference', 'woo-paypal-gateway')),
                    'default' => 'NO_PREFERENCE',
                    'desc_tip' => true,
                ),
                'payee_preferred' => array(
                    'title' => __('Instant Payments ', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'desc_tip' => true,
                    'description' => __(
                            'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.', 'woo-paypal-gateway'
                    ),
                    'label' => __('Require Instant Payment', 'woo-paypal-gateway'),
                ),
                'set_billing_address' => array(
                    'title' => __('Billing Address', 'woo-paypal-gateway'),
                    'label' => __('Use PayPal Shipping Address as Billing', 'woo-paypal-gateway'),
                    'description' => __('If the billing address is empty and PayPal provides a shipping address, the order will use the shipping address as the billing address.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => false,
                ),
                'send_items' => array(
                    'title' => __('Send Item Details', 'woo-paypal-gateway'),
                    'label' => __('Send line item details to PayPal', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Include all line item details in the payment request to PayPal so that they can be seen from the PayPal transaction details page.', 'woo-paypal-gateway'),
                    'default' => 'yes'
                ),
                'invoice_id_prefix' => array(
                    'title' => __('Invoice prefix', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'woo-paypal-gateway'),
                    'default' => 'WC-PPCP',
                    'desc_tip' => true,
                ),
                'soft_descriptor' => array(
                    'title' => __('Credit Card Statement Name', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('The value entered here will be displayed on the buyer\'s credit card statement.', 'woo-paypal-gateway'),
                    'default' => substr(get_bloginfo('name'), 0, 21),
                    'desc_tip' => true,
                    'custom_attributes' => array('maxlength' => '22'),
                ),
                'debug' => array(
                    'title' => __('Debug log', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'description' => sprintf(
                            // translators: %s is the path to the WooCommerce PayPal debug log file.
                            __('Log PayPal events, such as Webhook, Payment, Refund inside %s', 'woo-paypal-gateway'),
                            '<code>' . WC_Log_Handler_File::get_log_file_path('wpg_paypal_checkout') . '</code>'
                    ),
                ),
            );
            if (wc_ship_to_billing_address_only() === true) {
                unset($advanced_settings['set_billing_address']);
            }
            return $advanced_settings;
        }

        public function ppcp_setting_fields() {
            $cards_list = array(
                'visa' => _x('Visa', 'Name of credit card', 'woo-paypal-gateway'),
                'mastercard' => _x('Mastercard', 'Name of credit card', 'woo-paypal-gateway'),
                'amex' => _x('American Express', 'Name of credit card', 'woo-paypal-gateway'),
                'discover' => _x('Discover', 'Name of credit card', 'woo-paypal-gateway'),
                'jcb' => _x('JCB', 'Name of credit card', 'woo-paypal-gateway'),
                'elo' => _x('Elo', 'Name of credit card', 'woo-paypal-gateway'),
                'hiper' => _x('Hiper', 'Name of credit card', 'woo-paypal-gateway'),
            );
            $default_settings = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal', 'woo-paypal-gateway'),
                    'description' => __('Enable this option to activate the PayPal gateway. Uncheck to disable it.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'woo-paypal-gateway'),
                    'default' => __('PayPal', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woo-paypal-gateway'),
                    'type' => 'textarea',
                    'description' => __('The description displayed to customers during checkout.', 'woo-paypal-gateway'),
                    'default' => __('Pay via PayPal; you can pay with your credit card if you don’t have a PayPal account', 'woo-paypal-gateway'),
                    'desc_tip' => true
                ),
                'api_details' => array(
                    'title' => __('PayPal API Credentials', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'sandbox' => array(
                    'title' => __('Environment', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'label' => __('Select PayPal Environment', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'description' => __('Choose the PayPal environment. Select "Sandbox" for testing transactions (no real transactions will occur) or "Production" for live transactions.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'options' => array(
                        'yes' => __('Sandbox', 'woo-paypal-gateway'),
                        'no' => __('Production', 'woo-paypal-gateway'),
                    ),
                ),
                'rest_client_id_live' => array(
                    'title' => __('Client ID', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => sprintf(
                            // translators: %1$s is the URL to retrieve Client ID, %2$s is the URL to PayPal documentation.
                            __('<a href="%1$s" target="_blank" style="text-decoration: none;">Retrieve your Client ID and Secret key</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="%2$s" target="_blank" style="text-decoration: none;">Need help? Read PayPal’s documentation</a>', 'woo-paypal-gateway'),
                            esc_url('https://developer.paypal.com/dashboard/applications/live'),
                            esc_url('https://developer.paypal.com/api/rest/#link-getclientidandclientsecret')
                    ),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'rest_secret_id_live' => array(
                    'title' => __('Secret key', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your Secret key.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'rest_client_id_sandbox' => array(
                    'title' => __('Client ID', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => sprintf(
                            // translators: %1$s is the URL to retrieve sandbox Client ID. %2$s is the URL to PayPal REST API documentation.
                            __('<a href="%1$s" target="_blank" style="text-decoration: none;">Retrieve your Client ID and Secret key</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="%2$s" target="_blank" style="text-decoration: none;">Need help? Read PayPal’s documentation</a>', 'woo-paypal-gateway'),
                            esc_url('https://developer.paypal.com/dashboard/applications/sandbox'),
                            esc_url('https://developer.paypal.com/api/rest/#link-getclientidandclientsecret')
                    ),
                    'default' => '',
                    'desc_tip' => false,
                ),
                'rest_secret_id_sandbox' => array(
                    'title' => __('Secret key', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your PayPal Secret key.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                ),
            );

            $button_manager_settings_product_page = array(
                'ppcp_button_header' => array(
                    'title' => __('Product Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                    'description' => __('Enable product-specific button settings to apply the selected options to PayPal Smart Buttons on your product pages.', 'woo-paypal-gateway'),
                ),
                'show_on_product_page' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                    'description' => '',
                ),
                'product_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers on the product page only.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
                        'paylater' => __('Pay Later', 'woo-paypal-gateway'),
                        'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
                        'blik' => __('BLIK', 'woo-paypal-gateway'),
                        'eps' => __('eps', 'woo-paypal-gateway'),
                        'giropay' => __('giropay', 'woo-paypal-gateway'),
                        'ideal' => __('iDEAL', 'woo-paypal-gateway'),
                        'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
                        'mybank' => __('MyBank', 'woo-paypal-gateway'),
                        'p24' => __('Przelewy24', 'woo-paypal-gateway'),
                        'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
                        'sofort' => __('Sofort', 'woo-paypal-gateway'),
                        'venmo' => __('Venmo', 'woo-paypal-gateway')
                    ),
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select funding methods to hide.', 'woo-paypal-gateway'),
                    )
                ),
                'product_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'horizontal',
                    'desc_tip' => true,
                    'options' => array(
                        'horizontal' => __('Horizontal (Recommended)', 'woo-paypal-gateway'),
                        'vertical' => __('Vertical', 'woo-paypal-gateway')
                    ),
                ),
                'product_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'product_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'product_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                )
            );
            $button_manager_settings_cart_page = array(
                'cart_button_settings' => array(
                    'title' => __('Cart Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('Enable the Cart-specific button settings to apply these options to the PayPal buttons displayed on your Cart page.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'show_on_cart' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'no',
                ),
                'cart_button_location' => array(
                    'title' => __('Button Location', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('The location of the payment buttons in relation to the Proceed to checkout button.', 'woo-paypal-gateway'),
                    'default' => 'below',
                    'desc_tip' => true,
                    'options' => array(
                        'below' => __('Below checkout button', 'woo-paypal-gateway'),
                        'above' => __('Above checkout button', 'woo-paypal-gateway'),
                    )
                ),
                'cart_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => array('card'),
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
                        'paylater' => __('Pay Later', 'woo-paypal-gateway'),
                        'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
                        'blik' => __('BLIK', 'woo-paypal-gateway'),
                        'eps' => __('eps', 'woo-paypal-gateway'),
                        'giropay' => __('giropay', 'woo-paypal-gateway'),
                        'ideal' => __('iDEAL', 'woo-paypal-gateway'),
                        'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
                        'mybank' => __('MyBank', 'woo-paypal-gateway'),
                        'p24' => __('Przelewy24', 'woo-paypal-gateway'),
                        'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
                        'sofort' => __('Sofort', 'woo-paypal-gateway'),
                        'venmo' => __('Venmo', 'woo-paypal-gateway')
                    ),
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select funding methods to hide.', 'woo-paypal-gateway'),
                    )
                ),
                'cart_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'vertical',
                    'desc_tip' => true,
                    'options' => array(
                        'vertical' => __('Vertical (Recommended)', 'woo-paypal-gateway'),
                        'horizontal' => __('Horizontal', 'woo-paypal-gateway'),
                    ),
                ),
                'cart_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'cart_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'cart_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
            );
            $button_manager_settings_checkout_page = array(
                'checkout_button_settings' => array(
                    'title' => __('Checkout Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'checkout_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
                        'paylater' => __('Pay Later', 'woo-paypal-gateway'),
                        'bancontact' => __('Bancontact', 'woo-paypal-gateway'),
                        'blik' => __('BLIK', 'woo-paypal-gateway'),
                        'eps' => __('eps', 'woo-paypal-gateway'),
                        'giropay' => __('giropay', 'woo-paypal-gateway'),
                        'ideal' => __('iDEAL', 'woo-paypal-gateway'),
                        'mercadopago' => __('Mercado Pago', 'woo-paypal-gateway'),
                        'mybank' => __('MyBank', 'woo-paypal-gateway'),
                        'p24' => __('Przelewy24', 'woo-paypal-gateway'),
                        'sepa' => __('SEPA-Lastschrift', 'woo-paypal-gateway'),
                        'sofort' => __('Sofort', 'woo-paypal-gateway'),
                        'venmo' => __('Venmo', 'woo-paypal-gateway')
                    ),
                    'custom_attributes' => array(
                        'data-placeholder' => __('Select funding methods to hide.', 'woo-paypal-gateway'),
                    )
                ),
                'checkout_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'vertical',
                    'desc_tip' => true,
                    'options' => array(
                        'vertical' => __('Vertical (Recommended)', 'woo-paypal-gateway'),
                        'horizontal' => __('Horizontal', 'woo-paypal-gateway'),
                    ),
                ),
                'checkout_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'checkout_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'checkout_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
                'use_place_order' => array(
                    'title' => __('Use Place Order Button', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'value' => 'yes',
                    'desc_tip' => false,
                    'description' => __('Enable this to use the default Place Order button on the checkout page instead of showing PayPal buttons. This setting does not affect Express Checkout.',
                            'woo-paypal-gateway'),
                ),
            );
            $button_manager_settings_express_checkout_page = array(
                'express_checkout_button_settings' => array(
                    'title' => __('Express Checkout', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'enable_checkout_button_top' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => 'ppcp_checkout_button_settings',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'yes'
                ),
                'express_checkout_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'horizontal',
                    'desc_tip' => true,
                    'options' => array(
                        'horizontal' => __('Horizontal (Recommended)', 'woo-paypal-gateway'),
                        'vertical' => __('Vertical', 'woo-paypal-gateway'),
                    ),
                ),
                'express_checkout_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'express_checkout_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'express_checkout_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_checkout_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
            );
            $button_manager_settings_mini_cart_page = array(
                'mini_cart_button_settings' => array(
                    'title' => __('Mini Cart Settings', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('Enable the Mini Cart-specific button settings to apply these options to the PayPal buttons on your Mini Cart page.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'show_on_mini_cart' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woo-paypal-gateway'),
                    'default' => 'yes'
                ),
                'min_cart_button_location' => array(
                    'title' => __('Button Location', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('The location of the payment buttons in relation to the Proceed to checkout button.', 'woo-paypal-gateway'),
                    'default' => 'below',
                    'desc_tip' => true,
                    'options' => array(
                        'below' => __('Below checkout button', 'woo-paypal-gateway'),
                        'above' => __('Above checkout button', 'woo-paypal-gateway'),
                    )
                ),
                'mini_cart_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'horizontal',
                    'desc_tip' => true,
                    'options' => array(
                        'horizontal' => __('Horizontal (Recommended)', 'woo-paypal-gateway'),
                        'vertical' => __('Vertical', 'woo-paypal-gateway'),
                    ),
                ),
                'mini_cart_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'gold',
                    'options' => array(
                        'gold' => __('Gold (Recommended)', 'woo-paypal-gateway'),
                        'blue' => __('Blue', 'woo-paypal-gateway'),
                        'silver' => __('Silver', 'woo-paypal-gateway'),
                        'white' => __('White', 'woo-paypal-gateway'),
                        'black' => __('Black', 'woo-paypal-gateway')
                    ),
                ),
                'mini_cart_button_shape' => array(
                    'title' => __('Button Shape', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'rect',
                    'options' => array(
                        'rect' => __('Rect (Recommended)', 'woo-paypal-gateway'),
                        'pill' => __('Pill', 'woo-paypal-gateway')
                    ),
                ),
                'mini_cart_button_label' => array(
                    'title' => __('Button Label', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'default' => 'paypal',
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
            );
            $pay_later_messaging_settings = array(
                'pay_later_messaging_settings' => array(
                    'title' => __('Pay Later Messaging Customization', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'enabled_pay_later_messaging' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('Enable Pay Later Messaging', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => '<div style="font-size: smaller">Displays Pay Later messaging for available offers.',
                    'default' => 'no'
                ),
                'pay_later_messaging_page_type' => array(
                    'title' => __('Page Type', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'css' => 'width: 100%;',
                    'class' => 'wc-enhanced-select pay_later_messaging_field',
                    'default' => '',
                    'options' => array('home' => __('Home', 'woo-paypal-gateway'), 'category' => __('Category', 'woo-paypal-gateway'), 'product' => __('Product', 'woo-paypal-gateway'), 'cart' => __('Cart', 'woo-paypal-gateway'), 'payment' => __('Payment', 'woo-paypal-gateway')),
                    'description' => '<div style="font-size: smaller;">Set the page(s) you want to display messaging on, and then adjust that page\'s display option below.</div>',
                ),
                'pay_later_messaging_home_page_settings' => array(
                    'title' => __('Home Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_home_field',
                ),
                'pay_later_messaging_home_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field',
                    'description' => '',
                    'default' => 'flex',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on Home page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_home_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_preview_shortcode preview_shortcode',
                    'description' => '',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'button_class' => 'home_copy_text',
                    'default' => '[ppcp_bnpl_message placement="home"]'
                ),
                'pay_later_messaging_category_page_settings' => array(
                    'title' => __('Category Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field',
                ),
                'pay_later_messaging_category_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field',
                    'description' => '',
                    'default' => 'flex',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on category page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_category_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'category_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="category"]'
                ),
                'pay_later_messaging_product_page_settings' => array(
                    'title' => __('Product Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field',
                ),
                'pay_later_messaging_product_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field',
                    'description' => '',
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on product page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_product_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'product_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="product"]'
                ),
                'pay_later_messaging_cart_page_settings' => array(
                    'title' => __('Cart Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field',
                ),
                'pay_later_messaging_cart_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field',
                    'description' => '',
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on cart page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_cart_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'cart_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="cart"]'
                ),
                'pay_later_messaging_payment_page_settings' => array(
                    'title' => __('Payment Page', 'woo-paypal-gateway'),
                    'description' => '',
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field',
                ),
                'pay_later_messaging_payment_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field',
                    'description' => '',
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => '',
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_flex_layout_field',
                    'description' => '',
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_flex_layout_field',
                    'description' => '',
                    'default' => '8x1',
                    'desc_tip' => true,
                    'options' => array('1x1' => __('Flexes between 120px and 300px wide', 'woo-paypal-gateway'), '1x4' => __('160px wide', 'woo-paypal-gateway'), '8x1' => __('Flexes between 250px and 768px wide', 'woo-paypal-gateway'), '20x1' => __('Flexes between 250px and 1169px wide', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_shortcode' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('I need a shortcode so that I can place the message in a better spot on payment page.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_shortcode',
                    'description' => '',
                    'default' => 'no'
                ),
                'pay_later_messaging_payment_preview_shortcode' => array(
                    'title' => __('Shortcode', 'woo-paypal-gateway'),
                    'type' => 'copy_text',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_preview_shortcode preview_shortcode',
                    'description' => '',
                    'button_class' => 'payment_copy_text',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => '[ppcp_bnpl_message placement="payment"]'
            ));

            $advanced_card_processing = array(
                'advanced_card_processing' => array(
                    'title' => __('Checkout Page Settings - Advanced Credit/Debit Card', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'enable_advanced_card_payments' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable advanced credit and debit card payments', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'description' => '',
                ),
                'advanced_card_payments_title' => array(
                    'title' => __('Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-paypal-gateway'),
                    'default' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                    'desc_tip' => true
                ),
                'disable_cards' => array(
                    'title' => __('Disable specific credit cards', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select advanced_cc_fields_group',
                    'default' => array(),
                    'desc_tip' => true,
                    'description' => __(
                            'By default all possible credit cards will be accepted. You can disable some cards, if you wish.',
                            'woo-paypal-gateway'
                    ),
                    'options' => $cards_list,
                ),
                '3d_secure_contingency' => array(
                    'title' => __('Contingency for 3D Secure', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => array(
                        'SCA_WHEN_REQUIRED' => __('3D Secure when required', 'woo-paypal-gateway'),
                        'SCA_ALWAYS' => __('Always trigger 3D Secure', 'woo-paypal-gateway'),
                    ),
                    'default' => 'SCA_WHEN_REQUIRED',
                    'desc_tip' => true,
                    'description' => __('3D Secure benefits cardholders and merchants by providing an additional layer of verification using Verified by Visa, MasterCard SecureCode and American Express SafeKey.', 'woo-paypal-gateway'),
                ),
                'enable_save_card' => array(
                    'title' => __('Card Save Enabled', 'woo-paypal-gateway'),
                    'label' => __('Enable card saving at checkout', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'description' => __('When enabled, customers can securely save their card details using PayPal for faster future checkouts.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                )
            );

            $advanced_settings = array(
                'advanced' => array(
                    'title' => __('Additional Configuration Options', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading ppcp-collapsible-section',
                ),
                'paymentaction' => array(
                    'title' => __('Payment action', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woo-paypal-gateway'),
                    'default' => 'capture',
                    'desc_tip' => true,
                    'options' => array(
                        'capture' => __('Capture', 'woo-paypal-gateway'),
                        'authorize' => __('Authorize', 'woo-paypal-gateway'),
                    ),
                ),
                'brand_name' => array(
                    'title' => __('Brand Name', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls what users see as the brand / company name on PayPal review pages.', 'woo-paypal-gateway'),
                    'default' => get_bloginfo('name'),
                    'desc_tip' => true,
                ),
                'landing_page' => array(
                    'title' => __('Landing Page', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('The type of landing page to show on the PayPal site for customer checkout. PayPal Account Optional must be checked for this option to be used.', 'woo-paypal-gateway'),
                    'options' => array('LOGIN' => __('Login', 'woo-paypal-gateway'),
                        'BILLING' => __('Billing', 'woo-paypal-gateway'),
                        'NO_PREFERENCE' => __('No Preference', 'woo-paypal-gateway')),
                    'default' => 'NO_PREFERENCE',
                    'desc_tip' => true,
                ),
                'payee_preferred' => array(
                    'title' => __('Instant Payments ', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'no',
                    'desc_tip' => true,
                    'description' => __(
                            'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or PayPal Credit.', 'woo-paypal-gateway'
                    ),
                    'label' => __('Require Instant Payment', 'woo-paypal-gateway'),
                ),
                'set_billing_address' => array(
                    'title' => __('Billing Address', 'woo-paypal-gateway'),
                    'label' => __('Use PayPal Shipping Address as Billing', 'woo-paypal-gateway'),
                    'description' => __('If the billing address is empty and PayPal provides a shipping address, the order will use the shipping address as the billing address.', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                    'desc_tip' => false,
                ),
                'send_items' => array(
                    'title' => __('Send Item Details', 'woo-paypal-gateway'),
                    'label' => __('Send line item details to PayPal', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Include all line item details in the payment request to PayPal so that they can be seen from the PayPal transaction details page.', 'woo-paypal-gateway'),
                    'default' => 'yes'
                ),
                'invoice_id_prefix' => array(
                    'title' => __('Invoice prefix', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'woo-paypal-gateway'),
                    'default' => 'WC-PPCP',
                    'desc_tip' => true,
                ),
                'soft_descriptor' => array(
                    'title' => __('Credit Card Statement Name', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('The value entered here will be displayed on the buyer\'s credit card statement.', 'woo-paypal-gateway'),
                    'default' => substr(get_bloginfo('name'), 0, 21),
                    'desc_tip' => true,
                    'custom_attributes' => array('maxlength' => '22'),
                ),
                'debug' => array(
                    'title' => __('Debug log', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'description' => sprintf(
                            // translators: %1$s is the path to the PayPal debug log file.
                            __('Log PayPal events, such as Webhook, Payment, Refund inside %1$s', 'woo-paypal-gateway'),
                            '<code>' . WC_Log_Handler_File::get_log_file_path('wpg_paypal_checkout') . '</code>'
                    ),
                ),
            );

            if (wc_ship_to_billing_address_only() === true) {
                unset($advanced_settings['set_billing_address']);
            }

            $google_pay = array(
                'enabled_google_pay' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('Enable Google Pay', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => __('Displays the Google Pay button.', 'woo-paypal-gateway'),
                    'default' => 'no',
                ),
                'google_pay_pages' => array(
                    'title' => __('Select Page(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'css' => 'width: 100%;',
                    'class' => 'wc-enhanced-select',
                    'default' => array('express_checkout'),
                    'options' => array(
                        'product' => __('Product', 'woo-paypal-gateway'),
                        'cart' => __('Cart', 'woo-paypal-gateway'),
                        'mini_cart' => __('Mini Cart', 'woo-paypal-gateway'),
                        'express_checkout' => __('Express Checkout', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                    ),
                    'description' => __('Set the page(s) to display the Google Pay button.', 'woo-paypal-gateway'),
                ),
            );

            $apple_pay = array(
                'apple_pay' => array(
                    'title' => __('Apple Pay', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading',
                ),
                'apple_pay_payments_title' => array(
                    'title' => __('Apple Pay Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-paypal-gateway'),
                    'default' => __('Apple Pay', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'apple_pay_domain_register' => array(
                    'title' => __('Domain Registration', 'woo-paypal-gateway'),
                    'type' => 'apple_pay_domain_register',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-paypal-gateway'),
                    'default' => __('Apple Pay', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'enabled_apple_pay' => array(
                    'title' => __('Enable Apple Pay', 'woo-paypal-gateway'),
                    'label' => __('Enable Apple Pay', 'woo-paypal-gateway'),
                    'type' => 'checkbox_enable_paypal_apple_pay',
                    'description' => __('Allow buyers to pay using Apple Pay.', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'apple_pay_payments_description' => array(
                    'title' => __('Apple Pay Payment Description', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees when they select Apple Pay payment method during checkout.', 'woo-paypal-gateway'),
                    'default' => __('Accept payments using Apple Pay.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
            );

            $settings = apply_filters('ppcp_settings', array_merge($default_settings, $button_manager_settings_product_page, $button_manager_settings_cart_page, $button_manager_settings_express_checkout_page, $button_manager_settings_checkout_page, $advanced_card_processing, $button_manager_settings_mini_cart_page, $pay_later_messaging_settings, $advanced_settings, $google_pay, $apple_pay));
            return $settings;
        }
    }

}