<?php
/**
 * SyncResult value object.
 *
 * Captures the outcome of a single user sync operation
 * against one target subsite. Passed to the log and hooks.
 *
 * @package MCAS\Sync
 */

declare( strict_types=1 );

namespace MCAS\Sync;

class SyncResult {

	public const STATUS_SUCCESS = 'success';
	public const STATUS_FAILED  = 'failed';
	public const STATUS_SKIPPED = 'skipped';

	public int    $user_id;
	public int    $source_blog;
	public int    $target_blog;
	public string $action;
	public string $status;
	public string $message;

	public function __construct(
		int    $user_id,
		int    $source_blog,
		int    $target_blog,
		string $action,
		string $status  = self::STATUS_SUCCESS,
		string $message = ''
	) {
		$this->user_id     = $user_id;
		$this->source_blog = $source_blog;
		$this->target_blog = $target_blog;
		$this->action      = $action;
		$this->status      = $status;
		$this->message     = $message;
	}

	public function is_success(): bool {
		return $this->status === self::STATUS_SUCCESS;
	}

	public function is_failed(): bool {
		return $this->status === self::STATUS_FAILED;
	}
}
