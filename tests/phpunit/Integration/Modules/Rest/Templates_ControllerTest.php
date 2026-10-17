<?php
/**
 * Templates_Controller unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Rest\Templates_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for template sharing between governing and brand sites.
 */
#[CoversClass( Templates_Controller::class )]
final class Templates_ControllerTest extends TestCase {
	private const TEMPLATE = 'onedesign-test//shared-single';
	private const PATTERN  = 'onedesign-test/banner';

	/**
	 * Controller under test.
	 */
	private Templates_Controller $controller;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->controller = new Templates_Controller();
		$this->initialize_rest_server( [ $this->controller ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		if ( \WP_Block_Templates_Registry::get_instance()->is_registered( self::TEMPLATE ) ) {
			unregister_block_template( self::TEMPLATE );
		}
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( self::PATTERN ) ) {
			unregister_block_pattern( self::PATTERN );
		}

		parent::tearDown();
	}

	/**
	 * Received templates, patterns and template parts are stored without duplicates.
	 */
	public function test_create_templates_stores_items_without_duplicates(): void {
		$this->assertSame( 400, $this->request( 'POST', 'shared', [ 'templates' => [] ] )->get_status() );

		update_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [ [ 'id' => 't1' ] ] );

		$data = $this->request(
			'POST',
			'shared',
			[
				'templates'      => [ [ 'id' => 't1' ], [ 'id' => 't2' ], [ 'slug' => 'no-id' ] ],
				'patterns'       => [ [ 'name' => 'p1' ], [ 'name' => 'p1' ] ],
				'template_parts' => [ [ 'id' => 'tp1' ] ],
			]
		)->get_data();

		$this->assertSame( [ 't1', 't2' ], array_column( get_option( Constants::ONEDESIGN_SHARED_TEMPLATES ), 'id' ) );
		$this->assertSame( [ [ 'name' => 'p1' ] ], get_option( Constants::ONEDESIGN_SHARED_PATTERNS ) );
		$this->assertSame( [ [ 'id' => 'tp1' ] ], get_option( Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS ) );
		$this->assertSame( get_option( Constants::ONEDESIGN_SHARED_TEMPLATES ), $this->request( 'GET', 'shared' )->get_data()['templates'] );
		$this->assertCount( 2, $data['templates'] );
	}

	/**
	 * Synced patterns become wp_block posts that are tracked and reused on later syncs.
	 */
	public function test_create_synced_patterns_creates_and_tracks_block_posts(): void {
		$request = [
			'synced_patterns' => [
				[
					'id'          => 12,
					'original_id' => 12,
					'slug'        => 'site-brand-promo',
					'title'       => 'Promo',
					'content'     => '<!-- wp:paragraph --><p>Promo</p><!-- /wp:paragraph -->',
				],
			],
		];

		$created = $this->request( 'POST', 'create-synced-patterns', $request )->get_data()['created_posts'];
		$post    = get_post( $created[12] );

		$this->assertSame( 'wp_block', $post->post_type );
		$this->assertSame( 'Promo', $post->post_title );
		$this->assertSame( [ $post->ID ], get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS ) );

		$this->assertSame( [ 12 => $post->ID ], $this->request( 'POST', 'create-synced-patterns', $request )->get_data()['created_posts'] );
		$this->assertCount( 1, get_posts( [ 'post_type' => 'wp_block' ] ), 'A second sync must not duplicate the post' );
	}

	/**
	 * Brand sites remove selected templates, or everything they created when removing all.
	 */
	public function test_remove_template_from_brand_site(): void {
		$tracked_id = self::factory()->post->create( [ 'post_type' => 'wp_block' ] );
		update_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [ [ 'id' => 't1' ], [ 'id' => 't2' ] ] );
		update_option( Constants::ONEDESIGN_SHARED_PATTERNS, [ [ 'name' => 'p1' ] ] );
		update_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS, [ $tracked_id ] );

		$this->assertSame( 400, $this->request( 'DELETE', 'remove-site-templates', [ 'template_ids' => [] ] )->get_status() );

		$this->request( 'DELETE', 'remove-site-templates', [ 'template_ids' => [ 't1' ] ] );
		$this->assertSame( [ [ 'id' => 't2' ] ], get_option( Constants::ONEDESIGN_SHARED_TEMPLATES ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $tracked_id ), 'Partial removal keeps created posts' );

		$this->request(
			'DELETE',
			'remove-site-templates',
			[
				'template_ids'  => [ 't2' ],
				'is_remove_all' => true,
			]
		);
		$this->assertNull( get_post( $tracked_id ) );
		$this->assertSame( [], get_option( Constants::ONEDESIGN_SHARED_TEMPLATES ) );
		$this->assertSame( [], get_option( Constants::ONEDESIGN_SHARED_PATTERNS ) );
		$this->assertSame( [], get_option( Constants::ONEDESIGN_BRAND_SITE_POST_IDS ) );
	}

	/**
	 * Removal is forwarded to the brand site; unknown sites and remote failures are reported.
	 */
	public function test_remove_template_forwards_to_brand_site(): void {
		$this->set_up_governing_site();
		$this->mock_http( static fn () => self::http_response( 200, [ 'success' => true ] ) );

		$this->assertSame(
			404,
			$this->request(
				'DELETE',
				'remove',
				[
					'template_ids' => [ 't1' ],
					'site'         => 'missing',
				]
			)->get_status()
		);

		$data = $this->request(
			'DELETE',
			'remove',
			[
				'template_ids' => [ 't1' ],
				'site'         => 'brand-a',
			]
		)->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertSame( 'DELETE', $this->http_requests[0]['args']['method'] );
		$this->assertSame( 'key-a', $this->http_requests[0]['args']['headers']['X-OneDesign-Token'] );
		$this->assertStringEndsWith( '/wp-json/onedesign/v1/templates/remove-site-templates', $this->http_requests[0]['url'] );

		$this->mock_http( static fn () => self::http_response( 500 ) );
		$failed = $this->request(
			'DELETE',
			'remove',
			[
				'template_ids' => [ 't1' ],
				'site'         => 'brand-a',
			]
		)->get_data();
		$this->assertFalse( $failed['success'] );
		$this->assertStringContainsString( 'Unexpected response code: 500', (string) reset( $failed['errors'] ) );
	}

	/**
	 * Applying a template syncs patterns first, then sends the template and its patterns with site-scoped IDs/slugs.
	 */
	public function test_apply_templates_to_sites_sends_site_scoped_template(): void {
		$this->set_up_governing_site();
		$synced_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Synced CTA',
				'post_name'    => 'synced-cta',
				'post_content' => '<!-- wp:paragraph --><p>Buy now</p><!-- /wp:paragraph -->',
			]
		);
		$block_ref = '<!-- wp:block {"ref":' . $synced_id . '} /-->';
		$part_id   = self::factory()->post->create(
			[
				'post_type'    => 'wp_template_part',
				'post_name'    => 'sharing-header',
				'post_title'   => 'Sharing Header',
				'post_content' => $block_ref,
			]
		);
		wp_set_object_terms( $part_id, get_stylesheet(), 'wp_theme' );

		register_block_pattern(
			self::PATTERN,
			[
				'title'   => 'Banner',
				'content' => '<!-- wp:heading --><h2>Banner</h2><!-- /wp:heading -->',
			]
		);
		register_block_template(
			self::TEMPLATE,
			[
				'title'   => 'Shared Single',
				'content' => '<!-- wp:pattern {"slug":"' . self::PATTERN . '"} /-->' . $block_ref . '<!-- wp:template-part {"slug":"sharing-header","theme":"' . get_stylesheet() . '"} /-->',
			]
		);
		$this->assertContains( self::template_id(), wp_list_pluck( $this->request( 'GET', 'all' )->get_data()['templates'], 'id' ) );
		$this->mock_http(
			static fn ( string $url ) => self::http_response(
				200,
				str_ends_with( $url, 'create-synced-patterns' )
					? [ 'created_posts' => [ $synced_id => 901 ] ]
					: [ 'success' => true ]
			)
		);

		$data = $this->request(
			'POST',
			'apply',
			[
				'sites'     => [ 'brand-a' ],
				'templates' => [ self::template_id() ],
			]
		)->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertCount( 2, $this->http_requests, 'Only the selected brand site is contacted' );
		$this->assertStringEndsWith( '/wp-json/onedesign/v1/templates/create-synced-patterns', $this->http_requests[0]['url'] );
		$this->assertStringEndsWith( '/wp-json/onedesign/v1/templates/shared', $this->http_requests[1]['url'] );
		$this->assertSame( 'key-a', $this->http_requests[1]['args']['headers']['X-OneDesign-Token'] );

		$prefix   = sanitize_title( get_bloginfo( 'name' ) ) . '-brand-a';
		$payload  = json_decode( $this->http_requests[1]['args']['body'], true );
		$template = $payload['templates'][0];

		$this->assertSame( $prefix . '//' . sanitize_title( self::template_id() ), $template['id'] );
		$this->assertSame( self::template_id(), $template['original_id'] );
		$this->assertSame( $prefix . '-shared-single', $template['slug'] );
		$blocks = parse_blocks( $template['content'] );
		$this->assertSame( $prefix . '-onedesign-test-banner', $blocks[0]['attrs']['slug'] );
		$this->assertSame( 901, $blocks[1]['attrs']['ref'] );
		$this->assertSame( $prefix . '-sharing-header', $blocks[2]['attrs']['slug'] );
		$this->assertArrayNotHasKey( 'theme', $blocks[2]['attrs'], 'The receiving site uses its own theme' );

		$synced = json_decode( $this->http_requests[0]['args']['body'], true )['synced_patterns'];
		$this->assertCount( 1, $synced, 'A pattern referenced directly and inside a part is sent once' );
		$this->assertSame( $synced_id, reset( $synced )['original_id'] );
		$this->assertSame( '<!-- wp:paragraph --><p>Buy now</p><!-- /wp:paragraph -->', reset( $synced )['content'] );
		$this->assertCount( 1, $payload['template_parts'] );
		$part = reset( $payload['template_parts'] );
		$this->assertSame( $prefix . '-sharing-header', $part['slug'] );
		$this->assertSame( 901, parse_blocks( $part['content'] )[0]['attrs']['ref'] );
		$this->assertSame( [ self::PATTERN ], array_column( $payload['patterns'], 'name' ) );
	}

	/**
	 * A failed site must not prevent a later site from receiving the template.
	 *
	 * @param string $stage         Failing endpoint.
	 * @param string $failure       Remote failure type.
	 * @param string $error         Expected error fragment.
	 * @param int    $request_count Expected outgoing requests.
	 */
	#[DataProvider( 'remote_failure_provider' )]
	public function test_apply_templates_isolates_remote_failures( string $stage, string $failure, string $error, int $request_count ): void {
		$this->set_up_governing_site();
		register_block_template(
			self::TEMPLATE,
			[
				'title'   => 'Shared Single',
				'content' => '<!-- wp:paragraph /-->',
			]
		);
		$this->mock_http(
			static function ( string $url ) use ( $stage, $failure ) {
				if ( str_contains( $url, 'brand-a' ) && str_ends_with( $url, $stage ) ) {
					return match ( $failure ) {
						'transport' => new \WP_Error( 'http_request_failed', 'Timeout' ),
						'status'    => self::http_response( 503 ),
						default     => self::http_response( 200, '<html>Proxy error</html>' ),
					};
				}

				return self::http_response( 200, [ 'success' => true ] );
			}
		);

		$response = $this->request(
			'POST',
			'apply',
			[
				'sites'     => [ 'brand-a', 'brand-b' ],
				'templates' => [ self::template_id() ],
			]
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertSame( [ 'https://brand-a.example/' ], array_keys( $data['errors'] ) );
		$this->assertStringContainsString( $error, $data['errors']['https://brand-a.example/'] );
		$this->assertSame( [ 'https://brand-b.example/' => [ 'success' => true ] ], $data['responses'] );
		$this->assertCount( $request_count, $this->http_requests );
		$this->assertStringContainsString( 'brand-b.example', end( $this->http_requests )['url'] );
		$this->assertSame( 'key-b', end( $this->http_requests )['args']['headers']['X-OneDesign-Token'] );
	}

	/**
	 * Remote failures that must remain local to the affected target.
	 *
	 * @return array<string,array{string,string,string,int}>
	 */
	public static function remote_failure_provider(): array {
		return [
			'sync timeout'    => [ 'create-synced-patterns', 'transport', 'Timeout', 3 ],
			'sync HTTP error' => [ 'create-synced-patterns', 'status', '503', 3 ],
			'sync bad JSON'   => [ 'create-synced-patterns', 'json', 'JSON decode error', 3 ],
			'push timeout'    => [ 'shared', 'transport', 'Timeout', 4 ],
			'push HTTP error' => [ 'shared', 'status', '503', 4 ],
			'push bad JSON'   => [ 'shared', 'json', 'JSON decode error', 4 ],
		];
	}

	/**
	 * Resync removes everything from the brand site, then re-applies the templates.
	 */
	public function test_resync_applied_templates_removes_then_applies(): void {
		$this->set_up_governing_site();
		register_block_template(
			self::TEMPLATE,
			[
				'title'   => 'Shared Single',
				'content' => '<!-- wp:paragraph /-->',
			]
		);
		$this->mock_http( static fn () => self::http_response( 200, [ 'success' => true ] ) );

		$data = $this->request(
			'POST',
			'resync',
			[
				'sites'     => [ 'brand-a' ],
				'templates' => [ self::template_id() ],
			]
		)->get_data();

		$this->assertTrue( $data['remove_response']['success'] );
		$this->assertTrue( $data['apply_response']['success'] );
		$this->assertStringEndsWith( 'remove-site-templates', $this->http_requests[0]['url'] );
		$this->assertTrue( json_decode( $this->http_requests[0]['args']['body'], true )['is_remove_all'] );
		$this->assertStringEndsWith( 'templates/shared', end( $this->http_requests )['url'] );
	}

	/**
	 * Shared templates are collected from each brand site by ID, with per-site errors.
	 */
	public function test_get_templates_from_connected_sites(): void {
		$this->set_up_governing_site();
		$this->mock_http(
			static fn ( string $url ) => str_contains( $url, 'brand-a' )
				? self::http_response( 200, [ 'templates' => [ [ 'id' => 't1' ] ] ] )
				: self::http_response( 200, [ 'success' => true ] )
		);

		$data = $this->request( 'GET', 'connected-sites' )->get_data();

		$this->assertSame( [ 'brand-a' => [ [ 'id' => 't1' ] ] ], $data['templates'] );
		$this->assertStringContainsString( 'Brand B', $data['errors']['brand-b'] );
	}

	/**
	 * ID WordPress reports for the registered test template (scoped to the active theme).
	 */
	private static function template_id(): string {
		return get_stylesheet() . '//shared-single';
	}

	/**
	 * Mark this site as governing with two connected brand sites.
	 */
	private function set_up_governing_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		Settings::set_shared_sites(
			[
				[
					'id'      => 'brand-a',
					'name'    => 'Brand A',
					'url'     => 'https://brand-a.example',
					'api_key' => 'key-a',
				],
				[
					'id'      => 'brand-b',
					'name'    => 'Brand B',
					'url'     => 'https://brand-b.example',
					'api_key' => 'key-b',
				],
			]
		);
	}

	/**
	 * Dispatch an authorized request through REST validation and permissions.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $route  Route relative to the controller namespace.
	 * @param array<string,mixed> $body   JSON body.
	 */
	private function request( string $method, string $route, array $body = [] ): \WP_REST_Response {
		return $this->dispatch_rest(
			$method,
			'/' . Templates_Controller::NAMESPACE . '/' . $route,
			$body,
			[
				'X-OneDesign-Token' => Settings::get_api_key(),
				'Origin'            => get_site_url(),
			]
		);
	}
}
