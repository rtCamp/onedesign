<?php
/**
 * Hooks class to handle all the hooks related functionalities.
 *
 * @package OneDesin\Modules\Post_Types;
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Post_Types;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Settings\Admin as SettingsAdmin;
use OneDesign\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 */
	private const MENU_SLUG = SettingsAdmin::MENU_SLUG;

	/**
	 * Screens used for redirects.
	 */
	public const PATTERN_REDIRECT_SCREEN  = 'onedesign-pattern-library';
	public const TEMPLATE_REDIRECT_SCREEN = 'onedesign-template-library';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_footer', [ $this, 'print_pattern_library_button_in_editor_js_template' ] );
		add_action( 'admin_footer', [ $this, 'add_templates_button_to_editor' ] );
		add_action( 'wp_ajax_register_block_patterns', [ $this, 'ajax_register_block_patterns' ] );
		add_action( 'wp_ajax_nopriv_register_block_patterns', [ $this, 'ajax_register_block_patterns' ] );
		add_action( 'init', [ $this, 'register_block_patterns_if_not_exist' ] );
		add_filter( 'should_load_remote_block_patterns', '__return_false' );
		add_action( 'after_setup_theme', [ $this, 'remove_core_block_patterns' ] );
		add_filter( 'allowed_block_types_all', [ $this, 'allowed_block_types' ], 10, 2 );

		// Create templates, patterns and template parts from saved options.
		add_action( 'after_setup_theme', [ $this, 'create_template' ], 99 );
		// Plugin specific redirects.
		add_action( 'admin_init', [ $this, 'handle_pattern_library_redirect' ] );
		add_action( 'admin_init', [ $this, 'templates_page_redirection' ] );
	}

	/**
	 * Add a settings page.
	 */
	public function add_settings_page(): void {
		// Only add plugin-specific submenu pages if sites have been connecting.
		if ( ! Settings::is_governing_site() || ! Settings::get_shared_sites() ) {
			return;
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Pattern Library', 'onedesign' ),
			__( 'Pattern Library', 'onedesign' ),
			'manage_options',
			self::PATTERN_REDIRECT_SCREEN,
			'__return_null',
			2
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Templates', 'onedesign' ),
			__( 'Template Library', 'onedesign' ),
			'manage_options',
			self::TEMPLATE_REDIRECT_SCREEN,
			'__return_null',
			2
		);
	}

	/**
	 * Create templates, patterns and template parts from saved options.
	 */
	public function create_template(): void {

		if ( Settings::is_governing_site() ) {
			return;
		}

		$brand_site_post_ids = get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, [] );

		$shared_templates = get_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [] );

		$logs = [];

		$all_post_types = get_post_types( [ 'public' => true ], 'names' );

		// Remove post types that shouldn't have templates.
		$excluded_types = [ 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ];
		$all_post_types = array_values( array_diff( $all_post_types, $excluded_types ) );

		foreach ( $shared_templates as $template ) {
			$res = register_block_template(
				sanitize_text_field( $template['id'] ),
				[
					'slug'        => isset( $template['slug'] ) ? sanitize_text_field( $template['slug'] ) : '',
					'title'       => isset( $template['title'] ) ? sanitize_text_field( $template['title'] ) : '',
					'description' => isset( $template['description'] ) ? sanitize_textarea_field( $template['description'] ) : '',
					'content'     => $template['content'] ?? '',
					'post_types'  => isset( $template['post_types'] ) ? array_map( 'sanitize_textarea_field', $template['post_types'] ) : $all_post_types,
				]
			);

			$logs[] = sprintf(
				/* translators: 1: Template slug. 2: Result. */
				__( 'Template %1$s registration result: %2$s', 'onedesign' ),
				sanitize_text_field( $template['slug'] ) ?? '',
				wp_json_encode( $res )
			);
		}

		$shared_patterns = get_option( Constants::ONEDESIGN_SHARED_PATTERNS, [] );
		foreach ( $shared_patterns as $pattern ) {
			if ( ! class_exists( '\WP_Block_Patterns_Registry' ) ) {
				require_once ABSPATH . 'wp-includes/class-wp-block-patterns-registry.php';
			}
			$res    = register_block_pattern(
				sanitize_text_field( $pattern['slug'] ),
				[
					'title'       => isset( $pattern['title'] ) ? sanitize_text_field( $pattern['title'] ) : '',
					'content'     => $pattern['content'] ?? '',
					'description' => isset( $pattern['description'] ) ? sanitize_textarea_field( $pattern['description'] ) : '',
					'postTypes'   => isset( $pattern['post_types'] ) ? array_map( 'sanitize_textarea_field', $pattern['post_types'] ) : [],
				]
			);
			$logs[] = sprintf(
				/* translators: 1: Pattern slug. 2: Result. */
				__( 'Pattern %1$s registration result: %2$s', 'onedesign' ),
				sanitize_text_field( $pattern['slug'] ),
				$res
			);
		}

		$shared_template_parts = get_option( Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS, [] );
		foreach ( $shared_template_parts as $template_part ) {
			// Check if template part already exists.
			$existing = get_posts(
				[
					'post_type'   => 'wp_template_part',
					'name'        => sanitize_text_field( $template_part['slug'] ),
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				]
			);

			if ( ! empty( $existing ) ) {
				$logs[] = sprintf(
					/* translators: 1: Template part slug. */
					__( 'Template part already exists: %s', 'onedesign' ),
					sanitize_text_field( $template_part['slug'] ),
				);
				continue;
			}

			// Create the template part post.
			$post_data = [
				'post_type'    => 'wp_template_part',
				'post_title'   => isset( $template_part['title'] ) ? sanitize_text_field( $template_part['title'] ) : '',
				'post_name'    => isset( $template_part['slug'] ) ? sanitize_text_field( $template_part['slug'] ) : '',
				'post_status'  => 'publish',
				'post_content' => $template_part['content'] ?? '',
			];

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$logs[] = sprintf(
					/* translators: 1: Error message. */
					__( 'Error creating template part %1$s: %2$s', 'onedesign' ),
					sanitize_text_field( $template_part['slug'] ),
					$post_id->get_error_message()
				);
				continue;
			}

			$logs[] = sprintf(
				/* translators: 1: Template part slug. 2: Post ID. */
				__( 'Template part created successfully: %1$s (ID: %2$d)', 'onedesign' ),
				sanitize_text_field( $template_part['slug'] ),
				$post_id
			);
			$brand_site_post_ids[] = $post_id;

			$current_theme = get_option( 'stylesheet' );
			$theme_slug    = get_option( 'template' );

			// add required meta & assign taxonomy terms.
			update_post_meta( $post_id, '_wp_template_part_area', sanitize_text_field( $template_part['area'] ) ?? 'uncategorized' );
			update_post_meta( $post_id, '_wp_theme', $current_theme );
			update_post_meta( $post_id, '_wp_template_part_theme', $theme_slug );
			wp_set_object_terms( $post_id, sanitize_text_field( $template_part['area'] ) ?? 'uncategorized', 'wp_template_part_area' );
			wp_set_object_terms( $post_id, $current_theme, 'wp_theme' );

			// Store description if provided.
			if ( isset( $template_part['description'] ) ) {
				update_post_meta( $post_id, 'description', sanitize_textarea_field( $template_part['description'] ) );
			}

			$logs[] = sprintf(
				/* translators: 1: Template part slug. 2: Post ID. */
				__( 'Template part setup completed: %1$s (ID: %2$d)', 'onedesign' ),
				sanitize_text_field( $template_part['slug'] ),
				$post_id
			);
		}
		update_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, array_unique( $brand_site_post_ids ), false );
	}

	/**
	 * Allow only specific block types.
	 *
	 * @param bool|array               $allowed_block_types Array of allowed block types or boolean to allow all or disallow all.
	 * @param \WP_Block_Editor_Context $editor_context               The post being edited, provided by the 'allowed_block_types_all' filter.
	 */
	public function allowed_block_types( bool|array $allowed_block_types, \WP_Block_Editor_Context $editor_context ): array|bool {
		// Allow all block types in the Pattern Library post type.
		if ( isset( $editor_context->post->post_type ) && ( Template::get_slug() === $editor_context->post->post_type ) ) {
			return [];
		}
		return $allowed_block_types;
	}

	/**
	 * Remove core block patterns.
	 */
	public function remove_core_block_patterns(): void {
		remove_theme_support( 'core-block-patterns' );
	}

	/**
	 * Prints the Pattern Library Button Template.
	 */
	public function print_pattern_library_button_in_editor_js_template(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen instanceof \WP_Screen || Pattern::get_slug() !== $current_screen->post_type ) {
			return;
		}
		?>
		<script id="pattern-library-gutenberg-button" type="text/html">
			<div id="pattern-library-button">
				<button id="pattern-library-main-button" type="button" class="button button-primary button-large">
					<span class="pattern-library-main-button-active">
					<?php esc_html_e( 'Patterns Selection', 'onedesign' ); ?>
				</button>
			</div>
		</script>
		<?php
	}

	/**
	 * Add templates button to editor.
	 */
	public function add_templates_button_to_editor(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen instanceof \WP_Screen || Template::get_slug() !== $current_screen->post_type ) {
			return;
		}
		?>
		<script id="onedesign-template-button" type="text/html">
			<div id="onedesign-template-render">
				<button id="template-main-button" type="button" class="button button-primary button-large">
					<span class="onedesign-template-main-button-active">
					<?php esc_html_e( 'Templates', 'onedesign' ); ?>
				</button>
			</div>
		</script>
		<?php
	}

	/**
	 * Register block patterns via ajax.
	 */
	public function ajax_register_block_patterns(): void {
		// Verify nonce for security.
		if ( ! check_ajax_referer( 'onedesign_nonce', 'security', false ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token.' ], 403 );
		}

		// Call the registration function.
		$this->register_block_patterns_if_not_exist();

		// Return success.
		wp_send_json_success( [ 'message' => 'Patterns registered successfully' ] );
	}

	/**
	 * Register block patterns if not exist.
	 */
	public function register_block_patterns_if_not_exist(): void {
		$site_patterns = get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS );
		if ( ! is_array( $site_patterns ) ) {
			return;
		}

		foreach ( $site_patterns as $pattern_name => $pattern_data ) {
			if ( ! is_array( $pattern_data ) || empty( $pattern_name ) ) {
				continue;
			}

			// Check if pattern already registered.
			if ( class_exists( '\WP_Block_Patterns_Registry' ) && \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern_name ) ) {
				continue;
			}

			// Prepare pattern args.
			$pattern_args = [
				'title'       => $pattern_data['title'] ?? '',
				'content'     => $pattern_data['content'] ?? '',
				'categories'  => $pattern_data['categories'] ?? [],
				'keywords'    => $pattern_data['keywords'] ?? [],
				'description' => $pattern_data['description'] ?? '',
			];

			// Register categories if they are not registered.
			if ( ! empty( $pattern_args['categories'] ) ) {
				foreach ( $pattern_args['categories'] as $category ) {
					if ( term_exists( $category, 'wp_pattern_category' ) ) {
						continue;
					}

					wp_insert_term( $category, 'wp_pattern_category' );
				}
			}

			// Add optional properties if they exist.
			if ( isset( $pattern_data['viewportWidth'] ) ) {
				$pattern_args['viewportWidth'] = $pattern_data['viewportWidth'];
			}
			if ( isset( $pattern_data['blockTypes'] ) ) {
				$pattern_args['blockTypes'] = $pattern_data['blockTypes'];
			}
			if ( isset( $pattern_data['postTypes'] ) ) {
				$pattern_args['postTypes'] = $pattern_data['postTypes'];
			}
			if ( isset( $pattern_data['templateTypes'] ) ) {
				$pattern_args['templateTypes'] = $pattern_data['templateTypes'];
			}
			if ( isset( $pattern_data['inserter'] ) ) {
				$pattern_args['inserter'] = $pattern_data['inserter'];
			}

			// Only register if we have required content.
			if ( empty( $pattern_args['title'] ) || empty( $pattern_args['content'] ) ) {
				continue;
			}

			register_block_pattern( $pattern_name, $pattern_args );
		}
	}

	/**
	 * Handle the redirect to create or open the Templates post.
	 *
	 * This function checks if the Templates post exists and redirects to it,
	 * or creates a new one if it doesn't exist.
	 */
	public function templates_page_redirection(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || self::TEMPLATE_REDIRECT_SCREEN !== strtolower( sanitize_text_field( $_GET['page'] ) ) ) {
			return;
		}

		// Check if a Pattern Library post already exists.
		$existing_posts = get_posts(
			[
				'post_type'        => Template::get_slug(),
				'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts'      => 1,
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $existing_posts ) ) {
			// Redirect to edit the existing post.
			wp_safe_redirect( admin_url( 'post.php?post=' . $existing_posts[0]->ID . '&action=edit' ) );
			exit;
		}

		// If no post exists, create a new one.
		$new_post_id = wp_insert_post(
			[
				'post_type'    => Template::get_slug(),
				'post_title'   => esc_html__( 'Templates', 'onedesign' ),
				'post_content' => '',
				'post_status'  => 'draft',
			]
		);

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html__( 'Error creating template post.', 'onedesign' ) );
		}

		// Redirect to the newly created post for editing.
		wp_safe_redirect( admin_url( 'post.php?post=' . $new_post_id . '&action=edit' ) );
		exit;
	}

	/**
	 * Handle the redirect to create or open the Pattern Library post.
	 *
	 * This function checks if the Pattern Library post exists and redirects to it,
	 * or creates a new one if it doesn't exist.
	 */
	public function handle_pattern_library_redirect(): void {
		$pages = [ self::PATTERN_REDIRECT_SCREEN, self::MENU_SLUG ];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || ! in_array( strtolower( sanitize_text_field( $_GET['page'] ) ), $pages, true ) ) {
			return;
		}

		$this->create_and_open_pattern_library_post();
	}

	/**
	 * Callback function to create and open a new Pattern Library post.
	 */
	private function create_and_open_pattern_library_post(): void {
		// Check if a Pattern Library post already exists.
		$existing_posts = get_posts(
			[
				'post_type'        => Pattern::get_slug(),
				'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts'      => 1,
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $existing_posts ) ) {
			// Redirect to edit the existing post.
			wp_safe_redirect( admin_url( 'post.php?post=' . $existing_posts[0]->ID . '&action=edit' ) );
			exit;
		}

		// If no post exists, create a new one.
		$new_post_id = wp_insert_post(
			[
				'post_type'    => Pattern::get_slug(),
				'post_title'   => esc_html__( 'Pattern Library', 'onedesign' ),
				'post_content' => '<!-- wp:heading {"level":2} --><h2>Click on the "Patterns Selection" to push patterns to brand site.</h2><!-- /wp:heading -->',
				'post_status'  => 'draft',
			]
		);

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html__( 'Error creating Pattern Library post.', 'onedesign' ) );
		}

		// Redirect to the newly created post for editing.
		wp_safe_redirect( admin_url( 'post.php?post=' . $new_post_id . '&action=edit' ) );
		exit;
	}
}
