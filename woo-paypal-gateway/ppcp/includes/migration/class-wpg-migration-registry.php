<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration registry and runner.
 *
 * Improvements over competitor (pymntpl-paypal-woocommerce Update.php):
 *
 * 1. Class-based migrations with up() + verify() instead of raw include files
 * 2. Pre-migration settings snapshots for safe rollback
 * 3. Structured logging visible in admin (not just error_log)
 * 4. Health checks after migration completes
 * 5. Fresh install detection — skips all migrations for new installs
 * 6. Runs on admin_init not init — zero frontend performance impact
 * 7. Prevents double-execution with a transient lock
 * 8. WooCommerce-dependency awareness per migration
 */
class WPG_Migration_Registry {

	const DB_VERSION_OPTION  = 'wpg_ppcp_db_version';
	const LOCK_TRANSIENT     = 'wpg_ppcp_migration_lock';
	const SNAPSHOT_OPTION    = 'wpg_ppcp_pre_migration_snapshot';
	const FRESH_INSTALL_FLAG = 'wpg_ppcp_is_fresh_install';

	/**
	 * Registered migration class names, keyed by version.
	 *
	 * @var array<string, string>
	 */
	private $migrations = array();

	/**
	 * @var WPG_Migration_Logger
	 */
	private $logger;

	/**
	 * @var WPG_Migration_Health_Check
	 */
	private $health_check;

	/**
	 * @var string Current plugin version.
	 */
	private $plugin_version;

	public function __construct( $plugin_version ) {
		$this->plugin_version = $plugin_version;
		$this->logger         = new WPG_Migration_Logger();
		$this->health_check   = new WPG_Migration_Health_Check();
	}

	/**
	 * Register a migration class for a target version.
	 *
	 * @param string $version    Semantic version this migration upgrades to.
	 * @param string $class_name Fully qualified class name implementing WPG_Migration_Interface.
	 */
	public function register( $version, $class_name ) {
		$this->migrations[ $version ] = $class_name;
	}

	/**
	 * Initialize — hook into WordPress lifecycle.
	 */
	public function init() {
		if ( $this->is_fresh_install() ) {
			$this->handle_fresh_install();
			return;
		}

		add_action( 'admin_init', array( $this, 'maybe_run_migrations' ), 5 );
		add_action( 'admin_notices', array( $this, 'maybe_show_migration_notice' ) );
	}

	/**
	 * Detect a brand-new installation (no previous version stored).
	 *
	 * @return bool
	 */
	private function is_fresh_install() {
		$stored = get_option( self::DB_VERSION_OPTION, false );

		if ( false !== $stored ) {
			return false;
		}

		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', false );
		if ( false !== $settings && ! empty( $settings ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Fresh installs skip all migrations and set the version to current.
	 */
	private function handle_fresh_install() {
		update_option( self::DB_VERSION_OPTION, $this->plugin_version );
		update_option( self::FRESH_INSTALL_FLAG, 'yes' );
		$this->logger->log(
			$this->plugin_version,
			'skipped',
			'Fresh installation detected — all migrations skipped.'
		);
	}

	/**
	 * Check if migrations need to run and execute them.
	 */
	public function maybe_run_migrations() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$db_version = $this->get_db_version();

		if ( version_compare( $db_version, $this->plugin_version, '>=' ) ) {
			return;
		}

		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			$this->run_pending_migrations( $db_version );
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Execute all pending migrations in version order.
	 *
	 * @param string $current_db_version
	 */
	private function run_pending_migrations( $current_db_version ) {
		$pending = $this->get_pending_migrations( $current_db_version );

		if ( empty( $pending ) ) {
			$this->update_db_version( $this->plugin_version );
			$this->logger->log(
				$this->plugin_version,
				'success',
				'Version updated (no migrations needed).'
			);
			return;
		}

		$this->take_settings_snapshot();

		$all_succeeded = true;

		foreach ( $pending as $version => $class_name ) {
			$migration = new $class_name();

			if ( ! ( $migration instanceof WPG_Migration_Interface ) ) {
				$this->logger->log( $version, 'failed', 'Invalid migration class.', $class_name );
				$all_succeeded = false;
				break;
			}

			if ( $migration->requires_woocommerce() && ! function_exists( 'WC' ) ) {
				$this->logger->log(
					$version,
					'skipped',
					$migration->get_description(),
					'WooCommerce not loaded — migration deferred.'
				);
				continue;
			}

			$this->logger->log( $version, 'started', $migration->get_description() );

			$result = false;
			try {
				$result = $migration->up();
			} catch ( \Exception $e ) {
				$this->logger->log( $version, 'failed', $migration->get_description(), $e->getMessage() );
				$all_succeeded = false;
				break;
			}

			if ( ! $result ) {
				$this->logger->log( $version, 'failed', $migration->get_description(), 'up() returned false.' );
				$all_succeeded = false;
				break;
			}

			$verified = false;
			try {
				$verified = $migration->verify();
			} catch ( \Exception $e ) {
				$verified = false;
			}

			if ( ! $verified ) {
				$this->logger->log( $version, 'verify_failed', $migration->get_description(), 'verify() returned false — migration may be incomplete.' );
			} else {
				$this->logger->log( $version, 'success', $migration->get_description() );
			}

			$this->update_db_version( $version );
		}

		if ( $all_succeeded ) {
			$this->update_db_version( $this->plugin_version );
		}

		$health_results = $this->health_check->run_all();
		if ( ! $this->health_check->all_passed( $health_results ) ) {
			$failures = array();
			foreach ( $health_results as $r ) {
				if ( ! $r['pass'] ) {
					$failures[] = $r['name'] . ': ' . $r['message'];
				}
			}
			$this->logger->log(
				$this->plugin_version,
				'verify_failed',
				'Post-migration health check failures.',
				implode( '; ', $failures )
			);
		}
	}

	/**
	 * Get migrations that need to run, sorted by version.
	 *
	 * @param string $current_db_version
	 * @return array<string, string> version => class_name
	 */
	private function get_pending_migrations( $current_db_version ) {
		$pending = array();

		foreach ( $this->migrations as $version => $class_name ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				$pending[ $version ] = $class_name;
			}
		}

		uksort( $pending, 'version_compare' );

		return $pending;
	}

	/**
	 * Store a snapshot of settings before migration.
	 *
	 * Enables manual rollback if a migration corrupts settings.
	 * The competitor has no equivalent.
	 */
	private function take_settings_snapshot() {
		$snapshot = array(
			'timestamp'       => current_time( 'mysql' ),
			'db_version'      => $this->get_db_version(),
			'plugin_version'  => $this->plugin_version,
			'paypal_settings' => get_option( 'woocommerce_wpg_paypal_checkout_settings', array() ),
			'cc_settings'     => get_option( 'woocommerce_wpg_paypal_checkout_cc_settings', array() ),
		);

		update_option( self::SNAPSHOT_OPTION, $snapshot, false );
	}

	/**
	 * Restore settings from the pre-migration snapshot.
	 *
	 * @return bool True if restored, false if no snapshot exists.
	 */
	public function restore_snapshot() {
		$snapshot = get_option( self::SNAPSHOT_OPTION, false );

		if ( ! $snapshot || ! is_array( $snapshot ) ) {
			return false;
		}

		if ( isset( $snapshot['paypal_settings'] ) ) {
			update_option( 'woocommerce_wpg_paypal_checkout_settings', $snapshot['paypal_settings'] );
		}

		if ( isset( $snapshot['cc_settings'] ) ) {
			update_option( 'woocommerce_wpg_paypal_checkout_cc_settings', $snapshot['cc_settings'] );
		}

		if ( isset( $snapshot['db_version'] ) ) {
			$this->update_db_version( $snapshot['db_version'] );
		}

		$this->logger->log(
			$this->plugin_version,
			'success',
			'Settings restored from pre-migration snapshot.',
			'Snapshot taken at ' . $snapshot['timestamp']
		);

		return true;
	}

	/**
	 * Prevent concurrent migration runs.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return false;
		}
		set_transient( self::LOCK_TRANSIENT, time(), 5 * MINUTE_IN_SECONDS );
		return true;
	}

	private function release_lock() {
		delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * @return string
	 */
	public function get_db_version() {
		return get_option( self::DB_VERSION_OPTION, '0' );
	}

	/**
	 * @param string $version
	 */
	private function update_db_version( $version ) {
		update_option( self::DB_VERSION_OPTION, $version );
	}

	/**
	 * @return WPG_Migration_Logger
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * @return WPG_Migration_Health_Check
	 */
	public function get_health_check() {
		return $this->health_check;
	}

	/**
	 * Show admin notice if a migration failed.
	 */
	public function maybe_show_migration_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! $this->logger->has_failures() ) {
			return;
		}

		$last = $this->logger->get_last_entry();
		if ( ! $last || ! in_array( $last['status'], array( 'failed', 'verify_failed' ), true ) ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>' . esc_html__( 'PayPal Gateway — Migration Notice', 'woo-paypal-gateway' ) . '</strong></p>';
		echo '<p>';
		printf(
			/* translators: 1: migration version, 2: failure detail */
			esc_html__( 'A database migration to version %1$s encountered an issue: %2$s', 'woo-paypal-gateway' ),
			esc_html( $last['version'] ),
			esc_html( $last['detail'] ?: $last['description'] )
		);
		echo '</p>';
		echo '<p>' . esc_html__( 'Your payment gateway continues to work normally. Please contact support if this persists.', 'woo-paypal-gateway' ) . '</p>';
		echo '</div>';
	}
}
