<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration interface.
 *
 * Every version migration implements this contract.
 * Unlike the competitor's plain include files, our migrations are classes
 * with verify() for post-migration health checks and get_description()
 * for admin-visible logging.
 */
interface WPG_Migration_Interface {

	/**
	 * Target version this migration upgrades to.
	 *
	 * @return string Semantic version, e.g. '9.1.0'.
	 */
	public function get_version();

	/**
	 * Human-readable summary of what this migration does.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Run the migration.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function up();

	/**
	 * Verify the migration succeeded.
	 *
	 * Called after up(). If this returns false the migration is marked
	 * as failed in the log, but the plugin continues to load.
	 *
	 * @return bool
	 */
	public function verify();

	/**
	 * Whether this migration needs WooCommerce to be loaded.
	 *
	 * @return bool
	 */
	public function requires_woocommerce();
}
