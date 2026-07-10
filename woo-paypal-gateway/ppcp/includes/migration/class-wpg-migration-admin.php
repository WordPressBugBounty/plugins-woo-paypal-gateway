<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin integration for the migration system.
 *
 * Adds a "System Status" section to the PayPal gateway debug info,
 * and provides an AJAX endpoint for emergency settings rollback.
 */
class WPG_Migration_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_wpg_ppcp_migration_status', array( __CLASS__, 'ajax_migration_status' ) );
		add_action( 'wp_ajax_wpg_ppcp_rollback_settings', array( __CLASS__, 'ajax_rollback_settings' ) );
		add_filter( 'woocommerce_system_status_report', array( __CLASS__, 'add_system_status_info' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
	}

	/**
	 * Handle admin action links (rollback, clear log).
	 */
	public static function handle_admin_actions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wpg_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_GET['wpg_action'] ) );

			if ( 'rollback_settings' === $action && check_admin_referer( 'wpg_rollback_settings' ) ) {
				$registry = WPG_Migration_Bootstrap::get_registry();
				if ( $registry && $registry->restore_snapshot() ) {
					add_action( 'admin_notices', function () {
						echo '<div class="notice notice-success"><p>';
						esc_html_e( 'PayPal Gateway settings have been restored from the pre-migration snapshot.', 'woo-paypal-gateway' );
						echo '</p></div>';
					} );
				}
			}

			if ( 'clear_migration_log' === $action && check_admin_referer( 'wpg_clear_migration_log' ) ) {
				$registry = WPG_Migration_Bootstrap::get_registry();
				if ( $registry ) {
					$registry->get_logger()->clear();
					add_action( 'admin_notices', function () {
						echo '<div class="notice notice-success"><p>';
						esc_html_e( 'Migration log cleared.', 'woo-paypal-gateway' );
						echo '</p></div>';
					} );
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * AJAX: Return migration status as JSON.
	 */
	public static function ajax_migration_status() {
		check_ajax_referer( 'wpg_ppcp_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$registry = WPG_Migration_Bootstrap::get_registry();
		if ( ! $registry ) {
			wp_send_json_error( 'Migration system not initialized.' );
		}

		$logger  = $registry->get_logger();
		$health  = $registry->get_health_check();

		wp_send_json_success( array(
			'db_version'     => $registry->get_db_version(),
			'plugin_version' => WPG_PLUGIN_VERSION,
			'migration_log'  => $logger->get_log(),
			'health_checks'  => $health->run_all(),
			'has_snapshot'   => (bool) get_option( WPG_Migration_Registry::SNAPSHOT_OPTION, false ),
		) );
	}

	/**
	 * AJAX: Rollback settings to pre-migration snapshot.
	 */
	public static function ajax_rollback_settings() {
		check_ajax_referer( 'wpg_ppcp_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$registry = WPG_Migration_Bootstrap::get_registry();
		if ( ! $registry ) {
			wp_send_json_error( 'Migration system not initialized.' );
		}

		$restored = $registry->restore_snapshot();

		if ( $restored ) {
			wp_send_json_success( 'Settings restored from pre-migration snapshot.' );
		} else {
			wp_send_json_error( 'No snapshot available for rollback.' );
		}
	}

	/**
	 * Add migration info to WooCommerce System Status.
	 */
	public static function add_system_status_info() {
		$registry = WPG_Migration_Bootstrap::get_registry();
		if ( ! $registry ) {
			return;
		}

		$db_version = $registry->get_db_version();
		$logger     = $registry->get_logger();
		$last       = $logger->get_last_entry();
		?>
		<table class="wc_status_table widefat" cellspacing="0">
			<thead>
				<tr>
					<th colspan="3" data-export-label="PayPal Gateway Migration">
						<?php esc_html_e( 'PayPal Gateway Migration Status', 'woo-paypal-gateway' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td data-export-label="Plugin Version"><?php esc_html_e( 'Plugin version', 'woo-paypal-gateway' ); ?>:</td>
					<td class="help">&nbsp;</td>
					<td><?php echo esc_html( WPG_PLUGIN_VERSION ); ?></td>
				</tr>
				<tr>
					<td data-export-label="DB Version"><?php esc_html_e( 'Database version', 'woo-paypal-gateway' ); ?>:</td>
					<td class="help">&nbsp;</td>
					<td>
						<?php echo esc_html( $db_version ); ?>
						<?php if ( version_compare( $db_version, WPG_PLUGIN_VERSION, '<' ) ) : ?>
							<mark class="error"><?php esc_html_e( '(migration pending)', 'woo-paypal-gateway' ); ?></mark>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( $last ) : ?>
				<tr>
					<td data-export-label="Last Migration"><?php esc_html_e( 'Last migration', 'woo-paypal-gateway' ); ?>:</td>
					<td class="help">&nbsp;</td>
					<td>
						<?php echo esc_html( $last['version'] . ' — ' . $last['status'] . ' — ' . $last['timestamp'] ); ?>
					</td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}
}
