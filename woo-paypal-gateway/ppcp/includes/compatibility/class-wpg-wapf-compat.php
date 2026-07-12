<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Product Fields for WooCommerce (SW WAPF) compatibility.
 *
 * Ensures WAPF field data and costs are included when using
 * PayPal express checkout on product pages.
 */
class WPG_WAPF_Compat {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( ! $this->is_active() ) {
			return;
		}

		add_filter( 'wpg_ppcp_product_total', array( $this, 'adjust_product_total' ), 10, 3 );
		add_action( 'wp_footer', array( $this, 'render_price_sync_script' ) );
	}

	private function is_active() {
		// Use class_exists() with autoload disabled so we never trigger the
		// target plugin's autoloader while merely detecting whether it is active.
		// Some autoloaders throw a fatal when handed a class name they don't own.
		return defined( 'WAPF_VERSION' )
			|| class_exists( 'SW_WAPF_PRO', false )
			|| class_exists( 'SW_WAPF', false );
	}

	public function adjust_product_total( $total, $product, $qty ) {
		if ( empty( $_POST['wapf_costs'] ) ) {
			return $total;
		}

		$wapf_cost = (float) sanitize_text_field( wp_unslash( $_POST['wapf_costs'] ) );

		if ( $wapf_cost > 0 ) {
			$total += $wapf_cost * $qty;
		}

		return $total;
	}

	public function render_price_sync_script() {
		if ( ! is_product() ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function($) {
			if (typeof $ === 'undefined') return;
			$(document).on('wapf/pricing', function(e, data) {
				var wapfCost = 0;
				if (data && typeof data.optionsTotal !== 'undefined') {
					wapfCost = parseFloat(data.optionsTotal) || 0;
				}

				if (!$('form.cart input[name="wapf_costs"]').length) {
					$('form.cart').append('<input type="hidden" name="wapf_costs" value="0">');
				}
				$('form.cart input[name="wapf_costs"]').val(wapfCost);
				$(document.body).trigger('ppcp_checkout_updated');
			});
		})(jQuery);
		</script>
		<?php
	}
}
