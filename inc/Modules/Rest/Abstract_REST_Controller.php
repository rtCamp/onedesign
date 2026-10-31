<?php
/**
 * Base REST controller class.
 *
 * Includes the shared namespace, version and hook registration.
 *
 * @package OneDesign\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Modules\Rest;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Multisite\Settings as MU_Settings;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Utils;
use WP_REST_Controller;

/**
 * Class - Abstract_REST_Controller
 */
abstract class Abstract_REST_Controller extends WP_REST_Controller implements Registrable {
	/**
	 * The namespace for the REST API.
	 */
	public const NAMESPACE = 'onedesign/v1';

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
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * We throw an exception here to force the child class to implement this method.
	 *
	 * @throws \Exception If method not implemented.
	 *
	 * @codeCoverageIgnore
	 */
	public function register_routes(): void {
		throw new \Exception( __FUNCTION__ . ' Method not implemented.' );
	}

	/**
	 * Checks for the use of the OneDesign API key in the request headers.
	 *
	 * @todo this should be on a hook.
	 *
	 * @param \WP_REST_Request<array{}> $request Request.
	 */
	public function check_api_permissions( $request ): bool {
		// check if the request is from same site.
		if ( Settings::is_governing_site() ) {
			return current_user_can( 'manage_options' );
		}

		// See if the `X_ONEDESIGN_TOKEN` header is present.
		$token = $request->get_header( 'X_ONEDESIGN_TOKEN' );
		$token = ! empty( $token ) ? sanitize_text_field( wp_unslash( $token ) ) : '';

		// Bail if the token is missing or invalid.
		if ( ! hash_equals( Settings::get_api_key(), $token ) ) {
			return false;
		}

		$request_origin = $request->get_header( 'origin' );
		$request_origin = ! empty( $request_origin ) ? esc_url_raw( wp_unslash( $request_origin ) ) : '';
		$user_agent     = $request->get_header( 'user-agent' );
		$user_agent     = ! empty( $user_agent ) ? sanitize_text_field( wp_unslash( $user_agent ) ) : '';

		/**
		 * If both origin and user-agent are missing, deny access.
		 *
		 * Here checking both because server side requests will not have origin header.
		 */
		if ( empty( $request_origin ) && empty( $user_agent ) ) {
			return false;
		}

		// If it's the same domain, we're good.
		if ( self::is_same_domain( get_site_url(), $request_origin ) ) {
			return true;
		}

		$governing_site_url = Settings::get_parent_site_url();

		// If it's a healthcheck with no governing site, allow it and set the governing site.
		if ( empty( $governing_site_url ) ) {
			if ( '/' . $this->namespace . '/health-check' === $request->get_route() ) {

				// Need to check x-onedesign-source header to confirm request source is from settings page as we are performing health check before sharing patterns/templates.
				$source = $request->get_header( 'X_ONEDESIGN_SOURCE' );
				$source = ! empty( $source ) ? sanitize_text_field( wp_unslash( $source ) ) : '';
				if ( 'Settings' !== $source ) {
					return false;
				}

				Settings::set_parent_site_url( $request_origin );
				return true;
			}
			return false;
		}

		// if token is valid and request is from different domain then check if it matches governing site url.
		if ( self::is_same_domain( $governing_site_url, $request_origin ) || false !== strpos( $user_agent, $governing_site_url ) ) {
			return true;
		}

		// If we're still here, check multisite.
		if ( ! is_multisite() ) {
			return false;
		}

		$all_multisite_urls = self::get_all_multisite_urls();
		return ( in_array( $request_origin, $all_multisite_urls, true ) && in_array( $governing_site_url, $all_multisite_urls, true ) ) || false !== strpos( $user_agent, $request_origin );
	}

	/**
	 * Gets one of this site's brand sites.
	 *
	 * @param string $site_id The brand site's ID.
	 *
	 * @return array{api_key: string, id: string, logo: string, logo_id: int, name: string, url: string}|\WP_Error The brand site, or an error if it isn't one of this site's.
	 */
	protected static function get_site( string $site_id ): array|\WP_Error {
		return Settings::get_shared_site_by_id( $site_id ) ?? new \WP_Error( 'onedesign_site_not_found', __( 'The brand site was not found.', 'onedesign' ), [ 'status' => 404 ] );
	}

	/**
	 * Whether the request is from the governing site this brand site is connected to, signed with this site's API key.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function is_governing_request( $request ): bool {
		if ( ! Settings::is_consumer_site() ) {
			return false;
		}

		$governing_site_url = Settings::get_parent_site_url();
		$governing_site_url = ! empty( $governing_site_url ) ? Utils::normalize_url( $governing_site_url ) : null;

		return $governing_site_url === $this->get_requesting_site_url( $request )
			&& $this->has_api_key( $request, Settings::get_api_key() );
	}

	/**
	 * Gets the normalized URL of the site that sent the request, from its X-OneDesign-Site-URL header.
	 *
	 * @param \WP_REST_Request<array<string,mixed>>|\WP_REST_Request<array{}> $request Request.
	 *
	 * @return string The normalized URL, or an empty string if the header is missing or invalid.
	 */
	protected function get_requesting_site_url( $request ): string {
		$header   = $request->get_header( 'X-OneDesign-Site-URL' );
		$site_url = ! empty( $header ) ? esc_url_raw( wp_unslash( $header ) ) : '';
		$parsed   = wp_parse_url( $site_url );

		return ! empty( $parsed['scheme'] ) && ! empty( $parsed['host'] ) ? Utils::normalize_url( $site_url ) : '';
	}

	/**
	 * Whether the request's X-OneDesign-Token is the given API key.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @param string                                $api_key The API key the sender should hold.
	 */
	protected function has_api_key( $request, string $api_key ): bool {
		$token = (string) $request->get_header( 'X-OneDesign-Token' );

		return '' !== $api_key && '' !== $token && hash_equals( $api_key, $token );
	}

	/**
	 * Check if two URLs belong to the same domain.
	 *
	 * Ports are compared too, so sites sharing a host (e.g. `localhost:8888` and `localhost:8890`) aren't treated as the same site.
	 *
	 * @param string $url1 First URL.
	 * @param string $url2 Second URL.
	 *
	 * @return bool True if both URLs belong to the same domain, false otherwise.
	 */
	private function is_same_domain( string $url1, string $url2 ): bool {
		$parsed_url1 = wp_parse_url( $url1 );
		$parsed_url2 = wp_parse_url( $url2 );

		if ( ! isset( $parsed_url1['host'] ) || ! isset( $parsed_url2['host'] ) ) {
			return false;
		}

		return hash_equals( $parsed_url1['host'], $parsed_url2['host'] )
			&& self::get_url_port( $parsed_url1 ) === self::get_url_port( $parsed_url2 );
	}

	/**
	 * Gets the port of a parsed URL, falling back to the scheme's default port.
	 *
	 * @param array<string,int|string> $parsed_url The result of wp_parse_url().
	 */
	private static function get_url_port( array $parsed_url ): int {
		if ( isset( $parsed_url['port'] ) ) {
			return (int) $parsed_url['port'];
		}

		return 'https' === ( $parsed_url['scheme'] ?? '' ) ? 443 : 80;
	}

	/**
	 * Get URLs of all multisites in the network.
	 *
	 * @return string[] Array of multisite URLs.
	 */
	private function get_all_multisite_urls(): array {
		$sites_info = MU_Settings::get_all_multisites_info();
		$urls       = [];

		foreach ( $sites_info as $site ) {
			if ( ! isset( $site['url'] ) ) {
				continue;
			}

			$urls[] = $site['url'];
		}

		return $urls;
	}
}
