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

// We use local constants so this plugin can be uninstalled even if the autoloader is corrupted or missing.
const PLUGIN_PREFIX = 'onedesign_';

/**
 * Uninstalls the plugin. If multisite, uninstalls from all sites.
 */
function run_uninstaller(): void {
	if ( ! is_multisite() ) {
		uninstall();
		return;
	}

	delete_network_plugin_data();

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) ?: [];

	foreach ( $site_ids as $site_id ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- The state doesn't matter during uninstall.
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

		uninstall();
		restore_current_blog();
	}
}

/**
 * The (site-specific) uninstall function.
 */
function uninstall(): void {
	disconnect_sites();
	delete_options();
}

/**
 * Delete multisite network plugin data.
 */
function delete_network_plugin_data(): void {
	$options = [
		// 1.1.x and earlier.
		PLUGIN_PREFIX . 'multisite_governing_site',
	];

	foreach ( $options as $option ) {
		delete_site_option( $option );
	}
}

/**
 * Deletes options.
 */
function delete_options(): void {
	$options = [
		// Governing site options.
		PLUGIN_PREFIX . 'shared_sites',
		PLUGIN_PREFIX . 'site_type',

		// Brand site options.
		PLUGIN_PREFIX . 'brand_site_patterns',
		PLUGIN_PREFIX . 'brand_site_post_ids',
		PLUGIN_PREFIX . 'consumer_api_key',
		PLUGIN_PREFIX . 'parent_site_url',
		PLUGIN_PREFIX . 'shared_templates',
		PLUGIN_PREFIX . 'template_patterns',

		// 1.1.x and earlier: options the plugin no longer uses.
		PLUGIN_PREFIX . 'child_site_public_key',
		PLUGIN_PREFIX . 'shared_patterns',
		PLUGIN_PREFIX . 'shared_synced_patterns',
		PLUGIN_PREFIX . 'shared_template_parts',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Tells the connected sites this site is going away.
 */
function disconnect_sites(): void {
	// Load required classes.
	if ( ! load_dependencies() ) {
		return;
	}

	if ( \OneDesign\Modules\Settings\Settings::is_governing_site() ) {
		// Governing sites tell their brand sites to forget them. The network's own sites are uninstalled in the same run.
		foreach ( \OneDesign\Modules\Settings\Settings::get_shared_sites() as $site_data ) {
			if ( ! \OneDesign\Modules\Multisite\Utils::is_network_site_url( $site_data['url'] ) ) {
				\OneDesign\Modules\Rest\Remote_Client::disconnect_brand_site( $site_data );
			}
		}

		return;
	}

	// Brand sites deregister from the governing site, while they still have its URL and their key. Only one with a governing site off the network has one to deregister from.
	$governing_site_url = (string) \OneDesign\Modules\Settings\Settings::get_parent_site_url();
	if ( ! \OneDesign\Modules\Settings\Settings::is_consumer_site() || '' === $governing_site_url || \OneDesign\Modules\Multisite\Utils::is_network_site_url( $governing_site_url ) ) {
		return;
	}

	$deregistered = \OneDesign\Modules\Rest\Remote_Client::deregister_from_governing_site();

	if ( is_wp_error( $deregistered ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Nowhere else to report it during uninstall.
		error_log( sprintf( 'OneDesign uninstall: Deregistering from the governing site failed: %s', $deregistered->get_error_message() ) );
	}
}

/**
 * Load required plugin dependencies using the autoloader.
 *
 * @return bool True if dependencies loaded successfully.
 */
function load_dependencies(): bool {
	// Try to find and load the plugin's autoloader.
	$autoloader_path = __DIR__ . '/inc/Autoloader.php';
	if ( ! file_exists( $autoloader_path ) ) {
		return false;
	}

	// The main plugin file isn't loaded during uninstall, but the autoloader needs this.
	if ( ! defined( 'ONEDESIGN_DIR' ) ) {
		define( 'ONEDESIGN_DIR', plugin_dir_path( __FILE__ ) );
	}

	require_once $autoloader_path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

	// If the autoloader succeeded we have what we need.
	return class_exists( '\OneDesign\Autoloader' ) && \OneDesign\Autoloader::autoload();
}

// Run the uninstaller.
run_uninstaller();
