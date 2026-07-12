<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CheckoutWC compatibility.
 *
 * Handles integration with CheckoutWC's multi-step checkout layout:
 * - Ensures PayPal buttons render in CheckoutWC's express payment section
 * - Re-initializes buttons after CheckoutWC's AJAX step navigation
 * - Prevents CSS conflicts with CheckoutWC's design framework
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_compat_styles' ), 20 );
		add_filter( 'wpg_ppcp_button_selectors', array( $this, 'add_checkoutwc_selectors' ) );
		add_action( 'wp_footer', array( $this, 'render_step_navigation_handler' ) );
	}

	private function is_checkoutwc_active() {
		return function_exists( 'cfw_is_checkout' ) || class_exists( 'Objectiv\Plugins\Checkout\Main', false );
	}

	/**
	 * Render express payment buttons in CheckoutWC's express payment section.
	 */
	public function render_express_buttons() {
		if ( ! function_exists( 'cfw_is_checkout' ) || ! cfw_is_checkout() ) {
			return;
		}

		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ) ) {
			return;
		}

		$button_manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
		if ( ! $button_manager->is_valid_for_use() ) {
			return;
		}

		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
		$enabled  = isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];

		if ( ! $enabled ) {
			return;
		}

		echo '<div class="wpg-checkoutwc-express-buttons ppcp-button-container">';
		echo '<div id="ppcp_checkout_top" class="checkout"></div>';
		echo '</div>';
	}

	/**
	 * Render checkout buttons after CheckoutWC's payment method list.
	 */
	public function render_checkout_buttons() {
		if ( ! function_exists( 'cfw_is_checkout' ) || ! cfw_is_checkout() ) {
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
		if ( function_exists( 'cfw_is_checkout' ) && cfw_is_checkout() ) {
			$selectors['ppcp_checkout_top'] = '#ppcp_checkout_top';
		}
		return $selectors;
	}

	/**
	 * Enqueue minimal CSS to fix button sizing within CheckoutWC's layout.
	 */
	public function enqueue_compat_styles() {
		if ( ! function_exists( 'cfw_is_checkout' ) || ! cfw_is_checkout() ) {
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
		if ( ! function_exists( 'cfw_is_checkout' ) || ! cfw_is_checkout() ) {
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
