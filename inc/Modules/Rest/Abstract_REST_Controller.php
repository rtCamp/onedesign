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
	 * Whether the request is from a brand site of this governing site, signed with that brand's API key.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function is_brand_request( $request ): bool {
		if ( ! Settings::is_governing_site() ) {
			return false;
		}

		$shared_sites = Settings::get_shared_sites();
		$site_url     = $this->get_requesting_site_url( $request );

		return $this->has_api_key( $request, $shared_sites[ $site_url ]['api_key'] ?? '' );
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
}
