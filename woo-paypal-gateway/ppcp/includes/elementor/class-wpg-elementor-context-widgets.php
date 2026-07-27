<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.
/**
 * Context-specific Elementor widgets, modeled on the reference implementation:
 * cart payment buttons, product payment buttons, and a product Pay Later
 * message. Each renders the plugin's real frontend output so the live buttons
 * always match the gateway configuration.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared base: renders real plugin output on the matching page context and a
 * static placeholder inside the Elementor editor.
 */
abstract class WPG_Elementor_Abstract_Context_Widget extends \Elementor\Widget_Base {

	public function get_categories() {
		return array( 'wpg-paypal' );
	}

	public function get_icon() {
		return 'eicon-button';
	}

	protected function is_editor() {
		return class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	protected function render_editor_placeholder( $label ) {
		echo '<div style="border:1px dashed #b0b5bf;border-radius:4px;padding:18px;text-align:center;background:#f8f9fb;color:#50575e;font-size:13px;">';
		echo esc_html( $label );
		echo '</div>';
	}
}

/**
 * PayPal smart buttons for the cart page.
 */
class WPG_Elementor_Cart_Buttons_Widget extends WPG_Elementor_Abstract_Context_Widget {

	public function get_name() {
		return 'wpg_ppcp_cart_buttons';
	}

	public function get_title() {
		return __( 'PayPal Cart Buttons', 'woo-paypal-gateway' );
	}

	protected function render() {
		if ( $this->is_editor() ) {
			$this->render_editor_placeholder( __( 'PayPal smart buttons render here on the cart page (styling follows the gateway settings).', 'woo-paypal-gateway' ) );
			return;
		}
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ) ) {
			return;
		}
		$manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
		if ( method_exists( $manager, 'display_paypal_button_cart_page' ) ) {
			// The default cart placement is suppressed so the widget placement wins.
			remove_action( 'woocommerce_proceed_to_checkout', array( $manager, 'display_paypal_button_cart_page' ) );
			$manager->display_paypal_button_cart_page();
		}
	}
}

/**
 * PayPal smart buttons for the single product page.
 */
class WPG_Elementor_Product_Buttons_Widget extends WPG_Elementor_Abstract_Context_Widget {

	public function get_name() {
		return 'wpg_ppcp_product_buttons';
	}

	public function get_title() {
		return __( 'PayPal Product Buttons', 'woo-paypal-gateway' );
	}

	protected function render() {
		if ( $this->is_editor() ) {
			$this->render_editor_placeholder( __( 'PayPal smart buttons render here on the single product page (styling follows the gateway settings).', 'woo-paypal-gateway' ) );
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		if ( ! class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager' ) ) {
			return;
		}
		$manager = PPCP_Paypal_Checkout_For_Woocommerce_Button_Manager::instance();
		if ( method_exists( $manager, 'display_paypal_button_product_page' ) ) {
			remove_action( 'woocommerce_after_add_to_cart_form', array( $manager, 'display_paypal_button_product_page' ), 1 );
			$manager->display_paypal_button_product_page();
		}
	}
}

/**
 * PayPal Pay Later message for the single product page.
 */
class WPG_Elementor_Pay_Later_Message_Widget extends WPG_Elementor_Abstract_Context_Widget {

	public function get_name() {
		return 'wpg_ppcp_paylater_message';
	}

	public function get_title() {
		return __( 'PayPal Pay Later Message', 'woo-paypal-gateway' );
	}

	public function get_icon() {
		return 'eicon-notification';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array(
			'label' => __( 'Pay Later Message', 'woo-paypal-gateway' ),
		) );
		$this->add_control( 'note', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => __( 'The message layout, logo, text size and color are configured under WooCommerce &rarr; Settings &rarr; Payments &rarr; PayPal &rarr; Pay Later Messaging.', 'woo-paypal-gateway' ),
		) );
		$this->end_controls_section();
	}

	protected function render() {
		if ( $this->is_editor() ) {
			$this->render_editor_placeholder( __( 'PayPal Pay Later message renders here on the single product page.', 'woo-paypal-gateway' ) );
			return;
		}
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		echo '<div class="ppcp_message_product"></div>';
	}
}
