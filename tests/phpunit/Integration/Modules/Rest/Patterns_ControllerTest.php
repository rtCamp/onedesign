<?php
/**
 * Patterns_Controller unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for pattern sharing between governing and brand sites.
 */
#[CoversClass( Patterns_Controller::class )]
final class Patterns_ControllerTest extends TestCase {
	private const PATTERN  = 'onedesign-test/hero';
	private const CATEGORY = 'onedesign-test-cat';

	/**
	 * Controller under test.
	 */
	private Patterns_Controller $controller;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->controller = new Patterns_Controller();
		$this->initialize_rest_server( [ $this->controller ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		register_block_pattern_category( self::CATEGORY, [ 'label' => 'Test Category' ] );
		register_block_pattern(
			self::PATTERN,
			[
				'title'      => 'Hero',
				'content'    => '<!-- wp:heading --><h2>Hero</h2><!-- /wp:heading -->',
				'categories' => [ self::CATEGORY ],
			]
		);
		register_block_pattern(
			'onedesign-test/hidden',
			[
				'title'    => 'Hidden',
				'content'  => '<!-- wp:paragraph /-->',
				'inserter' => false,
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		unregister_block_pattern( self::PATTERN );
		unregister_block_pattern( 'onedesign-test/hidden' );
		unregister_block_pattern_category( self::CATEGORY );

		parent::tearDown();
	}

	/**
	 * Brand sites sanitize received patterns and merge them with existing ones.
	 */
	public function test_receive_patterns_sanitizes_and_merges(): void {
		$this->assertSame( 'not_child_site', $this->request( 'POST', 'receive-patterns', [ 'patterns_data' => [] ] )->get_data()['code'] );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		$this->assertSame( 404, $this->request( 'GET', 'brand-site-patterns' )->get_status() );
		update_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS, [ 'existing' => [ 'name' => 'existing' ] ] );

		$response = $this->request(
			'POST',
			'receive-patterns',
			[
				'patterns_data' => [
					[
						'name'          => 'Brand/Hero',
						'title'         => '<b>Hero</b>',
						'content'       => '<!-- wp:heading /-->',
						'categories'    => [ 'featured' ],
						'viewportWidth' => '1200',
						'inserter'      => 0,
					],
					[
						'name'  => 'no-content',
						'title' => 'Skipped',
					],
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'existing'  => [ 'name' => 'existing' ],
				'brandhero' => [
					'name'          => 'brandhero',
					'title'         => 'Hero',
					'content'       => '<!-- wp:heading /-->',
					'description'   => '',
					'categories'    => [ 'featured' ],
					'keywords'      => [],
					'viewportWidth' => 1200,
					'inserter'      => false,
				],
			],
			get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS )
		);
		$this->assertSame( get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS ), $this->request( 'GET', 'brand-site-patterns' )->get_data()['patterns'] );
	}

	/**
	 * Only the requested patterns are removed from a brand site.
	 */
	public function test_remove_brand_site_patterns(): void {
		$request = [ 'pattern_names' => [ 'a' ] ];
		$this->assertSame( 'no_patterns_found', $this->request( 'DELETE', 'remove-brand-site-patterns', $request )->get_data()['code'] );

		update_option(
			Constants::ONEDESIGN_BRAND_SITE_PATTERNS,
			[
				'a' => [ 'name' => 'a' ],
				'b' => [ 'name' => 'b' ],
			]
		);

		$this->assertSame( 'no_patterns_removed', $this->request( 'DELETE', 'remove-brand-site-patterns', [ 'pattern_names' => [ 'missing' ] ] )->get_data()['code'] );
		$this->assertSame( 200, $this->request( 'DELETE', 'remove-brand-site-patterns', $request )->get_status() );
		$this->assertSame( [ 'b' => [ 'name' => 'b' ] ], get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS ) );
	}

	/**
	 * Local patterns include registered and user patterns with category labels, excluding non-inserter ones.
	 */
	public function test_get_local_patterns_includes_registered_and_user_patterns(): void {
		$block_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'My Pattern',
				'post_content' => '<!-- wp:paragraph /-->',
			]
		);
		wp_set_object_terms( $block_id, 'user-cat', 'wp_pattern_category' );
		update_post_meta( $block_id, 'wp_pattern_keywords', [ 'promo' ] );

		$patterns = array_column( $this->request( 'GET', 'local-patterns' )->get_data(), null, 'name' );

		$this->assertSame( [ self::CATEGORY => 'Test Category' ], $patterns[ self::PATTERN ]['category_labels'] );
		$this->assertSame( 'registered', $patterns[ self::PATTERN ]['source'] );
		$this->assertArrayNotHasKey( 'onedesign-test/hidden', $patterns );

		$user_pattern = $patterns[ 'user-pattern-' . $block_id ];
		$this->assertSame( 'user', $user_pattern['source'] );
		$this->assertSame( [ 'user-cat' ], $user_pattern['categories'] );
		$this->assertSame( [ 'promo' ], $user_pattern['keywords'] );
		$this->assertSame( $block_id, $user_pattern['id'] );
	}

	/**
	 * Pattern categories merge user-created terms without duplicating registered ones.
	 */
	public function test_get_pattern_categories_merges_user_terms(): void {
		self::factory()->term->create(
			[
				'taxonomy' => 'wp_pattern_category',
				'name'     => 'Seasonal',
				'slug'     => 'seasonal',
			]
		);
		self::factory()->term->create(
			[
				'taxonomy' => 'wp_pattern_category',
				'name'     => 'Duplicate',
				'slug'     => self::CATEGORY,
			]
		);

		$categories = array_count_values( array_column( $this->request( 'GET', 'pattern-categories' )->get_data()['categories'], 'name' ) );

		$this->assertSame( 1, $categories['seasonal'] );
		$this->assertSame( 1, $categories[ self::CATEGORY ] );
	}

	/**
	 * Selected local patterns are pushed to each target with its token; failures are reported per site.
	 */
	public function test_push_patterns_to_targets(): void {
		$this->set_up_governing_site();
		$this->assertSame( [ 'brand-a', 'brand-b' ], array_column( $this->request( 'GET', 'configured-sites' )->get_data(), 'id' ) );
		$this->mock_http( static fn ( string $url ) => str_contains( $url, 'brand-a' ) ? self::http_response( 200, [ 'success' => true ] ) : self::http_response( 500, [ 'message' => 'Boom' ] ) );

		$results = $this->request(
			'POST',
			'push-patterns',
			[
				'pattern_names'   => [ self::PATTERN, 'unknown/pattern' ],
				'target_site_ids' => [ 'brand-a', 'brand-b', 'missing' ],
			]
		)->get_data();

		$this->assertTrue( $results['brand-a']['success'] );
		$this->assertFalse( $results['brand-b']['success'] );
		$this->assertStringContainsString( 'Boom (Status: 500)', $results['brand-b']['message'] );
		$this->assertFalse( $results['missing']['success'] );

		$this->assertCount( 2, $this->http_requests );
		$this->assertStringEndsWith( '/wp-json/onedesign/v1/receive-patterns', $this->http_requests[0]['url'] );
		$this->assertSame( 'key-a', $this->http_requests[0]['args']['headers']['X-OneDesign-Token'] );

		$body = json_decode( $this->http_requests[0]['args']['body'], true );
		$this->assertSame( [ self::PATTERN ], array_column( $body['patterns_data'], 'name' ), 'Unknown patterns are not sent' );
		$this->assertSame( get_bloginfo( 'name' ), $body['source_site_name'] );
	}

	/**
	 * Pushing requires a governing site and at least one known pattern.
	 */
	public function test_push_patterns_to_targets_validates_input(): void {
		$request = [
			'pattern_names'   => [ 'unknown/pattern' ],
			'target_site_ids' => [ 'brand-a' ],
		];
		$this->assertSame( 'not_parent_site', $this->request( 'POST', 'push-patterns', $request )->get_data()['code'] );

		$this->set_up_governing_site();
		$this->assertSame( 'no_patterns_selected', $this->request( 'POST', 'push-patterns', $request )->get_data()['code'] );
	}

	/**
	 * Removal requests are forwarded to the brand site; unknown sites and remote errors surface as errors.
	 */
	public function test_request_remove_brand_site_patterns(): void {
		$this->set_up_governing_site();
		$this->mock_http( static fn ( string $url ) => str_contains( $url, 'brand-a' ) ? self::http_response( 200, [ 'success' => true ] ) : self::http_response( 404, [ 'message' => 'Gone' ] ) );

		$unknown = $this->request(
			'DELETE',
			'request-remove-brand-site-patterns',
			[
				'pattern_names' => [ 'a' ],
				'site_id'       => 'missing',
			]
		);
		$this->assertSame( 'site_not_found', $unknown->get_data()['code'] );

		$success = $this->request(
			'DELETE',
			'request-remove-brand-site-patterns',
			[
				'pattern_names' => [ 'a' ],
				'site_id'       => 'brand-a',
			]
		);
		$this->assertTrue( $success->get_data()['success'] );
		$this->assertSame( 'DELETE', $this->http_requests[0]['args']['method'] );
		$this->assertStringEndsWith( '/wp-json/onedesign/v1/remove-brand-site-patterns', $this->http_requests[0]['url'] );
		$this->assertSame( [ 'pattern_names' => [ 'a' ] ], json_decode( $this->http_requests[0]['args']['body'], true ) );

		$error = $this->request(
			'DELETE',
			'request-remove-brand-site-patterns',
			[
				'pattern_names' => [ 'a' ],
				'site_id'       => 'brand-b',
			]
		);
		$this->assertSame( 'remote_error', $error->get_data()['code'] );
		$this->assertSame( 404, $error->get_status() );
	}

	/**
	 * Patterns from every brand site are collected by site ID; transport errors are logged.
	 */
	public function test_get_all_brand_site_patterns(): void {
		$this->set_up_governing_site();
		$this->mock_http(
			static fn ( string $url ) => str_contains( $url, 'brand-a' )
				? self::http_response(
					200,
					[
						'patterns' => [
							[
								'name'  => 'a/one',
								'title' => 'One',
							],
							[ 'name' => 'a/untitled' ],
						],
					]
				)
				: new \WP_Error( 'http_request_failed', 'Timeout' )
		);

		$data = $this->request( 'GET', 'get-all-brand-site-patterns' )->get_data();

		$this->assertSame( [ 'brand-a' ], array_keys( $data['patterns'] ) );
		$this->assertSame( [ 'a/one' ], array_column( $data['patterns']['brand-a'], 'name' ) );
		$this->assertSame( 'Brand A', $data['patterns']['brand-a'][0]['source_site'] );
		$this->assertSame( 'Timeout', $data['error_logs'][0]->get_error_message() );
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
			'/' . Patterns_Controller::NAMESPACE . '/' . $route,
			$body,
			[
				'X-OneDesign-Token' => Settings::get_api_key(),
				'Origin'            => get_site_url(),
			]
		);
	}
}
