<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_WC_PayPal_Payments_Converter extends WPG_Plugin_Converter {

	protected $source_gateway_ids = array( 'ppcp-gateway', 'ppcp-credit-card-gateway' );

	protected $settings_option_keys = array(
		'woocommerce_ppcp-gateway_settings',
		'woocommerce_ppcp-credit-card-gateway_settings',
	);

	protected $order_meta_map = array(
		'_ppcp_paypal_order_id'      => '_wpg_paypal_order_id',
		'_ppcp_paypal_intent'        => '_paymentaction',
		'_ppcp_paypal_payment_mode'  => '_payment_status',
		'_paypal_fee'                => '_paypal_fee',
		'_paypal_fee_currency_code'  => '_paypal_fee_currency_code',
		'payment_token_id'           => '_payment_tokens_id',
	);

	protected $user_meta_map = array(
		'_ppcp_target_customer_id'  => 'wpg_ppcp_paypal_customer_id',
		'ppcp_customer_id'          => 'wpg_ppcp_paypal_customer_id',
		'ppcp_guest_customer_id'    => 'wpg_ppcp_paypal_customer_id',
	);

	public function __construct() {
		$this->settings_map = array(
			'enabled' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'enabled',
			),
			'title' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'title',
			),
			'description' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'description',
			),
			'intent' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'paymentaction',
				'transform'     => array( $this, 'transform_intent' ),
			),
			'button_color' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'button_color',
			),
			'button_shape' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'button_shape',
			),
			'button_label' => array(
				'source_option' => 'woocommerce_ppcp-gateway_settings',
				'target_key'    => 'button_label',
			),
		);
	}

	public function get_source_name() {
		return __( 'WooCommerce PayPal Payments (Official)', 'woo-paypal-gateway' );
	}

	public function get_source_slug() {
		return 'woocommerce-paypal-payments';
	}

	public function is_source_installed() {
		return defined( 'PAYPAL_API_URL' )
			|| class_exists( 'WooCommerce\PayPalCommerce\PluginModule', false )
			|| parent::is_source_installed();
	}

	public function get_source_cc_gateway_ids() {
		return array( 'ppcp-credit-card-gateway' );
	}

	public function get_runtime_payment_token_key() {
		return 'payment_token_id';
	}

	public function get_runtime_customer_id_keys() {
		return array( '_ppcp_target_customer_id', 'ppcp_customer_id', 'ppcp_guest_customer_id' );
	}

	public function transform_intent( $value ) {
		return 'AUTHORIZE' === strtoupper( $value ) ? 'authorize' : 'capture';
	}
}
