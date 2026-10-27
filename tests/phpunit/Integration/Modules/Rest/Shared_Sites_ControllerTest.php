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
	 * Lists the brand sites without their keys.
	 */
	public function test_get_shared_sites_leaves_out_the_keys(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com',
					'api_key' => 'secret',
				],
			]
		);

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/onedesign/v1/shared-sites' ) );
		$sites    = $response->get_data()['shared_sites'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $sites );
		$this->assertSame( 'https://a.example.com/', $sites[0]['url'] );
		$this->assertArrayNotHasKey( 'api_key', $sites[0] );
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
	 * URLs that differ only by a trailing slash are the same site, so they're rejected and nothing is saved or checked.
	 */
	public function test_set_shared_sites_rejects_urls_that_differ_by_a_trailing_slash(): void {
		$requested_urls = [];
		$filter         = static function ( $preempt, $args, $url ) use ( &$requested_urls ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$requested_urls[] = $url;
			return new \WP_Error( 'blocked', 'Intercepted' );
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$response = $this->set_shared_sites(
			[
				[
					'url'     => 'https://dup.example.com',
					'name'    => 'Dup 1',
					'api_key' => 'k1',
				],
				[
					'url'     => 'https://dup.example.com/',
					'name'    => 'Dup 2',
					'api_key' => 'k2',
				],
			]
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'duplicate_site_url', $response->get_data()['code'] );
		$this->assertSame( [], $requested_urls );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Saves valid sites data successfully.
	 */
	public function test_set_shared_sites_returns_success_for_valid_data(): void {
		// Its sanitizer gives each site an id.
		( new Settings() )->register_settings();

		// Both brand sites pass their health check.
		$filter = static fn (): array => [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => '',
			'headers'  => [],
			'cookies'  => [],
		];
		add_filter( 'pre_http_request', $filter );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/shared-sites' );
		$request->set_body(
			wp_json_encode(
				[
					'sites_data' => [
						[
							'url'     => 'https://site-a.example.com',
							'name'    => 'Site A',
							'api_key' => 'ka',
						],
						[
							'url'     => 'https://site-b.example.com',
							'name'    => 'Site B',
							'api_key' => 'kb',
						],
					],
				]
			)
		);
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $data['shared_sites'] );
		$this->assertNotEmpty( $data['shared_sites'][0]['id'] );

		// Verify data was actually persisted to the database.
		$stored = Settings::get_shared_sites();
		$this->assertCount( 2, $stored );
	}

	/**
	 * Handles empty sites_data gracefully.
	 */
	public function test_set_shared_sites_allows_empty_sites_data(): void {
		$request = new WP_REST_Request( 'POST', '/onedesign/v1/shared-sites' );
		$request->set_body( wp_json_encode( [ 'sites_data' => [] ] ) );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( [], $data['shared_sites'] );
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
	 * A stored site keeps its key when it's saved without one, and the response leaves the key out.
	 */
	public function test_set_shared_sites_keeps_stored_keys(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com',
					'api_key' => 'keep-me',
				],
			]
		);

		$response = $this->set_shared_sites(
			[
				[
					'id'   => 'abc-1',
					'name' => 'A renamed',
					'url'  => 'https://a.example.com',
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'keep-me', Settings::get_shared_sites()['https://a.example.com/']['api_key'] );
		$this->assertSame( 'A renamed', $response->get_data()['shared_sites'][0]['name'] );
		$this->assertArrayNotHasKey( 'api_key', $response->get_data()['shared_sites'][0] );
	}

	/**
	 * A blank key, even with stray whitespace, keeps the stored key, but a site that moved to another URL doesn't keep it and is checked again.
	 */
	public function test_set_shared_sites_only_keeps_the_key_of_an_unchanged_url(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com',
					'api_key' => 'key-a',
				],
				[
					'id'      => 'abc-2',
					'name'    => 'B',
					'url'     => 'https://b.example.com',
					'api_key' => 'key-b',
				],
			]
		);

		$checked_urls = [];
		$filter       = static function ( $preempt, $args, $url ) use ( &$checked_urls ): array { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$checked_urls[] = $url;

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
					'api_key' => " \n",
				],
				[
					'id'   => 'abc-2',
					'name' => 'B',
					'url'  => 'https://elsewhere.example.com',
				],
			]
		);

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'https://elsewhere.example.com/wp-json/onedesign/v1/governing-site' ], $checked_urls );

		$sites = Settings::get_shared_sites();
		$this->assertSame( 'key-a', $sites['https://a.example.com/']['api_key'] );
		$this->assertSame( '', $sites['https://elsewhere.example.com/']['api_key'] );
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
	 * Removing a site by id removes it through the setting, and tells the brand site.
	 */
	public function test_delete_shared_site_removes_and_notifies_the_brand(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com',
					'api_key' => 'ka',
				],
			]
		);

		$sent   = [];
		$filter = static function ( $preempt, array $args, string $url ) use ( &$sent ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$sent = [ $args, $url ];

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

		$data = $this->server->dispatch( new WP_REST_Request( 'DELETE', '/onedesign/v1/shared-sites/abc-1' ) )->get_data();

		remove_filter( 'pre_http_request', $filter );

		$this->assertTrue( $data['governing_site_notified'] );
		$this->assertSame( [], Settings::get_shared_sites() );
		$this->assertSame( 'https://a.example.com/wp-json/onedesign/v1/governing-site', $sent[1] );
		$this->assertSame( 'DELETE', $sent[0]['method'] );
		$this->assertSame( 'ka', $sent[0]['headers']['X-OneDesign-Token'] );
		$this->assertSame( Utils::get_current_site_url(), $sent[0]['headers']['X-OneDesign-Site-URL'] );
	}

	/**
	 * A brand site that can't be notified is explained, and the site is removed anyway. Unknown ids are 404.
	 */
	public function test_delete_shared_site_explains_an_unreachable_brand_and_unknown_ids(): void {
		Settings::set_shared_sites(
			[
				[
					'id'      => 'abc-1',
					'name'    => 'A',
					'url'     => 'https://a.example.com',
					'api_key' => 'ka',
				],
			]
		);

		$this->assertSame( 404, $this->server->dispatch( new WP_REST_Request( 'DELETE', '/onedesign/v1/shared-sites/nope' ) )->get_status() );

		$filter = static fn () => new \WP_Error( 'http_request_failed', 'down' );
		add_filter( 'pre_http_request', $filter );

		$data = $this->server->dispatch( new WP_REST_Request( 'DELETE', '/onedesign/v1/shared-sites/abc-1' ) )->get_data();

		remove_filter( 'pre_http_request', $filter );

		$this->assertFalse( $data['governing_site_notified'] );
		$this->assertStringContainsString( 'could not be notified', $data['message'] );
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
