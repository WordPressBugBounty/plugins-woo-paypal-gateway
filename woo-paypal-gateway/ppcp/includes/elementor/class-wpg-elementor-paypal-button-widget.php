<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPG_Elementor_PayPal_Button_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wpg-paypal-button';
	}

	public function get_title() {
		return esc_html__( 'PayPal Button', 'woo-paypal-gateway' );
	}

	public function get_icon() {
		return 'eicon-paypal-button';
	}

	public function get_categories() {
		return array( 'wpg-paypal', 'woocommerce-elements' );
	}

	public function get_keywords() {
		return array( 'paypal', 'payment', 'checkout', 'buy', 'google pay', 'apple pay' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_button_type',
			array(
				'label' => esc_html__( 'Button Settings', 'woo-paypal-gateway' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'button_type',
			array(
				'label'   => esc_html__( 'Button Type', 'woo-paypal-gateway' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'paypal',
				'options' => array(
					'paypal'  => esc_html__( 'PayPal Only', 'woo-paypal-gateway' ),
					'express' => esc_html__( 'All Express Buttons', 'woo-paypal-gateway' ),
				),
			)
		);

		$this->add_control(
			'show_google',
			array(
				'label'        => esc_html__( 'Show Google Pay', 'woo-paypal-gateway' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'woo-paypal-gateway' ),
				'label_off'    => esc_html__( 'No', 'woo-paypal-gateway' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'button_type' => 'express' ),
			)
		);

		$this->add_control(
			'show_apple',
			array(
				'label'        => esc_html__( 'Show Apple Pay', 'woo-paypal-gateway' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'woo-paypal-gateway' ),
				'label_off'    => esc_html__( 'No', 'woo-paypal-gateway' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array( 'button_type' => 'express' ),
			)
		);

		$this->add_control(
			'product_id',
			array(
				'label'       => esc_html__( 'Product ID', 'woo-paypal-gateway' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 0,
				'description' => esc_html__( 'Optional. Link button to a specific product for direct purchase.', 'woo-paypal-gateway' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_style',
			array(
				'label' => esc_html__( 'Button Style', 'woo-paypal-gateway' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'color',
			array(
				'label'   => esc_html__( 'Color', 'woo-paypal-gateway' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'gold',
				'options' => array(
					'gold'   => esc_html__( 'Gold', 'woo-paypal-gateway' ),
					'blue'   => esc_html__( 'Blue', 'woo-paypal-gateway' ),
					'silver' => esc_html__( 'Silver', 'woo-paypal-gateway' ),
					'white'  => esc_html__( 'White', 'woo-paypal-gateway' ),
					'black'  => esc_html__( 'Black', 'woo-paypal-gateway' ),
				),
			)
		);

		$this->add_control(
			'shape',
			array(
				'label'   => esc_html__( 'Shape', 'woo-paypal-gateway' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'rect',
				'options' => array(
					'rect' => esc_html__( 'Rectangle', 'woo-paypal-gateway' ),
					'pill' => esc_html__( 'Pill', 'woo-paypal-gateway' ),
				),
			)
		);

		$this->add_control(
			'height',
			array(
				'label'   => esc_html__( 'Height (px)', 'woo-paypal-gateway' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'min'  => 25,
						'max'  => 55,
						'step' => 1,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 48,
				),
			)
		);

		$this->add_control(
			'label',
			array(
				'label'   => esc_html__( 'Label', 'woo-paypal-gateway' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'paypal',
				'options' => array(
					'paypal'   => esc_html__( 'PayPal', 'woo-paypal-gateway' ),
					'checkout' => esc_html__( 'Checkout', 'woo-paypal-gateway' ),
					'buynow'   => esc_html__( 'Buy Now', 'woo-paypal-gateway' ),
					'pay'      => esc_html__( 'Pay', 'woo-paypal-gateway' ),
				),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Layout', 'woo-paypal-gateway' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'vertical',
				'options' => array(
					'vertical'   => esc_html__( 'Vertical', 'woo-paypal-gateway' ),
					'horizontal' => esc_html__( 'Horizontal', 'woo-paypal-gateway' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$height = isset( $settings['height']['size'] ) ? (int) $settings['height']['size'] : 48;

		$shortcode_atts = array(
			'color'  => $settings['color'],
			'shape'  => $settings['shape'],
			'height' => $height,
			'label'  => $settings['label'],
			'layout' => $settings['layout'],
		);

		if ( ! empty( $settings['product_id'] ) ) {
			$shortcode_atts['id']      = (int) $settings['product_id'];
			$shortcode_atts['context'] = 'product';
		}

		if ( 'express' === $settings['button_type'] ) {
			$shortcode_atts['show_google'] = ! empty( $settings['show_google'] ) ? 'yes' : 'no';
			$shortcode_atts['show_apple']  = ! empty( $settings['show_apple'] ) ? 'yes' : 'no';
			$shortcode = 'wpg_express_buttons';
		} else {
			$shortcode = 'wpg_paypal_button';
		}

		$atts_string = '';
		foreach ( $shortcode_atts as $key => $value ) {
			$atts_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}

		$output = do_shortcode( '[' . $shortcode . $atts_string . ']' );

		if ( empty( $output ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="wpg-elementor-placeholder" style="padding:20px;text-align:center;background:#f0f0f0;border:2px dashed #ccc;border-radius:4px;">';
				echo '<p style="margin:0;color:#666;">' . esc_html__( 'PayPal Button — will render on the live page.', 'woo-paypal-gateway' ) . '</p>';
				echo '<p style="margin:5px 0 0;color:#999;font-size:12px;">' . esc_html__( 'Ensure PayPal gateway is enabled in WooCommerce settings.', 'woo-paypal-gateway' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output already escaped
	}

	protected function content_template() {
		?>
		<div class="wpg-elementor-placeholder" style="padding:20px;text-align:center;background:#f0f0f0;border:2px dashed #ccc;border-radius:4px;">
			<p style="margin:0;color:#666;">
				<# if ( 'express' === settings.button_type ) { #>
					<?php echo esc_html__( 'PayPal Express Buttons', 'woo-paypal-gateway' ); ?>
				<# } else { #>
					<?php echo esc_html__( 'PayPal Button', 'woo-paypal-gateway' ); ?>
				<# } #>
			</p>
			<p style="margin:5px 0 0;color:#999;font-size:12px;">
				{{ settings.color }} · {{ settings.shape }} · {{ settings.height.size }}px
			</p>
		</div>
		<?php
	}
}
