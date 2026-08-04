<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CheckoutWC compatibility.
 *
 * CheckoutWC replaces the checkout template, so the plugin's button
 * placements move into CheckoutWC's slots. Taking over a slot also disables
 * the corresponding native placement — otherwise both render and the page
 * ends up with duplicate button containers, of which only the first ever
 * receives a button.
 *
 * Rendering is delegated to the button manager's own display methods so all
 * gateway/express settings, session state, zero-total checks and the full
 * express set (PayPal, Google Pay, Apple Pay, Fastlane) behave exactly as on
 * a stock checkout.
 */
class WPG_CheckoutWC_Compat {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( ! $this->is_checkoutwc_active() ) {
			return;
		}

		add_action( 'cfw_payment_request_buttons', array( $this, 'render_express_buttons' ), 10 );
		add_action( 'cfw_checkout_after_payment_methods', array( $this, 'render_checkout_buttons' ), 10 );
		add_action( 'wp', array( $this, 'suppress_native_placements' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_compat_styles' ), 20 );
		add_filter( 'wpg_ppcp_button_selectors', array( $this, 'add_checkoutwc_selectors' ) );
		add_action( 'wp_footer', array( $this, 'render_step_navigation_handler' ) );
	}

	private function is_checkoutwc_active() {
		return defined( 'CFW_NAME' ) || function_exists( 'cfw_is_checkout' ) || class_exists( 'Objectiv\Plugins\Checkout\Main', false );
	}

	/**
	 * Whether the current request renders CheckoutWC's checkout template.
	 *
	 * @return bool
	 */
	private function is_cfw_checkout() {
		return function_exists( 'cfw_is_checkout' ) && cfw_is_checkout();
	}

	/**
	 * On CheckoutWC pages the compat renders into CheckoutWC's slots, so the
	 * native WooCommerce-hook placements must not render a second copy of the
	 * same containers (duplicate element ids leave one container permanently
	 * empty). Reference behavior: taking over the slot disables the native
	 * render.
	 */
	public function suppress_native_placements() {
		if ( ! $this->is_cfw_checkout() ) {
			return;
		}
		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ) ) {
			return;
		}
		$button_manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
		remove_action( 'woocommerce_before_checkout_form', array( $button_manager, 'display_paypal_button_top_checkout_page' ), 10 );
		remove_action( 'woocommerce_review_order_after_submit', array( $button_manager, 'display_paypal_button_checkout_page' ) );
	}

	/**
	 * Render the express payment section in CheckoutWC's express slot.
	 *
	 * Delegates to the button manager so the express-enable settings, wallet
	 * toggles, Fastlane, active-session and zero-total guards all apply.
	 */
	public function render_express_buttons() {
		if ( ! $this->is_cfw_checkout() ) {
			return;
		}

		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ) ) {
			return;
		}

		$button_manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
		if ( ! $button_manager->is_valid_for_use() ) {
			return;
		}

		echo '<div class="wpg-checkoutwc-express-buttons">';
		$button_manager->display_paypal_button_top_checkout_page();
		echo '</div>';
	}

	/**
	 * Render checkout buttons after CheckoutWC's payment method list.
	 */
	public function render_checkout_buttons() {
		if ( ! $this->is_cfw_checkout() ) {
			return;
		}

		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ) ) {
			return;
		}

		$button_manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
		if ( ! $button_manager->is_valid_for_use() ) {
			return;
		}

		$button_manager->display_paypal_button_checkout_page();
	}

	/**
	 * Add CheckoutWC-specific selectors so the main JS picks up the containers.
	 *
	 * @param array $selectors Existing selectors.
	 * @return array Modified selectors.
	 */
	public function add_checkoutwc_selectors( $selectors ) {
		if ( $this->is_cfw_checkout() ) {
			$selectors['ppcp_checkout_top'] = '#ppcp_checkout_top';
			$selectors['ppcp_checkout']     = '#ppcp_checkout';
		}
		return $selectors;
	}

	/**
	 * Enqueue minimal CSS to fix button sizing within CheckoutWC's layout.
	 */
	public function enqueue_compat_styles() {
		if ( ! $this->is_cfw_checkout() ) {
			return;
		}

		$css = '
			.wpg-checkoutwc-express-buttons { margin: 10px 0; }
			.wpg-checkoutwc-express-buttons .ppcp-button-container { max-width: 100%; }
		';

		wp_add_inline_style( 'ppcp-paypal-checkout-for-woocommerce-public', $css );
	}

	/**
	 * Handle CheckoutWC's AJAX step navigation by re-triggering button render.
	 */
	public function render_step_navigation_handler() {
		if ( ! $this->is_cfw_checkout() ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function($) {
			$(document.body).on('cfw-after-tab-change', function() {
				$(document.body).trigger('ppcp_checkout_updated');
			});
		})(jQuery);
		</script>
		<?php
	}
}
