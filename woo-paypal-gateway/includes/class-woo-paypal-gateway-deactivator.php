<?php

/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    Woo_Paypal_Gateway
 * @subpackage Woo_Paypal_Gateway/includes
 * @author     easypayment
 */
class Woo_Paypal_Gateway_Deactivator {

    /**
     * @since    1.0.0
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'wpg_ppcp_prewarm_client_token' );
    }

}
