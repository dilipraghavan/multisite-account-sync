<?php
/**
 * Network Admin Panel template.
 *
 * Variables available from NetworkPanel::render_page():
 *   $sites         — array of WP_Site objects
 *   $recent_logs   — array of log row objects
 *   $total_logs    — int
 *   $success_count — int
 *   $failed_count  — int
 *   $network_users — array of user objects
 *
 * @package MCAS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap mcas-wrap">

	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-update"></span>
		<?php esc_html_e( 'Multisite Account Sync', 'mcas' ); ?>
	</h1>
	<hr class="wp-header-end">

	<!-- ── Stats Bar ─────────────────────────────────────────── -->
	<div class="mcas-stats">
		<div class="mcas-stat-box">
			<span class="mcas-stat-number"><?php echo esc_html( count( $sites ) ); ?></span>
			<span class="mcas-stat-label"><?php esc_html_e( 'Subsites', 'mcas' ); ?></span>
		</div>
		<div class="mcas-stat-box">
			<span class="mcas-stat-number"><?php echo esc_html( count( $network_users ) ); ?></span>
			<span class="mcas-stat-label"><?php esc_html_e( 'Network Users', 'mcas' ); ?></span>
		</div>
		<div class="mcas-stat-box mcas-stat-success">
			<span class="mcas-stat-number"><?php echo esc_html( $success_count ); ?></span>
			<span class="mcas-stat-label"><?php esc_html_e( 'Successful Syncs', 'mcas' ); ?></span>
		</div>
		<div class="mcas-stat-box mcas-stat-failed">
			<span class="mcas-stat-number"><?php echo esc_html( $failed_count ); ?></span>
			<span class="mcas-stat-label"><?php esc_html_e( 'Failed Syncs', 'mcas' ); ?></span>
		</div>
	</div>

	<div class="mcas-grid">

		<!-- ── Manual Sync ───────────────────────────────────── -->
		<div class="mcas-card">
			<h2><?php esc_html_e( 'Manual Sync', 'mcas' ); ?></h2>

			<form method="post" action="">
				<?php wp_nonce_field( 'mcas_manual_sync', 'mcas_nonce' ); ?>
				<input type="hidden" name="mcas_action" value="sync_all">

				<p><?php esc_html_e( 'Sync all users across all subsites on the network.', 'mcas' ); ?></p>

				<?php submit_button( __( 'Sync All Users', 'mcas' ), 'primary', 'submit', false ); ?>
			</form>

			<hr>

			<form method="post" action="">
				<?php wp_nonce_field( 'mcas_manual_sync', 'mcas_nonce' ); ?>
				<input type="hidden" name="mcas_action" value="sync_user">

				<label for="mcas_user_id"><strong><?php esc_html_e( 'Sync a specific user:', 'mcas' ); ?></strong></label>
				<div class="mcas-inline-form">
					<select name="mcas_user_id" id="mcas_user_id">
						<option value=""><?php esc_html_e( '— Select user —', 'mcas' ); ?></option>
						<?php foreach ( $network_users as $u ) : ?>
							<option value="<?php echo esc_attr( $u->ID ); ?>">
								<?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php submit_button( __( 'Sync User', 'mcas' ), 'secondary', 'submit', false ); ?>
				</div>
			</form>
		</div>

		<!-- ── Network Sites ─────────────────────────────────── -->
		<div class="mcas-card">
			<h2><?php esc_html_e( 'Network Sites', 'mcas' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Site', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Users', 'mcas' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sites as $site ) : ?>
						<?php
						$site_users = get_users( [ 'blog_id' => $site->blog_id, 'fields' => 'ID' ] );
						?>
						<tr>
							<td><?php echo esc_html( $site->blog_id ); ?></td>
							<td>
								<a href="<?php echo esc_url( get_home_url( $site->blog_id ) ); ?>" target="_blank">
									<?php echo esc_html( $site->domain . $site->path ); ?>
								</a>
							</td>
							<td><?php echo esc_html( count( $site_users ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div><!-- .mcas-grid -->

	<!-- ── Recent Sync Log ───────────────────────────────────── -->
	<div class="mcas-card mcas-card-full">
		<h2>
			<?php esc_html_e( 'Recent Sync Activity', 'mcas' ); ?>
			<span class="mcas-total-count">
				<?php printf( esc_html__( '%d total entries', 'mcas' ), $total_logs ); ?>
			</span>
		</h2>

		<?php if ( empty( $recent_logs ) ) : ?>
			<p class="mcas-empty"><?php esc_html_e( 'No sync activity recorded yet. Trigger a sync or update a user profile.', 'mcas' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'User', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Source Blog', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Target Blog', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Action', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Status', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Message', 'mcas' ); ?></th>
						<th><?php esc_html_e( 'Time', 'mcas' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_logs as $log ) :
						$user = get_userdata( $log->user_id );
					?>
						<tr>
							<td><?php echo esc_html( $log->id ); ?></td>
							<td><?php echo $user ? esc_html( $user->user_login ) : esc_html( "User #{$log->user_id}" ); ?></td>
							<td><?php echo esc_html( $log->source_blog ); ?></td>
							<td><?php echo esc_html( $log->target_blog ); ?></td>
							<td><code><?php echo esc_html( $log->action ); ?></code></td>
							<td>
								<span class="mcas-status mcas-status-<?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( $log->status ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log->message ); ?></td>
							<td><?php echo esc_html( $log->synced_at ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

</div><!-- .mcas-wrap -->
