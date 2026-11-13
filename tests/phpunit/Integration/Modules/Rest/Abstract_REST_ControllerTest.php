<?php
/**
 * Tests for Abstract_REST_Controller.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Main;
use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * The errors OneDesign's routes send through VIP's edge.
 */
#[CoversClass( Abstract_REST_Controller::class )]
#[CoversClass( Main::class )]
class Abstract_REST_ControllerTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		$this->set_up_governing_site();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		update_option( 'permalink_structure', '/%postname%/' );
		( new \ReflectionProperty( Main::class, 'instance' ) )->setValue( null, null );
		Main::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		( new \ReflectionProperty( Main::class, 'instance' ) )->setValue( null, null );
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE'], $GLOBALS['HTTP_RAW_POST_DATA'] );
		$_GET = [];

		parent::tearDown();
	}

	/**
	 * On VIP, the 502 of an unreachable brand site and the 503 a brand site answers with carry x-vip-reached-origin.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_marks_502_and_503_errors_as_reached_origin_on_vip(): void {
		define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		add_filter( 'pre_http_request', static fn (): \WP_Error => new \WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' ) );
		$server = $this->serve( 'POST', '/shared-sites', [ 'sites_data' => [ $this->new_brand_site() ] ] );

		$this->assertSame( 502, $server->status );
		$this->assertSame( 'true', $server->sent_headers['x-vip-reached-origin'] ?? null );
		$this->assertSame( 'onedesign_health_check_failed', json_decode( $server->sent_body, true )['code'] ?? null );

		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static fn (): array => [
				'response' => [
					'code'    => 503,
					'message' => '',
				],
				'body'     => (string) wp_json_encode( [ 'message' => 'Down for maintenance.' ] ),
				'headers'  => [],
				'cookies'  => [],
			]
		);
		$server = $this->serve( 'GET', '/templates', [ 'site' => 'brand-1' ] );

		$this->assertSame( 503, $server->status );
		$this->assertSame( 'true', $server->sent_headers['x-vip-reached-origin'] ?? null );
		$this->assertSame( 'Down for maintenance.', json_decode( $server->sent_body, true )['message'] ?? null );
	}

	/**
	 * Off VIP, a 502 doesn't get the header.
	 */
	public function test_leaves_502_errors_unmarked_off_vip(): void {
		$filter = static fn (): \WP_Error => new \WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' );
		add_filter( 'pre_http_request', $filter );

		$server = $this->serve( 'POST', '/shared-sites', [ 'sites_data' => [ $this->new_brand_site() ] ] );

		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 502, $server->status );
		$this->assertArrayNotHasKey( 'x-vip-reached-origin', $server->sent_headers );
	}

	/**
	 * Serves a request to a OneDesign route as WordPress serves it over HTTP.
	 *
	 * @param string              $method The method.
	 * @param string              $route  The route, relative to the OneDesign namespace.
	 * @param array<string,mixed> $body   The JSON body, or the query of a GET request.
	 */
	private function serve( string $method, string $route, array $body ): \Spy_REST_Server {
		global $wp_rest_server;
		$wp_rest_server = null;
		$server         = rest_get_server();
		$this->assertInstanceOf( \Spy_REST_Server::class, $server );

		$_SERVER['REQUEST_METHOD'] = $method;
		if ( 'GET' === $method ) {
			$_GET = $body;
		} else {
			$_SERVER['CONTENT_TYPE']       = 'application/json';
			$GLOBALS['HTTP_RAW_POST_DATA'] = (string) wp_json_encode( $body ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$server->serve_request( '/' . Abstract_REST_Controller::NAMESPACE . $route );

		return $server;
	}

	/**
	 * A brand site that isn't stored yet, so saving it runs its health check.
	 *
	 * @return array<string,string>
	 */
	private function new_brand_site(): array {
		return [
			'name'    => 'Brand Two',
			'url'     => 'https://two.example.org',
			'api_key' => 'key-two',
		];
	}
}
