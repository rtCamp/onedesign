<?php
/**
 * Tests for Templates_Controller on a governing site.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Library\Templates;
use OneDesign\Modules\Rest\Remote_Client;
use OneDesign\Modules\Rest\Templates_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Applying a governing site's templates to its brand sites.
 */
#[CoversClass( Templates_Controller::class )]
#[CoversClass( Templates::class )]
#[CoversClass( Remote_Client::class )]
class Templates_Controller_GoverningSiteTest extends TestCase {
	/**
	 * REST server.
	 */
	private ?\WP_REST_Server $server;

	/**
	 * The requests sent to brand sites, as [method, URL, decoded body].
	 *
	 * @var array<int,array{string,string,mixed}>
	 */
	private array $sent = [];

	/**
	 * The id of the template this site has.
	 */
	private string $template_id;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_up_two_brand_sites();

		$post_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_template',
				'post_name'    => 'lab-landing',
				'post_title'   => 'Lab Landing',
				'post_content' => '<!-- wp:paragraph --><p>Landing</p><!-- /wp:paragraph -->',
			]
		);
		wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );
		$this->template_id = get_stylesheet() . '//lab-landing';

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		( new Templates_Controller() )->register_hooks();
		do_action( 'rest_api_init' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'lab/cta' ) ) {
			unregister_block_pattern( 'lab/cta' );
		}
		if ( \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'lab-demo' ) ) {
			unregister_block_pattern_category( 'lab-demo' );
		}
		foreach ( get_option( Templates::TEMPLATES_OPTION_KEY, [] ) as $template ) {
			if ( \WP_Block_Templates_Registry::get_instance()->is_registered( $template['id'] ) ) {
				unregister_block_template( $template['id'] );
			}
		}
		foreach ( get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY, [] ) as $pattern ) {
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern['name'] ) ) {
				unregister_block_pattern( $pattern['name'] );
			}
		}

		parent::tear_down();
	}

	/**
	 * The library lists this site's templates with their title and content as strings, which core's route nests.
	 */
	public function test_get_templates_lists_this_site_templates(): void {
		$templates = $this->dispatch( 'GET', '/templates' )->get_data();
		$template  = current( array_filter( $templates, fn ( $t ): bool => $this->template_id === $t->id ) );

		$this->assertInstanceOf( \WP_Block_Template::class, $template );
		$this->assertSame( 'Lab Landing', $template->title );
		$this->assertStringContainsString( '<p>Landing</p>', $template->content );
	}

	/**
	 * A brand site's templates come back sanitized, and only that brand site is asked, with a URL no cache has an answer for. A template that doesn't match the schema is left out.
	 */
	public function test_lists_a_brand_site_templates_sanitized(): void {
		$this->answer(
			static fn (): array => [
				200,
				[
					[
						'id'          => 'gov-brand-one//lab-landing',
						'original_id' => 'theme//lab-landing',
						'title'       => 'Landing<script>alert(1)</script>',
						'content'     => '<p>Hi<img src="x" onerror="alert(2)"></p>',
					],
					[
						'id'    => 'gov-brand-one//broken',
						'title' => 'Broken',
					],
				],
			]
		);

		$response  = $this->dispatch( 'GET', '/templates', [ 'site' => 'brand-1' ] );
		$templates = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'theme//lab-landing' ], array_column( $templates, 'original_id' ) );
		$this->assertStringNotContainsString( '<script', (string) wp_json_encode( $templates ) );
		$this->assertStringNotContainsString( 'onerror', (string) wp_json_encode( $templates ) );
		$this->assertCount( 1, $this->sent );
		$this->assertMatchesRegularExpression( '#^https://one\.example\.org/wp-json/onedesign/v1/templates\?timestamp=\d+$#', $this->sent[0][1] );
	}

	/**
	 * A brand site that fails passes its error on, with its own message.
	 */
	public function test_listing_a_brand_site_returns_its_error(): void {
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Database error.' ] ] );

		$response = $this->dispatch( 'GET', '/templates', [ 'site' => 'brand-2' ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Database error.', $response->as_error()->get_error_message() );
	}

	/**
	 * Applying sends the one brand site it names only the template fields it reads, with the synced patterns the templates use, in one request.
	 */
	public function test_apply_templates_sends_them_to_the_brand_site(): void {
		$hero = $this->add_synced_pattern( 'lab-hero', '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' );
		$this->set_template_content( '<!-- wp:block {"ref":' . $hero . '} /-->' );
		$this->answer( static fn (): array => [ 200, [ 'message' => 'Templates saved.' ] ] );

		$response = $this->dispatch(
			'POST',
			'/templates',
			[
				'site' => 'brand-2',
				'ids'  => [ $this->template_id ],
			]
		);

		$this->assertSame( [ 'message' => 'The templates were applied to Brand Two.' ], $response->get_data() );
		$this->assertCount( 1, $this->sent );
		[ $method, $url, $body ] = $this->sent[0];
		$this->assertSame( 'POST', $method );
		$this->assertSame( 'https://two.example.org/wp-json/onedesign/v1/templates', $url );
		$this->assertSame( $this->template_id, $body['templates'][0]['original_id'] );
		$this->assertSame( [], array_diff( array_keys( $body['templates'][0] ), [ 'id', 'original_id', 'title', 'description', 'content', 'post_types' ] ) );
		$this->assertSame( [ $hero ], array_column( $body['synced_patterns'], 'original_id' ) );
	}

	/**
	 * A brand site that doesn't take the templates passes its error on, with its own message.
	 */
	public function test_apply_templates_returns_the_brand_site_error(): void {
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Brand Two is down' ] ] );

		$response = $this->dispatch(
			'POST',
			'/templates',
			[
				'site' => 'brand-2',
				'ids'  => [ $this->template_id ],
			]
		);

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Brand Two is down', $response->as_error()->get_error_message() );
	}

	/**
	 * Applying a template this site doesn't have, or to a site that isn't a brand site, is refused before any brand site is contacted.
	 */
	public function test_apply_templates_refuses_bad_requests_without_contacting_brand_sites(): void {
		$this->answer( static fn (): array => [ 200, [] ] );

		$unknown_template = $this->dispatch(
			'POST',
			'/templates',
			[
				'site' => 'brand-1',
				'ids'  => [ 'theme//nope' ],
			]
		);
		$unknown_site     = $this->dispatch(
			'POST',
			'/templates',
			[
				'site' => 'brand-9',
				'ids'  => [ $this->template_id ],
			]
		);

		$this->assertSame( 400, $unknown_template->get_status() );
		$this->assertSame( 'onedesign_templates_not_found', $unknown_template->as_error()->get_error_code() );
		$this->assertSame( 404, $unknown_site->get_status() );
		$this->assertSame( [], $this->sent );
	}

	/**
	 * Re-syncing a brand site reports the brand site's failure instead of a success.
	 */
	public function test_resync_templates_returns_the_brand_site_error(): void {
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Brand One is down' ] ] );

		$response = $this->dispatch( 'PUT', '/templates', [ 'site' => 'brand-1' ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Brand One is down', $response->as_error()->get_error_message() );
	}

	/**
	 * A brand site gets its own copy of each template part, pattern and synced pattern a template uses, at any depth and named for it, and the template points at those copies with its block attributes intact. Synced patterns that aren't published, or have a password, stay here.
	 */
	public function test_apply_templates_gives_the_brand_site_copies_of_what_they_use(): void {
		$this->connect_brand_one();
		$this->add_template_part( 'lab-header', '<!-- wp:site-title /-->' );
		$this->add_template_part( 'lab-footer', '<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->' );
		register_block_pattern(
			'lab/cta',
			[
				'title'   => 'CTA',
				'content' => '<!-- wp:template-part {"slug":"lab-footer"} /--><!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->',
			]
		);
		$nested    = $this->add_synced_pattern( 'lab-ready', '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->' );
		$hero      = $this->add_synced_pattern( 'lab-hero', '<!-- wp:heading {"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}} --><h2 class="wp-block-heading">Hero</h2><!-- /wp:heading --><!-- wp:block {"ref":' . $nested . '} /-->' );
		$draft     = $this->add_synced_pattern( 'lab-draft', '<!-- wp:paragraph --><p>Draft</p><!-- /wp:paragraph -->', [ 'post_status' => 'draft' ] );
		$password  = $this->add_synced_pattern( 'lab-secret', '<!-- wp:paragraph --><p>Secret</p><!-- /wp:paragraph -->', [ 'post_password' => 'pass' ] );
		$overrides = '"metadata":{"name":"Hero"},"content":{"Title":{"content":"\u003cb\u003eHi\u003c/b\u003e \u002d\u002d you"}}';
		$this->set_template_content(
			'<!-- wp:template-part {"slug":"lab-header","theme":"' . get_stylesheet() . '","tagName":"header","style":{"spacing":{"padding":{"top":"1rem"}}}} /-->'
			. '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'
			. '<!-- wp:pattern {"slug":"lab/cta"} /-->'
			. '<!-- wp:block {"ref":' . $hero . ',' . $overrides . '} /-->'
			. '</div><!-- /wp:group -->'
			. '<!-- wp:block {"ref":' . $draft . '} /--><!-- wp:block {"ref":' . $password . '} /-->'
		);

		$this->apply( [ $this->template_id ], 'brand-1' );

		// Brand One created a post for each synced pattern and template part it got, and only those.
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );
		$this->assertEqualsCanonicalizing( [ $hero, $nested, get_stylesheet() . '//lab-header', get_stylesheet() . '//lab-footer' ], array_keys( $post_ids ) );
		$brand_hero   = get_post( $post_ids[ $hero ] );
		$brand_nested = get_post( $post_ids[ $nested ] );
		$this->assertSame( $this->brand_slug( 'lab-hero' ), $brand_hero->post_name );
		$this->assertStringContainsString( '<!-- wp:block {"ref":' . $brand_nested->ID . '} /-->', $brand_hero->post_content );
		$this->assertStringContainsString( '"metadata":{"name":"Title","bindings":{"__default":{"source":"core/pattern-overrides"}}}', $brand_hero->post_content );
		$this->assertStringContainsString( 'Ready to talk?', $brand_nested->post_content );
		$this->assertSame( $this->brand_slug( 'lab-footer' ), get_post( $post_ids[ get_stylesheet() . '//lab-footer' ] )->post_name );

		$template = get_option( Templates::TEMPLATES_OPTION_KEY )[ $this->template_id ];
		$this->assertSame( 'test-blog-brand-one//' . sanitize_title( $this->template_id ), $template['id'] );
		$this->assertStringContainsString( '<!-- wp:template-part {"slug":"' . $this->brand_slug( 'lab-header' ) . '","tagName":"header","style":{"spacing":{"padding":{"top":"1rem"}}}} /-->', $template['content'] );
		$this->assertStringContainsString( '<!-- wp:pattern {"slug":"' . $this->brand_slug( 'lab-cta' ) . '"} /-->', $template['content'] );
		$this->assertStringContainsString( '<!-- wp:block {"ref":' . $brand_hero->ID . ',' . $overrides . '} /-->', $template['content'] );
		$this->assertSame( 2, substr_count( $template['content'], '<!-- wp:block /-->' ), 'The unpublished and password-protected synced patterns are dropped.' );

		$pattern = get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY )['lab/cta'];
		$this->assertSame( $this->brand_slug( 'lab-cta' ), $pattern['name'] );
		$this->assertStringContainsString( '<!-- wp:template-part {"slug":"' . $this->brand_slug( 'lab-footer' ) . '"} /-->', $pattern['content'] );

		// Only this site's own unpublished and password-protected synced patterns have their content.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- `suppress_filters` is false.
		$synced_patterns = get_posts(
			[
				'post_type'        => 'wp_block',
				'post_status'      => 'any',
				'numberposts'      => 100,
				'suppress_filters' => false,
			]
		);
		$this->assertEqualsCanonicalizing( [ $draft, $password ], array_keys( preg_grep( '/<p>(Draft|Secret)<\/p>/', wp_list_pluck( $synced_patterns, 'post_content', 'ID' ) ) ) );
	}

	/**
	 * A brand site names the pattern categories of the patterns a template uses as this site names them.
	 */
	public function test_apply_templates_names_pattern_categories_as_this_site_does(): void {
		$this->connect_brand_one();
		register_block_pattern_category( 'lab-demo', [ 'label' => 'Lab Demo' ] );
		register_block_pattern(
			'lab/cta',
			[
				'title'      => 'CTA',
				'content'    => '<!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->',
				'categories' => [ 'lab-demo' ],
			]
		);
		$this->set_template_content( '<!-- wp:pattern {"slug":"lab/cta"} /-->' );

		$this->apply( [ $this->template_id ], 'brand-1' );

		$term = get_term_by( 'slug', 'lab-demo', 'wp_pattern_category' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertSame( 'Lab Demo', $term->name );
	}

	/**
	 * A template part without an area, which core leaves null, reaches the brand site as uncategorized.
	 */
	public function test_apply_templates_sends_a_template_part_without_an_area_as_uncategorized(): void {
		$this->connect_brand_one();
		$this->add_template_part( 'lab-header', '<!-- wp:site-title /-->', null );
		$this->set_template_content( '<!-- wp:template-part {"slug":"lab-header"} /-->' );

		$this->apply( [ $this->template_id ], 'brand-1' );

		$post_id = get_option( Templates::POST_IDS_OPTION_KEY )[ get_stylesheet() . '//lab-header' ];
		$this->assertSame( [ 'uncategorized' ], wp_get_object_terms( $post_id, 'wp_template_part_area', [ 'fields' => 'slugs' ] ) );
	}

	/**
	 * Re-syncing a brand site works out which templates it has, and brings those to their current version without adding the ones other brand sites have.
	 */
	public function test_resync_templates_brings_only_the_brand_site_templates_up_to_date(): void {
		$this->connect_brand_one();
		$this->answer( static fn (): array => [ 200, [ 'message' => 'Templates saved.' ] ] );
		$other_id = $this->add_template( 'lab-other', '<!-- wp:paragraph --><p>Other</p><!-- /wp:paragraph -->' );
		$this->apply( [ $this->template_id ], 'brand-1' );
		$this->apply( [ $other_id ], 'brand-2' );
		$this->set_template_content( '<!-- wp:paragraph --><p>Landing, updated</p><!-- /wp:paragraph -->' );

		$response = $this->dispatch( 'PUT', '/templates', [ 'site' => 'brand-1' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Brand One now has the latest version of these templates.', $response->get_data()['message'] );
		$templates = get_option( Templates::TEMPLATES_OPTION_KEY );
		$this->assertSame( [ $this->template_id ], array_keys( $templates ) );
		$this->assertStringContainsString( 'Landing, updated', $templates[ $this->template_id ]['content'] );
	}

	/**
	 * When a brand site fails to save the new version, re-syncing says so, and the brand site keeps the templates and template parts it had.
	 */
	public function test_resync_templates_leaves_the_brand_site_as_it_was_when_saving_fails(): void {
		$this->connect_brand_one();
		$this->add_template_part( 'lab-header', '<!-- wp:site-title /-->' );
		$this->set_template_content( '<!-- wp:template-part {"slug":"lab-header"} /--><!-- wp:paragraph --><p>Landing</p><!-- /wp:paragraph -->' );
		$this->apply( [ $this->template_id ], 'brand-1' );
		$before = get_option( Templates::TEMPLATES_OPTION_KEY );
		$this->set_template_content( '<!-- wp:paragraph --><p>Landing, updated</p><!-- /wp:paragraph -->' );
		add_filter(
			'rest_pre_dispatch',
			static fn ( $result, \WP_REST_Server $server, WP_REST_Request $request ) => 'POST' === $request->get_method() && '/onedesign/v1/templates' === $request->get_route() // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
				? new \WP_Error( 'disk_full', 'Brand One is out of space.', [ 'status' => 500 ] )
				: $result,
			10,
			3
		);

		$response = $this->dispatch( 'PUT', '/templates', [ 'site' => 'brand-1' ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Brand One is out of space.', $response->as_error()->get_error_message() );
		$this->assertSame( $before, get_option( Templates::TEMPLATES_OPTION_KEY ) );
		$part = get_post( get_option( Templates::POST_IDS_OPTION_KEY )[ get_stylesheet() . '//lab-header' ] );
		$this->assertSame( $this->brand_slug( 'lab-header' ), $part->post_name );
	}

	/**
	 * Applying templates again after this site is renamed updates the brand site's copies, and the patterns they use, instead of adding more, and they keep the names they got first.
	 */
	public function test_apply_templates_again_after_a_rename_updates_the_brand_site_copies(): void {
		$this->connect_brand_one();
		$this->add_template_part( 'lab-header', '<!-- wp:site-title /-->' );
		$hero = $this->add_synced_pattern( 'lab-hero', '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' );
		register_block_pattern(
			'lab/cta',
			[
				'title'   => 'CTA',
				'content' => '<!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->',
			]
		);
		$this->set_template_content( '<!-- wp:template-part {"slug":"lab-header"} /--><!-- wp:block {"ref":' . $hero . '} /--><!-- wp:pattern {"slug":"lab/cta"} /-->' );
		$this->apply( [ $this->template_id ], 'brand-1' );
		$post_ids = get_option( Templates::POST_IDS_OPTION_KEY );

		update_option( 'blogname', 'Lab Governing' );
		$this->apply( [ $this->template_id ], 'brand-1' );

		$templates = get_option( Templates::TEMPLATES_OPTION_KEY );
		$this->assertCount( 1, $templates );
		$this->assertStringStartsWith( 'lab-governing-brand-one//', $templates[ $this->template_id ]['id'] );
		$this->assertSame( $post_ids, get_option( Templates::POST_IDS_OPTION_KEY ) );
		$this->assertSame( $this->brand_slug( 'lab-header' ), get_post( $post_ids[ get_stylesheet() . '//lab-header' ] )->post_name );
		$this->assertStringContainsString( '"slug":"' . $this->brand_slug( 'lab-header' ) . '"', $templates[ $this->template_id ]['content'] );
		$this->assertStringContainsString( '{"ref":' . $post_ids[ $hero ] . '}', $templates[ $this->template_id ]['content'] );
		$this->assertStringContainsString( '"slug":"' . $this->brand_slug( 'lab-cta' ) . '"', $templates[ $this->template_id ]['content'] );
		$this->assertSame( [ $this->brand_slug( 'lab-cta' ) ], array_column( get_option( Templates::TEMPLATE_PATTERNS_OPTION_KEY ), 'name' ) );
	}

	/**
	 * A brand site registers a template from a site whose name is in a non-Latin script under the id this site gives it, and renders the template part and pattern it uses.
	 */
	public function test_apply_templates_from_a_site_with_a_non_latin_name_registers_and_renders_them_on_the_brand_site(): void {
		$this->connect_brand_one();
		update_option( 'blogname', 'אתר' );
		$this->add_template_part( 'lab-header', '<!-- wp:paragraph --><p>Header</p><!-- /wp:paragraph -->' );
		register_block_pattern(
			'lab/cta',
			[
				'title'   => 'CTA',
				'content' => '<!-- wp:paragraph --><p>Talk to us</p><!-- /wp:paragraph -->',
			]
		);
		$this->set_template_content( '<!-- wp:template-part {"slug":"lab-header"} /--><!-- wp:pattern {"slug":"lab/cta"} /-->' );

		$this->apply( [ $this->template_id ], 'brand-1' );
		add_filter( 'pre_option_' . Settings::OPTION_SITE_TYPE, static fn (): string => Settings::SITE_TYPE_CONSUMER );
		( new Templates() )->register_received_templates();

		$id = Templates::get_brand_slug( $this->template_id, 'Brand One', '//' );
		$this->assertSame( $id, get_option( Templates::TEMPLATES_OPTION_KEY )[ $this->template_id ]['id'] );
		$rendered = do_blocks( \WP_Block_Templates_Registry::get_instance()->get_registered( $id )->content );
		$this->assertStringContainsString( '>Header</p>', $rendered );
		$this->assertStringContainsString( '>Talk to us</p>', $rendered );
	}

	/**
	 * Removing templates from a brand site passes its error on, with its own message.
	 */
	public function test_delete_templates_returns_the_brand_site_error(): void {
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Database error.' ] ] );

		$response = $this->dispatch(
			'DELETE',
			'/templates',
			[
				'site' => 'brand-1',
				'ids'  => [ $this->template_id ],
			]
		);

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Database error.', $response->as_error()->get_error_message() );
	}

	/**
	 * Only administrators can read or change what brand sites have.
	 */
	public function test_routes_need_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->answer( static fn (): array => [ 200, [] ] );

		$change   = [
			'site' => 'brand-1',
			'ids'  => [ $this->template_id ],
		];
		$requests = [
			[ 'GET', [] ],
			[ 'GET', [ 'site' => 'brand-1' ] ],
			[ 'POST', $change ],
			[ 'PUT', [ 'site' => 'brand-1' ] ],
			[ 'DELETE', $change ],
		];
		foreach ( $requests as [ $method, $body ] ) {
			$this->assertSame( 403, $this->dispatch( $method, '/templates', $body )->get_status(), $method . ' ' . wp_json_encode( $body ) );
		}
		$this->assertSame( [], $this->sent );
	}

	/**
	 * Applies templates to a brand site.
	 *
	 * @param string[] $template_ids The templates.
	 * @param string   $site_id      The brand site.
	 */
	private function apply( array $template_ids, string $site_id ): void {
		$response = $this->dispatch(
			'POST',
			'/templates',
			[
				'site' => $site_id,
				'ids'  => $template_ids,
			]
		);

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Gets the name Brand One gets something of this site's under.
	 *
	 * @param string $slug The slug on this site.
	 */
	private function brand_slug( string $slug ): string {
		return 'test-blog-brand-one-' . $slug;
	}

	/**
	 * Changes the content of this site's template.
	 *
	 * @param string $content The new content.
	 */
	private function set_template_content( string $content ): void {
		wp_update_post(
			wp_slash(
				[
					'ID'           => get_block_template( $this->template_id )->wp_id,
					'post_content' => $content,
				]
			)
		);
	}

	/**
	 * Adds a template to this site.
	 *
	 * @param string $slug    The slug.
	 * @param string $content The content.
	 *
	 * @return string The template's id.
	 */
	private function add_template( string $slug, string $content ): string {
		$post_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_template',
				'post_name'    => $slug,
				'post_title'   => $slug,
				'post_content' => $content,
			]
		);
		wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );

		return get_stylesheet() . '//' . $slug;
	}

	/**
	 * Adds a template part to this site's theme.
	 *
	 * @param string      $slug    The slug.
	 * @param string      $content The content.
	 * @param string|null $area    The area, or null for none.
	 */
	private function add_template_part( string $slug, string $content, ?string $area = 'header' ): void {
		$post_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_template_part',
				'post_name'    => $slug,
				'post_title'   => $slug,
				'post_content' => $content,
			]
		);
		wp_set_object_terms( $post_id, get_stylesheet(), 'wp_theme' );
		if ( null !== $area ) {
			wp_set_object_terms( $post_id, $area, 'wp_template_part_area' );
		}
	}

	/**
	 * Adds a synced pattern to this site.
	 *
	 * @param string              $slug    The slug.
	 * @param string              $content The content.
	 * @param array<string,mixed> $fields  Other post fields.
	 *
	 * @return int The synced pattern's id.
	 */
	private function add_synced_pattern( string $slug, string $content, array $fields = [] ): int {
		return self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_name'    => $slug,
				'post_title'   => ucfirst( $slug ),
				'post_content' => wp_slash( $content ),
			] + $fields
		);
	}

	/**
	 * Sets up a governing site with Brand One and Brand Two.
	 *
	 * @param string $brand_one_key Brand One's API key.
	 */
	private function set_up_two_brand_sites( string $brand_one_key = 'key-one' ): void {
		$this->set_up_governing_site(
			[
				[
					'id'      => 'brand-1',
					'name'    => 'Brand One',
					'url'     => 'https://one.example.org',
					'api_key' => $brand_one_key,
				],
				[
					'id'      => 'brand-2',
					'name'    => 'Brand Two',
					'url'     => 'https://two.example.org',
					'api_key' => 'key-two',
				],
			]
		);
	}

	/**
	 * Dispatches a request as the current user.
	 *
	 * @param string              $method The method.
	 * @param string              $route  The route, relative to the OneDesign namespace.
	 * @param array<string,mixed> $body   The JSON body, or the query of a GET request.
	 */
	private function dispatch( string $method, string $route, array $body = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/onedesign/v1' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $body );
		} elseif ( ! empty( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}

	/**
	 * Makes this site answer Brand One's requests with the brand site routes, as if it were Brand One too, so tests can read what Brand One saved.
	 */
	private function connect_brand_one(): void {
		$this->set_up_two_brand_sites( Settings::get_api_key() );
		Settings::set_parent_site_url( home_url() );

		// Brand One's routes share their paths with this site's, so they get a server of their own.
		global $wp_rest_server;
		$brand_server   = new \WP_REST_Server();
		$wp_rest_server = $brand_server;
		$as_brand_site  = static fn (): string => Settings::SITE_TYPE_CONSUMER;
		add_filter( 'pre_option_' . Settings::OPTION_SITE_TYPE, $as_brand_site );
		( new Templates_Controller() )->register_routes();
		remove_filter( 'pre_option_' . Settings::OPTION_SITE_TYPE, $as_brand_site );
		$wp_rest_server = $this->server;

		add_filter(
			'pre_http_request',
			static function ( $preempt, array $args, string $url ) use ( $as_brand_site, $brand_server ) {
				$prefix = 'https://one.example.org/wp-json';
				if ( ! str_starts_with( $url, $prefix ) ) {
					return $preempt;
				}

				$route   = (string) wp_parse_url( $url, PHP_URL_PATH );
				$request = new WP_REST_Request( $args['method'], substr( $route, strlen( '/wp-json' ) ) );
				parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
				$request->set_query_params( $query );
				$request->set_headers( $args['headers'] );
				$request->set_body( (string) ( $args['body'] ?? '' ) );

				add_filter( 'pre_option_' . Settings::OPTION_SITE_TYPE, $as_brand_site );
				$response = $brand_server->dispatch( $request );
				remove_filter( 'pre_option_' . Settings::OPTION_SITE_TYPE, $as_brand_site );

				return [
					'response' => [
						'code'    => $response->get_status(),
						'message' => '',
					],
					'body'     => (string) wp_json_encode( $response->get_data() ),
					'headers'  => [],
					'cookies'  => [],
				];
			},
			10,
			3
		);
	}

	/**
	 * Answers requests to brand sites that nothing answered yet, and records them.
	 *
	 * @param callable $answer Gives the status and JSON body for a URL.
	 * @phpstan-param callable(string):array{int,mixed} $answer
	 */
	private function answer( callable $answer ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, array $args, string $url ) use ( $answer ): array {
				if ( false !== $preempt ) {
					return $preempt;
				}

				$this->sent[]    = [ $args['method'], $url, json_decode( (string) ( $args['body'] ?? '' ), true ) ];
				[ $code, $body ] = $answer( $url );

				return [
					'response' => [
						'code'    => $code,
						'message' => '',
					],
					'body'     => (string) wp_json_encode( $body ),
					'headers'  => [],
					'cookies'  => [],
				];
			},
			10,
			3
		);
	}
}
