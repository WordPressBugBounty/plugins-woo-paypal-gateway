<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records migration activity for debugging and admin visibility.
 *
 * Stores a structured log in wp_options so merchants and support staff
 * can see exactly what ran, when, and whether it succeeded — without
 * requiring WP_DEBUG or server log access.
 */
class WPG_Migration_Logger {

	const LOG_OPTION = 'wpg_ppcp_migration_log';
	const MAX_ENTRIES = 50;

	/**
	 * Record a migration event.
	 *
	 * @param string $version     Migration version.
	 * @param string $status      'started', 'success', 'failed', 'verify_failed', 'skipped'.
	 * @param string $description Human-readable description.
	 * @param string $detail      Optional extra detail (error message, etc).
	 */
	public function log( $version, $status, $description, $detail = '' ) {
		$log = get_option( self::LOG_OPTION, array() );

		$log[] = array(
			'version'     => $version,
			'status'      => $status,
			'description' => $description,
			'detail'      => $detail,
			'timestamp'   => current_time( 'mysql' ),
			'php_version' => PHP_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
		);

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_ENTRIES );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Get the full migration log.
	 *
	 * @return array
	 */
	public function get_log() {
		return get_option( self::LOG_OPTION, array() );
	}

	/**
	 * Get the last migration entry.
	 *
	 * @return array|null
	 */
	public function get_last_entry() {
		$log = $this->get_log();
		return ! empty( $log ) ? end( $log ) : null;
	}

	/**
	 * Check if any migration has failed.
	 *
	 * @return bool
	 */
	public function has_failures() {
		$log = $this->get_log();
		foreach ( $log as $entry ) {
			if ( in_array( $entry['status'], array( 'failed', 'verify_failed' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clear the log.
	 */
	public function clear() {
		delete_option( self::LOG_OPTION );
	}
}
