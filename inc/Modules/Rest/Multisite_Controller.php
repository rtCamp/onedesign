<?php
/**
 * This file will contain routes for OneDesign Multisite handling.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Multisite\Settings as MU_Settings;
use OneDesign\Modules\Settings\Settings;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Multisite_Controller
 */
class Multisite_Controller extends Abstract_REST_Controller {
	/**
	 * The namespace for the REST API.
	 */
	public const NAMESPACE = parent::NAMESPACE . '/multisite';

	/**
	 * {@inheritDoc}
	 *
	 * Reuses the namespace constant.
	 *
	 * @var string
	 */
	protected $namespace = self::NAMESPACE;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( ! is_multisite() ) {
			return;
		}

		parent::register_hooks();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route to store governing site in multisite setup.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/governing-site',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_multisite_governing_site' ],
					'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_multisite_governing_site' ],
					'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
					'args'                => [
						'governing_site_id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		/**
		 * Register a route to add-sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/add-sites',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_multisite_sites' ],
					'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
					'args'                => [
						'site_ids' => [
							'required' => true,
							'type'     => 'array',
						],
					],
				],
			]
		);

		/**
		 * Register a route to get all sites from current multisite setup.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/sites',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_all_multisite_sites' ],
					'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
				],
			]
		);
	}

	/**
	 * Get the governing site for multisite setup.
	 */
	public function get_multisite_governing_site(): WP_REST_Response {

		// get site wide option of onedesign_multisite_governing_site.
		$governing_site = MU_Settings::get_multisite_governing_site_id();

		return new WP_REST_Response(
			[
				'success'        => true,
				'governing_site' => (int) $governing_site,
			]
		);
	}

	/**
	 * Set the governing site for multisite setup.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_multisite_governing_site( \WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$governing_site_id = filter_var( $request->get_param( 'governing_site_id' ), FILTER_SANITIZE_NUMBER_INT );

		if ( empty( $governing_site_id ) || ! is_numeric( $governing_site_id ) ) {
			return new \WP_Error(
				'invalid_governing_site_id',
				__( 'Invalid site info provided.', 'onedesign' ),
				[ 'status' => 400 ]
			);
		}

		// update site wide option of onedesign_multisite_governing_site.
		$is_updated = MU_Settings::set_multisite_governing_site_id( (int) $governing_site_id );

		if ( ! $is_updated ) {
			return new \WP_Error(
				'update_failed',
				__( 'Failed to update governing site.', 'onedesign' ),
				[ 'status' => 500 ]
			);
		}

		// set all existing sites site-type as brand-site and current site as governing-site.
		$multisite_info = MU_Settings::get_all_multisites_info();

		foreach ( $multisite_info as $site ) {
			if ( ! switch_to_blog( (int) $site['id'] ) ) {
				continue;
			}

			if ( intval( $site['id'] ) === intval( $governing_site_id ) ) {
				update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING, false );
				delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
				delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
			} else {
				update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );
				Settings::regenerate_api_key();
				delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
				delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
			}

			restore_current_blog();
		}

		return new WP_REST_Response(
			[
				'success'        => true,
				'governing_site' => $governing_site_id,
			]
		);
	}

	/**
	 * Add sites to multisite setup.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_multisite_sites( \WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$site_ids = array_map( 'absint', (array) $request->get_param( 'site_ids' ) );

		if ( empty( $site_ids ) ) {
			return new \WP_Error(
				'invalid_site_ids',
				__( 'Invalid site info provided.', 'onedesign' ),
				[ 'status' => 400 ]
			);
		}

		// get governing site id.
		$governing_site_id = MU_Settings::get_multisite_governing_site_id();

		if ( ! $governing_site_id ) {
			return new \WP_Error(
				'no_governing_site',
				__( 'No governing site set. Please set a governing site first.', 'onedesign' ),
				[ 'status' => 400 ]
			);
		}

		// get governing site details.
		$governing_site_details = get_blog_details( $governing_site_id );
		if ( ! $governing_site_details || empty( $governing_site_details->siteurl ) ) {
			return new \WP_Error(
				'invalid_governing_site',
				__( 'The governing site could not be found.', 'onedesign' ),
				[ 'status' => 400 ]
			);
		}
		$governing_site_url = $governing_site_details->siteurl;

		$shared_sites = Settings::get_shared_sites();

		foreach ( $site_ids as $site_id ) {

			// switch to each site and update option of onedesign_site_type as brand-site.
			if ( ! switch_to_blog( (int) $site_id ) ) {
				continue;
			}

			$site_url                  = trailingslashit( get_bloginfo( 'url' ) );
			$shared_sites[ $site_url ] = [
				'id'          => (string) $site_id,
				'name'        => get_bloginfo( 'name' ),
				'url'         => $site_url,
				'api_key'     => Settings::get_api_key(),
				'is_editable' => false,
			];

			update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );
			Settings::set_parent_site_url( $governing_site_url );

			// remove shared sites options if exists to avoid conflicts.
			delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

			restore_current_blog();
		}

		// update shared sites in governing site.
		if ( ! switch_to_blog( (int) $governing_site_id ) ) {
			return new \WP_Error(
				sprintf( 'failed_to_switch_blog_%d', $governing_site_id ),
				__( 'Failed to switch to governing site blog.', 'onedesign' ),
				[ 'status' => 500 ]
			);
		}

		Settings::set_shared_sites( $shared_sites );

		restore_current_blog();

		return new WP_REST_Response(
			[
				'success'     => true,
				'added_sites' => $site_ids,
			]
		);
	}

	/**
	 * Get all sites from current multisite setup.
	 */
	public function get_all_multisite_sites(): WP_REST_Response {

		$all_multisites = MU_Settings::get_all_multisites_info();

		return new WP_REST_Response(
			[
				'success' => true,
				'sites'   => $all_multisites,
			]
		);
	}
}
