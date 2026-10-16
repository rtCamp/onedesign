<?php
/**
 * OneDesign
 *
 * @package           OneDesign
 * @author            rtCamp
 * @copyright         2025 rtCamp
 * @license           GPL-2.0-or-later
 *
 * Plugin Name:       OneDesign
 * Plugin URI:        https://github.com/rtCamp/onedesign
 * Description:       Sync patterns across multiple WordPress sites and manage them from a single dashboard.
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       onedesign
 * Domain Path:       /languages
 * x-release-please-start-version
 * Version:           2.0.0
 * x-release-please-end
 * Requires PHP:      8.2
 * Requires at least: 6.8
 * Tested up to:      6.9
 */

declare( strict_types = 1 );

namespace OneDesign;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * File path to the plugin's main file.
	 */
	define( 'ONEDESIGN_FILE', __FILE__ );

	/**
	 * Version of the plugin.
	 */
	define( 'ONEDESIGN_VERSION', '2.0.0' ); // x-release-please-version.

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONEDESIGN_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONEDESIGN_URL', plugin_dir_url( __FILE__ ) );

	/**
	 * The plugin basename.
	 */
	define( 'ONEDESIGN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

constants();

// If autoloader fails, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneDesign\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( 'OneDesign\Main' ) ) {
	\OneDesign\Main::instance();
}
