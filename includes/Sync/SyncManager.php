<?php
/**
 * SyncManager
 *
 * Orchestrates the full user sync cycle across the network.
 * Responsible for:
 *   - Resolving which blogs to sync to (filterable)
 *   - Firing before/after action hooks
 *   - Looping subsites via switch_to_blog()
 *   - Delegating per-subsite work to UserReplicator
 *   - Returning a SyncResult[] array for logging
 *
 * @package MCAS\Sync
 */

declare( strict_types=1 );

namespace MCAS\Sync;

class SyncManager {

	private UserReplicator $replicator;

	public function __construct() {
		$this->replicator = new UserReplicator();
	}

	/**
	 * Sync a single user across all eligible network subsites.
	 *
	 * @param int    $user_id     The user ID to sync.
	 * @param string $action      The event that triggered the sync
	 *                            e.g. 'profile_update', 'role_change',
	 *                            'user_register', 'manual_sync'.
	 * @param int    $source_blog The blog ID where the change originated.
	 *                            Defaults to the current blog.
	 *
	 * @return SyncResult[] Results indexed by target blog ID.
	 */
	public function sync_user( int $user_id, string $action, int $source_blog = 0 ): array {
		if ( ! $source_blog ) {
			$source_blog = get_current_blog_id();
		}

		/**
		 * Allow short-circuiting the sync entirely for a user/event combination.
		 *
		 * @param bool   $should_sync Whether to proceed.
		 * @param int    $user_id
		 * @param string $action
		 */
		$should_sync = apply_filters( 'mcas_should_sync_user', true, $user_id, $action );

		if ( ! $should_sync ) {
			return [];
		}

		$target_blogs = $this->get_target_blogs( $user_id, $source_blog );

		if ( empty( $target_blogs ) ) {
			return [];
		}

		/**
		 * Fires before the sync loop begins.
		 *
		 * @param int    $user_id
		 * @param int    $source_blog
		 * @param string $action
		 */
		do_action( 'mcas_before_user_sync', $user_id, $source_blog, $action );

		$results = [];

		foreach ( $target_blogs as $blog_id ) {
			switch_to_blog( $blog_id );

			$result = $this->replicator->replicate( $user_id, $source_blog, $action, $blog_id );
			$results[ $blog_id ] = $result;

			restore_current_blog();

			// Fire individual failure hook immediately.
			if ( $result->is_failed() ) {
				/**
				 * Fires when a sync to a specific subsite fails.
				 *
				 * @param int    $user_id
				 * @param int    $target_blog
				 * @param string $message
				 */
				do_action( 'mcas_sync_failed', $user_id, $blog_id, $result->message );
			}
		}

		/**
		 * Fires after the full sync loop completes.
		 *
		 * @param int          $user_id
		 * @param int[]        $synced_blogs
		 * @param SyncResult[] $results
		 */
		do_action( 'mcas_after_user_sync', $user_id, array_keys( $results ), $results );

		return $results;
	}

	/**
	 * Sync all users on the network to all subsites.
	 * Used by the manual full-network sweep and the cron job.
	 *
	 * @param string $action  Label for this batch e.g. 'manual_sync', 'scheduled_sync'.
	 *
	 * @return SyncResult[]   Flat array of all results across all users and blogs.
	 */
	public function sync_all_users( string $action = 'manual_sync' ): array {
		$users = get_users( [ 'fields' => 'ID', 'number' => -1 ] );
		$all_results = [];

		foreach ( $users as $user_id ) {
			$results     = $this->sync_user( (int) $user_id, $action );
			$all_results = array_merge( $all_results, $results );
		}

		return $all_results;
	}

	/**
	 * Resolve the list of target blog IDs for a sync operation.
	 *
	 * Excludes the source blog. Filterable so developers can
	 * restrict syncing to a subset of subsites.
	 *
	 * @param int $user_id
	 * @param int $source_blog
	 *
	 * @return int[]
	 */
	private function get_target_blogs( int $user_id, int $source_blog ): array {
		$all_blogs = get_sites( [
			'fields' => 'ids',
			'number' => 0,
		] );

		// Exclude the source blog — no need to sync back to origin.
		$targets = array_values(
			array_filter( $all_blogs, fn( $id ) => (int) $id !== $source_blog )
		);

		/**
		 * Filter the list of target blog IDs that will receive the sync.
		 *
		 * @param int[] $targets     Blog IDs to sync to.
		 * @param int   $user_id     User being synced.
		 * @param int   $source_blog Blog ID where change originated.
		 */
		return apply_filters( 'mcas_blogs_to_sync', $targets, $user_id, $source_blog );
	}
}
