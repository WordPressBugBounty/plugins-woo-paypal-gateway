<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Store API (Cart/Checkout Blocks) schema extension.
 *
 * Exposes fresh, server-authoritative plugin state on every Store API cart
 * response under the `wpg_ppcp` namespace (audit finding P13). The block
 * checkout previously had only one-time wp_localize/getSetting blobs computed
 * at page render, so state like the active-PayPal-session flag or the cart
 * total could go stale after cart edits, in concurrent tabs, or when the WC
 * session expired.
 *
 * This extension is strictly ADDITIVE:
 * - It only adds extra JSON to Store API cart responses; nothing existing is
 *   removed or rewired, so stores where nothing reads the data see zero
 *   behaviour change.
 * - It registers only when the modern Store API classes exist (older
 *   WooCommerce silently skips it) and every callback is wrapped so a failure
 *   degrades to empty data rather than ever breaking the cart endpoint.
 *
 * Client side, ppcp-checkout.js mirrors the payload to
 * window.wpgPPCPStoreApiCart and fires the `ppcp_store_api_updated` jQuery
 * event whenever it changes, so scripts can react to fresh server state
 * instead of the render-time snapshot.
 */
class PPCP_Store_API_Extension {

    const SCHEMA_NAMESPACE = 'wpg_ppcp';

    /**
     * Register the cart-schema extension. Safe to call unconditionally from
     * woocommerce_blocks_loaded; it no-ops when the Store API is unavailable.
     */
    public static function init() {
        if (
            !class_exists('\Automattic\WooCommerce\StoreApi\StoreApi')
            || !class_exists('\Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema')
            || !class_exists('\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema')
        ) {
            return;
        }
        try {
            $extend = \Automattic\WooCommerce\StoreApi\StoreApi::container()->get(
                \Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema::class
            );
            $extend->register_endpoint_data(array(
                'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
                'namespace'       => self::SCHEMA_NAMESPACE,
                'data_callback'   => array(__CLASS__, 'data_callback'),
                'schema_callback' => array(__CLASS__, 'schema_callback'),
                'schema_type'     => ARRAY_A,
            ));
        } catch (\Throwable $e) {
            // An optional read-only extension must never take down the cart
            // endpoint. Silently skip on any container/registration failure.
        }
    }

    /**
     * Fresh server-side state, recomputed on every Store API cart response.
     *
     * @return array
     */
    public static function data_callback() {
        $data = array(
            'has_active_session'  => false,
            'paypal_order_status' => '',
            'order_total'         => '',
            'currency_code'       => '',
            'needs_shipping'      => false,
        );
        try {
            if (function_exists('woo_paypal_gateway_ppcp_get_paypal_order_session_data')) {
                $session = woo_paypal_gateway_ppcp_get_paypal_order_session_data();
                $status  = isset($session['status']) ? strtolower((string) $session['status']) : '';
                $data['paypal_order_status'] = $status;
                // "Active session" mirrors the server-side approval truth used by the
                // capture flow: a session-stored PayPal order id in an approved state.
                // No order id is exposed — only state — to keep the surface minimal.
                $data['has_active_session'] = !empty($session['id']) && in_array($status, array('approved', 'capture'), true);
            }
            if (function_exists('WC') && WC()->cart instanceof WC_Cart) {
                $data['order_total']    = (string) WC()->cart->get_total('edit');
                $data['needs_shipping'] = (bool) WC()->cart->needs_shipping();
            }
            if (function_exists('get_woocommerce_currency')) {
                $data['currency_code'] = (string) get_woocommerce_currency();
            }
        } catch (\Throwable $e) {
            // Return the defaults rather than erroring the cart response.
        }
        return $data;
    }

    /**
     * JSON schema for the extension payload.
     *
     * @return array
     */
    public static function schema_callback() {
        return array(
            'has_active_session' => array(
                'description' => __('Whether an approved PayPal order is active in the current session.', 'woo-paypal-gateway'),
                'type'        => 'boolean',
                'readonly'    => true,
            ),
            'paypal_order_status' => array(
                'description' => __('Lowercase status of the session PayPal order, empty when none.', 'woo-paypal-gateway'),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'order_total' => array(
                'description' => __('Server-calculated cart total.', 'woo-paypal-gateway'),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'currency_code' => array(
                'description' => __('Active store currency code.', 'woo-paypal-gateway'),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'needs_shipping' => array(
                'description' => __('Whether the cart requires shipping.', 'woo-paypal-gateway'),
                'type'        => 'boolean',
                'readonly'    => true,
            ),
        );
    }
}
