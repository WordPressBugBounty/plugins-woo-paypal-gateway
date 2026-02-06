<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Payment Gateway for PayPal on WooCommerce
 * Plugin URI:        https://profiles.wordpress.org/easypayment
 * Description:       PayPal, Credit/Debit Cards, Google Pay, Apple Pay, Pay Later, Venmo, SEPA, iDEAL, Mercado Pago, Sofort, Bancontact & more - by an official PayPal Partner
 * Version:           9.0.56
 * Author:            easypayment
 * Author URI:        https://profiles.wordpress.org/easypayment/
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woo-paypal-gateway
 * Domain Path:       /languages
 * Requires at least: 4.7
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Tested up to: 6.9.1
 * WC requires at least: 3.4
 * WC tested up to: 10.4.3
 */
if (!defined('WPINC')) {
    die;
}


if (!defined('WPG_PLUGIN_VERSION')) {
    define('WPG_PLUGIN_VERSION', '9.0.56');
}
if (!defined('WPG_PLUGIN_PATH')) {
    define('WPG_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));
}
if (!defined('WPG_PLUGIN_DIR')) {
    define('WPG_PLUGIN_DIR', dirname(__FILE__));
}
if (!defined('WPG_PLUGIN_BASENAME')) {
    define('WPG_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('WPG_PLUGIN_ASSET_URL')) {
    define('WPG_PLUGIN_ASSET_URL', plugin_dir_url(__FILE__));
}
if (!defined('WPG_PLUGIN_FILE')) {
    define('WPG_PLUGIN_FILE', __FILE__);
}
if (!defined('WPG_SANDBOX_PARTNER_MERCHANT_ID')) {
    define('WPG_SANDBOX_PARTNER_MERCHANT_ID', 'K6QLN2LPGQRHL');
}
if (!defined('WPG_LIVE_PARTNER_MERCHANT_ID')) {
    define('WPG_LIVE_PARTNER_MERCHANT_ID', 'GT5R877JNBPLL');
}
if (!defined('WPG_ONBOARDING_URL')) {
    define('WPG_ONBOARDING_URL', 'https://mbjtechnolabs.com/ppcp-seller-onboarding/seller-onboarding.php');
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woo-paypal-gateway-activator.php
 */
function activate_woo_paypal_gateway() {
    set_transient('woo_paypal_gateway_redirect', true, 30);
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-paypal-gateway-activator.php';
    Woo_Paypal_Gateway_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woo-paypal-gateway-deactivator.php
 */
function deactivate_woo_paypal_gateway() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-woo-paypal-gateway-deactivator.php';
    Woo_Paypal_Gateway_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woo_paypal_gateway');
register_deactivation_hook(__FILE__, 'deactivate_woo_paypal_gateway');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */

require_once WPG_PLUGIN_DIR . '/ppcp/includes/ppcp-paypal-checkout-for-woocommerce-function.php';
require plugin_dir_path(__FILE__) . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce.php';
require plugin_dir_path(__FILE__) . 'includes/class-woo-paypal-gateway.php';

/**
 * Begins execution of the plugin.
 * @since    1.0.0
 */
function run_woo_paypal_gateway() {
    $plugin = new Woo_Paypal_Gateway();
    $plugin->run();
}

function init_wpg_woo_paypal_gateway_class() {
    if (class_exists('WC_Payment_Gateway')) {
        run_ppcp_paypal_checkout_for_woocommerce();
    }
    run_woo_paypal_gateway();
}

add_action('plugins_loaded', 'init_wpg_woo_paypal_gateway_class', 11);

if (!function_exists('run_ppcp_paypal_checkout_for_woocommerce')) {
    run_ppcp_paypal_checkout_for_woocommerce();
}

function run_ppcp_paypal_checkout_for_woocommerce() {
    if (!class_exists('PPCP_Paypal_Checkout_For_Woocommerce')) {
        require plugin_dir_path(__FILE__) . '/ppcp/includes/class-ppcp-paypal-checkout-for-woocommerce.php';
    }
    $plugin = new PPCP_Paypal_Checkout_For_Woocommerce();
    $plugin->run();
}

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action( 'woocommerce_blocks_loaded', function () {
    if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }
    $checkout_block_file = WPG_PLUGIN_DIR . '/ppcp/checkout-block/ppcp-checkout-block.php';
    $cc_block_file       = WPG_PLUGIN_DIR . '/ppcp/checkout-block/ppcp-cc-block.php';
    if ( file_exists( $checkout_block_file ) ) {
        require_once $checkout_block_file;
    }
    if ( file_exists( $cc_block_file ) ) {
        require_once $cc_block_file;
    }
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            if ( class_exists( 'PPCP_Checkout_Block' ) ) {
                $payment_method_registry->register( new PPCP_Checkout_Block() );
            }
            if ( class_exists( 'PPCP_Checkout_CC_Block' ) ) {
                $payment_method_registry->register( new PPCP_Checkout_CC_Block() );
            }
        }
    );
}, 20 );


add_action('admin_init', 'woo_paypal_gateway_redirect_to_settings');

function woo_paypal_gateway_redirect_to_settings() {
    // Check if the transient is set and user has access to the admin panel
    if (get_transient('woo_paypal_gateway_redirect')) {
        // Remove the transient so it only redirects once
        delete_transient('woo_paypal_gateway_redirect');

        // Make sure the redirect only happens for administrators
        if (is_admin() && current_user_can('manage_options')) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout'));
            exit;
        }
    }
}

function wpg_is_using_block_cart_or_checkout() {
    if (!function_exists('has_block') || !class_exists('\Automattic\WooCommerce\Blocks\Package')) {
        return false;
    }
    global $post;
    if (!$post instanceof \WP_Post) {
        return false;
    }
    if(is_admin_checkout_page_edit_screen()) {
        return true;
    }
    return has_block('woocommerce/cart', $post) || has_block('woocommerce/checkout', $post);
}

add_action('admin_notices', 'wpg_paypal_classic_sunset_notice_v5');
function wpg_paypal_classic_sunset_notice_v5() {

    if (!is_admin() || !current_user_can('manage_woocommerce')) {
        return;
    }

    if (!function_exists('get_active_classic_gateways')) {
        return;
    }

    $active_classic = get_active_classic_gateways();
    if (empty($active_classic)) {
        return;
    }

    $switch_url = admin_url(
        'admin.php?page=wc-settings&tab=checkout&section=wpg_paypal_checkout'
    );

    echo '<div class="notice notice-warning" style="border-left:4px solid #b99000; padding:0;">';
    echo '  <div style="padding:16px 18px; background:#fff;">';

    echo '    <div style="max-width:100%;">';

    // Title
    echo '      <div style="font-size:14px; font-weight:700; margin:0 0 6px; color:#1d2327;">';
    echo '        🚨 Action Required: PayPal Classic is being retired';
    echo '      </div>';

    // Body (updated sentence)
    echo '      <div style="font-size:13px; color:#50575e; margin:0 0 14px; line-height:1.55;">';
    echo '        Your store is currently using legacy PayPal payment methods that are being sunset soon. ';
    echo '        <strong>If you do not upgrade, customers may be unable to complete payments, resulting in lost orders.</strong><br><br>';
    echo '        To avoid service interruption, please switch to <strong>PayPal Checkout</strong>, ';
    echo '        which supports <strong>Advanced Card Payments</strong>, <strong>Google Pay</strong>, ';
    echo '        and <strong>Apple Pay</strong>.';
    echo '      </div>';

    // Active Classic gateways
    echo '      <div style="margin:0 0 16px;">';
    echo '        <div style="font-size:12px; font-weight:600; color:#50575e; margin:0 0 8px;">';
    echo '          Detected active Classic gateways (click to review)';
    echo '        </div>';
    echo '        <div style="display:flex; flex-wrap:wrap; gap:8px;">';

    foreach ($active_classic as $gateway) {
        echo '<a href="' . esc_url($gateway['settings_url']) . '" 
                  style="
                    display:inline-flex;
                    align-items:center;
                    padding:6px 14px;
                    border-radius:999px;
                    background:#f6f7f7;
                    border:1px solid #dcdcde;
                    font-size:12px;
                    color:#1d2327;
                    text-decoration:none;
                    line-height:1;
                  "
                  title="Review gateway settings">';
        echo esc_html($gateway['title']);
        echo '</a>';
    }

    echo '        </div>';
    echo '      </div>';

    // CTA
    echo '      <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center;">';
    echo '        <a class="button button-primary" href="' . esc_url($switch_url) . '">Upgrade to PayPal Checkout Now</a>';
    echo '        <span style="font-size:12px; color:#6c7781;">';
    echo '          After upgrading, disable Classic gateways to prevent duplicate payment options.';
    echo '        </span>';
    echo '      </div>';

    echo '    </div>'; // content
    echo '  </div>';   // wrapper
    echo '</div>';
}

