<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations. Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-language / locale compatibility.
 *
 * Ensures the PayPal SDK loads with the correct locale parameter
 * matching the site language, and registers translatable strings
 * with WPML/Polylang.
 */
class WPG_Locale_Compat {

	private static $instance = null;

	private $paypal_locale_map = array(
		'de_DE'      => 'de_DE',
		'de_DE_formal' => 'de_DE',
		'de_AT'      => 'de_DE',
		'de_CH'      => 'de_DE',
		'de_CH_informal' => 'de_DE',
		'fr_FR'      => 'fr_FR',
		'fr_BE'      => 'fr_FR',
		'fr_CA'      => 'fr_CA',
		'es_ES'      => 'es_ES',
		'es_MX'      => 'es_MX',
		'es_AR'      => 'es_ES',
		'es_CL'      => 'es_ES',
		'es_CO'      => 'es_ES',
		'es_CR'      => 'es_ES',
		'es_PE'      => 'es_ES',
		'es_VE'      => 'es_ES',
		'it_IT'      => 'it_IT',
		'pt_BR'      => 'pt_BR',
		'pt_PT'      => 'pt_PT',
		'nl_NL'      => 'nl_NL',
		'nl_BE'      => 'nl_NL',
		'pl_PL'      => 'pl_PL',
		'sv_SE'      => 'sv_SE',
		'da_DK'      => 'da_DK',
		'nb_NO'      => 'no_NO',
		'nn_NO'      => 'no_NO',
		'fi'         => 'fi_FI',
		'ja'         => 'ja_JP',
		'ko_KR'      => 'ko_KR',
		'zh_CN'      => 'zh_CN',
		'zh_TW'      => 'zh_TW',
		'zh_HK'      => 'zh_HK',
		'ru_RU'      => 'ru_RU',
		'tr_TR'      => 'tr_TR',
		'ar'         => 'ar_EG',
		'he_IL'      => 'he_IL',
		'th'         => 'th_TH',
		'en_GB'      => 'en_GB',
		'en_AU'      => 'en_AU',
		'en_CA'      => 'en_US',
		'en_NZ'      => 'en_GB',
		'en_ZA'      => 'en_GB',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_filter( 'wpg_ppcp_sdk_locale', array( $this, 'get_paypal_locale' ) );

		if ( $this->is_wpml_active() ) {
			// WPML registration is performed BY firing wpml_register_single_string —
			// WPML does not fire a 'wpml_register_string_packages' action to hook,
			// so the old add_action() registration never ran.
			add_action( 'init', array( $this, 'register_wpml_strings' ), 20 );
		}

		if ( $this->is_polylang_active() ) {
			add_action( 'init', array( $this, 'register_polylang_strings' ), 20 );
		}
	}

	/**
	 * Map WordPress locale to PayPal SDK locale.
	 *
	 * The incoming $locale is core's Locale_Handler::get_valid_locale(), which
	 * is already validated against the full PayPal locale matrix. Discarding it
	 * (as this callback previously did) regressed core: en_US stores fell
	 * through the prefix loop to en_GB, and languages core supports but this
	 * map does not (cs_CZ, el_GR, hu_HU, ...) were downgraded to en_US. Core's
	 * value now wins whenever it is a validated non-fallback locale; the map
	 * only fills in when core could not resolve the site language (e.g. nb_NO,
	 * which core rejects but maps cleanly to PayPal's no_NO).
	 *
	 * @param string $locale Core-validated locale (falls back to en_US).
	 * @return string PayPal-compatible locale.
	 */
	public function get_paypal_locale( $locale ) {
		$has_handler = class_exists( 'PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler' );
		$wp_locale   = determine_locale();
		$lang        = substr( $wp_locale, 0, 2 );

		if ( ! empty( $locale ) && $has_handler && PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler::is_supported( $locale ) ) {
			// en_US is also core's could-not-resolve fallback: trust it only when
			// the site language really is English, otherwise try the map below.
			if ( 'en_US' !== $locale || 'en' === $lang ) {
				return $locale;
			}
		}

		if ( isset( $this->paypal_locale_map[ $wp_locale ] ) ) {
			$mapped = $this->paypal_locale_map[ $wp_locale ];
			if ( ! $has_handler || PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler::is_supported( $mapped ) ) {
				return $mapped;
			}
		}

		foreach ( $this->paypal_locale_map as $wp_key => $paypal_key ) {
			// Match on the full language code only ('de' matches de_AT, not
			// every key that merely starts with those letters).
			if ( $wp_key === $lang || 0 === strpos( $wp_key, $lang . '_' ) ) {
				return $paypal_key;
			}
		}

		return ! empty( $locale ) ? $locale : 'en_US';
	}

	public function register_wpml_strings() {
		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );

		$translatable = array(
			'title'       => isset( $settings['title'] ) ? $settings['title'] : '',
			'description' => isset( $settings['description'] ) ? $settings['description'] : '',
		);

		foreach ( $translatable as $name => $value ) {
			if ( ! empty( $value ) ) {
				// The documented WPML String Translation API: registration happens
				// by firing this action, not by hooking one.
				do_action( 'wpml_register_single_string', 'woo-paypal-gateway', 'gateway_' . $name, $value );
			}
		}
	}

	public function register_polylang_strings() {
		if ( ! function_exists( 'pll_register_string' ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );

		if ( ! empty( $settings['title'] ) ) {
			pll_register_string( 'wpg_gateway_title', $settings['title'], 'woo-paypal-gateway' );
		}

		if ( ! empty( $settings['description'] ) ) {
			pll_register_string( 'wpg_gateway_description', $settings['description'], 'woo-paypal-gateway' );
		}
	}

	private function is_wpml_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress', false );
	}

	private function is_polylang_active() {
		return defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_register_string' );
	}
}
