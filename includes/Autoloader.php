<?php
/**
 * Autoloader for the MCAS namespace.
 *
 * Maps MCAS\ClassName  → includes/ClassName.php
 *     MCAS\Admin\Foo   → includes/Admin/Foo.php
 *     MCAS\Sync\Bar    → includes/Sync/Bar.php
 *
 * @package MCAS
 */

declare( strict_types=1 );

namespace MCAS;

class Autoloader {

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Load a class file based on its fully-qualified class name.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		// Only handle classes in the MCAS namespace.
		if ( strpos( $class, 'MCAS\\' ) !== 0 ) {
			return;
		}

		// Strip leading namespace prefix.
		$relative = substr( $class, strlen( 'MCAS\\' ) );

		// Convert namespace separators to directory separators.
		$file = MCAS_PLUGIN_DIR . 'includes/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
