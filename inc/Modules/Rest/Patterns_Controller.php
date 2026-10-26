<?php
/**
 * The /patterns route, which lists, adds and removes the patterns in a site's library.
 *
 * @package OneDesign\Modules\Rest
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Library\Patterns;
use OneDesign\Modules\Library\Templates;
use OneDesign\Modules\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class - Patterns_Controller
 */
class Patterns_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$args = self::get_route_args();

		if ( Settings::is_governing_site() ) {
			register_rest_route(
				self::NAMESPACE,
				'/patterns',
				[
					[
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'get_patterns' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site' => $args['site'],
						],
					],
					[
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'share_patterns' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site'  => [ 'required' => true ] + $args['site'],
							'names' => $args['names'],
						],
					],
					[
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => [ $this, 'delete_patterns' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site'  => [ 'required' => true ] + $args['site'],
							'names' => $args['names'],
						],
					],
				]
			);
		}

		if ( Settings::is_consumer_site() ) {
			register_rest_route(
				self::NAMESPACE,
				'/patterns',
				[
					[
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'get_received_patterns' ],
						'permission_callback' => [ $this, 'is_governing_request' ],
					],
					[
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'save_received_patterns' ],
						'permission_callback' => [ $this, 'is_governing_request' ],
						'args'                => [
							'patterns'        => [
								'required'          => true,
								'type'              => 'array',
								'minItems'          => 1,
								'items'             => Patterns::PATTERN_SCHEMA,
								'validate_callback' => 'rest_validate_request_arg',
							],
							'synced_patterns' => [
								'type'              => 'array',
								'items'             => Templates::SYNCED_PATTERN_SCHEMA,
								'default'           => [],
								'validate_callback' => 'rest_validate_request_arg',
							],
						],
					],
					[
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => [ $this, 'delete_received_patterns' ],
						'permission_callback' => [ $this, 'is_governing_request' ],
						'args'                => [
							'names' => $args['names'],
						],
					],
				]
			);
		}
	}

	/**
	 * Lists this site's patterns, or the patterns a brand site has.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site?: string}> $request
	 */
	public function get_patterns( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		if ( null === $request->get_param( 'site' ) ) {
			return rest_ensure_response( array_values( Patterns::get_local_patterns() ) );
		}

		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		// The timestamp keeps a cache in front of the brand site from answering with an old list.
		$patterns = Remote_Client::request_brand_site( $site, add_query_arg( 'timestamp', time(), 'patterns' ) );
		if ( is_wp_error( $patterns ) ) {
			return $patterns;
		}

		// What a brand site sends back is checked like anything else it sends.
		$valid = array_filter(
			$patterns,
			static fn ( $pattern ): bool => ! is_wp_error( rest_validate_value_from_schema( $pattern, Patterns::PATTERN_SCHEMA ) )
		);

		return rest_ensure_response(
			array_values(
				array_map(
					static fn ( array $pattern ): array => Patterns::sanitize_pattern( $pattern ) + [ 'category_labels' => Patterns::sanitize_category_labels( $pattern ) ],
					$valid
				)
			)
		);
	}

	/**
	 * Sends this site's patterns, with the synced patterns they use, to a brand site, which adds them or updates the ones it has.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site: string, names: string[]}> $request
	 */
	public function share_patterns( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$patterns = array_values( array_intersect_key( Patterns::get_local_patterns(), array_flip( (array) $request['names'] ) ) );
		if ( empty( $patterns ) ) {
			return new \WP_Error( 'onedesign_patterns_not_found', __( 'None of these patterns exist on this site.', 'onedesign' ), [ 'status' => 400 ] );
		}

		$references = Templates::get_references( ...array_column( $patterns, 'content' ) );
		$shared     = Remote_Client::request_brand_site(
			$site,
			'patterns',
			WP_REST_Server::CREATABLE,
			[
				'patterns'        => $patterns,
				'synced_patterns' => Templates::get_brand_synced_patterns( $references['synced_patterns'], $site['name'] ),
			]
		);
		if ( is_wp_error( $shared ) ) {
			return $shared;
		}

		return rest_ensure_response(
			[
				/* translators: %s: brand site name. */
				'message' => sprintf( __( 'The patterns were shared with %s.', 'onedesign' ), $site['name'] ),
			]
		);
	}

	/**
	 * Removes patterns from a brand site.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site: string, names: string[]}> $request
	 */
	public function delete_patterns( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$removed = Remote_Client::request_brand_site( $site, 'patterns', WP_REST_Server::DELETABLE, [ 'names' => (array) $request['names'] ] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}

		return rest_ensure_response(
			[
				/* translators: %s: brand site name. */
				'message' => sprintf( __( 'The patterns were removed from %s.', 'onedesign' ), $site['name'] ),
			]
		);
	}

	/**
	 * Lists the patterns this brand site was sent from the Pattern Library, with the labels it gives their categories.
	 */
	public function get_received_patterns(): WP_REST_Response {
		return rest_ensure_response( array_values( Patterns::add_category_labels( Patterns::get_received_patterns() ) ) );
	}

	/**
	 * Adds the patterns the governing site sent, with the synced patterns they use, or updates the ones this site has.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{patterns: array<int,array<string,mixed>>, synced_patterns: array<int,array{original_id: int, slug: string, title?: string, content: string}>}> $request
	 */
	public function save_received_patterns( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		// The patterns point at this site's copies of the synced patterns, so those are saved first.
		$saved = Templates::add_received_synced_patterns( (array) $request['synced_patterns'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		Patterns::add_received_patterns( (array) $request['patterns'] );

		return rest_ensure_response( [ 'message' => __( 'Patterns saved.', 'onedesign' ) ] );
	}

	/**
	 * Removes patterns this brand site received, with the synced patterns that neither the other patterns nor the templates use.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{names: string[]}> $request
	 */
	public function delete_received_patterns( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$message = __( 'Patterns removed.', 'onedesign' );
		$removed = Patterns::remove_received_patterns( (array) $request['names'], $message );

		return is_wp_error( $removed ) ? $removed : rest_ensure_response( [ 'message' => $message ] );
	}

	/**
	 * Gets the args the /patterns routes share. The site is optional, since listing without one lists this site's own patterns.
	 *
	 * @return array{names: array<string,mixed>, site: array<string,mixed>}
	 */
	private static function get_route_args(): array {
		return [
			'names' => [
				'required'          => true,
				'type'              => 'array',
				'minItems'          => 1,
				'items'             => [
					'type'      => 'string',
					'minLength' => 1,
				],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'site'  => [
				'type'              => 'string',
				'minLength'         => 1,
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}
