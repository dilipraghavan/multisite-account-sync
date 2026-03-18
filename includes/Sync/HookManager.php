<?php
/**
 * HookManager
 *
 * Registers all WordPress action hooks that trigger user sync.
 * Keeps hook registration cleanly separated from sync logic.
 *
 * Events handled:
 *   profile_update   — user saves their profile or admin edits a user
 *   set_user_role    — user role is changed on any subsite
 *   user_register    — new user registered on the network
 *   delete_user      — user deleted (removes from all subsites)
 *
 * @package MCAS\Sync
 */

declare( strict_types=1 );

namespace MCAS\Sync;

class HookManager {

	private SyncManager $sync_manager;

	public function __construct( SyncManager $sync_manager ) {
		$this->sync_manager = $sync_manager;
		$this->register_hooks();
	}

	/**
	 * Register all user lifecycle hooks.
	 */
	private function register_hooks(): void {
		// Profile updated — fires on wp-admin/profile.php and wp-admin/user-edit.php.
		add_action( 'profile_update', [ $this, 'on_profile_update' ], 10, 2 );

		// Role changed on any subsite.
		add_action( 'set_user_role', [ $this, 'on_set_user_role' ], 10, 3 );

		// New user registered on the network.
		add_action( 'user_register', [ $this, 'on_user_register' ], 10, 1 );

		// User deleted from the network.
		add_action( 'delete_user', [ $this, 'on_delete_user' ], 10, 1 );

		// Also handle wpmu_delete_user for network-level deletion.
		add_action( 'wpmu_delete_user', [ $this, 'on_delete_user' ], 10, 1 );
	}

	/**
	 * Fires when a user's profile is updated.
	 *
	 * @param int      $user_id       The user ID.
	 * @param \WP_User $old_user_data The user object before the update.
	 */
	public function on_profile_update( int $user_id, \WP_User $old_user_data ): void {
		$this->sync_manager->sync_user(
			$user_id,
			'profile_update',
			get_current_blog_id()
		);
	}

	/**
	 * Fires when a user's role is changed on a subsite.
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles The previous roles.
	 */
	public function on_set_user_role( int $user_id, string $role, array $old_roles ): void {
		// Skip if role hasn't actually changed.
		if ( in_array( $role, $old_roles, true ) ) {
			return;
		}

		$this->sync_manager->sync_user(
			$user_id,
			'role_change',
			get_current_blog_id()
		);
	}

	/**
	 * Fires when a new user is registered.
	 *
	 * @param int $user_id The new user's ID.
	 */
	public function on_user_register( int $user_id ): void {
		$this->sync_manager->sync_user(
			$user_id,
			'user_register',
			get_current_blog_id()
		);
	}

	/**
	 * Fires when a user is deleted.
	 *
	 * Removes the user from all subsites before WordPress
	 * completes the deletion from the network.
	 *
	 * @param int $user_id The deleted user's ID.
	 */
	public function on_delete_user( int $user_id ): void {
		$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );

		foreach ( $sites as $blog_id ) {
			switch_to_blog( (int) $blog_id );

			if ( is_user_member_of_blog( $user_id, (int) $blog_id ) ) {
				remove_user_from_blog( $user_id, (int) $blog_id );
			}

			restore_current_blog();
		}
	}
}
