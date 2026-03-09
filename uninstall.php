<?php
/**
 * Uninstall script.
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Drops the sync log table from every subsite on the network.
 *
 * @package MCAS
 */

// Security: only run from WordPress uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! is_multisite() ) {
	// Single site fallback — shouldn't happen but handle gracefully.
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcas_sync_log" );
	delete_option( 'mcas_db_version' );
	return;
}

// Network: drop table on every subsite.
$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );

foreach ( $sites as $site_id ) {
	switch_to_blog( $site_id );

	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcas_sync_log" );
	delete_option( 'mcas_db_version' );

	restore_current_blog();
}
