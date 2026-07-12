<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Product Add-Ons compatibility.
 *
 * Ensures add-on costs are included in the PayPal button's displayed total
 * on product pages when using express checkout. The add-on field data itself
 * is already captured by form.cart.serialize() in the JS.
 */
class WPG_Product_Addons_Compat {

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
		return defined( 'WC_PRODUCT_ADDONS_VERSION' ) || class_exists( 'WC_Product_Addons', false );
	}

	/**
	 * Adjust product total to include add-on costs.
	 *
	 * @param float      $total   Current product total.
	 * @param WC_Product $product Product object.
	 * @param float      $qty     Quantity.
	 * @return float Adjusted total.
	 */
	public function adjust_product_total( $total, $product, $qty ) {
		if ( empty( $_POST['addon_costs'] ) ) {
			return $total;
		}

		$addon_cost = (float) sanitize_text_field( wp_unslash( $_POST['addon_costs'] ) );

		if ( $addon_cost > 0 ) {
			$total += $addon_cost * $qty;
		}

		return $total;
	}

	/**
	 * Script to sync add-on price changes with PayPal button total.
	 */
	public function render_price_sync_script() {
		if ( ! is_product() ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function($) {
			if (typeof $ === 'undefined') return;
			$(document.body).on('updated_addons', function() {
				var addonCost = 0;
				$('.wc-pao-addon-field').each(function() {
					var $field = $(this);
					if ($field.is(':checkbox') || $field.is(':radio')) {
						if ($field.is(':checked') && $field.data('price')) {
							addonCost += parseFloat($field.data('price')) || 0;
						}
					} else if ($field.is('select')) {
						var selected = $field.find(':selected');
						if (selected.data('price')) {
							addonCost += parseFloat(selected.data('price')) || 0;
						}
					} else if ($field.data('price-type') === 'quantity_based' || $field.data('price')) {
						if ($field.val()) {
							addonCost += parseFloat($field.data('price')) || 0;
						}
					}
				});

				if (!$('form.cart input[name="addon_costs"]').length) {
					$('form.cart').append('<input type="hidden" name="addon_costs" value="0">');
				}
				$('form.cart input[name="addon_costs"]').val(addonCost);
				$(document.body).trigger('ppcp_checkout_updated');
			});
		})(jQuery);
		</script>
		<?php
	}
}
