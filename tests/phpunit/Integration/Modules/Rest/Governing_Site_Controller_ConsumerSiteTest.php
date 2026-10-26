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
	 * An admin disconnecting clears the governing site.
	 */
	public function test_remove_governing_site_clears_the_governing_site(): void {
		Settings::set_parent_site_url( 'https://governing.example.com' );

		$response = $this->server->dispatch( new WP_REST_Request( 'DELETE', '/onedesign/v1/governing-site' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( Settings::get_parent_site_url() );
	}
}
