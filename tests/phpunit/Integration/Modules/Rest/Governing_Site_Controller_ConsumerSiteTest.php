<?php
/**
 * Tests for Governing_Site_Controller on a brand (consumer) site.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Governing_Site_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Brand-site (consumer) scenarios for {@see Governing_Site_Controller}.
 */
#[CoversClass( Governing_Site_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
class Governing_Site_Controller_ConsumerSiteTest extends TestCase {
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

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		( new Governing_Site_Controller() )->register_hooks();
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
	 * Succeeds even when no governing site was previously set.
	 */
	public function test_remove_governing_site_succeeds_when_already_unset(): void {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );

		$request  = new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Disconnecting tells the governing site to remove this brand site before clearing the local pairing.
	 */
	public function test_remove_governing_site_notifies_governing_site(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		$requested_urls = [];
		$filter         = static function ( $preempt, $args, $url ) use ( &$requested_urls ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$requested_urls[] = $url;
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

		$request  = new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'pre_http_request', $filter );

		$this->assertTrue( $data['governing_site_notified'] );
		$this->assertSame( [ 'https://governing.example.com/wp-json/onedesign/v1/brand-site' ], $requested_urls );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * An unreachable governing site doesn't block the local disconnect, but the admin is told to finish it there.
	 */
	public function test_remove_governing_site_disconnects_locally_when_governing_site_unreachable(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		$filter = static fn () => new \WP_Error( 'http_request_failed', 'cURL error 7' );
		add_filter( 'pre_http_request', $filter );

		$request  = new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'pre_http_request', $filter );

		$this->assertFalse( $data['governing_site_notified'] );
		$this->assertStringContainsString( 'https://governing.example.com', $data['message'] );
		$this->assertStringContainsString( 'could not be notified', $data['message'] );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * A governing site that rejects this site's key may have already removed it, and the warning says so.
	 */
	public function test_remove_governing_site_warns_when_governing_site_does_not_recognize_it(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		$filter = static fn () => [
			'response' => [
				'code'    => 401,
				'message' => 'Unauthorized',
			],
			'body'     => '',
			'headers'  => [],
			'cookies'  => [],
		];
		add_filter( 'pre_http_request', $filter );

		$request  = new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		remove_filter( 'pre_http_request', $filter );

		$this->assertFalse( $data['governing_site_notified'] );
		$this->assertStringContainsString( 'did not recognize', $data['message'] );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * Only a governing site with this site's key can connect it, so a logged-in admin (from set_up) is refused.
	 */
	public function test_connect_refuses_an_admin_without_the_key(): void {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/governing-site' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://governing.example.com' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * A request with this site's key connects it to the sender, identified by
	 * X-OneDesign-Site-URL. The user is logged out so success can only come from the key.
	 */
	public function test_connect_with_the_key_connects_the_sender(): void {
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
		$api_key = Settings::get_api_key();

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/governing-site' );
		$request->set_header( 'X-OneDesign-Token', $api_key );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://governing.example.com' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://governing.example.com', Settings::get_parent_site_url() );
	}

	/**
	 * A brand that is already connected refuses to be repointed to a different governing site.
	 */
	public function test_connect_rejects_a_different_governing_site_when_connected(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );
		$api_key = Settings::get_api_key();
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/governing-site' );
		$request->set_header( 'X-OneDesign-Token', $api_key );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://other.example.com' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'onedesign_already_connected', $response->get_data()['code'] );
		$this->assertSame( 'https://governing.example.com', Settings::get_parent_site_url() );
	}

	/**
	 * The same governing site, with or without a trailing slash, can connect again.
	 */
	public function test_connect_accepts_the_same_governing_site_when_connected(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );
		$api_key = Settings::get_api_key();
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/onedesign/v1/governing-site' );
		$request->set_header( 'X-OneDesign-Token', $api_key );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://governing.example.com/' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'https://governing.example.com', Settings::get_parent_site_url() );
	}

	/**
	 * Brand site registers the governing-site DELETE route.
	 */
	public function test_registers_governing_site_delete_route(): void {
		$routes = $this->server->get_routes();
		$ns     = '/' . Governing_Site_Controller::NAMESPACE;

		$this->assertArrayHasKey( 'DELETE', $routes[ $ns . '/governing-site' ][1]['methods'] );
	}

	/**
	 * The governing site, presenting this site's token, clears the pairing, and isn't told about it in turn.
	 */
	public function test_remove_governing_site_clears_pairing(): void {
		wp_set_current_user( 0 );

		$requested_urls = [];
		$filter         = static function ( $preempt, $args, $url ) use ( &$requested_urls ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
			$requested_urls[] = $url;
			return new \WP_Error( 'blocked', 'Intercepted' );
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		$response = $this->server->dispatch( $this->governing_request( 'DELETE', '/onedesign/v1/governing-site' ) );

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( [], $requested_urls );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * Only the governing site this brand site is connected to can remove it.
	 */
	public function test_remove_governing_site_rejects_other_sites(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://other.example.com' );
		$request->set_header( 'X-OneDesign-Token', Settings::get_api_key() );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'https://governing.example.com', Settings::get_parent_site_url() );
	}

	/**
	 * The governing site can't remove this brand site without its token.
	 */
	public function test_remove_governing_site_rejects_wrong_token(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://governing.example.com' );
		$request->set_header( 'X-OneDesign-Token', 'wrong-key' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'https://governing.example.com', Settings::get_parent_site_url() );
	}

	/**
	 * Builds a request from the governing site this brand site is connected to.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  REST route.
	 *
	 * @return \WP_REST_Request<array<string,mixed>>
	 */
	private function governing_request( string $method, string $route ): WP_REST_Request {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://governing.example.com' );
		$request->set_header( 'X-OneDesign-Token', Settings::get_api_key() );

		return $request;
	}
}
