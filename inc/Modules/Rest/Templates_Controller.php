<?php
/**
 * This class will have REST endpoints for templates sharing.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Rest;

use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Settings\Settings;

/**
 * Class Templates_Controller
 */
class Templates_Controller extends Abstract_REST_Controller {
	/**
	 * The namespace for the REST API.
	 */
	public const NAMESPACE = parent::NAMESPACE . '/templates';

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {

		/**
		 * Register route to get all templates.
		 *
		 * @return void
		 */
		register_rest_route(
			self::NAMESPACE,
			'/all',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_templates' ],
				'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
			]
		);

		/**
		 * Register a route to get templates from all connected sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/connected-sites',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_templates_from_connected_sites' ],
				'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
			]
		);

		/**
		 * Register a route to get shared templates.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shared_templates' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_templates' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
					'args'                => [
						'templates'      => [
							'required' => true,
							'type'     => 'array',
						],
						'patterns'       => [
							'required' => false,
							'type'     => 'array',
						],
						'template_parts' => [
							'required' => false,
							'type'     => 'array',
						],
					],
				],
			]
		);

		/**
		 * Register a route to apply templates to brand sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/apply',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'apply_templates_to_sites' ],
				'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
				'args'                => [
					'sites'     => [
						'required' => true,
						'type'     => 'array',
					],
					'templates' => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);

		/**
		 * Register a route to remove template from shared site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/remove',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_template' ],
				'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
				'args'                => [
					'template_ids' => [
						'required' => true,
						'type'     => 'array',
					],
					'site'         => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		/**
		 * Register a route to remove template from shared site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/remove-site-templates',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_template_from_brand_site' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
				'args'                => [
					'template_ids'  => [
						'required' => true,
						'type'     => 'array',
					],
					'is_remove_all' => [
						'required' => false,
						'type'     => 'boolean',
					],
				],
			]
		);

		/**
		 * Register a route to re-sync templates to connected sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/resync',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'resync_applied_templates' ],
				'permission_callback' => [ Basic_Options_Controller::class, 'permission_callback' ],
				'args'                => [
					'sites'     => [
						'required' => true,
						'type'     => 'array',
					],
					'templates' => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);

		/**
		 * Register a route to create synced patterns.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/create-synced-patterns',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_synced_patterns' ],
				'permission_callback' => [ $this, 'check_api_permissions' ],
				'args'                => [
					'synced_patterns' => [
						'required' => false,
						'type'     => 'array',
					],
				],
			]
		);
	}

	/**
	 * Create synced patterns.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 */
	public function create_synced_patterns( \WP_REST_Request $request ): \WP_REST_Response {
		$synced_patterns = $request->get_param( 'synced_patterns' );

		$existing_synced_patterns = get_option( Constants::ONEDESIGN_SHARED_SYNCED_PATTERNS, [] );
		if ( ! is_array( $existing_synced_patterns ) ) {
			$existing_synced_patterns = [];
		}

		// Merge new synced patterns with existing ones, avoiding duplicates based on 'id'.
		foreach ( $synced_patterns as $pattern ) {
			if ( ! isset( $pattern['id'] ) || array_filter( $existing_synced_patterns, static fn ( $t ) => $t['id'] === $pattern['id'] ) ) {
				continue;
			}

			$existing_synced_patterns[] = $pattern;
		}

		update_option( Constants::ONEDESIGN_SHARED_SYNCED_PATTERNS, $existing_synced_patterns, false );

		// need to actual create posts so that in governing site I can map existing synced pattern.
		$created_posts = [];
		$error_logs    = [];
		foreach ( $existing_synced_patterns as $sync_pattern ) {
			// check if same post_name don't exists.
			$existing_post = get_posts( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
				[
					'post_type'        => 'wp_block',
					'name'             => sanitize_text_field( $sync_pattern['slug'] ),
					'post_status'      => 'publish',
					'numberposts'      => 1,
					'suppress_filters' => false,
				],
			);

			if ( ! empty( $existing_post ) ) {
				$created_posts[ $sync_pattern['original_id'] ] = $existing_post[0]->ID;
				continue;
			}

			$post_data = [
				'post_type'    => 'wp_block',
				'post_title'   => isset( $sync_pattern['title'] ) ? sanitize_text_field( $sync_pattern['title'] ) : '',
				'post_name'    => isset( $sync_pattern['slug'] ) ? sanitize_text_field( $sync_pattern['slug'] ) : '',
				'post_status'  => 'publish',
				'post_content' => $sync_pattern['content'],
			];

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$error_logs[ $sync_pattern['original_id'] ] = sprintf(
					/* translators: %s: error message */
					'Failed to create synced pattern: %s',
					$post_id->get_error_message()
				);
			} else {
				$created_posts[ $sync_pattern['original_id'] ] = $post_id;
			}
		}

		// update brand site post ids option.
		$brand_site_post_ids = get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, [] );
		if ( ! is_array( $brand_site_post_ids ) ) {
			$brand_site_post_ids = [];
		}
		$brand_site_post_ids = array_merge( $brand_site_post_ids, array_values( $created_posts ) );

		update_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, $brand_site_post_ids, false );

		return new \WP_REST_Response(
			[
				'success'         => true,
				'message'         => __( 'Synced patterns created successfully.', 'onedesign' ),
				'synced_patterns' => $existing_synced_patterns,
				'created_posts'   => $created_posts,
				'error_logs'      => $error_logs,
			],
			200
		);
	}

	/**
	 * Re-sync applied templates to given brand site.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 */
	public function resync_applied_templates( \WP_REST_Request $request ): \WP_REST_Response {
		$sites     = $request->get_param( 'sites' );
		$templates = $request->get_param( 'templates' );

		if ( empty( $sites ) || empty( $templates ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Sites and templates parameters are required.', 'onedesign' ),
				],
				400
			);
		}

		// Create request for remove_template.
		$remove_request = new \WP_REST_Request( 'DELETE', self::NAMESPACE . '/remove' );
		$remove_request->set_param( 'template_ids', $templates );
		$remove_request->set_param( 'site', $sites[0] );
		$remove_request->set_param( 'is_remove_all', true );
		$remove_template_response = $this->remove_template( $remove_request );

		// Create request for apply templates.
		$apply_request = new \WP_REST_Request( 'POST', self::NAMESPACE . '/apply' );
		$apply_request->set_param( 'templates', $templates );
		$apply_request->set_param( 'sites', $sites );
		$apply_template_response = $this->apply_templates_to_sites( $apply_request );

		return new \WP_REST_Response(
			[
				'success'         => true,
				'message'         => __( 'Templates re-synced successfully to the selected sites.', 'onedesign' ),
				'remove_response' => $remove_template_response->get_data(),
				'apply_response'  => $apply_template_response->get_data(),
			],
			200
		);
	}

	/**
	 * Remove templates.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 */
	public function remove_template_from_brand_site( \WP_REST_Request $request ): \WP_REST_Response {
		$template_ids  = $request->get_param( 'template_ids' );
		$is_remove_all = $request->get_param( 'is_remove_all' );

		if ( empty( $template_ids ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Template IDs parameter is required.', 'onedesign' ),
				],
				400
			);
		}

		$existing_templates = get_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [] );
		if ( ! is_array( $existing_templates ) ) {
			$existing_templates = [];
		}

		// Remove templates based on 'id'.
		$delete_logs = [];
		if ( $is_remove_all ) {
			$updated_templates = [];

			// get brand site post ids and remove them.
			$brand_site_post_ids = get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, [] );
			if ( is_array( $brand_site_post_ids ) ) {
				foreach ( $brand_site_post_ids as $post_id ) {
					$deleted = wp_delete_post( $post_id, true );
					if ( is_wp_error( $deleted ) ) {
						$delete_logs[] = [
							'post_id' => $post_id,
							'message' => sprintf(
								/* translators: %s: error message */
								__( 'Failed to delete post ID %s.', 'onedesign' ),
								$post_id,
							),
						];
					} else {
						$delete_logs[] = [
							'post_id' => $post_id,
							'message' => sprintf(
								/* translators: %s: post ID */
								__( 'Deleted post ID %s.', 'onedesign' ),
								$post_id,
							),
						];
					}
				}
			}
			update_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, [], false );
			update_option( Constants::ONEDESIGN_SHARED_PATTERNS, [], false );
			update_option( Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS, [], false );
			update_option( Constants::ONEDESIGN_SHARED_SYNCED_PATTERNS, [], false );
		} else {
			$updated_templates = array_filter( $existing_templates, static fn ( $t ) => ! in_array( $t['id'], $template_ids, true ) );
		}

		update_option( Constants::ONEDESIGN_SHARED_TEMPLATES, array_values( $updated_templates ), false );

		return new \WP_REST_Response(
			[
				'success'     => true,
				'message'     => __( 'Templates removed successfully.', 'onedesign' ),
				'templates'   => array_values( $updated_templates ),
				'delete_logs' => $delete_logs,
			],
			200
		);
	}

	/**
	 * Remove templates from selected brand site.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 */
	public function remove_template( \WP_REST_Request $request ): \WP_REST_Response {
		$template_ids  = $request->get_param( 'template_ids' );
		$site          = $request->get_param( 'site' );
		$is_remove_all = $request->get_param( 'is_remove_all' );

		if ( empty( $template_ids ) || empty( $site ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Template IDs and site parameters are required.', 'onedesign' ),
				],
				400
			);
		}

		$error_log     = [];
		$response_data = [];

		// get site info from child sites option.
		$site_info = $this->get_site_by_id( $site );
		if ( ! $site_info ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Site not found.', 'onedesign' ),
				],
				404
			);
		}

		$request_url = $this->build_api_endpoint( $site_info['url'], 'remove-site-templates', self::NAMESPACE );
		$api_key     = $site_info['api_key'] ?? '';

		$response = wp_safe_remote_request(
			$request_url,
			[
				'headers' => [
					'X-OneDesign-Token' => $api_key,
					'Content-Type'      => 'application/json',
				],
				'method'  => 'DELETE',
				'body'    => (string) wp_json_encode(
					[
						'template_ids'  => $template_ids,
						'is_remove_all' => $is_remove_all,
					]
				),
			]
		);

		$handle_response = $this->handle_remote_response( $response );
		if ( $handle_response['success'] ) {
			$response_data = $handle_response['data'];
		} else {
			$error_log[ $request_url ] = $handle_response['error'];
		}

		return new \WP_REST_Response(
			[
				'success'      => count( $error_log ) === 0,
				'message'      => __( 'Templates removed successfully from the site.', 'onedesign' ),
				'template_ids' => $template_ids,
				'site'         => $site,
				'response'     => $response_data,
				'errors'       => $error_log,
			],
			200
		);
	}

	/**
	 * Create or update shared templates.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 */
	public function create_templates( \WP_REST_Request $request ): \WP_REST_Response {
		$templates      = $request->get_param( 'templates' );
		$patterns       = $request->get_param( 'patterns' );
		$template_parts = $request->get_param( 'template_parts' );

		if ( empty( $templates ) || ! is_array( $templates ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Templates parameter is required and should be an array.', 'onedesign' ),
				],
				400
			);
		}

		$existing_templates = get_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [] );
		if ( ! is_array( $existing_templates ) ) {
			$existing_templates = [];
		}

		// Merge new templates with existing ones, avoiding duplicates based on 'id'.
		foreach ( $templates as $template ) {
			if ( ! isset( $template['id'] ) || array_filter( $existing_templates, static fn ( $t ) => $t['id'] === $template['id'] ) ) {
				continue;
			}

			$existing_templates[] = $template;
		}

		update_option( Constants::ONEDESIGN_SHARED_TEMPLATES, $existing_templates, false );

		// get existing patterns.
		$existing_patterns = get_option( Constants::ONEDESIGN_SHARED_PATTERNS, [] );
		if ( ! is_array( $existing_patterns ) ) {
			$existing_patterns = [];
		}

		// Merge new patterns with existing ones, avoiding duplicates based on 'id'.
		if ( is_array( $patterns ) ) {
			foreach ( $patterns as $pattern ) {
				if ( ! isset( $pattern['name'] ) || array_filter( $existing_patterns, static fn ( $t ) => $t['name'] === $pattern['name'] ) ) {
					continue;
				}

				$existing_patterns[] = $pattern;
			}
		}

		update_option( Constants::ONEDESIGN_SHARED_PATTERNS, $existing_patterns, false );

		// get existing template parts.
		$existing_template_parts = get_option( Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS, [] );
		if ( ! is_array( $existing_template_parts ) ) {
			$existing_template_parts = [];
		}

		// Merge new template parts with existing ones, avoiding duplicates based on 'id'.
		if ( is_array( $template_parts ) ) {
			foreach ( $template_parts as $template_part ) {
				if ( ! isset( $template_part['id'] ) || array_filter( $existing_template_parts, static fn ( $t ) => $t['id'] === $template_part['id'] ) ) {
					continue;
				}

				$existing_template_parts[] = $template_part;
			}
		}

		update_option( Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS, $existing_template_parts, false );

		return new \WP_REST_Response(
			[
				'success'        => true,
				'message'        => __( 'Templates saved successfully.', 'onedesign' ),
				'templates'      => $existing_templates,
				'patterns'       => $existing_patterns,
				'template_parts' => $existing_template_parts,
			],
			200
		);
	}

	/**
	 * Apply selected templates to selected brand sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response The REST response object.
	 */
	public function apply_templates_to_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$sites     = $request->get_param( 'sites' );
		$templates = $request->get_param( 'templates' );

		if ( empty( $sites ) || empty( $templates ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Sites and templates parameters are required.', 'onedesign' ),
				],
				400
			);
		}

		$all_templates    = get_block_templates();
		$shared_templates = [];
		foreach ( $all_templates as $template ) {
			$template = (array) $template;
			if ( ! in_array( $template['id'], $templates, true ) ) {
				continue;
			}

			$shared_templates[] = $template;
		}

		// process templates to have all info about its template parts/patterns.
		$parsed_templates = [];
		$already_tracked  = [];
		foreach ( $shared_templates as $template ) {
			$parsed_templates = array_merge( $parsed_templates, $this->parse_block_template( $template['content'], $already_tracked ) );
		}

		$template_parts  = array_filter( $parsed_templates, static fn ( $t ) => 'template-part' === $t['type'] );
		$patterns        = array_filter( $parsed_templates, static fn ( $t ) => 'pattern' === $t['type'] );
		$synced_patterns = array_filter( $parsed_templates, static fn ( $t ) => 'block' === $t['type'] );

		// get site info from child sites option.
		$brand_sites = Settings::get_shared_sites();

		$error_log     = [];
		$response_data = [];

		foreach ( $brand_sites as $site ) {
			$site_url     = esc_url_raw( trailingslashit( $site['url'] ) );
			$site_api_key = $site['api_key'];
			$site_id      = $site['id'];
			if ( ! in_array( $site_id, $sites, true ) ) {
				continue;
			}

			$request_url         = $this->build_api_endpoint( $site['url'], 'shared', self::NAMESPACE );
			$new_templates       = $this->modify_template_template_part_pattern_slug( $shared_templates, $site['name'] );
			$new_patterns        = $this->modify_template_template_part_pattern_slug( $patterns, $site['name'] );
			$new_template_parts  = $this->modify_template_template_part_pattern_slug( $template_parts, $site['name'] );
			$new_synced_patterns = $this->modify_template_template_part_pattern_slug( $synced_patterns, $site['name'] );

			// first make a request to create synced patterns to brand site.
			$synced_patterns_request_url = $this->build_api_endpoint( $site['url'], 'create-synced-patterns', self::NAMESPACE );
			$synced_patterns_response    = wp_safe_remote_post(
				$synced_patterns_request_url,
				[
					'headers' => [
						'X-OneDesign-Token' => $site_api_key,
						'Content-Type'      => 'application/json',
					],
					'body'    => (string) wp_json_encode(
						[
							'synced_patterns' => $new_synced_patterns,
						]
					),
				]
			);

			$handled_response = $this->handle_remote_response( $synced_patterns_response );
			if ( ! $handled_response['success'] ) {
				$error_log[ $site_url ] = $handled_response['error'];
				continue;
			}
			$synced_patterns_response = $handled_response['data'];

			// replace current site synced pattern ref with created post id.
			if ( isset( $synced_patterns_response['created_posts'] ) && is_array( $synced_patterns_response['created_posts'] ) ) {
				$created_posts = $synced_patterns_response['created_posts'];

				$new_templates      = $this->replace_block_refs( $new_templates, $created_posts );
				$new_template_parts = $this->replace_block_refs( $new_template_parts, $created_posts );
				$new_patterns       = $this->replace_block_refs( $new_patterns, $created_posts );
			}
			$response = wp_safe_remote_post(
				$request_url,
				[
					'headers' => [
						'X-OneDesign-Token' => $site_api_key,
						'Content-Type'      => 'application/json',
					],
					'body'    => (string) wp_json_encode(
						[
							'templates'      => $new_templates,
							'patterns'       => $new_patterns,
							'template_parts' => $new_template_parts,
						]
					),
				]
			);

			$handled_response = $this->handle_remote_response( $response );
			if ( ! $handled_response['success'] ) {
				$error_log[ $site_url ] = $handled_response['error'];
			} else {
				$response_data[ $site_url ] = $handled_response['data'];
			}
		}

		return new \WP_REST_Response(
			[
				'success'          => count( $error_log ) === 0,
				'message'          => __( 'Templates applied successfully to the selected sites.', 'onedesign' ),
				'sites'            => $sites,
				'templates'        => $templates,
				'shared_templates' => $shared_templates,
				'responses'        => $response_data,
				'errors'           => $error_log,
				'template_parts'   => $new_template_parts,
				'patterns'         => $new_patterns,
				'synced_patterns'  => $synced_patterns,
			],
			200
		);
	}

	/**
	 * Get all block templates.
	 */
	public function get_all_templates(): \WP_REST_Response {
		$templates = get_block_templates();
		return new \WP_REST_Response(
			[
				'success'   => true,
				'templates' => $templates,
			],
			200
		);
	}

	/**
	 * Get templates from all connected sites.
	 */
	public function get_templates_from_connected_sites(): \WP_REST_Response {
		$connected_sites = Settings::get_shared_sites();
		$sites_response  = [];
		$error_log       = [];
		foreach ( $connected_sites as $site ) {
			$request_url      = $this->build_api_endpoint( $site['url'], 'shared', self::NAMESPACE ) . '?timestamp=' . time(); // Add timestamp to avoid caching issues.
			$api_key          = $site['api_key'];
			$response         = wp_safe_remote_get(
				$request_url,
				[
					'headers' => [
						'X-OneDesign-Token' => $api_key,
						'Content-Type'      => 'application/json',
					],
					'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- Admin-only request to a brand site that may be slow.
				]
			);
			$handled_response = $this->handle_remote_response( $response );
			if ( $handled_response['success'] ) {
				if ( isset( $handled_response['data']['templates'] ) ) {
					$sites_response[ $site['id'] ] = $handled_response['data']['templates'];
				} else {
					$error_log[ $site['id'] ] = sprintf(
						/* translators: %s: site name */
						__( 'No templates found in the response from site: %s', 'onedesign' ),
						$site['name']
					);
				}
			} else {
				$error_log[ $site['id'] ] = $handled_response['error'];
			}
		}
		return new \WP_REST_Response(
			[
				'success'   => true,
				'templates' => $sites_response,
				'errors'    => $error_log,
			],
			200
		);
	}

	/**
	 * Get shared templates.
	 */
	public function get_shared_templates(): \WP_REST_Response {
		$shared_templates = get_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [] );
		return new \WP_REST_Response(
			[
				'success'   => true,
				'templates' => $shared_templates,
			],
			200
		);
	}

	/**
	 * Handle remote response.
	 *
	 * @param array<string,mixed>|\WP_Error $response The response from wp_remote_get or wp_remote_post.
	 *
	 * @return array{
	 *   success: true,
	 *   data: mixed
	 * }|array{
	 *   success: false,
	 *   error: string
	 * }
	 */
	private function handle_remote_response( array|\WP_Error $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: response code */
					__( 'Unexpected response code: %s', 'onedesign' ),
					$response_code
				),
			];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: error message */
					__( 'JSON decode error: %s', 'onedesign' ),
					json_last_error_msg()
				),
			];
		}

		return [
			'success' => true,
			'data'    => $data,
		];
	}

	/**
	 * Get site info by site ID.
	 *
	 * @param string $site_id Site ID.
	 *
	 * @return ?array<string,mixed> Site info array or null if not found.
	 */
	private function get_site_by_id( string $site_id ): array|null {
		$sites    = Settings::get_shared_sites();
		$filtered = array_filter(
			$sites,
			static function ( $site ) use ( $site_id ): bool {
				return (string) $site['id'] === (string) $site_id;
			}
		);

		return ! empty( $filtered ) ? array_values( $filtered )[0] : null;
	}

	/**
	 * Modify the slug and id of templates, template parts, and patterns to ensure uniqueness across shared sites.
	 * Also modifies references within the content.
	 *
	 * @param array<int|string,array<string,mixed>> $templates Array of template objects.
	 * @param string                                $shared_site_name The name of the site to which template is going to be shared.
	 *
	 * @return array<int|string,array<string,mixed>> The modified template array with unique slugs, ids, and updated content references.
	 */
	private function modify_template_template_part_pattern_slug( array $templates, string $shared_site_name ): array {
		foreach ( $templates as $index => $template ) {

			// set original slug field to keep track of original slugs.
			if ( isset( $template['slug'] ) && ! isset( $template['original_slug'] ) ) {
				$templates[ $index ]['original_slug'] = $template['slug'];
			}

			// set original id field to keep track of original ids.
			if ( isset( $template['id'] ) && ! isset( $template['original_id'] ) ) {
				$templates[ $index ]['original_id'] = $template['id'];
			}

			// Modify top-level slug and id.
			if ( isset( $template['slug'] ) ) {
				$templates[ $index ]['slug'] = self::generate_unique_slug_for_template_patterns_template_parts(
					$template['slug'],
					$shared_site_name
				);
			}

			if ( isset( $template['id'] ) ) {
				$templates[ $index ]['id'] = self::generate_unique_slug_for_template_patterns_template_parts(
					$template['id'],
					$shared_site_name,
					false
				);
			}

			/**
			 * Removes the "theme" attribute from WordPress block comments in the content.
			 *
			 * For example, transforms:
			 * <!-- wp:template-part {"slug":"onedesign-onepress-2-ut","theme":"rtcamp-2024","area":"uncategorized"} /-->
			 * into:
			 * <!-- wp:template-part {"slug":"onedesign-onepress-2-ut","area":"uncategorized"} /-->
			 */
			$content = $template['content'] ?? '';

			if ( ! empty( $content ) && is_string( $content ) ) {
				// Remove theme attribute from block comments.
				$pattern = '/<!--\s*wp:(template-part|pattern)\s*(\{[^}]*\})\s*\/?-->/';

				$content = preg_replace_callback(
					$pattern,
					static function ( $matches ): string|null {
						$block_type      = $matches[1];
						$attributes_json = $matches[2];

						// Decode the attributes.
						$attributes = json_decode( $attributes_json, true );
						if ( ! $attributes ) {
							return $matches[0]; // Return original if JSON decode fails.
						}

						// Remove theme attribute if present.
						if ( isset( $attributes['theme'] ) ) {
							unset( $attributes['theme'] );
						}

						// Re-encode and return.
						return '<!-- wp:' . $block_type . ' ' . wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES ) . ' /-->';
					},
					$content
				);

				// Assign cleaned content back.
				$templates[ $index ]['content'] = $content;
			}

			// Modify content references (uses the cleaned content from above).
			$current_content = $templates[ $index ]['content'] ?? '';

			if ( empty( $current_content ) ) {
				continue;
			}

			$modified_content = $this->modify_content_references(
				$current_content,
				$shared_site_name
			);

			$templates[ $index ]['content'] = $modified_content;
		}

		return $templates;
	}

	/**
	 * Modify template part and pattern references within block content.
	 *
	 * @param string|array<string,mixed>|\WP_Block_Template $content The block content containing WordPress block markup.
	 * @param string                                        $shared_site_name The name of the site to which template is going to be shared.
	 *
	 * @return array<string,mixed>|string|null Modified content with updated slugs and themes.
	 */
	private function modify_content_references( string|array|\WP_Block_Template $content, string $shared_site_name ): array|string|null {

		$content_string = '';

		if ( is_string( $content ) ) {
			$content_string = $content;
		} elseif ( is_object( $content ) ) {
			// Handle WP_Block_Template object.
			if ( isset( $content->content ) ) {
				$content_string = $content->content;
			} elseif ( isset( $content->post_content ) ) {
				// Handle WP_Post object (for patterns/blocks).
				$content_string = $content->post_content;
			} else {
				// Return empty string if we can't find content.
				return '';
			}
		} elseif ( is_array( $content ) ) {
			// Handle array format.
			if ( ! isset( $content['content'] ) ) {
				return '';
			}

			$content_string = $content['content'];
		} else {
			// Unsupported content type.
			return '';
		}

		// Pattern to match template-part and pattern blocks.
		$pattern = '/<!--\s*wp:(template-part|pattern)\s*(\{[^}]*\})\s*\/?-->/';

		return preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $shared_site_name ): string|null {
				$block_type      = $matches[1];
				$attributes_json = $matches[2];

				// Decode the attributes.
				$attributes = json_decode( $attributes_json, true );

				if ( ! $attributes ) {
					return $matches[0]; // Return original if JSON decode fails.
				}

				// Modify slug if present.
				if ( isset( $attributes['slug'] ) ) {
					$attributes['slug'] = self::generate_unique_slug_for_template_patterns_template_parts(
						$attributes['slug'],
						$shared_site_name
					);
				}

				// Encode back to JSON.
				$new_attributes_json = wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES );

				// Return the modified block.
				return "<!-- wp:{$block_type} {$new_attributes_json} /-->";
			},
			$content_string
		);
	}

	/**
	 * Generate a unique slug for template patterns and template parts.
	 *
	 * @param string $base_slug The base slug (e.g., 'header', 'footer').
	 * @param string $sharing_site_name The name of the site to which template is going to be shared.
	 * @param bool   $is_slug Whether this is for slug (true) or id (false).
	 *
	 * @return string Unique slug combining current site name, sharing site name, and base slug.
	 */
	private static function generate_unique_slug_for_template_patterns_template_parts( string $base_slug, string $sharing_site_name, bool $is_slug = true ): string {
		// Sanitize the base slug to ensure it's URL-friendly.
		$sanitized_slug = sanitize_title( $base_slug );

		// Convert sharing site name to lowercase and sanitize it.
		$sanitized_site_name = sanitize_title( strtolower( $sharing_site_name ) );

		// Combine the sanitized base slug with the sanitized site name.
		$unique_slug = '';
		if ( $is_slug ) {
			$unique_slug = self::get_current_site_name() . '-' . $sanitized_site_name . '-' . $sanitized_slug;
		} else {
			$unique_slug = self::get_current_site_name() . '-' . $sanitized_site_name . '//' . $sanitized_slug;
		}

		return $unique_slug;
	}

	/**
	 * Get current site name in lowercase.
	 *
	 * @return string Current site name in lowercase.
	 */
	private static function get_current_site_name(): string {
		$site_name = get_bloginfo( 'name' );
		// convert to lowercase letters.
		return sanitize_title( strtolower( $site_name ) );
	}

	/**
	 * Parse the block template content to extract blocks, template parts, and patterns.
	 *
	 * This function identifies and extracts blocks, template parts, and patterns from the provided content.
	 * It handles nested structures and ensures that each unique content is processed only once to avoid duplication.
	 *
	 * @param string   $content         The block template content to parse.
	 * @param string[] $already_tracked An array to keep track of already processed content to avoid duplication.
	 *                                  This should be passed by reference to maintain state across recursive calls.
	 *
	 * @return array<array<string,mixed>> An array of parsed elements, each containing type, attributes, and content.
	 */
	private function parse_block_template( string $content, array &$already_tracked ): array {
		$results = [];

		// to process template parts and patterns.
		$pattern = '/<!--\s*wp:(template-part|pattern|block)\s*(\{[^}]*\})?\s*\/?-->/';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$block_type      = $match[1];
				$attributes_json = $match[2] ?? '{}';

				// Decode JSON attributes.
				$attributes = json_decode( $attributes_json, true );

				$result = [
					'type'       => $block_type,
					'full_match' => $match[0],
					'attributes' => $attributes ? $attributes : [],
				];

				// Create unique tracking key based on content identity.
				$tracking_key = '';

				if ( 'template-part' === $block_type ) {
					$template_id           = $result['attributes']['theme'] . '//' . $result['attributes']['slug'];
					$result['content']     = get_block_template(
						id: $template_id,
						template_type: 'wp_template_part'
					) ?? '';
					$result['id']          = $result['content']->id ?? null;
					$result['slug']        = $result['content']->slug ?? null;
					$result['theme']       = $result['content']->theme ?? null;
					$result['title']       = $result['content']->title ?? null;
					$result['description'] = $result['content']->description ?? null;
					$result['post_types']  = $result['content']->post_types ?? null;
					$result['area']        = $result['content']->area ?? null;
					$tracking_key          = 'template-part_' . $template_id;
				}

				if ( 'pattern' === $block_type ) {
					$result['content']     = \WP_Block_Patterns_Registry::get_instance()->get_registered( $result['attributes']['slug'] ) ?? '';
					$result['title']       = $result['content']['title'] ?? null;
					$result['slug']        = $result['content']['slug'] ?? null;
					$result['description'] = $result['content']['description'] ?? null;
					$result['name']        = $result['content']['name'] ?? null;
					$result['post_types']  = $result['content']->post_types ?? null;
					$tracking_key          = 'pattern_' . $result['attributes']['slug'];
				}

				if ( 'block' === $block_type ) {
					$result['content']     = get_post( $attributes['ref'] );
					$result['id']          = $result['content']->ID ?? null;
					$result['slug']        = $result['content']->post_name ?? null;
					$result['title']       = $result['content']->post_title ?? null;
					$result['description'] = $result['content']->post_excerpt ?? null;
					$result['content']     = $result['content']->post_content ?? null;
					$tracking_key          = 'block_' . $attributes['ref'];
				}

				// Check if this specific content has already been processed.
				if ( in_array( $tracking_key, $already_tracked, true ) ) {
					continue;
				}

				// Add to tracking to prevent processing again.
				$already_tracked[] = $tracking_key;

				// Add to results only if not already processed.
				$results[] = $result;

				// Recursively parse nested blocks and merge them at the same level.
				if ( empty( $result['content'] ) ) {
					continue;
				}

				$nested_blocks = [];

				if ( isset( $result['content']->content ) ) {
					$nested_blocks = $this->parse_block_template( $result['content']->content, $already_tracked );
				}

				if ( isset( $result['content']->post_content ) ) {
					$nested_blocks = $this->parse_block_template( $result['content']->post_content, $already_tracked );
				}

				// Flatten the nested results into the main array.
				$results = array_merge( $results, $nested_blocks );
			}
		}

		return $results;
	}

	/**
	 * Replace wp:block ref IDs - handles multiple WordPress block comment formats.
	 *
	 * @param array<int|string,array<string,mixed>> $items       Array of items to process.
	 * @param array<int|string,int|string>          $id_map      Map of old_id => new_id.
	 * @param string                                $content_key Key for the content field (default: 'content').
	 * @return array<int|string,array<string,mixed>> Modified items with updated block refs.
	 */
	private function replace_block_refs( array $items, array $id_map = [], string $content_key = 'content' ): array {
		if ( empty( $id_map ) ) {
			return $items;
		}

		foreach ( $items as $key => $item ) {
			if ( ! isset( $item[ $content_key ] ) || empty( $item[ $content_key ] ) ) {
				continue;
			}

			$content = $item[ $content_key ];

			foreach ( $id_map as $old_id => $new_id ) {
				$pattern1 = '/(<!--\s*wp:block\s*\{\s*"ref"\s*:\s*)' . preg_quote( (string) $old_id, '/' ) . '(\s*\}\s*\/-->)/';
				$content  = preg_replace( $pattern1, '${1}' . $new_id . '${2}', $content );
			}

			$items[ $key ][ $content_key ] = $content;
		}

		return $items;
	}
}
