<?php
/**
 * Scheduler
 *
 * Manages the WP-Cron scheduled sync job.
 *
 * Responsibilities:
 *   - Register a custom 'every_6_hours' cron interval
 *   - Schedule the recurring event on plugin activation
 *   - Run sync_all_users() on each cron tick
 *   - Expose methods for Activator/Deactivator to manage scheduling
 *
 * @package MCAS\Sync
 */

declare( strict_types=1 );

namespace MCAS\Sync;

class Scheduler {

	/** Cron hook name. */
	const HOOK = 'mcas_scheduled_sync';

	/** Default recurrence. */
	const DEFAULT_INTERVAL = 'every_6_hours';

	private SyncManager $sync_manager;

	public function __construct( SyncManager $sync_manager ) {
		$this->sync_manager = $sync_manager;
		$this->register_hooks();
	}

	/**
	 * Register hooks for cron interval and job execution.
	 */
	private function register_hooks(): void {
		// Register the custom interval.
		add_filter( 'cron_schedules', [ $this, 'register_intervals' ] );

		// Handle the cron event when it fires.
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @param array $schedules Existing WP cron schedules.
	 * @return array
	 */
	public function register_intervals( array $schedules ): array {
		$schedules['every_6_hours'] = [
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 6 Hours', 'mcas' ),
		];

		$schedules['every_12_hours'] = [
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 Hours', 'mcas' ),
		];

		return $schedules;
	}

	/**
	 * Execute the scheduled sync — called by WP-Cron.
	 */
	public function run(): void {
		$results = $this->sync_manager->sync_all_users( 'scheduled_sync' );

		// Store last run timestamp and result summary in network options.
		$success = count( array_filter( $results, fn( $r ) => $r->is_success() ) );
		$failed  = count( array_filter( $results, fn( $r ) => $r->is_failed() ) );

		update_site_option( 'mcas_last_cron_run', [
			'timestamp' => time(),
			'success'   => $success,
			'failed'    => $failed,
			'total'     => count( $results ),
		] );
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 * Called from Activator.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), self::DEFAULT_INTERVAL, self::HOOK );
		}
	}

	/**
	 * Unschedule the cron event.
	 * Called from Deactivator.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	/**
	 * Get the last cron run summary from network options.
	 *
	 * @return array|null
	 */
	public static function get_last_run(): ?array {
		$data = get_site_option( 'mcas_last_cron_run' );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get the next scheduled run timestamp.
	 *
	 * @return int|false
	 */
	public static function get_next_run(): int|false {
		return wp_next_scheduled( self::HOOK );
	}
}
