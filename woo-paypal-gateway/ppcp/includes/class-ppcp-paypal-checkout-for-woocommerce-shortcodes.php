<?php

defined( 'ABSPATH' ) || exit;

class PPCP_Paypal_Checkout_For_Woocommerce_Shortcodes {

    private static $instance = null;
    private $shortcode_containers = array();
    private $counter = 0;
    private $settings;
    private $enabled;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );
        $this->enabled  = isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    public function init() {
        if ( ! $this->enabled ) {
            return;
        }

        add_shortcode( 'wpg_paypal_button', array( $this, 'render_paypal_button' ) );
        add_shortcode( 'wpg_express_buttons', array( $this, 'render_express_buttons' ) );
        add_action( 'wp_footer', array( $this, 'render_shortcode_initializer' ), 5 );
    }

    private function get_setting( $key, $default = '' ) {
        return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
    }

    public function render_paypal_button( $atts ) {
        if ( ! $this->enabled || is_admin() ) {
            return '';
        }

        $atts = shortcode_atts( array(
            'context' => 'standalone',
            'id'      => 0,
            'color'   => '',
            'shape'   => '',
            'height'  => '',
            'label'   => '',
            'layout'  => '',
            'size'    => '',
        ), $atts, 'wpg_paypal_button' );

        $this->counter++;
        $container_id = 'wpg_shortcode_' . $this->counter;

        $color  = ! empty( $atts['color'] ) ? $atts['color'] : $this->get_setting( 'product_button_color', 'gold' );
        $shape  = ! empty( $atts['shape'] ) ? $atts['shape'] : $this->get_setting( 'product_button_shape', 'rect' );
        $height = ! empty( $atts['height'] ) ? absint( $atts['height'] ) : absint( $this->get_setting( 'product_button_height', 48 ) );
        $label  = ! empty( $atts['label'] ) ? $atts['label'] : $this->get_setting( 'product_button_label', 'paypal' );
        $layout = ! empty( $atts['layout'] ) ? $atts['layout'] : 'vertical';
        $size   = ! empty( $atts['size'] ) ? $atts['size'] : 'responsive';

        $height = max( 25, min( 55, $height ) );

        $this->shortcode_containers[] = array(
            'container_id' => $container_id,
            'type'         => 'paypal',
            'context'      => sanitize_key( $atts['context'] ),
            'product_id'   => absint( $atts['id'] ),
            'color'        => sanitize_key( $color ),
            'shape'        => sanitize_key( $shape ),
            'height'       => $height,
            'label'        => sanitize_key( $label ),
            'layout'       => sanitize_key( $layout ),
        );

        $this->ensure_scripts_enqueued();

        $classes = 'wpg-shortcode-button ' . esc_attr( $size );

        ob_start();
        ?>
        <div class="ppcp-button-container wpg-shortcode-container">
            <div id="<?php echo esc_attr( $container_id ); ?>"
                 class="<?php echo esc_attr( $classes ); ?>"
                 style="--button-height: <?php echo (int) $height; ?>px;"
                 data-wpg-shortcode="1"
                 data-wpg-context="<?php echo esc_attr( $atts['context'] ); ?>"
                 <?php if ( $atts['id'] ) : ?>
                     data-wpg-product-id="<?php echo absint( $atts['id'] ); ?>"
                 <?php endif; ?>
            ></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_express_buttons( $atts ) {
        if ( ! $this->enabled || is_admin() ) {
            return '';
        }

        $atts = shortcode_atts( array(
            'context'      => 'standalone',
            'id'           => 0,
            'height'       => '',
            'color'        => '',
            'shape'        => '',
            'label'        => '',
            'layout'       => '',
            'size'         => '',
            'show_paypal'  => 'yes',
            'show_google'  => 'yes',
            'show_apple'   => 'yes',
        ), $atts, 'wpg_express_buttons' );

        $this->counter++;
        $container_id = 'wpg_shortcode_' . $this->counter;

        $height = ! empty( $atts['height'] ) ? absint( $atts['height'] ) : absint( $this->get_setting( 'product_button_height', 48 ) );
        $color  = ! empty( $atts['color'] ) ? $atts['color'] : $this->get_setting( 'product_button_color', 'gold' );
        $shape  = ! empty( $atts['shape'] ) ? $atts['shape'] : $this->get_setting( 'product_button_shape', 'rect' );
        $label  = ! empty( $atts['label'] ) ? $atts['label'] : $this->get_setting( 'product_button_label', 'paypal' );
        $layout = ! empty( $atts['layout'] ) ? $atts['layout'] : 'vertical';
        $size   = ! empty( $atts['size'] ) ? $atts['size'] : 'responsive';

        $height = max( 25, min( 55, $height ) );

        $show_paypal = 'yes' === $atts['show_paypal'];
        $show_google = 'yes' === $atts['show_google'] && 'yes' === $this->get_setting( 'enabled_google_pay', 'no' );
        $show_apple  = 'yes' === $atts['show_apple'] && 'yes' === $this->get_setting( 'enabled_apple_pay', 'no' );

        if ( ! $show_paypal && ! $show_google && ! $show_apple ) {
            return '';
        }

        $this->shortcode_containers[] = array(
            'container_id' => $container_id,
            'type'         => 'express',
            'context'      => sanitize_key( $atts['context'] ),
            'product_id'   => absint( $atts['id'] ),
            'color'        => sanitize_key( $color ),
            'shape'        => sanitize_key( $shape ),
            'height'       => $height,
            'label'        => sanitize_key( $label ),
            'layout'       => sanitize_key( $layout ),
            'show_paypal'  => $show_paypal,
            'show_google'  => $show_google,
            'show_apple'   => $show_apple,
        );

        $this->ensure_scripts_enqueued();

        $apple_shape_class = ( $shape === 'pill' ) ? 'apple-shape-pill' : 'apple-shape-rect';
        $apple_radius      = ( $shape === 'pill' ) ? round( $height / 2 ) : 4;

        $classes = 'wpg-shortcode-button ' . esc_attr( $size );

        ob_start();
        ?>
        <div class="ppcp-button-container wpg-shortcode-container">
            <?php if ( $show_paypal ) : ?>
                <div id="<?php echo esc_attr( $container_id ); ?>"
                     class="<?php echo esc_attr( $classes ); ?>"
                     style="--button-height: <?php echo (int) $height; ?>px;"
                     data-wpg-shortcode="1"
                     data-wpg-context="<?php echo esc_attr( $atts['context'] ); ?>"
                     <?php if ( $atts['id'] ) : ?>
                         data-wpg-product-id="<?php echo absint( $atts['id'] ); ?>"
                     <?php endif; ?>
                ></div>
            <?php endif; ?>
            <?php if ( $show_google ) : ?>
                <div data-context="shortcode" class="google-pay-container shortcode <?php echo esc_attr( $classes ); ?>" style="height: <?php echo (int) $height; ?>px;"></div>
            <?php endif; ?>
            <?php if ( $show_apple ) : ?>
                <div data-context="shortcode" class="apple-pay-container shortcode <?php echo esc_attr( $classes . ' ' . $apple_shape_class ); ?>" style="--button-height: <?php echo (int) $height; ?>px; --button-radius: <?php echo (int) $apple_radius; ?>px; height: <?php echo (int) $height; ?>px;"></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function ensure_scripts_enqueued() {
        static $enqueued = false;
        if ( $enqueued ) {
            return;
        }
        $enqueued = true;

        wp_enqueue_script( 'ppcp-checkout-js' );
        wp_enqueue_script( 'ppcp-paypal-checkout-for-woocommerce-public' );
        wp_enqueue_style( 'ppcp-paypal-checkout-for-woocommerce-public' );
    }

    public function render_shortcode_initializer() {
        if ( empty( $this->shortcode_containers ) ) {
            return;
        }

        $shortcode_data = array();
        foreach ( $this->shortcode_containers as $sc ) {
            $shortcode_data[] = array(
                'containerId' => $sc['container_id'],
                'type'        => $sc['type'],
                'context'     => $sc['context'],
                'productId'   => $sc['product_id'],
                'style'       => array(
                    'color'  => $sc['color'],
                    'shape'  => $sc['shape'],
                    'height' => $sc['height'],
                    'label'  => $sc['label'],
                    'layout' => $sc['layout'],
                ),
            );
        }

        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';
            if (typeof wpg_paypal_sdk === 'undefined') {
                return;
            }
            var shortcodes = <?php echo wp_json_encode( $shortcode_data ); ?>;
            function renderShortcodeButtons() {
                $.each(shortcodes, function(i, sc) {
                    var el = document.getElementById(sc.containerId);
                    if (!el || el.children.length > 0) {
                        return;
                    }
                    var style = {
                        color: sc.style.color,
                        shape: sc.style.shape,
                        height: sc.style.height,
                        label: sc.style.label,
                        layout: sc.style.layout
                    };
                    if (style.layout === 'horizontal') {
                        style.tagline = false;
                    }
                    try {
                        var buttonOptions = {
                            style: style,
                            createOrder: function() {
                                var data = {
                                    ppcp_action: 'create_order',
                                    utm_nooverride: '1',
                                    used: 'paypal',
                                    from: 'shortcode'
                                };
                                if (sc.productId > 0) {
                                    data.product_id = sc.productId;
                                    data.add_to_cart = sc.productId;
                                    data.quantity = 1;
                                }
                                if (typeof ppcp_manager !== 'undefined') {
                                    return fetch(ppcp_manager.create_order_url_for_paypal, {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: $.param(data)
                                    }).then(function(res) { return res.json(); })
                                      .then(function(json) { return json.orderID || json.id; });
                                }
                            },
                            onApprove: function(data) {
                                if (typeof ppcp_manager !== 'undefined') {
                                    window.location.href = ppcp_manager.display_order_page + '&paypal_order_id=' + data.orderID + '&from=shortcode';
                                }
                            },
                            onError: function(err) {
                                console.error('WPG PayPal Shortcode Error:', err);
                            }
                        };
                        var button = wpg_paypal_sdk.Buttons(buttonOptions);
                        if (button.isEligible()) {
                            button.render('#' + sc.containerId);
                        }
                    } catch(e) {
                        console.error('WPG Shortcode render error:', e);
                    }
                });
            }

            if (document.readyState === 'complete') {
                renderShortcodeButtons();
            } else {
                $(window).on('load', renderShortcodeButtons);
            }
        })(jQuery);
        </script>
        <?php
    }
}
