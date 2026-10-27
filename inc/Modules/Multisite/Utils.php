<?php
/**
 * Helpers for setting up the sites on a network.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Multisite;

use OneDesign\Modules\Settings\Admin as Settings_Admin;
use OneDesign\Modules\Settings\Settings as AdminSettings;
use OneDesign\Utils as Plugin_Utils;

/**
 * Class Utils
 */
final class Utils {
	/**
	 * Whether OneDesign is network-activated, so network admins can set up the network's sites.
	 */
	public static function is_network_activated(): bool {
		return is_multisite() && array_key_exists( plugin_basename( ONEDESIGN_FILE ), (array) get_site_option( 'active_sitewide_plugins', [] ) );
	}

	/**
	 * Get information of the network's sites.
	 *
	 * @return array{
	 *   id: string,
	 *   name: string,
	 *   url: string,
	 *   siteType: string,
	 *   canAddAsBrandSite: bool,
	 *   manageUrl: string
	 * }[]
	 */
	public static function get_all_multisites_info(): array {
		$sites      = get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		);
		$sites_info = [];

		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only site options are read while switched.

			$sites_info[] = [
				'id'                => (string) $site_id,
				'name'              => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'               => Plugin_Utils::get_current_site_url(),
				'siteType'          => (string) AdminSettings::get_site_type(),
				'canAddAsBrandSite' => self::can_add_as_brand_site(),
				'manageUrl'         => admin_url( 'admin.php?page=' . Settings_Admin::SCREEN_ID ),
			];

			restore_current_blog();
		}

		return $sites_info;
	}

	/**
	 * Whether any site on the network has a OneDesign type.
	 */
	public static function has_site_with_type(): bool {
		foreach ( get_sites(
			[
				'fields' => 'ids',
				'number' => 0,
			]
		) as $site_id ) {
			if ( get_blog_option( (int) $site_id, AdminSettings::OPTION_SITE_TYPE ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current site can be added as a brand site, which it can while it has no type or is a brand site with no governing site.
	 */
	public static function can_add_as_brand_site(): bool {
		return ! AdminSettings::get_site_type() || ( AdminSettings::is_consumer_site() && ! AdminSettings::get_parent_site_url() );
	}

	/**
	 * Makes a site on the network one of a governing site's brand sites.
	 *
	 * @param int $governing_site_id The governing site's blog id.
	 * @param int $site_id           The site's blog id.
	 */
	public static function add_brand_site( int $governing_site_id, int $site_id ): void {
		switch_to_blog( $governing_site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only site options are read/written while switched.

		$governing_site_url = Plugin_Utils::get_current_site_url();

		switch_to_blog( $site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only site options are read/written while switched.

		update_option( AdminSettings::OPTION_SITE_TYPE, AdminSettings::SITE_TYPE_CONSUMER, false );
		AdminSettings::set_parent_site_url( $governing_site_url );

		$logo_id    = (int) get_option( 'site_icon', 0 );
		$brand_site = [
			'id'      => (string) $site_id,
			'name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'url'     => Plugin_Utils::get_current_site_url(),
			'logo'    => (string) wp_get_attachment_url( $logo_id ),
			'logo_id' => $logo_id,
			'api_key' => AdminSettings::get_api_key(),
		];

		restore_current_blog();

		$shared_sites                       = AdminSettings::get_shared_sites();
		$shared_sites[ $brand_site['url'] ] = $brand_site;
		AdminSettings::set_shared_sites( $shared_sites );

		restore_current_blog();
	}

	/**
	 * Whether a URL is the home URL of a site on this network.
	 *
	 * @param string $url The URL.
	 */
	public static function is_network_site_url( string $url ): bool {
		$network_site_urls = array_map(
			static fn ( \WP_Site $site ): string => Plugin_Utils::normalize_url( (string) $site->home ),
			is_multisite() ? get_sites( [ 'number' => 0 ] ) : []
		);

		return in_array( Plugin_Utils::normalize_url( $url ), $network_site_urls, true );
	}
}
