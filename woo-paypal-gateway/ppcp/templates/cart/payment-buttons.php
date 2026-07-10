<?php
/**
 * Cart page payment buttons.
 *
 * Override by copying to: yourtheme/woo-paypal-gateway/cart/payment-buttons.php
 *
 * @var bool   $show_paypal       Whether to show the PayPal button.
 * @var bool   $show_google       Whether to show the Google Pay button.
 * @var bool   $show_apple        Whether to show the Apple Pay button.
 * @var string $button_class      CSS classes for button sizing.
 * @var int    $button_height     Button height in pixels.
 * @var string $apple_shape_class Apple Pay shape CSS class.
 * @var int    $apple_radius      Apple Pay border radius.
 * @var bool   $separator_before  Show separator before buttons.
 * @var bool   $separator_after   Show separator after buttons.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpg_ppcp_before_cart_buttons', $args ?? array() );
?>
<div class="ppcp-button-container ppcp_cart">
	<?php if ( ! empty( $separator_before ) ) : ?>
		<div class="ppcp-proceed-to-checkout-button-separator <?php echo esc_attr( $button_class ); ?>"><span><?php echo esc_html_x( 'Or', 'Important', 'woo-paypal-gateway' ); ?></span></div>
	<?php endif; ?>
	<?php if ( $show_paypal ) : ?>
		<div id="ppcp_cart" class="cart <?php echo esc_attr( $button_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px;min-height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
	<?php if ( $show_google ) : ?>
		<div data-context="cart" class="google-pay-container cart <?php echo esc_attr( $button_class ); ?>" style="height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
	<?php if ( $show_apple ) : ?>
		<div data-context="cart" class="apple-pay-container cart <?php echo esc_attr( $button_class . ' ' . $apple_shape_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px; --button-radius: <?php echo (int) $apple_radius; ?>px; height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
	<?php if ( ! empty( $separator_after ) ) : ?>
		<div class="ppcp-proceed-to-checkout-button-separator <?php echo esc_attr( $button_class ); ?>"><span><?php echo esc_html_x( 'Or', 'Important', 'woo-paypal-gateway' ); ?></span></div>
	<?php endif; ?>
</div>
<?php
do_action( 'wpg_ppcp_after_cart_buttons', $args ?? array() );
