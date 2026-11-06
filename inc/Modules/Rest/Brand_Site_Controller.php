<?php
/**
 * The /brand-site route, which lets a brand site remove itself from its governing site.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Settings\Settings;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class - Brand_Site_Controller
 */
class Brand_Site_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		// Only on governing sites.
		if ( Settings::is_governing_site() ) {
			// Lets a brand site remove itself when it disconnects.
			register_rest_route(
				self::NAMESPACE,
				'/brand-site',
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_current_brand_site' ],
					'permission_callback' => [ $this, 'is_brand_request' ],
				]
			);
		}
	}

	/**
	 * Removes the requesting brand site from this governing site.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function remove_current_brand_site( $request ): WP_REST_Response|\WP_Error {
		$site_url = $this->get_requesting_site_url( $request );

		if ( empty( $site_url ) || ! Settings::remove_shared_site( $site_url ) ) {
			return new \WP_Error(
				'onedesign_remove_site_failed',
				__( 'The brand site could not be removed.', 'onedesign' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'message' => __( 'Brand site removed successfully.', 'onedesign' ),
			]
		);
	}
}
