<?php

declare(strict_types = 1);

/**
 * Register template post type.
 *
 * @package OneDesign
 */

namespace OneDesign\Modules\Post_Types;

/**
 * Class Template
 */
class Template extends Abstract_Post_Type {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'onedesign-template';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_post_type(): void {
		// phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral -- Slug is defined in get_slug method.
		register_post_type(
			self::get_slug(),
			[
				'public'        => false,
				'show_ui'       => true,
				'has_archive'   => false,
				'show_in_rest'  => true,
				'menu_position' => 6,
				'supports'      => [ 'title', 'editor', 'custom-fields' ],
				'menu_icon'     => 'dashicons-media-text',
				'labels'        => [
					'name'               => _x( 'Template Library', 'post type general name', 'onedesign' ),
					'singular_name'      => _x( 'Template Library', 'post type singular name', 'onedesign' ),
					'menu_name'          => _x( 'Template Library', 'admin menu', 'onedesign' ),
					'name_admin_bar'     => _x( 'Template Library', 'add new on admin bar', 'onedesign' ),
					'add_new'            => _x( 'Add New', 'Template Library', 'onedesign' ),
					'add_new_item'       => __( 'Add New Template Library', 'onedesign' ),
					'new_item'           => __( 'New Template Library', 'onedesign' ),
					'edit_item'          => __( 'Edit Template Library', 'onedesign' ),
					'view_item'          => __( 'View Template Library', 'onedesign' ),
					'all_items'          => __( 'All Template Library', 'onedesign' ),
					'search_items'       => __( 'Search Template Library', 'onedesign' ),
					'parent_item_colon'  => __( 'Parent Template Library:', 'onedesign' ),
					'not_found'          => __( 'No Template Library found.', 'onedesign' ),
					'not_found_in_trash' => __( 'No Template Library found in trash.', 'onedesign' ),
				],
			]
		);
	}
}
