<?php
/**
 * This will be executed when the plugin is uninstalled via the WordPress admin.
 *
 * @package OneDesign
 */

declare( strict_types = 1 );

namespace OneDesign;

// Only uninstall if called by WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The main plugin file isn't loaded during uninstall, but the autoloader needs this.
if ( ! defined( 'ONEDESIGN_DIR' ) ) {
	define( 'ONEDESIGN_DIR', plugin_dir_path( __FILE__ ) );
}

require_once __DIR__ . '/inc/Autoloader.php';

if ( Autoloader::autoload() ) {
	Modules\Core\Uninstaller::uninstall();
}
