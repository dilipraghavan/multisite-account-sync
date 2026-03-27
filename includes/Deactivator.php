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
		Sync\Scheduler::unschedule();
	}
}
