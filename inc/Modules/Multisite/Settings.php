<?php

declare(strict_types = 1);

/**
 * Multisite-specific settings and utilities.
 *
 * @package OneDesign
 */

namespace OneDesign\Modules\Multisite;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Settings\Settings as AdminSettings;

/**
 * Class Settings
 */
class Settings implements Registrable {
	/**
	 * Multisite governing site id
	 *
	 * @var string
	 */
	public const OPTION_MULTISITE_GOVERNING_SITE = 'onedesign_multisite_governing_site';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// check if current site setup is multisite or not.
		if ( ! is_multisite() ) {
			return;
		}

		// add onedesign_multisite_api_key_generated action to change same key in governing site.
		add_action( 'onedesign_regenerate_api_key', [ $this, 'sync_api_key_to_governing_site' ], 10, 2 );

		// auto assign brand-site on new site creation if governing site is set.
		add_action( 'wp_initialize_site', [ $this, 'assign_brand_site_on_new_site_creation' ], 10 );

		// listen to option changes for blogname, siteurl and home to update into governing site table.
		add_action( 'updated_option', [ $this, 'update_site_details_in_governing_site_table' ], 10, 3 );
	}

	/**
	 * Get the governing site for multisite setup.
	 *
	 * @return ?int Governing site ID, or 0 if not set. null if not multisite.
	 */
	public static function get_multisite_governing_site_id(): ?int {
		if ( ! is_multisite() ) {
			return null;
		}

		$governing_site_id = get_site_option( self::OPTION_MULTISITE_GOVERNING_SITE, 0 );
		return is_numeric( $governing_site_id ) ? (int) $governing_site_id : 0;
	}

	/**
	 * Set the governing site ID for multisite.
	 *
	 * @param int $site_id The site ID to set as governing site.
	 */
	public static function set_multisite_governing_site_id( int $site_id ): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		return update_site_option( self::OPTION_MULTISITE_GOVERNING_SITE, $site_id );
	}

	/**
	 * Check if governing site is selected in multisite setup.
	 *
	 * @return bool True if governing site is selected, false otherwise.
	 */
	public static function is_governing_site_selected(): bool {
		$governing_site_id = self::get_multisite_governing_site_id();
		return ( (int) $governing_site_id ) > 0;
	}

	/**
	 * Get information of all multisites in the network.
	 *
	 * @return array Array of multisite information.
	 */
	public static function get_all_multisites_info(): array {
		if ( ! is_multisite() ) {
			return [];
		}

		$sites      = get_sites( [ 'number' => 0 ] );
		$sites_info = [];

		foreach ( $sites as $site ) {
			$site_details = get_blog_details( $site->blog_id );
			if ( ! $site_details ) {
				continue;
			}

			$sites_info[] = [
				'id'   => (string) $site_details->blog_id,
				'name' => $site_details->blogname,
				'url'  => $site_details->siteurl,
			];
		}

		return $sites_info;
	}

	/**
	 * Sync API key to governing site when a new key is generated in any child site.
	 *
	 * @param string $secret_key The generated secret key.
	 * @param int    $blog_id The blog ID where the key is generated.
	 */
	public function sync_api_key_to_governing_site( string $secret_key, int $blog_id ): void {
		// get the governing site id.
		$governing_site_id = self::get_multisite_governing_site_id();

		// go to governing site and update shared_sites option secret_key of blog_id site.
		if ( ! $governing_site_id || ! $secret_key ) {
			return;
		}

		if ( ! switch_to_blog( (int) $governing_site_id ) ) {
			return;
		}
		$shared_sites = AdminSettings::get_shared_sites();
		foreach ( $shared_sites as &$site ) {
			if ( (int) $site['id'] === (int) $blog_id ) {
				$site['api_key'] = $secret_key;
				break;
			}
		}

		AdminSettings::set_shared_sites( $shared_sites );

		restore_current_blog();
	}

	/**
	 * Assign brand-site on new site creation if governing site is set.
	 *
	 * @param \WP_Site $new_site The new site object.
	 */
	public function assign_brand_site_on_new_site_creation( \WP_Site $new_site ): void {

		$governing_site_id = self::get_multisite_governing_site_id();

		if ( ! $governing_site_id || $new_site->blog_id === $governing_site_id ) {
			return;
		}

		if ( ! switch_to_blog( (int) $new_site->blog_id ) ) {
			return;
		}

		update_option( AdminSettings::OPTION_SITE_TYPE, AdminSettings::SITE_TYPE_CONSUMER, false );

		restore_current_blog();
	}

	/**
	 * Update site details in governing site table on option changes.
	 *
	 * @param string $option_name The name of the updated option.
	 * @param mixed  $old_value The old value of the option.
	 * @param mixed  $new_value The new value of the option.
	 */
	public function update_site_details_in_governing_site_table( string $option_name, $old_value, $new_value ): void {

		$governing_site_id = self::get_multisite_governing_site_id();

		// If governing site is not set or we are on governing site, return.
		if ( ! $governing_site_id || get_current_blog_id() === (int) $governing_site_id ) {
			return;
		}

		$relevant_options = [ 'blogname', 'siteurl', 'home', 'site_icon' ];

		$current_site_id = get_current_blog_id();

		// For site_icon: Fetch details in CHILD site context BEFORE switching.
		$logo_url = '';
		$logo_id  = 0;
		if ( ! in_array( $option_name, $relevant_options, true ) ) {
			return;
		}

		if ( 'site_icon' === $option_name ) {
			if ( ! empty( $new_value ) && is_numeric( $new_value ) && (int) $new_value > 0 ) {
				$attachment = get_post( (int) $new_value );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$logo_url = wp_get_attachment_url( (int) $new_value );
					$logo_id  = (int) $new_value;
				}
			}
		}

		// Now switch to governing site.
		if ( ! switch_to_blog( (int) $governing_site_id ) ) {
			return;
		}

		// Get shared sites from governing site.
		$shared_sites = AdminSettings::get_shared_sites();

		foreach ( $shared_sites as &$site ) {
			if ( (int) $site['id'] !== $current_site_id ) {
				continue;
			}

			if ( 'blogname' === $option_name ) {
				$site['name'] = sanitize_text_field( $new_value );
			} elseif ( in_array( $option_name, [ 'siteurl', 'home' ], true ) ) {
				$site['url'] = esc_url_raw( $new_value );
			} elseif ( 'site_icon' === $option_name ) {
				$site['logo']    = $logo_url ?: '';
				$site['logo_id'] = $logo_id;
			}
			break;
		}

		// Save the updated shared_sites option.
		AdminSettings::set_shared_sites( $shared_sites );

		// Restore blog.
		restore_current_blog();
	}
}
