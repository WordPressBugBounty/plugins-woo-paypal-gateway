<?php

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
		add_filter( 'wpg_ppcp_localize_script_data', array( $this, 'add_locale_data' ) );

		if ( $this->is_wpml_active() ) {
			add_action( 'wpml_register_string_packages', array( $this, 'register_wpml_strings' ) );
		}

		if ( $this->is_polylang_active() ) {
			add_action( 'init', array( $this, 'register_polylang_strings' ), 20 );
		}
	}

	/**
	 * Map WordPress locale to PayPal SDK locale.
	 *
	 * @param string $locale Current locale setting.
	 * @return string PayPal-compatible locale.
	 */
	public function get_paypal_locale( $locale ) {
		$wp_locale = determine_locale();

		if ( isset( $this->paypal_locale_map[ $wp_locale ] ) ) {
			return $this->paypal_locale_map[ $wp_locale ];
		}

		$lang = substr( $wp_locale, 0, 2 );
		foreach ( $this->paypal_locale_map as $wp_key => $paypal_key ) {
			if ( strpos( $wp_key, $lang ) === 0 ) {
				return $paypal_key;
			}
		}

		return 'en_US';
	}

	public function add_locale_data( $data ) {
		$data['sdk_locale'] = apply_filters( 'wpg_ppcp_sdk_locale', 'en_US' );
		return $data;
	}

	public function register_wpml_strings() {
		if ( ! function_exists( 'icl_register_string' ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', array() );

		$translatable = array(
			'title'       => isset( $settings['title'] ) ? $settings['title'] : '',
			'description' => isset( $settings['description'] ) ? $settings['description'] : '',
		);

		foreach ( $translatable as $name => $value ) {
			if ( ! empty( $value ) ) {
				icl_register_string( 'woo-paypal-gateway', 'gateway_' . $name, $value );
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
