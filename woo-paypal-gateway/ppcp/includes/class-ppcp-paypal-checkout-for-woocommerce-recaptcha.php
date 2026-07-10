<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PPCP_Paypal_Checkout_For_Woocommerce_ReCaptcha {

	private static $_instance = null;
	private $settings;
	private $enabled;
	private $site_key;
	private $secret_key;
	private $threshold;
	private $log_enabled;
	private $skip_logged_in;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		$this->settings   = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		$this->enabled    = isset( $this->settings['recaptcha_enabled'] ) && $this->settings['recaptcha_enabled'] === 'yes';
		$this->site_key   = isset( $this->settings['recaptcha_site_key'] ) ? $this->settings['recaptcha_site_key'] : '';
		$this->secret_key = isset( $this->settings['recaptcha_secret_key'] ) ? $this->settings['recaptcha_secret_key'] : '';
		$this->threshold  = isset( $this->settings['recaptcha_threshold'] ) ? (float) $this->settings['recaptcha_threshold'] : 0.5;
		$this->log_enabled = isset( $this->settings['debug'] ) && $this->settings['debug'] === 'yes';
		$this->skip_logged_in = isset( $this->settings['recaptcha_skip_logged_in'] ) && $this->settings['recaptcha_skip_logged_in'] === 'yes';
	}

	public function init() {
		if ( ! $this->is_active() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'verify_checkout' ), 5 );
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'verify_blocks_checkout' ), 5 );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'output_hidden_field' ) );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'add_score_to_order_note' ) );
	}

	public function is_active() {
		return $this->enabled && ! empty( $this->site_key ) && ! empty( $this->secret_key );
	}

	public function get_site_key() {
		return $this->site_key;
	}

	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( $this->skip_logged_in && is_user_logged_in() ) {
			return;
		}

		wp_enqueue_script(
			'wpg-recaptcha-external',
			'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $this->site_key ),
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'wpg-recaptcha',
			WPG_PLUGIN_ASSET_URL . 'ppcp/public/js/wpg-recaptcha.js',
			array( 'wpg-recaptcha-external' ),
			WPG_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'wpg-recaptcha', 'wpg_recaptcha_params', array(
			'site_key' => $this->site_key,
			'actions'  => array( 'checkout', 'cc_checkout' ),
		) );
	}

	public function output_hidden_field() {
		echo '<input type="hidden" name="wpg_recaptcha_token" id="wpg_recaptcha_token" value="" />';
	}

	public function verify_checkout() {
		if ( $this->skip_logged_in && is_user_logged_in() ) {
			return;
		}

		if ( $this->has_paypal_approval() ) {
			$this->log( 'reCAPTCHA skipped — PayPal-approved session active.' );
			return;
		}

		$token = isset( $_POST['wpg_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['wpg_recaptcha_token'] ) ) : '';

		if ( empty( $token ) ) {
			$this->log( 'reCAPTCHA token missing from checkout submission — allowing payment (graceful degradation).' );
			return;
		}

		$result = $this->verify_token( $token );

		if ( is_wp_error( $result ) ) {
			$this->log( 'reCAPTCHA verification error: ' . $result->get_error_message() . ' — allowing payment.' );
			return;
		}

		if ( $result['pass'] === false ) {
			$this->log( sprintf( 'reCAPTCHA blocked checkout — score: %.2f, threshold: %.2f', $result['score'], $this->threshold ) );
			wc_add_notice(
				__( 'Payment verification failed. Please try again or contact support.', 'woo-paypal-gateway' ),
				'error'
			);
		} else {
			$this->log( sprintf( 'reCAPTCHA passed — score: %.2f', $result['score'] ) );
			if ( WC()->session ) {
				WC()->session->set( 'wpg_recaptcha_score', $result['score'] );
			}
		}
	}

	public function verify_blocks_checkout( $context ) {
		if ( $this->skip_logged_in && is_user_logged_in() ) {
			return;
		}

		if ( $this->has_paypal_approval() ) {
			$this->log( 'reCAPTCHA skipped (Blocks) — PayPal-approved session active.' );
			return;
		}

		$payment_data = $context->payment_data;
		$token = isset( $payment_data['wpg_recaptcha_token'] ) ? sanitize_text_field( $payment_data['wpg_recaptcha_token'] ) : '';

		if ( empty( $token ) ) {
			$this->log( 'reCAPTCHA token missing from Blocks checkout — allowing payment (graceful degradation).' );
			return;
		}

		$result = $this->verify_token( $token );

		if ( is_wp_error( $result ) ) {
			$this->log( 'reCAPTCHA verification error (Blocks): ' . $result->get_error_message() . ' — allowing payment.' );
			return;
		}

		if ( $result['pass'] === false ) {
			$this->log( sprintf( 'reCAPTCHA blocked Blocks checkout — score: %.2f, threshold: %.2f', $result['score'], $this->threshold ) );
			throw new \Exception(
				__( 'Payment verification failed. Please try again or contact support.', 'woo-paypal-gateway' )
			);
		} else {
			$this->log( sprintf( 'reCAPTCHA passed (Blocks) — score: %.2f', $result['score'] ) );
			if ( WC()->session ) {
				WC()->session->set( 'wpg_recaptcha_score', $result['score'] );
			}
		}
	}

	private function verify_token( $token ) {
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $this->secret_key,
				'response' => $token,
				'remoteip' => $this->get_client_ip(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body ) || ! isset( $body['success'] ) ) {
			return new WP_Error( 'recaptcha_invalid_response', 'Invalid reCAPTCHA API response.' );
		}

		if ( $body['success'] !== true ) {
			$errors = isset( $body['error-codes'] ) ? implode( ', ', $body['error-codes'] ) : 'unknown';
			return new WP_Error( 'recaptcha_failed', 'reCAPTCHA verification failed: ' . $errors );
		}

		$score = isset( $body['score'] ) ? (float) $body['score'] : 0.0;

		return array(
			'pass'  => $score >= $this->threshold,
			'score' => $score,
		);
	}

	public function add_score_to_order_note( $order_id ) {
		if ( ! $this->is_active() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$score = WC()->session ? WC()->session->get( 'wpg_recaptcha_score' ) : null;
		if ( $score !== null ) {
			$order->add_order_note( sprintf(
				// translators: %s: reCAPTCHA score (0.0 - 1.0).
				__( 'reCAPTCHA v3 score: %s', 'woo-paypal-gateway' ),
				number_format( (float) $score, 2 )
			) );
			if ( WC()->session ) {
				WC()->session->set( 'wpg_recaptcha_score', null );
			}
		}
	}

	private function has_paypal_approval() {
		if ( function_exists( 'ppcp_get_paypal_order_session_data' ) ) {
			$session_data = ppcp_get_paypal_order_session_data();
			$status = isset( $session_data['status'] ) ? strtolower( $session_data['status'] ) : '';
			if ( $status === 'approved' && ! empty( $session_data['id'] ) ) {
				return true;
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check, no state change.
		if ( ! empty( $_GET['paypal_order_id'] ) || ! empty( $_POST['paypal_order_id'] ) ) {
			return true;
		}
		return false;
	}

	private function get_client_ip() {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private function log( $message ) {
		if ( ! $this->log_enabled ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, array( 'source' => 'wpg_paypal_checkout' ) );
		}
	}
}
