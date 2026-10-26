<?php
/**
 * Tests for Patterns_Controller on a governing site.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Library\Patterns;
use OneDesign\Modules\Library\Templates;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Rest\Remote_Client;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Sharing a governing site's patterns with its brand sites.
 */
#[CoversClass( Patterns_Controller::class )]
#[CoversClass( Patterns::class )]
#[CoversClass( Remote_Client::class )]
#[CoversClass( Settings::class )]
#[CoversClass( Templates::class )]
class Patterns_Controller_GoverningSiteTest extends TestCase {
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
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_up_two_brand_sites();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		( new Patterns_Controller() )->register_hooks();
		do_action( 'rest_api_init' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * The library lists this site's own patterns with the labels of their categories, including the ones admins created, which core's categories route leaves out.
	 */
	public function test_lists_this_site_patterns_with_their_category_labels(): void {
		$name = $this->create_user_pattern();

		$patterns = $this->dispatch( 'GET', '/patterns' )->get_data();

		$this->assertSame( [ 'lab-demo' => 'Lab Demo' ], array_column( $patterns, 'category_labels', 'name' )[ $name ] );
	}

	/**
	 * A brand site's patterns come back sanitized, and only that brand site is asked, with a URL no cache has an answer for.
	 */
	public function test_lists_a_brand_site_patterns_sanitized(): void {
		$this->answer(
			static fn (): array => [
				200,
				[
					[
						'name'    => 'hero',
						'title'   => 'Hero<script>alert(1)</script>',
						'content' => '<p>Hi<img src="x" onerror="alert(2)"></p><script>alert(3)</script>',
					],
				],
			]
		);

		$response = $this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-1' ] );
		$patterns = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'hero' ], array_column( $patterns, 'name' ) );
		$this->assertStringNotContainsString( '<script', (string) wp_json_encode( $patterns ) );
		$this->assertStringNotContainsString( 'onerror', (string) wp_json_encode( $patterns ) );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'GET', $this->sent[0][0] );
		$this->assertMatchesRegularExpression( '#^https://one\.example\.org/wp-json/onedesign/v1/patterns\?timestamp=\d+$#', $this->sent[0][1] );
	}

	/**
	 * A brand site's patterns come back with the labels the brand site gives their categories, sanitized.
	 */
	public function test_lists_a_brand_site_patterns_with_its_category_labels(): void {
		$this->answer(
			static fn (): array => [
				200,
				[
					[
						'name'            => 'hero',
						'title'           => 'Hero',
						'content'         => '<p>Hi</p>',
						'categories'      => [ 'lab-demo' ],
						'category_labels' => [ 'lab-demo' => 'Lab <script>alert(1)</script>Demo' ],
					],
					[
						'name'    => 'footer',
						'title'   => 'Footer',
						'content' => '<p>Bye</p>',
					],
				],
			]
		);

		$patterns = $this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-1' ] )->get_data();

		$this->assertSame(
			[
				'hero'   => [ 'lab-demo' => 'Lab Demo' ],
				'footer' => [],
			],
			array_column( $patterns, 'category_labels', 'name' )
		);
	}

	/**
	 * A brand site that fails passes its error on, with its own message.
	 */
	public function test_listing_a_brand_site_returns_its_error(): void {
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Database error.' ] ] );

		$response = $this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-2' ] );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Database error.', $response->as_error()->get_error_message() );
	}

	/**
	 * A brand site's pattern that doesn't match the schema, like one without content, is left out.
	 */
	public function test_listing_a_brand_site_drops_patterns_that_do_not_match_the_schema(): void {
		$this->answer(
			static fn (): array => [
				200,
				[
					[
						'name'    => 'hero',
						'title'   => 'Hero',
						'content' => '<p>Hi</p>',
					],
					[
						'name'  => 'broken',
						'title' => 'Broken',
					],
				],
			]
		);

		$patterns = $this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-1' ] )->get_data();

		$this->assertSame( [ 'hero' ], array_column( $patterns, 'name' ) );
	}

	/**
	 * A brand site that has no patterns yet is listed with none.
	 */
	public function test_lists_an_empty_brand_site(): void {
		$this->answer( static fn (): array => [ 200, [] ] );

		$response = $this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-1' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
	}

	/**
	 * Sharing sends the patterns, with the synced patterns they use, to the one brand site it names in one request.
	 */
	public function test_share_patterns_sends_them_to_the_brand_site(): void {
		$ready = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Lab Ready',
				'post_content' => '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->',
			]
		);
		$hero  = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Lab Hero',
				'post_content' => '<!-- wp:block {"ref":' . $ready . '} /-->',
			]
		);
		$this->answer( static fn (): array => [ 200, [ 'message' => 'Patterns saved.' ] ] );

		$response = $this->dispatch(
			'POST',
			'/patterns',
			[
				'site'  => 'brand-2',
				'names' => [ 'user-pattern-' . $hero ],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'message' => 'The patterns were shared with Brand Two.' ], $response->get_data() );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'POST', $this->sent[0][0] );
		$this->assertSame( 'https://two.example.org/wp-json/onedesign/v1/patterns', $this->sent[0][1] );
		$this->assertSame( 'Lab Hero', $this->sent[0][2]['patterns'][0]['title'] );
		$this->assertSame( [ $ready ], array_column( $this->sent[0][2]['synced_patterns'], 'original_id' ) );
	}

	/**
	 * A shared pattern that nests a synced pattern points at the brand site's own copy of it, never at this site's post.
	 */
	public function test_share_patterns_gives_the_brand_site_copies_of_the_synced_patterns_they_use(): void {
		$this->connect_brand_one();
		$nested = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_name'    => 'lab-ready',
				'post_title'   => 'Lab Ready',
				'post_content' => '<!-- wp:paragraph --><p>Ready to talk?</p><!-- /wp:paragraph -->',
			]
		);
		$hero   = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Lab Hero',
				'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Hero</h2><!-- /wp:heading --><!-- wp:block {"ref":' . $nested . '} /-->',
			]
		);

		$response = $this->dispatch(
			'POST',
			'/patterns',
			[
				'site'  => 'brand-1',
				'names' => [ 'user-pattern-' . $hero ],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$copy = get_post( get_option( Templates::POST_IDS_OPTION_KEY )[ $nested ] ?? 0 );
		$this->assertInstanceOf( \WP_Post::class, $copy );
		$this->assertNotSame( $nested, $copy->ID );
		$this->assertSame( 'wp_block', $copy->post_type );
		$this->assertStringContainsString( '<p>Ready to talk?</p>', $copy->post_content );
		$pattern = get_option( Patterns::PATTERNS_OPTION_KEY )[ 'user-pattern-' . $hero ];
		$this->assertStringContainsString( '<!-- wp:block {"ref":' . $copy->ID . '} /-->', $pattern['content'] );
	}

	/**
	 * A password-protected synced pattern stays out of the library, since core doesn't render it and brand sites would get it without the password.
	 */
	public function test_leaves_out_password_protected_patterns(): void {
		$post_id = self::factory()->post->create(
			[
				'post_type'     => 'wp_block',
				'post_title'    => 'Lab Secret',
				'post_password' => 'pass',
			]
		);

		$patterns = $this->dispatch( 'GET', '/patterns' )->get_data();

		$this->assertNotContains( 'user-pattern-' . $post_id, array_column( $patterns, 'name' ) );
	}

	/**
	 * A brand site that doesn't take the patterns passes its error on, with its own message.
	 */
	public function test_share_patterns_returns_the_brand_site_error(): void {
		$name = $this->create_user_pattern();
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Brand Two is down' ] ] );

		$response = $this->dispatch(
			'POST',
			'/patterns',
			[
				'site'  => 'brand-2',
				'names' => [ $name ],
			]
		);

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Brand Two is down', $response->as_error()->get_error_message() );
	}

	/**
	 * Brand One's own is_governing_request() accepts the key and site URL that Remote_Client sends, so a shared pattern is listed afterwards.
	 */
	public function test_brand_site_accepts_what_remote_client_sends(): void {
		$name = $this->create_user_pattern();
		$this->connect_brand_one();

		$shared = $this->dispatch(
			'POST',
			'/patterns',
			[
				'site'  => 'brand-1',
				'names' => [ $name ],
			]
		);
		$listed = $this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-1' ] )->get_data();

		$this->assertSame( 200, $shared->get_status() );
		$this->assertSame( [ $name ], array_column( $listed, 'name' ) );
	}

	/**
	 * Removing patterns asks the one brand site it names to remove them.
	 */
	public function test_delete_patterns_asks_the_brand_site_to_remove_them(): void {
		$this->answer( static fn (): array => [ 200, [ 'message' => 'Patterns removed.' ] ] );

		$removed = $this->dispatch(
			'DELETE',
			'/patterns',
			[
				'site'  => 'brand-1',
				'names' => [ 'hero' ],
			]
		);

		$this->assertSame( [ 'message' => 'The patterns were removed from Brand One.' ], $removed->get_data() );
		$this->assertSame( [ [ 'DELETE', 'https://one.example.org/wp-json/onedesign/v1/patterns', [ 'names' => [ 'hero' ] ] ] ], $this->sent );
	}

	/**
	 * A site that isn't a brand site of this site, missing parameters or patterns this site doesn't have are refused before any brand site is contacted.
	 */
	public function test_refuses_bad_requests_without_contacting_brand_sites(): void {
		$name = $this->create_user_pattern();
		$this->answer( static fn (): array => [ 200, [] ] );

		$unknown = [
			$this->dispatch( 'GET', '/patterns', [ 'site' => 'brand-9' ] ),
			$this->dispatch(
				'POST',
				'/patterns',
				[
					'site'  => 'brand-9',
					'names' => [ $name ],
				]
			),
			$this->dispatch(
				'DELETE',
				'/patterns',
				[
					'site'  => 'brand-9',
					'names' => [ $name ],
				]
			),
		];
		$empty   = $this->dispatch( 'POST', '/patterns', [] );
		$missing = $this->dispatch(
			'POST',
			'/patterns',
			[
				'site'  => 'brand-1',
				'names' => [ 'no-such-pattern' ],
			]
		);

		foreach ( $unknown as $response ) {
			$this->assertSame( 404, $response->get_status() );
			$this->assertSame( 'onedesign_site_not_found', $response->as_error()->get_error_code() );
		}
		$this->assertSame( 400, $empty->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $empty->as_error()->get_error_code() );
		$this->assertSame( 400, $missing->get_status() );
		$this->assertSame( 'onedesign_patterns_not_found', $missing->as_error()->get_error_code() );
		$this->assertSame( [], $this->sent );
	}

	/**
	 * Removing patterns from a brand site passes its error on, with its own message.
	 */
	public function test_delete_patterns_returns_the_brand_site_error(): void {
		$this->answer( static fn (): array => [ 500, [ 'message' => 'Database error.' ] ] );

		$response = $this->dispatch(
			'DELETE',
			'/patterns',
			[
				'site'  => 'brand-1',
				'names' => [ 'hero' ],
			]
		);

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( 'Database error.', $response->as_error()->get_error_message() );
	}

	/**
	 * Only administrators can read or change this site's library or what brand sites have.
	 */
	public function test_routes_need_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->answer( static fn (): array => [ 200, [] ] );

		$change   = [
			'site'  => 'brand-1',
			'names' => [ 'hero' ],
		];
		$requests = [
			[ 'GET', [] ],
			[ 'GET', [ 'site' => 'brand-1' ] ],
			[ 'POST', $change ],
			[ 'DELETE', $change ],
		];
		foreach ( $requests as [ $method, $body ] ) {
			$this->assertSame( 403, $this->dispatch( $method, '/patterns', $body )->get_status(), $method . ' ' . wp_json_encode( $body ) );
		}
		$this->assertSame( [], $this->sent );
	}

	/**
	 * Creates a user pattern in a user-created category.
	 *
	 * @return string The pattern's name in the library.
	 */
	private function create_user_pattern(): string {
		$post_id = self::factory()->post->create(
			[
				'post_type'    => 'wp_block',
				'post_title'   => 'Lab Hero',
				'post_content' => '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->',
			]
		);
		wp_insert_term( 'Lab Demo', 'wp_pattern_category', [ 'slug' => 'lab-demo' ] );
		wp_set_object_terms( $post_id, 'lab-demo', 'wp_pattern_category' );

		return 'user-pattern-' . $post_id;
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
		( new Patterns_Controller() )->register_routes();
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
