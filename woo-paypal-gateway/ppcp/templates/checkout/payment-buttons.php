<?php
/**
 * Checkout page payment buttons.
 *
 * Override by copying to: yourtheme/woo-paypal-gateway/checkout/payment-buttons.php
 *
 * @var bool   $show_paypal       Whether to show the PayPal button.
 * @var bool   $show_google       Whether to show the Google Pay button.
 * @var bool   $show_apple        Whether to show the Apple Pay button.
 * @var string $button_class      CSS classes for button sizing.
 * @var int    $button_height     Button height in pixels.
 * @var string $apple_shape_class Apple Pay shape CSS class.
 * @var int    $apple_radius      Apple Pay border radius.
 * @var bool   $is_pay_page       Whether this is the order-pay page.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpg_ppcp_before_checkout_buttons', $args ?? array() );
?>
<div class="ppcp-button-container">
	<?php if ( $show_paypal ) : ?>
		<?php if ( ! empty( $is_pay_page ) ) : ?>
			<div id="ppcp_order_pay" class="checkout <?php echo esc_attr( $button_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px;"></div>
		<?php else : ?>
			<div id="ppcp_checkout" class="checkout <?php echo esc_attr( $button_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px;"></div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( $show_google ) : ?>
		<div data-context="checkout" class="google-pay-container checkout <?php echo esc_attr( $button_class ); ?>" style="height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
	<?php if ( $show_apple ) : ?>
		<div data-context="checkout" class="apple-pay-container checkout <?php echo esc_attr( $button_class . ' ' . $apple_shape_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px; --button-radius: <?php echo (int) $apple_radius; ?>px; height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
</div>
<?php
do_action( 'wpg_ppcp_after_checkout_buttons', $args ?? array() );
