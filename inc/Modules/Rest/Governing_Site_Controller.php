<?php
/**
 * The /governing-site routes, which connect a brand site to its governing site and disconnect it.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Settings\Settings;
use OneDesign\Utils;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class - Governing_Site_Controller
 */
class Governing_Site_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		// Only on consumer sites.
		if ( Settings::is_consumer_site() ) {
			register_rest_route(
				self::NAMESPACE,
				'/governing-site',
				[
					[
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'connect_governing_site' ],
						'permission_callback' => [ $this, 'can_connect_governing_site' ],
					],
					[
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => [ $this, 'remove_governing_site' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
					],
				],
			);
		}
	}

	/**
	 * Allows a governing site holding this brand site's API key to connect it.
	 *
	 * Fails if the site is already connected to a different governing site.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function can_connect_governing_site( $request ): bool|\WP_Error {
		$site_url = $this->get_requesting_site_url( $request );

		// Bail if the site URL is empty or the API key doesn't match.
		if ( empty( $site_url ) || ! $this->has_api_key( $request, Settings::get_api_key() ) ) {
			return false;
		}

		// Never repoint a connected brand.
		$governing_site_url = Settings::get_parent_site_url();
		if ( ! empty( $governing_site_url ) && Utils::normalize_url( $governing_site_url ) !== $site_url ) {
			return new \WP_Error(
				'onedesign_already_connected',
				__( 'This site is already connected to a different governing site. Disconnect it there first.', 'onedesign' ),
				[ 'status' => 409 ]
			);
		}

		return true;
	}

	/**
	 * Connects this brand site to the governing site that sent the request, or confirms it's already connected to it.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function connect_governing_site( $request ): WP_REST_Response|\WP_Error {
		// Connect if not already connected.
		if ( empty( Settings::get_parent_site_url() ) ) {
			Settings::set_parent_site_url( $this->get_requesting_site_url( $request ) );
		}

		return rest_ensure_response(
			[
				'message' => __( 'Connected to the governing site.', 'onedesign' ),
			]
		);
	}

	/**
	 * Remove governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_governing_site(): WP_REST_Response|\WP_Error {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Governing site removed successfully.', 'onedesign' ),
			]
		);
	}
}
