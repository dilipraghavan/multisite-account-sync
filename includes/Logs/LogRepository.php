<?php
/**
 * LogRepository
 *
 * All database reads and writes for the wp_mcas_sync_log table.
 * No business logic here — pure data access.
 *
 * @package MCAS\Logs
 */

declare( strict_types=1 );

namespace MCAS\Logs;

use MCAS\Sync\SyncResult;

class LogRepository {

	/**
	 * Create the log table for the current blog context if it doesn't exist.
	 * Handles subsites that were created after plugin activation.
	 */
	private function maybe_create_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . MCAS_TABLE_LOG;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			\MCAS\Activator::create_table();
		}
	}

	/**
	 * Insert a single SyncResult into the log table.
	 *
	 * @param SyncResult $result
	 *
	 * @return int|false Inserted row ID or false on failure.
	 */
	public function insert( SyncResult $result ): int|false {
		$this->maybe_create_table();
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . MCAS_TABLE_LOG,
			[
				'user_id'     => $result->user_id,
				'source_blog' => $result->source_blog,
				'target_blog' => $result->target_blog,
				'action'      => $result->action,
				'status'      => $result->status,
				'message'     => $result->message,
				'synced_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Insert multiple SyncResult objects in one go.
	 *
	 * @param SyncResult[] $results
	 *
	 * @return int Number of rows successfully inserted.
	 */
	public function insert_batch( array $results ): int {
		$count = 0;
		foreach ( $results as $result ) {
			if ( $this->insert( $result ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Query log entries with optional filters.
	 *
	 * @param array $args {
	 *   @type int    $user_id      Filter by user ID.
	 *   @type int    $target_blog  Filter by target blog ID.
	 *   @type string $status       Filter by status: 'success'|'failed'|'skipped'.
	 *   @type string $action       Filter by action type.
	 *   @type string $date_from    Filter entries after this date (Y-m-d).
	 *   @type string $date_to      Filter entries before this date (Y-m-d).
	 *   @type int    $per_page     Number of results per page. Default 20.
	 *   @type int    $paged        Page number. Default 1.
	 *   @type string $orderby      Column to order by. Default 'synced_at'.
	 *   @type string $order        ASC or DESC. Default DESC.
	 * }
	 *
	 * @return object[] Array of row objects.
	 */
	public function query( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'user_id'     => 0,
			'target_blog' => 0,
			'status'      => '',
			'action'      => '',
			'date_from'   => '',
			'date_to'     => '',
			'per_page'    => 20,
			'paged'       => 1,
			'orderby'     => 'synced_at',
			'order'       => 'DESC',
		];

		$args    = wp_parse_args( $args, $defaults );
		$table   = $wpdb->prefix . MCAS_TABLE_LOG;
		$where   = [ '1=1' ];
		$prepare = [];

		if ( $args['user_id'] ) {
			$where[]   = 'user_id = %d';
			$prepare[] = (int) $args['user_id'];
		}

		if ( $args['target_blog'] ) {
			$where[]   = 'target_blog = %d';
			$prepare[] = (int) $args['target_blog'];
		}

		if ( $args['status'] ) {
			$where[]   = 'status = %s';
			$prepare[] = $args['status'];
		}

		if ( $args['action'] ) {
			$where[]   = 'action = %s';
			$prepare[] = $args['action'];
		}

		if ( $args['date_from'] ) {
			$where[]   = 'synced_at >= %s';
			$prepare[] = $args['date_from'] . ' 00:00:00';
		}

		if ( $args['date_to'] ) {
			$where[]   = 'synced_at <= %s';
			$prepare[] = $args['date_to'] . ' 23:59:59';
		}

		$allowed_orderby = [ 'id', 'user_id', 'target_blog', 'action', 'status', 'synced_at' ];
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'synced_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * (int) $args['per_page'];
		$per_page = max( 1, (int) $args['per_page'] );

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$prepare[] = $per_page;
		$prepare[] = $offset;

		$sql = $wpdb->prepare( $sql, $prepare );

		return $wpdb->get_results( $sql ) ?: [];
	}

	/**
	 * Count log entries matching the same filters as query().
	 *
	 * @param array $args Same filter args as query(), minus pagination.
	 *
	 * @return int
	 */
	public function count( array $args = [] ): int {
		global $wpdb;

		$table   = $wpdb->prefix . MCAS_TABLE_LOG;
		$where   = [ '1=1' ];
		$prepare = [];

		if ( ! empty( $args['user_id'] ) ) {
			$where[]   = 'user_id = %d';
			$prepare[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['target_blog'] ) ) {
			$where[]   = 'target_blog = %d';
			$prepare[] = (int) $args['target_blog'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]   = 'status = %s';
			$prepare[] = $args['status'];
		}

		if ( ! empty( $args['action'] ) ) {
			$where[]   = 'action = %s';
			$prepare[] = $args['action'];
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

		if ( ! empty( $prepare ) ) {
			$sql = $wpdb->prepare( $sql, $prepare );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Delete log entries older than a given number of days.
	 *
	 * @param int $days
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_older_than( int $days ): int {
		global $wpdb;

		$table = $wpdb->prefix . MCAS_TABLE_LOG;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE synced_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return (int) $wpdb->rows_affected;
	}
}
