<?php

declare(strict_types = 1);

/**
 * Abstract class to register post type.
 *
 * @package OneDesign
 */

namespace OneDesign\Modules\Post_Types;

use OneDesign\Contracts\Interfaces\Registrable;

/**
 * Base class to register post types.
 */
abstract class Abstract_Post_Type implements Registrable {
	/**
	 * Get slug of post type.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * To register post type.
	 */
	abstract public function register_post_type(): void;

	/**
	 * To get argument to register custom post type.
	 *
	 * To override arguments, define this method in a child class and override args.
	 *
	 * @return array{
	 *   show_in_rest: bool,
	 *   public: bool,
	 *   has_archive: bool,
	 *   menu_position: int,
	 *   supports: list<string>,
	 * }
	 */
	public function default_args(): array {
		return [
			'show_in_rest'  => true,
			'public'        => true,
			'has_archive'   => true,
			'menu_position' => 6,
			'supports'      => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ],
		];
	}
}
