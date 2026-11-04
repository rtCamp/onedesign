<?php
/**
 * The /shared-sites routes, which store the brand sites of a governing site and remove them.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Settings\Settings;
use OneDesign\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class - Shared_Sites_Controller
 */
class Shared_Sites_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		/**
		 * Register a route which will store the brand sites' name, URL, logo and API key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shared_sites' ],
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_shared_sites' ],
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
					'args'                => [
						'sites_data' => [
							'required'          => true,
							'type'              => 'array',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
			]
		);

		/**
		 * Register a route to remove a brand site, and tell it.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites/(?P<id>[A-Za-z0-9-]+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_shared_site' ],
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
			]
		);
	}

	/**
	 * Get shared sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_shared_sites(): WP_REST_Response|\WP_Error {
		return rest_ensure_response(
			[
				'success'      => true,
				'shared_sites' => Settings::get_shared_sites_without_keys(),
			]
		);
	}

	/**
	 * Set shared sites data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites_data   = $decoded_body['sites_data'] ?? [];

		$stored_sites = array_column( Settings::get_shared_sites(), null, 'id' );

		// check if same url exists more than once or not.
		$urls     = [];
		$to_check = [];
		foreach ( $sites_data as &$site ) {
			$url = Utils::normalize_url( (string) ( $site['url'] ?? '' ) );
			if ( isset( $site['url'] ) && in_array( $url, $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'onedesign' ), [ 'status' => 400 ] );
			}

			// Add a unique ID if it doesn't exist.
			$site['id'] = $site['id'] ?? wp_generate_uuid4();

			// The browser never gets the keys, so a site saved without one keeps its stored key.
			$site['api_key'] = trim( (string) ( $site['api_key'] ?? '' ) );
			$stored          = '' !== $site['id'] ? ( $stored_sites[ $site['id'] ] ?? null ) : null;
			$same_url        = null !== $stored && Utils::normalize_url( $stored['url'] ) === $url;

			if ( $same_url && '' === $site['api_key'] ) {
				$site['api_key'] = $stored['api_key'];
			}

			// New sites, and sites whose URL or key changed, are health-checked before they're saved, which also connects them.
			if ( ! $same_url || $site['api_key'] !== $stored['api_key'] ) {
				$to_check[] = $site;
			}

			$urls[] = $url;
		}
		unset( $site );

		foreach ( $to_check as $site ) {
			$checked = Remote_Client::check_brand_site( (string) ( $site['url'] ?? '' ), $site['api_key'] );

			if ( is_wp_error( $checked ) ) {
				return $checked;
			}
		}

		Settings::set_shared_sites( $sites_data );

		return rest_ensure_response(
			[
				'shared_sites' => Settings::get_shared_sites_without_keys(),
			]
		);
	}

	/**
	 * Removes a brand site, and then tells it to disconnect from this governing site.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array<string,mixed>> $request
	 */
	public function delete_shared_site( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site_id = (string) $request['id'];
		$site    = null;

		foreach ( Settings::get_shared_sites() as $stored_site ) {
			if ( $stored_site['id'] === $site_id ) {
				$site = $stored_site;
				break;
			}
		}

		if ( null === $site ) {
			return new \WP_Error( 'onedesign_site_not_found', __( 'The brand site was not found.', 'onedesign' ), [ 'status' => 404 ] );
		}

		if ( ! Settings::remove_shared_site( $site['url'] ) ) {
			return new \WP_Error( 'onedesign_remove_site_failed', __( 'The brand site could not be removed.', 'onedesign' ), [ 'status' => 500 ] );
		}

		if ( Remote_Client::disconnect_brand_site( $site ) ) {
			return rest_ensure_response(
				[
					'governing_site_notified' => true,
					'message'                 => __( 'Brand site removed successfully.', 'onedesign' ),
				]
			);
		}

		return rest_ensure_response(
			[
				'governing_site_notified' => false,
				'message'                 => sprintf(
					/* translators: %s: brand site name. */
					__( '%s was removed, but it could not be notified. If it still shows this site under Governing Site Connection, click Disconnect Governing Site in its OneDesign settings.', 'onedesign' ),
					$site['name']
				),
			]
		);
	}
}
