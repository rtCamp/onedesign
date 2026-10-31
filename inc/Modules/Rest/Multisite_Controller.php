<?php
/**
 * The /multisite routes, which make a network's sites governing sites and add the network's sites to them as brand sites.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Multisite\Utils as MU_Utils;
use OneDesign\Modules\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Multisite_Controller
 */
class Multisite_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		if ( ! MU_Utils::is_network_activated() ) {
			return;
		}

		parent::register_hooks();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		// The routes change other sites on the network, so they're for network admins only.
		register_rest_route(
			self::NAMESPACE,
			'/multisite/governing-sites',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_governing_site' ],
				'permission_callback' => static fn () => current_user_can( 'manage_network_options' ),
				'args'                => [
					'site_id' => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/multisite/brand-sites',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_brand_sites' ],
				'permission_callback' => static fn () => current_user_can( 'manage_network_options' ),
				'args'                => [
					'governing_site_id' => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'site_ids'          => [
						'required'          => true,
						'type'              => 'array',
						'items'             => [
							'type' => 'integer',
						],
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Makes a site on the network with no OneDesign type a governing site.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @phpstan-param \WP_REST_Request<array{site_id: int}> $request
	 */
	public function add_governing_site( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site_id = (int) $request['site_id'];

		if ( ! get_site( $site_id ) ) {
			return new \WP_Error(
				'onedesign_site_not_found',
				sprintf(
					/* translators: %d: site ID. */
					__( 'There\'s no site with ID %d on this network.', 'onedesign' ),
					$site_id
				),
				[ 'status' => 400 ]
			);
		}

		// A site's type never changes once it's set.
		if ( get_blog_option( $site_id, Settings::OPTION_SITE_TYPE ) ) {
			return new \WP_Error(
				'onedesign_site_has_type',
				sprintf(
					/* translators: %d: site ID. */
					__( 'Site %d already has a OneDesign site type.', 'onedesign' ),
					$site_id
				),
				[ 'status' => 400 ]
			);
		}

		update_blog_option( $site_id, Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Adds sites on the network to a governing site's brand sites.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @phpstan-param \WP_REST_Request<array{governing_site_id: int, site_ids: int[]}> $request
	 */
	public function add_brand_sites( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$governing_site_id = (int) $request['governing_site_id'];

		if ( ! get_site( $governing_site_id ) || Settings::SITE_TYPE_GOVERNING !== get_blog_option( $governing_site_id, Settings::OPTION_SITE_TYPE ) ) {
			return new \WP_Error(
				'onedesign_not_governing_site',
				sprintf(
					/* translators: %d: site ID. */
					__( 'Site %d isn\'t a governing site on this network.', 'onedesign' ),
					$governing_site_id
				),
				[ 'status' => 400 ]
			);
		}

		$site_ids = (array) $request['site_ids'];

		foreach ( $site_ids as $site_id ) {
			if ( ! get_site( $site_id ) ) {
				return new \WP_Error(
					'onedesign_site_not_found',
					sprintf(
						/* translators: %d: site ID. */
						__( 'There\'s no site with ID %d on this network.', 'onedesign' ),
						$site_id
					),
					[ 'status' => 400 ]
				);
			}

			switch_to_blog( $site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only site options are read while switched.
			$can_add_as_brand_site = MU_Utils::can_add_as_brand_site();
			restore_current_blog();

			if ( ! $can_add_as_brand_site ) {
				return new \WP_Error(
					'onedesign_site_cannot_be_brand',
					sprintf(
						/* translators: %d: site ID. */
						__( 'Site %d can\'t be added as a brand site.', 'onedesign' ),
						$site_id
					),
					[ 'status' => 400 ]
				);
			}
		}

		foreach ( $site_ids as $site_id ) {
			MU_Utils::add_brand_site( $governing_site_id, $site_id );
		}

		// The governing site's settings screen shows its brand sites from the response.
		switch_to_blog( $governing_site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only site options are read while switched.
		$shared_sites = Settings::get_shared_sites_without_keys();
		restore_current_blog();

		return rest_ensure_response( [ 'shared_sites' => $shared_sites ] );
	}
}
