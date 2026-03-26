<?php
/**
 * LogTable
 *
 * Extends WP_List_Table to render the sync audit log with:
 *   - Sortable columns
 *   - Filter bar (status, action, target blog)
 *   - Pagination
 *   - Bulk delete action
 *
 * @package MCAS\Admin
 */

declare( strict_types=1 );

namespace MCAS\Admin;

use MCAS\Logs\LogRepository;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LogTable extends \WP_List_Table {

	private LogRepository $repository;

	public function __construct( LogRepository $repository ) {
		parent::__construct( [
			'singular' => 'sync_log',
			'plural'   => 'sync_logs',
			'ajax'     => false,
		] );

		$this->repository = $repository;
	}

	/**
	 * Define all columns.
	 */
	public function get_columns(): array {
		return [
			'cb'          => '<input type="checkbox">',
			'id'          => __( 'ID', 'mcas' ),
			'user'        => __( 'User', 'mcas' ),
			'source_blog' => __( 'Source', 'mcas' ),
			'target_blog' => __( 'Target', 'mcas' ),
			'action'      => __( 'Action', 'mcas' ),
			'status'      => __( 'Status', 'mcas' ),
			'message'     => __( 'Message', 'mcas' ),
			'synced_at'   => __( 'Time', 'mcas' ),
		];
	}

	/**
	 * Define sortable columns.
	 */
	public function get_sortable_columns(): array {
		return [
			'id'          => [ 'id', true ],
			'user'        => [ 'user_id', false ],
			'target_blog' => [ 'target_blog', false ],
			'action'      => [ 'action', false ],
			'status'      => [ 'status', false ],
			'synced_at'   => [ 'synced_at', true ],
		];
	}

	/**
	 * Define bulk actions.
	 */
	public function get_bulk_actions(): array {
		return [
			'delete' => __( 'Delete', 'mcas' ),
		];
	}

	/**
	 * Checkbox column for bulk actions.
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d">', $item->id );
	}

	/**
	 * Default column renderer.
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	/**
	 * ID column with delete row action.
	 */
	public function column_id( $item ): string {
		$delete_url = wp_nonce_url(
			add_query_arg( [
				'page'       => 'mcas-sync-log',
				'mcas_action' => 'delete_log',
				'log_id'     => $item->id,
			], network_admin_url( 'admin.php' ) ),
			'mcas_delete_log_' . $item->id
		);

		$actions = [
			'delete' => sprintf( '<a href="%s" class="mcas-delete-log">%s</a>', esc_url( $delete_url ), __( 'Delete', 'mcas' ) ),
		];

		return sprintf( '%d %s', $item->id, $this->row_actions( $actions ) );
	}

	/**
	 * User column — links to user edit screen.
	 */
	public function column_user( $item ): string {
		$user = get_userdata( $item->user_id );
		if ( ! $user ) {
			return esc_html( "User #{$item->user_id}" );
		}

		$edit_url = network_admin_url( 'user-edit.php?user_id=' . $item->user_id );
		return sprintf(
			'<a href="%s">%s</a><br><small>%s</small>',
			esc_url( $edit_url ),
			esc_html( $user->display_name ),
			esc_html( $user->user_login )
		);
	}

	/**
	 * Source blog column.
	 */
	public function column_source_blog( $item ): string {
		$url = get_home_url( (int) $item->source_blog );
		return sprintf( '<a href="%s" target="_blank">Site %d</a>', esc_url( $url ), $item->source_blog );
	}

	/**
	 * Target blog column.
	 */
	public function column_target_blog( $item ): string {
		$url = get_home_url( (int) $item->target_blog );
		return sprintf( '<a href="%s" target="_blank">Site %d</a>', esc_url( $url ), $item->target_blog );
	}

	/**
	 * Action column — styled as a code badge.
	 */
	public function column_action( $item ): string {
		return sprintf( '<code>%s</code>', esc_html( $item->action ) );
	}

	/**
	 * Status column — coloured badge.
	 */
	public function column_status( $item ): string {
		return sprintf(
			'<span class="mcas-status mcas-status-%s">%s</span>',
			esc_attr( $item->status ),
			esc_html( $item->status )
		);
	}

	/**
	 * Time column — human-readable diff + full timestamp on hover.
	 */
	public function column_synced_at( $item ): string {
		$timestamp = strtotime( $item->synced_at );
		$human     = human_time_diff( $timestamp, time() ) . ' ago';
		return sprintf(
			'<abbr title="%s">%s</abbr>',
			esc_attr( $item->synced_at ),
			esc_html( $human )
		);
	}

	/**
	 * Render the filter bar above the table.
	 *
	 * @param string $which Top or bottom.
	 */
	public function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		$current_status = isset( $_GET['mcas_status'] ) ? sanitize_text_field( $_GET['mcas_status'] ) : '';
		$current_action = isset( $_GET['mcas_action_filter'] ) ? sanitize_text_field( $_GET['mcas_action_filter'] ) : '';
		$current_blog   = isset( $_GET['mcas_blog'] ) ? (int) $_GET['mcas_blog'] : 0;

		$sites = get_sites( [ 'number' => 0 ] );
		?>
		<div class="alignleft actions mcas-filters">
			<select name="mcas_status">
				<option value=""><?php esc_html_e( 'All Statuses', 'mcas' ); ?></option>
				<option value="success" <?php selected( $current_status, 'success' ); ?>><?php esc_html_e( 'Success', 'mcas' ); ?></option>
				<option value="failed"  <?php selected( $current_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'mcas' ); ?></option>
				<option value="skipped" <?php selected( $current_status, 'skipped' ); ?>><?php esc_html_e( 'Skipped', 'mcas' ); ?></option>
			</select>

			<select name="mcas_action_filter">
				<option value=""><?php esc_html_e( 'All Actions', 'mcas' ); ?></option>
				<option value="profile_update" <?php selected( $current_action, 'profile_update' ); ?>><?php esc_html_e( 'Profile Update', 'mcas' ); ?></option>
				<option value="role_change"    <?php selected( $current_action, 'role_change' ); ?>><?php esc_html_e( 'Role Change', 'mcas' ); ?></option>
				<option value="user_register"  <?php selected( $current_action, 'user_register' ); ?>><?php esc_html_e( 'User Register', 'mcas' ); ?></option>
				<option value="manual_sync"    <?php selected( $current_action, 'manual_sync' ); ?>><?php esc_html_e( 'Manual Sync', 'mcas' ); ?></option>
			</select>

			<select name="mcas_blog">
				<option value="0"><?php esc_html_e( 'All Sites', 'mcas' ); ?></option>
				<?php foreach ( $sites as $site ) : ?>
					<option value="<?php echo esc_attr( $site->blog_id ); ?>" <?php selected( $current_blog, $site->blog_id ); ?>>
						<?php echo esc_html( $site->domain . $site->path ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Filter', 'mcas' ), 'button', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Message shown when the table has no rows.
	 */
	public function no_items(): void {
		esc_html_e( 'No sync log entries found.', 'mcas' );
	}

	/**
	 * Build query args from current request and prepare table data.
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

		$args = [
			'per_page'    => $per_page,
			'paged'       => $paged,
			'orderby'     => isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'synced_at',
			'order'       => isset( $_GET['order'] ) && strtoupper( $_GET['order'] ) === 'ASC' ? 'ASC' : 'DESC',
			'status'      => isset( $_GET['mcas_status'] ) ? sanitize_text_field( $_GET['mcas_status'] ) : '',
			'action'      => isset( $_GET['mcas_action_filter'] ) ? sanitize_text_field( $_GET['mcas_action_filter'] ) : '',
			'target_blog' => isset( $_GET['mcas_blog'] ) ? (int) $_GET['mcas_blog'] : 0,
		];

		$this->items = $this->repository->query( $args );
		$total       = $this->repository->count( $args );

		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}
}
