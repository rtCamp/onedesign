<?php
/**
 * REST boundaries for pattern and template sharing.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Post_Types\Constants;
use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Patterns_Controller;
use OneDesign\Modules\Rest\Templates_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unauthorized or invalid REST requests must not change stored content or contact targets.
 */
#[CoversClass( Abstract_REST_Controller::class )]
#[CoversClass( Patterns_Controller::class )]
#[CoversClass( Templates_Controller::class )]
final class SharingRoutesTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->initialize_rest_server( [ new Patterns_Controller(), new Templates_Controller() ] );
		$this->mock_http( static fn () => self::http_response( 200, [ 'success' => true ] ) );
	}

	/**
	 * Sharing writes require manage_options even when the payload is valid.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $route  REST route.
	 * @param array<string,mixed> $body   Valid payload.
	 */
	#[DataProvider( 'admin_write_routes_provider' )]
	public function test_admin_writes_reject_unprivileged_users( string $method, string $route, array $body ): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		$before = $this->stored_content();

		foreach ( [ 0, self::factory()->user->create( [ 'role' => 'editor' ] ) ] as $user_id ) {
			wp_set_current_user( $user_id );
			$response = $this->dispatch_rest( $method, $route, $body );
			$this->assertSame( 0 === $user_id ? 401 : 403, $response->get_status(), $route );
			$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
		}

		$this->assertSame( [], $this->http_requests, 'Denied requests must not contact a brand site' );
		$this->assertSame( $before, $this->stored_content() );
	}

	/**
	 * Required arguments are checked before sharing callbacks can run.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $route  REST route.
	 * @param array<string,mixed> $body   Valid payload.
	 */
	#[DataProvider( 'admin_write_routes_provider' )]
	public function test_admin_writes_validate_json_payloads( string $method, string $route, array $body ): void {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$missing = $this->dispatch_rest( $method, $route );
		$this->assertSame( 400, $missing->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $missing->get_data()['code'] );

		foreach ( array_keys( $body ) as $argument ) {
			$invalid_body              = $body;
			$invalid_body[ $argument ] = [ [ 'invalid' => 'object instead of ID' ] ];
			$invalid                   = $this->dispatch_rest( $method, $route, $invalid_body );
			$this->assertSame( 400, $invalid->get_status(), $argument );
			$this->assertSame( 'rest_invalid_param', $invalid->get_data()['code'] );
			$this->assertArrayHasKey( $argument, $invalid->get_data()['data']['params'] );
		}

		$this->assertSame( [], $this->http_requests );
	}

	/**
	 * Governing sites authorize every receiver write using the paired token and origin.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $route  REST route.
	 * @param array<string,mixed> $body   Valid receiver payload.
	 */
	#[DataProvider( 'receiver_write_routes_provider' )]
	public function test_receiver_writes_require_paired_credentials( string $method, string $route, array $body ): void {
		wp_set_current_user( 0 );
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example' );
		update_option( Constants::ONEDESIGN_SHARED_TEMPLATES, [ [ 'id' => 't1' ] ] );
		update_option( Constants::ONEDESIGN_BRAND_SITE_PATTERNS, [ 'p1' => [ 'name' => 'p1' ] ] );
		$key    = Settings::get_api_key();
		$before = $this->stored_content();

		foreach ( [
			[],
			[
				'X-OneDesign-Token' => 'wrong-token',
				'Origin'            => 'https://governing.example',
			],
			[
				'X-OneDesign-Token' => $key,
				'Origin'            => 'https://unpaired.example',
			],
		] as $headers ) {
			$response = $this->dispatch_rest( $method, $route, $body, $headers );
			$this->assertSame( 401, $response->get_status(), $route );
			$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
			$this->assertSame( $before, $this->stored_content(), 'Rejected writes leave all shared content intact' );
			$this->assertSame( [], get_posts( [ 'post_type' => 'wp_block' ] ) );
		}

		$response = $this->dispatch_rest(
			$method,
			$route,
			$body,
			[
				'X-OneDesign-Token' => $key,
				'Origin'            => 'https://governing.example',
			]
		);
		$this->assertSame( 200, $response->get_status(), $route );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertNotSame( $before, $this->stored_content(), 'Valid credentials reach the write callback' );
		$this->assertSame( [], $this->http_requests );
	}

	/**
	 * Governing-site mutations, with valid arguments so permission checks are reached.
	 *
	 * @return array<string,array{string,string,array<string,mixed>}>
	 */
	public static function admin_write_routes_provider(): array {
		return [
			'push patterns'    => [
				'POST',
				'/onedesign/v1/push-patterns',
				[
					'pattern_names'   => [ 'p1' ],
					'target_site_ids' => [ 'brand-a' ],
				],
			],
			'remove patterns'  => [
				'DELETE',
				'/onedesign/v1/request-remove-brand-site-patterns',
				[
					'site_id'       => 'brand-a',
					'pattern_names' => [ 'p1' ],
				],
			],
			'apply templates'  => [
				'POST',
				'/onedesign/v1/templates/apply',
				[
					'sites'     => [ 'brand-a' ],
					'templates' => [ 't1' ],
				],
			],
			'remove templates' => [
				'DELETE',
				'/onedesign/v1/templates/remove',
				[
					'site'         => 'brand-a',
					'template_ids' => [ 't1' ],
				],
			],
			'resync templates' => [
				'POST',
				'/onedesign/v1/templates/resync',
				[
					'sites'     => [ 'brand-a' ],
					'templates' => [ 't1' ],
				],
			],
		];
	}

	/**
	 * Brand-site mutations that must reject unauthenticated cross-site writes.
	 *
	 * @return array<string,array{string,string,array<string,mixed>}>
	 */
	public static function receiver_write_routes_provider(): array {
		return [
			'receive patterns' => [
				'POST',
				'/onedesign/v1/receive-patterns',
				[
					'patterns_data' => [
						[
							'name'    => 'p2',
							'title'   => 'New',
							'content' => '<!-- wp:paragraph /-->',
						],
					],
				],
			],
			'remove patterns'  => [ 'DELETE', '/onedesign/v1/remove-brand-site-patterns', [ 'pattern_names' => [ 'p1' ] ] ],
			'share templates'  => [ 'POST', '/onedesign/v1/templates/shared', [ 'templates' => [ [ 'id' => 't2' ] ] ] ],
			'remove templates' => [ 'DELETE', '/onedesign/v1/templates/remove-site-templates', [ 'template_ids' => [ 't1' ] ] ],
			'sync patterns'    => [
				'POST',
				'/onedesign/v1/templates/create-synced-patterns',
				[
					'synced_patterns' => [
						[
							'id'          => 'shared-p1',
							'original_id' => 12,
							'slug'        => 'shared-p1',
							'title'       => 'Promo',
							'content'     => '<!-- wp:paragraph /-->',
						],
					],
				],
			],
		];
	}

	/**
	 * Snapshot every shared-content option to detect unauthorized writes.
	 *
	 * @return array<string,mixed>
	 */
	private function stored_content(): array {
		$content = [];
		foreach ( [ Constants::ONEDESIGN_BRAND_SITE_PATTERNS, Constants::ONEDESIGN_SHARED_TEMPLATES, Constants::ONEDESIGN_SHARED_PATTERNS, Constants::ONEDESIGN_SHARED_TEMPLATE_PARTS, Constants::ONEDESIGN_SHARED_SYNCED_PATTERNS, Constants::ONEDESIGN_BRAND_SITE_POST_IDS ] as $option ) {
			$content[ $option ] = get_option( $option );
		}

		return $content;
	}
}
