<?php
/**
 * The patterns in a site's library. A governing site shares its own patterns, and a brand site keeps and registers the ones it received.
 *
 * @package OneDesign\Modules\Library
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Library;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Settings\Settings;

/**
 * Class - Patterns
 */
final class Patterns implements Registrable {
	/**
	 * The option key for the patterns a brand site keeps track of.
	 *
	 * @var string
	 */
	public const PATTERNS_OPTION_KEY = 'onedesign_brand_site_patterns';

	/**
	 * The schema of a pattern the governing site sends, with the fields brand sites register it with and the labels of its categories.
	 */
	public const PATTERN_SCHEMA = [
		'type'       => 'object',
		'properties' => [
			'name'            => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'title'           => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'content'         => [ 'type' => 'string' ],
			'description'     => [ 'type' => 'string' ],
			'categories'      => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'keywords'        => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'viewportWidth'   => [ 'type' => 'integer' ],
			'blockTypes'      => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'postTypes'       => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'templateTypes'   => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
			'inserter'        => [ 'type' => 'boolean' ],
			'category_labels' => [
				'type'                 => 'object',
				'additionalProperties' => [ 'type' => 'string' ],
			],
		],
		'required'   => [ 'name', 'title', 'content' ],
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_received_patterns' ] );
	}

	/**
	 * Registers the patterns this brand site received.
	 */
	public function register_received_patterns(): void {
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		foreach ( self::get_received_patterns() as $pattern ) {
			// A pattern of this site's own with the same name wins.
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern['name'] ) ) {
				continue;
			}

			register_block_pattern( $pattern['name'], $pattern );
		}
	}

	/**
	 * Gets this site's patterns, both registered and user-created, with the labels of their categories.
	 *
	 * @return array<string,array<string,mixed>> Patterns keyed by name.
	 */
	public static function get_local_patterns(): array {
		$patterns_map = [];

		$fields              = array_flip( array_keys( self::PATTERN_SCHEMA['properties'] ) );
		$registered_patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();

		foreach ( $registered_patterns as $pattern ) {
			if ( isset( $pattern['inserter'] ) && false === $pattern['inserter'] ) {
				continue;
			}

			$patterns_map[ $pattern['name'] ] = array_intersect_key( $pattern, $fields ) + [
				'description' => '',
				'categories'  => [],
				'keywords'    => [],
			];
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- `suppress_filters` is false.
		$user_patterns = get_posts(
			[
				'post_type'        => 'wp_block',
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => -1, // phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.posts_per_page_posts_per_page -- The library needs every user pattern.
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			]
		);

		foreach ( $user_patterns as $pattern_post ) {
			// get_posts() primed the term cache.
			$categories = get_the_terms( $pattern_post, 'wp_pattern_category' );

			$keywords = get_post_meta( $pattern_post->ID, 'wp_pattern_keywords', true );
			if ( ! is_array( $keywords ) ) {
				$keywords = [];
			}

			$pattern_name = 'user-pattern-' . $pattern_post->ID;

			$patterns_map[ $pattern_name ] = [
				'name'        => $pattern_name,
				'title'       => $pattern_post->post_title,
				'description' => $pattern_post->post_excerpt,
				'content'     => $pattern_post->post_content,
				'categories'  => is_array( $categories ) ? wp_list_pluck( $categories, 'slug' ) : [],
				'keywords'    => $keywords,
			];
		}

		return self::add_category_labels( $patterns_map );
	}

	/**
	 * Gets the patterns this brand site received.
	 *
	 * @return array<string,array<string,mixed>> Patterns keyed by name.
	 */
	public static function get_received_patterns(): array {
		return get_option( self::PATTERNS_OPTION_KEY, [] );
	}

	/**
	 * Adds patterns the governing site sent, or updates the ones this site has.
	 *
	 * @param array<int,array<string,mixed>> $patterns Patterns that match PATTERN_SCHEMA.
	 */
	public static function add_received_patterns( array $patterns ): void {
		$saved = self::get_received_patterns();

		// Each pattern is stored under its sanitized name, which is the name it's listed and removed by.
		foreach ( $patterns as $pattern ) {
			$pattern                   = self::receive_pattern( $pattern );
			$saved[ $pattern['name'] ] = $pattern;
		}

		update_option( self::PATTERNS_OPTION_KEY, $saved, false );
	}

	/**
	 * Sanitizes a pattern the governing site sent and adds the pattern categories it's in. It points at this site's copies of the synced patterns it uses.
	 *
	 * A category this site doesn't have is named with the governing site's label.
	 *
	 * @param array<string,mixed> $pattern A pattern that matches PATTERN_SCHEMA.
	 *
	 * @return array<string,mixed> The pattern.
	 */
	public static function receive_pattern( array $pattern ): array {
		$labels  = self::sanitize_category_labels( $pattern );
		$pattern = self::sanitize_pattern( [ 'content' => Templates::replace_refs( (string) $pattern['content'] ) ] + $pattern );

		foreach ( $pattern['categories'] as $category ) {
			if ( ! term_exists( $category, 'wp_pattern_category' ) ) {
				wp_insert_term( $labels[ $category ] ?? $category, 'wp_pattern_category', [ 'slug' => $category ] );
			}
		}

		return $pattern;
	}

	/**
	 * Removes patterns this brand site received.
	 *
	 * Patterns it no longer has count as removed.
	 *
	 * @param string[] $names   The pattern names.
	 * @param string   $message What the governing site is told was removed.
	 *
	 * @return true|\WP_Error True if every copy only they used was deleted.
	 */
	public static function remove_received_patterns( array $names, string $message ): true|\WP_Error {
		$patterns = self::get_received_patterns();
		$removed  = array_intersect_key( $patterns, array_flip( $names ) );

		update_option( self::PATTERNS_OPTION_KEY, array_diff_key( $patterns, $removed ), false );

		return Templates::delete_unused_posts( $removed, Templates::get_used_references(), $message );
	}

	/**
	 * Keeps the fields of a pattern brand sites register it with, each sanitized. The governing site runs it too on the patterns brand sites send back.
	 *
	 * @param array<string,mixed> $pattern A pattern that matches PATTERN_SCHEMA.
	 *
	 * @return array<string,mixed> The pattern.
	 */
	public static function sanitize_pattern( array $pattern ): array {
		$strings = static fn ( string $field, callable $sanitize ): ?array => isset( $pattern[ $field ] ) ? array_map( $sanitize, (array) $pattern[ $field ] ) : null;

		return array_filter(
			[
				'name'          => sanitize_text_field( (string) $pattern['name'] ),
				'title'         => sanitize_text_field( (string) $pattern['title'] ),
				'content'       => wp_kses_post( (string) $pattern['content'] ),
				'description'   => sanitize_text_field( (string) ( $pattern['description'] ?? '' ) ),
				'categories'    => $strings( 'categories', 'sanitize_text_field' ) ?? [],
				'keywords'      => $strings( 'keywords', 'sanitize_text_field' ) ?? [],
				'viewportWidth' => isset( $pattern['viewportWidth'] ) ? (int) $pattern['viewportWidth'] : null,
				'blockTypes'    => $strings( 'blockTypes', 'sanitize_text_field' ),
				'postTypes'     => $strings( 'postTypes', 'sanitize_key' ),
				'templateTypes' => $strings( 'templateTypes', 'sanitize_key' ),
				'inserter'      => isset( $pattern['inserter'] ) ? (bool) $pattern['inserter'] : null,
			],
			static fn ( $value ): bool => null !== $value
		);
	}

	/**
	 * Gets the labels a pattern's categories were sent with, keyed by slug, each sanitized.
	 *
	 * @param array<string,mixed> $pattern A pattern that matches PATTERN_SCHEMA.
	 *
	 * @return array<string,string>
	 */
	public static function sanitize_category_labels( array $pattern ): array {
		$labels = (array) ( $pattern['category_labels'] ?? [] );

		return array_combine(
			array_map( 'sanitize_text_field', array_map( 'strval', array_keys( $labels ) ) ),
			array_map( 'sanitize_text_field', array_map( 'strval', $labels ) )
		);
	}

	/**
	 * Adds the label this site gives each of the patterns' categories, keyed by slug. A category this site doesn't have keeps its slug as its label.
	 *
	 * @template TKey of int|string
	 *
	 * @param array<TKey,array<string,mixed>> $patterns Patterns with their category slugs.
	 *
	 * @return array<TKey,array<string,mixed>> The patterns, each with its category_labels.
	 */
	public static function add_category_labels( array $patterns ): array {
		$labels = self::get_category_labels();

		foreach ( $patterns as &$pattern ) {
			$pattern['category_labels'] = [];
			foreach ( (array) ( $pattern['categories'] ?? [] ) as $category ) {
				$pattern['category_labels'][ $category ] = $labels[ $category ] ?? $category;
			}
		}

		return $patterns;
	}

	/**
	 * Gets the labels of this site's pattern categories, both registered and user-created, keyed by slug. A registered category keeps its label when admins created one with the same slug.
	 *
	 * @return array<string,string>
	 */
	private static function get_category_labels(): array {
		$terms = get_terms(
			[
				'taxonomy'   => 'wp_pattern_category',
				'hide_empty' => false,
			]
		);

		return wp_list_pluck( \WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered(), 'label', 'name' )
			+ ( is_array( $terms ) ? wp_list_pluck( $terms, 'name', 'slug' ) : [] );
	}
}
