<?php
/**
 * Class Meta to register all the metas based on post type.
 *
 * @package OneDesign
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Post_Types;

use OneDesign\Contracts\Interfaces\Registrable;

/**
 * Class Meta
 */
class Meta implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// Register all the meta based on post type here.
		add_action( 'init', [ $this, 'register_custom_meta' ], 10 );
	}

	/**
	 * Callback function to register the custom meta for all post types.
	 */
	public function register_custom_meta(): void {

		// Get all the post meta with required information.
		$post_meta_array = $this->get_post_meta_array();

		foreach ( $post_meta_array as $meta_info ) {
			$args = [
				'show_in_rest'  => $meta_info['show_in_rest'] ?? true,
				'type'          => $meta_info['type'] ?? '',
				'single'        => $meta_info['single'] ?? true,
				'auth_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			];

			if ( array_key_exists( 'default', $meta_info ) ) {
				$args['default'] = $meta_info['default'];
			}

			$post_types = $meta_info['post_type'];

			if ( ! is_array( $post_types ) || empty( $post_types ) ) {
				continue;
			}

			foreach ( $post_types as $post_type ) {
				register_post_meta( $post_type, $meta_info['meta'], $args );
			}
		}
	}

	/**
	 * Function to register the meta array with
	 * required information to posttype, metakey, type and default values.
	 */
	private function get_post_meta_array(): array {
		return [
			[
				'post_type'    => [ Pattern::get_slug() ],
				'meta'         => 'brand_site',
				'type'         => 'array',
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type' => 'integer',
						],
					],
				],
				'single'       => true,
			],
		];
	}
}
