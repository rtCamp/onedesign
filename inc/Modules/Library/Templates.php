<?php
/**
 * The templates in a site's library. A governing site shares its own templates with what they use, and a brand site keeps and registers the ones it received.
 *
 * @package OneDesign\Modules\Library
 */

declare(strict_types = 1);

namespace OneDesign\Modules\Library;

use OneDesign\Contracts\Interfaces\Registrable;
use OneDesign\Modules\Settings\Settings;

/**
 * Class - Templates
 *
 * @phpstan-type References array{template_parts: array<string,\WP_Block_Template>, patterns: array<string,array<string,mixed>>, synced_patterns: array<int,\WP_Post>}
 */
final class Templates implements Registrable {
	/**
	 * The option key for the templates a brand site received.
	 *
	 * @var string
	 */
	public const TEMPLATES_OPTION_KEY = 'onedesign_shared_templates';

	/**
	 * The option key for the patterns a brand site's templates use, keyed by the governing site's name for each, which stays the same when either site is renamed.
	 *
	 * @var string
	 */
	public const TEMPLATE_PATTERNS_OPTION_KEY = 'onedesign_template_patterns';

	/**
	 * The option key for the posts a brand site created for what its governing site sent (synced patterns and template parts), keyed by the governing site's id for each.
	 *
	 * @var string
	 */
	public const POST_IDS_OPTION_KEY = 'onedesign_brand_site_post_ids';

	/**
	 * The schema of a template the governing site sends.
	 */
	public const TEMPLATE_SCHEMA = [
		'type'       => 'object',
		'properties' => [
			'id'          => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'original_id' => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'title'       => [ 'type' => 'string' ],
			'description' => [ 'type' => 'string' ],
			'content'     => [ 'type' => 'string' ],
			'post_types'  => [
				'type'  => 'array',
				'items' => [ 'type' => 'string' ],
			],
		],
		'required'   => [ 'id', 'original_id', 'content' ],
	];

	/**
	 * The schema of a template part a template uses.
	 */
	public const TEMPLATE_PART_SCHEMA = [
		'type'       => 'object',
		'properties' => [
			'original_id' => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'slug'        => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'title'       => [ 'type' => 'string' ],
			'description' => [ 'type' => 'string' ],
			'content'     => [ 'type' => 'string' ],
			'area'        => [ 'type' => 'string' ],
		],
		'required'   => [ 'original_id', 'slug', 'content' ],
	];

	/**
	 * The schema of a synced pattern the governing site sends with the templates or patterns that use it.
	 */
	public const SYNCED_PATTERN_SCHEMA = [
		'type'       => 'object',
		'properties' => [
			'original_id' => [
				'type'    => 'integer',
				'minimum' => 1,
			],
			'slug'        => [
				'type'      => 'string',
				'minLength' => 1,
			],
			'title'       => [ 'type' => 'string' ],
			'content'     => [ 'type' => 'string' ],
		],
		'required'   => [ 'original_id', 'slug', 'content' ],
	];

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_received_templates' ] );
	}

	/**
	 * Registers the templates this brand site received, and the patterns they use. The template parts they use were saved when they arrived.
	 */
	public function register_received_templates(): void {
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		foreach ( get_option( self::TEMPLATE_PATTERNS_OPTION_KEY, [] ) as $pattern ) {
			// A pattern of this site's own with the same name wins.
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern['name'] ) ) {
				continue;
			}

			register_block_pattern( $pattern['name'], $pattern );
		}

		// Without post types of its own, a template is for every post type that can have one.
		$post_types = array_values( array_diff( get_post_types( [ 'public' => true ] ), [ 'attachment' ] ) );

		// They were sanitized when they arrived.
		foreach ( self::get_received_templates() as $template ) {
			register_block_template(
				$template['id'],
				[
					'title'       => $template['title'],
					'description' => $template['description'],
					'content'     => $template['content'],
					'post_types'  => $template['post_types'] ?? $post_types,
				]
			);
		}
	}

	/**
	 * Gets the templates this brand site received.
	 *
	 * @return array<string,array{id: string, original_id: string, title: string, description: string, content: string, post_types?: string[]}> Templates keyed by the governing site's id for each.
	 */
	public static function get_received_templates(): array {
		return get_option( self::TEMPLATES_OPTION_KEY, [] );
	}

	/**
	 * Adds the templates the governing site sent, with the patterns and template parts they use, or updates the ones this site has, and points the synced patterns sent with them at those.
	 *
	 * @param array<int,array<string,mixed>>                                                                                            $templates          Templates that match TEMPLATE_SCHEMA.
	 * @param array<string,array<string,mixed>>                                                                                         $patterns           Patterns that match Patterns::PATTERN_SCHEMA, keyed by the governing site's name for each.
	 * @param array<int,array{original_id: string, slug: string, title?: string, description?: string, content: string, area?: string}> $template_parts     Template parts that match TEMPLATE_PART_SCHEMA.
	 * @param int[]                                                                                                                     $synced_pattern_ids This site's copies of the synced patterns sent with them.
	 *
	 * @return true|\WP_Error True if every template part and synced pattern was saved.
	 */
	public static function add_received_templates( array $templates, array $patterns, array $template_parts, array $synced_pattern_ids ): true|\WP_Error {
		$post_ids = [];
		foreach ( $template_parts as $template_part ) {
			$post_id = self::save_post(
				$template_part['original_id'],
				[
					'post_type'    => 'wp_template_part',
					'post_name'    => sanitize_title( $template_part['slug'] ),
					'post_title'   => sanitize_text_field( $template_part['title'] ?? '' ),
					'post_excerpt' => sanitize_text_field( $template_part['description'] ?? '' ),
					'post_content' => wp_kses_post( self::replace_refs( $template_part['content'] ) ),
					'post_status'  => 'publish',
				]
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Core finds a template part by its theme, and lists it under its area.
			wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );
			wp_set_object_terms( $post_id, sanitize_key( $template_part['area'] ?? '' ) ?: 'uncategorized', 'wp_template_part_area' );

			$post_ids[ sanitize_title( $template_part['slug'] ) ] = $post_id;
		}

		$saved_patterns = get_option( self::TEMPLATE_PATTERNS_OPTION_KEY, [] );

		$names = [
			'core/template-part' => array_map( static fn ( int $post_id ): string => get_post_field( 'post_name', $post_id ), $post_ids ),
			'core/pattern'       => [],
		];

		foreach ( $patterns as $key => $pattern ) {
			$names['core/pattern'][ $pattern['name'] ] = $saved_patterns[ $key ]['name'] ?? $pattern['name'];
		}

		$point_at_kept_names = static fn ( string $content ): string => self::rename_to_kept_names( $content, $names );

		foreach ( array_merge( $post_ids, $synced_pattern_ids ) as $post_id ) {
			$saved = self::rewrite_content( $post_id, $point_at_kept_names );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		foreach ( $patterns as $key => $pattern ) {
			$saved_patterns[ $key ] = Patterns::receive_pattern(
				[
					'name'    => $names['core/pattern'][ $pattern['name'] ],
					'content' => $point_at_kept_names( (string) $pattern['content'] ),
				] + $pattern
			);
		}
		update_option( self::TEMPLATE_PATTERNS_OPTION_KEY, $saved_patterns, false );

		$saved = self::get_received_templates();
		foreach ( $templates as $template ) {
			$template = self::sanitize_template( [ 'content' => $point_at_kept_names( self::replace_refs( (string) $template['content'] ) ) ] + $template );

			$saved[ $template['original_id'] ] = $template;
		}
		update_option( self::TEMPLATES_OPTION_KEY, $saved, false );

		return true;
	}

	/**
	 * Removes templates this brand site received, with the template parts, patterns and synced patterns that neither the other templates nor the patterns from the Pattern Library use. Templates it no longer has count as removed.
	 *
	 * @param string[] $original_ids The governing site's ids for the templates.
	 * @param string   $message      What the governing site is told was removed.
	 *
	 * @return true|\WP_Error True if every copy only they used was deleted.
	 */
	public static function remove_received_templates( array $original_ids, string $message ): true|\WP_Error {
		$templates = self::get_received_templates();
		$removed   = array_intersect_key( $templates, array_flip( $original_ids ) );

		update_option( self::TEMPLATES_OPTION_KEY, array_diff_key( $templates, $removed ), false );

		return self::remove_unused( $message );
	}

	/**
	 * Keeps the fields of a template brand sites register it with, each sanitized. The governing site runs it too on the templates brand sites send back.
	 *
	 * @param array<string,mixed> $template A template that matches TEMPLATE_SCHEMA.
	 *
	 * @return array{id: string, original_id: string, title: string, description: string, content: string, post_types?: string[]} The template.
	 */
	public static function sanitize_template( array $template ): array {
		$sanitized = [
			'id'          => sanitize_text_field( (string) $template['id'] ),
			'original_id' => sanitize_text_field( (string) $template['original_id'] ),
			'title'       => sanitize_text_field( (string) ( $template['title'] ?? '' ) ),
			'description' => sanitize_text_field( (string) ( $template['description'] ?? '' ) ),
			'content'     => wp_kses_post( (string) $template['content'] ),
		];

		// Without post types, the template applies to every post type.
		if ( isset( $template['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_key', (array) $template['post_types'] );
		}

		return $sanitized;
	}

	/**
	 * Adds a synced pattern for each one the governing site sent, or updates the copy this site has.
	 *
	 * @param array<int,array{original_id: int, slug: string, title?: string, content: string}> $synced_patterns Synced patterns that match SYNCED_PATTERN_SCHEMA.
	 *
	 * @return int[]|\WP_Error This site's copies, or the error if one of them wasn't saved.
	 */
	public static function add_received_synced_patterns( array $synced_patterns ): array|\WP_Error {
		$post_ids = [];
		foreach ( $synced_patterns as $synced_pattern ) {
			$post_id = self::save_post(
				$synced_pattern['original_id'],
				[
					'post_type'    => 'wp_block',
					'post_name'    => sanitize_title( $synced_pattern['slug'] ),
					'post_title'   => sanitize_text_field( $synced_pattern['title'] ?? '' ),
					'post_content' => wp_kses_post( $synced_pattern['content'] ),
					'post_status'  => 'publish',
				]
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			$post_ids[] = $post_id;
		}

		// They can nest each other, so they point at this site's copies once every one of them has one.
		foreach ( $post_ids as $post_id ) {
			$saved = self::rewrite_content( $post_id, [ self::class, 'replace_refs' ] );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		return $post_ids;
	}

	/**
	 * Points the synced patterns in markup the governing site sent at this site's copies, and drops the ones this site has no copy of.
	 *
	 * @param string $content Block markup with the governing site's synced pattern ids.
	 */
	public static function replace_refs( string $content ): string {
		$post_ids = get_option( self::POST_IDS_OPTION_KEY, [] );

		return traverse_and_serialize_blocks(
			parse_blocks( $content ),
			static function ( array &$block ) use ( $post_ids ): string {
				if ( 'core/block' === $block['blockName'] && isset( $block['attrs']['ref'] ) ) {
					$ref = $post_ids[ (int) $block['attrs']['ref'] ] ?? null;

					if ( $ref ) {
						$block['attrs']['ref'] = $ref;
					} else {
						unset( $block['attrs']['ref'] );
					}
				}

				return '';
			}
		);
	}

	/**
	 * Gets what the templates and the Pattern Library's patterns this brand site has use.
	 *
	 * @phpstan-return References
	 */
	public static function get_used_references(): array {
		return self::get_references( ...array_column( array_merge( array_values( self::get_received_templates() ), array_values( Patterns::get_received_patterns() ) ), 'content' ) );
	}

	/**
	 * Removes the patterns, template parts and synced patterns this site keeps for the governing site that neither its templates nor the patterns from the Pattern Library use.
	 *
	 * @param string $message What the governing site is told was saved or removed, which the error leads with.
	 *
	 * @return true|\WP_Error True if every one of them was deleted.
	 */
	public static function remove_unused( string $message ): true|\WP_Error {
		$used     = self::get_used_references();
		$patterns = get_option( self::TEMPLATE_PATTERNS_OPTION_KEY, [] );
		update_option( self::TEMPLATE_PATTERNS_OPTION_KEY, array_filter( $patterns, static fn ( array $pattern ): bool => isset( $used['patterns'][ $pattern['name'] ] ) ), false );

		$used_post_ids = array_merge(
			array_keys( $used['synced_patterns'] ),
			array_map( static fn ( \WP_Block_Template $template_part ): ?int => $template_part->wp_id, $used['template_parts'] )
		);

		// Only the posts this site created for the governing site, never its own.
		$post_ids = get_option( self::POST_IDS_OPTION_KEY, [] );
		$error    = null;
		foreach ( array_diff( $post_ids, $used_post_ids ) as $original_id => $post_id ) {
			if ( ! wp_delete_post( $post_id, true ) ) {
				/* translators: 1: "Templates saved.", "Templates removed.", "Patterns saved." or "Patterns removed.", 2: template part or synced pattern title. */
				$error = new \WP_Error( 'onedesign_post_not_deleted', sprintf( __( '%1$s The brand site couldn\'t delete "%2$s" along with them. Nothing else uses it, so you can delete that synced pattern or template part there yourself.', 'onedesign' ), $message, get_the_title( $post_id ) ), [ 'status' => 500 ] );
				continue;
			}

			unset( $post_ids[ $original_id ] );
		}
		update_option( self::POST_IDS_OPTION_KEY, $post_ids, false );

		return $error ?? true;
	}

	/**
	 * Gets the template parts, patterns and synced patterns blocks use, at any depth.
	 *
	 * A synced pattern only counts when it's published without a password, since that's the only time core renders it.
	 *
	 * @param string ...$contents Block markup.
	 *
	 * @phpstan-return References
	 */
	public static function get_references( string ...$contents ): array {
		$found = [
			'template_parts'  => [],
			'patterns'        => [],
			'synced_patterns' => [],
		];
		foreach ( $contents as $content ) {
			$found = self::find_references( parse_blocks( $content ), $found );
		}

		return $found;
	}

	/**
	 * Gives a brand site's name to the template parts and patterns blocks use, and drops the synced patterns the brand site isn't sent.
	 *
	 * The brand site points the synced patterns it's sent at its own copies.
	 *
	 * @param string $content         Block markup.
	 * @param string $site_name       The brand site's name.
	 * @param int[]  $synced_patterns The ids of the synced patterns the brand site is sent.
	 */
	public static function rename_references( string $content, string $site_name, array $synced_patterns ): string {
		$renamed = traverse_and_serialize_blocks(
			parse_blocks( $content ),
			static function ( array &$block ) use ( $site_name ): string {
				if ( in_array( $block['blockName'], [ 'core/template-part', 'core/pattern' ], true ) && isset( $block['attrs']['slug'] ) ) {
					$block['attrs']['slug'] = self::get_brand_slug( (string) $block['attrs']['slug'], $site_name );
					// The brand site keeps the template part in its own theme.
					unset( $block['attrs']['theme'] );
				}

				return '';
			}
		);

		return self::drop_unsent_refs( $renamed, $synced_patterns );
	}

	/**
	 * Drops the synced patterns blocks use that the brand site isn't sent.
	 *
	 * @param string $content         Block markup.
	 * @param int[]  $synced_patterns The ids of the synced patterns the brand site is sent.
	 */
	public static function drop_unsent_refs( string $content, array $synced_patterns ): string {
		return traverse_and_serialize_blocks(
			parse_blocks( $content ),
			static function ( array &$block ) use ( $synced_patterns ): string {
				if ( 'core/block' === $block['blockName'] && ! in_array( (int) ( $block['attrs']['ref'] ?? 0 ), $synced_patterns, true ) ) {
					unset( $block['attrs']['ref'] );
				}

				return '';
			}
		);
	}

	/**
	 * Gets a brand site's own copies of synced patterns, named for it, to send with what uses them.
	 *
	 * @param array<int,\WP_Post> $posts     The synced patterns, keyed by id.
	 * @param string              $site_name The brand site's name.
	 *
	 * @return array<int,array{original_id: int, slug: string, title: string, content: string}> Synced patterns that match SYNCED_PATTERN_SCHEMA.
	 */
	public static function get_brand_synced_patterns( array $posts, string $site_name ): array {
		$synced_patterns = [];
		foreach ( $posts as $post ) {
			$synced_patterns[] = [
				'original_id' => $post->ID,
				'slug'        => self::get_brand_slug( $post->post_name, $site_name ),
				'title'       => $post->post_title,
				'content'     => self::rename_references( $post->post_content, $site_name, array_keys( $posts ) ),
			];
		}

		return $synced_patterns;
	}

	/**
	 * Gets the name a brand site gets something of this site's under.
	 *
	 * It starts with both sites' names so it's unlikely to match one of the brand site's own. A template part, pattern or synced pattern the brand site already has keeps the name it got first, even after either site is renamed.
	 *
	 * It only has what core allows in a template id, so a name in a non-Latin script gives the hex of its bytes.
	 *
	 * @param string $slug      The slug or id on this site.
	 * @param string $site_name The brand site's name.
	 * @param string $separator What goes between the sites' names and the slug, which is "//" for a template id.
	 */
	public static function get_brand_slug( string $slug, string $site_name, string $separator = '-' ): string {
		return sanitize_key( sanitize_title( get_bloginfo( 'name' ) ) ) . '-' . sanitize_key( sanitize_title( $site_name ) ) . $separator . sanitize_key( sanitize_title( $slug ) );
	}

	/**
	 * Updates the post this site created for something the governing site sent, or creates it.
	 *
	 * Posts this site has of its own are never touched, even when they have the same slug.
	 *
	 * @param int|string          $original_id The governing site's id for it.
	 * @param array<string,mixed> $post        The post's fields.
	 *
	 * @return int|\WP_Error The post's id.
	 */
	private static function save_post( int|string $original_id, array $post ): int|\WP_Error {
		$post_ids = get_option( self::POST_IDS_OPTION_KEY, [] );
		$post_id  = $post_ids[ $original_id ] ?? 0;

		if ( get_post_type( $post_id ) === $post['post_type'] ) {
			$post['ID'] = $post_id;
			unset( $post['post_name'] );
		}

		// Both expect slashed fields.
		$post_id = isset( $post['ID'] ) ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_ids[ $original_id ] = $post_id;
		update_option( self::POST_IDS_OPTION_KEY, $post_ids, false );

		return $post_id;
	}

	/**
	 * Saves a post's content again with its references rewritten, if that changes it.
	 *
	 * @param int                     $post_id The post's id.
	 * @param callable(string):string $rewrite What rewrites the content.
	 *
	 * @return true|\WP_Error True if the content was saved or didn't change.
	 */
	private static function rewrite_content( int $post_id, callable $rewrite ): true|\WP_Error {
		$content   = (string) get_post_field( 'post_content', $post_id, 'raw' );
		$rewritten = $rewrite( $content );

		if ( $rewritten === $content ) {
			return true;
		}

		$updated = wp_update_post(
			wp_slash(
				[
					'ID'           => $post_id,
					'post_content' => $rewritten,
				]
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : true;
	}

	/**
	 * Points the template parts and patterns in markup the governing site sent at the names this site has them under.
	 *
	 * @param string                             $content Block markup with the names the governing site sent.
	 * @param array<string,array<string,string>> $names   This site's names, keyed by block name and then by the ones the governing site sent.
	 */
	private static function rename_to_kept_names( string $content, array $names ): string {
		return traverse_and_serialize_blocks(
			parse_blocks( $content ),
			static function ( array &$block ) use ( $names ): string {
				$slug = (string) ( $block['attrs']['slug'] ?? '' );

				if ( isset( $block['blockName'], $names[ $block['blockName'] ][ $slug ] ) ) {
					$block['attrs']['slug'] = $names[ $block['blockName'] ][ $slug ];
				}

				return '';
			}
		);
	}

	/**
	 * Adds the template parts, patterns and synced patterns these blocks use, and what those use in turn.
	 *
	 * @param array<int|string,array<string,mixed>> $blocks Parsed blocks.
	 * @param array                                 $found  What was found before.
	 * @phpstan-param References $found
	 *
	 * @phpstan-return References
	 */
	private static function find_references( array $blocks, array $found ): array {
		foreach ( $blocks as $block ) {
			$found = self::find_references( $block['innerBlocks'], $found );
			$attrs = $block['attrs'];

			if ( 'core/template-part' === $block['blockName'] && ! empty( $attrs['slug'] ) ) {
				$id = ( $attrs['theme'] ?? get_stylesheet() ) . '//' . $attrs['slug'];

				$template_part = isset( $found['template_parts'][ $id ] ) ? null : get_block_template( $id, 'wp_template_part' );
				if ( $template_part ) {
					$found['template_parts'][ $id ] = $template_part;
					$found                          = self::find_references( parse_blocks( $template_part->content ), $found );
				}
			}

			if ( 'core/pattern' === $block['blockName'] && ! empty( $attrs['slug'] ) ) {
				$name = (string) $attrs['slug'];

				// A brand site's template patterns are read from where they're saved, since a request that just saved them hasn't registered them.
				$pattern = isset( $found['patterns'][ $name ] ) ? null : ( array_column( get_option( self::TEMPLATE_PATTERNS_OPTION_KEY, [] ), null, 'name' )[ $name ] ?? \WP_Block_Patterns_Registry::get_instance()->get_registered( $name ) );
				if ( $pattern ) {
					$found['patterns'][ $name ] = $pattern;
					$found                      = self::find_references( parse_blocks( $pattern['content'] ), $found );
				}
			}

			if ( 'core/block' === $block['blockName'] && ! empty( $attrs['ref'] ) ) {
				$post = isset( $found['synced_patterns'][ (int) $attrs['ref'] ] ) ? null : get_post( (int) $attrs['ref'] );
				if ( $post && 'wp_block' === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password ) {
					$found['synced_patterns'][ $post->ID ] = $post;
					$found                                 = self::find_references( parse_blocks( $post->post_content ), $found );
				}
			}
		}

		return $found;
	}
}
