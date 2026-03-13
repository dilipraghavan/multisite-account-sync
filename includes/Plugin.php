<?php
/**
 * Core Plugin class.
 *
 * Singleton that initialises all service components.
 * Each phase wires in its own manager/service here.
 *
 * @package MCAS
 */

declare( strict_types=1 );

namespace MCAS;

class Plugin {

	/** @var Plugin|null Singleton instance. */
	private static ?Plugin $instance = null;

	/**
	 * Get or create the singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Initialise all plugin components.
	 *
	 * Components are added here as each build phase is completed:
	 *   Phase 2 → SyncManager
	 *   Phase 3 → Hook integrations
	 *   Phase 4 → LogRepository
	 *   Phase 5 → Admin\NetworkPanel
	 *   Phase 6 → Admin\LogTable
	 *   Phase 7 → Sync\Scheduler
	 */
	private function init(): void {
		$this->load_textdomain();

		// Phase 2 — Sync engine.
		new Sync\SyncManager();

		// Phase 3 — Hook integrations registered here.
		// Phase 4 — LogRepository registered here.
		// Phase 5 — Admin\NetworkPanel registered here.
	}

	/**
	 * Load plugin translations.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'mcas',
			false,
			dirname( plugin_basename( MCAS_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
