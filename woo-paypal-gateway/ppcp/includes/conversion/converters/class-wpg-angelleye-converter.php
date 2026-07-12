<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_AngellEYE_Converter extends WPG_Plugin_Converter {

	protected $source_gateway_ids = array( 'angelleye_ppcp', 'paypal_express' );

	protected $settings_option_keys = array(
		'woocommerce_angelleye_ppcp_settings',
		'woocommerce_paypal_express_settings',
	);

	protected $order_meta_map = array(
		'_payment_tokens_id'          => '_payment_tokens_id',
		'_transaction_id'             => '_transaction_id',
		'_paypal_fee'                 => '_paypal_fee',
		'_paypal_fee_currency_code'   => '_paypal_fee_currency_code',
		'_payment_action'             => '_paymentaction',
	);

	protected $user_meta_map = array(
		'angelleye_ppcp_paypal_customer_id'         => 'wpg_ppcp_paypal_customer_id',
		'sandbox_angelleye_ppcp_paypal_customer_id' => 'wpg_ppcp_paypal_customer_id',
	);

	public function __construct() {
		$this->settings_map = array(
			'enabled' => array(
				'source_option' => 'woocommerce_angelleye_ppcp_settings',
				'target_key'    => 'enabled',
			),
			'title' => array(
				'source_option' => 'woocommerce_angelleye_ppcp_settings',
				'target_key'    => 'title',
			),
			'paymentaction' => array(
				'source_option' => 'woocommerce_angelleye_ppcp_settings',
				'target_key'    => 'paymentaction',
			),
			'button_color' => array(
				'source_option' => 'woocommerce_angelleye_ppcp_settings',
				'target_key'    => 'button_color',
			),
			'button_shape' => array(
				'source_option' => 'woocommerce_angelleye_ppcp_settings',
				'target_key'    => 'button_shape',
			),
			'button_label' => array(
				'source_option' => 'woocommerce_angelleye_ppcp_settings',
				'target_key'    => 'button_label',
			),
		);
	}

	public function get_source_name() {
		return __( 'AngellEYE PayPal for WooCommerce', 'woo-paypal-gateway' );
	}

	public function get_source_slug() {
		return 'angelleye-paypal-for-woocommerce';
	}

	public function is_source_installed() {
		return defined( 'ANGELLEYE_PPCP_VERSION' )
			|| class_exists( 'AngellEYE_Gateway_Paypal', false )
			|| parent::is_source_installed();
	}

	public function get_runtime_payment_token_key() {
		return '_payment_tokens_id';
	}

	public function get_runtime_customer_id_keys() {
		return array( 'angelleye_ppcp_paypal_customer_id', 'sandbox_angelleye_ppcp_paypal_customer_id' );
	}
}
