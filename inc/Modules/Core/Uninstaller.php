<?php
/**
 * Deletes the plugin's data when it's uninstalled, or deactivated if the site opted in.
 *
 * @package OneDesign\Modules\Core
 */

declare( strict_types = 1 );

namespace OneDesign\Modules\Core;

use OneDesign\Modules\Multisite\Utils as MU_Utils;
use OneDesign\Modules\Rest\Remote_Client;
use OneDesign\Modules\Settings\Settings;

/**
 * Class - Uninstaller
 */
final class Uninstaller {
	/**
	 * The prefix of the plugin's options.
	 */
	private const PLUGIN_PREFIX = 'onedesign_';

	/**
	 * Uninstalls the plugin. If multisite, uninstalls from all sites.
	 */
	public static function uninstall(): void {
		if ( is_multisite() ) {
			self::delete_network_plugin_data();
		}

		// The network's own sites are uninstalled in the same run.
		self::on_each_site( static fn () => self::uninstall_site( true ) );
	}

	/**
	 * Uninstalls the plugin on deactivation from the sites that opted in, since hosts like WordPress VIP never uninstall plugins.
	 *
	 * @internal Hook callback
	 *
	 * @param bool $network_wide Whether the plugin is being deactivated for the whole network.
	 */
	public static function deactivate( $network_wide ): void {
		$maybe_uninstall_site = static function () use ( $network_wide ): void {
			if ( $network_wide && in_array( plugin_basename( ONEDESIGN_FILE ), (array) get_option( 'active_plugins', [] ), true ) ) {
				return;
			}

			if ( Settings::should_delete_data_on_deactivation() ) {
				self::uninstall_site( false );
			}
		};

		// Deactivating for the network leaves the plugin active on the other networks.
		if ( $network_wide ) {
			self::on_each_site( $maybe_uninstall_site, get_current_network_id() );
			return;
		}

		$maybe_uninstall_site();
	}

	/**
	 * Runs a callback on each site. If not multisite, runs it on the current site.
	 *
	 * @param callable $callback   The callback to run.
	 * @param int      $network_id Only runs it on this network's sites. Default 0, every network.
	 */
	private static function on_each_site( callable $callback, int $network_id = 0 ): void {
		if ( ! is_multisite() ) {
			$callback();
			return;
		}

		$site_ids = get_sites(
			[
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => $network_id,
			]
		) ?: [];

		foreach ( $site_ids as $site_id ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- The state doesn't matter while deleting the plugin's data.
			if ( ! switch_to_blog( (int) $site_id ) ) {
				continue;
			}

			$callback();
			restore_current_blog();
		}
	}

	/**
	 * The (site-specific) uninstall function.
	 *
	 * @param bool $skip_network_sites Whether to skip the sites on the network.
	 */
	private static function uninstall_site( bool $skip_network_sites ): void {
		self::disconnect_sites( $skip_network_sites );
		self::delete_options();
	}

	/**
	 * Delete multisite network plugin data.
	 */
	private static function delete_network_plugin_data(): void {
		$options = [
			// 1.1.x and earlier.
			self::PLUGIN_PREFIX . 'multisite_governing_site',
		];

		foreach ( $options as $option ) {
			delete_site_option( $option );
		}
	}

	/**
	 * Deletes options.
	 */
	private static function delete_options(): void {
		$options = [
			// Shared options.
			self::PLUGIN_PREFIX . 'delete_data_on_deactivation',
			self::PLUGIN_PREFIX . 'site_type',

			// Governing site options.
			self::PLUGIN_PREFIX . 'shared_sites',

			// Brand site options.
			self::PLUGIN_PREFIX . 'brand_site_patterns',
			self::PLUGIN_PREFIX . 'brand_site_post_ids',
			self::PLUGIN_PREFIX . 'consumer_api_key',
			self::PLUGIN_PREFIX . 'parent_site_url',
			self::PLUGIN_PREFIX . 'shared_templates',
			self::PLUGIN_PREFIX . 'template_patterns',

			// 1.1.x and earlier: options the plugin no longer uses.
			self::PLUGIN_PREFIX . 'child_site_public_key',
			self::PLUGIN_PREFIX . 'shared_patterns',
			self::PLUGIN_PREFIX . 'shared_synced_patterns',
			self::PLUGIN_PREFIX . 'shared_template_parts',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Tells the connected sites this site is going away.
	 *
	 * @param bool $skip_network_sites Whether to skip the sites on the network.
	 */
	private static function disconnect_sites( bool $skip_network_sites ): void {
		if ( Settings::is_governing_site() ) {
			// Governing sites tell their brand sites to forget them.
			foreach ( Settings::get_shared_sites() as $site_data ) {
				if ( ! $skip_network_sites || ! MU_Utils::is_network_site_url( $site_data['url'] ) ) {
					Remote_Client::disconnect_brand_site( $site_data );
				}
			}

			return;
		}

		// Brand sites deregister from the governing site, while they still have its URL and their key.
		$governing_site_url = (string) Settings::get_parent_site_url();
		if ( ! Settings::is_consumer_site() || '' === $governing_site_url || ( $skip_network_sites && MU_Utils::is_network_site_url( $governing_site_url ) ) ) {
			return;
		}

		$deregistered = Remote_Client::deregister_from_governing_site();

		if ( is_wp_error( $deregistered ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Nowhere else to report it while deleting the plugin's data.
			error_log( sprintf( 'OneDesign: Deregistering from the governing site failed: %s', $deregistered->get_error_message() ) );
		}
	}
}
