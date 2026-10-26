<?php
/**
 * Restrict CPTs creation for more than 1 site.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Post_Types;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Settings\Settings;

/**
 * Class CPT_Restriction
 */
class CPT_Restriction implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_filter( 'register_post_type_args', [ $this, 'restrict_cpt' ], 5, 2 );
		add_action( 'init', [ $this, 'unregister_cpt' ], 20 );
		add_action( 'current_screen', [ $this, 'limit_template_posts' ] );
	}

	/**
	 * Callback function to restrict CPT creation.
	 *
	 * @param array<string,mixed> $args      Array of arguments for registering a post type.
	 * @param string              $post_type Post type key.
	 *
	 * @return array<string,mixed> Modified arguments.
	 */
	public function restrict_cpt( array $args, string $post_type ): array {
		if ( Template::get_slug() !== $post_type ) {
			return $args;
		}

		if ( Settings::is_governing_site() ) {
			// Only remove it from the menu if it's a governing site.
			$args['show_in_menu']        = false;
			$args['show_in_admin_bar']   = false;
			$args['show_in_nav_menus']   = false;
			$args['has_archive']         = false;
			$args['exclude_from_search'] = true;
			$args['publicly_queryable']  = false;

			return $args;
		}

		$args['public']              = false;
		$args['show_ui']             = false;
		$args['show_in_menu']        = false;
		$args['show_in_admin_bar']   = false;
		$args['show_in_nav_menus']   = false;
		$args['can_export']          = false;
		$args['has_archive']         = false;
		$args['exclude_from_search'] = true;
		$args['publicly_queryable']  = false;
		$args['show_in_rest']        = false;
		$args['capabilities']        = [
			'edit_post'          => 'do_not_allow',
			'read_post'          => 'do_not_allow',
			'delete_post'        => 'do_not_allow',
			'edit_posts'         => 'do_not_allow',
			'edit_others_posts'  => 'do_not_allow',
			'publish_posts'      => 'do_not_allow',
			'read_private_posts' => 'do_not_allow',
		];

		return $args;
	}

	/**
	 * Callback function to remove CPT support.
	 */
	public function unregister_cpt(): void {
		if ( Settings::is_governing_site() ) {
			return;
		}

		unregister_post_type( Template::get_slug() );
	}

	/**
	 * Callback function to limit template posts.
	 */
	public function limit_template_posts(): void {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || Template::get_slug() !== $screen->post_type || 'add' !== $screen->action ) {
			return;
		}

		// Count existing pattern library posts.
		$existing_posts = wp_count_posts( Template::get_slug() );

		// check if $existing_posts is not null to avoid errors.
		if ( ! $existing_posts ) {
			return;
		}

		$post_count = $existing_posts->publish + $existing_posts->draft + $existing_posts->pending + $existing_posts->private;

		// If a post already exists, redirect to edit screen.
		if ( $post_count <= 0 ) {
			return;
		}

		// Get the existing post.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- `suppress_filters` is false.
		$existing_post = get_posts(
			[
				'post_type'        => Template::get_slug(),
				'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts'      => 1,
				'suppress_filters' => false,
			]
		);

		if ( ! empty( $existing_post ) ) {
			// Redirect to edit screen of the existing post.
			wp_safe_redirect( admin_url( 'post.php?post=' . $existing_post[0]->ID . '&action=edit' ) );
			exit;
		}
	}
}
