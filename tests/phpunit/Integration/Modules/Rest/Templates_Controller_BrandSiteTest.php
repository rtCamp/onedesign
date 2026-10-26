<?php
/**
 * Tests for Templates_Controller on a brand site.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Library\Patterns;
use OneDesign\Modules\Library\Templates;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Rest\Templates_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * The templates, and what they use, that a governing site sends a brand site.
 */
#[CoversClass( Templates_Controller::class )]
#[CoversClass( Templates::class )]
#[CoversClass( Patterns::class )]
class Templates_Controller_BrandSiteTest extends TestCase {
	/**
	 * The governing site this brand site is connected to.
	 */
	private const GOVERNING_SITE_URL = 'https://governing.example.com';

	/**
	 * Markup with a script and an event handler.
	 */
	private const UNSAFE_CONTENT = '<!-- wp:paragraph --><p>Hello<img src="x" onerror="alert(1)"></p><!-- /wp:paragraph --><script>alert(2)</script>';

	/**
	 * REST server.
	 */
	private ?\WP_REST_Server $server;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( self::GOVERNING_SITE_URL );

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		wp_set_current_user( 0 );

		( new Templates_Controller() )->register_hooks();
		( new Patterns_Controller() )->register_hooks();
		do_action( 'rest_api_init' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		foreach ( [ 'gov-brand//landing', 'gov-brand//other' ] as $template ) {
			if ( \WP_Block_Templates_Registry::get_instance()->is_registered( $template ) ) {
				unregister_block_template( $template );
			}
		}
		foreach ( [ 'gov-brand-cta', 'user-pattern-7' ] as $pattern ) {
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern ) ) {
				unregister_block_pattern( $pattern );
			}
		}

		parent::tear_down();
	}

	/**
	 * Scripts and event handlers in a template, its pattern and its template part never reach what this site stores, registers or creates from them.
	 */
	public function test_saved_templates_have_no_scripts(): void {
		$response = $this->send(
			'POST',
			'templates',
			[
				'templates'      => [
					[
						'id'          => 'gov-brand//landing',
						'original_id' => 'theme//landing',
						'title'       => 'Landing<script>alert(3)</script>',
						'description' => '<img src=x onerror=alert(4)>',
						'content'     => self::UNSAFE_CONTENT,
					],
				],
				'patterns'       => [
					'theme/cta' => [
						'name'    => 'gov-brand-cta',
						'title'   => 'CTA',
						'content' => self::UNSAFE_CONTENT,
					],
				],
				'template_parts' => [
					[
						'original_id' => 'theme//header',
						'slug'        => 'gov-brand-header',
						'title'       => 'Header',
						'content'     => self::UNSAFE_CONTENT,
						'area'        => 'header',
					],
				],
			]
		);
		( new Templates() )->register_received_templates();

		$this->assertSame( 200, $response->get_status() );

		$template = \WP_Block_Templates_Registry::get_instance()->get_registered( 'gov-brand//landing' );
		$pattern  = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'gov-brand-cta' );
		$part     = get_block_template( get_stylesheet() . '//gov-brand-header', 'wp_template_part' );
		$this->assertNotNull( $template );
		$this->assertIsArray( $pattern );
		$this->assertInstanceOf( \WP_Block_Template::class, $part );
		$this->assertSame( 'header', $part->area );

		$stored = (string) wp_json_encode(
			[
				get_option( Templates::TEMPLATES_OPTION_KEY ),
				get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY ),
			]
		);
		foreach ( [ $stored, $template->title . $template->description . $template->content, $pattern['content'], $part->content ] as $html ) {
			$this->assertStringNotContainsString( '<script', $html );
			$this->assertStringNotContainsString( 'onerror', $html );
		}
		$this->assertStringContainsString( '<p>Hello<img src="x"', $template->content );
	}

	/**
	 * A template part is a post as soon as it arrives, and a page load after that writes nothing.
	 */
	public function test_template_parts_are_saved_when_they_arrive_and_page_loads_write_nothing(): void {
		$this->send_templates( [ $this->template( 'landing', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ) ], [ $this->template_part( 'header' ) ] );

		$part = get_block_template( get_stylesheet() . '//gov-brand-header', 'wp_template_part' );
		$this->assertInstanceOf( \WP_Block_Template::class, $part );
		$this->assertStringContainsString( 'Header', $part->content );

		$posts = $this->get_posts_table();
		do_action( 'after_setup_theme' );
		( new Templates() )->register_received_templates();
		( new Patterns() )->register_received_patterns();

		$this->assertSame( $posts, $this->get_posts_table() );
	}

	/**
	 * A template without content is refused before anything is stored.
	 */
	public function test_save_templates_refuses_a_template_without_content(): void {
		$response = $this->send(
			'POST',
			'templates',
			[
				'templates' => [
					[
						'id'          => 'gov-brand//landing',
						'original_id' => 'theme//landing',
					],
				],
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( Templates::TEMPLATES_OPTION_KEY ) );
	}

	/**
	 * A brand site with no templates lists none, and removing one it no longer has is fine.
	 */
	public function test_a_new_brand_site_lists_no_templates_and_removes_any(): void {
		$listed  = $this->send( 'GET', 'templates', null );
		$removed = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//gone' ] ] );

		$this->assertSame( 200, $listed->get_status() );
		$this->assertSame( [], $listed->get_data() );
		$this->assertSame( 200, $removed->get_status() );
		$this->assertSame( [], get_option( Templates::TEMPLATES_OPTION_KEY ) );
	}

	/**
	 * Removing a template also removes the template parts, patterns and synced patterns no other template uses, and never this site's own synced pattern, even one with the same slug as a copy.
	 */
	public function test_delete_templates_removes_what_no_other_template_uses(): void {
		$own = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_name'    => 'gov-brand-hero',
				'post_content' => 'Our own hero',
			]
		);
		$this->send_templates(
			[
				$this->template( 'landing', '<!-- wp:template-part {"slug":"gov-brand-header"} /--><!-- wp:pattern {"slug":"gov-brand-cta"} /-->' ),
				$this->template( 'other', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ),
			],
			[ $this->template_part( 'header' ) ],
			[
				'theme/cta' => [
					'name'    => 'gov-brand-cta',
					'title'   => 'CTA',
					'content' => '<!-- wp:block {"ref":12} /-->',
				],
			],
			[ $this->synced_pattern( 12, 'hero', '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' ) ]
		);
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );
		$this->assertNotSame( $own, $post_ids[12] );
		// As the next request does when it starts.
		( new Templates() )->register_received_templates();

		$first = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//landing' ] ] );

		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( [ 'theme//other' ], array_keys( get_option( Templates::TEMPLATES_OPTION_KEY ) ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $post_ids['theme//header'] ), 'The other template still uses the template part.' );
		$this->assertNull( get_post( $post_ids[12] ) );
		$this->assertSame( [], get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY ) );

		$second = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//other' ] ] );

		$this->assertSame( 200, $second->get_status() );
		$this->assertNull( get_post( $post_ids['theme//header'] ) );
		$this->assertSame( [], get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( 'Our own hero', get_post( $own )->post_content );
	}

	/**
	 * Removing a template keeps the copy of a synced pattern it used when a pattern from the Pattern Library still uses it.
	 */
	public function test_delete_templates_keeps_the_synced_patterns_that_shared_patterns_use(): void {
		$this->send(
			'POST',
			'patterns',
			[
				'patterns'        => [
					[
						'name'    => 'user-pattern-7',
						'title'   => 'Hero',
						'content' => '<!-- wp:block {"ref":12} /-->',
					],
				],
				'synced_patterns' => [ $this->synced_pattern( 12, 'ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ) ],
			]
		);
		$this->send_templates( [ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ], [], [], [ $this->synced_pattern( 12, 'ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ) ] );
		$copy = get_option( Templates::POST_IDS_OPTION_KEY )[12];

		$response = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//landing' ] ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertInstanceOf( \WP_Post::class, get_post( $copy ) );
		$this->assertSame( [ 12 => $copy ], get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( '<!-- wp:block {"ref":' . $copy . '} /-->', get_option( Patterns::PATTERNS_OPTION_KEY )['user-pattern-7']['content'] );
	}

	/**
	 * Removing patterns from the Pattern Library also deletes the copies of the synced patterns only they used.
	 */
	public function test_delete_patterns_deletes_the_synced_patterns_only_they_used(): void {
		$this->send(
			'POST',
			'patterns',
			[
				'synced_patterns' => [
					$this->synced_pattern( 12, 'ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ),
					$this->synced_pattern( 13, 'quote', '<!-- wp:paragraph --><p>Great work</p><!-- /wp:paragraph -->' ),
				],
				'patterns'        => [
					[
						'name'    => 'user-pattern-7',
						'title'   => 'Hero',
						'content' => '<!-- wp:block {"ref":12} /-->',
					],
					[
						'name'    => 'user-pattern-8',
						'title'   => 'Testimonial',
						'content' => '<!-- wp:block {"ref":13} /-->',
					],
				],
			]
		);
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );

		$response = $this->send( 'DELETE', 'patterns', [ 'names' => [ 'user-pattern-7' ] ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $post_ids[12] ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $post_ids[13] ), 'The other pattern still uses it.' );
		$this->assertSame( [ 13 => $post_ids[13] ], get_option( Templates::POST_IDS_OPTION_KEY ) );
	}

	/**
	 * Removing a pattern from the Pattern Library keeps the copy of a synced pattern it used when a template still uses it, here through the template's own pattern.
	 */
	public function test_delete_patterns_keeps_the_synced_patterns_templates_use(): void {
		$this->send(
			'POST',
			'patterns',
			[
				'patterns'        => [
					[
						'name'    => 'user-pattern-7',
						'title'   => 'Hero',
						'content' => '<!-- wp:block {"ref":12} /-->',
					],
				],
				'synced_patterns' => [ $this->synced_pattern( 12, 'ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ) ],
			]
		);
		$this->send_templates(
			[ $this->template( 'landing', '<!-- wp:pattern {"slug":"gov-brand-cta"} /-->' ) ],
			[],
			[
				'theme/cta' => [
					'name'    => 'gov-brand-cta',
					'title'   => 'CTA',
					'content' => '<!-- wp:block {"ref":12} /-->',
				],
			],
			[ $this->synced_pattern( 12, 'ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ) ]
		);
		$copy = get_option( Templates::POST_IDS_OPTION_KEY )[12];
		// As the next request does when it starts.
		( new Templates() )->register_received_templates();

		$response = $this->send( 'DELETE', 'patterns', [ 'names' => [ 'user-pattern-7' ] ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertInstanceOf( \WP_Post::class, get_post( $copy ) );
		$this->assertSame( [ 12 => $copy ], get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( '<!-- wp:block {"ref":' . $copy . '} /-->', get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY )['theme/cta']['content'] );
	}

	/**
	 * The Pattern Library lists and removes only the patterns it sent, so removing patterns there never takes one out of a template.
	 */
	public function test_the_pattern_library_only_sees_the_patterns_it_sent(): void {
		$this->send(
			'POST',
			'patterns',
			[
				'patterns' => [
					[
						'name'    => 'user-pattern-7',
						'title'   => 'Hero',
						'content' => '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->',
					],
				],
			]
		);
		$this->send_templates(
			[ $this->template( 'landing', '<!-- wp:pattern {"slug":"gov-brand-cta"} /-->' ) ],
			[],
			[
				'theme/cta' => [
					'name'    => 'gov-brand-cta',
					'title'   => 'CTA',
					'content' => '<!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->',
				],
			]
		);

		$listed  = $this->send( 'GET', 'patterns', null );
		$removed = $this->send( 'DELETE', 'patterns', [ 'names' => [ 'user-pattern-7', 'gov-brand-cta' ] ] );
		( new Patterns() )->register_received_patterns();
		( new Templates() )->register_received_templates();

		$this->assertSame( [ 'user-pattern-7' ], array_column( $listed->get_data(), 'name' ) );
		$this->assertSame( 200, $removed->get_status() );
		$this->assertSame( [], get_option( Patterns::PATTERNS_OPTION_KEY ) );
		$this->assertFalse( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'user-pattern-7' ) );
		$this->assertStringContainsString( 'Talk to us', \WP_Block_Patterns_Registry::get_instance()->get_registered( 'gov-brand-cta' )['content'] );
	}

	/**
	 * When a template part only the removed template used can't be deleted, removing says so and what to do about it, and this site still knows the post is its copy.
	 */
	public function test_delete_templates_reports_a_post_it_could_not_delete(): void {
		$this->send_templates( [ $this->template( 'landing', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ) ], [ $this->template_part( 'header' ) ] );
		add_filter( 'pre_delete_post', '__return_false' );

		$response = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//landing' ] ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'onedesign_post_not_deleted', $response->as_error()->get_error_code() );
		$this->assertSame( 'Templates removed. The brand site couldn\'t delete "Header" along with them. Nothing else uses it, so you can delete that synced pattern or template part there yourself.', $response->as_error()->get_error_message() );
		$this->assertSame( [], get_option( Templates::TEMPLATES_OPTION_KEY ) );
		$this->assertArrayHasKey( 'theme//header', get_option( Templates::POST_IDS_OPTION_KEY ) );
	}

	/**
	 * When a synced pattern only the removed pattern used can't be deleted, removing says the patterns are gone and what to do about the synced pattern.
	 */
	public function test_delete_patterns_reports_a_post_it_could_not_delete(): void {
		$this->send(
			'POST',
			'patterns',
			[
				'patterns'        => [
					[
						'name'    => 'user-pattern-7',
						'title'   => 'Hero',
						'content' => '<!-- wp:block {"ref":12} /-->',
					],
				],
				'synced_patterns' => [ $this->synced_pattern( 12, 'ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ) ],
			]
		);
		add_filter( 'pre_delete_post', '__return_false' );

		$response = $this->send( 'DELETE', 'patterns', [ 'names' => [ 'user-pattern-7' ] ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Patterns removed. The brand site couldn\'t delete "Ready" along with them. Nothing else uses it, so you can delete that synced pattern or template part there yourself.', $response->as_error()->get_error_message() );
		$this->assertSame( [], get_option( Patterns::PATTERNS_OPTION_KEY ) );
		$this->assertArrayHasKey( 12, get_option( Templates::POST_IDS_OPTION_KEY ) );
	}

	/**
	 * A synced pattern without a slug is a bad request, so nothing it came with is saved.
	 */
	public function test_save_templates_refuses_a_synced_pattern_without_a_slug(): void {
		$response = $this->send(
			'POST',
			'templates',
			[
				'templates'       => [ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ],
				'synced_patterns' => [ [ 'slug' => '' ] + $this->synced_pattern( 12, 'hero', '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' ) ],
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
		$this->assertFalse( get_option( Templates::TEMPLATES_OPTION_KEY ) );
		$this->assertFalse( get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( 0, (int) wp_count_posts( 'wp_block' )->publish );
	}

	/**
	 * A synced pattern is created without its scripts.
	 */
	public function test_save_synced_patterns_creates_them_without_scripts(): void {
		$this->send_templates( [ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ], [], [], [ $this->synced_pattern( 12, 'cta', self::UNSAFE_CONTENT ) ] );

		$post = get_post( get_option( Templates::POST_IDS_OPTION_KEY )[12] );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertSame( 'wp_block', $post->post_type );
		$this->assertStringContainsString( '<p>Hello', $post->post_content );
		$this->assertStringNotContainsString( '<script', $post->post_content );
		$this->assertStringNotContainsString( 'onerror', $post->post_content );
	}

	/**
	 * Synced patterns that nest each other point at this site's copies, and one this site has no copy of is dropped.
	 */
	public function test_save_synced_patterns_points_them_at_this_site_copies(): void {
		$this->send_templates(
			[ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ],
			[],
			[],
			[
				$this->synced_pattern( 12, 'hero', '<!-- wp:block {"ref":13,"metadata":{"name":"CTA"}} /--><!-- wp:block {"ref":99} /-->' ),
				$this->synced_pattern( 13, 'cta', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' ),
			]
		);

		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );
		$this->assertSame( '<!-- wp:block {"ref":' . $post_ids[13] . ',"metadata":{"name":"CTA"}} /--><!-- wp:block /-->', get_post( $post_ids[12] )->post_content );
	}

	/**
	 * Sending a synced pattern again updates this site's copy instead of adding one.
	 */
	public function test_save_synced_patterns_again_updates_the_copy(): void {
		$this->send_templates( [ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ], [], [], [ $this->synced_pattern( 12, 'hero', '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' ) ] );
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );

		$this->send_templates( [ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ], [], [], [ $this->synced_pattern( 12, 'hero', '<!-- wp:paragraph --><p>Hero, updated</p><!-- /wp:paragraph -->' ) ] );

		$this->assertSame( $post_ids, get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( 1, (int) wp_count_posts( 'wp_block' )->publish );
		$this->assertStringContainsString( 'Hero, updated', get_post( $post_ids[12] )->post_content );
	}

	/**
	 * A template the governing site sends.
	 *
	 * @param string $slug    The slug on the governing site.
	 * @param string $content The content.
	 *
	 * @return array<string,string>
	 */
	private function template( string $slug, string $content ): array {
		return [
			'id'          => 'gov-brand//' . $slug,
			'original_id' => 'theme//' . $slug,
			'title'       => ucfirst( $slug ),
			'content'     => $content,
		];
	}

	/**
	 * A template part the governing site sends.
	 *
	 * @param string $slug The slug on the governing site.
	 *
	 * @return array<string,string>
	 */
	private function template_part( string $slug ): array {
		return [
			'original_id' => 'theme//' . $slug,
			'slug'        => 'gov-brand-' . $slug,
			'title'       => ucfirst( $slug ),
			'content'     => '<!-- wp:paragraph --><p>' . ucfirst( $slug ) . '</p><!-- /wp:paragraph -->',
			'area'        => $slug,
		];
	}

	/**
	 * A synced pattern the governing site sends.
	 *
	 * @param int    $original_id Its id on the governing site.
	 * @param string $slug        The slug on the governing site.
	 * @param string $content     The content.
	 *
	 * @return array<string,int|string>
	 */
	private function synced_pattern( int $original_id, string $slug, string $content ): array {
		return [
			'original_id' => $original_id,
			'slug'        => 'gov-brand-' . $slug,
			'title'       => ucfirst( $slug ),
			'content'     => $content,
		];
	}

	/**
	 * Sends templates, with what they use, from the governing site.
	 *
	 * @param array<int,array<string,mixed>>    $templates       The templates.
	 * @param array<int,array<string,mixed>>    $template_parts  The template parts.
	 * @param array<string,array<string,mixed>> $patterns        The patterns, keyed by the governing site's name for each.
	 * @param array<int,array<string,mixed>>    $synced_patterns The synced patterns.
	 */
	private function send_templates( array $templates, array $template_parts = [], array $patterns = [], array $synced_patterns = [] ): void {
		$response = $this->send(
			'POST',
			'templates',
			[
				'templates'       => $templates,
				'patterns'        => $patterns,
				'template_parts'  => $template_parts,
				'synced_patterns' => $synced_patterns,
			]
		);

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Gets every post's id, type and last change.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_posts_table(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The test reads the table itself.
		return $wpdb->get_results( "SELECT ID, post_type, post_name, post_modified FROM {$wpdb->posts} ORDER BY ID", ARRAY_A );
	}

	/**
	 * Dispatches a request from the governing site, with this site's key.
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route, relative to the OneDesign namespace.
	 * @param ?array<string,mixed> $body   The JSON body, if any.
	 */
	private function send( string $method, string $route, ?array $body ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/onedesign/v1/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-OneDesign-Site-URL', self::GOVERNING_SITE_URL );
		$request->set_header( 'X-OneDesign-Token', Settings::get_api_key() );
		if ( null !== $body ) {
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}
}
