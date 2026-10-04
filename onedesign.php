<?php
/**
 * Plugin Name: OneDesign
 * Description: Sync patterns across multiple WordPress sites and manage them from a single dashboard.
 * Author: rtCamp
 * Author URI: https://rtcamp.com
 * Plugin URI: https://github.com/rtCamp/OneDesign/
 * Update URI: https://github.com/rtCamp/OneDesign/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: onedesign
 * Domain Path: /languages
 * x-release-please-start-version
 * Version: 1.1.3
 * x-release-please-end
 * Requires PHP: 8.0
 * Requires at least: 6.8
 * Tested up to: 6.9
 *
 * @package OneDesign
 */

namespace OneDesign;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit();

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * Version of the plugin.
	 */
	define( 'ONEDESIGN_VERSION', '1.1.3' ); // x-release-please-version.

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONEDESIGN_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONEDESIGN_URL', plugin_dir_url( __FILE__ ) );

	/**
	 * Plugin basename.
	 */
	define( 'ONEDESIGN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

constants();

// If autoloader failed, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneDesign\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( 'OneDesign\Main' ) ) {
	add_action(
		'plugins_loaded',
		static function (): void {
			\OneDesign\Main::instance();

			//phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- @todo remove before submitting to .org.
			load_plugin_textdomain( 'onedesign', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	);
}
