<?php
/**
 * The /secret-key route, which regenerates a brand site's API key.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Settings\Settings;
use WP_REST_Server;

/**
 * Class - Secret_Key_Controller
 */
class Secret_Key_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route to regenerate the api key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => static function () {
						$api_key = Settings::regenerate_api_key();

						return new \WP_REST_Response(
							[
								'secret_key' => $api_key,
							]
						);
					},
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				],
			]
		);
	}
}
