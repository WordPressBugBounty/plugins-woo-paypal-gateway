<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

defined( 'ABSPATH' ) || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_3DS {

	const PROCEED = 1;
	const REJECT  = 2;
	const RETRY   = 3;

	/**
	 * Capture the payment, then hold the order for a human to look at.
	 *
	 * Used only by the "review" handling mode. Previously that mode returned PROCEED
	 * everywhere, making it indistinguishable from "accept" while its label promised the
	 * merchant something extra.
	 */
	const REVIEW = 4;

	const META_LIABILITY_SHIFT   = '_ppcp_3ds_liability_shift';
	const META_ENROLLMENT        = '_ppcp_3ds_enrollment';
	const META_AUTH_STATUS       = '_ppcp_3ds_auth_status';
	const META_DECISION          = '_ppcp_3ds_decision';
	const META_HISTORY           = '_ppcp_3ds_history';
	const META_REJECTIONS        = '_ppcp_3ds_rejections';
	const META_MERCHANT_LIABLE   = '_ppcp_3ds_merchant_liability';
	const META_NEEDS_REVIEW      = '_ppcp_3ds_needs_review';

	/**
	 * How many past evaluations to keep on an order.
	 *
	 * A shopper retrying a mistyped one-time code produces a handful of entries; someone
	 * cycling stolen cards against a single order could produce hundreds, so the array is
	 * bounded. The most recent attempts are the ones worth keeping.
	 */
	const HISTORY_LIMIT = 20;

	/**
	 * Issuer rejections allowed on one order before it stops accepting new payment attempts.
	 *
	 * Counts rejections only — an issuer answering "no, not this cardholder" (authentication
	 * status R). A failed challenge (status N) is NOT counted: that is the shopper who
	 * mistyped a one-time code, and it is the case RETRY exists to let them recover from.
	 * Counting those would lock a genuine customer out of their own order, which costs far
	 * more than the card testing it would prevent.
	 *
	 * Ten, deliberately generous. This limit is not what stops card testing — an attacker
	 * abandons the cart and starts another order, and the tally is back to zero, so the
	 * per-order count only ever reaches somebody committed to a single checkout, which is
	 * the shopper rather than the script. Set low it costs sales; set high it costs almost
	 * nothing, because the case it guards against does not stay on one order anyway. The
	 * shopper it does reach is the one whose bank keeps refusing while they work through
	 * which of their cards will go through, and they should not run out of road first.
	 *
	 * The per-address tally below is the signal that actually spans orders.
	 *
	 * Override with the wpg_ppcp_3ds_max_rejections filter.
	 */
	const DEFAULT_MAX_REJECTIONS = 10;

	/**
	 * Issuer rejections from one IP address, across every order, before the store is warned.
	 *
	 * The per-order limit above is keyed on the order, and an order is free for an attacker to
	 * recreate: abandon the cart, start another, and the count is back to zero. It stops a
	 * shopper hammering one checkout, not somebody working through a list of stolen cards.
	 * Card testing rotates the card, so the card is useless as a key — the address the attempts
	 * arrive from is the only thing that stays put, which is what this counts.
	 *
	 * Nothing is blocked on this count. Addresses are shared — offices, universities and mobile
	 * carriers put many genuine shoppers behind one of them — so refusing payments on this
	 * signal alone would cost real sales. It writes a warning the merchant can act on instead.
	 *
	 * Ten is deliberately well clear of ordinary use: a real shopper whose bank turns them down
	 * gives up long before it, while a script working through a card list passes it quickly.
	 *
	 * Override with the wpg_ppcp_3ds_velocity_threshold filter.
	 */
	const DEFAULT_VELOCITY_THRESHOLD = 10;

	/**
	 * How long rejections from one address accumulate before the tally starts again, in seconds.
	 *
	 * Measured from the first rejection in the run, not the most recent, so a slow trickle of
	 * attempts cannot hold the window open indefinitely and never trip the threshold.
	 *
	 * Override with the wpg_ppcp_3ds_velocity_window filter.
	 */
	const DEFAULT_VELOCITY_WINDOW = 21600;

	/**
	 * Transient prefix for the per-address tally.
	 */
	const VELOCITY_PREFIX = 'wpg_ppcp_3ds_vel_';

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
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'hold_order_flagged_for_review' ), 10, 3 );
	}

	public function get_handling_mode() {
		return isset( $this->settings['3ds_liability_handling'] ) ? $this->settings['3ds_liability_handling'] : 'smart';
	}

	/**
	 * Hold an order the "review" mode flagged, instead of letting it complete.
	 *
	 * The payment is captured either way — the shopper is charged and their order is placed.
	 * This only stops the order moving to processing/completed, so it waits in on-hold for
	 * the merchant to approve or refund it.
	 */
	public function hold_order_flagged_for_review( $status, $order_id, $order = null ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof \WC_Order ) {
			return $status;
		}
		if ( 'yes' !== $order->get_meta( self::META_NEEDS_REVIEW ) ) {
			return $status;
		}
		return 'on-hold';
	}

	/**
	 * Issuer rejections allowed on a single order before it stops accepting new attempts.
	 */
	public function get_max_rejections() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations.
		$max = (int) apply_filters( 'wpg_ppcp_3ds_max_rejections', self::DEFAULT_MAX_REJECTIONS );
		// A zero or negative threshold would refuse the shopper's first attempt and make card
		// payments impossible, which is never what a merchant filtering this wants.
		return $max > 0 ? $max : self::DEFAULT_MAX_REJECTIONS;
	}

	/**
	 * Issuer rejections from one address, across all orders, before the store is warned.
	 */
	public function get_velocity_threshold() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations.
		$threshold = (int) apply_filters( 'wpg_ppcp_3ds_velocity_threshold', self::DEFAULT_VELOCITY_THRESHOLD );
		// Zero or less would warn on the first rejection any shopper ever hits, burying the
		// real signal in noise, which defeats the point of watching for it at all.
		return $threshold > 0 ? $threshold : self::DEFAULT_VELOCITY_THRESHOLD;
	}

	/**
	 * How long rejections from one address accumulate before the tally restarts, in seconds.
	 */
	public function get_velocity_window() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations.
		$window = (int) apply_filters( 'wpg_ppcp_3ds_velocity_window', self::DEFAULT_VELOCITY_WINDOW );
		return $window > 0 ? $window : self::DEFAULT_VELOCITY_WINDOW;
	}

	/**
	 * Count an issuer rejection against the address it came from, and warn once if it is unusual.
	 *
	 * Detection only — this never refuses a payment. See DEFAULT_VELOCITY_THRESHOLD for why
	 * blocking on a shared address would cost more than the card testing it would stop.
	 *
	 * @param \WC_Order $order Order the rejected attempt belongs to.
	 * @return void
	 */
	private function record_ip_rejection( $order ) {
		if ( ! method_exists( $order, 'get_customer_ip_address' ) ) {
			return;
		}
		$ip = $order->get_customer_ip_address();
		if ( empty( $ip ) ) {
			return;
		}

		$window = $this->get_velocity_window();
		$now    = time();
		// Hashed so the stored key is a fixed, safe length whatever the address format. This is
		// key hygiene, not anonymisation — the address itself goes in the warning below, because
		// a merchant can only act on one they can read, and WooCommerce already records it on
		// the order either way.
		$key    = self::VELOCITY_PREFIX . md5( $ip );

		$tally = get_transient( $key );
		$started_within_window = is_array( $tally )
			&& isset( $tally['count'], $tally['started'] )
			&& ( $now - (int) $tally['started'] ) < $window;
		if ( ! $started_within_window ) {
			$tally = array(
				'count'   => 0,
				'started' => $now,
				'warned'  => false,
			);
		}

		$tally['count'] = (int) $tally['count'] + 1;

		$threshold = $this->get_velocity_threshold();
		if ( empty( $tally['warned'] ) && $tally['count'] >= $threshold ) {
			// Once per window. Every later rejection in the same run would repeat the same
			// finding, and a log the merchant has to scroll past is one they stop reading.
			$tally['warned'] = true;
			$this->log_warning( sprintf(
				'Possible card testing: %1$d card authentications from %2$s were rejected by the issuer within %3$d minutes, across orders. Most recent was order #%4$d. Nothing has been blocked — review these orders and consider blocking the address if the pattern looks automated.',
				$tally['count'],
				$ip,
				(int) round( $window / 60 ),
				$order->get_id()
			) );
		}

		set_transient( $key, $tally, $window );
	}

	/**
	 * How many issuer rejections this order has already collected.
	 */
	public function get_rejection_count( $order ) {
		return (int) $order->get_meta( self::META_REJECTIONS );
	}

	/**
	 * Whether this order has used up its allowance of issuer rejections.
	 */
	public function has_exhausted_attempts( $order ) {
		return $this->get_rejection_count( $order ) >= $this->get_max_rejections();
	}

	public function is_logging_enabled() {
		return isset( $this->settings['3ds_logging'] ) && $this->settings['3ds_logging'] === 'yes';
	}

	public function evaluate( $order, $response_object ) {
		// Deliberately no attempt-limit check here. The limit is enforced where new PayPal
		// orders are minted, not on the result of one that already exists: refusing here
		// would throw away a card that genuinely authenticated just because earlier attempts
		// on the same order failed, which is the ordinary shape of a shopper who mistyped a
		// one-time code and then got it right.
		if ( empty( $response_object ) ) {
			$this->log( 'No response object for 3DS evaluation, order #' . $order->get_id() );
			return self::RETRY;
		}

		$response = json_decode( json_encode( $response_object ), true );

		$auth_result = $this->extract_auth_result( $response );
		if ( empty( $auth_result ) ) {
			// No authentication result at all means nothing shifted the liability, so the
			// merchant carries this one — the flag has to say so explicitly here.
			$this->store_meta( $order, '', '', '', 'proceed_no_3ds', false );
			$this->log( 'No 3DS authentication result in response, order #' . $order->get_id() . ' — proceeding.' );
			return self::PROCEED;
		}

		$liability_shift = strtoupper( isset( $auth_result['liability_shift'] ) ? $auth_result['liability_shift'] : '' );
		$enrollment      = strtoupper( isset( $auth_result['three_d_secure']['enrollment_status'] ) ? $auth_result['three_d_secure']['enrollment_status'] : '' );
		$auth_status     = strtoupper( isset( $auth_result['three_d_secure']['authentication_status'] ) ? $auth_result['three_d_secure']['authentication_status'] : '' );

		$decision = $this->decide( $liability_shift, $enrollment, $auth_status );

		$decision_label = $this->decision_label( $decision );
		$this->store_meta( $order, $liability_shift, $enrollment, $auth_status, $decision_label, $this->shifts_liability( $liability_shift ) );

		// Noted after decide() so the note can state the outcome. Recording the raw codes
		// without it left the merchant to work out for themselves that, say, NO/Y/N meant
		// the capture had been blocked.
		$this->add_order_note( $order, $liability_shift, $enrollment, $auth_status, $decision_label );

		// Noted on the attempt that uses up the allowance, and only that one: the equality
		// test means a later rejection that pushes the count higher does not add a second
		// note, so the order carries one clear record rather than one per refusal.
		if ( 'reject' === $decision_label && $this->get_rejection_count( $order ) === $this->get_max_rejections() ) {
			$order->add_order_note( sprintf(
				/* translators: %d: number of issuer rejections allowed on one order. */
				__( '3D Secure limit reached: this order has been rejected by the card issuer %d times. It will not accept further payment attempts.', 'woo-paypal-gateway' ),
				$this->get_max_rejections()
			) );
		}

		$this->log( sprintf(
			'3DS evaluation for order #%d: liability=%s enrollment=%s auth=%s → %s',
			$order->get_id(), $liability_shift, $enrollment, $auth_status, $decision_label
		) );

		return $decision;
	}

	private function extract_auth_result( $response ) {
		// Direct Advanced Card gateway: the card sits at payment_source.card.
		if ( ! empty( $response['payment_source']['card']['authentication_result']['liability_shift'] ) ) {
			return $response['payment_source']['card']['authentication_result'];
		}
		// Card-backed wallets (Google Pay / Apple Pay) nest the underlying card under the
		// wallet key, so the 3DS authentication_result lives at
		// payment_source.{google_pay|apple_pay}.card.authentication_result.
		foreach ( array( 'google_pay', 'apple_pay' ) as $wallet ) {
			if ( ! empty( $response['payment_source'][ $wallet ]['card']['authentication_result']['liability_shift'] ) ) {
				return $response['payment_source'][ $wallet ]['card']['authentication_result'];
			}
		}
		return null;
	}

	/**
	 * Whether a fraud chargeback on this transaction falls to the card issuer.
	 *
	 * The Orders v2 spec documents POSSIBLE for a shifted liability, but PayPal also returns
	 * YES for a fully authenticated card in the field, so both are honored. Anything else
	 * leaves the merchant carrying the risk.
	 */
	private function shifts_liability( $liability_shift ) {
		return in_array( $liability_shift, array( 'POSSIBLE', 'YES' ), true );
	}

	private function decide( $liability_shift, $enrollment, $auth_status ) {
		$mode = $this->get_handling_mode();

		// Liability shifted to the card issuer — a fraud chargeback on this transaction
		// is the bank's responsibility, not the merchant's (and not ours). Always proceed,
		// and never hold it for review: there is nothing for a merchant to weigh up.
		if ( $this->shifts_liability( $liability_shift ) ) {
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
		// Card or issuer not enrolled in 3-D Secure and no authentication was attempted.
		// Legitimate shoppers routinely land here (non-participating cards), so declining
		// them would cost real sales and revenue — only the strict "reject" mode blocks these.
		if ( in_array( $enrollment, array( 'B', 'U', 'N' ), true ) && empty( $auth_status ) ) {
			if ( $mode === 'reject' ) {
				return self::REJECT;
			}
			if ( $mode === 'review' ) {
				return self::REVIEW;
			}
			return self::PROCEED;
		}

		// Issuer actively rejected authentication. Strong block signal — never retry.
		if ( $auth_status === 'R' ) {
			return self::REJECT;
		}

		// A challenge was expected but the cardholder did not pass or complete it
		// (N = failed authentication, C = challenge required but not completed). This is the
		// classic stolen-card signal: a fraudster cannot clear the issuer's challenge.
		if ( in_array( $auth_status, array( 'N', 'C' ), true ) ) {
			return $this->resolve_failed_auth( $mode );
		}

		// Unable to authenticate (often transient) or no status returned — treat as ambiguous.
		if ( $auth_status === 'U' || empty( $auth_status ) ) {
			return $this->resolve_ambiguous( $mode );
		}

		// Any other non-shifted outcome.
		return $this->resolve_ambiguous( $mode );
	}

	/**
	 * Decide what to do when a 3-D Secure challenge was presented but the cardholder did not
	 * successfully authenticate (auth status N or C) and liability did not shift.
	 *
	 * The default "smart" mode returns RETRY rather than a hard REJECT: a genuine cardholder
	 * who mistyped a one-time code can simply try again, while a fraudster who cannot clear
	 * the bank's challenge is stopped before the payment is captured. This protects against
	 * stolen-card chargebacks without declining legitimate customers or reducing sales volume.
	 */
	private function resolve_failed_auth( $mode ) {
		switch ( $mode ) {
			case 'reject':
				return self::REJECT;
			case 'smart':
				return self::RETRY;
			case 'review':
				return self::REVIEW;
			case 'accept':
			default:
				return self::PROCEED;
		}
	}

	private function resolve_ambiguous( $mode ) {
		switch ( $mode ) {
			case 'reject':
				return self::REJECT;
			// "smart" only interrupts on a positive authentication failure (handled in
			// resolve_failed_auth); a merely-unknown result carries no such signal, so we
			// proceed to avoid declining legitimate customers. Note this is the path that
			// captures an unauthenticated card with the merchant carrying the chargeback
			// risk — the order is flagged for it, see store_meta().
			case 'smart':
				return self::PROCEED;
			case 'review':
				return self::REVIEW;
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
			case self::REVIEW:
				return 'review';
			default:
				return 'unknown';
		}
	}

	private function add_order_note( $order, $liability_shift, $enrollment, $auth_status, $decision ) {
		$note = __( '3D Secure response', 'woo-paypal-gateway' ) . "\n";
		$note .= __( 'Liability Shift', 'woo-paypal-gateway' ) . ': ' . woo_paypal_gateway_ppcp_readable( $liability_shift ) . "\n";
		$note .= __( 'Enrollment Status', 'woo-paypal-gateway' ) . ': ' . ( $enrollment ?: '—' ) . "\n";
		$note .= __( 'Authentication Status', 'woo-paypal-gateway' ) . ': ' . ( $auth_status ?: '—' ) . "\n";
		$note .= __( 'Decision', 'woo-paypal-gateway' ) . ': ' . $this->decision_description( $decision ) . "\n";
		if ( 'yes' === $order->get_meta( self::META_MERCHANT_LIABLE ) ) {
			$note .= __( 'Liability', 'woo-paypal-gateway' ) . ': ' . __( 'Not covered by 3D Secure — the bank did not authenticate this card, so a fraud chargeback on this order would fall to your store rather than the card issuer. This is common and does not by itself mean the order is fraudulent.', 'woo-paypal-gateway' ) . "\n";
		}
		$note .= __( 'Handling Mode', 'woo-paypal-gateway' ) . ': ' . ucfirst( $this->get_handling_mode() );

		$order->add_order_note( $note );
	}

	/**
	 * Plain-language form of a decision label, for merchant-facing order notes.
	 */
	private function decision_description( $decision ) {
		switch ( $decision ) {
			case 'proceed':
				return __( 'Proceed — capture allowed', 'woo-paypal-gateway' );
			case 'proceed_no_3ds':
				return __( 'Proceed — no 3D Secure result was returned', 'woo-paypal-gateway' );
			case 'reject':
				return __( 'Rejected — capture blocked, shopper asked for another payment method', 'woo-paypal-gateway' );
			case 'retry':
				return __( 'Retry — capture blocked, shopper asked to try again', 'woo-paypal-gateway' );
			case 'review':
				return __( 'Review — payment captured, order held for you to check', 'woo-paypal-gateway' );
			default:
				return $decision;
		}
	}

	private function store_meta( $order, $liability_shift, $enrollment, $auth_status, $decision, $liability_shifted ) {
		$order->update_meta_data( self::META_LIABILITY_SHIFT, $liability_shift );
		$order->update_meta_data( self::META_ENROLLMENT, $enrollment );
		$order->update_meta_data( self::META_AUTH_STATUS, $auth_status );
		$order->update_meta_data( self::META_DECISION, $decision );

		// Flag orders the store will be charged back for if the payment turns out to be
		// fraudulent. Only meaningful once the capture actually goes ahead: a blocked
		// attempt costs the merchant nothing, so it is not marked.
		$captured = in_array( $decision, array( 'proceed', 'proceed_no_3ds', 'review' ), true );
		$order->update_meta_data( self::META_MERCHANT_LIABLE, ( $captured && ! $liability_shifted ) ? 'yes' : 'no' );

		if ( 'review' === $decision ) {
			$order->update_meta_data( self::META_NEEDS_REVIEW, 'yes' );
		}

		// Only a rejection counts. A retry is the recoverable case by design — the capture is
		// blocked either way, so no money moves — and counting it would lock out the shopper
		// who simply mistyped their one-time code.
		if ( 'reject' === $decision ) {
			$order->update_meta_data( self::META_REJECTIONS, $this->get_rejection_count( $order ) + 1 );
			// Same event counted a second way. The line above is what this order is allowed,
			// which an attacker escapes by starting another; this one follows the address the
			// attempts come from, which they cannot change as cheaply.
			$this->record_ip_rejection( $order );
		}

		$this->append_history( $order, $liability_shift, $enrollment, $auth_status, $decision );
		$order->save_meta_data();
	}

	/**
	 * Append this evaluation to the order's 3-D Secure history.
	 *
	 * The four scalar keys above are overwritten on every attempt, so an order whose shopper
	 * failed authentication several times before a different card succeeded would show only
	 * the final, successful result. That hides exactly the repeat-failure pattern a merchant
	 * investigating a chargeback needs to see, so every attempt is kept here as well.
	 */
	private function append_history( $order, $liability_shift, $enrollment, $auth_status, $decision ) {
		$history = $order->get_meta( self::META_HISTORY );
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = array(
			'time'       => time(),
			'liability'  => $liability_shift,
			'enrollment' => $enrollment,
			'auth'       => $auth_status,
			'decision'   => $decision,
		);

		if ( count( $history ) > self::HISTORY_LIMIT ) {
			$history = array_slice( $history, - self::HISTORY_LIMIT );
		}

		$order->update_meta_data( self::META_HISTORY, $history );
	}

	/**
	 * Read the stored history, normalised to an array.
	 */
	private function get_history( $order ) {
		$history = $order->get_meta( self::META_HISTORY );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * How many of an order's evaluations blocked the capture.
	 */
	private function count_blocked_attempts( $history ) {
		$blocked = 0;
		foreach ( $history as $entry ) {
			if ( isset( $entry['decision'] ) && in_array( $entry['decision'], array( 'reject', 'retry' ), true ) ) {
				$blocked++;
			}
		}
		return $blocked;
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
		$history  = $this->get_history( $order );

		if ( empty( $liability ) && empty( $decision ) && empty( $history ) ) {
			echo '<p>' . esc_html__( 'No 3D Secure data recorded for this order.', 'woo-paypal-gateway' ) . '</p>';
			return;
		}

		$status_colors = array(
			'proceed'        => '#46b450',
			'proceed_no_3ds' => '#999',
			'reject'         => '#dc3232',
			'retry'          => '#ffb900',
			'review'         => '#ffb900',
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

		if ( $this->has_exhausted_attempts( $order ) ) {
			echo '<p style="margin:10px 0 6px;padding:6px 8px;background:#fdf1f1;border-left:3px solid #dc3232;">';
			echo '<strong>' . esc_html__( 'Attempt limit reached.', 'woo-paypal-gateway' ) . '</strong> ';
			echo esc_html( sprintf(
				/* translators: %d: number of issuer rejections allowed on one order. */
				__( 'The card issuer rejected this order %d times, so it will not accept further payment attempts.', 'woo-paypal-gateway' ),
				$this->get_max_rejections()
			) );
			echo '</p>';
		}

		if ( 'yes' === $order->get_meta( self::META_NEEDS_REVIEW ) ) {
			echo '<p style="margin:10px 0 6px;padding:6px 8px;background:#fcf9e8;border-left:3px solid #ffb900;">';
			// This banner is the only place a merchant is told why the order is on hold, so it
			// has to explain the cause and the way out on its own.
			echo '<strong>' . esc_html__( 'Held for review.', 'woo-paypal-gateway' ) . '</strong> ';
			echo esc_html__( 'The payment was captured, so the customer has already been charged. The order stays on hold until you approve or refund it. It was held because Liability Shift Handling is set to Review and the bank did not authenticate this card — set that option to Accept if you would rather these orders complete on their own.', 'woo-paypal-gateway' );
			echo '</p>';
		}

		if ( 'yes' === $order->get_meta( self::META_MERCHANT_LIABLE ) ) {
			// Neutral styling on purpose. This applies to a large share of ordinary card orders
			// — any card whose bank does not authenticate it — and needs nothing from the
			// merchant, so it must not look like the attempt-limit warning above, which does.
			echo '<p style="margin:10px 0 6px;padding:6px 8px;background:#f6f7f7;border-left:3px solid #c3c4c7;">';
			echo '<strong>' . esc_html__( 'Not covered by 3D Secure.', 'woo-paypal-gateway' ) . '</strong> ';
			echo esc_html__( 'The bank did not authenticate this card, so if this order is later charged back as fraud, the cost falls to your store rather than the card issuer. This is common and does not by itself mean the order is fraudulent.', 'woo-paypal-gateway' );
			echo '</p>';
		}

		$this->render_3ds_history( $history, $status_colors );
	}

	/**
	 * Render the per-attempt history under the summary table.
	 *
	 * Only shown once an order has more than one evaluation: a single-attempt order is fully
	 * described by the summary above, and repeating it would be noise. An order that was
	 * blocked before it succeeded gets a warning, because the summary alone shows the final
	 * green "Proceed" and says nothing about what came before it.
	 */
	private function render_3ds_history( $history, $status_colors ) {
		if ( count( $history ) < 2 ) {
			return;
		}

		$blocked = $this->count_blocked_attempts( $history );
		if ( $blocked > 0 ) {
			echo '<p style="margin:10px 0 6px;padding:6px 8px;background:#fcf9e8;border-left:3px solid #ffb900;">';
			echo '<strong>' . esc_html( sprintf(
				/* translators: %d: number of 3D Secure attempts that were blocked. */
				_n(
					'%d earlier attempt on this order was blocked by 3D Secure.',
					'%d earlier attempts on this order were blocked by 3D Secure.',
					$blocked,
					'woo-paypal-gateway'
				),
				$blocked
			) ) . '</strong></p>';
		}

		echo '<table class="widefat striped" style="border:0;margin-top:6px;">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( '#', 'woo-paypal-gateway' ) . '</th>';
		echo '<th>' . esc_html__( 'Result', 'woo-paypal-gateway' ) . '</th>';
		echo '<th>' . esc_html__( 'Decision', 'woo-paypal-gateway' ) . '</th>';
		echo '</tr></thead><tbody>';

		$attempt = 0;
		foreach ( $history as $entry ) {
			$attempt++;
			$entry_decision = isset( $entry['decision'] ) ? $entry['decision'] : '';
			$entry_color    = isset( $status_colors[ $entry_decision ] ) ? $status_colors[ $entry_decision ] : '#999';

			$codes = sprintf(
				'%s / %s / %s',
				! empty( $entry['liability'] ) ? $entry['liability'] : '—',
				! empty( $entry['enrollment'] ) ? $entry['enrollment'] : '—',
				! empty( $entry['auth'] ) ? $entry['auth'] : '—'
			);

			echo '<tr>';
			echo '<td>' . esc_html( $attempt ) . '</td>';
			echo '<td><code>' . esc_html( $codes ) . '</code>';
			if ( ! empty( $entry['time'] ) ) {
				echo '<br /><span style="color:#666;">' . esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ) . '</span>';
			}
			echo '</td>';
			echo '<td><span style="color:' . esc_attr( $entry_color ) . ';font-weight:600;">';
			echo esc_html( ucfirst( str_replace( '_', ' ', $entry_decision ) ) ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="color:#666;margin-top:6px;">' . esc_html__( 'Liability / Enrollment / Authentication codes, oldest attempt first.', 'woo-paypal-gateway' ) . '</p>';
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

	/**
	 * Write a warning that stands on its own, whatever the verbose 3-D Secure log setting is.
	 *
	 * log() above is diagnostic detail a merchant opts into. A card-testing finding is not
	 * detail — it is the one thing here they would want to know without having gone looking, so
	 * it is not gated on that setting. WooCommerce's own log threshold still applies.
	 *
	 * @param string $message Warning to record.
	 * @return void
	 */
	private function log_warning( $message ) {
		if ( ! $this->logger ) {
			$this->logger = wc_get_logger();
		}
		$this->logger->warning( $message, array( 'source' => 'wpg_paypal_3ds' ) );
	}
}
