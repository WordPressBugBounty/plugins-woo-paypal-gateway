<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations. Hook names are public API that existing sites and integrations already hook into; renaming them would break those customisations, and hooks belonging to other plugins are fired here as integration points and are not ours to rename.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JS optimizer / cache plugin compatibility.
 *
 * The plugin's frontend scripts are registered with their real dependencies
 * (jquery, wp-element, wc-blocks-registry, ...), so plain WordPress always
 * executes them in a working order. JS optimization features - combine, minify,
 * defer and "delay JavaScript execution" - rewrite the script tags and routinely
 * break that ordering: the plugin's files then execute before jQuery / wp-* and
 * the PayPal options silently disappear from checkout ("jQuery is not defined",
 * "wp is not defined" in the console).
 *
 * Following the approach of WooCommerce PayPal Payments' ppcp-compat module,
 * this class registers the plugin's scripts on the exclusion filters that the
 * popular optimizers expose (SiteGround Optimizer, LiteSpeed Cache, W3 Total
 * Cache, WP Rocket, Autoptimize, Perfmatters), and additionally marks the
 * plugin's script tags with the vendor-neutral opt-out attributes
 * (data-cfasync, data-no-optimize, data-no-defer, data-no-minify, nowprocket)
 * that attribute-based optimizers such as Cloudflare Rocket Loader honour.
 *
 * Optimizers that run outside WordPress (host-level minifiers, CDN rewriting)
 * cannot be reached from PHP at all; for those the frontend scripts carry their
 * own load guard that waits for jQuery / wp-* instead of throwing.
 */
class WPG_Script_Optimizer_Compat {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		// SiteGround Optimizer (all three take arrays of script handles).
		add_filter( 'sgo_js_minify_exclude', array( $this, 'add_script_handles' ) );
		add_filter( 'sgo_javascript_combine_exclude', array( $this, 'add_script_handles' ) );
		add_filter( 'sgo_js_async_exclude', array( $this, 'add_script_handles' ) );

		// LiteSpeed Cache (arrays of URL / filename substrings).
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'add_url_substrings' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'add_url_substrings' ) );

		// WP Rocket (arrays of URL patterns matched against the src / tag).
		add_filter( 'rocket_exclude_js', array( $this, 'add_url_substrings' ) );
		add_filter( 'rocket_exclude_defer_js', array( $this, 'add_url_substrings' ) );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'add_url_substrings' ) );
		add_filter( 'rocket_minify_excluded_external_js', array( $this, 'add_external_domains' ) );

		// Perfmatters (arrays of substrings matched against the script tag).
		add_filter( 'perfmatters_delay_js_exclusions', array( $this, 'add_url_substrings' ) );
		add_filter( 'perfmatters_defer_js_exclusions', array( $this, 'add_url_substrings' ) );

		// Autoptimize (comma separated string).
		add_filter( 'autoptimize_filter_js_exclude', array( $this, 'add_autoptimize_exclusions' ) );

		// W3 Total Cache (per-tag veto).
		add_filter( 'w3tc_minify_js_do_tag_minification', array( $this, 'w3tc_skip_tag_minification' ), 10, 3 );

		// Attribute-based opt-outs (Cloudflare Rocket Loader and others).
		add_filter( 'script_loader_tag', array( $this, 'mark_script_tag' ), 10, 2 );
	}

	/**
	 * Frontend script handles this plugin registers.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return apply_filters(
			'wpg_ppcp_optimizer_exempt_handles',
			array(
				'ppcp-checkout-js', // The PayPal JS SDK itself.
				'google-pay-sdk',   // External wallet SDKs the buttons need.
				'apple-pay-sdk',
				'ppcp-paypal-checkout-for-woocommerce-public',
				'wpg_paypal_checkout-blocks-integration',
				'wpg_paypal_cc-blocks-integration',
				'wpg-ppcp-fastlane',
				'wpg-ppcp-minicart-block',
				'ppcp-pay-later-messaging-block',
				'ppcp-pay-later-messaging-home',
				'ppcp-pay-later-messaging-category',
				'ppcp-pay-later-messaging-product',
				'ppcp-pay-later-messaging-cart',
				'ppcp-pay-later-messaging-payment',
				'ppcp-pay-later-messaging-shortcode',
				'ppcp-paypal-checkout-for-woocommerce-order-capture',
			)
		);
	}

	/**
	 * Substrings that identify this plugin's script URLs, including the external
	 * PayPal JS SDK. Every optimizer that filters by URL matches on substrings
	 * (or a regex a plain substring is valid in), so these cover all handles at once.
	 *
	 * @return string[]
	 */
	public function get_url_substrings() {
		return apply_filters(
			'wpg_ppcp_optimizer_exempt_url_parts',
			array(
				'woo-paypal-gateway',
				'paypal.com/sdk',
				'pay.google.com',
				'applepay.cdn-apple.com',
			)
		);
	}

	/**
	 * Merge our script handles into an optimizer's exclusion list.
	 *
	 * @param array|mixed $handles Excluded handles so far.
	 * @return array
	 */
	public function add_script_handles( $handles ) {
		return array_values( array_unique( array_merge( (array) $handles, $this->get_script_handles() ) ) );
	}

	/**
	 * Merge our URL substrings into an optimizer's exclusion list.
	 *
	 * @param array|mixed $exclusions Excluded URL parts so far.
	 * @return array
	 */
	public function add_url_substrings( $exclusions ) {
		return array_values( array_unique( array_merge( (array) $exclusions, $this->get_url_substrings() ) ) );
	}

	/**
	 * Keep WP Rocket from minifying / combining the external PayPal SDK.
	 *
	 * @param array|mixed $domains Excluded external domains so far.
	 * @return array
	 */
	public function add_external_domains( $domains ) {
		return array_values( array_unique( array_merge( (array) $domains, array( 'paypal.com' ) ) ) );
	}

	/**
	 * Autoptimize expects a comma separated string rather than an array.
	 *
	 * @param string|mixed $exclude Comma separated exclusions so far.
	 * @return string
	 */
	public function add_autoptimize_exclusions( $exclude ) {
		$ours = implode( ', ', $this->get_url_substrings() );
		$exclude = is_string( $exclude ) ? trim( $exclude ) : '';
		return '' === $exclude ? $ours : $exclude . ', ' . $ours;
	}

	/**
	 * W3 Total Cache asks per script tag whether it may minify it.
	 *
	 * @param bool        $do_tag_minification Whether W3TC intends to minify the tag.
	 * @param string      $script_tag          The full script tag.
	 * @param string|null $file                The resolved file path, when known.
	 * @return bool
	 */
	public function w3tc_skip_tag_minification( $do_tag_minification, $script_tag, $file ) {
		$haystack = $script_tag . ' ' . (string) $file;
		foreach ( $this->get_url_substrings() as $part ) {
			if ( false !== strpos( $haystack, $part ) ) {
				return false;
			}
		}
		return $do_tag_minification;
	}

	/**
	 * Mark our script tags with the vendor-neutral opt-out attributes that
	 * attribute-based optimizers honour: data-cfasync="false" (Cloudflare
	 * Rocket Loader), nowprocket (WP Rocket), data-no-optimize / data-no-defer /
	 * data-no-minify (LiteSpeed and various minifiers).
	 *
	 * @param string $tag    The complete script tag.
	 * @param string $handle The script handle.
	 * @return string
	 */
	public function mark_script_tag( $tag, $handle ) {
		if ( ! in_array( $handle, $this->get_script_handles(), true ) ) {
			return $tag;
		}
		if ( false !== strpos( $tag, 'data-cfasync' ) ) {
			return $tag;
		}
		return str_replace(
			'<script ',
			'<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" nowprocket ',
			$tag
		);
	}
}
