<?php

defined( 'ABSPATH' ) || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_3DS {

	const PROCEED = 1;
	const REJECT  = 2;
	const RETRY   = 3;

	const META_LIABILITY_SHIFT = '_ppcp_3ds_liability_shift';
	const META_ENROLLMENT      = '_ppcp_3ds_enrollment';
	const META_AUTH_STATUS     = '_ppcp_3ds_auth_status';
	const META_DECISION        = '_ppcp_3ds_decision';

	private $settings;
	private $logger;

	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
	}

	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_3ds_meta_box' ) );
	}

	public function get_handling_mode() {
		return isset( $this->settings['3ds_liability_handling'] ) ? $this->settings['3ds_liability_handling'] : 'accept';
	}

	public function is_logging_enabled() {
		return isset( $this->settings['3ds_logging'] ) && $this->settings['3ds_logging'] === 'yes';
	}

	public function evaluate( $order, $response_object ) {
		if ( empty( $response_object ) ) {
			$this->log( 'No response object for 3DS evaluation, order #' . $order->get_id() );
			return self::RETRY;
		}

		$response = json_decode( json_encode( $response_object ), true );

		$auth_result = $this->extract_auth_result( $response );
		if ( empty( $auth_result ) ) {
			$this->store_meta( $order, '', '', '', 'proceed_no_3ds' );
			$this->log( 'No 3DS authentication result in response, order #' . $order->get_id() . ' — proceeding.' );
			return self::PROCEED;
		}

		$liability_shift = strtoupper( isset( $auth_result['liability_shift'] ) ? $auth_result['liability_shift'] : '' );
		$enrollment      = strtoupper( isset( $auth_result['three_d_secure']['enrollment_status'] ) ? $auth_result['three_d_secure']['enrollment_status'] : '' );
		$auth_status     = strtoupper( isset( $auth_result['three_d_secure']['authentication_status'] ) ? $auth_result['three_d_secure']['authentication_status'] : '' );

		$this->add_order_note( $order, $liability_shift, $enrollment, $auth_status );

		$decision = $this->decide( $liability_shift, $enrollment, $auth_status );

		$decision_label = $this->decision_label( $decision );
		$this->store_meta( $order, $liability_shift, $enrollment, $auth_status, $decision_label );

		$this->log( sprintf(
			'3DS evaluation for order #%d: liability=%s enrollment=%s auth=%s → %s',
			$order->get_id(), $liability_shift, $enrollment, $auth_status, $decision_label
		) );

		return $decision;
	}

	private function extract_auth_result( $response ) {
		if ( ! empty( $response['payment_source']['card']['authentication_result']['liability_shift'] ) ) {
			return $response['payment_source']['card']['authentication_result'];
		}
		return null;
	}

	private function decide( $liability_shift, $enrollment, $auth_status ) {
		$mode = $this->get_handling_mode();

		if ( $liability_shift === 'POSSIBLE' ) {
			return self::PROCEED;
		}

		if ( $liability_shift === 'UNKNOWN' ) {
			return $this->resolve_ambiguous( $mode );
		}

		if ( $liability_shift === 'NO' ) {
			return $this->decide_no_shift( $enrollment, $auth_status, $mode );
		}

		return self::PROCEED;
	}

	private function decide_no_shift( $enrollment, $auth_status, $mode ) {
		if ( in_array( $enrollment, array( 'B', 'U', 'N' ), true ) && empty( $auth_status ) ) {
			if ( $mode === 'reject' ) {
				return self::REJECT;
			}
			return self::PROCEED;
		}

		if ( $auth_status === 'R' ) {
			return self::REJECT;
		}

		if ( $auth_status === 'N' ) {
			if ( $mode === 'accept' ) {
				return self::PROCEED;
			}
			return self::REJECT;
		}

		if ( $auth_status === 'U' ) {
			return $this->resolve_ambiguous( $mode );
		}

		if ( empty( $auth_status ) ) {
			return $this->resolve_ambiguous( $mode );
		}

		if ( $mode === 'reject' ) {
			return self::REJECT;
		}
		return self::PROCEED;
	}

	private function resolve_ambiguous( $mode ) {
		switch ( $mode ) {
			case 'reject':
				return self::REJECT;
			case 'review':
				return self::PROCEED;
			case 'accept':
			default:
				return self::PROCEED;
		}
	}

	private function decision_label( $decision ) {
		switch ( $decision ) {
			case self::PROCEED:
				return 'proceed';
			case self::REJECT:
				return 'reject';
			case self::RETRY:
				return 'retry';
			default:
				return 'unknown';
		}
	}

	private function add_order_note( $order, $liability_shift, $enrollment, $auth_status ) {
		$note = __( '3D Secure response', 'woo-paypal-gateway' ) . "\n";
		$note .= __( 'Liability Shift', 'woo-paypal-gateway' ) . ': ' . ppcp_readable( $liability_shift ) . "\n";
		$note .= __( 'Enrollment Status', 'woo-paypal-gateway' ) . ': ' . ( $enrollment ?: '—' ) . "\n";
		$note .= __( 'Authentication Status', 'woo-paypal-gateway' ) . ': ' . ( $auth_status ?: '—' ) . "\n";
		$note .= __( 'Handling Mode', 'woo-paypal-gateway' ) . ': ' . ucfirst( $this->get_handling_mode() );

		$order->add_order_note( $note );
	}

	private function store_meta( $order, $liability_shift, $enrollment, $auth_status, $decision ) {
		$order->update_meta_data( self::META_LIABILITY_SHIFT, $liability_shift );
		$order->update_meta_data( self::META_ENROLLMENT, $enrollment );
		$order->update_meta_data( self::META_AUTH_STATUS, $auth_status );
		$order->update_meta_data( self::META_DECISION, $decision );
		$order->save_meta_data();
	}

	public function add_3ds_meta_box() {
		$screen = $this->get_order_screen();
		if ( ! $screen ) {
			return;
		}
		add_meta_box(
			'ppcp-3ds-details',
			__( '3D Secure Details', 'woo-paypal-gateway' ),
			array( $this, 'render_3ds_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	public function render_3ds_meta_box( $post_or_order ) {
		$order = $this->get_order_from_screen( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( 'wpg_paypal_checkout_cc' !== $payment_method ) {
			echo '<p>' . esc_html__( 'Not a credit card payment.', 'woo-paypal-gateway' ) . '</p>';
			return;
		}

		$liability = $order->get_meta( self::META_LIABILITY_SHIFT );
		$enrollment = $order->get_meta( self::META_ENROLLMENT );
		$auth_status = $order->get_meta( self::META_AUTH_STATUS );
		$decision = $order->get_meta( self::META_DECISION );

		if ( empty( $liability ) && empty( $decision ) ) {
			echo '<p>' . esc_html__( 'No 3D Secure data recorded for this order.', 'woo-paypal-gateway' ) . '</p>';
			return;
		}

		$status_colors = array(
			'proceed'        => '#46b450',
			'proceed_no_3ds' => '#999',
			'reject'         => '#dc3232',
			'retry'          => '#ffb900',
		);
		$color = isset( $status_colors[ $decision ] ) ? $status_colors[ $decision ] : '#999';

		echo '<table class="widefat striped" style="border:0;">';
		echo '<tr><td><strong>' . esc_html__( 'Liability Shift', 'woo-paypal-gateway' ) . '</strong></td>';
		echo '<td>' . esc_html( $liability ?: '—' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Enrollment', 'woo-paypal-gateway' ) . '</strong></td>';
		echo '<td>' . esc_html( $enrollment ?: '—' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Auth Status', 'woo-paypal-gateway' ) . '</strong></td>';
		echo '<td>' . esc_html( $auth_status ?: '—' ) . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'Decision', 'woo-paypal-gateway' ) . '</strong></td>';
		echo '<td><span style="color:' . esc_attr( $color ) . ';font-weight:600;">' . esc_html( ucfirst( str_replace( '_', ' ', $decision ) ) ) . '</span></td></tr>';
		echo '</table>';
	}

	private function get_order_screen() {
		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
			if ( $controller && is_callable( array( $controller, 'custom_orders_table_usage_is_enabled' ) ) && $controller->custom_orders_table_usage_is_enabled() ) {
				return wc_get_page_screen_id( 'shop-order' );
			}
		}
		return 'shop_order';
	}

	private function get_order_from_screen( $post_or_order ) {
		if ( $post_or_order instanceof \WC_Order ) {
			return $post_or_order;
		}
		if ( $post_or_order instanceof \WP_Post ) {
			return wc_get_order( $post_or_order->ID );
		}
		global $theorder;
		if ( $theorder instanceof \WC_Order ) {
			return $theorder;
		}
		return null;
	}

	private function log( $message ) {
		if ( ! $this->is_logging_enabled() ) {
			return;
		}
		if ( ! $this->logger ) {
			$this->logger = wc_get_logger();
		}
		$this->logger->info( $message, array( 'source' => 'wpg_paypal_3ds' ) );
	}
}
