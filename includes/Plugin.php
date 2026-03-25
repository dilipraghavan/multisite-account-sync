<?php
/**
 * Core Plugin class.
 *
 * Singleton that initialises all service components.
 *
 * @package MCAS
 */

declare( strict_types=1 );

namespace MCAS;

class Plugin {

	/** @var Plugin|null Singleton instance. */
	private static ?Plugin $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Initialise all plugin components.
	 *
	 *   Phase 2 → SyncManager
	 *   Phase 3 → HookManager
	 *   Phase 4 → LogRepository + SyncLogger
	 *   Phase 5 → Admin\NetworkPanel
	 *   Phase 6 → Admin\LogTable
	 *   Phase 7 → Sync\Scheduler
	 */
	private function init(): void {
		$this->load_textdomain();

		// Phase 2 — Sync engine.
		$sync_manager = new Sync\SyncManager();

		// Phase 3 — Hook integrations.
		new Sync\HookManager( $sync_manager );

		// Phase 4 — Audit logging.
		$log_repository = new Logs\LogRepository();
		new Logs\SyncLogger( $log_repository );

		// Phase 5 — Network Admin Panel.
		new Admin\NetworkPanel( $log_repository, $sync_manager );
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'mcas',
			false,
			dirname( plugin_basename( MCAS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
