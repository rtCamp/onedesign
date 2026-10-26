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
	 * An admin disconnecting clears the governing site.
	 */
	public function test_remove_governing_site_clears_the_governing_site(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		$response = $this->server->dispatch( new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( Settings::get_parent_site_url() );
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
