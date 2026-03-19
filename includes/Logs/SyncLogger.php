<?php
/**
 * SyncLogger
 *
 * Listens to sync lifecycle hooks and persists SyncResult
 * objects to the database via LogRepository.
 *
 * Keeps logging concerns completely separate from sync logic.
 *
 * @package MCAS\Logs
 */

declare( strict_types=1 );

namespace MCAS\Logs;

class SyncLogger {

	private LogRepository $repository;

	public function __construct( LogRepository $repository ) {
		$this->repository = $repository;
		$this->register_hooks();
	}

	/**
	 * Register hooks to capture sync events.
	 */
	private function register_hooks(): void {
		// Log all results after a full user sync completes.
		add_action( 'mcas_after_user_sync', [ $this, 'log_sync_results' ], 10, 3 );
	}

	/**
	 * Persist all SyncResult objects from a completed sync cycle.
	 *
	 * @param int          $user_id      The user that was synced.
	 * @param int[]        $synced_blogs Blog IDs that were targeted.
	 * @param \MCAS\Sync\SyncResult[] $results Results indexed by blog ID.
	 */
	public function log_sync_results( int $user_id, array $synced_blogs, array $results ): void {
		if ( empty( $results ) ) {
			return;
		}

		$this->repository->insert_batch( $results );
	}
}
