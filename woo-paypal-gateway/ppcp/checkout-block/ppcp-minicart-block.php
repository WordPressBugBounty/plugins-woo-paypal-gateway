<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class PPCP_MiniCart_Block implements IntegrationInterface {

	private $settings;

	public function __construct() {
		$this->settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
	}

	public function get_name() {
		return 'wpg-ppcp-minicart';
	}

	public function initialize() {
		if ( ! $this->has_any_minicart_button() ) {
			return;
		}

		add_filter( 'render_block_woocommerce/mini-cart-footer-block', array( $this, 'inject_express_buttons' ), 100, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_paypal_sdk' ) );

		wp_register_script(
			'wpg-ppcp-minicart-block',
			WPG_PLUGIN_ASSET_URL . 'ppcp/checkout-block/ppcp-minicart.js',
			array( 'wp-element' ),
			WPG_PLUGIN_VERSION,
			true
		);
	}

	public function maybe_enqueue_paypal_sdk() {
		if ( is_checkout() || is_cart() || is_product() ) {
			return;
		}
		if ( wp_script_is( 'ppcp-checkout-js', 'registered' ) ) {
			wp_enqueue_script( 'ppcp-checkout-js' );
		}
		if ( wp_script_is( 'ppcp-paypal-checkout-for-woocommerce-public', 'registered' ) ) {
			wp_enqueue_script( 'ppcp-paypal-checkout-for-woocommerce-public' );
		}
		if ( wp_script_is( 'ppcp-paypal-checkout-for-woocommerce-public', 'registered' ) ) {
			wp_enqueue_style( 'ppcp-paypal-checkout-for-woocommerce-public' );
		}
	}

	public function get_script_handles() {
		return array( 'wpg-ppcp-minicart-block' );
	}

	public function get_editor_script_handles() {
		return array();
	}

	public function get_script_data() {
		$show_paypal   = $this->is_paypal_enabled_for_minicart();
		$show_gpay     = $this->is_google_pay_enabled_for_minicart();
		$show_applepay = $this->is_apple_pay_enabled_for_minicart();

		return array(
			'show_paypal'   => $show_paypal ? 'yes' : 'no',
			'show_gpay'     => $show_gpay ? 'yes' : 'no',
			'show_applepay' => $show_applepay ? 'yes' : 'no',
		);
	}

	public function inject_express_buttons( $content, $block ) {
		if ( ! $this->has_any_minicart_button() ) {
			return $content;
		}

		$buttons_html = $this->get_button_containers_html();
		if ( empty( $buttons_html ) ) {
			return $content;
		}

		$wrapper = '<div class="wpg-ppcp-minicart-express" data-wpg-minicart="1">' . $buttons_html . '</div>';

		$insert_pos = strpos( $content, '<div class="wc-block-mini-cart__footer-actions">' );
		if ( false !== $insert_pos ) {
			return substr_replace( $content, $wrapper, $insert_pos, 0 );
		}

		return $wrapper . $content;
	}

	private function get_button_containers_html() {
		$html = '';
		$button_height = $this->get_setting( 'mini_cart_button_height', 38 );
		$button_class  = 'responsive';

		if ( $this->is_paypal_enabled_for_minicart() ) {
			$html .= '<div id="ppcp_mini_cart_block" class="ppcp-minicart-button ' . esc_attr( $button_class ) . '" style="--button-height: ' . (int) $button_height . 'px;"></div>';
		}

		if ( $this->is_google_pay_enabled_for_minicart() ) {
			$html .= '<div data-context="mini_cart" class="google-pay-container mini_cart ppcp-minicart-button ' . esc_attr( $button_class ) . '" style="height: ' . (int) $button_height . 'px;"></div>';
		}

		if ( $this->is_apple_pay_enabled_for_minicart() ) {
			$html .= '<div data-context="mini_cart" class="apple-pay-container mini_cart ppcp-minicart-button ' . esc_attr( $button_class ) . '" style="height: ' . (int) $button_height . 'px;"></div>';
		}

		return $html;
	}

	private function has_any_minicart_button() {
		return $this->is_paypal_enabled_for_minicart()
			|| $this->is_google_pay_enabled_for_minicart()
			|| $this->is_apple_pay_enabled_for_minicart();
	}

	private function is_paypal_enabled_for_minicart() {
		$pages = isset( $this->settings['paypal_button_pages'] ) ? $this->settings['paypal_button_pages'] : array();
		if ( ! is_array( $pages ) ) {
			return false;
		}
		return in_array( 'mini_cart', $pages, true );
	}

	private function is_google_pay_enabled_for_minicart() {
		if ( ! isset( $this->settings['enabled_google_pay'] ) || $this->settings['enabled_google_pay'] !== 'yes' ) {
			return false;
		}
		$pages = isset( $this->settings['google_pay_pages'] ) ? $this->settings['google_pay_pages'] : array();
		if ( ! is_array( $pages ) ) {
			return false;
		}
		return in_array( 'mini_cart', $pages, true );
	}

	private function is_apple_pay_enabled_for_minicart() {
		if ( ! isset( $this->settings['enabled_apple_pay'] ) || $this->settings['enabled_apple_pay'] !== 'yes' ) {
			return false;
		}
		$pages = isset( $this->settings['apple_pay_pages'] ) ? $this->settings['apple_pay_pages'] : array();
		if ( ! is_array( $pages ) ) {
			return false;
		}
		return in_array( 'mini_cart', $pages, true );
	}

	private function get_setting( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}
}
