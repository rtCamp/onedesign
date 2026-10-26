<?php
/**
 * Tests for who may call a brand site's pattern routes.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use OneDesign\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_REST_Request;

/**
 * Only the governing site's server, naming itself and signing with the brand's key, reaches a brand site's pattern routes.
 */
#[CoversClass( Patterns_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
class Patterns_Controller_BrandSiteTest extends TestCase {
	/**
	 * The governing site this brand site is connected to. It shares its host with other sites in sub-directories.
	 */
	private const GOVERNING_SITE_URL = 'https://example.com/governing';

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
		update_option(
			Constants::ONEDESIGN_BRAND_SITE_PATTERNS,
			[
				'hero' => [
					'name'    => 'hero',
					'title'   => 'Hero',
					'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
				],
			],
			false
		);

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		// Only the key can let a request in.
		wp_set_current_user( 0 );

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
	 * The governing site, naming itself and signing with this site's key, reaches each route while nobody is logged in.
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route.
	 * @param ?array<string,mixed> $body   The JSON body, if any.
	 */
	#[DataProvider( 'brand_site_routes' )]
	public function test_accepts_the_governing_site_with_the_key( string $method, string $route, ?array $body ): void {
		$response = $this->send( $method, $route, $body, self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * A request with this site's key whose Origin and User-Agent name the governing site is refused without X-OneDesign-Site-URL, and changes nothing.
	 *
	 * @param string               $method The method.
	 * @param string               $route  The route.
	 * @param ?array<string,mixed> $body   The JSON body, if any.
	 */
	#[DataProvider( 'brand_site_routes' )]
	public function test_refuses_a_matching_origin_without_the_site_url( string $method, string $route, ?array $body ): void {
		$response = $this->send( $method, $route, $body, null, Settings::get_api_key() );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertSame( [ 'hero' ], array_keys( get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS ) ) );
	}

	/**
	 * The brand site's pattern routes, each with a body it accepts.
	 *
	 * @return array<string,array{string,string,?array<string,mixed>}>
	 */
	public static function brand_site_routes(): array {
		return [
			'list'    => [ 'GET', '/onedesign/v1/brand-site-patterns', null ],
			'receive' => [ 'POST', '/onedesign/v1/receive-patterns', self::patterns_body() ],
			'remove'  => [ 'DELETE', '/onedesign/v1/remove-brand-site-patterns', [ 'pattern_names' => [ 'hero' ] ] ],
		];
	}

	/**
	 * Requests without this site's key, or from a site other than its governing site, are refused and change nothing, whatever Origin and User-Agent they claim.
	 *
	 * @param string  $site_url The URL the request says it's from.
	 * @param ?string $token    The token it sends, or null to send this site's key.
	 */
	#[DataProvider( 'refused_requests' )]
	public function test_refuses_other_requests( string $site_url, ?string $token ): void {
		$response = $this->send( 'POST', '/onedesign/v1/receive-patterns', self::patterns_body(), $site_url, $token ?? Settings::get_api_key() );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertSame( [ 'hero' ], array_keys( get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS ) ) );
	}

	/**
	 * The requests that must be refused.
	 *
	 * @return array<string,array{string,?string}>
	 */
	public static function refused_requests(): array {
		return [
			'missing token'         => [ self::GOVERNING_SITE_URL, '' ],
			'wrong key'             => [ self::GOVERNING_SITE_URL, 'wrong-key' ],
			'other site'            => [ 'https://other.example.com', null ],
			'sibling sub-directory' => [ 'https://example.com/sibling', null ],
		];
	}

	/**
	 * A key that can't be decrypted (say, after the salts changed) matches no token, not even an empty one.
	 */
	public function test_refuses_everything_when_the_key_cannot_be_decrypted(): void {
		update_option( Settings::OPTION_CONSUMER_API_KEY, base64_encode( str_repeat( 'x', 48 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A value Encryptor can't decrypt.

		$response = $this->send( 'GET', '/onedesign/v1/brand-site-patterns', null, self::GOVERNING_SITE_URL, '' );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	/**
	 * A site that isn't a brand site takes no pattern from anyone, even with the right headers.
	 */
	public function test_refuses_everything_on_a_site_that_is_not_a_brand_site(): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$response = $this->send( 'GET', '/onedesign/v1/brand-site-patterns', null, self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	/**
	 * The governing site's server names itself in X-OneDesign-Site-URL when it pushes patterns to a brand site.
	 */
	public function test_governing_site_sends_its_url_with_the_key(): void {
		$this->set_up_governing_site();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		register_block_pattern(
			'onedesign-test/hero',
			[
				'title'   => 'Hero',
				'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			]
		);

		$headers = [];
		add_filter( // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- It only reads the headers.
			'http_request_args',
			static function ( array $args ) use ( &$headers ): array {
				$headers = $args['headers'];
				return $args;
			}
		);
		add_filter(
			'pre_http_request',
			static fn (): array => [
				'headers'  => [],
				'body'     => '{}',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			]
		);

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/push-patterns' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				[
					'pattern_names'   => [ 'onedesign-test/hero' ],
					'target_site_ids' => [ 'brand-1' ],
				]
			)
		);
		$this->server->dispatch( $request );
		unregister_block_pattern( 'onedesign-test/hero' );

		$this->assertSame( Utils::get_current_site_url(), $headers['X-OneDesign-Site-URL'] ?? null );
		$this->assertSame( 'brand-key', $headers['X-OneDesign-Token'] ?? null );
	}

	/**
	 * A pattern push with one pattern.
	 *
	 * @return array<string,mixed>
	 */
	private static function patterns_body(): array {
		return [
			'patterns_data' => [
				[
					'name'    => 'cta',
					'title'   => 'Call to action',
					'content' => '<!-- wp:paragraph --><p>Buy</p><!-- /wp:paragraph -->',
				],
			],
		];
	}

	/**
	 * Dispatches a request to a brand site route, with the Origin and User-Agent a browser or script can set, naming the governing site.
	 *
	 * @param string               $method   The method.
	 * @param string               $route    The route.
	 * @param ?array<string,mixed> $body     The JSON body, if any.
	 * @param ?string              $site_url The URL the request says it's from in X-OneDesign-Site-URL, or null to send no header.
	 * @param string               $token    The token, or '' to send none.
	 */
	private function send( string $method, string $route, ?array $body, ?string $site_url, string $token ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'Origin', $site_url ?? self::GOVERNING_SITE_URL );
		$request->set_header( 'User-Agent', 'WordPress/6.9; ' . self::GOVERNING_SITE_URL );
		if ( null !== $site_url ) {
			$request->set_header( 'X-OneDesign-Site-URL', $site_url );
		}
		if ( '' !== $token ) {
			$request->set_header( 'X-OneDesign-Token', $token );
		}
		if ( null !== $body ) {
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}
}
