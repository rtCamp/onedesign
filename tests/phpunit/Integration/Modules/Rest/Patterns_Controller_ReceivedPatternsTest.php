<?php
/**
 * Tests for how patterns are sanitized when a brand site receives them and when the governing site lists them back.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Post_Types\Admin;
use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Received patterns keep their namespaced names and lose their scripts.
 */
#[CoversClass( Patterns_Controller::class )]
#[CoversClass( Admin::class )]
class Patterns_Controller_ReceivedPatternsTest extends TestCase {
	/**
	 * A pattern with scripts and event handlers in each of its fields that allow markup.
	 */
	private const UNSAFE_PATTERN = [
		'name'        => 'theme/hero',
		'title'       => 'Hero<script>alert(1)</script>',
		'description' => '<img src=x onerror=alert(2)>',
		'content'     => '<!-- wp:paragraph --><p>Hello<img src="x" onerror="alert(3)"></p><!-- /wp:paragraph --><script>alert(4)</script>',
	];

	/**
	 * REST server.
	 */
	private ?\WP_REST_Server $server;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		( new Patterns_Controller() )->register_hooks();
		do_action( 'rest_api_init' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'theme/hero' ) ) {
			unregister_block_pattern( 'theme/hero' );
		}

		parent::tear_down();
	}

	/**
	 * A brand site stores and registers a pattern under the namespaced name the governing site gave it, without its scripts.
	 */
	public function test_received_pattern_keeps_its_name_and_loses_its_scripts(): void {
		$this->set_up_brand_site();

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/receive-patterns' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://governing.example.com' );
		$request->set_header( 'X-OneDesign-Token', Settings::get_api_key() );
		$request->set_body( (string) wp_json_encode( [ 'patterns_data' => [ self::UNSAFE_PATTERN ] ] ) );

		$response = $this->server->dispatch( $request );
		( new Admin() )->register_block_patterns_if_not_exist();

		$this->assertSame( 200, $response->get_status() );
		$stored = get_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS );
		$this->assertSame( [ 'theme/hero' ], array_keys( $stored ) );
		$this->assertSame( 'theme/hero', $stored['theme/hero']['name'] );

		$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( 'theme/hero' );
		$this->assertIsArray( $registered );
		foreach ( [ (string) wp_json_encode( $stored ), $registered['title'] . $registered['description'] . $registered['content'] ] as $html ) {
			$this->assertStringNotContainsString( '<script', $html );
			$this->assertStringNotContainsString( 'onerror', $html );
		}
		$this->assertStringContainsString( '<p>Hello<img src="x"', $registered['content'] );
	}

	/**
	 * The governing site sanitizes the patterns a brand site lists, and drops the ones that don't match the schema.
	 */
	public function test_brand_site_patterns_are_sanitized_on_the_governing_site(): void {
		$this->set_up_governing_site();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$filter = static fn (): array => [
			'headers'  => [],
			'body'     => (string) wp_json_encode(
				[
					'success'  => true,
					'patterns' => [
						self::UNSAFE_PATTERN,
						[
							'name'  => 'theme/no-content',
							'title' => 'No content',
						],
					],
				]
			),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
		add_filter( 'pre_http_request', $filter );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/onedesign/v1/get-all-brand-site-patterns' ) );

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 200, $response->get_status() );
		$patterns = $response->get_data()['patterns']['brand-1'];
		$this->assertSame( [ 'theme/hero' ], array_column( $patterns, 'name' ) );
		$html = (string) wp_json_encode( $patterns );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
	}
}
