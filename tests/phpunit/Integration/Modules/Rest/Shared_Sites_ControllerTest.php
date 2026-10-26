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
use OneDesign\Tests\TestCase;
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
}
