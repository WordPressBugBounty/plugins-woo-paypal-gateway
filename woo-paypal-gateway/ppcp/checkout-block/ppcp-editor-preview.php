<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Public class names using the plugin's established WPG_/PPCP_ prefixes; renaming shipped classes would break existing sites and integrations.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The page the block editor draws its PayPal buttons on.
 *
 * The editor canvas is a srcdoc iframe. window.location.host is empty in such a
 * document, and the PayPal JS SDK refuses to bootstrap without one:
 *
 *     Bootstrap Error for buttons: Can not read window host
 *
 * So the SDK cannot run in the canvas, however it is loaded. Instead the buttons
 * are drawn on this small same-origin page -- an ordinary document with a real
 * host, where the SDK behaves exactly as it does on the front end -- and the
 * editor embeds it in an iframe.
 *
 * The page is admin-only (edit_posts plus a nonce), renders nothing but buttons,
 * and gives them no createOrder callback, so nothing here can start a payment.
 * It reports its own height to the editor so the placement is sized to whatever
 * PayPal decided to draw.
 */
class PPCP_Editor_Button_Preview {

    const ACTION = 'wpg_ppcp_editor_button_preview';
    const CONTEXTS = array('express_checkout', 'cart', 'checkout');
    const BUTTONS = array('paypal', 'googlepay', 'applepay');
    const GOOGLE_PAY_SDK = 'https://pay.google.com/gp/p/js/pay.js';
    const APPLE_PAY_SDK = 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js';

    public static function init() {
        add_action('wp_ajax_' . self::ACTION, array(__CLASS__, 'render'));
    }

    /**
     * The URL the editor points its preview frame at, or '' when this user has no
     * business seeing it. The frame appends the placement and the buttons to draw.
     */
    public static function get_frame_url() {
        if (!current_user_can('edit_posts')) {
            return '';
        }
        return add_query_arg(
                array(
                    'action' => self::ACTION,
                    '_wpnonce' => wp_create_nonce(self::ACTION),
                ),
                admin_url('admin-ajax.php')
        );
    }

    /**
     * The height one button takes in each placement, so the editor can size the
     * frame sensibly before the page reports what it actually drew.
     */
    public static function get_frame_heights() {
        $settings = self::get_settings();
        $heights = array();
        foreach (self::CONTEXTS as $context) {
            $style = self::get_button_style($settings, $context);
            $heights[$context] = (int) $style['height'];
        }
        return $heights;
    }

    public static function render() {
        if (!current_user_can('edit_posts')) {
            status_header(403);
            exit;
        }
        check_admin_referer(self::ACTION);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- check_admin_referer() above verified the request.
        $context = isset($_GET['context']) ? sanitize_key(wp_unslash($_GET['context'])) : '';
        $requested = isset($_GET['buttons']) ? sanitize_text_field(wp_unslash($_GET['buttons'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (!in_array($context, self::CONTEXTS, true)) {
            $context = 'checkout';
        }
        // Display hints from the editor, which already knows what the placement
        // shows. Nothing but which buttons get drawn depends on them.
        $buttons = array_values(array_intersect(array_map('sanitize_key', explode(',', $requested)), self::BUTTONS));

        $settings = self::get_settings();
        $style = self::get_button_style($settings, $context);
        // The front end sizes every placement from this stylesheet, keyed on the
        // container's id plus its device and size classes. The preview loads the same
        // stylesheet and renders the same containers, so the buttons are laid out by
        // the same rules rather than by a second set that has to be kept in step.
        $device = wp_is_mobile() ? 'mobile' : 'desktop';
        $size = self::get_button_size($settings, $context);
        $height = (int) $style['height'];
        $config = array(
            'context' => $context,
            'buttons' => $buttons,
            'environment' => self::is_sandbox($settings) ? 'TEST' : 'PRODUCTION',
            'device' => $device,
            'style' => $style,
            'google' => self::get_wallet_style($settings, 'google_pay', $context),
            'apple' => self::get_wallet_style($settings, 'apple_pay', $context),
        );
        $sdk_url = self::get_sdk_url($settings, $context);
        $classes = trim($device . ' ' . $size);
        $draw_paypal = in_array('paypal', $buttons, true);
        $draw_google = in_array('googlepay', $buttons, true);
        $draw_apple = in_array('applepay', $buttons, true);
        // Wallet containers carry their height inline on the front end, as the block's
        // own content component renders them.
        $wallet_height = ('express_checkout' === $context) ? 40 : 48;

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php esc_html_e('PayPal button preview', 'woo-paypal-gateway'); ?></title>
                <link rel="stylesheet" href="<?php echo esc_url(WPG_PLUGIN_ASSET_URL . 'ppcp/public/css/ppcp-paypal-checkout-for-woocommerce-public.css?ver=' . WPG_PLUGIN_VERSION); ?>">
                <style>html, body {margin: 0; padding: 0; background: transparent; overflow: hidden;}</style>
            </head>
            <body>
                <?php
                /*
                 * The placement's own element, and nothing above it. WooCommerce already
                 * wraps this frame in the express-payment list on the editor side, so
                 * repeating that list here would apply its padding twice -- and inside
                 * the frame it would take the browser's default list padding too, since
                 * the stylesheet only zeroes that under a theme's content wrapper. What
                 * is reproduced is the element the stylesheet keys its geometry off.
                 */
                ?>
                <div id="wpg-ppcp-preview">
                    <?php if ('express_checkout' === $context) : ?>
                        <div id="express-payment-method-wpg_paypal_checkout_top">
                            <?php if ($draw_paypal) : ?>
                                <div id="ppcp_checkout_top" class="<?php echo esc_attr($device); ?>"></div>
                                <div id="ppcp_checkout_top_alternative" class="<?php echo esc_attr($device); ?>"></div>
                            <?php endif; ?>
                            <?php if ($draw_google) : ?>
                                <div class="google-pay-container express_checkout <?php echo esc_attr($device); ?>" data-context="express_checkout" style="height: <?php echo (int) $wallet_height; ?>px;"></div>
                            <?php endif; ?>
                            <?php if ($draw_apple) : ?>
                                <div class="apple-pay-container express_checkout <?php echo esc_attr($device); ?>" data-context="express_checkout" style="height: <?php echo (int) $wallet_height; ?>px;"></div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ('cart' === $context) : ?>
                        <div id="express-payment-method-wpg_paypal_cart_bottom">
                            <?php if ($draw_paypal) : ?>
                                <div id="ppcp_cart" class="<?php echo esc_attr($classes); ?>"></div>
                            <?php endif; ?>
                            <?php if ($draw_google) : ?>
                                <div class="google-pay-container cart <?php echo esc_attr($classes); ?>" data-context="cart" style="height: <?php echo (int) $wallet_height; ?>px;"></div>
                            <?php endif; ?>
                            <?php if ($draw_apple) : ?>
                                <div class="apple-pay-container cart <?php echo esc_attr($classes); ?>" data-context="cart" style="height: <?php echo (int) $wallet_height; ?>px;"></div>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="ppcp_checkout_parent">
                            <?php if ($draw_paypal) : ?>
                                <div id="ppcp_checkout" class="<?php echo esc_attr($classes); ?>"></div>
                            <?php endif; ?>
                            <?php if ($draw_google) : ?>
                                <div class="google-pay-container checkout <?php echo esc_attr($classes); ?>" data-context="checkout" style="height: <?php echo (int) $wallet_height; ?>px;"></div>
                            <?php endif; ?>
                            <?php if ($draw_apple) : ?>
                                <div class="apple-pay-container checkout <?php echo esc_attr($classes); ?>" data-context="checkout" style="height: <?php echo (int) $wallet_height; ?>px;"></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <script>window.wpgPPCPPreview = <?php echo wp_json_encode($config); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() escapes for a script context. ?>;</script>
                <?php if (!empty($sdk_url) && $draw_paypal) : ?>
                    <script src="<?php echo esc_url($sdk_url); ?>"></script>
                <?php endif; ?>
                <?php if ($draw_google) : ?>
                    <script src="<?php echo esc_url(self::GOOGLE_PAY_SDK); ?>"></script>
                <?php endif; ?>
                <?php if ($draw_apple) : ?>
                    <script src="<?php echo esc_url(self::APPLE_PAY_SDK); ?>"></script>
                <?php endif; ?>
                <script src="<?php echo esc_url(WPG_PLUGIN_ASSET_URL . 'ppcp/checkout-block/ppcp-editor-preview.js?ver=' . WPG_PLUGIN_VERSION); ?>"></script>
            </body>
        </html>
        <?php
        exit;
    }

    private static function get_settings() {
        $settings = get_option('woocommerce_wpg_paypal_checkout_settings', array());
        return is_array($settings) ? $settings : array();
    }

    private static function is_sandbox($settings) {
        return isset($settings['sandbox']) && 'yes' === $settings['sandbox'];
    }

    private static function get_store_country() {
        if (function_exists('WC') && WC() && isset(WC()->countries) && is_object(WC()->countries)) {
            return (string) WC()->countries->get_base_country();
        }
        return '';
    }

    private static function setting($settings, $key, $default) {
        return (isset($settings[$key]) && '' !== $settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * PayPal button styling for one placement, matching the front end's defaults.
     */
    public static function get_button_style($settings, $context) {
        $defaults = array(
            'express_checkout' => array('layout' => 'horizontal', 'height' => '40'),
            'cart' => array('layout' => 'vertical', 'height' => '48'),
            'checkout' => array('layout' => 'vertical', 'height' => '48'),
        );
        if (!isset($defaults[$context])) {
            $context = 'checkout';
        }
        $prefix = ('express_checkout' === $context) ? 'express_checkout_button_' : $context . '_button_';
        return array(
            'layout' => self::setting($settings, $prefix . 'layout', $defaults[$context]['layout']),
            'color' => self::setting($settings, $prefix . 'color', 'gold'),
            'shape' => self::setting($settings, $prefix . 'shape', 'rect'),
            'height' => (string) self::setting($settings, $prefix . 'height', $defaults[$context]['height']),
            'label' => self::setting($settings, $prefix . 'label', 'paypal'),
        );
    }

    /**
     * The size class the front end puts on a cart or checkout container, which is
     * what the stylesheet keys its max-width off. The express placement has no size
     * setting: the stylesheet gives it a fixed width.
     */
    public static function get_button_size($settings, $context) {
        if ('cart' !== $context && 'checkout' !== $context) {
            return '';
        }
        return self::setting($settings, $context . '_button_size', 'responsive');
    }

    /**
     * Google Pay / Apple Pay button styling for one placement.
     */
    public static function get_wallet_style($settings, $wallet, $context) {
        $prefix = $wallet . '_' . $context . '_page_';
        return array(
            'label' => self::setting($settings, $prefix . 'label', 'plain'),
            'color' => self::setting($settings, $prefix . 'color', 'black'),
            'shape' => self::setting($settings, $prefix . 'shape', 'rect'),
        );
    }

    /**
     * The funding methods this placement disallows, as the SDK will accept them.
     *
     * A setting saved as '' or array('') would otherwise reach the URL as
     * disable-funding= with nothing after it, and PayPal rejects the whole request:
     *
     *     SDK Validation error: 'Please pass a value for query param: disable-funding'
     *
     * That takes the entire SDK down, so the placement draws no buttons at all
     * rather than merely ignoring one setting.
     */
    private static function get_disallowed_funding($settings, $context) {
        $key = ('cart' === $context) ? 'cart_disallowed_funding_methods' : 'checkout_disallowed_funding_methods';
        $raw = isset($settings[$key]) ? (array) $settings[$key] : array();
        $disallowed = array();
        foreach ($raw as $method) {
            $method = strtolower(trim((string) $method));
            if ('' !== $method && !in_array($method, $disallowed, true)) {
                $disallowed[] = $method;
            }
        }
        return $disallowed;
    }

    /**
     * The alternative funding sources this placement offers, mirroring the
     * enable-funding argument the button manager builds for the front end.
     */
    public static function get_funding($settings, $context) {
        $disallowed = self::get_disallowed_funding($settings, $context);
        return array(
            'venmo' => !in_array('venmo', $disallowed, true),
            'paylater' => !in_array('paylater', $disallowed, true),
        );
    }

    /**
     * The PayPal JS SDK URL this page loads, '' when the account is not configured.
     *
     * A slim version of the front-end arguments: buttons only, no client token, and
     * commit=false, because nothing here starts a payment.
     */
    public static function get_sdk_url($settings, $context) {
        $client_id_key = self::is_sandbox($settings) ? 'rest_client_id_sandbox' : 'rest_client_id_live';
        $client_id = isset($settings[$client_id_key]) ? $settings[$client_id_key] : '';
        if (empty($client_id)) {
            return '';
        }
        // The currencies the PayPal SDK accepts; anything else falls back to USD,
        // matching how the button manager picks the front-end currency.
        $supported = array('AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'INR', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD');
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
        $args = array(
            'client-id' => $client_id,
            'currency' => apply_filters('wpg_ppcp_woocommerce_currency', in_array($currency, $supported, true) ? $currency : 'USD'),
            'intent' => (isset($settings['paymentaction']) && 'authorize' === $settings['paymentaction']) ? 'authorize' : 'capture',
            'commit' => 'false',
            'components' => 'buttons',
        );
        if (class_exists('PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler')) {
            $locale = apply_filters('wpg_ppcp_sdk_locale', PPCP_Paypal_Checkout_For_Woocommerce_Locale_Handler::get_valid_locale());
            // Every argument must carry a value; PayPal rejects the whole request over
            // an empty one, and takes the buttons with it.
            if (!empty($locale)) {
                $args['locale'] = $locale;
            }
        }
        // Which alternatives PayPal considers eligible depends on where it thinks the
        // buyer is. On the front end the SDK works that out from the shopper; here
        // there is no shopper, so a sandbox preview is told the store's own country,
        // the way the button manager tells it the customer's. PayPal only accepts
        // buyer-country in sandbox, so it is never sent for a live account.
        if (self::is_sandbox($settings)) {
            $country = self::get_store_country();
            if (2 === strlen($country)) {
                $args['buyer-country'] = $country;
            }
        }
        $enable = array();
        foreach (self::get_funding($settings, $context) as $source => $allowed) {
            if ($allowed) {
                $enable[] = $source;
            }
        }
        if (!empty($enable)) {
            $args['enable-funding'] = implode(',', $enable);
        }
        $disallowed = self::get_disallowed_funding($settings, $context);
        if (!empty($disallowed)) {
            $args['disable-funding'] = implode(',', $disallowed);
        }
        return add_query_arg($args, 'https://www.paypal.com/sdk/js');
    }
}
