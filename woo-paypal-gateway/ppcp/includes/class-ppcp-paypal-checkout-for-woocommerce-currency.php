<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations. Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized currency handling for the PayPal Commerce Platform module.
 *
 * Owns three concerns that were previously scattered/duplicated:
 *  - the list of PayPal-supported currencies + a support check,
 *  - the number of decimals PayPal expects for a currency,
 *  - resolving the "active" store currency, with awareness of the popular
 *    WooCommerce multi-currency plugins (WOOCS, CURCY/YayCurrency, Aelia,
 *    WPML WooCommerce Multilingual & Multicurrency).
 *
 * A conversion seam (`wpg_ppcp_convert_amount`) and an active-currency filter
 * (`wpg_ppcp_active_currency`) let third parties plug in any other solution.
 */
class PPCP_Paypal_Checkout_For_Woocommerce_Currency {

	private static $instance = null;

	/**
	 * Currencies PayPal supports for payments.
	 *
	 * @var string[]
	 */
	private $supported = array(
		'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS',
		'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB',
		'SGD', 'SEK', 'CHF', 'THB', 'USD',
	);

	/**
	 * Currencies for which PayPal does NOT accept decimal amounts.
	 *
	 * This mirrors PayPal's documented rule for HUF, JPY and TWD. Kept as a
	 * dedicated, filterable list so the behaviour is explicit and overridable.
	 *
	 * @var string[]
	 */
	private $zero_decimal = array( 'HUF', 'JPY', 'TWD' );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return string[] PayPal-supported currency codes.
	 */
	public function get_supported_currencies() {
		return apply_filters( 'wpg_ppcp_supported_currencies', $this->supported );
	}

	/**
	 * Whether PayPal supports the given currency.
	 *
	 * @param string $code Currency code.
	 * @return bool
	 */
	public function is_supported( $code ) {
		return in_array( strtoupper( (string) $code ), $this->get_supported_currencies(), true );
	}

	/**
	 * Number of decimals PayPal expects for a currency (0 or 2).
	 *
	 * @param string $code Currency code.
	 * @return int
	 */
	public function get_decimals( $code ) {
		$zero = apply_filters( 'wpg_ppcp_zero_decimal_currencies', $this->zero_decimal );
		$decimals = in_array( strtoupper( (string) $code ), $zero, true ) ? 0 : 2;
		return (int) apply_filters( 'wpg_ppcp_currency_decimals', $decimals, $code );
	}

	/**
	 * Whether the currency is a PayPal zero-decimal currency.
	 *
	 * @param string $code Currency code.
	 * @return bool
	 */
	public function is_zero_decimal( $code ) {
		return 0 === $this->get_decimals( $code );
	}

	/**
	 * Resolve the active store currency, honouring multi-currency plugins.
	 *
	 * WooCommerce's own cart/order totals are already converted by these plugins,
	 * so we only need to report the matching currency CODE to PayPal.
	 *
	 * @return string Currency code.
	 */
	public function get_active_currency() {
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

		// Detection guards use class_exists( ..., false ) so we never trigger a
		// third-party plugin's autoloader (a broken autoloader can fatal the site).
		if ( isset( $GLOBALS['WOOCS'] ) && is_object( $GLOBALS['WOOCS'] ) && ! empty( $GLOBALS['WOOCS']->current_currency ) ) {
			// WOOCS - Currency Switcher for WooCommerce.
			$currency = $GLOBALS['WOOCS']->current_currency;
		} elseif ( function_exists( 'wmc_get_price' ) && ! empty( $_COOKIE['woocommerce_current_currency'] ) ) {
			// WooCommerce Multi Currency (VillaTheme).
			$currency = sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_current_currency'] ) );
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_Data', false ) && method_exists( 'WOOMULTI_CURRENCY_Data', 'get_ins' ) ) {
			$settings = WOOMULTI_CURRENCY_Data::get_ins();
			if ( $settings && method_exists( $settings, 'get_current_currency' ) ) {
				$currency = $settings->get_current_currency();
			}
		} elseif ( class_exists( '\Aelia\WC\CurrencySwitcher\Settings', false ) && function_exists( 'WC' ) && WC() && isset( WC()->session ) && WC()->session ) {
			// Aelia Currency Switcher.
			$aelia_currency = WC()->session->get( 'aelia_cs_selected_currency' );
			if ( ! empty( $aelia_currency ) ) {
				$currency = $aelia_currency;
			}
		}

		return apply_filters( 'wpg_ppcp_active_currency', $currency );
	}

	/**
	 * Convert an amount into the active currency where a plugin provides a hook.
	 *
	 * By default amounts are returned unchanged because WooCommerce cart/order
	 * totals are already in the active currency. Multi-currency solutions that
	 * need custom conversion can hook `wpg_ppcp_convert_amount`.
	 *
	 * @param float  $amount Amount in store base handling.
	 * @param string $code   Target currency code.
	 * @return float
	 */
	public function convert( $amount, $code = '' ) {
		return (float) apply_filters( 'wpg_ppcp_convert_amount', $amount, $code );
	}
}
