<?php
/**
 * Handles plugin activation across the network.
 *
 * Creates the wp_mcas_sync_log custom table on every subsite
 * when network-activated, and stores the plugin version.
 *
 * @package MCAS
 */

declare( strict_types=1 );

namespace MCAS;

class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * When network-activated WordPress calls this once — we loop
	 * over every subsite and create the table on each.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 */
	public static function activate( bool $network_wide ): void {
		if ( $network_wide && is_multisite() ) {
			self::activate_for_network();
		} else {
			self::create_table();
		}
	}

	/**
	 * Loop all subsites and create the table on each.
	 */
	private static function activate_for_network(): void {
		$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			self::create_table();
			restore_current_blog();
		}
	}

	/**
	 * Create the sync log table for the current site context.
	 *
	 * Table structure:
	 *   id            — auto-increment primary key
	 *   user_id       — WP user ID that was synced
	 *   source_blog   — blog ID where the change originated
	 *   target_blog   — blog ID that received the sync
	 *   action        — e.g. 'profile_update', 'role_change', 'manual_sync'
	 *   status        — 'success' | 'failed' | 'skipped'
	 *   message       — optional detail or error message
	 *   synced_at     — datetime of the sync event
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . MCAS_TABLE_LOG;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			source_blog BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
			target_blog BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
			action      VARCHAR(64)         NOT NULL DEFAULT '',
			status      VARCHAR(16)         NOT NULL DEFAULT 'success',
			message     TEXT,
			synced_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_id    (user_id),
			KEY idx_target     (target_blog),
			KEY idx_synced_at  (synced_at),
			KEY idx_status     (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'mcas_db_version', MCAS_VERSION );
	}
}
