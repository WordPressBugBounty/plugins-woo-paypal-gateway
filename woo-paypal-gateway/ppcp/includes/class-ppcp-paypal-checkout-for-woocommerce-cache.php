<?php

defined( 'ABSPATH' ) || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_Cache {

	protected static $instance = null;

	private $prefix = 'wpg_ppcp_';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'update_option_woocommerce_wpg_paypal_checkout_settings', array( $this, 'on_settings_save' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_plugin_update' ), 10, 2 );
		add_action( 'wp_ajax_wpg_ppcp_clear_cache', array( $this, 'ajax_clear_cache' ) );

		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		if ( ! isset( $settings['cache_enabled'] ) || 'yes' === $settings['cache_enabled'] ) {
			add_action( 'wpg_ppcp_prewarm_client_token', array( $this, 'prewarm_client_token' ) );
			$this->schedule_prewarm();
		}
	}

	public function get( $key, $default = null ) {
		$value = get_transient( $this->prefix . $key );
		return false === $value ? $default : $value;
	}

	public function set( $key, $value, $ttl = HOUR_IN_SECONDS ) {
		return set_transient( $this->prefix . $key, $value, $ttl );
	}

	public function delete( $key ) {
		return delete_transient( $this->prefix . $key );
	}

	public function flush_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$transient_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s LIMIT 500",
				$wpdb->esc_like( '_transient_' . $this->prefix ) . '%',
				$wpdb->esc_like( '_transient_ppcp_' ) . '%',
				$wpdb->esc_like( '_transient_wpg_' ) . '%'
			)
		);

		foreach ( $transient_keys as $key ) {
			$transient_name = str_replace( '_transient_', '', $key );
			delete_transient( $transient_name );
		}

		do_action( 'wpg_ppcp_cache_flushed' );
	}

	public function on_settings_save( $old_value, $new_value ) {
		$keys_that_invalidate = array(
			'sandbox', 'rest_client_id_sandbox', 'rest_secret_id_sandbox',
			'rest_client_id_live', 'rest_secret_id_live', 'disable_funding',
			'advanced_card_payments', 'enabled_google_pay', 'enabled_apple_pay',
		);

		foreach ( $keys_that_invalidate as $key ) {
			$old = isset( $old_value[ $key ] ) ? $old_value[ $key ] : '';
			$new = isset( $new_value[ $key ] ) ? $new_value[ $key ] : '';
			if ( $old !== $new ) {
				$this->flush_all();
				return;
			}
		}
	}

	public function on_plugin_update( $upgrader, $options ) {
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}
		$plugins = isset( $options['plugins'] ) ? $options['plugins'] : array();
		if ( in_array( WPG_PLUGIN_BASENAME, $plugins, true ) ) {
			$this->flush_all();
		}
	}

	public function ajax_clear_cache() {
		check_ajax_referer( 'wpg_ppcp_clear_cache', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$this->flush_all();
		wp_send_json_success( array( 'message' => __( 'Cache cleared successfully.', 'woo-paypal-gateway' ) ) );
	}

	private function schedule_prewarm() {
		if ( ! wp_next_scheduled( 'wpg_ppcp_prewarm_client_token' ) ) {
			wp_schedule_event( time() + 45 * MINUTE_IN_SECONDS, 'hourly', 'wpg_ppcp_prewarm_client_token' );
		}
	}

	public function prewarm_client_token() {
		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		$advanced_cc = isset( $settings['advanced_card_payments'] ) && 'yes' === $settings['advanced_card_payments'];
		if ( ! $advanced_cc ) {
			return;
		}

		$is_sandbox = isset( $settings['sandbox'] ) && 'yes' === $settings['sandbox'];
		$token_key  = $is_sandbox ? 'ppcp_sandbox_client_token' : 'ppcp_client_token';
		$existing   = get_transient( $token_key );

		if ( false !== $existing ) {
			return;
		}

		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Request' ) ) {
			require_once WPG_PLUGIN_DIR . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce-request.php';
		}

		$request = PPCP_Paypal_Checkout_For_Woocommerce_Request::instance();
		$request->get_genrate_token();
	}
}

function wpg_ppcp_cache() {
	return PPCP_Paypal_Checkout_For_Woocommerce_Cache::instance();
}
