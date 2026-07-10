<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap the migration system.
 *
 * Loads all migration infrastructure and registers known migrations.
 * Called from the main plugin file before gateway initialization.
 */
class WPG_Migration_Bootstrap {

	/**
	 * @var WPG_Migration_Registry|null
	 */
	private static $registry = null;

	/**
	 * Initialize the migration system.
	 *
	 * @param string $plugin_version Current plugin version.
	 */
	public static function init( $plugin_version ) {
		if ( null !== self::$registry ) {
			return;
		}

		self::load_classes();
		self::$registry = new WPG_Migration_Registry( $plugin_version );
		self::register_migrations();
		self::handle_existing_installs_without_version();
		self::$registry->init();

		if ( is_admin() ) {
			WPG_Migration_Admin::init();
		}
	}

	/**
	 * Load migration system classes.
	 */
	private static function load_classes() {
		$dir = dirname( __FILE__ );

		require_once $dir . '/class-wpg-migration-interface.php';
		require_once $dir . '/class-wpg-migration-base.php';
		require_once $dir . '/class-wpg-migration-logger.php';
		require_once $dir . '/class-wpg-migration-health-check.php';
		require_once $dir . '/class-wpg-migration-registry.php';
		require_once $dir . '/class-wpg-migration-admin.php';

		$migrations_dir = $dir . '/migrations';
		require_once $migrations_dir . '/class-wpg-migration-9-1-0.php';
	}

	/**
	 * Register all known migrations.
	 *
	 * Add new migrations here as they are created.
	 * Order does not matter — the registry sorts by version.
	 */
	private static function register_migrations() {
		self::$registry->register( '9.1.0', 'WPG_Migration_9_1_0' );
	}

	/**
	 * Handle existing installs that predate the migration system.
	 *
	 * If settings exist but no DB version is stored, this is an existing
	 * install upgrading from <= 9.0.66.1 (before migration tracking existed).
	 * We seed the DB version to '9.0.66.1' so migrations from 9.1.0 onward run.
	 */
	private static function handle_existing_installs_without_version() {
		$db_version = get_option( WPG_Migration_Registry::DB_VERSION_OPTION, false );

		if ( false !== $db_version ) {
			return;
		}

		$settings = get_option( 'woocommerce_wpg_paypal_checkout_settings', false );

		if ( false !== $settings && ! empty( $settings ) ) {
			update_option( WPG_Migration_Registry::DB_VERSION_OPTION, '9.0.66.1' );
		}
	}

	/**
	 * Get the registry instance (for admin pages, debugging, etc).
	 *
	 * @return WPG_Migration_Registry|null
	 */
	public static function get_registry() {
		return self::$registry;
	}
}
