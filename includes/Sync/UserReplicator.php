<?php
/**
 * UserReplicator
 *
 * Responsible for replicating a single user's data into the
 * current blog context. Always called from within a
 * switch_to_blog() / restore_current_blog() block.
 *
 * Handles:
 *   - Adding the user to the subsite if not already a member
 *   - Syncing user meta keys (filterable)
 *   - Syncing the user's role on the subsite
 *
 * @package MCAS\Sync
 */

declare( strict_types=1 );

namespace MCAS\Sync;

class UserReplicator {

	/**
	 * Default user meta keys to sync across subsites.
	 * Filtered via 'mcas_sync_user_meta_keys'.
	 */
	private const DEFAULT_META_KEYS = [
		'first_name',
		'last_name',
		'description',
		'locale',
	];

	/**
	 * Replicate a user into the current blog context.
	 *
	 * Must be called from within switch_to_blog().
	 *
	 * @param int    $user_id     The user to replicate.
	 * @param int    $source_blog The blog ID where the change originated.
	 * @param string $action      The event type triggering this sync.
	 * @param int    $target_blog The blog receiving the sync.
	 *
	 * @return SyncResult
	 */
	public function replicate( int $user_id, int $source_blog, string $action, int $target_blog ): SyncResult {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new SyncResult(
				$user_id,
				$source_blog,
				$target_blog,
				$action,
				SyncResult::STATUS_FAILED,
				'User not found.'
			);
		}

		// Add user to the subsite if they are not already a member.
		if ( ! is_user_member_of_blog( $user_id ) ) {
			$added = add_user_to_blog( $target_blog, $user_id, $this->get_default_role() );

			if ( is_wp_error( $added ) ) {
				return new SyncResult(
					$user_id,
					$source_blog,
					$target_blog,
					$action,
					SyncResult::STATUS_FAILED,
					$added->get_error_message()
				);
			}
		}

		// Sync user meta.
		$this->sync_user_meta( $user_id );

		// Sync role from source blog.
		$this->sync_user_role( $user_id, $source_blog, $target_blog );

		return new SyncResult(
			$user_id,
			$source_blog,
			$target_blog,
			$action,
			SyncResult::STATUS_SUCCESS,
			'User synced successfully.'
		);
	}

	/**
	 * Sync filterable user meta keys for the given user.
	 *
	 * @param int $user_id
	 */
	private function sync_user_meta( int $user_id ): void {
		/**
		 * Filter the user meta keys that get synced across subsites.
		 *
		 * @param string[] $meta_keys List of meta key strings.
		 * @param int      $user_id   The user being synced.
		 */
		$meta_keys = apply_filters( 'mcas_sync_user_meta_keys', self::DEFAULT_META_KEYS, $user_id );

		foreach ( $meta_keys as $key ) {
			// get_user_meta without blog context gives us the network-level value.
			$value = get_user_meta( $user_id, $key, true );
			update_user_meta( $user_id, $key, $value );
		}
	}

	/**
	 * Sync the user's role from the source blog to the current (target) blog.
	 *
	 * @param int $user_id
	 * @param int $source_blog
	 * @param int $target_blog
	 */
	private function sync_user_role( int $user_id, int $source_blog, int $target_blog ): void {
		// Fetch role from source blog.
		switch_to_blog( $source_blog );
		$source_user = new \WP_User( $user_id );
		$roles       = $source_user->roles;
		restore_current_blog();

		// We are already switched to target blog at this point.
		// Re-switch to target to apply the role.
		switch_to_blog( $target_blog );
		$target_user = new \WP_User( $user_id );

		if ( ! empty( $roles ) ) {
			$target_user->set_role( $roles[0] );
		}

		restore_current_blog();
	}

	/**
	 * Default role to assign when adding a user to a new subsite.
	 * Falls back to the network's default role setting.
	 *
	 * @return string
	 */
	private function get_default_role(): string {
		return get_option( 'default_role', 'subscriber' );
	}
}
