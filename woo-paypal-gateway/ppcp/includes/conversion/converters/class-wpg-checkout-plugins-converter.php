<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_Checkout_Plugins_Converter extends WPG_Plugin_Converter {

	protected $source_gateway_ids = array( 'cppw_paypal' );

	protected $settings_option_keys = array(
		'woocommerce_cppw_paypal_settings',
	);

	protected $order_meta_map = array(
		'_cppw_paypal_order_id'  => '_wpg_paypal_order_id',
		'_cppw_agreement_id'     => '_payment_tokens_id',
		'_cppw_environment'      => '_enviorment',
		'_paypal_fee'            => '_paypal_fee',
	);

	public function __construct() {
		$this->settings_map = array(
			'enabled' => array(
				'source_option' => 'woocommerce_cppw_paypal_settings',
				'target_key'    => 'enabled',
			),
			'title' => array(
				'source_option' => 'woocommerce_cppw_paypal_settings',
				'target_key'    => 'title',
			),
			'description' => array(
				'source_option' => 'woocommerce_cppw_paypal_settings',
				'target_key'    => 'description',
			),
		);
	}

	public function get_source_name() {
		return __( 'Checkout Plugins - PayPal for WooCommerce', 'woo-paypal-gateway' );
	}

	public function get_source_slug() {
		return 'checkout-paypal-woo';
	}

	public function get_runtime_payment_token_key() {
		return '_cppw_agreement_id';
	}
}
