<?php
/**
 * The /templates route, which lists, applies, re-syncs and removes the templates in a site's library.
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
 * Class - Templates_Controller
 *
 * @phpstan-type Shared_Content array{
 *   templates: array<int,\WP_Block_Template>,
 *   template_parts: array<string,\WP_Block_Template>,
 *   patterns: array<string,array<string,mixed>>,
 *   synced_patterns: array<int,\WP_Post>
 * }
 */
class Templates_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$args = self::get_route_args();

		if ( Settings::is_governing_site() ) {
			register_rest_route(
				self::NAMESPACE,
				'/templates',
				[
					[
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'get_templates' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site' => $args['site'],
						],
					],
					[
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'apply_templates' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site' => [ 'required' => true ] + $args['site'],
							'ids'  => $args['ids'],
						],
					],
					[
						'methods'             => 'PUT',
						'callback'            => [ $this, 'resync_templates' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site' => [ 'required' => true ] + $args['site'],
						],
					],
					[
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => [ $this, 'delete_templates' ],
						'permission_callback' => static fn () => current_user_can( 'manage_options' ),
						'args'                => [
							'site' => [ 'required' => true ] + $args['site'],
							'ids'  => $args['ids'],
						],
					],
				]
			);
		}

		if ( Settings::is_consumer_site() ) {
			register_rest_route(
				self::NAMESPACE,
				'/templates',
				[
					[
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => [ $this, 'get_received_templates' ],
						'permission_callback' => [ $this, 'is_governing_request' ],
					],
					[
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'save_received_templates' ],
						'permission_callback' => [ $this, 'is_governing_request' ],
						'args'                => [
							'templates'       => [
								'required'          => true,
								'type'              => 'array',
								'minItems'          => 1,
								'items'             => Templates::TEMPLATE_SCHEMA,
								'validate_callback' => 'rest_validate_request_arg',
							],
							// Keyed by the governing site's name for each.
							'patterns'        => [
								'type'                 => 'object',
								'additionalProperties' => Patterns::PATTERN_SCHEMA,
								'default'              => [],
								'validate_callback'    => 'rest_validate_request_arg',
							],
							'template_parts'  => [
								'type'              => 'array',
								'items'             => Templates::TEMPLATE_PART_SCHEMA,
								'default'           => [],
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
						'callback'            => [ $this, 'delete_received_templates' ],
						'permission_callback' => [ $this, 'is_governing_request' ],
						'args'                => [
							'ids' => $args['ids'],
						],
					],
				]
			);
		}
	}

	/**
	 * Lists this site's templates, or the templates a brand site has. Core's /wp/v2/templates nests the title and content, which the Template Library reads as strings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site?: string}> $request
	 */
	public function get_templates( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		if ( null === $request->get_param( 'site' ) ) {
			return rest_ensure_response( get_block_templates() );
		}

		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$templates = self::get_brand_site_templates( $site );
		if ( is_wp_error( $templates ) ) {
			return $templates;
		}

		return rest_ensure_response( array_values( array_map( [ Templates::class, 'sanitize_template' ], $templates ) ) );
	}

	/**
	 * Applies this site's templates, with the patterns, template parts and synced patterns they use, to a brand site.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site: string, ids: string[]}> $request
	 */
	public function apply_templates( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$shared = self::get_shared_content( (array) $request['ids'] );
		if ( is_wp_error( $shared ) ) {
			return $shared;
		}

		$applied = self::share_with_brand_site( $site, $shared );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		return rest_ensure_response(
			[
				/* translators: %s: brand site name. */
				'message' => sprintf( __( 'The templates were applied to %s.', 'onedesign' ), $site['name'] ),
			]
		);
	}

	/**
	 * Sends a brand site the current version of the templates it has, with what they use now.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site: string}> $request
	 */
	public function resync_templates( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$templates = self::get_brand_site_templates( $site );
		if ( is_wp_error( $templates ) ) {
			return $templates;
		}

		$shared = self::get_shared_content( array_column( $templates, 'original_id' ) );
		if ( is_wp_error( $shared ) ) {
			return $shared;
		}

		$applied = self::share_with_brand_site( $site, $shared );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		return rest_ensure_response(
			[
				/* translators: %s: brand site name. */
				'message' => sprintf( __( '%s now has the latest version of these templates.', 'onedesign' ), $site['name'] ),
			]
		);
	}

	/**
	 * Removes templates from a brand site.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{site: string, ids: string[]}> $request
	 */
	public function delete_templates( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site = self::get_site( (string) $request['site'] );
		if ( is_wp_error( $site ) ) {
			return $site;
		}

		$removed = Remote_Client::request_brand_site( $site, 'templates', WP_REST_Server::DELETABLE, [ 'ids' => (array) $request['ids'] ] );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}

		return rest_ensure_response(
			[
				/* translators: %s: brand site name. */
				'message' => sprintf( __( 'The templates were removed from %s.', 'onedesign' ), $site['name'] ),
			]
		);
	}

	/**
	 * Lists the templates this brand site received.
	 */
	public function get_received_templates(): WP_REST_Response {
		return rest_ensure_response( array_values( Templates::get_received_templates() ) );
	}

	/**
	 * Adds the templates the governing site sent, with the patterns, template parts and synced patterns they use, or updates the ones this site has.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{templates: array<int,array<string,mixed>>, patterns: array<string,array<string,mixed>>, template_parts: array<int,array{original_id: string, slug: string, title?: string, description?: string, content: string, area?: string}>, synced_patterns: array<int,array{original_id: int, slug: string, title?: string, content: string}>}> $request
	 */
	public function save_received_templates( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		// The templates, patterns and template parts point at this site's copies of the synced patterns, so those are saved first.
		$synced_pattern_ids = Templates::add_received_synced_patterns( (array) $request['synced_patterns'] );
		if ( is_wp_error( $synced_pattern_ids ) ) {
			return $synced_pattern_ids;
		}

		$saved = Templates::add_received_templates( (array) $request['templates'], (array) $request['patterns'], (array) $request['template_parts'], $synced_pattern_ids );

		return is_wp_error( $saved ) ? $saved : rest_ensure_response( [ 'message' => __( 'Templates saved.', 'onedesign' ) ] );
	}

	/**
	 * Removes templates this brand site received, by the governing site's id for each.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array{ids: string[]}> $request
	 */
	public function delete_received_templates( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$message = __( 'Templates removed.', 'onedesign' );
		$removed = Templates::remove_received_templates( (array) $request['ids'], $message );

		return is_wp_error( $removed ) ? $removed : rest_ensure_response( [ 'message' => $message ] );
	}

	/**
	 * Gets the templates a brand site has. What it sends back is checked like anything else it sends, so only the ones that match the schema of the templates it's sent are kept.
	 *
	 * @param array{api_key: string, id: string, logo: string, logo_id: int, name: string, url: string} $site The brand site.
	 *
	 * @return array<int|string,array<string,mixed>>|\WP_Error The valid ones, or the brand site's error.
	 */
	private static function get_brand_site_templates( array $site ): array|\WP_Error {
		// The timestamp keeps a cache in front of the brand site from answering with an old list.
		$templates = Remote_Client::request_brand_site( $site, add_query_arg( 'timestamp', time(), 'templates' ) );
		if ( is_wp_error( $templates ) ) {
			return $templates;
		}

		return array_filter(
			$templates,
			static fn ( $template ): bool => ! is_wp_error( rest_validate_value_from_schema( $template, Templates::TEMPLATE_SCHEMA ) )
		);
	}

	/**
	 * Gets these templates of this site, with the patterns, template parts and synced patterns they use.
	 *
	 * @param string[] $ids The template ids.
	 *
	 * @return array<string,array<int|string,mixed>>|\WP_Error
	 * @phpstan-return Shared_Content|\WP_Error
	 */
	private static function get_shared_content( array $ids ): array|\WP_Error {
		$templates = array_values( array_filter( get_block_templates(), static fn ( \WP_Block_Template $template ): bool => in_array( $template->id, $ids, true ) ) );

		if ( empty( $templates ) ) {
			return new \WP_Error( 'onedesign_templates_not_found', __( 'None of these templates exist on this site.', 'onedesign' ), [ 'status' => 400 ] );
		}

		return [ 'templates' => $templates ] + Templates::get_references( ...array_column( $templates, 'content' ) );
	}

	/**
	 * Sends templates, with what they use, to a brand site, named for it.
	 *
	 * @param array{api_key: string, id: string, logo: string, logo_id: int, name: string, url: string} $site   The brand site.
	 * @param array<string,array<int|string,mixed>>                                                     $shared What get_shared_content() returned.
	 * @phpstan-param Shared_Content $shared
	 *
	 * @return true|\WP_Error True if the brand site saved them.
	 */
	private static function share_with_brand_site( array $site, array $shared ): true|\WP_Error {
		$synced_pattern_ids = array_keys( $shared['synced_patterns'] );
		$rename             = static fn ( string $content ): string => Templates::rename_references( $content, $site['name'], $synced_pattern_ids );

		$templates = [];
		foreach ( $shared['templates'] as $template ) {
			$templates[] = array_filter(
				[
					'id'          => Templates::get_brand_slug( $template->id, $site['name'], '//' ),
					'original_id' => $template->id,
					'title'       => $template->title,
					'description' => $template->description,
					'content'     => $rename( $template->content ),
					'post_types'  => $template->post_types,
				],
				static fn ( $value ): bool => null !== $value
			);
		}

		// Keyed by this site's name for each, so the brand site replaces its copy when either site is renamed.
		$pattern_fields = array_flip( array_keys( Patterns::PATTERN_SCHEMA['properties'] ) );
		$patterns       = [];
		foreach ( Patterns::add_category_labels( $shared['patterns'] ) as $name => $pattern ) {
			$patterns[ $name ] = [
				'name'    => Templates::get_brand_slug( $name, $site['name'] ),
				'content' => $rename( (string) $pattern['content'] ),
			] + array_intersect_key( $pattern, $pattern_fields );
		}

		$template_parts = [];
		foreach ( $shared['template_parts'] as $template_part ) {
			$template_parts[] = [
				'original_id' => $template_part->id,
				'slug'        => Templates::get_brand_slug( $template_part->slug, $site['name'] ),
				'title'       => $template_part->title,
				'description' => $template_part->description,
				'content'     => $rename( $template_part->content ),
				'area'        => $template_part->area,
			];
		}

		$response = Remote_Client::request_brand_site(
			$site,
			'templates',
			WP_REST_Server::CREATABLE,
			[
				'templates'       => $templates,
				'patterns'        => $patterns,
				'template_parts'  => $template_parts,
				'synced_patterns' => Templates::get_brand_synced_patterns( $shared['synced_patterns'], $site['name'] ),
			]
		);

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Gets the args the /templates routes share. The site is optional, since listing without one lists this site's own templates.
	 *
	 * @return array{ids: array<string,mixed>, site: array<string,mixed>}
	 */
	private static function get_route_args(): array {
		return [
			'ids'  => [
				'required'          => true,
				'type'              => 'array',
				'minItems'          => 1,
				'items'             => [
					'type'      => 'string',
					'minLength' => 1,
				],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'site' => [
				'type'              => 'string',
				'minLength'         => 1,
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}
