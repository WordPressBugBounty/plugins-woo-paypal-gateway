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
            return $this->settings[$id];
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

            $defaults = array(
                'title' => __('PayPal', 'woo-paypal-gateway'),
                'description' => __(
                        'Accept PayPal, PayPal Credit and alternative payment types.', 'woo-paypal-gateway'
                )
            );
            foreach ($defaults as $key => $value) {
                if (isset($this->settings[$key])) {
                    continue;
                }
                $this->settings[$key] = $value;
            }
            return true;
        }

        public function ppcp_setting_fields() {
            $payment_action_not_available = '';
            if (get_woocommerce_currency() === 'INR') {
                $payment_action_not_available = __('Authorize payment action is not available for INR currency.', 'woo-paypal-gateway');
            }
            $default_settings = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Checkout', 'woo-paypal-gateway'),
                    'description' => __('Check this box to enable the payment gateway. Leave unchecked to disable it.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-paypal-gateway'),
                    'default' => __('PayPal Checkout', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woo-paypal-gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woo-paypal-gateway'),
                    'default' => __("Pay via PayPal; you can pay with your credit card if you don't have a PayPal account", 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'api_details' => array(
                    'title' => __('Account Settings', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading',
                ),
                'sandbox' => array(
                    'title' => __('PayPal sandbox', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal sandbox', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'description' => __('Check this box to enable test mode so that all transactions will hit PayPal’s sandbox server instead of the live server. This should only be used during development as no real transactions will occur when this is enabled.', 'woo-paypal-gateway'),
                    'desc_tip' => true
                ),
                'live_api_details' => array(
                    'title' => __('PayPal REST API Details &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a target="_blank" href="https://developer.paypal.com/dashboard/applications/live" style="text-decoration: none;">Get a PayPal Live Client ID and Secret</a>', 'woo-paypal-gateway'),
                    'type' => 'title'
                ),
                'rest_client_id_live' => array(
                    'title' => __('PayPal Client ID', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your PayPal Client ID.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'rest_secret_id_live' => array(
                    'title' => __('PayPal Secret', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your PayPal Secret.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'sandbox_api_details' => array(
                    'title' => __('PayPal REST API Details &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a target="_blank" href="https://developer.paypal.com/dashboard/applications/sandbox" style="text-decoration: none;">Get a PayPal Sandbox Client ID and Secret</a>', 'woo-paypal-gateway'),
                    'type' => 'title'
                ),
                'rest_client_id_sandbox' => array(
                    'title' => __('Sandbox Client ID', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your PayPal Sandbox Client ID.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true
                ),
                'rest_secret_id_sandbox' => array(
                    'title' => __('Sandbox Secret', 'woo-paypal-gateway'),
                    'type' => 'password',
                    'description' => __('Enter your PayPal Sandbox Secret.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true
                )
            );

            $button_manager_settings = array(
                'ppcp_button_header' => array(
                    'title' => __('Smart Payment Buttons Settings', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading',
                ),
                'product_button_settings' => array(
                    'title' => __('Product Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('Enable the Product specific button settings, and the options set will be applied to the PayPal Smart buttons on your Product pages.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => '',
                ),
                'show_on_product_page' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Smart Button on the Product Pages.', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true,
                    'description' => __('', 'woo-paypal-gateway'),
                ),
                'product_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_product_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
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
                    'description' => __('Set the color you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the shape you would like to use for the buttons.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the label type you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'default' => 'paypal',
                    'desc_tip' => true,
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
                'product_button_tagline' => array(
                    'title' => __('Tagline', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'ppcp_product_button_settings',
                    'default' => 'yes',
                    'label' => __('Enable tagline', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'description' => __(
                            'Add the tagline. This line will only show up, if you select a horizontal layout.', 'woo-paypal-gateway'
                    ),
                ),
                'cart_button_settings' => array(
                    'title' => __('Cart Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('Enable the Cart specific button settings, and the options set will be applied to the PayPal buttons on your Cart page.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => '',
                ),
                'show_on_cart' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Smart Button on the Cart page.', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true,
                    'description' => __('Optionally override global button settings above and configure buttons specific to Cart page.', 'woo-paypal-gateway'),
                ),
                'cart_button_top_position' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => 'ppcp_cart_button_settings',
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Smart Button on the Top of the Cart page.', 'woo-paypal-gateway'),
                    'default' => 'no'
                ),
                'cart_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_cart_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
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
                    'description' => __('Set the color you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the shape you would like to use for the buttons.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the label type you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'default' => 'paypal',
                    'desc_tip' => true,
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
                'cart_button_tagline' => array(
                    'title' => __('Tagline', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'ppcp_cart_button_settings',
                    'default' => 'yes',
                    'label' => __('Enable tagline', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'description' => __(
                            'Add the tagline. This line will only show up, if you select a horizontal layout.', 'woo-paypal-gateway'
                    ),
                ),
                'checkout_button_settings' => array(
                    'title' => __('Checkout Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('Enable the checkout specific button settings, and the options set will be applied to the PayPal buttons on your checkout page.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => '',
                ),
                'show_on_checkout_page' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Smart Button on the Checkout page.', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true,
                    'description' => __('Optionally override global button settings above and configure buttons specific to checkout page.', 'woo-paypal-gateway'),
                ),
                'enable_checkout_button_top' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => 'ppcp_checkout_button_settings',
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Smart Button on the Top of the Checkout page.', 'woo-paypal-gateway'),
                    'default' => 'no'
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
                    'description' => __('Set the color you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the shape you would like to use for the buttons.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the label type you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'default' => 'paypal',
                    'desc_tip' => true,
                    'options' => array(
                        'paypal' => __('PayPal', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
                'checkout_button_tagline' => array(
                    'title' => __('Tagline', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'ppcp_checkout_button_settings',
                    'default' => 'yes',
                    'label' => __('Enable tagline', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'description' => __(
                            'Add the tagline. This line will only show up, if you select a horizontal layout.', 'woo-paypal-gateway'
                    ),
                ),
                'mini_cart_button_settings' => array(
                    'title' => __('Mini Cart Page', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => __('Enable the Mini Cart specific button settings, and the options set will be applied to the PayPal buttons on your Mini Cart page.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => '',
                ),
                'show_on_mini_cart' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'class' => '',
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Smart Button on the Mini Cart page.', 'woo-paypal-gateway'),
                    'default' => 'yes',
                    'desc_tip' => true,
                    'description' => __('Optionally override global button settings above and configure buttons specific to Mini Cart page.', 'woo-paypal-gateway'),
                ),
                'mini_cart_disallowed_funding_methods' => array(
                    'title' => __('Hide Funding Method(s)', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('Funding methods selected here will be hidden from buyers during checkout.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'options' => array(
                        'card' => __('Credit or Debit Card', 'woo-paypal-gateway'),
                        'credit' => __('PayPal Credit', 'woo-paypal-gateway'),
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
                ),
                'mini_cart_button_layout' => array(
                    'title' => __('Button Layout', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('Select Vertical for stacked buttons, and Horizontal for side-by-side buttons.', 'woo-paypal-gateway'),
                    'default' => 'vertical',
                    'desc_tip' => true,
                    'options' => array(
                        'vertical' => __('Vertical (Recommended)', 'woo-paypal-gateway'),
                        'horizontal' => __('Horizontal', 'woo-paypal-gateway'),
                    ),
                ),
                'mini_cart_button_color' => array(
                    'title' => __('Button Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select ppcp_mini_cart_button_settings',
                    'description' => __('Set the color you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the shape you would like to use for the buttons.', 'woo-paypal-gateway'),
                    'desc_tip' => true,
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
                    'description' => __('Set the label type you would like to use for the PayPal button.', 'woo-paypal-gateway'),
                    'default' => 'mini_cart',
                    'desc_tip' => true,
                    'options' => array(
                        'paypal' => __('PayPal (Recommended)', 'woo-paypal-gateway'),
                        'checkout' => __('Checkout', 'woo-paypal-gateway'),
                        'buynow' => __('Buy Now', 'woo-paypal-gateway'),
                        'pay' => __('Pay', 'woo-paypal-gateway'),
                    ),
                ),
                'mini_cart_button_tagline' => array(
                    'title' => __('Tagline', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'class' => 'ppcp_mini_cart_button_settings',
                    'default' => 'yes',
                    'label' => __('Enable tagline', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                    'description' => __(
                            'Add the tagline. This line will only show up, if you select a horizontal layout.', 'woo-paypal-gateway'
                    ),
                ),
            );

            $order_review_page_settings = array(
                'order_review_page' => array(
                    'title' => __('Order Review Page options', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading',
                ),
                'order_review_page_title' => array(
                    'title' => __('Page Title', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('Set the Page Title value you would like used on the PayPal Checkout order review page.', 'woo-paypal-gateway'),
                    'default' => __('Confirm your PayPal order', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                ),
                'order_review_page_description' => array(
                    'title' => __('Description', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => __('Set the Description you would like used on the PayPal Checkout order review page.', 'woo-paypal-gateway'),
                    'default' => __("<strong>You're almost done!</strong><br>Review your information before you place your order.", 'woo-paypal-gateway'),
                ),
                'order_review_page_button_text' => array(
                    'title' => __('Button Text', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('Set the Button Text you would like used on the PayPal Checkout order review page.', 'woo-paypal-gateway'),
                    'default' => __('Confirm your PayPal order', 'woo-paypal-gateway'),
                    'desc_tip' => true,
                )
            );

            $pay_later_messaging_settings = array(
                'pay_later_messaging_settings' => array(
                    'title' => __('Pay Later Messaging Settings', 'woo-paypal-gateway'),
                    'class' => '',
                    'description' => '',
                    'type' => 'title',
                    'class' => 'ppcp_separator_heading',
                ),
                'enabled_pay_later_messaging' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'label' => __('Enable Pay Later Messaging', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'description' => '<div style="font-size: smaller">Displays Pay Later messaging for available offers. Restrictions apply. <a target="_blank" href="https://developer.paypal.com/docs/business/pay-later/us/commerce-platforms/">See terms and learn more</a></div>',
                    'default' => 'no'
                ),
                'pay_later_messaging_page_type' => array(
                    'title' => __('Page Type', 'woo-paypal-gateway'),
                    'type' => 'multiselect',
                    'css' => 'width: 100%;',
                    'class' => 'wc-enhanced-select pay_later_messaging_field',
                    'default' => array('home', 'category', 'product', 'cart', 'payment'),
                    'options' => array('home' => __('Home', 'woo-paypal-gateway'), 'category' => __('Category', 'woo-paypal-gateway'), 'product' => __('Product', 'woo-paypal-gateway'), 'cart' => __('Cart', 'woo-paypal-gateway'), 'payment' => __('Payment', 'woo-paypal-gateway')),
                    'description' => '<div style="font-size: smaller;">Set the page(s) you want to display messaging on, and then adjust that page\'s display option below.</div>',
                ),
                'pay_later_messaging_home_page_settings' => array(
                    'title' => __('Home Page', 'woo-paypal-gateway'),
                    'description' => __('Customize the appearance of <a target="_blank" href="https://www.paypal.com/us/business/buy-now-pay-later">Pay Later Messaging</a> on the Home page to promote special financing offers which help increase sales.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_home_field',
                ),
                'pay_later_messaging_home_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'flex',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_home_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_home_field pay_later_messaging_home_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
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
                    'description' => __('Customize the appearance of <a target="_blank" href="https://www.paypal.com/us/business/buy-now-pay-later">Pay Later Messaging</a> on the Category page to promote special financing offers which help increase sales.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_category_field',
                ),
                'pay_later_messaging_category_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'flex',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_category_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_category_field pay_later_messaging_category_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
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
                    'description' => __('Customize the appearance of <a target="_blank" href="https://www.paypal.com/us/business/buy-now-pay-later">Pay Later Messaging</a> on the Product page to promote special financing offers which help increase sales.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_product_field',
                ),
                'pay_later_messaging_product_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_product_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_product_field pay_later_messaging_product_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
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
                    'description' => __('Customize the appearance of <a target="_blank" href="https://www.paypal.com/us/business/buy-now-pay-later">Pay Later Messaging</a> on the Cart page to promote special financing offers which help increase sales.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_cart_field',
                ),
                'pay_later_messaging_cart_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_cart_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_cart_field pay_later_messaging_cart_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
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
                    'description' => __('Customize the appearance of <a target="_blank" href="https://www.paypal.com/us/business/buy-now-pay-later">Pay Later Messaging</a> on the Payment page to promote special financing offers which help increase sales.', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'class' => 'pay_later_messaging_field pay_later_messaging_payment_field',
                ),
                'pay_later_messaging_payment_layout_type' => array(
                    'title' => __('Layout Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'text',
                    'desc_tip' => true,
                    'options' => array('text' => __('Text Layout', 'woo-paypal-gateway'), 'flex' => __('Flex Layout', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_logo_type' => array(
                    'title' => __('Logo Type', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'primary',
                    'desc_tip' => true,
                    'options' => array('primary' => __('Primary', 'woo-paypal-gateway'), 'alternative' => __('Alternative', 'woo-paypal-gateway'), 'inline' => __('Inline', 'woo-paypal-gateway'), 'none' => __('None', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_logo_position' => array(
                    'title' => __('Logo Position', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'left',
                    'desc_tip' => true,
                    'options' => array('left' => __('Left', 'woo-paypal-gateway'), 'right' => __('Right', 'woo-paypal-gateway'), 'top' => __('Top', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_text_size' => array(
                    'title' => __('Text Size', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => '12',
                    'desc_tip' => true,
                    'options' => array('10' => __('10 px', 'woo-paypal-gateway'), '11' => __('11 px', 'woo-paypal-gateway'), '12' => __('12 px', 'woo-paypal-gateway'), '13' => __('13 px', 'woo-paypal-gateway'), '14' => __('14 px', 'woo-paypal-gateway'), '15' => __('15 px', 'woo-paypal-gateway'), '16' => __('16 px', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_text_layout_text_color' => array(
                    'title' => __('Text Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_text_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'black',
                    'desc_tip' => true,
                    'options' => array('black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_flex_layout_color' => array(
                    'title' => __('Color', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
                    'default' => 'blue',
                    'desc_tip' => true,
                    'options' => array('blue' => __('Blue', 'woo-paypal-gateway'), 'black' => __('Black', 'woo-paypal-gateway'), 'white' => __('White', 'woo-paypal-gateway'), 'white-no-border' => __('White (No Border)', 'woo-paypal-gateway'), 'gray' => __('Gray', 'woo-paypal-gateway'), 'monochrome' => __('Monochrome', 'woo-paypal-gateway'), 'grayscale' => __('Grayscale', 'woo-paypal-gateway'))
                ),
                'pay_later_messaging_payment_flex_layout_ratio' => array(
                    'title' => __('Ratio', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select pay_later_messaging_field pay_later_messaging_payment_field pay_later_messaging_payment_flex_layout_field',
                    'description' => __('', 'woo-paypal-gateway'),
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

            $advanced_settings = array(
                'advanced' => array(
                    'title' => __('Advanced Settings', 'woo-paypal-gateway'),
                    'type' => 'title',
                    'description' => '',
                    'class' => 'ppcp_separator_heading',
                ),
                'brand_name' => array(
                    'title' => __('Brand Name', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('This controls what users see as the brand / company name on PayPal review pages.', 'woo-paypal-gateway'),
                    'default' => __(get_bloginfo('name'), 'woo-paypal-gateway'),
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
                'enable_advanced_card_payments' => array(
                    'title' => __('Enable/Disable', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable advanced credit and debit card payments', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'description' => '',
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
                'paymentaction' => array(
                    'title' => __('Payment action', 'woo-paypal-gateway'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woo-paypal-gateway') . '  ' . $payment_action_not_available,
                    'default' => 'capture',
                    'desc_tip' => true,
                    'options' => array(
                        'capture' => __('Capture', 'woo-paypal-gateway'),
                        'authorize' => __('Authorize', 'woo-paypal-gateway'),
                    ),
                ),
                'invoice_id_prefix' => array(
                    'title' => __('Invoice prefix', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'woo-paypal-gateway'),
                    'default' => 'WC-PPCP',
                    'desc_tip' => true,
                ),
                'order_review_page_enable_coupons' => array(
                    'title' => __('Enable/Disable coupons', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable the use of coupon codes', 'woo-paypal-gateway'),
                    'description' => __('Coupons can be applied from the order review.', 'woo-paypal-gateway'),
                    'default' => 'no',
                ),
                'soft_descriptor' => array(
                    'title' => __('Credit Card Statement Name', 'woo-paypal-gateway'),
                    'type' => 'text',
                    'description' => __('The value entered here will be displayed on the buyer\'s credit card statement.', 'woo-paypal-gateway'),
                    'default' => '',
                    'desc_tip' => true,
                    'custom_attributes' => array('maxlength' => '22'),
                ),
                'debug' => array(
                    'title' => __('Debug log', 'woo-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'woo-paypal-gateway'),
                    'default' => 'no',
                    'description' => sprintf(__('Log PayPal events, such as Webhook, Payment, Refund inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woo-paypal-gateway'), '<code>' . WC_Log_Handler_File::get_log_file_path('wpg_paypal_checkout') . '</code>'),
                )
            );
            if ('yes' === get_option('woocommerce_enable_coupons')) {
                unset($advanced_settings['order_review_page_enable_coupons']);
            }
            $settings = apply_filters('ppcp_settings', array_merge($default_settings, $button_manager_settings, $pay_later_messaging_settings, $advanced_settings));
            return $settings;
        }

    }

}