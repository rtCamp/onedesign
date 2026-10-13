<?php
/**
 * Basic_Options_Controller unit tests.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Basic_Options_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Tests for the basic options REST endpoints.
 */
#[CoversClass( Basic_Options_Controller::class )]
final class Basic_Options_ControllerTest extends TestCase {
	/**
	 * REST server.
	 */
	private \WP_REST_Server $server;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->initialize_rest_server( [ new Basic_Options_Controller() ] );
		$this->server = rest_get_server();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Admin-only endpoints reject users without manage_options.
	 */
	public function test_admin_endpoints_require_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		foreach ( [ 'site-type', 'shared-sites', 'secret-key', 'governing-site' ] as $route ) {
			$this->assertSame( 403, $this->request( 'GET', $route )->get_status(), $route );
		}
	}

	/**
	 * The site type can be saved and read back.
	 */
	public function test_site_type_roundtrip(): void {
		$this->assertNull( $this->request( 'GET', 'site-type' )->get_data()['site_type'] );

		$response = $this->request( 'POST', 'site-type', [ 'site_type' => Settings::SITE_TYPE_GOVERNING ] );
		$this->assertTrue( $response->get_data()['success'] );

		$this->assertSame( Settings::SITE_TYPE_GOVERNING, $this->request( 'GET', 'site-type' )->get_data()['site_type'] );
		$this->assertSame( 400, $this->request( 'POST', 'site-type' )->get_status(), 'site_type is required' );
	}

	/**
	 * Saving shared sites assigns missing IDs and persists them; duplicate URLs are rejected.
	 */
	public function test_set_shared_sites_assigns_ids_and_rejects_duplicates(): void {
		$response = $this->request(
			'POST',
			'shared-sites',
			[
				'sites_data' => [
					[
						'id'      => 'brand-a',
						'name'    => 'Brand A',
						'url'     => 'https://brand-a.example',
						'api_key' => 'key-a',
					],
					[
						'name'    => 'Brand B',
						'url'     => 'https://brand-b.example',
						'api_key' => 'key-b',
					],
				],
			]
		);

		$sites = $response->get_data()['sites_data'];
		$this->assertSame( 'brand-a', $sites[0]['id'] );
		$this->assertTrue( wp_is_uuid( $sites[1]['id'] ) );
		$this->assertSame( [ 'https://brand-a.example', 'https://brand-b.example' ], array_keys( Settings::get_shared_sites() ) );
		$this->assertSame( 'Brand B', $this->request( 'GET', 'shared-sites' )->get_data()['shared_sites'][1]['name'] );

		$duplicate = $this->request(
			'POST',
			'shared-sites',
			[
				'sites_data' => [
					[ 'url' => 'https://dup.example' ],
					[ 'url' => 'https://dup.example' ],
				],
			]
		);
		$this->assertSame( 400, $duplicate->get_status() );
		$this->assertSame( 'duplicate_site_url', $duplicate->get_data()['code'] );
		$this->assertCount( 2, Settings::get_shared_sites(), 'Rejected payload must not overwrite stored sites' );
	}

	/**
	 * The secret key endpoint returns the stored key and regenerates it on PUT.
	 */
	public function test_secret_key_get_and_regenerate(): void {
		$key = $this->request( 'GET', 'secret-key' )->get_data()['secret_key'];
		$this->assertSame( Settings::get_api_key(), $key );

		$new_key = $this->request( 'PUT', 'secret-key' )->get_data()['secret_key'];
		$this->assertNotSame( $key, $new_key );
		$this->assertSame( Settings::get_api_key(), $new_key );
	}

	/**
	 * The governing site URL can be read and removed.
	 */
	public function test_governing_site_get_and_remove(): void {
		Settings::set_parent_site_url( 'https://governing.example' );

		$this->assertSame( 'https://governing.example', $this->request( 'GET', 'governing-site' )->get_data()['governing_site_url'] );

		$this->assertTrue( $this->request( 'DELETE', 'governing-site' )->get_data()['success'] );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * A brand site answers a token-authenticated health check from its governing site.
	 */
	public function test_health_check_with_valid_token(): void {
		wp_set_current_user( 0 );
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( 'https://governing.example' );

		$this->assertSame( 401, $this->request( 'GET', 'health-check' )->get_status() );

		$request = new WP_REST_Request( 'GET', '/' . Basic_Options_Controller::NAMESPACE . '/health-check' );
		$request->set_header( 'X-OneDesign-Token', Settings::get_api_key() );
		$request->set_header( 'Origin', 'https://governing.example' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * Dispatch a JSON request to a plugin route.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $route  Route relative to the namespace.
	 * @param array<string,mixed> $body   JSON body.
	 */
	private function request( string $method, string $route, array $body = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/' . Basic_Options_Controller::NAMESPACE . '/' . $route );
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return $this->server->dispatch( $request );
	}
}
