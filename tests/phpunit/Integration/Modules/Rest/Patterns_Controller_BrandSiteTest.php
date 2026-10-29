<?php
/**
 * Tests for Patterns_Controller on a brand site.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Library\Patterns;
use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use WP_REST_Request;

/**
 * The patterns a governing site sends a brand site, and who may send them.
 */
#[CoversClass( Patterns_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
#[CoversClass( Patterns::class )]
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

		foreach ( [ 'hero', 'theme/hero' ] as $name ) {
			if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_pattern( $name );
			}
		}

		parent::tear_down();
	}

	/**
	 * The governing site, with this site's key, can push patterns while nobody is logged in, sending only what Remote_Client sends.
	 */
	public function test_save_patterns_accepts_the_governing_site_with_the_key(): void {
		$response = $this->send( 'POST', $this->patterns_body(), self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'hero', get_option( Patterns::PATTERNS_OPTION_KEY ) );
	}

	/**
	 * Requests without this site's key, or from a site other than its governing site, are refused and change nothing, whatever Origin and User-Agent they claim.
	 *
	 * @param string  $site_url The URL the request says it's from.
	 * @param ?string $token    The token it sends, or null to send this site's key.
	 */
	#[DataProvider( 'refused_requests' )]
	public function test_refuses_other_requests( string $site_url, ?string $token ): void {
		$this->send( 'POST', $this->patterns_body(), self::GOVERNING_SITE_URL, Settings::get_api_key() );
		$saved = get_option( Patterns::PATTERNS_OPTION_KEY );

		$banner                        = $this->patterns_body();
		$banner['patterns'][0]['name'] = 'banner';
		$requests                      = [
			'GET'    => null,
			'POST'   => $banner,
			'DELETE' => [ 'names' => [ 'hero' ] ],
		];
		foreach ( $requests as $method => $body ) {
			$response = $this->send( $method, $body, $site_url, $token ?? Settings::get_api_key(), true );

			$this->assertContains( $response->get_status(), [ 401, 403 ], $method );
		}
		$this->assertSame( $saved, get_option( Patterns::PATTERNS_OPTION_KEY ) );
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
	 * A logged-in admin of this site is no governing site, so without the key they're refused too.
	 */
	public function test_save_patterns_refuses_an_admin_without_the_key(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $this->send( 'POST', $this->patterns_body(), self::GOVERNING_SITE_URL, '', true );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertFalse( get_option( Patterns::PATTERNS_OPTION_KEY ) );
	}

	/**
	 * A key that can't be decrypted (say, after the salts changed) matches no token, not even an empty one.
	 */
	public function test_save_patterns_refuses_everything_when_the_key_cannot_be_decrypted(): void {
		update_option( Settings::OPTION_CONSUMER_API_KEY, base64_encode( str_repeat( 'x', 48 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- A value Encryptor can't decrypt.

		$response = $this->send( 'POST', $this->patterns_body(), self::GOVERNING_SITE_URL, '', true );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertFalse( get_option( Patterns::PATTERNS_OPTION_KEY ) );
	}

	/**
	 * Scripts and event handlers in a pattern never reach the stored pattern, or the pattern this site registers from it.
	 */
	public function test_saved_patterns_have_no_scripts(): void {
		$body = [
			'patterns' => [
				[
					'name'        => 'hero',
					'title'       => 'Hero<script>alert(1)</script>',
					'description' => '<img src=x onerror=alert(2)>',
					'content'     => '<!-- wp:paragraph --><p>Hello<img src="x" onerror="alert(3)"></p><!-- /wp:paragraph --><script>alert(4)</script>',
				],
			],
		];

		$response = $this->send( 'POST', $body, self::GOVERNING_SITE_URL, Settings::get_api_key() );
		( new Patterns() )->register_received_patterns();

		$this->assertSame( 200, $response->get_status() );
		$stored     = (string) wp_json_encode( get_option( Patterns::PATTERNS_OPTION_KEY ) );
		$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'hero' );
		$this->assertIsArray( $registered );
		foreach ( [ $stored, $registered['title'] . $registered['content'] ] as $html ) {
			$this->assertStringNotContainsString( '<script', $html );
			$this->assertStringNotContainsString( 'onerror', $html );
		}
		$this->assertStringContainsString( '<p>Hello<img src="x"', $registered['content'] );
	}

	/**
	 * The pattern categories a pattern is in exist as soon as it arrives, so nothing is written when a page loads. Each is named with the label it was sent with, or its slug without one.
	 */
	public function test_saved_patterns_add_their_categories(): void {
		$body                                   = $this->patterns_body();
		$body['patterns'][0]['categories']      = [ 'lab-demo', 'unlabelled' ];
		$body['patterns'][0]['category_labels'] = [ 'lab-demo' => 'Lab Demo' ];

		$this->send( 'POST', $body, self::GOVERNING_SITE_URL, Settings::get_api_key() );

		foreach ( [
			'lab-demo'   => 'Lab Demo',
			'unlabelled' => 'unlabelled',
		] as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, 'wp_pattern_category' );
			$this->assertInstanceOf( \WP_Term::class, $term );
			$this->assertSame( $name, $term->name );
		}
	}

	/**
	 * The patterns are listed with the labels this site gives their categories, so a category it already had keeps its own label.
	 */
	public function test_get_patterns_lists_this_site_category_labels(): void {
		wp_insert_term( 'Our Heroes', 'wp_pattern_category', [ 'slug' => 'lab-hero' ] );
		$body                                   = $this->patterns_body();
		$body['patterns'][0]['categories']      = [ 'lab-hero', 'lab-demo' ];
		$body['patterns'][0]['category_labels'] = [
			'lab-hero' => 'Heroes',
			'lab-demo' => 'Lab Demo',
		];

		$this->send( 'POST', $body, self::GOVERNING_SITE_URL, Settings::get_api_key() );
		$patterns = $this->send( 'GET', null, self::GOVERNING_SITE_URL, Settings::get_api_key() )->get_data();

		$this->assertSame(
			[
				'lab-hero' => 'Our Heroes',
				'lab-demo' => 'Lab Demo',
			],
			$patterns[0]['category_labels']
		);
	}

	/**
	 * A pattern keeps its namespaced name, so it's registered under the name the governing site gave it.
	 */
	public function test_saved_patterns_keep_namespaced_names(): void {
		$body                        = $this->patterns_body();
		$body['patterns'][0]['name'] = 'theme/hero';

		$this->send( 'POST', $body, self::GOVERNING_SITE_URL, Settings::get_api_key() );
		( new Patterns() )->register_received_patterns();

		$this->assertSame( [ 'theme/hero' ], array_keys( get_option( Patterns::PATTERNS_OPTION_KEY ) ) );
		$this->assertTrue( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'theme/hero' ) );
	}

	/**
	 * A pattern is stored under its sanitized name, so it's listed and removed by that name.
	 */
	public function test_saved_patterns_are_removed_by_the_name_they_are_listed_with(): void {
		$body                        = $this->patterns_body();
		$body['patterns'][0]['name'] = ' theme/hero<br>';

		$this->send( 'POST', $body, self::GOVERNING_SITE_URL, Settings::get_api_key() );
		$names = array_column( $this->send( 'GET', null, self::GOVERNING_SITE_URL, Settings::get_api_key() )->get_data(), 'name' );
		$this->send( 'DELETE', [ 'names' => $names ], self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$this->assertSame( [ 'theme/hero' ], $names );
		$this->assertSame( [], get_option( Patterns::PATTERNS_OPTION_KEY ) );
	}

	/**
	 * A pattern without content is refused before anything is stored.
	 */
	public function test_save_patterns_refuses_a_pattern_without_content(): void {
		$response = $this->send(
			'POST',
			[
				'patterns' => [
					[
						'name'  => 'hero',
						'title' => 'Hero',
					],
				],
			],
			self::GOVERNING_SITE_URL,
			Settings::get_api_key()
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code() );
		$this->assertFalse( get_option( Patterns::PATTERNS_OPTION_KEY ) );
	}

	/**
	 * A brand site with no patterns lists none, which isn't an error.
	 */
	public function test_get_patterns_lists_none_on_a_new_brand_site(): void {
		$response = $this->send( 'GET', null, self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $response->get_data() );
	}

	/**
	 * Removing patterns takes out only those, and removing one that's already gone is fine.
	 */
	public function test_delete_patterns_removes_them_and_accepts_ones_already_gone(): void {
		$this->send( 'POST', $this->patterns_body(), self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$response = $this->send( 'DELETE', [ 'names' => [ 'hero', 'gone' ] ], self::GOVERNING_SITE_URL, Settings::get_api_key() );
		$again    = $this->send( 'DELETE', [ 'names' => [ 'hero' ] ], self::GOVERNING_SITE_URL, Settings::get_api_key() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 200, $again->get_status() );
		$this->assertSame( [], get_option( Patterns::PATTERNS_OPTION_KEY ) );
	}

	/**
	 * A pattern push with one pattern.
	 *
	 * @return array<string,mixed>
	 */
	private function patterns_body(): array {
		return [
			'patterns' => [
				[
					'name'    => 'hero',
					'title'   => 'Hero',
					'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
				],
			],
		];
	}

	/**
	 * Dispatches a request to /patterns.
	 *
	 * @param string               $method   The method.
	 * @param ?array<string,mixed> $body     The JSON body, if any.
	 * @param string               $site_url The URL the request says it's from.
	 * @param string               $token    The token, or '' to send none.
	 * @param bool                 $spoofed  Whether to add the Origin and User-Agent a browser or script can set, naming the sender and the governing site.
	 */
	private function send( string $method, ?array $body, string $site_url, string $token, bool $spoofed = false ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/onedesign/v1/patterns' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-OneDesign-Site-URL', $site_url );
		if ( $spoofed ) {
			$request->set_header( 'Origin', $site_url );
			$request->set_header( 'User-Agent', 'WordPress/6.9; ' . self::GOVERNING_SITE_URL );
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
