<?php
/**
 * Sends the requests between a governing site and its brand sites.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Utils;

/**
 * Class - Remote_Client
 */
class Remote_Client {
	/**
	 * Runs a brand site's health check, which also connects it to this governing site.
	 *
	 * @param string $url     The brand site's URL.
	 * @param string $api_key The brand site's API key.
	 *
	 * @return true|\WP_Error True if the brand site accepted the key.
	 */
	public static function check_brand_site( string $url, string $api_key ): true|\WP_Error {
		$response = self::request(
			$url,
			'governing-site',
			$api_key,
			[
				'method'  => \WP_REST_Server::CREATABLE,
				'timeout' => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- An admin is waiting for the answer.
			]
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return true;
		}

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'onedesign_bad_key', __( 'The brand site rejected this API key. Copy the key from the brand site\'s OneDesign settings and try again.', 'onedesign' ), [ 'status' => 400 ] );
		}

		if ( 409 === $code ) {
			return new \WP_Error( 'onedesign_already_connected', __( 'This brand site is already connected to a different governing site. Disconnect it there first, then try again.', 'onedesign' ), [ 'status' => 409 ] );
		}

		return new \WP_Error( 'onedesign_health_check_failed', __( 'Could not reach the brand site. Check the URL, and that OneDesign is active there, then try again.', 'onedesign' ), [ 'status' => 502 ] );
	}

	/**
	 * Sends a JSON request to a OneDesign route on a brand site, and decodes its answer. Anything but a 200 is an error with the brand site's own message.
	 *
	 * @param array{url: string, api_key: string} $site_data The brand site, with its stored key.
	 * @param string                              $route     The route, relative to the OneDesign namespace.
	 * @param string                              $method    The request method.
	 * @param ?array<string,mixed>                $body      The request body, if any.
	 *
	 * @return array<int|string,mixed>|\WP_Error The decoded response, or the brand site's error.
	 */
	public static function request_brand_site( array $site_data, string $route, string $method = \WP_REST_Server::READABLE, ?array $body = null ): array|\WP_Error {
		if ( empty( $site_data['url'] ) || empty( $site_data['api_key'] ) ) {
			return new \WP_Error( 'onedesign_missing_site_data', __( 'Missing url or api_key.', 'onedesign' ) );
		}

		$args = [ 'method' => $method ];
		if ( null !== $body ) {
			$args['body'] = (string) wp_json_encode( $body );
		}

		$response = self::request( $site_data['url'], $route, $site_data['api_key'], $args );

		if ( is_wp_error( $response ) ) {
			$response->add_data( [ 'status' => 502 ] );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'onedesign_bad_key', __( 'The brand site rejected the API key. This is usually caused when the brand site regenerates its API key. Copy the key from the brand site\'s OneDesign settings, then edit the site under Brand Sites and paste it in.', 'onedesign' ), [ 'status' => 400 ] );
		}

		if ( 200 !== $code ) {
			return new \WP_Error(
				'onedesign_rest_failed_to_connect',
				// translators: %s is the error code.
				is_string( $data['message'] ?? null ) ? $data['message'] : sprintf( __( 'Failed to connect to the brand site. Error code %s', 'onedesign' ), (string) $code ),
				[ 'status' => $code ]
			);
		}

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'onedesign_rest_invalid_response', __( 'The brand site returned an invalid response.', 'onedesign' ), [ 'status' => 500 ] );
		}

		return $data;
	}

	/**
	 * Sends a request to a OneDesign route on another site, signed with the API key that site checks.
	 *
	 * @param string              $site_url The other site's URL.
	 * @param string              $route    The route, relative to the OneDesign namespace.
	 * @param string              $api_key  The API key the other site checks.
	 * @param array<string,mixed> $args     Extra request arguments, like the method and timeout.
	 *
	 * @return array<string,mixed>|\WP_Error The response.
	 */
	private static function request( string $site_url, string $route, string $api_key, array $args = [] ): array|\WP_Error {
		return wp_safe_remote_request(
			sprintf( '%s/wp-json/%s/%s', untrailingslashit( $site_url ), Abstract_REST_Controller::NAMESPACE, $route ),
			$args + [
				'headers' => [
					'Accept'               => 'application/json',
					'Content-Type'         => 'application/json',
					'X-OneDesign-Token'    => $api_key,
					'X-OneDesign-Site-URL' => Utils::get_current_site_url(),
				],
			]
		);
	}
}
