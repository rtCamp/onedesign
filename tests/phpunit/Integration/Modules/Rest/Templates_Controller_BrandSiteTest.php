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
	 * The content of this site's own header.
	 */
	private const OWN_HEADER = '<!-- wp:paragraph --><p>Our own header</p><!-- /wp:paragraph -->';

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

		$this->unregister_received();

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
	 * When this site has a template part with the slug a template part it's sent would get, the template, its pattern and the template part that nests it all render the one it was sent, and this site's own stays as it was.
	 */
	public function test_a_template_part_whose_slug_this_site_has_renders_where_it_is_used(): void {
		$own = $this->create_own_header();

		$this->send_landing_template( 'Governing header' );

		$rendered = $this->render_template( 'gov-brand//landing' );
		$this->assertSame( 3, substr_count( $rendered, '>Governing header</p>' ), 'The template, the footer and the pattern each render it.' );
		$this->assertStringNotContainsString( 'Our own header', $rendered );
		$this->assertSame( 'gov-brand-header', get_post( $own )->post_name );
		$this->assertSame( self::OWN_HEADER, get_post( $own )->post_content );
	}

	/**
	 * A template renders this site's copy of a synced pattern sent with it, and when this site has a template part with the slug a template part it's sent would get, the synced pattern that nests it renders the one it was sent.
	 */
	public function test_a_synced_pattern_renders_a_template_part_saved_under_another_slug(): void {
		$this->create_own_header();

		$this->send_templates(
			[ $this->template( 'landing', '<!-- wp:block {"ref":12} /-->' ) ],
			[ $this->template_part( 'header', '<!-- wp:paragraph --><p>Governing header</p><!-- /wp:paragraph -->' ) ],
			[],
			[ $this->synced_pattern( 12, 'hero', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ) ]
		);

		$rendered = $this->render_template( 'gov-brand//landing' );
		$this->assertStringContainsString( '>Governing header</p>', $rendered );
		$this->assertStringNotContainsString( 'Our own header', $rendered );
	}

	/**
	 * A template part sent later under the name an earlier one got here leaves the synced pattern and template part that nest the earlier one rendering it.
	 */
	public function test_a_template_part_sent_under_a_name_an_earlier_one_got_here_leaves_what_nests_that_one(): void {
		$this->create_own_header();
		$this->send_templates(
			[ $this->template( 'landing', '<!-- wp:block {"ref":12} /--><!-- wp:template-part {"slug":"gov-brand-footer"} /-->' ) ],
			[
				$this->template_part( 'footer', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ),
				$this->template_part( 'header', '<!-- wp:paragraph --><p>Governing header</p><!-- /wp:paragraph -->' ),
			],
			[],
			[ $this->synced_pattern( 12, 'hero', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ) ]
		);
		$kept = get_post( get_option( Templates::POST_IDS_OPTION_KEY )['theme//header'] )->post_name;

		$this->send_templates(
			[ $this->template( 'other', '<!-- wp:template-part {"slug":"' . $kept . '"} /-->' ) ],
			[
				[
					'original_id' => 'theme//second',
					'slug'        => $kept,
					'title'       => 'Second',
					'content'     => '<!-- wp:paragraph --><p>Second header</p><!-- /wp:paragraph -->',
					'area'        => 'header',
				],
			]
		);

		$rendered = $this->render_template( 'gov-brand//landing' );
		$this->assertSame( 2, substr_count( $rendered, '>Governing header</p>' ), 'The synced pattern and the footer each render it.' );
		$this->assertStringNotContainsString( 'Second header', $rendered );
	}

	/**
	 * Sending an edited template part again changes what the template renders, even when this site has a template part with its slug.
	 */
	public function test_sending_an_edited_template_part_again_changes_what_renders(): void {
		$this->create_own_header();
		$this->send_landing_template( 'Governing header' );
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );

		$this->send_landing_template( 'Governing header v2' );

		$this->assertSame( $post_ids, get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( 3, substr_count( $this->render_template( 'gov-brand//landing' ), '>Governing header v2</p>' ) );
	}

	/**
	 * Removing a template deletes the template parts this site saved for it, including one it saved under another slug, and this site's own template part with that slug stays.
	 */
	public function test_delete_templates_deletes_a_template_part_saved_under_another_slug(): void {
		$own = $this->create_own_header();
		$this->send_landing_template( 'Governing header' );
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );

		$response = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//landing' ] ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $post_ids['theme//header'] ) );
		$this->assertNull( get_post( $post_ids['theme//footer'] ) );
		$this->assertSame( [], get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( self::OWN_HEADER, get_post( $own )->post_content );
	}

	/**
	 * Sending one template again after either site is renamed leaves the template part and pattern it shares with another template under the names they got here, so the other template still renders them, and removing both templates deletes them.
	 */
	public function test_sending_a_template_again_under_other_site_names_keeps_what_it_shares_rendering(): void {
		$this->send_templates_with_a_shared_header( 'gov-brand', 'landing', 'other' );
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );

		$this->send_templates_with_a_shared_header( 'acme-brand', 'other' );

		$this->assertSame( $post_ids, get_option( Templates::POST_IDS_OPTION_KEY ) );
		foreach ( [ 'gov-brand//landing', 'acme-brand//other' ] as $template ) {
			$rendered = $this->render_template( $template );
			$this->assertSame( 2, substr_count( $rendered, '>Governing header</p>' ), 'The template and its pattern each render it.' );
			$this->assertStringContainsString( '>Talk to us</p>', $rendered );
		}

		$first  = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//landing' ] ] );
		$kept   = get_post( $post_ids['theme//header'] );
		$second = $this->send( 'DELETE', 'templates', [ 'ids' => [ 'theme//other' ] ] );

		$this->assertSame( 200, $first->get_status() );
		$this->assertInstanceOf( \WP_Post::class, $kept, 'The other template still uses it.' );
		$this->assertSame( 200, $second->get_status() );
		$this->assertNull( get_post( $post_ids['theme//header'] ) );
		$this->assertSame( [], get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( [], get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY ) );
	}

	/**
	 * Sending one template again after this site deletes its own template part, whose slug the one it was sent would have had, leaves that template part under the slug it got, so the other template still renders it.
	 */
	public function test_sending_a_template_again_after_the_own_template_part_is_deleted_keeps_what_it_shares_rendering(): void {
		$own = $this->create_own_header();
		$this->send_templates_with_a_shared_header( 'gov-brand', 'landing', 'other' );
		wp_delete_post( $own, true );

		$this->send_templates_with_a_shared_header( 'gov-brand', 'other' );

		foreach ( [ 'gov-brand//landing', 'gov-brand//other' ] as $template ) {
			$this->assertSame( 2, substr_count( $this->render_template( $template ), '>Governing header</p>' ), 'The template and its pattern each render it.' );
		}
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
	 * @param string $sites   What the sites' names start each name with.
	 *
	 * @return array<string,string>
	 */
	private function template( string $slug, string $content, string $sites = 'gov-brand' ): array {
		return [
			'id'          => $sites . '//' . $slug,
			'original_id' => 'theme//' . $slug,
			'title'       => ucfirst( $slug ),
			'content'     => $content,
		];
	}

	/**
	 * A template part the governing site sends.
	 *
	 * @param string  $slug    The slug on the governing site.
	 * @param ?string $content The content, which is a paragraph with its name if there's none.
	 * @param string  $sites   What the sites' names start each name with.
	 *
	 * @return array<string,string>
	 */
	private function template_part( string $slug, ?string $content = null, string $sites = 'gov-brand' ): array {
		return [
			'original_id' => 'theme//' . $slug,
			'slug'        => $sites . '-' . $slug,
			'title'       => ucfirst( $slug ),
			'content'     => $content ?? '<!-- wp:paragraph --><p>' . ucfirst( $slug ) . '</p><!-- /wp:paragraph -->',
			'area'        => $slug,
		];
	}

	/**
	 * Sends a template that uses a header directly, through a footer and through a pattern.
	 *
	 * @param string $header What the header says.
	 */
	private function send_landing_template( string $header ): void {
		$this->send_templates(
			[ $this->template( 'landing', '<!-- wp:template-part {"slug":"gov-brand-header"} /--><!-- wp:template-part {"slug":"gov-brand-footer"} /--><!-- wp:pattern {"slug":"gov-brand-cta"} /-->' ) ],
			[
				// Before the header it nests, so this site saves it first.
				$this->template_part( 'footer', '<!-- wp:template-part {"slug":"gov-brand-header"} /-->' ),
				$this->template_part( 'header', '<!-- wp:paragraph --><p>' . $header . '</p><!-- /wp:paragraph -->' ),
			],
			[
				'theme/cta' => [
					'name'    => 'gov-brand-cta',
					'title'   => 'CTA',
					'content' => '<!-- wp:template-part {"slug":"gov-brand-header"} /-->',
				],
			]
		);
	}

	/**
	 * Sends templates that each use a header directly and through a pattern, with the names the sites' names give them.
	 *
	 * @param string $sites What the sites' names start each name with.
	 * @param string ...$slugs The templates' slugs on the governing site.
	 */
	private function send_templates_with_a_shared_header( string $sites, string ...$slugs ): void {
		$header = '<!-- wp:template-part {"slug":"' . $sites . '-header"} /-->';

		$this->send_templates(
			array_map( fn ( string $slug ): array => $this->template( $slug, $header . '<!-- wp:pattern {"slug":"' . $sites . '-cta"} /-->', $sites ), $slugs ),
			[ $this->template_part( 'header', '<!-- wp:paragraph --><p>Governing header</p><!-- /wp:paragraph -->', $sites ) ],
			[
				'theme/cta' => [
					'name'    => $sites . '-cta',
					'title'   => 'CTA',
					'content' => '<!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->' . $header,
				],
			]
		);
	}

	/**
	 * Creates this site's own header, with the slug the governing site's header gets here.
	 *
	 * @return int The post's id.
	 */
	private function create_own_header(): int {
		$post_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_template_part',
				'post_name'    => 'gov-brand-header',
				'post_title'   => 'Our own header',
				'post_content' => self::OWN_HEADER,
			]
		);
		wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );

		return $post_id;
	}

	/**
	 * Registers the templates and patterns this site received, as a page load does, and renders a template.
	 *
	 * @param string $id The template's id.
	 */
	private function render_template( string $id ): string {
		$this->unregister_received();
		( new Templates() )->register_received_templates();
		( new Patterns() )->register_received_patterns();

		return do_blocks( \WP_Block_Templates_Registry::get_instance()->get_registered( $id )->content );
	}

	/**
	 * Unregisters the templates and patterns the tests send.
	 */
	private function unregister_received(): void {
		foreach ( [ 'gov-brand//landing', 'gov-brand//other', 'acme-brand//other' ] as $template ) {
			if ( \WP_Block_Templates_Registry::get_instance()->is_registered( $template ) ) {
				unregister_block_template( $template );
			}
		}
		foreach ( [ 'gov-brand-cta', 'acme-brand-cta', 'user-pattern-7' ] as $pattern ) {
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern ) ) {
				unregister_block_pattern( $pattern );
			}
		}
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
