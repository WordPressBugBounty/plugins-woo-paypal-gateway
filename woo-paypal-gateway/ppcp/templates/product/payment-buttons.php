<?php
/**
 * Product page payment buttons.
 *
 * Override by copying to: yourtheme/woo-paypal-gateway/product/payment-buttons.php
 *
 * @var bool   $show_paypal    Whether to show the PayPal button.
 * @var bool   $show_google    Whether to show the Google Pay button.
 * @var bool   $show_apple     Whether to show the Apple Pay button.
 * @var string $button_class   CSS classes for button sizing.
 * @var int    $button_height  Button height in pixels.
 * @var string $apple_shape_class Apple Pay shape CSS class.
 * @var int    $apple_radius   Apple Pay border radius.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpg_ppcp_before_product_buttons', $args ?? array() );
?>
<div class="ppcp-button-container">
	<?php if ( $show_paypal ) : ?>
		<div id="ppcp_product" class="<?php echo esc_attr( $button_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
	<?php if ( $show_google ) : ?>
		<div data-context="product" class="google-pay-container product <?php echo esc_attr( $button_class ); ?>" style="height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
	<?php if ( $show_apple ) : ?>
		<div data-context="product" class="apple-pay-container product <?php echo esc_attr( $button_class . ' ' . $apple_shape_class ); ?>" style="--button-height: <?php echo (int) $button_height; ?>px; --button-radius: <?php echo (int) $apple_radius; ?>px; height: <?php echo (int) $button_height; ?>px;"></div>
	<?php endif; ?>
</div>
<?php
do_action( 'wpg_ppcp_after_product_buttons', $args ?? array() );
