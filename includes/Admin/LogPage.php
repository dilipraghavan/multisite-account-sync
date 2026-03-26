<?php
/**
 * LogPage
 *
 * Registers the Sync Log submenu page under Account Sync
 * and handles rendering + bulk/single delete actions.
 *
 * @package MCAS\Admin
 */

declare( strict_types=1 );

namespace MCAS\Admin;

use MCAS\Logs\LogRepository;

class LogPage {

	private LogRepository $repository;

	public function __construct( LogRepository $repository ) {
		$this->repository = $repository;
		$this->register_hooks();
	}

	private function register_hooks(): void {
		add_action( 'network_admin_menu', [ $this, 'register_menu' ] );
	}

	/**
	 * Register as a submenu under the main Account Sync page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'mcas-network-panel',
			__( 'Sync Log', 'mcas' ),
			__( 'Sync Log', 'mcas' ),
			'manage_network',
			'mcas-sync-log',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Handle actions and render the log page.
	 */
	public function render_page(): void {
		$this->handle_actions();

		$table = new LogTable( $this->repository );
		$table->prepare_items();
		?>
		<div class="wrap mcas-wrap">
			<h1><?php esc_html_e( 'Sync Log', 'mcas' ); ?></h1>

			<?php if ( isset( $_GET['mcas_deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php printf( esc_html__( '%d log entries deleted.', 'mcas' ), (int) $_GET['mcas_deleted'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="mcas-sync-log">
				<?php
				$table->search_box( __( 'Search', 'mcas' ), 'mcas_search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle single and bulk delete actions.
	 */
	private function handle_actions(): void {
		global $wpdb;

		// Single delete.
		if (
			isset( $_GET['mcas_action'], $_GET['log_id'] ) &&
			$_GET['mcas_action'] === 'delete_log'
		) {
			$log_id = (int) $_GET['log_id'];
			check_admin_referer( 'mcas_delete_log_' . $log_id );

			$wpdb->delete(
				$wpdb->prefix . MCAS_TABLE_LOG,
				[ 'id' => $log_id ],
				[ '%d' ]
			);

			wp_safe_redirect( add_query_arg( [ 'page' => 'mcas-sync-log', 'mcas_deleted' => 1 ], network_admin_url( 'admin.php' ) ) );
			exit;
		}

		// Bulk delete.
		if (
			isset( $_POST['action'], $_POST['log_ids'] ) &&
			$_POST['action'] === 'delete' &&
			! empty( $_POST['log_ids'] )
		) {
			check_admin_referer( 'bulk-sync_logs' );

			$ids     = array_map( 'intval', (array) $_POST['log_ids'] );
			$deleted = 0;

			foreach ( $ids as $id ) {
				$result = $wpdb->delete(
					$wpdb->prefix . MCAS_TABLE_LOG,
					[ 'id' => $id ],
					[ '%d' ]
				);
				if ( $result ) {
					$deleted++;
				}
			}

			wp_safe_redirect( add_query_arg( [ 'page' => 'mcas-sync-log', 'mcas_deleted' => $deleted ], network_admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}
