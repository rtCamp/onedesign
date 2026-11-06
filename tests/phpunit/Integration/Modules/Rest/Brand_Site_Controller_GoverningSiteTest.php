<?php
/**
 * Tests for Brand_Site_Controller on a governing site.
 *
 * @package OneDesign\Tests\Integration\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneDesign\Tests\Integration\Modules\Rest;

use OneDesign\Modules\Rest\Abstract_REST_Controller;
use OneDesign\Modules\Rest\Brand_Site_Controller;
use OneDesign\Modules\Settings\Settings;
use OneDesign\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WP_REST_Request;

/**
 * Governing-site scenarios for {@see Brand_Site_Controller}.
 */
#[CoversClass( Brand_Site_Controller::class )]
#[CoversClass( Abstract_REST_Controller::class )]
class Brand_Site_Controller_GoverningSiteTest extends TestCase {
	/**
	 * REST server.
	 */
	private ?\WP_REST_Server $server;

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		( new Brand_Site_Controller() )->register_hooks();
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
	 * Governing site registers the brand-site DELETE route brand sites use to disconnect.
	 */
	public function test_registers_brand_site_delete(): void {
		$routes = $this->server->get_routes();
		$ns     = '/' . Brand_Site_Controller::NAMESPACE;

		$this->assertArrayHasKey( $ns . '/brand-site', $routes );
		$this->assertArrayHasKey( 'DELETE', $routes[ $ns . '/brand-site' ][0]['methods'] );
	}

	/**
	 * A brand site presenting its own token removes itself, and only itself.
	 */
	public function test_remove_brand_site_removes_the_requesting_site(): void {
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example.com',
					'api_key' => 'brand-one-key',
				],
				[
					'name'    => 'Brand Two',
					'url'     => 'https://brand-two.example.com',
					'api_key' => 'brand-two-key',
				],
			]
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://brand-one.example.com' );
		$request->set_header( 'X-OneDesign-Token', 'brand-one-key' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'https://brand-two.example.com/' ], array_keys( Settings::get_shared_sites() ) );
	}

	/**
	 * A brand site cannot remove itself without its own token.
	 */
	public function test_remove_brand_site_rejects_wrong_token(): void {
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example.com',
					'api_key' => 'brand-one-key',
				],
			]
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://brand-one.example.com' );
		$request->set_header( 'X-OneDesign-Token', 'wrong-key' );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertCount( 1, Settings::get_shared_sites() );
	}

	/**
	 * Only a brand site can remove itself, so a logged-in admin (from set_up) without its key is refused.
	 */
	public function test_remove_brand_site_refuses_an_admin_without_a_brand_key(): void {
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand One',
					'url'     => 'https://brand-one.example.com',
					'api_key' => 'brand-one-key',
				],
			]
		);

		$request  = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
		$this->assertCount( 1, Settings::get_shared_sites() );
	}

	/**
	 * A listed brand's X-OneDesign-Site-URL and key identify it. The user is logged out
	 * to prove success comes from the key.
	 */
	public function test_is_brand_request_with_site_url_header_and_key(): void {
		$site_url = 'https://brand.example.com/';
		$api_key  = 'brand-token';
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand Site',
					'url'     => $site_url,
					'api_key' => $api_key,
				],
			]
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$request->set_header( 'X-OneDesign-Token', $api_key );
		$request->set_header( 'X-OneDesign-Site-URL', $site_url );

		$this->assertTrue( ( new Brand_Site_Controller() )->is_brand_request( $request ) );
	}

	/**
	 * A key without X-OneDesign-Site-URL can't identify the brand, even when Origin names it.
	 */
	public function test_is_not_brand_request_without_site_url_header(): void {
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand Site',
					'url'     => 'https://brand.example.com/',
					'api_key' => 'brand-token',
				],
			]
		);

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$request->set_header( 'origin', 'https://brand.example.com' );
		$request->set_header( 'X-OneDesign-Token', 'brand-token' );

		$this->assertFalse( ( new Brand_Site_Controller() )->is_brand_request( $request ) );
	}

	/**
	 * A listed brand's X-OneDesign-Site-URL with the wrong key isn't that brand.
	 */
	public function test_is_not_brand_request_with_wrong_key(): void {
		$site_url = 'https://brand.example.com/';
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Brand Site',
					'url'     => $site_url,
					'api_key' => 'brand-token',
				],
			]
		);

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$request->set_header( 'X-OneDesign-Token', 'wrong-token' );
		$request->set_header( 'X-OneDesign-Site-URL', $site_url );

		$this->assertFalse( ( new Brand_Site_Controller() )->is_brand_request( $request ) );
	}

	/**
	 * A brand in a sub-directory is identified by the X-OneDesign-Site-URL path, since Origin drops it.
	 */
	public function test_remove_brand_site_identifies_sub_directory_brand_from_site_url_header(): void {
		Settings::set_shared_sites(
			[
				[
					'name'    => 'Sibling',
					'url'     => 'https://example.com/sibling',
					'api_key' => 'sibling-key',
				],
				[
					'name'    => 'Brand',
					'url'     => 'https://example.com/brand',
					'api_key' => 'brand-key',
				],
			]
		);

		$request = new WP_REST_Request( 'DELETE', '/onedesign/v1/brand-site' );
		$request->set_header( 'X-OneDesign-Site-URL', 'https://example.com/brand/' );

		// The sibling's key must not authenticate the brand's path.
		$request->set_header( 'X-OneDesign-Token', 'sibling-key' );
		$this->assertGreaterThanOrEqual( 400, $this->server->dispatch( $request )->get_status() );
		$this->assertCount( 2, Settings::get_shared_sites() );

		$request->set_header( 'X-OneDesign-Token', 'brand-key' );
		$this->assertSame( 200, $this->server->dispatch( $request )->get_status() );
		$this->assertSame( [ 'https://example.com/sibling/' ], array_keys( Settings::get_shared_sites() ) );
	}
}
