<?php
/**
 * The /health-check and /governing-site routes, which connect a brand site to its governing site and disconnect it.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Settings\Settings;
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
		/**
		 * Register a route for health-check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'health_check' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/governing-site',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_governing_site' ],
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				],
			],
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health_check(): WP_REST_Response|\WP_Error {

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'onedesign' ),
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
