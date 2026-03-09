<?php
/**
 * Handles plugin deactivation.
 *
 * Clears scheduled cron events. Does NOT drop the database table
 * on deactivation — only on uninstall (see uninstall.php).
 *
 * @package MCAS
 */

declare( strict_types=1 );

namespace MCAS;

class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		// Clear the scheduled cron job added in Phase 7.
		$timestamp = wp_next_scheduled( 'mcas_scheduled_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'mcas_scheduled_sync' );
		}
	}
}
