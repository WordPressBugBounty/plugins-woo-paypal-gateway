<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_Pymntpl_Converter extends WPG_Plugin_Converter {

	protected $source_gateway_ids = array( 'ppcp', 'ppcp_card', 'ppcp_applepay', 'ppcp_googlepay' );

	protected $settings_option_keys = array(
		'woocommerce_ppcp_settings',
		'woocommerce_ppcp_card_settings',
		'woocommerce_ppcp_api_settings',
		'woocommerce_ppcp_advanced_settings',
		'woocommerce_ppcp_googlepay_settings',
		'woocommerce_ppcp_applepay_settings',
	);

	protected $order_meta_map = array(
		'_ppcp_paypal_order_id'  => '_wpg_paypal_order_id',
		'_ppcp_authorization_id' => '_auth_transaction_id',
		'_ppcp_environment'      => '_enviorment',
		'_paypal_fee'            => '_paypal_fee',
		'_paypal_payer_id'       => '_paypal_payer_id',
		'_billing_agreement_id'  => '_payment_tokens_id',
		'_payment_method_token'  => '_payment_tokens_id',
	);

	protected $user_meta_map = array(
		'wc_ppcp_customer_id' => 'wpg_ppcp_paypal_customer_id',
	);

	public function __construct() {
		$this->settings_map = array(
			'enabled' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'enabled',
			),
			'title_text' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'title',
			),
			'description' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'description',
			),
			'button_color' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'button_color',
			),
			'button_shape' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'button_shape',
			),
			'button_label' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'button_label',
			),
			'button_height' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'button_height',
			),
			'transaction_type' => array(
				'source_option' => 'woocommerce_ppcp_settings',
				'target_key'    => 'paymentaction',
				'transform'     => array( $this, 'transform_transaction_type' ),
			),
			'environment' => array(
				'source_option' => 'woocommerce_ppcp_api_settings',
				'target_key'    => 'sandbox',
				'transform'     => array( $this, 'transform_environment' ),
			),
			'client_id_sandbox' => array(
				'source_option' => 'woocommerce_ppcp_api_settings',
				'target_key'    => 'sandbox_client_id',
			),
			'secret_key_sandbox' => array(
				'source_option' => 'woocommerce_ppcp_api_settings',
				'target_key'    => 'sandbox_secret_id',
			),
			'client_id_production' => array(
				'source_option' => 'woocommerce_ppcp_api_settings',
				'target_key'    => 'api_client_id',
			),
			'secret_key_production' => array(
				'source_option' => 'woocommerce_ppcp_api_settings',
				'target_key'    => 'api_secret',
			),
			'card_save_enabled' => array(
				'source_option' => 'woocommerce_ppcp_card_settings',
				'target_key'    => 'enable_save_card',
			),
			'3ds_enabled' => array(
				'source_option' => 'woocommerce_ppcp_card_settings',
				'target_key'    => 'threed_secure_contingency',
				'transform'     => array( $this, 'transform_3ds' ),
			),
		);
	}

	public function get_source_name() {
		return __( 'Payment Plugins for PayPal WooCommerce', 'woo-paypal-gateway' );
	}

	public function get_source_slug() {
		return 'pymntpl-paypal-woocommerce';
	}

	public function get_source_cc_gateway_ids() {
		return array( 'ppcp_card' );
	}

	public function get_runtime_payment_token_key() {
		return '_payment_method_token';
	}

	public function get_runtime_customer_id_keys() {
		return array( 'wc_ppcp_customer_id' );
	}

	public function transform_transaction_type( $value ) {
		return 'authorize' === $value ? 'authorize' : 'capture';
	}

	public function transform_environment( $value ) {
		return 'sandbox' === $value ? 'yes' : 'no';
	}

	public function transform_3ds( $value ) {
		return 'yes' === $value ? 'SCA_ALWAYS' : 'SCA_WHEN_REQUIRED';
	}
}
