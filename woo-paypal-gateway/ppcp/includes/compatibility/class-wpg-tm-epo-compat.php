<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ThemeComplete Extra Product Options compatibility.
 *
 * Ensures EPO field data and costs are included when using
 * PayPal express checkout on product pages.
 */
class WPG_TM_EPO_Compat {

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
		return defined( 'THEMECOMPLETE_EPO_VERSION' ) || class_exists( 'THEMECOMPLETE_EPO', false );
	}

	public function adjust_product_total( $total, $product, $qty ) {
		if ( empty( $_POST['tm_epo_costs'] ) ) {
			return $total;
		}

		$epo_cost = (float) sanitize_text_field( wp_unslash( $_POST['tm_epo_costs'] ) );

		if ( $epo_cost > 0 ) {
			$total += $epo_cost * $qty;
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
			$(document.body).on('tm-epo-after-update', function() {
				var epoCost = 0;
				$('.tmcp-field').each(function() {
					var $field = $(this);
					if (($field.is(':checkbox') || $field.is(':radio')) && !$field.is(':checked')) return;
					var price = parseFloat($field.data('price')) || 0;
					if ($field.is('select')) {
						price = parseFloat($field.find(':selected').data('price')) || 0;
					}
					epoCost += price;
				});

				if (!$('form.cart input[name="tm_epo_costs"]').length) {
					$('form.cart').append('<input type="hidden" name="tm_epo_costs" value="0">');
				}
				$('form.cart input[name="tm_epo_costs"]').val(epoCost);
				$(document.body).trigger('ppcp_checkout_updated');
			});
		})(jQuery);
		</script>
		<?php
	}
}
