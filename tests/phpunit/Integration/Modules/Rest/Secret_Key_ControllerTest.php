<?php
/**
 * Tests for Secret_Key_Controller.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Secret_Key_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Tests for the /secret-key route.
 */
#[CoversClass( Secret_Key_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
class Secret_Key_ControllerTest extends TestCase {
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

		( new Secret_Key_Controller() )->register_hooks();
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
	 * PUT secret-key regenerates and returns a new key.
	 */
	public function test_regenerate_secret_key_returns_new_key(): void {
		$old_key = Settings::get_api_key();

		// Regenerate via PUT.
		$put_request = new WP_REST_Request( 'PUT', '/onedesign/v1/secret-key' );
		$response    = $this->server->dispatch( $put_request );
		$data        = $response->get_data();

		$this->assertNotEmpty( $data['secret_key'] );
		$this->assertNotSame( $old_key, $data['secret_key'] );
	}
}
