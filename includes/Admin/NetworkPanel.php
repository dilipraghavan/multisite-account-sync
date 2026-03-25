<?php
/**
 * NetworkPanel
 *
 * Registers the Network Admin menu page and handles:
 *   - Sync status overview (recent activity per user/subsite)
 *   - Manual sync trigger for a single user or full network sweep
 *   - Admin notices for sync results
 *
 * @package MCAS\Admin
 */

declare( strict_types=1 );

namespace MCAS\Admin;

use MCAS\Logs\LogRepository;
use MCAS\Sync\SyncManager;

class NetworkPanel {

	private LogRepository $log_repository;
	private SyncManager   $sync_manager;

	public function __construct( LogRepository $log_repository, SyncManager $sync_manager ) {
		$this->log_repository = $log_repository;
		$this->sync_manager   = $sync_manager;
		$this->register_hooks();
	}

	/**
	 * Register hooks for the network admin UI.
	 */
	private function register_hooks(): void {
		add_action( 'network_admin_menu', [ $this, 'register_menu' ] );
		add_action( 'network_admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the Network Admin menu page.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Account Sync', 'mcas' ),
			__( 'Account Sync', 'mcas' ),
			'manage_network',
			'mcas-network-panel',
			[ $this, 'render_page' ],
			'dashicons-update',
			30
		);
	}

	/**
	 * Enqueue admin styles on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'mcas-network-panel' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'mcas-admin',
			MCAS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			MCAS_VERSION
		);
	}

	/**
	 * Display admin notices after a manual sync action.
	 */
	public function admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'mcas-network-panel' ) === false ) {
			return;
		}

		if ( isset( $_GET['mcas_synced'] ) ) {
			$count = (int) $_GET['mcas_synced'];
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				esc_html( _n( '%d user synced successfully.', '%d users synced successfully.', $count, 'mcas' ) ),
				$count
			);
			echo '</p></div>';
		}

		if ( isset( $_GET['mcas_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			esc_html_e( 'Sync encountered errors. Check the log below for details.', 'mcas' );
			echo '</p></div>';
		}
	}

	/**
	 * Handle manual sync form submissions and render the page.
	 */
	public function render_page(): void {
		// Handle form submissions before any output.
		$this->handle_actions();

		$sites        = get_sites( [ 'number' => 0 ] );
		$recent_logs  = $this->log_repository->query( [ 'per_page' => 10, 'orderby' => 'synced_at', 'order' => 'DESC' ] );
		$total_logs   = $this->log_repository->count();
		$success_count = $this->log_repository->count( [ 'status' => 'success' ] );
		$failed_count  = $this->log_repository->count( [ 'status' => 'failed' ] );
		$network_users = get_users( [ 'number' => -1, 'fields' => [ 'ID', 'user_login', 'display_name' ] ] );

		include MCAS_PLUGIN_DIR . 'templates/admin/network-panel.php';
	}

	/**
	 * Process manual sync form submissions.
	 */
	private function handle_actions(): void {
		if ( ! isset( $_POST['mcas_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'mcas_manual_sync', 'mcas_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'mcas' ) );
		}

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mcas' ) );
		}

		$action = sanitize_text_field( $_POST['mcas_action'] );

		if ( $action === 'sync_user' && ! empty( $_POST['mcas_user_id'] ) ) {
			$user_id = (int) $_POST['mcas_user_id'];
			$results = $this->sync_manager->sync_user( $user_id, 'manual_sync' );
			$synced  = count( array_filter( $results, fn( $r ) => $r->is_success() ) );

			wp_safe_redirect( add_query_arg( [ 'mcas_synced' => $synced ], $this->page_url() ) );
			exit;
		}

		if ( $action === 'sync_all' ) {
			$results = $this->sync_manager->sync_all_users( 'manual_sync' );
			$synced  = count( array_filter( $results, fn( $r ) => $r->is_success() ) );

			wp_safe_redirect( add_query_arg( [ 'mcas_synced' => $synced ], $this->page_url() ) );
			exit;
		}
	}

	/**
	 * Get the URL for this admin page.
	 */
	private function page_url(): string {
		return network_admin_url( 'admin.php?page=mcas-network-panel' );
	}
}
