<?php
/**
 * Tests for Shared_Sites_Controller.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Shared_Sites_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use OneDesign\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Tests for the /shared-sites route.
 */
#[CoversClass( Shared_Sites_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
class Shared_Sites_ControllerTest extends TestCase {
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

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		( new Shared_Sites_Controller() )->register_hooks();
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
	 * Returns 400 when duplicate site URLs exist, before any brand site is checked.
	 */
	public function test_set_shared_sites_rejects_duplicate_urls(): void {
		$requested_urls = [];
		$filter         = static function ( $preempt, $args, $url ) use ( &$requested_urls ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$requested_urls[] = $url;
			return new \WP_Error( 'blocked', 'Intercepted' );
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/shared-sites' );
		$request->set_body(
			wp_json_encode(
				[
					'sites_data' => [
						[
							'url'     => 'https://dup.example.com',
							'name'    => 'Dup 1',
							'api_key' => 'k1',
						],
						[
							'url'     => 'https://dup.example.com',
							'name'    => 'Dup 2',
							'api_key' => 'k2',
						],
					],
				]
			)
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'duplicate_site_url', $response->get_data()['code'] );
		$this->assertSame( [], $requested_urls );
	}

	/**
	 * Handles missing sites_data key gracefully.
	 */
	public function test_set_shared_sites_handles_missing_sites_data_key(): void {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/shared-sites' );
		$request->set_body( wp_json_encode( [ 'other' => 'data' ] ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );

		// sites_data is required by the route schema → request fails validation.
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Saving health-checks a new site from this server with its key, and leaves the unchanged ones alone.
	 */
	public function test_set_shared_sites_checks_new_sites(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com/',
					'api_key' => 'ka',
				],
			]
		);

		$sent   = [];
		$filter = static function ( $preempt, array $args, string $url ) use ( &$sent ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$sent[] = [ $args, $url ];

			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => '',
				'headers'  => [],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$response = $this->set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com/',
					'api_key' => 'ka',
				],
				[
					'name'    => 'B',
					'url'     => 'https://b.example.com/',
					'api_key' => 'typed-key',
				],
			]
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $sent );
		$this->assertSame( 'https://b.example.com/wp-json/onedesign/v1/governing-site', $sent[0][1] );
		$this->assertSame( 'POST', $sent[0][0]['method'] );
		$this->assertSame( 'typed-key', $sent[0][0]['headers']['X-OneDesign-Token'] );
		$this->assertSame( Utils::get_current_site_url(), $sent[0][0]['headers']['X-OneDesign-Site-URL'] );
	}

	/**
	 * A key the brand site rejects, and a brand site connected elsewhere, are reported as such, and anything else as a failed check. None of them is saved.
	 */
	public function test_set_shared_sites_reports_the_brand_answer(): void {
		$filter = static fn ( $preempt, array $args ): array => [ // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			'response' => [
				'code'    => [
					'bad'       => 401,
					'connected' => 409,
				][ $args['headers']['X-OneDesign-Token'] ] ?? 500,
				'message' => '',
			],
			'body'     => '',
			'headers'  => [],
			'cookies'  => [],
		];
		add_filter( 'pre_http_request', $filter, 10, 2 );

		$responses = [];
		foreach ( [ 'bad', 'connected', 'good' ] as $api_key ) {
			$responses[ $api_key ] = $this->set_shared_sites(
				[
					[
						'name'    => 'B',
						'url'     => 'https://b.example.com',
						'api_key' => $api_key,
					],
				]
			);
		}

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 'onedesign_bad_key', $responses['bad']->get_data()['code'] );
		$this->assertSame( 'onedesign_already_connected', $responses['connected']->get_data()['code'] );
		$this->assertSame( 'onedesign_health_check_failed', $responses['good']->get_data()['code'] );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Dispatches a save of the brand sites.
	 *
	 * @param array<int,array<string,string>> $sites_data The brand sites.
	 */
	private function set_shared_sites( array $sites_data ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/shared-sites' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( [ 'sites_data' => $sites_data ] ) );

		return $this->server->dispatch( $request );
	}
}
