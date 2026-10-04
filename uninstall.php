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
const PLUGIN_PREFIX = PLUGIN_PREFIX . '';

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
	delete_posts();

	delete_options();
}

/**
 * Delete multisite network plugin data.
 */
function delete_network_plugin_data(): void {
	$options = [
		PLUGIN_PREFIX . 'multisite_governing_site',
	];

	foreach ( $options as $option ) {
		delete_site_option( $option );
	}
}

/**
 * Delete posts from brand sites.
 */
function delete_posts(): void {
	$brand_site_post_ids = (array) get_option( PLUGIN_PREFIX . 'brand_site_post_ids', [] );
	foreach ( $brand_site_post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}

/**
 * Deletes options.
 */
function delete_options(): void {
	$options = [
		PLUGIN_PREFIX . 'site_type',
		PLUGIN_PREFIX . 'consumer_api_key',
		PLUGIN_PREFIX . 'parent_site_url',
		PLUGIN_PREFIX . 'shared_sites',

		PLUGIN_PREFIX . 'brand_site_patterns',
		PLUGIN_PREFIX . 'child_site_public_key',
		PLUGIN_PREFIX . 'shared_templates',
		PLUGIN_PREFIX . 'brand_site_post_ids',
		PLUGIN_PREFIX . 'shared_patterns',
		PLUGIN_PREFIX . 'shared_template_parts',
		PLUGIN_PREFIX . 'shared_synced_patterns',
		PLUGIN_PREFIX . 'multisite_governing_site',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

// Run the uninstaller.
run_uninstaller();
