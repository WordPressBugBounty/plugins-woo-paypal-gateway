<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_Elementor_Integration {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'wpg-paypal',
			array(
				'title' => esc_html__( 'PayPal Gateway', 'woo-paypal-gateway' ),
				'icon'  => 'eicon-paypal-button',
			)
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once dirname( __FILE__ ) . '/class-wpg-elementor-paypal-button-widget.php';
		$widgets_manager->register( new WPG_Elementor_PayPal_Button_Widget() );
	}
}
