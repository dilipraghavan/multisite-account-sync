<?php
/**
 * Plugin Name:       Multisite Central Account Sync
 * Plugin URI:        https://github.com/dilip/multisite-account-sync
 * Description:       Synchronizes user accounts, roles, and capabilities across all WordPress multisite network subsites with audit logging and a central admin panel.
 * Version:           1.0.0
 * Author:            Dilip
 * Author URI:        https://yourportfolio.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcas
 * Domain Path:       /languages
 * Network:           true
 *
 * @package MCAS
 */

declare( strict_types=1 );

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require network activation — plugin must be network-activated to function.
if ( ! is_multisite() ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Multisite Central Account Sync</strong> requires WordPress Multisite to be enabled.</p></div>';
	} );
	return;
}

// Plugin constants.
define( 'MCAS_VERSION',     '1.0.0' );
define( 'MCAS_PLUGIN_FILE', __FILE__ );
define( 'MCAS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MCAS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'MCAS_TABLE_LOG',   'mcas_sync_log' );

// Autoloader.
require_once MCAS_PLUGIN_DIR . 'includes/Autoloader.php';
MCAS\Autoloader::register();

// Activation / deactivation hooks.
register_activation_hook( __FILE__,   [ 'MCAS\\Activator',   'activate'   ] );
register_deactivation_hook( __FILE__, [ 'MCAS\\Deactivator', 'deactivate' ] );

// Boot the plugin.
add_action( 'plugins_loaded', [ 'MCAS\\Plugin', 'get_instance' ] );
